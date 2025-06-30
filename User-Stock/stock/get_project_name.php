<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php'; 

// Récupérer le code projet
$projectCode = isset($_GET['project_code']) ? trim($_GET['project_code']) : '';

if (empty($projectCode)) {
    echo json_encode([
        'success' => false,
        'message' => 'Code projet manquant'
    ]);
    exit;
}

try {
    // Récupérer le nom du client associé au code projet
    $query = "
        SELECT nom_client 
        FROM identification_projet 
        WHERE code_projet = :code_projet
        LIMIT 1
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':code_projet' => $projectCode]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'success' => true,
            'code_projet' => $projectCode,
            'nom_client' => $result['nom_client']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Projet non trouvé'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}