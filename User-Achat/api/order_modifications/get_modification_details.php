<?php
/**
 * API pour récupérer les détails d'une modification spécifique
 * 
 * @package DYM_MANUFACTURE
 * @subpackage api/order_modifications
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non authentifié'
    ]);
    exit();
}

// Inclure la connexion à la base de données
require_once '../../../database/connection.php';

try {
    // Récupérer l'ID de la modification
    $modificationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$modificationId) {
        throw new Exception('ID de modification invalide');
    }
    
    // Requête pour récupérer les détails de la modification
    $query = "SELECT 
                omh.*,
                u.name as user_name,
                u.email as user_email,
                am.designation,
                am.unit,
                am.status as current_status,
                ip.code_projet,
                ip.nom_client
              FROM order_modifications_history omh
              LEFT JOIN users_exp u ON omh.modified_by = u.id
              LEFT JOIN achats_materiaux am ON omh.order_id = am.id
              LEFT JOIN identification_projet ip ON omh.expression_id = ip.idExpression
              WHERE omh.id = :modification_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':modification_id', $modificationId, PDO::PARAM_INT);
    $stmt->execute();
    
    $modification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$modification) {
        throw new Exception('Modification introuvable');
    }
    
    // Récupérer l'historique des autres modifications de cette commande
    $historyQuery = "SELECT 
                       omh.modified_at,
                       omh.modification_reason,
                       u.name as user_name
                     FROM order_modifications_history omh
                     LEFT JOIN users_exp u ON omh.modified_by = u.id
                     WHERE omh.order_id = :order_id 
                     AND omh.id != :current_id
                     ORDER BY omh.modified_at DESC
                     LIMIT 10";
    
    $historyStmt = $pdo->prepare($historyQuery);
    $historyStmt->execute([
        ':order_id' => $modification['order_id'],
        ':current_id' => $modificationId
    ]);
    
    $relatedModifications = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les différences
    $changes = [];
    
    if ($modification['old_quantity'] != $modification['new_quantity']) {
        $changes[] = [
            'field' => 'quantity',
            'label' => 'Quantité',
            'old_value' => $modification['old_quantity'],
            'new_value' => $modification['new_quantity'],
            'difference' => $modification['new_quantity'] - $modification['old_quantity']
        ];
    }
    
    if ($modification['old_price'] != $modification['new_price']) {
        $priceDiff = $modification['new_price'] - $modification['old_price'];
        $changes[] = [
            'field' => 'price',
            'label' => 'Prix unitaire',
            'old_value' => $modification['old_price'],
            'new_value' => $modification['new_price'],
            'difference' => $priceDiff,
            'percentage' => $modification['old_price'] > 0 ? 
                          round(($priceDiff / $modification['old_price']) * 100, 2) : 0
        ];
    }
    
    if ($modification['old_supplier'] != $modification['new_supplier']) {
        $changes[] = [
            'field' => 'supplier',
            'label' => 'Fournisseur',
            'old_value' => $modification['old_supplier'],
            'new_value' => $modification['new_supplier']
        ];
    }
    
    if ($modification['old_payment_method'] != $modification['new_payment_method']) {
        $changes[] = [
            'field' => 'payment_method',
            'label' => 'Mode de paiement',
            'old_value' => $modification['old_payment_method'],
            'new_value' => $modification['new_payment_method']
        ];
    }
    
    // Réponse JSON
    echo json_encode([
        'success' => true,
        'modification' => $modification,
        'changes' => $changes,
        'related_modifications' => $relatedModifications,
        'summary' => [
            'total_changes' => count($changes),
            'has_quantity_change' => !empty(array_filter($changes, fn($c) => $c['field'] === 'quantity')),
            'has_price_change' => !empty(array_filter($changes, fn($c) => $c['field'] === 'price')),
            'has_supplier_change' => !empty(array_filter($changes, fn($c) => $c['field'] === 'supplier')),
            'has_payment_change' => !empty(array_filter($changes, fn($c) => $c['field'] === 'payment_method'))
        ]
    ]);

} catch (Exception $e) {
    error_log("Erreur lors de la récupération des détails de modification: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>