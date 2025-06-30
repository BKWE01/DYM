<?php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de retour non valide']);
    exit;
}

$returnId = intval($_GET['id']);

// Connexion à la base de données
include_once '../../../../../database/connection.php';

try {
    // Récupérer les détails du retour
    $stmt = $pdo->prepare("
        SELECT 
            r.*, 
            p.product_name, 
            p.barcode, 
            p.unit,
            u_created.name as created_by_name,
            u_approved.name as approved_by_name,
            u_rejected.name as rejected_by_name,
            u_completed.name as completed_by_name,
            u_canceled.name as canceled_by_name
        FROM stock_returns r
        JOIN products p ON r.product_id = p.id
        LEFT JOIN users_exp u_created ON r.created_by = u_created.id
        LEFT JOIN users_exp u_approved ON r.approved_by = u_approved.id
        LEFT JOIN users_exp u_rejected ON r.rejected_by = u_rejected.id
        LEFT JOIN users_exp u_completed ON r.completed_by = u_completed.id
        LEFT JOIN users_exp u_canceled ON r.canceled_by = u_canceled.id
        WHERE r.id = ?
    ");
    $stmt->execute([$returnId]);
    $return = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$return) {
        echo json_encode(['success' => false, 'message' => 'Retour non trouvé']);
        exit;
    }
    
    // Récupérer l'historique du retour
    $stmt = $pdo->prepare("
        SELECT * FROM stock_returns_history
        WHERE return_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$returnId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'return_data' => $return,
        'history' => $history
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>