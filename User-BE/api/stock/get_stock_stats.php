<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Compter le nombre total de produits
    $totalQuery = "SELECT COUNT(*) as total FROM products";
    $totalStmt = $pdo->query($totalQuery);
    $totalProducts = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Compter le nombre de catégories uniques
    $categoriesQuery = "SELECT COUNT(DISTINCT category) as categories FROM products WHERE category IS NOT NULL";
    $categoriesStmt = $pdo->query($categoriesQuery);
    $categories = $categoriesStmt->fetch(PDO::FETCH_ASSOC)['categories'];

    // Compter les produits par niveau de stock
    $stockLevelsQuery = "SELECT 
        SUM(CASE WHEN quantity > 10 THEN 1 ELSE 0 END) as normal,
        SUM(CASE WHEN quantity > 0 AND quantity <= 10 THEN 1 ELSE 0 END) as low,
        SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as zero
      FROM products";
    $stockLevelsStmt = $pdo->query($stockLevelsQuery);
    $stockLevels = $stockLevelsStmt->fetch(PDO::FETCH_ASSOC);

    // Calculer les pourcentages
    $normalPercent = $totalProducts > 0 ? round(($stockLevels['normal'] / $totalProducts) * 100) : 0;
    $lowPercent = $totalProducts > 0 ? round(($stockLevels['low'] / $totalProducts) * 100) : 0;
    $zeroPercent = $totalProducts > 0 ? round(($stockLevels['zero'] / $totalProducts) * 100) : 0;

    // Préparer la réponse
    $response = [
        'total' => $totalProducts,
        'categories' => $categories,
        'normal' => $stockLevels['normal'],
        'normalPercent' => $normalPercent,
        'low' => $stockLevels['low'],
        'lowPercent' => $lowPercent,
        'zero' => $stockLevels['zero'],
        'zeroPercent' => $zeroPercent
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    // En cas d'erreur, renvoyer une réponse d'erreur
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}
?>