<?php
/**
 * API pour récupérer les détails d'une commande et son historique
 * À placer dans le dossier /commandes-traitement/
 */
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../../database/connection.php';

// Récupérer les paramètres de la requête
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$expressionId = isset($_GET['expression_id']) ? $_GET['expression_id'] : '';
$designation = isset($_GET['designation']) ? $_GET['designation'] : '';

if ($id === 0 || empty($expressionId) || empty($designation)) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants'
    ]);
    exit;
}

try {
    // Récupérer les informations de base depuis expression_dym
    $edQuery = "SELECT ed.*, 
                 ip.code_projet, 
                 ip.nom_client
               FROM expression_dym ed
               LEFT JOIN identification_projet ip ON ed.idExpression = ip.idExpression
               WHERE ed.idExpression = :expression_id
               AND ed.designation = :designation";

    $edStmt = $pdo->prepare($edQuery);
    $edStmt->execute([
        ':expression_id' => $expressionId,
        ':designation' => $designation
    ]);
    $expressionData = $edStmt->fetch(PDO::FETCH_ASSOC);

    if (!$expressionData) {
        echo json_encode([
            'success' => false,
            'message' => 'Expression de besoin non trouvée'
        ]);
        exit;
    }

    // Récupérer les détails de l'achat spécifique
    $orderQuery = "SELECT am.* 
                  FROM achats_materiaux am
                  WHERE am.id = :id";

    $orderStmt = $pdo->prepare($orderQuery);
    $orderStmt->execute([':id' => $id]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode([
            'success' => false,
            'message' => 'Commande non trouvée'
        ]);
        exit;
    }

    // Combine les informations de l'expression et de la commande
    $result = array_merge($expressionData, $order);

    // Récupérer tout l'historique des achats pour ce produit
    $historyQuery = "SELECT am.*
                   FROM achats_materiaux am
                   WHERE am.expression_id = :expression_id
                   AND am.designation = :designation
                   ORDER BY am.date_achat DESC";

    $historyStmt = $pdo->prepare($historyQuery);
    $historyStmt->execute([
        ':expression_id' => $expressionId,
        ':designation' => $designation
    ]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Déterminer si c'est une commande partielle
    $isPartial = count($history) > 1 || ($order['is_partial'] == 1) || ($order['parent_id'] !== null);

    // Retourner les résultats
    echo json_encode([
        'success' => true,
        'order' => $result,
        'expression_data' => $expressionData,
        'is_partial' => $isPartial,
        'partial_history' => $history
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}