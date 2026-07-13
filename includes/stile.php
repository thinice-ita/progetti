<?php
/**
 * Frammento di CSS condiviso tra le pagine, incluso direttamente (nessuna
 * dipendenza da framework esterni: prototipo semplice e autosufficiente).
 *
 * Palette e token in stile "Dossier istituzionale": carta, titoli serif di
 * sistema, tratti sottili al posto delle ombre, verde scuro come accento.
 */
?>
<style>
    :root {
        --bg: #f4f2ea;
        --surface: #fffefb;
        --ink: #2b2a24;
        --ink-soft: #726e5f;
        --ink-faint: #948f7c;
        --rule: #ded8c6;
        --neutral-soft: #eeece2;
        --accent: #3f5a4d;
        --accent-hover: #32493f;
        --accent-soft: #e4e9e0;
        --accent-border: #a9bdb0;
        --danger: #9c4331;
        --danger-hover: #7c3526;
        --danger-soft: #f6e8e2;
        --amber: #8a5a10;
        --amber-soft: #f6ead0;
        --green: #2c6b45;
        --green-soft: #dcece0;
        --radius-s: 3px;
        --radius-m: 4px;
        --radius-l: 4px;
        --shadow-card: 0 1px 2px rgba(43,42,36,.07);
        --font-serif: "Palatino Linotype", Palatino, Georgia, "Iowan Old Style", serif;

        /* Tavolozza "etichette d'archivio" degli step: unica fonte, usata sia
           dalle step-card sia dai marker della linea del tempo. */
        --step0-bg: #eef1e5; --step0-rule: #c9d3b4;
        --step1-bg: #f7f0dc; --step1-rule: #e3cf9b;
        --step2-bg: #f7e9e2; --step2-rule: #e5bfa9;
        --step3-bg: #eaeef1; --step3-rule: #bfccd6;
        --step4-bg: #f1e9f0; --step4-rule: #d3bcd1;
        --step5-bg: #e6f0ee; --step5-rule: #aecdc6;
    }
    * { box-sizing: border-box; }
    body {
        font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        background: var(--bg);
        color: var(--ink);
        margin: 0;
        padding: 2rem 1rem;
    }
    .contenitore {
        max-width: 960px;
        margin: 0 auto;
        background: var(--surface);
        border: 1px solid var(--rule);
        border-radius: var(--radius-l);
        padding: 2rem;
        box-shadow: var(--shadow-card);
    }
    h1, h2, h3 { font-family: var(--font-serif); font-weight: 600; color: var(--ink); }
    h1 {
        font-size: 1.6rem;
        letter-spacing: -.005em;
        margin-top: 0;
        padding-bottom: 0.6rem;
        border-bottom: 1px solid var(--ink);
    }
    h2 { font-size: 1.15rem; margin-top: 1.75rem; }

    /* Testata di pagina: titolo del progetto e ricerca sulla stessa riga,
       ricerca allineata a destra. Il filetto "da letterhead" si sposta
       dall'h1 all'intera riga così la linea corre sotto entrambi. */
    .progetto-testata {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 0.75rem 1.5rem;
        padding-bottom: 0.6rem;
        margin-bottom: 1rem;
        border-bottom: 1px solid var(--ink);
    }
    .progetto-testata h1 { margin: 0; padding-bottom: 0; border-bottom: none; }
    .progetto-testata .ricerca-globale { margin: 0; flex-shrink: 0; }

    /* Stato di focus visibile per la navigazione da tastiera */
    a:focus-visible, button:focus-visible, input:focus-visible,
    textarea:focus-visible, select:focus-visible, summary:focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
    input[type="text"], input[type="date"], input[type="datetime-local"],
    input[type="file"], textarea, select {
        display: block;
        width: 100%;
        padding: 0.6rem;
        border: 1px solid var(--rule);
        border-radius: var(--radius-s);
        margin-bottom: 1rem;
        font-size: 0.95rem;
        font-family: inherit;
        background: var(--surface);
        color: var(--ink);
        transition: border-color .12s, box-shadow .12s;
    }
    input[type="text"]:focus, input[type="date"]:focus, input[type="datetime-local"]:focus,
    input[type="file"]:focus, textarea:focus, select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-soft);
    }
    textarea { min-height: 100px; resize: vertical; }
    button, a.btn {
        background: var(--accent);
        color: #fdfcf8;
        border: 1px solid var(--accent);
        padding: 0.4rem 0.85rem;
        border-radius: var(--radius-s);
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        line-height: 1.3;
        transition: background .12s, border-color .12s, transform .08s;
    }
    button:hover, a.btn:hover { background: var(--accent-hover); border-color: var(--accent-hover); }
    button:active, a.btn:active { transform: translateY(1px); }
    a.btn-secondary { background: transparent; color: var(--accent); border-color: var(--accent); }
    a.btn-secondary:hover { background: var(--accent-soft); border-color: var(--accent); }
    a.btn-danger { background: transparent; color: var(--danger); border-color: var(--danger); }
    a.btn-danger:hover { background: var(--danger-soft); border-color: var(--danger); }
    .stato {
        background: var(--accent-soft);
        border-left: 4px solid var(--accent);
        color: var(--ink);
        padding: 0.8rem 1rem;
        margin: 1rem 0;
        border-radius: 0 var(--radius-s) var(--radius-s) 0;
    }
    .errore {
        background: var(--danger-soft);
        border-left: 4px solid var(--danger);
        color: var(--danger-hover);
        padding: 0.8rem 1rem;
        margin: 1rem 0;
        border-radius: 0 var(--radius-s) var(--radius-s) 0;
    }
    .successo {
        background: var(--green-soft);
        border-left: 4px solid var(--green);
        color: #1f4d34;
        padding: 0.8rem 1rem;
        margin: 1rem 0;
        border-radius: 0 var(--radius-s) var(--radius-s) 0;
    }
    .box-testo {
        background: var(--surface);
        border: 1px solid var(--rule);
        border-radius: var(--radius-s);
        padding: 1rem;
        white-space: pre-wrap;
        word-wrap: break-word;
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: 1rem;
    }
    table.elenco { width: 100%; border-collapse: collapse; margin-top: 1rem; font-variant-numeric: tabular-nums; }
    table.elenco th {
        background: transparent;
        padding: 0.5rem 0.7rem;
        text-align: left;
        border-bottom: 1px solid var(--ink);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        font-weight: 700;
        color: var(--ink-soft);
    }
    table.elenco td { padding: 0.55rem 0.7rem; border-bottom: 1px solid var(--rule); vertical-align: middle; }
    table.elenco tbody tr:last-child td { border-bottom: 1px solid var(--ink); }
    table.elenco tr:hover { background: var(--accent-soft); }
    .badge {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        border-radius: var(--radius-s);
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-left: 0.5rem;
    }
    .badge-da_fare { background: var(--neutral-soft); color: var(--ink-soft); }
    .badge-in_corso { background: var(--amber-soft); color: var(--amber); }
    .badge-completato { background: var(--green-soft); color: var(--green); }
    .badge-da_fare::before, .badge-in_corso::before, .badge-completato::before {
        content: '';
        display: inline-block;
        width: 0.4rem;
        height: 0.4rem;
        border-radius: 50%;
        background: currentColor;
        margin-right: 0.35rem;
        vertical-align: middle;
    }
    .step-card {
        background: var(--surface);
        border: 1px solid var(--rule);
        border-left: 3px solid var(--accent);
        border-radius: 0 var(--radius-s) var(--radius-s) 0;
        padding: 0.85rem 1rem;
        margin-bottom: 0.9rem;
        transition: outline .1s;
    }
    .step-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem; }
    .step-date { font-size: 0.78rem; color: var(--ink-soft); margin: -0.2rem 0 0.6rem; font-variant-numeric: tabular-nums; }

    /* Step "completato": aspetto disattivato/grigio, ma resta interattivo (drag&drop
       e "+Nuovo" chiedono conferma e riaprono lo step invece di essere bloccati). */
    .step-card.step-chiuso { background: var(--neutral-soft) !important; border-color: var(--rule) !important; opacity: 0.72; }
    .step-card.step-chiuso:hover { opacity: 0.95; }
    .step-card.step-chiuso .step-header strong { color: var(--ink-soft); }

    /* Tavolozza tenue, ispirata alle etichette di cartelle di un archivio, per
       distinguere visivamente gli step tra loro (assegnata ciclicamente) */
    .step-colore-0 { background: var(--step0-bg); border-color: var(--step0-rule); }
    .step-colore-1 { background: var(--step1-bg); border-color: var(--step1-rule); }
    .step-colore-2 { background: var(--step2-bg); border-color: var(--step2-rule); }
    .step-colore-3 { background: var(--step3-bg); border-color: var(--step3-rule); }
    .step-colore-4 { background: var(--step4-bg); border-color: var(--step4-rule); }
    .step-colore-5 { background: var(--step5-bg); border-color: var(--step5-rule); }

    .sezione-liberi {
        border: 2px dashed var(--rule);
        border-radius: var(--radius-m);
        padding: 0.8rem;
        margin-bottom: 0.9rem;
        background: var(--bg);
        transition: outline .1s;
    }
    .sezione-liberi-titolo { font-size: 0.85rem; font-weight: 600; color: var(--ink-soft); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 0.4rem; }

    /* Feedback visivo durante il trascinamento di un evento su una zona valida */
    .drop-attivo { outline: 2px dashed var(--accent); outline-offset: -4px; }
    details.evento[draggable="true"] > .evento-summary { cursor: grab; }

    .ordina-form { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; font-size: 0.85rem; color: var(--ink-soft); margin: 0.8rem 0 1.2rem; }
    .ordina-form select { display: inline-block; width: auto; margin: 0 0 0 0.4rem; padding: 0.3rem 0.5rem; font-size: 0.85rem; }

    details.evento {
        border-left: 2px solid var(--rule);
        margin-bottom: 0.25rem;
        background: var(--bg);
        border-radius: 0 var(--radius-s) var(--radius-s) 0;
    }
    details.evento[open] { background: var(--surface); }
    details.evento-tipo-registrazione { border-left-color: var(--accent); }
    .evento-summary {
        list-style: none;
        cursor: pointer;
        padding: 0.3rem 0.55rem;
        display: flex;
        flex-wrap: nowrap;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
    }
    .evento-summary::-webkit-details-marker { display: none; }
    .evento-riga {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        overflow: hidden;
        min-width: 0;
        flex: 1 1 auto;
    }
    .evento-riga::before {
        content: '▸';
        color: var(--ink-faint);
        font-size: 0.7rem;
        display: inline-block;
        flex-shrink: 0;
        transition: transform .15s;
    }
    details.evento[open] .evento-riga::before { transform: rotate(90deg); }
    .evento-data-compatta { color: var(--ink-soft); white-space: nowrap; flex-shrink: 0; font-variant-numeric: tabular-nums; }
    .evento-titolo-compatto {
        font-weight: 600;
        color: var(--ink);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
        flex: 1 1 auto;
    }
    .evento-corpo {
        padding: 0 0.7rem 0.6rem 1.3rem;
        display: flex;
        gap: 1.2rem;
        align-items: flex-start;
    }
    .evento-corpo-principale { flex: 3 1 0; min-width: 0; }
    .evento-corpo .task-lista {
        flex: 1 1 0;
        min-width: 0;
        border-left: 1px solid var(--rule);
        padding-left: 0.9rem;
    }
    @media (max-width: 640px) {
        .evento-corpo { flex-direction: column; }
        .evento-corpo .task-lista { border-left: none; padding-left: 0; border-top: 1px solid var(--rule); padding-top: 0.5rem; width: 100%; }
    }
    .allegati { margin-top: 0.4rem; font-size: 0.85rem; }
    .allegati a { margin-right: 1rem; }
    .allegati audio { max-width: 100%; margin-top: 0.3rem; }

    .dropzone {
        border: 2px dashed var(--rule);
        border-radius: var(--radius-m);
        padding: 1rem;
        text-align: center;
        background: var(--bg);
        margin-bottom: 0.6rem;
        transition: background .1s, border-color .1s;
    }
    .dropzone.dropzone-attivo { border-color: var(--accent); background: var(--accent-soft); }
    .dropzone-testo { margin: 0 0 0.6rem; font-size: 0.85rem; color: var(--ink-soft); }
    .dropzone input[type="file"] { margin-bottom: 0; }
    .lista-file-selezionati { list-style: none; padding: 0; margin: 0 0 1rem; font-size: 0.82rem; }
    .lista-file-selezionati li { padding: 0.15rem 0; color: var(--ink-soft); }
    .lista-file-selezionati li.file-vuoto { color: var(--danger); }

    .lista-allegati-esistenti { list-style: none; padding: 0; margin: 0 0 1rem; }
    .lista-allegati-esistenti li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.3rem 0;
        border-bottom: 1px solid var(--rule);
        font-size: 0.85rem;
    }
    .allegato-elimina {
        flex-shrink: 0;
        border: none;
        background: transparent;
        color: var(--ink-faint);
        font-size: 1rem;
        line-height: 1;
        cursor: pointer;
        padding: 0.1rem 0.4rem;
        border-radius: var(--radius-s);
    }
    .allegato-elimina:hover { background: var(--danger-soft); color: var(--danger); }

    .badge-allegati {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.5rem;
        border-radius: var(--radius-s);
        font-size: 0.72rem;
        font-weight: 600;
        background: var(--neutral-soft);
        color: var(--ink-soft);
        white-space: nowrap;
        flex-shrink: 0;
    }
    .azioni { display: flex; gap: 0.3rem; align-items: center; flex-wrap: nowrap; flex-shrink: 0; margin-left: auto; }
    .azioni a { flex-shrink: 0; }
    .azioni-icone { gap: 0.2rem; }
    .icona-azione {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.6rem;
        height: 1.6rem;
        font-size: 0.9rem;
        line-height: 1;
        text-decoration: none;
        border-radius: var(--radius-s);
        opacity: 0.7;
        flex-shrink: 0;
    }
    .icona-azione:hover { opacity: 1; background: rgba(43,42,36,0.08); }

    /* Zona stampe: raggruppa le azioni di stampa separandole da modifica/elimina,
       cosi non si confondono con le azioni di gestione dello step/evento. Dentro
       una riga di icone esistente si separa con un divisore; da sola (a livello
       progetto) mostra anche un'etichetta. */
    .zona-stampe { display: inline-flex; align-items: center; gap: 0.35rem; }
    .zona-stampe-titolo {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--ink-faint);
    }
    .azioni-icone .zona-stampe { padding-left: 0.35rem; margin-left: 0.15rem; border-left: 1px solid var(--rule); }
    .btn-stampa {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        background: var(--surface);
        color: var(--ink-soft);
        border: 1px dashed var(--rule);
        padding: 0.3rem 0.65rem;
        border-radius: var(--radius-s);
        font-size: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
    }
    .btn-stampa:hover { background: var(--neutral-soft); border-color: var(--ink-faint); }

    /* L'area stampe a livello di progetto va tenuta chiaramente staccata dai
       pulsanti di gestione (Modifica/Nuovo step/Elimina), così non si legge
       come parte dello stesso gruppo di azioni. */
    .zona-stampe-progetto {
        display: flex;
        flex-wrap: wrap;
        margin-top: 1rem;
        padding-top: 1.1rem;
        border-top: 1px dashed var(--rule);
    }

    /* Riga separata per le azioni che aggiungono contenuto a uno step (+ Evento,
       Registrazione), distinta dalle icone di gestione dello step nell'header. */
    .step-azioni-contenuto { display: flex; gap: 0.4rem; flex-wrap: wrap; margin: 0.4rem 0 0.8rem; }

    .badge-task {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.5rem;
        border-radius: var(--radius-s);
        font-size: 0.72rem;
        font-weight: 600;
        background: var(--accent-soft);
        color: var(--accent);
        white-space: nowrap;
        flex-shrink: 0;
    }
    .task-lista { margin-bottom: 0.5rem; }
    .task-lista-titolo {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--ink-soft);
        margin: 0 0 0.5rem;
    }
    .task-riga {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.2rem 0;
        border-bottom: 1px solid var(--rule);
        font-size: 0.85rem;
    }
    .task-check { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; flex: 1 1 auto; min-width: 0; }
    .task-check input[type="checkbox"] { flex-shrink: 0; width: 1rem; height: 1rem; cursor: pointer; accent-color: var(--accent); }
    .task-testo { word-break: break-word; }
    .task-fatto { text-decoration: line-through; color: var(--ink-faint); }
    .task-azioni { display: flex; gap: 0.1rem; flex-shrink: 0; }

    /* Durante la modifica del testo, la riga nasconde checkbox e icone e lascia
       tutta la larghezza disponibile all'input (la colonna dei task è stretta:
       1/4 della card), altrimenti il testo resterebbe leggibile solo per poche
       lettere. */
    .task-riga-editing .task-check input[type="checkbox"] { display: none; }
    .task-riga-editing .task-azioni { display: none; }
    .task-modifica-input {
        flex: 1 1 auto;
        width: 100%;
        min-width: 0;
        min-height: 4.5rem;
        padding: 0.4rem 0.5rem;
        border: 1px solid var(--accent-border);
        border-radius: var(--radius-s);
        font-size: 0.85rem;
        font-family: inherit;
        line-height: 1.4;
        resize: vertical;
        white-space: pre-wrap;
        word-break: break-word;
        margin-bottom: 0;
    }
    .task-modifica, .task-elimina {
        flex-shrink: 0;
        border: none;
        background: transparent;
        color: var(--ink-faint);
        font-size: 0.85rem;
        line-height: 1;
        cursor: pointer;
        padding: 0.1rem 0.4rem;
        border-radius: var(--radius-s);
    }
    .task-elimina { font-size: 1rem; }
    .task-modifica:hover { background: var(--accent-soft); color: var(--accent); }
    .task-elimina:hover { background: var(--danger-soft); color: var(--danger); }
    .task-aggiungi-riga { display: flex; gap: 0.4rem; margin-top: 0.35rem; }
    .task-nuovo-input {
        flex: 1 1 auto;
        padding: 0.3rem 0.5rem;
        border: 1px solid var(--rule);
        border-radius: var(--radius-s);
        font-size: 0.82rem;
        margin-bottom: 0;
    }
    .task-aggiungi-btn {
        flex-shrink: 0;
        width: 1.6rem;
        height: 1.6rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        color: var(--accent);
        border: 1px solid var(--accent-border);
        border-radius: var(--radius-s);
        padding: 0;
        font-size: 1rem;
        line-height: 1;
        cursor: pointer;
    }
    .task-aggiungi-btn:hover { background: var(--accent-soft); }

    a.link-indietro { display: inline-block; margin-top: 1.5rem; color: var(--accent); text-decoration: none; }
    a.link-indietro:hover { text-decoration: underline; }

    .ricerca-globale { display: flex; align-items: center; gap: 0.6rem; margin: 1rem 0 0.4rem; }
    .ricerca-globale input[type="search"] { margin-bottom: 0; max-width: 420px; }
    .ricerca-conteggio { font-size: 0.8rem; color: var(--ink-soft); white-space: nowrap; }

    /* Pagina dei risultati di ricerca (ricerca.php) */
    .ricerca-esito { color: var(--ink-soft); font-size: 0.9rem; margin: 0 0 1.4rem; }
    .ricerca-lista { display: flex; flex-direction: column; }
    .ricerca-risultato {
        display: block;
        text-decoration: none;
        color: inherit;
        padding: 0.8rem 0.3rem;
        border-bottom: 1px solid var(--rule);
        border-radius: var(--radius-s);
        transition: background .1s;
    }
    .ricerca-risultato:first-child { border-top: 1px solid var(--rule); }
    .ricerca-risultato:hover { background: var(--accent-soft); }
    .ricerca-risultato-dove {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--accent);
        margin-bottom: 0.3rem;
    }
    .ricerca-risultato-contesto { font-size: 0.9rem; line-height: 1.55; color: var(--ink); }
    .ricerca-risultato-contesto mark {
        background: var(--amber-soft);
        color: var(--ink);
        padding: 0 0.15rem;
        border-radius: 2px;
    }

    /* Evidenziazione temporanea nel punto d'arrivo dopo un click da ricerca.php:
       stessa resa del <mark> nei risultati, ma si dissolve dopo qualche secondo. */
    mark.evidenziazione-temporanea {
        background: var(--amber-soft);
        color: var(--ink);
        padding: 0 0.15rem;
        border-radius: 2px;
        transition: background-color 1s ease, color 1s ease;
    }
    mark.evidenziazione-temporanea.evidenziazione-svanita { background: transparent; color: inherit; }

    .nota-campo { font-size: 0.8rem; color: var(--ink-soft); margin: -0.6rem 0 1rem; }

    .campi-riga { display: flex; gap: 1rem; }
    .campi-riga > div { flex: 1 1 0; min-width: 0; }
    .campi-riga label { font-size: 0.85rem; }

    /* ===== Linea del tempo (linea-tempo.php) ===== */
    .lt-sottotitolo { font-size: 0.82rem; color: var(--ink-soft); margin: 0 0 1rem; }

    .lt-legenda { display: flex; flex-wrap: wrap; gap: 1.1rem; margin: 0 0 1.6rem; font-size: 0.78rem; color: var(--ink-soft); }
    .lt-legenda span { display: inline-flex; align-items: center; gap: 0.4rem; }
    .lt-legenda-tab { width: 1.1rem; height: 0.75rem; border-radius: 2px; background: var(--step0-bg); border: 1px solid var(--step0-rule); display: inline-block; }
    .lt-legenda-dot { width: 0.6rem; height: 0.6rem; border-radius: 50%; border: 1px dashed var(--ink-faint); display: inline-block; }
    .lt-legenda-linea { width: 1.1rem; height: 0; border-top: 1px dashed var(--ink-faint); display: inline-block; }

    .linea-tempo-scroll { overflow-x: auto; padding-bottom: 0.5rem; }
    .lt-pista { position: relative; height: 27rem; min-width: 920px; }

    .lt-asse { position: absolute; left: 0; right: 0; top: 50%; height: 2px; background: var(--ink); transform: translateY(-1px); }

    .lt-oggi { position: absolute; top: 1.5rem; bottom: 2.6rem; border-left: 1px dashed var(--ink-faint); transform: translateX(-50%); }
    .lt-oggi-etichetta {
        position: absolute; top: -1.3rem; left: 50%; transform: translateX(-50%);
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
        color: var(--ink-faint); white-space: nowrap;
    }

    .lt-marker { position: absolute; z-index: 1; }
    .lt-marker.lt-aperta { z-index: 40; }

    /* --lt-livello: assegnata via JS quando un marker si accavalla con un altro
       nella stessa corsia (es. due step aperti lo stesso giorno) — lo allontana
       dalla linea invece di sovrapporlo, con lo stelo che si allunga di pari passo. */
    .lt-sopra { bottom: 50%; margin-bottom: calc(0.4rem + var(--lt-livello, 0) * 2.4rem); transform: translateX(-50%); }
    .lt-sotto { top: 50%; margin-top: calc(0.4rem + var(--lt-livello, 0) * 2.4rem); transform: translateX(-50%); }
    .lt-sopra::after, .lt-sotto::after { content: ''; position: absolute; left: 50%; width: 1px; background: var(--rule); }
    .lt-sopra::after { bottom: calc(-1 * (0.4rem + var(--lt-livello, 0) * 2.4rem)); height: calc(0.4rem + var(--lt-livello, 0) * 2.4rem); }
    .lt-sotto::after { top: calc(-1 * (0.4rem + var(--lt-livello, 0) * 2.4rem)); height: calc(0.4rem + var(--lt-livello, 0) * 2.4rem); }

    .lt-marker-toggle { border: none; cursor: pointer; font: inherit; display: block; }

    .lt-tab {
        padding: 0.3rem 0.6rem;
        border: 1px solid var(--rule);
        border-radius: var(--radius-s);
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--ink);
        white-space: nowrap;
        max-width: 9.5rem;
        overflow: hidden;
        text-overflow: ellipsis;
        box-shadow: var(--shadow-card);
    }
    .lt-tab-colore-0 { background: var(--step0-bg); border-color: var(--step0-rule); }
    .lt-tab-colore-1 { background: var(--step1-bg); border-color: var(--step1-rule); }
    .lt-tab-colore-2 { background: var(--step2-bg); border-color: var(--step2-rule); }
    .lt-tab-colore-3 { background: var(--step3-bg); border-color: var(--step3-rule); }
    .lt-tab-colore-4 { background: var(--step4-bg); border-color: var(--step4-rule); }
    .lt-tab-colore-5 { background: var(--step5-bg); border-color: var(--step5-rule); }

    .lt-dot {
        width: 1.15rem; height: 1.15rem;
        border-radius: 50%;
        border: 1px dashed var(--ink-faint);
        background: var(--surface);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.62rem; font-weight: 700; color: var(--ink-soft);
    }

    .lt-callout {
        display: none;
        position: absolute; left: 50%; transform: translateX(-50%);
        width: 15.5rem; max-height: 9.5rem; overflow-y: auto;
        background: var(--surface); border: 1px solid var(--rule); border-radius: var(--radius-m);
        box-shadow: 0 6px 16px -6px rgba(43,42,36,.25);
        padding: 0.5rem;
        text-align: left;
    }
    .lt-aperta .lt-callout { display: block; }
    .lt-sopra .lt-callout { bottom: 100%; margin-bottom: 0.6rem; }
    .lt-sotto .lt-callout { top: 100%; margin-top: 0.6rem; }

    .lt-callout-titolo {
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
        color: var(--ink-faint); margin: 0.1rem 0.3rem 0.4rem;
    }

    .lt-evento-riga { border-bottom: 1px solid var(--neutral-soft); }
    .lt-evento-riga:last-child { border-bottom: none; }
    .lt-evento-riga-toggle {
        display: flex; align-items: center; gap: 0.4rem;
        width: 100%; background: none; border: none; text-align: left; cursor: pointer;
        padding: 0.4rem 0.3rem; font: inherit; color: var(--ink); border-radius: 3px;
    }
    .lt-evento-riga-toggle:hover { background: var(--accent-soft); }
    .lt-evento-riga-titolo { flex: 1 1 auto; font-size: 0.82rem; font-weight: 600; }
    .lt-evento-riga-data { font-size: 0.7rem; color: var(--ink-soft); white-space: nowrap; font-variant-numeric: tabular-nums; }

    .lt-evento-dettaglio { display: none; padding: 0 0.5rem 0.6rem; font-size: 0.8rem; color: var(--ink-soft); line-height: 1.5; }
    .lt-evento-aperto .lt-evento-dettaglio { display: block; }
    .lt-evento-dettaglio .lt-badge-riga { display: flex; gap: 0.35rem; margin-top: 0.4rem; flex-wrap: wrap; align-items: center; }
    .lt-evento-dettaglio a { color: var(--accent); font-size: 0.78rem; }

    .lt-mesi { position: relative; height: 1.4rem; min-width: 920px; border-top: 1px solid var(--rule); margin-top: 0.4rem; }
    .lt-mesi span {
        position: absolute; top: 0.35rem; transform: translateX(-50%);
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
        color: var(--ink-faint); white-space: nowrap;
    }

    .lt-nota-densita, .lt-nota-vuota {
        margin-top: 1.4rem;
        padding-top: 1rem;
        border-top: 1px dashed var(--rule);
        font-size: 0.82rem;
        color: var(--ink-soft);
        line-height: 1.6;
    }
</style>
