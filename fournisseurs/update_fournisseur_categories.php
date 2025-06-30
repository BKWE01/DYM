<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si les données requises sont fournies
if (!isset($_POST['fournisseur_id']) || empty($_POST['fournisseur_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID du fournisseur non spécifié']);
    exit();
}

$fournisseur_id = $_POST['fournisseur_id'];
$categories = isset($_POST['categories']) ? $_POST['categories'] : [];

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Commencer une transaction
    $pdo->beginTransaction();

    // Supprimer toutes les catégories existantes pour ce fournisseur
    $deleteQuery = "DELETE FROM fournisseur_categories WHERE fournisseur_id = :fournisseur_id";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->bindParam(':fournisseur_id', $fournisseur_id);
    $deleteStmt->execute();

    // Insérer les nouvelles catégories
    if (!empty($categories)) {
        $insertQuery = "INSERT INTO fournisseur_categories (fournisseur_id, categorie) VALUES (:fournisseur_id, :categorie)";
        $insertStmt = $pdo->prepare($insertQuery);

        foreach ($categories as $categorie) {
            if (!empty($categorie)) {
                $insertStmt->bindParam(':fournisseur_id', $fournisseur_id);
                $insertStmt->bindParam(':categorie', $categorie);
                $insertStmt->execute();
            }
        }
    }

    // Valider la transaction
    $pdo->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Catégories mises à jour avec succès']);

} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    $pdo->rollBack();

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}