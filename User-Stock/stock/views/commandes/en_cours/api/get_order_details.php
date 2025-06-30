<?php
/**
 * API pour récupérer les détails d'une commande
 * 
 * Récupère les informations détaillées d'une commande ainsi que son historique
 * sur la base de l'ID d'expression et de la désignation du produit.
 * 
 * @package DYM_MANUFACTURE
 * @subpackage stock/api
 */

// Initialisation
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non connecté'
    ]);
    exit();
}

// Vérifier les paramètres requis
if (!isset($_GET['expression_id']) || !isset($_GET['designation'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants: expression_id et/ou designation'
    ]);
    exit();
}

$expressionId = $_GET['expression_id'];
$designation = $_GET['designation'];

// Détecter si l'ID d'expression est de type système
$isSystem = preg_match('/^[0-9]{5}-EXP_B-[0-9]{8}$/', $expressionId) || strpos($expressionId, 'SYS-') === 0;

// Connexion à la base de données
try {
    include_once '../../../../../../database/connection.php';

    // 1. Récupérer les informations de base de la commande
    if ($isSystem) {
        // Requête pour les expressions système (besoins)
        $orderQuery = "SELECT 
                        b.id,
                        b.idBesoin as idExpression,
                        b.designation_article as designation,
                        b.qt_acheter as quantity,
                        b.qt_demande as original_quantity,
                        (b.qt_demande - b.qt_acheter) as remaining,
                        b.caracteristique as unit,
                        (SELECT MAX(am.prix_unitaire) FROM achats_materiaux am WHERE am.expression_id = b.idBesoin AND am.designation = b.designation_article) as prix_unitaire,
                        (SELECT MAX(am.fournisseur) FROM achats_materiaux am WHERE am.expression_id = b.idBesoin AND am.designation = b.designation_article) as fournisseur,
                        b.achat_status as status,
                        b.created_at,
                        b.updated_at,
                        CONCAT('SYS-', COALESCE(d.service_demandeur, 'Système')) as code_projet,
                        COALESCE(d.client, 'Demande interne') as nom_client,
                        (SELECT MAX(am.date_achat) FROM achats_materiaux am WHERE am.expression_id = b.idBesoin AND am.designation = b.designation_article) as date_achat
                    FROM besoins b
                    LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                    WHERE b.idBesoin = :expression_id
                    AND b.designation_article = :designation
                    LIMIT 1";
    } else {
        // Requête pour les expressions normales (expression_dym)
        $orderQuery = "SELECT 
                        ed.id, 
                        ed.idExpression,
                        ed.designation, 
                        ed.qt_acheter as quantity, 
                        ed.initial_qt_acheter as original_quantity,
                        ed.qt_restante as remaining,
                        ed.unit, 
                        ed.prix_unitaire,
                        ed.fournisseur,
                        ed.valide_achat as status,
                        ed.created_at,
                        ed.updated_at,
                        ip.code_projet,
                        ip.nom_client,
                        (SELECT MAX(am.date_achat) FROM achats_materiaux am WHERE am.expression_id = ed.idExpression AND am.designation = ed.designation) as date_achat
                    FROM expression_dym ed
                    JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                    WHERE ed.idExpression = :expression_id
                    AND ed.designation = :designation
                    LIMIT 1";
    }

    $orderStmt = $pdo->prepare($orderQuery);
    $orderStmt->bindParam(':expression_id', $expressionId);
    $orderStmt->bindParam(':designation', $designation);
    $orderStmt->execute();
    
    $orderInfo = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$orderInfo) {
        echo json_encode([
            'success' => false,
            'message' => 'Aucune commande trouvée avec ces critères'
        ]);
        exit();
    }

    // 2. Récupérer l'historique des commandes liées (pour les commandes partielles par exemple)
    $historyQuery = "SELECT 
                        am.id,
                        am.expression_id,
                        am.designation,
                        am.quantity,
                        am.unit,
                        am.prix_unitaire,
                        am.fournisseur,
                        am.date_achat,
                        am.date_reception,
                        am.status,
                        am.is_partial,
                        am.parent_id,
                        am.notes
                    FROM achats_materiaux am
                    WHERE am.expression_id = :expression_id
                    AND am.designation = :designation
                    ORDER BY am.date_achat DESC";

    $historyStmt = $pdo->prepare($historyQuery);
    $historyStmt->bindParam(':expression_id', $expressionId);
    $historyStmt->bindParam(':designation', $designation);
    $historyStmt->execute();
    
    $orderHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Retourner les données
    echo json_encode([
        'success' => true,
        'order_info' => $orderInfo,
        'order_history' => $orderHistory,
        'is_system' => $isSystem
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
    exit();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
    exit();
}
?>