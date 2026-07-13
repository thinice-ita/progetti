<?php
/**
 * Frammento di CSS condiviso tra le pagine, incluso direttamente (nessuna
 * dipendenza da framework esterni: prototipo semplice e autosufficiente).
 */
?>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        background: #f4f5f7;
        color: #222;
        margin: 0;
        padding: 2rem 1rem;
    }
    .contenitore {
        max-width: 960px;
        margin: 0 auto;
        background: #fff;
        border-radius: 8px;
        padding: 2rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    h1 { font-size: 1.5rem; margin-top: 0; }
    h2 { font-size: 1.15rem; margin-top: 1.75rem; color: #333; }
    label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
    input[type="text"], input[type="date"], input[type="datetime-local"],
    input[type="file"], textarea, select {
        display: block;
        width: 100%;
        padding: 0.6rem;
        border: 1px solid #ccc;
        border-radius: 6px;
        margin-bottom: 1rem;
        font-size: 0.95rem;
        font-family: inherit;
    }
    textarea { min-height: 100px; resize: vertical; }
    button, a.btn {
        background: #4f6ef2;
        color: #fff;
        border: 1px solid transparent;
        padding: 0.4rem 0.85rem;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        line-height: 1.3;
        transition: background .12s, border-color .12s;
    }
    button:hover, a.btn:hover { background: #3c58d6; }
    a.btn-secondary { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
    a.btn-secondary:hover { background: #e2e8f0; border-color: #cbd5e1; }
    a.btn-danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    a.btn-danger:hover { background: #fee2e2; border-color: #fca5a5; }
    .stato {
        background: #eef2ff;
        border-left: 4px solid #2563eb;
        padding: 0.8rem 1rem;
        margin: 1rem 0;
        border-radius: 4px;
    }
    .errore {
        background: #fef2f2;
        border-left: 4px solid #dc2626;
        color: #991b1b;
        padding: 0.8rem 1rem;
        margin: 1rem 0;
        border-radius: 4px;
    }
    .successo {
        background: #f0fdf4;
        border-left: 4px solid #16a34a;
        color: #166534;
        padding: 0.8rem 1rem;
        margin: 1rem 0;
        border-radius: 4px;
    }
    .box-testo {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 1rem;
        white-space: pre-wrap;
        word-wrap: break-word;
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: 1rem;
    }
    table.elenco { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    table.elenco th {
        background: #f5f5f5;
        padding: 0.5rem 0.7rem;
        text-align: left;
        border-bottom: 2px solid #ddd;
        font-size: 0.8rem;
    }
    table.elenco td { padding: 0.5rem 0.7rem; border-bottom: 1px solid #eee; vertical-align: middle; }
    table.elenco tr:hover { background: #fafafa; }
    .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; margin-left: 0.5rem; }
    .badge-da_fare { background: #f3f4f6; color: #4b5563; }
    .badge-in_corso { background: #fef3c7; color: #92400e; }
    .badge-completato { background: #d1fae5; color: #065f46; }
    .step-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.8rem; margin-bottom: 0.9rem; transition: outline .1s; }
    .step-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem; }
    .step-date { font-size: 0.78rem; color: #64748b; margin: -0.2rem 0 0.6rem; }

    /* Step "completato": aspetto disattivato/grigio, ma resta interattivo (drag&drop
       e "+Nuovo" chiedono conferma e riaprono lo step invece di essere bloccati). */
    .step-card.step-chiuso { background: #f1f5f9 !important; border-color: #e2e8f0 !important; opacity: 0.72; }
    .step-card.step-chiuso:hover { opacity: 0.95; }
    .step-card.step-chiuso .step-header strong { color: #64748b; }

    /* Tavolozza tenue per distinguere visivamente gli step tra loro (assegnata ciclicamente) */
    .step-colore-0 { background: #eef5ff; border-color: #bcd7f6; }
    .step-colore-1 { background: #eefbf3; border-color: #b9e6c9; }
    .step-colore-2 { background: #fff8ec; border-color: #f3dfa8; }
    .step-colore-3 { background: #f6eefc; border-color: #ddc4f0; }
    .step-colore-4 { background: #fdeef3; border-color: #f5c2d6; }
    .step-colore-5 { background: #eafbfa; border-color: #b3e5e0; }

    .sezione-liberi {
        border: 2px dashed #cbd5e1;
        border-radius: 8px;
        padding: 0.8rem;
        margin-bottom: 0.9rem;
        background: #fafbfc;
        transition: outline .1s;
    }
    .sezione-liberi-titolo { font-size: 0.85rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 0.4rem; }

    /* Feedback visivo durante il trascinamento di un evento su una zona valida */
    .drop-attivo { outline: 2px dashed #2563eb; outline-offset: -4px; }
    details.evento[draggable="true"] > .evento-summary { cursor: grab; }

    .ordina-form { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; font-size: 0.85rem; color: #555; margin: 0.8rem 0 1.2rem; }
    .ordina-form select { display: inline-block; width: auto; margin: 0 0 0 0.4rem; padding: 0.3rem 0.5rem; font-size: 0.85rem; }

    details.evento {
        border-left: 3px solid #d1d5db;
        margin-bottom: 0.25rem;
        background: #fafafa;
        border-radius: 0 6px 6px 0;
    }
    details.evento[open] { background: #fff; }
    details.evento-tipo-registrazione { border-left-color: #2563eb; }
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
        color: #888;
        font-size: 0.7rem;
        display: inline-block;
        flex-shrink: 0;
        transition: transform .15s;
    }
    details.evento[open] .evento-riga::before { transform: rotate(90deg); }
    .evento-data-compatta { color: #666; white-space: nowrap; flex-shrink: 0; }
    .evento-titolo-compatto {
        font-weight: 600;
        color: #222;
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
        border-left: 1px solid #eee;
        padding-left: 0.9rem;
    }
    @media (max-width: 640px) {
        .evento-corpo { flex-direction: column; }
        .evento-corpo .task-lista { border-left: none; padding-left: 0; border-top: 1px solid #eee; padding-top: 0.5rem; width: 100%; }
    }
    .allegati { margin-top: 0.4rem; font-size: 0.85rem; }
    .allegati a { margin-right: 1rem; }
    .allegati audio { max-width: 100%; margin-top: 0.3rem; }

    .dropzone {
        border: 2px dashed #cbd5e1;
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
        background: #fafbfc;
        margin-bottom: 0.6rem;
        transition: background .1s, border-color .1s;
    }
    .dropzone.dropzone-attivo { border-color: #2563eb; background: #eef2ff; }
    .dropzone-testo { margin: 0 0 0.6rem; font-size: 0.85rem; color: #64748b; }
    .dropzone input[type="file"] { margin-bottom: 0; }
    .lista-file-selezionati { list-style: none; padding: 0; margin: 0 0 1rem; font-size: 0.82rem; }
    .lista-file-selezionati li { padding: 0.15rem 0; color: #444; }
    .lista-file-selezionati li.file-vuoto { color: #dc2626; }

    .lista-allegati-esistenti { list-style: none; padding: 0; margin: 0 0 1rem; }
    .lista-allegati-esistenti li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.3rem 0;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.85rem;
    }
    .allegato-elimina {
        flex-shrink: 0;
        border: none;
        background: transparent;
        color: #999;
        font-size: 1rem;
        line-height: 1;
        cursor: pointer;
        padding: 0.1rem 0.4rem;
        border-radius: 4px;
    }
    .allegato-elimina:hover { background: #fef2f2; color: #dc2626; }

    .badge-allegati {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.5rem;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 600;
        background: #f1f5f9;
        color: #475569;
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
        border-radius: 5px;
        opacity: 0.7;
        flex-shrink: 0;
    }
    .icona-azione:hover { opacity: 1; background: rgba(0,0,0,0.06); }

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
        color: #94a3b8;
    }
    .azioni-icone .zona-stampe { padding-left: 0.35rem; margin-left: 0.15rem; border-left: 1px solid #e2e8f0; }
    .btn-stampa {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        background: #f8fafc;
        color: #475569;
        border: 1px dashed #cbd5e1;
        padding: 0.3rem 0.65rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
    }
    .btn-stampa:hover { background: #f1f5f9; border-color: #94a3b8; }

    /* Riga separata per le azioni che aggiungono contenuto a uno step (+ Evento,
       Registrazione), distinta dalle icone di gestione dello step nell'header. */
    .step-azioni-contenuto { display: flex; gap: 0.4rem; flex-wrap: wrap; margin: 0.4rem 0 0.8rem; }

    .badge-task {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.5rem;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 600;
        background: #eef2ff;
        color: #3730a3;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .task-lista { margin-bottom: 0.5rem; }
    .task-lista-titolo {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #64748b;
        margin: 0 0 0.5rem;
    }
    .task-riga {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.2rem 0;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.85rem;
    }
    .task-check { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; flex: 1 1 auto; min-width: 0; }
    .task-check input[type="checkbox"] { flex-shrink: 0; width: 1rem; height: 1rem; cursor: pointer; }
    .task-testo { word-break: break-word; }
    .task-fatto { text-decoration: line-through; color: #999; }
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
        border: 1px solid #93c5fd;
        border-radius: 4px;
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
        color: #999;
        font-size: 0.85rem;
        line-height: 1;
        cursor: pointer;
        padding: 0.1rem 0.4rem;
        border-radius: 4px;
    }
    .task-elimina { font-size: 1rem; }
    .task-modifica:hover { background: #eef2ff; color: #3730a3; }
    .task-elimina:hover { background: #fef2f2; color: #dc2626; }
    .task-aggiungi-riga { display: flex; gap: 0.4rem; margin-top: 0.35rem; }
    .task-nuovo-input {
        flex: 1 1 auto;
        padding: 0.3rem 0.5rem;
        border: 1px solid #ddd;
        border-radius: 5px;
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
        color: #3730a3;
        border: 1px solid #c7d2fe;
        border-radius: 4px;
        padding: 0;
        font-size: 1rem;
        line-height: 1;
        cursor: pointer;
    }
    .task-aggiungi-btn:hover { background: #eef2ff; }

    a.link-indietro { display: inline-block; margin-top: 1.5rem; color: #2563eb; text-decoration: none; }
    a.link-indietro:hover { text-decoration: underline; }

    .ricerca-globale { display: flex; align-items: center; gap: 0.6rem; margin: 1rem 0 0.4rem; }
    .ricerca-globale input[type="search"] { margin-bottom: 0; max-width: 420px; }
    .ricerca-conteggio { font-size: 0.8rem; color: #64748b; white-space: nowrap; }

    .nota-campo { font-size: 0.8rem; color: #64748b; margin: -0.6rem 0 1rem; }

    .campi-riga { display: flex; gap: 1rem; }
    .campi-riga > div { flex: 1 1 0; min-width: 0; }
    .campi-riga label { font-size: 0.85rem; }
</style>
