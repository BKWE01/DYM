<?php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non connecté'
    ]);
    exit;
}

// Vérifier si l'ID du projet est fourni
if (!isset($_GET['project_id']) || empty($_GET['project_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID du projet non fourni'
    ]);
    exit;
}

$projectId = intval($_GET['project_id']);

require_once '../../../../database/connection.php';

try {
    // Forcer l'encodage UTF-8 pour la connexion
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
    
    // Variables pour déterminer le type de projet
    $isSystemProject = false;
    $project = null;
    
    // 1. Essayer de récupérer les détails du projet depuis identification_projet
    $sqlProject = "SELECT 
                        id,
                        CONVERT(code_projet USING utf8mb4) COLLATE utf8mb4_general_ci AS code_projet,
                        CONVERT(nom_client USING utf8mb4) COLLATE utf8mb4_general_ci AS nom_client,
                        CONVERT(description_projet USING utf8mb4) COLLATE utf8mb4_general_ci AS description_projet,
                        CONVERT(sitgeo USING utf8mb4) COLLATE utf8mb4_general_ci AS sitgeo,
                        CONVERT(chefprojet USING utf8mb4) COLLATE utf8mb4_general_ci AS chefprojet,
                        created_at,
                        updated_at
                   FROM identification_projet 
                   WHERE id = :id";
    
    $stmtProject = $pdo->prepare($sqlProject);
    $stmtProject->execute(['id' => $projectId]);
    $project = $stmtProject->fetch(PDO::FETCH_ASSOC);

    // Si le projet n'est pas trouvé, essayer de le récupérer depuis besoins
    if (!$project) {
        $sqlBesoin = "SELECT 
                        b.id,
                        'SYS' AS code_projet,
                        CONVERT(
                            CASE 
                                WHEN d.client IS NOT NULL THEN CONCAT('Demande ', CONVERT(d.client USING utf8mb4))
                                ELSE CONCAT('Demande Système #', CONVERT(b.id USING utf8mb4))
                            END 
                            USING utf8mb4
                        ) COLLATE utf8mb4_general_ci AS nom_client,
                        'Demande système' AS description_projet,
                        '' AS sitgeo,
                        'Admin Système' AS chefprojet,
                        b.created_at,
                        b.updated_at
                      FROM besoins b
                      LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                      WHERE b.id = :id";
        
        $stmtBesoin = $pdo->prepare($sqlBesoin);
        $stmtBesoin->execute(['id' => $projectId]);
        $project = $stmtBesoin->fetch(PDO::FETCH_ASSOC);
        
        if ($project) {
            $isSystemProject = true;
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Projet non trouvé'
            ]);
            exit;
        }
    }

    // 2. Récupérer les produits réservés pour ce projet
    if ($isSystemProject) {
        // Pour un besoin système
        $sqlProducts = "SELECT 
                            CONVERT(b.designation_article USING utf8mb4) COLLATE utf8mb4_general_ci AS product_name,
                            CONVERT(p.barcode USING utf8mb4) COLLATE utf8mb4_general_ci AS barcode,
                            CONVERT(p.unit USING utf8mb4) COLLATE utf8mb4_general_ci AS unit,
                            b.quantity_reserved AS reserved_quantity,
                            p.quantity AS total_quantity,
                            CONVERT(COALESCE(c.libelle, 'Non catégorisé') USING utf8mb4) COLLATE utf8mb4_general_ci AS category_name
                        FROM 
                            besoins b
                        JOIN 
                            products p ON b.product_id = p.id
                        LEFT JOIN 
                            categories c ON p.category = c.id
                        WHERE 
                            b.id = :project_id
                            AND b.quantity_reserved > 0
                        ORDER BY 
                            b.designation_article";
    } else {
        // Pour un projet normal
        $sqlProducts = "SELECT 
                            CONVERT(ed.designation USING utf8mb4) COLLATE utf8mb4_general_ci AS product_name,
                            CONVERT(p.barcode USING utf8mb4) COLLATE utf8mb4_general_ci AS barcode,
                            CONVERT(p.unit USING utf8mb4) COLLATE utf8mb4_general_ci AS unit,
                            ed.quantity_reserved AS reserved_quantity,
                            p.quantity AS total_quantity,
                            CONVERT(COALESCE(c.libelle, 'Non catégorisé') USING utf8mb4) COLLATE utf8mb4_general_ci AS category_name
                        FROM 
                            expression_dym ed
                        JOIN 
                            identification_projet ip ON ed.idExpression = ip.idExpression
                        JOIN 
                            products p ON LOWER(CONVERT(TRIM(ed.designation) USING utf8mb4)) = LOWER(CONVERT(TRIM(p.product_name) USING utf8mb4))
                        LEFT JOIN 
                            categories c ON p.category = c.id
                        WHERE 
                            ip.id = :project_id
                            AND ed.quantity_reserved > 0
                        ORDER BY 
                            ed.designation";
    }

    $stmtProducts = $pdo->prepare($sqlProducts);
    $stmtProducts->execute(['project_id' => $projectId]);
    $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

    // 3. Pour chaque produit, calculer le statut de disponibilité
    foreach ($products as &$product) {
        // Récupérer le total réservé pour ce produit (des deux tables)
        $sqlTotalReserved = "
            SELECT 
                (
                    SELECT COALESCE(SUM(ed.quantity_reserved), 0)
                    FROM expression_dym ed
                    JOIN products p ON LOWER(CONVERT(TRIM(ed.designation) USING utf8mb4)) = LOWER(CONVERT(TRIM(p.product_name) USING utf8mb4))
                    WHERE LOWER(CONVERT(TRIM(p.product_name) USING utf8mb4)) = LOWER(CONVERT(TRIM(:product_name) USING utf8mb4))
                ) +
                (
                    SELECT COALESCE(SUM(b.quantity_reserved), 0)
                    FROM besoins b
                    JOIN products p ON b.product_id = p.id
                    WHERE LOWER(CONVERT(TRIM(p.product_name) USING utf8mb4)) = LOWER(CONVERT(TRIM(:product_name) USING utf8mb4))
                ) AS total_reserved";
        
        $stmtTotalReserved = $pdo->prepare($sqlTotalReserved);
        $stmtTotalReserved->execute(['product_name' => $product['product_name']]);
        $totalReservedResult = $stmtTotalReserved->fetch(PDO::FETCH_ASSOC);
        $totalReserved = floatval($totalReservedResult['total_reserved'] ?? 0);

        $availableQuantity = $product['total_quantity'] - $totalReserved;
        $availableQuantity = max(0, $availableQuantity);
        
        // Déterminer le statut
        $status = 'unavailable';
        if ($availableQuantity >= $product['reserved_quantity']) {
            $status = 'available';
        } else if ($availableQuantity > 0) {
            $status = 'partial';
        }
        
        $product['available_quantity'] = $availableQuantity;
        $product['status'] = $status;
    }

    echo json_encode([
        'success' => true,
        'project' => $project,
        'products' => $products,
        'is_system_project' => $isSystemProject
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Erreur PDO dans get_project_details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Erreur générale dans get_project_details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur système: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>