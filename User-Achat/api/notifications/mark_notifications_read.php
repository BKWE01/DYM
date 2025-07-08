<?php
/**
 * API AMÉLIORÉE pour marquer les notifications comme lues
 * Version 2.0 avec gestion optimisée des compteurs
 * 
 * Service Achat - DYM MANUFACTURE
 * Fichier: /User-Achat/api/mark_notifications_read.php
 */

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non connecté'
    ]);
    exit;
}

// Headers pour les réponses JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Connexion à la base de données
try {
    include_once dirname(__DIR__, 3) . '/database/connection.php';
    include_once dirname(__DIR__, 3) . '/include/date_helper.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données',
        'debug' => $e->getMessage()
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = [
    'success' => false,
    'message' => '',
    'data' => [],
    'counters' => []
];

try {
    // Récupérer les données POST
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Données JSON invalides');
    }

    $action = $input['action'] ?? '';

    switch ($action) {

        // ========== MARQUER UNE NOTIFICATION INDIVIDUELLE COMME LUE ==========
        case 'mark_single':
            $material_id = $input['material_id'] ?? 0;
            $expression_id = $input['expression_id'] ?? '';
            $source_table = $input['source_table'] ?? 'expression_dym';
            $notification_type = $input['notification_type'] ?? '';
            $designation = $input['designation'] ?? '';

            if (!$material_id || !$expression_id || !$notification_type) {
                throw new Exception('Paramètres manquants pour marquer la notification');
            }

            // Valider le type de notification
            $validTypes = ['urgent', 'recent', 'pending', 'remaining'];
            if (!in_array($notification_type, $validTypes)) {
                throw new Exception('Type de notification invalide');
            }

            // Valider la table source
            $validTables = ['expression_dym', 'besoins'];
            if (!in_array($source_table, $validTables)) {
                throw new Exception('Table source invalide');
            }

            $pdo->beginTransaction();

            // Insérer ou mettre à jour l'enregistrement de lecture
            $insertQuery = "INSERT INTO user_notifications_read 
                           (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
                           VALUES (:user_id, :notification_type, :material_id, :expression_id, :source_table, :designation, NOW())
                           ON DUPLICATE KEY UPDATE 
                           read_at = NOW(), updated_at = NOW()";

            $insertStmt = $pdo->prepare($insertQuery);
            $success = $insertStmt->execute([
                ':user_id' => $user_id,
                ':notification_type' => $notification_type,
                ':material_id' => $material_id,
                ':expression_id' => $expression_id,
                ':source_table' => $source_table,
                ':designation' => $designation
            ]);

            if (!$success) {
                throw new Exception('Erreur lors de l\'enregistrement de la notification lue');
            }

            $pdo->commit();

            // Récupérer les compteurs mis à jour
            $counters = getUpdatedCounters($pdo, $user_id);

            $response = [
                'success' => true,
                'message' => 'Notification marquée comme lue',
                'data' => [
                    'material_id' => $material_id,
                    'notification_type' => $notification_type,
                    'source_table' => $source_table
                ],
                'counters' => $counters
            ];
            break;

        // ========== MARQUER TOUTES LES NOTIFICATIONS D'UN TYPE COMME LUES ==========
        case 'mark_type':
            $notification_type = $input['notification_type'] ?? '';

            if (!$notification_type) {
                throw new Exception('Type de notification non spécifié');
            }

            $validTypes = ['urgent', 'recent', 'pending', 'remaining'];
            if (!in_array($notification_type, $validTypes)) {
                throw new Exception('Type de notification invalide');
            }

            $pdo->beginTransaction();

            $marked_count = 0;

            // Récupérer et marquer selon le type
            switch ($notification_type) {
                case 'urgent':
                    $marked_count = markUrgentNotifications($pdo, $user_id);
                    break;
                case 'recent':
                    $marked_count = markRecentNotifications($pdo, $user_id);
                    break;
                case 'pending':
                    $marked_count = markPendingNotifications($pdo, $user_id);
                    break;
                case 'remaining':
                    $marked_count = markRemainingNotifications($pdo, $user_id);
                    break;
            }

            $pdo->commit();

            // Récupérer les compteurs mis à jour
            $counters = getUpdatedCounters($pdo, $user_id);

            $response = [
                'success' => true,
                'message' => "Toutes les notifications de type '{$notification_type}' marquées comme lues",
                'data' => [
                    'notification_type' => $notification_type,
                    'marked_count' => $marked_count
                ],
                'counters' => $counters
            ];
            break;

        // ========== MARQUER TOUTES LES NOTIFICATIONS COMME LUES ==========
        case 'mark_all':
            $pdo->beginTransaction();

            $total_marked = 0;
            
            // Marquer tous les types
            $total_marked += markUrgentNotifications($pdo, $user_id);
            $total_marked += markRecentNotifications($pdo, $user_id);
            $total_marked += markPendingNotifications($pdo, $user_id);
            $total_marked += markRemainingNotifications($pdo, $user_id);

            $pdo->commit();

            // Récupérer les compteurs mis à jour
            $counters = getUpdatedCounters($pdo, $user_id);

            $response = [
                'success' => true,
                'message' => 'Toutes les notifications marquées comme lues',
                'data' => [
                    'marked_count' => $total_marked
                ],
                'counters' => $counters
            ];
            break;

        // ========== OBTENIR LE NOMBRE DE NOTIFICATIONS NON LUES ==========
        case 'get_unread_count':
            $counters = getUpdatedCounters($pdo, $user_id);

            $response = [
                'success' => true,
                'message' => 'Compteurs de notifications récupérés',
                'data' => [
                    'unread_count' => $counters['total']
                ],
                'counters' => $counters
            ];
            break;

        // ========== ACTUALISER LES COMPTEURS ==========
        case 'refresh_counters':
            $counters = getUpdatedCounters($pdo, $user_id);

            $response = [
                'success' => true,
                'message' => 'Compteurs actualisés',
                'data' => [],
                'counters' => $counters
            ];
            break;

        default:
            throw new Exception('Action non reconnue: ' . $action);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    $response = [
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'data' => [],
        'counters' => []
    ];

    // Log d'erreur pour debugging
    error_log("Erreur API notifications: " . $e->getMessage() . " - User: " . $user_id);
}

echo json_encode($response);

// ============================================================================
// FONCTIONS UTILITAIRES
// ============================================================================

/**
 * Marque les notifications urgentes comme lues
 */
function markUrgentNotifications($pdo, $user_id) {
    $marked_count = 0;

    // Matériaux urgents - expression_dym
    $query1 = "INSERT INTO user_notifications_read 
               (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
               SELECT :user_id, 'urgent', ed.id, ed.idExpression, 'expression_dym', ed.designation, NOW()
               FROM expression_dym ed
               JOIN identification_projet ip ON ed.idExpression = ip.idExpression
               WHERE ed.qt_acheter IS NOT NULL 
               AND ed.qt_acheter > 0 
               AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
               AND ed.created_at >= (SELECT DATE_SUB(NOW(), INTERVAL 6 MONTH))
               AND (ed.qt_acheter > 100 OR DATEDIFF(NOW(), ed.created_at) > 7)
               AND NOT EXISTS (
                   SELECT 1 FROM user_notifications_read unr 
                   WHERE unr.user_id = :user_id2 
                   AND unr.notification_type = 'urgent'
                   AND unr.material_id = ed.id
                   AND unr.source_table = 'expression_dym'
               )
               ON DUPLICATE KEY UPDATE read_at = NOW(), updated_at = NOW()";

    $stmt1 = $pdo->prepare($query1);
    $stmt1->execute([':user_id' => $user_id, ':user_id2' => $user_id]);
    $marked_count += $stmt1->rowCount();

    // Matériaux urgents - besoins
    $query2 = "INSERT INTO user_notifications_read 
               (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
               SELECT :user_id, 'urgent', b.id, b.idBesoin, 'besoins', b.designation_article, NOW()
               FROM besoins b
               WHERE b.qt_acheter IS NOT NULL 
               AND b.qt_acheter > 0 
               AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL)
               AND b.created_at >= (SELECT DATE_SUB(NOW(), INTERVAL 6 MONTH))
               AND (b.qt_acheter > 100 OR DATEDIFF(NOW(), b.created_at) > 7)
               AND NOT EXISTS (
                   SELECT 1 FROM user_notifications_read unr 
                   WHERE unr.user_id = :user_id2 
                   AND unr.notification_type = 'urgent'
                   AND unr.material_id = b.id
                   AND unr.source_table = 'besoins'
               )
               ON DUPLICATE KEY UPDATE read_at = NOW(), updated_at = NOW()";

    $stmt2 = $pdo->prepare($query2);
    $stmt2->execute([':user_id' => $user_id, ':user_id2' => $user_id]);
    $marked_count += $stmt2->rowCount();

    return $marked_count;
}

/**
 * Marque les notifications récentes comme lues
 */
function markRecentNotifications($pdo, $user_id) {
    $marked_count = 0;

    // Matériaux récents - expression_dym
    $query1 = "INSERT INTO user_notifications_read 
               (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
               SELECT :user_id, 'recent', ed.id, ed.idExpression, 'expression_dym', ed.designation, NOW()
               FROM expression_dym ed
               WHERE ed.qt_acheter IS NOT NULL 
               AND ed.qt_acheter > 0 
               AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
               AND ed.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               AND NOT EXISTS (
                   SELECT 1 FROM user_notifications_read unr 
                   WHERE unr.user_id = :user_id2 
                   AND unr.notification_type = 'recent'
                   AND unr.material_id = ed.id
                   AND unr.source_table = 'expression_dym'
               )
               ON DUPLICATE KEY UPDATE read_at = NOW(), updated_at = NOW()";

    $stmt1 = $pdo->prepare($query1);
    $stmt1->execute([':user_id' => $user_id, ':user_id2' => $user_id]);
    $marked_count += $stmt1->rowCount();

    // Matériaux récents - besoins
    $query2 = "INSERT INTO user_notifications_read 
               (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
               SELECT :user_id, 'recent', b.id, b.idBesoin, 'besoins', b.designation_article, NOW()
               FROM besoins b
               WHERE b.qt_acheter IS NOT NULL 
               AND b.qt_acheter > 0 
               AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL)
               AND b.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               AND NOT EXISTS (
                   SELECT 1 FROM user_notifications_read unr 
                   WHERE unr.user_id = :user_id2 
                   AND unr.notification_type = 'recent'
                   AND unr.material_id = b.id
                   AND unr.source_table = 'besoins'
               )
               ON DUPLICATE KEY UPDATE read_at = NOW(), updated_at = NOW()";

    $stmt2 = $pdo->prepare($query2);
    $stmt2->execute([':user_id' => $user_id, ':user_id2' => $user_id]);
    $marked_count += $stmt2->rowCount();

    return $marked_count;
}

/**
 * Marque les notifications en attente comme lues
 */
function markPendingNotifications($pdo, $user_id) {
    $marked_count = 0;

    // Matériaux en attente - expression_dym
    $query1 = "INSERT INTO user_notifications_read 
               (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
               SELECT :user_id, 'pending', ed.id, ed.idExpression, 'expression_dym', ed.designation, NOW()
               FROM expression_dym ed
               WHERE ed.qt_acheter IS NOT NULL 
               AND ed.qt_acheter > 0 
               AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
               AND ed.created_at >= (SELECT DATE_SUB(NOW(), INTERVAL 6 MONTH))
               AND NOT EXISTS (
                   SELECT 1 FROM user_notifications_read unr 
                   WHERE unr.user_id = :user_id2 
                   AND unr.notification_type = 'pending'
                   AND unr.material_id = ed.id
                   AND unr.source_table = 'expression_dym'
               )
               ON DUPLICATE KEY UPDATE read_at = NOW(), updated_at = NOW()";

    $stmt1 = $pdo->prepare($query1);
    $stmt1->execute([':user_id' => $user_id, ':user_id2' => $user_id]);
    $marked_count += $stmt1->rowCount();

    // Matériaux en attente - besoins
    $query2 = "INSERT INTO user_notifications_read 
               (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
               SELECT :user_id, 'pending', b.id, b.idBesoin, 'besoins', b.designation_article, NOW()
               FROM besoins b
               WHERE b.qt_acheter IS NOT NULL 
               AND b.qt_acheter > 0 
               AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL)
               AND b.created_at >= (SELECT DATE_SUB(NOW(), INTERVAL 6 MONTH))
               AND NOT EXISTS (
                   SELECT 1 FROM user_notifications_read unr 
                   WHERE unr.user_id = :user_id2 
                   AND unr.notification_type = 'pending'
                   AND unr.material_id = b.id
                   AND unr.source_table = 'besoins'
               )
               ON DUPLICATE KEY UPDATE read_at = NOW(), updated_at = NOW()";

    $stmt2 = $pdo->prepare($query2);
    $stmt2->execute([':user_id' => $user_id, ':user_id2' => $user_id]);
    $marked_count += $stmt2->rowCount();

    return $marked_count;
}

/**
 * Marque les notifications de quantités restantes comme lues
 */
function markRemainingNotifications($pdo, $user_id) {
    $marked_count = 0;

    // Matériaux avec quantités restantes - expression_dym
    $query1 = "INSERT INTO user_notifications_read 
               (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
               SELECT :user_id, 'remaining', ed.id, ed.idExpression, 'expression_dym', ed.designation, NOW()
               FROM expression_dym ed
               WHERE ed.qt_restante > 0 AND ed.valide_achat = 'en_cours'
               AND NOT EXISTS (
                   SELECT 1 FROM user_notifications_read unr 
                   WHERE unr.user_id = :user_id2 
                   AND unr.notification_type = 'remaining'
                   AND unr.material_id = ed.id
                   AND unr.source_table = 'expression_dym'
               )
               ON DUPLICATE KEY UPDATE read_at = NOW(), updated_at = NOW()";

    $stmt1 = $pdo->prepare($query1);
    $stmt1->execute([':user_id' => $user_id, ':user_id2' => $user_id]);
    $marked_count += $stmt1->rowCount();

    // Matériaux avec quantités restantes - besoins
    $query2 = "INSERT INTO user_notifications_read 
               (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
               SELECT :user_id, 'remaining', b.id, b.idBesoin, 'besoins', b.designation_article, NOW()
               FROM besoins b
               WHERE b.qt_restante > 0 AND b.achat_status = 'en_cours'
               AND NOT EXISTS (
                   SELECT 1 FROM user_notifications_read unr 
                   WHERE unr.user_id = :user_id2 
                   AND unr.notification_type = 'remaining'
                   AND unr.material_id = b.id
                   AND unr.source_table = 'besoins'
               )
               ON DUPLICATE KEY UPDATE read_at = NOW(), updated_at = NOW()";

    $stmt2 = $pdo->prepare($query2);
    $stmt2->execute([':user_id' => $user_id, ':user_id2' => $user_id]);
    $marked_count += $stmt2->rowCount();

    return $marked_count;
}

/**
 * Récupère les compteurs mis à jour en excluant les notifications lues
 */
function getUpdatedCounters($pdo, $user_id) {
    try {
        // Utiliser la fonction getFilteredDateCondition si disponible
        $dateCondition = function_exists('getFilteredDateCondition') ? getFilteredDateCondition() : "created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";

        $countQuery = "SELECT 
            -- Notifications urgentes non lues
            (SELECT COUNT(*) FROM expression_dym ed
             WHERE ed.qt_acheter IS NOT NULL AND ed.qt_acheter > 0 
             AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
             AND ed.{$dateCondition}
             AND (ed.qt_acheter > 100 OR DATEDIFF(NOW(), ed.created_at) > 7)
             AND NOT EXISTS (
                 SELECT 1 FROM user_notifications_read unr 
                 WHERE unr.user_id = :user_id 
                 AND unr.notification_type = 'urgent'
                 AND unr.material_id = ed.id
                 AND unr.source_table = 'expression_dym'
             )) +
            (SELECT COUNT(*) FROM besoins b
             WHERE b.qt_acheter IS NOT NULL AND b.qt_acheter > 0 
             AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL)
             AND b.{$dateCondition}
             AND (b.qt_acheter > 100 OR DATEDIFF(NOW(), b.created_at) > 7)
             AND NOT EXISTS (
                 SELECT 1 FROM user_notifications_read unr 
                 WHERE unr.user_id = :user_id2 
                 AND unr.notification_type = 'urgent'
                 AND unr.material_id = b.id
                 AND unr.source_table = 'besoins'
             )) as urgent_count,

            -- Notifications récentes non lues
            (SELECT COUNT(*) FROM expression_dym ed
             WHERE ed.qt_acheter IS NOT NULL AND ed.qt_acheter > 0 
             AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
             AND ed.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND NOT EXISTS (
                 SELECT 1 FROM user_notifications_read unr 
                 WHERE unr.user_id = :user_id3 
                 AND unr.notification_type = 'recent'
                 AND unr.material_id = ed.id
                 AND unr.source_table = 'expression_dym'
             )) +
            (SELECT COUNT(*) FROM besoins b
             WHERE b.qt_acheter IS NOT NULL AND b.qt_acheter > 0 
             AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL)
             AND b.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND NOT EXISTS (
                 SELECT 1 FROM user_notifications_read unr 
                 WHERE unr.user_id = :user_id4 
                 AND unr.notification_type = 'recent'
                 AND unr.material_id = b.id
                 AND unr.source_table = 'besoins'
             )) as recent_count,

            -- Notifications en attente non lues
            (SELECT COUNT(*) FROM expression_dym ed
             WHERE ed.qt_acheter IS NOT NULL AND ed.qt_acheter > 0 
             AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
             AND ed.{$dateCondition}
             AND ed.id NOT IN (
                 SELECT ed2.id FROM expression_dym ed2 
                 WHERE (ed2.qt_acheter > 100 OR DATEDIFF(NOW(), ed2.created_at) > 7)
                 OR ed2.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             )
             AND NOT EXISTS (
                 SELECT 1 FROM user_notifications_read unr 
                 WHERE unr.user_id = :user_id5 
                 AND unr.notification_type = 'pending'
                 AND unr.material_id = ed.id
                 AND unr.source_table = 'expression_dym'
             )) +
            (SELECT COUNT(*) FROM besoins b
             WHERE b.qt_acheter IS NOT NULL AND b.qt_acheter > 0 
             AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL)
             AND b.{$dateCondition}
             AND b.id NOT IN (
                 SELECT b2.id FROM besoins b2 
                 WHERE (b2.qt_acheter > 100 OR DATEDIFF(NOW(), b2.created_at) > 7)
                 OR b2.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             )
             AND NOT EXISTS (
                 SELECT 1 FROM user_notifications_read unr 
                 WHERE unr.user_id = :user_id6 
                 AND unr.notification_type = 'pending'
                 AND unr.material_id = b.id
                 AND unr.source_table = 'besoins'
             )) as pending_count,

            -- Notifications de quantités restantes non lues
            (SELECT COUNT(*) FROM expression_dym ed
             WHERE ed.qt_restante > 0 AND ed.valide_achat = 'en_cours'
             AND NOT EXISTS (
                 SELECT 1 FROM user_notifications_read unr 
                 WHERE unr.user_id = :user_id7 
                 AND unr.notification_type = 'remaining'
                 AND unr.material_id = ed.id
                 AND unr.source_table = 'expression_dym'
             )) +
            (SELECT COUNT(*) FROM besoins b
             WHERE b.qt_restante > 0 AND b.achat_status = 'en_cours'
             AND NOT EXISTS (
                 SELECT 1 FROM user_notifications_read unr 
                 WHERE unr.user_id = :user_id8 
                 AND unr.notification_type = 'remaining'
                 AND unr.material_id = b.id
                 AND unr.source_table = 'besoins'
             )) as remaining_count";

        $stmt = $pdo->prepare($countQuery);
        $stmt->execute([
            ':user_id' => $user_id,
            ':user_id2' => $user_id,
            ':user_id3' => $user_id,
            ':user_id4' => $user_id,
            ':user_id5' => $user_id,
            ':user_id6' => $user_id,
            ':user_id7' => $user_id,
            ':user_id8' => $user_id
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $counters = [
            'urgent' => (int) $result['urgent_count'],
            'recent' => (int) $result['recent_count'], 
            'pending' => (int) $result['pending_count'],
            'remaining' => (int) $result['remaining_count'],
            'partial' => (int) $result['remaining_count'], // Alias pour compatibilité
            'total' => 0
        ];

        // Calculer le total
        $counters['total'] = $counters['urgent'] + $counters['recent'] + $counters['pending'] + $counters['remaining'];

        return $counters;

    } catch (Exception $e) {
        error_log("Erreur lors du calcul des compteurs: " . $e->getMessage());
        
        // Retourner des valeurs par défaut en cas d'erreur
        return [
            'urgent' => 0,
            'recent' => 0,
            'pending' => 0,
            'remaining' => 0,
            'partial' => 0,
            'total' => 0
        ];
    }
}
?>