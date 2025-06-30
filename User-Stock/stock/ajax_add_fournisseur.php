<?php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Connexion à la base de données
include_once '../../database/connection.php'; 

// Récupérer et décoder les données JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Vérifier si les données nécessaires sont présentes
if (!isset($data['action']) || $data['action'] !== 'add' || !isset($data['nom']) || empty($data['nom'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

// Récupérer les données du formulaire
$nom = trim($data['nom']);
$email = isset($data['email']) ? trim($data['email']) : '';
$telephone = isset($data['telephone']) ? trim($data['telephone']) : '';
$adresse = isset($data['adresse']) ? trim($data['adresse']) : '';
$categorie = isset($data['categorie']) ? $data['categorie'] : null;
$notes = isset($data['notes']) ? trim($data['notes']) : '';

try {
    // Vérifier si le fournisseur existe déjà
    $checkQuery = "SELECT * FROM fournisseurs WHERE nom = :nom";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindParam(':nom', $nom);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Un fournisseur avec ce nom existe déjà.']);
        exit();
    }

    // Insérer le nouveau fournisseur
    $insertQuery = "INSERT INTO fournisseurs (nom, email, telephone, adresse, categorie, notes, created_by) 
                  VALUES (:nom, :email, :telephone, :adresse, :categorie, :notes, :created_by)";
    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->bindParam(':nom', $nom);
    $insertStmt->bindParam(':email', $email);
    $insertStmt->bindParam(':telephone', $telephone);
    $insertStmt->bindParam(':adresse', $adresse);
    $insertStmt->bindParam(':categorie', $categorie);
    $insertStmt->bindParam(':notes', $notes);
    $insertStmt->bindParam(':created_by', $user_id);
    $insertStmt->execute();

    // Récupérer les infos du fournisseur nouvellement créé
    $newFournisseurId = $pdo->lastInsertId();
    $newFournisseurQuery = "SELECT * FROM fournisseurs WHERE id = :id";
    $newFournisseurStmt = $pdo->prepare($newFournisseurQuery);
    $newFournisseurStmt->bindParam(':id', $newFournisseurId);
    $newFournisseurStmt->execute();
    $fournisseur = $newFournisseurStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Fournisseur ajouté avec succès!',
        'fournisseur' => $fournisseur
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout du fournisseur: ' . $e->getMessage()]);
}