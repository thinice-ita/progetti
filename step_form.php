<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

$pdo = dbConnect();

$idStep       = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$fkProgetto   = isset($_GET['fk_progetto']) ? (int) $_GET['fk_progetto'] : 0;
$nome         = '';
$descrizione  = '';
$stato        = 'da_fare';
$statoVecchio = null;
$dataApertura = date('Y-m-d');
$dataInizio   = '';
$dataChiusura = '';
$errore       = '';

if ($idStep > 0) {
    $stmt = $pdo->prepare('SELECT * FROM step WHERE id_step = ?');
    $stmt->execute([$idStep]);
    $riga = $stmt->fetch();

    if (!$riga) {
        $idStep = 0;
    } else {
        $fkProgetto   = (int) $riga['fk_progetto'];
        $nome         = $riga['nome'];
        $descrizione  = (string) $riga['descrizione'];
        $stato        = $riga['stato'];
        $statoVecchio = $riga['stato'];
        $dataApertura = (string) ($riga['data_apertura'] ?? date('Y-m-d', strtotime($riga['creato_il'])));
        $dataInizio   = (string) ($riga['data_inizio'] ?? '');
        $dataChiusura = (string) ($riga['data_chiusura'] ?? '');
    }
}

if ($fkProgetto <= 0) {
    http_response_code(400);
    echo 'Progetto non specificato.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome         = trim((string) ($_POST['nome'] ?? ''));
    $descrizione  = trim((string) ($_POST['descrizione'] ?? ''));
    $stato        = (string) ($_POST['stato'] ?? 'da_fare');
    $dataApertura = trim((string) ($_POST['data_apertura'] ?? ''));
    $dataInizio   = trim((string) ($_POST['data_inizio'] ?? ''));
    $dataChiusura = trim((string) ($_POST['data_chiusura'] ?? ''));

    if (!in_array($stato, ['da_fare', 'in_corso', 'completato'], true)) {
        $stato = 'da_fare';
    }

    if ($nome === '') {
        $errore = 'Il nome dello step è obbligatorio.';
    } else {
        $dataAperturaSql = $dataApertura !== '' ? $dataApertura : date('Y-m-d');
        $dataInizioSql   = $dataInizio !== '' ? $dataInizio : null;

        if ($idStep > 0) {
            // Il valore finale di data_chiusura tiene conto del cambio di stato (vedi
            // applicaCambioStatoStep): si azzera in automatico se lo step viene riaperto,
            // a meno che l'utente non l'abbia valorizzata a mano in questo stesso salvataggio.
            $dataChiusuraSql = applicaCambioStatoStep($pdo, $idStep, (string) $statoVecchio, $stato, $dataChiusura);

            $stmt = $pdo->prepare(
                'UPDATE step SET nome=?, descrizione=?, stato=?, data_apertura=?, data_inizio=?, data_chiusura=? WHERE id_step=?'
            );
            $stmt->execute([
                $nome, $descrizione !== '' ? $descrizione : null, $stato,
                $dataAperturaSql, $dataInizioSql, $dataChiusuraSql, $idStep,
            ]);
        } else {
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(ordine), 0) + 1 AS prossimo FROM step WHERE fk_progetto = ?');
            $stmt->execute([$fkProgetto]);
            $ordine = (int) $stmt->fetch()['prossimo'];

            $dataChiusuraSql = $stato === 'completato'
                ? ($dataChiusura !== '' ? $dataChiusura : date('Y-m-d'))
                : ($dataChiusura !== '' ? $dataChiusura : null);

            $stmt = $pdo->prepare(
                'INSERT INTO step (fk_progetto, ordine, nome, descrizione, stato, data_apertura, data_inizio, data_chiusura)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $fkProgetto, $ordine, $nome, $descrizione !== '' ? $descrizione : null, $stato,
                $dataAperturaSql, $dataInizioSql, $dataChiusuraSql,
            ]);
        }

        redirect('progetto_view.php?id=' . $fkProgetto);
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title><?= $idStep > 0 ? 'Modifica step' : 'Nuovo step' ?></title>
<?php include __DIR__ . '/includes/stile.php'; ?>
</head>
<body>
<div class="contenitore">
    <h1><?= $idStep > 0 ? 'Modifica step' : 'Nuovo step' ?></h1>

    <?php if ($errore !== ''): ?>
        <div class="errore"><?= h($errore) ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="nome">Nome step</label>
        <input type="text" id="nome" name="nome" value="<?= h($nome) ?>" required>

        <label for="descrizione">Descrizione</label>
        <textarea id="descrizione" name="descrizione"><?= h($descrizione) ?></textarea>

        <label for="stato">Stato</label>
        <select id="stato" name="stato">
            <option value="da_fare" <?= $stato === 'da_fare' ? 'selected' : '' ?>>Da fare</option>
            <option value="in_corso" <?= $stato === 'in_corso' ? 'selected' : '' ?>>In corso</option>
            <option value="completato" <?= $stato === 'completato' ? 'selected' : '' ?>>Completato</option>
        </select>

        <div class="campi-riga">
            <div>
                <label for="data_apertura">Data apertura</label>
                <input type="date" id="data_apertura" name="data_apertura" value="<?= h($dataApertura) ?>">
            </div>
            <div>
                <label for="data_inizio">Data inizio</label>
                <input type="date" id="data_inizio" name="data_inizio" value="<?= h($dataInizio) ?>">
            </div>
            <div>
                <label for="data_chiusura">Data chiusura</label>
                <input type="date" id="data_chiusura" name="data_chiusura" value="<?= h($dataChiusura) ?>">
            </div>
        </div>
        <p class="nota-campo">Data inizio: si valorizza da sola al primo evento assegnato allo step.
        Data chiusura: si valorizza/azzera da sola quando lo stato passa a/da "Completato".
        Tutte e tre restano comunque modificabili a mano.</p>

        <button type="submit">Salva</button>
        <a class="btn btn-secondary" href="progetto_view.php?id=<?= $fkProgetto ?>">Annulla</a>
    </form>
</div>
</body>
</html>
