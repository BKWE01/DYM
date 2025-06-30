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

    // Requête modifiée pour exclure les projets terminés
    $stmt = $pdo->prepare("
        SELECT e.idExpression, MIN(e.created_at) as created_at, i.code_projet, i.nom_client, i.description_projet
        FROM expression_dym e
        JOIN identification_projet i ON e.idExpression = i.idExpression
        WHERE DATE(e.created_at) BETWEEN :startDate AND :endDate
        AND NOT EXISTS (
            SELECT 1 
            FROM project_status ps 
            WHERE ps.idExpression = e.idExpression 
            AND ps.status = 'completed'
        )
        GROUP BY e.idExpression, i.code_projet, i.nom_client, i.description_projet
        ORDER BY MIN(e.created_at) DESC
    ");

    $stmt->bindParam(':startDate', $startDate);
    $stmt->bindParam(':endDate', $endDate);
    $stmt->execute();

    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($expressions) {
        echo json_encode($expressions);
    } else {
        echo json_encode([]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>