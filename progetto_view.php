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

$stmt = $pdo->prepare('SELECT * FROM eventi WHERE fk_progetto = ? ORDER BY id_evento');
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

/**
 * Ordinamento eventi indipendente per sezione (eventi liberi, o un singolo step):
 * ogni sezione ha il proprio campo/direzione nella query string (validati contro
 * una lista fissa), così cambiare l'ordinamento di una sezione non tocca le altre.
 */
function chiaviOrdinamento(string $sezione): array
{
    return ['ordina_' . $sezione, 'dir_' . $sezione];
}

function leggiOrdinamento(string $sezione): array
{
    [$chiaveCampo, $chiaveDir] = chiaviOrdinamento($sezione);
    $campo     = in_array($_GET[$chiaveCampo] ?? '', ['data_evento', 'creato_il'], true) ? $_GET[$chiaveCampo] : 'data_evento';
    $direzione = ($_GET[$chiaveDir] ?? '') === 'asc' ? 'ASC' : 'DESC';
    return [$campo, $direzione];
}

function ordinaEventi(array $eventi, string $campo, string $direzione): array
{
    usort($eventi, function (array $a, array $b) use ($campo, $direzione): int {
        $cmp = $a[$campo] <=> $b[$campo];
        if ($cmp === 0) {
            $cmp = $a['id_evento'] <=> $b['id_evento'];
        }
        return $direzione === 'ASC' ? $cmp : -$cmp;
    });
    return $eventi;
}

/**
 * Campi nascosti con l'ordinamento attuale di tutte le sezioni tranne quella
 * corrente: ogni mini-form di ordinamento (una per sezione) li riporta così,
 * inviandola, non azzera l'ordinamento scelto per le altre sezioni.
 */
function campiNascostiOrdinamento(array $ordinamentoPerSezione, string $sezioneCorrente): void
{
    foreach ($ordinamentoPerSezione as $sezione => $valori) {
        if ($sezione === $sezioneCorrente) {
            continue;
        }
        [$campo, $direzione]         = $valori;
        [$chiaveCampo, $chiaveDir] = chiaviOrdinamento($sezione);
        echo '<input type="hidden" name="' . h($chiaveCampo) . '" value="' . h($campo) . '">';
        echo '<input type="hidden" name="' . h($chiaveDir) . '" value="' . h(strtolower($direzione)) . '">';
    }
}

$ordinamentoPerSezione = ['liberi' => leggiOrdinamento('liberi')];
foreach ($stepList as $step) {
    $ordinamentoPerSezione['step' . (int) $step['id_step']] = leggiOrdinamento('step' . (int) $step['id_step']);
}

[$campoLiberi, $direzioneLiberi] = $ordinamentoPerSezione['liberi'];
$eventiLiberi = ordinaEventi($eventiLiberi, $campoLiberi, $direzioneLiberi);

foreach ($stepList as $step) {
    $idStepOrdinamento = (int) $step['id_step'];
    [$campo, $direzione] = $ordinamentoPerSezione['step' . $idStepOrdinamento];
    $eventiPerStep[$idStepOrdinamento] = ordinaEventi($eventiPerStep[$idStepOrdinamento] ?? [], $campo, $direzione);
}

$allegatiPerEvento = [];

if ($tuttiEventi) {
    $idEventiList = array_column($tuttiEventi, 'id_evento');
    $segnaposto   = implode(',', array_fill(0, count($idEventiList), '?'));

    $stmt = $pdo->prepare("SELECT * FROM eventi_allegati WHERE fk_evento IN ($segnaposto) ORDER BY id_allegato");
    $stmt->execute($idEventiList);

    foreach ($stmt->fetchAll() as $allegato) {
        $allegatiPerEvento[(int) $allegato['fk_evento']][] = $allegato;
    }
}

$taskPerEvento = [];

if ($tuttiEventi) {
    $stmt = $pdo->prepare("SELECT * FROM eventi_task WHERE fk_evento IN ($segnaposto) ORDER BY id_task");
    $stmt->execute($idEventiList);

    foreach ($stmt->fetchAll() as $task) {
        $taskPerEvento[(int) $task['fk_evento']][] = $task;
    }
}

$stmt = $pdo->prepare(
    'SELECT p.* FROM partecipanti p
     JOIN progetto_partecipanti pp ON pp.fk_partecipante = p.id_partecipante
     WHERE pp.fk_progetto = ? ORDER BY p.cognome, p.nome'
);
$stmt->execute([$idProgetto]);
$partecipantiProgetto = $stmt->fetchAll();

$partecipantiPerStep   = [];
$partecipantiPerEvento = [];

if ($stepList) {
    $idStepList = array_column($stepList, 'id_step');
    $segnapostoStep = implode(',', array_fill(0, count($idStepList), '?'));

    $stmt = $pdo->prepare(
        "SELECT sp.fk_step, p.* FROM step_partecipanti sp
         JOIN partecipanti p ON p.id_partecipante = sp.fk_partecipante
         WHERE sp.fk_step IN ($segnapostoStep) ORDER BY p.cognome, p.nome"
    );
    $stmt->execute($idStepList);

    foreach ($stmt->fetchAll() as $riga) {
        $partecipantiPerStep[(int) $riga['fk_step']][] = $riga;
    }
}

