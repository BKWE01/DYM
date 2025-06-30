<?php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si un fournisseur est spécifié
if (!isset($_GET['supplier']) || empty($_GET['supplier'])) {
    echo json_encode(['success' => false, 'message' => 'Fournisseur non spécifié']);
    exit();
}

$supplier = $_GET['supplier'];

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Récupérer les commandes pour ce fournisseur
    $query = "SELECT am.id, am.expression_id, am.designation, am.quantity, am.unit, 
               am.prix_unitaire, am.date_achat, am.status, ip.code_projet, ip.nom_client,
               (am.quantity * am.prix_unitaire) as montant_total
               FROM achats_materiaux am
               LEFT JOIN identification_projet ip ON am.expression_id = ip.idExpression
               WHERE am.fournisseur = :supplier
               ORDER BY am.date_achat DESC";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':supplier', $supplier);
    $stmt->execute();

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des commandes: ' . $e->getMessage()
    ]);
}