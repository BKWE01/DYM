<?php
// Enregistrer dans le fichier check_reserved_quantities.php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

// Récupérer l'ID du projet et du produit des paramètres GET
$projectId = $_GET['project_id'] ?? 0;
$productId = $_GET['product_id'] ?? 0;
$productName = $_GET['product_name'] ?? '';

header('Content-Type: application/json');

try {
    $result = [
        'success' => true,
        'project' => null,
        'product' => null,
        'reserved_quantities' => [
            'expression_dym' => null,
            'achats_materiaux' => null
        ],
        'transferts' => []
    ];

    // Récupérer les informations du projet
    if ($projectId > 0) {
        $projectQuery = "SELECT * FROM identification_projet WHERE id = :project_id";
        $projectStmt = $pdo->prepare($projectQuery);
        $projectStmt->execute(['project_id' => $projectId]);
        $result['project'] = $projectStmt->fetch(PDO::FETCH_ASSOC);
    }

    // Récupérer les informations du produit
    if ($productId > 0) {
        $productQuery = "SELECT * FROM products WHERE id = :product_id";
        $productStmt = $pdo->prepare($productQuery);
        $productStmt->execute(['product_id' => $productId]);
        $result['product'] = $productStmt->fetch(PDO::FETCH_ASSOC);

        if ($result['product']) {
            $productName = $result['product']['product_name'];
        }
    }

    // Si nous avons un projet et un nom de produit
    if ($result['project'] && $productName) {
        // Vérifier dans expression_dym
        $expressionQuery = "
            SELECT id, designation, quantity, quantity_reserved
            FROM expression_dym
            WHERE idExpression = :expression_id
            AND (
                LOWER(designation) = LOWER(:product_name)
                OR LOWER(designation) LIKE CONCAT('%', LOWER(:product_name), '%')
            )
        ";
        $expressionStmt = $pdo->prepare($expressionQuery);
        $expressionStmt->execute([
            'expression_id' => $result['project']['idExpression'],
            'product_name' => $productName
        ]);
        $result['reserved_quantities']['expression_dym'] = $expressionStmt->fetchAll(PDO::FETCH_ASSOC);

        // Vérifier dans achats_materiaux
        $achatsQuery = "
            SELECT id, designation, quantity, status, date_achat
            FROM achats_materiaux
            WHERE expression_id = :expression_id
            AND (
                LOWER(designation) = LOWER(:product_name)
                OR LOWER(designation) LIKE CONCAT('%', LOWER(:product_name), '%')
            )
            AND status IN ('commandé', 'en_cours')
        ";
        $achatsStmt = $pdo->prepare($achatsQuery);
        $achatsStmt->execute([
            'expression_id' => $result['project']['idExpression'],
            'product_name' => $productName
        ]);
        $result['reserved_quantities']['achats_materiaux'] = $achatsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les transferts associés
        $transfertsQuery = "
            SELECT t.*, 
                   p.product_name,
                   sp.nom_client AS source_project, 
                   dp.nom_client AS destination_project
            FROM transferts t
            JOIN products p ON t.product_id = p.id
            JOIN identification_projet sp ON t.source_project_id = sp.id
            JOIN identification_projet dp ON t.destination_project_id = dp.id
            WHERE (t.source_project_id = :project_id OR t.destination_project_id = :project_id)
            AND p.id = :product_id
        ";
        $transfertsStmt = $pdo->prepare($transfertsQuery);
        $transfertsStmt->execute([
            'project_id' => $projectId,
            'product_id' => $productId
        ]);
        $result['transferts'] = $transfertsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}