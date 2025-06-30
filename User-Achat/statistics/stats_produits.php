<?php

/**
 * Statistiques Détaillées par Produit - Version Sans Modal
 * Fichier: /User-Achat/statistics/stats_produits.php
 * Mise à jour: Redirection vers page de détails dédiée
 */

session_start();

// Headers pour éviter la mise en cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../index.php");
    exit();
}

// Connexion à la base de données
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Paramètres de filtrage
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'all';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Récupérer les catégories pour le filtre (requête simple)
    $categoriesQuery = "SELECT id, libelle FROM categories ORDER BY libelle";
    $categoriesStmt = $pdo->prepare($categoriesQuery);
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer uniquement les statistiques globales (optimisé)
    $whereConditions = ["1=1"];
    $params = [];

    if ($selectedCategory !== 'all') {
        $whereConditions[] = "p.category = :category_id";
        $params[':category_id'] = $selectedCategory;
    }

    if (!empty($searchTerm)) {
        $whereConditions[] = "(p.product_name LIKE :search OR p.barcode LIKE :search)";
        $params[':search'] = "%$searchTerm%";
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Requête optimisée pour les statistiques globales uniquement
    $globalStatsQuery = "
        SELECT 
            COUNT(*) as total_produits,
            COUNT(CASE WHEN quantity > 0 THEN 1 END) as produits_en_stock,
            COUNT(CASE WHEN quantity = 0 THEN 1 END) as produits_rupture,
            COUNT(CASE WHEN quantity BETWEEN 1 AND 5 THEN 1 END) as produits_critique,
            COALESCE(SUM(quantity * unit_price), 0) as valeur_totale,
            COALESCE(AVG(unit_price), 0) as prix_moyen
        FROM products p
        LEFT JOIN categories c ON p.category = c.id
        WHERE $whereClause
    ";

    $globalStatsStmt = $pdo->prepare($globalStatsQuery);
    foreach ($params as $key => $value) {
        $globalStatsStmt->bindValue($key, $value);
    }
    $globalStatsStmt->execute();
    $globalStats = $globalStatsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des données: " . $e->getMessage();
}

// Fonctions utilitaires
function formatNumber($number)
{
    return number_format(floatval($number), 0, ',', ' ');
}

