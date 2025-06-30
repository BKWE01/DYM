<?php
// Configuration des headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // ===== STATISTIQUES COMPLÈTES - TOUS LES PRODUITS =====
    
    // Requête principale pour obtenir TOUTES les statistiques
    $statsQuery = "
        SELECT 
            COUNT(*) as total_products,
            COUNT(DISTINCT p.category) as unique_categories,
            -- Stock normal (> 10)
            SUM(CASE WHEN p.quantity > 10 THEN 1 ELSE 0 END) as high_stock,
            -- Stock moyen (3-10)  
            SUM(CASE WHEN p.quantity BETWEEN 3 AND 10 THEN 1 ELSE 0 END) as medium_stock,
            -- Stock faible (1-2)
            SUM(CASE WHEN p.quantity > 0 AND p.quantity < 3 THEN 1 ELSE 0 END) as low_stock,
            -- Rupture de stock (0)
            SUM(CASE WHEN p.quantity = 0 THEN 1 ELSE 0 END) as zero_stock,
            -- Totaux des quantités
            SUM(p.quantity) as total_quantity,
            SUM(COALESCE(p.quantity_reserved, 0)) as total_reserved,
            AVG(p.quantity) as average_quantity,
            MAX(p.quantity) as max_quantity,
            MIN(p.quantity) as min_quantity
        FROM products p
        LEFT JOIN categories c ON p.category = c.id
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculs des valeurs
    $totalProducts = intval($stats['total_products']);
    $highStock = intval($stats['high_stock']);
    $mediumStock = intval($stats['medium_stock']);  
    $lowStock = intval($stats['low_stock']);
    $zeroStock = intval($stats['zero_stock']);
    
    // Stock "normal" = stock élevé + stock moyen (compatible avec l'ancien système)
    $normalStock = $highStock + $mediumStock;
    
    // Calcul des pourcentages (protection contre division par zéro)
    $normalPercent = $totalProducts > 0 ? round(($normalStock / $totalProducts) * 100, 1) : 0;
    $lowPercent = $totalProducts > 0 ? round(($lowStock / $totalProducts) * 100, 1) : 0;
    $zeroPercent = $totalProducts > 0 ? round(($zeroStock / $totalProducts) * 100, 1) : 0;
    
    // ===== RÉPONSE COMPATIBLE AVEC L'ANCIEN SYSTÈME =====
    
    $response = [
        // Format compatible avec l'ancienne API
        'total' => $totalProducts,
        'categories' => intval($stats['unique_categories']),
        'normal' => $normalStock,
        'normalPercent' => $normalPercent,
        'low' => $lowStock,
        'lowPercent' => $lowPercent,
        'zero' => $zeroStock,
        'zeroPercent' => $zeroPercent,
        
        // Statistiques détaillées additionnelles
        'detailed_stats' => [
            'high_stock' => $highStock,
            'medium_stock' => $mediumStock,
            'total_quantity' => intval($stats['total_quantity']),
            'total_reserved' => intval($stats['total_reserved']),
            'average_quantity' => round(floatval($stats['average_quantity'] ?: 0), 2),
            'max_quantity' => intval($stats['max_quantity']),
            'min_quantity' => intval($stats['min_quantity'])
        ],
        
        // Métadonnées
        'success' => true,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur de base de données',
        'message' => 'Impossible de récupérer les statistiques: ' . $e->getMessage(),
        'success' => false
    ]);
    
    // Log l'erreur
    error_log("Erreur SQL dans get_stock_stats.php: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur système', 
        'message' => 'Une erreur inattendue s\'est produite: ' . $e->getMessage(),
        'success' => false
    ]);
    
    // Log l'erreur
    error_log("Erreur générale dans get_stock_stats.php: " . $e->getMessage());
}
?>