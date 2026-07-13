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

$stmt = $pdo->prepare('SELECT * FROM step WHERE fk_progetto = ? ORDER BY ordine, id_step');
$stmt->execute([$idProgetto]);
$stepList = $stmt->fetchAll();

$storicoPerStep = [];
if ($stepList) {
    $idStepList        = array_column($stepList, 'id_step');
    $segnapostoStep    = implode(',', array_fill(0, count($idStepList), '?'));
    $stmt = $pdo->prepare("SELECT * FROM step_storico_stato WHERE fk_step IN ($segnapostoStep) ORDER BY data_cambio");
    $stmt->execute($idStepList);
    foreach ($stmt->fetchAll() as $s) {
        $storicoPerStep[(int) $s['fk_step']][] = $s;
    }
}

$stmt = $pdo->prepare('SELECT * FROM eventi WHERE fk_progetto = ? ORDER BY data_evento DESC, id_evento DESC');
$stmt->execute([$idProgetto]);
$tuttiEventi = $stmt->fetchAll();

$eventiLiberi  = [];
$eventiPerStep = [];
foreach ($tuttiEventi as $evento) {
    if ($evento['fk_step'] === null) {
        $eventiLiberi[] = $evento;
    } else {
        $eventiPerStep[(int) $evento['fk_step']][] = $evento;
    }
}

$allegatiPerEvento = [];
$taskPerEvento     = [];

if ($tuttiEventi) {
    $idEventiList = array_column($tuttiEventi, 'id_evento');
    $segnaposto   = implode(',', array_fill(0, count($idEventiList), '?'));

    $stmt = $pdo->prepare("SELECT * FROM eventi_allegati WHERE fk_evento IN ($segnaposto) ORDER BY id_allegato");
    $stmt->execute($idEventiList);
    foreach ($stmt->fetchAll() as $allegato) {
        $allegatiPerEvento[(int) $allegato['fk_evento']][] = $allegato;
    }

    $stmt = $pdo->prepare("SELECT * FROM eventi_task WHERE fk_evento IN ($segnaposto) ORDER BY id_task");
    $stmt->execute($idEventiList);
    foreach ($stmt->fetchAll() as $task) {
        $taskPerEvento[(int) $task['fk_evento']][] = $task;
    }
}

$etichetteStato = ['da_fare' => 'Da fare', 'in_corso' => 'In corso', 'completato' => 'Completato'];

/**
 * Etichetta compatta dei task di un evento ("5 task · 3 da fare"), o stringa vuota se non ce ne sono.
 */
function reportEtichettaTask(array $task): string
{
    if (!$task) {
        return '';
    }

    $nonFatti = count(array_filter($task, static fn(array $t): bool => !$t['fatto']));

    return count($task) . ' task · ' . ($nonFatti > 0 ? $nonFatti . ' da fare' : 'tutti fatti');
}

/**
 * Stampa un evento come <details> annidato: riga compatta (titolo, data, badge
 * allegati/task sempre visibili) ed esplosione col dettaglio completo.
 */
