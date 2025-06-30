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

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Vérifier d'abord si le produit existe dans la table products
    $checkProduct = "SELECT id FROM products WHERE id = :product_id";
    $checkStmt = $pdo->prepare($checkProduct);
    $checkStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé'
        ]);
        exit();
    }

    // Récupérer l'historique des mouvements du produit
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
                p.unit
            FROM stock_movement sm
            LEFT JOIN products p ON sm.product_id = p.id
            WHERE sm.product_id = :product_id
            ORDER BY sm.date DESC
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmt->execute();

    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si aucun mouvement n'est trouvé, renvoyer un tableau vide mais avec succès
    if (empty($movements)) {
        echo json_encode([
            'success' => true,
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
    }

    // Renvoyer les données de l'historique
    echo json_encode([
        'success' => true,
        'history' => $movements,
        'total_count' => count($movements)
    ]);

} catch (PDOException $e) {
    error_log("Erreur dans get_product_history.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
    exit();
}
?>