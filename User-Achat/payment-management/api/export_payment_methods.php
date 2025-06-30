<?php
/**
 * API mise à jour pour exporter les modes de paiement au format CSV ou JSON
 * NOUVELLE VERSION : Support icônes uploadées (icon_path)
 * 
 * @package DYM_MANUFACTURE
 * @subpackage payment_management_api
 * @author DYM Team
 * @version 3.2 - Upload d'icônes personnalisées
 * @date 29/06/2025
 */

session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérification des permissions
$allowedUserTypes = ['admin', 'achat', 'super_admin'];
if (!in_array($_SESSION['user_type'] ?? '', $allowedUserTypes)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
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
    
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $projectRoot = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])));
    
    return $baseUrl . $projectRoot . '/' . ltrim($iconPath, '/');
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
 * Fonction pour obtenir les informations sur le fichier d'icône
 */
function getIconFileInfo($iconPath) {
    if (empty($iconPath)) {
        return null;
    }
    
    $physicalPath = '../../../uploads/payment_icons/' . basename($iconPath);
    
    if (!file_exists($physicalPath)) {
        return ['exists' => false, 'error' => 'Fichier manquant'];
    }
    
    $fileSize = filesize($physicalPath);
    $mimeType = mime_content_type($physicalPath);
    $extension = strtolower(pathinfo($iconPath, PATHINFO_EXTENSION));
    
    return [
        'exists' => true,
        'filename' => basename($iconPath),
        'size' => $fileSize,
        'size_formatted' => formatFileSize($fileSize),
        'mime_type' => $mimeType,
        'extension' => $extension,
        'last_modified' => date('Y-m-d H:i:s', filemtime($physicalPath))
    ];
}

/**
 * Fonction pour formater la taille de fichier
 */
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($bytes, 1024);
    
    return round(pow(1024, $base - floor($base)), 2) . ' ' . $units[floor($base)];
}

