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

// Ordinamento eventi: per data dell'evento o per data di caricamento, crescente o decrescente.
// I valori sono validati contro una lista fissa, quindi possono essere interpolati nell'ORDER BY in sicurezza.
$ordinaPer = in_array($_GET['ordina_per'] ?? '', ['data_evento', 'creato_il'], true) ? $_GET['ordina_per'] : 'data_evento';
$direzione = ($_GET['direzione'] ?? '') === 'asc' ? 'ASC' : 'DESC';

$stmt = $pdo->prepare(
    "SELECT * FROM eventi WHERE fk_progetto = ? ORDER BY $ordinaPer $direzione, id_evento $direzione"
);
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

$etichetteStato = ['da_fare' => 'Da fare', 'in_corso' => 'In corso', 'completato' => 'Completato'];
$iconeTipo      = ['nota' => '📝', 'riunione' => '👥', 'email' => '✉️', 'registrazione' => '🎙️'];

/**
 * Testo della pillola dei task di un evento ("5 task · 3 da fare"), o stringa vuota se non ci sono task.
 */
function etichettaPillolaTask(array $task): string
{
    if (!$task) {
        return '';
    }

    $nonFatti = count(array_filter($task, static fn(array $t): bool => !$t['fatto']));

    return count($task) . ' task · ' . ($nonFatti > 0 ? $nonFatti . ' da fare' : 'tutti fatti');
}

/**
 * Stampa un evento come blocco <details> compatto ed espandibile, trascinabile
 * verso un'altra zona (step o "eventi liberi").
 */
