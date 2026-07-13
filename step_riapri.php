<?php
/**
 * Endpoint AJAX: riapre uno step "completato" portandolo a "in_corso" (data_chiusura
 * azzerata, transizione loggata in step_storico_stato). Usato dalla vista progetto
 * quando l'utente trascina un evento su uno step chiuso, oppure clicca "+ Evento" /
 * "Registrazione" su uno step chiuso, e conferma l'avviso mostrato in pagina.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/funzioni.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = dbConnect();

$idStep = isset($_POST['id_step']) ? (int) $_POST['id_step'] : 0;

$stmt = $pdo->prepare('SELECT stato FROM step WHERE id_step = ?');
$stmt->execute([$idStep]);
$step = $stmt->fetch();

if (!$step) {
    echo json_encode(['ok' => false, 'error' => 'Step non trovato.']);
    exit;
}

if ($step['stato'] !== 'completato') {
    // Non era chiuso: nessuna transizione da fare, ma non è un errore (idempotente).
    echo json_encode(['ok' => true, 'stato' => $step['stato'], 'gia_aperto' => true]);
    exit;
}

$nuovoStato   = 'in_corso';
$dataChiusura = applicaCambioStatoStep($pdo, $idStep, $step['stato'], $nuovoStato, null);

$pdo->prepare('UPDATE step SET stato = ?, data_chiusura = ? WHERE id_step = ?')
    ->execute([$nuovoStato, $dataChiusura, $idStep]);

echo json_encode(['ok' => true, 'stato' => $nuovoStato]);
