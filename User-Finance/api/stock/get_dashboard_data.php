<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentification requise'
    ]);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Structure de la réponse
    $dashboardData = [
        'categories' => [],
        'movements' => [],
        'topProducts' => [],
        'alerts' => [],
        'recommendations' => []
    ];

    // 1. Récupérer les données de catégories pour le graphique
    $categoriesQuery = "SELECT 
                            c.id,
                            c.libelle as category,
                            c.code,
                            COUNT(p.id) as count,
                            SUM(p.quantity) as quantity
                        FROM categories c
                        LEFT JOIN products p ON c.id = p.category
                        GROUP BY c.id, c.libelle, c.code
                        ORDER BY quantity DESC";
                        // LIMIT 10";
    $categoriesStmt = $pdo->query($categoriesQuery);
    $dashboardData['categories'] = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Récupérer les données des mouvements récents (7 derniers jours)
    $movementsQuery = "SELECT 
                          DATE_FORMAT(date, '%Y-%m-%d') as day,
                          DATE_FORMAT(date, '%d/%m') as day_display,
                          DAYNAME(date) as day_name,
                          SUM(CASE WHEN movement_type = 'entry' THEN quantity ELSE 0 END) as entries,
                          SUM(CASE WHEN movement_type = 'output' THEN quantity ELSE 0 END) as outputs,
                          SUM(CASE WHEN movement_type = 'entry' THEN quantity ELSE -quantity END) as total
                      FROM stock_movement
                      WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      GROUP BY DATE_FORMAT(date, '%Y-%m-%d'), DATE_FORMAT(date, '%d/%m'), DAYNAME(date)
                      ORDER BY DATE_FORMAT(date, '%Y-%m-%d') ASC";
    $movementsStmt = $pdo->query($movementsQuery);
    $movements = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Compléter avec des jours vides si nécessaire pour avoir 7 jours
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = new DateTime();
        $date->modify("-$i days");
        $dayKey = $date->format('Y-m-d');
        $dayDisplay = $date->format('d/m');
        $dayName = $date->format('l'); // Nom du jour en anglais

        // Traduire le nom du jour en français
        $frenchDays = [
            'Monday' => 'Lundi',
            'Tuesday' => 'Mardi',
            'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi',
            'Friday' => 'Vendredi',
            'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche'
        ];
        $dayNameFr = $frenchDays[$dayName] ?? $dayName;

        $days[$dayKey] = [
            'day' => $dayKey,
            'day_display' => $dayDisplay,
            'day_name' => $dayNameFr,
            'entries' => 0,
            'outputs' => 0,
            'total' => 0
        ];
    }

    // Fusionner avec les données réelles
    foreach ($movements as $movement) {
        if (isset($days[$movement['day']])) {
            $days[$movement['day']] = $movement;
        }
    }

    $dashboardData['movements'] = array_values($days);

    // 3. Récupérer les produits les plus stockés
    $topProductsQuery = "SELECT 
                            p.id,
                            p.product_name as name,
                            p.quantity,
                            c.libelle as category
                         FROM products p
                         LEFT JOIN categories c ON p.category = c.id
                         WHERE p.quantity > 0
                         ORDER BY p.quantity DESC
                         LIMIT 5";
    $topProductsStmt = $pdo->query($topProductsQuery);
    $dashboardData['topProducts'] = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Générer les alertes de stock
    // 4.1 Produits en rupture
    $ruptureSql = "SELECT 
                      p.id,
                      p.product_name as name,
                      p.quantity,
                      c.libelle as category
                   FROM products p
                   LEFT JOIN categories c ON p.category = c.id
                   WHERE p.quantity = 0
                   ORDER BY p.product_name
                   LIMIT 3";
    $ruptureStmt = $pdo->query($ruptureSql);
    $ruptureProducts = $ruptureStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ruptureProducts as $product) {
        $dashboardData['alerts'][] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'quantity' => 0,
            'category' => $product['category'],
            'type' => 'rupture',
            'priority' => 'high'
        ];
    }

    // 4.2 Produits en stock critique (moins de 3 unités)
    $critiqueSql = "SELECT 
                       p.id,
                       p.product_name as name,
                       p.quantity,
                       c.libelle as category
                    FROM products p
                    LEFT JOIN categories c ON p.category = c.id
                    WHERE p.quantity > 0 AND p.quantity < 3
                    ORDER BY p.quantity
                    LIMIT " . (5 - count($dashboardData['alerts']));
    $critiqueStmt = $pdo->query($critiqueSql);
    $critiqueProducts = $critiqueStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($critiqueProducts as $product) {
        $dashboardData['alerts'][] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'quantity' => $product['quantity'],
            'category' => $product['category'],
            'type' => 'faible',
            'priority' => 'medium'
        ];
    }

    // 5. Générer des recommandations basées sur l'état du stock
    // 5.1 Compter les produits par niveau de stock
    $stockCountsQuery = "SELECT 
                            SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as rupture_count,
                            SUM(CASE WHEN quantity > 0 AND quantity < 3 THEN 1 ELSE 0 END) as critique_count,
                            SUM(CASE WHEN quantity >= 3 AND quantity <= 10 THEN 1 ELSE 0 END) as faible_count
                         FROM products";
    $stockCountsStmt = $pdo->query($stockCountsQuery);
    $stockCounts = $stockCountsStmt->fetch(PDO::FETCH_ASSOC);

    // 5.2 Générer des recommandations en fonction des comptages
    if ($stockCounts['rupture_count'] > 0) {
        $dashboardData['recommendations'][] = [
            'title' => "Commander d'urgence les {$stockCounts['rupture_count']} produits en rupture",
            'description' => "Ces produits sont actuellement indisponibles et pourraient affecter les projets en cours.",
            'priority' => 'high'
        ];
    }

    if ($stockCounts['critique_count'] > 0) {
        $dashboardData['recommendations'][] = [
            'title' => "Planifier le réapprovisionnement de {$stockCounts['critique_count']} produits critiques",
            'description' => "Ces produits ont un niveau de stock très bas (moins de 3 unités) et risquent d'être bientôt en rupture.",
            'priority' => 'medium'
        ];
    }

    if ($stockCounts['faible_count'] > 0) {
        $dashboardData['recommendations'][] = [
            'title' => "Surveiller {$stockCounts['faible_count']} produits à stock faible",
            'description' => "Ces produits ont un niveau de stock entre 3 et 10 unités. Prévoir leur réapprovisionnement à moyen terme.",
            'priority' => 'low'
        ];
    }

    // 5.3 Ajouter une recommandation générale
    $dashboardData['recommendations'][] = [
        'title' => "Analyser les tendances de consommation",
        'description' => "Étudier les mouvements récents pour optimiser les niveaux de stock et anticiper les besoins futurs.",
        'priority' => 'low'
    ];

    // Limiter à 3 recommandations maximum
    if (count($dashboardData['recommendations']) > 3) {
        // Garder les 3 recommandations prioritaires
        usort($dashboardData['recommendations'], function ($a, $b) {
            $priority = ['high' => 0, 'medium' => 1, 'low' => 2];
            return $priority[$a['priority']] <=> $priority[$b['priority']];
        });

        $dashboardData['recommendations'] = array_slice($dashboardData['recommendations'], 0, 3);
    }

    // Renvoyer les données du tableau de bord
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'categories' => $dashboardData['categories'],
        'movements' => $dashboardData['movements'],
        'topProducts' => $dashboardData['topProducts'],
        'alerts' => $dashboardData['alerts'],
        'recommendations' => $dashboardData['recommendations']
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
    exit();
}
?>