function stampaEvento(array $evento, array $allegatiPerEvento, array $taskPerEvento, array $iconeTipo): void
{
    $idEvento = (int) $evento['id_evento'];
    $allegati = $allegatiPerEvento[$idEvento] ?? [];
    $task     = $taskPerEvento[$idEvento] ?? [];
    ?>
    <details class="evento evento-tipo-<?= h($evento['tipo']) ?>" draggable="true" data-evento-id="<?= $idEvento ?>">
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
        <div class="ricerca-globale">
            <input type="search" id="ricerca-input" placeholder="🔍 Cerca in questo progetto (eventi, step, task)...">
            <span id="ricerca-conteggio" class="ricerca-conteggio"></span>
        </div>
    </div>

    <?php if ($progetto['descrizione']): ?>
        <div class="box-testo"><?= nl2br(h($progetto['descrizione'])) ?></div>
    <?php endif; ?>

    <p>
        <strong>Apertura:</strong> <?= formattaData($progetto['data_apertura']) ?>
        &middot; <strong>Chiusura:</strong> <?= $progetto['data_chiusura'] ? formattaData($progetto['data_chiusura']) : 'in corso' ?>
    </p>

    <div class="azioni">
        <a class="btn btn-secondary" href="progetto_form.php?id=<?= $idProgetto ?>">Modifica progetto</a>
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

    <?php if ($tuttiEventi): ?>
        <form method="get" class="ordina-form">
            <input type="hidden" name="id" value="<?= $idProgetto ?>">
            <span>Ordina eventi per
                <select name="ordina_per" onchange="this.form.submit()">
                    <option value="data_evento" <?= $ordinaPer === 'data_evento' ? 'selected' : '' ?>>Data evento</option>
                    <option value="creato_il" <?= $ordinaPer === 'creato_il' ? 'selected' : '' ?>>Data caricamento</option>
                </select>
            </span>
            <select name="direzione" onchange="this.form.submit()">
                <option value="desc" <?= $direzione === 'DESC' ? 'selected' : '' ?>>Decrescente (più recenti prima)</option>
                <option value="asc" <?= $direzione === 'ASC' ? 'selected' : '' ?>>Crescente (più vecchi prima)</option>
            </select>
        </form>
    <?php endif; ?>

    <h2>Eventi liberi</h2>
    <div class="sezione-liberi" data-drop-step="">
        <div class="sezione-liberi-titolo">Non assegnati a nessuno step &mdash; trascina qui per rimuovere l'assegnazione</div>
        <?php if (!$eventiLiberi): ?>
            <p><em>Nessun evento libero.</em></p>
        <?php endif; ?>
        <?php foreach ($eventiLiberi as $evento): ?>
            <?php stampaEvento($evento, $allegatiPerEvento, $taskPerEvento, $iconeTipo); ?>
        <?php endforeach; ?>
    </div>

    <h2>Step</h2>

    <?php if (!$stepList): ?>
        <p>Nessuno step presente. Creane uno per organizzare gli eventi, oppure lasciali liberi.</p>
    <?php endif; ?>

    <?php foreach ($stepList as $indice => $step): $idStep = (int) $step['id_step']; $stepChiuso = $step['stato'] === 'completato'; ?>
        <div class="step-card <?= classeColoreStep($indice) ?><?= $stepChiuso ? ' step-chiuso' : '' ?>"
             data-drop-step="<?= $idStep ?>" data-step-stato="<?= h($step['stato']) ?>" data-step-nome="<?= h($step['nome']) ?>">
            <div class="step-header">
                <div>
                    <strong><?= h($step['nome']) ?></strong>
                    <span class="badge badge-<?= h($step['stato']) ?>"><?= h($etichetteStato[$step['stato']] ?? $step['stato']) ?></span>
                </div>
                <div class="azioni azioni-icone">
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

            <div class="step-azioni-contenuto">
                <a class="btn azione-riapertura" href="evento_form.php?fk_step=<?= $idStep ?>">+ Evento</a>
                <a class="btn azione-riapertura" href="registrazione_form.php?fk_step=<?= $idStep ?>">🎙️ Registrazione</a>
            </div>

            <?php if ($step['descrizione']): ?>
                <p><?= nl2br(h($step['descrizione'])) ?></p>
            <?php endif; ?>

            <?php $eventi = $eventiPerStep[$idStep] ?? []; ?>

            <?php if (!$eventi): ?>
                <p><em>Nessun evento in questo step. Trascina qui un evento libero.</em></p>
            <?php endif; ?>

            <?php foreach ($eventi as $evento): ?>
                <?php stampaEvento($evento, $allegatiPerEvento, $taskPerEvento, $iconeTipo); ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <a class="link-indietro" href="index.php">&larr; Elenco progetti</a>
</div>

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

(function () {
    var input = document.getElementById('ricerca-input');
    var conteggio = document.getElementById('ricerca-conteggio');
    if (!input) {
        return;
    }

    var eventi = Array.prototype.slice.call(document.querySelectorAll('details.evento'));
    var stepCards = Array.prototype.slice.call(document.querySelectorAll('.step-card'));
    var sezioneLiberi = document.querySelector('.sezione-liberi');

    function testoRicercabile(dettaglio) {
        if (!dettaglio.dataset.ricercaTesto) {
            dettaglio.dataset.ricercaTesto = dettaglio.textContent.toLowerCase();
        }
        return dettaglio.dataset.ricercaTesto;
    }

    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();

        if (q === '') {
            eventi.forEach(function (ev) { ev.style.display = ''; });
            stepCards.forEach(function (card) { card.style.display = ''; });
            if (sezioneLiberi) {
                sezioneLiberi.style.display = '';
            }
            conteggio.textContent = '';
            return;
        }

        var trovati = 0;

        eventi.forEach(function (ev) {
            var match = testoRicercabile(ev).indexOf(q) !== -1;
            ev.style.display = match ? '' : 'none';
            if (match) {
                ev.open = true;
                trovati++;
            }
        });

        stepCards.forEach(function (card) {
            var nomeMatch = (card.getAttribute('data-step-nome') || '').toLowerCase().indexOf(q) !== -1;

            if (nomeMatch) {
                card.querySelectorAll('details.evento').forEach(function (ev) { ev.style.display = ''; });
            }

            var haEventiVisibili = Array.prototype.some.call(
                card.querySelectorAll('details.evento'),
                function (ev) { return ev.style.display !== 'none'; }
            );

            card.style.display = (nomeMatch || haEventiVisibili) ? '' : 'none';
        });

        if (sezioneLiberi) {
            var haLiberiVisibili = Array.prototype.some.call(
                sezioneLiberi.querySelectorAll('details.evento'),
                function (ev) { return ev.style.display !== 'none'; }
            );
            sezioneLiberi.style.display = haLiberiVisibili ? '' : 'none';
        }

        conteggio.textContent = trovati + (trovati === 1 ? ' risultato' : ' risultati');
    });
})();
</script>
</body>
</html>
