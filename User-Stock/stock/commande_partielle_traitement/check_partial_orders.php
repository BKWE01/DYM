<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../../database/connection.php';

// Récupérer les paramètres de la requête
$productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$productName = isset($_GET['product_name']) ? $_GET['product_name'] : '';

if (empty($productName) && $productId === 0) {
    echo json_encode([
        'success' => false,
        'hasPartialOrders' => false,
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
            'hasPartialOrders' => false,
            'message' => 'Produit non trouvé'
        ]);
        exit;
    }

    // Préparer le terme de recherche
    $searchTerm = '%' . strtolower($productName) . '%';

    // Requête unifiée pour récupérer les commandes partielles des deux sources
    $partialOrdersQuery = "
    (
        -- Commandes partielles depuis expression_dym
        SELECT 
            ed.id, 
            ed.idExpression, 
            ed.designation as designation, 
            ed.qt_acheter,
            ed.qt_restante,
            ed.initial_qt_acheter,
            ed.unit,
            ed.quantity_stock,
            ed.prix_unitaire, 
            ed.fournisseur, 
            ip.code_projet, 
            ip.nom_client,
            CASE 
                WHEN ed.initial_qt_acheter IS NOT NULL AND ed.initial_qt_acheter > 0 
                THEN ed.initial_qt_acheter - COALESCE(ed.qt_restante, 0)
                ELSE ed.qt_acheter
            END AS ordered_quantity,
            CASE 
                WHEN ed.initial_qt_acheter IS NOT NULL AND ed.initial_qt_acheter > 0 
                THEN COALESCE(ed.quantity_stock, 0) / ed.initial_qt_acheter * 100
                ELSE COALESCE(ed.quantity_stock, 0) / (ed.qt_acheter + COALESCE(ed.qt_restante, 0)) * 100
            END AS progress_percentage,
            'expression_dym' as source_table
        FROM expression_dym ed
        JOIN identification_projet ip ON ed.idExpression = ip.idExpression
        WHERE ed.qt_restante > 0
        AND ed.valide_achat = 'en_cours'
        AND LOWER(ed.designation) LIKE :search
    )
    
    UNION ALL
    
    (
        -- Commandes partielles depuis besoins
        SELECT 
            b.id, 
            b.idBesoin as idExpression, 
            b.designation_article as designation, 
            b.qt_acheter,
            (b.qt_demande - b.qt_acheter) as qt_restante,
            b.qt_demande as initial_qt_acheter,
            b.caracteristique as unit,
            b.qt_stock as quantity_stock,
            NULL as prix_unitaire, 
            NULL as fournisseur, 
            'SYS' as code_projet, 
            CONCAT('Demande ', d.client) as nom_client,
            b.qt_acheter as ordered_quantity,
            CASE 
                WHEN b.qt_demande > 0 
                THEN COALESCE(b.qt_stock, 0) / b.qt_demande * 100
                ELSE 0
            END AS progress_percentage,
            'besoins' as source_table
        FROM besoins b
        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
        WHERE (b.qt_demande - b.qt_acheter) > 0
        AND b.achat_status = 'en_cours'
        AND LOWER(b.designation_article) LIKE :search
    )
    
    ORDER BY source_table, idExpression, designation
    LIMIT 30";

    $partialOrdersStmt = $pdo->prepare($partialOrdersQuery);
    $partialOrdersStmt->execute([':search' => $searchTerm]);
    $partialOrders = $partialOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer les statistiques des commandes partielles
    $ordersCount = count($partialOrders);
    $totalQuantity = 0;

    foreach ($partialOrders as &$order) {
        // Utiliser directement la quantité initiale calculée dans la requête SQL
        $initialQty = floatval($order['initial_qt_acheter'] ?? ($order['qt_acheter'] + $order['qt_restante']));
        $stockQty = floatval($order['quantity_stock'] ?? 0);
        $remainingQty = floatval($order['qt_restante'] ?? 0);

        // S'assurer que la quantité initiale est toujours disponible pour l'affichage
        $order['initial_qty'] = $initialQty;
        $order['total_quantity'] = $initialQty;

        // Calculer combien il reste à recevoir (basé sur quantity_stock et la quantité totale)
        $stillNeeded = max(0, $initialQty - $stockQty);
        $order['still_needed'] = $stillNeeded;

        // Ajouter à la quantité totale restante
        $totalQuantity += $stillNeeded;
    }

    echo json_encode([
        'success' => true,
        'hasPartialOrders' => ($ordersCount > 0),
        'ordersCount' => $ordersCount,
        'totalQuantity' => $totalQuantity,
        'orders' => $partialOrders
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'hasPartialOrders' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}