<?php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si un ID de fournisseur est spécifié
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID fournisseur non spécifié']);
    exit();
}

$id = $_GET['id'];

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Récupérer les informations du fournisseur
    $query = "SELECT * FROM fournisseurs WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($supplier) {
        echo json_encode([
            'success' => true,
            'supplier' => $supplier
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Fournisseur non trouvé'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération du fournisseur: ' . $e->getMessage()
    ]);
}