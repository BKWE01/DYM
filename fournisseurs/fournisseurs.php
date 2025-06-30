<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Connexion à la base de données
include_once '../database/connection.php';

// Inclure le gestionnaire de couleurs
require_once 'config/colors.php';

$message = '';

// Traitement du formulaire d'ajout de fournisseur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $nom = trim($_POST['nom']);
        $email = trim($_POST['email']);
        $telephone = trim($_POST['telephone']);
        $adresse = trim($_POST['adresse']);
        $notes = trim($_POST['notes']);
        $categories = isset($_POST['categories']) ? $_POST['categories'] : [];

        try {
            // Vérifier si le fournisseur existe déjà
            $checkQuery = "SELECT * FROM fournisseurs WHERE nom = :nom";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':nom', $nom);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                $message = "Un fournisseur avec ce nom existe déjà.";
            } else {
                // Commencer une transaction
                $pdo->beginTransaction();

                // Insérer le nouveau fournisseur
                $insertQuery = "INSERT INTO fournisseurs (nom, email, telephone, adresse, notes, created_by) 
                              VALUES (:nom, :email, :telephone, :adresse, :notes, :created_by)";
                $insertStmt = $pdo->prepare($insertQuery);
                $insertStmt->bindParam(':nom', $nom);
                $insertStmt->bindParam(':email', $email);
                $insertStmt->bindParam(':telephone', $telephone);
                $insertStmt->bindParam(':adresse', $adresse);
                $insertStmt->bindParam(':notes', $notes);
                $insertStmt->bindParam(':created_by', $user_id);
                $insertStmt->execute();

                $fournisseur_id = $pdo->lastInsertId();

                // Insérer les catégories
                if (!empty($categories)) {
                    $insertCatQuery = "INSERT INTO fournisseur_categories (fournisseur_id, categorie) VALUES (:fournisseur_id, :categorie)";
                    $insertCatStmt = $pdo->prepare($insertCatQuery);

                    foreach ($categories as $categorie) {
                        if (!empty($categorie)) {
                            $insertCatStmt->bindParam(':fournisseur_id', $fournisseur_id);
                            $insertCatStmt->bindParam(':categorie', $categorie);
                            $insertCatStmt->execute();
                        }
                    }
                }

                // Valider la transaction
                $pdo->commit();

                $message = "Fournisseur ajouté avec succès!";

                // Après le traitement...
                header("Location: fournisseurs.php?t=" . time());
                exit();
            }
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Erreur lors de l'ajout du fournisseur: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'edit') {
        $id = $_POST['id'];
        $nom = trim($_POST['nom']);
        $email = trim($_POST['email']);
        $telephone = trim($_POST['telephone']);
        $adresse = trim($_POST['adresse']);
        $notes = trim($_POST['notes']);
        $categories = isset($_POST['categories']) ? $_POST['categories'] : [];

        try {
            // Vérifier si un autre fournisseur a déjà ce nom
            $checkQuery = "SELECT * FROM fournisseurs WHERE nom = :nom AND id != :id";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':nom', $nom);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                $message = "Un autre fournisseur avec ce nom existe déjà.";
            } else {
                // Commencer une transaction
                $pdo->beginTransaction();

                // Mettre à jour le fournisseur
                $updateQuery = "UPDATE fournisseurs SET 
                         nom = :nom, 
                         email = :email, 
                         telephone = :telephone, 
                         adresse = :adresse, 
                         notes = :notes,
                         updated_at = CURRENT_TIMESTAMP
                         WHERE id = :id";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->bindParam(':nom', $nom);
                $updateStmt->bindParam(':email', $email);
                $updateStmt->bindParam(':telephone', $telephone);
                $updateStmt->bindParam(':adresse', $adresse);
                $updateStmt->bindParam(':notes', $notes);
                $updateStmt->bindParam(':id', $id);
                $updateStmt->execute();

                // Supprimer toutes les catégories existantes pour ce fournisseur
                $deleteCatQuery = "DELETE FROM fournisseur_categories WHERE fournisseur_id = :id";
                $deleteCatStmt = $pdo->prepare($deleteCatQuery);
                $deleteCatStmt->bindParam(':id', $id);
                $deleteCatStmt->execute();

                // Traiter les nouvelles catégories
                if (!empty($categories)) {
                    foreach ($categories as $categorie) {
                        if (!empty($categorie)) {
                            // Vérifier si la catégorie existe dans categories_fournisseurs
                            $checkCatQuery = "SELECT id, couleur FROM categories_fournisseurs WHERE nom = :nom";
                            $checkCatStmt = $pdo->prepare($checkCatQuery);
                            $checkCatStmt->bindParam(':nom', $categorie);
                            $checkCatStmt->execute();
                            $existingCat = $checkCatStmt->fetch(PDO::FETCH_ASSOC);

                            if (!$existingCat) {
                                // Créer la catégorie avec une couleur intelligente
                                $smartColor = ColorManager::getSmartFallbackColor($categorie);
                                $createCatQuery = "INSERT INTO categories_fournisseurs (nom, couleur, description, active, created_by) 
                                             VALUES (:nom, :couleur, :description, 1, :created_by)";
                                $createCatStmt = $pdo->prepare($createCatQuery);
                                $createCatStmt->bindParam(':nom', $categorie);
                                $createCatStmt->bindParam(':couleur', $smartColor);
                                $createCatStmt->bindParam(':description', "Catégorie créée automatiquement");
                                $createCatStmt->bindParam(':created_by', $user_id);
                                $createCatStmt->execute();
                            } elseif (empty($existingCat['couleur']) || !ColorManager::colorExists($existingCat['couleur'])) {
                                // Mettre à jour la couleur si elle est manquante ou invalide
                                $smartColor = ColorManager::getSmartFallbackColor($categorie);
                                $updateCatQuery = "UPDATE categories_fournisseurs SET couleur = :couleur WHERE id = :id";
                                $updateCatStmt = $pdo->prepare($updateCatQuery);
                                $updateCatStmt->bindParam(':couleur', $smartColor);
                                $updateCatStmt->bindParam(':id', $existingCat['id']);
                                $updateCatStmt->execute();
                            }

                            // Insérer la relation fournisseur-catégorie
                            $insertCatQuery = "INSERT INTO fournisseur_categories (fournisseur_id, categorie) VALUES (:fournisseur_id, :categorie)";
                            $insertCatStmt = $pdo->prepare($insertCatQuery);
                            $insertCatStmt->bindParam(':fournisseur_id', $id);
                            $insertCatStmt->bindParam(':categorie', $categorie);
                            $insertCatStmt->execute();
                        }
                    }
                }

                // Valider la transaction
                $pdo->commit();

                $message = "Fournisseur mis à jour avec succès!";

                // Redirection pour éviter la resoumission
                header("Location: fournisseurs.php?success=edit&t=" . time());
                exit();
            }
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Erreur lors de la mise à jour du fournisseur: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = $_POST['id'];

        try {
            // Vérifier si le fournisseur est lié à des commandes
            $checkQuery = "SELECT COUNT(*) as count FROM achats_materiaux WHERE fournisseur = 
                          (SELECT nom FROM fournisseurs WHERE id = :id)";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                $message = "Ce fournisseur ne peut pas être supprimé car il est associé à des commandes.";
            } else {
                // Commencer une transaction
                $pdo->beginTransaction();

                // Supprimer les catégories du fournisseur
                $deleteCatQuery = "DELETE FROM fournisseur_categories WHERE fournisseur_id = :id";
                $deleteCatStmt = $pdo->prepare($deleteCatQuery);
                $deleteCatStmt->bindParam(':id', $id);
                $deleteCatStmt->execute();

                // Supprimer le fournisseur
                $deleteQuery = "DELETE FROM fournisseurs WHERE id = :id";
                $deleteStmt = $pdo->prepare($deleteQuery);
                $deleteStmt->bindParam(':id', $id);
                $deleteStmt->execute();

                // Valider la transaction
                $pdo->commit();

                $message = "Fournisseur supprimé avec succès!";
            }
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Erreur lors de la suppression du fournisseur: " . $e->getMessage();
        }
    }
}

