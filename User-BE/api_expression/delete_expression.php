<?php
// Fichier: /DYM MANUFACTURE/expressions_besoins/User-BE/api_expression/delete_expression.php
session_start();

// Vérifier si l'utilisateur est connecté et est un super_admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../index.php");
    exit();
}

// Connexion à la base de données
include_once '../../database/connection.php';

// Vérifier si l'utilisateur est un super_admin
try {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT role FROM users_exp WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData || $userData['role'] !== 'super_admin') {
        echo json_encode(['success' => false, 'message' => 'Permission refusée. Seul un super administrateur peut effectuer cette action.']);
        exit();
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
    exit();
}

// Vérifier que l'ID a été fourni
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de l\'expression manquant.']);
    exit();
}

$id = $_POST['id'];

try {
    // Commencer une transaction
    $pdo->beginTransaction();

    // 1. Collecter les informations pour le journal système
    $stmt = $pdo->prepare("
    SELECT e.designation, e.quantity, ip.code_projet, ip.nom_client
    FROM expression_dym e
    JOIN identification_projet ip ON e.idExpression = ip.idExpression
    WHERE e.idExpression = :id
    LIMIT 1
  ");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();
    $expressionInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Mettre à jour les quantités réservées dans la table products
    $stmt = $pdo->prepare("
    SELECT designation, quantity FROM expression_dym 
    WHERE idExpression = :id AND quantity_reserved > 0
  ");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();
    $reservedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reservedItems as $item) {
        // Libérer les quantités réservées
        $stmt = $pdo->prepare("
      UPDATE products 
      SET quantity_reserved = GREATEST(0, COALESCE(quantity_reserved, 0) - :quantity) 
      WHERE product_name = :designation
    ");
        $stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
        $stmt->bindParam(':designation', $item['designation'], PDO::PARAM_STR);
        $stmt->execute();
    }

    // 3. Supprimer les entrées dans achats_materiaux liées à cette expression
    $stmt = $pdo->prepare("DELETE FROM achats_materiaux WHERE expression_id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();

    // 4. Supprimer les entrées dans expression_dym
    $stmt = $pdo->prepare("DELETE FROM expression_dym WHERE idExpression = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();

    // 5. Supprimer les entrées dans identification_projet
    $stmt = $pdo->prepare("DELETE FROM identification_projet WHERE idExpression = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();

    // 6. Ajouter une entrée dans system_logs
    if ($expressionInfo) {
        $logDetails = json_encode([
            'action' => 'delete',
            'expression_id' => $id,
            'projet' => $expressionInfo['code_projet'],
            'client' => $expressionInfo['nom_client'],
            'produits_supprimés' => $reservedItems
        ]);

        $stmt = $pdo->prepare("
    INSERT INTO system_logs (user_id, username, action, type, entity_id, entity_name, details, ip_address)
    VALUES (:user_id, :username, 'suppression', 'expression', :entity_id, :entity_name, :details, :ip)
  ");
        $username = $_SESSION['username'] ?? 'Unknown';
        $entityName = $expressionInfo['code_projet'] ?? 'Unknown';
        $ipAddress = $_SERVER['REMOTE_ADDR'];

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':entity_id', $id, PDO::PARAM_STR);
        $stmt->bindParam(':entity_name', $entityName, PDO::PARAM_STR);
        $stmt->bindParam(':details', $logDetails, PDO::PARAM_STR);
        $stmt->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
        $stmt->execute();
    }

    // Valider la transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Expression de besoin supprimée avec succès.']);
} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
}
?>