<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Paramètres de pagination
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['length']) ? intval($_GET['length']) : 15;
    $offset = ($page - 1) * $limit;

    // Paramètres de filtrage
    $productName = isset($_GET['product_name']) ? $_GET['product_name'] : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $unit = isset($_GET['unit']) ? $_GET['unit'] : '';
    $stockLevel = isset($_GET['stock_level']) ? $_GET['stock_level'] : '';

    // Construction de la requête de base
    $sql = "SELECT 
                p.id,
                p.barcode, 
                p.product_name, 
                c.libelle as category_name, 
                p.quantity, 
                COALESCE(p.quantity_reserved, 0) as quantity_reserved, 
                COALESCE(p.unit, 'unité') as unit
            FROM products p
            LEFT JOIN categories c ON p.category = c.id
            WHERE 1=1";

    $countSql = "SELECT COUNT(*) as total FROM products p LEFT JOIN categories c ON p.category = c.id WHERE 1=1";
    $params = [];

    // Ajout des conditions de filtrage
    if (!empty($productName)) {
        $sql .= " AND p.product_name LIKE :productName";
        $countSql .= " AND p.product_name LIKE :productName";
        $params[':productName'] = "%$productName%";
    }

    if (!empty($category)) {
        $sql .= " AND p.category = :category";
        $countSql .= " AND p.category = :category";
        $params[':category'] = $category;
    }

    if (!empty($unit)) {
        $sql .= " AND p.unit = :unit";
        $countSql .= " AND p.unit = :unit";
        $params[':unit'] = $unit;
    }

    // Filtrage par niveau de stock
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

    // Tri
    $sql .= " ORDER BY p.product_name LIMIT :limit OFFSET :offset";

    // Préparation et exécution de la requête principale
    $stmt = $pdo->prepare($sql);

    // Liaison des paramètres
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Requête pour le comptage total
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Réponse avec pagination
    echo json_encode([
        'data' => $products,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalRecords,
        'page' => $page,
        'totalPages' => ceil($totalRecords / $limit)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur de base de données',
        'message' => $e->getMessage()
    ]);
    exit();
}
?>