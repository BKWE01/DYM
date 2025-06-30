<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
  // Rediriger vers index.php si non connecté
  header("Location: ./../index.php");
  exit();
}

// Reste du code pour la page protégée
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Expressions de Besoin | Bureau d'Études</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- DataTables CSS -->
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
  <link rel="stylesheet" type="text/css"
    href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

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

    .top-bar {
      background-color: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
      transition: all 0.3s ease;
    }

    .dashboard-btn {
      background-color: #f0f9ff;
      color: #0369a1;
      border: none;
      padding: 0.625rem 1rem;
      border-radius: 0.5rem;
      font-weight: 600;
      font-size: 0.875rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s ease;
    }

    .dashboard-btn:hover {
      background-color: #e0f2fe;
      color: #0284c7;
      transform: translateY(-2px);
    }

    .dashboard-btn:active {
      transform: translateY(0);
    }

    .dashboard-btn .material-icons {
      font-size: 1.25rem;
    }

    .date-time {
      background-color: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      padding: 0.625rem 1rem;
      font-size: 0.875rem;
      color: #64748b;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .date-time .material-icons {
      color: #64748b;
      font-size: 1.25rem;
    }

    .page-title {
      color: #1e293b;
      font-weight: 700;
      font-size: 1.5rem;
      line-height: 2rem;
      letter-spacing: -0.025em;
    }

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

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
      color: #4a5568;
      margin-bottom: 1rem;
    }

    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
      border: 1px solid #e2e8f0;
      border-radius: 0.375rem;
      padding: 0.375rem 0.75rem;
      box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
      padding: 0.375rem 0.75rem;
      margin-left: 0.25rem;
      border: 1px solid #e2e8f0;
      border-radius: 0.375rem;
      background-color: #ffffff;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
      background: #4299e1;
      color: white !important;
      border: 1px solid #4299e1;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
      background: #2b6cb0;
      color: white !important;
      border: 1px solid #2b6cb0;
    }

    .badge {
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

    .badge-blue {
      background-color: #3b82f6;
    }

    .badge-green {
      background-color: #10b981;
    }

    .badge-orange {
      background-color: #f59e0b;
    }

    .view-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.375rem 0.75rem;
      font-size: 0.75rem;
      font-weight: 600;
      color: #fff;
      background-color: #3b82f6;
      border-radius: 0.375rem;
      transition: all 0.2s;
    }

    .view-btn:hover {
      background-color: #2563eb;
    }

    .view-btn .material-icons {
      font-size: 1rem;
      margin-right: 0.25rem;
    }
  </style>
</head>

