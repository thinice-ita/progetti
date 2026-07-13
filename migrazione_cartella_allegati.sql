-- Migrazione: nomi di cartella "parlanti" per gli allegati di registrazione/trascrizione.
--
-- Cosa cambia:
--  - eventi_allegati.cartella_relativa: nuova colonna, percorso a cartelle dentro allegati/
--    (es. "9001_man_strum/Tolleranza_interna"), calcolato da titolo progetto + nome step al
--    momento del caricamento (o "_generale" per eventi liberi).
--  - Per le registrazioni/trascrizioni già esistenti la colonna resta NULL: continuano a
--    essere lette dal vecchio percorso allegati/{id_evento}/ (nessun file viene spostato da
--    questa migrazione). Solo le nuove registrazioni useranno la cartella parlante.
--  - Gli allegati generici (caricati a mano da un evento nota/riunione/email) restano invariati:
--    cartella_relativa resta sempre NULL per loro.

ALTER TABLE eventi_allegati
    ADD COLUMN cartella_relativa VARCHAR(500) NULL AFTER nome_file_salvato;
