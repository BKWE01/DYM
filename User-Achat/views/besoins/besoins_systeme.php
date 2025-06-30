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

// Inclure la connexion à la base de données
include_once '../../../database/connection.php';

// Récupérer tous les besoins système avec les infos du demandeur
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.idBesoin, 
            d.service_demandeur, 
            d.nom_prenoms, 
            d.date_demande, 
            d.motif_demande,
            SUM(b.qt_demande) as total_quantity,
            MAX(b.stock_status) as stock_status,
            MAX(b.achat_status) as achat_status,
            MIN(b.created_at) as created_at
        FROM besoins b
        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
        GROUP BY b.idBesoin, d.service_demandeur, d.nom_prenoms, d.date_demande, d.motif_demande
        ORDER BY MIN(b.created_at) DESC
    ");
    $stmt->execute();
    $besoins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toutes les Expressions Système | Service Stock</title>
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
        <?php include_once '../../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- Top Bar -->
            <div class="top-bar p-4 mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-4">
                    <a href="index.php">
                        <button class="back-btn">
                            <span class="material-icons" style="font-size: 18px;">arrow_back</span>
                            Retour au tableau de bord
                        </button>
                    </a>
                    <h2 class="text-xl font-bold">Toutes les Expressions Système</h2>
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
                    <table id="besoins-table" class="min-w-full">
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
                            <?php if (isset($besoins) && is_array($besoins) && count($besoins) > 0): ?>
                                <?php foreach ($besoins as $besoin): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($besoin['idBesoin']) ?></td>
                                        <td><?= htmlspecialchars($besoin['service_demandeur'] ?? 'Non spécifié') ?></td>
                                        <td><?= htmlspecialchars($besoin['nom_prenoms'] ?? 'Non spécifié') ?></td>
                                        <td><?= isset($besoin['date_demande']) ? date('d/m/Y', strtotime($besoin['date_demande'])) : 'Non spécifié' ?>
                                        </td>
                                        <td>
                                            <?php if (isset($besoin['motif_demande']) && strlen($besoin['motif_demande']) > 50): ?>
                                                <?= htmlspecialchars(substr($besoin['motif_demande'], 0, 50)) ?>...
                                            <?php else: ?>
                                                <?= htmlspecialchars($besoin['motif_demande'] ?? 'Non spécifié') ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $besoin['total_quantity'] ?? 0 ?></td>
                                        <td>
                                            <?php
                                            $status = 'En attente';
                                            $statusClass = 'status-pending';

                                            if ($besoin['stock_status'] === 'validé') {
                                                $status = 'Validé - Stock';
                                                $statusClass = 'status-stocked';
                                            } elseif ($besoin['achat_status'] === 'validé') {
                                                $status = 'Validé - Achat';
                                                $statusClass = 'status-purchasing';
                                            }
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>"><?= $status ?></span>
                                        </td>
                                        <td>
                                            <a href="../../api/pdf/expression_syst_pdf.php?id=<?= htmlspecialchars($besoin['idBesoin']) ?>"
                                                target="_blank" class="view-pdf-btn">
                                                <span class="material-icons">picture_as_pdf</span>
                                                Voir PDF
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">Aucune expression système trouvée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

    <!-- Plugin pour le tri des dates françaises -->
    <script src="../../../assets/js/datatable-date-fr.js"></script>

<script>
$(document).ready(function () {
    /**
     * Mise à jour de la date et heure en temps réel
     */
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

    // Initialisation de l'affichage de la date
    updateDateTime();
    setInterval(updateDateTime, 60000); // Mise à jour chaque minute

    /**
     * Configuration et initialisation de DataTable
     * avec support complet des dates françaises
     */
    const table = $('#besoins-table').DataTable({
        responsive: true,
        language: {
            url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
        },
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
        order: [[3, 'desc']], // Tri par date décroissant par défaut (dates les plus récentes en premier)
        columnDefs: [
            {
                targets: 3, // Colonne Date
                type: 'date-fr', // Utiliser notre plugin de tri français
                orderData: [3], // Utiliser les données de tri de cette colonne
                render: function(data, type, row, meta) {
                    if (type === 'display') {
                        return data; // Afficher tel quel (déjà formaté en dd/mm/yyyy dans PHP)
                    }
                    if (type === 'type' || type === 'sort') {
                        // Pour le tri, utiliser l'attribut data-order s'il existe
                        var $cell = $(meta.settings.nTable).find('tbody tr').eq(meta.row).find('td').eq(meta.col);
                        var orderData = $cell.attr('data-order');
                        return orderData || data;
                    }
                    return data;
                }
            },
            {
                targets: 4, // Colonne Motif
                orderable: true,
                searchable: true,
                width: "20%",
                render: function(data, type, row) {
                    if (type === 'display' && data && data.length > 50) {
                        return '<span title="' + data + '">' + data.substring(0, 50) + '...</span>';
                    }
                    return data;
                }
            },
            {
                targets: 5, // Colonne Quantité totale
                className: 'text-center',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return new Intl.NumberFormat('fr-FR').format(data);
                    }
                    return data;
                }
            },
            {
                targets: -1, // Colonne Actions (dernière colonne)
                orderable: false,
                searchable: false,
                className: 'text-center'
            }
        ],
        // Configuration des boutons d'export
        buttons: [
            {
                extend: 'excelHtml5',
                text: 'Excel',
                className: 'export-btn export-excel',
                title: 'Liste_Expressions_Système_' + new Date().toISOString().split('T')[0],
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6] // Exclure la colonne Actions
                }
            },
            {
                extend: 'pdfHtml5',
                text: 'PDF',
                className: 'export-btn export-pdf',
                title: 'Liste des Expressions Système',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6] // Exclure la colonne Actions
                }
            }
        ]
    });

    /**
     * Gestionnaires d'événements pour l'export
     */
    $('#export-excel').on('click', function() {
        table.button('.buttons-excel').trigger();
    });

    $('#export-pdf').on('click', function() {
        table.button('.buttons-pdf').trigger();
    });

    /**
     * Amélioration de l'interface utilisateur
     */
    // Ajouter un indicateur de chargement
    table.on('processing.dt', function (e, settings, processing) {
        if (processing) {
            $('.dataTables_processing').show();
        } else {
            $('.dataTables_processing').hide();
        }
    });

    // Optimisation des performances pour les grandes tables
    if (table.rows().count() > 100) {
        table.page.len(25).draw();
    }

    // Debug: Vérifier le tri des dates
    console.log('DataTable initialisé avec', table.rows().count(), 'lignes');
    console.log('Plugin de tri des dates françaises chargé');
});
</script>
</body>

</html>