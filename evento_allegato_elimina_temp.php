<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$idTemporaneo = (string) ($_POST['id_temporaneo'] ?? '');
$nomeSalvato  = basename((string) ($_POST['nome_salvato'] ?? ''));

if (!preg_match('/^[a-f0-9]{32}$/', $idTemporaneo) || $nomeSalvato === '') {
    echo json_encode(['ok' => false, 'error' => 'Richiesta non valida.']);
    exit;
}

$cartellaTemp = __DIR__ . '/allegati/_temp/' . $idTemporaneo;
$manifestPath = $cartellaTemp . '/manifest.json';

if (is_file($manifestPath)) {
    $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
    unset($manifest[$nomeSalvato]);
    file_put_contents($manifestPath, json_encode($manifest));
}

@unlink($cartellaTemp . '/' . $nomeSalvato);

echo json_encode(['ok' => true]);
