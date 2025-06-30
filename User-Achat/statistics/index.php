<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Rediriger vers index.php
    header("Location: ./../../index.php");
    exit();
}

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

require_once '../../components/config.php';
$base_url = PROJECT_ROOT;
// Connexion à la base de données
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Fonction pour formater les nombres
function formatNumber($number)
{
    return number_format($number, 0, ',', ' ');
}

// Récupérer les statistiques globales
try {
    // Total des produits en stock
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE quantity > 0");
    $stmt->execute();
    $totalProducts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Valeur totale du stock
    $stmt = $pdo->prepare("SELECT SUM(quantity * unit_price) as total_value FROM products WHERE quantity > 0");
    $stmt->execute();
    $totalStockValue = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

    // Nombre de mouvements de stock du mois courant
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM stock_movement WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->execute();
    $currentMonthMovements = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Nombre de fournisseurs actifs
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM fournisseurs");
    $stmt->execute();
    $totalSuppliers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Prix moyen des produits
    $stmt = $pdo->prepare("SELECT AVG(prix_moyen) as avg_price FROM products WHERE prix_moyen IS NOT NULL AND prix_moyen > 0");
    $stmt->execute();
    $avgPrice = $stmt->fetch(PDO::FETCH_ASSOC)['avg_price'] ?? 0;

    // Évolution des prix moyens (comparaison avec le mois précédent)
    $stmt = $pdo->prepare("SELECT 
                          AVG(ph1.prix) as current_avg, 
                          AVG(ph2.prix) as previous_avg
                          FROM prix_historique ph1 
                          LEFT JOIN prix_historique ph2 ON ph1.product_id = ph2.product_id
                          WHERE 
                            MONTH(ph1.date_creation) = MONTH(CURRENT_DATE()) 
                            AND YEAR(ph1.date_creation) = YEAR(CURRENT_DATE())
                            AND MONTH(ph2.date_creation) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
                            AND YEAR(ph2.date_creation) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))");
    $stmt->execute();
    $priceEvolution = $stmt->fetch(PDO::FETCH_ASSOC);

    $priceEvolutionPercentage = 0;
    if ($priceEvolution['previous_avg'] > 0) {
        $priceEvolutionPercentage = (($priceEvolution['current_avg'] - $priceEvolution['previous_avg']) / $priceEvolution['previous_avg']) * 100;
    }

    // Achats du mois en cours
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(prix_unitaire * quantity), 0) as total 
                          FROM achats_materiaux 
                          WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
                          AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->execute();
    $currentMonthPurchases = $stmt->fetch(PDO::FETCH_ASSOC);

    // Nombre de commandes en attente
    $stmt = $pdo->prepare("SELECT COUNT(*) as count 
                          FROM expression_dym 
                          WHERE qt_acheter > 0 
                          AND (valide_achat = 'pas validé' OR valide_achat IS NULL)
                          AND " . getFilteredDateCondition());
    $stmt->execute();
    $pendingOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Récupérer le top 5 des produits les plus achetés (en valeur)
    $topProductsQuery = "SELECT 
                         designation,
                         COUNT(*) as order_count,
                         SUM(quantity) as total_quantity,
                         COALESCE(SUM(prix_unitaire * quantity), 0) as total_amount
                        FROM achats_materiaux
                        WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
                        GROUP BY designation
                        ORDER BY total_amount DESC
                        LIMIT 5";

    $topProductsStmt = $pdo->prepare($topProductsQuery);
    $topProductsStmt->execute();
    $topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les données pour l'évolution annuelle des achats
    $yearlyQuery = "SELECT 
                    MONTH(created_at) as month,
                    COUNT(*) as count,
                    COALESCE(SUM(prix_unitaire * quantity), 0) as total
                    FROM achats_materiaux
                    WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
                    GROUP BY MONTH(created_at)
                    ORDER BY month ASC";

    $yearlyStmt = $pdo->prepare($yearlyQuery);
    $yearlyStmt->execute();
    $yearlyData = $yearlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les données pour le graphique annuel
    $yearlyChartData = [
        'labels' => [],
        'values' => [],
        'counts' => []
    ];

    // Générer les 12 mois avec valeurs par défaut
    $monthNames = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre'
    ];

    for ($i = 1; $i <= 12; $i++) {
        $yearlyChartData['labels'][] = $monthNames[$i];
        $yearlyChartData['values'][] = 0;
        $yearlyChartData['counts'][] = 0;
    }

    // Remplir avec les données réelles
    foreach ($yearlyData as $data) {
        $month = (int) $data['month'];
        if ($month >= 1 && $month <= 12) {
            $yearlyChartData['values'][$month - 1] = (float) $data['total'];
            $yearlyChartData['counts'][$month - 1] = (int) $data['count'];
        }
    }

    // Annulations récentes
    $canceledQuery = "SELECT COUNT(*) as count FROM expression_dym WHERE valide_achat = 'annulé'";
    $canceledStmt = $pdo->prepare($canceledQuery);
    $canceledStmt->execute();
    $canceledCount = $canceledStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Récupérer les données pour la comparaison trimestrielle
    $quarterlyQuery = "SELECT 
                        QUARTER(created_at) as quarter,
                        COUNT(*) as orders,
                        COALESCE(SUM(prix_unitaire * quantity), 0) as amount
                        FROM achats_materiaux
                        WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
                        GROUP BY QUARTER(created_at)
                        ORDER BY quarter";

    $quarterlyStmt = $pdo->prepare($quarterlyQuery);
    $quarterlyStmt->execute();
    $quarterlyData = $quarterlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les données trimestrielles
    $quarterlyChartData = [
        'labels' => ['T1', 'T2', 'T3', 'T4'],
        'values' => [0, 0, 0, 0],
        'counts' => [0, 0, 0, 0]
    ];

    // Remplir avec les données réelles
    foreach ($quarterlyData as $data) {
        $quarter = (int) $data['quarter'];
        if ($quarter >= 1 && $quarter <= 4) {
            $quarterlyChartData['values'][$quarter - 1] = (float) $data['amount'];
            $quarterlyChartData['counts'][$quarter - 1] = (int) $data['orders'];
        }
    }

    // Répartition des achats par catégorie
    $categoryQuery = "SELECT 
                        c.libelle as category, 
                        COUNT(am.id) as count,
                        COALESCE(SUM(am.prix_unitaire * am.quantity), 0) as total
                    FROM achats_materiaux am
                    LEFT JOIN products p ON am.designation = p.product_name
                    LEFT JOIN categories c ON p.category = c.id
                    WHERE am.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    GROUP BY c.libelle
                    ORDER BY total DESC";

    $categoryStmt = $pdo->prepare($categoryQuery);
    $categoryStmt->execute();
    $categoryData = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les données pour le graphique de catégories
    $catChartData = [
        'labels' => [],
        'values' => [],
        'colors' => []
    ];

    // Couleurs pour les différentes catégories
    $colors = [
        '#4361ee',
        '#3a0ca3',
        '#7209b7',
        '#f72585',
        '#4cc9f0',
        '#4895ef',
        '#560bad',
        '#f15bb5',
        '#00bbf9',
        '#00f5d4',
        '#ff9e00',
        '#ff0054',
        '#390099',
        '#ffbd00',
        '#a2d2ff'
    ];

    $colorIndex = 0;
    foreach ($categoryData as $cat) {
        $catLabel = $cat['category'] ?: 'Non catégorisé';
        $catChartData['labels'][] = $catLabel;
        $catChartData['values'][] = (float) $cat['total'];
        $catChartData['colors'][] = $colors[$colorIndex % count($colors)];
        $colorIndex++;
    }

    // Récupérer les données des délais de livraison moyens par fournisseur
    $deliveryTimeQuery = "SELECT 
                            f.nom as supplier,
                            AVG(DATEDIFF(COALESCE(am.date_reception, CURDATE()), am.date_achat)) as avg_days
                        FROM achats_materiaux am
                        JOIN fournisseurs f ON am.fournisseur = f.nom
                        WHERE am.date_reception IS NOT NULL
                        AND am.date_achat IS NOT NULL
                        AND DATEDIFF(am.date_reception, am.date_achat) > 0
                        AND am.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                        GROUP BY f.nom
                        ORDER BY avg_days ASC
                        LIMIT 10";

    $deliveryTimeStmt = $pdo->prepare($deliveryTimeQuery);
    $deliveryTimeStmt->execute();
    $deliveryTimeData = $deliveryTimeStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les données de délai de livraison
    $deliveryChartData = [
        'labels' => [],
        'values' => []
    ];

    foreach ($deliveryTimeData as $supplier) {
        $deliveryChartData['labels'][] = $supplier['supplier'];
        $deliveryChartData['values'][] = round($supplier['avg_days'], 1);
    }

    // Vérifier si la table supplier_returns existe
    $checkTableQuery = "SHOW TABLES LIKE 'supplier_returns'";
    $checkTableStmt = $pdo->prepare($checkTableQuery);
    $checkTableStmt->execute();
    $supplierReturnsTableExists = $checkTableStmt->rowCount() > 0;

    if ($supplierReturnsTableExists) {
        // Récupérer les statistiques des retours fournisseurs
        $retourFournisseursQuery = "SELECT 
            COUNT(*) as count,
            COALESCE(SUM(sr.quantity * 
                (SELECT AVG(prix_unitaire) FROM expression_dym WHERE designation = p.product_name)
            ), 0) as total_amount
        FROM supplier_returns sr
        JOIN products p ON sr.product_id = p.id
        WHERE " . getFilteredDateCondition('sr.created_at');

        $retourFournisseursStmt = $pdo->prepare($retourFournisseursQuery);
        $retourFournisseursStmt->execute();
        $retourFournisseurs = $retourFournisseursStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $retourFournisseurs = [
            'count' => 0,
            'total_amount' => 0
        ];
    }

    // Récupérer les motifs de retour
    if ($supplierReturnsTableExists) {
        $returnReasonsQuery = "SELECT 
                                reason,
                                COUNT(*) as count
                            FROM supplier_returns
                            WHERE " . getFilteredDateCondition('created_at') . "
                            GROUP BY reason
                            ORDER BY count DESC";

        $returnReasonsStmt = $pdo->prepare($returnReasonsQuery);
        $returnReasonsStmt->execute();
        $returnReasons = $returnReasonsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Formater pour graphique en anneau
        $returnReasonsChart = [
            'labels' => [],
            'values' => [],
            'colors' => ['#f94144', '#f3722c', '#f8961e', '#f9c74f', '#90be6d', '#43aa8b', '#577590']
        ];

        foreach ($returnReasons as $idx => $reason) {
            $returnReasonsChart['labels'][] = $reason['reason'];
            $returnReasonsChart['values'][] = (int) $reason['count'];
        }
    } else {
        $returnReasons = [];
        $returnReasonsChart = [
            'labels' => [],
            'values' => [],
            'colors' => []
        ];
    }

    // Récupérer les statistiques sur les commandes annulées depuis expression_dym
    try {
        $canceledOrdersQuery = "SELECT 
            COUNT(*) as total_canceled,
            COUNT(DISTINCT idExpression) as projects_count,
            MAX(updated_at) as last_canceled_date
        FROM expression_dym
        WHERE valide_achat = 'annulé'";

        $canceledOrdersStmt = $pdo->prepare($canceledOrdersQuery);
        $canceledOrdersStmt->execute();
        $canceledOrdersStats = $canceledOrdersStmt->fetch(PDO::FETCH_ASSOC);

        // Si aucun résultat, initialiser avec des valeurs par défaut
        if (!$canceledOrdersStats) {
            $canceledOrdersStats = [
                'total_canceled' => 0,
                'projects_count' => 0,
                'last_canceled_date' => null
            ];
        }
    } catch (PDOException $e) {
        $canceledOrdersStats = [
            'total_canceled' => 0,
            'projects_count' => 0,
            'last_canceled_date' => null
        ];
    }

} catch (PDOException $e) {
    // Gérer l'erreur
    error_log("Erreur lors de la récupération des statistiques : " . $e->getMessage());
    $totalProducts = 0;
    $totalStockValue = 0;
    $currentMonthMovements = 0;
    $totalSuppliers = 0;
    $currentMonthPurchases = ['count' => 0, 'total' => 0];
    $pendingOrders = 0;
    $topProducts = [];
    $yearlyChartData = ['labels' => [], 'values' => [], 'counts' => []];
    $quarterlyChartData = ['labels' => [], 'values' => [], 'counts' => []];
    $catChartData = ['labels' => [], 'values' => [], 'colors' => []];
    $deliveryChartData = ['labels' => [], 'values' => []];
    $canceledCount = 0;
    $retourFournisseurs = ['count' => 0, 'total_amount' => 0];
    $returnReasonsChart = ['labels' => [], 'values' => [], 'colors' => []];
    $canceledOrdersStats = [
        'total_canceled' => 0,
        'projects_count' => 0,
        'last_canceled_date' => null
    ];
}

