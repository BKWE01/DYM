<?php
header('Content-Type: application/json');

// Désactiver l'affichage des erreurs pour éviter de corrompre la sortie JSON
ini_set('display_errors', 0);
error_reporting(0);

// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure le helper de date
try {
    if (file_exists('../../include/date_helper.php')) {
        include_once '../../include/date_helper.php';
    } else if (file_exists('../../includes/date_helper.php')) {
        include_once '../../includes/date_helper.php';
    } else {
        // Définir une fonction de secours si le fichier n'existe pas
        function getSystemStartDate()
        {
            return '2025-03-24'; // Valeur par défaut
        }
    }
} catch (Exception $e) {
    // Définir une fonction de secours en cas d'erreur
    function getSystemStartDate()
    {
        return '2025-03-24'; // Valeur par défaut
    }
}

try {
    // Récupérer l'ID du projet
    $projectId = isset($_GET['project_id']) ? $_GET['project_id'] : '';

    if (empty($projectId)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de projet manquant'
        ]);
        exit;
    }

    // Récupérer la date de début du système
    $systemStartDate = getSystemStartDate();

    // Récupérer les détails du projet pour obtenir le nom du client
    $stmtProject = $pdo->prepare("SELECT nom_client FROM identification_projet WHERE idExpression = :idExpression");
    $stmtProject->bindParam(':idExpression', $projectId);
    $stmtProject->execute();
    $project = $stmtProject->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        echo json_encode([
            'success' => false,
            'message' => 'Projet non trouvé'
        ]);
        exit;
    }

    $nomClient = $project['nom_client'];

    // Récupérer les produits du projet
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.idExpression,
            e.designation,
            e.unit,
            e.quantity,
            e.quantity_reserved,
            e.qt_stock,
            e.qt_acheter,
            e.type,
            p.id as product_id,
            p.product_name,
            
            (
                SELECT COALESCE(SUM(sm.quantity), 0) 
                FROM stock_movement sm 
                WHERE sm.movement_type = 'output' 
                AND sm.nom_projet = :nom_client
                AND sm.product_id = p.id
                AND sm.date >= :system_start_date
            ) as quantity_used,
            
            (
                SELECT COALESCE(SUM(sm.quantity), 0) 
                FROM stock_movement sm 
                WHERE (
                    (sm.movement_type = 'adjustment' AND sm.provenance LIKE '%Retour%' AND sm.provenance LIKE :nom_client_pattern)
                    OR 
                    (sm.movement_type = 'input' AND sm.provenance LIKE '%Retour%' AND sm.provenance LIKE :nom_client_pattern)
                )
                AND sm.product_id = p.id
                AND sm.date >= :system_start_date
            ) as quantity_returned
            
        FROM expression_dym e
        LEFT JOIN products p ON LOWER(e.designation) = LOWER(p.product_name)
        WHERE e.idExpression = :idExpression
        ORDER BY e.designation
    ");

    $nom_client_pattern = '%' . $nomClient . '%';
    $stmt->bindParam(':idExpression', $projectId);
    $stmt->bindParam(':nom_client', $nomClient);
    $stmt->bindParam(':nom_client_pattern', $nom_client_pattern);
    $stmt->bindParam(':system_start_date', $systemStartDate);
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($products) > 0) {
        echo json_encode([
            'success' => true,
            'products' => $products,
            'system_start_date' => $systemStartDate
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'products' => [],
            'message' => 'Aucun produit trouvé pour ce projet.'
        ]);
    }

} catch (PDOException $e) {
    error_log("API getProjectProducts - PDOException: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("API getProjectProducts - Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>