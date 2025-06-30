<?php

/**
 * Module de statistiques des commandes annulées - VERSION OPTIMISÉE
 * Affiche l'analyse détaillée des commandes annulées avec graphiques et tableaux
 * 
 * @package DYM_MANUFACTURE
 * @subpackage expressions_besoins/User-Achat/statistics
 * @version 2.1.0
 * @author Équipe DYM
 */

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
require_once '../../components/config.php';
$base_url = PROJECT_ROOT;
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Récupérer les filtres
$selectedPeriod = isset($_GET['period']) ? $_GET['period'] : '6months';
$selectedProject = isset($_GET['project']) ? $_GET['project'] : 'all';
$selectedReason = isset($_GET['reason']) ? $_GET['reason'] : 'all';

// Fonction pour formater les nombres
function formatNumber($number, $decimals = 0)
{
    if ($number === null || $number === '') return '0';
    return number_format((float)$number, $decimals, ',', ' ');
}

// Fonction pour obtenir la condition de période
function getPeriodCondition($period)
{
    switch ($period) {
        case 'today':
            return "AND DATE(ed.updated_at) = CURDATE()";
        case 'week':
            return "AND ed.updated_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        case 'month':
            return "AND ed.updated_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        case '3months':
            return "AND ed.updated_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        case '6months':
            return "AND ed.updated_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        case 'year':
            return "AND ed.updated_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        default:
            return ""; // Toutes les périodes
    }
}



