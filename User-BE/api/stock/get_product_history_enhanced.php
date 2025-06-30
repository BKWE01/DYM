<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentification requise'
    ]);
    exit();
}

// Vérifier si l'ID du produit est spécifié et valide
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || intval($_GET['id']) <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID de produit manquant ou invalide'
    ]);
    exit();
}

$productId = intval($_GET['id']);
$period = isset($_GET['period']) ? $_GET['period'] : 'all'; // all, month, year

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Vérifier d'abord si le produit existe dans la table products
    $checkProduct = "SELECT id, product_name, unit FROM products WHERE id = :product_id";
    $checkStmt = $pdo->prepare($checkProduct);
    $checkStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $checkStmt->execute();

    $product = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé'
        ]);
        exit();
    }

    // Construction de la requête avec filtre de période
    $sql = "SELECT 
                sm.id,
                sm.product_id,
                sm.quantity,
                sm.movement_type,
                sm.provenance,
                sm.fournisseur,
                sm.nom_projet,
                sm.destination,
                sm.demandeur,
                sm.invoice_id,
                sm.notes,
                sm.date,
                :unit as unit
            FROM stock_movement sm
            WHERE sm.product_id = :product_id";

    $params = [
        ':product_id' => $productId,
        ':unit' => $product['unit']
    ];

    // Ajouter la condition de période si nécessaire
    if ($period === 'month') {
        $sql .= " AND sm.date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    } elseif ($period === 'year') {
        $sql .= " AND sm.date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
    }

    $sql .= " ORDER BY sm.date DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si aucun mouvement n'est trouvé, renvoyer un tableau vide mais avec succès
    if (empty($movements)) {
        echo json_encode([
            'success' => true,
            'product' => $product,
            'history' => [],
            'total_count' => 0,
            'message' => 'Aucun mouvement trouvé pour ce produit'
        ]);
        exit();
    }

    // Enrichir chaque mouvement avec des informations supplémentaires
    foreach ($movements as &$movement) {
        // Formater le type de mouvement pour l'affichage
        if ($movement['movement_type'] === 'entry') {
            $movement['movement_type_display'] = 'Entrée';
            $movement['movement_color'] = 'green';
        } else if ($movement['movement_type'] === 'output') {
            $movement['movement_type_display'] = 'Sortie';
            $movement['movement_color'] = 'red';
        } else if ($movement['movement_type'] === 'transfer') {
            $movement['movement_type_display'] = 'Transfert';
            $movement['movement_color'] = 'blue';
        } else if ($movement['movement_type'] === 'return') {
            $movement['movement_type_display'] = 'Retour';
            $movement['movement_color'] = 'purple';
        } else {
            $movement['movement_type_display'] = ucfirst($movement['movement_type']);
            $movement['movement_color'] = 'gray';
        }

        // Si invoice_id existe, récupérer les détails de la facture
        if ($movement['invoice_id']) {
            $invoiceQuery = "SELECT invoice_number, file_path FROM invoices WHERE id = :invoice_id";
            $invoiceStmt = $pdo->prepare($invoiceQuery);
            $invoiceStmt->bindParam(':invoice_id', $movement['invoice_id'], PDO::PARAM_INT);
            $invoiceStmt->execute();
            $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);

            if ($invoice) {
                $movement['invoice_number'] = $invoice['invoice_number'];
                $movement['invoice_file'] = $invoice['file_path'];
            }
        }

        // Formater la date pour un affichage convivial
        $date = new DateTime($movement['date']);
        $movement['formatted_date'] = $date->format('d/m/Y H:i');

        // Calculer l'impact sur le stock (positif ou négatif)
        if ($movement['movement_type'] === 'entry' || $movement['movement_type'] === 'return') {
            $movement['stock_impact'] = '+' . $movement['quantity'];
            $movement['impact_color'] = 'green';
        } else {
            $movement['stock_impact'] = '-' . $movement['quantity'];
            $movement['impact_color'] = 'red';
        }
    }

    // Calculer des statistiques supplémentaires
    $totalEntries = 0;
    $totalOutputs = 0;
    $lastMovementDate = null;

    foreach ($movements as $movement) {
        if ($movement['movement_type'] === 'entry') {
            $totalEntries += $movement['quantity'];
        } else if ($movement['movement_type'] === 'output') {
            $totalOutputs += $movement['quantity'];
        }

        if (!$lastMovementDate || strtotime($movement['date']) > strtotime($lastMovementDate)) {
            $lastMovementDate = $movement['date'];
        }
    }

    $stats = [
        'total_entries' => $totalEntries,
        'total_outputs' => $totalOutputs,
        'net_change' => $totalEntries - $totalOutputs,
        'movement_count' => count($movements),
        'last_movement' => $lastMovementDate ? (new DateTime($lastMovementDate))->format('d/m/Y H:i') : null
    ];

    // Renvoyer les données de l'historique enrichies
    echo json_encode([
        'success' => true,
        'product' => $product,
        'history' => $movements,
        'total_count' => count($movements),
        'stats' => $stats,
        'period' => $period
    ]);

} catch (PDOException $e) {
    error_log("Erreur dans get_product_history_enhanced.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
    exit();
}
?>