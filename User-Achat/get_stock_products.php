<?php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a le rôle de gestionnaire de stock
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'stock') {
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Récupérer le paramètre de désignation
$designation = $_GET['designation'] ?? '';

if (empty($designation)) {
    echo json_encode([]);
    exit();
}

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Rechercher des produits similaires dans le stock
    $query = "SELECT id, barcode, product_name, quantity, unit 
              FROM products 
              WHERE product_name LIKE :designation 
              ORDER BY product_name";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':designation', '%' . $designation . '%');
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($products);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>