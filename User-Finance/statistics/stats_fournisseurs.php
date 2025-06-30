<?php
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

// Récupérer la période sélectionnée (par défaut 6 mois)
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$validPeriods = ['3', '6', '12', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = '6';
}

// Récupérer l'année sélectionnée
$currentYear = date('Y');
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;
// Garantir que l'année est valide (entre 2020 et l'année courante)
if ($selectedYear < 2020 || $selectedYear > $currentYear) {
    $selectedYear = $currentYear;
}

try {
    // Nombre total de fournisseurs
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM fournisseurs");
    $stmt->execute();
    $totalSuppliers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Construire les conditions basées sur la période et la date système
    // Pour cela, nous créons différentes versions des conditions en fonction du contexte

    // Condition de base pour le filtrage par date système - obtenue à partir de date_helper.php
    $baseSystemCondition = getFilteredDateCondition('created_at');

    // Condition de période pour les requêtes directes sur achats_materiaux
    $periodCondition = "";
    if ($period != 'all') {
        $periodCondition = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
    }

    // Condition pour l'année sélectionnée
    $yearCondition = "AND YEAR(created_at) = $selectedYear";

    // Fournisseurs actifs (avec commandes récentes)
    // Ici nous utilisons le nom de table complet pour éviter les ambiguïtés
    $activeQuery = "SELECT COUNT(DISTINCT fournisseur) as active 
                   FROM achats_materiaux 
                   WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                   AND " . $baseSystemCondition;

    $activeStmt = $pdo->prepare($activeQuery);
    $activeStmt->execute();
    $activeSuppliers = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'];

    // Montant total des achats avec condition de période et d'année
    $totalQuery = "SELECT COALESCE(SUM(prix_unitaire * quantity), 0) as total 
                  FROM achats_materiaux 
                  WHERE " . $baseSystemCondition;

    if ($period != 'all') {
        $totalQuery .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
    }

    $totalQuery .= " AND YEAR(created_at) = $selectedYear";

    $totalStmt = $pdo->prepare($totalQuery);
    $totalStmt->execute();
    $totalPurchases = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Évolution des achats par mois
    $monthlyQuery = "SELECT 
                      MONTH(created_at) as month, 
                      COUNT(DISTINCT fournisseur) as suppliers_count,
                      COALESCE(SUM(prix_unitaire * quantity), 0) as total_amount
                    FROM achats_materiaux
                    WHERE YEAR(created_at) = $selectedYear
                    GROUP BY MONTH(created_at)
                    ORDER BY month";

    $monthlyStmt = $pdo->prepare($monthlyQuery);
    $monthlyStmt->execute();
    $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Préparer les données pour le graphique mensuel
    $monthlyChartData = [
        'labels' => [],
        'suppliers' => [],
        'amounts' => []
    ];

    $monthNames = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre'
    ];

    // Initialiser avec tous les mois de l'année
    for ($m = 1; $m <= 12; $m++) {
        $monthlyChartData['labels'][] = $monthNames[$m];
        $monthlyChartData['suppliers'][] = 0;
        $monthlyChartData['amounts'][] = 0;
    }

    // Remplir avec les données réelles
    foreach ($monthlyData as $data) {
        $monthIndex = (int) $data['month'] - 1; // 0-indexed
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $monthlyChartData['suppliers'][$monthIndex] = (int) $data['suppliers_count'];
            $monthlyChartData['amounts'][$monthIndex] = (float) $data['total_amount'];
        }
    }

    // Délai moyen de livraison avec condition de période
    $delaiQuery = "SELECT AVG(DATEDIFF(COALESCE(date_reception, CURDATE()), date_achat)) as avg_days
                  FROM achats_materiaux
                  WHERE date_achat IS NOT NULL
                  AND status = 'reçu'
                  AND " . $baseSystemCondition;

    if ($period != 'all') {
        $delaiQuery .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
    }

    $delaiQuery .= " AND YEAR(created_at) = $selectedYear";

    $delaiStmt = $pdo->prepare($delaiQuery);
    $delaiStmt->execute();
    $avgDeliveryTime = $delaiStmt->fetch(PDO::FETCH_ASSOC)['avg_days'];

    // Top fournisseurs par volume d'achat
    $topQuery = "SELECT 
                   f.nom, 
                   fc.categorie,
                   COUNT(am.id) as orders,
                   COALESCE(SUM(am.prix_unitaire * am.quantity), 0) as amount,
                   AVG(DATEDIFF(COALESCE(am.date_reception, CURDATE()), am.date_achat)) as avg_delivery_time,
                   COUNT(CASE WHEN am.status = 'reçu' THEN 1 END) as completed_orders
                  FROM achats_materiaux am
                  LEFT JOIN fournisseurs f ON am.fournisseur = f.nom
                  LEFT JOIN fournisseur_categories fc ON f.id = fc.fournisseur_id
                  WHERE " . getFilteredDateCondition('am.created_at');

    if ($period != 'all') {
        $topQuery .= " AND am.created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
    }

    $topQuery .= " AND YEAR(am.created_at) = $selectedYear";

    $topQuery .= " GROUP BY f.nom, fc.categorie
                  ORDER BY amount DESC
                  LIMIT 10";

    $topStmt = $pdo->prepare($topQuery);
    $topStmt->execute();
    $topSuppliers = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    // Répartition par catégorie de fournisseur
    $categoriesQuery = "SELECT 
                         fc.categorie, 
                         COUNT(DISTINCT f.id) as supplier_count
                        FROM fournisseur_categories fc
                        LEFT JOIN fournisseurs f ON fc.fournisseur_id = f.id
                        GROUP BY fc.categorie";

    $categoriesStmt = $pdo->prepare($categoriesQuery);
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensuite, pour chaque catégorie, calculons le montant total des achats
    foreach ($categories as &$category) {
        // Requête pour obtenir le montant total des achats pour cette catégorie de fournisseur
        $amountQuery = "SELECT COALESCE(SUM(am.prix_unitaire * am.quantity), 0) as amount
                       FROM achats_materiaux am
                       JOIN fournisseurs f ON am.fournisseur = f.nom
                       JOIN fournisseur_categories fc ON f.id = fc.fournisseur_id
                       WHERE fc.categorie = :categorie
                       AND " . getFilteredDateCondition('am.created_at');

        if ($period != 'all') {
            $amountQuery .= " AND am.created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
        }

        $amountQuery .= " AND YEAR(am.created_at) = $selectedYear";

        $amountStmt = $pdo->prepare($amountQuery);
        $amountStmt->bindParam(':categorie', $category['categorie']);
        $amountStmt->execute();
        $result = $amountStmt->fetch(PDO::FETCH_ASSOC);

        $category['amount'] = $result['amount'];
    }

    // Trier les catégories par montant décroissant
    usort($categories, function ($a, $b) {
        return $b['amount'] <=> $a['amount'];
    });

    // Préparer les données pour le graphique de performance des fournisseurs
    $topPerformersData = [];
    foreach (array_slice($topSuppliers, 0, 5) as $supplier) {
        if (!empty($supplier['nom'])) {
            $completionRate = ($supplier['orders'] > 0)
                ? round(($supplier['completed_orders'] / $supplier['orders']) * 100, 1)
                : 0;

            $topPerformersData[] = [
                'nom' => $supplier['nom'],
                'avg_delivery_time' => round($supplier['avg_delivery_time'] ?? 0, 1),
                'completion_rate' => $completionRate,
                'orders' => $supplier['orders'],
                'amount' => $supplier['amount']
            ];
        }
    }

    // NOUVELLE SECTION: Taux de commandes annulées par fournisseur
    $canceledOrdersQuery = "SELECT 
                            f.nom as fournisseur,
                            COUNT(am.id) as total_orders,
                            COUNT(CASE WHEN am.canceled_at IS NOT NULL THEN 1 END) as canceled_orders,
                            COALESCE(SUM(CASE WHEN am.canceled_at IS NOT NULL THEN (am.prix_unitaire * am.quantity) ELSE 0 END), 0) as canceled_amount
                           FROM achats_materiaux am
                           LEFT JOIN fournisseurs f ON am.fournisseur = f.nom
                           WHERE " . getFilteredDateCondition('am.created_at');

    if ($period != 'all') {
        $canceledOrdersQuery .= " AND am.created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
    }

    $canceledOrdersQuery .= " AND YEAR(am.created_at) = $selectedYear";

    $canceledOrdersQuery .= " GROUP BY f.nom
                             HAVING total_orders > 0
                             ORDER BY (COUNT(CASE WHEN am.canceled_at IS NOT NULL THEN 1 END) / COUNT(am.id)) DESC
                             LIMIT 5";

    $canceledOrdersStmt = $pdo->prepare($canceledOrdersQuery);
    $canceledOrdersStmt->execute();
    $canceledOrdersStats = $canceledOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcul des taux d'annulation
    foreach ($canceledOrdersStats as &$supplierStats) {
        $supplierStats['cancellation_rate'] = ($supplierStats['total_orders'] > 0)
            ? round(($supplierStats['canceled_orders'] / $supplierStats['total_orders']) * 100, 1)
            : 0;
    }

    // Évolution des commandes et des annulations par mois
    $monthlyOrdersQuery = "SELECT 
                          MONTH(created_at) as month,
                          COUNT(*) as total_orders,
                          COUNT(CASE WHEN canceled_at IS NOT NULL THEN 1 END) as canceled_orders
                         FROM achats_materiaux
                         WHERE YEAR(created_at) = $selectedYear
                         GROUP BY MONTH(created_at)
                         ORDER BY month";

    $monthlyOrdersStmt = $pdo->prepare($monthlyOrdersQuery);
    $monthlyOrdersStmt->execute();
    $monthlyOrdersData = $monthlyOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Préparer les données pour le graphique d'évolution des commandes
    $monthlyOrdersChartData = [
        'labels' => [],
        'total_orders' => [],
        'canceled_orders' => []
    ];

    // Initialiser avec tous les mois de l'année
    for ($m = 1; $m <= 12; $m++) {
        $monthlyOrdersChartData['labels'][] = $monthNames[$m];
        $monthlyOrdersChartData['total_orders'][] = 0;
        $monthlyOrdersChartData['canceled_orders'][] = 0;
    }

    // Remplir avec les données réelles
    foreach ($monthlyOrdersData as $data) {
        $monthIndex = (int) $data['month'] - 1; // 0-indexed
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $monthlyOrdersChartData['total_orders'][$monthIndex] = (int) $data['total_orders'];
            $monthlyOrdersChartData['canceled_orders'][$monthIndex] = (int) $data['canceled_orders'];
        }
    }

} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
}

