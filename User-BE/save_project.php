<?php
session_start();
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Lire le JSON du corps de la requête
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !is_array($data)) {
        throw new Exception('Données invalides.');
    }

    // Préparer la requête d'insertion
    $stmt = $pdo->prepare("INSERT INTO identification_projet (idExpression, code_projet, nom_client, description_projet, sitgeo, chefprojet) VALUES (:idExpression, :code_projet, :nom_client, :description_projet, :sitgeo, :chefprojet)");

    // Lier les paramètres
    $stmt->bindParam(':idExpression', $data['idExpression']);
    $stmt->bindParam(':code_projet', $data['code_projet']);
    $stmt->bindParam(':nom_client', $data['nom_client']);
    $stmt->bindParam(':description_projet', $data['description_projet']);
    $stmt->bindParam(':sitgeo', $data['sitgeo']);
    $stmt->bindParam(':chefprojet', $data['chefprojet']);
    
    // Exécuter la requête
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
