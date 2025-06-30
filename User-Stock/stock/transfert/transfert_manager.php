<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Transferts - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/antd/4.16.13/antd.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
        }

        .ant-card {
            box-shadow: 0 1px 2px -2px rgba(0, 0, 0, 0.16), 0 3px 6px 0 rgba(0, 0, 0, 0.12), 0 5px 12px 4px rgba(0, 0, 0, 0.09);
        }

        /* Animation shake pour les erreurs */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .shake {
            animation: shake 0.6s;
        }

        /* Style pour les badges de statut */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            color: #fff;
            white-space: nowrap;
            border-radius: 9999px;
        }

        .status-pending {
            background-color: #f59e0b;
        }

        .status-completed {
            background-color: #10b981;
        }

        .status-canceled {
            background-color: #ef4444;
        }

        /* Style pour le menu contextuel */
        .context-menu {
            position: absolute;
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            z-index: 50;
        }

        .context-menu-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .context-menu-item:hover {
            background-color: #f3f4f6;
        }

        /* Style pour le mode sombre du modal */
        .dark-modal .ant-modal-content {
            background-color: #1f2937;
            color: #e5e7eb;
        }

        .dark-modal .ant-modal-header {
            background-color: #1f2937;
            border-bottom: 1px solid #374151;
        }

        .dark-modal .ant-modal-title {
            color: #e5e7eb;
        }

        .dark-modal .ant-modal-close {
            color: #e5e7eb;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react/17.0.2/umd/react.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react-dom/17.0.2/umd/react-dom.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/antd/4.16.13/antd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include_once '../sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once '../header.php'; ?>

            <main class="p-6 flex-1">
                <div class="bg-white p-6 rounded-lg shadow-md ant-card">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold">Gestion des Transferts</h1>
                        <a href="create_transfert.php" 
                           class="flex items-center bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition-colors duration-300">
                            <span class="material-icons mr-2">swap_horiz</span>
                            Nouveau transfert
                        </a>
                    </div>

                    <!-- Onglets pour les différents statuts de transfert -->
                    <div class="mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8" id="tabs">
                                <a href="#" class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" 
                                   data-status="all">
                                    Tous les transferts
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" 
                                   data-status="pending">
                                    En attente
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" 
                                   data-status="completed">
                                    Complétés
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" 
                                   data-status="canceled">
                                    Annulés
                                </a>
                            </nav>
                        </div>
                    </div>

                    <!-- Barre de recherche et filtres -->
                    <div class="mb-6 flex flex-wrap gap-4">
                        <div class="relative w-full md:w-96">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="material-icons text-gray-400">search</span>
                            </span>
                            <input type="text" id="searchInput" placeholder="Rechercher par produit, projet source ou destination..." 
                                   class="pl-10 p-2 border border-gray-300 rounded-md w-full">
                        </div>
                        <div class="flex space-x-4">
                            <select id="productFilter" class="p-2 border border-gray-300 rounded-md">
                                <option value="">Tous les produits</option>
                                <!-- Les produits seront chargés dynamiquement ici -->
                            </select>
                            <select id="dateFilter" class="p-2 border border-gray-300 rounded-md">
                                <option value="">Toutes les dates</option>
                                <option value="today">Aujourd'hui</option>
                                <option value="week">Cette semaine</option>
                                <option value="month">Ce mois</option>
                                <option value="custom">Personnalisé...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tableau des transferts -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ID
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Produit
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Quantité
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Projet source
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Projet destination
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Demandé par
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Statut
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="transfertTableBody" class="bg-white divide-y divide-gray-200">
                                <!-- Les transferts seront chargés dynamiquement ici -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="mt-4 flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            Affichage de <span id="startRange">1</span> à <span id="endRange">10</span> sur <span id="totalItems">--</span> transferts
                        </div>
                        <div class="flex space-x-2" id="pagination">
                            <!-- La pagination sera générée dynamiquement ici -->
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Menu contextuel pour les actions (sera positionné dynamiquement) -->
    <div id="contextMenu" class="context-menu hidden">
        <div class="context-menu-item view-details">
            <span class="material-icons mr-2 text-blue-500">visibility</span>
            Voir les détails
        </div>
        <div class="context-menu-item edit-transfert">
            <span class="material-icons mr-2 text-green-500">edit</span>
            Modifier
        </div>
        <div class="context-menu-item complete-transfert">
            <span class="material-icons mr-2 text-purple-500">check_circle</span>
            Marquer comme complété
        </div>
        <div class="context-menu-item cancel-transfert">
            <span class="material-icons mr-2 text-red-500">cancel</span>
            Annuler le transfert
        </div>
    </div>

    <!-- Modal pour les détails du transfert -->
    <div id="detailsModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="fixed inset-0 bg-black opacity-50" id="modalOverlay"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all max-w-lg w-full">
            <div class="px-6 py-4 bg-gray-100 border-b">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Détails du transfert</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" id="closeDetailsModal">
                        <span class="material-icons">close</span>
                    </button>
                </div>
            </div>
            <div class="p-6" id="transfertDetailsContent">
                <!-- Le contenu des détails du transfert sera inséré ici dynamiquement -->
            </div>
            <div class="px-6 py-4 bg-gray-100 flex justify-end">
                <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 mr-2" id="closeModalBtn">
                    Fermer
                </button>
                <button type="button" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600" id="printDetailsBtn">
                    <span class="material-icons mr-1 text-sm">print</span>
                    Imprimer
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestionnaire d'onglets
            const tabs = document.querySelectorAll('#tabs a');
            let currentStatus = 'all';
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    tabs.forEach(t => {
                        t.classList.remove('border-blue-500', 'text-blue-600');
                        t.classList.add('border-transparent', 'text-gray-500');
                    });
                    this.classList.remove('border-transparent', 'text-gray-500');
                    this.classList.add('border-blue-500', 'text-blue-600');
                    currentStatus = this.getAttribute('data-status');
                    loadTransferts(currentStatus);
                });
            });

            // Fonctions pour charger les données
            function loadTransferts(status = 'all') {
                const searchQuery = document.getElementById('searchInput').value;
                const productFilter = document.getElementById('productFilter').value;
                const dateFilter = document.getElementById('dateFilter').value;
                
                // Afficher un indicateur de chargement
                document.getElementById('transfertTableBody').innerHTML = `
                    <tr>
                        <td colspan="9" class="px-6 py-4 text-center">
                            <div class="flex justify-center">
                                <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </td>
                    </tr>
                `;
                
                // Appel AJAX pour récupérer les transferts
                fetch(`api_get_transferts.php?status=${status}&search=${searchQuery}&product=${productFilter}&date=${dateFilter}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            renderTransferts(data.transferts);
                            updatePagination(data.pagination);
                            loadProductsFilter(data.products);
                        } else {
                            document.getElementById('transfertTableBody').innerHTML = `
                                <tr>
                                    <td colspan="9" class="px-6 py-4 text-center text-red-500">
                                        ${data.message || 'Une erreur est survenue lors du chargement des transferts.'}
                                    </td>
                                </tr>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        document.getElementById('transfertTableBody').innerHTML = `
                            <tr>
                                <td colspan="9" class="px-6 py-4 text-center text-red-500">
                                    Une erreur est survenue lors de la communication avec le serveur.
                                </td>
                            </tr>
                        `;
                    });
            }

            function renderTransferts(transferts) {
                const tableBody = document.getElementById('transfertTableBody');
                tableBody.innerHTML = '';

                if (transferts.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                Aucun transfert trouvé.
                            </td>
                        </tr>
                    `;
                    return;
                }

                transferts.forEach(transfert => {
                    let statusClass = '';
                    let statusText = '';
                    
                    switch (transfert.status) {
                        case 'pending':
                            statusClass = 'status-pending';
                            statusText = 'En attente';
                            break;
                        case 'completed':
                            statusClass = 'status-completed';
                            statusText = 'Complété';
                            break;
                        case 'canceled':
                            statusClass = 'status-canceled';
                            statusText = 'Annulé';
                            break;
                    }

                    const row = document.createElement('tr');
                    row.classList.add('hover:bg-gray-50', 'cursor-pointer');
                    row.setAttribute('data-id', transfert.id);
                    row.setAttribute('data-status', transfert.status);
                    
                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${transfert.id}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">${transfert.product_name}</div>
                            <div class="text-xs text-gray-500">${transfert.barcode || ''}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${transfert.quantity}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${transfert.source_project}</div>
                            <div class="text-xs text-gray-500">${transfert.source_project_code}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${transfert.destination_project}</div>
                            <div class="text-xs text-gray-500">${transfert.destination_project_code}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${formatDate(transfert.created_at)}</div>
                            <div class="text-xs text-gray-500">${formatTime(transfert.created_at)}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${transfert.requested_by}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="status-badge ${statusClass}">${statusText}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button class="text-blue-600 hover:text-blue-900 view-details-btn" data-id="${transfert.id}">
                                <span class="material-icons">visibility</span>
                            </button>
                            ${transfert.status === 'pending' ? `
                                <button class="text-green-600 hover:text-green-900 ml-2 complete-btn" data-id="${transfert.id}">
                                    <span class="material-icons">check_circle</span>
                                </button>
                                <button class="text-red-600 hover:text-red-900 ml-2 cancel-btn" data-id="${transfert.id}">
                                    <span class="material-icons">cancel</span>
                                </button>
                            ` : ''}
                        </td>
                    `;

                    tableBody.appendChild(row);
                });

                // Ajouter des événements aux boutons
                document.querySelectorAll('.view-details-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const transfertId = this.getAttribute('data-id');
                        showTransfertDetails(transfertId);
                    });
                });

                document.querySelectorAll('.complete-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const transfertId = this.getAttribute('data-id');
                        completeTransfert(transfertId);
                    });
                });

                document.querySelectorAll('.cancel-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const transfertId = this.getAttribute('data-id');
                        cancelTransfert(transfertId);
                    });
                });

                // Ajouter un événement de clic sur les lignes pour afficher les détails
                document.querySelectorAll('#transfertTableBody tr').forEach(row => {
                    row.addEventListener('click', function() {
                        const transfertId = this.getAttribute('data-id');
                        showTransfertDetails(transfertId);
                    });

                    // Ajouter un gestionnaire pour le menu contextuel (clic droit)
                    row.addEventListener('contextmenu', function(e) {
                        e.preventDefault();
                        const transfertId = this.getAttribute('data-id');
                        const status = this.getAttribute('data-status');
                        showContextMenu(e, transfertId, status);
                    });
                });
            }

            function updatePagination(pagination) {
                const paginationContainer = document.getElementById('pagination');
                paginationContainer.innerHTML = '';

                // Mettre à jour les informations de plage
                document.getElementById('startRange').textContent = pagination.start;
                document.getElementById('endRange').textContent = pagination.end;
                document.getElementById('totalItems').textContent = pagination.total;

                // Bouton précédent
                const prevButton = document.createElement('button');
                prevButton.className = 'px-3 py-1 rounded-md bg-gray-200 text-gray-700';
                prevButton.innerHTML = '<span class="material-icons">chevron_left</span>';
                prevButton.disabled = pagination.current_page === 1;
                if (pagination.current_page > 1) {
                    prevButton.addEventListener('click', () => loadPage(pagination.current_page - 1));
                }
                paginationContainer.appendChild(prevButton);

                // Boutons de page
                for (let i = 1; i <= pagination.total_pages; i++) {
                    if (
                        i === 1 ||                              // Première page
                        i === pagination.total_pages ||         // Dernière page
                        Math.abs(i - pagination.current_page) < 2  // Pages proches de la page actuelle
                    ) {
                        const pageButton = document.createElement('button');
                        pageButton.className = i === pagination.current_page
                            ? 'px-3 py-1 rounded-md bg-blue-500 text-white'
                            : 'px-3 py-1 rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300';
                        pageButton.textContent = i;
                        pageButton.addEventListener('click', () => loadPage(i));
                        paginationContainer.appendChild(pageButton);
                    } else if (
                        (i === 2 && pagination.current_page > 3) ||
                        (i === pagination.total_pages - 1 && pagination.current_page < pagination.total_pages - 2)
                    ) {
                        // Ajouter des ellipses pour les sauts de page
                        const ellipsis = document.createElement('span');
                        ellipsis.className = 'px-3 py-1';
                        ellipsis.textContent = '...';
                        paginationContainer.appendChild(ellipsis);
                    }
                }

                // Bouton suivant
                const nextButton = document.createElement('button');
                nextButton.className = 'px-3 py-1 rounded-md bg-gray-200 text-gray-700';
                nextButton.innerHTML = '<span class="material-icons">chevron_right</span>';
                nextButton.disabled = pagination.current_page === pagination.total_pages;
                if (pagination.current_page < pagination.total_pages) {
                    nextButton.addEventListener('click', () => loadPage(pagination.current_page + 1));
                }
                paginationContainer.appendChild(nextButton);
            }

            function loadPage(page) {
                // Ajouter le paramètre de page à la requête de chargement des transferts
                // Implémenter cette logique si nécessaire
                loadTransferts(currentStatus, page);
            }

            function loadProductsFilter(products) {
                const productFilter = document.getElementById('productFilter');
                // Conserver l'option sélectionnée actuelle
                const currentValue = productFilter.value;
                productFilter.innerHTML = '<option value="">Tous les produits</option>';

                products.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.id;
                    option.textContent = product.product_name;
                    productFilter.appendChild(option);
                });

                // Restaurer la sélection précédente si elle existe
                if (currentValue) {
                    productFilter.value = currentValue;
                }
            }

            // Fonctions pour les actions sur les transferts
            function showTransfertDetails(transfertId) {
                // Appel AJAX pour récupérer les détails du transfert
                fetch(`api_get_transfert_details.php?id=${transfertId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            renderTransfertDetails(data.transfert);
                            showModal('detailsModal');
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message || 'Impossible de récupérer les détails du transfert.'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Une erreur est survenue lors de la communication avec le serveur.'
                        });
                    });
            }

            function renderTransfertDetails(transfert) {
                let statusClass = '';
                let statusText = '';
                
                switch (transfert.status) {
                    case 'pending':
                        statusClass = 'bg-yellow-100 text-yellow-800';
                        statusText = 'En attente';
                        break;
                    case 'completed':
                        statusClass = 'bg-green-100 text-green-800';
                        statusText = 'Complété';
                        break;
                    case 'canceled':
                        statusClass = 'bg-red-100 text-red-800';
                        statusText = 'Annulé';
                        break;
                }

                const completedInfo = transfert.completed_at 
                    ? `<div class="mt-2 text-sm text-gray-600">Complété le ${formatDate(transfert.completed_at)} à ${formatTime(transfert.completed_at)}</div>
                       <div class="text-sm text-gray-600">Traité par: ${transfert.completed_by || 'N/A'}</div>`
                    : '';

                const canceledInfo = transfert.canceled_at 
                    ? `<div class="mt-2 text-sm text-gray-600">Annulé le ${formatDate(transfert.canceled_at)} à ${formatTime(transfert.canceled_at)}</div>
                       <div class="text-sm text-gray-600">Annulé par: ${transfert.canceled_by || 'N/A'}</div>`
                    : '';

                const notesSection = transfert.notes 
                    ? `<div class="mt-4">
                           <h4 class="text-sm font-medium text-gray-700">Notes</h4>
                           <p class="text-sm text-gray-600 italic bg-gray-50 p-3 rounded">${transfert.notes}</p>
                       </div>`
                    : '';

                const detailsContent = `
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Transfert #${transfert.id}</h3>
                            <span class="px-3 py-1 rounded-full text-sm font-semibold ${statusClass}">${statusText}</span>
                        </div>
                        
                        <div class="border-t border-b py-3">
                            <h4 class="text-sm font-medium text-gray-700">Produit</h4>
                            <div class="text-sm text-gray-900 font-medium">${transfert.product_name}</div>
                            <div class="text-xs text-gray-500">${transfert.barcode || ''}</div>
                            <div class="mt-2 text-sm font-medium text-blue-600">Quantité: ${transfert.quantity}</div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 border-b pb-3">
                            <div>
                                <h4 class="text-sm font-medium text-gray-700">Projet source</h4>
                                <div class="text-sm text-gray-900">${transfert.source_project}</div>
                                <div class="text-xs text-gray-500">Code: ${transfert.source_project_code}</div>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-700">Projet destination</h4>
                                <div class="text-sm text-gray-900">${transfert.destination_project}</div>
                                <div class="text-xs text-gray-500">Code: ${transfert.destination_project_code}</div>
                            </div>
                        </div>
                        
                        <div class="border-b pb-3">
                            <h4 class="text-sm font-medium text-gray-700">Informations de demande</h4>
                            <div class="text-sm text-gray-600">Demandé par: ${transfert.requested_by}</div>
                            <div class="text-sm text-gray-600">Date de demande: ${formatDate(transfert.created_at)} à ${formatTime(transfert.created_at)}</div>
                            ${completedInfo}
                            ${canceledInfo}
                        </div>
                        
                        ${notesSection}
                    </div>
                `;

                document.getElementById('transfertDetailsContent').innerHTML = detailsContent;
            }

            function completeTransfert(transfertId) {
                Swal.fire({
                    title: 'Confirmer le transfert',
                    text: 'Êtes-vous sûr de vouloir marquer ce transfert comme complété? Cette action mettra à jour les quantités réservées des projets.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, compléter',
                    cancelButtonText: 'Annuler',
                    confirmButtonColor: '#10b981',
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Appel AJAX pour compléter le transfert
                        fetch('api_complete_transfert.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                id: transfertId
                            }),
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Transfert complété',
                                    text: data.message,
                                    icon: 'success',
                                    confirmButtonColor: '#10b981',
                                }).then(() => {
                                    loadTransferts(currentStatus);
                                });
                            } else {
                                Swal.fire({
                                    title: 'Erreur',
                                    text: data.message,
                                    icon: 'error',
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            Swal.fire({
                                title: 'Erreur',
                                text: 'Une erreur est survenue lors de la communication avec le serveur.',
                                icon: 'error',
                            });
                        });
                    }
                });
            }

            function cancelTransfert(transfertId) {
                Swal.fire({
                    title: 'Annuler le transfert',
                    text: 'Êtes-vous sûr de vouloir annuler ce transfert? Cette action ne peut pas être annulée.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, annuler',
                    cancelButtonText: 'Retour',
                    confirmButtonColor: '#ef4444',
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Appel AJAX pour annuler le transfert
                        fetch('api_cancel_transfert.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                id: transfertId
                            }),
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Transfert annulé',
                                    text: data.message,
                                    icon: 'success',
                                }).then(() => {
                                    loadTransferts(currentStatus);
                                });
                            } else {
                                Swal.fire({
                                    title: 'Erreur',
                                    text: data.message,
                                    icon: 'error',
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            Swal.fire({
                                title: 'Erreur',
                                text: 'Une erreur est survenue lors de la communication avec le serveur.',
                                icon: 'error',
                            });
                        });
                    }
                });
            }

            // Fonctions pour le menu contextuel
            function showContextMenu(event, transfertId, status) {
                const contextMenu = document.getElementById('contextMenu');
                contextMenu.style.left = `${event.pageX}px`;
                contextMenu.style.top = `${event.pageY}px`;
                
                // Définir les attributs data pour le menu
                contextMenu.setAttribute('data-id', transfertId);
                
                // Afficher/masquer les options selon le statut
                const completeOption = contextMenu.querySelector('.complete-transfert');
                const cancelOption = contextMenu.querySelector('.cancel-transfert');
                const editOption = contextMenu.querySelector('.edit-transfert');
                
                if (status === 'pending') {
                    completeOption.classList.remove('hidden');
                    cancelOption.classList.remove('hidden');
                    editOption.classList.remove('hidden');
                } else {
                    completeOption.classList.add('hidden');
                    cancelOption.classList.add('hidden');
                    editOption.classList.add('hidden');
                }
                
                contextMenu.classList.remove('hidden');
                
                // Fermer le menu sur clic extérieur
                const handleOutsideClick = function(e) {
                    if (!contextMenu.contains(e.target)) {
                        contextMenu.classList.add('hidden');
                        document.removeEventListener('click', handleOutsideClick);
                    }
                };
                
                setTimeout(() => {
                    document.addEventListener('click', handleOutsideClick);
                }, 10);
            }

            // Gestionnaires d'événements pour le menu contextuel
            document.querySelector('.context-menu .view-details').addEventListener('click', function() {
                const transfertId = document.getElementById('contextMenu').getAttribute('data-id');
                showTransfertDetails(transfertId);
            });
            
            document.querySelector('.context-menu .edit-transfert').addEventListener('click', function() {
                const transfertId = document.getElementById('contextMenu').getAttribute('data-id');
                window.location.href = `edit_transfert.php?id=${transfertId}`;
            });
            
            document.querySelector('.context-menu .complete-transfert').addEventListener('click', function() {
                const transfertId = document.getElementById('contextMenu').getAttribute('data-id');
                completeTransfert(transfertId);
            });
            
            document.querySelector('.context-menu .cancel-transfert').addEventListener('click', function() {
                const transfertId = document.getElementById('contextMenu').getAttribute('data-id');
                cancelTransfert(transfertId);
            });

            // Fonctions utilitaires
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            }
            
            function formatTime(dateString) {
                const date = new Date(dateString);
                return date.toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function showModal(modalId) {
                const modal = document.getElementById(modalId);
                modal.classList.remove('hidden');
                
                // Animation d'entrée
                const modalContent = modal.querySelector('div:nth-child(2)');
                modalContent.classList.add('scale-100', 'opacity-100');
                
                // Fermeture du modal
                const closeButtons = modal.querySelectorAll('#closeDetailsModal, #closeModalBtn, #modalOverlay');
                closeButtons.forEach(button => {
                    button.addEventListener('click', () => hideModal(modalId));
                });
                
                // Impression des détails
                const printButton = document.getElementById('printDetailsBtn');
                if (printButton) {
                    printButton.addEventListener('click', printTransfertDetails);
                }
            }
            
            function hideModal(modalId) {
                const modal = document.getElementById(modalId);
                
                // Animation de sortie
                const modalContent = modal.querySelector('div:nth-child(2)');
                modalContent.classList.remove('scale-100', 'opacity-100');
                
                setTimeout(() => {
                    modal.classList.add('hidden');
                }, 300);
            }
            
            function printTransfertDetails() {
                const detailsContent = document.getElementById('transfertDetailsContent').innerHTML;
                const transfertId = document.getElementById('transfertDetailsContent').querySelector('h3').textContent.replace('Transfert #', '');
                
                // Créer une fenêtre d'impression
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                    <head>
                        <title>Transfert #${transfertId} - Détails</title>
                        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                padding: 20px;
                            }
                            .print-header {
                                text-align: center;
                                margin-bottom: 20px;
                                padding-bottom: 20px;
                                border-bottom: 1px solid #e5e7eb;
                            }
                            .print-footer {
                                margin-top: 30px;
                                padding-top: 20px;
                                border-top: 1px solid #e5e7eb;
                                font-size: 0.75rem;
                                color: #6b7280;
                            }
                            @media print {
                                @page {
                                    size: A4;
                                    margin: 2cm;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="print-header">
                            <h1 class="text-2xl font-bold">DYM STOCK - Détails du Transfert</h1>
                            <p>Date d'impression: ${new Date().toLocaleString('fr-FR')}</p>
                        </div>
                        ${detailsContent}
                        <div class="print-footer">
                            <p>Document généré par DYM STOCK - Système de gestion des transferts</p>
                            <p>Imprimé le ${new Date().toLocaleString('fr-FR')}</p>
                        </div>
                    </body>
                    </html>
                `);
                
                // Attendre que le contenu soit chargé puis imprimer
                printWindow.document.close();
                printWindow.onload = function() {
                    printWindow.print();
                    printWindow.onafterprint = function() {
                        printWindow.close();
                    };
                };
            }

            // Événements pour les filtres de recherche
            document.getElementById('searchInput').addEventListener('input', debounce(function() {
                loadTransferts(currentStatus);
            }, 500));
            
            document.getElementById('productFilter').addEventListener('change', function() {
                loadTransferts(currentStatus);
            });
            
            document.getElementById('dateFilter').addEventListener('change', function() {
                const selectedValue = this.value;
                
                if (selectedValue === 'custom') {
                    // Afficher un modal pour sélectionner une plage de dates personnalisée
                    Swal.fire({
                        title: 'Sélectionner une période',
                        html: `
                            <div class="flex flex-col space-y-4">
                                <div>
                                    <label for="startDate" class="block text-sm font-medium text-gray-700">Date de début</label>
                                    <input type="date" id="startDate" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                                </div>
                                <div>
                                    <label for="endDate" class="block text-sm font-medium text-gray-700">Date de fin</label>
                                    <input type="date" id="endDate" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                                </div>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Appliquer',
                        cancelButtonText: 'Annuler',
                        willOpen: () => {
                            // Initialiser avec la date d'aujourd'hui
                            const today = new Date();
                            const formattedDate = today.toISOString().split('T')[0];
                            document.getElementById('endDate').value = formattedDate;
                            
                            // Date de début par défaut (1 mois avant)
                            const oneMonthAgo = new Date();
                            oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
                            document.getElementById('startDate').value = oneMonthAgo.toISOString().split('T')[0];
                        },
                        preConfirm: () => {
                            const startDate = document.getElementById('startDate').value;
                            const endDate = document.getElementById('endDate').value;
                            
                            if (!startDate || !endDate) {
                                Swal.showValidationMessage('Veuillez sélectionner une date de début et de fin');
                                return false;
                            }
                            
                            if (new Date(startDate) > new Date(endDate)) {
                                Swal.showValidationMessage('La date de début doit être antérieure à la date de fin');
                                return false;
                            }
                            
                            return { startDate, endDate };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Utiliser les dates sélectionnées pour filtrer les transferts
                            // Implémenter cette fonctionnalité selon vos besoins
                            console.log('Dates sélectionnées:', result.value);
                            loadTransferts(currentStatus, 1, result.value);
                        } else {
                            // Réinitialiser la valeur du sélecteur si annulé
                            document.getElementById('dateFilter').value = '';
                        }
                    });
                } else {
                    loadTransferts(currentStatus);
                }
            });

            // Fonction utilitaire de debounce pour éviter trop d'appels lors de la saisie
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            // Chargement initial des transferts
            loadTransferts('all');
        });
    </script>
</body>
</html>