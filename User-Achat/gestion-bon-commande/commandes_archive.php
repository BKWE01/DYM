<?php
///expressions_besoins/User-Achat/gestion-bon-commande/commandes_archive.php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Connexion à la base de données
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Récupération de l'utilisateur actuel pour l'affichage dans la navbar
$user_id = $_SESSION['user_id'];

// Initialisation des variables
$message = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Traitement des actions
if ($action === 'delete' && isset($_GET['id'])) {
    $orderId = $_GET['id'];

    try {
        // Récupérer le chemin du fichier avant de supprimer l'enregistrement
        $filePathQuery = "SELECT file_path FROM purchase_orders WHERE id = ?";
        $filePathStmt = $pdo->prepare($filePathQuery);
        $filePathStmt->execute([$orderId]);
        $filePath = $filePathStmt->fetchColumn();

        // Supprimer l'enregistrement de la base de données
        $deleteQuery = "DELETE FROM purchase_orders WHERE id = ?";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $success = $deleteStmt->execute([$orderId]);

        if ($success) {
            // Si la suppression en base a réussi, tenter de supprimer le fichier
            if ($filePath && file_exists($filePath)) {
                unlink($filePath);
            }

            $message = "Le bon de commande a été supprimé avec succès.";

            // Journaliser l'action
            if (function_exists('logSystemEvent')) {
                logSystemEvent($pdo, $user_id, 'delete_bon_commande', 'purchase_orders', $orderId, json_encode(['file_path' => $filePath]));
            }
        } else {
            $message = "Erreur lors de la suppression du bon de commande.";
        }
    } catch (PDOException $e) {
        $message = "Erreur de base de données : " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive des Bons de Commande</title>

    <!-- Styles CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

    <!-- Scripts JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
    /* ================================================================
           STYLES PRINCIPAUX - DYM MANUFACTURE
           ================================================================ */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f7f9fc;
    }

    .wrapper {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    /* ================================================================
           COMPOSANTS UI PERSONNALISÉS
           ================================================================ */
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
        transition: all 0.3s ease;
    }

    .validate-btn:hover {
        color: #2f855a;
        border-color: #2f855a;
        background-color: rgba(56, 161, 105, 0.1);
    }

    /* ================================================================
           DATATABLES PERSONNALISÉ
           ================================================================ */
    table.dataTable {
        width: 100% !important;
        margin-bottom: 1rem;
        clear: both;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    table.dataTable thead th,
    table.dataTable thead td {
        padding: 12px 18px;
        border-bottom: 2px solid #e2e8f0;
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        font-weight: 600;
        color: #4a5568;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }

    table.dataTable tbody td {
        padding: 12px 18px;
        vertical-align: middle;
        border-bottom: 1px solid #e2e8f0;
        background-color: #ffffff;
        transition: background-color 0.2s ease;
    }

    table.dataTable tbody tr:hover td {
        background-color: #f8fafc;
    }

    /* ================================================================
           BADGES ET STATUTS - MISE À JOUR AVEC STATUT REJETÉ
           ================================================================ */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
        white-space: nowrap;
        transition: all 0.2s ease;
    }

    .status-multi {
        background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        color: #5b21b6;
        border: 1px solid #a78bfa;
    }

    .status-single {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border: 1px solid #10b981;
    }

    .status-validated {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border: 1px solid #10b981;
    }

    .status-pending {
        background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
        color: #92400e;
        border: 1px solid #f59e0b;
    }

    /* NOUVEAU : Badge pour les bons de commande rejetés */
    .status-rejected {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border: 1px solid #f87171;
    }

    /* ================================================================
           BOUTONS D'ACTION
           ================================================================ */
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
        margin: 0 0.125rem;
        border: none;
        cursor: pointer;
    }

    .btn-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .btn-action .material-icons {
        font-size: 1rem;
        margin-right: 0.25rem;
    }

    .btn-view {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }

    .btn-download {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }

    .btn-validated {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
    }

    .btn-delete {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }

    /* NOUVEAU : Bouton pour voir les détails du rejet */
    .btn-rejection-details {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }

    /* ================================================================
           ANIMATIONS ET EFFETS
           ================================================================ */
    @keyframes fadeOut {
        from {
            opacity: 1;
        }

        to {
            opacity: 0;
        }
    }

    .flash-message {
        animation: fadeOut 5s forwards;
        animation-delay: 3s;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .slide-in {
        animation: slideIn 0.3s ease-out;
    }

    /* ================================================================
           MODAL PERSONNALISÉ
           ================================================================ */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        justify-content: center;
        align-items: center;
        z-index: 1000;
        padding: 1rem;
    }

    .modal-content {
        background-color: white;
        padding: 2rem;
        border-radius: 12px;
        max-width: 900px;
        width: 95%;
        max-height: 95vh;
        overflow-y: auto;
        position: relative;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    }

    .modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        font-size: 1.5rem;
        cursor: pointer;
        color: #6b7280;
        transition: color 0.2s ease;
    }

    .modal-close:hover {
        color: #374151;
    }

    /* ================================================================
           STYLES DE RECHERCHE PAR PRODUIT - INTÉGRÉS
           ================================================================ */
    .product-search-container {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border: 2px solid #0ea5e9;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease-in-out;
        position: relative;
        overflow: hidden;
    }

    .product-search-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #3b82f6, #10b981, #f59e0b, #8b5cf6);
    }

    .product-search-container:hover {
        box-shadow: 0 8px 25px rgba(14, 165, 233, 0.15);
        transform: translateY(-2px);
    }

    #product-search-input {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background: linear-gradient(to right, #ffffff, #f8fafc);
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 1rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    #product-search-input:focus {
        outline: none;
        border-color: #3b82f6;
        background: #ffffff;
        box-shadow:
            0 0 0 3px rgba(59, 130, 246, 0.1),
            0 4px 12px rgba(59, 130, 246, 0.15);
        transform: translateY(-1px);
    }

    #search-product-btn {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        border: none;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }

    #search-product-btn:hover:not(:disabled) {
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.6);
    }

    #search-product-btn:disabled {
        background: #94a3b8;
        transform: none;
        box-shadow: none;
        cursor: not-allowed;
    }

    #clear-product-search {
        transition: all 0.3s ease-in-out;
        color: #6b7280;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        background: transparent;
        border: 1px solid transparent;
    }

    #clear-product-search:hover {
        color: #dc2626;
        background: #fef2f2;
        border-color: #fecaca;
    }

    #product-search-results {
        animation: slideIn 0.4s ease-out;
        margin-top: 1rem;
    }

    .search-result-row {
        background: linear-gradient(135deg, #fef3c7 0%, #fef9e7 100%) !important;
        border-left: 4px solid #f59e0b !important;
        transition: all 0.3s ease-in-out;
    }

    .search-result-row:hover {
        background: linear-gradient(135deg, #fed7aa 0%, #fef3c7 100%) !important;
        transform: translateX(2px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
    }

    .search-result-icon {
        animation: bounce-in 0.6s ease-out;
        color: #f59e0b;
    }

    @keyframes bounce-in {
        0% {
            opacity: 0;
            transform: scale(0.3);
        }

        50% {
            opacity: 1;
            transform: scale(1.1);
        }

        100% {
            opacity: 1;
            transform: scale(1);
        }
    }

    .product-found-badge {
        background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
        border: 1px solid #f59e0b;
        color: #92400e;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        margin: 0.125rem;
        display: inline-block;
        transition: all 0.3s ease;
    }

    .product-found-badge:hover {
        background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
    }

    /* ================================================================
           RESPONSIVE DESIGN
           ================================================================ */
    @media (max-width: 768px) {
        .product-search-container {
            padding: 1rem;
            margin-bottom: 1rem;
        }

        #product-search-input {
            font-size: 16px;
            /* Évite le zoom sur iOS */
        }

        .btn-action {
            padding: 0.375rem 0.5rem;
            font-size: 0.7rem;
        }

        .modal-content {
            padding: 1rem;
            width: 98%;
            max-height: 98vh;
        }
    }

    /* ================================================================
           ÉTATS DE CHARGEMENT
           ================================================================ */
    .loading-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid #f3f4f6;
        border-radius: 50%;
        border-top: 2px solid #3b82f6;
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

    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    /* ================================================================
           NOUVEAUX STYLES POUR LES DÉTAILS DE REJET
           ================================================================ */
    .rejection-details {
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        border-left: 4px solid #ef4444;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-top: 0.5rem;
    }

    .rejection-reason {
        color: #991b1b;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .rejection-meta {
        color: #6b7280;
        font-size: 0.875rem;
    }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- Header de la page -->
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex flex-wrap justify-between items-center">
                <div class="flex items-center m-2">
                    <button class="validate-btn mr-4">Archive des Bons de Commande</button>
                </div>

                <div class="date-time m-2">
                    <span class="material-icons">event</span>
                    <span id="date-time-display"></span>
                </div>
            </div>

            <!-- Message flash -->
            <?php if (!empty($message)): ?>
            <div id="flash-message"
                class="flash-message bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <span class="block sm:inline"><?php echo $message; ?></span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3"
                    onclick="document.getElementById('flash-message').style.display='none';">
                    <span class="material-icons">close</span>
                </span>
            </div>
            <?php endif; ?>

            <!-- Contenu principal -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Liste des Bons de Commande</h2>
                    <a href="../achats_materiaux.php"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        <span class="material-icons align-middle mr-1">arrow_back</span>
                        Retour aux achats
                    </a>
                </div>

                <!-- Section des filtres avancés -->
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-700">Filtres Avancés</h3>
                    <button id="toggle-filters-orders" class="text-blue-600 hover:text-blue-800 font-medium">
                        <span class="material-icons align-middle">filter_list</span>
                        Masquer les filtres
                    </button>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 mb-6" id="filters-section-orders">

                    <!-- Panneau des filtres actifs -->
                    <div id="active-filters-container" class="bg-blue-50 p-3 rounded-lg mb-4 hidden">
                        <h3 class="font-medium text-blue-800 mb-2">Filtres actifs :</h3>
                        <!-- Les filtres actifs seront affichés ici dynamiquement -->
                    </div>

                    <form id="filters-form-orders">
                        <!-- Filtres de base - première ligne -->
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-4">
                            <!-- Filtre par numéro de bon -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">N° Bon de commande</label>
                                <input type="text" id="order-number-filter"
                                    class="block w-full border-gray-300 rounded-md shadow-sm"
                                    placeholder="Ex: BC-2024-001">
                            </div>

                            <!-- Filtre par fournisseur -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fournisseur</label>
                                <select id="supplier-filter-orders"
                                    class="block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Tous les fournisseurs</option>
                                    <?php
                                    $suppliersQuery = "SELECT DISTINCT fournisseur FROM purchase_orders ORDER BY fournisseur";
                                    $suppliersStmt = $pdo->prepare($suppliersQuery);
                                    $suppliersStmt->execute();
                                    while ($supplier = $suppliersStmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='{$supplier['fournisseur']}'>{$supplier['fournisseur']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Filtre par créateur -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Créé par</label>
                                <select id="created-by-filter"
                                    class="block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Tous les utilisateurs</option>
                                    <!-- Options ajoutées dynamiquement -->
                                </select>
                            </div>

                            <!-- Filtre par projet avec autocomplete -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Projet</label>
                                <input type="text" id="project-filter-orders"
                                    class="block w-full border-gray-300 rounded-md shadow-sm"
                                    placeholder="Rechercher un projet">
                                <div id="project-suggestions" class="absolute z-10 hidden"></div>
                            </div>
                        </div>

                        <!-- Filtres de temps et montants - deuxième ligne -->
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-4">
                            <!-- Filtres de date avancés -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                                <input type="date" id="date-start-filter"
                                    class="block w-full border-gray-300 rounded-md shadow-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                                <input type="date" id="date-end-filter"
                                    class="block w-full border-gray-300 rounded-md shadow-sm">
                            </div>

                            <!-- Filtre par période prédéfinie -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Période prédéfinie</label>
                                <select id="date-range-orders"
                                    class="block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Toute période</option>
                                    <option value="today">Aujourd'hui</option>
                                    <option value="week">7 derniers jours</option>
                                    <option value="month">30 derniers jours</option>
                                    <option value="quarter">3 derniers mois</option>
                                    <option value="year">Année en cours</option>
                                </select>
                            </div>

                            <!-- Filtre par montant -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Montant (FCFA)</label>
                                <div class="flex space-x-2">
                                    <input type="number" id="amount-min"
                                        class="block w-1/2 border-gray-300 rounded-md shadow-sm" placeholder="Min"
                                        min="0">
                                    <input type="number" id="amount-max"
                                        class="block w-1/2 border-gray-300 rounded-md shadow-sm" placeholder="Max"
                                        min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Raccourcis de date -->
                        <div id="date-shortcuts" class="flex flex-wrap gap-2 mb-4">
                            <button type="button" data-period="today"
                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-800 px-2 py-1 rounded">Aujourd'hui</button>
                            <button type="button" data-period="yesterday"
                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-800 px-2 py-1 rounded">Hier</button>
                            <button type="button" data-period="week"
                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-800 px-2 py-1 rounded">7 derniers
                                jours</button>
                            <button type="button" data-period="month"
                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-800 px-2 py-1 rounded">30
                                derniers jours</button>
                            <button type="button" data-period="quarter"
                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-800 px-2 py-1 rounded">3 derniers
                                mois</button>
                            <button type="button" data-period="year"
                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-800 px-2 py-1 rounded">12
                                derniers mois</button>
                        </div>

                        <!-- Statut et Type - troisième ligne MISE À JOUR AVEC REJET -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Filtre par état - MISE À JOUR -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">État de validation</label>
                                <select id="status-filter-orders"
                                    class="block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="all">Tous les états</option>
                                    <option value="validated">Validé</option>
                                    <option value="pending">En attente</option>
                                    <option value="rejected">Rejeté</option>
                                </select>
                            </div>

                            <!-- Filtre par type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type de commande</label>
                                <select id="type-filter-orders"
                                    class="block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="all">Tous</option>
                                    <option value="single">Projet unique</option>
                                    <option value="multi">Multi-projets</option>
                                </select>
                            </div>
                        </div>

                        <!-- Section des filtres multi-sélection -->
                        <details class="bg-gray-100 rounded-lg mb-4">
                            <summary class="cursor-pointer px-4 py-2 text-sm font-medium text-gray-700">Filtres
                                multi-sélection</summary>
                            <div class="p-4 border-t border-gray-200">
                                <h4 class="font-medium mb-2">Sélectionner plusieurs fournisseurs</h4>
                                <div id="multi-supplier-filter"
                                    class="max-h-48 overflow-y-auto grid grid-cols-2 md:grid-cols-3 gap-2">
                                    <!-- Options ajoutées dynamiquement -->
                                </div>
                            </div>
                        </details>

                        <!-- Actions et statistiques -->
                        <div class="flex flex-wrap justify-between items-center gap-4 mt-4">
                            <div class="flex flex-wrap gap-2">
                                <!-- Boutons d'action principaux -->
                                <button type="button" id="reset-filters-orders"
                                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                                    <span class="material-icons align-middle mr-1">clear</span>
                                    Réinitialiser
                                </button>

                                <button type="button" id="save-filters-btn"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">
                                    <span class="material-icons align-middle mr-1">bookmark</span>
                                    Sauvegarder
                                </button>

                                <button type="button" id="advanced-stats"
                                    class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-md">
                                    <span class="material-icons align-middle mr-1">analytics</span>
                                    Statistiques
                                </button>

                                <!-- Menu d'export -->
                                <div class="dropdown relative">
                                    <button type="button"
                                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md">
                                        <span class="material-icons align-middle mr-1">file_download</span>
                                        Exporter
                                    </button>
                                    <div
                                        class="dropdown-menu hidden absolute z-10 mt-1 bg-white shadow-lg rounded-md overflow-hidden">
                                        <button id="export-filtered-csv"
                                            class="block w-full text-left px-4 py-2 hover:bg-gray-100">CSV</button>
                                        <button id="export-filtered-excel"
                                            class="block w-full text-left px-4 py-2 hover:bg-gray-100">Excel</button>
                                        <button id="export-filtered-pdf"
                                            class="block w-full text-left px-4 py-2 hover:bg-gray-100">PDF</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistiques en temps réel MISE À JOUR AVEC REJETS -->
                            <div class="text-sm text-gray-600 bg-gray-100 p-3 rounded-lg">
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                    <div><span class="font-medium">Total filtré:</span> <span
                                            id="filtered-total">0</span></div>
                                    <div><span class="font-medium">Montant:</span> <span id="filtered-amount">0
                                            FCFA</span></div>
                                    <div><span class="font-medium">Validés:</span> <span
                                            id="filtered-validated">0</span></div>
                                    <div><span class="font-medium">En attente:</span> <span
                                            id="filtered-pending">0</span></div>
                                    <div><span class="font-medium">Rejetés:</span> <span id="filtered-rejected">0</span>
                                    </div>
                                    <div><span class="font-medium">Multi-projets:</span> <span
                                            id="filtered-multi">0</span></div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Panneaux des Favoris et Historique -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <h3 class="font-medium text-gray-800 mb-2">Filtres sauvegardés</h3>
                        <div id="favorites-filters" class="bg-gray-50 p-3 rounded-lg max-h-48 overflow-y-auto">
                            <!-- Favoris ajoutés dynamiquement -->
                            <p class="text-gray-500 italic">Aucun filtre favori</p>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-800 mb-2">Historique des recherches</h3>
                        <div id="history-filters" class="bg-gray-50 p-3 rounded-lg max-h-48 overflow-y-auto">
                            <!-- Historique ajouté dynamiquement -->
                            <p class="text-gray-500 italic">Aucun historique de recherche</p>
                        </div>
                    </div>
                </div>

                <!-- Statistiques des bons de commande MISE À JOUR AVEC REJETS -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <!-- Total des bons de commande -->
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                        <div class="flex items-center">
                            <div class="rounded-full bg-blue-100 p-3 mr-4">
                                <span class="material-icons text-blue-500">receipt</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700" id="total-orders">Chargement...</h3>
                                <p class="text-sm text-gray-500">Total des bons</p>
                            </div>
                        </div>
                    </div>

                    <!-- Montant total des commandes -->
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                        <div class="flex items-center">
                            <div class="rounded-full bg-green-100 p-3 mr-4">
                                <span class="material-icons text-green-500">payments</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700" id="total-amount">Chargement...</h3>
                                <p class="text-sm text-gray-500">Montant total</p>
                            </div>
                        </div>
                    </div>

                    <!-- Bons validés -->
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                        <div class="flex items-center">
                            <div class="rounded-full bg-purple-100 p-3 mr-4">
                                <span class="material-icons text-purple-500">check_circle</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700" id="validated-orders">Chargement...</h3>
                                <p class="text-sm text-gray-500">Bons validés</p>
                            </div>
                        </div>
                    </div>

                    <!-- NOUVEAU : Bons rejetés -->
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
                        <div class="flex items-center">
                            <div class="rounded-full bg-red-100 p-3 mr-4">
                                <span class="material-icons text-red-500">cancel</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700" id="rejected-orders">Chargement...</h3>
                                <p class="text-sm text-gray-500">Bons rejetés</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section de recherche par produit -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 mb-6 border border-blue-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-blue-800 flex items-center">
                            <span class="material-icons mr-2">search</span>
                            Recherche par Produit
                        </h3>
                        <button id="clear-product-search" class="text-blue-600 hover:text-blue-800 font-medium hidden">
                            <span class="material-icons align-middle">clear</span>
                            Effacer la recherche
                        </button>
                    </div>

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <label for="product-search-input" class="block text-sm font-medium text-gray-700 mb-2">
                                Nom du produit ou matériau
                            </label>
                            <div class="relative">
                                <input type="text" id="product-search-input"
                                    class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Ex: Tôle Plane Noire 1x2x30/10 mm" autocomplete="off">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                    <span class="material-icons text-gray-400">inventory_2</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-end">
                            <button type="button" id="search-product-btn"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition duration-150 ease-in-out flex items-center">
                                <span class="material-icons mr-2">search</span>
                                Rechercher
                            </button>
                        </div>
                    </div>

                    <!-- Zone de résultats de recherche -->
                    <div id="product-search-results" class="mt-4 hidden">
                        <div class="bg-white rounded-lg p-4 border border-blue-200">
                            <div id="search-results-header" class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-800"></h4>
                                <span id="search-results-count" class="text-sm text-gray-600"></span>
                            </div>
                            <div id="search-results-info" class="text-sm text-blue-600"></div>
                        </div>
                    </div>
                </div>

                <!-- Tableau des bons de commande -->
                <div class="overflow-x-auto">
                    <table id="purchaseOrdersTable" class="display responsive nowrap w-full">
                        <thead>
                            <tr>
                                <th>N° Bon</th>
                                <th>Référence</th>
                                <th>Date</th>
                                <th>Projet(s)</th>
                                <th>Fournisseur</th>
                                <th>Montant</th>
                                <th>Type</th>
                                <th>Créé par</th>
                                <th>État</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Les données seront chargées via DataTables/AJAX -->
                            <tr>
                                <td colspan="10" class="text-center">Chargement des données...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <!-- Modal de prévisualisation -->
    <div id="preview-modal" class="modal">
        <div class="modal-content relative">
            <span class="modal-close material-icons absolute top-2 right-2 text-gray-500 hover:text-gray-800"
                onclick="closePreviewModal()">close</span>
            <h2 class="text-xl font-semibold mb-4" id="modal-title">Détails du Bon de Commande</h2>
            <div id="modal-content">
                <!-- Le contenu sera chargé dynamiquement -->
                <div class="flex justify-center items-center h-32">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-700"></div>
                    <p class="ml-3 text-gray-600">Chargement des données...</p>
                </div>
            </div>
            <div class="flex justify-end mt-4 space-x-2">
                <button id="download-btn" class="bg-blue-500 hover:bg-blue-700 text-white py-2 px-4 rounded">
                    <span class="material-icons align-middle mr-1">download</span>
                    Télécharger
                </button>
                <button onclick="closePreviewModal()"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded">
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <!-- NOUVEAU : Modal pour les détails de rejet -->
    <div id="rejection-modal" class="modal">
        <div class="modal-content relative">
            <span class="modal-close material-icons absolute top-2 right-2 text-gray-500 hover:text-gray-800"
                onclick="closeRejectionModal()">close</span>
            <h2 class="text-xl font-semibold mb-4 text-red-600">
                <span class="material-icons align-middle mr-2">cancel</span>
                Détails du Rejet
            </h2>
            <div id="rejection-modal-content">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
            <div class="flex justify-end mt-4">
                <button onclick="closeRejectionModal()"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded">
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts jQuery et DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js">
    </script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js">
    </script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Plugin pour le tri des dates françaises -->
    <script src="../assets/js/datatable-date-fr.js"></script>
    <script src="../assets/js/datatable-custom-filters.js"></script>

    <script>
    /**
     * ================================================================
     * SYSTÈME DE GESTION DES BONS DE COMMANDE - ARCHIVE MISE À JOUR
     * Fichier: commandes_archive.php - JavaScript avec gestion des rejets
     * Version: 4.0 - Support des bons de commande rejetés
     * ================================================================
     */

    // ================================================================
    // VARIABLES GLOBALES ET CONFIGURATION
    // ================================================================
    let ordersTable = null;
    let searchMode = 'normal'; // 'normal' | 'product'

    const CONFIG = {
        urls: {
            orders: 'api/api_get_stored_orders.php',
            stats: 'api/api_get_order_stats.php',
            orderDetails: 'api/api_get_order_details.php',
            productSearch: 'api/search/search_by_product.php'
        },
        table: {
            pageLength: 15,
            lengthMenu: [
                [10, 15, 25, 50, -1],
                [10, 15, 25, 50, "Tout"]
            ],
            language: "//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
        }
    };

    // ================================================================
    // GESTIONNAIRE PRINCIPAL DE L'APPLICATION
    // ================================================================
    const App = {
        /**
         * Initialise l'application
         */
        init() {
            console.log('🚀 Initialisation de l\'application des bons de commande (v4.0 - avec rejets)');

            this.bindEvents();
            DateTime.init();
            Table.init();
            ProductSearch.init();
            Stats.load();
            Filters.init();

            console.log('✅ Application initialisée avec succès');
        },

        /**
         * Lie les événements globaux
         */
        bindEvents() {
            // Fonctions globales pour rétrocompatibilité
            window.closePreviewModal = () => Modal.close();
            window.showOrderDetails = (id) => Modal.showOrderDetails(id);
            window.previewOrder = (id) => Modal.showOrderDetails(id);
            window.confirmDelete = (id) => Actions.confirmDelete(id);

            // NOUVEAU : Fonctions pour les rejets
            window.closeRejectionModal = () => Modal.closeRejectionModal();
            window.showRejectionDetails = (id) => Modal.showRejectionDetails(id);
        }
    };

    // ================================================================
    // GESTIONNAIRE DE DATE ET HEURE
    // ================================================================
    const DateTime = {
        intervalId: null,

        init() {
            this.update();
            this.intervalId = setInterval(() => this.update(), 1000);
        },

        update() {
            const element = document.getElementById('date-time-display');
            if (element) {
                const now = new Date();
                element.textContent = `${now.toLocaleDateString('fr-FR')} ${now.toLocaleTimeString('fr-FR')}`;
            }
        }
    };

    // ================================================================
    // GESTIONNAIRE DU TABLEAU DATATABLES - MISE À JOUR AVEC REJETS
    // ================================================================
    const Table = {
        /**
         * Initialise le tableau
         */
        init() {
            if (ordersTable) {
                ordersTable.destroy();
                ordersTable = null;
            }

            ordersTable = $('#purchaseOrdersTable').DataTable({
                responsive: true,
                processing: true,
                serverSide: false,
                language: {
                    url: CONFIG.table.language,
                    emptyTable: "Aucun bon de commande enregistré",
                    zeroRecords: "Aucun bon de commande trouvé"
                },
                dom: 'Blfrtip',
                buttons: [{
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimer'
                    }
                ],
                ajax: {
                    url: CONFIG.urls.orders,
                    dataSrc: (json) => json.data || []
                },
                columns: this.getColumns(),
                columnDefs: [{
                        orderable: false,
                        targets: [9]
                    }, // Actions (maintenant colonne 9)
                    {
                        type: 'date-fr',
                        targets: 2
                    } // Date
                ],
                order: [
                    [2, 'desc']
                ],
                pageLength: CONFIG.table.pageLength,
                lengthMenu: CONFIG.table.lengthMenu,
                drawCallback: () => console.log('📊 Tableau redessiné')
            });

            return ordersTable;
        },

        /**
         * Définit les colonnes du tableau - MISE À JOUR AVEC GESTION DES REJETS
         */
        getColumns() {
            return [{
                    data: 'order_number',
                    title: 'N° Bon'
                },
                {
                    data: 'download_reference',
                    title: 'Référence',
                    render: (data) => data || '<span class="italic text-gray-400">Non disponible</span>'
                },
                {
                    data: 'generated_at',
                    title: 'Date',
                    type: 'date-fr',
                    render: function(data, type, row) {
                        if (type === 'display' || type === 'filter') {
                            // Vérification de sécurité avant d'utiliser la fonction
                            if (typeof window.formatDateFr === 'function') {
                                return window.formatDateFr(data);
                            } else {
                                // Fallback si la fonction n'est pas encore chargée
                                if (!data || data === null || data === undefined || data === '') {
                                    return 'N/A';
                                }

                                try {
                                    const dateObj = new Date(data);
                                    if (isNaN(dateObj.getTime())) {
                                        return 'Date invalide';
                                    }

                                    const day = String(dateObj.getDate()).padStart(2, '0');
                                    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                                    const year = dateObj.getFullYear();

                                    return `${day}/${month}/${year}`;
                                } catch (error) {
                                    console.error('Erreur formatage date:', data, error);
                                    return 'Erreur';
                                }
                            }
                        }
                        // Pour le tri et les autres types, retourner la valeur brute
                        return data;
                    }
                },
                {
                    data: 'project_info',
                    title: 'Projet(s)',
                    render: (data, type, row) => {
                        const isMulti = row.is_multi_project == 1;
                        const colorClass = isMulti ? 'bg-purple-100 text-purple-800' :
                            'bg-blue-100 text-blue-800';
                        return `<span class="${colorClass} px-2 py-1 rounded-full text-xs font-medium">${data}</span>`;
                    }
                },
                {
                    data: 'fournisseur',
                    title: 'Fournisseur'
                },
                {
                    data: 'montant_total',
                    title: 'Montant',
                    render: (data) => `<span class="font-semibold text-green-600">${data}</span>`
                },
                {
                    data: 'is_multi_project',
                    title: 'Type',
                    render: (data) => {
                        if (data == 1) {
                            return '<span class="status-badge status-multi">Multi-projets</span>';
                        } else {
                            return '<span class="status-badge status-single">Projet unique</span>';
                        }
                    }
                },
                {
                    data: 'username',
                    title: 'Créé par'
                },
                {
                    data: null,
                    title: 'État',
                    render: (data, type, row) => {
                        // MISE À JOUR : Gestion des trois états possibles
                        if (row.status === 'rejected' || row.rejected_at) {
                            return '<span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejeté</span>';
                        } else if (row.signature_finance && row.user_finance_id) {
                            return '<span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Validé Finance</span>';
                        } else {
                            return '<span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">En attente Finance</span>';
                        }
                    }
                },
                {
                    data: null,
                    title: 'Actions',
                    render: (data, type, row) => {
                        // Vérifier si c'est un résultat de recherche ou une donnée normale
                        const orderId = row.id;
                        const downloadRef = row.download_reference;
                        const isValidated = row.signature_finance && row.user_finance_id;
                        const isRejected = row.status === 'rejected' || row.rejected_at;

                        const viewBtn = `<button onclick="Modal.showOrderDetails(${orderId})" 
                       class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm" 
                       title="Voir">
                    <i class="material-icons mr-1" style="font-size: 14px;">visibility</i>Voir
                </button>`;

                        const downloadBtn = `<a href="api/download_bon_commande.php?id=${orderId}" 
                       class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm inline-block"
                       title="Télécharger">
                       <i class="material-icons mr-1" style="font-size: 14px;">download</i>Télécharger
                    </a>`;

                        const validatedBtn = isValidated ?
                            `<a href="api/download_validated_bon_commande.php?id=${orderId}" 
               class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-sm inline-block"
               title="Version validée">
               <i class="material-icons mr-1" style="font-size: 14px;">check_circle</i>Validé
            </a>` : '';

                        // NOUVEAU : Bouton pour voir les détails de rejet
                        const rejectionBtn = isRejected ?
                            `<button onclick="Modal.showRejectionDetails(${orderId})"
               class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm"
               title="Détails du rejet">
               <i class="material-icons mr-1" style="font-size: 14px;">error</i>Rejet
            </button>` : '';

                        const proformaBtn = row.proforma_path ?
                            `<a href="../../${row.proforma_path.startsWith('uploads/proformas/') ? row.proforma_path : 'uploads/proformas/' + row.proforma_path.replace(/^\/+/, '')}"
               class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded text-sm inline-block"
               title="Voir pro-forma" target="_blank">
               <i class="material-icons mr-1" style="font-size: 14px;">visibility</i>Pro-forma
            </a>` : '';

                        return `<div class="flex space-x-2">${viewBtn}${downloadBtn}${validatedBtn}${rejectionBtn}${proformaBtn}</div>`;
                    },
                    orderable: false
                }
            ];
        },

        /**
         * Met à jour le tableau avec de nouvelles données
         */
        updateData(data) {
            if (!ordersTable) return;

            ordersTable.clear();
            ordersTable.rows.add(data);
            ordersTable.draw();
        },

        /**
         * Recharge les données du tableau
         */
        reload() {
            if (ordersTable) {
                ordersTable.ajax.reload();
            }
        }
    };

    // ================================================================
    // GESTIONNAIRE DES ACTIONS - MISE À JOUR AVEC REJETS
    // ================================================================
    const Actions = {
        /**
         * Génère les boutons d'action pour chaque ligne
         */
        generateButtons(row) {
            const viewBtn = `<button onclick="Modal.showOrderDetails(${row.id})" 
                               class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm" 
                               title="Voir">
                            <i class="fas fa-eye mr-1"></i>Voir
                        </button>`;

            const downloadBtn = `<a href="api/download_bon_commande.php?id=${row.id}" 
                               class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm inline-block"
                               title="Télécharger">
                               <i class="fas fa-download mr-1"></i>Télécharger
                            </a>`;

            const validatedBtn = this.isValidated(row) ?
                `<a href="api/download_validated_bon_commande.php?id=${row.id}" 
               class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-sm inline-block"
               title="Version validée">
               <i class="fas fa-certificate mr-1"></i>Validé
            </a>` : '';

            // NOUVEAU : Bouton pour les détails de rejet
            const rejectionBtn = this.isRejected(row) ?
                `<button onclick="Modal.showRejectionDetails(${row.id})" 
               class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm"
               title="Détails du rejet">
               <i class="fas fa-times-circle mr-1"></i>Rejet
            </button>` : '';

            const deleteBtn = `<button onclick="Actions.confirmDelete(${row.id})" 
                                 class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm"
                                 title="Supprimer">
                               <i class="fas fa-trash mr-1"></i>Supprimer
                          </button>`;

            return `<div class="flex space-x-2">${viewBtn}${downloadBtn}${validatedBtn}${rejectionBtn}${deleteBtn}</div>`;
        },

        /**
         * Vérifie si un bon de commande est validé
         */
        isValidated(row) {
            return row.signature_finance && row.user_finance_id;
        },

        /**
         * NOUVEAU : Vérifie si un bon de commande est rejeté
         */
        isRejected(row) {
            return row.status === 'rejected' || row.rejected_at;
        },

        /**
         * Confirme la suppression d'un bon de commande
         */
        confirmDelete(orderId) {
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: "Cette action supprimera définitivement ce bon de commande!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Oui, supprimer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `commandes_archive.php?action=delete&id=${orderId}`;
                }
            });
        }
    };

    // ================================================================
    // GESTIONNAIRE DE RECHERCHE PAR PRODUIT (INCHANGÉ)
    // ================================================================
    const ProductSearch = {
        elements: {},
        currentResults: [],
        searchTimeout: null,
        originalData: null,
        isSearchActive: false,

        /**
         * Initialise le gestionnaire de recherche par produit
         */
        init() {
            this.elements = {
                input: document.getElementById('product-search-input'),
                searchBtn: document.getElementById('search-product-btn'),
                clearBtn: document.getElementById('clear-product-search'),
                resultsContainer: document.getElementById('product-search-results'),
                resultsHeader: document.getElementById('search-results-header'),
                resultsInfo: document.getElementById('search-results-info')
            };
            this.bindEvents();
            console.log('✅ Gestionnaire de recherche par produit initialisé');
        },

        /**
         * Attache les événements aux éléments
         */
        bindEvents() {
            // Recherche au clic
            this.elements.searchBtn?.addEventListener('click', () => this.handleSearch());

            // Recherche en temps réel (avec délai)
            this.elements.input?.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                const value = e.target.value.trim();
                if (value.length >= 3) {
                    this.searchTimeout = setTimeout(() => this.handleSearch(), 800);
                } else if (value.length === 0) {
                    this.clearSearch();
                }
            });

            // Recherche sur Entrée
            this.elements.input?.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.handleSearch();
                }
            });

            // Bouton d'effacement
            this.elements.clearBtn?.addEventListener('click', () => this.clearSearch());
        },

        /**
         * Gère la recherche par produit
         */
        async handleSearch() {
            const searchTerm = this.elements.input?.value?.trim();
            if (!searchTerm || searchTerm.length < 2) {
                this.showAlert('warning', 'Attention',
                    'Veuillez saisir au moins 2 caractères pour la recherche.');
                this.elements.input?.focus();
                return;
            }

            this.setLoading(true);

            try {
                const response = await fetch('api/search/search_by_product.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `product_name=${encodeURIComponent(searchTerm)}`
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                console.log('📡 Données reçues de l\'API:', data);

                if (data.success && data.data && data.data.length > 0) {
                    this.currentResults = data.data;
                    this.displayResults(data, searchTerm);
                    this.updateDataTable(data.data);
                    this.showClearButton();
                    this.isSearchActive = true;

                    this.showAlert('success', 'Recherche réussie',
                        `${data.total_found} bon(s) de commande trouvé(s) pour "${searchTerm}"`);
                } else {
                    this.showAlert('info', 'Aucun résultat',
                        data.message || `Aucun bon de commande trouvé contenant "${searchTerm}".`);
                    this.clearResultsDisplay();
                }
            } catch (error) {
                console.error('❌ Erreur lors de la recherche:', error);
                this.showAlert('error', 'Erreur',
                    'Une erreur est survenue lors de la recherche. Veuillez réessayer.');
                this.clearResultsDisplay();
            } finally {
                this.setLoading(false);
            }
        },

        /**
         * Met à jour le DataTable avec les résultats de recherche
         */
        updateDataTable(searchResults) {
            if (!ordersTable) {
                console.error('❌ DataTable non disponible');
                return;
            }

            try {
                // Sauvegarder les données originales si ce n'est pas déjà fait
                if (!this.originalData) {
                    this.originalData = ordersTable.data().toArray();
                    console.log('💾 Données originales sauvegardées:', this.originalData.length);
                }

                // Convertir les données de recherche au format DataTables (objets, pas arrays)
                const formattedData = this.convertSearchResultsToDataTablesFormat(searchResults);
                console.log('🔄 Données formatées pour DataTable:', formattedData);

                // Mettre à jour le DataTable
                ordersTable.clear();
                ordersTable.rows.add(formattedData);
                ordersTable.draw();

                console.log('✅ DataTable mis à jour avec', formattedData.length, 'résultats');
            } catch (error) {
                console.error('❌ Erreur lors de la mise à jour du DataTable:', error);
            }
        },

        /**
         * Convertit les résultats de recherche au format DataTables (objets avec propriétés nommées)
         */
        convertSearchResultsToDataTablesFormat(searchResults) {
            return searchResults.map(result => {
                // Formatage sécurisé de la date pour compatibilité avec le tri français
                let formattedDate = null;
                let displayDate = 'Date invalide';

                if (result.generated_at_raw) {
                    try {
                        // Utiliser la date raw (format ISO) pour créer un objet Date
                        const dateObj = new Date(result.generated_at_raw);
                        if (!isNaN(dateObj.getTime())) {
                            // Format pour l'affichage (dd/mm/yyyy)
                            const day = String(dateObj.getDate()).padStart(2, '0');
                            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                            const year = dateObj.getFullYear();
                            displayDate = `${day}/${month}/${year}`;
                            formattedDate = result.generated_at_raw; // Garder le format ISO pour le tri
                        }
                    } catch (error) {
                        console.warn('Erreur formatage date recherche:', result.generated_at_raw, error);
                    }
                }

                // Créer un objet avec exactement les mêmes propriétés que l'API normale
                return {
                    id: result.id,
                    order_number: result.order_number,
                    download_reference: result.download_reference || 'N/A',
                    generated_at: formattedDate, // Format ISO pour le tri
                    generated_at_display: displayDate, // Format français pour l'affichage
                    project_info: this.formatProjectInfoWithProduct(result),
                    fournisseur: result.fournisseur,
                    montant_total: result.montant_total,
                    montant_total_raw: result.montant_total_raw,
                    is_multi_project: result.is_multi_project,
                    username: result.username || 'N/A',
                    signature_finance: result.signature_finance,
                    user_finance_id: result.user_finance_id,
                    finance_username: result.finance_username || null,
                    file_path: result.file_path,
                    proforma_path: result.proforma_path,
                    // NOUVEAU : Données de rejet
                    status: result.status || 'pending',
                    rejected_at: result.rejected_at,
                    rejected_by_user_id: result.rejected_by_user_id,
                    rejection_reason: result.rejection_reason,
                    // Ajouter les informations de produit pour mise en évidence
                    _isSearchResult: true,
                    _productFound: result.product_found,
                    _productQuantity: result.product_quantity,
                    _productUnit: result.product_unit,
                    _productPrice: result.product_unit_price
                };
            });
        },

        /**
         * Formate les informations de projet avec le produit trouvé mis en évidence
         */
        formatProjectInfoWithProduct(result) {
            const baseProjectInfo = result.project_info;
            const productHighlight = `
                    <div class="mt-2 bg-yellow-50 border-l-4 border-yellow-400 p-2 rounded">
                        <div class="text-sm font-medium text-yellow-800">
                            <i class="material-icons text-yellow-600 mr-1" style="font-size: 16px; vertical-align: middle;">search</i>
                            ${result.product_found}
                        </div>
                        <div class="text-xs text-yellow-700 mt-1">
                            Qté: ${result.product_quantity} ${result.product_unit} • Prix: ${result.product_unit_price}
                        </div>
                    </div>
                `;

            return baseProjectInfo + productHighlight;
        },

        // Autres méthodes inchangées...
        displayResults(data, searchTerm) {
            if (!this.elements.resultsContainer) return;

            const stats = data.statistics || {};

            // Construction du HTML des résultats
            const resultsHtml = `
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-green-800 flex items-center">
                                <span class="material-icons mr-2">check_circle</span>
                                Résultats pour "${searchTerm}"
                            </h4>
                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                ${data.total_found} bon(s) trouvé(s)
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <div class="bg-white rounded-lg p-3 border border-green-200">
                                <div class="text-2xl font-bold text-green-600">${stats.total_orders || 0}</div>
                                <div class="text-sm text-gray-600">Bons de commande</div>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-blue-200">
                                <div class="text-2xl font-bold text-blue-600">${stats.total_amount_formatted || '0 FCFA'}</div>
                                <div class="text-sm text-gray-600">Montant total</div>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-purple-200">
                                <div class="text-2xl font-bold text-purple-600">${stats.unique_suppliers || 0}</div>
                                <div class="text-sm text-gray-600">Fournisseurs</div>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-orange-200">
                                <div class="text-2xl font-bold text-orange-600">${stats.unique_products || 0}</div>
                                <div class="text-sm text-gray-600">Produits uniques</div>
                            </div>
                        </div>

                        ${stats.products_list && stats.products_list.length > 0 ? `
                            <div class="mt-4">
                                <h5 class="text-sm font-medium text-gray-700 mb-2">Produits trouvés :</h5>
                                <div class="flex flex-wrap gap-2">
                                    ${stats.products_list.map(product => 
                                        `<span class="inline-block bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">
                                            ${this.escapeHtml(product)}
                                        </span>`
                                    ).join('')}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `;

            this.elements.resultsContainer.innerHTML = resultsHtml;
            this.elements.resultsContainer.classList.remove('hidden');
        },

        clearSearch() {
            // Vider l'input
            if (this.elements.input) {
                this.elements.input.value = '';
            }

            // Masquer les résultats
            this.clearResultsDisplay();

            // Restaurer les données originales dans le DataTable
            this.restoreOriginalData();

            // Masquer le bouton d'effacement
            this.hideClearButton();

            // Réinitialiser l'état
            this.currentResults = [];
            this.isSearchActive = false;

            console.log('🧹 Recherche effacée');
        },

        restoreOriginalData() {
            if (ordersTable && this.originalData) {
                try {
                    ordersTable.clear();
                    ordersTable.rows.add(this.originalData);
                    ordersTable.draw();
                    console.log('↩️ Données originales restaurées');
                } catch (error) {
                    console.error('❌ Erreur lors de la restauration:', error);
                    // En cas d'erreur, recharger le tableau
                    Table.reload();
                }
            }
        },

        clearResultsDisplay() {
            if (this.elements.resultsContainer) {
                this.elements.resultsContainer.classList.add('hidden');
                this.elements.resultsContainer.innerHTML = '';
            }
        },

        setLoading(isLoading) {
            if (!this.elements.searchBtn) return;

            if (isLoading) {
                this.elements.searchBtn.disabled = true;
                this.elements.searchBtn.innerHTML = `
                        <span class="loading-spinner mr-2"></span>
                        Recherche...
                    `;
            } else {
                this.elements.searchBtn.disabled = false;
                this.elements.searchBtn.innerHTML = `
                        <span class="material-icons mr-2">search</span>
                        Rechercher
                    `;
            }
        },

        showClearButton() {
            if (this.elements.clearBtn) {
                this.elements.clearBtn.classList.remove('hidden');
            }
        },

        hideClearButton() {
            if (this.elements.clearBtn) {
                this.elements.clearBtn.classList.add('hidden');
            }
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showAlert(type, title, message) {
            if (typeof Swal !== 'undefined') {
                const iconMap = {
                    'success': 'success',
                    'error': 'error',
                    'warning': 'warning',
                    'info': 'info'
                };

                Swal.fire({
                    icon: iconMap[type] || 'info',
                    title: title,
                    text: message,
                    confirmButtonColor: '#3b82f6',
                    timer: type === 'success' ? 3000 : undefined,
                    showConfirmButton: type !== 'success'
                });
            } else {
                alert(`${title}: ${message}`);
            }
        },

        getSearchState() {
            return {
                isActive: this.isSearchActive,
                resultsCount: this.currentResults.length,
                hasOriginalData: !!this.originalData
            };
        }
    };

    // ================================================================
    // GESTIONNAIRE DES STATISTIQUES - MISE À JOUR AVEC REJETS
    // ================================================================
    const Stats = {
        /**
         * Charge les statistiques
         */
        async load() {
            try {
                const response = await fetch(CONFIG.urls.stats);
                const data = await response.json();

                if (data.success) {
                    this.update(data.stats);
                } else {
                    this.showDefaults();
                }
            } catch (error) {
                console.error('❌ Erreur lors du chargement des statistiques:', error);
                this.showDefaults();
            }
        },

        /**
         * Met à jour l'affichage des statistiques - MISE À JOUR AVEC REJETS
         */
        update(stats) {
            this.setElement('total-orders', stats.total_orders);
            this.setElement('total-amount', this.formatAmount(stats.total_amount));
            this.setElement('validated-orders', stats.validated_orders || 0);
            this.setElement('rejected-orders', stats.rejected_orders || 0);
        },

        /**
         * Affiche les valeurs par défaut - MISE À JOUR AVEC REJETS
         */
        showDefaults() {
            this.setElement('total-orders', '0');
            this.setElement('total-amount', '0 FCFA');
            this.setElement('validated-orders', '0');
            this.setElement('rejected-orders', '0');
        },

        /**
         * Met à jour un élément DOM
         */
        setElement(id, value) {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        },

        /**
         * Formate un montant
         */
        formatAmount(amount) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'XOF',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        }
    };

    // ================================================================
    // GESTIONNAIRE DES MODALS - MISE À JOUR AVEC REJETS
    // ================================================================
    const Modal = {
        /**
         * Affiche les détails d'un bon de commande
         */
        async showOrderDetails(orderId) {
            const modal = document.getElementById('preview-modal');
            if (!modal) return;

            modal.style.display = 'flex';
            this.setLoading();

            try {
                const response = await fetch(`${CONFIG.urls.orderDetails}?id=${orderId}&full_details=1`);
                const data = await response.json();

                if (data.success) {
                    this.displayDetails(data);
                } else {
                    this.showError(data.message || "Impossible de charger les détails.");
                }
            } catch (error) {
                console.error('❌ Erreur modal:', error);
                this.showError("Erreur lors de la récupération des données.");
            }
        },

        /**
         * NOUVEAU : Affiche les détails de rejet d'un bon de commande
         */
        async showRejectionDetails(orderId) {
            const modal = document.getElementById('rejection-modal');
            if (!modal) return;

            modal.style.display = 'flex';

            try {
                const response = await fetch(`${CONFIG.urls.orderDetails}?id=${orderId}`);
                const data = await response.json();

                if (data.success && data.order) {
                    this.displayRejectionDetails(data.order);
                } else {
                    this.showRejectionError("Impossible de charger les détails de rejet.");
                }
            } catch (error) {
                console.error('❌ Erreur modal rejet:', error);
                this.showRejectionError("Erreur lors de la récupération des données de rejet.");
            }
        },

        /**
         * NOUVEAU : Affiche les détails de rejet dans le modal
         */
        displayRejectionDetails(order) {
            const content = document.getElementById('rejection-modal-content');
            if (!content) return;

            if (!order.rejected_at || !order.rejection_reason) {
                content.innerHTML = `
                        <div class="text-center py-8">
                            <span class="material-icons text-gray-400 text-6xl mb-4">info</span>
                            <p class="text-gray-600">Ce bon de commande n'a pas été rejeté.</p>
                        </div>
                    `;
                return;
            }

            const rejectedDate = new Date(order.rejected_at).toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            content.innerHTML = `
                    <div class="rejection-details">
                        <div class="mb-4">
                            <h4 class="rejection-reason">Motif du rejet :</h4>
                            <p class="text-gray-800 bg-white p-3 rounded border">${order.rejection_reason}</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 rejection-meta">
                            <div>
                                <strong>Date de rejet :</strong><br>
                                ${rejectedDate}
                            </div>
                            <div>
                                <strong>Rejeté par :</strong><br>
                                ${order.rejected_by_username || 'Utilisateur inconnu'}
                            </div>
                            <div>
                                <strong>Bon de commande :</strong><br>
                                ${order.order_number}
                            </div>
                            <div>
                                <strong>Fournisseur :</strong><br>
                                ${order.fournisseur}
                            </div>
                        </div>
                        
                        <div class="mt-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                            <p class="text-sm text-yellow-800">
                                <span class="material-icons text-yellow-600 mr-1" style="font-size: 16px; vertical-align: middle;">info</span>
                                Ce bon de commande a été rejeté et ne peut plus être traité. 
                                Contactez le service finance pour plus d'informations.
                            </p>
                        </div>
                    </div>
                `;
        },

        /**
         * NOUVEAU : Affiche une erreur dans le modal de rejet
         */
        showRejectionError(message) {
            const content = document.getElementById('rejection-modal-content');
            if (content) {
                content.innerHTML = `
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                            <p class="font-bold">Erreur</p>
                            <p>${message}</p>
                        </div>
                    `;
            }
        },

        /**
         * NOUVEAU : Ferme le modal de rejet
         */
        closeRejectionModal() {
            const modal = document.getElementById('rejection-modal');
            if (modal) modal.style.display = 'none';
        },

        /**
         * Affiche l'état de chargement
         */
        setLoading() {
            const content = document.getElementById('modal-content');
            if (content) {
                content.innerHTML = `
                <div class="flex justify-center items-center h-32">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-700"></div>
                    <p class="ml-3 text-gray-600">Chargement des données...</p>
                </div>
            `;
            }
        },

        /**
         * Affiche les détails du bon de commande
         */
        displayDetails(data) {
            const title = document.getElementById('modal-title');
            const content = document.getElementById('modal-content');
            const downloadBtn = document.getElementById('download-btn');

            if (title) title.textContent = `Bon de Commande ${data.order.order_number}`;
            if (downloadBtn) downloadBtn.onclick = () => window.location.href = data.order.file_path;
            if (content) content.innerHTML = this.buildDetailsHTML(data);
        },

        /**
         * Construit le HTML des détails
         */
        buildDetailsHTML(data) {
            const order = data.order;
            const isMulti = order.is_multi_project == 1;
            const formattedDate = new Date(order.generated_at).toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            // NOUVEAU : Affichage du statut de rejet si applicable
            let statusBadge = '';
            if (order.rejected_at) {
                const rejectedDate = new Date(order.rejected_at).toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                statusBadge = `
                        <div class="rejection-details mb-4">
                            <h3 class="rejection-reason">⚠️ BON DE COMMANDE REJETÉ</h3>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                    <div><strong>Date de rejet :</strong> ${rejectedDate}</div>
                                    <div><strong>Rejeté par :</strong> ${order.rejected_by_username || 'Utilisateur inconnu'}</div>
                                </div>
                                <div class="mt-2">
                                    <strong>Motif :</strong> 
                                    <span class="text-red-700">${order.rejection_reason || 'Aucun motif spécifié'}</span>
                                </div>
                            </div>
                        </div>
                    `;
            }

            return `
                    ${statusBadge}
                    <div class="bg-white rounded-lg border border-gray-200 mb-6 overflow-hidden">
                        <div class="bg-gray-50 p-4 border-b border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">N° Bon de commande:</p>
                                    <p class="font-bold text-xl">${order.order_number}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-600">Date de génération:</p>
                                    <p class="font-bold">${formattedDate}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="font-bold text-lg uppercase mb-2">FOURNISSEUR</h3>
                            <p class="font-medium text-lg">${order.fournisseur}</p>
                        </div>
                        
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="font-bold text-lg uppercase mb-2">
                                ${isMulti ? 'COMMANDE MULTI-PROJETS' : 'INFORMATIONS DU PROJET'}
                            </h3>
                            ${this.buildProjectInfo(data.projects)}
                        </div>
                        
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="font-bold text-lg uppercase mb-4">LISTE DES MATÉRIAUX</h3>
                            ${this.buildMaterialsTable(data.consolidated_materials || data.materials)}
                        </div>
                        
                        <div class="p-4">
                            <h3 class="font-bold text-lg uppercase mb-4">SIGNATURES</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="border p-3">
                                    <p class="font-medium">Service achat: ${order.username}</p>
                                </div>
                                <div class="border p-3">
                                    <p class="font-medium">Service finance:</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
        },

        /**
         * Construit les informations de projet - VERSION CORRIGÉE avec regroupement
         */
        buildProjectInfo(projects) {
            if (!projects || projects.length === 0) {
                return '<p class="text-gray-500 italic">Aucune information de projet disponible</p>';
            }

            // Éliminer les doublons en créant un Map avec le code_projet comme clé
            const uniqueProjects = new Map();

            projects.forEach(project => {
                const key = `${project.code_projet}-${project.nom_client}`;
                if (!uniqueProjects.has(key)) {
                    uniqueProjects.set(key, project);
                }
            });

            // Convertir le Map en tableau et construire le HTML
            const uniqueProjectsArray = Array.from(uniqueProjects.values());

            if (uniqueProjectsArray.length === 0) {
                return '<p class="text-gray-500 italic">Aucune information de projet disponible</p>';
            }

            // Construire la liste HTML avec les projets uniques
            return '<ul class="list-disc list-inside">' +
                uniqueProjectsArray.map(p => `<li>${p.code_projet} - ${p.nom_client}</li>`).join('') +
                '</ul>';
        },

        /**
         * Construit le tableau des matériaux
         */
        buildMaterialsTable(materials) {
            if (!materials || materials.length === 0) {
                return '<p class="text-gray-500 italic">Aucun matériau disponible</p>';
            }

            const total = materials.reduce((sum, m) => {
                const qty = parseFloat(m.quantity || m.qt_acheter || 0);
                const price = parseFloat(m.prix_unitaire || 0);
                return sum + (qty * price);
            }, 0);

            const rows = materials.map(m => {
                const qty = parseFloat(m.quantity || m.qt_acheter || 0);
                const price = parseFloat(m.prix_unitaire || 0);
                const itemTotal = qty * price;

                return `
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm font-medium">${m.designation || ''}</td>
                            <td class="px-4 py-2 text-sm">${qty.toFixed(2)}</td>
                            <td class="px-4 py-2 text-sm">${m.unit || ''}</td>
                            <td class="px-4 py-2 text-sm">${price.toLocaleString('fr-FR')} FCFA</td>
                            <td class="px-4 py-2 text-sm">${itemTotal.toLocaleString('fr-FR')} FCFA</td>
                        </tr>
                    `;
            }).join('');

            return `
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Désignation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Quantité</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Unité</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Prix Unitaire</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Montant Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${rows}
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50 font-bold">
                                    <td colspan="4" class="px-4 py-2 text-right text-sm uppercase">TOTAL</td>
                                    <td class="px-4 py-2 text-sm">${total.toLocaleString('fr-FR')} FCFA</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                `;
        },

        /**
         * Affiche une erreur
         */
        showError(message) {
            const content = document.getElementById('modal-content');
            if (content) {
                content.innerHTML = `
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                            <p class="font-bold">Erreur</p>
                            <p>${message}</p>
                        </div>
                    `;
            }
        },

        /**
         * Ferme le modal principal
         */
        close() {
            const modal = document.getElementById('preview-modal');
            if (modal) modal.style.display = 'none';
        }
    };

    // ================================================================
    // GESTIONNAIRE DES FILTRES - MISE À JOUR AVEC REJETS
    // ================================================================
    const Filters = {
        /**
         * Initialise les filtres
         */
        init() {
            this.bindToggle();
            this.initCustomFilters();
        },

        /**
         * Lie l'événement de toggle
         */
        bindToggle() {
            const toggleBtn = document.getElementById('toggle-filters-orders');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', this.toggle);
            }
        },

        /**
         * Bascule l'affichage des filtres
         */
        toggle() {
            const section = document.getElementById('filters-section-orders');
            const btn = document.getElementById('toggle-filters-orders');

            if (!section || !btn) return;

            const isHidden = section.classList.contains('hidden');

            if (isHidden) {
                section.classList.remove('hidden');
                btn.innerHTML = '<span class="material-icons align-middle">filter_list</span> Masquer les filtres';
            } else {
                section.classList.add('hidden');
                btn.innerHTML = '<span class="material-icons align-middle">filter_list</span> Afficher les filtres';
            }
        },

        /**
         * Initialise les filtres personnalisés - MISE À JOUR AVEC REJETS
         */
        initCustomFilters() {
            setTimeout(() => {
                if (window.CustomFilters?.OrdersFilters) {
                    // Mise à jour du module de filtres pour supporter les rejets
                    const originalApplyCustomFilters = window.CustomFilters.OrdersFilters
                        .applyCustomFilters;

                    window.CustomFilters.OrdersFilters.applyCustomFilters = function(filters) {
                        // Appeler la fonction originale
                        if (originalApplyCustomFilters) {
                            originalApplyCustomFilters.call(this, filters);
                        }

                        // Ajouter le filtre pour les rejets
                        const statusFilter = filters.status;
                        if (statusFilter) {
                            jQuery.fn.dataTable.ext.search.push(
                                function(settings, data, dataIndex) {
                                    if (settings.nTable.id !== 'purchaseOrdersTable') {
                                        return true;
                                    }

                                    // Statut (colonne 8)
                                    const status = data[8];
                                    if (!status) {
                                        return false;
                                    }

                                    if (statusFilter === 'validated' && !status.includes(
                                            'Validé')) {
                                        return false;
                                    }
                                    if (statusFilter === 'pending' && !status.includes('attente')) {
                                        return false;
                                    }
                                    // NOUVEAU : Filtre pour les rejets
                                    if (statusFilter === 'rejected' && !status.includes('Rejeté')) {
                                        return false;
                                    }

                                    return true;
                                }
                            );
                        }
                    };

                    // Mise à jour des statistiques pour inclure les rejets
                    const originalUpdateStatistics = window.CustomFilters.OrdersFilters.updateStatistics;

                    window.CustomFilters.OrdersFilters.updateStatistics = function() {
                        const data = this.table.rows({
                            search: 'applied'
                        }).data();

                        let totalAmount = 0;
                        let validatedCount = 0;
                        let pendingCount = 0;
                        let rejectedCount = 0;
                        let multiProjectCount = 0;

                        data.each(function(row) {
                            // Vérifier que la ligne a bien toutes les colonnes nécessaires
                            if (!row || row.length < 9) {
                                console.warn('Ligne incomplète:', row);
                                return;
                            }

                            // Montant (colonne 5)
                            const amountStr = row[5];
                            if (amountStr) {
                                const amount = parseFloat(amountStr.replace(/[^\d,-]/g, '')
                                    .replace(',', '.'));
                                if (!isNaN(amount)) {
                                    totalAmount += amount;
                                }
                            }

                            // Statut (colonne 8) - MISE À JOUR AVEC REJETS
                            const status = row[8];
                            if (status) {
                                if (status.includes('Rejeté')) {
                                    rejectedCount++;
                                } else if (status.includes('Validé')) {
                                    validatedCount++;
                                } else if (status.includes('attente')) {
                                    pendingCount++;
                                }
                            }

                            // Multi-projets (colonne 6)
                            const type = row[6];
                            if (type && type.includes('Multi')) {
                                multiProjectCount++;
                            }
                        });

                        // Mettre à jour l'affichage - MISE À JOUR AVEC REJETS
                        const totalElement = document.getElementById('filtered-total');
                        if (totalElement) {
                            totalElement.textContent = data.length;
                        }

                        const amountElement = document.getElementById('filtered-amount');
                        if (amountElement) {
                            amountElement.textContent = new Intl.NumberFormat('fr-FR', {
                                style: 'currency',
                                currency: 'XOF',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }).format(totalAmount);
                        }

                        const validatedElement = document.getElementById('filtered-validated');
                        if (validatedElement) {
                            validatedElement.textContent = validatedCount;
                        }

                        const pendingElement = document.getElementById('filtered-pending');
                        if (pendingElement) {
                            pendingElement.textContent = pendingCount;
                        }

                        // NOUVEAU : Élément pour les rejets
                        const rejectedElement = document.getElementById('filtered-rejected');
                        if (rejectedElement) {
                            rejectedElement.textContent = rejectedCount;
                        }

                        const multiElement = document.getElementById('filtered-multi');
                        if (multiElement) {
                            multiElement.textContent = multiProjectCount;
                        }
                    };

                    window.CustomFilters.OrdersFilters.init();
                }
            }, 1000);
        }
    };

    // ================================================================
    // INITIALISATION AUTOMATIQUE
    // ================================================================
    document.addEventListener('DOMContentLoaded', () => {
        App.init();
        // Initialiser la recherche par produit si les éléments existent
        if (document.getElementById('product-search-input')) {
            ProductSearch.init();
        }
    });

    // Export pour utilisation externe
    window.ProductSearch = ProductSearch;
    </script>
</body>

</html>