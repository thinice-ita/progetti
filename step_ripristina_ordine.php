<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();
$idProgetto = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT 1 FROM progetti WHERE id_progetto = ?');
$stmt->execute([$idProgetto]);

if ($stmt->fetch()) {
    // Ripristina l'ordine a quello di creazione (per id_step), annullando
    // qualunque spostamento fatto con le frecce su/giù.
    $pdo->prepare('UPDATE step SET ordine = id_step WHERE fk_progetto = ?')->execute([$idProgetto]);
}

redirect('progetto_view.php?id=' . $idProgetto);
