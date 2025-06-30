<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si l'ID du fournisseur est fourni
if (!isset($_GET['fournisseur_id']) || empty($_GET['fournisseur_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID du fournisseur non spécifié']);
    exit();
}

$fournisseur_id = $_GET['fournisseur_id'];

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Récupérer les catégories du fournisseur
    $query = "SELECT categorie FROM fournisseur_categories WHERE fournisseur_id = :fournisseur_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':fournisseur_id', $fournisseur_id);
    $stmt->execute();

    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'categories' => $categories]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}