if ($tuttiEventi) {
    $stmt = $pdo->prepare(
        "SELECT ep.fk_evento, p.* FROM eventi_partecipanti ep
         JOIN partecipanti p ON p.id_partecipante = ep.fk_partecipante
         WHERE ep.fk_evento IN ($segnaposto) ORDER BY p.cognome, p.nome"
    );
    $stmt->execute($idEventiList);

    foreach ($stmt->fetchAll() as $riga) {
        $partecipantiPerEvento[(int) $riga['fk_evento']][] = $riga;
    }
}

$etichetteStato = ['da_fare' => 'Da fare', 'in_corso' => 'In corso', 'completato' => 'Completato'];
$iconeTipo      = ['nota' => '📝', 'riunione' => '👥', 'email' => '✉️', 'registrazione' => '🎙️'];

/**
 * Stampa un evento come blocco <details> compatto ed espandibile, trascinabile
 * verso un'altra zona (step o "eventi liberi").
 */
function stampaEvento(array $evento, array $allegatiPerEvento, array $taskPerEvento, array $iconeTipo, array $partecipantiPerEvento): void
{
    $idEvento = (int) $evento['id_evento'];
    $allegati = $allegatiPerEvento[$idEvento] ?? [];
    $task     = $taskPerEvento[$idEvento] ?? [];
    $partecipanti = $partecipantiPerEvento[$idEvento] ?? [];
    ?>
    <details class="evento evento-tipo-<?= h($evento['tipo']) ?>" id="evento-<?= $idEvento ?>" draggable="true" data-evento-id="<?= $idEvento ?>">
        <summary class="evento-summary">
            <span class="evento-riga">
                <span><?= $iconeTipo[$evento['tipo']] ?? '' ?></span>
                <span class="evento-titolo-compatto"><?= h($evento['titolo']) ?></span>
                <span class="evento-data-compatta"><?= formattaDataOra($evento['data_evento']) ?></span>
            </span>
            <span class="azioni azioni-icone">
                <?php if ($allegati): ?>
                    <span class="badge-allegati" title="<?= count($allegati) ?> allegati">📎 <?= count($allegati) ?></span>
                <?php endif; ?>
                <?php if ($task): ?>
                    <span class="badge-task"><?= h(etichettaPillolaTask($task)) ?></span>
                <?php endif; ?>
                <?php if ($partecipanti): ?>
                    <button type="button" class="badge-partecipanti btn-partecipanti" data-scope="evento" data-id="<?= $idEvento ?>"
                        title="<?= h(implode(', ', array_map('formattaNomePartecipante', $partecipanti))) ?>">👤 <?= count($partecipanti) ?></button>
                <?php else: ?>
                    <button type="button" class="icona-azione btn-partecipanti" data-scope="evento" data-id="<?= $idEvento ?>" title="Partecipanti evento">👤</button>
                <?php endif; ?>
                <a href="evento_form.php?id=<?= $idEvento ?>" class="icona-azione" title="Modifica">✏️</a>
                <a href="evento_delete.php?id=<?= $idEvento ?>" class="icona-azione icona-azione-elimina" title="Elimina"
                   onclick="return confirm('Eliminare questo evento?');">🗑️</a>
                <span class="zona-stampe">
                    <a href="stampa_cartellina.php?tipo=evento&id=<?= $idEvento ?>" class="icona-azione" target="_blank"
                       title="Stampa cartellina evento">🖨️</a>
                </span>
            </span>
        </summary>
        <div class="evento-corpo">
            <div class="evento-corpo-principale">
                <?php if ($evento['testo']): ?>
                    <div class="box-testo"><?= nl2br(h($evento['testo'])) ?></div>
                <?php endif; ?>

                <?php if ($allegati): ?>
                    <div class="allegati">
                        <?php foreach ($allegati as $allegato): ?>
                            <?php $urlAllegato = 'allegati/' . cartellaAllegato($allegato['cartella_relativa'], $idEvento) . '/' . rawurlencode($allegato['nome_file_salvato']); ?>
                            <?php if ($allegato['ruolo'] === 'audio'): ?>
                                <br><audio controls src="<?= h($urlAllegato) ?>"></audio>
                            <?php else: ?>
                                <a href="<?= h($urlAllegato) ?>" target="_blank">📎 <?= h($allegato['nome_file_originale']) ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="task-lista" data-evento-task="<?= $idEvento ?>">
                <h3 class="task-lista-titolo">Task</h3>
                <?php foreach ($task as $t): ?>
                    <div class="task-riga" data-task-id="<?= (int) $t['id_task'] ?>">
                        <label class="task-check">
                            <input type="checkbox" <?= $t['fatto'] ? 'checked' : '' ?>>
                            <span class="task-testo <?= $t['fatto'] ? 'task-fatto' : '' ?>"
                                <?= ($t['data_chiusura'] ?? null) ? 'title="Completato il ' . h(formattaDataOra($t['data_chiusura'])) . '"' : '' ?>><?= h($t['testo']) ?></span>
                        </label>
                        <span class="task-azioni">
                            <button type="button" class="task-modifica" title="Modifica testo">✏️</button>
                            <button type="button" class="task-elimina" title="Elimina task">×</button>
                        </span>
                    </div>
                <?php endforeach; ?>
                <div class="task-aggiungi-riga">
                    <input type="text" class="task-nuovo-input" placeholder="Nuovo task...">
                    <button type="button" class="task-aggiungi-btn" title="Aggiungi task">+</button>
                </div>
            </div>
        </div>
    </details>
    <?php
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Progetto - <?= h($progetto['titolo']) ?></title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <a class="link-indietro" href="index.php">&larr; Elenco progetti</a>

    <div class="progetto-testata">
        <h1><?= h($progetto['titolo']) ?></h1>
        <form method="get" action="ricerca.php" class="ricerca-globale">
            <input type="hidden" name="id" value="<?= $idProgetto ?>">
            <input type="search" name="q" placeholder="🔍 Cerca in questo progetto (eventi, step, task)...">
        </form>
    </div>

    <?php if ($progetto['descrizione']): ?>
        <div class="box-testo"><?= nl2br(h($progetto['descrizione'])) ?></div>
    <?php endif; ?>

    <p>
        <strong>Apertura:</strong> <?= formattaData($progetto['data_apertura']) ?>
        &middot; <strong>Chiusura:</strong> <?= $progetto['data_chiusura'] ? formattaData($progetto['data_chiusura']) : 'in corso' ?>
    </p>

    <div class="azioni-principali">
        <a class="btn btn-secondary" href="progetto_form.php?id=<?= $idProgetto ?>">Modifica progetto</a>
        <a class="btn btn-secondary" href="linea-tempo.php?id=<?= $idProgetto ?>">🕰️ Linea del tempo</a>
        <a class="btn btn-secondary" href="progetto_partecipanti.php?id=<?= $idProgetto ?>">👤 Partecipanti<?= $partecipantiProgetto ? ' (' . count($partecipantiProgetto) . ')' : '' ?></a>
        <a class="btn" href="step_form.php?fk_progetto=<?= $idProgetto ?>">+ Nuovo step</a>
        <a class="btn" href="evento_form.php?fk_progetto=<?= $idProgetto ?>">+ Evento libero</a>
        <a class="btn" href="registrazione_form.php?fk_progetto=<?= $idProgetto ?>">🎙️ Registrazione libera</a>
        <a class="btn btn-danger" href="progetto_delete.php?id=<?= $idProgetto ?>"
           onclick="return confirm('Eliminare questo progetto e tutto il suo contenuto?');">Elimina progetto</a>
    </div>

    <div class="zona-stampe zona-stampe-progetto">
        <span class="zona-stampe-titolo">Stampe</span>
        <a class="btn-stampa" href="stampa_cartellina.php?tipo=progetto&id=<?= $idProgetto ?>" target="_blank"
           title="Stampa cartellina progetto">🖨️ Cartellina progetto</a>
        <a class="btn-stampa" href="report_progetto.php?id=<?= $idProgetto ?>" target="_blank"
           title="Report completo del progetto, esplorabile e stampabile">📋 Report progetto</a>
    </div>

    <h2>Eventi liberi</h2>
    <div class="sezione-liberi" data-drop-step="">
        <div class="sezione-liberi-titolo">Non assegnati a nessuno step &mdash; trascina qui per rimuovere l'assegnazione</div>
        <?php if ($eventiLiberi): ?>
            <form method="get" class="ordina-form">
                <input type="hidden" name="id" value="<?= $idProgetto ?>">
                <?php campiNascostiOrdinamento($ordinamentoPerSezione, 'liberi'); ?>
                <span>Ordina per
                    <select name="ordina_liberi" onchange="this.form.submit()">
                        <option value="data_evento" <?= $campoLiberi === 'data_evento' ? 'selected' : '' ?>>Data evento</option>
                        <option value="creato_il" <?= $campoLiberi === 'creato_il' ? 'selected' : '' ?>>Data caricamento</option>
                    </select>
                </span>
                <select name="dir_liberi" onchange="this.form.submit()">
                    <option value="desc" <?= $direzioneLiberi === 'DESC' ? 'selected' : '' ?>>Decrescente (più recenti prima)</option>
                    <option value="asc" <?= $direzioneLiberi === 'ASC' ? 'selected' : '' ?>>Crescente (più vecchi prima)</option>
                </select>
            </form>
        <?php else: ?>
            <p><em>Nessun evento libero.</em></p>
        <?php endif; ?>
        <?php foreach ($eventiLiberi as $evento): ?>
            <?php stampaEvento($evento, $allegatiPerEvento, $taskPerEvento, $iconeTipo, $partecipantiPerEvento); ?>
        <?php endforeach; ?>
    </div>

    <div class="step-sezione-intestazione">
        <h2>Step</h2>
        <?php if (count($stepList) > 1): ?>
            <a class="btn-stampa" href="step_ripristina_ordine.php?id=<?= $idProgetto ?>"
               onclick="return confirm('Ripristinare l\'ordine degli step a quello di creazione?\n\nLe posizioni spostate con le frecce andranno perse.');">↺ Ripristina ordinamento step</a>
        <?php endif; ?>
    </div>

    <?php if (!$stepList): ?>
        <p>Nessuno step presente. Creane uno per organizzare gli eventi, oppure lasciali liberi.</p>
    <?php endif; ?>

    <?php foreach ($stepList as $indice => $step): $idStep = (int) $step['id_step']; $stepChiuso = $step['stato'] === 'completato'; ?>
        <div class="step-card <?= classeColoreStep($indice) ?><?= $stepChiuso ? ' step-chiuso' : '' ?>" id="step-<?= $idStep ?>"
             data-drop-step="<?= $idStep ?>" data-step-stato="<?= h($step['stato']) ?>" data-step-nome="<?= h($step['nome']) ?>">
            <div class="step-header">
                <div>
                    <strong><?= h($step['nome']) ?></strong>
                    <span class="badge badge-<?= h($step['stato']) ?>"><?= h($etichetteStato[$step['stato']] ?? $step['stato']) ?></span>
                </div>
                <div class="azioni azioni-icone">
                    <?php if ($indice > 0): ?>
                        <a class="icona-azione" href="step_sposta.php?id=<?= $idStep ?>&direzione=su" title="Sposta su">⬆️</a>
                    <?php endif; ?>
                    <?php if ($indice < count($stepList) - 1): ?>
                        <a class="icona-azione" href="step_sposta.php?id=<?= $idStep ?>&direzione=giu" title="Sposta giù">⬇️</a>
                    <?php endif; ?>
                    <?php $partecipantiStep = $partecipantiPerStep[$idStep] ?? []; ?>
                    <?php if ($partecipantiStep): ?>
                        <button type="button" class="badge-partecipanti btn-partecipanti" data-scope="step" data-id="<?= $idStep ?>"
                            title="<?= h(implode(', ', array_map('formattaNomePartecipante', $partecipantiStep))) ?>">👤 <?= count($partecipantiStep) ?></button>
                    <?php else: ?>
                        <button type="button" class="icona-azione btn-partecipanti" data-scope="step" data-id="<?= $idStep ?>" title="Partecipanti allo step">👤</button>
                    <?php endif; ?>
                    <a class="icona-azione" href="step_form.php?id=<?= $idStep ?>" title="Modifica step">✏️</a>
                    <a class="icona-azione icona-azione-elimina" href="step_delete.php?id=<?= $idStep ?>" title="Elimina step"
                       onclick="return confirm('Eliminare questo step? Gli eventi collegati restano nel progetto e tornano liberi.');">🗑️</a>
                    <span class="zona-stampe">
                        <a class="icona-azione" href="stampa_cartellina.php?tipo=step&id=<?= $idStep ?>" target="_blank"
                           title="Stampa cartellina step">🖨️</a>
                    </span>
                </div>
            </div>

            <p class="step-date">
                Apertura: <?= ($step['data_apertura'] ?? null) ? formattaData($step['data_apertura']) : '—' ?>
                &middot; Inizio: <?= ($step['data_inizio'] ?? null) ? formattaData($step['data_inizio']) : '—' ?>
                &middot; Chiusura: <?= ($step['data_chiusura'] ?? null) ? formattaData($step['data_chiusura']) : '—' ?>
            </p>
            <?php if ($partecipantiStep): ?>
                <p class="step-partecipanti">👤 <?= h(implode(', ', array_map('formattaNomePartecipante', $partecipantiStep))) ?></p>
            <?php endif; ?>

            <div class="step-azioni-contenuto">
                <a class="btn azione-riapertura" href="evento_form.php?fk_step=<?= $idStep ?>">+ Evento</a>
                <a class="btn azione-riapertura" href="registrazione_form.php?fk_step=<?= $idStep ?>">🎙️ Registrazione</a>
            </div>

            <?php if ($step['descrizione']): ?>
                <p><?= nl2br(h($step['descrizione'])) ?></p>
            <?php endif; ?>

            <?php $eventi = $eventiPerStep[$idStep] ?? []; ?>
            <?php $sezioneStep = 'step' . $idStep; [$campoStep, $direzioneStep] = $ordinamentoPerSezione[$sezioneStep]; ?>

            <?php if ($eventi): ?>
                <form method="get" class="ordina-form">
                    <input type="hidden" name="id" value="<?= $idProgetto ?>">
                    <?php campiNascostiOrdinamento($ordinamentoPerSezione, $sezioneStep); ?>
                    <span>Ordina per
                        <select name="ordina_<?= $sezioneStep ?>" onchange="this.form.submit()">
                            <option value="data_evento" <?= $campoStep === 'data_evento' ? 'selected' : '' ?>>Data evento</option>
                            <option value="creato_il" <?= $campoStep === 'creato_il' ? 'selected' : '' ?>>Data caricamento</option>
                        </select>
                    </span>
                    <select name="dir_<?= $sezioneStep ?>" onchange="this.form.submit()">
                        <option value="desc" <?= $direzioneStep === 'DESC' ? 'selected' : '' ?>>Decrescente (più recenti prima)</option>
                        <option value="asc" <?= $direzioneStep === 'ASC' ? 'selected' : '' ?>>Crescente (più vecchi prima)</option>
                    </select>
                </form>
            <?php else: ?>
                <p><em>Nessun evento in questo step. Trascina qui un evento libero.</em></p>
            <?php endif; ?>

            <?php foreach ($eventi as $evento): ?>
                <?php stampaEvento($evento, $allegatiPerEvento, $taskPerEvento, $iconeTipo, $partecipantiPerEvento); ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <a class="link-indietro" href="index.php">&larr; Elenco progetti</a>
</div>

<dialog id="modal-partecipanti" class="modal-partecipanti">
    <div class="modal-partecipanti-header">
        <h2 id="modal-partecipanti-titolo">Partecipanti</h2>
        <button type="button" class="modal-chiudi" data-chiudi-modale title="Chiudi" aria-label="Chiudi">&times;</button>
    </div>
    <input type="search" id="modal-partecipanti-cerca" class="modal-partecipanti-cerca" placeholder="🔍 Cerca partecipante...">
    <div id="modal-partecipanti-lista" class="lista-partecipanti-selezione modal-partecipanti-lista"></div>
    <div class="modal-partecipanti-azioni">
        <button type="button" class="btn btn-secondary" data-chiudi-modale>Annulla</button>
        <button type="button" id="modal-partecipanti-salva" class="btn">Salva</button>
    </div>
</dialog>

<script>
(function () {
    var modale       = document.getElementById('modal-partecipanti');
    var titolo        = document.getElementById('modal-partecipanti-titolo');
    var lista         = document.getElementById('modal-partecipanti-lista');
    var cerca         = document.getElementById('modal-partecipanti-cerca');
    var bottoneSalva  = document.getElementById('modal-partecipanti-salva');
    var scopeCorrente = null;
    var idCorrente    = null;

    function urlEndpoint(scope, id) {
        return (scope === 'step' ? 'step_partecipanti.php' : 'evento_partecipanti.php') + '?id=' + encodeURIComponent(id) + '&ajax=1';
    }

    /**
     * Riga della lista costruita via DOM (non innerHTML con stringhe interpolate):
     * cognome/nome/email arrivano dall'anagrafica e potrebbero contenere caratteri
     * HTML speciali (es. un cognome con un apostrofo o "&"), quindi vanno trattati
     * come testo, non come markup.
     */
    function costruisciRiga(p) {
        var label = document.createElement('label');
        label.className = 'partecipante-riga';
        label.dataset.cerca = (p.nome + ' ' + p.contatti).toLowerCase();

        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = p.id;
        checkbox.checked = p.selezionato;

        var nome = document.createElement('span');
        nome.className = 'partecipante-nome';
        nome.textContent = p.nome;

        var contatti = document.createElement('span');
        contatti.className = 'partecipante-contatti';
        contatti.textContent = p.contatti;

        label.append(checkbox, nome, contatti);
        return label;
    }

    function apriModale(scope, id) {
        scopeCorrente = scope;
        idCorrente    = id;
        cerca.value   = '';
        titolo.textContent = 'Partecipanti';
        lista.innerHTML = '<p class="nota-campo">Caricamento...</p>';
        modale.showModal();

        fetch(urlEndpoint(scope, id))
            .then(function (r) { return r.json(); })
            .then(function (dati) {
                if (!dati.ok) {
                    lista.innerHTML = '';
                    lista.appendChild(Object.assign(document.createElement('p'), { className: 'errore', textContent: dati.error }));
                    return;
                }

                titolo.textContent = dati.titolo;
                lista.innerHTML = '';

                if (!dati.partecipanti.length) {
                    var messaggio = document.createElement('p');
                    messaggio.className = 'nota-campo';
                    messaggio.textContent = dati.vuotoMessaggio + ' ';
                    if (dati.vuotoLinkHref) {
                        var link = document.createElement('a');
                        link.href = dati.vuotoLinkHref;
                        link.target = '_blank';
                        link.textContent = dati.vuotoLinkTesto;
                        messaggio.appendChild(link);
                    }
                    lista.appendChild(messaggio);
                    return;
                }

                dati.partecipanti.forEach(function (p) {
                    lista.appendChild(costruisciRiga(p));
                });
            })
            .catch(function () {
                lista.innerHTML = '';
                lista.appendChild(Object.assign(document.createElement('p'), { className: 'errore', textContent: 'Errore di comunicazione con il server.' }));
            });
    }

    document.querySelectorAll('.btn-partecipanti').forEach(function (bottone) {
        bottone.addEventListener('click', function (e) {
            // I bottoni sugli eventi vivono dentro <summary>: senza queste due righe
            // il click aprirebbe la modale E (ri)aprirebbe/chiuderebbe l'evento.
            e.preventDefault();
            e.stopPropagation();
            apriModale(bottone.getAttribute('data-scope'), bottone.getAttribute('data-id'));
        });
    });

    cerca.addEventListener('input', function () {
        var q = cerca.value.trim().toLowerCase();
        lista.querySelectorAll('.partecipante-riga').forEach(function (riga) {
            riga.classList.toggle('modal-partecipanti-riga-nascosta', riga.dataset.cerca.indexOf(q) === -1);
        });
    });

    modale.querySelectorAll('[data-chiudi-modale]').forEach(function (bottone) {
        bottone.addEventListener('click', function () { modale.close(); });
    });

    // Click sullo sfondo (il target è la <dialog> stessa, non un suo discendente).
    modale.addEventListener('click', function (e) {
        if (e.target === modale) {
            modale.close();
        }
    });

    bottoneSalva.addEventListener('click', function () {
        var selezionati = Array.from(lista.querySelectorAll('input[type="checkbox"]:checked')).map(function (c) { return c.value; });
        var corpo = selezionati.map(function (id) { return 'partecipanti[]=' + encodeURIComponent(id); }).join('&');

        fetch(urlEndpoint(scopeCorrente, idCorrente), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: corpo
        })
            .then(function (r) { return r.json(); })
            .then(function (risposta) {
                if (!risposta.ok) {
                    alert('Errore: ' + risposta.error);
                    return;
                }
                // Ricarica sulla card interessata: più semplice e robusto che rimettere
                // a mano in sincronia badge/chip nel DOM (che potrebbero non esistere
                // ancora, es. primo partecipante appena aggiunto).
                var ancora = (scopeCorrente === 'step' ? 'step-' : 'evento-') + idCorrente;
                window.location.href = 'progetto_view.php?id=<?= $idProgetto ?>&_=' + Date.now() + '&restaChiuso=1#' + ancora;
            })
            .catch(function () {
                alert('Errore di comunicazione con il server.');
            });
    });
})();
</script>