function formatMoney($amount)
{
    return number_format(floatval($amount), 0, ',', ' ') . ' FCFA';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques Détaillées par Produit | Service Achat</title>

    <!-- CSS Optimisé -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables CSS - Version légère -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
        }

        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .card {
            transition: all 0.3s ease;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* DataTables Optimisé */
        table.dataTable {
            width: 100% !important;
            clear: both;
            border-collapse: separate;
            border-spacing: 0;
        }

        table.dataTable thead th {
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

        table.dataTable tbody tr:hover {
            background-color: #f1f5f9;
            cursor: pointer;
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
            text-decoration: none;
        }

        .view-btn:hover {
            background-color: #2563eb;
            color: #fff;
            transform: translateY(-1px);
        }

        .export-btn {
            padding: 6px 12px;
            margin-right: 8px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .export-excel {
            background-color: #d1fae5;
            color: #047857;
        }

        .export-excel:hover {
            background-color: #a7f3d0;
        }

        .export-pdf {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .export-pdf:hover {
            background-color: #fecaca;
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

        /* Loading states */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Success message styling */
        .success-message {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .success-message .material-icons {
            color: #047857;
        }

        .success-message p {
            color: #065f46;
            font-weight: 500;
        }

        .bg-orange-100 {
            background-color: #fff0d4;
        }

        .text-orange-800 {
            color: orange;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- Message de succès si retour de la page détails -->
            <?php if (isset($_GET['from']) && $_GET['from'] === 'details'): ?>
                <div class="success-message">
                    <div class="flex items-center">
                        <span class="material-icons mr-2">check_circle</span>
                        <p>Vous revenez de la page de détails d'un produit.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <div class="flex flex-wrap justify-between items-center mb-4">
                    <div class="flex items-center mb-2 md:mb-0">
                        <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-3 flex items-center">
                            <span class="material-icons">arrow_back</span>
                        </a>
                        <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                            <span class="material-icons mr-2 text-blue-500">inventory</span>
                            Statistiques Détaillées par Produit
                        </h1>
                    </div>
                    <button id="export-rapport-pdf" class="export-btn bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center ml-2">
                        <span class="material-icons mr-2">description</span>
                        Rapport PDF
                    </button>
                    <div class="date-time">
                        <span class="material-icons">calendar_today</span>
                        <span id="date-time-display"></span>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                        <span class="material-icons text-blue-600 mr-2">info</span>
                        <p class="text-blue-800 text-sm">
                            <strong>Nouvelle fonctionnalité :</strong> Cliquez sur "Voir Détails" pour accéder à une page complète
                            avec toutes les informations détaillées du produit, incluant graphiques et historiques complets.
                        </p>
                    </div>
                </div>

                <!-- Filtres optimisés -->
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                        <div class="relative">
                            <input type="text" name="search" id="search-input"
                                value="<?php echo htmlspecialchars($searchTerm); ?>"
                                placeholder="Nom ou code-barres..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <span class="material-icons absolute left-3 top-2.5 text-gray-400">search</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
                        <select name="category" id="category-select"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all" <?php echo $selectedCategory == 'all' ? 'selected' : ''; ?>>
                                Toutes les catégories
                            </option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"
                                    <?php echo $selectedCategory == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['libelle']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                            <span class="material-icons mr-2">search</span>
                            Rechercher
                        </button>
                        <a href="stats_produits.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center">
                            <span class="material-icons mr-2">refresh</span>
                            Réinitialiser
                        </a>
                    </div>
                </form>
            </div>

            <!-- Statistiques globales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="card bg-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">Total Produits</p>
                            <h3 class="text-2xl font-bold text-gray-800">
                                <?php echo formatNumber($globalStats['total_produits']); ?>
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">
                                Valeur totale: <?php echo formatMoney($globalStats['valeur_totale']); ?>
                            </p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <span class="material-icons text-blue-600">inventory</span>
                        </div>
                    </div>
                </div>

                <div class="card bg-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">En Stock</p>
                            <h3 class="text-2xl font-bold text-green-600">
                                <?php echo formatNumber($globalStats['produits_en_stock']); ?>
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo formatNumber(($globalStats['produits_en_stock'] / max($globalStats['total_produits'], 1)) * 100); ?>% du total
                            </p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <span class="material-icons text-green-600">check_circle</span>
                        </div>
                    </div>
                </div>

                <div class="card bg-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">Stock Critique</p>
                            <h3 class="text-2xl font-bold text-orange-600">
                                <?php echo formatNumber($globalStats['produits_critique']); ?>
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">
                                Nécessite attention
                            </p>
                        </div>
                        <div class="bg-orange-100 p-3 rounded-full">
                            <span class="material-icons text-orange-600">warning</span>
                        </div>
                    </div>
                </div>

                <div class="card bg-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">Rupture</p>
                            <h3 class="text-2xl font-bold text-red-600">
                                <?php echo formatNumber($globalStats['produits_rupture']); ?>
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">
                                Réapprovisionnement urgent
                            </p>
                        </div>
                        <div class="bg-red-100 p-3 rounded-full">
                            <span class="material-icons text-red-600">error</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau optimisé avec DataTables -->
            <div class="card bg-white p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Liste des Produits</h2>
                    <div class="flex items-center space-x-2">
                        <button id="export-excel" class="export-btn export-excel">
                            <span class="material-icons mr-1">file_download</span>
                            Excel
                        </button>
                        <button id="export-pdf" class="export-btn export-pdf">
                            <span class="material-icons mr-1">picture_as_pdf</span>
                            PDF
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="products-table" class="min-w-full display responsive nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Stock</th>
                                <th>Valeur</th>
                                <th>Rotation</th>
                                <th>Projets</th>
                                <th>Achats</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Les données seront chargées via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <!-- Scripts optimisés -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // Mise à jour de l'heure
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
                document.getElementById('date-time-display').textContent =
                    now.toLocaleDateString('fr-FR', options);
            }
            updateDateTime();
            setInterval(updateDateTime, 60000);

            // Configuration DataTable optimisée
            const table = $('#products-table').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                deferRender: true,
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json",
                    processing: "Chargement en cours..."
                },
                ajax: {
                    url: 'api/get_products_data.php',
                    type: 'POST',
                    data: function(d) {
                        d.category = $('#category-select').val();
                        d.search_term = $('#search-input').val();
                    },
                    error: function(xhr, error, code) {
                        console.error('Erreur AJAX:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur de chargement',
                            text: 'Impossible de charger les données. Veuillez rafraîchir la page.'
                        });
                    }
                },
                columns: [{
                        data: null,
                        render: function(data, type, row) {
                            return `
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">${row.product_name}</div>
                                        <div class="text-sm text-gray-500">${row.barcode} • ${row.category_name || 'Non catégorisé'}</div>
                                    </div>
                                </div>
                            `;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            let reservedHtml = '';
                            if (row.quantity_reserved > 0) {
                                reservedHtml = `<div class="text-xs text-orange-600">Réservé: ${formatNumber(row.quantity_reserved)}</div>`;
                            }
                            return `
                                <div class="text-sm text-gray-900">${formatNumber(row.quantity)} ${row.unit}</div>
                                ${reservedHtml}
                            `;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return `
                                <div class="text-sm text-gray-900">${formatMoney(row.stock_value)}</div>
                                <div class="text-xs text-gray-500">P.U: ${formatMoney(row.unit_price)}</div>
                            `;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return `
                                <div class="text-sm text-gray-900">${row.taux_rotation}x</div>
                                <div class="text-xs text-gray-500">${formatNumber(row.total_sorties)} sorties • ${row.frequence_mensuelle}/mois</div>
                            `;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return `
                                <div class="text-sm text-gray-900">${formatNumber(row.nb_projets)}</div>
                                <div class="text-xs text-gray-500">${formatNumber(row.quantite_demandee)} demandées</div>
                            `;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return `
                                <div class="text-sm text-gray-900">${formatNumber(row.nb_commandes)}</div>
                                <div class="text-xs text-gray-500">${formatMoney(row.montant_total_achats)}</div>
                            `;
                        }
                    },
                    {
                        data: 'statut_stock',
                        render: function(data, type, row) {
                            const status = getStatusInfo(data);
                            return `<span class="stat-badge ${status.class}">${status.text}</span>`;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `
                                <a href="details/product_details.php?id=${row.id}" class="view-btn">
                                    <span class="material-icons mr-1">visibility</span>
                                    Voir Détails
                                </a>
                            `;
                        }
                    }
                ],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                order: [
                    [3, 'desc']
                ], // Tri par rotation par défaut
                dom: 'Bfrtip',
                buttons: [{
                        extend: 'excelHtml5',
                        text: 'Excel',
                        title: 'Statistiques Produits - ' + new Date().toLocaleDateString('fr-FR'),
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6]
                        },
                        className: 'hidden buttons-excel'
                    },
                    {
                        extend: 'pdfHtml5',
                        text: 'PDF',
                        title: 'Statistiques Produits - ' + new Date().toLocaleDateString('fr-FR'),
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6]
                        },
                        className: 'hidden buttons-pdf',
                        orientation: 'landscape'
                    }
                ],
                drawCallback: function(settings) {
                    // Ajouter des tooltips aux lignes
                    $('#products-table tbody tr').each(function() {
                        const data = table.row(this).data();
                        if (data) {
                            $(this).attr('title', `Cliquez sur "Voir Détails" pour accéder aux informations complètes de ${data.product_name}`);
                        }
                    });
                }
            });

            // Export handlers
            $('#export-excel').on('click', function() {
                table.button('.buttons-excel').trigger();

                // Afficher un message de succès
                Swal.fire({
                    icon: 'success',
                    title: 'Export Excel',
                    text: 'Le fichier Excel a été généré avec succès !',
                    timer: 2000,
                    showConfirmButton: false
                });
            });

            $('#export-pdf').on('click', function() {
                table.button('.buttons-pdf').trigger();

                // Afficher un message de succès
                Swal.fire({
                    icon: 'success',
                    title: 'Export PDF',
                    text: 'Le fichier PDF a été généré avec succès !',
                    timer: 2000,
                    showConfirmButton: false
                });
            });

            // Gestionnaire pour l'export du rapport PDF complet
            $('#export-rapport-pdf').on('click', function() {
                // Récupérer les filtres actuels
                const categoryFilter = $('#category-select').val();
                const searchFilter = $('#search-input').val();

                // Afficher le loading
                Swal.fire({
                    title: 'Génération du rapport PDF',
                    text: 'Génération en cours du rapport détaillé des produits...',
                    icon: 'info',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Construire l'URL avec les paramètres de filtre
                let reportUrl = 'generate_report.php?type=produits';

                if (categoryFilter && categoryFilter !== 'all') {
                    reportUrl += '&category=' + encodeURIComponent(categoryFilter);
                }

                if (searchFilter && searchFilter.trim() !== '') {
                    reportUrl += '&search=' + encodeURIComponent(searchFilter.trim());
                }

                // Rediriger vers le générateur de rapport après un court délai
                setTimeout(() => {
                    window.location.href = reportUrl;

                    // Fermer le dialogue après redirection
                    setTimeout(() => {
                        Swal.close();
                    }, 2000);
                }, 1500);
            });

            // Reload table on filter change avec debounce optimisé
            let searchTimeout;
            $('#category-select, #search-input').on('change keyup', function() {
                clearTimeout(searchTimeout);
                const delay = $(this).is('#search-input') ? 500 : 100; // Plus de délai pour la recherche textuelle

                searchTimeout = setTimeout(function() {
                    table.draw();
                }, delay);
            });

            // Amélioration UX : Loading state
            $('#products-table').on('processing.dt', function(e, settings, processing) {
                if (processing) {
                    $('#products-table_wrapper').addClass('loading');
                } else {
                    $('#products-table_wrapper').removeClass('loading');
                }
            });

            // Masquer automatiquement le message de succès après 5 secondes
            setTimeout(function() {
                $('.success-message').fadeOut(500);
            }, 5000);
        });

        // Fonctions utilitaires optimisées
        function formatNumber(number) {
            return new Intl.NumberFormat('fr-FR').format(number || 0);
        }

        function formatMoney(amount) {
            return new Intl.NumberFormat('fr-FR').format(amount || 0) + ' FCFA';
        }

        function getStatusInfo(status) {
            const statuses = {
                'rupture': {
                    class: 'bg-red-100 text-red-800',
                    text: 'Rupture'
                },
                'critique': {
                    class: 'bg-orange-100 text-orange-800',
                    text: 'Critique'
                },
                'faible': {
                    class: 'bg-yellow-100 text-yellow-800',
                    text: 'Faible'
                }
            };
            return statuses[status] || {
                class: 'bg-green-100 text-green-800',
                text: 'Normal'
            };
        }

        // Fonction pour le raccourci clavier
        document.addEventListener('keydown', function(e) {
            // Ctrl + F pour focus sur la recherche
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search-input').focus();
            }

            // Échap pour réinitialiser les filtres
            if (e.key === 'Escape') {
                document.getElementById('search-input').value = '';
                document.getElementById('category-select').value = 'all';
                $('#products-table').DataTable().draw();
            }
        });

        // Amélioration de l'accessibilité
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter des labels ARIA
            $('#products-table_filter input').attr('aria-label', 'Recherche dans le tableau');
            $('#products-table_length select').attr('aria-label', 'Nombre de lignes par page');

            // Ajouter des tooltips informatifs
            $('#search-input').attr('title', 'Recherchez par nom de produit ou code-barres (Ctrl+F pour focus rapide)');
            $('#category-select').attr('title', 'Filtrer par catégorie de produit');
        });
    </script>
</body>

</html>