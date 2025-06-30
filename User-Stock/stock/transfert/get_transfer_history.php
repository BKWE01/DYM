<?php
// Enregistrer dans le fichier get_transfer_history.php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette ressource.'
    ]);
    exit;
}

// Connexion à la base de données
include_once '../../../database/connection.php';

header('Content-Type: application/json');

try {
    $productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    $projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

    if ($productId <= 0 || $projectId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de produit ou de projet invalide.'
        ]);
        exit;
    }

    // Requête pour obtenir les transferts liés au produit et au projet
    $sql = "
        SELECT t.*, 
               p.product_name, 
               sp.nom_client AS source_project, 
               dp.nom_client AS destination_project,
               u.name AS requested_by_name,
               u2.name AS completed_by_name,
               u3.name AS canceled_by_name
        FROM transferts t
        JOIN products p ON t.product_id = p.id
        JOIN identification_projet sp ON t.source_project_id = sp.id
        JOIN identification_projet dp ON t.destination_project_id = dp.id
        LEFT JOIN users_exp u ON t.requested_by = u.id
        LEFT JOIN users_exp u2 ON t.completed_by = u2.id
        LEFT JOIN users_exp u3 ON t.canceled_by = u3.id
        WHERE t.product_id = :product_id
        AND (t.source_project_id = :project_id OR t.destination_project_id = :project_id)
        ORDER BY t.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'product_id' => $productId,
        'project_id' => $projectId
    ]);
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer également l'historique des transferts depuis transfert_history
    $historySql = "
        SELECT th.*, 
               t.product_id, 
               t.source_project_id, 
               t.destination_project_id,
               u.name AS user_name
        FROM transfert_history th
        JOIN transferts t ON th.transfert_id = t.id
        LEFT JOIN users_exp u ON th.user_id = u.id
        WHERE t.product_id = :product_id
        AND (t.source_project_id = :project_id OR t.destination_project_id = :project_id)
        ORDER BY th.created_at DESC
    ";

    $historyStmt = $pdo->prepare($historySql);
    $historyStmt->execute([
        'product_id' => $productId,
        'project_id' => $projectId
    ]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'transfers' => $transfers,
        'history' => $history
    ]);

} catch (PDOException $e) {
    error_log('Erreur dans get_transfer_history.php: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la récupération de l\'historique des transferts: ' . $e->getMessage()
    ]);
}