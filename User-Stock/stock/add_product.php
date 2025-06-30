<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Vérifier si l'utilisateur a le rôle super_admin ou admin
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'super_admin' && $_SESSION['user_role'] !== 'admin')) {
    header("Location: ./index.php");
    exit();
}

// Connexion à la base de données
include_once '../../database/connection.php';

// Logger
include_once __DIR__ . '/trace/include_logger.php';
$logger = getLogger();

// Récupérer les catégories et leurs derniers numéros de produit
$categoriesQuery = $pdo->query("SELECT c.id, c.libelle, c.code, 
    COALESCE(MAX(CAST(SUBSTRING(p.barcode, LENGTH(c.code) + 2) AS UNSIGNED)), 0) as last_number
    FROM categories c
    LEFT JOIN products p ON p.barcode LIKE CONCAT(c.code, '-%')
    GROUP BY c.id, c.libelle, c.code
    ORDER BY c.libelle");
$categories = $categoriesQuery->fetchAll(PDO::FETCH_ASSOC);

// Créer un tableau associatif pour stocker les derniers numéros par catégorie
$lastNumbers = array();
foreach ($categories as $category) {
    $lastNumbers[$category['code']] = $category['last_number'];
}

// Récupérer les fournisseurs pour la liste déroulante
$fournisseursQuery = $pdo->query("SELECT id, nom FROM fournisseurs ORDER BY nom");
$fournisseurs = $fournisseursQuery->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les unités déjà utilisées pour les suggestions
$unitsQuery = $pdo->query("SELECT DISTINCT unit FROM products WHERE unit IS NOT NULL AND unit != '' ORDER BY unit");
$units = $unitsQuery->fetchAll(PDO::FETCH_COLUMN);

// Traitement de l'importation de fichier CSV
$importMessage = '';
$importClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");

    if ($handle !== FALSE) {
        // Lire l'en-tête pour déterminer les colonnes
        $header = fgetcsv($handle, 1000, ",");

        // Normaliser les en-têtes (supprimer les espaces, mettre en minuscules)
        $header = array_map(function ($item) {
            return strtolower(trim($item));
        }, $header);

        // Déterminer les indices des colonnes requises
        $colIndices = [
            'name' => array_search('nom', $header) !== false ? array_search('nom', $header) : array_search('name', $header),
            'quantity' => array_search('quantite', $header) !== false ? array_search('quantite', $header) : array_search('quantity', $header),
            'unit' => array_search('unite', $header) !== false ? array_search('unite', $header) : array_search('unit', $header),
            'price' => array_search('prix', $header) !== false ? array_search('prix', $header) : array_search('price', $header),
            'category' => array_search('categorie', $header) !== false ? array_search('categorie', $header) : array_search('category', $header),
            'supplier' => array_search('fournisseur', $header) !== false ? array_search('fournisseur', $header) : array_search('supplier', $header),
            'notes' => array_search('notes', $header) !== false ? array_search('notes', $header) : array_search('note', $header),
        ];

        // Vérifier que toutes les colonnes requises sont présentes
        $missingColumns = [];
        foreach (['name', 'quantity', 'unit', 'price'] as $requiredCol) {
            if ($colIndices[$requiredCol] === false) {
                $missingColumns[] = $requiredCol;
            }
        }

        if (!empty($missingColumns)) {
            $importMessage = "Erreur: Colonnes manquantes dans le fichier CSV: " . implode(', ', $missingColumns);
            $importClass = "text-red-600";
        } else {
            // Préparer la requête pour obtenir l'ID de catégorie par libellé
            $catStmt = $pdo->prepare("SELECT id FROM categories WHERE libelle = ? OR code = ? LIMIT 1");

            // Préparation de la requête d'insertion
            $stmt = $pdo->prepare("INSERT INTO products (barcode, product_name, quantity, unit, unit_price, category, supplier_id, notes, created_at) 
                                 VALUES (:barcode, :product_name, :quantity, :unit, :unit_price, :category, :supplier_id, :notes, NOW())");

            $pdo->beginTransaction();
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            // Parcourir le fichier ligne par ligne
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Traitement des données
                $productName = isset($data[$colIndices['name']]) ? trim($data[$colIndices['name']]) : '';
                $quantity = isset($data[$colIndices['quantity']]) ? trim($data[$colIndices['quantity']]) : 0;
                $unit = isset($data[$colIndices['unit']]) ? trim($data[$colIndices['unit']]) : 'unité';
                $price = isset($data[$colIndices['price']]) ? trim($data[$colIndices['price']]) : 0;
                $categoryName = isset($data[$colIndices['category']]) && $colIndices['category'] !== false ? trim($data[$colIndices['category']]) : '';
                $supplierName = isset($data[$colIndices['supplier']]) && $colIndices['supplier'] !== false ? trim($data[$colIndices['supplier']]) : null;
                $notes = isset($data[$colIndices['notes']]) && $colIndices['notes'] !== false ? trim($data[$colIndices['notes']]) : null;

                // Vérifier les données obligatoires
                if (empty($productName)) {
                    $errorCount++;
                    $errors[] = "Ligne " . ($successCount + $errorCount) . " : Nom du produit manquant";
                    continue;
                }

                // Essayer de trouver la catégorie
                $categoryId = null;
                if (!empty($categoryName)) {
                    $catStmt->execute([$categoryName, $categoryName]);
                    $categoryResult = $catStmt->fetch(PDO::FETCH_ASSOC);
                    if ($categoryResult) {
                        $categoryId = $categoryResult['id'];
                    }
                }

                // Si catégorie non trouvée, utiliser la première catégorie
                if ($categoryId === null && !empty($categories)) {
                    $categoryId = $categories[0]['id'];
                }

                // Générer le code-barres
                $categoryCode = '';
                foreach ($categories as $cat) {
                    if ($cat['id'] == $categoryId) {
                        $categoryCode = $cat['code'];
                        break;
                    }
                }

                if (!empty($categoryCode)) {
                    if (isset($lastNumbers[$categoryCode])) {
                        $lastNumbers[$categoryCode]++;
                    } else {
                        $lastNumbers[$categoryCode] = 1;
                    }
                    $barcode = $categoryCode . '-' . str_pad($lastNumbers[$categoryCode], 5, '0', STR_PAD_LEFT);
                } else {
                    $errorCount++;
                    $errors[] = "Ligne " . ($successCount + $errorCount) . " : Impossible de générer un code-barres (catégorie inconnue)";
                    continue;
                }

                // Rechercher le fournisseur par nom
                $supplierId = null;
                if (!empty($supplierName)) {
                    $supplierStmt = $pdo->prepare("SELECT id FROM fournisseurs WHERE nom = ? LIMIT 1");
                    $supplierStmt->execute([$supplierName]);
                    $supplierResult = $supplierStmt->fetch(PDO::FETCH_ASSOC);
                    if ($supplierResult) {
                        $supplierId = $supplierResult['id'];
                    }
                }

                try {
                    // Insérer le produit
                    $stmt->execute([
                        ':barcode' => $barcode,
                        ':product_name' => $productName,
                        ':quantity' => $quantity,
                        ':unit' => $unit,
                        ':unit_price' => $price,
                        ':category' => $categoryId,
                        ':supplier_id' => $supplierId,
                        ':notes' => $notes
                    ]);

                    // Récupérer l'ID du produit inséré
                    $productId = $pdo->lastInsertId();

                    // Enregistrer le mouvement de stock comme une entrée initiale si la quantité > 0
                    if ($quantity > 0) {
                        $movementStmt = $pdo->prepare("INSERT INTO stock_movement 
                                                    (product_id, quantity, movement_type, provenance, destination, notes, created_at) 
                                                    VALUES (:product_id, :quantity, 'entry', 'Initial', 'Stock', 'Création initiale du produit', NOW())");
                        $movementStmt->execute([
                            ':product_id' => $productId,
                            ':quantity' => $quantity
                        ]);
                    }

                    // Logger l'ajout du produit
                    if ($logger) {
                        $productData = [
                            'id' => $productId,
                            'barcode' => $barcode,
                            'product_name' => $productName,
                            'quantity' => $quantity,
                            'unit' => $unit,
                            'unit_price' => $price,
                            'category' => $categoryId,
                            'supplier_id' => $supplierId,
                            'notes' => $notes
                        ];
                        $logger->logProductAdd($productData);
                    }

                    $successCount++;
                } catch (PDOException $e) {
                    $errorCount++;
                    $errors[] = "Ligne " . ($successCount + $errorCount) . " : " . $e->getMessage();
                }
            }

            if ($errorCount > 0) {
                $importMessage = "$successCount produits importés avec succès. $errorCount erreurs rencontrées: <br>" . implode('<br>', $errors);
                $importClass = "text-orange-600";
                if ($successCount > 0) {
                    $pdo->commit();
                } else {
                    $pdo->rollBack();
                }
            } else {
                $pdo->commit();
                $importMessage = "$successCount produits importés avec succès!";
                $importClass = "text-green-600";
            }

            // Logger l'importation
            if ($logger && $successCount > 0) {
                $logger->log(
                    'product_import_csv',
                    'batch',
                    null,
                    "Import CSV de produits",
                    [
                        'file' => $_FILES['csv_file']['name'],
                        'success_count' => $successCount,
                        'error_count' => $errorCount,
                        'errors' => $errors
                    ]
                );
            }
        }

        fclose($handle);
    } else {
        $importMessage = "Erreur lors de l'ouverture du fichier CSV";
        $importClass = "text-red-600";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <!-- Ajout de Dropzone.js pour l'upload d'images -->
    <link href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" rel="stylesheet" type="text/css" />
    <script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
    <style>
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
        }

        .card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .barcode-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
        }

        .barcode-item {
            text-align: center;
            width: 45%;
            margin-bottom: 30px;
        }

        .product-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 20px;
            font-weight: bold;
        }

        /* Animation de notification */
        @keyframes fadeInOut {
            0% {
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                opacity: 0;
            }
        }

        .notification {
            animation: fadeInOut 4s ease-in-out;
        }

        /* Style personnalisé pour Dropzone */
        .dropzone {
            border: 2px dashed #3b82f6;
            border-radius: 8px;
            background: #f0f9ff;
            min-height: 150px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dropzone:hover {
            background: #e0f2fe;
            border-color: #2563eb;
        }

        .dropzone .dz-message {
            margin: 2em 0;
        }

        .input-success {
            border-color: #10b981;
            background-color: #d1fae5;
        }

        .input-error {
            border-color: #ef4444;
            background-color: #fee2e2;
        }

        /* Style de badge pour les unités */
        .unit-badge {
            display: inline-block;
            background-color: #e0f2fe;
            color: #3b82f6;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 0.75rem;
            margin-right: 4px;
            margin-bottom: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .unit-badge:hover {
            background-color: #bfdbfe;
            color: #2563eb;
        }

        /* Ajustements pour l'impression */
        @media print {
            body * {
                visibility: hidden;
            }

            #barcodesPrintArea,
            #barcodesPrintArea * {
                visibility: visible;
            }

            #barcodesPrintArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .barcode-item {
                page-break-inside: avoid;
                width: 45%;
                margin-bottom: 30px;
            }

            .product-info {
                font-size: 18px;
            }
        }

        /* Style d'onglets */
        .tab-button {
            padding: 10px 16px;
            background-color: #f3f4f6;
            border-radius: 8px 8px 0 0;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.2s ease;
            border-bottom: 2px solid transparent;
        }

        .tab-button:hover {
            background-color: #e5e7eb;
            color: #4b5563;
        }

        .tab-button.active {
            background-color: white;
            color: #3b82f6;
            border-bottom: 2px solid #3b82f6;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #1f2937;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            font-weight: normal;
        }

        .tooltip .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #1f2937 transparent transparent transparent;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>

