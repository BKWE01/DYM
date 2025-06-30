<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php'; 

// Récupérer les paramètres
$checkType = isset($_GET['type']) ? $_GET['type'] : 'all';
$lastCheck = isset($_GET['last_check']) ? $_GET['last_check'] : '';

// Définir la date de dernière vérification
$lastCheckDate = !empty($lastCheck) ? new DateTime($lastCheck) : new DateTime('-1 day');
$formattedLastCheck = $lastCheckDate->format('Y-m-d H:i:s');

try {
    $response = [
        'success' => true,
        'current_time' => (new DateTime())->format('Y-m-d H:i:s')
    ];

    // Vérifier les nouvelles entrées de stock
    if ($checkType == 'all' || $checkType == 'entries') {
        $entriesStmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM stock_movement 
            WHERE movement_type = 'entry' 
            AND date > :last_check
        ");
        $entriesStmt->execute([':last_check' => $formattedLastCheck]);
        $entriesCount = $entriesStmt->fetch(PDO::FETCH_ASSOC)['count'];

        $response['new_entries'] = $entriesCount;
    }

    // Vérifier les nouvelles réceptions de commandes
    if ($checkType == 'all' || $checkType == 'received') {
        $receivedStmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM achats_materiaux 
            WHERE status = 'reçu' 
            AND date_reception > :last_check
        ");
        $receivedStmt->execute([':last_check' => $formattedLastCheck]);
        $receivedCount = $receivedStmt->fetch(PDO::FETCH_ASSOC)['count'];

        $response['new_received'] = $receivedCount;
    }

    // Vérifier les commandes en attente
    if ($checkType == 'all' || $checkType == 'pending') {
        $pendingStmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM achats_materiaux 
            WHERE status = 'commandé'
        ");
        $pendingCount = $pendingStmt->fetch(PDO::FETCH_ASSOC)['count'];

        $response['pending_orders'] = $pendingCount;
    }

    // Exécuter la mise à jour des commandes reçues si nécessaire
    if (isset($_GET['update_received']) && $_GET['update_received'] == 'true') {
        include_once 'update_received_orders.php';
        $response['updated_received'] = true;
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>