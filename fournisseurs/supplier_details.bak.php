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

// Vérifier si l'ID du fournisseur est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ./fournisseurs.php");
    exit();
}

$fournisseur_id = $_GET['id'];

// Connexion à la base de données
include_once '../database/connection.php';

// Récupérer les informations du fournisseur
$fournisseur = null;
try {
    $query = "SELECT * FROM fournisseurs WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $fournisseur_id);
    $stmt->execute();
    $fournisseur = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fournisseur) {
        header("Location: ./fournisseurs.php");
        exit();
    }

    // Récupérer les catégories du fournisseur
    $categoryQuery = "SELECT categorie FROM fournisseur_categories WHERE fournisseur_id = :id";
    $categoryStmt = $pdo->prepare($categoryQuery);
    $categoryStmt->bindParam(':id', $fournisseur_id);
    $categoryStmt->execute();
    $fournisseur['categories'] = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des données du fournisseur: " . $e->getMessage();
}

// Récupérer les commandes du fournisseur
$commandes = [];
try {
    $commandesQuery = "SELECT 
                        am.id,
                        am.designation,
                        am.quantity,
                        am.unit,
                        am.prix_unitaire,
                        am.date_achat,
                        am.status,
                        am.date_reception,
                        ip.code_projet,
                        ip.nom_client
                      FROM 
                        achats_materiaux am
                      LEFT JOIN 
                        identification_projet ip ON am.expression_id = ip.idExpression
                      WHERE 
                        am.fournisseur = :nom
                      ORDER BY 
                        am.date_achat DESC";

    $commandesStmt = $pdo->prepare($commandesQuery);
    $commandesStmt->bindParam(':nom', $fournisseur['nom']);
    $commandesStmt->execute();
    $commandes = $commandesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des commandes: " . $e->getMessage();
}

// Calculer les statistiques
$statsData = [
    'total_commandes' => count($commandes),
    'montant_total' => 0,
    'prix_moyen' => 0,
    'derniere_commande' => null,
    'statuts' => [
        'commandé' => 0,
        'reçu' => 0,
        'en_attente' => 0
    ]
];

foreach ($commandes as $commande) {
    // Calculer le montant total
    $statsData['montant_total'] += $commande['quantity'] * $commande['prix_unitaire'];

    // Compter les commandes par statut
    switch ($commande['status']) {
        case 'commandé':
            $statsData['statuts']['commandé']++;
            break;
        case 'reçu':
            $statsData['statuts']['reçu']++;
            break;
        default:
            $statsData['statuts']['en_attente']++;
    }

    // Dernière commande
    if ($statsData['derniere_commande'] === null || strtotime($commande['date_achat']) > strtotime($statsData['derniere_commande'])) {
        $statsData['derniere_commande'] = $commande['date_achat'];
    }
}

// Calculer le prix moyen
if ($statsData['total_commandes'] > 0) {
    $statsData['prix_moyen'] = $statsData['montant_total'] / $statsData['total_commandes'];
}

// 1. Ajouter une variable pour les retours fournisseurs après la récupération des produits fréquents
// Récupérer les produits commandés les plus fréquents
$produitsFrequents = [];
try {
    $produitsQuery = "SELECT 
                        designation, 
                        COUNT(*) as nombre_commandes,
                        SUM(quantity) as quantite_totale,
                        AVG(prix_unitaire) as prix_moyen
                      FROM 
                        achats_materiaux
                      WHERE 
                        fournisseur = :nom
                      GROUP BY 
                        designation
                      ORDER BY 
                        nombre_commandes DESC, quantite_totale DESC
                      LIMIT 5";

    $produitsStmt = $pdo->prepare($produitsQuery);
    $produitsStmt->bindParam(':nom', $fournisseur['nom']);
    $produitsStmt->execute();
    $produitsFrequents = $produitsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Erreur silencieuse
}

