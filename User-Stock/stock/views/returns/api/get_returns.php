<?php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Connexion à la base de données
include_once '../../../../../database/connection.php';

try {
    // Récupérer les paramètres de filtrage
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : '';

    // Construction de la requête SQL de base
    $sql = "
    SELECT 
        r.*, 
        p.product_name, 
        p.barcode, 
        p.unit,
        u_created.name as created_by_name,
        u_approved.name as approved_by_name,
        u_rejected.name as rejected_by_name,
        u_completed.name as completed_by_name,
        u_canceled.name as canceled_by_name
    FROM stock_returns r
    JOIN products p ON r.product_id = p.id
    LEFT JOIN users_exp u_created ON r.created_by = u_created.id
    LEFT JOIN users_exp u_approved ON r.approved_by = u_approved.id
    LEFT JOIN users_exp u_rejected ON r.rejected_by = u_rejected.id
    LEFT JOIN users_exp u_completed ON r.completed_by = u_completed.id
    LEFT JOIN users_exp u_canceled ON r.canceled_by = u_canceled.id
    WHERE 1=1
";

    $params = [];

    // Ajouter la condition de recherche si elle est fournie
    if (!empty($search)) {
        $sql .= " AND (
            p.product_name LIKE ? 
            OR p.barcode LIKE ? 
            OR r.returned_by LIKE ?
            OR r.origin LIKE ?
            OR CAST(r.id AS CHAR) = ?
        )";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $search; // Recherche exacte pour l'ID
    }

    // Ajouter la condition de statut si elle est fournie
    if (!empty($status)) {
        $sql .= " AND r.status = ?";
        $params[] = $status;
    }

    // Ajouter la condition de date si elle est fournie
    if (!empty($date)) {
        switch ($date) {
            case 'today':
                $sql .= " AND DATE(r.created_at) = CURDATE()";
                break;
            case 'yesterday':
                $sql .= " AND DATE(r.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'week':
                $sql .= " AND YEARWEEK(r.created_at, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'month':
                $sql .= " AND MONTH(r.created_at) = MONTH(CURDATE()) AND YEAR(r.created_at) = YEAR(CURDATE())";
                break;
        }
    }

    // Trier par date de création décroissante (les plus récents d'abord)
    $sql .= " ORDER BY r.created_at DESC";

    // Exécuter la requête
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'returns' => $returns
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>