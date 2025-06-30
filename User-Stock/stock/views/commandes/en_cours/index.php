<?php

/**
 * Page de suivi des commandes en cours pour le service stock
 * Version corrigée avec gestion optimisée des dates
 * 
 * Cette page permet au gestionnaire de stock de consulter les matériaux 
 * commandés par le service achat et en attente de réception
 * 
 * @package DYM_MANUFACTURE
 * @subpackage stock
 */

session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../../../index.php");
    exit();
}

// Connexion à la base de données et helpers
include_once '../../../../../database/connection.php';
include_once '../../../../../include/date_helper.php';

// ===============================
// INITIALISATION DES VARIABLES
// ===============================
$message = '';
$orderedMaterials = [];
$isUserSuperAdmin = false;

/**
 * Fonction utilitaire pour formater les dates en français
 * @param string $date Date au format SQL ou autre
 * @return string Date formatée dd/mm/yyyy ou 'N/A'
 */
function formatDateFrench($date) {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format('d/m/Y');
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Fonction pour calculer les jours depuis une date
 * @param string $date Date de référence
 * @return int Nombre de jours
 */
function calculateDaysSince($date) {
    if (empty($date) || $date === 'N/A') {
        return 0;
    }
    
    try {
        $dateObj = new DateTime($date);
        $today = new DateTime();
        $interval = $today->diff($dateObj);
        return $interval->days;
    } catch (Exception $e) {
        return 0;
    }
}

// ===============================
// RÉCUPÉRATION DES DONNÉES
// ===============================
try {
    // Vérifier le rôle de l'utilisateur
    $userRoleQuery = "SELECT role FROM users_exp WHERE id = :user_id";
    $userRoleStmt = $pdo->prepare($userRoleQuery);
    $userRoleStmt->bindParam(':user_id', $_SESSION['user_id']);
    $userRoleStmt->execute();
    $userRole = $userRoleStmt->fetch(PDO::FETCH_ASSOC);

    if ($userRole && $userRole['role'] === 'super_admin') {
        $isUserSuperAdmin = true;
    }
} catch (Exception $e) {
    $isUserSuperAdmin = false;
}

try {
    // Obtenir la date de début du système pour le filtrage
    $systemStartDate = getSystemStartDate();

    /* REQUÊTE OPTIMISÉE POUR LES MATÉRIAUX COMMANDÉS 
       AVEC GESTION CORRECTE DES DATES ET FORMATAGE FRANÇAIS
    */
    $orderedQuery = "SELECT 
                material.id, 
                material.expression_id, 
                material.designation, 
                material.quantity, 
                material.original_quantity,
                material.unit, 
                material.prix_unitaire,
                material.fournisseur,
                material.status,
                material.qt_restante,
                material.quantity_remaining,
                material.code_projet,
                material.nom_client,
                material.date_achat,
                material.date_achat_timestamp,
                material.is_partial,
                material.command_count,
                material.total_partial_quantity,
                material.source_table,
                /* CALCUL DES JOURS DEPUIS LA COMMANDE */
                CASE 
                    WHEN material.date_achat IS NOT NULL 
                    THEN DATEDIFF(CURRENT_DATE, material.date_achat)
                    ELSE NULL 
                END as jours_depuis_commande,
                /* FORMATAGE DE LA DATE POUR L'AFFICHAGE */
                DATE_FORMAT(material.date_achat, '%d/%m/%Y') as date_achat_formatted
            FROM (
                -- Matériaux depuis expression_dym
                SELECT 
                    ed.id, 
                    ed.idExpression as expression_id, 
                    ed.designation, 
                    ed.qt_acheter as quantity, 
                    ed.initial_qt_acheter as original_quantity,
                    ed.unit, 
                    ed.prix_unitaire,
                    ed.fournisseur,
                    ed.valide_achat as status,
                    ed.qt_restante,
                    GREATEST(0, (COALESCE(ed.qt_acheter, 0) + COALESCE(ed.qt_restante, 0)) - COALESCE(ed.quantity_stock, 0)) as quantity_remaining,
                    ip.code_projet,
                    ip.nom_client,
                    /* RÉCUPÉRATION DE LA DATE D'ACHAT LA PLUS RÉCENTE */
                    (SELECT am.date_achat 
                     FROM achats_materiaux am 
                     WHERE am.expression_id = ed.idExpression 
                     AND am.designation = ed.designation 
                     AND am.date_achat IS NOT NULL
                     ORDER BY am.date_achat DESC, am.id DESC 
                     LIMIT 1) as date_achat,
                    (SELECT UNIX_TIMESTAMP(am.date_achat) 
                     FROM achats_materiaux am 
                     WHERE am.expression_id = ed.idExpression 
                     AND am.designation = ed.designation 
                     AND am.date_achat IS NOT NULL
                     ORDER BY am.date_achat DESC, am.id DESC 
                     LIMIT 1) as date_achat_timestamp,
                    (SELECT MAX(am.is_partial) FROM achats_materiaux am WHERE am.expression_id = ed.idExpression AND am.designation = ed.designation) as is_partial,
                    (SELECT COUNT(*) FROM achats_materiaux am WHERE am.expression_id = ed.idExpression AND am.designation = ed.designation) as command_count,
                    (SELECT SUM(am.quantity) FROM achats_materiaux am WHERE am.expression_id = ed.idExpression AND am.designation = ed.designation AND am.is_partial = 1) as total_partial_quantity,
                    'expression_dym' as source_table
                FROM expression_dym ed
                JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                WHERE (ed.valide_achat = 'validé' OR ed.valide_achat = 'en_cours' OR ed.valide_achat = 'valide_en_cours')
                AND ed.qt_acheter > 0
                AND ed.created_at >= :start_date
                
                UNION ALL
                
                -- Matériaux depuis besoins
                SELECT 
                    b.id, 
                    b.idBesoin as expression_id, 
                    b.designation_article as designation, 
                    b.qt_acheter as quantity, 
                    b.qt_demande as original_quantity,
                    b.caracteristique as unit, 
                    (SELECT MAX(am.prix_unitaire) FROM achats_materiaux am WHERE am.expression_id = b.idBesoin AND am.designation = b.designation_article) as prix_unitaire,
                    (SELECT MAX(am.fournisseur) FROM achats_materiaux am WHERE am.expression_id = b.idBesoin AND am.designation = b.designation_article) as fournisseur,
                    b.achat_status as status,
                    (b.qt_demande - b.qt_acheter) as qt_restante,
                    GREATEST(0, (COALESCE(b.qt_acheter, 0) + COALESCE((b.qt_demande - b.qt_acheter), 0)) - COALESCE(b.quantity_dispatch_stock, 0)) as quantity_remaining,
                    CONCAT('SYS-', COALESCE(d.service_demandeur, 'Système')) as code_projet,
                    COALESCE(d.client, 'Demande interne') as nom_client,
                    (SELECT am.date_achat 
                     FROM achats_materiaux am 
                     WHERE am.expression_id = b.idBesoin 
                     AND am.designation = b.designation_article 
                     AND am.date_achat IS NOT NULL
                     ORDER BY am.date_achat DESC, am.id DESC 
                     LIMIT 1) as date_achat,
                    (SELECT UNIX_TIMESTAMP(am.date_achat) 
                     FROM achats_materiaux am 
                     WHERE am.expression_id = b.idBesoin 
                     AND am.designation = b.designation_article 
                     AND am.date_achat IS NOT NULL
                     ORDER BY am.date_achat DESC, am.id DESC 
                     LIMIT 1) as date_achat_timestamp,
                    (SELECT MAX(am.is_partial) FROM achats_materiaux am WHERE am.expression_id = b.idBesoin AND am.designation = b.designation_article) as is_partial,
                    (SELECT COUNT(*) FROM achats_materiaux am WHERE am.expression_id = b.idBesoin AND am.designation = b.designation_article) as command_count,
                    (SELECT SUM(am.quantity) FROM achats_materiaux am WHERE am.expression_id = b.idBesoin AND am.designation = b.designation_article AND am.is_partial = 1) as total_partial_quantity,
                    'besoins' as source_table
                FROM besoins b
                LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                WHERE (b.achat_status = 'validé' OR b.achat_status = 'en_cours' OR b.achat_status = 'valide_en_cours')
                AND b.qt_acheter > 0
                AND b.created_at >= :start_date
            ) as material
            WHERE material.date_achat IS NOT NULL
            GROUP BY material.expression_id, material.designation
            ORDER BY material.date_achat_timestamp DESC, material.date_achat DESC";

    $orderedStmt = $pdo->prepare($orderedQuery);
    $orderedStmt->bindParam(':start_date', $systemStartDate);
    $orderedStmt->execute();
    $orderedMaterials = $orderedStmt->fetchAll(PDO::FETCH_ASSOC);

    // Nombre total de commandes
    $totalCommandesCount = count($orderedMaterials);

    // Récupérer des statistiques
    $commandesPartiellesQuery = "SELECT COUNT(*) as count FROM (
                                SELECT am.expression_id, am.designation
                                FROM achats_materiaux am
                                WHERE am.is_partial = 1 AND am.status != 'reçu'
                                GROUP BY am.expression_id, am.designation
                            ) as cp";
    $commandesPartiellesStmt = $pdo->prepare($commandesPartiellesQuery);
    $commandesPartiellesStmt->execute();
    $commandesPartielles = $commandesPartiellesStmt->fetchColumn();

    // Commandes en retard (plus de 7 jours)
    $commandesRetardeesQuery = "SELECT COUNT(*) as count FROM (
                               SELECT am.expression_id, am.designation
                               FROM achats_materiaux am 
                               WHERE DATEDIFF(CURRENT_DATE, am.date_achat) > 7 
                               AND am.status != 'reçu'
                               GROUP BY am.expression_id, am.designation
                           ) as cr";
    $commandesRetardeesStmt = $pdo->prepare($commandesRetardeesQuery);
    $commandesRetardeesStmt->execute();
    $commandesRetardees = $commandesRetardeesStmt->fetchColumn();

    // Nombre de fournisseurs distincts
    $fournisseursQuery = "SELECT COUNT(DISTINCT fournisseur) as count
                         FROM achats_materiaux
                         WHERE status != 'reçu' AND fournisseur IS NOT NULL AND fournisseur != ''";
    $fournisseursStmt = $pdo->prepare($fournisseursQuery);
    $fournisseursStmt->execute();
    $nbFournisseurs = $fournisseursStmt->fetchColumn();

    // Assembler les statistiques
    $stats = [
        'total_commandes' => $totalCommandesCount,
        'commandes_partielles' => $commandesPartielles,
        'commandes_retardees' => $commandesRetardees,
        'nb_fournisseurs' => $nbFournisseurs
    ];

    // Récupérer les fournisseurs les plus actifs
    $topFournisseursQuery = "SELECT 
                            fournisseur, 
                            COUNT(*) as count
                        FROM achats_materiaux
                        WHERE fournisseur IS NOT NULL AND fournisseur != ''
                        AND status != 'reçu'
                        GROUP BY fournisseur
                        ORDER BY count DESC
                        LIMIT 5";

    $topFournisseursStmt = $pdo->prepare($topFournisseursQuery);
    $topFournisseursStmt->execute();
    $topFournisseurs = $topFournisseursStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des données : " . $e->getMessage();
    $orderedMaterials = [];
    $stats = [
        'total_commandes' => 0,
        'commandes_partielles' => 0,
        'commandes_retardees' => 0,
        'nb_fournisseurs' => 0
    ];
    $topFournisseurs = [];
}

