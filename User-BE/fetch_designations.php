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
    
    $sql = "SELECT id, designation, unit, type FROM designations";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $designations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($designations);
} catch (PDOException $e) {
    echo 'Erreur de connexion : ' . $e->getMessage();
}
?>
