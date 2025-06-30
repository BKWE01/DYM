<?php
header('Content-Type: application/json');

// Désactiver l'affichage des erreurs pour éviter de corrompre la sortie JSON
ini_set('display_errors', 0);
error_reporting(0);

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure le helper de date
try {
    if (file_exists('../../include/date_helper.php')) {
        include_once '../../include/date_helper.php';
    } else if (file_exists('../../includes/date_helper.php')) {
        include_once '../../includes/date_helper.php';
    } else {
        function getSystemStartDate()
        {
            return '2025-03-24';
        }
    }
} catch (Exception $e) {
    function getSystemStartDate()
    {
        return '2025-03-24';
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

    // Récupérer la zone active de l'utilisateur
    $activeZoneId = isset($_SESSION['active_zone_id']) ? intval($_SESSION['active_zone_id']) : null;

    // Si aucune zone n'est définie, utiliser la zone par défaut
    if (!$activeZoneId) {
        $activeZoneId = 1; // Zone par défaut
    }

    // Récupérer la date de début du système
    $systemStartDate = getSystemStartDate();

    // Récupérer les informations complètes du projet
    $stmt = $pdo->prepare("
        SELECT idExpression, code_projet, nom_client 
        FROM identification_projet 
        WHERE idExpression = :idExpression
    ");
    $stmt->bindParam(':idExpression', $projectId);
    $stmt->execute();
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        echo json_encode([
            'success' => false,
            'message' => 'Projet non trouvé'
        ]);
        exit;
    }

    // Extraire les identifiants du projet
    $idExpression = $project['idExpression'];
    $codeProjet = $project['code_projet'];
    $nomClient = $project['nom_client'];

    // CORRECTION : Requête avec gestion explicite des collations
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            sm.id,
            sm.product_id,
            sm.quantity,
            sm.movement_type,
            CONVERT(sm.provenance USING utf8mb4) COLLATE utf8mb4_general_ci as provenance,
            CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci as nom_projet,
            CONVERT(sm.destination USING utf8mb4) COLLATE utf8mb4_general_ci as destination,
            CONVERT(sm.demandeur USING utf8mb4) COLLATE utf8mb4_general_ci as demandeur,
            CONVERT(sm.fournisseur USING utf8mb4) COLLATE utf8mb4_general_ci as fournisseur,
            CONVERT(sm.notes USING utf8mb4) COLLATE utf8mb4_general_ci as notes,
            sm.date,
            sm.created_at,
            sm.zone_id,
            CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci as product_name,
            p.barcode,
            p.unit,
            CONVERT(c.libelle USING utf8mb4) COLLATE utf8mb4_general_ci as category,
            CONVERT(u.name USING utf8mb4) COLLATE utf8mb4_general_ci as user_name,
            u.user_type,
            CONVERT(z.nom USING utf8mb4) COLLATE utf8mb4_general_ci as zone_name,
            z.code as zone_code
        FROM stock_movement sm
        LEFT JOIN products p ON sm.product_id = p.id
        LEFT JOIN categories c ON p.category = c.id
        LEFT JOIN users_exp u ON CONVERT(sm.demandeur USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.name USING utf8mb4) COLLATE utf8mb4_general_ci
        LEFT JOIN zones z ON sm.zone_id = z.id
        WHERE (
            CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci = :nom_client 
            OR CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci = :code_projet 
            OR CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci = :id_expression
            OR CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :nom_client_like
            OR CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :code_projet_like
        )
        AND sm.date >= :system_start_date
        AND (sm.zone_id = :zone_id OR sm.zone_id IS NULL)
        ORDER BY sm.date DESC, sm.id DESC
    ");

    // Lier les paramètres
    $stmt->bindParam(':nom_client', $nomClient);
    $stmt->bindParam(':code_projet', $codeProjet);
    $stmt->bindParam(':id_expression', $idExpression);
    $nomClientLike = '%' . $nomClient . '%';
    $codeProjetLike = '%' . $codeProjet . '%';
    $stmt->bindParam(':nom_client_like', $nomClientLike);
    $stmt->bindParam(':code_projet_like', $codeProjetLike);
    $stmt->bindParam(':system_start_date', $systemStartDate);
    $stmt->bindParam(':zone_id', $activeZoneId, PDO::PARAM_INT);

    $stmt->execute();
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // AMÉLIORATION : Inclure les mouvements de dispatching si la table existe
    $dispatchMovements = [];
    try {
        $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'dispatch_details'");
        if ($checkTableStmt->rowCount() > 0) {
            $dispatchStmt = $pdo->prepare("
                SELECT DISTINCT
                    dd.movement_id as id,
                    dd.product_id,
                    dd.allocated as quantity,
                    'dispatch' as movement_type,
                    CONCAT(CONVERT(sm.provenance USING utf8mb4) COLLATE utf8mb4_general_ci) as provenance,
                    CONVERT(dd.project USING utf8mb4) COLLATE utf8mb4_general_ci as nom_projet,
                    CONVERT(dd.client USING utf8mb4) COLLATE utf8mb4_general_ci as destination,
                    'Système' as demandeur,
                    CONVERT(dd.fournisseur USING utf8mb4) COLLATE utf8mb4_general_ci as fournisseur,
                    CONVERT(dd.notes USING utf8mb4) COLLATE utf8mb4_general_ci as notes,
                    dd.dispatch_date as date,
                    dd.created_at,
                    sm.zone_id,
                    CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci as product_name,
                    p.barcode,
                    p.unit,
                    CONVERT(c.libelle USING utf8mb4) COLLATE utf8mb4_general_ci as category,
                    'Système' as user_name,
                    'Système' as user_type,
                    CONVERT(z.nom USING utf8mb4) COLLATE utf8mb4_general_ci as zone_name,
                    z.code as zone_code
                FROM dispatch_details dd
                LEFT JOIN stock_movement sm ON dd.movement_id = sm.id
                LEFT JOIN products p ON dd.product_id = p.id
                LEFT JOIN categories c ON p.category = c.id
                LEFT JOIN zones z ON sm.zone_id = z.id
                WHERE (
                    CONVERT(dd.client USING utf8mb4) COLLATE utf8mb4_general_ci = :nom_client 
                    OR CONVERT(dd.client USING utf8mb4) COLLATE utf8mb4_general_ci = :code_projet 
                    OR CONVERT(dd.project USING utf8mb4) COLLATE utf8mb4_general_ci = :code_projet
                    OR CONVERT(dd.client USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :nom_client_like
                    OR CONVERT(dd.project USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :code_projet_like
                )
                AND dd.dispatch_date >= :system_start_date
                AND (sm.zone_id = :zone_id OR sm.zone_id IS NULL)
                ORDER BY dd.dispatch_date DESC
            ");

            $dispatchStmt->bindParam(':nom_client', $nomClient);
            $dispatchStmt->bindParam(':code_projet', $codeProjet);
            $dispatchStmt->bindParam(':nom_client_like', $nomClientLike);
            $dispatchStmt->bindParam(':code_projet_like', $codeProjetLike);
            $dispatchStmt->bindParam(':system_start_date', $systemStartDate);
            $dispatchStmt->bindParam(':zone_id', $activeZoneId, PDO::PARAM_INT);

            $dispatchStmt->execute();
            $dispatchMovements = $dispatchStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Ignorer si la table dispatch_details n'existe pas
        error_log("Table dispatch_details non disponible: " . $e->getMessage());
    }

    // Fusionner tous les mouvements
    $allMovements = array_merge($movements, $dispatchMovements);

    // Trier par date décroissante
    usort($allMovements, function ($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // Traiter et enrichir les données
    $processedMovements = [];
    $stats = [
        'inputs' => 0,
        'outputs' => 0,
        'adjustments' => 0,
        'returns' => 0,
        'dispatches' => 0, // Compteur séparé pour info
        'total_quantity_in' => 0,
        'total_quantity_out' => 0
    ];

    foreach ($allMovements as $movement) {
        // Standardiser les types de mouvements
        if ($movement['movement_type'] === 'entry') {
            $movement['movement_type'] = 'input';
        }

        // Enrichir avec des informations calculées
        $movement['formatted_date'] = date('d/m/Y H:i', strtotime($movement['date']));
        $movement['quantity_display'] = number_format($movement['quantity'], 2, ',', ' ');

        // Déterminer la direction du mouvement
        $isIncoming = in_array($movement['movement_type'], ['input', 'entry', 'dispatch']); // ✅ DISPATCH = ENTRÉE
        $isOutgoing = in_array($movement['movement_type'], ['output']);
        $isAdjustment = in_array($movement['movement_type'], ['adjustment']);
        $isReturn = in_array($movement['movement_type'], ['return']);
        $isDispatch = ($movement['movement_type'] === 'dispatch');

        // Calculer les statistiques
        if ($isDispatch) {
            // ✅ Les dispatches sont des entrées
            $stats['inputs']++;
            $stats['dispatches']++; // Compteur séparé pour info
            $stats['total_quantity_in'] += floatval($movement['quantity']);

            // ✅ Marquer comme entrée pour l'affichage
            $movement['display_type'] = 'input';
            $movement['movement_direction'] = 'in';
        } elseif ($isIncoming) {
            $stats['inputs']++;
            $stats['total_quantity_in'] += floatval($movement['quantity']);
            $movement['display_type'] = 'input';
            $movement['movement_direction'] = 'in';
        } elseif ($isOutgoing) {
            $stats['outputs']++;
            $stats['total_quantity_out'] += floatval($movement['quantity']);
            $movement['display_type'] = 'output';
            $movement['movement_direction'] = 'out';
        } elseif ($isAdjustment) {
            $stats['adjustments']++;
            $movement['display_type'] = 'adjustment';
            $movement['movement_direction'] = 'neutral';
        } elseif ($isReturn) {
            $stats['returns']++;
            $movement['display_type'] = 'return';
            $movement['movement_direction'] = 'neutral';
        }

        // Améliorer l'affichage du demandeur
        if (empty($movement['user_name']) && !empty($movement['demandeur'])) {
            $movement['user_name'] = $movement['demandeur'];
            $movement['user_type'] = $movement['user_type'] ?: 'N/A';
        }

        // Nettoyer les données NULL
        foreach ($movement as $key => $value) {
            if ($value === null) {
                $movement[$key] = '';
            }
        }

        $processedMovements[] = $movement;
    }

    // Calculer le solde net
    $stats['net_balance'] = $stats['total_quantity_in'] - $stats['total_quantity_out'];

    // Informations de debug si demandées
    $debugInfo = [];
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        $debugInfo = [
            'project_identifiers' => [
                'idExpression' => $idExpression,
                'code_projet' => $codeProjet,
                'nom_client' => $nomClient
            ],
            'search_criteria' => [
                'system_start_date' => $systemStartDate,
                'active_zone_id' => $activeZoneId,
                'total_movements_found' => count($processedMovements),
                'stock_movements' => count($movements),
                'dispatch_movements' => count($dispatchMovements)
            ]
        ];
    }

    echo json_encode([
        'success' => true,
        'movements' => $processedMovements,
        'stats' => $stats,
        'project' => $project,
        'system_start_date' => $systemStartDate,
        'active_zone' => [
            'id' => $activeZoneId,
            'total_movements' => count($processedMovements)
        ],
        'debug' => $debugInfo
    ]);
} catch (PDOException $e) {
    error_log("Erreur PDO dans api_getProjectHistory.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Erreur dans api_getProjectHistory.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
