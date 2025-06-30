<?php
// API pour gérer les notifications
header('Content-Type: application/json');
session_start();

require_once '../../database/connection.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$action = $_REQUEST['action'] ?? 'get_notifications';

try {
    switch ($action) {
        case 'get_notifications':
            // Récupérer les notifications
            $query = "
                SELECT 
                    nf.*,
                    af.code_appel,
                    CASE 
                        WHEN nf.created_at >= NOW() - INTERVAL 1 HOUR THEN CONCAT(FLOOR(TIMESTAMPDIFF(MINUTE, nf.created_at, NOW())), ' min')
                        WHEN nf.created_at >= NOW() - INTERVAL 1 DAY THEN CONCAT(FLOOR(TIMESTAMPDIFF(HOUR, nf.created_at, NOW())), ' h')
                        WHEN nf.created_at >= NOW() - INTERVAL 1 WEEK THEN CONCAT(FLOOR(TIMESTAMPDIFF(DAY, nf.created_at, NOW())), ' j')
                        ELSE DATE_FORMAT(nf.created_at, '%d/%m/%Y')
                    END as time_ago
                FROM notifications_fonds nf
                LEFT JOIN appels_fonds af ON nf.appel_fonds_id = af.id
                WHERE nf.user_id = ?
                ORDER BY nf.created_at DESC
                LIMIT 10
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);
            break;
            
        case 'mark_read':
            // Marquer une notification comme lue
            $notification_id = $_POST['id'] ?? null;
            
            if (!$notification_id) {
                throw new Exception('ID de notification manquant');
            }
            
            $query = "
                UPDATE notifications_fonds 
                SET lu = TRUE 
                WHERE id = ? AND user_id = ?
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$notification_id, $_SESSION['user_id']]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'mark_all_read':
            // Marquer toutes les notifications comme lues
            $query = "
                UPDATE notifications_fonds 
                SET lu = TRUE 
                WHERE user_id = ?
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'get_count':
            // Compter les notifications non lues
            $query = "
                SELECT COUNT(*) as count 
                FROM notifications_fonds 
                WHERE user_id = ? AND lu = FALSE
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
            break;
            
        default:
            throw new Exception('Action non reconnue');
    }
    
} catch (Exception $e) {
    error_log("Erreur notifications: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>