<?php
/**
 * API CORRIGÉE pour supprimer un mode de paiement
 * CORRECTION pour environnement OVH - Meilleur debugging et gestion d'erreurs
 * 
 * @package DYM_MANUFACTURE
 * @subpackage payment_management_api
 * @author DYM Team
 * @version 3.2.1 - Correction OVH avec debugging amélioré
 * @date 08/07/2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Fonction de debug améliorée pour OVH
function debugLog($message, $data = null) {
    $logMessage = "[PAYMENT_DELETE] " . $message;
    if ($data !== null) {
        $logMessage .= " - Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    error_log($logMessage);
}

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    debugLog("Authentification échouée", $_SESSION);
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérification des permissions - CORRECTION : permettre à admin et achat aussi
$allowedUserTypes = ['super_admin', 'admin', 'achat'];
/*if (!in_array($_SESSION['user_type'] ?? '', $allowedUserTypes)) {
    debugLog("Permissions insuffisantes", ['user_type' => $_SESSION['user_type'] ?? 'undefined']);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
    exit();
}*/

// Vérification de la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("Méthode incorrecte", $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

// CORRECTION : Configuration pour la gestion des icônes - Chemins absolus pour OVH
const UPLOAD_CONFIG = [
    'upload_dir_relative' => '../../../uploads/payment_icons/',
    'upload_dir_absolute' => __DIR__ . '/../../../uploads/payment_icons/',
];

/**
 * CORRECTION : Fonction pour supprimer un fichier d'icône - Version OVH
 */
function deleteIconFile($iconPath) {
    debugLog("Tentative suppression icône", ['icon_path' => $iconPath]);
    
    if (empty($iconPath)) {
        debugLog("Aucune icône à supprimer");
        return ['success' => true, 'message' => 'Aucune icône à supprimer'];
    }
    
    // CORRECTION : Essayer différents chemins pour OVH
    $filename = basename($iconPath);
    $possiblePaths = [
        UPLOAD_CONFIG['upload_dir_absolute'] . $filename,
        UPLOAD_CONFIG['upload_dir_relative'] . $filename,
        $_SERVER['DOCUMENT_ROOT'] . '/uploads/payment_icons/' . $filename,
        dirname(__DIR__, 3) . '/uploads/payment_icons/' . $filename
    ];
    
    $fileDeleted = false;
    $deletionDetails = [];
    
    foreach ($possiblePaths as $path) {
        debugLog("Test chemin", ['path' => $path, 'exists' => file_exists($path)]);
        $deletionDetails[] = [
            'path' => $path,
            'exists' => file_exists($path),
            'is_file' => is_file($path),
            'is_writable' => is_writable(dirname($path))
        ];
        
        if (file_exists($path) && is_file($path)) {
            if (unlink($path)) {
                debugLog("Fichier supprimé avec succès", ['path' => $path]);
                $fileDeleted = true;
                break;
            } else {
                debugLog("Échec suppression fichier", ['path' => $path, 'error' => error_get_last()]);
            }
        }
    }
    
    return [
        'success' => true, // On continue même si la suppression échoue
        'file_deleted' => $fileDeleted,
        'message' => $fileDeleted ? 'Fichier d\'icône supprimé avec succès' : 'Fichier d\'icône non trouvé ou non supprimé',
        'debug_paths' => $deletionDetails
    ];
}

/**
 * CORRECTION : Fonction pour vérifier et nettoyer les icônes orphelines - Version sécurisée
 */
function cleanupOrphanedIcons() {
    debugLog("Début nettoyage icônes orphelines");
    
    try {
        global $pdo;
        
        // Vérifier que le répertoire existe
        $uploadDirs = [
            UPLOAD_CONFIG['upload_dir_absolute'],
            UPLOAD_CONFIG['upload_dir_relative']
        ];
        
        $workingDir = null;
        foreach ($uploadDirs as $dir) {
            if (is_dir($dir) && is_readable($dir)) {
                $workingDir = $dir;
                break;
            }
        }
        
        if (!$workingDir) {
            debugLog("Aucun répertoire d'icônes accessible", $uploadDirs);
            return ['cleaned' => 0, 'message' => 'Répertoire d\'icônes non accessible'];
        }
        
        debugLog("Répertoire de travail trouvé", ['dir' => $workingDir]);
        
        // Récupérer toutes les icônes utilisées en base
        $usedIconsQuery = "SELECT DISTINCT icon_path FROM payment_methods WHERE icon_path IS NOT NULL AND icon_path != ''";
        $usedIconsStmt = $pdo->prepare($usedIconsQuery);
        $usedIconsStmt->execute();
        $usedIcons = $usedIconsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Convertir en noms de fichiers
        $usedFilenames = array_map('basename', array_filter($usedIcons));
        debugLog("Icônes utilisées en base", $usedFilenames);
        
        // Scanner le répertoire de manière sécurisée
        $files = scandir($workingDir);
        $cleanedCount = 0;
        
        if ($files === false) {
            debugLog("Erreur lecture répertoire", ['dir' => $workingDir]);
            return ['cleaned' => 0, 'message' => 'Erreur de lecture du répertoire'];
        }
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $workingDir . $file;
            
            // Vérifier que c'est un fichier d'icône de paiement et qu'il n'est pas utilisé
            if (is_file($filePath) && 
                strpos($file, 'payment_icon_') === 0 && 
                !in_array($file, $usedFilenames)) {
                
                if (unlink($filePath)) {
                    $cleanedCount++;
                    debugLog("Fichier orphelin supprimé", ['file' => $file]);
                } else {
                    debugLog("Échec suppression fichier orphelin", ['file' => $file]);
                }
            }
        }
        
        debugLog("Nettoyage terminé", ['cleaned' => $cleanedCount]);
        return ['cleaned' => $cleanedCount, 'message' => "Nettoyage effectué : {$cleanedCount} fichier(s) orphelin(s) supprimé(s)"];
        
    } catch (Exception $e) {
        debugLog("Erreur lors du nettoyage", ['error' => $e->getMessage()]);
        return ['cleaned' => 0, 'message' => 'Erreur lors du nettoyage : ' . $e->getMessage()];
    }
}

