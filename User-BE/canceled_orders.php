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
    <title>Commandes Annulées - DYM BE</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ========= datatable =========== -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <!-- ========= datatable js =========== -->
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

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

        .badge-canceled {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .badge-pending {
            background-color: #fff7ed;
            color: #c2410c;
        }

        .badge-completed {
            background-color: #d1fae5;
            color: #065f46;
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

        /* Tableaux */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 15px;
        }

        .dataTables_wrapper .dataTables_info {
            padding-top: 15px;
        }

        .dataTables_wrapper .dataTables_paginate {
            padding-top: 15px;
        }

        table.dataTable thead th {
            position: relative;
            background-image: none !important;
        }

        table.dataTable thead th.sorting:after,
        table.dataTable thead th.sorting_asc:after,
        table.dataTable thead th.sorting_desc:after {
            position: absolute;
            top: 12px;
            right: 8px;
            display: block;
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
        }

        table.dataTable thead th.sorting:after {
            content: "\f0dc";
            color: #ddd;
        }

        table.dataTable thead th.sorting_asc:after {
            content: "\f0de";
        }

        table.dataTable thead th.sorting_desc:after {
            content: "\f0dd";
        }

        /* Empty state */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            text-align: center;
        }

        .empty-state-icon {
            font-size: 4rem;
            color: #e5e7eb;
            margin-bottom: 1rem;
        }

        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .empty-state-description {
            color: #6b7280;
            max-width: 400px;
        }

        /* Filtres */
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .filter-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid #e5e7eb;
        }

        .filter-btn:hover {
            background-color: #f9fafb;
        }

        .filter-btn.active {
            background-color: #4F46E5;
            color: white;
        }

        /* Order detail styles */
        .order-details {
            background-color: #f9fafb;
            border-left: 4px solid #ef4444;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .order-detail-row {
            display: flex;
            margin-bottom: 0.5rem;
        }

        .order-detail-label {
            font-weight: 500;
            color: #4b5563;
            width: 180px;
        }

        .order-detail-value {
            color: #1f2937;
            flex: 1;
        }

        /* Statistics cards */
        .stats-card {
            background-color: white;
            border-radius: 0.5rem;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stats-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .stats-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .stats-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
        }

        .stats-subtitle {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.25rem;
        }

        /* Timeline styles */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0.75rem;
            width: 2px;
            background-color: #e5e7eb;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-marker {
            position: absolute;
            top: 0;
            left: -2rem;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background-color: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
        }

        .timeline-content {
            padding: 1rem;
            background-color: white;
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .timeline-date {
            font-size: 0.75rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }

        .timeline-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .timeline-description {
            font-size: 0.875rem;
            color: #4b5563;
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
                        <i class="fas fa-ban text-red-600 mr-2"></i>
                        Commandes Annulées
                    </h1>
                    <div class="text-sm text-gray-500 flex items-center">
                        <i class="far fa-calendar-alt mr-2"></i>
                        <span id="current-date"></span>
                    </div>
                </div>

                <div class="flex flex-wrap justify-between items-center mb-4">
                    <div class="mb-3">
                        <a href="dashboard.php"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-sm transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Retour au Tableau de Bord
                        </a>
                    </div>
                    <div class="text-sm text-gray-500">
                        <span class="block bg-red-100 px-3 py-1 rounded-full">
                            <i class="fas fa-info-circle mr-1"></i>
                            Commandes annulées automatiquement lors de la clôture des projets
                        </span>
                    </div>
                </div>

                <!-- Message d'information -->
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Les commandes listées ci-dessous ont été <strong>annulées automatiquement</strong>
                                lorsque les projets correspondants ont été marqués comme terminés.
                                Cette annulation automatique évite des dépenses inutiles pour des projets qui n'en ont
                                plus besoin.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" id="stats-container">
                    <div class="stats-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="stats-title">Total annulations</div>
                                <div class="stats-value" id="total-canceled">--</div>
                                <div class="stats-subtitle">Depuis le début</div>
                            </div>
                            <div class="stats-icon bg-red-100">
                                <i class="fas fa-ban text-red-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="stats-title">Ce mois</div>
                                <div class="stats-value" id="month-canceled">--</div>
                                <div class="stats-subtitle">Derniers 30 jours</div>
                            </div>
                            <div class="stats-icon bg-orange-100">
                                <i class="fas fa-calendar-times text-orange-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="stats-title">Valeur économisée</div>
                                <div class="stats-value" id="saved-value">--</div>
                                <div class="stats-subtitle">Commandes évitées</div>
                            </div>
                            <div class="stats-icon bg-green-100">
                                <i class="fas fa-money-bill-wave text-green-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="stats-title">Projets concernés</div>
                                <div class="stats-value" id="projects-count">--</div>
                                <div class="stats-subtitle">Projets terminés</div>
                            </div>
                            <div class="stats-icon bg-blue-100">
                                <i class="fas fa-folder-minus text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="mb-4">
                    <div class="flex flex-wrap items-center justify-between mb-2">
                        <h2 class="text-lg font-semibold text-gray-700 mb-2">Liste des commandes annulées</h2>
                        <div class="filter-container">
                            <button class="filter-btn active" data-period="all">
                                <i class="fas fa-globe mr-2"></i> Toutes
                            </button>
                            <button class="filter-btn" data-period="month">
                                <i class="fas fa-calendar-day mr-2"></i> Ce mois
                            </button>
                            <button class="filter-btn" data-period="quarter">
                                <i class="fas fa-calendar-week mr-2"></i> Ce trimestre
                            </button>
                            <button class="filter-btn" data-period="year">
                                <i class="fas fa-calendar mr-2"></i> Cette année
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Container qui contient soit le tableau, soit le message d'erreur -->
                <div id="content-container">
                    <!-- Zone de chargement -->
                    <div id="orders-loading" class="text-center py-8">
                        <div
                            class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-red-500 border-r-transparent">
                        </div>
                        <p class="text-gray-500 mt-2">Chargement des commandes annulées...</p>
                    </div>

                    <!-- Tableau des commandes annulées (initialement caché) -->
                    <div id="orders-table-container" class="overflow-x-auto" style="display:none;">
                        <table id="canceled-orders-table"
                            class="min-w-full divide-y divide-gray-200 display responsive nowrap">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ID Projet</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Code Projet</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nom Client</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Désignation</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Quantité</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Statut Original</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fournisseur</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date Annulation</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Annulé Par</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody id="orders-table-body" class="bg-white divide-y divide-gray-200">
                                <!-- Les commandes seront chargées ici par JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Message "Aucune commande annulée" (initialement caché) -->
                    <div id="orders-empty" class="empty-state" style="display:none;">
                        <i class="fas fa-ban empty-state-icon"></i>
                        <h3 class="empty-state-title">Aucune commande annulée</h3>
                        <p class="empty-state-description">
                            Aucune commande n'a encore été annulée automatiquement pour vos projets.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de détails de la commande annulée -->
        <div id="order-details-modal"
            class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center hidden z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl mx-4">
                <div class="flex justify-between items-start mb-4">
                    <h2 class="text-xl font-bold text-gray-800" id="modal-title">Détails de la Commande Annulée</h2>
                    <button id="close-modal" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="modal-content" class="mt-4">
                    <!-- Le contenu sera chargé dynamiquement -->
                </div>
                <div class="mt-6 flex justify-end">
                    <button id="close-modal-btn"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let dataTable = null;
        let ordersList = [];

        // Fonction pour initialiser la page
        $(document).ready(function () {
            // Afficher la date courante
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const today = new Date();
            document.getElementById('current-date').textContent = today.toLocaleDateString('fr-FR', options);

            // Charger les commandes annulées
            loadCanceledOrders('all');

            // Gestionnaires pour les filtres de période
            $('.filter-btn').on('click', function () {
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                const period = $(this).data('period');
                loadCanceledOrders(period);
            });

            // Gestionnaires pour les boutons de fermeture de la modal
            $('#close-modal, #close-modal-btn').on('click', function () {
                $('#order-details-modal').addClass('hidden');
            });

            // Fermer la modal si on clique en dehors
            $('#order-details-modal').on('click', function (e) {
                if (e.target === this) {
                    $(this).addClass('hidden');
                }
            });
        });

        // Fonction pour charger les commandes annulées
        function loadCanceledOrders(period) {
            // Afficher uniquement le loader, cacher tout le reste
            $('#orders-loading').show();
            $('#orders-table-container').hide();
            $('#orders-empty').hide();

            // Si une instance DataTable existe déjà, la détruire
            if (dataTable !== null) {
                dataTable.destroy();
                dataTable = null;
            }

            $.ajax({
                url: 'api_canceled/api_getCanceledOrders.php',
                type: 'GET',
                data: { period: period },
                dataType: 'json',
                success: function (response) {
                    // Cacher le loader
                    $('#orders-loading').hide();

                    if (response.success) {
                        // Mettre à jour les statistiques
                        updateStatistics(response.stats);

                        if (response.data && response.data.length > 0) {
                            // Des données sont présentes, afficher le tableau et cacher le message d'erreur
                            ordersList = response.data;
                            displayCanceledOrders(ordersList);
                            $('#orders-table-container').show();
                            $('#orders-empty').hide();
                        } else {
                            // Aucune donnée, afficher le message d'erreur et cacher le tableau
                            $('#orders-table-container').hide();
                            $('#orders-empty').show();
                        }
                    } else {
                        // Erreur de l'API, afficher le message d'erreur avec le texte d'erreur
                        $('#orders-table-container').hide();
                        $('#orders-empty').show();
                        $('#orders-empty .empty-state-title').text('Erreur de chargement');
                        $('#orders-empty .empty-state-description').text(response.message || 'Une erreur est survenue lors du chargement des commandes.');

                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: response.message || 'Impossible de charger les commandes annulées. Veuillez réessayer plus tard.',
                            confirmButtonColor: '#4F46E5'
                        });
                    }
                },
                error: function (xhr, status, error) {
                    // Erreur de connexion
                    $('#orders-loading').hide();
                    $('#orders-table-container').hide();
                    $('#orders-empty').show();
                    $('#orders-empty .empty-state-title').text('Erreur de connexion');
                    $('#orders-empty .empty-state-description').text('Impossible de se connecter au serveur.');

                    console.error('Erreur Ajax:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur de connexion',
                        text: 'Impossible de se connecter au serveur. Veuillez vérifier votre connexion et réessayer.',
                        confirmButtonColor: '#4F46E5'
                    });
                }
            });
        }

        // Fonction pour mettre à jour les statistiques
        function updateStatistics(stats) {
            if (!stats) return;

            // Formater les nombres avec séparateurs de milliers
            const formatNumber = (num) => {
                return new Intl.NumberFormat('fr-FR').format(num);
            };

            // Mettre à jour les valeurs des statistiques
            $('#total-canceled').text(formatNumber(stats.total_canceled || 0));
            $('#month-canceled').text(formatNumber(stats.month_canceled || 0));

            // Formater le montant avec FCFA
            /*const savedValue = stats.saved_value || 0;
            $('#saved-value').text(formatNumber(savedValue) + ' FCFA');*/

            $('#projects-count').text(formatNumber(stats.projects_count || 0));
        }

        // Fonction pour afficher les commandes annulées
        function displayCanceledOrders(orders) {
            const tableBody = $('#orders-table-body');
            tableBody.empty();

            // Vérifier si les données sont dans un format différent
            const orderData = Array.isArray(orders) ? orders : (orders.data || []);

            if (orderData.length === 0) {
                // Aucune donnée, afficher le message d'erreur et cacher le tableau
                $('#orders-table-container').hide();
                $('#orders-empty').show();
                return;
            }

            // Des données sont présentes, afficher le tableau et cacher le message d'erreur
            $('#orders-table-container').show();
            $('#orders-empty').hide();

            // Ajouter les lignes au tableau
            orderData.forEach(order => {
                // Formatter la date d'annulation
                const canceledDate = typeof order.canceled_at === 'string' ? order.canceled_at :
                    (order.canceled_at ? formatDate(order.canceled_at, true) : 'N/A');

                // Construire la ligne du tableau
                const row = $(`
            <tr class="hover:bg-red-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${order.project_id || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${order.code_projet || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">${order.nom_client || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${order.designation || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${order.quantity || '0'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${order.original_status}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${order.fournisseur || 'Non spécifié'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500">${canceledDate}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${order.canceled_by_name || 'Système'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button class="text-indigo-600 hover:text-indigo-900 mr-3 view-details-btn" 
                            data-id="${order.order_id}" 
                            onclick="viewOrderDetails(${order.id})">
                        <i class="fas fa-eye"></i> Détails
                    </button>
                </td>
            </tr>
        `);

                tableBody.append(row);
            });

            // Initialiser DataTable
            dataTable = $('#canceled-orders-table').DataTable({
                responsive: true,
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                },
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel mr-1"></i> Excel',
                        className: 'mr-2',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf mr-1"></i> PDF',
                        className: 'mr-2',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print mr-1"></i> Imprimer',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                        }
                    }
                ]
            });
        }

        // Fonction pour voir les détails d'une commande annulée
        function viewOrderDetails(orderId) {
            // Afficher un loader
            Swal.fire({
                title: 'Chargement...',
                text: 'Récupération des détails de la commande annulée',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Charger les détails
            fetch(`api_canceled/api_getCanceledOrderDetails.php?id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order;
                        const relatedOrders = data.related_orders || [];

                        // Déterminer si c'est une expression sans commande
                        const isExpressionOnly = order.order_id == 0;

                        // Adapter le message en fonction du type
                        const annulationTitle = isExpressionOnly ?
                            "Expression de besoin annulée" :
                            "Commande annulée";

                        const annulationDesc = isExpressionOnly ?
                            "Cette expression de besoin a été annulée car le projet a été marqué comme terminé avant qu'elle ne soit traitée par le service achat." :
                            "Cette commande a été annulée automatiquement car le projet a été marqué comme terminé.";

                        // Préparer le contenu de la modal
                        let content = `
                <div class="text-left">
                    <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">${annulationTitle}</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <p>${annulationDesc}</p>
                                    <p>Raison : ${order.cancel_reason || 'Non spécifiée'}</p>
                                    <p>Annulée par : ${order.canceled_by_name || 'Utilisateur inconnu'}</p>
                                    <p>Date d'annulation : ${order.canceled_at_formatted || new Date(order.canceled_at).toLocaleString('fr-FR')}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Produit:</p>
                            <p class="font-medium">${order.designation || 'N/A'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Projet:</p>
                            <p class="font-medium">${order.code_projet || 'N/A'} - ${order.nom_client || 'N/A'}</p>
                        </div>
                    </div>`;

                        // Ajouter les informations de commande seulement si c'est une vraie commande
                        if (!isExpressionOnly) {
                            content += `
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Quantité:</p>
                            <p class="font-medium">${order.quantity || 'N/A'} ${order.unit || ''}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Fournisseur:</p>
                            <p class="font-medium">${order.fournisseur || 'Non spécifié'}</p>
                        </div>
                    </div>
                    
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Prix unitaire:</p>
                            <p class="font-medium">--</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Date de commande:</p>
                            <p class="font-medium">${order.date_achat_formatted || 'N/A'}</p>
                        </div>
                    </div>`;
                        } else {
                            // Informations spécifiques pour les expressions sans commande
                            content += `
                    <div class="mb-4 p-3 bg-gray-50 rounded">
                        <p class="text-sm text-gray-600">Statut original de l'expression:</p>
                        <p class="font-medium">${formatStatusLabel(order.original_status)}</p>
                    </div>`;
                        }

                        /*<div>
                            <p class="text-sm text-gray-600">Prix unitaire:</p>
                            <p class="font-medium">${order.prix_unitaire ? (parseFloat(order.prix_unitaire).toLocaleString('fr-FR') + ' FCFA') : 'Non spécifié'}</p>
                        </div>*/

                        // Informations supplémentaires sur le projet
                        content += `
                <div class="mb-4">
                    <h3 class="font-medium mb-2 text-gray-800">Informations sur le projet:</h3>
                    <div class="bg-gray-50 p-3 rounded">
                        <p><strong>Description:</strong> ${order.description_projet || 'Non disponible'}</p>
                        <p><strong>Localisation:</strong> ${order.sitgeo || 'Non spécifiée'}</p>
                        <p><strong>Chef de projet:</strong> ${order.chefprojet || 'Non spécifié'}</p>
                    </div>
                </div>`;

                        // Ajouter la section des commandes liées si disponible et seulement pour les commandes réelles
                        if (!isExpressionOnly && relatedOrders.length > 0) {
                            content += `
                    <div class="mt-6">
                        <h3 class="font-medium mb-3 text-gray-800">Autres commandes pour ce produit:</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Statut</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Quantité</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Fournisseur</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">`;

                            relatedOrders.forEach(related => {
                                // Déterminer la classe de statut
                                let statusClass;
                                switch (related.status) {
                                    case 'reçu':
                                        statusClass = 'bg-green-100 text-green-800';
                                        break;
                                    case 'en_cours':
                                        statusClass = 'bg-orange-100 text-orange-800';
                                        break;
                                    case 'commandé':
                                        statusClass = 'bg-blue-100 text-blue-800';
                                        break;
                                    case 'annulé':
                                        statusClass = 'bg-red-100 text-red-800';
                                        break;
                                    default:
                                        statusClass = 'bg-gray-100 text-gray-800';
                                }

                                content += `
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap text-sm">${related.date_achat_formatted || new Date(related.date_achat).toLocaleDateString('fr-FR')}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-medium ${statusClass}">
                                    ${formatStatusLabel(related.status)}${related.is_partial == 1 ? ' (partielle)' : ''}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm">${related.quantity} ${related.unit || ''}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm">${related.fournisseur || 'Non spécifié'}</td>
                        </tr>`;
                            });

                            content += `
                                </tbody>
                            </table>
                        </div>
                    </div>`;
                        }

                        // Ajouter des informations sur le projet terminé
                        if (order.completed_by_name) {
                            content += `
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <h3 class="font-medium mb-2 text-gray-800">Clôture du projet:</h3>
                        <p><strong>Projet terminé par:</strong> ${order.completed_by_name}</p>
                        <p><strong>Date de clôture:</strong> ${order.completed_at ? new Date(order.completed_at).toLocaleDateString('fr-FR') : 'Non spécifiée'}</p>
                    </div>`;
                        }

                        content += '</div>';

                        // Afficher la modal avec les détails
                        Swal.fire({
                            title: annulationTitle,
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

        /**
         * Fonction utilitaire pour formater les labels de statut
         * @param {string} status - Statut original
         * @return {string} - Statut formaté
         */
        function formatStatusLabel(status) {
            switch (status) {
                case 'pas validé':
                    return 'Pas validé';
                case 'validé':
                    return 'Validé';
                case 'en_cours':
                    return 'En cours';
                case 'commandé':
                    return 'Commandé';
                case 'reçu':
                    return 'Reçu';
                case 'annulé':
                    return 'Annulé';
                default:
                    return status || 'Inconnu';
            }
        }

        /**
         * Fonction pour formater une date
         * @param {string} dateString - La date à formater
         * @param {boolean} includeTime - Inclure l'heure ou non
         * @returns {string} Date formatée
         */
        function formatDate(dateString, includeTime = false) {
            if (!dateString) return 'N/A';

            const date = new Date(dateString);
            const options = {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            };

            if (includeTime) {
                options.hour = '2-digit';
                options.minute = '2-digit';
            }

            return date.toLocaleDateString('fr-FR', options);
        }
    </script>
</body>

</html>