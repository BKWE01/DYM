<?php
header('Content-Type: application/json');
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette ressource.'
    ]);
    exit;
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    
    if (empty($query) || strlen($query) < 2) {
        echo json_encode([
            'success' => false,
            'message' => 'Veuillez fournir une requête de recherche d\'au moins 2 caractères.'
        ]);
        exit;
    }
    
    // Requête modifiée pour récupérer uniquement les produits reçus
    // Il y a deux façons de vérifier si un produit a été reçu:
    // 1. Via achats_materiaux avec status = 'reçu'
    // 2. Via expression_dym avec valide_achat = 'reçu'
    $sql = "
        SELECT DISTINCT p.id, p.barcode, p.product_name, p.quantity, p.unit, c.libelle as category_name
        FROM products p
        LEFT JOIN categories c ON p.category = c.id
        WHERE (p.product_name LIKE :query OR p.barcode LIKE :query)
        AND (
            -- Produit présent dans achats_materiaux avec statut reçu
            EXISTS (
                SELECT 1 FROM achats_materiaux am 
                WHERE LOWER(am.designation) = LOWER(p.product_name) 
                AND am.status = 'reçu'
            )
            OR
            -- Produit présent dans expression_dym avec valide_achat = reçu
            EXISTS (
                SELECT 1 FROM expression_dym ed 
                WHERE LOWER(ed.designation) = LOWER(p.product_name) 
                AND ed.valide_achat = 'reçu'
            )
        )
        ORDER BY p.product_name ASC
        LIMIT 15
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['query' => '%' . $query . '%']);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
    
} catch (PDOException $e) {
    error_log('Erreur dans api_search_products.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la recherche des produits: ' . $e->getMessage()
    ]);
}