// Vérifier si la table supplier_returns existe
try {
    $checkTableQuery = "SHOW TABLES LIKE 'supplier_returns'";
    $checkTableStmt = $pdo->prepare($checkTableQuery);
    $checkTableStmt->execute();
    $supplierReturnsTableExists = $checkTableStmt->rowCount() > 0;

    if ($supplierReturnsTableExists) {
        // Requête pour obtenir les fournisseurs avec le plus de retours
        $topRetourQuery = "SELECT 
                            supplier_name, 
                            COUNT(*) as returns_count,
                            COALESCE(SUM(sr.quantity * 
                                (SELECT AVG(prix_unitaire) FROM expression_dym WHERE designation = p.product_name)
                            ), 0) as returns_amount,
                            GROUP_CONCAT(DISTINCT reason) as reasons
                        FROM supplier_returns sr
                        JOIN products p ON sr.product_id = p.id
                        WHERE " . getFilteredDateCondition('sr.created_at');

        if ($period != 'all') {
            $topRetourQuery .= " AND sr.created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
        }

        $topRetourQuery .= " AND YEAR(sr.created_at) = $selectedYear";

        $topRetourQuery .= " GROUP BY supplier_name ORDER BY returns_count DESC LIMIT 5";

        $topRetourStmt = $pdo->prepare($topRetourQuery);
        $topRetourStmt->execute();
        $topRetours = $topRetourStmt->fetchAll(PDO::FETCH_ASSOC);

        // Répartition des retours par motif
        $reasonsQuery = "SELECT 
                        reason, 
                        COUNT(*) as count
                       FROM supplier_returns
                       WHERE " . getFilteredDateCondition('created_at');

        if ($period != 'all') {
            $reasonsQuery .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
        }

        $reasonsQuery .= " AND YEAR(created_at) = $selectedYear";

        $reasonsQuery .= " GROUP BY reason
                          ORDER BY count DESC";

        $reasonsStmt = $pdo->prepare($reasonsQuery);
        $reasonsStmt->execute();
        $returnReasons = $reasonsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Préparer les données pour le graphique en donut
        $reasonsChartData = [
            'labels' => [],
            'data' => []
        ];

        foreach ($returnReasons as $reason) {
            $reasonsChartData['labels'][] = $reason['reason'];
            $reasonsChartData['data'][] = (int) $reason['count'];
        }
    } else {
        $topRetours = [];
        $reasonsChartData = ['labels' => [], 'data' => []];
    }
} catch (PDOException $e) {
    $topRetours = [];
    $reasonsChartData = ['labels' => [], 'data' => []];
}

// NOUVELLE SECTION: Statistiques des délais de livraison
try {
    $deliveryTimeStatsQuery = "SELECT 
                             AVG(DATEDIFF(date_reception, date_achat)) as avg_time,
                             MIN(DATEDIFF(date_reception, date_achat)) as min_time,
                             MAX(DATEDIFF(date_reception, date_achat)) as max_time,
                             STDDEV(DATEDIFF(date_reception, date_achat)) as std_dev_time
                            FROM achats_materiaux
                            WHERE date_reception IS NOT NULL 
                            AND date_achat IS NOT NULL
                            AND status = 'reçu'
                            AND " . getFilteredDateCondition();

    if ($period != 'all') {
        $deliveryTimeStatsQuery .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
    }

    $deliveryTimeStatsQuery .= " AND YEAR(created_at) = $selectedYear";

    $deliveryTimeStatsStmt = $pdo->prepare($deliveryTimeStatsQuery);
    $deliveryTimeStatsStmt->execute();
    $deliveryTimeStats = $deliveryTimeStatsStmt->fetch(PDO::FETCH_ASSOC);

    // Distribution des délais par tranche
    $deliveryTimeDistributionQuery = "SELECT 
                                     CASE 
                                        WHEN DATEDIFF(date_reception, date_achat) < 3 THEN 'Moins de 3 jours'
                                        WHEN DATEDIFF(date_reception, date_achat) BETWEEN 3 AND 7 THEN '3-7 jours'
                                        WHEN DATEDIFF(date_reception, date_achat) BETWEEN 8 AND 14 THEN '1-2 semaines'
                                        WHEN DATEDIFF(date_reception, date_achat) BETWEEN 15 AND 30 THEN '2-4 semaines'
                                        ELSE 'Plus d''un mois'
                                     END AS time_range,
                                     COUNT(*) as count
                                    FROM achats_materiaux
                                    WHERE date_reception IS NOT NULL 
                                    AND date_achat IS NOT NULL
                                    AND status = 'reçu'
                                    AND " . getFilteredDateCondition();

    if ($period != 'all') {
        $deliveryTimeDistributionQuery .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
    }

    $deliveryTimeDistributionQuery .= " AND YEAR(created_at) = $selectedYear";

    $deliveryTimeDistributionQuery .= " GROUP BY time_range
                                       ORDER BY CASE 
                                            WHEN time_range = 'Moins de 3 jours' THEN 1
                                            WHEN time_range = '3-7 jours' THEN 2
                                            WHEN time_range = '1-2 semaines' THEN 3
                                            WHEN time_range = '2-4 semaines' THEN 4
                                            ELSE 5
                                        END";

    $deliveryTimeDistributionStmt = $pdo->prepare($deliveryTimeDistributionQuery);
    $deliveryTimeDistributionStmt->execute();
    $deliveryTimeDistribution = $deliveryTimeDistributionStmt->fetchAll(PDO::FETCH_ASSOC);

    // Préparer les données pour le graphique de distribution
    $distributionChartData = [
        'labels' => [],
        'data' => []
    ];

    foreach ($deliveryTimeDistribution as $distribution) {
        $distributionChartData['labels'][] = $distribution['time_range'];
        $distributionChartData['data'][] = (int) $distribution['count'];
    }

    // TOP 10 Fournisseurs par délai de livraison (les plus rapides)
    $topDeliveryTimeQuery = "SELECT 
                            f.nom as supplier_name,
                            AVG(DATEDIFF(date_reception, date_achat)) as avg_delivery_time,
                            COUNT(*) as orders_count
                           FROM achats_materiaux am
                           JOIN fournisseurs f ON am.fournisseur = f.nom
                           WHERE date_reception IS NOT NULL 
                           AND date_achat IS NOT NULL
                           AND status = 'reçu'
                           AND " . getFilteredDateCondition('am.created_at');

    if ($period != 'all') {
        $topDeliveryTimeQuery .= " AND am.created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
    }

    $topDeliveryTimeQuery .= " AND YEAR(am.created_at) = $selectedYear";

    $topDeliveryTimeQuery .= " GROUP BY f.nom
                             HAVING COUNT(*) >= 5 /* Au moins 5 commandes pour avoir des statistiques pertinentes */
                             ORDER BY avg_delivery_time ASC
                             LIMIT 10";

    $topDeliveryTimeStmt = $pdo->prepare($topDeliveryTimeQuery);
    $topDeliveryTimeStmt->execute();
    $topDeliveryTimeSuppliers = $topDeliveryTimeStmt->fetchAll(PDO::FETCH_ASSOC);

    // Préparer les données pour le graphique des délais de livraison
    $deliveryTimeChartData = [
        'labels' => [],
        'data' => []
    ];

    foreach ($topDeliveryTimeSuppliers as $supplier) {
        $deliveryTimeChartData['labels'][] = $supplier['supplier_name'];
        $deliveryTimeChartData['data'][] = round($supplier['avg_delivery_time'], 1);
    }

} catch (PDOException $e) {
    $deliveryTimeStats = [
        'avg_time' => 0,
        'min_time' => 0,
        'max_time' => 0,
        'std_dev_time' => 0
    ];
    $deliveryTimeDistribution = [];
    $distributionChartData = ['labels' => [], 'data' => []];
    $topDeliveryTimeSuppliers = [];
    $deliveryTimeChartData = ['labels' => [], 'data' => []];
}

// Fonction pour formater les nombres
function formatNumber($number)
{
    return number_format($number, 0, ',', ' ');
}

