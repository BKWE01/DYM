<?php
/**
 * API mise à jour pour basculer l'état actif/inactif d'un mode de paiement
 * NOUVELLE VERSION : Support icônes uploadées (icon_path)
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

// Vérification des permissions
$allowedUserTypes = ['admin', 'achat', 'super_admin'];
if (!in_array($_SESSION['user_type'] ?? '', $allowedUserTypes)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
    exit();
}

// Vérification de la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

/**
 * Fonction pour générer l'URL complète de l'icône
 */
function getIconUrl($iconPath) {
    if (empty($iconPath)) {
        return null;
    }
    
    return (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . 
           dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))) . '/' . ltrim($iconPath, '/');
}

/**
 * Fonction pour vérifier si un fichier d'icône existe
 */
function iconFileExists($iconPath) {
    if (empty($iconPath)) {
        return false;
    }
    
    $physicalPath = '../../../uploads/payment_icons/' . basename($iconPath);
    return file_exists($physicalPath);
}

/**
 * Fonction pour obtenir les informations détaillées sur l'icône
 */
function getIconInfo($iconPath) {
    return [
        'has_custom_icon' => !empty($iconPath),
        'icon_url' => getIconUrl($iconPath),
        'icon_exists' => iconFileExists($iconPath),
        'icon_filename' => !empty($iconPath) ? basename($iconPath) : null,
        'default_icon' => '💳',
        'icon_type' => !empty($iconPath) ? 'uploaded' : 'default'
    ];
}

