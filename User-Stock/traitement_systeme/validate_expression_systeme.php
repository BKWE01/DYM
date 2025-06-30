<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier si la requête est bien une méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Inclure la connexion à la base de données
include_once '../database/connection.php';

try {
    // Vérifier si les données requises sont présentes
    if (!isset($_POST['idBesoin']) || !isset($_POST['user_stock']) || !isset($_POST['qt_stock']) || !isset($_POST['qt_acheter'])) {
        throw new Exception('Données manquantes');
    }

    $idBesoin = $_POST['idBesoin'];
    $userStock = $_POST['user_stock'];
    $qtStock = $_POST['qt_stock'];
    $qtAcheter = $_POST['qt_acheter'];

    // Démarrer une transaction
    $pdo->beginTransaction();

    // Mettre à jour chaque besoin
    foreach ($qtStock as $id => $qty) {
        $stmt = $pdo->prepare("
            UPDATE besoins 
            SET user_stock = :user_stock,
                qt_stock = :qt_stock,
                qt_acheter = :qt_acheter,
                stock_status = 'validé'
            WHERE id = :id AND idBesoin = :idBesoin
        ");

        $stmt->execute([
            ':user_stock' => $userStock,
            ':qt_stock' => $qty,
            ':qt_acheter' => $qtAcheter[$id],
            ':id' => $id,
            ':idBesoin' => $idBesoin
        ]);
    }

    // Valider la transaction
    $pdo->commit();

    // Journaliser l'action (facultatif)
    $logStmt = $pdo->prepare("
        INSERT INTO system_logs (user_id, action, type, entity_id, entity_name, details, ip_address)
        VALUES (:user_id, 'validation', 'expression_systeme', :idBesoin, 'besoins', 'Validation d\'une expression de besoin système', :ip)
    ");
    
    $logStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':idBesoin' => $idBesoin,
        ':ip' => $_SERVER['REMOTE_ADDR']
    ]);

    // Retourner une réponse de succès
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    // En cas d'erreur, annuler la transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erreur de base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    // En cas d'erreur, annuler la transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>