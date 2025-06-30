<?php
// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Récupérer les produits avec leur quantité en stock et catégorie
    $stmt = $pdo->prepare("
        SELECT p.id, p.barcode, p.product_name, p.quantity, p.unit, c.libelle as type 
        FROM products p
        LEFT JOIN categories c ON p.category = c.id
        ORDER BY p.product_name ASC
    ");
    $stmt->execute();

    // Récupérer tous les produits
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Répondre avec les produits au format JSON
    header('Content-Type: application/json');
    echo json_encode($products);
    
} catch (PDOException $e) {
    // En cas d'erreur, retourner un message d'erreur
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>