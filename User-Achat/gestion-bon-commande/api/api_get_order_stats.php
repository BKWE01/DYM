<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Récupérer les statistiques des bons de commande - MISE À JOUR AVEC REJETS
    $statsQuery = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(montant_total), 0) as total_amount,
                    COUNT(DISTINCT fournisseur) as unique_suppliers,
                    COUNT(CASE WHEN signature_finance IS NOT NULL AND user_finance_id IS NOT NULL THEN 1 END) as validated_orders,
                    COUNT(CASE WHEN (signature_finance IS NULL OR user_finance_id IS NULL) AND (status != 'rejected' AND rejected_at IS NULL) THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN status = 'rejected' OR rejected_at IS NOT NULL THEN 1 END) as rejected_orders
                  FROM purchase_orders";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute();
    
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les autres statistiques intéressantes
    
    // 1. Nombre de commandes multi-projets
    $multiProjectsQuery = "SELECT COUNT(*) as count FROM purchase_orders WHERE is_multi_project = 1";
    $multiProjectsStmt = $pdo->prepare($multiProjectsQuery);
    $multiProjectsStmt->execute();
    $multiProjectsCount = $multiProjectsStmt->fetchColumn();
    
    // 2. Montant moyen par bon de commande
    $avgAmountQuery = "SELECT AVG(montant_total) as avg_amount FROM purchase_orders";
    $avgAmountStmt = $pdo->prepare($avgAmountQuery);
    $avgAmountStmt->execute();
    $avgAmount = $avgAmountStmt->fetchColumn();
    
    // 3. Bons de commande par mois (6 derniers mois)
    $monthlyQuery = "SELECT 
                     DATE_FORMAT(generated_at, '%Y-%m') as month,
                     COUNT(*) as order_count,
                     SUM(montant_total) as month_total,
                     COUNT(CASE WHEN signature_finance IS NOT NULL AND user_finance_id IS NOT NULL THEN 1 END) as validated_count,
                     COUNT(CASE WHEN status = 'rejected' OR rejected_at IS NOT NULL THEN 1 END) as rejected_count
                   FROM purchase_orders
                   WHERE generated_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                   GROUP BY DATE_FORMAT(generated_at, '%Y-%m')
                   ORDER BY month DESC";
    
    $monthlyStmt = $pdo->prepare($monthlyQuery);
    $monthlyStmt->execute();
    $monthlyStats = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Top 5 des fournisseurs par nombre de bons de commande
    $topSuppliersQuery = "SELECT 
                         fournisseur,
                         COUNT(*) as order_count,
                         SUM(montant_total) as total_amount,
                         COUNT(CASE WHEN signature_finance IS NOT NULL AND user_finance_id IS NOT NULL THEN 1 END) as validated_count,
                         COUNT(CASE WHEN status = 'rejected' OR rejected_at IS NOT NULL THEN 1 END) as rejected_count
                       FROM purchase_orders
                       GROUP BY fournisseur
                       ORDER BY order_count DESC
                       LIMIT 5";
    
    $topSuppliersStmt = $pdo->prepare($topSuppliersQuery);
    $topSuppliersStmt->execute();
    $topSuppliers = $topSuppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. NOUVEAU : Statistiques des rejets par utilisateur
    $rejectionStatsQuery = "SELECT 
                           ur.name as rejected_by_username,
                           COUNT(*) as rejection_count,
                           SUM(po.montant_total) as rejected_amount
                         FROM purchase_orders po
                         INNER JOIN users_exp ur ON po.rejected_by_user_id = ur.id
                         WHERE po.status = 'rejected' OR po.rejected_at IS NOT NULL
                         GROUP BY po.rejected_by_user_id, ur.name
                         ORDER BY rejection_count DESC
                         LIMIT 5";
    
    $rejectionStatsStmt = $pdo->prepare($rejectionStatsQuery);
    $rejectionStatsStmt->execute();
    $rejectionStats = $rejectionStatsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 6. NOUVEAU : Raisons de rejet les plus fréquentes
    $rejectionReasonsQuery = "SELECT 
                             rejection_reason,
                             COUNT(*) as count,
                             SUM(montant_total) as total_amount
                           FROM purchase_orders
                           WHERE (status = 'rejected' OR rejected_at IS NOT NULL) AND rejection_reason IS NOT NULL
                           GROUP BY rejection_reason
                           ORDER BY count DESC
                           LIMIT 10";
    
    $rejectionReasonsStmt = $pdo->prepare($rejectionReasonsQuery);
    $rejectionReasonsStmt->execute();
    $rejectionReasons = $rejectionReasonsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 7. NOUVEAU : Évolution des rejets par mois
    $rejectionTrendsQuery = "SELECT 
                            DATE_FORMAT(rejected_at, '%Y-%m') as month,
                            COUNT(*) as rejection_count,
                            SUM(montant_total) as rejected_amount
                          FROM purchase_orders
                          WHERE rejected_at IS NOT NULL AND rejected_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                          GROUP BY DATE_FORMAT(rejected_at, '%Y-%m')
                          ORDER BY month DESC";
    
    $rejectionTrendsStmt = $pdo->prepare($rejectionTrendsQuery);
    $rejectionTrendsStmt->execute();
    $rejectionTrends = $rejectionTrendsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 8. NOUVEAU : Taux de rejet par fournisseur
    $supplierRejectionRateQuery = "SELECT 
                                  fournisseur,
                                  COUNT(*) as total_orders,
                                  COUNT(CASE WHEN status = 'rejected' OR rejected_at IS NOT NULL THEN 1 END) as rejected_orders,
                                  ROUND((COUNT(CASE WHEN status = 'rejected' OR rejected_at IS NOT NULL THEN 1 END) / COUNT(*)) * 100, 2) as rejection_rate
                                FROM purchase_orders
                                GROUP BY fournisseur
                                HAVING COUNT(*) >= 3
                                ORDER BY rejection_rate DESC
                                LIMIT 10";
    
    $supplierRejectionRateStmt = $pdo->prepare($supplierRejectionRateQuery);
    $supplierRejectionRateStmt->execute();
    $supplierRejectionRates = $supplierRejectionRateStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compiler toutes les statistiques - MISE À JOUR AVEC REJETS
    $allStats = [
        'total_orders' => $stats['total_orders'],
        'total_amount' => $stats['total_amount'],
        'unique_suppliers' => $stats['unique_suppliers'],
        'validated_orders' => $stats['validated_orders'],
        'pending_orders' => $stats['pending_orders'],
        'rejected_orders' => $stats['rejected_orders'], // NOUVEAU
        'multi_projects_count' => $multiProjectsCount,
        'avg_order_amount' => $avgAmount,
        'monthly_stats' => $monthlyStats,
        'top_suppliers' => $topSuppliers,
        'rejection_stats' => $rejectionStats, // NOUVEAU
        'rejection_reasons' => $rejectionReasons, // NOUVEAU
        'rejection_trends' => $rejectionTrends, // NOUVEAU
        'supplier_rejection_rates' => $supplierRejectionRates, // NOUVEAU
        // Calculs de taux
        'validation_rate' => $stats['total_orders'] > 0 ? round(($stats['validated_orders'] / $stats['total_orders']) * 100, 2) : 0,
        'rejection_rate' => $stats['total_orders'] > 0 ? round(($stats['rejected_orders'] / $stats['total_orders']) * 100, 2) : 0,
        'pending_rate' => $stats['total_orders'] > 0 ? round(($stats['pending_orders'] / $stats['total_orders']) * 100, 2) : 0
    ];
    
    // Retourner les statistiques au format JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => $allStats
    ]);
    
} catch (Exception $e) {
    // En cas d'erreur, retourner un message d'erreur
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>