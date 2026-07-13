-- Migrazione: consente eventi "liberi" dentro un progetto (senza step),
-- e permette di riassegnare/spostare un evento tra step diversi o verso "libero".
--
-- Cosa cambia:
--  - eventi.fk_progetto: nuova colonna, sempre valorizzata (l'evento appartiene sempre a un progetto)
--  - eventi.fk_step: diventa opzionale (NULL = evento libero, non assegnato a nessuno step)
--  - cancellare uno STEP non cancella più i suoi eventi: restano nel progetto, tornano "liberi"
--    (prima: ON DELETE CASCADE, ora: ON DELETE SET NULL)
--  - cancellare un PROGETTO continua a cancellare tutti i suoi eventi (invariato)

-- 1) Rimuove il vecchio vincolo (CASCADE) su fk_step, per poterlo ricreare come SET NULL
ALTER TABLE eventi
    DROP FOREIGN KEY fk_evento_step;

-- 2) Rende fk_step opzionale e aggiunge la nuova colonna fk_progetto (ancora nullable in questo passo)
ALTER TABLE eventi
    MODIFY COLUMN fk_step INT UNSIGNED NULL,
    ADD COLUMN fk_progetto INT UNSIGNED NULL AFTER id_evento;

-- 3) Valorizza fk_progetto per le righe esistenti, deducendolo dallo step a cui erano collegate
UPDATE eventi e
INNER JOIN step s ON s.id_step = e.fk_step
SET e.fk_progetto = s.fk_progetto;

-- 4) Rende fk_progetto obbligatorio e ricrea i vincoli:
--    - fk_progetto: cancellando il progetto, cancella anche i suoi eventi (CASCADE, come prima)
--    - fk_step: cancellando lo step, gli eventi restano ma tornano "liberi" (SET NULL)
ALTER TABLE eventi
    MODIFY COLUMN fk_progetto INT UNSIGNED NOT NULL,
    ADD KEY idx_evento_progetto (fk_progetto),
    ADD CONSTRAINT fk_evento_progetto FOREIGN KEY (fk_progetto)
        REFERENCES progetti (id_progetto) ON DELETE CASCADE,
    ADD CONSTRAINT fk_evento_step FOREIGN KEY (fk_step)
        REFERENCES step (id_step) ON DELETE SET NULL;
