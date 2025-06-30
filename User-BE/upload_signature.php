<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit();
}

include_once '../database/connection.php'; 

if (isset($_FILES['signature']) && $_FILES['signature']['error'] === 0) {
    $user_id = $_SESSION['user_id'];
    $file_name = $_FILES['signature']['name'];
    $file_tmp = $_FILES['signature']['tmp_name'];
    $destination = "../uploads/" . $file_name;

    if (move_uploaded_file($file_tmp, $destination)) {
        $stmt = $pdo->prepare("UPDATE users_exp SET signature = :signature WHERE id = :id");
        $stmt->bindParam(':signature', $file_name);
        $stmt->bindParam(':id', $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'signature' => $file_name]);
        } else {
            echo json_encode(['error' => 'Échec de la mise à jour']);
        }
    } else {
        echo json_encode(['error' => 'Erreur lors du téléchargement']);
    }
} else {
    echo json_encode(['error' => 'Aucun fichier téléchargé']);
}
