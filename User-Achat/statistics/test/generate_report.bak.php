<?php
/**
 * Générateur de rapports PDF avec mPDF
 * 
 * Ce fichier génère des rapports PDF statistiques pour le service achat
 * Utilise mPDF pour une génération plus simple et efficace
 * 
 * @package DYM_MANUFACTURE
 * @subpackage expressions_besoins/User-Achat/statistics
 * @version 3.0
 * @author Équipe DYM
 */

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../index.php");
    exit();
}

// Inclure la bibliothèque mPDF
require_once('../../vendor/autoload.php'); // Si installé via Composer
// OU
// require_once('../mpdf/mpdf.php'); // Si installé manuellement

// Connexion à la base de données
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Récupérer les paramètres du rapport
$reportType = $_GET['type'] ?? 'dashboard';
$year = $_GET['year'] ?? date('Y');
$period = $_GET['period'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$client = $_GET['client'] ?? 'all';
$code_projet = $_GET['code_projet'] ?? null;
$view = $_GET['view'] ?? 'amount';

/**
 * Classe de génération de rapports PDF avec mPDF
 */
class ReportGenerator
{
    private $mpdf;
    private $pdo;
    private $reportData;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        
        // Configuration mPDF
        $config = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 10,
            'default_font' => 'helvetica',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 25,
            'margin_bottom' => 25,
            'margin_header' => 10,
            'margin_footer' => 10,
            'orientation' => 'P',
            'tempDir' => sys_get_temp_dir()
        ];

        $this->mpdf = new \Mpdf\Mpdf($config);
        
        // Métadonnées du document
        $this->mpdf->SetTitle('Rapport de Statistiques - DYM MANUFACTURE');
        $this->mpdf->SetAuthor('DYM MANUFACTURE');
        $this->mpdf->SetCreator('Système de Gestion DYM');
        $this->mpdf->SetSubject('Statistiques ' . ucfirst($_GET['type'] ?? 'dashboard'));
        
        // Configuration des en-têtes et pieds de page
        $this->setupHeaderFooter();
    }

    /**
     * Configuration de l'en-tête et du pied de page
     */
    private function setupHeaderFooter()
    {
        // En-tête
        $header = '
        <table width="100%" style="border-bottom: 1px solid #ccc; padding-bottom: 10px;">
            <tr>
                <td width="80">
                    <img src="../../public/logo.png" width="60" height="40" alt="DYM Logo">
                </td>
                <td style="text-align: center; vertical-align: middle;">
                    <h2 style="margin: 0; color: #333; font-size: 16px;">DYM MANUFACTURE</h2>
                    <p style="margin: 0; color: #666; font-size: 12px;">Rapport de Statistiques</p>
                </td>
                <td width="80" style="text-align: right; vertical-align: middle; font-size: 10px; color: #666;">
                    Généré le<br>' . date('d/m/Y H:i') . '
                </td>
            </tr>
        </table>';

        // Pied de page
        $footer = '
        <table width="100%" style="border-top: 1px solid #ccc; padding-top: 5px; font-size: 9px; color: #666;">
            <tr>
                <td width="33%">DYM MANUFACTURE</td>
                <td width="33%" style="text-align: center;">
                    Confidentiel - Usage interne uniquement
                </td>
                <td width="33%" style="text-align: right;">
                    Page {PAGENO} sur {nbpg}
                </td>
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
                    $this->generateAchatsReport($params['year'] ?? date('Y'), $params['view'] ?? 'amount');
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
                case 'projet_group_details':
                    $this->generateProjetGroupDetailsReport($params['code_projet']);
                    break;
                default:
                    throw new Exception('Type de rapport non reconnu: ' . $type);
            }
        } catch (Exception $e) {
            $this->generateErrorReport($e->getMessage());
        }
    }

    /**
     * Génère le rapport du tableau de bord
     */
    private function generateDashboardReport()
    {
        $html = $this->getReportHeader('RAPPORT DE TABLEAU DE BORD', 'Vue d\'ensemble des achats et du stock');

        try {
            // Récupération des statistiques
            $stats = $this->getDashboardStatistics();
            
            $html .= '<div class="section">';
            $html .= '<h2>Statistiques Générales</h2>';
            $html .= $this->createStatsTable([
                'Produits en stock' => $this->formatNumber($stats['total_products']),
                'Valeur du stock' => $this->formatNumber($stats['total_stock_value']) . ' FCFA',
                'Achats du mois' => $this->formatNumber($stats['current_month_amount']) . ' FCFA',
                'Commandes en attente' => $this->formatNumber($stats['pending_orders'])
            ]);
            $html .= '</div>';

            // Top fournisseurs
            $topSuppliers = $this->getTopSuppliers();
            if (!empty($topSuppliers)) {
                $html .= '<div class="section">';
                $html .= '<h2>Top 5 Fournisseurs</h2>';
                $html .= $this->createDataTable(
                    ['Fournisseur', 'Commandes', 'Montant'],
                    array_map(function($supplier) {
                        return [
                            $supplier['nom'] ?? 'Non spécifié',
                            $this->formatNumber($supplier['orders']),
                            $this->formatNumber($supplier['amount']) . ' FCFA'
                        ];
                    }, $topSuppliers)
                );
                $html .= '</div>';
            }

            // Note de conclusion
            $html .= $this->getReportFooter('dashboard');

        } catch (PDOException $e) {
            $html .= '<div class="error">Erreur lors de la génération du rapport: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }

    /**
     * Génère le rapport des achats
     */
    private function generateAchatsReport($year, $view = 'amount')
    {
        $html = $this->getReportHeader('RAPPORT DES ACHATS - ' . $year, 'Analyse détaillée des achats pour l\'année ' . $year);

        try {
            // Statistiques générales
            $yearStats = $this->getYearStatistics($year);
            
            $html .= '<div class="section">';
            $html .= '<h2>Statistiques Générales ' . $year . '</h2>';
            $html .= $this->createStatsTable([
                'Commandes totales' => $this->formatNumber($yearStats['total_orders']),
                'Montant total' => $this->formatNumber($yearStats['total_amount']) . ' FCFA',
                'Valeur moyenne' => $this->formatNumber($yearStats['average_order_value']) . ' FCFA',
                'Fournisseurs actifs' => $this->formatNumber($yearStats['supplier_count'])
            ]);
            $html .= '</div>';

            // Répartition trimestrielle
            $quarterlyData = $this->getQuarterlyData($year);
            if (!empty($quarterlyData)) {
                $html .= '<div class="section">';
                $html .= '<h2>Répartition Trimestrielle</h2>';
                $html .= $this->createDataTable(
                    ['Trimestre', 'Commandes', 'Montant', '% du total'],
                    array_map(function($quarter) use ($yearStats) {
                        $percentage = $yearStats['total_amount'] > 0
                            ? round(($quarter['amount'] / $yearStats['total_amount']) * 100, 1) . '%'
                            : '0%';
                        return [
                            $this->getQuarterName($quarter['quarter']),
                            $this->formatNumber($quarter['orders']),
                            $this->formatNumber($quarter['amount']) . ' FCFA',
                            $percentage
                        ];
                    }, $quarterlyData)
                );
                $html .= '</div>';
            }

            // Top 5 des produits
            $topProducts = $this->getTopProducts($year);
            if (!empty($topProducts)) {
                $html .= '<div class="section">';
                $html .= '<h2>Top 5 des Produits les Plus Achetés</h2>';
                $html .= $this->createDataTable(
                    ['Produit', 'Commandes', 'Quantité', 'Montant'],
                    array_map(function($product) {
                        return [
                            $product['designation'],
                            $this->formatNumber($product['order_count']),
                            $this->formatNumber($product['total_quantity']),
                            $this->formatNumber($product['total_amount']) . ' FCFA'
                        ];
                    }, array_slice($topProducts, 0, 5))
                );
                $html .= '</div>';
            }

            $html .= $this->getReportFooter('achats');

        } catch (PDOException $e) {
            $html .= '<div class="error">Erreur lors de la génération du rapport: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }

    /**
     * Génère le rapport des fournisseurs
     */
    private function generateFournisseursReport($period = 'all')
    {
        $periodText = $this->getPeriodText($period);
        $html = $this->getReportHeader('RAPPORT DES FOURNISSEURS', 'Analyse des performances et activités des fournisseurs ' . $periodText);

        try {
            // Statistiques générales
            $supplierStats = $this->getSupplierStatistics($period);
            
            $html .= '<div class="section">';
            $html .= '<h2>Vue d\'ensemble des Fournisseurs</h2>';
            $html .= $this->createStatsTable([
                'Fournisseurs totaux' => $this->formatNumber($supplierStats['total_suppliers']),
                'Fournisseurs actifs (6 mois)' => $this->formatNumber($supplierStats['active_suppliers']),
                'Montant total des achats' => $this->formatNumber($supplierStats['total_purchases']) . ' FCFA'
            ]);
            $html .= '</div>';

            // Top fournisseurs
            $topSuppliers = $this->getTopSuppliersDetailed($period);
            if (!empty($topSuppliers)) {
                $html .= '<div class="section">';
                $html .= '<h2>Top 10 Fournisseurs par Volume d\'Achat</h2>';
                $html .= $this->createDataTable(
                    ['Fournisseur', 'Catégorie', 'Commandes', 'Montant', '% du total'],
                    array_map(function($supplier) use ($supplierStats) {
                        $percentage = $supplierStats['total_purchases'] > 0
                            ? round(($supplier['amount'] / $supplierStats['total_purchases']) * 100, 1) . '%'
                            : '0%';
                        return [
                            $supplier['nom'] ?? 'Non spécifié',
                            $supplier['categorie'] ?? 'Non définie',
                            $this->formatNumber($supplier['orders']),
                            $this->formatNumber($supplier['amount']) . ' FCFA',
                            $percentage
                        ];
                    }, array_slice($topSuppliers, 0, 10))
                );
                $html .= '</div>';
            }

            $html .= $this->getReportFooter('fournisseurs');

        } catch (PDOException $e) {
            $html .= '<div class="error">Erreur lors de la génération du rapport: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }

    /**
     * Génère le rapport des produits
     */
    private function generateProduitsReport($category = 'all')
    {
        $categoryText = ($category == 'all') ? 'Toutes catégories' : 'Catégorie spécifique';
        $html = $this->getReportHeader('RAPPORT DES PRODUITS', 'Analyse du stock et des mouvements de produits - ' . $categoryText);

        try {
            // Statistiques générales du stock
            $stockStats = $this->getStockStatistics($category);
            
            $html .= '<div class="section">';
            $html .= '<h2>Statistiques Générales du Stock</h2>';
            $html .= $this->createStatsTable([
                'Produits totaux' => $this->formatNumber($stockStats['total_products']),
                'En stock' => $this->formatNumber($stockStats['in_stock']),
                'Rupture de stock' => $this->formatNumber($stockStats['out_of_stock']),
                'Quantité totale' => $this->formatNumber($stockStats['total_quantity']),
                'Valeur du stock' => $this->formatNumber($stockStats['total_value']) . ' FCFA'
            ]);
            $html .= '</div>';

            // Top produits par valeur
            $topProducts = $this->getTopProductsByValue($category);
            if (!empty($topProducts)) {
                $html .= '<div class="section">';
                $html .= '<h2>Top 10 Produits par Valeur en Stock</h2>';
                $html .= $this->createDataTable(
                    ['Produit', 'Catégorie', 'Quantité', 'Prix Unit.', 'Valeur'],
                    array_map(function($product) {
                        return [
                            $product['product_name'],
                            $product['category'] ?? 'Non définie',
                            $this->formatNumber($product['quantity']) . ' ' . ($product['unit'] ?? ''),
                            $this->formatNumber($product['unit_price']) . ' FCFA',
                            $this->formatNumber($product['value']) . ' FCFA'
                        ];
                    }, array_slice($topProducts, 0, 10))
                );
                $html .= '</div>';
            }

            $html .= $this->getReportFooter('produits');

        } catch (PDOException $e) {
            $html .= '<div class="error">Erreur lors de la génération du rapport: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }

    /**
     * Génère le rapport des projets
     */
    private function generateProjetsReport($selectedClient = 'all', $selectedCodeProjet = null)
    {
        $filterText = ($selectedClient == 'all') ? 'Tous clients' : 'Client: ' . $selectedClient;
        if (!empty($selectedCodeProjet)) {
            $filterText .= ' - Projet: ' . $selectedCodeProjet;
        }

        $html = $this->getReportHeader('RAPPORT DES PROJETS', 'Analyse et suivi des projets clients - ' . $filterText);

        try {
            // Statistiques générales des projets
            $projectStats = $this->getProjectStatistics($selectedClient, $selectedCodeProjet);
            
            $html .= '<div class="section">';
            $html .= '<h2>Vue d\'ensemble des Projets</h2>';
            $html .= $this->createStatsTable([
                'Projets totaux' => $this->formatNumber($projectStats['total_projects']),
                'Articles' => $this->formatNumber($projectStats['total_items']),
                'Montant total' => $this->formatNumber($projectStats['total_amount']) . ' FCFA',
                'Durée moyenne' => $this->formatNumber($projectStats['avg_duration']) . ' jours'
            ]);
            $html .= '</div>';

            // Si un projet spécifique
            if (!empty($selectedCodeProjet)) {
                $projectDetails = $this->getProjectDetails($selectedCodeProjet);
                if ($projectDetails) {
                    $html .= '<div class="section">';
                    $html .= '<h2>Détails du Projet: ' . htmlspecialchars($projectDetails['code_projet']) . '</h2>';
                    $html .= '<div class="project-info">';
                    $html .= '<p><strong>Client:</strong> ' . htmlspecialchars($projectDetails['nom_client']) . '</p>';
                    $html .= '<p><strong>Chef de projet:</strong> ' . htmlspecialchars($projectDetails['chefprojet']) . '</p>';
                    $html .= '<p><strong>Date de création:</strong> ' . date('d/m/Y', strtotime($projectDetails['created_at'])) . '</p>';
                    $html .= '<p><strong>Description:</strong> ' . htmlspecialchars($projectDetails['description_projet']) . '</p>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
            } else {
                // Liste des projets récents
                $recentProjects = $this->getRecentProjects($selectedClient);
                if (!empty($recentProjects)) {
                    $html .= '<div class="section">';
                    $html .= '<h2>Projets Récents</h2>';
                    $html .= $this->createDataTable(
                        ['Code Projet', 'Client', 'Chef Projet', 'Date', 'Articles', 'Montant'],
                        array_map(function($project) {
                            return [
                                $project['code_projet'],
                                $project['nom_client'],
                                $project['chefprojet'],
                                date('d/m/Y', strtotime($project['created_at'])),
                                $this->formatNumber($project['total_items']),
                                $this->formatNumber($project['total_amount']) . ' FCFA'
                            ];
                        }, array_slice($recentProjects, 0, 10))
                    );
                    $html .= '</div>';
                }
            }

            $html .= $this->getReportFooter('projets');

        } catch (PDOException $e) {
            $html .= '<div class="error">Erreur lors de la génération du rapport: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }

    /**
     * Génère le rapport des commandes annulées
     */
    private function generateCanceledOrdersReport()
    {
        $html = $this->getReportHeader('RAPPORT DES COMMANDES ANNULÉES', 'Analyse des commandes annulées et tendances');

        try {
            // Statistiques générales
            $canceledStats = $this->getCanceledStatistics();
            
            $html .= '<div class="section">';
            $html .= '<h2>Statistiques Générales</h2>';
            $html .= $this->createStatsTable([
                'Commandes annulées' => $this->formatNumber($canceledStats['total_canceled']),
                'Montant total' => $this->formatNumber($canceledStats['total_amount']) . ' FCFA',
                'Projets concernés' => $this->formatNumber($canceledStats['projects_count'])
            ]);
            $html .= '</div>';

            // Top produits annulés
            $topCanceledProducts = $this->getTopCanceledProducts();
            if (!empty($topCanceledProducts)) {
                $html .= '<div class="section">';
                $html .= '<h2>Top 5 des Produits les Plus Annulés</h2>';
                $html .= $this->createDataTable(
                    ['Produit', 'Nombre d\'annulations'],
                    array_map(function($product) {
                        return [
                            $product['designation'],
                            $this->formatNumber($product['count'])
                        ];
                    }, array_slice($topCanceledProducts, 0, 5))
                );
                $html .= '</div>';
            }

            $html .= $this->getReportFooter('canceled');

        } catch (PDOException $e) {
            $html .= '<div class="error">Erreur lors de la génération du rapport: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }

    /**
     * Génère le rapport détaillé d'un groupe de projets
     */
    private function generateProjetGroupDetailsReport($codeProjet)
    {
        $html = $this->getReportHeader('DÉTAILS DU GROUPE DE PROJETS: ' . strtoupper($codeProjet), 'Analyse détaillée du groupe de projets');

        try {
            // Informations du groupe de projets
            $projectInfo = $this->getProjectGroupInfo($codeProjet);
            if ($projectInfo) {
                $html .= '<div class="section">';
                $html .= '<h2>Informations du Groupe</h2>';
                $html .= '<div class="project-info">';
                $html .= '<p><strong>Code Projet:</strong> ' . htmlspecialchars($projectInfo['code_projet']) . '</p>';
                $html .= '<p><strong>Client(s):</strong> ' . htmlspecialchars($projectInfo['clients_list']) . '</p>';
                $html .= '<p><strong>Chef(s) de projet:</strong> ' . htmlspecialchars($projectInfo['project_managers']) . '</p>';
                $html .= '<p><strong>Nombre de projets:</strong> ' . $projectInfo['project_count'] . ' projet(s)</p>';
                $html .= '<p><strong>Période d\'activité:</strong> Du ' . date('d/m/Y', strtotime($projectInfo['earliest_creation'])) . ' au ' . date('d/m/Y', strtotime($projectInfo['latest_creation'])) . '</p>';
                $html .= '</div>';
                $html .= '</div>';

                // Statistiques du groupe
                $groupStats = $this->getProjectGroupStatistics($codeProjet);
                $html .= '<div class="section">';
                $html .= '<h2>Statistiques Groupées</h2>';
                $html .= $this->createStatsTable([
                    'Total des matériaux' => $this->formatNumber($groupStats['total_items']) . ' articles',
                    'Valeur totale' => $this->formatNumber($groupStats['total_amount']) . ' FCFA',
                    'En attente' => $this->formatNumber($groupStats['pending_items']),
                    'Commandés' => $this->formatNumber($groupStats['ordered_items']),
                    'Reçus' => $this->formatNumber($groupStats['received_items']),
                    'Annulés' => $this->formatNumber($groupStats['canceled_items'])
                ]);
                $html .= '</div>';
            }

            $html .= $this->getReportFooter('projet_group_details');

        } catch (PDOException $e) {
            $html .= '<div class="error">Erreur lors de la génération du rapport: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }

    /**
     * Génère un rapport d'erreur
     */
    private function generateErrorReport($errorMessage)
    {
        $html = $this->getReportHeader('ERREUR DE GÉNÉRATION', 'Une erreur est survenue lors de la génération du rapport');
        
        $html .= '<div class="section error">';
        $html .= '<h2>Erreur</h2>';
        $html .= '<p>Une erreur est survenue lors de la génération du rapport :</p>';
        $html .= '<p class="error-message">' . htmlspecialchars($errorMessage) . '</p>';
        $html .= '<p>Veuillez vérifier les paramètres et réessayer.</p>';
        $html .= '</div>';

        $this->mpdf->WriteHTML($this->getCSS() . $html);
    }

    /**
     * Sortie du PDF
     */
    public function output($filename = null)
    {
        if (!$filename) {
            $filename = 'Rapport_' . date('Ymd_His') . '.pdf';
        }
        
        return $this->mpdf->Output($filename, 'I');
    }

    // ==========================================
    // MÉTHODES UTILITAIRES ET DONNÉES
    // ==========================================

    /**
     * Récupère les statistiques du tableau de bord
     */
    private function getDashboardStatistics()
    {
        // Total des produits en stock
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM products WHERE quantity > 0");
        $stmt->execute();
        $totalProducts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Valeur totale du stock
        $stmt = $this->pdo->prepare("SELECT SUM(quantity * unit_price) as total_value FROM products WHERE quantity > 0");
        $stmt->execute();
        $totalStockValue = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

        // Achats du mois en cours
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(prix_unitaire * quantity), 0) as total 
                                  FROM achats_materiaux 
                                  WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
                                  AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $stmt->execute();
        $currentMonthPurchases = $stmt->fetch(PDO::FETCH_ASSOC);

        // Nombre de commandes en attente
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count 
                                  FROM expression_dym 
                                  WHERE qt_acheter > 0 
                                  AND (valide_achat = 'pas validé' OR valide_achat IS NULL)
                                  AND " . getFilteredDateCondition());
        $stmt->execute();
        $pendingOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        return [
            'total_products' => $totalProducts,
            'total_stock_value' => $totalStockValue,
            'current_month_count' => $currentMonthPurchases['count'],
            'current_month_amount' => $currentMonthPurchases['total'],
            'pending_orders' => $pendingOrders
        ];
    }

    /**
     * Récupère le top 5 des fournisseurs
     */
    private function getTopSuppliers()
    {
        $stmt = $this->pdo->prepare("SELECT 
                                    f.nom, 
                                    COUNT(am.id) as orders,
                                    COALESCE(SUM(am.prix_unitaire * am.quantity), 0) as amount
                                   FROM achats_materiaux am
                                   LEFT JOIN fournisseurs f ON am.fournisseur = f.nom
                                   GROUP BY f.nom
                                   ORDER BY amount DESC
                                   LIMIT 5");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques annuelles
     */
    private function getYearStatistics($year)
    {
        $stmt = $this->pdo->prepare("SELECT 
                                    COUNT(*) as total_orders,
                                    COALESCE(SUM(prix_unitaire * quantity), 0) as total_amount,
                                    AVG(prix_unitaire * quantity) as average_order_value,
                                    COUNT(DISTINCT fournisseur) as supplier_count
                                   FROM achats_materiaux
                                   WHERE YEAR(created_at) = :year");
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les données trimestrielles
     */
    private function getQuarterlyData($year)
    {
        $stmt = $this->pdo->prepare("SELECT 
                                    QUARTER(created_at) as quarter,
                                    COUNT(*) as orders,
                                    COALESCE(SUM(prix_unitaire * quantity), 0) as amount
                                   FROM achats_materiaux
                                   WHERE YEAR(created_at) = :year
                                   GROUP BY QUARTER(created_at)
                                   ORDER BY quarter");
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les top produits pour une année
     */
    private function getTopProducts($year)
    {
        $stmt = $this->pdo->prepare("SELECT 
                                    designation,
                                    COUNT(*) as order_count,
                                    SUM(quantity) as total_quantity,
                                    COALESCE(SUM(prix_unitaire * quantity), 0) as total_amount
                                   FROM achats_materiaux
                                   WHERE YEAR(created_at) = :year
                                   GROUP BY designation
                                   ORDER BY total_amount DESC");
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques des fournisseurs
     */
    private function getSupplierStatistics($period)
    {
        // Nombre total de fournisseurs
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM fournisseurs");
        $stmt->execute();
        $totalSuppliers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Fournisseurs actifs
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT fournisseur) as active 
                                  FROM achats_materiaux 
                                  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)");
        $stmt->execute();
        $activeSuppliers = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

        // Condition de période
        $periodCondition = "";
        if ($period != 'all') {
            $periodCondition = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
        }

        // Montant total des achats
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(prix_unitaire * quantity), 0) as total 
                                  FROM achats_materiaux
                                  WHERE 1=1 " . $periodCondition);
        $stmt->execute();
        $totalPurchases = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'total_suppliers' => $totalSuppliers,
            'active_suppliers' => $activeSuppliers,
            'total_purchases' => $totalPurchases
        ];
    }

    /**
     * Récupère les top fournisseurs détaillés
     */
    private function getTopSuppliersDetailed($period)
    {
        $periodCondition = "";
        if ($period != 'all') {
            $periodCondition = "AND am.created_at >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)";
        }

        $stmt = $this->pdo->prepare("SELECT 
                                    f.nom, 
                                    fc.categorie,
                                    COUNT(am.id) as orders,
                                    COALESCE(SUM(am.prix_unitaire * am.quantity), 0) as amount
                                   FROM achats_materiaux am
                                   LEFT JOIN fournisseurs f ON am.fournisseur = f.nom
                                   LEFT JOIN fournisseur_categories fc ON f.id = fc.fournisseur_id
                                   WHERE 1=1 " . $periodCondition . "
                                   GROUP BY f.nom, fc.categorie
                                   ORDER BY amount DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques du stock
     */
    private function getStockStatistics($category)
    {
        $categoryCondition = ($category != 'all') ? "AND p.category = :category_id" : "";

        $stmt = $this->pdo->prepare("SELECT 
                                    COUNT(*) as total_products,
                                    COUNT(CASE WHEN quantity > 0 THEN 1 END) as in_stock,
                                    COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock,
                                    SUM(quantity) as total_quantity,
                                    SUM(quantity * unit_price) as total_value
                                   FROM products p
                                   WHERE 1=1 $categoryCondition");

        if ($category != 'all') {
            $stmt->bindParam(':category_id', $category, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les top produits par valeur
     */
    private function getTopProductsByValue($category)
    {
        $categoryCondition = ($category != 'all') ? "AND p.category = :category_id" : "";

        $stmt = $this->pdo->prepare("SELECT 
                                    p.product_name,
                                    p.quantity,
                                    p.unit,
                                    p.unit_price,
                                    (p.quantity * p.unit_price) as value,
                                    c.libelle as category
                                   FROM products p
                                   LEFT JOIN categories c ON p.category = c.id
                                   WHERE p.quantity > 0 $categoryCondition
                                   ORDER BY value DESC");

        if ($category != 'all') {
            $stmt->bindParam(':category_id', $category, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques des projets
     */
    private function getProjectStatistics($selectedClient, $selectedCodeProjet)
    {
        $clientCondition = ($selectedClient != 'all') ? "AND ip.nom_client = :nom_client" : "";
        $projetCondition = (!empty($selectedCodeProjet)) ? "AND ip.code_projet = :code_projet" : "";

        $stmt = $this->pdo->prepare("SELECT 
                                    COUNT(DISTINCT ip.id) as total_projects,
                                    COUNT(DISTINCT ed.id) as total_items,
                                    COALESCE(SUM(ed.qt_acheter * ed.prix_unitaire), 0) as total_amount,
                                    ROUND(AVG(DATEDIFF(CURDATE(), ip.created_at)), 0) as avg_duration
                                   FROM identification_projet ip
                                   LEFT JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                                   WHERE " . getFilteredDateCondition('ip.created_at') . "
                                   $clientCondition $projetCondition");

        if ($selectedClient != 'all') {
            $stmt->bindParam(':nom_client', $selectedClient);
        }
        if (!empty($selectedCodeProjet)) {
            $stmt->bindParam(':code_projet', $selectedCodeProjet);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les détails d'un projet
     */
    private function getProjectDetails($codeProjet)
    {
        $stmt = $this->pdo->prepare("SELECT *
                                   FROM identification_projet
                                   WHERE code_projet = :code_projet
                                   AND " . getFilteredDateCondition('created_at') . "
                                   LIMIT 1");
        $stmt->bindParam(':code_projet', $codeProjet);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les projets récents
     */
    private function getRecentProjects($selectedClient)
    {
        $clientCondition = ($selectedClient != 'all') ? "AND ip.nom_client = :nom_client" : "";

        $stmt = $this->pdo->prepare("SELECT 
                                    ip.code_projet,
                                    ip.nom_client,
                                    ip.description_projet,
                                    ip.chefprojet,
                                    ip.created_at,
                                    COUNT(ed.id) as total_items,
                                    COALESCE(SUM(ed.qt_acheter * ed.prix_unitaire), 0) as total_amount
                                   FROM identification_projet ip
                                   LEFT JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                                   WHERE " . getFilteredDateCondition('ip.created_at') . "
                                   $clientCondition
                                   GROUP BY ip.code_projet, ip.nom_client, ip.description_projet, ip.chefprojet, ip.created_at
                                   ORDER BY ip.created_at DESC");

        if ($selectedClient != 'all') {
            $stmt->bindParam(':nom_client', $selectedClient);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques des commandes annulées
     */
    private function getCanceledStatistics()
    {
        // Nombre total de commandes annulées
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM expression_dym WHERE valide_achat = 'annulé'");
        $stmt->execute();
        $totalCanceled = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Montant total des commandes annulées
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(qt_acheter * prix_unitaire), 0) as total_amount 
                                  FROM expression_dym 
                                  WHERE valide_achat = 'annulé' AND prix_unitaire IS NOT NULL");
        $stmt->execute();
        $totalAmount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'];

        // Nombre de projets concernés
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT idExpression) as count 
                                  FROM expression_dym 
                                  WHERE valide_achat = 'annulé'");
        $stmt->execute();
        $projectsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        return [
            'total_canceled' => $totalCanceled,
            'total_amount' => $totalAmount,
            'projects_count' => $projectsCount
        ];
    }

    /**
     * Récupère les top produits annulés
     */
    private function getTopCanceledProducts()
    {
        $stmt = $this->pdo->prepare("SELECT designation, COUNT(*) as count 
                                  FROM expression_dym 
                                  WHERE valide_achat = 'annulé' 
                                  GROUP BY designation 
                                  ORDER BY count DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les informations d'un groupe de projets
     */
    private function getProjectGroupInfo($codeProjet)
    {
        $stmt = $this->pdo->prepare("SELECT 
                                    ip.code_projet,
                                    GROUP_CONCAT(DISTINCT ip.nom_client ORDER BY ip.nom_client SEPARATOR ', ') as clients_list,
                                    COUNT(DISTINCT ip.id) as project_count,
                                    GROUP_CONCAT(DISTINCT ip.chefprojet ORDER BY ip.created_at SEPARATOR ', ') as project_managers,
                                    MIN(ip.created_at) as earliest_creation,
                                    MAX(ip.created_at) as latest_creation
                                   FROM identification_projet ip
                                   WHERE ip.code_projet = :code_projet
                                   AND ip.created_at >= '" . getSystemStartDate() . "'
                                   GROUP BY ip.code_projet");
        $stmt->bindParam(':code_projet', $codeProjet);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques d'un groupe de projets
     */
    private function getProjectGroupStatistics($codeProjet)
    {
        $stmt = $this->pdo->prepare("SELECT 
                                    COUNT(ed.id) as total_items,
                                    COALESCE(SUM(ed.qt_acheter * ed.prix_unitaire), 0) as total_amount,
                                    COUNT(CASE WHEN ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL THEN 1 END) as pending_items,
                                    COUNT(CASE WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'en_cours' THEN 1 END) as ordered_items,
                                    COUNT(CASE WHEN ed.valide_achat = 'reçu' THEN 1 END) as received_items,
                                    COUNT(CASE WHEN ed.valide_achat = 'annulé' THEN 1 END) as canceled_items
                                   FROM identification_projet ip
                                   JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                                   WHERE ip.code_projet = :code_projet
                                   AND ip.created_at >= '" . getSystemStartDate() . "'");
        $stmt->bindParam(':code_projet', $codeProjet);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // MÉTHODES UTILITAIRES POUR LE HTML/CSS
    // ==========================================

    /**
     * Retourne le CSS pour le PDF
     */
    private function getCSS()
    {
        return '
        <style>
            body {
                font-family: helvetica, sans-serif;
                font-size: 10px;
                color: #333;
                line-height: 1.4;
            }
            
            h1 {
                color: #2c3e50;
                font-size: 18px;
                text-align: center;
                margin-bottom: 5px;
                border-bottom: 2px solid #3498db;
                padding-bottom: 10px;
            }
            
            h2 {
                color: #34495e;
                font-size: 14px;
                margin: 15px 0 10px 0;
                border-left: 4px solid #3498db;
                padding-left: 10px;
                background-color: #ecf0f1;
                padding: 8px;
            }
            
            h3 {
                color: #2c3e50;
                font-size: 12px;
                margin: 10px 0 5px 0;
            }
            
            .section {
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
            
            .stats-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
            }
            
            .stats-table td {
                padding: 8px;
                border: 1px solid #bdc3c7;
                vertical-align: top;
            }
            
            .stats-table .label {
                background-color: #ecf0f1;
                font-weight: bold;
                width: 40%;
            }
            
            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
                font-size: 9px;
            }
            
            .data-table th {
                background-color: #3498db;
                color: white;
                padding: 8px 5px;
                text-align: center;
                font-weight: bold;
                border: 1px solid #2980b9;
            }
            
            .data-table td {
                padding: 6px 5px;
                border: 1px solid #bdc3c7;
                text-align: left;
            }
            
            .data-table tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            .data-table tr:hover {
                background-color: #e8f4f8;
            }
            
            .project-info {
                background-color: #f8f9fa;
                padding: 10px;
                border-left: 4px solid #2ecc71;
                margin-bottom: 15px;
            }
            
            .project-info p {
                margin: 5px 0;
                line-height: 1.4;
            }
            
            .error {
                background-color: #ffebee;
                color: #c62828;
                padding: 15px;
                border-left: 4px solid #e74c3c;
                margin: 20px 0;
            }
            
            .error-message {
                font-weight: bold;
                margin: 10px 0;
                font-family: monospace;
                background-color: #fff;
                padding: 10px;
                border: 1px solid #ccc;
            }
            
            .summary {
                background-color: #e8f5e8;
                padding: 15px;
                border: 1px solid #4caf50;
                border-radius: 5px;
                margin: 20px 0;
            }
            
            .note {
                background-color: #fff3cd;
                color: #856404;
                padding: 10px;
                border: 1px solid #ffeaa7;
                border-radius: 3px;
                margin: 15px 0;
                font-style: italic;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .font-bold { font-weight: bold; }
            .text-small { font-size: 8px; }
        </style>';
    }

    /**
     * Retourne l'en-tête du rapport
     */
    private function getReportHeader($title, $subtitle = '')
    {
        $html = '<h1>' . htmlspecialchars($title) . '</h1>';
        if ($subtitle) {
            $html .= '<p class="text-center" style="color: #7f8c8d; margin-bottom: 20px;">' . htmlspecialchars($subtitle) . '</p>';
        }
        return $html;
    }

    /**
     * Retourne le pied de page du rapport
     */
    private function getReportFooter($reportType)
    {
        return '<div class="note">
                    <strong>Note:</strong> Ce rapport présente un aperçu des statistiques sur ' . $this->getReportTypeText($reportType) . '. 
                    Pour une analyse plus détaillée et des visualisations interactives, veuillez consulter le tableau de bord en ligne.
                </div>';
    }

    /**
     * Crée un tableau de statistiques
     */
    private function createStatsTable($data)
    {
        $html = '<table class="stats-table">';
        foreach ($data as $label => $value) {
            $html .= '<tr>';
            $html .= '<td class="label">' . htmlspecialchars($label) . '</td>';
            $html .= '<td>' . htmlspecialchars($value) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    /**
     * Crée un tableau de données
     */
    private function createDataTable($headers, $data)
    {
        $html = '<table class="data-table">';
        
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
     * Formate un nombre
     */
    private function formatNumber($number, $decimals = 0)
    {
        if ($number == 0) return '0';
        return number_format($number, $decimals, ',', ' ');
    }

    /**
     * Retourne le nom du trimestre
     */
    private function getQuarterName($quarter)
    {
        $names = [
            1 => '1er Trimestre',
            2 => '2ème Trimestre',
            3 => '3ème Trimestre',
            4 => '4ème Trimestre'
        ];
        return $names[$quarter] ?? 'T' . $quarter;
    }

    /**
     * Retourne le texte de la période
     */
    private function getPeriodText($period)
    {
        switch ($period) {
            case '3': return '(3 derniers mois)';
            case '6': return '(6 derniers mois)';
            case '12': return '(12 derniers mois)';
            default: return '(Tout l\'historique)';
        }
    }

    /**
     * Retourne le texte du type de rapport
     */
    private function getReportTypeText($type)
    {
        switch ($type) {
            case 'dashboard': return 'le tableau de bord';
            case 'achats': return 'les achats';
            case 'fournisseurs': return 'les fournisseurs';
            case 'produits': return 'les produits et le stock';
            case 'projets': return 'les projets';
            case 'canceled': return 'les commandes annulées';
            case 'projet_group_details': return 'les détails du groupe de projets';
            default: return 'les données';
        }
    }
}

// ==========================================
// TRAITEMENT PRINCIPAL
// ==========================================

try {
    // Créer le générateur de rapport
    $reportGenerator = new ReportGenerator($pdo);
    
    // Préparer les paramètres
    $params = [
        'year' => $year,
        'period' => $period,
        'category' => $category,
        'client' => $client,
        'code_projet' => $code_projet,
        'view' => $view
    ];
    
    // Générer le rapport
    $reportGenerator->generateReport($reportType, $params);
    
    // Définir le nom du fichier de sortie
    $filename = 'Rapport_' . ucfirst($reportType) . '_' . date('Ymd_His') . '.pdf';
    
    // Sortir le PDF
    $reportGenerator->output($filename);
    
} catch (Exception $e) {
    // En cas d'erreur, afficher un message d'erreur
    http_response_code(500);
    echo "Erreur lors de la génération du rapport PDF : " . htmlspecialchars($e->getMessage());
    error_log("Erreur génération PDF : " . $e->getMessage());
}
?>