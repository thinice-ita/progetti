<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();

// Elenco sempre completo: la ricerca filtra le righe già in pagina via JS, in
// tempo reale mentre si digita (nessun giro col server).
$partecipanti = $pdo->query('SELECT * FROM partecipanti ORDER BY cognome, nome')->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Gestione Partecipanti</title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <div class="progetto-testata">
        <h1>Gestione Partecipanti</h1>
        <div class="ricerca-globale">
            <input type="search" id="cerca-partecipanti" placeholder="🔍 Cerca per nome, email, cellulare...">
        </div>
    </div>

    <div class="azioni">
        <a class="btn" href="partecipante_form.php">+ Nuovo partecipante</a>
        <a class="btn btn-secondary" href="partecipanti_importa.php">📥 Importa da testo</a>
    </div>

    <table class="elenco">
        <thead>
        <tr><th>Cognome</th><th>Nome</th><th>Email</th><th>Cellulare</th><th>Azioni</th></tr>
        </thead>
        <tbody>
        <?php if (!$partecipanti): ?>
            <tr><td colspan="5">Nessun partecipante presente.</td></tr>
        <?php else: ?>
            <?php foreach ($partecipanti as $p): ?>
                <tr data-cerca="<?= h(mb_strtolower(($p['cognome'] ?? '') . ' ' . ($p['nome'] ?? '') . ' ' . ($p['email'] ?? '') . ' ' . ($p['cellulare'] ?? ''))) ?>">
                    <td><?= h($p['cognome']) ?></td>
                    <td><?= h($p['nome']) ?></td>
                    <td><?= $p['email'] ? '<a href="mailto:' . h($p['email']) . '">' . h($p['email']) . '</a>' : '—' ?></td>
                    <td><?= $p['cellulare'] ? '<a href="tel:' . h($p['cellulare']) . '">' . h($p['cellulare']) . '</a>' : '—' ?></td>
                    <td class="azioni">
                        <a class="btn btn-secondary" href="partecipante_form.php?id=<?= (int) $p['id_partecipante'] ?>">Modifica</a>
                        <a class="btn btn-danger" href="partecipante_delete.php?id=<?= (int) $p['id_partecipante'] ?>"
                           onclick="return confirm('Eliminare questo partecipante? Verrà rimosso anche da tutti i progetti/step/eventi a cui è collegato.');">Elimina</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr id="riga-nessun-risultato" hidden><td colspan="5">Nessun partecipante corrispondente alla ricerca.</td></tr>
        </tbody>
    </table>

    <a class="link-indietro" href="index.php">&larr; Elenco progetti</a>
</div>
<script>
(function () {
    var campoCerca = document.getElementById('cerca-partecipanti');
    var righe = document.querySelectorAll('table.elenco tbody tr[data-cerca]');
    var rigaNessunRisultato = document.getElementById('riga-nessun-risultato');

    campoCerca.addEventListener('input', function () {
        var q = campoCerca.value.trim().toLowerCase();
        var visibili = 0;

        righe.forEach(function (riga) {
            var corrisponde = riga.dataset.cerca.indexOf(q) !== -1;
            riga.hidden = !corrisponde;
            if (corrisponde) {
                visibili++;
            }
        });

        rigaNessunRisultato.hidden = visibili !== 0;
    });
})();
</script>
</body>
</html>
