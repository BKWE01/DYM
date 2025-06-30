<?php
session_start();

// Configuration des headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Non autorisé',
        'message' => 'Vous devez être connecté pour accéder à cette ressource.'
    ]);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // ===== RÉCUPÉRATION DES PRODUITS EN RUPTURE DE STOCK =====
    
    // Paramètres de pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 25;
    $offset = ($page - 1) * $limit;
    
    // Filtres additionnels
    $category = isset($_GET['category']) ? intval($_GET['category']) : 0;
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $includeReserved = isset($_GET['include_reserved']) ? filter_var($_GET['include_reserved'], FILTER_VALIDATE_BOOLEAN) : false;
    
    // Requête de base pour les produits en rupture de stock
    $sql = "
        SELECT 
            p.id,
            p.barcode,
            p.product_name,
            c.libelle as category_name,
            c.id as category_id,
            p.quantity,
            COALESCE(p.quantity_reserved, 0) as quantity_reserved,
            COALESCE(p.unit, 'unité') as unit,
            p.unit_price,
            p.prix_moyen,
            p.created_at,
            p.updated_at,
            -- Calculer le nombre de jours depuis le dernier réapprovisionnement
            COALESCE(
                (SELECT DATEDIFF(NOW(), MAX(sm.date)) 
                 FROM stock_movement sm 
                 WHERE sm.product_id = p.id AND sm.movement_type = 'entry'),
                DATEDIFF(NOW(), p.created_at)
            ) as days_out_of_stock,
            -- Dernière sortie
            (SELECT MAX(sm.date) 
             FROM stock_movement sm 
             WHERE sm.product_id = p.id AND sm.movement_type = 'output') as last_output_date,
            -- Quantité de la dernière sortie
            (SELECT sm.quantity 
             FROM stock_movement sm 
             WHERE sm.product_id = p.id AND sm.movement_type = 'output' 
             ORDER BY sm.date DESC LIMIT 1) as last_output_quantity
        FROM products p
        LEFT JOIN categories c ON p.category = c.id
        WHERE p.quantity = 0
    ";
    
    $countSql = "
        SELECT COUNT(*) as total
        FROM products p
        LEFT JOIN categories c ON p.category = c.id
        WHERE p.quantity = 0
    ";
    
    $params = [];
    
    // Filtre par catégorie
    if ($category > 0) {
        $sql .= " AND p.category = :category";
        $countSql .= " AND p.category = :category";
        $params[':category'] = $category;
    }
    
    // Filtre de recherche
    if (!empty($searchTerm)) {
        $sql .= " AND (p.product_name LIKE :search OR p.barcode LIKE :search)";
        $countSql .= " AND (p.product_name LIKE :search OR p.barcode LIKE :search)";
        $params[':search'] = "%$searchTerm%";
    }
    
    // Inclure les produits avec quantité réservée > 0 (optionnel)
    if ($includeReserved) {
        // Modifier la condition WHERE pour inclure les produits avec quantité = 0 OU quantité <= quantité_réservée
        $sql = str_replace(
            'WHERE p.quantity = 0',
            'WHERE (p.quantity = 0 OR p.quantity <= COALESCE(p.quantity_reserved, 0))',
            $sql
        );
        $countSql = str_replace(
            'WHERE p.quantity = 0',
            'WHERE (p.quantity = 0 OR p.quantity <= COALESCE(p.quantity_reserved, 0))',
            $countSql
        );
    }
    
    // Tri : les plus récemment épuisés en premier
    $sql .= " ORDER BY p.updated_at DESC, p.product_name ASC";
    $sql .= " LIMIT :limit OFFSET :offset";
    
    // ===== EXÉCUTION DES REQUÊTES =====
    
    // Compter le total
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Récupérer les données
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== FORMATAGE DES DONNÉES =====
    
    $formattedProducts = [];
    $totalDaysOutOfStock = 0;
    $oldestOutOfStock = 0;
    $recentlyOutOfStock = 0; // Épuisés dans les 7 derniers jours
    $categoriesAffected = [];
    $currentDate = new DateTime();
    
    foreach ($products as $product) {
        $daysOutOfStock = intval($product['days_out_of_stock']);
        $totalDaysOutOfStock += $daysOutOfStock;
        
        // Déterminer si récemment épuisé (moins de 7 jours)
        if ($daysOutOfStock <= 7) {
            $recentlyOutOfStock++;
        }
        
        // Suivre le plus ancien
        if ($daysOutOfStock > $oldestOutOfStock) {
            $oldestOutOfStock = $daysOutOfStock;
        }
        
        // Compter les catégories affectées
        $categoryName = $product['category_name'] ?: 'Non catégorisé';
        if (!in_array($categoryName, $categoriesAffected)) {
            $categoriesAffected[] = $categoryName;
        }
        
        // Déterminer l'urgence
        $urgency = 'normal';
        if ($product['quantity_reserved'] > 0) {
            $urgency = 'critical'; // Il y a des réservations mais pas de stock
        } elseif ($daysOutOfStock > 30) {
            $urgency = 'high'; // Épuisé depuis plus d'un mois
        } elseif ($daysOutOfStock > 7) {
            $urgency = 'medium'; // Épuisé depuis plus d'une semaine
        }
        
        // Formatage du produit
        $formattedProducts[] = [
            'id' => intval($product['id']),
            'barcode' => $product['barcode'] ?: '',
            'product_name' => $product['product_name'],
            'category_name' => $categoryName,
            'category_id' => intval($product['category_id'] ?: 0),
            'quantity' => 0, // Toujours 0 par définition
            'quantity_reserved' => intval($product['quantity_reserved']),
            'unit' => $product['unit'],
            'unit_price' => floatval($product['unit_price'] ?: 0),
            'prix_moyen' => floatval($product['prix_moyen'] ?: 0),
            'days_out_of_stock' => $daysOutOfStock,
            'last_output_date' => $product['last_output_date'],
            'last_output_quantity' => intval($product['last_output_quantity'] ?: 0),
            'urgency' => $urgency,
            'urgency_text' => getUrgencyText($urgency),
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at'],
            'status' => getOutOfStockStatus($daysOutOfStock, $product['quantity_reserved']),
            'recommendations' => generateOutOfStockRecommendations($daysOutOfStock, $product['quantity_reserved'])
        ];
    }
    
    // ===== STATISTIQUES ADDITIONNELLES =====
    
    // Analyse des mouvements récents sur les produits épuisés
    $recentMovementsQuery = "
        SELECT 
            COUNT(DISTINCT sm.product_id) as products_with_recent_activity,
            SUM(CASE WHEN sm.movement_type = 'output' THEN sm.quantity ELSE 0 END) as recent_outputs,
            COUNT(CASE WHEN sm.movement_type = 'output' THEN 1 END) as output_movements
        FROM stock_movement sm
        JOIN products p ON sm.product_id = p.id
        WHERE p.quantity = 0
        AND sm.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";
    
    $recentMovementsStmt = $pdo->prepare($recentMovementsQuery);
    $recentMovementsStmt->execute();
    $recentMovements = $recentMovementsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les projets affectés par les ruptures de stock
    $affectedProjectsQuery = "
        SELECT DISTINCT 
            ed.idExpression,
            ip.code_projet,
            ip.nom_client
        FROM expression_dym ed
        JOIN identification_projet ip ON ed.idExpression = ip.idExpression
        WHERE ed.designation IN (
            SELECT p.product_name 
            FROM products p 
            WHERE p.quantity = 0
        )
        AND ed.valide_achat NOT IN ('reçu', 'annulé')
        LIMIT 10
    ";
    
    $affectedProjectsStmt = $pdo->prepare($affectedProjectsQuery);
    $affectedProjectsStmt->execute();
    $affectedProjects = $affectedProjectsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== RÉPONSE =====
    
    $avgDaysOutOfStock = $totalRecords > 0 ? round($totalDaysOutOfStock / $totalRecords, 1) : 0;
    
    $response = [
        'success' => true,
        'data' => $formattedProducts,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalRecords / $limit),
            'total_records' => intval($totalRecords),
            'records_per_page' => $limit,
            'has_next' => $page < ceil($totalRecords / $limit),
            'has_prev' => $page > 1
        ],
        'statistics' => [
            'total_out_of_stock' => intval($totalRecords),
            'recently_out_of_stock' => $recentlyOutOfStock,
            'oldest_out_of_stock_days' => $oldestOutOfStock,
            'average_days_out_of_stock' => $avgDaysOutOfStock,
            'categories_affected' => count($categoriesAffected),
            'categories_list' => $categoriesAffected
        ],
        'recent_activity' => [
            'products_with_activity_last_30_days' => intval($recentMovements['products_with_recent_activity'] ?: 0),
            'recent_outputs_quantity' => intval($recentMovements['recent_outputs'] ?: 0),
            'output_movements_count' => intval($recentMovements['output_movements'] ?: 0)
        ],
        'affected_projects' => $affectedProjects,
        'filters_applied' => [
            'category' => $category,
            'search_term' => $searchTerm,
            'include_reserved' => $includeReserved
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur de base de données',
        'message' => 'Impossible de récupérer les produits en rupture de stock.',
        'debug' => [
            'error_code' => $e->getCode(),
            'error_message' => $e->getMessage()
        ]
    ]);
    
    error_log("Erreur SQL dans get_out_of_stock.php: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur système',
        'message' => 'Une erreur inattendue s\'est produite.',
        'debug' => [
            'error_message' => $e->getMessage()
        ]
    ]);
    
    error_log("Erreur générale dans get_out_of_stock.php: " . $e->getMessage());
}

