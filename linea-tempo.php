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

$iconeTipo = ['nota' => '📝', 'riunione' => '👥', 'email' => '✉️', 'registrazione' => '🎙️'];

$stmt = $pdo->prepare('SELECT id_step, nome, data_apertura, data_inizio, data_chiusura FROM step WHERE fk_progetto = ? ORDER BY ordine, id_step');
$stmt->execute([$idProgetto]);
$tuttiStep = $stmt->fetchAll();

// Stesso ciclo di 6 colori di classeColoreStep() (funzioni.php), qui serve
// però l'indice numerico puro per comporre la classe lt-tab-colore-N.
$coloreIndice = [];
foreach ($tuttiStep as $indice => $step) {
    $coloreIndice[(int) $step['id_step']] = $indice % 6;
}

$stepConData = array_values(array_filter(
    $tuttiStep,
    static fn(array $s): bool => $s['data_apertura'] !== null || $s['data_inizio'] !== null
));
$stepSenzaData = count($tuttiStep) - count($stepConData);

$stmt = $pdo->prepare('SELECT id_evento, fk_step, tipo, titolo, testo, data_evento FROM eventi WHERE fk_progetto = ? ORDER BY data_evento, id_evento');
$stmt->execute([$idProgetto]);
$tuttiEventi = $stmt->fetchAll();

$eventiPerStep = [];
$eventiLiberi  = [];
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

    $stmt = $pdo->prepare("SELECT fk_evento, COUNT(*) AS totale FROM eventi_allegati WHERE fk_evento IN ($segnaposto) GROUP BY fk_evento");
    $stmt->execute($idEventiList);
    foreach ($stmt->fetchAll() as $riga) {
        $allegatiPerEvento[(int) $riga['fk_evento']] = (int) $riga['totale'];
    }

    $stmt = $pdo->prepare("SELECT * FROM eventi_task WHERE fk_evento IN ($segnaposto)");
    $stmt->execute($idEventiList);
    foreach ($stmt->fetchAll() as $task) {
        $taskPerEvento[(int) $task['fk_evento']][] = $task;
    }
}

/**
 * Percentuale (0-100) della posizione di $data lungo l'asse [$inizio, $inizio + $totaleSecondi].
 */
function percentoSuAsse(DateTime $data, DateTime $inizio, int $totaleSecondi): float
{
    $percento = ($data->getTimestamp() - $inizio->getTimestamp()) / $totaleSecondi * 100;

    return max(0.0, min(100.0, $percento));
}

/**
 * Una riga espandibile dentro un fumetto: titolo/data collassati, dettaglio
 * (testo, allegati, task, link al progetto) che compare al click.
 */
function rigaEventoLineaTempo(array $evento, int $idProgetto, array $iconeTipo, array $allegatiPerEvento, array $taskPerEvento): void
{
    $idEvento = (int) $evento['id_evento'];
    $allegati = $allegatiPerEvento[$idEvento] ?? 0;
    $task     = $taskPerEvento[$idEvento] ?? [];
    $icona    = $iconeTipo[$evento['tipo']] ?? '';

    $snippet = '';
    if ($evento['testo']) {
        $normalizzato = trim(preg_replace('/\s+/u', ' ', $evento['testo']));
        $snippet = mb_substr($normalizzato, 0, 160) . (mb_strlen($normalizzato) > 160 ? '…' : '');
    }
    ?>
    <div class="lt-evento-riga">
        <button type="button" class="lt-evento-riga-toggle">
            <span class="lt-evento-riga-titolo"><?= $icona ?> <?= h($evento['titolo']) ?></span>
            <span class="lt-evento-riga-data"><?= formattaDataBreve($evento['data_evento']) ?> '<?= date('y', strtotime($evento['data_evento'])) ?></span>
        </button>
        <div class="lt-evento-dettaglio">
            <?php if ($snippet): ?><div><?= h($snippet) ?></div><?php endif; ?>
            <div class="lt-badge-riga">
                <?php if ($allegati): ?><span class="badge-allegati">📎 <?= $allegati ?></span><?php endif; ?>
                <?php if ($task): ?><span class="badge-task"><?= h(etichettaPillolaTask($task)) ?></span><?php endif; ?>
                <a href="progetto_view.php?id=<?= $idProgetto ?>#evento-<?= $idEvento ?>">Apri nel progetto &rarr;</a>
            </div>
        </div>
    </div>
    <?php
}

