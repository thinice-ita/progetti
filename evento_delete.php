<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();
$idEvento = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT fk_progetto FROM eventi WHERE id_evento = ?');
$stmt->execute([$idEvento]);
$riga = $stmt->fetch();

if ($riga) {
    $fkProgetto = (int) $riga['fk_progetto'];

    $stmt = $pdo->prepare('SELECT nome_file_salvato, cartella_relativa FROM eventi_allegati WHERE fk_evento = ?');
    $stmt->execute([$idEvento]);

    $cartelleToccate = [];

    foreach ($stmt->fetchAll() as $allegato) {
        $cartella = cartellaAllegato($allegato['cartella_relativa'], $idEvento);
        $percorso = __DIR__ . '/allegati/' . $cartella . '/' . $allegato['nome_file_salvato'];
        if (is_file($percorso)) {
            unlink($percorso);
        }
        $cartelleToccate[$cartella] = true;
    }

    foreach (array_keys($cartelleToccate) as $cartella) {
        @rmdir(__DIR__ . '/allegati/' . $cartella);
    }

    $pdo->prepare('DELETE FROM eventi WHERE id_evento = ?')->execute([$idEvento]);
    redirect('progetto_view.php?id=' . $fkProgetto);
}

redirect('index.php');
