<?php
// save_designation.php

// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Récupérer les données envoyées via POST
    $designation = $_POST['designation'];
    $unit = $_POST['unit'];
    $type = $_POST['type'];

    // Préparer la requête d'insertion
    $stmt = $pdo->prepare("INSERT INTO designations (designation, unit, type) VALUES (:designation, :unit, :type)");

    // Lier les paramètres de la requête aux valeurs récupérées
    $stmt->bindParam(':designation', $designation);
    $stmt->bindParam(':unit', $unit);
    $stmt->bindParam(':type', $type);

    // Exécuter la requête
    if ($stmt->execute()) {
        echo "Désignation enregistrée avec succès.";
    } else {
        echo "Erreur lors de l'enregistrement.";
    }

} catch (PDOException $e) {
    // Afficher un message d'erreur en cas d'exception
    echo "Erreur de connexion : " . $e->getMessage();
}

// Fermer la connexion (optionnel, PDO le fait automatiquement en fin de script)
$pdo = null;
?>
