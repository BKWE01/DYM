<?php
include_once '../database/connection.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    
    // Chemin du dossier de téléchargement
    $uploadDir = '../uploads/';
    $uploadFile = $uploadDir . basename($file['name']);

    // Vérifiez si le fichier a été téléchargé correctement
    if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
        // Mettre à jour l'image de profil dans la base de données
        $stmt = $pdo->prepare("UPDATE users_exp SET profile_image = :profile_image WHERE id = :id");
        $stmt->bindParam(':profile_image', $file['name']);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();

        // Réponse JSON
        echo json_encode(['success' => true, 'image' => $file['name']]);
    } else {
        echo json_encode(['error' => 'Erreur lors du téléchargement']);
    }
} else {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Requête invalide']);
}
?>
