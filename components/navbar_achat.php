<?php
include dirname(__DIR__) . '/database/connection.php';

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT name, profile_image FROM users_exp WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $user = null;
}

// Déterminer la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);

require_once 'config.php';
$base_url = PROJECT_ROOT;

// ========== SYSTÈME DE NOTIFICATIONS ==========
include_once dirname(__DIR__) . '/include/date_helper.php';

// Configuration système
$systemConfig = require dirname(__DIR__) . '/config/system_config.php';
$systemStartDate = $systemConfig['system_start_date'];

// Initialisation des notifications
$notifications = [
    'materials' => [
        'urgent' => [],
        'recent' => [],
        'pending' => [],
        'remaining' => [],
        'retours' => [],
        'canceled' => []
    ],
    'counts' => [
        'urgent' => 0,
        'recent' => 0,
        'pending' => 0,
        'remaining' => 0,
        'retours_attente' => 0,
        'recent_canceled' => 0,
        'total' => 0
    ],
    'stats' => [
        'volume_restant' => 0
    ]
];

try {
    // 1. Matériaux urgents
    $urgentQuery = "SELECT ed.id, ed.designation, ed.qt_acheter, ed.unit, ed.created_at,
                         ip.code_projet, ip.nom_client, 
                         DATEDIFF(NOW(), ed.created_at) as days_pending
                  FROM expression_dym ed
                  JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                  WHERE ed.qt_acheter IS NOT NULL 
                  AND ed.qt_acheter > 0 
                  AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
                  AND ed.created_at >= :system_start_date
                  AND (ed.qt_acheter > 100 OR DATEDIFF(NOW(), ed.created_at) > 7)
                  ORDER BY DATEDIFF(NOW(), ed.created_at) DESC, ed.qt_acheter DESC
                  LIMIT 3";

    $urgentStmt = $pdo->prepare($urgentQuery);
    $urgentStmt->bindParam(':system_start_date', $systemStartDate);
    $urgentStmt->execute();
    $notifications['materials']['urgent'] = $urgentStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications['counts']['urgent'] = count($notifications['materials']['urgent']);

    // 2. Matériaux récents (24h)
    $recentQuery = "SELECT ed.id, ed.designation, ed.qt_acheter, ed.unit, ed.created_at,
                       ip.code_projet, ip.nom_client
                FROM expression_dym ed
                JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                WHERE ed.qt_acheter IS NOT NULL 
                AND ed.qt_acheter > 0 
                AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
                AND ed.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND ed.id NOT IN (SELECT id FROM expression_dym 
                                 WHERE qt_acheter > 100 OR DATEDIFF(NOW(), created_at) > 7)
                ORDER BY ed.created_at DESC LIMIT 5";

    $recentStmt = $pdo->prepare($recentQuery);
    $recentStmt->execute();
    $notifications['materials']['recent'] = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications['counts']['recent'] = count($notifications['materials']['recent']);

    // 3. Matériaux en attente
    $pendingQuery = "SELECT ed.id, ed.designation, ed.qt_acheter, ed.unit, ed.created_at,
                        ip.code_projet, ip.nom_client
                 FROM expression_dym ed
                 JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                 WHERE ed.qt_acheter IS NOT NULL 
                 AND ed.qt_acheter > 0 
                 AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
                 AND ed.created_at >= :system_start_date
                 AND ed.id NOT IN (SELECT id FROM expression_dym 
                                  WHERE (qt_acheter > 100 OR DATEDIFF(NOW(), created_at) > 7)
                                  OR created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
                 ORDER BY ed.created_at DESC LIMIT 5";

    $pendingStmt = $pdo->prepare($pendingQuery);
    $pendingStmt->bindParam(':system_start_date', $systemStartDate);
    $pendingStmt->execute();
    $notifications['materials']['pending'] = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications['counts']['pending'] = count($notifications['materials']['pending']);

    // 4. Matériaux avec quantités restantes
    $remainingQuery = "SELECT ed.id, ed.designation, ed.qt_acheter, ed.qt_restante, ed.unit, ed.created_at,
                              ip.code_projet, ip.nom_client,
                              (SELECT SUM(am.quantity) FROM achats_materiaux am 
                               WHERE am.designation = ed.designation 
                               AND am.expression_id = ed.idExpression 
                               AND am.is_partial = 1) as quantite_commandee
                       FROM expression_dym ed
                       JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                       WHERE ed.qt_restante > 0 AND ed.valide_achat = 'en_cours'
                       ORDER BY ed.created_at DESC LIMIT 5";

    $remainingStmt = $pdo->prepare($remainingQuery);
    $remainingStmt->execute();
    $notifications['materials']['remaining'] = $remainingStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications['counts']['remaining'] = count($notifications['materials']['remaining']);

    // 5. Total général
    $totalQuery = "SELECT COUNT(*) as total FROM expression_dym
                 WHERE qt_acheter IS NOT NULL AND qt_acheter > 0 
                 AND (valide_achat = 'pas validé' OR valide_achat IS NULL)
                 AND created_at >= :system_start_date";

    $totalStmt = $pdo->prepare($totalQuery);
    $totalStmt->bindParam(':system_start_date', $systemStartDate);
    $totalStmt->execute();
    $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $notifications['counts']['total'] = $totalResult['total'] + $notifications['counts']['remaining'];

    // 6. Retours fournisseurs
    $retoursFournisseursQuery = "SELECT COUNT(*) as total_retours,
                                      COUNT(CASE WHEN sr.status = 'pending' THEN 1 END) as retours_attente
                               FROM supplier_returns sr
                               WHERE " . getFilteredDateCondition('sr.created_at');

    $retoursFournisseursStmt = $pdo->prepare($retoursFournisseursQuery);
    $retoursFournisseursStmt->execute();
    $retoursFournisseursResult = $retoursFournisseursStmt->fetch(PDO::FETCH_ASSOC);
    $notifications['counts']['retours_attente'] = $retoursFournisseursResult['retours_attente'] ?? 0;

    // 7. Commandes annulées récentes
    $canceledOrdersQuery = "SELECT COUNT(CASE WHEN DATEDIFF(NOW(), canceled_at) < 7 THEN 1 END) as recent_canceled
                          FROM canceled_orders_log co
                          WHERE " . getFilteredDateCondition('co.canceled_at');

    $canceledOrdersStmt = $pdo->prepare($canceledOrdersQuery);
    $canceledOrdersStmt->execute();
    $canceledOrdersResult = $canceledOrdersStmt->fetch(PDO::FETCH_ASSOC);
    $notifications['counts']['recent_canceled'] = $canceledOrdersResult['recent_canceled'] ?? 0;

    // Mise à jour du total
    $notifications['counts']['total'] += $notifications['counts']['retours_attente'] + $notifications['counts']['recent_canceled'];
} catch (Exception $e) {
    // Réinitialiser en cas d'erreur
    $notifications['counts']['total'] = 0;
    if (isset($_GET['debug_notifications'])) {
        error_log('Erreur notifications: ' . $e->getMessage());
    }
}

// Fonction pour afficher le temps écoulé
function timeAgo($date)
{
    $timestamp = strtotime($date);
    $strTime = array("seconde", "minute", "heure", "jour", "mois", "année");
    $length = array("60", "60", "24", "30", "12", "10");

    $currentTime = time();
    if ($currentTime >= $timestamp) {
        $diff = $currentTime - $timestamp;
        if ($diff < 60) return "à l'instant";

        for ($i = 0; $diff >= $length[$i] && $i < count($length) - 1; $i++) {
            $diff = $diff / $length[$i];
        }

        $diff = round($diff);
        $suffix = $diff > 1 ? "s" : "";
        return "il y a " . $diff . " " . $strTime[$i] . $suffix;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Achat - Navigation</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .bg-yellow-500 {
            background-color: #f59e0b !important;
        }

        .bg-yellow-50 {
            background-color: #fef9c3 !important;
        }

        /* Configuration Tailwind pour dark mode */
        .navbar-mobile-menu {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }

        .navbar-mobile-menu.open {
            transform: translateX(0);
        }

        /* Animation pour les notifications */
        .notification-badge {
            animation: pulse 2s infinite;
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

        /* Styles pour les onglets de notifications */
        .notification-tab-active {
            background-color: #f3f4f6;
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
        }

        /* Responsiveness améliorée */
        @media (max-width: 768px) {
            .navbar-links {
                display: none;
            }

            .navbar-mobile-toggle {
                display: block;
            }
        }

        @media (min-width: 769px) {
            .navbar-links {
                display: flex;
            }

            .navbar-mobile-toggle {
                display: none;
            }

            .navbar-mobile-menu {
                display: none;
            }
        }
    </style>
</head>

<body>

    <!-- NAVBAR PRINCIPALE -->
    <nav class="bg-gray-800 shadow-lg relative z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">

                <!-- LOGO & BURGER MENU -->
                <div class="flex items-center">
                    <!-- Burger Menu (Mobile) -->
                    <button id="mobile-menu-toggle" class="navbar-mobile-toggle text-gray-300 hover:text-white p-2 rounded-md lg:hidden">
                        <span class="material-icons">menu</span>
                    </button>

                    <!-- Logo -->
                    <div class="flex-shrink-0 ml-2 lg:ml-0">
                        <img class="h-8 w-auto" src="<?= $base_url ?>/public/logo.png" alt="DYM MANUFACTURE">
                    </div>
                </div>

                <!-- NAVIGATION DESKTOP -->
                <div class="navbar-links hidden lg:flex items-center space-x-1">
                    <a href="<?= $base_url ?>/User-Achat/dashboard.php"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo ($current_page == 'dashboard.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <span class="material-icons text-sm mr-2">dashboard</span>
                        <span class="hidden xl:inline">Dashboard</span>
                    </a>

                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo ($current_page == 'achats_materiaux.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <span class="material-icons text-sm mr-2">shopping_cart</span>
                        <span class="hidden xl:inline">Achats</span>
                    </a>

                    <a href="<?= $base_url ?>/fournisseurs/fournisseurs.php"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo ($current_page == 'fournisseurs.php' || $current_page == 'supplier_details.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <span class="material-icons text-sm mr-2">people_alt</span>
                        <span class="hidden xl:inline">Fournisseurs</span>
                    </a>

                    <a href="<?= $base_url ?>/User-Achat/payment-management/index.php"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo (strpos($_SERVER['REQUEST_URI'], '/payment-management/') !== false) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <span class="material-icons text-sm mr-2">payment</span>
                        <span class="hidden xl:inline">Paiements</span>
                    </a>

                    <a href="<?= $base_url ?>/User-Achat/views/besoins/index.php"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo (strpos($_SERVER['REQUEST_URI'], '/views/besoins/') !== false) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <span class="material-icons text-sm mr-2">assignment</span>
                        <span class="hidden xl:inline">Besoins</span>
                    </a>

                    <a href="<?= $base_url ?>/User-Achat/statistics/index.php"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo (strpos($_SERVER['REQUEST_URI'], '/statistics/') !== false) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <span class="material-icons text-sm mr-2">bar_chart</span>
                        <span class="hidden xl:inline">Stats</span>
                    </a>

                    <a href="<?= $base_url ?>/User-Achat/profile.php"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo ($current_page == 'profile.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                        <span class="material-icons text-sm mr-2">person</span>
                        <span class="hidden xl:inline">Profil</span>
                    </a>
                </div>

                <!-- ACTIONS DROITE -->
                <div class="flex items-center space-x-4">

                    <!-- NOTIFICATIONS -->
                    <div class="relative">
                        <button id="notifications-btn" class="relative p-2 text-gray-300 hover:text-white rounded-full hover:bg-gray-700 transition-colors duration-200">
                            <span class="material-icons">notifications</span>
                            <?php if ($notifications['counts']['total'] > 0): ?>
                                <span class="absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center notification-badge">
                                    <?php echo min($notifications['counts']['total'], 99); ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <!-- Dropdown Notifications -->
                        <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-lg shadow-xl border border-gray-200 overflow-hidden z-50">
                            <!-- Header -->
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-800">Notifications</h3>
                                    <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                        <?php echo $notifications['counts']['total']; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Onglets -->
                            <div class="flex border-b border-gray-200 bg-white">
                                <button class="notification-tab flex-1 py-2 px-1 text-xs font-medium text-center notification-tab-active" data-tab="urgent">
                                    Urgents
                                    <?php if ($notifications['counts']['urgent'] > 0): ?>
                                        <span class="ml-1 bg-red-500 text-white text-xs rounded-full px-1.5">
                                            <?php echo $notifications['counts']['urgent']; ?>
                                        </span>
                                    <?php endif; ?>
                                </button>
                                <button class="notification-tab flex-1 py-2 px-1 text-xs font-medium text-center" data-tab="recent">
                                    Récents
                                    <?php if ($notifications['counts']['recent'] > 0): ?>
                                        <span class="ml-1 bg-blue-500 text-white text-xs rounded-full px-1.5">
                                            <?php echo $notifications['counts']['recent']; ?>
                                        </span>
                                    <?php endif; ?>
                                </button>
                                <button class="notification-tab flex-1 py-2 px-1 text-xs font-medium text-center" data-tab="partials">
                                    Partiels
                                    <?php if ($notifications['counts']['remaining'] > 0): ?>
                                        <span class="ml-1 bg-yellow-500 text-white text-xs rounded-full px-1.5">
                                            <?php echo $notifications['counts']['remaining']; ?>
                                        </span>
                                    <?php endif; ?>
                                </button>
                                <button class="notification-tab flex-1 py-2 px-1 text-xs font-medium text-center" data-tab="others">
                                    Autres
                                    <?php
                                    $otherCount = $notifications['counts']['pending'] + $notifications['counts']['retours_attente'] + $notifications['counts']['recent_canceled'];
                                    if ($otherCount > 0): ?>
                                        <span class="ml-1 bg-gray-500 text-white text-xs rounded-full px-1.5">
                                            <?php echo $otherCount; ?>
                                        </span>
                                    <?php endif; ?>
                                </button>
                            </div>

                            <!-- Contenu des onglets -->
                            <div class="max-h-64 overflow-y-auto">
                                <!-- Onglet Urgents -->
                                <div id="tab-urgent" class="notification-content">
                                    <?php if (!empty($notifications['materials']['urgent'])): ?>
                                        <?php foreach ($notifications['materials']['urgent'] as $material): ?>
                                            <div class="p-3 border-l-4 border-red-500 hover:bg-red-50 transition-colors duration-200">
                                                <div class="flex justify-between items-start">
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            <?php echo htmlspecialchars($material['designation']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-600 mt-1">
                                                            <?php echo htmlspecialchars($material['code_projet']); ?> -
                                                            <?php echo htmlspecialchars($material['nom_client']); ?>
                                                        </p>
                                                        <div class="flex items-center mt-1 space-x-2">
                                                            <span class="text-xs bg-gray-100 px-2 py-1 rounded">
                                                                <?php echo htmlspecialchars($material['qt_acheter']); ?> <?php echo htmlspecialchars($material['unit']); ?>
                                                            </span>
                                                            <span class="text-xs text-red-600 font-medium">
                                                                <?php
                                                                if ($material['days_pending'] > 7) {
                                                                    echo $material['days_pending'] . ' jours!';
                                                                } elseif ($material['qt_acheter'] > 100) {
                                                                    echo 'Grande quantité!';
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                                                        class="ml-2 text-red-600 hover:text-red-800 text-xs font-medium">
                                                        Commander
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="p-6 text-center text-gray-500">
                                            <span class="material-icons text-4xl mb-2 opacity-50">check_circle</span>
                                            <p>Aucun matériau urgent</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Autres onglets (récents, partiels, autres) -->
                                <div id="tab-recent" class="notification-content hidden">
                                    <?php if (!empty($notifications['materials']['recent'])): ?>
                                        <?php foreach ($notifications['materials']['recent'] as $material): ?>
                                            <div class="p-3 border-l-4 border-blue-500 hover:bg-blue-50 transition-colors duration-200">
                                                <div class="flex justify-between items-start">
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            <?php echo htmlspecialchars($material['designation']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-600 mt-1">
                                                            <?php echo htmlspecialchars($material['code_projet']); ?> -
                                                            <?php echo htmlspecialchars($material['nom_client']); ?>
                                                        </p>
                                                        <div class="flex items-center mt-1 space-x-2">
                                                            <span class="text-xs bg-gray-100 px-2 py-1 rounded">
                                                                <?php echo htmlspecialchars($material['qt_acheter']); ?> <?php echo htmlspecialchars($material['unit']); ?>
                                                            </span>
                                                            <span class="text-xs text-blue-600">
                                                                <?php echo timeAgo($material['created_at']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                                                        class="ml-2 text-blue-600 hover:text-blue-800 text-xs font-medium">
                                                        Commander
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="p-6 text-center text-gray-500">
                                            <span class="material-icons text-4xl mb-2 opacity-50">schedule</span>
                                            <p>Aucun nouveau matériau</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Onglet Partiels -->
                                <div id="tab-partials" class="notification-content hidden">
                                    <?php if (!empty($notifications['materials']['remaining'])): ?>
                                        <?php foreach ($notifications['materials']['remaining'] as $material): ?>
                                            <div class="p-3 border-l-4 border-yellow-500 hover:bg-yellow-50 transition-colors duration-200">
                                                <div class="flex justify-between items-start">
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            <?php echo htmlspecialchars($material['designation']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-600 mt-1">
                                                            <?php echo htmlspecialchars($material['code_projet']); ?> -
                                                            <?php echo htmlspecialchars($material['nom_client']); ?>
                                                        </p>
                                                        <div class="mt-1">
                                                            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">
                                                                Restant: <?php echo htmlspecialchars($material['qt_restante']); ?> <?php echo htmlspecialchars($material['unit']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php?tab=partial"
                                                        class="ml-2 text-yellow-600 hover:text-yellow-800 text-xs font-medium">
                                                        Commander
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="p-6 text-center text-gray-500">
                                            <span class="material-icons text-4xl mb-2 opacity-50">partial_fulfill</span>
                                            <p>Aucune commande partielle</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Onglet Autres -->
                                <div id="tab-others" class="notification-content hidden">
                                    <!-- Matériaux en attente -->
                                    <?php if (!empty($notifications['materials']['pending'])): ?>
                                        <div class="bg-gray-100 px-3 py-2 text-xs font-medium text-gray-700">Matériaux en attente</div>
                                        <?php foreach (array_slice($notifications['materials']['pending'], 0, 3) as $material): ?>
                                            <div class="p-3 hover:bg-gray-50 transition-colors duration-200">
                                                <div class="flex justify-between items-start">
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            <?php echo htmlspecialchars($material['designation']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-600 mt-1">
                                                            <?php echo htmlspecialchars($material['code_projet']); ?>
                                                        </p>
                                                    </div>
                                                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                                                        class="ml-2 text-gray-600 hover:text-gray-800 text-xs font-medium">
                                                        Voir
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <?php if (empty($notifications['materials']['pending'])): ?>
                                        <div class="p-6 text-center text-gray-500">
                                            <span class="material-icons text-4xl mb-2 opacity-50">inbox</span>
                                            <p>Aucune autre notification</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                                <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                                    class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center py-2 px-4 rounded-md text-sm font-medium transition-colors duration-200">
                                    Voir tous les matériaux
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- PROFIL -->
                    <div class="relative">
                        <button id="profile-btn" class="flex items-center space-x-2 p-1 rounded-full hover:bg-gray-700 transition-colors duration-200">
                            <?php if ($user && $user['profile_image']): ?>
                                <img class="h-8 w-8 rounded-full object-cover border-2 border-gray-600"
                                    src="<?= $base_url ?>/uploads/<?php echo htmlspecialchars($user['profile_image']); ?>"
                                    alt="Profile">
                            <?php else: ?>
                                <img class="h-8 w-8 rounded-full object-cover border-2 border-gray-600"
                                    src="<?= $base_url ?>/uploads/default-profile-image.png"
                                    alt="Profile">
                            <?php endif; ?>
                            <span class="hidden sm:block text-gray-300 text-sm">
                                <?php echo htmlspecialchars($user['name'] ?? 'Utilisateur'); ?>
                            </span>
                            <span class="material-icons text-gray-300 text-sm">expand_more</span>
                        </button>

                        <!-- Dropdown Profil -->
                        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 overflow-hidden z-50">
                            <div class="py-1">
                                <a href="<?= $base_url ?>/User-Achat/profile.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                                    <span class="material-icons text-sm mr-2 align-middle">person</span>
                                    Mon Profil
                                </a>
                                <div class="border-t border-gray-100"></div>
                                <a href="<?= $base_url ?>/logout.php"
                                    class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors duration-200">
                                    <span class="material-icons text-sm mr-2 align-middle">logout</span>
                                    Déconnexion
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- MENU MOBILE -->
    <div id="mobile-menu" class="navbar-mobile-menu fixed inset-y-0 left-0 w-64 bg-gray-900 shadow-lg z-40 lg:hidden">
        <div class="flex flex-col h-full">
            <!-- Header Mobile -->
            <div class="flex items-center justify-between p-4 border-b border-gray-700">
                <img class="h-8 w-auto" src="<?= $base_url ?>/public/logo.png" alt="DYM MANUFACTURE">
                <button id="mobile-menu-close" class="text-gray-300 hover:text-white">
                    <span class="material-icons">close</span>
                </button>
            </div>

            <!-- Navigation Mobile -->
            <div class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <a href="<?= $base_url ?>/User-Achat/dashboard.php"
                    class="flex items-center px-3 py-2 rounded-md text-base font-medium transition-colors duration-200 <?php echo ($current_page == 'dashboard.php') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <span class="material-icons text-lg mr-3">dashboard</span>
                    Dashboard
                </a>

                <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                    class="flex items-center px-3 py-2 rounded-md text-base font-medium transition-colors duration-200 <?php echo ($current_page == 'achats_materiaux.php') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <span class="material-icons text-lg mr-3">shopping_cart</span>
                    Achats Matériaux
                    <?php if ($notifications['counts']['total'] > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            <?php echo $notifications['counts']['total']; ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="<?= $base_url ?>/fournisseurs/fournisseurs.php"
                    class="flex items-center px-3 py-2 rounded-md text-base font-medium transition-colors duration-200 <?php echo ($current_page == 'fournisseurs.php' || $current_page == 'supplier_details.php') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <span class="material-icons text-lg mr-3">people_alt</span>
                    Fournisseurs
                </a>

                <a href="<?= $base_url ?>/User-Achat/payment-management/index.php"
                    class="flex items-center px-3 py-2 rounded-md text-base font-medium transition-colors duration-200 <?php echo (strpos($_SERVER['REQUEST_URI'], '/payment-management/') !== false) ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <span class="material-icons text-lg mr-3">payment</span>
                    Modes de Paiement
                </a>

                <a href="<?= $base_url ?>/User-Achat/views/besoins/index.php"
                    class="flex items-center px-3 py-2 rounded-md text-base font-medium transition-colors duration-200 <?php echo (strpos($_SERVER['REQUEST_URI'], '/views/besoins/') !== false) ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <span class="material-icons text-lg mr-3">assignment</span>
                    Expression de Besoins
                </a>

                <a href="<?= $base_url ?>/User-Achat/statistics/index.php"
                    class="flex items-center px-3 py-2 rounded-md text-base font-medium transition-colors duration-200 <?php echo (strpos($_SERVER['REQUEST_URI'], '/statistics/') !== false) ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <span class="material-icons text-lg mr-3">bar_chart</span>
                    Statistiques
                </a>

                <a href="<?= $base_url ?>/User-Achat/profile.php"
                    class="flex items-center px-3 py-2 rounded-md text-base font-medium transition-colors duration-200 <?php echo ($current_page == 'profile.php') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <span class="material-icons text-lg mr-3">person</span>
                    Profil
                </a>
            </div>

            <!-- Profile Mobile -->
            <div class="border-t border-gray-700 p-4">
                <div class="flex items-center space-x-3 mb-3">
                    <?php if ($user && $user['profile_image']): ?>
                        <img class="h-10 w-10 rounded-full object-cover"
                            src="<?= $base_url ?>/uploads/<?php echo htmlspecialchars($user['profile_image']); ?>"
                            alt="Profile">
                    <?php else: ?>
                        <img class="h-10 w-10 rounded-full object-cover"
                            src="<?= $base_url ?>/uploads/default-profile-image.png"
                            alt="Profile">
                    <?php endif; ?>
                    <div class="flex-1">
                        <p class="text-white text-sm font-medium">
                            <?php echo htmlspecialchars($user['name'] ?? 'Utilisateur'); ?>
                        </p>
                        <p class="text-gray-400 text-xs">Service Achat</p>
                    </div>
                </div>

                <!-- Résumé notifications mobile -->
                <?php if ($notifications['counts']['total'] > 0): ?>
                    <div class="mb-3 p-3 bg-gray-800 rounded-lg">
                        <div class="text-white text-sm font-medium mb-2">Notifications actives</div>
                        <div class="space-y-1">
                            <?php if ($notifications['counts']['urgent'] > 0): ?>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-red-300">Urgents</span>
                                    <span class="bg-red-500 text-white px-2 py-1 rounded-full">
                                        <?php echo $notifications['counts']['urgent']; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if ($notifications['counts']['recent'] > 0): ?>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-blue-300">Récents</span>
                                    <span class="bg-blue-500 text-white px-2 py-1 rounded-full">
                                        <?php echo $notifications['counts']['recent']; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <a href="<?= $base_url ?>/logout.php"
                    class="flex items-center justify-center w-full px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-medium transition-colors duration-200">
                    <span class="material-icons text-sm mr-2">logout</span>
                    Déconnexion
                </a>
            </div>
        </div>
    </div>

    <!-- Overlay pour mobile -->
    <div id="mobile-overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden"></div>

    <!-- SCRIPTS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Éléments principaux
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const mobileMenuClose = document.getElementById('mobile-menu-close');
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileOverlay = document.getElementById('mobile-overlay');
            const notificationsBtn = document.getElementById('notifications-btn');
            const notificationsDropdown = document.getElementById('notifications-dropdown');
            const profileBtn = document.getElementById('profile-btn');
            const profileDropdown = document.getElementById('profile-dropdown');

            // === MENU MOBILE ===
            function openMobileMenu() {
                mobileMenu.classList.add('open');
                mobileOverlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeMobileMenu() {
                mobileMenu.classList.remove('open');
                mobileOverlay.classList.add('hidden');
                document.body.style.overflow = '';
            }

            function toggleMobileMenu() {
                if (mobileMenu.classList.contains('open')) {
                    closeMobileMenu();
                } else {
                    openMobileMenu();
                }
            }

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            }

            if (mobileMenuClose) {
                mobileMenuClose.addEventListener('click', closeMobileMenu);
            }

            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', closeMobileMenu);
            }

            // === NOTIFICATIONS ===
            if (notificationsBtn && notificationsDropdown) {
                notificationsBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationsDropdown.classList.toggle('hidden');

                    // Fermer autres dropdowns
                    if (profileDropdown) {
                        profileDropdown.classList.add('hidden');
                    }
                });

                // Empêcher la fermeture lors d'un clic dans le dropdown
                notificationsDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }

            // === PROFIL ===
            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('hidden');

                    // Fermer autres dropdowns
                    if (notificationsDropdown) {
                        notificationsDropdown.classList.add('hidden');
                    }
                });
            }

            // === ONGLETS NOTIFICATIONS ===
            const notificationTabs = document.querySelectorAll('.notification-tab');
            const notificationContents = document.querySelectorAll('.notification-content');

            notificationTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');

                    // Désactiver tous les onglets
                    notificationTabs.forEach(t => {
                        t.classList.remove('notification-tab-active');
                    });

                    // Masquer tous les contenus
                    notificationContents.forEach(content => {
                        content.classList.add('hidden');
                    });

                    // Activer l'onglet cliqué
                    this.classList.add('notification-tab-active');

                    // Afficher le contenu correspondant
                    const targetContent = document.getElementById(`tab-${targetTab}`);
                    if (targetContent) {
                        targetContent.classList.remove('hidden');
                    }
                });
            });

            // === FERMETURE GLOBALE ===
            document.addEventListener('click', function(event) {
                // Fermer tous les dropdowns si clic à l'extérieur
                if (notificationsDropdown && !notificationsBtn.contains(event.target) && !notificationsDropdown.contains(event.target)) {
                    notificationsDropdown.classList.add('hidden');
                }

                if (profileDropdown && !profileBtn.contains(event.target) && !profileDropdown.contains(event.target)) {
                    profileDropdown.classList.add('hidden');
                }
            });

            // === GESTION RESPONSIVE ===
            function handleResize() {
                if (window.innerWidth >= 1024) {
                    // Desktop : fermer le menu mobile
                    closeMobileMenu();
                }
            }

            window.addEventListener('resize', handleResize);

            // === KEYBOARD NAVIGATION ===
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    // Fermer tous les dropdowns et menus
                    if (notificationsDropdown) notificationsDropdown.classList.add('hidden');
                    if (profileDropdown) profileDropdown.classList.add('hidden');
                    closeMobileMenu();
                }
            });
        });
    </script>

</body>

</html>