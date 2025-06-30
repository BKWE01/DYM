<?php
// Activer la capture de toutes les erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fonction pour gérer les erreurs et les renvoyer en JSON
function handleError($errno, $errstr, $errfile, $errline)
{
    $response = [
        'success' => false,
        'message' => "Erreur PHP : [$errno] $errstr dans le fichier $errfile à la ligne $errline"
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Définir le gestionnaire d'erreurs personnalisé
set_error_handler("handleError");

// Connexion à la base de données
include_once '../../database/connection.php';

// Paramètres de pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
$offset = ($page - 1) * $limit;

// Paramètres de recherche et de filtrage
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$movement_type = isset($_GET['movement_type']) ? trim($_GET['movement_type']) : '';
$project_code = isset($_GET['project_code']) ? trim($_GET['project_code']) : '';

// Récupérer la collation par défaut
$collationQuery = "SELECT @@collation_connection as collation_connection";
$collationStmt = $pdo->query($collationQuery);
$collation = $collationStmt->fetch(PDO::FETCH_ASSOC)['collation_connection'];

// Requête principale avec conversion explicite de collation pour les champs texte
$query = "
    SELECT 
        sm.id, 
        sm.product_id, 
        CONVERT(p.product_name USING utf8) COLLATE utf8_general_ci as product_name, 
        sm.quantity, 
        CONVERT(sm.movement_type USING utf8) COLLATE utf8_general_ci as movement_type, 
        CONVERT(sm.provenance USING utf8) COLLATE utf8_general_ci as provenance, 
        CONVERT(sm.nom_projet USING utf8) COLLATE utf8_general_ci as nom_projet, 
        CONVERT(sm.destination USING utf8) COLLATE utf8_general_ci as destination, 
        CONVERT(sm.demandeur USING utf8) COLLATE utf8_general_ci as demandeur, 
        CONVERT(sm.notes USING utf8) COLLATE utf8_general_ci as notes,
        sm.date,
        sm.invoice_id
    FROM stock_movement sm 
    LEFT JOIN products p ON sm.product_id = p.id 
    WHERE 1=1
";

$params = [];

// Filtrage spécifique pour les retours fournisseurs
if (!empty($movement_type)) {
    if ($movement_type === 'supplier-return') {
        $query .= " AND sm.movement_type = 'output' AND sm.destination LIKE 'Retour fournisseur:%'";
    } else if ($movement_type !== 'dispatch') {
        $query .= " AND sm.movement_type = :movement_type";
        if ($movement_type === 'output') {
            $query .= " AND (sm.destination NOT LIKE 'Retour fournisseur:%' OR sm.destination IS NULL)";
        }
        $params[':movement_type'] = $movement_type;
    }
}

// Filtrer par projet si spécifié
if (!empty($project_code)) {
    $query .= " AND sm.nom_projet = :project_code";
    $params[':project_code'] = $project_code;
}

// Recherche textuelle
if (!empty($search)) {
    $query .= " AND (p.product_name LIKE :search OR sm.provenance LIKE :search OR sm.nom_projet LIKE :search OR sm.destination LIKE :search OR sm.demandeur LIKE :search)";
    $params[':search'] = "%$search%";
}

// Si on inclut les dispatching (par défaut ou si spécifiquement demandé)
if (empty($movement_type) || $movement_type === 'dispatch') {
    // Vérifier si la table dispatch_details existe
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'dispatch_details'");
    $dispatchTableExists = $checkTableStmt->rowCount() > 0;

    if ($dispatchTableExists) {
        // Union avec les données de dispatching
        $unionQuery = "
        UNION
        SELECT 
            dd.movement_id as id, 
            dd.product_id, 
            CONVERT(p.product_name USING utf8) COLLATE utf8_general_ci as product_name, 
            dd.allocated as quantity, 
            'dispatch' as movement_type, 
            '' as provenance, 
            CONVERT(dd.project USING utf8) COLLATE utf8_general_ci as nom_projet, 
            CONVERT(dd.client USING utf8) COLLATE utf8_general_ci as destination, 
            '' as demandeur, 
            CONVERT(dd.notes USING utf8) COLLATE utf8_general_ci as notes,
            dd.dispatch_date as date,
            (SELECT invoice_id FROM stock_movement WHERE id = dd.movement_id) as invoice_id
        FROM dispatch_details dd
        LEFT JOIN products p ON dd.product_id = p.id
        WHERE 1=1
    ";

        // Ajouter les conditions de filtrage pour les dispatching
        if (!empty($project_code)) {
            $unionQuery .= " AND dd.project = :project_code_union";
            $params[':project_code_union'] = $project_code;
        }

        if (!empty($search)) {
            $unionQuery .= " AND (p.product_name LIKE :search_union OR dd.project LIKE :search_union OR dd.client LIKE :search_union)";
            $params[':search_union'] = "%$search%";
        }

        $query .= " " . $unionQuery;
    }
}

// Finalisation de la requête avec tri et pagination
$query .= " ORDER BY date DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);

// Liaison des paramètres
if (isset($params) && is_array($params)) {
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

try {
    $stmt->execute();
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Requête de comptage similaire mais sans pagination
    $countSql = "
        SELECT COUNT(*) as total FROM (
            SELECT sm.id
            FROM stock_movement sm
            LEFT JOIN products p ON sm.product_id = p.id
            WHERE 1=1
    ";

    $countParams = [];

    // Filtrage spécifique pour les retours fournisseurs dans le comptage
    if (!empty($movement_type)) {
        if ($movement_type === 'supplier-return') {
            $countSql .= " AND sm.movement_type = 'output' AND sm.destination LIKE 'Retour fournisseur:%'";
        } else if ($movement_type !== 'dispatch') {
            $countSql .= " AND sm.movement_type = :movement_type";
            if ($movement_type === 'output') {
                $countSql .= " AND (sm.destination NOT LIKE 'Retour fournisseur:%' OR sm.destination IS NULL)";
            }
            $countParams[':movement_type'] = $movement_type;
        }
    }

    // Filtrer par projet si spécifié
    if (!empty($project_code)) {
        $countSql .= " AND sm.nom_projet = :project_code";
        $countParams[':project_code'] = $project_code;
    }

    // Recherche textuelle
    if (!empty($search)) {
        $countSql .= " AND (p.product_name LIKE :search OR sm.provenance LIKE :search OR sm.nom_projet LIKE :search OR sm.destination LIKE :search OR sm.demandeur LIKE :search)";
        $countParams[':search'] = "%$search%";
    }

    // Si on inclut les dispatching et que la table existe
    if ((empty($movement_type) || $movement_type === 'dispatch') && $dispatchTableExists) {
        $countSql .= "
            UNION
            SELECT dd.movement_id as id
            FROM dispatch_details dd
            LEFT JOIN products p ON dd.product_id = p.id
            WHERE 1=1
        ";

        // Ajouter les conditions de filtrage pour les dispatching
        if (!empty($project_code)) {
            $countSql .= " AND dd.project = :project_code_union";
            $countParams[':project_code_union'] = $project_code;
        }

        if (!empty($search)) {
            $countSql .= " AND (p.product_name LIKE :search_union OR dd.project LIKE :search_union OR dd.client LIKE :search_union)";
            $countParams[':search_union'] = "%$search%";
        }
    }

    $countSql .= ") as combined_movements";

    $countStmt = $pdo->prepare($countSql);

    // Liaison des paramètres pour la requête de comptage
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }

    $countStmt->execute();
    $totalResults = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Calculer le nombre total de pages
    $totalPages = ceil($totalResults / $limit);

    // Préparer la réponse
    $response = [
        'success' => true,
        'movements' => $movements,
        'totalPages' => $totalPages,
        'currentPage' => $page,
        'totalResults' => $totalResults
    ];

    // Envoyer la réponse JSON
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (PDOException $e) {
    $response = [
        'success' => false,
        'message' => 'Erreur SQL : ' . $e->getMessage()
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}