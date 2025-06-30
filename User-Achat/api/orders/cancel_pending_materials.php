<?php
/**
 * API pour annuler des matériaux en attente de commande
 * 
 * Ce script permet d'annuler un ou plusieurs matériaux en attente
 * en mettant à jour les tables expression_dym/besoins et en libérant les quantités réservées
 */

// Initialisation
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non connecté'
    ]);
    exit;
}

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Connexion à la base de données
require_once '../../../database/connection.php';

// Vérifier que les paramètres requis sont présents
if (!isset($_POST['materials']) || !isset($_POST['reason'])) {
    // Journaliser les paramètres reçus pour débogage
    error_log("Paramètres reçus : " . json_encode($_POST));

    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants'
    ]);
    exit;
}

// Récupérer les paramètres
$materialsJson = $_POST['materials'];
$materials = json_decode($materialsJson, true);
$reason = $_POST['reason'];

// Vérifier que materials est un tableau valide
if (!is_array($materials) || empty($materials)) {
    error_log("Materials non valide : " . $materialsJson);

    echo json_encode([
        'success' => false,
        'message' => 'Liste de matériaux invalide'
    ]);
    exit;
}

// Récupérer le nom de l'utilisateur pour les logs
$username = '';
$userQuery = "SELECT name FROM users_exp WHERE id = :user_id";
$userStmt = $pdo->prepare($userQuery);
$userStmt->bindParam(':user_id', $user_id);
$userStmt->execute();
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
if ($userInfo) {
    $username = $userInfo['name'];
}

