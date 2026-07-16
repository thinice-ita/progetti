<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();

$progetti = $pdo->query(
    "SELECT p.id_progetto, p.titolo, p.data_apertura, p.data_chiusura,
            (SELECT COUNT(*) FROM step s WHERE s.fk_progetto = p.id_progetto) AS tot_step
     FROM progetti p
     ORDER BY (p.data_chiusura IS NOT NULL), p.data_apertura DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Gestione Progetti</title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1>Gestione Progetti</h1>
    <div class="azioni">
        <a class="btn" href="progetto_form.php">+ Nuovo progetto</a>
        <a class="btn btn-secondary" href="partecipanti.php">👤 Gestione partecipanti</a>
    </div>

    <table class="elenco">
        <thead>
        <tr><th>Titolo</th><th>Aperto il</th><th>Chiuso il</th><th>Step</th><th>Azioni</th></tr>
        </thead>
        <tbody>
        <?php if (!$progetti): ?>
            <tr><td colspan="5">Nessun progetto presente.</td></tr>
        <?php else: ?>
            <?php foreach ($progetti as $p): ?>
                <tr>
                    <td><?= h($p['titolo']) ?></td>
                    <td><?= formattaData($p['data_apertura']) ?></td>
                    <td><?= $p['data_chiusura'] ? formattaData($p['data_chiusura']) : '—' ?></td>
                    <td><?= (int) $p['tot_step'] ?></td>
                    <td class="azioni">
                        <a class="btn" href="progetto_view.php?id=<?= (int) $p['id_progetto'] ?>">Apri</a>
                        <a class="btn btn-secondary" href="progetto_form.php?id=<?= (int) $p['id_progetto'] ?>">Modifica</a>
                        <a class="btn btn-danger" href="progetto_delete.php?id=<?= (int) $p['id_progetto'] ?>"
                           onclick="return confirm('Eliminare questo progetto e tutto il suo contenuto (step, eventi, allegati)?');">Elimina</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
