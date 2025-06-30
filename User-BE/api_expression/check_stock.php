<?php
// Fichier: /DYM MANUFACTURE/expressions_besoins/User-BE/api_expression/check_stock.php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé. Veuillez vous connecter.']);
    exit();
}

// Connexion à la base de données
include_once '../../database/connection.php';

// Récupérer les données envoyées
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['designation'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Données invalides. La désignation du produit est requise.'
    ]);
    exit();
}

$designation = $data['designation'];
$expressionId = $data['expressionId'] ?? null;
$currentQuantity = $data['currentQuantity'] ?? 0;

try {
    // Récupérer les informations de stock pour ce produit
    $stmt = $pdo->prepare("
    SELECT 
      p.product_name, 
      p.quantity, 
      COALESCE(p.quantity_reserved, 0) as quantity_reserved,
      p.unit_price,
      p.unit,
      c.libelle as type
    FROM products p
    LEFT JOIN categories c ON p.category = c.id
    WHERE p.product_name = :designation
  ");
    $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si le produit n'existe pas
    if (!$product) {
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé dans la base de données.',
            'exists' => false
        ]);
        exit();
    }

    // Si nous avons un ID d'expression, récupérer la quantité actuelle réservée pour ce produit
    $currentReservation = 0;
    if ($expressionId && !empty($expressionId)) {
        $stmt = $pdo->prepare("
      SELECT SUM(quantity) as current_reserved
      FROM expression_dym
      WHERE idExpression = :expressionId AND designation = :designation
    ");
        $stmt->bindParam(':expressionId', $expressionId, PDO::PARAM_STR);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->execute();
        $reservationInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reservationInfo && $reservationInfo['current_reserved']) {
            $currentReservation = $reservationInfo['current_reserved'];
        }
    }

    // Calculer la disponibilité réelle
    $totalReserved = $product['quantity_reserved'];
    $stockQuantity = $product['quantity'];

    // Ajuster pour exclure la quantité déjà réservée pour cette expression
    $adjustedReserved = $totalReserved - $currentReservation;
    $availableQuantity = $stockQuantity - $adjustedReserved;

    // Déterminer si la quantité demandée est disponible
    $requestedQuantity = isset($data['requestedQuantity']) ? floatval($data['requestedQuantity']) : 0;
    $isAvailable = $availableQuantity >= $requestedQuantity;

    // Construire la réponse
    $response = [
        'success' => true,
        'exists' => true,
        'product' => [
            'name' => $product['product_name'],
            'unit' => $product['unit'],
            'type' => $product['type'],
            'stockQuantity' => $stockQuantity,
            'totalReserved' => $totalReserved,
            'adjustedReserved' => $adjustedReserved,
            'availableQuantity' => $availableQuantity,
            'unitPrice' => $product['unit_price']
        ],
        'availability' => [
            'requested' => $requestedQuantity,
            'available' => $availableQuantity,
            'isAvailable' => $isAvailable
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}
?>