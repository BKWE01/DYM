<?php
header('Content-Type: application/json');
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette ressource.'
    ]);
    exit;
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    
    // Préparer la requête SQL de base
    $sql = "
        SELECT 
            id,
            idExpression,
            code_projet,
            nom_client,
            description_projet,
            chefprojet
        FROM 
            identification_projet
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($query)) {
        $sql .= " AND (code_projet LIKE :query 
                 OR nom_client LIKE :query 
                 OR description_projet LIKE :query
                 OR chefprojet LIKE :query)";
        $params['query'] = '%' . $query . '%';
    }
    
    // Par défaut, on limite aux projets les plus récents
    $sql .= " ORDER BY created_at DESC LIMIT 15";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'projects' => $projects
    ]);
    
} catch (PDOException $e) {
    error_log('Erreur dans api_search_all_projects.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la recherche des projets: ' . $e->getMessage()
    ]);
}