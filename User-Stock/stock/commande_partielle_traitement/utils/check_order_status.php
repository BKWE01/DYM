<?php
/**
 * Fonctions utilitaires pour vérifier le statut des commandes
 * basées sur quantity_stock
 */

/**
 * Vérifie si une commande est complètement satisfaite
 * 
 * @param PDO $pdo Instance de connexion à la base de données
 * @param int $expressionId ID de l'expression
 * @param string $designation Désignation du produit
 * @return bool|array True si satisfaite, tableau d'informations si non, false en cas d'erreur
 */
function isOrderFullySatisfied($pdo, $expressionId, $designation)
{
    try {
        $query = "
            SELECT 
                id, 
                qt_acheter, 
                qt_restante, 
                initial_qt_acheter, 
                quantity_stock, 
                valide_achat
            FROM expression_dym
            WHERE idExpression = :expression_id
            AND designation = :designation
            LIMIT 1
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':expression_id' => $expressionId,
            ':designation' => $designation
        ]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return false;
        }

        // Calculer la quantité totale requise
        $initialQty = floatval($order['initial_qt_acheter'] ?? ($order['qt_acheter'] + $order['qt_restante']));
        $stockQty = floatval($order['quantity_stock'] ?? 0);

        // Vérifier si la quantité en stock est suffisante
        $isFullySatisfied = $stockQty >= ($initialQty - 0.001); // Avec tolérance pour les erreurs d'arrondi

        if ($isFullySatisfied) {
            return true;
        } else {
            return [
                'id' => $order['id'],
                'total_required' => $initialQty,
                'current_stock' => $stockQty,
                'missing' => $initialQty - $stockQty,
                'status' => $order['valide_achat']
            ];
        }
    } catch (Exception $e) {
        error_log("Erreur dans isOrderFullySatisfied: " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour le statut d'une commande en fonction de quantity_stock
 * 
 * @param PDO $pdo Instance de connexion à la base de données
 * @param int $expressionId ID de l'expression
 * @param string $designation Désignation du produit
 * @return bool|string True si la mise à jour a réussi, message d'erreur sinon
 */
function updateOrderStatusBasedOnStock($pdo, $expressionId, $designation)
{
    try {
        $pdo->beginTransaction();

        // Récupérer les informations de la commande
        $query = "
            SELECT 
                id, 
                qt_acheter, 
                qt_restante, 
                initial_qt_acheter, 
                quantity_stock, 
                valide_achat
            FROM expression_dym
            WHERE idExpression = :expression_id
            AND designation = :designation
            LIMIT 1
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':expression_id' => $expressionId,
            ':designation' => $designation
        ]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $pdo->rollBack();
            return "Commande non trouvée";
        }

        // Calculer la quantité totale requise
        $initialQty = floatval($order['initial_qt_acheter'] ?? ($order['qt_acheter'] + $order['qt_restante']));
        $stockQty = floatval($order['quantity_stock'] ?? 0);

        // Vérifier si la quantité en stock est suffisante
        $isFullySatisfied = $stockQty >= ($initialQty - 0.001); // Avec tolérance pour les erreurs d'arrondi

        // Mettre à jour le statut de la commande si nécessaire
        if ($isFullySatisfied && $order['valide_achat'] !== 'reçu') {
            $updateQuery = "
                UPDATE expression_dym
                SET valide_achat = 'reçu',
                    qt_restante = 0
                WHERE id = :id
            ";

            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute([':id' => $order['id']]);

            // Mettre à jour le statut dans achats_materiaux si besoin
            $updateAchatsQuery = "
                UPDATE achats_materiaux
                SET status = 'reçu',
                    date_reception = NOW()
                WHERE expression_id = :expression_id
                AND designation = :designation
                AND status = 'commandé'
            ";

            $updateAchatsStmt = $pdo->prepare($updateAchatsQuery);
            $updateAchatsStmt->execute([
                ':expression_id' => $expressionId,
                ':designation' => $designation
            ]);
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur dans updateOrderStatusBasedOnStock: " . $e->getMessage());
        return "Erreur: " . $e->getMessage();
    }
}

/**
 * Synchronise quantity_stock pour toutes les commandes
 * Utile pour mettre à jour les données historiques
 * 
 * @param PDO $pdo Instance de connexion à la base de données
 * @return array Statistiques de la synchronisation
 */
function synchronizeAllOrdersQuantityStock($pdo)
{
    try {
        $stats = [
            'updated' => 0,
            'completed' => 0,
            'errors' => 0
        ];

        $pdo->beginTransaction();

        // Étape 1: Mettre à jour quantity_stock basé sur les mouvements de stock
        $updateQuery = "
            UPDATE expression_dym ed
            LEFT JOIN (
                SELECT 
                    am.expression_id,
                    am.designation,
                    SUM(am.quantity) as total_quantity
                FROM achats_materiaux am
                WHERE am.status = 'reçu'
                GROUP BY am.expression_id, am.designation
            ) received ON ed.idExpression = received.expression_id AND ed.designation = received.designation
            SET ed.quantity_stock = COALESCE(received.total_quantity, 0)
            WHERE (ed.quantity_stock IS NULL OR ed.quantity_stock = 0) AND received.total_quantity > 0
        ";

        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute();
        $stats['updated'] = $updateStmt->rowCount();

        // Étape 2: Mettre à jour le statut des commandes qui sont complètement satisfaites
        $updateStatusQuery = "
            UPDATE expression_dym ed
            SET ed.valide_achat = 'reçu'
            WHERE ed.quantity_stock >= (
                CASE 
                    WHEN ed.initial_qt_acheter IS NOT NULL AND ed.initial_qt_acheter > 0
                    THEN ed.initial_qt_acheter
                    ELSE ed.qt_acheter + COALESCE(ed.qt_restante, 0)
                END - 0.001
            )
            AND ed.valide_achat != 'reçu'
        ";

        $updateStatusStmt = $pdo->prepare($updateStatusQuery);
        $updateStatusStmt->execute();
        $stats['completed'] = $updateStatusStmt->rowCount();

        $pdo->commit();
        return $stats;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur dans synchronizeAllOrdersQuantityStock: " . $e->getMessage());
        $stats['errors'] = 1;
        $stats['error_message'] = $e->getMessage();
        return $stats;
    }
}