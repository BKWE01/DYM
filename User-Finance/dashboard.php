<?php

/**
 * dashboard.php - Service Finance (Version 2.0)
 * Tableau de bord refactorisé avec architecture orientée classe
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

session_start();

// Auto-loading des classes
require_once __DIR__ . '/classes/FinanceController.php';

// Mesurer le temps d'exécution de la page
$pageStartTime = microtime(true);

// Vérification sécurisée de la session
try {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ./../index.php");
        exit();
    }

    // Initialiser le contrôleur Finance
    $financeController = new FinanceController();

    // Vérifier les permissions Finance
    if (!$financeController->validateFinanceAccess($_SESSION)) {
        header("Location: ./../index.php?notification=error&message=Accès non autorisé");
        exit();
    }

    // Récupérer les statistiques pour l'affichage
    $statsResponse = $financeController->getFinanceStats();
    $stats = $statsResponse->isSuccess() ? $statsResponse->getData() : [];

    // Journaliser l'accès au dashboard
    $financeController->logFinanceAction(
        $_SESSION['user_id'],
        'access_dashboard',
        0,
        ['user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown']
    );
} catch (Exception $e) {
    error_log("Erreur dashboard Finance: " . $e->getMessage());
    header("Location: ./../index.php?notification=error&message=Erreur technique");
    exit();
}

// Configuration de la page
$pageConfig = [
    'title' => 'Gestion des Bons de Commande | Service Finance',
    'version' => '2.0',
    'user_id' => $_SESSION['user_id'],
    'user_name' => $_SESSION['name'] ?? 'Utilisateur',
    'execution_time' => microtime(true) - $pageStartTime
];
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageConfig['title']) ?></title>

    <!-- Meta informations -->
    <meta name="description" content="Tableau de bord Finance - Gestion et signature des bons de commande">
    <meta name="author" content="DYM MANUFACTURE">
    <meta name="version" content="<?= $pageConfig['version'] ?>">

    <!-- Styles CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

    <!-- Styles personnalisés Finance -->
    <link rel="stylesheet" href="assets/css/finance-dashboard.css">
</head>

<body>
    <div class="wrapper">
        <?php include_once '../components/navbar_finance.php'; ?>

        <main class="flex-1 p-6">
            <!-- Barre supérieure -->
            <div class="top-bar p-4 mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="dashboard-btn-container">
                    <button class="dashboard-btn" onclick="location.reload()">
                        <span class="material-icons">dashboard</span>
                        Tableau de bord Finance v<?= $pageConfig['version'] ?>
                    </button>
                </div>

                <div class="date-time">
                    <span class="material-icons">calendar_today</span>
                    <span id="date-time-display">Chargement...</span>
                </div>
            </div>

            <!-- Section des statistiques -->
            <div class="stats-grid mb-6">
                <div class="stat-card stat-pending">
                    <div class="stat-icon">
                        <span class="material-icons">pending_actions</span>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number" id="stat-pending-count"><?= $stats['pending_count'] ?? 0 ?></h3>
                        <p class="stat-label">En attente de signature</p>
                    </div>
                </div>

                <div class="stat-card stat-signed">
                    <div class="stat-icon">
                        <span class="material-icons">verified</span>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number" id="stat-signed-count"><?= $stats['signed_count'] ?? 0 ?></h3>
                        <p class="stat-label">Bons signés</p>
                    </div>
                </div>

                <div class="stat-card stat-amount">
                    <div class="stat-icon">
                        <span class="material-icons">payments</span>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number" id="stat-total-amount">
                            <?= number_format($stats['total_signed_amount'] ?? 0, 0, ',', ' ') ?> FCFA
                        </h3>
                        <p class="stat-label">Montant total signé</p>
                    </div>
                </div>
            </div>

            <!-- En-tête avec actions -->
            <div class="page-header mb-6 flex justify-between items-center">
                <h2 class="page-title">Gestion des Bons de Commande</h2>

                <div class="action-buttons flex space-x-2">
                    <button id="refresh-data" class="action-btn btn-refresh" title="Actualiser les données">
                        <span class="material-icons">refresh</span>
                        Actualiser
                    </button>
                    <button id="export-excel" class="action-btn btn-export-excel" title="Exporter en Excel">
                        <span class="material-icons">file_download</span>
                        Excel
                    </button>
                    <button id="export-pdf" class="action-btn btn-export-pdf" title="Exporter en PDF">
                        <span class="material-icons">picture_as_pdf</span>
                        PDF
                    </button>
                </div>
            </div>

            <!-- Container principal avec onglets -->
            <div class="card-container mb-8">
                <!-- Navigation des onglets -->
                <div class="tab-container">
                    <div class="tab active" data-tab="pending">
                        <span class="material-icons align-middle mr-2">pending_actions</span>
                        En attente de signature
                        <span class="tab-badge" id="pending-badge"><?= $stats['pending_count'] ?? 0 ?></span>
                    </div>
                    <div class="tab" data-tab="signed">
                        <span class="material-icons align-middle mr-2">verified</span>
                        Bons signés
                        <span class="tab-badge" id="signed-badge"><?= $stats['signed_count'] ?? 0 ?></span>
                    </div>
                    <div class="tab" data-tab="rejected">
                        <span class="material-icons align-middle mr-2">block</span>
                        Bons rejetés
                        <span class="tab-badge" id="rejected-badge"><?= $stats['rejected_count'] ?? 0 ?></span>
                    </div>
                </div>

                <!-- Contenu de l'onglet: Bons en attente -->
                <div id="pending-tab" class="tab-content active p-4">
                    <!-- État de chargement -->
                    <div id="pending-loading" class="loading-state">
                        <div class="loading-spinner"></div>
                        <p class="loading-text">Chargement des bons en attente...</p>
                    </div>

                    <!-- Container du tableau -->
                    <div id="pending-table-container" class="hidden">
                        <div class="table-header mb-4">
                            <h3 class="table-title">Bons de commande en attente de signature Finance</h3>
                            <p class="table-subtitle">Cliquez sur "Signer" pour valider un bon de commande</p>
                        </div>

                        <div class="table-responsive">
                            <table id="pending-table" class="finance-table">
                                <thead>
                                    <tr>
                                        <th>N° Bon</th>
                                        <th>Date de création</th>
                                        <th>Fournisseur</th>
                                        <th>Montant</th>
                                        <th>Créé par</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Données chargées dynamiquement -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- État vide -->
                    <div id="pending-no-data" class="empty-state hidden">
                        <div class="empty-icon">
                            <span class="material-icons">inbox</span>
                        </div>
                        <h3 class="empty-title">Aucun bon en attente</h3>
                        <p class="empty-message">Tous les bons de commande ont été traités ou aucun nouveau bon n'est disponible.</p>
                        <button class="empty-action" onclick="window.financeManager?.refreshCurrentTab()">
                            <span class="material-icons mr-2">refresh</span>
                            Actualiser
                        </button>
                    </div>
                </div>

                <!-- Contenu de l'onglet: Bons signés -->
                <div id="signed-tab" class="tab-content p-4">
                    <!-- État de chargement -->
                    <div id="signed-loading" class="loading-state">
                        <div class="loading-spinner"></div>
                        <p class="loading-text">Chargement des bons signés...</p>
                    </div>

                    <!-- Container du tableau -->
                    <div id="signed-table-container" class="hidden">
                        <div class="table-header mb-4">
                            <h3 class="table-title">Bons de commande signés par Finance</h3>
                            <p class="table-subtitle">Historique de vos signatures et validations</p>
                        </div>

                        <div class="table-responsive">
                            <table id="signed-table" class="finance-table">
                                <thead>
                                    <tr>
                                        <th>N° Bon</th>
                                        <th>Date de création</th>
                                        <th>Fournisseur</th>
                                        <th>Montant</th>
                                        <th>Date de signature</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Données chargées dynamiquement -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- État vide -->
                    <div id="signed-no-data" class="empty-state hidden">
                        <div class="empty-icon">
                            <span class="material-icons">history</span>
                        </div>
                        <h3 class="empty-title">Aucun bon signé</h3>
                        <p class="empty-message">Vous n'avez encore signé aucun bon de commande.</p>
                    </div>
                </div>

                <!-- Contenu de l'onglet: Bons rejetés -->
                <div id="rejected-tab" class="tab-content p-4">
                    <!-- État de chargement -->
                    <div id="rejected-loading" class="loading-state">
                        <div class="loading-spinner"></div>
                        <p class="loading-text">Chargement des bons rejetés...</p>
                    </div>

                    <!-- Container du tableau -->
                    <div id="rejected-table-container" class="hidden">
                        <div class="table-header mb-4">
                            <h3 class="table-title">Bons de commande rejetés par Finance</h3>
                            <p class="table-subtitle">Historique de vos rejets avec motifs</p>
                        </div>

                        <div class="table-responsive">
                            <table id="rejected-table" class="finance-table">
                                <thead>
                                    <tr>
                                        <th>N° Bon</th>
                                        <th>Date de création</th>
                                        <th>Fournisseur</th>
                                        <th>Montant</th>
                                        <th>Date de rejet</th>
                                        <th>Motif</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Données chargées dynamiquement -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- État vide -->
                    <div id="rejected-no-data" class="empty-state hidden">
                        <div class="empty-icon">
                            <span class="material-icons">block</span>
                        </div>
                        <h3 class="empty-title">Aucun bon rejeté</h3>
                        <p class="empty-message">Vous n'avez encore rejeté aucun bon de commande.</p>
                    </div>
                </div>
            </div>

            <!-- Section d'informations système -->
            <div class="system-info card-container p-4">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <span class="material-icons mr-2">info</span>
                    Informations système
                </h3>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Version:</span>
                        <span class="info-value"><?= $pageConfig['version'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Utilisateur:</span>
                        <span class="info-value"><?= htmlspecialchars($pageConfig['user_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Temps de chargement:</span>
                        <span class="info-value"><?= round($pageConfig['execution_time'] * 1000) ?>ms</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Dernière mise à jour:</span>
                        <span class="info-value" id="last-update">Just Now</span>
                    </div>
                </div>
            </div>
        </main>

        <?php include_once '../components/footer.html'; ?>
    </div>

    <!-- Scripts JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Classes JavaScript Finance -->
    <script src="assets/js/NotificationManager.js"></script>
    <script src="assets/js/ModalManager.js"></script>
    <script src="assets/js/DataTableManager.js"></script>
    <script src="assets/js/StatsManager.js"></script>
    <script src="assets/js/FinanceManager.js"></script>

    <!-- Script d'initialisation -->
    <script>
        /**
         * Initialisation de l'application Finance Dashboard v2.0
         */
        $(document).ready(function() {
            console.log('🚀 Initialisation Finance Dashboard v2.0');

            // Configuration globale
            window.FINANCE_CONFIG = {
                version: '<?= $pageConfig['version'] ?>',
                userId: <?= $pageConfig['user_id'] ?>,
                userName: '<?= htmlspecialchars($pageConfig['user_name']) ?>',
                debugMode: <?= json_encode(defined('DEBUG_MODE') && DEBUG_MODE) ?>
            };

            try {
                // NOUVEAU : Initialiser le gestionnaire de statistiques d'abord
                window.Stats = new StatsManager();

                // Initialiser le gestionnaire principal
                window.financeManager = new FinanceManager();

                // Initialiser le gestionnaire de notifications globales
                window.notificationManager = new NotificationManager({
                    position: 'top-right',
                    autoClose: true,
                    duration: 5000,
                    useSweetAlert: true
                });

                // Événements personnalisés pour la communication inter-composants
                $(document).on('financeManager:signOrder', function(e, orderId, orderNumber) {
                    window.financeManager.signOrder(orderId, orderNumber);
                });

                $(document).on('financeManager:viewDetails', function(e, orderId) {
                    window.financeManager.viewOrderDetails(orderId);
                });

                $(document).on('financeManager:viewSigned', function(e, bonId) {
                    window.financeManager.components.modal.showViewSignedModal(bonId);
                });

                // Gestion des boutons d'action - VERSION AMÉLIORÉE
                $('#refresh-data').on('click', async function() {
                    $(this).prop('disabled', true).html('<span class="loading-spinner mr-2"></span>Actualisation...');

                    try {
                        await window.financeManager.refreshAllTabs();
                        // CORRECTION : Recharger aussi les statistiques
                        if (window.Stats) {
                            await window.Stats.load();
                        }
                        window.notificationManager.success('Toutes les données ont été actualisées');
                    } catch (error) {
                        window.notificationManager.error('Erreur lors de l\'actualisation');
                    } finally {
                        $(this).prop('disabled', false).html('<span class="material-icons mr-2">refresh</span>Actualiser');
                    }
                });

                // Raccourcis clavier
                $(document).on('keydown', function(e) {
                    // Ctrl/Cmd + R pour actualiser
                    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                        e.preventDefault();
                        window.financeManager.refreshCurrentTab();
                    }

                    // Ctrl/Cmd + 1/2 pour changer d'onglet
                    if ((e.ctrlKey || e.metaKey) && (e.key === '1' || e.key === '2')) {
                        e.preventDefault();
                        const tab = e.key === '1' ? 'pending' : 'signed';
                        window.financeManager.switchTab(tab);
                    }
                });

                // Gestion responsive
                $(window).on('resize', function() {
                    if (window.financeManager.components.dataTable) {
                        window.financeManager.components.dataTable.recalculateResponsive();
                    }
                });

                // Notification de bienvenue
                setTimeout(() => {
                    window.notificationManager.success(
                        `Bienvenue ${window.FINANCE_CONFIG.userName} - Système Finance v${window.FINANCE_CONFIG.version}`, {
                            duration: 3000
                        }
                    );
                }, 1000);

                console.log('✅ Finance Dashboard initialisé avec succès');

            } catch (error) {
                console.error('❌ Erreur lors de l\'initialisation:', error);

                // Fallback en cas d'erreur
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur d\'initialisation',
                        text: 'Une erreur est survenue lors du chargement de l\'application. Veuillez recharger la page.',
                        confirmButtonText: 'Recharger',
                        allowOutsideClick: false
                    }).then(() => {
                        location.reload();
                    });
                }
            }
        });

        // Nettoyage avant déchargement de la page
        $(window).on('beforeunload', function() {
            if (window.financeManager) {
                window.financeManager.destroy();
            }
            if (window.notificationManager) {
                window.notificationManager.destroy();
            }
        });
    </script>

    <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
        <!-- Scripts de debug en mode développement -->
        <script>
            console.log('🔧 Mode DEBUG activé');
            console.log('📊 Stats Finance:', <?= json_encode($stats) ?>);
            console.log('⚙️ Config Page:', <?= json_encode($pageConfig) ?>);

            // Ajouter des outils de debug
            window.DEBUG_TOOLS = {
                financeManager: () => window.financeManager,
                notificationManager: () => window.notificationManager,
                stats: <?= json_encode($stats) ?>,
                config: <?= json_encode($pageConfig) ?>
            };
        </script>
    <?php endif; ?>
</body>

</html>