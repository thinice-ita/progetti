<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = dbConnect();

$idTask = isset($_POST['id_task']) ? (int) $_POST['id_task'] : 0;

$pdo->prepare('DELETE FROM eventi_task WHERE id_task = ?')->execute([$idTask]);

echo json_encode(['ok' => true]);
