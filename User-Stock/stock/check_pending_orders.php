<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php';

// Récupérer les paramètres de la requête
$productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$productName = isset($_GET['product_name']) ? $_GET['product_name'] : '';

if (empty($productName) && $productId === 0) {
    echo json_encode([
        'success' => false,
        'hasPendingOrders' => false,
        'message' => 'Paramètres manquants'
    ]);
    exit;
}

try {
    // Récupérer le produit par ID si présent
    if ($productId > 0) {
        $productStmt = $pdo->prepare("SELECT product_name FROM products WHERE id = :product_id");
        $productStmt->execute([':product_id' => $productId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $productName = $product['product_name'];
        }
    }

    if (empty($productName)) {
        echo json_encode([
            'success' => false,
            'hasPendingOrders' => false,
            'message' => 'Produit non trouvé'
        ]);
        exit;
    }

    // Préparer le terme de recherche
    $searchTerm = '%' . strtolower($productName) . '%';

    // Rechercher les commandes en attente qui pourraient correspondre au produit
    // MODIFICATION: Ne sélectionner que les produits avec statut 'validé' ou 'en_cours'
    $pendingOrdersStmt = $pdo->prepare("
    SELECT 
        am.id, 
        am.designation, 
        CASE 
            WHEN am.is_partial = 1 AND am.parent_id IS NOT NULL THEN 
                (SELECT original_quantity FROM achats_materiaux WHERE id = am.parent_id)
            WHEN am.is_partial = 1 THEN 
                am.original_quantity
            ELSE 
                am.quantity
        END as quantity,
        CASE
            WHEN b.idBesoin IS NOT NULL THEN 'SYS'
            ELSE ip.code_projet
        END as code_projet,
        CASE
            WHEN b.idBesoin IS NOT NULL THEN CONCAT('Demande ', COALESCE(d.client, 'Système'))
            ELSE ip.nom_client
        END as nom_client
    FROM achats_materiaux am
    LEFT JOIN identification_projet ip ON am.expression_id = ip.idExpression
    LEFT JOIN besoins b ON am.expression_id = b.idBesoin
    LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
    LEFT JOIN expression_dym ed ON am.expression_id = ed.idExpression AND LOWER(am.designation) = LOWER(ed.designation)
    WHERE am.status = 'commandé'
    AND (
        (b.idBesoin IS NOT NULL AND b.achat_status IN ('validé', 'en_cours'))
        OR 
        (ed.idExpression IS NOT NULL AND ed.valide_achat IN ('validé', 'en_cours'))
    )
    AND LOWER(am.designation) LIKE :search
    GROUP BY am.expression_id, am.designation
    ORDER BY am.date_achat ASC
    ");
    
    $pendingOrdersStmt->execute([':search' => $searchTerm]);
    $pendingOrders = $pendingOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer les statistiques des commandes en attente
    $ordersCount = count($pendingOrders);
    $totalQuantity = 0;

    foreach ($pendingOrders as $order) {
        $totalQuantity += floatval($order['quantity']);
    }

    echo json_encode([
        'success' => true,
        'hasPendingOrders' => ($ordersCount > 0),
        'ordersCount' => $ordersCount,
        'totalQuantity' => $totalQuantity,
        'orders' => $pendingOrders
    ]);

} catch (Exception $e) {
    error_log("Erreur dans check_pending_orders.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'hasPendingOrders' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}