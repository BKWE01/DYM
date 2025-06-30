<?php
/**
 * API pour modifier une commande existante
 * 
 * @package DYM_MANUFACTURE
 * @subpackage api/orders
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
    // Vérifier la méthode de requête
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    // Récupérer et valider les données
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $expressionId = filter_input(INPUT_POST, 'expression_id', FILTER_SANITIZE_STRING);
    $sourceTable = filter_input(INPUT_POST, 'source_table', FILTER_SANITIZE_STRING);
    $designation = filter_input(INPUT_POST, 'designation', FILTER_SANITIZE_STRING);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
    $unit = filter_input(INPUT_POST, 'unit', FILTER_SANITIZE_STRING);
    $prixUnitaire = filter_input(INPUT_POST, 'prix_unitaire', FILTER_VALIDATE_FLOAT);
    $fournisseur = filter_input(INPUT_POST, 'fournisseur', FILTER_SANITIZE_STRING);
    $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $userId = $_SESSION['user_id'];

    // Validation des données
    if (!$orderId || !$expressionId || !$quantity || !$prixUnitaire || !$fournisseur || !$paymentMethod) {
        throw new Exception('Données manquantes ou invalides');
    }

    if ($quantity <= 0 || $prixUnitaire <= 0) {
        throw new Exception('La quantité et le prix doivent être supérieurs à 0');
    }

    // Déterminer la source et les tables à modifier
    $sourceTable = $sourceTable ?: 'expression_dym';
    
    // Démarrer une transaction
    $pdo->beginTransaction();

    // 1. Récupérer les données actuelles de la commande
    $currentDataQuery = "SELECT * FROM achats_materiaux WHERE id = :order_id";
    $currentStmt = $pdo->prepare($currentDataQuery);
    $currentStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $currentStmt->execute();
    $currentData = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentData) {
        throw new Exception('Commande introuvable');
    }

    // 2. Vérifier si la commande peut être modifiée
    if (in_array($currentData['status'], ['reçu', 'annulé'])) {
        throw new Exception('Cette commande ne peut plus être modifiée (statut: ' . $currentData['status'] . ')');
    }

    // 3. Insérer dans l'historique des modifications
    $historyQuery = "INSERT INTO order_modifications_history 
                    (order_id, expression_id, old_quantity, new_quantity, old_price, new_price, 
                     old_supplier, new_supplier, old_payment_method, new_payment_method, 
                     modification_reason, modified_by, modified_at) 
                    VALUES 
                    (:order_id, :expression_id, :old_quantity, :new_quantity, :old_price, :new_price,
                     :old_supplier, :new_supplier, :old_payment_method, :new_payment_method,
                     :modification_reason, :modified_by, NOW())";

    $historyStmt = $pdo->prepare($historyQuery);
    $historyStmt->execute([
        ':order_id' => $orderId,
        ':expression_id' => $expressionId,
        ':old_quantity' => $currentData['quantity'],
        ':new_quantity' => $quantity,
        ':old_price' => $currentData['prix_unitaire'],
        ':new_price' => $prixUnitaire,
        ':old_supplier' => $currentData['fournisseur'],
        ':new_supplier' => $fournisseur,
        ':old_payment_method' => $currentData['mode_paiement'] ?? null,
        ':new_payment_method' => $paymentMethod,
        ':modification_reason' => $notes,
        ':modified_by' => $userId
    ]);

    // 4. Mettre à jour la commande dans achats_materiaux
    $updateOrderQuery = "UPDATE achats_materiaux 
                        SET quantity = :quantity, 
                            prix_unitaire = :prix_unitaire, 
                            fournisseur = :fournisseur,
                            mode_paiement = :payment_method,
                            notes = :notes,
                            updated_at = NOW()
                        WHERE id = :order_id";

    $updateStmt = $pdo->prepare($updateOrderQuery);
    $updateStmt->execute([
        ':quantity' => $quantity,
        ':prix_unitaire' => $prixUnitaire,
        ':fournisseur' => $fournisseur,
        ':payment_method' => $paymentMethod,
        ':notes' => $notes,
        ':order_id' => $orderId
    ]);

    // 5. Mettre à jour la table source (expression_dym ou besoins)
    if ($sourceTable === 'besoins') {
        $updateSourceQuery = "UPDATE besoins 
                             SET qt_acheter = :quantity
                             WHERE idBesoin = :expression_id 
                             AND designation_article = :designation";
    } else {
        $updateSourceQuery = "UPDATE expression_dym 
                             SET qt_acheter = :quantity,
                                 prix_unitaire = :prix_unitaire,
                                 fournisseur = :fournisseur
                             WHERE idExpression = :expression_id 
                             AND designation = :designation";
    }

    $updateSourceStmt = $pdo->prepare($updateSourceQuery);
    if ($sourceTable === 'besoins') {
        $updateSourceStmt->execute([
            ':quantity' => $quantity,
            ':expression_id' => $expressionId,
            ':designation' => $designation
        ]);
    } else {
        $updateSourceStmt->execute([
            ':quantity' => $quantity,
            ':prix_unitaire' => $prixUnitaire,
            ':fournisseur' => $fournisseur,
            ':expression_id' => $expressionId,
            ':designation' => $designation
        ]);
    }

    // 6. Ajouter une entrée dans les logs système
    $logQuery = "INSERT INTO system_logs 
                (user_id, username, action, type, entity_id, entity_name, details, created_at) 
                VALUES 
                (:user_id, :username, :action, :type, :entity_id, :entity_name, :details, NOW())";

    $logStmt = $pdo->prepare($logQuery);
    $logStmt->execute([
        ':user_id' => $userId,
        ':username' => $_SESSION['name'] ?? 'Utilisateur',
        ':action' => 'MODIFICATION_COMMANDE',
        ':type' => 'ACHAT',
        ':entity_id' => $orderId,
        ':entity_name' => $designation,
        ':details' => "Modification commande: Quantité ({$currentData['quantity']} → {$quantity}), Prix ({$currentData['prix_unitaire']} → {$prixUnitaire}), Fournisseur ({$currentData['fournisseur']} → {$fournisseur})"
    ]);

    // Valider la transaction
    $pdo->commit();

    // Réponse de succès
    echo json_encode([
        'success' => true,
        'message' => 'Commande modifiée avec succès',
        'data' => [
            'order_id' => $orderId,
            'expression_id' => $expressionId,
            'quantity' => $quantity,
            'prix_unitaire' => $prixUnitaire,
            'fournisseur' => $fournisseur,
            'payment_method' => $paymentMethod
        ]
    ]);

} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Erreur lors de la modification de commande: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>