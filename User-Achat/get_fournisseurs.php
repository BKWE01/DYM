<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Récupération des noms de fournisseurs
    $query = "SELECT nom FROM fournisseurs ORDER BY nom ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $fournisseurs = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Retourner la liste des fournisseurs en format JSON
    header('Content-Type: application/json');
    echo json_encode($fournisseurs);

} catch (PDOException $e) {
    // En cas d'erreur, renvoyer un tableau vide pour éviter de bloquer l'interface
    header('Content-Type: application/json');
    error_log("Erreur lors de la récupération des fournisseurs: " . $e->getMessage());
    echo json_encode([]);
}