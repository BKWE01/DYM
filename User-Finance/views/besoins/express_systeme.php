<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
  // Rediriger vers index.php
  header("Location: ./../../index.php");
  exit();
}

// Connexion à la base de données pour récupérer les infos utilisateur
require_once '../../../database/connection.php';

// Récupération des informations de l'utilisateur connecté
$user_id = $_SESSION['user_id'];
$user_info = [];

try {
  $stmt = $pdo->prepare("SELECT name, user_type FROM users_exp WHERE id = :user_id");
  $stmt->execute([':user_id' => $user_id]);
  $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  // En cas d'erreur, utiliser des valeurs par défaut
  $user_info = ['name' => 'Utilisateur Inconnu', 'user_type' => 'user'];
}

// Mapping des types d'utilisateurs vers les services
function getUserService($user_type)
{
  $service_mapping = [
    'be' => 'BUREAU D\'ETUDE',
    'bureau_etude' => 'BUREAU D\'ETUDE',
    'achat' => 'SERVICE ACHAT',
    'purchasing' => 'SERVICE ACHAT',
    'finance' => 'SERVICE FINANCE',
    'financial' => 'SERVICE FINANCE',
    'stock' => 'SERVICE STOCK',
    'warehouse' => 'SERVICE STOCK',
    'informatique' => 'SERVICE INFORMATIQUE',
    'it' => 'SERVICE INFORMATIQUE',
    'admin' => 'SERVICE INFORMATIQUE',
    'super_admin' => 'SERVICE INFORMATIQUE'
  ];

  $user_type_lower = strtolower($user_type);

  // Recherche exacte
  if (isset($service_mapping[$user_type_lower])) {
    return $service_mapping[$user_type_lower];
  }

  // Recherche partielle
  foreach ($service_mapping as $key => $service) {
    if (strpos($user_type_lower, $key) !== false) {
      return $service;
    }
  }

  // Par défaut
  return 'SERVICE INFORMATIQUE';
}

$user_service = getUserService($user_info['user_type'] ?? '');
$user_name = $user_info['name'] ?? 'Utilisateur Inconnu';

