<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

header('Content-Type: application/json; charset=utf-8');

const ESTENSIONI_ALLEGATO_CONSENTITE = [
    'pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx',
    'ppt', 'pptx', 'txt', 'zip', 'msg', 'eml', 'ods', 'odt', 'csv',
];

$pdo = dbConnect();

$idEvento = isset($_POST['fk_evento']) ? (int) $_POST['fk_evento'] : 0;

$stmt = $pdo->prepare('SELECT id_evento FROM eventi WHERE id_evento = ?');
$stmt->execute([$idEvento]);

if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Evento non trovato.']);
    exit;
}

if (!isset($_FILES['allegati']) || !is_array($_FILES['allegati']['name'])) {
    echo json_encode(['ok' => false, 'error' => 'Nessun file ricevuto.']);
    exit;
}

$allegatiSalvati = [];
$avvisi          = [];

foreach ($_FILES['allegati']['name'] as $indice => $nomeOriginaleGrezzo) {
    if ($_FILES['allegati']['error'][$indice] === UPLOAD_ERR_NO_FILE) {
        continue;
    }

    $nomeOriginale = basename($nomeOriginaleGrezzo);

    if ($_FILES['allegati']['error'][$indice] !== UPLOAD_ERR_OK) {
        $avvisi[] = "\"{$nomeOriginale}\": errore durante il caricamento (codice {$_FILES['allegati']['error'][$indice]}).";
        continue;
    }

    $tmpName    = $_FILES['allegati']['tmp_name'][$indice];
    $estensione = strtolower(pathinfo($nomeOriginale, PATHINFO_EXTENSION));

    if (!in_array($estensione, ESTENSIONI_ALLEGATO_CONSENTITE, true)) {
        $avvisi[] = "\"{$nomeOriginale}\": tipo di file non consentito.";
        continue;
    }

    if (!is_uploaded_file($tmpName)) {
        $avvisi[] = "\"{$nomeOriginale}\": upload non valido.";
        continue;
    }

    if ((int) $_FILES['allegati']['size'][$indice] === 0) {
        // Trascinare un'email direttamente da un client come Thunderbird/Outlook produce spesso
        // un file da 0 byte: il client non materializza il contenuto in tempo per il browser.
        // Va salvata prima su disco e allegata da lì.
        $avvisi[] = "\"{$nomeOriginale}\" risulta vuoto (0 byte) e non è stato allegato. " .
            "Se l'hai trascinato direttamente da un client di posta (es. Thunderbird/Outlook), è una " .
            "limitazione nota di quel trascinamento: salva prima il file/l'email su disco e poi allegalo da lì.";
        continue;
    }

    $cartellaEvento = __DIR__ . '/allegati/' . $idEvento;
    if (!is_dir($cartellaEvento)) {
        mkdir($cartellaEvento, 0775, true);
    }

    $nomeSalvato = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . ($estensione !== '' ? '.' . $estensione : '');

    if (move_uploaded_file($tmpName, $cartellaEvento . '/' . $nomeSalvato)) {
        $stmt = $pdo->prepare(
            'INSERT INTO eventi_allegati (fk_evento, nome_file_originale, nome_file_salvato, ruolo) VALUES (?,?,?,?)'
        );
        $stmt->execute([$idEvento, $nomeOriginale, $nomeSalvato, 'generico']);

        $allegatiSalvati[] = [
            'id_allegato' => (int) $pdo->lastInsertId(),
            'nome'        => $nomeOriginale,
            'url'         => 'allegati/' . $idEvento . '/' . rawurlencode($nomeSalvato),
        ];
    } else {
        $avvisi[] = "\"{$nomeOriginale}\": impossibile salvare il file sul server.";
    }
}

echo json_encode(['ok' => true, 'allegati' => $allegatiSalvati, 'avvisi' => $avvisi]);
