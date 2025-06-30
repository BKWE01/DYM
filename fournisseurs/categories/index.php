<?php
session_start();

// Désactiver complètement la mise en cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date dans le passé

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

require_once '../config/colors.php';
include_once '../../database/connection.php';

// Configuration des uploads
define('UPLOAD_DIR', 'uploads/icons/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['png', 'jpg', 'jpeg', 'svg', 'gif', 'webp']);

$availableColors = ColorManager::getAllColors();
$message = '';

/**
 * Fonction pour gérer l'upload d'icône
 */
function handleIconUpload($file, $categoryName)
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // Pas de fichier uploadé
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erreur lors de l\'upload du fichier.');
    }

    // Vérifier la taille
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('Le fichier est trop volumineux. Taille maximale : 2MB');
    }

    // Vérifier l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        throw new Exception('Type de fichier non autorisé. Formats acceptés : ' . implode(', ', ALLOWED_EXTENSIONS));
    }

    // Créer le dossier s'il n'existe pas
    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            throw new Exception('Impossible de créer le dossier de destination.');
        }
    }

    // Générer un nom de fichier unique
    $fileName = 'icon_' . preg_replace('/[^a-zA-Z0-9]/', '_', $categoryName) . '_' . time() . '.' . $extension;
    $filePath = UPLOAD_DIR . $fileName;

    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Erreur lors de la sauvegarde du fichier.');
    }

    return $filePath;
}

/**
 * Fonction pour supprimer une icône
 */
