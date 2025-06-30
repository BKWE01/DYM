<?php

/**
 * API pour récupérer l'historique des modifications des commandes
 * 
 * @package DYM_MANUFACTURE
 * @subpackage api/order_modifications
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non authentifié'
    ]);
    exit();
}

// Inclure la connexion à la base de données
require_once '../../../database/connection.php';

try {
    // Paramètres de DataTables
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 25);
    $search = $_POST['search']['value'] ?? '';

    // Filtres personnalisés
    $userFilter = $_POST['user_filter'] ?? '';
    $typeFilter = $_POST['type_filter'] ?? '';
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';
    $searchFilter = $_POST['search_filter'] ?? '';

    // Ordre de tri
    $orderColumn = $_POST['order'][0]['column'] ?? 0;
    $orderDir = $_POST['order'][0]['dir'] ?? 'desc';

    $columns = ['omh.modified_at', 'u.name', 'omh.order_id', 'omh.expression_id', 'omh.id', 'omh.id', 'omh.modification_reason', 'omh.id'];
    $orderBy = $columns[$orderColumn] ?? 'omh.modified_at';

    // Requête de base
    $baseQuery = "FROM order_modifications_history omh
                  LEFT JOIN users_exp u ON omh.modified_by = u.id";

    // Conditions WHERE
    $whereConditions = ['1=1'];
    $params = [];

    // Filtre utilisateur
    if (!empty($userFilter)) {
        $whereConditions[] = "omh.modified_by = :user_filter";
        $params[':user_filter'] = $userFilter;
    }

    // Filtre par type de modification
    if (!empty($typeFilter)) {
        switch ($typeFilter) {
            case 'quantity':
                $whereConditions[] = "omh.old_quantity != omh.new_quantity";
                break;
            case 'price':
                $whereConditions[] = "omh.old_price != omh.new_price";
                break;
            case 'supplier':
                $whereConditions[] = "omh.old_supplier != omh.new_supplier";
                break;
            case 'payment':
                $whereConditions[] = "omh.old_payment_method != omh.new_payment_method";
                break;
            case 'multiple':
                $whereConditions[] = "(
                    (omh.old_quantity != omh.new_quantity) + 
                    (omh.old_price != omh.new_price) + 
                    (omh.old_supplier != omh.new_supplier) + 
                    (omh.old_payment_method != omh.new_payment_method)
                ) > 1";
                break;
        }
    }

    // Filtre par date
    if (!empty($dateFrom)) {
        $whereConditions[] = "DATE(omh.modified_at) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $whereConditions[] = "DATE(omh.modified_at) <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    // Recherche globale
    if (!empty($search) || !empty($searchFilter)) {
        $searchTerm = !empty($searchFilter) ? $searchFilter : $search;
        $whereConditions[] = "(
        omh.expression_id LIKE :search OR
        omh.modification_reason LIKE :search OR
        omh.old_supplier LIKE :search OR
        omh.new_supplier LIKE :search OR
        u.name LIKE :search OR
        ip.nom_client LIKE :search OR
        ip.description_projet LIKE :search OR
        am.designation LIKE :search OR
        d.client LIKE :search
    )";
        $params[':search'] = '%' . $searchTerm . '%';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    // Requête pour compter le total des enregistrements
    $totalQuery = "SELECT COUNT(*) as total " . $baseQuery . " " . $whereClause;
    $totalStmt = $pdo->prepare($totalQuery);
    $totalStmt->execute($params);
    $totalRecords = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Requête pour les données paginées
    $dataQuery = "SELECT 
                omh.id,
                omh.order_id,
                omh.expression_id,
                omh.old_quantity,
                omh.new_quantity,
                omh.old_price,
                omh.new_price,
                omh.old_supplier,
                omh.new_supplier,
                omh.old_payment_method,
                omh.new_payment_method,
                omh.modification_reason,
                omh.modified_at,
                u.name as user_name,
                -- Informations projet/client (corrigées)
                ip.idExpression,
                ip.nom_client,
                ip.description_projet,
                am.designation as product_designation,
                -- Informations pour les besoins système
                CASE 
                    WHEN ip.idExpression IS NULL THEN 
                        CONCAT('SYSTÈME - ', COALESCE(d.client, 'Demande interne'))
                    ELSE 
                        CONCAT(COALESCE(ip.idExpression, ''), ' - ', COALESCE(ip.nom_client, 'Client inconnu'))
                END as project_display_name
              " . $baseQuery . "
              LEFT JOIN achats_materiaux am ON omh.order_id = am.id
              LEFT JOIN identification_projet ip ON omh.expression_id = ip.idExpression
              LEFT JOIN besoins b ON omh.expression_id = b.idBesoin
              LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
              " . $whereClause . "
              ORDER BY " . $orderBy . " " . strtoupper($orderDir) . "
              LIMIT :start, :length";

    $dataStmt = $pdo->prepare($dataQuery);

    // Lier tous les paramètres
    foreach ($params as $key => $value) {
        $dataStmt->bindValue($key, $value);
    }
    $dataStmt->bindValue(':start', $start, PDO::PARAM_INT);
    $dataStmt->bindValue(':length', $length, PDO::PARAM_INT);

    $dataStmt->execute();
    $modifications = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatage des données pour DataTables
    $formattedData = [];
    foreach ($modifications as $modification) {
        $formattedData[] = [
            'id' => $modification['id'],
            'order_id' => $modification['order_id'],
            'expression_id' => $modification['expression_id'],
            'project_display_name' => $modification['project_display_name'] ?? 'Projet non identifié',
            'idExpression' => $modification['idExpression'],
            'nom_client' => $modification['nom_client'],
            'product_designation' => $modification['product_designation'],
            'old_quantity' => $modification['old_quantity'],
            'new_quantity' => $modification['new_quantity'],
            'old_price' => $modification['old_price'],
            'new_price' => $modification['new_price'],
            'old_supplier' => $modification['old_supplier'],
            'new_supplier' => $modification['new_supplier'],
            'old_payment_method' => $modification['old_payment_method'],
            'new_payment_method' => $modification['new_payment_method'],
            'modification_reason' => $modification['modification_reason'],
            'modified_at' => $modification['modified_at'],
            'user_name' => $modification['user_name']
        ];
    }

    // Réponse pour DataTables
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalRecords,
        'data' => $formattedData,
        'success' => true
    ]);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération de l'historique des modifications: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'draw' => $_POST['draw'] ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
}
