<?php

/**
 * ================================================================
 * API DE RECHERCHE DE BONS DE COMMANDE PAR PRODUIT - VERSION CORRIGÉE OVH
 * ================================================================
 * 
 * Cette API permet de rechercher les bons de commande contenant
 * un produit spécifique par son nom/désignation.
 * 
 * CORRECTION : Problème de collation MySQL sur serveurs OVH
 * Ajout de COLLATE utf8mb4_general_ci pour forcer la collation
 * 
 * @author DYM Manufacture
 * @version 2.3.0
 * @created 2025-06-16
 * @updated 2025-06-16 - Correction problème collation OVH
 */

session_start();
header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non connecté',
        'error_code' => 'UNAUTHORIZED',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Connexion à la base de données
try {
    include_once '../../../../database/connection.php';
    
    // Forcer la collation pour éviter les conflits sur OVH
    $pdo->exec("SET collation_connection = 'utf8mb4_general_ci'");
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données',
        'error_code' => 'CONNECTION_ERROR',
        'debug' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Récupérer le terme de recherche (support GET et POST)
$searchTerm = '';

// Vérifier les différentes sources de paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Paramètres POST
    $searchTerm = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
    if (empty($searchTerm)) {
        $searchTerm = isset($_POST['q']) ? trim($_POST['q']) : '';
    }
} else {
    // Paramètres GET
    $searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
    if (empty($searchTerm)) {
        $searchTerm = isset($_GET['product_name']) ? trim($_GET['product_name']) : '';
    }
}