<script>
document.querySelectorAll('[data-evento-id]').forEach(function (el) {
    el.addEventListener('dragstart', function (e) {
        e.dataTransfer.setData('text/plain', el.getAttribute('data-evento-id'));
        e.dataTransfer.effectAllowed = 'move';
    });
});

/**
 * Se lo step (data-step-stato="completato") è chiuso, chiede conferma prima di
 * eseguire un'azione che lo riguarda (trascinarci sopra un evento, o crearne uno
 * nuovo): se l'utente conferma, riapre lo step (endpoint step_riapri.php, stato
 * -> in_corso, data_chiusura azzerata) e poi esegue l'azione; se annulla, non fa
 * nulla. Se lo step non è chiuso, esegue subito l'azione senza chiedere nulla.
 */
function eseguiConEventualeRiapertura(stepCard, azione) {
    if (stepCard.getAttribute('data-step-stato') !== 'completato') {
        azione();
        return;
    }

    var nome = stepCard.getAttribute('data-step-nome');
    var oggi = new Date().toLocaleDateString('it-IT');

    if (!confirm('Lo step "' + nome + '" è chiuso.\nContinuando, tornerà in stato "In corso" (data odierna: ' + oggi + ').\n\nContinuare?')) {
        return;
    }

    fetch('step_riapri.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id_step=' + encodeURIComponent(stepCard.getAttribute('data-drop-step'))
    })
        .then(function (r) { return r.json(); })
        .then(function (risposta) {
            if (!risposta.ok) {
                alert('Errore: ' + risposta.error);
                return;
            }
            azione();
        })
        .catch(function () {
            alert('Errore di comunicazione con il server.');
        });
}