// Récupérer l'année sélectionnée depuis l'URL ou utiliser l'année courante
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$currentYear = date('Y');

// Liste des années pour le filtre
$yearRange = range($currentYear - 4, $currentYear);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Statistiques | Service Achat</title>

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        /* Custom styles */
        :root {
            --primary-color: #4361ee;
            --secondary-color: #7209b7;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-color: #f3f4f6;
            --dark-color: #1f2937;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #334155;
            background-color: #f1f5f9;
        }

        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .dashboard-card {
            transition: all 0.3s ease;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .stats-icon {
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            margin-bottom: 1rem;
        }

        .radar-chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }

        .mini-chart-container {
            position: relative;
            height: 80px;
            width: 120px;
        }

        /* Tableau styling */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table th {
            background-color: #f8fafc;
            padding: 0.75rem 1rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .data-table tbody tr:hover {
            background-color: #f1f5f9;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .badge-success {
            background-color: #d1fae5;
            color: #047857;
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #b45309;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .badge-info {
            background-color: #dbeafe;
            color: #1d4ed8;
        }

        /* Animation */
        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
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

        /* Progress bars */
        .progress-bar {
            height: 0.5rem;
            border-radius: 9999px;
            background-color: #e2e8f0;
            overflow: hidden;
        }

        .progress-value {
            height: 100%;
            border-radius: 9999px;
        }

        /* Tabs */
        .tab {
            cursor: pointer;
            padding: 0.75rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Year filter styling */
        .year-filter {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }

        .year-filter select {
            appearance: none;
            background-color: transparent;
            border: none;
            padding: 0.25rem 0.5rem;
            margin: 0;
            color: #334155;
            font-weight: 500;
            cursor: pointer;
            outline: none;
            font-size: 0.875rem;
        }

        .year-filter-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-right: 0.5rem;
        }

        /* Info Card */
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

        /* KPI Grid cards */
        .kpi-card {
            text-align: left;
            padding: 1.25rem;
            border-radius: 1rem;
            background-color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .kpi-card .title {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .kpi-card .value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }

        .kpi-card .trend {
            display: flex;
            align-items: center;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .kpi-card .trend.up {
            color: #10b981;
        }

        .kpi-card .trend.down {
            color: #ef4444;
        }

        .kpi-card .trend.neutral {
            color: #64748b;
        }

        .kpi-card .icon {
            position: absolute;
            right: 1.25rem;
            top: 1.25rem;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .kpi-card .icon.blue {
            background-color: #dbeafe;
            color: #3b82f6;
        }

        .kpi-card .icon.green {
            background-color: #d1fae5;
            color: #10b981;
        }

        .kpi-card .icon.amber {
            background-color: #fef3c7;
            color: #f59e0b;
        }

        .kpi-card .icon.purple {
            background-color: #ede9fe;
            color: #8b5cf6;
        }

        .kpi-card .icon.red {
            background-color: #fee2e2;
            color: #ef4444;
        }

        .kpi-card .icon.pink {
            background-color: #fce7f3;
            color: #ec4899;
        }

        .kpi-card .icon .material-icons {
            font-size: 1.5rem;
        }

        .kpi-card .mini-chart {
            position: absolute;
            right: 1.25rem;
            bottom: 1.25rem;
            width: 100px;
            height: 50px;
        }

        /* Advanced charts styling */
        .chart-title {
            font-weight: 600;
            font-size: 1rem;
            color: #334155;
            margin-bottom: 1rem;
        }

        .chart-card {
            border-radius: 1rem;
            background-color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Dashboard header */
        .dashboard-header {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .dashboard-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .dashboard-header h1 .material-icons {
            margin-right: 0.75rem;
            color: var(--primary-color);
        }

        /* Category chip */
        .category-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        /* Action button */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }

        .action-btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .action-btn-primary:hover {
            background-color: #2d4ed8;
        }

        .action-btn-secondary {
            background-color: white;
            color: var(--dark-color);
            border: 1px solid #e2e8f0;
        }

        .action-btn-secondary:hover {
            background-color: #f8fafc;
        }

        .action-btn .material-icons {
            margin-right: 0.5rem;
            font-size: 1.25rem;
        }

        /* Calendar widget */
        .calendar-widget {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.25rem;
        }

        /* Data card */
        .data-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.25rem;
            height: 100%;
            transition: all 0.3s ease;
        }

        .data-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .data-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .data-card-title {
            font-weight: 600;
            font-size: 1rem;
            color: #334155;
        }

        .data-card-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: #f1f5f9;
            color: #64748b;
        }

        .data-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .data-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-list-item:last-child {
            border-bottom: none;
        }

        .data-list-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: #334155;
        }

        .data-list-value {
            font-weight: 600;
            font-size: 0.875rem;
            color: #1e293b;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                        <span class="material-icons mr-3">dashboard</span>
                        Tableau de Bord Statistiques
                    </h1>
                    <div class="year-filter ml-8">
                        <span class="year-filter-label">Année :</span>
                        <form id="yearForm" method="get" class="inline">
                            <select id="year-select" name="year" onchange="this.form.submit()">
                                <?php foreach ($yearRange as $year): ?>
                                <option value="<?= $year ?>" <?= $selectedYear == $year ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <button id="export-pdf" class="action-btn action-btn-secondary">
                        <span class="material-icons">picture_as_pdf</span>
                        Exporter PDF
                    </button>

                    <div class="date-time">
                        <span class="material-icons mr-2">event</span>
                        <span id="date-time-display"></span>
                    </div>
                </div>
            </div>

            <!-- Info Card -->
            <div class="info-card info mb-6">
                <div class="flex items-start">
                    <span class="material-icons text-blue-600 mr-3">info</span>
                    <div>
                        <h3 class="font-semibold text-blue-900">Tableau de bord du Service Achat</h3>
                        <p class="text-blue-800 text-sm mt-1">
                            Cette interface présente une vue d'ensemble des activités d'achat, incluant les tendances,
                            les performances des fournisseurs, et l'analyse des coûts pour l'année <?= $selectedYear ?>.
                            Utilisez les filtres pour affiner votre analyse.
                        </p>
                    </div>
                </div>
            </div>

            <!-- KPI Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- KPI 1: Total des produits en stock -->
                <div class="kpi-card">
                    <div class="icon blue">
                        <span class="material-icons">inventory</span>
                    </div>
                    <div class="title">Produits en stock</div>
                    <div class="value"><?= formatNumber($totalProducts) ?></div>
                    <div class="trend neutral">
                        <span class="material-icons text-sm mr-1">info</span>
                        Références actives
                    </div>
                </div>

                <!-- KPI 2: Valeur du stock -->
                <div class="kpi-card">
                    <div class="icon green">
                        <span class="material-icons">payments</span>
                    </div>
                    <div class="title">Valeur du stock</div>
                    <div class="value"><?= formatNumber($totalStockValue) ?> FCFA</div>
                    <div class="trend neutral">
                        <span class="material-icons text-sm mr-1">inventory_2</span>
                        Valorisation totale
                    </div>
                </div>

                <!-- KPI 3: Commandes en attente -->
                <div class="kpi-card">
                    <div class="icon amber">
                        <span class="material-icons">shopping_cart</span>
                    </div>
                    <div class="title">Commandes en attente</div>
                    <div class="value"><?= formatNumber($pendingOrders) ?></div>
                    <div class="trend <?= $pendingOrders > 10 ? 'up' : 'neutral' ?>">
                        <span
                            class="material-icons text-sm mr-1"><?= $pendingOrders > 10 ? 'trending_up' : 'trending_flat' ?></span>
                        À traiter
                    </div>
                </div>

                <!-- KPI 4: Achats du mois -->
                <div class="kpi-card">
                    <div class="icon purple">
                        <span class="material-icons">receipt_long</span>
                    </div>
                    <div class="title">Achats du mois</div>
                    <div class="value"><?= formatNumber($currentMonthPurchases['total']) ?> FCFA</div>
                    <div class="trend neutral">
                        <span class="material-icons text-sm mr-1">receipt</span>
                        <?= $currentMonthPurchases['count'] ?> commandes
                    </div>
                </div>
            </div>

            <!-- Second KPI Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- KPI 5: Mouvements de stock -->
                <div class="kpi-card">
                    <div class="icon blue">
                        <span class="material-icons">sync_alt</span>
                    </div>
                    <div class="title">Mouvements de stock</div>
                    <div class="value"><?= formatNumber($currentMonthMovements) ?></div>
                    <div class="trend neutral">
                        <span class="material-icons text-sm mr-1">date_range</span>
                        Ce mois-ci
                    </div>
                </div>

                <!-- KPI 6: Total fournisseurs -->
                <div class="kpi-card">
                    <div class="icon green">
                        <span class="material-icons">business</span>
                    </div>
                    <div class="title">Fournisseurs</div>
                    <div class="value"><?= formatNumber($totalSuppliers) ?></div>
                    <div class="trend neutral">
                        <span class="material-icons text-sm mr-1">group</span>
                        Partenaires actifs
                    </div>
                </div>

                <!-- KPI 7: Retours fournisseurs -->
                <div class="kpi-card">
                    <div class="icon pink">
                        <span class="material-icons">assignment_return</span>
                    </div>
                    <div class="title">Retours fournisseurs</div>
                    <div class="value"><?= formatNumber($retourFournisseurs['count']) ?></div>
                    <div class="trend neutral">
                        <span class="material-icons text-sm mr-1">monetization_on</span>
                        <?= formatNumber($retourFournisseurs['total_amount']) ?> FCFA
                    </div>
                </div>

                <!-- KPI 8: Commandes annulées -->
                <div class="kpi-card">
                    <div class="icon red">
                        <span class="material-icons">cancel</span>
                    </div>
                    <div class="title">Commandes annulées</div>
                    <div class="value"><?= formatNumber($canceledOrdersStats['total_canceled']) ?></div>
                    <div class="trend neutral">
                        <span class="material-icons text-sm mr-1">event_busy</span>
                        <?= $canceledOrdersStats['projects_count'] ?> projets concernés
                    </div>
                </div>
            </div>

            <!-- Main content grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Chart 1: Évolution des achats annuels -->
                <div class="chart-card lg:col-span-2">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="chart-title">Évolution des achats <?= $selectedYear ?></h3>
                        <div class="flex space-x-2">
                            <div class="badge badge-info flex items-center">
                                <span class="material-icons text-sm mr-1">trending_up</span>
                                Mensuel
                            </div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="purchasesYearlyChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Répartition par catégorie -->
                <div class="chart-card">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="chart-title">Répartition par catégorie</h3>
                        <div class="badge badge-success flex items-center">
                            <span class="material-icons text-sm mr-1">pie_chart</span>
                            Top catégories
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="categoriesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Second row of charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Chart 3: Comparaison trimestrielle -->
                <div class="chart-card">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="chart-title">Comparaison trimestrielle <?= $selectedYear ?></h3>
                        <div class="badge badge-info flex items-center">
                            <span class="material-icons text-sm mr-1">bar_chart</span>
                            Trimestriel
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="quarterlyChart"></canvas>
                    </div>
                </div>

                <!-- Chart 4: Délai moyen de livraison par fournisseur -->
                <div class="chart-card">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="chart-title">Délai moyen de livraison (jours)</h3>
                        <div class="badge badge-warning flex items-center">
                            <span class="material-icons text-sm mr-1">schedule</span>
                            Top 10 fournisseurs
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="deliveryTimeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Third row - Data cards and charts -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Top 5 Produits -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Top 5 Produits Achetés</h3>
                        <div class="data-card-badge">
                            <span class="material-icons text-sm mr-1">trending_up</span>
                            Par valeur
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table w-full">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th class="text-right">Quantité</th>
                                    <th class="text-right">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topProducts)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-gray-500">Aucune donnée disponible</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td class="font-medium"><?= htmlspecialchars($product['designation']) ?></td>
                                    <td class="text-right"><?= formatNumber($product['total_quantity']) ?></td>
                                    <td class="text-right font-semibold"><?= formatNumber($product['total_amount']) ?>
                                        FCFA</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Motifs des retours fournisseurs -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Motifs des retours fournisseurs</h3>
                        <div class="data-card-badge">
                            <span class="material-icons text-sm mr-1">assignment_return</span>
                            Analyse
                        </div>
                    </div>
                    <?php if (empty($returnReasons)): ?>
                    <div class="text-center py-4 text-gray-500">
                        Aucune donnée disponible sur les retours
                    </div>
                    <?php else: ?>
                    <div class="chart-container">
                        <canvas id="returnReasonsChart"></canvas>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Aperçu des commandes annulées -->
                <div class="data-card">
                    <div class="data-card-header">
                        <h3 class="data-card-title">Aperçu des commandes annulées</h3>
                        <div class="data-card-badge flex items-center">
                            <span class="material-icons text-sm mr-1">cancel</span>
                            <?= $selectedYear ?>
                        </div>
                    </div>
                    <?php if ($canceledOrdersStats['total_canceled'] > 0): ?>
                    <div class="space-y-4 mt-4">
                        <div class="flex items-center">
                            <div class="text-red-500 mr-3">
                                <span class="material-icons">warning</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium">Total des commandes annulées</p>
                                <p class="text-lg font-bold"><?= formatNumber($canceledOrdersStats['total_canceled']) ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center">
                            <div class="text-amber-500 mr-3">
                                <span class="material-icons">folder_off</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium">Projets avec annulations</p>
                                <p class="text-lg font-bold"><?= formatNumber($canceledOrdersStats['projects_count']) ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center">
                            <div class="text-purple-500 mr-3">
                                <span class="material-icons">event_busy</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium">Dernière annulation</p>
                                <p class="text-lg font-bold">
                                    <?= $canceledOrdersStats['last_canceled_date'] ? date('d/m/Y', strtotime($canceledOrdersStats['last_canceled_date'])) : 'N/A' ?>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <a href="stats_canceled_orders.php"
                                class="text-blue-600 hover:text-blue-800 flex items-center justify-center">
                                <span class="material-icons mr-1">visibility</span>
                                Voir tous les détails
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <span class="material-icons text-4xl mb-2">check_circle</span>
                        <p>Aucune commande annulée trouvée pour cette période</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Access Navigation -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
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

                <a href="stats_produits.php"
                    class="bg-green-600 hover:bg-green-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">inventory</span>
                    <h3 class="text-lg font-semibold">Statistiques des Produits</h3>
                    <p class="text-sm opacity-80 mt-1">Analyse du stock et des mouvements</p>
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

            // Configuration des graphiques
            setupCharts();

            // Configuration du bouton d'export PDF
            document.getElementById('export-pdf').addEventListener('click', handleExportPDF);
        });

        // Configuration des graphiques
        function setupCharts() {
            // Configuration des couleurs et options communes
            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.color = '#64748b';
            Chart.defaults.elements.line.borderWidth = 3;
            Chart.defaults.elements.point.radius = 3;
            Chart.defaults.elements.point.hoverRadius = 5;
            Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(17, 24, 39, 0.9)';
            Chart.defaults.plugins.tooltip.titleColor = '#ffffff';
            Chart.defaults.plugins.tooltip.bodyColor = '#ffffff';
            Chart.defaults.plugins.tooltip.padding = 12;
            Chart.defaults.plugins.tooltip.cornerRadius = 8;
            Chart.defaults.plugins.tooltip.displayColors = false;
            Chart.defaults.plugins.legend.labels.usePointStyle = true;
            Chart.defaults.plugins.legend.labels.boxWidth = 6;

            // Graphique 1: Évolution des achats annuels
            const purchasesYearlyCtx = document.getElementById('purchasesYearlyChart').getContext('2d');
            const purchasesYearlyData = <?= json_encode($yearlyChartData) ?>;

            const purchasesYearlyChart = new Chart(purchasesYearlyCtx, {
                type: 'line',
                data: {
                    labels: purchasesYearlyData.labels,
                    datasets: [
                        {
                            label: 'Montant des achats (FCFA)',
                            data: purchasesYearlyData.values,
                            borderColor: '#4361ee',
                            backgroundColor: 'rgba(67, 97, 238, 0.1)',
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Nombre de commandes',
                            data: purchasesYearlyData.counts,
                            borderColor: '#7209b7',
                            backgroundColor: 'rgba(114, 9, 183, 0.1)',
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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
                                        label += new Intl.NumberFormat('fr-FR').format(context.raw) + ' FCFA';
                                    } else {
                                        label += context.raw;
                                    }
                                    return label;
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            align: 'end'
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
                                text: 'FCFA',
                                font: {
                                    size: 12,
                                    weight: 'normal'
                                }
                            },
                            ticks: {
                                callback: function (value) {
                                    return new Intl.NumberFormat('fr-FR', {
                                        maximumFractionDigits: 0
                                    }).format(value);
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Nombre',
                                font: {
                                    size: 12,
                                    weight: 'normal'
                                }
                            },
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Graphique 2: Répartition par catégorie
            const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
            const categoriesData = <?= json_encode($catChartData) ?>;

            const categoriesChart = new Chart(categoriesCtx, {
                type: 'doughnut',
                data: {
                    labels: categoriesData.labels,
                    datasets: [{
                        data: categoriesData.values,
                        backgroundColor: categoriesData.colors,
                        borderColor: 'white',
                        borderWidth: 2,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                boxWidth: 8
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${new Intl.NumberFormat('fr-FR').format(value)} FCFA (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Graphique 3: Comparaison trimestrielle
            const quarterlyCtx = document.getElementById('quarterlyChart').getContext('2d');
            const quarterlyData = <?= json_encode($quarterlyChartData) ?>;

            const quarterlyChart = new Chart(quarterlyCtx, {
                type: 'bar',
                data: {
                    labels: quarterlyData.labels,
                    datasets: [
                        {
                            label: 'Montant des achats (FCFA)',
                            data: quarterlyData.values,
                            backgroundColor: [
                                'rgba(67, 97, 238, 0.7)',
                                'rgba(114, 9, 183, 0.7)',
                                'rgba(247, 37, 133, 0.7)',
                                'rgba(76, 201, 240, 0.7)'
                            ],
                            borderWidth: 0,
                            borderRadius: 6,
                            barPercentage: 0.6,
                            categoryPercentage: 0.6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.dataset.label || '';
                                    const value = context.raw;
                                    const commandes = quarterlyData.counts[context.dataIndex];
                                    return [
                                        `${label}: ${new Intl.NumberFormat('fr-FR').format(value)} FCFA`,
                                        `Nombre de commandes: ${commandes}`
                                    ];
                                }
                            }
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function (value) {
                                    return new Intl.NumberFormat('fr-FR', {
                                        maximumFractionDigits: 0
                                    }).format(value);
                                }
                            }
                        }
                    }
                }
            });

            // Graphique 4: Délai moyen de livraison par fournisseur
            const deliveryTimeCtx = document.getElementById('deliveryTimeChart').getContext('2d');
            const deliveryTimeData = <?= json_encode($deliveryChartData) ?>;

            const deliveryTimeChart = new Chart(deliveryTimeCtx, {
                type: 'bar',
                data: {
                    labels: deliveryTimeData.labels,
                    datasets: [{
                        label: 'Jours',
                        data: deliveryTimeData.values,
                        backgroundColor: function (context) {
                            const value = context.dataset.data[context.dataIndex];
                            // Couleur en fonction du délai
                            if (value <= 7) return 'rgba(16, 185, 129, 0.8)'; // Vert - Bon
                            if (value <= 14) return 'rgba(245, 158, 11, 0.8)'; // Jaune - Moyen
                            return 'rgba(239, 68, 68, 0.8)'; // Rouge - Mauvais
                        },
                        borderWidth: 0,
                        borderRadius: 4,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    indexAxis: 'y', // Affichage horizontal
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const value = context.raw;
                                    return `Délai moyen: ${value} jours`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            title: {
                                display: true,
                                text: 'Nombre de jours',
                                font: {
                                    size: 12,
                                    weight: 'normal'
                                }
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

            // Graphique 5: Motifs des retours fournisseurs
            <?php if (!empty($returnReasons)): ?>
            const returnReasonsCtx = document.getElementById('returnReasonsChart').getContext('2d');
            const returnReasonsData = <?= json_encode($returnReasonsChart) ?>;

            const returnReasonsChart = new Chart(returnReasonsCtx, {
                type: 'pie',
                data: {
                    labels: returnReasonsData.labels,
                    datasets: [{
                        data: returnReasonsData.values,
                        backgroundColor: returnReasonsData.colors,
                        borderColor: 'white',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                boxWidth: 8
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        }

        // Gestion de l'export PDF
        function handleExportPDF() {
            // Afficher un message de chargement
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
                window.location.href = 'generate_report.php?type=dashboard&year=<?= $selectedYear ?>';
            }, 1500);
        }
    </script>
</body>

</html>