function reportStampaEvento(array $evento, array $allegatiPerEvento, array $taskPerEvento): void
{
    $idEvento = (int) $evento['id_evento'];
    $allegati = $allegatiPerEvento[$idEvento] ?? [];
    $task     = $taskPerEvento[$idEvento] ?? [];
    ?>
    <details class="evento-blocco" data-evento-timestamp="<?= strtotime($evento['data_evento']) ?>">
        <summary>
            <span class="evento-riga-sinistra">
                <span class="evento-tipo">(<?= h($evento['tipo']) ?>)</span>
                <span class="evento-titolo"><?= h($evento['titolo']) ?></span>
                <span class="evento-data"><?= formattaDataOra($evento['data_evento']) ?></span>
            </span>
            <span class="evento-riga-destra">
                <?php if ($allegati): ?><span>Allegati: <?= count($allegati) ?></span><?php endif; ?>
                <?php if ($task): ?><span><?= h(reportEtichettaTask($task)) ?></span><?php endif; ?>
            </span>
        </summary>
        <div class="evento-blocco-corpo">
            <?php if ($evento['testo']): ?>
                <div class="evento-testo"><?= nl2br(h($evento['testo'])) ?></div>
            <?php endif; ?>
            <?php if ($task): ?>
                <div class="evento-task-titolo">Task</div>
                <ul class="evento-task-lista">
                    <?php foreach ($task as $t): ?>
                        <li class="<?= $t['fatto'] ? 'task-fatto' : '' ?>"><?= $t['fatto'] ? '☑' : '☐' ?> <?= h($t['testo']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($allegati): ?>
                <p class="evento-allegati">Allegati:
                    <?php foreach ($allegati as $i => $a): ?><?= $i > 0 ? ', ' : '' ?><?= h($a['nome_file_originale']) ?><?php endforeach; ?>
                </p>
            <?php endif; ?>
        </div>
    </details>
    <?php
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Report progetto - <?= h($progetto['titolo']) ?></title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10px;
        color: #1a1a1a;
        max-width: 210mm;
        margin: 0 auto;
        padding: 14mm 12mm;
    }
    @page { size: A4 portrait; margin: 12mm; }

    .report-toolbar { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.6rem; }
    .report-toolbar button {
        font-family: inherit;
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #cbd5e1;
        padding: 0.35rem 0.75rem;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
    }
    .report-toolbar button:hover { background: #e2e8f0; }
    .report-toolbar .stampa-btn { border-color: #94a3b8; margin-left: auto; }

    .pillola-sezione {
        display: inline-block;
        background: #eee;
        color: #333;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        padding: 0.25rem 0.7rem;
        border-radius: 999px;
        margin-bottom: 0.6rem;
    }

    /* Nessun riquadro: le sezioni sono separate solo da una riga, piu marcata
       tra progetto e step, piu leggera tra step ed evento. */
    .report-header { margin-bottom: 1.8rem; padding-bottom: 1.1rem; border-bottom: 2px solid #333; }
    .report-titolo { font-size: 16px; font-weight: 700; margin-bottom: 0.4rem; }
    .report-info { font-size: 10px; color: #555; margin: 0 0 0.5rem; }
    .report-descrizione { font-size: 10px; color: #333; font-style: italic; white-space: pre-wrap; word-wrap: break-word; }

    /* Wrapper di ogni step: il mini toolbar "Mostra/Apri eventi" e sovrapposto
       in alto a destra, fuori da <summary> apposta (dentro, il click veniva
       intercettato anche dal toggle nativo dello step: era il bug precedente). */
    .step-blocco-wrapper { position: relative; margin-bottom: 1.6rem; padding-bottom: 1.2rem; border-bottom: 1px solid #999; }
    .step-blocco-wrapper:last-child { border-bottom: none; margin-bottom: 0; }
    details.step-blocco > summary {
        list-style: none;
        cursor: pointer;
        padding: 0 13rem 0 0;
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 0.6rem;
        flex-wrap: wrap;
    }
    details.step-blocco > summary::-webkit-details-marker { display: none; }
    .step-titolo { font-size: 14px; font-weight: 700; }
    .step-info { font-size: 10px; color: #666; }
    .step-toolbar-esterno { position: absolute; top: 0; right: 0; display: flex; gap: 0.4rem; z-index: 2; }
    .step-toolbar-esterno button {
        font-family: inherit;
        background: transparent;
        border: 1px solid #ccc;
        color: #555;
        padding: 0.15rem 0.5rem;
        border-radius: 4px;
        font-size: 9px;
        cursor: pointer;
    }
    .step-toolbar-esterno button:hover { background: #f5f5f5; }

    /* Freccine ordinamento eventi: visibili solo a step aperto ("Mostra eventi"),
       via combinatore fratello sull'attributo [open] nativo di <details>. */
    .step-ordina { display: none; gap: 0.1rem; margin-left: 0.2rem; }
    details.step-blocco[open] ~ .step-toolbar-esterno .step-ordina { display: inline-flex; }
    .step-ordina button {
        font-family: inherit;
        background: transparent;
        border: none;
        color: #aaa;
        font-size: 9px;
        cursor: pointer;
        padding: 0.1rem 0.2rem;
    }
    .step-ordina button:hover { color: #666; }
    .step-ordina button.step-ordina-attivo { color: #1a1a1a; }
    .step-blocco-corpo { padding: 0.7rem 0 0; }
    .step-nota { font-size: 10px; color: #444; font-style: italic; white-space: pre-wrap; word-wrap: break-word; margin-bottom: 0.7rem; }
    .step-nessun-evento { font-size: 10px; color: #888; font-style: italic; }

    /* Storico cambi di stato dello step (riaperture comprese): niente box, solo
       una piccola etichetta e un elenco semplice, le riaperture in grassetto. */
    .step-storico-titolo { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #888; margin: 0 0 0.3rem; }
    .step-storico { list-style: none; margin: 0 0 0.8rem; padding: 0; font-size: 10px; color: #555; }
    .step-storico li { padding: 0.05rem 0; }
    .step-storico-riapertura { font-weight: 700; }

    /* Evento: riga tratteggiata leggera tra un evento e l'altro, piu sottile
       della riga (solida) che separa gli step. */
    details.evento-blocco { border-top: 1px dotted #ccc; padding: 0.5rem 0; }
    details.evento-blocco:first-child { border-top: none; padding-top: 0; }
    details.evento-blocco > summary {
        list-style: none;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 0.5rem;
        flex-wrap: wrap;
        font-size: 10px;
    }
    details.evento-blocco > summary::-webkit-details-marker { display: none; }
    .evento-riga-sinistra { display: flex; align-items: baseline; gap: 0.4rem; min-width: 0; }
    .evento-tipo { font-size: 9px; color: #888; font-style: italic; }
    .evento-titolo { font-weight: 700; font-size: 12px; }
    .evento-data { color: #777; font-size: 10px; white-space: nowrap; }
    .evento-riga-destra { display: flex; gap: 0.6rem; flex-shrink: 0; font-size: 10px; color: #666; font-style: italic; }
    .evento-blocco-corpo { padding: 0.4rem 0 0 0.8rem; font-size: 10px; }
    .evento-testo { white-space: pre-wrap; word-wrap: break-word; margin-bottom: 0.5rem; }
    .evento-task-titolo { font-size: 10px; font-style: italic; color: #888; margin: 0.3rem 0 0.2rem; }
    .evento-task-lista { list-style: none; padding: 0; margin: 0 0 0.4rem; font-size: 10px; }
    .evento-task-lista li { padding: 0.1rem 0; }
    .evento-task-lista li.task-fatto { color: #888; text-decoration: line-through; }
    .evento-allegati { font-size: 10px; color: #555; margin: 0; }

    .eventi-liberi-titolo { font-size: 14px; font-weight: 700; }

    @media print {
        .report-toolbar { display: none; }
        .step-toolbar-esterno { display: none; }
    }
</style>
</head>
<body>

<div class="report-toolbar">
    <button type="button" id="btn-comprimi-tutto">Comprimi tutto</button>
    <button type="button" id="btn-toggle-step">Apri tutti gli step</button>
    <button type="button" id="btn-toggle-eventi">Apri tutti gli eventi</button>
    <button type="button" class="stampa-btn" onclick="window.print()">🖨️ Stampa</button>
</div>

<span class="pillola-sezione">Progetto</span>
<div class="report-header">
    <div class="report-titolo"><?= h($progetto['titolo']) ?></div>
    <div class="report-info">
        Apertura: <?= formattaData($progetto['data_apertura']) ?>
        &middot; Chiusura: <?= $progetto['data_chiusura'] ? formattaData($progetto['data_chiusura']) : 'in corso' ?>
    </div>
    <?php if ($progetto['descrizione']): ?>
        <div class="report-descrizione"><?= nl2br(h($progetto['descrizione'])) ?></div>
    <?php endif; ?>
</div>

<span class="pillola-sezione">Step</span>

<?php foreach ($stepList as $step): $idStep = (int) $step['id_step']; $eventiStep = $eventiPerStep[$idStep] ?? []; ?>
    <div class="step-blocco-wrapper">
        <details class="step-blocco">
            <summary>
                <span>
                    <span class="step-titolo"><?= h($step['nome']) ?></span>
                    &middot; <?= h($etichetteStato[$step['stato']] ?? $step['stato']) ?>
                    <div class="step-info">
                        Apertura: <?= ($step['data_apertura'] ?? null) ? formattaData($step['data_apertura']) : '—' ?>
                        &middot; Inizio: <?= ($step['data_inizio'] ?? null) ? formattaData($step['data_inizio']) : '—' ?>
                        &middot; Chiusura: <?= ($step['data_chiusura'] ?? null) ? formattaData($step['data_chiusura']) : '—' ?>
                    </div>
                </span>
            </summary>
            <div class="step-blocco-corpo">
                <?php if ($step['descrizione']): ?>
                    <div class="step-nota"><?= nl2br(h($step['descrizione'])) ?></div>
                <?php endif; ?>
                <?php $storico = $storicoPerStep[$idStep] ?? []; ?>
                <?php if ($storico): ?>
                    <div class="step-storico-titolo">Storico stato</div>
                    <ul class="step-storico">
                        <?php foreach ($storico as $s): ?>
                            <?php $riapertura = $s['stato_precedente'] === 'completato' && $s['stato_nuovo'] !== 'completato'; ?>
                            <li class="<?= $riapertura ? 'step-storico-riapertura' : '' ?>">
                                <?= formattaDataOra($s['data_cambio']) ?> —
                                <?= $s['stato_precedente'] ? h($etichetteStato[$s['stato_precedente']] ?? $s['stato_precedente']) : '(creazione)' ?>
                                &rarr;
                                <?= h($etichetteStato[$s['stato_nuovo']] ?? $s['stato_nuovo']) ?>
                                <?= $riapertura ? '(riaperto)' : '' ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!$eventiStep): ?>
                    <p class="step-nessun-evento">Nessun evento in questo step.</p>
                <?php endif; ?>
                <?php foreach ($eventiStep as $evento): ?>
                    <?php reportStampaEvento($evento, $allegatiPerEvento, $taskPerEvento); ?>
                <?php endforeach; ?>
            </div>
        </details>
        <div class="step-toolbar-esterno">
            <button type="button" class="step-mostra-eventi">Mostra eventi</button>
            <button type="button" class="step-toggle-eventi">Apri tutti gli eventi</button>
            <span class="step-ordina">
                <button type="button" class="step-ordina-asc" title="Ordina dal più vecchio">&#9650;</button>
                <button type="button" class="step-ordina-desc step-ordina-attivo" title="Ordina dal più recente">&#9660;</button>
            </span>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($eventiLiberi): ?>
    <div class="step-blocco-wrapper">
        <details class="step-blocco">
            <summary>
                <span class="eventi-liberi-titolo">Eventi liberi</span>
            </summary>
            <div class="step-blocco-corpo">
                <?php foreach ($eventiLiberi as $evento): ?>
                    <?php reportStampaEvento($evento, $allegatiPerEvento, $taskPerEvento); ?>
                <?php endforeach; ?>
            </div>
        </details>
        <div class="step-toolbar-esterno">
            <button type="button" class="step-mostra-eventi">Mostra eventi</button>
            <button type="button" class="step-toggle-eventi">Apri tutti gli eventi</button>
            <span class="step-ordina">
                <button type="button" class="step-ordina-asc" title="Ordina dal più vecchio">&#9650;</button>
                <button type="button" class="step-ordina-desc step-ordina-attivo" title="Ordina dal più recente">&#9660;</button>
            </span>
        </div>
    </div>
<?php endif; ?>

<script>
function impostaOpen(selettore, valore) {
    document.querySelectorAll(selettore).forEach(function (d) { d.open = valore; });
}

function tuttiAperti(selettore) {
    var lista = document.querySelectorAll(selettore);
    return lista.length > 0 && Array.prototype.every.call(lista, function (d) { return d.open; });
}

/**
 * Le etichette dei pulsanti (globali e per-step) riflettono sempre lo stato
 * reale del DOM (non un flag separato): cosi restano corrette anche se
 * l'utente apre/chiude step o eventi singolarmente, cliccando direttamente
 * sull'intestazione invece che dai pulsanti.
 */
function aggiornaEtichette() {
    document.getElementById('btn-toggle-step').textContent =
        tuttiAperti('details.step-blocco') ? 'Chiudi tutti gli step' : 'Apri tutti gli step';
    document.getElementById('btn-toggle-eventi').textContent =
        tuttiAperti('details.evento-blocco') ? 'Chiudi tutti gli eventi' : 'Apri tutti gli eventi';

    document.querySelectorAll('.step-blocco-wrapper').forEach(function (wrapper) {
        var dettaglioStep = wrapper.querySelector('details.step-blocco');
        var eventiStep    = wrapper.querySelectorAll('details.evento-blocco');

        var mostraBtn = wrapper.querySelector('.step-mostra-eventi');
        if (mostraBtn) {
            mostraBtn.textContent = dettaglioStep.open ? 'Nascondi eventi' : 'Mostra eventi';
        }

        var toggleBtn = wrapper.querySelector('.step-toggle-eventi');
        if (toggleBtn) {
            var tuttiApertiStep = eventiStep.length > 0 && Array.prototype.every.call(eventiStep, function (d) { return d.open; });
            toggleBtn.textContent = tuttiApertiStep ? 'Chiudi tutti gli eventi' : 'Apri tutti gli eventi';
        }
    });
}

document.getElementById('btn-comprimi-tutto').addEventListener('click', function () {
    impostaOpen('details.step-blocco, details.evento-blocco', false);
    aggiornaEtichette();
});

document.getElementById('btn-toggle-step').addEventListener('click', function () {
    if (tuttiAperti('details.step-blocco')) {
        // chiudendo tutti gli step si chiudono anche tutti gli eventi che contengono
        impostaOpen('details.step-blocco, details.evento-blocco', false);
    } else {
        impostaOpen('details.step-blocco', true);
    }
    aggiornaEtichette();
});

document.getElementById('btn-toggle-eventi').addEventListener('click', function () {
    if (tuttiAperti('details.evento-blocco')) {
        impostaOpen('details.evento-blocco', false);
    } else {
        // aprire tutti gli eventi implica aprire anche gli step che li contengono
        impostaOpen('details.step-blocco, details.evento-blocco', true);
    }
    aggiornaEtichette();
});

// "Mostra/Nascondi eventi": apre o chiude solo lo step stesso (stessa azione
// del click sulla sua intestazione, ma esplicita). Vive fuori da <summary>,
// quindi non interferisce mai col toggle nativo (era li il bug precedente).
document.querySelectorAll('.step-mostra-eventi').forEach(function (bottone) {
    bottone.addEventListener('click', function () {
        var wrapper = bottone.closest('.step-blocco-wrapper');
        var dettaglioStep = wrapper.querySelector('details.step-blocco');
        dettaglioStep.open = !dettaglioStep.open;
        aggiornaEtichette();
    });
});

// "Apri/Chiudi tutti gli eventi" (di questo step): apre anche lo step stesso
// se non era gia aperto (altrimenti apriresti gli eventi senza vederli); alla
// chiusura invece lascia lo step com'e, cosi si puo tornare alla vista compatta
// senza perdere la visibilita dello step.
document.querySelectorAll('.step-toggle-eventi').forEach(function (bottone) {
    bottone.addEventListener('click', function () {
        var wrapper = bottone.closest('.step-blocco-wrapper');
        var dettaglioStep = wrapper.querySelector('details.step-blocco');
        var eventiStep    = wrapper.querySelectorAll('details.evento-blocco');
        var tuttiApertiStep = eventiStep.length > 0 && Array.prototype.every.call(eventiStep, function (d) { return d.open; });

        if (tuttiApertiStep) {
            eventiStep.forEach(function (d) { d.open = false; });
        } else {
            dettaglioStep.open = true;
            eventiStep.forEach(function (d) { d.open = true; });
        }
        aggiornaEtichette();
    });
});

document.querySelectorAll('details.step-blocco, details.evento-blocco').forEach(function (d) {
    d.addEventListener('toggle', aggiornaEtichette);
});

// Ordinamento eventi dentro uno step: riordina solo i suoi <details.evento-blocco>
// (per data), lasciando invariata la posizione di nota/storico/altri elementi.
document.querySelectorAll('.step-ordina-asc, .step-ordina-desc').forEach(function (bottone) {
    bottone.addEventListener('click', function () {
        var wrapper = bottone.closest('.step-blocco-wrapper');
        var corpo = wrapper.querySelector('.step-blocco-corpo');
        var ascendente = bottone.classList.contains('step-ordina-asc');
        var eventi = Array.prototype.slice.call(corpo.querySelectorAll(':scope > details.evento-blocco'));

        eventi.sort(function (a, b) {
            var ta = parseInt(a.getAttribute('data-evento-timestamp'), 10);
            var tb = parseInt(b.getAttribute('data-evento-timestamp'), 10);
            return ascendente ? ta - tb : tb - ta;
        });
        eventi.forEach(function (el) { corpo.appendChild(el); });

        wrapper.querySelectorAll('.step-ordina-asc, .step-ordina-desc').forEach(function (b) {
            b.classList.remove('step-ordina-attivo');
        });
        bottone.classList.add('step-ordina-attivo');
    });
});

aggiornaEtichette();
</script>
</body>
</html>
