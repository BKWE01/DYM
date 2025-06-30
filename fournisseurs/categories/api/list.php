<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Récupérer toutes les catégories actives par défaut
    $activeOnly = isset($_GET['active_only']) ? filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN) : true;
    
    $query = "SELECT * FROM categories_fournisseurs";
    if ($activeOnly) {
        $query .= " WHERE active = 1";
    }
    $query .= " ORDER BY nom ASC";
    
    $stmt = $pdo->query($query);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter le nombre de fournisseurs pour chaque catégorie
    foreach ($categories as &$category) {
        $countQuery = "SELECT COUNT(*) FROM fournisseur_categories WHERE categorie = :categorie";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->bindParam(':categorie', $category['nom']);
        $countStmt->execute();
        $category['nb_fournisseurs'] = $countStmt->fetchColumn();
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'categories' => $categories]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}