// ===== Asse temporale: dal primo mese utile all'ultimo =====
$oggi = new DateTime('today');

// L'inizio non è solo la data di apertura del progetto: se uno step o un evento
// ha una data precedente (es. inserita a posteriori), l'asse deve partire da lì,
// altrimenti quella data viene schiacciata a inizio pista (bug segnalato: eventi
// di lug/dic '25 invisibili con l'asse fermo a gen '26).
$candidatiInizio = [new DateTime($progetto['data_apertura'])];
foreach ($stepConData as $s) {
    $candidatiInizio[] = new DateTime($s['data_apertura'] ?: $s['data_inizio']);
}
foreach ($tuttiEventi as $e) {
    $candidatiInizio[] = new DateTime($e['data_evento']);
}

$inizioAsse = clone min($candidatiInizio);
$inizioAsse->modify('first day of this month')->setTime(0, 0, 0);

$candidatiFine   = [clone $oggi];
$candidatiFine[] = $progetto['data_chiusura'] ? new DateTime($progetto['data_chiusura']) : clone $oggi;
foreach ($stepConData as $s) {
    $candidatiFine[] = new DateTime($s['data_apertura'] ?: $s['data_inizio']);
}
foreach ($tuttiEventi as $e) {
    $candidatiFine[] = new DateTime($e['data_evento']);
}

$fineAsse = clone max($candidatiFine);
$fineAsse->modify('last day of this month')->setTime(23, 59, 59);
if ($fineAsse <= $inizioAsse) {
    $fineAsse = clone $inizioAsse;
    $fineAsse->modify('last day of this month')->setTime(23, 59, 59);
}

$totaleSecondi = max(1, $fineAsse->getTimestamp() - $inizioAsse->getTimestamp());

// ===== Larghezza della pista e livello di zoom =====
// La larghezza scala con la durata effettiva del progetto (px per mese), invece di
// restare fissa: così un progetto di più anni genera davvero una pista più larga
// del contenitore, con uno scorrimento laterale significativo, invece che schiacciare
// tutti i marker nello stesso spazio di un progetto di pochi mesi.
$mesiTotali = ((int) $fineAsse->format('Y') - (int) $inizioAsse->format('Y')) * 12
    + ((int) $fineAsse->format('n') - (int) $inizioAsse->format('n')) + 1;

$zoomConsentiti = ['0.6' => 0.6, '1' => 1.0, '1.5' => 1.5, '2' => 2.0];
$zoomInput       = (string) ($_GET['zoom'] ?? '1');
$zoomEtichetta   = isset($zoomConsentiti[$zoomInput]) ? $zoomInput : '1';
$zoom            = $zoomConsentiti[$zoomEtichetta];

const LT_PX_PER_MESE  = 90;
const LT_MARGINE_BORDO = 90;

$larghezzaPista = max(920, (int) round($mesiTotali * LT_PX_PER_MESE * $zoom) + 2 * LT_MARGINE_BORDO);

/**
 * Espressione CSS per la posizione orizzontale di un marker/tacca lungo la pista:
 * un margine fisso in pixel su entrambi i lati (perché i marker sono centrati sul
 * proprio punto con translateX(-50%): senza margine, quelli vicini a 0%/100%
 * finiscono mezzi tagliati fuori dall'area visibile/scorribile) più una quota
 * proporzionale dello spazio restante, in base alla percentuale [0-100] lungo l'asse.
 */
function posizioneCss(float $percento): string
{
    $margine = LT_MARGINE_BORDO;

    return 'calc(' . $margine . 'px + (100% - ' . (2 * $margine) . 'px) * ' . round($percento / 100, 6) . ')';
}

/**
 * Come posizioneCss(), ma sottrae metà di $larghezzaRem: usata per i marker
 * "punto" (eventi liberi) che devono restare centrati sulla propria data senza
 * ricorrere a transform:translateX(-50%) — un transform su un antenato del
 * fumetto (.lt-callout, position:fixed) gli creerebbe un containing block
 * diverso dalla finestra, vanificandone il posizionamento.
 */
function posizioneCentrataCss(float $percento, float $larghezzaRem): string
{
    return 'calc(' . posizioneCss($percento) . ' - ' . round($larghezzaRem / 2, 3) . 'rem)';
}

