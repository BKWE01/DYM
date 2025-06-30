<?php
// Fichier: /components/notifications/be/notifications_be.php
// Système de notifications pour le Bureau d'Études
$rootPath = dirname(__DIR__); 
include_once $rootPath.'/include/date_helper.php';

// Configuration système
$systemConfig = require $rootPath.'/config/system_config.php';
$systemStartDate = $systemConfig['system_start_date'];

// Variables pour stocker les données de notification
$notifications = [
    'materials' => [
        'received' => [],    // Matériaux reçus
        'canceled' => [],    // Commandes annulées
        'recent' => [],      // Modifications récentes
        'pending' => []      // Actions en attente
    ],
    'counts' => [
        'received' => 0,     // Nombre de matériaux reçus
        'canceled' => 0,     // Nombre de commandes annulées
        'recent' => 0,       // Nombre de modifications récentes
        'pending' => 0,      // Nombre d'actions en attente
        'total' => 0         // Total des notifications
    ]
];

try {
    // 1. Matériaux récemment reçus - critères: valide_achat = 'reçu' et date_reception récente (dernières 48h)
    $receivedQuery = "SELECT 
                am.id, 
                am.expression_id, 
                am.designation, 
                am.quantity, 
                am.unit, 
                am.date_reception,
                ip.code_projet, 
                ip.nom_client,
                u.name as received_by
            FROM achats_materiaux am
            JOIN identification_projet ip ON am.expression_id = ip.idExpression
            LEFT JOIN users_exp u ON am.user_achat = u.id
            WHERE am.status = 'reçu' 
            AND am.date_reception IS NOT NULL
            AND am.date_reception >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
            AND " . getFilteredDateCondition('am.date_reception') . "
            ORDER BY am.date_reception DESC
            LIMIT 5";

    $receivedStmt = $pdo->prepare($receivedQuery);
    $receivedStmt->execute();
    $notifications['materials']['received'] = $receivedStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications['counts']['received'] = count($notifications['materials']['received']);

    // 2. Commandes récemment annulées - critères: dans la table canceled_orders_log, dernières 48h
    $canceledQuery = "SELECT 
                co.id, 
                co.order_id,
                co.project_id,
                co.designation,
                co.canceled_at,
                co.cancel_reason,
                ip.code_projet,
                ip.nom_client,
                u.name as canceled_by
            FROM canceled_orders_log co
            LEFT JOIN identification_projet ip ON co.project_id = ip.idExpression
            LEFT JOIN users_exp u ON co.canceled_by = u.id
            WHERE " . getFilteredDateCondition('co.canceled_at') . "
            AND co.canceled_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
            ORDER BY co.canceled_at DESC
            LIMIT 5";

    $canceledStmt = $pdo->prepare($canceledQuery);
    $canceledStmt->execute();
    $notifications['materials']['canceled'] = $canceledStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications['counts']['canceled'] = count($notifications['materials']['canceled']);

    // 3. Modifications récentes des expressions de besoins (dernières 24h)
    $recentQuery = "SELECT 
                ed.id, 
                ed.idExpression, 
                ed.designation, 
                ed.updated_at,
                ed.valide_achat,
                ip.code_projet, 
                ip.nom_client
            FROM expression_dym ed
            JOIN identification_projet ip ON ed.idExpression = ip.idExpression
            WHERE ed.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND " . getFilteredDateCondition('ed.updated_at') . "
            ORDER BY ed.updated_at DESC
            LIMIT 5";

    $recentStmt = $pdo->prepare($recentQuery);
    $recentStmt->execute();
    $notifications['materials']['recent'] = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications['counts']['recent'] = count($notifications['materials']['recent']);

    // 4. Retours de matériaux en attente pour les projets (dernières 48h)
    $pendingQuery = "SELECT 
                sm.id, 
                sm.product_id, 
                sm.quantity, 
                sm.movement_type, 
                sm.destination,
                sm.nom_projet,
                sm.created_at,
                p.product_name
            FROM stock_movement sm
            LEFT JOIN products p ON sm.product_id = p.id
            WHERE sm.movement_type = 'transfer' 
            AND " . getFilteredDateCondition('sm.created_at') . "
            AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
            ORDER BY sm.created_at DESC
            LIMIT 5";

    $pendingStmt = $pdo->prepare($pendingQuery);
    $pendingStmt->execute();
    $notifications['materials']['pending'] = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications['counts']['pending'] = count($notifications['materials']['pending']);

    // 5. Nombre total de notifications
    $notifications['counts']['total'] =
        $notifications['counts']['received'] +
        $notifications['counts']['canceled'] +
        $notifications['counts']['recent'] +
        $notifications['counts']['pending'];

} catch (Exception $e) {
    // Réinitialiser en cas d'erreur
    $notifications = [
        'materials' => ['received' => [], 'canceled' => [], 'recent' => [], 'pending' => []],
        'counts' => ['received' => 0, 'canceled' => 0, 'recent' => 0, 'pending' => 0, 'total' => 0]
    ];

    // Journaliser l'erreur
    error_log("Erreur de notification Bureau d'Études: " . $e->getMessage());
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