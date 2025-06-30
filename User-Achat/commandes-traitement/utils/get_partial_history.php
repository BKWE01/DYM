<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../../database/connection.php';

// Récupérer les paramètres de la requête
$expressionId = isset($_GET['expression_id']) ? $_GET['expression_id'] : '';
$designation = isset($_GET['designation']) ? $_GET['designation'] : '';

if (empty($expressionId) || empty($designation)) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants'
    ]);
    exit;
}

try {
    // Récupérer l'historique des commandes pour ce produit
    $query = "SELECT 
        am.*,
        ip.code_projet,
        ip.nom_client
    FROM 
        achats_materiaux am
    LEFT JOIN 
        identification_projet ip ON am.expression_id = ip.idExpression
    WHERE 
        am.expression_id = :expression_id
        AND am.designation = :designation
    ORDER BY 
        am.date_achat DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':expression_id' => $expressionId,
        ':designation' => $designation
    ]);
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Préparer la réponse
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}