<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

// Connexion à la base de données
include_once '../../../database/connection.php'; 

try {
    // Requête pour inclure l'ID du produit
    $sql = "SELECT 
                p.id,
                p.barcode, 
                p.product_name, 
                c.libelle as category_name, 
                p.quantity, 
                COALESCE(p.quantity_reserved, 0) as quantity_reserved, 
                COALESCE(p.unit, 'unité') as unit
            FROM products p
            LEFT JOIN categories c ON p.category = c.id
            ORDER BY p.product_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur de base de données', 
        'message' => $e->getMessage()
    ]);
    exit();
}
?>