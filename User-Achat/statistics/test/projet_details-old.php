<?php
/**
 * Module de détails des projets - Version optimisée
 * Intègre les expressions de besoins classiques et les besoins système
 * 
 * @package DYM_MANUFACTURE
 * @subpackage expressions_besoins/User-Achat/statistics
 * @version 2.2.0
 * @author Équipe DYM
 * @date 2025-05-21
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
function formatNumber($number, $decimals = 0)
{
    if ($number === null || $number === '')
        return '0';
    return number_format((float) $number, $decimals, ',', ' ');
}
// Détecter si c'est un projet standard ou un besoin système
$isSystemProject = strpos($codeProjet, 'SYS-') === 0;
try {
    if ($isSystemProject) {
        // ================================================================
        // TRAITEMENT DES BESOINS SYSTÈME
        // ================================================================
        $besoinId = substr($codeProjet, 4); // Enlever le préfixe 'SYS-'
        // Analyse des prix d'achat vs. prix moyens pour besoins système
        $priceAnalysisQuery = "SELECT 
            AVG(CASE WHEN b.achat_status IN ('validé', 'reçu', 'en_cours') THEN (
                SELECT ((am.prix_unitaire - p.prix_moyen) / NULLIF(p.prix_moyen, 0)) * 100
                FROM achats_materiaux am 
                WHERE am.expression_id = b.idBesoin 
                AND am.designation = b.designation_article
                AND am.prix_unitaire > 0
                ORDER BY am.date_achat DESC
                LIMIT 1
            ) ELSE 0 END) as avg_price_diff,
            COUNT(CASE WHEN (
                SELECT am.prix_unitaire 
                FROM achats_materiaux am 
                WHERE am.expression_id = b.idBesoin 
                AND am.designation = b.designation_article
                ORDER BY am.date_achat DESC
                LIMIT 1
            ) < p.prix_moyen THEN 1 END) as better_prices,
            COUNT(CASE WHEN (
                SELECT am.prix_unitaire 
                FROM achats_materiaux am 
                WHERE am.expression_id = b.idBesoin 
                AND am.designation = b.designation_article
                ORDER BY am.date_achat DESC
                LIMIT 1
            ) > p.prix_moyen THEN 1 END) as worse_prices
            FROM besoins b
            LEFT JOIN products p ON b.designation_article = p.product_name
            WHERE b.idBesoin = :besoin_id
            AND p.prix_moyen > 0";
        $priceAnalysisStmt = $pdo->prepare($priceAnalysisQuery);
        $priceAnalysisStmt->bindParam(':besoin_id', $besoinId);
        $priceAnalysisStmt->execute();
        $priceAnalysis = $priceAnalysisStmt->fetch(PDO::FETCH_ASSOC);
        // Récupérer les informations du besoin système
        $projectInfoQuery = "SELECT 
            d.idBesoin,
            CONCAT('SYS-', d.idBesoin) as code_projet,
            d.client as nom_client,
            d.motif_demande as description_projet,
            '' as sitgeo,
            d.nom_prenoms as chefprojet,
            d.service_demandeur,
            d.date_demande as created_at,
            NULL as project_status,
            NULL as completed_date,
            NULL as completed_by,
            'besoins' as source_table
            FROM demandeur d
            WHERE d.idBesoin = :besoin_id
            LIMIT 1";
        $projectInfoStmt = $pdo->prepare($projectInfoQuery);
        $projectInfoStmt->bindParam(':besoin_id', $besoinId);
        $projectInfoStmt->execute();
        $projectInfo = $projectInfoStmt->fetch(PDO::FETCH_ASSOC);
        if (!$projectInfo) {
            // Si le projet n'existe pas, rediriger vers la liste des projets
            header("Location: stats_projets.php");
            exit();
        }
        // Récupérer les matériaux du besoin système
        $materialsQuery = "SELECT 
            b.id,
            b.designation_article as designation,
            b.qt_demande as quantity,
            b.caracteristique as unit,
            b.qt_stock,
            b.qt_acheter,
            (
                SELECT am.prix_unitaire 
                FROM achats_materiaux am 
                WHERE am.expression_id = b.idBesoin 
                AND am.designation = b.designation_article
                ORDER BY am.date_achat DESC
                LIMIT 1
            ) as prix_unitaire,
            (
                SELECT am.fournisseur 
                FROM achats_materiaux am 
                WHERE am.expression_id = b.idBesoin 
                AND am.designation = b.designation_article
                ORDER BY am.date_achat DESC
                LIMIT 1
            ) as fournisseur,
            b.achat_status as status,
            b.created_at,
            b.updated_at,
            p.unit_price as catalog_price,
            p.quantity as stock_available,
            c.libelle as category,
            (
                SELECT COUNT(*) 
                FROM achats_materiaux am 
                WHERE am.expression_id = b.idBesoin 
                AND am.designation = b.designation_article
            ) as order_count,
            (
                SELECT MAX(am.date_reception) 
                FROM achats_materiaux am 
                WHERE am.expression_id = b.idBesoin 
                AND am.designation = b.designation_article
            ) as last_receipt_date
            FROM besoins b
            LEFT JOIN products p ON b.designation_article = p.product_name
            LEFT JOIN categories c ON p.category = c.id
            WHERE b.idBesoin = :besoin_id
            ORDER BY b.achat_status ASC, b.created_at DESC";
        $materialsStmt = $pdo->prepare($materialsQuery);
        $materialsStmt->bindParam(':besoin_id', $besoinId);
        $materialsStmt->execute();
        $projectMaterials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
        // Statistiques supplémentaires pour le besoin système
        $additionalStatsQuery = "SELECT 
            COUNT(b.id) as total_materials,
            SUM(CASE WHEN b.achat_status = 'reçu' THEN 1 ELSE 0 END) as received_count,
            SUM(CASE WHEN b.achat_status = 'validé' OR b.achat_status = 'en_cours' THEN 1 ELSE 0 END) as ordered_count,
            SUM(CASE WHEN b.achat_status = 'annulé' THEN 1 ELSE 0 END) as canceled_count,
            SUM(CASE WHEN b.achat_status = 'pas validé' OR b.achat_status IS NULL THEN 1 ELSE 0 END) as pending_count,
            COALESCE(SUM(b.qt_acheter * (
                SELECT MAX(am.prix_unitaire) 
                FROM achats_materiaux am 
                WHERE am.expression_id = b.idBesoin 
                AND am.designation = b.designation_article
            )), 0) as total_value,
            MAX(b.updated_at) as last_update
            FROM besoins b
            WHERE b.idBesoin = :besoin_id";
        $additionalStatsStmt = $pdo->prepare($additionalStatsQuery);
        $additionalStatsStmt->bindParam(':besoin_id', $besoinId);
        $additionalStatsStmt->execute();
        $additionalStats = $additionalStatsStmt->fetch(PDO::FETCH_ASSOC);
        // Fusionner les statistiques supplémentaires avec les informations du projet
        $projectInfo = array_merge($projectInfo, $additionalStats);
        // Calculer le pourcentage de progression
        if ($projectInfo['total_materials'] > 0) {
            $projectInfo['progress_percentage'] = round(
                (($projectInfo['received_count'] + $projectInfo['ordered_count']) / $projectInfo['total_materials']) * 100
            );
        } else {
            $projectInfo['progress_percentage'] = 0;
        }
        // Récupérer les achats liés à ce besoin système
        $purchasesQuery = "SELECT 
            am.*,
            u.name as user_name
            FROM achats_materiaux am
            LEFT JOIN users_exp u ON am.user_achat = u.id
            WHERE am.expression_id = :expression_id
            ORDER BY am.date_achat DESC";
        $purchasesStmt = $pdo->prepare($purchasesQuery);
        $purchasesStmt->bindParam(':expression_id', $besoinId);
        $purchasesStmt->execute();
        $projectPurchases = $purchasesStmt->fetchAll(PDO::FETCH_ASSOC);

        // ================================================================
        // MODIFICATION: RÉCUPÉRATION AMÉLIORÉE DES MOUVEMENTS DE STOCK
        // POUR LES BESOINS SYSTÈME
        // ================================================================
        $movementsQuery = "
            SELECT DISTINCT
                sm.id,
                p.product_name,
                sm.quantity,
                sm.movement_type,
                sm.provenance,
                sm.destination,
                sm.fournisseur,
                sm.demandeur,
                sm.created_at,
                sm.invoice_id
            FROM stock_movement sm
            JOIN products p ON sm.product_id = p.id
            LEFT JOIN besoins b ON b.idBesoin = :besoin_id AND p.product_name = b.designation_article
            WHERE 
                sm.nom_projet = :code_projet 
                OR 
                b.id IS NOT NULL
            ORDER BY sm.created_at DESC";
        
        $movementsStmt = $pdo->prepare($movementsQuery);
        $movementsStmt->bindParam(':code_projet', $codeProjet);
        $movementsStmt->bindParam(':besoin_id', $besoinId);
        $movementsStmt->execute();
        $stockMovements = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // ================================================================
        // TRAITEMENT DES PROJETS STANDARDS
        // ================================================================
        // Analyse des prix d'achat vs. prix moyens
        $priceAnalysisQuery = "SELECT 
            AVG(CASE WHEN ed.prix_unitaire > 0 AND p.prix_moyen > 0 
                THEN ((ed.prix_unitaire - p.prix_moyen) / p.prix_moyen) * 100
                ELSE 0 
            END) as avg_price_diff,
            COUNT(CASE WHEN ed.prix_unitaire < p.prix_moyen THEN 1 END) as better_prices,
            COUNT(CASE WHEN ed.prix_unitaire > p.prix_moyen THEN 1 END) as worse_prices
            FROM identification_projet ip
            JOIN expression_dym ed ON ip.idExpression = ed.idExpression
            LEFT JOIN products p ON ed.designation = p.product_name
            WHERE ip.code_projet = :code_projet
            AND ed.prix_unitaire > 0 AND p.prix_moyen > 0";
        $priceAnalysisStmt = $pdo->prepare($priceAnalysisQuery);
        $priceAnalysisStmt->bindParam(':code_projet', $codeProjet);
        $priceAnalysisStmt->execute();
        $priceAnalysis = $priceAnalysisStmt->fetch(PDO::FETCH_ASSOC);
        // Récupérer les informations du projet standard
        $projectInfoQuery = "SELECT 
            ip.*,
            ps.status as project_status,
            ps.completed_at as completed_date,
            (SELECT u.name FROM users_exp u WHERE u.id = ps.completed_by) as completed_by,
            'expression_dym' as source_table
            FROM identification_projet ip
            LEFT JOIN project_status ps ON ip.idExpression = ps.idExpression
            WHERE ip.code_projet = :code_projet
            AND " . getFilteredDateCondition('ip.created_at') . "
            LIMIT 1";
        $projectInfoStmt = $pdo->prepare($projectInfoQuery);
        $projectInfoStmt->bindParam(':code_projet', $codeProjet);
        $projectInfoStmt->execute();
        $projectInfo = $projectInfoStmt->fetch(PDO::FETCH_ASSOC);
        if (!$projectInfo) {
            // Si le projet n'existe pas, rediriger vers la liste des projets
            header("Location: stats_projets.php");
            exit();
        }
        // Récupérer les matériaux du projet standard
        $materialsQuery = "SELECT 
            ed.*,
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
            AND " . getFilteredDateCondition('ip.created_at') . "
            ORDER BY ed.valide_achat ASC, ed.created_at DESC";
        $materialsStmt = $pdo->prepare($materialsQuery);
        $materialsStmt->bindParam(':code_projet', $codeProjet);
        $materialsStmt->execute();
        $projectMaterials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
        // Récupérer des statistiques supplémentaires pour le projet standard
        $additionalStatsQuery = "SELECT 
            COUNT(ed.id) as total_materials,
            SUM(CASE WHEN ed.valide_achat = 'reçu' THEN 1 ELSE 0 END) as received_count,
            SUM(CASE WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'en_cours' THEN 1 ELSE 0 END) as ordered_count,
            SUM(CASE WHEN ed.valide_achat = 'annulé' THEN 1 ELSE 0 END) as canceled_count,
            SUM(CASE WHEN ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL THEN 1 ELSE 0 END) as pending_count,
            COALESCE(SUM(CAST(NULLIF(ed.qt_acheter, '') AS DECIMAL(10,2)) * CAST(NULLIF(ed.prix_unitaire, '') AS DECIMAL(10,2))), 0) as total_value,
            MAX(ed.updated_at) as last_update
            FROM expression_dym ed
            WHERE ed.idExpression = :idExpression";
        $additionalStatsStmt = $pdo->prepare($additionalStatsQuery);
        $additionalStatsStmt->bindParam(':idExpression', $projectInfo['idExpression']);
        $additionalStatsStmt->execute();
        $additionalStats = $additionalStatsStmt->fetch(PDO::FETCH_ASSOC);
        // Fusionner les statistiques supplémentaires avec les informations du projet
        $projectInfo = array_merge($projectInfo, $additionalStats);
        // Calculer le pourcentage de progression
        if ($projectInfo['total_materials'] > 0) {
            $projectInfo['progress_percentage'] = round(
                (($projectInfo['received_count'] + $projectInfo['ordered_count']) / $projectInfo['total_materials']) * 100
            );
        } else {
            $projectInfo['progress_percentage'] = 0;
        }

        // ================================================================
        // MODIFICATION: RÉCUPÉRATION AMÉLIORÉE DES MOUVEMENTS DE STOCK
        // POUR LES PROJETS STANDARDS
        // ================================================================
        $movementsQuery = "
            SELECT DISTINCT
                sm.id,
                p.product_name,
                sm.quantity,
                sm.movement_type,
                sm.provenance,
                sm.destination,
                sm.fournisseur,
                sm.demandeur,
                sm.created_at,
                sm.invoice_id
            FROM stock_movement sm
            JOIN products p ON sm.product_id = p.id
            LEFT JOIN expression_dym ed ON ed.idExpression = :expression_id AND p.product_name = ed.designation
            WHERE 
                sm.nom_projet = :code_projet 
                OR 
                ed.id IS NOT NULL
            ORDER BY sm.created_at DESC";
        
        $movementsStmt = $pdo->prepare($movementsQuery);
        $movementsStmt->bindParam(':code_projet', $codeProjet);
        $movementsStmt->bindParam(':expression_id', $projectInfo['idExpression']);
        $movementsStmt->execute();
        $stockMovements = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les achats liés à ce projet
        $purchasesQuery = "SELECT 
            am.*,
            u.name as user_name
            FROM achats_materiaux am
            LEFT JOIN users_exp u ON am.user_achat = u.id
            WHERE am.expression_id LIKE :expression_id
            ORDER BY am.date_achat DESC";
        $purchasesStmt = $pdo->prepare($purchasesQuery);
        $expressionId = $projectInfo['idExpression'] . '%';  // Utilisation de LIKE pour trouver tous les achats liés
        $purchasesStmt->bindParam(':expression_id', $expressionId);
        $purchasesStmt->execute();
        $projectPurchases = $purchasesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // ================================================================
    // TRAITEMENTS COMMUNS (BESOINS SYSTÈME ET PROJETS STANDARDS)
    // ================================================================
    // Préparer les données pour le graphique des catégories
    $categoriesData = [];
    foreach ($projectMaterials as $material) {
        // Récupérer la catégorie, en tenant compte des différences entre les tables
        $category = $material['category'] ?? 'Non catégorisé';
        $amount = 0;
        if ($isSystemProject) {
            // Calcul du montant pour besoins système
            if (!empty($material['qt_acheter']) && !empty($material['prix_unitaire'])) {
                $amount = floatval($material['qt_acheter']) * floatval($material['prix_unitaire']);
            }
        } else {
            // Calcul du montant pour expression_dym
            if (!empty($material['qt_acheter']) && !empty($material['prix_unitaire'])) {
                $amount = floatval($material['qt_acheter']) * floatval($material['prix_unitaire']);
            }
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
        '#4299E1', // blue-500
        '#48BB78', // green-500
        '#ECC94B', // yellow-500
        '#9F7AEA', // purple-500
        '#ED64A6', // pink-500
        '#F56565', // red-500
        '#667EEA', // indigo-500
        '#ED8936', // orange-500
        '#38B2AC', // teal-500
        '#CBD5E0'  // gray-400
    ];
    $i = 0;
    foreach ($categoriesData as $category => $data) {
        $categoriesChartData['labels'][] = $category;
        $categoriesChartData['data'][] = $data['amount'];
        $categoriesChartData['backgroundColor'][] = $colors[$i % count($colors)];
        $i++;
    }
    // Préparer les données pour le graphique des statuts
    if ($isSystemProject) {
        // Statuts pour les besoins système
        $statusChartData = [
            'labels' => ['En attente', 'Commandé', 'Reçu'],
            'data' => [
                floatval($projectInfo['pending_count'] ?? 0),
                floatval($projectInfo['ordered_count'] ?? 0),
                floatval($projectInfo['received_count'] ?? 0)
            ],
            'backgroundColor' => [
                'rgba(239, 68, 68, 0.7)',   // Rouge pour En attente
                'rgba(59, 130, 246, 0.7)',  // Bleu pour Commandé
                'rgba(16, 185, 129, 0.7)'   // Vert pour Reçu
            ]
        ];
    } else {
        // Statuts pour les expressions de besoin
        $statusChartData = [
            'labels' => ['En attente', 'Commandé', 'Reçu'],
            'data' => [
                floatval($projectInfo['pending_count'] ?? 0),
                floatval($projectInfo['ordered_count'] ?? 0),
                floatval($projectInfo['received_count'] ?? 0)
            ],
            'backgroundColor' => [
                'rgba(239, 68, 68, 0.7)',   // Rouge pour En attente
                'rgba(59, 130, 246, 0.7)',  // Bleu pour Commandé
                'rgba(16, 185, 129, 0.7)'   // Vert pour Reçu
            ]
        ];
    }
    // Analyse mensuelle des achats du projet
    $monthlyQuery = "SELECT 
        DATE_FORMAT(am.date_achat, '%Y-%m') as month,
        DATE_FORMAT(am.date_achat, '%b %Y') as month_name,
        COUNT(*) as count,
        SUM(am.prix_unitaire * am.quantity) as total
        FROM achats_materiaux am
        WHERE am.expression_id " . ($isSystemProject ? "= :expression_id" : "LIKE :expression_id") . "
        AND am.date_achat IS NOT NULL
        GROUP BY DATE_FORMAT(am.date_achat, '%Y-%m'), DATE_FORMAT(am.date_achat, '%b %Y')
        ORDER BY month ASC";
    $monthlyStmt = $pdo->prepare($monthlyQuery);
    if ($isSystemProject) {
        $monthlyStmt->bindParam(':expression_id', $besoinId);
    } else {
        $expressionId = $projectInfo['idExpression'] . '%';
        $monthlyStmt->bindParam(':expression_id', $expressionId);
    }
    $monthlyStmt->execute();
    $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
    // Données pour l'évolution des prix des principaux matériaux
    $priceEvolutionQuery = "SELECT 
        am.designation,
        am.date_achat,
        am.prix_unitaire
        FROM achats_materiaux am
        WHERE am.expression_id " . ($isSystemProject ? "= :expression_id" : "LIKE :expression_id") . "
        AND am.date_achat IS NOT NULL
        ORDER BY am.designation, am.date_achat";
    $priceEvolutionStmt = $pdo->prepare($priceEvolutionQuery);
    if ($isSystemProject) {
        $priceEvolutionStmt->bindParam(':expression_id', $besoinId);
    } else {
        $expressionId = $projectInfo['idExpression'] . '%';
        $priceEvolutionStmt->bindParam(':expression_id', $expressionId);
    }
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
            'price' => $item['prix_unitaire']
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
    // Récupérer les statistiques des fournisseurs pour ce projet
    $supplierStatsQuery = "SELECT 
        am.fournisseur,
        COUNT(*) as order_count,
        SUM(am.quantity) as total_quantity,
        SUM(am.prix_unitaire * am.quantity) as total_amount,
        AVG(DATEDIFF(IFNULL(am.date_reception, CURRENT_DATE()), am.date_achat)) as avg_delivery_time
        FROM achats_materiaux am
        WHERE am.expression_id " . ($isSystemProject ? "= :expression_id" : "LIKE :expression_id") . "
        AND am.fournisseur IS NOT NULL
        AND am.fournisseur != ''
        GROUP BY am.fournisseur
        ORDER BY total_amount DESC";
    $supplierStatsStmt = $pdo->prepare($supplierStatsQuery);
    if ($isSystemProject) {
        $supplierStatsStmt->bindParam(':expression_id', $besoinId);
    } else {
        $expressionId = $projectInfo['idExpression'] . '%';
        $supplierStatsStmt->bindParam(':expression_id', $expressionId);
    }
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
            $supplierChartData['data'][] = floatval($supplier['total_amount']);
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
} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des données: " . $e->getMessage();
}
/**
 * Détermine la couleur en fonction du pourcentage
 * @param float $percentage Pourcentage
 * @return string Classe CSS pour la couleur
 */
