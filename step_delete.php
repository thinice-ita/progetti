<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();
$idStep = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT fk_progetto FROM step WHERE id_step = ?');
$stmt->execute([$idStep]);
$riga = $stmt->fetch();

if ($riga) {
    $fkProgetto = (int) $riga['fk_progetto'];

    // Gli eventi collegati NON vengono cancellati: il vincolo fk_evento_step (ON DELETE SET NULL)
    // li rende automaticamente "liberi", restando nel progetto.
    $pdo->prepare('DELETE FROM step WHERE id_step = ?')->execute([$idStep]);
    redirect('progetto_view.php?id=' . $fkProgetto);
}

redirect('index.php');
