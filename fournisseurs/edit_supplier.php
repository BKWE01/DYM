<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si les données requises sont fournies
if (!isset($_POST['id']) || empty($_POST['id']) || !isset($_POST['nom']) || empty($_POST['nom'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données incomplètes']);
    exit();
}

$id = $_POST['id'];
$nom = trim($_POST['nom']);
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
$adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Vérifier si un autre fournisseur a déjà ce nom
    $checkQuery = "SELECT * FROM fournisseurs WHERE nom = :nom AND id != :id";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindParam(':nom', $nom);
    $checkStmt->bindParam(':id', $id);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Un autre fournisseur avec ce nom existe déjà.']);
        exit();
    }

    // Mettre à jour le fournisseur
    $updateQuery = "UPDATE fournisseurs SET 
                 nom = :nom, 
                 email = :email, 
                 telephone = :telephone, 
                 adresse = :adresse, 
                 notes = :notes,
                 updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->bindParam(':nom', $nom);
    $updateStmt->bindParam(':email', $email);
    $updateStmt->bindParam(':telephone', $telephone);
    $updateStmt->bindParam(':adresse', $adresse);
    $updateStmt->bindParam(':notes', $notes);
    $updateStmt->bindParam(':id', $id);
    $updateStmt->execute();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Fournisseur mis à jour avec succès!']);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du fournisseur: ' . $e->getMessage()]);
}