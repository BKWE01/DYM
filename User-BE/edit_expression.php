<?php
// Fichier: /DYM MANUFACTURE/expressions_besoins/User-BE/edit_expression.php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Connexion à la base de données
include_once '../database/connection.php';

// Vérifier si l'utilisateur est un super_admin
try {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT role FROM users_exp WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData || $userData['role'] !== 'super_admin') {
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

// Vérifier si un ID a été fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'];

// Récupérer les informations du projet
try {
    $stmt = $pdo->prepare("
    SELECT * FROM identification_projet
    WHERE idExpression = :id
  ");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();
    $projectInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$projectInfo) {
        header("Location: dashboard.php");
        exit();
    }

    // Récupérer les expressions de besoin
    $stmt = $pdo->prepare("
    SELECT e.*, 
           COALESCE(p.quantity, 0) as stock_quantity,
           COALESCE(p.quantity_reserved, 0) as total_reserved,
           COALESCE(p.unit_price, 0) as current_price
    FROM expression_dym e
    LEFT JOIN products p ON e.designation = p.product_name
    WHERE e.idExpression = :id
    ORDER BY e.type, e.id
  ");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();
    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer toutes les désignations de produits pour l'autocomplétion
    $stmt = $pdo->prepare("
  SELECT p.product_name, p.unit, c.libelle as type, p.unit_price, p.quantity, p.quantity_reserved
  FROM products p
  LEFT JOIN categories c ON p.category = c.id
  ORDER BY p.product_name
");

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Expression de Besoin | Bureau d'Études</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

    .container {
        width: 93%;
        margin: 30px auto;
        padding: 20px;
    }

    .title {
        text-align: left;
        margin-bottom: 20px;
        font-size: 20px;
        font-weight: bold;
    }

    .form-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    .form-table th,
    .form-table td {
        padding: 8px;
        border: 1px solid #ccc;
        text-align: left;
        vertical-align: middle;
    }

    .form-table th {
        background-color: #f9f9f9;
        font-weight: bold;
    }

    .input-text {
        width: 100%;
        padding: 8px;
        border: none;
        box-sizing: border-box;
        background-color: #f3f4f6;
    }

    .input-text:focus {
        outline: none;
        background-color: #e9eef6;
    }

    .table-container {
        position: relative;
        width: 90%;
        margin: 30px auto;
    }

    .table-container table {
        width: 100%;
        border-collapse: collapse;
    }

    .table-container th,
    .table-container td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }

    .table-container th {
        background-color: #f7fafc;
        font-weight: 600;
    }

    .table-container .narrow {
        width: 150px;
    }

    .actions-column {
        width: 80px;
        text-align: center;
    }

    .delete-btn {
        cursor: pointer;
        background-color: #e53e3e;
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        transition: background-color 0.2s;
    }

    .delete-btn:hover {
        background-color: #c53030;
    }

    .btn {
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-primary {
        background-color: #3b82f6;
        color: white;
    }

    .btn-primary:hover {
        background-color: #2563eb;
    }

    .btn-success {
        background-color: #10b981;
        color: white;
    }

    .btn-success:hover {
        background-color: #059669;
    }

    .btn-cancel {
        background-color: #e5e7eb;
        color: #4b5563;
    }

    .btn-cancel:hover {
        background-color: #d1d5db;
    }

    .actions-bar {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        padding: 0 5%;
    }

    .autocomplete-list {
        border: 1px solid #d1d5db;
        background-color: #fff;
        border-radius: 4px;
        max-height: 200px;
        overflow-y: auto;
        position: absolute;
        width: 100%;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-top: 2px;
    }

    .autocomplete-item {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f3f4f6;
    }

    .autocomplete-item:hover {
        background-color: #f3f4f6;
    }

    .form-table td {
        position: relative;
    }

    .product-status {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 20px;
    }

    .stock-info {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }

    .stock-warning {
        color: #f59e0b;
    }

    .stock-error {
        color: #ef4444;
    }

    .stock-success {
        color: #10b981;
    }

    .spinner {
        display: none;
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

    .loading .spinner {
        display: inline-block;
    }

    /* Styles pour les inputs de quantité décimale */
    .quantity-input {
        text-align: right;
        font-weight: 500;
    }

    .quantity-input:focus {
        background-color: #fef3c7;
        outline: 2px solid #f59e0b;
    }

    /* Info-bulle pour les décimaux */
    .decimal-info {
        position: relative;
        display: inline-block;
        margin-left: 4px;
        cursor: help;
    }

    .decimal-info:hover .tooltip {
        visibility: visible;
        opacity: 1;
    }

    .tooltip {
        visibility: hidden;
        opacity: 0;
        background-color: #374151;
        color: white;
        text-align: center;
        padding: 8px 12px;
        border-radius: 6px;
        position: absolute;
        z-index: 1;
        bottom: 125%;
        left: 50%;
        margin-left: -80px;
        font-size: 12px;
        transition: opacity 0.3s;
        width: 160px;
    }

    .tooltip::after {
        content: "";
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: #374151 transparent transparent transparent;
    }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../components/navbar.php'; ?>

        <main class="flex-1 p-6">
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4">
                <h1 class="text-xl font-bold">Modifier l'expression de besoin: <?php echo htmlspecialchars($id); ?></h1>
                <div class="mt-2 text-sm text-blue-600 bg-blue-50 p-3 rounded-lg">
                    <i class="material-icons text-sm mr-1">info</i>
                    <strong>Nouveau :</strong> Les quantités décimales sont désormais autorisées (ex: 2.5, 1.25, etc.)
                </div>
            </div>

            <div class="container bg-white shadow-sm rounded-lg p-4">
                <h2 class="title">IDENTIFICATION DU PROJET</h2>
                <form id="edit-form">
                    <input type="hidden" id="expressionId" value="<?php echo htmlspecialchars($id); ?>">

                    <table class="form-table">
                        <tr>
                            <th class="label-code">Code Projet</th>
                            <td><input class="input-text" type="text" id="codeProjet"
                                    value="<?php echo htmlspecialchars($projectInfo['code_projet']); ?>"></td>
                        </tr>
                        <tr>
                            <th class="label-nom">Nom du Client</th>
                            <td><input class="input-text" type="text" id="nomClient"
                                    value="<?php echo htmlspecialchars($projectInfo['nom_client']); ?>"></td>
                        </tr>
                        <tr>
                            <th class="label-description">Description du Projet</th>
                            <td><input class="input-text" type="text" id="descriptionProjet"
                                    value="<?php echo htmlspecialchars($projectInfo['description_projet']); ?>"></td>
                        </tr>
                        <tr>
                            <th class="label-location">Situation géographique du chantier</th>
                            <td><input class="input-text" type="text" id="situationGeographique"
                                    value="<?php echo htmlspecialchars($projectInfo['sitgeo']); ?>"></td>
                        </tr>
                        <tr>
                            <th class="label-manager">Chef de Projet</th>
                            <td><input class="input-text" type="text" id="chefProjet"
                                    value="<?php echo htmlspecialchars($projectInfo['chefprojet']); ?>"></td>
                        </tr>
                    </table>
            </div>

            <div class="table-container bg-white shadow-sm rounded-lg p-4">
                <h2 class="title">DÉTAILS DES EXPRESSIONS</h2>
                <table id="dynamic-table">
                    <thead>
                        <tr>
                            <th>Désignation</th>
                            <th class="narrow">Unité</th>
                            <th class="narrow">Type</th>
                            <th class="narrow">
                                Quantité
                                <span class="decimal-info">
                                    <i class="material-icons text-sm">help_outline</i>
                                    <span class="tooltip">Les nombres décimaux sont autorisés (ex: 2.5, 1.25)</span>
                                </span>
                            </th>
                            <th class="narrow">Stock Dispo</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expressions as $expr):
                            // Calcul du stock disponible réel
                            $stockAvailable = $expr['stock_quantity'] - $expr['total_reserved'] + $expr['quantity'];
                            $stockAvailable = max(0, $stockAvailable);
                            $stockClass = $stockAvailable >= $expr['quantity'] ? 'stock-success' : ($stockAvailable > 0 ? 'stock-warning' : 'stock-error');
                        ?>
                        <tr id="row-<?php echo $expr['id']; ?>" class="product-row"
                            data-original-quantity="<?php echo htmlspecialchars($expr['quantity']); ?>">
                            <td>
                                <div class="relative">
                                    <input type="text" class="designation-input"
                                        value="<?php echo htmlspecialchars($expr['designation']); ?>"
                                        data-original="<?php echo htmlspecialchars($expr['designation']); ?>">
                                    <div class="autocomplete-list"></div>
                                    <div class="stock-info <?php echo $stockClass; ?>">
                                        Stock: <?php echo $stockAvailable; ?> / <?php echo $expr['stock_quantity']; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="narrow">
                                <input type="text" value="<?php echo htmlspecialchars($expr['unit']); ?>" readonly>
                            </td>
                            <td class="narrow">
                                <input type="text" value="<?php echo htmlspecialchars($expr['type']); ?>" readonly>
                            </td>
                            <td class="narrow">
                                <input type="number" class="quantity-input"
                                    value="<?php echo htmlspecialchars($expr['quantity']); ?>" min="0.01" step="0.01"
                                    placeholder="Ex: 2.5">
                            </td>
                            <td class="narrow">
                                <input type="text" value="<?php echo $stockAvailable; ?>" readonly>
                            </td>
                            <td class="actions-column">
                                <div class="delete-btn mx-auto">
                                    <span class="material-icons" style="font-size: 16px;">close</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="mt-4">
                    <button id="add-row-button" type="button"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center">
                        <span class="material-icons mr-1">add</span> Ajouter un produit
                    </button>
                </div>
            </div>

            <div class="actions-bar">
                <button type="button" class="btn btn-cancel"
                    onclick="window.location.href='dashboard.php'">Annuler</button>
                <button type="submit" form="edit-form" id="save-button" class="btn btn-success">
                    <span class="text">Enregistrer les modifications</span>
                    <span class="spinner material-icons" style="font-size: 18px;">refresh</span>
                </button>
            </div>
            </form>
        </main>

        <?php include_once '../components/footer.html'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // Variables globales
    let existingProducts = <?php echo json_encode($products); ?>;
    let deletedRows = [];
    let rowCounter = <?php echo count($expressions); ?>;
    let isSubmitting = false;

    // Normaliser le texte pour la comparaison
    function normalizeText(text) {
        if (!text) return '';
        let normalized = text.toLowerCase().trim();
        normalized = normalized.replace(/\s+/g, ' ');
        normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        return normalized;
    }

    // Fonction pour valider et formater les nombres décimaux
    function validateAndFormatDecimal(value) {
        // Convertir en nombre
        const num = parseFloat(value);

        // Vérifier si c'est un nombre valide et positif
        if (isNaN(num) || num <= 0) {
            return null;
        }

        // Limiter à 3 décimales maximum
        return Math.round(num * 1000) / 1000;
    }

    // Fonction pour formater l'affichage des nombres décimaux
    function formatDecimalDisplay(value) {
        const num = parseFloat(value);
        if (isNaN(num)) return '0';

        // Afficher jusqu'à 3 décimales, en supprimant les zéros inutiles
        return num.toFixed(3).replace(/\.?0+$/, '');
    }

    // Préparer les produits pour l'autocomplétion
    const normalizedProducts = existingProducts.map(product => ({
        product_name: product.product_name,
        unit: product.unit,
        type: product.type,
        unit_price: product.unit_price,
        quantity: parseFloat(product.quantity) || 0,
        quantity_reserved: parseFloat(product.quantity_reserved) || 0,
        normalizedName: normalizeText(product.product_name),
        available: Math.max(0, (parseFloat(product.quantity) || 0) - (parseFloat(product
            .quantity_reserved) || 0))
    }));

    // Filtrer la liste des désignations pour l'autocomplétion
    function filterProducts(input) {
        const filter = normalizeText(input.value);
        const list = input.nextElementSibling;

        list.innerHTML = '';

        if (!filter) return;

        const filteredProducts = normalizedProducts.filter(product =>
            product.normalizedName.includes(filter)
        );

        filteredProducts.slice(0, 10).forEach(product => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.textContent = product.product_name;
            item.addEventListener('click', () => selectProduct(product, input));
            list.appendChild(item);
        });
    }

    // Sélectionner un produit de la liste d'autocomplétion
    function selectProduct(product, input) {
        input.value = product.product_name;

        // Mettre à jour les champs unité et type
        const row = input.closest('tr');
        const unitInput = row.querySelector('td:nth-child(2) input');
        const typeInput = row.querySelector('td:nth-child(3) input');
        const stockInput = row.querySelector('td:nth-child(5) input');
        const stockInfo = input.closest('div').querySelector('.stock-info');

        if (unitInput) unitInput.value = product.unit;
        if (typeInput) typeInput.value = product.type;

        // Mettre à jour les informations de stock
        const stockAvailable = product.available;
        if (stockInput) stockInput.value = formatDecimalDisplay(stockAvailable);

        // Mettre à jour l'affichage des informations de stock
        if (stockInfo) {
            const quantityInput = row.querySelector('td:nth-child(4) input');
            const requestedQuantity = parseFloat(quantityInput.value) || 1;

            stockInfo.textContent =
                `Stock: ${formatDecimalDisplay(stockAvailable)} / ${formatDecimalDisplay(product.quantity)}`;

            if (stockAvailable >= requestedQuantity) {
                stockInfo.className = 'stock-info stock-success';
            } else if (stockAvailable > 0) {
                stockInfo.className = 'stock-info stock-warning';
            } else {
                stockInfo.className = 'stock-info stock-error';
            }
        }

        // Masquer la liste d'autocomplétion
        input.nextElementSibling.innerHTML = '';
    }

    // Vérifier la disponibilité du stock lors de la modification de la quantité
    function checkStockAvailability(row) {
        const designationInput = row.querySelector('td:nth-child(1) input');
        const quantityInput = row.querySelector('td:nth-child(4) input');
        const stockInput = row.querySelector('td:nth-child(5) input');
        const stockInfo = designationInput.closest('div').querySelector('.stock-info');

        const designation = normalizeText(designationInput.value);
        const requestedQuantity = parseFloat(quantityInput.value) || 0;

        // Valider la quantité saisie
        const validatedQuantity = validateAndFormatDecimal(quantityInput.value);
        if (validatedQuantity === null && quantityInput.value !== '') {
            quantityInput.style.borderColor = '#ef4444';
            quantityInput.style.backgroundColor = '#fef2f2';
            return;
        } else {
            quantityInput.style.borderColor = '';
            quantityInput.style.backgroundColor = '';
            if (validatedQuantity !== null) {
                quantityInput.value = formatDecimalDisplay(validatedQuantity);
            }
        }

        // Trouver le produit correspondant
        const product = normalizedProducts.find(p => p.normalizedName === designation);

        if (product) {
            const originalQuantity = parseFloat(row.getAttribute('data-original-quantity')) || 0;
            const isNewRow = row.id.includes('new-row');

            // Pour les lignes existantes, il faut considérer la quantité originale
            let adjustedAvailable = product.available;
            if (!isNewRow) {
                adjustedAvailable += originalQuantity;
            }

            // Mettre à jour l'affichage du stock disponible
            if (stockInput) stockInput.value = formatDecimalDisplay(adjustedAvailable);

            // Mettre à jour l'indicateur visuel
            if (stockInfo) {
                stockInfo.textContent =
                    `Stock: ${formatDecimalDisplay(adjustedAvailable)} / ${formatDecimalDisplay(product.quantity)}`;

                if (adjustedAvailable >= requestedQuantity) {
                    stockInfo.className = 'stock-info stock-success';
                } else if (adjustedAvailable > 0) {
                    stockInfo.className = 'stock-info stock-warning';
                } else {
                    stockInfo.className = 'stock-info stock-error';
                }
            }
        }
    }

    // Ajouter une nouvelle ligne
    function addRow() {
        rowCounter++;
        const tbody = document.querySelector('#dynamic-table tbody');
        const rowId = 'new-row-' + rowCounter;

        const newRow = document.createElement('tr');
        newRow.id = rowId;
        newRow.className = 'product-row';
        newRow.setAttribute('data-original-quantity', '0');
        newRow.innerHTML = `
                <td>
                    <div class="relative">
                        <input type="text" class="designation-input">
                        <div class="autocomplete-list"></div>
                        <div class="stock-info">Stock: 0 / 0</div>
                    </div>
                </td>
                <td class="narrow">
                    <input type="text" readonly>
                </td>
                <td class="narrow">
                    <input type="text" readonly>
                </td>
                <td class="narrow">
                    <input type="number" class="quantity-input" value="1" min="0.01" step="0.01" placeholder="Ex: 2.5">
                </td>
                <td class="narrow">
                    <input type="text" value="0" readonly>
                </td>
                <td class="actions-column">
                    <div class="delete-btn mx-auto">
                        <span class="material-icons" style="font-size: 16px;">close</span>
                    </div>
                </td>
            `;

        tbody.appendChild(newRow);

        // Ajouter les événements
        const designationInput = newRow.querySelector('.designation-input');
        designationInput.addEventListener('input', () => filterProducts(designationInput));

        const quantityInput = newRow.querySelector('.quantity-input');
        quantityInput.addEventListener('input', () => checkStockAvailability(newRow));
        quantityInput.addEventListener('change', () => checkStockAvailability(newRow));

        // Événement pour formater la quantité lors de la perte de focus
        quantityInput.addEventListener('blur', function() {
            const validated = validateAndFormatDecimal(this.value);
            if (validated !== null) {
                this.value = formatDecimalDisplay(validated);
            }
        });

        const deleteBtn = newRow.querySelector('.delete-btn');
        deleteBtn.addEventListener('click', () => {
            newRow.remove();
        });
    }

    // Supprimer une ligne existante
    function removeExistingRow(row) {
        const rowId = row.id.replace('row-', '');

        // Confirmer la suppression
        Swal.fire({
            title: 'Êtes-vous sûr?',
            text: "Voulez-vous supprimer cette ligne?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                deletedRows.push(rowId);
                row.remove();
            }
        });
    }

    // Collecter les données du formulaire
    function collectFormData() {
        const data = {
            expressionId: document.getElementById('expressionId').value,
            projectInfo: {
                code_projet: document.getElementById('codeProjet').value.trim(),
                nom_client: document.getElementById('nomClient').value.trim(),
                description_projet: document.getElementById('descriptionProjet').value.trim(),
                sitgeo: document.getElementById('situationGeographique').value.trim(),
                chefprojet: document.getElementById('chefProjet').value.trim()
            },
            expressions: [],
            deletedRows: deletedRows
        };

        // Collecter les données des produits
        document.querySelectorAll('.product-row').forEach(row => {
            const rowId = row.id.replace('row-', '').replace('new-row-', 'new-');
            const designation = row.querySelector('td:nth-child(1) input').value.trim();
            const unit = row.querySelector('td:nth-child(2) input').value.trim();
            const type = row.querySelector('td:nth-child(3) input').value.trim();
            const quantityValue = row.querySelector('td:nth-child(4) input').value;
            const originalQuantity = row.getAttribute('data-original-quantity') || 0;
            const originalDesignation = row.querySelector('td:nth-child(1) input').getAttribute(
                'data-original');

            // Valider et formater la quantité
            const validatedQuantity = validateAndFormatDecimal(quantityValue);

            if (designation && validatedQuantity !== null) {
                data.expressions.push({
                    id: rowId,
                    designation: designation,
                    unit: unit,
                    type: type,
                    quantity: validatedQuantity,
                    originalQuantity: originalQuantity,
                    originalDesignation: originalDesignation || designation
                });
            }
        });

        return data;
    }

    // Valider le formulaire
    function validateForm(data) {
        // Vérifier les informations du projet
        if (!data.projectInfo.code_projet || !data.projectInfo.nom_client ||
            !data.projectInfo.description_projet || !data.projectInfo.sitgeo ||
            !data.projectInfo.chefprojet) {
            return {
                valid: false,
                message: 'Tous les champs d\'identification du projet sont requis.'
            };
        }

        // Vérifier qu'il y a au moins une expression
        if (data.expressions.length === 0) {
            return {
                valid: false,
                message: 'Vous devez ajouter au moins un produit avec une quantité valide.'
            };
        }

        // Vérifier que les quantités sont valides
        const invalidQuantities = [];
        data.expressions.forEach((expr, index) => {
            if (!expr.quantity || expr.quantity <= 0) {
                invalidQuantities.push({
                    name: expr.designation,
                    row: index + 1,
                    quantity: expr.quantity
                });
            }
        });

        if (invalidQuantities.length > 0) {
            const quantitiesMessage = invalidQuantities.map(q =>
                `- "${q.name}" (ligne ${q.row}): quantité invalide (${q.quantity})`
            ).join('\n');

            return {
                valid: false,
                message: `Les quantités suivantes sont invalides:\n\n${quantitiesMessage}\n\nLes quantités doivent être des nombres positifs (décimaux autorisés).`
            };
        }

        // Vérifier que les produits existent dans la base de données
        const invalidProducts = [];
        data.expressions.forEach((expr, index) => {
            const normalizedDesignation = normalizeText(expr.designation);
            const productExists = normalizedProducts.some(p =>
                p.normalizedName === normalizedDesignation
            );

            if (!productExists) {
                invalidProducts.push({
                    name: expr.designation,
                    row: index + 1
                });
            }
        });

        if (invalidProducts.length > 0) {
            const productsMessage = invalidProducts.map(p =>
                `- "${p.name}" (ligne ${p.row})`
            ).join('\n');

            return {
                valid: false,
                message: `Les produits suivants n'existent pas dans la base de données:\n\n${productsMessage}\n\nVeuillez contacter le super administrateur pour ajouter ces produits.`
            };
        }

        // Vérifier les quantités par rapport au stock disponible
        const stockWarnings = [];
        data.expressions.forEach((expr, index) => {
            const normalizedDesignation = normalizeText(expr.designation);
            const product = normalizedProducts.find(p => p.normalizedName === normalizedDesignation);

            if (product) {
                const isNewRow = expr.id.includes('new-');
                const originalQuantity = isNewRow ? 0 : parseFloat(expr.originalQuantity);
                const requestedQuantity = parseFloat(expr.quantity);

                // Pour les lignes existantes, on doit ajuster le stock disponible
                let adjustedAvailable = product.available;
                if (!isNewRow) {
                    adjustedAvailable += originalQuantity;
                }

                if (requestedQuantity > adjustedAvailable) {
                    stockWarnings.push({
                        name: expr.designation,
                        row: index + 1,
                        requested: formatDecimalDisplay(requestedQuantity),
                        available: formatDecimalDisplay(adjustedAvailable)
                    });
                }
            }
        });

        if (stockWarnings.length > 0) {
            const warningsMessage = stockWarnings.map(w =>
                `- "${w.name}" (ligne ${w.row}): ${w.requested} demandé, ${w.available} disponible`
            ).join('\n');

            return {
                valid: true,
                warning: true,
                message: `Attention: Les produits suivants dépassent le stock disponible:\n\n${warningsMessage}\n\nVoulez-vous continuer quand même?`
            };
        }

        return {
            valid: true
        };
    }

    // Soumettre le formulaire
    function submitForm() {
        if (isSubmitting) return false;

        isSubmitting = true;
        const saveButton = document.getElementById('save-button');
        saveButton.disabled = true;
        saveButton.classList.add('loading');
        saveButton.querySelector('.text').textContent = 'Enregistrement en cours...';

        const formData = collectFormData();
        const validation = validateForm(formData);

        if (!validation.valid) {
            Swal.fire({
                title: 'Erreur de validation',
                text: validation.message,
                icon: 'error',
                confirmButtonText: 'OK'
            });
            resetSaveButton();
            return false;
        }

        // Si nous avons un avertissement de stock, demander confirmation
        if (validation.valid && validation.warning) {
            Swal.fire({
                title: 'Avertissement de stock',
                text: validation.message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Continuer quand même',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    sendFormData(formData);
                } else {
                    resetSaveButton();
                }
            });
            return false;
        }

        // Si tout est valide, envoyer les données
        sendFormData(formData);
        return false;
    }

    // Fonction pour réinitialiser le bouton de sauvegarde
    function resetSaveButton() {
        isSubmitting = false;
        const saveButton = document.getElementById('save-button');
        saveButton.disabled = false;
        saveButton.classList.remove('loading');
        saveButton.querySelector('.text').textContent = 'Enregistrer les modifications';
    }

    // Envoyer les données via AJAX
    function sendFormData(formData) {
        // Envoyer les données via AJAX
        $.ajax({
            url: 'api_expression/update_expression.php',
            type: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Succès',
                        text: 'Expression de besoin mise à jour avec succès.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'dashboard.php';
                    });
                } else {
                    Swal.fire({
                        title: 'Erreur',
                        text: response.message || 'Une erreur est survenue lors de la mise à jour.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    resetSaveButton();
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur AJAX:', xhr.responseText);
                Swal.fire({
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                resetSaveButton();
            }
        });
    }

    // Initialisation
    document.addEventListener('DOMContentLoaded', function() {
        // Initialiser les événements d'autocomplétion
        document.querySelectorAll('.designation-input').forEach(input => {
            input.addEventListener('input', () => filterProducts(input));
        });

        // Initialiser les événements de quantité
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('input', () => checkStockAvailability(input.closest('tr')));
            input.addEventListener('change', () => checkStockAvailability(input.closest('tr')));

            // Événement pour formater la quantité lors de la perte de focus
            input.addEventListener('blur', function() {
                const validated = validateAndFormatDecimal(this.value);
                if (validated !== null) {
                    this.value = formatDecimalDisplay(validated);
                }
            });
        });

        // Initialiser les événements de suppression
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');
                removeExistingRow(row);
            });
        });

        // Bouton d'ajout de ligne
        document.getElementById('add-row-button').addEventListener('click', addRow);

        // Soumission du formulaire
        document.getElementById('edit-form').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm();
        });
    });
    </script>
</body>

</html>