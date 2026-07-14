<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();

$idEvento   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$fkProgetto = 0;
$fkStep     = null;
$tipo       = 'nota';
$dataEvento = date('Y-m-d\TH:i');
$titolo     = '';
$testo      = '';
$errore     = '';

if ($idEvento > 0) {
    $stmt = $pdo->prepare('SELECT * FROM eventi WHERE id_evento = ?');
    $stmt->execute([$idEvento]);
    $riga = $stmt->fetch();

    if (!$riga) {
        $idEvento = 0;
    } else {
        $fkProgetto = (int) $riga['fk_progetto'];
        $fkStep     = $riga['fk_step'] !== null ? (int) $riga['fk_step'] : null;
        $tipo       = $riga['tipo'];
        $dataEvento = date('Y-m-d\TH:i', strtotime($riga['data_evento']));
        $titolo     = $riga['titolo'];
        $testo      = (string) $riga['testo'];
    }
}

if ($idEvento === 0) {
    // Nuovo evento: creato dentro uno step (fk_step in querystring) oppure libero (fk_progetto in querystring)
    $fkStepGet = isset($_GET['fk_step']) && $_GET['fk_step'] !== '' ? (int) $_GET['fk_step'] : null;

    if ($fkStepGet !== null) {
        $stmt = $pdo->prepare('SELECT fk_progetto FROM step WHERE id_step = ?');
        $stmt->execute([$fkStepGet]);
        $stepRiga = $stmt->fetch();

        if (!$stepRiga) {
            http_response_code(404);
            echo 'Step non trovato.';
            exit;
        }

        $fkStep     = $fkStepGet;
        $fkProgetto = (int) $stepRiga['fk_progetto'];
    } else {
        $fkProgetto = isset($_GET['fk_progetto']) ? (int) $_GET['fk_progetto'] : 0;
    }
}

if ($fkProgetto <= 0) {
    http_response_code(400);
    echo 'Progetto non specificato.';
    exit;
}

$stmt = $pdo->prepare('SELECT titolo FROM progetti WHERE id_progetto = ?');
$stmt->execute([$fkProgetto]);
$progettoRiga = $stmt->fetch();

if (!$progettoRiga) {
    http_response_code(404);
    echo 'Progetto non trovato.';
    exit;
}

$stmt = $pdo->prepare('SELECT id_step, nome FROM step WHERE fk_progetto = ? ORDER BY ordine, id_step');
$stmt->execute([$fkProgetto]);
$stepDelProgetto = $stmt->fetchAll();

$allegatiEsistenti = [];
$idTemporaneo      = '';
$allegatiInAttesa  = [];

