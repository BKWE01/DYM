<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Connexion à la base de données
include_once '../database/connection.php';

try {

    $period = isset($_GET['period']) ? $_GET['period'] : 'today';

    $today = date('Y-m-d');
    $startDate = '';
    $endDate = '';

    switch ($period) {
        case 'today':
            $startDate = $today;
            $endDate = $today;
            break;
        case 'week':
            $startDate = date('Y-m-d', strtotime('monday this week'));
            $endDate = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'month':
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            break;
        default:
            throw new Exception('Invalid period.');
    }

    $stmt = $pdo->prepare("
        SELECT e.idBesoin, MIN(e.created_at) as created_at, i.nom_prenoms
        FROM besoins e
        JOIN demandeur i ON e.idBesoin = i.idBesoin
        WHERE DATE(e.created_at) BETWEEN :startDate AND :endDate
        GROUP BY e.idBesoin, i.nom_prenoms
        ORDER BY MIN(e.created_at) DESC
    ");
    $stmt->bindParam(':startDate', $startDate);
    $stmt->bindParam(':endDate', $endDate);
    $stmt->execute();

    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($expressions ?: []);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
