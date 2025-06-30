<?php
/**
 * Page des commandes annulées pour le service stock
 * 
 * Cette page affiche les commandes annulées automatiquement ou manuellement
 * par le service achat ou lorsqu'un projet a été marqué comme terminé
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
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes Annulées - DYM STOCK</title>

    <!-- Styles CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

    <!-- Scripts JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body{
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

        .status-canceled {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        /* Style pour les cartes statistiques */
        .stats-card {
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex flex-col min-h-screen">
        <?php include_once '../../../sidebar.php'; ?>

        <div id="main-content" class="flex-1 flex flex-col overflow-hidden">
            <?php include_once '../../../header.php'; ?>

            <main class="flex-1 p-6 overflow-y-auto">
                <!-- En-tête de la page -->
                <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex flex-wrap justify-between items-center">
                    <div class="flex items-center m-2">
                        <h1 class="text-2xl font-bold text-gray-800">Commandes Annulées</h1>
                    </div>

                    <div class="flex items-center m-2 bg-blue-50 p-2 rounded-lg border border-blue-100">
                        <span class="material-icons text-blue-500 mr-2">info</span>
                        <span class="text-sm text-blue-800">
                            Cette page affiche les commandes qui ont été annulées automatiquement lorsqu'un projet a été marqué comme terminé
                        </span>
                    </div>
                </div>

                <!-- Message d'information -->
                <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <span class="material-icons text-yellow-500">info</span>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Information importante</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>Cette section affiche les commandes qui ont été automatiquement annulées lorsqu'un projet a été marqué comme terminé par le service Bureau d'Études.</p>
                                <p class="mt-2">Lorsqu'un projet est marqué comme terminé, toutes les commandes en attente, partielles ou normales associées à ce projet sont automatiquement annulées pour éviter des dépenses inutiles.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiques des commandes annulées -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Total des annulations</p>
                                <h3 class="text-2xl font-bold mt-1" id="total-canceled-count">0</h3>
                            </div>
                            <div class="rounded-full bg-red-100 p-3">
                                <span class="material-icons text-red-600">cancel</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Commandes annulées automatiquement</p>
                    </div>

                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Projets concernés</p>
                                <h3 class="text-2xl font-bold mt-1" id="projects-canceled-count">0</h3>
                            </div>
                            <div class="rounded-full bg-orange-100 p-3">
                                <span class="material-icons text-orange-600">folder_off</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Projets avec commandes annulées</p>
                    </div>

                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Dernière annulation</p>
                                <h3 class="text-lg font-bold mt-1" id="last-canceled-date">-</h3>
                            </div>
                            <div class="rounded-full bg-purple-100 p-3">
                                <span class="material-icons text-purple-600">event_busy</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Date de la dernière annulation</p>
                    </div>
                </div>

                <!-- Tableau des commandes annulées -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Historique des commandes annulées</h2>
                        <button id="refresh-list" class="flex items-center bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-sm transition">
                            <span class="material-icons text-sm mr-1">refresh</span>
                            Actualiser
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="canceledOrdersTable" class="display responsive nowrap w-full">
                            <thead>
                                <tr>
                                    <th>Projet</th>
                                    <th>Client</th>
                                    <th>Produit</th>
                                    <th>Statut original</th>
                                    <th>Quantité</th>
                                    <th>Fournisseur</th>
                                    <th>Date d'annulation</th>
                                    <th>Raison</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Les données seront chargées dynamiquement -->
                                <tr>
                                    <td colspan="9" class="text-center py-4">Chargement des données...</td>
                                </tr>
                            </tbody>
                        </table>
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

    <script>
        $(document).ready(function() {
            // Initialisation du DataTable pour les commandes annulées
            initCanceledOrdersTable();

            // Bouton d'actualisation
            $('#refresh-list').click(function() {
                $('#canceledOrdersTable').DataTable().ajax.reload();
            });
        });

        /**
        * Initialisation du tableau des commandes annulées
        */
        function initCanceledOrdersTable() {
            try {
                if ($.fn.DataTable.isDataTable('#canceledOrdersTable')) {
                    $('#canceledOrdersTable').DataTable().destroy();
                }

                $('#canceledOrdersTable').DataTable({
                    responsive: true,
                    language: { url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json" },
                    ajax: {
                        url: 'api/api_getCanceledOrders.php',
                        dataSrc: function (json) {
                            // Mise à jour des statistiques
                            updateCanceledOrdersStats(json.stats);
                            return json.data || [];
                        }
                    },
                    columns: [
                        { data: 'code_projet' },
                        { data: 'nom_client' },
                        { data: 'designation' },
                        { 
                            data: 'original_status',
                            render: function(data) {
                                let badge = '';
                                switch(data) {
                                    case 'validé':
                                        badge = '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Validé</span>';
                                        break;
                                    case 'en_cours':
                                        badge = '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">En cours</span>';
                                        break;
                                    case 'pas validé':
                                        badge = '<span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">Pas validé</span>';
                                        break;
                                    default:
                                        badge = '<span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">' + data + '</span>';
                                }
                                return badge;
                            }
                        },
                        { 
                            data: 'quantity',
                            render: function(data, type, row) {
                                if (data === null || data === undefined) {
                                    return 'N/A';
                                }
                                return parseFloat(data).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { data: 'fournisseur' },
                        { 
                            data: 'canceled_at',
                            render: function(data) {
                                const date = new Date(data);
                                return date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
                            }
                        },
                        { data: 'cancel_reason' },
                        {
                            data: 'id',
                            render: function (data, type, row) {
                                return `
                            <button onclick="viewCanceledOrderDetails(${data})" class="text-blue-600 hover:text-blue-800">
                                <span class="material-icons text-sm">visibility</span>
                            </button>`;
                            },
                            orderable: false
                        }
                    ],
                    columnDefs: [
                        { type: 'date-fr', targets: 6 }
                    ],
                    order: [[6, 'desc']],
                    pageLength: 15
                });
            } catch (error) {
                console.error("Erreur lors de l'initialisation du tableau des commandes annulées:", error);
            }
        }

        /**
        * Mise à jour des statistiques des commandes annulées
        */
        function updateCanceledOrdersStats(stats) {
            if (!stats) return;

            // Mettre à jour le compteur total
            if (document.getElementById('total-canceled-count')) {
                document.getElementById('total-canceled-count').textContent = stats.total_canceled || 0;
            }

            // Mettre à jour le compteur de projets
            if (document.getElementById('projects-canceled-count')) {
                document.getElementById('projects-canceled-count').textContent = stats.projects_count || 0;
            }

            // Mettre à jour la date de dernière annulation
            if (document.getElementById('last-canceled-date') && stats.last_canceled_date) {
                const date = new Date(stats.last_canceled_date);
                document.getElementById('last-canceled-date').textContent = date.toLocaleDateString('fr-FR');
            }
        }

        /**
         * Fonction pour voir les détails d'une commande annulée
         * @param {number} orderId - ID de l'entrée dans la table canceled_orders_log
         */
        function viewCanceledOrderDetails(orderId) {
            // Si orderId n'est pas défini ou est vide, afficher une erreur
            if (!orderId) {
                Swal.fire({
                    title: 'Erreur',
                    text: 'ID de commande non trouvé. Veuillez réessayer.',
                    icon: 'error'
                });
                return;
            }

            // Afficher un loader
            Swal.fire({
                title: 'Chargement...',
                text: 'Récupération des détails de la commande annulée',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Charger les détails avec l'ID spécifié
            fetch(`api/api_getCanceledOrderDetails.php?id=${orderId}`)
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
    </script>
</body>
</html>