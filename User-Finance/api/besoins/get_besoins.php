<?php
// Désactiver l'affichage des erreurs PHP pour éviter de corrompre le JSON
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Connexion à la base de données
include_once '../../../database/connection.php';

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

    // Vérifier si la connexion à la BDD est bien établie
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }

    $stmt = $pdo->prepare("
        SELECT 
            b.idBesoin, 
            d.service_demandeur, 
            d.nom_prenoms, 
            d.date_demande, 
            d.motif_demande,
            SUM(b.qt_demande) as total_quantity,
            MAX(b.stock_status) as stock_status,
            MAX(b.achat_status) as achat_status,
            MIN(b.created_at) as created_at
        FROM besoins b
        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
        WHERE DATE(b.created_at) BETWEEN :startDate AND :endDate
        GROUP BY b.idBesoin, d.service_demandeur, d.nom_prenoms, d.date_demande, d.motif_demande
        ORDER BY MIN(b.created_at) DESC
    ");
    
    $stmt->bindParam(':startDate', $startDate);
    $stmt->bindParam(':endDate', $endDate);
    $stmt->execute();

    $besoins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Traitement des valeurs NULL pour éviter les problèmes avec JSON
    foreach ($besoins as &$besoin) {
        foreach ($besoin as $key => $value) {
            if ($value === null) {
                $besoin[$key] = '';
            }
        }
    }
    
    echo json_encode($besoins, JSON_NUMERIC_CHECK);
    
} catch (PDOException $e) {
    // Enregistrer l'erreur dans un fichier log plutôt que de l'afficher
    error_log('PDO Error in get_besoins.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in get_besoins.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
?>