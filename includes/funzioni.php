<?php
/**
 * Funzioni di utilità condivise tra le pagine.
 */

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function formattaData(?string $data): string
{
    if (!$data) {
        return '';
    }
    $ts = strtotime($data);

    return $ts ? date('d/m/Y', $ts) : '';
}

function formattaDataOra(?string $data): string
{
    if (!$data) {
        return '';
    }
    $ts = strtotime($data);

    return $ts ? date('d/m/Y H:i', $ts) : '';
}

/**
 * Abbreviazione italiana (3 lettere maiuscole) del mese 1-12, per le etichette
 * della linea del tempo (non si usa date()/setlocale per non dipendere dalle
 * locale installate sul sistema).
 */
function meseAbbreviato(int $mese): string
{
    static $mesi = ['GEN', 'FEB', 'MAR', 'APR', 'MAG', 'GIU', 'LUG', 'AGO', 'SET', 'OTT', 'NOV', 'DIC'];

    return $mesi[$mese - 1] ?? '';
}

/**
 * Data breve "12 mag" (giorno + mese abbreviato minuscolo), per le etichette
 * compatte della linea del tempo.
 */
function formattaDataBreve(?string $data): string
{
    if (!$data) {
        return '';
    }
    $ts = strtotime($data);
    if (!$ts) {
        return '';
    }

    return ((int) date('j', $ts)) . ' ' . mb_strtolower(meseAbbreviato((int) date('n', $ts)));
}

/**
 * Testo della pillola dei task di un evento ("5 task · 3 da fare"), o stringa vuota se non ci sono task.
 */
function etichettaPillolaTask(array $task): string
{
    if (!$task) {
        return '';
    }

    $nonFatti = count(array_filter($task, static fn(array $t): bool => !$t['fatto']));

    return count($task) . ' task · ' . ($nonFatti > 0 ? $nonFatti . ' da fare' : 'tutti fatti');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Restituisce la classe CSS del colore tenue assegnato a uno step, in base alla
 * sua posizione (ordine di creazione). I colori si ripetono ciclicamente su una
 * tavolozza di 6 tinte, così step diversi sono visivamente distinguibili.
 */
function classeColoreStep(int $indice): string
{
    return 'step-colore-' . ($indice % 6);
}

/**
 * Riduce un testo libero (titolo progetto, nome step, titolo AI...) a una forma
 * breve utilizzabile come nome di cartella/file: traslittera gli accenti, rimuove
 * i caratteri non validi su Windows, e tronca a $maxLunghezza troncando all'ultima
 * parola intera (se possibile) invece che a metà parola.
 */
function abbreviaPerCartella(string $testo, int $maxLunghezza = 25): string
{
    static $mappaAccenti = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        'À' => 'A', 'È' => 'E', 'É' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
    ];

    $testo = strtr($testo, $mappaAccenti);
    $testo = preg_replace('/[\\\\\/:*?"<>|]/', '', $testo);
    $testo = preg_replace('/[\s_]+/', '_', trim($testo));
    $testo = trim($testo, "_ \t\n\r\0\x0B.");

    if ($testo === '') {
        return 'senza_nome';
    }

    if (mb_strlen($testo) <= $maxLunghezza) {
        return $testo;
    }

    $troncato        = mb_substr($testo, 0, $maxLunghezza);
    $ultimoSeparatore = mb_strrpos($troncato, '_');

    if ($ultimoSeparatore !== false && $ultimoSeparatore >= (int) ($maxLunghezza * 0.5)) {
        $troncato = mb_substr($troncato, 0, $ultimoSeparatore);
    }

    return rtrim($troncato, '_');
}

/**
 * Sottocartella di allegati/ in cui si trova fisicamente un allegato: quella
 * "parlante" salvata in cartella_relativa se presente (registrazioni/trascrizioni),
 * altrimenti la vecchia convenzione per id evento (allegati generici).
 */
function cartellaAllegato(?string $cartellaRelativa, int $idEvento): string
{
    return $cartellaRelativa !== null && $cartellaRelativa !== ''
        ? $cartellaRelativa
        : (string) $idEvento;
}

