<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = dbConnect();

$fkEvento = isset($_POST['fk_evento']) ? (int) $_POST['fk_evento'] : 0;
$testo    = trim((string) ($_POST['testo'] ?? ''));

if ($testo === '') {
    echo json_encode(['ok' => false, 'error' => 'Il testo del task è obbligatorio.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id_evento FROM eventi WHERE id_evento = ?');
$stmt->execute([$fkEvento]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Evento non trovato.']);
    exit;
}

$testo = mb_substr($testo, 0, 500);

$stmt = $pdo->prepare('INSERT INTO eventi_task (fk_evento, testo) VALUES (?, ?)');
$stmt->execute([$fkEvento, $testo]);

echo json_encode(['ok' => true, 'id_task' => (int) $pdo->lastInsertId(), 'testo' => $testo, 'fatto' => false]);