document.querySelectorAll('.azione-riapertura').forEach(function (link) {
    link.addEventListener('click', function (e) {
        var stepCard = link.closest('.step-card');
        if (!stepCard || stepCard.getAttribute('data-step-stato') !== 'completato') {
            return;
        }
        e.preventDefault();
        eseguiConEventualeRiapertura(stepCard, function () {
            location.href = link.href;
        });
    });
});

document.querySelectorAll('[data-drop-step]').forEach(function (zona) {
    zona.addEventListener('dragover', function (e) {
        e.preventDefault();
        zona.classList.add('drop-attivo');
    });
    zona.addEventListener('dragleave', function () {
        zona.classList.remove('drop-attivo');
    });
    zona.addEventListener('drop', function (e) {
        e.preventDefault();
        zona.classList.remove('drop-attivo');

        var idEvento = e.dataTransfer.getData('text/plain');
        var fkStep = zona.getAttribute('data-drop-step');

        function spostaEvento() {
            fetch('evento_sposta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_evento=' + encodeURIComponent(idEvento) + '&fk_step=' + encodeURIComponent(fkStep)
            })
                .then(function (r) { return r.json(); })
                .then(function (risposta) {
                    if (risposta.ok) {
                        location.reload();
                    } else {
                        alert('Errore: ' + risposta.error);
                    }
                })
                .catch(function () {
                    alert('Errore di comunicazione con il server.');
                });
        }

        eseguiConEventualeRiapertura(zona, spostaEvento);
    });
});

