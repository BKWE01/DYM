<?php
/**
 * Module de statistiques détaillées des projets avec regroupement par code projet identique
 * Affiche l'analyse des projets regroupés par code projet complet
 * Version modifiée : Regroupement des projets par code projet identique
 * 
 * @package DYM_MANUFACTURE
 * @subpackage expressions_besoins/User-Achat/statistics
 * @version 2.2.0
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

// Récupérer les filtres sélectionnés
$selectedClient = isset($_GET['client']) ? $_GET['client'] : 'all';
$selectedCodeProjet = isset($_GET['code_projet']) ? $_GET['code_projet'] : null;
$selectedPeriod = isset($_GET['period']) ? $_GET['period'] : 'all';
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$selectedSort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Fonction pour formater les nombres
function formatNumber($number, $decimals = 0)
{
    if ($number === null || $number === '') return '0';
    return number_format((float)$number, $decimals, ',', ' ');
}

// Fonction pour déterminer la couleur selon le pourcentage
function getProgressColor($percentage) {
    if ($percentage >= 75) return 'bg-green-500';
    if ($percentage >= 50) return 'bg-blue-500';
    if ($percentage >= 25) return 'bg-yellow-500';
    return 'bg-red-500';
}

// Fonction pour calculer le pourcentage avec gestion des erreurs
function calculatePercentage($value, $total) {
    if ($total == 0) return 0;
    return round(($value / $total) * 100);
}

// Construction des conditions de filtre pour les requêtes SQL
$dateCondition = "";
$clientCondition = ($selectedClient != 'all') ? "AND grouped_projects.client_principal = :nom_client" : "";
$projetCondition = (!empty($selectedCodeProjet)) ? "AND grouped_projects.code_projet = :code_projet" : "";
$statusCondition = "";

// Condition de date en fonction de la période sélectionnée
switch ($selectedPeriod) {
    case 'today':
        $dateCondition = "AND DATE(grouped_projects.date_creation_min) = CURDATE()";
        break;
    case 'week':
        $dateCondition = "AND YEARWEEK(grouped_projects.date_creation_min, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
        $dateCondition = "AND MONTH(grouped_projects.date_creation_min) = MONTH(CURDATE()) AND YEAR(grouped_projects.date_creation_min) = YEAR(CURDATE())";
        break;
    case 'quarter':
        $dateCondition = "AND QUARTER(grouped_projects.date_creation_min) = QUARTER(CURDATE()) AND YEAR(grouped_projects.date_creation_min) = YEAR(CURDATE())";
        break;
    case 'year':
        $dateCondition = "AND YEAR(grouped_projects.date_creation_min) = :year";
        break;
    default:
        $dateCondition = "AND grouped_projects.date_creation_min >= '" . getSystemStartDate() . "'";
        break;
}

// Condition de statut (à adapter selon le regroupement)
if ($selectedStatus !== 'all') {
    switch ($selectedStatus) {
        case 'completed':
            $statusCondition = "AND grouped_projects.pending_items = 0";
            break;
        case 'pending':
            $statusCondition = "AND grouped_projects.pending_items > 0";
            break;
        case 'active':
            $statusCondition = "AND grouped_projects.is_completed = 0";
            break;
        case 'finished':
            $statusCondition = "AND grouped_projects.is_completed = 1";
            break;
    }
}

// Clause de tri
$orderClause = "ORDER BY grouped_projects.date_creation_min DESC";
if ($selectedSort === 'name') {
    $orderClause = "ORDER BY grouped_projects.client_principal ASC, grouped_projects.code_projet ASC";
} elseif ($selectedSort === 'value_high') {
    $orderClause = "ORDER BY grouped_projects.total_amount DESC";
} elseif ($selectedSort === 'value_low') {
    $orderClause = "ORDER BY grouped_projects.total_amount ASC";
} elseif ($selectedSort === 'progress_high') {
    $orderClause = "ORDER BY grouped_projects.completed_percentage DESC";
} elseif ($selectedSort === 'progress_low') {
    $orderClause = "ORDER BY grouped_projects.completed_percentage ASC";
}

try {
    // Créer une vue temporaire des projets regroupés par code projet identique
    $groupedProjectsSubquery = "
        SELECT 
            ip.code_projet,
            -- Prendre le premier client par ordre alphabétique comme référence
            MIN(ip.nom_client) as client_principal,
            -- Concatener tous les clients uniques
            GROUP_CONCAT(DISTINCT ip.nom_client ORDER BY ip.nom_client SEPARATOR ', ') as tous_clients,
            -- Prendre la première description
            MIN(ip.description_projet) as description_projet,
            -- Prendre le premier chef de projet
            MIN(ip.chefprojet) as chefprojet,
            -- Prendre la première localisation
            MIN(ip.sitgeo) as sitgeo,
            -- Dates
            MIN(ip.created_at) as date_creation_min,
            MAX(ip.created_at) as date_creation_max,
            -- Compter les occurrences du même code projet
            COUNT(DISTINCT ip.id) as nb_occurrences,
            -- Lister les IDs des expressions
            GROUP_CONCAT(DISTINCT ip.idExpression SEPARATOR ',') as all_expressions,
            -- Calculer les totaux des matériaux
            COUNT(ed.id) as total_items,
            COALESCE(SUM(CASE WHEN ed.qt_acheter IS NOT NULL AND ed.prix_unitaire IS NOT NULL 
                             THEN CAST(ed.qt_acheter AS DECIMAL(10,2)) * CAST(ed.prix_unitaire AS DECIMAL(10,2)) 
                             ELSE 0 END), 0) as total_amount,
            -- Calculer les statuts
            SUM(CASE WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'reçu' OR ed.valide_achat = 'en_cours' THEN 1 ELSE 0 END) as completed_items,
            SUM(CASE WHEN ed.valide_achat = 'reçu' THEN 1 ELSE 0 END) as received_items,
            SUM(CASE WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'en_cours' THEN 1 ELSE 0 END) as ordered_items,
            SUM(CASE WHEN ed.valide_achat = 'annulé' THEN 1 ELSE 0 END) as canceled_items,
            SUM(CASE WHEN ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL THEN 1 ELSE 0 END) as pending_items,
            -- Vérifier si complètement reçu
            (COUNT(ed.id) > 0 AND COUNT(ed.id) = SUM(CASE WHEN ed.valide_achat = 'reçu' THEN 1 ELSE 0 END)) as is_fully_received,
            -- Vérifier si complètement terminé (via project_status)
            MAX(CASE WHEN ps.status = 'completed' THEN 1 ELSE 0 END) as is_completed,
            -- Calculer le pourcentage de progression
            CASE 
                WHEN COUNT(ed.id) > 0 THEN 
                    ROUND((SUM(CASE WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'reçu' OR ed.valide_achat = 'en_cours' THEN 1 ELSE 0 END) / COUNT(ed.id)) * 100, 0)
                ELSE 0 
            END as completed_percentage
        FROM identification_projet ip
        LEFT JOIN expression_dym ed ON ip.idExpression = ed.idExpression
        LEFT JOIN project_status ps ON ip.idExpression = ps.idExpression
        WHERE ip.created_at >= '" . getSystemStartDate() . "'
        GROUP BY ip.code_projet
    ";

    // Récupérer la liste des clients uniques (basée sur les projets regroupés)
    $clientsQuery = "
        SELECT DISTINCT client_principal as nom_client
        FROM ($groupedProjectsSubquery) as grouped_projects
        ORDER BY client_principal
    ";
    $clientsStmt = $pdo->prepare($clientsQuery);
    $clientsStmt->execute();
    $clients = $clientsStmt->fetchAll(PDO::FETCH_COLUMN);

    // Si un client est sélectionné, récupérer ses codes projets
    if ($selectedClient != 'all') {
        $projetsQuery = "
            SELECT code_projet, description_projet, tous_clients, nb_occurrences
            FROM ($groupedProjectsSubquery) as grouped_projects
            WHERE client_principal = :nom_client
            ORDER BY date_creation_min DESC
        ";
        $projetsStmt = $pdo->prepare($projetsQuery);
        $projetsStmt->bindParam(':nom_client', $selectedClient);
        $projetsStmt->execute();
        $projets = $projetsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Liste des années disponibles
    $yearQuery = "SELECT DISTINCT YEAR(created_at) as year FROM identification_projet ORDER BY year DESC";
    $yearStmt = $pdo->prepare($yearQuery);
    $yearStmt->execute();
    $years = $yearStmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($years)) {
        $years = [date('Y')];
    }

    // Statistiques générales des projets avec regroupement par code projet
    $statsQuery = "
        SELECT 
            COUNT(*) as total_projects_grouped,
            SUM(nb_occurrences) as total_projects_individual,
            COUNT(DISTINCT client_principal) as total_clients,
            SUM(total_items) as total_items,
            SUM(total_amount) as total_amount,
            ROUND(AVG(DATEDIFF(CURDATE(), date_creation_min)), 0) as avg_duration,
            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_projects,
            SUM(CASE WHEN is_completed = 0 THEN 1 ELSE 0 END) as active_projects
        FROM ($groupedProjectsSubquery) as grouped_projects
        WHERE 1=1 
        $dateCondition
        $clientCondition 
        $projetCondition
        $statusCondition
    ";

    $statsStmt = $pdo->prepare($statsQuery);
    if ($selectedClient != 'all') {
        $statsStmt->bindParam(':nom_client', $selectedClient);
    }
    if (!empty($selectedCodeProjet)) {
        $statsStmt->bindParam(':code_projet', $selectedCodeProjet);
    }
    if ($selectedPeriod === 'year') {
        $statsStmt->bindParam(':year', $selectedYear);
    }
    $statsStmt->execute();
    $statsProjects = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Récupérer le statut des achats des projets avec regroupement
    $statusQuery = "
        SELECT 
            'En attente' as status,
            SUM(pending_items) as count,
            SUM(pending_items * total_amount / NULLIF(total_items, 0)) as amount
        FROM ($groupedProjectsSubquery) as grouped_projects
        WHERE 1=1 $clientCondition $projetCondition $statusCondition
        
        UNION ALL
        
        SELECT 
            'Commandé' as status,
            SUM(ordered_items) as count,
            SUM(ordered_items * total_amount / NULLIF(total_items, 0)) as amount
        FROM ($groupedProjectsSubquery) as grouped_projects
        WHERE 1=1 $clientCondition $projetCondition $statusCondition
        
        UNION ALL
        
        SELECT 
            'Reçu' as status,
            SUM(received_items) as count,
            SUM(received_items * total_amount / NULLIF(total_items, 0)) as amount
        FROM ($groupedProjectsSubquery) as grouped_projects
        WHERE 1=1 $clientCondition $projetCondition $statusCondition
        
        UNION ALL
        
        SELECT 
            'Annulé' as status,
            SUM(canceled_items) as count,
            SUM(canceled_items * total_amount / NULLIF(total_items, 0)) as amount
        FROM ($groupedProjectsSubquery) as grouped_projects
        WHERE 1=1 $clientCondition $projetCondition $statusCondition
    ";

    $statusStmt = $pdo->prepare($statusQuery);
    if ($selectedClient != 'all') {
        $statusStmt->bindParam(':nom_client', $selectedClient);
        $statusStmt->bindParam(':nom_client', $selectedClient);
        $statusStmt->bindParam(':nom_client', $selectedClient);
        $statusStmt->bindParam(':nom_client', $selectedClient);
    }
    if (!empty($selectedCodeProjet)) {
        $statusStmt->bindParam(':code_projet', $selectedCodeProjet);
        $statusStmt->bindParam(':code_projet', $selectedCodeProjet);
        $statusStmt->bindParam(':code_projet', $selectedCodeProjet);
        $statusStmt->bindParam(':code_projet', $selectedCodeProjet);
    }
    $statusStmt->execute();
    $statusStats = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les données de statut pour le graphique
    $statusChartData = [
        'labels' => [],
        'data' => [],
        'backgroundColor' => [
            'rgba(239, 68, 68, 0.7)',   // Rouge pour En attente
            'rgba(59, 130, 246, 0.7)',  // Bleu pour Commandé
            'rgba(16, 185, 129, 0.7)',  // Vert pour Reçu
            'rgba(156, 163, 175, 0.7)'  // Gris pour Annulé
        ]
    ];

    foreach ($statusStats as $stat) {
        if ($stat['count'] > 0) {
            $statusChartData['labels'][] = $stat['status'];
            $statusChartData['data'][] = (float) ($stat['amount'] ?: 0);
        }
    }

    // Récupérer les projets récents avec regroupement
    $recentProjectsQuery = "
        SELECT *
        FROM ($groupedProjectsSubquery) as grouped_projects
        WHERE 1=1
        $dateCondition
        $clientCondition
        $statusCondition
        $orderClause
        LIMIT 15
    ";

    $recentProjectsStmt = $pdo->prepare($recentProjectsQuery);
    if ($selectedClient != 'all') {
        $recentProjectsStmt->bindParam(':nom_client', $selectedClient);
    }
    if ($selectedPeriod === 'year') {
        $recentProjectsStmt->bindParam(':year', $selectedYear);
    }
    $recentProjectsStmt->execute();
    $recentProjects = $recentProjectsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Si un projet spécifique est sélectionné, récupérer les détails des matériaux
    $projectInfo = null;
    $projectMaterials = [];
    if (!empty($selectedCodeProjet)) {
        // Récupérer les informations du projet regroupé
        $projectInfoQuery = "
            SELECT *
            FROM ($groupedProjectsSubquery) as grouped_projects
            WHERE code_projet = :code_projet
            LIMIT 1
        ";

        $projectInfoStmt = $pdo->prepare($projectInfoQuery);
        $projectInfoStmt->bindParam(':code_projet', $selectedCodeProjet);
        $projectInfoStmt->execute();
        $projectInfo = $projectInfoStmt->fetch(PDO::FETCH_ASSOC);

        if ($projectInfo) {
            // Récupérer tous les matériaux de toutes les expressions liées à ce code projet
            $materialsQuery = "
                SELECT 
                    ed.id,
                    ed.idExpression,
                    ed.designation,
                    ed.quantity,
                    ed.unit,
                    ed.qt_stock,
                    ed.qt_acheter,
                    ed.prix_unitaire,
                    ed.fournisseur,
                    ed.valide_achat as status,
                    ed.created_at,
                    ed.updated_at,
                    ip.nom_client,
                    (SELECT COUNT(*) FROM achats_materiaux am WHERE am.expression_id = ip.idExpression AND am.designation = ed.designation) as order_count,
                    (SELECT MAX(am.date_reception) FROM achats_materiaux am WHERE am.expression_id = ip.idExpression AND am.designation = ed.designation) as last_receipt_date
                FROM identification_projet ip
                JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                WHERE ip.code_projet = :code_projet
                AND ip.created_at >= '" . getSystemStartDate() . "'
                ORDER BY ed.valide_achat ASC, ed.created_at DESC
            ";

            $materialsStmt = $pdo->prepare($materialsQuery);
            $materialsStmt->bindParam(':code_projet', $selectedCodeProjet);
            $materialsStmt->execute();
            $projectMaterials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculer des statistiques supplémentaires pour le projet regroupé
            $projectInfo['total_materials'] = $projectInfo['total_items'];
            $projectInfo['received_count'] = $projectInfo['received_items'];
            $projectInfo['ordered_count'] = $projectInfo['ordered_items'];
            $projectInfo['canceled_count'] = $projectInfo['canceled_items'];
            $projectInfo['pending_count'] = $projectInfo['pending_items'];
            $projectInfo['total_value'] = $projectInfo['total_amount'];
            $projectInfo['progress_percentage'] = $projectInfo['completed_percentage'];
        }
    }

    // Récupérer les statistiques par catégorie pour le client/projet sélectionné
    $categoriesQuery = "
        SELECT 
            COALESCE(c.libelle, 'Non catégorisé') as category, 
            COUNT(ed.id) as item_count,
            COALESCE(SUM(CASE WHEN ed.qt_acheter IS NOT NULL AND ed.prix_unitaire IS NOT NULL 
                             THEN CAST(ed.qt_acheter AS DECIMAL(10,2)) * CAST(ed.prix_unitaire AS DECIMAL(10,2)) 
                             ELSE 0 END), 0) as amount
        FROM expression_dym ed
        JOIN identification_projet ip ON ed.idExpression = ip.idExpression
        LEFT JOIN products p ON ed.designation = p.product_name
        LEFT JOIN categories c ON p.category = c.id
        WHERE 1=1
        " . str_replace('grouped_projects.', 'ip.', $dateCondition) . "
        " . str_replace('grouped_projects.client_principal', 'ip.nom_client', $clientCondition) . "
        " . str_replace('grouped_projects.code_projet', 'ip.code_projet', $projetCondition) . "
        AND ed.qt_acheter > 0
        GROUP BY c.libelle
        ORDER BY amount DESC
    ";

    $categoriesStmt = $pdo->prepare($categoriesQuery);
    if ($selectedClient != 'all') {
        $categoriesStmt->bindParam(':nom_client', $selectedClient);
    }
    if (!empty($selectedCodeProjet)) {
        $categoriesStmt->bindParam(':code_projet', $selectedCodeProjet);
    }
    if ($selectedPeriod === 'year') {
        $categoriesStmt->bindParam(':year', $selectedYear);
    }
    $categoriesStmt->execute();
    $categoriesStats = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les données pour le graphique de catégories
    $categoriesChartData = [
        'labels' => [],
        'data' => [],
        'backgroundColor' => []
    ];

    // Couleurs pour les différentes catégories
    $colors = [
        '#4299E1', '#48BB78', '#ECC94B', '#9F7AEA', '#ED64A6', 
        '#F56565', '#667EEA', '#ED8936', '#38B2AC', '#CBD5E0'
    ];

    foreach ($categoriesStats as $index => $cat) {
        $categoryName = $cat['category'] ?: 'Non catégorisé';
        $categoriesChartData['labels'][] = $categoryName;
        $categoriesChartData['data'][] = (float) $cat['amount'];
        $categoriesChartData['backgroundColor'][] = $colors[$index % count($colors)];
    }
    
    // Récupérer des données pour la distribution des délais moyens par projet regroupé
    $delaiMoyenQuery = "
        SELECT 
            grouped_projects.code_projet,
            grouped_projects.client_principal,
            AVG(DATEDIFF(am.date_reception, am.date_achat)) as avg_delay
        FROM ($groupedProjectsSubquery) as grouped_projects
        JOIN identification_projet ip ON ip.code_projet = grouped_projects.code_projet
        JOIN achats_materiaux am ON am.expression_id = ip.idExpression
        WHERE am.date_reception IS NOT NULL 
        AND am.date_achat IS NOT NULL
        AND DATEDIFF(am.date_reception, am.date_achat) >= 0
        AND DATEDIFF(am.date_reception, am.date_achat) <= 90
        " . str_replace('grouped_projects.client_principal', 'grouped_projects.client_principal', $clientCondition) . "
        GROUP BY grouped_projects.code_projet, grouped_projects.client_principal
        HAVING avg_delay IS NOT NULL
        ORDER BY avg_delay ASC
        LIMIT 10
    ";
    
    $delaiStmt = $pdo->prepare($delaiMoyenQuery);
    if ($selectedClient != 'all') {
        $delaiStmt->bindParam(':nom_client', $selectedClient);
    }
    $delaiStmt->execute();
    $delaiProjects = $delaiStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données pour le graphique de délai
    $delaiChartData = [
        'labels' => array_map(function($item) { 
            return $item['code_projet'] . ' (' . substr($item['client_principal'], 0, 15) . ')'; 
        }, $delaiProjects),
        'data' => array_map(function($item) { 
            return round(floatval($item['avg_delay']), 1); 
        }, $delaiProjects)
    ];
    
    // Récupérer la distribution mensuelle des projets pour l'année sélectionnée avec regroupement
    $monthlyProjectsQuery = "
        SELECT 
            MONTH(date_creation_min) as month,
            COUNT(*) as count
        FROM ($groupedProjectsSubquery) as grouped_projects
        WHERE YEAR(date_creation_min) = :year
        " . str_replace('grouped_projects.client_principal', 'grouped_projects.client_principal', $clientCondition) . "
        GROUP BY MONTH(date_creation_min)
        ORDER BY month
    ";
                           
    $monthlyStmt = $pdo->prepare($monthlyProjectsQuery);
    $monthlyStmt->bindParam(':year', $selectedYear);
    if ($selectedClient != 'all') {
        $monthlyStmt->bindParam(':nom_client', $selectedClient);
    }
    $monthlyStmt->execute();
    $monthlyProjects = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données pour le graphique mensuel
    $monthNames = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
    ];
    
    $monthlyChartData = [
        'labels' => [],
        'data' => array_fill(0, 12, 0)
    ];
    
    foreach ($monthlyProjects as $monthData) {
        $monthIndex = (int)$monthData['month'] - 1;
        $monthlyChartData['data'][$monthIndex] = (int)$monthData['count'];
    }
    
    foreach ($monthNames as $index => $name) {
        $monthlyChartData['labels'][] = $name;
    }

} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des Projets Regroupés | Service Achat</title>

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="asset/css/stats_projet.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Animation CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</head>

<body class="bg-gray-100 font-inter text-gray-800">
    <div class="wrapper">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- Dashboard Header -->
            <div class="dashboard-header mb-6">
                <div class="flex items-center">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-2">
                        <span class="material-icons">arrow_back</span>
                    </a>
                    <h1 class="text-xl font-bold text-gray-800 flex items-center">
                        <span class="material-icons mr-3 text-blue-600">folder_special</span>
                        Statistiques des Projets Regroupés
                        <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Par Code Projet Identique</span>
                    </h1>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button id="export-pdf" class="action-btn action-btn-secondary">
                        <span class="material-icons">picture_as_pdf</span>
                        Exporter PDF
                    </button>

                    <div class="date-time">
                        <span class="material-icons">event</span>
                        <span id="date-time-display"></span>
                    </div>
                </div>
            </div>

            <!-- Information sur le regroupement -->
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <span class="material-icons text-blue-400">info</span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Regroupement intelligent :</strong> Tous les projets ayant exactement le même code projet 
                            (ex: "2408141") sont maintenant regroupés en une seule ligne avec agrégation des données.
                            <br><strong>Exemple :</strong> Les projets avec le code "2408141" du client "PETROCI" sont fusionnés.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Filtres avancés -->
            <div class="bg-white shadow-sm rounded-lg p-4 mb-6">
                <div class="flex flex-wrap justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-700 mb-2">Filtres avancés</h2>
                    <a href="?reset=1" class="text-blue-600 hover:text-blue-800 text-sm">
                        <span class="material-icons align-text-bottom text-sm">refresh</span>
                        Réinitialiser les filtres
                    </a>
                </div>
                
                <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 mt-2">
                    <!-- Filtre par client -->
                    <div>
                        <label for="client-select" class="block text-sm font-medium text-gray-700 mb-1">Client:</label>
                        <select id="client-select" name="client" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="all" <?php echo $selectedClient == 'all' ? 'selected' : ''; ?>>Tous les clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo htmlspecialchars($client); ?>" <?php echo $selectedClient == $client ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtre par projet (conditionnel) -->
                    <?php if ($selectedClient != 'all' && !empty($projets)): ?>
                        <div>
                            <label for="projet-select" class="block text-sm font-medium text-gray-700 mb-1">Code Projet :</label>
                            <select id="projet-select" name="code_projet" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Tous les projets</option>
                                <?php foreach ($projets as $projet): ?>
                                    <option value="<?php echo htmlspecialchars($projet['code_projet']); ?>" <?php echo $selectedCodeProjet == $projet['code_projet'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($projet['code_projet']); ?> 
                                        <?php if ($projet['nb_occurrences'] > 1): ?>
                                            <span class="text-xs">(<?php echo $projet['nb_occurrences']; ?> regroupés)</span>
                                        <?php endif; ?>
                                        <br><small><?php echo htmlspecialchars($projet['tous_clients']); ?></small>
                                        <br><small><?php echo htmlspecialchars(substr($projet['description_projet'], 0, 30)) . (strlen($projet['description_projet']) > 30 ? '...' : ''); ?></small>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Filtre par période -->
                    <div>
                        <label for="period-select" class="block text-sm font-medium text-gray-700 mb-1">Période:</label>
                        <select id="period-select" name="period" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="all" <?php echo $selectedPeriod == 'all' ? 'selected' : ''; ?>>Toutes les périodes</option>
                            <option value="today" <?php echo $selectedPeriod == 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                            <option value="week" <?php echo $selectedPeriod == 'week' ? 'selected' : ''; ?>>Cette semaine</option>
                            <option value="month" <?php echo $selectedPeriod == 'month' ? 'selected' : ''; ?>>Ce mois</option>
                            <option value="quarter" <?php echo $selectedPeriod == 'quarter' ? 'selected' : ''; ?>>Ce trimestre</option>
                            <option value="year" <?php echo $selectedPeriod == 'year' ? 'selected' : ''; ?>>Année spécifique</option>
                        </select>
                    </div>

                    <!-- Filtre par année (conditionnel) -->
                    <?php if ($selectedPeriod == 'year'): ?>
                        <div>
                            <label for="year-select" class="block text-sm font-medium text-gray-700 mb-1">Année:</label>
                            <select id="year-select" name="year" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $selectedYear == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Filtre par statut -->
                    <div>
                        <label for="status-select" class="block text-sm font-medium text-gray-700 mb-1">Statut:</label>
                        <select id="status-select" name="status" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="all" <?php echo $selectedStatus == 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="active" <?php echo $selectedStatus == 'active' ? 'selected' : ''; ?>>Projets actifs</option>
                            <option value="finished" <?php echo $selectedStatus == 'finished' ? 'selected' : ''; ?>>Projets terminés</option>
                            <option value="completed" <?php echo $selectedStatus == 'completed' ? 'selected' : ''; ?>>Achats complets</option>
                            <option value="pending" <?php echo $selectedStatus == 'pending' ? 'selected' : ''; ?>>Achats en attente</option>
                        </select>
                    </div>
                    
                    <!-- Filtre de tri -->
                    <div>
                        <label for="sort-select" class="block text-sm font-medium text-gray-700 mb-1">Trier par:</label>
                        <select id="sort-select" name="sort" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="latest" <?php echo $selectedSort == 'latest' ? 'selected' : ''; ?>>Plus récents</option>
                            <option value="name" <?php echo $selectedSort == 'name' ? 'selected' : ''; ?>>Nom du client</option>
                            <option value="value_high" <?php echo $selectedSort == 'value_high' ? 'selected' : ''; ?>>Valeur (décroissant)</option>
                            <option value="value_low" <?php echo $selectedSort == 'value_low' ? 'selected' : ''; ?>>Valeur (croissant)</option>
                            <option value="progress_high" <?php echo $selectedSort == 'progress_high' ? 'selected' : ''; ?>>Progression (décroissant)</option>
                            <option value="progress_low" <?php echo $selectedSort == 'progress_low' ? 'selected' : ''; ?>>Progression (croissant)</option>
                        </select>
                    </div>

                    <!-- Bouton de filtrage -->
                    <div class="flex items-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <span class="material-icons mr-1 text-sm">filter_alt</span>
                            Filtrer
                        </button>
                    </div>
                </form>
            </div>

            <?php if (isset($errorMessage)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <p><?php echo $errorMessage; ?></p>
                </div>
            <?php else: ?>
                <?php if (!empty($selectedCodeProjet) && !empty($projectInfo)): ?>
                    <!-- Détails du projet regroupé -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <!-- Carte d'information du projet regroupé -->
                        <div class="bg-white rounded-lg shadow-sm p-6 lg:col-span-2">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg font-semibold flex items-center">
                                    <span class="material-icons mr-2 text-blue-600">folder</span>
                                    Code Projet: <?php echo htmlspecialchars($projectInfo['code_projet']); ?>
                                    <?php if ($projectInfo['nb_occurrences'] > 1): ?>
                                        <span class="ml-2 px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">
                                            <?php echo $projectInfo['nb_occurrences']; ?> projets regroupés
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($projectInfo['is_completed']): ?>
                                        <span class="ml-2 px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Terminé</span>
                                    <?php else: ?>
                                        <span class="ml-2 px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">Actif</span>
                                    <?php endif; ?>
                                </h2>
                                
                                <div class="text-sm text-gray-500">
                                    <span class="material-icons text-sm align-middle">calendar_today</span>
                                    Du <?php echo date('d/m/Y', strtotime($projectInfo['date_creation_min'])); ?>
                                    <?php if ($projectInfo['date_creation_min'] != $projectInfo['date_creation_max']): ?>
                                        au <?php echo date('d/m/Y', strtotime($projectInfo['date_creation_max'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-700">Client principal</h3>
                                    <p class="text-gray-900"><?php echo htmlspecialchars($projectInfo['client_principal']); ?></p>
                                </div>
                                <?php if ($projectInfo['nb_occurrences'] > 1): ?>
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-700">Tous les clients</h3>
                                    <p class="text-gray-900 text-sm"><?php echo htmlspecialchars($projectInfo['tous_clients']); ?></p>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-700">Chef de projet</h3>
                                    <p class="text-gray-900"><?php echo htmlspecialchars($projectInfo['chefprojet']); ?></p>
                                </div>
                                <div class="md:col-span-2">
                                    <h3 class="text-sm font-semibold text-gray-700">Description</h3>
                                    <p class="text-gray-900"><?php echo htmlspecialchars($projectInfo['description_projet']); ?></p>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-700">Localisation</h3>
                                    <p class="text-gray-900"><?php echo htmlspecialchars($projectInfo['sitgeo']); ?></p>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-700">Expressions liées</h3>
                                    <p class="text-gray-900 text-sm font-mono"><?php echo htmlspecialchars($projectInfo['all_expressions']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Carte de statistiques du projet regroupé -->
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <h3 class="text-md font-semibold mb-4 flex items-center">
                                <span class="material-icons mr-2 text-blue-600">insights</span>
                                Vue d'ensemble consolidée
                            </h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-medium">Progression globale</span>
                                        <span class="text-sm font-bold"><?php echo $projectInfo['progress_percentage']; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="<?php echo getProgressColor($projectInfo['progress_percentage']); ?> h-2.5 rounded-full" style="width: <?php echo $projectInfo['progress_percentage']; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-blue-50 rounded-lg p-4">
                                        <div class="text-blue-700 text-lg font-bold"><?php echo formatNumber($projectInfo['total_materials']); ?></div>
                                        <div class="text-blue-600 text-sm">Total matériaux</div>
                                    </div>
                                    <div class="bg-green-50 rounded-lg p-4">
                                        <div class="text-green-700 text-lg font-bold"><?php echo formatNumber($projectInfo['total_value']); ?> FCFA</div>
                                        <div class="text-green-600 text-sm">Valeur totale</div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-purple-50 rounded-lg p-3">
                                        <div class="text-purple-700 text-sm font-bold"><?php echo formatNumber($projectInfo['received_count']); ?></div>
                                        <div class="text-purple-600 text-xs">Reçus</div>
                                    </div>
                                    <div class="bg-indigo-50 rounded-lg p-3">
                                        <div class="text-indigo-700 text-sm font-bold"><?php echo formatNumber($projectInfo['ordered_count']); ?></div>
                                        <div class="text-indigo-600 text-xs">Commandés</div>
                                    </div>
                                    <div class="bg-yellow-50 rounded-lg p-3">
                                        <div class="text-yellow-700 text-sm font-bold"><?php echo formatNumber($projectInfo['pending_count']); ?></div>
                                        <div class="text-yellow-600 text-xs">En attente</div>
                                    </div>
                                    <div class="bg-red-50 rounded-lg p-3">
                                        <div class="text-red-700 text-sm font-bold"><?php echo formatNumber($projectInfo['canceled_count']); ?></div>
                                        <div class="text-red-600 text-xs">Annulés</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des matériaux du projet regroupé -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-blue-600">format_list_bulleted</span>
                            Liste des matériaux consolidée
                            <?php if ($projectInfo['nb_occurrences'] > 1): ?>
                                <span class="ml-2 text-sm text-gray-600">(De toutes les expressions regroupées)</span>
                            <?php endif; ?>
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Expression
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Client
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Désignation
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Qté demandée
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Stock
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Achat
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Prix unitaire
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Fournisseur
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Statut
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Dernière mise à jour
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php
                                    if (empty($projectMaterials)):
                                        ?>
                                        <tr>
                                            <td colspan="10" class="py-4 px-4 text-gray-500 text-center">Aucun matériau enregistré pour ce projet</td>
                                        </tr>
                                        <?php
                                    else:
                                        foreach ($projectMaterials as $material):
                                            // Déterminer la classe du statut
                                            $statusClass = '';
                                            $statusText = '';
                                            $statusBg = '';

                                            if ($material['status'] == 'validé' || $material['status'] == 'en_cours') {
                                                $statusClass = 'text-blue-800';
                                                $statusBg = 'bg-blue-100';
                                                $statusText = 'Commandé';
                                                $rowClass = 'bg-blue-50';
                                            } elseif ($material['status'] == 'reçu') {
                                                $statusClass = 'text-green-800';
                                                $statusBg = 'bg-green-100';
                                                $statusText = 'Reçu';
                                                $rowClass = 'bg-green-50';
                                            } elseif ($material['status'] == 'annulé') {
                                                $statusClass = 'text-gray-800';
                                                $statusBg = 'bg-gray-100';
                                                $statusText = 'Annulé';
                                                $rowClass = 'bg-gray-50';
                                            } else {
                                                $statusClass = 'text-yellow-800';
                                                $statusBg = 'bg-yellow-100';
                                                $statusText = 'En attente';
                                                $rowClass = 'bg-yellow-50';
                                            }
                                            
                                            // Formatter la date de mise à jour
                                            $updatedAt = date('d/m/Y H:i', strtotime($material['updated_at']));
                                            ?>
                                            <tr class="hover:bg-gray-50 <?php echo $rowClass; ?>">
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <div class="text-sm font-mono text-gray-600"><?php echo htmlspecialchars($material['idExpression']); ?></div>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap text-sm">
                                                    <?php echo htmlspecialchars($material['nom_client']); ?>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="ml-0">
                                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($material['designation']); ?></div>
                                                            <?php if ($material['order_count'] > 1): ?>
                                                            <div class="text-xs text-gray-500"><?php echo $material['order_count']; ?> commandes</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($material['quantity']); ?>
                                                    <?php echo htmlspecialchars($material['unit'] ?? ''); ?>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($material['qt_stock'] ?? '0'); ?>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($material['qt_acheter'] ?? '0'); ?>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo !empty($material['prix_unitaire']) ? formatNumber($material['prix_unitaire']) . ' FCFA' : '-'; ?>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($material['fournisseur'] ?? '-'); ?>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusBg; ?> <?php echo $statusClass; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo $updatedAt; ?>
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
                <?php else: ?>
                    <!-- Vue d'ensemble - Statistiques générales et graphiques -->
                    <!-- KPI Cards avec regroupement -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
                        <!-- KPI 1: Projets regroupés -->
                        <div class="kpi-card">
                            <div class="icon blue">
                                <span class="material-icons">folder</span>
                            </div>
                            <div class="title">Projets uniques</div>
                            <div class="value"><?php echo formatNumber($statsProjects['total_projects_grouped']); ?></div>
                            <div class="trend neutral">
                                <span class="material-icons text-sm mr-1">merge_type</span>
                                <?php echo formatNumber($statsProjects['total_projects_individual']); ?> regroupés
                            </div>
                        </div>

                        <!-- KPI 2: Clients distincts -->
                        <div class="kpi-card">
                            <div class="icon purple">
                                <span class="material-icons">people</span>
                            </div>
                            <div class="title">Clients distincts</div>
                            <div class="value"><?php echo formatNumber($statsProjects['total_clients']); ?></div>
                            <div class="trend neutral">
                                <span class="material-icons text-sm mr-1">business</span>
                                <?php echo $selectedClient != 'all' ? 'Client: ' . htmlspecialchars($selectedClient) : 'Tous clients'; ?>
                            </div>
                        </div>

                        <!-- KPI 3: Projets actifs vs terminés -->
                        <div class="kpi-card">
                            <div class="icon green">
                                <span class="material-icons">done_all</span>
                            </div>
                            <div class="title">Statut des projets</div>
                            <div class="value">
                                <span class="text-blue-600"><?php echo formatNumber($statsProjects['active_projects']); ?></span> / 
                                <span class="text-green-600"><?php echo formatNumber($statsProjects['completed_projects']); ?></span>
                            </div>
                            <div class="trend neutral">
                                <span class="material-icons text-sm mr-1">sync</span>
                                Actifs / Terminés
                            </div>
                        </div>

                        <!-- KPI 4: Montant total des achats -->
                        <div class="kpi-card">
                            <div class="icon orange">
                                <span class="material-icons">payments</span>
                            </div>
                            <div class="title">Montant total</div>
                            <div class="value"><?php echo formatNumber($statsProjects['total_amount']); ?> FCFA</div>
                            <div class="trend neutral">
                                <span class="material-icons text-sm mr-1">shopping_cart</span>
                                <?php echo formatNumber($statsProjects['total_items']); ?> articles
                            </div>
                        </div>

                        <!-- KPI 5: Durée moyenne des projets -->
                        <div class="kpi-card">
                            <div class="icon amber">
                                <span class="material-icons">calendar_today</span>
                            </div>
                            <div class="title">Durée moyenne</div>
                            <div class="value"><?php echo formatNumber($statsProjects['avg_duration']); ?> jours</div>
                            <div class="trend neutral">
                                <span class="material-icons text-sm mr-1">timeline</span>
                                Depuis la création
                            </div>
                        </div>
                    </div>

                    <!-- Graphiques -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Graphique 1: Répartition des achats par statut -->
                        <div class="chart-card">
                            <h3 class="chart-title">Répartition des achats par statut</h3>
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>

                        <!-- Graphique 2: Répartition par catégorie -->
                        <div class="chart-card">
                            <h3 class="chart-title">Répartition des achats par catégorie</h3>
                            <div class="chart-container">
                                <canvas id="categoriesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Graphiques avancés -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Graphique 3: Distribution mensuelle des projets -->
                        <div class="chart-card">
                            <h3 class="chart-title">Distribution mensuelle des projets regroupés <?php echo $selectedYear; ?></h3>
                            <div class="chart-container">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>

                        <!-- Graphique 4: Délai moyen de livraison par projet -->
                        <div class="chart-card">
                            <h3 class="chart-title">Délai moyen de livraison par projet (jours)</h3>
                            <div class="chart-container">
                                <canvas id="delaiChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Tableau des projets récents regroupés -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold">Projets récents - Vue regroupée par code projet</h2>
                            <?php if ($selectedClient != 'all'): ?>
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium rounded-full px-2.5 py-1 flex items-center">
                                    <span class="material-icons text-sm mr-1">business</span>
                                    Client: <?php echo htmlspecialchars($selectedClient); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Code Projet
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Regroupement
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Client principal
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tous clients
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Description
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Chef de projet
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Période
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Articles
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Montant
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Progression
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Statut
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php
                                    if (empty($recentProjects)):
                                        ?>
                                        <tr>
                                            <td colspan="11" class="py-4 px-4 text-gray-500 text-center">Aucun projet trouvé</td>
                                        </tr>
                                        <?php
                                    else:
                                        foreach ($recentProjects as $project):
                                            // Déterminer la classe du statut
                                            $statusClass = $project['is_completed'] ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                            $statusText = $project['is_completed'] ? 'Terminé' : 'Actif';
                                            
                                            if ($project['is_fully_received']) {
                                                $rowClass = 'bg-green-50';
                                            } elseif ($project['is_completed']) {
                                                $rowClass = 'bg-red-50';
                                            } else {
                                                $rowClass = '';
                                            }

                                            // Déterminer la couleur de la barre de progression
                                            $progressColorClass = getProgressColor($project['completed_percentage']);

                                            // Formater les dates
                                            $dateMin = date('d/m/Y', strtotime($project['date_creation_min']));
                                            $dateMax = date('d/m/Y', strtotime($project['date_creation_max']));
                                            ?>
                                            <tr class="hover:bg-gray-50 <?php echo $rowClass; ?>">
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <a href="projet_details.php?client=<?php echo urlencode($project['client_principal']); ?>&code_projet=<?php echo urlencode($project['code_projet']); ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                                                        <?php echo htmlspecialchars($project['code_projet']); ?>
                                                    </a>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <?php if ($project['nb_occurrences'] > 1): ?>
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">
                                                            <?php echo $project['nb_occurrences']; ?> regroupés
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                                            Unique
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap"><?php echo htmlspecialchars($project['client_principal']); ?></td>
                                                <td class="py-3 px-4">
                                                    <div class="text-sm text-gray-900 max-w-xs truncate" title="<?php echo htmlspecialchars($project['tous_clients']); ?>">
                                                        <?php echo htmlspecialchars($project['tous_clients']); ?>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="text-sm text-gray-900 truncate max-w-xs" title="<?php echo htmlspecialchars($project['description_projet']); ?>">
                                                        <?php echo htmlspecialchars(substr($project['description_projet'], 0, 50)) . (strlen($project['description_projet']) > 50 ? '...' : ''); ?>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap"><?php echo htmlspecialchars($project['chefprojet']); ?></td>
                                                <td class="py-3 px-4 whitespace-nowrap text-sm">
                                                    <?php echo $dateMin; ?>
                                                    <?php if ($dateMin != $dateMax): ?>
                                                        <br>au <?php echo $dateMax; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap"><?php echo formatNumber($project['total_items']); ?></td>
                                                <td class="py-3 px-4 whitespace-nowrap font-medium"><?php echo formatNumber($project['total_amount']); ?> FCFA</td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                                                            <div class="<?php echo $progressColorClass; ?> h-2.5 rounded-full" style="width: <?php echo $project['completed_percentage']; ?>%"></div>
                                                        </div>
                                                        <span class="text-sm font-medium"><?php echo $project['completed_percentage']; ?>%</span>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
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

                    <!-- Statistiques par catégorie (reste identique) -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                        <h2 class="text-lg font-semibold mb-4">Répartition des achats par catégorie de produits</h2>

                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Catégorie
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Articles
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Montant
                                        </th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            % du total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php
                                    if (empty($categoriesStats)):
                                        ?>
                                        <tr>
                                            <td colspan="4" class="py-4 px-4 text-gray-500 text-center">Aucune donnée disponible</td>
                                        </tr>
                                        <?php
                                    else:
                                        foreach ($categoriesStats as $index => $cat):
                                            $categoryName = $cat['category'] ?? 'Non catégorisé';
                                            $percentage = $statsProjects['total_amount'] > 0 ? round(($cat['amount'] / $statsProjects['total_amount']) * 100, 1) : 0;

                                            // Déterminer la couleur du badge selon la catégorie
                                            $badgeClass = 'bg-gray-100 text-gray-800'; // Par défaut
                                            
                                            switch (strtoupper($categoryName)) {
                                                case 'REVETEMENT DE PEINTURE ET DE PROTECTION':
                                                case 'REPP':
                                                    $badgeClass = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'ELECTRICITE':
                                                case 'ELEC':
                                                    $badgeClass = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'REVETEMENT DE PROTECTION DE SOL':
                                                case 'REPS':
                                                    $badgeClass = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'ACCESOIRE':
                                                case 'ACC':
                                                    $badgeClass = 'bg-purple-100 text-purple-800';
                                                    break;
                                                case 'MATERIELS FERREUX':
                                                case 'MAFE':
                                                    $badgeClass = 'bg-red-100 text-red-800';
                                                    break;
                                                case 'DIVERS':
                                                case 'DIV':
                                                    $badgeClass = 'bg-indigo-100 text-indigo-800';
                                                    break;
                                                case 'EQUIPEMENT DE PROTECTION INDIVIDUEL':
                                                case 'EDPI':
                                                    $badgeClass = 'bg-pink-100 text-pink-800';
                                                    break;
                                                case 'OUTILS ET ACCESSOIRES DE SOUDURE':
                                                case 'OACS':
                                                    $badgeClass = 'bg-orange-100 text-orange-800';
                                                    break;
                                                case 'MATERIELS DE PLOMBERIE':
                                                case 'PLOM':
                                                    $badgeClass = 'bg-teal-100 text-teal-800';
                                                    break;
                                                case 'BOULONS, VIS ET ECROUS':
                                                case 'BOVE':
                                                    $badgeClass = 'bg-gray-100 text-gray-800';
                                                    break;
                                            }
                                            ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $badgeClass; ?>">
                                                        <?php echo htmlspecialchars($categoryName); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap"><?php echo formatNumber($cat['item_count']); ?></td>
                                                <td class="py-3 px-4 whitespace-nowrap font-medium"><?php echo formatNumber($cat['amount']); ?> FCFA</td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-24 bg-gray-200 rounded-full h-2.5 mr-2">
                                                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                                        </div>
                                                        <span><?php echo $percentage; ?>%</span>
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
                <?php endif; ?>
            <?php endif; ?>

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
                    <p class="text-sm opacity-80 mt-1">Analyse détaillée des commandes et achats</p>
                </a>

                <a href="stats_fournisseurs.php" class="bg-purple-600 hover:bg-purple-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">business</span>
                    <h3 class="text-lg font-semibold">Statistiques des Fournisseurs</h3>
                    <p class="text-sm opacity-80 mt-1">Performance et historique des fournisseurs</p>
                </a>

                <a href="stats_produits.php" class="bg-green-600 hover:bg-green-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">inventory</span>
                    <h3 class="text-lg font-semibold">Statistiques des Produits</h3>
                    <p class="text-sm opacity-80 mt-1">Analyse du stock et des mouvements</p>
                </a>

                <a href="stats_canceled_orders.php" class="bg-red-600 hover:bg-red-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
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
            
            // Soumission automatique du formulaire lors du changement de client ou de période
            const autoSubmitElements = ['client-select', 'period-select'];
            autoSubmitElements.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('change', function() {
                        this.form.submit();
                    });
                }
            });
            
            // Si period-select change à "year", rafraîchir pour afficher le sélecteur d'année
            const periodSelect = document.getElementById('period-select');
            if (periodSelect) {
                periodSelect.addEventListener('change', function() {
                    if (this.value === 'year') {
                        this.form.submit();
                    }
                });
            }
        });

        // Configuration des graphiques
        function setupCharts() {
            // Configuration des couleurs et options communes
            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.color = '#64748b';
            Chart.defaults.elements.line.borderWidth = 3;
            Chart.defaults.elements.point.radius = 3;
            Chart.defaults.elements.point.hoverRadius = 5;
            
            <?php if (empty($selectedCodeProjet) || empty($projectInfo)): ?>
                // Graphique 1: Répartition des achats par statut
                const statusCtx = document.getElementById('statusChart');
                if (statusCtx) {
                    const statusData = <?= json_encode($statusChartData) ?>;
                    renderCategoriesChart(statusCtx.id, {
                        labels: statusData.labels,
                        data: statusData.data,
                        backgroundColor: statusData.backgroundColor
                    });
                }

                // Graphique 2: Répartition par catégorie
                const categoriesCtx = document.getElementById('categoriesChart');
                if (categoriesCtx) {
                    const categoriesData = <?= json_encode($categoriesChartData) ?>;
                    renderCategoriesChart(categoriesCtx.id, {
                        labels: categoriesData.labels,
                        data: categoriesData.data,
                        backgroundColor: categoriesData.backgroundColor
                    });
                }
                
                // Graphique 3: Distribution mensuelle des projets
                const monthlyCtx = document.getElementById('monthlyChart');
                if (monthlyCtx) {
                    const monthlyData = <?= json_encode($monthlyChartData) ?>;
                    
                    new Chart(monthlyCtx, {
                        type: 'bar',
                        data: {
                            labels: monthlyData.labels,
                            datasets: [{
                                label: 'Nombre de projets regroupés',
                                data: monthlyData.data,
                                backgroundColor: 'rgba(59, 130, 246, 0.7)',
                                borderColor: 'rgb(59, 130, 246)',
                                borderWidth: 1,
                                borderRadius: 6,
                                barPercentage: 0.6
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
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: {
                                        label: function(context) {
                                            return 'Projets regroupés: ' + context.raw;
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
                                }
                            }
                        }
                    });
                }
                
                // Graphique 4: Délai moyen de livraison par projet
                const delaiCtx = document.getElementById('delaiChart');
                if (delaiCtx) {
                    const delaiData = <?= json_encode($delaiChartData) ?>;
                    
                    new Chart(delaiCtx, {
                        type: 'bar',
                        data: {
                            labels: delaiData.labels,
                            datasets: [{
                                label: 'Jours',
                                data: delaiData.data,
                                backgroundColor: function(context) {
                                    const value = context.dataset.data[context.dataIndex];
                                    // Couleur en fonction du délai
                                    if (value <= 7) return 'rgba(16, 185, 129, 0.7)'; // Vert - Bon
                                    if (value <= 14) return 'rgba(245, 158, 11, 0.7)'; // Jaune - Moyen
                                    return 'rgba(239, 68, 68, 0.7)'; // Rouge - Mauvais
                                },
                                borderWidth: 0,
                                borderRadius: 6,
                                maxBarThickness: 40
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Jours'
                                    }
                                }
                            }
                        }
                    });
                }
            <?php endif; ?>
        }

        // Gestion de l'export PDF
        function handleExportPDF() {
            // Afficher un message de chargement
            Swal.fire({
                title: 'Génération du rapport',
                text: 'Le rapport PDF avec regroupement par code projet est en cours de génération...',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Construire l'URL avec les paramètres actuels
            let pdfUrl = 'generate_report.php?type=projets_regroupes';

            // Ajouter les filtres sélectionnés
            const selectedClient = <?= json_encode($selectedClient) ?>;
            const selectedCodeProjet = <?= json_encode($selectedCodeProjet) ?>;
            const selectedPeriod = <?= json_encode($selectedPeriod) ?>;
            const selectedYear = <?= json_encode($selectedYear) ?>;
            const selectedStatus = <?= json_encode($selectedStatus) ?>;

            if (selectedClient !== 'all') {
                pdfUrl += '&client=' + encodeURIComponent(selectedClient);
            }

            if (selectedCodeProjet) {
                pdfUrl += '&code_projet=' + encodeURIComponent(selectedCodeProjet);
            }

            if (selectedPeriod !== 'all') {
                pdfUrl += '&period=' + encodeURIComponent(selectedPeriod);
                
                if (selectedPeriod === 'year') {
                    pdfUrl += '&year=' + encodeURIComponent(selectedYear);
                }
            }

            if (selectedStatus !== 'all') {
                pdfUrl += '&status=' + encodeURIComponent(selectedStatus);
            }

            // Rediriger vers le script de génération de PDF après un délai
            setTimeout(() => {
                window.location.href = pdfUrl;
            }, 1500);
        }

        // Formatage des montants pour l'affichage
        function formatMoney(amount) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'decimal',
                maximumFractionDigits: 0
            }).format(amount);
        }
    </script>
</body>
</html>