<body class="antialiased">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include_once 'sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col overflow-hidden">
            <?php include_once 'header.php'; ?>

            <main class="p-6 overflow-y-auto">
                <div class="max-w-7xl mx-auto">
                    <!-- En-tête de la page -->
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">Ajouter un produit</h1>
                            <p class="text-gray-600 mt-1">Ajoutez de nouveaux produits au système d'inventaire</p>
                        </div>
                        <div class="flex space-x-2">
                            <a href="liste_produits.php"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg flex items-center transition-colors">
                                <span class="material-icons-round text-base mr-1">list</span>
                                Liste des produits
                            </a>
                            <button id="printBarcodesBtn"
                                class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
                                <span class="material-icons-round text-base mr-1">print</span>
                                Imprimer codes-barres
                            </button>
                        </div>
                    </div>

                    <!-- Message de notification pour l'importation -->
                    <?php if (!empty($importMessage)): ?>
                        <div
                            class="mb-6 p-4 rounded-lg <?php echo $importClass === 'text-green-600' ? 'bg-green-100' : ($importClass === 'text-orange-600' ? 'bg-orange-100' : 'bg-red-100'); ?> notification">
                            <div class="flex items-start">
                                <span
                                    class="material-icons-round mr-2 mt-0.5 <?php echo $importClass === 'text-green-600' ? 'text-green-600' : ($importClass === 'text-orange-600' ? 'text-orange-600' : 'text-red-600'); ?>">
                                    <?php echo $importClass === 'text-green-600' ? 'check_circle' : 'error'; ?>
                                </span>
                                <div class="<?php echo $importClass; ?>">
                                    <?php echo $importMessage; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tabs navigation -->
                    <div class="flex border-b mb-6">
                        <button id="tab-manual" class="tab-button active">Ajout manuel</button>
                        <button id="tab-import" class="tab-button">Importation CSV</button>
                        <button id="tab-batch" class="tab-button">Ajout par lot</button>
                        <button id="tab-supplier" class="tab-button">Retour fournisseur</button>
                    </div>

                    <!-- Tab content: Ajout manuel -->
                    <div id="content-manual" class="tab-content active">
                        <div class="bg-white p-6 rounded-lg shadow mb-6">
                            <form id="productForm" class="space-y-6">
                                <div id="productEntries">
                                    <!-- Les entrées de produits seront ajoutées ici dynamiquement -->
                                </div>
                                <div class="flex justify-between items-center">
                                    <div class="flex space-x-2">
                                        <button type="button" id="addProductBtn"
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
                                            <span class="material-icons-round text-base mr-1">add_circle</span>
                                            Ajouter un produit
                                        </button>
                                    </div>
                                    <button type="submit"
                                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
                                        <span class="material-icons-round text-base mr-1">save</span>
                                        Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tab content: Importation CSV -->
                    <div id="content-import" class="tab-content">
                        <div class="bg-white p-6 rounded-lg shadow mb-6">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-2">Importer des produits depuis un fichier CSV</h3>
                                <p class="text-gray-600 mb-4">Téléchargez le modèle CSV, remplissez-le, puis importez-le
                                    pour ajouter plusieurs produits à la fois.</p>

                                <div class="flex space-x-4 mb-6">
                                    <a href="templates/product_import_template.csv" download
                                        class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-4 py-2 rounded-lg flex items-center transition-colors">
                                        <span class="material-icons-round text-base mr-1">download</span>
                                        Télécharger le modèle CSV
                                    </a>
                                    <div class="tooltip">
                                        <button type="button"
                                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center transition-colors">
                                            <span class="material-icons-round text-base mr-1">help_outline</span>
                                            Aide
                                        </button>
                                        <span class="tooltiptext">
                                            Le fichier CSV doit contenir les colonnes suivantes :
                                            'nom', 'quantite', 'unite', 'prix' (optionnel), 'categorie' (optionnel),
                                            'fournisseur' (optionnel), 'notes' (optionnel)
                                        </span>
                                    </div>
                                </div>

                                <form action="" method="post" enctype="multipart/form-data" class="space-y-4">
                                    <div class="border border-dashed border-blue-300 rounded-lg p-4 bg-blue-50">
                                        <div class="flex items-center justify-center w-full">
                                            <label for="csv_file"
                                                class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-lg cursor-pointer bg-blue-50 border-blue-300 hover:bg-blue-100">
                                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                    <span
                                                        class="material-icons-round text-blue-500 text-3xl mb-2">cloud_upload</span>
                                                    <p class="mb-2 text-sm text-blue-700"><span
                                                            class="font-semibold">Cliquez pour sélectionner</span> ou
                                                        glissez et déposez</p>
                                                    <p class="text-xs text-blue-500">CSV uniquement (max 5 MB)</p>
                                                </div>
                                                <input id="csv_file" name="csv_file" type="file" class="hidden"
                                                    accept=".csv" />
                                            </label>
                                        </div>
                                        <div id="file-selected" class="mt-3 text-center text-sm text-blue-700 hidden">
                                            Fichier sélectionné: <span id="file-name" class="font-medium"></span>
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit"
                                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
                                            <span class="material-icons-round text-base mr-1">upload_file</span>
                                            Importer les produits
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tab content: Ajout par lot -->
                    <div id="content-batch" class="tab-content">
                        <div class="bg-white p-6 rounded-lg shadow mb-6">
                            <h3 class="text-lg font-semibold mb-4">Ajout rapide par lot</h3>
                            <p class="text-gray-600 mb-4">Ajoutez rapidement plusieurs produits similaires en une seule
                                fois.</p>

                            <form id="batchForm" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-700">Catégorie</label>
                                        <select id="batch-category"
                                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            required>
                                            <option value="">Sélectionnez une catégorie</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['id'] ?>" data-code="<?= $category['code'] ?>">
                                                    <?= htmlspecialchars($category['libelle']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-700">Fournisseur
                                            (optionnel)</label>
                                        <select id="batch-supplier"
                                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">Sélectionnez un fournisseur</option>
                                            <?php foreach ($fournisseurs as $fournisseur): ?>
                                                <option value="<?= $fournisseur['id'] ?>">
                                                    <?= htmlspecialchars($fournisseur['nom']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-700">Unité</label>
                                        <input type="text" id="batch-unit"
                                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="ex: kg, litre, pièce" required>
                                        <div class="mt-2 flex flex-wrap">
                                            <?php foreach (array_slice($units, 0, 8) as $unit): ?>
                                                <span class="unit-badge unit-option"><?= htmlspecialchars($unit) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-700">Prix unitaire par
                                            défaut (optionnel)</label>
                                        <input type="number" id="batch-price" step="0.01"
                                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            value="0">
                                        <div class="mt-1 text-xs text-gray-500">Laissez à 0 si inconnu</div>
                                    </div>
                                </div>

                                <div class="border-t pt-4">
                                    <h4 class="font-medium mb-3">Produits par lot</h4>
                                    <div class="mb-4">
                                        <label class="block mb-2 text-sm font-medium text-gray-700">
                                            Entrez un produit par ligne avec le format: Nom du produit | Quantité
                                            <span class="text-gray-500 text-xs">(La quantité est optionnelle, 0 par
                                                défaut)</span>
                                        </label>
                                        <textarea id="batch-products" rows="8"
                                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="Tournevis cruciforme | 5&#10;Marteau | 3&#10;Clé à molette"></textarea>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" id="batch-submit"
                                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
                                        <span class="material-icons-round text-base mr-1">playlist_add</span>
                                        Ajouter le lot de produits
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tab content: Retour fournisseur -->
                    <div id="content-supplier" class="tab-content">
                        <div class="bg-white p-6 rounded-lg shadow mb-6">
                            <h3 class="text-lg font-semibold mb-4">Retour produit au fournisseur</h3>
                            <p class="text-gray-600 mb-4">Enregistrez un retour de produit vers un fournisseur.</p>

                            <form id="supplierReturnForm" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-700">Rechercher un
                                            produit</label>
                                        <div class="relative">
                                            <input type="text" id="search-product"
                                                class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                placeholder="Nom du produit ou code-barres">
                                            <button type="button" id="search-product-btn"
                                                class="absolute right-2 top-2 text-blue-500 hover:text-blue-700">
                                                <span class="material-icons-round">search</span>
                                            </button>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">Commencez à taper pour rechercher un
                                            produit</div>
                                    </div>

                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-700">Fournisseur <span
                                                class="text-red-500">*</span></label>
                                        <select id="return-supplier"
                                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            required>
                                            <option value="">Sélectionnez un fournisseur</option>
                                            <?php foreach ($fournisseurs as $fournisseur): ?>
                                                <option value="<?= $fournisseur['id'] ?>">
                                                    <?= htmlspecialchars($fournisseur['nom']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-700">Quantité à retourner
                                            <span class="text-red-500">*</span></label>
                                        <input type="number" id="return-quantity" min="1"
                                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            required>
                                        <div id="max-quantity" class="mt-1 text-xs text-red-500 hidden">
                                            Quantité maximale disponible: <span class="font-medium"></span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-700">Motif du retour
                                            <span class="text-red-500">*</span></label>
                                        <select id="return-reason"
                                            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            required>
                                            <option value="">Sélectionnez un motif</option>
                                            <option value="defectueux">Produit défectueux</option>
                                            <option value="erreur_livraison">Erreur de livraison</option>
                                            <option value="non_conforme">Produit non conforme</option>
                                            <option value="perime">Produit périmé</option>
                                            <option value="surplus">Surplus de stock</option>
                                            <option value="autre">Autre raison</option>
                                        </select>
                                    </div>
                                </div>

                                <div id="other-reason-container" class="hidden">
                                    <label class="block mb-2 text-sm font-medium text-gray-700">Précisez le motif <span
                                            class="text-red-500">*</span></label>
                                    <input type="text" id="other-reason"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-700">Commentaire
                                        (optionnel)</label>
                                    <textarea id="return-comment" rows="3"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="Informations complémentaires sur le retour..."></textarea>
                                </div>

                                <div id="selected-product-container" class="border p-4 rounded-lg bg-gray-50 hidden">
                                    <h4 class="font-medium mb-3">Produit sélectionné</h4>
                                    <div class="grid grid-cols-2 gap-2 text-sm">
                                        <div>
                                            <span class="font-medium">Nom:</span>
                                            <span id="selected-product-name">-</span>
                                        </div>
                                        <div>
                                            <span class="font-medium">Code:</span>
                                            <span id="selected-product-barcode">-</span>
                                        </div>
                                        <div>
                                            <span class="font-medium">Stock actuel:</span>
                                            <span id="selected-product-quantity">-</span>
                                        </div>
                                        <div>
                                            <span class="font-medium">Catégorie:</span>
                                            <span id="selected-product-category">-</span>
                                        </div>
                                    </div>
                                    <input type="hidden" id="selected-product-id">
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" id="return-submit"
                                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
                                        <span class="material-icons-round text-base mr-1">assignment_return</span>
                                        Enregistrer le retour
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Zone d'impression des codes-barres -->
    <div id="barcodesPrintArea" style="display: none;">
        <div class="barcode-container p-4"></div>
    </div>

    <!-- Modal de recherche de produit -->
    <div id="searchProductModal"
        class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 max-w-3xl w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Rechercher un produit</h3>
                <button id="closeSearchModal" class="text-gray-500 hover:text-gray-700">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="mb-4">
                <div class="relative">
                    <input type="text" id="modal-search-input"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Nom du produit ou code-barres">
                    <button type="button" id="modal-search-btn"
                        class="absolute right-2 top-2 text-blue-500 hover:text-blue-700">
                        <span class="material-icons-round">search</span>
                    </button>
                </div>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-2 px-4 text-left text-sm font-medium text-gray-600">Code</th>
                            <th class="py-2 px-4 text-left text-sm font-medium text-gray-600">Nom</th>
                            <th class="py-2 px-4 text-left text-sm font-medium text-gray-600">Quantité</th>
                            <th class="py-2 px-4 text-left text-sm font-medium text-gray-600">Action</th>
                        </tr>
                    </thead>
                    <tbody id="search-results-table"></tbody>
                </table>
                <div id="no-results" class="py-4 text-center text-gray-500 hidden">Aucun résultat trouvé</div>
                <div id="loading-results" class="py-4 text-center text-gray-500">
                    <span class="material-icons-round animate-spin inline-block mr-2">refresh</span>
                    Recherche en cours...
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Modal de confirmation de succès -->
    <div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <div class="text-center">
                <span class="material-icons-round text-green-500 text-5xl mb-4">check_circle</span>
                <h3 class="text-xl font-bold mb-2">Succès!</h3>
                <p class="mb-6 text-gray-600" id="successMessage">Les produits ont été ajoutés avec succès.</p>
                <div class="flex justify-center space-x-3">
                    <button id="closeSuccessModal"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg transition-colors">
                        Fermer
                    </button>
                    <button id="printSuccessModal"
                        class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg flex items-center justify-center transition-colors">
                        <span class="material-icons-round text-base mr-1">print</span>
                        Imprimer codes-barres
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de recherche de produits similaires -->
    <div id="similarProductsModal"
        class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 max-w-3xl w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Produits similaires trouvés</h3>
                <button id="closeSimilarModal" class="text-gray-500 hover:text-gray-700">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <p class="mb-4 text-gray-600">Des produits similaires existent déjà dans la base de données. Voulez-vous
                mettre à jour la quantité d'un produit existant au lieu d'en créer un nouveau?</p>
            <div class="max-h-96 overflow-y-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-2 px-4 text-left text-sm font-medium text-gray-600">Code</th>
                            <th class="py-2 px-4 text-left text-sm font-medium text-gray-600">Nom</th>
                            <th class="py-2 px-4 text-left text-sm font-medium text-gray-600">Quantité actuelle</th>
                            <th class="py-2 px-4 text-left text-sm font-medium text-gray-600">Action</th>
                        </tr>
                    </thead>
                    <tbody id="similarProductsTable"></tbody>
                </table>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button id="createNewProduct"
                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                    Créer un nouveau produit
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let productCount = 0;
        let lastNumbers = <?php echo json_encode($lastNumbers); ?>;
        let formBarcodes = {}; // Objet pour stocker les codes-barres générés dans le formulaire
        let similarProductsData = null; // Pour stocker les produits similaires
        let currentProductEntry = null; // Pour garder une référence à l'entrée de produit en cours
        let selectedProduct = null; // Pour stocker le produit sélectionné pour un retour

        // Initialisation de la page
        document.addEventListener('DOMContentLoaded', function () {
            // Ajouter la première entrée de produit
            addProductEntry();

            // Gestionnaire pour le changement d'onglet
            document.querySelectorAll('.tab-button').forEach(button => {
                button.addEventListener('click', function () {
                    // Retirer la classe active de tous les onglets
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

                    // Ajouter la classe active à l'onglet cliqué
                    this.classList.add('active');
                    document.getElementById('content-' + this.id.split('-')[1]).classList.add('active');
                });
            });

            // Gestionnaire pour le formulaire d'ajout manuel
            document.getElementById('productForm').addEventListener('submit', handleProductFormSubmit);

            // Gestionnaire pour le bouton d'ajout de produit
            document.getElementById('addProductBtn').addEventListener('click', function () {
                addProductEntry();
            });

            // Gestionnaire pour le bouton d'impression des codes-barres
            document.getElementById('printBarcodesBtn').addEventListener('click', function () {
                printBarcodes();
            });

            // Gestionnaire pour le fichier CSV sélectionné
            document.getElementById('csv_file').addEventListener('change', function (e) {
                const fileName = e.target.files[0]?.name;
                if (fileName) {
                    document.getElementById('file-name').textContent = fileName;
                    document.getElementById('file-selected').classList.remove('hidden');
                } else {
                    document.getElementById('file-selected').classList.add('hidden');
                }
            });

            // Gestionnaire pour les badges d'unité
            document.querySelectorAll('.unit-option').forEach(badge => {
                badge.addEventListener('click', function () {
                    const unitInput = this.closest('div').querySelector('input[type="text"]');
                    if (unitInput) {
                        unitInput.value = this.textContent.trim();
                    }
                });
            });

            // Gestionnaire pour le formulaire d'ajout par lot
            document.getElementById('batchForm').addEventListener('submit', handleBatchFormSubmit);

            // Gestionnaire pour le formulaire de retour fournisseur
            document.getElementById('supplierReturnForm').addEventListener('submit', handleSupplierReturnFormSubmit);

            // Gestionnaire pour le motif de retour "Autre"
            document.getElementById('return-reason').addEventListener('change', function () {
                const otherReasonContainer = document.getElementById('other-reason-container');
                if (this.value === 'autre') {
                    otherReasonContainer.classList.remove('hidden');
                    document.getElementById('other-reason').setAttribute('required', 'required');
                } else {
                    otherReasonContainer.classList.add('hidden');
                    document.getElementById('other-reason').removeAttribute('required');
                }
            });

            // Gestionnaire pour la recherche de produit (retour fournisseur)
            document.getElementById('search-product-btn').addEventListener('click', function () {
                openSearchModal();
            });

            document.getElementById('search-product').addEventListener('focus', function () {
                openSearchModal();
            });

            // Gestionnaires pour le modal de recherche
            document.getElementById('closeSearchModal').addEventListener('click', function () {
                document.getElementById('searchProductModal').classList.add('hidden');
            });

            document.getElementById('modal-search-btn').addEventListener('click', function () {
                const searchTerm = document.getElementById('modal-search-input').value.trim();
                if (searchTerm.length > 2) {
                    searchProducts(searchTerm);
                }
            });

            document.getElementById('modal-search-input').addEventListener('keyup', function (e) {
                if (e.key === 'Enter') {
                    const searchTerm = this.value.trim();
                    if (searchTerm.length > 2) {
                        searchProducts(searchTerm);
                    }
                }
            });

            // Gestionnaires pour les modals
            document.getElementById('closeSuccessModal').addEventListener('click', function () {
                document.getElementById('successModal').classList.add('hidden');
            });

            document.getElementById('printSuccessModal').addEventListener('click', function () {
                document.getElementById('successModal').classList.add('hidden');
                printBarcodes();
            });

            document.getElementById('closeSimilarModal').addEventListener('click', function () {
                document.getElementById('similarProductsModal').classList.add('hidden');
            });

            document.getElementById('createNewProduct').addEventListener('click', function () {
                document.getElementById('similarProductsModal').classList.add('hidden');
                // Continuer avec la création du produit
                if (currentProductEntry) {
                    submitSingleProduct(currentProductEntry);
                }
            });
        });

        // Fonction pour ouvrir la modal de recherche
        function openSearchModal() {
            document.getElementById('modal-search-input').value = document.getElementById('search-product').value;
            document.getElementById('search-results-table').innerHTML = '';
            document.getElementById('no-results').classList.add('hidden');
            document.getElementById('loading-results').classList.add('hidden');
            document.getElementById('searchProductModal').classList.remove('hidden');

            // Focus sur le champ de recherche
            setTimeout(() => {
                document.getElementById('modal-search-input').focus();
            }, 100);

            // Si un terme de recherche est déjà entré et fait plus de 2 caractères
            const searchTerm = document.getElementById('modal-search-input').value.trim();
            if (searchTerm.length > 2) {
                searchProducts(searchTerm);
            }
        }

        // Fonction pour rechercher des produits
        function searchProducts(searchTerm) {
            document.getElementById('loading-results').classList.remove('hidden');
            document.getElementById('no-results').classList.add('hidden');

            fetch(`api.php?action=searchProducts&term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading-results').classList.add('hidden');

                    if (data.success && data.products && data.products.length > 0) {
                        renderSearchResults(data.products);
                    } else {
                        document.getElementById('search-results-table').innerHTML = '';
                        document.getElementById('no-results').classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la recherche de produits:', error);
                    document.getElementById('loading-results').classList.add('hidden');
                    document.getElementById('no-results').classList.remove('hidden');
                });
        }

        // Fonction pour afficher les résultats de recherche
        function renderSearchResults(products) {
            const tbody = document.getElementById('search-results-table');
            tbody.innerHTML = '';

            products.forEach(product => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50';
                row.innerHTML = `
                    <td class="py-2 px-4 text-sm">${product.barcode}</td>
                    <td class="py-2 px-4 text-sm">${product.product_name}</td>
                    <td class="py-2 px-4 text-sm">${product.quantity} ${product.unit}</td>
                    <td class="py-2 px-4">
                        <button type="button" class="select-product bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded text-sm">
                            Sélectionner
                        </button>
                    </td>
                `;
                tbody.appendChild(row);

                const selectButton = row.querySelector('.select-product');
                selectButton.addEventListener('click', function () {
                    selectProductForReturn(product);
                });
            });
        }

        // Fonction pour sélectionner un produit pour le retour
        function selectProductForReturn(product) {
            selectedProduct = product;

            // Mettre à jour les informations affichées
            document.getElementById('selected-product-name').textContent = product.product_name;
            document.getElementById('selected-product-barcode').textContent = product.barcode;
            document.getElementById('selected-product-quantity').textContent = `${product.quantity} ${product.unit}`;

            // Récupérer le nom de la catégorie
            fetch(`api.php?action=getCategoryName&id=${product.category}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('selected-product-category').textContent = data.category_name;
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                });

            document.getElementById('selected-product-id').value = product.id;
            document.getElementById('selected-product-container').classList.remove('hidden');

            // Mettre à jour la quantité maximale
            const maxQuantityElement = document.getElementById('max-quantity');
            maxQuantityElement.querySelector('span').textContent = product.quantity;
            maxQuantityElement.classList.remove('hidden');

            // Limiter la quantité à retourner
            const quantityInput = document.getElementById('return-quantity');
            quantityInput.setAttribute('max', product.quantity);
            quantityInput.value = Math.min(quantityInput.value || 1, product.quantity);

            // Fermer le modal de recherche
            document.getElementById('searchProductModal').classList.add('hidden');

            // Mettre à jour le champ de recherche
            document.getElementById('search-product').value = product.product_name;
        }

        // Fonction pour ajouter une entrée de produit au formulaire
        function addProductEntry() {
            productCount++;
            const productEntry = document.createElement('div');
            productEntry.className = 'product-entry mb-8 p-6 border rounded-lg shadow-sm';
            productEntry.dataset.entryId = 'product-' + productCount;

            productEntry.innerHTML = `
                <div class="flex justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Produit ${productCount}</h3>
                    ${productCount > 1 ? `<button type="button" class="remove-product text-red-500 hover:text-red-700 p-1 rounded transition-colors">
                        <span class="material-icons-round">delete</span>
                    </button>` : ''}
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700">Nom du produit <span class="text-red-500">*</span></label>
                                <input type="text" name="product_name[]" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 product-name" required>
                                <div class="mt-1 text-xs text-gray-500">Saisissez un nom descriptif et précis</div>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700">Catégorie <span class="text-red-500">*</span></label>
                                <select name="category[]" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 category-select" required>
                                    <option value="">Sélectionnez une catégorie</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" data-code="<?= $category['code'] ?>"><?= htmlspecialchars($category['libelle']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700">Quantité initiale</label>
                                <input type="number" name="quantity[]" value="0" min="0" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <div class="mt-1 text-xs text-gray-500">Quantité actuellement en stock</div>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700">Unité <span class="text-red-500">*</span></label>
                                <input type="text" name="unit[]" placeholder="ex: kg, litre, pièce" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <div class="flex flex-wrap mt-1">
                                    <?php foreach (array_slice($units, 0, 4) as $unit): ?>
                                    <span class="unit-option cursor-pointer text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded mr-1 mb-1 hover:bg-gray-200"><?= htmlspecialchars($unit) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700">Prix unitaire</label>
                                <input type="number" step="0.01" name="unit_price[]" value="0" min="0" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <div class="mt-1 text-xs text-gray-500">Optionnel - peut être ajouté ultérieurement</div>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-700">Fournisseur</label>
                                <select name="supplier_id[]" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Sélectionnez un fournisseur</option>
                                    <?php foreach ($fournisseurs as $fournisseur): ?>
                                    <option value="<?= $fournisseur['id'] ?>"><?= htmlspecialchars($fournisseur['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block mb-2 text-sm font-medium text-gray-700">Notes (optionnel)</label>
                                <textarea name="notes[]" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Informations complémentaires, emplacement, etc."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-1 flex flex-col items-center justify-center border rounded-lg p-4 bg-gray-50">
                        <div class="text-center mb-3">
                            <p class="text-sm font-medium text-gray-700 mb-1">Code-barres</p>
                            <p class="text-xs text-gray-500 mb-3">Généré automatiquement après sélection de la catégorie</p>
                        </div>
                        <div class="barcode-item flex flex-col items-center justify-center">
                            <canvas class="product-barcode w-full"></canvas>
                            <p class="text-sm mt-2 barcode-text text-gray-700"></p>
                        </div>
                        <input type="hidden" name="barcode[]" class="barcode-input">
                    </div>
                </div>
            `;

            document.getElementById('productEntries').appendChild(productEntry);

            // Ajouter un gestionnaire pour supprimer l'entrée de produit
            const removeButton = productEntry.querySelector('.remove-product');
            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    productEntry.remove();
                });
            }

            // Ajouter un gestionnaire pour les unités proposées
            const unitOptions = productEntry.querySelectorAll('.unit-option');
            const unitInput = productEntry.querySelector('input[name="unit[]"]');

            unitOptions.forEach(option => {
                option.addEventListener('click', function () {
                    unitInput.value = this.textContent.trim();
                });
            });

            // Ajouter un gestionnaire pour la recherche de produits similaires
            const productNameInput = productEntry.querySelector('.product-name');
            productNameInput.addEventListener('blur', function () {
                if (this.value.trim().length > 2) {
                    checkSimilarProducts(this.value.trim(), productEntry);
                }
            });

            // Ajouter un écouteur d'événements pour la sélection de catégorie
            const categorySelect = productEntry.querySelector('.category-select');
            categorySelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const categoryCode = selectedOption.getAttribute('data-code');
                    const newBarcode = generateBarcode(categoryCode);
                    const barcodeInput = productEntry.querySelector('.barcode-input');
                    const barcodeText = productEntry.querySelector('.barcode-text');
                    const canvas = productEntry.querySelector('.product-barcode');

                    barcodeInput.value = newBarcode;
                    barcodeText.textContent = newBarcode;

                    JsBarcode(canvas, newBarcode, {
                        format: "CODE128",
                        width: 2,
                        height: 80,
                        displayValue: false
                    });
                }
            });
        }

        // Fonction pour générer un code-barres unique
        function generateBarcode(categoryCode) {
            if (formBarcodes[categoryCode]) {
                formBarcodes[categoryCode]++;
            } else {
                formBarcodes[categoryCode] = Math.max(lastNumbers[categoryCode] || 0,
                    Object.values(formBarcodes).reduce((max, val) => Math.max(max, val), 0)) + 1;
            }
            return `${categoryCode}-${formBarcodes[categoryCode].toString().padStart(5, '0')}`;
        }

        // Fonction pour vérifier les produits similaires
        function checkSimilarProducts(productName, productEntry) {
            // Requête AJAX pour vérifier les produits similaires
            fetch(`api.php?action=checkSimilarProducts&name=${encodeURIComponent(productName)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.products && data.products.length > 0) {
                        // Afficher le modal des produits similaires
                        similarProductsData = data.products;
                        currentProductEntry = productEntry;

                        const tbody = document.getElementById('similarProductsTable');
                        tbody.innerHTML = '';

                        data.products.forEach(product => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td class="py-2 px-4 text-sm">${product.barcode}</td>
                                <td class="py-2 px-4 text-sm">${product.product_name}</td>
                                <td class="py-2 px-4 text-sm">${product.quantity} ${product.unit}</td>
                                <td class="py-2 px-4">
                                    <button type="button" data-product-id="${product.id}" class="use-existing-product bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded text-sm">
                                        Mettre à jour
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });

                        // Ajouter des gestionnaires d'événements pour les boutons de mise à jour
                        document.querySelectorAll('.use-existing-product').forEach(button => {
                            button.addEventListener('click', function () {
                                const productId = this.getAttribute('data-product-id');
                                updateExistingProduct(productId, productEntry);
                            });
                        });

                        document.getElementById('similarProductsModal').classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la vérification des produits similaires:', error);
                });
        }

        // Fonction pour mettre à jour un produit existant
        function updateExistingProduct(productId, productEntry) {
            const quantityInput = productEntry.querySelector('input[name="quantity[]"]');
            const quantity = parseInt(quantityInput.value) || 0;

            if (quantity <= 0) {
                alert('Veuillez entrer une quantité supérieure à zéro pour mettre à jour le stock.');
                return;
            }

            // Trouver le produit dans les données similaires
            const product = similarProductsData.find(p => p.id == productId);
            if (!product) return;

            // Requête AJAX pour mettre à jour le produit existant
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'updateProductQuantity',
                    product_id: productId,
                    quantity: quantity
                }),
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Fermer le modal
                        document.getElementById('similarProductsModal').classList.add('hidden');

                        // Afficher un message de succès
                        document.getElementById('successMessage').textContent = `Le stock du produit "${product.product_name}" a été mis à jour avec succès.`;
                        document.getElementById('successModal').classList.remove('hidden');

                        // Supprimer l'entrée de produit du formulaire
                        productEntry.remove();

                        // Si c'était la seule entrée, en ajouter une nouvelle
                        if (document.querySelectorAll('.product-entry').length === 0) {
                            addProductEntry();
                        }
                    } else {
                        alert('Erreur lors de la mise à jour du produit: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de la mise à jour du produit.');
                });
        }

        // Fonction pour gérer la soumission du formulaire d'ajout manuel
        function handleProductFormSubmit(e) {
            e.preventDefault();

            const productEntries = document.querySelectorAll('.product-entry');

            // Vérifier si toutes les entrées ont un code-barres
            let allValid = true;
            productEntries.forEach(entry => {
                const barcode = entry.querySelector('.barcode-input').value;
                if (!barcode) {
                    const categorySelect = entry.querySelector('.category-select');
                    categorySelect.classList.add('input-error');
                    allValid = false;
                }
            });

            if (!allValid) {
                alert('Veuillez sélectionner une catégorie pour chaque produit afin de générer un code-barres.');
                return;
            }

            // Traiter chaque entrée de produit individuellement
            let promises = [];

            productEntries.forEach(entry => {
                promises.push(submitSingleProduct(entry));
            });

            // Attendre que toutes les soumissions soient terminées
            Promise.all(promises)
                .then(results => {
                    const successCount = results.filter(r => r.success).length;

                    if (successCount > 0) {
                        // Afficher le message de succès
                        document.getElementById('successMessage').textContent = `${successCount} produit(s) ajouté(s) avec succès.`;
                        document.getElementById('successModal').classList.remove('hidden');

                        // Réinitialiser le formulaire
                        document.getElementById('productEntries').innerHTML = '';
                        addProductEntry();
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de l\'ajout des produits:', error);
                    alert('Une erreur est survenue lors de l\'ajout des produits.');
                });
        }

        // Fonction pour soumettre un seul produit
        function submitSingleProduct(productEntry) {
            const product = {
                barcode: productEntry.querySelector('.barcode-input').value,
                name: productEntry.querySelector('input[name="product_name[]"]').value,
                quantity: productEntry.querySelector('input[name="quantity[]"]').value,
                unit: productEntry.querySelector('input[name="unit[]"]').value,
                price: productEntry.querySelector('input[name="unit_price[]"]').value,
                category: productEntry.querySelector('select[name="category[]"]').value,
                supplier_id: productEntry.querySelector('select[name="supplier_id[]"]').value,
                notes: productEntry.querySelector('textarea[name="notes[]"]').value
            };

            return fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'addProducts',
                    products: [product]
                }),
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        return { success: true, product: product };
                    } else {
                        alert('Erreur lors de l\'ajout du produit: ' + data.error);
                        return { success: false, error: data.error };
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    return { success: false, error: 'Erreur réseau' };
                });
        }

        // Fonction pour gérer la soumission du formulaire d'ajout par lot
        function handleBatchFormSubmit(e) {
            e.preventDefault();

            const categorySelect = document.getElementById('batch-category');
            const selectedOption = categorySelect.options[categorySelect.selectedIndex];

            if (!selectedOption || !selectedOption.value) {
                alert('Veuillez sélectionner une catégorie pour les produits.');
                return;
            }

            const categoryCode = selectedOption.getAttribute('data-code');
            const unit = document.getElementById('batch-unit').value.trim();
            const price = document.getElementById('batch-price').value;
            const supplierId = document.getElementById('batch-supplier').value;
            const productsText = document.getElementById('batch-products').value.trim();

            if (!unit) {
                alert('Veuillez spécifier une unité pour les produits.');
                return;
            }

            if (!productsText) {
                alert('Veuillez entrer au moins un produit.');
                return;
            }

            // Analyser le texte des produits
            const productLines = productsText.split('\n');
            const products = [];

            productLines.forEach(line => {
                if (line.trim()) {
                    const parts = line.split('|');
                    const productName = parts[0].trim();
                    const quantity = parts.length > 1 ? parseInt(parts[1].trim()) || 0 : 0;

                    if (productName) {
                        const barcode = generateBarcode(categoryCode);

                        products.push({
                            barcode: barcode,
                            name: productName,
                            quantity: quantity,
                            unit: unit,
                            price: price,
                            category: categorySelect.value,
                            supplier_id: supplierId,
                            notes: ''
                        });
                    }
                }
            });

            if (products.length === 0) {
                alert('Aucun produit valide n\'a été trouvé.');
                return;
            }

            // Envoyer la requête
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'addProducts',
                    products: products
                }),
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Afficher le message de succès
                        document.getElementById('successMessage').textContent = `${products.length} produit(s) ajouté(s) avec succès.`;
                        document.getElementById('successModal').classList.remove('hidden');

                        // Réinitialiser le formulaire
                        document.getElementById('batch-products').value = '';
                    } else {
                        alert('Erreur lors de l\'ajout des produits: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de l\'ajout des produits.');
                });
        }

        // Fonction pour gérer la soumission du formulaire de retour fournisseur
        function handleSupplierReturnFormSubmit(e) {
            e.preventDefault();

            // Vérifier que tous les champs requis sont remplis
            const productId = document.getElementById('selected-product-id').value;
            const supplierId = document.getElementById('return-supplier').value;
            const quantity = parseInt(document.getElementById('return-quantity').value);
            const reason = document.getElementById('return-reason').value;
            const otherReason = document.getElementById('other-reason').value;
            const comment = document.getElementById('return-comment').value;

            if (!productId) {
                alert('Veuillez sélectionner un produit.');
                return;
            }

            if (!supplierId) {
                alert('Veuillez sélectionner un fournisseur.');
                return;
            }

            if (isNaN(quantity) || quantity < 1) {
                alert('Veuillez entrer une quantité valide (supérieure à 0).');
                return;
            }

            if (!reason) {
                alert('Veuillez sélectionner un motif de retour.');
                return;
            }

            if (reason === 'autre' && !otherReason) {
                alert('Veuillez préciser le motif de retour.');
                return;
            }

            // Vérifier que la quantité n'excède pas le stock disponible
            if (selectedProduct && quantity > selectedProduct.quantity) {
                alert(`La quantité ne peut pas excéder le stock disponible (${selectedProduct.quantity}).`);
                return;
            }

            // Préparer les données pour la requête
            const returnData = {
                product_id: productId,
                supplier_id: supplierId,
                quantity: quantity,
                reason: reason,
                other_reason: otherReason,
                comment: comment
            };

            // Envoyer la requête
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'returnToSupplier',
                    data: returnData
                }),
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Afficher le message de succès
                        document.getElementById('successMessage').textContent = `Le retour fournisseur a été enregistré avec succès.`;
                        document.getElementById('successModal').classList.remove('hidden');

                        // Réinitialiser le formulaire
                        document.getElementById('supplierReturnForm').reset();
                        document.getElementById('selected-product-container').classList.add('hidden');
                        document.getElementById('max-quantity').classList.add('hidden');
                        document.getElementById('other-reason-container').classList.add('hidden');
                        selectedProduct = null;
                    } else {
                        alert('Erreur lors de l\'enregistrement du retour: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de l\'enregistrement du retour.');
                });
        }

        // Fonction pour imprimer les codes-barres
        function printBarcodes() {
            const barcodeContainer = document.querySelector('#barcodesPrintArea .barcode-container');
            barcodeContainer.innerHTML = '';

            const products = document.querySelectorAll('.product-entry');
            let validProducts = false;

            products.forEach((product) => {
                const barcode = product.querySelector('input[name="barcode[]"]').value;
                if (!barcode) return;

                validProducts = true;
                const productName = product.querySelector('input[name="product_name[]"]').value;
                const barcodeItem = document.createElement('div');
                barcodeItem.className = 'barcode-item';

                const productInfo = document.createElement('div');
                productInfo.className = 'product-info';
                productInfo.textContent = `${productName} (${barcode})`;

                barcodeItem.appendChild(productInfo);

                const canvas = document.createElement('canvas');
                barcodeItem.appendChild(canvas);

                barcodeContainer.appendChild(barcodeItem);

                JsBarcode(canvas, barcode, {
                    format: "CODE128",
                    width: 2,
                    height: 100,
                    displayValue: false
                });
            });

            if (!validProducts) {
                alert('Veuillez d\'abord créer au moins un produit avec un code-barres.');
                return;
            }

            // Afficher la zone d'impression et lancer l'impression
            document.getElementById('barcodesPrintArea').style.display = 'block';
            window.print();
            // Cacher la zone d'impression après l'impression
            setTimeout(() => {
                document.getElementById('barcodesPrintArea').style.display = 'none';
            }, 100);
        }
    </script>
</body>

</html>