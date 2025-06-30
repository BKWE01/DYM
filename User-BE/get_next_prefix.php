<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Assurez-vous que ce header est configuré correctement pour votre environnement

// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Obtenir le plus grand préfixe existant
    $stmt = $pdo->query("SELECT MAX(SUBSTRING_INDEX(idExpression, '-', 1)) AS max_prefix FROM expression_dym");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextPrefix = '00001'; // Valeur par défaut
    if ($row && $row['max_prefix']) {
        $nextPrefix = str_pad((int)$row['max_prefix'] + 1, 5, '0', STR_PAD_LEFT);
    }

    echo json_encode(['success' => true, 'prefix' => $nextPrefix]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
