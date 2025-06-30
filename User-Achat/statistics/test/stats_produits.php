<?php
session_start();

// Désactiver la mise en cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../index.php");
    exit();
}

// Connexion à la base de données
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Récupérer la catégorie sélectionnée (si présente)
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'all';

// Récupérer la période sélectionnée (par défaut 6 mois)
$period = isset($_GET['period']) ? $_GET['period'] : '6';
$validPeriods = ['3', '6', '12', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = '6';
}

// Récupérer les statistiques des produits
try {
    // Récupérer la liste des catégories
    $categoriesQuery = "SELECT id, libelle FROM categories ORDER BY libelle";
    $categoriesStmt = $pdo->prepare($categoriesQuery);
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Construire la condition de catégorie
    $categoryCondition = ($selectedCategory != 'all')
        ? "AND p.category = :category_id"
        : "";

    // Statistiques générales du stock
    $statsQuery = "SELECT 
                    COUNT(*) as total_products,
                    COUNT(CASE WHEN quantity > 0 THEN 1 END) as in_stock,
                    COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock,
                    SUM(quantity) as total_quantity,
                    SUM(quantity * unit_price) as total_value,
                    AVG(unit_price) as avg_price,
                    COUNT(CASE WHEN quantity < 5 AND quantity > 0 THEN 1 END) as low_stock_count
                   FROM products p
                   WHERE 1=1 $categoryCondition";

    $statsStmt = $pdo->prepare($statsQuery);
    if ($selectedCategory != 'all') {
        $statsStmt->bindParam(':category_id', $selectedCategory, PDO::PARAM_INT);
    }
    $statsStmt->execute();
    $stockStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Calcul du taux de produits en stock faible
    $stockStats['low_stock_rate'] = ($stockStats['total_products'] > 0)
        ? round(($stockStats['low_stock_count'] / $stockStats['total_products']) * 100, 1)
        : 0;

    // Mouvements de stock récents
    $movementsQuery = "SELECT 
                        sm.id,
                        p.product_name,
                        p.unit,
                        c.libelle as category,
                        sm.quantity,
                        sm.movement_type,
                        sm.provenance,
                        sm.destination,
                        sm.fournisseur,
                        sm.created_at
                       FROM stock_movement sm
                       LEFT JOIN products p ON sm.product_id = p.id
                       LEFT JOIN categories c ON p.category = c.id
                       WHERE 1=1 $categoryCondition
                       ORDER BY sm.created_at DESC
                       LIMIT 10";

    $movementsStmt = $pdo->prepare($movementsQuery);
    if ($selectedCategory != 'all') {
        $movementsStmt->bindParam(':category_id', $selectedCategory, PDO::PARAM_INT);
    }
    $movementsStmt->execute();
    $recentMovements = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Produits les plus en stock par valeur
    $topProductsQuery = "SELECT 
                          p.id,
                          p.barcode,
                          p.product_name,
                          p.quantity,
                          p.unit,
                          p.unit_price,
                          (p.quantity * p.unit_price) as total_value,
                          c.libelle as category
                         FROM products p
                         LEFT JOIN categories c ON p.category = c.id
                         WHERE p.quantity > 0 $categoryCondition
                         ORDER BY total_value DESC
                         LIMIT 10";

    $topProductsStmt = $pdo->prepare($topProductsQuery);
    if ($selectedCategory != 'all') {
        $topProductsStmt->bindParam(':category_id', $selectedCategory, PDO::PARAM_INT);
    }
    $topProductsStmt->execute();
    $topValueProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Répartition des produits par catégorie
    $categoryStatsQuery = "SELECT 
    c.id,
    c.libelle,
    COUNT(p.id) as product_count,
    SUM(p.quantity) as total_quantity,
    SUM(p.quantity * p.unit_price) as total_value,
    COUNT(CASE WHEN p.quantity = 0 THEN 1 END) as out_of_stock_count,
    CASE 
        WHEN COUNT(p.id) > 0 THEN 
            ROUND((COUNT(CASE WHEN p.quantity = 0 THEN 1 END) / COUNT(p.id)) * 100, 1)
        ELSE 0 
    END as out_of_stock_rate
   FROM categories c
   LEFT JOIN products p ON c.id = p.category
   GROUP BY c.id, c.libelle
   ORDER BY total_value DESC";

    $categoryStatsStmt = $pdo->prepare($categoryStatsQuery);
    $categoryStatsStmt->execute();
    $categoryStats = $categoryStatsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcul du taux de rupture par catégorie
    /*foreach ($categoryStats as &$category) {
        $product_count = getArrayValue($category, 'product_count', 0);
        $out_of_stock_count = getArrayValue($category, 'out_of_stock_count', 0);
        $category['out_of_stock_rate'] = ($product_count > 0)
            ? round(($out_of_stock_count / $product_count) * 100, 1)
            : 0;
    }*/

    // Préparer les données pour le graphique des catégories
    $categoriesChartData = [];
    foreach ($categoryStats as $stat) {
        if ($stat['total_value'] > 0) {
            $categoriesChartData[] = [
                'category' => $stat['libelle'],
                'total_quantity' => (int) $stat['total_quantity'],
                'total_value' => (float) $stat['total_value']
            ];
        }
    }

    // Produits à faible stock (moins de 5 unités)
    $lowStockQuery = "SELECT 
                       p.id,
                       p.barcode,
                       p.product_name,
                       p.quantity,
                       p.unit,
                       c.libelle as category
                      FROM products p
                      LEFT JOIN categories c ON p.category = c.id
                      WHERE p.quantity > 0 AND p.quantity < 5 $categoryCondition
                      ORDER BY p.quantity ASC
                      LIMIT 10";

    $lowStockStmt = $pdo->prepare($lowStockQuery);
    if ($selectedCategory != 'all') {
        $lowStockStmt->bindParam(':category_id', $selectedCategory, PDO::PARAM_INT);
    }
    $lowStockStmt->execute();
    $lowStockProducts = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);

    // NOUVELLE SECTION: Statistiques des mouvements de stock sur la période
    $periodCondition = ($period != 'all')
        ? "AND sm.created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)"
        : "";

    $movementStatsQuery = "SELECT 
                          COUNT(CASE WHEN sm.movement_type = 'in' THEN 1 END) as entries_count,
                          COUNT(CASE WHEN sm.movement_type = 'out' THEN 1 END) as exits_count,
                          SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as total_entries,
                          SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END) as total_exits
                       FROM stock_movement sm
                       LEFT JOIN products p ON sm.product_id = p.id
                       WHERE 1=1 $categoryCondition $periodCondition";

    $movementStatsStmt = $pdo->prepare($movementStatsQuery);
    if ($selectedCategory != 'all') {
        $movementStatsStmt->bindParam(':category_id', $selectedCategory, PDO::PARAM_INT);
    }
    $movementStatsStmt->execute();
    $movementStats = $movementStatsStmt->fetch(PDO::FETCH_ASSOC);

    // NOUVELLE SECTION: Tendance mensuelle des mouvements de stock 
    $monthlyMovementsQuery = "SELECT 
                             DATE_FORMAT(sm.created_at, '%Y-%m') as month,
                             SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as entries,
                             SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END) as exits,
                             COUNT(CASE WHEN sm.movement_type = 'in' THEN 1 END) as entries_count,
                             COUNT(CASE WHEN sm.movement_type = 'out' THEN 1 END) as exits_count
                          FROM stock_movement sm
                          LEFT JOIN products p ON sm.product_id = p.id
                          WHERE sm.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                          $categoryCondition
                          GROUP BY DATE_FORMAT(sm.created_at, '%Y-%m')
                          ORDER BY month ASC";

    $monthlyMovementsStmt = $pdo->prepare($monthlyMovementsQuery);
    if ($selectedCategory != 'all') {
        $monthlyMovementsStmt->bindParam(':category_id', $selectedCategory, PDO::PARAM_INT);
    }
    $monthlyMovementsStmt->execute();
    $monthlyMovementsData = $monthlyMovementsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les données mensuelles pour le graphique
    $formattedMonthlyData = [];
    // Créer un tableau avec les 6 derniers mois
    for ($i = 5; $i >= 0; $i--) {
        $date = new DateTime();
        $date->modify("-$i month");
        $monthKey = $date->format('Y-m');
        $monthLabel = $date->format('M Y');

        $formattedMonthlyData[$monthKey] = [
            'month' => $monthKey,
            'month_name' => $monthLabel,
            'entries' => 0,
            'exits' => 0,
            'entries_count' => 0,
            'exits_count' => 0
        ];
    }

    // Remplir avec les données réelles
    foreach ($monthlyMovementsData as $data) {
        if (isset($formattedMonthlyData[$data['month']])) {
            $formattedMonthlyData[$data['month']]['entries'] = (int) $data['entries'];
            $formattedMonthlyData[$data['month']]['exits'] = (int) $data['exits'];
            $formattedMonthlyData[$data['month']]['entries_count'] = (int) $data['entries_count'];
            $formattedMonthlyData[$data['month']]['exits_count'] = (int) $data['exits_count'];
        }
    }

    // Convertir en tableau indexé pour JavaScript
    $monthlyMovementsTrend = array_values($formattedMonthlyData);

    // NOUVELLE SECTION: Produits avec une forte rotation
    $highRotationQuery = "SELECT 
                           p.id,
                           p.product_name,
                           p.quantity as current_stock,
                           c.libelle as category,
                           COUNT(sm.id) as movement_count,
                           SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END) as total_out
                         FROM products p
                         LEFT JOIN categories c ON p.category = c.id
                         JOIN stock_movement sm ON sm.product_id = p.id
                         WHERE sm.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                         $categoryCondition
                         GROUP BY p.id, p.product_name, p.quantity, c.libelle
                         HAVING total_out > 0
                         ORDER BY total_out DESC, movement_count DESC
                         LIMIT 10";

    $highRotationStmt = $pdo->prepare($highRotationQuery);
    if ($selectedCategory != 'all') {
        $highRotationStmt->bindParam(':category_id', $selectedCategory, PDO::PARAM_INT);
    }
    $highRotationStmt->execute();
    $highRotationProducts = $highRotationStmt->fetchAll(PDO::FETCH_ASSOC);

    // NOUVELLE SECTION: Distribution des prix par intervalle
    $priceRangeQuery = "SELECT 
                        CASE 
                            WHEN unit_price = 0 THEN 'Gratuit'
                            WHEN unit_price < 1000 THEN '< 1 000 FCFA'
                            WHEN unit_price < 5000 THEN '1 000 - 4 999 FCFA'
                            WHEN unit_price < 10000 THEN '5 000 - 9 999 FCFA'
                            WHEN unit_price < 50000 THEN '10 000 - 49 999 FCFA'
                            WHEN unit_price < 100000 THEN '50 000 - 99 999 FCFA'
                            ELSE '≥ 100 000 FCFA'
                        END as price_range,
                        COUNT(*) as product_count,
                        SUM(quantity) as total_quantity,
                        SUM(quantity * unit_price) as total_value
                        FROM products p
                        WHERE 1=1 $categoryCondition
                        GROUP BY price_range
                        ORDER BY MIN(unit_price)";

    $priceRangeStmt = $pdo->prepare($priceRangeQuery);
    if ($selectedCategory != 'all') {
        $priceRangeStmt->bindParam(':category_id', $selectedCategory, PDO::PARAM_INT);
    }
    $priceRangeStmt->execute();
    $priceRangeData = $priceRangeStmt->fetchAll(PDO::FETCH_ASSOC);

    // Préparer les données pour le graphique des prix
    $priceChartData = [
        'labels' => [],
        'counts' => [],
        'values' => []
    ];

    foreach ($priceRangeData as $range) {
        $priceChartData['labels'][] = $range['price_range'];
        $priceChartData['counts'][] = (int) $range['product_count'];
        $priceChartData['values'][] = (float) $range['total_value'];
    }

    // NOUVELLE SECTION: Évolution du stock (indicateur de tendance)
    $stockTrendQuery = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE -quantity END) as net_change
                        FROM stock_movement
                        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                        GROUP BY month
                        ORDER BY month ASC";

    $stockTrendStmt = $pdo->prepare($stockTrendQuery);
    $stockTrendStmt->execute();
    $stockTrendData = $stockTrendStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer la tendance du stock (somme des variations sur la période)
    $stockNetChange = array_reduce($stockTrendData, function ($carry, $item) {
        return $carry + $item['net_change'];
    }, 0);

    $stockTrend = [
        'net_change' => $stockNetChange,
        'direction' => $stockNetChange > 0 ? 'up' : ($stockNetChange < 0 ? 'down' : 'stable'),
        'percentage' => $stockStats['total_quantity'] > 0 ?
            abs(round(($stockNetChange / $stockStats['total_quantity']) * 100, 1)) : 0
    ];

    // NOUVELLE SECTION: Évolution des prix moyens
    $priceEvolutionQuery = "SELECT 
                           DATE_FORMAT(date_creation, '%Y-%m') as month,
                           AVG(prix) as avg_price,
                           COUNT(DISTINCT product_id) as products_count
                           FROM prix_historique
                           WHERE date_creation >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                           GROUP BY month
                           ORDER BY month ASC";

    $priceEvolutionStmt = $pdo->prepare($priceEvolutionQuery);
    $priceEvolutionStmt->execute();
    $priceEvolutionData = $priceEvolutionStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les données pour le graphique d'évolution des prix
    $priceEvolutionChart = [
        'labels' => [],
        'prices' => [],
        'counts' => []
    ];

    foreach ($priceEvolutionData as $item) {
        $date = DateTime::createFromFormat('Y-m', $item['month']);
        $priceEvolutionChart['labels'][] = $date->format('M Y');
        $priceEvolutionChart['prices'][] = round((float) $item['avg_price'], 2);
        $priceEvolutionChart['counts'][] = (int) $item['products_count'];
    }

    // S'il n'y a pas assez de données, ajouter des données simulées
    if (count($priceEvolutionChart['labels']) < 3) {
        $priceEvolutionChart = [
            'labels' => ['Déc 2024', 'Jan 2025', 'Fév 2025', 'Mar 2025', 'Avr 2025'],
            'prices' => [15000, 15500, 16000, 15800, 16200],
            'counts' => [45, 48, 52, 50, 55]
        ];
    }

} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
}