/**
 * Ricalcola il testo della pillola dei task nell'intestazione dell'evento a
 * partire dalle righe presenti nel DOM (nessuna richiesta al server).
 */
function aggiornaPillolaTask(lista) {
    var dettaglio = lista.closest('details.evento');
    var pillola   = dettaglio.querySelector('.badge-task');
    var righe     = lista.querySelectorAll('.task-riga');
    var nonFatti  = 0;

    righe.forEach(function (riga) {
        if (!riga.querySelector('input[type="checkbox"]').checked) {
            nonFatti++;
        }
    });

    if (righe.length === 0) {
        if (pillola) {
            pillola.remove();
        }
        return;
    }

    if (!pillola) {
        pillola = document.createElement('span');
        pillola.className = 'badge-task';
        var azioni = dettaglio.querySelector('.azioni-icone');
        azioni.insertBefore(pillola, azioni.firstChild);
    }

    pillola.textContent = righe.length + ' task · ' + (nonFatti > 0 ? nonFatti + ' da fare' : 'tutti fatti');
}

function aggiungiTask(rigaForm) {
    var input = rigaForm.querySelector('.task-nuovo-input');
    var testo = input.value.trim();
    if (testo === '') {
        return;
    }

    var lista     = rigaForm.closest('.task-lista');
    var idEvento  = lista.getAttribute('data-evento-task');

    fetch('task_aggiungi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'fk_evento=' + encodeURIComponent(idEvento) + '&testo=' + encodeURIComponent(testo)
    })
        .then(function (r) { return r.json(); })
        .then(function (risposta) {
            if (!risposta.ok) {
                alert('Errore: ' + risposta.error);
                return;
            }

            var riga = document.createElement('div');
            riga.className = 'task-riga';
            riga.setAttribute('data-task-id', risposta.id_task);
            riga.innerHTML =
                '<label class="task-check">' +
                    '<input type="checkbox">' +
                    '<span class="task-testo"></span>' +
                '</label>' +
                '<span class="task-azioni">' +
                    '<button type="button" class="task-modifica" title="Modifica testo">✏️</button>' +
                    '<button type="button" class="task-elimina" title="Elimina task">×</button>' +
                '</span>';
            riga.querySelector('.task-testo').textContent = risposta.testo;

            lista.insertBefore(riga, rigaForm);
            input.value = '';
            input.focus();
            aggiornaPillolaTask(lista);
        })
        .catch(function () {
            alert('Errore di comunicazione con il server.');
        });
}

