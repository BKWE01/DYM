<?php
/**
 * API pour récupérer les détails d'une commande
 * Support amélioré pour les matériaux d'expression_dym et besoins système
 * 
 * @package DYM_MANUFACTURE
 * @subpackage api/orders
 */

// Headers pour API JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Démarrage de session sécurisé
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Non authentifié'
    ]);
    exit();
}

// Connexion à la base de données
require_once '../../../database/connection.php';

try {
    // Récupération des paramètres
    $orderId = $_GET['order_id'] ?? null;
    $expressionId = $_GET['expression_id'] ?? null;
    $designation = $_GET['designation'] ?? null;
    
    // Log amélioré pour debug
    error_log("Order Details API - Params: orderId='$orderId', expressionId='$expressionId', designation='$designation'");
    
    // Validation des paramètres
    if (empty($expressionId) && empty($orderId)) {
        throw new Exception('Paramètres insuffisants : expression_id ou order_id requis');
    }
    
    // Déterminer la source de données avec logique améliorée
    $sourceTable = determineSourceTable($pdo, $expressionId, $designation, $orderId);
    error_log("Source table déterminée: $sourceTable");
    
    // Préparer la requête en fonction de la source
    $orderData = getOrderDetails($pdo, $orderId, $expressionId, $designation, $sourceTable);
    
    if ($orderData) {
        echo json_encode([
            'success' => true,
            'data' => $orderData
        ]);
    } else {
        // Log plus détaillé pour le debug
        error_log("Aucun détail trouvé - sourceTable: $sourceTable, expressionId: $expressionId, designation: $designation, orderId: $orderId");
        echo json_encode([
            'success' => false,
            'message' => 'Aucun détail trouvé pour cette commande',
            'debug' => [
                'source_table' => $sourceTable,
                'expression_id' => $expressionId,
                'designation' => $designation,
                'order_id' => $orderId
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erreur API get_order_details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

/**
 * Détermine la table source avec logique améliorée
 */
function determineSourceTable($pdo, $expressionId, $designation, $orderId) {
    // Si on a un order_id, chercher dans achats_materiaux d'abord
    if (!empty($orderId) && $orderId !== '0') {
        $sql = "SELECT am.expression_id, am.designation,
                       CASE 
                           WHEN b.idBesoin IS NOT NULL THEN 'besoins'
                           WHEN ed.idExpression IS NOT NULL THEN 'expression_dym'
                           ELSE 'unknown'
                       END as source_table
                FROM achats_materiaux am
                LEFT JOIN besoins b ON am.expression_id = b.idBesoin
                LEFT JOIN expression_dym ed ON am.expression_id = ed.idExpression
                WHERE am.id = :order_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['source_table'] !== 'unknown') {
            error_log("Source déterminée par order_id: " . $result['source_table']);
            return $result['source_table'];
        }
    }
    
    // Logique de détection par expression_id
    if (!empty($expressionId)) {
        // Vérifier les indicateurs dans l'ID
        if (preg_match('/^(SYS|SYSTÈME|BES)/i', $expressionId)) {
            error_log("Source déterminée par préfixe SYS/SYSTÈME/BES: besoins");
            return 'besoins';
        }
        
        // Chercher dans besoins d'abord pour les IDs système
        $sql = "SELECT COUNT(*) FROM besoins WHERE idBesoin = :expression_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':expression_id', $expressionId);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            error_log("Trouvé dans besoins avec idBesoin: $expressionId");
            return 'besoins';
        }
        
        // Chercher dans expression_dym
        $sql = "SELECT COUNT(*) FROM expression_dym WHERE idExpression = :expression_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':expression_id', $expressionId);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            error_log("Trouvé dans expression_dym avec idExpression: $expressionId");
            return 'expression_dym';
        }
    }
    
    // Recherche par désignation si l'expression_id ne donne rien
    if (!empty($designation)) {
        // Chercher dans achats_materiaux pour voir quel type d'expression_id est utilisé
        $sql = "SELECT DISTINCT am.expression_id,
                       CASE 
                           WHEN b.idBesoin IS NOT NULL THEN 'besoins'
                           WHEN ed.idExpression IS NOT NULL THEN 'expression_dym'
                           ELSE 'unknown'
                       END as source_table
                FROM achats_materiaux am
                LEFT JOIN besoins b ON am.expression_id = b.idBesoin AND am.designation = :designation
                LEFT JOIN expression_dym ed ON am.expression_id = ed.idExpression AND am.designation = :designation
                WHERE am.designation = :designation
                ORDER BY am.date_achat DESC
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':designation', $designation);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['source_table'] !== 'unknown') {
            error_log("Source déterminée par désignation '$designation': " . $result['source_table']);
            return $result['source_table'];
        }
    }
    
    // Par défaut, essayer besoins pour les systèmes
    error_log("Source par défaut: expression_dym");
    return 'expression_dym';
}

