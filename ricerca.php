<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();
$idProgetto = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$query = trim($_GET['q'] ?? '');

$stmt = $pdo->prepare('SELECT * FROM progetti WHERE id_progetto = ?');
$stmt->execute([$idProgetto]);
$progetto = $stmt->fetch();

if (!$progetto) {
    http_response_code(404);
    echo 'Progetto non trovato.';
    exit;
}

if ($query === '') {
    redirect('progetto_view.php?id=' . $idProgetto);
}

/**
 * Estrae dal testo un frammento di contesto attorno alla prima occorrenza di
 * $query (spazi/a-capo normalizzati prima della ricerca), con il termine
 * evidenziato in <mark>. Tronca ai lati con "…" se il testo prosegue oltre
 * il raggio scelto. Restituisce HTML già pronto per l'output.
 */
function estraiContesto(string $testo, string $query, int $raggio = 70): string
{
    $testo = trim(preg_replace('/\s+/u', ' ', $testo));
    $pos = mb_stripos($testo, $query);

    if ($pos === false) {
        return h(mb_substr($testo, 0, 2 * $raggio));
    }

    $lunghezzaQuery = mb_strlen($query);
    $lunghezzaTesto = mb_strlen($testo);
    $inizio = max(0, $pos - $raggio);
    $fine   = min($lunghezzaTesto, $pos + $lunghezzaQuery + $raggio);

    $prima = mb_substr($testo, $inizio, $pos - $inizio);
    $match = mb_substr($testo, $pos, $lunghezzaQuery);
    $dopo  = mb_substr($testo, $pos + $lunghezzaQuery, $fine - $pos - $lunghezzaQuery);

    return ($inizio > 0 ? '…' : '') . h($prima) . '<mark>' . h($match) . '</mark>' . h($dopo) . ($fine < $lunghezzaTesto ? '…' : '');
}

$pattern = '%' . addcslashes($query, '%_\\') . '%';
$risultati = [];

// Step: nome e descrizione
$stmt = $pdo->prepare(
    'SELECT id_step, nome, descrizione FROM step
     WHERE fk_progetto = ? AND (nome LIKE ? OR descrizione LIKE ?)
     ORDER BY ordine, id_step'
);
$stmt->execute([$idProgetto, $pattern, $pattern]);

foreach ($stmt->fetchAll() as $step) {
    $link = 'progetto_view.php?id=' . $idProgetto . '#step-' . $step['id_step'];

    if (mb_stripos($step['nome'], $query) !== false) {
        $risultati[] = [
            'posizione' => 'Step: ' . $step['nome'] . ' — Nome',
            'contesto'  => estraiContesto($step['nome'], $query),
            'link'      => $link,
        ];
    }
    if ($step['descrizione'] !== null && mb_stripos($step['descrizione'], $query) !== false) {
        $risultati[] = [
            'posizione' => 'Step: ' . $step['nome'] . ' — Descrizione',
            'contesto'  => estraiContesto($step['descrizione'], $query),
            'link'      => $link,
        ];
    }
}

// Eventi: titolo e testo, con lo step di appartenenza per la posizione
$stmt = $pdo->prepare(
    'SELECT e.id_evento, e.titolo, e.testo, s.nome AS nome_step
     FROM eventi e LEFT JOIN step s ON s.id_step = e.fk_step
     WHERE e.fk_progetto = ? AND (e.titolo LIKE ? OR e.testo LIKE ?)
     ORDER BY e.data_evento DESC, e.id_evento DESC'
);
$stmt->execute([$idProgetto, $pattern, $pattern]);

foreach ($stmt->fetchAll() as $evento) {
    $link = 'progetto_view.php?id=' . $idProgetto . '#evento-' . $evento['id_evento'];
    $dove = $evento['nome_step'] ? ' (step: ' . $evento['nome_step'] . ')' : ' (evento libero)';

    if (mb_stripos($evento['titolo'], $query) !== false) {
        $risultati[] = [
            'posizione' => 'Evento: ' . $evento['titolo'] . $dove . ' — Titolo',
            'contesto'  => estraiContesto($evento['titolo'], $query),
            'link'      => $link,
        ];
    }
    if ($evento['testo'] !== null && mb_stripos($evento['testo'], $query) !== false) {
        $risultati[] = [
            'posizione' => 'Evento: ' . $evento['titolo'] . $dove . ' — Testo',
            'contesto'  => estraiContesto($evento['testo'], $query),
            'link'      => $link,
        ];
    }
}

// Task collegati agli eventi del progetto
$stmt = $pdo->prepare(
    'SELECT t.testo, e.id_evento, e.titolo AS titolo_evento
     FROM eventi_task t JOIN eventi e ON e.id_evento = t.fk_evento
     WHERE e.fk_progetto = ? AND t.testo LIKE ?
     ORDER BY t.id_task'
);
$stmt->execute([$idProgetto, $pattern]);

foreach ($stmt->fetchAll() as $task) {
    $risultati[] = [
        'posizione' => "Task nell'evento: " . $task['titolo_evento'],
        'contesto'  => estraiContesto($task['testo'], $query),
        'link'      => 'progetto_view.php?id=' . $idProgetto . '#evento-' . $task['id_evento'],
    ];
}

$totale = count($risultati);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Ricerca «<?= h($query) ?>» - <?= h($progetto['titolo']) ?></title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <a class="link-indietro" href="progetto_view.php?id=<?= $idProgetto ?>">&larr; <?= h($progetto['titolo']) ?></a>

    <h1>Ricerca</h1>

    <form method="get" action="ricerca.php" class="ricerca-globale">
        <input type="hidden" name="id" value="<?= $idProgetto ?>">
        <input type="search" name="q" value="<?= h($query) ?>" placeholder="🔍 Cerca in questo progetto (eventi, step, task)..." autofocus>
        <button type="submit" class="btn">Cerca</button>
    </form>

    <p class="ricerca-esito">
        Hai cercato: <strong>«<?= h($query) ?>»</strong>
        &middot;
        <span class="ricerca-conteggio"><?= $totale === 0
            ? 'nessun risultato trovato'
            : $totale . ($totale === 1 ? ' risultato trovato' : ' risultati trovati') ?></span>
    </p>

    <?php if (!$risultati): ?>
        <p><em>Prova con un'altra parola, o un termine più breve.</em></p>
    <?php else: ?>
        <div class="ricerca-lista">
            <?php foreach ($risultati as $r): ?>
                <a class="ricerca-risultato" href="<?= h($r['link']) ?>">
                    <div class="ricerca-risultato-dove"><?= h($r['posizione']) ?></div>
                    <div class="ricerca-risultato-contesto"><?= $r['contesto'] ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <a class="link-indietro" href="progetto_view.php?id=<?= $idProgetto ?>">&larr; Torna al progetto</a>
</div>
</body>
</html>
