<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Récupérer les données JSON envoyées par la requête
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'];
    $designation = $data['designation'];
    $unit = $data['unit'];
    $type = $data['type'];

    // Mettre à jour la désignation
    $sql = "UPDATE designations SET designation = ?, unit = ?, type = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$designation, $unit, $type, $id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo 'Erreur de connexion : ' . $e->getMessage();
}
?>
