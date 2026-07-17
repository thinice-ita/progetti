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
function calcolaTargetSintesi(int $numeroParole): array
{
    if ($numeroParole < 600) {
        return ['puntiMin' => 1, 'puntiMax' => 2, 'righeMin' => 2, 'righeMax' => 3];
    }
    if ($numeroParole < 1200) {
        return ['puntiMin' => 2, 'puntiMax' => 4, 'righeMin' => 3, 'righeMax' => 4];
    }
    if ($numeroParole < 2000) {
        return ['puntiMin' => 4, 'puntiMax' => 6, 'righeMin' => 4, 'righeMax' => 5];
    }
    if ($numeroParole < 3500) {
        return ['puntiMin' => 6, 'puntiMax' => 9, 'righeMin' => 5, 'righeMax' => 6];
    }
    return ['puntiMin' => 9, 'puntiMax' => 14, 'righeMin' => 6, 'righeMax' => 8];
}

function generaSintesi(string $trascrizione, string $apiKey): string
{
    $url = 'https://api.openai.com/v1/chat/completions';

    $numeroParole = count(preg_split('/\s+/u', trim($trascrizione), -1, PREG_SPLIT_NO_EMPTY));
    $target       = calcolaTargetSintesi($numeroParole);

    $promptSistema =
        "Sei un assistente che analizza la trascrizione di una riunione aziendale in italiano. " .
        "A partire dal testo fornito dall'utente, produci una risposta in italiano composta da due parti.\n\n" .
        "PARTE 1 - TITOLO: sulla primissima riga scrivi un titolo sintetico della riunione, nel formato ESATTO " .
        "\"Titolo: <titolo>\" (senza virgolette). Il titolo deve essere breve (max 8-10 parole), descrivere " .
        "l'argomento principale trattato e, se individuabili dalla trascrizione, la zona geografica, il gruppo " .
        "di persone o il settore coinvolto. Esempi: \"Titolo: Riunione soci zona Treviso\", " .
        "\"Titolo: Incontro aziende cerealicole\". Non includere la data nel titolo.\n\n" .
        "PARTE 2 - SINTESI: dopo una riga vuota, prosegui con la sintesi vera e propria, dettagliata ma chiara, " .
        "strutturata ESATTAMENTE nelle tre sezioni seguenti, in formato Markdown.\n\n" .
        "La trascrizione fornita contiene circa {$numeroParole} parole. La lunghezza e il livello di dettaglio " .
        "della sintesi devono essere PROPORZIONALI alla quantità di contenuto, secondo queste regole:\n\n" .
        "## Punti principali\n" .
        "Elenco puntato (una riga per punto) dei principali argomenti trattati durante la riunione. Il numero " .
        "di punti deve essere tra {$target['puntiMin']} e {$target['puntiMax']}. Se la trascrizione tratta " .
        "davvero meno argomenti distinti di {$target['puntiMin']}, non inventarne di artificiosi: elencane " .
        "quanti ne trovi realmente. Non accorpare argomenti diversi in un unico punto solo per restare sotto " .
        "il minimo.\n\n" .
        "## Approfondimento\n" .
        "Per OGNI punto elencato sopra, ripeti il punto come sottotitolo (\"### Nome del punto\") seguito da " .
        "un paragrafo di circa {$target['righeMin']}-{$target['righeMax']} righe che ne descrive i dettagli, " .
        "il contesto, le eventuali decisioni prese e le posizioni espresse dai partecipanti, basandoti solo su " .
        "quanto effettivamente detto nella trascrizione.\n\n" .
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
