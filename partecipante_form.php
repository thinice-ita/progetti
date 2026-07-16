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
        }

        redirect('partecipanti.php');
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
        <p class="nota-campo">Almeno uno tra cognome e nome è obbligatorio.</p>

        <button type="submit">Salva</button>
        <a class="btn btn-secondary" href="partecipanti.php">Annulla</a>
    </form>
</div>
</body>
</html>
