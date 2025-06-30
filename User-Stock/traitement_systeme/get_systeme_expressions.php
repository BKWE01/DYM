<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Connexion à la base de données
include_once '../../database/connection.php';

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

    // Requête pour récupérer les expressions de besoin système non validées
    // Suppression du filtre de date pour afficher toutes les expressions
    $stmt = $pdo->prepare("
        SELECT DISTINCT b.idBesoin, b.created_at, d.service_demandeur, d.nom_prenoms
        FROM besoins b
        JOIN demandeur d ON b.idBesoin = d.idBesoin
        WHERE (b.qt_stock IS NULL OR b.qt_stock = '')
        AND (b.stock_status IS NULL OR b.stock_status != 'validé')
        GROUP BY b.idBesoin
        ORDER BY b.created_at DESC
    ");
    
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