<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';
include_once '../../../include/date_helper.php';

// Définir le type de contenu
header('Content-Type: application/json');

try {
    // Requête modifiée pour éviter les doublons
    $query = "SELECT 
        sr.id,
        sr.movement_id,
        sr.product_id,
        sr.supplier_id,
        sr.supplier_name,
        sr.quantity,
        sr.reason,
        sr.comment,
        sr.status,
        sr.created_at,
        sr.completed_at,
        p.product_name,
        p.product_image,
        COALESCE((
            SELECT MAX(ed.prix_unitaire) 
            FROM expression_dym ed 
            WHERE ed.designation = p.product_name 
            LIMIT 1
        ), 0) as prix_unitaire
    FROM supplier_returns sr
    JOIN products p ON sr.product_id = p.id
    WHERE " . getFilteredDateCondition('sr.created_at') . "
    GROUP BY sr.id
    ORDER BY sr.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatage des dates
    foreach ($returns as &$return) {
        $return['created_at'] = date('d/m/Y H:i', strtotime($return['created_at']));
        if ($return['completed_at']) {
            $return['completed_at'] = date('d/m/Y H:i', strtotime($return['completed_at']));
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $returns
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
    ]);
}
?>