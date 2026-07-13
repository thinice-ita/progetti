-- Migrazione: task collegati a un evento (registrazione/trascrizione, riunione, email, nota...),
-- con flag "fatto" sì/no. Gestiti a mano dal dettaglio evento (aggiungi/spunta/elimina, via AJAX)
-- e, per le registrazioni, creati automaticamente dalla sezione "## Task assegnati" generata dall'AI.

CREATE TABLE eventi_task (
    id_task     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fk_evento   INT UNSIGNED NOT NULL,
    testo       VARCHAR(500) NOT NULL,
    fatto       TINYINT(1) NOT NULL DEFAULT 0,
    creato_il   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_task),
    KEY idx_task_evento (fk_evento),
    CONSTRAINT fk_task_evento FOREIGN KEY (fk_evento)
        REFERENCES eventi (id_evento) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