/**
 * Formate les nombres avec séparateurs de milliers
 * @param float $number Le nombre à formater
 * @return string Le nombre formaté
 */
function formatNumber($number) {
    return number_format((float) $number, 0, ',', ' ');
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes en cours - DYM STOCK</title>

    <!-- Styles CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

    <!-- Scripts JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            overflow: auto !important;
        }

        /* DataTables custom styles */
        table.dataTable {
            width: 100% !important;
            margin-bottom: 1rem;
            clear: both;
            border-collapse: separate;
            border-spacing: 0;
        }

        table.dataTable thead th,
        table.dataTable thead td {
            padding: 12px 18px;
            border-bottom: 2px solid #e2e8f0;
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }

        table.dataTable tbody td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Style pour les badges de statut */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .status-pending {
            background-color: #fff0e1;
            color: #ff8c00;
        }

        .status-completed {
            background-color: #e6f6f0;
            color: #38a169;
        }

        .status-partial {
            background-color: #fde68a;
            color: #92400e;
        }

        .status-validation {
            background-color: #dbeafe;
            color: #1e40af;
        }

        /* Style pour les lignes du tableau */
        .material-row {
            transition: background-color 0.2s;
        }

        .material-row:hover {
            background-color: #f7fafc;
        }

        .material-row.completed {
            background-color: #f0fff4;
        }

        .partial-order {
            position: relative;
            border-left: 4px solid #f59e0b;
        }

        .partial-order:hover {
            background-color: #fef3c7;
        }

        /* Style pour les cartes statistiques */
        .stats-card {
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Notification bubble pour les commandes en retard */
        .delay-bubble {
            position: relative;
        }

        .delay-bubble::after {
            content: '!';
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        .delay-pulse {
            animation: pulse 2s infinite;
        }

        /* Style pour les dates en retard */
        .date-late {
            color: #dc2626;
            font-weight: 600;
        }

        .date-recent {
            color: #059669;
            font-weight: 500;
        }

        .date-normal {
            color: #374151;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex flex-col min-h-screen">
        <?php include_once '../../../sidebar.php'; ?>

        <div id="main-content" class="flex-1 flex flex-col overflow-hidden">
            <?php include_once '../../../header.php'; ?>

            <main class="flex-1 p-6 overflow-y-auto">
                <!-- Message flash pour les erreurs -->
                <?php if (!empty($message)): ?>
                    <div id="flash-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        <span class="block sm:inline"><?php echo $message; ?></span>
                        <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="document.getElementById('flash-message').style.display='none';">
                            <span class="material-icons">close</span>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- En-tête de la page -->
                <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex flex-wrap justify-between items-center">
                    <div class="flex items-center m-2">
                        <h1 class="text-2xl font-bold text-gray-800">Commandes en cours</h1>
                    </div>

                    <div class="flex items-center m-2 bg-blue-50 p-2 rounded-lg border border-blue-100">
                        <span class="material-icons text-blue-500 mr-2">info</span>
                        <span class="text-sm text-blue-800">
                            Cette page vous permet de suivre les commandes passées par le service achat
                        </span>
                    </div>
                </div>

                <!-- Cartes de statistiques (conservées identiques) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Total des commandes -->
                    <div class="bg-white rounded-lg shadow-sm p-6 stats-card" style="border-top: 4px solid #3b82f6;">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="rounded-full bg-blue-100 p-3">
                                    <span class="material-icons text-blue-600">inventory</span>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-700">Total des commandes</h3>
                                    <p class="text-2xl font-bold text-gray-900">
                                        <?php echo $stats['total_commandes']; ?>
                                    </p>
                                </div>
                            </div>

                            <?php if ($stats['total_commandes'] > 10): ?>
                                <span class="bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full px-2.5 py-1 flex items-center">
                                    <span class="material-icons text-sm mr-1">priority_high</span>
                                    Élevé
                                </span>
                            <?php elseif ($stats['total_commandes'] > 0): ?>
                                <span class="bg-green-100 text-green-800 text-xs font-medium rounded-full px-2.5 py-1 flex items-center">
                                    <span class="material-icons text-sm mr-1">check</span>
                                    Normal
                                </span>
                            <?php else: ?>
                                <span class="bg-gray-100 text-gray-800 text-xs font-medium rounded-full px-2.5 py-1 flex items-center">
                                    <span class="material-icons text-sm mr-1">done_all</span>
                                    Aucun
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500">Commandes actives</span>
                                <span class="material-icons text-blue-500">shopping_cart</span>
                            </div>
                        </div>
                    </div>

                    <!-- Commandes partielles -->
                    <div class="bg-white rounded-lg shadow-sm p-6 stats-card" style="border-top: 4px solid #f59e0b;">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="rounded-full bg-amber-100 p-3">
                                    <span class="material-icons text-amber-500">content_paste</span>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-700">Commandes partielles</h3>
                                    <p class="text-2xl font-bold text-gray-900">
                                        <?php echo $stats['commandes_partielles']; ?>
                                    </p>
                                </div>
                            </div>

                            <span class="bg-amber-100 text-amber-800 text-xs font-medium rounded-full px-2.5 py-1 flex items-center">
                                <span class="material-icons text-sm mr-1">content_paste</span>
                                Partiel
                            </span>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500">En plusieurs livraisons</span>
                                <span class="material-icons text-amber-500">content_paste</span>
                            </div>
                        </div>
                    </div>

                    <!-- Commandes en retard -->
                    <div class="bg-white rounded-lg shadow-sm p-6 stats-card" style="border-top: 4px solid #ef4444;">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="rounded-full bg-red-100 p-3">
                                    <span class="material-icons text-red-500">schedule</span>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-700">Commandes en retard</h3>
                                    <p class="text-2xl font-bold text-gray-900">
                                        <?php echo $stats['commandes_retardees']; ?>
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-full bg-red-100 p-3 <?php echo $stats['commandes_retardees'] > 0 ? 'delay-bubble delay-pulse' : ''; ?>">
                                <span class="material-icons text-red-500">warning</span>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500">Plus de 7 jours</span>
                                <span class="material-icons text-red-500">warning</span>
                            </div>
                        </div>
                    </div>

                    <!-- Fournisseurs -->
                    <div class="bg-white rounded-lg shadow-sm p-6 stats-card" style="border-top: 4px solid #8b5cf6;">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="rounded-full bg-purple-100 p-3">
                                    <span class="material-icons text-purple-500">business</span>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-700">Fournisseurs</h3>
                                    <p class="text-2xl font-bold text-gray-900">
                                        <?php echo $stats['nb_fournisseurs']; ?>
                                    </p>
                                </div>
                            </div>

                            <span class="bg-purple-100 text-purple-800 text-xs font-medium rounded-full px-2.5 py-1 flex items-center">
                                <span class="material-icons text-sm mr-1">business</span>
                                Actif
                            </span>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500">Partenaires actifs</span>
                                <span class="material-icons text-purple-500">business</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section principale avec le tableau des commandes -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Matériaux commandés en attente de réception</h2>
                        <div class="flex space-x-2">
                            <button id="refresh-list" class="flex items-center bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-sm transition">
                                <span class="material-icons text-sm mr-1">refresh</span>
                                Actualiser
                            </button>
                            <button id="export-excel" class="flex items-center bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded text-sm transition">
                                <span class="material-icons text-sm mr-1">file_download</span>
                                Exporter Excel
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="orderedMaterialsTable" class="display responsive nowrap w-full">
                            <thead>
                                <tr>
                                    <th>Projet</th>
                                    <th>Client</th>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Unité</th>
                                    <th>Statut</th>
                                    <th>Prix Unit.</th>
                                    <th>Fournisseur</th>
                                    <th>Date commande</th>
                                    <th>Qté Restante</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    foreach ($orderedMaterials as $material) {
                                        // Déterminer la classe CSS et le badge de statut
                                        $rowClass = 'material-row';
                                        $statusBadge = '';

                                        // Vérifier s'il s'agit d'une commande partielle
                                        $isPartial = (int) $material['is_partial'] === 1 || $material['status'] === 'en_cours';
                                        $isPartialCompleted = $isPartial && (float) $material['qt_restante'] <= 0;
                                        $isValidationInProgress = $material['status'] === 'valide_en_cours';

                                        // Calculer la quantité restante
                                        $quantityRemaining = floatval($material['quantity_remaining'] ?? 0);

                                        if ($isPartial && !$isPartialCompleted) {
                                            $rowClass .= ' partial-order bg-yellow-50';
                                            $statusBadge = '<span class="status-badge status-partial">Partielle</span>';

                                            $initialQty = floatval($material['original_quantity'] ?? $material['quantity']);
                                            $orderedQty = $initialQty - floatval($material['qt_restante']);
                                            $progress = $initialQty > 0 ? round(($orderedQty / $initialQty) * 100) : 0;

                                            $quantityInfo = '
                                            <div>
                                                <span class="text-sm text-gray-900">' . number_format($orderedQty, 2, ',', ' ') . ' / ' .
                                                number_format($initialQty, 2, ',', ' ') . ' ' .
                                                htmlspecialchars($material['unit'] ?? '') . '</span>
                                                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                                    <div class="bg-yellow-500 h-1.5 rounded-full" style="width: ' . $progress . '%"></div>
                                                </div>
                                                <span class="text-xs text-yellow-600">Reste: ' . number_format(floatval($material['qt_restante']), 2, ',', ' ') . '</span>
                                            </div>';
                                        } elseif ($isValidationInProgress) {
                                            $rowClass .= ' bg-blue-50';
                                            $statusBadge = '<span class="status-badge status-validation">En validation</span>';

                                            $displayQuantity = floatval($material['quantity']);
                                            $quantityInfo = '<span class="text-sm text-gray-900">' .
                                                number_format($displayQuantity, 2, ',', ' ') . ' ' .
                                                htmlspecialchars($material['unit'] ?? '') . '</span>';
                                        } else {
                                            $rowClass .= ' completed';
                                            $statusBadge = '<span class="status-badge status-completed">Commandé</span>';

                                            $displayQuantity = floatval($material['quantity']);
                                            $quantityInfo = '<span class="text-sm text-gray-900">' .
                                                number_format($displayQuantity, 2, ',', ' ') . ' ' .
                                                htmlspecialchars($material['unit'] ?? '') . '</span>';
                                        }

                                        // Gestion correcte des dates avec formatage français
                                        $dateCommande = '';
                                        $dateClass = 'date-normal';
                                        $isLate = false;
                                        $joursDepuis = 0;

                                        if (!empty($material['date_achat'])) {
                                            // Utiliser la date formatée directement depuis SQL
                                            $dateCommande = $material['date_achat_formatted'] ?? formatDateFrench($material['date_achat']);
                                            $joursDepuis = calculateDaysSince($material['date_achat']);

                                            if ($joursDepuis > 7) {
                                                $isLate = true;
                                                $rowClass .= ' bg-red-50';
                                                $dateClass = 'date-late';
                                            } elseif ($joursDepuis <= 3) {
                                                $dateClass = 'date-recent';
                                            }
                                        } else {
                                            $dateCommande = 'N/A';
                                        }

                                        // Indicateur de source
                                        $sourceIndicator = '';
                                        if ($material['source_table'] === 'besoins') {
                                            $sourceIndicator = '<span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Système</span>';
                                        }

                                        // Style pour la quantité restante
                                        $remainingClass = '';
                                        $remainingIcon = '';
                                        if ($quantityRemaining > 0) {
                                            $remainingClass = 'text-amber-600 font-medium';
                                            $remainingIcon = '<span class="material-icons text-sm mr-1">schedule</span>';
                                        } else {
                                            $remainingClass = 'text-green-600 font-medium';
                                            $remainingIcon = '<span class="material-icons text-sm mr-1">check_circle</span>';
                                        }

                                        // Afficher la ligne du tableau
                                        echo '
                                        <tr class="' . $rowClass . '">
                                            <td>' . htmlspecialchars($material['code_projet'] ?? 'N/A') . '</td>
                                            <td>' . htmlspecialchars($material['nom_client'] ?? 'N/A') . '</td>
                                            <td>' . htmlspecialchars($material['designation'] ?? 'N/A') . $sourceIndicator . '</td>
                                            <td>' . $quantityInfo . '</td>
                                            <td>' . htmlspecialchars($material['unit'] ?? 'N/A') . '</td>
                                            <td>' . $statusBadge . ($isLate ? ' <span class="inline-block ml-1 px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Retard (' . $joursDepuis . 'j)</span>' : '') . '</td>
                                            <td>' . (isset($material['prix_unitaire']) && $material['prix_unitaire'] > 0 ? '-- FCFA' : '--') . '</td>
                                            <td>' . htmlspecialchars($material['fournisseur'] ?? 'N/A') . '</td>
                                            <td class="' . $dateClass . '">' . $dateCommande . '</td>
                                            <td class="' . $remainingClass . '">
                                                <div class="flex items-center">
                                                    ' . $remainingIcon . '
                                                    <span>' . number_format($quantityRemaining, 2, ',', ' ') . '</span>
                                                    ' . ($quantityRemaining > 0 ? '<span class="text-xs text-gray-500 ml-1">' . htmlspecialchars($material['unit'] ?? '') . '</span>' : '') . '
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button onclick="showOrderDetails(\'' . addslashes(htmlspecialchars($material['designation'] ?? '')) . '\', \'' . $material['expression_id'] . '\')" 
                                                    class="text-blue-600 hover:text-blue-800 mr-2" title="Voir détails">
                                                    <span class="material-icons">visibility</span>
                                                </button>
                                                <button onclick="checkInStockAvailability(\'' . addslashes(htmlspecialchars($material['designation'] ?? '')) . '\')" 
                                                    class="text-purple-600 hover:text-purple-800 mr-2" title="Vérifier disponibilité en stock">
                                                    <span class="material-icons">inventory_2</span>
                                                </button>
                                                ' . ($isUserSuperAdmin ? '
                                                <button onclick="markAsReceived(\'' . htmlspecialchars($material['id']) . '\', \'' .
                                                    htmlspecialchars($material['expression_id']) . '\', \'' .
                                                    addslashes(htmlspecialchars($material['designation'] ?? '')) . '\', \'' .
                                                    htmlspecialchars($material['source_table'] ?? 'expression_dym') . '\')" 
                                                    class="text-green-600 hover:text-green-800" title="Marquer comme déjà reçu">
                                                    <span class="material-icons">check_circle</span>
                                                </button>' : '') . '
                                            </td>
                                        </tr>';
                                    }
                                } catch (Exception $e) {
                                    echo "<tr><td colspan='11' class='text-center text-red-500 py-4'>Erreur lors de l'affichage des données: " . $e->getMessage() . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section des fournisseurs les plus actifs (conservée identique) -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Fournisseurs les plus actifs</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <?php foreach ($topFournisseurs as $index => $fournisseur):
                            $colors = [
                                'bg-blue-100 text-blue-600',
                                'bg-green-100 text-green-600',
                                'bg-purple-100 text-purple-600',
                                'bg-amber-100 text-amber-600',
                                'bg-pink-100 text-pink-600'
                            ];
                            $color = $colors[$index % count($colors)];
                        ?>
                            <div class="p-4 rounded-lg <?php echo $color; ?> shadow-sm">
                                <div class="flex justify-between items-center">
                                    <div class="font-medium"><?php echo htmlspecialchars($fournisseur['fournisseur']); ?></div>
                                    <div class="text-sm"><?php echo $fournisseur['count']; ?> cmd.</div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($topFournisseurs)): ?>
                            <div class="col-span-5 text-center py-4 text-gray-500">
                                Aucun fournisseur actif pour le moment
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Scripts jQuery et DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    
    <!-- INCLURE LE PLUGIN POUR LES DATES FRANÇAISES -->
    <script src="assets/js/datatable-date-fr.js"></script>

    <script>
        $(document).ready(function() {
            // Initialisation du DataTable avec support des dates françaises
            const orderedTable = $('#orderedMaterialsTable').DataTable({
                responsive: true,
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                },
                dom: 'Blfrtip',
                buttons: ['excel', 'print'],
                columnDefs: [
                    {
                        // Configuration pour la colonne Date commande (index 8)
                        type: 'date-fr',
                        targets: 8
                    },
                    {
                        // Colonne Quantité restante - tri numérique
                        type: 'num',
                        targets: 9
                    },
                    {
                        // Colonne Actions non triable
                        orderable: false,
                        targets: [10]
                    }
                ],
                order: [[8, 'desc']], // Tri par date décroissant (plus récentes en premier)
                pageLength: 15,
                
                // Configuration pour améliorer l'affichage des dates
                drawCallback: function(settings) {
                    // Ajouter des tooltips pour les dates en retard
                    $('.date-late').each(function() {
                        var dateText = $(this).text();
                        if (dateText !== 'N/A') {
                            $(this).attr('title', 'Commande en retard depuis le ' + dateText);
                        }
                    });
                    
                    // Ajouter des tooltips pour les dates récentes
                    $('.date-recent').each(function() {
                        var dateText = $(this).text();
                        if (dateText !== 'N/A') {
                            $(this).attr('title', 'Commande récente du ' + dateText);
                        }
                    });
                }
            });

            // Actualiser la page
            $('#refresh-list').click(function() {
                location.reload();
            });

            // Exporter en Excel
            $('#export-excel').click(function() {
                orderedTable.button('.buttons-excel').trigger();
            });
        });

        // Fonction pour afficher les détails d'une commande (conservée identique)
        function showOrderDetails(designation, expressionId) {
            Swal.fire({
                title: 'Chargement...',
                text: 'Récupération des détails de la commande',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`api/get_order_details.php?expression_id=${expressionId}&designation=${encodeURIComponent(designation)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const orderInfo = data.order_info;
                        const orderHistory = data.order_history || [];
                        const isSystem = data.is_system || false;

                        // Construire le contenu HTML pour la modal
                        let content = `
                        <div class="text-left">
                            <div class="mb-4 grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Produit:</p>
                                    <p class="font-medium">${designation}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Projet:</p>
                                    <p class="font-medium">${orderInfo.code_projet} - ${orderInfo.nom_client}</p>
                                    ${isSystem ? '<span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">Système</span>' : ''}
                                </div>
                            </div>
                            
                            <div class="mb-4 grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Quantité:</p>
                                    <p class="font-medium">${parseFloat(orderInfo.quantity).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${orderInfo.unit}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Fournisseur:</p>
                                    <p class="font-medium">${orderInfo.fournisseur || 'Non spécifié'}</p>
                                </div>
                            </div>
                            
                            <div class="mb-4 grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Prix unitaire:</p>
                                    <p class="font-medium">${orderInfo.prix_unitaire ? '-- FCFA' : 'Non spécifié'}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Date de commande:</p>
                                    <p class="font-medium">${orderInfo.date_achat ? formatDateFr(orderInfo.date_achat) : 'N/A'}</p>
                                </div>
                            </div>`;

                        // Ajouter l'historique des commandes si disponible
                        if (orderHistory.length > 0) {
                            content += `
                            <div class="mt-6">
                                <h3 class="font-medium mb-3">Historique des commandes</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white border">
                                        <thead>
                                            <tr>
                                                <th class="border px-4 py-2 bg-gray-50">Date</th>
                                                <th class="border px-4 py-2 bg-gray-50">Quantité</th>
                                                <th class="border px-4 py-2 bg-gray-50">Prix unitaire</th>
                                                <th class="border px-4 py-2 bg-gray-50">Fournisseur</th>
                                                <th class="border px-4 py-2 bg-gray-50">Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;

                            orderHistory.forEach(item => {
                                const status = item.status === 'reçu' ?
                                    '<span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Reçu</span>' :
                                    '<span class="block px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">En cours</span>';

                                const formattedDate = formatDateFr(item.date_achat);

                                content += `
                                <tr>
                                    <td class="border px-4 py-2">${formattedDate}</td>
                                    <td class="border px-4 py-2">${parseFloat(item.quantity).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${item.unit || ''}</td>
                                    <td class="border px-4 py-2">-- FCFA</td>
                                    <td class="border px-4 py-2">${item.fournisseur || '-'}</td>
                                    <td class="border px-4 py-2">${status}</td>
                                </tr>`;
                            });

                            content += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>`;
                        }

                        // Ajouter un commentaire si la commande est en retard
                        if (orderInfo.date_achat) {
                            const joursDepuis = calculateDaysSince(orderInfo.date_achat);

                            if (joursDepuis > 7) {
                                content += `
                                <div class="mt-4 p-3 bg-red-50 rounded-lg border border-red-200">
                                    <p class="text-red-800 flex items-center">
                                        <span class="material-icons mr-2">warning</span>
                                        Cette commande a <strong>${joursDepuis} jours</strong> de retard. Vous devriez contacter le fournisseur.
                                    </p>
                                </div>`;
                            }
                        }

                        content += `</div>`;

                        // Afficher la modal avec les détails
                        Swal.fire({
                            title: 'Détails de la commande',
                            html: content,
                            width: 800,
                            confirmButtonText: 'Fermer',
                            showClass: {
                                popup: 'animate__animated animate__fadeIn'
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message || 'Impossible de récupérer les détails de la commande',
                            icon: 'error'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la récupération des détails:', error);
                    Swal.fire({
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la récupération des détails',
                        icon: 'error'
                    });
                });
        }

        // Fonction pour vérifier la disponibilité d'un produit en stock (conservée identique)
        function checkInStockAvailability(designation) {
            Swal.fire({
                title: 'Chargement...',
                text: 'Vérification de la disponibilité en stock',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`api/check_product_availability.php?designation=${encodeURIComponent(designation)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.stock_info) {
                            const stockInfo = data.stock_info;

                            let content = `
                                <div class="text-left">
                                    <div class="mb-4">
                                        <p class="text-sm text-gray-600">Produit:</p>
                                        <p class="font-medium">${designation}</p>
                                    </div>
                                    
                                    <div class="mb-4 grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-600">Quantité en stock:</p>
                                            <p class="font-medium text-2xl">${parseFloat(stockInfo.quantity).toLocaleString('fr-FR')} ${stockInfo.unit}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">Quantité réservée:</p>
                                            <p class="font-medium">${parseFloat(stockInfo.quantity_reserved || 0).toLocaleString('fr-FR')} ${stockInfo.unit}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <p class="text-sm text-gray-600">Quantité disponible:</p>
                                        <p class="font-medium text-xl text-${(stockInfo.quantity - (stockInfo.quantity_reserved || 0)) > 0 ? 'green' : 'red'}-600">
                                            ${parseFloat(stockInfo.quantity - (stockInfo.quantity_reserved || 0)).toLocaleString('fr-FR')} ${stockInfo.unit}
                                        </p>
                                    </div>`;

                            if ((stockInfo.quantity - (stockInfo.quantity_reserved || 0)) > 0) {
                                content += `
                                    <div class="mt-4 p-3 bg-green-50 rounded-lg border border-green-200">
                                        <p class="text-green-800 flex items-center">
                                            <span class="material-icons mr-2">check_circle</span>
                                            Ce produit est disponible en stock et pourrait être utilisé pour répondre à des besoins urgents.
                                        </p>
                                    </div>`;
                            } else {
                                content += `
                                    <div class="mt-4 p-3 bg-amber-50 rounded-lg border border-amber-200">
                                        <p class="text-amber-800 flex items-center">
                                            <span class="material-icons mr-2">info</span>
                                            Ce produit n'est pas disponible en stock ou toutes les unités sont réservées.
                                        </p>
                                    </div>`;
                            }

                            content += `
                                <div class="mt-4 text-right">
                                    <a href="../../../inventory.php?search=${encodeURIComponent(designation)}" class="text-blue-600 hover:text-blue-800 flex items-center justify-end">
                                        <span class="material-icons text-sm mr-1">search</span>
                                        Voir dans l'inventaire
                                    </a>
                                </div>
                            </div>`;

                            Swal.fire({
                                title: 'Disponibilité en stock',
                                html: content,
                                width: 600,
                                confirmButtonText: 'Fermer',
                                showClass: {
                                    popup: 'animate__animated animate__fadeIn'
                                }
                            });
                        } else {
                            Swal.fire({
                                title: 'Produit non trouvé',
                                html: `
                                    <div class="p-3 bg-amber-50 rounded-lg">
                                        <p class="text-amber-800 flex items-center">
                                            <span class="material-icons mr-2">search_off</span>
                                            Le produit "${designation}" n'existe pas dans votre base de données de stock.
                                        </p>
                                    </div>
                                `,
                                icon: 'warning'
                            });
                        }
                    } else {
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message || 'Impossible de vérifier la disponibilité du produit',
                            icon: 'error'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la vérification du stock:', error);
                    Swal.fire({
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la vérification du stock',
                        icon: 'error'
                    });
                });
        }

        // Fonction pour marquer une commande comme déjà reçue (conservée identique)
        function markAsReceived(id, expressionId, designation, sourceTable = 'expression_dym') {
            Swal.fire({
                title: 'Marquer comme déjà reçu ?',
                html: `
                <div class="text-left">
                    <p class="mb-4">Vous êtes sur le point de marquer ce produit comme <strong>déjà reçu</strong> dans le système.</p>
                    <p><strong>Produit :</strong> ${designation}</p>
                    <p><strong>ID Expression :</strong> ${expressionId}</p>
                    <div class="mt-4 p-4 bg-yellow-50 border border-yellow-300 rounded-md">
                        <p class="text-yellow-800 text-sm">
                            <span class="material-icons align-middle text-sm mr-1">warning</span>
                            Cette action est réservée aux super-administrateurs pour résoudre les décalages entre le stock réel et les commandes.
                        </p>
                    </div>
                </div>
            `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, marquer comme reçu',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#10B981',
                cancelButtonColor: '#6B7280',
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Traitement en cours...',
                        text: 'Veuillez patienter pendant la mise à jour du statut',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('api/mark_as_received.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                id: id,
                                expression_id: expressionId,
                                designation: designation,
                                source_table: sourceTable
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Succès !',
                                    text: data.message || 'La commande a été marquée comme reçue avec succès',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Erreur',
                                    text: data.message || 'Une erreur est survenue lors de la mise à jour',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            Swal.fire({
                                title: 'Erreur',
                                text: 'Une erreur de communication est survenue',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });
        }

        /**
         * Fonction utilitaire pour formater une date en français (côté client)
         * Compatible avec la fonction PHP formatDateFrench
         */
        function formatDateFr(date) {
            if (!date || date === 'N/A') return 'N/A';

            try {
                const dateObj = new Date(date);
                if (isNaN(dateObj.getTime())) return 'N/A';

                const day = String(dateObj.getDate()).padStart(2, '0');
                const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                const year = dateObj.getFullYear();

                return `${day}/${month}/${year}`;
            } catch (e) {
                return 'N/A';
            }
        }

        /**
         * Fonction utilitaire pour calculer les jours depuis une date (côté client)
         * Compatible avec la fonction PHP calculateDaysSince
         */
        function calculateDaysSince(date) {
            if (!date || date === 'N/A') return 0;

            try {
                const dateObj = new Date(date);
                const today = new Date();
                const diffTime = Math.abs(today - dateObj);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                return diffDays;
            } catch (e) {
                return 0;
            }
        }
    </script>
</body>

</html>