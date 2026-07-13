-- Migrazione: storico dei cambi di stato dello step (da_fare/in_corso/completato).
-- Serve perché uno step può essere chiuso e riaperto più volte (manualmente dal
-- form, oppure in automatico trascinandoci sopra un evento o creando un nuovo
-- evento/registrazione mentre è chiuso) e si vuole conservare traccia di quando
-- è successo, non solo l'ultimo stato/data.

CREATE TABLE step_storico_stato (
    id_storico          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fk_step              INT UNSIGNED NOT NULL,
    stato_precedente     ENUM('da_fare','in_corso','completato') NULL,
    stato_nuovo          ENUM('da_fare','in_corso','completato') NOT NULL,
    data_cambio          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_storico),
    KEY idx_storico_step (fk_step),
    CONSTRAINT fk_storico_step FOREIGN KEY (fk_step)
        REFERENCES step (id_step) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
