-- Migrazione: partecipanti al progetto (colleghi o esterni), anagrafica globale
-- riutilizzabile tra più progetti, associata a progetto/step/evento tramite tabelle
-- ponte. cognome/nome nullable singolarmente per consentire l'import rapido da testo
-- incollato (basta che almeno uno dei due sia valorizzato, controllo lato app).

CREATE TABLE partecipanti (
    id_partecipante INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cognome         VARCHAR(100) NULL,
    nome            VARCHAR(100) NULL,
    email           VARCHAR(255) NULL,
    cellulare       VARCHAR(50) NULL,
    creato_il       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_partecipante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE progetto_partecipanti (
    fk_progetto     INT UNSIGNED NOT NULL,
    fk_partecipante INT UNSIGNED NOT NULL,
    PRIMARY KEY (fk_progetto, fk_partecipante),
    KEY idx_pp_partecipante (fk_partecipante),
    CONSTRAINT fk_pp_progetto FOREIGN KEY (fk_progetto)
        REFERENCES progetti (id_progetto) ON DELETE CASCADE,
    CONSTRAINT fk_pp_partecipante FOREIGN KEY (fk_partecipante)
        REFERENCES partecipanti (id_partecipante) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE step_partecipanti (
    fk_step         INT UNSIGNED NOT NULL,
    fk_partecipante INT UNSIGNED NOT NULL,
    PRIMARY KEY (fk_step, fk_partecipante),
    KEY idx_sp_partecipante (fk_partecipante),
    CONSTRAINT fk_sp_step FOREIGN KEY (fk_step)
        REFERENCES step (id_step) ON DELETE CASCADE,
    CONSTRAINT fk_sp_partecipante FOREIGN KEY (fk_partecipante)
        REFERENCES partecipanti (id_partecipante) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE eventi_partecipanti (
    fk_evento       INT UNSIGNED NOT NULL,
    fk_partecipante INT UNSIGNED NOT NULL,
    PRIMARY KEY (fk_evento, fk_partecipante),
    KEY idx_ep_partecipante (fk_partecipante),
    CONSTRAINT fk_ep_evento FOREIGN KEY (fk_evento)
        REFERENCES eventi (id_evento) ON DELETE CASCADE,
    CONSTRAINT fk_ep_partecipante FOREIGN KEY (fk_partecipante)
        REFERENCES partecipanti (id_partecipante) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
