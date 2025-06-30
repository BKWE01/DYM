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

// Récupérer les données JSON de la requête
$data = json_decode(file_get_contents('php://input'), true);

// Vérifier si l'idBesoin est présent
if (!isset($data['idBesoin']) || empty($data['idBesoin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID de besoin manquant']);
    exit;
}

$idBesoin = $data['idBesoin'];

// Inclure la connexion à la base de données
include_once '../database/connection.php';

try {
    // Démarrer une transaction
    $pdo->beginTransaction();

    // Mettre à jour les besoins avec le statut 'rejeté'
    $stmt = $pdo->prepare("
        UPDATE besoins 
        SET stock_status = 'rejeté',
            user_stock = :user_id
        WHERE idBesoin = :idBesoin
    ");

    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':idBesoin' => $idBesoin
    ]);

    // Valider la transaction
    $pdo->commit();

    // Journaliser l'action (facultatif)
    $logStmt = $pdo->prepare("
        INSERT INTO system_logs (user_id, action, type, entity_id, entity_name, details, ip_address)
        VALUES (:user_id, 'rejet', 'expression_systeme', :idBesoin, 'besoins', 'Rejet d\'une expression de besoin système', :ip)
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