/**
 * Sostituisce lo <span> del testo del task con un <input> per modificarlo in linea;
 * salva su blur/Enter (Escape annulla), via AJAX su task_modifica.php.
 */
function abilitaModificaTask(bottone) {
    var riga = bottone.closest('.task-riga');
    var span = riga.querySelector('.task-testo');

    if (riga.querySelector('.task-modifica-input')) {
        return;
    }

    var testoAttuale = span.textContent;
    var input = document.createElement('textarea');
    input.className = 'task-modifica-input';
    input.rows = 3;
    input.value = testoAttuale;

    // Durante la modifica nasconde checkbox e icone: la riga (task-lista è solo
    // 1/4 della larghezza della card) è troppo stretta perché il testo si legga
    // se deve condividere lo spazio anche con quelli.
    riga.classList.add('task-riga-editing');

    span.replaceWith(input);
    input.focus();
    input.select();

    var chiuso = false;

    function chiudi(spanAggiornato) {
        if (chiuso) {
            return;
        }
        chiuso = true;
        riga.classList.remove('task-riga-editing');
        input.replaceWith(spanAggiornato || span);
    }

    function salva() {
        var nuovoTesto = input.value.trim();
        if (nuovoTesto === '' || nuovoTesto === testoAttuale) {
            chiudi();
            return;
        }

        fetch('task_modifica.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_task=' + encodeURIComponent(riga.getAttribute('data-task-id')) + '&testo=' + encodeURIComponent(nuovoTesto)
        })
            .then(function (r) { return r.json(); })
            .then(function (risposta) {
                if (!risposta.ok) {
                    alert('Errore: ' + risposta.error);
                } else {
                    span.textContent = risposta.testo;
                }
                chiudi();
            })
            .catch(function () {
                alert('Errore di comunicazione con il server.');
                chiudi();
            });
    }

    input.addEventListener('blur', salva);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            input.blur();
        } else if (e.key === 'Escape') {
            input.removeEventListener('blur', salva);
            chiudi();
        }
    });
}

