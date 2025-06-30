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

$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matériaux Reçus - DYM BE</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ========= datatable =========== -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <!-- ========= datatable js =========== -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js">
    </script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js">
    </script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

    <style>
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background-color: #f3f4f6;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-badge .material-icons {
        font-size: 0.875rem;
        margin-right: 0.25rem;
    }

    .badge-received {
        background-color: #d1f5ea;
        color: #065f46;
    }

    .badge-partial {
        background-color: #fef3c7;
        color: #92400e;
    }

    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-in {
        animation: fadeIn 0.5s ease-out forwards;
    }

    /* Tableaux */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 15px;
    }

    .dataTables_wrapper .dataTables_info {
        padding-top: 15px;
    }

    .dataTables_wrapper .dataTables_paginate {
        padding-top: 15px;
    }

    table.dataTable thead th {
        position: relative;
        background-image: none !important;
    }

    table.dataTable thead th.sorting:after,
    table.dataTable thead th.sorting_asc:after,
    table.dataTable thead th.sorting_desc:after {
        position: absolute;
        top: 12px;
        right: 8px;
        display: block;
        font-family: "Font Awesome 5 Free";
        font-weight: 900;
    }

    table.dataTable thead th.sorting:after {
        content: "\f0dc";
        color: #ddd;
    }

    table.dataTable thead th.sorting_asc:after {
        content: "\f0de";
    }

    table.dataTable thead th.sorting_desc:after {
        content: "\f0dd";
    }

    /* Empty state */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        text-align: center;
    }

    .empty-state-icon {
        font-size: 4rem;
        color: #e5e7eb;
        margin-bottom: 1rem;
    }

    .empty-state-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    .empty-state-description {
        color: #6b7280;
        max-width: 400px;
    }

    /* Filtres */
    .filter-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 1rem;
    }

    .filter-btn {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        border: 1px solid #e5e7eb;
    }

    .filter-btn:hover {
        background-color: #f9fafb;
    }

    .filter-btn.active {
        background-color: #4F46E5;
        color: white;
    }

    /* Material detail styles */
    .material-details {
        background-color: #f9fafb;
        border-left: 4px solid #4f46e5;
        border-radius: 0.375rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .material-detail-row {
        display: flex;
        margin-bottom: 0.5rem;
    }

    .material-detail-label {
        font-weight: 500;
        color: #4b5563;
        width: 180px;
    }

    .material-detail-value {
        color: #1f2937;
        flex: 1;
    }

    /* Statistics cards */
    .stats-card {
        background-color: white;
        border-radius: 0.5rem;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }

    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .stats-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }

    .stats-title {
        font-size: 0.875rem;
        font-weight: 500;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }

    .stats-value {
        font-size: 1.5rem;
        font-weight: 600;
        color: #111827;
    }

    .stats-subtitle {
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 0.25rem;
    }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../components/navbar.php'; ?>

        <div class="container mx-auto px-4 py-6">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-wrap justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 my-2">
                        <i class="fas fa-box-open text-green-600 mr-2"></i>
                        Matériaux Reçus
                    </h1>
                    <div class="text-sm text-gray-500 flex items-center">
                        <i class="far fa-calendar-alt mr-2"></i>
                        <span id="current-date"></span>
                    </div>
                </div>

                <div class="flex flex-wrap justify-between items-center mb-4">
                    <div class="mb-3">
                        <a href="dashboard.php"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-sm transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Retour au Tableau de Bord
                        </a>
                    </div>
                    <div class="text-sm text-gray-500">
                        <span class="block bg-blue-100 px-3 py-1 rounded-full">
                            <i class="fas fa-info-circle mr-1"></i>
                            Consultez les matériaux reçus pour vos projets
                        </span>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" id="stats-container">
                    <div class="stats-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="stats-title">Total matériaux reçus</div>
                                <div class="stats-value" id="total-received">--</div>
                                <div class="stats-subtitle">Depuis le début</div>
                            </div>
                            <div class="stats-icon bg-green-100">
                                <i class="fas fa-boxes text-green-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="stats-title">Reçus ce mois</div>
                                <div class="stats-value" id="month-received">--</div>
                                <div class="stats-subtitle">Derniers 30 jours</div>
                            </div>
                            <div class="stats-icon bg-blue-100">
                                <i class="fas fa-calendar-check text-blue-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="stats-title">Montant total</div>
                                <div class="stats-value" id="total-value">--</div>
                                <div class="stats-subtitle">Valeur des matériaux</div>
                            </div>
                            <div class="stats-icon bg-purple-100">
                                <i class="fas fa-money-bill-wave text-purple-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="stats-title">Projets concernés</div>
                                <div class="stats-value" id="total-projects">--</div>
                                <div class="stats-subtitle">Avec matériaux reçus</div>
                            </div>
                            <div class="stats-icon bg-yellow-100">
                                <i class="fas fa-project-diagram text-yellow-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="mb-4">
                    <div class="flex flex-wrap items-center justify-between mb-2">
                        <h2 class="text-lg font-semibold text-gray-700 mb-2">Liste des matériaux reçus</h2>
                        <div class="filter-container">
                            <button class="filter-btn active" data-period="all">
                                <i class="fas fa-globe mr-2"></i> Tous
                            </button>
                            <button class="filter-btn" data-period="month">
                                <i class="fas fa-calendar-day mr-2"></i> Ce mois
                            </button>
                            <button class="filter-btn" data-period="quarter">
                                <i class="fas fa-calendar-week mr-2"></i> Ce trimestre
                            </button>
                            <button class="filter-btn" data-period="year">
                                <i class="fas fa-calendar mr-2"></i> Cette année
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Conteneur principal pour le contenu dynamique -->
                <div id="main-content-container">

                    <!-- Message de chargement -->
                    <div id="materials-loading" class="text-center py-8" style="display: none;">
                        <div
                            class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-indigo-500 border-r-transparent">
                        </div>
                        <p class="text-gray-500 mt-2">Chargement des matériaux reçus...</p>
                    </div>

                    <!-- Tableau des matériaux reçus -->
                    <div id="table-container" class="overflow-x-auto" style="display: none;">
                        <table id="received-materials-table"
                            class="min-w-full divide-y divide-gray-200 display responsive nowrap">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ID Projet</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Code Projet</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nom Client</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Désignation</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Quantité</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Unité</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fournisseur</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Prix Unitaire</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date Réception</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Statut</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody id="materials-table-body" class="bg-white divide-y divide-gray-200">
                                <!-- Les matériaux seront chargés ici par JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Message d'état vide -->
                    <div id="materials-empty" class="empty-state text-center py-12" style="display: none;">
                        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                        <h3 class="empty-state-title text-xl font-semibold text-gray-600 mb-2">Aucun matériau reçu</h3>
                        <p class="empty-state-description text-gray-500 max-w-md mx-auto">
                            Aucun matériau n'a encore été reçu pour vos projets.
                        </p>
                    </div>

                </div>
            </div>
        </div>

        <!-- Modal de détails du matériau -->
        <div id="material-details-modal"
            class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center hidden z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl mx-4">
                <div class="flex justify-between items-start mb-4">
                    <h2 class="text-xl font-bold text-gray-800" id="modal-title">Détails du Matériau</h2>
                    <button id="close-modal" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="modal-content" class="mt-4">
                    <!-- Le contenu sera chargé dynamiquement -->
                </div>
                <div class="mt-6 flex justify-end">
                    <button id="close-modal-btn"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Variables globales
    let dataTable = null;
    let materialsList = [];

    // Fonction pour initialiser la page
    $(document).ready(function() {
        console.log('=== INITIALISATION DE LA PAGE ===');

        // Afficher la date courante
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        const today = new Date();
        document.getElementById('current-date').textContent = today.toLocaleDateString('fr-FR', options);

        // Charger les matériaux reçus
        loadReceivedMaterials('all');

        // Gestionnaires pour les filtres de période
        $('.filter-btn').on('click', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            const period = $(this).data('period');
            console.log('Changement de filtre vers:', period);
            loadReceivedMaterials(period);
        });

        // Gestionnaires pour les boutons de fermeture de la modal
        $('#close-modal, #close-modal-btn').on('click', function() {
            $('#material-details-modal').addClass('hidden');
        });

        // Fermer la modal si on clique en dehors
        $('#material-details-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).addClass('hidden');
            }
        });
    });

    // Fonction pour gérer l'affichage des éléments
    function resetDisplayStates() {
        console.log('Réinitialisation des états d\'affichage');
        $('#materials-loading').hide();
        $('#table-container').hide();
        $('#materials-empty').hide();
    }

    function showLoading() {
        console.log('Affichage du chargement');
        resetDisplayStates();
        $('#materials-loading').show();
    }

    function showTable() {
        console.log('Affichage du tableau');
        resetDisplayStates();
        $('#table-container').show();
    }

    function showEmptyState(title = null, description = null) {
        console.log('Affichage de l\'état vide');
        resetDisplayStates();

        if (title) {
            $('#materials-empty .empty-state-title').text(title);
        }
        if (description) {
            $('#materials-empty .empty-state-description').text(description);
        }

        $('#materials-empty').show();
    }

    // Fonction pour charger les matériaux reçus - VERSION ENTIÈREMENT CORRIGÉE
    function loadReceivedMaterials(period) {
        console.log('=== DÉBUT CHARGEMENT MATÉRIAUX ===');
        console.log('Période demandée:', period);

        // Afficher le chargement
        showLoading();

        // Si une instance DataTable existe déjà, la détruire
        if (dataTable !== null) {
            try {
                dataTable.destroy();
                dataTable = null;
                console.log('DataTable détruite avec succès');
            } catch (e) {
                console.error('Erreur lors de la destruction de DataTable:', e);
            }
        }

        // Vider le tableau
        $('#materials-table-body').empty();

        // Appel à l'API avec la période sélectionnée
        $.ajax({
            url: 'api_received/api_getReceivedMaterials.php',
            type: 'GET',
            data: {
                period: period
            },
            dataType: 'json',
            timeout: 30000, // 30 secondes de timeout
            success: function(response) {
                console.log('=== RÉPONSE API REÇUE ===');
                console.log('Response complète:', response);

                // Vérification robuste de la réponse
                if (response && typeof response === 'object') {

                    if (response.success === true) {
                        console.log('✅ API Success = true');

                        // Mettre à jour les statistiques en premier
                        if (response.stats) {
                            updateStatistics(response.stats);
                            console.log('Statistiques mises à jour:', response.stats);
                        }

                        // Vérifier les matériaux
                        console.log('Vérification des matériaux...');
                        console.log('response.materials existe:', !!response.materials);
                        console.log('Type de response.materials:', typeof response.materials);
                        console.log('Est un Array:', Array.isArray(response.materials));
                        console.log('Longueur:', response.materials ? response.materials.length : 'N/A');

                        if (response.materials && Array.isArray(response.materials) && response.materials
                            .length > 0) {
                            console.log('✅ Matériaux trouvés - Nombre:', response.materials.length);
                            materialsList = response.materials;
                            displayReceivedMaterials(materialsList);
                        } else {
                            console.log('⚠️ Aucun matériau trouvé');
                            showEmptyState('Aucun matériau trouvé',
                                'Aucun matériau n\'a été trouvé pour la période sélectionnée.');
                        }
                    } else {
                        console.log('❌ API Success = false');
                        console.log('Message d\'erreur:', response.message);
                        showEmptyState('Erreur de chargement', response.message ||
                            'Une erreur est survenue lors du chargement des matériaux.');

                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: response.message ||
                                'Impossible de charger les matériaux reçus. Veuillez réessayer plus tard.',
                            confirmButtonColor: '#4F46E5'
                        });
                    }
                } else {
                    console.log('❌ Réponse API invalide');
                    console.log('Type de réponse:', typeof response);
                    console.log('Contenu de la réponse:', response);
                    showEmptyState('Erreur de format', 'La réponse du serveur est invalide.');
                }

                console.log('=== FIN TRAITEMENT RÉPONSE ===');
            },
            error: function(xhr, status, error) {
                console.log('=== ERREUR AJAX ===');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                console.error('Status Code:', xhr.status);

                let errorMessage = 'Erreur de connexion au serveur.';

                if (xhr.status === 404) {
                    errorMessage = 'Fichier API introuvable (404).';
                } else if (xhr.status === 500) {
                    errorMessage = 'Erreur interne du serveur (500).';
                } else if (status === 'timeout') {
                    errorMessage = 'Délai d\'attente dépassé.';
                } else if (status === 'parsererror') {
                    errorMessage = 'Erreur de format de réponse JSON.';
                }

                showEmptyState('Erreur de connexion', errorMessage);

                Swal.fire({
                    icon: 'error',
                    title: 'Erreur de connexion',
                    text: errorMessage + ' Veuillez vérifier votre connexion et réessayer.',
                    confirmButtonColor: '#4F46E5'
                });
            }
        });
    }

    // Fonction pour mettre à jour les statistiques
    function updateStatistics(stats) {
        console.log('Mise à jour des statistiques:', stats);

        if (!stats || typeof stats !== 'object') {
            console.log('Stats non valides, utilisation des valeurs par défaut');
            stats = {
                total_received: 0,
                month_received: 0,
                total_value: 0,
                total_projects: 0
            };
        }

        // Formater les nombres avec séparateurs de milliers
        const formatNumber = (num) => {
            const number = parseInt(num) || 0;
            return new Intl.NumberFormat('fr-FR').format(number);
        };

        // Mettre à jour les valeurs des statistiques
        $('#total-received').text(formatNumber(stats.total_received));
        $('#month-received').text(formatNumber(stats.month_received));
        $('#total-projects').text(formatNumber(stats.total_projects));

        // Pour le montant, on garde "---" comme dans l'original
        $('#total-value').text('---');

        console.log('✅ Statistiques affichées avec succès');
    }

    // Fonction pour afficher les matériaux reçus - VERSION ENTIÈREMENT CORRIGÉE
    function displayReceivedMaterials(materials) {
        console.log('=== DÉBUT AFFICHAGE DES MATÉRIAUX ===');
        console.log('Nombre de matériaux à afficher:', materials.length);

        if (!materials || !Array.isArray(materials) || materials.length === 0) {
            console.log('❌ Aucun matériau à afficher');
            showEmptyState('Aucun matériau', 'Aucun matériau trouvé.');
            return;
        }

        // Vider le tableau
        const tableBody = $('#materials-table-body');
        tableBody.empty();

        let successCount = 0;

        // Parcourir les matériaux et créer les lignes
        materials.forEach((material, index) => {
            try {
                console.log(`Traitement matériau ${index + 1}:`, material);

                // Formatter la date de réception
                const receptionDate = material.date_reception ? formatDate(material.date_reception, true) :
                    'N/A';

                // Déterminer la classe pour le badge de statut
                let statusBadgeClass = 'badge-received';
                let statusText = 'Reçu';

                if (material.is_partial == 1) {
                    statusBadgeClass = 'badge-partial';
                    statusText = 'Partiel';
                }

                // Formater le prix unitaire
                const prixUnitaire = '--- FCFA'; // Comme dans l'original

                // Priorité à la quantité de expression_dym
                const quantite = material.ed_quantity || material.original_quantity || material.am_quantity ||
                    '0';

                const row = $(`
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">${material.idExpression || 'N/A'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">${material.code_projet || 'N/A'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">${material.nom_client || 'N/A'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">${material.designation || 'N/A'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">${quantite}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">${material.unit || 'unité'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">${material.fournisseur || 'N/A'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">${prixUnitaire}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">${receptionDate}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge ${statusBadgeClass}">${statusText}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="text-indigo-600 hover:text-indigo-900 mr-3 view-details-btn" 
                                        data-id="${material.id}" 
                                        onclick="viewMaterialDetails(${material.id})">
                                    <i class="fas fa-eye"></i> Détails
                                </button>
                            </td>
                        </tr>
                    `);

                tableBody.append(row);
                successCount++;

            } catch (e) {
                console.error(`Erreur lors du traitement du matériau ${index + 1}:`, e);
            }
        });

        console.log(`✅ ${successCount} lignes ajoutées au tableau sur ${materials.length}`);

        if (successCount > 0) {
            // Afficher le tableau
            showTable();

            // Initialiser DataTable
            try {
                dataTable = $('#received-materials-table').DataTable({
                    responsive: true,
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                    },
                    dom: 'Bfrtip',
                    buttons: [{
                            extend: 'excel',
                            text: '<i class="fas fa-file-excel mr-1"></i> Excel',
                            className: 'mr-2',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                            }
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="fas fa-file-pdf mr-1"></i> PDF',
                            className: 'mr-2',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                            }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print mr-1"></i> Imprimer',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                            }
                        }
                    ]
                });

                console.log('✅ DataTable initialisée avec succès');
            } catch (e) {
                console.error('❌ Erreur lors de l\'initialisation de DataTable:', e);
                // Même en cas d'erreur DataTable, on affiche le tableau
                showTable();
            }
        } else {
            console.log('❌ Aucune ligne valide créée');
            showEmptyState('Erreur d\'affichage', 'Impossible d\'afficher les matériaux.');
        }

        console.log('=== FIN AFFICHAGE DES MATÉRIAUX ===');
    }

    // Fonction pour voir les détails d'un matériau
    function viewMaterialDetails(materialId) {
        console.log('Affichage des détails pour le matériau ID:', materialId);

        // Trouver le matériau dans la liste
        const material = materialsList.find(m => m.id == materialId);

        if (!material) {
            console.error('Matériau non trouvé:', materialId);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Impossible de trouver les détails de ce matériau.',
                confirmButtonColor: '#4F46E5'
            });
            return;
        }

        // Priorité à la quantité de expression_dym
        const displayQuantity = material.ed_quantity || material.original_quantity || material.am_quantity || '0';

        // Utiliser le prix unitaire de expression_dym si disponible
        const displayPrice = material.ed_prix_unitaire || material.prix_unitaire;

        // Préparer le contenu de la modal
        const modalContent = `
                <div class="material-details">
                    <div class="bg-gray-100 p-4 rounded-md mb-4">
                        <h3 class="font-medium text-gray-800 mb-2">Informations sur le matériau</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="material-detail-row">
                                    <div class="material-detail-label">Désignation:</div>
                                    <div class="material-detail-value">${material.designation || 'N/A'}</div>
                                </div>
                                <div class="material-detail-row">
                                    <div class="material-detail-label">Quantité:</div>
                                    <div class="material-detail-value">${displayQuantity} ${material.unit || 'unité'}</div>
                                </div>
                                <div class="material-detail-row">
                                    <div class="material-detail-label">Fournisseur:</div>
                                    <div class="material-detail-value">${material.fournisseur || 'Non spécifié'}</div>
                                </div>
                                <div class="material-detail-row">
                                    <div class="material-detail-label">Date de réception:</div>
                                    <div class="material-detail-value">${formatDate(material.date_reception, true)}</div>
                                </div>
                            </div>
                            <div>
                                <div class="material-detail-row">
                                    <div class="material-detail-label">ID Projet:</div>
                                    <div class="material-detail-value">${material.idExpression || 'N/A'}</div>
                                </div>
                                <div class="material-detail-row">
                                    <div class="material-detail-label">Code Projet:</div>
                                    <div class="material-detail-value">${material.code_projet || 'N/A'}</div>
                                </div>
                                <div class="material-detail-row">
                                    <div class="material-detail-label">Client:</div>
                                    <div class="material-detail-value">${material.nom_client || 'N/A'}</div>
                                </div>
                                <div class="material-detail-row">
                                    <div class="material-detail-label">Statut:</div>
                                    <div class="material-detail-value">
                                        <span class="status-badge ${material.is_partial == 1 ? 'badge-partial' : 'badge-received'}">
                                            ${material.is_partial == 1 ? 'Partiel' : 'Reçu'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    ${material.notes ? `
                    <div class="bg-yellow-50 p-4 rounded-md mb-4">
                        <h3 class="font-medium text-yellow-800 mb-2">Notes</h3>
                        <p class="text-yellow-700">${material.notes}</p>
                    </div>
                    ` : ''}
                </div>
            `;

        // Mettre à jour le titre et le contenu de la modal
        $('#modal-title').text(`Détails du Matériau: ${material.designation}`);
        $('#modal-content').html(modalContent);

        // Afficher la modal
        $('#material-details-modal').removeClass('hidden');
    }

    // Fonction pour formater une date
    function formatDate(dateString, includeTime = false) {
        if (!dateString) return 'N/A';

        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return 'N/A';

            const options = {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            };

            if (includeTime) {
                options.hour = '2-digit';
                options.minute = '2-digit';
            }

            return date.toLocaleDateString('fr-FR', options);
        } catch (e) {
            console.error('Erreur lors du formatage de date:', e);
            return 'N/A';
        }
    }
    </script>
</body>

</html>