// ===== Barre "Gantt" degli step: una corsia ciascuno, dalla data di apertura/inizio
// alla data di chiusura. Se lo step è ancora aperto (nessuna data di chiusura), la
// parte "solida" arriva solo fino all'ultimo evento interno (non fino a oggi: oggi
// potrebbe essere lontanissimo dall'ultima attività reale sullo step), seguita da un
// breve tratteggio che segnala "prosegue, durata non ancora determinata" =====
$barreStep = [];

foreach ($stepConData as $step) {
    $inizioStep = new DateTime($step['data_apertura'] ?: $step['data_inizio']);
    $aperto     = $step['data_chiusura'] === null;
    $eventiStep = $eventiPerStep[(int) $step['id_step']] ?? [];

    if ($aperto) {
        $fineStep = clone $inizioStep;
        foreach ($eventiStep as $evento) {
            $dataEvento = new DateTime($evento['data_evento']);
            if ($dataEvento > $fineStep) {
                $fineStep = $dataEvento;
            }
        }
    } else {
        $fineStep = new DateTime($step['data_chiusura']);
    }

    if ($fineStep < $inizioStep) {
        $fineStep = clone $inizioStep;
    }

    $barreStep[] = [
        'nome'           => $step['nome'],
        'colore'         => $coloreIndice[(int) $step['id_step']],
        'eventi'         => $eventiStep,
        'inizio'         => $inizioStep,
        'fine'           => $fineStep,
        'aperto'         => $aperto,
        'percentoInizio' => percentoSuAsse($inizioStep, $inizioAsse, $totaleSecondi),
        'percentoFine'   => percentoSuAsse($fineStep, $inizioAsse, $totaleSecondi),
    ];
}

usort($barreStep, static fn(array $a, array $b): int => $a['inizio'] <=> $b['inizio']);

/**
 * Espressione CSS per la larghezza di una barra step, sulla stessa scala (margine
 * fisso + quota proporzionale) usata da posizioneCss() per la posizione: una
 * larghezza minima in px evita barre invisibili per step aperti e chiusi lo stesso giorno.
 */
function larghezzaBarraCss(float $percentoInizio, float $percentoFine): string
{
    $delta   = max(0.0, $percentoFine - $percentoInizio);
    $margine = LT_MARGINE_BORDO;

    // Minimo 110px: uno step aperto e chiuso a distanza di pochi giorni avrebbe
    // altrimenti una barra troppo stretta per leggerne il nome (bug segnalato:
    // step brevi ridotti a 1-2 lettere).
    return 'max(110px, calc((100% - ' . (2 * $margine) . 'px) * ' . round($delta / 100, 6) . '))';
}

// ===== Marker degli eventi liberi: punti su un'unica corsia sotto le barre step =====
$markerLiberi = [];

foreach ($eventiLiberi as $evento) {
    $markerLiberi[] = [
        'data'   => new DateTime($evento['data_evento']),
        'evento' => $evento,
    ];
}

usort($markerLiberi, static fn(array $a, array $b): int => $a['data'] <=> $b['data']);

foreach ($markerLiberi as &$m) {
    $m['percento'] = percentoSuAsse($m['data'], $inizioAsse, $totaleSecondi);
}
unset($m);

// ===== Tacche dei mesi =====
$mesi         = [];
$cursoreMese  = clone $inizioAsse;
$primoMese    = true;
while ($cursoreMese <= $fineAsse) {
    $etichetta = meseAbbreviato((int) $cursoreMese->format('n'));
    if ($primoMese || $cursoreMese->format('n') === '1') {
        $etichetta .= " '" . $cursoreMese->format('y');
    }
    $mesi[] = ['percento' => percentoSuAsse($cursoreMese, $inizioAsse, $totaleSecondi), 'etichetta' => $etichetta];
    $cursoreMese->modify('first day of next month');
    $primoMese = false;
}

$percentoOggi = percentoSuAsse($oggi, $inizioAsse, $totaleSecondi);

