<?php
declare(strict_types=1);

/**
 * Da eseguire UNA TANTUM dal browser dopo aver creato la tabella eventi_task
 * (vedi migrazione_task_eventi.sql): estrae i task dalla sezione "## Task assegnati"
 * già presente nel testo degli eventi esistenti e crea i relativi record.
 * Idempotente: salta gli eventi che hanno già almeno un task registrato, quindi è
 * sicuro riaprire questa pagina più di una volta.
 */

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();

$stmt = $pdo->query(
    "SELECT id_evento, titolo, testo FROM eventi
     WHERE testo LIKE '%Task assegnati%'
       AND id_evento NOT IN (SELECT DISTINCT fk_evento FROM eventi_task)"
);
$eventiCandidati = $stmt->fetchAll();

$inserimento = $pdo->prepare('INSERT INTO eventi_task (fk_evento, testo) VALUES (?, ?)');

$totaleTask = 0;
$dettaglio  = [];

foreach ($eventiCandidati as $riga) {
    $task = estraiTaskDaTesto((string) $riga['testo']);
    if (!$task) {
        continue;
    }

    foreach ($task as $testoTask) {
        $inserimento->execute([$riga['id_evento'], mb_substr($testoTask, 0, 500)]);
        $totaleTask++;
    }

    $dettaglio[] = ['id_evento' => (int) $riga['id_evento'], 'titolo' => $riga['titolo'], 'task' => $task];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Backfill task su eventi esistenti</title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1>Backfill task su eventi esistenti</h1>
    <div class="successo">
        Creati <?= $totaleTask ?> task su <?= count($dettaglio) ?> eventi
        (eventi con sezione "Task assegnati" nel testo e nessun task già registrato).
    </div>

    <?php if (!$dettaglio): ?>
        <p><em>Nessun evento da elaborare.</em></p>
    <?php endif; ?>

    <?php foreach ($dettaglio as $d): ?>
        <h2>#<?= $d['id_evento'] ?> &mdash; <?= h($d['titolo']) ?></h2>
        <ul>
            <?php foreach ($d['task'] as $t): ?>
                <li><?= h($t) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>

    <a class="link-indietro" href="index.php">&larr; Torna all'elenco progetti</a>
</div>
</body>
</html>
