<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();

$tipo = $_GET['tipo'] ?? '';
$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!in_array($tipo, ['progetto', 'step', 'evento'], true) || $id <= 0) {
    http_response_code(400);
    echo 'Richiesta non valida.';
    exit;
}

$etichetteStato = ['da_fare' => 'Da fare', 'in_corso' => 'In corso', 'completato' => 'Completato'];

$progetto = null;
$step     = null;
$evento   = null;
$stepList = [];

if ($tipo === 'progetto') {
    $stmt = $pdo->prepare('SELECT * FROM progetti WHERE id_progetto = ?');
    $stmt->execute([$id]);
    $progetto = $stmt->fetch();

    if (!$progetto) {
        http_response_code(404);
        echo 'Progetto non trovato.';
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM step WHERE fk_progetto = ? ORDER BY ordine, id_step');
    $stmt->execute([$id]);
    $stepList = $stmt->fetchAll();
} elseif ($tipo === 'step') {
    $stmt = $pdo->prepare('SELECT * FROM step WHERE id_step = ?');
    $stmt->execute([$id]);
    $step = $stmt->fetch();

    if (!$step) {
        http_response_code(404);
        echo 'Step non trovato.';
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM progetti WHERE id_progetto = ?');
    $stmt->execute([$step['fk_progetto']]);
    $progetto = $stmt->fetch();
} else {
    $stmt = $pdo->prepare('SELECT * FROM eventi WHERE id_evento = ?');
    $stmt->execute([$id]);
    $evento = $stmt->fetch();

    if (!$evento) {
        http_response_code(404);
        echo 'Evento non trovato.';
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM progetti WHERE id_progetto = ?');
    $stmt->execute([$evento['fk_progetto']]);
    $progetto = $stmt->fetch();

    if ($evento['fk_step'] !== null) {
        $stmt = $pdo->prepare('SELECT * FROM step WHERE id_step = ?');
        $stmt->execute([$evento['fk_step']]);
        $step = $stmt->fetch();
    }
}

$titoloPagina = 'Cartellina ' . $tipo . ' — ' . ($progetto['titolo'] ?? '');
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title><?= h($titoloPagina) ?></title>
<style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: #1a1a1a; margin: 0; }
    @page { size: A3 landscape; margin: 0; }

    .stampa-toolbar { padding: 1rem; }
    .stampa-toolbar button {
        background: #4f6ef2;
        color: #fff;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
    }

    /* Foglio A3 orizzontale diviso in due colonne A4: la sinistra resta vuota,
       tutto il contenuto sta nella destra. Stampato su A3 e piegato a metà,
       diventa subito una cartellina pronta all'uso. */
    .cartellina-foglio { display: flex; width: 420mm; height: 297mm; }
    .cartellina-colonna { width: 210mm; height: 297mm; }
    .cartellina-colonna-destra { padding: 20mm 18mm; }

    .cartellina-blocco { margin-bottom: 2.4rem; }
    .cartellina-blocco + .cartellina-blocco { padding-top: 1.8rem; border-top: 1px solid #ddd; }
    .cartellina-etichetta {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #94a3b8;
        margin-bottom: 0.35rem;
    }
    .cartellina-titolo { font-size: 1.7rem; font-weight: 700; margin-bottom: 0.5rem; }
    .cartellina-info { font-size: 0.95rem; color: #444; margin-bottom: 0.6rem; }
    /* Riferimento al livello superiore (progetto, o progetto+step): piu piccolo
       e leggermente scalato a sinistra rispetto al contenuto principale, che
       resta quello del livello corrente (step o evento). */
    .cartellina-riferimento {
        font-size: 0.78rem;
        color: #94a3b8;
        margin: 0 0 1.1rem -0.15rem;
    }
    .cartellina-elenco-step { margin: 0; padding-left: 1.2rem; font-size: 0.95rem; color: #333; }
    .cartellina-elenco-step li { margin-bottom: 0.25rem; }
    .cartellina-elenco-step-data { color: #777; }
    .cartellina-note {
        font-size: 0.9rem;
        color: #333;
        white-space: pre-wrap;
        word-wrap: break-word;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 0.7rem 0.9rem;
    }

    @media print {
        .stampa-toolbar { display: none; }
    }
</style>
</head>
<body>

<div class="stampa-toolbar">
    <button type="button" onclick="window.print()">🖨️ Stampa (A3 orizzontale)</button>
</div>

<div class="cartellina-foglio">
    <div class="cartellina-colonna cartellina-colonna-sinistra"></div>
    <div class="cartellina-colonna cartellina-colonna-destra">

        <?php if ($tipo === 'progetto'): ?>
            <div class="cartellina-blocco">
                <div class="cartellina-etichetta">Progetto</div>
                <div class="cartellina-titolo"><?= h($progetto['titolo']) ?></div>
                <div class="cartellina-info">
                    Apertura: <?= formattaData($progetto['data_apertura']) ?>
                    &middot; Chiusura: <?= $progetto['data_chiusura'] ? formattaData($progetto['data_chiusura']) : 'in corso' ?>
                </div>
                <?php if ($progetto['descrizione']): ?>
                    <div class="cartellina-note"><?= nl2br(h($progetto['descrizione'])) ?></div>
                <?php endif; ?>
            </div>

            <?php if ($stepList): ?>
                <div class="cartellina-blocco">
                    <div class="cartellina-etichetta">Step</div>
                    <ul class="cartellina-elenco-step">
                        <?php foreach ($stepList as $s): ?>
                            <li>
                                <?= h($s['nome']) ?>
                                <span class="cartellina-elenco-step-data">— alla data del <?= formattaData($s['data_apertura']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

        <?php elseif ($tipo === 'step'): ?>
            <div class="cartellina-riferimento">Progetto: <?= h($progetto['titolo']) ?></div>

            <div class="cartellina-blocco">
                <div class="cartellina-etichetta">Step</div>
                <div class="cartellina-titolo"><?= h($step['nome']) ?></div>
                <div class="cartellina-info">
                    Stato: <?= h($etichetteStato[$step['stato']] ?? $step['stato']) ?>
                    &middot; Apertura: <?= ($step['data_apertura'] ?? null) ? formattaData($step['data_apertura']) : '—' ?>
                    &middot; Inizio: <?= ($step['data_inizio'] ?? null) ? formattaData($step['data_inizio']) : '—' ?>
                    &middot; Chiusura: <?= ($step['data_chiusura'] ?? null) ? formattaData($step['data_chiusura']) : '—' ?>
                </div>
                <?php if ($step['descrizione']): ?>
                    <div class="cartellina-note"><?= nl2br(h($step['descrizione'])) ?></div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="cartellina-riferimento">
                Progetto: <?= h($progetto['titolo']) ?><?php if ($step): ?> &middot; Step: <?= h($step['nome']) ?><?php else: ?> &middot; Evento libero<?php endif; ?>
            </div>

            <div class="cartellina-blocco">
                <div class="cartellina-etichetta">Evento</div>
                <div class="cartellina-titolo"><?= h($evento['titolo']) ?></div>
                <div class="cartellina-info">Data: <?= formattaDataOra($evento['data_evento']) ?></div>
            </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
