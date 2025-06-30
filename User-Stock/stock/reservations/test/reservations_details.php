<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails des Réservations - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <style>
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

        .table-container {
            overflow-x: auto;
            max-width: 100%;
        }

        @media (min-width: 1024px) {
            .table-fixed {
                table-layout: fixed;
            }
        }

        /* Pour les tooltips */
        [data-tooltip] {
            position: relative;
            cursor: pointer;
        }

        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.5rem;
            background-color: #1f2937;
            color: white;
            border-radius: 0.25rem;
            white-space: nowrap;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            z-index: 10;
            font-size: 0.75rem;
        }

        [data-tooltip]:hover:before {
            visibility: visible;
            opacity: 1;
        }

        /* Animation pour le loading */
        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>

<body class="bg-gray-100 text-sm">
    <div id="notification-container" class="fixed top-4 right-4 z-50"></div>
    <div class="flex h-screen">
        <?php include_once '../sidebar.php'; ?>

        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once '../header.php'; ?>

            <main class="p-4 flex-1">
                <div class="bg-white p-4 rounded-lg shadow mb-4">
                    <div class="flex flex-wrap gap-2 items-center justify-between">
                        <div class="flex flex-wrap gap-2 items-center">
                            <!-- Barre de recherche -->
                            <div class="relative w-64">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons text-gray-400 text-base">search</span>
                                </span>
                                <input type="text" id="searchInput" placeholder="Rechercher un produit ou projet"
                                    class="w-full pl-10 p-1.5 border rounded text-xs">
                            </div>

                            <!-- Filtres -->
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons text-gray-400 text-base">business</span>
                                </span>
                                <select id="projectFilter"
                                    class="pl-10 p-1.5 border rounded appearance-none pr-8 text-xs">
                                    <option value="">Tous les projets</option>
                                    <!-- Les projets seront chargés dynamiquement ici -->
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <span class="material-icons text-gray-400 text-base">expand_more</span>
                                </span>
                            </div>

                            <!-- Filtre par statut de stock -->
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons text-gray-400 text-base">inventory_2</span>
                                </span>
                                <select id="stockStatusFilter"
                                    class="pl-10 p-1.5 border rounded appearance-none pr-8 text-xs">
                                    <option value="">Tous les statuts</option>
                                    <option value="available">Disponible</option>
                                    <option value="partial">Partiellement disponible</option>
                                    <option value="unavailable">Non disponible</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <span class="material-icons text-gray-400 text-base">expand_more</span>
                                </span>
                            </div>

                            <!-- Bouton réinitialiser -->
                            <button id="resetFiltersBtn"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-3 py-1.5 rounded-md border border-gray-300 shadow-sm transition duration-300 ease-in-out flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-opacity-50 text-xs">
                                <span class="material-icons mr-1 text-base text-gray-600">refresh</span>
                                Réinitialiser
                            </button>
                        </div>

                        <!-- Statistiques rapides -->
                        <div class="flex gap-2 flex-wrap">
                            <div class="bg-blue-50 p-2 rounded-lg border border-blue-100 flex items-center">
                                <span class="material-icons text-blue-500 mr-2">bookmark</span>
                                <div>
                                    <div class="text-xs text-gray-500">Total Réservations</div>
                                    <div id="totalReservations" class="font-semibold text-blue-700">--</div>
                                </div>
                            </div>
                            <div class="bg-green-50 p-2 rounded-lg border border-green-100 flex items-center">
                                <span class="material-icons text-green-500 mr-2">check_circle</span>
                                <div>
                                    <div class="text-xs text-gray-500">Disponibles</div>
                                    <div id="availableReservations" class="font-semibold text-green-700">--</div>
                                </div>
                            </div>
                            <div class="bg-red-50 p-2 rounded-lg border border-red-100 flex items-center">
                                <span class="material-icons text-red-500 mr-2">error</span>
                                <div>
                                    <div class="text-xs text-gray-500">Non disponibles</div>
                                    <div id="unavailableReservations" class="font-semibold text-red-700">--</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableau des réservations -->
                <div class="bg-white p-4 rounded-lg shadow">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Détails des réservations par projet</h2>
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200 table-fixed" id="reservationsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">
                                        Projet
                                    </th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">
                                        Code Produit
                                    </th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/5">
                                        Désignation
                                    </th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">
                                        Unité
                                    </th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">
                                        Catégorie
                                    </th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">
                                        Qté Réservée
                                    </th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">
                                        Stock Dispo
                                    </th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">
                                        Statut
                                    </th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="reservationsTableBody" class="bg-white divide-y divide-gray-200">
                                <!-- Les réservations seront chargées dynamiquement ici -->
                                <tr>
                                    <td colspan="9" class="px-4 py-4 text-center text-gray-500">
                                        <div class="flex justify-center items-center space-x-2">
                                            <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500">
                                            </div>
                                            <span>Chargement des réservations...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="pagination" class="mt-4 flex justify-center text-xs">
                        <!-- Les boutons de pagination seront insérés ici -->
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de confirmation pour libérer une réservation -->
    <div id="releaseModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" id="modalOverlay"></div>
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 md:mx-0 transform transition-all opacity-0 scale-95"
            id="modalContent">
            <div class="flex items-center justify-between p-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800">Libérer une réservation</h2>
                <button type="button" id="closeModalBtn" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div class="p-6">
                <p class="mb-4 text-gray-600">Êtes-vous sûr de vouloir libérer cette réservation ? Cette action est
                    irréversible.</p>

                <div class="bg-gray-50 p-3 rounded-lg mb-4">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="text-gray-500">Projet:</div>
                        <div id="releaseProject" class="font-medium text-gray-800">--</div>

                        <div class="text-gray-500">Produit:</div>
                        <div id="releaseProduct" class="font-medium text-gray-800">--</div>

                        <div class="text-gray-500">Quantité:</div>
                        <div id="releaseQuantity" class="font-medium text-gray-800">--</div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button id="cancelReleaseBtn"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button id="confirmReleaseBtn"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm bg-red-600 text-white hover:bg-red-700">
                        Libérer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de détails du projet -->
    <div id="projectDetailsModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" id="projectModalOverlay"></div>
        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4 md:mx-0 transform transition-all opacity-0 scale-95"
            id="projectModalContent">
            <div class="flex items-center justify-between p-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800" id="projectDetailsTitle">Détails du projet</h2>
                <button type="button" id="closeProjectModalBtn"
                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div class="p-6">
                <div id="projectDetailsContent" class="mb-4 space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm text-gray-500">Code Projet</div>
                                <div id="projectCode" class="font-medium">--</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Client</div>
                                <div id="projectClient" class="font-medium">--</div>
                            </div>
                            <div class="col-span-2">
                                <div class="text-sm text-gray-500">Description</div>
                                <div id="projectDescription" class="font-medium">--</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Situation Géographique</div>
                                <div id="projectLocation" class="font-medium">--</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Chef de Projet</div>
                                <div id="projectManager" class="font-medium">--</div>
                            </div>
                        </div>
                    </div>

                    <h3 class="font-semibold text-base text-gray-800">Réservations de ce projet</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Produit
                                    </th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Qté Réservée
                                    </th>
                                    <th scope="col"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Statut
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="projectProductsTable" class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="3" class="px-4 py-4 text-center text-gray-500">
                                        Chargement...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            let currentPage = 1;
            const itemsPerPage = 20;
            let allReservations = [];
            let filteredReservations = [];

            // Fonction pour afficher une notification
            function showNotification(message, type = 'success') {
                const backgroundColor = type === 'success' ? '#4CAF50' : '#F44336';
                const icon = type === 'success' ? 'check_circle' : 'error_outline';

                Toastify({
                    text: `
                        <div class="flex items-center">
                            <span class="material-icons mr-2" style="font-size: 18px;">${icon}</span>
                            <span>${message}</span>
                        </div>
                    `,
                    duration: 3000,
                    close: false,
                    gravity: "top",
                    position: "right",
                    style: {
                        background: backgroundColor,
                    },
                    stopOnFocus: true,
                    escapeMarkup: false,
                }).showToast();
            }

            // Fonction pour charger les données des projets
            function loadProjects() {
                $.ajax({
                    url: 'api/get_projects.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            const projectSelect = $('#projectFilter');
                            projectSelect.html('<option value="">Tous les projets</option>');

                            response.projects.forEach(function (project) {
                                projectSelect.append(`<option value="${project.id}">${project.code_projet} - ${project.nom_client}</option>`);
                            });
                        } else {
                            console.error('Erreur lors du chargement des projets:', response.message);
                            showNotification('Erreur lors du chargement des projets', 'error');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erreur AJAX:', error);
                        showNotification('Une erreur est survenue lors du chargement des projets', 'error');
                    }
                });
            }

            // Fonction pour charger les réservations
            function loadReservations() {
                const searchQuery = $('#searchInput').val();
                const projectFilter = $('#projectFilter').val();
                const stockStatusFilter = $('#stockStatusFilter').val();

                // Afficher l'indicateur de chargement
                $('#reservationsTableBody').html(`
                    <tr>
                        <td colspan="9" class="px-4 py-4 text-center text-gray-500">
                            <div class="flex justify-center items-center space-x-2">
                                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"></div>
                                <span>Chargement des réservations...</span>
                            </div>
                        </td>
                    </tr>
                `);

                $.ajax({
                    url: 'api/get_reserved_products.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            // Stocker toutes les réservations
                            allReservations = response.reservations;

                            // Mettre à jour les statistiques
                            updateStatistics(allReservations);

                            // Appliquer les filtres
                            applyFilters();
                        } else {
                            console.error('Erreur lors du chargement des réservations:', response.message);
                            showNotification('Erreur lors du chargement des réservations', 'error');
                            $('#reservationsTableBody').html(`
                                <tr>
                                    <td colspan="9" class="px-4 py-4 text-center text-red-500">
                                        Une erreur est survenue lors du chargement des réservations
                                    </td>
                                </tr>
                            `);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erreur AJAX:', error);
                        showNotification('Une erreur est survenue lors du chargement des réservations', 'error');
                        $('#reservationsTableBody').html(`
                            <tr>
                                <td colspan="9" class="px-4 py-4 text-center text-red-500">
                                    Une erreur est survenue lors du chargement des réservations
                                </td>
                            </tr>
                        `);
                    }
                });
            }

            // Mettre à jour les statistiques
            function updateStatistics(reservations) {
                let total = reservations.length;
                let available = reservations.filter(r => r.status === 'available').length;
                let unavailable = reservations.filter(r => r.status === 'unavailable').length;
                let partial = reservations.filter(r => r.status === 'partial').length;

                $('#totalReservations').text(total);
                $('#availableReservations').text(available);
                $('#unavailableReservations').text(unavailable + (partial ? ` (+ ${partial} partiels)` : ''));
            }

            // Appliquer les filtres aux réservations
            function applyFilters() {
                const searchQuery = $('#searchInput').val().toLowerCase();
                const projectFilter = $('#projectFilter').val();
                const stockStatusFilter = $('#stockStatusFilter').val();

                // Filtrer les réservations avec des vérifications de nullité
                filteredReservations = allReservations.filter(function (reservation) {
                    // Filtre de recherche - vérifier chaque propriété avant d'appeler toLowerCase()
                    const matchesSearch = searchQuery === '' ||
                        (reservation.project_name && reservation.project_name.toLowerCase().includes(searchQuery)) ||
                        (reservation.project_code && reservation.project_code.toLowerCase().includes(searchQuery)) ||
                        (reservation.product_name && reservation.product_name.toLowerCase().includes(searchQuery)) ||
                        (reservation.barcode && reservation.barcode.toLowerCase().includes(searchQuery)) ||
                        (reservation.category_name && reservation.category_name.toLowerCase().includes(searchQuery));

                    // Filtre de projet
                    const matchesProject = projectFilter === '' ||
                        (reservation.project_id && reservation.project_id.toString() === projectFilter);

                    // Filtre de statut
                    const matchesStatus = stockStatusFilter === '' ||
                        reservation.status === stockStatusFilter;

                    return matchesSearch && matchesProject && matchesStatus;
                });

                // Mettre à jour la pagination
                currentPage = 1;
                updatePagination();

                // Afficher les résultats
                displayReservations();
            }

            // Afficher les réservations filtrées
            function displayReservations() {
                const tableBody = $('#reservationsTableBody');
                tableBody.empty();

                if (filteredReservations.length === 0) {
                    tableBody.html(`
                        <tr>
                            <td colspan="9" class="px-4 py-4 text-center text-gray-500">
                                Aucune réservation trouvée
                            </td>
                        </tr>
                    `);
                    return;
                }

                // Calculer les indices de début et de fin pour la pagination
                const startIndex = (currentPage - 1) * itemsPerPage;
                const endIndex = Math.min(startIndex + itemsPerPage, filteredReservations.length);

                // Afficher les réservations pour la page actuelle
                for (let i = startIndex; i < endIndex; i++) {
                    const reservation = filteredReservations[i];

                    // Déterminer le statut et le badge correspondant
                    let statusBadge = '';
                    if (reservation.status === 'available') {
                        statusBadge = `<span class="status-badge badge-success">
                                          <span class="material-icons">check_circle</span>
                                          Disponible
                                      </span>`;
                    } else if (reservation.status === 'partial') {
                        statusBadge = `<span class="status-badge badge-warning">
                                          <span class="material-icons">warning</span>
                                          Partiel
                                      </span>`;
                    } else {
                        statusBadge = `<span class="status-badge badge-danger">
                                          <span class="material-icons">error</span>
                                          Non disponible
                                      </span>`;
                    }

                    tableBody.append(`
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="ml-1">
                                        <div class="text-sm font-medium text-blue-600 cursor-pointer project-details-link" 
                                             data-project-id="${reservation.project_id}">
                                            ${reservation.project_code}
                                        </div>
                                        <div class="text-xs text-gray-500">${reservation.project_name}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">${reservation.barcode}</td>
                            <td class="px-4 py-2">
                                <div class="text-sm font-medium text-gray-900">${reservation.product_name}</div>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">${reservation.unit}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">${reservation.category_name}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-xs font-semibold text-blue-600">${reservation.reserved_quantity}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-xs font-semibold
                                ${reservation.status === 'available' ? 'text-green-600' :
                            reservation.status === 'partial' ? 'text-yellow-600' : 'text-red-600'}">
                                ${reservation.available_quantity} / ${reservation.total_quantity}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                ${statusBadge}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                                <button class="release-reservation-btn bg-red-50 hover:bg-red-100 text-red-600 p-1.5 rounded-md transition-colors focus:outline-none"
                                    <span class="material-icons text-sm">delete</span>
                                </button>
                            </td>
                            </tr>
                            `);

                    /*<td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                        <button class="release-reservation-btn bg-red-50 hover:bg-red-100 text-red-600 p-1.5 rounded-md transition-colors focus:outline-none"
                                data-reservation-id="${reservation.id}"
                                data-project-id="${reservation.project_id}"
                                data-project-name="${reservation.project_code} - ${reservation.project_name}"
                                data-product-id="${reservation.product_id}"
                                data-product-name="${reservation.product_name}"
                                data-quantity="${reservation.reserved_quantity}"
                                ${reservation.can_release ? '' : 'disabled'}
                                data-tooltip="${reservation.can_release ? 'Libérer cette réservation' : 'Impossible de libérer cette réservation'}">
                            <span class="material-icons text-sm">delete</span>
                        </button>
                    </td>*/
                }
            }

            // Mettre à jour la pagination
            function updatePagination() {
                const totalPages = Math.ceil(filteredReservations.length / itemsPerPage);
                const paginationContainer = $('#pagination');
                paginationContainer.empty();

                if (totalPages <= 1) {
                    return;
                }

                // Structure de pagination
                paginationContainer.html(`
                    <div class="flex items-center space-x-1">
                        <button id="prevPage" class="px-3 py-1 rounded-md bg-gray-100 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed" 
                                ${currentPage <= 1 ? 'disabled' : ''}>
                            <span class="material-icons text-xs">chevron_left</span>
                        </button>
                        
                        <div id="pageNumbers" class="flex items-center space-x-1"></div>
                        
                        <button id="nextPage" class="px-3 py-1 rounded-md bg-gray-100 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                ${currentPage >= totalPages ? 'disabled' : ''}>
                            <span class="material-icons text-xs">chevron_right</span>
                        </button>
                        
                        <span class="ml-4 text-xs text-gray-600">
                            Page ${currentPage} sur ${totalPages} (${filteredReservations.length} réservations)
                        </span>
                    </div>
                `);

                // Ajouter les numéros de page
                const pageNumbers = $('#pageNumbers');

                // Déterminer l'intervalle de pages à afficher
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, currentPage + 2);

                // Ajuster l'intervalle pour toujours afficher 5 pages si possible
                if (endPage - startPage < 4) {
                    if (startPage === 1) {
                        endPage = Math.min(totalPages, startPage + 4);
                    } else if (endPage === totalPages) {
                        startPage = Math.max(1, endPage - 4);
                    }
                }

                // Première page si nécessaire
                if (startPage > 1) {
                    pageNumbers.append(`
                        <button class="page-number px-3 py-1 rounded-md bg-gray-100 hover:bg-gray-200" data-page="1">1</button>
                    `);

                    if (startPage > 2) {
                        pageNumbers.append(`<span class="px-1">...</span>`);
                    }
                }

                // Pages numériques
                for (let i = startPage; i <= endPage; i++) {
                    const isActive = i === currentPage;
                    pageNumbers.append(`
                        <button class="page-number px-3 py-1 rounded-md ${isActive ? 'bg-blue-500 text-white' : 'bg-gray-100 hover:bg-gray-200'}" 
                                data-page="${i}" ${isActive ? 'disabled' : ''}>
                            ${i}
                        </button>
                    `);
                }

                // Dernière page si nécessaire
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        pageNumbers.append(`<span class="px-1">...</span>`);
                    }

                    pageNumbers.append(`
                        <button class="page-number px-3 py-1 rounded-md bg-gray-100 hover:bg-gray-200" 
                                data-page="${totalPages}">
                            ${totalPages}
                        </button>
                    `);
                }

                // Gestionnaires d'événements pour la pagination
                $('#prevPage').on('click', function () {
                    if (currentPage > 1) {
                        currentPage--;
                        displayReservations();
                        updatePagination();
                    }
                });

                $('#nextPage').on('click', function () {
                    if (currentPage < totalPages) {
                        currentPage++;
                        displayReservations();
                        updatePagination();
                    }
                });

                $('.page-number').on('click', function () {
                    const page = parseInt($(this).data('page'));
                    currentPage = page;
                    displayReservations();
                    updatePagination();
                });
            }

            // Fonctions pour les modals
            function openReleaseModal(reservation) {
                // Remplir les détails de la réservation
                $('#releaseProject').text(reservation.projectName);
                $('#releaseProduct').text(reservation.productName);
                $('#releaseQuantity').text(reservation.quantity);

                // Stocker les IDs pour l'action de libération
                $('#confirmReleaseBtn').data('reservation-id', reservation.reservationId);
                $('#confirmReleaseBtn').data('project-id', reservation.projectId);
                $('#confirmReleaseBtn').data('product-id', reservation.productId);

                // Afficher la modal avec animation
                $('#releaseModal').removeClass('hidden');
                setTimeout(() => {
                    $('#modalOverlay').addClass('opacity-100');
                    $('#modalContent').removeClass('opacity-0 scale-95').addClass('opacity-100 scale-100');
                }, 50);
            }

            function closeReleaseModal() {
                $('#modalOverlay').removeClass('opacity-100');
                $('#modalContent').removeClass('opacity-100 scale-100').addClass('opacity-0 scale-95');
                setTimeout(() => {
                    $('#releaseModal').addClass('hidden');
                }, 300);
            }

            function loadProjectDetails(projectId) {
                $.ajax({
                    url: 'api/get_project_details.php',
                    type: 'GET',
                    data: { project_id: projectId },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            const project = response.project;
                            const products = response.products;

                            // Mise à jour des détails du projet
                            $('#projectDetailsTitle').text(`Détails du projet: ${project.code_projet}`);
                            $('#projectCode').text(project.code_projet);
                            $('#projectClient').text(project.nom_client);
                            $('#projectDescription').text(project.description_projet);
                            $('#projectLocation').text(project.sitgeo);
                            $('#projectManager').text(project.chefprojet);

                            // Mise à jour de la liste des produits
                            const productsList = $('#projectProductsTable');
                            productsList.empty();

                            if (products.length === 0) {
                                productsList.html(`
                                    <tr>
                                        <td colspan="3" class="px-4 py-4 text-center text-gray-500">
                                            Aucun produit réservé pour ce projet
                                        </td>
                                    </tr>
                                `);
                            } else {
                                products.forEach(function (product) {
                                    // Déterminer le statut et le badge correspondant
                                    let statusBadge = '';
                                    if (product.status === 'available') {
                                        statusBadge = `<span class="status-badge badge-success">
                                                          <span class="material-icons">check_circle</span>
                                                          Disponible
                                                      </span>`;
                                    } else if (product.status === 'partial') {
                                        statusBadge = `<span class="status-badge badge-warning">
                                                          <span class="material-icons">warning</span>
                                                          Partiel
                                                      </span>`;
                                    } else {
                                        statusBadge = `<span class="status-badge badge-danger">
                                                          <span class="material-icons">error</span>
                                                          Non disponible
                                                      </span>`;
                                    }

                                    productsList.append(`
                                        <tr>
                                            <td class="px-4 py-2">
                                                <div class="text-sm font-medium text-gray-900">${product.product_name}</div>
                                                <div class="text-xs text-gray-500">${product.barcode}</div>
                                            </td>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-blue-600">
                                                ${product.reserved_quantity} ${product.unit}
                                            </td>
                                            <td class="px-4 py-2 whitespace-nowrap">
                                                ${statusBadge}
                                            </td>
                                        </tr>
                                    `);
                                });
                            }

                            // Afficher la modal
                            openProjectDetailsModal();
                        } else {
                            console.error('Erreur lors du chargement des détails du projet:', response.message);
                            showNotification('Erreur lors du chargement des détails du projet', 'error');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erreur AJAX:', error);
                        showNotification('Une erreur est survenue lors du chargement des détails du projet', 'error');
                    }
                });
            }

            function openProjectDetailsModal() {
                $('#projectDetailsModal').removeClass('hidden');
                setTimeout(() => {
                    $('#projectModalOverlay').addClass('opacity-100');
                    $('#projectModalContent').removeClass('opacity-0 scale-95').addClass('opacity-100 scale-100');
                }, 50);
            }

            function closeProjectDetailsModal() {
                $('#projectModalOverlay').removeClass('opacity-100');
                $('#projectModalContent').removeClass('opacity-100 scale-100').addClass('opacity-0 scale-95');
                setTimeout(() => {
                    $('#projectDetailsModal').addClass('hidden');
                }, 300);
            }

            // Fonction pour libérer une réservation
            function releaseReservation(reservationId, projectId, productId) {
                $.ajax({
                    url: 'api/release_reservation.php',
                    type: 'POST',
                    data: {
                        project_id: projectId,
                        product_id: productId
                    },
                    dataType: 'json',
                    success: function (response) {
                        closeReleaseModal();

                        if (response.success) {
                            showNotification('La réservation a été libérée avec succès', 'success');
                            loadReservations(); // Recharger les données
                        } else {
                            console.error('Erreur lors de la libération de la réservation:', response.message);
                            showNotification('Erreur: ' + response.message, 'error');
                        }
                    },
                    error: function (xhr, status, error) {
                        closeReleaseModal();
                        console.error('Erreur AJAX:', error);
                        showNotification('Une erreur est survenue lors de la libération de la réservation', 'error');
                    }
                });
            }

            // Gestionnaires d'événements

            // Événements de filtre
            $('#searchInput').on('input', function () {
                applyFilters();
            });

            $('#projectFilter, #stockStatusFilter').on('change', function () {
                applyFilters();
            });

            $('#resetFiltersBtn').on('click', function () {
                $('#searchInput').val('');
                $('#projectFilter').val('');
                $('#stockStatusFilter').val('');
                applyFilters();
            });

            // Événement pour l'ouverture de la modal de libération
            $(document).on('click', '.release-reservation-btn', function () {
                if (!$(this).prop('disabled')) {
                    const reservationData = {
                        reservationId: $(this).data('reservation-id'),
                        projectId: $(this).data('project-id'),
                        projectName: $(this).data('project-name'),
                        productId: $(this).data('product-id'),
                        productName: $(this).data('product-name'),
                        quantity: $(this).data('quantity')
                    };

                    openReleaseModal(reservationData);
                }
            });

            // Événements pour fermer la modal de libération
            $('#closeModalBtn, #cancelReleaseBtn, #modalOverlay').on('click', function (e) {
                if (e.target === this) {
                    closeReleaseModal();
                }
            });

            // Événement pour confirmer la libération
            $('#confirmReleaseBtn').on('click', function () {
                const reservationId = $(this).data('reservation-id');
                const projectId = $(this).data('project-id');
                const productId = $(this).data('product-id');

                releaseReservation(reservationId, projectId, productId);
            });

            // Événement pour voir les détails du projet
            $(document).on('click', '.project-details-link', function () {
                const projectId = $(this).data('project-id');
                loadProjectDetails(projectId);
            });

            // Événements pour fermer la modal de détails du projet
            $('#closeProjectModalBtn, #projectModalOverlay').on('click', function (e) {
                if (e.target === this) {
                    closeProjectDetailsModal();
                }
            });

            // Fermer les modals avec la touche Escape
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    if (!$('#releaseModal').hasClass('hidden')) {
                        closeReleaseModal();
                    }
                    if (!$('#projectDetailsModal').hasClass('hidden')) {
                        closeProjectDetailsModal();
                    }
                }
            });

            // Initialisation
            loadProjects();
            loadReservations();

        });
    </script>
</body>

</html>