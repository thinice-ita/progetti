<?php
/**
 * Riceve l'audio, lo comprime e trascrive, genera la sintesi + titolo con l'AI,
 * e crea un evento di tipo "registrazione" nel progetto (dentro uno step se indicato,
 * altrimenti libero), con audio e trascrizione come allegati. Output progressivo
 * (flush) come nel trascrittore standalone.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';
require __DIR__ . '/includes/openai.php';
require __DIR__ . '/includes/ffmpeg.php';

set_time_limit(600);

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

const ESTENSIONI_VIETATE = [
    'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
    'exe', 'bat', 'cmd', 'sh', 'js', 'html', 'htm', 'py', 'pl', 'cgi', 'asp', 'aspx',
];

function mostraErroreRegistrazione(string $messaggio, int $fkProgetto, ?int $fkStep): void
{
    $linkRiprova = $fkStep !== null
        ? 'registrazione_form.php?fk_step=' . $fkStep
        : 'registrazione_form.php?fk_progetto=' . $fkProgetto;
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Errore registrazione</title>
        <?php include __DIR__ . '/includes/stile.php'; ?>
    </head>
    <body>
    <div class="contenitore">
        <h1>Errore durante il caricamento</h1>
        <div class="errore"><?= h($messaggio) ?></div>
        <a class="link-indietro" href="<?= h($linkRiprova) ?>">&larr; Riprova</a>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$pdo = dbConnect();

$fkProgetto  = isset($_POST['fk_progetto']) ? (int) $_POST['fk_progetto'] : 0;
$fkStepInput = trim((string) ($_POST['fk_step'] ?? ''));
$fkStep      = $fkStepInput === '' ? null : (int) $fkStepInput;

$stmt = $pdo->prepare('SELECT titolo FROM progetti WHERE id_progetto = ?');
$stmt->execute([$fkProgetto]);
$progettoRiga = $stmt->fetch();
if (!$progettoRiga) {
    mostraErroreRegistrazione('Progetto non trovato.', $fkProgetto, $fkStep);
}

$nomeStep = null;

if ($fkStep !== null) {
    $stmt = $pdo->prepare('SELECT nome FROM step WHERE id_step = ? AND fk_progetto = ?');
    $stmt->execute([$fkStep, $fkProgetto]);
    $stepRiga = $stmt->fetch();
    if (!$stepRiga) {
        mostraErroreRegistrazione('Step non valido per questo progetto.', $fkProgetto, $fkStep);
    }
    $nomeStep = $stepRiga['nome'];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['audio'])) {
    mostraErroreRegistrazione('Nessun file audio ricevuto.', $fkProgetto, $fkStep);
}

$file = $_FILES['audio'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    mostraErroreRegistrazione('Errore durante il caricamento del file (codice ' . $file['error'] . ').', $fkProgetto, $fkStep);
}

$nomeOriginale = basename($file['name']);
$estensione    = strtolower(pathinfo($nomeOriginale, PATHINFO_EXTENSION));

if (in_array($estensione, ESTENSIONI_VIETATE, true)) {
    mostraErroreRegistrazione('Tipo di file non consentito.', $fkProgetto, $fkStep);
}

if (!is_uploaded_file($file['tmp_name'])) {
    mostraErroreRegistrazione('Upload non valido.', $fkProgetto, $fkStep);
}

$cartellaUpload = __DIR__ . '/uploads';
if (!is_dir($cartellaUpload)) {
    mkdir($cartellaUpload, 0775, true);
}

$nomeFileSalvato = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ($estensione !== '' ? '.' . $estensione : '');
$percorsoFile    = $cartellaUpload . '/' . $nomeFileSalvato;

if (!move_uploaded_file($file['tmp_name'], $percorsoFile)) {
    mostraErroreRegistrazione('Impossibile salvare il file caricato sul server.', $fkProgetto, $fkStep);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Elaborazione registrazione in corso</title>
    <?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1>Elaborazione registrazione</h1>
    <div class="successo">Upload completato, elaborazione in corso...</div>
    <?php flush(); ?>

    <?php
    $segmenti         = [];
    $cartellaSegmenti = null;

    try {
        $timestampRegistrazione = estraiDataRegistrazione(FFMPEG_PATH, $percorsoFile);
        $dataEventoSql = $timestampRegistrazione !== null
            ? date('Y-m-d H:i:s', $timestampRegistrazione)
            : date('Y-m-d H:i:s');

        echo '<div class="stato">1/4 - Compressione audio in corso (ffmpeg)...</div>';
        flush();

        $percorsoFileCompresso = $cartellaUpload . '/' . pathinfo($nomeFileSalvato, PATHINFO_FILENAME) . '_compresso.mp3';
        comprimiAudio(FFMPEG_PATH, $percorsoFile, $percorsoFileCompresso);

        $dimensioneMassimaBytes = 25 * 1024 * 1024;
        if (filesize($percorsoFileCompresso) > $dimensioneMassimaBytes) {
            throw new Exception(
                'Il file compresso supera comunque i 25MB consentiti dall\'API OpenAI ' .
                '(registrazione troppo lunga). Prova a dividere la registrazione in parti più corte.'
            );
        }

        echo '<div class="stato">Compressione completata (' . round(filesize($percorsoFileCompresso) / 1024 / 1024, 2) . ' MB).</div>';
        flush();

        echo '<div class="stato">2/4 - Trascrizione audio in corso (può richiedere qualche minuto per file lunghi)...</div>';
        flush();

        $cartellaSegmenti = $cartellaUpload . '/' . pathinfo($nomeFileSalvato, PATHINFO_FILENAME) . '_segmenti';
        $segmenti = segmentaAudio(FFMPEG_PATH, $percorsoFileCompresso, $cartellaSegmenti, 1200);
        $totaleSegmenti = count($segmenti);

        $partiTrascrizione = [];
        foreach ($segmenti as $indice => $percorsoSegmento) {
            echo '<div class="stato">Trascrizione parte ' . ($indice + 1) . '/' . $totaleSegmenti . '...</div>';
            flush();
            $partiTrascrizione[] = trascriviAudio($percorsoSegmento, OPENAI_API_KEY);
        }

        $trascrizione = implode("\n\n", $partiTrascrizione);

        echo '<div class="stato">Trascrizione completata.</div>';
        flush();

        echo '<div class="stato">3/4 - Generazione sintesi in corso...</div>';
        flush();

        $rispostaModello = generaSintesi($trascrizione, OPENAI_API_KEY);
        [$titoloRiunione, $corpoSintesi] = estraiTitoloESintesi($rispostaModello);

        echo '<div class="stato">Sintesi generata.</div>';
        flush();

        echo '<div class="stato">4/4 - Salvataggio evento nel progetto...</div>';
        flush();

        $stmt = $pdo->prepare(
            "INSERT INTO eventi (fk_progetto, fk_step, tipo, data_evento, titolo, testo) VALUES (?, ?, 'registrazione', ?, ?, ?)"
        );
        $stmt->execute([$fkProgetto, $fkStep, $dataEventoSql, $titoloRiunione, $corpoSintesi]);
        $idEvento = (int) $pdo->lastInsertId();
        impostaDataInizioStepSeVuota($pdo, $fkStep);

        $stmtTask = $pdo->prepare('INSERT INTO eventi_task (fk_evento, testo) VALUES (?, ?)');
        foreach (estraiTaskDaTesto($corpoSintesi) as $testoTask) {
            $stmtTask->execute([$idEvento, mb_substr($testoTask, 0, 500)]);
        }

        $cartellaRelativa = abbreviaPerCartella($progettoRiga['titolo'])
            . '/' . ($nomeStep !== null ? abbreviaPerCartella($nomeStep) : '_generale');
        $cartellaAllegati = __DIR__ . '/allegati/' . $cartellaRelativa;
        mkdir($cartellaAllegati, 0775, true);

        $baseNomeFile = trovaBaseFileUnivoca(
            $cartellaAllegati,
            date('Ymd', $timestampRegistrazione ?? time()) . '_' . abbreviaPerCartella($titoloRiunione, 30),
            ['mp3', 'txt']
        );

        $nomeAudioFinale = $baseNomeFile . '.mp3';
        rename($percorsoFileCompresso, $cartellaAllegati . '/' . $nomeAudioFinale);

        $nomeTrascrizioneFinale = $baseNomeFile . '.txt';
        file_put_contents($cartellaAllegati . '/' . $nomeTrascrizioneFinale, $trascrizione);

        $stmt = $pdo->prepare(
            'INSERT INTO eventi_allegati (fk_evento, nome_file_originale, nome_file_salvato, cartella_relativa, ruolo) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$idEvento, $nomeOriginale, $nomeAudioFinale, $cartellaRelativa, 'audio']);
        $stmt->execute([$idEvento, 'trascrizione.txt', $nomeTrascrizioneFinale, $cartellaRelativa, 'trascrizione']);

        // Pulizia file temporanei (l'audio compresso è già stato spostato in allegati/)
        @unlink($percorsoFile);
        foreach ($segmenti as $segmento) {
            @unlink($segmento);
        }
        if ($cartellaSegmenti !== null) {
            @rmdir($cartellaSegmenti);
        }

        echo '<div class="successo">Evento creato: ' . h($titoloRiunione) . '</div>';
        flush();
        ?>

        <h2>Sintesi generata</h2>
        <div class="box-testo"><?= nl2br(h($corpoSintesi)) ?></div>

        <a class="link-indietro" href="progetto_view.php?id=<?= $fkProgetto ?>">&larr; Torna al progetto</a>

        <?php
    } catch (Throwable $e) {
        echo '<div class="errore">Si è verificato un errore durante l\'elaborazione: ' . h($e->getMessage()) . '</div>';
        $linkRiprova = $fkStep !== null ? 'registrazione_form.php?fk_step=' . $fkStep : 'registrazione_form.php?fk_progetto=' . $fkProgetto;
        echo '<a class="link-indietro" href="' . h($linkRiprova) . '">&larr; Riprova</a>';
    }
    ?>
</div>
</body>
</html>
