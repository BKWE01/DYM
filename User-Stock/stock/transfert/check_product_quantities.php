<?php
// Enregistrer dans check_product_quantities.php
session_start();
include_once '../../../database/connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

$productId = $_GET['product_id'] ?? 0;
$projectId = $_GET['project_id'] ?? 0;

if (!$productId || !$projectId) {
    die("Veuillez spécifier un ID de produit et un ID de projet");
}

try {
    // Récupérer les informations du produit
    $productQuery = "SELECT * FROM products WHERE id = :product_id";
    $productStmt = $pdo->prepare($productQuery);
    $productStmt->execute(['product_id' => $productId]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les informations du projet
    $projectQuery = "SELECT * FROM identification_projet WHERE id = :project_id";
    $projectStmt = $pdo->prepare($projectQuery);
    $projectStmt->execute(['project_id' => $projectId]);
    $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier les quantités réservées
    $checkExpressionSql = "
        SELECT id, designation, quantity_reserved 
        FROM expression_dym 
        WHERE idExpression = :expression_id 
        AND (
            LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
            OR LOWER(TRIM(designation)) LIKE CONCAT('%', LOWER(TRIM(:product_name)), '%')
        )
    ";
    $checkExpressionStmt = $pdo->prepare($checkExpressionSql);
    $checkExpressionStmt->execute([
        'expression_id' => $project['idExpression'],
        'product_name' => $product['product_name']
    ]);
    $expressions = $checkExpressionStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Vérifier les quantités dans achats_materiaux
    $checkAchatsSql = "
        SELECT id, designation, quantity, status
        FROM achats_materiaux 
        WHERE expression_id = :expression_id 
        AND (
            LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
            OR LOWER(TRIM(designation)) LIKE CONCAT('%', LOWER(TRIM(:product_name)), '%')
        )
    ";
    $checkAchatsStmt = $pdo->prepare($checkAchatsSql);
    $checkAchatsStmt->execute([
        'expression_id' => $project['idExpression'],
        'product_name' => $product['product_name']
    ]);
    $achats = $checkAchatsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Afficher les résultats en format JSON
    header('Content-Type: application/json');
    echo json_encode([
        'product' => $product,
        'project' => $project,
        'expressions' => $expressions,
        'achats' => $achats
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}