<?php
/**
 * API pour marquer une commande comme déjà reçue (super_admin uniquement)
 */

session_start();

// Désactiver la mise en cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour effectuer cette action']);
    exit();
}

// Connexion à la base de données
include_once '../../../../../../database/connection.php';

try {
    // Vérifier si l'utilisateur est un super_admin
    $userRoleQuery = "SELECT role FROM users_exp WHERE id = :user_id";
    $userRoleStmt = $pdo->prepare($userRoleQuery);
    $userRoleStmt->bindParam(':user_id', $_SESSION['user_id']);
    $userRoleStmt->execute();
    $userRole = $userRoleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRole || $userRole['role'] !== 'super_admin') {
        echo json_encode(['success' => false, 'message' => 'Action réservée aux super-administrateurs']);
        exit();
    }

    // Récupérer les données JSON du corps de la requête
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Vérifier que les données nécessaires sont présentes
    if (!$data || !isset($data['id']) || !isset($data['expression_id']) || !isset($data['designation'])) {
        echo json_encode(['success' => false, 'message' => 'Données incomplètes']);
        exit();
    }

    $id = $data['id'];
    $expressionId = $data['expression_id'];
    $designation = $data['designation'];
    $sourceTable = isset($data['source_table']) ? $data['source_table'] : 'expression_dym';

    // Commencer une transaction
    $pdo->beginTransaction();

    // Enregistrer cette action dans les logs du système
    $logDetailsQuery = "
        INSERT INTO system_logs 
        (user_id, username, action, type, entity_id, entity_name, details, ip_address) 
        VALUES 
        (:user_id, :username, 'mark_as_received', 'commande', :entity_id, :entity_name, :details, :ip_address)
    ";

    $logStmt = $pdo->prepare($logDetailsQuery);
    $logStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':username' => $_SESSION['name'] ?? 'Unknown',
        ':entity_id' => $id,
        ':entity_name' => $designation,
        ':details' => json_encode([
            'id' => $id,
            'expression_id' => $expressionId,
            'source_table' => $sourceTable,
            'action' => 'Marqué comme déjà reçu manuellement'
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    // Les mises à jour dépendent de la table source
    if ($sourceTable === 'besoins') {
        // 1. Mettre à jour le statut dans besoins
        $updateBesoinStatusQuery = "
            UPDATE besoins 
            SET achat_status = 'reçu'
            WHERE id = :id AND idBesoin = :expression_id
        ";

        $updateBesoinStmt = $pdo->prepare($updateBesoinStatusQuery);
        $updateBesoinStmt->execute([
            ':id' => $id,
            ':expression_id' => $expressionId
        ]);

        // 2. Mettre à jour le statut dans achats_materiaux
        $updateAchatQuery = "
            UPDATE achats_materiaux 
            SET status = 'reçu', 
                date_reception = NOW() 
            WHERE expression_id = :expression_id 
            AND designation = :designation
            AND status = 'commandé'
        ";

        $updateAchatStmt = $pdo->prepare($updateAchatQuery);
        $updateAchatStmt->execute([
            ':expression_id' => $expressionId,
            ':designation' => $designation
        ]);

    } else {
        // 1. Mettre à jour le statut dans expression_dym
        $updateExpressionQuery = "
            UPDATE expression_dym 
            SET valide_achat = 'reçu'
            WHERE id = :id OR (idExpression = :expression_id AND designation = :designation)
        ";

        $updateExpressionStmt = $pdo->prepare($updateExpressionQuery);
        $updateExpressionStmt->execute([
            ':id' => $id,
            ':expression_id' => $expressionId,
            ':designation' => $designation
        ]);

        // 2. Mettre à jour le statut dans achats_materiaux
        $updateAchatQuery = "
            UPDATE achats_materiaux 
            SET status = 'reçu', 
                date_reception = NOW() 
            WHERE expression_id = :expression_id 
            AND designation = :designation
            AND status = 'commandé'
        ";

        $updateAchatStmt = $pdo->prepare($updateAchatQuery);
        $updateAchatStmt->execute([
            ':expression_id' => $expressionId,
            ':designation' => $designation
        ]);
    }

    // Récupérer quelques infos pour le message de succès
    $infoQuery = "SELECT COUNT(*) as count FROM achats_materiaux WHERE expression_id = :expression_id AND designation = :designation AND status = 'reçu'";
    $infoStmt = $pdo->prepare($infoQuery);
    $infoStmt->execute([
        ':expression_id' => $expressionId,
        ':designation' => $designation
    ]);
    $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

    // Valider la transaction
    $pdo->commit();

    // Répondre avec succès
    echo json_encode([
        'success' => true,
        'message' => 'La commande a été marquée comme reçue avec succès. ' .
            $info['count'] . ' commande(s) mise(s) à jour.',
        'order_info' => [
            'id' => $id,
            'designation' => $designation,
            'expression_id' => $expressionId,
            'source_table' => $sourceTable
        ]
    ]);

} catch (Exception $e) {
    // En cas d'erreur, annuler la transaction
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Journaliser l'erreur
    error_log("Erreur lors du marquage de la commande comme reçue: " . $e->getMessage());

    // Répondre avec l'erreur
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue: ' . $e->getMessage()
    ]);
}