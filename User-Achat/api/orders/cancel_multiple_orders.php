<?php
/**
 * API pour annuler plusieurs commandes
 * 
 * Ce script permet d'annuler une ou plusieurs commandes de matériaux
 * qui n'ont pas encore été reçues en mettant à jour les tables achats_materiaux et expression_dym/besoins
 * et en libérant uniquement les quantités réservées pour les projets spécifiques
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
        if (!isset($material['id']) || !isset($material['designation'])) {
            $errorCount++;
            continue;
        }

        $id = $material['id'];
        $expressionId = $material['expressionId'] ?? '';
        $designation = $material['designation'];
        $sourceTable = $material['sourceTable'] ?? 'expression_dym'; // Source du matériau

        try {
            // Selon la source, utiliser la requête appropriée
            if ($sourceTable === 'besoins') {
                // Si l'expressionId est vide, le récupérer directement depuis la base de données
                if (empty($expressionId) || $expressionId === 'undefined') {
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

                // 1. Récupérer les informations de la commande issue de la table besoins
                $orderQuery = "SELECT b.id as b_id, b.idBesoin, b.designation_article as designation, 
                              b.qt_demande, b.qt_acheter, b.caracteristique as unit, b.achat_status,
                              CONCAT('SYS-', COALESCE(d.service_demandeur, 'Système')) as code_projet,
                              COALESCE(d.client, 'Demande interne') as nom_client,
                              am.id as am_id, am.prix_unitaire, am.fournisseur
                        FROM besoins b
                        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                        LEFT JOIN achats_materiaux am ON am.expression_id = b.idBesoin 
                            AND am.designation = b.designation_article 
                            AND (am.status = 'commandé' OR am.status = 'valide_en_cours')
                        WHERE b.id = :id AND b.idBesoin = :expressionId 
                        AND (b.achat_status = 'validé' OR b.achat_status = 'en_cours' OR b.achat_status = 'commandé')
                        LIMIT 1";

                $orderStmt = $pdo->prepare($orderQuery);
                $orderStmt->bindParam(':id', $id);
                $orderStmt->bindParam(':expressionId', $expressionId);
                $orderStmt->execute();
                $orderInfo = $orderStmt->fetch(PDO::FETCH_ASSOC);

                if (!$orderInfo) {
                    error_log("Commande non trouvée (besoins): ID=$id, ExpressionID=$expressionId");

                    // Essayons de trouver la commande sans la condition sur l'ID
                    $altOrderQuery = "SELECT b.id as b_id, b.idBesoin, b.designation_article as designation, 
                                  b.qt_demande, b.qt_acheter, b.caracteristique as unit, b.achat_status,
                                  CONCAT('SYS-', COALESCE(d.service_demandeur, 'Système')) as code_projet,
                                  COALESCE(d.client, 'Demande interne') as nom_client,
                                  am.id as am_id, am.prix_unitaire, am.fournisseur
                            FROM besoins b
                            LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                            LEFT JOIN achats_materiaux am ON am.expression_id = b.idBesoin 
                                AND am.designation = b.designation_article
                            WHERE b.designation_article = :designation AND b.idBesoin = :expressionId
                            LIMIT 1";

                    $altOrderStmt = $pdo->prepare($altOrderQuery);
                    $altOrderStmt->bindParam(':designation', $designation);
                    $altOrderStmt->bindParam(':expressionId', $expressionId);
                    $altOrderStmt->execute();
                    $orderInfo = $altOrderStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$orderInfo) {
                        $errorCount++;
                        continue;
                    }
                }

                // 2. Stocker l'annulation dans la table canceled_orders_log
                $logQuery = "INSERT INTO canceled_orders_log 
                            (order_id, project_id, designation, canceled_by, cancel_reason, original_status, is_partial, canceled_at)
                            VALUES 
                            (:order_id, :project_id, :designation, :canceled_by, :cancel_reason, :original_status, :is_partial, NOW())";

                $logStmt = $pdo->prepare($logQuery);
                $logStmt->bindParam(':order_id', $orderInfo['am_id']);
                $logStmt->bindParam(':project_id', $expressionId);
                $logStmt->bindParam(':designation', $designation);
                $logStmt->bindParam(':canceled_by', $user_id);
                $logStmt->bindParam(':cancel_reason', $reason);
                $originalStatus = $orderInfo['achat_status'] ?? 'pas validé';
                $logStmt->bindParam(':original_status', $originalStatus);
                $isPartial = ($orderInfo['achat_status'] === 'en_cours') ? 1 : 0;
                $logStmt->bindParam(':is_partial', $isPartial);
                $logStmt->execute();

                // 3. Mettre à jour le statut dans achats_materiaux pour les commandes liées
                $updateOrdersQuery = "UPDATE achats_materiaux 
                                    SET status = 'annulé', 
                                    canceled_at = NOW(),
                                    canceled_by = :user_id,
                                    cancel_reason = :reason
                                    WHERE expression_id = :expressionId 
                                    AND designation = :designation
                                    AND (status = 'commandé' OR status = 'valide_en_cours')";

                $updateOrdersStmt = $pdo->prepare($updateOrdersQuery);
                $updateOrdersStmt->bindParam(':user_id', $user_id);
                $updateOrdersStmt->bindParam(':reason', $reason);
                $updateOrdersStmt->bindParam(':expressionId', $expressionId);
                $updateOrdersStmt->bindParam(':designation', $designation);
                $updateOrdersStmt->execute();

                // Vérifier si des lignes ont été mises à jour
                $updatedRows = $updateOrdersStmt->rowCount();
                error_log("Nombre de commandes mises à jour dans achats_materiaux: $updatedRows");

                // Si aucune mise à jour n'a été effectuée, tenter avec une condition élargie
                if ($updatedRows == 0) {
                    // Essayer une requête plus large pour trouver les commandes correspondantes
                    $findOrderQuery = "SELECT id, status FROM achats_materiaux 
                                   WHERE expression_id = :expressionId 
                                   AND designation = :designation";
                    $findOrderStmt = $pdo->prepare($findOrderQuery);
                    $findOrderStmt->bindParam(':expressionId', $expressionId);
                    $findOrderStmt->bindParam(':designation', $designation);
                    $findOrderStmt->execute();
                    $orders = $findOrderStmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($orders) > 0) {
                        error_log("Commandes trouvées mais non mises à jour: " . json_encode($orders));

                        // Tenter une mise à jour directe par ID
                        foreach ($orders as $order) {
                            $updateByIdQuery = "UPDATE achats_materiaux 
                                            SET status = 'annulé', 
                                            canceled_at = NOW(),
                                            canceled_by = :user_id,
                                            cancel_reason = :reason
                                            WHERE id = :order_id";
                            $updateByIdStmt = $pdo->prepare($updateByIdQuery);
                            $updateByIdStmt->bindParam(':user_id', $user_id);
                            $updateByIdStmt->bindParam(':reason', $reason);
                            $updateByIdStmt->bindParam(':order_id', $order['id']);
                            $updateByIdStmt->execute();

                            $byIdUpdated = $updateByIdStmt->rowCount();
                            error_log("Mise à jour par ID {$order['id']}: $byIdUpdated lignes");
                        }
                    } else {
                        error_log("Aucune commande trouvée pour: ExpressionID=$expressionId, Designation=$designation");
                    }
                }

                // 4. Mettre à jour le statut dans besoins
                $updateBesoinsQuery = "UPDATE besoins 
                                      SET achat_status = 'annulé',
                                      quantity_reserved = 0 
                                      WHERE (id = :id AND idBesoin = :expressionId) 
                                      OR (idBesoin = :expressionId AND designation_article = :designation)";

                $updateBesoinsStmt = $pdo->prepare($updateBesoinsQuery);
                $updateBesoinsStmt->bindParam(':id', $id);
                $updateBesoinsStmt->bindParam(':expressionId', $expressionId);
                $updateBesoinsStmt->bindParam(':designation', $designation);
                $updateResult = $updateBesoinsStmt->execute();

                // Vérifier si la mise à jour a réussi
                if ($updateResult && $updateBesoinsStmt->rowCount() > 0) {
                    error_log("Mise à jour réussie pour besoins : ID=$id, ExpressionID=$expressionId");
                } else {
                    error_log("Échec de la mise à jour pour besoins : ID=$id, ExpressionID=$expressionId");
                }

                // 5. Calculer les quantités à libérer dans products
                // D'abord, vérifier si le produit existe dans la table products
                $findProductQuery = "SELECT id, product_name, quantity, quantity_reserved FROM products 
                                   WHERE product_name = :designation";
                $findProductStmt = $pdo->prepare($findProductQuery);
                $findProductStmt->bindParam(':designation', $designation);
                $findProductStmt->execute();
                $productInfo = $findProductStmt->fetch(PDO::FETCH_ASSOC);

                $productUpdated = false;
                if ($productInfo) {
                    // Récupérer la quantité à libérer pour ce projet spécifique
                    $quantityToRelease = floatval($orderInfo['qt_acheter']);

                    // S'assurer que nous ne mettons pas une valeur négative
                    $newReservedQuantity = max(0, floatval($productInfo['quantity_reserved']) - $quantityToRelease);

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

                // 6. Ajouter un log système pour la libération des quantités
                $releaseLogDetails = "Libération de {$orderInfo['qt_acheter']} {$orderInfo['unit']} de $designation pour le besoin système {$orderInfo['code_projet']} suite à l'annulation de commande.";
                if ($productUpdated) {
                    $releaseLogDetails .= " Quantité réservée réduite pour le produit '{$productInfo['product_name']}' (ID: {$productInfo['id']}) de {$quantityToRelease} unités.";
                }

                $releaseLogQuery = "INSERT INTO system_logs 
                                  (user_id, username, action, type, entity_id, entity_name, details, ip_address) 
                                  VALUES 
                                  (:user_id, :username, 'liberation_quantite', 'stock', 
                                  :entity_id, :entity_name, :details, :ip_address)";

                $releaseLogStmt = $pdo->prepare($releaseLogQuery);
                $releaseLogStmt->bindParam(':user_id', $user_id);
                $releaseLogStmt->bindParam(':username', $username);
                $releaseLogStmt->bindParam(':entity_id', $expressionId);
                $releaseLogStmt->bindParam(':entity_name', $designation);
                $releaseLogStmt->bindParam(':details', $releaseLogDetails);
                $releaseLogStmt->bindParam(':ip_address', $ipAddress);
                $releaseLogStmt->execute();

                // 7. Préparer le log pour l'annulation
                $logDetails = "Annulation de commande: $designation pour le besoin système {$orderInfo['code_projet']} - {$orderInfo['nom_client']}. Raison: $reason";
                $logEntries[] = [
                    'entity_id' => $expressionId,
                    'entity_name' => $designation,
                    'details' => $logDetails
                ];

            } else {
                // GESTION POUR expression_dym
                // Si l'expressionId est vide, essayer de le récupérer
                if (empty($expressionId) || $expressionId === 'undefined') {
                    $idExpQuery = "SELECT idExpression FROM expression_dym WHERE id = :id LIMIT 1";
                    $idExpStmt = $pdo->prepare($idExpQuery);
                    $idExpStmt->bindParam(':id', $id);
                    $idExpStmt->execute();
                    $idExpResult = $idExpStmt->fetch(PDO::FETCH_ASSOC);

                    if ($idExpResult && !empty($idExpResult['idExpression'])) {
                        $expressionId = $idExpResult['idExpression'];
                        error_log("ID d'expression récupéré depuis la base: ID=$id, ExpressionID=$expressionId");
                    } else {
                        error_log("Impossible de trouver l'ID d'expression pour: ID=$id");
                        $errorCount++;
                        continue;
                    }
                }

                // 1. Récupérer les informations de la commande
                $orderQuery = "SELECT am.id as am_id, ed.id as ed_id, ed.idExpression, ed.designation, 
                              ed.quantity, ed.qt_acheter, ed.quantity_reserved, 
                              ed.unit, ed.prix_unitaire, ed.fournisseur, ed.valide_achat,
                              ip.code_projet, ip.nom_client
                        FROM expression_dym ed
                        LEFT JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                        LEFT JOIN achats_materiaux am ON am.expression_id = ed.idExpression 
                            AND am.designation = ed.designation 
                            AND (am.status = 'commandé' OR am.status = 'valide_en_cours')
                        WHERE ed.id = :id AND ed.idExpression = :expressionId 
                        AND (ed.valide_achat = 'validé' OR ed.valide_achat = 'en_cours' OR ed.valide_achat = 'commandé')
                        LIMIT 1";

                $orderStmt = $pdo->prepare($orderQuery);
                $orderStmt->bindParam(':id', $id);
                $orderStmt->bindParam(':expressionId', $expressionId);
                $orderStmt->execute();
                $orderInfo = $orderStmt->fetch(PDO::FETCH_ASSOC);

                if (!$orderInfo) {
                    error_log("Commande non trouvée (expression_dym): ID=$id, ExpressionID=$expressionId");

                    // Essayons de trouver la commande sans la condition sur l'ID
                    $altOrderQuery = "SELECT ed.id as ed_id, ed.idExpression, ed.designation, 
                                    ed.quantity, ed.qt_acheter, ed.quantity_reserved, 
                                    ed.unit, ed.prix_unitaire, ed.fournisseur, ed.valide_achat,
                                    ip.code_projet, ip.nom_client,
                                    am.id as am_id
                              FROM expression_dym ed
                              LEFT JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                              LEFT JOIN achats_materiaux am ON am.expression_id = ed.idExpression 
                                  AND am.designation = ed.designation
                              WHERE ed.designation = :designation AND ed.idExpression = :expressionId
                              LIMIT 1";

                    $altOrderStmt = $pdo->prepare($altOrderQuery);
                    $altOrderStmt->bindParam(':designation', $designation);
                    $altOrderStmt->bindParam(':expressionId', $expressionId);
                    $altOrderStmt->execute();
                    $orderInfo = $altOrderStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$orderInfo) {
                        $errorCount++;
                        continue;
                    }
                }

                // 2. Stocker l'annulation dans la table canceled_orders_log
                $logQuery = "INSERT INTO canceled_orders_log 
                            (order_id, project_id, designation, canceled_by, cancel_reason, original_status, is_partial, canceled_at)
                            VALUES 
                            (:order_id, :project_id, :designation, :canceled_by, :cancel_reason, :original_status, :is_partial, NOW())";

                $logStmt = $pdo->prepare($logQuery);
                $logStmt->bindParam(':order_id', $orderInfo['am_id']);
                $logStmt->bindParam(':project_id', $expressionId);
                $logStmt->bindParam(':designation', $designation);
                $logStmt->bindParam(':canceled_by', $user_id);
                $logStmt->bindParam(':cancel_reason', $reason);
                $originalStatus = $orderInfo['valide_achat'] ?? 'pas validé';
                $logStmt->bindParam(':original_status', $originalStatus);
                $isPartial = ($orderInfo['valide_achat'] === 'en_cours') ? 1 : 0;
                $logStmt->bindParam(':is_partial', $isPartial);
                $logStmt->execute();

                // 3. Mettre à jour le statut dans achats_materiaux pour les commandes liées
                $updateOrdersQuery = "UPDATE achats_materiaux 
                                    SET status = 'annulé', 
                                    canceled_at = NOW(),
                                    canceled_by = :user_id,
                                    cancel_reason = :reason
                                    WHERE expression_id = :expressionId 
                                    AND designation = :designation
                                    AND (status = 'commandé' OR status = 'valide_en_cours')";

                $updateOrdersStmt = $pdo->prepare($updateOrdersQuery);
                $updateOrdersStmt->bindParam(':user_id', $user_id);
                $updateOrdersStmt->bindParam(':reason', $reason);
                $updateOrdersStmt->bindParam(':expressionId', $expressionId);
                $updateOrdersStmt->bindParam(':designation', $designation);
                $updateOrdersStmt->execute();

                // Vérifier si des lignes ont été mises à jour
                $updatedRows = $updateOrdersStmt->rowCount();
                error_log("Nombre de commandes mises à jour dans achats_materiaux: $updatedRows");

                // Si aucune mise à jour n'a été effectuée, tenter avec une condition élargie
                if ($updatedRows == 0) {
                    // Essayer une requête plus large pour trouver les commandes correspondantes
                    $findOrderQuery = "SELECT id, status FROM achats_materiaux 
                                   WHERE expression_id = :expressionId 
                                   AND designation = :designation";
                    $findOrderStmt = $pdo->prepare($findOrderQuery);
                    $findOrderStmt->bindParam(':expressionId', $expressionId);
                    $findOrderStmt->bindParam(':designation', $designation);
                    $findOrderStmt->execute();
                    $orders = $findOrderStmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($orders) > 0) {
                        error_log("Commandes trouvées mais non mises à jour: " . json_encode($orders));

                        // Tenter une mise à jour directe par ID
                        foreach ($orders as $order) {
                            $updateByIdQuery = "UPDATE achats_materiaux 
                                            SET status = 'annulé', 
                                            canceled_at = NOW(),
                                            canceled_by = :user_id,
                                            cancel_reason = :reason
                                            WHERE id = :order_id";
                            $updateByIdStmt = $pdo->prepare($updateByIdQuery);
                            $updateByIdStmt->bindParam(':user_id', $user_id);
                            $updateByIdStmt->bindParam(':reason', $reason);
                            $updateByIdStmt->bindParam(':order_id', $order['id']);
                            $updateByIdStmt->execute();

                            $byIdUpdated = $updateByIdStmt->rowCount();
                            error_log("Mise à jour par ID {$order['id']}: $byIdUpdated lignes");
                        }
                    } else {
                        error_log("Aucune commande trouvée pour: ExpressionID=$expressionId, Designation=$designation");
                    }
                }

                // 4. Mettre à jour le statut dans expression_dym et réinitialiser quantity_reserved
                $updateExpressionQuery = "UPDATE expression_dym 
                                        SET valide_achat = 'annulé', 
                                            quantity_reserved = 0 
                                        WHERE (id = :id AND idExpression = :expressionId)
                                        OR (idExpression = :expressionId AND designation = :designation)";

                $updateExpressionStmt = $pdo->prepare($updateExpressionQuery);
                $updateExpressionStmt->bindParam(':id', $id);
                $updateExpressionStmt->bindParam(':expressionId', $expressionId);
                $updateExpressionStmt->bindParam(':designation', $designation);
                $updateResult = $updateExpressionStmt->execute();

                // Vérifier si la mise à jour a réussi
                if ($updateResult && $updateExpressionStmt->rowCount() > 0) {
                    error_log("Mise à jour réussie pour expression_dym : ID=$id, ExpressionID=$expressionId");
                } else {
                    error_log("Échec de la mise à jour pour expression_dym : ID=$id, ExpressionID=$expressionId");
                }

                // 5. Calculer les quantités à libérer dans products
                // D'abord, vérifier si le produit existe dans la table products
                $findProductQuery = "SELECT id, product_name, quantity, quantity_reserved FROM products 
                                   WHERE product_name = :designation";
                $findProductStmt = $pdo->prepare($findProductQuery);
                $findProductStmt->bindParam(':designation', $designation);
                $findProductStmt->execute();
                $productInfo = $findProductStmt->fetch(PDO::FETCH_ASSOC);

                $productUpdated = false;
                if ($productInfo) {
                    // Récupérer la quantité à libérer pour ce projet spécifique
                    $quantityToRelease = floatval($orderInfo['qt_acheter']);

                    // S'assurer que nous ne mettons pas une valeur négative
                    $newReservedQuantity = max(0, floatval($productInfo['quantity_reserved']) - $quantityToRelease);

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

                // 6. Ajouter un log système pour la libération des quantités
                $releaseLogDetails = "Libération de {$orderInfo['qt_acheter']} {$orderInfo['unit']} de $designation pour le projet {$orderInfo['code_projet']} suite à l'annulation de commande.";
                if ($productUpdated) {
                    $releaseLogDetails .= " Quantité réservée réduite pour le produit '{$productInfo['product_name']}' (ID: {$productInfo['id']}) de {$quantityToRelease} unités.";
                }

                $releaseLogQuery = "INSERT INTO system_logs 
                                  (user_id, username, action, type, entity_id, entity_name, details, ip_address) 
                                  VALUES 
                                  (:user_id, :username, 'liberation_quantite', 'stock', 
                                  :entity_id, :entity_name, :details, :ip_address)";

                $releaseLogStmt = $pdo->prepare($releaseLogQuery);
                $releaseLogStmt->bindParam(':user_id', $user_id);
                $releaseLogStmt->bindParam(':username', $username);
                $releaseLogStmt->bindParam(':entity_id', $expressionId);
                $releaseLogStmt->bindParam(':entity_name', $designation);
                $releaseLogStmt->bindParam(':details', $releaseLogDetails);
                $releaseLogStmt->bindParam(':ip_address', $ipAddress);
                $releaseLogStmt->execute();

                // 7. Préparer le log pour l'annulation
                $logDetails = "Annulation de commande: $designation pour le projet {$orderInfo['code_projet']} - {$orderInfo['nom_client']}. Raison: $reason";
                $logEntries[] = [
                    'entity_id' => $expressionId,
                    'entity_name' => $designation,
                    'details' => $logDetails
                ];
            }

            $canceledCount++;

        } catch (Exception $itemException) {
            error_log("Erreur lors de l'annulation de l'item: " . $itemException->getMessage());
            $errorCount++;
        }
    }

    // 8. Ajouter un seul log système pour toutes les annulations
    $bulkLogDetails = "Annulation groupée de $canceledCount commande(s). Raison: $reason. $quantitiesReleased réservations de produits ajustées.";

    $logSystemQuery = "INSERT INTO system_logs 
                     (user_id, username, action, type, entity_id, entity_name, details, ip_address) 
                     VALUES 
                     (:user_id, :username, 'annulation_groupée', 'commande', 
                     :entity_id, :entity_name, :details, :ip_address)";

    $logSystemStmt = $pdo->prepare($logSystemQuery);
    $logSystemStmt->bindParam(':user_id', $user_id);
    $logSystemStmt->bindParam(':username', $username);
    $logSystemStmt->bindParam(':entity_id', $user_id); // Utiliser l'ID de l'utilisateur comme référence
    $logSystemStmt->bindParam(':entity_name', $username);
    $logSystemStmt->bindParam(':details', $bulkLogDetails);
    $logSystemStmt->bindParam(':ip_address', $ipAddress);
    $logSystemStmt->execute();

    // Valider la transaction
    $pdo->commit();

    // Préparer un message plus détaillé
    $detailedMessage = "Annulation réussie: $canceledCount commande(s) annulée(s)" .
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
    error_log("Erreur lors de l'annulation groupée de commandes: " . $e->getMessage());

    // Retourner un message d'erreur
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de l\'annulation: ' . $e->getMessage()
    ]);
}