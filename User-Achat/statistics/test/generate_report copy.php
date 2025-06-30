<?php
/**
 * Générateur de rapports PDF optimisé avec mPDF
 * 
 * Génère des rapports PDF compacts et bien structurés pour le service achat
 * Optimisé pour réduire l'espace inutile et améliorer la lisibilité
 * 
 * @package DYM_MANUFACTURE
 * @subpackage User-Achat/statistics
 * @version 2.0 - Version optimisée
 * @author Équipe DYM
 */

session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../index.php");
    exit();
}

// Inclusions nécessaires
require_once('../../vendor/autoload.php'); // mPDF via Composer
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Récupération des paramètres
$reportType = $_GET['type'] ?? 'dashboard';
$year = $_GET['year'] ?? date('Y');
$period = $_GET['period'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$client = $_GET['client'] ?? 'all';
$code_projet = $_GET['code_projet'] ?? null;

/**
 * Classe optimisée pour la génération de rapports PDF
 */
class OptimizedReportGenerator
{
    private $mpdf;
    private $pdo;
    
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->initializePDF();
    }
    
    /**
     * Initialise mPDF avec une configuration optimisée
     */
    private function initializePDF()
    {
        $config = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 9,
            'default_font' => 'helvetica',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 8,
            'margin_footer' => 8,
            'orientation' => 'P',
            'tempDir' => sys_get_temp_dir()
        ];
        
        $this->mpdf = new \Mpdf\Mpdf($config);
        
        // Métadonnées
        $this->mpdf->SetTitle('Rapport Statistiques - DYM MANUFACTURE');
        $this->mpdf->SetAuthor('DYM MANUFACTURE');
        $this->mpdf->SetCreator('Système DYM');
        
        // En-tête et pied de page compacts
        $this->setupHeaderFooter();
    }
    
    /**
     * Configuration compacte de l'en-tête et du pied de page
     */
    private function setupHeaderFooter()
    {
        $header = '
        <table width="100%" style="border-bottom: 1px solid #ddd; font-size: 8px;">
            <tr>
                <td width="20%" style="text-align: left; color: #666;">
                    <strong>DYM MANUFACTURE</strong>
                </td>
                <td width="60%" style="text-align: center; color: #333;">
                    <strong>RAPPORT DE STATISTIQUES</strong>
                </td>
                <td width="20%" style="text-align: right; color: #666;">
                    ' . date('d/m/Y H:i') . '
                </td>
            </tr>
        </table>';
        
        $footer = '
        <table width="100%" style="border-top: 1px solid #ddd; font-size: 7px; color: #666;">
            <tr>
                <td width="50%">DYM MANUFACTURE - Confidentiel</td>
                <td width="50%" style="text-align: right;">Page {PAGENO} sur {nbpg}</td>
            </tr>
        </table>';
        
        $this->mpdf->SetHTMLHeader($header);
        $this->mpdf->SetHTMLFooter($footer);
    }
    
    /**
     * Génère le rapport selon le type demandé
     */
    public function generateReport($type, $params = [])
    {
        try {
            switch ($type) {
                case 'dashboard':
                    $this->generateDashboardReport();
                    break;
                case 'achats':
                    $this->generateAchatsReport($params['year'] ?? date('Y'));
                    break;
                case 'fournisseurs':
                    $this->generateFournisseursReport($params['period'] ?? 'all');
                    break;
                case 'produits':
                    $this->generateProduitsReport($params['category'] ?? 'all');
                    break;
                case 'projets':
                    $this->generateProjetsReport($params['client'] ?? 'all', $params['code_projet'] ?? null);
                    break;
                case 'canceled':
                    $this->generateCanceledOrdersReport();
                    break;
                default:
                    $this->generateErrorReport('Type de rapport non reconnu: ' . $type);
            }
        } catch (Exception $e) {
            $this->generateErrorReport($e->getMessage());
        }
    }
    
    /**
     * Rapport du tableau de bord - Version compacte
     */
    private function generateDashboardReport()
    {
        $html = $this->getPageHeader('TABLEAU DE BORD', 'Vue d\'ensemble des statistiques');
        
        try {
            // Statistiques principales en 2 colonnes
            $stats = $this->getDashboardStats();
            $html .= $this->createTwoColumnStats([
                'Produits en stock' => $this->formatNumber($stats['total_products']),
                'Valeur du stock' => $this->formatNumber($stats['stock_value']) . ' FCFA',
                'Achats ce mois' => $this->formatNumber($stats['monthly_purchases']) . ' FCFA',
                'Commandes en attente' => $this->formatNumber($stats['pending_orders'])
            ]);
            
            // Top 5 fournisseurs en tableau compact
            $suppliers = $this->getTopSuppliers(5);
            if (!empty($suppliers)) {
                $html .= $this->createSection('Top 5 Fournisseurs');
                $html .= $this->createCompactTable(
                    ['Fournisseur', 'Commandes', 'Montant Total'],
                    array_map(function($s) {
                        return [$s['nom'] ?? 'Non défini', $s['orders'], $this->formatNumber($s['amount']) . ' FCFA'];
                    }, $suppliers)
                );
            }
            
        } catch (Exception $e) {
            $html .= $this->createErrorBox('Erreur lors de la récupération des données: ' . $e->getMessage());
        }
        
        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }
    
    /**
     * Rapport des achats - Version optimisée
     */
    private function generateAchatsReport($year)
    {
        $html = $this->getPageHeader('RAPPORT DES ACHATS ' . $year, 'Analyse des achats pour l\'année ' . $year);
        
        try {
            // Statistiques annuelles
            $yearStats = $this->getYearStats($year);
            $html .= $this->createTwoColumnStats([
                'Total commandes' => $this->formatNumber($yearStats['total_orders']),
                'Montant total' => $this->formatNumber($yearStats['total_amount']) . ' FCFA',
                'Valeur moyenne' => $this->formatNumber($yearStats['avg_amount']) . ' FCFA',
                'Fournisseurs actifs' => $this->formatNumber($yearStats['suppliers_count'])
            ]);
            
            // Données mensuelles condensées
            $monthlyData = $this->getMonthlyData($year);
            if (!empty($monthlyData)) {
                $html .= $this->createSection('Répartition Mensuelle');
                $html .= $this->createCompactTable(
                    ['Mois', 'Commandes', 'Montant'],
                    array_map(function($m) {
                        return [$m['month_name'], $m['orders'], $this->formatNumber($m['amount']) . ' FCFA'];
                    }, $monthlyData)
                );
            }
            
            // Top produits
            $topProducts = $this->getTopProducts($year, 8);
            if (!empty($topProducts)) {
                $html .= $this->createSection('Top Produits Achetés');
                $html .= $this->createCompactTable(
                    ['Produit', 'Qté', 'Montant'],
                    array_map(function($p) {
                        return [
                            $this->truncateText($p['designation'], 30),
                            $this->formatNumber($p['total_qty']),
                            $this->formatNumber($p['total_amount']) . ' FCFA'
                        ];
                    }, $topProducts)
                );
            }
            
        } catch (Exception $e) {
            $html .= $this->createErrorBox('Erreur: ' . $e->getMessage());
        }
        
        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }
    
    /**
     * Rapport des fournisseurs - Version compacte
     */
    private function generateFournisseursReport($period)
    {
        $periodText = $this->getPeriodText($period);
        $html = $this->getPageHeader('RAPPORT FOURNISSEURS', 'Analyse des fournisseurs ' . $periodText);
        
        try {
            $stats = $this->getSuppliersStats($period);
            $html .= $this->createTwoColumnStats([
                'Fournisseurs totaux' => $this->formatNumber($stats['total_suppliers']),
                'Fournisseurs actifs' => $this->formatNumber($stats['active_suppliers']),
                'Total achats' => $this->formatNumber($stats['total_amount']) . ' FCFA',
                'Moyenne par fournisseur' => $this->formatNumber($stats['avg_per_supplier']) . ' FCFA'
            ]);
            
            // Top fournisseurs
            $topSuppliers = $this->getDetailedSuppliers($period, 10);
            if (!empty($topSuppliers)) {
                $html .= $this->createSection('Top 10 Fournisseurs');
                $html .= $this->createCompactTable(
                    ['Fournisseur', 'Commandes', 'Montant', '% Total'],
                    array_map(function($s) use ($stats) {
                        $percentage = $stats['total_amount'] > 0 ? 
                            round(($s['amount'] / $stats['total_amount']) * 100, 1) . '%' : '0%';
                        return [
                            $this->truncateText($s['nom'] ?? 'Non défini', 25),
                            $s['orders'],
                            $this->formatNumber($s['amount']) . ' FCFA',
                            $percentage
                        ];
                    }, $topSuppliers)
                );
            }
            
        } catch (Exception $e) {
            $html .= $this->createErrorBox('Erreur: ' . $e->getMessage());
        }
        
        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }
    
    /**
     * Rapport des produits - Version optimisée
     */
    private function generateProduitsReport($category)
    {
        $categoryText = ($category == 'all') ? 'Toutes catégories' : 'Catégorie sélectionnée';
        $html = $this->getPageHeader('RAPPORT PRODUITS', 'Analyse du stock - ' . $categoryText);
        
        try {
            $stats = $this->getStockStats($category);
            $html .= $this->createTwoColumnStats([
                'Total produits' => $this->formatNumber($stats['total_products']),
                'En stock' => $this->formatNumber($stats['in_stock']),
                'Rupture stock' => $this->formatNumber($stats['out_of_stock']),
                'Valeur totale' => $this->formatNumber($stats['total_value']) . ' FCFA'
            ]);
            
            // Top produits par valeur
            $topProducts = $this->getTopProductsByValue($category, 10);
            if (!empty($topProducts)) {
                $html .= $this->createSection('Top 10 Produits par Valeur');
                $html .= $this->createCompactTable(
                    ['Produit', 'Qté', 'Prix Unit.', 'Valeur'],
                    array_map(function($p) {
                        return [
                            $this->truncateText($p['product_name'], 25),
                            $this->formatNumber($p['quantity']) . ' ' . ($p['unit'] ?? ''),
                            $this->formatNumber($p['unit_price']) . ' FCFA',
                            $this->formatNumber($p['value']) . ' FCFA'
                        ];
                    }, $topProducts)
                );
            }
            
        } catch (Exception $e) {
            $html .= $this->createErrorBox('Erreur: ' . $e->getMessage());
        }
        
        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }
    
    /**
     * Rapport des projets - Version compacte
     */
    private function generateProjetsReport($selectedClient, $selectedCodeProjet)
    {
        $filterText = ($selectedClient == 'all') ? 'Tous clients' : 'Client: ' . $selectedClient;
        if ($selectedCodeProjet) $filterText .= ' - Projet: ' . $selectedCodeProjet;
        
        $html = $this->getPageHeader('RAPPORT PROJETS', $filterText);
        
        try {
            $stats = $this->getProjectsStats($selectedClient, $selectedCodeProjet);
            $html .= $this->createTwoColumnStats([
                'Total projets' => $this->formatNumber($stats['total_projects']),
                'Total articles' => $this->formatNumber($stats['total_items']),
                'Montant total' => $this->formatNumber($stats['total_amount']) . ' FCFA',
                'Durée moyenne' => $this->formatNumber($stats['avg_duration']) . ' jours'
            ]);
            
            // Projets récents
            $recentProjects = $this->getRecentProjects($selectedClient, $selectedCodeProjet, 12);
            if (!empty($recentProjects)) {
                $html .= $this->createSection('Projets Récents');
                $html .= $this->createCompactTable(
                    ['Code Projet', 'Client', 'Chef Projet', 'Date', 'Articles'],
                    array_map(function($p) {
                        return [
                            $p['code_projet'],
                            $this->truncateText($p['nom_client'], 20),
                            $this->truncateText($p['chefprojet'], 15),
                            date('d/m/Y', strtotime($p['created_at'])),
                            $this->formatNumber($p['total_items'])
                        ];
                    }, $recentProjects)
                );
            }
            
        } catch (Exception $e) {
            $html .= $this->createErrorBox('Erreur: ' . $e->getMessage());
        }
        
        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }
    
    /**
     * Rapport des commandes annulées - Version optimisée
     */
    private function generateCanceledOrdersReport()
    {
        $html = $this->getPageHeader('COMMANDES ANNULÉES', 'Analyse des annulations et tendances');
        
        try {
            // Statistiques principales
            $stats = $this->getCanceledStats();
            $html .= $this->createTwoColumnStats([
                'Total annulées' => $this->formatNumber($stats['total_canceled']),
                'Montant annulé' => $this->formatNumber($stats['total_amount']) . ' FCFA',
                'Projets concernés' => $this->formatNumber($stats['projects_count']),
                'Taux annulation' => $stats['cancellation_rate'] . '%'
            ]);
            
            // Tendance mensuelle (6 derniers mois)
            $monthlyTrend = $this->getCanceledMonthlyTrend();
            if (!empty($monthlyTrend)) {
                $html .= $this->createSection('Tendance (6 derniers mois)');
                $html .= $this->createCompactTable(
                    ['Mois', 'Annulations', 'Montant', 'Évolution'],
                    array_map(function($m) {
                        return [
                            $m['month_name'],
                            $this->formatNumber($m['count']),
                            $this->formatNumber($m['amount']) . ' FCFA',
                            $m['evolution'] ?? '-'
                        ];
                    }, array_slice($monthlyTrend, -6))
                );
            }
            
            // Top produits annulés
            $topCanceled = $this->getTopCanceledProducts(8);
            if (!empty($topCanceled)) {
                $html .= $this->createSection('Top Produits Annulés');
                $html .= $this->createCompactTable(
                    ['Produit', 'Annulations', 'Valeur Perdue'],
                    array_map(function($p) {
                        return [
                            $this->truncateText($p['designation'], 30),
                            $this->formatNumber($p['count']),
                            $this->formatNumber($p['total_value']) . ' FCFA'
                        ];
                    }, $topCanceled)
                );
            }
            
            // Analyse par projets
            $projectsAnalysis = $this->getCanceledByProjects(10);
            if (!empty($projectsAnalysis)) {
                $html .= $this->createSection('Analyse par Projets');
                $html .= $this->createCompactTable(
                    ['Code Projet', 'Client', 'Annulations', 'Valeur', 'Taux %'],
                    array_map(function($p) {
                        return [
                            $p['code_projet'],
                            $this->truncateText($p['nom_client'], 20),
                            $this->formatNumber($p['canceled_count']),
                            $this->formatNumber($p['total_value']) . ' FCFA',
                            $p['project_rate'] . '%'
                        ];
                    }, $projectsAnalysis)
                );
            }
            
            // Recommandations
            $html .= $this->createSection('Recommandations');
            $html .= $this->createRecommendationsList();
            
        } catch (Exception $e) {
            $html .= $this->createErrorBox('Erreur: ' . $e->getMessage());
        }
        
        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }
    
    /**
     * Génère un rapport d'erreur
     */
    private function generateErrorReport($errorMessage)
    {
        $html = $this->getPageHeader('ERREUR', 'Impossible de générer le rapport');
        $html .= $this->createErrorBox($errorMessage);
        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }
    
    // ==========================================
    // MÉTHODES DE RÉCUPÉRATION DE DONNÉES
    // ==========================================
    
    /**
     * Statistiques du tableau de bord
     */
    private function getDashboardStats()
    {
        // Simulation des données - Remplacer par vos vraies requêtes
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM products WHERE quantity > 0");
        $stmt->execute();
        $products = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        return [
            'total_products' => $products,
            'stock_value' => 25000000, // À calculer réellement
            'monthly_purchases' => 5000000, // À calculer réellement
            'pending_orders' => 25 // À calculer réellement
        ];
    }
    
    /**
     * Top fournisseurs
     */
    private function getTopSuppliers($limit = 5)
    {
        // Simulation - Remplacer par vraie requête
        return [
            ['nom' => 'Fournisseur A', 'orders' => 45, 'amount' => 2500000],
            ['nom' => 'Fournisseur B', 'orders' => 32, 'amount' => 1800000],
            ['nom' => 'Fournisseur C', 'orders' => 28, 'amount' => 1200000],
            ['nom' => 'Fournisseur D', 'orders' => 21, 'amount' => 950000],
            ['nom' => 'Fournisseur E', 'orders' => 18, 'amount' => 780000]
        ];
    }
    
    /**
     * Statistiques annuelles
     */
    private function getYearStats($year)
    {
        // Simulation - Remplacer par vraies requêtes
        return [
            'total_orders' => 234,
            'total_amount' => 15000000,
            'avg_amount' => 64103,
            'suppliers_count' => 28
        ];
    }
    
    /**
     * Données mensuelles
     */
    private function getMonthlyData($year)
    {
        // Simulation - Remplacer par vraie requête
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = [
                'month' => $i,
                'month_name' => date('M Y', mktime(0, 0, 0, $i, 1, $year)),
                'orders' => rand(15, 45),
                'amount' => rand(800000, 2500000)
            ];
        }
        return $months;
    }
    
    /**
     * Top produits
     */
    private function getTopProducts($year, $limit = 10)
    {
        // Simulation - Remplacer par vraie requête
        $products = [];
        for ($i = 1; $i <= $limit; $i++) {
            $products[] = [
                'designation' => 'Produit ' . chr(64 + $i),
                'total_qty' => rand(50, 500),
                'total_amount' => rand(200000, 1500000)
            ];
        }
        return $products;
    }
    
    /**
     * Statistiques des fournisseurs
     */
    private function getSuppliersStats($period)
    {
        // Simulation - Remplacer par vraies requêtes
        return [
            'total_suppliers' => 45,
            'active_suppliers' => 28,
            'total_amount' => 12000000,
            'avg_per_supplier' => 428571
        ];
    }
    
    /**
     * Fournisseurs détaillés
     */
    private function getDetailedSuppliers($period, $limit = 10)
    {
        // Simulation - Remplacer par vraie requête
        $suppliers = [];
        for ($i = 1; $i <= $limit; $i++) {
            $suppliers[] = [
                'nom' => 'Fournisseur ' . chr(64 + $i),
                'orders' => rand(10, 50),
                'amount' => rand(500000, 2000000)
            ];
        }
        return $suppliers;
    }
    
    /**
     * Statistiques du stock
     */
    private function getStockStats($category)
    {
        // Simulation - Remplacer par vraies requêtes
        return [
            'total_products' => 156,
            'in_stock' => 134,
            'out_of_stock' => 22,
            'total_value' => 8500000
        ];
    }
    
    /**
     * Top produits par valeur
     */
    private function getTopProductsByValue($category, $limit = 10)
    {
        // Simulation - Remplacer par vraie requête
        $products = [];
        for ($i = 1; $i <= $limit; $i++) {
            $qty = rand(10, 200);
            $price = rand(5000, 50000);
            $products[] = [
                'product_name' => 'Produit Stock ' . $i,
                'quantity' => $qty,
                'unit' => 'pcs',
                'unit_price' => $price,
                'value' => $qty * $price
            ];
        }
        return $products;
    }
    
    /**
     * Statistiques des projets
     */
    private function getProjectsStats($client, $codeProjet)
    {
        // Simulation - Remplacer par vraies requêtes
        return [
            'total_projects' => 23,
            'total_items' => 456,
            'total_amount' => 18500000,
            'avg_duration' => 45
        ];
    }
    
    /**
     * Projets récents
     */
    private function getRecentProjects($client, $codeProjet, $limit = 10)
    {
        // Simulation - Remplacer par vraie requête
        $projects = [];
        for ($i = 1; $i <= $limit; $i++) {
            $projects[] = [
                'code_projet' => '2024' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'nom_client' => 'Client ' . chr(64 + $i),
                'chefprojet' => 'Chef ' . $i,
                'created_at' => date('Y-m-d', strtotime('-' . rand(1, 90) . ' days')),
                'total_items' => rand(10, 50)
            ];
        }
        return $projects;
    }
    
    /**
     * Statistiques des commandes annulées
     */
    private function getCanceledStats()
    {
        // Simulation - Remplacer par vraies requêtes
        return [
            'total_canceled' => 12,
            'total_amount' => 850000,
            'projects_count' => 8,
            'cancellation_rate' => 5.2
        ];
    }
    
    /**
     * Tendance mensuelle des annulations
     */
    private function getCanceledMonthlyTrend()
    {
        // Simulation - Remplacer par vraie requête
        $trend = [];
        for ($i = 6; $i >= 1; $i--) {
            $trend[] = [
                'month_name' => date('M Y', strtotime('-' . $i . ' months')),
                'count' => rand(0, 5),
                'amount' => rand(0, 200000),
                'evolution' => ($i < 6) ? (rand(-50, 100) . '%') : '-'
            ];
        }
        return $trend;
    }
    
    /**
     * Top produits annulés
     */
    private function getTopCanceledProducts($limit = 10)
    {
        // Simulation - Remplacer par vraie requête
        $products = [];
        for ($i = 1; $i <= $limit; $i++) {
            $products[] = [
                'designation' => 'Produit Annulé ' . chr(64 + $i),
                'count' => rand(1, 5),
                'total_value' => rand(50000, 300000)
            ];
        }
        return $products;
    }
    
    /**
     * Analyse des annulations par projets
     */
    private function getCanceledByProjects($limit = 10)
    {
        // Simulation - Remplacer par vraie requête
        $projects = [];
        for ($i = 1; $i <= $limit; $i++) {
            $projects[] = [
                'code_projet' => '2024' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'nom_client' => 'Client ' . chr(64 + $i),
                'canceled_count' => rand(1, 4),
                'total_value' => rand(50000, 200000),
                'project_rate' => rand(5, 25)
            ];
        }
        return $projects;
    }
    
    // ==========================================
    // MÉTHODES UTILITAIRES HTML/CSS
    // ==========================================
    
    /**
     * CSS optimisé pour des rapports compacts
     */
    private function getCSS()
    {
        return '
        <style>
            body {
                font-family: helvetica, sans-serif;
                font-size: 9px;
                line-height: 1.3;
                color: #333;
                margin: 0;
                padding: 0;
            }
            
            .page-header {
                text-align: center;
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 2px solid #2c3e50;
            }
            
            .page-title {
                font-size: 16px;
                font-weight: bold;
                color: #2c3e50;
                margin: 0 0 3px 0;
            }
            
            .page-subtitle {
                font-size: 10px;
                color: #7f8c8d;
                margin: 0;
            }
            
            .section {
                margin-bottom: 12px;
                page-break-inside: avoid;
            }
            
            .section-title {
                font-size: 11px;
                font-weight: bold;
                color: #34495e;
                margin: 0 0 6px 0;
                padding: 4px 8px;
                background-color: #ecf0f1;
                border-left: 3px solid #3498db;
            }
            
            .stats-grid {
                display: table;
                width: 100%;
                margin-bottom: 10px;
            }
            
            .stats-row {
                display: table-row;
            }
            
            .stats-cell {
                display: table-cell;
                width: 50%;
                padding: 4px 8px;
                border: 1px solid #bdc3c7;
                vertical-align: top;
            }
            
            .stats-label {
                font-weight: bold;
                background-color: #f8f9fa;
            }
            
            .compact-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 8px;
                margin-bottom: 10px;
            }
            
            .compact-table th {
                background-color: #34495e;
                color: white;
                padding: 4px 6px;
                text-align: left;
                font-weight: bold;
                border: 1px solid #2c3e50;
            }
            
            .compact-table td {
                padding: 3px 6px;
                border: 1px solid #ddd;
                vertical-align: top;
            }
            
            .compact-table tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            .error-box {
                background-color: #ffebee;
                color: #c62828;
                padding: 8px;
                border-left: 3px solid #e74c3c;
                margin: 10px 0;
                font-size: 8px;
            }
            
            .recommendations {
                background-color: #e8f5e8;
                padding: 8px;
                border-left: 3px solid #27ae60;
                font-size: 8px;
            }
            
            .recommendations ul {
                margin: 5px 0 0 15px;
                padding: 0;
            }
            
            .recommendations li {
                margin-bottom: 3px;
            }
            
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .font-bold { font-weight: bold; }
        </style>';
    }
    
    /**
     * En-tête de page optimisé
     */
    private function getPageHeader($title, $subtitle = '')
    {
        $html = '<div class="page-header">';
        $html .= '<div class="page-title">' . htmlspecialchars($title) . '</div>';
        if ($subtitle) {
            $html .= '<div class="page-subtitle">' . htmlspecialchars($subtitle) . '</div>';
        }
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Section avec titre
     */
    private function createSection($title)
    {
        return '<div class="section-title">' . htmlspecialchars($title) . '</div>';
    }
    
    /**
     * Statistiques en 2 colonnes
     */
    private function createTwoColumnStats($data)
    {
        $html = '<div class="stats-grid">';
        $items = array_chunk($data, 2, true);
        
        foreach ($items as $chunk) {
            foreach ($chunk as $label => $value) {
                $html .= '<div class="stats-row">';
                $html .= '<div class="stats-cell stats-label">' . htmlspecialchars($label) . '</div>';
                $html .= '<div class="stats-cell">' . htmlspecialchars($value) . '</div>';
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Tableau compact
     */
    private function createCompactTable($headers, $data)
    {
        $html = '<table class="compact-table">';
        
        // En-têtes
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead>';
        
        // Données
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        return $html;
    }
    
    /**
     * Boîte d'erreur
     */
    private function createErrorBox($message)
    {
        return '<div class="error-box"><strong>Erreur:</strong> ' . htmlspecialchars($message) . '</div>';
    }
    
    /**
     * Liste de recommandations
     */
    private function createRecommendationsList()
    {
        $html = '<div class="recommendations">';
        $html .= '<strong>Actions préventives recommandées :</strong>';
        $html .= '<ul>';
        $html .= '<li>Améliorer la validation avant commande</li>';
        $html .= '<li>Renforcer la communication avec les clients</li>';
        $html .= '<li>Négocier de meilleurs délais avec les fournisseurs</li>';
        $html .= '<li>Mettre en place des alertes pour les projets à risque</li>';
        $html .= '<li>Former les équipes aux bonnes pratiques</li>';
        $html .= '</ul>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Formate un nombre
     */
    private function formatNumber($number, $decimals = 0)
    {
        if ($number == 0) return '0';
        return number_format($number, $decimals, ',', ' ');
    }
    
    /**
     * Tronque un texte
     */
    private function truncateText($text, $length)
    {
        return (strlen($text) > $length) ? substr($text, 0, $length) . '...' : $text;
    }
    
    /**
     * Texte de période
     */
    private function getPeriodText($period)
    {
        switch ($period) {
            case '3': return '(3 derniers mois)';
            case '6': return '(6 derniers mois)';
            case '12': return '(12 derniers mois)';
            default: return '(Toute la période)';
        }
    }
    
    /**
     * Sortie du PDF
     */
    public function output($filename = null)
    {
        if (!$filename) {
            $filename = 'Rapport_' . ucfirst($_GET['type'] ?? 'statistiques') . '_' . date('Ymd_His') . '.pdf';
        }
        
        return $this->mpdf->Output($filename, 'I');
    }
}

// ==========================================
// EXÉCUTION PRINCIPALE
// ==========================================

try {
    // Créer le générateur de rapport optimisé
    $generator = new OptimizedReportGenerator($pdo);
    
    // Paramètres pour le rapport
    $params = [
        'year' => $year,
        'period' => $period,
        'category' => $category,
        'client' => $client,
        'code_projet' => $code_projet
    ];
    
    // Générer le rapport
    $generator->generateReport($reportType, $params);
    
    // Nom du fichier de sortie
    $filename = 'Rapport_' . ucfirst($reportType) . '_' . date('Ymd_His') . '.pdf';
    
    // Générer et télécharger le PDF
    $generator->output($filename);
    
} catch (Exception $e) {
    // Gestion des erreurs
    http_response_code(500);
    echo "Erreur lors de la génération du rapport PDF : " . htmlspecialchars($e->getMessage());
    error_log("Erreur PDF DYM: " . $e->getMessage() . " - " . $e->getFile() . ":" . $e->getLine());
}
?>