try {
    // ========================================
    // STATISTIQUES PRINCIPALES
    // ========================================

    $periodCondition = getPeriodCondition($selectedPeriod);
    $projectCondition = ($selectedProject != 'all') ? "AND ip.code_projet = :project" : "";

    // 1. Nombre total de commandes annulées
    $totalQuery = "SELECT COUNT(*) as total 
                   FROM expression_dym ed
                   JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                   WHERE ed.valide_achat = 'annulé' 
                   $periodCondition $projectCondition";
    $stmt = $pdo->prepare($totalQuery);
    if ($selectedProject != 'all') {
        $stmt->bindParam(':project', $selectedProject);
    }
    $stmt->execute();
    $totalCanceled = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // 2. Montant total des commandes annulées
    $amountQuery = "SELECT COALESCE(SUM(CAST(ed.qt_acheter AS DECIMAL(10,2)) * CAST(ed.prix_unitaire AS DECIMAL(10,2))), 0) as total_amount 
                    FROM expression_dym ed
                    JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                    WHERE ed.valide_achat = 'annulé' 
                    AND ed.prix_unitaire IS NOT NULL 
                    AND ed.qt_acheter IS NOT NULL
                    $periodCondition $projectCondition";
    $stmt = $pdo->prepare($amountQuery);
    if ($selectedProject != 'all') {
        $stmt->bindParam(':project', $selectedProject);
    }
    $stmt->execute();
    $totalAmount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'];

    // 3. Nombre de projets concernés
    $projectsQuery = "SELECT COUNT(DISTINCT ip.code_projet) as count 
                      FROM expression_dym ed
                      JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                      WHERE ed.valide_achat = 'annulé' 
                      $periodCondition $projectCondition";
    $stmt = $pdo->prepare($projectsQuery);
    if ($selectedProject != 'all') {
        $stmt->bindParam(':project', $selectedProject);
    }
    $stmt->execute();
    $projectsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // 4. Taux d'annulation
    $rateQuery = "SELECT 
                    COUNT(CASE WHEN ed.valide_achat = 'annulé' THEN 1 END) as canceled,
                    COUNT(*) as total
                  FROM expression_dym ed
                  JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                  WHERE ed.qt_acheter > 0
                  $periodCondition $projectCondition";
    $stmt = $pdo->prepare($rateQuery);
    if ($selectedProject != 'all') {
        $stmt->bindParam(':project', $selectedProject);
    }
    $stmt->execute();
    $rateData = $stmt->fetch(PDO::FETCH_ASSOC);
    $cancellationRate = $rateData['total'] > 0 ? round(($rateData['canceled'] / $rateData['total']) * 100, 2) : 0;

    // 5. Date de la dernière annulation
    $lastQuery = "SELECT MAX(ed.updated_at) as last_canceled 
                  FROM expression_dym ed
                  JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                  WHERE ed.valide_achat = 'annulé'
                  $periodCondition $projectCondition";
    $stmt = $pdo->prepare($lastQuery);
    if ($selectedProject != 'all') {
        $stmt->bindParam(':project', $selectedProject);
    }
    $stmt->execute();
    $lastCanceledDate = $stmt->fetch(PDO::FETCH_ASSOC)['last_canceled'];

    // ========================================
    // DONNÉES POUR LES GRAPHIQUES
    // ========================================

    // Top 10 des produits les plus annulés
    $topProductsQuery = "SELECT ed.designation, COUNT(*) as count,
                         COALESCE(SUM(CAST(ed.qt_acheter AS DECIMAL(10,2)) * CAST(ed.prix_unitaire AS DECIMAL(10,2))), 0) as total_value
                         FROM expression_dym ed
                         JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                         WHERE ed.valide_achat = 'annulé' 
                         $periodCondition $projectCondition
                         GROUP BY ed.designation 
                         ORDER BY count DESC 
                         LIMIT 10";
    $stmt = $pdo->prepare($topProductsQuery);
    if ($selectedProject != 'all') {
        $stmt->bindParam(':project', $selectedProject);
    }
    $stmt->execute();
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tendance mensuelle des 12 derniers mois
    $monthlyQuery = "SELECT 
                     DATE_FORMAT(ed.updated_at, '%Y-%m') as month,
                     DATE_FORMAT(ed.updated_at, '%M %Y') as month_name,
                     COUNT(*) as count,
                     COALESCE(SUM(CAST(ed.qt_acheter AS DECIMAL(10,2)) * CAST(ed.prix_unitaire AS DECIMAL(10,2))), 0) as amount
                     FROM expression_dym ed
                     JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                     WHERE ed.valide_achat = 'annulé' 
                     AND ed.updated_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                     $projectCondition
                     GROUP BY DATE_FORMAT(ed.updated_at, '%Y-%m'), DATE_FORMAT(ed.updated_at, '%M %Y')
                     ORDER BY month";
    $stmt = $pdo->prepare($monthlyQuery);
    if ($selectedProject != 'all') {
        $stmt->bindParam(':project', $selectedProject);
    }
    $stmt->execute();
    $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Répartition par raisons d'annulation (simulée - à adapter selon votre système)
    $reasonsData = [
        ['reason' => 'Changement de spécifications', 'count' => rand(10, 50)],
        ['reason' => 'Produit non disponible', 'count' => rand(5, 30)],
        ['reason' => 'Prix trop élevé', 'count' => rand(3, 25)],
        ['reason' => 'Délai trop long', 'count' => rand(2, 20)],
        ['reason' => 'Erreur de commande', 'count' => rand(1, 15)],
    ];

    // Projets avec le plus d'annulations
    $topProjectsQuery = "SELECT 
                         ip.code_projet,
                         ip.nom_client,
                         COUNT(ed.id) as canceled_count,
                         COALESCE(SUM(CAST(ed.qt_acheter AS DECIMAL(10,2)) * CAST(ed.prix_unitaire AS DECIMAL(10,2))), 0) as total_value,
                         MAX(ed.updated_at) as last_canceled
                         FROM expression_dym ed
                         JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                         WHERE ed.valide_achat = 'annulé'
                         $periodCondition
                         GROUP BY ip.code_projet, ip.nom_client
                         ORDER BY canceled_count DESC 
                         LIMIT 10";
    $stmt = $pdo->prepare($topProjectsQuery);
    $stmt->execute();
    $topProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Liste des projets pour le filtre
    $projectsListQuery = "SELECT DISTINCT ip.code_projet, ip.nom_client
                          FROM expression_dym ed
                          JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                          WHERE ed.valide_achat = 'annulé'
                          ORDER BY ip.code_projet";
    $stmt = $pdo->prepare($projectsListQuery);
    $stmt->execute();
    $projectsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Commandes annulées récentes (dernières 20)
    $recentOrdersQuery = "SELECT 
                          ed.id,
                          ed.designation,
                          ed.quantity,
                          ed.unit,
                          ed.qt_acheter,
                          ed.prix_unitaire,
                          ed.fournisseur,
                          ed.updated_at,
                          ip.code_projet,
                          ip.nom_client,
                          ip.idExpression
                          FROM expression_dym ed
                          JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                          WHERE ed.valide_achat = 'annulé'
                          $periodCondition $projectCondition
                          ORDER BY ed.updated_at DESC 
                          LIMIT 20";
    $stmt = $pdo->prepare($recentOrdersQuery);
    if ($selectedProject != 'all') {
        $stmt->bindParam(':project', $selectedProject);
    }
    $stmt->execute();
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des statistiques : " . $e->getMessage());
    // Initialiser les variables avec des valeurs par défaut
    $totalCanceled = 0;
    $totalAmount = 0;
    $projectsCount = 0;
    $cancellationRate = 0;
    $lastCanceledDate = null;
    $topProducts = [];
    $monthlyTrend = [];
    $reasonsData = [];
    $topProjects = [];
    $projectsList = [];
    $recentOrders = [];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des Commandes Annulées | Service Achat</title>

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .dashboard-card {
            transition: all 0.3s ease;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .trend-up {
            color: #10b981;
        }

        .trend-down {
            color: #ef4444;
        }

        .trend-neutral {
            color: #6b7280;
        }

        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            display: inline-block;
        }

        .status-canceled {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .kpi-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .kpi-icon {
            width: 60px;
            height: 60px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .kpi-value {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .kpi-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .kpi-trend {
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- Header de la page -->
            <div class="bg-white shadow-sm rounded-lg p-4 mb-6">
                <div class="flex flex-wrap justify-between items-center">
                    <div class="flex items-center mb-2 md:mb-0">
                        <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-2">
                            <span class="material-icons">arrow_back</span>
                        </a>
                        <h1 class="text-xl font-bold text-gray-800 flex items-center">
                            <span class="material-icons mr-3 text-red-600">cancel</span>
                            Statistiques des Commandes Annulées
                            <span class="ml-2 px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                <?php echo formatNumber($totalCanceled); ?> annulations
                            </span>
                        </h1>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button id="export-pdf" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded flex items-center">
                            <span class="material-icons mr-2">picture_as_pdf</span>
                            Exporter PDF
                        </button>

                        <div class="flex items-center bg-gray-100 px-4 py-2 rounded">
                            <span class="material-icons mr-2">event</span>
                            <span id="date-time-display"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="bg-white shadow-sm rounded-lg p-4 mb-6">
                <h2 class="text-lg font-semibold mb-4">Filtres</h2>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Filtre par période -->
                    <div>
                        <label for="period" class="block text-sm font-medium text-gray-700 mb-1">Période:</label>
                        <select name="period" id="period" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="all" <?php echo $selectedPeriod == 'all' ? 'selected' : ''; ?>>Toutes les périodes</option>
                            <option value="today" <?php echo $selectedPeriod == 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                            <option value="week" <?php echo $selectedPeriod == 'week' ? 'selected' : ''; ?>>Cette semaine</option>
                            <option value="month" <?php echo $selectedPeriod == 'month' ? 'selected' : ''; ?>>Ce mois</option>
                            <option value="3months" <?php echo $selectedPeriod == '3months' ? 'selected' : ''; ?>>3 derniers mois</option>
                            <option value="6months" <?php echo $selectedPeriod == '6months' ? 'selected' : ''; ?>>6 derniers mois</option>
                            <option value="year" <?php echo $selectedPeriod == 'year' ? 'selected' : ''; ?>>Cette année</option>
                        </select>
                    </div>

                    <!-- Filtre par projet -->
                    <div>
                        <label for="project" class="block text-sm font-medium text-gray-700 mb-1">Projet:</label>
                        <select name="project" id="project" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="all" <?php echo $selectedProject == 'all' ? 'selected' : ''; ?>>Tous les projets</option>
                            <?php foreach ($projectsList as $project): ?>
                                <option value="<?php echo htmlspecialchars($project['code_projet']); ?>" <?php echo $selectedProject == $project['code_projet'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['code_projet']) . ' - ' . htmlspecialchars($project['nom_client']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Bouton de filtrage -->
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded flex items-center justify-center">
                            <span class="material-icons mr-2">filter_alt</span>
                            Filtrer
                        </button>
                    </div>
                </form>
            </div>

            <!-- Cartes de statistiques principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
                <!-- KPI 1: Total des commandes annulées -->
                <div class="kpi-card">
                    <div class="kpi-icon bg-red-100">
                        <span class="material-icons text-red-600">cancel</span>
                    </div>
                    <div class="kpi-value"><?php echo formatNumber($totalCanceled); ?></div>
                    <div class="kpi-label">Commandes annulées</div>
                    <div class="kpi-trend trend-neutral">
                        <span class="material-icons text-sm">info</span>
                        Total historique
                    </div>
                </div>

                <!-- KPI 2: Montant total annulé -->
                <div class="kpi-card">
                    <div class="kpi-icon bg-purple-100">
                        <span class="material-icons text-purple-600">monetization_on</span>
                    </div>
                    <div class="kpi-value"><?php echo formatNumber($totalAmount); ?></div>
                    <div class="kpi-label">Montant total (FCFA)</div>
                    <div class="kpi-trend trend-down">
                        <span class="material-icons text-sm">trending_down</span>
                        Valeur perdue
                    </div>
                </div>

                <!-- KPI 3: Projets concernés -->
                <div class="kpi-card">
                    <div class="kpi-icon bg-yellow-100">
                        <span class="material-icons text-yellow-600">folder_off</span>
                    </div>
                    <div class="kpi-value"><?php echo formatNumber($projectsCount); ?></div>
                    <div class="kpi-label">Projets concernés</div>
                    <div class="kpi-trend trend-neutral">
                        <span class="material-icons text-sm">folder</span>
                        Avec annulations
                    </div>
                </div>

                <!-- KPI 4: Taux d'annulation -->
                <div class="kpi-card">
                    <div class="kpi-icon bg-orange-100">
                        <span class="material-icons text-orange-600">percent</span>
                    </div>
                    <div class="kpi-value"><?php echo $cancellationRate; ?>%</div>
                    <div class="kpi-label">Taux d'annulation</div>
                    <div class="kpi-trend <?php echo $cancellationRate > 10 ? 'trend-down' : ($cancellationRate < 5 ? 'trend-up' : 'trend-neutral'); ?>">
                        <span class="material-icons text-sm">
                            <?php echo $cancellationRate > 10 ? 'trending_up' : ($cancellationRate < 5 ? 'trending_down' : 'remove'); ?>
                        </span>
                        <?php echo $cancellationRate > 10 ? 'Élevé' : ($cancellationRate < 5 ? 'Faible' : 'Moyen'); ?>
                    </div>
                </div>

                <!-- KPI 5: Dernière annulation -->
                <div class="kpi-card">
                    <div class="kpi-icon bg-blue-100">
                        <span class="material-icons text-blue-600">schedule</span>
                    </div>
                    <div class="kpi-value text-lg">
                        <?php echo $lastCanceledDate ? date('d/m/Y', strtotime($lastCanceledDate)) : 'N/A'; ?>
                    </div>
                    <div class="kpi-label">Dernière annulation</div>
                    <div class="kpi-trend trend-neutral">
                        <span class="material-icons text-sm">event</span>
                        <?php echo $lastCanceledDate ? 'Il y a ' . round((time() - strtotime($lastCanceledDate)) / 86400) . ' jours' : 'Aucune'; ?>
                    </div>
                </div>
            </div>

            <!-- Graphiques principaux -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Graphique 1: Tendance mensuelle -->
                <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <span class="material-icons mr-2 text-blue-600">timeline</span>
                        Tendance des annulations (12 derniers mois)
                    </h3>
                    <div class="chart-container">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>

                <!-- Graphique 2: Top produits annulés -->
                <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <span class="material-icons mr-2 text-red-600">inventory_2</span>
                        Top 10 des produits les plus annulés
                    </h3>
                    <div class="chart-container">
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Deuxième rang de graphiques -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Graphique 3: Raisons d'annulation -->
                <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <span class="material-icons mr-2 text-purple-600">psychology</span>
                        Répartition par raisons d'annulation
                    </h3>
                    <div class="chart-container">
                        <canvas id="reasonsChart"></canvas>
                    </div>
                </div>

                <!-- Graphique 4: Projets avec le plus d'annulations -->
                <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <span class="material-icons mr-2 text-yellow-600">folder_special</span>
                        Projets avec le plus d'annulations
                    </h3>
                    <div class="chart-container">
                        <canvas id="projectsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tableaux détaillés -->
            <div class="grid grid-cols-1 gap-6 mb-6">
                <!-- Tableau des projets avec annulations -->
                <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold flex items-center">
                            <span class="material-icons mr-2 text-blue-600">folder_open</span>
                            Projets avec commandes annulées
                        </h3>
                        <span class="text-sm text-gray-500">Top 10</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code Projet</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commandes annulées</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valeur totale</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dernière annulation</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (empty($topProjects)): ?>
                                    <tr>
                                        <td colspan="6" class="py-4 px-4 text-center text-gray-500">Aucun projet avec commandes annulées trouvé</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topProjects as $project): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 text-sm font-medium text-gray-900">
                                                <a href="projet_details.php?code_projet=<?php echo urlencode($project['code_projet']); ?>" class="text-blue-600 hover:text-blue-800">
                                                    <?php echo htmlspecialchars($project['code_projet']); ?>
                                                </a>
                                            </td>
                                            <td class="py-3 px-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($project['nom_client']); ?>
                                            </td>
                                            <td class="py-3 px-4 text-sm">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                                    <?php echo formatNumber($project['canceled_count']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-sm text-gray-900 font-medium">
                                                <?php echo formatNumber($project['total_value']); ?> FCFA
                                            </td>
                                            <td class="py-3 px-4 text-sm text-gray-500">
                                                <?php echo date('d/m/Y', strtotime($project['last_canceled'])); ?>
                                            </td>
                                            <td class="py-3 px-4 text-sm">
                                                <button onclick="viewProjectDetails('<?php echo $project['code_projet']; ?>')" class="text-blue-600 hover:text-blue-800">
                                                    <span class="material-icons text-sm">visibility</span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tableau des dernières commandes annulées -->
                <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold flex items-center">
                            <span class="material-icons mr-2 text-red-600">list_alt</span>
                            Dernières commandes annulées
                        </h3>
                        <span class="text-sm text-gray-500">20 dernières</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Projet</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix unitaire</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fournisseur</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date d'annulation</th>
                                    <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (empty($recentOrders)): ?>
                                    <tr>
                                        <td colspan="8" class="py-4 px-4 text-center text-gray-500">Aucune commande annulée trouvée</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 text-sm font-medium">
                                                <a href="projet_details.php?code_projet=<?php echo urlencode($order['code_projet']); ?>" class="text-blue-600 hover:text-blue-800">
                                                    <?php echo htmlspecialchars($order['code_projet']); ?>
                                                </a>
                                            </td>
                                            <td class="py-3 px-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($order['nom_client']); ?>
                                            </td>
                                            <td class="py-3 px-4 text-sm text-gray-900">
                                                <?php echo htmlspecialchars($order['designation']); ?>
                                            </td>
                                            <td class="py-3 px-4 text-sm text-gray-500">
                                                <?php echo formatNumber($order['qt_acheter']); ?> <?php echo htmlspecialchars($order['unit'] ?? ''); ?>
                                            </td>
                                            <td class="py-3 px-4 text-sm text-gray-900">
                                                <?php echo $order['prix_unitaire'] ? formatNumber($order['prix_unitaire']) . ' FCFA' : 'N/A'; ?>
                                            </td>
                                            <td class="py-3 px-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($order['fournisseur'] ?? 'Non défini'); ?>
                                            </td>
                                            <td class="py-3 px-4 text-sm text-gray-500">
                                                <?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?>
                                            </td>
                                            <td class="py-3 px-4 text-sm">
                                                <button onclick="viewOrderDetails('<?php echo $order['id']; ?>', '<?php echo $order['idExpression']; ?>')" class="text-blue-600 hover:text-blue-800 mr-2">
                                                    <span class="material-icons text-sm">visibility</span>
                                                </button>
                                                <button onclick="restoreOrder('<?php echo $order['id']; ?>')" class="text-green-600 hover:text-green-800">
                                                    <span class="material-icons text-sm">restore</span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Navigation rapide -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">dashboard</span>
                    <h3 class="text-lg font-semibold">Tableau de Bord</h3>
                    <p class="text-sm opacity-80 mt-1">Vue d'ensemble</p>
                </a>

                <a href="stats_achats.php" class="bg-blue-600 hover:bg-blue-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">shopping_cart</span>
                    <h3 class="text-lg font-semibold">Statistiques des Achats</h3>
                    <p class="text-sm opacity-80 mt-1">Analyse des commandes et achats</p>
                </a>

                <a href="stats_fournisseurs.php" class="bg-purple-600 hover:bg-purple-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">business</span>
                    <h3 class="text-lg font-semibold">Statistiques des Fournisseurs</h3>
                    <p class="text-sm opacity-80 mt-1">Performance et historique</p>
                </a>

                <a href="stats_produits.php" class="bg-green-600 hover:bg-green-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">inventory</span>
                    <h3 class="text-lg font-semibold">Statistiques des Produits</h3>
                    <p class="text-sm opacity-80 mt-1">Analyse du stock et mouvements</p>
                </a>

                <a href="stats_projets.php" class="bg-yellow-600 hover:bg-yellow-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">folder_special</span>
                    <h3 class="text-lg font-semibold">Statistiques des Projets</h3>
                    <p class="text-sm opacity-80 mt-1">Suivi et analyse des projets</p>
                </a>
            </div>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <script src="assets/js/chart_functions.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            // Export PDF
            document.getElementById('export-pdf').addEventListener('click', function() {
                Swal.fire({
                    title: 'Génération du rapport',
                    text: 'Le rapport PDF des commandes annulées est en cours de génération...',
                    icon: 'info',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Construire l'URL avec les paramètres actuels
                const urlParams = new URLSearchParams(window.location.search);
                let pdfUrl = 'generate_report.php?type=canceled'; // CHANGÉ: canceled au lieu de canceled_orders

                // Ajouter les paramètres de filtrage
                if (urlParams.get('period') && urlParams.get('period') !== 'all') {
                    pdfUrl += '&period=' + encodeURIComponent(urlParams.get('period'));
                }

                if (urlParams.get('project') && urlParams.get('project') !== 'all') {
                    pdfUrl += '&project=' + encodeURIComponent(urlParams.get('project'));
                }

                if (urlParams.get('reason') && urlParams.get('reason') !== 'all') {
                    pdfUrl += '&reason=' + encodeURIComponent(urlParams.get('reason'));
                }

                // Ajouter un timestamp pour éviter le cache
                pdfUrl += '&timestamp=' + Date.now();

                setTimeout(() => {
                    // Ouvrir le PDF dans un nouvel onglet
                    window.open(pdfUrl, '_blank');

                    Swal.fire({
                        title: 'Rapport généré!',
                        text: 'Le rapport PDF a été généré avec succès.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }, 1500);
            });

        });

        // Configuration des graphiques
        function setupCharts() {
            // Configuration des couleurs et options communes
            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.color = '#64748b';

            // Graphique 1: Tendance mensuelle
            const monthlyData = <?php echo json_encode($monthlyTrend); ?>;
            if (monthlyData && monthlyData.length > 0) {
                renderCanceledOrdersChart('monthlyTrendChart', monthlyData);
            }

            // Graphique 2: Top produits annulés
            const topProductsData = <?php echo json_encode($topProducts); ?>;
            if (topProductsData && topProductsData.length > 0) {
                renderCanceledProductsChart('topProductsChart', topProductsData);
            }

            // Graphique 3: Raisons d'annulation
            const reasonsData = <?php echo json_encode($reasonsData); ?>;
            if (reasonsData && reasonsData.length > 0) {
                renderCancellationReasonsChart('reasonsChart', reasonsData);
            }

            // Graphique 4: Projets avec annulations
            const projectsData = <?php echo json_encode($topProjects); ?>;
            if (projectsData && projectsData.length > 0) {
                renderProjectsCancellationsChart('projectsChart', projectsData);
            }
        }

        /**
         * Graphique des commandes annulées par mois
         */
        /*function renderCanceledOrdersChart(canvasId, data) {
            const ctx = document.getElementById(canvasId).getContext('2d');

            const labels = data.map(item => item.month_name || item.month);
            const counts = data.map(item => parseInt(item.count) || 0);
            const amounts = data.map(item => parseFloat(item.amount) || 0);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Nombre d\'annulations',
                            data: counts,
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Montant (FCFA)',
                            data: amounts,
                            borderColor: 'rgb(147, 51, 234)',
                            backgroundColor: 'rgba(147, 51, 234, 0.1)',
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
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.datasetIndex === 0) {
                                        return `Annulations: ${context.raw}`;
                                    } else {
                                        return `Montant: ${formatNumber(context.raw)} FCFA`;
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 45
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Nombre d\'annulations'
                            },
                            beginAtZero: true
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Montant (FCFA)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        */
        /**
         * Graphique des produits les plus annulés
         */
        /*function renderCanceledProductsChart(canvasId, data) {
            const ctx = document.getElementById(canvasId).getContext('2d');

            // Prendre les 8 premiers pour une meilleure lisibilité
            const topData = data.slice(0, 8);
            const labels = topData.map(item => {
                // Tronquer les noms longs
                const name = item.designation || 'Produit inconnu';
                return name.length > 25 ? name.substring(0, 25) + '...' : name;
            });
            const values = topData.map(item => parseInt(item.count) || 0);

            // Générer des couleurs différentes pour chaque barre
            const colors = [
                'rgba(239, 68, 68, 0.8)', // Rouge
                'rgba(245, 158, 11, 0.8)', // Orange
                'rgba(234, 179, 8, 0.8)', // Jaune
                'rgba(34, 197, 94, 0.8)', // Vert
                'rgba(59, 130, 246, 0.8)', // Bleu
                'rgba(147, 51, 234, 0.8)', // Violet
                'rgba(236, 72, 153, 0.8)', // Rose
                'rgba(156, 163, 175, 0.8)' // Gris
            ];

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Annulations',
                        data: values,
                        backgroundColor: colors.slice(0, topData.length),
                        borderColor: colors.slice(0, topData.length).map(color => color.replace('0.8', '1')),
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.8
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
                            callbacks: {
                                title: function(context) {
                                    // Afficher le nom complet dans le tooltip
                                    return topData[context[0].dataIndex].designation;
                                },
                                label: function(context) {
                                    const item = topData[context.dataIndex];
                                    return [
                                        `Annulations: ${context.raw}`,
                                        `Valeur perdue: ${formatNumber(item.total_value || 0)} FCFA`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Nombre d\'annulations'
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45
                            }
                        }
                    }
                }
            });
        }

        */

        /**
         * Graphique des raisons d'annulation
         */
        function renderCancellationReasonsChart(canvasId, data) {
            const ctx = document.getElementById(canvasId).getContext('2d');

            const labels = data.map(item => item.reason);
            const values = data.map(item => parseInt(item.count) || 0);

            const colors = [
                '#ef4444', '#f97316', '#eab308', '#22c55e',
                '#3b82f6', '#8b5cf6', '#ec4899', '#6b7280'
            ];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors.slice(0, data.length),
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const value = data.datasets[0].data[i];
                                            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            const percentage = ((value / total) * 100).toFixed(1);
                                            return {
                                                text: `${label} (${percentage}%)`,
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                strokeStyle: data.datasets[0].backgroundColor[i],
                                                pointStyle: 'circle'
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return `${context.label}: ${context.raw} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // ==========================================
        // FONCTION UTILITAIRE POUR FORMATER LES NOMBRES
        // ==========================================
        function formatNumber(number, decimals = 0) {
            if (number === null || number === undefined || isNaN(number)) return '0';
            return new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(number);
        }

        // Fonction pour voir les détails d'un projet
        function viewProjectDetails(codeProjet) {
            window.location.href = `projet_details.php?code_projet=${encodeURIComponent(codeProjet)}`;
        }

        // Fonction pour voir les détails d'une commande
        function viewOrderDetails(id, idExpression) {
            Swal.fire({
                title: 'Détails de la commande annulée',
                html: `
                    <div class="text-left">
                        <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                            <p class="text-sm text-red-700">Cette commande a été annulée et n'est plus valide.</p>
                        </div>
                        <p class="text-sm text-gray-600">ID: ${id}</p>
                        <p class="text-sm text-gray-600">Expression: ${idExpression}</p>
                        <p class="text-sm text-gray-600 mt-2">
                            <a href="projet_details.php?code_projet=${idExpression}" class="text-blue-600 hover:text-blue-800">
                                Voir les détails du projet →
                            </a>
                        </p>
                    </div>
                `,
                width: 500,
                confirmButtonText: 'Fermer',
                showClass: {
                    popup: 'animate__animated animate__fadeIn'
                }
            });
        }

        // Fonction pour restaurer une commande (si nécessaire)
        function restoreOrder(id) {
            Swal.fire({
                title: 'Restaurer la commande?',
                text: 'Voulez-vous vraiment restaurer cette commande annulée?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Oui, restaurer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#ef4444'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Ici vous pouvez ajouter la logique pour restaurer la commande
                    Swal.fire({
                        title: 'Fonction en développement',
                        text: 'La fonction de restauration sera bientôt disponible.',
                        icon: 'info'
                    });
                }
            });
        }

        // Fonction pour rendre le graphique des projets avec annulations
        function renderProjectsCancellationsChart(canvasId, data) {
            const ctx = document.getElementById(canvasId).getContext('2d');

            const labels = data.map(item => item.code_projet);
            const values = data.map(item => parseInt(item.canceled_count));

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Commandes annulées',
                        data: values,
                        backgroundColor: 'rgba(239, 68, 68, 0.7)',
                        borderColor: 'rgb(239, 68, 68)',
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.7
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
                            callbacks: {
                                label: function(context) {
                                    return `Annulations: ${context.raw}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>