<?php
/**
 * API pour récupérer les événements récents pour le tableau de bord du Bureau d'Études
 */
session_start();

// Désactiver tout affichage d'erreur qui pourrait corrompre le JSON
ini_set('display_errors', 0);
error_reporting(0);

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Non autorisé'
    ]);
    exit;
}

// Connexion à la base de données
require_once '../../database/connection.php';
require_once '../../include/date_helper.php';

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
    
    return "à l'instant";
}

// Augmenter la limite pour récupérer plus d'événements qui seront paginés côté client
$limit = 50;

// Tableau pour stocker les événements
$events = [];

try {
    // 1. Récupérer les matériaux récemment reçus
    $receivedQuery = "SELECT 
                am.designation, 
                am.quantity, 
                am.unit, 
                am.date_reception,
                ip.code_projet, 
                ip.nom_client
            FROM achats_materiaux am
            JOIN identification_projet ip ON am.expression_id = ip.idExpression
            WHERE am.status = 'reçu' 
            AND am.date_reception IS NOT NULL
            AND am.date_reception >= DATE_SUB(NOW(), INTERVAL 14 DAY)
            AND " . getFilteredDateCondition('am.date_reception') . "
            ORDER BY am.date_reception DESC
            LIMIT " . $limit;

    $receivedStmt = $pdo->prepare($receivedQuery);
    $receivedStmt->execute();
    $receivedMaterials = $receivedStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($receivedMaterials as $material) {
        $events[] = [
            'event_type' => 'received',
            'title' => 'Matériau reçu',
            'description' => htmlspecialchars($material['designation']) . " (" . htmlspecialchars($material['quantity']) . " " . htmlspecialchars($material['unit'] ?? 'unité(s)') . ") - Projet: " . htmlspecialchars($material['code_projet'] ?? 'N/A'),
            'time' => $material['date_reception'],
            'time_ago' => timeAgo($material['date_reception'])
        ];
    }

    // 2. Récupérer les commandes annulées
    $canceledQuery = "SELECT 
                co.designation,
                co.canceled_at,
                co.cancel_reason,
                ip.code_projet,
                ip.nom_client
            FROM canceled_orders_log co
            LEFT JOIN identification_projet ip ON co.project_id = ip.idExpression
            WHERE " . getFilteredDateCondition('co.canceled_at') . "
            AND co.canceled_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
            ORDER BY co.canceled_at DESC
            LIMIT " . $limit;

    $canceledStmt = $pdo->prepare($canceledQuery);
    $canceledStmt->execute();
    $canceledOrders = $canceledStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($canceledOrders as $order) {
        $events[] = [
            'event_type' => 'canceled',
            'title' => 'Commande annulée',
            'description' => htmlspecialchars($order['designation']) . " - Projet: " . htmlspecialchars($order['nom_client'] ?? 'N/A') . " - Raison: " . htmlspecialchars($order['cancel_reason'] ?? 'Non spécifiée'),
            'time' => $order['canceled_at'],
            'time_ago' => timeAgo($order['canceled_at'])
        ];
    }

    // 3. Récupérer les retours matériaux
    $returnsQuery = "SELECT 
                sm.quantity,
                sm.product_id, 
                sm.destination,
                sm.nom_projet,
                sm.created_at,
                p.product_name
            FROM stock_movement sm
            LEFT JOIN products p ON sm.product_id = p.id
            WHERE sm.movement_type = 'transfer' 
            AND " . getFilteredDateCondition('sm.created_at') . "
            AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
            ORDER BY sm.created_at DESC
            LIMIT " . $limit;

    $returnsStmt = $pdo->prepare($returnsQuery);
    $returnsStmt->execute();
    $returns = $returnsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($returns as $return) {
        $productName = $return['product_name'] ?? ('Produit #' . $return['product_id']);
        $events[] = [
            'event_type' => 'return',
            'title' => 'Retour de matériel',
            'description' => htmlspecialchars($productName) . " (" . htmlspecialchars($return['quantity']) . " unité(s)) - Projet: " . htmlspecialchars($return['nom_projet'] ?? 'N/A'),
            'time' => $return['created_at'],
            'time_ago' => timeAgo($return['created_at'])
        ];
    }

    // 4. Récupérer les expressions récemment mises à jour
    $updatedQuery = "SELECT 
                ed.designation, 
                ed.updated_at,
                ed.valide_achat,
                ip.code_projet, 
                ip.nom_client
            FROM expression_dym ed
            JOIN identification_projet ip ON ed.idExpression = ip.idExpression
            WHERE ed.updated_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
            AND " . getFilteredDateCondition('ed.updated_at') . "
            ORDER BY ed.updated_at DESC
            LIMIT " . $limit;

    $updatedStmt = $pdo->prepare($updatedQuery);
    $updatedStmt->execute();
    $updatedExpressions = $updatedStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($updatedExpressions as $expression) {
        $statusInfo = "";
        if (!empty($expression['valide_achat'])) {
            $statusInfo = " - Statut: " . htmlspecialchars($expression['valide_achat']);
        }
        
        $events[] = [
            'event_type' => 'updated',
            'title' => 'Expression mise à jour',
            'description' => htmlspecialchars($expression['designation']) . " - Projet: " . htmlspecialchars($expression['nom_client'] ?? 'N/A') . $statusInfo,
            'time' => $expression['updated_at'],
            'time_ago' => timeAgo($expression['updated_at'])
        ];
    }

    // Vérifier s'il y a des événements
    if (empty($events)) {
        // Si aucun événement, créer au moins un événement fictif pour tester
        $events[] = [
            'event_type' => 'info',
            'title' => 'Pas d\'événements récents',
            'description' => 'Aucun événement récent à afficher pour le moment.',
            'time' => date('Y-m-d H:i:s'),
            'time_ago' => 'à l\'instant'
        ];
    }

    // Trier tous les événements par date (les plus récents en premier)
    usort($events, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

    // Limiter le nombre d'événements pour éviter une réponse trop volumineuse
    $events = array_slice($events, 0, 50);

    // Retourner les événements au format JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'events' => $events
    ]);

} catch (Exception $e) {
    // Journaliser l'erreur pour le débogage
    error_log("Erreur API events: " . $e->getMessage() . " - " . $e->getTraceAsString());
    
    // En cas d'erreur, retourner un message d'erreur et au moins un événement fictif pour les tests
    $errorEvents = [
        [
            'event_type' => 'error',
            'title' => 'Erreur système',
            'description' => 'Une erreur est survenue lors de la récupération des événements.',
            'time' => date('Y-m-d H:i:s'),
            'time_ago' => 'à l\'instant'
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des événements: ' . $e->getMessage(),
        'events' => $errorEvents // Renvoyer au moins un événement fictif pour éviter des problèmes côté client
    ]);
}