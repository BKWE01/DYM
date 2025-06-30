<?php
header('Content-Type: application/json');

// Récupération des données JSON
$data = json_decode(file_get_contents('php://input'), true);

$success = true;
$message = '';

// Connexion à la base de données
include_once '../../database/connection.php'; 

try {
    // Début de la transaction
    $pdo->beginTransaction();

    // Préparation de la requête
    $stmt = $pdo->prepare("INSERT INTO categories (libelle, code) VALUES (:libelle, :code)");

    // Insertion de chaque catégorie
    foreach ($data as $category) {
        $result = $stmt->execute([
            ':libelle' => $category['libelle'],
            ':code' => $category['code']
        ]);
        
        if (!$result) {
            throw new Exception("Erreur lors de l'insertion de la catégorie");
        }
    }

    // Validation de la transaction
    $pdo->commit();
    $message = 'Catégories enregistrées avec succès';

} catch (PDOException $e) {
    // En cas d'erreur de connexion à la base de données
    $success = false;
    $message = 'Erreur de connexion à la base de données: ' . $e->getMessage();
} catch (Exception $e) {
    // En cas d'erreur lors de l'insertion
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $success = false;
    $message = 'Erreur : ' . $e->getMessage();
}

// Envoi de la réponse
echo json_encode(['success' => $success, 'message' => $message]);
