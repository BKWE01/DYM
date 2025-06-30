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

// Vérifier si la catégorie est spécifiée
if (!isset($_GET['categorie']) || empty($_GET['categorie'])) {
    header("Location: ./index.php");
    exit();
}

$categorie = $_GET['categorie'];

// Connexion à la base de données
include_once '../../database/connection.php';

// Récupérer les informations de la catégorie
$categoryInfo = null;
try {
    $catQuery = "SELECT * FROM categories_fournisseurs WHERE nom = :nom";
    $catStmt = $pdo->prepare($catQuery);
    $catStmt->bindParam(':nom', $categorie);
    $catStmt->execute();
    $categoryInfo = $catStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$categoryInfo) {
        // Si la catégorie n'existe pas dans la table categories_fournisseurs, créer un objet temporaire
        $categoryInfo = [
            'nom' => $categorie,
            'description' => '',
            'couleur' => 'badge-blue',
            'icone' => 'category',
            'active' => 1
        ];
    }
} catch (PDOException $e) {
    // Erreur silencieuse
}

// Récupérer les fournisseurs de cette catégorie
$fournisseurs = [];
try {
    $query = "SELECT f.* 
              FROM fournisseurs f
              JOIN fournisseur_categories fc ON f.id = fc.fournisseur_id
              WHERE fc.categorie = :categorie
              ORDER BY f.nom ASC";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':categorie', $categorie);
    $stmt->execute();
    $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer toutes les catégories pour chaque fournisseur
    foreach ($fournisseurs as &$fournisseur) {
        $categoryQuery = "SELECT categorie FROM fournisseur_categories WHERE fournisseur_id = :id";
        $categoryStmt = $pdo->prepare($categoryQuery);
        $categoryStmt->bindParam(':id', $fournisseur['id']);
        $categoryStmt->execute();
        $fournisseur['categories'] = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    // Erreur silencieuse
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fournisseurs de la catégorie <?php echo htmlspecialchars($categorie); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <style>
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

        table.dataTable tbody tr.odd {
            background-color: #f9fafb;
        }

        table.dataTable tbody tr.even {
            background-color: #ffffff;
        }

        table.dataTable tbody tr:hover {
            background-color: #f1f5f9;
        }

        /* Style pour les badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            color: #fff;
            white-space: nowrap;
            border-radius: 9999px;
            margin-bottom: 0.25rem;
        }

        .badge-blue {
            background-color: #3b82f6;
        }

        .badge-green {
            background-color: #10b981;
        }

        .badge-purple {
            background-color: #8b5cf6;
        }

        .badge-orange {
            background-color: #f59e0b;
        }

        .badge-red {
            background-color: #ef4444;
        }

        .badge-gray {
            background-color: #6b7280;
        }

        .badge-pink {
            background-color: #ec4899;
        }

        .badge-indigo {
            background-color: #6366f1;
        }

        .badge-yellow {
            background-color: #facc15;
            color: #1f2937;
        }

        .badge-lime {
            background-color: #84cc16;
        }

        .badge-teal {
            background-color: #14b8a6;
        }

        .badge-cyan {
            background-color: #06b6d4;
        }

        .badge-brown {
            background-color: #a47148;
        }

        /* Tooltips */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Category badges */
        .category-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .category-badge {
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
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper flex flex-col min-h-screen">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
                <h1 class="text-xl font-semibold flex items-center space-x-2">
                    <?php if (!empty($categoryInfo['icone'])): ?>
                        <span class="material-icons"><?php echo htmlspecialchars($categoryInfo['icone']); ?></span>
                    <?php else: ?>
                        <span class="material-icons">category</span>
                    <?php endif; ?>
                    <span>Fournisseurs de la catégorie <span class="badge <?php echo htmlspecialchars($categoryInfo['couleur']); ?>"><?php echo htmlspecialchars($categorie); ?></span></span>
                </h1>

                <div class="flex items-center space-x-4">
                    <a href="./index.php" class="flex items-center text-gray-600 hover:text-gray-900">
                        <span class="material-icons mr-1">arrow_back</span>
                        Retour aux catégories
                    </a>
                    <a href="../fournisseurs.php" class="flex items-center text-gray-600 hover:text-gray-900">
                        <span class="material-icons mr-1">storefront</span>
                        Tous les fournisseurs
                    </a>
                </div>
            </div>

            <?php if (!empty($categoryInfo['description'])): ?>
                <div class="bg-white shadow-sm rounded-lg p-4 mb-4">
                    <h2 class="text-lg font-semibold mb-2">Description</h2>
                    <p><?php echo nl2br(htmlspecialchars($categoryInfo['description'])); ?></p>
                </div>
            <?php endif; ?>

            <!-- Section Liste des fournisseurs -->
            <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-semibold">Liste des fournisseurs (<?php echo count($fournisseurs); ?>)</h2>
                    <div class="relative">
                        <input type="text" id="supplier-search" placeholder="Rechercher un fournisseur"
                            class="pl-10 py-2 pr-4 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <span class="absolute left-3 top-2 text-gray-400 material-icons">search</span>
                    </div>
                </div>

                <?php if (empty($fournisseurs)): ?>
                    <div class="text-center py-8">
                        <span class="material-icons text-gray-400 text-5xl mb-4">sentiment_dissatisfied</span>
                        <p class="text-gray-500">Aucun fournisseur n'est associé à cette catégorie.</p>
                        <a href="../fournisseurs.php" class="mt-4 inline-flex items-center text-blue-600 hover:text-blue-800">
                            <span class="material-icons mr-1">add</span>
                            Ajouter un fournisseur à cette catégorie
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table id="suppliers-table" class="min-w-full">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Catégories</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Adresse</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fournisseurs as $fournisseur): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fournisseur['nom']); ?></td>
                                        <td>
                                            <?php if (!empty($fournisseur['categories'])): ?>
                                                <div class="category-badges">
                                                    <?php foreach ($fournisseur['categories'] as $cat): ?>
                                                        <span class="badge <?php echo $cat === $categorie ? $categoryInfo['couleur'] : getBadgeColor($cat); ?>">
                                                            <?php echo htmlspecialchars($cat); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo !empty($fournisseur['email']) ? htmlspecialchars($fournisseur['email']) : '<span class="text-gray-400">-</span>'; ?>
                                        </td>
                                        <td><?php echo !empty($fournisseur['telephone']) ? htmlspecialchars($fournisseur['telephone']) : '<span class="text-gray-400">-</span>'; ?>
                                        </td>
                                        <td><?php echo !empty($fournisseur['adresse']) ? htmlspecialchars($fournisseur['adresse']) : '<span class="text-gray-400">-</span>'; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($fournisseur['notes'])): ?>
                                                <div class="tooltip">
                                                    <span class="material-icons text-gray-500">note</span>
                                                    <span
                                                        class="tooltiptext"><?php echo htmlspecialchars($fournisseur['notes']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="../fournisseurs.php" class="text-blue-500 hover:text-blue-700 mx-1" title="Éditer">
                                                <span class="material-icons">edit</span>
                                            </a>
                                            <a href="#" class="view-orders text-green-500 hover:text-green-700 mx-1" 
                                               data-supplier="<?php echo htmlspecialchars($fournisseur['nom']); ?>" title="Voir les commandes">
                                                <span class="material-icons">visibility</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <!-- Modal pour afficher les commandes d'un fournisseur -->
    <div id="supplier-orders-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Commandes du fournisseur: <span id="supplier-name"></span></h2>
            <div id="supplier-orders-content" class="max-h-96 overflow-y-auto">
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="text-left py-2">Produit</th>
                            <th class="text-left py-2">Projet</th>
                            <th class="text-left py-2">Quantité</th>
                            <th class="text-left py-2">Prix</th>
                            <th class="text-left py-2">Date</th>
                            <th class="text-left py-2">Statut</th>
                        </tr>
                    </thead>
                    <tbody id="supplier-orders-body">
                        <!-- Les commandes seront chargées dynamiquement ici -->
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="button"
                    class="cancel-modal bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Scripts jQuery et DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>

    <script>
        $(document).ready(function () {
            // Initialisation des DataTables
            const suppliersTable = $('#suppliers-table').DataTable({
                responsive: true,
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                },
                order: [[0, 'asc']], // Tri par nom
                pageLength: 15
            });

            // Recherche en temps réel
            $('#supplier-search').on('keyup', function () {
                suppliersTable.search(this.value).draw();
            });

            // Ouvrir la modale des commandes du fournisseur
            $('.view-orders').on('click', function (e) {
                e.preventDefault();
                const supplier = $(this).data('supplier');
                loadSupplierOrders(supplier);
            });

            // Fermer les modales
            $('.close, .cancel-modal').on('click', function () {
                $('.modal').css('display', 'none');
            });

            // Fermer les modales en cliquant en dehors
            $(window).on('click', function (event) {
                if ($(event.target).hasClass('modal')) {
                    $('.modal').css('display', 'none');
                }
            });

            // Charger les commandes d'un fournisseur
            function loadSupplierOrders(supplier) {
                $.ajax({
                    url: '../get_supplier_orders.php',
                    type: 'GET',
                    data: { supplier: supplier },
                    dataType: 'json',
                    success: function (response) {
                        $('#supplier-name').text(supplier);

                        if (response.success && response.orders.length > 0) {
                            let html = '';
                            response.orders.forEach(function (order) {
                                let statusBadge;
                                switch (order.status) {
                                    case 'commandé':
                                        statusBadge = '<span class="badge badge-blue">Commandé</span>';
                                        break;
                                    case 'reçu':
                                        statusBadge = '<span class="badge badge-green">Reçu</span>';
                                        break;
                                    default:
                                        statusBadge = '<span class="badge badge-orange">En attente</span>';
                                }

                                html += `
                                    <tr>
                                        <td class="py-2">${order.designation}</td>
                                        <td class="py-2">${order.code_projet || '-'}</td>
                                        <td class="py-2">${order.quantity} ${order.unit}</td>
                                        <td class="py-2">${formatMoney(order.prix_unitaire)} FCFA</td>
                                        <td class="py-2">${formatDate(order.date_achat)}</td>
                                        <td class="py-2">${statusBadge}</td>
                                    </tr>
                                `;
                            });
                            $('#supplier-orders-body').html(html);
                        } else {
                            $('#supplier-orders-body').html('<tr><td colspan="6" class="text-center py-4">Aucune commande trouvée pour ce fournisseur</td></tr>');
                        }

                        $('#supplier-orders-modal').css('display', 'block');
                    },
                    error: function () {
                        showNotification("Erreur de communication avec le serveur", "error");
                    }
                });
            }

            // Formater un montant en FCFA
            function formatMoney(amount) {
                return new Intl.NumberFormat('fr-FR').format(amount);
            }

            // Formater une date
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('fr-FR');
            }

            // Afficher une notification
            function showNotification(message, type) {
                Swal.fire({
                    text: message,
                    icon: type === 'error' ? 'error' : 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        });
    </script>

    <?php
    // Fonction pour déterminer la couleur du badge en fonction de la catégorie
    function getBadgeColor($category)
    {
        try {
            global $pdo;
            $query = "SELECT couleur FROM categories_fournisseurs WHERE nom = :nom";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':nom', $category);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['couleur'];
            }
        } catch (PDOException $e) {
            // Erreur silencieuse
        }
        
        // Couleurs par défaut si la catégorie n'est pas trouvée dans la table
        $categories = [
            'Matériaux ferreux' => 'badge-blue',
            'Matériaux non ferreux' => 'badge-green',
            'Électrique' => 'badge-purple',
            'Plomberie' => 'badge-orange',
            'Outillage' => 'badge-red',
            'Quincaillerie' => 'badge-blue',
            'Peinture' => 'badge-purple',
            'Divers' => 'badge-orange'
        ];

        return isset($categories[$category]) ? $categories[$category] : 'badge-blue';
    }
    ?>
</body>

</html>