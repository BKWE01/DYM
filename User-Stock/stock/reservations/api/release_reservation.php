<?php
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

// Vérifier si les paramètres nécessaires sont fournis
if (!isset($_POST['project_id']) || !isset($_POST['product_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants'
    ]);
    exit;
}

$projectId = intval($_POST['project_id']);
$productId = intval($_POST['product_id']);
$userId = $_SESSION['user_id'];
$sourceTable = isset($_POST['source_table']) ? $_POST['source_table'] : ''; // Pour spécifier la source si connu

require_once '../../../../database/connection.php';

try {
    // Commencer une transaction
    $pdo->beginTransaction();

    // Déterminer si c'est une réservation dans expression_dym ou besoins
    $isSystemReservation = false;
    $reservation = null;
    
    if ($sourceTable === 'besoins') {
        $isSystemReservation = true;
    } elseif ($sourceTable === 'expression_dym') {
        $isSystemReservation = false;
    } else {
        // Si la source n'est pas spécifiée, essayer de trouver la réservation dans les deux tables
        
        // Chercher d'abord dans expression_dym
        $sqlCheckED = "SELECT ip.*, ed.designation, ed.quantity_reserved, p.product_name 
                      FROM identification_projet ip
                      JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                      JOIN products p ON p.id = :product_id
                      WHERE ip.id = :project_id 
                      AND LOWER(ed.designation) = LOWER(p.product_name) 
                      AND ed.quantity_reserved > 0";
        $stmtCheckED = $pdo->prepare($sqlCheckED);
        $stmtCheckED->execute([
            'project_id' => $projectId,
            'product_id' => $productId
        ]);
        $reservation = $stmtCheckED->fetch(PDO::FETCH_ASSOC);
        
        if ($reservation) {
            $isSystemReservation = false;
        } else {
            // Si pas trouvé dans expression_dym, chercher dans besoins
            $sqlCheckB = "SELECT b.*, p.product_name 
                         FROM besoins b
                         JOIN products p ON p.id = :product_id
                         WHERE b.id = :project_id 
                         AND b.product_id = :product_id 
                         AND b.quantity_reserved > 0";
            $stmtCheckB = $pdo->prepare($sqlCheckB);
            $stmtCheckB->execute([
                'project_id' => $projectId,
                'product_id' => $productId
            ]);
            $reservation = $stmtCheckB->fetch(PDO::FETCH_ASSOC);
            
            if ($reservation) {
                $isSystemReservation = true;
            }
        }
    }
    
    // Si aucune réservation n'est trouvée
    if (!$reservation) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Réservation non trouvée'
        ]);
        exit;
    }

    // Stocker la quantité réservée avant de la mettre à jour
    $reservedQuantity = $isSystemReservation ? 
                        floatval($reservation['quantity_reserved']) : 
                        floatval($reservation['quantity_reserved']);

    if ($isSystemReservation) {
        // Mettre à jour la quantité réservée dans besoins
        $sqlUpdateBesoin = "UPDATE besoins 
                           SET quantity_reserved = 0,
                               updated_at = NOW()
                           WHERE id = :id
                           AND product_id = :product_id";
        $stmtUpdateBesoin = $pdo->prepare($sqlUpdateBesoin);
        $stmtUpdateBesoin->execute([
            'id' => $projectId,
            'product_id' => $productId
        ]);
    } else {
        // Mettre à jour la quantité réservée dans expression_dym
        $sqlUpdateExpression = "UPDATE expression_dym 
                               SET quantity_reserved = 0,
                                   updated_at = NOW()
                               WHERE idExpression = :idExpression
                               AND LOWER(designation) = LOWER(:designation)";
        $stmtUpdateExpression = $pdo->prepare($sqlUpdateExpression);
        $stmtUpdateExpression->execute([
            'idExpression' => $reservation['idExpression'],
            'designation' => $reservation['designation']
        ]);
    }

    // Mettre également à jour la quantité réservée dans la table products
    $sqlUpdateProduct = "UPDATE products 
                        SET quantity_reserved = GREATEST(0, quantity_reserved - :reserved_quantity),
                            updated_at = NOW() 
                        WHERE id = :product_id";
    $stmtUpdateProduct = $pdo->prepare($sqlUpdateProduct);
    $stmtUpdateProduct->execute([
        'reserved_quantity' => $reservedQuantity,
        'product_id' => $productId
    ]);

    // Obtenir le nom d'utilisateur
    $sqlUser = "SELECT name FROM users_exp WHERE id = :user_id";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute(['user_id' => $userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $username = $user ? $user['name'] : 'Utilisateur inconnu';

    // Détails pour le log
    $projectCode = $isSystemReservation ? 'SYS' : $reservation['code_projet'];
    $productName = $reservation['product_name'];
    
    $details = json_encode([
        'project_code' => $projectCode,
        'product_name' => $productName,
        'quantity_released' => $reservedQuantity,
        'source' => $isSystemReservation ? 'besoins' : 'expression_dym'
    ]);

    // Enregistrer l'action dans les logs système
    $sqlLog = "INSERT INTO system_logs 
               (user_id, username, action, type, entity_id, entity_name, details, ip_address)
               VALUES (:user_id, :username, :action, :type, :entity_id, :entity_name, :details, :ip_address)";
    $stmtLog = $pdo->prepare($sqlLog);
    $stmtLog->execute([
        'user_id' => $userId,
        'username' => $username,
        'action' => 'release_reservation',
        'type' => 'reservation',
        'entity_id' => $projectId . '-' . $productId,
        'entity_name' => $projectCode . ' - ' . $productName,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    // Valider la transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'La réservation a été libérée avec succès',
        'released_quantity' => $reservedQuantity,
        'source' => $isSystemReservation ? 'besoins' : 'expression_dym'
    ]);

} catch (PDOException $e) {
    // En cas d'erreur, annuler la transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}