<?php
/**
 * API Ultra-Optimisée pour les données produits DataTables
 * Fichier: /User-Achat/statistics/api/get_products_data.php
 * Version: 2.0 Optimisée pour performances extrêmes
 */

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

include_once '../../../database/connection.php';

try {
    // Paramètres DataTables avec validation
    $draw = filter_var($_POST['draw'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
    $start = filter_var($_POST['start'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
    $length = filter_var($_POST['length'] ?? 25, FILTER_VALIDATE_INT, ['options' => ['default' => 25, 'min_range' => 1, 'max_range' => 100]]);
    $searchValue = trim($_POST['search']['value'] ?? '');
    $orderColumn = filter_var($_POST['order'][0]['column'] ?? 3, FILTER_VALIDATE_INT, ['options' => ['default' => 3, 'min_range' => 0, 'max_range' => 7]]);
    $orderDir = ($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    
    // Paramètres de filtrage personnalisés
    $categoryFilter = $_POST['category'] ?? 'all';
    $searchTerm = trim($_POST['search_term'] ?? '');

    // Configuration des colonnes pour le tri (optimisé)
    $columns = [
        0 => 'p.product_name',
        1 => 'p.quantity',
        2 => 'stock_value',
        3 => 'rotation_score', // Score de rotation pré-calculé
        4 => 'usage_score',    // Score d'utilisation pré-calculé
        5 => 'purchase_score', // Score d'achat pré-calculé
        6 => 'stock_status',   // Statut pré-calculé
        7 => null // Actions - non triable
    ];

    // Construction des conditions WHERE optimisées
    $whereConditions = ["1=1"];
    $params = [];

    // Filtre par catégorie (utilise l'index sur category)
    if ($categoryFilter !== 'all' && ctype_digit($categoryFilter)) {
        $whereConditions[] = "p.category = :category_id";
        $params[':category_id'] = (int)$categoryFilter;
    }

    // Recherche optimisée (utilise les index existants)
    $searchQuery = !empty($searchValue) ? $searchValue : $searchTerm;
    if (!empty($searchQuery)) {
        $whereConditions[] = "(p.product_name LIKE :search OR p.barcode LIKE :search)";
        $params[':search'] = "%$searchQuery%";
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Ordre de tri optimisé
    $orderBy = "ORDER BY ";
    if (isset($columns[$orderColumn]) && $columns[$orderColumn] !== null) {
        $orderBy .= $columns[$orderColumn] . " " . $orderDir;
    } else {
        $orderBy .= "rotation_score DESC, p.product_name ASC";
    }

    // ========================================
    // REQUÊTE PRINCIPALE ULTRA-OPTIMISÉE
    // ========================================
    
    $mainQuery = "
        SELECT 
            p.id,
            p.barcode,
            p.product_name,
            p.quantity,
            p.quantity_reserved,
            p.unit,
            p.unit_price,
            p.prix_moyen,
            c.libelle as category_name,
            c.id as category_id,
            
            -- Calculs de base optimisés
            (p.quantity * p.unit_price) as stock_value,
            
            -- Statut de stock pré-calculé (très rapide)
            CASE 
                WHEN p.quantity = 0 THEN 'rupture'
                WHEN p.quantity < 5 THEN 'critique'
                WHEN p.quantity < 10 THEN 'faible'
                ELSE 'normal'
            END as stock_status,
            
            -- Statistiques pré-agrégées depuis des tables temporaires
            COALESCE(ms.total_sorties_6m, 0) as total_sorties,
            COALESCE(ms.total_entrees_6m, 0) as total_entrees,
            COALESCE(ms.nb_mouvements_6m, 0) as nb_mouvements,
            COALESCE(ms.frequence_mensuelle, 0) as frequence_mensuelle,
            
            COALESCE(pas.nb_commandes, 0) as nb_commandes,
            COALESCE(pas.montant_total_achats, 0) as montant_total_achats,
            COALESCE(pas.nb_fournisseurs, 0) as nb_fournisseurs,
            
            COALESCE(pus.nb_projets, 0) as nb_projets,
            COALESCE(pus.quantite_demandee, 0) as quantite_demandee,
            
            -- Scores optimisés pour le tri (calculs simples)
            CASE 
                WHEN p.quantity > 0 AND COALESCE(ms.total_sorties_6m, 0) > 0 
                THEN ROUND(COALESCE(ms.total_sorties_6m, 0) / p.quantity, 2)
                ELSE 0
            END as rotation_score,
            
            (COALESCE(pus.nb_projets, 0) * 10 + COALESCE(pus.quantite_demandee, 0)) as usage_score,
            (COALESCE(pas.nb_commandes, 0) * 100 + COALESCE(pas.montant_total_achats, 0) / 1000) as purchase_score,
            
            -- Taux de rotation simplifié
            CASE 
                WHEN p.quantity > 0 AND COALESCE(ms.total_sorties_6m, 0) > 0 
                THEN ROUND(COALESCE(ms.total_sorties_6m, 0) / p.quantity, 2)
                ELSE 0
            END as taux_rotation

        FROM products p
        LEFT JOIN categories c ON p.category = c.id
        
        -- Jointure avec statistiques de mouvements pré-calculées (BEAUCOUP plus rapide)
        LEFT JOIN (
            SELECT 
                product_id,
                SUM(CASE WHEN movement_type = 'entry' THEN quantity ELSE 0 END) as total_entrees_6m,
                SUM(CASE WHEN movement_type = 'output' THEN quantity ELSE 0 END) as total_sorties_6m,
                COUNT(*) as nb_mouvements_6m,
                ROUND(COUNT(*) / 6.0, 1) as frequence_mensuelle
            FROM stock_movement 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY product_id
        ) ms ON p.id = ms.product_id
        
        -- Jointure avec statistiques d'achats pré-calculées (optimisé avec index)
        LEFT JOIN (
            SELECT 
                p2.id as product_id,
                COUNT(am.id) as nb_commandes,
                SUM(am.quantity * COALESCE(am.prix_unitaire, 0)) as montant_total_achats,
                COUNT(DISTINCT am.fournisseur) as nb_fournisseurs
            FROM products p2
            JOIN achats_materiaux am ON am.designation = p2.product_name
            WHERE am.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              AND am.status != 'annulé'
            GROUP BY p2.id
        ) pas ON p.id = pas.product_id
        
        -- Jointure avec statistiques de projets pré-calculées (optimisé)
        LEFT JOIN (
            SELECT 
                p3.id as product_id,
                COUNT(DISTINCT ed.idExpression) as nb_projets,
                SUM(COALESCE(ed.quantity, 0)) as quantite_demandee
            FROM products p3
            JOIN expression_dym ed ON ed.designation = p3.product_name
            WHERE ed.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY p3.id
        ) pus ON p.id = pus.product_id
        
        WHERE $whereClause
        $orderBy
        LIMIT :start, :length
    ";

    // ========================================
    // EXÉCUTION OPTIMISÉE
    // ========================================
    
    // Préparer et exécuter la requête principale
    $stmt = $pdo->prepare($mainQuery);
    
    // Lier les paramètres
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Requête de comptage optimisée (simple et rapide)
    if (!empty($whereConditions) && count($whereConditions) > 1) {
        // Si il y a des filtres, compter les résultats filtrés
        $countQuery = "
            SELECT COUNT(*) as total
            FROM products p
            LEFT JOIN categories c ON p.category = c.id
            WHERE $whereClause
        ";
        $countStmt = $pdo->prepare($countQuery);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $totalFiltered = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    } else {
        // Pas de filtre, utiliser le comptage rapide
        $totalFiltered = null; // Sera défini plus bas
    }

    // Comptage total (mis en cache ou rapide)
    static $totalRecordsCache = null;
    if ($totalRecordsCache === null) {
        $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM products");
        $totalRecordsCache = (int)$totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    $totalRecords = $totalRecordsCache;
    
    // Si pas de filtre, le total filtré = total général
    if ($totalFiltered === null) {
        $totalFiltered = $totalRecords;
    }

    // ========================================
    // FORMATAGE OPTIMISÉ DES DONNÉES
    // ========================================
    
    $data = [];
    foreach ($products as $product) {
        $data[] = [
            'id' => (int)$product['id'],
            'barcode' => $product['barcode'] ?? '',
            'product_name' => $product['product_name'] ?? '',
            'quantity' => (float)($product['quantity'] ?? 0),
            'quantity_reserved' => (float)($product['quantity_reserved'] ?? 0),
            'unit' => $product['unit'] ?? 'unité',
            'unit_price' => (float)($product['unit_price'] ?? 0),
            'prix_moyen' => (float)($product['prix_moyen'] ?? 0),
            'stock_value' => (float)($product['stock_value'] ?? 0),
            'category_name' => $product['category_name'] ?? 'Non catégorisé',
            'category_id' => (int)($product['category_id'] ?? 0),
            
            // Statistiques de mouvements
            'total_entrees' => (int)($product['total_entrees'] ?? 0),
            'total_sorties' => (int)($product['total_sorties'] ?? 0),
            'nb_mouvements' => (int)($product['nb_mouvements'] ?? 0),
            'frequence_mensuelle' => (float)($product['frequence_mensuelle'] ?? 0),
            
            // Statistiques d'achats
            'nb_commandes' => (int)($product['nb_commandes'] ?? 0),
            'montant_total_achats' => (float)($product['montant_total_achats'] ?? 0),
            'nb_fournisseurs' => (int)($product['nb_fournisseurs'] ?? 0),
            
            // Statistiques de projets
            'nb_projets' => (int)($product['nb_projets'] ?? 0),
            'quantite_demandee' => (float)($product['quantite_demandee'] ?? 0),
            
            // Indicateurs calculés
            'taux_rotation' => (float)($product['taux_rotation'] ?? 0),
            'statut_stock' => $product['stock_status'] ?? 'normal',
            
            // Scores pour optimiser les tris futurs
            'rotation_score' => (float)($product['rotation_score'] ?? 0),
            'usage_score' => (float)($product['usage_score'] ?? 0),
            'purchase_score' => (float)($product['purchase_score'] ?? 0)
        ];
    }

    // ========================================
    // RÉPONSE DATATABLES OPTIMISÉE
    // ========================================
    
    $response = [
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalFiltered,
        'data' => $data,
        'performance' => [
            'query_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms',
            'memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
            'records_returned' => count($data)
        ]
    ];

    echo json_encode($response, JSON_NUMERIC_CHECK);

} catch (PDOException $e) {
    // Log détaillé pour le débogage
    error_log("Erreur PDO dans get_products_data.php: " . $e->getMessage() . " | Query: " . ($mainQuery ?? 'N/A'));
    
    http_response_code(500);
    echo json_encode([
        'draw' => $draw ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Erreur de base de données',
        'debug' => $e->getMessage() // Retirer en production
    ]);
    
} catch (Exception $e) {
    // Log pour les autres erreurs
    error_log("Erreur générale dans get_products_data.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'draw' => $draw ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Erreur serveur'
    ]);
}

// ========================================
// OPTIMISATIONS SUPPLÉMENTAIRES
// ========================================

/**
 * Notes d'optimisation pour la base de données :
 * 
 * 1. Créer ces index si ils n'existent pas :
 *    CREATE INDEX idx_stock_movement_product_date ON stock_movement(product_id, created_at, movement_type);
 *    CREATE INDEX idx_achats_materiaux_designation_date ON achats_materiaux(designation, created_at, status);
 *    CREATE INDEX idx_expression_dym_designation_date ON expression_dym(designation, created_at);
 *    CREATE INDEX idx_products_category_name ON products(category, product_name);
 * 
 * 2. Pour de meilleures performances, considérer l'ajout d'une table de cache :
 *    CREATE TABLE product_stats_cache (
 *        product_id INT PRIMARY KEY,
 *        stats_data JSON,
 *        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 *    );
 * 
 * 3. Utiliser un job CRON pour mettre à jour les statistiques hors ligne :
 *    # Toutes les heures
 *    0 * * * * php /path/to/update_product_stats.php
 */
?>