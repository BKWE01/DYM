<?php
/**
 * API mise à jour pour récupérer tous les modes de paiement
 * NOUVELLE VERSION : Support upload d'icônes (icon_path)
 * 
 * @package DYM_MANUFACTURE
 * @subpackage payment_management_api
 * @author DYM Team
 * @version 3.2 - Upload d'icônes personnalisées
 * @date 29/06/2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

/**
 * Fonction helper pour générer l'URL complète de l'icône
 */
function getIconUrl($iconPath) {
    if (empty($iconPath)) {
        return null;
    }
    
    // Vérifier si c'est un chemin relatif
    if (strpos($iconPath, 'http') === 0) {
        return $iconPath; // URL absolue
    }
    
    // Construire l'URL relative depuis la racine du projet
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $projectRoot = dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))); // Remonter de 3 niveaux
    
    return $baseUrl . $projectRoot . '/' . ltrim($iconPath, '/');
}

/**
 * Fonction helper pour vérifier si un fichier d'icône existe
 */
function iconFileExists($iconPath) {
    if (empty($iconPath)) {
        return false;
    }
    
    // Chemin physique depuis la racine du serveur
    $physicalPath = $_SERVER['DOCUMENT_ROOT'] . dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))) . '/' . ltrim($iconPath, '/');
    return file_exists($physicalPath);
}

/**
 * Fonction helper pour déterminer le niveau d'usage
 */
function getUsageLevel($usage) {
    if ($usage === 0) return 'unused';
    if ($usage <= 5) return 'low';
    if ($usage <= 20) return 'medium';
    if ($usage <= 50) return 'high';
    return 'very_high';
}

