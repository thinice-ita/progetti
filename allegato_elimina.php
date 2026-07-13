<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = dbConnect();

$idAllegato = isset($_POST['id_allegato']) ? (int) $_POST['id_allegato'] : 0;

$stmt = $pdo->prepare('SELECT fk_evento, nome_file_salvato, cartella_relativa FROM eventi_allegati WHERE id_allegato = ?');
$stmt->execute([$idAllegato]);
$allegato = $stmt->fetch();

if (!$allegato) {
    echo json_encode(['ok' => false, 'error' => 'Allegato non trovato.']);
    exit;
}

$cartella = cartellaAllegato($allegato['cartella_relativa'], (int) $allegato['fk_evento']);
$percorso = __DIR__ . '/allegati/' . $cartella . '/' . $allegato['nome_file_salvato'];

if (is_file($percorso)) {
    unlink($percorso);
}

$pdo->prepare('DELETE FROM eventi_allegati WHERE id_allegato = ?')->execute([$idAllegato]);

echo json_encode(['ok' => true]);
