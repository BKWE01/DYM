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

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

    .table-container td {
      width: auto;
      /* Default width */
    }

    .table-container .narrow {
      width: 250px;
      /* Adjust the width as needed */
    }

    .table-container tr:last-child td {
      border-bottom: none;
    }

    .table-container input {
      border: 1px solid #d1d5db;
      /* Couleur de bordure gris clair */
      padding: 8px;
      width: 100%;
      box-sizing: border-box;
      background-color: #f8f9fa;
      border-radius: 4px;
    }

    .table-container input:focus {
      border-color: #3490dc;
      /* Bordure bleue lorsque l'élément est focus */
      outline: none;
      /* Supprimer le contour par défaut du navigateur */
    }

    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      width: 400px;
    }

    .modal.active {
      display: flex;
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

    .autocomplete-item:last-child {
      border-bottom: none;
    }

    .form-table td {
      position: relative;
    }

    .validate-btn {
      border: 2px solid #38a169;
      /* Green border */
      color: #38a169;
      /* Green text */
      padding: 8px 18px;
      border-radius: 8px;
      text-align: center;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
      background-color: transparent;
      /* No background */
      transition: color 0.3s, border-color 0.3s;
    }

    .validate-btn:hover {
      color: #2f855a;
      /* Darker green text */
      border-color: #2f855a;
      /* Darker green border */
    }

    .date-time {
      display: flex;
      align-items: center;
      font-size: 16px;
      color: #4a5568;
      border: 2px solid #e2e8f0;
      /* Light gray border */
      border-radius: 8px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      /* Elegant font-family */
      padding: 8px 18px;
    }

    .date-time .material-icons {
      margin-right: 12px;
      font-size: 22px;
      /* Larger icon size */
      color: #2d3748;
      /* Icon color matching the text */
    }

    .date-time span {
      font-weight: 500;
      line-height: 1.4;
      /* Better spacing for readability */
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
      min-width: 200px;
      /* Largeur minimale pour les cellules */
      vertical-align: middle;
      /* Aligner verticalement au milieu */
    }

    .form-table th {
      background-color: #f9f9f9;
      font-weight: bold;
    }

    .input-text {
      width: 100%;
      /* Les inputs occuperont toute la largeur de la cellule */
      padding: 8px;
      border: none;
      box-sizing: border-box;
      /* Assure que le padding ne dépasse pas la largeur */
      background-color: #f3f4f6;
      /* Couleur de fond modifiée */
    }

    .input-text:focus {
      outline: none;
      /* Retirer le contour par défaut */
    }

    .checkbox-group {
      display: flex;
      justify-content: space-between;
      margin-bottom: 20px;
    }

    .checkbox-group label {
      display: flex;
      align-items: center;
    }

    .checkbox-group input[type="checkbox"] {
      margin-right: 8px;
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

    .actions-column {
      width: 80px !important;
      min-width: 50% !important;
      max-width: 80px !important;
      text-align: center;
    }

    .validate-btn:disabled {
      border: 2px solid #9ca3af;
      /* Couleur grise pour l'état désactivé */
      color: #9ca3af;
      cursor: not-allowed;
      opacity: 0.7;
    }

    .validate-btn .spinner {
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

    .validate-btn.loading .spinner {
      display: inline-block;
    }
  </style>
</head>

<body class="bg-gray-100">

  <div class="wrapper flex flex-col min-h-screen">
    <!-- Include Navbar -->
    <?php include_once '../components/navbar.php'; ?>

    <main class="flex-1 p-6">
      <!-- Main Content -->
      <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
        <button id="validate-btn" class="validate-btn" onclick="validateExpressions()">
          <span class="text">Valider les expressions</span>
          <span class="spinner material-icons" style="font-size: 18px;">refresh</span>
        </button>
        <div class="date-time">
          <span class="material-icons">calendar_today</span>
          <span id="date-time-display"></span>
        </div>
      </div>

      <div class="container">
        <h2 class="title">IDENTIFICATION DU PROJET</h2>
        <table class="form-table">
          <tr>
            <th class="label-code">Code Projet</th>
            <td><input class="input-text" type="text" id="codeProjet" placeholder="Entrez le code du projet"></td>
          </tr>
          <tr>
            <th class="label-nom">Nom du Client</th>
            <td><input class="input-text" type="text" id="nomClient" placeholder="Entrez le nom du client"></td>
          </tr>
          <tr>
            <th class="label-description">Description du Projet</th>
            <td><input class="input-text" type="text" id="descriptionProjet" placeholder="Entrez la description"></td>
          </tr>
          <tr>
            <th class="label-location">Situation géographique du chantier</th>
            <td><input class="input-text" type="text" id="situationGeographique"
                placeholder="Entrez la situation géographique"></td>
          </tr>
          <tr>
            <th class="label-manager">Chef de Projet</th>
            <td><input class="input-text" type="text" id="chefProjet" placeholder="Entrez le nom du chef de projet">
            </td>
          </tr>
        </table>
      </div>

      <div class="table-container bg-white shadow-sm rounded-lg p-4">
        <input type="hidden" id="user_emet" value="<?php echo $_SESSION['user_id']; ?>">
        <h2 class="title">BESOIN EMMEDIAT(S) DU PROJET</h2>
        <table id="dynamic-table">
          <thead>
            <tr>
              <th>Désignation</th>
              <th class="narrow">Unité</th>
              <th class="narrow">Type</th>
              <th class="narrow">Quantité</th>
              <th class="actions-column">Actions</th>
            </tr>
          </thead>
          <tbody>
            <!-- La première ligne est ajoutée par JavaScript -->
          </tbody>
        </table>
        <div class="mt-4">
          <button id="add-row-button"
            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center" onclick="addRow()">
            <span class="material-icons mr-1">add</span> Ajouter un produit
          </button>
        </div>
      </div>

    </main>

    <!-- Include Footer -->
    <?php include_once '../components/footer.html'; ?>
  </div>

  <script>
    // Variables globales
    let existingDesignations = [];
    let projectCodes = [];
    let clientNames = [];
    let codeToClient = {}; // Mapping code projet -> nom client
    let clientToCode = {}; // Mapping nom client -> code projet
    let rowCounter = 0; // Compteur pour les ID de lignes

    // Fonction pour vérifier en temps réel si un produit existe
    function checkProductExists(inputElement) {
      const input = inputElement;
      const value = input.value;
      const normalizedValue = normalizeText(value);

      // Si le champ est vide, ne rien faire
      if (!normalizedValue) return;

      // Vérifier si le produit existe dans la liste des désignations connues
      const exists = existingDesignations.some(item =>
        item.normalizedName === normalizedValue
      );

      // Obtenir la cellule parente et gérer l'indicateur visuel
      const cell = input.closest('td');

      // Supprimer tout indicateur précédent
      const existingIndicator = cell.querySelector('.product-status');
      if (existingIndicator) {
        existingIndicator.remove();
      }

      // Ajouter un nouvel indicateur
      const indicator = document.createElement('div');
      indicator.className = 'product-status';
      indicator.style.position = 'absolute';
      indicator.style.right = '10px';
      indicator.style.top = '50%';
      indicator.style.transform = 'translateY(-50%)';
      indicator.style.fontSize = '20px';

      if (exists) {
        // Indicateur pour produit existant (coche verte)
        indicator.innerHTML = '✓';
        indicator.style.color = '#38a169'; // Vert
        // Réinitialiser le style de la bordure
        input.style.borderColor = '#d1d5db';
      } else {
        // Indicateur pour produit non existant (croix rouge)
        indicator.innerHTML = '✗';
        indicator.style.color = '#e53e3e'; // Rouge
        // Mettre en évidence le champ avec une bordure rouge
        input.style.borderColor = '#e53e3e';

        // Afficher une infobulle
        indicator.title = 'Ce produit n\'existe pas dans la base de données. Contactez le super administrateur.';

        // Option: Afficher une alerte
        Swal.fire({
          title: 'Produit non reconnu',
          text: 'Ce produit n\'existe pas dans la base de données. Veuillez contacter le super administrateur pour l\'ajouter.',
          icon: 'warning',
          confirmButtonText: 'Compris'
        });
      }

      // Ajouter l'indicateur à la cellule
      cell.appendChild(indicator);
    }

    // Fonction pour filtrer la liste des désignations
    function filterList(inputElement) {
      const input = inputElement;
      const filter = normalizeText(input.value);
      const list = input.nextElementSibling;

      list.innerHTML = '';

      if (!filter) return;

      const filteredOptions = existingDesignations.filter(option =>
        option.normalizedName.includes(filter)
      );

      filteredOptions.forEach(option => {
        const item = document.createElement('div');
        item.className = 'autocomplete-item';
        item.textContent = option.designation; // Afficher le nom original
        item.onclick = () => selectOption(option, input);
        list.appendChild(item);
      });
    }

    // Gestion de l'événement input
    function handleInput(event) {
      filterList(event.target);
    }

    // Ajout d'un gestionnaire d'événements pour la perte de focus
    function handleBlur(event) {
      // Attendre un court instant pour permettre la sélection d'un élément de la liste déroulante
      setTimeout(() => {
        // Vérifier uniquement si la liste d'autocomplétion n'est pas visible
        const list = event.target.nextElementSibling;
        if (list && list.children.length === 0) {
          checkProductExists(event.target);
        }
      }, 200);
    }

    // Fonction pour sélectionner une option dans la liste d'autocomplétion
    function selectOption(option, input) {
      // On peut utiliser l'objet option directement puisqu'il vient de la liste
      const selectedItem = option;

      if (selectedItem) {
        // Utiliser le nom original (non normalisé) pour l'affichage
        input.value = selectedItem.designation;

        // Récupérer et remplir le champ "unit" (2e colonne)
        const unitInput = input.closest('tr').querySelector('td:nth-child(2) input');
        if (unitInput) {
          unitInput.value = selectedItem.unit;
        }

        // Récupérer et remplir le champ "type" (3e colonne)
        const typeInput = input.closest('tr').querySelector('td:nth-child(3) input');
        if (typeInput) {
          typeInput.value = selectedItem.type;
        }

        // Supprimer toute indication d'erreur existante
        input.style.borderColor = '#d1d5db'; // Réinitialiser la couleur de bordure

        // Supprimer l'indicateur existant s'il y en a un
        const cell = input.closest('td');
        const existingIndicator = cell.querySelector('.product-status');
        if (existingIndicator) {
          existingIndicator.remove();
        }

        // Ajouter un indicateur de validation
        const indicator = document.createElement('div');
        indicator.className = 'product-status';
        indicator.style.position = 'absolute';
        indicator.style.right = '10px';
        indicator.style.top = '50%';
        indicator.style.transform = 'translateY(-50%)';
        indicator.style.fontSize = '20px';
        indicator.innerHTML = '✓';
        indicator.style.color = '#38a169'; // Vert
        cell.appendChild(indicator);
      }

      // Masquer la liste d'autocomplétion
      input.nextElementSibling.innerHTML = '';
    }

    // Supprimer une ligne spécifique du tableau
    function removeRow(rowId) {
      const row = document.getElementById(rowId);
      if (row) {
        // Confirmer avant de supprimer
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
            row.remove();

            // Vérifier s'il reste au moins une ligne
            const tbody = document.getElementById('dynamic-table').getElementsByTagName('tbody')[0];
            if (tbody.children.length === 0) {
              // S'il ne reste plus de lignes, en ajouter une nouvelle
              addRow();
            }
          }
        });
      }
    }

    // Ajouter une nouvelle ligne au tableau
    function addRow() {
      rowCounter++;
      const rowId = 'row-' + rowCounter;

      const table = document.getElementById('dynamic-table').getElementsByTagName('tbody')[0];
      const newRow = table.insertRow();
      newRow.id = rowId;

      // Cellule pour la désignation (avec autocomplétion)
      const designationCell = newRow.insertCell();
      designationCell.innerHTML = `
    <div class="relative">
      <input type="text" class="designation-input" placeholder="Saisir une désignation">
      <div class="autocomplete-list"></div>
    </div>
  `;

      // Cellule pour l'unité (readonly)
      const unitCell = newRow.insertCell();
      unitCell.classList.add('narrow');
      const unitInput = document.createElement('input');
      unitInput.type = 'text';
      unitInput.placeholder = '';
      unitInput.readOnly = true;
      unitCell.appendChild(unitInput);

      // Cellule pour le type (readonly)
      const typeCell = newRow.insertCell();
      typeCell.classList.add('narrow');
      const typeInput = document.createElement('input');
      typeInput.type = 'text';
      typeInput.placeholder = '';
      typeInput.readOnly = true;
      typeCell.appendChild(typeInput);

      // Cellule pour la quantité
      const quantityCell = newRow.insertCell();
      quantityCell.classList.add('narrow');
      const quantityInput = document.createElement('input');
      quantityInput.type = 'number';
      quantityInput.placeholder = '';
      quantityCell.appendChild(quantityInput);

      // Cellule pour les actions (bouton supprimer)
      const actionsCell = newRow.insertCell();
      actionsCell.classList.add('actions-column');
      const deleteButton = document.createElement('div');
      deleteButton.className = 'delete-btn mx-auto';
      deleteButton.innerHTML = '<span class="material-icons" style="font-size: 16px;">close</span>';
      deleteButton.onclick = function () { removeRow(rowId); };
      actionsCell.appendChild(deleteButton);

      // Mettre à jour les événements pour l'autocomplétion
      const designationInput = designationCell.querySelector('.designation-input');
      designationInput.addEventListener('input', handleInput);
      designationInput.addEventListener('blur', handleBlur);
    }

    // Mettre à jour tous les inputs pour l'autocomplétion
    function updateAllInputs() {
      document.querySelectorAll('.designation-input').forEach(input => {
        input.removeEventListener('input', handleInput);
        input.addEventListener('input', handleInput);

        input.removeEventListener('blur', handleBlur);
        input.addEventListener('blur', handleBlur);
      });
    }

    // Charger les données des projets existants
    async function loadProjectsData() {
      try {
        const response = await fetch('get_projects_data.php');
        const data = await response.json();

        if (data.success) {
          // Stocker les données reçues
          projectCodes = data.projectCodes;
          clientNames = data.clientNames;
          codeToClient = data.codeToClient;
          clientToCode = data.clientToCode;

          // Initialiser l'autocomplétion
          setupAutocompletion();
        } else {
          console.error('Erreur lors du chargement des données des projets:', data.error);
        }
      } catch (error) {
        console.error('Erreur:', error);
      }
    }

    // Configurer l'autocomplétion pour les champs
    function setupAutocompletion() {
      setupFieldAutocompletion('codeProjet', projectCodes, function (selectedCode) {
        // Quand un code est sélectionné, remplir automatiquement le nom du client
        const clientName = codeToClient[selectedCode];
        if (clientName) {
          document.getElementById('nomClient').value = clientName;
        }
      });

      setupFieldAutocompletion('nomClient', clientNames, function (selectedClient) {
        // Quand un client est sélectionné, remplir automatiquement le code du projet
        const projectCode = clientToCode[selectedClient];
        if (projectCode) {
          document.getElementById('codeProjet').value = projectCode;
        }
      });
    }

    // Configurer l'autocomplétion pour un champ spécifique
    function setupFieldAutocompletion(fieldId, suggestions, onSelectCallback) {
      const input = document.getElementById(fieldId);
      if (!input) return;

      // Créer la liste d'autocomplétion
      const autocompleteList = document.createElement('div');
      autocompleteList.className = 'autocomplete-list';
      autocompleteList.style.display = 'none';
      input.parentNode.style.position = 'relative';
      input.parentNode.appendChild(autocompleteList);

      // Ajouter des événements
      input.addEventListener('input', function () {
        const value = this.value.toLowerCase();
        autocompleteList.innerHTML = '';

        if (!value) {
          autocompleteList.style.display = 'none';
          return;
        }

        const matchingSuggestions = suggestions.filter(suggestion =>
          suggestion.toLowerCase().includes(value)
        );

        if (matchingSuggestions.length > 0) {
          autocompleteList.style.display = 'block';
          matchingSuggestions.slice(0, 5).forEach(suggestion => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.textContent = suggestion;
            item.addEventListener('click', function () {
              input.value = suggestion;
              autocompleteList.style.display = 'none';

              // Exécuter la fonction de callback après la sélection
              if (typeof onSelectCallback === 'function') {
                onSelectCallback(suggestion);
              }
            });
            autocompleteList.appendChild(item);
          });
        } else {
          autocompleteList.style.display = 'none';
        }
      });

      // Masquer la liste lorsqu'on clique ailleurs
      document.addEventListener('click', function (e) {
        if (e.target !== input) {
          autocompleteList.style.display = 'none';
        }
      });
    }

    // Cette fonction normalise les textes pour la comparaison en:
    // 1. Supprimant les espaces supplémentaires
    // 2. Convertissant en minuscules
    // 3. Supprimant les accents
    function normalizeText(text) {
      if (!text) return '';

      // Convertir en minuscules et supprimer les espaces en début/fin
      let normalized = text.toLowerCase().trim();

      // Supprimer les espaces supplémentaires entre les mots
      normalized = normalized.replace(/\s+/g, ' ');

      // Supprimer les accents (décomposer puis enlever les diacritiques)
      normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

      return normalized;
    }

    // Charger les désignations depuis la base de données
    function loadDesignations() {
      fetch('get_designations.php')
        .then(response => response.json())
        .then(data => {
          // Ajouter à chaque élément une version normalisée pour la comparaison
          existingDesignations = data.map(item => ({
            designation: item.product_name,
            unit: item.unit,
            type: item.type,
            normalizedName: normalizeText(item.product_name) // Ajouter cette propriété
          }));

          console.log('Données chargées et normalisées:', existingDesignations);
          updateAllInputs();
        })
        .catch(error => {
          console.error('Erreur lors du chargement des désignations:', error);
        });
    }

    // Mettre à jour la date et l'heure
    function updateDateTime() {
      const now = new Date();
      const formattedDate = `${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
      document.getElementById('date-time-display').textContent = formattedDate;
    }

    // Récupérer le prochain préfixe
    async function getNextPrefix() {
      try {
        const response = await fetch('get_next_prefix.php');
        const data = await response.json();
        if (data.success) {
          return data.prefix;
        } else {
          throw new Error('Erreur lors de la récupération du préfixe.');
        }
      } catch (error) {
        console.error('Erreur:', error);
        throw error;
      }
    }

    // Générer un ID d'expression
    function generateIdExpression(prefix) {
      const timestamp = Date.now();
      return `${prefix}-${timestamp}`;
    }

    // Vérifier si tous les produits existent
    function verifyAllProductsExist() {
      const rows = document.querySelectorAll('#dynamic-table tbody tr');
      const nonExistingProducts = [];

      rows.forEach((row, index) => {
        const designationInput = row.querySelector('td:nth-child(1) input');
        const designation = designationInput.value;
        const normalizedDesignation = normalizeText(designation);

        if (normalizedDesignation) {
          const exists = existingDesignations.some(item =>
            item.normalizedName === normalizedDesignation
          );

          if (!exists) {
            nonExistingProducts.push({
              designation: designation,
              row: index + 1
            });
          }
        }
      });

      return nonExistingProducts;
    }

    // Suivre l'état de soumission
    let isSubmitting = false;

    // Valider et soumettre les expressions
    async function validateExpressions() {

      // Éviter les soumissions multiples
      if (isSubmitting) {
        return;
      }

      // Marquer le début de la soumission
      isSubmitting = true;

      // Désactiver le bouton et afficher l'indicateur de chargement
      const validateBtn = document.getElementById('validate-btn');
      validateBtn.disabled = true;
      validateBtn.classList.add('loading');
      validateBtn.querySelector('.text').textContent = 'Traitement en cours...';

      try {
        // Vérifier si tous les champs requis sont remplis
        const codeProjet = document.getElementById('codeProjet').value.trim();
        const nomClient = document.getElementById('nomClient').value.trim();
        const descriptionProjet = document.getElementById('descriptionProjet').value.trim();
        const situationGeographique = document.getElementById('situationGeographique').value.trim();
        const chefProjet = document.getElementById('chefProjet').value.trim();

        if (!codeProjet || !nomClient || !descriptionProjet || !situationGeographique || !chefProjet) {
          Swal.fire({
            title: 'Champs incomplets',
            text: 'Veuillez remplir tous les champs d\'identification du projet.',
            icon: 'warning',
            confirmButtonText: 'OK'
          });
          resetButton();
          return;
        }

        // Vérifier s'il y a au moins une ligne de produit
        const rows = document.querySelectorAll('#dynamic-table tbody tr');
        if (rows.length === 0) {
          Swal.fire({
            title: 'Aucun produit',
            text: 'Veuillez ajouter au moins un produit.',
            icon: 'warning',
            confirmButtonText: 'OK'
          });
          resetButton();
          return;
        }

        // Vérifier si tous les produits existent dans la base de données
        const nonExistingProducts = verifyAllProductsExist();
        if (nonExistingProducts.length > 0) {
          // Construire un message avec les produits non existants
          const productsMessage = nonExistingProducts.map(product =>
            `- "${product.designation}" (ligne ${product.row})`
          ).join('<br>');

          Swal.fire({
            title: 'Produits non reconnus',
            html: `Les produits suivants n'existent pas dans la base de données :<br><br>${productsMessage}<br><br>Veuillez contacter le super administrateur pour ajouter ces produits.`,
            icon: 'error',
            confirmButtonText: 'OK'
          });
          resetButton();
          return;
        }

        // Vérifier si toutes les quantités sont renseignées
        let missingQuantities = false;
        rows.forEach((row, index) => {
          const designationInput = row.querySelector('td:nth-child(1) input');
          const quantityInput = row.querySelector('td:nth-child(4) input');

          if (designationInput.value.trim() && (!quantityInput.value || quantityInput.value <= 0)) {
            missingQuantities = true;
          }
        });

        if (missingQuantities) {
          Swal.fire({
            title: 'Quantités manquantes',
            text: 'Veuillez renseigner des quantités valides pour tous les produits.',
            icon: 'warning',
            confirmButtonText: 'OK'
          });
          resetButton();
          return;
        }

        // Générer un ID unique pour cette session de soumission
        const submissionId = Date.now().toString();
        localStorage.setItem('lastSubmissionId', submissionId);

        // Tout est valide, procéder à l'enregistrement
        const prefix = await getNextPrefix();
        const idExpression = generateIdExpression(prefix);

        // Données du projet
        const projectData = {
          idExpression: idExpression,
          code_projet: codeProjet,
          nom_client: nomClient,
          description_projet: descriptionProjet,
          sitgeo: situationGeographique,
          chefprojet: chefProjet,
          submissionId: submissionId // Ajouter l'ID de soumission
        };

        // Enregistrer les données du projet
        const projectResponse = await fetch('save_project.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(projectData)
        });
        const projectResult = await projectResponse.json();

        if (!projectResult.success) {
          throw new Error('Erreur lors de l\'enregistrement des données du projet: ' + (projectResult.error || ''));
        }

        // Données des expressions
        const expressionData = [];
        const userEmet = document.getElementById('user_emet').value;

        rows.forEach(row => {
          const designation = row.querySelector('td:nth-child(1) input').value.trim();
          const unit = row.querySelector('td:nth-child(2) input').value;
          const type = row.querySelector('td:nth-child(3) input').value;
          const quantity = row.querySelector('td:nth-child(4) input').value;

          if (designation) {
            expressionData.push({
              idExpression,
              userEmet,
              designation,
              unit,
              type,
              quantity,
              submissionId: submissionId // Ajouter l'ID de soumission
            });
          }
        });

        // Enregistrer les expressions
        const expressionResponse = await fetch('save_expressions.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(expressionData)
        });
        const expressionResult = await expressionResponse.json();

        if (expressionResult.success) {
          // Vérifier si des articles ont besoin d'achats supplémentaires
          if (expressionResult.needsPurchase) {
            // Construire la liste des articles à inclure dans l'alerte
            const itemsList = expressionResult.itemsNeedingPurchase.map(item =>
              `<tr>
          <td style="padding: 8px; border: 1px solid #ddd; text-align: left;">${item.designation}</td>
          <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${item.requested}</td>
          <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${item.available}</td>
          <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${item.to_purchase}</td>
        </tr>`
            ).join('');

            // Afficher une alerte SweetAlert avec la liste des articles dans un tableau
            Swal.fire({
              title: 'Information',
              html: `
          <p>Votre demande a été enregistrée avec succès.</p>
          <p>Cependant, certains articles nécessiteront des achats supplémentaires :</p>
          <table style="width: 100%; margin-top: 15px; border-collapse: collapse;">
            <thead>
              <tr style="background-color: #f3f3f3;">
                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Désignation</th>
                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Demandé</th>
                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Disponible</th>
                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">À acheter</th>
              </tr>
            </thead>
            <tbody>
              ${itemsList}
            </tbody>
          </table>
        `,
              icon: 'info',
              confirmButtonText: 'Compris',
              width: '600px'
            }).then(() => {
              // Après confirmation, continuer avec le processus
              proceedAfterSave(idExpression);
            });
          } else {
            // Si tous les articles sont disponibles, afficher un message de succès simple
            Swal.fire({
              title: 'Succès',
              text: 'Les expressions ont été enregistrées avec succès.',
              icon: 'success',
              confirmButtonText: 'OK'
            }).then(() => {
              proceedAfterSave(idExpression);
            });
          }
        } else {
          Swal.fire({
            title: 'Erreur',
            text: 'Une erreur est survenue lors de l\'enregistrement des expressions: ' + (expressionResult.error || ''),
            icon: 'error',
            confirmButtonText: 'OK'
          });
          resetButton();
        }
      } catch (error) {
        console.error('Erreur:', error);
        Swal.fire({
          title: 'Erreur',
          text: 'Une erreur est survenue: ' + error.message,
          icon: 'error',
          confirmButtonText: 'OK'
        });
        resetButton();
      }
    }

    // Fonction pour réinitialiser le bouton
    function resetButton() {
      isSubmitting = false;
      const validateBtn = document.getElementById('validate-btn');
      validateBtn.disabled = false;
      validateBtn.classList.remove('loading');
      validateBtn.querySelector('.text').textContent = 'Valider les expressions';
    }

    // Fonction pour continuer après la sauvegarde
    function proceedAfterSave(idExpression) {
      if (idExpression) {
        // Ouvrir le PDF en téléchargement dans un nouvel onglet
        window.open(`generate_pdf.php?id=${idExpression}&download=true`, '_blank');

        // Rediriger l'utilisateur vers dashboard.php
        window.location.href = 'dashboard.php';
      } else {
        Swal.fire({
          title: 'Erreur',
          text: 'ID d\'expression manquant pour la redirection.',
          icon: 'error',
          confirmButtonText: 'OK'
        });
      }
    }

    // Ajout de styles CSS pour les indicateurs
    const style = document.createElement('style');
    style.textContent = `
  .product-status {
    cursor: pointer;
    font-weight: bold;
  }
  
  .designation-input.invalid {
    border-color: #e53e3e !important;
    background-color: rgba(229, 62, 62, 0.05);
  }
  
  .designation-input.valid {
    border-color: #38a169 !important;
  }
`;
    document.head.appendChild(style);

    // Initialisation au chargement de la page
    document.addEventListener('DOMContentLoaded', () => {
      // Ajouter la première ligne au chargement
      addRow();

      loadDesignations();
      loadProjectsData();
      updateDateTime();
      setInterval(updateDateTime, 1000); // Mise à jour de l'heure toutes les secondes
    });
  </script>
</body>

</html>