<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../../database/connection.php';

// Récupérer les paramètres de la requête
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$source = isset($_GET['source']) ? $_GET['source'] : 'expression_dym';

if ($id === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de commande non fourni'
    ]);
    exit;
}

try {
    // Variables pour stocker les résultats
    $material = null;
    $linkedOrders = [];

    // Récupérer les informations du matériau selon la source
    if ($source === 'besoins') {
        // Requête pour la table besoins
        $materialQuery = "SELECT 
                            b.*,
                            b.designation_article as designation,
                            b.caracteristique as unit,
                            b.qt_demande as initial_qt_acheter,
                            b.qt_stock as quantity_stock,
                            (b.qt_demande - b.qt_acheter) as qt_restante,
                            b.achat_status as valide_achat,
                            'SYS' as code_projet,
                            CONCAT('Demande ', d.client) as nom_client
                        FROM besoins b
                        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                        WHERE b.id = :id";
    } else {
        // Requête pour la table expression_dym (par défaut)
        $materialQuery = "SELECT ed.*, 
                            ip.code_projet, 
                            ip.nom_client
                        FROM expression_dym ed
                        JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                        WHERE ed.id = :id";
    }

    $materialStmt = $pdo->prepare($materialQuery);
    $materialStmt->execute([':id' => $id]);
    $material = $materialStmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        echo json_encode([
            'success' => false,
            'message' => 'Commande partielle non trouvée'
        ]);
        exit;
    }

    // Expression ID à utiliser dans les requêtes suivantes
    $expressionId = $source === 'besoins' ? $material['idBesoin'] : $material['idExpression'];
    $designation = $source === 'besoins' ? $material['designation_article'] : $material['designation'];

    // Récupérer les commandes liées pour ce matériau (achats partiels déjà effectués)
    $linkedOrdersQuery = "SELECT am.*
                        FROM achats_materiaux am
                        WHERE am.expression_id = :expression_id
                        AND am.designation = :designation
                        AND am.is_partial = 1
                        ORDER BY am.date_achat DESC";

    $linkedOrdersStmt = $pdo->prepare($linkedOrdersQuery);
    $linkedOrdersStmt->execute([
        ':expression_id' => $expressionId,
        ':designation' => $designation
    ]);
    $linkedOrders = $linkedOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer des statistiques supplémentaires pour cette commande partielle
    $initialQty = 0;
    $stockQty = 0;
    $remainingQty = 0;

    if ($source === 'besoins') {
        $initialQty = floatval($material['qt_demande']);
        $stockQty = floatval($material['qt_stock'] ?? 0);
        $remainingQty = $initialQty - floatval($material['qt_acheter']);
    } else {
        $initialQty = floatval($material['initial_qt_acheter'] ?? ($material['qt_acheter'] + $material['qt_restante']));
        $stockQty = floatval($material['quantity_stock'] ?? 0);
        $remainingQty = floatval($material['qt_restante']);
    }

    $progress = $initialQty > 0 ? round(($stockQty / $initialQty) * 100) : 0;

    $stats = [
        'initial_qty' => $initialQty,
        'stock_qty' => $stockQty,
        'remaining_qty' => $remainingQty,
        'progress' => $progress,
        'orders_count' => count($linkedOrders),
        'source' => $source
    ];

    echo json_encode([
        'success' => true,
        'material' => $material,
        'linkedOrders' => $linkedOrders,
        'stats' => $stats,
        'source' => $source
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}