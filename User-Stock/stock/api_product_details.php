<?php
header('Content-Type: application/json');

// Activation de l'affichage des erreurs pour le débogage
ini_set('display_errors', 0);
error_reporting(0);

// Connexion à la base de données
include_once '../../database/connection.php'; 

$id = $_GET['id'] ?? '';

if (!$id) {
    echo json_encode(['error' => 'ID du produit non fourni']);
    exit;
}

try {
    // Récupérer les informations du produit
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['error' => 'Produit non trouvé']);
        exit;
    }

    // Récupérer les mouvements de stock
    $stmt = $pdo->prepare("SELECT * FROM stock_movement WHERE product_id = :id ORDER BY date DESC");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Séparer les entrées et les sorties
    $entries = array_filter($movements, function($m) { return $m['movement_type'] == 'entry'; });
    $outputs = array_filter($movements, function($m) { return $m['movement_type'] == 'output'; });

    $result = [
        'product' => $product,
        'entries' => array_values($entries),
        'outputs' => array_values($outputs)
    ];

    echo json_encode($result);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des données: ' . $e->getMessage()]);
}

// Pas besoin de ces lignes avec PDO
// $stmt->close();
// $pdo->close();