// Adresse IP pour les logs
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
    // Démarrer une transaction
    $pdo->beginTransaction();

    // Compteurs pour le résumé
    $canceledCount = 0;
    $errorCount = 0;
    $logEntries = [];
    $quantitiesReleased = 0;

    // Traiter chaque matériau
    foreach ($materials as $material) {
        // Vérifier que les champs requis sont présents
        if (!isset($material['id']) || !isset($material['expressionId']) || !isset($material['designation'])) {
            $errorCount++;
            continue;
        }

        $id = $material['id'];
        $expressionId = $material['expressionId'];
        $designation = $material['designation'];
        $sourceTable = $material['sourceTable'] ?? 'expression_dym'; // Source du matériau

        try {
            // Traiter selon la source du matériau
            if ($sourceTable === 'besoins') {
                // Vérifier que l'expressionId est bien défini pour les besoins
                if ($sourceTable === 'besoins' && (empty($expressionId) || $expressionId === 'undefined')) {
                    // Tentative de récupération de l'ID du besoin directement depuis la base de données
                    $idBesoinQuery = "SELECT idBesoin FROM besoins WHERE id = :id LIMIT 1";
                    $idBesoinStmt = $pdo->prepare($idBesoinQuery);
                    $idBesoinStmt->bindParam(':id', $id);
                    $idBesoinStmt->execute();
                    $idBesoinResult = $idBesoinStmt->fetch(PDO::FETCH_ASSOC);

                    if ($idBesoinResult && !empty($idBesoinResult['idBesoin'])) {
                        $expressionId = $idBesoinResult['idBesoin'];
                        error_log("ID de besoin récupéré depuis la base: ID=$id, ExpressionID=$expressionId");
                    } else {
                        error_log("Impossible de trouver l'ID du besoin pour: ID=$id");
                        $errorCount++;
                        continue;
                    }
                }

                // 1. Récupérer les informations du matériau depuis besoins
                $materialQuery = "SELECT b.id as b_id, b.idBesoin, b.designation_article as designation, 
                                b.qt_demande, b.qt_acheter, b.caracteristique as unit, b.achat_status,
                                CONCAT('SYS-', COALESCE(d.service_demandeur, 'Système')) as code_projet,
                                COALESCE(d.client, 'Demande interne') as nom_client
                          FROM besoins b
                          LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                          WHERE b.id = :id AND b.idBesoin = :expressionId 
                          AND (b.achat_status IS NULL OR b.achat_status = 'pas validé' OR b.achat_status = '')
                          LIMIT 1";

                $materialStmt = $pdo->prepare($materialQuery);
                $materialStmt->bindParam(':id', $id);
                $materialStmt->bindParam(':expressionId', $expressionId);
                $materialStmt->execute();
                $materialInfo = $materialStmt->fetch(PDO::FETCH_ASSOC);

                if (!$materialInfo) {
                    error_log("Matériau non trouvé (besoins): ID=$id, ExpressionID=$expressionId");
                    $errorCount++;
                    continue;
                }

                // 2. Stocker l'annulation dans la table canceled_orders_log
                $logQuery = "INSERT INTO canceled_orders_log 
                            (order_id, project_id, designation, canceled_by, cancel_reason, original_status, is_partial, canceled_at)
                            VALUES 
                            (0, :project_id, :designation, :canceled_by, :cancel_reason, :original_status, 0, NOW())";

                $logStmt = $pdo->prepare($logQuery);
                $logStmt->bindParam(':project_id', $expressionId);
                $logStmt->bindParam(':designation', $designation);
                $logStmt->bindParam(':canceled_by', $user_id);
                $logStmt->bindParam(':cancel_reason', $reason);
                $originalStatus = $materialInfo['achat_status'] ?? 'pas validé';
                $logStmt->bindParam(':original_status', $originalStatus);
                $logStmt->execute();

                // 3. Mettre à jour le statut dans besoins
                $updateBesoinsQuery = "UPDATE besoins 
                                      SET achat_status = 'annulé',
                                      quantity_reserved = 0 
                                      WHERE id = :id AND idBesoin = :expressionId";

                $updateBesoinsStmt = $pdo->prepare($updateBesoinsQuery);
                $updateBesoinsStmt->bindParam(':id', $id);
                $updateBesoinsStmt->bindParam(':expressionId', $expressionId);
                $updateResult = $updateBesoinsStmt->execute();

                // Vérifier si la mise à jour a réussi
                if ($updateResult && $updateBesoinsStmt->rowCount() > 0) {
                    error_log("Mise à jour réussie pour besoins : ID=$id, ExpressionID=$expressionId");
                } else {
                    error_log("Échec de la mise à jour pour besoins : ID=$id, ExpressionID=$expressionId");
                }

                // 4. Calculer les quantités à libérer dans products
                $quantityRequested = 0;
                if (isset($materialInfo['qt_acheter']) && $materialInfo['qt_acheter'] > 0) {
                    $quantityRequested = floatval($materialInfo['qt_acheter']);
                } elseif (isset($materialInfo['qt_demande']) && $materialInfo['qt_demande'] > 0) {
                    $quantityRequested = floatval($materialInfo['qt_demande']);
                }

                // Vérifier si le produit existe dans la table products
                $findProductQuery = "SELECT id, product_name, quantity, quantity_reserved FROM products 
                                   WHERE product_name = :designation";
                $findProductStmt = $pdo->prepare($findProductQuery);
                $findProductStmt->bindParam(':designation', $designation);
                $findProductStmt->execute();
                $productInfo = $findProductStmt->fetch(PDO::FETCH_ASSOC);

                $productUpdated = false;
                if ($productInfo && $quantityRequested > 0) {
                    // S'assurer que nous ne mettons pas une valeur négative
                    $newReservedQuantity = max(0, floatval($productInfo['quantity_reserved']) - $quantityRequested);

                    // Mettre à jour le produit pour libérer uniquement la quantité de ce projet
                    $updateProductQuery = "UPDATE products 
                                         SET quantity_reserved = :new_reserved
                                         WHERE id = :productId";

                    $updateProductStmt = $pdo->prepare($updateProductQuery);
                    $updateProductStmt->bindParam(':new_reserved', $newReservedQuantity);
                    $updateProductStmt->bindParam(':productId', $productInfo['id']);
                    $updateProductStmt->execute();

                    // Journaliser la mise à jour de quantity_reserved dans products
                    error_log("Produit ID {$productInfo['id']}: quantity_reserved réduit de {$productInfo['quantity_reserved']} à {$newReservedQuantity}");

                    $productUpdated = true;
                    $quantitiesReleased++;
                }

                // 5. Ajouter un log système pour la libération des quantités
                $releaseLogDetails = "Annulation de matériau en attente: $designation pour le besoin système {$materialInfo['code_projet']}. Quantité libérée: $quantityRequested {$materialInfo['unit']}.";
                if ($productUpdated) {
                    $releaseLogDetails .= " Quantité réservée réduite pour le produit '{$productInfo['product_name']}' (ID: {$productInfo['id']}) de {$quantityRequested} unités.";
                }

                $releaseLogQuery = "INSERT INTO system_logs 
                                  (user_id, username, action, type, entity_id, entity_name, details, ip_address) 
                                  VALUES 
                                  (:user_id, :username, 'annulation_materiau', 'materiau', 
                                  :entity_id, :entity_name, :details, :ip_address)";

                $releaseLogStmt = $pdo->prepare($releaseLogQuery);
                $releaseLogStmt->bindParam(':user_id', $user_id);
                $releaseLogStmt->bindParam(':username', $username);
                $releaseLogStmt->bindParam(':entity_id', $expressionId);
                $releaseLogStmt->bindParam(':entity_name', $designation);
                $releaseLogStmt->bindParam(':details', $releaseLogDetails);
                $releaseLogStmt->bindParam(':ip_address', $ipAddress);
                $releaseLogStmt->execute();

            } else {
                // Traitement pour expression_dym
                // 1. Récupérer les informations du matériau
                $materialQuery = "SELECT ed.id as ed_id, ed.idExpression, ed.designation, 
                                ed.quantity, ed.qt_acheter, ed.quantity_reserved, 
                                ed.unit, ed.prix_unitaire, ed.valide_achat,
                                ip.code_projet, ip.nom_client
                          FROM expression_dym ed
                          LEFT JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                          WHERE ed.id = :id AND ed.idExpression = :expressionId 
                          AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
                          LIMIT 1";

                $materialStmt = $pdo->prepare($materialQuery);
                $materialStmt->bindParam(':id', $id);
                $materialStmt->bindParam(':expressionId', $expressionId);
                $materialStmt->execute();
                $materialInfo = $materialStmt->fetch(PDO::FETCH_ASSOC);

                if (!$materialInfo) {
                    error_log("Matériau non trouvé (expression_dym): ID=$id, ExpressionID=$expressionId");
                    $errorCount++;
                    continue;
                }

                // 2. Stocker l'annulation dans la table canceled_orders_log
                $logQuery = "INSERT INTO canceled_orders_log 
                            (order_id, project_id, designation, canceled_by, cancel_reason, original_status, is_partial, canceled_at)
                            VALUES 
                            (0, :project_id, :designation, :canceled_by, :cancel_reason, :original_status, 0, NOW())";

                $logStmt = $pdo->prepare($logQuery);
                $logStmt->bindParam(':project_id', $expressionId);
                $logStmt->bindParam(':designation', $designation);
                $logStmt->bindParam(':canceled_by', $user_id);
                $logStmt->bindParam(':cancel_reason', $reason);
                $originalStatus = $materialInfo['valide_achat'] ?? 'pas validé';
                $logStmt->bindParam(':original_status', $originalStatus);
                $logStmt->execute();

                // 3. Mettre à jour le statut dans expression_dym
                $updateExpressionQuery = "UPDATE expression_dym 
                                        SET valide_achat = 'annulé', 
                                            quantity_reserved = 0
                                        WHERE id = :id AND idExpression = :expressionId";

                $updateExpressionStmt = $pdo->prepare($updateExpressionQuery);
                $updateExpressionStmt->bindParam(':id', $id);
                $updateExpressionStmt->bindParam(':expressionId', $expressionId);
                $updateResult = $updateExpressionStmt->execute();

                // Vérifier si la mise à jour a réussi
                if ($updateResult && $updateExpressionStmt->rowCount() > 0) {
                    error_log("Mise à jour réussie pour expression_dym : ID=$id, ExpressionID=$expressionId");
                } else {
                    error_log("Échec de la mise à jour pour expression_dym : ID=$id, ExpressionID=$expressionId");
                }

                // 4. Calculer les quantités à libérer dans products
                $quantityRequested = 0;
                if (isset($materialInfo['qt_acheter']) && $materialInfo['qt_acheter'] > 0) {
                    $quantityRequested = floatval($materialInfo['qt_acheter']);
                } elseif (isset($materialInfo['quantity']) && $materialInfo['quantity'] > 0) {
                    $quantityRequested = floatval($materialInfo['quantity']);
                }

                // Vérifier si le produit existe dans la table products
                $findProductQuery = "SELECT id, product_name, quantity, quantity_reserved FROM products 
                                   WHERE product_name = :designation";
                $findProductStmt = $pdo->prepare($findProductQuery);
                $findProductStmt->bindParam(':designation', $designation);
                $findProductStmt->execute();
                $productInfo = $findProductStmt->fetch(PDO::FETCH_ASSOC);

                $productUpdated = false;
                if ($productInfo && $quantityRequested > 0) {
                    // S'assurer que nous ne mettons pas une valeur négative
                    $newReservedQuantity = max(0, floatval($productInfo['quantity_reserved']) - $quantityRequested);

                    // Mettre à jour le produit pour libérer uniquement la quantité de ce projet
                    $updateProductQuery = "UPDATE products 
                                         SET quantity_reserved = :new_reserved
                                         WHERE id = :productId";

                    $updateProductStmt = $pdo->prepare($updateProductQuery);
                    $updateProductStmt->bindParam(':new_reserved', $newReservedQuantity);
                    $updateProductStmt->bindParam(':productId', $productInfo['id']);
                    $updateProductStmt->execute();

                    // Journaliser la mise à jour de quantity_reserved dans products
                    error_log("Produit ID {$productInfo['id']}: quantity_reserved réduit de {$productInfo['quantity_reserved']} à {$newReservedQuantity}");

                    $productUpdated = true;
                    $quantitiesReleased++;
                }

                // 5. Ajouter un log système pour la libération des quantités
                $releaseLogDetails = "Annulation de matériau en attente: $designation pour le projet {$materialInfo['code_projet']}. Quantité libérée: $quantityRequested {$materialInfo['unit']}.";
                if ($productUpdated) {
                    $releaseLogDetails .= " Quantité réservée réduite pour le produit '{$productInfo['product_name']}' (ID: {$productInfo['id']}) de {$quantityRequested} unités.";
                }

                $releaseLogQuery = "INSERT INTO system_logs 
                                  (user_id, username, action, type, entity_id, entity_name, details, ip_address) 
                                  VALUES 
                                  (:user_id, :username, 'annulation_materiau', 'materiau', 
                                  :entity_id, :entity_name, :details, :ip_address)";

                $releaseLogStmt = $pdo->prepare($releaseLogQuery);
                $releaseLogStmt->bindParam(':user_id', $user_id);
                $releaseLogStmt->bindParam(':username', $username);
                $releaseLogStmt->bindParam(':entity_id', $expressionId);
                $releaseLogStmt->bindParam(':entity_name', $designation);
                $releaseLogStmt->bindParam(':details', $releaseLogDetails);
                $releaseLogStmt->bindParam(':ip_address', $ipAddress);
                $releaseLogStmt->execute();
            }

            $canceledCount++;

        } catch (Exception $itemException) {
            error_log("Erreur lors de l'annulation du matériau: " . $itemException->getMessage());
            $errorCount++;
        }
    }

    // 6. Ajouter un seul log système pour toutes les annulations
    $bulkLogDetails = "Annulation groupée de $canceledCount matériau(x) en attente. Raison: $reason. $quantitiesReleased réservations de produits ajustées.";

    $logSystemQuery = "INSERT INTO system_logs 
                     (user_id, username, action, type, entity_id, entity_name, details, ip_address) 
                     VALUES 
                     (:user_id, :username, 'annulation_groupée', 'materiau', 
                     :entity_id, :entity_name, :details, :ip_address)";

    $logSystemStmt = $pdo->prepare($logSystemQuery);
    $logSystemStmt->bindParam(':user_id', $user_id);
    $logSystemStmt->bindParam(':username', $username);
    $logSystemStmt->bindParam(':entity_id', $user_id);
    $logSystemStmt->bindParam(':entity_name', $username);
    $logSystemStmt->bindParam(':details', $bulkLogDetails);
    $logSystemStmt->bindParam(':ip_address', $ipAddress);
    $logSystemStmt->execute();

    // Valider la transaction
    $pdo->commit();

    // Préparer un message plus détaillé
    $detailedMessage = "Annulation réussie: $canceledCount matériau(x) en attente annulé(s)" .
        ($errorCount > 0 ? ", $errorCount échec(s)" : "") .
        ". Les quantités réservées pour les projets spécifiques ont été ajustées.";

    // Retourner un succès
    echo json_encode([
        'success' => true,
        'message' => $detailedMessage,
        'details' => [
            'canceledCount' => $canceledCount,
            'errorCount' => $errorCount,
            'quantitiesReleased' => $quantitiesReleased
        ]
    ]);

} catch (PDOException $e) {
    // En cas d'erreur, annuler la transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log de l'erreur
    error_log("Erreur lors de l'annulation groupée de matériaux en attente: " . $e->getMessage());

    // Retourner un message d'erreur
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de l\'annulation: ' . $e->getMessage()
    ]);
}