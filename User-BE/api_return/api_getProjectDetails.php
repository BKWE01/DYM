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

// Inclure le logger SQL si disponible
try {
    if (file_exists('../../include/sql_logger.php')) {
        include_once '../../include/sql_logger.php';
    } else if (file_exists('../../includes/sql_logger.php')) {
        include_once '../../includes/sql_logger.php';
    }
} catch (Exception $e) {
    // Ignorer si le logger n'est pas disponible
}

try {
    error_log("=== Début de l'exécution de api_getProjectDetails.php ===");

    // Récupérer le terme de recherche
    $query = isset($_GET['query']) ? $_GET['query'] : '';

    if (empty($query)) {
        echo json_encode([
            'success' => false,
            'message' => 'Veuillez fournir un ID de projet'
        ]);
        exit;
    }

    error_log("Recherche du projet avec ID: $query");

    // Récupérer la date de début du système
    $systemStartDate = getSystemStartDate();
    error_log("Date de début du système: $systemStartDate");

    // Récupérer les détails du projet
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.*, ps.status as project_status
        FROM identification_projet p
        LEFT JOIN project_status ps ON p.idExpression = ps.idExpression
        WHERE 
            p.idExpression = :exact_query 
            AND p.created_at >= :system_start_date
        LIMIT 1
    ");

    $stmt->bindParam(':exact_query', $query);
    $stmt->bindParam(':system_start_date', $systemStartDate);
    $stmt->execute();

    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        error_log("Projet non trouvé: $query");
        echo json_encode([
            'success' => false,
            'message' => 'Projet non trouvé ou antérieur à la date système (' . date('d/m/Y', strtotime($systemStartDate)) . ')'
        ]);
        exit;
    }

    error_log("Projet trouvé: " . $project['idExpression'] . " - " . $project['nom_client']);

    // Si le projet est marqué comme terminé, renvoyer une réponse indiquant qu'il n'y a pas de produits à retourner
    if (isset($project['project_status']) && $project['project_status'] === 'completed') {
        error_log("Projet marqué comme terminé, pas de produits à retourner");
        echo json_encode([
            'success' => true,
            'project' => $project,
            'project_completed' => true,
            'reservedProducts' => []
        ]);
        exit;
    }

    // CORRECTION: Récupérer les produits réservés pour ce projet
    // Modification pour améliorer la détection des produits disponibles pour le retour
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
            p.quantity as product_quantity,
            p.quantity_reserved as product_quantity_reserved,
            (
                SELECT COALESCE(SUM(sm.quantity), 0) 
                FROM stock_movement sm 
                WHERE sm.movement_type = 'output' 
                AND sm.nom_projet = :nom_client
                AND sm.product_id = p.id
                AND sm.date >= :system_start_date
            ) as quantity_output
        FROM expression_dym e
        LEFT JOIN products p ON LOWER(e.designation) = LOWER(p.product_name)
        WHERE e.idExpression = :idExpression
    ");

    $stmt->bindParam(':idExpression', $project['idExpression']);
    $stmt->bindParam(':nom_client', $project['nom_client']);
    $stmt->bindParam(':system_start_date', $systemStartDate);
    $stmt->execute();

    $reservedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Nombre de produits réservés trouvés: " . count($reservedProducts));

    // Pour chaque produit, récupérer les mouvements d'ajustement et de retour
    foreach ($reservedProducts as &$product) {
        if (!isset($product['product_id']) || empty($product['product_id'])) {
            continue;
        }

        // 1. Récupérer les quantités retournées (mouvements d'ajustement et entrées liés aux retours)
        $returnStmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) as total_returned
            FROM stock_movement
            WHERE product_id = :product_id
            AND (
                (movement_type = 'adjustment' AND provenance LIKE '%Retour%' AND provenance LIKE :nom_client_pattern)
                OR
                (movement_type = 'input' AND provenance LIKE '%Retour%' AND provenance LIKE :nom_client_pattern)
            )
            AND date >= :system_start_date
        ");
        
        $nom_client_pattern = '%' . $project['nom_client'] . '%';
        $returnStmt->bindParam(':product_id', $product['product_id']);
        $returnStmt->bindParam(':nom_client_pattern', $nom_client_pattern);
        $returnStmt->bindParam(':system_start_date', $systemStartDate);
        $returnStmt->execute();
        
        $returnData = $returnStmt->fetch(PDO::FETCH_ASSOC);
        $product['quantity_returned'] = floatval($returnData['total_returned']);
        
        // 2. Récupérer les données détaillées des sorties pour confirmer la quantité utilisée
        $outputStmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) as total_output, 
                  COUNT(*) as output_count
            FROM stock_movement
            WHERE product_id = :product_id
            AND movement_type = 'output'
            AND nom_projet = :nom_client
            AND date >= :system_start_date
        ");
        
        $outputStmt->bindParam(':product_id', $product['product_id']);
        $outputStmt->bindParam(':nom_client', $project['nom_client']);
        $outputStmt->bindParam(':system_start_date', $systemStartDate);
        $outputStmt->execute();
        
        $outputData = $outputStmt->fetch(PDO::FETCH_ASSOC);
        $product['quantity_output'] = floatval($outputData['total_output']);
        
        error_log("Produit ID: " . $product['product_id'] . ", Nom: " . $product['designation'] . 
                 ", Sorties: " . $product['quantity_output'] . 
                 ", Retours: " . $product['quantity_returned']);
    }

    // Calculer les quantités utilisées nettes et filtrer les produits
    $filteredProducts = array_filter(array_map(function ($product) {
        // Convertir en nombres flottants
        $reserved = floatval($product['quantity'] ?? 0);
        $quantity_reserved = floatval($product['quantity_reserved'] ?? 0);
        $output = floatval($product['quantity_output'] ?? 0);
        $returned = floatval($product['quantity_returned'] ?? 0);

        // Utiliser quantity_reserved en priorité si disponible
        if ($quantity_reserved > 0) {
            $reserved = $quantity_reserved;
        }

        // Calculer la quantité utilisée nette (sorties - retours)
        $netUsed = max(0, $output); // Conserver toute la quantité sortie comme "utilisée"
        
        // Ajouter cette valeur calculée au produit
        $product['quantity_used'] = $netUsed;

        // Calculer la quantité restante disponible
        $product['remaining'] = max(0, $reserved - $netUsed);

        // Log pour le débogage
        error_log("Produit: " . $product['designation'] . 
                 ", Reserved: $reserved, Output: $output, " . 
                 "Returned: $returned, Net Used: $netUsed, " . 
                 "Remaining: " . $product['remaining']);

        return $product;
    }, $reservedProducts), function ($product) {
        // CORRECTION: Inclure tous les produits liés au projet même si leur ID n'est pas trouvé
        $hasReserved = isset($product['quantity']) && floatval($product['quantity']) > 0;
        $hasProduct = isset($product['product_id']) && $product['product_id'] > 0;

        // S'il s'agit d'un retour de produit non utilisé, on veut voir tous les produits avec une quantité restante
        if ($hasReserved && floatval($product['remaining']) > 0) {
            return true;
        }

        // S'il s'agit d'un retour partiel, on veut voir tous les produits qui ont été sortis
        if ($hasProduct && floatval($product['quantity_output']) > 0) {
            return true;
        }

        return false;
    });

    error_log("Nombre de produits filtrés disponibles pour le retour: " . count($filteredProducts));

    // AJOUT: Vérifier si aucun produit n'a de quantité_reserved définie, et si oui, l'initialiser
    $needsInitialization = false;
    foreach ($filteredProducts as $product) {
        if (!isset($product['quantity_reserved']) || $product['quantity_reserved'] === null) {
            $needsInitialization = true;
            break;
        }
    }

    // Si l'initialisation est nécessaire, exécuter la procédure stockée
    if ($needsInitialization) {
        error_log("Initialisation des quantités réservées requise");
        try {
            $stmt = $pdo->prepare("CALL initialize_reserved_quantities()");
            $stmt->execute();
            error_log("Procédure d'initialisation exécutée avec succès");

            // Récupérer à nouveau les produits avec les quantités réservées mises à jour
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
                    p.quantity as product_quantity,
                    p.quantity_reserved as product_quantity_reserved,
                    (
                        SELECT COALESCE(SUM(sm.quantity), 0) 
                        FROM stock_movement sm 
                        WHERE sm.movement_type = 'output' 
                        AND sm.nom_projet = :nom_client
                        AND sm.product_id = p.id
                        AND sm.date >= :system_start_date
                    ) as quantity_output
                FROM expression_dym e
                LEFT JOIN products p ON LOWER(e.designation) = LOWER(p.product_name)
                WHERE e.idExpression = :idExpression
            ");
            
            $stmt->bindParam(':idExpression', $project['idExpression']);
            $stmt->bindParam(':nom_client', $project['nom_client']);
            $stmt->bindParam(':system_start_date', $systemStartDate);
            $stmt->execute();

            $reservedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Nombre de produits réservés après initialisation: " . count($reservedProducts));

            // Récupérer à nouveau les quantités retournées et les sorties détaillées
            foreach ($reservedProducts as &$product) {
                if (!isset($product['product_id']) || empty($product['product_id'])) {
                    continue;
                }

                // Récupérer les retours
                $returnStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(quantity), 0) as total_returned
                    FROM stock_movement
                    WHERE product_id = :product_id
                    AND (
                        (movement_type = 'adjustment' AND provenance LIKE '%Retour%' AND provenance LIKE :nom_client_pattern)
                        OR
                        (movement_type = 'input' AND provenance LIKE '%Retour%' AND provenance LIKE :nom_client_pattern)
                    )
                    AND date >= :system_start_date
                ");
                
                $nom_client_pattern = '%' . $project['nom_client'] . '%';
                $returnStmt->bindParam(':product_id', $product['product_id']);
                $returnStmt->bindParam(':nom_client_pattern', $nom_client_pattern);
                $returnStmt->bindParam(':system_start_date', $systemStartDate);
                $returnStmt->execute();
                
                $returnData = $returnStmt->fetch(PDO::FETCH_ASSOC);
                $product['quantity_returned'] = floatval($returnData['total_returned']);
                
                // Récupérer les sorties
                $outputStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(quantity), 0) as total_output, 
                          COUNT(*) as output_count
                    FROM stock_movement
                    WHERE product_id = :product_id
                    AND movement_type = 'output'
                    AND nom_projet = :nom_client
                    AND date >= :system_start_date
                ");
                
                $outputStmt->bindParam(':product_id', $product['product_id']);
                $outputStmt->bindParam(':nom_client', $project['nom_client']);
                $outputStmt->bindParam(':system_start_date', $systemStartDate);
                $outputStmt->execute();
                
                $outputData = $outputStmt->fetch(PDO::FETCH_ASSOC);
                $product['quantity_output'] = floatval($outputData['total_output']);
            }

            // Recalculer les quantités
            $filteredProducts = array_filter(array_map(function ($product) {
                // Convertir en nombres flottants
                $reserved = floatval($product['quantity'] ?? 0);
                $quantity_reserved = floatval($product['quantity_reserved'] ?? 0);
                $output = floatval($product['quantity_output'] ?? 0);
                $returned = floatval($product['quantity_returned'] ?? 0);

                // Utiliser quantity_reserved en priorité si disponible
                if ($quantity_reserved > 0) {
                    $reserved = $quantity_reserved;
                }

                // Calculer la quantité utilisée nette (sorties totales)
                $netUsed = max(0, $output);

                // Ajouter cette valeur calculée au produit
                $product['quantity_used'] = $netUsed;

                // Calculer la quantité restante disponible
                $product['remaining'] = max(0, $reserved - $netUsed);

                return $product;
            }, $reservedProducts), function ($product) {
                $hasReserved = isset($product['quantity']) && floatval($product['quantity']) > 0;
                $hasProduct = isset($product['product_id']) && $product['product_id'] > 0;

                // S'il s'agit d'un retour de produit non utilisé, on veut voir tous les produits avec une quantité restante
                if ($hasReserved && floatval($product['remaining']) > 0) {
                    return true;
                }

                // S'il s'agit d'un retour partiel, on veut voir tous les produits qui ont été sortis
                if ($hasProduct && floatval($product['quantity_output']) > 0) {
                    return true;
                }

                return false;
            });

            error_log("Nombre de produits filtrés après initialisation: " . count($filteredProducts));
        } catch (PDOException $e) {
            error_log("Erreur lors de l'initialisation des quantités réservées: " . $e->getMessage());
            // Continuer avec les produits déjà récupérés
        }
    }

    echo json_encode([
        'success' => true,
        'project' => $project,
        'project_completed' => false,
        'reservedProducts' => array_values($filteredProducts), // array_values pour réindexer le tableau
        'system_start_date' => $systemStartDate // Inclure la date de début pour information
    ]);

    error_log("=== Fin de l'exécution de api_getProjectDetails.php ===");

} catch (PDOException $e) {
    error_log("PDOException dans api_getProjectDetails.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Exception dans api_getProjectDetails.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>