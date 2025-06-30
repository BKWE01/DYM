<?php
// fix_missing_stock_general.php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../../database/connection.php';

// Fonction pour enregistrer les événements dans un fichier de log
function logEvent($message)
{
    $logFile = 'dispatching_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Récupérer les données JSON du corps de la requête
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['source_movement_id']) || !isset($data['product_id']) || !isset($data['quantity'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$pdo->beginTransaction();

try {
    // Récupérer les informations nécessaires
    $sourceMovementId = $data['source_movement_id'];
    $productId = $data['product_id'];
    $quantity = $data['quantity'];
    $provenance = $data['provenance'] ?? '';
    $fournisseur = $data['fournisseur'] ?? '';
    $timestamp = $data['timestamp'] ?? null;

    // Récupérer les informations du produit
    $productStmt = $pdo->prepare("SELECT product_name FROM products WHERE id = :product_id");
    $productStmt->execute([':product_id' => $productId]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception("Produit non trouvé avec l'ID: $productId");
    }

    // Récupérer le mouvement source pour obtenir plus d'informations
    $sourceMovementStmt = $pdo->prepare("SELECT * FROM stock_movement WHERE id = :id");
    $sourceMovementStmt->execute([':id' => $sourceMovementId]);
    $sourceMovement = $sourceMovementStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sourceMovement) {
        throw new Exception("Mouvement source non trouvé avec l'ID: $sourceMovementId");
    }

    // Préparer la date pour le nouveau mouvement
    $dateStr = '';
    if ($timestamp) {
        // Utiliser un timestamp proche du mouvement d'origine mais légèrement ultérieur
        $date = new DateTime();
        $date->setTimestamp($timestamp);
        $date->modify('+10 seconds'); // Ajouter 10 secondes
        $dateStr = $date->format('Y-m-d H:i:s');
    } else {
        $dateStr = date('Y-m-d H:i:s'); // Date actuelle
    }

    // Créer un mouvement pour la quantité restante vers le stock général
    $insertStmt = $pdo->prepare("
        INSERT INTO stock_movement 
        (product_id, quantity, movement_type, provenance, nom_projet, destination, date, fournisseur, invoice_id) 
        VALUES (:product_id, :quantity, 'entry', :provenance, 'Stock général', 'Stock général', :date, :fournisseur, :invoice_id)
    ");

    $insertStmt->execute([
        ':product_id' => $productId,
        ':quantity' => $quantity,
        ':provenance' => $provenance,
        ':date' => $dateStr,
        ':fournisseur' => $fournisseur,
        ':invoice_id' => $sourceMovement['invoice_id']
    ]);

    $newMovementId = $pdo->lastInsertId();

    if (!$newMovementId) {
        throw new Exception("Erreur lors de la création du mouvement, lastInsertId() a retourné une valeur vide");
    }

    logEvent("CORRECTION MANUELLE: Mouvement de stock #$newMovementId créé pour le reste non dispatché de $quantity unités de {$product['product_name']}, lié au mouvement d'origine #$sourceMovementId");

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Entrée de stock général créée avec succès',
        'id' => $newMovementId
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    logEvent("ERREUR DE CORRECTION MANUELLE: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la création de l\'entrée: ' . $e->getMessage()
    ]);
}
?>