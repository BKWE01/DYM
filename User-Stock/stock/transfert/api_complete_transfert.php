<?php
header('Content-Type: application/json');
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette ressource.'
    ]);
    exit;
}

// Connexion à la base de données
include_once '../../../database/connection.php';

// Récupérer les données envoyées
$data = json_decode(file_get_contents('php://input'), true);
$transfertId = isset($data['id']) ? intval($data['id']) : 0;

if ($transfertId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de transfert invalide.'
    ]);
    exit;
}

try {
    // Commencer une transaction
    $pdo->beginTransaction();

    // 1. Vérifier que le transfert existe et est en attente
    $checkSql = "
        SELECT 
            t.*,
            p.product_name,
            sp.idExpression AS source_expression_id,
            dp.idExpression AS destination_expression_id
        FROM 
            transferts t
        JOIN 
            products p ON t.product_id = p.id
        JOIN 
            identification_projet sp ON t.source_project_id = sp.id
        JOIN 
            identification_projet dp ON t.destination_project_id = dp.id
        WHERE 
            t.id = :transfert_id
            AND t.status = 'pending'
    ";

    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute(['transfert_id' => $transfertId]);
    $transfert = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$transfert) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Transfert non trouvé ou déjà traité.'
        ]);
        exit;
    }

    // 2. Vérifier si la quantité réservée dans le projet source est toujours suffisante
    // D'abord, vérifier dans expression_dym
    $checkSourceSql = "
    SELECT 
        COALESCE(SUM(quantity_reserved), 0) as total_reserved_expression
    FROM 
        expression_dym
    WHERE 
        idExpression = :source_expression_id
        AND (
            LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
            OR LOWER(TRIM(designation)) LIKE CONCAT('%', LOWER(TRIM(:product_name)), '%')
        )
    ";

    $checkSourceStmt = $pdo->prepare($checkSourceSql);
    $checkSourceStmt->execute([
        'source_expression_id' => $transfert['source_expression_id'],
        'product_name' => $transfert['product_name']
    ]);
    $sourceReservation = $checkSourceStmt->fetch(PDO::FETCH_ASSOC);
    $sourceReservedFromExpression = floatval($sourceReservation['total_reserved_expression']);

    // Vérifier dans achats_materiaux en incluant le statut 'reçu'
    $checkAchatsSql = "
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'commandé' THEN quantity ELSE 0 END), 0) as commande_qty,
        COALESCE(SUM(CASE WHEN status = 'en_cours' THEN quantity ELSE 0 END), 0) as encours_qty,
        COALESCE(SUM(CASE WHEN status = 'reçu' THEN quantity ELSE 0 END), 0) as recu_qty,
        COALESCE(SUM(quantity), 0) as total_quantity
    FROM 
        achats_materiaux
    WHERE 
        expression_id = :source_expression_id
        AND (
            LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
            OR LOWER(TRIM(designation)) LIKE CONCAT('%', LOWER(TRIM(:product_name)), '%')
        )
    ";

    $checkAchatsStmt = $pdo->prepare($checkAchatsSql);
    $checkAchatsStmt->execute([
        'source_expression_id' => $transfert['source_expression_id'],
        'product_name' => $transfert['product_name']
    ]);
    $sourceAchat = $checkAchatsStmt->fetch(PDO::FETCH_ASSOC);

    // Prioriser la quantité depuis achats_materiaux avec statut 'reçu'
    // puisque nous voulons transférer des produits reçus
    $sourceReservedQuantity = floatval($sourceAchat['recu_qty']);

    // Si la quantité reçue n'est pas suffisante, considérer aussi les autres statuts
    if ($sourceReservedQuantity < $transfert['quantity']) {
        $sourceReservedQuantity += floatval($sourceAchat['commande_qty']) + floatval($sourceAchat['encours_qty']);
    }

    // Si toujours insuffisant, vérifier la quantité dans expression_dym
    if ($sourceReservedQuantity < $transfert['quantity'] && $sourceReservedFromExpression > 0) {
        $sourceReservedQuantity = max($sourceReservedQuantity, $sourceReservedFromExpression);
    }

    // Ajouter une petite tolérance pour les erreurs d'arrondi
    $tolerance = 0.001;
    if ($sourceReservedQuantity + $tolerance < $transfert['quantity']) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'La quantité réservée dans le projet source est insuffisante. Veuillez vérifier si des modifications ont été apportées entre-temps.',
            'debug' => [
                'reserved_quantity' => $sourceReservedQuantity,
                'required_quantity' => $transfert['quantity'],
                'from_expression_dym' => $sourceReservedFromExpression,
                'from_achats_materiaux' => [
                    'reçu' => $sourceAchat['recu_qty'],
                    'commandé' => $sourceAchat['commande_qty'],
                    'en_cours' => $sourceAchat['encours_qty'],
                    'total' => $sourceAchat['total_quantity']
                ]
            ]
        ]);
        exit;
    }

    // 3. Mettre à jour uniquement les quantités réservées dans expression_dym
    // 3.1 Réduire la quantité dans le projet source
    $updateSourceSql = "
    UPDATE expression_dym
    SET quantity_reserved = quantity_reserved - :quantity
    WHERE idExpression = :source_expression_id
    AND (
        LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
        OR LOWER(TRIM(designation)) LIKE CONCAT('%', LOWER(TRIM(:product_name)), '%')
    )
    AND quantity_reserved >= :quantity
