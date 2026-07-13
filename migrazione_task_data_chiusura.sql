-- Migrazione: aggiunge eventi_task.data_chiusura, valorizzata automaticamente
-- dall'applicazione quando il task viene spuntato come "fatto" (e azzerata se
-- viene de-spuntato). Non è modificabile a mano dall'utente, solo dal flag "Fatto".

ALTER TABLE eventi_task
    ADD COLUMN data_chiusura DATETIME NULL AFTER fatto;
