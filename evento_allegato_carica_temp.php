<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

const ESTENSIONI_ALLEGATO_CONSENTITE = [
    'pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx',
    'ppt', 'pptx', 'txt', 'zip', 'msg', 'eml', 'ods', 'odt', 'csv',
];

$idTemporaneo = (string) ($_POST['id_temporaneo'] ?? '');

if (!preg_match('/^[a-f0-9]{32}$/', $idTemporaneo)) {
    echo json_encode(['ok' => false, 'error' => 'Identificativo temporaneo non valido.']);
    exit;
}

if (!isset($_FILES['allegati']) || !is_array($_FILES['allegati']['name'])) {
    echo json_encode(['ok' => false, 'error' => 'Nessun file ricevuto.']);
    exit;
}

$cartellaTemp = __DIR__ . '/allegati/_temp/' . $idTemporaneo;
if (!is_dir($cartellaTemp)) {
    mkdir($cartellaTemp, 0775, true);
}

$manifestPath = $cartellaTemp . '/manifest.json';
$manifest     = is_file($manifestPath) ? (json_decode((string) file_get_contents($manifestPath), true) ?: []) : [];

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
        $avvisi[] = "\"{$nomeOriginale}\" risulta vuoto (0 byte) e non è stato allegato. " .
            "Se l'hai trascinato direttamente da un client di posta (es. Thunderbird/Outlook), è una " .
            "limitazione nota di quel trascinamento: salva prima il file/l'email su disco e poi allegalo da lì.";
        continue;
    }

    $nomeSalvato = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . ($estensione !== '' ? '.' . $estensione : '');

    if (move_uploaded_file($tmpName, $cartellaTemp . '/' . $nomeSalvato)) {
        $manifest[$nomeSalvato] = $nomeOriginale;
        $allegatiSalvati[]      = ['nome_salvato' => $nomeSalvato, 'nome' => $nomeOriginale];
    } else {
        $avvisi[] = "\"{$nomeOriginale}\": impossibile salvare il file sul server.";
    }
}

file_put_contents($manifestPath, json_encode($manifest));

echo json_encode(['ok' => true, 'allegati' => $allegatiSalvati, 'avvisi' => $avvisi]);
