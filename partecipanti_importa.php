<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();

$testo      = '';
$importati  = null;
$saltati    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testo = (string) ($_POST['testo'] ?? '');
    $importati = 0;
    $saltati   = 0;

    $stmt = $pdo->prepare('INSERT INTO partecipanti (cognome, nome, email, cellulare) VALUES (?,?,?,?)');

    foreach (preg_split('/\r\n|\r|\n/', $testo) as $riga) {
        $riga = trim($riga);
        if ($riga === '') {
            continue;
        }

        $campi = array_pad(explode(';', $riga), 4, '');
        [$cognome, $nome, $email, $cellulare] = array_map('trim', array_slice($campi, 0, 4));

        if ($cognome === '' && $nome === '') {
            $saltati++;
            continue;
        }

        $stmt->execute([
            $cognome !== '' ? $cognome : null,
            $nome !== '' ? $nome : null,
            $email !== '' ? $email : null,
            $cellulare !== '' ? $cellulare : null,
        ]);
        $importati++;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Importa partecipanti</title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1>Importa partecipanti da testo</h1>

    <?php if ($importati !== null): ?>
        <div class="successo">Importati <?= $importati ?> partecipanti<?= $saltati > 0 ? ', ' . $saltati . ' righe saltate (senza cognome né nome)' : '' ?>.</div>
    <?php endif; ?>

    <p class="nota-campo">Incolla una riga per partecipante, nel formato <code>cognome;nome;mail;cell</code>.
    I campi mancanti restano vuoti (basta che ci sia almeno il cognome o il nome); le righe senza nessuno dei due vengono saltate.</p>

    <form method="post">
        <label for="testo">Testo da importare</label>
        <textarea id="testo" name="testo" rows="12" placeholder="Rossi;Mario;mario.rossi@email.it;333 1234567&#10;Bianchi;Luca;;&#10;;Anna;anna@email.it;347 7654321"><?= h($testo) ?></textarea>

        <button type="submit">Importa</button>
        <a class="btn btn-secondary" href="partecipanti.php">Torna all'elenco</a>
    </form>
</div>
</body>
</html>
