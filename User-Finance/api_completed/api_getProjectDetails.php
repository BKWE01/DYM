<?php
header('Content-Type: application/json');

// Désactiver l'affichage des erreurs pour éviter de corrompre la sortie JSON
ini_set('display_errors', 0);
error_reporting(0);

// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure le helper de date
try {
    if (file_exists('../../include/date_helper.php')) {
        include_once '../../include/date_helper.php';
    } else if (file_exists('../../includes/date_helper.php')) {
        include_once '../../includes/date_helper.php';
    } else {
        // Définir une fonction de secours si le fichier n'existe pas
        function getSystemStartDate()
        {
            return '2025-03-24'; // Valeur par défaut
        }
    }
} catch (Exception $e) {
    // Définir une fonction de secours en cas d'erreur
    function getSystemStartDate()
    {
        return '2025-03-24'; // Valeur par défaut
    }
}

try {
    // Récupérer l'ID du projet
    $projectId = isset($_GET['project_id']) ? $_GET['project_id'] : '';

    if (empty($projectId)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de projet manquant'
        ]);
        exit;
    }

    // Récupérer la date de début du système
    $systemStartDate = getSystemStartDate();

    // Récupérer les détails du projet
    $stmt = $pdo->prepare("
        SELECT 
            ip.*, 
            ps.status as project_status,
            ps.completed_at,
            ps.completed_by,
            u.name as completed_by_name
        FROM identification_projet ip
        JOIN project_status ps ON ip.idExpression = ps.idExpression
        LEFT JOIN users_exp u ON ps.completed_by = u.id
        WHERE 
            ip.idExpression = :idExpression
            AND ip.created_at >= :system_start_date
        LIMIT 1
    ");
    
    $stmt->bindParam(':idExpression', $projectId);
    $stmt->bindParam(':system_start_date', $systemStartDate);
    $stmt->execute();
    
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($project) {
        echo json_encode([
            'success' => true,
            'project' => $project,
            'system_start_date' => $systemStartDate
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Projet non trouvé'
        ]);
    }

} catch (PDOException $e) {
    error_log("API getProjectDetails - PDOException: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("API getProjectDetails - Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>