";

    $updateSourceStmt = $pdo->prepare($updateSourceSql);
    $updateResult = $updateSourceStmt->execute([
        'quantity' => $transfert['quantity'],
        'source_expression_id' => $transfert['source_expression_id'],
        'product_name' => $transfert['product_name']
    ]);

    if ($updateSourceStmt->rowCount() <= 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de mettre à jour la quantité réservée dans le projet source.',
            'debug' => [
                'transfert_id' => $transfertId,
                'product_name' => $transfert['product_name'],
                'quantity' => $transfert['quantity'],
                'source_expression_id' => $transfert['source_expression_id']
            ]
        ]);
        exit;
    }

    // 3.2 Rechercher s'il existe déjà un enregistrement dans expression_dym pour ce produit
    // dans le projet de destination, même si la désignation n'est pas exactement la même
    $checkAnyDestSql = "
    SELECT id, designation
    FROM expression_dym
    WHERE idExpression = :dest_expression_id
    AND (
        LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
        OR LOWER(TRIM(designation)) LIKE CONCAT('%', LOWER(TRIM(:product_name)), '%')
        OR :product_name LIKE CONCAT('%', LOWER(TRIM(designation)), '%')
    )
    LIMIT 1
";

    $checkAnyDestStmt = $pdo->prepare($checkAnyDestSql);
    $checkAnyDestStmt->execute([
        'dest_expression_id' => $transfert['destination_expression_id'],
        'product_name' => $transfert['product_name']
    ]);
    $anyDestEntry = $checkAnyDestStmt->fetch(PDO::FETCH_ASSOC);

    if ($anyDestEntry) {
        // Mettre à jour l'entrée existante
        $updateDestSql = "
        UPDATE expression_dym
        SET quantity_reserved = COALESCE(quantity_reserved, 0) + :quantity
        WHERE id = :entry_id
    ";

        $updateDestStmt = $pdo->prepare($updateDestSql);
        $updateDestResult = $updateDestStmt->execute([
            'quantity' => $transfert['quantity'],
            'entry_id' => $anyDestEntry['id']
        ]);

        if (!$updateDestResult) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de mettre à jour la réservation dans le projet destination.',
                'debug' => [
                    'transfert_id' => $transfertId,
                    'product_name' => $transfert['product_name'],
                    'destination_expression_id' => $transfert['destination_expression_id'],
                    'entry_id' => $anyDestEntry['id']
                ]
            ]);
            exit;
        }

        $transfertSource = 'expression_dym';
        error_log("Transfert #{$transfertId}: Mise à jour réussie de l'entrée existante ID {$anyDestEntry['id']} dans expression_dym pour le projet destination");
    } else {
        // Créer une nouvelle entrée dans expression_dym seulement si aucune entrée existante n'a été trouvée
        $insertDestSql = "
        INSERT INTO expression_dym (
            idExpression, 
            designation, 
            quantity_reserved, 
            created_at
        ) VALUES (
            :dest_expression_id,
            :product_name,
            :quantity,
            NOW()
        )
    ";

        $insertDestStmt = $pdo->prepare($insertDestSql);
        $insertDestResult = $insertDestStmt->execute([
            'dest_expression_id' => $transfert['destination_expression_id'],
            'product_name' => $transfert['product_name'],
            'quantity' => $transfert['quantity']
        ]);

        if (!$insertDestResult) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de créer une nouvelle réservation dans le projet destination.',
                'debug' => [
                    'transfert_id' => $transfertId,
                    'product_name' => $transfert['product_name'],
                    'destination_expression_id' => $transfert['destination_expression_id']
                ]
            ]);
            exit;
        }

        $transfertSource = 'expression_dym';
        error_log("Transfert #{$transfertId}: Création d'une nouvelle entrée dans expression_dym pour le projet destination");
    }

    // 4. Mettre à jour le statut du transfert
    $updateTransfertSql = "
        UPDATE transferts
        SET 
            status = 'completed',
            completed_at = NOW(),
            completed_by = :user_id
        WHERE 
            id = :transfert_id
    ";

    $updateTransfertStmt = $pdo->prepare($updateTransfertSql);
    $updateTransfertResult = $updateTransfertStmt->execute([
        'user_id' => $_SESSION['user_id'],
        'transfert_id' => $transfertId
    ]);

    if (!$updateTransfertResult) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de mettre à jour le statut du transfert.'
        ]);
        exit;
    }

    // 5. Ajouter une entrée dans l'historique
    $addHistorySql = "
        INSERT INTO transfert_history (
            transfert_id,
            action,
            details,
            user_id,
            created_at
        ) VALUES (
            :transfert_id,
            'complete',
            :details,
            :user_id,
            NOW()
        )
    ";

    $historyDetails = json_encode([
        'product_name' => $transfert['product_name'],
        'quantity' => $transfert['quantity'],
        'source_project_id' => $transfert['source_project_id'],
        'destination_project_id' => $transfert['destination_project_id'],
        'transfert_source' => $transfertSource // Ajout de la source de transfert pour le traçage
    ]);

    $addHistoryStmt = $pdo->prepare($addHistorySql);
    $addHistoryResult = $addHistoryStmt->execute([
        'transfert_id' => $transfertId,
        'details' => $historyDetails,
        'user_id' => $_SESSION['user_id']
    ]);

    // 6. Journaliser l'action dans system_logs
    $logSql = "
        INSERT INTO system_logs (
            user_id,
            username,
            action,
            type,
            entity_id,
            entity_name,
            details,
            ip_address,
            created_at
        ) VALUES (
            :user_id,
            :username,
            'complete_transfert',
            'transfert',
            :transfert_id,
            :entity_name,
            :details,
            :ip_address,
            NOW()
        )
    ";

    $username = '';
    $userStmt = $pdo->prepare("SELECT name FROM users_exp WHERE id = :user_id");
    $userStmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $username = $user['name'];
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $entityName = "Transfert #{$transfertId} - {$transfert['product_name']}";
    $logDetails = "Transfert #{$transfertId} complété. Produit: {$transfert['product_name']}, Quantité: {$transfert['quantity']}, Source: {$transfertSource}";

    $logStmt = $pdo->prepare($logSql);
    $logStmt->execute([
        'user_id' => $_SESSION['user_id'],
        'username' => $username,
        'transfert_id' => $transfertId,
        'entity_name' => $entityName,
        'details' => $logDetails,
        'ip_address' => $ipAddress
    ]);

    // Valider la transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Transfert complété avec succès.',
        'transfert_details' => [
            'id' => $transfertId,
            'product_name' => $transfert['product_name'],
            'quantity' => $transfert['quantity'],
            'source' => $transfertSource
        ]
    ]);

} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erreur dans api_complete_transfert.php: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la complétion du transfert: ' . $e->getMessage()
    ]);
}