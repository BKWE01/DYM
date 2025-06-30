<?php
/**
 * API améliorée pour l'autocomplétion des produits
 * Prend en compte la catégorie du produit original pour des suggestions plus pertinentes
 */

include_once '../../../database/connection.php';

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';
$originalProduct = $_GET['original'] ?? '';
$originalCategory = null;

if (empty($term) || strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Si un produit original est fourni, essayer de déterminer sa catégorie
    if (!empty($originalProduct)) {
        $categoryStmt = $pdo->prepare("SELECT category FROM products WHERE product_name = ? LIMIT 1");
        $categoryStmt->execute([$originalProduct]);
        $categoryResult = $categoryStmt->fetch(PDO::FETCH_ASSOC);
        if ($categoryResult) {
            $originalCategory = $categoryResult['category'];
        }
    }
    
    // Si on a trouvé une catégorie, prioriser les produits de la même catégorie
    if ($originalCategory) {
        $stmt = $pdo->prepare("SELECT p.product_name, p.category, c.libelle as category_name 
                              FROM products p
                              LEFT JOIN categories c ON p.category = c.id
                              WHERE p.product_name LIKE ? 
                              ORDER BY 
                                CASE WHEN p.category = ? THEN 0 ELSE 1 END,
                                p.product_name 
                              LIMIT 10");
        $stmt->execute(["%$term%", $originalCategory]);
    } else {
        // Sinon, recherche standard
        $stmt = $pdo->prepare("SELECT p.product_name, p.category, c.libelle as category_name 
                              FROM products p
                              LEFT JOIN categories c ON p.category = c.id
                              WHERE p.product_name LIKE ? 
                              ORDER BY p.product_name 
                              LIMIT 10");
        $stmt->execute(["%$term%"]);
    }
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}