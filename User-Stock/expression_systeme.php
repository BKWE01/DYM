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

// Inclure la connexion à la base de données
include_once '../database/connection.php';

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Expressions Système</title>
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

        .add-btn {
            background-color: #dbeafe;
            border: none;
            color: #3b82f6;
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

        .add-btn:hover {
            background-color: #bfdbfe;
            color: #2563eb;
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

        .export-btn {
            padding: 6px 12px;
            margin-right: 8px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .export-excel {
            background-color: #d1fae5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .export-excel:hover {
            background-color: #a7f3d0;
        }

        .export-pdf {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .export-pdf:hover {
            background-color: #fecaca;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-stocked {
            background-color: #d1fae5;
            color: #047857;
        }

        .status-purchasing {
            background-color: #dbeafe;
            color: #1e40af;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../components/navbar_stock.php'; ?>

        <main class="flex-1 p-6">
            <!-- Top Bar -->
            <div class="top-bar p-4 mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
                <a href="dashboard.php" class="text-decoration-none">
                    <button class="dashboard-btn">
                        <span class="material-icons" style="font-size: 18px;">arrow_back</span>
                        Retour au Dashboard
                    </button>
                </a>

                <a href="express_systeme.php" class="text-decoration-none">
                    <button class="add-btn">
                        <span class="material-icons" style="font-size: 18px;">add</span>
                        Nouvelle expression
                    </button>
                </a>

                <div class="date-time">
                    <span class="material-icons">calendar_today</span>
                    <span id="date-time-display"></span>
                </div>
            </div>

            <!-- Header Section -->
            <div class="mb-6 flex justify-between items-center">
                <div class="flex items-center">
                    <h2 class="text-2xl font-bold text-gray-800">Expressions Système</h2>
                    <span class="badge-new pulse ml-2">Live</span>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="views/besoins/besoins_systeme.php">
                        <button class="view-all-btn">
                            <span>Toutes les expressions</span>
                            <span class="material-icons" style="font-size: 18px;">chevron_right</span>
                        </button>
                    </a>
                </div>
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

                <!-- Contenu des onglets -->
                <div id="today-content" class="tab-content active">
                    <div id="today-loading" class="loading-spinner">
                        <span class="material-icons animate-spin inline-block mr-2">refresh</span>
                        Chargement...
                    </div>
                    <div id="today-table-container" class="overflow-x-auto hidden">
                        <!-- Boutons d'exportation pour l'onglet Aujourd'hui -->
                        <div class="flex mb-4">
                            <button id="export-excel-today" class="export-btn export-excel">
                                Excel
                            </button>
                            <button id="export-pdf-today" class="export-btn export-pdf">
                                PDF
                            </button>
                        </div>
                        <table id="today-table" class="min-w-full">
                            <thead>
                                <tr>
                                    <th>N° Besoin</th>
                                    <th>Service</th>
                                    <th>Demandeur</th>
                                    <th>Date</th>
                                    <th>Motif</th>
                                    <th>Qté totale</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Les données seront chargées dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                    <div id="today-no-data" class="hidden text-center py-8">
                        <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
                        <p class="text-gray-500 font-medium">Aucune expression système aujourd'hui</p>
                    </div>
                </div>

                <div id="week-content" class="tab-content">
                    <div id="week-loading" class="loading-spinner">
                        <span class="material-icons animate-spin inline-block mr-2">refresh</span>
                        Chargement...
                    </div>
                    <div id="week-table-container" class="overflow-x-auto hidden">
                        <!-- Boutons d'exportation pour l'onglet Cette Semaine -->
                        <div class="flex mb-4">
                            <button id="export-excel-week" class="export-btn export-excel">
                                Excel
                            </button>
                            <button id="export-pdf-week" class="export-btn export-pdf">
                                PDF
                            </button>
                        </div>
                        <table id="week-table" class="min-w-full">
                            <thead>
                                <tr>
                                    <th>N° Besoin</th>
                                    <th>Service</th>
                                    <th>Demandeur</th>
                                    <th>Date</th>
                                    <th>Motif</th>
                                    <th>Qté totale</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Les données seront chargées dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                    <div id="week-no-data" class="hidden text-center py-8">
                        <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
                        <p class="text-gray-500 font-medium">Aucune expression système cette semaine</p>
                    </div>
                </div>

                <div id="month-content" class="tab-content">
                    <div id="month-loading" class="loading-spinner">
                        <span class="material-icons animate-spin inline-block mr-2">refresh</span>
                        Chargement...
                    </div>
                    <div id="month-table-container" class="overflow-x-auto hidden">
                        <!-- Boutons d'exportation pour l'onglet Ce Mois -->
                        <div class="flex mb-4">
                            <button id="export-excel-month" class="export-btn export-excel">
                                Excel
                            </button>
                            <button id="export-pdf-month" class="export-btn export-pdf">
                                PDF
                            </button>
                        </div>
                        <table id="month-table" class="min-w-full">
                            <thead>
                                <tr>
                                    <th>N° Besoin</th>
                                    <th>Service</th>
                                    <th>Demandeur</th>
                                    <th>Date</th>
                                    <th>Motif</th>
                                    <th>Qté totale</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Les données seront chargées dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                    <div id="month-no-data" class="hidden text-center py-8">
                        <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
                        <p class="text-gray-500 font-medium">Aucune expression système ce mois-ci</p>
                    </div>
                </div>
            </div>
        </main>

        <?php include_once '../components/footer.html'; ?>
    </div>

    <!-- jQuery and DataTables scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

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
            setInterval(updateDateTime, 60000); // Mise à jour chaque minute

            // Tab switching
            $('.card-tab').on('click', function () {
                const tabId = $(this).data('tab');

                // Activer l'onglet
                $('.card-tab').removeClass('active');
                $(this).addClass('active');

                // Afficher le contenu correspondant
                $('.tab-content').removeClass('active');
                $(`#${tabId}-content`).addClass('active');
            });

            // Configuration pour DataTables
            const tableConfig = {
                responsive: true,
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                },
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50],
                order: [[3, 'desc']], // Tri par date par défaut
                columnDefs: [
                    {
                        targets: -1, // Colonne Actions
                        orderable: false,
                        searchable: false
                    },
                    {
                        targets: 4, // Colonne Motif
                        orderable: true,
                        searchable: true,
                        width: "15%"
                    }
                ]
            };

            // Fonctions pour initialiser les tableaux
            function initializeTable(period, data) {
                $(`#${period}-loading`).hide();

                if (!data || data.length === 0) {
                    $(`#${period}-no-data`).removeClass('hidden');
                    return;
                }

                $(`#${period}-table-container`).removeClass('hidden');

                try {
                    // Détruire la table si elle existe déjà
                    if ($.fn.DataTable.isDataTable(`#${period}-table`)) {
                        $(`#${period}-table`).DataTable().destroy();
                    }

                    // Initialiser la table avec les données
                    const table = $(`#${period}-table`).DataTable({
                        ...tableConfig,
                        data: data,
                        columns: [
                            { data: 'idBesoin' },
                            { data: 'service_demandeur' },
                            { data: 'nom_prenoms' },
                            { 
                                data: 'date_demande',
                                render: function(data) {
                                    if (!data) return 'Non spécifié';
                                    return new Date(data).toLocaleDateString('fr-FR');
                                }
                            },
                            { 
                                data: 'motif_demande',
                                render: function(data) {
                                    if (!data) return 'Non spécifié';
                                    return data.length > 50 ? data.substring(0, 50) + '...' : data;
                                }
                            },
                            { data: 'total_quantity' },
                            { 
                                data: null,
                                render: function(data, type, row) {
                                    let status = 'En attente';
                                    let statusClass = 'status-pending';
                                    
                                    if (row.stock_status === 'validé') {
                                        status = 'Validé - Stock';
                                        statusClass = 'status-stocked';
                                    } else if (row.achat_status === 'validé') {
                                        status = 'Validé - Achat';
                                        statusClass = 'status-purchasing';
                                    }
                                    
                                    return `<span class="status-badge ${statusClass}">${status}</span>`;
                                }
                            },
                            {
                                data: null,
                                render: function(data, type, row) {
                                    return `<a href="expression_syst_pdf.php?id=${row.idBesoin}" target="_blank" class="view-btn">
                                                <span class="material-icons" style="font-size: 18px;">picture_as_pdf</span>
                                                Voir PDF
                                            </a>`;
                                }
                            }
                        ],
                        buttons: [
                            {
                                extend: 'excelHtml5',
                                text: 'Excel',
                                title: `Expressions Système - ${period}`,
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5, 6]
                                },
                                className: 'hidden buttons-excel'
                            },
                            {
                                extend: 'pdfHtml5',
                                text: 'PDF',
                                title: `Expressions Système - ${period}`,
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5, 6]
                                },
                                className: 'hidden buttons-pdf',
                                orientation: 'landscape'
                            }
                        ]
                    });

                    // Attach export functionality to the custom buttons
                    $(`#export-excel-${period}`).on('click', function() {
                        table.button('.buttons-excel').trigger();
                    });

                    $(`#export-pdf-${period}`).on('click', function() {
                        table.button('.buttons-pdf').trigger();
                    });

                } catch (error) {
                    console.error(`Erreur lors de l'initialisation du tableau ${period}:`, error);
                    $(`#${period}-no-data`)
                        .removeClass('hidden')
                        .find('p')
                        .text('Erreur lors de l\'initialisation du tableau');
                }
            }

            // Fonction pour charger les données depuis le serveur
            function loadData(period) {
                $.ajax({
                    url: `api/besoins/get_besoins.php?period=${period}`,
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        console.log(`Données ${period} chargées:`, data);
                        initializeTable(period, data);
                    },
                    error: function (xhr, status, error) {
                        console.error(`Erreur lors de la récupération des besoins (${period}):`, error);
                        $(`#${period}-loading`).hide();
                        $(`#${period}-no-data`)
                            .removeClass('hidden')
                            .find('p')
                            .text('Erreur lors du chargement des données');
                    }
                });
            }

            // Charger les données avec un léger délai entre chaque requête
            setTimeout(() => loadData('today'), 0);
            setTimeout(() => loadData('week'), 300);
            setTimeout(() => loadData('month'), 600);
        });
    </script>
</body>

</html>