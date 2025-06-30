<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php'; 

// Récupérer la date du mois sélectionné
$data = json_decode(file_get_contents('php://input'), true);
$selectedDate = $data['date'];

// Convertir la date en objet DateTime
$date = new DateTime($selectedDate);
$startOfMonth = $date->format('Y-m-01');
$endOfMonth = $date->format('Y-m-t');

// Fonction pour obtenir l'évolution du stock
function getInventoryData($pdo, $startDate, $endDate) {
    $query = "SELECT DATE(sm.date) as date, 
              SUM(CASE WHEN sm.movement_type = 'entry' THEN sm.quantity ELSE 0 END) as entries,
              SUM(CASE WHEN sm.movement_type = 'sortie' THEN sm.quantity ELSE 0 END) as outputs
              FROM stock_movement sm
              WHERE sm.date BETWEEN :start_date AND :end_date
              GROUP BY DATE(sm.date)
              ORDER BY DATE(sm.date)";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    
    $inventoryData = [
        'labels' => [],
        'entries' => [],
        'outputs' => [],
        'stock' => []
    ];
    
    $currentStock = getInitialStock($pdo, $startDate);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $inventoryData['labels'][] = $row['date'];
        $inventoryData['entries'][] = $row['entries'];
        $inventoryData['outputs'][] = $row['outputs'];
        $currentStock += $row['entries'] - $row['outputs'];
        $inventoryData['stock'][] = $currentStock;
    }
    
    return $inventoryData;
}

// Fonction pour obtenir le stock initial au début du mois
function getInitialStock($pdo, $startDate) {
    $query = "SELECT SUM(quantity) as initial_stock FROM products WHERE created_at < :start_date";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['start_date' => $startDate]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['initial_stock'] ?? 0;
}

// Remplacer getSalesData par getProductMovements
function getProductMovements($pdo, $startDate, $endDate) {
    $query = "SELECT p.product_name, 
              SUM(CASE WHEN sm.movement_type = 'entry' THEN sm.quantity ELSE 0 END) as total_entries,
              SUM(CASE WHEN sm.movement_type = 'sortie' THEN sm.quantity ELSE 0 END) as total_outputs
              FROM stock_movement sm
              JOIN products p ON sm.product_id = p.id
              WHERE sm.date BETWEEN :start_date AND :end_date
              GROUP BY p.product_name
              ORDER BY (total_entries + total_outputs) DESC
              LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    
    $movementData = [
        'labels' => [],
        'entries' => [],
        'outputs' => []
    ];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $movementData['labels'][] = $row['product_name'];
        $movementData['entries'][] = $row['total_entries'];
        $movementData['outputs'][] = $row['total_outputs'];
    }
    
    return $movementData;
}

// Fonction pour obtenir les activités du mois
function getActivitiesData($pdo, $startDate, $endDate) {
    $query = "SELECT p.product_name, sm.movement_type, sm.quantity, sm.date,
              sm.provenance, sm.destination
              FROM stock_movement sm
              JOIN products p ON sm.product_id = p.id
              WHERE sm.date BETWEEN :start_date AND :end_date
              ORDER BY sm.date";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    
    $activitiesData = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activitiesData[] = [
            'task' => $row['product_name'] . ' (' . $row['movement_type'] . ')',
            'start' => $row['date'],
            'end' => $row['date'], // Pour un diagramme de Gantt simple, on utilise la même date
            'details' => "Quantité: " . $row['quantity'] . 
                         ", Provenance: " . $row['provenance'] . 
                         ", Destination: " . $row['destination']
        ];
    }
    
    return $activitiesData;
}

try {
    $response = [
        'inventory' => getInventoryData($pdo, $startOfMonth, $endOfMonth),
        'productMovements' => getProductMovements($pdo, $startOfMonth, $endOfMonth),
        'activities' => getActivitiesData($pdo, $startOfMonth, $endOfMonth)
    ];
    
    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
}
