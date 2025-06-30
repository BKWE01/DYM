<?php
/**
 * get_bon_details_finance.php
 * API pour récupérer les détails complets d'un bon de commande pour le service Finance
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

// Configuration d'erreur pour debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Auto-loading des classes avec le bon chemin
require_once __DIR__ . '/../../classes/ApiResponse.php';
require_once __DIR__ . '/../../classes/DatabaseManager.php';
require_once __DIR__ . '/../../classes/PurchaseOrderService.php';
require_once __DIR__ . '/../../classes/FinanceController.php';

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
    
    // Vérifier les paramètres
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        ApiResponse::validationError(
            ['id' => 'ID du bon de commande requis'], 
            'Paramètres manquants'
        )->sendError();
    }
    
    $orderId = intval($_GET['id']);
    
    if ($orderId <= 0) {
        ApiResponse::validationError(
            ['id' => 'ID du bon de commande invalide'], 
            'Paramètres invalides'
        )->sendError();
    }
    
    // Initialiser le contrôleur Finance
    $financeController = new FinanceController();
    
    // Vérifier les permissions Finance
    if (!$financeController->validateFinanceAccess($_SESSION)) {
        ApiResponse::unauthorized('Accès réservé au service Finance')->sendError();
    }
    
    // Récupérer les détails du bon
    $response = $financeController->getOrderDetails($orderId);
    
    // Ajouter les informations de timing
    $executionTime = microtime(true) - $startTime;
    $response->withTiming($executionTime);
    
    // Ajouter les métadonnées
    $response->addMeta('order_id', $orderId);
    $response->addMeta('requested_by', $_SESSION['user_id']);
    $response->addMeta('request_timestamp', date('Y-m-d H:i:s'));
    
    // Journaliser l'accès
    $financeController->logFinanceAction(
        $_SESSION['user_id'], 
        'view_order_details', 
        $orderId,
        ['success' => $response->isSuccess()]
    );
    
    // Envoyer la réponse
    if ($response->isSuccess()) {
        $response->sendSuccess();
    } else {
        $response->sendError();
    }
    
} catch (Exception $e) {
    error_log("Erreur API get_bon_details_finance: " . $e->getMessage());
    
    ApiResponse::error(
        'Erreur technique lors de la récupération des détails',
        ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
    )->sendError();
}
?>