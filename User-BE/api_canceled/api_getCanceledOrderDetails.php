<?php
// Service Bureau d'Etude "/DYM MANUFACTURE/expressions_besoins/User-BE/api_canceled/api_getCanceledOrderDetails.php" 

header('Content-Type: application/json');

// Désactiver l'affichage des erreurs pour éviter de corrompre la sortie JSON
ini_set('display_errors', 0);
error_reporting(0);

// Connexion à la base de données
include_once '../../database/connection.php';

try {
    // Vérifier si l'ID est fourni
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID de commande manquant');
    }

    $order_id = $_GET['id'];

    // Déterminer si nous cherchons par ID de log ou par ID de commande
    $isLogId = true;

    // Si l'ID est numérique et supérieur à 1000, il s'agit probablement d'un ID de commande
    if (is_numeric($order_id) && $order_id > 1000) {
        $isLogId = false;
    }

    // Construire la condition de recherche selon le type d'ID
    $condition = ($isLogId) ? "co.id = :id" : "co.order_id = :id";

    // Requête principale pour récupérer les détails de la commande annulée
    $query = "
    SELECT 
        co.*,
        u.name AS canceled_by_name,
        ip.code_projet,
        ip.nom_client,
        ip.description_projet,
        ip.sitgeo,
        ip.chefprojet,
        ps.completed_by,
        ps.completed_at,
        u2.name AS completed_by_name,
        /* Priorité à expression_dym pour les informations de base */
        COALESCE(ed.quantity, CASE WHEN co.order_id = 0 THEN NULL ELSE am.quantity END) as quantity,
        COALESCE(ed.unit, CASE WHEN co.order_id = 0 THEN NULL ELSE am.unit END) as unit,
        COALESCE(ed.prix_unitaire, CASE WHEN co.order_id = 0 THEN NULL ELSE am.prix_unitaire END) as prix_unitaire,
        COALESCE(ed.fournisseur, CASE WHEN co.order_id = 0 THEN NULL ELSE am.fournisseur END) as fournisseur,
        /* Information spécifique à achats_materiaux */
        CASE WHEN co.order_id = 0 THEN NULL ELSE am.notes END as notes,
        CASE WHEN co.order_id = 0 THEN NULL ELSE am.date_achat END as date_achat,
        CASE WHEN co.order_id = 0 THEN NULL ELSE uc.name END AS command_by_name
    FROM canceled_orders_log co
    LEFT JOIN users_exp u ON co.canceled_by = u.id
    LEFT JOIN identification_projet ip ON co.project_id = ip.idExpression
    LEFT JOIN project_status ps ON co.project_id = ps.idExpression
    LEFT JOIN users_exp u2 ON ps.completed_by = u2.id
    LEFT JOIN achats_materiaux am ON co.order_id = am.id
    LEFT JOIN users_exp uc ON am.user_achat = uc.id
    /* Ajout de la jointure avec expression_dym */
    LEFT JOIN expression_dym ed ON (co.project_id = ed.idExpression AND LOWER(co.designation) = LOWER(ed.designation))
    WHERE $condition
    LIMIT 1";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $order_id);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        // Si rien n'est trouvé, essayer l'autre méthode de recherche
        $altCondition = ($isLogId) ? "co.order_id = :id" : "co.id = :id";
        $query = str_replace($condition, $altCondition, $query);
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':id', $order_id);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$order) {
        throw new Exception('Commande annulée non trouvée');
    }

    // Récupérer la liste des autres commandes pour ce même produit/projet en utilisant expression_dym comme source principale
    $relatedQuery = "
    SELECT 
        am.id,
        am.expression_id,
        am.designation,
        am.quantity,
        am.unit,
        am.prix_unitaire,
        am.fournisseur,
        am.status,
        am.date_achat,
        am.date_reception,
        am.is_partial
    FROM expression_dym ed
    JOIN achats_materiaux am ON ed.idExpression = am.expression_id AND LOWER(ed.designation) = LOWER(am.designation)
    WHERE ed.idExpression = :project_id
    AND LOWER(ed.designation) = LOWER(:designation)
    AND (am.id != :order_id OR :order_id = 0)
    ORDER BY am.date_achat DESC";

    $relatedStmt = $pdo->prepare($relatedQuery);
    $relatedStmt->bindParam(':project_id', $order['project_id']);
    $relatedStmt->bindParam(':designation', $order['designation']);
    $relatedStmt->bindParam(':order_id', $order['order_id']);
    $relatedStmt->execute();

    $relatedOrders = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les dates pour l'affichage
    $order['canceled_at_formatted'] = date('d/m/Y H:i', strtotime($order['canceled_at']));
    if (isset($order['date_achat']) && $order['date_achat']) {
        $order['date_achat_formatted'] = date('d/m/Y', strtotime($order['date_achat']));
    } else {
        $order['date_achat_formatted'] = 'N/A';
    }
    
    if (isset($order['completed_at']) && $order['completed_at']) {
        $order['completed_at_formatted'] = date('d/m/Y', strtotime($order['completed_at']));
    } else {
        $order['completed_at_formatted'] = 'N/A';
    }

    // Formater les ordres liés
    $formattedRelatedOrders = [];
    foreach ($relatedOrders as $related) {
        $related['date_achat_formatted'] = isset($related['date_achat']) && $related['date_achat']
            ? date('d/m/Y', strtotime($related['date_achat']))
            : 'N/A';

        $related['date_reception_formatted'] = isset($related['date_reception']) && $related['date_reception']
            ? date('d/m/Y', strtotime($related['date_reception']))
            : 'N/A';

        $formattedRelatedOrders[] = $related;
    }

    // Calculer la valeur économisée pour cette commande
    $savedValue = 0;
    if (isset($order['prix_unitaire']) && isset($order['quantity']) && 
        is_numeric($order['prix_unitaire']) && is_numeric($order['quantity'])) {
        $savedValue = floatval($order['prix_unitaire']) * floatval($order['quantity']);
    }

    echo json_encode([
        'success' => true,
        'order' => $order,
        'related_orders' => $formattedRelatedOrders,
        'saved_value' => $savedValue
    ]);

} catch (PDOException $e) {
    error_log("Erreur de base de données dans api_getCanceledOrderDetails.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Exception dans api_getCanceledOrderDetails.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>