<?php
/**
 * API pour récupérer les commandes annulées
 * 
 * Ce fichier retourne la liste des commandes annulées
 * avec les statistiques associées
 * 
 * @package DYM_MANUFACTURE
 * @subpackage stock
 */

session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Connexion à la base de données et helpers
include_once '../../../../../../database/connection.php';
include_once '../../../../../../include/date_helper.php';

// Récupérer les commandes annulées
try {
    // Requête principale pour récupérer les commandes annulées
    $query = "SELECT 
                cl.id,
                cl.order_id,
                cl.project_id,
                cl.designation,
                cl.canceled_by,
                u.name AS canceled_by_name,
                cl.cancel_reason,
                cl.original_status,
                cl.is_partial,
                cl.canceled_at,
                ip.code_projet,
                ip.nom_client,
                ip.description_projet,
                ip.sitgeo,
                ip.chefprojet,
                CASE 
                    WHEN cl.order_id > 0 THEN am.quantity
                    WHEN cl.original_status = 'pas validé' THEN ed.qt_acheter
                    ELSE NULL
                END as quantity,
                COALESCE(am.unit, ed.unit) as unit,
                COALESCE(am.prix_unitaire, ed.prix_unitaire) as prix_unitaire,
                COALESCE(am.fournisseur, ed.fournisseur) as fournisseur,
                am.date_achat
              FROM 
                canceled_orders_log cl
              LEFT JOIN 
                users_exp u ON cl.canceled_by = u.id
              LEFT JOIN 
                identification_projet ip ON cl.project_id = ip.idExpression
              LEFT JOIN 
                achats_materiaux am ON cl.order_id = am.id AND cl.order_id > 0
              LEFT JOIN
                expression_dym ed ON cl.project_id = ed.idExpression AND cl.designation = ed.designation AND cl.order_id = 0
              ORDER BY 
                cl.canceled_at DESC";
                
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Requête pour les statistiques
    $statsQuery = "SELECT 
                    COUNT(*) as total_canceled,
                    COUNT(DISTINCT project_id) as projects_count,
                    MAX(canceled_at) as last_canceled_date
                  FROM 
                    canceled_orders_log";
                    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Formater les dates pour l'affichage
    foreach ($orders as &$order) {
        if (isset($order['date_achat'])) {
            $order['date_achat_formatted'] = date('d/m/Y', strtotime($order['date_achat']));
        }
        if (isset($order['canceled_at'])) {
            $order['canceled_at_formatted'] = date('d/m/Y H:i', strtotime($order['canceled_at']));
        }
    }
    
    // Retourner les résultats
    echo json_encode([
        'success' => true, 
        'data' => $orders,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    // En cas d'erreur
    error_log("Erreur lors de la récupération des commandes annulées: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
    ]);
}