<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';
include_once '../../../include/date_helper.php';

// Définir le type de contenu
header('Content-Type: application/json');

try {
    // Récupérer les projets avec des commandes annulées
    $query = "SELECT 
        ip.idExpression,
        ip.code_projet,
        ip.nom_client,
        COUNT(ed.id) AS canceled_count,
        MAX(ed.updated_at) AS last_canceled
    FROM expression_dym ed
    JOIN identification_projet ip ON ed.idExpression = ip.idExpression
    WHERE ed.valide_achat = 'annulé'
    GROUP BY ip.idExpression, ip.code_projet, ip.nom_client
    ORDER BY last_canceled DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'projects' => $projects
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}
?>