// Calculer les tendances pour certaines métriques clés
// Comparer les données actuelles avec la période précédente
try {
    // Tendance du montant total des achats
    $previousPeriodQuery = "SELECT COALESCE(SUM(prix_unitaire * quantity), 0) as total 
                           FROM achats_materiaux 
                           WHERE " . $baseSystemCondition;

    if ($period != 'all') {
        $previousPeriodQuery .= " AND created_at >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL $period MONTH), INTERVAL $period MONTH)";
        $previousPeriodQuery .= " AND created_at < DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
    } else {
        $previousPeriodQuery .= " AND YEAR(created_at) = " . ($selectedYear - 1);
    }

    $previousPeriodStmt = $pdo->prepare($previousPeriodQuery);
    $previousPeriodStmt->execute();
    $previousTotalPurchases = $previousPeriodStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Calculer le pourcentage de variation
    $purchaseTrend = 0;
    if ($previousTotalPurchases > 0) {
        $purchaseTrend = (($totalPurchases - $previousTotalPurchases) / $previousTotalPurchases) * 100;
    }

    // Tendance du nombre de fournisseurs actifs
    $previousActiveSuppliersQuery = "SELECT COUNT(DISTINCT fournisseur) as active 
                                    FROM achats_materiaux 
                                    WHERE created_at >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), INTERVAL 6 MONTH)
                                    AND created_at < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                                    AND " . $baseSystemCondition;

    $previousActiveSuppliersStmt = $pdo->prepare($previousActiveSuppliersQuery);
    $previousActiveSuppliersStmt->execute();
    $previousActiveSuppliers = $previousActiveSuppliersStmt->fetch(PDO::FETCH_ASSOC)['active'] ?? 0;

    // Calculer le pourcentage de variation
    $activeSuppliersTrend = 0;
    if ($previousActiveSuppliers > 0) {
        $activeSuppliersTrend = (($activeSuppliers - $previousActiveSuppliers) / $previousActiveSuppliers) * 100;
    }

    // Tendance du délai moyen de livraison
    $previousDelaiQuery = "SELECT AVG(DATEDIFF(COALESCE(date_reception, CURDATE()), date_achat)) as avg_days
                          FROM achats_materiaux
                          WHERE date_achat IS NOT NULL
                          AND status = 'reçu'
                          AND " . $baseSystemCondition;

    if ($period != 'all') {
        $previousDelaiQuery .= " AND created_at >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL $period MONTH), INTERVAL $period MONTH)";
        $previousDelaiQuery .= " AND created_at < DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
    } else {
        $previousDelaiQuery .= " AND YEAR(created_at) = " . ($selectedYear - 1);
    }

    $previousDelaiStmt = $pdo->prepare($previousDelaiQuery);
    $previousDelaiStmt->execute();
    $previousAvgDeliveryTime = $previousDelaiStmt->fetch(PDO::FETCH_ASSOC)['avg_days'] ?? 0;

    // Calculer le pourcentage de variation (attention, ici une diminution est positive)
    $deliveryTimeTrend = 0;
    if ($previousAvgDeliveryTime > 0) {
        $deliveryTimeTrend = (($previousAvgDeliveryTime - $avgDeliveryTime) / $previousAvgDeliveryTime) * 100;
    }

} catch (PDOException $e) {
    $purchaseTrend = 0;
    $activeSuppliersTrend = 0;
    $deliveryTimeTrend = 0;
}

// Préparer les données pour les graphiques en JSON
$categoriesChartData = [
    'labels' => array_map(function ($cat) {
        return $cat['categorie'] ?: 'Non définie'; }, $categories),
    'data' => array_map(function ($cat) {
        return (float) $cat['amount']; }, $categories),
];

// Score de performance global des fournisseurs
$globalPerformanceScore = 0;
$performanceFactors = [];

