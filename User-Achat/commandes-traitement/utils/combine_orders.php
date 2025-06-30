<?php
/**
 * Fonction utilitaire pour regrouper les commandes partielles lors de la réception
 */

/**
 * Regroupe les commandes partielles en une seule entrée lors de la réception finale
 * Cette version corrigée maintient les commandes en statut 'commandé'
 * 
 * @param PDO $pdo Instance de connexion à la base de données
 * @param int $currentOrderId ID de la commande actuellement traitée
 * @return array Informations sur le regroupement effectué
 */
function combinePartialOrders($pdo, $currentOrderId)
{
    try {
        // 1. Récupérer les informations sur la commande actuelle
        $orderQuery = "SELECT id, expression_id, designation, quantity, original_quantity, is_partial, unit, prix_unitaire, fournisseur, status
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
        $relatedQuery = "SELECT id, expression_id, designation, quantity, status, parent_id, is_partial, unit, prix_unitaire, fournisseur
                        FROM achats_materiaux
                        WHERE designation = :designation
                        AND expression_id = :expression_id
                        AND id != :current_id
                        AND is_partial = 1";

        $relatedStmt = $pdo->prepare($relatedQuery);
        $relatedStmt->bindParam(':designation', $currentOrder['designation']);
        $relatedStmt->bindParam(':expression_id', $currentOrder['expression_id']);
        $relatedStmt->bindParam(':current_id', $currentOrderId);
        $relatedStmt->execute();
        $relatedOrders = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Déterminer si toutes les quantités sont maintenant commandées
        $totalOrdered = floatval($currentOrder['quantity']);
        $orderIds = [$currentOrderId];
        $totalPrice = floatval($currentOrder['quantity']) * floatval($currentOrder['prix_unitaire']);
        $lastFournisseur = $currentOrder['fournisseur'];
        $ordersWithPrices = [];
        $ordersWithPrices[] = [
            'quantity' => floatval($currentOrder['quantity']),
            'prix_unitaire' => floatval($currentOrder['prix_unitaire'])
        ];

        foreach ($relatedOrders as $order) {
            $totalOrdered += floatval($order['quantity']);
            $orderIds[] = $order['id'];
            $totalPrice += floatval($order['quantity']) * floatval($order['prix_unitaire']);
            $ordersWithPrices[] = [
                'quantity' => floatval($order['quantity']),
                'prix_unitaire' => floatval($order['prix_unitaire'])
            ];

            // Conserver le fournisseur le plus récent pour la commande regroupée
            if (!empty($order['fournisseur'])) {
                $lastFournisseur = $order['fournisseur'];
            }
        }

        // Si la quantité totale est égale à la quantité originale, c'est complètement commandé
        $isComplete = abs($totalOrdered - floatval($currentOrder['original_quantity'])) < 0.001; // Utiliser une petite valeur de tolérance pour les floats

        // Calculer le prix unitaire moyen pondéré
        $avgPrice = calculateWeightedAveragePrice($ordersWithPrices);

        // 4. Si c'est complet, créer une commande regroupée
        if ($isComplete) {
            $pdo->beginTransaction();

            // Créer une commande regroupée avec le statut 'commandé' (pas 'reçu')
            $createGroupedQuery = "INSERT INTO achats_materiaux 
                                  (expression_id, designation, quantity, unit, prix_unitaire, fournisseur, 
                                   status, user_achat, original_quantity, is_partial, date_achat)
                                  VALUES 
                                  (:expression_id, :designation, :total_quantity, :unit, :prix_unitaire, 
                                   :fournisseur, 'commandé', :user_achat, :original_quantity, 0, NOW())";

            $createStmt = $pdo->prepare($createGroupedQuery);

            // Récupérer l'ID utilisateur de la session
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Valeur par défaut si non disponible

            $createStmt->bindParam(':expression_id', $currentOrder['expression_id']);
            $createStmt->bindParam(':designation', $currentOrder['designation']);
            $createStmt->bindParam(':total_quantity', $currentOrder['original_quantity']); // Utiliser la quantité originale complète
            $createStmt->bindParam(':unit', $currentOrder['unit']);
            $createStmt->bindParam(':prix_unitaire', $avgPrice);
            $createStmt->bindParam(':fournisseur', $lastFournisseur);
            $createStmt->bindParam(':user_achat', $userId);
            $createStmt->bindParam(':original_quantity', $currentOrder['original_quantity']);
            $createStmt->execute();

            $groupedOrderId = $pdo->lastInsertId();

            // Mettre à jour les commandes partielles existantes avec un parent_id
            // IMPORTANT: Maintenir le statut original de chaque commande
            $updateQuery = "UPDATE achats_materiaux 
                          SET parent_id = :parent_id 
                          WHERE id IN (" . implode(',', $orderIds) . ")";

            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(':parent_id', $groupedOrderId);
            $updateStmt->execute();

            // Mettre à jour l'entrée original dans expression_dym
            $updateExpressionQuery = "UPDATE expression_dym 
            SET valide_achat = 'valide_en_cours', 
                qt_restante = 0,
                qt_acheter = :total_quantity,
                prix_unitaire = :prix_unitaire,
                fournisseur = :fournisseur
            WHERE idExpression = :expression_id 
            AND designation = :designation";

            $updateExpressionStmt = $pdo->prepare($updateExpressionQuery);
            $updateExpressionStmt->bindParam(':total_quantity', $totalOrdered);
            $updateExpressionStmt->bindParam(':prix_unitaire', $avgPrice);
            $updateExpressionStmt->bindParam(':fournisseur', $lastFournisseur);
            $updateExpressionStmt->bindParam(':expression_id', $currentOrder['expression_id']);
            $updateExpressionStmt->bindParam(':designation', $currentOrder['designation']);
            $updateExpressionStmt->execute();

            // Journaliser l'événement de regroupement
            if (function_exists('logSystemEvent')) {
                $details = json_encode([
                    'grouped_id' => $groupedOrderId,
                    'order_ids' => $orderIds,
                    'total_quantity' => $totalOrdered,
                    'original_quantity' => $currentOrder['original_quantity'],
                    'avg_price' => $avgPrice,
                    'designation' => $currentOrder['designation'],
                    'orders_with_prices' => $ordersWithPrices
                ]);

                // Récupérer l'ID utilisateur de la session
                $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

                logSystemEvent($pdo, $userId, 'commandes_partielles_regroupees', 'achats_materiaux', $groupedOrderId, $details);
            }

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Commandes partielles regroupées avec succès',
                'is_complete' => true,
                'grouped_id' => $groupedOrderId,
                'total_quantity' => $totalOrdered,
                'original_quantity' => $currentOrder['original_quantity'],
                'avg_price' => $avgPrice,
                'order_ids' => $orderIds
            ];
        } else {
            // Pas encore complet, mettre à jour la quantité restante
            $remainingQuantity = floatval($currentOrder['original_quantity']) - $totalOrdered;

            if ($remainingQuantity <= 0.001) {
                // Si la quantité restante est très faible, la considérer comme nulle
                $remainingQuantity = 0;

                // Commencer une nouvelle transaction pour mettre à jour le statut
                $pdo->beginTransaction();

                // Mettre à jour la commande comme complètement validée
                $updateStatusQuery = "UPDATE expression_dym 
                                   SET valide_achat = 'valide_en_cours', 
                                       qt_restante = 0,
                                       prix_unitaire = :prix_unitaire
                                   WHERE idExpression = :expression_id 
                                   AND designation = :designation";

                $updateStatusStmt = $pdo->prepare($updateStatusQuery);
                $updateStatusStmt->bindParam(':prix_unitaire', $avgPrice);
                $updateStatusStmt->bindParam(':expression_id', $currentOrder['expression_id']);
                $updateStatusStmt->bindParam(':designation', $currentOrder['designation']);
                $updateStatusStmt->execute();

                $pdo->commit();

                // Journaliser l'événement
                if (function_exists('logSystemEvent')) {
                    $details = json_encode([
                        'order_id' => $currentOrderId,
                        'total_ordered' => $totalOrdered,
                        'original_quantity' => $currentOrder['original_quantity'],
                        'designation' => $currentOrder['designation'],
                        'valide_achat' => 'valide_en_cours',
                        'avg_price' => $avgPrice
                    ]);

                    // Récupérer l'ID utilisateur de la session
                    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

                    logSystemEvent($pdo, $userId, 'commande_presque_complete', 'achats_materiaux', $currentOrderId, $details);
                }

            } elseif ($remainingQuantity > 0) {
                // Commencer une nouvelle transaction pour mettre à jour la quantité restante
                $pdo->beginTransaction();

                $updateQuery = "UPDATE expression_dym 
                              SET qt_restante = :remaining_quantity,
                                  prix_unitaire = :prix_unitaire
                              WHERE idExpression = :expression_id 
                              AND designation = :designation";

                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->bindParam(':remaining_quantity', $remainingQuantity);
                $updateStmt->bindParam(':prix_unitaire', $avgPrice);
                $updateStmt->bindParam(':expression_id', $currentOrder['expression_id']);
                $updateStmt->bindParam(':designation', $currentOrder['designation']);
                $updateStmt->execute();

                $pdo->commit();

                // Journaliser l'événement
                if (function_exists('logSystemEvent')) {
                    $details = json_encode([
                        'order_id' => $currentOrderId,
                        'total_ordered' => $totalOrdered,
                        'original_quantity' => $currentOrder['original_quantity'],
                        'remaining_quantity' => $remainingQuantity,
                        'designation' => $currentOrder['designation'],
                        'avg_price' => $avgPrice
                    ]);

                    // Récupérer l'ID utilisateur de la session
                    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

                    logSystemEvent($pdo, $userId, 'commande_partielle_mise_a_jour', 'achats_materiaux', $currentOrderId, $details);
                }
            }

            return [
                'success' => true,
                'message' => 'Commande partielle mise à jour, reste à commander: ' . $remainingQuantity,
                'is_complete' => false,
                'remaining_quantity' => $remainingQuantity,
                'original_quantity' => $currentOrder['original_quantity'],
                'total_ordered' => $totalOrdered,
                'avg_price' => $avgPrice
            ];
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur lors du regroupement des commandes partielles: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Calcule le prix moyen pondéré basé sur les quantités et prix unitaires
 * 
 * @param array $ordersWithPrices Tableau contenant les paires quantité/prix
 * @return float Prix moyen pondéré
 */
function calculateWeightedAveragePrice($ordersWithPrices)
{
    $totalWeight = 0;
    $weightedSum = 0;

    foreach ($ordersWithPrices as $order) {
        $quantity = floatval($order['quantity']);
        $price = floatval($order['prix_unitaire']);

        if ($quantity > 0 && $price > 0) {
            $totalWeight += $quantity;
            $weightedSum += $quantity * $price;
        }
    }

    if ($totalWeight > 0) {
        return $weightedSum / $totalWeight;
    }

    // Retourner une valeur par défaut si aucun calcul n'est possible
    return 0;
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

            // Journaliser l'action
            if (function_exists('logSystemEvent')) {
                $details = json_encode([
                    'order_id' => $orderId,
                    'requested_qty' => $requestedQty,
                    'original_qty' => $originalQty,
                    'remaining_qty' => $originalQty - $requestedQty
                ]);

                // Récupérer l'ID utilisateur de la session
                $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

                logSystemEvent($pdo, $userId, 'commande_marquee_partielle', 'achats_materiaux', $orderId, $details);
            }
        }

        return $isPartial;
    } catch (Exception $e) {
        error_log("Erreur lors du marquage d'une commande partielle: " . $e->getMessage());
        return false;
    }
}