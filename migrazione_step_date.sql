-- Migrazione: aggiunge le date di step (apertura/inizio/chiusura). Tutte e tre
-- sono modificabili a mano dal form dello step, ma vengono anche valorizzate
-- automaticamente dall'applicazione:
--  - data_apertura: alla creazione dello step (di default la data odierna);
--  - data_inizio:   in automatico al primo evento assegnato allo step (creato
--                    direttamente nello step, spostato dentro via drag&drop, o
--                    generato da una registrazione), solo se non già valorizzata;
--  - data_chiusura: in automatico quando lo stato passa a "completato"; viene
--                    azzerata in automatico se lo step viene riaperto (stato
--                    diverso da "completato"), a meno che l'utente non la
--                    valorizzi esplicitamente a mano nello stesso salvataggio.
--
-- Lo storico di ogni cambio di stato (utile perché uno step può essere chiuso
-- e riaperto più volte) è in una tabella a parte: vedi migrazione_step_storico_stato.sql.

ALTER TABLE step
    ADD COLUMN data_apertura DATE NULL AFTER stato,
    ADD COLUMN data_inizio   DATE NULL AFTER data_apertura,
    ADD COLUMN data_chiusura DATE NULL AFTER data_inizio;

-- Backfill per gli step già esistenti: data_apertura = data di creazione;
-- se lo step è già "completato" gli si assegna anche una data_chiusura di
-- partenza (uguale alla data di creazione, per non lasciarla vuota) — è solo
-- un valore di comodo iniziale, modificabile a mano da subito.
UPDATE step SET data_apertura = DATE(creato_il) WHERE data_apertura IS NULL;
UPDATE step SET data_chiusura = DATE(creato_il) WHERE stato = 'completato' AND data_chiusura IS NULL;
