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

// Valider les données requises
if (
    !isset($data['product_id']) || !isset($data['source_project_id']) ||
    !isset($data['destination_project_id']) || !isset($data['quantity']) ||
    empty($data['product_id']) || empty($data['source_project_id']) ||
    empty($data['destination_project_id']) || empty($data['quantity'])
) {

    echo json_encode([
        'success' => false,
        'message' => 'Données incomplètes. Veuillez remplir tous les champs obligatoires.'
    ]);
    exit;
}

// Convertir et valider la quantité
$quantity = floatval($data['quantity']);
if ($quantity <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'La quantité doit être supérieure à zéro.'
    ]);
    exit;
}

try {
    // Commencer une transaction
    $pdo->beginTransaction();

    // 1. Vérifier que le produit existe
    $checkProductSql = "SELECT product_name, barcode FROM products WHERE id = :product_id";
    $checkProductStmt = $pdo->prepare($checkProductSql);
    $checkProductStmt->execute(['product_id' => $data['product_id']]);
    $product = $checkProductStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé.'
        ]);
        exit;
    }

    // 2. Vérifier que les projets existent
    $checkProjectsSql = "
        SELECT id, idExpression, code_projet, nom_client 
        FROM identification_projet 
        WHERE id IN (:source_id, :destination_id)
    ";
    $checkProjectsStmt = $pdo->prepare($checkProjectsSql);
    $checkProjectsStmt->execute([
        'source_id' => $data['source_project_id'],
        'destination_id' => $data['destination_project_id']
    ]);
    $projects = $checkProjectsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($projects) !== 2) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Un ou plusieurs projets spécifiés n\'existent pas.'
        ]);
        exit;
    }

    // Organiser les projets
    $sourceProject = null;
    $destinationProject = null;
    foreach ($projects as $project) {
        if ($project['id'] == $data['source_project_id']) {
            $sourceProject = $project;
        } elseif ($project['id'] == $data['destination_project_id']) {
            $destinationProject = $project;
        }
    }

    // 3. Vérifier que le produit a une quantité réservée suffisante dans le projet source
// 3.1. Vérifier d'abord dans expression_dym avec une correspondance plus stricte
    $checkReservationSql = "
    SELECT COALESCE(SUM(quantity_reserved), 0) as total_reserved
    FROM expression_dym
    WHERE idExpression = :expression_id
    AND (
        LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
        OR LOWER(TRIM(designation)) LIKE CONCAT('%', LOWER(TRIM(:product_name)), '%')
    )
";

    $checkReservationStmt = $pdo->prepare($checkReservationSql);
    $checkReservationStmt->execute([
        'expression_id' => $sourceProject['idExpression'],
        'product_name' => $product['product_name']
    ]);
    $reservation = $checkReservationStmt->fetch(PDO::FETCH_ASSOC);

    $reservedQuantity = $reservation ? floatval($reservation['total_reserved']) : 0;

    // Ajouter un log pour déboguer
    error_log("Vérification des quantités pour le produit '{$product['product_name']}' dans le projet source (ID: {$sourceProject['id']})");
    error_log("Quantité réservée trouvée dans expression_dym: {$reservedQuantity}");

    // 3.2. Vérifier dans achats_materiaux (y compris pour statut 'reçu')
    $checkAchatsSql = "
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'commandé' THEN quantity ELSE 0 END), 0) as commande_qty,
        COALESCE(SUM(CASE WHEN status = 'en_cours' THEN quantity ELSE 0 END), 0) as encours_qty,
        COALESCE(SUM(CASE WHEN status = 'reçu' THEN quantity ELSE 0 END), 0) as recu_qty,
        COALESCE(SUM(quantity), 0) as total_quantity
    FROM 
        achats_materiaux
    WHERE 
        expression_id = :expression_id
        AND (
            LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
            OR LOWER(TRIM(designation)) LIKE CONCAT('%', LOWER(TRIM(:product_name)), '%')
        )
