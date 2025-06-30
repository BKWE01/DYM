<?php
/**
 * delete_order.php
 * API pour supprimer définitivement un bon de commande rejeté
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

// Désactiver l'affichage des erreurs pour les API
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

// Validation des données d'entrée
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$delete_reason = isset($_POST['delete_reason']) 
    ? trim(htmlspecialchars($_POST['delete_reason'], ENT_QUOTES, 'UTF-8'))
    : '';

// Validation des paramètres
if (!$order_id || $order_id <= 0 || empty($delete_reason)) {
    echo json_encode([
        'success' => false,
        'message' => 'Données manquantes ou invalides',
        'errors' => [
            'order_id' => $order_id ? 'ID valide' : 'ID invalide',
            'delete_reason' => !empty($delete_reason) ? 'Raison fournie' : 'Raison manquante'
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

    // Supprimer définitivement le bon de commande
    $result = $financeController->deleteOrder($order_id, $_SESSION['user_id'], $delete_reason);

    if ($result->isSuccess()) {
        // Journaliser l'action
        $financeController->logFinanceAction(
            $_SESSION['user_id'],
            'delete_order_permanent',
            $order_id,
            ['reason' => $delete_reason]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Bon de commande supprimé définitivement avec succès',
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
    error_log("Erreur suppression bon de commande: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Erreur technique lors de la suppression'
    ]);
}
?>