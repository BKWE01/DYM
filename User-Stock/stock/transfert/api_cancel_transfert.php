<?php
header('Content-Type: application/json');
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette ressource.'
    ]);
    exit;
}

// Connexion à la base de données
include_once '../../../database/connection.php';

// Récupérer les données envoyées
$data = json_decode(file_get_contents('php://input'), true);
$transfertId = isset($data['id']) ? intval($data['id']) : 0;

if ($transfertId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de transfert invalide.'
    ]);
    exit;
}

try {
    // Commencer une transaction
    $pdo->beginTransaction();
    
    // 1. Vérifier que le transfert existe et est en attente
    $checkSql = "
        SELECT 
            t.*,
            p.product_name
        FROM 
            transferts t
        JOIN 
            products p ON t.product_id = p.id
        WHERE 
            t.id = :transfert_id
            AND t.status = 'pending'
    ";
    
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute(['transfert_id' => $transfertId]);
    $transfert = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transfert) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Transfert non trouvé ou déjà traité.'
        ]);
        exit;
    }
    
    // 2. Mettre à jour le statut du transfert
    $updateTransfertSql = "
        UPDATE transferts
        SET 
            status = 'canceled',
            canceled_at = NOW(),
            canceled_by = :user_id
        WHERE 
            id = :transfert_id
    ";
    
    $updateTransfertStmt = $pdo->prepare($updateTransfertSql);
    $updateTransfertResult = $updateTransfertStmt->execute([
        'user_id' => $_SESSION['user_id'],
        'transfert_id' => $transfertId
    ]);
    
    if (!$updateTransfertResult) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de mettre à jour le statut du transfert.'
        ]);
        exit;
    }
    
    // 3. Ajouter une entrée dans l'historique
    $addHistorySql = "
        INSERT INTO transfert_history (
            transfert_id,
            action,
            details,
            user_id,
            created_at
        ) VALUES (
            :transfert_id,
            'cancel',
            :details,
            :user_id,
            NOW()
        )
    ";
    
    $historyDetails = json_encode([
        'product_name' => $transfert['product_name'],
        'quantity' => $transfert['quantity'],
        'source_project_id' => $transfert['source_project_id'],
        'destination_project_id' => $transfert['destination_project_id'],
        'reason' => $data['reason'] ?? 'Aucune raison fournie'
    ]);
    
    $addHistoryStmt = $pdo->prepare($addHistorySql);
    $addHistoryResult = $addHistoryStmt->execute([
        'transfert_id' => $transfertId,
        'details' => $historyDetails,
        'user_id' => $_SESSION['user_id']
    ]);
    
    // 4. Journaliser l'action dans system_logs
    $logSql = "
        INSERT INTO system_logs (
            user_id,
            username,
            action,
            type,
            entity_id,
            entity_name,
            details,
            ip_address,
            created_at
        ) VALUES (
            :user_id,
            :username,
            'cancel_transfert',
            'transfert',
            :transfert_id,
            :entity_name,
            :details,
            :ip_address,
            NOW()
        )
    ";
    
    $username = '';
    $userStmt = $pdo->prepare("SELECT name FROM users_exp WHERE id = :user_id");
    $userStmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $username = $user['name'];
    }
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $entityName = "Transfert #{$transfertId} - {$transfert['product_name']}";
    $logDetails = "Transfert #{$transfertId} annulé. Produit: {$transfert['product_name']}, Quantité: {$transfert['quantity']}";
    if (!empty($data['reason'])) {
        $logDetails .= ", Raison: {$data['reason']}";
    }
    
    $logStmt = $pdo->prepare($logSql);
    $logStmt->execute([
        'user_id' => $_SESSION['user_id'],
        'username' => $username,
        'transfert_id' => $transfertId,
        'entity_name' => $entityName,
        'details' => $logDetails,
        'ip_address' => $ipAddress
    ]);
    
    // Valider la transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Transfert annulé avec succès.'
    ]);
    
} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Erreur dans api_cancel_transfert.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de l\'annulation du transfert: ' . $e->getMessage()
    ]);
}