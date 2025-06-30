<?php
header('Content-Type: application/json');
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette ressource.'
    ]);
    exit;
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    $transfertId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($transfertId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de transfert invalide.'
        ]);
        exit;
    }

    // Requête pour obtenir les détails du transfert
    $sql = "
        SELECT 
            t.*,
            p.product_name, p.barcode,
            sp.nom_client AS source_project, sp.code_projet AS source_project_code,
            dp.nom_client AS destination_project, dp.code_projet AS destination_project_code,
            req.name AS requested_by,
            comp.name AS completed_by,
            canc.name AS canceled_by
        FROM 
            transferts t
        LEFT JOIN 
            products p ON t.product_id = p.id
        LEFT JOIN 
            identification_projet sp ON t.source_project_id = sp.id
        LEFT JOIN 
            identification_projet dp ON t.destination_project_id = dp.id
        LEFT JOIN 
            users_exp req ON t.requested_by = req.id
        LEFT JOIN 
            users_exp comp ON t.completed_by = comp.id
        LEFT JOIN 
            users_exp canc ON t.canceled_by = canc.id
        WHERE 
            t.id = :transfert_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['transfert_id' => $transfertId]);
    $transfert = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transfert) {
        echo json_encode([
            'success' => false,
            'message' => 'Transfert non trouvé.'
        ]);
        exit;
    }

    // Ajouter l'historique du transfert si disponible
    $historySql = "
        SELECT 
            th.*,
            u.name AS user_name
        FROM 
            transfert_history th
        LEFT JOIN 
            users_exp u ON th.user_id = u.id
        WHERE 
            th.transfert_id = :transfert_id
        ORDER BY 
            th.created_at DESC
    ";

    $historyStmt = $pdo->prepare($historySql);
    $historyStmt->execute(['transfert_id' => $transfertId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    $transfert['history'] = $history;

    echo json_encode([
        'success' => true,
        'transfert' => $transfert
    ]);

} catch (PDOException $e) {
    error_log('Erreur dans api_get_transfert_details.php: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la récupération des détails du transfert: ' . $e->getMessage()
    ]);
}