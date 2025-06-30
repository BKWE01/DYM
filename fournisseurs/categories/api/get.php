<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de la catégorie non spécifié']);
    exit();
}

$id = $_GET['id'];

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Récupérer les informations de la catégorie
    $query = "SELECT * FROM categories_fournisseurs WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($category) {
        // Vérifier si le fichier icône existe encore
        if (!empty($category['icon_path']) && !file_exists($category['icon_path'])) {
            // Mettre à jour la base de données si le fichier n'existe plus
            $updateQuery = "UPDATE categories_fournisseurs SET icon_path = NULL WHERE id = :id";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(':id', $id);
            $updateStmt->execute();
            $category['icon_path'] = null;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'category' => $category]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Catégorie non trouvée']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
