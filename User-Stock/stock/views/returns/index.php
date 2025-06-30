<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Rediriger vers index.php
    header("Location: ./../../../index.php");
    exit();
}

// Fonction de traduction des motifs de retour
function translateReturnReason($reason)
{
    $translations = [
        'unused' => 'Produit non utilisé',
        'excess' => 'Excédent de matériel',
        'wrong_product' => 'Produit erroné',
        'defective' => 'Produit défectueux',
        'project_completed' => 'Projet terminé',
        'other' => 'Autre'
    ];

    return isset($translations[$reason]) ? $translations[$reason] : $reason;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Retours - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

    <style>
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transform: translateY(-3px);
        }

        /* DataTables customization */
        table.dataTable {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        table.dataTable thead th,
        table.dataTable thead td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        table.dataTable tbody td {
            padding: 8px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.875rem;
        }

        table.dataTable tbody tr:nth-child(odd) {
            background-color: #f9fafb;
        }

        table.dataTable tbody tr:nth-child(even) {
            background-color: #ffffff;
        }

        table.dataTable tbody tr:hover {
            background-color: #f1f5f9;
            cursor: pointer;
        }

        /* Status badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            border-radius: 9999px;
        }

        .badge-green {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-blue {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-red {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .badge-yellow {
            background-color: #fef3c7;
            color: #92400e;
        }
    </style>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include_once '../../sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once '../../header.php'; ?>

            <main class="p-6 flex-1 overflow-y-auto">
                <div class="max-w-7xl mx-auto">
                    <!-- En-tête de la page -->
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Gestion des Retours en Stock</h1>
                            <p class="text-gray-600 mt-1">Gérez les retours de matériel préalablement sorti du stock</p>
                        </div>
                        <a href="add_return.php"
                            class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg shadow-md transition-all flex items-center">
                            <span class="material-icons-round mr-2">add_circle</span>
                            Nouveau Retour
                        </a>
                    </div>

                    <!-- Filtres -->
                    <div class="bg-white p-4 rounded-lg shadow-sm mb-6">
                        <div class="flex flex-wrap gap-4 items-center">
                            <div class="relative flex-grow md:max-w-xs">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons-round text-gray-400 text-base">search</span>
                                </span>
                                <input type="text" id="searchInput" placeholder="Rechercher..."
                                    class="pl-10 pr-4 py-2 w-full border rounded-md text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons-round text-gray-400 text-base">date_range</span>
                                </span>
                                <select id="dateFilter"
                                    class="pl-10 pr-8 py-2 border rounded-md text-sm appearance-none cursor-pointer focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    <option value="">Toutes les dates</option>
                                    <option value="today">Aujourd'hui</option>
                                    <option value="yesterday">Hier</option>
                                    <option value="week">Cette semaine</option>
                                    <option value="month">Ce mois</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <span class="material-icons-round text-gray-400 text-base">expand_more</span>
                                </span>
                            </div>

                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons-round text-gray-400 text-base">filter_list</span>
                                </span>
                                <select id="statusFilter"
                                    class="pl-10 pr-8 py-2 border rounded-md text-sm appearance-none cursor-pointer focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    <option value="">Tous les statuts</option>
                                    <option value="pending">En attente</option>
                                    <option value="approved">Approuvé</option>
                                    <option value="completed">Complété</option>
                                    <option value="rejected">Rejeté</option>
                                    <option value="canceled">Annulé</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <span class="material-icons-round text-gray-400 text-base">expand_more</span>
                                </span>
                            </div>

                            <button id="resetFilters"
                                class="flex items-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm bg-white hover:bg-gray-50">
                                <span class="material-icons-round text-gray-500 mr-1">refresh</span>
                                Réinitialiser
                            </button>
                        </div>
                    </div>

                    <!-- Tableau des retours -->
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div id="loading" class="flex justify-center items-center py-12">
                            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span class="text-gray-700">Chargement des retours...</span>
                        </div>

                        <div id="returnsTableContainer" class="hidden overflow-x-auto">
                            <table id="returnsTable" class="w-full">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Produit</th>
                                        <th>Quantité</th>
                                        <th>Origine</th>
                                        <th>Motif</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="returnsTableBody">
                                    <!-- Les données seront chargées par JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <div id="noReturns" class="hidden text-center py-12">
                            <div
                                class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <span class="material-icons-round text-gray-400 text-4xl">assignment_return</span>
                            </div>
                            <h3 class="text-lg font-medium text-gray-800 mb-2">Aucun retour trouvé</h3>
                            <p class="text-gray-500 mb-6">Aucun retour en stock n'a été enregistré pour le moment.</p>
                            <a href="add_return.php"
                                class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg inline-flex items-center">
                                <span class="material-icons-round mr-2">add_circle</span>
                                Créer un retour
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- DataTables JS et plugins -->
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>

    <script>
        $(document).ready(function () {
            let dataTable;

            // Fonction pour afficher les notifications
            function showNotification(message, type) {
                const backgroundColor = type === 'success' ? '#10b981' : '#ef4444';

                Toastify({
                    text: message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: backgroundColor,
                    stopOnFocus: true,
                }).showToast();
            }

            // Fonction pour charger les retours
            function loadReturns() {
                $('#loading').removeClass('hidden');
                $('#returnsTableContainer').addClass('hidden');
                $('#noReturns').addClass('hidden');

                // Collecter les valeurs des filtres
                const searchValue = $('#searchInput').val();
                const dateFilter = $('#dateFilter').val();
                const statusFilter = $('#statusFilter').val();

                $.ajax({
                    url: 'api/get_returns.php',
                    type: 'GET',
                    data: {
                        search: searchValue,
                        date: dateFilter,
                        status: statusFilter
                    },
                    dataType: 'json',
                    success: function (response) {
                        $('#loading').addClass('hidden');

                        if (response.success) {
                            if (response.returns.length === 0) {
                                $('#noReturns').removeClass('hidden');
                                return;
                            }

                            // Préparer les données pour DataTable
                            const tableData = response.returns.map(return_item => {
                                // Déterminer la classe et le texte du badge de statut
                                let statusBadge, statusText;
                                switch (return_item.status) {
                                    case 'pending':
                                        statusBadge = 'badge-yellow';
                                        statusText = 'En attente';
                                        break;
                                    case 'approved':
                                        statusBadge = 'badge-blue';
                                        statusText = 'Approuvé';
                                        break;
                                    case 'completed':
                                        statusBadge = 'badge-green';
                                        statusText = 'Complété';
                                        break;
                                    case 'rejected':
                                        statusBadge = 'badge-red';
                                        statusText = 'Rejeté';
                                        break;
                                    case 'canceled':
                                        statusBadge = 'badge-red';
                                        statusText = 'Annulé';
                                        break;
                                    default:
                                        statusBadge = 'badge-yellow';
                                        statusText = 'En attente';
                                }

                                // Formater les actions disponibles en fonction du statut
                                let actionsHtml = `
                                    <a href="return_details.php?id=${return_item.id}" class="text-blue-600 hover:text-blue-800" title="Voir les détails">
                                        <span class="material-icons-round text-base">visibility</span>
                                    </a>
                                `;

                                // Ajouter des boutons d'action supplémentaires selon le statut
                                if (return_item.status === 'pending') {
                                    actionsHtml += `
                                        <button class="approve-btn ml-2 text-green-600 hover:text-green-800" data-id="${return_item.id}" title="Approuver">
                                            <span class="material-icons-round text-base">check_circle</span>
                                        </button>
                                        <button class="reject-btn ml-2 text-red-600 hover:text-red-800" data-id="${return_item.id}" title="Rejeter">
                                            <span class="material-icons-round text-base">cancel</span>
                                        </button>
                                    `;
                                } else if (return_item.status === 'approved') {
                                    actionsHtml += `
                                        <button class="complete-btn ml-2 text-green-600 hover:text-green-800" data-id="${return_item.id}" title="Marquer comme complété">
                                            <span class="material-icons-round text-base">done_all</span>
                                        </button>
                                    `;
                                }

                                if (['pending', 'approved'].includes(return_item.status)) {
                                    actionsHtml += `
                                        <button class="cancel-btn ml-2 text-red-600 hover:text-red-800" data-id="${return_item.id}" title="Annuler">
                                            <span class="material-icons-round text-base">delete</span>
                                        </button>
                                    `;
                                }

                                return [
                                    return_item.id,
                                    formatDate(return_item.created_at),
                                    return_item.product_name,
                                    return_item.quantity + ' ' + (return_item.unit || 'unité(s)'),
                                    return_item.origin,
                                    translateReturnReason(return_item.return_reason),
                                    `<span class="badge ${statusBadge}">${statusText}</span>`,
                                    actionsHtml
                                ];
                            });

                            // Si DataTable est déjà initialisée, la détruire
                            if (dataTable) {
                                dataTable.destroy();
                            }

                            // Initialiser DataTable
                            dataTable = $('#returnsTable').DataTable({
                                data: tableData,
                                responsive: true,
                                columns: [
                                    { title: 'ID' },
                                    { title: 'Date' },
                                    { title: 'Produit' },
                                    { title: 'Quantité' },
                                    { title: 'Origine' },
                                    { title: 'Motif' },
                                    { title: 'Statut' },
                                    { title: 'Actions', orderable: false }
                                ],
                                order: [[0, 'desc']], // Trier par ID décroissant
                                language: {
                                    url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                                },
                                pageLength: 10,
                                lengthMenu: [
                                    [10, 25, 50, -1],
                                    [10, 25, 50, 'Tous']
                                ],
                                drawCallback: function () {
                                    // Attacher les gestionnaires d'événements aux boutons d'action
                                    attachActionHandlers();
                                }
                            });

                            $('#returnsTableContainer').removeClass('hidden');
                        } else {
                            $('#noReturns').removeClass('hidden');
                            showNotification('Erreur lors du chargement des retours: ' + response.message, 'error');
                        }
                    },
                    error: function (xhr, status, error) {
                        $('#loading').addClass('hidden');
                        $('#noReturns').removeClass('hidden');
                        showNotification('Une erreur est survenue lors du chargement des retours', 'error');
                        console.error('Erreur AJAX:', error);
                    }
                });
            }

            // Fonction pour attacher les gestionnaires d'événements aux boutons d'action
            function attachActionHandlers() {
                // Gestionnaire pour le bouton Approuver
                $('.approve-btn').off('click').on('click', function () {
                    const returnId = $(this).data('id');
                    approveReturn(returnId);
                });

                // Gestionnaire pour le bouton Rejeter
                $('.reject-btn').off('click').on('click', function () {
                    const returnId = $(this).data('id');
                    rejectReturn(returnId);
                });

                // Gestionnaire pour le bouton Compléter
                $('.complete-btn').off('click').on('click', function () {
                    const returnId = $(this).data('id');
                    completeReturn(returnId);
                });

                // Gestionnaire pour le bouton Annuler
                $('.cancel-btn').off('click').on('click', function () {
                    const returnId = $(this).data('id');
                    cancelReturn(returnId);
                });
            }

            // Fonction pour traduire les motifs de retour
            function translateReturnReason(reason) {
                const translations = {
                    'unused': 'Produit non utilisé',
                    'excess': 'Excédent de matériel',
                    'wrong_product': 'Produit erroné',
                    'defective': 'Produit défectueux',
                    'project_completed': 'Projet terminé',
                    'other': 'Autre'
                };

                return translations[reason] || reason;
            }

            // Fonction pour approuver un retour
            function approveReturn(returnId) {
                Swal.fire({
                    title: 'Approuver le retour',
                    text: 'Êtes-vous sûr de vouloir approuver ce retour?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, approuver',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'api/process_return.php',
                            type: 'POST',
                            contentType: 'application/json', // Spécifier le type de contenu
                            data: JSON.stringify({           // Convertir les données en JSON
                                return_id: returnId,
                                action: 'approve'
                            }),
                            dataType: 'json',
                            success: function (response) {
                                if (response.success) {
                                    showNotification('Retour approuvé avec succès', 'success');
                                    loadReturns(); // Recharger les retours
                                } else {
                                    showNotification('Erreur: ' + response.message, 'error');
                                }
                            },
                            error: function (xhr, status, error) {
                                showNotification('Une erreur est survenue', 'error');
                                console.error('Erreur AJAX:', error);
                            }
                        });
                    }
                });
            }

            // Fonction pour rejeter un retour
            function rejectReturn(returnId) {
                Swal.fire({
                    title: 'Rejeter le retour',
                    text: 'Êtes-vous sûr de vouloir rejeter ce retour?',
                    input: 'text',
                    inputPlaceholder: 'Entrez un motif de rejet (optionnel)',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, rejeter',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'api/process_return.php',
                            type: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({
                                return_id: returnId,
                                action: 'reject',
                                reject_reason: result.value || ''
                            }),
                            dataType: 'json',
                            success: function (response) {
                                if (response.success) {
                                    showNotification('Retour rejeté avec succès', 'success');
                                    loadReturns(); // Recharger les retours
                                } else {
                                    showNotification('Erreur: ' + response.message, 'error');
                                }
                            },
                            error: function (xhr, status, error) {
                                showNotification('Une erreur est survenue', 'error');
                                console.error('Erreur AJAX:', error);
                            }
                        });
                    }
                });
            }

            // Fonction pour marquer un retour comme complété
            function completeReturn(returnId) {
                Swal.fire({
                    title: 'Compléter le retour',
                    text: 'Êtes-vous sûr de vouloir marquer ce retour comme complété? Cette action finalisera le retour en stock.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, compléter',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'api/process_return.php',
                            type: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({
                                return_id: returnId,
                                action: 'complete'
                            }),
                            dataType: 'json',
                            success: function (response) {
                                if (response.success) {
                                    showNotification('Retour complété avec succès', 'success');
                                    loadReturns(); // Recharger les retours
                                } else {
                                    showNotification('Erreur: ' + response.message, 'error');
                                }
                            },
                            error: function (xhr, status, error) {
                                showNotification('Une erreur est survenue', 'error');
                                console.error('Erreur AJAX:', error);
                            }
                        });
                    }
                });
            }


            // Fonction pour annuler un retour
            function cancelReturn(returnId) {
                Swal.fire({
                    title: 'Annuler le retour',
                    text: 'Êtes-vous sûr de vouloir annuler ce retour?',
                    input: 'text',
                    inputPlaceholder: 'Entrez un motif d\'annulation (optionnel)',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, annuler',
                    cancelButtonText: 'Ne pas annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'api/cancel_return.php',
                            type: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({
                                return_id: returnId,
                                cancel_reason: result.value || ''
                            }),
                            dataType: 'json',
                            success: function (response) {
                                if (response.success) {
                                    showNotification('Retour annulé avec succès', 'success');
                                    loadReturns(); // Recharger les retours
                                } else {
                                    showNotification('Erreur: ' + response.message, 'error');
                                }
                            },
                            error: function (xhr, status, error) {
                                showNotification('Une erreur est survenue', 'error');
                                console.error('Erreur AJAX:', error);
                            }
                        });
                    }
                });
            }

            // Fonction pour formater une date
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            // Gestionnaires d'événements pour les filtres
            $('#searchInput').on('keyup', function () {
                loadReturns();
            });

            $('#dateFilter, #statusFilter').on('change', function () {
                loadReturns();
            });

            $('#resetFilters').on('click', function () {
                $('#searchInput').val('');
                $('#dateFilter').val('');
                $('#statusFilter').val('');
                loadReturns();
            });

            // Charger les retours au chargement de la page
            loadReturns();
        });
    </script>
</body>

</html>