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
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/select/1.3.4/css/select.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        .badge-dispatch {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .badge-return {
            background-color: #fff0f6;
            color: #eb2f96;
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
            text-decoration: none;
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

        /* Modal styles */
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

        .close,
        .close-modal {
            color: #64748b;
            font-size: 24px;
            font-weight: 700;
            cursor: pointer;
        }

        .close:hover,
        .close:focus,
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

        /* Styles spécifiques pour la section entrées/sorties */
        .swal-movement-details {
            z-index: 9999;
        }

        #movements-table tbody tr {
            transition: background-color 0.2s ease;
        }

        #movements-table tbody tr:hover {
            background-color: #f8fafc !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-item {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../components/navbar_finance.php'; ?>

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

                    <!-- Section de statistiques de stock -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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

                        <div id="all-products" class="tab-content active">
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
                                            <option value="low">Stock faible (<3)</option>
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
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div id="low-stock" class="tab-content">
                            <div class="p-5">
                                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-5">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <span class="material-icons text-yellow-400">warning</span>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-yellow-700">
                                                Ces produits ont un niveau de stock faible (moins de 10 unités).
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
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div id="out-of-stock" class="tab-content">
                            <div class="p-5">
                                <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-5">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <span class="material-icons text-red-400">error</span>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-red-700">
                                                Ces produits sont en rupture de stock (quantité = 0).
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
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenu Entrées/Sorties -->
                <div id="entrees-sorties-content" style="display: none;"></div>

                <!-- Contenu Tableau de Bord -->
                <div id="tableau-bord-content" style="display: none;">
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

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div class="card-container lg:col-span-2">
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

                        <div class="card-container">
                            <div class="section-header">
                                <h3 class="section-title flex items-center">
                                    <span class="material-icons mr-2" style="font-size: 20px;">star</span>
                                    Top Produits en Stock
                                </h3>
                            </div>
                            <div class="p-4">
                                <div id="top-products-list" class="space-y-4">
                                    <div class="animate-pulse">
                                        <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-container mb-6">
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
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
                                    <div class="animate-pulse">
                                        <div class="h-4 bg-red-100 rounded w-full mb-2"></div>
                                        <div class="h-3 bg-red-50 rounded w-3/4"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

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

    <!-- Modals -->
    <div id="invoicePreviewModal" class="invoice-modal">
        <div class="invoice-modal-content">
            <span class="close">&times;</span>
            <h2 id="invoiceModalTitle" class="text-xl font-semibold mb-4">Prévisualisation de la facture</h2>
            <div id="invoiceModalContent" class="flex justify-content-center mt-4"></div>
            <div class="mt-4 flex justify-end">
                <button id="invoiceDownloadBtn" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
                    Télécharger
                </button>
            </div>
        </div>
    </div>

    <div id="productDetailModal" class="product-modal">
        <div class="product-modal-content">
            <div class="product-modal-header">
                <h2 id="productModalTitle" class="product-modal-title">Détails du Produit</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div id="productModalBody" class="product-modal-body">
                <div class="animate-pulse">
                    <div class="h-6 bg-gray-200 rounded w-1/4 mb-4"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/2 mb-2"></div>
                    <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/3 mb-4"></div>
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/select/1.3.4/js/dataTables.select.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        $(document).ready(function () {
            // ===== VARIABLES GLOBALES =====
            let productsTable;
            let lowStockTable;
            let outOfStockTable;
            let entriesSortiesTable;
            let projectsLoaded = false;
            let chartInstances = {};

            // ===== FONCTIONS UTILITAIRES =====
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

            // ===== INITIALISATION =====
            updateDateTime();
            setInterval(updateDateTime, 60000);

            initProductsTable();
            loadStockStatistics();

            // ===== GESTION DES ONGLETS STOCK =====
            $('.tab-button').on('click', function () {
                const tabId = $(this).data('tab');

                $('.tab-button').removeClass('active');
                $(this).addClass('active');

                $('.tab-content').removeClass('active').hide();
                $(`#${tabId}`).addClass('active').show();

                if (tabId === 'low-stock' && !lowStockTable) {
                    initLowStockTable();
                } else if (tabId === 'out-of-stock' && !outOfStockTable) {
                    initOutOfStockTable();
                }
            });

            // ===== GESTION DES BOUTONS PRINCIPAUX =====
            $('#btn-consulter-stock').on('click', function (e) {
                e.preventDefault();
                $('#stock-content').show();
                $('#entrees-sorties-content').hide();
                $('#tableau-bord-content').hide();
                updateButtonStyles($(this));

                if (productsTable) {
                    productsTable.ajax.reload();
                    loadStockStatistics();
                }
            });

            $('#btn-entrees-sorties').on('click', function (e) {
                e.preventDefault();
                $('#entrees-sorties-content').html('<div class="text-center p-8"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-700"></div><p class="mt-2 text-gray-600">Chargement en cours...</p></div>').show();
                $('#stock-content').hide();
                $('#tableau-bord-content').hide();
                updateButtonStyles($(this));
                loadEntriesSortiesContent();
            });

            $('#btn-tableau-bord').on('click', function (e) {
                e.preventDefault();
                $('#tableau-bord-content').show();
                $('#stock-content').hide();
                $('#entrees-sorties-content').hide();
                updateButtonStyles($(this));
                initDashboard();
            });

            // ===== FONCTIONS DE GESTION DES BOUTONS =====
            function updateButtonStyles(activeButton) {
                $('#btn-consulter-stock, #btn-entrees-sorties, #btn-tableau-bord').removeClass('active').css({
                    'background-color': '',
                    'color': ''
                });

                activeButton.addClass('active').css({
                    'background-color': '#ebf7ee',
                    'color': '#10b981'
                });
            }

            // ===== FONCTIONS POUR LES TABLEAUX DE STOCK =====
            function initProductsTable() {
                productsTable = $('#products-table').DataTable({
                    responsive: true,
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'api/stock/get_filtered_products.php',
                        data: function (d) {
                            d.product_name = $('#filter-product-name').val();
                            d.category = $('#filter-category').val();
                            d.unit = $('#filter-unit').val();
                            d.stock_level = $('#filter-stock-level').val();
                        },
                        error: function (xhr, status, error) {
                            console.error("Erreur AJAX:", status, error);
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
                        }
                    ],
                    order: [[1, 'asc']],
                    pageLength: 15,
                    lengthMenu: [15, 25, 50, 100],
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                    }
                });

                $('#products-table tbody').on('click', 'tr', function (e) {
                    if (!$(e.target).closest('.action-button').length) {
                        const data = productsTable.row(this).data();
                        if (data && data.id) {
                            viewProductDetails(data.id, data.product_name);
                        }
                    }
                });
            }

            function initLowStockTable() {
                lowStockTable = $('#low-stock-table').DataTable({
                    responsive: true,
                    processing: true,
                    ajax: {
                        url: 'api/stock/get_low_stock.php',
                        dataSrc: '',
                        error: function (xhr, status, error) {
                            console.error("Erreur AJAX:", status, error);
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
                    order: [[3, 'asc']],
                    pageLength: 10,
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                    }
                });
            }

            function initOutOfStockTable() {
                outOfStockTable = $('#out-of-stock-table').DataTable({
                    responsive: true,
                    processing: true,
                    ajax: {
                        url: 'api/stock/get_out_of_stock.php',
                        dataSrc: '',
                        error: function (xhr, status, error) {
                            console.error("Erreur AJAX:", status, error);
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
                    order: [[1, 'asc']],
                    pageLength: 10,
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                    }
                });
            }

            // ===== FONCTIONS POUR ENTRÉES/SORTIES =====
            function loadEntriesSortiesContent() {
                let entriesSortiesHTML = `
                    <div class="mb-6 flex justify-between items-center">
                        <div class="flex items-center">
                            <h2 class="text-2xl font-bold text-gray-800">Entrées/Sorties</h2>
                            <span class="ml-2 badge badge-blue">Mouvements</span>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button id="btn-refresh-movements"
                                class="bg-blue-500 text-white px-3 py-2 rounded-md flex items-center hover:bg-blue-600 transition-colors">
                                <span class="material-icons mr-1" style="font-size: 18px;">refresh</span>
                                Actualiser
                            </button>
                            <button id="btn-export-movements-excel"
                                class="bg-green-500 text-white px-3 py-2 rounded-md flex items-center hover:bg-green-600 transition-colors">
                                <span class="material-icons mr-1" style="font-size: 18px;">file_download</span>
                                Excel
                            </button>
                            <button id="btn-export-movements-pdf"
                                class="bg-red-500 text-white px-3 py-2 rounded-md flex items-center hover:bg-red-600 transition-colors">
                                <span class="material-icons mr-1" style="font-size: 18px;">picture_as_pdf</span>
                                PDF
                            </button>
                        </div>
                    </div>

                    <div class="card-container">
                        <div class="filter-section">
                            <div class="section-header mb-3">
                                <h3 class="section-title flex items-center">
                                    <span class="material-icons mr-2" style="font-size: 20px;">filter_alt</span>
                                    Filtres des Mouvements
                                </h3>
                            </div>

                            <div class="filter-group">
                                <div class="filter-item">
                                    <label class="filter-label">Recherche globale</label>
                                    <input type="text" id="filter-movement-search" class="filter-input"
                                        placeholder="Rechercher un mouvement, produit, projet...">
                                </div>

                                <div class="filter-item">
                                    <label class="filter-label">Type de mouvement</label>
                                    <select id="filter-movement-type" class="filter-input">
                                        <option value="">Tous les types</option>
                                        <option value="entry">Entrée</option>
                                        <option value="output">Sortie</option>
                                        <option value="supplier-return">Retour fournisseur</option>
                                        <option value="dispatch">Dispatching</option>
                                    </select>
                                </div>

                                <div class="filter-item">
                                    <label class="filter-label">Projet</label>
                                    <select id="filter-movement-project" class="filter-input">
                                        <option value="">Tous les projets</option>
                                    </select>
                                </div>

                                <div class="filter-item">
                                    <label class="filter-label">Période</label>
                                    <select id="filter-movement-period" class="filter-input">
                                        <option value="">Toutes les périodes</option>
                                        <option value="today">Aujourd'hui</option>
                                        <option value="week">Cette semaine</option>
                                        <option value="month">Ce mois</option>
                                        <option value="quarter">Ce trimestre</option>
                                        <option value="year">Cette année</option>
                                    </select>
                                </div>

                                <div class="filter-item mt-auto">
                                    <button id="btn-apply-movement-filters" class="btn-filter">
                                        <span class="material-icons" style="font-size: 18px;">search</span>
                                        Appliquer
                                    </button>
                                </div>

                                <div class="filter-item mt-auto">
                                    <button id="btn-reset-movement-filters" class="btn-filter btn-reset">
                                        <span class="material-icons" style="font-size: 18px;">refresh</span>
                                        Réinitialiser
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="p-4">
                            <table id="movements-table" class="min-w-full">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Produit</th>
                                        <th>Quantité</th>
                                        <th>Type</th>
                                        <th>Provenance</th>
                                        <th>Projet</th>
                                        <th>Destination</th>
                                        <th>Demandeur</th>
                                        <th>Date</th>
                                        <th>Facture</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                `;

                $('#entrees-sorties-content').html(entriesSortiesHTML);

                if (!projectsLoaded) {
                    loadProjectsForMovements();
                    projectsLoaded = true;
                }
                
                initMovementsDataTable();
                initMovementsEvents();
            }

            function loadProjectsForMovements() {
                $.ajax({
                    url: 'api/stock/api_getProjects.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.success && response.projects) {
                            let projectSelect = $('#filter-movement-project');
                            projectSelect.empty();
                            projectSelect.append('<option value="">Tous les projets</option>');

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
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erreur lors du chargement des projets:', error);
                    }
                });
            }

            function initMovementsDataTable() {
                entriesSortiesTable = $('#movements-table').DataTable({
                    responsive: true,
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'api/stock/api_getStockMovements.php',
                        type: 'GET',
                        data: function (d) {
                            d.search = $('#filter-movement-search').val();
                            d.movement_type = $('#filter-movement-type').val();
                            d.project_code = $('#filter-movement-project').val();
                            d.period = $('#filter-movement-period').val();
                            
                            d.page = Math.floor(d.start / d.length) + 1;
                            d.limit = d.length;
                        },
                        error: function (xhr, status, error) {
                            console.error("Erreur AJAX:", status, error);
                            
                            Toastify({
                                text: "Erreur lors du chargement des mouvements",
                                duration: 3000,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "#ef4444",
                            }).showToast();
                        }
                    },
                    columns: [
                        {
                            data: 'id',
                            className: 'text-center',
                            width: '60px'
                        },
                        {
                            data: 'product_name',
                            render: function (data, type, row) {
                                return `<div class="font-medium text-gray-900">${data}</div>`;
                            }
                        },
                        {
                            data: 'quantity',
                            className: 'text-right',
                            render: function (data, type, row) {
                                return `<span class="font-semibold">${data}</span>`;
                            }
                        },
                        {
                            data: 'movement_type',
                            className: 'text-center',
                            render: function (data, type, row) {
                                let badgeClass, movementTypeDisplay;

                                if (data === 'entry') {
                                    badgeClass = 'badge-entry';
                                    movementTypeDisplay = 'Entrée';
                                } else if (data === 'output') {
                                    if (row.destination && row.destination.startsWith('Retour fournisseur:')) {
                                        badgeClass = 'badge-return';
                                        movementTypeDisplay = 'Retour';
                                    } else {
                                        badgeClass = 'badge-output';
                                        movementTypeDisplay = 'Sortie';
                                    }
                                } else if (data === 'dispatch') {
                                    badgeClass = 'badge-dispatch';
                                    movementTypeDisplay = 'Dispatch';
                                }

                                return `<span class="badge ${badgeClass}">${movementTypeDisplay}</span>`;
                            }
                        },
                        {
                            data: 'provenance',
                            render: function (data, type, row) {
                                return data || '-';
                            }
                        },
                        {
                            data: 'nom_projet',
                            render: function (data, type, row) {
                                if (data) {
                                    return `<span class="font-medium text-blue-600">${data}</span>`;
                                }
                                return '-';
                            }
                        },
                        {
                            data: 'destination',
                            render: function (data, type, row) {
                                const isOutput = row.movement_type === 'output';
                                const isStockGeneral = row.movement_type === 'entry' && 
                                    (data === 'Stock général' || row.nom_projet === 'Stock général');
                                
                                if (isOutput || isStockGeneral) {
                                    return data || '-';
                                }
                                return `<span class="text-gray-300">-</span>`;
                            }
                        },
                        {
                            data: 'demandeur',
                            render: function (data, type, row) {
                                const isOutput = row.movement_type === 'output';
                                
                                if (isOutput) {
                                    return data || '-';
                                }
                                return `<span class="text-gray-300">-</span>`;
                            }
                        },
                        {
                            data: 'date',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'sort' || type === 'type') {
                                    return data;
                                }
                                
                                const date = new Date(data);
                                return date.toLocaleDateString('fr-FR', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                            }
                        },
                        {
                            data: 'invoice_id',
                            className: 'text-center',
                            orderable: false,
                            render: function (data, type, row) {
                                if (row.movement_type === 'entry' && data) {
                                    return `
                                        <button onclick="previewInvoice(${data})" 
                                            class="text-blue-600 hover:text-blue-800 flex items-center justify-center mx-auto">
                                            <span class="material-icons text-sm mr-1">description</span>
                                            Facture #${data}
                                        </button>
                                    `;
                                }
                                return '-';
                            }
                        },
                        {
                            data: null,
                            className: 'text-center',
                            orderable: false,
                            render: function (data, type, row) {
                                let actions = '';
                                
                                actions += `
                                    <button class="action-button view" onclick="viewMovementDetails(${row.id})">
                                        <span class="material-icons" style="font-size: 16px;">visibility</span>
                                        Détails
                                    </button>
                                `;
                                
                                if (row.movement_type === 'output' && row.destination && 
                                    row.destination.startsWith('Retour fournisseur:')) {
                                    actions += `
                                        <button class="action-button history ml-2" onclick="viewReturnDetails(${row.id})">
                                            <span class="material-icons" style="font-size: 16px;">info</span>
                                            Retour
                                        </button>
                                    `;
                                }
                                
                                return `<div class="flex justify-center space-x-1">${actions}</div>`;
                            }
                        }
                    ],
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'excel',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle;">file_download</span> Excel',
                            className: 'btn-filter',
                            title: 'Mouvements_Stock',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                            }
                        },
                        {
                            extend: 'pdf',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle;">picture_as_pdf</span> PDF',
                            className: 'btn-filter',
                            title: 'Mouvements_Stock',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                            },
                            orientation: 'landscape',
                            pageSize: 'A4'
                        }
                    ],
                    order: [[8, 'desc']],
                    pageLength: 15,
                    lengthMenu: [10, 15, 25, 50, 100],
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                    }
                });
            }

            function initMovementsEvents() {
                $('#btn-apply-movement-filters').on('click', function () {
                    applyMovementFilters();
                });

                $('#btn-reset-movement-filters').on('click', function () {
                    resetMovementFilters();
                });

                $('#filter-movement-search').on('input', debounce(function () {
                    applyMovementFilters();
                }, 500));

                $('#filter-movement-type, #filter-movement-project, #filter-movement-period').on('change', function () {
                    applyMovementFilters();
                });

                $('#btn-refresh-movements').on('click', function () {
                    if (entriesSortiesTable) {
                        entriesSortiesTable.ajax.reload();
                        
                        Toastify({
                            text: "Les données des mouvements ont été actualisées",
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#10b981",
                        }).showToast();
                    }
                });

                $('#btn-export-movements-excel').on('click', function () {
                    if (entriesSortiesTable) {
                        entriesSortiesTable.button('.buttons-excel').trigger();
                    }
                });

                $('#btn-export-movements-pdf').on('click', function () {
                    if (entriesSortiesTable) {
                        entriesSortiesTable.button('.buttons-pdf').trigger();
                    }
                });
            }

            function applyMovementFilters() {
                if (entriesSortiesTable) {
                    entriesSortiesTable.draw();
                }
            }

            function resetMovementFilters() {
                $('#filter-movement-search').val('');
                $('#filter-movement-type').val('');
                $('#filter-movement-project').val('');
                $('#filter-movement-period').val('');
                
                applyMovementFilters();
            }

            // ===== FONCTIONS GLOBALES =====
            window.viewMovementDetails = function(movementId) {
                $.ajax({
                    url: `api/stock/get_movement_details.php?id=${movementId}`,
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            displayMovementDetails(data.movement);
                        } else {
                            Toastify({
                                text: "Impossible de charger les détails du mouvement",
                                duration: 3000,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "#ef4444",
                            }).showToast();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur:', error);
                        Toastify({
                            text: "Erreur lors du chargement des détails",
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#ef4444",
                        }).showToast();
                    }
                });
            };

            function displayMovementDetails(movement) {
                const modalContent = `
                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <h3 class="text-lg font-semibold mb-3">Détails du mouvement #${movement.id}</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="mb-2"><strong>Produit:</strong> ${movement.product_name}</p>
                                <p class="mb-2"><strong>Quantité:</strong> ${movement.quantity}</p>
                                <p class="mb-2"><strong>Type:</strong> ${getMovementTypeDisplay(movement.movement_type)}</p>
                                <p class="mb-2"><strong>Date:</strong> ${new Date(movement.date).toLocaleString('fr-FR')}</p>
                            </div>
                            
                            <div>
                                <p class="mb-2"><strong>Provenance:</strong> ${movement.provenance || '-'}</p>
                                <p class="mb-2"><strong>Destination:</strong> ${movement.destination || '-'}</p>
                                <p class="mb-2"><strong>Projet:</strong> ${movement.nom_projet || '-'}</p>
                                <p class="mb-2"><strong>Demandeur:</strong> ${movement.demandeur || '-'}</p>
                            </div>
                        </div>
                        
                        ${movement.notes ? `
                            <div class="mt-4">
                                <p class="mb-2"><strong>Notes:</strong></p>
                                <p class="bg-white p-3 rounded border">${movement.notes}</p>
                            </div>
                        ` : ''}
                    </div>
                `;
                
                Swal.fire({
                    title: 'Détails du mouvement',
                    html: modalContent,
                    width: '800px',
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        container: 'swal-movement-details'
                    }
                });
            }

            function getMovementTypeDisplay(type) {
                switch(type) {
                    case 'entry': return 'Entrée';
                    case 'output': return 'Sortie';
                    case 'dispatch': return 'Dispatching';
                    default: return type;
                }
            }

            // ===== AUTRES FONCTIONS =====
            function loadStockStatistics() {
                $.ajax({
                    url: 'api/stock/get_stock_stats.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
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
                    }
                });
            }

            function initDashboard() {
                // Code pour le tableau de bord...
                console.log("Initialisation du tableau de bord");
            }

            // Gestion des filtres
            $('#btn-apply-filters').on('click', function () {
                if (productsTable) {
                    productsTable.draw();
                }
            });

            $('#btn-reset-filters').on('click', function () {
                $('#filter-unit').val('');
                $('#filter-stock-level').val('');
                $('#filter-category').val('');
                $('#filter-product-name').val('');
                if (productsTable) {
                    productsTable.draw();
                }
            });

            // Gestion des exports
            $('#btn-export-excel').on('click', function () {
                if (productsTable) {
                    productsTable.button('.buttons-excel').trigger();
                }
            });

            $('#btn-export-pdf').on('click', function () {
                window.location.href = 'api/stock/export_pdf.php';
            });

            $('#btn-refresh-stock').on('click', function () {
                if (productsTable) {
                    productsTable.ajax.reload();
                }
                loadStockStatistics();

                Toastify({
                    text: "Les données du stock ont été actualisées",
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#10b981",
                    stopOnFocus: true
                }).showToast();
            });

            // Fonctions globales pour les produits
            window.viewProductDetails = function(productId, productName) {
                console.log("Voir détails produit:", productId, productName);
            };

            window.viewProductHistory = function(productId, productName) {
                console.log("Voir historique produit:", productId, productName);
            };

            window.viewReturnDetails = function(movementId) {
                console.log("Voir détails retour:", movementId);
            };

            window.previewInvoice = function(invoiceId) {
                console.log("Prévisualiser facture:", invoiceId);
            };

            // Fermeture des modals
            $('.close, .close-modal').on('click', function () {
                $('#invoicePreviewModal').css('display', 'none');
                $('#productDetailModal').css('display', 'none');
            });

            $(window).on('click', function (event) {
                if (event.target == document.getElementById('invoicePreviewModal')) {
                    $('#invoicePreviewModal').css('display', 'none');
                }
                if (event.target == document.getElementById('productDetailModal')) {
                    $('#productDetailModal').css('display', 'none');
                }
            });
        });
    </script>
</body>

</html>