if (!empty($topPerformersData)) {
    // Moyenne des taux de complétion
    $avgCompletionRate = array_sum(array_column($topPerformersData, 'completion_rate')) / count($topPerformersData);

    // Score de délai inversé (plus le délai est court, meilleur est le score) - normalisé sur 100
    $maxDeliveryTime = max(array_column($topPerformersData, 'avg_delivery_time'));
    $avgDeliveryScore = 0;
    if ($maxDeliveryTime > 0) {
        $deliveryScores = array_map(function ($supplier) use ($maxDeliveryTime) {
            return (1 - ($supplier['avg_delivery_time'] / ($maxDeliveryTime * 1.5))) * 100;
        }, $topPerformersData);
        $avgDeliveryScore = array_sum($deliveryScores) / count($deliveryScores);
    }

    // Score de cancellation inversé (plus le taux d'annulation est bas, meilleur est le score)
    $avgCancellationRate = 0;
    if (!empty($canceledOrdersStats)) {
        $avgCancellationRate = array_sum(array_column($canceledOrdersStats, 'cancellation_rate')) / count($canceledOrdersStats);
        $avgCancellationScore = 100 - $avgCancellationRate; // Inverser pour avoir un bon score quand le taux est bas
    } else {
        $avgCancellationScore = 100; // Si pas d'annulations, score parfait
    }

    // Calculer le score global (moyenne pondérée)
    $globalPerformanceScore = ($avgCompletionRate * 0.5) + ($avgDeliveryScore * 0.3) + ($avgCancellationScore * 0.2);
    $globalPerformanceScore = round($globalPerformanceScore, 1);

    // Détails des facteurs pour l'affichage
    $performanceFactors = [
        [
            'name' => 'Taux de complétion',
            'value' => round($avgCompletionRate, 1),
            'weight' => '50%',
            'color' => 'green'
        ],
        [
            'name' => 'Rapidité de livraison',
            'value' => round($avgDeliveryScore, 1),
            'weight' => '30%',
            'color' => 'blue'
        ],
        [
            'name' => 'Fiabilité (non-annulation)',
            'value' => round($avgCancellationScore, 1),
            'weight' => '20%',
            'color' => 'purple'
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des Fournisseurs | Service Achat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Styles spécifiques à cette page */
        body {
            font-family: 'Inter', sans-serif;
        }

        .dashboard-card {
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .stats-card {
            transition: all 0.3s ease;
            border-radius: 0.75rem;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .supplier-card {
            border-left: 4px solid #4361ee;
            transition: all 0.3s ease;
        }

        .supplier-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .mini-chart-container {
            position: relative;
            height: 120px;
            width: 100%;
        }

        .status-badge {
            display: inline-block;
            border-radius: 9999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .toggle-btn {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .toggle-btn.active {
            background-color: #4299e1;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Indicateur de tendance */
        .trend-indicator {
            display: inline-flex;
            align-items: center;
            font-weight: 500;
            font-size: 0.875rem;
            margin-left: 0.5rem;
        }

        .trend-up {
            color: #10b981;
        }

        .trend-down {
            color: #ef4444;
        }

        .trend-neutral {
            color: #6b7280;
        }

        /* Jauge de performance */
        .gauge-container {
            position: relative;
            width: 210px;
            height: 105px;
            margin: 0 auto;
            overflow: hidden;
        }

        .gauge-bg {
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 100%;
            border: 10px solid #f3f4f6;
            top: 0;
            left: 5px;
        }

        .gauge-fill {
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 100%;
            background: conic-gradient(#4361ee var(--gauge-value),
                    #f3f4f6 var(--gauge-value));
            clip-path: polygon(100px 100px,
                    200px 100px,
                    200px 0,
                    0 0,
                    0 100px);
            top: 0;
            left: 5px;
            transform-origin: center bottom;
        }

        .gauge-center {
            position: absolute;
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 100%;
            top: 40px;
            left: 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1) inset;
        }

        .gauge-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .gauge-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        /* Animation de charge */
        @keyframes loadingPulse {
            0% {
                opacity: 0.6;
            }

            50% {
                opacity: 1;
            }

            100% {
                opacity: 0.6;
            }
        }

        .loading {
            animation: loadingPulse 1.5s infinite;
        }

        /* Table avec lignes alternées et bordures arrondies */
        .alt-table {
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .alt-table th {
            background-color: #f8fafc;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #64748b;
            padding: 0.75rem 1rem;
            text-align: left;
        }

        .alt-table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }

        .alt-table tbody tr:nth-child(odd) {
            background-color: #f9fafb;
        }

        .alt-table tbody tr:nth-child(even) {
            background-color: #ffffff;
        }

        .alt-table tbody tr:hover {
            background-color: #f1f5f9;
        }

        /* Catégories badge avec couleurs */
        .category-badge {
            display: inline-flex;
            padding: 0.25rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        /* Étiquettes de la légende du graphique */
        .chart-legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.75rem;
        }

        .chart-legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
            margin-right: 0.25rem;
        }

        /* Gestion des KPI Cards améliorées */
        .kpi-card {
            position: relative;
            overflow: hidden;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .kpi-card-icon {
            position: absolute;
            right: -15px;
            top: -15px;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.15;
            transform: rotate(15deg);
        }

        .kpi-card-icon .material-icons {
            font-size: 3rem;
            color: white;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../../components/navbar_finance.php'; ?>

        <main class="flex-1 p-6">
            <div class="bg-white shadow-sm rounded-lg p-4 mb-6 flex flex-wrap justify-between items-center">
                <div class="flex items-center mb-2 md:mb-0">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-2">
                        <span class="material-icons">arrow_back</span>
                    </a>
                    <h1 class="text-xl font-bold text-gray-800">Statistiques des Fournisseurs</h1>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <form action="" method="GET" class="flex items-center">
                        <label for="period-select" class="mr-2 text-gray-700">Période:</label>
                        <select id="period-select" name="period"
                            class="bg-white border border-gray-300 rounded-md py-1 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            onchange="this.form.submit()">
                            <option value="3" <?php echo $period == '3' ? 'selected' : ''; ?>>3 derniers mois</option>
                            <option value="6" <?php echo $period == '6' ? 'selected' : ''; ?>>6 derniers mois</option>
                            <option value="12" <?php echo $period == '12' ? 'selected' : ''; ?>>12 derniers mois</option>
                            <option value="all" <?php echo $period == 'all' ? 'selected' : ''; ?>>Tout l'historique
                            </option>
                        </select>

                        <label for="year-select" class="ml-4 mr-2 text-gray-700">Année:</label>
                        <select id="year-select" name="year"
                            class="bg-white border border-gray-300 rounded-md py-1 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            onchange="this.form.submit()">
                            <?php
                            for ($year = date('Y'); $year >= 2020; $year--) {
                                echo '<option value="' . $year . '" ' . ($selectedYear == $year ? 'selected' : '') . '>' . $year . '</option>';
                            }
                            ?>
                        </select>
                    </form>

                    <button id="export-pdf"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded flex items-center">
                        <span class="material-icons mr-2">picture_as_pdf</span>
                        Exporter PDF
                    </button>

                    <div class="flex items-center bg-gray-100 px-4 py-2 rounded">
                        <span class="material-icons mr-2">event</span>
                        <span id="date-time-display"></span>
                    </div>
                </div>
            </div>

            <?php if (isset($errorMessage)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <p><?php echo $errorMessage; ?></p>
                </div>
            <?php else: ?>
                <!-- Carte de performance globale -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex flex-col lg:flex-row items-center">
                        <div class="w-full lg:w-1/3 mb-6 lg:mb-0 flex flex-col items-center">
                            <h2 class="text-xl font-semibold text-gray-800 mb-2">Performance Globale des Fournisseurs</h2>

                            <!-- Jauge de performance -->
                            <div class="gauge-container">
                                <div class="gauge-bg"></div>
                                <div class="gauge-fill" style="--gauge-value: <?php echo $globalPerformanceScore; ?>%">
                                </div>
                                <div class="gauge-center">
                                    <div class="gauge-value"><?php echo $globalPerformanceScore; ?>/100</div>
                                    <div class="gauge-label">Score global</div>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <?php
                                $performanceLevel = '';
                                $performanceColor = '';

                                if ($globalPerformanceScore >= 90) {
                                    $performanceLevel = 'Excellent';
                                    $performanceColor = 'bg-green-100 text-green-800';
                                } elseif ($globalPerformanceScore >= 75) {
                                    $performanceLevel = 'Bon';
                                    $performanceColor = 'bg-blue-100 text-blue-800';
                                } elseif ($globalPerformanceScore >= 60) {
                                    $performanceLevel = 'Satisfaisant';
                                    $performanceColor = 'bg-yellow-100 text-yellow-800';
                                } else {
                                    $performanceLevel = 'À améliorer';
                                    $performanceColor = 'bg-red-100 text-red-800';
                                }
                                ?>
                                <span class="<?php echo $performanceColor; ?> px-3 py-1 rounded-full text-sm font-semibold">
                                    <?php echo $performanceLevel; ?>
                                </span>
                            </div>
                        </div>

                        <div class="w-full lg:w-2/3 lg:pl-8">
                            <h3 class="text-lg font-medium text-gray-700 mb-3">Facteurs de performance</h3>

                            <!-- Scores détaillés -->
                            <div class="space-y-4">
                                <?php foreach ($performanceFactors as $factor): ?>
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <div class="flex items-center">
                                                <span class="font-medium text-sm"><?php echo $factor['name']; ?></span>
                                                <span
                                                    class="ml-2 text-xs text-gray-500">(<?php echo $factor['weight']; ?>)</span>
                                            </div>
                                            <span class="text-sm font-semibold"><?php echo $factor['value']; ?>/100</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <?php
                                            $barColor = '';
                                            switch ($factor['color']) {
                                                case 'green':
                                                    $barColor = 'bg-green-500';
                                                    break;
                                                case 'blue':
                                                    $barColor = 'bg-blue-500';
                                                    break;
                                                case 'purple':
                                                    $barColor = 'bg-purple-500';
                                                    break;
                                                default:
                                                    $barColor = 'bg-gray-500';
                                            }
                                            ?>
                                            <div class="<?php echo $barColor; ?> h-2 rounded-full"
                                                style="width: <?php echo $factor['value']; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-6">
                                <h3 class="text-lg font-medium text-gray-700 mb-2">Recommandations</h3>

                                <ul class="list-disc list-inside text-sm space-y-1 text-gray-600">
                                    <?php if (isset($performanceFactors[0]) && $performanceFactors[0]['value'] < 70): ?>
                                        <li>Améliorer le taux de complétion des commandes en travaillant plus étroitement avec
                                            les fournisseurs principaux</li>
                                    <?php endif; ?>

                                    <?php if (isset($performanceFactors[1]) && $performanceFactors[1]['value'] < 70): ?>
                                        <li>Négocier des délais de livraison plus courts et mettre en place un système de suivi
                                            des commandes plus rigoureux</li>
                                    <?php endif; ?>

                                    <?php if (isset($performanceFactors[2]) && $performanceFactors[2]['value'] < 70): ?>
                                        <li>Réduire le taux d'annulation en améliorant la communication et la planification avec
                                            les fournisseurs</li>
                                    <?php endif; ?>

                                    <?php if ($globalPerformanceScore > 85): ?>
                                        <li>Maintenir les bonnes pratiques actuelles et renforcer les partenariats avec les
                                            fournisseurs les plus performants</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiques globales en KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- KPI 1: Fournisseurs totaux -->
                    <div class="kpi-card bg-white p-6 shadow-sm">
                        <div class="kpi-card-icon bg-purple-600">
                            <span class="material-icons">business</span>
                        </div>
                        <p class="text-sm text-gray-500">Total Fournisseurs</p>
                        <h3 class="text-2xl font-bold mt-1"><?php echo formatNumber($totalSuppliers); ?></h3>
                        <p class="text-xs text-gray-500 mt-2">Partenaires enregistrés</p>
                    </div>

                    <!-- KPI 2: Fournisseurs actifs -->
                    <div class="kpi-card bg-white p-6 shadow-sm">
                        <div class="kpi-card-icon bg-green-600">
                            <span class="material-icons">check_circle</span>
                        </div>
                        <p class="text-sm text-gray-500">Fournisseurs actifs</p>
                        <h3 class="text-2xl font-bold mt-1">
                            <?php echo formatNumber($activeSuppliers); ?>
                            <?php if ($activeSuppliersTrend != 0): ?>
                                <span
                                    class="trend-indicator <?php echo $activeSuppliersTrend > 0 ? 'trend-up' : 'trend-down'; ?>">
                                    <span class="material-icons text-sm">
                                        <?php echo $activeSuppliersTrend > 0 ? 'trending_up' : 'trending_down'; ?>
                                    </span>
                                    <?php echo abs(round($activeSuppliersTrend)); ?>%
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p class="text-xs text-gray-500 mt-2">Sur les 6 derniers mois</p>
                    </div>

                    <!-- KPI 3: Montant total des achats -->
                    <div class="kpi-card bg-white p-6 shadow-sm">
                        <div class="kpi-card-icon bg-blue-600">
                            <span class="material-icons">payments</span>
                        </div>
                        <p class="text-sm text-gray-500">Volume d'achats</p>
                        <h3 class="text-2xl font-bold mt-1">
                            <?php echo formatNumber($totalPurchases); ?> FCFA
                            <?php if ($purchaseTrend != 0): ?>
                                <span class="trend-indicator <?php echo $purchaseTrend > 0 ? 'trend-up' : 'trend-down'; ?>">
                                    <span class="material-icons text-sm">
                                        <?php echo $purchaseTrend > 0 ? 'trending_up' : 'trending_down'; ?>
                                    </span>
                                    <?php echo abs(round($purchaseTrend)); ?>%
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p class="text-xs text-gray-500 mt-2">
                            <?php
                            if ($period == 'all')
                                echo "Total $selectedYear";
                            else
                                echo "Sur les $period derniers mois";
                            ?>
                        </p>
                    </div>

                    <!-- KPI 4: Délai moyen de livraison -->
                    <div class="kpi-card bg-white p-6 shadow-sm">
                        <div class="kpi-card-icon bg-orange-600">
                            <span class="material-icons">schedule</span>
                        </div>
                        <p class="text-sm text-gray-500">Délai moyen livraison</p>
                        <h3 class="text-2xl font-bold mt-1">
                            <?php echo round($avgDeliveryTime, 1); ?> jours
                            <?php if ($deliveryTimeTrend != 0): ?>
                                <span class="trend-indicator <?php echo $deliveryTimeTrend > 0 ? 'trend-up' : 'trend-down'; ?>">
                                    <span class="material-icons text-sm">
                                        <?php echo $deliveryTimeTrend > 0 ? 'trending_up' : 'trending_down'; ?>
                                    </span>
                                    <?php echo abs(round($deliveryTimeTrend)); ?>%
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p class="text-xs text-gray-500 mt-2">Entre commande et réception</p>
                    </div>
                </div>

                <!-- Onglets de navigation -->
                <div class="bg-white p-4 shadow-sm rounded-lg mb-6">
                    <div class="flex space-x-4 border-b border-gray-200 overflow-x-auto pb-1">
                        <button
                            class="toggle-btn py-2 px-4 border-b-2 border-blue-500 text-blue-600 font-medium whitespace-nowrap"
                            data-tab="tab-overview">Vue d'ensemble</button>
                        <button
                            class="toggle-btn py-2 px-4 border-b-2 border-transparent hover:text-blue-500 hover:border-blue-300 whitespace-nowrap"
                            data-tab="tab-performance">Performance</button>
                        <button
                            class="toggle-btn py-2 px-4 border-b-2 border-transparent hover:text-blue-500 hover:border-blue-300 whitespace-nowrap"
                            data-tab="tab-delivery-time">Délais de livraison</button>
                        <button
                            class="toggle-btn py-2 px-4 border-b-2 border-transparent hover:text-blue-500 hover:border-blue-300 whitespace-nowrap"
                            data-tab="tab-categories">Catégories</button>
                        <button
                            class="toggle-btn py-2 px-4 border-b-2 border-transparent hover:text-blue-500 hover:border-blue-300 whitespace-nowrap"
                            data-tab="tab-returns">Retours</button>
                    </div>
                </div>

                <!-- Contenu de l'onglet "Vue d'ensemble" -->
                <div id="tab-overview" class="tab-content active">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Graphique: Évolution des achats mensuelle -->
                        <div class="bg-white p-6 shadow-sm rounded-lg">
                            <h2 class="text-lg font-semibold mb-4">Évolution mensuelle des achats
                                (<?php echo $selectedYear; ?>)</h2>
                            <div class="chart-container">
                                <canvas id="monthlyPurchasesChart"></canvas>
                            </div>
                        </div>

                        <!-- Graphique: Répartition par catégorie -->
                        <div class="bg-white p-6 shadow-sm rounded-lg">
                            <h2 class="text-lg font-semibold mb-4">Répartition des achats par catégorie de fournisseur</h2>
                            <div class="chart-container">
                                <canvas id="categoriesChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Évolution des commandes et annulations -->
                    <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                        <h2 class="text-lg font-semibold mb-4">Évolution des commandes et annulations
                            (<?php echo $selectedYear; ?>)</h2>
                        <div class="chart-container">
                            <canvas id="ordersEvolutionChart"></canvas>
                        </div>
                    </div>

                    <!-- Top fournisseurs par volume d'achat -->
                    <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold">Top fournisseurs par volume d'achat</h2>
                            <div class="text-sm text-gray-500">Période: <?php
                            if ($period == 'all') {
                                echo "Année $selectedYear";
                            } else {
                                echo "Les $period derniers mois";
                            }
                            ?></div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="alt-table min-w-full bg-white">
                                <thead>
                                    <tr>
                                        <th class="py-2 px-4">Fournisseur</th>
                                        <th class="py-2 px-4">Catégorie</th>
                                        <th class="py-2 px-4">Commandes</th>
                                        <th class="py-2 px-4">Délai moyen</th>
                                        <th class="py-2 px-4">Taux livraison</th>
                                        <th class="py-2 px-4">Montant total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (empty($topSuppliers)):
                                        ?>
                                        <tr>
                                            <td colspan="6" class="py-4 px-4 text-gray-500 text-center">Aucune donnée disponible
                                            </td>
                                        </tr>
                                        <?php
                                    else:
                                        foreach ($topSuppliers as $index => $supplier):
                                            $deliveryRate = ($supplier['orders'] > 0)
                                                ? round(($supplier['completed_orders'] / $supplier['orders']) * 100, 1)
                                                : 0;

                                            // Déterminer la classe pour le taux de livraison
                                            $rateClass = '';
                                            if ($deliveryRate >= 90)
                                                $rateClass = 'text-green-600';
                                            elseif ($deliveryRate >= 75)
                                                $rateClass = 'text-yellow-600';
                                            else
                                                $rateClass = 'text-red-600';

                                            // Définir une couleur de fond pour les lignes alternées
                                            $rowBg = $index % 2 === 0 ? 'bg-white' : 'bg-gray-50';

                                            // Mettre en surbrillance le top 3
                                            $isTopThree = $index < 3;
                                            $rowClass = $isTopThree ? $rowBg . ' border-l-4 border-blue-400' : $rowBg;
                                            ?>
                                            <tr class="<?php echo $rowClass; ?> hover:bg-blue-50 transition-colors">
                                                <td class="py-3 px-4 font-medium">
                                                    <?php if ($isTopThree): ?>
                                                        <div class="flex items-center">
                                                            <span
                                                                class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-800 text-xs font-bold mr-2">
                                                                <?php echo $index + 1; ?>
                                                            </span>
                                                            <?php echo htmlspecialchars($supplier['nom'] ?? 'Non spécifié'); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($supplier['nom'] ?? 'Non spécifié'); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <?php
                                                    $categoryName = htmlspecialchars($supplier['categorie'] ?? 'Non définie');

                                                    // Déterminer la couleur du badge en fonction de la catégorie
                                                    $badgeClass = 'bg-gray-100 text-gray-800'; // Par défaut
                                                    switch ($categoryName) {
                                                        case 'Matériaux ferreux':
                                                            $badgeClass = 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'Matériaux non ferreux':
                                                            $badgeClass = 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'Électrique':
                                                            $badgeClass = 'bg-yellow-100 text-yellow-800';
                                                            break;
                                                        case 'Plomberie':
                                                            $badgeClass = 'bg-purple-100 text-purple-800';
                                                            break;
                                                        case 'Outillage':
                                                            $badgeClass = 'bg-red-100 text-red-800';
                                                            break;
                                                        case 'Quincaillerie':
                                                            $badgeClass = 'bg-indigo-100 text-indigo-800';
                                                            break;
                                                        case 'Peinture':
                                                            $badgeClass = 'bg-pink-100 text-pink-800';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="category-badge <?php echo $badgeClass; ?>">
                                                        <?php echo $categoryName; ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4"><?php echo formatNumber($supplier['orders']); ?></td>
                                                <td class="py-3 px-4">
                                                    <?php echo round($supplier['avg_delivery_time'] ?? 0, 1); ?> jours
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="<?php echo $rateClass; ?> font-medium">
                                                        <?php echo $deliveryRate; ?>%
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 font-semibold">
                                                    <?php echo formatNumber($supplier['amount']); ?> FCFA
                                                </td>
                                            </tr>
                                            <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Contenu de l'onglet "Performance" -->
                <div id="tab-performance" class="tab-content">
                    <!-- Analyse des prix par fournisseur -->
                    <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                        <h2 class="text-lg font-semibold mb-4">Analyse de la performance des fournisseurs</h2>

                        <!-- Performance radar chart -->
                        <div class="chart-container">
                            <canvas id="supplierPerformanceChart"></canvas>
                        </div>

                        <!-- Explication des métriques -->
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="flex items-center mb-2">
                                    <span class="material-icons text-blue-600 mr-2">schedule</span>
                                    <h3 class="font-medium">Délai de livraison</h3>
                                </div>
                                <p class="text-sm text-gray-600">Nombre moyen de jours entre la commande et la réception.
                                    Plus le délai est court, meilleure est la performance.</p>
                            </div>

                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="flex items-center mb-2">
                                    <span class="material-icons text-green-600 mr-2">done_all</span>
                                    <h3 class="font-medium">Taux de complétion</h3>
                                </div>
                                <p class="text-sm text-gray-600">Pourcentage de commandes livrées avec succès par rapport au
                                    total des commandes passées.</p>
                            </div>

                            <div class="bg-purple-50 p-4 rounded-lg">
                                <div class="flex items-center mb-2">
                                    <span class="material-icons text-purple-600 mr-2">payments</span>
                                    <h3 class="font-medium">Volume d'achats</h3>
                                </div>
                                <p class="text-sm text-gray-600">Montant total des achats effectués auprès du fournisseur
                                    sur la période sélectionnée.</p>
                            </div>
                        </div>
                    </div>

                    <!-- NOUVELLE SECTION: Taux d'annulation de commandes par fournisseur -->
                    <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                        <h2 class="text-lg font-semibold mb-4">Taux d'annulation de commandes par fournisseur</h2>

                        <div class="overflow-x-auto">
                            <table class="alt-table min-w-full bg-white">
                                <thead>
                                    <tr>
                                        <th class="py-2 px-4">Fournisseur</th>
                                        <th class="py-2 px-4">Total commandes</th>
                                        <th class="py-2 px-4">Commandes annulées</th>
                                        <th class="py-2 px-4">Taux d'annulation</th>
                                        <th class="py-2 px-4">Montant annulé</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($canceledOrdersStats)): ?>
                                        <tr>
                                            <td colspan="5" class="py-4 px-4 text-gray-500 text-center">Aucune donnée
                                                d'annulation
                                                disponible</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($canceledOrdersStats as $index => $stats):
                                            // Déterminer la classe en fonction du taux d'annulation
                                            $cancellationClass = '';
                                            if ($stats['cancellation_rate'] <= 5)
                                                $cancellationClass = 'text-green-600';
                                            elseif ($stats['cancellation_rate'] <= 15)
                                                $cancellationClass = 'text-yellow-600';
                                            else
                                                $cancellationClass = 'text-red-600';

                                            $rowBg = $index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                            ?>
                                            <tr class="<?php echo $rowBg; ?> hover:bg-red-50 transition-colors">
                                                <td class="py-3 px-4">
                                                    <?php echo htmlspecialchars($stats['fournisseur'] ?? 'Non spécifié'); ?>
                                                </td>
                                                <td class="py-3 px-4"><?php echo formatNumber($stats['total_orders']); ?></td>
                                                <td class="py-3 px-4"><?php echo formatNumber($stats['canceled_orders']); ?></td>
                                                <td class="py-3 px-4">
                                                    <div class="flex items-center">
                                                        <div class="w-16 bg-gray-200 rounded-full h-2.5 mr-2">
                                                            <div class="bg-red-500 h-2.5 rounded-full"
                                                                style="width: <?php echo min($stats['cancellation_rate'] * 2, 100); ?>%">
                                                            </div>
                                                        </div>
                                                        <span class="<?php echo $cancellationClass; ?> font-medium">
                                                            <?php echo $stats['cancellation_rate']; ?>%
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4"><?php echo formatNumber($stats['canceled_amount']); ?> FCFA
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Conseils pour améliorer le taux d'annulation -->
                        <div class="mt-4 bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div class="flex items-start">
                                <span class="material-icons text-blue-600 mr-2">lightbulb</span>
                                <div>
                                    <h3 class="font-medium text-gray-800">Conseils pour réduire les annulations</h3>
                                    <ul class="mt-2 text-sm text-gray-600 list-disc list-inside space-y-1">
                                        <li>Améliorer la communication avec les fournisseurs présentant des taux
                                            d'annulation élevés</li>
                                        <li>Établir des accords contractuels plus clairs sur les délais et conditions de
                                            livraison</li>
                                        <li>Mettre en place un système d'alerte précoce pour anticiper les risques
                                            d'annulation</li>
                                        <li>Diversifier les sources d'approvisionnement pour les produits critiques</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenu de l'onglet "Délais de livraison" -->
                <div id="tab-delivery-time" class="tab-content">
                    <!-- Statistiques globales des délais de livraison -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <!-- Délai moyen -->
                        <div class="stats-card bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Délai moyen</p>
                                    <h3 class="text-2xl font-bold mt-1">
                                        <?php echo round($deliveryTimeStats['avg_time'] ?? 0, 1); ?>
                                        jours</h3>
                                </div>
                                <div class="rounded-full bg-blue-100 p-3">
                                    <span class="material-icons text-blue-600">schedule</span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Délai moyen de livraison</p>
                        </div>

                        <!-- Délai minimum -->
                        <div class="stats-card bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Délai minimum</p>
                                    <h3 class="text-2xl font-bold mt-1">
                                        <?php echo round($deliveryTimeStats['min_time'] ?? 0, 1); ?>
                                        jours</h3>
                                </div>
                                <div class="rounded-full bg-green-100 p-3">
                                    <span class="material-icons text-green-600">fast_forward</span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Livraison la plus rapide</p>
                        </div>

                        <!-- Délai maximum -->
                        <div class="stats-card bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Délai maximum</p>
                                    <h3 class="text-2xl font-bold mt-1">
                                        <?php echo round($deliveryTimeStats['max_time'] ?? 0, 1); ?>
                                        jours</h3>
                                </div>
                                <div class="rounded-full bg-red-100 p-3">
                                    <span class="material-icons text-red-600">timer_off</span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Livraison la plus lente</p>
                        </div>

                        <!-- Écart-type -->
                        <div class="stats-card bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Écart-type</p>
                                    <h3 class="text-2xl font-bold mt-1">
                                        <?php echo round($deliveryTimeStats['std_dev_time'] ?? 0, 1); ?> jours
                                    </h3>
                                </div>
                                <div class="rounded-full bg-purple-100 p-3">
                                    <span class="material-icons text-purple-600">insights</span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Variabilité des délais</p>
                        </div>
                    </div>

                    <!-- Distribution des délais de livraison -->
                    <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                        <h2 class="text-lg font-semibold mb-4">Distribution des délais de livraison</h2>
                        <div class="chart-container">
                            <canvas id="deliveryTimeDistributionChart"></canvas>
                        </div>

                        <!-- Aide à l'interprétation -->
                        <div class="mt-4 bg-gray-50 p-4 rounded-lg">
                            <div class="flex items-start">
                                <span class="material-icons text-blue-600 mr-2">info</span>
                                <div>
                                    <h3 class="font-medium text-gray-800">Interprétation</h3>
                                    <p class="mt-1 text-sm text-gray-600">
                                        Ce graphique montre la répartition des délais de livraison par tranche. Une
                                        concentration importante dans les tranches inférieures
                                        (moins de 7 jours) indique une bonne performance des fournisseurs en termes de
                                        délais.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top fournisseurs par délai moyen de livraison -->
                    <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                        <h2 class="text-lg font-semibold mb-4">Top 10 fournisseurs par délai de livraison</h2>

                        <div class="chart-container">
                            <canvas id="deliveryTimeBySupplierChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Contenu de l'onglet "Catégories" -->
                <div id="tab-categories" class="tab-content">
                    <!-- Graphique de répartition des catégories -->
                    <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                        <h2 class="text-lg font-semibold mb-4">Répartition des achats par catégorie de fournisseur</h2>
                        <div class="chart-container">
                            <canvas id="categoriesDistributionChart"></canvas>
                        </div>
                    </div>

                    <!-- Détail des catégories -->
                    <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                        <h2 class="text-lg font-semibold mb-4">Détail des catégories de fournisseurs</h2>
                        <div class="overflow-x-auto">
                            <table class="alt-table min-w-full bg-white">
                                <thead>
                                    <tr>
                                        <th class="py-2 px-4">Catégorie</th>
                                        <th class="py-2 px-4">Nombre de fournisseurs</th>
                                        <th class="py-2 px-4">Montant des achats</th>
                                        <th class="py-2 px-4">% du volume total</th>
                                        <th class="py-2 px-4">Moyenne par fournisseur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (empty($categories)):
                                        ?>
                                        <tr>
                                            <td colspan="5" class="py-4 px-4 text-gray-500 text-center">Aucune donnée disponible
                                            </td>
                                        </tr>
                                        <?php
                                    else:
                                        foreach ($categories as $index => $category):
                                            $percentage = $totalPurchases > 0 ? round(($category['amount'] / $totalPurchases) * 100, 1) : 0;
                                            $avgPerSupplier = $category['supplier_count'] > 0 ? $category['amount'] / $category['supplier_count'] : 0;
                                            $categoryName = $category['categorie'] ? $category['categorie'] : 'Non définie';

                                            // Déterminer la couleur du badge en fonction de la catégorie
                                            $badgeClass = 'bg-gray-100 text-gray-800'; // Par défaut
                                            switch ($categoryName) {
                                                case 'Matériaux ferreux':
                                                    $badgeClass = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'Matériaux non ferreux':
                                                    $badgeClass = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'Électrique':
                                                    $badgeClass = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'Plomberie':
                                                    $badgeClass = 'bg-purple-100 text-purple-800';
                                                    break;
                                                case 'Outillage':
                                                    $badgeClass = 'bg-red-100 text-red-800';
                                                    break;
                                                case 'Quincaillerie':
                                                    $badgeClass = 'bg-indigo-100 text-indigo-800';
                                                    break;
                                                case 'Peinture':
                                                    $badgeClass = 'bg-pink-100 text-pink-800';
                                                    break;
                                            }

                                            $rowBg = $index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                            ?>
                                            <tr class="<?php echo $rowBg; ?> hover:bg-blue-50 transition-colors">
                                                <td class="py-3 px-4">
                                                    <span class="category-badge <?php echo $badgeClass; ?>">
                                                        <?php echo htmlspecialchars($categoryName); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <?php echo formatNumber($category['supplier_count']); ?>
                                                </td>
                                                <td class="py-3 px-4 font-medium">
                                                    <?php echo formatNumber($category['amount']); ?> FCFA
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="flex items-center">
                                                        <div class="w-16 bg-gray-200 rounded-full h-2.5 mr-2">
                                                            <div class="bg-purple-600 h-2.5 rounded-full"
                                                                style="width: <?php echo $percentage; ?>%"></div>
                                                        </div>
                                                        <span><?php echo $percentage; ?>%</span>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <?php echo formatNumber($avgPerSupplier); ?> FCFA
                                                </td>
                                            </tr>
                                            <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Contenu de l'onglet "Retours" -->
                <div id="tab-returns" class="tab-content">
                    <?php if (!empty($topRetours)): ?>
                        <!-- Graphique des motifs de retour -->
                        <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                            <h2 class="text-lg font-semibold mb-4">Répartition des motifs de retour</h2>
                            <div class="chart-container">
                                <canvas id="returnReasonsChart"></canvas>
                            </div>
                        </div>

                        <!-- Analyse des retours fournisseurs -->
                        <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                            <h2 class="text-lg font-semibold mb-4">Top fournisseurs par retours</h2>
                            <div class="overflow-x-auto">
                                <table class="alt-table min-w-full bg-white">
                                    <thead>
                                        <tr>
                                            <th class="py-2 px-4">Fournisseur</th>
                                            <th class="py-2 px-4">Retours</th>
                                            <th class="py-2 px-4">Montant</th>
                                            <th class="py-2 px-4">Motifs</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topRetours as $index => $retour):
                                            $rowBg = $index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                            ?>
                                            <tr class="<?php echo $rowBg; ?> hover:bg-pink-50 transition-colors">
                                                <td class="py-3 px-4 font-medium">
                                                    <?php echo htmlspecialchars($retour['supplier_name']); ?>
                                                </td>
                                                <td class="py-3 px-4"><?php echo formatNumber($retour['returns_count']); ?>
                                                </td>
                                                <td class="py-3 px-4"><?php echo formatNumber($retour['returns_amount']); ?>
                                                    FCFA</td>
                                                <td class="py-3 px-4">
                                                    <?php
                                                    $reasons = explode(',', $retour['reasons']);
                                                    foreach (array_slice(array_unique($reasons), 0, 3) as $reason):
                                                        $badgeClass = 'bg-pink-100 text-pink-800';
                                                        if ($reason === 'defectueux')
                                                            $badgeClass = 'bg-red-100 text-red-800';
                                                        else if ($reason === 'erreur_commande')
                                                            $badgeClass = 'bg-yellow-100 text-yellow-800';
                                                        else if ($reason === 'surplus')
                                                            $badgeClass = 'bg-blue-100 text-blue-800';
                                                        ?>
                                                        <span
                                                            class="px-2 py-1 text-xs rounded-full <?php echo $badgeClass; ?> inline-block mr-1 mb-1">
                                                            <?php echo ucfirst($reason); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (count(array_unique($reasons)) > 3): ?>
                                                        <span
                                                            class="text-xs text-gray-500">+<?php echo count(array_unique($reasons)) - 3; ?>
                                                            autres</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-white p-6 shadow-sm rounded-lg mb-6 text-center text-gray-500">
                            <span class="material-icons text-5xl text-gray-300 mb-2">assignment_return</span>
                            <p>Aucune donnée de retour fournisseur disponible</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recommandations -->
                <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                    <h2 class="text-lg font-semibold mb-4">Recommandations pour l'optimisation des fournisseurs</h2>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div
                            class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="font-semibold text-blue-800 mb-2 flex items-center">
                                <span class="material-icons mr-1 text-blue-600">money</span>
                                Consolidation des achats
                            </h3>
                            <p class="text-sm text-blue-700">Regrouper les commandes auprès des fournisseurs principaux pour
                                bénéficier de tarifs dégressifs et réduire les frais de livraison.</p>
                            <?php if ($totalPurchases > 1000000): ?>
                                <div class="mt-2 text-xs text-blue-800 font-medium">
                                    Économie potentielle: <?php echo formatNumber($totalPurchases * 0.05); ?> FCFA (5%)
                                </div>
                            <?php endif; ?>
                        </div>

                        <div
                            class="bg-green-50 border-l-4 border-green-500 p-4 rounded shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="font-semibold text-green-800 mb-2 flex items-center">
                                <span class="material-icons mr-1 text-green-600">schedule</span>
                                Négociation des délais
                            </h3>
                            <p class="text-sm text-green-700">Établir des accords sur les délais de livraison avec les
                                fournisseurs réguliers pour améliorer la planification des projets.</p>
                            <?php if ($avgDeliveryTime > 7): ?>
                                <div class="mt-2 text-xs text-green-800 font-medium">
                                    Objectif: Réduire le délai moyen à 7 jours (actuellement
                                    <?php echo round($avgDeliveryTime, 1); ?> jours)
                                </div>
                            <?php endif; ?>
                        </div>

                        <div
                            class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="font-semibold text-purple-800 mb-2 flex items-center">
                                <span class="material-icons mr-1 text-purple-600">diversity_3</span>
                                Diversification
                            </h3>
                            <p class="text-sm text-purple-700">Développer des partenariats avec plusieurs fournisseurs par
                                catégorie pour réduire les risques de rupture d'approvisionnement.</p>
                            <?php if (count($categories) > 0):
                                $mainCategory = $categories[0]['categorie'] ?? 'Non définie';
                                $mainCategoryAmount = $categories[0]['amount'] ?? 0;
                                $totalAmount = array_sum(array_column($categories, 'amount'));
                                $mainCategoryPercentage = $totalAmount > 0 ? round(($mainCategoryAmount / $totalAmount) * 100) : 0;

                                if ($mainCategoryPercentage > 40):
                                    ?>
                                    <div class="mt-2 text-xs text-purple-800 font-medium">
                                        Attention: <?php echo $mainCategoryPercentage; ?>% des achats sont dans la catégorie
                                        "<?php echo $mainCategory; ?>"
                                    </div>
                                <?php endif; endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Navigation rapide -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                    <a href="index.php"
                        class="bg-gray-600 hover:bg-gray-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                        <span class="material-icons text-3xl mb-2">dashboard</span>
                        <h3 class="text-lg font-semibold">Tableau de Bord</h3>
                        <p class="text-sm opacity-80 mt-1">Vue d'ensemble</p>
                    </a>

                    <a href="stats_achats.php"
                        class="bg-blue-600 hover:bg-blue-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                        <span class="material-icons text-3xl mb-2">shopping_cart</span>
                        <h3 class="text-lg font-semibold">Statistiques des Achats</h3>
                        <p class="text-sm opacity-80 mt-1">Analyse détaillée des commandes et achats</p>
                    </a>

                    <a href="stats_produits.php"
                        class="bg-green-600 hover:bg-green-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                        <span class="material-icons text-3xl mb-2">inventory</span>
                        <h3 class="text-lg font-semibold">Statistiques des Produits</h3>
                        <p class="text-sm opacity-80 mt-1">Analyse du stock et des mouvements</p>
                    </a>

                    <a href="stats_projets.php"
                        class="bg-yellow-600 hover:bg-yellow-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                        <span class="material-icons text-3xl mb-2">folder_special</span>
                        <h3 class="text-lg font-semibold">Statistiques des Projets</h3>
                        <p class="text-sm opacity-80 mt-1">Suivi et analyse des projets clients</p>
                    </a>

                    <a href="stats_canceled_orders.php"
                        class="bg-red-600 hover:bg-red-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                        <span class="material-icons text-3xl mb-2">cancel</span>
                        <h3 class="text-lg font-semibold">Commandes Annulées</h3>
                        <p class="text-sm opacity-80 mt-1">Analyse des commandes annulées</p>
                    </a>
                </div>
            <?php endif; ?>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/chart_functions.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mise à jour de la date et de l'heure
            function updateDateTime() {
                const now = new Date();
                const options = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                const dateTimeStr = now.toLocaleDateString('fr-FR', options);
                document.getElementById('date-time-display').textContent = dateTimeStr.charAt(0).toUpperCase() + dateTimeStr.slice(1);
            }

            updateDateTime();
            setInterval(updateDateTime, 60000);

            // Gestion des onglets
            const tabButtons = document.querySelectorAll('.toggle-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Désactiver tous les onglets
                    tabButtons.forEach(btn => {
                        btn.classList.remove('border-blue-500', 'text-blue-600');
                        btn.classList.add('border-transparent', 'hover:text-blue-500', 'hover:border-blue-300');
                    });

                    // Activer l'onglet cliqué
                    button.classList.add('border-blue-500', 'text-blue-600');
                    button.classList.remove('border-transparent', 'hover:text-blue-500', 'hover:border-blue-300');

                    // Cacher tous les contenus
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                    });

                    // Afficher le contenu correspondant
                    const tabId = button.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');

                    // Initialiser les graphiques selon l'onglet
                    if (tabId === 'tab-delivery-time') {
                        renderDeliveryTimeDistributionChart();
                        renderDeliveryTimeBySupplierChart();
                    } else if (tabId === 'tab-categories') {
                        renderCategoriesDistributionChart();
                    } else if (tabId === 'tab-returns') {
                        renderReturnReasonsChart();
                    } else if (tabId === 'tab-performance') {
                        renderSupplierPerformanceRadarChart();
                    }
                });
            });

            // Initialiser tous les graphiques
            renderInitialCharts();

            // Export PDF
            document.getElementById('export-pdf').addEventListener('click', exportPDF);
        });

        // Fonction pour initialiser les graphiques au chargement de la page
        function renderInitialCharts() {
            // Graphique d'évolution mensuelle des achats
            renderMonthlyEvolutionChart();

            // Graphique de répartition par catégorie (vue d'ensemble)
            renderCategoriesChart();

            // Graphique d'évolution des commandes et annulations
            renderOrdersEvolutionChart();

            // Pour l'onglet actif, initialiser ses graphiques spécifiques
            const activeTab = document.querySelector('.tab-content.active');
            if (activeTab) {
                const tabId = activeTab.id;

                if (tabId === 'tab-delivery-time') {
                    renderDeliveryTimeDistributionChart();
                    renderDeliveryTimeBySupplierChart();
                } else if (tabId === 'tab-categories') {
                    renderCategoriesDistributionChart();
                } else if (tabId === 'tab-returns') {
                    renderReturnReasonsChart();
                } else if (tabId === 'tab-performance') {
                    renderSupplierPerformanceRadarChart();
                }
            }
        }

        // Graphique d'évolution mensuelle des achats
        function renderMonthlyEvolutionChart() {
            const ctx = document.getElementById('monthlyPurchasesChart');
            if (!ctx) return;

            const data = <?php echo json_encode($monthlyChartData); ?>;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Montant des achats (FCFA)',
                            data: data.amounts,
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Nombre de fournisseurs',
                            data: data.suppliers,
                            borderColor: 'rgb(139, 92, 246)',
                            backgroundColor: 'rgba(139, 92, 246, 0)',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            tension: 0.4,
                            pointRadius: 3,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function (context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.datasetIndex === 0) {
                                        label += new Intl.NumberFormat('fr-FR').format(context.parsed.y) + ' FCFA';
                                    } else {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Montant (FCFA)'
                            },
                            ticks: {
                                callback: function (value) {
                                    return new Intl.NumberFormat('fr-FR', {
                                        notation: 'compact',
                                        compactDisplay: 'short'
                                    }).format(value);
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Nombre de fournisseurs'
                            },
                            min: 0
                        }
                    }
                }
            });
        }

        // Graphique de répartition par catégorie (vue d'ensemble)
        function renderCategoriesChart() {
            const ctx = document.getElementById('categoriesChart');
            if (!ctx) return;

            const data = <?php echo json_encode($categoriesChartData); ?>;

            // Générer des couleurs pour chaque catégorie
            const backgroundColors = [];
            for (let i = 0; i < data.labels.length; i++) {
                const hue = (i * 137.5) % 360; // Répartir uniformément les couleurs
                backgroundColors.push(`hsl(${hue}, 70%, 60%)`);
            }

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.data,
                        backgroundColor: backgroundColors,
                        borderColor: 'white',
                        borderWidth: 2,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 15,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${new Intl.NumberFormat('fr-FR').format(value)} FCFA (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        // Graphique d'évolution des commandes et annulations
        function renderOrdersEvolutionChart() {
            const ctx = document.getElementById('ordersEvolutionChart');
            if (!ctx) return;

            const data = <?php echo json_encode($monthlyOrdersChartData); ?>;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Commandes totales',
                            data: data.total_orders,
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1,
                            borderRadius: 4,
                            order: 1
                        },
                        {
                            label: 'Commandes annulées',
                            data: data.canceled_orders,
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderColor: 'rgba(239, 68, 68, 1)',
                            borderWidth: 1,
                            borderRadius: 4,
                            order: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre de commandes'
                            }
                        }
                    }
                }
            });
        }

        // Graphique pour la distribution des délais de livraison
        function renderDeliveryTimeDistributionChart() {
            const ctx = document.getElementById('deliveryTimeDistributionChart');
            if (!ctx) return;

            const data = <?php echo json_encode($distributionChartData); ?>;

            // Définir des couleurs en fonction des tranches de délai
            const backgroundColors = [
                'rgba(16, 185, 129, 0.7)',  // Vert pour délais courts
                'rgba(59, 130, 246, 0.7)',  // Bleu
                'rgba(245, 158, 11, 0.7)',  // Jaune
                'rgba(239, 68, 68, 0.7)',   // Rouge pour longs délais
                'rgba(107, 114, 128, 0.7)'  // Gris pour le reste
            ];

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Nombre de commandes',
                        data: data.data,
                        backgroundColor: backgroundColors.slice(0, data.labels.length),
                        borderColor: 'white',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return `${context.label}: ${context.parsed.y} commande(s)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre de commandes'
                            },
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // Graphique pour la distribution des délais par fournisseur (top 10)
        function renderDeliveryTimeBySupplierChart() {
            const ctx = document.getElementById('deliveryTimeBySupplierChart');
            if (!ctx) return;

            const data = <?php echo json_encode($deliveryTimeChartData); ?>;

            // Créer un gradient de couleurs du vert au rouge
            const backgroundColors = data.data.map(value => {
                // Normaliser les valeurs pour le gradient (0 à 100% de l'échelle)
                const maxDelay = Math.max(...data.data);
                const minDelay = Math.min(...data.data);
                const normalizedValue = maxDelay > minDelay
                    ? (value - minDelay) / (maxDelay - minDelay)
                    : 0.5;

                // Vert pour les valeurs basses, rouge pour les valeurs hautes
                // HSL: Vert = 120, Jaune = 60, Rouge = 0
                const hue = 120 - (normalizedValue * 120);
                return `hsla(${hue}, 80%, 60%, 0.8)`;
            });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Délai moyen (jours)',
                        data: data.data,
                        backgroundColor: backgroundColors,
                        borderColor: 'white',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y', // Barres horizontales
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return `Délai moyen: ${context.parsed.x.toFixed(1)} jours`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Délai moyen (jours)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Graphique pour la répartition des catégories (onglet catégories)
        function renderCategoriesDistributionChart() {
            const ctx = document.getElementById('categoriesDistributionChart');
            if (!ctx) return;

            const data = <?php echo json_encode($categoriesChartData); ?>;

            // Générer des couleurs pour chaque catégorie
            const backgroundColors = [];
            for (let i = 0; i < data.labels.length; i++) {
                const hue = (i * 137.5) % 360; // Répartir uniformément les couleurs
                backgroundColors.push(`hsl(${hue}, 70%, 60%)`);
            }

            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.data,
                        backgroundColor: backgroundColors,
                        borderColor: 'white',
                        borderWidth: 2,
                        hoverOffset: 20
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                font: {
                                    size: 12
                                },
                                boxWidth: 15,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${new Intl.NumberFormat('fr-FR').format(value)} FCFA (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Graphique pour les motifs de retour
        function renderReturnReasonsChart() {
            const ctx = document.getElementById('returnReasonsChart');
            if (!ctx) return;

            const data = <?php echo json_encode($reasonsChartData); ?>;

            // Si pas de données ou canvas introuvable, ne rien faire
            if (!data.labels.length || !ctx) return;

            // Définir des couleurs pour les différents motifs
            const backgroundColors = [
                'rgba(239, 68, 68, 0.8)',   // Rouge - defectueux
                'rgba(245, 158, 11, 0.8)',  // Jaune - erreur_commande
                'rgba(59, 130, 246, 0.8)',  // Bleu - surplus
                'rgba(139, 92, 246, 0.8)',  // Violet
                'rgba(16, 185, 129, 0.8)'   // Vert
            ];

            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels.map(r => r ? (r.charAt(0).toUpperCase() + r.slice(1)) : 'Non spécifié'),
                    datasets: [{
                        data: data.data,
                        backgroundColor: backgroundColors.slice(0, data.labels.length),
                        borderColor: 'white',
                        borderWidth: 2,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 15,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} retour(s) (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Graphique radar pour les performances des fournisseurs
        function renderSupplierPerformanceRadarChart() {
            const ctx = document.getElementById('supplierPerformanceChart');
            if (!ctx) return;

            const performanceData = <?php echo json_encode($topPerformersData); ?>;

            if (!performanceData.length) return;

            // Extraire les données pour le radar chart
            const labels = performanceData.map(supplier => supplier.nom);
            const deliveryTimes = performanceData.map(supplier => {
                // Normaliser les délais pour qu'un délai plus court donne un meilleur score
                // Formule: 100 - (délai/max_délai*100)
                const maxDelay = Math.max(...performanceData.map(s => s.avg_delivery_time));
                return maxDelay > 0 ? 100 - ((supplier.avg_delivery_time / maxDelay) * 100) : 50;
            });
            const completionRates = performanceData.map(supplier => supplier.completion_rate);
            const volumes = performanceData.map(supplier => {
                // Normaliser les volumes pour avoir une échelle de 0 à 100
                const maxVolume = Math.max(...performanceData.map(s => s.amount));
                return maxVolume > 0 ? (supplier.amount / maxVolume) * 100 : 0;
            });

            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Rapidité de livraison',
                            data: deliveryTimes,
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: 'rgb(59, 130, 246)',
                            pointBackgroundColor: 'rgb(59, 130, 246)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgb(59, 130, 246)'
                        },
                        {
                            label: 'Taux de complétion',
                            data: completionRates,
                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                            borderColor: 'rgb(16, 185, 129)',
                            pointBackgroundColor: 'rgb(16, 185, 129)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgb(16, 185, 129)'
                        },
                        {
                            label: 'Volume d\'achat',
                            data: volumes,
                            backgroundColor: 'rgba(139, 92, 246, 0.2)',
                            borderColor: 'rgb(139, 92, 246)',
                            pointBackgroundColor: 'rgb(139, 92, 246)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgb(139, 92, 246)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            angleLines: {
                                display: true
                            },
                            suggestedMin: 0,
                            suggestedMax: 100,
                            ticks: {
                                stepSize: 20
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 15,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    let value = context.raw;
                                    let metricName = context.dataset.label;

                                    if (metricName === 'Rapidité de livraison') {
                                        // Retrouver le délai réel à partir du score normalisé
                                        const supplierIndex = context.dataIndex;
                                        const actualDelay = performanceData[supplierIndex].avg_delivery_time;
                                        return `${metricName}: ${value.toFixed(1)}/100 (${actualDelay.toFixed(1)} jours)`;
                                    } else if (metricName === 'Taux de complétion') {
                                        return `${metricName}: ${value.toFixed(1)}%`;
                                    } else if (metricName === 'Volume d\'achat') {
                                        // Retrouver le montant réel à partir du score normalisé
                                        const supplierIndex = context.dataIndex;
                                        const actualAmount = performanceData[supplierIndex].amount;
                                        return `${metricName}: ${value.toFixed(1)}/100 (${new Intl.NumberFormat('fr-FR').format(actualAmount)} FCFA)`;
                                    }

                                    return `${metricName}: ${value.toFixed(1)}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Fonction pour exporter en PDF
        function exportPDF() {
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
                window.location.href = 'generate_report.php?type=fournisseurs&period=<?php echo $period; ?>&year=<?php echo $selectedYear; ?>';
            }, 1500);
        }
    </script>
</body>

</html>