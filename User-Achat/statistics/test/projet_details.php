<?php

/**
 * Module de détails des projets groupés par code projet
 * Affiche les détails d'un groupe de projets partageant le même code
 * 
 * @package DYM_MANUFACTURE
 * @subpackage expressions_besoins/User-Achat/statistics
 * @version 2.2.0
 * @author Équipe DYM
 */

session_start();

// Désactiver la mise en cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../index.php");
    exit();
}

// Connexion à la base de données
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Vérifier si un code projet est fourni
if (!isset($_GET['code_projet']) || empty($_GET['code_projet'])) {
    header("Location: stats_projets.php");
    exit();
}

$codeProjet = $_GET['code_projet'];

// Fonction pour formater les nombres
function formatNumber($number)
{
    return number_format($number, 0, ',', ' ');
}

try {
    // Récupérer les informations du groupe de projets (REGROUPÉES PAR CODE)
    $projectInfoQuery = "SELECT 
                        ip.code_projet,
                        GROUP_CONCAT(DISTINCT ip.nom_client ORDER BY ip.nom_client SEPARATOR ', ') as clients_list,
                        COUNT(DISTINCT ip.id) as project_count,
                        GROUP_CONCAT(DISTINCT ip.description_projet ORDER BY ip.created_at SEPARATOR ' | ') as descriptions_combined,
                        GROUP_CONCAT(DISTINCT ip.sitgeo ORDER BY ip.created_at SEPARATOR ', ') as locations_combined,
                        GROUP_CONCAT(DISTINCT ip.chefprojet ORDER BY ip.created_at SEPARATOR ', ') as project_managers,
                        MIN(ip.created_at) as earliest_creation,
                        MAX(ip.created_at) as latest_creation,
                        GROUP_CONCAT(DISTINCT ip.idExpression ORDER BY ip.created_at SEPARATOR ', ') as expression_ids
                       FROM identification_projet ip
                       WHERE ip.code_projet = :code_projet
                       AND ip.created_at >= '" . getSystemStartDate() . "'
                       GROUP BY ip.code_projet";

    $projectInfoStmt = $pdo->prepare($projectInfoQuery);
    $projectInfoStmt->bindParam(':code_projet', $codeProjet);
    $projectInfoStmt->execute();
    $projectInfo = $projectInfoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$projectInfo) {
        // Si le groupe de projets n'existe pas, rediriger vers la liste des projets
        header("Location: stats_projets.php");
        exit();
    }

    // Récupérer les matériaux du groupe de projets (AGRÉGÉS)
    $materialsQuery = "SELECT 
                       ed.*,
                       ip.nom_client,
                       ip.idExpression,
                       p.unit_price as catalog_price,
                       p.quantity as stock_available,
                       c.libelle as category,
                       (SELECT COUNT(*) FROM achats_materiaux am WHERE am.expression_id = ip.idExpression AND am.designation = ed.designation) as order_count,
                       (SELECT MAX(am.date_reception) FROM achats_materiaux am WHERE am.expression_id = ip.idExpression AND am.designation = ed.designation) as last_receipt_date
                      FROM identification_projet ip
                      JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                      LEFT JOIN products p ON ed.designation = p.product_name
                      LEFT JOIN categories c ON p.category = c.id
                      WHERE ip.code_projet = :code_projet
                      AND ip.created_at >= '" . getSystemStartDate() . "'
                      ORDER BY ip.nom_client ASC, ed.valide_achat ASC, ed.created_at DESC";

    $materialsStmt = $pdo->prepare($materialsQuery);
    $materialsStmt->bindParam(':code_projet', $codeProjet);
    $materialsStmt->execute();
    $projectMaterials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtenir des statistiques sur les matériaux du groupe de projets (AGRÉGÉES)
    $materialStats = [
        'total_items' => count($projectMaterials),
        'total_amount' => 0,
        'pending_items' => 0,
        'ordered_items' => 0,
        'received_items' => 0,
        'canceled_items' => 0,
        'consumed_items' => 0, // NOUVEAU : matériaux consommés depuis le stock
        'total_pending' => 0,
        'total_ordered' => 0,
        'total_received' => 0,
        'total_canceled' => 0,
        'total_consumed' => 0 // NOUVEAU
    ];

    foreach ($projectMaterials as $material) {
        $amount = 0;
        if (!empty($material['qt_acheter']) && !empty($material['prix_unitaire'])) {
            $amount = $material['qt_acheter'] * $material['prix_unitaire'];
            $materialStats['total_amount'] += $amount;
        }

        // NOUVELLE LOGIQUE DE COMPTAGE
        if (empty($material['qt_acheter']) || $material['qt_acheter'] == '0' || $material['qt_acheter'] == 0) {
            // Consommé depuis le stock
            $materialStats['consumed_items']++;
            $materialStats['total_consumed'] += $amount;
        } elseif ($material['valide_achat'] == 'validé' || $material['valide_achat'] == 'en_cours') {
            $materialStats['ordered_items']++;
            $materialStats['total_ordered'] += $amount;
        } elseif ($material['valide_achat'] == 'reçu') {
            $materialStats['received_items']++;
            $materialStats['total_received'] += $amount;
        } elseif ($material['valide_achat'] == 'annulé') {
            $materialStats['canceled_items']++;
            $materialStats['total_canceled'] += $amount;
        } else {
            $materialStats['pending_items']++;
            $materialStats['total_pending'] += $amount;
        }
    }

    // CORRECTION PRINCIPALE : Récupérer les mouvements de stock liés à ce groupe de projets
    // Utilisation de paramètres nommés uniquement

    // D'abord, vérifier si la table dispatch_details existe
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'dispatch_details'");
    $dispatchTableExists = $checkTableStmt->rowCount() > 0;

    // Récupérer tous les IDs d'expression du groupe de projets
    $expressionIds = explode(', ', $projectInfo['expression_ids']);
    $clientName = explode(', ', $projectInfo['clients_list'])[0];

    // Construire les conditions IN pour les expressions
    $expressionInConditions = [];
    $params = [];

    for ($i = 0; $i < count($expressionIds); $i++) {
        $expressionInConditions[] = ":expression_id_$i";
        $params[":expression_id_$i"] = trim($expressionIds[$i]);
    }
    $expressionInClause = implode(',', $expressionInConditions);

    $movementsQuery = "
        SELECT DISTINCT
            sm.id,
            p.product_name,
            p.barcode,
            p.unit,
            sm.quantity,
            sm.movement_type,
            sm.provenance,
            sm.destination,
            sm.fournisseur,
            sm.demandeur,
            sm.created_at,
            sm.date,
            sm.invoice_id,
            sm.nom_projet,
            sm.notes,
            c.libelle as product_category,
            'stock_movement' as source_table,
            CASE 
                WHEN sm.movement_type = 'entry' THEN 'Entrée'
                WHEN sm.movement_type = 'output' THEN 'Sortie'
                WHEN sm.movement_type = 'transfer' THEN 'Transfert'
                WHEN sm.movement_type = 'return' THEN 'Retour'
                ELSE sm.movement_type
            END as type_mouvement_fr
        FROM stock_movement sm
        INNER JOIN products p ON sm.product_id = p.id
        LEFT JOIN categories c ON p.category = c.id
        WHERE (
            -- Liaison directe par code projet
            sm.nom_projet = :code_projet_main
            OR 
            -- Liaison par ID d'expression (tous les IDs du groupe)
            sm.nom_projet IN ($expressionInClause)
            OR
            -- Liaison partielle par code projet (pour les variations)
            sm.nom_projet LIKE CONCAT(:code_projet_like, '%')
            OR
            -- Liaison par nom client si trouvé dans le nom du projet
            sm.nom_projet LIKE CONCAT('%', :client_name, '%')
        )
        AND sm.created_at >= '" . getSystemStartDate() . "'
    ";

    // Ajouter les paramètres principaux
    $params[':code_projet_main'] = $codeProjet;
    $params[':code_projet_like'] = $codeProjet;
    $params[':client_name'] = $clientName;

    // Si la table dispatch_details existe, ajouter les dispatches
    if ($dispatchTableExists) {
        // Créer les conditions IN pour les dispatches avec des noms différents
        $dispatchExpressionInConditions = [];
        for ($i = 0; $i < count($expressionIds); $i++) {
            $dispatchExpressionInConditions[] = ":dispatch_expression_id_$i";
            $params[":dispatch_expression_id_$i"] = trim($expressionIds[$i]);
        }
        $dispatchExpressionInClause = implode(',', $dispatchExpressionInConditions);

        $movementsQuery .= "
        UNION
        SELECT DISTINCT
            CONCAT('dispatch_', dd.id) as id,
            p.product_name,
            p.barcode,
            p.unit,
            dd.allocated as quantity,
            'dispatch' as movement_type,
            'Stock' as provenance,
            dd.client as destination,
            dd.fournisseur,
            '' as demandeur,
            dd.created_at,
            dd.dispatch_date as date,
            dd.order_id as invoice_id,
            dd.project as nom_projet,
            dd.notes,
            c.libelle as product_category,
            'dispatch_details' as source_table,
            'Dispatch' as type_mouvement_fr
        FROM dispatch_details dd
        INNER JOIN products p ON dd.product_id = p.id
        LEFT JOIN categories c ON p.category = c.id
        WHERE (
            -- Liaison directe par code projet
            dd.project = :code_projet_dispatch
            OR 
            -- Liaison par ID d'expression (tous les IDs du groupe)
            dd.project IN ($dispatchExpressionInClause)
            OR
            -- Liaison partielle par code projet
            dd.project LIKE CONCAT(:code_projet_dispatch_like, '%')
            OR
            -- Liaison par nom client
            dd.client LIKE CONCAT('%', :client_name_dispatch, '%')
        )
        AND dd.created_at >= '" . getSystemStartDate() . "'
        ";

        // Ajouter les paramètres pour dispatch
        $params[':code_projet_dispatch'] = $codeProjet;
        $params[':code_projet_dispatch_like'] = $codeProjet;
        $params[':client_name_dispatch'] = $clientName;
    }

    $movementsQuery .= " ORDER BY created_at DESC, movement_type ASC";

    try {
        $movementsStmt = $pdo->prepare($movementsQuery);

        // Bind tous les paramètres
        foreach ($params as $key => $value) {
            $movementsStmt->bindValue($key, $value);
        }

        $movementsStmt->execute();
        $stockMovements = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // En cas d'erreur, essayer une requête simplifiée
        error_log("Erreur requête mouvements complexe: " . $e->getMessage());

        // Requête de fallback simplifiée
        $fallbackQuery = "
            SELECT DISTINCT
                sm.id,
                p.product_name,
                p.barcode,
                p.unit,
                sm.quantity,
                sm.movement_type,
                sm.provenance,
                sm.destination,
                sm.fournisseur,
                sm.demandeur,
                sm.created_at,
                sm.date,
                sm.invoice_id,
                sm.nom_projet,
                sm.notes,
                c.libelle as product_category,
                'stock_movement' as source_table,
                CASE 
                    WHEN sm.movement_type = 'entry' THEN 'Entrée'
                    WHEN sm.movement_type = 'output' THEN 'Sortie'
                    WHEN sm.movement_type = 'transfer' THEN 'Transfert'
                    WHEN sm.movement_type = 'return' THEN 'Retour'
                    ELSE sm.movement_type
                END as type_mouvement_fr
            FROM stock_movement sm
            INNER JOIN products p ON sm.product_id = p.id
            LEFT JOIN categories c ON p.category = c.id
            WHERE (
                sm.nom_projet = :code_projet
                OR sm.nom_projet LIKE CONCAT(:code_projet_like, '%')
            )
            AND sm.created_at >= '" . getSystemStartDate() . "'
            ORDER BY sm.created_at DESC
        ";

        $fallbackStmt = $pdo->prepare($fallbackQuery);
        $fallbackStmt->bindValue(':code_projet', $codeProjet);
        $fallbackStmt->bindValue(':code_projet_like', $codeProjet);
        $fallbackStmt->execute();
        $stockMovements = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculer les statistiques des mouvements (avec dispatch = entrée)
    $movementStats = [
        'total_entries' => 0,
        'total_outputs' => 0,
        'total_transfers' => 0,
        'total_returns' => 0,
        'total_dispatches' => 0, // Garde le compte séparé pour info
        'entry_quantity' => 0,
        'output_quantity' => 0,
        'net_movement' => 0,
        'total_movements' => count($stockMovements)
    ];

    foreach ($stockMovements as $movement) {
        $quantity = floatval($movement['quantity']);

        switch ($movement['movement_type']) {
            case 'entry':
                $movementStats['total_entries']++;
                $movementStats['entry_quantity'] += $quantity;
                $movementStats['net_movement'] += $quantity;
                break;
            case 'output':
                $movementStats['total_outputs']++;
                $movementStats['output_quantity'] += $quantity;
                $movementStats['net_movement'] -= $quantity;
                break;
            case 'transfer':
                $movementStats['total_transfers']++;
                break;
            case 'return':
                $movementStats['total_returns']++;
                $movementStats['entry_quantity'] += $quantity;
                $movementStats['net_movement'] += $quantity;
                break;
            case 'dispatch':
                // CORRECTION : Les dispatch sont comptés comme des entrées
                $movementStats['total_dispatches']++; // Compte séparé pour référence
                $movementStats['total_entries']++; // Ajouté aux entrées
                $movementStats['entry_quantity'] += $quantity;
                $movementStats['net_movement'] += $quantity;
                break;
        }
    }

    // Récupérer les achats liés à ce groupe de projets
    $purchasesQuery = "SELECT 
                        am.*,
                        u.name as user_name,
                        ip.nom_client
                       FROM achats_materiaux am
                       LEFT JOIN users_exp u ON am.user_achat = u.id
                       JOIN identification_projet ip ON am.expression_id = ip.idExpression
                       WHERE ip.code_projet = :code_projet
                       ORDER BY am.date_achat DESC";

    $purchasesStmt = $pdo->prepare($purchasesQuery);
    $purchasesStmt->bindParam(':code_projet', $codeProjet);
    $purchasesStmt->execute();
    $projectPurchases = $purchasesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Préparer les données pour le graphique des catégories (AGRÉGÉES)
    $categoriesData = [];
    foreach ($projectMaterials as $material) {
        $category = $material['category'] ?? 'Non catégorisé';
        $amount = 0;

        if (!empty($material['qt_acheter']) && !empty($material['prix_unitaire'])) {
            $amount = $material['qt_acheter'] * $material['prix_unitaire'];
        }

        if (!isset($categoriesData[$category])) {
            $categoriesData[$category] = [
                'count' => 0,
                'amount' => 0
            ];
        }

        $categoriesData[$category]['count']++;
        $categoriesData[$category]['amount'] += $amount;
    }

    // Trier par montant décroissant
    uasort($categoriesData, function ($a, $b) {
        return $b['amount'] <=> $a['amount'];
    });

    // Formater pour le graphique
    $categoriesChartData = [
        'labels' => [],
        'data' => [],
        'backgroundColor' => []
    ];

    // Couleurs pour les catégories
    $colors = [
        '#4299E1',
        '#48BB78',
        '#ECC94B',
        '#9F7AEA',
        '#ED64A6',
        '#F56565',
        '#667EEA',
        '#ED8936',
        '#38B2AC',
        '#CBD5E0'
    ];

    $i = 0;
    foreach ($categoriesData as $category => $data) {
        $categoriesChartData['labels'][] = $category;
        $categoriesChartData['data'][] = $data['amount'];
        $categoriesChartData['backgroundColor'][] = $colors[$i % count($colors)];
        $i++;
    }

    // Préparer les données pour le graphique des statuts
    $statusChartData = [
        'labels' => ['En attente', 'Commandé', 'Reçu', 'Consommé', 'Annulé'],
        'data' => [
            $materialStats['total_pending'],
            $materialStats['total_ordered'],
            $materialStats['total_received'],
            $materialStats['total_consumed'], // NOUVEAU
            $materialStats['total_canceled']
        ],
        'backgroundColor' => [
            'rgba(239, 68, 68, 0.7)',   // Rouge pour En attente
            'rgba(59, 130, 246, 0.7)',  // Bleu pour Commandé
            'rgba(16, 185, 129, 0.7)',  // Vert pour Reçu
            'rgba(20, 184, 166, 0.7)',  // Teal pour Consommé (NOUVEAU)
            'rgba(156, 163, 175, 0.7)'  // Gris pour Annulé
        ]
    ];

    // Analyse mensuelle des achats du groupe de projets
    $monthlyQuery = "SELECT 
                     DATE_FORMAT(am.date_achat, '%Y-%m') as month,
                     DATE_FORMAT(am.date_achat, '%b %Y') as month_name,
                     COUNT(*) as count,
                     SUM(CAST(am.prix_unitaire AS DECIMAL(10,2)) * CAST(am.quantity AS DECIMAL(10,2))) as total
                    FROM achats_materiaux am
                    JOIN identification_projet ip ON am.expression_id = ip.idExpression
                    WHERE ip.code_projet = :code_projet
                    AND am.date_achat IS NOT NULL
                    GROUP BY DATE_FORMAT(am.date_achat, '%Y-%m'), DATE_FORMAT(am.date_achat, '%b %Y')
                    ORDER BY month ASC";

    $monthlyStmt = $pdo->prepare($monthlyQuery);
    $monthlyStmt->bindParam(':code_projet', $codeProjet);
    $monthlyStmt->execute();
    $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Données pour l'évolution des prix des principaux matériaux du groupe
    $priceEvolutionQuery = "SELECT 
                           am.designation,
                           am.date_achat,
                           am.prix_unitaire,
                           ip.nom_client
                          FROM achats_materiaux am
                          JOIN identification_projet ip ON am.expression_id = ip.idExpression
                          WHERE ip.code_projet = :code_projet
                          AND am.date_achat IS NOT NULL
                          ORDER BY am.designation, am.date_achat";

    $priceEvolutionStmt = $pdo->prepare($priceEvolutionQuery);
    $priceEvolutionStmt->bindParam(':code_projet', $codeProjet);
    $priceEvolutionStmt->execute();
    $priceEvolutionData = $priceEvolutionStmt->fetchAll(PDO::FETCH_ASSOC);

    // Organiser les données d'évolution de prix par produit
    $priceByProduct = [];
    foreach ($priceEvolutionData as $item) {
        if (!isset($priceByProduct[$item['designation']])) {
            $priceByProduct[$item['designation']] = [];
        }
        $priceByProduct[$item['designation']][] = [
            'date' => date('d/m/Y', strtotime($item['date_achat'])),
            'price' => $item['prix_unitaire'],
            'client' => $item['nom_client']
        ];
    }

    // Prendre les 5 produits avec le plus de variations de prix
    $productsWithPriceVariation = [];
    foreach ($priceByProduct as $product => $prices) {
        if (count($prices) > 1) {
            $productsWithPriceVariation[$product] = $prices;
        }
    }
    // Trier par nombre de variations
    uasort($productsWithPriceVariation, function ($a, $b) {
        return count($b) - count($a);
    });
    // Prendre les 5 premiers
    $topProductsWithPriceVariation = array_slice($productsWithPriceVariation, 0, 5, true);

    // Préparer les données pour le graphique d'évolution des prix
    $priceEvolutionChartData = [
        'labels' => [],
        'datasets' => []
    ];

    $colorIndex = 0;
    foreach ($topProductsWithPriceVariation as $product => $priceData) {
        $dataset = [
            'label' => $product,
            'data' => [],
            'borderColor' => $colors[$colorIndex % count($colors)],
            'backgroundColor' => 'transparent',
            'tension' => 0.4
        ];

        foreach ($priceData as $data) {
            if (!in_array($data['date'], $priceEvolutionChartData['labels'])) {
                $priceEvolutionChartData['labels'][] = $data['date'];
            }
            $dataset['data'][] = [
                'x' => $data['date'],
                'y' => $data['price']
            ];
        }

        $priceEvolutionChartData['datasets'][] = $dataset;
        $colorIndex++;
    }

    // Récupérer les statistiques des fournisseurs pour ce groupe de projets
    $supplierStatsQuery = "SELECT 
                          am.fournisseur,
                          COUNT(*) as order_count,
                          SUM(CAST(am.quantity AS DECIMAL(10,2))) as total_quantity,
                          SUM(CAST(am.prix_unitaire AS DECIMAL(10,2)) * CAST(am.quantity AS DECIMAL(10,2))) as total_amount,
                          AVG(DATEDIFF(IFNULL(am.date_reception, CURRENT_DATE()), am.date_achat)) as avg_delivery_time,
                          GROUP_CONCAT(DISTINCT ip.nom_client ORDER BY ip.nom_client SEPARATOR ', ') as clients
                         FROM achats_materiaux am
                         JOIN identification_projet ip ON am.expression_id = ip.idExpression
                         WHERE ip.code_projet = :code_projet
                         AND am.fournisseur IS NOT NULL
                         AND am.fournisseur != ''
                         GROUP BY am.fournisseur
                         ORDER BY total_amount DESC";

    $supplierStatsStmt = $pdo->prepare($supplierStatsQuery);
    $supplierStatsStmt->bindParam(':code_projet', $codeProjet);
    $supplierStatsStmt->execute();
    $supplierStats = $supplierStatsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater pour le graphique des fournisseurs
    $supplierChartData = [
        'labels' => [],
        'data' => [],
        'backgroundColor' => []
    ];

    $i = 0;
    foreach ($supplierStats as $supplier) {
        if ($i < 5) { // Limiter aux 5 premiers fournisseurs
            $supplierChartData['labels'][] = $supplier['fournisseur'];
            $supplierChartData['data'][] = $supplier['total_amount'];
            $supplierChartData['backgroundColor'][] = $colors[$i % count($colors)];
        }
        $i++;
    }

    // Préparer les données pour le graphique radar de performance des fournisseurs
    $supplierPerformanceData = [];
    foreach ($supplierStats as $supplier) {
        // Calculer des indicateurs de performance
        $deliveryTime = min(50, $supplier['avg_delivery_time']); // Délai de livraison (limité à 50 jours max)
        $orderCount = $supplier['order_count'];
        $totalAmount = $supplier['total_amount'];

        // Normaliser les valeurs pour le radar (1-10)
        $deliveryScore = 10 - (($deliveryTime / 50) * 10); // Inverse: moins de jours = meilleur score

        $supplierPerformanceData[] = [
            'nom' => $supplier['fournisseur'],
            'avg_delivery_time' => $deliveryTime,
            'completion_rate' => $deliveryScore // Score de performance
        ];
    }

    // Analyse des prix d'achat vs. prix moyens pour le groupe
    $priceAnalysisQuery = "SELECT 
        AVG(CASE WHEN CAST(ed.prix_unitaire AS DECIMAL(10,2)) > 0 AND p.prix_moyen > 0 
              THEN ((CAST(ed.prix_unitaire AS DECIMAL(10,2)) - p.prix_moyen) / p.prix_moyen) * 100
              ELSE 0 
            END) as avg_price_diff,
        COUNT(CASE WHEN CAST(ed.prix_unitaire AS DECIMAL(10,2)) < p.prix_moyen THEN 1 END) as better_prices,
        COUNT(CASE WHEN CAST(ed.prix_unitaire AS DECIMAL(10,2)) > p.prix_moyen THEN 1 END) as worse_prices
        FROM identification_projet ip
        JOIN expression_dym ed ON ip.idExpression = ed.idExpression
        LEFT JOIN products p ON ed.designation = p.product_name
        WHERE ip.code_projet = :code_projet
        AND ed.prix_unitaire > 0 AND p.prix_moyen > 0";

    $priceAnalysisStmt = $pdo->prepare($priceAnalysisQuery);
    $priceAnalysisStmt->bindParam(':code_projet', $codeProjet);
    $priceAnalysisStmt->execute();
    $priceAnalysis = $priceAnalysisStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Groupe de Projets: <?php echo htmlspecialchars($codeProjet); ?> | Service Achat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">

    <link rel="stylesheet" href="style.css">

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Styles personnalisés pour DataTables */
        .dataTables_wrapper {
            padding: 0;
        }

        .dataTables_filter {
            margin-bottom: 1rem;
        }

        .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            margin-left: 0.5rem;
        }

        .dataTables_length select {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            margin: 0 0.5rem;
        }

        table.dataTable thead th {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
        }

        table.dataTable tbody tr:hover {
            background-color: #f9fafb;
        }

        .dt-buttons {
            margin-bottom: 1rem;
        }

        .dt-button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            margin-right: 0.5rem;
            cursor: pointer;
        }

        .dt-button:hover {
            background: #2563eb;
        }

        /* Styles pour les badges de statut */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-ordered {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-received {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-canceled {
            background-color: #ff6969;
            color:rgb(255, 255, 255);
        }

        .status-entry {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-output {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-consumed {
            background-color: #ecfdf5;
            color: #059669;
        }

        /* Responsive */
        @media (max-width: 768px) {

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                text-align: center;
                margin-bottom: 1rem;
            }
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .mini-chart-container {
            position: relative;
            height: 100px;
            width: 100%;
        }

        .dashboard-card {
            transition: all 0.3s ease;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .stats-card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .radar-chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- En-tête avec les actions -->
            <div class="bg-white shadow-sm rounded-lg p-4 mb-6">
                <div class="flex flex-wrap items-center justify-between">
                    <div class="flex items-center mb-2 md:mb-0">
                        <a href="stats_projets.php" class="text-blue-600 hover:text-blue-800 mr-2">
                            <span class="material-icons">arrow_back</span>
                        </a>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800 flex items-center">
                                Groupe de Projets: <?php echo htmlspecialchars($projectInfo['code_projet']); ?>
                                <span
                                    class="ml-2 px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">
                                    <?php echo $projectInfo['project_count']; ?> projet(s) groupé(s)
                                </span>
                            </h1>
                            <p class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($projectInfo['clients_list']); ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Statut du groupe avec badge -->
                        <div class="flex items-center">
                            <span class="px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800">
                                Actif depuis
                                <?php echo round((time() - strtotime($projectInfo['earliest_creation'])) / 86400); ?>
                                jours
                            </span>
                        </div>

                        <a href="stats_projets.php?client=<?php echo urlencode(explode(', ', $projectInfo['clients_list'])[0]); ?>"
                            class="flex items-center bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm">
                            <span class="material-icons text-sm mr-1">business</span>
                            Voir tous les projets client
                        </a>

                        <button id="export-pdf"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded flex items-center">
                            <span class="material-icons mr-2">picture_as_pdf</span>
                            Exporter PDF
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($errorMessage)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <p><?php echo $errorMessage; ?></p>
                </div>
            <?php else: ?>
                <!-- Informations détaillées du groupe de projets -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Colonne 1: Informations générales du groupe -->
                    <div class="bg-white shadow-sm rounded-lg p-6 dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-blue-600">folder_shared</span>
                            Informations du groupe
                        </h2>

                        <div class="space-y-4">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Code Projet</h3>
                                <p class="mt-1 font-semibold"><?php echo htmlspecialchars($projectInfo['code_projet']); ?>
                                </p>
                            </div>

                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Client(s)</h3>
                                <p class="mt-1"><?php echo htmlspecialchars($projectInfo['clients_list']); ?></p>
                            </div>

                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Chef(s) de projet</h3>
                                <p class="mt-1"><?php echo htmlspecialchars($projectInfo['project_managers']); ?></p>
                            </div>

                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Nombre de projets</h3>
                                <p class="mt-1 text-lg font-bold text-purple-600">
                                    <?php echo $projectInfo['project_count']; ?> projet(s)
                                </p>
                            </div>

                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Période d'activité</h3>
                                <p class="mt-1">
                                    Du <?php echo date('d/m/Y', strtotime($projectInfo['earliest_creation'])); ?>
                                    au <?php echo date('d/m/Y', strtotime($projectInfo['latest_creation'])); ?>
                                </p>
                            </div>

                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Localisation(s)</h3>
                                <p class="mt-1"><?php echo htmlspecialchars($projectInfo['locations_combined']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne 2: Description du groupe -->
                    <div class="bg-white shadow-sm rounded-lg p-6 dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-purple-600">description</span>
                            Descriptions combinées
                        </h2>
                        <div class="max-h-64 overflow-y-auto">
                            <p class="text-gray-700 whitespace-pre-line text-sm">
                                <?php echo htmlspecialchars($projectInfo['descriptions_combined']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Colonne 3: Statistiques du groupe -->
                    <div class="bg-white shadow-sm rounded-lg p-6 dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-green-600">analytics</span>
                            Statistiques groupées
                        </h2>

                        <div class="space-y-4">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Total des matériaux</h3>
                                <p class="mt-1 text-xl font-bold"><?php echo formatNumber($materialStats['total_items']); ?>
                                    articles</p>
                            </div>

                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Valeur totale</h3>
                                <p class="mt-1 text-xl font-bold">
                                    <?php echo formatNumber($materialStats['total_amount']); ?> FCFA
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mt-4">
                                <div class="text-center">
                                    <div class="text-red-600 font-bold">
                                        <?php echo formatNumber($materialStats['pending_items']); ?>
                                    </div>
                                    <p class="text-xs text-gray-500">En attente</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-blue-600 font-bold">
                                        <?php echo formatNumber($materialStats['ordered_items']); ?>
                                    </div>
                                    <p class="text-xs text-gray-500">Commandés</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-green-600 font-bold">
                                        <?php echo formatNumber($materialStats['received_items']); ?>
                                    </div>
                                    <p class="text-xs text-gray-500">Reçus</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-teal-600 font-bold">
                                        <?php echo formatNumber($materialStats['consumed_items']); ?>
                                    </div>
                                    <p class="text-xs text-gray-500">Consommés</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-gray-600 font-bold">
                                        <?php echo formatNumber($materialStats['canceled_items']); ?>
                                    </div>
                                    <p class="text-xs text-gray-500">Annulés</p>
                                </div>
                            </div>

                            <!-- Barre de progression -->
                            <div class="mt-4">
                                <h3 class="text-sm font-medium text-gray-500 mb-2">Progression des achats</h3>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <?php
                                    $pendingPercent = $materialStats['total_items'] > 0 ? ($materialStats['pending_items'] / $materialStats['total_items']) * 100 : 0;
                                    $orderedPercent = $materialStats['total_items'] > 0 ? ($materialStats['ordered_items'] / $materialStats['total_items']) * 100 : 0;
                                    $receivedPercent = $materialStats['total_items'] > 0 ? ($materialStats['received_items'] / $materialStats['total_items']) * 100 : 0;
                                    $canceledPercent = $materialStats['total_items'] > 0 ? ($materialStats['canceled_items'] / $materialStats['total_items']) * 100 : 0;
                                    ?>
                                    <div class="flex h-2.5 rounded-full overflow-hidden">
                                        <div class="bg-green-600 h-2.5" style="width: <?php echo $receivedPercent; ?>%">
                                        </div>
                                        <div class="bg-blue-600 h-2.5" style="width: <?php echo $orderedPercent; ?>%"></div>
                                        <div class="bg-red-600 h-2.5" style="width: <?php echo $pendingPercent; ?>%"></div>
                                        <div class="bg-gray-400 h-2.5" style="width: <?php echo $canceledPercent; ?>%">
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>0%</span>
                                    <span>Progression: <?php echo round($receivedPercent + $orderedPercent); ?>%</span>
                                    <span>100%</span>
                                </div>
                            </div>

                            <!-- Statistiques des mouvements de stock -->
                            <div class="mt-6 pt-4 border-t border-gray-200">
                                <h3 class="text-sm font-medium text-gray-500 mb-3 flex items-center">
                                    <span class="material-icons text-sm mr-1">swap_horiz</span>
                                    Mouvements de stock
                                </h3>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="text-center bg-green-50 p-2 rounded">
                                        <div class="text-green-600 font-bold text-lg">
                                            <?php echo formatNumber($movementStats['total_entries']); ?>
                                        </div>
                                        <p class="text-xs text-green-600">Entrées totales</p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo formatNumber($movementStats['entry_quantity']); ?> unités
                                        </p>
                                        <?php if ($movementStats['total_dispatches'] > 0): ?>
                                            <p class="text-xs text-purple-500">
                                                (<?php echo $movementStats['total_dispatches']; ?> dispatches)
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-center bg-red-50 p-2 rounded">
                                        <div class="text-red-600 font-bold text-lg">
                                            <?php echo formatNumber($movementStats['total_outputs']); ?>
                                        </div>
                                        <p class="text-xs text-red-600">Sorties totales</p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo formatNumber($movementStats['output_quantity']); ?> unités
                                        </p>
                                    </div>

                                    <div class="text-center bg-blue-50 p-2 rounded">
                                        <div class="text-blue-600 font-bold text-lg">
                                            <?php echo formatNumber($movementStats['total_transfers']); ?>
                                        </div>
                                        <p class="text-xs text-blue-600">Transferts</p>
                                    </div>

                                    <div class="text-center <?php echo $movementStats['net_movement'] >= 0 ? 'bg-green-50' : 'bg-orange-50'; ?> p-2 rounded">
                                        <div class="<?php echo $movementStats['net_movement'] >= 0 ? 'text-green-600' : 'text-orange-600'; ?> font-bold text-lg">
                                            <?php echo ($movementStats['net_movement'] >= 0 ? '+' : '') . formatNumber($movementStats['net_movement']); ?>
                                        </div>
                                        <p class="text-xs <?php echo $movementStats['net_movement'] >= 0 ? 'text-green-600' : 'text-orange-600'; ?>">
                                            Mouvement net
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Analyse des prix -->
                            <?php if (!empty($priceAnalysis)): ?>
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <h3 class="text-sm font-medium text-gray-500">Analyse des prix</h3>
                                    <?php
                                    $priceDiff = round($priceAnalysis['avg_price_diff'] ?? 0, 1);
                                    $priceDiffClass = $priceDiff <= 0 ? 'text-green-600' : 'text-red-600';
                                    $priceDiffSymbol = $priceDiff <= 0 ? '' : '+';
                                    ?>
                                    <p class="mt-1 text-lg <?php echo $priceDiffClass; ?> font-bold">
                                        <?php echo $priceDiffSymbol . $priceDiff; ?>%
                                    </p>
                                    <p class="text-xs text-gray-500">vs. prix moyens du catalogue</p>

                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <div class="text-center bg-green-50 p-1 rounded">
                                            <span
                                                class="text-green-700 font-medium"><?php echo $priceAnalysis['better_prices'] ?? 0; ?></span>
                                            <p class="text-xs text-green-600">Prix avantageux</p>
                                        </div>
                                        <div class="text-center bg-red-50 p-1 rounded">
                                            <span
                                                class="text-red-700 font-medium"><?php echo $priceAnalysis['worse_prices'] ?? 0; ?></span>
                                            <p class="text-xs text-red-600">Prix défavorables</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Premier rang de graphiques -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Graphique 1: Répartition par statut -->
                    <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-blue-600">pie_chart</span>
                            Répartition des achats par statut
                        </h2>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>

                    <!-- Graphique 2: Répartition par catégorie -->
                    <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-green-600">category</span>
                            Répartition des achats par catégorie
                        </h2>
                        <div class="chart-container">
                            <canvas id="categoriesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Deuxième rang de graphiques -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Graphique 3: Évolution mensuelle des achats -->
                    <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-purple-600">timeline</span>
                            Évolution mensuelle des achats
                        </h2>
                        <?php if (empty($monthlyData)): ?>
                            <div class="flex items-center justify-center h-64">
                                <p class="text-gray-500">Pas assez de données pour afficher ce graphique</p>
                            </div>
                        <?php else: ?>
                            <div class="chart-container">
                                <canvas id="monthlyPurchasesChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Graphique 4: Répartition par fournisseur -->
                    <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-yellow-600">business</span>
                            Répartition par fournisseur
                        </h2>
                        <?php if (empty($supplierStats)): ?>
                            <div class="flex items-center justify-center h-64">
                                <p class="text-gray-500">Pas assez de données pour afficher ce graphique</p>
                            </div>
                        <?php else: ?>
                            <div class="chart-container">
                                <canvas id="suppliersChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Troisième rang de graphiques -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Graphique 5: Évolution des prix -->
                    <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-red-600">trending_up</span>
                            Évolution des prix des principaux matériaux
                        </h2>
                        <?php if (empty($topProductsWithPriceVariation)): ?>
                            <div class="flex items-center justify-center h-64">
                                <p class="text-gray-500">Pas assez de données pour afficher ce graphique</p>
                            </div>
                        <?php else: ?>
                            <div class="chart-container">
                                <canvas id="priceEvolutionChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Graphique 6: Performance des fournisseurs -->
                    <div class="bg-white p-6 shadow-sm rounded-lg dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-indigo-600">speed</span>
                            Performance des fournisseurs
                        </h2>
                        <?php if (count($supplierPerformanceData) < 2): ?>
                            <div class="flex items-center justify-center h-64">
                                <p class="text-gray-500">Pas assez de données pour afficher ce graphique</p>
                            </div>
                        <?php else: ?>
                            <div class="radar-chart-container">
                                <canvas id="supplierPerformanceChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Onglets pour les différentes informations avec DataTables -->
                <div class="bg-white shadow-sm rounded-lg mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button onclick="showTab('tab-materials')"
                                class="py-4 px-6 border-b-2 border-blue-500 font-medium text-blue-600 tab-button"
                                id="btn-tab-materials">
                                <span class="material-icons text-sm mr-2">inventory</span>
                                Matériaux
                                <span class="ml-1 bg-blue-100 text-blue-800 text-xs font-medium px-2 py-0.5 rounded-full">
                                    <?php echo count($projectMaterials); ?>
                                </span>
                            </button>
                            <button onclick="showTab('tab-movements')"
                                class="py-4 px-6 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button"
                                id="btn-tab-movements">
                                <span class="material-icons text-sm mr-2">swap_horiz</span>
                                Mouvements de stock
                                <span class="ml-1 bg-gray-100 text-gray-800 text-xs font-medium px-2 py-0.5 rounded-full">
                                    <?php echo count($stockMovements); ?>
                                </span>
                            </button>
                            <button onclick="showTab('tab-purchases')"
                                class="py-4 px-6 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button"
                                id="btn-tab-purchases">
                                <span class="material-icons text-sm mr-2">shopping_cart</span>
                                Achats
                                <span class="ml-1 bg-gray-100 text-gray-800 text-xs font-medium px-2 py-0.5 rounded-full">
                                    <?php echo count($projectPurchases); ?>
                                </span>
                            </button>
                            <button onclick="showTab('tab-suppliers')"
                                class="py-4 px-6 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button"
                                id="btn-tab-suppliers">
                                <span class="material-icons text-sm mr-2">business</span>
                                Fournisseurs
                                <span class="ml-1 bg-gray-100 text-gray-800 text-xs font-medium px-2 py-0.5 rounded-full">
                                    <?php echo count($supplierStats); ?>
                                </span>
                            </button>
                        </nav>
                    </div>

                    <!-- Contenu des onglets avec DataTables -->
                    <div class="p-6">

                        <!-- Onglet Matériaux -->
                        <div id="tab-materials" class="tab-content" style="display: block;">
                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <span class="material-icons mr-2 text-blue-600">inventory</span>
                                    Liste des matériaux du groupe de projets
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Détails des matériaux demandés pour l'ensemble des projets du groupe
                                </p>
                            </div>

                            <div class="overflow-x-auto">
                                <table id="materialsTable" class="display responsive nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Désignation</th>
                                            <th>Catégorie</th>
                                            <th>Qté demandée</th>
                                            <th>Stock</th>
                                            <th>Achat</th>
                                            <th>Prix unitaire</th>
                                            <th>Montant</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($projectMaterials)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-gray-500">
                                                    Aucun matériau trouvé pour ce groupe de projets
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($projectMaterials as $material):
                                                // Calculer le montant
                                                $amount = 0;
                                                if (!empty($material['qt_acheter']) && !empty($material['prix_unitaire'])) {
                                                    $amount = $material['qt_acheter'] * $material['prix_unitaire'];
                                                }

                                                // Déterminer le statut et sa classe CSS
                                                $statusClass = 'status-pending';
                                                $statusText = 'En attente';

                                                // NOUVELLE LOGIQUE : Vérifier d'abord si c'est consommé depuis le stock
                                                if (empty($material['qt_acheter']) || $material['qt_acheter'] == '0' || $material['qt_acheter'] == 0) {
                                                    // Si quantité à acheter = 0, c'est que le besoin a été satisfait par le stock
                                                    $statusClass = 'status-consumed';
                                                    $statusText = 'Consommée';
                                                } elseif ($material['valide_achat'] == 'validé' || $material['valide_achat'] == 'en_cours') {
                                                    $statusClass = 'status-ordered';
                                                    $statusText = 'Commandé';
                                                } elseif ($material['valide_achat'] == 'reçu') {
                                                    $statusClass = 'status-received';
                                                    $statusText = 'Reçu';
                                                } elseif ($material['valide_achat'] == 'annulé') {
                                                    $statusClass = 'status-canceled';
                                                    $statusText = 'Annulé';
                                                }
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($material['nom_client']); ?></td>
                                                    <td>
                                                        <div class="font-medium">
                                                            <?php echo htmlspecialchars($material['designation']); ?>
                                                        </div>
                                                        <?php if ($material['order_count'] > 1): ?>
                                                            <div class="text-xs text-gray-500">
                                                                <?php echo $material['order_count']; ?> commandes
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($material['category'] ?? 'Non catégorisé'); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($material['quantity']); ?>
                                                        <?php echo htmlspecialchars($material['unit'] ?? ''); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($material['qt_stock'] ?? '0'); ?></td>
                                                    <td><?php echo htmlspecialchars($material['qt_acheter'] ?? '0'); ?></td>
                                                    <td>
                                                        <?php echo !empty($material['prix_unitaire']) ? formatNumber($material['prix_unitaire']) . ' FCFA' : '-'; ?>
                                                    </td>
                                                    <td class="font-medium">
                                                        <?php echo formatNumber($amount); ?> FCFA
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo $statusClass; ?>">
                                                            <?php echo $statusText; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Onglet Mouvements de stock CORRIGÉ -->
                        <div id="tab-movements" class="tab-content" style="display: none;">
                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <span class="material-icons mr-2 text-green-600">swap_horiz</span>
                                    Mouvements de stock du groupe de projets
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Historique complet des entrées et sorties de stock pour l'ensemble des projets du groupe
                                </p>

                                <!-- Statistiques rapides des mouvements -->
                                <div class="grid grid-cols-4 gap-4 mt-4 mb-6">
                                    <div class="bg-green-50 rounded-lg p-3">
                                        <div class="flex items-center">
                                            <span class="material-icons text-green-600 mr-2">arrow_downward</span>
                                            <div>
                                                <p class="text-green-600 font-bold"><?php echo formatNumber($movementStats['total_entries']); ?></p>
                                                <p class="text-xs text-green-600">Entrées totales</p>
                                                <?php if ($movementStats['total_dispatches'] > 0): ?>
                                                    <p class="text-xs text-purple-600">
                                                        (dont <?php echo $movementStats['total_dispatches']; ?> dispatches)
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-red-50 rounded-lg p-3">
                                        <div class="flex items-center">
                                            <span class="material-icons text-red-600 mr-2">arrow_upward</span>
                                            <div>
                                                <p class="text-red-600 font-bold"><?php echo formatNumber($movementStats['total_outputs']); ?></p>
                                                <p class="text-xs text-red-600">Sorties</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-blue-50 rounded-lg p-3">
                                        <div class="flex items-center">
                                            <span class="material-icons text-blue-600 mr-2">swap_horiz</span>
                                            <div>
                                                <p class="text-blue-600 font-bold"><?php echo formatNumber($movementStats['total_transfers']); ?></p>
                                                <p class="text-xs text-blue-600">Transferts</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <div class="flex items-center">
                                            <span class="material-icons text-gray-600 mr-2">assessment</span>
                                            <div>
                                                <p class="<?php echo $movementStats['net_movement'] >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-bold">
                                                    <?php echo ($movementStats['net_movement'] >= 0 ? '+' : '') . formatNumber($movementStats['net_movement']); ?>
                                                </p>
                                                <p class="text-xs text-gray-600">Solde net</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table id="movementsTable" class="display responsive nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Produit</th>
                                            <th>Catégorie</th>
                                            <th>Quantité</th>
                                            <th>Unité</th>
                                            <th>Provenance/Destination</th>
                                            <th>Fournisseur</th>
                                            <th>Demandeur</th>
                                            <th>Client</th>
                                            <th>Expression</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($stockMovements)): ?>
                                            <tr>
                                                <td colspan="12" class="text-center text-gray-500 py-8">
                                                    <div class="flex flex-col items-center">
                                                        <span class="material-icons text-gray-300 text-4xl mb-2">inventory_2</span>
                                                        <p>Aucun mouvement de stock trouvé pour ce groupe de projets</p>
                                                        <p class="text-xs mt-1">Code projet : <?php echo htmlspecialchars($codeProjet); ?></p>
                                                        <p class="text-xs">Expressions : <?php echo htmlspecialchars($projectInfo['expression_ids']); ?></p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($stockMovements as $movement):
                                                // CORRECTION COMPLÈTE : Convertir tous les dispatch en entrée
                                                $actualMovementType = $movement['movement_type'];

                                                // Si c'est un dispatch, on le traite comme une entrée
                                                if ($actualMovementType === 'dispatch') {
                                                    $actualMovementType = 'entry';
                                                }

                                                // Déterminer les classes CSS selon le type (avec dispatch converti)
                                                $typeClass = 'status-pending';
                                                $typeIcon = 'help';
                                                $sourceOrDest = '';
                                                $displayType = '';

                                                switch ($actualMovementType) {
                                                    case 'entry': // Inclut maintenant les dispatch convertis
                                                        $typeClass = 'status-entry';
                                                        $typeIcon = 'arrow_downward';
                                                        $sourceOrDest = $movement['provenance'] ?: $movement['destination'];
                                                        $displayType = 'Entrée';
                                                        break;
                                                    case 'output':
                                                        $typeClass = 'status-output';
                                                        $typeIcon = 'arrow_upward';
                                                        $sourceOrDest = $movement['destination'];
                                                        $displayType = 'Sortie';
                                                        break;
                                                    case 'transfer':
                                                        $typeClass = 'status-ordered';
                                                        $typeIcon = 'swap_horiz';
                                                        $sourceOrDest = $movement['destination'];
                                                        $displayType = 'Transfert';
                                                        break;
                                                    case 'return':
                                                        $typeClass = 'status-received';
                                                        $typeIcon = 'undo';
                                                        $sourceOrDest = $movement['destination'];
                                                        $displayType = 'Retour';
                                                        break;
                                                }

                                                // Utiliser la date correcte selon la source
                                                $displayDate = !empty($movement['date']) ? $movement['date'] : $movement['created_at'];
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="font-medium">
                                                            <?php echo date('d/m/Y', strtotime($displayDate)); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            <?php echo date('H:i', strtotime($displayDate)); ?>
                                                        </div>
                                                        <div class="text-xs text-purple-500">
                                                            <?php echo $movement['source_table'] == 'dispatch_details' ? 'Dispatch' : 'Stock'; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo $typeClass; ?> flex items-center">
                                                            <span class="material-icons text-sm mr-1"><?php echo $typeIcon; ?></span>
                                                            <?php echo $displayType; ?>
                                                            <?php if ($movement['movement_type'] === 'dispatch'): ?>
                                                                <span class="text-xs ml-1 opacity-75">(Dispatch)</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="font-medium">
                                                            <?php echo htmlspecialchars($movement['product_name']); ?>
                                                        </div>
                                                        <?php if (!empty($movement['barcode'])): ?>
                                                            <div class="text-xs text-gray-500">
                                                                Code: <?php echo htmlspecialchars($movement['barcode']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded-full">
                                                            <?php echo htmlspecialchars($movement['product_category'] ?? 'Non catégorisé'); ?>
                                                        </span>
                                                    </td>
                                                    <td class="font-bold text-right">
                                                        <span class="<?php echo in_array($actualMovementType, ['entry']) ? 'text-green-600' : 'text-red-600'; ?>">
                                                            <?php echo in_array($actualMovementType, ['entry']) ? '+' : '-'; ?><?php echo formatNumber($movement['quantity']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($movement['unit'] ?? 'unité'); ?></td>
                                                    <td><?php echo htmlspecialchars($sourceOrDest ?: '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($movement['fournisseur'] ?: '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($movement['demandeur'] ?: '-'); ?></td>
                                                    <td>
                                                        <div class="font-medium">
                                                            <?php echo htmlspecialchars($movement['destination'] ?? '-'); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="text-xs font-mono bg-blue-50 text-blue-700 px-2 py-1 rounded">
                                                            <?php echo htmlspecialchars($movement['nom_projet']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($movement['notes'])): ?>
                                                            <div class="text-xs text-gray-600 max-w-xs truncate" title="<?php echo htmlspecialchars($movement['notes']); ?>">
                                                                <?php echo htmlspecialchars($movement['notes']); ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Onglet Achats -->
                        <div id="tab-purchases" class="tab-content" style="display: none;">
                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <span class="material-icons mr-2 text-purple-600">shopping_cart</span>
                                    Achats effectués pour le groupe de projets
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Détails des commandes passées pour l'ensemble des projets
                                </p>
                            </div>

                            <div class="overflow-x-auto">
                                <table id="purchasesTable" class="display responsive nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Désignation</th>
                                            <th>Quantité</th>
                                            <th>Prix unitaire</th>
                                            <th>Montant</th>
                                            <th>Fournisseur</th>
                                            <th>Date achat</th>
                                            <th>Statut</th>
                                            <th>Acheteur</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($projectPurchases)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-gray-500">
                                                    Aucun achat trouvé pour ce groupe de projets
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($projectPurchases as $purchase):
                                                // Calculer le montant
                                                $purchaseAmount = 0;
                                                if (!empty($purchase['quantity']) && !empty($purchase['prix_unitaire'])) {
                                                    $purchaseAmount = $purchase['quantity'] * $purchase['prix_unitaire'];
                                                }

                                                // Déterminer le statut
                                                $purchaseStatusClass = 'status-pending';
                                                if ($purchase['status'] == 'reçu') {
                                                    $purchaseStatusClass = 'status-received';
                                                } elseif ($purchase['status'] == 'commandé') {
                                                    $purchaseStatusClass = 'status-ordered';
                                                }
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($purchase['nom_client']); ?></td>
                                                    <td class="font-medium">
                                                        <?php echo htmlspecialchars($purchase['designation']); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($purchase['quantity']); ?>
                                                        <?php echo htmlspecialchars($purchase['unit'] ?? ''); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo !empty($purchase['prix_unitaire']) ? formatNumber($purchase['prix_unitaire']) . ' FCFA' : '-'; ?>
                                                    </td>
                                                    <td class="font-medium">
                                                        <?php echo formatNumber($purchaseAmount); ?> FCFA
                                                    </td>
                                                    <td><?php echo htmlspecialchars($purchase['fournisseur'] ?? '-'); ?></td>
                                                    <td>
                                                        <?php echo !empty($purchase['date_achat']) ? date('d/m/Y', strtotime($purchase['date_achat'])) : '-'; ?>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo $purchaseStatusClass; ?>">
                                                            <?php echo ucfirst($purchase['status'] ?? 'En attente'); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($purchase['user_name'] ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Onglet Fournisseurs -->
                        <div id="tab-suppliers" class="tab-content" style="display: none;">
                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <span class="material-icons mr-2 text-orange-600">business</span>
                                    Statistiques des fournisseurs
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Performance et analyse des fournisseurs du groupe de projets
                                </p>
                            </div>

                            <div class="overflow-x-auto">
                                <table id="suppliersTable" class="display responsive nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Fournisseur</th>
                                            <th>Client(s)</th>
                                            <th>Nb commandes</th>
                                            <th>Quantité totale</th>
                                            <th>Montant total</th>
                                            <th>Délai moyen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($supplierStats)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-gray-500">
                                                    Aucun fournisseur trouvé pour ce groupe de projets
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($supplierStats as $supplier):
                                                $avgDeliveryTime = round($supplier['avg_delivery_time'], 1);
                                                $deliveryTimeClass = 'status-received';

                                                if ($avgDeliveryTime <= 7) {
                                                    $deliveryTimeClass = 'status-received';
                                                } elseif ($avgDeliveryTime <= 14) {
                                                    $deliveryTimeClass = 'status-pending';
                                                } else {
                                                    $deliveryTimeClass = 'status-canceled';
                                                }
                                            ?>
                                                <tr>
                                                    <td class="font-medium">
                                                        <?php echo htmlspecialchars($supplier['fournisseur']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($supplier['clients']); ?></td>
                                                    <td><?php echo formatNumber($supplier['order_count']); ?></td>
                                                    <td><?php echo formatNumber($supplier['total_quantity']); ?></td>
                                                    <td class="font-medium">
                                                        <?php echo formatNumber($supplier['total_amount']); ?> FCFA
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo $deliveryTimeClass; ?>">
                                                            <?php echo $avgDeliveryTime; ?> jours
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <!-- Actions de navigation -->
            <div class="flex flex-wrap justify-between mb-6">
                <a href="stats_projets.php"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded flex items-center">
                    <span class="material-icons mr-2">arrow_back</span>
                    Retour aux statistiques
                </a>

                <div class="flex space-x-3">
                    <?php if (!empty($projectInfo['clients_list'])): ?>
                        <a href="stats_projets.php?client=<?php echo urlencode(explode(', ', $projectInfo['clients_list'])[0]); ?>"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded flex items-center">
                            <span class="material-icons mr-2">business</span>
                            Projets du client
                        </a>
                    <?php endif; ?>

                    <button id="print-details"
                        class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded flex items-center">
                        <span class="material-icons mr-2">print</span>
                        Imprimer
                    </button>
                </div>
            </div>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <!-- Scripts personnalisés -->
    <script src="assets/js/chart_functions.js"></script>
    <script src="assets/js/projet-details.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Préparation des données pour JavaScript
            const projectData = {
                codeProjet: '<?php echo htmlspecialchars($codeProjet); ?>',
                charts: {
                    statusChart: <?php echo json_encode($statusChartData ?? []); ?>,
                    categoriesChart: <?php echo json_encode($categoriesChartData ?? []); ?>,
                    monthlyData: <?php echo json_encode($monthlyData ?? []); ?>,
                    suppliersChart: <?php echo json_encode($supplierChartData ?? []); ?>,
                    priceEvolutionChart: <?php echo json_encode($priceEvolutionChartData ?? []); ?>,
                    supplierPerformanceData: <?php echo json_encode($supplierPerformanceData ?? []); ?>
                }
            };

            // Initialiser la page avec les données
            initProjectDetails(projectData);
        });
    </script>
</body>

</html>