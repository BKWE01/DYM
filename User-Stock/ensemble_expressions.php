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

// Récupérer toutes les expressions validées
try {
    $stmt = $pdo->prepare("
        SELECT e.idExpression, MIN(e.created_at) as created_at, i.code_projet, i.nom_client, i.description_projet
        FROM expression_dym e
        JOIN identification_projet i ON e.idExpression = i.idExpression
        WHERE (e.valide_stock = 'validé')
        GROUP BY e.idExpression, i.code_projet, i.nom_client, i.description_projet
        ORDER BY MIN(e.created_at) DESC
    ");
    $stmt->execute();
    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toutes les Expressions | Service Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

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

        .view-pdf-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            background-color: #3b82f6;
            color: white;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .view-pdf-btn:hover {
            background-color: #2563eb;
        }

        .view-pdf-btn .material-icons {
            font-size: 16px;
            margin-right: 4px;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../components/navbar_stock.php'; ?>

        <main class="flex-1 p-6">
            <!-- Top Bar -->
            <div class="top-bar p-4 mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-4">
                    <a href="dashboard.php">
                        <button class="back-btn">
                            <span class="material-icons" style="font-size: 18px;">arrow_back</span>
                            Retour au Dashboard
                        </button>
                    </a>
                    <h2 class="text-xl font-bold">Toutes les Expressions</h2>
                </div>

                <div class="date-time">
                    <span class="material-icons">calendar_today</span>
                    <span id="date-time-display"></span>
                </div>
            </div>

            <!-- Main Content -->
            <div class="card-container p-6">
                <div class="flex mb-4">
                    <button id="export-excel" class="export-btn export-excel">
                        Excel
                    </button>
                    <button id="export-pdf" class="export-btn export-pdf">
                        PDF
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table id="expressions-table" class="min-w-full">
                        <thead>
                            <tr>
                                <th>N° Expression</th>
                                <th>Client</th>
                                <th>Projet</th>
                                <th>Description</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($expressions) && is_array($expressions) && count($expressions) > 0): ?>
                                <?php foreach ($expressions as $expression): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($expression['idExpression']) ?></td>
                                        <td><?= htmlspecialchars($expression['nom_client'] ?? 'Non spécifié') ?></td>
                                        <td><?= htmlspecialchars($expression['code_projet'] ?? 'Non spécifié') ?></td>
                                        <td><?= htmlspecialchars($expression['description_projet'] ?? 'Non spécifié') ?></td>
                                        <td><?= date('d/m/Y', strtotime($expression['created_at'])) ?></td>
                                        <td>
                                            <a href="generate_pdf.php?id=<?= htmlspecialchars($expression['idExpression']) ?>" target="_blank" class="view-pdf-btn">
                                                <span class="material-icons">visibility</span>
                                                Voir PDF
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">Aucune expression trouvée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <?php include_once '../components/footer.html'; ?>
    </div>

    <!-- jQuery and DataTables scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

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

            // Initialize DataTable with export buttons
            const table = $('#expressions-table').DataTable({
                responsive: true,
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                },
                dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[4, 'desc']], // Trier par date de création par défaut
                columnDefs: [
                    { 
                        targets: -1, // Colonne Actions
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            // Excel export button
            $('#export-excel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });

            // PDF export button
            $('#export-pdf').on('click', function() {
                table.button('.buttons-pdf').trigger();
            });

            // Add export buttons (hidden but triggered by our custom buttons)
            new $.fn.dataTable.Buttons(table, {
                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: 'Excel',
                        title: 'Liste des Expressions',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4]
                        },
                        className: 'hidden'
                    },
                    {
                        extend: 'pdfHtml5',
                        text: 'PDF',
                        title: 'Liste des Expressions',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4]
                        },
                        className: 'hidden',
                        orientation: 'landscape'
                    }
                ]
            });
            
            table.buttons().container().appendTo($('.dataTables_length').parent());
        });
    </script>
</body>

</html>