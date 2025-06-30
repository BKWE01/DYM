<?php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier si la requête de recherche est fournie
if (!isset($_GET['query']) || empty($_GET['query'])) {
    echo json_encode(['success' => false, 'message' => 'Requête de recherche non fournie']);
    exit;
}

$query = trim($_GET['query']);

// Connexion à la base de données
include_once '../../../../database/connection.php';

try {
    // Rechercher les produits correspondants avec vérification des stocks négatifs
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.barcode, 
            p.product_name, 
            p.quantity, 
            p.unit, 
            p.quantity_reserved,
            c.libelle as category_name
        FROM products p
        LEFT JOIN categories c ON p.category = c.id
        WHERE 
            (p.product_name LIKE ? 
            OR p.barcode LIKE ? 
            OR CAST(p.id AS CHAR) = ?)
            AND p.quantity >= 0
        ORDER BY 
            CASE 
                WHEN p.barcode = ? THEN 0 
                WHEN p.product_name LIKE ? THEN 1 
                ELSE 2 
            END,
            p.product_name ASC
        LIMIT 10
    ");
    
    $searchTerm = "%$query%";
    $exactTerm = $query;
    $startTerm = "$query%";
    $stmt->execute([$searchTerm, $searchTerm, $query, $exactTerm, $startTerm]); 
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>