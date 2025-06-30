<?php
/**
 * autoload.php
 * Chargement automatique des classes Finance
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

// Enregistrer l'autoloader
spl_autoload_register(function ($class_name) {
    $class_file = __DIR__ . '/classes/' . $class_name . '.php';
    
    if (file_exists($class_file)) {
        require_once $class_file;
        return true;
    }
    
    return false;
});

// Chargement des dépendances système
$dependencies = [
    __DIR__ . '/../components/config.php',
    __DIR__ . '/../database/connection.php'
];

foreach ($dependencies as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

// Fonction utilitaire pour charger une classe spécifique
function loadFinanceClass($className) {
    $classFile = __DIR__ . '/classes/' . $className . '.php';
    
    if (file_exists($classFile)) {
        require_once $classFile;
        return true;
    }
    
    throw new Exception("Classe $className introuvable dans " . $classFile);
}

// Initialisation des constantes si pas encore définies
if (!defined('FINANCE_VERSION')) {
    define('FINANCE_VERSION', '2.0');
}

if (!defined('FINANCE_DEBUG')) {
    define('FINANCE_DEBUG', defined('DEBUG_MODE') ? DEBUG_MODE : false);
}