<?php
// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Exécuter la procédure stockée
    $pdo->exec("CALL initialize_reserved_quantities()");
    
    echo "Initialisation des quantités réservées réussie.";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}