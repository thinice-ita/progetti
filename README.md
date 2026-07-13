# Gestione Progetti (prototipo locale XAMPP + MySQL)

Applicazione locale per gestire progetti composti da **eventi** (note, riunioni, email,
registrazioni audio trascritte con AI), organizzabili in **step** opzionali. Un evento può
restare "libero" dentro il progetto oppure essere assegnato a uno step, anche via drag&drop.

Include al suo interno l'intera pipeline di trascrizione/sintesi AI (in precedenza un prototipo
a parte, `trascrizioni/`, ora archiviato — questa app ne è l'evoluzione unica): audio -> ffmpeg
(compressione/segmentazione) -> OpenAI `gpt-4o-transcribe` -> OpenAI `gpt-4o` (sintesi + titolo)
-> evento creato in automatico, con audio compresso e trascrizione completa come allegati.

## Modello dati

```
progetti  (id_progetto, titolo, descrizione, data_apertura, data_chiusura)
  └─ step  (id_step, fk_progetto, ordine, nome, descrizione, stato: da_fare/in_corso/completato,
            data_apertura, data_inizio, data_chiusura)
       ├─ step_storico_stato  (id_storico, fk_step, stato_precedente, stato_nuovo, data_cambio)
       └─ eventi  (id_evento, fk_progetto, fk_step NULL=evento libero, tipo: nota/riunione/email/registrazione,
                   data_evento, titolo, testo)
            ├─ eventi_allegati  (id_allegato, fk_evento, nome_file_originale, nome_file_salvato,
            │                    cartella_relativa NULL=vecchia struttura per id, ruolo: audio/trascrizione/generico)
            └─ eventi_task  (id_task, fk_evento, testo, fatto: 0/1, data_chiusura)
```

Regole di cancellazione (vincoli FK):
- Cancellare un **progetto** cancella a cascata step, eventi, allegati e task (righe DB; i file
  fisici in `allegati/` vanno ripuliti dal codice applicativo, già gestito in `progetto_delete.php`).
- Cancellare uno **step** NON cancella i suoi eventi: tornano liberi (`fk_step = NULL`), e i loro
  allegati "parlanti" vengono spostati fisicamente nella cartella `_generale` del progetto
  (gestito da `evento_sposta.php`, sia per il drag&drop che per l'assegnazione manuale).
- Cancellare un **evento** cancella a cascata i suoi allegati e i suoi task.

### Task (eventi_task)

Ogni evento (di qualunque tipo: nota, riunione, email, registrazione) può avere una lista di task
con flag "fatto" sì/no, gestita a mano dal dettaglio evento in `progetto_view.php` (pulsante
"+ Aggiungi", checkbox, "×" per eliminare — tutto via AJAX su `task_aggiungi.php` / `task_toggle.php`
/ `task_elimina.php`, senza ricaricare la pagina). Nell'intestazione compatta dell'evento compare
una pillola riassuntiva ("5 task · 3 da fare").

Per gli eventi di tipo **registrazione**, `registrazione_process.php` crea automaticamente i task
leggendo la sezione `## Task assegnati` generata dall'AI nella sintesi (vedi il prompt in
`includes/openai.php`), tramite `estraiTaskDaTesto()` in `includes/funzioni.php`.

Ogni task ha anche `data_chiusura`, valorizzata in automatico quando viene spuntato "fatto"
(azzerata se de-spuntato) — non è modificabile a mano. Il testo del task è invece modificabile
in linea dal dettaglio evento (icona ✏️ sulla riga del task).

### Date e stato dello step

Oltre a `stato` (da_fare/in_corso/completato), ogni step ha tre date, tutte modificabili a mano
dal form (`step_form.php`) ma valorizzate anche in automatico dall'applicazione:
- `data_apertura`: alla creazione dello step (di default oggi);
- `data_inizio`: al primo evento assegnato allo step (creato dentro, spostato dentro via
  drag&drop, o generato da una registrazione), solo se non già valorizzata;
- `data_chiusura`: quando lo stato passa a "completato"; viene azzerata in automatico se lo step
  viene riaperto, a meno che l'utente non la valorizzi esplicitamente nello stesso salvataggio.

Ogni cambio di stato viene loggato in `step_storico_stato` (stato precedente, stato nuovo, data),
perché uno step può essere chiuso e riaperto più volte e si vuole conservarne traccia completa,
non solo l'ultima data.

Uno step "completato" appare in grigio/disattivato nella vista progetto, ma resta comunque
possibile trascinarci sopra un evento o creare un nuovo evento/registrazione al suo interno: in
quel caso compare un avviso di conferma ("lo step tornerà In corso, alla data odierna") prima di
procedere, gestito da `step_riapri.php` (endpoint AJAX) lato client.

## Struttura del progetto

