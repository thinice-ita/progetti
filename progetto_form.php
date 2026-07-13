<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();

$idProgetto   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$titolo       = '';
$descrizione  = '';
$dataApertura = date('Y-m-d');
$dataChiusura = '';
$errore       = '';

if ($idProgetto > 0) {
    $stmt = $pdo->prepare('SELECT * FROM progetti WHERE id_progetto = ?');
    $stmt->execute([$idProgetto]);
    $riga = $stmt->fetch();

    if (!$riga) {
        $idProgetto = 0;
    } else {
        $titolo       = $riga['titolo'];
        $descrizione  = (string) $riga['descrizione'];
        $dataApertura = $riga['data_apertura'];
        $dataChiusura = (string) $riga['data_chiusura'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titolo       = trim((string) ($_POST['titolo'] ?? ''));
    $descrizione  = trim((string) ($_POST['descrizione'] ?? ''));
    $dataApertura = (string) ($_POST['data_apertura'] ?? '');
    $dataChiusura = trim((string) ($_POST['data_chiusura'] ?? ''));

    if ($titolo === '' || $dataApertura === '') {
        $errore = 'Titolo e data di apertura sono obbligatori.';
    } else {
        if ($idProgetto > 0) {
            $stmt = $pdo->prepare(
                'UPDATE progetti SET titolo=?, descrizione=?, data_apertura=?, data_chiusura=? WHERE id_progetto=?'
            );
            $stmt->execute([$titolo, $descrizione !== '' ? $descrizione : null, $dataApertura, $dataChiusura !== '' ? $dataChiusura : null, $idProgetto]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO progetti (titolo, descrizione, data_apertura, data_chiusura) VALUES (?,?,?,?)'
            );
            $stmt->execute([$titolo, $descrizione !== '' ? $descrizione : null, $dataApertura, $dataChiusura !== '' ? $dataChiusura : null]);
            $idProgetto = (int) $pdo->lastInsertId();
        }

        redirect('progetto_view.php?id=' . $idProgetto);
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title><?= $idProgetto > 0 ? 'Modifica progetto' : 'Nuovo progetto' ?></title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1><?= $idProgetto > 0 ? 'Modifica progetto' : 'Nuovo progetto' ?></h1>

    <?php if ($errore !== ''): ?>
        <div class="errore"><?= h($errore) ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="titolo">Titolo</label>
        <input type="text" id="titolo" name="titolo" value="<?= h($titolo) ?>" required>

        <label for="descrizione">Descrizione</label>
        <textarea id="descrizione" name="descrizione"><?= h($descrizione) ?></textarea>

        <label for="data_apertura">Data apertura</label>
        <input type="date" id="data_apertura" name="data_apertura" value="<?= h($dataApertura) ?>" required>

        <label for="data_chiusura">Data chiusura (lasciare vuoto se il progetto è aperto)</label>
        <input type="date" id="data_chiusura" name="data_chiusura" value="<?= h($dataChiusura) ?>">

        <button type="submit">Salva</button>
        <a class="btn btn-secondary" href="<?= $idProgetto > 0 ? 'progetto_view.php?id=' . $idProgetto : 'index.php' ?>">Annulla</a>
    </form>
</div>
</body>
</html>
