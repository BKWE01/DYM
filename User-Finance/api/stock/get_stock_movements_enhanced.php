<?php
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
include_once '../../../database/connection.php';

try {
    // Paramètres DataTables
    $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
    $searchValue = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';

    // Paramètres de filtrage personnalisés
    $movementType = isset($_GET['movement_type']) ? trim($_GET['movement_type']) : '';
    $projectCode = isset($_GET['project_code']) ? trim($_GET['project_code']) : '';
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    $productFilter = isset($_GET['product_filter']) ? trim($_GET['product_filter']) : '';

    // Colonnes pour le tri
    $columns = [
        'id',
        'product_name',
        'quantity',
        'movement_type',
        'provenance',
        'nom_projet',
        'destination',
        'demandeur',
        'date'
    ];

    $orderColumn = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 8;
    $orderDirection = isset($_GET['order'][0]['dir']) && $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
    $orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'date';

    // Vérifier si la table dispatch_details existe
    $checkDispatchTable = $pdo->query("SHOW TABLES LIKE 'dispatch_details'");
    $dispatchTableExists = $checkDispatchTable->rowCount() > 0;

    // Récupérer la collation par défaut
    $collationQuery = "SELECT @@collation_connection as collation_connection";
    $collationStmt = $pdo->query($collationQuery);
    $collation = $collationStmt->fetch(PDO::FETCH_ASSOC)['collation_connection'];

    // Construire la requête UNION avec collation forcée
    $params = [];
    $conditions = [];

    // === REQUÊTE PRINCIPALE (stock_movement) ===
    $mainQuery = "
        SELECT 
            sm.id,
            sm.product_id,
            CONVERT(COALESCE(p.product_name, '') USING utf8mb4) COLLATE utf8mb4_general_ci as product_name,
            sm.quantity,
            CONVERT(sm.movement_type USING utf8mb4) COLLATE utf8mb4_general_ci as movement_type,
            CONVERT(COALESCE(sm.provenance, '') USING utf8mb4) COLLATE utf8mb4_general_ci as provenance,
            CONVERT(COALESCE(sm.nom_projet, '') USING utf8mb4) COLLATE utf8mb4_general_ci as nom_projet,
            CONVERT(COALESCE(sm.destination, '') USING utf8mb4) COLLATE utf8mb4_general_ci as destination,
            CONVERT(COALESCE(sm.demandeur, '') USING utf8mb4) COLLATE utf8mb4_general_ci as demandeur,
            CONVERT(COALESCE(sm.notes, '') USING utf8mb4) COLLATE utf8mb4_general_ci as notes,
            sm.date,
            sm.invoice_id,
            CONVERT(COALESCE(c.libelle, '') USING utf8mb4) COLLATE utf8mb4_general_ci as category_name,
            CONVERT(COALESCE(p.unit, '') USING utf8mb4) COLLATE utf8mb4_general_ci as unit
        FROM stock_movement sm
        LEFT JOIN products p ON sm.product_id = p.id
        LEFT JOIN categories c ON p.category = c.id
        WHERE 1=1
    ";

    // Filtres pour la requête principale
    if (!empty($movementType) && $movementType !== 'all') {
        if ($movementType === 'entry') {
            // Pour les entrées, inclure les vraies entrées mais pas les dispatches
            $conditions[] = "sm.movement_type = 'entry'";
        } elseif ($movementType === 'dispatch') {
            // Pour les dispatches uniquement, exclure tout de la table principale
            $conditions[] = "1=0"; // Condition impossible
        } else {
            // Pour output, return, transfer
            $conditions[] = "sm.movement_type = :movement_type";
            $params[':movement_type'] = $movementType;
        }
    }

    if (!empty($projectCode)) {
        $conditions[] = "sm.nom_projet = :project_code";
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
        $conditions[] = "p.product_name LIKE :product_filter";
        $params[':product_filter'] = "%{$productFilter}%";
    }

    // Recherche globale pour la requête principale
    if (!empty($searchValue)) {
        $searchConditions = [
            "p.product_name LIKE :search",
            "sm.provenance LIKE :search",
            "sm.nom_projet LIKE :search",
            "sm.destination LIKE :search",
            "sm.demandeur LIKE :search"
        ];
        $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
        $params[':search'] = "%{$searchValue}%";
    }

    // Ajouter les conditions à la requête principale
    if (!empty($conditions)) {
        $mainQuery .= " AND " . implode(" AND ", $conditions);
    }

    // === REQUÊTE DISPATCH (si la table existe) ===
    $dispatchQuery = "";
    if ($dispatchTableExists && (empty($movementType) || $movementType === 'entry' || $movementType === 'dispatch')) {
        $dispatchQuery = "
    UNION ALL
    SELECT 
        dd.movement_id as id,
        dd.product_id,
        CONVERT(COALESCE(p.product_name, '') USING utf8mb4) COLLATE utf8mb4_general_ci as product_name,
        dd.allocated as quantity,
        CONVERT('dispatch' USING utf8mb4) COLLATE utf8mb4_general_ci as movement_type,
        CONVERT(COALESCE(sm_orig.provenance, '') USING utf8mb4) COLLATE utf8mb4_general_ci as provenance,
        CONVERT(COALESCE(dd.project, '') USING utf8mb4) COLLATE utf8mb4_general_ci as nom_projet,
        CONVERT(COALESCE(dd.client, '') USING utf8mb4) COLLATE utf8mb4_general_ci as destination,
        CONVERT(COALESCE(sm_orig.demandeur, '') USING utf8mb4) COLLATE utf8mb4_general_ci as demandeur,
        CONVERT(COALESCE(dd.notes, '') USING utf8mb4) COLLATE utf8mb4_general_ci as notes,
        dd.dispatch_date as date,
        sm_orig.invoice_id,
        CONVERT(COALESCE(c.libelle, '') USING utf8mb4) COLLATE utf8mb4_general_ci as category_name,
        CONVERT(COALESCE(p.unit, '') USING utf8mb4) COLLATE utf8mb4_general_ci as unit
    FROM dispatch_details dd
    LEFT JOIN products p ON dd.product_id = p.id
    LEFT JOIN categories c ON p.category = c.id
    LEFT JOIN stock_movement sm_orig ON dd.movement_id = sm_orig.id
    WHERE 1=1
";

        // Filtres pour les dispatches
        if (!empty($projectCode)) {
            $dispatchQuery .= " AND dd.project = :project_code_dispatch";
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
            $dispatchQuery .= " AND p.product_name LIKE :product_filter_dispatch";
            $params[':product_filter_dispatch'] = "%{$productFilter}%";
        }

        // Recherche globale pour les dispatches
        if (!empty($searchValue)) {
            $dispatchSearchConditions = [
                "p.product_name LIKE :search_dispatch",
                "dd.project LIKE :search_dispatch",
                "dd.client LIKE :search_dispatch"
            ];
            $dispatchQuery .= " AND (" . implode(" OR ", $dispatchSearchConditions) . ")";
            $params[':search_dispatch'] = "%{$searchValue}%";
        }
    }

    // === REQUÊTE COMPLÈTE ===
    $fullQuery = "SELECT * FROM (" . $mainQuery . $dispatchQuery . ") as combined_results";

    // Compter le total
    $countQuery = "SELECT COUNT(*) as total FROM (" . $mainQuery . $dispatchQuery . ") as combined_count";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Ajouter le tri et la pagination
    $fullQuery .= " ORDER BY {$orderBy} {$orderDirection} LIMIT :start, :length";

    $dataStmt = $pdo->prepare($fullQuery);

    // Lier les paramètres
    foreach ($params as $key => $value) {
        $dataStmt->bindValue($key, $value);
    }
    $dataStmt->bindValue(':start', $start, PDO::PARAM_INT);
    $dataStmt->bindValue(':length', $length, PDO::PARAM_INT);

    $dataStmt->execute();
    $movements = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    // Traitement des données pour DataTables
    $data = [];
    foreach ($movements as $movement) {
        // Badge pour le type de mouvement
        $typeBadge = '';
        switch (strtolower($movement['movement_type'])) {
            case 'entry':
                $typeBadge = '<span class="badge badge-entry">Entrée</span>';
                break;
            case 'output':
                $typeBadge = '<span class="badge badge-output">Sortie</span>';
                break;
            case 'dispatch':
                // Les dispatches sont des entrées spéciales
                $typeBadge = '<span class="badge badge-entry">Entrée</span>';
                break;
            case 'return':
                $typeBadge = '<span class="badge badge-blue">Retour</span>';
                break;
            case 'transfer':
                $typeBadge = '<span class="badge badge-yellow">Transfert</span>';
                break;
            case 'adjustment':
                $typeBadge = '<span class="badge" style="background-color: #e1f5fe; color: #0277bd; border: 1px solid #0277bd;">Ajustement</span>';
                break;
            default:
                $typeBadge = '<span class="badge badge-gray">' . ucfirst($movement['movement_type']) . '</span>';
        }

        // Formatage de la date
        $formattedDate = date('d/m/Y H:i', strtotime($movement['date']));

        // Bouton facture
        $invoiceButton = '';
        if ($movement['invoice_id']) {
            $invoiceButton = '<button class="btn btn-sm btn-outline-primary" onclick="previewInvoice(' . $movement['invoice_id'] . ')" title="Voir facture">
                <span class="material-icons" style="font-size: 16px;">description</span>
                Facture #' . $movement['invoice_id'] . '
            </button>';
        } else {
            $invoiceButton = '<span class="text-muted">-</span>';
        }

        // Gestion des données communes
        $provenance = $movement['provenance'] ?: '-';
        $destination = $movement['destination'] ?: '-';
        $demandeur = $movement['demandeur'] ?: '-';
        $projet = $movement['nom_projet'] ? '<span class="badge badge-blue">' . $movement['nom_projet'] . '</span>' : '-';

        // Pour les dispatches, ajuster l'affichage du projet et destination
        if ($movement['movement_type'] === 'dispatch') {
            // Le projet devient celui du dispatch, la destination le client
            $projet = $movement['nom_projet'] ? '<span class="badge badge-green">' . $movement['nom_projet'] . '</span>' : '-';
            $destination = $movement['destination'] ? $movement['destination'] . ' (Client)' : '-';
        }

        // Actions
        $actions = '<div class="btn-group">
            <button class="btn btn-sm btn-outline-info" onclick="viewMovementDetails(' . $movement['id'] . ')" title="Voir détails">
                <span class="material-icons" style="font-size: 16px;">visibility</span>
            </button>
        </div>';

        $data[] = [
            $movement['id'],
            $movement['product_name'] . '<br><small class="text-muted">' . ($movement['category_name'] ?? '') . '</small>',
            '<strong>' . number_format($movement['quantity'], 2) . '</strong> ' . ($movement['unit'] ?? ''),
            $typeBadge,
            $provenance,
            $projet,
            $destination,
            $demandeur,
            $formattedDate,
            $invoiceButton,
            $actions
        ];
    }

    // Statistiques supplémentaires (dispatches comptés comme entrées)
    $statsQuery = "
    SELECT 
        COUNT(CASE WHEN movement_type IN ('entry', 'dispatch') THEN 1 END) as total_entries,
        COUNT(CASE WHEN movement_type = 'output' THEN 1 END) as total_outputs,
        COUNT(CASE WHEN movement_type = 'return' THEN 1 END) as total_returns,
        COUNT(CASE WHEN movement_type = 'transfer' THEN 1 END) as total_transfers,
        SUM(CASE WHEN movement_type IN ('entry', 'dispatch') THEN quantity ELSE 0 END) as quantity_entries,
        SUM(CASE WHEN movement_type = 'output' THEN quantity ELSE 0 END) as quantity_outputs
    FROM (" . $mainQuery . $dispatchQuery . ") as stats_data
";

    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Réponse JSON pour DataTables
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalRecords,
        'data' => $data,
        'stats' => $stats
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);

    error_log("Erreur SQL dans get_stock_movements_enhanced.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur système: ' . $e->getMessage()
    ]);

    error_log("Erreur générale dans get_stock_movements_enhanced.php: " . $e->getMessage());
}
