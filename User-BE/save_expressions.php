<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Assurez-vous que ce header est configuré correctement pour votre environnement

// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Lire le JSON du corps de la requête
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !is_array($data)) {
        throw new Exception('Données invalides.');
    }

    // Array to track items that need additional purchases
    $itemsNeedingPurchase = [];

    // Check stock availability and update reserved quantities
    foreach ($data as $item) {
        // Vérifier si le stock disponible est suffisant et récupérer le prix unitaire
        $stockQuery = $pdo->prepare("
            SELECT p.quantity, p.quantity_reserved, p.unit_price 
            FROM products p 
            WHERE p.product_name = :designation
        ");
        $stockQuery->bindParam(':designation', $item['designation']);
        $stockQuery->execute();
        $stockInfo = $stockQuery->fetch(PDO::FETCH_ASSOC);

        $qt_acheter = null;       // Par défaut, aucun achat supplémentaire n'est nécessaire
        $qt_stock = null;         // Par défaut, aucune quantité en stock n'est disponible
        $prix_unitaire = null;    // Le prix unitaire du produit
        $montant = null;          // Le montant total (prix unitaire × quantité)

        if ($stockInfo) {
            // Récupérer le prix unitaire
            $prix_unitaire = $stockInfo['unit_price'];

            // Calculer le montant total
            $montant = $prix_unitaire * floatval($item['quantity']);

            $quantityInStock = $stockInfo['quantity'];
            $quantityReserved = $stockInfo['quantity_reserved'] ?: 0;
            $availableStock = $quantityInStock - $quantityReserved;

            // Si la quantité disponible est négative, l'afficher comme 0 pour l'utilisateur
            $displayAvailableStock = max(0, $availableStock);

            // Si la quantité demandée dépasse le stock disponible
            if (floatval($item['quantity']) > $availableStock) {
                // La quantité qu'on peut prendre du stock est le maximum entre 0 et le stock disponible
                $qt_stock = max(0, $availableStock);

                // Calculer la quantité à acheter (différence entre la demande et le stock disponible)
                $qt_acheter = floatval($item['quantity']) - $availableStock;

                // Si le stock disponible est négatif, la totalité doit être achetée
                if ($availableStock < 0) {
                    $qt_acheter = floatval($item['quantity']);
                    $qt_stock = 0;
                }

                $itemsNeedingPurchase[] = [
                    'designation' => $item['designation'],
                    'requested' => floatval($item['quantity']),
                    'available' => $displayAvailableStock,
                    'to_purchase' => $qt_acheter,
                    'prix_unitaire' => $prix_unitaire,
                    'montant' => $montant
                ];
            } else {
                // Si la quantité demandée est inférieure ou égale au stock disponible,
                // toute la quantité sera fournie à partir du stock
                $qt_stock = floatval($item['quantity']);
            }

            // Mettre à jour la quantité réservée dans la table products
            $updateReservedQuery = $pdo->prepare("UPDATE products SET quantity_reserved = COALESCE(quantity_reserved, 0) + :quantity WHERE product_name = :designation");
            $updateReservedQuery->bindParam(':quantity', $item['quantity']);
            $updateReservedQuery->bindParam(':designation', $item['designation']);
            $updateReservedQuery->execute();
        }

        // MODIFICATION: Ajouter le champ quantity_reserved lors de l'insertion dans expression_dym
        // Initialiser quantity_reserved avec la même valeur que quantity
        $reserved_quantity = floatval($item['quantity']);

        // Préparer la requête d'insertion avec les colonnes prix_unitaire, montant et quantity_reserved
        $stmt = $pdo->prepare("
            INSERT INTO expression_dym (
                idExpression, user_emet, designation, unit, quantity, quantity_reserved, type, 
                qt_stock, qt_acheter, prix_unitaire, montant, valide_stock, valide_achat
            ) VALUES (
                :idExpression, :user_emet, :designation, :unit, :quantity, :quantity_reserved, :type, 
                :qt_stock, :qt_acheter, :prix_unitaire, :montant, 'validé', 'pas validé'
            )
        ");

        // Lier les paramètres
        $stmt->bindParam(':idExpression', $item['idExpression']);
        $stmt->bindParam(':user_emet', $item['userEmet']);
        $stmt->bindParam(':designation', $item['designation']);
        $stmt->bindParam(':unit', $item['unit']);
        $stmt->bindParam(':quantity', $item['quantity']);
        $stmt->bindParam(':quantity_reserved', $reserved_quantity); // Nouveau paramètre
        $stmt->bindParam(':type', $item['type']);
        $stmt->bindParam(':qt_stock', $qt_stock);
        $stmt->bindParam(':qt_acheter', $qt_acheter);
        $stmt->bindParam(':prix_unitaire', $prix_unitaire);
        $stmt->bindParam(':montant', $montant);

        // Exécuter la requête
        $stmt->execute();
    }

    echo json_encode([
        'success' => true,
        'needsPurchase' => !empty($itemsNeedingPurchase),
        'itemsNeedingPurchase' => $itemsNeedingPurchase
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>