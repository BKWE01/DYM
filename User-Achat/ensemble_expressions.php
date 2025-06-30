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

// Reste du code pour la page protégée
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Expressions de Besoin | Service Achat</title>
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

    .page-title {
      color: #1e293b;
      font-weight: 700;
      font-size: 1.5rem;
      line-height: 2rem;
      letter-spacing: -0.025em;
    }

    .dashboard-btn {
      background-color: #f3f4f6;
      color: #4b5563;
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
      background-color: #e5e7eb;
      color: #1f2937;
      transform: translateY(-2px);
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

    .view-selector {
      background-color: white;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      padding: 0.625rem 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      color: #1e293b;
      transition: all 0.2s ease;
      cursor: pointer;
      outline: none;
    }

    .view-selector:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
    }

    .container-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
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
      padding: 10px 15px;
      border-bottom: 2px solid #e2e8f0;
      background-color: #f7fafc;
      font-weight: 600;
      color: #4a5568;
      font-size: 0.875rem;
    }

    table.dataTable tbody td {
      padding: 8px 15px;
      vertical-align: middle;
      border-bottom: 1px solid #e2e8f0;
      font-size: 0.875rem;
    }

    table.dataTable tbody tr.odd {
      background-color: #f9fafb;
    }

    table.dataTable tbody tr.even {
      background-color: #ffffff;
    }

    table.dataTable tbody tr:hover {
      background-color: #f1f5f9;
      cursor: pointer;
    }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
      color: #4a5568;
      margin-bottom: 1rem;
      font-size: 0.875rem;
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
      background: #3b82f6;
      color: white !important;
      border: 1px solid #3b82f6;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
      background: #2563eb;
      color: white !important;
      border: 1px solid #2563eb;
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

    .badge-pending {
      background-color: #eff6ff;
      color: #3b82f6;
    }

    .badge-validated {
      background-color: #ecfdf5;
      color: #10b981;
    }

    .loading-spinner {
      text-align: center;
      padding: 2rem;
      color: #64748b;
    }

    .export-btn {
      display: inline-flex;
      align-items: center;
      padding: 0.5rem 0.75rem;
      font-size: 0.75rem;
      font-weight: 600;
      color: white;
      border-radius: 0.375rem;
      transition: all 0.2s ease;
      margin-left: 0.5rem;
    }

    .export-excel {
      background-color: #10b981;
    }

    .export-excel:hover {
      background-color: #059669;
    }

    .export-pdf {
      background-color: #ef4444;
    }

    .export-pdf:hover {
      background-color: #dc2626;
    }

    .switch-view-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      font-weight: 600;
      font-size: 0.875rem;
      transition: all 0.2s ease;
      gap: 0.5rem;
    }

    .switch-view-btn.active {
      background-color: #eff6ff;
      color: #3b82f6;
      border: 1px solid #bfdbfe;
    }

    .switch-view-btn:not(.active) {
      background-color: #f9fafb;
      color: #6b7280;
      border: 1px solid #e5e7eb;
    }

    .switch-view-btn:not(.active):hover {
      background-color: #f3f4f6;
      color: #4b5563;
    }
  </style>
</head>

