<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();

$idPartecipante = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$cognome        = '';
$nome           = '';
$email          = '';
$cellulare      = '';
$errore         = '';

// Se si arriva dalla selezione partecipanti di un progetto (manca chi si sta
// cercando), il nuovo partecipante va aggiunto subito al roster di quel
// progetto e si torna lì, invece che all'anagrafica generale.
$fkProgetto = isset($_GET['fk_progetto']) ? (int) $_GET['fk_progetto'] : (isset($_POST['fk_progetto']) ? (int) $_POST['fk_progetto'] : 0);

if ($fkProgetto > 0) {
    $stmt = $pdo->prepare('SELECT 1 FROM progetti WHERE id_progetto = ?');
    $stmt->execute([$fkProgetto]);
    if (!$stmt->fetch()) {
        $fkProgetto = 0;
    }
}

if ($idPartecipante > 0) {
    $stmt = $pdo->prepare('SELECT * FROM partecipanti WHERE id_partecipante = ?');
    $stmt->execute([$idPartecipante]);
    $riga = $stmt->fetch();

    if (!$riga) {
        $idPartecipante = 0;
    } else {
        $cognome   = (string) ($riga['cognome'] ?? '');
        $nome      = (string) ($riga['nome'] ?? '');
        $email     = (string) ($riga['email'] ?? '');
        $cellulare = (string) ($riga['cellulare'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cognome   = trim((string) ($_POST['cognome'] ?? ''));
    $nome      = trim((string) ($_POST['nome'] ?? ''));
    $email     = trim((string) ($_POST['email'] ?? ''));
    $cellulare = trim((string) ($_POST['cellulare'] ?? ''));

    if ($cognome === '' && $nome === '') {
        $errore = 'Inserire almeno il cognome o il nome.';
    } else {
        $params = [
            $cognome !== '' ? $cognome : null,
            $nome !== '' ? $nome : null,
            $email !== '' ? $email : null,
            $cellulare !== '' ? $cellulare : null,
        ];

        if ($idPartecipante > 0) {
            $stmt = $pdo->prepare('UPDATE partecipanti SET cognome=?, nome=?, email=?, cellulare=? WHERE id_partecipante=?');
            $stmt->execute([...$params, $idPartecipante]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO partecipanti (cognome, nome, email, cellulare) VALUES (?,?,?,?)');
            $stmt->execute($params);
            $idPartecipante = (int) $pdo->lastInsertId();

            if ($fkProgetto > 0) {
                $pdo->prepare('INSERT INTO progetto_partecipanti (fk_progetto, fk_partecipante) VALUES (?, ?)')
                    ->execute([$fkProgetto, $idPartecipante]);
            }
        }

        redirect($fkProgetto > 0 ? 'progetto_partecipanti.php?id=' . $fkProgetto : 'partecipanti.php');
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title><?= $idPartecipante > 0 ? 'Modifica partecipante' : 'Nuovo partecipante' ?></title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1><?= $idPartecipante > 0 ? 'Modifica partecipante' : 'Nuovo partecipante' ?></h1>

    <?php if ($errore !== ''): ?>
        <div class="errore"><?= h($errore) ?></div>
    <?php endif; ?>

    <form method="post">
        <?php if ($fkProgetto > 0): ?>
            <input type="hidden" name="fk_progetto" value="<?= $fkProgetto ?>">
        <?php endif; ?>
        <div class="campi-riga">
            <div>
                <label for="cognome">Cognome</label>
                <input type="text" id="cognome" name="cognome" value="<?= h($cognome) ?>">
            </div>
            <div>
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" value="<?= h($nome) ?>">
            </div>
        </div>

        <div class="campi-riga">
            <div>
                <label for="email">Email</label>
                <input type="text" id="email" name="email" value="<?= h($email) ?>">
            </div>
            <div>
                <label for="cellulare">Cellulare</label>
                <input type="text" id="cellulare" name="cellulare" value="<?= h($cellulare) ?>">
            </div>
        </div>
        <p class="nota-campo">Almeno uno tra cognome e nome è obbligatorio.
            <?php if ($fkProgetto > 0 && $idPartecipante === 0): ?>
                Salvando, verrà aggiunto subito al progetto.
            <?php endif; ?>
        </p>

        <button type="submit">Salva</button>
        <a class="btn btn-secondary" href="<?= $fkProgetto > 0 ? 'progetto_partecipanti.php?id=' . $fkProgetto : 'partecipanti.php' ?>">Annulla</a>
    </form>
</div>
</body>
</html>
