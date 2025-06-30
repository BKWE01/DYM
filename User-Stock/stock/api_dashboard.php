<?php
// Fichier API pour le tableau de bord
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php';

try {
    // Tableaux pour stocker les données du tableau de bord
    $response = [
        'success' => true,
        'data' => [
            'general_stats' => [],
            'recent_activity' => [],
            'most_used_products' => [],
            'stock_evolution' => [],
            'category_distribution' => []
        ]
    ];

    // 1. Statistiques générales
    // Nombre total de produits
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $total_materials = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Nombre de catégories
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $categories = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Produits en alerte (stock faible ou épuisé)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE quantity <= 10");
    $materials_in_alert = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Valeur totale du stock
    $stmt = $pdo->query("SELECT SUM(quantity * unit_price) as total_value FROM products");
    $stock_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

    $response['data']['general_stats'] = [
        'total_materials' => $total_materials,
        'categories' => $categories,
        'materials_in_alert' => $materials_in_alert,
        'stock_value' => $stock_value
    ];

    // 2. Activité récente
    $stmt = $pdo->query("
        SELECT sm.*, p.product_name 
        FROM stock_movement sm
        JOIN products p ON sm.product_id = p.id
        ORDER BY sm.date DESC
        LIMIT 10
    ");
    $response['data']['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Produits les plus utilisés
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.product_name,
            p.quantity as remaining_stock,
            COALESCE((
                SELECT SUM(ABS(sm.quantity))
                FROM stock_movement sm
                WHERE sm.product_id = p.id AND sm.movement_type = 'output'
                AND sm.date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            ), 0) as used_quantity
        FROM products p
        ORDER BY used_quantity DESC
        LIMIT 4
    ");
    $response['data']['most_used_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Évolution du stock (entrées et sorties par mois)
    // Préparer les tableaux pour les entrées et sorties mensuelles
    $entries = array_fill(0, 12, 0);
    $outputs = array_fill(0, 12, 0);

    // Récupérer les entrées
    $stmt = $pdo->query("
        SELECT 
            MONTH(date) as month, 
            SUM(quantity) as total
        FROM stock_movement
        WHERE movement_type = 'entry'
        AND date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
        GROUP BY MONTH(date)
    ");
    $monthly_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($monthly_entries as $entry) {
        $month_index = intval($entry['month']) - 1; // Ajuster pour 0-indexed array
        $entries[$month_index] = intval($entry['total']);
    }

    // Récupérer les sorties (convertir les valeurs négatives en positives pour le graphique)
    $stmt = $pdo->query("
        SELECT 
            MONTH(date) as month, 
            SUM(ABS(quantity)) as total
        FROM stock_movement
        WHERE movement_type = 'output'
        AND date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
        GROUP BY MONTH(date)
    ");
    $monthly_outputs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($monthly_outputs as $output) {
        $month_index = intval($output['month']) - 1; // Ajuster pour 0-indexed array
        $outputs[$month_index] = intval($output['total']);
    }

    $response['data']['stock_evolution'] = [
        'entries' => $entries,
        'outputs' => $outputs
    ];

    // 5. Distribution par catégorie
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.libelle as name,
            COUNT(p.id) as count,
            SUM(p.quantity * p.unit_price) as value
        FROM categories c
        LEFT JOIN products p ON c.id = p.category
        GROUP BY c.id, c.libelle
        ORDER BY count DESC
    ");
    $response['data']['category_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($response);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => "Erreur de connexion à la base de données: " . $e->getMessage()
    ]);
}