try {
    // Paramètres de requête
    $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';
    $includeUsage = isset($_GET['include_usage']) && $_GET['include_usage'] === 'true';
    $includeValidation = isset($_GET['include_validation']) && $_GET['include_validation'] === 'true';
    $checkDuplicates = isset($_GET['check_duplicates']) && $_GET['check_duplicates'] === 'true';
    $singleId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    // Requête de base - MISE À JOUR : utilisation de icon_path au lieu de icon
    $query = "
        SELECT 
            pm.id,
            pm.label,
            pm.description,
            pm.icon_path,
            pm.is_active,
            pm.display_order,
            pm.created_at,
            pm.updated_at
        FROM payment_methods pm
        WHERE 1=1";
    
    $params = [];
    
    // Filtres
    if ($activeOnly) {
        $query .= " AND pm.is_active = 1";
    }
    
    if ($singleId) {
        $query .= " AND pm.id = ?";
        $params[] = $singleId;
    }
    
    $query .= " ORDER BY pm.display_order ASC, pm.id ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les statistiques d'usage séparément si demandées
    $usageStats = [];
    if ($includeUsage) {
        try {
            $usageQuery = "
                SELECT 
                    am.mode_paiement_id as payment_method_id,
                    COUNT(*) as total_usage,
                    SUM(CASE WHEN am.date_achat >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_usage,
                    SUM(CASE WHEN am.status IN ('en_attente', 'commande', 'en_livraison') THEN 1 ELSE 0 END) as pending_orders,
                    MAX(am.date_achat) as last_used
                FROM achats_materiaux am 
                WHERE am.mode_paiement_id IS NOT NULL
                GROUP BY am.mode_paiement_id
            ";
            $usageStmt = $pdo->prepare($usageQuery);
            $usageStmt->execute();
            $usageResults = $usageStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Indexer par payment_method_id pour faciliter l'accès
            foreach ($usageResults as $usage) {
                $usageStats[$usage['payment_method_id']] = $usage;
            }
        } catch (PDOException $e) {
            // Si la table achats_materiaux n'existe pas, continuer sans les stats
            error_log("Table achats_materiaux introuvable, statistiques d'usage désactivées: " . $e->getMessage());
            $includeUsage = false;
        }
    }
    
    // Traitement des doublons si demandé
    $duplicatesData = [];
    if ($checkDuplicates || $includeValidation) {
        $duplicatesQuery = "
            SELECT label, COUNT(*) as count
            FROM payment_methods 
            GROUP BY LOWER(TRIM(label))
            HAVING COUNT(*) > 1
        ";
        $duplicatesStmt = $pdo->prepare($duplicatesQuery);
        $duplicatesStmt->execute();
        $duplicatesData = $duplicatesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Traitement des données pour améliorer l'affichage
    $processedData = [];
    $duplicateIds = [];
    
    // Identifier les IDs des doublons
    if (!empty($duplicatesData)) {
        foreach ($duplicatesData as $duplicate) {
            $findDuplicatesQuery = "
                SELECT id 
                FROM payment_methods 
                WHERE LOWER(TRIM(label)) = LOWER(TRIM(?))
            ";
            $findStmt = $pdo->prepare($findDuplicatesQuery);
            $findStmt->execute([$duplicate['label']]);
            $ids = $findStmt->fetchAll(PDO::FETCH_COLUMN);
            $duplicateIds = array_merge($duplicateIds, $ids);
        }
        $duplicateIds = array_unique($duplicateIds);
    }
    
    foreach ($paymentMethods as $method) {
        $processedMethod = [
            'id' => (int)$method['id'],
            'label' => $method['label'],
            'description' => $method['description'],
            'icon_path' => $method['icon_path'], // NOUVEAU : utilisation de icon_path
            'is_active' => (int)$method['is_active'],
            'display_order' => (int)$method['display_order'],
            'created_at' => $method['created_at'],
            'updated_at' => $method['updated_at'],
            'formatted_created_at' => $method['created_at'] ? date('d/m/Y H:i', strtotime($method['created_at'])) : '',
            'formatted_updated_at' => $method['updated_at'] ? date('d/m/Y H:i', strtotime($method['updated_at'])) : '',
            'status_text' => (int)$method['is_active'] === 1 ? 'Actif' : 'Inactif',
            'status_color' => (int)$method['is_active'] === 1 ? 'green' : 'red'
        ];
        
        // NOUVEAU : Gestion des icônes uploadées
        $processedMethod['icon_info'] = [
            'has_custom_icon' => !empty($method['icon_path']),
            'icon_url' => getIconUrl($method['icon_path']),
            'icon_exists' => iconFileExists($method['icon_path']),
            'icon_filename' => !empty($method['icon_path']) ? basename($method['icon_path']) : null,
            'default_icon' => '💳', // Icône par défaut si pas d'upload
        ];
        
        // Ajouter les statistiques d'usage si demandées et disponibles
        if ($includeUsage && isset($usageStats[$method['id']])) {
            $usage = $usageStats[$method['id']];
            $totalUsage = (int)$usage['total_usage'];
            $processedMethod['usage_stats'] = [
                'total_usage' => $totalUsage,
                'recent_usage_30d' => (int)$usage['recent_usage'],
                'pending_orders' => (int)$usage['pending_orders'],
                'last_used_date' => $usage['last_used'],
                'formatted_last_used' => $usage['last_used'] ? date('d/m/Y H:i', strtotime($usage['last_used'])) : 'Jamais',
                'usage_level' => getUsageLevel($totalUsage),
                'can_deactivate' => (int)$usage['pending_orders'] === 0
            ];
        } elseif ($includeUsage) {
            // Pas de statistiques trouvées pour ce mode
            $processedMethod['usage_stats'] = [
                'total_usage' => 0,
                'recent_usage_30d' => 0,
                'pending_orders' => 0,
                'last_used_date' => null,
                'formatted_last_used' => 'Jamais',
                'usage_level' => 'unused',
                'can_deactivate' => true
            ];
        }
        
        // Ajouter les flags de validation si demandés
        if ($includeValidation) {
            $processedMethod['validation'] = [
                'is_duplicate' => in_array((int)$method['id'], $duplicateIds),
                'label_valid' => !empty(trim($method['label'])) && strlen(trim($method['label'])) >= 2,
                'has_whitespace' => $method['label'] !== trim($method['label']),
                'icon_valid' => empty($method['icon_path']) || iconFileExists($method['icon_path']), // NOUVEAU : validation existence fichier
                'needs_cleanup' => false
            ];
            
            // Marquer comme nécessitant un nettoyage si des problèmes sont détectés
            $processedMethod['validation']['needs_cleanup'] = 
                $processedMethod['validation']['is_duplicate'] || 
                !$processedMethod['validation']['label_valid'] || 
                $processedMethod['validation']['has_whitespace'] ||
                !$processedMethod['validation']['icon_valid'];
        }
        
        // Informations supplémentaires pour l'interface
        $processedMethod['ui_info'] = [
            'icon_display' => $processedMethod['icon_info']['has_custom_icon'] ? 
                             $processedMethod['icon_info']['icon_url'] : 
                             $processedMethod['icon_info']['default_icon'],
            'description_short' => $method['description'] ? 
                (strlen($method['description']) > 50 ? substr($method['description'], 0, 50) . '...' : $method['description']) : 
                'Aucune description',
            'age_days' => $method['created_at'] ? floor((time() - strtotime($method['created_at'])) / 86400) : 0,
            'last_modified_days' => $method['updated_at'] ? floor((time() - strtotime($method['updated_at'])) / 86400) : 0,
            'unique_identifier' => 'payment_' . $method['id'],
            'display_name' => $method['label'],
            'icon_type' => $processedMethod['icon_info']['has_custom_icon'] ? 'uploaded' : 'default'
        ];
        
        $processedData[] = $processedMethod;
    }
    
    // Statistiques globales
    $totalMethods = count($processedData);
    $activeMethods = count(array_filter($processedData, function($m) { return $m['is_active'] === 1; }));
    $inactiveMethods = $totalMethods - $activeMethods;
    $methodsWithCustomIcons = count(array_filter($processedData, function($m) { return $m['icon_info']['has_custom_icon']; }));
    $methodsWithBrokenIcons = count(array_filter($processedData, function($m) { 
        return $m['icon_info']['has_custom_icon'] && !$m['icon_info']['icon_exists']; 
    }));
    
    $globalStats = [
        'total_methods' => $totalMethods,
        'active_methods' => $activeMethods,
        'inactive_methods' => $inactiveMethods,
        'duplicate_count' => count($duplicateIds),
        'needs_cleanup_count' => 0,
        // NOUVEAU : Statistiques des icônes
        'icon_stats' => [
            'methods_with_custom_icons' => $methodsWithCustomIcons,
            'methods_with_default_icons' => $totalMethods - $methodsWithCustomIcons,
            'broken_icon_links' => $methodsWithBrokenIcons,
            'icon_usage_percentage' => $totalMethods > 0 ? round(($methodsWithCustomIcons / $totalMethods) * 100, 1) : 0
        ]
    ];
    
    if ($includeValidation) {
        $globalStats['needs_cleanup_count'] = count(array_filter($processedData, function($m) { 
            return isset($m['validation']['needs_cleanup']) && $m['validation']['needs_cleanup']; 
        }));
    }
    
    if ($includeUsage) {
        // Calculer les totaux d'usage à partir des statistiques collectées
        $totalUsage = 0;
        $recentUsage = 0;
        $pendingOrders = 0;
        
        foreach ($processedData as $method) {
            if (isset($method['usage_stats'])) {
                $totalUsage += $method['usage_stats']['total_usage'];
                $recentUsage += $method['usage_stats']['recent_usage_30d'];
                $pendingOrders += $method['usage_stats']['pending_orders'];
            }
        }
        
        $globalStats['usage_stats'] = [
            'total_usage' => $totalUsage,
            'recent_usage_30d' => $recentUsage,
            'pending_orders' => $pendingOrders,
            'average_usage_per_method' => $totalMethods > 0 ? round($totalUsage / $totalMethods, 2) : 0
        ];
    }
    
    // Réponse
    $response = [
        'success' => true,
        'data' => $processedData,
        'statistics' => $globalStats,
        'options' => [
            'active_only' => $activeOnly,
            'include_usage' => $includeUsage,
            'include_validation' => $includeValidation
        ],
        'message' => "Récupération réussie de {$totalMethods} mode(s) de paiement",
        'version' => '3.2', // NOUVELLE VERSION avec upload d'icônes
        'features' => [
            'icon_upload_support' => true,
            'icon_validation' => true,
            'custom_icon_stats' => true
        ]
    ];
    
    // Ajouter les doublons si demandés
    if ($checkDuplicates) {
        $response['duplicates'] = $duplicatesData;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Erreur base de données dans get_payment_methods.php: " . $e->getMessage());
    error_log("Requête SQL: " . ($query ?? 'non définie'));
    error_log("Paramètres: " . print_r($params ?? [], true));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des modes de paiement',
        'error_details' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
    
} catch (Exception $e) {
    error_log("Erreur générale dans get_payment_methods.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur inattendue s\'est produite',
        'error_details' => $e->getMessage()
    ]);
}
?>