<?php
// get_designations.php

// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Requête pour récupérer les désignations et unités
    $stmt = $pdo->prepare("
    SELECT p.product_name, p.unit, c.libelle as type
    FROM products p
    JOIN categories c ON p.category = c.id
    ");
    $stmt->execute();

    // Récupérer les résultats
    $designations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Renvoyer les résultats au format JSON
    echo json_encode($designations);

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}

$conn = null;
?>
