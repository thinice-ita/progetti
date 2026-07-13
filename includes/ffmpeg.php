<?php
/**
 * Compressione e segmentazione audio tramite ffmpeg (eseguito come processo esterno),
 * necessarie per stare sotto i limiti dell'API OpenAI (25MB e ~1400 secondi per richiesta)
 * anche con registrazioni di riunioni lunghe.
 *
 * I comandi vengono eseguiti con proc_open() passando gli argomenti come array, non come
 * stringa: su Windows, escapeshellarg() sostituisce ogni "%" con uno spazio (per bloccare
 * l'espansione di variabili di cmd.exe), il che romperebbe pattern come "%03d" usati da
 * ffmpeg per numerare i segmenti. Con l'array, PHP invoca il processo senza passare dalla
 * shell, evitando il problema.
 *
 * L'output di ffmpeg (stdout/stderr) viene reindirizzato su un file temporaneo anziché
 * su pipe: ffmpeg scrive molte righe di avanzamento su stderr durante la conversione, e
 * se il buffer del pipe si riempie (file audio grandi/lunghi) il processo si blocca in
 * attesa che qualcuno lo svuoti, causando un deadlock se lo leggiamo solo a fine processo.
 * Scrivendo su file non c'è alcun limite di buffer e il problema non si presenta.
 */

/**
 * Esegue un comando esterno passando gli argomenti come array (nessuna shell coinvolta)
 * e restituisce codice di uscita e output combinato (stdout + stderr).
 *
 * @param string[] $comando
 * @return array{exitCode: int, output: string}
 */
function eseguiComandoEsterno(array $comando): array
{
    $fileLog = tempnam(sys_get_temp_dir(), 'ffmpeg_log_');

    $descrittori = [
        0 => ['pipe', 'r'],
        1 => ['file', $fileLog, 'w'],
        2 => ['file', $fileLog, 'a'],
    ];

    $processo = proc_open($comando, $descrittori, $pipe);

    if (!is_resource($processo)) {
        @unlink($fileLog);

        return ['exitCode' => -1, 'output' => 'Impossibile avviare il processo esterno.'];
    }

    fclose($pipe[0]);
    $codiceUscita = proc_close($processo);

    $output = is_file($fileLog) ? (string) file_get_contents($fileLog) : '';
    @unlink($fileLog);

    return ['exitCode' => $codiceUscita, 'output' => trim($output)];
}

/**
 * Verifica che il binario ffmpeg configurato sia disponibile ed eseguibile,
 * provando a lanciare "ffmpeg -version".
 */
function ffmpegDisponibile(string $percorsoFfmpeg): bool
{
    $risultato = eseguiComandoEsterno([$percorsoFfmpeg, '-version']);

    return $risultato['exitCode'] === 0;
}

function messaggioFfmpegNonDisponibile(string $percorsoFfmpeg): string
{
    return 'ffmpeg non è disponibile o non è stato trovato ' .
        "(percorso/comando configurato: \"{$percorsoFfmpeg}\"). " .
        'Installa ffmpeg (https://ffmpeg.org/download.html) e assicurati che sia nel PATH di sistema, ' .
        'oppure imposta il percorso completo nella costante FFMPEG_PATH in config.php.';
}

/**
 * Comprime un file audio in mp3 mono, 16kHz, 32kbps tramite ffmpeg.
 *
 * @param string $percorsoFfmpeg        Percorso (o nome, se nel PATH) dell'eseguibile ffmpeg
 * @param string $percorsoOriginale     File audio originale da comprimere
 * @param string $percorsoDestinazione  File mp3 compresso di destinazione
 * @throws Exception  Se ffmpeg non è disponibile o la compressione fallisce, con un messaggio
 *                     chiaro (mai un fallimento silenzioso)
 */
function comprimiAudio(string $percorsoFfmpeg, string $percorsoOriginale, string $percorsoDestinazione): void
{
    if (!ffmpegDisponibile($percorsoFfmpeg)) {
        throw new Exception(messaggioFfmpegNonDisponibile($percorsoFfmpeg));
    }

    // -vn: scarta eventuale traccia video/copertina; -ac 1: mono; -ar 16000: 16kHz; -b:a 32k: 32kbps
    $risultato = eseguiComandoEsterno([
        $percorsoFfmpeg, '-y', '-i', $percorsoOriginale,
        '-vn', '-ac', '1', '-ar', '16000', '-b:a', '32k', '-f', 'mp3', $percorsoDestinazione,
    ]);

    if ($risultato['exitCode'] !== 0 || !is_file($percorsoDestinazione) || filesize($percorsoDestinazione) === 0) {
        throw new Exception("Compressione audio con ffmpeg fallita (codice uscita {$risultato['exitCode']}): {$risultato['output']}");
    }
}