document.addEventListener('click', function (e) {
    var bottoneModifica = e.target.closest('.task-modifica');
    if (bottoneModifica) {
        abilitaModificaTask(bottoneModifica);
        return;
    }

    var bottoneElimina = e.target.closest('.task-elimina');
    if (bottoneElimina) {
        var riga = bottoneElimina.closest('.task-riga');
        if (!confirm('Eliminare questo task?')) {
            return;
        }

        fetch('task_elimina.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_task=' + encodeURIComponent(riga.getAttribute('data-task-id'))
        })
            .then(function (r) { return r.json(); })
            .then(function (risposta) {
                if (!risposta.ok) {
                    alert('Errore: ' + risposta.error);
                    return;
                }
                var lista = riga.closest('.task-lista');
                riga.remove();
                aggiornaPillolaTask(lista);
            })
            .catch(function () {
                alert('Errore di comunicazione con il server.');
            });
        return;
    }

    var bottoneAggiungi = e.target.closest('.task-aggiungi-btn');
    if (bottoneAggiungi) {
        aggiungiTask(bottoneAggiungi.closest('.task-aggiungi-riga'));
    }
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.classList.contains('task-nuovo-input')) {
        e.preventDefault();
        aggiungiTask(e.target.closest('.task-aggiungi-riga'));
    }
});

