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

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
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
      width: 250px; /* Adjust the width as needed */
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
      z-index: 1000;
    }
    .autocomplete-item {
      padding: 8px;
      cursor: pointer;
    }
    .autocomplete-item:hover {
      background-color: #f0f0f0;
    }
    .create-item {
      font-style: italic;
      padding: 8px;
      cursor: pointer;
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
  </style>
</head>
<body class="bg-gray-100">

  <div class="wrapper flex flex-col min-h-screen">
    <!-- Include Navbar -->
    <?php include_once '../components/navbar.php'; ?>

    <main class="flex-1 p-6">
      <!-- Main Content -->
      <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
        <button class="validate-btn" onclick="validateExpressions()">Valider les expressions</button>
        <h2 class="header-title">SYSTEME DE MANAGEMENT INTEGRE QUALITE-SECURITE-ENVIRONEMENT</h2>
        <div class="date-time">
          <span class="material-icons">calendar_today</span>
          <span id="date-time-display"></span>
        </div>
      </div>

      <div class="container">
        <table class="demande-table">
            <tr>
                <th id="service-label">SERVICE DEMANDEUR</th>
                <th id="nom-prenoms-label">NOM & PRÉNOMS DEMANDEUR</th>
                <th id="date-demande-label">DATE DEMANDE</th>
            </tr>
            <tr>
                <td><input type="text" class="form-input" id="service_demandeur" name="service_demandeur"></td>
                <td><input type="text" class="form-input" id="nom_prenoms" name="nom_prenoms"></td>
                <td><input type="date" class="form-date" id="date_demande" readonly></td>
            </tr>
            <tr>
                <td colspan="3" class="motif">
                    <label for="motif_demande" class="form-label" id="motif-label"><u><em>Motif de la demande :</em></u></label>
                    <textarea id="motif_demande" class="form-textarea" name="motif_demande"></textarea>
                </td>
            </tr>
        </table>
    </div>
      <div class="table-container bg-white shadow-sm rounded-lg ">
        <table id="dynamic-table">
          <thead>
            <tr>
              <th>Désignation de l'article</th>
              <th class="narrow">Caractéristique</th>
              <th class="narrow">Quantité démandé</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <div class="relative">
                  <input type="text" class="designation-input" placeholder="Saisir une désignation">
                  <div class="autocomplete-list"></div>
                </div>
              </td>
              <td><input type="text" placeholder=""></td>
              <td><input type="number" placeholder=""></td>
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

  <!-- Modal -->
  <div id="create-modal" class="modal">
    <div class="modal-content">
      <h2 class="text-xl font-semibold mb-4">Créer une nouvelle désignation</h2>
      <input type="text" id="new-designation" class="mb-4 p-2 border border-gray-300 rounded w-full" placeholder="Désignation">
      <input type="text" id="unit-input" class="mb-4 p-2 border border-gray-300 rounded w-full" placeholder="Unité">
      <button onclick="saveNewDesignation()" class="bg-blue-500 text-white py-2 px-4 rounded">Enregistrer</button>
      <button onclick="closeModal()" class="ml-2 bg-gray-500 text-white py-2 px-4 rounded">Annuler</button>
    </div>
  </div>

  <!-- Scripts -->
  <script>
    let existingDesignations = [];

    function filterList(inputElement) {
      const input = inputElement;
      const filter = input.value.toLowerCase();
      const list = input.nextElementSibling;
      
      list.innerHTML = '';

      if (!filter) return;

      const filteredOptions = existingDesignations.filter(option =>
        option.designation.toLowerCase().includes(filter)
      );

      filteredOptions.forEach(option => {
        const item = document.createElement('div');
        item.className = 'autocomplete-item';
        item.textContent = option.designation;
        item.onclick = () => selectOption(option, input);
        list.appendChild(item);
      });

      if (!filteredOptions.length) {
        const createItem = document.createElement('div');
        createItem.className = 'create-item';
        createItem.textContent = `Créer "${input.value}"`;
        createItem.onclick = () => {
          openModal(input.value);
          list.innerHTML = ''; // Clear the list
        };
        list.appendChild(createItem);
      }
    }

    function handleInput(event) {
      filterList(event.target);
    }

    function selectOption(option, input) {
      const selectedItem = existingDesignations.find(item => item.designation === option.designation);
      if (selectedItem) {
        input.value = option.designation;
        const unitInput = input.closest('tr').querySelector('td:nth-child(2) input');
        if (unitInput) {
          unitInput.value = selectedItem.unit;
        }
      }
      input.nextElementSibling.innerHTML = '';
    }

    function addRow() {
      const table = document.getElementById('dynamic-table').getElementsByTagName('tbody')[0];
      const newRow = table.insertRow();
      const cells = ['Désignation', 'Caractéristique', 'Quantité demandé',];
      cells.forEach((text, index) => {
        const cell = newRow.insertCell();
        const input = document.createElement('input');
        input.type = index === 2 ? 'number' : 'text';
        input.placeholder = text;
        cell.appendChild(input);
        if (index === 1 || index === 2) {
          cell.classList.add('narrow');
        }

        if (text === 'Désignation') {
          const list = document.createElement('div');
          list.className = 'autocomplete-list';
          cell.innerHTML = `<div class="relative"><input type="text" class="designation-input" placeholder="${text}">${list.outerHTML}</div>`;
        }
      });
      updateAllInputs();
    }

    function updateAllInputs() {
      document.querySelectorAll('.designation-input').forEach(input => {
        input.removeEventListener('input', handleInput);
        input.addEventListener('input', handleInput);
      });
    }

    function openModal(value = '') {
      document.getElementById('new-designation').value = value;
      document.getElementById('create-modal').classList.add('active');
    }

    function closeModal() {
      document.getElementById('create-modal').classList.remove('active');
    }
    

    function saveNewDesignation() {
        const designation = document.getElementById('new-designation').value;
        const unit = document.getElementById('unit-input').value;

        if (designation && unit) {
            // Envoi des données au serveur
            fetch('save_designation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'designation': designation,
                    'unit': unit
                })
            })
            .then(response => response.text())
            .then(data => {
                alert(data); // Affiche la réponse du serveur
                closeModal();

                // Mise à jour des champs existants
                document.querySelectorAll('.designation-input').forEach(input => {
                    if (input.value === designation) {
                        const unitInput = input.closest('tr').querySelector('td:nth-child(2) input');
                        if (unitInput) {
                            unitInput.value = unit;
                        }
                    }
                });

                loadDesignations(); // Recharger les désignations après ajout
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de l\'enregistrement de la désignation.');
            });
        } else {
            alert('Veuillez remplir tous les champs.');
        }
    }


    document.addEventListener('DOMContentLoaded', () => {
      loadDesignations();
    });

    function loadDesignations() {
      fetch('get_designations.php')
        .then(response => response.json())
        .then(data => {
          existingDesignations = data.map(item => ({
            designation: item.designation,
            unit: item.unit
          }));
          updateAllInputs();
        });
    }

    function validateExpressions() {
      // Your validation logic here
      alert('Validation des expressions');
    }

    function updateDateTime() {
      const now = new Date();
      const formattedDate = `${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
      document.getElementById('date-time-display').textContent = formattedDate;
    }

    setInterval(updateDateTime, 1000); // Update time every second


    async function submitExpressions() {
        const idBesoin = await generateIdBesoin(); // Générer l'idBesoin avec la fonction async

        const demandeurData = {
            idBesoin, // Utiliser l'idBesoin généré
            service_demandeur: document.getElementById('service_demandeur').value,
            nom_prenoms: document.getElementById('nom_prenoms').value,
            date_demande: document.getElementById('date_demande').value,
            motif_demande: document.getElementById('motif_demande').value
        };

        try {
            const demandeurResponse = await fetch('save_demandeur.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(demandeurData)
            });
            const demandeurResult = await demandeurResponse.json();

            if (!demandeurResult.success) {
                throw new Error('Erreur lors de l\'enregistrement du demandeur.');
            }

            const rows = document.querySelectorAll('#dynamic-table tbody tr');
            const besoinsData = [];

            rows.forEach(row => {
                const designation = row.querySelector('td:nth-child(1) input').value;
                const caracteristique = row.querySelector('td:nth-child(2) input').value;
                const qt_demande = row.querySelector('td:nth-child(3) input').value;

                if (designation) {
                    besoinsData.push({
                        idBesoin, // Utiliser idBesoin du demandeur
                        designation_article: designation,
                        caracteristique: caracteristique,
                        qt_demande: qt_demande,
                    });
                }
            });

            const besoinsResponse = await fetch('save_besoins.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(besoinsData)
            });

            const besoinsResult = await besoinsResponse.json();

            if (besoinsResult.success) {
                alert('Les expressions de besoins ont été enregistrées avec succès.');
                window.location.reload();
            } else {
                alert('Erreur lors de l\'enregistrement des besoins.');
            }
        } catch (error) {
            alert('Une erreur est survenue : ' + error.message);
        }
      }

    // Fonction pour générer l'idBesoin
    async function generateIdBesoin() {
        try {
            const response = await fetch('get_last_idBesoin.php'); // Appel à l'API pour récupérer le dernier idBesoin
            const result = await response.json();

            let lastId = result.lastId || 1; // Si aucun idBesoin, on commence à 1
            lastId = (lastId + 1).toString().padStart(5, '0'); // Incrémenter et ajouter des zéros devant

            const now = new Date();
            const datePart = now.toISOString().split('T')[0].replace(/-/g, ''); // AAAAMMJJ

            return `${lastId}-EXP_B-${datePart}`; // Format final de l'idBesoin
        } catch (error) {
            console.error('Erreur lors de la récupération de l\'idBesoin :', error);
            throw new Error('Impossible de générer l\'idBesoin.');
        }
     }


      function validateExpressions() {
          submitExpressions();
      }

  </script>
  <script>
    // JavaScript pour afficher la date actuelle dans le champ date_demande
    document.addEventListener("DOMContentLoaded", function() {
      var today = new Date().toISOString().split('T')[0];
      document.getElementById('date_demande').value = today;
    });
  </script>
</body>
</html>