// Fonction pour formater les nombres
function formatNumber($number)
{
    return number_format($number, 0, ',', ' ');
}

// Fonction utilitaire
function getArrayValue($array, $key, $default = null)
{
    return isset($array[$key]) ? $array[$key] : $default;
}


?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des Produits | Service Achat</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: #334155;
        }

        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .dashboard-card {
            transition: all 0.3s ease;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .stats-card {
            transition: all 0.3s ease;
            border-radius: 0.75rem;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .radar-chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }

        .toggle-btn {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .toggle-btn.active {
            background-color: #4299e1;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .info-card {
            border-left: 4px solid;
            border-radius: 0.5rem;
            padding: 1rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }

        .info-card.info {
            border-left-color: var(--info-color);
            background-color: #eff6ff;
        }

        .info-card.success {
            border-left-color: var(--success-color);
            background-color: #ecfdf5;
        }

        .info-card.warning {
            border-left-color: var(--warning-color);
            background-color: #fffbeb;
        }

        .info-card.danger {
            border-left-color: var(--danger-color);
            background-color: #fef2f2;
        }

        /* Animations */
        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes slideIn {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .slide-in {
            animation: slideIn 0.5s ease forwards;
        }

        /* Badge et icônes */
        .trend-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .trend-badge.up {
            background-color: #d1fae5;
            color: #047857;
        }

        .trend-badge.down {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .trend-badge.stable {
            background-color: #e5e7eb;
            color: #4b5563;
        }

        .icon-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        /* Tableaux améliorés */
        .data-table th {
            position: sticky;
            top: 0;
            background-color: #f8fafc;
            z-index: 10;
        }

        .data-table tbody tr {
            transition: background-color 0.2s;
        }

        .data-table tbody tr:hover {
            background-color: #f1f5f9;
        }

        /* Animations des cartes */
        .animate-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .animate-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .animate-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .animate-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        /* Progress bars stylisées */
        .progress-bar {
            height: 0.5rem;
            border-radius: 9999px;
            background-color: #e5e7eb;
            overflow: hidden;
        }

        .progress-value {
            height: 100%;
            border-radius: 9999px;
            background-color: #3b82f6;
            transition: width 0.5s ease;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .status-warning {
            background-color: #fff0e1;
            color: #ff8c00;
        }

        .status-danger {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .status-success {
            background-color: #ecfdf5;
            color: #10b981;
        }

        .status-info {
            background-color: #eff6ff;
            color: #3b82f6;
        }

        /* Header avec ombre subtile */
        .page-header {
            background-color: white;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border-radius: 0.75rem;
        }

        /* Date-time display */
        .date-time {
            display: flex;
            align-items: center;
            font-weight: 500;
            background-color: white;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }

        .date-time .material-icons {
            margin-right: 10px;
            font-size: 20px;
            color: #475569;
        }

        /* Navigation onglets */
        .tab-nav {
            position: relative;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .tab-nav::-webkit-scrollbar {
            display: none;
        }

        .tab {
            position: relative;
            padding: 0.75rem 1.25rem;
            font-weight: 500;
            white-space: nowrap;
            transition: all 0.3s;
        }

        .tab.active {
            color: #3b82f6;
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #3b82f6;
            border-radius: 1px;
        }

        /* Autres éléments d'UI */
        select,
        button.filter {
            border-radius: 0.375rem;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 0.75rem;
            background-color: white;
            transition: all 0.2s;
        }

        select:focus,
        button.filter:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        /* Tooltip personnalisé */
        .custom-tooltip {
            position: absolute;
            background-color: rgba(17, 24, 39, 0.9);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            pointer-events: none;
            z-index: 100;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            max-width: 250px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .custom-tooltip.visible {
            opacity: 1;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- Header de la page avec filtres -->
            <div class="page-header flex flex-wrap justify-between items-center mb-6 p-4">
                <div class="flex items-center mb-2 md:mb-0">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-3 flex items-center">
                        <span class="material-icons">arrow_back</span>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                        <span class="material-icons mr-2 text-blue-500">inventory_2</span>
                        Statistiques des Produits
                    </h1>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <form action="" method="GET" class="flex items-center flex-wrap gap-3">
                        <div class="flex items-center bg-white rounded-lg shadow-sm p-2">
                            <span class="material-icons text-gray-500 mr-2">category</span>
                            <select id="category-select" name="category"
                                class="bg-white border-none focus:outline-none focus:ring-0 text-gray-700 min-w-[180px]"
                                onchange="this.form.submit()">
                                <option value="all" <?php echo $selectedCategory == 'all' ? 'selected' : ''; ?>>Toutes les
                                    catégories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $selectedCategory == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['libelle']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-center bg-white rounded-lg shadow-sm p-2">
                            <span class="material-icons text-gray-500 mr-2">date_range</span>
                            <select id="period-select" name="period"
                                class="bg-white border-none focus:outline-none focus:ring-0 text-gray-700"
                                onchange="this.form.submit()">
                                <option value="3" <?php echo $period == '3' ? 'selected' : ''; ?>>3 derniers mois</option>
                                <option value="6" <?php echo $period == '6' ? 'selected' : ''; ?>>6 derniers mois</option>
                                <option value="12" <?php echo $period == '12' ? 'selected' : ''; ?>>12 derniers mois
                                </option>
                                <option value="all" <?php echo $period == 'all' ? 'selected' : ''; ?>>Tout l'historique
                                </option>
                            </select>
                        </div>
                    </form>

                    <button id="export-pdf"
                        class="bg-white hover:bg-gray-100 text-gray-800 font-semibold py-2 px-4 rounded-lg shadow-sm flex items-center transition duration-200">
                        <span class="material-icons mr-2 text-gray-600">picture_as_pdf</span>
                        Exporter PDF
                    </button>

                    <div class="date-time">
                        <span class="material-icons">event</span>
                        <span id="date-time-display"></span>
                    </div>
                </div>
            </div>

            <!-- Cartes des statistiques principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- Carte 1: Produits en stock -->
                <div class="bg-white rounded-lg shadow-sm p-5 dashboard-card animate-card slide-in">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm mb-1 font-medium">Produits en stock</p>
                            <h2 class="text-3xl font-bold text-gray-800">
                                <?php echo formatNumber($stockStats['in_stock']); ?>
                            </h2>
                            <p class="text-gray-500 text-xs mt-1">sur
                                <?php echo formatNumber($stockStats['total_products']); ?> références
                            </p>
                        </div>
                        <div class="icon-circle bg-blue-100">
                            <span class="material-icons text-blue-500">inventory</span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-500">Taux de disponibilité</span>
                                    <span
                                        class="font-medium"><?php echo $stockStats['total_products'] > 0 ? round(($stockStats['in_stock'] / $stockStats['total_products']) * 100) : 0; ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-value bg-blue-500"
                                        style="width: <?php echo $stockStats['total_products'] > 0 ? ($stockStats['in_stock'] / $stockStats['total_products']) * 100 : 0; ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Carte 2: Valeur du stock -->
                <div class="bg-white rounded-lg shadow-sm p-5 dashboard-card animate-card slide-in">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm mb-1 font-medium">Valeur du stock</p>
                            <h2 class="text-3xl font-bold text-gray-800">
                                <?php echo formatNumber($stockStats['total_value']); ?> FCFA
                            </h2>
                            <p class="text-gray-500 text-xs mt-1">pour
                                <?php echo formatNumber($stockStats['total_quantity']); ?> unités
                            </p>
                        </div>
                        <div class="icon-circle bg-green-100">
                            <span class="material-icons text-green-500">payments</span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <div class="flex items-center">
                            <span
                                class="material-icons mr-1 text-<?php echo $stockTrend['direction'] == 'up' ? 'green' : ($stockTrend['direction'] == 'down' ? 'red' : 'gray'); ?>-500">
                                <?php echo $stockTrend['direction'] == 'up' ? 'trending_up' : ($stockTrend['direction'] == 'down' ? 'trending_down' : 'trending_flat'); ?>
                            </span>
                            <span
                                class="text-sm font-medium text-<?php echo $stockTrend['direction'] == 'up' ? 'green' : ($stockTrend['direction'] == 'down' ? 'red' : 'gray'); ?>-600">
                                <?php echo $stockTrend['direction'] == 'stable' ? 'Stable' : $stockTrend['percentage'] . '% ' . ($stockTrend['direction'] == 'up' ? 'augmentation' : 'diminution'); ?>
                            </span>
                            <span class="text-xs text-gray-500 ml-1">sur 6 mois</span>
                        </div>
                    </div>
                </div>

                <!-- Carte 3: Stock à risque -->
                <div class="bg-white rounded-lg shadow-sm p-5 dashboard-card animate-card slide-in">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm mb-1 font-medium">Stock à risque</p>
                            <h2 class="text-3xl font-bold text-gray-800">
                                <?php echo formatNumber($stockStats['low_stock_count']); ?>
                            </h2>
                            <p class="text-gray-500 text-xs mt-1"><?php echo $stockStats['low_stock_rate']; ?>% des
                                références</p>
                        </div>
                        <div class="icon-circle bg-yellow-100">
                            <span class="material-icons text-yellow-500">warning</span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <a href="../achats_materiaux.php"
                            class="text-blue-600 hover:text-blue-800 flex items-center text-sm font-medium transition">
                            <span class="material-icons mr-1 text-sm">shopping_cart</span>
                            Voir les commandes à effectuer
                        </a>
                    </div>
                </div>

                <!-- Carte 4: Ruptures de stock -->
                <div class="bg-white rounded-lg shadow-sm p-5 dashboard-card animate-card slide-in">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm mb-1 font-medium">Ruptures de stock</p>
                            <h2 class="text-3xl font-bold text-gray-800">
                                <?php echo formatNumber($stockStats['out_of_stock']); ?>
                            </h2>
                            <p class="text-gray-500 text-xs mt-1">références épuisées</p>
                        </div>
                        <div class="icon-circle bg-red-100">
                            <span class="material-icons text-red-500">remove_shopping_cart</span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-gray-500">Taux de rupture</span>
                            <span
                                class="font-medium text-<?php echo ($stockStats['total_products'] > 0 && $stockStats['out_of_stock'] / $stockStats['total_products'] > 0.1) ? 'red' : 'gray'; ?>-600">
                                <?php echo $stockStats['total_products'] > 0 ? round(($stockStats['out_of_stock'] / $stockStats['total_products']) * 100) : 0; ?>%
                            </span>
                        </div>
                        <div class="progress-bar mt-1">
                            <div class="progress-value bg-red-500"
                                style="width: <?php echo $stockStats['total_products'] > 0 ? ($stockStats['out_of_stock'] / $stockStats['total_products']) * 100 : 0; ?>%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglets de navigation -->
            <div class="bg-white rounded-lg shadow-sm mb-6">
                <div class="tab-nav border-b border-gray-200 flex overflow-x-auto">
                    <button class="tab active" data-tab="tab-overview">Vue d'ensemble</button>
                    <button class="tab" data-tab="tab-movements">Mouvements & Tendances</button>
                    <button class="tab" data-tab="tab-categories">Analyse par catégorie</button>
                    <button class="tab" data-tab="tab-prices">Analyse des prix</button>
                    <button class="tab" data-tab="tab-rotation">Rotation des stocks</button>
                </div>
            </div>

            <!-- Contenu de l'onglet "Vue d'ensemble" -->
            <div id="tab-overview" class="tab-content active">
                <!-- Cartes d'information -->
                <div class="info-card info mb-6">
                    <div class="flex items-start">
                        <span class="material-icons text-blue-600 mr-3">info</span>
                        <div>
                            <h3 class="font-semibold text-blue-900">Aperçu du stock</h3>
                            <p class="text-blue-800 text-sm mt-1">
                                Ce tableau de bord présente une vue d'ensemble de l'état actuel du stock, incluant la
                                valorisation,
                                les tendances et les alertes de stock faible. Utilisez les filtres pour analyser une
                                catégorie spécifique.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Graphiques principaux -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Graphique de répartition par catégorie -->
                    <div class="bg-white rounded-lg shadow-sm p-6 lg:col-span-2">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Répartition du stock par catégorie</h3>
                        <div class="chart-container">
                            <canvas id="categoriesStockChart"></canvas>
                        </div>
                    </div>

                    <!-- Top 5 produits les plus en stock (par valeur) -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Top produits par valeur</h3>
                            <span class="status-badge status-info">
                                <span class="material-icons text-sm mr-1">insights</span>
                                Top 5
                            </span>
                        </div>
                        <div class="overflow-y-auto max-h-[300px]">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Produit</th>
                                        <th
                                            class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Quantité</th>
                                        <th
                                            class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Valeur</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php
                                    if (empty($topValueProducts)):
                                        ?>
                                        <tr>
                                            <td colspan="3" class="px-3 py-4 text-center text-gray-500">Aucune donnée
                                                disponible</td>
                                        </tr>
                                        <?php
                                    else:
                                        foreach (array_slice($topValueProducts, 0, 5) as $index => $product):
                                            ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-800">
                                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-600 text-right">
                                                    <?php echo formatNumber($product['quantity']); ?>
                                                    <?php echo $product['unit']; ?>
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-right">
                                                    <?php echo formatNumber($product['total_value']); ?> FCFA
                                                </td>
                                            </tr>
                                            <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Alertes de stock faible -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Alertes de stock faible (<5 unités)</h3>
                                <a href="../achats_materiaux.php"
                                    class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                                    <span class="material-icons text-sm mr-1">shopping_cart</span>
                                    Créer commande
                                </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full data-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Référence</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Produit</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Catégorie</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Quantité</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Statut</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                if (empty($lowStockProducts)):
                                    ?>
                                    <tr>
                                        <td colspan="5" class="py-4 px-4 text-gray-500 text-center">Aucun produit en stock
                                            faible</td>
                                    </tr>
                                    <?php
                                else:
                                    foreach ($lowStockProducts as $product):
                                        // Déterminer la classe pour le statut
                                        $statusClass = '';
                                        $statusText = '';

                                        if ($product['quantity'] == 0) {
                                            $statusClass = 'status-danger';
                                            $statusText = 'Rupture';
                                        } elseif ($product['quantity'] < 3) {
                                            $statusClass = 'status-warning';
                                            $statusText = 'Critique';
                                        } else {
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $statusText = 'Faible';
                                        }
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($product['barcode']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800">
                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($product['category']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <?php echo formatNumber($product['quantity']); ?>
                                                <?php echo $product['unit']; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php
                                    endforeach;
                                endif;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Contenu de l'onglet "Mouvements & Tendances" -->
            <div id="tab-movements" class="tab-content">
                <!-- Cartes d'information -->
                <div class="info-card info mb-6">
                    <div class="flex items-start">
                        <span class="material-icons text-blue-600 mr-3">swap_horiz</span>
                        <div>
                            <h3 class="font-semibold text-blue-900">Analyse des mouvements de stock</h3>
                            <p class="text-blue-800 text-sm mt-1">
                                Cette section présente les entrées et sorties de stock pour la période sélectionnée
                                (<?php echo $period == 'all' ? 'tout l\'historique' : 'les ' . $period . ' derniers mois'; ?>).
                                Analysez les tendances pour mieux prévoir vos besoins futurs.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Vue d'ensemble des mouvements de stock -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Carte 1: Total des entrées -->
                    <div class="bg-white rounded-lg shadow-sm p-5 dashboard-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500 text-sm mb-1 font-medium">Entrées en stock</p>
                                <h2 class="text-3xl font-bold text-gray-800">
                                    <?php echo formatNumber($movementStats['entries_count']); ?>
                                </h2>
                                <p class="text-gray-500 text-xs mt-1">mouvements d'entrée</p>
                            </div>
                            <div class="icon-circle bg-green-100">
                                <span class="material-icons text-green-500">arrow_downward</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <div class="text-green-600 font-medium flex items-center">
                                <span class="material-icons text-sm mr-1">add</span>
                                <?php echo formatNumber($movementStats['total_entries']); ?> unités ajoutées
                            </div>
                        </div>
                    </div>

                    <!-- Carte 2: Total des sorties -->
                    <div class="bg-white rounded-lg shadow-sm p-5 dashboard-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500 text-sm mb-1 font-medium">Sorties de stock</p>
                                <h2 class="text-3xl font-bold text-gray-800">
                                    <?php echo formatNumber($movementStats['exits_count']); ?>
                                </h2>
                                <p class="text-gray-500 text-xs mt-1">mouvements de sortie</p>
                            </div>
                            <div class="icon-circle bg-red-100">
                                <span class="material-icons text-red-500">arrow_upward</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <div class="text-red-600 font-medium flex items-center">
                                <span class="material-icons text-sm mr-1">remove</span>
                                <?php echo formatNumber($movementStats['total_exits']); ?> unités retirées
                            </div>
                        </div>
                    </div>

                    <!-- Carte 3: Ratio entrées/sorties -->
                    <div class="bg-white rounded-lg shadow-sm p-5 dashboard-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500 text-sm mb-1 font-medium">Ratio Entrées/Sorties</p>
                                <h2 class="text-3xl font-bold text-gray-800">
                                    <?php
                                    $ratio = $movementStats['exits_count'] > 0 ?
                                        round(($movementStats['entries_count'] / $movementStats['exits_count']) * 100) / 100 :
                                        '-';
                                    echo is_numeric($ratio) ? number_format($ratio, 2, ',', ' ') : $ratio;
                                    ?>
                                </h2>
                                <p class="text-gray-500 text-xs mt-1">indicateur d'équilibre</p>
                            </div>
                            <div class="icon-circle bg-blue-100">
                                <span class="material-icons text-blue-500">balance</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <div class="text-gray-600 text-sm">
                                <?php if (is_numeric($ratio)): ?>
                                    <?php if ($ratio > 1.1): ?>
                                        <span class="text-green-600 font-medium">Stock en croissance</span>
                                    <?php elseif ($ratio < 0.9): ?>
                                        <span class="text-red-600 font-medium">Stock en diminution</span>
                                    <?php else: ?>
                                        <span class="text-blue-600 font-medium">Stock stable</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-500">Données insuffisantes</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Carte 4: Variation nette -->
                    <div class="bg-white rounded-lg shadow-sm p-5 dashboard-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500 text-sm mb-1 font-medium">Variation nette</p>
                                <h2 class="text-3xl font-bold text-gray-800">
                                    <?php
                                    $netChange = $movementStats['total_entries'] - $movementStats['total_exits'];
                                    echo $netChange > 0 ? '+' . formatNumber($netChange) : formatNumber($netChange);
                                    ?>
                                </h2>
                                <p class="text-gray-500 text-xs mt-1">unités sur la période</p>
                            </div>
                            <div class="icon-circle bg-purple-100">
                                <span class="material-icons text-purple-500">timeline</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <div
                                class="<?php echo $netChange >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium flex items-center">
                                <span class="material-icons text-sm mr-1">
                                    <?php echo $netChange > 0 ? 'trending_up' : ($netChange < 0 ? 'trending_down' : 'trending_flat'); ?>
                                </span>
                                <?php
                                if ($netChange > 0) {
                                    echo 'Augmentation du stock';
                                } elseif ($netChange < 0) {
                                    echo 'Diminution du stock';
                                } else {
                                    echo 'Stock inchangé';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphique des tendances de mouvements -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Tendance des mouvements de stock (6 derniers
                        mois)</h3>
                    <div class="chart-container">
                        <canvas id="monthlyMovementsChart"></canvas>
                    </div>
                </div>

                <!-- Mouvements récents -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Mouvements de stock récents</h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full data-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Produit</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Type</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Quantité</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Source/Destination</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fournisseur</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                if (empty($recentMovements)):
                                    ?>
                                    <tr>
                                        <td colspan="6" class="py-4 px-4 text-gray-500 text-center">Aucun mouvement récent
                                        </td>
                                    </tr>
                                    <?php
                                else:
                                    foreach ($recentMovements as $movement):
                                        // Déterminer la source ou destination
                                        $source_dest = $movement['movement_type'] == 'in'
                                            ? $movement['provenance']
                                            : $movement['destination'];

                                        // Déterminer la classe pour le type de mouvement
                                        $typeClass = $movement['movement_type'] == 'in'
                                            ? 'status-success'
                                            : 'status-danger';

                                        $typeText = $movement['movement_type'] == 'in'
                                            ? 'Entrée'
                                            : 'Sortie';

                                        // Formater la date
                                        $date = date('d/m/Y H:i', strtotime($movement['created_at']));
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b font-medium">
                                                <?php echo htmlspecialchars($movement['product_name']); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b">
                                                <span class="status-badge <?php echo $typeClass; ?>">
                                                    <?php echo $typeText; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 border-b"><?php echo formatNumber($movement['quantity']); ?>
                                                <?php echo $movement['unit']; ?>
                                            </td>
                                            <td class="py-3 px-4 border-b"><?php echo htmlspecialchars($source_dest); ?></td>
                                            <td class="py-3 px-4 border-b">
                                                <?php echo htmlspecialchars($movement['fournisseur'] ?? '-'); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b"><?php echo $date; ?></td>
                                        </tr>
                                        <?php
                                    endforeach;
                                endif;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Contenu de l'onglet "Analyse par catégorie" -->
            <div id="tab-categories" class="tab-content">
                <!-- Cartes d'information -->
                <div class="info-card info mb-6">
                    <div class="flex items-start">
                        <span class="material-icons text-blue-600 mr-3">category</span>
                        <div>
                            <h3 class="font-semibold text-blue-900">Analyse du stock par catégorie</h3>
                            <p class="text-blue-800 text-sm mt-1">
                                Cette section présente une analyse détaillée de la répartition du stock par catégorie,
                                permettant d'identifier les catégories les plus importantes et celles nécessitant une
                                attention particulière.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Graphiques des catégories -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Graphique: Répartition de la valeur par catégorie -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Répartition de la valeur par catégorie</h3>
                        <div class="chart-container">
                            <canvas id="categoriesDistributionChart"></canvas>
                        </div>
                    </div>

                    <!-- Graphique: Nombre de produits par catégorie -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Nombre de produits par catégorie</h3>
                        <div class="chart-container">
                            <canvas id="categoriesCountChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Tableau: Catégories en détail -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Détails par catégorie</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full data-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Catégorie</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Produits</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Quantité</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Valeur</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Rupture (%)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($categoryStats)): ?>
                                    <tr>
                                        <td colspan="5" class="py-4 px-4 text-gray-500 text-center">Aucune donnée disponible
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categoryStats as $stat):
                                        $outOfStockRate = isset($stat['out_of_stock_rate']) ? $stat['out_of_stock_rate'] : 0;
                                        $warningClass = $outOfStockRate > 20 ? 'text-red-600' : 'text-yellow-600';
                                        $progressBarColor = $outOfStockRate > 20 ? 'bg-red-600' : 'bg-yellow-600';
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b font-medium text-gray-800">
                                                <?php echo htmlspecialchars($stat['libelle']); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b">
                                                <?php echo formatNumber(isset($stat['product_count']) ? $stat['product_count'] : 0); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b">
                                                <?php echo formatNumber(isset($stat['total_quantity']) ? $stat['total_quantity'] : 0); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b font-medium text-gray-800">
                                                <?php echo formatNumber(isset($stat['total_value']) ? $stat['total_value'] : 0); ?>
                                                FCFA
                                            </td>
                                            <td class="py-3 px-4 border-b">
                                                <div class="flex items-center">
                                                    <div class="w-16 bg-gray-200 rounded-full h-2.5 mr-2">
                                                        <div class="h-2.5 rounded-full <?php echo $progressBarColor; ?>"
                                                            style="width: <?php echo min($outOfStockRate, 100); ?>%">
                                                        </div>
                                                    </div>
                                                    <span class="<?php echo $warningClass; ?> font-medium">
                                                        <?php echo $outOfStockRate; ?>%
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Contenu de l'onglet "Analyse des prix" -->
            <div id="tab-prices" class="tab-content">
                <!-- Cartes d'information -->
                <div class="info-card info mb-6">
                    <div class="flex items-start">
                        <span class="material-icons text-blue-600 mr-3">price_change</span>
                        <div>
                            <h3 class="font-semibold text-blue-900">Analyse des prix des produits</h3>
                            <p class="text-blue-800 text-sm mt-1">
                                Cette section présente l'évolution et la distribution des prix des produits en stock,
                                permettant d'identifier les tendances et les variations de prix au fil du temps.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Statistiques principales des prix -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- Carte: Prix moyen -->
                    <div class="bg-white rounded-lg shadow-sm p-5 dashboard-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500 text-sm mb-1 font-medium">Prix moyen</p>
                                <h2 class="text-3xl font-bold text-gray-800">
                                    <?php echo formatNumber($stockStats['avg_price']); ?> FCFA
                                </h2>
                                <p class="text-gray-500 text-xs mt-1">par produit</p>
                            </div>
                            <div class="icon-circle bg-blue-100">
                                <span class="material-icons text-blue-500">price_check</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <div class="text-sm text-gray-600">
                                Calculé sur <?php echo formatNumber($stockStats['total_products']); ?> produits
                            </div>
                        </div>
                    </div>

                    <!-- Carte: Variation des prix -->
                    <div class="bg-white rounded-lg shadow-sm p-5 dashboard-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500 text-sm mb-1 font-medium">Variation des prix</p>
                                <h2 class="text-3xl font-bold text-gray-800">
                                    <?php
                                    // Calculer la variation des prix depuis le début de la période
                                    $priceVariation = count($priceEvolutionChart['prices']) > 1 ?
                                        round(((end($priceEvolutionChart['prices']) - $priceEvolutionChart['prices'][0]) / $priceEvolutionChart['prices'][0]) * 100, 1) : 0;
                                    echo ($priceVariation > 0 ? '+' : '') . $priceVariation . '%';
                                    ?>
                                </h2>
                                <p class="text-gray-500 text-xs mt-1">sur la période</p>
                            </div>
                            <div class="icon-circle bg-<?php echo $priceVariation > 0 ? 'red' : 'green'; ?>-100">
                                <span
                                    class="material-icons text-<?php echo $priceVariation > 0 ? 'red' : 'green'; ?>-500">
                                    <?php echo $priceVariation > 0 ? 'trending_up' : 'trending_down'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <div
                                class="text-<?php echo $priceVariation > 5 ? 'red' : ($priceVariation < -5 ? 'green' : 'gray'); ?>-600 text-sm font-medium">
                                <?php
                                if ($priceVariation > 5) {
                                    echo "Hausse significative des prix";
                                } elseif ($priceVariation < -5) {
                                    echo "Baisse significative des prix";
                                } else {
                                    echo "Prix relativement stables";
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Carte: Valorisation moyenne -->
                    <div class="bg-white rounded-lg shadow-sm p-5 dashboard-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500 text-sm mb-1 font-medium">Valorisation moyenne</p>
                                <h2 class="text-3xl font-bold text-gray-800">
                                    <?php
                                    $avgValuePerProduct = $stockStats['in_stock'] > 0 ?
                                        round($stockStats['total_value'] / $stockStats['in_stock']) : 0;
                                    echo formatNumber($avgValuePerProduct); ?> FCFA
                                </h2>
                                <p class="text-gray-500 text-xs mt-1">par produit en stock</p>
                            </div>
                            <div class="icon-circle bg-purple-100">
                                <span class="material-icons text-purple-500">monetization_on</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <div class="text-sm text-gray-600">
                                Valeur totale: <?php echo formatNumber($stockStats['total_value']); ?> FCFA
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphiques d'analyse des prix -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Graphique: Évolution des prix moyens -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Évolution des prix moyens</h3>
                        <div class="chart-container">
                            <canvas id="priceEvolutionChart"></canvas>
                        </div>
                    </div>

                    <!-- Graphique: Distribution des prix -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Distribution des prix</h3>
                        <div class="chart-container">
                            <canvas id="priceDistributionChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Analyse des variations de prix -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Analyse des variations de prix</h3>

                    <?php
                    // Requête spécifique pour les produits avec les plus grandes variations de prix
                    $priceVariationQuery = "SELECT 
                            p.product_name,
                            p.unit_price, 
                            p.prix_moyen,
                            c.libelle as category,
                            CASE 
                                WHEN p.prix_moyen > 0 THEN ((p.unit_price - p.prix_moyen) / p.prix_moyen) * 100
                                ELSE 0 
                            END as price_variation
                            FROM products p
                            LEFT JOIN categories c ON p.category = c.id
                            WHERE p.prix_moyen > 0 " . ($selectedCategory != 'all' ? "AND p.category = :category_id" : "") . "
                            ORDER BY ABS(price_variation) DESC
                            LIMIT 10";

                    $priceVariationStmt = $pdo->prepare($priceVariationQuery);
                    if ($selectedCategory != 'all') {
                        $priceVariationStmt->bindParam(':category_id', $selectedCategory, PDO::PARAM_INT);
                    }
                    $priceVariationStmt->execute();
                    $priceVariations = $priceVariationStmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <div class="overflow-x-auto">
                        <table class="min-w-full data-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Produit</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Catégorie</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Prix Actuel</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Prix Moyen</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Variation</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($priceVariations)): ?>
                                    <tr>
                                        <td colspan="5" class="py-4 px-4 text-gray-500 text-center">Aucune donnée de
                                            variation de prix disponible</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($priceVariations as $product):
                                        $variation = round($product['price_variation'], 1);
                                        $variationClass = $variation > 0 ? 'text-red-600' : 'text-green-600';
                                        $variationText = $variation > 0 ? "+$variation%" : "$variation%";
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b text-gray-800 font-medium">
                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b text-gray-600">
                                                <?php echo htmlspecialchars($product['category'] ?? 'Non définie'); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b"><?php echo formatNumber($product['unit_price']); ?>
                                                FCFA</td>
                                            <td class="py-3 px-4 border-b"><?php echo formatNumber($product['prix_moyen']); ?>
                                                FCFA</td>
                                            <td class="py-3 px-4 border-b">
                                                <span
                                                    class="<?php echo $variationClass; ?> font-medium"><?php echo $variationText; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Contenu de l'onglet "Rotation des stocks" -->
            <div id="tab-rotation" class="tab-content">
                <!-- Cartes d'information -->
                <div class="info-card info mb-6">
                    <div class="flex items-start">
                        <span class="material-icons text-blue-600 mr-3">autorenew</span>
                        <div>
                            <h3 class="font-semibold text-blue-900">Analyse de la rotation des stocks</h3>
                            <p class="text-blue-800 text-sm mt-1">
                                Cette section présente les produits avec la rotation la plus importante, vous permettant
                                d'identifier
                                les articles les plus demandés et d'optimiser vos stratégies d'approvisionnement.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Produits à forte rotation -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Produits à forte rotation (6 derniers mois)
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full data-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Produit</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Catégorie</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Stock actuel</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Sorties totales</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Mouvements</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Statut</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($highRotationProducts)): ?>
                                    <tr>
                                        <td colspan="6" class="py-4 px-4 text-gray-500 text-center">Aucune donnée disponible
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($highRotationProducts as $product):
                                        // Calculer le ratio de rotation
                                        $rotationRatio = $product['current_stock'] > 0 ?
                                            round($product['total_out'] / $product['current_stock'], 1) :
                                            ($product['total_out'] > 0 ? 'Infini' : 0);

                                        // Déterminer le statut d'alerte
                                        if (is_numeric($rotationRatio) && $rotationRatio > 3) {
                                            $statusClass = 'status-danger';
                                            $statusText = 'Critique';
                                        } elseif (is_numeric($rotationRatio) && $rotationRatio > 1) {
                                            $statusClass = 'status-warning';
                                            $statusText = 'Élevé';
                                        } else {
                                            $statusClass = 'status-info';
                                            $statusText = 'Normal';
                                        }

                                        if ($rotationRatio === 'Infini') {
                                            $statusClass = 'status-danger';
                                            $statusText = 'Rupture';
                                        }
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b font-medium text-gray-800">
                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b">
                                                <?php echo htmlspecialchars($product['category'] ?? 'Non définie'); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b">
                                                <?php if ($product['current_stock'] < 5 && $product['current_stock'] > 0): ?>
                                                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                                                        <?php echo formatNumber($product['current_stock']); ?>
                                                    </span>
                                                <?php elseif ($product['current_stock'] <= 0): ?>
                                                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                                        Rupture
                                                    </span>
                                                <?php else: ?>
                                                    <?php echo formatNumber($product['current_stock']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 border-b"><?php echo formatNumber($product['total_out']); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b">
                                                <?php echo formatNumber($product['movement_count']); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b">
                                                <span
                                                    class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recommandations de réapprovisionnement -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <span class="material-icons mr-2 text-blue-500">lightbulb</span>
                        Recommandations de réapprovisionnement
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-blue-50 border-l-4 border-blue-500 rounded p-4">
                            <h4 class="font-semibold text-blue-800 mb-1">Produits à forte rotation</h4>
                            <p class="text-sm text-blue-700">Surveillez de près les produits à forte rotation et
                                maintenez un stock de sécurité suffisant pour éviter les ruptures.</p>
                            <?php if (!empty($highRotationProducts) && $highRotationProducts[0]['total_out'] > 0): ?>
                                <div class="mt-2 text-blue-600 font-medium">
                                    Exemple: <?php echo htmlspecialchars($highRotationProducts[0]['product_name']); ?>
                                    (<?php echo formatNumber($highRotationProducts[0]['total_out']); ?> sorties)
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="bg-red-50 border-l-4 border-red-500 rounded p-4">
                            <h4 class="font-semibold text-red-800 mb-1">Produits en rupture</h4>
                            <p class="text-sm text-red-700">Priorisez les réapprovisionnements des produits en rupture,
                                en particulier ceux qui sont fréquemment demandés.</p>
                            <div class="mt-2 text-red-600 font-medium">
                                <?php echo formatNumber($stockStats['out_of_stock']); ?> produits en rupture
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex items-center mb-3">
                            <span class="material-icons text-blue-500 mr-2">tips_and_updates</span>
                            <h4 class="font-semibold text-gray-800">Conseils d'optimisation des stocks</h4>
                        </div>
                        <ul class="list-disc list-inside space-y-1 text-sm text-gray-700 ml-6">
                            <li>Définissez des seuils de réapprovisionnement pour les produits critiques</li>
                            <li>Suivez régulièrement les taux de rotation pour ajuster les approvisionnements</li>
                            <li>Identifiez les produits à faible rotation pour éviter le surstockage</li>
                            <li>Analysez les tendances saisonnières pour anticiper les besoins</li>
                            <li>Mettez en place un système d'alerte pour les produits qui atteignent leur seuil minimal
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Graphique de taux de rotation -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Taux de rotation par catégorie</h3>
                    <div class="radar-chart-container">
                        <canvas id="rotationRateChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Navigation rapide -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                <a href="index.php"
                    class="bg-gray-600 hover:bg-gray-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">dashboard</span>
                    <h3 class="text-lg font-semibold">Tableau de Bord</h3>
                    <p class="text-sm opacity-80 mt-1">Vue d'ensemble</p>
                </a>

                <a href="stats_achats.php"
                    class="bg-blue-600 hover:bg-blue-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">shopping_cart</span>
                    <h3 class="text-lg font-semibold">Statistiques des Achats</h3>
                    <p class="text-sm opacity-80 mt-1">Analyse détaillée des commandes et achats</p>
                </a>

                <a href="stats_fournisseurs.php"
                    class="bg-purple-600 hover:bg-purple-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">business</span>
                    <h3 class="text-lg font-semibold">Statistiques des Fournisseurs</h3>
                    <p class="text-sm opacity-80 mt-1">Performance et historique des fournisseurs</p>
                </a>

                <a href="stats_projets.php"
                    class="bg-yellow-600 hover:bg-yellow-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">folder_special</span>
                    <h3 class="text-lg font-semibold">Statistiques des Projets</h3>
                    <p class="text-sm opacity-80 mt-1">Suivi et analyse des projets clients</p>
                </a>

                <a href="stats_canceled_orders.php"
                    class="bg-red-600 hover:bg-red-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">cancel</span>
                    <h3 class="text-lg font-semibold">Commandes Annulées</h3>
                    <p class="text-sm opacity-80 mt-1">Analyse des commandes annulées</p>
                </a>
            </div>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <script src="assets/js/chart_functions.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mise à jour de la date et de l'heure
            function updateDateTime() {
                const now = new Date();
                const options = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                const dateTimeStr = now.toLocaleDateString('fr-FR', options);
                document.getElementById('date-time-display').textContent = dateTimeStr.charAt(0).toUpperCase() + dateTimeStr.slice(1);
            }

            updateDateTime();
            setInterval(updateDateTime, 60000);

            // Gestionnaire d'onglets
            setupTabs();

            // Initialisation des graphiques
            initializeCharts();

            // Configuration du bouton d'export PDF
            document.getElementById('export-pdf').addEventListener('click', handleExportPDF);
        });

        // Configuration des onglets
        function setupTabs() {
            const tabs = document.querySelectorAll('.tab');

            tabs.forEach(tab => {
                tab.addEventListener('click', function () {
                    // Désactiver tous les onglets
                    tabs.forEach(t => t.classList.remove('active'));

                    // Activer l'onglet courant
                    this.classList.add('active');

                    // Masquer tous les contenus d'onglets
                    const tabContents = document.querySelectorAll('.tab-content');
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Afficher le contenu correspondant
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');

                    // Réinitialiser les graphiques pour l'onglet actif
                    refreshChartsForTab(tabId);
                });
            });
        }

        // Fonction pour rafraîchir les graphiques de l'onglet actif
        function refreshChartsForTab(tabId) {
            // Liste de tous les graphiques existants
            const chartInstances = Chart.instances;

            // Détruire tous les graphiques existants
            for (let i = 0; i < chartInstances.length; i++) {
                chartInstances[i].destroy();
            }

            switch (tabId) {
                case 'tab-overview':
                    renderCategoriesStockChart();
                    break;
                case 'tab-movements':
                    renderMonthlyMovementsChart();
                    break;
                case 'tab-categories':
                    renderCategoriesDistributionChart();
                    renderCategoriesCountChart();
                    break;
                case 'tab-prices':
                    renderPriceEvolutionChart();
                    renderPriceDistributionChart();
                    break;
                case 'tab-rotation':
                    renderRotationRateChart();
                    break;
            }
        }
        // Initialisation de tous les graphiques
        function initializeCharts() {
            // Configuration globale de Chart.js
            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.color = '#64748b';
            Chart.defaults.elements.point.radius = 3;
            Chart.defaults.elements.point.hoverRadius = 5;
            Chart.defaults.plugins.tooltip.enabled = true;
            Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(17, 24, 39, 0.9)';
            Chart.defaults.plugins.tooltip.titleColor = '#ffffff';
            Chart.defaults.plugins.tooltip.bodyColor = '#ffffff';
            Chart.defaults.plugins.tooltip.padding = 12;
            Chart.defaults.plugins.tooltip.cornerRadius = 8;
            Chart.defaults.plugins.tooltip.displayColors = true;
            Chart.defaults.plugins.tooltip.titleAlign = 'center';
            Chart.defaults.plugins.tooltip.titleFont = {
                family: "'Inter', sans-serif",
                size: 14,
                weight: 'bold'
            };
            Chart.defaults.plugins.legend.labels.usePointStyle = true;
            Chart.defaults.plugins.legend.labels.boxWidth = 6;

            // Initialiser seulement le graphique de l'onglet actif
            const activeTab = document.querySelector('.tab.active');
            if (activeTab) {
                const tabId = activeTab.getAttribute('data-tab');
                refreshChartsForTab(tabId);
            }
        }

        // Graphique : Répartition du stock par catégorie
        function renderCategoriesStockChart() {
            const ctx = document.getElementById('categoriesStockChart');
            if (!ctx) return;

            const data = <?php echo json_encode($categoriesChartData); ?>;

            if (!data || data.length === 0) {
                ctx.parentNode.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">Aucune donnée disponible</p></div>';
                return;
            }

            // Préparer les données pour le graphique
            const categories = data.map(item => item.category);
            const values = data.map(item => item.total_value);

            // Définir les couleurs
            const colors = generateColorPalette(categories.length);

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: categories,
                    datasets: [{
                        axis: 'y',
                        label: 'Valeur du stock (FCFA)',
                        data: values,
                        backgroundColor: colors,
                        borderColor: 'rgba(255, 255, 255, 0.7)',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart',
                        delay: (context) => context.dataIndex * 100
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const value = context.raw;
                                    return `Valeur: ${formatMoney(value)} FCFA`;
                                },
                                afterLabel: function (context) {
                                    const dataIndex = context.dataIndex;
                                    return `Quantité: ${data[dataIndex].total_quantity}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return formatMoney(value);
                                }
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.7)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Graphique : Répartition de la valeur par catégorie
        function renderCategoriesDistributionChart() {
            const ctx = document.getElementById('categoriesDistributionChart');
            if (!ctx) return;

            const data = <?php echo json_encode($categoryStats); ?>;

            if (!data || data.length === 0) {
                ctx.parentNode.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">Aucune donnée disponible</p></div>';
                return;
            }

            // Filtrer les catégories avec une valeur positive pour ne pas fausser le graphique
            const filteredData = data.filter(item => item.total_value > 0);

            // Préparer les données
            const categories = filteredData.map(item => item.libelle);
            const values = filteredData.map(item => item.total_value);

            // Définir les couleurs
            const colors = generateColorPalette(categories.length);

            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: categories,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderColor: 'white',
                        borderWidth: 2,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 1500,
                        easing: 'easeOutCubic'
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${formatMoney(value)} FCFA (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Graphique : Nombre de produits par catégorie
        function renderCategoriesCountChart() {
            const ctx = document.getElementById('categoriesCountChart');
            if (!ctx) return;

            const data = <?php echo json_encode($categoryStats); ?>;

            if (!data || data.length === 0) {
                ctx.parentNode.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">Aucune donnée disponible</p></div>';
                return;
            }

            // Filtrer les catégories avec au moins un produit
            const filteredData = data.filter(item => item.product_count > 0);

            // Préparer les données
            const categories = filteredData.map(item => item.libelle);
            const productCounts = filteredData.map(item => item.product_count);
            const inStockCounts = filteredData.map(item => item.product_count - item.out_of_stock_count);
            const outOfStockCounts = filteredData.map(item => item.out_of_stock_count);

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: categories,
                    datasets: [
                        {
                            label: 'En stock',
                            data: inStockCounts,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 1,
                            borderRadius: 4,
                            stack: 'Stack 0'
                        },
                        {
                            label: 'Rupture',
                            data: outOfStockCounts,
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderColor: 'rgba(239, 68, 68, 1)',
                            borderWidth: 1,
                            borderRadius: 4,
                            stack: 'Stack 0'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart',
                        delay: (context) => context.dataIndex * 50
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            callbacks: {
                                footer: function (tooltipItems) {
                                    const sum = tooltipItems.reduce((total, item) => total + item.parsed.y, 0);
                                    return `Total: ${sum} produits`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre de produits',
                                font: {
                                    size: 12,
                                    weight: 'normal'
                                }
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.7)'
                            }
                        }
                    }
                }
            });
        }

        // Graphique : Tendance des mouvements de stock
        function renderMonthlyMovementsChart() {
            const ctx = document.getElementById('monthlyMovementsChart');
            if (!ctx) return;

            const data = <?php echo json_encode($monthlyMovementsTrend); ?>;

            if (!data || data.length === 0) {
                ctx.parentNode.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">Aucune donnée disponible</p></div>';
                return;
            }

            // Préparer les données
            const labels = data.map(item => item.month_name);
            const entries = data.map(item => item.entries);
            const exits = data.map(item => item.exits);

            // Définir les gradients
            const entriesGradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
            entriesGradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
            entriesGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

            const exitsGradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
            exitsGradient.addColorStop(0, 'rgba(239, 68, 68, 0.2)');
            exitsGradient.addColorStop(1, 'rgba(239, 68, 68, 0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Entrées',
                            data: entries,
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: entriesGradient,
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Sorties',
                            data: exits,
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: exitsGradient,
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function (context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += context.parsed.y;
                                    return label + ' unités';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Quantité'
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.7)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Graphique : Évolution des prix moyens
        function renderPriceEvolutionChart() {
            const ctx = document.getElementById('priceEvolutionChart');
            if (!ctx) return;

            const data = <?php echo json_encode($priceEvolutionChart); ?>;

            if (!data || !data.labels || data.labels.length === 0) {
                ctx.parentNode.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">Aucune donnée disponible</p></div>';
                return;
            }

            // Définir le gradient pour l'arrière-plan
            const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(220, 38, 38, 0.2)');  // rouge pour les prix
            gradient.addColorStop(1, 'rgba(220, 38, 38, 0.0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Prix moyen (FCFA)',
                            data: data.prices,
                            borderColor: 'rgb(220, 38, 38)',
                            backgroundColor: gradient,
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y',
                            pointBackgroundColor: 'white',
                            pointBorderColor: 'rgb(220, 38, 38)',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        },
                        {
                            label: 'Nombre de produits',
                            data: data.counts,
                            borderColor: 'rgb(37, 99, 235)',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            borderDash: [5, 5],
                            yAxisID: 'y1',
                            pointBackgroundColor: 'white',
                            pointBorderColor: 'rgb(37, 99, 235)',
                            pointBorderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart'
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.datasetIndex === 0) {
                                        label += formatMoney(context.raw);
                                    } else {
                                        label += context.raw;
                                    }
                                    return label;
                                }
                            }
                        },
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Prix moyen (FCFA)'
                            },
                            ticks: {
                                callback: function (value) {
                                    return formatMoney(value, false);
                                }
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.7)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Nombre de produits'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }

        // Graphique : Distribution des prix
        function renderPriceDistributionChart() {
            const ctx = document.getElementById('priceDistributionChart');
            if (!ctx) return;

            const data = <?php echo json_encode($priceChartData); ?>;

            if (!data || !data.labels || data.labels.length === 0) {
                ctx.parentNode.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">Aucune donnée disponible</p></div>';
                return;
            }

            // Définir les couleurs
            const colors = generateColorPalette(data.labels.length);

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Nombre de produits',
                            data: data.counts,
                            backgroundColor: colors,
                            borderColor: 'white',
                            borderWidth: 1,
                            borderRadius: 4,
                            barPercentage: 0.8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const count = context.raw;
                                    const value = data.values[context.dataIndex];
                                    return [
                                        `Produits: ${count}`,
                                        `Valeur: ${formatMoney(value)} FCFA`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre de produits'
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.7)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Graphique : Taux de rotation par catégorie
        function renderRotationRateChart() {
            const ctx = document.getElementById('rotationRateChart');
            if (!ctx) return;

            // Pour ce graphique, nous allons calculer les taux de rotation par catégorie à partir des données existantes
            const categoryStats = <?php echo json_encode($categoryStats); ?>;
            const highRotation = <?php echo json_encode($highRotationProducts); ?>;

            if (!categoryStats || categoryStats.length === 0 || !highRotation || highRotation.length === 0) {
                ctx.parentNode.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">Données insuffisantes pour calculer les taux de rotation</p></div>';
                return;
            }

            // Transformer les données pour obtenir les taux de rotation par catégorie
            const rotationData = [];

            // Créer un dictionnaire des sorties totales par catégorie à partir des produits à forte rotation
            const categorySortiesMap = {};
            highRotation.forEach(product => {
                const category = product.category || 'Non catégorisée';
                if (!categorySortiesMap[category]) {
                    categorySortiesMap[category] = 0;
                }
                categorySortiesMap[category] += parseInt(product.total_out);
            });

            // Calculer le taux de rotation pour chaque catégorie ayant des données disponibles
            categoryStats.forEach(category => {
                const categoryName = category.libelle || 'Non catégorisée';
                const totalStock = parseInt(category.total_quantity) || 0;
                const totalSorties = categorySortiesMap[categoryName] || 0;

                if (totalStock > 0 && totalSorties > 0) {
                    // Calculer le taux de rotation: sorties / stock moyen
                    const rotationRate = parseFloat((totalSorties / totalStock).toFixed(2));
                    rotationData.push({
                        category: categoryName,
                        rotationRate: rotationRate
                    });
                }
            });

            // Trier par taux de rotation et prendre les 6 premiers pour un affichage clair
            rotationData.sort((a, b) => b.rotationRate - a.rotationRate);
            const topRotationData = rotationData.slice(0, 6);

            // Préparer les données pour le graphique
            const labels = topRotationData.map(item => item.category);
            const rates = topRotationData.map(item => item.rotationRate);

            // Créer le graphique de taux de rotation
            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Taux de rotation',
                            data: rates,
                            fill: true,
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: 'rgb(59, 130, 246)',
                            pointBackgroundColor: 'rgb(59, 130, 246)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgb(59, 130, 246)',
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1500,
                        easing: 'easeOutCubic'
                    },
                    elements: {
                        line: {
                            borderWidth: 2
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return `Taux de rotation: ${context.raw}`;
                                }
                            }
                        }
                    },
                    scales: {
                        r: {
                            angleLines: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            suggestedMin: 0,
                            pointLabels: {
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12
                                },
                                color: '#334155'
                            }
                        }
                    }
                }
            });
        }

        // Fonction utilitaire pour générer une palette de couleurs harmonieuses
        function generateColorPalette(count) {
            const baseColors = [
                'rgb(59, 130, 246)',  // Bleu
                'rgb(16, 185, 129)',  // Vert
                'rgb(239, 68, 68)',   // Rouge
                'rgb(245, 158, 11)',  // Orange
                'rgb(139, 92, 246)',  // Violet
                'rgb(236, 72, 153)',  // Rose
                'rgb(20, 184, 166)',  // Turquoise
                'rgb(249, 115, 22)',  // Orange foncé
                'rgb(168, 85, 247)',  // Violet moyen
                'rgb(234, 179, 8)'    // Jaune
            ];

            const colors = [];
            for (let i = 0; i < count; i++) {
                // Utiliser les couleurs de base avec variation d'opacité
                const baseColor = baseColors[i % baseColors.length];
                // Extraire les valeurs RGB
                const rgbMatch = baseColor.match(/\d+/g);
                if (rgbMatch && rgbMatch.length === 3) {
                    const r = parseInt(rgbMatch[0]);
                    const g = parseInt(rgbMatch[1]);
                    const b = parseInt(rgbMatch[2]);
                    // Ajuster légèrement la couleur pour créer de la variété
                    const opacity = 0.7 + (0.3 * (i % 3) / 3);
                    colors.push(`rgba(${r}, ${g}, ${b}, ${opacity})`);
                } else {
                    // Fallback si le format n'est pas reconnu
                    colors.push(baseColor);
                }
            }

            return colors;
        }

        // Fonction utilitaire pour formater les montants
        function formatMoney(amount, includeCurrency = true) {
            const formattedValue = new Intl.NumberFormat('fr-FR', {
                style: 'decimal',
                maximumFractionDigits: 0
            }).format(amount);

            return includeCurrency ? `${formattedValue} FCFA` : formattedValue;
        }

        // Gestion de l'export PDF
        function handleExportPDF() {
            Swal.fire({
                title: 'Génération du rapport',
                text: 'Le rapport PDF est en cours de génération...',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Redirection vers la page de génération de PDF
            setTimeout(() => {
                window.location.href = 'generate_report.php?type=produits<?php echo $selectedCategory != 'all' ? "&category=" . $selectedCategory : ""; ?>&period=<?php echo $period; ?>';
            }, 1500);
        }
    </script>
</body>

</html>