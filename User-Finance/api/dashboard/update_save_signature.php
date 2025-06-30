<?php
/**
 * update_save_signature.php
 * API pour sauvegarder la signature Finance d'un bon de commande
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

// Désactiver l'affichage des erreurs pour les API
ini_set('display_errors', 0);
error_reporting(0);

session_start();

// Auto-loading des classes avec le bon chemin
require_once __DIR__ . '/../../../User-Finance/classes/ApiResponse.php';
require_once __DIR__ . '/../../../User-Finance/classes/DatabaseManager.php';
require_once __DIR__ . '/../../../User-Finance/classes/PurchaseOrderService.php';
require_once __DIR__ . '/../../../User-Finance/classes/FinanceController.php';

// Headers pour JSON
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

// Mesurer le temps d'exécution
$startTime = microtime(true);

try {
    // Vérifier la session utilisateur
    if (!isset($_SESSION['user_id'])) {
        ApiResponse::unauthorized('Session expirée')->sendError();
    }
    
    // Vérifier la méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('Méthode HTTP non autorisée')->sendError();
    }
    
    // Vérifier les paramètres
    if (!isset($_POST['order_id']) || empty($_POST['order_id'])) {
        ApiResponse::validationError(
            ['order_id' => 'ID du bon de commande requis'], 
            'Paramètres manquants'
        )->sendError();
    }
    
    $orderId = intval($_POST['order_id']);
    $userId = intval($_SESSION['user_id']);
    
    if ($orderId <= 0) {
        ApiResponse::validationError(
            ['order_id' => 'ID du bon de commande invalide'], 
            'Paramètres invalides'
        )->sendError();
    }
    
    // Initialiser le contrôleur Finance
    $financeController = new FinanceController();
    
    // Vérifier les permissions Finance
    if (!$financeController->validateFinanceAccess($_SESSION)) {
        ApiResponse::unauthorized('Accès réservé au service Finance')->sendError();
    }
    
    // Vérifier que le bon n'est pas déjà signé
    $existingDetails = $financeController->getOrderDetails($orderId);
    
    if (!$existingDetails->isSuccess()) {
        ApiResponse::notFound('Bon de commande non trouvé')->sendError();
    }
    
    $orderData = $existingDetails->getData();
    
    if (!empty($orderData['order']['signature_finance']) || !empty($orderData['order']['user_finance_id'])) {
        ApiResponse::error(
            'Ce bon de commande a déjà été signé',
            ['already_signed' => true]
        )->sendError();
    }
    
    // Signer le bon de commande
    $signResponse = $financeController->signOrder($orderId, $userId);
    
    // Ajouter les informations de timing
    $executionTime = microtime(true) - $startTime;
    $signResponse->withTiming($executionTime);
    
    // Ajouter les métadonnées
    $signResponse->addMeta('order_id', $orderId);
    $signResponse->addMeta('signed_by', $userId);
    $signResponse->addMeta('signed_at', date('Y-m-d H:i:s'));
    $signResponse->addMeta('order_number', $orderData['order']['order_number'] ?? 'N/A');
    
    // Journaliser l'action
    $financeController->logFinanceAction(
        $userId, 
        $signResponse->isSuccess() ? 'sign_order_success' : 'sign_order_failed', 
        $orderId,
        [
            'order_number' => $orderData['order']['order_number'] ?? 'N/A',
            'fournisseur' => $orderData['order']['fournisseur'] ?? 'N/A',
            'montant_total' => $orderData['order']['montant_total'] ?? 0
        ]
    );
    
    // Envoyer la réponse
    if ($signResponse->isSuccess()) {
        $signResponse->setMessage('Bon de commande signé avec succès par le service Finance');
        $signResponse->sendSuccess();
    } else {
        $signResponse->sendError();
    }
    
} catch (Exception $e) {
    error_log("Erreur API update_save_signature: " . $e->getMessage());
    
    // Journaliser l'erreur
    if (isset($financeController) && isset($userId) && isset($orderId)) {
        $financeController->logFinanceAction(
            $userId, 
            'sign_order_error', 
            $orderId,
            ['error' => $e->getMessage()]
        );
    }
    
    ApiResponse::error(
        'Erreur technique lors de la signature',
        ['exception' => $e->getMessage()]
    )->sendError();
}
?>