// Récupérer les retours fournisseurs
$returnsData = [];
try {
    // Vérifier si la table supplier_returns existe
    $tableExists = false;
    $checkTableStmt = $pdo->prepare("SHOW TABLES LIKE 'supplier_returns'");
    $checkTableStmt->execute();
    $tableExists = $checkTableStmt->rowCount() > 0;

    if ($tableExists) {
        // Si la table existe, récupérer les retours par supplier_id
        $returnsQuery = "SELECT sr.*, p.product_name 
                         FROM supplier_returns sr
                         JOIN products p ON sr.product_id = p.id
                         WHERE sr.supplier_id = :supplier_id
                         ORDER BY sr.created_at DESC";
        $returnsStmt = $pdo->prepare($returnsQuery);
        $returnsStmt->bindParam(':supplier_id', $fournisseur_id);
        $returnsStmt->execute();
        $returnsData = $returnsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($returnsData)) {
            // Essayer par nom de fournisseur si aucun retour n'est trouvé par ID
            $returnsQuery = "SELECT sr.*, p.product_name 
                             FROM supplier_returns sr
                             JOIN products p ON sr.product_id = p.id
                             WHERE sr.supplier_name = :supplier_name
                             ORDER BY sr.created_at DESC";
            $returnsStmt = $pdo->prepare($returnsQuery);
            $returnsStmt->bindParam(':supplier_name', $fournisseur['nom']);
            $returnsStmt->execute();
            $returnsData = $returnsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Si aucun retour n'est trouvé dans la table supplier_returns ou si la table n'existe pas,
    // rechercher dans la table stock_movement
    if (empty($returnsData)) {
        $returnsQuery = "SELECT 
                            sm.id, 
                            sm.product_id, 
                            p.product_name, 
                            sm.quantity, 
                            sm.notes,
                            sm.date as created_at,
                            'pending' as status
                        FROM 
                            stock_movement sm
                        JOIN 
                            products p ON sm.product_id = p.id
                        WHERE 
                            sm.movement_type = 'output' 
                            AND sm.destination = :destination
                        ORDER BY 
                            sm.date DESC";
        $returnsStmt = $pdo->prepare($returnsQuery);
        $destination = "Retour fournisseur: " . $fournisseur['nom'];
        $returnsStmt->bindParam(':destination', $destination);
        $returnsStmt->execute();
        $returnsData = $returnsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Analyser les notes pour extraire le motif et les commentaires
        foreach ($returnsData as &$return) {
            if (!empty($return['notes'])) {
                if (strpos($return['notes'], 'Motif:') === 0) {
                    $notesContent = substr($return['notes'], strlen('Motif:'));
                    $parts = explode(' - ', $notesContent);
                    $return['reason'] = trim($parts[0]);
                    if (count($parts) > 1) {
                        $return['comment'] = trim($parts[1]);
                    }
                }
            }
        }
    }

    // Calculer le nombre total de retours et la quantité totale retournée
    $totalReturns = count($returnsData);
    $totalReturnedQuantity = 0;
    foreach ($returnsData as $return) {
        $totalReturnedQuantity += $return['quantity'];
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
    <title>Détails du fournisseur - <?php echo htmlspecialchars($fournisseur['nom']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
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

        /* Style pour les cartes de statistiques */
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
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

        /* Tabs styles */
        .tab-active {
            color: #3b82f6;
            border-bottom: 2px solid #3b82f6;
        }

        /* Make the navigation sticky */
        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #fff;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper flex flex-col min-h-screen">
        <?php include_once '../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- Header with supplier info and actions -->
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
                <h1 class="text-xl font-semibold flex items-center space-x-2">
                    <span class="material-icons">storefront</span>
                    <span>Détails du fournisseur: <?php echo htmlspecialchars($fournisseur['nom']); ?></span>
                </h1>

                <div class="flex items-center space-x-4">
                    <a href="./fournisseurs.php" class="flex items-center text-gray-600 hover:text-gray-900">
                        <span class="material-icons mr-1">arrow_back</span>
                        Retour à la liste
                    </a>
                    <button id="edit-supplier-btn" data-id="<?php echo $fournisseur['id']; ?>"
                        class="flex items-center bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition-colors duration-300">
                        <span class="material-icons mr-2">edit</span>
                        Modifier
                    </button>
                </div>
            </div>

            <!-- Supplier information card -->
            <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="col-span-1">
                        <h2 class="text-lg font-semibold mb-4">Informations générales</h2>
                        <ul class="space-y-3">
                            <li class="flex items-start">
                                <span class="material-icons text-gray-500 mr-2">business</span>
                                <div>
                                    <span class="text-sm text-gray-500">Nom</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($fournisseur['nom']); ?></p>
                                </div>
                            </li>
                            <li class="flex items-start">
                                <span class="material-icons text-gray-500 mr-2">category</span>
                                <div>
                                    <span class="text-sm text-gray-500">Catégories</span>
                                    <div class="category-badges mt-1">
                                        <?php if (!empty($fournisseur['categories'])): ?>
                                            <?php foreach ($fournisseur['categories'] as $categorie): ?>
                                                <span class="badge <?php echo getBadgeColor($categorie); ?>">
                                                    <?php echo htmlspecialchars($categorie); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">Aucune catégorie</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                            <li class="flex items-start">
                                <span class="material-icons text-gray-500 mr-2">email</span>
                                <div>
                                    <span class="text-sm text-gray-500">Email</span>
                                    <p class="font-medium">
                                        <?php echo !empty($fournisseur['email']) ? htmlspecialchars($fournisseur['email']) : '<span class="text-gray-400">Non renseigné</span>'; ?>
                                    </p>
                                </div>
                            </li>
                            <li class="flex items-start">
                                <span class="material-icons text-gray-500 mr-2">phone</span>
                                <div>
                                    <span class="text-sm text-gray-500">Téléphone</span>
                                    <p class="font-medium">
                                        <?php echo !empty($fournisseur['telephone']) ? htmlspecialchars($fournisseur['telephone']) : '<span class="text-gray-400">Non renseigné</span>'; ?>
                                    </p>
                                </div>
                            </li>
                            <li class="flex items-start">
                                <span class="material-icons text-gray-500 mr-2">location_on</span>
                                <div>
                                    <span class="text-sm text-gray-500">Adresse</span>
                                    <p class="font-medium">
                                        <?php echo !empty($fournisseur['adresse']) ? htmlspecialchars($fournisseur['adresse']) : '<span class="text-gray-400">Non renseignée</span>'; ?>
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="col-span-2">
                        <h2 class="text-lg font-semibold mb-4">Notes</h2>
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 min-h-[150px]">
                            <?php if (!empty($fournisseur['notes'])): ?>
                                <p class="whitespace-pre-line"><?php echo htmlspecialchars($fournisseur['notes']); ?></p>
                            <?php else: ?>
                                <p class="text-gray-400 italic">Aucune note enregistrée pour ce fournisseur.</p>
                            <?php endif; ?>
                        </div>

                        <h2 class="text-lg font-semibold mt-6 mb-4">Statistiques</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="stat-card bg-white shadow-sm p-4 border-l-4 border-blue-500 rounded">
                                <div class="flex items-center">
                                    <div class="rounded-full bg-blue-100 p-2">
                                        <span class="material-icons text-blue-500">shopping_cart</span>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-gray-500">Total commandes</h3>
                                        <p class="text-xl font-bold"><?php echo $statsData['total_commandes']; ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="stat-card bg-white shadow-sm p-4 border-l-4 border-green-500 rounded">
                                <div class="flex items-center">
                                    <div class="rounded-full bg-green-100 p-2">
                                        <span class="material-icons text-green-500">payments</span>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-gray-500">Montant total</h3>
                                        <p class="text-xl font-bold">
                                            <?php echo number_format($statsData['montant_total'], 0, ',', ' '); ?> FCFA
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="stat-card bg-white shadow-sm p-4 border-l-4 border-purple-500 rounded">
                                <div class="flex items-center">
                                    <div class="rounded-full bg-purple-100 p-2">
                                        <span class="material-icons text-purple-500">event</span>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-gray-500">Dernière commande</h3>
                                        <p class="text-xl font-bold">
                                            <?php echo !empty($statsData['derniere_commande']) ? date('d/m/Y', strtotime($statsData['derniere_commande'])) : 'N/A'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="stat-card bg-white shadow-sm p-4 border-l-4 border-pink-500 rounded">
                                <div class="flex items-center">
                                    <div class="rounded-full bg-pink-100 p-2">
                                        <span class="material-icons text-pink-500">assignment_return</span>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-gray-500">Retours produits</h3>
                                        <p class="text-xl font-bold">
                                            <?php echo isset($totalReturns) ? $totalReturns : '0'; ?>
                                            <span
                                                class="text-sm font-normal text-gray-500">(<?php echo isset($totalReturnedQuantity) ? $totalReturnedQuantity : '0'; ?>
                                                unités)</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs for different sections -->
            <div class="bg-white shadow-sm rounded-lg sticky-top mb-4">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8 px-6">
                        <a href="#" id="tab-commandes"
                            class="tab-active whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            <span class="material-icons align-middle mr-1">receipt_long</span>
                            Historique des commandes
                        </a>
                        <a href="#" id="tab-produits"
                            class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            <span class="material-icons align-middle mr-1">inventory_2</span>
                            Produits fréquemment commandés
                        </a>
                        <a href="#" id="tab-returns"
                            class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            <span class="material-icons align-middle mr-1">assignment_return</span>
                            Retours produits
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Commandes section -->
            <div id="content-commandes" class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-semibold">Historique des commandes</h2>
                    <div class="flex space-x-2">
                        <button id="export-excel"
                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                            <span class="material-icons align-middle text-sm mr-1">file_download</span>
                            Excel
                        </button>
                        <button id="export-pdf"
                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                            <span class="material-icons align-middle text-sm mr-1">picture_as_pdf</span>
                            PDF
                        </button>
                    </div>
                </div>

                <?php if (empty($commandes)): ?>
                    <div class="bg-gray-50 p-8 text-center rounded-lg border border-gray-200">
                        <span class="material-icons text-gray-400 text-5xl mb-2">receipt_long</span>
                        <p class="text-gray-500">Aucune commande trouvée pour ce fournisseur.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table id="commandes-table" class="min-w-full">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Projet</th>
                                    <th>Quantité</th>
                                    <th>Prix unitaire</th>
                                    <th>Total</th>
                                    <th>Date commande</th>
                                    <th>Date réception</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commandes as $commande): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($commande['designation']); ?></td>
                                        <td>
                                            <?php if (!empty($commande['code_projet']) && !empty($commande['nom_client'])): ?>
                                                <span
                                                    class="font-medium"><?php echo htmlspecialchars($commande['code_projet']); ?></span>
                                                <br>
                                                <span
                                                    class="text-xs text-gray-500"><?php echo htmlspecialchars($commande['nom_client']); ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $commande['quantity'] . ' ' . $commande['unit']; ?></td>
                                        <td class="text-right">
                                            <?php echo number_format($commande['prix_unitaire'], 0, ',', ' '); ?> FCFA
                                        </td>
                                        <td class="text-right">
                                            <?php echo number_format($commande['quantity'] * $commande['prix_unitaire'], 0, ',', ' '); ?>
                                            FCFA
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($commande['date_achat'])); ?></td>
                                        <td>
                                            <?php echo !empty($commande['date_reception']) ? date('d/m/Y', strtotime($commande['date_reception'])) : '<span class="text-gray-400">-</span>'; ?>
                                        </td>
                                        <td>
                                            <?php
                                            switch ($commande['status']) {
                                                case 'commandé':
                                                    echo '<span class="badge badge-blue">Commandé</span>';
                                                    break;
                                                case 'reçu':
                                                    echo '<span class="badge badge-green">Reçu</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge badge-orange">En attente</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Produits section -->
            <div id="content-produits" class="bg-white shadow-sm rounded-lg p-6 mb-6 hidden">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-semibold">Produits fréquemment commandés</h2>
                    <div class="flex space-x-2">
                        <button id="export-produits-excel"
                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                            <span class="material-icons align-middle text-sm mr-1">file_download</span>
                            Excel
                        </button>
                        <button id="export-produits-pdf"
                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                            <span class="material-icons align-middle text-sm mr-1">picture_as_pdf</span>
                            PDF
                        </button>
                    </div>
                </div>

                <?php if (empty($produitsFrequents)): ?>
                    <div class="bg-gray-50 p-8 text-center rounded-lg border border-gray-200">
                        <span class="material-icons text-gray-400 text-5xl mb-2">inventory_2</span>
                        <p class="text-gray-500">Aucun produit fréquemment commandé trouvé.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table id="produits-table" class="min-w-full">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Nombre de commandes</th>
                                    <th>Quantité totale</th>
                                    <th>Prix moyen</th>
                                    <th>Montant total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produitsFrequents as $produit): ?>
                                    <tr>
                                        <td class="font-medium"><?php echo htmlspecialchars($produit['designation']); ?></td>
                                        <td><?php echo $produit['nombre_commandes']; ?></td>
                                        <td><?php echo number_format($produit['quantite_totale'], 0, ',', ' '); ?></td>
                                        <td class="text-right"><?php echo number_format($produit['prix_moyen'], 0, ',', ' '); ?>
                                            FCFA</td>
                                        <td class="text-right">
                                            <?php echo number_format($produit['prix_moyen'] * $produit['quantite_totale'], 0, ',', ' '); ?>
                                            FCFA
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="content-returns" class="bg-white shadow-sm rounded-lg p-6 mb-6 hidden">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-semibold">Historique des retours</h2>
                    <div class="flex space-x-2">
                        <button id="export-returns-excel"
                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                            <span class="material-icons align-middle text-sm mr-1">file_download</span>
                            Excel
                        </button>
                        <button id="export-returns-pdf"
                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                            <span class="material-icons align-middle text-sm mr-1">picture_as_pdf</span>
                            PDF
                        </button>
                    </div>
                </div>

                <?php if (empty($returnsData)): ?>
                    <div class="bg-gray-50 p-8 text-center rounded-lg border border-gray-200">
                        <span class="material-icons text-gray-400 text-5xl mb-2">assignment_return</span>
                        <p class="text-gray-500">Aucun retour produit trouvé pour ce fournisseur.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table id="returns-table" class="min-w-full">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Motif du retour</th>
                                    <th>Commentaire</th>
                                    <th>Date du retour</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($returnsData as $return): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($return['product_name']); ?></td>
                                        <td><?php echo $return['quantity']; ?></td>
                                        <td>
                                            <?php
                                            if (isset($return['reason'])) {
                                                echo htmlspecialchars($return['reason']);
                                            } else {
                                                echo '<span class="text-gray-400">Non spécifié</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (isset($return['comment']) && !empty($return['comment'])) {
                                                echo htmlspecialchars($return['comment']);
                                            } else {
                                                echo '<span class="text-gray-400">-</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($return['created_at'])); ?></td>
                                        <td>
                                            <?php
                                            $badgeClass = 'badge-blue';
                                            $statusText = 'En attente';

                                            if (isset($return['status'])) {
                                                switch ($return['status']) {
                                                    case 'completed':
                                                        $badgeClass = 'badge-green';
                                                        $statusText = 'Complété';
                                                        break;
                                                    case 'cancelled':
                                                        $badgeClass = 'badge-red';
                                                        $statusText = 'Annulé';
                                                        break;
                                                    default:
                                                        $badgeClass = 'badge-blue';
                                                        $statusText = 'En attente';
                                                }
                                            }

                                            echo '<span class="badge ' . $badgeClass . '">' . $statusText . '</span>';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <?php include_once '../components/footer.html'; ?>
    </div>

    <!-- Scripts jQuery et DataTables -->
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
            // Initialiser DataTable pour les commandes (code existant)
            var commandesTable = $('#commandes-table').DataTable({
                // configuration existante
            });

            // Initialiser DataTable pour les produits fréquents
            var produitsTable = $('#produits-table').DataTable({
                responsive: true,
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                },
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: 'Excel',
                        title: 'Produits_<?php echo htmlspecialchars($fournisseur['nom']); ?>',
                        className: 'hidden'
                    },
                    {
                        extend: 'pdf',
                        text: 'PDF',
                        title: 'Produits_<?php echo htmlspecialchars($fournisseur['nom']); ?>',
                        className: 'hidden'
                    }
                ],
                order: [[1, 'desc']], // Tri par nombre de commandes décroissant
                pageLength: 10
            });

            // Initialiser DataTable pour les retours
            var returnsTable = $('#returns-table').DataTable({
                responsive: true,
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                },
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: 'Excel',
                        title: 'Retours_<?php echo htmlspecialchars($fournisseur['nom']); ?>',
                        className: 'hidden'
                    },
                    {
                        extend: 'pdf',
                        text: 'PDF',
                        title: 'Retours_<?php echo htmlspecialchars($fournisseur['nom']); ?>',
                        className: 'hidden'
                    }
                ],
                order: [[4, 'desc']], // Tri par date de retour décroissante
                pageLength: 10
            });

            // Exportation vers Excel (code existant)
            $('#export-excel').on('click', function () {
                commandesTable.button('.buttons-excel').trigger();
            });

            // Exportation vers PDF (code existant)
            $('#export-pdf').on('click', function () {
                commandesTable.button('.buttons-pdf').trigger();
            });

            // Exportation des produits vers Excel
            $('#export-produits-excel').on('click', function () {
                produitsTable.button('.buttons-excel').trigger();
            });

            // Exportation des produits vers PDF
            $('#export-produits-pdf').on('click', function () {
                produitsTable.button('.buttons-pdf').trigger();
            });

                // Exportation des retours vers Excel
    $('#export-returns-excel').on('click', function () {
        returnsTable.button('.buttons-excel').trigger();
    });

    // Exportation des retours vers PDF
    $('#export-returns-pdf').on('click', function () {
        returnsTable.button('.buttons-pdf').trigger();
    });

            // Navigation par onglets
            $('#tab-commandes').on('click', function (e) {
                e.preventDefault();
                toggleActiveTab($(this), $('#content-commandes'));
            });

            $('#tab-produits').on('click', function (e) {
                e.preventDefault();
                toggleActiveTab($(this), $('#content-produits'));
            });

            $('#tab-returns').on('click', function (e) {
        e.preventDefault();
        toggleActiveTab($(this), $('#content-returns'));
    });

    function toggleActiveTab(tab, content) {
        // Désactiver tous les onglets et masquer tous les contenus
        $('#tab-commandes, #tab-produits, #tab-returns').removeClass('tab-active text-blue-600 border-blue-600').addClass('border-transparent text-gray-500');
        $('#content-commandes, #content-produits, #content-returns').addClass('hidden');

                // Activer l'onglet cliqué et afficher son contenu
                tab.addClass('tab-active text-blue-600 border-blue-600').removeClass('border-transparent text-gray-500');
                content.removeClass('hidden');

                // Réajuster les colonnes du tableau selon l'onglet actif
                if (content.attr('id') === 'content-commandes') {
                    commandesTable.columns.adjust().responsive.recalc();
                } else if (content.attr('id') === 'content-produits') {
                    produitsTable.columns.adjust().responsive.recalc();
                } else if (content.attr('id') === 'content-returns') {
                    returnsTable.columns.adjust().responsive.recalc();
                }
            }

            // Rediriger vers la page de modification du fournisseur (code existant)
            $('#edit-supplier-btn').on('click', function () {
                const id = $(this).data('id');
                window.location.href = 'fournisseurs.php?edit=' + id;
            });
        });
    </script>

    <?php
    // Fonction pour déterminer la couleur du badge en fonction de la catégorie
    function getBadgeColor($category)
    {
        try {
            global $pdo;
            // Vérifier d'abord si la catégorie existe dans la table categories_fournisseurs
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