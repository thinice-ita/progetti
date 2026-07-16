<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();
$idStep = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$ajax   = ($_GET['ajax'] ?? '') === '1';

$stmt = $pdo->prepare('SELECT * FROM step WHERE id_step = ?');
$stmt->execute([$idStep]);
$step = $stmt->fetch();

if (!$step) {
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Step non trovato.']);
        exit;
    }
    http_response_code(404);
    echo 'Step non trovato.';
    exit;
}

$fkProgetto = (int) $step['fk_progetto'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vincola comunque lato server la selezione al roster di progetto, anche se la
    // form propone solo quelli (l'utente potrebbe alterare i valori inviati a mano).
    $stmt = $pdo->prepare('SELECT fk_partecipante FROM progetto_partecipanti WHERE fk_progetto = ?');
    $stmt->execute([$fkProgetto]);
    $roster = array_map('intval', array_column($stmt->fetchAll(), 'fk_partecipante'));

    $selezionati = array_intersect(array_map('intval', $_POST['partecipanti'] ?? []), $roster);

    $stmt = $pdo->prepare('SELECT fk_partecipante FROM step_partecipanti WHERE fk_step = ?');
    $stmt->execute([$idStep]);
    $precedenti = array_map('intval', array_column($stmt->fetchAll(), 'fk_partecipante'));

    $rimossi = array_diff($precedenti, $selezionati);

    if ($rimossi) {
        $segnaposto = implode(',', array_fill(0, count($rimossi), '?'));

        // Stessa logica di progetto_partecipanti.php: un partecipante tolto dallo step
        // non può restare selezionato su un evento di quello stesso step.
        $pdo->prepare(
            "DELETE ep FROM eventi_partecipanti ep
             JOIN eventi e ON e.id_evento = ep.fk_evento
             WHERE e.fk_step = ? AND ep.fk_partecipante IN ($segnaposto)"
        )->execute([$idStep, ...$rimossi]);
    }

    $pdo->prepare('DELETE FROM step_partecipanti WHERE fk_step = ?')->execute([$idStep]);

    if ($selezionati) {
        $stmt = $pdo->prepare('INSERT INTO step_partecipanti (fk_step, fk_partecipante) VALUES (?, ?)');
        foreach ($selezionati as $idPartecipante) {
            $stmt->execute([$idStep, $idPartecipante]);
        }
    }

    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        $stmt = $pdo->prepare(
            'SELECT p.* FROM partecipanti p
             JOIN step_partecipanti sp ON sp.fk_partecipante = p.id_partecipante
             WHERE sp.fk_step = ? ORDER BY p.cognome, p.nome'
        );
        $stmt->execute([$idStep]);
        $nomi = array_map(static fn(array $p): string => formattaNomePartecipante($p) ?: '(senza nome)', $stmt->fetchAll());
        echo json_encode(['ok' => true, 'count' => count($nomi), 'nomi' => $nomi]);
        exit;
    }

    redirect('progetto_view.php?id=' . $fkProgetto . '#step-' . $idStep);
}

$stmt = $pdo->prepare(
    'SELECT p.*, (sp.fk_step IS NOT NULL) AS selezionato
     FROM partecipanti p
     JOIN progetto_partecipanti pp ON pp.fk_partecipante = p.id_partecipante AND pp.fk_progetto = ?
     LEFT JOIN step_partecipanti sp ON sp.fk_partecipante = p.id_partecipante AND sp.fk_step = ?
     ORDER BY p.cognome, p.nome'
);
$stmt->execute([$fkProgetto, $idStep]);
$partecipanti = $stmt->fetchAll();

if ($ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'              => true,
        'titolo'          => 'Partecipanti allo step "' . $step['nome'] . '"',
        'partecipanti'    => array_map(
            static fn(array $p): array => datiPartecipanteJson($p) + ['selezionato' => (bool) $p['selezionato']],
            $partecipanti
        ),
        'vuotoMessaggio'  => $partecipanti ? '' : 'Nessun partecipante nel roster di questo progetto.',
        'vuotoLinkHref'   => $partecipanti ? '' : 'progetto_partecipanti.php?id=' . $fkProgetto,
        'vuotoLinkTesto'  => $partecipanti ? '' : 'Aggiungine prima al progetto',
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Partecipanti - <?= h($step['nome']) ?></title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1>Partecipanti allo step "<?= h($step['nome']) ?>"</h1>

    <?php if (!$partecipanti): ?>
        <p><em>Nessun partecipante nel roster di questo progetto.</em>
        <a href="progetto_partecipanti.php?id=<?= $fkProgetto ?>">Aggiungine prima al progetto</a>.</p>
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

            <button type="submit">Salva</button>
            <a class="btn btn-secondary" href="progetto_view.php?id=<?= $fkProgetto ?>#step-<?= $idStep ?>">Annulla</a>
        </form>
    <?php endif; ?>

    <a class="link-indietro" href="progetto_view.php?id=<?= $fkProgetto ?>">&larr; Torna al progetto</a>
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
