<?php
session_start();

// Vérifications de sécurité
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'finance') {
    header("Location: ../../index.php");
    exit();
}

require_once '../../database/connection.php';

// Traitement du filtre
$filter = $_GET['filter'] ?? 'all';
$view_code = $_GET['view'] ?? null;

// Requête pour récupérer les appels de fonds
$where_clause = "";
$params = [];

switch ($filter) {
    case 'en_attente':
        $where_clause = "WHERE af.statut = 'en_attente'";
        break;
    case 'valide':
        $where_clause = "WHERE af.statut = 'valide'";
        break;
    case 'partiellement_valide':
        $where_clause = "WHERE af.statut = 'partiellement_valide'";
        break;
    case 'rejete':
        $where_clause = "WHERE af.statut = 'rejete'";
        break;
}

$query = "
    SELECT 
        af.*,
        u.name as demandeur_nom,
        u.email as demandeur_email,
        vf.name as validateur_nom,
        COUNT(afe.id) as nb_elements,
        COALESCE(SUM(CASE WHEN afe.statut = 'valide' THEN afe.montant_total ELSE 0 END), 0) as montant_valide,
        COALESCE(SUM(CASE WHEN afe.statut = 'en_attente' THEN afe.montant_total ELSE 0 END), 0) as montant_attente
    FROM appels_fonds af
    LEFT JOIN users_exp u ON af.user_id = u.id
    LEFT JOIN users_exp vf ON af.validé_par = vf.id
    LEFT JOIN appels_fonds_elements afe ON af.id = afe.appel_fonds_id
    $where_clause
    GROUP BY af.id
    ORDER BY af.date_creation DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appels = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Appels de Fonds | Finance</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #334155;
        }

        .top-bar {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .finance-btn {
            background-color: #ebf2ff;
            border: none;
            color: #3b82f6;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .finance-btn:hover {
            background-color: #dbeafe;
            color: #2563eb;
            transform: translateY(-1px);
        }

        .card-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .filter-tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }

        .filter-tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-tab.active {
            background-color: white;
            color: #3b82f6;
            border-bottom: 2px solid #3b82f6;
        }

        .filter-tab:not(.active):hover {
            background-color: #f1f5f9;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-en_attente {
            background-color: #fef3c7;
            color: #d97706;
        }

        .status-valide {
            background-color: #d1fae5;
            color: #059669;
        }

        .status-partiellement_valide {
            background-color: #e0e7ff;
            color: #7c3aed;
        }

        .status-rejete {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .action-btn {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-view {
            background-color: #3b82f6;
            color: white;
        }

        .btn-view:hover {
            background-color: #2563eb;
        }

        .btn-validate {
            background-color: #10b981;
            color: white;
        }

        .btn-validate:hover {
            background-color: #059669;
        }

        .btn-reject {
            background-color: #ef4444;
            color: white;
        }

        .btn-reject:hover {
            background-color: #dc2626;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            padding: 1.5rem;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .modal-body {
            padding: 1.5rem;
            max-height: calc(90vh - 200px);
            overflow-y: auto;
        }

        .modal-footer {
            padding: 1.5rem;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .element-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }

        .element-card:hover {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .element-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .element-actions {
            display: flex;
            gap: 0.5rem;
        }

        .close {
            color: #9ca3af;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close:hover {
            color: #4b5563;
        }

        table.dataTable {
            width: 100% !important;
            border-collapse: separate;
            border-spacing: 0;
        }

        table.dataTable thead th {
            padding: 12px 15px;
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #374151;
        }

        table.dataTable tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        table.dataTable tbody tr:hover {
            background-color: #f8fafc;
        }

        .progress-bar {
            background-color: #e5e7eb;
            border-radius: 9999px;
            height: 0.5rem;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            transition: width 0.3s ease;
        }
    </style>
</head>

<body>
    <div class="min-h-screen bg-gray-50">
        <?php include_once '../../components/navbar_finance.php'; ?>

        <main class="p-6">
            <!-- Top Bar -->
            <div class="top-bar p-4 mb-6 flex justify-between items-center">
                <a href="./dashboards.php" class="finance-btn">
                    <span class="material-icons" style="font-size: 18px;">arrow_back</span>
                    Retour au tableau de bord
                </a>

                <div class="flex items-center space-x-4">
                    <button onclick="exportData('excel')" class="finance-btn finance-btn-secondary">
                        <span class="material-icons" style="font-size: 18px;">file_download</span>
                        Export Excel
                    </button>
                    <button onclick="exportData('pdf')" class="finance-btn finance-btn-secondary">
                        <span class="material-icons" style="font-size: 18px;">picture_as_pdf</span>
                        Export PDF
                    </button>
                </div>
            </div>

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Gestion des Appels de Fonds</h1>
                <p class="text-gray-600">Validation et suivi des demandes d'appels de fonds</p>
            </div>

            <!-- Filtres -->
            <div class="card-container mb-6">
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?= ($filter === 'all') ? 'active' : '' ?>">
                        <span class="material-icons">list</span>
                        Tous (<?= count($appels) ?>)
                    </a>
                    <a href="?filter=en_attente" class="filter-tab <?= ($filter === 'en_attente') ? 'active' : '' ?>">
                        <span class="material-icons">pending_actions</span>
                        En attente
                    </a>
                    <a href="?filter=partiellement_valide" class="filter-tab <?= ($filter === 'partiellement_valide') ? 'active' : '' ?>">
                        <span class="material-icons">partial_fulfillment</span>
                        Partiels
                    </a>
                    <a href="?filter=valide" class="filter-tab <?= ($filter === 'valide') ? 'active' : '' ?>">
                        <span class="material-icons">check_circle</span>
                        Validés
                    </a>
                    <a href="?filter=rejete" class="filter-tab <?= ($filter === 'rejete') ? 'active' : '' ?>">
                        <span class="material-icons">cancel</span>
                        Rejetés
                    </a>
                </div>

                <div class="p-4">
                    <table id="appels-table" class="w-full">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Date</th>
                                <th>Demandeur</th>
                                <th>Désignation</th>
                                <th>Montant Total</th>
                                <th>Statut</th>
                                <th>Progression</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appels as $appel): ?>
                                <tr>
                                    <td>
                                        <span class="font-mono text-sm"><?= htmlspecialchars($appel['code_appel']) ?></span>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($appel['date_creation'])) ?>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($appel['demandeur_nom']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($appel['entite']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="max-w-xs">
                                            <div class="font-medium truncate"><?= htmlspecialchars($appel['designation']) ?></div>
                                            <div class="text-xs text-gray-500"><?= $appel['nb_elements'] ?> élément(s)</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-right">
                                            <div class="font-medium"><?= number_format($appel['montant_total'], 0, ',', ' ') ?> FCFA</div>
                                            <?php if ($appel['montant_valide'] > 0): ?>
                                                <div class="text-xs text-green-600">
                                                    Validé: <?= number_format($appel['montant_valide'], 0, ',', ' ') ?> FCFA
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $appel['statut'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $appel['statut'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $progression = $appel['montant_total'] > 0 ? ($appel['montant_valide'] / $appel['montant_total']) * 100 : 0;
                                        ?>
                                        <div class="w-full">
                                            <div class="flex justify-between text-xs text-gray-600 mb-1">
                                                <span><?= round($progression) ?>%</span>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $progression ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex space-x-1">
                                            <button onclick="viewAppel('<?= $appel['code_appel'] ?>')" 
                                                    class="action-btn btn-view">
                                                <span class="material-icons" style="font-size: 14px;">visibility</span>
                                                Voir
                                            </button>
                                            <?php if ($appel['statut'] === 'en_attente' || $appel['statut'] === 'partiellement_valide'): ?>
                                                <button onclick="validateAppel('<?= $appel['code_appel'] ?>')" 
                                                        class="action-btn btn-validate">
                                                    <span class="material-icons" style="font-size: 14px;">check</span>
                                                    Valider
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de visualisation/validation -->
    <div id="appel-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-xl font-semibold">Détails de l'Appel de Fonds</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="modal-body">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
            <div class="modal-footer" id="modal-footer">
                <!-- Les boutons seront ajoutés dynamiquement -->
            </div>
        </div>
    </div>

    <!-- Modal de rejet -->
    <div id="reject-modal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="text-xl font-semibold">Rejeter l'Appel de Fonds</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="reject-form">
                    <input type="hidden" id="reject-appel-id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Motif du rejet</label>
                        <select id="reject-reason" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500">
                            <option value="">Sélectionnez un motif</option>
                            <option value="Justificatifs insuffisants">Justificatifs insuffisants</option>
                            <option value="Montant non conforme">Montant non conforme</option>
                            <option value="Budget non disponible">Budget non disponible</option>
                            <option value="Demande incomplète">Demande incomplète</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Commentaire</label>
                        <textarea id="reject-comment" rows="4" 
                                  class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500"
                                  placeholder="Expliquez davantage la raison du rejet..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeRejectModal()" 
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">
                    Annuler
                </button>
                <button type="button" onclick="confirmReject()" 
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Confirmer le rejet
                </button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/gestion_appels.js"></script>
</body>
</html>