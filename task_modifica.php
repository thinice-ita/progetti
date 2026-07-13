<?php
/**
 * Endpoint AJAX: modifica il testo (titolo) di un task esistente.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = dbConnect();

$idTask = isset($_POST['id_task']) ? (int) $_POST['id_task'] : 0;
$testo  = trim((string) ($_POST['testo'] ?? ''));

if ($testo === '') {
    echo json_encode(['ok' => false, 'error' => 'Il testo del task è obbligatorio.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id_task FROM eventi_task WHERE id_task = ?');
$stmt->execute([$idTask]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Task non trovato.']);
    exit;
}

$testo = mb_substr($testo, 0, 500);

$pdo->prepare('UPDATE eventi_task SET testo = ? WHERE id_task = ?')->execute([$testo, $idTask]);

echo json_encode(['ok' => true, 'testo' => $testo]);
