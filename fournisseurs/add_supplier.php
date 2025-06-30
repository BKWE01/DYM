<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Vérifier si les données requises sont fournies
if (!isset($_POST['nom']) || empty($_POST['nom'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nom du fournisseur non spécifié']);
    exit();
}

$nom = trim($_POST['nom']);
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
$adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Vérifier si le fournisseur existe déjà
    $checkQuery = "SELECT * FROM fournisseurs WHERE nom = :nom";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindParam(':nom', $nom);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Un fournisseur avec ce nom existe déjà.']);
        exit();
    }

    // Insérer le nouveau fournisseur
    $insertQuery = "INSERT INTO fournisseurs (nom, email, telephone, adresse, notes, created_by) 
                  VALUES (:nom, :email, :telephone, :adresse, :notes, :created_by)";
    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->bindParam(':nom', $nom);
    $insertStmt->bindParam(':email', $email);
    $insertStmt->bindParam(':telephone', $telephone);
    $insertStmt->bindParam(':adresse', $adresse);
    $insertStmt->bindParam(':notes', $notes);
    $insertStmt->bindParam(':created_by', $user_id);
    $insertStmt->execute();

    $fournisseur_id = $pdo->lastInsertId();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Fournisseur ajouté avec succès!', 'fournisseur_id' => $fournisseur_id]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout du fournisseur: ' . $e->getMessage()]);
}