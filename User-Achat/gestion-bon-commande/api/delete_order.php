<?php
/**
 * ================================================================
 * API DE SUPPRESSION DES BONS DE COMMANDE
 * Fichier: /gestion-bon-commande/api/delete_order.php
 * Version: 1.0
 * Description: Gestion sécurisée de la suppression des bons de commande
 * ================================================================
 */

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Utilisateur non connecté'
    ]);
    exit();
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Méthode HTTP non autorisée'
    ]);
    exit();
}

// Récupérer les données de la requête
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Récupérer l'ID du bon de commande
$orderId = null;
if (isset($data['id'])) {
    $orderId = intval($data['id']);
} elseif (isset($_POST['id'])) {
    $orderId = intval($_POST['id']);
} elseif (isset($_GET['id'])) {
    $orderId = intval($_GET['id']);
}

// Vérifier si l'ID est valide
if (!$orderId || $orderId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'ID du bon de commande manquant ou invalide'
    ]);
    exit();
}

// Récupérer le motif de suppression (optionnel)
$deleteReason = isset($data['reason']) ? trim($data['reason']) : 'Suppression manuelle';

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Commencer une transaction
    $pdo->beginTransaction();

    // 1. Récupérer les informations du bon de commande avant suppression
    $orderInfoQuery = "SELECT 
                        po.id,
                        po.order_number,
                        po.file_path,
                        po.fournisseur,
                        po.montant_total,
                        po.expression_id,
                        po.related_expressions,
                        po.is_multi_project,
                        po.status,
                        po.signature_finance,
                        po.user_finance_id,
                        u.name as created_by_name
                     FROM purchase_orders po
                     LEFT JOIN users_exp u ON po.user_id = u.id
                     WHERE po.id = ?";

    $orderInfoStmt = $pdo->prepare($orderInfoQuery);
    $orderInfoStmt->execute([$orderId]);
    $orderInfo = $orderInfoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$orderInfo) {
        throw new Exception("Bon de commande non trouvé (ID: {$orderId})");
    }

    // 2. Vérifications de sécurité
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['user_type'] ?? 'user';

    // Vérifier si l'utilisateur a le droit de supprimer
    // (Admin ou créateur du bon de commande)
    $canDelete = false;
    
    if ($userRole === 'admin' || $userRole === 'super_admin') {
        $canDelete = true;
    } else {
        // Vérifier si c'est le créateur
        $creatorQuery = "SELECT user_id FROM purchase_orders WHERE id = ?";
        $creatorStmt = $pdo->prepare($creatorQuery);
        $creatorStmt->execute([$orderId]);
        $creatorId = $creatorStmt->fetchColumn();
        
        if ($creatorId == $userId) {
            $canDelete = true;
        }
    }

    if (!$canDelete) {
        throw new Exception("Vous n'avez pas l'autorisation de supprimer ce bon de commande");
    }

    // 3. Vérifications de logique métier
    
    // Empêcher la suppression si le bon est déjà validé par la finance
    if ($orderInfo['signature_finance'] && $orderInfo['user_finance_id']) {
        throw new Exception("Impossible de supprimer un bon de commande déjà validé par le service finance");
    }

    // 4. Enregistrer l'historique avant suppression
    $historyData = [
        'order_id' => $orderId,
        'project_id' => $orderInfo['expression_id'],
        'designation' => "Bon de commande {$orderInfo['order_number']}",
        'canceled_by' => $userId,
        'cancel_reason' => $deleteReason,
        'original_status' => $orderInfo['status'] ?? 'pending',
        'is_partial' => 0
    ];

    $historyQuery = "INSERT INTO canceled_orders_log 
                    (order_id, project_id, designation, canceled_by, cancel_reason, original_status, is_partial, canceled_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $historyStmt = $pdo->prepare($historyQuery);
    $historyStmt->execute([
        $historyData['order_id'],
        $historyData['project_id'],
        $historyData['designation'],
        $historyData['canceled_by'],
        $historyData['cancel_reason'],
        $historyData['original_status'],
        $historyData['is_partial']
    ]);

    // 5. Supprimer les enregistrements liés (dans l'ordre pour respecter les contraintes)
    
    // Supprimer les matériaux liés (si la table existe)
    $checkMaterialsTableQuery = "SHOW TABLES LIKE 'purchase_order_materials'";
    $checkMaterialsStmt = $pdo->prepare($checkMaterialsTableQuery);
    $checkMaterialsStmt->execute();
    
    if ($checkMaterialsStmt->rowCount() > 0) {
        $deleteMaterialsQuery = "DELETE FROM purchase_order_materials WHERE purchase_order_id = ?";
        $deleteMaterialsStmt = $pdo->prepare($deleteMaterialsQuery);
        $deleteMaterialsStmt->execute([$orderId]);
    }

    // Supprimer les relations d'expressions liées
    $deleteRelationsQuery = "DELETE FROM related_expressions WHERE achat_id = ?";
    $deleteRelationsStmt = $pdo->prepare($deleteRelationsQuery);
    $deleteRelationsStmt->execute([$orderId]);

    // 6. Supprimer le bon de commande principal
    $deleteOrderQuery = "DELETE FROM purchase_orders WHERE id = ?";
    $deleteOrderStmt = $pdo->prepare($deleteOrderQuery);
    $deleteOrderResult = $deleteOrderStmt->execute([$orderId]);

    if (!$deleteOrderResult) {
        throw new Exception("Erreur lors de la suppression du bon de commande en base de données");
    }

    // 7. Supprimer le fichier PDF physique
    $fileDeleted = false;
    $filePath = $orderInfo['file_path'];
    
    if (!empty($filePath)) {
        // Construire le chemin complet
        $basePath = dirname(dirname(__FILE__)); // Remonte de 2 niveaux
        $fullPath = null;
        
        // Différentes tentatives pour localiser le fichier
        $possiblePaths = [
            $basePath . DIRECTORY_SEPARATOR . $filePath,
            $basePath . DIRECTORY_SEPARATOR . 'purchase_orders' . DIRECTORY_SEPARATOR . basename($filePath),
            dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'purchase_orders' . DIRECTORY_SEPARATOR . basename($filePath)
        ];
        
        foreach ($possiblePaths as $path) {
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if (file_exists($normalizedPath) && is_file($normalizedPath)) {
                $fullPath = $normalizedPath;
                break;
            }
        }
        
        if ($fullPath && file_exists($fullPath)) {
            $fileDeleted = unlink($fullPath);
        }
    }

    // 8. Enregistrer l'action dans les logs système
    $logQuery = "INSERT INTO system_logs 
                (user_id, username, action, type, entity_id, entity_name, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $logDetails = json_encode([
        'order_number' => $orderInfo['order_number'],
        'fournisseur' => $orderInfo['fournisseur'],
        'montant' => $orderInfo['montant_total'],
        'file_deleted' => $fileDeleted,
        'reason' => $deleteReason
    ]);

    $logStmt = $pdo->prepare($logQuery);
    $logStmt->execute([
        $userId,
        $_SESSION['name'] ?? 'Utilisateur inconnu',
        'DELETE_PURCHASE_ORDER',
        'purchase_orders',
        $orderId,
        "Bon de commande {$orderInfo['order_number']}",
        $logDetails,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    // 9. Valider la transaction
    $pdo->commit();

    // 10. Retourner le succès
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => "Le bon de commande {$orderInfo['order_number']} a été supprimé avec succès",
        'data' => [
            'order_number' => $orderInfo['order_number'],
            'file_deleted' => $fileDeleted,
            'deleted_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }

    // Log l'erreur
    error_log("Erreur suppression bon de commande ID {$orderId}: " . $e->getMessage());

    // Retourner l'erreur
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'DELETE_ORDER_ERROR'
    ]);

} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur de base de données
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }

    // Log l'erreur de base de données
    error_log("Erreur PDO suppression bon de commande ID {$orderId}: " . $e->getMessage());

    // Retourner une erreur générique pour la sécurité
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données lors de la suppression',
        'error_code' => 'DATABASE_ERROR'
    ]);
}
?>