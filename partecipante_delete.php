<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();
$idPartecipante = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Cascade sulle tabelle ponte (progetto/step/eventi_partecipanti) già gestito dalle FK.
$pdo->prepare('DELETE FROM partecipanti WHERE id_partecipante = ?')->execute([$idPartecipante]);

redirect('partecipanti.php');
