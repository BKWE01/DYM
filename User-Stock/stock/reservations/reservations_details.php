<?php
// Vérifier si la session n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de sécurité - rediriger si pas connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails des Réservations - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

    <!-- Toastify and SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #334155;
        }

        .wrapper {
            display: flex;
            /*flex-direction: column;
            min-height: 100vh;*/
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

        .back-btn {
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

        .back-btn:hover {
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

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
            border-left: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px -1px rgba(0, 0, 0, 0.1);
        }

        .stat-card.total { border-left-color: #3b82f6; }
        .stat-card.available { border-left-color: #10b981; }
        .stat-card.unavailable { border-left-color: #ef4444; }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
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
            padding: 12px 15px;
            border-bottom: 2px solid #e2e8f0;
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.875rem;
        }

        table.dataTable tbody td {
            padding: 10px 15px;
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

        /* Status badges */
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

        /* Action buttons */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            margin-right: 4px;
        }

        .btn-view {
            background-color: #3b82f6;
            color: white;
        }

        .btn-view:hover {
            background-color: #2563eb;
        }

        .btn-release {
            background-color: #ef4444;
            color: white;
        }

        .btn-release:hover {
            background-color: #dc2626;
        }

        .btn-release:disabled {
            background-color: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }

        .project-link {
            color: #3b82f6;
            font-weight: 600;
            cursor: pointer;
            text-decoration: underline;
        }

        .project-link:hover {
            color: #2563eb;
        }

        /* Export buttons */
        .export-btn {
            padding: 8px 16px;
            margin-right: 8px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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

        .refresh-btn {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .refresh-btn:hover {
            background-color: #e2e8f0;
        }

        /* Modals */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease;
        }

        .modal-content {
            transition: all 0.3s ease;
        }

        /* Loading state */
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../sidebar.php'; ?>

        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once '../header.php'; ?>

            <main class="p-6 flex-1">
                <!-- Top Bar -->
                <div class="top-bar p-4 mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-4">
                        <a href="../index.php">
                            <button class="back-btn">
                                <span class="material-icons" style="font-size: 18px;">arrow_back</span>
                                Retour au Dashboard
                            </button>
                        </a>
                        <h2 class="text-xl font-bold">Détails des Réservations</h2>
                    </div>

                    <div class="date-time">
                        <span class="material-icons">calendar_today</span>
                        <span id="date-time-display"></span>
                    </div>
                </div>

                <!-- Statistiques rapides -->
                <div class="stats-container" id="statsContainer">
                    <div class="stat-card total">
                        <div class="stat-icon text-blue-500">
                            <span class="material-icons">bookmark</span>
                        </div>
                        <div class="stat-number text-blue-700" id="totalReservations">--</div>
                        <div class="stat-label">Total Réservations</div>
                    </div>
                    <div class="stat-card available">
                        <div class="stat-icon text-green-500">
                            <span class="material-icons">check_circle</span>
                        </div>
                        <div class="stat-number text-green-700" id="availableReservations">--</div>
                        <div class="stat-label">Disponibles</div>
                    </div>
                    <div class="stat-card unavailable">
                        <div class="stat-icon text-red-500">
                            <span class="material-icons">error</span>
                        </div>
                        <div class="stat-number text-red-700" id="unavailableReservations">--</div>
                        <div class="stat-label">Non disponibles</div>
                    </div>
                </div>

                <!-- Tableau des réservations -->
                <div class="card-container p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Détails des réservations par projet</h2>
                        
                        <!-- Boutons d'export et actions -->
                        <div class="flex gap-2">
                            <button id="export-excel" class="export-btn export-excel">
                                <span class="material-icons" style="font-size: 16px;">table_view</span>
                                Excel
                            </button>
                            <button id="export-pdf" class="export-btn export-pdf">
                                <span class="material-icons" style="font-size: 16px;">picture_as_pdf</span>
                                PDF
                            </button>
                            <button id="refresh-data" class="export-btn refresh-btn">
                                <span class="material-icons" style="font-size: 16px;">refresh</span>
                                Actualiser
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="reservations-table" class="min-w-full">
                            <thead>
                                <tr>
                                    <th>Projet</th>
                                    <th>Code Produit</th>
                                    <th>Désignation</th>
                                    <th>Unité</th>
                                    <th>Catégorie</th>
                                    <th>Qté Réservée</th>
                                    <th>Stock Dispo</th>
                                    <th>Statut</th>
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
        </div>
    </div>

    <!-- Modal de confirmation pour libérer une réservation -->
    <div id="releaseModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
        <div class="modal-overlay fixed inset-0" id="modalOverlay"></div>
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md mx-4 md:mx-0 transform opacity-0 scale-95"
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
        <div class="modal-overlay fixed inset-0" id="projectModalOverlay"></div>
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4 md:mx-0 transform opacity-0 scale-95"
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

    <!-- Script principal des réservations -->
    <script src="assets/js/reservations.js"></script>
</body>

</html>