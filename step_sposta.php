<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();
$idStep    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$direzione = $_GET['direzione'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM step WHERE id_step = ?');
$stmt->execute([$idStep]);
$step = $stmt->fetch();

if (!$step) {
    redirect('index.php');
}

$fkProgetto = (int) $step['fk_progetto'];

$stmt = $pdo->prepare('SELECT id_step, ordine FROM step WHERE fk_progetto = ? ORDER BY ordine, id_step');
$stmt->execute([$fkProgetto]);
$stepOrdinati = $stmt->fetchAll();

$indice = null;
foreach ($stepOrdinati as $i => $s) {
    if ((int) $s['id_step'] === $idStep) {
        $indice = $i;
        break;
    }
}

$indiceVicino = $direzione === 'su' ? $indice - 1 : $indice + 1;

// Scambia il valore di "ordine" con lo step adiacente: basta ai due coinvolti,
// senza dover rinumerare l'intera lista.
if ($indice !== null && $indiceVicino >= 0 && $indiceVicino < count($stepOrdinati)) {
    $vicino = $stepOrdinati[$indiceVicino];
    $pdo->prepare('UPDATE step SET ordine = ? WHERE id_step = ?')->execute([$vicino['ordine'], $idStep]);
    $pdo->prepare('UPDATE step SET ordine = ? WHERE id_step = ?')->execute([$stepOrdinati[$indice]['ordine'], $vicino['id_step']]);
}

redirect('progetto_view.php?id=' . $fkProgetto . '#step-' . $idStep);
