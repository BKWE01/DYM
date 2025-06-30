<?php
/**
 * Script d'installation pour la fonctionnalité de logs système
 * Ce script crée la table system_logs si elle n'existe pas
 */

// Connexion à la base de données
include_once dirname(__DIR__) . '../../database/connection.php';

// Vérifier si la table system_logs existe déjà
try {
    $tableExists = false;
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_logs'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
    }

    if ($tableExists) {
        echo "La table 'system_logs' existe déjà.<br>";
    } else {
        // Créer la table system_logs
        $sql = "
        CREATE TABLE `system_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) DEFAULT NULL,
          `username` varchar(225) DEFAULT NULL,
          `action` varchar(255) NOT NULL,
          `type` varchar(50) NOT NULL,
          `entity_id` varchar(50) DEFAULT NULL,
          `entity_name` varchar(255) DEFAULT NULL,
          `details` text DEFAULT NULL,
          `ip_address` varchar(45) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `action` (`action`),
          KEY `type` (`type`),
          KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";

        $pdo->exec($sql);
        echo "Table 'system_logs' créée avec succès.<br>";
    }

    // Créer le dossier logs s'il n'existe pas
    $logsDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0777, true);
        echo "Dossier 'logs' créé avec succès.<br>";
    } else {
        echo "Le dossier 'logs' existe déjà.<br>";
    }

    // Créer le fichier .htaccess pour protéger le dossier logs
    $htaccessFile = $logsDir . '/.htaccess';
    if (!file_exists($htaccessFile)) {
        $htaccessContent = "# Empêcher l'accès direct au dossier logs\n";
        $htaccessContent .= "<IfModule mod_rewrite.c>\n";
        $htaccessContent .= "    RewriteEngine On\n";
        $htaccessContent .= "    RewriteRule ^(.*)$ - [F,L]\n";
        $htaccessContent .= "</IfModule>\n\n";
        $htaccessContent .= "# Empêcher l'affichage du contenu du répertoire\n";
        $htaccessContent .= "Options -Indexes\n\n";
        $htaccessContent .= "# Refuser l'accès à tous les fichiers\n";
        $htaccessContent .= "<FilesMatch \"^.*$\">\n";
        $htaccessContent .= "    Order Allow,Deny\n";
        $htaccessContent .= "    Deny from all\n";
        $htaccessContent .= "</FilesMatch>";

        file_put_contents($htaccessFile, $htaccessContent);
        echo "Fichier .htaccess créé pour protéger le dossier 'logs'.<br>";
    } else {
        echo "Le fichier .htaccess existe déjà dans le dossier 'logs'.<br>";
    }

    echo "<br>Installation terminée avec succès.";

} catch (PDOException $e) {
    die("Erreur lors de l'installation: " . $e->getMessage());
}