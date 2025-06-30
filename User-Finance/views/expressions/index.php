<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Rediriger vers index.php
    header("Location: ./../../index.php");
    exit();
}

// Reste du code pour la page protégée
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Service Achat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

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

        .dashboard-btn {
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
        }

        .dashboard-btn:hover {
            background-color: #d1fae5;
            color: #059669;
            transform: translateY(-1px);
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

        .view-all-btn {
            background-color: white;
            border: 1px solid #e2e8f0;
            color: #4b5563;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .view-all-btn:hover {
            background-color: #f1f5f9;
            color: #1e293b;
            transform: translateY(-1px);
        }

        .card-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .badge-new {
            display: inline-block;
            background-color: #10b981;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 9999px;
            margin-left: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }

            100% {
                opacity: 1;
            }
        }

        .pulse {
            animation: pulse 2s infinite;
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
            cursor: pointer;
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

        .badge-blue {
            background-color: #3b82f6;
        }

        .badge-green {
            background-color: #10b981;
        }

        .badge-orange {
            background-color: #f59e0b;
        }

        .view-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #fff;
            background-color: #3b82f6;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }

        .view-btn:hover {
            background-color: #2563eb;
        }

        .view-btn .material-icons {
            font-size: 1rem;
            margin-right: 0.25rem;
        }

        .card-tab {
            padding: 10px 16px;
            background-color: #f8fafc;
            color: #64748b;
            border-bottom: 2px solid transparent;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .card-tab.active {
            background-color: white;
            color: #3b82f6;
            border-bottom: 2px solid #3b82f6;
        }

        .card-tab:hover:not(.active) {
            background-color: #f1f5f9;
            color: #475569;
        }

        .tab-content {
            display: none;
            padding: 16px;
        }

        .tab-content.active {
            display: block;
        }

        .loading-spinner {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }

        .switch-view-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            gap: 0.5rem;
        }

        .switch-view-btn.active {
            background-color: #eff6ff;
            color: #3b82f6;
            border: 1px solid #bfdbfe;
        }

        .switch-view-btn:not(.active) {
            background-color: #f9fafb;
            color: #6b7280;
            border: 1px solid #e5e7eb;
        }

        .switch-view-btn:not(.active):hover {
            background-color: #f3f4f6;
            color: #4b5563;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../../../components/navbar_finance.php'; ?>

        <main class="flex-1 p-6">
            <!-- Top Bar -->
            <div class="top-bar p-4 mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
                <button class="dashboard-btn">
                    <span class="material-icons" style="font-size: 18px;">dashboard</span>
                    Tableau de Bord Expression
                </button>

                <div class="date-time">
                    <span class="material-icons">calendar_today</span>
                    <span id="date-time-display"></span>
                </div>
            </div>

            <!-- Header Section -->
            <div class="mb-6 flex justify-between items-center">
                <div class="flex items-center">
                    <h2 class="text-2xl font-bold text-gray-800">Vue d'ensemble</h2>
                    <span class="badge-new pulse ml-2">Live</span>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="ensemble_expressions.php">
                        <button class="view-all-btn">
                            <span>Toutes les expressions</span>
                            <span class="material-icons" style="font-size: 18px;">chevron_right</span>
                        </button>
                    </a>
                </div>
            </div>

            <!-- Validated Expressions Button (always active) -->
            <div class="flex mb-6">
                <button id="validated-view-btn" class="switch-view-btn active">
                    <span class="material-icons" style="font-size: 18px;">assignment_turned_in</span>
                    Expressions validées
                </button>
            </div>

            <!-- Main Content -->
            <div class="card-container">
                <div class="flex border-b">
                    <div class="card-tab active" data-tab="today" data-period="today">
                        <span class="material-icons mr-2"
                            style="color: #3b82f6; font-size: 20px; vertical-align: middle;">today</span>
                        Aujourd'hui
                    </div>
                    <div class="card-tab" data-tab="week" data-period="week">
                        <span class="material-icons mr-2"
                            style="color: #8b5cf6; font-size: 20px; vertical-align: middle;">date_range</span>
                        Cette Semaine
                    </div>
                    <div class="card-tab" data-tab="month" data-period="month">
                        <span class="material-icons mr-2"
                            style="color: #ec4899; font-size: 20px; vertical-align: middle;">calendar_month</span>
                        Ce Mois
                    </div>
                </div>

                <!-- Validated Expressions View -->
                <div id="validated-expressions-container">
                    <!-- Today Tab Content -->
                    <div id="today-content-validated" class="tab-content active">
                        <div id="today-loading-validated" class="loading-spinner">
                            <span class="material-icons animate-spin inline-block mr-2">refresh</span>
                            Chargement...
                        </div>
                        <div id="today-table-container-validated" class="overflow-x-auto hidden">
                            <table id="today-table-validated" class="min-w-full">
                                <thead>
                                    <tr>
                                        <th>N° Expression</th>
                                        <th>Client</th>
                                        <th>Projet</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Les données seront chargées dynamiquement -->
                                </tbody>
                            </table>
                        </div>
                        <div id="today-no-data-validated" class="hidden text-center py-8">
                            <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
                            <p class="text-gray-500 font-medium">Aucune expression validée aujourd'hui</p>
                        </div>
                    </div>

                    <!-- Week Tab Content -->
                    <div id="week-content-validated" class="tab-content">
                        <div id="week-loading-validated" class="loading-spinner">
                            <span class="material-icons animate-spin inline-block mr-2">refresh</span>
                            Chargement...
                        </div>
                        <div id="week-table-container-validated" class="overflow-x-auto hidden">
                            <table id="week-table-validated" class="min-w-full">
                                <thead>
                                    <tr>
                                        <th>N° Expression</th>
                                        <th>Client</th>
                                        <th>Projet</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Les données seront chargées dynamiquement -->
                                </tbody>
                            </table>
                        </div>
                        <div id="week-no-data-validated" class="hidden text-center py-8">
                            <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
                            <p class="text-gray-500 font-medium">Aucune expression validée cette semaine</p>
                        </div>
                    </div>

                    <!-- Month Tab Content -->
                    <div id="month-content-validated" class="tab-content">
                        <div id="month-loading-validated" class="loading-spinner">
                            <span class="material-icons animate-spin inline-block mr-2">refresh</span>
                            Chargement...
                        </div>
                        <div id="month-table-container-validated" class="overflow-x-auto hidden">
                            <table id="month-table-validated" class="min-w-full">
                                <thead>
                                    <tr>
                                        <th>N° Expression</th>
                                        <th>Client</th>
                                        <th>Projet</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Les données seront chargées dynamiquement -->
                                </tbody>
                            </table>
                        </div>
                        <div id="month-no-data-validated" class="hidden text-center py-8">
                            <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
                            <p class="text-gray-500 font-medium">Aucune expression validée ce mois-ci</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <?php include_once '../../../components/footer.html'; ?>
    </div>

    <!-- jQuery and DataTables scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>

    <script>
        $(document).ready(function () {
            // Date and time updater
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

            updateDateTime();
            setInterval(updateDateTime, 60000); // Update every minute

            // Tab switching
            $('.card-tab').on('click', function () {
                const tabId = $(this).data('tab');

                // Activer l'onglet
                $('.card-tab').removeClass('active');
                $(this).addClass('active');

                // Afficher le contenu correspondant
                $('.tab-content').removeClass('active');
                $(`#${tabId}-content-validated`).addClass('active');
            });

            // Configuration pour les tableaux
            const tableConfig = {
                columns: [
                    { data: 'idExpression', title: 'N° Expression' },
                    {
                        data: 'nom_client',
                        title: 'Client',
                        render: function (data) {
                            return data || 'Non spécifié';
                        }
                    },
                    {
                        data: 'code_projet',
                        title: 'Projet',
                        render: function (data) {
                            return data || 'Non spécifié';
                        }
                    },
                    {
                        data: 'description_projet',
                        title: 'Description',
                        render: function (data) {
                            // Tronquer la description si elle est trop longue
                            if (data && data.length > 50) {
                                return data.substring(0, 50) + '...';
                            }
                            return data || 'Non spécifié';
                        }
                    },
                    {
                        data: 'created_at',
                        title: 'Date',
                        render: function (data) {
                            const date = new Date(data);
                            return date.toLocaleDateString('fr-FR', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric'
                            });
                        }
                    }
                ]
            };

            // Options communes pour DataTables
            const commonOptions = {
                responsive: true,
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                },
                pageLength: 5,
                lengthMenu: [5, 10, 25],
                order: [[0, 'desc']]
            };

            // Initialize DataTables for validated expressions
            function initializeValidatedTable(period, data) {
                $(`#${period}-loading-validated`).hide();

                if (!data || data.length === 0) {
                    $(`#${period}-no-data-validated`).removeClass('hidden');
                    return;
                }

                $(`#${period}-table-container-validated`).removeClass('hidden');

                try {
                    // Détruire la table si elle existe déjà
                    if ($.fn.DataTable.isDataTable(`#${period}-table-validated`)) {
                        $(`#${period}-table-validated`).DataTable().destroy();
                    }

                    // Initialiser la table avec les données
                    const table = $(`#${period}-table-validated`).DataTable({
                        ...commonOptions,
                        ...tableConfig,
                        data: data
                    });

                    // Cliquer sur une ligne pour voir le PDF
                    $(`#${period}-table-validated tbody`).on('click', 'tr', function () {
                        const rowData = table.row(this).data();
                        if (rowData && rowData.idExpression) {
                            window.open(`../../api/pdf/generate_pdf.php?id=${rowData.idExpression}`, '_blank');
                        }
                    });
                } catch (error) {
                    console.error(`Erreur lors de l'initialisation du tableau validé ${period}:`, error);
                    $(`#${period}-no-data-validated`)
                        .removeClass('hidden')
                        .find('p')
                        .text('Erreur lors de l\'initialisation du tableau');
                }
            }

            // Load data for validated expressions
            function loadValidatedData(period) {
                $.ajax({
                    url: `../../api/expressions/get_expressions_vaide.php?period=${period}`,
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        console.log(`Données ${period} chargées:`, data);
                        initializeValidatedTable(period, data);
                    },
                    error: function (xhr, status, error) {
                        console.error(`Erreur lors de la récupération des expressions validées (${period}):`, error);
                        $(`#${period}-loading-validated`).hide();
                        $(`#${period}-no-data-validated`)
                            .removeClass('hidden')
                            .find('p')
                            .text('Erreur lors du chargement des données');
                    }
                });
            }

            // Charger les données avec un léger délai entre chaque requête
            setTimeout(() => loadValidatedData('today'), 0);
            setTimeout(() => loadValidatedData('week'), 300);
            setTimeout(() => loadValidatedData('month'), 600);
        });
    </script>
</body>

</html>