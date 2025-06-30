<?php
// Ce script doit être exécuté une fois pour synchroniser les quantités réservées 
// entre la table products et la table expression_dym

header('Content-Type: text/html; charset=utf-8');

require_once '../database/connection.php';

try {

    echo "<h1>Outil de synchronisation des quantités réservées</h1>";
    echo "<p>Début du processus de synchronisation...</p>";

    // 1. Récupérer tous les produits avec des quantités réservées
    $sqlProducts = "SELECT id, product_name, quantity_reserved FROM products WHERE quantity_reserved > 0";
    $stmtProducts = $pdo->prepare($sqlProducts);
    $stmtProducts->execute();
    $productsWithReservations = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Nombre de produits avec des réservations trouvés: " . count($productsWithReservations) . "</p>";

    $pdo->beginTransaction();

    $updatedCount = 0;
    $errorCount = 0;

    // Pour chaque produit réservé, mettre à jour la table expression_dym correspondante
    foreach ($productsWithReservations as $product) {
        // Vérifier si une expression existe pour ce produit
        $sqlCheck = "SELECT ed.id, ed.idExpression, ed.designation, ed.quantity 
                     FROM expression_dym ed 
                     WHERE LOWER(ed.designation) = LOWER(:product_name)";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute(['product_name' => $product['product_name']]);
        $expressions = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

        if (count($expressions) > 0) {
            // Répartir la quantité réservée entre les expressions existantes
            $remainingReservation = $product['quantity_reserved'];

            foreach ($expressions as $expression) {
                if ($remainingReservation <= 0)
                    break;

                // La quantité à réserver pour cette expression ne peut pas dépasser la quantité totale demandée
                $quantityToReserve = min($remainingReservation, $expression['quantity']);

                // Mettre à jour la quantité réservée pour cette expression
                $sqlUpdate = "UPDATE expression_dym 
                              SET quantity_reserved = :quantity_reserved 
                              WHERE id = :id";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    'quantity_reserved' => $quantityToReserve,
                    'id' => $expression['id']
                ]);

                $remainingReservation -= $quantityToReserve;
                $updatedCount++;

                echo "<p>✅ Mise à jour de l'expression ID: " . $expression['id'] .
                    ", Produit: " . $expression['designation'] .
                    ", Quantité réservée: " . $quantityToReserve . "</p>";
            }

            // S'il reste encore des réservations non attribuées
            if ($remainingReservation > 0) {
                echo "<p>⚠️ Attention: Impossible d'attribuer " . $remainingReservation .
                    " unités réservées pour le produit '" . $product['product_name'] .
                    "'. Quantité d'expression insuffisante.</p>";
            }
        } else {
            echo "<p>❌ Aucune expression trouvée pour le produit '" . $product['product_name'] .
                "'. Impossible d'attribuer " . $product['quantity_reserved'] . " unités réservées.</p>";
            $errorCount++;
        }
    }

    // Valider la transaction
    $pdo->commit();

    echo "<h2>Synchronisation terminée</h2>";
    echo "<p>Nombre d'expressions mises à jour: " . $updatedCount . "</p>";
    echo "<p>Nombre d'erreurs: " . $errorCount . "</p>";
    echo "<p><a href='../reservations/reservations_details.php'>Voir la page des réservations</a></p>";

} catch (PDOException $e) {
    // En cas d'erreur, annuler la transaction
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "<h2>Erreur pendant la synchronisation</h2>";
    echo "<p>Message d'erreur: " . $e->getMessage() . "</p>";
}