```
index.php                      Elenco progetti
progetto_form.php               Crea/modifica progetto
progetto_view.php               Vista progetto: eventi liberi + step colorati, drag&drop, task
progetto_delete.php             Cancella progetto (+ allegati su disco)
step_form.php / step_delete.php Crea/modifica/cancella step
step_riapri.php                 Endpoint AJAX: riapre uno step "completato" (usato da drag&drop
                                 e "+Nuovo" sulla vista progetto, dopo conferma dell'utente)
evento_form.php                 Crea/modifica evento (nota/riunione/email/registrazione), libero o in uno step
evento_delete.php               Cancella evento (+ allegati su disco)
evento_sposta.php               Endpoint AJAX: riassegna un evento a un altro step (drag&drop),
                                 sposta anche fisicamente gli allegati "parlanti" nella nuova cartella
registrazione_form.php          Form upload audio (libero o dentro uno step)
registrazione_process.php       Pipeline: ffmpeg -> trascrizione -> sintesi AI -> crea evento + allegati + task
task_aggiungi.php                Endpoint AJAX: crea un task su un evento
task_modifica.php                Endpoint AJAX: modifica il testo di un task
task_toggle.php                  Endpoint AJAX: spunta/de-spunta un task (fatto sì/no,
                                  valorizza/azzera anche data_chiusura)
task_elimina.php                 Endpoint AJAX: cancella un task
task_backfill_esistenti.php      Da eseguire una tantum dal browser: crea i task per gli eventi
                                 già salvati che hanno una sezione "Task assegnati" nel testo
includes/db.php                 Connessione PDO MySQL
includes/funzioni.php           Helper condivisi (escape HTML, date, colori step, nomi cartella
                                 "parlanti", risoluzione collisioni file, estrazione task dal testo AI)
includes/openai.php             Chiamate OpenAI (trascrizione, sintesi+titolo+task)
includes/ffmpeg.php             Compressione/segmentazione audio via ffmpeg
includes/stile.php              CSS condiviso
config.php                      Credenziali reali (NON versionato)
config.example.php              Modello di configurazione (versionato)
schema_progetti.sql             Schema completo per un'installazione da zero
migrazione_eventi_liberi.sql    Migrazione da schema precedente (fk_step obbligatorio) — non serve su un'installazione nuova
migrazione_cartella_allegati.sql Migrazione: aggiunge eventi_allegati.cartella_relativa (cartelle "parlanti")
migrazione_task_eventi.sql      Migrazione: aggiunge la tabella eventi_task
uploads/                        File audio temporanei durante l'elaborazione (ripuliti a fine pipeline)
allegati/{progetto}/{step|_generale}/   Allegati di registrazione/trascrizione, con nomi "parlanti"
                                 (es. 9001_man_strum/Tolleranza_interna/20260712_gestione_schede.mp3)
allegati/{id_evento}/           Allegati generici caricati a mano da evento_form.php (struttura precedente)
```

## Prima installazione su un'altra macchina

1. **Copia i file**: questa cartella non è un repository git — copia semplicemente tutto
   `C:\xampp\htdocs\progetti` sulla nuova macchina (esclusi `config.php`, `uploads/`, `allegati/`
   se non vuoi portare dati/segreti; vanno bene anche completi se è la stessa persona/uso).
2. **XAMPP**: installa XAMPP (Apache + MySQL/MariaDB + PHP) sulla nuova macchina, se non già presente.
3. **ffmpeg**: scarica ffmpeg per Windows da https://www.gyan.dev/ffmpeg/builds/ (build
   "essentials", zip), estrailo (es. in `C:\ffmpeg`, così da avere `C:\ffmpeg\bin\ffmpeg.exe`)
   e aggiungi `C:\ffmpeg\bin` al PATH di sistema, **oppure** imposta il percorso completo
   nella costante `FFMPEG_PATH` di `config.php`. **Il valore attuale è specifico di questo PC**
   (`C:\Users\francesconi.CONFAGRICOLTURA\...`) e quasi certamente non esisterà sull'altra macchina:
   se ffmpeg non è raggiungibile, l'app mostra un errore chiaro invece di fallire silenziosamente.
4. **Database**: crea in phpMyAdmin un database `progetti` (charset `utf8mb4`), poi importa
   `schema_progetti.sql` (schema già nella versione finale, comprese le date di step e lo storico
   stato: su un'installazione nuova **non** servono le `migrazione_*.sql`, che servono solo per
   aggiornare un database creato con uno schema precedente).
5. **config.php**: copia `config.example.php` in `config.php` e imposta:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` (di default XAMPP: `localhost` / `progetti` / `root` / vuota)
   - `OPENAI_API_KEY` (può essere la stessa chiave, oppure una nuova)
   - `FFMPEG_PATH` (vedi punto 3)
6. **Cartelle**: `uploads/` e `allegati/` vengono create automaticamente al primo utilizzo
   (permessi di scrittura richiesti per l'utente con cui gira Apache).
7. Avvia Apache+MySQL da XAMPP Control Panel e apri `http://localhost/progetti/`.

## Aggiornare un'installazione già esistente su un'altra macchina

Caso diverso dal precedente: sull'altra macchina l'app **è già installata e in uso**, con un suo
database e i suoi allegati già caricati (dati diversi da questo PC — non vanno toccati). Qui si
tratta solo di allineare il **codice** e lo **schema del database**, non i dati.