try {
    // Récupération du format demandé (par défaut CSV)
    $format = $_GET['format'] ?? 'csv';
    $includeUsage = isset($_GET['include_usage']) && $_GET['include_usage'] === 'true';
    $includeIcons = isset($_GET['include_icons']) && $_GET['include_icons'] === 'true'; // NOUVEAU
    $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';
    $iconDetailsLevel = $_GET['icon_details'] ?? 'basic'; // basic, full, urls_only
    
    // Validation du format
    if (!in_array($format, ['csv', 'json'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Format non supporté']);
        exit();
    }
    
    // Construction de la requête - MISE À JOUR : utilisation de icon_path
    $query = "
        SELECT 
            pm.id,
            pm.label,
            pm.description,
            pm.icon_path,
            pm.is_active,
            pm.display_order,
            pm.created_at,
            pm.updated_at" . 
            ($includeUsage ? ",
            COALESCE(usage.total_usage, 0) as total_usage,
            COALESCE(usage.recent_usage, 0) as recent_usage_30d,
            COALESCE(usage.last_used, NULL) as last_used_date" : "") . "
        FROM payment_methods pm" .
        ($includeUsage ? "
        LEFT JOIN (
            SELECT 
                mode_paiement_id as payment_method_id,
                COUNT(*) as total_usage,
                SUM(CASE WHEN date_achat >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_usage,
                MAX(date_achat) as last_used
            FROM achats_materiaux 
            WHERE mode_paiement_id IS NOT NULL
            GROUP BY mode_paiement_id
        ) usage ON pm.id = usage.payment_method_id" : "") . "
        WHERE 1=1" .
        ($activeOnly ? " AND pm.is_active = 1" : "") . "
        ORDER BY pm.display_order ASC, pm.id ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enrichir les données avec les informations d'icônes si demandé
    if ($includeIcons) {
        foreach ($paymentMethods as &$method) {
            $iconInfo = getIconFileInfo($method['icon_path']);
            
            switch ($iconDetailsLevel) {
                case 'urls_only':
                    $method['icon_url'] = getIconUrl($method['icon_path']);
                    break;
                    
                case 'full':
                    $method['icon_info'] = [
                        'has_custom_icon' => !empty($method['icon_path']),
                        'icon_url' => getIconUrl($method['icon_path']),
                        'file_info' => $iconInfo
                    ];
                    break;
                    
                case 'basic':
                default:
                    $method['has_custom_icon'] = !empty($method['icon_path']);
                    $method['icon_exists'] = !empty($method['icon_path']) && ($iconInfo['exists'] ?? false);
                    $method['icon_url'] = getIconUrl($method['icon_path']);
                    break;
            }
        }
        unset($method); // Libérer la référence
    }
    
    // Générer le nom du fichier avec timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "modes_paiement_{$timestamp}";
    
    if ($format === 'json') {
        // Export JSON
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        
        $exportData = [
            'export_info' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'generated_by' => $_SESSION['name'] ?? $_SESSION['user_id'],
                'total_records' => count($paymentMethods),
                'include_usage_stats' => $includeUsage,
                'include_icon_info' => $includeIcons,
                'icon_details_level' => $iconDetailsLevel,
                'active_only' => $activeOnly,
                'format' => 'json',
                'version' => '3.2' // NOUVELLE VERSION avec icônes
            ],
            'payment_methods' => []
        ];
        
        foreach ($paymentMethods as $method) {
            $exportMethod = [
                'id' => (int)$method['id'],
                'label' => $method['label'],
                'description' => $method['description'],
                'icon_path' => $method['icon_path'], // NOUVEAU : icon_path au lieu de icon
                'is_active' => (int)$method['is_active'] === 1,
                'display_order' => (int)$method['display_order'],
                'created_at' => $method['created_at'],
                'updated_at' => $method['updated_at'],
                // Informations supplémentaires pour l'export
                'status_text' => (int)$method['is_active'] === 1 ? 'Actif' : 'Inactif',
                'formatted_dates' => [
                    'created' => $method['created_at'] ? date('d/m/Y H:i', strtotime($method['created_at'])) : '',
                    'updated' => $method['updated_at'] ? date('d/m/Y H:i', strtotime($method['updated_at'])) : ''
                ]
            ];
            
            // NOUVEAU : Ajouter les informations d'icônes selon le niveau de détail
            if ($includeIcons) {
                switch ($iconDetailsLevel) {
                    case 'urls_only':
                        $exportMethod['icon_url'] = $method['icon_url'] ?? null;
                        break;
                        
                    case 'full':
                        $exportMethod['icon_details'] = $method['icon_info'] ?? null;
                        break;
                        
                    case 'basic':
                    default:
                        $exportMethod['icon_status'] = [
                            'has_custom_icon' => $method['has_custom_icon'] ?? false,
                            'icon_exists' => $method['icon_exists'] ?? false,
                            'icon_url' => $method['icon_url'] ?? null
                        ];
                        break;
                }
            }
            
            if ($includeUsage) {
                $exportMethod['usage_statistics'] = [
                    'total_usage' => (int)($method['total_usage'] ?? 0),
                    'recent_usage_30d' => (int)($method['recent_usage_30d'] ?? 0),
                    'last_used_date' => $method['last_used_date'],
                    'formatted_last_used' => $method['last_used_date'] ? 
                        date('d/m/Y H:i', strtotime($method['last_used_date'])) : 'Jamais utilisé',
                    'usage_level' => (int)($method['total_usage'] ?? 0) == 0 ? 'unused' : 
                                   ((int)($method['total_usage'] ?? 0) <= 10 ? 'low' : 'high')
                ];
            }
            
            $exportData['payment_methods'][] = $exportMethod;
        }
        
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else {
        // Export CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        // Créer le fichier CSV
        $output = fopen('php://output', 'w');
        
        // BOM UTF-8 pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // En-têtes de colonnes - MISE À JOUR pour inclure les icônes
        $headers = [
            'ID',
            'Libellé',
            'Description',
            'Statut',
            'Ordre d\'affichage',
            'Date de création',
            'Dernière modification'
        ];
        
        // NOUVEAU : Ajouter les colonnes d'icônes selon le niveau de détail
        if ($includeIcons) {
            switch ($iconDetailsLevel) {
                case 'urls_only':
                    $headers[] = 'URL de l\'icône';
                    break;
                    
                case 'full':
                    $headers = array_merge($headers, [
                        'Icône personnalisée',
                        'Fichier d\'icône existe',
                        'URL de l\'icône',
                        'Nom du fichier',
                        'Taille du fichier',
                        'Type MIME',
                        'Extension'
                    ]);
                    break;
                    
                case 'basic':
                default:
                    $headers = array_merge($headers, [
                        'Icône personnalisée',
                        'Fichier existe',
                        'URL de l\'icône'
                    ]);
                    break;
            }
        }
        
        if ($includeUsage) {
            $headers = array_merge($headers, [
                'Utilisation totale',
                'Utilisation (30j)',
                'Dernière utilisation'
            ]);
        }
        
        fputcsv($output, $headers, ';');
        
        // Données
        foreach ($paymentMethods as $method) {
            $row = [
                $method['id'],
                $method['label'],
                $method['description'] ?: 'Aucune description',
                (int)$method['is_active'] === 1 ? 'Actif' : 'Inactif',
                $method['display_order'],
                $method['created_at'] ? date('d/m/Y H:i', strtotime($method['created_at'])) : '',
                $method['updated_at'] ? date('d/m/Y H:i', strtotime($method['updated_at'])) : ''
            ];
            
            // NOUVEAU : Ajouter les données d'icônes
            if ($includeIcons) {
                switch ($iconDetailsLevel) {
                    case 'urls_only':
                        $row[] = $method['icon_url'] ?? '';
                        break;
                        
                    case 'full':
                        $iconInfo = $method['icon_info']['file_info'] ?? null;
                        $row = array_merge($row, [
                            $method['has_custom_icon'] ? 'Oui' : 'Non',
                            ($iconInfo && $iconInfo['exists']) ? 'Oui' : 'Non',
                            $method['icon_url'] ?? '',
                            $iconInfo['filename'] ?? '',
                            $iconInfo['size_formatted'] ?? '',
                            $iconInfo['mime_type'] ?? '',
                            $iconInfo['extension'] ?? ''
                        ]);
                        break;
                        
                    case 'basic':
                    default:
                        $row = array_merge($row, [
                            ($method['has_custom_icon'] ?? false) ? 'Oui' : 'Non',
                            ($method['icon_exists'] ?? false) ? 'Oui' : 'Non',
                            $method['icon_url'] ?? ''
                        ]);
                        break;
                }
            }
            
            if ($includeUsage) {
                $row = array_merge($row, [
                    (int)($method['total_usage'] ?? 0),
                    (int)($method['recent_usage_30d'] ?? 0),
                    $method['last_used_date'] ? 
                        date('d/m/Y H:i', strtotime($method['last_used_date'])) : 'Jamais utilisé'
                ]);
            }
            
            fputcsv($output, $row, ';');
        }
        
        // Ajouter des informations de métadonnées à la fin
        fputcsv($output, [], ';'); // Ligne vide
        fputcsv($output, ['# Informations d\'export'], ';');
        fputcsv($output, ['Généré le', date('d/m/Y H:i:s')], ';');
        fputcsv($output, ['Généré par', $_SESSION['name'] ?? $_SESSION['user_id']], ';');
        fputcsv($output, ['Total d\'enregistrements', count($paymentMethods)], ';');
        fputcsv($output, ['Inclut les statistiques d\'usage', $includeUsage ? 'Oui' : 'Non'], ';');
        fputcsv($output, ['Inclut les informations d\'icônes', $includeIcons ? 'Oui' : 'Non'], ';');
        fputcsv($output, ['Niveau de détail des icônes', $iconDetailsLevel], ';');
        fputcsv($output, ['Modes actifs uniquement', $activeOnly ? 'Oui' : 'Non'], ';');
        fputcsv($output, ['Version', '3.2 (avec support d\'icônes uploadées)'], ';');
        
        // Ajouter un résumé des données
        $totalActive = count(array_filter($paymentMethods, function($m) { return (int)$m['is_active'] === 1; }));
        $totalInactive = count($paymentMethods) - $totalActive;
        $totalWithIcons = $includeIcons ? count(array_filter($paymentMethods, function($m) { return $m['has_custom_icon'] ?? false; })) : 0;
        
        fputcsv($output, [], ';'); // Ligne vide
        fputcsv($output, ['# Résumé des données'], ';');
        fputcsv($output, ['Total modes de paiement', count($paymentMethods)], ';');
        fputcsv($output, ['Modes actifs', $totalActive], ';');
        fputcsv($output, ['Modes inactifs', $totalInactive], ';');
        
        if ($includeIcons) {
            fputcsv($output, ['Modes avec icône personnalisée', $totalWithIcons], ';');
            fputcsv($output, ['Pourcentage avec icône', count($paymentMethods) > 0 ? round(($totalWithIcons / count($paymentMethods)) * 100, 1) . '%' : '0%'], ';');
        }
        
        if ($includeUsage) {
            $totalUsage = array_sum(array_column($paymentMethods, 'total_usage'));
            $totalRecentUsage = array_sum(array_column($paymentMethods, 'recent_usage_30d'));
            
            fputcsv($output, ['Usage total', $totalUsage], ';');
            fputcsv($output, ['Usage récent (30j)', $totalRecentUsage], ';');
            fputcsv($output, ['Usage moyen par mode', $totalUsage > 0 ? round($totalUsage / count($paymentMethods), 2) : 0], ';');
        }
        
        fclose($output);
    }
    
    // Log de l'export pour audit
    $logMessage = "EXPORT MODES PAIEMENT - Format: {$format}, Utilisateur: " . ($_SESSION['user_id'] ?? 'inconnu') . 
                 ", Enregistrements: " . count($paymentMethods);
    if ($includeIcons) {
        $logMessage .= ", Icônes: {$iconDetailsLevel}";
    }
    
    error_log($logMessage);
    
} catch (PDOException $e) {
    error_log("Erreur base de données dans export_payment_methods.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'export des modes de paiement'
    ]);
    
} catch (Exception $e) {
    error_log("Erreur générale dans export_payment_methods.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur inattendue s\'est produite lors de l\'export'
    ]);
}
?>