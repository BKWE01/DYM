<?php
/**
 * API pour récupérer les détails d'une commande annulée
 * 
 * Ce fichier retourne les détails complets d'une commande annulée spécifique
 * ainsi que les commandes liées éventuelles
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

// Vérifier si l'ID de la commande est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de commande requis']);
    exit();
}

$id = intval($_GET['id']);

try {
    // Récupérer les détails de la commande annulée
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
                DATE_FORMAT(cl.canceled_at, '%d/%m/%Y %H:%i') as canceled_at_formatted,
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
                am.date_achat,
                DATE_FORMAT(am.date_achat, '%d/%m/%Y') as date_achat_formatted
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
              WHERE 
                cl.id = :id";
                
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        exit();
    }
    
    // Pour les commandes réelles (pas les expressions seules), récupérer les commandes associées
    $relatedOrders = [];
    if ($order['order_id'] > 0) {
        $relatedQuery = "SELECT 
                        am.id,
                        am.expression_id,
                        am.designation,
                        am.quantity,
                        am.unit,
                        am.prix_unitaire,
                        am.fournisseur,
                        am.status,
                        am.is_partial,
                        am.date_achat,
                        DATE_FORMAT(am.date_achat, '%d/%m/%Y') as date_achat_formatted
                      FROM 
                        achats_materiaux am
                      WHERE 
                        am.expression_id = :expression_id
                        AND am.designation = :designation
                        AND am.id != :order_id
                      ORDER BY 
                        am.date_achat DESC";
                        
        $relatedStmt = $pdo->prepare($relatedQuery);
        $relatedStmt->bindParam(':expression_id', $order['project_id'], PDO::PARAM_STR);
        $relatedStmt->bindParam(':designation', $order['designation'], PDO::PARAM_STR);
        $relatedStmt->bindParam(':order_id', $order['order_id'], PDO::PARAM_INT);
        $relatedStmt->execute();
        $relatedOrders = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Retourner les résultats
    echo json_encode([
        'success' => true, 
        'order' => $order,
        'related_orders' => $relatedOrders
    ]);
    
} catch (Exception $e) {
    // En cas d'erreur
    error_log("Erreur lors de la récupération des détails de la commande annulée: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de la récupération des détails: ' . $e->getMessage()
    ]);
}