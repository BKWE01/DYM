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
    header("Location: ./../index.php");
    exit();
}

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
    .table-container th, .table-container td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #e2e8f0;
    }
    .table-container th {
      background-color: #f7fafc;
      font-weight: 600;
    }
    .table-container td {
      width: auto; /* Default width */
    }
    .table-container .narrow {
      width: 200px; /* Adjust the width as needed */
    }
    /* Colonne pour les actions (bouton supprimer) */
    .table-container .action-column {
      width: 60px; /* Colonne plus étroite pour le bouton supprimer */
      text-align: center;
    }
    .table-container tr:last-child td {
      border-bottom: none;
    }
    .table-container input {
      border: 1px solid #d1d5db; /* Couleur de bordure gris clair */
      padding: 8px;
      width: 100%;
      box-sizing: border-box;
      background-color: #f8f9fa;
      border-radius: 4px;
    }
    .table-container input:focus {
      border-color: #3490dc; /* Bordure bleue lorsque l'élément est focus */
      outline: none; /* Supprimer le contour par défaut du navigateur */
    }
    
    /* Champ désactivé après sélection */
    .table-container input:disabled {
      background-color: #e5e7eb;
      cursor: not-allowed;
      opacity: 0.7;
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
      z-index: 100;
    }
    .modal-content {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      width: 400px;
      max-width: 90%;
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
      border: 2px solid #38a169; /* Green border */
      color: #38a169; /* Green text */
      padding: 8px 18px;
      border-radius: 8px;
      text-align: center;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
      background-color: transparent; /* No background */
      transition: color 0.3s, border-color 0.3s;
    }
    .validate-btn:hover {
      color: #2f855a; /* Darker green text */
      border-color: #2f855a; /* Darker green border */
      background-color: #f0fff4;
    }
    .date-time {
      display: flex;
      align-items: center;
      font-size: 16px;
      color: #4a5568;
      border: 2px solid #e2e8f0; /* Light gray border */
      border-radius: 8px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Elegant font-family */
      padding: 8px 18px;
    }
    .date-time .material-icons {
      margin-right: 12px;
      font-size: 22px; /* Larger icon size */
      color: #2d3748; /* Icon color matching the text */
    }
    .date-time span {
      font-weight: 500;
      line-height: 1.4; /* Better spacing for readability */
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

    .demande-table th, .demande-table td {
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
        pointer-events: none; /* Empêche toute interaction */
    }

    .form-textarea {
        height: 100px; /* Ajuster selon besoin */
        resize: none;
    }

    .form-label {
        display: block;
        margin-bottom: 5px;
        text-align: left;
        padding-left: 10px;
        font-weight: bold;
    }

    /* Désactiver tout effet de hover */
    .form-input:hover,
    .form-textarea:hover {
        border: none;
    }

    .form-input:focus,
    .form-textarea:focus {
        outline: none;
    }

    .header-title{
      font-weight: 300;
    }
    
    .required-field::after {
      content: " *";
      color: red;
    }
    
    .hidden-product-id {
      display: none;
    }
    
    /* Style pour le bouton supprimer */
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
  </style>
</head>
<body class="bg-gray-100">

  <div class="wrapper flex flex-col min-h-screen">
    <!-- Include Navbar -->
    <?php include_once '../components/navbar_achat.php'; ?>

    <main class="flex-1 p-6">
      <!-- Main Content -->
      <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
        <button class="validate-btn" onclick="validateExpressions()">Valider les expressions</button>
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
                <td><input type="text" class="form-input" id="service_demandeur" name="service_demandeur" required></td>
                <td><input type="text" class="form-input" id="nom_prenoms" name="nom_prenoms" required></td>
                <td><input type="date" class="form-date" id="date_demande" readonly></td>
            </tr>
            <tr>
                <td colspan="3" class="motif">
                    <label for="motif_demande" class="form-label required-field" id="motif-label"><u><em>Motif de la demande :</em></u></label>
                    <textarea id="motif_demande" class="form-textarea" name="motif_demande" required></textarea>
                </td>
            </tr>
        </table>
    </div>
      <div class="table-container bg-white shadow-sm rounded-lg ">
        <table id="dynamic-table">
          <thead>
            <tr>
              <th>Désignation de l'article</th>
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
              <td><input type="number" class="quantity-input" placeholder="Quantité" min="1"></td>
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
    <?php include_once '../components/footer.html'; ?>
  </div>

  <!-- Scripts -->
  <script>
    // Stockage des produits
    let products = [];
    
    // Charger les produits depuis la base de données
    document.addEventListener('DOMContentLoaded', () => {
      loadProducts();
      updateDateTime();
      setInterval(updateDateTime, 1000); // Update time every second
      
      // Initialiser la date actuelle
      var today = new Date().toISOString().split('T')[0];
      document.getElementById('date_demande').value = today;
    });
    
    // Charger les produits depuis le serveur
    function loadProducts() {
      fetch('get_products.php')
        .then(response => response.json())
        .then(data => {
          products = data;
          setupProductInputs();
        })
        .catch(error => {
          console.error('Erreur lors du chargement des produits:', error);
        });
    }
    
    // Configurer tous les champs de saisie de produits
    function setupProductInputs() {
      document.querySelectorAll('.product-input').forEach(input => {
        input.removeEventListener('input', handleProductInput);
        input.addEventListener('input', handleProductInput);
      });
    }
    
    // Gestionnaire d'événements pour la saisie de produit
    function handleProductInput(event) {
      const input = event.target;
      const filter = input.value.toLowerCase();
      const dropdown = input.nextElementSibling.nextElementSibling;
      
      dropdown.innerHTML = '';
      
      if (!filter) {
        dropdown.style.display = 'none';
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
          <span class="product-stock">Stock: ${product.quantity} ${product.unit}</span>
        `;
        item.onclick = () => selectProduct(product, input);
        dropdown.appendChild(item);
      });
    }
    
    // Sélectionner un produit dans la liste
    function selectProduct(product, input) {
      // Remplir les champs avec les informations du produit
      input.value = product.product_name;
      input.dataset.barcode = product.barcode;
      input.dataset.unit = product.unit;
      input.dataset.quantity = product.quantity;
      
      // Stocker l'ID du produit dans le champ caché
      const productIdInput = input.parentElement.querySelector('.product-id');
      if (productIdInput) {
        productIdInput.value = product.id;
      }
      
      // Désactiver le champ après sélection
      input.disabled = true;
      
      // Fermer la liste déroulante
      const dropdown = input.nextElementSibling.nextElementSibling;
      dropdown.innerHTML = '';
      dropdown.style.display = 'none';
      
      // Mettre le focus sur le champ de quantité
      const quantityInput = input.closest('tr').querySelector('.quantity-input');
      if (quantityInput) {
        quantityInput.focus();
      }
    }
    
    // Ajouter une nouvelle ligne au tableau
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
      
      // Cellule de quantité
      const quantityCell = newRow.insertCell(1);
      quantityCell.className = 'narrow';
      quantityCell.innerHTML = '<input type="number" class="quantity-input" placeholder="Quantité" min="1">';
      
      // Cellule avec bouton de suppression
      const actionCell = newRow.insertCell(2);
      actionCell.className = 'action-column';
      actionCell.innerHTML = `
        <button type="button" class="delete-row-btn" onclick="deleteRow(this)">
          <span class="material-icons" style="font-size: 18px;">delete</span>
        </button>
      `;
      
      // Réinitialiser les gestionnaires d'événements
      setupProductInputs();
    }
    
    // Supprimer une ligne du tableau
    function deleteRow(button) {
      const row = button.closest('tr');
      
      // Si c'est la seule ligne du tableau, la vider plutôt que la supprimer
      const tbody = document.getElementById('dynamic-table').getElementsByTagName('tbody')[0];
      if (tbody.rows.length === 1) {
        const productInput = row.querySelector('.product-input');
        const quantityInput = row.querySelector('.quantity-input');
        const productIdInput = row.querySelector('.product-id');
        
        // Réinitialiser les champs
        productInput.value = '';
        productInput.disabled = false;
        quantityInput.value = '';
        if (productIdInput) {
          productIdInput.value = '';
        }
        
        // Supprimer les données du dataset
        delete productInput.dataset.barcode;
        delete productInput.dataset.unit;
        delete productInput.dataset.quantity;
      } else {
        // Sinon, supprimer la ligne
        row.parentNode.removeChild(row);
      }
    }
    
    // Mettre à jour l'affichage de la date et de l'heure
    function updateDateTime() {
      const now = new Date();
      const formattedDate = `${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
      document.getElementById('date-time-display').textContent = formattedDate;
    }
    
    // Valider et soumettre le formulaire
    async function validateExpressions() {
      // Vérifier les champs obligatoires
      const service = document.getElementById('service_demandeur').value;
      const nomPrenoms = document.getElementById('nom_prenoms').value;
      const motif = document.getElementById('motif_demande').value;
      
      if (!service || !nomPrenoms || !motif) {
        alert('Veuillez remplir tous les champs obligatoires.');
        return;
      }
      
      // Vérifier qu'au moins un produit a été ajouté
      const productInputs = document.querySelectorAll('.product-input');
      const quantityInputs = document.querySelectorAll('.quantity-input');
      let hasValidProduct = false;
      
      for (let i = 0; i < productInputs.length; i++) {
        if (productInputs[i].value && quantityInputs[i].value) {
          hasValidProduct = true;
          break;
        }
      }
      
      if (!hasValidProduct) {
        alert('Veuillez ajouter au moins un produit avec une quantité.');
        return;
      }
      
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
      
      try {
        // Enregistrer les données du demandeur
        const demandeurResponse = await fetch('save_demandeur.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(demandeurData)
        });
        
        const demandeurResult = await demandeurResponse.json();
        
        if (!demandeurResult.success) {
          throw new Error('Erreur lors de l\'enregistrement du demandeur.');
        }
        
        // Préparer les données des besoins
        const besoinsData = [];
        
        for (let i = 0; i < productInputs.length; i++) {
          if (productInputs[i].value && quantityInputs[i].value) {
            const productIdInput = productInputs[i].parentElement.querySelector('.product-id');
            const productId = productIdInput ? productIdInput.value : '';
            
            besoinsData.push({
              idBesoin,
              product_id: productId,
              designation_article: productInputs[i].value,
              qt_demande: quantityInputs[i].value,
              user_emet: <?php echo $_SESSION['user_id']; ?>
            });
          }
        }
        
        // Enregistrer les données des besoins
        const besoinsResponse = await fetch('save_besoins.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(besoinsData)
        });
        
        const besoinsResult = await besoinsResponse.json();
        
        if (besoinsResult.success) {
          alert('Les expressions ont été enregistrées avec succès.');
          
          // Ouvrir le PDF dans un nouvel onglet
          window.open(`expression_syst_pdf.php?id=${idBesoin}`, '_blank');
          
          // Rediriger vers la page d'expressions
          window.location.href = 'expression_systeme.php';
        } else {
          alert('Erreur lors de l\'enregistrement des besoins.');
        }
      } catch (error) {
        alert('Une erreur est survenue: ' + error.message);
      }
    }
    
    // Générer un ID de besoin unique
    async function generateIdBesoin() {
      try {
        const response = await fetch('get_last_idBesoin.php');
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
  </script>
</body>
</html>