// Validation des paramètres
if (empty($searchTerm)) {
    echo json_encode([
        'success' => false,
        'message' => 'Terme de recherche manquant',
        'error_code' => 'MISSING_SEARCH_TERM',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'post_data' => $_POST,
            'get_data' => $_GET
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

if (strlen($searchTerm) < 2) {
    echo json_encode([
        'success' => false,
        'message' => 'Le terme de recherche doit contenir au moins 2 caractères',
        'error_code' => 'SEARCH_TOO_SHORT',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

try {
    // ================================================================
    // REQUÊTE PRINCIPALE DE RECHERCHE - VERSION CORRIGÉE OVH
    // Utilisation de COLLATE utf8mb4_general_ci pour éviter les conflits
    // ================================================================

    $searchQuery = "
        SELECT DISTINCT
            po.id as order_id,
            po.order_number,
            po.download_reference,
            po.expression_id,
            po.related_expressions,
            po.file_path,
            po.fournisseur,
            po.montant_total,
            po.user_id,
            po.is_multi_project,
            po.generated_at,
            po.signature_finance,
            po.user_finance_id,
            u.name as username,
            uf.name as finance_username,
            am.designation as product_found,
            am.quantity as product_quantity,
            am.prix_unitaire as product_unit_price,
            am.unit as product_unit,
            CASE 
                WHEN po.expression_id LIKE '%EXP_B%' THEN 'besoins' 
                ELSE 'expression_dym' 
            END as source_table,
            CASE 
                WHEN po.signature_finance IS NOT NULL AND po.user_finance_id IS NOT NULL 
                THEN 'validé' 
                ELSE 'en_attente' 
            END as status,
            -- Score de pertinence pour le tri avec COLLATE forcé
            CASE 
                WHEN am.designation COLLATE utf8mb4_general_ci = ? COLLATE utf8mb4_general_ci THEN 5
                WHEN am.designation COLLATE utf8mb4_general_ci LIKE CONCAT(? COLLATE utf8mb4_general_ci, '%') THEN 3
                WHEN am.designation COLLATE utf8mb4_general_ci LIKE CONCAT('%', ? COLLATE utf8mb4_general_ci, '%') THEN 2
                ELSE 1
            END as relevance_score
        FROM purchase_orders po
        LEFT JOIN users_exp u ON po.user_id = u.id
        LEFT JOIN users_exp uf ON po.user_finance_id = uf.id
        INNER JOIN achats_materiaux am ON (
            (po.expression_id COLLATE utf8mb4_general_ci = am.expression_id COLLATE utf8mb4_general_ci
             AND po.fournisseur COLLATE utf8mb4_general_ci = am.fournisseur COLLATE utf8mb4_general_ci
             AND DATE(po.generated_at) = DATE(am.date_achat))
            OR 
            (po.related_expressions IS NOT NULL AND 
             JSON_SEARCH(po.related_expressions, 'one', am.expression_id COLLATE utf8mb4_general_ci) IS NOT NULL AND 
             po.fournisseur COLLATE utf8mb4_general_ci = am.fournisseur COLLATE utf8mb4_general_ci AND 
             DATE(po.generated_at) = DATE(am.date_achat))
        )
        WHERE am.designation COLLATE utf8mb4_general_ci LIKE ? COLLATE utf8mb4_general_ci
        ORDER BY relevance_score DESC, po.generated_at DESC
        LIMIT 50
    ";

    // Préparer les paramètres de recherche
    $searchPattern = '%' . $searchTerm . '%';

    $stmt = $pdo->prepare($searchQuery);
    $stmt->execute([
        $searchTerm,      // Correspondance exacte
        $searchTerm,      // Commence par
        $searchTerm,      // Contient
        $searchPattern    // designation LIKE
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // FORMATAGE DES RÉSULTATS POUR COMPATIBILITÉ
    // ================================================================

    $formattedResults = [];
    $productsList = [];

    foreach ($results as $row) {
        // Collecter les produits uniques
        if (!in_array($row['product_found'], $productsList)) {
            $productsList[] = $row['product_found'];
        }

        // Informations projet
        $projectInfo = "";
        if ($row['is_multi_project'] == 1) {
            $relatedExpressions = json_decode($row['related_expressions'], true);
            $projectCount = $relatedExpressions ? count($relatedExpressions) : 1;
            $projectInfo = "Multi-projets ($projectCount)";
        } else {
            $projectInfo = $row['expression_id'];
        }

        // Formatage des dates - CORRECTION IMPORTANTE
        $rawDate = $row['generated_at'];
        $formattedDisplayDate = 'Date invalide';

        if (!empty($rawDate) && $rawDate !== '0000-00-00 00:00:00') {
            try {
                $dateObj = new DateTime($rawDate);
                $formattedDisplayDate = $dateObj->format('d/m/Y');
            } catch (Exception $e) {
                error_log("Erreur formatage date recherche: " . $e->getMessage());
            }
        }

        // Formatage compatible avec le format existant
        $formattedResults[] = [
            'id' => $row['order_id'],
            'order_number' => $row['order_number'],
            'download_reference' => $row['download_reference'],
            'fournisseur' => $row['fournisseur'],
            'montant_total' => number_format((float)$row['montant_total'], 0, ',', ' ') . ' FCFA',
            'montant_total_raw' => (float)$row['montant_total'],
            'generated_at' => $rawDate, // Format ISO original pour le tri
            'generated_at_raw' => $rawDate, // Format brut pour le traitement JS
            'generated_at_display' => $formattedDisplayDate, // Format d'affichage
            'username' => $row['username'] ?? 'N/A',
            'finance_username' => $row['finance_username'] ?? 'N/A',
            'project_info' => $projectInfo,
            'is_multi_project' => $row['is_multi_project'],
            'signature_finance' => $row['signature_finance'],
            'user_finance_id' => $row['user_finance_id'],
            'file_path' => $row['file_path'],
            'product_found' => $row['product_found'],
            'product_quantity' => $row['product_quantity'],
            'product_unit' => $row['product_unit'],
            'product_unit_price' => number_format((float)$row['product_unit_price'], 0, ',', ' ') . ' FCFA',
            'product_unit_price_raw' => (float)$row['product_unit_price'],
            'relevance_score' => $row['relevance_score'],
            'source_table' => $row['source_table']
        ];
    }

    // ================================================================
    // STATISTIQUES POUR LE FRONTEND
    // ================================================================

    $totalResults = count($formattedResults);
    $totalAmount = array_sum(array_column($formattedResults, 'montant_total_raw'));
    $uniqueSuppliers = count(array_unique(array_column($formattedResults, 'fournisseur')));

    // ================================================================
    // RÉPONSE FINALE COMPATIBLE
    // ================================================================

    echo json_encode([
        'success' => true,
        'message' => "Recherche effectuée avec succès",
        'data' => $formattedResults,
        'total_found' => $totalResults,
        'statistics' => [
            'total_orders' => $totalResults,
            'total_amount' => $totalAmount,
            'total_amount_formatted' => number_format($totalAmount, 0, ',', ' ') . ' FCFA',
            'unique_suppliers' => $uniqueSuppliers,
            'unique_products' => count($productsList),
            'products_list' => $productsList,
            'search_term' => $searchTerm
        ],
        'pagination' => [
            'current_page' => 1,
            'total_pages' => 1,
            'per_page' => 50,
            'has_more' => false
        ],
        'timestamp' => date('Y-m-d H:i:s'),
        'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
        'server_info' => [
            'charset' => 'utf8mb4_general_ci',
            'fixed_for_ovh' => true
        ]
    ]);
    
} catch (PDOException $e) {
    // Log de l'erreur pour debugging
    error_log("Erreur de recherche par produit: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la recherche dans la base de données',
        'error_code' => 'DATABASE_ERROR',
        'debug_info' => [
            'search_term' => $searchTerm,
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'suggested_fix' => 'Problème de collation MySQL résolu avec COLLATE utf8mb4_general_ci'
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur système lors de la recherche',
        'error_code' => 'SYSTEM_ERROR',
        'debug_info' => [
            'search_term' => $searchTerm,
            'error_message' => $e->getMessage()
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>