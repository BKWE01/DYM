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
    <title>Projets Terminés - DYM BE</title>
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

        .badge-completed {
            background-color: #d1fae5;
            color: #047857;
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

        /* Buttons */
        .dt-buttons {
            margin-bottom: 1rem;
        }

        .dt-button {
            background-color: #f3f4f6 !important;
            border: 1px solid #d1d5db !important;
            border-radius: 0.375rem !important;
            padding: 0.5rem 1rem !important;
            font-size: 0.875rem !important;
            color: #4b5563 !important;
            transition: all 0.2s !important;
            box-shadow: none !important;
        }

        .dt-button:hover {
            background-color: #e5e7eb !important;
            color: #1f2937 !important;
        }

        .project-details-wrapper {
            display: none;
            animation: fadeIn 0.3s ease-out forwards;
        }

        .project-details {
            background-color: #f9fafb;
            border-left: 4px solid #4f46e5;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .project-detail-row {
            display: flex;
            margin-bottom: 0.5rem;
        }

        .project-detail-label {
            font-weight: 500;
            color: #4b5563;
            width: 180px;
        }

        .project-detail-value {
            color: #1f2937;
            flex: 1;
        }

        .button-back {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background-color: #f3f4f6;
            color: #4b5563;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            margin-bottom: 1rem;
        }

        .button-back:hover {
            background-color: #e5e7eb;
            color: #1f2937;
        }

        /* Products table */
        .products-table th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #4b5563;
            text-align: left;
            padding: 0.75rem 1rem;
        }

        .products-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .products-table tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../components/navbar.php'; ?>

        <div class="container mx-auto px-4 py-6">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-wrap justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        Projets Terminés
                    </h1>
                    <div class="text-sm text-gray-500 flex items-center">
                        <i class="far fa-calendar-alt mr-2"></i>
                        <span id="current-date"></span>
                    </div>
                </div>

                <div class="flex flex-wrap justify-between items-center mb-4">
                    <div class="mb-2">
                        <a href="project_return.php"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-sm transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Retour à la gestion des retours
                        </a>
                    </div>
                    <div class="text-sm text-gray-500 mb-2">
                        <span class="block bg-blue-100 px-3 py-1 rounded-full">
                            <i class="fas fa-info-circle mr-1"></i>
                            Consultez l'historique des projets terminés
                        </span>
                    </div>
                </div>

                <!-- Vue principale: Liste des projets terminés -->
                <div id="projects-list-view">
                    <div class="mb-4">
                        <p class="text-gray-600">
                            Cette page affiche tous les projets qui ont été marqués comme terminés dans le système.
                            Cliquez sur un projet pour voir plus de détails.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="completed-projects-table"
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
                                        Chef de Projet</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date de Création</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date de Fin</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody id="projects-table-body" class="bg-white divide-y divide-gray-200">
                                <!-- Les projets seront chargés ici par JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <div id="projects-loading" class="text-center py-8">
                        <div
                            class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-indigo-500 border-r-transparent">
                        </div>
                        <p class="text-gray-500 mt-2">Chargement des projets terminés...</p>
                    </div>

                    <div id="projects-empty" class="empty-state hidden">
                        <i class="fas fa-clipboard-check empty-state-icon"></i>
                        <h3 class="empty-state-title">Aucun projet terminé</h3>
                        <p class="empty-state-description">
                            Aucun projet n'a encore été marqué comme terminé dans le système.
                        </p>
                    </div>
                </div>

                <!-- Vue de détail: Détails d'un projet spécifique -->
                <div id="project-details-view" class="project-details-wrapper">
                    <button id="back-to-list" class="button-back">
                        <i class="fas fa-arrow-left mr-2"></i> Retour à la liste
                    </button>

                    <div class="project-details">
                        <h2 class="text-xl font-semibold mb-4" id="detail-project-title">Détails du projet</h2>

                        <div class="project-detail-row">
                            <div class="project-detail-label">ID Projet:</div>
                            <div class="project-detail-value" id="detail-project-id"></div>
                        </div>

                        <div class="project-detail-row">
                            <div class="project-detail-label">Code Projet:</div>
                            <div class="project-detail-value" id="detail-project-code"></div>
                        </div>

                        <div class="project-detail-row">
                            <div class="project-detail-label">Nom Client:</div>
                            <div class="project-detail-value" id="detail-client-name"></div>
                        </div>

                        <div class="project-detail-row">
                            <div class="project-detail-label">Description:</div>
                            <div class="project-detail-value" id="detail-project-description"></div>
                        </div>

                        <div class="project-detail-row">
                            <div class="project-detail-label">Situation Géographique:</div>
                            <div class="project-detail-value" id="detail-project-location"></div>
                        </div>

                        <div class="project-detail-row">
                            <div class="project-detail-label">Chef de Projet:</div>
                            <div class="project-detail-value" id="detail-project-manager"></div>
                        </div>

                        <div class="project-detail-row">
                            <div class="project-detail-label">Date de Création:</div>
                            <div class="project-detail-value" id="detail-creation-date"></div>
                        </div>

                        <div class="project-detail-row">
                            <div class="project-detail-label">Date de Fin:</div>
                            <div class="project-detail-value" id="detail-completion-date"></div>
                        </div>

                        <div class="project-detail-row">
                            <div class="project-detail-label">Terminé par:</div>
                            <div class="project-detail-value" id="detail-completed-by"></div>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">Produits du projet</h3>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table id="products-table" class="w-full products-table">
                            <thead>
                                <tr>
                                    <th>Désignation</th>
                                    <th>Unité</th>
                                    <th>Type</th>
                                    <th>Quantité Initiale</th>
                                    <th>Quantité Utilisée</th>
                                    <th>Quantité Retournée</th>
                                </tr>
                            </thead>
                            <tbody id="products-table-body">
                                <!-- Les produits seront chargés ici -->
                            </tbody>
                        </table>
                        <div id="products-loading" class="text-center py-6 hidden">
                            <div
                                class="inline-block h-6 w-6 animate-spin rounded-full border-4 border-solid border-indigo-500 border-r-transparent">
                            </div>
                            <p class="text-gray-500 mt-2">Chargement des produits...</p>
                        </div>
                        <div id="products-empty" class="text-center py-6 hidden">
                            <i class="fas fa-box-open text-gray-300 text-2xl mb-2"></i>
                            <p class="text-gray-500">Aucun produit trouvé pour ce projet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let dataTable = null;

        // Fonction pour initialiser la page
        $(document).ready(function() {
            // Afficher la date courante
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            const today = new Date();
            document.getElementById('current-date').textContent = today.toLocaleDateString('fr-FR', options);

            // Charger les projets terminés
            loadCompletedProjects();

            // Gestionnaire pour le bouton de retour à la liste
            $('#back-to-list').on('click', function() {
                showProjectsListView();
            });
        });

        // Fonction pour charger la liste des projets terminés
        function loadCompletedProjects() {
            // Afficher le loading et cacher le message vide
            $('#projects-loading').show();
            $('#projects-empty').hide(); // Utiliser hide() au lieu de addClass('hidden')

            // Vider le tableau au cas où
            $('#projects-table-body').empty();

            // Si une instance DataTable existe déjà, la détruire
            if (dataTable !== null) {
                dataTable.destroy();
                dataTable = null;
            }

            $.ajax({
                url: 'api_completed/api_getCompletedProjects.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Réponse API:', response); // Debug

                    // Cacher le loading
                    $('#projects-loading').hide();

                    if (response.success && response.projects && response.projects.length > 0) {
                        console.log('Nombre de projets trouvés:', response.projects.length); // Debug
                        displayCompletedProjects(response.projects);
                        // S'assurer que le message vide est caché
                        $('#projects-empty').hide();
                    } else {
                        console.log('Aucun projet trouvé'); // Debug
                        // Afficher le message vide
                        $('#projects-empty').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erreur AJAX:', error); // Debug
                    $('#projects-loading').hide();
                    $('#projects-empty').show();
                    $('#projects-empty .empty-state-title').text('Erreur de chargement');
                    $('#projects-empty .empty-state-description').text('Une erreur est survenue lors du chargement des projets terminés.');

                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Impossible de charger les projets terminés. Veuillez réessayer plus tard.',
                        confirmButtonColor: '#4F46E5'
                    });
                }
            });
        }

        // Fonction pour afficher la liste des projets terminés
        function displayCompletedProjects(projects) {
            console.log('Affichage de', projects.length, 'projets'); // Debug

            const tableBody = $('#projects-table-body');
            tableBody.empty();

            projects.forEach(project => {
                const creationDate = formatDate(project.created_at);
                const completionDate = formatDate(project.completed_at);

                const row = $(`
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${project.idExpression}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${project.code_projet || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">${project.nom_client}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${project.chefprojet}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500">${creationDate}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500">${completionDate}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button class="text-indigo-600 hover:text-indigo-900 mr-3 view-details-btn" data-id="${project.idExpression}">
                        <i class="fas fa-eye"></i> Voir
                    </button>
                    <button class="text-blue-600 hover:text-blue-900 export-pdf-btn" data-id="${project.idExpression}">
                        <i class="fas fa-file-pdf"></i> Exporter
                    </button>
                </td>
            </tr>
        `);

                tableBody.append(row);
            });

            // S'assurer que le tableau est visible
            $('#completed-projects-table').show();

            // Initialiser DataTable après avoir ajouté les données
            setTimeout(function() {
                try {
                    dataTable = $('#completed-projects-table').DataTable({
                        responsive: true,
                        language: {
                            url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                        },
                        dom: 'Bfrtip',
                        buttons: [{
                                extend: 'excel',
                                text: '<i class="fas fa-file-excel mr-1"></i> Excel',
                                className: 'mr-2',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5]
                                }
                            },
                            {
                                extend: 'pdf',
                                text: '<i class="fas fa-file-pdf mr-1"></i> PDF',
                                className: 'mr-2',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5]
                                }
                            },
                            {
                                extend: 'print',
                                text: '<i class="fas fa-print mr-1"></i> Imprimer',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5]
                                }
                            }
                        ]
                    });

                    console.log('DataTable initialisé avec succès'); // Debug
                } catch (error) {
                    console.error('Erreur lors de l\'initialisation de DataTable:', error); // Debug
                }
            }, 100);

            // Ajouter des écouteurs d'événements pour les boutons
            $('.view-details-btn').on('click', function() {
                const projectId = $(this).data('id');
                loadProjectDetails(projectId);
            });

            $('.export-pdf-btn').on('click', function() {
                const projectId = $(this).data('id');
                exportProjectPDF(projectId);
            });

            // S'assurer que le message vide est caché
            $('#projects-empty').hide();
        }

        // Fonction pour charger les détails d'un projet
        function loadProjectDetails(projectId) {
            $.ajax({
                url: 'api_completed/api_getProjectDetails.php',
                type: 'GET',
                data: {
                    project_id: projectId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayProjectDetails(response.project);
                        loadProjectProducts(projectId);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: response.message || 'Impossible de charger les détails du projet.',
                            confirmButtonColor: '#4F46E5'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la connexion au serveur.',
                        confirmButtonColor: '#4F46E5'
                    });
                }
            });
        }

        // Fonction pour afficher les détails d'un projet
        function displayProjectDetails(project) {
            // Remplir les détails du projet
            $('#detail-project-title').text(`Projet: ${project.nom_client} (Terminé)`);
            $('#detail-project-id').text(project.idExpression);
            $('#detail-project-code').text(project.code_projet || 'Non spécifié');
            $('#detail-client-name').text(project.nom_client);
            $('#detail-project-description').text(project.description_projet || 'Aucune description disponible');
            $('#detail-project-location').text(project.sitgeo || 'Non spécifiée');
            $('#detail-project-manager').text(project.chefprojet || 'Non spécifié');
            $('#detail-creation-date').text(formatDate(project.created_at));
            $('#detail-completion-date').text(formatDate(project.completed_at));

            // Si le nom de l'utilisateur qui a terminé le projet est disponible, l'afficher
            if (project.completed_by_name) {
                $('#detail-completed-by').text(project.completed_by_name);
            } else {
                $('#detail-completed-by').text(`Utilisateur ID: ${project.completed_by || 'Non spécifié'}`);
            }

            // Afficher la vue détaillée
            showProjectDetailsView();
        }

        // Fonction pour charger les produits d'un projet
        function loadProjectProducts(projectId) {
            $('#products-loading').removeClass('hidden');
            $('#products-empty').addClass('hidden');
            $('#products-table-body').empty();

            $.ajax({
                url: 'api_completed/api_getProjectProducts.php',
                type: 'GET',
                data: {
                    project_id: projectId
                },
                dataType: 'json',
                success: function(response) {
                    $('#products-loading').addClass('hidden');

                    if (response.success && response.products && response.products.length > 0) {
                        displayProjectProducts(response.products);
                    } else {
                        $('#products-empty').removeClass('hidden');
                    }
                },
                error: function() {
                    $('#products-loading').addClass('hidden');
                    $('#products-empty').removeClass('hidden');
                }
            });
        }

        // Fonction pour afficher les produits d'un projet
        function displayProjectProducts(products) {
            const tableBody = $('#products-table-body');
            tableBody.empty();

            products.forEach(product => {
                const initialQuantity = parseFloat(product.quantity || 0).toFixed(2);
                const usedQuantity = parseFloat(product.quantity_used || 0).toFixed(2);
                const returnedQuantity = parseFloat(product.quantity_returned || 0).toFixed(2);

                const row = $(`
            <tr>
                <td>${product.designation}</td>
                <td>${product.unit || 'unité'}</td>
                <td>${product.type || '-'}</td>
                <td>${initialQuantity}</td>
                <td>${usedQuantity}</td>
                <td>${returnedQuantity}</td>
            </tr>
        `);

                tableBody.append(row);
            });
        }

        // Fonction pour exporter un projet en PDF
        function exportProjectPDF(projectId) {
            window.open(`api_completed/generate_project_report.php?project_id=${projectId}`, '_blank');
        }

        // Fonction pour afficher la vue de la liste des projets
        function showProjectsListView() {
            $('#project-details-view').hide();
            $('#projects-list-view').show();
        }

        // Fonction pour afficher la vue des détails d'un projet
        function showProjectDetailsView() {
            $('#projects-list-view').hide();
            $('#project-details-view').show();
        }

        // Fonction pour formater une date
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