// ===== Altezza della pista: un blocco di corsie fisse per gli step, una corsia
// per gli eventi liberi (con una piccola riserva per l'impilamento se si
// accavallano), più un margine minimo in fondo. Il fumetto è "position:fixed"
// rispetto alla finestra (vedi posizionaCallout in JS), quindi non serve
// riservargli spazio: sceglie da solo se aprirsi sopra o sotto in base allo
// spazio reale sullo schermo, indipendentemente da dove si trova nella pista. =====
const LT_ALTEZZA_CORSIA  = 34;
const LT_TOP_PAD         = 20;
const LT_GAP_LIBERI      = 14;
const LT_ALTEZZA_LIBERI  = 22;
const LT_RISERVA_LIVELLI = 2;
const LT_ALTEZZA_LIVELLO = 18;
const LT_PAD_FONDO       = 16;

$haLiberi           = count($markerLiberi) > 0;
$altezzaBloccoStep  = count($barreStep) * LT_ALTEZZA_CORSIA;
$topLiberi          = LT_TOP_PAD + $altezzaBloccoStep + ($haLiberi ? LT_GAP_LIBERI : 0);
$altezzaBloccoLiberi = $haLiberi ? (LT_ALTEZZA_LIBERI + LT_RISERVA_LIVELLI * LT_ALTEZZA_LIVELLO) : 0;

