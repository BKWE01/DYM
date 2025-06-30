<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de retour manquant ou invalide']);
    exit();
}

$returnId = (int) $_GET['id'];

// Connexion à la base de données
include_once '../../../database/connection.php';

// Définir le type de contenu
header('Content-Type: application/json');

try {
    $query = "SELECT 
        sr.*,
        p.product_name,
        COALESCE((
            SELECT MAX(ed.prix_unitaire) 
            FROM expression_dym ed 
            WHERE ed.designation = p.product_name 
            LIMIT 1
        ), 0) as prix_unitaire
    FROM supplier_returns sr
    JOIN products p ON sr.product_id = p.id
    WHERE sr.id = :return_id
    LIMIT 1";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':return_id', $returnId, PDO::PARAM_INT);
    $stmt->execute();
    $return = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($return) {
        // Formatage des dates
        $return['created_at'] = date('d/m/Y H:i', strtotime($return['created_at']));
        if ($return['completed_at']) {
            $return['completed_at'] = date('d/m/Y H:i', strtotime($return['completed_at']));
        }

        echo json_encode([
            'success' => true,
            'data' => $return
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Retour non trouvé'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
    ]);
}
?>