<?php
/**
 * get_rejected_bons.php
 * API pour récupérer les bons de commande rejetés par Finance
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
    
    // Initialiser le contrôleur Finance
    $financeController = new FinanceController();
    
    // Vérifier les permissions Finance
    if (!$financeController->validateFinanceAccess($_SESSION)) {
        ApiResponse::unauthorized('Accès réservé au service Finance')->sendError();
    }
    
    // Récupérer les bons rejetés
    $response = $financeController->getRejectedOrders();
    
    // Ajouter les informations de timing
    $executionTime = microtime(true) - $startTime;
    $response->withTiming($executionTime);
    
    // Ajouter les métadonnées
    $response->addMeta('user_id', $_SESSION['user_id']);
    $response->addMeta('user_type', $_SESSION['user_type']);
    
    // Journaliser l'accès
    $financeController->logFinanceAction(
        $_SESSION['user_id'], 
        'view_rejected_orders', 
        0, 
        ['count' => count($response->getData() ?? [])]
    );
    
    // Envoyer la réponse
    if ($response->isSuccess()) {
        $response->sendSuccess();
    } else {
        $response->sendError();
    }
    
} catch (Exception $e) {
    error_log("Erreur API get_rejected_bons: " . $e->getMessage());
    
    ApiResponse::error(
        'Erreur technique lors de la récupération des bons rejetés',
        ['exception' => $e->getMessage()]
    )->sendError();
}
?>