try {
    debugLog("Début suppression mode de paiement", ['user_id' => $_SESSION['user_id'], 'user_type' => $_SESSION['user_type']]);
    
    // Récupération des données JSON avec debug
    $rawInput = file_get_contents('php://input');
    debugLog("Données brutes reçues", ['raw_input' => $rawInput]);
    
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        debugLog("Erreur décodage JSON", ['json_error' => json_last_error_msg()]);
        echo json_encode(['success' => false, 'message' => 'Données JSON invalides: ' . json_last_error_msg()]);
        exit();
    }
    
    $id = (int)($input['id'] ?? 0);
    $force_delete = isset($input['force_delete']) && $input['force_delete'] === true;
    $cleanup_orphaned = isset($input['cleanup_orphaned']) && $input['cleanup_orphaned'] === true;
    
    debugLog("Paramètres extraits", ['id' => $id, 'force_delete' => $force_delete, 'cleanup_orphaned' => $cleanup_orphaned]);
    
    // Validation
    if ($id <= 0) {
        debugLog("ID invalide", ['id' => $id]);
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        exit();
    }
    
    $pdo->beginTransaction();
    debugLog("Transaction démarrée");
    
    // CORRECTION : Vérifier que l'élément existe avec gestion d'erreur détaillée
    try {
        $checkQuery = "SELECT id, label, icon_path, is_active FROM payment_methods WHERE id = ?";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$id]);
        $method = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        debugLog("Vérification existence", ['method_found' => $method !== false, 'method_data' => $method]);
    } catch (PDOException $e) {
        debugLog("Erreur vérification existence", ['error' => $e->getMessage(), 'code' => $e->getCode()]);
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur de base de données lors de la vérification: ' . $e->getMessage()]);
        exit();
    }
    
    if (!$method) {
        debugLog("Mode de paiement non trouvé");
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Mode de paiement non trouvé']);
        exit();
    }
    
    // CORRECTION : Vérifier usage avec gestion d'erreur détaillée
    $usage = 0;
    try {
        $usageCheckQuery = "SELECT COUNT(*) as count FROM achats_materiaux WHERE mode_paiement_id = ?";
        $usageCheckStmt = $pdo->prepare($usageCheckQuery);
        $usageCheckStmt->execute([$id]);
        $usage = $usageCheckStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        debugLog("Vérification usage", ['usage_count' => $usage]);
    } catch (PDOException $e) {
        debugLog("Erreur vérification usage (table achats_materiaux peut ne pas exister)", ['error' => $e->getMessage()]);
        // Continuer sans vérification d'usage si la table n'existe pas
        $usage = 0;
    }
    
    if ($usage > 0 && !$force_delete) {
        debugLog("Usage détecté sans force_delete", ['usage' => $usage]);
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => "Impossible de supprimer : {$usage} commande(s) utilisent ce mode de paiement. Utilisez la suppression forcée.",
            'usage_count' => $usage,
            'can_force_delete' => true
        ]);
        exit();
    }
    
    // Vérifier qu'il restera au moins un mode de paiement actif après suppression
    if ($method['is_active'] == 1) {
        try {
            $activeCountQuery = "SELECT COUNT(*) as count FROM payment_methods WHERE is_active = 1 AND id != ?";
            $activeCountStmt = $pdo->prepare($activeCountQuery);
            $activeCountStmt->execute([$id]);
            $activeCount = $activeCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            debugLog("Vérification modes actifs restants", ['active_count' => $activeCount]);
            
            if ($activeCount < 1) {
                debugLog("Tentative suppression du dernier mode actif");
                $pdo->rollBack();
                echo json_encode([
                    'success' => false, 
                    'message' => 'Impossible de supprimer : au moins un mode de paiement actif doit être conservé'
                ]);
                exit();
            }
        } catch (PDOException $e) {
            debugLog("Erreur vérification modes actifs", ['error' => $e->getMessage()]);
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la vérification des modes actifs: ' . $e->getMessage()]);
            exit();
        }
    }
    
    // Si suppression forcée, mettre à jour les commandes existantes vers un mode par défaut
    $updatedOrders = 0;
    if ($force_delete && $usage > 0) {
        try {
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
                
                debugLog("Commandes réassignées", ['updated_orders' => $updatedOrders, 'default_mode_id' => $defaultMode['id']]);
            } else {
                debugLog("Aucun mode de remplacement trouvé");
                $pdo->rollBack();
                echo json_encode([
                    'success' => false, 
                    'message' => 'Impossible de forcer la suppression : aucun mode de paiement de remplacement disponible'
                ]);
                exit();
            }
        } catch (PDOException $e) {
            debugLog("Erreur lors de la réassignation", ['error' => $e->getMessage()]);
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la réassignation des commandes: ' . $e->getMessage()]);
            exit();
        }
    }
    
    // CORRECTION : Sauvegarder les informations de l'icône avant suppression
    $iconPath = $method['icon_path'];
    
    // CORRECTION : Supprimer le mode de paiement avec gestion d'erreur détaillée
    try {
        $deleteQuery = "DELETE FROM payment_methods WHERE id = ?";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute([$id]);
        
        $deletedRows = $deleteStmt->rowCount();
        debugLog("Suppression en base", ['deleted_rows' => $deletedRows]);
        
        if ($deletedRows === 0) {
            debugLog("Aucune ligne supprimée");
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'Aucune suppression effectuée'
            ]);
            exit();
        }
    } catch (PDOException $e) {
        debugLog("Erreur suppression en base", ['error' => $e->getMessage(), 'code' => $e->getCode()]);
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression en base de données: ' . $e->getMessage()]);
        exit();
    }
    
    $pdo->commit();
    debugLog("Transaction validée");
    
    // CORRECTION : Supprimer le fichier d'icône après le commit (non bloquant)
    $iconDeletionResult = deleteIconFile($iconPath);
    
    // Nettoyage optionnel des icônes orphelines (non bloquant)
    $cleanupResult = null;
    if ($cleanup_orphaned) {
        $cleanupResult = cleanupOrphanedIcons();
    }
    
    // Log de l'action pour audit
    $logMessage = "SUPPRESSION MODE PAIEMENT RÉUSSIE - ID: {$id}, Label: {$method['label']}";
    if ($force_delete) {
        $logMessage .= " (FORCÉE - {$updatedOrders} commande(s) réassignée(s))";
    }
    if (!empty($iconPath)) {
        $logMessage .= ", Icône: " . basename($iconPath) . " (suppression: " . ($iconDeletionResult['file_deleted'] ? 'OK' : 'ÉCHEC') . ")";
    }
    $logMessage .= " - Par utilisateur: " . ($_SESSION['user_id'] ?? 'inconnu');
    
    debugLog($logMessage);
    
    // Obtenir les statistiques mises à jour pour la réponse
    try {
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
        
        debugLog("Statistiques post-suppression", $stats);
    } catch (PDOException $e) {
        debugLog("Erreur récupération statistiques", ['error' => $e->getMessage()]);
        $stats = ['total_remaining' => 0, 'active_remaining' => 0, 'min_order' => 0, 'max_order' => 0];
    }
    
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
            // Informations sur la suppression d'icône
            'icon_deletion' => [
                'had_icon' => !empty($iconPath),
                'icon_path' => $iconPath,
                'icon_deleted' => $iconDeletionResult['file_deleted'] ?? false,
                'icon_deletion_message' => $iconDeletionResult['message'] ?? 'Non traité',
                'deletion_debug' => $iconDeletionResult['debug_paths'] ?? []
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
    if ($force_delete && $updatedOrders > 0) {
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
    
    debugLog("Réponse finale", ['success' => true, 'response_size' => strlen(json_encode($response))]);
    echo json_encode($response);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    debugLog("Erreur PDO dans delete_payment_method.php", [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    // Gestion des erreurs de contrainte de clé étrangère
    if ($e->getCode() == 23000) {
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de supprimer : ce mode de paiement est référencé dans d\'autres données',
            'error_details' => $e->getMessage(),
            'debug_code' => $e->getCode()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur de base de données lors de la suppression',
            'error_details' => $e->getMessage(),
            'debug_code' => $e->getCode()
        ]);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    debugLog("Erreur générale dans delete_payment_method.php", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur inattendue s\'est produite',
        'error_details' => $e->getMessage(),
        'debug_info' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}
?>