<body>
  <div class="wrapper">
    <?php include_once '../components/navbar.php'; ?>

    <main class="flex-1 p-6">
      <!-- Top Bar -->
      <div class="top-bar p-4 mb-6 flex flex-wrap justify-between items-center gap-4">
        <a href="dashboard.php" class="dashboard-btn">
          <span class="material-icons">arrow_back</span>
          <span>Retour au tableau de bord</span>
        </a>

        <div class="date-time">
          <span class="material-icons">calendar_today</span>
          <span id="date-time-display"></span>
        </div>
      </div>

      <!-- Main Content -->
      <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <div class="flex flex-wrap justify-between items-center mb-6">
          <h1 class="page-title mb-2">Toutes les expressions de besoin</h1>

          <div class="flex space-x-2">
            <button id="export-excel" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
              <span class="material-icons align-middle text-sm mr-1">file_download</span>
              Excel
            </button>
            <button id="export-pdf" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
              <span class="material-icons align-middle text-sm mr-1">picture_as_pdf</span>
              PDF
            </button>
          </div>
        </div>

        <div class="bg-blue-50 text-blue-700 p-3 mb-4 rounded-md flex items-center justify-between">
          <div class="flex items-center">
            <span class="material-icons mr-2">info</span>
            <span>Seuls les projets actifs sont affichés ici. Les projets terminés ne sont pas inclus.</span>
          </div>
          <a href="completed_projects.php"
            class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded text-sm flex items-center">
            <span class="material-icons text-sm mr-1">check_circle</span>
            Voir les projets terminés
          </a>
        </div>

        <div id="loading-spinner" class="text-center py-8">
          <span class="material-icons animate-spin inline-block mr-2">refresh</span>
          Chargement...
        </div>

        <div id="expressions-table-container" class="overflow-x-auto hidden">
          <table id="expressions-table" class="min-w-full">
            <thead>
              <tr>
                <th>N° Expression</th>
                <th>Client</th>
                <th>Projet</th>
                <th>Description</th>
                <th>Date de création</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <!-- Data will be loaded dynamically -->
            </tbody>
          </table>
        </div>

        <div id="no-data-message" class="hidden">
          <div class="text-center py-8">
            <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
            <p class="text-gray-500 font-medium">Aucune expression de besoin trouvée</p>
          </div>
        </div>
      </div>
    </main>

    <?php include_once '../components/footer.html'; ?>
  </div>

  <!-- jQuery and DataTables scripts -->
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
      // Date and time updater
      function updateDateTime() {
        const now = new Date();
        const options = {
          weekday: 'long',
          year: 'numeric',
          month: 'long',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        };
        const formattedDate = now.toLocaleDateString('fr-FR', options);
        document.getElementById('date-time-display').textContent = formattedDate;
      }

      updateDateTime();
      setInterval(updateDateTime, 60000); // Update every minute

      // Fetch expressions
      $.ajax({
        url: 'get_ens_expressions_vaide.php',
        type: 'GET',
        dataType: 'json',
        success: function (data) {
          $('#loading-spinner').hide();

          if (!data || data.length === 0) {
            $('#no-data-message').removeClass('hidden');
            return;
          }

          $('#expressions-table-container').removeClass('hidden');

          // Initialize DataTable
          var table = $('#expressions-table').DataTable({
            data: data,
            responsive: true,
            language: {
              url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
            },
            dom: 'Bfrtip',
            buttons: [
              {
                extend: 'excel',
                text: 'Excel',
                title: 'Expressions_de_Besoin',
                className: 'hidden'
              },
              {
                extend: 'pdf',
                text: 'PDF',
                title: 'Expressions_de_Besoin',
                className: 'hidden'
              }
            ],
            columns: [
              {
                data: 'idExpression',
                title: 'N° Expression'
              },
              {
                data: 'nom_client',
                title: 'Client',
                render: function (data, type, row) {
                  return data || 'Non spécifié';
                }
              },
              {
                data: 'code_projet',
                title: 'Projet',
                render: function (data, type, row) {
                  return data || 'Non spécifié';
                }
              },
              {
                data: 'description_projet',
                title: 'Description',
                render: function (data, type, row) {
                  return data || 'Non spécifié';
                }
              },
              {
                data: 'created_at',
                title: 'Date de création',
                render: function (data, type, row) {
                  if (type === 'display' || type === 'filter') {
                    const date = new Date(data);
                    return date.toLocaleDateString('fr-FR', {
                      day: '2-digit',
                      month: '2-digit',
                      year: 'numeric'
                    });
                  }
                  return data;
                }
              },
              {
                data: null,
                title: 'Actions',
                orderable: false,
                render: function (data, type, row) {
                  return `<a href="generate_pdf.php?id=${row.idExpression}" target="_blank" class="view-btn">
                    <span class="material-icons">visibility</span>
                    Voir PDF
                  </a>`;
                }
              }
            ],
            order: [[3, 'desc']], // Sort by date desc
            pageLength: 10
          });

          // Exportation vers Excel
          $('#export-excel').on('click', function () {
            table.button('.buttons-excel').trigger();
          });

          // Exportation vers PDF
          $('#export-pdf').on('click', function () {
            table.button('.buttons-pdf').trigger();
          });
        },
        error: function (xhr, status, error) {
          $('#loading-spinner').hide();
          $('#no-data-message').removeClass('hidden').find('p').text('Erreur lors du chargement des données');
          console.error('Erreur lors de la récupération des expressions:', error);
        }
      });
    });
  </script>
</body>

</html>