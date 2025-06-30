<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

require_once '../../../../database/connection.php';

try {
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
    
    // Requête optimisée unique
    $sql = "SELECT 
                project_id AS id,
                project_code AS code_projet,
                project_name AS nom_client,
                source_type,
                SUM(total_reserved) AS total_reserved
            FROM (
                -- Projets normaux
                SELECT 
                    ip.id AS project_id,
                    ip.code_projet AS project_code,
                    ip.nom_client AS project_name,
                    'project' AS source_type,
                    SUM(ed.quantity_reserved) AS total_reserved
                FROM identification_projet ip
                JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                WHERE ed.quantity_reserved > 0
                GROUP BY ip.id, ip.code_projet, ip.nom_client
                
                UNION ALL
                
                -- Demandes système
                SELECT 
                    b.id AS project_id,
                    'SYS' AS project_code,
                    CASE 
                        WHEN d.client IS NOT NULL THEN CONCAT('Demande ', d.client)
                        ELSE CONCAT('Demande Système #', b.id)
                    END AS project_name,
                    'besoin' AS source_type,
                    SUM(b.quantity_reserved) AS total_reserved
                FROM besoins b
                LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                WHERE b.quantity_reserved > 0
                GROUP BY b.id, d.client
            ) AS combined_projects
            GROUP BY project_id, project_code, project_name, source_type
            HAVING SUM(total_reserved) > 0
            ORDER BY project_code";
                    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'projects' => $projects,
        'total_count' => count($projects)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Erreur PDO dans get_projects: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>