try {
    // Récupération des données JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
        exit();
    }
    
    $id = (int)($input['id'] ?? 0);
    $is_active = (int)($input['is_active'] ?? 0);
    
    // Validation
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        exit();
    }
    
    if (!in_array($is_active, [0, 1])) {
        echo json_encode(['success' => false, 'message' => 'Statut invalide']);
        exit();
    }
    
    $pdo->beginTransaction();
    
    // Vérifier que l'élément existe et récupérer les données actuelles
    $checkQuery = "SELECT id, label, icon_path, is_active, display_order FROM payment_methods WHERE id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$id]);
    $method = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$method) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Mode de paiement non trouvé']);
        exit();
    }
    
    // Vérifier si l'état change réellement
    if ((int)$method['is_active'] === $is_active) {
        $iconInfo = getIconInfo($method['icon_path']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Aucun changement nécessaire',
            'data' => [
                'id' => (int)$method['id'],
                'label' => $method['label'],
                'icon_path' => $method['icon_path'], // NOUVEAU : icon_path au lieu de icon
                'is_active' => (int)$method['is_active'],
                'status_text' => (int)$method['is_active'] === 1 ? 'Actif' : 'Inactif',
                'action_performed' => 'none',
                // NOUVEAU : Informations sur l'icône
                'icon_info' => $iconInfo
            ]
        ]);
        exit();
    }
    
    // Vérifications spéciales pour la désactivation
    if ($is_active == 0) {
        // Vérifier qu'il reste au moins un mode actif
        $activeCountQuery = "SELECT COUNT(*) as count FROM payment_methods WHERE is_active = 1 AND id != ?";
        $activeCountStmt = $pdo->prepare($activeCountQuery);
        $activeCountStmt->execute([$id]);
        $activeCount = $activeCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($activeCount < 1) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'Impossible de désactiver : au moins un mode de paiement doit rester actif'
            ]);
            exit();
        }
        
        // Vérifier s'il y a des commandes en cours avec ce mode
        $usageCheckQuery = "
            SELECT COUNT(*) as count 
            FROM achats_materiaux 
            WHERE mode_paiement_id = ? 
            AND status IN ('en_attente', 'commande', 'en_livraison')
        ";
        $usageCheckStmt = $pdo->prepare($usageCheckQuery);
        $usageCheckStmt->execute([$id]);
        $pendingUsage = $usageCheckStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($pendingUsage > 0) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => "Impossible de désactiver : {$pendingUsage} commande(s) en cours utilisent ce mode de paiement"
            ]);
            exit();
        }
        
        // Vérification supplémentaire : usage récent (7 derniers jours)
        $recentUsageQuery = "
            SELECT COUNT(*) as count 
            FROM achats_materiaux 
            WHERE mode_paiement_id = ? 
            AND date_achat >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";
        $recentUsageStmt = $pdo->prepare($recentUsageQuery);
        $recentUsageStmt->execute([$id]);
        $recentUsage = $recentUsageStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($recentUsage > 0) {
            // Avertissement mais pas blocage pour l'usage récent
            $warning = "Attention : ce mode de paiement a été utilisé {$recentUsage} fois ces 7 derniers jours";
        }
    }
    
    // Vérifications spéciales pour l'activation
    if ($is_active == 1) {
        // Vérifier que l'icône existe si elle est définie
        if (!empty($method['icon_path']) && !iconFileExists($method['icon_path'])) {
            // Avertissement si l'icône n'existe plus
            $warning = "Attention : le fichier d'icône associé à ce mode de paiement est manquant";
        }
        
        // Vérifier que le mode n'est pas en conflit avec des validations système
        $systemValidationQuery = "
            SELECT COUNT(*) as total_modes
            FROM payment_methods 
            WHERE is_active = 1
        ";
        $systemValidationStmt = $pdo->prepare($systemValidationQuery);
        $systemValidationStmt->execute();
        $systemStats = $systemValidationStmt->fetch(PDO::FETCH_ASSOC);
        
        // Log pour suivi si on dépasse un certain nombre de modes actifs
        if ($systemStats['total_modes'] >= 10) {
            error_log("ATTENTION: Activation du mode de paiement ID {$id} - Nombre élevé de modes actifs: " . ($systemStats['total_modes'] + 1));
        }
    }
    
    // Mettre à jour le statut
    $updateQuery = "UPDATE payment_methods SET is_active = ?, updated_at = NOW() WHERE id = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->execute([$is_active, $id]);
    
    // Vérifier que la mise à jour a fonctionné
    if ($updateStmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => 'Aucune modification effectuée'
        ]);
        exit();
    }
    
    // Récupérer l'état mis à jour avec des informations supplémentaires
    $selectQuery = "
        SELECT pm.*, 
               (SELECT COUNT(*) FROM achats_materiaux am WHERE am.mode_paiement_id = pm.id) as total_usage,
               (SELECT COUNT(*) FROM achats_materiaux am WHERE am.mode_paiement_id = pm.id AND am.date_achat >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_usage
        FROM payment_methods pm 
        WHERE pm.id = ?
    ";
    $selectStmt = $pdo->prepare($selectQuery);
    $selectStmt->execute([$id]);
    $updatedMethod = $selectStmt->fetch(PDO::FETCH_ASSOC);
    
    $pdo->commit();
    
    $action = $is_active ? 'activé' : 'désactivé';
    
    // Log de l'action pour audit
    $logMessage = "BASCULEMENT MODE PAIEMENT - ID: {$id}, Label: {$method['label']}, Action: {$action}";
    if (!empty($method['icon_path'])) {
        $logMessage .= ", Icône: " . basename($method['icon_path']);
    }
    $logMessage .= " - Par utilisateur: " . ($_SESSION['user_id'] ?? 'inconnu');
    
    error_log($logMessage);
    
    // Obtenir les informations sur l'icône
    $iconInfo = getIconInfo($updatedMethod['icon_path']);
    
    $response = [
        'success' => true,
        'message' => "Mode de paiement {$action} avec succès",
        'data' => [
            'id' => (int)$updatedMethod['id'],
            'label' => $updatedMethod['label'],
            'description' => $updatedMethod['description'],
            'icon_path' => $updatedMethod['icon_path'], // NOUVEAU : icon_path au lieu de icon
            'is_active' => (int)$updatedMethod['is_active'],
            'display_order' => (int)$updatedMethod['display_order'],
            'created_at' => $updatedMethod['created_at'],
            'updated_at' => $updatedMethod['updated_at'],
            // Informations supplémentaires
            'formatted_updated_at' => date('d/m/Y H:i', strtotime($updatedMethod['updated_at'])),
            'status_text' => (int)$updatedMethod['is_active'] === 1 ? 'Actif' : 'Inactif',
            'status_color' => (int)$updatedMethod['is_active'] === 1 ? 'green' : 'red',
            'action_performed' => $action,
            // NOUVEAU : Informations complètes sur l'icône
            'icon_info' => $iconInfo,
            // Statistiques d'usage
            'usage_stats' => [
                'total_usage' => (int)$updatedMethod['total_usage'],
                'recent_usage_30d' => (int)$updatedMethod['recent_usage'],
                'usage_level' => (int)$updatedMethod['total_usage'] == 0 ? 'unused' : 
                               ((int)$updatedMethod['total_usage'] <= 10 ? 'low' : 'high')
            ]
        ],
        // NOUVEAU : Validation de l'icône
        'icon_validation' => [
            'icon_configured' => !empty($updatedMethod['icon_path']),
            'icon_file_exists' => $iconInfo['icon_exists'],
            'icon_accessible' => $iconInfo['icon_exists'], // Même chose pour le moment
            'icon_warning' => !empty($updatedMethod['icon_path']) && !$iconInfo['icon_exists'] ? 
                'Fichier d\'icône manquant' : null
        ]
    ];
    
    // Ajouter l'avertissement si présent
    if (isset($warning)) {
        $response['warning'] = $warning;
    }
    
    // Ajouter les détails d'activation si activé avec icône manquante
    if ($is_active && !empty($updatedMethod['icon_path']) && !$iconInfo['icon_exists']) {
        $response['activation_warning'] = [
            'message' => 'Mode activé mais icône manquante',
            'missing_icon_path' => $updatedMethod['icon_path'],
            'suggested_action' => 'Uploadez une nouvelle icône ou supprimez la référence'
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erreur base de données dans toggle_payment_method.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la modification du statut'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erreur générale dans toggle_payment_method.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur inattendue s\'est produite'
    ]);
}
?>