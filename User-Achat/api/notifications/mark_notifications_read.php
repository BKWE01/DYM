<?php

/**
 * API pour marquer les notifications comme lues
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
    include_once '../../../database/connection.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = [
    'success' => false,
    'message' => '',
    'data' => []
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

            // Insérer ou mettre à jour l'enregistrement de lecture
            $insertQuery = "INSERT INTO user_notifications_read 
                           (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
                           VALUES (:user_id, :notification_type, :material_id, :expression_id, :source_table, :designation, NOW())
                           ON DUPLICATE KEY UPDATE 
                           read_at = NOW(), updated_at = NOW()";

            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->execute([
                ':user_id' => $user_id,
                ':notification_type' => $notification_type,
                ':material_id' => $material_id,
                ':expression_id' => $expression_id,
                ':source_table' => $source_table,
                ':designation' => $designation
            ]);

            $response = [
                'success' => true,
                'message' => 'Notification marquée comme lue',
                'data' => [
                    'material_id' => $material_id,
                    'notification_type' => $notification_type
                ]
            ];
            break;

        // ========== MARQUER TOUTES LES NOTIFICATIONS D'UN TYPE COMME LUES ==========
        case 'mark_type':
            $notification_type = $input['notification_type'] ?? '';

            if (!$notification_type) {
                throw new Exception('Type de notification non spécifié');
            }

            $pdo->beginTransaction();

            // Récupérer toutes les notifications non lues de ce type pour cet utilisateur
            if ($notification_type === 'urgent') {
                // Matériaux urgents - expression_dym
                $selectQuery1 = "SELECT ed.id as material_id, ed.idExpression as expression_id, 
                                       ed.designation, 'expression_dym' as source_table
                                FROM expression_dym ed
                                JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                                WHERE ed.qt_acheter IS NOT NULL 
                                AND ed.qt_acheter > 0 
                                AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
                                AND (ed.qt_acheter > 100 OR DATEDIFF(NOW(), ed.created_at) > 7)
                                AND NOT EXISTS (
                                    SELECT 1 FROM user_notifications_read unr 
                                    WHERE unr.user_id = :user_id 
                                    AND unr.notification_type = 'urgent'
                                    AND unr.material_id = ed.id
                                    AND unr.source_table = 'expression_dym'
                                )";

                // Matériaux urgents - besoins
                $selectQuery2 = "SELECT b.id as material_id, b.idBesoin as expression_id, 
                                       b.designation_article as designation, 'besoins' as source_table
                                FROM besoins b
                                WHERE b.qt_acheter IS NOT NULL 
                                AND b.qt_acheter > 0 
                                AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL)
                                AND (b.qt_acheter > 100 OR DATEDIFF(NOW(), b.created_at) > 7)
                                AND NOT EXISTS (
                                    SELECT 1 FROM user_notifications_read unr 
                                    WHERE unr.user_id = :user_id 
                                    AND unr.notification_type = 'urgent'
                                    AND unr.material_id = b.id
                                    AND unr.source_table = 'besoins'
                                )";
            } elseif ($notification_type === 'recent') {
                // Matériaux récents - expression_dym
                $selectQuery1 = "SELECT ed.id as material_id, ed.idExpression as expression_id, 
                                       ed.designation, 'expression_dym' as source_table
                                FROM expression_dym ed
                                WHERE ed.qt_acheter IS NOT NULL 
                                AND ed.qt_acheter > 0 
                                AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
                                AND ed.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                AND NOT EXISTS (
                                    SELECT 1 FROM user_notifications_read unr 
                                    WHERE unr.user_id = :user_id 
                                    AND unr.notification_type = 'recent'
                                    AND unr.material_id = ed.id
                                    AND unr.source_table = 'expression_dym'
                                )";

                // Matériaux récents - besoins
                $selectQuery2 = "SELECT b.id as material_id, b.idBesoin as expression_id, 
                                       b.designation_article as designation, 'besoins' as source_table
                                FROM besoins b
                                WHERE b.qt_acheter IS NOT NULL 
                                AND b.qt_acheter > 0 
                                AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL)
                                AND b.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                AND NOT EXISTS (
                                    SELECT 1 FROM user_notifications_read unr 
                                    WHERE unr.user_id = :user_id 
                                    AND unr.notification_type = 'recent'
                                    AND unr.material_id = b.id
                                    AND unr.source_table = 'besoins'
                                )";
            } elseif ($notification_type === 'remaining') {
                // Matériaux avec quantités restantes - expression_dym
                $selectQuery1 = "SELECT ed.id as material_id, ed.idExpression as expression_id, 
                                       ed.designation, 'expression_dym' as source_table
                                FROM expression_dym ed
                                WHERE ed.qt_restante > 0 AND ed.valide_achat = 'en_cours'
                                AND NOT EXISTS (
                                    SELECT 1 FROM user_notifications_read unr 
                                    WHERE unr.user_id = :user_id 
                                    AND unr.notification_type = 'remaining'
                                    AND unr.material_id = ed.id
                                    AND unr.source_table = 'expression_dym'
                                )";

                // Matériaux avec quantités restantes - besoins
                $selectQuery2 = "SELECT b.id as material_id, b.idBesoin as expression_id, 
                                       b.designation_article as designation, 'besoins' as source_table
                                FROM besoins b
                                WHERE b.qt_restante > 0 AND b.achat_status = 'en_cours'
                                AND NOT EXISTS (
                                    SELECT 1 FROM user_notifications_read unr 
                                    WHERE unr.user_id = :user_id 
                                    AND unr.notification_type = 'remaining'
                                    AND unr.material_id = b.id
                                    AND unr.source_table = 'besoins'
                                )";
            } else {
                throw new Exception('Type de notification non supporté');
            }

            $materials_to_mark = [];

            // Exécuter les requêtes pour récupérer les matériaux à marquer
            $stmt1 = $pdo->prepare($selectQuery1);
            $stmt1->execute([':user_id' => $user_id]);
            $materials_to_mark = array_merge($materials_to_mark, $stmt1->fetchAll(PDO::FETCH_ASSOC));

            if (isset($selectQuery2)) {
                $stmt2 = $pdo->prepare($selectQuery2);
                $stmt2->execute([':user_id' => $user_id]);
                $materials_to_mark = array_merge($materials_to_mark, $stmt2->fetchAll(PDO::FETCH_ASSOC));
            }

            // Marquer tous les matériaux comme lus
            $marked_count = 0;
            foreach ($materials_to_mark as $material) {
                $insertQuery = "INSERT INTO user_notifications_read 
                               (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
                               VALUES (:user_id, :notification_type, :material_id, :expression_id, :source_table, :designation, NOW())
                               ON DUPLICATE KEY UPDATE read_at = NOW(), updated_at = NOW()";

                $insertStmt = $pdo->prepare($insertQuery);
                $insertStmt->execute([
                    ':user_id' => $user_id,
                    ':notification_type' => $notification_type,
                    ':material_id' => $material['material_id'],
                    ':expression_id' => $material['expression_id'],
                    ':source_table' => $material['source_table'],
                    ':designation' => $material['designation']
                ]);
                $marked_count++;
            }

            $pdo->commit();

            $response = [
                'success' => true,
                'message' => "Toutes les notifications de type '{$notification_type}' marquées comme lues",
                'data' => [
                    'notification_type' => $notification_type,
                    'marked_count' => $marked_count
                ]
            ];
            break;

        // ========== MARQUER TOUTES LES NOTIFICATIONS COMME LUES ==========
        case 'mark_all':
            $pdo->beginTransaction();

            $marked_count = 0;
            $notification_types = ['urgent', 'recent', 'pending', 'remaining'];

            foreach ($notification_types as $type) {
                // Utiliser la même logique que mark_type mais pour chaque type
                // (Code similaire à mark_type mais en boucle)

                // Pour simplifier, on peut marquer directement toutes les notifications existantes
                $markAllQuery = "INSERT INTO user_notifications_read 
                               (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
                               SELECT :user_id, :notification_type, ed.id, ed.idExpression, 'expression_dym', ed.designation, NOW()
                               FROM expression_dym ed
                               WHERE ed.qt_acheter IS NOT NULL AND ed.qt_acheter > 0 
                               AND (ed.valide_achat IN ('pas validé', 'en_cours') OR ed.valide_achat IS NULL)
                               ON DUPLICATE KEY UPDATE read_at = NOW(), updated_at = NOW()";

                $markStmt = $pdo->prepare($markAllQuery);
                $markStmt->execute([
                    ':user_id' => $user_id,
                    ':notification_type' => $type
                ]);
                $marked_count += $markStmt->rowCount();

                // Même chose pour la table besoins
                $markAllQueryBesoins = "INSERT INTO user_notifications_read 
                                       (user_id, notification_type, material_id, expression_id, source_table, designation, read_at)
                                       SELECT :user_id, :notification_type, b.id, b.idBesoin, 'besoins', b.designation_article, NOW()
                                       FROM besoins b
                                       WHERE b.qt_acheter IS NOT NULL AND b.qt_acheter > 0 
                                       AND (b.achat_status IN ('pas validé', 'en_cours') OR b.achat_status IS NULL)
                                       ON DUPLICATE KEY UPDATE read_at = NOW(), updated_at = NOW()";

                $markStmtBesoins = $pdo->prepare($markAllQueryBesoins);
                $markStmtBesoins->execute([
                    ':user_id' => $user_id,
                    ':notification_type' => $type
                ]);
                $marked_count += $markStmtBesoins->rowCount();
            }

            $pdo->commit();

            $response = [
                'success' => true,
                'message' => 'Toutes les notifications marquées comme lues',
                'data' => [
                    'marked_count' => $marked_count
                ]
            ];
            break;

        // ========== OBTENIR LE NOMBRE DE NOTIFICATIONS NON LUES ==========
        case 'get_unread_count':
            $countQuery = "SELECT 
                (SELECT COUNT(*) FROM expression_dym ed
                 WHERE ed.qt_acheter IS NOT NULL AND ed.qt_acheter > 0 
                 AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
                 AND NOT EXISTS (
                     SELECT 1 FROM user_notifications_read unr 
                     WHERE unr.user_id = :user_id 
                     AND unr.material_id = ed.id
                     AND unr.source_table = 'expression_dym'
                 )) +
                (SELECT COUNT(*) FROM besoins b
                 WHERE b.qt_acheter IS NOT NULL AND b.qt_acheter > 0 
                 AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL)
                 AND NOT EXISTS (
                     SELECT 1 FROM user_notifications_read unr 
                     WHERE unr.user_id = :user_id2 
                     AND unr.material_id = b.id
                     AND unr.source_table = 'besoins'
                 )) as unread_count";

            $countStmt = $pdo->prepare($countQuery);
            $countStmt->execute([
                ':user_id' => $user_id,
                ':user_id2' => $user_id
            ]);
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);

            $response = [
                'success' => true,
                'message' => 'Nombre de notifications non lues récupéré',
                'data' => [
                    'unread_count' => $countResult['unread_count'] ?? 0
                ]
            ];
            break;

        default:
            throw new Exception('Action non reconnue');
    }
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    $response = [
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'data' => []
    ];
}

echo json_encode($response);
