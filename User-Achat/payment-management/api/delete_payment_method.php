<?php
/**
 * API mise à jour pour supprimer un mode de paiement
 * NOUVELLE VERSION : Support suppression d'icônes (icon_path)
 * 
 * @package DYM_MANUFACTURE
 * @subpackage payment_management_api
 * @author DYM Team
 * @version 3.2 - Upload d'icônes personnalisées
 * @date 29/06/2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérification des permissions (seuls les super_admin peuvent supprimer)
$allowedUserTypes = ['super_admin'];
/*if (!in_array($_SESSION['user_type'] ?? '', $allowedUserTypes)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Seuls les super administrateurs peuvent supprimer des modes de paiement']);
    exit();
}*/

// Vérification de la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

/**
 * Configuration pour la gestion des icônes
 */
const UPLOAD_CONFIG = [
    'upload_dir' => '../../../uploads/payment_icons/', // Répertoire d'upload
];

/**
 * Fonction pour supprimer un fichier d'icône
 */
function deleteIconFile($iconPath) {
    if (empty($iconPath)) {
        return ['success' => true, 'message' => 'Aucune icône à supprimer'];
    }
    
    $physicalPath = UPLOAD_CONFIG['upload_dir'] . basename($iconPath);
    
    if (!file_exists($physicalPath)) {
        return ['success' => true, 'message' => 'Fichier d\'icône déjà inexistant'];
    }
    
    if (unlink($physicalPath)) {
        return ['success' => true, 'message' => 'Fichier d\'icône supprimé avec succès'];
    } else {
        return ['success' => false, 'message' => 'Impossible de supprimer le fichier d\'icône'];
    }
}

/**
 * Fonction pour vérifier et nettoyer les icônes orphelines
 */
function cleanupOrphanedIcons() {
    $uploadDir = UPLOAD_CONFIG['upload_dir'];
    
    if (!is_dir($uploadDir)) {
        return ['cleaned' => 0, 'message' => 'Répertoire d\'icônes inexistant'];
    }
    
    try {
        global $pdo;
        
        // Récupérer toutes les icônes utilisées en base
        $usedIconsQuery = "SELECT DISTINCT icon_path FROM payment_methods WHERE icon_path IS NOT NULL AND icon_path != ''";
        $usedIconsStmt = $pdo->prepare($usedIconsQuery);
        $usedIconsStmt->execute();
        $usedIcons = $usedIconsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Convertir en noms de fichiers
        $usedFilenames = array_map('basename', $usedIcons);
        
        // Scanner le répertoire
        $files = scandir($uploadDir);
        $cleanedCount = 0;
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $uploadDir . $file;
            
            // Vérifier que c'est un fichier d'icône de paiement
            if (is_file($filePath) && strpos($file, 'payment_icon_') === 0) {
                // Si le fichier n'est pas utilisé, le supprimer
                if (!in_array($file, $usedFilenames)) {
                    if (unlink($filePath)) {
                        $cleanedCount++;
                    }
                }
            }
        }
        
        return ['cleaned' => $cleanedCount, 'message' => "Nettoyage effectué : {$cleanedCount} fichier(s) orphelin(s) supprimé(s)"];
        
    } catch (Exception $e) {
        return ['cleaned' => 0, 'message' => 'Erreur lors du nettoyage : ' . $e->getMessage()];
    }
}

