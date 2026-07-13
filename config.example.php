<?php
/**
 * ESEMPIO di file di configurazione.
 *
 * Copia questo file in "config.php" (stesso percorso) e inserisci i tuoi
 * valori reali. Il file "config.php" non deve mai essere versionato.
 */

// --- Connessione al database MySQL "progetti" (creato in phpMyAdmin) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'progetti');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- Chiave API OpenAI ---
define('OPENAI_API_KEY', 'sk-INSERISCI_QUI_LA_TUA_API_KEY');

// --- Percorso dell'eseguibile ffmpeg ---
define('FFMPEG_PATH', 'ffmpeg');
