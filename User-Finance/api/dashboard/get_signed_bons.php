<?php
/**
 * get_signed_bons.php - VERSION SANS LIMITATION
 * API pour récupérer les bons de commande déjà signés par Finance
 */

// Désactiver l'affichage des erreurs pour les API
ini_set('display_errors', 0);
error_reporting(0);

session_start();

require_once __DIR__ . '/../../../User-Finance/classes/ApiResponse.php';
require_once __DIR__ . '/../../../User-Finance/classes/DatabaseManager.php';
require_once __DIR__ . '/../../../User-Finance/classes/PurchaseOrderService.php';
require_once __DIR__ . '/../../../User-Finance/classes/FinanceController.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

$startTime = microtime(true);

try {
    if (!isset($_SESSION['user_id'])) {
        ApiResponse::unauthorized('Session expirée')->sendError();
    }
    
    $financeController = new FinanceController();
    
    if (!$financeController->validateFinanceAccess($_SESSION)) {
        ApiResponse::unauthorized('Accès réservé au service Finance')->sendError();
    }
    
    // SUPPRESSION DE TOUTE PAGINATION FORCÉE
    // Récupérer TOUS les bons signés sans limitation
    $response = $financeController->getSignedOrders();
    
    if ($response->isSuccess()) {
        $allData = $response->getData();
        $totalRecords = count($allData);
        
        // LOG de vérification
        error_log("🔍 API get_signed_bons: " . $totalRecords . " bons retournés");
        
        // Retourner TOUTES les données sans pagination
        $response = ApiResponse::success($allData, "Tous les bons signés récupérés ($totalRecords)");
        
        $response->addMeta('total_records', $totalRecords);
        $response->addMeta('request_timestamp', date('Y-m-d H:i:s'));
    }
    
    $executionTime = microtime(true) - $startTime;
    $response->withTiming($executionTime);
    
    $response->addMeta('user_id', $_SESSION['user_id']);
    $response->addMeta('user_type', $_SESSION['user_type']);
    
    $financeController->logFinanceAction(
        $_SESSION['user_id'], 
        'view_signed_orders', 
        0, 
        ['count' => count($response->getData() ?? [])]
    );
    
    if ($response->isSuccess()) {
        $response->sendSuccess();
    } else {
        $response->sendError();
    }
    
} catch (Exception $e) {
    error_log("Erreur API get_signed_bons: " . $e->getMessage());
    
    ApiResponse::error(
        'Erreur technique lors de la récupération des bons signés',
        ['exception' => $e->getMessage()]
    )->sendError();
}
?>