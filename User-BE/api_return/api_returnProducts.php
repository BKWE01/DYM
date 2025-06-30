<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Désactiver l'affichage des erreurs pour éviter de corrompre la sortie JSON
ini_set('display_errors', 0);
error_reporting(0);

// Connexion à la base de données
include_once '../../database/connection.php';

try {
    // Récupérer les données envoyées
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        throw new Exception('Données invalides.');
    }

    // Activer le mode debug pour voir ce qui est reçu
    error_log("Données reçues: " . print_r($data, true));

    $returns = $data['returns'] ?? [];
    $projectCompleted = $data['project_completed'] ?? false;
    $projectId = $data['project_id'] ?? '';
    $returnType = $data['return_type'] ?? 'unused'; // Type de retour: 'unused' ou 'partial'
    $removeAllReservations = $data['remove_all_reservations'] ?? false; // Nouveau paramètre

    if (empty($projectId)) {
        throw new Exception('ID de projet manquant.');
    }

    // Récupérer l'ID de l'utilisateur connecté et son rôle
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
    $userType = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';

    // Log pour le débogage
    error_log("Session: " . print_r($_SESSION, true));
    error_log("Type utilisateur: $userType");
    error_log("Projet marqué comme terminé: " . ($projectCompleted ? 'Oui' : 'Non'));
    error_log("Retirer toutes les réservations: " . ($removeAllReservations ? 'Oui' : 'Non'));

    // Vérifier que $pdo est bien défini
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Connexion à la base de données non établie.');
    }

    // Récupérer les informations du projet
    $stmt = $pdo->prepare("SELECT * FROM identification_projet WHERE idExpression = :idExpression");
    $stmt->bindParam(':idExpression', $projectId);
    $stmt->execute();
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception('Projet non trouvé.');
    }

    // Vérifier si une transaction est déjà en cours et la terminer
    if ($pdo->inTransaction()) {
        error_log("Une transaction était déjà en cours, on la termine");
        $pdo->commit();
    }

    // Démarrer une nouvelle transaction
    error_log("Démarrage d'une nouvelle transaction");
    $pdo->beginTransaction();

    $nomClient = $project['nom_client'];
    $returnCount = 0;

    // Récupérer le nom de l'utilisateur pour les logs
    $stmt = $pdo->prepare("SELECT name FROM users_exp WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userName = $user ? $user['name'] : 'Utilisateur';

    // Traiter les retours de produits
    foreach ($returns as $item) {
        $productId = $item['product_id'];
        $quantity = floatval($item['quantity']);
        $designation = $item['designation'];
        $expressionId = isset($item['expression_id']) ? $item['expression_id'] : $projectId;

        // Log pour le débogage
        error_log("Traitement du produit: ID=$productId, Quantité=$quantity");

        if ($quantity <= 0) {
            error_log("Quantité nulle ou négative, ignorée");
            continue; // Ignorer les quantités nulles ou négatives
        }

        // CORRECTION: Vérifier l'existence du produit dans la table products avant de continuer
        $checkProductStmt = $pdo->prepare("SELECT id, quantity, quantity_reserved FROM products WHERE id = :product_id");
        $checkProductStmt->bindParam(':product_id', $productId);
        $checkProductStmt->execute();
        $productData = $checkProductStmt->fetch(PDO::FETCH_ASSOC);

        if (!$productData) {
            error_log("Produit non trouvé dans la table products: ID=$productId");
            continue; // Si le produit n'existe pas, passer au suivant
        }

        // NOUVEAU: Vérifier si le produit est associé au projet
        $checkProjectProductStmt = $pdo->prepare("
            SELECT * FROM expression_dym 
            WHERE idExpression = :idExpression 
            AND LOWER(designation) = LOWER(:designation)
        ");
        $checkProjectProductStmt->bindParam(':idExpression', $expressionId);
        $checkProjectProductStmt->bindParam(':designation', $designation);
        $checkProjectProductStmt->execute();
        $projectProduct = $checkProjectProductStmt->fetch(PDO::FETCH_ASSOC);

        if (!$projectProduct) {
            error_log("Produit non associé au projet: ID=$productId, Projet=$expressionId");
            // Créer une entrée si elle n'existe pas
            $stmt = $pdo->prepare("
                INSERT INTO expression_dym (idExpression, designation, quantity, quantity_reserved)
                VALUES (:idExpression, :designation, :quantity, :quantity_reserved)
            ");
            $zeroQuantity = 0;
            $stmt->bindParam(':idExpression', $expressionId);
            $stmt->bindParam(':designation', $designation);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':quantity_reserved', $quantity);
            $stmt->execute();
            error_log("Entrée créée dans expression_dym pour le produit: $designation");
        }

        // Gérer le retour selon le type
        if ($returnType === 'unused') {
            // Pour les produits non utilisés, on réduit seulement la quantité réservée
            error_log("Retour de produit non utilisé: $designation, quantité=$quantity");

            // Mettre à jour la quantité réservée dans la table products
            $stmt = $pdo->prepare("
                UPDATE products 
                SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity) 
                WHERE id = :product_id
            ");
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':product_id', $productId);
            $result = $stmt->execute();

            if (!$result) {
                error_log("Erreur lors de la mise à jour de la quantité réservée dans products: " . print_r($stmt->errorInfo(), true));
            } else {
                error_log("Mise à jour réussie dans products, lignes affectées: " . $stmt->rowCount());
            }

            // Mettre à jour également la quantité réservée dans la table expression_dym
            $stmt = $pdo->prepare("
                UPDATE expression_dym 
                SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity)
                WHERE idExpression = :idExpression 
                AND LOWER(designation) = LOWER(:designation)
            ");
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':idExpression', $expressionId);
            $stmt->bindParam(':designation', $designation);
            $result = $stmt->execute();

            if (!$result) {
                error_log("Erreur lors de la mise à jour de la quantité réservée dans expression_dym: " . print_r($stmt->errorInfo(), true));
            } else {
                error_log("Mise à jour réussie dans expression_dym, lignes affectées: " . $stmt->rowCount());
            }

            // Enregistrer le mouvement comme un ajustement
            $movementType = 'adjustment';
            $provenance = "Retour produit non utilisé - projet: {$nomClient}";
            $destination = "Ajustement réservation";
        } else {
            // Pour les retours partiels, on augmente la quantité en stock et on diminue la quantité réservée
            error_log("Retour partiel de produit: $designation, quantité=$quantity");

            // Mettre à jour le stock et la quantité réservée
            $stmt = $pdo->prepare("
                UPDATE products 
                SET quantity = quantity + :quantity,
                    quantity_reserved = GREATEST(0, quantity_reserved - :quantity) 
                WHERE id = :product_id
            ");
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':product_id', $productId);
            $result = $stmt->execute();

            if (!$result) {
                error_log("Erreur lors de la mise à jour des quantités dans products: " . print_r($stmt->errorInfo(), true));
            } else {
                error_log("Mise à jour réussie dans products, lignes affectées: " . $stmt->rowCount());
            }

            // Mettre à jour également la quantité réservée dans la table expression_dym
            $stmt = $pdo->prepare("
                UPDATE expression_dym 
                SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity)
                WHERE idExpression = :idExpression 
                AND LOWER(designation) = LOWER(:designation)
            ");
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':idExpression', $expressionId);
            $stmt->bindParam(':designation', $designation);
            $result = $stmt->execute();

            if (!$result) {
                error_log("Erreur lors de la mise à jour de la quantité réservée dans expression_dym: " . print_r($stmt->errorInfo(), true));
            } else {
                error_log("Mise à jour réussie dans expression_dym, lignes affectées: " . $stmt->rowCount());
            }

            // Enregistrer le mouvement comme une entrée
            $movementType = 'input';
            $provenance = "Retour partiel - projet: {$nomClient}";
            $destination = "Stock général";
        }

        // Enregistrer un mouvement de stock
        $stmt = $pdo->prepare("
            INSERT INTO stock_movement (product_id, quantity, movement_type, provenance, destination, nom_projet, demandeur, date) 
            VALUES (:product_id, :quantity, :movement_type, :provenance, :destination, :nom_projet, :demandeur, NOW())
        ");
        $stmt->bindParam(':product_id', $productId);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':movement_type', $movementType);
        $stmt->bindParam(':provenance', $provenance);
        $stmt->bindParam(':destination', $destination);
        $stmt->bindParam(':nom_projet', $nomClient);
        $stmt->bindParam(':demandeur', $userName);
        $result = $stmt->execute();

        if (!$result) {
            error_log("Erreur lors de l'enregistrement du mouvement de stock: " . print_r($stmt->errorInfo(), true));
        } else {
            error_log("Mouvement de stock enregistré avec succès");
            $returnCount++;
        }
    }

    // Si le projet est marqué comme terminé, mettre à jour le statut dans la base de données
    if ($projectCompleted) {
        error_log("Marquage du projet comme terminé");

        // Créer une table project_status si elle n'existe pas déjà
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS project_status (
                id INT AUTO_INCREMENT PRIMARY KEY,
                idExpression VARCHAR(255) NOT NULL,
                status VARCHAR(50) NOT NULL,
                completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_by INT,
                UNIQUE KEY (idExpression)
            )
        ");

        // Insérer ou mettre à jour le statut du projet
        $stmt = $pdo->prepare("
            INSERT INTO project_status (idExpression, status, completed_by) 
            VALUES (:idExpression, 'completed', :completed_by)
            ON DUPLICATE KEY UPDATE 
                status = 'completed', 
                completed_at = CURRENT_TIMESTAMP,
                completed_by = :completed_by
        ");
        $stmt->bindParam(':idExpression', $projectId);
        $stmt->bindParam(':completed_by', $userId);
        $result = $stmt->execute();

        if (!$result) {
            error_log("Erreur lors de la mise à jour du statut du projet: " . print_r($stmt->errorInfo(), true));
        } else {
            error_log("Statut du projet mis à jour avec succès");
        }

        // MODIFICATION: Annuler les commandes en attente en se basant sur expression_dym au lieu de achats_materiaux
        error_log("Annulation des commandes en attente pour le projet terminé");

        // 1. Récupérer les matériaux du projet depuis expression_dym
        $pendingOrdersQuery = "
            SELECT 
                ed.id as ed_id,
                ed.idExpression,
                ed.designation,
                ed.valide_achat,
                GROUP_CONCAT(am.id) as order_ids,
                COUNT(am.id) as order_count
            FROM 
                expression_dym ed
            LEFT JOIN 
                achats_materiaux am ON am.expression_id = ed.idExpression AND LOWER(am.designation) = LOWER(ed.designation)
            WHERE 
                ed.idExpression = :idExpression
                AND (ed.valide_achat = 'pas validé' OR ed.valide_achat = 'validé' OR ed.valide_achat = 'en_cours')
            GROUP BY 
                ed.id, ed.idExpression, ed.designation, ed.valide_achat
        ";

        $pendingOrdersStmt = $pdo->prepare($pendingOrdersQuery);
        $pendingOrdersStmt->bindParam(':idExpression', $projectId);
        $pendingOrdersStmt->execute();
        $pendingExpressions = $pendingOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("Nombre d'expressions à annuler: " . count($pendingExpressions));
        $canceledOrdersCount = 0;

        // 2. Pour chaque expression, trouver et annuler toutes les commandes associées
        foreach ($pendingExpressions as $expression) {
            $expressionId = $expression['idExpression'];
            $designation = $expression['designation'];
            $valideAchat = $expression['valide_achat'];

            error_log("Traitement de l'expression ID={$expression['ed_id']}, Désignation=$designation, Statut=$valideAchat");

            // 2.1 Mettre à jour le statut de l'expression
            $updateExpressionStmt = $pdo->prepare("
        UPDATE expression_dym 
        SET valide_achat = 'annulé'
        WHERE id = :ed_id
    ");
            $updateExpressionStmt->bindParam(':ed_id', $expression['ed_id']);
            $updateExpressionStmt->execute();

            // 2.2 Récupérer toutes les commandes liées à cette expression/désignation
            $ordersStmt = $pdo->prepare("
        SELECT * FROM achats_materiaux
        WHERE expression_id = :expression_id
        AND LOWER(designation) = LOWER(:designation)
        AND (status = 'en_attente' OR status = 'commandé' OR status = 'en_cours')
    ");
            $ordersStmt->bindParam(':expression_id', $expressionId);
            $ordersStmt->bindParam(':designation', $designation);
            $ordersStmt->execute();
            $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

            // Si aucune commande n'est trouvée mais que l'expression a un statut qui doit être annulé,
            // créer un enregistrement dans le log d'annulation avec order_id = 0
            if (empty($orders) && in_array($valideAchat, ['pas validé', 'validé', 'en_cours'])) {
                error_log("Aucune commande trouvée pour l'expression, mais création d'un log d'annulation");

                $logStmt = $pdo->prepare("
            INSERT INTO canceled_orders_log (
                order_id, 
                project_id, 
                designation, 
                canceled_by, 
                cancel_reason,
                original_status,
                is_partial,
                canceled_at
            ) VALUES (
                0,
                :project_id,
                :designation,
                :canceled_by,
                'Projet marqué comme terminé',
                :original_status,
                0,
                NOW()
            )
        ");

                $logStmt->bindParam(':project_id', $projectId);
                $logStmt->bindParam(':designation', $designation);
                $logStmt->bindParam(':canceled_by', $userId);
                $logStmt->bindParam(':original_status', $valideAchat);

                $logStmt->execute();
                $canceledOrdersCount++;
            }

            foreach ($orders as $order) {
                $orderId = $order['id'];
                $orderStatus = $order['status'];
                $isPartial = $order['is_partial'];

                error_log("Annulation de la commande ID=$orderId, Statut=$orderStatus, Désignation=$designation");

                // Mettre à jour le statut de la commande à "annulé"
                $updateOrderStmt = $pdo->prepare("
            UPDATE achats_materiaux 
            SET status = 'annulé',
                canceled_at = NOW(),
                canceled_by = :user_id,
                cancel_reason = 'Projet marqué comme terminé'
            WHERE id = :order_id
        ");
                $updateOrderStmt->bindParam(':order_id', $orderId);
                $updateOrderStmt->bindParam(':user_id', $userId);
                $result = $updateOrderStmt->execute();

                if ($result) {
                    $canceledOrdersCount++;

                    // Enregistrer un log de l'annulation
                    $logStmt = $pdo->prepare("
                INSERT INTO canceled_orders_log (
                    order_id, 
                    project_id, 
                    designation, 
                    canceled_by, 
                    cancel_reason,
                    original_status,
                    is_partial,
                    canceled_at
                ) VALUES (
                    :order_id,
                    :project_id,
                    :designation,
                    :canceled_by,
                    'Projet marqué comme terminé',
                    :original_status,
                    :is_partial,
                    NOW()
                )
            ");

                    $logStmt->bindParam(':order_id', $orderId);
                    $logStmt->bindParam(':project_id', $projectId);
                    $logStmt->bindParam(':designation', $designation);
                    $logStmt->bindParam(':canceled_by', $userId);
                    $logStmt->bindParam(':original_status', $orderStatus);
                    $logStmt->bindParam(':is_partial', $isPartial);

                    $logStmt->execute();
                }
            }
        }

        // Si le projet est terminé ou si on doit retirer toutes les réservations,
        // ajuster toutes les quantités réservées restantes
        if ($removeAllReservations || empty($returns)) {
            error_log("Ajustement automatique des quantités réservées pour le projet terminé");

            // MODIFICATION: Récupérer toutes les expressions avec des quantités réservées
            $stmt = $pdo->prepare("
                SELECT 
                    e.designation,
                    e.quantity,
                    e.quantity_reserved,
                    p.id as product_id,
                    p.quantity_reserved as product_quantity_reserved
                FROM expression_dym e
                JOIN products p ON LOWER(e.designation) = LOWER(p.product_name)
                WHERE e.idExpression = :idExpression
                AND e.quantity_reserved > 0
            ");
            $stmt->bindParam(':idExpression', $projectId);
            $stmt->execute();

            $remainingProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Produits restants à ajuster: " . count($remainingProducts));

            foreach ($remainingProducts as $product) {
                $reserved = floatval($product['quantity_reserved']);

                if ($reserved > 0) {
                    error_log("Ajustement du produit: {$product['designation']}, quantité réservée: $reserved");

                    // Réduire la quantité réservée dans les deux tables
                    // 1. Dans la table products
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity) 
                        WHERE id = :product_id
                    ");
                    $stmt->bindParam(':quantity', $reserved);
                    $stmt->bindParam(':product_id', $product['product_id']);
                    $result = $stmt->execute();

                    if (!$result) {
                        error_log("Erreur lors de l'ajustement automatique dans products: " . print_r($stmt->errorInfo(), true));
                    } else {
                        error_log("Ajustement automatique réussi dans products, lignes affectées: " . $stmt->rowCount());
                    }

                    // 2. Dans la table expression_dym
                    $stmt = $pdo->prepare("
                        UPDATE expression_dym 
                        SET quantity_reserved = 0
                        WHERE idExpression = :idExpression 
                        AND LOWER(designation) = LOWER(:designation)
                    ");
                    $stmt->bindParam(':idExpression', $projectId);
                    $stmt->bindParam(':designation', $product['designation']);
                    $result = $stmt->execute();

                    if (!$result) {
                        error_log("Erreur lors de l'ajustement automatique dans expression_dym: " . print_r($stmt->errorInfo(), true));
                    } else {
                        error_log("Ajustement automatique réussi dans expression_dym, lignes affectées: " . $stmt->rowCount());
                    }

                    // Enregistrer un mouvement de stock
                    $stmt = $pdo->prepare("
                        INSERT INTO stock_movement (
                            product_id, quantity, movement_type, provenance, destination, nom_projet, demandeur, date
                        ) VALUES (
                            :product_id, :quantity, 'adjustment', :provenance, 'Ajustement réservation', :nom_projet, :demandeur, NOW()
                        )
                    ");
                    $provenance = "Ajustement automatique (projet terminé): {$nomClient}";
                    $stmt->bindParam(':product_id', $product['product_id']);
                    $stmt->bindParam(':quantity', $reserved);
                    $stmt->bindParam(':provenance', $provenance);
                    $stmt->bindParam(':nom_projet', $nomClient);
                    $stmt->bindParam(':demandeur', $userName);
                    $result = $stmt->execute();

                    if (!$result) {
                        error_log("Erreur lors de l'enregistrement du mouvement d'ajustement: " . print_r($stmt->errorInfo(), true));
                    } else {
                        error_log("Mouvement d'ajustement enregistré avec succès");
                        $returnCount++;
                    }
                }
            }
        }
    }

    // Valider la transaction uniquement si elle est active
    if ($pdo->inTransaction()) {
        $pdo->commit();
        error_log("Transaction validée avec succès");
    }

    // Message de réussite
    $message = '';
    if ($returnCount > 0) {
        if ($returnType === 'unused') {
            $message .= $returnCount . " produit(s) non utilisé(s) ont été ajustés dans les réservations. ";
        } else {
            $message .= $returnCount . " produit(s) ont été retournés au stock. ";
        }
    }

    if ($projectCompleted) {
        $message .= "Le projet a été marqué comme terminé et toutes les réservations ont été supprimées. ";

        // Ajouter une information sur l'annulation des commandes
        if ($canceledOrdersCount > 0) {
            $message .= $canceledOrdersCount . " commande(s) en attente ont été automatiquement annulée(s) au service achat.";
        }
    } else if (empty($message)) {
        $message = "Aucune modification n'a été effectuée.";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'return_count' => $returnCount,
        'canceled_orders_count' => $canceledOrdersCount ?? 0
    ]);

} catch (Exception $e) {
    // En cas d'erreur, annuler la transaction seulement si elle est active
    if (isset($pdo) && $pdo->inTransaction()) {
        error_log("Annulation de la transaction suite à une erreur");
        $pdo->rollBack();
    }

    error_log("Erreur lors du traitement des retours: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());

    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>