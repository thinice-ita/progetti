<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();

$fkStepGet = isset($_GET['fk_step']) && $_GET['fk_step'] !== '' ? (int) $_GET['fk_step'] : null;
$fkStep    = null;
$nomeStep  = null;

if ($fkStepGet !== null) {
    $stmt = $pdo->prepare('SELECT s.*, p.titolo AS titolo_progetto FROM step s INNER JOIN progetti p ON p.id_progetto = s.fk_progetto WHERE s.id_step = ?');
    $stmt->execute([$fkStepGet]);
    $step = $stmt->fetch();

    if (!$step) {
        http_response_code(404);
        echo 'Step non trovato.';
        exit;
    }

    $fkStep     = $fkStepGet;
    $nomeStep   = $step['nome'];
    $fkProgetto = (int) $step['fk_progetto'];
    $titoloProgetto = $step['titolo_progetto'];
} else {
    $fkProgetto = isset($_GET['fk_progetto']) ? (int) $_GET['fk_progetto'] : 0;

    $stmt = $pdo->prepare('SELECT titolo FROM progetti WHERE id_progetto = ?');
    $stmt->execute([$fkProgetto]);
    $progetto = $stmt->fetch();

    if (!$progetto) {
        http_response_code(404);
        echo 'Progetto non trovato.';
        exit;
    }

    $titoloProgetto = $progetto['titolo'];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Nuova registrazione</title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1>Nuova registrazione</h1>
    <p>
        Progetto: <strong><?= h($titoloProgetto) ?></strong>
        <?php if ($nomeStep !== null): ?>
            &middot; Step: <strong><?= h($nomeStep) ?></strong>
        <?php else: ?>
            &middot; <em>evento libero (nessuno step)</em>
        <?php endif; ?>
    </p>
    <p>Carica il file audio: verrà compresso, trascritto e riassunto automaticamente. Il risultato (titolo, sintesi,
        audio e trascrizione) diventerà un evento in questo progetto.</p>

    <form action="registrazione_process.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="fk_progetto" value="<?= (int) $fkProgetto ?>">
        <input type="hidden" name="fk_step" value="<?= $fkStep !== null ? (int) $fkStep : '' ?>">
        <label for="audio">File audio</label>
        <input
            type="file"
            id="audio"
            name="audio"
            accept="audio/*,.mp3,.m4a,.wav,.ogg,.oga,.flac,.webm,.mp4,.mpeg,.mpga,.aac,.wma,.amr"
            required
        >
        <button type="submit">Carica e avvia trascrizione</button>
    </form>

    <a class="link-indietro" href="progetto_view.php?id=<?= (int) $fkProgetto ?>">&larr; Torna al progetto</a>
</div>
</body>
</html>
