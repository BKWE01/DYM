<?php
/**
 * Fonction utilitaire pour regrouper les commandes partielles lors de la réception
 * 
 * À ajouter dans un fichier utilitaire comme /utils/combine_orders.php
 */

/**
 * Regroupe les commandes partielles en une seule entrée lors de la réception finale
 * 
 * @param PDO $pdo Instance de connexion à la base de données
 * @param int $currentOrderId ID de la commande actuellement reçue
 * @return array Informations sur le regroupement effectué
 */
function combinePartialOrders($pdo, $currentOrderId)
{
    try {
        // 1. Récupérer les informations sur la commande actuelle
        $orderQuery = "SELECT id, expression_id, designation, quantity, original_quantity, is_partial 
                      FROM achats_materiaux 
                      WHERE id = :id";
        $orderStmt = $pdo->prepare($orderQuery);
        $orderStmt->bindParam(':id', $currentOrderId);
        $orderStmt->execute();
        $currentOrder = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentOrder) {
            return ['success' => false, 'message' => 'Commande non trouvée'];
        }

        // Si ce n'est pas une commande partielle, rien à faire
        if (!$currentOrder['is_partial']) {
            return ['success' => true, 'message' => 'Pas une commande partielle, aucun regroupement nécessaire'];
        }

        // 2. Rechercher des commandes partielles pour le même produit et expression
        $relatedQuery = "SELECT id, expression_id, designation, quantity, status, parent_id, is_partial
                        FROM achats_materiaux
                        WHERE designation = :designation
                        AND expression_id = :expression_id
                        AND id != :current_id
                        AND is_partial = 1
                        AND status IN ('reçu', 'commandé')";

        $relatedStmt = $pdo->prepare($relatedQuery);
        $relatedStmt->bindParam(':designation', $currentOrder['designation']);
        $relatedStmt->bindParam(':expression_id', $currentOrder['expression_id']);
        $relatedStmt->bindParam(':current_id', $currentOrderId);
        $relatedStmt->execute();
        $relatedOrders = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($relatedOrders)) {
            // Aucune commande reliée trouvée, cela pourrait être la première partie
            return ['success' => true, 'message' => 'Aucune commande reliée trouvée'];
        }

        // 3. Déterminer si toutes les quantités sont maintenant commandées/reçues
        $totalOrdered = $currentOrder['quantity'];
        $orderIds = [$currentOrderId];

        foreach ($relatedOrders as $order) {
            $totalOrdered += $order['quantity'];
            $orderIds[] = $order['id'];
        }

        // Si la quantité totale est égale à la quantité originale, c'est complètement commandé
        $isComplete = abs($totalOrdered - $currentOrder['original_quantity']) < 0.001; // Utiliser une petite valeur de tolérance pour les floats

        // 4. Si c'est complet, créer une commande regroupée
        if ($isComplete) {
            $pdo->beginTransaction();

            // Créer une commande regroupée ou utiliser une existante
            $createGroupedQuery = "INSERT INTO achats_materiaux 
                                  (expression_id, designation, quantity, unit, prix_unitaire, fournisseur, 
                                   status, user_achat, original_quantity, is_partial, date_achat)
                                  SELECT 
                                      expression_id, designation, :total_quantity, unit, prix_unitaire, fournisseur,
                                      'reçu', user_achat, original_quantity, 0, NOW()
                                  FROM achats_materiaux
                                  WHERE id = :current_id";

            $createStmt = $pdo->prepare($createGroupedQuery);
            $createStmt->bindParam(':total_quantity', $totalOrdered);
            $createStmt->bindParam(':current_id', $currentOrderId);
            $createStmt->execute();

            $groupedOrderId = $pdo->lastInsertId();

            // Mettre à jour les commandes partielles existantes avec un parent_id
            $updateQuery = "UPDATE achats_materiaux 
                          SET parent_id = :parent_id 
                          WHERE id IN (" . implode(',', $orderIds) . ")";

            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(':parent_id', $groupedOrderId);
            $updateStmt->execute();

            // Mettre à jour l'entrée original dans expression_dym
            $updateExpressionQuery = "UPDATE expression_dym 
                                    SET valide_achat = 'reçu', 
                                        qt_restante = 0
                                    WHERE idExpression = :expression_id 
                                    AND designation = :designation";

            $updateExpressionStmt = $pdo->prepare($updateExpressionQuery);
            $updateExpressionStmt->bindParam(':expression_id', $currentOrder['expression_id']);
            $updateExpressionStmt->bindParam(':designation', $currentOrder['designation']);
            $updateExpressionStmt->execute();

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Commandes partielles regroupées avec succès',
                'is_complete' => true,
                'grouped_id' => $groupedOrderId,
                'total_quantity' => $totalOrdered,
                'order_ids' => $orderIds
            ];
        } else {
            // Pas encore complet, mettre à jour la quantité restante
            $remainingQuantity = $currentOrder['original_quantity'] - $totalOrdered;

            if ($remainingQuantity > 0) {
                $updateQuery = "UPDATE expression_dym 
                              SET qt_restante = :remaining_quantity 
                              WHERE idExpression = :expression_id 
                              AND designation = :designation";

                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->bindParam(':remaining_quantity', $remainingQuantity);
                $updateStmt->bindParam(':expression_id', $currentOrder['expression_id']);
                $updateStmt->bindParam(':designation', $currentOrder['designation']);
                $updateStmt->execute();
            }

            return [
                'success' => true,
                'message' => 'Commande partielle mise à jour, reste à commander: ' . $remainingQuantity,
                'is_complete' => false,
                'remaining_quantity' => $remainingQuantity,
                'original_quantity' => $currentOrder['original_quantity'],
                'total_ordered' => $totalOrdered
            ];
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Vérifie si une commande est partielle et la marque comme telle
 * 
 * @param PDO $pdo Instance de connexion à la base de données
 * @param int $orderId ID de la commande à vérifier
 * @param float $requestedQty Quantité demandée
 * @param float $originalQty Quantité originale
 * @return bool True si c'est une commande partielle
 */
function markAsPartialOrder($pdo, $orderId, $requestedQty, $originalQty)
{
    try {
        // Vérifier si c'est une commande partielle
        $isPartial = $requestedQty < $originalQty;

        if ($isPartial) {
            $updateQuery = "UPDATE achats_materiaux 
                          SET is_partial = 1 
                          WHERE id = :id";

            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(':id', $orderId);
            $updateStmt->execute();
        }

        return $isPartial;
    } catch (Exception $e) {
        error_log("Erreur lors du marquage d'une commande partielle: " . $e->getMessage());
        return false;
    }
}