<?php
/**
 * reject_order.php
 * API pour rejeter un bon de commande OU révoquer un bon signé - VERSION MISE À JOUR
 * 
 * @author DYM MANUFACTURE
 * @version 2.1
 */

// Désactiver l'affichage des erreurs pour les API (IMPORTANT pour éviter les erreurs HTML)
ini_set('display_errors', 0);
error_reporting(0);

session_start();

// Headers pour JSON
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé'
    ]);
    exit();
}

// Inclusion des classes nécessaires
require_once __DIR__ . '/../../classes/FinanceController.php';
require_once __DIR__ . '/../../classes/ApiResponse.php';

// Vérification de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit();
}

// CORRECTION : Utiliser une méthode de validation sécurisée alternative
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

// CORRECTION : Remplacer FILTER_SANITIZE_STRING deprecated par trim + htmlspecialchars
$rejection_reason = isset($_POST['rejection_reason']) 
    ? trim(htmlspecialchars($_POST['rejection_reason'], ENT_QUOTES, 'UTF-8'))
    : '';

// NOUVEAU : Vérifier si c'est une révocation d'un bon signé
$revoke_signed = isset($_POST['revoke_signed']) && $_POST['revoke_signed'] === 'true';

// Validation des données
if (!$order_id || $order_id <= 0 || empty($rejection_reason)) {
    echo json_encode([
        'success' => false,
        'message' => 'Données manquantes ou invalides',
        'errors' => [
            'order_id' => $order_id ? 'ID valide' : 'ID invalide',
            'rejection_reason' => !empty($rejection_reason) ? 'Raison fournie' : 'Raison manquante'
        ]
    ]);
    exit();
}

try {
    // Initialiser le contrôleur Finance
    $financeController = new FinanceController();

    // Vérifier les permissions Finance
    if (!$financeController->validateFinanceAccess($_SESSION)) {
        throw new Exception('Accès non autorisé au service Finance');
    }

    // NOUVEAU : Gérer différemment selon si c'est une révocation ou un rejet classique
    if ($revoke_signed) {
        // Révocation d'un bon signé
        $result = $financeController->revokeSignedOrder($order_id, $_SESSION['user_id'], $rejection_reason);
        $action_type = 'revoke_signed_order';
        $success_message = 'Bon de commande révoqué avec succès';
    } else {
        // Rejet classique d'un bon en attente
        $result = $financeController->rejectOrder($order_id, $_SESSION['user_id'], $rejection_reason);
        $action_type = 'reject_order';
        $success_message = 'Bon de commande rejeté avec succès';
    }

    if ($result->isSuccess()) {
        // Journaliser l'action
        $financeController->logFinanceAction(
            $_SESSION['user_id'],
            $action_type,
            $order_id,
            [
                'reason' => $rejection_reason,
                'revoke_signed' => $revoke_signed
            ]
        );

        echo json_encode([
            'success' => true,
            'message' => $success_message,
            'data' => $result->getData()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result->getMessage(),
            'errors' => $result->getErrors()
        ]);
    }

} catch (Exception $e) {
    // Log l'erreur mais ne pas l'afficher pour éviter les fuites d'information
    error_log("Erreur rejet/révocation bon de commande: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Erreur technique lors de l\'opération'
    ]);
}
?>