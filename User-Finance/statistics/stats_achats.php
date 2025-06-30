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

// Récupérer l'année sélectionnée (par défaut année courante)
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : date('Y');
$currentYear = date('Y');

// Récupérer la liste des années disponibles
try {
    $yearsQuery = "SELECT DISTINCT YEAR(created_at) as year 
                  FROM achats_materiaux 
                  ORDER BY year DESC";

    $yearsStmt = $pdo->prepare($yearsQuery);
    $yearsStmt->execute();
    $availableYears = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);

    // Si aucune année n'est disponible, utiliser l'année courante
    if (empty($availableYears)) {
        $availableYears = [date('Y')];
    }

    // S'assurer que l'année sélectionnée est valide
    if (!in_array($selectedYear, $availableYears)) {
        $selectedYear = $availableYears[0];
    }
} catch (PDOException $e) {
    $availableYears = [date('Y')];
    $selectedYear = date('Y');
}

// Récupérer le mode de vue (montant, quantité ou combiné)
$viewMode = isset($_GET['view']) ? $_GET['view'] : 'amount';
if (!in_array($viewMode, ['amount', 'count', 'both'])) {
    $viewMode = 'amount';
}

// Récupérer les statistiques globales pour l'année sélectionnée
try {
    // Statistiques générales pour l'année
    $statsQuery = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(prix_unitaire * quantity), 0) as total_amount,
                    AVG(prix_unitaire * quantity) as average_order_value,
                    COUNT(DISTINCT fournisseur) as supplier_count
                   FROM achats_materiaux
                   WHERE YEAR(created_at) = :year";

    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    $statsStmt->execute();
    $yearStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Tendance des prix d'achat
    $prixTendanceQuery = "SELECT 
                        AVG(prix_unitaire) as avg_price,
                        MAX(prix_unitaire) as max_price,
                        MIN(prix_unitaire) as min_price,
                        STDDEV(prix_unitaire) as price_deviation
                        FROM achats_materiaux
                        WHERE YEAR(created_at) = :year
                        AND prix_unitaire > 0";

    $prixTendanceStmt = $pdo->prepare($prixTendanceQuery);
    $prixTendanceStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    $prixTendanceStmt->execute();
    $prixTendance = $prixTendanceStmt->fetch(PDO::FETCH_ASSOC);

    // Prix moyen comparé à l'année précédente (pour indiquer la tendance)
    if ($selectedYear > 2010) {  // Un seuil raisonnable pour éviter des années trop anciennes
        $prevYearPriceQuery = "SELECT 
                                AVG(prix_unitaire) as avg_price
                               FROM achats_materiaux
                               WHERE YEAR(created_at) = :prev_year
                               AND prix_unitaire > 0";
        
        $prevYear = $selectedYear - 1;
        $prevYearPriceStmt = $pdo->prepare($prevYearPriceQuery);
        $prevYearPriceStmt->bindParam(':prev_year', $prevYear, PDO::PARAM_INT);
        $prevYearPriceStmt->execute();
        $prevYearPrice = $prevYearPriceStmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculer la variation en pourcentage
        if ($prevYearPrice && $prevYearPrice['avg_price'] > 0) {
            $priceVariation = (($prixTendance['avg_price'] - $prevYearPrice['avg_price']) / $prevYearPrice['avg_price']) * 100;
        } else {
            $priceVariation = 0;
        }
    } else {
        $priceVariation = 0;
    }

    // Commandes par trimestre
    $quarterlyQuery = "SELECT 
                        QUARTER(created_at) as quarter,
                        COUNT(*) as orders,
                        COALESCE(SUM(prix_unitaire * quantity), 0) as amount
                       FROM achats_materiaux
                       WHERE YEAR(created_at) = :year
                       GROUP BY QUARTER(created_at)
                       ORDER BY quarter";

    $quarterlyStmt = $pdo->prepare($quarterlyQuery);
    $quarterlyStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    $quarterlyStmt->execute();
    $quarterlyStats = $quarterlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Commandes par statut
    $statusQuery = "SELECT 
                     status,
                     COUNT(*) as count,
                     COALESCE(SUM(prix_unitaire * quantity), 0) as amount
                    FROM achats_materiaux
                    WHERE YEAR(created_at) = :year
                    GROUP BY status";

    $statusStmt = $pdo->prepare($statusQuery);
    $statusStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    $statusStmt->execute();
    $statusStats = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 des produits les plus achetés
    $topProductsQuery = "SELECT 
                          designation,
                          COUNT(*) as order_count,
                          SUM(quantity) as total_quantity,
                          COALESCE(SUM(prix_unitaire * quantity), 0) as total_amount,
                          AVG(prix_unitaire) as avg_price
                         FROM achats_materiaux
                         WHERE YEAR(created_at) = :year
                         GROUP BY designation
                         ORDER BY total_amount DESC
                         LIMIT 10";

    $topProductsStmt = $pdo->prepare($topProductsQuery);
    $topProductsStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    $topProductsStmt->execute();
    $topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Tendance des achats sur les 5 derniers mois
    $recentMonthsQuery = "SELECT 
                           DATE_FORMAT(created_at, '%Y-%m') as month,
                           DATE_FORMAT(created_at, '%b %Y') as month_name,
                           COUNT(*) as count,
                           COALESCE(SUM(prix_unitaire * quantity), 0) as total
                          FROM achats_materiaux
                          WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 5 MONTH)
                          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                          ORDER BY month ASC";

    $recentMonthsStmt = $pdo->prepare($recentMonthsQuery);
    $recentMonthsStmt->execute();
    $recentMonthsData = $recentMonthsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupération des tendances pour les mini-graphiques
    $trendData = [
        'orders' => [],
        'amounts' => [],
        'prices' => []
    ];

    foreach ($recentMonthsData as $month) {
        $trendData['orders'][] = $month['count'];
        $trendData['amounts'][] = $month['total'];
    }

    // Tendance des prix moyens sur les 5 derniers mois
    $pricesTrendQuery = "SELECT 
                          DATE_FORMAT(created_at, '%Y-%m') as month,
                          AVG(prix_unitaire) as avg_price
                         FROM achats_materiaux
                         WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 5 MONTH)
                         AND prix_unitaire > 0
                         GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                         ORDER BY month ASC";

    $pricesTrendStmt = $pdo->prepare($pricesTrendQuery);
    $pricesTrendStmt->execute();
    $pricesTrendData = $pricesTrendStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pricesTrendData as $month) {
        $trendData['prices'][] = $month['avg_price'];
    }

    // SECTION: Statistiques des commandes annulées
    $canceledQuery = "SELECT 
                       COUNT(*) as total_canceled,
                       COUNT(DISTINCT idExpression) as projects_count
                      FROM expression_dym
                      WHERE valide_achat = 'annulé'
                      AND YEAR(updated_at) = :year";

    $canceledStmt = $pdo->prepare($canceledQuery);
    $canceledStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    $canceledStmt->execute();
    $canceledStats = $canceledStmt->fetch(PDO::FETCH_ASSOC);

    // Top produits annulés
    $topCanceledQuery = "SELECT 
                          designation,
                          COUNT(*) as count
                         FROM expression_dym
                         WHERE valide_achat = 'annulé'
                         AND YEAR(updated_at) = :year
                         GROUP BY designation
                         ORDER BY count DESC
                         LIMIT 5";

    $topCanceledStmt = $pdo->prepare($topCanceledQuery);
    $topCanceledStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    $topCanceledStmt->execute();
    $topCanceledProducts = $topCanceledStmt->fetchAll(PDO::FETCH_ASSOC);

    // Tendance mensuelle des annulations
    $monthlyCanceledQuery = "SELECT 
                              MONTH(updated_at) as month,
                              COUNT(*) as count
                             FROM expression_dym
                             WHERE valide_achat = 'annulé'
                             AND YEAR(updated_at) = :year
                             GROUP BY MONTH(updated_at)
                             ORDER BY month";

    $monthlyCanceledStmt = $pdo->prepare($monthlyCanceledQuery);
    $monthlyCanceledStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    $monthlyCanceledStmt->execute();
    $monthlyCanceledData = $monthlyCanceledStmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les données d'achat mensuelles pour le graphique
    $monthlyDataQuery = "SELECT 
                           MONTH(created_at) as month,
                           COUNT(*) as count,
                           COALESCE(SUM(prix_unitaire * quantity), 0) as total,
                           SUM(quantity) as quantity_sum
                          FROM achats_materiaux
                          WHERE YEAR(created_at) = :year
                          GROUP BY MONTH(created_at)
                          ORDER BY month";

    $monthlyDataStmt = $pdo->prepare($monthlyDataQuery);
    $monthlyDataStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    $monthlyDataStmt->execute();
    $monthlyData = $monthlyDataStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatage des données mensuelles pour JavaScript
    $monthNames = [
        "Janvier",
        "Février",
        "Mars",
        "Avril",
        "Mai",
        "Juin",
        "Juillet",
        "Août",
        "Septembre",
        "Octobre",
        "Novembre",
        "Décembre"
    ];

    $formattedMonthlyData = [];
    for ($i = 1; $i <= 12; $i++) {
        $formattedMonthlyData[$i] = [
            'month' => $i,
            'month_name' => $monthNames[$i - 1],
            'count' => 0,
            'total' => 0,
            'quantity_sum' => 0
        ];
    }

    foreach ($monthlyData as $data) {
        if (isset($formattedMonthlyData[$data['month']])) {
            $formattedMonthlyData[$data['month']]['count'] = (int) $data['count'];
            $formattedMonthlyData[$data['month']]['total'] = (float) $data['total'];
            $formattedMonthlyData[$data['month']]['quantity_sum'] = (float) $data['quantity_sum'];
        }
    }

    // Formatage des données d'annulation mensuelle
    $formattedCanceledData = [];
    for ($i = 1; $i <= 12; $i++) {
        $formattedCanceledData[$i] = [
            'month' => $i,
            'month_name' => $monthNames[$i - 1],
            'count' => 0
        ];
    }

    foreach ($monthlyCanceledData as $data) {
        if (isset($formattedCanceledData[$data['month']])) {
            $formattedCanceledData[$data['month']]['count'] = (int) $data['count'];
        }
    }

    // Convertir en tableau indexé pour JavaScript
    $formattedMonthlyData = array_values($formattedMonthlyData);
    $formattedCanceledData = array_values($formattedCanceledData);

    // Top 5 fournisseurs
    $topSuppliersQuery = "SELECT 
                            fournisseur,
                            COUNT(*) as order_count,
                            COALESCE(SUM(prix_unitaire * quantity), 0) as total_amount
                           FROM achats_materiaux
                           WHERE YEAR(created_at) = :year
                           AND fournisseur IS NOT NULL AND fournisseur != ''
                           GROUP BY fournisseur
                           ORDER BY total_amount DESC
                           LIMIT 5";

    $topSuppliersStmt = $pdo->prepare($topSuppliersQuery);
    $topSuppliersStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    $topSuppliersStmt->execute();
    $topSuppliers = $topSuppliersStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
}

