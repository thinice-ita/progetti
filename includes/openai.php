<?php
/**
 * Funzioni per interagire con le API di OpenAI:
 * - trascrizione audio (endpoint /v1/audio/transcriptions, modello gpt-4o-transcribe)
 * - generazione sintesi testuale + titolo (endpoint /v1/chat/completions, modello gpt-4o)
 */

/**
 * Invia il file audio all'endpoint di trascrizione di OpenAI e restituisce il testo trascritto.
 *
 * @param string $percorsoFile Percorso assoluto del file audio da trascrivere
 * @param string $apiKey       Chiave API OpenAI
 * @return string              Testo trascritto
 * @throws Exception           In caso di errore di rete o risposta inattesa dall'API
 */
function trascriviAudio(string $percorsoFile, string $apiKey): string
{
    $url = 'https://api.openai.com/v1/audio/transcriptions';

    $fileAllegato = new CURLFile($percorsoFile);

    $campiForm = [
        'file'  => $fileAllegato,
        'model' => 'gpt-4o-transcribe',
        'response_format' => 'json',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $campiForm,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 600,
    ]);

    $risposta = curl_exec($ch);

    if ($risposta === false) {
        $erroreCurl = curl_error($ch);
        curl_close($ch);
        throw new Exception('Errore di connessione durante la trascrizione: ' . $erroreCurl);
    }

    $codiceHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dati = json_decode($risposta, true);

    if ($codiceHttp !== 200) {
        $messaggioErrore = $dati['error']['message'] ?? $risposta;
        throw new Exception("Errore API OpenAI durante la trascrizione (HTTP {$codiceHttp}): {$messaggioErrore}");
    }

    if (!isset($dati['text'])) {
        throw new Exception('Risposta inattesa dall\'API di trascrizione: ' . $risposta);
    }

    return $dati['text'];
}

/**
 * Invia la trascrizione a Chat Completions e restituisce la risposta completa del modello:
 * prima riga con il titolo ("Titolo: ..."), poi la sintesi Markdown strutturata.
 *
 * @param string $trascrizione Testo completo della trascrizione
 * @param string $apiKey       Chiave API OpenAI
 * @return string              Risposta completa generata dal modello
 * @throws Exception           In caso di errore di rete o risposta inattesa dall'API
 */
function generaSintesi(string $trascrizione, string $apiKey): string
{
    $url = 'https://api.openai.com/v1/chat/completions';

    $promptSistema =
        "Sei un assistente che analizza la trascrizione di una riunione aziendale in italiano. " .
        "A partire dal testo fornito dall'utente, produci una risposta in italiano composta da due parti.\n\n" .
        "PARTE 1 - TITOLO: sulla primissima riga scrivi un titolo sintetico della riunione, nel formato ESATTO " .
        "\"Titolo: <titolo>\" (senza virgolette). Il titolo deve essere breve (max 8-10 parole), descrivere " .
        "l'argomento principale trattato e, se individuabili dalla trascrizione, la zona geografica, il gruppo " .
        "di persone o il settore coinvolto. Esempi: \"Titolo: Riunione soci zona Treviso\", " .
        "\"Titolo: Incontro aziende cerealicole\". Non includere la data nel titolo.\n\n" .
        "PARTE 2 - SINTESI: dopo una riga vuota, prosegui con la sintesi vera e propria, dettagliata ma chiara, " .
        "strutturata ESATTAMENTE nelle tre sezioni seguenti, in formato Markdown:\n\n" .
        "## Punti principali\n" .
        "Elenco puntato breve (una riga per punto) dei principali argomenti trattati durante la riunione.\n\n" .
        "## Approfondimento\n" .
        "Per OGNI punto elencato sopra, ripeti il punto come sottotitolo (\"### Nome del punto\") seguito da " .
        "un paragrafo di circa 3-5 righe che ne descrive i dettagli, il contesto, le eventuali decisioni prese " .
        "e le posizioni espresse dai partecipanti, basandoti solo su quanto effettivamente detto nella trascrizione.\n\n" .
        "## Task assegnati\n" .
        "- elenco puntato nel formato \"Nome persona - descrizione del task\". " .
        "Se dal testo non è possibile individuare un responsabile, usa \"Da assegnare - descrizione del task\". " .
        "Se non ci sono task assegnati, scrivi \"- Nessun task assegnato\".\n\n" .
        "Non aggiungere sezioni diverse da queste tre e non inventare informazioni non presenti nella trascrizione.";

    $corpo = [
        'model'       => 'gpt-4o',
        'messages'    => [
            ['role' => 'system', 'content' => $promptSistema],
            ['role' => 'user', 'content' => $trascrizione],
        ],
        'temperature' => 0.3,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($corpo),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 300,
    ]);

    $risposta = curl_exec($ch);

    if ($risposta === false) {
        $erroreCurl = curl_error($ch);
        curl_close($ch);
        throw new Exception('Errore di connessione durante la generazione della sintesi: ' . $erroreCurl);
    }

    $codiceHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dati = json_decode($risposta, true);

    if ($codiceHttp !== 200) {
        $messaggioErrore = $dati['error']['message'] ?? $risposta;
        throw new Exception("Errore API OpenAI durante la generazione della sintesi (HTTP {$codiceHttp}): {$messaggioErrore}");
    }

    if (!isset($dati['choices'][0]['message']['content'])) {
        throw new Exception('Risposta inattesa dall\'API di chat completion: ' . $risposta);
    }

    return $dati['choices'][0]['message']['content'];
}

/**
 * Estrae il titolo generato dal modello (prima riga "Titolo: ...") dal resto della sintesi.
 *
 * @param string $rispostaModello Testo completo restituito da generaSintesi()
 * @return array{0: string, 1: string} Coppia [titolo, sintesi senza la riga del titolo]
 */
function estraiTitoloESintesi(string $rispostaModello): array
{
    if (preg_match('/^\s*Titolo:\s*(.+)$/mi', $rispostaModello, $corrispondenza)) {
        $titolo = trim($corrispondenza[1]);
        $sintesiSenzaTitolo = trim(preg_replace('/^\s*Titolo:\s*.+$/mi', '', $rispostaModello, 1));

        return [$titolo, $sintesiSenzaTitolo];
    }

    return ['Riunione', $rispostaModello];
}