/**
 * Récupère les détails complets d'une commande
 */
function getOrderDetails($pdo, $orderId, $expressionId, $designation, $sourceTable) {
    // Requête pour les informations principales
    $orderInfo = getOrderInfo($pdo, $orderId, $expressionId, $designation, $sourceTable);
    
    if (!$orderInfo) {
        error_log("Aucune info de commande trouvée pour sourceTable: $sourceTable");
        return null;
    }
    
    // Récupérer tous les matériaux associés
    $materials = getMaterials($pdo, $orderInfo, $sourceTable);
    
    // Récupérer l'historique si disponible
    $history = getOrderHistory($pdo, $orderInfo, $sourceTable);
    
    return [
        'order' => $orderInfo,
        'materials' => $materials,
        'history' => $history,
        'source_table' => $sourceTable
    ];
}

/**
 * Récupère les informations principales de la commande
 */
function getOrderInfo($pdo, $orderId, $expressionId, $designation, $sourceTable) {
    if ($sourceTable === 'besoins') {
        return getBesoinsOrderInfo($pdo, $orderId, $expressionId, $designation);
    } else {
        return getExpressionOrderInfo($pdo, $orderId, $expressionId, $designation);
    }
}

/**
 * Récupère les informations pour une commande de type expression_dym
 */
function getExpressionOrderInfo($pdo, $orderId, $expressionId, $designation) {
    $whereConditions = [];
    $params = [];
    
    if ($orderId && $orderId !== '0') {
        $whereConditions[] = "am.id = :order_id";
        $params['order_id'] = $orderId;
    }
    
    if ($expressionId) {
        $whereConditions[] = "am.expression_id = :expression_id";
        $params['expression_id'] = $expressionId;
    }
    
    if ($designation) {
        $whereConditions[] = "am.designation = :designation";
        $params['designation'] = $designation;
    }
    
    if (empty($whereConditions)) {
        error_log("Aucune condition WHERE pour expression_dym");
        return null;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT DISTINCT
            am.id,
            am.expression_id,
            am.designation,
            am.fournisseur,
            am.date_achat,
            am.date_reception,
            am.status,
            am.notes,
            COALESCE(ip.code_projet, 'N/A') as code_projet,
            COALESCE(ip.nom_client, 'N/A') as nom_client,
            u.name as user_achat_name,
            'expression_dym' as source_table
        FROM achats_materiaux am
        LEFT JOIN identification_projet ip ON am.expression_id = ip.idExpression
        LEFT JOIN users_exp u ON am.user_achat = u.id
        $whereClause
        ORDER BY am.date_achat DESC
        LIMIT 1
    ";
    
    error_log("Requête expression_dym: $sql");
    error_log("Paramètres: " . json_encode($params));
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        error_log("Résultat expression_dym trouvé: " . json_encode($result));
    } else {
        error_log("Aucun résultat expression_dym trouvé");
    }
    
    return $result;
}

/**
 * Récupère les informations pour une commande de type besoins - VERSION CORRIGÉE
 */