// Fonction pour formater les nombres
function formatNumber($number, $decimals = 0)
{
    if ($number == 0) return '0';
    return number_format($number, $decimals, ',', ' ');
}

// Fonction pour déterminer la classe de tendance
function getTrendClass($value)
{
    if ($value > 0) return 'up';
    if ($value < 0) return 'down';
    return 'neutral';
}

// Fonction pour obtenir l'icône de tendance
function getTrendIcon($trend)
{
    if ($trend === 'up') return 'trending_up';
    if ($trend === 'down') return 'trending_down';
    return 'trending_flat';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des Achats <?= $selectedYear ?> | DYM MANUFACTURE</title>
    
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

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .stats-card {
            transition: all 0.3s ease;
            border-radius: 0.75rem;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .chart-container {
            position: relative;
            height: 320px;
            width: 100%;
        }
        
        .chart-container-lg {
            position: relative;
            height: 400px;
            width: 100%;
        }
        
        .mini-chart-container {
            position: relative;
            height: 50px;
            width: 100px;
        }
        
        .quarter-card {
            border-left-width: 4px;
            transition: all 0.2s ease;
        }
        
        .quarter-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-badge.commandé {
            background-color: #EBF8FF;
            color: #2B6CB0;
        }
        
        .status-badge.en_attente, .status-badge.en-cours {
            background-color: #FFFBEB;
            color: #D97706;
        }
        
        .status-badge.reçu {
            background-color: #F0FFF4;
            color: #2F855A;
        }
        
        .status-badge.annulé {
            background-color: #FEF2F2;
            color: #DC2626;
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
            animation: fadeIn 0.3s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .view-selector {
            display: inline-flex;
            background-color: #E5E7EB;
            border-radius: 9999px;
            padding: 2px;
        }
        
        .view-option {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .view-option.active {
            background-color: #3B82F6;
            color: white;
        }
        
        .trend-indicator {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .trend-up {
            color: #10B981;
        }
        
        .trend-down {
            color: #EF4444;
        }
        
        .trend-neutral {
            color: #6B7280;
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .data-table th {
            background-color: #F8FAFC;
            color: #64748B;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #E2E8F0;
        }
        
        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #E2E8F0;
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover {
            background-color: #F1F5F9;
        }
        
        .tab {
            cursor: pointer;
            padding: 0.75rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
        }
        
        .tab.active {
            color: #3B82F6;
            border-bottom-color: #3B82F6;
        }
        
        .tab:hover:not(.active) {
            color: #4B5563;
            border-bottom-color: #E5E7EB;
        }
        
        .tab .material-icons {
            margin-right: 0.5rem;
            font-size: 1.25rem;
        }
        
        .nav-card {
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .nav-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .trend-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .trend-badge.up {
            background-color: #D1FAE5;
            color: #065F46;
        }
        
        .trend-badge.down {
            background-color: #FEE2E2;
            color: #991B1B;
        }
        
        .trend-badge.neutral {
            background-color: #F3F4F6;
            color: #4B5563;
        }
        
        @media (max-width: 768px) {
            .chart-container, .chart-container-lg {
                height: 250px;
            }
        }

        /* Animation de chargement */
        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }

        .loading-shimmer {
            animation: shimmer 2s infinite linear;
            background: linear-gradient(to right, #f6f7f8 8%, #edeef1 18%, #f6f7f8 33%);
            background-size: 1000px 100%;
        }
        
        /* Dashboard cards animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }
        
        .animate-delay-100 { animation-delay: 0.1s; }
        .animate-delay-200 { animation-delay: 0.2s; }
        .animate-delay-300 { animation-delay: 0.3s; }
        .animate-delay-400 { animation-delay: 0.4s; }
        .animate-delay-500 { animation-delay: 0.5s; }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../../components/navbar_finance.php'; ?>

        <main class="flex-1 p-6">
            <!-- En-tête de la page avec filtres -->
            <div class="bg-white shadow rounded-lg p-4 mb-6 flex flex-wrap justify-between items-center">
                <div class="flex items-center space-x-4 mb-2 md:mb-0">
                    <a href="index.php" class="flex items-center text-blue-600 hover:text-blue-800 transition">
                        <span class="material-icons">arrow_back</span>
                        <span class="ml-1 font-medium">Dashboard</span>
                    </a>
                    <h1 class="text-xl font-bold text-gray-800 flex items-center">
                        <span class="material-icons text-blue-600 mr-2">insights</span>
                        Statistiques des Achats <?= $selectedYear ?>
                    </h1>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Sélecteur d'année -->
                    <form action="" method="GET" class="flex items-center bg-gray-50 px-3 py-2 rounded-lg shadow-sm border border-gray-200">
                        <span class="material-icons text-gray-500 mr-2">event</span>
                        <select id="year-select" name="year" 
                            class="bg-transparent border-none focus:outline-none text-gray-700 font-medium"
                            onchange="this.form.submit()">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?= $year ?>" <?= $selectedYear == $year ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Préserver les autres paramètres dans le formulaire -->
                        <input type="hidden" name="view" value="<?= $viewMode ?>">
                    </form>

                    <!-- Bouton d'export PDF -->
                    <button id="export-pdf" class="flex items-center bg-white border border-gray-300 rounded-lg px-4 py-2 text-gray-700 hover:bg-gray-50 transition shadow-sm">
                        <span class="material-icons mr-2 text-gray-600">picture_as_pdf</span>
                        <span class="font-medium">Exporter PDF</span>
                    </button>

                    <!-- Affichage de la date et heure -->
                    <div class="flex items-center bg-white px-4 py-2 rounded-lg border border-gray-200 shadow-sm">
                        <span class="material-icons text-gray-600 mr-2">schedule</span>
                        <span id="date-time-display" class="text-gray-700 font-medium"></span>
                    </div>
                </div>
            </div>

            <?php if (isset($errorMessage)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm">
                    <div class="flex items-center">
                        <span class="material-icons mr-2">error</span>
                        <p><?= $errorMessage ?></p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Indicateurs de performance clés (KPIs) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- KPI 1: Commandes totales -->
                    <div class="stats-card bg-white p-6 shadow-sm border border-gray-100 rounded-xl animate-fade-in-up">
                        <div class="flex items-center justify-between mb-2">
                            <div class="rounded-full bg-blue-100 p-3">
                                <span class="material-icons text-blue-600">shopping_cart</span>
                            </div>
                            <?php if (!empty($trendData['orders'])): ?>
                                <div class="mini-chart-container">
                                    <canvas id="ordersChart"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-500 mb-1">Commandes totales</h3>
                        <div class="flex items-end justify-between">
                        <p class="text-3xl font-bold text-gray-800">
                                <?= formatNumber($yearStats['total_orders']) ?>
                            </p>
                            <div class="trend-badge <?= $yearStats['total_orders'] > 0 ? 'up' : 'neutral' ?>">
                                <span class="material-icons text-sm mr-1"><?= $yearStats['total_orders'] > 0 ? 'trending_up' : 'trending_flat' ?></span>
                                <span><?= $selectedYear ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 2: Montant total -->
                    <div class="stats-card bg-white p-6 shadow-sm border border-gray-100 rounded-xl animate-fade-in-up animate-delay-100">
                        <div class="flex items-center justify-between mb-2">
                            <div class="rounded-full bg-green-100 p-3">
                                <span class="material-icons text-green-600">payments</span>
                            </div>
                            <?php if (!empty($trendData['amounts'])): ?>
                                <div class="mini-chart-container">
                                    <canvas id="amountsChart"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-500 mb-1">Montant total</h3>
                        <div class="flex items-end justify-between">
                            <p class="text-3xl font-bold text-gray-800">
                                <?= formatNumber($yearStats['total_amount']) ?> <span class="text-sm font-medium">FCFA</span>
                            </p>
                            <div class="trend-badge <?= $yearStats['total_amount'] > 0 ? 'up' : 'neutral' ?>">
                                <span class="material-icons text-sm mr-1">monetization_on</span>
                                <span>Achats</span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 3: Valeur moyenne -->
                    <div class="stats-card bg-white p-6 shadow-sm border border-gray-100 rounded-xl animate-fade-in-up animate-delay-200">
                        <div class="flex items-center justify-between mb-2">
                            <div class="rounded-full bg-purple-100 p-3">
                                <span class="material-icons text-purple-600">analytics</span>
                            </div>
                            <div class="flex items-center">
                                <span class="material-icons text-purple-600 text-lg">calculate</span>
                                <span class="ml-1 text-xs font-medium text-purple-600">Moyenne</span>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-500 mb-1">Valeur moyenne</h3>
                        <div class="flex items-end justify-between">
                            <p class="text-3xl font-bold text-gray-800">
                                <?= formatNumber($yearStats['average_order_value']) ?> <span class="text-sm font-medium">FCFA</span>
                            </p>
                            <div class="trend-badge neutral">
                                <span class="material-icons text-sm mr-1">receipt</span>
                                <span>Par cmd</span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 4: Prix moyen d'achat -->
                    <div class="stats-card bg-white p-6 shadow-sm border border-gray-100 rounded-xl animate-fade-in-up animate-delay-300">
                        <div class="flex items-center justify-between mb-2">
                            <div class="rounded-full bg-red-100 p-3">
                                <span class="material-icons text-red-600">price_check</span>
                            </div>
                            <?php if (!empty($trendData['prices'])): ?>
                                <div class="mini-chart-container">
                                    <canvas id="pricesChart"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-500 mb-1">Prix moyen d'achat</h3>
                        <div class="flex items-end justify-between">
                            <p class="text-3xl font-bold text-gray-800">
                                <?= formatNumber($prixTendance['avg_price'] ?? 0) ?> <span class="text-sm font-medium">FCFA</span>
                            </p>
                            <?php 
                            $trendClass = getTrendClass($priceVariation ?? 0);
                            $trendIcon = getTrendIcon($trendClass);
                            ?>
                            <div class="trend-badge <?= $trendClass ?>">
                                <span class="material-icons text-sm mr-1"><?= $trendIcon ?></span>
                                <span><?= round($priceVariation ?? 0, 1) ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seconde ligne d'indicateurs -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Indicateur 5: Nombre de fournisseurs -->
                    <div class="stats-card bg-white p-6 shadow-sm border border-gray-100 rounded-xl animate-fade-in-up animate-delay-400">
                        <div class="flex items-center justify-between mb-2">
                            <div class="rounded-full bg-yellow-100 p-3">
                                <span class="material-icons text-yellow-600">business</span>
                            </div>
                            <div class="flex items-center">
                                <span class="material-icons text-yellow-600 text-lg">group</span>
                                <span class="ml-1 text-xs font-medium text-yellow-600">Partenaires</span>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-500 mb-1">Fournisseurs actifs</h3>
                        <div class="flex items-end justify-between">
                            <p class="text-3xl font-bold text-gray-800">
                                <?= formatNumber($yearStats['supplier_count']) ?>
                            </p>
                            <div class="trend-badge neutral">
                                <span class="material-icons text-sm mr-1">handshake</span>
                                <span>Fournisseurs</span>
                            </div>
                        </div>
                    </div>

                    <!-- Indicateur 6: Commandes annulées -->
                    <div class="stats-card bg-white p-6 shadow-sm border border-gray-100 rounded-xl animate-fade-in-up animate-delay-500">
                        <div class="flex items-center justify-between mb-2">
                            <div class="rounded-full bg-red-100 p-3">
                                <span class="material-icons text-red-600">cancel</span>
                            </div>
                            <div class="flex items-center">
                                <span class="material-icons text-red-600 text-lg">error</span>
                                <span class="ml-1 text-xs font-medium text-red-600">Annulations</span>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-500 mb-1">Commandes annulées</h3>
                        <div class="flex items-end justify-between">
                            <p class="text-3xl font-bold text-gray-800">
                                <?= formatNumber($canceledStats['total_canceled'] ?? 0) ?>
                            </p>
                            <div class="trend-badge <?= ($canceledStats['total_canceled'] ?? 0) > 0 ? 'down' : 'neutral' ?>">
                                <span class="material-icons text-sm mr-1">business</span>
                                <span><?= formatNumber($canceledStats['projects_count'] ?? 0) ?> projets</span>
                            </div>
                        </div>
                    </div>

                    <!-- Indicateur 7: Dispersion des prix -->
                    <div class="stats-card bg-white p-6 shadow-sm border border-gray-100 rounded-xl animate-fade-in-up animate-delay-500">
                        <div class="flex items-center justify-between mb-2">
                            <div class="rounded-full bg-blue-100 p-3">
                                <span class="material-icons text-blue-600">scatter_plot</span>
                            </div>
                            <div class="flex items-center">
                                <span class="material-icons text-blue-600 text-lg">bar_chart</span>
                                <span class="ml-1 text-xs font-medium text-blue-600">Min/Max</span>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-500 mb-1">Dispersion des prix</h3>
                        <div class="flex items-center justify-between mt-2">
                            <div>
                                <span class="text-sm text-gray-500">Min:</span>
                                <span class="font-semibold"><?= formatNumber($prixTendance['min_price'] ?? 0) ?> FCFA</span>
                            </div>
                            <span class="text-gray-400">•</span>
                            <div>
                                <span class="text-sm text-gray-500">Max:</span>
                                <span class="font-semibold"><?= formatNumber($prixTendance['max_price'] ?? 0) ?> FCFA</span>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>
                </div>

                <!-- Onglets de navigation -->
                <div class="bg-white shadow-sm rounded-lg mb-6">
                    <div class="border-b border-gray-200">
                        <div class="flex overflow-x-auto">
                            <button class="tab active" data-tab="tab-overview">
                                <span class="material-icons">dashboard</span>
                                Vue d'ensemble
                            </button>
                            <button class="tab" data-tab="tab-quarterly">
                                <span class="material-icons">date_range</span>
                                Analyse trimestrielle
                            </button>
                            <button class="tab" data-tab="tab-status">
                                <span class="material-icons">receipt_long</span>
                                Statut des commandes
                            </button>
                            <button class="tab" data-tab="tab-canceled">
                                <span class="material-icons">cancel</span>
                                Commandes annulées
                            </button>
                            <button class="tab" data-tab="tab-suppliers">
                                <span class="material-icons">business</span>
                                Fournisseurs
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contenu de l'onglet "Vue d'ensemble" -->
                <div id="tab-overview" class="tab-content active">
                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-6">
                        <!-- Graphique principal: Évolution des achats mensuels -->
                        <div class="bg-white p-6 shadow-sm rounded-xl lg:col-span-3 border border-gray-100">
                            <div class="flex flex-wrap justify-between items-center mb-4">
                                <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <span class="material-icons text-blue-600 mr-2">insights</span>
                                    Évolution des achats mensuels <?= $selectedYear ?>
                                </h2>
                                
                                <!-- Sélecteur de vue -->
                                <div class="view-selector mt-2 sm:mt-0">
                                    <a href="?year=<?= $selectedYear ?>&view=amount" 
                                       class="view-option <?= $viewMode === 'amount' ? 'active' : '' ?>">
                                        Montant
                                    </a>
                                    <a href="?year=<?= $selectedYear ?>&view=count" 
                                       class="view-option <?= $viewMode === 'count' ? 'active' : '' ?>">
                                        Quantité
                                    </a>
                                    <a href="?year=<?= $selectedYear ?>&view=both" 
                                       class="view-option <?= $viewMode === 'both' ? 'active' : '' ?>">
                                        Combiné
                                    </a>
                                </div>
                            </div>
                            <div class="chart-container-lg">
                                <canvas id="monthlyPurchasesChart"></canvas>
                            </div>
                        </div>

                        <!-- Top 5 des produits les plus achetés -->
                        <div class="bg-white p-6 shadow-sm rounded-xl lg:col-span-2 border border-gray-100">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <span class="material-icons text-green-600 mr-2">category</span>
                                    Top 5 produits
                                </h2>
                                <a href="stats_produits.php" class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                                    Voir tous
                                    <span class="material-icons text-sm ml-1">arrow_forward</span>
                                </a>
                            </div>
                            
                            <?php if (empty($topProducts)): ?>
                                <div class="flex flex-col items-center justify-center py-8">
                                    <span class="material-icons text-gray-400 text-5xl mb-4">inventory</span>
                                    <p class="text-gray-500 text-center">Aucune donnée disponible pour cette période</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="data-table min-w-full">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Produit</th>
                                                <th class="text-right">Montant</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($topProducts, 0, 5) as $index => $product): ?>
                                                <tr>
                                                    <td class="font-medium">
                                                        <div class="flex items-center">
                                                            <span class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-800 text-xs font-bold mr-2">
                                                                <?= $index + 1 ?>
                                                            </span>
                                                            <span class="truncate" style="max-width: 180px;" title="<?= htmlspecialchars($product['designation']) ?>">
                                                                <?= htmlspecialchars($product['designation']) ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="text-right font-semibold">
                                                        <?= formatNumber($product['total_amount']) ?> FCFA
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-4">
                                    <div class="chart-container" style="height: 200px;">
                                        <canvas id="topProductsChart"></canvas>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Tableau détaillé des produits (top 10) -->
                    <div class="bg-white p-6 shadow-sm rounded-xl mb-6 border border-gray-100">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                                <span class="material-icons text-blue-600 mr-2">inventory</span>
                                Top 10 des produits les plus achetés
                            </h2>
                            <a href="stats_produits.php" 
                               class="inline-flex items-center px-3 py-1 border border-blue-600 text-blue-600 bg-white rounded-lg hover:bg-blue-50 transition-colors">
                                <span class="material-icons text-sm mr-1">leaderboard</span>
                                Analyse produits
                            </a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="data-table min-w-full">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th class="text-center">Commandes</th>
                                        <th class="text-center">Quantité totale</th>
                                        <th class="text-center">Prix moyen</th>
                                        <th class="text-right">Montant total</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Si aucun produit n'est trouvé, afficher un message
                                    if (empty($topProducts)):
                                    ?>
                                        <tr>
                                            <td colspan="6" class="py-4 px-4 text-gray-500 text-center">Aucune donnée disponible</td>
                                        </tr>
                                    <?php
                                    else:
                                        foreach ($topProducts as $index => $product):
                                    ?>
                                        <tr class="hover:bg-gray-50 <?= $index < 3 ? 'bg-blue-50' : '' ?>">
                                            <td class="py-3 px-4 font-medium">
                                                <div class="flex items-center">
                                                    <?php if ($index < 3): ?>
                                                        <span class="flex items-center justify-center w-6 h-6 rounded-full 
                                                                     <?= $index === 0 ? 'bg-yellow-400' : ($index === 1 ? 'bg-gray-300' : 'bg-blue-600') ?> 
                                                                     text-white text-xs font-bold mr-2">
                                                            <?= $index + 1 ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold mr-2">
                                                            <?= $index + 1 ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($product['designation']) ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-center"><?= formatNumber($product['order_count']) ?></td>
                                            <td class="py-3 px-4 text-center"><?= formatNumber($product['total_quantity']) ?></td>
                                            <td class="py-3 px-4 text-center"><?= formatNumber($product['avg_price']) ?> FCFA</td>
                                            <td class="py-3 px-4 text-right font-semibold"><?= formatNumber($product['total_amount']) ?> FCFA</td>
                                            <td class="py-3 px-4 text-center">
                                                <button onclick="viewProductDetails('<?= htmlspecialchars($product['designation']) ?>')" 
                                                        class="text-blue-600 hover:text-blue-800 bg-blue-100 hover:bg-blue-200 transition-colors rounded-full p-2">
                                                    <span class="material-icons text-sm">visibility</span>
                                                </button>
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

                <!-- Contenu de l'onglet "Analyse trimestrielle" -->
                <div id="tab-quarterly" class="tab-content">
                    <div class="grid grid-cols-1 gap-6 mb-6">
                        <!-- Graphique de répartition trimestrielle -->
                        <div class="bg-white p-6 shadow-sm rounded-xl border border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                                <span class="material-icons text-blue-600 mr-2">date_range</span>
                                Répartition trimestrielle des achats pour <?= $selectedYear ?>
                            </h2>
                            <div class="chart-container-lg">
                                <canvas id="quarterlyChart"></canvas>
                            </div>
                        </div>

                        <!-- Statistiques trimestrielles -->
                        <div class="bg-white p-6 shadow-sm rounded-xl border border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                                <span class="material-icons text-green-600 mr-2">analytics</span>
                                Détail par trimestre
                            </h2>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                                <?php
                                // Définir les couleurs et les noms pour chaque trimestre
                                $quarterColors = [
                                    1 => 'border-blue-500',
                                    2 => 'border-green-500',
                                    3 => 'border-yellow-500',
                                    4 => 'border-red-500'
                                ];

                                $quarterBgColors = [
                                    1 => 'bg-blue-50',
                                    2 => 'bg-green-50',
                                    3 => 'bg-yellow-50',
                                    4 => 'bg-red-50'
                                ];

                                $quarterNames = [
                                    1 => '1er Trimestre',
                                    2 => '2ème Trimestre',
                                    3 => '3ème Trimestre',
                                    4 => '4ème Trimestre'
                                ];

                                $quarterIcons = [
                                    1 => 'filter_1',
                                    2 => 'filter_2',
                                    3 => 'filter_3',
                                    4 => 'filter_4'
                                ];

                                // Préparer un tableau avec tous les trimestres
                                $quarterData = [];
                                for ($i = 1; $i <= 4; $i++) {
                                    $quarterData[$i] = [
                                        'quarter' => $i,
                                        'orders' => 0,
                                        'amount' => 0
                                    ];
                                }

                                // Remplir avec les données réelles
                                foreach ($quarterlyStats as $stat) {
                                    $quarter = $stat['quarter'];
                                    $quarterData[$quarter]['orders'] = $stat['orders'];
                                    $quarterData[$quarter]['amount'] = $stat['amount'];
                                }

                                // Afficher les cartes pour chaque trimestre
                                foreach ($quarterData as $quarter => $data):
                                    $percentage = $yearStats['total_amount'] > 0 ? round(($data['amount'] / $yearStats['total_amount']) * 100, 1) : 0;
                                ?>
                                    <div class="quarter-card <?= $quarterBgColors[$quarter] ?> p-4 rounded-xl border <?= $quarterColors[$quarter] ?>">
                                        <div class="flex items-center mb-3">
                                            <span class="material-icons text-gray-700 mr-2"><?= $quarterIcons[$quarter] ?></span>
                                            <h3 class="font-semibold text-gray-800"><?= $quarterNames[$quarter] ?></h3>
                                        </div>
                                        <div class="space-y-3">
                                            <div>
                                                <p class="text-sm text-gray-600 mb-1">Commandes:</p>
                                                <p class="text-lg font-bold"><?= formatNumber($data['orders']) ?></p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 mb-1">Montant:</p>
                                                <p class="text-lg font-bold"><?= formatNumber($data['amount']) ?> FCFA</p>
                                            </div>
                                            <div>
                                                <div class="flex justify-between items-center mb-1">
                                                    <p class="text-sm text-gray-600">% du total:</p>
                                                    <p class="font-medium"><?= $percentage ?>%</p>
                                                </div>
                                                <div class="w-full bg-white rounded-full h-2">
                                                    <div class="h-2 rounded-full"
                                                        style="width: <?= $percentage ?>%; background-color: <?= $quarter == 1 ? '#3B82F6' : ($quarter == 2 ? '#10B981' : ($quarter == 3 ? '#F59E0B' : '#EF4444')) ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Tendance des achats trimestriels -->
                        <div class="bg-white p-6 shadow-sm rounded-xl border border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                                <span class="material-icons text-purple-600 mr-2">trending_up</span>
                                Tendance des achats trimestriels
                            </h2>
                            <div class="chart-container">
                                <canvas id="quarterlyTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenu de l'onglet "Statut des commandes" -->
                <div id="tab-status" class="tab-content">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Graphique de répartition par statut -->
                        <div class="bg-white p-6 shadow-sm rounded-xl border border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                                <span class="material-icons text-blue-600 mr-2">pie_chart</span>
                                Répartition par statut
                            </h2>
                            <div class="chart-container-lg">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>

                        <!-- Détail des statuts -->
                        <div class="bg-white p-6 shadow-sm rounded-xl border border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                                <span class="material-icons text-green-600 mr-2">assignment</span>
                                Détail par statut
                            </h2>

                            <div class="overflow-x-auto">
                                <table class="data-table min-w-full">
                                    <thead>
                                        <tr>
                                            <th>Statut</th>
                                            <th class="text-center">Commandes</th>
                                            <th class="text-right">Montant</th>
                                            <th class="text-center">% du total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Si aucun statut n'est trouvé, afficher un message
                                        if (empty($statusStats)):
                                        ?>
                                            <tr>
                                                <td colspan="4" class="py-4 px-4 text-gray-500 text-center">Aucune donnée disponible</td>
                                            </tr>
                                        <?php
                                        else:
                                            foreach ($statusStats as $status):
                                                $statusLabel = $status['status'] ? $status['status'] : 'Non défini';
                                                $percentage = $yearStats['total_amount'] > 0 ? round(($status['amount'] / $yearStats['total_amount']) * 100, 1) : 0;
                                        ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-3 px-4">
                                                    <span class="status-badge <?= strtolower($statusLabel) ?>">
                                                        <?= ucfirst($statusLabel) ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-center"><?= formatNumber($status['count']) ?></td>
                                                <td class="py-3 px-4 text-right font-semibold"><?= formatNumber($status['amount']) ?> FCFA</td>
                                                <td class="py-3 px-4">
                                                    <div class="flex items-center">
                                                    <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                                                            <div class="bg-blue-600 h-2.5 rounded-full"
                                                                style="width: <?= $percentage ?>%"></div>
                                                        </div>
                                                        <span class="text-sm font-medium"><?= $percentage ?>%</span>
                                                    </div>
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
                    
                    <!-- Analyse de l'évolution des statuts -->
                    <div class="bg-white p-6 shadow-sm rounded-xl border border-gray-100 mb-6">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-6">
                            <span class="material-icons text-blue-600 mr-2">show_chart</span>
                            Évolution des statuts de commande
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <?php 
                            // Classification des statuts
                            $commandeCount = 0;
                            $recuCount = 0;
                            $attenteCount = 0;
                            $annuleCount = 0;
                            
                            // Montants par statut
                            $commandeAmount = 0;
                            $recuAmount = 0;
                            $attenteAmount = 0;
                            $annuleAmount = 0;
                            
                            foreach ($statusStats as $status) {
                                $statusLower = strtolower($status['status'] ?? '');
                                
                                if ($statusLower === 'commandé') {
                                    $commandeCount += $status['count'];
                                    $commandeAmount += $status['amount'];
                                } elseif ($statusLower === 'reçu') {
                                    $recuCount += $status['count'];
                                    $recuAmount += $status['amount'];
                                } elseif ($statusLower === 'en_attente' || $statusLower === 'en attente' || $statusLower === 'en-cours') {
                                    $attenteCount += $status['count'];
                                    $attenteAmount += $status['amount'];
                                } elseif ($statusLower === 'annulé') {
                                    $annuleCount += $status['count'];
                                    $annuleAmount += $status['amount'];
                                }
                            }
                            
                            // Cartes de statut
                            $statusCards = [
                                [
                                    'title' => 'Commandé',
                                    'count' => $commandeCount,
                                    'amount' => $commandeAmount,
                                    'color' => 'blue',
                                    'icon' => 'shopping_cart'
                                ],
                                [
                                    'title' => 'Reçu',
                                    'count' => $recuCount,
                                    'amount' => $recuAmount,
                                    'color' => 'green',
                                    'icon' => 'check_circle'
                                ],
                                [
                                    'title' => 'En attente',
                                    'count' => $attenteCount,
                                    'amount' => $attenteAmount,
                                    'color' => 'yellow',
                                    'icon' => 'hourglass_empty'
                                ],
                                [
                                    'title' => 'Annulé',
                                    'count' => $annuleCount,
                                    'amount' => $annuleAmount,
                                    'color' => 'red',
                                    'icon' => 'cancel'
                                ]
                            ];
                            
                            foreach ($statusCards as $card):
                                $percentage = $yearStats['total_orders'] > 0 ? round(($card['count'] / $yearStats['total_orders']) * 100, 1) : 0;
                            ?>
                                <div class="bg-<?= $card['color'] ?>-50 rounded-xl border border-<?= $card['color'] ?>-200 p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="font-semibold text-<?= $card['color'] ?>-800"><?= $card['title'] ?></h3>
                                        <span class="material-icons text-<?= $card['color'] ?>-500"><?= $card['icon'] ?></span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 mb-3">
                                        <div>
                                            <p class="text-xs text-gray-500">Commandes</p>
                                            <p class="text-lg font-bold text-gray-800"><?= formatNumber($card['count']) ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Montant</p>
                                            <p class="text-lg font-bold text-gray-800"><?= formatNumber($card['amount']) ?></p>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center mb-1">
                                            <p class="text-xs text-gray-500">% des commandes</p>
                                            <p class="text-xs font-medium"><?= $percentage ?>%</p>
                                        </div>
                                        <div class="w-full bg-white rounded-full h-1.5">
                                            <div class="h-1.5 rounded-full bg-<?= $card['color'] ?>-500" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Contenu de l'onglet "Commandes annulées" -->
                <div id="tab-canceled" class="tab-content">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Statistiques des commandes annulées -->
                        <div class="bg-white p-6 shadow-sm rounded-xl border border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                                <span class="material-icons text-red-600 mr-2">cancel</span>
                                Vue d'ensemble des annulations
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <!-- Total commandes annulées -->
                                <div class="p-4 bg-red-50 rounded-lg border border-red-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-medium text-gray-700">Total annulées</h3>
                                        <span class="material-icons text-red-500">cancel</span>
                                    </div>
                                    <p class="text-2xl font-bold text-gray-900">
                                        <?= formatNumber($canceledStats['total_canceled'] ?? 0) ?>
                                    </p>
                                    <p class="text-sm text-gray-500 mt-1">commandes</p>
                                </div>

                                <!-- Projets concernés -->
                                <div class="p-4 bg-blue-50 rounded-lg border border-blue-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-medium text-gray-700">Projets concernés</h3>
                                        <span class="material-icons text-blue-500">business</span>
                                    </div>
                                    <p class="text-2xl font-bold text-gray-900">
                                        <?= formatNumber($canceledStats['projects_count'] ?? 0) ?>
                                    </p>
                                    <p class="text-sm text-gray-500 mt-1">projets</p>
                                </div>
                            </div>

                            <h3 class="font-medium text-gray-700 mb-3 mt-2">Taux d'annulation pour <?= $selectedYear ?></h3>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 mb-4">
                                <?php
                                // Calculer le taux d'annulation (annulées / total)
                                $cancellationRate = ($yearStats['total_orders'] > 0 && isset($canceledStats['total_canceled']))
                                    ? ($canceledStats['total_canceled'] / ($yearStats['total_orders'] + $canceledStats['total_canceled'])) * 100
                                    : 0;
                                ?>
                                <div class="bg-red-500 h-2.5 rounded-full" style="width: <?= $cancellationRate ?>%">
                                </div>
                            </div>
                            <p class="text-sm text-gray-600">
                                <span class="font-medium"><?= number_format($cancellationRate, 1) ?>%</span> des
                                commandes ont été annulées en <?= $selectedYear ?>
                            </p>

                            <div class="mt-6 text-center">
                                <a href="stats_canceled_orders.php" 
                                   class="inline-flex items-center px-4 py-2 bg-red-50 text-red-700 border border-red-300 rounded-lg hover:bg-red-100 transition-colors">
                                    <span class="material-icons mr-2 text-red-600">visibility</span>
                                    Voir l'analyse détaillée
                                </a>
                            </div>
                        </div>

                        <!-- Graphique d'évolution des annulations -->
                        <div class="bg-white p-6 shadow-sm rounded-xl border border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                                <span class="material-icons text-red-600 mr-2">trending_up</span>
                                Évolution mensuelle des annulations
                            </h2>
                            <div class="chart-container-lg">
                                <canvas id="canceledChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Top produits annulés -->
                    <div class="bg-white p-6 shadow-sm rounded-xl mb-6 border border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                            <span class="material-icons text-red-600 mr-2">highlight_off</span>
                            Top 5 des produits les plus annulés
                        </h2>

                        <div class="overflow-x-auto">
                            <table class="data-table min-w-full">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th class="text-center">Nombre d'annulations</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Si aucun produit annulé n'est trouvé, afficher un message
                                    if (empty($topCanceledProducts)):
                                    ?>
                                        <tr>
                                            <td colspan="3" class="py-4 px-4 text-gray-500 text-center">Aucune donnée disponible</td>
                                        </tr>
                                    <?php
                                    else:
                                        foreach ($topCanceledProducts as $index => $product):
                                    ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 font-medium">
                                                <div class="flex items-center">
                                                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-700 text-xs font-bold mr-2">
                                                        <?= $index + 1 ?>
                                                    </span>
                                                    <?= htmlspecialchars($product['designation']) ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-medium">
                                                    <?= formatNumber($product['count']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <button onclick="viewProductDetails('<?= htmlspecialchars($product['designation']) ?>')" 
                                                        class="text-blue-600 hover:text-blue-800 bg-blue-100 hover:bg-blue-200 transition-colors rounded-full p-2">
                                                    <span class="material-icons text-sm">visibility</span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (!empty($topCanceledProducts)): ?>
                            <div class="mt-6">
                                <div class="chart-container" style="height: 250px;">
                                    <canvas id="canceledProductsChart"></canvas>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Contenu de l'onglet "Fournisseurs" -->
                <div id="tab-suppliers" class="tab-content">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Top 5 des fournisseurs -->
                        <div class="bg-white p-6 shadow-sm rounded-xl border border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                                <span class="material-icons text-blue-600 mr-2">business</span>
                                Top 5 des fournisseurs
                            </h2>
                            
                            <?php if (empty($topSuppliers)): ?>
                                <div class="flex flex-col items-center justify-center py-8">
                                    <span class="material-icons text-gray-400 text-5xl mb-4">store</span>
                                    <p class="text-gray-500 text-center">Aucune donnée disponible pour cette période</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="data-table min-w-full">
                                        <thead>
                                            <tr>
                                                <th>Fournisseur</th>
                                                <th class="text-center">Commandes</th>
                                                <th class="text-right">Montant</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topSuppliers as $index => $supplier): ?>
                                                <tr class="hover:bg-gray-50 <?= $index < 3 ? 'bg-indigo-50' : '' ?>">
                                                    <td class="py-3 px-4 font-medium">
                                                        <div class="flex items-center">
                                                            <?php if ($index < 3): ?>
                                                                <span class="flex items-center justify-center w-6 h-6 rounded-full 
                                                                         <?= $index === 0 ? 'bg-indigo-500' : ($index === 1 ? 'bg-indigo-400' : 'bg-indigo-300') ?> 
                                                                         text-white text-xs font-bold mr-2">
                                                                    <?= $index + 1 ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-xs font-bold mr-2">
                                                                    <?= $index + 1 ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?= htmlspecialchars($supplier['fournisseur'] ?: 'Non spécifié') ?>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 px-4 text-center"><?= formatNumber($supplier['order_count']) ?></td>
                                                    <td class="py-3 px-4 text-right font-semibold"><?= formatNumber($supplier['total_amount']) ?> FCFA</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-6">
                                    <div class="chart-container" style="height: 250px;">
                                        <canvas id="topSuppliersChart"></canvas>
                                    </div>
                                </div>
                                
                                <div class="mt-6 text-center">
                                    <a href="stats_fournisseurs.php" 
                                       class="inline-flex items-center px-4 py-2 bg-indigo-50 text-indigo-700 border border-indigo-300 rounded-lg hover:bg-indigo-100 transition-colors">
                                        <span class="material-icons mr-2 text-indigo-600">handshake</span>
                                        Analyse détaillée fournisseurs
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Répartition par fournisseur -->
                        <div class="bg-white p-6 shadow-sm rounded-xl border border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                                <span class="material-icons text-purple-600 mr-2">pie_chart</span>
                                Répartition des achats par fournisseur
                            </h2>
                            <?php if (empty($topSuppliers)): ?>
                                <div class="flex flex-col items-center justify-center py-8">
                                    <span class="material-icons text-gray-400 text-5xl mb-4">donut_large</span>
                                    <p class="text-gray-500 text-center">Aucune donnée disponible pour cette période</p>
                                </div>
                            <?php else: ?>
                                <div class="chart-container-lg">
                                    <canvas id="suppliersDistributionChart"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Informations complémentaires sur les fournisseurs -->
                    <div class="bg-white p-6 shadow-sm rounded-xl border border-gray-100 mb-6">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                            <span class="material-icons text-green-600 mr-2">insights</span>
                            Analyse des performances fournisseurs
                        </h2>
                        
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <span class="material-icons text-blue-600">info</span>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">Information</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p>Cette section présente une analyse détaillée des fournisseurs pour l'année <?= $selectedYear ?>. Pour accéder à une analyse plus complète incluant les délais de livraison, taux de conformité et historique complet, veuillez consulter la page d'analyse des fournisseurs.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="flex items-center bg-gray-50 rounded-xl p-4 border border-gray-200">
                                <span class="material-icons text-blue-600 text-3xl mr-3">supervisor_account</span>
                                <div>
                                    <p class="text-sm text-gray-500">Fournisseurs actifs</p>
                                    <p class="text-xl font-bold"><?= formatNumber($yearStats['supplier_count']) ?></p>
                                    <p class="text-xs text-gray-500">en <?= $selectedYear ?></p>
                                </div>
                            </div>
                            
                            <div class="flex items-center bg-gray-50 rounded-xl p-4 border border-gray-200">
                                <span class="material-icons text-purple-600 text-3xl mr-3">bar_chart</span>
                                <div>
                                    <p class="text-sm text-gray-500">Commandes par fournisseur</p>
                                    <p class="text-xl font-bold"><?= formatNumber($yearStats['total_orders'] / ($yearStats['supplier_count'] ?: 1), 1) ?></p>
                                    <p class="text-xs text-gray-500">moyenne</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center bg-gray-50 rounded-xl p-4 border border-gray-200">
                                <span class="material-icons text-green-600 text-3xl mr-3">monetization_on</span>
                                <div>
                                    <p class="text-sm text-gray-500">Montant moyen</p>
                                    <p class="text-xl font-bold"><?= formatNumber($yearStats['total_amount'] / ($yearStats['supplier_count'] ?: 1)) ?></p>
                                    <p class="text-xs text-gray-500">FCFA par fournisseur</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Navigation rapide vers les autres pages de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">

                <a href="index.php"
                    class="nav-card bg-gray-600 hover:bg-gray-700 text-white p-6 rounded-xl shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">dashboard</span>
                    <h3 class="text-lg font-semibold">Tableau de Bord</h3>
                    <p class="text-sm opacity-80 mt-1">Vue d'ensemble</p>
                </a>

                <a href="stats_fournisseurs.php"
                    class="nav-card bg-purple-600 hover:bg-purple-700 text-white p-6 rounded-xl shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">business</span>
                    <h3 class="text-lg font-semibold">Statistiques des Fournisseurs</h3>
                    <p class="text-sm opacity-80 mt-1">Performance et historique des fournisseurs</p>
                </a>

                <a href="stats_produits.php"
                    class="nav-card bg-green-600 hover:bg-green-700 text-white p-6 rounded-xl shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">inventory</span>
                    <h3 class="text-lg font-semibold">Statistiques des Produits</h3>
                    <p class="text-sm opacity-80 mt-1">Analyse du stock et des mouvements</p>
                </a>

                <a href="stats_projets.php"
                    class="nav-card bg-yellow-600 hover:bg-yellow-700 text-white p-6 rounded-xl shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">folder_special</span>
                    <h3 class="text-lg font-semibold">Statistiques des Projets</h3>
                    <p class="text-sm opacity-80 mt-1">Suivi et analyse des projets clients</p>
                </a>

                <a href="stats_canceled_orders.php"
                    class="nav-card bg-red-600 hover:bg-red-700 text-white p-6 rounded-xl shadow-sm text-center hover:shadow-md transition-all">
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
        // Variables globales
        let monthlyPurchasesChart = null;
        let quarterlyChart = null;
        let quarterlyTrendChart = null;
        let statusChart = null;
        let canceledChart = null;
        let topProductsChart = null;
        let canceledProductsChart = null;
        let topSuppliersChart = null;
        let suppliersDistributionChart = null;
        let miniCharts = {};

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

            // Gestion des onglets
            const tabButtons = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Désactiver tous les onglets
                    tabButtons.forEach(btn => {
                        btn.classList.remove('active');
                    });

                    // Activer l'onglet cliqué
                    button.classList.add('active');

                    // Cacher tous les contenus
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                    });

                    // Afficher le contenu correspondant
                    const tabId = button.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');

                    // Initialiser les graphiques selon l'onglet
                    if (tabId === 'tab-overview') {
                        initOverviewCharts();
                    } else if (tabId === 'tab-quarterly') {
                        initQuarterlyCharts();
                    } else if (tabId === 'tab-status') {
                        initStatusCharts();
                    } else if (tabId === 'tab-canceled') {
                        initCanceledCharts();
                    } else if (tabId === 'tab-suppliers') {
                        initSuppliersCharts();
                    }
                });
            });

            // Initialiser les graphiques au chargement de la page
            initOverviewCharts();
            initMiniCharts();
            
            // Config du bouton d'export PDF
            document.getElementById('export-pdf').addEventListener('click', handleExportPDF);
        });

        // Initialisation des mini-graphiques
        function initMiniCharts() {
            // Mini-graphique des commandes
            if (document.getElementById('ordersChart')) {
                const ordersData = <?= json_encode($trendData['orders'] ?? []) ?>;
                if (ordersData.length > 0) {
                    createMiniChart('ordersChart', ordersData, 'up');
                }
            }
            
            // Mini-graphique des montants
            if (document.getElementById('amountsChart')) {
                const amountsData = <?= json_encode($trendData['amounts'] ?? []) ?>;
                if (amountsData.length > 0) {
                    createMiniChart('amountsChart', amountsData, 'up');
                }
            }
            
            // Mini-graphique des prix
            if (document.getElementById('pricesChart')) {
                const pricesData = <?= json_encode($trendData['prices'] ?? []) ?>;
                if (pricesData.length > 0) {
                    createMiniChart('pricesChart', pricesData, <?= ($priceVariation ?? 0) > 0 ? "'up'" : "'down'" ?>);
                }
            }
        }

        // Graphiques de l'onglet "Vue d'ensemble"
        function initOverviewCharts() {
            // Graphique des achats mensuels
            initMonthlyPurchasesChart();
            
            // Graphique des top produits
            initTopProductsChart();
        }

       // Graphique des achats mensuels
       function initMonthlyPurchasesChart() {
            const ctx = document.getElementById('monthlyPurchasesChart').getContext('2d');
            const monthlyData = <?= json_encode($formattedMonthlyData) ?>;
            const viewMode = "<?= $viewMode ?>";
            const selectedYear = <?= $selectedYear ?>;
            
            // Détruire le graphique existant s'il existe
            if (monthlyPurchasesChart) {
                monthlyPurchasesChart.destroy();
            }
            
            // Préparer les données
            const labels = monthlyData.map(item => item.month_name);
            const amounts = monthlyData.map(item => parseFloat(item.total));
            const counts = monthlyData.map(item => parseInt(item.count));
            const quantities = monthlyData.map(item => parseFloat(item.quantity_sum));
            
            // Création des datasets en fonction du mode de vue
            let datasets = [];
            
            if (viewMode === 'amount' || viewMode === 'both') {
                // Définir le gradient pour l'arrière-plan
                const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
                gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');
                
                datasets.push({
                    label: 'Montant total (FCFA)',
                    data: amounts,
                    type: 'line',
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y',
                    pointBackgroundColor: 'white',
                    pointBorderColor: 'rgb(59, 130, 246)',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                });
            }
            
            if (viewMode === 'count' || viewMode === 'both') {
                datasets.push({
                    label: 'Nombre de commandes',
                    data: counts,
                    type: viewMode === 'both' ? 'line' : 'bar',
                    borderColor: 'rgb(139, 92, 246)',
                    backgroundColor: viewMode === 'both' ? 'rgba(139, 92, 246, 0.1)' : 'rgba(139, 92, 246, 0.7)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    borderDash: viewMode === 'both' ? [5, 5] : [],
                    yAxisID: viewMode === 'both' ? 'y1' : 'y',
                    pointBackgroundColor: 'white',
                    pointBorderColor: 'rgb(139, 92, 246)',
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5
                });
            }
            
            // Si le mode est 'count', ajouter aussi les quantités
            if (viewMode === 'count') {
                datasets.push({
                    label: 'Quantité totale',
                    data: quantities,
                    type: 'line',
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1',
                    pointBackgroundColor: 'white',
                    pointBorderColor: 'rgb(16, 185, 129)',
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5
                });
            }
            
            // Configuration du graphique
            monthlyPurchasesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart',
                        delay: (context) => context.dataIndex * 100
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: `Évolution mensuelle des achats - ${selectedYear}`,
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                bottom: 20
                            }
                        },
                        tooltip: {
                            usePointStyle: true,
                            backgroundColor: 'rgba(17, 24, 39, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function (context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.dataset.label.includes('Montant')) {
                                        label += formatMoney(context.raw);
                                    } else {
                                        label += context.raw;
                                    }
                                    return label;
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8,
                                padding: 15
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12
                                }
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: viewMode === 'count' ? 'Nombre' : 'Montant (FCFA)',
                                color: '#64748b',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12,
                                    weight: 'normal'
                                }
                            },
                            ticks: {
                                callback: function (value) {
                                    if (viewMode === 'count') {
                                        return value;
                                    }
                                    return formatMoney(value, false);
                                },
                                color: '#64748b',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12
                                }
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.7)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: viewMode === 'both' || viewMode === 'count',
                            position: 'right',
                            title: {
                                display: true,
                                text: viewMode === 'count' && quantities.some(q => q > 0) ? 'Quantité' : 'Nombre',
                                color: '#64748b',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12,
                                    weight: 'normal'
                                }
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12
                                }
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }

        // Graphique des top produits
        function initTopProductsChart() {
            const topProductsData = <?= json_encode($topProducts) ?>;
            
            if (!topProductsData || topProductsData.length === 0 || !document.getElementById('topProductsChart')) {
                return;
            }
            
            const ctx = document.getElementById('topProductsChart').getContext('2d');
            
            // Préparer les données (limité aux 5 premiers produits)
            const top5 = topProductsData.slice(0, 5);
            const labels = top5.map(product => {
                // Tronquer le nom s'il est trop long
                const name = product.designation;
                return name.length > 25 ? name.substring(0, 22) + '...' : name;
            });
            const values = top5.map(product => parseFloat(product.total_amount));
            
            // Créer une palette de couleurs
            const colors = [
                'rgba(59, 130, 246, 0.7)',
                'rgba(16, 185, 129, 0.7)',
                'rgba(245, 158, 11, 0.7)',
                'rgba(99, 102, 241, 0.7)',
                'rgba(236, 72, 153, 0.7)'
            ];
            
            // Configuration du graphique
            topProductsChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
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
                        duration: 1500
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 15,
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            usePointStyle: true,
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${formatMoney(value)} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        // Graphiques de l'onglet "Analyse trimestrielle"
        function initQuarterlyCharts() {
            if (!quarterlyChart) {
                initQuarterlyChart();
            }
            
            if (!quarterlyTrendChart) {
                initQuarterlyTrendChart();
            }
        }

        // Graphique par trimestre
        function initQuarterlyChart() {
            // Détruire le graphique existant s'il existe
            if (quarterlyChart) {
                quarterlyChart.destroy();
            }

            const quarterlyData = <?= json_encode($quarterlyStats) ?>;
            const quarterNames = ['1er Trimestre', '2ème Trimestre', '3ème Trimestre', '4ème Trimestre'];
            const amounts = [0, 0, 0, 0];
            const orders = [0, 0, 0, 0];

            // Remplir les données
            quarterlyData.forEach(quarter => {
                const index = parseInt(quarter.quarter) - 1;
                if (index >= 0 && index < 4) {
                    amounts[index] = parseFloat(quarter.amount);
                    orders[index] = parseInt(quarter.orders);
                }
            });

            const ctx = document.getElementById('quarterlyChart').getContext('2d');

            // Définir des couleurs pour chaque trimestre
            const bgColors = [
                'rgba(59, 130, 246, 0.7)',
                'rgba(16, 185, 129, 0.7)',
                'rgba(245, 158, 11, 0.7)',
                'rgba(239, 68, 68, 0.7)'
            ];
            
            const borderColors = [
                'rgb(59, 130, 246)',
                'rgb(16, 185, 129)',
                'rgb(245, 158, 11)',
                'rgb(239, 68, 68)'
            ];

            // Utiliser le mot-clé 'new' ici
            quarterlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: quarterNames,
                    datasets: [
                        {
                            label: 'Montant (FCFA)',
                            data: amounts,
                            backgroundColor: bgColors,
                            borderColor: borderColors,
                            borderWidth: 1,
                            yAxisID: 'y',
                            borderRadius: 6
                        },
                        {
                            label: 'Nombre de commandes',
                            data: orders,
                            type: 'line',
                            borderColor: 'rgb(107, 114, 128)',
                            backgroundColor: 'rgba(107, 114, 128, 0.1)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            yAxisID: 'y1'
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
                        title: {
                            display: true,
                            text: 'Comparaison trimestrielle des achats',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                bottom: 20
                            }
                        },
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            usePointStyle: true,
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
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Montant (FCFA)',
                                color: '#64748b',
                                font: {
                                    size: 12,
                                    weight: 'normal'
                                }
                            },
                            ticks: {
                                callback: function (value) {
                                    return formatMoney(value, false);
                                },
                                color: '#64748b',
                                font: {
                                    size: 12
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
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Nombre de commandes',
                                color: '#64748b',
                                font: {
                                    size: 12,
                                    weight: 'normal'
                                }
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }

        // Graphique de tendance trimestrielle
        function initQuarterlyTrendChart() {
            // Détruire le graphique existant s'il existe
            if (quarterlyTrendChart) {
                quarterlyTrendChart.destroy();
            }

            const quarterlyData = <?= json_encode($quarterlyStats) ?>;
            const quarterNames = ['1er Trimestre', '2ème Trimestre', '3ème Trimestre', '4ème Trimestre'];
            const amounts = [0, 0, 0, 0];
            const percentage = [0, 0, 0, 0];
            let total = 0;

            // Calculer le montant total
            quarterlyData.forEach(quarter => {
                const index = parseInt(quarter.quarter) - 1;
                if (index >= 0 && index < 4) {
                    const amount = parseFloat(quarter.amount);
                    amounts[index] = amount;
                    total += amount;
                }
            });

            // Calculer les pourcentages
            if (total > 0) {
                for (let i = 0; i < 4; i++) {
                    percentage[i] = (amounts[i] / total) * 100;
                }
            }

            const ctx = document.getElementById('quarterlyTrendChart').getContext('2d');

            // Définir des couleurs pour chaque trimestre
            const bgColors = [
                'rgba(59, 130, 246, 0.2)',
                'rgba(16, 185, 129, 0.2)',
                'rgba(245, 158, 11, 0.2)',
                'rgba(239, 68, 68, 0.2)'
            ];
            
            const borderColors = [
                'rgb(59, 130, 246)',
                'rgb(16, 185, 129)',
                'rgb(245, 158, 11)',
                'rgb(239, 68, 68)'
            ];

            // Créer le graphique
            quarterlyTrendChart = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: quarterNames,
                    datasets: [{
                        label: 'Pourcentage du total',
                        data: percentage,
                        backgroundColor: 'rgba(139, 92, 246, 0.3)',
                        borderColor: 'rgb(139, 92, 246)',
                        borderWidth: 2,
                        pointBackgroundColor: borderColors,
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgb(139, 92, 246)',
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1500,
                        easing: 'easeOutCubic'
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Répartition des achats par trimestre (%)',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                bottom: 20
                            }
                        },
                        legend: {
                            display: false
                        },
                        tooltip: {
                            usePointStyle: true,
                            callbacks: {
                                label: function (context) {
                                    const index = context.dataIndex;
                                    const percent = context.raw.toFixed(1);
                                    const amount = formatMoney(amounts[index]);
                                    return [
                                        `${percent}% du total`,
                                        `Montant: ${amount}`
                                    ];
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
                            beginAtZero: true,
                            suggestedMax: Math.max(...percentage) * 1.1,
                            ticks: {
                                stepSize: 10,
                                callback: function(value) {
                                    return value + '%';
                                },
                                color: '#64748b',
                                backdropColor: 'transparent'
                            },
                            pointLabels: {
                                color: '#334155',
                                font: {
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                }
            });
        }

        // Graphiques de l'onglet "Statut des commandes"
        function initStatusCharts() {
            if (!statusChart) {
                initStatusChart();
            }
        }

        // Graphique de statut des commandes
        function initStatusChart() {
            // Détruire le graphique existant s'il existe
            if (statusChart) {
                statusChart.destroy();
            }

            const statusData = <?= json_encode($statusStats) ?>;
            const labels = [];
            const values = [];
            const colors = [
                'rgba(16, 185, 129, 0.7)',  // reçu (vert)
                'rgba(59, 130, 246, 0.7)',  // commandé (bleu)
                'rgba(245, 158, 11, 0.7)',  // en attente (jaune)
                'rgba(239, 68, 68, 0.7)',   // annulé (rouge)
                'rgba(107, 114, 128, 0.7)'  // autre (gris)
            ];

            statusData.forEach(status => {
                labels.push(status.status || 'Non défini');
                values.push(parseFloat(status.amount));
            });

            const ctx = document.getElementById('statusChart').getContext('2d');

            // Utiliser le mot-clé 'new' ici
            statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors.slice(0, values.length),
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
                        duration: 1500
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Répartition des achats par statut',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                bottom: 20
                            }
                        },
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            usePointStyle: true,
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${formatMoney(value)} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        // Graphiques de l'onglet "Commandes annulées"
        function initCanceledCharts() {
            if (!canceledChart) {
                initCanceledChart();
            }
            
            if (!canceledProductsChart && document.getElementById('canceledProductsChart')) {
                initCanceledProductsChart();
            }
        }

        // Graphique d'évolution des annulations
        function initCanceledChart() {
            // Détruire le graphique existant s'il existe
            if (canceledChart) {
                canceledChart.destroy();
            }

            const canceledData = <?= json_encode($formattedCanceledData) ?>;
            const labels = canceledData.map(item => item.month_name);
            const values = canceledData.map(item => item.count);

            const ctx = document.getElementById('canceledChart').getContext('2d');

            // Définir le gradient pour l'arrière-plan
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(239, 68, 68, 0.2)');
            gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)');

            // Utiliser le mot-clé 'new' ici
            canceledChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Commandes annulées',
                        data: values,
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: gradient,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: 'white',
                        pointBorderColor: 'rgb(239, 68, 68)',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Évolution mensuelle des annulations',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                bottom: 20
                            }
                        },
                        legend: {
                            display: false
                        },
                        tooltip: {
                            usePointStyle: true,
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre d\'annulations',
                                color: '#64748b',
                                font: {
                                    size: 12,
                                    weight: 'normal'
                                }
                            },
                            ticks: {
                                precision: 0,
                                color: '#64748b',
                                font: {
                                    size: 12
                                }
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.7)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }

        // Graphique des produits annulés
        function initCanceledProductsChart() {
            const topCanceledProducts = <?= json_encode($topCanceledProducts) ?>;
            
            if (!topCanceledProducts || topCanceledProducts.length === 0 || !document.getElementById('canceledProductsChart')) {
                return;
            }
            
            // Détruire le graphique existant s'il existe
            if (canceledProductsChart) {
                canceledProductsChart.destroy();
            }
            
            const ctx = document.getElementById('canceledProductsChart').getContext('2d');
            
            // Préparer les données
            const labels = topCanceledProducts.map(product => {
                // Tronquer le nom s'il est trop long
                const name = product.designation;
                return name.length > 25 ? name.substring(0, 22) + '...' : name;
            });
            const values = topCanceledProducts.map(product => parseInt(product.count));
            
            // Créer une palette de couleurs rouges
            const colors = [
                'rgba(239, 68, 68, 0.8)',
                'rgba(220, 38, 38, 0.7)',
                'rgba(248, 113, 113, 0.6)',
                'rgba(254, 202, 202, 0.5)',
                'rgba(254, 226, 226, 0.4)'
            ];
            
            // Configuration du graphique
            canceledProductsChart = new Chart(ctx, {
                type: 'horizontalBar', // Utiliser un graphique horizontal
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nombre d\'annulations',
                        data: values,
                        backgroundColor: colors,
                        borderColor: 'white',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y', // Graphique horizontal
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
                            usePointStyle: true
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: '#64748b',
                                font: {
                                    size: 12
                                }
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.7)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Graphiques de l'onglet "Fournisseurs"
        function initSuppliersCharts() {
            if (document.getElementById('topSuppliersChart') && !topSuppliersChart) {
                initTopSuppliersChart();
            }
            
            if (document.getElementById('suppliersDistributionChart') && !suppliersDistributionChart) {
                initSuppliersDistributionChart();
            }
        }
        
        // Graphique des top fournisseurs
        function initTopSuppliersChart() {
            const topSuppliers = <?= json_encode($topSuppliers ?? []) ?>;
            
            if (!topSuppliers || topSuppliers.length === 0 || !document.getElementById('topSuppliersChart')) {
                return;
            }
            
            // Détruire le graphique existant s'il existe
            if (topSuppliersChart) {
                topSuppliersChart.destroy();
            }
            
            const ctx = document.getElementById('topSuppliersChart').getContext('2d');
            
            // Préparer les données
            const labels = topSuppliers.slice(0, 5).map(supplier => {
                // Tronquer le nom s'il est trop long
                const name = supplier.fournisseur || 'Non spécifié';
                return name.length > 20 ? name.substring(0, 17) + '...' : name;
            });
            const values = topSuppliers.slice(0, 5).map(supplier => parseFloat(supplier.total_amount));
            
            // Créer une palette de couleurs
            const colors = [
                'rgba(99, 102, 241, 0.8)', // Indigo
                'rgba(79, 70, 229, 0.7)',
                'rgba(124, 58, 237, 0.6)',
                'rgba(139, 92, 246, 0.5)',
                'rgba(167, 139, 250, 0.4)'
            ];
            
            // Configuration du graphique
            topSuppliersChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Montant total (FCFA)',
                        data: values,
                        backgroundColor: colors,
                        borderColor: 'white',
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y', // Graphique horizontal
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
                            usePointStyle: true,
                            callbacks: {
                                label: function (context) {
                                    return `Montant: ${formatMoney(context.raw)}`;
                                },
                                afterLabel: function (context) {
                                    const index = context.dataIndex;
                                    return `Commandes: ${topSuppliers[index].order_count}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Montant (FCFA)',
                                color: '#64748b',
                                font: {
                                    size: 12,
                                    weight: 'normal'
                                }
                            },
                            ticks: {
                                callback: function (value) {
                                    return formatMoney(value, false);
                                },
                                color: '#64748b',
                                font: {
                                    size: 12
                                }
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.7)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Graphique de distribution des fournisseurs
        function initSuppliersDistributionChart() {
            const topSuppliers = <?= json_encode($topSuppliers ?? []) ?>;
            
            if (!topSuppliers || topSuppliers.length === 0 || !document.getElementById('suppliersDistributionChart')) {
                return;
            }
            
            // Détruire le graphique existant s'il existe
            if (suppliersDistributionChart) {
                suppliersDistributionChart.destroy();
            }
            
            const ctx = document.getElementById('suppliersDistributionChart').getContext('2d');
            
            // Préparer les données
            const labels = topSuppliers.map(supplier => supplier.fournisseur || 'Non spécifié');
            const values = topSuppliers.map(supplier => parseFloat(supplier.total_amount));
            
            // Créer une palette de couleurs
            const colors = [
                'rgba(99, 102, 241, 0.7)',
                'rgba(139, 92, 246, 0.7)',
                'rgba(167, 139, 250, 0.7)',
                'rgba(196, 181, 253, 0.7)',
                'rgba(224, 231, 255, 0.7)',
                'rgba(79, 70, 229, 0.7)',
                'rgba(124, 58, 237, 0.7)',
                'rgba(109, 40, 217, 0.7)',
                'rgba(91, 33, 182, 0.7)',
                'rgba(67, 56, 202, 0.7)'
            ];
            
            // Configuration du graphique
            suppliersDistributionChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors.slice(0, values.length),
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
                        duration: 1500
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 15,
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            usePointStyle: true,
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${formatMoney(value)} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        // Fonction pour formater l'argent
        function formatMoney(amount, includeCurrency = true) {
            const formattedAmount = new Intl.NumberFormat('fr-FR', {
                maximumFractionDigits: 0
            }).format(amount);
            
            return includeCurrency ? formattedAmount + ' FCFA' : formattedAmount;
        }

        // Fonction pour voir les détails d'un produit
        function viewProductDetails(designation) {
            window.location.href = "../stock/inventory.php?search=" + encodeURIComponent(designation);
        }

        // Export PDF
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

            // Rediriger vers le script de génération de PDF
            setTimeout(() => {
                window.location.href = `generate_report.php?type=achats&year=<?= $selectedYear ?>&view=<?= $viewMode ?>`;
            }, 1500);
        }
        
        // Fonction pour créer des mini-graphiques
        function createMiniChart(canvasId, data, trend = 'neutral') {
            if (!document.getElementById(canvasId)) return;
            
            const ctx = document.getElementById(canvasId).getContext('2d');
            
            // Déterminer la couleur en fonction de la tendance
            let color;
            switch (trend) {
                case 'up':
                    color = '#10b981'; // vert
                    break;
                case 'down':
                    color = '#ef4444'; // rouge
                    break;
                default:
                    color = '#3b82f6'; // bleu (neutre)
            }
            
            // Configuration du graphique
            miniCharts[canvasId] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: Array(data.length).fill(''),
                    datasets: [{
                        data: data,
                        borderColor: color,
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointRadius: 0,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            enabled: false
                        }
                    },
                    scales: {
                        x: {
                            display: false
                        },
                        y: {
                            display: false,
                            min: Math.min(...data) * 0.9,
                            max: Math.max(...data) * 1.1
                        }
                    },
                    elements: {
                        line: {
                            tension: 0.4
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>