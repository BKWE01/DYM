<?php
// /User-Finance/api/stock/get_movements_financial_stats.php
// API pour récupérer les statistiques financières des mouvements de stock

session_start();

header('Content-Type: application/json; charset=utf-8');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Non autorisé'
    ]);
    exit();
}

// Connexion à la base de données
include_once '../../../../database/connection.php';

try {
    // Paramètres de filtrage
    $movementType = isset($_GET['movement_type']) ? trim($_GET['movement_type']) : '';
    $projectCode = isset($_GET['project_code']) ? trim($_GET['project_code']) : '';
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    $productFilter = isset($_GET['product_filter']) ? trim($_GET['product_filter']) : '';
    $searchValue = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Vérifier si la table dispatch_details existe
    $checkDispatchTable = $pdo->query("SHOW TABLES LIKE 'dispatch_details'");
    $dispatchTableExists = $checkDispatchTable->rowCount() > 0;

    // Construction des conditions de filtrage
    $params = [];
    $conditions = [];

    // === REQUÊTE PRINCIPALE POUR LES STATISTIQUES FINANCIÈRES ===
    $mainQuery = "
        SELECT 
            CONVERT(sm.movement_type USING utf8mb4) COLLATE utf8mb4_general_ci as movement_type,
            sm.quantity,
            CONVERT(COALESCE(sm.nom_projet, '') USING utf8mb4) COLLATE utf8mb4_general_ci as nom_projet,
            CONVERT(COALESCE(p.product_name, '') USING utf8mb4) COLLATE utf8mb4_general_ci as product_name,
            COALESCE(p.prix_moyen, 0) as prix_moyen,
            COALESCE(p.unit_price, 0) as unit_price,
            sm.date,
            -- Calcul de la valeur selon le type de mouvement
            CASE 
                WHEN sm.movement_type = 'entry' THEN
                    COALESCE(
                        (SELECT CAST(am.prix_unitaire AS DECIMAL(15,2)) 
                         FROM achats_materiaux am 
                         WHERE CONVERT(am.designation USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci
                         AND CONVERT(am.expression_id USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci
                         AND am.status = 'reçu' 
                         AND am.prix_unitaire IS NOT NULL
                         AND am.prix_unitaire != ''
                         AND am.prix_unitaire != '0'
                         ORDER BY am.date_reception DESC LIMIT 1),
                        COALESCE(p.prix_moyen, 0),
                        COALESCE(p.unit_price, 0),
                        0
                    ) * sm.quantity
                WHEN sm.movement_type = 'return' THEN
                    -- Les retours sont comptés comme des entrées positives
                    COALESCE(p.prix_moyen, p.unit_price, 0) * sm.quantity
                ELSE 
                    -- Sorties, transferts
                    COALESCE(p.prix_moyen, p.unit_price, 0) * sm.quantity
            END as valeur_unitaire
        FROM stock_movement sm
        LEFT JOIN products p ON sm.product_id = p.id
        WHERE 1=1
    ";

    // Appliquer les filtres pour la requête principale
    if (!empty($movementType) && $movementType !== 'all') {
        if ($movementType === 'entry') {
            $conditions[] = "sm.movement_type = 'entry'";
        } elseif ($movementType === 'dispatch') {
            $conditions[] = "1=0"; // Exclure de la requête principale
        } else {
            $conditions[] = "sm.movement_type = :movement_type";
            $params[':movement_type'] = $movementType;
        }
    }

    if (!empty($projectCode)) {
        $conditions[] = "CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci = :project_code";
        $params[':project_code'] = $projectCode;
    }

    if (!empty($dateFrom)) {
        $conditions[] = "DATE(sm.date) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $conditions[] = "DATE(sm.date) <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    if (!empty($productFilter)) {
        $conditions[] = "CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :product_filter";
        $params[':product_filter'] = "%{$productFilter}%";
    }

    if (!empty($searchValue)) {
        $searchConditions = [
            "CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search",
            "CONVERT(sm.provenance USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search",
            "CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search",
            "CONVERT(sm.destination USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search",
            "CONVERT(sm.demandeur USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search"
        ];
        $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
        $params[':search'] = "%{$searchValue}%";
    }

    // Ajouter les conditions à la requête
    if (!empty($conditions)) {
        $mainQuery .= " AND " . implode(" AND ", $conditions);
    }

    // === REQUÊTE DISPATCH ===
    $dispatchQuery = "";
    if ($dispatchTableExists && (empty($movementType) || $movementType === 'entry' || $movementType === 'dispatch')) {
        $dispatchQuery = "
    UNION ALL
    SELECT 
        'dispatch' as movement_type,
        dd.allocated as quantity,
        CONVERT(COALESCE(dd.project, '') USING utf8mb4) COLLATE utf8mb4_general_ci as nom_projet,
        CONVERT(COALESCE(p.product_name, '') USING utf8mb4) COLLATE utf8mb4_general_ci as product_name,
        COALESCE(p.prix_moyen, 0) as prix_moyen,
        COALESCE(p.unit_price, 0) as unit_price,
        dd.dispatch_date as date,
        COALESCE(p.prix_moyen, p.unit_price, 0) * dd.allocated as valeur_unitaire
    FROM dispatch_details dd
    LEFT JOIN products p ON dd.product_id = p.id
    LEFT JOIN stock_movement sm_orig ON dd.movement_id = sm_orig.id
    WHERE 1=1
";

        // Appliquer les mêmes filtres pour dispatch
        if (!empty($projectCode)) {
            $dispatchQuery .= " AND CONVERT(dd.project USING utf8mb4) COLLATE utf8mb4_general_ci = :project_code_dispatch";
            $params[':project_code_dispatch'] = $projectCode;
        }

        if (!empty($dateFrom)) {
            $dispatchQuery .= " AND DATE(dd.dispatch_date) >= :date_from_dispatch";
            $params[':date_from_dispatch'] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $dispatchQuery .= " AND DATE(dd.dispatch_date) <= :date_to_dispatch";
            $params[':date_to_dispatch'] = $dateTo;
        }

        if (!empty($productFilter)) {
            $dispatchQuery .= " AND CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :product_filter_dispatch";
            $params[':product_filter_dispatch'] = "%{$productFilter}%";
        }

        if (!empty($searchValue)) {
            $dispatchSearchConditions = [
                "CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search_dispatch",
                "CONVERT(dd.project USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search_dispatch",
                "CONVERT(dd.client USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search_dispatch"
            ];
            $dispatchQuery .= " AND (" . implode(" OR ", $dispatchSearchConditions) . ")";
            $params[':search_dispatch'] = "%{$searchValue}%";
        }
    }

    // === REQUÊTE FINALE POUR CALCULER LES STATISTIQUES ===
    $finalQuery = "
    SELECT 
        -- Statistiques générales
        COUNT(CASE WHEN movement_type IN ('entry', 'dispatch', 'return') THEN 1 END) as total_entries,
        COUNT(CASE WHEN movement_type IN ('output', 'transfer') THEN 1 END) as total_outputs,
        COUNT(CASE WHEN movement_type = 'return' THEN 1 END) as total_returns,
        COUNT(CASE WHEN movement_type = 'transfer' THEN 1 END) as total_transfers,
        
        -- Quantités
        SUM(CASE WHEN movement_type IN ('entry', 'dispatch', 'return') THEN quantity ELSE 0 END) as quantity_entries,
        SUM(CASE WHEN movement_type IN ('output', 'transfer') THEN quantity ELSE 0 END) as quantity_outputs,
        SUM(CASE WHEN movement_type = 'return' THEN quantity ELSE 0 END) as quantity_returns,
        SUM(CASE WHEN movement_type = 'transfer' THEN quantity ELSE 0 END) as quantity_transfers,
        
        -- Valeurs monétaires
        SUM(CASE WHEN movement_type IN ('entry', 'dispatch', 'return') THEN valeur_unitaire ELSE 0 END) as valeur_entries,
        SUM(CASE WHEN movement_type IN ('output', 'transfer') THEN valeur_unitaire ELSE 0 END) as valeur_outputs,
        SUM(CASE WHEN movement_type = 'return' THEN valeur_unitaire ELSE 0 END) as valeur_returns,
        SUM(CASE WHEN movement_type = 'transfer' THEN valeur_unitaire ELSE 0 END) as valeur_transfers,
        
        -- Valeur nette (entrées - sorties)
        SUM(CASE WHEN movement_type IN ('entry', 'dispatch', 'return') THEN valeur_unitaire ELSE 0 END) - 
        SUM(CASE WHEN movement_type IN ('output', 'transfer') THEN valeur_unitaire ELSE 0 END) as valeur_nette,
        
        -- Statistiques par période si des dates sont spécifiées
        " . (!empty($dateFrom) || !empty($dateTo) ? "
        MIN(date) as date_debut,
        MAX(date) as date_fin,
        " : "
        NULL as date_debut,
        NULL as date_fin,
        ") . "
        
        -- Prix moyen des mouvements
        AVG(CASE WHEN movement_type IN ('entry', 'dispatch', 'return') AND quantity > 0 THEN valeur_unitaire/quantity END) as prix_moyen_entrees,
        AVG(CASE WHEN movement_type IN ('output', 'transfer') AND quantity > 0 THEN valeur_unitaire/quantity END) as prix_moyen_sorties
        
    FROM (" . $mainQuery . $dispatchQuery . ") as combined_movements
    ";

    // Préparer et exécuter la requête
    $stmt = $pdo->prepare($finalQuery);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // === FORMATAGE DES RÉSULTATS ===
    
    // S'assurer que tous les valeurs sont des nombres
    $valeur_entries = floatval($stats['valeur_entries'] ?? 0);
    $valeur_outputs = floatval($stats['valeur_outputs'] ?? 0);
    $valeur_returns = floatval($stats['valeur_returns'] ?? 0);
    $valeur_transfers = floatval($stats['valeur_transfers'] ?? 0);
    $valeur_nette = floatval($stats['valeur_nette'] ?? 0);

    // Formatage des valeurs
    $response = [
        'success' => true,
        'stats' => [
            // Statistiques de base
            'total_entries' => intval($stats['total_entries'] ?? 0),
            'total_outputs' => intval($stats['total_outputs'] ?? 0),
            'total_returns' => intval($stats['total_returns'] ?? 0),
            'total_transfers' => intval($stats['total_transfers'] ?? 0),
            
            // Quantités
            'quantity_entries' => floatval($stats['quantity_entries'] ?? 0),
            'quantity_outputs' => floatval($stats['quantity_outputs'] ?? 0),
            'quantity_returns' => floatval($stats['quantity_returns'] ?? 0),
            'quantity_transfers' => floatval($stats['quantity_transfers'] ?? 0),
            
            // Valeurs monétaires (en FCFA)
            'valeur_entries' => round($valeur_entries, 2),
            'valeur_outputs' => round($valeur_outputs, 2),
            'valeur_returns' => round($valeur_returns, 2),
            'valeur_transfers' => round($valeur_transfers, 2),
            'valeur_nette' => round($valeur_nette, 2),
            
            // Valeurs formatées pour l'affichage
            'valeur_entries_formatted' => number_format($valeur_entries, 0, ',', ' ') . ' FCFA',
            'valeur_outputs_formatted' => number_format($valeur_outputs, 0, ',', ' ') . ' FCFA',
            'valeur_returns_formatted' => number_format($valeur_returns, 0, ',', ' ') . ' FCFA',
            'valeur_transfers_formatted' => number_format($valeur_transfers, 0, ',', ' ') . ' FCFA',
            'valeur_nette_formatted' => number_format($valeur_nette, 0, ',', ' ') . ' FCFA',
            
            // Prix moyens
            'prix_moyen_entrees' => round(floatval($stats['prix_moyen_entrees'] ?? 0), 2),
            'prix_moyen_sorties' => round(floatval($stats['prix_moyen_sorties'] ?? 0), 2),
            
            // Période analysée
            'periode' => [
                'date_debut' => $stats['date_debut'] ?? null,
                'date_fin' => $stats['date_fin'] ?? null,
                'filtres_appliques' => !empty($dateFrom) || !empty($dateTo) || !empty($movementType) || !empty($projectCode)
            ]
        ],
        'filters_applied' => [
            'movement_type' => $movementType,
            'project_code' => $projectCode,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'product_filter' => $productFilter,
            'search' => $searchValue
        ],
        'debug' => [
            'has_dispatch_table' => $dispatchTableExists,
            'query_executed' => true,
            'params_count' => count($params)
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    
    // Log détaillé de l'erreur
    error_log("Erreur SQL dans get_movements_financial_stats.php: " . $e->getMessage());
    error_log("Code d'erreur: " . $e->getCode());
    error_log("Info SQL: " . $e->errorInfo[2] ?? 'N/A');
    
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'debug' => [
            'sql_error' => true,
            'error_type' => 'PDOException',
            'suggestion' => 'Vérifiez les collations des tables de la base de données'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    
    error_log("Erreur générale dans get_movements_financial_stats.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erreur système: ' . $e->getMessage(),
        'error_code' => 'SYSTEM_ERROR',
        'debug' => [
            'general_error' => true,
            'error_type' => 'Exception'
        ]
    ]);
}
?>