function getProgressColor($percentage)
{
    if ($percentage >= 75)
        return 'bg-green-500';
    if ($percentage >= 50)
        return 'bg-blue-500';
    if ($percentage >= 25)
        return 'bg-yellow-500';
    return 'bg-red-500';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Projet: <?php echo htmlspecialchars($codeProjet); ?> | Service Achat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
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

        /* Style pour le badge de source de données */
        .source-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 8px;
            font-size: 0.7rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .source-badge.expression {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid #3b82f6;
        }

        .source-badge.besoin {
            background-color: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
            border: 1px solid #8b5cf6;
        }

        /* Style amélioré pour les tableaux */
        .data-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #475569;
            padding: 0.75rem 1rem;
            text-align: left;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .data-table tr:hover {
            background-color: #f1f5f9;
        }

        .data-table td {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: #1e293b;
        }

        /* Animation de chargement */
        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .loading {
            animation: pulse 1s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- En-tête avec les actions -->
            <div class="bg-white shadow-sm rounded-lg p-4 mb-6 relative">
                <div class="flex flex-wrap items-center justify-between">
                    <div class="flex items-center mb-2 md:mb-0">
                        <a href="stats_projets.php" class="text-blue-600 hover:text-blue-800 mr-2">
                            <span class="material-icons">arrow_back</span>
                        </a>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">
                                <?php echo $isSystemProject ? 'Besoin Système:' : 'Projet:'; ?>
                                <?php echo htmlspecialchars($projectInfo['code_projet']); ?>
                            </h1>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($projectInfo['nom_client']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Badge indiquant la source -->
                    <div class="source-badge <?php echo $isSystemProject ? 'besoin' : 'expression'; ?>">
                        <?php echo $isSystemProject ? 'Besoin Système' : 'Expression DYM'; ?>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Statut du projet avec badge -->
                        <div class="flex items-center">
                            <span class="px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800">
                                <?php echo $isSystemProject ? 'Système' : 'Projet'; ?> actif depuis
                                <?php echo round((time() - strtotime($projectInfo['created_at'])) / 86400); ?> jours
                            </span>
                        </div>

                        <a href="?client=<?php echo urlencode($projectInfo['nom_client']); ?>"
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
                <!-- Informations détaillées du projet -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Colonne 1: Informations générales -->
                    <div class="bg-white shadow-sm rounded-lg p-6 dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-blue-600">info</span>
                            Informations générales
                        </h2>

                        <div class="space-y-4">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Code
                                    <?php echo $isSystemProject ? 'Besoin' : 'Projet'; ?></h3>
                                <p class="mt-1 font-semibold"><?php echo htmlspecialchars($projectInfo['code_projet']); ?>
                                </p>
                            </div>

                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Client</h3>
                                <p class="mt-1">
                                    <?php echo htmlspecialchars($projectInfo['nom_client'] ?? 'Non spécifié'); ?></p>
                            </div>

                            <div>
                                <h3 class="text-sm font-medium text-gray-500">
                                    <?php echo $isSystemProject ? 'Demandeur' : 'Chef de projet'; ?></h3>
                                <p class="mt-1"><?php echo htmlspecialchars($projectInfo['chefprojet']); ?></p>
                            </div>

                            <?php if ($isSystemProject): ?>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500">Service demandeur</h3>
                                    <p class="mt-1">
                                        <?php echo htmlspecialchars($projectInfo['service_demandeur'] ?? 'Non spécifié'); ?></p>
                                </div>
                            <?php else: ?>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500">ID Expression</h3>
                                    <p class="mt-1 text-sm font-mono">
                                        <?php echo htmlspecialchars($projectInfo['idExpression']); ?></p>
                                </div>
                            <?php endif; ?>

                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Date de création</h3>
                                <p class="mt-1"><?php echo date('d/m/Y H:i', strtotime($projectInfo['created_at'])); ?></p>
                            </div>

                            <?php if (!$isSystemProject): ?>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500">Localisation</h3>
                                    <p class="mt-1"><?php echo htmlspecialchars($projectInfo['sitgeo'] ?? 'Non spécifiée'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Colonne 2: Description du projet -->
                    <div class="bg-white shadow-sm rounded-lg p-6 dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-purple-600">description</span>
                            Description <?php echo $isSystemProject ? 'du besoin' : 'du projet'; ?>
                        </h2>
                        <p class="text-gray-700 whitespace-pre-line">
                            <?php echo htmlspecialchars($projectInfo['description_projet']); ?>
                        </p>

                        <!-- Date et mise à jour -->
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <div class="flex items-center text-sm text-gray-500">
                                <span class="material-icons text-sm mr-1">update</span>
                                Dernière mise à jour:
                                <?php echo isset($projectInfo['last_update']) ? date('d/m/Y H:i', strtotime($projectInfo['last_update'])) : 'N/A'; ?>
                            </div>

                            <?php if (isset($projectInfo['project_status']) && $projectInfo['project_status'] === 'completed'): ?>
                                <div class="flex items-center text-sm text-green-600 mt-2">
                                    <span class="material-icons text-sm mr-1">check_circle</span>
                                    Terminé le: <?php echo date('d/m/Y', strtotime($projectInfo['completed_date'])); ?>
                                    par <?php echo htmlspecialchars($projectInfo['completed_by'] ?? 'N/A'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Colonne 3: Statistiques du projet -->
                    <div class="bg-white shadow-sm rounded-lg p-6 dashboard-card">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="material-icons mr-2 text-green-600">analytics</span>
                            Statistiques
                        </h2>

                        <div class="space-y-4">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Total des matériaux</h3>
                                <p class="mt-1 text-xl font-bold">
                                    <?php echo formatNumber($projectInfo['total_materials']); ?>
                                    articles</p>
                            </div>

                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Valeur totale</h3>
                                <p class="mt-1 text-xl font-bold">
                                    <?php echo formatNumber($projectInfo['total_value'] ?? 0); ?> FCFA
                                </p>
                            </div>

                            <div class="grid grid-cols-3 gap-4 mt-4">
                                <div class="text-center">
                                    <div class="text-red-600 font-bold">
                                        <?php echo formatNumber($projectInfo['pending_count'] ?? 0); ?>
                                    </div>
                                    <p class="text-xs text-gray-500">En attente</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-blue-600 font-bold">
                                        <?php echo formatNumber($projectInfo['ordered_count'] ?? 0); ?>
                                    </div>
                                    <p class="text-xs text-gray-500">Commandés</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-green-600 font-bold">
                                        <?php echo formatNumber($projectInfo['received_count'] ?? 0); ?>
                                    </div>
                                    <p class="text-xs text-gray-500">Reçus</p>
                                </div>
                            </div>

                            <!-- Barre de progression -->
                            <div class="mt-4">
                                <h3 class="text-sm font-medium text-gray-500 mb-2">Progression des achats</h3>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <?php
                                    $pendingPercent = $projectInfo['total_materials'] > 0 ? ($projectInfo['pending_count'] / $projectInfo['total_materials']) * 100 : 0;
                                    $orderedPercent = $projectInfo['total_materials'] > 0 ? ($projectInfo['ordered_count'] / $projectInfo['total_materials']) * 100 : 0;
                                    $receivedPercent = $projectInfo['total_materials'] > 0 ? ($projectInfo['received_count'] / $projectInfo['total_materials']) * 100 : 0;
                                    ?>
                                    <div class="flex h-2.5 rounded-full overflow-hidden">
                                        <div class="bg-green-600 h-2.5" style="width: <?php echo $receivedPercent; ?>%">
                                        </div>
                                        <div class="bg-blue-600 h-2.5" style="width: <?php echo $orderedPercent; ?>%"></div>
                                        <div class="bg-red-600 h-2.5" style="width: <?php echo $pendingPercent; ?>%"></div>
                                    </div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>0%</span>
                                    <span>Progression: <?php echo round($receivedPercent + $orderedPercent); ?>%</span>
                                    <span>100%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Analyse des prix -->
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

                <!-- Onglets pour les différentes informations -->
                <div class="bg-white shadow-sm rounded-lg mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button onclick="showTab('tab-materials')"
                                class="py-4 px-6 border-b-2 border-blue-500 font-medium text-blue-600"
                                id="btn-tab-materials">
                                Matériaux <span
                                    class="ml-1 bg-blue-100 text-blue-800 text-xs font-medium px-2 py-0.5 rounded-full"><?php echo count($projectMaterials); ?></span>
                            </button>
                            <button onclick="showTab('tab-movements')"
                                class="py-4 px-6 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                                id="btn-tab-movements">
                                Mouvements de stock <span
                                    class="ml-1 bg-gray-100 text-gray-800 text-xs font-medium px-2 py-0.5 rounded-full"><?php echo count($stockMovements); ?></span>
                            </button>
                            <button onclick="showTab('tab-purchases')"
                                class="py-4 px-6 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                                id="btn-tab-purchases">
                                Achats <span
                                    class="ml-1 bg-gray-100 text-gray-800 text-xs font-medium px-2 py-0.5 rounded-full"><?php echo count($projectPurchases); ?></span>
                            </button>
                            <button onclick="showTab('tab-suppliers')"
                                class="py-4 px-6 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                                id="btn-tab-suppliers">
                                Fournisseurs <span
                                    class="ml-1 bg-gray-100 text-gray-800 text-xs font-medium px-2 py-0.5 rounded-full"><?php echo count($supplierStats); ?></span>
                            </button>
                        </nav>
                    </div>

                    <!-- Contenu des onglets -->
                    <div class="p-6">
                        <!-- Onglet Matériaux -->
                        <div id="tab-materials" style="display: block;">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 data-table">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Catégorie</th>
                                            <th scope="col">Qté demandée</th>
                                            <th scope="col">Stock</th>
                                            <th scope="col">Achat</th>
                                            <th scope="col">Prix unitaire</th>
                                            <th scope="col">Montant</th>
                                            <th scope="col">Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($projectMaterials)): ?>
                                            <tr>
                                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">Aucun matériau
                                                    trouvé pour ce <?php echo $isSystemProject ? 'besoin' : 'projet'; ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($projectMaterials as $material):
                                                // Déterminer la classe du statut
                                                $statusClass = '';
                                                $statusText = '';

                                                // Adapter selon la source (expression_dym ou besoins)
                                                if ($isSystemProject) {
                                                    // Pour besoins système
                                                    if ($material['status'] == 'validé' || $material['status'] == 'en_cours') {
                                                        $statusClass = 'bg-blue-100 text-blue-800';
                                                        $statusText = 'Commandé';
                                                    } elseif ($material['status'] == 'reçu') {
                                                        $statusClass = 'bg-green-100 text-green-800';
                                                        $statusText = 'Reçu';
                                                    } elseif ($material['status'] == 'annulé') {
                                                        $statusClass = 'bg-red-100 text-red-800';
                                                        $statusText = 'Annulé';
                                                    } else {
                                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                                        $statusText = 'En attente';
                                                    }
                                                } else {
                                                    // Pour expression_dym
                                                    if ($material['valide_achat'] == 'validé' || $material['valide_achat'] == 'en_cours') {
                                                        $statusClass = 'bg-blue-100 text-blue-800';
                                                        $statusText = 'Commandé';
                                                    } elseif ($material['valide_achat'] == 'reçu') {
                                                        $statusClass = 'bg-green-100 text-green-800';
                                                        $statusText = 'Reçu';
                                                    } elseif ($material['valide_achat'] == 'annulé') {
                                                        $statusClass = 'bg-red-100 text-red-800';
                                                        $statusText = 'Annulé';
                                                    } else {
                                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                                        $statusText = 'En attente';
                                                    }
                                                }

                                                // Calculer le montant
                                                $amount = 0;
                                                if (!empty($material['qt_acheter']) && !empty($material['prix_unitaire'])) {
                                                    $amount = floatval($material['qt_acheter']) * floatval($material['prix_unitaire']);
                                                }
                                                ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($material['designation']); ?>
                                                        <?php if ($material['order_count'] > 1): ?>
                                                            <span
                                                                class="text-xs text-gray-500 ml-1">(<?php echo $material['order_count']; ?>
                                                                commandes)</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($material['category'] ?? 'Non catégorisé'); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo formatNumber($material['quantity'], 2); ?>
                                                        <?php echo htmlspecialchars($material['unit'] ?? ''); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo formatNumber($material['qt_stock'] ?? '0'); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo formatNumber($material['qt_acheter'] ?? '0'); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo !empty($material['prix_unitaire']) ? formatNumber($material['prix_unitaire']) . ' FCFA' : '-'; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo formatNumber($amount); ?> FCFA
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                        <span
                                                            class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
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

                        <!-- Onglet Mouvements de stock -->
                        <div id="tab-movements" style="display: none;">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 data-table">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col">Produit</th>
                                            <th scope="col">Quantité</th>
                                            <th scope="col">Type</th>
                                            <th scope="col">Provenance/Destination</th>
                                            <th scope="col">Fournisseur</th>
                                            <th scope="col">Demandeur</th>
                                            <th scope="col">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($stockMovements)): ?>
                                            <tr>
                                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">Aucun mouvement de
                                                    stock trouvé pour ce <?php echo $isSystemProject ? 'besoin' : 'projet'; ?>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($stockMovements as $movement):
                                                // Déterminer le type de mouvement
                                                $moveType = $movement['movement_type'] == 'entry' ? 'Entrée' : 'Sortie';
                                                $moveClass = $movement['movement_type'] == 'entry' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';

                                                // Déterminer la provenance ou destination
                                                $sourceOrDest = $movement['movement_type'] == 'entry' ? $movement['provenance'] : $movement['destination'];
                                                ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($movement['product_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo formatNumber($movement['quantity']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                        <span
                                                            class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $moveClass; ?>">
                                                            <?php echo $moveType; ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($sourceOrDest); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($movement['fournisseur'] ?? '-'); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($movement['demandeur'] ?? '-'); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo date('d/m/Y H:i', strtotime($movement['created_at'])); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Onglet Achats -->
                        <div id="tab-purchases" style="display: none;">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 data-table">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Quantité</th>
                                            <th scope="col">Prix unitaire</th>
                                            <th scope="col">Montant</th>
                                            <th scope="col">Fournisseur</th>
                                            <th scope="col">Date achat</th>
                                            <th scope="col">Statut</th>
                                            <th scope="col">Acheteur</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($projectPurchases)): ?>
                                            <tr>
                                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">Aucun achat trouvé
                                                    pour ce <?php echo $isSystemProject ? 'besoin' : 'projet'; ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($projectPurchases as $purchase):
                                                // Déterminer la classe du statut
                                                $purchaseStatusClass = '';

                                                if ($purchase['status'] == 'reçu') {
                                                    $purchaseStatusClass = 'bg-green-100 text-green-800';
                                                } elseif ($purchase['status'] == 'commandé') {
                                                    $purchaseStatusClass = 'bg-blue-100 text-blue-800';
                                                } elseif ($purchase['status'] == 'annulé') {
                                                    $purchaseStatusClass = 'bg-red-100 text-red-800';
                                                } else {
                                                    $purchaseStatusClass = 'bg-yellow-100 text-yellow-800';
                                                }

                                                // Calculer le montant
                                                $purchaseAmount = 0;
                                                if (!empty($purchase['quantity']) && !empty($purchase['prix_unitaire'])) {
                                                    $purchaseAmount = floatval($purchase['quantity']) * floatval($purchase['prix_unitaire']);
                                                }
                                                ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($purchase['designation']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo formatNumber($purchase['quantity'], 2); ?>
                                                        <?php echo htmlspecialchars($purchase['unit'] ?? ''); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo !empty($purchase['prix_unitaire']) ? formatNumber($purchase['prix_unitaire']) . ' FCFA' : '-'; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo formatNumber($purchaseAmount); ?> FCFA
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($purchase['fournisseur'] ?? '-'); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo !empty($purchase['date_achat']) ? date('d/m/Y', strtotime($purchase['date_achat'])) : '-'; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                        <span
                                                            class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $purchaseStatusClass; ?>">
                                                            <?php echo ucfirst($purchase['status'] ?? 'En attente'); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($purchase['user_name'] ?? '-'); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Onglet Fournisseurs -->
                        <div id="tab-suppliers" style="display: none;">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 data-table">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col">Fournisseur</th>
                                            <th scope="col">Nombre de commandes</th>
                                            <th scope="col">Quantité totale</th>
                                            <th scope="col">Montant total</th>
                                            <th scope="col">Délai moyen (jours)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($supplierStats)): ?>
                                            <tr>
                                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Aucun fournisseur
                                                    trouvé pour ce <?php echo $isSystemProject ? 'besoin' : 'projet'; ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($supplierStats as $supplier):
                                                // Déterminer la classe pour le délai
                                                $deliveryTimeClass = '';
                                                $avgDeliveryTime = round($supplier['avg_delivery_time'], 1);

                                                if ($avgDeliveryTime <= 7) {
                                                    $deliveryTimeClass = 'bg-green-100 text-green-800';
                                                } elseif ($avgDeliveryTime <= 14) {
                                                    $deliveryTimeClass = 'bg-yellow-100 text-yellow-800';
                                                } else {
                                                    $deliveryTimeClass = 'bg-red-100 text-red-800';
                                                }
                                                ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($supplier['fournisseur']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo formatNumber($supplier['order_count']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo formatNumber($supplier['total_quantity'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo formatNumber($supplier['total_amount']); ?> FCFA
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                        <span
                                                            class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $deliveryTimeClass; ?>">
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
                    <?php if (!empty($projectInfo['nom_client'])): ?>
                        <a href="stats_projets.php?client=<?php echo urlencode($projectInfo['nom_client']); ?>"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded flex items-center">
                            <span class="material-icons mr-2">business</span>
                            <?php echo $isSystemProject ? 'Besoins' : 'Projets'; ?> du client
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

    <script src="assets/js/chart_functions.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Créer le graphique de statut des achats
            const statusData = <?php echo json_encode($statusChartData ?? []); ?>;

            if (document.getElementById('statusChart') && statusData.labels.length > 0) {
                renderCategoriesChart('statusChart', {
                    labels: statusData.labels,
                    data: statusData.data,
                    backgroundColor: statusData.backgroundColor
                });
            }

            // Créer le graphique des catégories
            const categoriesData = <?php echo json_encode($categoriesChartData ?? []); ?>;

            if (document.getElementById('categoriesChart') && categoriesData.labels.length > 0) {
                renderCategoriesChart('categoriesChart', {
                    labels: categoriesData.labels,
                    data: categoriesData.data,
                    backgroundColor: categoriesData.backgroundColor
                });
            }

            // Créer le graphique des achats mensuels
            <?php if (!empty($monthlyData)): ?>
                const monthlyPurchasesData = {
                    labels: <?php echo json_encode(array_column($monthlyData, 'month_name')); ?>,
                    counts: <?php echo json_encode(array_column($monthlyData, 'count')); ?>,
                    amounts: <?php echo json_encode(array_column($monthlyData, 'total')); ?>
                };

                if (document.getElementById('monthlyPurchasesChart')) {
                    // Créer le graphique en barres des achats mensuels
                    const ctx = document.getElementById('monthlyPurchasesChart').getContext('2d');

                    // Définir le gradient pour l'arrière-plan
                    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.8)');
                    gradient.addColorStop(1, 'rgba(16, 185, 129, 0.2)');

                    // Configuration avancée
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: monthlyPurchasesData.labels,
                            datasets: [{
                                label: 'Montant total (FCFA)',
                                data: monthlyPurchasesData.amounts,
                                backgroundColor: gradient,
                                borderColor: 'rgb(16, 185, 129)',
                                borderWidth: 1,
                                borderRadius: 6,
                                barPercentage: 0.7,
                                categoryPercentage: 0.8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: {
                                duration: 1500,
                                easing: 'easeOutQuart'
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                title: {
                                    display: true,
                                    text: `Achats mensuels - <?php echo date('Y'); ?>`,
                                    font: {
                                        size: 16,
                                        family: "'Inter', sans-serif",
                                        weight: 'bold'
                                    },
                                    color: '#334155',
                                    padding: {
                                        top: 10,
                                        bottom: 20
                                    }
                                },
                                tooltip: {
                                    usePointStyle: true,
                                    backgroundColor: 'rgba(17, 24, 39, 0.9)',
                                    titleColor: '#ffffff',
                                    bodyColor: '#ffffff',
                                    padding: 12,
                                    cornerRadius: 8,
                                    callbacks: {
                                        label: function (context) {
                                            return `Montant: ${formatMoney(context.raw)} FCFA`;
                                        },
                                        afterLabel: function (context) {
                                            const dataIndex = context.dataIndex;
                                            return `Commandes: ${monthlyPurchasesData.counts[dataIndex]}`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function (value) {
                                            return formatMoney(value, false);
                                        },
                                        font: {
                                            family: "'Inter', sans-serif",
                                            size: 12
                                        },
                                        color: '#64748b'
                                    },
                                    grid: {
                                        color: 'rgba(226, 232, 240, 0.7)'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        font: {
                                            family: "'Inter', sans-serif",
                                            size: 12
                                        },
                                        color: '#64748b'
                                    }
                                }
                            }
                        }
                    });
                }
            <?php endif; ?>

            // Créer le graphique des fournisseurs
            <?php if (!empty($supplierStats)): ?>
                const suppliersData = <?php echo json_encode($supplierChartData ?? []); ?>;

                if (document.getElementById('suppliersChart')) {
                    renderCategoriesChart('suppliersChart', {
                        labels: suppliersData.labels,
                        data: suppliersData.data,
                        backgroundColor: suppliersData.backgroundColor
                    });
                }
            <?php endif; ?>

            // Créer le graphique d'évolution des prix
            <?php if (!empty($topProductsWithPriceVariation)): ?>
                const priceEvolutionData = <?php echo json_encode($priceEvolutionChartData ?? []); ?>;

                if (document.getElementById('priceEvolutionChart')) {
                    new Chart(document.getElementById('priceEvolutionChart'), {
                        type: 'line',
                        data: {
                            labels: priceEvolutionData.labels,
                            datasets: priceEvolutionData.datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'nearest',
                                axis: 'x',
                                intersect: false
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            return context.dataset.label + ': ' + formatMoney(context.parsed.y) + ' FCFA';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    title: {
                                        display: true,
                                        text: 'Prix unitaire (FCFA)'
                                    },
                                    ticks: {
                                        callback: function (value) {
                                            return formatMoney(value);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            <?php endif; ?>

            // Créer le graphique radar de performance fournisseur
            <?php if (!empty($supplierPerformanceData) && count($supplierPerformanceData) >= 2): ?>
                const supplierPerformanceChartData = <?php echo json_encode($supplierPerformanceData ?? []); ?>;

                if (document.getElementById('supplierPerformanceChart')) {
                    // Préparation des données pour le graphique radar
                    const labels = supplierPerformanceChartData.map(item => item.nom);
                    const deliveryData = supplierPerformanceChartData.map(item => item.avg_delivery_time);
                    const scoreData = supplierPerformanceChartData.map(item => item.completion_rate);

                    new Chart(document.getElementById('supplierPerformanceChart'), {
                        type: 'radar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Délai de livraison (jours)',
                                    data: deliveryData,
                                    fill: true,
                                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                    borderColor: 'rgb(255, 99, 132)',
                                    pointBackgroundColor: 'rgb(255, 99, 132)',
                                    pointBorderColor: '#fff',
                                    pointHoverBackgroundColor: '#fff',
                                    pointHoverBorderColor: 'rgb(255, 99, 132)',
                                    pointRadius: 4,
                                    pointHoverRadius: 6
                                },
                                {
                                    label: 'Taux de performance (%)',
                                    data: scoreData,
                                    fill: true,
                                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                    borderColor: 'rgb(54, 162, 235)',
                                    pointBackgroundColor: 'rgb(54, 162, 235)',
                                    pointBorderColor: '#fff',
                                    pointHoverBackgroundColor: '#fff',
                                    pointHoverBorderColor: 'rgb(54, 162, 235)',
                                    pointRadius: 4,
                                    pointHoverRadius: 6
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            const value = context.raw;
                                            if (context.datasetIndex === 0) {
                                                return `Délai: ${value.toFixed(1)} jours`;
                                            } else {
                                                return `Performance: ${value.toFixed(1)}/10`;
                                            }
                                        }
                                    }
                                }
                            },
                            scales: {
                                r: {
                                    angleLines: {
                                        display: true
                                    },
                                    suggestedMin: 0
                                }
                            }
                        }
                    });
                }
            <?php endif; ?>

            // Fonction pour formater les montants en FCFA
            function formatMoney(amount, includeCurrency = true) {
                const formatted = new Intl.NumberFormat('fr-FR', {
                    maximumFractionDigits: 0
                }).format(amount);

                return includeCurrency ? `${formatted} FCFA` : formatted;
            }

            // Fonction pour gérer les onglets
            function showTab(tabId) {
                // Cache tous les onglets
                document.getElementById('tab-materials').style.display = 'none';
                document.getElementById('tab-movements').style.display = 'none';
                document.getElementById('tab-purchases').style.display = 'none';
                document.getElementById('tab-suppliers').style.display = 'none';

                // Réinitialise les styles de tous les boutons
                document.getElementById('btn-tab-materials').classList.remove('border-blue-500', 'text-blue-600');
                document.getElementById('btn-tab-materials').classList.add('border-transparent', 'text-gray-500');
                document.getElementById('btn-tab-movements').classList.remove('border-blue-500', 'text-blue-600');
                document.getElementById('btn-tab-movements').classList.add('border-transparent', 'text-gray-500');
                document.getElementById('btn-tab-purchases').classList.remove('border-blue-500', 'text-blue-600');
                document.getElementById('btn-tab-purchases').classList.add('border-transparent', 'text-gray-500');
                document.getElementById('btn-tab-suppliers').classList.remove('border-blue-500', 'text-blue-600');
                document.getElementById('btn-tab-suppliers').classList.add('border-transparent', 'text-gray-500');

                // Affiche l'onglet sélectionné
                document.getElementById(tabId).style.display = 'block';

                // Met en évidence le bouton correspondant
                document.getElementById('btn-' + tabId).classList.remove('border-transparent', 'text-gray-500');
                document.getElementById('btn-' + tabId).classList.add('border-blue-500', 'text-blue-600');
            }

            // Exposer la fonction showTab globalement
            window.showTab = showTab;

            // Export PDF
            document.getElementById('export-pdf').addEventListener('click', function () {
                Swal.fire({
                    title: 'Génération du rapport',
                    text: 'Le rapport PDF est en cours de génération...',
                    icon: 'info',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Rediriger vers le script de génération de PDF
                setTimeout(() => {
                    window.location.href = 'generate_report.php?type=projets&code_projet=<?php echo urlencode($codeProjet); ?>&source=<?php echo $isSystemProject ? 'besoin' : 'expression'; ?>';
                }, 1500);
            });

            // Impression des détails
            document.getElementById('print-details').addEventListener('click', function () {
                window.print();
            });
        });
    </script>
</body>

</html>