$altezzaPista = LT_TOP_PAD + $altezzaBloccoStep
    + ($haLiberi ? LT_GAP_LIBERI + $altezzaBloccoLiberi : 0)
    + LT_PAD_FONDO;
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Linea del tempo - <?= h($progetto['titolo']) ?></title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <a class="link-indietro" href="progetto_view.php?id=<?= $idProgetto ?>"><span class="lt-freccia">&larr;</span> <?= h($progetto['titolo']) ?></a>

    <h1 class="lt-no-stampa">Linea del tempo</h1>
    <p class="lt-sottotitolo">
        <?= h(meseAbbreviato((int) $inizioAsse->format('n'))) ?> <?= $inizioAsse->format('Y') ?>
        &ndash;
        <?= h(meseAbbreviato((int) $fineAsse->format('n'))) ?> <?= $fineAsse->format('Y') ?>
        &middot; oggi: <?= formattaData($oggi->format('Y-m-d')) ?>
    </p>

    <?php if (!$barreStep && !$markerLiberi): ?>
        <p><em>Nessuno step con data né evento libero da mostrare sulla linea del tempo.</em></p>
    <?php else: ?>
        <div class="lt-legenda">
            <span><i class="lt-legenda-tab"></i> Step (durata apertura&rarr;chiusura, tratteggiato se ancora in corso)</span>
            <span><i class="lt-legenda-dot"></i> Evento libero</span>
            <span><i class="lt-legenda-dot" style="font-size:.55rem;display:inline-flex;align-items:center;justify-content:center;">+</i> Più eventi vicini, raggruppati</span>
            <span><i class="lt-legenda-linea"></i> Oggi</span>
        </div>

        <div class="lt-zoom lt-no-stampa">
            <span>Zoom:</span>
            <?php foreach (['0.6' => '60%', '1' => '100%', '1.5' => '150%', '2' => '200%'] as $valore => $etichetta): ?>
                <a href="?id=<?= $idProgetto ?>&zoom=<?= $valore ?>"
                   class="<?= $zoomEtichetta === $valore ? 'lt-zoom-attivo' : '' ?>"><?= $etichetta ?></a>
            <?php endforeach; ?>
            <button type="button" class="lt-stampa-btn" onclick="window.print()">🖨️ Stampa (A3 orizzontale)</button>
        </div>

        <div class="lt-stampa-layout">
            <div class="lt-stampa-step-lista">
                <h2 class="lt-stampa-step-lista-titolo">Step</h2>
                <?php foreach ($barreStep as $b): ?>
                    <div class="lt-stampa-step-riga">
                        <div class="lt-stampa-step-nome">
                            <span class="lt-stampa-step-swatch lt-tab-colore-<?= $b['colore'] ?>"></span>
                            <strong><?= h($b['nome']) ?></strong>
                        </div>
                        <div class="lt-stampa-step-date">
                            <?= formattaData($b['inizio']->format('Y-m-d')) ?> &rarr;
                            <?= $b['aperto'] ? 'in corso' : formattaData($b['fine']->format('Y-m-d')) ?>
                        </div>
                        <div class="lt-stampa-step-stato"><?= $b['aperto'] ? 'In corso' : 'Completato' ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$barreStep): ?>
                    <p><em>Nessuno step con data.</em></p>
                <?php endif; ?>
            </div>

        <div class="linea-tempo-scroll">
            <div class="lt-pista" style="min-width:<?= $larghezzaPista ?>px; height:<?= $altezzaPista ?>px;">

                <?php if ($percentoOggi >= 0 && $percentoOggi <= 100): ?>
                    <div class="lt-oggi" style="left:<?= posizioneCss($percentoOggi) ?>; bottom:<?= LT_PAD_FONDO ?>px;">
                        <span class="lt-oggi-etichetta">Oggi</span>
                    </div>
                <?php endif; ?>

                <?php foreach ($barreStep as $indice => $b): ?>
                    <div class="lt-marker lt-marker-barra<?= $b['aperto'] ? ' lt-marker-in-corso' : '' ?>"
                         style="left:<?= posizioneCss($b['percentoInizio']) ?>; width:<?= larghezzaBarraCss($b['percentoInizio'], $b['percentoFine']) ?>; top:<?= LT_TOP_PAD + $indice * LT_ALTEZZA_CORSIA ?>px;">
                        <button type="button" class="lt-marker-toggle lt-barra lt-tab-colore-<?= $b['colore'] ?>">
                            <span class="lt-barra-nome" title="<?= h($b['nome']) ?>"><?= h($b['nome']) ?></span>
                        </button>
                        <?php if ($b['aperto']): ?>
                            <span class="lt-barra-tratteggio" aria-hidden="true"></span>
                        <?php endif; ?>
                        <div class="lt-callout">
                            <div class="lt-callout-titolo">
                                <?= h($b['nome']) ?> &mdash;
                                <?= formattaDataBreve($b['inizio']->format('Y-m-d')) ?> <?= $b['inizio']->format('Y') ?>
                                &rarr;
                                <?php if ($b['aperto']): ?>
                                    in corso<?= $b['eventi'] ? (' (ultimo evento ' . formattaDataBreve($b['fine']->format('Y-m-d')) . " '" . $b['fine']->format('y') . ')') : '' ?>
                                <?php else: ?>
                                    <?= formattaDataBreve($b['fine']->format('Y-m-d')) . ' ' . $b['fine']->format('Y') ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!$b['eventi']): ?>
                                <div class="lt-evento-dettaglio" style="display:block;"><em>Nessun evento in questo step.</em></div>
                            <?php else: ?>
                                <?php foreach ($b['eventi'] as $evento): ?>
                                    <?php rigaEventoLineaTempo($evento, $idProgetto, $iconeTipo, $allegatiPerEvento, $taskPerEvento); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($markerLiberi as $m): ?>
                    <div class="lt-marker lt-marker-libero" style="left:<?= posizioneCentrataCss($m['percento'], 1.15) ?>; --lt-corsia-top:<?= $topLiberi ?>px;">
                        <button type="button" class="lt-marker-toggle lt-dot"><?= $iconeTipo[$m['evento']['tipo']] ?? '•' ?></button>
                        <div class="lt-callout">
                            <div class="lt-callout-titolo">Evento libero &mdash; <?= formattaDataBreve($m['evento']['data_evento']) ?> <?= $m['data']->format('Y') ?></div>
                            <?php rigaEventoLineaTempo($m['evento'], $idProgetto, $iconeTipo, $allegatiPerEvento, $taskPerEvento); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="lt-mesi" style="min-width:<?= $larghezzaPista ?>px">
                <?php foreach ($mesi as $mese): ?>
                    <span style="left:<?= posizioneCss($mese['percento']) ?>"><?= h($mese['etichetta']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        </div>
    <?php endif; ?>

    <?php if ($stepSenzaData > 0): ?>
        <p class="lt-nota-densita lt-no-stampa">
            <?= $stepSenzaData ?> <?= $stepSenzaData === 1 ? 'step non ha' : 'step non hanno' ?> ancora una data
            di apertura/inizio e non <?= $stepSenzaData === 1 ? 'compare' : 'compaiono' ?> sulla linea:
            <?= $stepSenzaData === 1 ? 'assegnagliene una' : 'assegnagliene una' ?> dalla modifica dello step per includer<?= $stepSenzaData === 1 ? 'lo' : 'li' ?>.
        </p>
    <?php endif; ?>

    <a class="link-indietro lt-no-stampa" href="progetto_view.php?id=<?= $idProgetto ?>">&larr; Torna al progetto</a>
</div>

<script>
/**
 * Dopo il primo render, sui marker "evento libero" (non sugli step) unisce
 * quelli che si sovrappongono visivamente nella stessa corsia (sopra o sotto)
 * in un unico marker "+N", spostando le loro righe nello stesso fumetto.
 * Si basa sulle posizioni realmente calcolate a schermo, quindi funziona a
 * qualunque larghezza e durata del progetto.
 */
(function () {
    function raggruppaCorsia(selettore) {
        var marcatori = Array.prototype.slice.call(document.querySelectorAll(selettore))
            .sort(function (a, b) { return a.getBoundingClientRect().left - b.getBoundingClientRect().left; });

        var attivo = null;

        marcatori.forEach(function (m) {
            if (!attivo) {
                attivo = m;
                return;
            }

            var rAttivo = attivo.getBoundingClientRect();
            var rM = m.getBoundingClientRect();

            if (rM.left < rAttivo.right + 6) {
                var calloutAttivo = attivo.querySelector('.lt-callout');
                var calloutM = m.querySelector('.lt-callout');

                Array.prototype.slice.call(calloutM.querySelectorAll('.lt-evento-riga')).forEach(function (riga) {
                    calloutAttivo.appendChild(riga);
                });

                var totaleRighe = calloutAttivo.querySelectorAll('.lt-evento-riga').length;
                attivo.querySelector('.lt-marker-toggle').textContent = '+' + totaleRighe;

                var titolo = calloutAttivo.querySelector('.lt-callout-titolo');
                if (titolo) {
                    titolo.textContent = totaleRighe + ' eventi liberi ravvicinati';
                }

                m.remove();
            } else {
                attivo = m;
            }
        });
    }

    /**
     * Seconda passata, su TUTTI i marker della corsia (anche gli step, che il
     * raggruppamento sopra non tocca): se due si accavallano ancora — tipico
     * caso, più step aperti lo stesso giorno — allontana il secondo dalla
     * linea invece di sovrapporlo, impilandoli su "anelli" concentrici.
     */
    function distanziaCorsia(selettore) {
        var marcatori = Array.prototype.slice.call(document.querySelectorAll(selettore))
            .sort(function (a, b) { return a.getBoundingClientRect().left - b.getBoundingClientRect().left; });

        var occupatoFinoPerLivello = [];

        marcatori.forEach(function (m) {
            var r = m.getBoundingClientRect();
            var livello = 0;

            while (occupatoFinoPerLivello[livello] !== undefined && r.left < occupatoFinoPerLivello[livello] + 6) {
                livello++;
            }

            occupatoFinoPerLivello[livello] = r.right;

            if (livello > 0) {
                m.style.setProperty('--lt-livello', String(livello));
            }
        });
    }

    raggruppaCorsia('.lt-marker-libero');
    distanziaCorsia('.lt-marker-libero');

    // Scorrimento iniziale: posiziona "oggi" verso destra (non al centro) invece
    // che a inizio asse — gli eventi passati sono la maggioranza, quelli futuri
    // rari, quindi conviene mostrare più storico e poco spazio vuoto a destra.
    // Resta comunque liberamente scorribile in entrambe le direzioni grazie alla
    // pista che ora è realmente più larga del contenitore su progetti lunghi.
    var scrollBox = document.querySelector('.linea-tempo-scroll');
    var oggi      = document.querySelector('.lt-oggi');
    if (scrollBox && oggi) {
        scrollBox.scrollLeft = Math.max(0, oggi.offsetLeft - scrollBox.clientWidth * 0.8);
    }
})();

/**
 * Trascinamento con il mouse (cursore "manina") per scorrere lateralmente la
 * linea del tempo, in alternativa alla scrollbar in basso o al touchpad.
 */
(function () {
    var scrollBox = document.querySelector('.linea-tempo-scroll');
    if (!scrollBox) {
        return;
    }

    var trascinando    = false;
    var partenzaX      = 0;
    var partenzaScroll = 0;
    var mosso          = false;

    scrollBox.addEventListener('mousedown', function (e) {
        if (e.button !== 0) {
            return;
        }
        trascinando    = true;
        mosso          = false;
        partenzaX      = e.clientX;
        partenzaScroll = scrollBox.scrollLeft;
        scrollBox.classList.add('lt-trascinando');
    });

    document.addEventListener('mousemove', function (e) {
        if (!trascinando) {
            return;
        }
        var delta = e.clientX - partenzaX;
        if (Math.abs(delta) > 4) {
            mosso = true;
        }
        scrollBox.scrollLeft = partenzaScroll - delta;
    });

    document.addEventListener('mouseup', function () {
        if (!trascinando) {
            return;
        }
        trascinando = false;
        scrollBox.classList.remove('lt-trascinando');
    });

    // Cattura il click in fase di "capture" (prima che arrivi al gestore globale
    // che apre/chiude i marker): se c'è stato un vero trascinamento, lo blocca,
    // altrimenti un semplice click su un marker verrebbe interpretato due volte
    // (apri/richiudi) subito dopo ogni drag-scroll.
    scrollBox.addEventListener('click', function (e) {
        if (mosso) {
            e.stopPropagation();
            e.preventDefault();
            mosso = false;
        }
    }, true);
})();

/**
 * Posiziona il fumetto di un marker appena aperto in coordinate di finestra
 * (il fumetto è "position:fixed", vedi CSS): la scrollbar nativa del contenitore
 * che scorre si disegna comunque sopra il suo contenuto (non è questione di
 * z-index, è il contenitore stesso a disegnarla per ultima), quindi finché il
 * fumetto resta dentro quel contenitore ne resta sempre coperto — portandolo
 * fuori (fixed rispetto alla finestra, non più annidato lì dentro) risolve alla
 * radice sia questo sia il taglio ai bordi della pista. Sceglie sopra/sotto
 * l'ancora in base a dove c'è più spazio sullo schermo, e resta comunque dentro
 * i margini della finestra in entrambi gli assi.
 */
function posizionaCallout(host) {
    var callout = host.querySelector('.lt-callout');
    if (!callout) {
        return;
    }

    var margine = 10;
    var ancora  = (host.querySelector('.lt-marker-toggle') || host).getBoundingClientRect();
    var box     = callout.getBoundingClientRect();

    var spazioSotto = window.innerHeight - ancora.bottom;
    var top = (spazioSotto >= box.height + margine || spazioSotto >= ancora.top)
        ? ancora.bottom + 8
        : ancora.top - box.height - 8;
    top = Math.max(margine, Math.min(top, window.innerHeight - box.height - margine));

    var left = ancora.left + ancora.width / 2 - box.width / 2;
    left = Math.max(margine, Math.min(left, window.innerWidth - box.width - margine));

    callout.style.top  = top + 'px';
    callout.style.left = left + 'px';
}

/**
 * Un fumetto "fixed" non segue automaticamente il marker se la pista scorre
 * (drag o scrollbar) o la pagina: invece di chiuderlo (tentativo precedente —
 * si chiudeva subito da solo per lo scroll automatico che il browser fa per
 * portare in vista un bottone appena messo a fuoco dal click), lo riposiziona.
 * La capture phase di "scroll" su window intercetta anche lo scroll di elementi
 * interni come .linea-tempo-scroll, che normalmente non fa bubbling.
 */
function riposizionaCalloutAperto() {
    var host = document.querySelector('.lt-marker.lt-aperta');
    if (host) {
        posizionaCallout(host);
    }
}
window.addEventListener('scroll', riposizionaCalloutAperto, true);
window.addEventListener('resize', riposizionaCalloutAperto);

document.addEventListener('click', function (e) {
    var toggle = e.target.closest('.lt-marker-toggle');
    if (toggle) {
        var host = toggle.closest('.lt-marker');
        var apri = !host.classList.contains('lt-aperta');
        document.querySelectorAll('.lt-marker.lt-aperta').forEach(function (m) {
            if (m !== host) {
                m.classList.remove('lt-aperta');
            }
        });
        host.classList.toggle('lt-aperta', apri);
        if (apri) {
            posizionaCallout(host);
        }
        return;
    }

    var riga = e.target.closest('.lt-evento-riga-toggle');
    if (riga) {
        riga.closest('.lt-evento-riga').classList.toggle('lt-evento-aperto');
        return;
    }

    if (!e.target.closest('.lt-marker')) {
        document.querySelectorAll('.lt-marker.lt-aperta').forEach(function (m) { m.classList.remove('lt-aperta'); });
    }
});
</script>
</body>
</html>
