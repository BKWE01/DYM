<?php
// Script pour mettre à jour les commandes totalement reçues
// Peut être exécuté comme une tâche cron ou être appelé depuis le dispatching

// Connexion à la base de données
include_once '../../database/connection.php'; 

// Fonction pour journaliser les événements
function logEvent($message)
{
    $logFile = 'order_updates_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

try {
    // Démarrer une transaction
    $pdo->beginTransaction();

    // 1. Identifier les commandes achats_materiaux où la quantité est à 0 ou moins
    $completedOrdersStmt = $pdo->query("
        SELECT id, expression_id, designation 
        FROM achats_materiaux 
        WHERE status = 'commandé' 
        AND (quantity = 0 OR quantity IS NULL OR quantity <= 0)
    ");

    $completedOrders = $completedOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    $updatedCount = 0;

    foreach ($completedOrders as $order) {
        // Mettre à jour le statut à "reçu" et définir la date de réception
        $updateStmt = $pdo->prepare("
            UPDATE achats_materiaux 
            SET status = 'reçu', date_reception = NOW() 
            WHERE id = :id
        ");
        $updateStmt->execute([':id' => $order['id']]);
        logEvent("Commande #" . $order['id'] . " (" . $order['designation'] . ") marquée comme 'reçu' - Quantité originale préservée");

        // Mettre à jour le statut dans expression_dym
        $updateExpressionStmt = $pdo->prepare("
            UPDATE expression_dym 
            SET valide_achat = 'reçu' 
            WHERE idExpression = :expression_id 
            AND designation = :designation
            AND valide_achat = 'validé'
        ");

        $updateExpressionStmt->execute([
            ':expression_id' => $order['expression_id'],
            ':designation' => $order['designation']
        ]);

        $updatedCount++;
        logEvent("Commande #" . $order['id'] . " (" . $order['designation'] . ") marquée comme 'reçu'");
    }

    // 2. Mettre à jour les commandes en expression_dym où la quantité à acheter est 0
    $completedExpressionsStmt = $pdo->query("
        SELECT id, idExpression, designation 
        FROM expression_dym 
        WHERE valide_achat = 'validé' 
        AND (qt_acheter = 0 OR qt_acheter IS NULL OR qt_acheter <= 0)
    ");

    $completedExpressions = $completedExpressionsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($completedExpressions as $expr) {
        $updateStmt = $pdo->prepare("
            UPDATE expression_dym 
            SET valide_achat = 'reçu' 
            WHERE id = :id
        ");
        $updateStmt->execute([':id' => $expr['id']]);

        // Vérifier si une entrée correspondante existe dans achats_materiaux et la mettre à jour
        $checkAchatStmt = $pdo->prepare("
            SELECT id FROM achats_materiaux 
            WHERE expression_id = :expression_id 
            AND designation = :designation 
            AND status = 'commandé'
        ");

        $checkAchatStmt->execute([
            ':expression_id' => $expr['idExpression'],
            ':designation' => $expr['designation']
        ]);

        $achat = $checkAchatStmt->fetch(PDO::FETCH_ASSOC);

        if ($achat) {
            $updateAchatStmt = $pdo->prepare("
                UPDATE achats_materiaux 
                SET status = 'reçu', date_reception = NOW() 
                WHERE id = :id
            ");
            $updateAchatStmt->execute([':id' => $achat['id']]);
        }

        $updatedCount++;
        logEvent("Expression #" . $expr['id'] . " (" . $expr['designation'] . ") marquée comme 'reçu'");
    }

    // Valider toutes les modifications
    $pdo->commit();

    // Journaliser le résultat
    logEvent("Mise à jour terminée. $updatedCount commandes marquées comme 'reçu'.");

    echo "Mise à jour réussie. $updatedCount commandes ont été marquées comme 'reçu'.";

} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logEvent("ERREUR: " . $e->getMessage());
    echo "Une erreur est survenue: " . $e->getMessage();
}