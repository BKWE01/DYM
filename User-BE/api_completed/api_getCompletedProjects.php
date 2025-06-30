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
    // Récupérer la date de début du système
    $systemStartDate = getSystemStartDate();

    // Récupérer tous les projets marqués comme terminés
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
            ps.status = 'completed'
            AND ip.created_at >= :system_start_date
        ORDER BY ps.completed_at DESC
    ");
    
    $stmt->bindParam(':system_start_date', $systemStartDate);
    $stmt->execute();
    
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($projects) > 0) {
        echo json_encode([
            'success' => true,
            'projects' => $projects,
            'system_start_date' => $systemStartDate
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'projects' => [],
            'message' => 'Aucun projet terminé trouvé.'
        ]);
    }

} catch (PDOException $e) {
    error_log("API getCompletedProjects - PDOException: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("API getCompletedProjects - Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>