<body>
  <div class="wrapper">
    <?php include_once '../components/navbar_achat.php'; ?>

    <main class="flex-1 p-6">
      <!-- Top bar -->
      <div class="top-bar flex flex-col md:flex-row justify-between items-center p-4 gap-4 mb-6">
        <a href="dashboard.php" class="dashboard-btn">
          <span class="material-icons">arrow_back</span>
          <span>Retour au tableau de bord</span>
        </a>

        <div class="date-time">
          <span class="material-icons">event</span>
          <span id="date-time-display"></span>
        </div>
      </div>

      <!-- Main content -->
      <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <div class="container-header">
          <h1 class="page-title">Expressions de Besoin</h1>

          <div class="flex">
            <div class="flex mb-4">
              <button id="pending-view-btn" class="switch-view-btn active mr-2">
                <span class="material-icons" style="font-size: 18px;">assignment</span>
                Expressions non validées
              </button>
              <button id="validated-view-btn" class="switch-view-btn">
                <span class="material-icons" style="font-size: 18px;">assignment_turned_in</span>
                Expressions validées
              </button>
            </div>
          </div>
        </div>

        <!-- Export buttons -->
        <div class="flex justify-end mb-4">
          <button id="export-excel" class="export-btn export-excel">
            <span class="material-icons mr-1 text-sm">file_download</span>
            Excel
          </button>
          <button id="export-pdf" class="export-btn export-pdf">
            <span class="material-icons mr-1 text-sm">picture_as_pdf</span>
            PDF
          </button>
        </div>

        <!-- Pending Expressions View -->
        <div id="pending-expressions-container">
          <div id="pending-loading" class="loading-spinner">
            <span class="material-icons animate-spin inline-block mr-2">refresh</span>
            Chargement...
          </div>
          <div id="pending-table-container" class="overflow-x-auto hidden">
            <table id="pending-table" class="min-w-full">
              <thead>
                <tr>
                  <th>N° Expression</th>
                  <th>Client</th>
                  <th>Projet</th>
                  <th>Description</th>
                  <th>Statut</th>
                  <th>Date de création</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <!-- Les données seront chargées dynamiquement -->
              </tbody>
            </table>
          </div>
          <div id="pending-no-data" class="hidden text-center py-8">
            <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
            <p class="text-gray-500 font-medium">Aucune expression non validée trouvée</p>
          </div>
        </div>

        <!-- Validated Expressions View -->
        <div id="validated-expressions-container" class="hidden">
          <div id="validated-loading" class="loading-spinner">
            <span class="material-icons animate-spin inline-block mr-2">refresh</span>
            Chargement...
          </div>
          <div id="validated-table-container" class="overflow-x-auto hidden">
            <table id="validated-table" class="min-w-full">
              <thead>
                <tr>
                  <th>N° Expression</th>
                  <th>Client</th>
                  <th>Projet</th>
                  <th>Statut</th>
                  <th>Date de création</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <!-- Les données seront chargées dynamiquement -->
              </tbody>
            </table>
          </div>
          <div id="validated-no-data" class="hidden text-center py-8">
            <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
            <p class="text-gray-500 font-medium">Aucune expression validée trouvée</p>
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
    document.addEventListener('DOMContentLoaded', function () {
      // Update date and time
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

      // View switching (Pending vs Validated)
      $('#pending-view-btn').on('click', function () {
        $(this).addClass('active');
        $('#validated-view-btn').removeClass('active');
        $('#pending-expressions-container').removeClass('hidden');
        $('#validated-expressions-container').addClass('hidden');
      });

      $('#validated-view-btn').on('click', function () {
        $(this).addClass('active');
        $('#pending-view-btn').removeClass('active');
        $('#validated-expressions-container').removeClass('hidden');
        $('#pending-expressions-container').addClass('hidden');
      });

      // Common DataTable options
      const dataTableOptions = {
        responsive: true,
        language: {
          url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
        },
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
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
        ]
      };

      // Initialize DataTable for pending expressions
      function initializePendingTable(data) {
        $('#pending-loading').hide();

        if (!data || data.length === 0) {
          $('#pending-no-data').removeClass('hidden');
          return;
        }

        $('#pending-table-container').removeClass('hidden');

        const table = $('#pending-table').DataTable({
          ...dataTableOptions,
          data: data,
          columns: [
            { data: 'idExpression', title: 'N° Expression' },
            {
              data: 'nom_client',
              title: 'Client',
              render: function (data) {
                return data || 'Non spécifié';
              }
            },
            {
              data: 'code_projet',
              title: 'Projet',
              render: function (data) {
                return data || 'Non spécifié';
              }
            },
            {
              data: 'description_projet',
              title: 'Description',
              render: function (data) {
                return data ? (data.length > 50 ? data.substring(0, 50) + '...' : data) : 'Non spécifié';
              }
            },
            {
              data: null,
              title: 'Statut',
              render: function () {
                return '<span class="badge badge-pending">En attente</span>';
              }
            },
            {
              data: 'created_at',
              title: 'Date de création',
              render: function (data) {
                const date = new Date(data);
                return date.toLocaleDateString('fr-FR', {
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric'
                });
              }
            },
            {
              data: null,
              title: 'Actions',
              render: function (data) {
                return `<button class="view-details bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs" data-id="${data.idExpression}">
                  <span class="material-icons align-middle text-xs">visibility</span>
                  Détails
                </button>`;
              }
            }
          ]
        });

        // Click event for view-details button
        $('#pending-table').on('click', '.view-details', function (e) {
          e.stopPropagation();
          const id = $(this).data('id');
          window.location.href = `expression_details.php?id=${id}`;
        });

        // Click event for rows
        $('#pending-table tbody').on('click', 'tr', function () {
          const data = table.row(this).data();
          window.location.href = `expression_details.php?id=${data.idExpression}`;
        });
      }

      // Initialize DataTable for validated expressions
      function initializeValidatedTable(data) {
        $('#validated-loading').hide();

        if (!data || data.length === 0) {
          $('#validated-no-data').removeClass('hidden');
          return;
        }

        $('#validated-table-container').removeClass('hidden');

        const table = $('#validated-table').DataTable({
          ...dataTableOptions,
          data: data,
          columns: [
            { data: 'idExpression', title: 'N° Expression' },
            {
              data: 'nom_client',
              title: 'Client',
              render: function (data) {
                return data || 'Non spécifié';
              }
            },
            {
              data: 'code_projet',
              title: 'Projet',
              render: function (data) {
                return data || 'Non spécifié';
              }
            },
            {
              data: null,
              title: 'Statut',
              render: function () {
                return '<span class="badge badge-validated">Validée</span>';
              }
            },
            {
              data: 'created_at',
              title: 'Date de création',
              render: function (data) {
                const date = new Date(data);
                return date.toLocaleDateString('fr-FR', {
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric'
                });
              }
            },
            {
              data: null,
              title: 'Actions',
              render: function (data) {
                return `<button class="view-pdf bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs" data-id="${data.idExpression}">
                  <span class="material-icons align-middle text-xs">picture_as_pdf</span>
                  Voir PDF
                </button>`;
              }
            }
          ]
        });

        // Click event for view-pdf button
        $('#validated-table').on('click', '.view-pdf', function (e) {
          e.stopPropagation();
          const id = $(this).data('id');
          window.open(`generate_pdf.php?id=${id}`, '_blank');
        });

        // Click event for rows
        $('#validated-table tbody').on('click', 'tr', function () {
          const data = table.row(this).data();
          window.open(`generate_pdf.php?id=${data.idExpression}`, '_blank');
        });
      }

      // Fetch data for pending expressions
      function fetchPendingExpressions() {
        $.ajax({
          url: 'get_ens_expressions.php', // Assurez-vous que cette API renvoie également la colonne description_projet
          type: 'GET',
          dataType: 'json',
          success: function (data) {
            initializePendingTable(data);
          },
          error: function (xhr, status, error) {
            $('#pending-loading').hide();
            $('#pending-no-data')
              .removeClass('hidden')
              .find('p')
              .text('Erreur lors du chargement des données');
            console.error('Error fetching pending expressions:', error);
          }
        });
      }

      // Fetch data for validated expressions
      function fetchValidatedExpressions() {
        $.ajax({
          url: 'get_ens_expressions_vaide.php', // Assurez-vous que cette API renvoie également la colonne description_projet
          type: 'GET',
          dataType: 'json',
          success: function (data) {
            initializeValidatedTable(data);
          },
          error: function (xhr, status, error) {
            $('#validated-loading').hide();
            $('#validated-no-data')
              .removeClass('hidden')
              .find('p')
              .text('Erreur lors du chargement des données');
            console.error('Error fetching validated expressions:', error);
          }
        });
      }

      // Export to Excel button
      $('#export-excel').on('click', function () {
        if ($('#pending-expressions-container').is(':visible')) {
          $('#pending-table').DataTable().button(0).trigger();
        } else {
          $('#validated-table').DataTable().button(0).trigger();
        }
      });

      // Export to PDF button
      $('#export-pdf').on('click', function () {
        if ($('#pending-expressions-container').is(':visible')) {
          $('#pending-table').DataTable().button(1).trigger();
        } else {
          $('#validated-table').DataTable().button(1).trigger();
        }
      });

      // Initialize data
      fetchPendingExpressions();
      fetchValidatedExpressions();
    });
  </script>
</body>

</html>