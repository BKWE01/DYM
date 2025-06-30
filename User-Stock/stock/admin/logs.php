<?php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs d'activité - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" rel="stylesheet">
    <style>
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-stock-entry {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .badge-stock-output {
            background-color: #fce4ec;
            color: #e91e63;
        }

        .badge-product {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .badge-user {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .badge-invoice {
            background-color: #f3e5f5;
            color: #8e24aa;
        }

        .badge-error {
            background-color: #ffebee;
            color: #d32f2f;
        }

        .badge-other {
            background-color: #f5f5f5;
            color: #616161;
        }

        /* Styles pour le DateRangePicker */
        .daterangepicker {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 0.875rem;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .daterangepicker .ranges li.active {
            background-color: #4F46E5;
        }

        .daterangepicker td.active, .daterangepicker td.active:hover {
            background-color: #4F46E5;
        }

        /* Styles pour les filtres */
        .filter-container {
            transition: max-height 0.3s ease;
            overflow: hidden;
        }

        .filter-container.collapsed {
            max-height: 0;
        }

        .filter-container.expanded {
            max-height: 500px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include_once dirname(__DIR__) .'/sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once dirname(__DIR__) .'/header.php'; ?>

            <main class="p-4 flex-1 overflow-auto">
                <div class="bg-white p-4 rounded-lg shadow mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold">Logs d'activité</h2>
                        
                        <div class="flex items-center">
                            <button id="toggleFilters" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded flex items-center text-sm mr-2">
                                <span class="material-icons text-base mr-1">filter_list</span>
                                Filtres
                                <span id="filterIcon" class="material-icons text-base ml-1">expand_more</span>
                            </button>
                            
                            <div class="relative">
                                <input type="text" id="dateRangePicker" class="border rounded px-3 py-1 w-60 text-sm" placeholder="Sélectionner une période">
                                <span class="material-icons absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400">date_range</span>
                            </div>
                        </div>
                    </div>

                    <!-- Zone de filtres avancés (masquée par défaut) -->
                    <div id="filtersContainer" class="filter-container collapsed mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 rounded-lg border">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type d'action</label>
                                <select id="actionTypeFilter" class="w-full px-3 py-2 border rounded">
                                    <option value="">Toutes les actions</option>
                                    <option value="stock_entry">Entrée de stock</option>
                                    <option value="stock_output">Sortie de stock</option>
                                    <option value="product_add">Ajout de produit</option>
                                    <option value="product_edit">Modification de produit</option>
                                    <option value="product_delete">Suppression de produit</option>
                                    <option value="user_login">Connexion</option>
                                    <option value="user_logout">Déconnexion</option>
                                    <option value="invoice_upload">Upload de facture</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur</label>
                                <select id="userFilter" class="w-full px-3 py-2 border rounded">
                                    <option value="">Tous les utilisateurs</option>
                                    <!-- Chargé dynamiquement -->
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                                <input type="text" id="searchInput" class="w-full px-3 py-2 border rounded" placeholder="Rechercher...">
                            </div>
                            
                            <div class="md:col-span-3 flex justify-end mt-2">
                                <button id="resetFilters" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded text-sm mr-2">
                                    Réinitialiser les filtres
                                </button>
                                <button id="applyFilters" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded text-sm">
                                    Appliquer les filtres
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tableau des logs -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilisateur</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Élément</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Détails</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody" class="bg-white divide-y divide-gray-200">
                                <!-- Les logs seront insérés ici dynamiquement -->
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">Chargement des logs...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="pagination" class="mt-4 flex justify-center space-x-2">
                        <!-- Les boutons de pagination seront insérés ici -->
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment/locale/fr.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisation des variables
            let currentPage = 1;
            const itemsPerPage = 25;
            let totalPages = 1;
            let startDate = moment().subtract(7, 'days').format('YYYY-MM-DD');
            let endDate = moment().format('YYYY-MM-DD');
            let actionType = '';
            let userId = '';
            let searchQuery = '';

            // Configuration du sélecteur de date
            $('#dateRangePicker').daterangepicker({
                startDate: moment().subtract(7, 'days'),
                endDate: moment(),
                ranges: {
                   'Aujourd\'hui': [moment(), moment()],
                   'Hier': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                   '7 derniers jours': [moment().subtract(6, 'days'), moment()],
                   '30 derniers jours': [moment().subtract(29, 'days'), moment()],
                   'Ce mois': [moment().startOf('month'), moment().endOf('month')],
                   'Mois précédent': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },
                locale: {
                    format: 'DD/MM/YYYY',
                    separator: ' - ',
                    applyLabel: 'Appliquer',
                    cancelLabel: 'Annuler',
                    fromLabel: 'Du',
                    toLabel: 'Au',
                    customRangeLabel: 'Période personnalisée',
                    weekLabel: 'S',
                    daysOfWeek: ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa'],
                    monthNames: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
                    firstDay: 1
                }
            }, function(start, end) {
                startDate = start.format('YYYY-MM-DD');
                endDate = end.format('YYYY-MM-DD');
                loadLogs();
            });

            // Charger les utilisateurs pour le filtre
            loadUsers();

            // Fonction pour charger la liste des utilisateurs
            function loadUsers() {
                $.ajax({
                    url: 'api_getUsers.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const userSelect = $('#userFilter');
                            userSelect.empty().append('<option value="">Tous les utilisateurs</option>');
                            
                            response.users.forEach(user => {
                                userSelect.append(`<option value="${user.id}">${user.name}</option>`);
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur lors du chargement des utilisateurs:', error);
                        console.log('Réponse complète:', xhr.responseText); // Ajout pour déboguer
                    }
                });
            }

            // Fonction pour charger les logs
            function loadLogs() {
                $('#logsTableBody').html('<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Chargement des logs...</td></tr>');
                
                $.ajax({
                    url: 'api_getLogs.php',
                    type: 'GET',
                    data: {
                        page: currentPage,
                        limit: itemsPerPage,
                        start_date: startDate,
                        end_date: endDate,
                        action_type: actionType,
                        user_id: userId,
                        search: searchQuery
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            displayLogs(response.logs);
                            totalPages = Math.ceil(response.total / itemsPerPage);
                            updatePagination();
                        } else {
                            $('#logsTableBody').html(`<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">${response.message}</td></tr>`);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur AJAX:', error);
                        console.log('Réponse complète:', xhr.responseText); // Ajout pour déboguer
                        $('#logsTableBody').html('<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">Erreur lors du chargement des logs</td></tr>');
                    }
                });
            }

            // Fonction pour afficher les logs
            function displayLogs(logs) {
                const tableBody = $('#logsTableBody');
                tableBody.empty();
                
                if (logs.length === 0) {
                    tableBody.html('<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Aucun log trouvé</td></tr>');
                    return;
                }
                
                logs.forEach(log => {
                    // Déterminer le type de badge
                    let badgeClass = 'badge-other';
                    let actionDisplay = log.action;
                    
                    if (log.action.includes('stock_entry')) {
                        badgeClass = 'badge-stock-entry';
                        actionDisplay = 'Entrée de stock';
                    } else if (log.action.includes('stock_output')) {
                        badgeClass = 'badge-stock-output';
                        actionDisplay = 'Sortie de stock';
                    } else if (log.action.includes('product_')) {
                        badgeClass = 'badge-product';
                        
                        if (log.action === 'product_add') {
                            actionDisplay = 'Ajout de produit';
                        } else if (log.action === 'product_edit') {
                            actionDisplay = 'Modification de produit';
                        } else if (log.action === 'product_delete') {
                            actionDisplay = 'Suppression de produit';
                        }
                    } else if (log.action.includes('user_')) {
                        badgeClass = 'badge-user';
                        
                        if (log.action === 'user_login') {
                            actionDisplay = 'Connexion';
                        } else if (log.action === 'user_logout') {
                            actionDisplay = 'Déconnexion';
                        }
                    } else if (log.action.includes('invoice_')) {
                        badgeClass = 'badge-invoice';
                        actionDisplay = 'Upload de facture';
                    } else if (log.action.includes('error')) {
                        badgeClass = 'badge-error';
                        actionDisplay = 'Erreur';
                    }
                    
                    // Formater la date
                    const formattedDate = moment(log.created_at).format('DD/MM/YYYY HH:mm:ss');
                    
                    // Préparer le contenu du tableau
                    const row = `
                        <tr class="hover:bg-gray-50 cursor-pointer">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${formattedDate}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${log.username || 'N/A'}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="badge ${badgeClass}">${actionDisplay}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${log.entity_name || 'N/A'}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 hover:text-blue-800">
                                <a href="view_log_details.php?id=${log.id}" class="flex items-center text-blue-600 hover:text-blue-800">
                                    <span class="material-icons text-base">visibility</span>
                                    <span class="ml-1">Détails</span>
                                </a>
                            </td>
                        </tr>
                    `;
                    
                    tableBody.append(row);
                });
            }

            // Fonction pour mettre à jour la pagination
            function updatePagination() {
                const pagination = $('#pagination');
                pagination.empty();
                
                if (totalPages <= 1) {
                    return;
                }
                
                // Bouton précédent
                pagination.append(`
                    <button class="px-3 py-1 rounded ${currentPage === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}" 
                            ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">
                        &lt;
                    </button>
                `);
                
                // Numéros de page
                const maxPages = 7;
                let startPage = Math.max(1, currentPage - Math.floor(maxPages / 2));
                let endPage = Math.min(totalPages, startPage + maxPages - 1);
                
                if (endPage - startPage + 1 < maxPages) {
                    startPage = Math.max(1, endPage - maxPages + 1);
                }
                
                for (let i = startPage; i <= endPage; i++) {
                    pagination.append(`
                        <button class="px-3 py-1 rounded ${i === currentPage ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}" 
                                data-page="${i}">
                            ${i}
                        </button>
                    `);
                }
                
                // Bouton suivant
                pagination.append(`
                    <button class="px-3 py-1 rounded ${currentPage === totalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}" 
                            ${currentPage === totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">
                        &gt;
                    </button>
                `);
                
                // Ajouter les événements aux boutons de pagination
                $('#pagination button').on('click', function() {
                    if (!$(this).prop('disabled')) {
                        currentPage = parseInt($(this).data('page'));
                        loadLogs();
                    }
                });
            }

            // Gestionnaire d'événements pour le toggle des filtres
            $('#toggleFilters').on('click', function() {
                const filtersContainer = $('#filtersContainer');
                const filterIcon = $('#filterIcon');
                
                if (filtersContainer.hasClass('collapsed')) {
                    filtersContainer.removeClass('collapsed').addClass('expanded');
                    filterIcon.text('expand_less');
                } else {
                    filtersContainer.removeClass('expanded').addClass('collapsed');
                    filterIcon.text('expand_more');
                }
            });

            // Gestionnaire d'événements pour l'application des filtres
            $('#applyFilters').on('click', function() {
                actionType = $('#actionTypeFilter').val();
                userId = $('#userFilter').val();
                searchQuery = $('#searchInput').val();
                currentPage = 1;
                loadLogs();
            });

            // Gestionnaire d'événements pour la réinitialisation des filtres
            $('#resetFilters').on('click', function() {
                $('#actionTypeFilter').val('');
                $('#userFilter').val('');
                $('#searchInput').val('');
                actionType = '';
                userId = '';
                searchQuery = '';
                currentPage = 1;
                loadLogs();
            });

            // Chargement initial des logs
            loadLogs();
        });
    </script>
</body>
</html>