/**
 * Trova un nome-base di file libero in $cartella, aggiungendo un suffisso
 * incrementale (_2, _3, ...) se $base con una qualsiasi delle $estensioni esiste già.
 * Usato per non sovrascrivere mai un file esistente e per tenere allineato lo
 * stesso suffisso su più file "gemelli" (es. audio + trascrizione dello stesso evento).
 */
function trovaBaseFileUnivoca(string $cartella, string $base, array $estensioni): string
{
    $collide = static function (string $candidato) use ($cartella, $estensioni): bool {
        foreach ($estensioni as $estensione) {
            if (is_file($cartella . '/' . $candidato . '.' . $estensione)) {
                return true;
            }
        }

        return false;
    };

    $candidato = $base;
    $contatore = 2;

    while ($collide($candidato)) {
        $candidato = $base . '_' . $contatore;
        $contatore++;
    }

    return $candidato;
}

/**
 * Imposta step.data_inizio alla data odierna, ma solo se non è già valorizzata:
 * va chiamata ogni volta che un evento finisce assegnato a uno step (creato lì
 * dentro, spostato via drag&drop, o generato da una registrazione), così la
 * prima volta che succede la data si fissa da sola e non viene più toccata.
 */
function impostaDataInizioStepSeVuota(PDO $pdo, ?int $idStep): void
{
    if ($idStep === null) {
        return;
    }

    $pdo->prepare('UPDATE step SET data_inizio = CURDATE() WHERE id_step = ? AND data_inizio IS NULL')
        ->execute([$idStep]);
}

/**
 * Applica un cambio di stato a uno step esistente: se lo stato è invariato non fa
 * nulla. Se cambia, registra la transizione in step_storico_stato (uno step può
 * essere chiuso e riaperto più volte: si vuole tenerne traccia completa, non solo
 * l'ultima data) e calcola il valore da salvare in step.data_chiusura:
 *  - passaggio A "completato": la data indicata in $dataChiusuraInput se presente,
 *    altrimenti la data odierna;
 *  - passaggio VIA da "completato" (riapertura): la data indicata in
 *    $dataChiusuraInput se l'utente l'ha valorizzata esplicitamente nel form,
 *    altrimenti NULL (uno step non chiuso non ha una data di chiusura).
 *
 * @return string|null Valore da salvare in step.data_chiusura (formato Y-m-d), o null.
 */
function applicaCambioStatoStep(PDO $pdo, int $idStep, string $statoVecchio, string $statoNuovo, ?string $dataChiusuraInput): ?string
{
    $dataChiusuraInput = $dataChiusuraInput !== null ? trim($dataChiusuraInput) : '';

    if ($statoVecchio === $statoNuovo) {
        return $dataChiusuraInput !== '' ? $dataChiusuraInput : null;
    }

    $pdo->prepare('INSERT INTO step_storico_stato (fk_step, stato_precedente, stato_nuovo) VALUES (?,?,?)')
        ->execute([$idStep, $statoVecchio, $statoNuovo]);

    if ($statoNuovo === 'completato') {
        return $dataChiusuraInput !== '' ? $dataChiusuraInput : date('Y-m-d');
    }

    return $dataChiusuraInput !== '' ? $dataChiusuraInput : null;
}

/**
 * Estrae l'elenco dei task dalla sezione "## Task assegnati" generata dall'AI
 * (vedi includes/openai.php), nel formato "- Nome persona - descrizione del task"
 * per riga. Ignora la sezione se assente e la riga segnaposto "Nessun task assegnato".
 *
 * @return string[] Elenco dei task (testo della riga, senza il trattino iniziale)
 */
function estraiTaskDaTesto(string $testo): array
{
    if (!preg_match('/^##\s*Task assegnati\s*$(.*?)(?=^##\s|\z)/ms', $testo, $corrispondenza)) {
        return [];
    }

    $task = [];

    foreach (preg_split('/\r\n|\r|\n/', $corrispondenza[1]) as $riga) {
        $riga = trim($riga);
        if ($riga === '' || $riga[0] !== '-') {
            continue;
        }

        $testoTask = trim(substr($riga, 1));
        if ($testoTask === '' || stripos($testoTask, 'nessun task assegnato') === 0) {
            continue;
        }

        $task[] = $testoTask;
    }

    return $task;
}
