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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

  <style>
    nav a{
      text-decoration: none;
    }
    
    .wrapper {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
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
  </style>
</head>
<body class="bg-gray-100">
  <div class="wrapper">
    <?php include_once '../components/navbar.php'; ?>

    <main class="flex-1 p-6">
      <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
        <button class="validate-btn">Tout les éléments</button>
        <div class="relative flex items-center mx-4 w-1/3">
          <input type="text" id="search-input" placeholder="Rechercher..." class="border border-gray-300 rounded-lg pl-10 pr-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-400" />
          <span class="material-icons absolute left-3 text-gray-500">search</span>
        </div>
        <div class="date-time">
          <span class="material-icons">calendar_today</span>
          <span id="date-time-display"></span>
        </div>
      </div>

      <div class="table-container">
        <table class="min-w-full bg-white border border-gray-200">
          <thead>
            <tr>
              <th class="px-4 py-2 border-b text-left">Designation</th>
              <th class="px-4 py-2 border-b text-left">Unit</th>
              <th class="px-4 py-2 border-b text-left">Type</th>
              <th class="px-4 py-2 border-b text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="designations-body">
            <!-- Les données seront insérées ici via JavaScript -->
          </tbody>
        </table>
      </div>

      <!-- Modal Modifier -->
      <div class="modal fade" id="edit-modal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="editModalLabel">Modifier Designation</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <form id="edit-form">
                <input type="hidden" id="edit-id">
                <div class="mb-3">
                  <label for="edit-designation" class="form-label">Designation</label>
                  <input type="text" id="edit-designation" class="form-control">
                </div>
                <div class="mb-3">
                  <label for="edit-unit" class="form-label">Unit</label>
                  <input type="text" id="edit-unit" class="form-control">
                </div>
                <div class="mb-3">
                  <label for="edit-type" class="form-label">Type</label>
                  <input type="text" id="edit-type" class="form-control">
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="button" class="btn btn-primary" id="save-changes">Enregistrer</button>
            </div>
          </div>
        </div>
      </div>

    </main>

    <?php include_once '../components/footer.html'; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      let designationsData = []; // Pour stocker les données récupérées

      // Fonction pour récupérer les données
      function fetchDesignations() {
        fetch('get_designations.php')
          .then(response => response.json())
          .then(data => {
            designationsData = data; // Stockez les données pour les filtrer
            renderTable(data); // Affichez les données dans le tableau
          });
      }

      // Fonction pour afficher les données dans le tableau
      function renderTable(data) {
        const tbody = document.getElementById('designations-body');
        tbody.innerHTML = ''; // Réinitialisez le contenu du tableau
        data.forEach(item => {
          const row = `
            <tr>
              <td class="border px-4 py-2">${item.product_name}</td>
              <td class="border px-4 py-2">${item.unit}</td>
              <td class="border px-4 py-2">${item.type}</td>
              <td class="border px-4 py-2 text-center">
                <span class="material-icons text-blue-500 cursor-pointer" onclick="openEditModal(${item.id}, '${item.product_name}', '${item.unit}', '${item.type}')">edit</span>
              </td>
            </tr>
          `;
          tbody.insertAdjacentHTML('beforeend', row);
        });
      }

      // Écoutez les changements dans le champ de recherche
      document.getElementById('search-input').addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase(); // Récupérez la valeur du champ de recherche
        const filteredData = designationsData.filter(item => {
          return item.product_name.toLowerCase().includes(searchTerm) || 
                 item.unit.toLowerCase().includes(searchTerm) || 
                 item.type.toLowerCase().includes(searchTerm);
        });
        renderTable(filteredData); // Affichez les données filtrées
      });

      // Appel initial pour récupérer et afficher les données
      fetchDesignations();

      // Modals pour éditer et supprimer
      window.openEditModal = function(id, designation, unit, type) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-designation').value = designation;
        document.getElementById('edit-unit').value = unit;
        document.getElementById('edit-type').value = type;
        const modal = new bootstrap.Modal(document.getElementById('edit-modal'));
        modal.show();
      };

      document.getElementById('save-changes').addEventListener('click', function() {
        const id = document.getElementById('edit-id').value;
        const designation = document.getElementById('edit-designation').value;
        const unit = document.getElementById('edit-unit').value;
        const type = document.getElementById('edit-type').value;

        // Envoyez une requête pour mettre à jour l'élément
        fetch('update-designation.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ id, designation, unit, type })
        }).then(response => response.json())
          .then(result => {
            if (result.success) {
              fetchDesignations(); // Récupérez les données mises à jour
              const modal = bootstrap.Modal.getInstance(document.getElementById('edit-modal'));
              modal.hide();
            } else {
              alert('Erreur lors de la mise à jour');
            }
          });
      });

      

      // Mise à jour de la date et de l'heure
      function updateDateTime() {
        const now = new Date();
        const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
        document.getElementById('date-time-display').textContent = now.toLocaleString('fr-FR', options);
      }
      setInterval(updateDateTime, 1000);
      updateDateTime();
    });
  </script>
</body>
</html>