if ($idEvento > 0) {
    $stmt = $pdo->prepare('SELECT * FROM eventi_allegati WHERE fk_evento = ? ORDER BY id_allegato');
    $stmt->execute([$idEvento]);
    $allegatiEsistenti = $stmt->fetchAll();
} else {
    // Evento non ancora salvato: gli allegati caricati nel frattempo vanno in una cartella
    // "in attesa" identificata da un token temporaneo, e verranno spostati sotto il vero
    // id_evento al primo salvataggio riuscito (vedi più sotto). Se il salvataggio fallisce
    // (es. titolo mancante) e la pagina si ricarica, si riusa lo stesso token già inviato
    // nel form, così gli allegati già caricati restano collegati.
    $idTemporaneoInput = (string) ($_POST['id_temporaneo'] ?? '');
    $idTemporaneo      = preg_match('/^[a-f0-9]{32}$/', $idTemporaneoInput) ? $idTemporaneoInput : bin2hex(random_bytes(16));

    $manifestPath = __DIR__ . '/allegati/_temp/' . $idTemporaneo . '/manifest.json';
    if (is_file($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
        foreach ($manifest as $nomeSalvato => $nomeOriginale) {
            $allegatiInAttesa[] = ['nome_salvato' => $nomeSalvato, 'nome' => $nomeOriginale];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = (string) ($_POST['tipo'] ?? 'nota');
    if (!in_array($tipo, ['nota', 'riunione', 'email', 'registrazione'], true)) {
        $tipo = 'nota';
    }

    $dataEventoInput = (string) ($_POST['data_evento'] ?? '');
    $titolo          = trim((string) ($_POST['titolo'] ?? ''));
    $testo           = trim((string) ($_POST['testo'] ?? ''));
    $fkStepInput     = trim((string) ($_POST['fk_step'] ?? ''));
    $fkStepPost      = $fkStepInput === '' ? null : (int) $fkStepInput;

    if ($fkStepPost !== null && !in_array($fkStepPost, array_map('intval', array_column($stepDelProgetto, 'id_step')), true)) {
        $errore = 'Step non valido per questo progetto.';
    } elseif ($titolo === '' || $dataEventoInput === '') {
        $errore = 'Titolo e data sono obbligatori.';
    } else {
        $dataEventoSql = date('Y-m-d H:i:s', strtotime($dataEventoInput));
        $fkStep        = $fkStepPost;

        if ($idEvento > 0) {
            $stmt = $pdo->prepare('UPDATE eventi SET tipo=?, data_evento=?, titolo=?, testo=?, fk_step=? WHERE id_evento=?');
            $stmt->execute([$tipo, $dataEventoSql, $titolo, $testo !== '' ? $testo : null, $fkStep, $idEvento]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO eventi (fk_progetto, fk_step, tipo, data_evento, titolo, testo) VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$fkProgetto, $fkStep, $tipo, $dataEventoSql, $titolo, $testo !== '' ? $testo : null]);
            $idEvento = (int) $pdo->lastInsertId();

            // Adotta sotto il vero id_evento gli allegati caricati nel frattempo nella
            // cartella "in attesa" (se presenti), poi elimina la cartella temporanea.
            $cartellaTemp = __DIR__ . '/allegati/_temp/' . $idTemporaneo;
            $manifestPath = $cartellaTemp . '/manifest.json';

            if (is_file($manifestPath)) {
                $manifest       = json_decode((string) file_get_contents($manifestPath), true) ?: [];
                $cartellaEvento = __DIR__ . '/allegati/' . $idEvento;
                if (!is_dir($cartellaEvento)) {
                    mkdir($cartellaEvento, 0775, true);
                }

                foreach ($manifest as $nomeSalvato => $nomeOriginale) {
                    $percorsoOrigine = $cartellaTemp . '/' . $nomeSalvato;
                    if (is_file($percorsoOrigine) && rename($percorsoOrigine, $cartellaEvento . '/' . $nomeSalvato)) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO eventi_allegati (fk_evento, nome_file_originale, nome_file_salvato, ruolo) VALUES (?,?,?,?)'
                        );
                        $stmt->execute([$idEvento, $nomeOriginale, $nomeSalvato, 'generico']);
                    }
                }

                @unlink($manifestPath);
                @rmdir($cartellaTemp);
            }
        }

        impostaDataInizioStepSeVuota($pdo, $fkStep);

        redirect('progetto_view.php?id=' . $fkProgetto);
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title><?= $idEvento > 0 ? 'Modifica evento' : 'Nuovo evento' ?></title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1><?= $idEvento > 0 ? 'Modifica evento' : 'Nuovo evento' ?></h1>
    <p>Progetto: <strong><?= h($progettoRiga['titolo']) ?></strong></p>

    <?php if ($errore !== ''): ?>
        <div class="errore"><?= h($errore) ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="nota" <?= $tipo === 'nota' ? 'selected' : '' ?>>Nota</option>
            <option value="riunione" <?= $tipo === 'riunione' ? 'selected' : '' ?>>Riunione</option>
            <option value="email" <?= $tipo === 'email' ? 'selected' : '' ?>>Email</option>
            <option value="registrazione" <?= $tipo === 'registrazione' ? 'selected' : '' ?>>Registrazione</option>
        </select>

        <label for="fk_step">Step</label>
        <select id="fk_step" name="fk_step">
            <option value="">— Nessuno (evento libero) —</option>
            <?php foreach ($stepDelProgetto as $s): ?>
                <option value="<?= (int) $s['id_step'] ?>" <?= $fkStep === (int) $s['id_step'] ? 'selected' : '' ?>>
                    <?= h($s['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="data_evento">Data e ora</label>
        <input type="datetime-local" id="data_evento" name="data_evento" value="<?= h($dataEvento) ?>" required>

        <label for="titolo">Titolo</label>
        <input type="text" id="titolo" name="titolo" value="<?= h($titolo) ?>" required>

        <label for="testo">Testo / note</label>
        <textarea id="testo" name="testo" rows="7"><?= h($testo) ?></textarea>

        <?php if ($idEvento === 0): ?>
            <input type="hidden" name="id_temporaneo" value="<?= h($idTemporaneo) ?>">
        <?php endif; ?>

        <button type="submit">Salva</button>
        <a class="btn btn-secondary" href="progetto_view.php?id=<?= $fkProgetto ?>">Annulla</a>
    </form>

    <label>Allegati<?= $idEvento === 0 ? ' (in attesa del salvataggio)' : ' già caricati' ?></label>
    <ul class="lista-allegati-esistenti" id="lista-allegati-esistenti">
        <?php if ($idEvento > 0): ?>
            <?php foreach ($allegatiEsistenti as $a): ?>
                <?php $urlAllegatoEsistente = 'allegati/' . cartellaAllegato($a['cartella_relativa'], $idEvento) . '/' . rawurlencode($a['nome_file_salvato']); ?>
                <li data-id-allegato="<?= (int) $a['id_allegato'] ?>">
                    <a href="<?= h($urlAllegatoEsistente) ?>" target="_blank">📎 <?= h($a['nome_file_originale']) ?></a>
                    <button type="button" class="allegato-elimina" title="Elimina allegato">×</button>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($allegatiInAttesa as $a): ?>
                <li data-nome-salvato="<?= h($a['nome_salvato']) ?>">
                    📎 <?= h($a['nome']) ?>
                    <button type="button" class="allegato-elimina" title="Elimina allegato">×</button>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
    <?php $nessunAllegatoPresente = $idEvento > 0 ? !$allegatiEsistenti : !$allegatiInAttesa; ?>
    <p class="nota-campo" id="nessun-allegato" <?= $nessunAllegatoPresente ? '' : 'style="display:none"' ?>>Nessun allegato caricato.</p>

    <label for="allegato-input">Allega file (caricamento immediato, indipendente dal salvataggio sopra)</label>
    <div class="dropzone" id="dropzone">
        <p class="dropzone-testo">Trascina qui i file, oppure usa il pulsante sotto per selezionarli.</p>
        <input type="file" id="allegato-input" name="allegati[]" multiple>
    </div>
    <ul class="lista-file-selezionati" id="lista-file-selezionati"></ul>
</div>
<script>
(function () {
    var dropzone = document.getElementById('dropzone');
    if (!dropzone) {
        return;
    }
    var input          = document.getElementById('allegato-input');
    var lista          = document.getElementById('lista-file-selezionati');
    var listaEsistenti = document.getElementById('lista-allegati-esistenti');
    var nessunAllegato = document.getElementById('nessun-allegato');
    var idEvento       = <?= (int) $idEvento ?>;
    var idTemporaneo   = <?= json_encode($idTemporaneo) ?>;

    var campoDataEvento         = document.getElementById('data_evento');
    var dataEventoModificataAMano = false;
    if (campoDataEvento) {
        campoDataEvento.addEventListener('input', function () {
            dataEventoModificataAMano = true;
        });
    }

    var campoTipo         = document.getElementById('tipo');
    var tipoModificatoAMano = false;
    if (campoTipo) {
        campoTipo.addEventListener('change', function () {
            tipoModificatoAMano = true;
        });
    }

    function dueCifre(n) {
        return String(n).padStart(2, '0');
    }

    function formattaDataEventoLocale(data) {
        return data.getFullYear() + '-' + dueCifre(data.getMonth() + 1) + '-' + dueCifre(data.getDate()) +
            'T' + dueCifre(data.getHours()) + ':' + dueCifre(data.getMinutes());
    }

    /**
     * Ricava la data/ora da un file appena allegato, per proporla come data
     * dell'evento (solo su un evento nuovo, non ancora salvato, e solo se l'utente
     * non ha già toccato il campo a mano): per le email .eml (testo semplice) legge
     * l'header "Date:"; per tutti gli altri file, compresi i .msg (formato binario
     * di Outlook, non analizzabile lato client), usa la data di ultima modifica del
     * file. Resta comunque sempre modificabile a mano dopo la proposta.
     */
    function estraiDataEventoDaFile(file) {
        var estensione = file.name.split('.').pop().toLowerCase();

        if (estensione === 'eml' && typeof file.text === 'function') {
            return file.text().then(function (testo) {
                var corrispondenza = testo.match(/^Date:\s*(.+)$/im);
                if (corrispondenza) {
                    var timestamp = Date.parse(corrispondenza[1].trim());
                    if (!isNaN(timestamp)) {
                        return new Date(timestamp);
                    }
                }
                return new Date(file.lastModified);
            }).catch(function () {
                return new Date(file.lastModified);
            });
        }

        return Promise.resolve(new Date(file.lastModified));
    }

    function suggerisciDataEventoDaFile(fileList) {
        if (idEvento > 0 || dataEventoModificataAMano || !campoDataEvento || !fileList.length) {
            return;
        }
        estraiDataEventoDaFile(fileList[0]).then(function (data) {
            if (!dataEventoModificataAMano && !isNaN(data.getTime())) {
                campoDataEvento.value = formattaDataEventoLocale(data);
            }
        });
    }

    /**
     * Se il primo file allegato è un'email (.eml o .msg), imposta da solo il
     * "Tipo" evento su "email" — solo su un evento nuovo e solo se l'utente non
     * ha già scelto il tipo a mano. Resta comunque sempre modificabile.
     */
    function suggerisciTipoDaFile(fileList) {
        if (idEvento > 0 || tipoModificatoAMano || !campoTipo || !fileList.length) {
            return;
        }
        var estensione = fileList[0].name.split('.').pop().toLowerCase();
        if (estensione === 'eml' || estensione === 'msg') {
            campoTipo.value = 'email';
        }
    }

    function aggiungiRigaAllegato(a) {
        var li = document.createElement('li');
        if (idEvento > 0) {
            li.setAttribute('data-id-allegato', a.id_allegato);
            li.innerHTML = '<a href="" target="_blank"></a> <button type="button" class="allegato-elimina" title="Elimina allegato">×</button>';
            li.querySelector('a').href = a.url;
            li.querySelector('a').textContent = '📎 ' + a.nome;
        } else {
            li.setAttribute('data-nome-salvato', a.nome_salvato);
            li.textContent = '📎 ' + a.nome + ' ';
            var bottone = document.createElement('button');
            bottone.type = 'button';
            bottone.className = 'allegato-elimina';
            bottone.title = 'Elimina allegato';
            bottone.textContent = '×';
            li.appendChild(bottone);
        }
        listaEsistenti.appendChild(li);
        if (nessunAllegato) {
            nessunAllegato.style.display = 'none';
        }
    }

    /**
     * Carica subito i file selezionati/trascinati con una richiesta indipendente dal
     * salvataggio dell'evento (fetch separato, non parte del <form> principale): un file
     * "virtuale" trascinato direttamente da un client di posta (Thunderbird/Outlook) può
     * risultare 0 byte o non trasferirsi correttamente, ma senza intaccare titolo/data
     * dell'evento, che restano un salvataggio completamente separato. Se l'evento non è
     * ancora salvato, i file vanno in una cartella "in attesa" identificata da un token
     * temporaneo, e verranno adottati dal vero evento al primo salvataggio riuscito.
     */
    function caricaFile(fileList) {
        if (!fileList.length) {
            return;
        }

        var dati = new FormData();
        var url;
        if (idEvento > 0) {
            dati.append('fk_evento', idEvento);
            url = 'evento_allegato_carica.php';
        } else {
            dati.append('id_temporaneo', idTemporaneo);
            url = 'evento_allegato_carica_temp.php';
        }
        Array.prototype.forEach.call(fileList, function (file) {
            dati.append('allegati[]', file);
        });

        lista.innerHTML = '<li>Caricamento in corso...</li>';

        fetch(url, { method: 'POST', body: dati })
            .then(function (r) { return r.json(); })
            .then(function (risposta) {
                lista.innerHTML = '';
                if (!risposta.ok) {
                    alert('Errore: ' + risposta.error);
                    return;
                }
                risposta.allegati.forEach(aggiungiRigaAllegato);
                risposta.avvisi.forEach(function (avviso) {
                    var li = document.createElement('li');
                    li.className = 'file-vuoto';
                    li.textContent = '⚠️ ' + avviso;
                    lista.appendChild(li);
                });
            })
            .catch(function () {
                lista.innerHTML = '';
                alert('Errore di comunicazione con il server durante il caricamento.');
            })
            .finally(function () {
                input.value = '';
            });
    }

    /**
     * Un file trascinato direttamente da un client di posta (Thunderbird/Outlook) prende come
     * nome l'oggetto della mail, che può contenere virgolette o a-capo: nel corpo
     * multipart/form-data questi caratteri rompono l'header Content-Disposition di quella parte
     * e possono far scartare a PHP l'intera richiesta (compresi titolo/data), non solo il file.
     * Si ricostruisce quindi il file con un nome "ripulito" prima di metterlo nell'input.
     */
    function nomeFileSicuro(nome) {
        return nome.replace(/["\r\n]/g, '_');
    }

    function sanificaFileList(fileList) {
        var dt = new DataTransfer();
        Array.prototype.forEach.call(fileList, function (file) {
            var nomeSicuro = nomeFileSicuro(file.name);
            dt.items.add(nomeSicuro !== file.name
                ? new File([file], nomeSicuro, { type: file.type, lastModified: file.lastModified })
                : file);
        });
        return dt.files;
    }

    dropzone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropzone.classList.add('dropzone-attivo');
    });
    dropzone.addEventListener('dragleave', function () {
        dropzone.classList.remove('dropzone-attivo');
    });
    dropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropzone.classList.remove('dropzone-attivo');
        if (e.dataTransfer.files.length) {
            var fileList = sanificaFileList(e.dataTransfer.files);
            suggerisciDataEventoDaFile(fileList);
            suggerisciTipoDaFile(fileList);
            caricaFile(fileList);
        }
    });

    input.addEventListener('change', function () {
        var fileList = sanificaFileList(input.files);
        suggerisciDataEventoDaFile(fileList);
        suggerisciTipoDaFile(fileList);
        caricaFile(fileList);
    });

    // Rete di sicurezza: se un drop finisse fuori dalla dropzone, evita che il browser
    // navighi via mostrando il file trascinato (che farebbe perdere i dati già inseriti nel form).
    document.addEventListener('dragover', function (e) { e.preventDefault(); });
    document.addEventListener('drop', function (e) { e.preventDefault(); });

    document.addEventListener('click', function (e) {
        var bottone = e.target.closest('.allegato-elimina');
        if (!bottone) {
            return;
        }
        var riga = bottone.closest('li');
        if (!confirm('Eliminare questo allegato?')) {
            return;
        }

        var url, corpo;
        if (riga.hasAttribute('data-id-allegato')) {
            url   = 'allegato_elimina.php';
            corpo = 'id_allegato=' + encodeURIComponent(riga.getAttribute('data-id-allegato'));
        } else {
            url   = 'evento_allegato_elimina_temp.php';
            corpo = 'id_temporaneo=' + encodeURIComponent(idTemporaneo) +
                '&nome_salvato=' + encodeURIComponent(riga.getAttribute('data-nome-salvato'));
        }

        fetch(url, {
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
                riga.remove();
                if (!listaEsistenti.querySelector('li') && nessunAllegato) {
                    nessunAllegato.style.display = '';
                }
            })
            .catch(function () {
                alert('Errore di comunicazione con il server.');
            });
    });
})();
</script>
</body>
</html>
