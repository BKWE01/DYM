<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

// Récupérer le nom du fournisseur depuis la requête
$fournisseur = $_POST['fournisseur'] ?? '';

if (empty($fournisseur)) {
    echo json_encode(['success' => false, 'message' => 'Nom du fournisseur non spécifié']);
    exit();
}

try {
    // Vérifier si le fournisseur existe déjà
    $query = "SELECT id FROM fournisseurs WHERE LOWER(nom) = LOWER(:nom)";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':nom', $fournisseur);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Le fournisseur existe déjà
        echo json_encode(['success' => true, 'exists' => true, 'id' => $result['id']]);
    } else {
        // Le fournisseur n'existe pas
        echo json_encode(['success' => true, 'exists' => false]);
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la vérification du fournisseur: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la vérification du fournisseur']);
}
?>