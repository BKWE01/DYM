<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

// Connexion à la base de données
include_once '../../../database/connection.php';

$period = isset($_GET['period']) ? $_GET['period'] : 'month'; // week, month, year
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;

try {
    // Déterminer la clause de période
    $periodClause = "";
    $groupBy = "";
    $dateFormat = "";

    switch ($period) {
        case 'week':
            $periodClause = "AND sm.date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)";
            $groupBy = "DATE(sm.date)";
            $dateFormat = "%d/%m";
            break;
        case 'month':
            $periodClause = "AND sm.date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)";
            $groupBy = "DATE(sm.date)";
            $dateFormat = "%d/%m";
            break;
        case 'year':
            $periodClause = "AND sm.date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)";
            $groupBy = "MONTH(sm.date), YEAR(sm.date)";
            $dateFormat = "%m/%Y";
            break;
        default:
            $periodClause = "AND sm.date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)";
            $groupBy = "DATE(sm.date)";
            $dateFormat = "%d/%m";
    }

    // Construction de la clause de catégorie
    $categoryClause = "";
    $params = [];

    if ($category > 0) {
        $categoryClause = "AND p.category = :category";
        $params[':category'] = $category;
    }

    // Requête pour obtenir les mouvements agrégés par jour/mois
    $sql = "SELECT 
                DATE_FORMAT(sm.date, :dateFormat) as date_label,
                $groupBy as group_date,
                SUM(CASE WHEN sm.movement_type = 'entry' THEN sm.quantity ELSE 0 END) as entries,
                SUM(CASE WHEN sm.movement_type = 'output' THEN sm.quantity ELSE 0 END) as outputs,
                SUM(CASE WHEN sm.movement_type = 'entry' THEN sm.quantity 
                     WHEN sm.movement_type = 'output' THEN -sm.quantity ELSE 0 END) as net_change
            FROM stock_movement sm
            JOIN products p ON sm.product_id = p.id
            WHERE 1=1 
            $periodClause
            $categoryClause
            GROUP BY $groupBy, date_label
            ORDER BY group_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':dateFormat', $dateFormat);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $movementsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les catégories pour le filtre
    $categoriesSql = "SELECT id, libelle, code FROM categories ORDER BY libelle";
    $categoriesStmt = $pdo->query($categoriesSql);
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer les statistiques globales
    $totalEntries = 0;
    $totalOutputs = 0;
    $maxDaily = 0;

    foreach ($movementsData as $day) {
        $totalEntries += $day['entries'];
        $totalOutputs += $day['outputs'];
        $maxDaily = max($maxDaily, $day['entries'], $day['outputs']);
    }

    $stats = [
        'total_entries' => $totalEntries,
        'total_outputs' => $totalOutputs,
        'net_change' => $totalEntries - $totalOutputs,
        'max_daily' => $maxDaily,
        'period' => $period,
        'days_count' => count($movementsData)
    ];

    // Si aucune donnée n'est trouvée, générer des données vides avec les dates
    if (empty($movementsData)) {
        $movementsData = generateEmptyData($period);
    }

    echo json_encode([
        'success' => true,
        'data' => $movementsData,
        'categories' => $categories,
        'current_category' => $category,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
    exit();
}

// Fonction pour générer des données vides sur la période
function generateEmptyData($period)
{
    $data = [];
    $endDate = new DateTime();
    $interval = new DateInterval('P1D'); // 1 jour par défaut

    switch ($period) {
        case 'week':
            $startDate = new DateTime();
            $startDate->modify('-7 days');
            break;
        case 'month':
            $startDate = new DateTime();
            $startDate->modify('-30 days');
            break;
        case 'year':
            $startDate = new DateTime();
            $startDate->modify('-12 months');
            $interval = new DateInterval('P1M'); // 1 mois
            break;
        default:
            $startDate = new DateTime();
            $startDate->modify('-30 days');
    }

    $dateRange = new DatePeriod($startDate, $interval, $endDate);

    foreach ($dateRange as $date) {
        $label = $period === 'year' ? $date->format('m/Y') : $date->format('d/m');
        $data[] = [
            'date_label' => $label,
            'group_date' => $period === 'year' ? $date->format('Y-m-01') : $date->format('Y-m-d'),
            'entries' => 0,
            'outputs' => 0,
            'net_change' => 0
        ];
    }

    return $data;
}
?>