/**
 * Divide un file audio in segmenti di durata massima $durataSegmentoSecondi.
 *
 * Necessario perché gpt-4o-transcribe accetta al massimo ~1400 secondi per richiesta:
 * per registrazioni più lunghe (riunioni di 30-60+ minuti) l'audio va spezzato in più parti,
 * trascritte separatamente. Se il file è già più corto del segmento, ffmpeg genera un solo
 * segmento (equivalente al file originale, nessuna perdita di contenuto).
 *
 * @return string[] Elenco ordinato dei percorsi dei segmenti generati
 * @throws Exception Se ffmpeg non è disponibile o la segmentazione fallisce
 */
function segmentaAudio(string $percorsoFfmpeg, string $percorsoFile, string $cartellaOutput, int $durataSegmentoSecondi = 1200): array
{
    if (!ffmpegDisponibile($percorsoFfmpeg)) {
        throw new Exception(messaggioFfmpegNonDisponibile($percorsoFfmpeg));
    }

    if (!is_dir($cartellaOutput)) {
        mkdir($cartellaOutput, 0775, true);
    }

    $modelloNomeFile = $cartellaOutput . '/segmento_%03d.mp3';

    // -f segment: divide l'audio in più file senza ricodificarlo (-c copy = veloce e senza perdita)
    $risultato = eseguiComandoEsterno([
        $percorsoFfmpeg, '-y', '-i', $percorsoFile,
        '-f', 'segment', '-segment_time', (string) $durataSegmentoSecondi,
        '-c', 'copy', '-reset_timestamps', '1', $modelloNomeFile,
    ]);

    $segmenti = glob($cartellaOutput . '/segmento_*.mp3');
    sort($segmenti);

    if ($risultato['exitCode'] !== 0 || empty($segmenti)) {
        throw new Exception("Segmentazione audio con ffmpeg fallita (codice uscita {$risultato['exitCode']}): {$risultato['output']}");
    }

    return $segmenti;
}

/**
 * Deriva il percorso di ffprobe a partire da quello configurato per ffmpeg (i due
 * eseguibili si trovano normalmente nella stessa cartella "bin" di una distribuzione ffmpeg).
 */
function percorsoFfprobeDaFfmpeg(string $percorsoFfmpeg): string
{
    if (stripos($percorsoFfmpeg, 'ffmpeg') !== false) {
        return preg_replace('/ffmpeg(\.exe)?$/i', 'ffprobe$1', $percorsoFfmpeg);
    }

    return 'ffprobe';
}

/**
 * Prova a leggere la data di registrazione dai metadati del file audio (tag comuni
 * come "creation_time", usati da molte app di registrazione vocale/riunioni).
 * Restituisce un timestamp Unix, oppure null se ffprobe non è disponibile o
 * il file non contiene alcun tag di data riconoscibile.
 */
function estraiDataRegistrazione(string $percorsoFfmpeg, string $percorsoFile): ?int
{
    $percorsoFfprobe = percorsoFfprobeDaFfmpeg($percorsoFfmpeg);

    $risultato = eseguiComandoEsterno([
        $percorsoFfprobe, '-v', 'quiet', '-print_format', 'json', '-show_format', $percorsoFile,
    ]);

    if ($risultato['exitCode'] !== 0) {
        return null;
    }

    $dati = json_decode($risultato['output'], true);
    $tag = $dati['format']['tags'] ?? null;

    if (!is_array($tag)) {
        return null;
    }

    // I tag hanno maiuscole/minuscole variabili a seconda del formato/app di registrazione
    $tagMinuscole = array_change_key_case($tag, CASE_LOWER);

    $possibiliChiavi = ['creation_time', 'date', 'com.apple.quicktime.creationdate', 'date_recorded'];

    foreach ($possibiliChiavi as $chiave) {
        if (!empty($tagMinuscole[$chiave])) {
            $timestamp = strtotime($tagMinuscole[$chiave]);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }
    }

    return null;
}