Non essendo un repository git, non c'è modo di fare un "pull" mirato: bisogna copiare i file di
codice a mano e poi applicare a mano le migrazioni SQL che nel frattempo si sono accumulate, **in
ordine cronologico** (ogni script presuppone lo stato lasciato dal precedente).

1. **Copia SOLO il codice**, sovrascrivendo quello esistente sull'altra macchina:
   tutti i file `.php`, `.sql`, `.md` e la cartella `includes/`. **Non copiare/sovrascrivere**:
   - `config.php` (credenziali e `FFMPEG_PATH` sono specifici di quella macchina)
   - `uploads/` (temporanea, irrilevante)
   - `allegati/` (i file audio/documenti già caricati su quella macchina — sovrascriverla con la
     cartella di questo PC mescolerebbe o cancellerebbe i loro allegati reali)

   In pratica: copia l'intera cartella ma togli/salta quei tre elementi (se usi uno zip, escludili
   prima di comprimere; se copi a mano coi Esplora File, deseleziona quelle tre voci).

2. **Esegui in phpMyAdmin, sul database dell'altra macchina**, gli script di migrazione nuovi
   rispetto a quando è stata fatta l'ultima copia — **non `schema_progetti.sql`** (quello è solo
   per un'installazione da zero, e ricreerebbe le tabelle perdendo i dati). Alla data di oggi, se
   l'altra macchina è ferma alla versione con "eventi liberi" già funzionante, gli script da
   lanciare in ordine sono:
   1. `migrazione_cartella_allegati.sql` (aggiunge `eventi_allegati.cartella_relativa`)
   2. `migrazione_task_eventi.sql` (crea la tabella `eventi_task`)
   3. `migrazione_task_data_chiusura.sql` (aggiunge `eventi_task.data_chiusura`)
   4. `migrazione_step_date.sql` (aggiunge `step.data_apertura`/`data_inizio`/`data_chiusura`)
   5. `migrazione_step_storico_stato.sql` (crea la tabella `step_storico_stato`)

3. **Backfill task sui record già esistenti su quella macchina**: apri una volta, dal browser
   di quella macchina, `http://localhost/progetti/task_backfill_esistenti.php`. È un'operazione
   diversa per ogni macchina perché legge gli eventi già salvati **in quel** database — va rifatta
   lì, non basta averla già eseguita qui.

Non serve chiedere a un'altra sessione di Claude Code di "indovinare" cosa fare confrontando i
file: la sequenza sopra è completa e autosufficiente. Se in futuro capita spesso di allineare le
due macchine, conviene mettere questa cartella sotto **git** (anche solo un repository locale) e
usare un servizio come GitHub/un drive condiviso per `git push`/`git pull` tra le due — elimina il
rischio di dimenticare un file o una migrazione.

## Utilizzo

1. Crea un progetto da `index.php`.
2. Dentro il progetto, aggiungi eventi liberi e/o step; sposta gli eventi tra "liberi" e
   step diversi trascinandoli (drag&drop) oppure riassegnandoli dal form di modifica evento.
3. Per una registrazione audio: "Registrazione libera" (a livello progetto) o "🎙️ Registrazione"
   dentro uno step. La pipeline compressione/trascrizione/sintesi può richiedere alcuni minuti
   per audio lunghi; il risultato crea un evento con titolo generato dall'AI, sintesi come testo,
   audio e trascrizione completa come allegati, più eventuali task rilevati automaticamente.
4. Su ogni evento (di qualunque tipo) puoi aggiungere/spuntare/eliminare task dal dettaglio
   dell'evento; il conteggio compare come pillola nell'intestazione compatta.

## Limiti di dimensione upload (php.ini di XAMPP)

Per audio di riunioni lunghe potrebbe essere necessario alzare i limiti di default di PHP.
Modifica `C:\xampp\php\php.ini` e riavvia Apache da XAMPP Control Panel:

```
upload_max_filesize = 30M
post_max_size = 30M
max_execution_time = 600
max_input_time = 600
```

Nota: l'API di trascrizione OpenAI accetta file fino a 25 MB; il file viene comunque
compresso con ffmpeg prima dell'invio, quindi audio anche più grandi in origine di solito
rientrano nel limite dopo la compressione.

## Note

- Nessun login/autenticazione: prototipo locale mono-utente.
- Ordinamento eventi (data evento / data di caricamento, crescente/decrescente) configurabile
  dalla vista progetto, di default **data evento decrescente** (più recenti prima).
- I colori tenui degli step sono assegnati automaticamente in modo ciclico in base all'ordine
  di creazione (vedi `classeColoreStep()` in `includes/funzioni.php`), non sono personalizzabili
  manualmente in questa versione.
- Gli allegati di registrazione/trascrizione vivono in cartelle "parlanti" dentro `allegati/`
  (nome progetto + nome step abbreviati); se un evento viene spostato tra step, i file vengono
  spostati fisicamente insieme a lui (`evento_sposta.php`). Gli allegati generici caricati a mano
  restano nella vecchia struttura `allegati/{id_evento}/`.
