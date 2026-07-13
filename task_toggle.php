<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = dbConnect();

$idTask = isset($_POST['id_task']) ? (int) $_POST['id_task'] : 0;

$stmt = $pdo->prepare('SELECT fatto FROM eventi_task WHERE id_task = ?');
$stmt->execute([$idTask]);
$task = $stmt->fetch();

if (!$task) {
    echo json_encode(['ok' => false, 'error' => 'Task non trovato.']);
    exit;
}

$nuovoStato   = $task['fatto'] ? 0 : 1;
$dataChiusura = $nuovoStato ? date('Y-m-d H:i:s') : null;

$pdo->prepare('UPDATE eventi_task SET fatto = ?, data_chiusura = ? WHERE id_task = ?')
    ->execute([$nuovoStato, $dataChiusura, $idTask]);

echo json_encode([
    'ok' => true,
    'fatto' => (bool) $nuovoStato,
    'data_chiusura' => $dataChiusura ? formattaDataOra($dataChiusura) : null,
]);