// ===== FONCTIONS UTILITAIRES =====

/**
 * Retourne le texte descriptif de l'urgence
 */
function getUrgencyText($urgency) {
    switch ($urgency) {
        case 'critical':
            return 'Critique (Réservations en attente)';
        case 'high':
            return 'Élevée (Épuisé depuis plus de 30 jours)';
        case 'medium':
            return 'Moyenne (Épuisé depuis plus de 7 jours)';
        default:
            return 'Normale';
    }
}

/**
 * Retourne le statut du produit en rupture de stock
 */
function getOutOfStockStatus($daysOutOfStock, $quantityReserved) {
    if ($quantityReserved > 0) {
        return [
            'status' => 'critical',
            'message' => 'Rupture critique - Commandes en attente',
            'color' => 'red'
        ];
    } elseif ($daysOutOfStock > 30) {
        return [
            'status' => 'old',
            'message' => 'Rupture ancienne - Attention requise',
            'color' => 'orange'
        ];
    } elseif ($daysOutOfStock > 7) {
        return [
            'status' => 'recent',
            'message' => 'Rupture récente - Surveillance nécessaire',
            'color' => 'yellow'
        ];
    } else {
        return [
            'status' => 'new',
            'message' => 'Rupture très récente',
            'color' => 'blue'
        ];
    }
}

/**
 * Génère des recommandations pour les produits en rupture de stock
 */
function generateOutOfStockRecommendations($daysOutOfStock, $quantityReserved) {
    $recommendations = [];
    
    if ($quantityReserved > 0) {
        $recommendations[] = 'Commander immédiatement - Des commandes sont en attente';
        $recommendations[] = 'Contacter les clients concernés pour les informer';
        $recommendations[] = 'Vérifier les fournisseurs alternatifs';
    } elseif ($daysOutOfStock > 30) {
        $recommendations[] = 'Évaluer la pertinence de maintenir ce produit';
        $recommendations[] = 'Analyser l\'historique de demande';
        $recommendations[] = 'Considérer des produits de substitution';
    } elseif ($daysOutOfStock > 7) {
        $recommendations[] = 'Programmer une commande de réapprovisionnement';
        $recommendations[] = 'Vérifier les délais de livraison habituels';
        $recommendations[] = 'Surveiller les demandes clients';
    } else {
        $recommendations[] = 'Évaluer le besoin de réapprovisionnement';
        $recommendations[] = 'Analyser la cause de l\'épuisement';
    }
    
    return $recommendations;
}
?>
'