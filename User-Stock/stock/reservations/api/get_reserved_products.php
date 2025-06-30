<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

require_once '../../../../database/connection.php';

try {
    // Forcer l'encodage UTF-8 et la collation
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("SET character_set_results = utf8mb4");
    $pdo->exec("SET collation_connection = utf8mb4_general_ci");
    
    // Requête optimisée avec gestion des collations
    $sql = "WITH reservation_data AS (
        -- Réservations depuis expression_dym
        SELECT 
            ip.id AS project_id,
            CONVERT(ip.code_projet USING utf8mb4) COLLATE utf8mb4_general_ci AS project_code,
            CONVERT(ip.nom_client USING utf8mb4) COLLATE utf8mb4_general_ci AS project_name,
            p.id AS product_id,
            CONVERT(p.barcode USING utf8mb4) COLLATE utf8mb4_general_ci AS barcode,
            CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci AS product_name,
            CONVERT(p.unit USING utf8mb4) COLLATE utf8mb4_general_ci AS unit,
            p.quantity AS total_quantity,
            ed.quantity_reserved AS reserved_quantity,
            CONVERT(COALESCE(c.libelle, 'Non catégorisé') USING utf8mb4) COLLATE utf8mb4_general_ci AS category_name,
            c.id AS category_id,
            'expression_dym' AS source_table,
            ed.created_at,
            CONCAT(CONVERT(ip.id USING utf8mb4), '_', CONVERT(p.id USING utf8mb4), '_ED') AS unique_id
        FROM expression_dym ed
        JOIN identification_projet ip ON ed.idExpression = ip.idExpression
        JOIN products p ON LOWER(CONVERT(TRIM(ed.designation) USING utf8mb4)) = LOWER(CONVERT(TRIM(p.product_name) USING utf8mb4))
        LEFT JOIN categories c ON p.category = c.id
        WHERE ed.quantity_reserved > 0
        
        UNION ALL
        
        -- Réservations depuis besoins
        SELECT 
            b.id AS project_id,
            'SYS' AS project_code,
            CONVERT(
                CASE 
                    WHEN d.client IS NOT NULL THEN CONCAT('Demande ', CONVERT(d.client USING utf8mb4))
                    ELSE CONCAT('Demande Système #', CONVERT(b.id USING utf8mb4))
                END 
                USING utf8mb4
            ) COLLATE utf8mb4_general_ci AS project_name,
            p.id AS product_id,
            CONVERT(p.barcode USING utf8mb4) COLLATE utf8mb4_general_ci AS barcode,
            CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci AS product_name,
            CONVERT(p.unit USING utf8mb4) COLLATE utf8mb4_general_ci AS unit,
            p.quantity AS total_quantity,
            b.quantity_reserved AS reserved_quantity,
            CONVERT(COALESCE(c.libelle, 'Non catégorisé') USING utf8mb4) COLLATE utf8mb4_general_ci AS category_name,
            c.id AS category_id,
            'besoins' AS source_table,
            b.created_at,
            CONCAT(CONVERT(b.id USING utf8mb4), '_', CONVERT(p.id USING utf8mb4), '_B') AS unique_id
        FROM besoins b
        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
        JOIN products p ON b.product_id = p.id
        LEFT JOIN categories c ON p.category = c.id
        WHERE b.quantity_reserved > 0
    ),
    product_totals AS (
        -- Calculer les totaux réservés par produit
        SELECT 
            product_id,
            SUM(reserved_quantity) AS total_reserved_for_product
        FROM reservation_data
        GROUP BY product_id
    )
    SELECT 
        rd.*,
        pt.total_reserved_for_product,
        GREATEST(0, rd.total_quantity - pt.total_reserved_for_product) AS available_quantity,
        CASE 
            WHEN (rd.total_quantity - pt.total_reserved_for_product) >= rd.reserved_quantity THEN 'available'
            WHEN (rd.total_quantity - pt.total_reserved_for_product) > 0 THEN 'partial'
            ELSE 'unavailable'
        END AS status
    FROM reservation_data rd
    JOIN product_totals pt ON rd.product_id = pt.product_id
    ORDER BY rd.project_code, rd.product_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Traitement minimal côté PHP
    foreach ($results as &$result) {
        $result['id'] = $result['unique_id'];
        $result['can_release'] = true;
        $result['available_quantity'] = max(0, floatval($result['available_quantity']));
        
        // Formater les dates
        if (isset($result['created_at'])) {
            $date = new DateTime($result['created_at']);
            $result['created_at'] = $date->format('d/m/Y H:i');
        }
        
        // S'assurer que tous les champs texte sont en UTF-8
        foreach (['project_code', 'project_name', 'barcode', 'product_name', 'unit', 'category_name'] as $field) {
            if (isset($result[$field])) {
                $result[$field] = mb_convert_encoding($result[$field], 'UTF-8', 'auto');
            }
        }
    }

    echo json_encode([
        'success' => true,
        'reservations' => $results,
        'total_count' => count($results)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Erreur PDO dans get_reserved_products: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Erreur générale dans get_reserved_products: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur système: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>