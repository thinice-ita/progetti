<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();
$idProgetto = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($idProgetto > 0) {
    // Recupera gli allegati da cancellare dal disco prima che il CASCADE elimini le righe
    $stmt = $pdo->prepare(
        'SELECT ea.fk_evento, ea.nome_file_salvato, ea.cartella_relativa
         FROM eventi_allegati ea
         INNER JOIN eventi e ON e.id_evento = ea.fk_evento
         WHERE e.fk_progetto = ?'
    );
    $stmt->execute([$idProgetto]);

    $cartelleToccate = [];

    foreach ($stmt->fetchAll() as $allegato) {
        $cartella = cartellaAllegato($allegato['cartella_relativa'], (int) $allegato['fk_evento']);
        $percorso = __DIR__ . '/allegati/' . $cartella . '/' . $allegato['nome_file_salvato'];
        if (is_file($percorso)) {
            unlink($percorso);
        }
        $cartelleToccate[$cartella] = true;
    }

    foreach (array_keys($cartelleToccate) as $cartella) {
        @rmdir(__DIR__ . '/allegati/' . $cartella);
        if (strpos($cartella, '/') !== false) {
            // Cartella "parlante" progetto/step: rimuove anche la cartella del progetto se resta vuota.
            @rmdir(__DIR__ . '/allegati/' . dirname($cartella));
        }
    }

    // ON DELETE CASCADE elimina automaticamente step, eventi e eventi_allegati collegati
    $pdo->prepare('DELETE FROM progetti WHERE id_progetto = ?')->execute([$idProgetto]);
}

redirect('index.php');
