<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php'; 

try {

    // Récupérer l'ID du mouvement
    $movementId = isset($_GET['movement_id']) ? intval($_GET['movement_id']) : 0;

    if ($movementId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de mouvement invalide'
        ]);
        exit;
    }

    // Récupérer les détails du dispatching pour ce mouvement
    $stmt = $pdo->prepare("
        SELECT * FROM dispatch_details 
        WHERE movement_id = :movement_id
    ");
    $stmt->execute([':movement_id' => $movementId]);
    $dispatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'dispatches' => $dispatches
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'dispatches' => [] // Assurez-vous que cette ligne existe
    ]);
}