// Récupérer tous les fournisseurs
$fournisseurs = [];
try {
    $query = "SELECT * FROM fournisseurs ORDER BY nom ASC";
    $stmt = $pdo->query($query);
    $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les catégories pour chaque fournisseur
    foreach ($fournisseurs as &$fournisseur) {
        $categoryQuery = "SELECT categorie FROM fournisseur_categories WHERE fournisseur_id = :id";
        $categoryStmt = $pdo->prepare($categoryQuery);
        $categoryStmt->bindParam(':id', $fournisseur['id']);
        $categoryStmt->execute();
        $fournisseur['categories'] = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $message = "Erreur lors de la récupération des fournisseurs: " . $e->getMessage();
}

// Récupérer les statistiques par fournisseur
$fournisseursStats = [];
try {
    $statsQuery = "SELECT 
                    f.id,
                    f.nom,
                    COUNT(DISTINCT am.id) AS nombre_commandes, 
                    SUM(am.quantity * am.prix_unitaire) AS montant_total,
                    AVG(am.prix_unitaire) AS prix_moyen,
                    MAX(am.date_achat) AS dernier_achat,
                    GROUP_CONCAT(DISTINCT fc.categorie SEPARATOR ', ') as categories
                FROM 
                    fournisseurs f
                LEFT JOIN 
                    achats_materiaux am ON f.nom = am.fournisseur
                LEFT JOIN
                    fournisseur_categories fc ON f.id = fc.fournisseur_id
                GROUP BY 
                    f.id, f.nom
                ORDER BY 
                    nombre_commandes DESC";
    $statsStmt = $pdo->query($statsQuery);
    $fournisseursStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
    // Ajouter les statistiques de retours pour chaque fournisseur
    foreach ($fournisseursStats as &$stat) {
        // Vérifier si la table supplier_returns existe
        $tableExists = false;
        $checkTableStmt = $pdo->prepare("SHOW TABLES LIKE 'supplier_returns'");
        $checkTableStmt->execute();
        $tableExists = $checkTableStmt->rowCount() > 0;

        $returnCount = 0;

        if ($tableExists) {
            // Si la table existe, compter les retours par supplier_id
            $returnQuery = "SELECT COUNT(*) as return_count FROM supplier_returns WHERE supplier_id = :supplier_id";
            $returnStmt = $pdo->prepare($returnQuery);
            $returnStmt->bindParam(':supplier_id', $stat['id']);
            $returnStmt->execute();
            $returnData = $returnStmt->fetch(PDO::FETCH_ASSOC);
            $returnCount = $returnData['return_count'];

            if ($returnCount == 0) {
                // Essayer par nom de fournisseur si aucun retour n'est trouvé par ID
                $returnQuery = "SELECT COUNT(*) as return_count FROM supplier_returns WHERE supplier_name = :supplier_name";
                $returnStmt = $pdo->prepare($returnQuery);
                $returnStmt->bindParam(':supplier_name', $stat['nom']);
                $returnStmt->execute();
                $returnData = $returnStmt->fetch(PDO::FETCH_ASSOC);
                $returnCount = $returnData['return_count'];
            }
        }

        // Si aucun retour n'est trouvé dans la table supplier_returns ou si la table n'existe pas,
        // rechercher dans la table stock_movement
        if ($returnCount == 0) {
            $returnQuery = "SELECT COUNT(*) as return_count FROM stock_movement 
                            WHERE movement_type = 'output' 
                            AND destination = :destination";
            $returnStmt = $pdo->prepare($returnQuery);
            $destination = "Retour fournisseur: " . $stat['nom'];
            $returnStmt->bindParam(':destination', $destination);
            $returnStmt->execute();
            $returnData = $returnStmt->fetch(PDO::FETCH_ASSOC);
            $returnCount = $returnData['return_count'];
        }

        // Ajouter le nombre de retours aux statistiques
        $stat['nombre_retours'] = $returnCount;
    }
} catch (PDOException $e) {
    // Si l'erreur est liée à la structure de la table, on continue sans statistiques
    $message .= " Erreur lors de la récupération des statistiques.";
}

// Récupérer les catégories de produits pour le filtre
$categories = [];
try {
    $catQuery = "SELECT DISTINCT categorie FROM fournisseur_categories ORDER BY categorie ASC";
    $catStmt = $pdo->query($catQuery);
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Erreur silencieuse
}

// Récupérer les derniers achats
$dernierAchats = [];
try {
    $achatsQuery = "SELECT 
                    am.id,
                    am.designation,
                    am.quantity,
                    am.unit,
                    am.prix_unitaire,
                    am.fournisseur,
                    am.date_achat,
                    am.status,
                    ip.code_projet,
                    ip.nom_client
                FROM 
                    achats_materiaux am
                LEFT JOIN 
                    identification_projet ip ON am.expression_id = ip.idExpression
                ORDER BY 
                    am.date_achat DESC
                LIMIT 10";
    $achatsStmt = $pdo->query($achatsQuery);
    $dernierAchats = $achatsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Erreur silencieuse pour les derniers achats
}

// Récupérer les catégories pour les sélecteurs
$categoriesForSelect = [];
try {
    $catSelectQuery = "SELECT nom, couleur, active FROM categories_fournisseurs WHERE active = 1 ORDER BY nom ASC";
    $catSelectStmt = $pdo->query($catSelectQuery);
    $categoriesForSelect = $catSelectStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si la table n'existe pas encore, utiliser les catégories par défaut avec couleurs intelligentes
    $defaultCategories = [
        'Matériaux BTP',
        'Équipements',
        'Services',
        'Fournitures',
        'Électronique',
        'Transport',
        'Sécurité',
        'Maintenance'
    ];

    foreach ($defaultCategories as $cat) {
        $categoriesForSelect[] = [
            'nom' => $cat,
            'couleur' => ColorManager::getSmartFallbackColor($cat),
            'active' => 1
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Fournisseurs</title>
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

        .badge-pink {
            background-color: #ec4899;
            color: #fff;
        }


        .badge-cyan {
            background-color: #06b6d4;
            color: white;
        }

        .bg-brown,
        .badge-brown {
            background-color: #8B4513;
            color: white;
        }

        /* Style pour les modales */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 60%;
            max-width: 700px;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        /* Style pour les cartes de statistiques */
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        /* Autres styles personnalisés */
        .tab-active {
            color: #3b82f6;
            border-bottom: 2px solid #3b82f6;
        }

        /* Nouvelles classes de couleurs */
        .badge-slate {
            background-color: #475569;
        }

        .badge-emerald {
            background-color: #059669;
        }

        /* Amélioration de l'aperçu des badges */
        .badge-preview {
            width: 120px;
            height: 32px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.75rem;
            margin-top: 8px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .badge-preview:hover {
            transform: scale(1.05);
            border-color: #e5e7eb;
        }

        /* Amélioration du select des couleurs */
        .color-option {
            padding: 8px 12px;
            border-radius: 4px;
            margin: 2px 0;
        }

        .color-select {
            position: relative;
        }

        .color-select::after {
            content: '';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: var(--selected-color, #3b82f6);
        }

        /* Animation pour le message flash */
        @keyframes fadeOut {
            from {
                opacity: 1;
            }

            to {
                opacity: 0;
            }
        }

        .flash-message {
            animation: fadeOut 5s forwards;
            animation-delay: 3s;
        }

        /* Style pour le tableau des derniers achats */
        .recent-orders-table th {
            font-size: 0.85rem;
            text-transform: uppercase;
            padding: 12px 15px;
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }

        .recent-orders-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #e5e7eb;
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

        /* Style pour le select multiple */
        select[multiple] {
            height: auto;
            min-height: 120px;
            padding: 8px;
        }

        select[multiple] option {
            padding: 5px;
            margin-bottom: 3px;
            border-radius: 4px;
        }

        select[multiple] option:hover {
            background-color: #e5e7eb;
        }

        select[multiple] option:checked {
            background-color: #3b82f6;
            color: white;
        }

        /* Style pour les badges de catégories */
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
        <?php include_once '../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex flex-wrap justify-between items-center">
                <h1 class="text-xl font-semibold flex items-center space-x-2">
                    <span class="material-icons">storefront</span>
                    <span>Gestion des Fournisseurs</span>
                </h1>

                <div class="flex flex-wrap items-center my-2 space-x-4">
                    <!-- Bouton Rapport statistics PDF Global pdf -->
                    <button id="export-all-suppliers-report"
                        class="flex items-center bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-md transition-colors duration-300">
                        <span class="material-icons mr-2">assignment</span>
                        Rapport PDF Global
                    </button>
                    <a href="./categories/index.php"
                        class="flex items-center bg-purple-500 hover:bg-purple-600 text-white py-2 px-4 rounded-md transition-colors duration-300">
                        <span class="material-icons mr-2">category</span>
                        Gérer les catégories
                    </a>
                    <button id="add-supplier-btn"
                        class="flex items-center bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition-colors duration-300">
                        <span class="material-icons mr-2">add</span>
                        Ajouter un fournisseur
                    </button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div id="flash-message"
                    class="flash-message bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                    <span class="block sm:inline"><?php echo $message; ?></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3"
                        onclick="document.getElementById('flash-message').style.display='none';">
                        <span class="material-icons">close</span>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Onglets -->
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <ul class="flex flex-wrap -mb-px">
                        <li class="mr-2">
                            <a href="#" id="tab-list"
                                class="inline-flex items-center py-2 px-4 text-blue-600 border-b-2 border-blue-500 active font-medium">
                                <span class="material-icons mr-2 text-sm">view_list</span>
                                Liste des fournisseurs
                            </a>
                        </li>
                        <li class="mr-2">
                            <a href="#" id="tab-stats"
                                class="inline-flex items-center py-2 px-4 text-gray-500 hover:text-gray-700 font-medium">
                                <span class="material-icons mr-2 text-sm">bar_chart</span>
                                Statistiques
                            </a>
                        </li>
                        <li class="mr-2">
                            <a href="#" id="tab-orders"
                                class="inline-flex items-center py-2 px-4 text-gray-500 hover:text-gray-700 font-medium">
                                <span class="material-icons mr-2 text-sm">receipt_long</span>
                                Derniers achats
                            </a>
                        </li>
                    </ul>
                </div>
            </div>


            <!-- Section Liste des fournisseurs -->
            <div id="section-list" class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <div class="flex justify-between flex-wrap items-center mb-6">
                    <h2 class="text-lg font-semibold">Liste des fournisseurs</h2>
                    <div class="flex flex-wrap space-x-4">
                        <div class="relative">
                            <input type="text" id="supplier-search" placeholder="Rechercher un fournisseur"
                                class="pl-10 py-2 pr-4 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <span class="absolute left-3 top-2 text-gray-400 material-icons">search</span>
                        </div>
                        <div class="relative">
                            <select id="category-filter"
                                class="pl-10 py-2 pr-4 border border-gray-300 rounded-md focus:outline-none">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($categories as $categorie): ?>
                                    <option value="<?php echo htmlspecialchars($categorie); ?>">
                                        <?php echo htmlspecialchars($categorie); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="absolute left-3 top-2 text-gray-400 material-icons">filter_list</span>
                        </div>
                    </div>
                </div>

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
                                <tr data-fournisseur-id="<?php echo $fournisseur['id']; ?>">
                                    <td><?php echo htmlspecialchars($fournisseur['nom']); ?></td>
                                    <td>
                                        <?php if (!empty($fournisseur['categories'])): ?>
                                            <div class="category-badges">
                                                <?php foreach ($fournisseur['categories'] as $categorie): ?>
                                                    <span class="badge <?php echo getBadgeColor($categorie); ?>">
                                                        <?php echo htmlspecialchars($categorie); ?>
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
                                        <button class="edit-supplier text-blue-500 hover:text-blue-700 mx-1"
                                            data-id="<?php echo $fournisseur['id']; ?>">
                                            <span class="material-icons">edit</span>
                                        </button>
                                        <button class="delete-supplier text-red-500 hover:text-red-700 mx-1"
                                            data-id="<?php echo $fournisseur['id']; ?>">
                                            <span class="material-icons">delete</span>
                                        </button>
                                        <a href="supplier_details.php?id=<?php echo $fournisseur['id']; ?>"
                                            class="text-green-500 hover:text-green-700 mx-1" title="Voir les détails">
                                            <span class="material-icons">visibility</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section Statistiques -->
            <div id="section-stats" class="bg-white shadow-sm rounded-lg p-6 mb-6 hidden">
                <h2 class="text-lg font-semibold mb-6">Statistiques des fournisseurs</h2>

                <!-- Carte récapitulative -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-500">
                        <div class="flex items-center">
                            <div class="rounded-full bg-blue-100 p-3">
                                <span class="material-icons text-blue-500">business</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-700">Total fournisseurs</h3>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($fournisseurs); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-500">
                        <div class="flex items-center">
                            <div class="rounded-full bg-green-100 p-3">
                                <span class="material-icons text-green-500">shopping_cart</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-700">Commandes</h3>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php
                                    $totalCommandes = 0;
                                    foreach ($fournisseursStats as $stat) {
                                        $totalCommandes += $stat['nombre_commandes'];
                                    }
                                    echo $totalCommandes;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-purple-500">
                        <div class="flex items-center">
                            <div class="rounded-full bg-purple-100 p-3">
                                <span class="material-icons text-purple-500">payments</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-700">Montant total</h3>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php
                                    $montantTotal = 0;
                                    foreach ($fournisseursStats as $stat) {
                                        $montantTotal += $stat['montant_total'];
                                    }
                                    echo number_format($montantTotal, 0, ',', ' ') . ' FCFA';
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableau des statistiques -->
                <div class="overflow-x-auto">
                    <table id="stats-table" class="min-w-full">
                        <thead>
                            <tr>
                                <th>Fournisseur</th>
                                <th>Catégories</th>
                                <th>Nombre de commandes</th>
                                <th>Montant total</th>
                                <th>Prix moyen</th>
                                <th>Retours</th>
                                <th>Dernier achat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fournisseursStats as $stat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stat['nom']); ?></td>
                                    <td>
                                        <?php if (!empty($stat['categories'])): ?>
                                            <div class="category-badges">
                                                <?php
                                                $categoriesArray = explode(', ', $stat['categories']);
                                                foreach ($categoriesArray as $categorie): ?>
                                                    <span class="badge <?php echo getBadgeColor($categorie); ?>">
                                                        <?php echo htmlspecialchars($categorie); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo $stat['nombre_commandes']; ?></td>
                                    <td class="text-right">
                                        <?php echo !empty($stat['montant_total']) ? number_format($stat['montant_total'], 0, ',', ' ') . ' FCFA' : '0 FCFA'; ?>
                                    </td>
                                    <td class="text-right">
                                        <?php echo !empty($stat['prix_moyen']) ? number_format($stat['prix_moyen'], 0, ',', ' ') . ' FCFA' : '0 FCFA'; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($stat['nombre_retours'] > 0): ?>
                                            <span class="badge badge-pink"><?php echo $stat['nombre_retours']; ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo !empty($stat['dernier_achat']) ? date('d/m/Y', strtotime($stat['dernier_achat'])) : '-'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section Derniers achats -->
            <div id="section-orders" class="bg-white shadow-sm rounded-lg p-6 mb-6 hidden">
                <h2 class="text-lg font-semibold mb-6">Derniers achats effectués</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full recent-orders-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Fournisseur</th>
                                <th>Projet</th>
                                <th>Quantité</th>
                                <th>Prix unitaire</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dernierAchats as $achat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($achat['designation']); ?></td>
                                    <td><?php echo htmlspecialchars($achat['fournisseur']); ?></td>
                                    <td>
                                        <?php if (!empty($achat['code_projet']) && !empty($achat['nom_client'])): ?>
                                            <span
                                                class="font-medium"><?php echo htmlspecialchars($achat['code_projet']); ?></span>
                                            <span
                                                class="text-xs text-gray-500"><?php echo htmlspecialchars($achat['nom_client']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $achat['quantity'] . ' ' . $achat['unit']; ?></td>
                                    <td class="text-right">
                                        <?php echo number_format($achat['prix_unitaire'], 0, ',', ' ') . ' FCFA'; ?>
                                    </td>
                                    <td class="text-right">
                                        <?php echo number_format($achat['quantity'] * $achat['prix_unitaire'], 0, ',', ' ') . ' FCFA'; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($achat['date_achat'])); ?></td>
                                    <td>
                                        <?php
                                        switch ($achat['status']) {
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
                            <?php if (empty($dernierAchats)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-gray-500">Aucun achat récent trouvé</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <?php include_once '../components/footer.html'; ?>
    </div>

    <!-- Modal pour ajouter un fournisseur -->
    <div id="add-supplier-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Ajouter un nouveau fournisseur</h2>
            <form id="add-supplier-form" method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">Nom du fournisseur
                            *</label>
                        <input type="text" id="nom" name="nom"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            required>
                    </div>
                    <div>
                        <label for="categories" class="block text-sm font-medium text-gray-700 mb-1">Catégories</label>
                        <select id="categories" name="categories[]"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            multiple>
                            <?php foreach ($categoriesForSelect as $cat): ?>
                                <?php if ($cat['active'] == 1): ?>
                                    <option value="<?php echo htmlspecialchars($cat['nom']); ?>"
                                        class="<?php echo htmlspecialchars($cat['couleur']); ?>">
                                        <?php echo htmlspecialchars($cat['nom']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Maintenez la touche Ctrl (ou Cmd sur Mac) pour
                            sélectionner plusieurs catégories</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="email" name="email"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="telephone" class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="mb-4">
                    <label for="adresse" class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                    <input type="text" id="adresse" name="adresse"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="button"
                        class="cancel-modal bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md mr-2">Annuler</button>
                    <button type="submit"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour modifier un fournisseur -->
    <div id="edit-supplier-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Modifier le fournisseur</h2>
            <form id="edit-supplier-form" method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit-id" name="id" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="edit-nom" class="block text-sm font-medium text-gray-700 mb-1">Nom du fournisseur
                            *</label>
                        <input type="text" id="edit-nom" name="nom"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            required>
                    </div>
                    <div>
                        <label for="edit-categories"
                            class="block text-sm font-medium text-gray-700 mb-1">Catégories</label>
                        <select id="edit-categories" name="categories[]"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            multiple>
                            <?php foreach ($categoriesForSelect as $cat): ?>
                                <?php if ($cat['active'] == 1): ?>
                                    <option value="<?php echo htmlspecialchars($cat['nom']); ?>"
                                        class="<?php echo htmlspecialchars($cat['couleur']); ?>">
                                        <?php echo htmlspecialchars($cat['nom']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Maintenez la touche Ctrl (ou Cmd sur Mac) pour
                            sélectionner plusieurs catégories</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="edit-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="edit-email" name="email"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="edit-telephone"
                            class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                        <input type="tel" id="edit-telephone" name="telephone"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="mb-4">
                    <label for="edit-adresse" class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                    <input type="text" id="edit-adresse" name="adresse"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="edit-notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea id="edit-notes" name="notes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="button"
                        class="cancel-modal bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md mr-2">Annuler</button>
                    <button type="submit"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour supprimer un fournisseur -->
    <div id="delete-supplier-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Confirmer la suppression</h2>
            <p class="mb-4">Êtes-vous sûr de vouloir supprimer ce fournisseur? Cette action est irréversible.</p>
            <form id="delete-supplier-form" method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete-id" name="id" value="">
                <div class="flex justify-end">
                    <button type="button"
                        class="cancel-modal bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md mr-2">Annuler</button>
                    <button type="submit"
                        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md">Supprimer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts jQuery et DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>

    <script>
        // Désenregistrer tous les gestionnaires d'événements précédents
        $(document).off('click', '.edit-supplier, .delete-supplier, .edit-category, .delete-category');
        $(document).ready(function() {
            // Nettoyer d'abord le DOM avant d'initialiser DataTables
            $('#suppliers-table').find('tbody').html(function() {
                // Éliminer les doublons basés sur l'attribut data-fournisseur-id
                const uniqueRows = {};
                $(this).find('tr').each(function() {
                    const id = $(this).data('fournisseur-id');
                    if (id) {
                        if (!uniqueRows[id]) {
                            uniqueRows[id] = $(this)[0].outerHTML;
                        }
                    }
                });

                // Reconstruire le contenu avec seulement des lignes uniques
                return Object.values(uniqueRows).join('');
            });

            // Détruire l'instance existante si elle existe
            if ($.fn.DataTable.isDataTable('#suppliers-table')) {
                $('#suppliers-table').DataTable().destroy();
            }

            // Initialiser DataTables avec les options minimales nécessaires
            const suppliersTable = $('#suppliers-table').DataTable({
                responsive: true,
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                },
                order: [
                    [0, 'asc']
                ],
                pageLength: 15,
                retrieve: true, // Utiliser l'instance existante si elle existe
                destroy: true, // Détruire l'instance existante avant d'en créer une nouvelle
                stateSave: false // Ne pas sauvegarder l'état pour éviter les problèmes de cache
            });

            // Même chose pour la table des statistiques
            $('#stats-table').find('tbody').html(function() {
                const uniqueRows = {};
                $(this).find('tr').each(function() {
                    const id = $(this).find('td:first').text();
                    if (!uniqueRows[id]) {
                        uniqueRows[id] = $(this)[0].outerHTML;
                    }
                });
                return Object.values(uniqueRows).join('');
            });

            if ($.fn.DataTable.isDataTable('#stats-table')) {
                $('#stats-table').DataTable().destroy();
            }

            const statsTable = $('#stats-table').DataTable({
                responsive: true,
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                },
                order: [
                    [2, 'desc']
                ],
                pageLength: 15,
                retrieve: true,
                destroy: true,
                stateSave: false
            });

            // Recherche en temps réel
            $('#supplier-search').on('keyup', function() {
                suppliersTable.search(this.value).draw();
            });

            // Filtre par catégorie
            $('#category-filter').on('change', function() {
                const category = $(this).val();

                if (category === '') {
                    // Si aucune catégorie n'est sélectionnée, afficher tous les fournisseurs
                    suppliersTable.search('').draw();
                } else {
                    // Sinon, filtrer par la catégorie sélectionnée
                    suppliersTable.search(category).draw();
                }
            });

            // Navigation par onglets
            $('#tab-list').on('click', function(e) {
                e.preventDefault();
                toggleActiveTab($(this), $('#section-list'));
            });

            $('#tab-stats').on('click', function(e) {
                e.preventDefault();
                toggleActiveTab($(this), $('#section-stats'));
            });

            $('#tab-orders').on('click', function(e) {
                e.preventDefault();
                toggleActiveTab($(this), $('#section-orders'));
            });

            function toggleActiveTab(tab, section) {
                // Désactiver tous les onglets et sections
                $('#tab-list, #tab-stats, #tab-orders').removeClass('text-blue-600 border-b-2 border-blue-500').addClass('text-gray-500');
                $('#section-list, #section-stats, #section-orders').addClass('hidden');

                // Activer l'onglet et la section cliqués
                tab.removeClass('text-gray-500').addClass('text-blue-600 border-b-2 border-blue-500');
                section.removeClass('hidden');
            }

            // Gestion des modales
            // Ouvrir la modale d'ajout de fournisseur
            $('#add-supplier-btn').on('click', function() {
                $('#add-supplier-modal').css('display', 'block');
            });

            // Ouvrir la modale de modification
            $('.edit-supplier').on('click', function() {
                const id = $(this).data('id');
                loadSupplierData(id);
            });

            // Ouvrir la modale de suppression
            $('.delete-supplier').on('click', function() {
                const id = $(this).data('id');
                $('#delete-id').val(id);
                $('#delete-supplier-modal').css('display', 'block');
            });

            // Fermer les modales
            $('.close, .cancel-modal').on('click', function() {
                $('.modal').css('display', 'none');
            });

            // Fermer les modales en cliquant en dehors
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('modal')) {
                    $('.modal').css('display', 'none');
                }
            });

            // Charger les données du fournisseur pour l'édition
            function loadSupplierData(id) {
                $.ajax({
                    url: 'get_supplier.php',
                    type: 'GET',
                    data: {
                        id: id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const supplier = response.supplier;
                            $('#edit-id').val(supplier.id);
                            $('#edit-nom').val(supplier.nom);
                            $('#edit-email').val(supplier.email);
                            $('#edit-telephone').val(supplier.telephone);
                            $('#edit-adresse').val(supplier.adresse);
                            $('#edit-notes').val(supplier.notes);

                            // Récupérer les catégories du fournisseur
                            $.ajax({
                                url: 'get_fournisseur_categories.php',
                                type: 'GET',
                                data: {
                                    fournisseur_id: supplier.id
                                },
                                dataType: 'json',
                                success: function(categoriesResponse) {
                                    if (categoriesResponse.success) {
                                        // Désélectionner toutes les options
                                        $('#edit-categories option').prop('selected', false);

                                        // Sélectionner les catégories du fournisseur
                                        const categories = categoriesResponse.categories;
                                        categories.forEach(function(categorie) {
                                            $('#edit-categories option[value="' + categorie + '"]').prop('selected', true);
                                        });
                                    }
                                }
                            });

                            $('#edit-supplier-modal').css('display', 'block');
                        } else {
                            showNotification("Erreur lors du chargement des données", "error");
                        }
                    },
                    error: function() {
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

            // Si un message de succès est affiché, le cacher après 5 secondes
            setTimeout(function() {
                $('#flash-message').fadeOut('slow');
            }, 5000);
        });

        // Désactiver complètement DataTables lors des opérations d'ajout/modification
        $('#add-supplier-form, #edit-supplier-form, #add-category-form, #edit-category-form').on('submit', function() {
            // Supprimer toute trace de DataTables avant la soumission
            if ($.fn.DataTable.isDataTable('#suppliers-table')) {
                $('#suppliers-table').DataTable().destroy();
                $('#suppliers-table').removeClass('dataTable').find('tbody tr').removeClass();
            }
            if ($.fn.DataTable.isDataTable('#categories-table')) {
                $('#categories-table').DataTable().destroy();
                $('#categories-table').removeClass('dataTable').find('tbody tr').removeClass();
            }
            return true;
        });

        // Gestion de l'export du rapport global des fournisseurs
        $('#export-all-suppliers-report').on('click', function() {
            const totalSuppliers = <?php echo count($fournisseurs); ?>;

            // Afficher une confirmation avec options
            Swal.fire({
                title: 'Exporter le rapport global des fournisseurs',
                html: `
            <p>Générer un rapport PDF complet de tous les fournisseurs ?</p>
            <div style="margin-top: 15px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
                <strong>Contenu du rapport :</strong><br>
                • ${totalSuppliers} fournisseurs<br>
                • Statistiques générales<br>
                • Analyse comparative<br>
                • Top fournisseurs par performance<br>
                • Répartition par catégories<br>
                • Recommandations stratégiques
            </div>
        `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Oui, générer le rapport',
                cancelButtonText: 'Annuler',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    // Construire l'URL du rapport avec paramètres
                    const reportUrl = '../User-Achat/statistics/generate_report.php?' +
                        'type=all_suppliers&' +
                        'period=all&' +
                        'include_stats=1&' +
                        'include_performance=1&' +
                        'include_categories=1&' +
                        'include_recommendations=1';

                    // Ouvrir le PDF dans un nouvel onglet
                    window.open(reportUrl, '_blank');
                }
            });
        });
    </script>

    <?php

    // Fonction pour déterminer la couleur du badge en fonction de la catégorie
    function getBadgeColor($category)
    {
        global $pdo;

        // Utiliser le ColorManager pour une gestion centralisée
        return ColorManager::getCategoryColor($category, $pdo);
    }
    ?>
</body>

</html>