";

    $checkAchatsStmt = $pdo->prepare($checkAchatsSql);
    $checkAchatsStmt->execute([
        'expression_id' => $sourceProject['idExpression'],
        'product_name' => $product['product_name']
    ]);
    $sourceAchat = $checkAchatsStmt->fetch(PDO::FETCH_ASSOC);

    // Prioriser la quantité depuis achats_materiaux avec statut 'reçu'
    $achatsRecu = floatval($sourceAchat['recu_qty']);
    $achatsCommande = floatval($sourceAchat['commande_qty']);
    $achatsEnCours = floatval($sourceAchat['encours_qty']);
    $achatsTotal = floatval($sourceAchat['total_quantity']);

    // Ajouter des logs pour déboguer
    error_log("Quantités dans achats_materiaux: reçu={$achatsRecu}, commandé={$achatsCommande}, en_cours={$achatsEnCours}, total={$achatsTotal}");

    // Si la quantité dans expression_dym est insuffisante, utiliser celle des achats
    if ($reservedQuantity < $quantity) {
        // D'abord essayer avec les produits reçus
        if ($achatsRecu >= $quantity) {
            $reservedQuantity = $achatsRecu;
        }
        // Ensuite avec tous les achats si nécessaire
        else if ($achatsTotal >= $quantity) {
            $reservedQuantity = $achatsTotal;
        }
    }

    error_log("Quantité réservée finale utilisée pour vérification: {$reservedQuantity}");

    // Ajouter une petite tolérance pour les erreurs d'arrondi
    $tolerance = 0.001;
    if ($reservedQuantity + $tolerance < $quantity) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => "La quantité réservée disponible dans le projet source ({$reservedQuantity}) est inférieure à la quantité demandée ({$quantity}).",
            'debug' => [
                'reserved_quantity' => $reservedQuantity,
                'required_quantity' => $quantity,
                'from_expression_dym' => $reservedQuantity,
                'from_achats_materiaux' => [
                    'reçu' => $achatsRecu,
                    'commandé' => $achatsCommande,
                    'en_cours' => $achatsEnCours,
                    'total' => $achatsTotal
                ]
            ]
        ]);
        exit;
    }
    // 3.2. Si rien trouvé, vérifier dans achats_materiaux avec une recherche plus large
    /*if ($reservedQuantity <= 0) {
        $checkAchatsSql = "
        SELECT SUM(quantity) as total_quantity
        FROM achats_materiaux
        WHERE expression_id = :expression_id
        AND LOWER(designation) LIKE LOWER(:product_pattern)
        AND status IN ('commandé', 'en_cours', 'reçu')
    ";

        $checkAchatsStmt = $pdo->prepare($checkAchatsSql);
        $checkAchatsStmt->execute([
            'expression_id' => $sourceProject['idExpression'],
            'product_pattern' => '%' . $product['product_name'] . '%'
        ]);
        $achat = $checkAchatsStmt->fetch(PDO::FETCH_ASSOC);

        if ($achat) {
            $reservedQuantity = floatval($achat['total_quantity']);
        }
    }

    // 3.3. Dernière tentative - vérifier les quantités directement dans le stock du projet
    if ($reservedQuantity <= 0) {
        // Recherche dans d'autres tables ou colonnes qui pourraient stocker 
        // des informations sur les quantités de produits dans les projets
        $checkStockSql = "
        SELECT COUNT(*) as has_stock
        FROM stock_movement sm
        JOIN products p ON sm.product_id = p.id
        WHERE LOWER(p.product_name) = LOWER(:product_name)
        AND (sm.nom_projet = :project_code OR sm.provenance = :project_code)
    ";

        $checkStockStmt = $pdo->prepare($checkStockSql);
        $checkStockStmt->execute([
            'product_name' => $product['product_name'],
            'project_code' => $sourceProject['code_projet']
        ]);
        $stockCheck = $checkStockStmt->fetch(PDO::FETCH_ASSOC);

        if ($stockCheck && $stockCheck['has_stock'] > 0) {
            // Si le produit a été utilisé dans ce projet, définir une quantité minimale
            // pour permettre au moins un transfert de base
            $reservedQuantity = 1;
        }
    }

    // 3.4. Vérification finale et décision
    if ($reservedQuantity < $quantity) {
        // Ajout d'un log détaillé pour aider au diagnostic
        error_log("Transfert refusé - Produit: {$product['product_name']}, Projet source: {$sourceProject['code_projet']}, Quantité réservée: {$reservedQuantity}, Quantité demandée: {$quantity}");

        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => "La quantité réservée disponible dans le projet source ({$reservedQuantity}) est inférieure à la quantité demandée ({$quantity})."
        ]);
        exit;
    }
*/
    // 4. Créer le transfert
    $createTransfertSql = "
        INSERT INTO transferts (
            product_id,
            source_project_id,
            source_project_code,
            destination_project_id,
            destination_project_code,
            quantity,
            notes,
            status,
            requested_by,
            created_at
        ) VALUES (
            :product_id,
            :source_project_id,
            :source_project_code,
            :destination_project_id,
            :destination_project_code,
            :quantity,
            :notes,
            'pending',
            :user_id,
            NOW()
        )
    ";

    $createTransfertStmt = $pdo->prepare($createTransfertSql);
    $createTransfertResult = $createTransfertStmt->execute([
        'product_id' => $data['product_id'],
        'source_project_id' => $data['source_project_id'],
        'source_project_code' => $data['source_project_code'] ?? $sourceProject['code_projet'],
        'destination_project_id' => $data['destination_project_id'],
        'destination_project_code' => $data['destination_project_code'] ?? $destinationProject['code_projet'],
        'quantity' => $quantity,
        'notes' => $data['notes'] ?? null,
        'user_id' => $_SESSION['user_id']
    ]);

    if (!$createTransfertResult) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de créer le transfert.'
        ]);
        exit;
    }

    $transfertId = $pdo->lastInsertId();

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
            'create',
            :details,
            :user_id,
            NOW()
        )
    ";

    $historyDetails = json_encode([
        'product_name' => $product['product_name'],
        'quantity' => $quantity,
        'source_project' => $sourceProject['nom_client'],
        'source_project_code' => $sourceProject['code_projet'],
        'destination_project' => $destinationProject['nom_client'],
        'destination_project_code' => $destinationProject['code_projet'],
        'notes' => $data['notes'] ?? null
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
            'create_transfert',
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
    $entityName = "Transfert #{$transfertId} - {$product['product_name']}";
    $logDetails = "Transfert #{$transfertId} créé. Produit: {$product['product_name']}, Quantité: {$quantity}, De: {$sourceProject['nom_client']} à {$destinationProject['nom_client']}";

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
        'message' => 'Transfert créé avec succès.',
        'transfert_id' => $transfertId
    ]);

} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erreur dans api_create_transfert.php: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la création du transfert: ' . $e->getMessage()
    ]);
}