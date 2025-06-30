<?php
// API pour récupérer les appels de fonds urgents
header('Content-Type: application/json');
session_start();

require_once '../../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

try {
    // Récupérer les appels en attente depuis plus de 48h
    $query = "
        SELECT 
            af.*,
            u.name as demandeur_nom,
            CASE 
                WHEN af.date_creation >= NOW() - INTERVAL 1 HOUR THEN CONCAT(FLOOR(TIMESTAMPDIFF(MINUTE, af.date_creation, NOW())), ' min')
                WHEN af.date_creation >= NOW() - INTERVAL 1 DAY THEN CONCAT(FLOOR(TIMESTAMPDIFF(HOUR, af.date_creation, NOW())), ' h')
                WHEN af.date_creation >= NOW() - INTERVAL 1 WEEK THEN CONCAT(FLOOR(TIMESTAMPDIFF(DAY, af.date_creation, NOW())), ' j')
                ELSE DATE_FORMAT(af.date_creation, '%d/%m/%Y')
            END as time_ago,
            TIMESTAMPDIFF(HOUR, af.date_creation, NOW()) as hours_pending
        FROM appels_fonds af
        LEFT JOIN users_exp u ON af.user_id = u.id
        WHERE af.statut IN ('en_attente', 'partiellement_valide')
        AND af.date_creation <= NOW() - INTERVAL 48 HOUR
        ORDER BY af.date_creation ASC
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $appels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'appels' => $appels
    ]);
    
} catch (Exception $e) {
    error_log("Erreur appels urgents: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des appels urgents'
    ]);
}
?>