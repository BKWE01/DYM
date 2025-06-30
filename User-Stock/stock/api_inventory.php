<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php'; 

function getPeriodDates($period) {
    $end_date = date('Y-m-d H:i:s'); // Date actuelle
    
    if ($period === 'week') {
        $start_date = date('Y-m-d H:i:s', strtotime('-1 week'));
    } elseif ($period === 'month') {
        $start_date = date('Y-m-01 00:00:00'); // Premier jour du mois en cours
    } else {
        // Par défaut, utilisez le mois en cours
        $start_date = date('Y-m-01 00:00:00');
    }
    
    return ['start_date' => $start_date, 'end_date' => $end_date];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $period = isset($_GET['period']) ? $_GET['period'] : 'month';
        $dates = getPeriodDates($period);
        
        $query = "
            SELECT 
                p.id, 
                p.barcode, 
                p.product_name, 
                p.quantity,
                p.quantity_reserved,
                (p.quantity - COALESCE(p.quantity_reserved, 0)) as available_quantity,
                p.unit_price,
                p.category,
                p.created_at,
                p.updated_at,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'entry' AND sm.date BETWEEN :start_date AND :end_date THEN sm.quantity ELSE 0 END), 0) as total_entries,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'output' AND sm.date BETWEEN :start_date AND :end_date THEN sm.quantity ELSE 0 END), 0) as total_outputs
            FROM 
                products p
            LEFT JOIN 
                stock_movement sm ON p.id = sm.product_id
            GROUP BY 
                p.id
            ORDER BY 
                p.product_name
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':start_date', $dates['start_date']);
        $stmt->bindParam(':end_date', $dates['end_date']);
        $stmt->execute();
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcul des totaux d'entrées et de sorties
        $totalEntries = 0;
        $totalOutputs = 0;
        foreach ($inventory as &$product) {
            $totalEntries += $product['total_entries'];
            $totalOutputs += $product['total_outputs'];
            
            // On utilise directement la valeur de la colonne quantity comme current_quantity
            $product['current_quantity'] = $product['quantity'];
        }

        $response = [
            'inventory' => $inventory,
            'totalEntries' => $totalEntries,
            'totalOutputs' => $totalOutputs,
            'period' => $period,
            'startDate' => $dates['start_date'],
            'endDate' => $dates['end_date']
        ];

        echo json_encode($response);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la récupération de l\'inventaire: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
}