?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Expression de besoin - Système</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    .table-container {
      position: relative;
      width: 90%;
      margin: 30px auto;
    }

    .add-row {
      cursor: pointer;
      background-color: #3490dc;
      color: white;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      position: absolute;
      bottom: 10px;
      right: 25px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .table-container table {
      width: 90%;
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

    .table-container td {
      width: auto;
    }

    .table-container .narrow {
      width: 200px;
    }

    .table-container .action-column {
      width: 60px;
      text-align: center;
    }

    .table-container tr:last-child td {
      border-bottom: none;
    }

    .table-container input {
      border: 1px solid #d1d5db;
      padding: 8px;
      width: 100%;
      box-sizing: border-box;
      background-color: #f8f9fa;
      border-radius: 4px;
    }

    .table-container input:focus {
      border-color: #3490dc;
      outline: none;
    }

    /* Champ désactivé après sélection */
    .table-container input:disabled {
      background-color: #e5e7eb;
      cursor: not-allowed;
      opacity: 0.7;
    }

    /* Champs automatiques non modifiables */
    .auto-filled {
      background-color: #f3f4f6 !important;
      cursor: not-allowed;
      color: #374151;
      font-weight: 500;
    }

    /* Modal Styles pour les erreurs */
    .error-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      justify-content: center;
      align-items: center;
      z-index: 1000;
    }

    .error-modal-content {
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      width: 500px;
      max-width: 90%;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    .error-modal.active {
      display: flex;
    }

    .error-modal h3 {
      color: #dc2626;
      margin-bottom: 15px;
      font-size: 18px;
      font-weight: 600;
    }

    .error-modal p {
      color: #374151;
      line-height: 1.6;
      margin-bottom: 15px;
    }

    .error-modal .error-details {
      background-color: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 6px;
      padding: 15px;
      margin: 15px 0;
      color: #991b1b;
      font-family: monospace;
      font-size: 14px;
      max-height: 200px;
      overflow-y: auto;
    }

    .error-modal button {
      background-color: #dc2626;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
    }

    .error-modal button:hover {
      background-color: #b91c1c;
    }

    .autocomplete-list {
      border: 1px solid #d1d5db;
      background-color: #fff;
      border-radius: 4px;
      max-height: 200px;
      overflow-y: auto;
      position: absolute;
      z-index: 10;
      width: 100%;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .autocomplete-item {
      padding: 8px 12px;
      cursor: pointer;
      border-bottom: 1px solid #f0f0f0;
    }

    .autocomplete-item:hover {
      background-color: #f0f9ff;
    }

    .autocomplete-item .product-code {
      color: #6b7280;
      font-size: 0.8rem;
      margin-right: 8px;
    }

    .autocomplete-item .product-name {
      font-weight: 500;
    }

    .autocomplete-item .product-stock {
      float: right;
      background-color: #e5e7eb;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 0.8rem;
    }

    .validate-btn {
      border: 2px solid #38a169;
      color: #38a169;
      padding: 8px 18px;
      border-radius: 8px;
      text-align: center;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
      background-color: transparent;
      transition: color 0.3s, border-color 0.3s;
    }

    .validate-btn:hover {
      color: #2f855a;
      border-color: #2f855a;
      background-color: #f0fff4;
    }

    /* Indication visuelle des produits non valides */
    .invalid-product {
      border-color: #ef4444 !important;
      background-color: #fef2f2 !important;
    }

    .valid-product {
      border-color: #10b981 !important;
      background-color: #f0fdf4 !important;
    }

    .date-time {
      display: flex;
      align-items: center;
      font-size: 16px;
      color: #4a5568;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 8px 18px;
    }

    .date-time .material-icons {
      margin-right: 12px;
      font-size: 22px;
      color: #2d3748;
    }

    .date-time span {
      font-weight: 500;
      line-height: 1.4;
    }

    .container {
      width: 100%;
      margin: 30px auto;
      display: flex;
      justify-content: center;
    }

    .demande-table {
      width: 90%;
      border-collapse: collapse;
      border: 2px solid #000;
    }

    .demande-table th,
    .demande-table td {
      border: 1px solid #000;
      padding: 15px;
      text-align: center;
    }

    .demande-table th {
      background-color: #f0f0f0;
      font-weight: bold;
    }

    .demande-table td {
      padding: 0;
    }

    .form-input,
    .form-date,
    .form-textarea {
      width: 100%;
      height: 100%;
      padding: 10px;
      border: none;
      outline: none;
      background-color: transparent;
      font-size: 16px;
      font-family: inherit;
      color: #000;
    }

    .form-date[readonly] {
      background-color: transparent;
      pointer-events: none;
    }

    .form-textarea {
      height: 100px;
      resize: none;
    }

    .form-label {
      display: block;
      margin-bottom: 5px;
      text-align: left;
      padding-left: 10px;
      font-weight: bold;
    }

    .header-title {
      font-weight: 300;
    }

    .required-field::after {
      content: " *";
      color: red;
    }

    .hidden-product-id {
      display: none;
    }

    .delete-row-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: #f56565;
      color: white;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      border: none;
      cursor: pointer;
      transition: background-color 0.3s;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      margin: 0 auto;
      padding: 0;
      flex-shrink: 0;
    }

    .delete-row-btn:hover {
      background-color: #e53e3e;
    }

    .unit-input,
    .type-input {
      background-color: #f8f9fa;
      color: #495057;
      border: 1px solid #ced4da;
      padding: 6px 12px;
      width: 100%;
      border-radius: 4px;
    }

    .unit-input:read-only,
    .type-input:read-only {
      background-color: #e9ecef;
      cursor: not-allowed;
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

    /* Style pour les champs décimaux */
    .decimal-input {
      text-align: right;
    }

    /* Indication visuelle pour les champs automatiques */
    .auto-filled-indicator {
      position: relative;
    }

    .auto-filled-indicator::after {
      content: "🔒";
      position: absolute;
      right: 5px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 12px;
      color: #6b7280;
    }

    /* Loading spinner */
    .loading {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 3px solid #f3f3f3;
      border-radius: 50%;
      border-top-color: #3498db;
      animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }
  </style>
</head>

<body class="bg-gray-100">

  <div class="wrapper flex flex-col min-h-screen">
    <!-- Include Navbar -->
    <?php include_once '../../../components/navbar_finance.php'; ?>

    <main class="flex-1 p-6">
      <!-- Main Content -->
      <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
        <button class="validate-btn" onclick="validateExpressions()">Valider les expressions</button>

        <a href="index.php" class="text-decoration-none">
          <button class="dashboard-btn">
            <span class="material-icons" style="font-size: 18px;">arrow_back</span>
            Retour au Dashboard
          </button>
        </a>

        <h2 class="header-title">SYSTEME DE MANAGEMENT INTEGRE QUALITE-SECURITE-ENVIRONNEMENT</h2>
        <div class="date-time">
          <span class="material-icons">calendar_today</span>
          <span id="date-time-display"></span>
        </div>
      </div>

      <div class="container">
        <table class="demande-table">
          <tr>
            <th id="service-label" class="required-field">SERVICE DEMANDEUR</th>
            <th id="nom-prenoms-label" class="required-field">NOM & PRÉNOMS DEMANDEUR</th>
            <th id="date-demande-label">DATE DEMANDE</th>
          </tr>
          <tr>
            <td class="auto-filled-indicator">
              <input type="text" class="form-input auto-filled" id="service_demandeur" name="service_demandeur"
                value="<?= htmlspecialchars($user_service) ?>" readonly required>
            </td>
            <td class="auto-filled-indicator">
              <input type="text" class="form-input auto-filled" id="nom_prenoms" name="nom_prenoms"
                value="<?= htmlspecialchars($user_name) ?>" readonly required>
            </td>
            <td><input type="date" class="form-date" id="date_demande" readonly></td>
          </tr>
          <tr>
            <td colspan="3" class="motif">
              <label for="motif_demande" class="form-label required-field" id="motif-label">
                <u><em>Motif de la demande :</em></u>
              </label>
              <textarea id="motif_demande" class="form-textarea" name="motif_demande" required></textarea>
            </td>
          </tr>
        </table>
      </div>

      <div class="table-container bg-white shadow-sm rounded-lg">
        <table id="dynamic-table">
          <thead>
            <tr>
              <th>Désignation de l'article</th>
              <th class="narrow">Unité</th>
              <th class="narrow">Type</th>
              <th class="narrow">Quantité demandée</th>
              <th class="action-column">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <div class="relative">
                  <input type="text" class="product-input" placeholder="Saisir une désignation">
                  <input type="hidden" class="product-id hidden-product-id" value="">
                  <div class="autocomplete-list"></div>
                </div>
              </td>
              <td><input type="text" class="unit-input" placeholder="Unité" readonly></td>
              <td><input type="text" class="type-input" placeholder="Type" readonly></td>
              <td>
                <input type="number" class="quantity-input decimal-input" placeholder="Quantité"
                  min="0" step="0.01">
              </td>
              <td class="action-column">
                <button type="button" class="delete-row-btn" onclick="deleteRow(this)">
                  <span class="material-icons" style="font-size: 18px;">delete</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <div id="add-row-button" class="add-row" onclick="addRow()">
          <span class="material-icons">add</span>
        </div>
      </div>
    </main>

    <!-- Include Footer -->
    <?php include_once '../../../components/footer.html'; ?>

    <!-- Modal d'erreur pour les produits inexistants -->
    <div id="error-modal" class="error-modal">
      <div class="error-modal-content">
        <h3>⚠️ Produits non disponibles dans le catalogue</h3>
        <p>Les articles suivants ne sont pas disponibles dans notre catalogue et ne peuvent pas être commandés :</p>
        <div id="error-details" class="error-details"></div>
        <p><strong>Solution :</strong> Veuillez contacter l'administrateur pour ajouter ces produits au catalogue avant de pouvoir les commander.</p>
        <div style="text-align: right; margin-top: 20px;">
          <button onclick="closeErrorModal()">Fermer</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script>
    // Configuration globale
    const APP_CONFIG = {
      user: {
        id: <?= json_encode($user_id) ?>,
        name: <?= json_encode($user_name) ?>,
        service: <?= json_encode($user_service) ?>
      },
      decimal: {
        precision: 2,
        step: 0.01
      }
    };

    // Stockage des produits
    let products = [];

    // Charger les produits depuis la base de données
    document.addEventListener('DOMContentLoaded', () => {
      loadProducts();
      updateDateTime();
      setInterval(updateDateTime, 1000);

      // Initialiser la date actuelle
      var today = new Date().toISOString().split('T')[0];
      document.getElementById('date_demande').value = today;

      // Afficher les informations utilisateur pour debug
      console.log('Utilisateur connecté:', APP_CONFIG.user);
    });

    /**
     * Charger les produits depuis le serveur
     */
    function loadProducts() {
      fetch('../../api/besoins/get_products.php')
        .then(response => response.json())
        .then(data => {
          products = data;
          setupProductInputs();
        })
        .catch(error => {
          console.error('Erreur lors du chargement des produits:', error);
          showErrorMessage('Erreur lors du chargement du catalogue des produits. Veuillez rafraîchir la page.');
        });
    }

    /**
     * Configurer tous les champs de saisie de produits
     */
    function setupProductInputs() {
      document.querySelectorAll('.product-input').forEach(input => {
        input.removeEventListener('input', handleProductInput);
        input.addEventListener('input', handleProductInput);

        // Ajouter validation en temps réel
        input.addEventListener('blur', validateProductInput);
      });
    }

    /**
     * Validation en temps réel des produits saisis
     */
    function validateProductInput(event) {
      const input = event.target;
      const inputValue = input.value.trim();

      if (!inputValue) {
        input.classList.remove('invalid-product', 'valid-product');
        return;
      }

      // Vérifier si le produit existe dans le catalogue
      const productExists = products.some(product =>
        product.product_name.toLowerCase() === inputValue.toLowerCase() ||
        product.barcode.toLowerCase() === inputValue.toLowerCase()
      );

      if (productExists) {
        input.classList.remove('invalid-product');
        input.classList.add('valid-product');
      } else {
        input.classList.remove('valid-product');
        input.classList.add('invalid-product');
      }
    }

    /**
     * Gestionnaire d'événements pour la saisie de produit
     */
    function handleProductInput(event) {
      const input = event.target;
      const filter = input.value.toLowerCase();
      const dropdown = input.nextElementSibling.nextElementSibling;

      dropdown.innerHTML = '';

      if (!filter) {
        dropdown.style.display = 'none';
        input.classList.remove('invalid-product', 'valid-product');
        return;
      }

      // Filtrer les produits qui correspondent à la recherche
      const matches = products.filter(product =>
        product.product_name.toLowerCase().includes(filter) ||
        product.barcode.toLowerCase().includes(filter)
      );

      if (matches.length === 0) {
        dropdown.style.display = 'none';
        return;
      }

      // Afficher les résultats
      dropdown.style.display = 'block';

      matches.slice(0, 10).forEach(product => {
        const item = document.createElement('div');
        item.className = 'autocomplete-item';
        item.innerHTML = `
          <span class="product-code">${product.barcode}</span>
          <span class="product-name">${product.product_name}</span>
          <span class="product-stock">Stock: ${formatNumber(product.quantity)} ${product.unit}</span>
        `;
        item.onclick = () => selectProduct(product, input);
        dropdown.appendChild(item);
      });
    }

    /**
     * Sélectionner un produit dans la liste
     */
    function selectProduct(product, input) {
      // Remplir les champs avec les informations du produit
      input.value = product.product_name;
      input.dataset.barcode = product.barcode;

      const row = input.closest('tr');
      const unitInput = row.querySelector('.unit-input');
      const typeInput = row.querySelector('.type-input');

      if (unitInput && product.unit) {
        unitInput.value = product.unit;
      }

      if (typeInput) {
        typeInput.value = product.type || '';
      }

      // Stocker l'ID du produit dans le champ caché
      const productIdInput = input.parentElement.querySelector('.product-id');
      if (productIdInput) {
        productIdInput.value = product.id;
      }

      // Marquer comme valide
      input.classList.remove('invalid-product');
      input.classList.add('valid-product');

      // Désactiver le champ après sélection
      input.disabled = true;

      // Fermer la liste déroulante
      const dropdown = input.nextElementSibling.nextElementSibling;
      dropdown.innerHTML = '';
      dropdown.style.display = 'none';

      // Mettre le focus sur le champ de quantité
      const quantityInput = row.querySelector('.quantity-input');
      if (quantityInput) {
        quantityInput.focus();
      }
    }

    /**
     * Ajouter une nouvelle ligne au tableau
     */
    function addRow() {
      const table = document.getElementById('dynamic-table').getElementsByTagName('tbody')[0];
      const newRow = table.insertRow();

      // Cellule de désignation du produit avec autocomplétion
      const designationCell = newRow.insertCell(0);
      designationCell.innerHTML = `
        <div class="relative">
          <input type="text" class="product-input" placeholder="Saisir une désignation">
          <input type="hidden" class="product-id hidden-product-id" value="">
          <div class="autocomplete-list"></div>
        </div>
      `;

      // Cellule pour l'unité (readonly)
      const unitCell = newRow.insertCell(1);
      unitCell.className = 'narrow';
      unitCell.innerHTML = '<input type="text" class="unit-input" placeholder="Unité" readonly>';

      // Cellule pour le type (readonly)
      const typeCell = newRow.insertCell(2);
      typeCell.className = 'narrow';
      typeCell.innerHTML = '<input type="text" class="type-input" placeholder="Type" readonly>';

      // Cellule de quantité avec support des décimaux
      const quantityCell = newRow.insertCell(3);
      quantityCell.className = 'narrow';
      quantityCell.innerHTML = `
        <input type="number" class="quantity-input decimal-input" placeholder="Quantité" 
               min="0" step="${APP_CONFIG.decimal.step}">
      `;

      // Cellule avec bouton de suppression
      const actionCell = newRow.insertCell(4);
      actionCell.className = 'action-column';
      actionCell.innerHTML = `
        <button type="button" class="delete-row-btn" onclick="deleteRow(this)">
          <span class="material-icons" style="font-size: 18px;">delete</span>
        </button>
      `;

      // Réinitialiser les gestionnaires d'événements
      setupProductInputs();
    }

    /**
     * Supprimer une ligne du tableau
     */
    function deleteRow(button) {
      const row = button.closest('tr');
      const tbody = document.getElementById('dynamic-table').getElementsByTagName('tbody')[0];

      if (tbody.rows.length === 1) {
        // Si c'est la seule ligne, la réinitialiser
        const productInput = row.querySelector('.product-input');
        const quantityInput = row.querySelector('.quantity-input');
        const unitInput = row.querySelector('.unit-input');
        const typeInput = row.querySelector('.type-input');
        const productIdInput = row.querySelector('.product-id');

        // Réinitialiser les champs
        productInput.value = '';
        productInput.disabled = false;
        productInput.classList.remove('invalid-product', 'valid-product');
        quantityInput.value = '';
        unitInput.value = '';
        typeInput.value = '';

        if (productIdInput) {
          productIdInput.value = '';
        }

        // Supprimer les données du dataset
        delete productInput.dataset.barcode;
      } else {
        // Sinon, supprimer la ligne
        row.parentNode.removeChild(row);
      }
    }

    /**
     * Afficher un message d'erreur détaillé
     */
    function showErrorModal(message) {
      document.getElementById('error-details').innerHTML = message;
      document.getElementById('error-modal').classList.add('active');
    }

    /**
     * Fermer le modal d'erreur
     */
    function closeErrorModal() {
      document.getElementById('error-modal').classList.remove('active');
    }

    /**
     * Afficher un message d'erreur simple
     */
    function showErrorMessage(message) {
      alert(message);
    }

    /**
     * Mettre à jour l'affichage de la date et de l'heure
     */
    function updateDateTime() {
      const now = new Date();
      const formattedDate = `${now.toLocaleDateString('fr-FR')} ${now.toLocaleTimeString('fr-FR')}`;
      document.getElementById('date-time-display').textContent = formattedDate;
    }

    /**
     * Formater un nombre avec gestion des décimaux
     */
    function formatNumber(value) {
      if (value === null || value === undefined || value === '') return '0';

      const num = parseFloat(value);
      if (isNaN(num)) return '0';

      return num.toLocaleString('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: APP_CONFIG.decimal.precision
      });
    }

    /**
     * Validation préalable avant soumission
     */
    function validateBeforeSubmission() {
      const productInputs = document.querySelectorAll('.product-input');
      const quantityInputs = document.querySelectorAll('.quantity-input');
      const invalidProducts = [];
      const missingProducts = [];

      for (let i = 0; i < productInputs.length; i++) {
        const productInput = productInputs[i];
        const quantityInput = quantityInputs[i];
        const quantity = parseFloat(quantityInput.value);

        // Vérifier si la ligne a des données
        if (productInput.value && !isNaN(quantity) && quantity > 0) {
          const productIdInput = productInput.parentElement.querySelector('.product-id');
          const productId = productIdInput ? productIdInput.value : '';

          // Vérifier si le produit a un ID (donc existe dans le catalogue)
          if (!productId) {
            // Vérifier si le produit existe par nom
            const productExists = products.some(product =>
              product.product_name.toLowerCase() === productInput.value.toLowerCase()
            );

            if (!productExists) {
              invalidProducts.push({
                index: i + 1,
                name: productInput.value,
                quantity: quantity
              });
            }
          }
        } else if (productInput.value && (isNaN(quantity) || quantity <= 0)) {
          missingProducts.push({
            index: i + 1,
            name: productInput.value,
            issue: 'Quantité manquante ou invalide'
          });
        }
      }

      // S'il y a des produits invalides, afficher l'erreur
      if (invalidProducts.length > 0) {
        let errorMessage = '';
        invalidProducts.forEach(product => {
          errorMessage += `• Ligne ${product.index}: "${product.name}" (Quantité: ${product.quantity})\n`;
        });

        showErrorModal(errorMessage);
        return false;
      }

      // S'il y a des problèmes de quantité
      if (missingProducts.length > 0) {
        let errorMessage = 'Problèmes détectés:\n\n';
        missingProducts.forEach(product => {
          errorMessage += `• Ligne ${product.index}: "${product.name}" - ${product.issue}\n`;
        });

        showErrorMessage(errorMessage);
        return false;
      }

      return true;
    }

    /**
     * Valider et soumettre le formulaire avec validation stricte
     */
    async function validateExpressions() {
      try {
        // Validation préalable
        if (!validateBeforeSubmission()) {
          return;
        }

        // Vérifier les champs obligatoires
        const service = document.getElementById('service_demandeur').value;
        const nomPrenoms = document.getElementById('nom_prenoms').value;
        const motif = document.getElementById('motif_demande').value;

        if (!service || !nomPrenoms || !motif) {
          showErrorMessage('Veuillez remplir tous les champs obligatoires.');
          return;
        }

        // Vérifier qu'au moins un produit a été ajouté
        const productInputs = document.querySelectorAll('.product-input');
        const quantityInputs = document.querySelectorAll('.quantity-input');
        let hasValidProduct = false;

        for (let i = 0; i < productInputs.length; i++) {
          const quantity = parseFloat(quantityInputs[i].value);
          if (productInputs[i].value && !isNaN(quantity) && quantity > 0) {
            hasValidProduct = true;
            break;
          }
        }

        if (!hasValidProduct) {
          showErrorMessage('Veuillez ajouter au moins un produit avec une quantité valide.');
          return;
        }

        // Afficher un indicateur de chargement
        const validateBtn = document.querySelector('.validate-btn');
        const originalText = validateBtn.innerHTML;
        validateBtn.innerHTML = '<span class="loading"></span> Enregistrement...';
        validateBtn.disabled = true;

        // Générer l'ID de besoin
        const idBesoin = await generateIdBesoin();

        // Préparer les données du demandeur
        const demandeurData = {
          idBesoin,
          service_demandeur: service,
          nom_prenoms: nomPrenoms,
          date_demande: document.getElementById('date_demande').value,
          motif_demande: motif
        };

        // Enregistrer les données du demandeur
        const demandeurResponse = await fetch('../../api/besoins/save_demandeur.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(demandeurData)
        });

        const demandeurResult = await demandeurResponse.json();

        if (!demandeurResult.success) {
          throw new Error('Erreur lors de l\'enregistrement du demandeur.');
        }

        // Préparer les données des besoins
        const besoinsData = [];

        for (let i = 0; i < productInputs.length; i++) {
          const quantity = parseFloat(quantityInputs[i].value);

          if (productInputs[i].value && !isNaN(quantity) && quantity > 0) {
            const productIdInput = productInputs[i].parentElement.querySelector('.product-id');
            const productId = productIdInput ? productIdInput.value : '';
            const row = productInputs[i].closest('tr');
            const unitInput = row.querySelector('.unit-input');
            const typeInput = row.querySelector('.type-input');

            besoinsData.push({
              idBesoin,
              product_id: productId,
              designation_article: productInputs[i].value,
              caracteristique: unitInput ? unitInput.value : '',
              type: typeInput ? typeInput.value : '',
              qt_demande: quantity,
              quantity_reserved: quantity,
              user_emet: APP_CONFIG.user.id
            });
          }
        }

        // Enregistrer les données des besoins
        const besoinsResponse = await fetch('../../api/besoins/save_besoins.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(besoinsData)
        });

        const besoinsResult = await besoinsResponse.json();

        // Restaurer le bouton
        validateBtn.innerHTML = originalText;
        validateBtn.disabled = false;

        if (besoinsResult.success) {
          alert('Les expressions ont été enregistrées avec succès.');

          // Ouvrir le PDF dans un nouvel onglet
          window.open(`../../api/pdf/expression_syst_pdf.php?id=${idBesoin}`, '_blank');

          // Rediriger vers la page d'expressions
          window.location.href = 'index.php';
        } else {
          // Gestion spécifique des erreurs de produits inexistants
          if (besoinsResult.message && besoinsResult.message.includes('Produits non trouvés')) {
            showErrorModal(besoinsResult.message.replace(/\n/g, '<br>'));
          } else {
            throw new Error('Erreur lors de l\'enregistrement des besoins: ' + besoinsResult.message);
          }
        }
      } catch (error) {
        // Restaurer le bouton en cas d'erreur
        const validateBtn = document.querySelector('.validate-btn');
        validateBtn.innerHTML = 'Valider les expressions';
        validateBtn.disabled = false;

        console.error('Erreur:', error);
        showErrorMessage('Une erreur est survenue: ' + error.message);
      }
    }

    /**
     * Générer un ID de besoin unique
     */
    async function generateIdBesoin() {
      try {
        const response = await fetch('../../api/besoins/get_last_idBesoin.php');
        const result = await response.json();

        let lastId = result.lastId || 1;
        lastId = (lastId + 1).toString().padStart(5, '0');

        const now = new Date();
        const datePart = now.toISOString().split('T')[0].replace(/-/g, '');

        return `${lastId}-EXP_B-${datePart}`;
      } catch (error) {
        console.error('Erreur lors de la génération de l\'ID:', error);
        throw new Error('Impossible de générer l\'ID de besoin.');
      }
    }

    // Gérer les événements de fermeture des listes déroulantes
    document.addEventListener('click', function(e) {
      if (!e.target.classList.contains('product-input')) {
        document.querySelectorAll('.autocomplete-list').forEach(list => {
          list.style.display = 'none';
        });
      }
    });
  </script>
</body>

</html>