try {
    // Récupération des données JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
        exit();
    }
    
    $id = (int)($input['id'] ?? 0);
    $force_delete = isset($input['force_delete']) && $input['force_delete'] === true;
    $cleanup_orphaned = isset($input['cleanup_orphaned']) && $input['cleanup_orphaned'] === true;
    
    // Validation
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        exit();
    }
    
    $pdo->beginTransaction();
    
    // Vérifier que l'élément existe et récupérer ses informations
    $checkQuery = "SELECT id, label, icon_path, is_active FROM payment_methods WHERE id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$id]);
    $method = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$method) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Mode de paiement non trouvé']);
        exit();
    }
    
    // Vérifier s'il y a des commandes associées à ce mode de paiement
    $usageCheckQuery = "
        SELECT COUNT(*) as count 
        FROM achats_materiaux 
        WHERE mode_paiement_id = ?
    ";
    $usageCheckStmt = $pdo->prepare($usageCheckQuery);
    $usageCheckStmt->execute([$id]);
    $usage = $usageCheckStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($usage > 0 && !$force_delete) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => "Impossible de supprimer : {$usage} commande(s) utilisent ce mode de paiement. Désactivez-le plutôt ou utilisez la suppression forcée.",
            'usage_count' => $usage,
            'can_force_delete' => true
        ]);
        exit();
    }
    
    // Vérifier qu'il restera au moins un mode de paiement actif après suppression
    if ($method['is_active'] == 1) {
        $activeCountQuery = "SELECT COUNT(*) as count FROM payment_methods WHERE is_active = 1 AND id != ?";
        $activeCountStmt = $pdo->prepare($activeCountQuery);
        $activeCountStmt->execute([$id]);
        $activeCount = $activeCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($activeCount < 1) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'Impossible de supprimer : au moins un mode de paiement actif doit être conservé'
            ]);
            exit();
        }
    }
    
    // Vérifier qu'il restera au moins 3 modes de paiement au total
    $totalCountQuery = "SELECT COUNT(*) as count FROM payment_methods WHERE id != ?";
    $totalCountStmt = $pdo->prepare($totalCountQuery);
    $totalCountStmt->execute([$id]);
    $totalCount = $totalCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($totalCount < 3) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => 'Impossible de supprimer : au moins 3 modes de paiement doivent être conservés pour le système'
        ]);
        exit();
    }
    
    // Vérifier s'il y a des commandes en cours ou récentes (sécurité supplémentaire)
    if (!$force_delete) {
        $recentUsageQuery = "
            SELECT COUNT(*) as count 
            FROM achats_materiaux 
            WHERE mode_paiement_id = ? 
            AND (status IN ('en_attente', 'commande', 'en_livraison') 
                 OR date_achat >= DATE_SUB(NOW(), INTERVAL 30 DAY))
        ";
        $recentUsageStmt = $pdo->prepare($recentUsageQuery);
        $recentUsageStmt->execute([$id]);
        $recentUsage = $recentUsageStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($recentUsage > 0) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => "Impossible de supprimer : ce mode de paiement a été utilisé récemment ({$recentUsage} commande(s) dans les 30 derniers jours)",
                'recent_usage_count' => $recentUsage,
                'can_force_delete' => true
            ]);
            exit();
        }
    }
    
    // Si suppression forcée, mettre à jour les commandes existantes vers un mode par défaut
    if ($force_delete && $usage > 0) {
        // Trouver un mode de paiement actif par défaut
        $defaultModeQuery = "SELECT id FROM payment_methods WHERE is_active = 1 AND id != ? ORDER BY display_order ASC LIMIT 1";
        $defaultModeStmt = $pdo->prepare($defaultModeQuery);
        $defaultModeStmt->execute([$id]);
        $defaultMode = $defaultModeStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($defaultMode) {
            // Mettre à jour les commandes existantes
            $updateOrdersQuery = "UPDATE achats_materiaux SET mode_paiement_id = ? WHERE mode_paiement_id = ?";
            $updateOrdersStmt = $pdo->prepare($updateOrdersQuery);
            $updateOrdersStmt->execute([$defaultMode['id'], $id]);
            
            $updatedOrders = $updateOrdersStmt->rowCount();
        } else {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'Impossible de forcer la suppression : aucun mode de paiement de remplacement disponible'
            ]);
            exit();
        }
    }
    
    // Réorganiser les ordres d'affichage pour combler le vide
    $orderAdjustQuery = "
        UPDATE payment_methods 
        SET display_order = display_order - 1 
        WHERE display_order > (SELECT display_order FROM payment_methods WHERE id = ?)
    ";
    $orderAdjustStmt = $pdo->prepare($orderAdjustQuery);
    $orderAdjustStmt->execute([$id]);
    
    // NOUVEAU : Sauvegarder les informations de l'icône avant suppression
    $iconPath = $method['icon_path'];
    $iconDeletionResult = ['success' => true, 'message' => 'Aucune icône à supprimer'];
    
    // Supprimer le mode de paiement
    $deleteQuery = "DELETE FROM payment_methods WHERE id = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$id]);
    
    // Vérifier que la suppression a fonctionné
    if ($deleteStmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => 'Aucune suppression effectuée'
        ]);
        exit();
    }
    
    $pdo->commit();
    
    // NOUVEAU : Supprimer le fichier d'icône après le commit de la base de données
    if (!empty($iconPath)) {
        $iconDeletionResult = deleteIconFile($iconPath);
    }
    
    // Nettoyage optionnel des icônes orphelines
    $cleanupResult = null;
    if ($cleanup_orphaned) {
        $cleanupResult = cleanupOrphanedIcons();
    }
    
    // Log de l'action pour audit
    $logMessage = "SUPPRESSION MODE PAIEMENT - ID: {$id}, Label: {$method['label']}";
    if ($force_delete) {
        $logMessage .= " (FORCÉE - {$usage} commande(s) réassignée(s))";
    }
    if (!empty($iconPath)) {
        $logMessage .= ", Icône supprimée: " . basename($iconPath);
    }
    $logMessage .= " - Par utilisateur: " . ($_SESSION['user_id'] ?? 'inconnu');
    
    error_log($logMessage);
    
    // Obtenir les statistiques mises à jour pour la réponse
    $statsQuery = "
        SELECT 
            COUNT(*) as total_remaining,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_remaining,
            MIN(display_order) as min_order,
            MAX(display_order) as max_order
        FROM payment_methods
    ";
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Construction de la réponse
    $response = [
        'success' => true,
        'message' => "Mode de paiement '{$method['label']}' supprimé avec succès",
        'data' => [
            'deleted_id' => (int)$id,
            'deleted_label' => $method['label'],
            'deleted_by' => $_SESSION['user_id'] ?? null,
            'deleted_at' => date('Y-m-d H:i:s'),
            'force_delete_used' => $force_delete,
            // NOUVEAU : Informations sur la suppression d'icône
            'icon_deletion' => [
                'had_icon' => !empty($iconPath),
                'icon_path' => $iconPath,
                'icon_deleted' => $iconDeletionResult['success'],
                'icon_deletion_message' => $iconDeletionResult['message']
            ],
            // Statistiques après suppression
            'remaining_stats' => [
                'total_methods' => (int)$stats['total_remaining'],
                'active_methods' => (int)$stats['active_remaining'],
                'display_order_range' => [
                    'min' => (int)$stats['min_order'],
                    'max' => (int)$stats['max_order']
                ]
            ]
        ]
    ];
    
    // Ajouter les informations de suppression forcée si applicable
    if ($force_delete && isset($updatedOrders)) {
        $response['data']['force_delete_info'] = [
            'orders_updated' => $updatedOrders,
            'default_mode_used' => $defaultMode['id'] ?? null,
            'original_usage_count' => $usage
        ];
    }
    
    // Ajouter les informations de nettoyage si applicable
    if ($cleanupResult) {
        $response['data']['cleanup_info'] = $cleanupResult;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erreur base de données dans delete_payment_method.php: " . $e->getMessage());
    
    // Gestion des erreurs de contrainte de clé étrangère
    if ($e->getCode() == 23000) {
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de supprimer : ce mode de paiement est référencé dans d\'autres données'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la suppression du mode de paiement'
        ]);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erreur générale dans delete_payment_method.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur inattendue s\'est produite'
    ]);
}
?>