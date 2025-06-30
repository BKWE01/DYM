<?php
session_start();

// Configuration des headers pour éviter la mise en cache
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
    // Paramètres de pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $length = isset($_GET['length']) ? max(1, min(100, intval($_GET['length']))) : 15;
    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    
    // Calculer l'offset basé sur start ou page
    if ($start > 0) {
        $offset = $start;
        $page = floor($start / $length) + 1;
    } else {
        $offset = ($page - 1) * $length;
    }

    // Paramètres de filtrage
    $productName = isset($_GET['product_name']) ? trim($_GET['product_name']) : '';
    $category = isset($_GET['category']) ? intval($_GET['category']) : 0;
    $unit = isset($_GET['unit']) ? trim($_GET['unit']) : '';
    $stockLevel = isset($_GET['stock_level']) ? trim($_GET['stock_level']) : '';
    
    // Paramètres DataTables
    $searchValue = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
    $orderColumn = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 1;
    $orderDir = isset($_GET['order'][0]['dir']) && $_GET['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';

    // Mapping des colonnes pour l'ordre
    $columns = ['p.barcode', 'p.product_name', 'c.libelle', 'p.quantity', 'p.quantity_reserved', 'p.unit'];
    $orderByColumn = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'p.product_name';

    // Construction de la requête de base - RÉCUPÉRER TOUS LES PRODUITS SANS EXCEPTION
    $sql = "SELECT 
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
                p.updated_at
            FROM products p
            LEFT JOIN categories c ON p.category = c.id
            WHERE 1=1"; // AUCUNE restriction sur la quantité - TOUS les produits

    $countSql = "SELECT COUNT(*) as total 
                 FROM products p 
                 LEFT JOIN categories c ON p.category = c.id 
                 WHERE 1=1"; // AUCUNE restriction sur la quantité - TOUS les produits

    $params = [];

    // ===== FILTRES SPÉCIFIQUES =====
    
    // Filtre par nom de produit
    if (!empty($productName)) {
        $sql .= " AND p.product_name LIKE :productName";
        $countSql .= " AND p.product_name LIKE :productName";
        $params[':productName'] = "%$productName%";
    }

    // Filtre par catégorie
    if ($category > 0) {
        $sql .= " AND p.category = :category";
        $countSql .= " AND p.category = :category";
        $params[':category'] = $category;
    }

    // Filtre par unité
    if (!empty($unit)) {
        $sql .= " AND p.unit = :unit";
        $countSql .= " AND p.unit = :unit";
        $params[':unit'] = $unit;
    }

    // Filtre par niveau de stock
    if (!empty($stockLevel)) {
        switch ($stockLevel) {
            case 'high':
                $sql .= " AND p.quantity > 10";
                $countSql .= " AND p.quantity > 10";
                break;
            case 'medium':
                $sql .= " AND p.quantity BETWEEN 3 AND 10";
                $countSql .= " AND p.quantity BETWEEN 3 AND 10";
                break;
            case 'low':
                $sql .= " AND p.quantity > 0 AND p.quantity < 3";
                $countSql .= " AND p.quantity > 0 AND p.quantity < 3";
                break;
            case 'zero':
                $sql .= " AND p.quantity = 0";
                $countSql .= " AND p.quantity = 0";
                break;
        }
    }

    // Recherche globale DataTables
    if (!empty($searchValue)) {
        $searchCondition = " AND (p.product_name LIKE :search 
                           OR p.barcode LIKE :search 
                           OR c.libelle LIKE :search 
                           OR p.unit LIKE :search)";
        $sql .= $searchCondition;
        $countSql .= $searchCondition;
        $params[':search'] = "%$searchValue%";
    }

    // ===== EXÉCUTION DES REQUÊTES =====

    // Requête pour le comptage total
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Ajout du tri et de la pagination
    $sql .= " ORDER BY $orderByColumn $orderDir LIMIT :limit OFFSET :offset";

    // Préparation et exécution de la requête principale
    $stmt = $pdo->prepare($sql);

    // Liaison des paramètres de filtrage
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Liaison des paramètres de pagination
    $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== TRAITEMENT DES DONNÉES =====
    
    // Formater les données pour l'affichage
    $formattedProducts = [];
    foreach ($products as $product) {
        $quantity = intval($product['quantity']);
        $quantityReserved = intval($product['quantity_reserved']);
        
        // Déterminer le statut du stock
        $stockStatus = 'normal';
        if ($quantity === 0) {
            $stockStatus = 'out_of_stock';
        } elseif ($quantity < 3) {
            $stockStatus = 'low';
        } elseif ($quantity <= 10) {
            $stockStatus = 'medium';
        } else {
            $stockStatus = 'high';
        }

        $formattedProducts[] = [
            'id' => intval($product['id']),
            'barcode' => $product['barcode'] ?: '',
            'product_name' => $product['product_name'],
            'category_name' => $product['category_name'] ?: 'Non catégorisé',
            'category_id' => intval($product['category_id'] ?: 0),
            'quantity' => $quantity,
            'quantity_reserved' => $quantityReserved,
            'quantity_available' => max(0, $quantity - $quantityReserved),
            'unit' => $product['unit'] ?: 'unité',
            'unit_price' => floatval($product['unit_price'] ?: 0),
            'prix_moyen' => floatval($product['prix_moyen'] ?: 0),
            'stock_status' => $stockStatus,
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at']
        ];
    }

    // ===== STATISTIQUES ADDITIONNELLES =====
    $stats = [
        'total_products' => $totalRecords,
        'total_quantity' => 0,
        'total_reserved' => 0,
        'categories_count' => 0
    ];

    // Calculer les statistiques sur les produits récupérés
    foreach ($formattedProducts as $product) {
        $stats['total_quantity'] += $product['quantity'];
        $stats['total_reserved'] += $product['quantity_reserved'];
    }

    // Compter les catégories distinctes
    $categoriesStmt = $pdo->prepare("SELECT COUNT(DISTINCT p.category) as count FROM products p WHERE p.category IS NOT NULL");
    $categoriesStmt->execute();
    $stats['categories_count'] = $categoriesStmt->fetch(PDO::FETCH_ASSOC)['count'];

    // ===== RÉPONSE JSON =====
    
    // Format de réponse compatible DataTables
    $response = [
        'draw' => intval($_GET['draw'] ?? 1),
        'recordsTotal' => intval($totalRecords),
        'recordsFiltered' => intval($totalRecords),
        'data' => $formattedProducts,
        'page' => $page,
        'totalPages' => ceil($totalRecords / $length),
        'stats' => $stats,
        'debug' => [
            'sql_executed' => true,
            'products_count' => count($formattedProducts),
            'filters_applied' => [
                'product_name' => $productName,
                'category' => $category,
                'unit' => $unit,
                'stock_level' => $stockLevel,
                'search' => $searchValue
            ]
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur de base de données',
        'message' => 'Impossible de récupérer les produits.',
        'debug' => [
            'error_code' => $e->getCode(),
            'error_message' => $e->getMessage(),
            'sql_error' => true
        ]
    ]);
    
    // Log l'erreur pour le débogage
    error_log("Erreur SQL dans get_filtered_products.php: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur système',
        'message' => 'Une erreur inattendue s\'est produite.',
        'debug' => [
            'error_message' => $e->getMessage(),
            'general_error' => true
        ]
    ]);
    
    // Log l'erreur pour le débogage
    error_log("Erreur générale dans get_filtered_products.php: " . $e->getMessage());
}
?>