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

$stmt = $pdo->prepare('SELECT id_step, nome, data_apertura, data_inizio FROM step WHERE fk_progetto = ? ORDER BY ordine, id_step');
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
            <span class="lt-evento-riga-data"><?= formattaDataBreve($evento['data_evento']) ?></span>
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

// ===== Asse temporale: dal mese di apertura del progetto all'ultimo mese utile =====
$inizioAsse = new DateTime($progetto['data_apertura']);
$inizioAsse->modify('first day of this month')->setTime(0, 0, 0);

$candidatiFine   = [new DateTime('today')];
$candidatiFine[] = $progetto['data_chiusura'] ? new DateTime($progetto['data_chiusura']) : new DateTime('today');
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

// ===== Marker: uno step (posizionato a data_apertura/data_inizio) o un evento libero =====
$marker = [];

foreach ($stepConData as $step) {
    $data     = new DateTime($step['data_apertura'] ?: $step['data_inizio']);
    $marker[] = [
        'tipo'    => 'step',
        'data'    => $data,
        'nome'    => $step['nome'],
        'colore'  => $coloreIndice[(int) $step['id_step']],
        'eventi'  => $eventiPerStep[(int) $step['id_step']] ?? [],
    ];
}

foreach ($eventiLiberi as $evento) {
    $marker[] = [
        'tipo'   => 'libero',
        'data'   => new DateTime($evento['data_evento']),
        'evento' => $evento,
    ];
}

usort($marker, static fn(array $a, array $b): int => $a['data'] <=> $b['data']);

foreach ($marker as $indice => &$m) {
    $m['posizione'] = $indice % 2 === 0 ? 'sopra' : 'sotto';
    $m['percento']  = percentoSuAsse($m['data'], $inizioAsse, $totaleSecondi);
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

$oggi          = new DateTime('today');
$percentoOggi  = percentoSuAsse($oggi, $inizioAsse, $totaleSecondi);
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
    <a class="link-indietro" href="progetto_view.php?id=<?= $idProgetto ?>">&larr; <?= h($progetto['titolo']) ?></a>

    <h1>Linea del tempo</h1>
    <p class="lt-sottotitolo">
        <?= h(meseAbbreviato((int) $inizioAsse->format('n'))) ?> <?= $inizioAsse->format('Y') ?>
        &ndash;
        <?= h(meseAbbreviato((int) $fineAsse->format('n'))) ?> <?= $fineAsse->format('Y') ?>
        &middot; oggi: <?= formattaData($oggi->format('Y-m-d')) ?>
    </p>

    <?php if (!$marker): ?>
        <p><em>Nessuno step con data né evento libero da mostrare sulla linea del tempo.</em></p>
    <?php else: ?>
        <div class="lt-legenda">
            <span><i class="lt-legenda-tab"></i> Step</span>
            <span><i class="lt-legenda-dot"></i> Evento libero</span>
            <span><i class="lt-legenda-dot" style="font-size:.55rem;display:inline-flex;align-items:center;justify-content:center;">+</i> Più eventi vicini, raggruppati</span>
            <span><i class="lt-legenda-linea"></i> Oggi</span>
        </div>

        <div class="linea-tempo-scroll">
            <div class="lt-pista">
                <div class="lt-asse"></div>

                <?php if ($percentoOggi >= 0 && $percentoOggi <= 100): ?>
                    <div class="lt-oggi" style="left:<?= $percentoOggi ?>%">
                        <span class="lt-oggi-etichetta">Oggi</span>
                    </div>
                <?php endif; ?>

                <?php foreach ($marker as $m): ?>
                    <div class="lt-marker lt-<?= $m['posizione'] ?><?= $m['tipo'] === 'libero' ? ' lt-marker-libero' : '' ?>" style="left:<?= $m['percento'] ?>%">
                        <?php if ($m['tipo'] === 'step'): ?>
                            <button type="button" class="lt-marker-toggle lt-tab lt-tab-colore-<?= $m['colore'] ?>"><?= h($m['nome']) ?></button>
                            <div class="lt-callout">
                                <div class="lt-callout-titolo"><?= h($m['nome']) ?> &mdash; <?= formattaDataBreve($m['data']->format('Y-m-d')) ?> <?= $m['data']->format('Y') ?></div>
                                <?php if (!$m['eventi']): ?>
                                    <div class="lt-evento-dettaglio" style="display:block;"><em>Nessun evento in questo step.</em></div>
                                <?php else: ?>
                                    <?php foreach ($m['eventi'] as $evento): ?>
                                        <?php rigaEventoLineaTempo($evento, $idProgetto, $iconeTipo, $allegatiPerEvento, $taskPerEvento); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <button type="button" class="lt-marker-toggle lt-dot"><?= $iconeTipo[$m['evento']['tipo']] ?? '•' ?></button>
                            <div class="lt-callout">
                                <div class="lt-callout-titolo">Evento libero &mdash; <?= formattaDataBreve($m['evento']['data_evento']) ?> <?= $m['data']->format('Y') ?></div>
                                <?php rigaEventoLineaTempo($m['evento'], $idProgetto, $iconeTipo, $allegatiPerEvento, $taskPerEvento); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="lt-mesi">
                <?php foreach ($mesi as $mese): ?>
                    <span style="left:<?= $mese['percento'] ?>%"><?= h($mese['etichetta']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($stepSenzaData > 0): ?>
        <p class="lt-nota-densita">
            <?= $stepSenzaData ?> <?= $stepSenzaData === 1 ? 'step non ha' : 'step non hanno' ?> ancora una data
            di apertura/inizio e non <?= $stepSenzaData === 1 ? 'compare' : 'compaiono' ?> sulla linea:
            <?= $stepSenzaData === 1 ? 'assegnagliene una' : 'assegnagliene una' ?> dalla modifica dello step per includer<?= $stepSenzaData === 1 ? 'lo' : 'li' ?>.
        </p>
    <?php endif; ?>

    <a class="link-indietro" href="progetto_view.php?id=<?= $idProgetto ?>">&larr; Torna al progetto</a>
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

    raggruppaCorsia('.lt-sopra.lt-marker-libero');
    raggruppaCorsia('.lt-sotto.lt-marker-libero');

    distanziaCorsia('.lt-sopra');
    distanziaCorsia('.lt-sotto');
})();

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
