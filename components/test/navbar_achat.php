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

// Déterminez la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);

require_once 'config.php';
$base_url = PROJECT_ROOT;

// ========== Système de notifications amélioré =========
include_once dirname(__DIR__) . '/include/date_helper.php';

// Configuration système
$systemConfig = require dirname(__DIR__) . '/config/system_config.php';
$systemStartDate = $systemConfig['system_start_date'];

// Variables pour stocker les données de notification
$notifications = [
    'materials' => [
        'urgent' => [],
        'recent' => [],
        'pending' => [],
        'remaining' => [] // Nouvelle catégorie pour les quantités restantes
    ],
    'counts' => [
        'urgent' => 0,
        'recent' => 0,
        'pending' => 0,
        'remaining' => 0, // Compteur pour les quantités restantes
        'total' => 0
    ]
];

try {
    // 1. Matériaux urgents (prioritaires) - critères : qt_acheter élevée ou délai dépassé
    $urgentQuery = "SELECT ed.id, ed.designation, ed.qt_acheter, ed.unit, ed.created_at,
                         ip.code_projet, ip.nom_client, 
                         DATEDIFF(NOW(), ed.created_at) as days_pending
                  FROM expression_dym ed
                  JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                  WHERE ed.qt_acheter IS NOT NULL 
                  AND ed.qt_acheter > 0 
                  AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
                  AND ed.created_at >= :system_start_date
                  AND (
                      ed.qt_acheter > 100
                      OR DATEDIFF(NOW(), ed.created_at) > 7
                  )
                  ORDER BY DATEDIFF(NOW(), ed.created_at) DESC, ed.qt_acheter DESC
                  LIMIT 3";

    $urgentStmt = $pdo->prepare($urgentQuery);
    $urgentStmt->bindParam(':system_start_date', $systemStartDate);
    $urgentStmt->execute();
    $notifications['materials']['urgent'] = $urgentStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications['counts']['urgent'] = count($notifications['materials']['urgent']);

    // 2. Matériaux récents (ajoutés dans les dernières 24 heures)
    $recentQuery = "SELECT ed.id, ed.designation, ed.qt_acheter, ed.unit, ed.created_at,
                       ip.code_projet, ip.nom_client
                FROM expression_dym ed
                JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                WHERE ed.qt_acheter IS NOT NULL 
                AND ed.qt_acheter > 0 
                AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
                AND ed.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND ed.id NOT IN (SELECT id FROM expression_dym 
                                 WHERE qt_acheter > 100 
                                 OR DATEDIFF(NOW(), created_at) > 7)
                ORDER BY ed.created_at DESC
                LIMIT 5";

    $recentStmt = $pdo->prepare($recentQuery);
    $recentStmt->execute();
    $notifications['materials']['recent'] = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications['counts']['recent'] = count($notifications['materials']['recent']);

    // 3. Autres matériaux en attente
    $pendingQuery = "SELECT ed.id, ed.designation, ed.qt_acheter, ed.unit, ed.created_at,
                        ip.code_projet, ip.nom_client
                 FROM expression_dym ed
                 JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                 WHERE ed.qt_acheter IS NOT NULL 
                 AND ed.qt_acheter > 0 
                 AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
                 AND ed.created_at >= :system_start_date
                 AND ed.id NOT IN (SELECT id FROM expression_dym 
                                  WHERE (qt_acheter > 100 
                                  OR DATEDIFF(NOW(), created_at) > 7)
                                  OR created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
                 ORDER BY ed.created_at DESC
                 LIMIT 5";

    $pendingStmt = $pdo->prepare($pendingQuery);
    $pendingStmt->bindParam(':system_start_date', $systemStartDate);
    $pendingStmt->execute();
    $notifications['materials']['pending'] = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications['counts']['pending'] = count($notifications['materials']['pending']);

    // 4. Nombre total de matériaux à acheter
    $totalQuery = "SELECT COUNT(*) as total
                 FROM expression_dym
                 WHERE qt_acheter IS NOT NULL 
                 AND qt_acheter > 0 
                 AND (valide_achat = 'pas validé' OR valide_achat IS NULL)
                 AND created_at >= :system_start_date";

    $totalStmt = $pdo->prepare($totalQuery);
    $totalStmt->bindParam(':system_start_date', $systemStartDate);
    $totalStmt->execute();
    $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $notifications['counts']['total'] = $totalResult['total'];


    // 5. Nouvelle requête pour les matériaux avec des quantités restantes
    $remainingQuery = "SELECT 
    ed.id, 
    ed.designation, 
    ed.qt_acheter, 
    ed.qt_restante, 
    ed.unit, 
    ed.created_at,
    ip.code_projet, 
    ip.nom_client,
    (SELECT SUM(am.quantity) 
     FROM achats_materiaux am 
     WHERE am.designation = ed.designation 
     AND am.expression_id = ed.idExpression 
     AND am.is_partial = 1) as quantite_commandee
FROM expression_dym ed
JOIN identification_projet ip ON ed.idExpression = ip.idExpression
WHERE ed.qt_restante > 0 
AND ed.valide_achat = 'en_cours'
ORDER BY ed.created_at DESC
LIMIT 5";

    $remainingStmt = $pdo->prepare($remainingQuery);
    $remainingStmt->execute();
    $notifications['materials']['remaining'] = $remainingStmt->fetchAll(PDO::FETCH_ASSOC);

    // Intégrer ce compte dans le total
    $totalRemainingQuery = "SELECT 
    COUNT(*) as total,
    SUM(qt_restante) as volume_restant
FROM expression_dym
WHERE qt_restante > 0 
AND valide_achat = 'en_cours'";

    $totalRemainingStmt = $pdo->prepare($totalRemainingQuery);
    $totalRemainingStmt->execute();
    $totalRemainingResult = $totalRemainingStmt->fetch(PDO::FETCH_ASSOC);
    $notifications['counts']['remaining'] = $totalRemainingResult['total'];
    $notifications['counts']['total'] += $totalRemainingResult['total'];

    // Ajouter ces données supplémentaires aux statistiques
    $notifications['stats'] = [
        'volume_restant' => $totalRemainingResult['volume_restant'] ?? 0
    ];


    // 6. Ajout des retours fournisseurs aux notifications
    $retoursFournisseursQuery = "SELECT 
    COUNT(*) as total_retours,
    COUNT(CASE WHEN sr.status = 'pending' THEN 1 END) as retours_attente
FROM supplier_returns sr
WHERE " . getFilteredDateCondition('sr.created_at');

    $retoursFournisseursStmt = $pdo->prepare($retoursFournisseursQuery);
    $retoursFournisseursStmt->execute();
    $retoursFournisseursResult = $retoursFournisseursStmt->fetch(PDO::FETCH_ASSOC);

    // Ajouter ces données aux notifications
    $notifications['counts']['retours'] = $retoursFournisseursResult['total_retours'] ?? 0;
    $notifications['counts']['retours_attente'] = $retoursFournisseursResult['retours_attente'] ?? 0;

    // Augmenter le total si des retours sont en attente
    if ($notifications['counts']['retours_attente'] > 0) {
        $notifications['counts']['total'] += $notifications['counts']['retours_attente'];
    }

    // Récupérer les derniers retours fournisseurs
    $derniersRetoursQuery = "SELECT 
    sr.id, 
    sr.quantity,
    sr.reason,
    sr.status,
    sr.created_at,
    p.product_name,
    sr.supplier_name
FROM supplier_returns sr
JOIN products p ON sr.product_id = p.id
WHERE sr.status = 'pending'
ORDER BY sr.created_at DESC
LIMIT 3";

    $derniersRetoursStmt = $pdo->prepare($derniersRetoursQuery);
    $derniersRetoursStmt->execute();
    $notifications['materials']['retours'] = $derniersRetoursStmt->fetchAll(PDO::FETCH_ASSOC);
    // =================================

    // 7. Ajout des commandes annulées aux notifications
    $canceledOrdersQuery = "SELECT 
COUNT(*) as total_canceled,
COUNT(CASE WHEN DATEDIFF(NOW(), canceled_at) < 7 THEN 1 END) as recent_canceled
FROM canceled_orders_log co
WHERE " . getFilteredDateCondition('co.canceled_at');

    $canceledOrdersStmt = $pdo->prepare($canceledOrdersQuery);
    $canceledOrdersStmt->execute();
    $canceledOrdersResult = $canceledOrdersStmt->fetch(PDO::FETCH_ASSOC);

    // Ajouter ces données aux notifications
    $notifications['counts']['canceled'] = $canceledOrdersResult['total_canceled'] ?? 0;
    $notifications['counts']['recent_canceled'] = $canceledOrdersResult['recent_canceled'] ?? 0;

    // Augmenter le total si des commandes ont été récemment annulées
    if ($notifications['counts']['recent_canceled'] > 0) {
        $notifications['counts']['total'] += $notifications['counts']['recent_canceled'];
    }

    // Récupérer les dernières commandes annulées
    $recentCanceledOrdersQuery = "SELECT 
co.id, 
co.order_id,
co.designation,
co.canceled_at,
co.cancel_reason,
co.project_id,
ip.code_projet,
ip.nom_client,
am.quantity,
am.unit
FROM canceled_orders_log co
LEFT JOIN identification_projet ip ON co.project_id = ip.idExpression
LEFT JOIN achats_materiaux am ON co.order_id = am.id
WHERE " . getFilteredDateCondition('co.canceled_at') . "
ORDER BY co.canceled_at DESC
LIMIT 3";

    $recentCanceledOrdersStmt = $pdo->prepare($recentCanceledOrdersQuery);
    $recentCanceledOrdersStmt->execute();
    $notifications['materials']['canceled'] = $recentCanceledOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

    // =================================
    // Débogage optionnel
    if (isset($_GET['debug_notifications'])) {
        echo '<div style="position:fixed; top:0; left:0; width:100%; background:blue; color:white; padding:10px; z-index:1000;">';
        echo '<strong>Débogage Notifications:</strong><br>';
        echo 'Date système: ' . $systemStartDate . '<br>';
        echo 'Urgent: ' . $notifications['counts']['urgent'] . '<br>';
        echo 'Récent: ' . $notifications['counts']['recent'] . '<br>';
        echo 'En attente: ' . $notifications['counts']['pending'] . '<br>';
        echo 'Total: ' . $notifications['counts']['total'] . '<br>';
        echo '</div>';
    }

} catch (Exception $e) {
    // Réinitialiser en cas d'erreur
    $notifications = [
        'materials' => ['urgent' => [], 'recent' => [], 'pending' => [], 'remaining' => []],
        'counts' => ['urgent' => 0, 'recent' => 0, 'pending' => 0, 'remaining' => 0, 'total' => 0]
    ];

    // Débogage des erreurs
    if (isset($_GET['debug_notifications'])) {
        echo '<div style="position:fixed; top:0; left:0; width:100%; background:red; color:white; padding:10px; z-index:1000;">';
        echo '<strong>Erreur de notification:</strong> ' . $e->getMessage();
        echo '</div>';
    }
}

// Fonction pour formater les dates de façon relative
function timeAgo($date)
{
    $timestamp = strtotime($date);
    $strTime = array("seconde", "minute", "heure", "jour", "mois", "année");
    $length = array("60", "60", "24", "30", "12", "10");

    $currentTime = time();
    if ($currentTime >= $timestamp) {
        $diff = $currentTime - $timestamp;

        if ($diff < 60) {
            return "à l'instant";
        }

        for ($i = 0; $diff >= $length[$i] && $i < count($length) - 1; $i++) {
            $diff = $diff / $length[$i];
        }

        $diff = round($diff);
        $suffix = $diff > 1 ? "s" : "";
        return "il y a " . $diff . " " . $strTime[$i] . $suffix;
    }
}
?>

<nav class="bg-gray-800">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <img class="h-8 w-15" src="<?= $base_url ?>/public/logo.png" alt="Your Company">
                </div>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="<?= $base_url ?>/User-Achat/dashboard.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'dashboard.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>"
                            aria-current="page">Dashboard</a>
                        <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'achats_materiaux.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Achats
                            Matériaux</a>
                        <a href="<?= $base_url ?>/fournisseurs/fournisseurs.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'fournisseurs.php' || $current_page == 'supplier_details.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Fournisseurs</a>
                        <a href="<?= $base_url ?>/User-Achat/views/besoins/index.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'index.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Expression
                            de besoin Système</a>
                        <!-- Nouveau lien vers les statistiques -->
                        <a href="<?= $base_url ?>/User-Achat/statistics/index.php"
                            class="rounded-md px-3 py-2 text-sm font-medium flex items-center <?php echo (strpos($current_page, 'reserve') !== false || $current_page == 'index.php' && strpos($_SERVER['REQUEST_URI'], '/statistics/') !== false) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z" />
                                <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z" />
                            </svg>
                            Statistiques
                        </a>

                        <a href="<?= $base_url ?>/User-Achat/profile.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'profile.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Profile</a>
                    </div>
                </div>
            </div>
            <div class="hidden md:block">
                <div class="ml-4 flex items-center md:ml-6">
                    <div class="relative">
                        <button type="button" id="notifications-toggle"
                            class="relative rounded-full bg-gray-800 p-1 text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800">
                            <span class="absolute -inset-1.5"></span>
                            <span class="sr-only">View notifications</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            <?php if ($notifications['counts']['total'] > 0): ?>
                                <span class="absolute top-0 right-0 -mt-1 -mr-1 flex items-center justify-center">
                                    <?php if ($notifications['counts']['urgent'] > 0): ?>
                                        <!-- Badge rouge pour les notifications urgentes -->
                                        <span
                                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                        <span
                                            class="relative inline-flex rounded-full h-5 w-5 bg-red-500 text-white text-xs flex items-center justify-center">
                                            <?php echo $notifications['counts']['urgent']; ?>
                                        </span>
                                    <?php else: ?>
                                        <!-- Badge bleu pour les notifications normales -->
                                        <span
                                            class="relative inline-flex rounded-full h-5 w-5 bg-blue-500 text-white text-xs flex items-center justify-center">
                                            <?php echo $notifications['counts']['total']; ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <!-- Notifications Dropdown - Version optimisée avec onglets -->
                        <div id="notifications-dropdown"
                            class="hidden absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-lg border border-gray-200 z-50 overflow-hidden">
                            <div class="p-3 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                                <h3 class="text-lg font-semibold text-gray-800">Notifications</h3>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?php echo $notifications['counts']['total']; ?> matériaux
                                </span>
                            </div>

                            <!-- Système d'onglets -->
                            <div class="flex border-b border-gray-200">
                                <button type="button"
                                    class="notification-tab-button notification-tab-active flex-1 py-2 px-1 text-sm font-medium"
                                    data-notification-tab="urgent">
                                    <span class="relative">
                                        Urgents
                                        <?php if ($notifications['counts']['urgent'] > 0): ?>
                                            <span
                                                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-xs text-white">
                                                <?php echo $notifications['counts']['urgent']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </button>
                                <button type="button"
                                    class="notification-tab-button flex-1 py-2 px-1 text-sm font-medium"
                                    data-notification-tab="recents">
                                    <span class="relative">
                                        Récents
                                        <?php if ($notifications['counts']['recent'] > 0): ?>
                                            <span
                                                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-blue-500 text-xs text-white">
                                                <?php echo $notifications['counts']['recent']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </button>
                                <button type="button"
                                    class="notification-tab-button flex-1 py-2 px-1 text-sm font-medium"
                                    data-notification-tab="partials">
                                    <span class="relative">
                                        Partiels
                                        <?php if ($notifications['counts']['remaining'] > 0): ?>
                                            <span
                                                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-yellow-500 text-xs text-white">
                                                <?php echo $notifications['counts']['remaining']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </button>
                                <button type="button"
                                    class="notification-tab-button flex-1 py-2 px-1 text-sm font-medium"
                                    data-notification-tab="others">
                                    <span class="relative">
                                        Autres
                                        <?php if ($notifications['counts']['pending'] + $notifications['counts']['retours_attente'] + $notifications['counts']['recent_canceled'] > 0): ?>
                                            <span
                                                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-gray-500 text-xs text-white">
                                                <?php echo $notifications['counts']['pending'] + $notifications['counts']['retours_attente'] + $notifications['counts']['recent_canceled']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </button>
                            </div>

                            <!-- Contenu des onglets -->
                            <div>
                                <!-- Section des notifications urgentes -->
                                <div id="notification-tab-urgent" class="notification-tab-content">
                                    <?php if (!empty($notifications['materials']['urgent'])): ?>
                                        <div class="max-h-72 overflow-y-auto">
                                            <?php foreach ($notifications['materials']['urgent'] as $material): ?>
                                                <div
                                                    class="p-3 border-l-4 border-red-500 hover:bg-red-50 transition duration-200 flex justify-between items-start">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($material['designation']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-600 mt-1">
                                                            <span
                                                                class="font-medium"><?php echo htmlspecialchars($material['code_projet']); ?></span>
                                                            - <?php echo htmlspecialchars($material['nom_client']); ?>
                                                        </p>
                                                        <div class="flex items-center mt-1">
                                                            <span class="text-xs text-gray-500 mr-2">
                                                                <?php echo htmlspecialchars($material['qt_acheter']); ?>
                                                                <?php echo htmlspecialchars($material['unit']); ?>
                                                            </span>
                                                            <span class="text-xs text-red-600 font-medium">
                                                                <?php
                                                                if (isset($material['days_pending'])) {
                                                                    if ($material['days_pending'] > 7) {
                                                                        echo 'En attente depuis ' . $material['days_pending'] . ' jours !';
                                                                    } elseif ($material['qt_acheter'] > 100) {
                                                                        echo 'Grande quantité !';
                                                                    }
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                                                        class="text-red-600 hover:text-red-800 text-xs font-medium">
                                                        Commander
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-6 text-center text-gray-500">
                                            <p>Aucun matériau urgent</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Section des matériaux récents -->
                                <div id="notification-tab-recents" class="notification-tab-content hidden">
                                    <?php if (!empty($notifications['materials']['recent'])): ?>
                                        <div class="max-h-72 overflow-y-auto">
                                            <?php foreach ($notifications['materials']['recent'] as $material): ?>
                                                <div
                                                    class="p-3 hover:bg-blue-50 border-l-4 border-blue-500 transition duration-200 flex justify-between items-start">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($material['designation']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-600 mt-1">
                                                            <span
                                                                class="font-medium"><?php echo htmlspecialchars($material['code_projet']); ?></span>
                                                            - <?php echo htmlspecialchars($material['nom_client']); ?>
                                                        </p>
                                                        <div class="flex items-center mt-1">
                                                            <span class="text-xs text-gray-500 mr-2">
                                                                <?php echo htmlspecialchars($material['qt_acheter']); ?>
                                                                <?php echo htmlspecialchars($material['unit']); ?>
                                                            </span>
                                                            <span class="text-xs text-blue-600">
                                                                Ajouté <?php echo timeAgo($material['created_at']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                                                        class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                                        Commander
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-6 text-center text-gray-500">
                                            <p>Aucun nouveau matériau</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Section des commandes partielles -->
                                <div id="notification-tab-partials" class="notification-tab-content hidden">
                                    <?php if (!empty($notifications['materials']['remaining'])): ?>
                                        <div class="max-h-72 overflow-y-auto">
                                            <?php foreach ($notifications['materials']['remaining'] as $material): ?>
                                                <div
                                                    class="p-3 border-l-4 border-yellow-500 hover:bg-yellow-50 transition duration-200 flex justify-between items-start">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($material['designation']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-600 mt-1">
                                                            <span
                                                                class="font-medium"><?php echo htmlspecialchars($material['code_projet']); ?></span>
                                                            - <?php echo htmlspecialchars($material['nom_client']); ?>
                                                        </p>
                                                        <div class="flex items-center mt-1">
                                                            <span class="text-xs text-yellow-600">
                                                                Restant:
                                                                <?php echo htmlspecialchars($material['qt_restante']); ?>
                                                                <?php echo htmlspecialchars($material['unit']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php?tab=partial"
                                                        class="text-yellow-600 hover:text-yellow-800 text-xs font-medium">
                                                        Commander
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-6 text-center text-gray-500">
                                            <p>Aucune commande partielle</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Section autres notifications -->
                                <div id="notification-tab-others" class="notification-tab-content hidden">
                                    <div class="max-h-72 overflow-y-auto">
                                        <?php if (!empty($notifications['materials']['pending'])): ?>
                                            <div class="p-2 bg-gray-100 text-xs font-medium text-gray-700">Matériaux en
                                                attente</div>
                                            <?php foreach (array_slice($notifications['materials']['pending'], 0, 2) as $material): ?>
                                                <div
                                                    class="p-3 hover:bg-gray-100 transition duration-200 flex justify-between items-start">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($material['designation']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-600 mt-1">
                                                            <span
                                                                class="font-medium"><?php echo htmlspecialchars($material['code_projet']); ?></span>
                                                            - <?php echo htmlspecialchars($material['nom_client']); ?>
                                                        </p>
                                                    </div>
                                                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                                                        class="text-gray-600 hover:text-gray-800 text-xs font-medium">
                                                        Commander
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (count($notifications['materials']['pending']) > 2): ?>
                                                <div class="text-center p-1 text-xs text-blue-500">
                                                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php">
                                                        + <?php echo count($notifications['materials']['pending']) - 2; ?>
                                                        autres
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if (!empty($notifications['materials']['retours'])): ?>
                                            <div class="p-2 bg-purple-100 text-xs font-medium text-purple-700">Retours
                                                fournisseurs</div>
                                            <?php foreach (array_slice($notifications['materials']['retours'], 0, 2) as $retour): ?>
                                                <div
                                                    class="p-3 border-l-4 border-purple-500 hover:bg-purple-50 transition duration-200 flex justify-between items-start">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($retour['product_name']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-600 mt-1">
                                                            <span
                                                                class="font-medium"><?php echo htmlspecialchars($retour['supplier_name']); ?></span>
                                                        </p>
                                                    </div>
                                                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php?tab=returns"
                                                        class="text-purple-600 hover:text-purple-800 text-xs font-medium">
                                                        Gérer
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <?php if (!empty($notifications['materials']['canceled'])): ?>
                                            <div class="p-2 bg-red-100 text-xs font-medium text-red-700">Commandes annulées
                                            </div>
                                            <?php foreach (array_slice($notifications['materials']['canceled'], 0, 2) as $order): ?>
                                                <div
                                                    class="p-3 border-l-4 border-red-500 hover:bg-red-50 transition duration-200 flex justify-between items-start">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($order['designation']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-600 mt-1">
                                                            <span
                                                                class="font-medium"><?php echo htmlspecialchars($order['code_projet'] ?? 'N/A'); ?></span>
                                                        </p>
                                                    </div>
                                                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php?tab=canceled"
                                                        class="text-red-600 hover:text-red-800 text-xs font-medium">
                                                        Voir détails
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <?php if (empty($notifications['materials']['pending']) && empty($notifications['materials']['retours']) && empty($notifications['materials']['canceled'])): ?>
                                            <div class="p-6 text-center text-gray-500">
                                                <p>Aucune autre notification</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Pied de page des notifications -->
                            <div class="p-3 bg-gray-50 text-center border-t border-gray-200">
                                <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow-sm transition duration-150">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M5 5a3 3 0 015-2.236A3 3 0 0114.83 6H16a2 2 0 110 4h-5V9a1 1 0 10-2 0v1H4a2 2 0 110-4h1.17C5.06 5.687 5 5.35 5 5zm4 1V5a1 1 0 10-1 1h1zm3 0a1 1 0 10-1-1v1h1z"
                                            clip-rule="evenodd" />
                                        <path d="M9 11H3v5a2 2 0 002 2h4v-7zM11 18h4a2 2 0 002-2v-5h-6v7z" />
                                    </svg>
                                    Voir tous les matériaux à acheter
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Profile dropdown -->
                    <div class="relative ml-3">
                        <div>
                            <button type="button"
                                class="relative flex max-w-xs items-center rounded-full bg-gray-800 text-sm focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800"
                                id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                <span class="absolute -inset-1.5"></span>
                                <span class="sr-only">Open user menu</span>
                                <?php if ($user && $user['profile_image']): ?>
                                    <img class="h-8 w-8 rounded-full"
                                        src="<?= $base_url ?>/uploads/<?php echo htmlspecialchars($user['profile_image']); ?>"
                                        alt="Profile Image">
                                <?php else: ?>
                                    <img class="h-8 w-8 rounded-full"
                                        src="<?= $base_url ?>/uploads/default-profile-image.png" alt="Profile Image">
                                <?php endif; ?>
                            </button>
                        </div>
                        <div class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none profile-menu hidden"
                            role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                            <a href="<?= $base_url ?>/User-Achat/profile.php"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem"
                                tabindex="-1">Profil</a>
                            <a href="<?= $base_url ?>/logout.php"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem"
                                tabindex="-1">Déconnexion</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="-mr-2 flex md:hidden">
                <!-- Mobile menu button (reste inchangé) -->
                <button type="button"
                    class="relative inline-flex items-center justify-center rounded-md bg-gray-800 p-2 text-gray-400 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800"
                    aria-controls="mobile-menu" aria-expanded="false">
                    <span class="absolute -inset-0.5"></span>
                    <span class="sr-only">Open main menu</span>
                    <svg class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu (avec ajout de support pour notifications) -->
    <div class="md:hidden" id="mobile-menu">
        <div class="space-y-1 px-2 pb-3 pt-2 sm:px-3">
            <a href="<?= $base_url ?>/User-Achat/dashboard.php"
                class="block rounded-md px-3 py-2 <?php echo ($current_page == 'dashboard.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>"
                aria-current="page">Dashboard</a>
            <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                class="block rounded-md px-3 py-2 <?php echo ($current_page == 'achats_materiaux.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Achats
                Matériaux
                <?php if ($notifications['counts']['total'] > 0): ?>
                    <span
                        class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full">
                        <?php echo $notifications['counts']['total']; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?= $base_url ?>/fournisseurs/fournisseurs.php"
                class="block rounded-md px-3 py-2 <?php echo ($current_page == 'fournisseurs.php' || $current_page == 'supplier_details.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Fournisseurs</a>
            <a href="<?= $base_url ?>/User-Achat/views/besoins/index.php"
                class="block rounded-md px-3 py-2 <?php echo ($current_page == 'index.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Expression
                de besoin système</a>
            <!-- Nouveau lien vers les statistiques -->
            <a href="<?= $base_url ?>/User-Achat/statistics/"
                class="rounded-md px-3 py-2 text-sm font-medium flex items-center <?php echo (strpos($current_page, 'reserve') !== false || $current_page == 'index.php' && strpos($_SERVER['REQUEST_URI'], '/statistics/') !== false) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z" />
                    <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z" />
                </svg>
                Statistiques
            </a>
            <a href="<?= $base_url ?>/User-Achat/profile.php"
                class="block rounded-md px-3 py-2 <?php echo ($current_page == 'profile.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Profile</a>
        </div>
        <div class="border-t border-gray-700 pb-3 pt-4">
            <div class="flex items-center px-5">
                <div class="flex-shrink-0">
                    <?php if ($user && $user['profile_image']): ?>
                        <img class="h-10 w-10 rounded-full"
                            src="<?= $base_url ?>/uploads/<?php echo htmlspecialchars($user['profile_image']); ?>"
                            alt="Profile Image">
                    <?php else: ?>
                        <img class="h-10 w-10 rounded-full" src="<?= $base_url ?>/uploads/default-profile-image.png"
                            alt="Profile Image">
                    <?php endif; ?>
                </div>
                <div class="ml-3">
                    <div class="text-base font-medium text-white"><?php echo htmlspecialchars($user['name']); ?></div>
                </div>

                <!-- Ajout du bouton de notifications pour mobile -->
                <?php if ($notifications['counts']['total'] > 0): ?>
                    <div class="ml-auto flex-shrink-0">
                        <button id="mobile-notifications-toggle"
                            class="relative rounded-full bg-gray-700 p-1 text-gray-400 hover:text-white">
                            <span class="sr-only">View notifications</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            <?php if ($notifications['counts']['urgent'] > 0): ?>
                                <span
                                    class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-400 ring-2 ring-red-600"></span>
                            <?php endif; ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-3 space-y-1 px-2">
                <a href="<?= $base_url ?>/User-Achat/profile.php"
                    class="block rounded-md py-2 px-3 text-base font-medium text-gray-300 hover:bg-gray-700 hover:text-white">Profil</a>
                <a href="<?= $base_url ?>/logout.php"
                    class="block rounded-md py-2 px-3 text-base font-medium text-gray-300 hover:bg-gray-700 hover:text-white">Déconnexion</a>
            </div>

            <!-- Résumé des notifications pour mobile -->
            <div id="mobile-notifications-dropdown" class="hidden mt-3 mx-2 bg-gray-700 rounded-lg overflow-hidden">
                <?php if ($notifications['counts']['urgent'] > 0): ?>
                    <div class="p-3 bg-red-900 text-white">
                        <div class="font-semibold flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                            <?php echo $notifications['counts']['urgent']; ?> matériaux urgents
                        </div>
                        <p class="text-sm opacity-80 mt-1">Nécessitent une action immédiate</p>
                    </div>
                <?php endif; ?>

                <?php if ($notifications['counts']['recent'] > 0): ?>
                    <div class="p-3 bg-blue-900 text-white">
                        <div class="font-semibold flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                    clip-rule="evenodd" />
                            </svg>
                            <?php echo $notifications['counts']['recent']; ?> nouveaux matériaux
                        </div>
                        <p class="text-sm opacity-80 mt-1">Ajoutés ces dernières 24 heures</p>
                    </div>
                <?php endif; ?>

                <?php if ($notifications['counts']['retours_attente'] > 0): ?>
                    <div class="p-3 bg-purple-900 text-white">
                        <div class="font-semibold flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"
                                    clip-rule="evenodd" />
                            </svg>
                            <?php echo $notifications['counts']['retours_attente']; ?> retours en attente
                        </div>
                        <p class="text-sm opacity-80 mt-1">Retours fournisseurs à traiter</p>
                    </div>
                <?php endif; ?>

                <?php if ($notifications['counts']['recent_canceled'] > 0): ?>
                    <div class="p-3 bg-gray-800 text-white">
                        <div class="font-semibold flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                    clip-rule="evenodd" />
                            </svg>
                            <?php echo $notifications['counts']['recent_canceled']; ?> commandes annulées
                        </div>
                        <p class="text-sm opacity-80 mt-1">Projets marqués comme terminés</p>
                    </div>
                <?php endif; ?>

                <div class="p-3 text-white">
                    <div class="font-semibold">
                        Total: <?php echo $notifications['counts']['total']; ?> matériaux à commander
                    </div>
                    <a href="<?= $base_url ?>/User-Achat/achats_materiaux.php"
                        class="block mt-2 bg-gray-600 hover:bg-gray-500 text-center py-2 px-3 rounded text-sm">
                        Gérer les commandes
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Gestion du menu profil
        const profileMenuButton = document.getElementById('user-menu-button');
        const profileMenu = document.querySelector('.profile-menu');

        if (profileMenuButton && profileMenu) {
            profileMenuButton.addEventListener('click', function () {
                profileMenu.classList.toggle('hidden');
            });
        }

        // Gestion des notifications - Desktop
        const notificationsToggle = document.getElementById('notifications-toggle');
        const notificationsDropdown = document.getElementById('notifications-dropdown');

        if (notificationsToggle && notificationsDropdown) {
            notificationsToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                notificationsDropdown.classList.toggle('hidden');

                // Fermer le menu profil si ouvert
                if (profileMenu) {
                    profileMenu.classList.add('hidden');
                }
            });

            // Empêcher la fermeture du dropdown lors d'un clic à l'intérieur
            notificationsDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        // Gestion des notifications - Mobile
        const mobileNotificationsToggle = document.getElementById('mobile-notifications-toggle');
        const mobileNotificationsDropdown = document.getElementById('mobile-notifications-dropdown');

        if (mobileNotificationsToggle && mobileNotificationsDropdown) {
            mobileNotificationsToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                mobileNotificationsDropdown.classList.toggle('hidden');
            });
        }

        // Fermer les dropdowns en cliquant à l'extérieur
        document.addEventListener('click', function (event) {
            if (profileMenu && profileMenuButton && !profileMenuButton.contains(event.target) && !profileMenu.contains(event.target)) {
                profileMenu.classList.add('hidden');
            }

            if (notificationsDropdown && notificationsToggle && !notificationsToggle.contains(event.target) && !notificationsDropdown.contains(event.target)) {
                notificationsDropdown.classList.add('hidden');
            }

            if (mobileNotificationsDropdown && mobileNotificationsToggle && !mobileNotificationsToggle.contains(event.target) && !mobileNotificationsDropdown.contains(event.target)) {
                mobileNotificationsDropdown.classList.add('hidden');
            }
        });
    });

    // Gestion des onglets pour les notifications
    const notificationTabButtons = document.querySelectorAll('.notification-tab-button');
    const notificationTabContents = document.querySelectorAll('.notification-tab-content');

    notificationTabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Désactiver tous les onglets
            notificationTabButtons.forEach(btn => {
                btn.classList.remove('notification-tab-active');
                btn.classList.remove('text-blue-600');
                btn.classList.remove('border-b-2');
                btn.classList.remove('border-blue-600');
            });

            // Masquer tous les contenus
            notificationTabContents.forEach(content => {
                content.classList.add('hidden');
            });

            // Activer l'onglet cliqué
            button.classList.add('notification-tab-active');
            button.classList.add('text-blue-600');
            button.classList.add('border-b-2');
            button.classList.add('border-blue-600');

            // Afficher le contenu correspondant
            const tabName = button.getAttribute('data-notification-tab');
            document.getElementById(`notification-tab-${tabName}`).classList.remove('hidden');
        });
    });
</script>