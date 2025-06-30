<?php
header('Content-Type: application/json');
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé. Vous devez être connecté.']);
    exit();
}

// Connexion à la base de données
include_once dirname(__DIR__) . '/../../database/connection.php';

try {
    // Récupérer les paramètres de filtrage
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 25;
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $actionType = isset($_GET['action_type']) ? $_GET['action_type'] : '';
    $userId = isset($_GET['user_id']) ? $_GET['user_id'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // Calcul de l'offset pour la pagination
    $offset = ($page - 1) * $limit;

    // Construction de la requête SQL pour compter le nombre total de logs
    $countSql = "SELECT COUNT(*) as total FROM system_logs WHERE 1=1";
    $params = [];

    // Filtrer par date
    if ($startDate && $endDate) {
        $countSql .= " AND created_at BETWEEN :start_date AND DATE_ADD(:end_date, INTERVAL 1 DAY)";
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    }

    // Filtrer par type d'action
    if ($actionType) {
        $countSql .= " AND action = :action_type";
        $params[':action_type'] = $actionType;
    }

    // Filtrer par utilisateur
    if ($userId) {
        $countSql .= " AND user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    // Filtrer par recherche
    if ($search) {
        $countSql .= " AND (entity_name LIKE :search OR username LIKE :search OR details LIKE :search)";
        $params[':search'] = "%$search%";
    }

    // Exécuter la requête de comptage
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Construction de la requête SQL pour récupérer les logs
    $sql = "SELECT * FROM system_logs WHERE 1=1";

    // Appliquer les mêmes filtres que pour la requête de comptage
    if ($startDate && $endDate) {
        $sql .= " AND created_at BETWEEN :start_date AND DATE_ADD(:end_date, INTERVAL 1 DAY)";
    }

    if ($actionType) {
        $sql .= " AND action = :action_type";
    }

    if ($userId) {
        $sql .= " AND user_id = :user_id";
    }

    if ($search) {
        $sql .= " AND (entity_name LIKE :search OR username LIKE :search OR details LIKE :search)";
    }

    // Tri et pagination
    $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;

    // Exécuter la requête principale
    $stmt = $pdo->prepare($sql);

    // Bind des paramètres
    foreach ($params as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }

    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Renvoyer les résultats
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'total' => $totalCount,
        'page' => $page,
        'limit' => $limit,
        'totalPages' => ceil($totalCount / $limit)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}