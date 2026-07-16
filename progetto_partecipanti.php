<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();
$idProgetto = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT * FROM progetti WHERE id_progetto = ?');
$stmt->execute([$idProgetto]);
$progetto = $stmt->fetch();

if (!$progetto) {
    http_response_code(404);
    echo 'Progetto non trovato.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selezionati = array_map('intval', $_POST['partecipanti'] ?? []);

    $stmt = $pdo->prepare('SELECT fk_partecipante FROM progetto_partecipanti WHERE fk_progetto = ?');
    $stmt->execute([$idProgetto]);
    $precedenti = array_map('intval', array_column($stmt->fetchAll(), 'fk_partecipante'));

    $rimossi = array_diff($precedenti, $selezionati);

    if ($rimossi) {
        $segnaposto = implode(',', array_fill(0, count($rimossi), '?'));

        // Un partecipante tolto dal roster del progetto non può restare selezionato
        // in uno step o in un evento di quello stesso progetto (vincolo: le selezioni
        // a livello step/evento sono sempre un sottoinsieme del roster di progetto).
        $pdo->prepare(
            "DELETE sp FROM step_partecipanti sp
             JOIN step s ON s.id_step = sp.fk_step
             WHERE s.fk_progetto = ? AND sp.fk_partecipante IN ($segnaposto)"
        )->execute([$idProgetto, ...$rimossi]);

        $pdo->prepare(
            "DELETE ep FROM eventi_partecipanti ep
             JOIN eventi e ON e.id_evento = ep.fk_evento
             WHERE e.fk_progetto = ? AND ep.fk_partecipante IN ($segnaposto)"
        )->execute([$idProgetto, ...$rimossi]);
    }

    $pdo->prepare('DELETE FROM progetto_partecipanti WHERE fk_progetto = ?')->execute([$idProgetto]);

    if ($selezionati) {
        $stmt = $pdo->prepare('INSERT INTO progetto_partecipanti (fk_progetto, fk_partecipante) VALUES (?, ?)');
        foreach ($selezionati as $idPartecipante) {
            $stmt->execute([$idProgetto, $idPartecipante]);
        }
    }

    redirect('progetto_view.php?id=' . $idProgetto);
}

$stmt = $pdo->prepare(
    'SELECT p.*, (pp.fk_progetto IS NOT NULL) AS selezionato
     FROM partecipanti p
     LEFT JOIN progetto_partecipanti pp ON pp.fk_partecipante = p.id_partecipante AND pp.fk_progetto = ?
     ORDER BY selezionato DESC, p.cognome, p.nome'
);
$stmt->execute([$idProgetto]);
$partecipanti = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Partecipanti - <?= h($progetto['titolo']) ?></title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1>Partecipanti al progetto</h1>
    <p class="nota-campo">Seleziona chi, tra i partecipanti in anagrafica, fa parte di questo progetto. Solo chi è
    selezionato qui potrà poi essere assegnato a singoli step ed eventi.</p>

    <?php if (!$partecipanti): ?>
        <p><em>Nessun partecipante in anagrafica.</em>
        <a href="partecipante_form.php?fk_progetto=<?= $idProgetto ?>">Aggiungine uno</a>: verrà aggiunto subito a questo progetto.</p>
    <?php else: ?>
        <form method="post">
            <input type="search" id="cerca-partecipanti" class="modal-partecipanti-cerca" placeholder="🔍 Cerca partecipante...">
            <div class="lista-partecipanti-selezione">
                <?php foreach ($partecipanti as $p): ?>
                    <label class="partecipante-riga" data-cerca="<?= h(strtolower(formattaNomePartecipante($p) . ' ' . trim(($p['email'] ?? '') . ' ' . ($p['cellulare'] ?? '')))) ?>">
                        <input type="checkbox" name="partecipanti[]" value="<?= (int) $p['id_partecipante'] ?>" <?= $p['selezionato'] ? 'checked' : '' ?>>
                        <span class="partecipante-nome"><?= h(formattaNomePartecipante($p)) ?: '(senza nome)' ?></span>
                        <span class="partecipante-contatti"><?= h(trim(($p['email'] ?? '') . ($p['cellulare'] ? ' · ' . $p['cellulare'] : ''))) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="nota-campo">Non trovi chi cerchi? <a href="partecipante_form.php?fk_progetto=<?= $idProgetto ?>">Aggiungi una nuova scheda partecipante</a>
            (verrà aggiunto subito a questo progetto; salva prima le spunte fatte qui, altrimenti si perdono).</p>

            <button type="submit">Salva</button>
            <a class="btn btn-secondary" href="progetto_view.php?id=<?= $idProgetto ?>">Annulla</a>
        </form>
    <?php endif; ?>

    <p><a href="partecipanti.php" target="_blank">👤 Gestione anagrafica partecipanti</a> (si apre in una nuova scheda)</p>

    <a class="link-indietro" href="progetto_view.php?id=<?= $idProgetto ?>">&larr; Torna al progetto</a>
</div>
<script>
var cercaPartecipanti = document.getElementById('cerca-partecipanti');
if (cercaPartecipanti) {
    cercaPartecipanti.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('.lista-partecipanti-selezione .partecipante-riga').forEach(function (riga) {
            riga.classList.toggle('modal-partecipanti-riga-nascosta', riga.dataset.cerca.indexOf(q) === -1);
        });
    });
}
</script>
</body>
</html>
