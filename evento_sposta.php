<?php
/**
 * Endpoint AJAX: riassegna un evento a uno step diverso, oppure lo rende "libero"
 * (fk_step = NULL). Usato dal drag&drop nella vista progetto.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = dbConnect();

$idEvento    = isset($_POST['id_evento']) ? (int) $_POST['id_evento'] : 0;
$fkStepInput = trim((string) ($_POST['fk_step'] ?? ''));
$fkStep      = $fkStepInput === '' ? null : (int) $fkStepInput;

$stmt = $pdo->prepare(
    'SELECT e.fk_progetto, p.titolo AS titolo_progetto
     FROM eventi e
     INNER JOIN progetti p ON p.id_progetto = e.fk_progetto
     WHERE e.id_evento = ?'
);
$stmt->execute([$idEvento]);
$evento = $stmt->fetch();

if (!$evento) {
    echo json_encode(['ok' => false, 'error' => 'Evento non trovato.']);
    exit;
}

$nomeStep = null;

if ($fkStep !== null) {
    $stmt = $pdo->prepare('SELECT fk_progetto, nome FROM step WHERE id_step = ?');
    $stmt->execute([$fkStep]);
    $step = $stmt->fetch();

    if (!$step || (int) $step['fk_progetto'] !== (int) $evento['fk_progetto']) {
        echo json_encode(['ok' => false, 'error' => 'Step non valido per questo progetto.']);
        exit;
    }
    $nomeStep = $step['nome'];
}

// Se l'evento ha allegati con cartella "parlante" (registrazione/trascrizione), li sposta
// fisicamente nella cartella del nuovo step (o "_generale" se torna libero).
$stmt = $pdo->prepare(
    'SELECT id_allegato, nome_file_salvato, cartella_relativa FROM eventi_allegati
     WHERE fk_evento = ? AND cartella_relativa IS NOT NULL'
);
$stmt->execute([$idEvento]);
$allegatiParlanti = $stmt->fetchAll();

if ($allegatiParlanti) {
    $cartellaVecchia = $allegatiParlanti[0]['cartella_relativa'];
    $cartellaNuova   = abbreviaPerCartella($evento['titolo_progetto'])
        . '/' . ($nomeStep !== null ? abbreviaPerCartella($nomeStep) : '_generale');

    if ($cartellaNuova !== $cartellaVecchia) {
        $dirAssolutaNuova = __DIR__ . '/allegati/' . $cartellaNuova;
        if (!is_dir($dirAssolutaNuova) && !mkdir($dirAssolutaNuova, 0775, true) && !is_dir($dirAssolutaNuova)) {
            echo json_encode(['ok' => false, 'error' => 'Impossibile creare la cartella di destinazione.']);
            exit;
        }

        $estensioni = array_values(array_unique(array_map(
            static fn(array $a): string => pathinfo($a['nome_file_salvato'], PATHINFO_EXTENSION),
            $allegatiParlanti
        )));
        $baseAttuale = pathinfo($allegatiParlanti[0]['nome_file_salvato'], PATHINFO_FILENAME);
        $baseNuova   = trovaBaseFileUnivoca($dirAssolutaNuova, $baseAttuale, $estensioni);

        $spostamenti = [];
        foreach ($allegatiParlanti as $allegato) {
            $estensione       = pathinfo($allegato['nome_file_salvato'], PATHINFO_EXTENSION);
            $nomeNuovo        = $baseNuova . ($estensione !== '' ? '.' . $estensione : '');
            $percorsoVecchio  = __DIR__ . '/allegati/' . $cartellaVecchia . '/' . $allegato['nome_file_salvato'];
            $percorsoNuovo    = $dirAssolutaNuova . '/' . $nomeNuovo;

            if (is_file($percorsoVecchio) && !rename($percorsoVecchio, $percorsoNuovo)) {
                echo json_encode(['ok' => false, 'error' => 'Impossibile spostare i file dell\'evento.']);
                exit;
            }

            $spostamenti[] = ['id' => $allegato['id_allegato'], 'nome' => $nomeNuovo];
        }

        $stmtAgg = $pdo->prepare('UPDATE eventi_allegati SET nome_file_salvato = ?, cartella_relativa = ? WHERE id_allegato = ?');
        foreach ($spostamenti as $spostamento) {
            $stmtAgg->execute([$spostamento['nome'], $cartellaNuova, $spostamento['id']]);
        }

        @rmdir(__DIR__ . '/allegati/' . $cartellaVecchia);
    }
}

$stmt = $pdo->prepare('UPDATE eventi SET fk_step = ? WHERE id_evento = ?');
$stmt->execute([$fkStep, $idEvento]);

impostaDataInizioStepSeVuota($pdo, $fkStep);

echo json_encode(['ok' => true]);
