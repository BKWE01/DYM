<?php
// Désactiver l'affichage des erreurs pour éviter de corrompre la sortie JSON
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure le helper de date s'il existe
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

    // Récupérer l'ID de l'utilisateur et son rôle
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';

    // Si l'utilisateur est super_admin, montrer tous les projets
    if ($userRole === 'super_admin') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT ip.* 
            FROM identification_projet ip
            JOIN expression_dym e ON ip.idExpression = e.idExpression
            LEFT JOIN project_status ps ON ip.idExpression = ps.idExpression
            WHERE 
                (ps.status IS NULL OR ps.status != 'completed')
                AND ip.created_at >= :system_start_date
            ORDER BY ip.created_at DESC
        ");

        $stmt->bindParam(':system_start_date', $systemStartDate);
    } else {
        // Pour les utilisateurs normaux, montrer tous les projets
        // sans filtrer par user_emet puisque ce sont des IDs différents
        $stmt = $pdo->prepare("
            SELECT DISTINCT ip.* 
            FROM identification_projet ip
            JOIN expression_dym e ON ip.idExpression = e.idExpression
            LEFT JOIN project_status ps ON ip.idExpression = ps.idExpression
            WHERE 
                (ps.status IS NULL OR ps.status != 'completed')
                AND ip.created_at >= :system_start_date
            ORDER BY ip.created_at DESC
        ");

        $stmt->bindParam(':system_start_date', $systemStartDate);
    }

    $stmt->execute();

    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'projects' => $projects,
        'system_start_date' => $systemStartDate,
        'user_id' => $userId,
        'user_role' => $userRole
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>