document.addEventListener('change', function (e) {
    if (!e.target.matches('.task-riga input[type="checkbox"]')) {
        return;
    }

    var checkbox = e.target;
    var riga      = checkbox.closest('.task-riga');

    fetch('task_toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id_task=' + encodeURIComponent(riga.getAttribute('data-task-id'))
    })
        .then(function (r) { return r.json(); })
        .then(function (risposta) {
            if (!risposta.ok) {
                checkbox.checked = !checkbox.checked;
                alert('Errore: ' + risposta.error);
                return;
            }
            var testoEl = riga.querySelector('.task-testo');
            testoEl.classList.toggle('task-fatto', risposta.fatto);
            if (risposta.data_chiusura) {
                testoEl.title = 'Completato il ' + risposta.data_chiusura;
            } else {
                testoEl.removeAttribute('title');
            }
            aggiornaPillolaTask(riga.closest('.task-lista'));
        })
        .catch(function () {
            checkbox.checked = !checkbox.checked;
            alert('Errore di comunicazione con il server.');
        });
});

/**
 * Evidenzia ogni occorrenza di $query dentro $radice avvolgendola in
 * <mark class="evidenziazione-temporanea">, senza toccare script/style né
 * i <mark> già creati. Restituisce l'elenco dei <mark> creati.
 */
function evidenziaTesto(radice, query) {
    var queryLower = query.toLowerCase();
    var marcati = [];

    var walker = document.createTreeWalker(radice, NodeFilter.SHOW_TEXT, {
        acceptNode: function (nodo) {
            if (nodo.parentNode.closest('script, style, mark')) {
                return NodeFilter.FILTER_REJECT;
            }
            return nodo.textContent.toLowerCase().indexOf(queryLower) !== -1
                ? NodeFilter.FILTER_ACCEPT
                : NodeFilter.FILTER_SKIP;
        }
    });

    var nodi = [];
    var nodo;
    while ((nodo = walker.nextNode())) {
        nodi.push(nodo);
    }

    nodi.forEach(function (testoNodo) {
        var testo = testoNodo.textContent;
        var testoLower = testo.toLowerCase();
        var frammento = document.createDocumentFragment();
        var cursore = 0;
        var pos;

        while ((pos = testoLower.indexOf(queryLower, cursore)) !== -1) {
            if (pos > cursore) {
                frammento.appendChild(document.createTextNode(testo.slice(cursore, pos)));
            }
            var mark = document.createElement('mark');
            mark.className = 'evidenziazione-temporanea';
            mark.textContent = testo.slice(pos, pos + query.length);
            frammento.appendChild(mark);
            marcati.push(mark);
            cursore = pos + query.length;
        }
        frammento.appendChild(document.createTextNode(testo.slice(cursore)));
        testoNodo.parentNode.replaceChild(frammento, testoNodo);
    });

    return marcati;
}

/**
 * Arrivando da un link della pagina di ricerca (#evento-123&q=parola), apre
 * il relativo <details> anche nei browser che non lo fanno da soli seguendo
 * l'ancora, ci scorre sopra, evidenzia la parola cercata e dopo 5 secondi
 * toglie l'evidenziazione (dissolvenza, poi rimozione dei <mark>).
 *
 * Il salvataggio partecipanti (via modale) ricarica anch'esso sull'ancora
 * dell'evento/step, ma solo per scorrerci sopra: aggiunge "restaChiuso=1"
 * apposta per NON farlo aprire se era chiuso.
 */
(function () {
    if (!location.hash) {
        return;
    }
    var bersaglio = document.getElementById(location.hash.slice(1));
    if (!bersaglio) {
        return;
    }
    var restaChiuso = new URLSearchParams(location.search).get('restaChiuso') === '1';
    if (bersaglio.tagName === 'DETAILS' && !restaChiuso) {
        bersaglio.open = true;
    }
    bersaglio.scrollIntoView({ block: 'center' });

    // Step appena spostato su/giù con le frecce: flash di 3 secondi, poi dissolvenza.
    if (new URLSearchParams(location.search).get('evidenzia') === '1' && bersaglio.classList.contains('step-card')) {
        bersaglio.classList.add('step-evidenziato');
        setTimeout(function () {
            bersaglio.classList.add('step-evidenziato-svanito');
            setTimeout(function () {
                bersaglio.classList.remove('step-evidenziato', 'step-evidenziato-svanito');
            }, 1000);
        }, 3000);
    }

    var query = new URLSearchParams(location.search).get('q');
    if (!query) {
        return;
    }

    var marcati = evidenziaTesto(bersaglio, query);
    if (!marcati.length) {
        return;
    }

    setTimeout(function () {
        marcati.forEach(function (m) { m.classList.add('evidenziazione-svanita'); });
        setTimeout(function () {
            marcati.forEach(function (m) {
                var parent = m.parentNode;
                if (!parent) {
                    return;
                }
                while (m.firstChild) {
                    parent.insertBefore(m.firstChild, m);
                }
                parent.removeChild(m);
                parent.normalize();
            });
        }, 1000);
    }, 5000);
})();
</script>
</body>
</html>
