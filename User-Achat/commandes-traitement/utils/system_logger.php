<?php
/**
 * Enregistre un événement dans le journal système
 * 
 * @param PDO $pdo Instance de connexion à la base de données
 * @param int $userId ID de l'utilisateur qui effectue l'action
 * @param string $action Type d'action (création, modification, etc.)
 * @param string $type Type d'entité concernée (produit, commande, etc.)
 * @param mixed $entityId Identifiant de l'entité concernée
 * @param string $details Détails supplémentaires
 * @return bool Succès de l'opération
 */
function logSystemEvent($pdo, $userId, $action, $type, $entityId, $details = null)
{
    try {
        // Récupérer le nom d'utilisateur
        $username = null;
        if ($userId) {
            $usernameQuery = "SELECT name FROM users_exp WHERE id = :id LIMIT 1";
            $usernameStmt = $pdo->prepare($usernameQuery);
            $usernameStmt->bindParam(':id', $userId);
            $usernameStmt->execute();
            $username = $usernameStmt->fetchColumn();
        }

        // Récupérer le nom de l'entité si disponible
        $entityName = null;
        if ($type === 'achats_materiaux' && $entityId) {
            $entityQuery = "SELECT designation FROM achats_materiaux WHERE id = :id LIMIT 1";
            $entityStmt = $pdo->prepare($entityQuery);
            $entityStmt->bindParam(':id', $entityId);
            $entityStmt->execute();
            $entityName = $entityStmt->fetchColumn();
        } elseif ($type === 'expression_dym' && $entityId) {
            $entityQuery = "SELECT designation FROM expression_dym WHERE id = :id LIMIT 1";
            $entityStmt = $pdo->prepare($entityQuery);
            $entityStmt->bindParam(':id', $entityId);
            $entityStmt->execute();
            $entityName = $entityStmt->fetchColumn();
        }

        // Récupérer l'adresse IP si disponible
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

        // Insérer l'événement dans le journal
        $logQuery = "INSERT INTO system_logs (user_id, username, action, type, entity_id, entity_name, details, ip_address)
                   VALUES (:user_id, :username, :action, :type, :entity_id, :entity_name, :details, :ip_address)";

        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindParam(':user_id', $userId);
        $logStmt->bindParam(':username', $username);
        $logStmt->bindParam(':action', $action);
        $logStmt->bindParam(':type', $type);
        $logStmt->bindParam(':entity_id', $entityId);
        $logStmt->bindParam(':entity_name', $entityName);
        $logStmt->bindParam(':details', $details);
        $logStmt->bindParam(':ip_address', $ipAddress);

        return $logStmt->execute();
    } catch (Exception $e) {
        error_log("Erreur lors de la journalisation: " . $e->getMessage());
        return false;
    }
}