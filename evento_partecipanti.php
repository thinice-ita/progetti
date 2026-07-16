<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();
$idEvento = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$ajax     = ($_GET['ajax'] ?? '') === '1';

$stmt = $pdo->prepare('SELECT * FROM eventi WHERE id_evento = ?');
$stmt->execute([$idEvento]);
$evento = $stmt->fetch();

if (!$evento) {
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Evento non trovato.']);
        exit;
    }
    http_response_code(404);
    echo 'Evento non trovato.';
    exit;
}

$fkProgetto = (int) $evento['fk_progetto'];
$fkStep     = $evento['fk_step'] !== null ? (int) $evento['fk_step'] : null;

// Il pool di partecipanti selezionabili è vincolato: se l'evento è dentro uno step,
// solo chi è già stato scelto per quello step; se è libero, tutto il roster di progetto.
if ($fkStep !== null) {
    $stmt = $pdo->prepare(
        'SELECT p.* FROM partecipanti p
         JOIN step_partecipanti sp ON sp.fk_partecipante = p.id_partecipante
         WHERE sp.fk_step = ? ORDER BY p.cognome, p.nome'
    );
    $stmt->execute([$fkStep]);
} else {
    $stmt = $pdo->prepare(
        'SELECT p.* FROM partecipanti p
         JOIN progetto_partecipanti pp ON pp.fk_partecipante = p.id_partecipante
         WHERE pp.fk_progetto = ? ORDER BY p.cognome, p.nome'
    );
    $stmt->execute([$fkProgetto]);
}
$pool = $stmt->fetchAll();
$poolIds = array_map('intval', array_column($pool, 'id_partecipante'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selezionati = array_intersect(array_map('intval', $_POST['partecipanti'] ?? []), $poolIds);

    $pdo->prepare('DELETE FROM eventi_partecipanti WHERE fk_evento = ?')->execute([$idEvento]);

    if ($selezionati) {
        $stmt = $pdo->prepare('INSERT INTO eventi_partecipanti (fk_evento, fk_partecipante) VALUES (?, ?)');
        foreach ($selezionati as $idPartecipante) {
            $stmt->execute([$idEvento, $idPartecipante]);
        }
    }

    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        $stmt = $pdo->prepare(
            'SELECT p.* FROM partecipanti p
             JOIN eventi_partecipanti ep ON ep.fk_partecipante = p.id_partecipante
             WHERE ep.fk_evento = ? ORDER BY p.cognome, p.nome'
        );
        $stmt->execute([$idEvento]);
        $nomi = array_map(static fn(array $p): string => formattaNomePartecipante($p) ?: '(senza nome)', $stmt->fetchAll());
        echo json_encode(['ok' => true, 'count' => count($nomi), 'nomi' => $nomi]);
        exit;
    }

    redirect('progetto_view.php?id=' . $fkProgetto . '#evento-' . $idEvento);
}

$stmt = $pdo->prepare('SELECT fk_partecipante FROM eventi_partecipanti WHERE fk_evento = ?');
$stmt->execute([$idEvento]);
$selezionatiAttuali = array_map('intval', array_column($stmt->fetchAll(), 'fk_partecipante'));

if ($ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'             => true,
        'titolo'         => 'Partecipanti all\'evento "' . $evento['titolo'] . '"',
        'partecipanti'   => array_map(
            static fn(array $p): array => datiPartecipanteJson($p) + [
                'selezionato' => in_array((int) $p['id_partecipante'], $selezionatiAttuali, true),
            ],
            $pool
        ),
        'vuotoMessaggio' => $pool ? '' : 'Nessun partecipante disponibile.',
        'vuotoLinkHref'  => $pool ? '' : ($fkStep !== null ? 'step_partecipanti.php?id=' . $fkStep : 'progetto_partecipanti.php?id=' . $fkProgetto),
        'vuotoLinkTesto' => $pool ? '' : ($fkStep !== null ? 'Selezionane prima per lo step' : 'Aggiungine prima al progetto'),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Partecipanti - <?= h($evento['titolo']) ?></title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1>Partecipanti all'evento "<?= h($evento['titolo']) ?>"</h1>

    <?php if (!$pool): ?>
        <p><em>Nessun partecipante disponibile.</em>
        <?php if ($fkStep !== null): ?>
            <a href="step_partecipanti.php?id=<?= $fkStep ?>">Selezionane prima per lo step</a>.
        <?php else: ?>
            <a href="progetto_partecipanti.php?id=<?= $fkProgetto ?>">Aggiungine prima al progetto</a>.
        <?php endif; ?>
        </p>
    <?php else: ?>
        <form method="post">
            <div class="lista-partecipanti-selezione">
                <?php foreach ($pool as $p): ?>
                    <label class="partecipante-riga">
                        <input type="checkbox" name="partecipanti[]" value="<?= (int) $p['id_partecipante'] ?>"
                            <?= in_array((int) $p['id_partecipante'], $selezionatiAttuali, true) ? 'checked' : '' ?>>
                        <span class="partecipante-nome"><?= h(formattaNomePartecipante($p)) ?: '(senza nome)' ?></span>
                        <span class="partecipante-contatti"><?= h(trim(($p['email'] ?? '') . ($p['cellulare'] ? ' · ' . $p['cellulare'] : ''))) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit">Salva</button>
            <a class="btn btn-secondary" href="progetto_view.php?id=<?= $fkProgetto ?>#evento-<?= $idEvento ?>">Annulla</a>
        </form>
    <?php endif; ?>

    <a class="link-indietro" href="progetto_view.php?id=<?= $fkProgetto ?>">&larr; Torna al progetto</a>
</div>
</body>
</html>
