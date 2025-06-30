<?php
// API pour récupérer les activités récentes
header('Content-Type: application/json');
session_start();

require_once '../../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

try {
    // Récupérer les activités récentes
    $query = "
        SELECT 
            afh.*,
            af.code_appel,
            u.name as user_nom,
            CASE 
                WHEN afh.created_at >= NOW() - INTERVAL 1 HOUR THEN CONCAT(FLOOR(TIMESTAMPDIFF(MINUTE, afh.created_at, NOW())), ' min')
                WHEN afh.created_at >= NOW() - INTERVAL 1 DAY THEN CONCAT(FLOOR(TIMESTAMPDIFF(HOUR, afh.created_at, NOW())), ' h')
                WHEN afh.created_at >= NOW() - INTERVAL 1 WEEK THEN CONCAT(FLOOR(TIMESTAMPDIFF(DAY, afh.created_at, NOW())), ' j')
                ELSE DATE_FORMAT(afh.created_at, '%d/%m/%Y')
            END as time_ago,
            CASE afh.action
                WHEN 'creation' THEN 'Création'
                WHEN 'validate_elements' THEN 'Validation partielle'
                WHEN 'reject_elements' THEN 'Rejet partiel'
                WHEN 'validate_all' THEN 'Validation complète'
                WHEN 'reject_all' THEN 'Rejet complet'
                ELSE afh.action
            END as action_libelle
        FROM appels_fonds_historique afh
        LEFT JOIN appels_fonds af ON afh.appel_fonds_id = af.id
        LEFT JOIN users_exp u ON afh.user_id = u.id
        ORDER BY afh.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);
    
} catch (Exception $e) {
    error_log("Erreur activités: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des activités'
    ]);
}
?>