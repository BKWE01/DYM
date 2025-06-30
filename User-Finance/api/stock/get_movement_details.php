<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Non autorisé'
    ]);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    $movementId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($movementId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de mouvement invalide'
        ]);
        exit();
    }
    
    // Requête pour récupérer les détails du mouvement
    $sql = "SELECT 
                sm.id,
                sm.product_id,
                p.product_name,
                sm.quantity,
                sm.movement_type,
                sm.provenance,
                sm.nom_projet,
                sm.destination,
                sm.demandeur,
                sm.notes,
                sm.date,
                sm.invoice_id,
                c.libelle as category_name,
                p.unit
            FROM stock_movement sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN categories c ON p.category = c.id
            WHERE sm.id = :movement_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':movement_id' => $movementId]);
    $movement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($movement) {
        echo json_encode([
            'success' => true,
            'movement' => $movement
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Mouvement non trouvé'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
    
    error_log("Erreur SQL dans get_movement_details.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur système: ' . $e->getMessage()
    ]);
    
    error_log("Erreur générale dans get_movement_details.php: " . $e->getMessage());
}
?>