function deleteIcon($iconPath)
{
    if ($iconPath && file_exists($iconPath)) {
        unlink($iconPath);
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            // Traitement de l'ajout
            $nom = trim($_POST['nom']);
            $description = trim($_POST['description']);
            $couleur = $_POST['couleur'];

            try {
                // Vérifier si la catégorie existe déjà
                $checkQuery = "SELECT COUNT(*) as count FROM categories_fournisseurs WHERE nom = :nom";
                $checkStmt = $pdo->prepare($checkQuery);
                $checkStmt->bindParam(':nom', $nom);
                $checkStmt->execute();
                $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($result['count'] > 0) {
                    $message = "Une catégorie avec ce nom existe déjà.";
                } else {
                    // Gérer l'upload d'icône
                    $iconPath = null;
                    if (isset($_FILES['icon_file'])) {
                        try {
                            $iconPath = handleIconUpload($_FILES['icon_file'], $nom);
                        } catch (Exception $e) {
                            $message = "Erreur d'upload : " . $e->getMessage();
                        }
                    }

                    if (empty($message)) {
                        // Insérer la nouvelle catégorie
                        $insertQuery = "INSERT INTO categories_fournisseurs (nom, description, couleur, icon_path, created_by) 
                                      VALUES (:nom, :description, :couleur, :icon_path, :created_by)";
                        $insertStmt = $pdo->prepare($insertQuery);
                        $insertStmt->bindParam(':nom', $nom);
                        $insertStmt->bindParam(':description', $description);
                        $insertStmt->bindParam(':couleur', $couleur);
                        $insertStmt->bindParam(':icon_path', $iconPath);
                        $insertStmt->bindParam(':created_by', $user_id);
                        $insertStmt->execute();

                        // Redirection immédiate avec paramètres anti-cache
                        $redirectUrl = "index.php?success=add&t=" . time() . "&r=" . rand(1000, 9999) . "&nocache=" . uniqid();
                        header("Location: $redirectUrl");
                        exit();
                    }
                }
            } catch (PDOException $e) {
                $message = "Erreur lors de l'ajout de la catégorie: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'edit') {
            // Traitement de la modification
            $id = $_POST['id'];
            $nom = trim($_POST['nom']);
            $description = trim($_POST['description']);
            $couleur = $_POST['couleur'];
            $active = isset($_POST['active']) ? 1 : 0;

            try {
                // Vérifier si une autre catégorie a déjà ce nom
                $checkQuery = "SELECT COUNT(*) as count FROM categories_fournisseurs WHERE nom = :nom AND id != :id";
                $checkStmt = $pdo->prepare($checkQuery);
                $checkStmt->bindParam(':nom', $nom);
                $checkStmt->bindParam(':id', $id);
                $checkStmt->execute();
                $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($result['count'] > 0) {
                    $message = "Une autre catégorie avec ce nom existe déjà.";
                } else {
                    // Récupérer les anciennes données
                    $getOldQuery = "SELECT nom, icon_path FROM categories_fournisseurs WHERE id = :id";
                    $getOldStmt = $pdo->prepare($getOldQuery);
                    $getOldStmt->bindParam(':id', $id);
                    $getOldStmt->execute();
                    $oldData = $getOldStmt->fetch(PDO::FETCH_ASSOC);

                    $pdo->beginTransaction();

                    // Gérer l'upload d'une nouvelle icône
                    $iconPath = $oldData['icon_path'];

                    if (isset($_FILES['icon_file']) && $_FILES['icon_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                        try {
                            $newIconPath = handleIconUpload($_FILES['icon_file'], $nom);
                            if ($newIconPath) {
                                deleteIcon($oldData['icon_path']);
                                $iconPath = $newIconPath;
                            }
                        } catch (Exception $e) {
                            $message = "Erreur d'upload : " . $e->getMessage();
                        }
                    }

                    if (isset($_POST['remove_icon']) && $_POST['remove_icon'] === '1') {
                        deleteIcon($oldData['icon_path']);
                        $iconPath = null;
                    }

                    if (empty($message)) {
                        $updateQuery = "UPDATE categories_fournisseurs SET 
                                     nom = :nom, 
                                     description = :description, 
                                     couleur = :couleur, 
                                     icon_path = :icon_path,
                                     active = :active,
                                     updated_at = NOW()
                                     WHERE id = :id";
                        $updateStmt = $pdo->prepare($updateQuery);
                        $updateStmt->bindParam(':nom', $nom);
                        $updateStmt->bindParam(':description', $description);
                        $updateStmt->bindParam(':couleur', $couleur);
                        $updateStmt->bindParam(':icon_path', $iconPath);
                        $updateStmt->bindParam(':active', $active);
                        $updateStmt->bindParam(':id', $id);
                        $updateStmt->execute();

                        if ($oldData['nom'] !== $nom) {
                            $updateRefQuery = "UPDATE fournisseur_categories SET categorie = :new_name WHERE categorie = :old_name";
                            $updateRefStmt = $pdo->prepare($updateRefQuery);
                            $updateRefStmt->bindParam(':new_name', $nom);
                            $updateRefStmt->bindParam(':old_name', $oldData['nom']);
                            $updateRefStmt->execute();
                        }

                        $pdo->commit();

                        // Redirection immédiate avec paramètres anti-cache
                        $redirectUrl = "index.php?success=edit&t=" . time() . "&r=" . rand(1000, 9999) . "&nocache=" . uniqid();
                        header("Location: $redirectUrl");
                        exit();
                    } else {
                        $pdo->rollBack();
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = "Erreur lors de la mise à jour: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'delete') {
            // Traitement de la suppression
            $id = $_POST['id'];

            try {
                $getQuery = "SELECT nom, icon_path FROM categories_fournisseurs WHERE id = :id";
                $getStmt = $pdo->prepare($getQuery);
                $getStmt->bindParam(':id', $id);
                $getStmt->execute();
                $categoryData = $getStmt->fetch(PDO::FETCH_ASSOC);

                if (!$categoryData) {
                    $message = "Catégorie non trouvée.";
                } else {
                    $checkQuery = "SELECT COUNT(*) FROM fournisseur_categories WHERE categorie = :categorie";
                    $checkStmt = $pdo->prepare($checkQuery);
                    $checkStmt->bindParam(':categorie', $categoryData['nom']);
                    $checkStmt->execute();
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        $message = "Cette catégorie ne peut pas être supprimée car elle est utilisée par des fournisseurs.";
                    } else {
                        $deleteQuery = "DELETE FROM categories_fournisseurs WHERE id = :id";
                        $deleteStmt = $pdo->prepare($deleteQuery);
                        $deleteStmt->bindParam(':id', $id);
                        $deleteStmt->execute();

                        deleteIcon($categoryData['icon_path']);

                        // Redirection immédiate avec paramètres anti-cache
                        $redirectUrl = "index.php?success=delete&t=" . time() . "&r=" . rand(1000, 9999) . "&nocache=" . uniqid();
                        header("Location: $redirectUrl");
                        exit();
                    }
                }
            } catch (PDOException $e) {
                $message = "Erreur lors de la suppression: " . $e->getMessage();
            }
        }
    }
}

// Récupérer toutes les catégories - SOLUTION INSPIRÉE DE FOURNISSEURS.PHP
$categories = [];
try {
    // PREMIÈRE ÉTAPE : Nettoyer les doublons en base de façon plus agressive
    $cleanupQuery = "
        DELETE t1 FROM categories_fournisseurs t1
        INNER JOIN categories_fournisseurs t2 
        WHERE t1.id > t2.id 
        AND LOWER(TRIM(t1.nom)) = LOWER(TRIM(t2.nom))
    ";
    $pdo->exec($cleanupQuery);

    // Requête directe pour récupérer toutes les catégories
    $query = "SELECT id, nom, description, couleur, icon_path, active, created_by, created_at, updated_at 
              FROM categories_fournisseurs 
              ORDER BY updated_at DESC, nom ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $rawCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("=== DEBUG REQUÊTE SQL ===");
    error_log("Nombre de catégories récupérées de la DB : " . count($rawCategories));

    // ÉLIMINER LES DOUBLONS CÔTÉ PHP DE FAÇON PLUS STRICTE
    $uniqueCategories = [];
    $seenNames = []; // Pour tracker les noms déjà vus

    foreach ($rawCategories as $category) {
        $categoryId = $category['id'];
        $categoryName = strtolower(trim($category['nom'])); // Normaliser le nom

        // Vérifier que la catégorie a des données valides
        if (!empty($category['nom']) && !empty($category['id'])) {

            // Vérifier si ce nom n'a pas déjà été ajouté
            if (!isset($seenNames[$categoryName])) {
                // S'assurer que couleur existe
                if (empty($category['couleur'])) {
                    $category['couleur'] = 'badge-blue';
                }

                $uniqueCategories[$categoryId] = $category;
                $seenNames[$categoryName] = $categoryId;

                error_log("Catégorie ajoutée aux uniques: ID={$category['id']}, Nom='{$category['nom']}'");
            } else {
                error_log("Doublon ignoré: ID={$category['id']}, Nom='{$category['nom']}' (déjà vu avec ID={$seenNames[$categoryName]})");
            }
        } else {
            error_log("Catégorie ignorée (données invalides): ID={$category['id']}, Nom='{$category['nom']}'");
        }
    }

    // Convertir en tableau indexé
    $categories = array_values($uniqueCategories);

    // Ajouter le nombre de fournisseurs pour chaque catégorie
    foreach ($categories as &$category) {
        $countQuery = "SELECT COUNT(DISTINCT fc.id) FROM fournisseur_categories fc WHERE fc.categorie = :categorie";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->bindParam(':categorie', $category['nom']);
        $countStmt->execute();
        $category['nb_fournisseurs'] = (int)$countStmt->fetchColumn();
    }

    // Debug : afficher le nombre de catégories après filtrage complet
    error_log("Nombre de catégories après filtrage strict : " . count($categories));

    // Vérifier qu'il n'y a plus de doublons
    $noms = array_column($categories, 'nom');
    $nomsLower = array_map('strtolower', array_map('trim', $noms));
    $duplicates = array_diff_assoc($nomsLower, array_unique($nomsLower));

    if (!empty($duplicates)) {
        error_log("DOUBLONS ENCORE PRÉSENTS APRÈS FILTRAGE : " . implode(', ', $duplicates));

        // Afficher tous les noms pour debug
        error_log("TOUS LES NOMS FINAUX : " . implode(', ', $noms));
    } else {
        error_log("✅ PLUS DE DOUBLONS DÉTECTÉS - PROBLÈME RÉSOLU");
    }

    // Vérifier si "teste" est présent
    $testeFound = false;
    foreach ($categories as $cat) {
        if (strtolower(trim($cat['nom'])) === 'teste') {
            $testeFound = true;
            error_log("✅ TESTE TROUVÉ : ID={$cat['id']}, Active={$cat['active']}, Couleur={$cat['couleur']}");
            break;
        }
    }
    if (!$testeFound) {
        error_log("❌ TESTE NON TROUVÉ dans les catégories finales !");
    }
} catch (PDOException $e) {
    $message = "Erreur lors de la récupération des catégories: " . $e->getMessage();
    error_log("Erreur SQL : " . $e->getMessage());
}

// Message de succès basé sur les paramètres GET
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'add':
            $message = "Catégorie ajoutée avec succès!";
            break;
        case 'edit':
            $message = "Catégorie mise à jour avec succès!";
            break;
        case 'delete':
            $message = "Catégorie supprimée avec succès!";
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories de Fournisseurs</title>

    <!-- Meta pour désactiver le cache -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">

    <style>
        /* Styles DataTables personnalisés */
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

        /* Styles pour les badges */
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

        .badge-slate {
            background-color: #475569;
        }

        .badge-emerald {
            background-color: #059669;
        }

        /* Styles pour les modales */
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
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 70%;
            max-width: 800px;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-height: 90vh;
            overflow-y: auto;
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

        /* Styles pour l'upload d'icônes */
        .icon-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: border-color 0.3s ease;
            cursor: pointer;
        }

        .icon-upload-area:hover,
        .icon-upload-area.dragover {
            border-color: #3b82f6;
            background-color: #f8fafc;
        }

        .icon-preview {
            max-width: 64px;
            max-height: 64px;
            object-fit: contain;
            border-radius: 4px;
        }

        .current-icon {
            max-width: 32px;
            max-height: 32px;
            object-fit: contain;
        }

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
        }

        .bg-success-light {
            background-color: #c9ffed;
        }

        /* Animation pour les messages */
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
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper flex flex-col min-h-screen">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- En-tête -->
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex flex-wrap justify-between items-center">
                <h1 class="text-xl font-semibold flex items-center space-x-2">
                    <span class="material-icons">category</span>
                    <span>Gestion des Catégories de Fournisseurs</span>
                </h1>

                <div class="flex flex-wrap items-center space-x-4">
                    <a href="../fournisseurs.php" class="flex items-center text-gray-600 hover:text-gray-900">
                        <span class="material-icons mr-1">arrow_back</span>
                        Retour aux fournisseurs
                    </a>
                    <button id="add-category-btn"
                        class="flex items-center bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition-colors duration-300">
                        <span class="material-icons mr-2">add</span>
                        Ajouter une catégorie
                    </button>
                    <button id="refresh-btn"
                        class="flex items-center bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded-md transition-colors duration-300 ml-2">
                        <span class="material-icons mr-2">refresh</span>
                        Actualiser
                    </button>
                    <button id="cleanup-btn"
                        class="flex items-center bg-orange-500 hover:bg-orange-600 text-white py-2 px-4 rounded-md transition-colors duration-300 ml-2">
                        <span class="material-icons mr-2">cleaning_services</span>
                        Nettoyer les doublons
                    </button>
                </div>
            </div>

            <!-- Message flash -->
            <?php if (!empty($message)): ?>
                <div id="flash-message"
                    class="flash-message bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                    <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3"
                        onclick="document.getElementById('flash-message').style.display='none';">
                        <span class="material-icons">close</span>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Section Liste des catégories -->
            <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <div class="flex flex-wrap justify-between items-center mb-6">
                    <h2 class="text-lg font-semibold">
                        Liste des catégories
                        (<span id="categories-count"><?php echo count($categories); ?></span> entrées)
                        <?php if (count($categories) > 0): ?>
                            <small class="text-gray-500 ml-2">- Dernière mise à jour triée en premier</small>
                        <?php endif; ?>
                    </h2>
                    <div class="relative">
                        <input type="text" id="category-search" placeholder="Rechercher une catégorie"
                            class="pl-10 py-2 pr-4 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <span class="absolute left-3 top-2 text-gray-400 material-icons">search</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="categories-table" class="min-w-full">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Aperçu</th>
                                <th>Icône</th>
                                <th>Fournisseurs</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Debug : Afficher les catégories récupérées
                            error_log("=== DEBUG AFFICHAGE CATEGORIES ===");
                            error_log("Nombre total de catégories à afficher : " . count($categories));

                            foreach ($categories as $index => $category):
                                // Debug : Log chaque catégorie
                                error_log("Catégorie $index : ID={$category['id']}, Nom={$category['nom']}, Couleur={$category['couleur']}, Active={$category['active']}");

                                // Vérifier que la catégorie a bien toutes les données requises
                                if (empty($category['id']) || empty($category['nom'])) {
                                    error_log("ERREUR : Catégorie incomplète détectée - ID: {$category['id']}, Nom: {$category['nom']}");
                                    continue; // Ignorer les catégories incomplètes
                                }

                                // S'assurer que la couleur existe, sinon utiliser une couleur par défaut
                                $couleurCategory = !empty($category['couleur']) ? $category['couleur'] : 'badge-blue';
                            ?>
                                <tr data-category-id="<?php echo intval($category['id']); ?>" class="category-row">
                                    <td><?php echo htmlspecialchars($category['nom']); ?></td>
                                    <td><?php echo !empty($category['description']) ? htmlspecialchars($category['description']) : '<span class="text-gray-400">-</span>'; ?></td>
                                    <td>
                                        <div class="flex items-center space-x-2">
                                            <span class="badge <?php echo htmlspecialchars($couleurCategory); ?>">
                                                <?php echo htmlspecialchars($category['nom']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($category['icon_path']) && file_exists($category['icon_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($category['icon_path']); ?>"
                                                alt="Icône" class="current-icon"
                                                title="<?php echo htmlspecialchars($category['nom']); ?>">
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-gray"><?php echo intval($category['nb_fournisseurs']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (intval($category['active']) === 1): ?>
                                            <span class="badge badge-green">Actif</span>
                                        <?php else: ?>
                                            <span class="badge badge-red">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="edit-category text-blue-500 hover:text-blue-700 mx-1"
                                            data-id="<?php echo intval($category['id']); ?>" title="Modifier">
                                            <span class="material-icons">edit</span>
                                        </button>
                                        <button class="delete-category text-red-500 hover:text-red-700 mx-1"
                                            data-id="<?php echo intval($category['id']); ?>" title="Supprimer"
                                            <?php echo intval($category['nb_fournisseurs']) > 0 ? 'disabled' : ''; ?>>
                                            <span class="material-icons">
                                                <?php echo intval($category['nb_fournisseurs']) > 0 ? 'lock' : 'delete'; ?>
                                            </span>
                                        </button>
                                        <a href="view_fournisseurs.php?categorie=<?php echo urlencode($category['nom']); ?>"
                                            class="text-green-500 hover:text-green-700 mx-1" title="Voir les fournisseurs">
                                            <span class="material-icons">visibility</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php
                            endforeach;

                            // Debug final
                            error_log("=== FIN DEBUG AFFICHAGE ===");
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <!-- Modal pour ajouter une catégorie -->
    <div id="add-category-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Ajouter une nouvelle catégorie</h2>
            <form id="add-category-form" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <div class="mb-4">
                    <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">Nom de la catégorie *</label>
                    <input type="text" id="nom" name="nom"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        required>
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="couleur" class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
                        <select id="couleur" name="couleur"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($availableColors as $class => $info): ?>
                                <option value="<?php echo $class; ?>" data-hex="<?php echo $info['hex']; ?>">
                                    <?php echo $info['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="badge-preview" class="badge-preview badge-blue mt-2">Aperçu</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Icône personnalisée</label>
                        <div class="icon-upload-area" id="icon-upload-area">
                            <input type="file" id="icon_file" name="icon_file"
                                accept=".png,.jpg,.jpeg,.svg,.gif,.webp" class="hidden">
                            <div class="upload-content">
                                <span class="material-icons text-4xl text-gray-400 mb-2">cloud_upload</span>
                                <p class="text-sm text-gray-600 mb-2">Cliquez pour télécharger une icône</p>
                                <p class="text-xs text-gray-400">PNG, JPG, SVG, GIF (max 2MB)</p>
                            </div>
                            <div class="preview-content hidden">
                                <img id="icon-preview" class="icon-preview mx-auto mb-2" alt="Aperçu">
                                <button type="button" class="text-sm text-red-500 hover:text-red-700" id="remove-icon-btn">
                                    Supprimer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <div class="flex items-center mx-2 bg-success-light p-2 rounded">
                        <span id="icon-preview" class="material-icons mr-2">category</span>
                        <a href="https://fonts.google.com/icons" target="_blank" class="text-sm text-blue-500 hover:underline">Voir les icônes disponibles</a>
                    </div>

                    <button type="button"
                        class="cancel-modal bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md mr-2">
                        Annuler
                    </button>
                    <button type="submit"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">
                        Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour modifier une catégorie -->
    <div id="edit-category-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Modifier la catégorie</h2>
            <form id="edit-category-form" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit-id" name="id" value="">
                <input type="hidden" id="remove-icon-flag" name="remove_icon" value="0">

                <div class="mb-4">
                    <label for="edit-nom" class="block text-sm font-medium text-gray-700 mb-1">Nom de la catégorie *</label>
                    <input type="text" id="edit-nom" name="nom"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        required>
                </div>

                <div class="mb-4">
                    <label for="edit-description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="edit-description" name="description" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="edit-couleur" class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
                        <select id="edit-couleur" name="couleur"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($availableColors as $class => $info): ?>
                                <option value="<?php echo $class; ?>" data-hex="<?php echo $info['hex']; ?>">
                                    <?php echo $info['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="edit-badge-preview" class="badge-preview badge-blue mt-2">Aperçu</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Icône personnalisée</label>

                        <!-- Icône actuelle -->
                        <div id="current-icon-section" class="mb-3 hidden">
                            <p class="text-sm text-gray-600 mb-2">Icône actuelle :</p>
                            <div class="flex items-center space-x-3">
                                <img id="current-icon-img" class="icon-preview" alt="Icône actuelle">
                                <button type="button" class="text-sm text-red-500 hover:text-red-700" id="remove-current-icon">
                                    Supprimer l'icône actuelle
                                </button>
                            </div>
                        </div>

                        <!-- Zone d'upload -->
                        <div class="icon-upload-area" id="edit-icon-upload-area">
                            <input type="file" id="edit_icon_file" name="icon_file"
                                accept=".png,.jpg,.jpeg,.svg,.gif,.webp" class="hidden">
                            <div class="upload-content">
                                <span class="material-icons text-4xl text-gray-400 mb-2">cloud_upload</span>
                                <p class="text-sm text-gray-600 mb-2">Cliquez pour télécharger une nouvelle icône</p>
                                <p class="text-xs text-gray-400">PNG, JPG, SVG, GIF (max 2MB)</p>
                            </div>
                            <div class="preview-content hidden">
                                <img id="edit-icon-preview" class="icon-preview mx-auto mb-2" alt="Aperçu">
                                <button type="button" class="text-sm text-red-500 hover:text-red-700" id="edit-remove-icon-btn">
                                    Supprimer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="flex items-center">
                        <input type="checkbox" id="edit-active" name="active"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="edit-active" class="ml-2 block text-sm text-gray-900">
                            Catégorie active
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Décochez pour désactiver sans supprimer.</p>
                </div>

                <div class="flex justify-end">
                    <button type="button"
                        class="cancel-modal bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md mr-2">
                        Annuler
                    </button>
                    <button type="submit"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour supprimer une catégorie -->
    <div id="delete-category-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-semibold mb-4">Confirmer la suppression</h2>
            <p class="mb-4">Êtes-vous sûr de vouloir supprimer cette catégorie? Cette action est irréversible.</p>
            <form id="delete-category-form" method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete-id" name="id" value="">
                <div class="flex justify-end">
                    <button type="button"
                        class="cancel-modal bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md mr-2">
                        Annuler
                    </button>
                    <button type="submit"
                        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md">
                        Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>

    <script>
        $(document).ready(function() {

            // NETTOYAGE DU DOM AVANT INITIALISATION (solution de fournisseurs.php)
            $('#categories-table').find('tbody').html(function() {
                // Éliminer les doublons basés sur l'attribut data-category-id
                const uniqueRows = {};
                $(this).find('tr').each(function() {
                    const id = $(this).data('category-id');
                    if (id) {
                        if (!uniqueRows[id]) {
                            uniqueRows[id] = $(this)[0].outerHTML;
                        }
                    }
                });

                // Reconstruire le contenu avec seulement des lignes uniques
                return Object.values(uniqueRows).join('');
            });

            // FORCER LA DESTRUCTION DE TOUTE INSTANCE DATATABLE EXISTANTE
            if ($.fn.DataTable.isDataTable('#categories-table')) {
                $('#categories-table').DataTable().clear().destroy();
            }

            // Nettoyer complètement le DOM du tableau
            $('#categories-table').removeClass('dataTable');

            // Supprimer tous les événements liés à DataTables
            $('#categories-table').off();

            // INITIALISER DATATABLE AVEC DES PARAMÈTRES STRICTS
            const categoriesTable = $('#categories-table').DataTable({
                responsive: true,
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                },
                order: [
                    [0, 'asc']
                ],
                pageLength: -1, // Afficher toutes les lignes par défaut
                destroy: true,
                retrieve: false, // IMPORTANT : Ne pas réutiliser une instance existante
                stateSave: false,
                processing: false,
                serverSide: false,
                deferRender: false,
                // Désactiver le cache interne de DataTables
                ajax: null,
                data: null,
                // Configuration stricte pour éviter les doublons
                rowId: function(data) {
                    return 'row_' + $(data).data('category-id');
                },
                // Menu de pagination
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, "Tout"]
                ],
                // Forcer l'affichage de toutes les lignes
                dom: 'lrtip' // Supprimer la pagination par défaut
            });

            // Recherche en temps réel avec mise à jour du compteur
            $('#category-search').on('keyup', function() {
                categoriesTable.search(this.value).draw();

                // Mettre à jour le compteur
                var visibleRows = categoriesTable.rows({
                    search: 'applied'
                }).count();
                $('#categories-count').text(visibleRows);
            });

            // GESTION DES MODALES
            $('#add-category-btn').on('click', function() {
                resetForm('add');
                $('#add-category-modal').show();
            });

            $(document).on('click', '.edit-category', function() {
                const id = $(this).data('id');
                loadCategoryData(id);
            });

            $(document).on('click', '.delete-category', function() {
                if (!$(this).attr('disabled')) {
                    const id = $(this).data('id');
                    $('#delete-id').val(id);
                    $('#delete-category-modal').show();
                }
            });

            $('.close, .cancel-modal').on('click', function() {
                $('.modal').hide();
                resetForm();
            });

            $(window).on('click', function(event) {
                if ($(event.target).hasClass('modal')) {
                    $('.modal').hide();
                    resetForm();
                }
            });

            // GESTION DES UPLOADS D'ICÔNES
            $('#icon-upload-area').on('click', function(e) {
                if (!$(e.target).is('#icon_file')) {
                    e.preventDefault();
                    e.stopPropagation();
                    $('#icon_file').trigger('click');
                }
            });

            $('#icon_file').on('change', function(e) {
                e.stopPropagation();
                handleFilePreview(this, 'icon-preview', 'icon-upload-area');
            });

            $('#remove-icon-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                resetIconUpload('icon-upload-area', 'icon_file');
            });

            $('#edit-icon-upload-area').on('click', function(e) {
                if (!$(e.target).is('#edit_icon_file')) {
                    e.preventDefault();
                    e.stopPropagation();
                    $('#edit_icon_file').trigger('click');
                }
            });

            $('#edit_icon_file').on('change', function(e) {
                e.stopPropagation();
                handleFilePreview(this, 'edit-icon-preview', 'edit-icon-upload-area');
            });

            $('#edit-remove-icon-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                resetIconUpload('edit-icon-upload-area', 'edit_icon_file');
            });

            $('#remove-current-icon').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#remove-icon-flag').val('1');
                $('#current-icon-section').hide();
                $(this).text('Icône supprimée (sera effective après sauvegarde)').addClass('text-orange-500');
            });

            // Gestion de l'aperçu des couleurs
            $('#couleur').on('change', updateBadgePreview);
            $('#edit-couleur').on('change', updateEditBadgePreview);

            // FONCTIONS UTILITAIRES
            function handleFilePreview(input, previewId, uploadAreaId) {
                if (input.files && input.files[0]) {
                    const file = input.files[0];

                    if (file.size > 2 * 1024 * 1024) {
                        Swal.fire({
                            title: 'Erreur',
                            text: 'Le fichier est trop volumineux. Taille maximale : 2MB',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        input.value = '';
                        return;
                    }

                    const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        Swal.fire({
                            title: 'Erreur',
                            text: 'Type de fichier non autorisé. Utilisez PNG, JPG, SVG, GIF ou WebP.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        input.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $(`#${previewId}`).attr('src', e.target.result);
                        $(`#${uploadAreaId} .upload-content`).addClass('hidden');
                        $(`#${uploadAreaId} .preview-content`).removeClass('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            }

            function resetIconUpload(uploadAreaId, inputId) {
                $(`#${inputId}`).val('');
                $(`#${uploadAreaId} .upload-content`).removeClass('hidden');
                $(`#${uploadAreaId} .preview-content`).addClass('hidden');
            }

            function resetForm(type = '') {
                if (type === 'add') {
                    $('#add-category-form')[0].reset();
                    resetIconUpload('icon-upload-area', 'icon_file');
                    updateBadgePreview();
                } else {
                    resetIconUpload('edit-icon-upload-area', 'edit_icon_file');
                    $('#current-icon-section').addClass('hidden');
                    $('#remove-icon-flag').val('0');
                    $('#remove-current-icon').text('Supprimer l\'icône actuelle').removeClass('text-orange-500');
                }
            }

            function updateBadgePreview() {
                const colorClass = $('#couleur').val();
                const categoryName = $('#nom').val() || 'Aperçu';
                $('#badge-preview').attr('class', 'badge-preview ' + colorClass).text(categoryName);
            }

            function updateEditBadgePreview() {
                const colorClass = $('#edit-couleur').val();
                const categoryName = $('#edit-nom').val() || 'Aperçu';
                $('#edit-badge-preview').attr('class', 'badge-preview ' + colorClass).text(categoryName);
            }

            function loadCategoryData(id) {
                $.ajax({
                    url: 'api/get.php',
                    type: 'GET',
                    data: {
                        id: id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const category = response.category;
                            $('#edit-id').val(category.id);
                            $('#edit-nom').val(category.nom);
                            $('#edit-description').val(category.description);
                            $('#edit-couleur').val(category.couleur);
                            $('#edit-active').prop('checked', category.active == 1);
                            $('#remove-icon-flag').val('0');

                            if (category.icon_path) {
                                $('#current-icon-img').attr('src', category.icon_path);
                                $('#current-icon-section').removeClass('hidden');
                                $('#remove-current-icon').text('Supprimer l\'icône actuelle').removeClass('text-orange-500');
                            } else {
                                $('#current-icon-section').addClass('hidden');
                            }

                            updateEditBadgePreview();
                            resetIconUpload('edit-icon-upload-area', 'edit_icon_file');
                            $('#edit-category-modal').show();
                        } else {
                            Swal.fire('Erreur', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Erreur', 'Erreur de communication avec le serveur', 'error');
                    }
                });
            }

            // Mettre à jour l'aperçu en temps réel
            $('#nom').on('input', updateBadgePreview);
            $('#edit-nom').on('input', updateEditBadgePreview);

            // Initialiser les aperçus
            updateBadgePreview();

            // Bouton de rafraîchissement FORCE LE RECHARGEMENT
            $('#refresh-btn').on('click', function() {
                // Forcer le rechargement complet avec suppression du cache
                window.location.href = window.location.pathname + '?nocache=' + new Date().getTime();
            });

            // Bouton de nettoyage des doublons
            $('#cleanup-btn').on('click', function() {
                Swal.fire({
                    title: 'Nettoyer les doublons ?',
                    text: 'Cette action va supprimer définitivement les catégories en double. Êtes-vous sûr ?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f59e0b',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Oui, nettoyer',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Afficher un loader
                        Swal.fire({
                            title: 'Nettoyage en cours...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // Appeler l'API de nettoyage
                        $.ajax({
                            url: 'api/cleanup.php',
                            type: 'POST',
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        title: 'Nettoyage terminé !',
                                        text: `${response.duplicates_found} doublons trouvés et nettoyés. ${response.additional_deleted_rows} lignes supplémentaires supprimées.`,
                                        icon: 'success',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        // Recharger la page
                                        window.location.reload(true);
                                    });
                                } else {
                                    Swal.fire('Erreur', response.message, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Erreur', 'Erreur de communication avec le serveur', 'error');
                            }
                        });
                    }
                });
            });

            // Cacher le message flash après 5 secondes
            setTimeout(function() {
                $('#flash-message').fadeOut('slow');
            }, 5000);

            // Log pour debug dans la console
            console.log('=== DEBUG FRONTEND ===');
            console.log('Catégories PHP récupérées: <?php echo count($categories); ?>');
            console.log('DataTable initialisée avec ' + categoriesTable.data().count() + ' lignes');
            console.log('Lignes visibles dans le DOM: ' + $('#categories-table tbody tr.category-row').length);

            // Vérifier si toutes les lignes sont bien affichées
            var phpCount = <?php echo count($categories); ?>;
            var domRows = $('#categories-table tbody tr.category-row').length;
            var dataTableCount = categoriesTable.data().count();

            if (phpCount !== domRows) {
                console.error('PROBLÈME DÉTECTÉ: PHP=' + phpCount + ', DOM=' + domRows + ', DataTable=' + dataTableCount);

                // Lister les catégories manquantes
                console.log('Catégories PHP:', <?php echo json_encode(array_column($categories, 'nom')); ?>);

                var domCategories = [];
                $('#categories-table tbody tr.category-row').each(function() {
                    domCategories.push($(this).find('td:first').text().trim());
                });
                console.log('Catégories DOM:', domCategories);
            } else {
                console.log('✅ Toutes les catégories sont correctement affichées');
            }

            // Rechercher spécifiquement "teste"
            var testeRow = $('#categories-table tbody tr.category-row').filter(function() {
                return $(this).find('td:first').text().trim().toLowerCase() === 'teste';
            });

            if (testeRow.length > 0) {
                console.log('✅ Catégorie "teste" trouvée dans le DOM');
                console.log('Données teste:', {
                    id: testeRow.data('category-id'),
                    nom: testeRow.find('td:first').text(),
                    visible: testeRow.is(':visible')
                });
            } else {
                console.error('❌ Catégorie "teste" NON trouvée dans le DOM');
            }
        });

        // DÉSACTIVER DATATABLES LORS DES SOUMISSIONS (solution de fournisseurs.php)
        $('#add-category-form, #edit-category-form, #delete-category-form').on('submit', function() {
            // Supprimer toute trace de DataTables avant la soumission
            if ($.fn.DataTable.isDataTable('#categories-table')) {
                $('#categories-table').DataTable().destroy();
                $('#categories-table').removeClass('dataTable').find('tbody tr').removeClass();
            }
            return true;
        });
    </script>
</body>

</html>