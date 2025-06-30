<?php

/**
 * Page de gestion des modes de paiement - Service Achat
 * NOUVELLE VERSION : Support upload d'icônes personnalisées
 * 
 * @package DYM_MANUFACTURE
 * @subpackage User-Achat/payment-management
 * @author DYM Team
 * @version 3.2 - Upload d'icônes personnalisées
 * @date 29/06/2025
 */

session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Vérification des permissions
$allowedUserTypes = ['admin', 'achat', 'super_admin'];
if (!in_array($_SESSION['user_type'] ?? '', $allowedUserTypes)) {
    header("Location: ../achats_materiaux.php?error=access_denied");
    exit();
}

// Connexion à la base de données
include_once '../../database/connection.php';

// Variables utilisateur
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Utilisateur';
$user_type = $_SESSION['user_type'] ?? '';

// Gestion des messages
$message = '';
$messageType = '';

if (isset($_GET['success'])) {
    $message = "Opération effectuée avec succès !";
    $messageType = 'success';
} elseif (isset($_GET['error'])) {
    $message = "Une erreur s'est produite lors de l'opération.";
    $messageType = 'error';
} elseif (isset($_GET['duplicates_found'])) {
    $message = "Des doublons ont été détectés et traités !";
    $messageType = 'warning';
} elseif (isset($_GET['icon_uploaded'])) {
    $message = "Icône uploadée avec succès !";
    $messageType = 'success';
}