function getBesoinsOrderInfo($pdo, $orderId, $expressionId, $designation) {
    $whereConditions = [];
    $params = [];
    
    if ($orderId && $orderId !== '0') {
        $whereConditions[] = "am.id = :order_id";
        $params['order_id'] = $orderId;
    }
    
    if ($expressionId) {
        $whereConditions[] = "am.expression_id = :expression_id";
        $params['expression_id'] = $expressionId;
    }
    
    if ($designation) {
        $whereConditions[] = "am.designation = :designation";
        $params['designation'] = $designation;
    }
    
    if (empty($whereConditions)) {
        error_log("Aucune condition WHERE pour besoins");
        return null;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Requête corrigée pour les besoins système
    $sql = "
        SELECT DISTINCT
            am.id,
            am.expression_id,
            am.designation,
            am.fournisseur,
            am.date_achat,
            am.date_reception,
            am.status,
            am.notes,
            am.quantity,
            am.unit,
            am.prix_unitaire,
            CONCAT('SYS-', COALESCE(d.client, 'Système')) as code_projet,
            COALESCE(d.client, 'Demande système') as nom_client,
            u.name as user_achat_name,
            'besoins' as source_table,
            b.qt_demande,
            b.qt_acheter,
            b.caracteristique as unit_besoin,
            d.service_demandeur,
            d.motif_demande,
            d.date_demande,
            b.created_at as besoin_created_at
        FROM achats_materiaux am
        LEFT JOIN besoins b ON am.expression_id = b.idBesoin 
            AND am.designation = b.designation_article
        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
        LEFT JOIN users_exp u ON am.user_achat = u.id
        $whereClause
        ORDER BY am.date_achat DESC
        LIMIT 1
    ";
    
    error_log("Requête besoins: $sql");
    error_log("Paramètres: " . json_encode($params));
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        error_log("Résultat besoins trouvé: " . json_encode($result));
    } else {
        error_log("Aucun résultat besoins trouvé avec les conditions WHERE");
        
        // Essayer une requête plus flexible si aucun résultat
        if (!$result && $expressionId) {
            error_log("Tentative de requête flexible pour expression_id: $expressionId");
            
            $flexibleSql = "
                SELECT DISTINCT
                    am.id,
                    am.expression_id,
                    am.designation,
                    am.fournisseur,
                    am.date_achat,
                    am.date_reception,
                    am.status,
                    am.notes,
                    am.quantity,
                    am.unit,
                    am.prix_unitaire,
                    CONCAT('SYS-', COALESCE(d.client, 'Système')) as code_projet,
                    COALESCE(d.client, 'Demande système') as nom_client,
                    u.name as user_achat_name,
                    'besoins' as source_table,
                    b.qt_demande,
                    b.qt_acheter,
                    b.caracteristique as unit_besoin,
                    d.service_demandeur,
                    d.motif_demande
                FROM achats_materiaux am
                LEFT JOIN besoins b ON am.expression_id = b.idBesoin
                LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                LEFT JOIN users_exp u ON am.user_achat = u.id
                WHERE am.expression_id = :expression_id
                ORDER BY am.date_achat DESC
                LIMIT 1
            ";
            
            $flexibleStmt = $pdo->prepare($flexibleSql);
            $flexibleStmt->bindValue(':expression_id', $expressionId);
            $flexibleStmt->execute();
            $result = $flexibleStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                error_log("Résultat trouvé avec requête flexible");
            }
        }
    }
    
    return $result;
}

/**
 * Récupère les matériaux associés à la commande - VERSION AMÉLIORÉE
 */
function getMaterials($pdo, $orderInfo, $sourceTable) {
    if (!$orderInfo || !isset($orderInfo['expression_id'])) {
        return [];
    }
    
    $sql = "
        SELECT 
            am.id,
            am.designation,
            am.quantity,
            am.unit,
            am.prix_unitaire,
            am.status,
            am.date_achat,
            am.date_reception,
            am.is_partial,
            am.notes
        FROM achats_materiaux am
        WHERE am.expression_id = :expression_id
    ";
    
    $params = ['expression_id' => $orderInfo['expression_id']];
    
    // Ajouter la condition de désignation si disponible
    if (isset($orderInfo['designation']) && !empty($orderInfo['designation'])) {
        $sql .= " AND am.designation = :designation";
        $params['designation'] = $orderInfo['designation'];
    }
    
    $sql .= " ORDER BY am.date_achat DESC";
    
    error_log("Requête matériaux: $sql");
    error_log("Paramètres matériaux: " . json_encode($params));
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->execute();
    
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Nombre de matériaux trouvés: " . count($materials));
    
    return $materials;
}

/**
 * Récupère l'historique d'une commande - VERSION AMÉLIORÉE
 */
function getOrderHistory($pdo, $orderInfo, $sourceTable) {
    if (!$orderInfo || !isset($orderInfo['expression_id'])) {
        return [];
    }
    
    try {
        $sql = "
            SELECT 
                sl.action,
                sl.details,
                sl.created_at,
                sl.username as user_name
            FROM system_logs sl
            WHERE sl.entity_id = :expression_id
            AND sl.type = 'achat'
            ORDER BY sl.created_at DESC
            LIMIT 10
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':expression_id', $orderInfo['expression_id']);
        $stmt->execute();
        
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Historique trouvé: " . count($history) . " entrées");
        
        return $history;
    } catch (Exception $e) {
        error_log("Erreur récupération historique: " . $e->getMessage());
        return [];
    }
}
?>