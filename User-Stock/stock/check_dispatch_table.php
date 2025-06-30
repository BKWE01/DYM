<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php'; 

try {

    // Vérifier si la table dispatch_details existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'dispatch_details'");
    $exists = $stmt->rowCount() > 0;

    echo json_encode([
        'success' => true,
        'exists' => $exists
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'exists' => false
    ]);
}