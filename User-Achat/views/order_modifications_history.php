<?php

/**
 * Page d'affichage de l'historique des modifications des commandes
 * 
 * @package DYM_MANUFACTURE
 * @subpackage User-Achat
 */

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Inclure les fichiers nécessaires
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_type'] ?? 'user';

// Statistiques rapides
try {
    // Nombre total de modifications
    $totalModificationsQuery = "SELECT COUNT(*) as total FROM order_modifications_history WHERE 1=1";
    if (function_exists('getFilteredDateCondition')) {
        $totalModificationsQuery .= " AND " . getFilteredDateCondition('modified_at');
    }
    $totalStmt = $pdo->prepare($totalModificationsQuery);
    $totalStmt->execute();
    $totalModifications = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Modifications ce mois-ci
    $thisMonthQuery = "SELECT COUNT(*) as total FROM order_modifications_history 
                      WHERE MONTH(modified_at) = MONTH(CURRENT_DATE()) 
                      AND YEAR(modified_at) = YEAR(CURRENT_DATE())";
    $thisMonthStmt = $pdo->prepare($thisMonthQuery);
    $thisMonthStmt->execute();
    $thisMonthModifications = $thisMonthStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Utilisateurs ayant effectué des modifications
    $usersQuery = "SELECT COUNT(DISTINCT modified_by) as total FROM order_modifications_history WHERE 1=1";
    if (function_exists('getFilteredDateCondition')) {
        $usersQuery .= " AND " . getFilteredDateCondition('modified_at');
    }
    $usersStmt = $pdo->prepare($usersQuery);
    $usersStmt->execute();
    $totalUsers = $usersStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
    $totalModifications = 0;
    $thisMonthModifications = 0;
    $totalUsers = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Modifications - DYM</title>

    <!-- Styles CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

    <style>
        /* Styles personnalisés pour la page d'historique */
        .history-card {
            transition: all 0.3s ease;
            border-left: 4px solid #3b82f6;
        }

        .history-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .stats-card {
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modification-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-quantity {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-price {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-supplier {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-payment {
            background-color: #f3e8ff;
            color: #7c3aed;
        }

        .badge-multiple {
            background-color: #e5e7eb;
            color: #374151;
        }

        .change-indicator {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
        }

        .change-old {
            color: #dc2626;
            text-decoration: line-through;
        }

        .change-new {
            color: #059669;
            font-weight: 600;
        }

        /* DataTables personnalisations */
        table.dataTable tbody tr:hover {
            background-color: #f8fafc;
        }

        .dt-buttons {
            margin-bottom: 1rem;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        /* Filtres avancés */
        .filters-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 0.5rem;
        }

        .filter-input {
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            transform: scale(1.02);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- En-tête de la page -->
            <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <div class="flex flex-wrap justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">
                            <span class="material-icons align-middle text-blue-600 mr-2">history</span>
                            Historique des Modifications
                        </h1>
                        <p class="text-gray-600">Suivi des modifications apportées aux commandes de matériaux</p>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Date et heure -->
                        <div class="flex items-center text-gray-600">
                            <span class="material-icons mr-2">event</span>
                            <span id="current-datetime"></span>
                        </div>

                        <!-- Bouton retour -->
                        <a href="../achats_materiaux.php"
                            class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
                            <span class="material-icons mr-2">arrow_back</span>
                            Retour aux achats
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistiques rapides -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="stats-card bg-white p-6 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Total des modifications</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= number_format($totalModifications) ?></h3>
                        </div>
                        <div class="rounded-full bg-blue-100 p-3">
                            <span class="material-icons text-blue-600">edit</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Toutes les modifications enregistrées</p>
                </div>

                <div class="stats-card bg-white p-6 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Ce mois-ci</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= number_format($thisMonthModifications) ?></h3>
                        </div>
                        <div class="rounded-full bg-green-100 p-3">
                            <span class="material-icons text-green-600">trending_up</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Modifications du mois en cours</p>
                </div>

                <div class="stats-card bg-white p-6 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Utilisateurs actifs</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= number_format($totalUsers) ?></h3>
                        </div>
                        <div class="rounded-full bg-purple-100 p-3">
                            <span class="material-icons text-purple-600">people</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Utilisateurs ayant effectué des modifications</p>
                </div>
            </div>

            <!-- Filtres avancés -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                        <span class="material-icons mr-2">filter_list</span>
                        Filtres de recherche
                    </h2>
                    <button id="reset-filters"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 transition">
                        <span class="material-icons mr-1">refresh</span>
                        Réinitialiser
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="filter-user" class="block text-sm font-medium text-gray-700 mb-1">
                            Utilisateur
                        </label>
                        <select id="filter-user" class="filter-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Tous les utilisateurs</option>
                        </select>
                    </div>

                    <div>
                        <label for="filter-type" class="block text-sm font-medium text-gray-700 mb-1">
                            Type de modification
                        </label>
                        <select id="filter-type" class="filter-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Tous les types</option>
                            <option value="quantity">Quantité</option>
                            <option value="price">Prix</option>
                            <option value="supplier">Fournisseur</option>
                            <option value="payment">Mode de paiement</option>
                            <option value="multiple">Multiple</option>
                        </select>
                    </div>

                    <div>
                        <label for="filter-date-from" class="block text-sm font-medium text-gray-700 mb-1">
                            Du
                        </label>
                        <input type="date" id="filter-date-from"
                            class="filter-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="filter-date-to" class="block text-sm font-medium text-gray-700 mb-1">
                            Au
                        </label>
                        <input type="date" id="filter-date-to"
                            class="filter-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mt-4">
                    <label for="filter-search" class="block text-sm font-medium text-gray-700 mb-1">
                        Recherche dans les commandes
                    </label>
                    <input type="text" id="filter-search" placeholder="Rechercher par désignation, expression ID, fournisseur..."
                        class="filter-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Tableau des modifications -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">
                        Historique des modifications
                    </h2>
                    <div class="flex space-x-2">
                        <button id="export-excel"
                            class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                            <span class="material-icons mr-2">file_download</span>
                            Exporter Excel
                        </button>
                        <button id="refresh-data"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                            <span class="material-icons mr-2">refresh</span>
                            Actualiser
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="modificationsTable" class="display responsive nowrap w-full">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Utilisateur</th>
                                <th>Commande</th>
                                <th>Projet / Client</th> <!-- Colonne renommée -->
                                <th>Type</th>
                                <th>Modifications</th>
                                <th>Raison</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Les données seront chargées dynamiquement -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <!-- Modal pour les détails de modification -->
    <div id="modification-details-modal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Détails de la modification</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div id="modification-details-content">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>

    <!-- Scripts JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Configuration globale
        const CONFIG = {
            API_URL: '../api/order_modifications/get_modifications_history.php',
            DETAILS_API_URL: '../api/order_modifications/get_modification_details.php',
            LANGUAGE_URL: "//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
        };

        // Variables globales
        let modificationsTable;
        let currentFilters = {};

        /**
         * Initialisation de l'application
         */
        $(document).ready(function() {
            initializeDateTime();
            initializeDataTable();
            initializeFilters();
            initializeEventHandlers();
            loadUsers();
        });

        /**
         * Mise à jour de la date et heure
         */
        function initializeDateTime() {
            function updateDateTime() {
                const now = new Date();
                document.getElementById('current-datetime').textContent =
                    now.toLocaleDateString('fr-FR') + ' ' + now.toLocaleTimeString('fr-FR');
            }

            updateDateTime();
            setInterval(updateDateTime, 1000);
        }

        /**
         * Initialisation du DataTable
         */
        function initializeDataTable() {
            modificationsTable = $('#modificationsTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                language: {
                    url: CONFIG.LANGUAGE_URL
                },
                ajax: {
                    url: CONFIG.API_URL,
                    type: 'POST',
                    data: function(d) {
                        // Ajouter les filtres personnalisés
                        d.user_filter = $('#filter-user').val();
                        d.type_filter = $('#filter-type').val();
                        d.date_from = $('#filter-date-from').val();
                        d.date_to = $('#filter-date-to').val();
                        d.search_filter = $('#filter-search').val();
                        return d;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('Erreur lors du chargement des données:', error);
                        Swal.fire({
                            title: 'Erreur',
                            text: 'Impossible de charger les données',
                            icon: 'error'
                        });
                    }
                },
                columns: [{
                        data: 'modified_at',
                        render: function(data) {
                            const date = new Date(data);
                            return date.toLocaleDateString('fr-FR') + '<br><small class="text-gray-500">' +
                                date.toLocaleTimeString('fr-FR') + '</small>';
                        }
                    },
                    {
                        data: 'user_name',
                        render: function(data) {
                            return `<div class="flex items-center">
                <span class="material-icons text-gray-400 mr-2">person</span>
                ${data || 'Utilisateur inconnu'}
            </div>`;
                        }
                    },
                    {
                        data: 'order_id',
                        render: function(data) {
                            return `<span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded">#${data}</span>`;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            // Affichage amélioré avec projet et client
                            let displayHtml = `<div class="space-y-1">`;

                            // Expression ID (plus petit)
                            displayHtml += `<div class="font-mono text-xs text-gray-500">${row.expression_id}</div>`;

                            // Nom du projet et client (principal)
                            if (row.project_display_name && row.project_display_name !== 'Projet non identifié') {
                                displayHtml += `<div class="font-medium text-sm text-blue-700">${row.project_display_name}</div>`;
                            } else {
                                displayHtml += `<div class="text-sm text-gray-600">Projet non identifié</div>`;
                            }

                            // Produit (si disponible)
                            if (row.product_designation) {
                                displayHtml += `<div class="text-xs text-gray-600 truncate" style="max-width: 200px;" title="${row.product_designation}">
                    <span class="material-icons text-xs mr-1">inventory</span>${row.product_designation}
                </div>`;
                            }

                            displayHtml += `</div>`;
                            return displayHtml;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return getModificationTypeBadge(row);
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return getModificationSummary(row);
                        }
                    },
                    {
                        data: 'modification_reason',
                        render: function(data) {
                            if (!data) return '<span class="text-gray-400">Aucune raison</span>';
                            return data.length > 50 ?
                                `<span title="${data}">${data.substring(0, 50)}...</span>` :
                                data;
                        }
                    },
                    {
                        data: 'id',
                        orderable: false,
                        render: function(data) {
                            return `<button onclick="viewModificationDetails(${data})" 
                           class="inline-flex items-center px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                <span class="material-icons text-sm mr-1">visibility</span>
                Détails
            </button>`;
                        }
                    }
                ],
                order: [
                    [0, 'desc']
                ], // Trier par date décroissante
                pageLength: 25,
                dom: 'Blfrtip',
                buttons: [{
                    extend: 'excel',
                    text: '<span class="material-icons">file_download</span> Excel',
                    className: 'btn btn-success',
                    title: 'Historique_Modifications_' + new Date().toISOString().split('T')[0]
                }],
                drawCallback: function() {
                    // Réattacher les événements après redessinage
                    attachTooltips();
                }
            });
        }

        /**
         * Initialisation des filtres
         */
        function initializeFilters() {
            // Gestionnaires d'événements pour les filtres
            $('#filter-user, #filter-type, #filter-date-from, #filter-date-to').on('change', function() {
                modificationsTable.ajax.reload();
            });

            // Recherche avec délai
            let searchTimeout;
            $('#filter-search').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    modificationsTable.ajax.reload();
                }, 500);
            });

            // Réinitialisation des filtres
            $('#reset-filters').on('click', function() {
                $('#filter-user, #filter-type, #filter-date-from, #filter-date-to, #filter-search').val('');
                modificationsTable.ajax.reload();
            });
        }

        /**
         * Initialisation des gestionnaires d'événements
         */
        function initializeEventHandlers() {
            // Bouton d'actualisation
            $('#refresh-data').on('click', function() {
                modificationsTable.ajax.reload(null, false);
                Swal.fire({
                    title: 'Actualisé!',
                    text: 'Les données ont été actualisées',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            });

            // Export Excel
            $('#export-excel').on('click', function() {
                modificationsTable.button('.buttons-excel').trigger();
            });

            // Fermeture de modal avec Escape
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });
        }

        /**
         * Chargement de la liste des utilisateurs
         */
        function loadUsers() {
            $.get('../api/order_modifications/get_users_list.php')
                .done(function(data) {
                    if (data.success) {
                        const select = $('#filter-user');
                        data.users.forEach(user => {
                            select.append(`<option value="${user.id}">${user.name}</option>`);
                        });
                    }
                })
                .fail(function() {
                    console.warn('Impossible de charger la liste des utilisateurs');
                });
        }

        /**
         * Détermine le type de modification et retourne le badge approprié
         */
        function getModificationTypeBadge(row) {
            const types = [];

            if (row.old_quantity !== row.new_quantity) types.push('quantity');
            if (row.old_price !== row.new_price) types.push('price');
            if (row.old_supplier !== row.new_supplier) types.push('supplier');
            if (row.old_payment_method !== row.new_payment_method) types.push('payment');

            if (types.length === 0) return '<span class="modification-badge badge-multiple">Autre</span>';
            if (types.length > 1) return '<span class="modification-badge badge-multiple">Multiple</span>';

            const type = types[0];
            const badges = {
                'quantity': '<span class="modification-badge badge-quantity">Quantité</span>',
                'price': '<span class="modification-badge badge-price">Prix</span>',
                'supplier': '<span class="modification-badge badge-supplier">Fournisseur</span>',
                'payment': '<span class="modification-badge badge-payment">Paiement</span>'
            };

            return badges[type] || '<span class="modification-badge badge-multiple">Autre</span>';
        }

        /**
         * Génère un résumé des modifications
         */
        function getModificationSummary(row) {
            const changes = [];

            if (row.old_quantity !== row.new_quantity) {
                changes.push(`<div class="mb-1">
                    <strong>Quantité:</strong> 
                    <span class="change-old">${row.old_quantity}</span> → 
                    <span class="change-new">${row.new_quantity}</span>
                </div>`);
            }

            if (row.old_price !== row.new_price) {
                changes.push(`<div class="mb-1">
                    <strong>Prix:</strong> 
                    <span class="change-old">${formatPrice(row.old_price)}</span> → 
                    <span class="change-new">${formatPrice(row.new_price)}</span>
                </div>`);
            }

            if (row.old_supplier !== row.new_supplier) {
                changes.push(`<div class="mb-1">
                    <strong>Fournisseur:</strong> 
                    <span class="change-old">${row.old_supplier || 'N/A'}</span> → 
                    <span class="change-new">${row.new_supplier || 'N/A'}</span>
                </div>`);
            }

            if (row.old_payment_method !== row.new_payment_method) {
                changes.push(`<div class="mb-1">
                    <strong>Paiement:</strong> 
                    <span class="change-old">${row.old_payment_method || 'N/A'}</span> → 
                    <span class="change-new">${row.new_payment_method || 'N/A'}</span>
                </div>`);
            }

            return changes.length > 0 ? changes.join('') : '<span class="text-gray-400">Aucune modification détectée</span>';
        }

        /**
         * Affichage des détails d'une modification
         */
        function viewModificationDetails(modificationId) {
            const modal = document.getElementById('modification-details-modal');
            const content = document.getElementById('modification-details-content');

            // Afficher le loader
            content.innerHTML = `
                <div class="flex justify-center items-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                    <span class="ml-3">Chargement des détails...</span>
                </div>
            `;

            modal.style.display = 'flex';

            // Charger les détails
            $.get(CONFIG.DETAILS_API_URL, {
                    id: modificationId
                })
                .done(function(data) {
                    if (data.success) {
                        content.innerHTML = buildModificationDetailsHTML(data.modification);
                    } else {
                        content.innerHTML = `<div class="text-red-600">Erreur: ${data.message}</div>`;
                    }
                })
                .fail(function() {
                    content.innerHTML = `<div class="text-red-600">Erreur lors du chargement des détails</div>`;
                });
        }

        /**
         * Construction du HTML pour les détails de modification
         */
        function buildModificationDetailsHTML(modification) {
            return `
                <div class="space-y-6">
                    <!-- Informations générales -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-semibold mb-3">Informations générales</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Commande ID</p>
                                <p class="font-medium">#${modification.order_id}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Expression ID</p>
                                <p class="font-medium">${modification.expression_id}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Modifié par</p>
                                <p class="font-medium">${modification.user_name}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Date de modification</p>
                                <p class="font-medium">${formatDateTime(modification.modified_at)}</p>
                            </div>
                        </div>
                        ${modification.modification_reason ? `
                        <div class="mt-4">
                            <p class="text-sm text-gray-600">Raison de la modification</p>
                            <p class="font-medium bg-white p-3 rounded border">${modification.modification_reason}</p>
                        </div>
                        ` : ''}
                    </div>

                    <!-- Détails des modifications -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="text-lg font-semibold mb-3">Modifications apportées</h3>
                        <div class="space-y-4">
                            ${buildChangeDetails(modification)}
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-3">
                        <button onclick="closeModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition">
                            Fermer
                        </button>
                    </div>
                </div>
            `;
        }

        /**
         * Construction des détails de changements
         */
        function buildChangeDetails(modification) {
            let html = '';

            if (modification.old_quantity !== modification.new_quantity) {
                html += `
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h4 class="font-semibold text-blue-800">Quantité</h4>
                        <p class="change-indicator">
                            <span class="change-old">${modification.old_quantity}</span> → 
                            <span class="change-new">${modification.new_quantity}</span>
                        </p>
                    </div>
                `;
            }

            if (modification.old_price !== modification.new_price) {
                html += `
                    <div class="border-l-4 border-green-500 pl-4">
                        <h4 class="font-semibold text-green-800">Prix unitaire</h4>
                        <p class="change-indicator">
                            <span class="change-old">${formatPrice(modification.old_price)} FCFA</span> → 
                            <span class="change-new">${formatPrice(modification.new_price)} FCFA</span>
                        </p>
                    </div>
                `;
            }

            if (modification.old_supplier !== modification.new_supplier) {
                html += `
                    <div class="border-l-4 border-yellow-500 pl-4">
                        <h4 class="font-semibold text-yellow-800">Fournisseur</h4>
                        <p class="change-indicator">
                            <span class="change-old">${modification.old_supplier || 'Non spécifié'}</span> → 
                            <span class="change-new">${modification.new_supplier || 'Non spécifié'}</span>
                        </p>
                    </div>
                `;
            }

            if (modification.old_payment_method !== modification.new_payment_method) {
                html += `
                    <div class="border-l-4 border-purple-500 pl-4">
                        <h4 class="font-semibold text-purple-800">Mode de paiement</h4>
                        <p class="change-indicator">
                            <span class="change-old">${modification.old_payment_method || 'Non spécifié'}</span> → 
                            <span class="change-new">${modification.new_payment_method || 'Non spécifié'}</span>
                        </p>
                    </div>
                `;
            }

            return html || '<p class="text-gray-500">Aucune modification détaillée disponible</p>';
        }

        /**
         * Fermeture de la modal
         */
        function closeModal() {
            document.getElementById('modification-details-modal').style.display = 'none';
        }

        /**
         * Utilitaires de formatage
         */
        function formatPrice(price) {
            if (!price) return '0';
            return parseFloat(price).toLocaleString('fr-FR');
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR') + ' à ' + date.toLocaleTimeString('fr-FR');
        }

        /**
         * Attacher les tooltips (version sans dépendance externe)
         */
        function attachTooltips() {
            // Version simple sans dépendance Bootstrap/jQuery UI
            $('[title]').each(function() {
                const $element = $(this);
                const title = $element.attr('title');

                if (title) {
                    // Créer un tooltip personnalisé simple
                    $element.on('mouseenter', function(e) {
                        // Créer le tooltip
                        const tooltip = $(`
                    <div class="custom-tooltip" 
                         style="position: absolute; 
                                background: #333; 
                                color: white; 
                                padding: 8px 12px; 
                                border-radius: 4px; 
                                font-size: 12px; 
                                z-index: 1000; 
                                max-width: 300px; 
                                word-wrap: break-word;
                                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                                pointer-events: none;">
                        ${title}
                    </div>
                `);

                        $('body').append(tooltip);

                        // Positionner le tooltip
                        const rect = e.target.getBoundingClientRect();
                        tooltip.css({
                            top: rect.top - tooltip.outerHeight() - 8,
                            left: rect.left + (rect.width / 2) - (tooltip.outerWidth() / 2)
                        });

                        // Ajuster si le tooltip sort de l'écran
                        if (tooltip.offset().top < $(window).scrollTop()) {
                            tooltip.css('top', rect.bottom + 8);
                        }

                        if (tooltip.offset().left < 0) {
                            tooltip.css('left', 8);
                        }

                        if (tooltip.offset().left + tooltip.outerWidth() > $(window).width()) {
                            tooltip.css('left', $(window).width() - tooltip.outerWidth() - 8);
                        }
                    });

                    $element.on('mouseleave', function() {
                        $('.custom-tooltip').remove();
                    });
                }
            });
        }
    </script>
</body>

</html>