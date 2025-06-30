<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Rediriger vers index.php
    header("Location: ./../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Retours - DYM BE</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- DataTable CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    
    <!-- DataTable JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    
    <!-- Bibliothèques pour les exports -->
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f3f4f6;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge .material-icons {
            font-size: 0.875rem;
            margin-right: 0.25rem;
        }

        .badge-success {
            background-color: #d1fae5;
            color: #047857;
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #d97706;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #dc2626;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        /* Animation pour les cartes de projet */
        @keyframes slideInFromBottom {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-in {
            animation: slideInFromBottom 0.3s ease-out forwards;
        }

        /* Animation pour les quantités */
        @keyframes pulseHighlight {

            0%,
            100% {
                background-color: transparent;
            }

            50% {
                background-color: rgba(147, 197, 253, 0.3);
            }
        }

        .pulse-highlight {
            animation: pulseHighlight 1s ease-in-out;
        }

        /* Styles pour les cartes de projet */
        .project-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .project-card.active {
            border-left-color: #4F46E5;
        }

        /* Styles spécifiques pour les contrôles de quantité */
        .quantity-controls {
            display: flex;
            align-items: center;
        }

        .qty-btn {
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #F1F5F9;
            border: 1px solid #E2E8F0;
            color: #475569;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .qty-btn:hover {
            background-color: #E2E8F0;
            color: #1E293B;
        }

        .qty-btn:first-child {
            border-top-left-radius: 0.375rem;
            border-bottom-left-radius: 0.375rem;
        }

        .qty-btn:last-child {
            border-top-right-radius: 0.375rem;
            border-bottom-right-radius: 0.375rem;
        }

        .return-quantity {
            width: 3rem;
            text-align: center;
            border-top: 1px solid #E2E8F0;
            border-bottom: 1px solid #E2E8F0;
            border-left: none;
            border-right: none;
            height: 2rem;
            font-size: 0.875rem;
        }

        /* Input sans flèches numériques */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }

        /* Styles pour les états vides */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
        }

        .empty-state-icon {
            font-size: 3rem;
            color: #E5E7EB;
            margin-bottom: 1rem;
        }

        /* Flottant avec résumé des retours */
        .return-summary {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background-color: white;
            border-radius: 0.75rem;
            padding: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(150%);
            transition: transform 0.3s ease-out;
            z-index: 50;
            max-width: 20rem;
        }

        .return-summary.visible {
            transform: translateY(0);
        }

        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #E2E8F0;
        }

        .summary-title {
            font-weight: 600;
            color: #1E293B;
            font-size: 0.875rem;
        }

        .summary-close {
            color: #64748B;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .summary-close:hover {
            color: #1E293B;
        }

        .summary-content {
            margin-bottom: 0.75rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.813rem;
        }

        .summary-item-name {
            color: #475569;
            margin-right: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .summary-item-quantity {
            font-weight: 600;
            color: #1E293B;
        }

        .summary-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.5rem;
            border-top: 1px solid #E2E8F0;
        }

        .summary-total {
            font-weight: 600;
            color: #1E293B;
        }

        /* Styles pour les boutons DataTables */
        .dt-buttons {
            margin-bottom: 1rem;
        }

        .dt-button {
            margin-right: 0.5rem !important;
            padding: 0.375rem 0.75rem !important;
            font-size: 0.875rem !important;
            border-radius: 0.375rem !important;
            border: 1px solid #d1d5db !important;
            background-color: white !important;
            color: #374151 !important;
            transition: all 0.2s ease !important;
        }

        .dt-button:hover {
            background-color: #f9fafb !important;
            border-color: #9ca3af !important;
        }

        .dt-button.dt-button-active {
            background-color: #4f46e5 !important;
            color: white !important;
            border-color: #4f46e5 !important;
        }

        /* Amélioration de l'affichage du tableau */
        .dataTables_wrapper .dataTables_info {
            padding-top: 0.75rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .dataTables_wrapper .dataTables_paginate {
            padding-top: 0.75rem;
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            border: 1px solid #d1d5db;
            font-size: 0.875rem;
        }

        .dataTables_wrapper .dataTables_filter input {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            border: 1px solid #d1d5db;
            font-size: 0.875rem;
        }

        /* Styles pour les messages vides personnalisés */
        .dt-empty-message {
            margin-top: 1rem;
            margin-bottom: 1rem;
        }

        /* Masquer les messages par défaut de DataTables */
        .dataTables_empty {
            display: none !important;
        }

        /* Améliorer l'espacement du wrapper DataTables */
        .dataTables_wrapper {
            margin-bottom: 1rem;
        }

        /* Styles pour le message "Aucun enregistrement" par défaut */
        .dataTables_wrapper .dataTables_empty {
            padding: 2rem;
            text-align: center;
            background-color: #f9fafb;
            color: #6b7280;
            border-radius: 0.5rem;
        }

        /* Styles pour les badges de mouvement améliorés */
        .movement-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .movement-badge .fas {
            font-size: 0.7rem;
            margin-right: 0.25rem;
        }

        .badge-input {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-output {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .badge-adjustment {
            background-color: #dbeafe;
            color: #1d4ed8;
        }

        .badge-return {
            background-color: #f3e8ff;
            color: #7c3aed;
        }

        /* Amélioration des filtres */
        .filter-container {
            position: relative;
        }

        .filter-container select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
        }

        /* Styles pour le modal amélioré */
        .movement-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .movement-detail-label {
            font-weight: 600;
            color: #374151;
        }

        .movement-detail-value {
            color: #6b7280;
            text-align: right;
        }

        /* Animation pour les statistiques */
        @keyframes countUp {
            from {
                transform: scale(0.8);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .stat-animate {
            animation: countUp 0.3s ease-out;
        }

        /* Styles pour les tooltips */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #1f2937;
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.75rem;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../components/navbar.php'; ?>

        <div class="container mx-auto px-4 py-6">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-wrap justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 my-2">
                        <i class="fas fa-exchange-alt text-indigo-600 mr-2"></i>
                        Gestion des Retours de Matériel
                    </h1>
                    <div class="text-sm text-gray-500 flex items-center">
                        <i class="far fa-calendar-alt mr-2"></i>
                        <span id="current-date"></span>
                    </div>
                </div>

                <div class="flex flex-wrap justify-between items-center mb-4">
                    <div class="mb-3">
                        <a href="completed_projects.php"
                            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg shadow-sm transition-colors">
                            <i class="fas fa-check-circle mr-2"></i>
                            Voir les projets terminés
                        </a>
                    </div>
                    <div class="text-sm text-gray-500">
                        <span class="block bg-blue-100 px-3 py-1 rounded-full">
                            <i class="fas fa-info-circle mr-1"></i>
                            Les projets terminés sont archivés et consultables à tout moment
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Colonne de gauche: Liste des projets -->
                    <div class="lg:col-span-1">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg font-semibold text-gray-700">Mes Projets</h2>
                                <div class="text-sm text-indigo-600 flex items-center">
                                    <span id="project-count">0 projets</span>
                                </div>
                            </div>

                            <div class="relative mb-4">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input type="text" id="project-search"
                                    class="bg-white w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="Rechercher un projet...">
                            </div>

                            <div id="projects-list" class="overflow-y-auto max-h-96 space-y-3">
                                <!-- Les projets seront chargés ici dynamiquement -->
                                <div class="text-center py-4 text-gray-500 text-sm">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>
                                    Chargement des projets...
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne centrale et droite: Détails du projet et produits réservés -->
                    <div class="lg:col-span-2">
                        <div id="project-details" class="bg-gray-50 rounded-lg p-4 mb-6 hidden">
                            <!-- Les détails du projet sélectionné seront affichés ici -->
                        </div>

                        <div id="reserved-products-section" class="bg-white rounded-lg shadow-md p-4 hidden">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg font-semibold text-gray-700 mr-2">Produits Réservés</h2>
                                <div class="flex space-x-4">
                                    <div
                                        class="return-type-selector bg-white rounded-lg flex shadow border border-gray-200">
                                        <button
                                            class="return-type-option px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center active bg-indigo-600 text-white"
                                            data-type="unused">
                                            <i class="fas fa-box-open mr-1.5"></i>
                                            Non utilisés
                                        </button>
                                        <button
                                            class="return-type-option px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center"
                                            data-type="partial">
                                            <i class="fas fa-exchange-alt mr-1.5"></i>
                                            Retour partiel
                                        </button>
                                    </div>
                                    <button id="select-all-btn"
                                        class="text-indigo-600 text-sm font-medium hover:text-indigo-800">
                                        <i class="fas fa-check-square mr-1"></i>
                                        Tout sélectionner
                                    </button>
                                </div>
                            </div>

                            <div id="reserved-products" class="space-y-4">
                                <!-- Les produits réservés seront affichés ici -->
                            </div>

                            <div id="no-products-message" class="hidden py-6 text-center text-gray-500">
                                <i class="fas fa-box-open text-gray-300 text-4xl mb-2"></i>
                                <p>Aucun produit disponible pour le retour</p>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button id="submit-returns"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg shadow-sm flex items-center transition-all duration-200">
                                    <i class="fas fa-check mr-2"></i>
                                    Valider les retours
                                </button>
                            </div>
                        </div>

                        <div id="empty-state" class="empty-state bg-white rounded-lg shadow-md p-6">
                            <i class="fas fa-box-open empty-state-icon"></i>
                            <h3 class="text-lg font-medium mb-2">Sélectionnez un projet</h3>
                            <p class="text-gray-500 mb-4">Choisissez un projet dans la liste pour voir les produits
                                disponibles pour le retour</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historique des mouvements -->
            <div id="history-section" class="bg-white rounded-lg shadow-md p-6 hidden">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-700">
                        <i class="fas fa-history text-indigo-500 mr-2"></i>
                        Historique des Mouvements
                    </h2>
                    <div class="flex space-x-2">
                        <!-- Filtres avancés -->
                        <div class="relative">
                            <select id="movement-type-filter" class="pl-8 pr-4 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Tous les types</option>
                                <option value="input">Entrées</option>
                                <option value="output">Sorties</option>
                                <option value="adjustment">Ajustements</option>
                                <option value="return">Retours</option>
                            </select>
                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                <i class="fas fa-filter text-gray-400 text-sm"></i>
                            </div>
                        </div>

                        <!-- Filtre par période -->
                        <div class="relative">
                            <select id="period-filter" class="pl-8 pr-4 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Toutes les périodes</option>
                                <option value="today">Aujourd'hui</option>
                                <option value="week">Cette semaine</option>
                                <option value="month">Ce mois</option>
                                <option value="quarter">Ce trimestre</option>
                            </select>
                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                <i class="fas fa-calendar text-gray-400 text-sm"></i>
                            </div>
                        </div>

                        <!-- Bouton d'export -->
                        <button id="export-history" class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm">
                            <i class="fas fa-download mr-1"></i>
                            Exporter
                        </button>

                        <!-- Bouton de rafraîchissement -->
                        <button id="refresh-history" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-600">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- Statistiques rapides -->
                <div id="history-stats" class="grid grid-cols-4 gap-4 mb-4 hidden">
                    <div class="bg-green-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-green-600" id="stats-inputs">0</div>
                        <div class="text-xs text-green-700">Entrées</div>
                    </div>
                    <div class="bg-red-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-red-600" id="stats-outputs">0</div>
                        <div class="text-xs text-red-700">Sorties</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-blue-600" id="stats-adjustments">0</div>
                        <div class="text-xs text-blue-700">Ajustements</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-purple-600" id="stats-returns">0</div>
                        <div class="text-xs text-purple-700">Retours</div>
                    </div>
                </div>

                <!-- Tableau amélioré -->
                <div class="overflow-x-auto">
                    <table id="history-table" class="min-w-full divide-y divide-gray-200 display responsive nowrap">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date & Heure
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Produit
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Quantité
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Source
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Destination
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Utilisateur
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                                <!-- Colonnes cachées pour le filtrage -->
                                <th scope="col" style="display: none;">Type Filter</th>
                                <th scope="col" style="display: none;">Date Filter</th>
                            </tr>
                        </thead>
                        <tbody id="history-table-body" class="bg-white divide-y divide-gray-200">
                            <!-- L'historique sera chargé ici -->
                        </tbody>
                    </table>
                </div>

                <!-- États vides et de chargement -->
                <div id="history-empty" class="text-center py-8 hidden">
                    <i class="fas fa-history text-gray-300 text-4xl mb-2"></i>
                    <p class="text-gray-500">Aucun mouvement trouvé pour ce projet</p>
                    <button onclick="loadProjectHistory(currentProject.idExpression)"
                        class="mt-2 px-3 py-1 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                        Actualiser
                    </button>
                </div>

                <div id="history-loading" class="text-center py-8 hidden">
                    <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-indigo-500 border-r-transparent"></div>
                    <p class="text-gray-500 mt-2">Chargement de l'historique...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Flottant avec résumé des retours -->
    <div id="return-summary" class="return-summary">
        <div class="summary-header">
            <div class="summary-title">Résumé des retours</div>
            <div class="summary-close" onclick="toggleReturnSummary(false)">
                <i class="fas fa-times"></i>
            </div>
        </div>
        <div class="summary-content" id="summary-content">
            <!-- Les éléments du résumé seront ajoutés ici -->
        </div>
        <div class="summary-footer">
            <div class="summary-total">
                Total: <span id="summary-total">0</span> produit(s)
            </div>
            <button id="submit-from-summary"
                class="text-sm bg-indigo-600 hover:bg-indigo-700 text-white py-1 px-3 rounded">
                Valider
            </button>
        </div>
    </div>

    <!-- Modal pour les détails des mouvements -->
    <div id="movement-details-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900" id="modal-title">
                        Détails du mouvement
                    </h3>
                    <button onclick="closeMovementModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="modal-content" class="p-6">
                    <!-- Le contenu sera chargé dynamiquement -->
                </div>
            </div>
        </div>
    </div>

<!-- Script JavaScript externe -->
<script src="assets/js/project_return.js"></script>
</body>
</body>

</html>