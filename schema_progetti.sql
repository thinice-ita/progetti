-- Schema (per installazioni da zero): Progetto -> Step (liberi, opzionali) -> Evento -> Allegati
-- Un evento appartiene sempre a un progetto; può anche essere assegnato a uno step, oppure
-- restare "libero" (fk_step = NULL). MySQL / MariaDB (XAMPP)
--
-- Se il database esiste già con lo schema precedente (fk_step obbligatorio),
-- NON eseguire questo file: usa invece migrazione_eventi_liberi.sql.

CREATE TABLE progetti (
    id_progetto     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titolo          VARCHAR(255) NOT NULL,
    descrizione     TEXT NULL,
    data_apertura   DATE NOT NULL,
    data_chiusura   DATE NULL,
    creato_il       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_progetto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- data_apertura/data_inizio/data_chiusura: tutte modificabili a mano dal form dello
-- step, ma valorizzate anche in automatico dall'applicazione (creazione step,
-- primo evento assegnato, cambio stato a/da "completato" — vedi step_storico_stato
-- per lo storico di tutti i cambi di stato).
CREATE TABLE step (
    id_step         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fk_progetto     INT UNSIGNED NOT NULL,
    ordine          INT UNSIGNED NOT NULL DEFAULT 0,
    nome            VARCHAR(255) NOT NULL,
    descrizione     TEXT NULL,
    stato           ENUM('da_fare','in_corso','completato') NOT NULL DEFAULT 'da_fare',
    data_apertura   DATE NULL,
    data_inizio     DATE NULL,
    data_chiusura   DATE NULL,
    creato_il       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_step),
    KEY idx_step_progetto (fk_progetto),
    CONSTRAINT fk_step_progetto FOREIGN KEY (fk_progetto)
        REFERENCES progetti (id_progetto) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Storico dei cambi di stato dello step (uno step può essere chiuso e riaperto
-- più volte: si vuole conservare traccia di ogni transizione, non solo l'ultima).
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

-- fk_step è opzionale: NULL significa "evento libero", non assegnato a nessuno step.
-- Cancellando lo step, l'evento resta nel progetto e torna libero (SET NULL);
-- cancellando il progetto, l'evento viene cancellato (CASCADE).
CREATE TABLE eventi (
    id_evento       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fk_progetto     INT UNSIGNED NOT NULL,
    fk_step         INT UNSIGNED NULL,
    tipo            ENUM('nota','riunione','email','registrazione') NOT NULL DEFAULT 'nota',
    data_evento     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    titolo          VARCHAR(255) NOT NULL,
    testo           MEDIUMTEXT NULL,
    creato_il       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_evento),
    KEY idx_evento_progetto (fk_progetto),
    KEY idx_evento_step (fk_step),
    CONSTRAINT fk_evento_progetto FOREIGN KEY (fk_progetto)
        REFERENCES progetti (id_progetto) ON DELETE CASCADE,
    CONSTRAINT fk_evento_step FOREIGN KEY (fk_step)
        REFERENCES step (id_step) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- cartella_relativa: percorso a cartelle "parlanti" dentro allegati/ (es. "9001_man_strum/Tolleranza_interna"),
-- usato per registrazioni/trascrizioni; NULL per gli allegati generici, che restano in allegati/{id_evento}/.
CREATE TABLE eventi_allegati (
    id_allegato         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fk_evento           INT UNSIGNED NOT NULL,
    nome_file_originale VARCHAR(255) NOT NULL,
    nome_file_salvato   VARCHAR(255) NOT NULL,
    cartella_relativa   VARCHAR(500) NULL,
    ruolo               ENUM('audio','trascrizione','generico') NOT NULL DEFAULT 'generico',
    creato_il           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_allegato),
    KEY idx_allegato_evento (fk_evento),
    CONSTRAINT fk_allegato_evento FOREIGN KEY (fk_evento)
        REFERENCES eventi (id_evento) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task collegati a un evento, con flag "fatto" sì/no: gestiti a mano dal dettaglio evento
-- (aggiungi/spunta/elimina, via AJAX) e, per le registrazioni, creati automaticamente dalla
-- sezione "## Task assegnati" generata dall'AI.
-- data_chiusura: valorizzata in automatico quando il task viene spuntato "fatto"
-- (azzerata se de-spuntato), non modificabile a mano.
CREATE TABLE eventi_task (
    id_task     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fk_evento   INT UNSIGNED NOT NULL,
    testo       VARCHAR(500) NOT NULL,
    fatto       TINYINT(1) NOT NULL DEFAULT 0,
    data_chiusura DATETIME NULL,
    creato_il   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_task),
    KEY idx_task_evento (fk_evento),
    CONSTRAINT fk_task_evento FOREIGN KEY (fk_evento)
        REFERENCES eventi (id_evento) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Partecipanti (colleghi o esterni): anagrafica globale riutilizzabile tra più
-- progetti, associata a progetto/step/evento tramite tabelle ponte. cognome/nome
-- nullable singolarmente per consentire l'import rapido da testo incollato (basta
-- che almeno uno dei due sia valorizzato, controllo lato app).
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
