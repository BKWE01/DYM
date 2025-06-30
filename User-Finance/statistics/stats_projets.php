<?php

/**
 * Module de statistiques détaillées des projets - VERSION GROUPÉE PAR CODE PROJET AVEC DATATABLES
 * Affiche l'analyse des projets regroupés par code projet avec agrégation des données
 * MODIFICATION : Affichage de TOUS les projets avec DataTables et redirection vers projet_details.php
 * 
 * @package DYM_MANUFACTURE
 * @subpackage expressions_besoins/User-Achat/statistics
 * @version 2.3.1
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

// REDIRECTION VERS PROJET_DETAILS.PHP SI UN PROJET EST SÉLECTIONNÉ
if (isset($_GET['code_projet']) && !empty($_GET['code_projet'])) {
    $codeProjet = $_GET['code_projet'];
    header("Location: projet_details.php?code_projet=" . urlencode($codeProjet));
    exit();
}

// Connexion à la base de données
require_once '../../components/config.php';
$base_url = PROJECT_ROOT;
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Récupérer les filtres sélectionnés
$selectedClient = isset($_GET['client']) ? $_GET['client'] : 'all';
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
function getProgressColor($percentage)
{
    if ($percentage >= 75) return 'bg-green-500';
    if ($percentage >= 50) return 'bg-blue-500';
    if ($percentage >= 25) return 'bg-yellow-500';
    return 'bg-red-500';
}

// Fonction pour calculer le pourcentage avec gestion des erreurs
function calculatePercentage($value, $total)
{
    if ($total == 0) return 0;
    return round(($value / $total) * 100);
}

// Construction des conditions de filtre pour les requêtes SQL
$dateCondition = "";
$statusCondition = "";

// Condition de date en fonction de la période sélectionnée
switch ($selectedPeriod) {
    case 'today':
        $dateCondition = "AND DATE(ip.created_at) = CURDATE()";
        break;
    case 'week':
        $dateCondition = "AND YEARWEEK(ip.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
        $dateCondition = "AND MONTH(ip.created_at) = MONTH(CURDATE()) AND YEAR(ip.created_at) = YEAR(CURDATE())";
        break;
    case 'quarter':
        $dateCondition = "AND QUARTER(ip.created_at) = QUARTER(CURDATE()) AND YEAR(ip.created_at) = YEAR(CURDATE())";
        break;
    case 'year':
        $dateCondition = "AND YEAR(ip.created_at) = :year";
        break;
    default:
        $dateCondition = "AND ip.created_at >= '" . getSystemStartDate() . "'";
        break;
}

// Condition de statut adaptée pour le regroupement
$havingCondition = "";
if ($selectedStatus !== 'all') {
    switch ($selectedStatus) {
        case 'completed':
            $havingCondition = "HAVING ROUND((SUM(CASE WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'reçu' OR ed.valide_achat = 'en_cours' THEN 1 ELSE 0 END) / COUNT(ed.id)) * 100, 1) = 100";
            break;
        case 'pending':
            $havingCondition = "HAVING SUM(CASE WHEN ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL THEN 1 ELSE 0 END) > 0";
            break;
        case 'active':
            $havingCondition = ""; // Les projets actifs seront filtrés dans une sous-requête
            break;
        case 'finished':
            $havingCondition = ""; // Les projets terminés seront filtrés dans une sous-requête
            break;
    }
}

// Clause de tri adaptée pour le regroupement
$orderClause = "ORDER BY latest_creation DESC";
if ($selectedSort === 'name') {
    $orderClause = "ORDER BY clients_list ASC, code_projet ASC";
} elseif ($selectedSort === 'value_high') {
    $orderClause = "ORDER BY total_value DESC";
} elseif ($selectedSort === 'value_low') {
    $orderClause = "ORDER BY total_value ASC";
} elseif ($selectedSort === 'progress_high') {
    $orderClause = "ORDER BY completed_percentage DESC";
} elseif ($selectedSort === 'progress_low') {
    $orderClause = "ORDER BY completed_percentage ASC";
}

try {
    // ========================================
    // RÉCUPÉRATION DES DONNÉES DE BASE
    // ========================================

    // Récupérer la liste des clients
    $clientsQuery = "SELECT DISTINCT nom_client 
                    FROM identification_projet 
                    WHERE created_at >= '" . getSystemStartDate() . "'
                    ORDER BY nom_client";
    $clientsStmt = $pdo->prepare($clientsQuery);
    $clientsStmt->execute();
    $clients = $clientsStmt->fetchAll(PDO::FETCH_COLUMN);

    // Si un client est sélectionné, récupérer ses projets (codes projets groupés)
    if ($selectedClient != 'all') {
        $projetsQuery = "SELECT code_projet,
                                GROUP_CONCAT(DISTINCT description_projet SEPARATOR ' | ') as descriptions_grouped
                        FROM identification_projet
                        WHERE nom_client = :nom_client
                        AND created_at >= '" . getSystemStartDate() . "'
                        GROUP BY code_projet
                        ORDER BY MAX(created_at) DESC";
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

    // ========================================
    // STATISTIQUES GÉNÉRALES GROUPÉES
    // ========================================

    // Construction de la condition WHERE pour les clients
    $whereConditions = ["ip.created_at >= '" . getSystemStartDate() . "'"];
    $bindParams = [];

    if ($selectedClient != 'all') {
        $whereConditions[] = "ip.nom_client = :nom_client";
        $bindParams[':nom_client'] = $selectedClient;
    }

    // Ajouter la condition de date
    if ($selectedPeriod === 'year') {
        $whereConditions[] = "YEAR(ip.created_at) = :year";
        $bindParams[':year'] = $selectedYear;
    } elseif ($selectedPeriod === 'today') {
        $whereConditions[] = "DATE(ip.created_at) = CURDATE()";
    } elseif ($selectedPeriod === 'week') {
        $whereConditions[] = "YEARWEEK(ip.created_at, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($selectedPeriod === 'month') {
        $whereConditions[] = "MONTH(ip.created_at) = MONTH(CURDATE()) AND YEAR(ip.created_at) = YEAR(CURDATE())";
    } elseif ($selectedPeriod === 'quarter') {
        $whereConditions[] = "QUARTER(ip.created_at) = QUARTER(CURDATE()) AND YEAR(ip.created_at) = YEAR(CURDATE())";
    }

    $whereClause = "WHERE " . implode(" AND ", $whereConditions);

    // Statistiques générales des projets GROUPÉES PAR CODE PROJET
    $statsQuery = "SELECT 
                    COUNT(DISTINCT ip.code_projet) as total_project_groups,
                    COUNT(DISTINCT ip.id) as total_individual_projects,
                    COUNT(DISTINCT ed.id) as total_items,
                    COALESCE(SUM(CAST(ed.qt_acheter AS DECIMAL(10,2)) * CAST(ed.prix_unitaire AS DECIMAL(10,2))), 0) as total_amount,
                    ROUND(AVG(DATEDIFF(CURDATE(), ip.created_at)), 0) as avg_duration
                  FROM identification_projet ip
                  LEFT JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                  $whereClause";

    $statsStmt = $pdo->prepare($statsQuery);
    foreach ($bindParams as $key => $value) {
        $statsStmt->bindParam($key, $value);
    }
    $statsStmt->execute();
    $statsProjects = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Calculer les projets actifs et terminés séparément
    $activeProjectsQuery = "SELECT COUNT(DISTINCT ip.code_projet) as active_project_groups
                          FROM identification_projet ip
                          WHERE NOT EXISTS (
                              SELECT 1 FROM project_status ps 
                              INNER JOIN identification_projet ip2 ON ps.idExpression = ip2.idExpression 
                              WHERE ip2.code_projet = ip.code_projet AND ps.status = 'completed'
                          )
                          AND " . implode(" AND ", $whereConditions);

    $activeStmt = $pdo->prepare($activeProjectsQuery);
    foreach ($bindParams as $key => $value) {
        $activeStmt->bindParam($key, $value);
    }
    $activeStmt->execute();
    $activeResult = $activeStmt->fetch(PDO::FETCH_ASSOC);
    $statsProjects['active_project_groups'] = $activeResult['active_project_groups'];

    $completedProjectsQuery = "SELECT COUNT(DISTINCT ip.code_projet) as completed_project_groups
                             FROM identification_projet ip
                             WHERE EXISTS (
                                 SELECT 1 FROM project_status ps 
                                 INNER JOIN identification_projet ip2 ON ps.idExpression = ip2.idExpression 
                                 WHERE ip2.code_projet = ip.code_projet AND ps.status = 'completed'
                             )
                             AND " . implode(" AND ", $whereConditions);

    $completedStmt = $pdo->prepare($completedProjectsQuery);
    foreach ($bindParams as $key => $value) {
        $completedStmt->bindParam($key, $value);
    }
    $completedStmt->execute();
    $completedResult = $completedStmt->fetch(PDO::FETCH_ASSOC);
    $statsProjects['completed_project_groups'] = $completedResult['completed_project_groups'];

    // ========================================
    // STATISTIQUES DE STATUT DES ACHATS
    // ========================================

    // Récupérer le statut des achats des projets GROUPÉS
    $statusQuery = "SELECT 
                     CASE 
                       WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'en_cours' THEN 'Commandé'
                       WHEN ed.valide_achat = 'reçu' THEN 'Reçu'
                       WHEN ed.valide_achat = 'annulé' THEN 'Annulé'
                       ELSE 'En attente'
                     END as status,
                     COUNT(*) as count,
                     COALESCE(SUM(CAST(ed.qt_acheter AS DECIMAL(10,2)) * CAST(ed.prix_unitaire AS DECIMAL(10,2))), 0) as amount
                   FROM identification_projet ip
                   LEFT JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                   $whereClause
                   AND ed.qt_acheter > 0
                   GROUP BY 
                     CASE 
                       WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'en_cours' THEN 'Commandé'
                       WHEN ed.valide_achat = 'reçu' THEN 'Reçu'
                       WHEN ed.valide_achat = 'annulé' THEN 'Annulé'
                       ELSE 'En attente'
                     END";

    $statusStmt = $pdo->prepare($statusQuery);
    foreach ($bindParams as $key => $value) {
        $statusStmt->bindParam($key, $value);
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

    $statusesFound = [
        'En attente' => false,
        'Commandé' => false,
        'Reçu' => false,
        'Annulé' => false
    ];

    foreach ($statusStats as $stat) {
        $statusesFound[$stat['status']] = true;
        $statusChartData['labels'][] = $stat['status'];
        $statusChartData['data'][] = (float) $stat['amount'];
    }

    // Ajouter les statuts manquants avec des valeurs nulles pour le graphique
    foreach ($statusesFound as $status => $found) {
        if (!$found) {
            $statusChartData['labels'][] = $status;
            $statusChartData['data'][] = 0;
        }
    }

    // ========================================
    // RÉCUPÉRATION DE TOUS LES PROJETS GROUPÉS
    // ========================================

    // REQUÊTE PRINCIPALE : Récupérer TOUS les projets GROUPÉS par code_projet avec agrégation
    $allProjectsQuery = "SELECT 
                            ip.code_projet,
                            GROUP_CONCAT(DISTINCT ip.nom_client ORDER BY ip.nom_client SEPARATOR ', ') as clients_list,
                            COUNT(DISTINCT ip.id) as project_count,
                            GROUP_CONCAT(DISTINCT ip.description_projet ORDER BY ip.created_at SEPARATOR ' | ') as descriptions_combined,
                            GROUP_CONCAT(DISTINCT ip.sitgeo ORDER BY ip.created_at SEPARATOR ', ') as locations_combined,
                            GROUP_CONCAT(DISTINCT ip.chefprojet ORDER BY ip.created_at SEPARATOR ', ') as project_managers,
                            MIN(ip.created_at) as earliest_creation,
                            MAX(ip.created_at) as latest_creation,
                            COUNT(ed.id) as total_items,
                            COALESCE(SUM(CAST(ed.qt_acheter AS DECIMAL(10,2)) * CAST(ed.prix_unitaire AS DECIMAL(10,2))), 0) as total_value,
                            SUM(CASE WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'reçu' OR ed.valide_achat = 'en_cours' THEN 1 ELSE 0 END) as completed_items,
                            SUM(CASE WHEN ed.valide_achat = 'reçu' THEN 1 ELSE 0 END) as received_items,
                            SUM(CASE WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'en_cours' THEN 1 ELSE 0 END) as ordered_items,
                            SUM(CASE WHEN ed.valide_achat = 'annulé' THEN 1 ELSE 0 END) as canceled_items,
                            SUM(CASE WHEN ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL THEN 1 ELSE 0 END) as pending_items,
                            CASE 
                                WHEN COUNT(ed.id) > 0 AND COUNT(ed.id) = SUM(CASE WHEN ed.valide_achat = 'reçu' THEN 1 ELSE 0 END) THEN 1
                                ELSE 0 
                            END as is_fully_received,
                            CASE 
                                WHEN COUNT(ed.id) > 0 THEN ROUND((SUM(CASE WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'reçu' OR ed.valide_achat = 'en_cours' THEN 1 ELSE 0 END) / COUNT(ed.id)) * 100, 1)
                                ELSE 0 
                            END as completed_percentage,
                            CASE
                                WHEN EXISTS (
                                    SELECT 1 FROM project_status ps 
                                    INNER JOIN identification_projet ip2 ON ps.idExpression = ip2.idExpression 
                                    WHERE ip2.code_projet = ip.code_projet AND ps.status = 'completed'
                                ) THEN 1
                                ELSE 0
                            END as has_completed_projects
                           FROM identification_projet ip
                           LEFT JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                           $whereClause
                           GROUP BY ip.code_projet
                           $havingCondition
                           $orderClause";

    // Exécution de la requête pour tous les projets
    $allProjectsStmt = $pdo->prepare($allProjectsQuery);
    foreach ($bindParams as $key => $value) {
        $allProjectsStmt->bindParam($key, $value);
    }
    $allProjectsStmt->execute();
    $allProjects = $allProjectsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Compter le nombre total de groupes de projets trouvés
    $totalProjectGroups = count($allProjects);

    // ========================================
    // STATISTIQUES PAR CATÉGORIE
    // ========================================

    // Récupérer les statistiques par catégorie pour le client/projet sélectionné
    $categoriesQuery = "SELECT 
                        COALESCE(c.libelle, 'Non catégorisé') as category, 
                        COUNT(ed.id) as item_count,
                        COALESCE(SUM(CAST(ed.qt_acheter AS DECIMAL(10,2)) * CAST(ed.prix_unitaire AS DECIMAL(10,2))), 0) as amount
                       FROM expression_dym ed
                       JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                       LEFT JOIN products p ON ed.designation = p.product_name
                       LEFT JOIN categories c ON p.category = c.id
                       $whereClause
                       AND ed.qt_acheter > 0
                       GROUP BY c.libelle
                       ORDER BY amount DESC";

    $categoriesStmt = $pdo->prepare($categoriesQuery);
    foreach ($bindParams as $key => $value) {
        $categoriesStmt->bindParam($key, $value);
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
        '#4299E1',
        '#48BB78',
        '#ECC94B',
        '#9F7AEA',
        '#ED64A6',
        '#F56565',
        '#667EEA',
        '#ED8936',
        '#38B2AC',
        '#CBD5E0'
    ];

    foreach ($categoriesStats as $index => $cat) {
        $categoryName = $cat['category'] ?: 'Non catégorisé';
        $categoriesChartData['labels'][] = $categoryName;
        $categoriesChartData['data'][] = (float) $cat['amount'];
        $categoriesChartData['backgroundColor'][] = $colors[$index % count($colors)];
    }

    // ========================================
    // DISTRIBUTION MENSUELLE
    // ========================================

    // Distribution mensuelle des projets groupés par code_projet pour l'année sélectionnée
    $monthlyWhereConditions = ["YEAR(created_at) = :year"];
    $monthlyBindParams = [':year' => $selectedYear];

    if ($selectedClient != 'all') {
        $monthlyWhereConditions[] = "nom_client = :nom_client";
        $monthlyBindParams[':nom_client'] = $selectedClient;
    }

    $monthlyWhereClause = "WHERE " . implode(" AND ", $monthlyWhereConditions);

    $monthlyProjectsQuery = "SELECT 
                            MONTH(created_at) as month,
                            COUNT(DISTINCT code_projet) as count
                           FROM identification_projet
                           $monthlyWhereClause
                           GROUP BY MONTH(created_at)
                           ORDER BY month";

    $monthlyStmt = $pdo->prepare($monthlyProjectsQuery);
    foreach ($monthlyBindParams as $key => $value) {
        $monthlyStmt->bindParam($key, $value);
    }
    $monthlyStmt->execute();
    $monthlyProjects = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les données pour le graphique mensuel
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

    $monthlyChartData = [
        'labels' => [],
        'data' => array_fill(0, 12, 0)
    ];

    // Remplir les données
    foreach ($monthlyProjects as $monthData) {
        $monthIndex = (int)$monthData['month'] - 1; // 0-based index for arrays
        $monthlyChartData['data'][$monthIndex] = (int)$monthData['count'];
    }

    // Remplir les labels
    foreach ($monthNames as $index => $name) {
        $monthlyChartData['labels'][] = $name;
    }
} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
    error_log("Erreur SQL dans stats_projets.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des Projets Groupés | Service Achat</title>

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

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- Bootstrap CSS (pour DataTables) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="asset/css/stats_projet.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Animation CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />

    <style>
        a {
            text-decoration: none !important;
        }

        /* Styles personnalisés pour DataTables */
        .dataTables_wrapper {
            font-family: 'Inter', sans-serif;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1rem;
        }

        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin-top: 1rem;
        }

        .dataTables_wrapper .dt-buttons {
            margin-bottom: 1rem;
        }

        .dataTables_wrapper .dt-button {
            margin-right: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: 1px solid #d1d5db;
            background-color: #f9fafb;
            color: #374151;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .dataTables_wrapper .dt-button:hover {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .dataTables_wrapper table.dataTable thead th {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
            padding: 0.75rem;
        }

        .dataTables_wrapper table.dataTable tbody tr:hover {
            background-color: #f9fafb;
        }

        .dataTables_wrapper .dataTables_processing {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Responsive design pour DataTables */
        @media (max-width: 768px) {

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                text-align: center;
                margin-bottom: 0.5rem;
            }

            .dataTables_wrapper .dt-buttons {
                text-align: center;
                margin-bottom: 0.5rem;
            }

            .dataTables_wrapper .dt-button {
                margin: 0.25rem;
                padding: 0.375rem 0.75rem;
                font-size: 0.75rem;
            }
        }

        /* Style pour les lignes cliquables */
        .clickable-row {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .clickable-row:hover {
            background-color: #f0f9ff !important;
        }
    </style>
</head>

<body class="bg-gray-100 font-inter text-gray-800">
    <div class="wrapper">
        <?php include_once '../../components/navbar_finance.php'; ?>

        <main class="flex-1 p-6">
            <!-- Dashboard Header -->
            <div class="dashboard-header mb-6">
                <div class="flex items-center">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-2">
                        <span class="material-icons">arrow_back</span>
                    </a>
                    <h1 class="text-xl font-bold text-gray-800 flex items-center">
                        <span class="material-icons mr-3 text-blue-600">folder_special</span>
                        Statistiques des Projets Groupés
                        <span class="ml-2 px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                            Par Code Projet
                        </span>
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

            <!-- Notice explicative sur le regroupement -->
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <span class="material-icons text-blue-400">info</span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Mode Groupé :</strong> Les projets sont regroupés par code projet identique.
                            Tous les projets partageant le même code sont agrégés en une seule ligne avec cumul des données.
                            <strong>Cliquez sur une ligne</strong> pour voir les détails complets du groupe de projets.
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
                            <option value="active" <?php echo $selectedStatus == 'active' ? 'selected' : ''; ?>>Groupes actifs</option>
                            <option value="finished" <?php echo $selectedStatus == 'finished' ? 'selected' : ''; ?>>Groupes terminés</option>
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
                <!-- Vue d'ensemble - Statistiques générales et graphiques -->
                <!-- KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- KPI 1: Nombre total de groupes de projets -->
                    <div class="kpi-card">
                        <div class="icon blue">
                            <span class="material-icons">folder_shared</span>
                        </div>
                        <div class="title">Groupes de projets</div>
                        <div class="value"><?php echo formatNumber($statsProjects['total_project_groups']); ?></div>
                        <div class="trend neutral">
                            <span class="material-icons text-sm mr-1">folder</span>
                            <?php echo formatNumber($statsProjects['total_individual_projects']); ?> projets individuels
                        </div>
                    </div>

                    <!-- KPI 2: Groupes actifs vs terminés -->
                    <div class="kpi-card">
                        <div class="icon green">
                            <span class="material-icons">done_all</span>
                        </div>
                        <div class="title">Statut des groupes</div>
                        <div class="value">
                            <span class="text-blue-600"><?php echo formatNumber($statsProjects['active_project_groups']); ?></span> /
                            <span class="text-green-600"><?php echo formatNumber($statsProjects['completed_project_groups']); ?></span>
                        </div>
                        <div class="trend neutral">
                            <span class="material-icons text-sm mr-1">sync</span>
                            Actifs / Terminés
                        </div>
                    </div>

                    <!-- KPI 3: Montant total des achats -->
                    <div class="kpi-card">
                        <div class="icon purple">
                            <span class="material-icons">payments</span>
                        </div>
                        <div class="title">Montant total</div>
                        <div class="value"><?php echo formatNumber($statsProjects['total_amount']); ?> FCFA</div>
                        <div class="trend neutral">
                            <span class="material-icons text-sm mr-1">shopping_cart</span>
                            <?php echo formatNumber($statsProjects['total_items']); ?> articles
                        </div>
                    </div>

                    <!-- KPI 4: Durée moyenne des projets -->
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

                <!-- Graphique de distribution mensuelle -->
                <div class="grid grid-cols-1 gap-6 mb-6">
                    <div class="chart-card">
                        <h3 class="chart-title">Distribution mensuelle des groupes de projets <?php echo $selectedYear; ?></h3>
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Tableau de tous les groupes de projets avec DataTables -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex items-center space-x-3">
                            <h2 class="text-lg font-semibold">Tous les groupes de projets (par code projet)</h2>
                            <span class="bg-gray-100 text-gray-800 text-xs font-medium rounded-full px-2.5 py-1 flex items-center">
                                <span class="material-icons text-sm mr-1">folder_shared</span>
                                <?php echo $totalProjectGroups; ?> groupe(s) trouvé(s)
                            </span>
                        </div>
                        <?php if ($selectedClient != 'all'): ?>
                            <span class="bg-blue-100 text-blue-800 text-xs font-medium rounded-full px-2.5 py-1 flex items-center">
                                <span class="material-icons text-sm mr-1">business</span>
                                Client: <?php echo htmlspecialchars($selectedClient); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Notice d'aide -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <span class="material-icons text-yellow-400 text-sm">info</span>
                            </div>
                            <div class="ml-2">
                                <p class="text-sm text-yellow-800">
                                    <strong>Astuce :</strong> Cliquez sur une ligne du tableau pour voir les détails complets du groupe de projets
                                    (matériaux, mouvements de stock, achats, etc.).
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="projectsTable" class="table table-striped table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>Code Projet</th>
                                    <th>Client(s)</th>
                                    <th>Projets</th>
                                    <th>Chef(s) de projet</th>
                                    <th>Période</th>
                                    <th>Articles</th>
                                    <th>Montant total</th>
                                    <th>Progression</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($allProjects)):
                                    foreach ($allProjects as $project):
                                        // Déterminer la classe du statut
                                        $statusClass = $project['has_completed_projects'] ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                        $statusText = $project['has_completed_projects'] ? 'Terminé' : 'Actif';

                                        // Déterminer la couleur de la barre de progression
                                        $progressColorClass = getProgressColor($project['completed_percentage']);

                                        // Formater les dates
                                        $earliestDate = date('d/m/Y', strtotime($project['earliest_creation']));
                                        $latestDate = date('d/m/Y', strtotime($project['latest_creation']));
                                        $dateRange = ($earliestDate === $latestDate) ? $earliestDate : "$earliestDate - $latestDate";
                                ?>
                                        <tr class="clickable-row" data-code-projet="<?php echo htmlspecialchars($project['code_projet']); ?>">
                                            <td>
                                                <div class="font-medium text-blue-600">
                                                    <?php echo htmlspecialchars($project['code_projet']); ?>
                                                </div>
                                                <?php if ($project['project_count'] > 1): ?>
                                                    <div class="text-xs text-purple-600 font-medium">
                                                        <?php echo $project['project_count']; ?> projets groupés
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="text-sm text-gray-900" title="<?php echo htmlspecialchars($project['clients_list']); ?>">
                                                    <?php echo htmlspecialchars($project['clients_list']); ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">
                                                    <?php echo $project['project_count']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-sm text-gray-900" title="<?php echo htmlspecialchars($project['project_managers']); ?>">
                                                    <?php echo htmlspecialchars($project['project_managers']); ?>
                                                </div>
                                            </td>
                                            <td class="text-sm text-gray-500">
                                                <?php echo $dateRange; ?>
                                            </td>
                                            <td class="text-center"><?php echo formatNumber($project['total_items']); ?></td>
                                            <td class="font-medium"><?php echo formatNumber($project['total_value']); ?> FCFA</td>
                                            <td>
                                                <div class="flex items-center">
                                                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                        <div class="<?php echo $progressColorClass; ?> h-2 rounded-full" style="width: <?php echo $project['completed_percentage']; ?>%"></div>
                                                    </div>
                                                    <span class="text-sm font-medium"><?php echo $project['completed_percentage']; ?>%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="projet_details.php?code_projet=<?php echo urlencode($project['code_projet']); ?>"
                                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                                    title="Voir les détails"
                                                    onclick="event.stopPropagation();">
                                                    <span class="material-icons text-sm">visibility</span>
                                                </a>
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

                <!-- Statistiques par catégorie -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-4">Répartition des achats par catégorie de produits (données groupées)</h2>

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

                                        // Déterminer la couleur du badge
                                        $badgeClass = 'bg-gray-100 text-gray-800'; // Par défaut

                                        switch ($categoryName) {
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

    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

    <!-- Bootstrap JS (pour DataTables) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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

            // Initialisation de DataTables
            initializeDataTables();
        });

        // Configuration des graphiques
        function setupCharts() {
            // Configuration des couleurs et options communes
            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.color = '#64748b';
            Chart.defaults.elements.line.borderWidth = 3;
            Chart.defaults.elements.point.radius = 3;
            Chart.defaults.elements.point.hoverRadius = 5;

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

            // Graphique 3: Distribution mensuelle des groupes de projets
            const monthlyCtx = document.getElementById('monthlyChart');
            if (monthlyCtx) {
                const monthlyData = <?= json_encode($monthlyChartData) ?>;

                new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyData.labels,
                        datasets: [{
                            label: 'Nombre de groupes de projets',
                            data: monthlyData.data,
                            backgroundColor: 'rgba(139, 92, 246, 0.7)',
                            borderColor: 'rgb(139, 92, 246)',
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
                                        return `Groupes de projets: ${context.raw}`;
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
        }

        // Initialisation de DataTables
        function initializeDataTables() {
            const projectsTable = $('#projectsTable');
            if (projectsTable.length > 0) {
                const table = projectsTable.DataTable({
                    responsive: true,
                    processing: true,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                    },
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                        '<"row"<"col-sm-12"B>>' +
                        '<"row"<"col-sm-12"tr>>' +
                        '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    buttons: [{
                            extend: 'excel',
                            text: '<i class="fas fa-file-excel"></i> Excel',
                            className: 'btn btn-success btn-sm',
                            title: 'Statistiques Projets Groupés - ' + new Date().toLocaleDateString('fr-FR'),
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                            }
                        },
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            className: 'btn btn-info btn-sm',
                            title: 'Statistiques Projets Groupés - ' + new Date().toLocaleDateString('fr-FR'),
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                            }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Imprimer',
                            className: 'btn btn-secondary btn-sm',
                            title: 'Statistiques des Projets Groupés',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                            },
                            customize: function(win) {
                                $(win.document.body)
                                    .css('font-size', '10pt')
                                    .prepend(
                                        '<div style="text-align:center; margin-bottom: 20px;">' +
                                        '<h1>DYM MANUFACTURE</h1>' +
                                        '<h2>Statistiques des Projets Groupés</h2>' +
                                        '<p>Généré le ' + new Date().toLocaleDateString('fr-FR') + '</p>' +
                                        '</div>'
                                    );

                                $(win.document.body).find('table')
                                    .addClass('compact')
                                    .css('font-size', 'inherit');
                            }
                        }
                    ],
                    pageLength: 25,
                    lengthMenu: [
                        [10, 25, 50, 100, -1],
                        [10, 25, 50, 100, "Tout"]
                    ],
                    order: [
                        [4, 'desc']
                    ], // Trier par date (colonne Période) par défaut
                    columnDefs: [{
                            targets: [5, 6], // Colonnes Articles et Montant
                            className: 'text-center'
                        },
                        {
                            targets: [7], // Colonne Progression
                            orderable: false,
                            searchable: false
                        },
                        {
                            targets: [9], // Colonne Actions
                            orderable: false,
                            searchable: false,
                            className: 'text-center'
                        }
                    ],
                    initComplete: function() {
                        console.log('DataTables initialisé avec succès');
                        $('.dt-button').addClass('me-2 mb-2');
                    },
                    drawCallback: function(settings) {
                        $('[data-bs-toggle="tooltip"]').tooltip();
                    }
                });

                // Gestion des clics sur les lignes pour navigation vers les détails
                $('#projectsTable tbody').on('click', 'tr.clickable-row', function() {
                    const codeProjet = $(this).data('code-projet');
                    if (codeProjet) {
                        window.location.href = 'projet_details.php?code_projet=' + encodeURIComponent(codeProjet);
                    }
                });
            }
        }

        // Gestion de l'export PDF
        function handleExportPDF() {
            // Afficher un message de chargement
            Swal.fire({
                title: 'Génération du rapport',
                text: 'Le rapport PDF des projets groupés est en cours de génération...',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Construire l'URL avec les paramètres actuels
            let pdfUrl = 'generate_report.php?type=projets_grouped';

            // Ajouter les filtres sélectionnés
            const selectedClient = <?= json_encode($selectedClient) ?>;
            const selectedPeriod = <?= json_encode($selectedPeriod) ?>;
            const selectedYear = <?= json_encode($selectedYear) ?>;
            const selectedStatus = <?= json_encode($selectedStatus) ?>;

            if (selectedClient !== 'all') {
                pdfUrl += '&client=' + encodeURIComponent(selectedClient);
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

                // Fermer le message de chargement après la redirection
                setTimeout(() => {
                    Swal.close();
                }, 2000);
            }, 1500);
        }

        // Formatage des montants pour l'affichage
        function formatMoney(amount) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'decimal',
                maximumFractionDigits: 0
            }).format(amount);
        }

        // Fonction de rendu des graphiques (si chart_functions.js n'est pas disponible)
        function renderCategoriesChart(canvasId, data) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.data,
                        backgroundColor: data.backgroundColor,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                boxWidth: 12,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = formatMoney(context.raw);
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return `${label}: ${value} FCFA (${percentage}%)`;
                                }
                            }
                        }
                    },
                    layout: {
                        padding: {
                            top: 10,
                            bottom: 10
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>