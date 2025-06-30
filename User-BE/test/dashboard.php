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

// Connexion à la base de données
include_once '../database/connection.php';

// Récupérer le rôle de l'utilisateur
$user_id = $_SESSION['user_id'];
$userRole = '';

try {
  $stmt = $pdo->prepare("SELECT role FROM users_exp WHERE id = :user_id");
  $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
  $stmt->execute();
  $userData = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($userData) {
    $userRole = $userData['role'];
  }
} catch (PDOException $e) {
  // Gérer l'erreur silencieusement
  error_log("Erreur lors de la récupération du rôle utilisateur: " . $e->getMessage());
}

// Récupérer les statistiques récentes pour le tableau de bord
try {
  // 1. Nombre de matériaux reçus récemment (derniers 7 jours)
  $receivedQuery = "SELECT COUNT(*) as count FROM achats_materiaux 
                     WHERE status = 'reçu' 
                     AND date_reception >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
  $receivedStmt = $pdo->prepare($receivedQuery);
  $receivedStmt->execute();
  $receivedCount = $receivedStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Nombre de commandes annulées récemment (derniers 7 jours)
$canceledQuery = "SELECT COUNT(DISTINCT project_id, designation) as count 
                 FROM canceled_orders_log 
                 WHERE canceled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
  $canceledStmt = $pdo->prepare($canceledQuery);
  $canceledStmt->execute();
  $canceledCount = $canceledStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

  // 3. Nombre de retours de matériaux récents (derniers 7 jours)
  $returnsQuery = "SELECT COUNT(*) as count FROM stock_movement 
                    WHERE movement_type = 'transfer' 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
  $returnsStmt = $pdo->prepare($returnsQuery);
  $returnsStmt->execute();
  $returnsCount = $returnsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

  // 4. Nombre total d'expressions de besoin actives
  $activeExpressionsQuery = "SELECT COUNT(DISTINCT idExpression) as count 
                            FROM expression_dym 
                            WHERE NOT EXISTS (
                                SELECT 1 FROM project_status 
                                WHERE project_status.idExpression = expression_dym.idExpression 
                                AND project_status.status = 'completed'
                            )";
  $activeExpressionsStmt = $pdo->prepare($activeExpressionsQuery);
  $activeExpressionsStmt->execute();
  $activeExpressionsCount = $activeExpressionsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

} catch (PDOException $e) {
  // Gérer l'erreur silencieusement
  error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
  $receivedCount = 0;
  $canceledCount = 0;
  $returnsCount = 0;
  $activeExpressionsCount = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | Bureau d'Études</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- DataTables CSS -->
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
  <link rel="stylesheet" type="text/css"
    href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

  <!-- SweetAlert2 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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

    .date-time {
      display: flex;
      align-items: center;
      font-size: 14px;
      color: #64748b;
      background-color: #f1f5f9;
      border-radius: 8px;
      padding: 10px 16px;
      font-weight: 500;
    }

    .date-time .material-icons {
      margin-right: 10px;
      font-size: 20px;
      color: #475569;
    }

    .view-all-btn {
      background-color: white;
      border: 1px solid #e2e8f0;
      color: #4b5563;
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .view-all-btn:hover {
      background-color: #f1f5f9;
      color: #1e293b;
      transform: translateY(-1px);
    }

    .card-container {
      background-color: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
      overflow: hidden;
    }

    .section-header {
      display: flex;
      align-items: center;
      padding: 16px 20px;
      border-bottom: 1px solid #f1f5f9;
    }

    .section-title {
      font-size: 16px;
      font-weight: 600;
      color: #1e293b;
    }

    .badge-new {
      display: inline-block;
      background-color: #10b981;
      color: white;
      font-size: 10px;
      font-weight: 600;
      padding: 2px 6px;
      border-radius: 9999px;
      margin-left: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    @keyframes pulse {
      0% {
        opacity: 1;
      }

      50% {
        opacity: 0.7;
      }

      100% {
        opacity: 1;
      }
    }

    .pulse {
      animation: pulse 2s infinite;
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

    .badge-orange {
      background-color: #f59e0b;
    }

    .badge-red {
      background-color: #ef4444;
    }

    .badge-purple {
      background-color: #8b5cf6;
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

    .card-tab {
      padding: 10px 16px;
      background-color: #f8fafc;
      color: #64748b;
      border-bottom: 2px solid transparent;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .card-tab.active {
      background-color: white;
      color: #3b82f6;
      border-bottom: 2px solid #3b82f6;
    }

    .card-tab:hover:not(.active) {
      background-color: #f1f5f9;
      color: #475569;
    }

    .tab-content {
      display: none;
      padding: 16px;
    }

    .tab-content.active {
      display: block;
    }

    .loading-spinner {
      text-align: center;
      padding: 2rem;
      color: #64748b;
    }

    /* Styles pour les boutons d'action */
    .action-btn {
      padding: 0.25rem;
      border-radius: 0.25rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin: 0 0.125rem;
      cursor: pointer;
      transition: all 0.2s;
    }

    .edit-btn {
      background-color: #3b82f6;
      color: white;
    }

    .edit-btn:hover {
      background-color: #2563eb;
    }

    .delete-btn {
      background-color: #ef4444;
      color: white;
    }

    .delete-btn:hover {
      background-color: #dc2626;
    }

    /* Styles pour rendre les tableaux responsives */
    .dtr-details {
      width: 100%;
    }

    .dtr-title {
      font-weight: 600;
      color: #4a5568;
      padding-right: 0.5rem;
    }

    table.dataTable.dtr-inline.collapsed>tbody>tr>td.dtr-control:before {
      background-color: #3b82f6;
    }

    /* Styles pour les cartes de statistiques */
    .stat-card {
      background-color: white;
      border-radius: 0.5rem;
      padding: 1.5rem;
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
      transition: all 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .stat-card .icon {
      width: 3rem;
      height: 3rem;
      border-radius: 9999px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
    }

    .stat-card .title {
      font-size: 0.875rem;
      font-weight: 500;
      color: #6b7280;
      margin-bottom: 0.5rem;
    }

    .stat-card .value {
      font-size: 1.5rem;
      font-weight: 700;
      color: #1f2937;
    }

    /* Styles pour la section des événements récents */
    .event-item {
      padding: 1rem;
      border-bottom: 1px solid #e5e7eb;
      transition: all 0.2s ease;
    }

    .event-item:hover {
      background-color: #f9fafb;
    }

    .event-item:last-child {
      border-bottom: none;
    }

    .event-indicator {
      width: 0.75rem;
      height: 0.75rem;
      border-radius: 50%;
      margin-right: 0.75rem;
    }

    .event-title {
      font-weight: 600;
      font-size: 0.875rem;
      color: #1f2937;
      margin-bottom: 0.25rem;
    }

    .event-description {
      font-size: 0.75rem;
      color: #6b7280;
    }

    .event-time {
      font-size: 0.75rem;
      color: #9ca3af;
      text-align: right;
      white-space: nowrap;
    }
  </style>
</head>

<body>
  <div class="wrapper">
    <?php include_once '../components/navbar.php'; ?>

    <main class="flex-1 p-6">
      <!-- Top Bar -->
      <div class="top-bar p-4 mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <button class="dashboard-btn">
          <span class="material-icons" style="font-size: 18px;">dashboard</span>
          Bureau d'Études
        </button>

        <div class="date-time">
          <span class="material-icons">calendar_today</span>
          <span id="date-time-display"></span>
        </div>
      </div>

      <!-- Statistiques récentes -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Matériaux reçus -->
        <div class="stat-card">
          <div class="flex items-center justify-between mb-4">
            <div class="icon bg-green-100">
              <span class="material-icons text-green-600">inventory</span>
            </div>
            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">7 derniers jours</span>
          </div>
          <div class="title">Matériaux reçus</div>
          <div class="value"><?php echo $receivedCount; ?></div>
          <div class="mt-2 text-sm">
            <a href="received_materials.php" class="text-green-600 hover:underline flex items-center">
              <span class="material-icons text-sm mr-1">visibility</span>
              Voir détails
            </a>
          </div>
        </div>

        <!-- Commandes annulées -->
        <div class="stat-card">
          <div class="flex items-center justify-between mb-4">
            <div class="icon bg-red-100">
              <span class="material-icons text-red-600">cancel</span>
            </div>
            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">7 derniers jours</span>
          </div>
          <div class="title">Commandes annulées</div>
          <div class="value"><?php echo $canceledCount; ?></div>
          <div class="mt-2 text-sm">
            <a href="canceled_orders.php" class="text-red-600 hover:underline flex items-center">
              <span class="material-icons text-sm mr-1">visibility</span>
              Voir détails
            </a>
          </div>
        </div>

        <!-- Retours matériaux -->
        <div class="stat-card">
          <div class="flex items-center justify-between mb-4">
            <div class="icon bg-blue-100">
              <span class="material-icons text-blue-600">swap_horiz</span>
            </div>
            <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">7 derniers jours</span>
          </div>
          <div class="title">Retours matériaux</div>
          <div class="value"><?php echo $returnsCount; ?></div>
          <div class="mt-2 text-sm">
            <a href="project_return.php" class="text-blue-600 hover:underline flex items-center">
              <span class="material-icons text-sm mr-1">visibility</span>
              Gérer retours
            </a>
          </div>
        </div>

        <!-- Expressions actives -->
        <div class="stat-card">
          <div class="flex items-center justify-between mb-4">
            <div class="icon bg-purple-100">
              <span class="material-icons text-purple-600">description</span>
            </div>
            <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">En cours</span>
          </div>
          <div class="title">Expressions actives</div>
          <div class="value"><?php echo $activeExpressionsCount; ?></div>
          <div class="mt-2 text-sm">
            <a href="ensemble_expressions.php" class="text-purple-600 hover:underline flex items-center">
              <span class="material-icons text-sm mr-1">visibility</span>
              Voir toutes
            </a>
          </div>
        </div>
      </div>

      <!-- Header Section -->
      <div class="mb-6 flex flex-wrap justify-between items-center">
        <div class="flex items-center">
          <h2 class="text-2xl font-bold text-gray-800">Vue d'ensemble</h2>
          <span class="badge-new pulse ml-2">Live</span>
        </div>

        <a href="ensemble_expressions.php">
          <button class="view-all-btn">
            <span>Toutes les expressions</span>
            <span class="material-icons" style="font-size: 18px;">chevron_right</span>
          </button>
        </a>
      </div>

      <div class="mb-2 flex flex-wrap items-center justify-between">
        <div class="text-sm text-gray-500 flex items-center my-2">
          <span class="bg-blue-100 px-3 py-1 rounded-full flex items-center">
            <span class="material-icons mr-2">info</span>
            Seuls les projets actifs sont affichés. Les projets terminés sont disponibles dans
            <a href="completed_projects.php" class="text-indigo-600 hover:underline ml-2"> projets terminés</a>.
          </span>
        </div>
        <div class="flex gap-2">
          <!-- Ajouter ces deux nouveaux boutons -->
          <a href="received_materials.php"
            class="inline-flex items-center px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-sm shadow-sm transition-colors">
            <span class="material-icons mr-2">inventory</span>
            Matériaux reçus
          </a>
          <a href="canceled_orders.php"
            class="inline-flex items-center px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-sm shadow-sm transition-colors">
            <span class="material-icons mr-2">cancel</span>
            Commandes annulées
          </a>
          <!-- Garder le bouton existant -->
          <a href="completed_projects.php"
            class="inline-flex items-center px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-sm shadow-sm transition-colors">
            <span class="material-icons mr-2">check</span>
            Voir les projets terminés
          </a>
        </div>
      </div>

      <!-- Affichage en deux colonnes : tableau d'expression à gauche et événements récents à droite -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Colonne des expressions (occupe 2 colonnes sur grand écran) -->
        <div class="lg:col-span-2">
          <!-- Main Content -->
          <div class="card-container">
            <div class="flex border-b overflow-x-auto">
              <div class="card-tab active" data-tab="today">
                <span class="material-icons mr-2"
                  style="color: #3b82f6; font-size: 20px; vertical-align: middle;">today</span>
                Aujourd'hui
              </div>
              <div class="card-tab" data-tab="week">
                <span class="material-icons mr-2"
                  style="color: #8b5cf6; font-size: 20px; vertical-align: middle;">date_range</span>
                Cette Semaine
              </div>
              <div class="card-tab" data-tab="month">
                <span class="material-icons mr-2"
                  style="color: #ec4899; font-size: 20px; vertical-align: middle;">calendar_month</span>
                Ce Mois
              </div>
            </div>

            <!-- Tab Content -->
            <div id="today-content" class="tab-content active">
              <div id="today-loading" class="loading-spinner">
                <span class="material-icons animate-spin inline-block mr-2">refresh</span>
                Chargement...
              </div>
              <div id="today-table-container" class="overflow-x-auto hidden w-full">
                <table id="today-table" class="min-w-full display responsive nowrap">
                  <thead>
                    <tr>
                      <th>N° Expression</th>
                      <th>Client</th>
                      <th>Projet</th>
                      <th>Description</th>
                      <th>Date</th>
                      <?php if ($userRole === 'super_admin'): ?>
                        <th>Actions</th>
                      <?php endif; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Les données seront chargées dynamiquement -->
                  </tbody>
                </table>
              </div>
              <div id="today-no-data" class="hidden text-center py-8">
                <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
                <p class="text-gray-500 font-medium">Aucune expression de besoin aujourd'hui</p>
              </div>
            </div>

            <div id="week-content" class="tab-content">
              <div id="week-loading" class="loading-spinner">
                <span class="material-icons animate-spin inline-block mr-2">refresh</span>
                Chargement...
              </div>
              <div id="week-table-container" class="overflow-x-auto hidden w-full">
                <table id="week-table" class="min-w-full display responsive nowrap">
                  <thead>
                    <tr>
                      <th>N° Expression</th>
                      <th>Client</th>
                      <th>Projet</th>
                      <th>Description</th>
                      <th>Date</th>
                      <?php if ($userRole === 'super_admin'): ?>
                        <th>Actions</th>
                      <?php endif; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Les données seront chargées dynamiquement -->
                  </tbody>
                </table>
              </div>
              <div id="week-no-data" class="hidden text-center py-8">
                <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
                <p class="text-gray-500 font-medium">Aucune expression de besoin cette semaine</p>
              </div>
            </div>

            <div id="month-content" class="tab-content">
              <div id="month-loading" class="loading-spinner">
                <span class="material-icons animate-spin inline-block mr-2">refresh</span>
                Chargement...
              </div>
              <div id="month-table-container" class="overflow-x-auto hidden w-full">
                <table id="month-table" class="min-w-full display responsive nowrap">
                  <thead>
                    <tr>
                      <th>N° Expression</th>
                      <th>Client</th>
                      <th>Projet</th>
                      <th>Description</th>
                      <th>Date</th>
                      <?php if ($userRole === 'super_admin'): ?>
                        <th>Actions</th>
                      <?php endif; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Les données seront chargées dynamiquement -->
                  </tbody>
                </table>
              </div>
              <div id="month-no-data" class="hidden text-center py-8">
                <span class="material-icons text-gray-400 text-5xl mb-2">inbox</span>
                <p class="text-gray-500 font-medium">Aucune expression de besoin ce mois-ci</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Colonne des événements récents (occupe 1 colonne sur grand écran) -->
        <div class="lg:col-span-1">
          <div class="card-container">
            <div class="section-header flex justify-between items-center">
              <div class="flex items-center">
                <h3 class="section-title">Événements récents</h3>
                <span class="badge-new ml-2 pulse">Live</span>
              </div>

              <!-- Filtre par type d'événement -->
              <div class="flex">
                <select id="event-filter" class="text-xs border border-gray-300 rounded-md px-2 py-1">
                  <option value="all">Tous</option>
                  <option value="received">Matériaux reçus</option>
                  <option value="canceled">Commandes annulées</option>
                  <option value="updated">Expressions modifiées</option>
                  <option value="return">Retours de matériel</option>
                  <!-- Ajout d'options spécifiques si nécessaire -->
                </select>
              </div>
            </div>

            <div class="p-4">
              <!-- Section des événements récents -->
              <div id="recent-events" class="space-y-4">
                <!-- Les événements seront chargés dynamiquement -->
                <div class="text-center py-4">
                  <span class="material-icons animate-spin inline-block text-gray-400">refresh</span>
                  <p class="text-gray-500 mt-2">Chargement des événements...</p>
                </div>
              </div>

              <!-- Pagination des événements -->
              <div id="events-pagination" class="flex justify-between items-center mt-4 border-t pt-4">
                <button id="prev-events"
                  class="text-sm text-gray-600 px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed">
                  <span class="material-icons text-sm align-middle">arrow_back</span> Précédent
                </button>
                <span id="pagination-info" class="text-xs text-gray-500">Page <span id="current-page">1</span> / <span
                    id="total-pages">1</span></span>
                <button id="next-events"
                  class="text-sm text-gray-600 px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed">
                  Suivant <span class="material-icons text-sm align-middle">arrow_forward</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Bouton pour actualiser les événements -->
          <div class="mt-4 text-center">
            <button id="refresh-events-btn"
              class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center mx-auto">
              <span class="material-icons mr-2">refresh</span>
              Actualiser les événements
            </button>
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

  <!-- Scripts pour l'export et la responsive -->
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

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

      // Variables pour stocker les références aux tables
      let todayTable = null;
      let weekTable = null;
      let monthTable = null;

      // Chargements des données initiaux
      loadData('today');

      // Chargement des événements récents
      loadRecentEvents();

      // Tab switching - MODIFIÉ POUR CORRIGER LE RESPONSIVE
      $('.card-tab').on('click', function () {
        const tabId = $(this).data('tab');

        // Activer l'onglet
        $('.card-tab').removeClass('active');
        $(this).addClass('active');

        // Afficher le contenu correspondant
        $('.tab-content').removeClass('active');
        $(`#${tabId}-content`).addClass('active');

        // Charger les données de l'onglet si elles ne sont pas déjà chargées
        if (tabId === 'week' && weekTable === null) {
          loadData('week');
        } else if (tabId === 'month' && monthTable === null) {
          loadData('month');
        }

        // Ajuster les tableaux pour le responsive dans l'onglet actif
        if (tabId === 'today' && todayTable !== null) {
          todayTable.columns.adjust().responsive.recalc();
        } else if (tabId === 'week' && weekTable !== null) {
          weekTable.columns.adjust().responsive.recalc();
        } else if (tabId === 'month' && monthTable !== null) {
          monthTable.columns.adjust().responsive.recalc();
        }
      });

      // Gestionnaire pour actualiser les événements récents
      $('#refresh-events-btn').on('click', function () {
        loadRecentEvents();
      });

      // DataTable common options
      const dataTableOptions = {
        responsive: true,
        language: {
          url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
        },
        pageLength: 5,
        lengthMenu: [5, 10, 25],
        dom: 'Bfrtip',
        buttons: [
          {
            extend: 'excel',
            text: 'Excel',
            className: 'hidden'
          },
          {
            extend: 'pdf',
            text: 'PDF',
            className: 'hidden'
          }
        ],
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
              return data || 'Non spécifié';
            }
          },
          {
            data: 'created_at',
            title: 'Date',
            render: function (data) {
              const date = new Date(data);
              return date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
              });
            }
          }
      <?php if ($userRole === 'super_admin'): ?>,
            {
              data: null,
              title: 'Actions',
              orderable: false,
              render: function (data, type, row) {
                return `
            <div class="flex space-x-2 justify-center">
              <button class="action-btn edit-btn p-1 rounded" data-id="${row.idExpression}" title="Modifier">
                <span class="material-icons" style="font-size: 18px;">edit</span>
              </button>
              <button class="action-btn delete-btn p-1 rounded" data-id="${row.idExpression}" title="Supprimer">
                <span class="material-icons" style="font-size: 18px;">delete</span>
              </button>
            </div>
          `;
              }
            }
      <?php endif; ?>
        ],
        order: [[4, 'desc']] // Tri par date décroissante
      };

      // Fonction pour initialiser une DataTable - MODIFIÉE POUR STOCKER LES RÉFÉRENCES
      function initializeDataTable(period, data) {
        $(`#${period}-loading`).hide();

        if (!data || data.length === 0) {
          $(`#${period}-no-data`).removeClass('hidden');
          return;
        }

        $(`#${period}-table-container`).removeClass('hidden');

        // Initialiser la table avec les options
        const table = $(`#${period}-table`).DataTable({
          ...dataTableOptions,
          data: data
        });

        // Stocker la référence à la table dans la variable appropriée
        if (period === 'today') {
          todayTable = table;
        } else if (period === 'week') {
          weekTable = table;
        } else if (period === 'month') {
          monthTable = table;
        }

        // Forcer un recalcul du responsive si la tab est active
        if ($(`#${period}-content`).hasClass('active')) {
          setTimeout(function () {
            table.columns.adjust().responsive.recalc();
          }, 10);
        }

        // Ajout de l'action au clic sur une ligne
        $(`#${period}-table tbody`).on('click', 'tr', function (e) {
          // Vérifier si le clic provient d'un bouton d'action ou d'un contrôle responsive
          if ($(e.target).closest('.action-btn, .dtr-control, td.dtr-control:before').length === 0) {
            const data = table.row(this).data();
            window.open(`generate_pdf.php?id=${data.idExpression}`, '_blank');
          }
        });

        <?php if ($userRole === 'super_admin'): ?>
          // Gestionnaire pour le bouton d'édition
          $(`#${period}-table tbody`).on('click', '.edit-btn', function (e) {
            e.stopPropagation(); // Empêcher la propagation de l'événement
            const expressionId = $(this).data('id');
            window.location.href = `edit_expression.php?id=${expressionId}`;
          });

          // Gestionnaire pour le bouton de suppression
          $(`#${period}-table tbody`).on('click', '.delete-btn', function (e) {
            e.stopPropagation(); // Empêcher la propagation de l'événement
            const expressionId = $(this).data('id');

            Swal.fire({
              title: 'Confirmation de suppression',
              text: "Êtes-vous sûr de vouloir supprimer cette expression de besoin? Cette action est irréversible.",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#d33',
              cancelButtonColor: '#3085d6',
              confirmButtonText: 'Oui, supprimer',
              cancelButtonText: 'Annuler'
            }).then((result) => {
              if (result.isConfirmed) {
                // Appel AJAX pour supprimer l'expression
                $.ajax({
                  url: 'api_expression/delete_expression.php',
                  type: 'POST',
                  data: { id: expressionId },
                  dataType: 'json',
                  success: function (response) {
                    if (response.success) {
                      Swal.fire(
                        'Supprimé!',
                        'L\'expression de besoin a été supprimée.',
                        'success'
                      ).then(() => {
                        // Recharger la page pour actualiser les données
                        location.reload();
                      });
                    } else {
                      Swal.fire(
                        'Erreur!',
                        response.message || 'Une erreur est survenue lors de la suppression.',
                        'error'
                      );
                    }
                  },
                  error: function () {
                    Swal.fire(
                      'Erreur!',
                      'Une erreur est survenue lors de la communication avec le serveur.',
                      'error'
                    );
                  }
                });
              }
            });
          });
        <?php endif; ?>

        // Configurer les boutons d'export pour ce tableau
        $(`#export-excel-${period}`).on('click', function () {
          const exportBtn = table.buttons('.buttons-excel');
          if (exportBtn.length > 0) {
            exportBtn.trigger();
          }
        });

        $(`#export-pdf-${period}`).on('click', function () {
          const exportBtn = table.buttons('.buttons-pdf');
          if (exportBtn.length > 0) {
            exportBtn.trigger();
          }
        });
      }

      // Fonction pour charger les données - MODIFIÉE POUR LE CHARGEMENT DIFFÉRÉ
      function loadData(period) {
        $.ajax({
          url: `get_expressions_vaide.php?period=${period}`,
          type: 'GET',
          dataType: 'json',
          success: function (data) {
            initializeDataTable(period, data);
          },
          error: function (xhr, status, error) {
            $(`#${period}-loading`).hide();
            $(`#${period}-no-data`)
              .removeClass('hidden')
              .find('p')
              .text('Erreur lors du chargement des données');
            console.error(`Erreur lors de la récupération des expressions (${period}):`, error);
          }
        });
      }

      // Variables globales pour la pagination des événements
      let allEvents = [];
      let currentPage = 1;
      let eventsPerPage = 3;

      // Initialisation de la variable globale currentFilter
      let currentFilter = 'all';

      // Fonction pour charger les événements récents
      function loadRecentEvents() {
        // Initialiser les variables de pagination
        let currentPage = 1; // Définir currentPage ici
        let eventsPerPage = 5;
        let allEvents = [];

        $('#recent-events').html(`
    <div class="text-center py-4">
      <span class="material-icons animate-spin inline-block text-gray-400">refresh</span>
      <p class="text-gray-500 mt-2">Chargement des événements...</p>
    </div>
  `);

        // Réinitialiser la pagination
        currentPage = 1;

        $.ajax({
          url: 'api_events/get_recent_events.php',
          type: 'GET',
          dataType: 'json',
          success: function (response) {
            if (response.success && response.events && response.events.length > 0) {
              // Stocker tous les événements
              allEvents = response.events;

              // Mettre à jour les événements filtrés
              filterAndDisplayEvents();
            } else {
              $('#recent-events').html(`
          <div class="text-center py-4">
            <span class="material-icons text-gray-400 text-4xl">event_busy</span>
            <p class="text-gray-500 mt-2">Aucun événement récent trouvé</p>
            <p class="text-sm text-gray-400">${response.message || 'Aucune donnée disponible'}</p>
          </div>
        `);

              // Désactiver la pagination
              updatePaginationControls(0, 0);
            }
          },
          error: function (xhr, status, error) {
            let errorMsg = '';
            try {
              // Essayer de parser le message d'erreur si disponible
              const errorResponse = JSON.parse(xhr.responseText);
              errorMsg = errorResponse.message || error;
            } catch (e) {
              // Si ce n'est pas du JSON, afficher l'erreur brute
              errorMsg = error || 'Erreur de communication avec le serveur';
            }

            $('#recent-events').html(`
        <div class="text-center py-4">
          <span class="material-icons text-red-500 text-4xl">error_outline</span>
          <p class="text-red-500 mt-2">Erreur lors du chargement des événements</p>
          <p class="text-gray-500 text-sm mt-1">${errorMsg}</p>
        </div>
      `);

            // Désactiver la pagination
            updatePaginationControls(0, 0);

            console.error('Erreur AJAX:', xhr.responseText);
          }
        });

        // Filtrer et afficher les événements avec pagination
        function filterAndDisplayEvents() {
          // Filtrer les événements selon le type sélectionné
          let filteredEvents = allEvents;

          if (window.currentFilter && window.currentFilter !== 'all') {
            filteredEvents = allEvents.filter(event => event.event_type === window.currentFilter);
          }

          // Calculer le nombre total de pages
          const totalPages = Math.max(1, Math.ceil(filteredEvents.length / eventsPerPage));

          // Ajuster la page courante si nécessaire
          if (currentPage > totalPages) {
            currentPage = totalPages;
          }

          // Calculer les indices de début et de fin pour la page courante
          const startIndex = (currentPage - 1) * eventsPerPage;
          const endIndex = Math.min(startIndex + eventsPerPage, filteredEvents.length);

          // Extraire les événements pour la page courante
          const eventsToShow = filteredEvents.slice(startIndex, endIndex);

          // Mettre à jour les contrôles de pagination
          updatePaginationControls(currentPage, totalPages);

          // Afficher les événements
          if (eventsToShow.length > 0) {
            renderEvents(eventsToShow);
          } else {
            $('#recent-events').html(`
        <div class="text-center py-4">
          <span class="material-icons text-gray-400 text-4xl">filter_alt</span>
          <p class="text-gray-500 mt-2">Aucun événement ne correspond au filtre sélectionné</p>
        </div>
      `);
          }
        }
      }

      // Filtrer et afficher les événements avec pagination
      function filterAndDisplayEvents() {
        // Filtrer les événements selon le type sélectionné
        let filteredEvents = allEvents;

        if (currentFilter !== 'all') {
          filteredEvents = allEvents.filter(event => event.event_type === currentFilter);
        }

        // Calculer le nombre total de pages
        const totalPages = Math.max(1, Math.ceil(filteredEvents.length / eventsPerPage));

        // Ajuster la page courante si nécessaire
        if (currentPage > totalPages) {
          currentPage = totalPages;
        }

        // Calculer les indices de début et de fin pour la page courante
        const startIndex = (currentPage - 1) * eventsPerPage;
        const endIndex = Math.min(startIndex + eventsPerPage, filteredEvents.length);

        // Extraire les événements pour la page courante
        const eventsToShow = filteredEvents.slice(startIndex, endIndex);

        // Mettre à jour les contrôles de pagination
        updatePaginationControls(currentPage, totalPages);

        // Afficher les événements
        if (eventsToShow.length > 0) {
          renderEvents(eventsToShow);
        } else {
          $('#recent-events').html(`
      <div class="text-center py-4">
        <span class="material-icons text-gray-400 text-4xl">filter_alt</span>
        <p class="text-gray-500 mt-2">Aucun événement ne correspond au filtre sélectionné</p>
      </div>
    `);
        }
      }

      // Mettre à jour les contrôles de pagination
      function updatePaginationControls(currentPage, totalPages) {
        $('#current-page').text(currentPage);
        $('#total-pages').text(totalPages);

        // Activer/désactiver les boutons selon la position
        $('#prev-events').prop('disabled', currentPage <= 1);
        $('#next-events').prop('disabled', currentPage >= totalPages);

        // Cacher la pagination s'il n'y a pas d'événements
        $('#events-pagination').toggle(totalPages > 0);
      }

      // Fonction pour afficher les événements
      function renderEvents(events) {
        let html = '';

        events.forEach(event => {
          let colorClass = '';
          let iconName = '';

          // Déterminer la couleur et l'icône en fonction du type d'événement
          switch (event.event_type) {
            case 'received':
              colorClass = 'bg-green-500';
              iconName = 'inventory';
              break;
            case 'canceled':
              colorClass = 'bg-red-500';
              iconName = 'cancel';
              break;
            case 'updated':
              colorClass = 'bg-blue-500';
              iconName = 'update';
              break;
            case 'return':
              colorClass = 'bg-purple-500';
              iconName = 'swap_horiz';
              break;
            default:
              colorClass = 'bg-gray-500';
              iconName = 'info';
          }

          html += `
      <div class="event-item flex">
        <div class="flex-shrink-0 flex items-start mt-1">
          <div class="event-indicator ${colorClass}"></div>
        </div>
        <div class="flex-grow">
          <div class="event-title">${event.title}</div>
          <div class="event-description">${event.description}</div>
        </div>
        <div class="flex-shrink-0 flex items-start">
          <div class="event-time">${event.time_ago}</div>
        </div>
      </div>
    `;
        });

        $('#recent-events').html(html);
      }

      // Initialiser les gestionnaires d'événements pour la pagination et le filtrage
      $(document).ready(function () {
        // Gérer le changement de filtre
        $('#event-filter').on('change', function () {
          currentFilter = $(this).val();
          loadRecentEvents(); // Recharger les événements avec le nouveau filtre
        });

        // Gérer la navigation de pagination
        $('#prev-events').on('click', function () {
          if (currentPage > 1) {
            currentPage--;
            filterAndDisplayEvents();
          }
        });

        $('#next-events').on('click', function () {
          const totalPages = Math.ceil(allEvents.length / eventsPerPage);
          if (currentPage < totalPages) {
            currentPage++;
            filterAndDisplayEvents();
          }
        });

        // Actualiser les événements
        $('#refresh-events-btn').on('click', function () {
          loadRecentEvents();
        });

        // Charger les événements au démarrage
        loadRecentEvents();
      });

      // Créer les boutons d'export
      $('<div class="flex space-x-2 my-4 justify-end">' +
        '<button id="export-excel-today" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">' +
        '<span class="material-icons align-middle text-sm mr-1">file_download</span>Excel</button>' +
        '<button id="export-pdf-today" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">' +
        '<span class="material-icons align-middle text-sm mr-1">picture_as_pdf</span>PDF</button>' +
        '</div>').insertBefore('#today-table-container');

      $('<div class="flex space-x-2 my-4 justify-end">' +
        '<button id="export-excel-week" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">' +
        '<span class="material-icons align-middle text-sm mr-1">file_download</span>Excel</button>' +
        '<button id="export-pdf-week" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">' +
        '<span class="material-icons align-middle text-sm mr-1">picture_as_pdf</span>PDF</button>' +
        '</div>').insertBefore('#week-table-container');

      $('<div class="flex space-x-2 my-4 justify-end">' +
        '<button id="export-excel-month" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">' +
        '<span class="material-icons align-middle text-sm mr-1">file_download</span>Excel</button>' +
        '<button id="export-pdf-month" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">' +
        '<span class="material-icons align-middle text-sm mr-1">picture_as_pdf</span>PDF</button>' +
        '</div>').insertBefore('#month-table-container');

      // Ajouter un écouteur pour redimensionner les tableaux lors d'un changement de taille de fenêtre
      $(window).on('resize', function () {
        if (todayTable !== null && $('#today-content').hasClass('active')) {
          todayTable.columns.adjust().responsive.recalc();
        } else if (weekTable !== null && $('#week-content').hasClass('active')) {
          weekTable.columns.adjust().responsive.recalc();
        } else if (monthTable !== null && $('#month-content').hasClass('active')) {
          monthTable.columns.adjust().responsive.recalc();
        }
      });
    });
  </script>
</body>

</html>