// Récupération des statistiques
try {
    $statsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM payment_methods) as total_modes,
            (SELECT COUNT(*) FROM payment_methods WHERE is_active = 1) as modes_actifs,
            (SELECT COUNT(*) FROM payment_methods WHERE is_active = 0) as modes_inactifs,
            (SELECT COUNT(*) FROM payment_methods WHERE icon_path IS NOT NULL AND icon_path != '') as modes_avec_icones,
            (SELECT MAX(updated_at) FROM payment_methods) as derniere_maj
    ";
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Vérification des doublons - UNIQUEMENT sur le libellé
    $duplicatesQuery = "
        SELECT label, COUNT(*) as nb_doublons
        FROM payment_methods 
        GROUP BY LOWER(TRIM(label))
        HAVING COUNT(*) > 1
    ";
    $duplicatesStmt = $pdo->prepare($duplicatesQuery);
    $duplicatesStmt->execute();
    $duplicates = $duplicatesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // NOUVEAU : Vérifier les icônes orphelines ou manquantes
    $iconIssuesQuery = "
        SELECT 
            COUNT(CASE WHEN icon_path IS NOT NULL AND icon_path != '' THEN 1 END) as icones_configurees,
            COUNT(CASE WHEN icon_path IS NOT NULL AND icon_path != '' THEN 1 END) as icones_potentiellement_manquantes
        FROM payment_methods
    ";
    $iconIssuesStmt = $pdo->prepare($iconIssuesQuery);
    $iconIssuesStmt->execute();
    $iconStats = $iconIssuesStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des statistiques : " . $e->getMessage());
    $stats = ['total_modes' => 0, 'modes_actifs' => 0, 'modes_inactifs' => 0, 'modes_avec_icones' => 0, 'derniere_maj' => null];
    $duplicates = [];
    $iconStats = ['icones_configurees' => 0];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Modes de Paiement - DYM MANUFACTURE</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/images/favicon.ico">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.20/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- CSS personnalisé -->
    <link href="assets/css/payment-management.css" rel="stylesheet">

    <!-- NOUVEAU : Styles pour l'upload d'icônes -->
    <style>
        .icon-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
            cursor: pointer;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-upload-area:hover {
            border-color: #007bff;
            background: #e3f2fd;
        }

        .icon-upload-area.dragover {
            border-color: #007bff;
            background: #e3f2fd;
            transform: scale(1.02);
            box-shadow: 0 4px 8px rgba(0,123,255,0.2);
        }

        .upload-content {
            width: 100%;
        }

        .icon-preview-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .icon-preview-image {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 8px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .current-icon-display {
            width: 80px;
            height: 80px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            margin: 0 auto 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .current-icon-display img {
            max-width: 64px;
            max-height: 64px;
            object-fit: contain;
        }

        .icon-resources-link {
            color: #007bff;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }

        .icon-resources-link:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        .upload-progress {
            margin-top: 10px;
        }

        .icon-info-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .stat-card-icons {
            background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
            color: white;
        }

        .stat-card-icons .stat-icon {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <?php include_once '../../components/navbar_achat.php'; ?>

    <!-- Container principal -->
    <div class="container-fluid mt-4">
        <!-- En-tête -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="header-section">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="page-title">
                                <i class="fas fa-credit-card me-2"></i>
                                Gestion des Modes de Paiement
                            </h1>
                            <p class="page-subtitle">Administration et configuration des méthodes de paiement (Version 3.2 - Upload d'icônes)</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="../achats_materiaux.php"
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                <span class="material-icons align-middle mr-1">arrow_back</span>
                                Retour aux achats
                            </a>
                            <button type="button" class="btn btn-primary" id="btnAddPayment">
                                <i class="fas fa-plus me-1"></i>
                                Nouveau Mode
                            </button>
                            <button type="button" class="btn btn-success" id="btnExport">
                                <i class="fas fa-download me-1"></i>
                                Exporter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages de notification -->
        <?php if (!empty($message)): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'error' ? 'danger' : 'warning'); ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alertes pour les doublons -->
        <?php if (!empty($duplicates)): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Attention !</strong> Des doublons potentiels ont été détectés :
                        <ul class="mb-0 mt-2">
                            <?php foreach ($duplicates as $duplicate): ?>
                                <li>Libellé: "<?php echo htmlspecialchars($duplicate['label']); ?>" (<?php echo $duplicate['nb_doublons']; ?> occurrences)</li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_modes']; ?></h3>
                        <p>Total des modes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['modes_actifs']; ?></h3>
                        <p>Modes actifs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['modes_inactifs']; ?></h3>
                        <p>Modes inactifs</p>
                    </div>
                </div>
            </div>
            <!-- NOUVEAU : Statistique des icônes -->
            <div class="col-md-3">
                <div class="stat-card stat-card-icons">
                    <div class="stat-icon">
                        <i class="fas fa-images"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['modes_avec_icones']; ?></h3>
                        <p>Avec icônes</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- NOUVEAU : Informations sur les icônes -->
        <?php if ($stats['modes_avec_icones'] > 0): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="icon-info-section">
                    <h6 class="mb-2">
                        <i class="fas fa-info-circle me-2"></i>
                        Informations sur les icônes personnalisées
                    </h6>
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted">
                                <strong><?php echo $stats['modes_avec_icones']; ?></strong> mode(s) utilisent des icônes personnalisées
                            </small>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">
                                Pourcentage: <strong><?php echo $stats['total_modes'] > 0 ? round(($stats['modes_avec_icones'] / $stats['total_modes']) * 100, 1) : 0; ?>%</strong>
                            </small>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">
                                <i class="fas fa-folder me-1"></i>
                                Stockage: <code>/uploads/payment_icons/</code>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filtres et actions -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="filters-section">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <select class="form-select" id="filterStatus">
                                <option value="all">Tous les statuts</option>
                                <option value="1">Actifs uniquement</option>
                                <option value="0">Inactifs uniquement</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="searchPayments" placeholder="Rechercher...">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-info" id="btnCheckDuplicates">
                        <i class="fas fa-search-plus me-1"></i>
                        Vérifier doublons
                    </button>
                    <button type="button" class="btn btn-outline-warning" id="btnRefresh">
                        <i class="fas fa-sync-alt me-1"></i>
                        Actualiser
                    </button>
                </div>
            </div>
        </div>

        <!-- Tableau des modes de paiement -->
        <div class="row">
            <div class="col-12">
                <div class="table-container">
                    <div class="table-responsive">
                        <table id="paymentsTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Icône</th>
                                    <th>Libellé</th>
                                    <th>Description</th>
                                    <th>Statut</th>
                                    <th>Ordre</th>
                                    <th>Utilisation</th>
                                    <th>Créé le</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Données chargées via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL D'AJOUT/MODIFICATION AVEC UPLOAD D'ICÔNES -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalTitle">
                        <i class="fas fa-plus me-2"></i>
                        Nouveau Mode de Paiement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm" enctype="multipart/form-data">
                        <input type="hidden" id="payment_id" name="payment_id">
                        <input type="hidden" id="current_icon_path" name="current_icon_path">

                        <!-- Libellé -->
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="payment_label" class="form-label">
                                        Libellé <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="payment_label" name="payment_label"
                                        maxlength="100" required placeholder="Ex: Carte de crédit, Espèces, Virement...">
                                    <div class="form-text">Nom d'affichage du mode de paiement (2-100 caractères)</div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="payment_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="payment_description" name="payment_description"
                                        rows="3" maxlength="500" placeholder="Description détaillée du mode de paiement (optionnel)"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- NOUVELLE SECTION : Upload d'icône -->
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">
                                        Icône personnalisée <span class="text-muted">(optionnel)</span>
                                    </label>
                                    
                                    <!-- Aperçu de l'icône actuelle -->
                                    <div id="currentIconPreview" class="current-icon-display" style="display: none;">
                                        <img id="currentIconImage" src="" alt="Icône actuelle">
                                    </div>
                                    
                                    <!-- Zone d'upload -->
                                    <div class="icon-upload-area" id="iconUploadArea">
                                        <input type="file" id="icon_file" name="icon_file" 
                                               accept=".png,.jpg,.jpeg,.svg,.webp" style="display: none;">
                                        <div class="upload-content">
                                            <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-muted"></i>
                                            <p class="mb-2"><strong>Cliquez pour sélectionner</strong> ou glissez-déposez votre icône</p>
                                            <p class="text-muted small mb-2">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Formats acceptés: PNG, JPG, SVG, WebP
                                            </p>
                                            <p class="text-muted small mb-0">
                                                <i class="fas fa-weight-hanging me-1"></i>
                                                Taille maximale: 2MB • Recommandé: 64x64px
                                            </p>
                                        </div>
                                        <div class="upload-progress" style="display: none;">
                                            <div class="progress mt-3">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                     role="progressbar" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Bouton de suppression d'icône -->
                                    <div class="mt-2 text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="removeIconBtn" style="display: none;">
                                            <i class="fas fa-times me-1"></i>
                                            Supprimer l'icône
                                        </button>
                                    </div>

                                    <!-- Liens vers les ressources d'icônes -->
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <h6 class="mb-2">
                                            <i class="fas fa-external-link-alt me-2"></i>
                                            Ressources d'icônes gratuites
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <a href="https://www.flaticon.com/search?word=payment" target="_blank" class="icon-resources-link">
                                                    <i class="fas fa-external-link-alt me-1"></i>
                                                    Flaticon - Paiement
                                                </a>
                                            </div>
                                            <div class="col-md-4">
                                                <a href="https://icons8.com/icons/set/payment" target="_blank" class="icon-resources-link">
                                                    <i class="fas fa-external-link-alt me-1"></i>
                                                    Icons8 - Paiement
                                                </a>
                                            </div>
                                            <div class="col-md-4">
                                                <a href="https://www.iconfinder.com/search/?q=payment&price=free" target="_blank" class="icon-resources-link">
                                                    <i class="fas fa-external-link-alt me-1"></i>
                                                    IconFinder - Gratuit
                                                </a>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-lightbulb me-1"></i>
                                                <strong>Conseil :</strong> Téléchargez des icônes au format PNG ou SVG pour une meilleure qualité.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="form-text">
                                        Uploadez votre propre icône ou utilisez les liens ci-dessus pour télécharger des icônes gratuites.
                                        L'icône sera redimensionnée automatiquement pour l'affichage.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ordre et statut -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_order" class="form-label">Ordre d'affichage</label>
                                    <input type="number" class="form-control" id="payment_order" name="payment_order"
                                        min="1" max="999" value="1">
                                    <div class="form-text">Position dans la liste (1-999)</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" id="payment_active"
                                            name="payment_active" checked>
                                        <label class="form-check-label" for="payment_active">
                                            Mode actif
                                        </label>
                                    </div>
                                    <div class="form-text">Les modes inactifs ne sont pas proposés lors des commandes</div>
                                </div>
                            </div>
                        </div>

                        <!-- Information sur la nouvelle version -->
                        <div class="alert alert-info mt-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-rocket fa-2x me-3 text-primary"></i>
                                <div>
                                    <h6 class="mb-1">
                                        <strong>Version 3.2 - Nouveautés !</strong>
                                    </h6>
                                    <p class="mb-0">
                                        Uploadez vos propres icônes personnalisées ! Fini les codes techniques complexes, 
                                        glissez-déposez simplement votre image et elle sera automatiquement optimisée.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="button" class="btn btn-primary" id="btnSavePayment">
                        <i class="fas fa-save me-1"></i>
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.20/dist/sweetalert2.all.min.js"></script>

    <!-- JavaScript personnalisé -->
    <script src="assets/js/payment-management.js"></script>
</body>

</html>