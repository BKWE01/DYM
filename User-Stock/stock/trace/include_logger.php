<?php
/**
 * Ce fichier initialise le Logger pour la traçabilité des actions
 * Il doit être inclus après la connexion à la base de données
 */

// Vérifier si la classe Logger existe déjà pour éviter les redéclarations
if (!class_exists('Logger')) {
    require_once dirname(__DIR__) . '/trace/logger.php';
}

// Vérifier si la connexion PDO est disponible
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // La connexion PDO n'est pas disponible, journaliser l'erreur
    $logDir = dirname(__DIR__) . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/system_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] Erreur: Logger non initialisé car la connexion PDO n'est pas disponible" . PHP_EOL;

    file_put_contents($logFile, $logMessage, FILE_APPEND);

    // Créer une fonction vide pour éviter les erreurs si le logger est appelé
    if (!function_exists('getLogger')) {
        function getLogger()
        {
            return null;
        }
    }
} else {
    // Créer une instance du Logger
    $logger = new Logger($pdo);

    // Fonction pour récupérer l'instance du Logger
    if (!function_exists('getLogger')) {
        function getLogger()
        {
            global $logger;
            return $logger;
        }
    }
}