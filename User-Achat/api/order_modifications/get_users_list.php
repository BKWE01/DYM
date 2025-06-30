<?php
/**
 * API pour récupérer la liste des utilisateurs ayant effectué des modifications
 * 
 * @package DYM_MANUFACTURE
 * @subpackage api/order_modifications
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non authentifié'
    ]);
    exit();
}

// Inclure la connexion à la base de données
require_once '../../../database/connection.php';

try {
    // Requête pour récupérer les utilisateurs ayant effectué des modifications
    $query = "SELECT DISTINCT 
                u.id,
                u.name,
                COUNT(omh.id) as modification_count
              FROM users_exp u
              INNER JOIN order_modifications_history omh ON u.id = omh.modified_by
              GROUP BY u.id, u.name
              ORDER BY u.name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users)
    ]);

} catch (Exception $e) {
    error_log("Erreur lors de la récupération de la liste des utilisateurs: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>