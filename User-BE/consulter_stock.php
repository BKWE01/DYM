<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Connexion à la base de données
include_once '../database/connection.php';

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Stock | Bureau d'Études</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/select/1.3.4/css/select.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Votre CSS personnalisé précédent reste inchangé -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #334155;
        }

        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .top-bar {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
        }

        .stock-btn {
            background-color: #ebf7ee;
            border: none;
            color: #10b981;
            padding: 10px 20px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .stock-btn:hover {
            background-color: #d1fae5;
            color: #059669;
            transform: translateY(-1px);
        }

        .stock-btn.active {
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .date-time {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #64748b;
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 500;
        }

        .date-time .material-icons {
            margin-right: 10px;
            font-size: 20px;
            color: #475569;
        }

        .card-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .section-header {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            color: #fff;
            white-space: nowrap;
            border-radius: 9999px;
        }

        .badge-green {
            background-color: #10b981;
        }

        .badge-yellow {
            background-color: #f59e0b;
        }

        .badge-red {
            background-color: #ef4444;
        }

        .badge-blue {
            background-color: #3b82f6;
        }

        .badge-entry {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .badge-output {
            background-color: #fce4ec;
            color: #e91e63;
        }

        .filter-section {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }

        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
        }

        .filter-input {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 14px;
            color: #334155;
            background-color: white;
            transition: all 0.2s;
        }

        .filter-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
        }

        .btn-filter {
            display: flex;
            align-items: center;
            gap: 6px;
            background-color: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-filter:hover {
            background-color: #2563eb;
        }

        .btn-reset {
            background-color: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-reset:hover {
            background-color: #e2e8f0;
            color: #334155;
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
            padding: 10px 15px;
            border-bottom: 2px solid #e2e8f0;
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.875rem;
        }

        table.dataTable tbody td {
            padding: 8px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.875rem;
        }

        table.dataTable tbody tr.odd {
            background-color: #f9fafb;
        }

        table.dataTable tbody tr.even {
            background-color: #ffffff;
        }

        table.dataTable tbody tr:hover {
            background-color: #f1f5f9;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate {
            color: #4a5568;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.375rem 0.75rem;
            margin-left: 0.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            background-color: #ffffff;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #3b82f6;
            color: white !important;
            border: 1px solid #3b82f6;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #2563eb;
            color: white !important;
            border: 1px solid #2563eb;
        }

        /* Style pour le modal de prévisualisation de facture */
        .invoice-modal {
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
        }

        .invoice-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 5px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-item {
                width: 100%;
            }
        }

        .category-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            background-color: #e2e8f0;
            color: #475569;
        }

        .stock-status {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .status-high {
            background-color: #10b981;
        }

        .status-medium {
            background-color: #f59e0b;
        }

        .status-low {
            background-color: #ef4444;
        }

        /* Nouveaux styles pour les améliorations */
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(.25, .8, .25, 1);
        }

        .stat-card:hover {
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.25), 0 10px 10px rgba(0, 0, 0, 0.22);
        }

        .stat-header {
            padding: 16px;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-title {
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }

        .stat-body {
            padding: 20px;
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .stat-description {
            font-size: 12px;
            color: #64748b;
        }

        .stat-footer {
            padding: 8px 16px;
            background-color: #f1f5f9;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }

        /* Onglets pour différentes vues */
        .tab-container {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }

        .tab-button {
            padding: 12px 16px;
            font-weight: 600;
            font-size: 14px;
            color: #64748b;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .tab-button.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
            background-color: white;
        }

        .tab-button:hover:not(.active) {
            color: #1e293b;
            background-color: #f1f5f9;
        }

        .tab-content {
            display: none;
            padding: 20px;
        }

        .tab-content.active {
            display: block;
        }

        /* Modal pour les détails de produit */
        .product-modal {
            display: none;
            position: fixed;
            z-index: 1060;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .product-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            animation: modalFadeIn 0.3s;
        }

        .product-modal-header {
            padding: 16px 20px;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .product-modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }

        .product-modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .product-modal-footer {
            padding: 16px 20px;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: right;
        }

        .close-modal {
            color: #64748b;
            font-size: 24px;
            font-weight: 700;
            cursor: pointer;
        }

        .close-modal:hover {
            color: #ef4444;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Style pour les cartes dans le tableau de bord */
        .dashboard-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.19), 0 6px 6px rgba(0, 0, 0, 0.23);
        }

        /* Nouveau design pour les boutons d'action */
        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .action-button.view {
            background-color: #dbeafe;
            color: #2563eb;
        }

        .action-button.view:hover {
            background-color: #bfdbfe;
        }

        .action-button.history {
            background-color: #e0f2fe;
            color: #0284c7;
        }

        .action-button.history:hover {
            background-color: #bae6fd;
        }

        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 120px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .tooltip .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Flèches pour les tendances */
        .trend-up {
            color: #10b981;
        }

        .trend-down {
            color: #ef4444;
        }

        /* Timeline pour l'historique des produits */
        .timeline {
            position: relative;
            margin: 20px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            left: 16px;
            height: 100%;
            width: 4px;
            background: #e2e8f0;
            border-radius: 4px;
        }

        .timeline-item {
            position: relative;
            padding-left: 45px;
            padding-bottom: 20px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: 12px;
            top: 4px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .timeline-content {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .timeline-date {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .timeline-title {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .timeline-desc {
            font-size: 13px;
            color: #475569;
        }

        /* Badge pour la date de mise à jour */
        .update-badge {
            display: inline-block;
            padding: 2px 8px;
            background-color: #e0f2fe;
            color: #0284c7;
            border-radius: 9999px;
            font-size: 10px;
            font-weight: 600;
        }

        /* Animation de pulsation pour atirer l'attention */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }
        
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../components/navbar.php'; ?>

        <main class="flex-1 p-6">
            <!-- Top Bar -->
            <div class="top-bar p-4 mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex gap-3">
                    <a href="#" id="btn-consulter-stock" class="stock-btn active">
                        <span class="material-icons" style="font-size: 18px;">inventory</span>
                        Consulter le Stock
                    </a>

                    <a href="#" id="btn-entrees-sorties" class="stock-btn"
                        style="background-color: #f0e6fd; color: #7c3aed;">
                        <span class="material-icons" style="font-size: 18px;">swap_horiz</span>
                        Entrées/Sorties
                    </a>

                    <a href="#" id="btn-tableau-bord" class="stock-btn"
                        style="background-color: #e0f2fe; color: #0284c7;">
                        <span class="material-icons" style="font-size: 18px;">dashboard</span>
                        Tableau de Bord
                    </a>
                </div>

                <div class="date-time">
                    <span class="material-icons">calendar_today</span>
                    <span id="date-time-display"></span>
                </div>
            </div>

            <!-- Conteneur pour le contenu dynamique -->
            <div id="content-container">
                <!-- Contenu Consulter Stock -->
                <div id="stock-content">
                    <!-- Header Section -->
                    <div class="mb-6 flex justify-between items-center">
                        <div class="flex items-center">
                            <h2 class="text-2xl font-bold text-gray-800">Inventaire des Produits</h2>
                            <span class="ml-2 badge badge-green">Stock</span>
                        </div>

                        <!-- Nouveau : Boutons d'action/export -->
                        <div class="flex space-x-2">
                            <button id="btn-refresh-stock"
                                class="bg-blue-500 text-white px-3 py-2 rounded-md flex items-center hover:bg-blue-600 transition-colors">
                                <span class="material-icons mr-1" style="font-size: 18px;">refresh</span>
                                Actualiser
                            </button>
                            <button id="btn-export-pdf"
                                class="bg-red-500 text-white px-3 py-2 rounded-md flex items-center hover:bg-red-600 transition-colors">
                                <span class="material-icons mr-1" style="font-size: 18px;">picture_as_pdf</span>
                                PDF
                            </button>
                            <button id="btn-export-excel"
                                class="bg-green-500 text-white px-3 py-2 rounded-md flex items-center hover:bg-green-600 transition-colors">
                                <span class="material-icons mr-1" style="font-size: 18px;">file_download</span>
                                Excel
                            </button>
                        </div>
                    </div>

                    <!-- Nouveau : Section de statistiques de stock -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <!-- Total Produits -->
                        <div class="dashboard-card">
                            <div class="p-4 flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Total Produits</p>
                                    <h3 id="total-products" class="text-3xl font-bold text-gray-800">-</h3>
                                </div>
                                <div class="bg-blue-100 p-3 rounded-full">
                                    <span class="material-icons text-blue-500">category</span>
                                </div>
                            </div>
                            <div class="bg-blue-50 px-4 py-2 text-xs text-blue-600">
                                <span id="unique-categories">-</span> catégories de produits
                            </div>
                        </div>

                        <!-- Stock Normal -->
                        <div class="dashboard-card">
                            <div class="p-4 flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Stock Normal</p>
                                    <h3 id="normal-stock" class="text-3xl font-bold text-green-600">-</h3>
                                </div>
                                <div class="bg-green-100 p-3 rounded-full">
                                    <span class="material-icons text-green-500">check_circle</span>
                                </div>
                            </div>
                            <div class="bg-green-50 px-4 py-2 text-xs text-green-600">
                                <span id="normal-stock-percent">-</span>% du stock total
                            </div>
                        </div>

                        <!-- Stock Faible -->
                        <div class="dashboard-card">
                            <div class="p-4 flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Stock Faible</p>
                                    <h3 id="low-stock" class="text-3xl font-bold text-yellow-500">-</h3>
                                </div>
                                <div class="bg-yellow-100 p-3 rounded-full">
                                    <span class="material-icons text-yellow-500">warning</span>
                                </div>
                            </div>
                            <div class="bg-yellow-50 px-4 py-2 text-xs text-yellow-600">
                                <span id="low-stock-percent">-</span>% du stock total
                            </div>
                        </div>

                        <!-- Rupture de Stock -->
                        <div class="dashboard-card">
                            <div class="p-4 flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Rupture de Stock</p>
                                    <h3 id="out-of-stock" class="text-3xl font-bold text-red-600">-</h3>
                                </div>
                                <div class="bg-red-100 p-3 rounded-full">
                                    <span class="material-icons text-red-500">error</span>
                                </div>
                            </div>
                            <div class="bg-red-50 px-4 py-2 text-xs text-red-600">
                                Nécessite attention immédiate
                            </div>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="card-container">
                        <!-- Nouveau : navigation par onglets -->
                        <div class="tab-container flex">
                            <div class="tab-button active" data-tab="all-products">
                                <span class="material-icons align-middle mr-1" style="font-size: 18px;">view_list</span>
                                Tous les produits
                            </div>
                            <div class="tab-button" data-tab="low-stock">
                                <span class="material-icons align-middle mr-1 text-yellow-500"
                                    style="font-size: 18px;">warning</span>
                                Stock faible
                            </div>
                            <div class="tab-button" data-tab="out-of-stock">
                                <span class="material-icons align-middle mr-1 text-red-500"
                                    style="font-size: 18px;">error</span>
                                Rupture de stock
                            </div>
                        </div>

                        <!-- Onglet "Tous les produits" -->
                        <div id="all-products" class="tab-content active">
                            <!-- Filtres avancés -->
                            <div class="filter-section">
                                <div class="section-header mb-3">
                                    <h3 class="section-title flex items-center">
                                        <span class="material-icons mr-2" style="font-size: 20px;">filter_alt</span>
                                        Filtres Avancés
                                    </h3>
                                </div>

                                <div class="filter-group">
                                    <div class="filter-item">
                                        <label class="filter-label">Nom du produit</label>
                                        <input type="text" id="filter-product-name" class="filter-input"
                                            placeholder="Nom du produit">
                                    </div>

                                    <div class="filter-item">
                                        <label class="filter-label">Catégorie</label>
                                        <select id="filter-category" class="filter-input">
                                            <option value="">Toutes les catégories</option>
                                            <?php
                                            // Récupérer les catégories
                                            $sql = "SELECT id, libelle, code FROM categories ORDER BY libelle";
                                            $stmt = $pdo->prepare($sql);
                                            $stmt->execute();

                                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='{$row['id']}'>{$row['libelle']} ({$row['code']})</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="filter-item">
                                        <label class="filter-label">Unité</label>
                                        <select id="filter-unit" class="filter-input">
                                            <option value="">Toutes les unités</option>
                                            <?php
                                            // Récupérer les unités distinctes
                                            $sql = "SELECT DISTINCT unit FROM products WHERE unit IS NOT NULL AND unit != '' ORDER BY unit";
                                            $stmt = $pdo->prepare($sql);
                                            $stmt->execute();

                                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='{$row['unit']}'>{$row['unit']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="filter-item">
                                        <label class="filter-label">Niveau de stock</label>
                                        <select id="filter-stock-level" class="filter-input">
                                            <option value="">Tous les niveaux</option>
                                            <option value="high">Stock élevé (>10)</option>
                                            <option value="medium">Stock moyen (3-10)</option>
                                            <option value="low">Stock faible (<3)< /option>
                                            <option value="zero">Rupture de stock (0)</option>
                                        </select>
                                    </div>

                                    <div class="filter-item mt-auto">
                                        <button id="btn-apply-filters" class="btn-filter">
                                            <span class="material-icons" style="font-size: 18px;">search</span>
                                            Appliquer
                                        </button>
                                    </div>

                                    <div class="filter-item mt-auto">
                                        <button id="btn-reset-filters" class="btn-filter btn-reset">
                                            <span class="material-icons" style="font-size: 18px;">refresh</span>
                                            Réinitialiser
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4">
                                <table id="products-table" class="min-w-full">
                                    <thead>
                                        <tr>
                                            <th>Barcode</th>
                                            <th>Nom du Produit</th>
                                            <th>Catégorie</th>
                                            <th>Quantité</th>
                                            <th>Quantité Réservée</th>
                                            <th>Unité</th>
                                            <th>État du Stock</th>
                                            <th>Actions</th> <!-- Nouvelle colonne pour les actions -->
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Les données seront chargées via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Onglet "Stock faible" -->
                        <div id="low-stock" class="tab-content">
                            <div class="p-5">
                                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-5">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <span class="material-icons text-yellow-400">warning</span>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-yellow-700">
                                                Ces produits ont un niveau de stock faible (moins de 10 unités). Il est
                                                recommandé de planifier leur réapprovisionnement.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <table id="low-stock-table" class="min-w-full">
                                    <thead>
                                        <tr>
                                            <th>Barcode</th>
                                            <th>Nom du Produit</th>
                                            <th>Catégorie</th>
                                            <th>Quantité</th>
                                            <th>Unité</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Les données seront chargées via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Onglet "Rupture de stock" -->
                        <div id="out-of-stock" class="tab-content">
                            <div class="p-5">
                                <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-5">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <span class="material-icons text-red-400">error</span>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-red-700">
                                                Ces produits sont actuellement en rupture de stock (quantité = 0). Un
                                                réapprovisionnement urgent est recommandé.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <table id="out-of-stock-table" class="min-w-full">
                                    <thead>
                                        <tr>
                                            <th>Barcode</th>
                                            <th>Nom du Produit</th>
                                            <th>Catégorie</th>
                                            <th>Quantité</th>
                                            <th>Unité</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Les données seront chargées via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenu Entrées/Sorties (sera chargé dynamiquement) -->
                <div id="entrees-sorties-content" style="display: none;"></div>

                <!-- Nouveau : Contenu Tableau de Bord -->
                <div id="tableau-bord-content" style="display: none;">
                    <!-- En-tête du tableau de bord -->
                    <div class="mb-6 flex justify-between items-center">
                        <div class="flex items-center">
                            <h2 class="text-2xl font-bold text-gray-800">Tableau de Bord Stock</h2>
                            <span class="ml-2 badge badge-blue">Analyses</span>
                        </div>

                        <div class="flex space-x-2">
                            <button id="btn-print-dashboard"
                                class="bg-purple-500 text-white px-3 py-2 rounded-md flex items-center hover:bg-purple-600 transition-colors">
                                <span class="material-icons mr-1" style="font-size: 18px;">print</span>
                                Imprimer
                            </button>
                            <button id="btn-refresh-dashboard"
                                class="bg-blue-500 text-white px-3 py-2 rounded-md flex items-center hover:bg-blue-600 transition-colors">
                                <span class="material-icons mr-1" style="font-size: 18px;">refresh</span>
                                Actualiser
                            </button>
                        </div>
                    </div>

                    <!-- Analyse de stock par catégorie -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <!-- Graphique de répartition -->
                        <div class="card-container lg:col-span-">
                            <div class="section-header">
                                <h3 class="section-title flex items-center">
                                    <span class="material-icons mr-2" style="font-size: 20px;">pie_chart</span>
                                    Répartition du Stock par Catégorie
                                </h3>
                            </div>
                            <div class="p-5">
                                <canvas id="stockCategoryChart" height="300"></canvas>
                            </div>
                        </div>

                        <!-- Top produits -->
                        <div class="card-container">
                            <div class="section-header">
                                <h3 class="section-title flex items-center">
                                    <span class="material-icons mr-2" style="font-size: 20px;">star</span>
                                    Top Produits en Stock
                                </h3>
                            </div>
                            <div class="p-4">
                                <div id="top-products-list" class="space-y-4">
                                    <!-- Les données seront chargées dynamiquement -->
                                    <div class="animate-pulse">
                                        <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                                    </div>
                                    <div class="animate-pulse">
                                        <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                                    </div>
                                    <div class="animate-pulse">
                                        <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Analyse des mouvements récents -->
                        <div class="card-container ">
                            <div class="section-header">
                                <h3 class="section-title flex items-center">
                                    <span class="material-icons mr-2" style="font-size: 20px;">analytics</span>
                                    Mouvements de Stock Récents
                                </h3>
                            </div>
                            <div class="p-5">
                                <canvas id="stockMovementChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>


                    <!-- Alertes et recommandations -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Alertes de stock -->
                        <div class="card-container">
                            <div class="section-header bg-red-50">
                                <h3 class="section-title flex items-center text-red-700">
                                    <span class="material-icons mr-2 text-red-500"
                                        style="font-size: 20px;">notifications_active</span>
                                    Alertes de Stock
                                </h3>
                            </div>
                            <div class="p-4">
                                <div id="stock-alerts" class="space-y-3">
                                    <!-- Les alertes seront chargées dynamiquement -->
                                    <div class="animate-pulse">
                                        <div class="h-4 bg-red-100 rounded w-full mb-2"></div>
                                        <div class="h-3 bg-red-50 rounded w-3/4"></div>
                                    </div>
                                    <div class="animate-pulse">
                                        <div class="h-4 bg-red-100 rounded w-full mb-2"></div>
                                        <div class="h-3 bg-red-50 rounded w-3/4"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recommandations -->
                        <div class="card-container">
                            <div class="section-header bg-blue-50">
                                <h3 class="section-title flex items-center text-blue-700">
                                    <span class="material-icons mr-2 text-blue-500"
                                        style="font-size: 20px;">tips_and_updates</span>
                                    Recommandations
                                </h3>
                            </div>
                            <div class="p-4">
                                <div id="stock-recommendations" class="space-y-3">
                                    <!-- Les recommandations seront chargées dynamiquement -->
                                    <div class="animate-pulse">
                                        <div class="h-4 bg-blue-100 rounded w-full mb-2"></div>
                                        <div class="h-3 bg-blue-50 rounded w-3/4"></div>
                                    </div>
                                    <div class="animate-pulse">
                                        <div class="h-4 bg-blue-100 rounded w-full mb-2"></div>
                                        <div class="h-3 bg-blue-50 rounded w-3/4"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <?php include_once '../components/footer.html'; ?>
    </div>

    <!-- Modal pour la prévisualisation de facture -->
    <div id="invoicePreviewModal" class="invoice-modal">
        <div class="invoice-modal-content">
            <span class="close">&times;</span>
            <h2 id="invoiceModalTitle" class="text-xl font-semibold mb-4">Prévisualisation de la facture</h2>
            <div id="invoiceModalContent" class="flex justify-content-center mt-4">
                <!-- Le contenu du modal sera inséré ici dynamiquement -->
            </div>
            <div class="mt-4 flex justify-end">
                <button id="invoiceDownloadBtn" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
                    Télécharger
                </button>
            </div>
        </div>
    </div>

    <!-- Modal pour les détails du produit -->
    <div id="productDetailModal" class="product-modal">
        <div class="product-modal-content">
            <div class="product-modal-header">
                <h2 id="productModalTitle" class="product-modal-title">Détails du Produit</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div id="productModalBody" class="product-modal-body">
                <!-- Le contenu sera chargé dynamiquement -->
                <div class="animate-pulse">
                    <div class="h-6 bg-gray-200 rounded w-1/4 mb-4"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/2 mb-2"></div>
                    <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/3 mb-4"></div>

                    <div class="h-6 bg-gray-200 rounded w-1/4 mb-4 mt-6"></div>
                    <div class="h-24 bg-gray-200 rounded w-full mb-4"></div>
                </div>
            </div>
            <div class="product-modal-footer">
                <button id="closeProductModal"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                    Fermer
                </button>
                <button id="viewProductHistory"
                    class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    <span class="material-icons align-middle mr-1" style="font-size: 18px;">history</span>
                    Voir l'historique
                </button>
            </div>
        </div>
    </div>


    <!-- jQuery et scripts DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/select/1.3.4/js/dataTables.select.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

    <!-- Ajoutez ces scripts après les autres scripts DataTables -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function () {
            // Variables globales
            let productsTable;
            let lowStockTable;
            let outOfStockTable;
            let currentEntriesSortiesPage = 1;
            const movementsPerPage = 10;
            let chartInstances = {}; // Pour stocker les instances de graphiques

            // Fonction de mise à jour de la date et de l'heure
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
                const formattedDate = now.toLocaleDateString('fr-FR', options);
                document.getElementById('date-time-display').textContent = formattedDate;
            }

            // Mettre à jour la date et l'heure initialement et toutes les minutes
            updateDateTime();
            setInterval(updateDateTime, 60000);

            // Initialisation des tableaux pour les différents onglets
            initProductsTable();
            // Les autres tableaux seront initialisés lors du clic sur leur onglet respectif

            // Statistiques de stock initiales
            loadStockStatistics();

            // Gestion des onglets
            $('.tab-button').on('click', function () {
                const tabId = $(this).data('tab');

                // Activer l'onglet
                $('.tab-button').removeClass('active');
                $(this).addClass('active');

                // Afficher le contenu associé
                $('.tab-content').removeClass('active').hide();
                $(`#${tabId}`).addClass('active').show();

                // Initialiser le tableau correspondant s'il n'existe pas encore
                if (tabId === 'low-stock' && !lowStockTable) {
                    initLowStockTable();
                } else if (tabId === 'out-of-stock' && !outOfStockTable) {
                    initOutOfStockTable();
                }
            });

            // Gestion des boutons principaux (navigation)
            $('#btn-consulter-stock').on('click', function (e) {
                e.preventDefault();

                // Afficher le contenu du stock
                $('#stock-content').show();
                $('#entrees-sorties-content').hide();
                $('#tableau-bord-content').hide();

                // Mettre à jour les styles des boutons
                updateButtonStyles($(this));

                // Rafraîchir les données si nécessaire
                if (productsTable) {
                    productsTable.ajax.reload();
                    loadStockStatistics();
                }
            });

            $('#btn-entrees-sorties').on('click', function (e) {
                e.preventDefault();

                // Afficher un indicateur de chargement
                $('#entrees-sorties-content').html('<div class="text-center p-8"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-700"></div><p class="mt-2 text-gray-600">Chargement en cours...</p></div>').show();
                $('#stock-content').hide();
                $('#tableau-bord-content').hide();

                // Mettre à jour les styles des boutons
                updateButtonStyles($(this));

                // Charger le contenu des entrées/sorties via AJAX
                loadEntriesSortiesContent();
            });

            $('#btn-tableau-bord').on('click', function (e) {
                e.preventDefault();

                // Afficher le contenu du tableau de bord
                $('#tableau-bord-content').show();
                $('#stock-content').hide();
                $('#entrees-sorties-content').hide();

                // Mettre à jour les styles des boutons
                updateButtonStyles($(this));

                // Charger/initialiser les graphiques et données du tableau de bord
                initDashboard();
            });

            // Fonction pour mettre à jour les styles des boutons
            function updateButtonStyles(activeButton) {
                // Réinitialiser tous les boutons
                $('#btn-consulter-stock').removeClass('active').css({
                    'background-color': '#ebf7ee',
                    'color': '#10b981'
                });

                $('#btn-entrees-sorties').removeClass('active').css({
                    'background-color': '#f0e6fd',
                    'color': '#7c3aed'
                });

                $('#btn-tableau-bord').removeClass('active').css({
                    'background-color': '#e0f2fe',
                    'color': '#0284c7'
                });

                // Activer le bouton sélectionné
                activeButton.addClass('active').css({
                    'background-color': '#ebf7ee',
                    'color': '#10b981'
                });

                // Appliquer des couleurs spécifiques en fonction du bouton
                if (activeButton.attr('id') === 'btn-entrees-sorties') {
                    activeButton.css({
                        'background-color': '#ebf7ee',
                        'color': '#10b981'
                    });
                } else if (activeButton.attr('id') === 'btn-tableau-bord') {
                    activeButton.css({
                        'background-color': '#ebf7ee',
                        'color': '#10b981'
                    });
                }
            }

            // Initialisation de la DataTable pour les produits (tous)
            function initProductsTable() {
                productsTable = $('#products-table').DataTable({
                    responsive: true,
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'api/stock/get_filtered_products.php',
                        data: function (d) {
                            // Ajouter les filtres personnalisés
                            d.product_name = $('#filter-product-name').val();
                            d.category = $('#filter-category').val();
                            d.unit = $('#filter-unit').val();
                            d.stock_level = $('#filter-stock-level').val();
                        },
                        error: function (xhr, status, error) {
                            console.error("Erreur AJAX:", status, error);
                            console.log("Réponse du serveur:", xhr.responseText);

                            // Fallback aux anciennes API si la nouvelle n'existe pas
                            if (xhr.status === 404) {
                                $.ajax({
                                    url: 'api/stock/get_products.php',
                                    dataSrc: '',
                                    success: function (data) {
                                        productsTable.clear().rows.add(data).draw();
                                    }
                                });
                            }
                        }
                    },
                    columns: [
                        {
                            data: 'barcode',
                            render: function (data) {
                                return `<span class="font-mono text-xs">${data || ''}</span>`;
                            }
                        },
                        { data: 'product_name' },
                        { data: 'category_name' },
                        {
                            data: 'quantity',
                            className: 'text-right'
                        },
                        {
                            data: 'quantity_reserved',
                            className: 'text-right',
                            render: function (data) {
                                return data || 0;
                            }
                        },
                        { data: 'unit' },
                        {
                            data: null,
                            render: function (data, type, row) {
                                let quantity = parseInt(row.quantity);
                                let statusClass = '';
                                let statusText = '';

                                if (quantity === 0) {
                                    statusClass = 'status-low';
                                    statusText = 'Rupture';
                                } else if (quantity < 3) {
                                    statusClass = 'status-low';
                                    statusText = 'Faible';
                                } else if (quantity <= 10) {
                                    statusClass = 'status-medium';
                                    statusText = 'Moyen';
                                } else {
                                    statusClass = 'status-high';
                                    statusText = 'Élevé';
                                }

                                return `
                        <div class="stock-status">
                            <div class="status-indicator ${statusClass}"></div>
                            <span>${statusText}</span>
                        </div>
                    `;
                            }
                        },
                        {
                            // Colonne pour les actions
                            data: null,
                            orderable: false,
                            render: function (data, type, row) {
                                return `
                        <div class="flex space-x-2">
                            <button class="action-button view" onclick="viewProductDetails(${row.id || 0}, '${row.product_name.replace(/'/g, "\\'")}')">
                                <span class="material-icons" style="font-size: 16px;">visibility</span>
                                Détails
                            </button>
                            <button class="action-button history" onclick="viewProductHistory(${row.id || 0}, '${row.product_name.replace(/'/g, "\\'")}')">
                                <span class="material-icons" style="font-size: 16px;">history</span>
                                Historique
                            </button>
                        </div>
                    `;
                            }
                        }
                    ],
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'excel',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle;">file_download</span> Excel',
                            className: 'btn-filter',
                            title: 'Inventaire_des_Produits',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6]
                            }
                        },
                        {
                            extend: 'pdf',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle;">picture_as_pdf</span> PDF',
                            className: 'btn-filter',
                            title: 'Inventaire_des_Produits',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6]
                            }
                        },
                        {
                            extend: 'print',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle;">print</span> Imprimer',
                            className: 'btn-filter',
                            title: 'Inventaire des Produits',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6]
                            }
                        }
                    ],
                    order: [[1, 'asc']],
                    pageLength: 15,
                    lengthMenu: [15, 25, 50, 100],
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                    }
                });

                // Événements pour le tableau des produits
                $('#products-table tbody').on('click', 'tr', function (e) {
                    // S'assurer que le clic n'était pas sur un bouton d'action
                    if (!$(e.target).closest('.action-button').length) {
                        const data = productsTable.row(this).data();
                        if (data && data.id) {
                            viewProductDetails(data.id, data.product_name);
                        }
                    }
                });
            }

            // Initialisation de la DataTable pour les produits en stock faible
            function initLowStockTable() {
                lowStockTable = $('#low-stock-table').DataTable({
                    responsive: true,
                    processing: true,
                    ajax: {
                        url: 'api/stock/get_low_stock.php',
                        dataSrc: '',
                        error: function (xhr, status, error) {
                            console.error("Erreur AJAX:", status, error);
                            console.log("Réponse du serveur:", xhr.responseText);

                            // Fallback pour simuler les données si l'API n'existe pas encore
                            if (xhr.status === 404) {
                                return {
                                    data: productsTable ? filterLowStockProducts(productsTable.data()) : []
                                };
                            }
                        }
                    },
                    columns: [
                        {
                            data: 'barcode',
                            render: function (data) {
                                return `<span class="font-mono text-xs">${data || ''}</span>`;
                            }
                        },
                        { data: 'product_name' },
                        { data: 'category_name' },
                        {
                            data: 'quantity',
                            className: 'text-right',
                            render: function (data) {
                                return `<span class="font-semibold text-yellow-600">${data}</span>`;
                            }
                        },
                        { data: 'unit' },
                        {
                            data: null,
                            orderable: false,
                            render: function (data, type, row) {
                                return `
                                <div class="flex space-x-2">
                                    <button class="action-button view" onclick="viewProductDetails(${row.id || 0}, '${row.product_name.replace(/'/g, "\\'")}')">
                                        <span class="material-icons" style="font-size: 16px;">visibility</span>
                                        Détails
                                    </button>
                                </div>
                            `;
                            }
                        }
                    ],
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'excel',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle;">file_download</span> Excel',
                            className: 'btn-filter',
                            title: 'Produits_Stock_Faible',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4]
                            }
                        },
                        {
                            extend: 'pdf',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle;">picture_as_pdf</span> PDF',
                            className: 'btn-filter',
                            title: 'Produits_Stock_Faible',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4]
                            }
                        }
                    ],
                    order: [[3, 'asc']],
                    pageLength: 10,
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                    }
                });
            }

            // Initialisation de la DataTable pour les produits en rupture de stock
            function initOutOfStockTable() {
                outOfStockTable = $('#out-of-stock-table').DataTable({
                    responsive: true,
                    processing: true,
                    ajax: {
                        url: 'api/stock/get_out_of_stock.php',
                        dataSrc: '',
                        error: function (xhr, status, error) {
                            console.error("Erreur AJAX:", status, error);
                            console.log("Réponse du serveur:", xhr.responseText);

                            // Fallback pour simuler les données si l'API n'existe pas encore
                            if (xhr.status === 404) {
                                return {
                                    data: productsTable ? filterOutOfStockProducts(productsTable.data()) : []
                                };
                            }
                        }
                    },
                    columns: [
                        {
                            data: 'barcode',
                            render: function (data) {
                                return `<span class="font-mono text-xs">${data || ''}</span>`;
                            }
                        },
                        { data: 'product_name' },
                        { data: 'category_name' },
                        {
                            data: 'quantity',
                            className: 'text-right',
                            render: function (data) {
                                return `<span class="font-semibold text-red-600">0</span>`;
                            }
                        },
                        { data: 'unit' },
                        {
                            data: null,
                            orderable: false,
                            render: function (data, type, row) {
                                return `
                                <div class="flex space-x-2">
                                    <button class="action-button view" onclick="viewProductDetails(${row.id || 0}, '${row.product_name.replace(/'/g, "\\'")}')">
                                        <span class="material-icons" style="font-size: 16px;">visibility</span>
                                        Détails
                                    </button>
                                </div>
                            `;
                            }
                        }
                    ],
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'excel',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle;">file_download</span> Excel',
                            className: 'btn-filter',
                            title: 'Produits_Rupture_Stock',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4]
                            }
                        },
                        {
                            extend: 'pdf',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle;">picture_as_pdf</span> PDF',
                            className: 'btn-filter',
                            title: 'Produits_Rupture_Stock',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4]
                            }
                        }
                    ],
                    order: [[1, 'asc']],
                    pageLength: 10,
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                    }
                });
            }

            // Fonctions de filtrage fallback (si l'API n'existe pas)
            function filterLowStockProducts(data) {
                return data.filter(product => {
                    const quantity = parseInt(product.quantity);
                    return quantity > 0 && quantity <= 10;
                });
            }

            function filterOutOfStockProducts(data) {
                return data.filter(product => {
                    const quantity = parseInt(product.quantity);
                    return quantity === 0;
                });
            }

            // Chargement des statistiques de stock
            function loadStockStatistics() {
                $.ajax({
                    url: 'api/stock/get_stock_stats.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        // Mise à jour des statistiques
                        $('#total-products').text(data.total || 0);
                        $('#unique-categories').text(data.categories || 0);
                        $('#normal-stock').text(data.normal || 0);
                        $('#normal-stock-percent').text(data.normalPercent || 0);
                        $('#low-stock').text(data.low || 0);
                        $('#low-stock-percent').text(data.lowPercent || 0);
                        $('#out-of-stock').text(data.zero || 0);
                    },
                    error: function (xhr, status, error) {
                        console.error("Erreur lors du chargement des statistiques:", error);

                        // Fallback pour calculer les stats à partir des données du tableau
                        if (productsTable) {
                            setTimeout(calculateStatsFromTable, 1000);
                        }
                    }
                });
            }

            // Fallback pour calculer les statistiques si l'API n'existe pas
            function calculateStatsFromTable() {
                const tableData = productsTable.data();
                if (!tableData || !tableData.length) return;

                let stats = {
                    total: tableData.length,
                    categories: new Set(),
                    normal: 0,
                    low: 0,
                    zero: 0
                };

                tableData.forEach(product => {
                    const quantity = parseInt(product.quantity);
                    if (product.category_name) stats.categories.add(product.category_name);

                    if (quantity === 0) {
                        stats.zero++;
                    } else if (quantity <= 10) {
                        stats.low++;
                    } else {
                        stats.normal++;
                    }
                });

                // Calculer les pourcentages
                const normalPercent = Math.round((stats.normal / stats.total) * 100);
                const lowPercent = Math.round((stats.low / stats.total) * 100);

                // Mettre à jour l'interface
                $('#total-products').text(stats.total);
                $('#unique-categories').text(stats.categories.size);
                $('#normal-stock').text(stats.normal);
                $('#normal-stock-percent').text(normalPercent);
                $('#low-stock').text(stats.low);
                $('#low-stock-percent').text(lowPercent);
                $('#out-of-stock').text(stats.zero);
            }

            // Gestion des filtres pour les produits
            $('#btn-apply-filters').on('click', function () {
                applyFilters();
            });

            $('#btn-reset-filters').on('click', function () {
                $('#filter-unit').val('');
                $('#filter-stock-level').val('');
                $('#filter-category').val('');
                $('#filter-product-name').val('');
                applyFilters();
            });

            function applyFilters() {
                // Avec serverSide: true, il suffit de redessiner la table pour appliquer les filtres
                productsTable.draw();

                // Mettre à jour les statistiques aussi
                loadStockStatistics();
            }

            // Fonctionnalité Entrées/Sorties (reprise du code existant)
            function loadEntriesSortiesContent() {
                // Construire le contenu HTML des entrées/sorties
                let entriesSortiesHTML = `
                <div class="mb-6 flex justify-between items-center">
                    <div class="flex items-center">
                        <h2 class="text-2xl font-bold text-gray-800">Entrées/Sorties</h2>
                        <span class="ml-2 badge badge-blue">Mouvements</span>
                    </div>
                </div>
                
                <div class="card-container">
                    <!-- Barre de recherche et filtrage -->
                    <div class="filter-section p-4">
                        <div class="flex flex-wrap space-x-4">
                            <div class="relative w-64 mt-2">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons text-gray-400">search</span>
                                </span>
                                <input type="text" id="searchInput" placeholder="Rechercher un mouvement"
                                    class="w-full pl-10 p-2 border rounded">
                            </div>
                            <div class="relative mt-2">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons text-gray-400">filter_list</span>
                                </span>
                                <select id="movementTypeFilter" class="pl-10 p-2 border rounded appearance-none pr-8">
                                    <option value="">Tous les types</option>
                                    <option value="entry">Entrée</option>
                                    <option value="output">Sortie</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <span class="material-icons text-gray-400">expand_more</span>
                                </span>
                            </div>
                            <div class="relative mt-2">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons text-gray-400">business</span>
                                </span>
                                <select id="projectFilter" class="pl-10 p-2 border rounded appearance-none pr-8">
                                    <option value="">Tous les projets</option>
                                    <!-- Les projets seront chargés dynamiquement -->
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <span class="material-icons text-gray-400">expand_more</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tableau des mouvements de stock -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provenance</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Projet</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Demandeur</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facture</th>
                                </tr>
                            </thead>
                            <tbody id="movementTableBody" class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="10" class="px-6 py-4 text-center text-gray-500">Chargement des données...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="pagination" class="mt-4 flex justify-center space-x-2">
                        <!-- Les boutons de pagination seront insérés ici -->
                    </div>
                </div>
            `;

                // Insérer le HTML
                $('#entrees-sorties-content').html(entriesSortiesHTML);

                // Initialiser les événements et données
                loadProjects();
                loadMovements(currentEntriesSortiesPage);

                // Écouter les événements de filtre
                $('#searchInput').on('input', debounce(function () {
                    currentEntriesSortiesPage = 1;
                    loadMovements(currentEntriesSortiesPage);
                }, 300));

                $('#movementTypeFilter, #projectFilter').on('change', function () {
                    currentEntriesSortiesPage = 1;
                    loadMovements(currentEntriesSortiesPage);
                });
            }

            // Initialisation du tableau de bord
            function initDashboard() {
                // Charger les données pour les graphiques
                loadDashboardData();

                // Initialiser les événements du tableau de bord
                $('#btn-refresh-dashboard').on('click', function () {
                    // Réinitialiser les graphiques
                    for (let chartId in chartInstances) {
                        if (chartInstances[chartId]) {
                            chartInstances[chartId].destroy();
                        }
                    }

                    // Recharger les données
                    loadDashboardData();
                });

                $('#btn-print-dashboard').on('click', function () {
                    window.print();
                });
            }

            // Chargement des données du tableau de bord
            function loadDashboardData() {
                $.ajax({
                    url: 'api/stock/get_dashboard_data.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        // Initialiser les graphiques
                        initStockCategoryChart(data.categories || []);
                        initStockMovementChart(data.movements || []);

                        // Mettre à jour les listes
                        updateTopProductsList(data.topProducts || []);
                        updateStockAlertsList(data.alerts || []);
                        updateRecommendationsList(data.recommendations || []);
                    },
                    error: function (xhr, status, error) {
                        console.error("Erreur lors du chargement des données du tableau de bord:", error);

                        // Fallback avec des données simulées
                        simulateDashboardData();
                    }
                });
            }

            // Fallback : Simuler les données du tableau de bord si l'API n'existe pas
            function simulateDashboardData() {
                const tableData = productsTable ? productsTable.data() : [];

                // Données pour le graphique par catégorie
                const categoriesData = generateSimulatedCategoriesData(tableData);
                initStockCategoryChart(categoriesData);

                // Données pour le graphique des mouvements
                const movementsData = generateSimulatedMovementsData();
                initStockMovementChart(movementsData);

                // Données pour les listes
                const topProducts = generateSimulatedTopProducts(tableData);
                updateTopProductsList(topProducts);

                // Alertes et recommandations
                const alerts = generateSimulatedAlerts(tableData);
                updateStockAlertsList(alerts);

                const recommendations = generateSimulatedRecommendations(tableData);
                updateRecommendationsList(recommendations);
            }

            // Génération de données simulées pour les graphiques
            function generateSimulatedCategoriesData(tableData) {
                const categories = {};

                // Grouper par catégorie
                tableData.forEach(product => {
                    const category = product.category_name || 'Non catégorisé';
                    if (!categories[category]) {
                        categories[category] = {
                            count: 0,
                            quantity: 0
                        };
                    }

                    categories[category].count++;
                    categories[category].quantity += parseInt(product.quantity) || 0;
                });

                // Formater pour le graphique
                const result = [];
                for (const [category, data] of Object.entries(categories)) {
                    result.push({
                        category: category,
                        count: data.count,
                        quantity: data.quantity
                    });
                }

                return result;
            }

            function generateSimulatedMovementsData() {
                // Simuler des données de mouvements pour les 7 derniers jours
                const days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
                const result = [];

                for (let i = 0; i < 7; i++) {
                    const entries = Math.floor(Math.random() * 10) + 1;
                    const outputs = Math.floor(Math.random() * 8);

                    result.push({
                        day: days[i],
                        entries: entries,
                        outputs: outputs,
                        total: entries - outputs
                    });
                }

                return result;
            }

            function generateSimulatedTopProducts(tableData) {
                // Trier par quantité en stock et prendre les 5 premiers
                const sortedData = [...tableData].sort((a, b) =>
                    (parseInt(b.quantity) || 0) - (parseInt(a.quantity) || 0)
                ).slice(0, 5);

                return sortedData.map(product => ({
                    id: product.id || 0,
                    name: product.product_name,
                    quantity: parseInt(product.quantity) || 0,
                    category: product.category_name
                }));
            }

            function generateSimulatedAlerts(tableData) {
                // Filtrer les produits en rupture ou en stock faible
                const criticalProducts = tableData.filter(product => {
                    const quantity = parseInt(product.quantity) || 0;
                    return quantity < 3;
                }).slice(0, 5);

                return criticalProducts.map(product => ({
                    id: product.id || 0,
                    name: product.product_name,
                    quantity: parseInt(product.quantity) || 0,
                    type: parseInt(product.quantity) === 0 ? 'rupture' : 'faible'
                }));
            }

            function generateSimulatedRecommendations(tableData) {
                // Générer des recommandations basées sur l'état du stock
                const lowStockCount = tableData.filter(p => {
                    const q = parseInt(p.quantity) || 0;
                    return q > 0 && q < 3;
                }).length;

                const zeroStockCount = tableData.filter(p => {
                    const q = parseInt(p.quantity) || 0;
                    return q === 0;
                }).length;

                const recommendations = [];

                if (zeroStockCount > 0) {
                    recommendations.push({
                        title: `Commander d'urgence les ${zeroStockCount} produits en rupture`,
                        description: 'Ces produits sont actuellement indisponibles et pourraient affecter les projets.',
                        priority: 'high'
                    });
                }

                if (lowStockCount > 0) {
                    recommendations.push({
                        title: `Planifier le réapprovisionnement de ${lowStockCount} produits`,
                        description: 'Ces produits seront bientôt en rupture si aucune action n\'est prise.',
                        priority: 'medium'
                    });
                }

                recommendations.push({
                    title: 'Analyser les tendances de consommation',
                    description: 'Identifier les produits les plus utilisés pour optimiser les niveaux de stock.',
                    priority: 'low'
                });

                return recommendations;
            }

            // Initialisation des graphiques
            function initStockCategoryChart(categoriesData) {
                const ctx = document.getElementById('stockCategoryChart');
                if (!ctx) return;

                // Détruire le graphique existant s'il y en a un
                if (chartInstances.stockCategory) {
                    chartInstances.stockCategory.destroy();
                }

                // Préparer les données
                const labels = categoriesData.map(item => item.category);
                const quantities = categoriesData.map(item => item.quantity);
                const counts = categoriesData.map(item => item.count);

                // Générer des couleurs aléatoires
                const backgroundColors = labels.map(() => {
                    const r = Math.floor(Math.random() * 200);
                    const g = Math.floor(Math.random() * 200);
                    const b = Math.floor(Math.random() * 200);
                    return `rgba(${r}, ${g}, ${b}, 0.7)`;
                });

                // Créer le graphique
                chartInstances.stockCategory = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Quantité en stock',
                                data: quantities,
                                backgroundColor: backgroundColors,
                                borderColor: backgroundColors.map(color => color.replace('0.7', '1')),
                                borderWidth: 1
                            },
                            {
                                label: 'Nombre de produits',
                                data: counts,
                                type: 'line',
                                borderColor: '#4b5563',
                                backgroundColor: 'rgba(75, 85, 99, 0.2)',
                                borderWidth: 2,
                                pointBackgroundColor: '#4b5563',
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Quantité en stock'
                                }
                            },
                            y1: {
                                position: 'right',
                                beginAtZero: true,
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

            function initStockMovementChart(movementsData) {
                const ctx = document.getElementById('stockMovementChart');
                if (!ctx) return;

                // Détruire le graphique existant s'il y en a un
                if (chartInstances.stockMovement) {
                    chartInstances.stockMovement.destroy();
                }

                // Préparer les données
                const labels = movementsData.map(item => item.day);
                const entries = movementsData.map(item => item.entries);
                const outputs = movementsData.map(item => item.outputs);
                const totals = movementsData.map(item => item.total);

                // Créer le graphique
                chartInstances.stockMovement = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Entrées',
                                data: entries,
                                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                                borderColor: 'rgba(16, 185, 129, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Sorties',
                                data: outputs,
                                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                                borderColor: 'rgba(239, 68, 68, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Bilan',
                                data: totals,
                                type: 'line',
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                borderWidth: 2,
                                pointBackgroundColor: '#3b82f6',
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Quantité'
                                }
                            }
                        }
                    }
                });
            }

            // Mise à jour des listes dans le tableau de bord
            function updateTopProductsList(topProducts) {
                const container = $('#top-products-list');
                if (!container) return;

                if (!topProducts || topProducts.length === 0) {
                    container.html('<div class="text-center py-4 text-gray-500">Aucune donnée disponible</div>');
                    return;
                }

                let html = '';
                topProducts.forEach((product, index) => {
                    const rank = index + 1;
                    html += `
                    <div class="product-status-item flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="bg-blue-100 text-blue-600 font-bold h-8 w-8 rounded-full flex items-center justify-center mr-3">
                                ${rank}
                            </div>
                            <div>
                                <div class="font-medium">${product.name}</div>
                                <div class="text-xs text-gray-500">${product.category || 'Non catégorisé'}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-gray-700">${product.quantity}</div>
                            <div class="text-xs text-gray-500">en stock</div>
                        </div>
                    </div>
                `;
                });

                container.html(html);
            }

            function updateStockAlertsList(alerts) {
                const container = $('#stock-alerts');
                if (!container) return;

                if (!alerts || alerts.length === 0) {
                    container.html('<div class="text-center py-4 text-gray-500">Aucune alerte actuellement</div>');
                    return;
                }

                let html = '';
                alerts.forEach(alert => {
                    const isRupture = alert.type === 'rupture';
                    const bgColor = isRupture ? 'bg-red-50' : 'bg-yellow-50';
                    const borderColor = isRupture ? 'border-red-200' : 'border-yellow-200';
                    const textColor = isRupture ? 'text-red-700' : 'text-yellow-700';
                    const icon = isRupture ? 'error' : 'warning';

                    html += `
                    <div class="p-3 ${bgColor} border ${borderColor} rounded-lg">
                        <div class="flex items-start">
                            <span class="material-icons ${textColor} mr-2">${icon}</span>
                            <div>
                                <div class="font-medium ${textColor}">
                                    ${isRupture ? 'RUPTURE' : 'STOCK FAIBLE'}: ${alert.name}
                                </div>
                                <div class="text-sm mt-1">
                                    <span class="font-bold">${alert.quantity}</span> unité(s) restantes
                                </div>
                                <button onclick="viewProductDetails(${alert.id}, '${alert.name.replace(/'/g, "\\'")}');" class="mt-2 text-xs bg-white ${textColor} px-2 py-1 rounded hover:bg-opacity-80">
                                    Voir détails
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                });

                container.html(html);
            }

            function updateRecommendationsList(recommendations) {
                const container = $('#stock-recommendations');
                if (!container) return;

                if (!recommendations || recommendations.length === 0) {
                    container.html('<div class="text-center py-4 text-gray-500">Aucune recommandation disponible</div>');
                    return;
                }

                let html = '';
                recommendations.forEach(rec => {
                    let priorityColor, priorityBg, priorityText;

                    switch (rec.priority) {
                        case 'high':
                            priorityColor = 'text-red-700';
                            priorityBg = 'bg-red-50';
                            priorityText = 'Priorité Haute';
                            break;
                        case 'medium':
                            priorityColor = 'text-yellow-700';
                            priorityBg = 'bg-yellow-50';
                            priorityText = 'Priorité Moyenne';
                            break;
                        default:
                            priorityColor = 'text-blue-700';
                            priorityBg = 'bg-blue-50';
                            priorityText = 'Priorité Normale';
                    }

                    html += `
                    <div class="p-3 ${priorityBg} border border-gray-200 rounded-lg">
                        <div class="flex items-start">
                            <span class="material-icons ${priorityColor} mr-2">lightbulb</span>
                            <div>
                                <div class="font-medium ${priorityColor}">
                                    ${rec.title}
                                </div>
                                <div class="text-sm text-gray-600 mt-1">
                                    ${rec.description}
                                </div>
                                <div class="mt-2 text-xs ${priorityColor} font-medium">
                                    ${priorityText}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                });

                container.html(html);
            }

            // Fonctions utilitaires
            // Fonction utilitaire debounce
            function debounce(func, wait) {
                let timeout;
                return function () {
                    const context = this;
                    const args = arguments;
                    clearTimeout(timeout);
                    timeout = setTimeout(function () {
                        func.apply(context, args);
                    }, wait);
                };
            }

            // Fonctions pour la pagination des mouvements
            function updatePaginationControls(currentPage, totalPages) {
                $('#current-page').text(currentPage);
                $('#total-pages').text(totalPages);

                // Activer/désactiver les boutons selon la position
                $('#prev-events').prop('disabled', currentPage <= 1);
                $('#next-events').prop('disabled', currentPage >= totalPages);

                // Cacher la pagination s'il n'y a pas d'événements
                $('#events-pagination').toggle(totalPages > 0);
            }

            // Fonction pour charger les projets
            function loadProjects() {
                $.ajax({
                    url: 'api/stock/api_getProjects.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            let projectSelect = $('#projectFilter');
                            projectSelect.empty();
                            projectSelect.append('<option value="">Tous les projets</option>');

                            if (response.projects && response.projects.length > 0) {
                                // Trier les projets par code
                                response.projects.sort((a, b) => a.code_projet.localeCompare(b.code_projet));

                                response.projects.forEach(function (project) {
                                    const projectCode = project.code_projet || 'N/A';
                                    const clientName = project.nom_client || 'Client non spécifié';

                                    projectSelect.append(`
                                    <option value="${projectCode}">
                                        ${projectCode} - ${clientName}
                                    </option>
                                `);
                                });
                            } else {
                                projectSelect.append('<option value="">Aucun projet disponible</option>');
                            }
                        } else {
                            console.error('Erreur lors du chargement des projets:', response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erreur AJAX:', error);
                    }
                });
            }

            // Fonction pour charger les mouvements de stock
            function loadMovements(page) {
                const search = $('#searchInput').val();
                const movementType = $('#movementTypeFilter').val();
                const projectCode = $('#projectFilter').val();

                $.ajax({
                    url: 'api/stock/api_getStockMovements.php',
                    type: 'GET',
                    data: {
                        page: page,
                        limit: movementsPerPage,
                        search: search,
                        movement_type: movementType,
                        project_code: projectCode
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            let tableBody = $('#movementTableBody');
                            tableBody.empty();

                            if (response.movements.length === 0) {
                                tableBody.append(`
                                <tr>
                                    <td colspan="10" class="px-6 py-4 text-center text-gray-500">Aucun mouvement trouvé</td>
                                </tr>
                            `);
                            } else {
                                response.movements.forEach(function (movement) {
                                    let badgeClass, movementTypeDisplay;

                                    if (movement.movement_type === 'entry') {
                                        badgeClass = 'badge-entry';
                                        movementTypeDisplay = 'Entrée';
                                    } else if (movement.movement_type === 'output') {
                                        badgeClass = 'badge-output';
                                        movementTypeDisplay = 'Sortie';
                                    }

                                    // Affichage conditionnel en fonction du type de mouvement
                                    const isOutput = movement.movement_type === 'output';

                                    tableBody.append(`
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.id}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${movement.product_name}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.quantity}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="badge ${badgeClass}">${movementTypeDisplay}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.provenance || '-'}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-medium">${movement.nom_projet || '-'}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 ${!isOutput ? 'text-gray-300' : ''}">${isOutput ? (movement.destination || '-') : '-'}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 ${!isOutput ? 'text-gray-300' : ''}">${isOutput ? (movement.demandeur || '-') : '-'}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.date}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${createInvoiceCell(movement)}</td>
                                    </tr>
                                `);
                                });
                            }

                            updatePaginationControls(response.currentPage, response.totalPages);
                        } else {
                            console.error('Erreur lors de la récupération des mouvements:', response.message);
                            $('#movementTableBody').html('<tr><td colspan="10" class="text-center py-4">Erreur lors de la récupération des mouvements. Veuillez réessayer.</td></tr>');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erreur AJAX:', error);
                        $('#movementTableBody').html('<tr><td colspan="10" class="text-center py-4">Erreur de connexion au serveur. Veuillez réessayer plus tard.</td></tr>');
                    }
                });
            }

            // Fonction pour générer la cellule de facture
            function createInvoiceCell(movement) {
                // Par défaut, vide
                if (movement.movement_type !== 'entry' || !movement.invoice_id) {
                    return '-';
                }

                // Si c'est une entrée avec facture
                return `
                <a href="javascript:void(0);" onclick="previewInvoice(${movement.invoice_id})" class="text-blue-600 hover:text-blue-800 flex items-center">
                    <span class="material-icons text-sm mr-1">description</span>
                    Facture #${movement.invoice_id}
                </a>
            `;
            }

            // Gestion des boutons d'export
            $('#btn-export-excel').on('click', function () {
                if (productsTable) {
                    productsTable.button('.buttons-excel').trigger();
                }
            });

            $('#btn-export-pdf').on('click', function () {
                // Récupérer les filtres actuels
                const productName = $('#filter-product-name').val();
                const category = $('#filter-category').val();
                const stockLevel = $('#filter-stock-level').val();

                let filter = 'all';
                if (stockLevel === 'low') {
                    filter = 'low';
                } else if (stockLevel === 'zero') {
                    filter = 'out';
                }

                // Rediriger vers la nouvelle API d'exportation PDF avec les paramètres de filtre
                window.location.href = `api/stock/export_pdf.php?filter=${filter}&category=${category}&product_name=${encodeURIComponent(productName)}`;
            });

            // Ajouter un gestionnaire pour le sélecteur de période dans le tableau de bord
            $('#dashboard-period').on('change', function () {
                const period = $(this).val();
                const category = $('#dashboard-category').val();
                loadStockTrends(period, category);
            });

            // Ajouter un gestionnaire pour le sélecteur de catégorie dans le tableau de bord
            $('#dashboard-category').on('change', function () {
                const period = $('#dashboard-period').val();
                const category = $(this).val();
                loadStockTrends(period, category);
            });

            $('#btn-refresh-stock').on('click', function () {
                if (productsTable) {
                    productsTable.ajax.reload();
                }

                loadStockStatistics();

                // Afficher une notification
                Toastify({
                    text: "Les données du stock ont été actualisées",
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#10b981",
                    stopOnFocus: true
                }).showToast();
            });

            // Fonction pour charger les tendances du stock
            function loadStockTrends(period, category) {
                $.ajax({
                    url: 'api/stock/get_stock_trends.php',
                    type: 'GET',
                    data: {
                        period: period || 'month',
                        category: category || 0
                    },
                    success: function (response) {
                        if (response.success) {
                            updateStockChart(response.data, response.stats.period);
                            updateTrendsStats(response.stats);
                        } else {
                            console.error("Erreur lors du chargement des tendances:", response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Erreur AJAX:", error);
                        // Fallback avec des données simulées
                        const simulatedData = generateSimulatedTrendsData(period || 'month');
                        updateStockChart(simulatedData, period || 'month');
                    }
                });
            }

            // Fonction pour mettre à jour le graphique des tendances
            function updateStockChart(data, period) {
                const ctx = document.getElementById('stockMovementChart');
                if (!ctx) return;

                // Détruire le graphique existant s'il y en a un
                if (chartInstances.stockMovement) {
                    chartInstances.stockMovement.destroy();
                }

                // Préparer les données
                const labels = data.map(item => item.date_label);
                const entries = data.map(item => parseInt(item.entries) || 0);
                const outputs = data.map(item => parseInt(item.outputs) || 0);
                const netChanges = data.map(item => parseInt(item.net_change) || 0);

                // Créer le graphique
                chartInstances.stockMovement = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Entrées',
                                data: entries,
                                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                                borderColor: 'rgba(16, 185, 129, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Sorties',
                                data: outputs,
                                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                                borderColor: 'rgba(239, 68, 68, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Bilan',
                                data: netChanges,
                                type: 'line',
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                borderWidth: 2,
                                pointBackgroundColor: '#3b82f6',
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Quantité'
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: `Mouvements de Stock - ${getPeriodTitle(period)}`,
                                font: {
                                    size: 16
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        }
                    }
                });
            }

            // Fonction pour obtenir le titre de la période
            function getPeriodTitle(period) {
                switch (period) {
                    case 'week':
                        return '7 derniers jours';
                    case 'month':
                        return '30 derniers jours';
                    case 'year':
                        return '12 derniers mois';
                    default:
                        return '30 derniers jours';
                }
            }

            // Fonction pour mettre à jour les statistiques de tendances
            function updateTrendsStats(stats) {
                $('#trend-entries-count').text(stats.total_entries);
                $('#trend-outputs-count').text(stats.total_outputs);
                $('#trend-net-change').text(stats.net_change);

                // Ajouter une classe de couleur en fonction du bilan net
                if (stats.net_change > 0) {
                    $('#trend-net-change').removeClass('text-red-600').addClass('text-green-600');
                    $('#trend-net-icon').html('<span class="material-icons text-green-600">arrow_upward</span>');
                } else if (stats.net_change < 0) {
                    $('#trend-net-change').removeClass('text-green-600').addClass('text-red-600');
                    $('#trend-net-icon').html('<span class="material-icons text-red-600">arrow_downward</span>');
                } else {
                    $('#trend-net-change').removeClass('text-green-600 text-red-600').addClass('text-gray-600');
                    $('#trend-net-icon').html('<span class="material-icons text-gray-600">remove</span>');
                }

                $('#trend-period-label').text(getPeriodTitle(stats.period));
            }


            // Fonction pour générer des données de tendances simulées
            function generateSimulatedTrendsData(period) {
                const data = [];
                const now = new Date();
                let days = 30;

                if (period === 'week') {
                    days = 7;
                } else if (period === 'year') {
                    days = 12; // 12 mois
                }

                for (let i = 0; i < days; i++) {
                    const date = new Date();

                    if (period === 'year') {
                        // Pour l'année, reculer de i mois
                        date.setMonth(now.getMonth() - i);
                        const label = `${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;

                        const entries = Math.floor(Math.random() * 50) + 10;
                        const outputs = Math.floor(Math.random() * 40) + 5;
                        const netChange = entries - outputs;

                        data.unshift({
                            date_label: label,
                            entries: entries,
                            outputs: outputs,
                            net_change: netChange
                        });
                    } else {
                        // Pour la semaine/mois, reculer de i jours
                        date.setDate(now.getDate() - i);
                        const label = `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}`;

                        const entries = Math.floor(Math.random() * 15) + 1;
                        const outputs = Math.floor(Math.random() * 10);
                        const netChange = entries - outputs;

                        data.unshift({
                            date_label: label,
                            entries: entries,
                            outputs: outputs,
                            net_change: netChange
                        });
                    }
                }

                return data;
            }

            // Rendre les fonctions disponibles globalement
            window.changePage = function (page) {
                currentEntriesSortiesPage = page;
                loadMovements(page);
            };

            // Fonction pour prévisualiser une facture
            window.previewInvoice = function (invoiceId) {
                const modalTitle = document.getElementById('invoiceModalTitle');
                const modalContent = document.getElementById('invoiceModalContent');
                const downloadBtn = document.getElementById('invoiceDownloadBtn');

                // Mettre à jour le titre
                modalTitle.textContent = `Facture #${invoiceId}`;

                // Afficher l'indicateur de chargement
                modalContent.innerHTML = '<div class="flex justify-center items-center p-4"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>';

                // Afficher le modal
                document.getElementById('invoicePreviewModal').style.display = 'block';

                // Charger la facture
                $.ajax({
                    url: `api/stock/get_invoice_direct.php?id=${invoiceId}`,
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        if (data.success) {
                            displayInvoiceContent(data.file_url, invoiceId);
                        } else {
                            // Si échec, essayer avec force=1
                            $.ajax({
                                url: `api/stock/get_invoice_direct.php?id=${invoiceId}&force=1`,
                                type: 'GET',
                                dataType: 'json',
                                success: function (forceData) {
                                    if (forceData.success) {
                                        displayInvoiceContent(forceData.file_url, invoiceId);
                                    } else {
                                        showInvoiceError(invoiceId);
                                    }
                                },
                                error: function () {
                                    showInvoiceError(invoiceId);
                                }
                            });
                        }
                    },
                    error: function () {
                        showInvoiceError(invoiceId);
                    }
                });
            };

            // Fonction pour afficher le contenu de la facture
            function displayInvoiceContent(fileUrl, invoiceId) {
                const modalContent = document.getElementById('invoiceModalContent');
                const fileExtension = fileUrl.split('.').pop().toLowerCase();

                if (['pdf'].includes(fileExtension)) {
                    // Pour les PDF
                    modalContent.innerHTML = `
                    <iframe src="${fileUrl}" width="100%" height="500px" frameborder="0"></iframe>
                `;
                } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                    // Pour les images
                    modalContent.innerHTML = `
                    <img src="${fileUrl}" alt="Facture #${invoiceId}" class="max-w-full h-auto">
                `;
                } else {
                    // Pour les autres types
                    modalContent.innerHTML = `
                    <div class="p-4 bg-gray-100 rounded-md text-center">
                        <span class="material-icons text-4xl text-gray-600 mb-2">description</span>
                        <p>Le fichier ne peut pas être prévisualisé directement.</p>
                        <p class="text-sm text-gray-500">Type de fichier: ${fileExtension.toUpperCase()}</p>
                    </div>
                `;
                }

                // Configurer le bouton de téléchargement
                document.getElementById('invoiceDownloadBtn').onclick = function () {
                    window.open(fileUrl, '_blank');
                };
            }

            // Fonction pour afficher un message d'erreur
            function showInvoiceError(invoiceId) {
                const modalContent = document.getElementById('invoiceModalContent');

                modalContent.innerHTML = `
                <div class="p-4 bg-red-50 rounded-md text-center">
                    <span class="material-icons text-4xl text-red-500 mb-2">error_outline</span>
                    <p>Impossible de charger la facture #${invoiceId}.</p>
                    <p class="text-sm text-gray-600">Veuillez vérifier que le fichier existe.</p>
                </div>
            `;

                // Désactiver le bouton de téléchargement
                document.getElementById('invoiceDownloadBtn').style.display = 'none';
            }

            // Gestionnaire pour fermer les modals
            $('.close, .close-modal, #closeProductModal').on('click', function () {
                $('#invoicePreviewModal').css('display', 'none');
                $('#productDetailModal').removeClass('active');
            });

            // Fermer les modals en cliquant en dehors
            $(window).on('click', function (event) {
                if (event.target == document.getElementById('invoicePreviewModal')) {
                    $('#invoicePreviewModal').css('display', 'none');
                }

                if (event.target == document.getElementById('productDetailModal')) {
                    $('#productDetailModal').removeClass('active');
                }
            });

            // Fonctions globales pour afficher les détails et l'historique des produits
            window.viewProductDetails = function (productId, productName) {
                // Mettre à jour le titre de la modale
                $('#productModalTitle').text(`Détails: ${productName}`);

                // Afficher l'indicateur de chargement
                $('#productModalBody').html(`
        <div class="animate-pulse">
            <div class="h-6 bg-gray-200 rounded w-1/4 mb-4"></div>
            <div class="h-4 bg-gray-200 rounded w-1/2 mb-2"></div>
            <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
            <div class="h-4 bg-gray-200 rounded w-1/3 mb-4"></div>
            
            <div class="h-6 bg-gray-200 rounded w-1/4 mb-4 mt-6"></div>
            <div class="h-24 bg-gray-200 rounded w-full mb-4"></div>
        </div>
    `);

                // Afficher la modale
                $('#productDetailModal').css('display', 'block');

                // Charger les détails du produit
                loadProductDetails(productId);
            };


            window.viewProductHistory = function (productId, productName) {
                // Mettre à jour le titre de la modale
                $('#productModalTitle').text(`Historique: ${productName}`);

                // Afficher l'indicateur de chargement
                $('#productModalBody').html(`
        <div class="animate-pulse">
            <div class="h-6 bg-gray-200 rounded w-1/2 mb-4"></div>
            <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
            <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
            <div class="h-4 bg-gray-200 rounded w-full mb-4"></div>
            
            <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
            <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
            <div class="h-4 bg-gray-200 rounded w-full mb-4"></div>
        </div>
    `);

                // Afficher la modale
                $('#productDetailModal').css('display', 'block');

                // Charger l'historique du produit
                loadProductHistory(productId);
            };

            // Fonction pour charger les détails d'un produit
            function loadProductDetails(productId) {
                $.ajax({
                    url: `api/stock/get_product_details.php?id=${productId}`,
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        console.log("Données reçues:", data); // Debugging
                        if (data.success) {
                            displayProductDetails(data.product);
                        } else {
                            // Fallback pour simuler les détails si l'API renvoie une erreur
                            const product = findProductInTable(productId);
                            if (product) {
                                displayProductDetails(product);
                            } else {
                                showProductError("Impossible de trouver les détails du produit.");
                            }
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Erreur lors du chargement des détails:", error);
                        showProductError("Erreur lors du chargement des détails du produit.");
                    }
                });
            }

            // Fonction pour charger l'historique d'un produit
            function loadProductHistory(productId) {
                // Récupérer la période sélectionnée (si un sélecteur existe)
                const period = $('#history-period').val() || 'all';

                $.ajax({
                    url: `api/stock/get_product_history.php?id=${productId}`,
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        console.log("Données d'historique reçues:", data); // Debugging
                        if (data.success) {
                            displayProductHistory(data.history);
                        } else {
                            // Fallback pour simuler l'historique si l'API renvoie une erreur
                            displaySimulatedProductHistory(productId);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Erreur lors du chargement de l'historique:", error);
                        // Tenter d'utiliser l'API améliorée
                        $.ajax({
                            url: `api/stock/get_product_history_enhanced.php?id=${productId}&period=${period}`,
                            type: 'GET',
                            dataType: 'json',
                            success: function (data) {
                                if (data.success) {
                                    displayEnhancedProductHistory(data);
                                } else {
                                    displaySimulatedProductHistory(productId);
                                }
                            },
                            error: function () {
                                displaySimulatedProductHistory(productId);
                            }
                        });
                    }
                });
            }


            // Fonction améliorée pour afficher l'historique d'un produit avec des statistiques
            function displayEnhancedProductHistory(data) {
                console.log("Affichage de l'historique amélioré:", data); // Debugging

                const history = data.history;
                const product = data.product;
                const stats = data.stats;

                if (!history || history.length === 0) {
                    $('#productModalBody').html(`
            <div class="text-center py-6">
                <span class="material-icons text-gray-400 text-5xl mb-2">history</span>
                <p class="text-gray-500">Aucun historique disponible pour ce produit.</p>
            </div>
        `);
                    return;
                }

                let html = `
        <div class="mb-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="bg-blue-50 p-3 rounded-lg">
                    <div class="text-sm font-medium text-gray-500">Total des entrées</div>
                    <div class="font-bold text-xl text-blue-600">${stats.total_entries} ${product.unit || 'unités'}</div>
                </div>
                <div class="bg-red-50 p-3 rounded-lg">
                    <div class="text-sm font-medium text-gray-500">Total des sorties</div>
                    <div class="font-bold text-xl text-red-600">${stats.total_outputs} ${product.unit || 'unités'}</div>
                </div>
                <div class="bg-${stats.net_change >= 0 ? 'green' : 'red'}-50 p-3 rounded-lg">
                    <div class="text-sm font-medium text-gray-500">Bilan net</div>
                    <div class="font-bold text-xl text-${stats.net_change >= 0 ? 'green' : 'red'}-600">${stats.net_change > 0 ? '+' : ''}${stats.net_change} ${product.unit || 'unités'}</div>
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <h3 class="font-semibold text-lg mb-3">Historique des Mouvements</h3>
                
                <div class="flex items-center">
                    <span class="text-sm mr-2">Période :</span>
                    <select id="history-period" class="text-sm border rounded p-1" onchange="loadProductHistory(${product.id})">
                        <option value="all" ${data.period === 'all' ? 'selected' : ''}>Tout l'historique</option>
                        <option value="month" ${data.period === 'month' ? 'selected' : ''}>30 derniers jours</option>
                        <option value="year" ${data.period === 'year' ? 'selected' : ''}>12 derniers mois</option>
                    </select>
                </div>
            </div>
            
            <div class="text-sm text-gray-500 mb-4">Affichage des ${history.length} derniers mouvements - Dernier mouvement: ${stats.last_movement || 'N/A'}</div>
            
            <div class="timeline">
    `;

                history.forEach(entry => {
                    const isEntry = entry.movement_type === 'entry' || entry.movement_type === 'return';
                    const dotColor = isEntry ? 'bg-green-500' : 'bg-red-500';
                    const date = new Date(entry.date);
                    const formattedDate = date.toLocaleDateString('fr-FR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    const impact = isEntry ? `+${entry.quantity}` : `-${entry.quantity}`;
                    const impactColor = isEntry ? 'green' : 'red';

                    html += `
            <div class="timeline-item">
                <div class="timeline-dot ${dotColor}"></div>
                <div class="timeline-content">
                    <div class="timeline-date">${entry.formatted_date || formattedDate}</div>
                    <div class="timeline-title">
                        ${entry.movement_type_display || (isEntry ? 'Entrée' : 'Sortie')} de <span class="font-bold text-${impactColor}-600">${impact}</span> ${entry.unit || 'unité(s)'}
                    </div>
                    <div class="timeline-desc">
                        ${isEntry ? `Provenance: ${entry.provenance || 'Non spécifiée'}` : `Destination: ${entry.destination || 'Non spécifiée'}`}
                        ${entry.nom_projet ? `<br>Projet: <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-0.5 rounded">${entry.nom_projet}</span>` : ''}
                        ${entry.demandeur && !isEntry ? `<br>Demandeur: ${entry.demandeur}` : ''}
                        ${entry.notes ? `<br>Notes: <span class="italic text-gray-600">${entry.notes}</span>` : ''}
                    </div>
                </div>
            </div>
        `;
                });

                html += `
            </div>
        </div>
    `;

                $('#productModalBody').html(html);

                // Modifier le bouton pour voir les détails
                $('#viewProductHistory').text('Voir détails').off('click').on('click', function () {
                    viewProductDetails(product.id, product.product_name);
                });
            }


            // Fonction utilitaire pour trouver un produit dans le tableau
            function findProductInTable(productId) {
                if (!productsTable) return null;

                const tableData = productsTable.data();
                for (let i = 0; i < tableData.length; i++) {
                    if (tableData[i] && tableData[i].id == productId) {
                        return tableData[i];
                    }
                }

                return null;
            }


            // Fonction pour afficher les détails d'un produit
            function displayProductDetails(product) {
                console.log("Affichage des détails du produit:", product); // Debugging

                let html = `
        <div class="product-info-section grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <div class="text-sm font-medium text-gray-500 mb-1">Code Barre</div>
                <div class="font-mono text-lg mb-3">${product.barcode || 'Non défini'}</div>
                
                <div class="text-sm font-medium text-gray-500 mb-1">Nom du Produit</div>
                <div class="font-semibold text-lg mb-3">${product.product_name}</div>
                
                <div class="text-sm font-medium text-gray-500 mb-1">Catégorie</div>
                <div class="mb-3"><span class="category-tag">${product.category_name || 'Non catégorisé'}</span></div>
            </div>
            
            <div>
                <div class="text-sm font-medium text-gray-500 mb-1">Quantité en Stock</div>
                <div class="font-bold text-2xl ${getQuantityColorClass(product.quantity)} mb-3">${product.quantity}</div>
                
                <div class="text-sm font-medium text-gray-500 mb-1">Quantité Réservée</div>
                <div class="font-medium mb-3">${product.quantity_reserved || 0}</div>
                
                <div class="text-sm font-medium text-gray-500 mb-1">Unité</div>
                <div class="mb-3">${product.unit || 'unité'}</div>
            </div>
        </div>
        
        <div class="product-info-section mt-6">
            <h3 class="font-semibold text-lg mb-3">État du Stock</h3>
            <div class="bg-gray-50 p-4 rounded-lg">
                ${generateStockStatusInfo(product.quantity)}
            </div>
        </div>
        
        <div class="mt-6">
            <h3 class="font-semibold text-lg mb-3">Actions Recommandées</h3>
            <div class="space-y-2">
                ${generateProductRecommendations(product.quantity)}
            </div>
        </div>
    `;

                $('#productModalBody').html(html);

                // Configurer le bouton pour voir l'historique
                $('#viewProductHistory').off('click').on('click', function () {
                    viewProductHistory(product.id, product.product_name);
                }).show();
            }

            // Fonction pour afficher l'historique simple d'un produit
            function displayProductHistory(history) {
                console.log("Affichage de l'historique du produit:", history); // Debugging

                if (!history || history.length === 0) {
                    $('#productModalBody').html(`
            <div class="text-center py-6">
                <span class="material-icons text-gray-400 text-5xl mb-2">history</span>
                <p class="text-gray-500">Aucun historique disponible pour ce produit.</p>
            </div>
        `);
                    return;
                }

                let html = `
        <div class="mb-4">
            <h3 class="font-semibold text-lg mb-3">Historique des Mouvements</h3>
            <div class="text-sm text-gray-500 mb-4">Affichage des ${history.length} derniers mouvements</div>
            
            <div class="timeline">
    `;

                history.forEach(entry => {
                    const isEntry = entry.movement_type === 'entry';
                    const dotColor = isEntry ? 'bg-green-500' : 'bg-red-500';
                    const date = new Date(entry.date);
                    const formattedDate = date.toLocaleDateString('fr-FR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    html += `
            <div class="timeline-item">
                <div class="timeline-dot ${dotColor}"></div>
                <div class="timeline-content">
                    <div class="timeline-date">${formattedDate}</div>
                    <div class="timeline-title">
                        ${isEntry ? 'Entrée' : 'Sortie'} de ${entry.quantity} ${entry.unit || 'unité(s)'}
                    </div>
                    <div class="timeline-desc">
                        ${isEntry ? `Provenance: ${entry.provenance || 'Non spécifiée'}` : `Destination: ${entry.destination || 'Non spécifiée'}`}
                        ${entry.nom_projet ? `<br>Projet: ${entry.nom_projet}` : ''}
                        ${entry.demandeur && !isEntry ? `<br>Demandeur: ${entry.demandeur}` : ''}
                    </div>
                </div>
            </div>
        `;
                });

                html += `
            </div>
        </div>
    `;

                $('#productModalBody').html(html);

                // Modifier le bouton pour voir les détails
                $('#viewProductHistory').text('Voir détails').off('click').on('click', function () {
                    const productId = history.length > 0 ? history[0].product_id : 0;
                    const productName = productsTable ?
                        findProductInTable(productId)?.product_name || 'Produit' :
                        'Produit';

                    viewProductDetails(productId, productName);
                });
            }

            // Fonction pour simuler l'historique d'un produit si aucun n'est disponible
            function displaySimulatedProductHistory(productId) {
                console.log("Affichage de l'historique simulé pour le produit ID:", productId); // Debugging

                const product = findProductInTable(productId);
                if (!product) {
                    $('#productModalBody').html(`
            <div class="text-center py-6">
                <span class="material-icons text-gray-400 text-5xl mb-2">history</span>
                <p class="text-gray-500">Aucun historique disponible pour ce produit.</p>
            </div>
        `);
                    return;
                }

                // Créer un historique simulé
                const now = new Date();
                const history = [];

                // Ajouter quelques entrées simulées (plus anciennes)
                for (let i = 3; i >= 1; i--) {
                    const date = new Date(now);
                    date.setMonth(date.getMonth() - i);

                    history.push({
                        product_id: productId,
                        movement_type: 'entry',
                        quantity: Math.floor(Math.random() * 20) + 5,
                        unit: product.unit || 'unité',
                        provenance: 'Fournisseur',
                        nom_projet: `PRJ-${100 + Math.floor(Math.random() * 900)}`,
                        date: date.toISOString(),
                        formatted_date: date.toLocaleDateString('fr-FR', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        }),
                        movement_type_display: 'Entrée',
                        movement_color: 'green'
                    });
                }

                // Ajouter quelques sorties simulées (plus récentes)
                for (let i = 2; i >= 0; i--) {
                    const date = new Date(now);
                    date.setDate(date.getDate() - i * 7);

                    history.push({
                        product_id: productId,
                        movement_type: 'output',
                        quantity: Math.floor(Math.random() * 5) + 1,
                        unit: product.unit || 'unité',
                        destination: 'Service Production',
                        demandeur: 'Technicien',
                        nom_projet: `PRJ-${100 + Math.floor(Math.random() * 900)}`,
                        date: date.toISOString(),
                        formatted_date: date.toLocaleDateString('fr-FR', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        }),
                        movement_type_display: 'Sortie',
                        movement_color: 'red'
                    });
                }

                // Trier par date (plus récent en premier)
                history.sort((a, b) => new Date(b.date) - new Date(a.date));

                // Calculer des statistiques
                const stats = {
                    total_entries: history.filter(entry => entry.movement_type === 'entry')
                        .reduce((sum, entry) => sum + entry.quantity, 0),
                    total_outputs: history.filter(entry => entry.movement_type === 'output')
                        .reduce((sum, entry) => sum + entry.quantity, 0),
                    last_movement: history[0]?.formatted_date || 'N/A'
                };

                stats.net_change = stats.total_entries - stats.total_outputs;

                // Afficher un historique simulé enrichi
                const data = {
                    history: history,
                    product: product,
                    stats: stats,
                    period: 'all'
                };

                displayEnhancedProductHistory(data);
            }

            // Fonction pour déterminer la classe de couleur en fonction de la quantité
            function getQuantityColorClass(quantity) {
                quantity = parseInt(quantity);
                if (quantity === 0) {
                    return 'text-red-600';
                } else if (quantity < 3) {
                    return 'text-yellow-600';
                } else if (quantity <= 10) {
                    return 'text-blue-600';
                } else {
                    return 'text-green-600';
                }
            }

            // Fonction pour générer les informations sur l'état du stock
            function generateStockStatusInfo(quantity) {
                quantity = parseInt(quantity);

                if (quantity === 0) {
                    return `
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <span class="material-icons text-red-500 mr-2">error</span>
                </div>
                <div>
                    <p class="font-medium text-red-600">RUPTURE DE STOCK</p>
                    <p class="text-gray-600 text-sm">Ce produit n'est plus disponible en stock.</p>
                </div>
            </div>
        `;
                } else if (quantity < 3) {
                    return `
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <span class="material-icons text-yellow-500 mr-2">warning</span>
                </div>
                <div>
                    <p class="font-medium text-yellow-600">STOCK CRITIQUE</p>
                    <p class="text-gray-600 text-sm">Stock très faible, réapprovisionnement urgent recommandé.</p>
                </div>
            </div>
        `;
                } else if (quantity <= 10) {
                    return `
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <span class="material-icons text-blue-500 mr-2">info</span>
                </div>
                <div>
                    <p class="font-medium text-blue-600">STOCK FAIBLE</p>
                    <p class="text-gray-600 text-sm">Le niveau de stock est bas, pensez à commander bientôt.</p>
                </div>
            </div>
        `;
                } else {
                    return `
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <span class="material-icons text-green-500 mr-2">check_circle</span>
                </div>
                <div>
                    <p class="font-medium text-green-600">STOCK NORMAL</p>
                    <p class="text-gray-600 text-sm">Le niveau de stock est suffisant.</p>
                </div>
            </div>
        `;
                }
            }

            // Fonction pour générer des recommandations en fonction de la quantité
            function generateProductRecommendations(quantity) {
                quantity = parseInt(quantity);

                let recommendations = '';

                if (quantity === 0) {
                    recommendations += `
            <div class="bg-red-50 p-3 rounded-lg border border-red-200">
                <div class="flex items-start">
                    <span class="material-icons text-red-500 mr-2">priority_high</span>
                    <div>
                        <p class="font-medium text-red-700">Commander immédiatement</p>
                        <p class="text-sm text-gray-700">Ce produit est en rupture de stock et doit être commandé de toute urgence.</p>
                    </div>
                </div>
            </div>
        `;
                } else if (quantity < 3) {
                    recommendations += `
            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                <div class="flex items-start">
                    <span class="material-icons text-yellow-500 mr-2">schedule</span>
                    <div>
                        <p class="font-medium text-yellow-700">Planifier une commande</p>
                        <p class="text-sm text-gray-700">Le stock est très bas, il est recommandé de passer une commande rapidement.</p>
                    </div>
                </div>
            </div>
        `;
                } else if (quantity <= 10) {
                    recommendations += `
            <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                <div class="flex items-start">
                    <span class="material-icons text-blue-500 mr-2">assignment</span>
                    <div>
                        <p class="font-medium text-blue-700">Préparer la commande</p>
                        <p class="text-sm text-gray-700">Le stock est bas, il serait judicieux de préparer une commande prochainement.</p>
                    </div>
                </div>
            </div>
        `;
                } else {
                    recommendations += `
            <div class="bg-green-50 p-3 rounded-lg border border-green-200">
                <div class="flex items-start">
                    <span class="material-icons text-green-500 mr-2">inventory</span>
                    <div>
                        <p class="font-medium text-green-700">Stock suffisant</p>
                        <p class="text-sm text-gray-700">Le niveau de stock est adéquat. Aucune action n'est requise pour le moment.</p>
                    </div>
                </div>
            </div>
        `;
                }

                // Ajouter toujours une recommandation sur la vérification périodique
                recommendations += `
        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
            <div class="flex items-start">
                <span class="material-icons text-gray-500 mr-2">update</span>
                <div>
                    <p class="font-medium text-gray-700">Vérification périodique</p>
                    <p class="text-sm text-gray-700">Pensez à vérifier régulièrement l'état du stock pour anticiper les besoins futurs.</p>
                </div>
            </div>
        </div>
    `;

                return recommendations;
            }

            // Fonction pour afficher une erreur dans le modal produit
            function showProductError(message) {
                $('#productModalBody').html(`
        <div class="text-center py-6">
            <span class="material-icons text-red-400 text-5xl mb-2">error_outline</span>
            <p class="text-red-500 font-medium">${message}</p>
            <p class="text-gray-500 mt-2">Veuillez réessayer ultérieurement ou contacter l'administrateur système.</p>
        </div>
    `);

                // Masquer le bouton d'historique
                $('#viewProductHistory').hide();
            }

            // Gestionnaires pour les modales
            $('.close-modal, #closeProductModal').on('click', function () {
                $('#productDetailModal').css('display', 'none');
            });

            // Fermer les modales en cliquant en dehors
            $(window).on('click', function (event) {
                if (event.target == document.getElementById('productDetailModal')) {
                    $('#productDetailModal').css('display', 'none');
                }
            });

        });

    </script>
</body>

</html>