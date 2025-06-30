<?php

/**
 * Page de Détails de Produit - Version Optimisée avec Statistiques Prix
 * Fichier: /User-Achat/statistics/details/product_details.php
 */
session_start();
// Headers pour éviter la mise en cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../index.php");
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';
include_once '../../../include/date_helper.php';

// Récupération de l'ID du produit
$productId = isset($_GET['id']) ? intval($_GET['id']) : null;
if (!$productId) {
    header("Location: ../stats_produits.php");
    exit();
}

try {
    // Récupération des détails complets du produit
    $productDetails = getProductCompleteDetails($pdo, $productId);

    if (!$productDetails) {
        throw new Exception("Produit non trouvé");
    }
} catch (Exception $e) {
    $errorMessage = "Erreur lors de la récupération des données: " . $e->getMessage();
}

/**
 * Fonction complète pour récupérer tous les détails d'un produit
 */
function getProductCompleteDetails($pdo, $productId)
{
    try {
        // Détails de base du produit avec statistiques avancées
        $detailQuery = "
            SELECT 
                p.*,
                c.libelle as category_name,
                (p.quantity * p.unit_price) as stock_value,
                (p.quantity - p.quantity_reserved) as available_quantity,
                
                -- Calculs de seuils
                CASE 
                    WHEN p.quantity = 0 THEN 'rupture'
                    WHEN p.quantity < 5 THEN 'critique'
                    WHEN p.quantity < 10 THEN 'faible'
                    ELSE 'normal'
                END as stock_status,
                
                -- Valeur totale des mouvements sur 12 mois
                (SELECT COALESCE(SUM(sm.quantity * p.unit_price), 0) 
                 FROM stock_movement sm 
                 WHERE sm.product_id = p.id 
                   AND sm.movement_type = 'output' 
                   AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                ) as valeur_sorties_12m,
                
                -- Nombre de mouvements par mois (moyenne)
                (SELECT ROUND(COUNT(*) / 12.0, 1) 
                 FROM stock_movement sm 
                 WHERE sm.product_id = p.id 
                   AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                ) as mouvements_par_mois
                
            FROM products p
            LEFT JOIN categories c ON p.category = c.id
            WHERE p.id = :product_id
        ";

        $detailStmt = $pdo->prepare($detailQuery);
        $detailStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $detailStmt->execute();
        $productDetails = $detailStmt->fetch(PDO::FETCH_ASSOC);

        if (!$productDetails) {
            return null;
        }

        // **NOUVELLES STATISTIQUES PRIX**
        // Prix moyen calculé depuis les achats
        $prixStatsQuery = "
            SELECT 
                -- Prix moyen depuis les achats
                AVG(CASE WHEN am.prix_unitaire > 0 THEN am.prix_unitaire END) as prix_moyen_achats,
                
                -- Dernier prix d'achat
                (SELECT am2.prix_unitaire 
                 FROM achats_materiaux am2 
                 WHERE LOWER(TRIM(am2.designation)) = LOWER(TRIM(:product_name))
                   AND am2.prix_unitaire > 0 
                   AND am2.status != 'annulé'
                 ORDER BY am2.created_at DESC 
                 LIMIT 1) as dernier_prix_achat,
                
                -- Prix minimum et maximum
                MIN(CASE WHEN am.prix_unitaire > 0 THEN am.prix_unitaire END) as prix_min,
                MAX(CASE WHEN am.prix_unitaire > 0 THEN am.prix_unitaire END) as prix_max,
                
                -- Nombre d'achats avec prix
                COUNT(CASE WHEN am.prix_unitaire > 0 THEN 1 END) as nb_achats_avec_prix,
                
                -- Date du dernier achat avec prix
                MAX(CASE WHEN am.prix_unitaire > 0 THEN am.created_at END) as date_dernier_prix
                
            FROM achats_materiaux am
            WHERE LOWER(TRIM(am.designation)) = LOWER(TRIM(:product_name))
              AND am.status != 'annulé'
        ";

        $prixStatsStmt = $pdo->prepare($prixStatsQuery);
        $prixStatsStmt->bindValue(':product_name', $productDetails['product_name']);
        $prixStatsStmt->execute();
        $productDetails['prix_stats'] = $prixStatsStmt->fetch(PDO::FETCH_ASSOC);

        // Évolution des prix (12 derniers mois)
        $evolutionPrixQuery = "
            SELECT 
                DATE_FORMAT(am.created_at, '%Y-%m') as mois,
                CASE 
                    WHEN MONTH(am.created_at) = 1 THEN CONCAT('Janvier ', YEAR(am.created_at))
                    WHEN MONTH(am.created_at) = 2 THEN CONCAT('Février ', YEAR(am.created_at))
                    WHEN MONTH(am.created_at) = 3 THEN CONCAT('Mars ', YEAR(am.created_at))
                    WHEN MONTH(am.created_at) = 4 THEN CONCAT('Avril ', YEAR(am.created_at))
                    WHEN MONTH(am.created_at) = 5 THEN CONCAT('Mai ', YEAR(am.created_at))
                    WHEN MONTH(am.created_at) = 6 THEN CONCAT('Juin ', YEAR(am.created_at))
                    WHEN MONTH(am.created_at) = 7 THEN CONCAT('Juillet ', YEAR(am.created_at))
                    WHEN MONTH(am.created_at) = 8 THEN CONCAT('Août ', YEAR(am.created_at))
                    WHEN MONTH(am.created_at) = 9 THEN CONCAT('Septembre ', YEAR(am.created_at))
                    WHEN MONTH(am.created_at) = 10 THEN CONCAT('Octobre ', YEAR(am.created_at))
                    WHEN MONTH(am.created_at) = 11 THEN CONCAT('Novembre ', YEAR(am.created_at))
                    WHEN MONTH(am.created_at) = 12 THEN CONCAT('Décembre ', YEAR(am.created_at))
                END as mois_format,
                AVG(am.prix_unitaire) as prix_moyen_mois,
                MIN(am.prix_unitaire) as prix_min_mois,
                MAX(am.prix_unitaire) as prix_max_mois,
                COUNT(*) as nb_achats_mois
            FROM achats_materiaux am
            WHERE LOWER(TRIM(am.designation)) = LOWER(TRIM(:product_name))
              AND am.prix_unitaire > 0
              AND am.status != 'annulé'
              AND am.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(am.created_at, '%Y-%m')
            ORDER BY mois ASC
        ";

        $evolutionPrixStmt = $pdo->prepare($evolutionPrixQuery);
        $evolutionPrixStmt->bindValue(':product_name', $productDetails['product_name']);
        $evolutionPrixStmt->execute();
        $productDetails['evolution_prix'] = $evolutionPrixStmt->fetchAll(PDO::FETCH_ASSOC);

        // Statistiques de rotation détaillées
        $rotationQuery = "
            SELECT 
                -- Statistiques 1 mois
                SUM(CASE WHEN sm.movement_type = 'entry' AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN sm.quantity ELSE 0 END) as entrees_1m,
                SUM(CASE WHEN sm.movement_type = 'output' AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN sm.quantity ELSE 0 END) as sorties_1m,
                COUNT(CASE WHEN sm.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END) as mouvements_1m,
                
                -- Statistiques 3 mois
                SUM(CASE WHEN sm.movement_type = 'entry' AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN sm.quantity ELSE 0 END) as entrees_3m,
                SUM(CASE WHEN sm.movement_type = 'output' AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN sm.quantity ELSE 0 END) as sorties_3m,
                COUNT(CASE WHEN sm.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN 1 END) as mouvements_3m,
                
                -- Statistiques 6 mois
                SUM(CASE WHEN sm.movement_type = 'entry' AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN sm.quantity ELSE 0 END) as entrees_6m,
                SUM(CASE WHEN sm.movement_type = 'output' AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN sm.quantity ELSE 0 END) as sorties_6m,
                COUNT(CASE WHEN sm.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN 1 END) as mouvements_6m,
                
                -- Statistiques 12 mois
                SUM(CASE WHEN sm.movement_type = 'entry' AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN sm.quantity ELSE 0 END) as entrees_12m,
                SUM(CASE WHEN sm.movement_type = 'output' AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN sm.quantity ELSE 0 END) as sorties_12m,
                COUNT(CASE WHEN sm.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN 1 END) as mouvements_12m,
                
                -- Fréquences
                ROUND(COUNT(CASE WHEN sm.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END) / 1.0, 1) as freq_1m,
                ROUND(COUNT(CASE WHEN sm.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN 1 END) / 3.0, 1) as freq_3m,
                ROUND(COUNT(CASE WHEN sm.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN 1 END) / 6.0, 1) as freq_6m,
                ROUND(COUNT(CASE WHEN sm.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN 1 END) / 12.0, 1) as freq_12m
                
            FROM stock_movement sm
            WHERE sm.product_id = :product_id
        ";

        $rotationStmt = $pdo->prepare($rotationQuery);
        $rotationStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $rotationStmt->execute();
        $productDetails['rotation_stats'] = $rotationStmt->fetch(PDO::FETCH_ASSOC);

        // Mouvements récents (30 derniers)
        $movementsQuery = "
            SELECT 
                sm.*,
                DATE_FORMAT(sm.created_at, '%d/%m/%Y à %H:%i') as date_formatted,
                CASE 
                    WHEN sm.movement_type = 'entry' THEN 'Entrée'
                    WHEN sm.movement_type = 'output' THEN 'Sortie'
                    WHEN sm.movement_type = 'transfer' THEN 'Transfert'
                    WHEN sm.movement_type = 'return' THEN 'Retour'
                    ELSE sm.movement_type
                END as type_formatted
            FROM stock_movement sm
            WHERE sm.product_id = :product_id
            ORDER BY sm.created_at DESC
            LIMIT 30
        ";

        $movementsStmt = $pdo->prepare($movementsQuery);
        $movementsStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $movementsStmt->execute();
        $productDetails['movements'] = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Achats récents avec détails projet (30 derniers)
        $achatsQuery = "
            SELECT 
                am.*,
                DATE_FORMAT(am.created_at, '%d/%m/%Y à %H:%i') as date_formatted,
                ip.nom_client,
                ip.code_projet,
                ip.description_projet,
                u.name as user_name,
                CASE 
                    WHEN am.status = 'commandé' THEN 'Commandé'
                    WHEN am.status = 'reçu' THEN 'Reçu'
                    WHEN am.status = 'en_cours' THEN 'En cours'
                    WHEN am.status = 'annulé' THEN 'Annulé'
                    ELSE am.status
                END as status_formatted
            FROM achats_materiaux am
            LEFT JOIN identification_projet ip ON am.expression_id = ip.idExpression
            LEFT JOIN users_exp u ON am.user_achat = u.id
            WHERE LOWER(TRIM(am.designation)) = LOWER(TRIM(:product_name))
            ORDER BY am.created_at DESC
            LIMIT 30
        ";

        $achatsStmt = $pdo->prepare($achatsQuery);
        $achatsStmt->bindValue(':product_name', $productDetails['product_name']);
        $achatsStmt->execute();
        $productDetails['achats'] = $achatsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Utilisation dans les projets (30 derniers)
        $projetsQuery = "
            SELECT 
                ed.*,
                ip.nom_client,
                ip.code_projet,
                ip.description_projet,
                ip.chefprojet,
                DATE_FORMAT(ed.created_at, '%d/%m/%Y') as date_formatted,
                CASE 
                    WHEN ed.valide_achat = 'validé' THEN 'Validé'
                    WHEN ed.valide_achat = 'commandé' THEN 'Commandé'
                    WHEN ed.valide_achat = 'reçu' THEN 'Reçu'
                    WHEN ed.valide_achat = 'en_cours' THEN 'En cours'
                    WHEN ed.valide_achat = 'annulé' THEN 'Annulé'
                    ELSE 'Pas validé'
                END as status_formatted
            FROM expression_dym ed
            LEFT JOIN identification_projet ip ON ed.idExpression = ip.idExpression
            WHERE LOWER(TRIM(ed.designation)) = LOWER(TRIM(:product_name))
            ORDER BY ed.created_at DESC
            LIMIT 30
        ";

        $projetsStmt = $pdo->prepare($projetsQuery);
        $projetsStmt->bindValue(':product_name', $productDetails['product_name']);
        $projetsStmt->execute();
        $productDetails['projets'] = $projetsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Évolution mensuelle (24 derniers mois pour avoir plus de données)
        $evolutionQuery = "
            SELECT 
                DATE_FORMAT(sm.created_at, '%Y-%m') as mois,
                CASE 
                    WHEN MONTH(sm.created_at) = 1 THEN CONCAT('Janvier ', YEAR(sm.created_at))
                    WHEN MONTH(sm.created_at) = 2 THEN CONCAT('Février ', YEAR(sm.created_at))
                    WHEN MONTH(sm.created_at) = 3 THEN CONCAT('Mars ', YEAR(sm.created_at))
                    WHEN MONTH(sm.created_at) = 4 THEN CONCAT('Avril ', YEAR(sm.created_at))
                    WHEN MONTH(sm.created_at) = 5 THEN CONCAT('Mai ', YEAR(sm.created_at))
                    WHEN MONTH(sm.created_at) = 6 THEN CONCAT('Juin ', YEAR(sm.created_at))
                    WHEN MONTH(sm.created_at) = 7 THEN CONCAT('Juillet ', YEAR(sm.created_at))
                    WHEN MONTH(sm.created_at) = 8 THEN CONCAT('Août ', YEAR(sm.created_at))
                    WHEN MONTH(sm.created_at) = 9 THEN CONCAT('Septembre ', YEAR(sm.created_at))
                    WHEN MONTH(sm.created_at) = 10 THEN CONCAT('Octobre ', YEAR(sm.created_at))
                    WHEN MONTH(sm.created_at) = 11 THEN CONCAT('Novembre ', YEAR(sm.created_at))
                    WHEN MONTH(sm.created_at) = 12 THEN CONCAT('Décembre ', YEAR(sm.created_at))
                END as mois_format,
                SUM(CASE WHEN sm.movement_type = 'entry' THEN sm.quantity ELSE 0 END) as entrees,
                SUM(CASE WHEN sm.movement_type = 'output' THEN sm.quantity ELSE 0 END) as sorties,
                COUNT(*) as nb_mouvements,
                SUM(CASE WHEN sm.movement_type = 'entry' THEN sm.quantity ELSE -sm.quantity END) as variation_nette
            FROM stock_movement sm
            WHERE sm.product_id = :product_id
              AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
            GROUP BY DATE_FORMAT(sm.created_at, '%Y-%m')
            ORDER BY mois ASC
        ";

        $evolutionStmt = $pdo->prepare($evolutionQuery);
        $evolutionStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $evolutionStmt->execute();
        $productDetails['evolution_mensuelle'] = $evolutionStmt->fetchAll(PDO::FETCH_ASSOC);

        // **STATISTIQUES FOURNISSEURS AMÉLIORÉES AVEC PRIX MOYEN**
        $fournisseursQuery = "
            SELECT 
                am.fournisseur,
                COUNT(*) as nb_commandes,
                SUM(am.quantity) as quantite_totale,
                AVG(CASE WHEN am.prix_unitaire > 0 THEN am.prix_unitaire END) as prix_moyen,
                MIN(CASE WHEN am.prix_unitaire > 0 THEN am.prix_unitaire END) as prix_min,
                MAX(CASE WHEN am.prix_unitaire > 0 THEN am.prix_unitaire END) as prix_max,
                MAX(am.created_at) as derniere_commande,
                DATE_FORMAT(MAX(am.created_at), '%d/%m/%Y') as derniere_commande_format,
                COUNT(CASE WHEN am.prix_unitaire > 0 THEN 1 END) as nb_commandes_avec_prix,
                
                -- Dernière commande avec prix
                (SELECT am2.prix_unitaire 
                 FROM achats_materiaux am2 
                 WHERE am2.fournisseur = am.fournisseur 
                   AND LOWER(TRIM(am2.designation)) = LOWER(TRIM(:product_name))
                   AND am2.prix_unitaire > 0 
                   AND am2.status != 'annulé'
                 ORDER BY am2.created_at DESC 
                 LIMIT 1) as dernier_prix
                 
            FROM achats_materiaux am
            WHERE LOWER(TRIM(am.designation)) = LOWER(TRIM(:product_name))
              AND am.fournisseur IS NOT NULL
              AND am.fournisseur != ''
              AND am.status != 'annulé'
            GROUP BY am.fournisseur
            ORDER BY nb_commandes DESC, derniere_commande DESC
            LIMIT 10
        ";

        $fournisseursStmt = $pdo->prepare($fournisseursQuery);
        $fournisseursStmt->bindValue(':product_name', $productDetails['product_name']);
        $fournisseursStmt->execute();
        $productDetails['fournisseurs'] = $fournisseursStmt->fetchAll(PDO::FETCH_ASSOC);

        // Historique des prix détaillé
        $prixHistoriqueQuery = "
            SELECT 
                ph.*,
                u.name as user_name,
                DATE_FORMAT(ph.date_creation, '%d/%m/%Y à %H:%i') as date_formatted
            FROM prix_historique ph
            LEFT JOIN users_exp u ON ph.user_id = u.id
            WHERE ph.product_id = :product_id
            ORDER BY ph.date_creation DESC
            LIMIT 50
        ";

        $prixHistoriqueStmt = $pdo->prepare($prixHistoriqueQuery);
        $prixHistoriqueStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $prixHistoriqueStmt->execute();
        $productDetails['prix_historique'] = $prixHistoriqueStmt->fetchAll(PDO::FETCH_ASSOC);

        return $productDetails;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des détails du produit: " . $e->getMessage());
        return null;
    }
}

// Fonctions utilitaires
function formatNumber($number)
{
    return number_format(floatval($number), 0, ',', ' ');
}

function formatMoney($amount)
{
    return number_format(floatval($amount), 0, ',', ' ') . ' FCFA';
}

function formatDecimal($number, $decimals = 2)
{
    return number_format(floatval($number), $decimals, ',', ' ');
}

function getStatusClass($status)
{
    $classes = [
        'rupture' => 'bg-red-100 text-red-800',
        'critique' => 'bg-orange-100 text-orange-800',
        'faible' => 'bg-yellow-100 text-yellow-800',
        'normal' => 'bg-green-100 text-green-800'
    ];
    return $classes[$status] ?? 'bg-gray-100 text-gray-800';
}

function getStatusText($status)
{
    $texts = [
        'rupture' => 'Rupture de Stock',
        'critique' => 'Stock Critique',
        'faible' => 'Stock Faible',
        'normal' => 'Stock Normal'
    ];
    return $texts[$status] ?? 'Statut Inconnu';
}

function getMovementTypeClass($type)
{
    $classes = [
        'entry' => 'bg-green-100 text-green-800',
        'output' => 'bg-red-100 text-red-800',
        'transfer' => 'bg-blue-100 text-blue-800',
        'return' => 'bg-purple-100 text-purple-800'
    ];
    return $classes[$type] ?? 'bg-gray-100 text-gray-800';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails - <?php echo htmlspecialchars($productDetails['product_name'] ?? 'Produit'); ?> | Service Achat</title>

    <!-- CSS Optimisé -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <!-- SweetAlert CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .card {
            transition: all 0.3s ease;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            background: white;
        }

        .card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .tab-button {
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-button.active {
            border-color: #3b82f6 !important;
            color: #3b82f6 !important;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .section-title .material-icons {
            margin-right: 0.5rem;
            color: #3b82f6;
        }

        /* DataTables personnalisé */
        .dataTables_wrapper {
            font-family: 'Inter', sans-serif;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate {
            color: #374151;
            font-size: 0.875rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5rem 0.75rem;
            margin: 0 2px;
            border-radius: 0.375rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #3b82f6 !important;
            border-color: #3b82f6 !important;
            color: white !important;
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem;
            font-size: 0.875rem;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        table.dataTable thead th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #1f2937;
            padding: 0.75rem;
        }

        table.dataTable tbody td {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        table.dataTable tbody tr:hover {
            background-color: #f8fafc;
        }

        .dt-buttons {
            margin-bottom: 1rem;
        }

        .dt-button {
            background: #6b7280 !important;
            border: 1px solid #6b7280 !important;
            color: white !important;
            padding: 0.5rem 1rem !important;
            border-radius: 0.375rem !important;
            font-size: 0.875rem !important;
            margin-right: 0.5rem !important;
            transition: all 0.2s !important;
        }

        .dt-button:hover {
            background: #4b5563 !important;
            border-color: #4b5563 !important;
        }

        /* Styles spéciaux pour le tableau fournisseurs */
        #fournisseursTable .bg-green-50 {
            background-color: #f0fdf4 !important;
        }

        #fournisseursTable .bg-blue-50 {
            background-color: #eff6ff !important;
        }

        #fournisseursTable .text-green-800 {
            color: #166534 !important;
            font-weight: 700;
        }

        #fournisseursTable .text-blue-800 {
            color: #1e40af !important;
            font-weight: 700;
        }

        /* Préservation des styles de badges dans les DataTables */
        table.dataTable .stat-badge {
            display: inline-flex !important;
            align-items: center !important;
            padding: 0.25rem 0.75rem !important;
            border-radius: 9999px !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
        }

        table.dataTable .bg-green-100 {
            background-color: #dcfce7 !important;
        }

        table.dataTable .text-green-800 {
            color: #166534 !important;
        }

        table.dataTable .bg-red-100 {
            background-color: #fee2e2 !important;
        }

        table.dataTable .text-red-800 {
            color: #991b1b !important;
        }

        table.dataTable .bg-blue-100 {
            background-color: #dbeafe !important;
        }

        table.dataTable .text-blue-800 {
            color: #1e40af !important;
        }

        table.dataTable .bg-yellow-100 {
            background-color: #fef3c7 !important;
        }

        table.dataTable .text-yellow-800 {
            color: #92400e !important;
        }

        table.dataTable .bg-purple-100 {
            background-color: #e9d5ff !important;
        }

        table.dataTable .text-purple-800 {
            color: #6b21a8 !important;
        }

        .metric-card {
            text-align: center;
            padding: 1.5rem;
        }

        .metric-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .metric-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }

        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(229, 231, 235, 0.3);
            margin-bottom: 20px;
        }

        .chart-container:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }

        .chart-container canvas {
            border-radius: 8px;
        }

        /* Animations et effets pour les graphiques */
        .chart-container {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .chart-container:hover {
            transform: translateY(-5px);
        }

        /* Style pour les titres de graphiques inspiré du dashboard */
        .chart-title {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f3f4f6;
        }

        .chart-title .material-icons {
            margin-right: 0.75rem;
            font-size: 1.5rem;
        }

        .chart-title h4 {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1f2937;
        }

        /* Conteneurs de graphiques avec style dashboard */
        .chart-card {
            border-radius: 1rem;
            background-color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s ease;
            border: 1px solid rgba(229, 231, 235, 0.2);
        }

        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Badge pour les graphiques */
        .chart-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: auto;
        }

        .chart-badge.success {
            background-color: #d1fae5;
            color: #047857;
        }

        .chart-badge.info {
            background-color: #dbeafe;
            color: #1d4ed8;
        }

        .chart-badge.warning {
            background-color: #fef3c7;
            color: #b45309;
        }

        /* Responsive pour les graphiques */
        @media (max-width: 768px) {
            .chart-container {
                height: 300px;
                padding: 15px;
            }

            .chart-card {
                padding: 1rem;
            }

            .chart-card h4 {
                font-size: 1rem;
            }
        }

        /* Loading animation pour les graphiques */
        @keyframes chartLoading {
            0% {
                opacity: 0;
                transform: scale(0.95);
            }

            50% {
                opacity: 0.5;
                transform: scale(0.98);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .chart-container canvas {
            animation: chartLoading 1s ease-out;
        }

        /* Pulse pour les badges */
        .chart-badge {
            animation: pulse 3s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        /* Effets de survol pour les cartes */
        .card-hover-effect {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover-effect:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Gradient de fond pour les sections importantes */
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .gradient-bg-light {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .gradient-bg-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        /* Animation pour les métriques */
        .metric-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .metric-card:hover::before {
            left: 100%;
        }

        .metric-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        /* Animation pour les badges de statut */
        .stat-badge {
            transition: all 0.2s ease;
            position: relative;
        }

        .stat-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        /* Effet de pulse pour les éléments importants */
        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        /* Effets de loading pour les graphiques */
        @keyframes chartLoad {
            0% {
                opacity: 0;
                transform: scale(0.8);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .chart-container canvas {
            animation: chartLoad 0.8s ease-out;
        }

        .product-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 0.75rem;
            border: 3px solid #e5e7eb;
        }

        .price-trend-up {
            color: #dc2626;
        }

        .price-trend-down {
            color: #16a34a;
        }

        .price-trend-stable {
            color: #6b7280;
        }

        .bg-orange-50 {
            background-color: #ffb42c;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .card {
                box-shadow: none;
                border: 1px solid #e5e7eb;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="wrapper">
        <?php include_once '../../../components/navbar_finance.php'; ?>

        <main class="flex-1 p-6">
            <?php if (isset($errorMessage)): ?>
                <!-- Message d'erreur -->
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <span class="material-icons text-red-500 mr-2">error</span>
                        <p class="text-red-800 font-medium"><?php echo htmlspecialchars($errorMessage); ?></p>
                    </div>
                    <div class="mt-4">
                        <a href="../stats_produits.php" class="text-red-600 hover:text-red-800 underline">
                            Retour à la liste des produits
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <!-- Header avec informations principales et image -->
                <div class="card p-6 mb-6">
                    <div class="flex flex-wrap justify-between items-start mb-6">
                        <div class="flex flex-wrap items-start mb-4 md:mb-0">
                            <a href="../stats_produits.php" class="text-blue-600 hover:text-blue-800 mr-4 no-print flex items-center">
                                <span class="material-icons">arrow_back</span>
                            </a>

                            <!-- Image du produit -->
                            <div class="mr-6">
                                <?php if (!empty($productDetails['product_image']) && file_exists('../../../' . $productDetails['product_image'])): ?>
                                    <img src="../../../<?php echo htmlspecialchars($productDetails['product_image']); ?>"
                                        alt="<?php echo htmlspecialchars($productDetails['product_name']); ?>"
                                        class="product-image">
                                <?php else: ?>
                                    <div class="product-image bg-gray-100 flex items-center justify-center">
                                        <span class="material-icons text-gray-400 text-4xl">inventory_2</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                                    <?php echo htmlspecialchars($productDetails['product_name']); ?>
                                </h1>
                                <div class="flex flex-wrap gap-2 items-center">
                                    <span class="stat-badge bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($productDetails['barcode']); ?>
                                    </span>
                                    <span class="stat-badge bg-gray-100 text-gray-800">
                                        <?php echo htmlspecialchars($productDetails['category_name'] ?? 'Non catégorisé'); ?>
                                    </span>
                                    <span class="stat-badge <?php echo getStatusClass($productDetails['stock_status']); ?>">
                                        <?php echo getStatusText($productDetails['stock_status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 no-print">
                            <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center">
                                <span class="material-icons mr-2">print</span>
                                Imprimer
                            </button>
                            <button onclick="exportToPDF()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center">
                                <span class="material-icons mr-2">picture_as_pdf</span>
                                PDF Simple
                            </button>
                            <button onclick="exportDetailedReport()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center">
                                <span class="material-icons mr-2">description</span>
                                Rapport Détaillé
                            </button>
                        </div>
                    </div>

                    <!-- Métriques principales -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <div class="metric-card card card-hover-effect bg-gradient-to-br from-blue-50 to-blue-100 border-l-4 border-blue-500">
                            <div class="metric-number text-blue-600">
                                <?php echo formatNumber($productDetails['quantity']); ?>
                            </div>
                            <div class="metric-label">Stock Total</div>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php echo htmlspecialchars($productDetails['unit']); ?>
                            </div>
                        </div>

                        <div class="metric-card card card-hover-effect bg-gradient-to-br from-green-50 to-green-100 border-l-4 border-green-500">
                            <div class="metric-number text-green-600">
                                <?php echo formatNumber($productDetails['available_quantity']); ?>
                            </div>
                            <div class="metric-label">Disponible</div>
                            <div class="text-xs text-gray-500 mt-1">
                                Réservé: <?php echo formatNumber($productDetails['quantity_reserved']); ?>
                            </div>
                        </div>

                        <div class="metric-card card card-hover-effect bg-gradient-to-br from-purple-50 to-purple-100 border-l-4 border-purple-500">
                            <div class="metric-number text-purple-600">
                                <?php echo formatMoney($productDetails['stock_value']); ?>
                            </div>
                            <div class="metric-label">Valeur Stock</div>
                            <div class="text-xs text-gray-500 mt-1">
                                P.U: <?php echo formatMoney($productDetails['unit_price']); ?>
                            </div>
                        </div>

                        <div class="metric-card card card-hover-effect bg-gradient-to-br from-orange-50 to-orange-100 border-l-4 border-orange-500">
                            <div class="metric-number text-orange-600">
                                <?php echo formatMoney($productDetails['prix_stats']['prix_moyen_achats'] ?? 0); ?>
                            </div>
                            <div class="metric-label">Prix Moyen</div>
                            <div class="text-xs text-gray-500 mt-1">
                                Sur <?php echo formatNumber($productDetails['prix_stats']['nb_achats_avec_prix'] ?? 0); ?> achats
                            </div>
                        </div>

                        <div class="metric-card card card-hover-effect bg-gradient-to-br from-red-50 to-red-100 border-l-4 border-red-500">
                            <div class="metric-number text-red-600">
                                <?php echo formatMoney($productDetails['prix_stats']['dernier_prix_achat'] ?? 0); ?>
                            </div>
                            <div class="metric-label">Dernier Prix</div>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php if (!empty($productDetails['prix_stats']['date_dernier_prix'])): ?>
                                    <?php echo date('d/m/Y', strtotime($productDetails['prix_stats']['date_dernier_prix'])); ?>
                                <?php else: ?>
                                    Non défini
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="metric-card card card-hover-effect bg-gradient-to-br from-indigo-50 to-indigo-100 border-l-4 border-indigo-500">
                            <div class="metric-number text-indigo-600">
                                <?php echo formatMoney($productDetails['valeur_sorties_12m'] ?? 0); ?>
                            </div>
                            <div class="metric-label">Valeur Sorties</div>
                            <div class="text-xs text-gray-500 mt-1">
                                12 derniers mois
                            </div>
                        </div>
                    </div>
                </div>

                <!-- **NOUVELLE SECTION : Analyse des Prix** -->
                <div class="card p-6 mb-6">
                    <h2 class="section-title">
                        <span class="material-icons">trending_up</span>
                        Analyse des Prix
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <!-- Prix moyen -->
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-blue-800 mb-3">Prix Moyen</h3>
                            <div class="space-y-2">
                                <div class="text-2xl font-bold text-blue-600">
                                    <?php echo formatMoney($productDetails['prix_stats']['prix_moyen_achats'] ?? 0); ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Basé sur <?php echo formatNumber($productDetails['prix_stats']['nb_achats_avec_prix'] ?? 0); ?> achats
                                </div>
                                <!-- Mini graphique de tendance -->
                                <div class="mt-3">
                                    <canvas id="prixMoyenTrendChart" width="100" height="30"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Dernier Prix -->
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-green-800 mb-3">Dernier Prix</h3>
                            <div class="space-y-2">
                                <div class="text-2xl font-bold text-green-600">
                                    <?php echo formatMoney($productDetails['prix_stats']['dernier_prix_achat'] ?? 0); ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <?php if (!empty($productDetails['prix_stats']['date_dernier_prix'])): ?>
                                        <?php echo date('d/m/Y', strtotime($productDetails['prix_stats']['date_dernier_prix'])); ?>
                                    <?php else: ?>
                                        Non défini
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Fourchette de Prix -->
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-purple-800 mb-3">Fourchette de Prix</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Min:</span>
                                    <span class="font-medium"><?php echo formatMoney($productDetails['prix_stats']['prix_min'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Max:</span>
                                    <span class="font-medium"><?php echo formatMoney($productDetails['prix_stats']['prix_max'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Écart:</span>
                                    <span class="font-medium">
                                        <?php
                                        $ecart = ($productDetails['prix_stats']['prix_max'] ?? 0) - ($productDetails['prix_stats']['prix_min'] ?? 0);
                                        echo formatMoney($ecart);
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Tendance des Prix -->
                        <div class="bg-orange-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-orange-800 mb-3">Tendance</h3>
                            <div class="space-y-2">
                                <?php
                                $evolution_prix = $productDetails['evolution_prix'] ?? [];
                                $tendance = 'stable';
                                $tendance_text = 'Stable';
                                $tendance_class = 'price-trend-stable';

                                if (count($evolution_prix) >= 2) {
                                    $premier_prix = $evolution_prix[0]['prix_moyen_mois'] ?? 0;
                                    $dernier_prix = end($evolution_prix)['prix_moyen_mois'] ?? 0;

                                    if ($dernier_prix > $premier_prix * 1.05) {
                                        $tendance = 'hausse';
                                        $tendance_text = 'En hausse';
                                        $tendance_class = 'price-trend-up';
                                    } elseif ($dernier_prix < $premier_prix * 0.95) {
                                        $tendance = 'baisse';
                                        $tendance_text = 'En baisse';
                                        $tendance_class = 'price-trend-down';
                                    }
                                }
                                ?>

                                <div class="flex items-center">
                                    <span class="material-icons <?php echo $tendance_class; ?> mr-2">
                                        <?php echo $tendance === 'hausse' ? 'trending_up' : ($tendance === 'baisse' ? 'trending_down' : 'trending_flat'); ?>
                                    </span>
                                    <span class="font-medium <?php echo $tendance_class; ?>">
                                        <?php echo $tendance_text; ?>
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Sur les 12 derniers mois
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Graphique d'évolution des prix -->
                    <?php if (!empty($productDetails['evolution_prix'])): ?>
                        <div class="chart-card mb-6">
                            <div class="flex flex-wrap justify-between items-center mb-4">
                                <div class="flex items-center">
                                    <span class="material-icons text-blue-500 mr-3 text-2xl">trending_up</span>
                                    <h4 class="font-bold text-lg text-gray-800">Évolution des Prix</h4>
                                </div>
                                <div class="chart-badge info">
                                    <span class="material-icons text-sm mr-1">schedule</span>
                                    12 derniers mois
                                </div>
                            </div>
                            <div class="chart-container">
                                <canvas id="prixEvolutionChart"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Statistiques de rotation détaillées -->
                <div class="card p-6 mb-6">
                    <h2 class="section-title">
                        <span class="material-icons">analytics</span>
                        Analyse de Rotation
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- 1 mois -->
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-blue-800 mb-3">1 Mois</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Entrées:</span>
                                    <span class="font-medium"><?php echo formatNumber($productDetails['rotation_stats']['entrees_1m'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Sorties:</span>
                                    <span class="font-medium"><?php echo formatNumber($productDetails['rotation_stats']['sorties_1m'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Mouvements:</span>
                                    <span class="font-medium"><?php echo formatNumber($productDetails['rotation_stats']['mouvements_1m'] ?? 0); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- 3 mois -->
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-green-800 mb-3">3 Mois</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Entrées:</span>
                                    <span class="font-medium"><?php echo formatNumber($productDetails['rotation_stats']['entrees_3m'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Sorties:</span>
                                    <span class="font-medium"><?php echo formatNumber($productDetails['rotation_stats']['sorties_3m'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Fréquence/mois:</span>
                                    <span class="font-medium"><?php echo formatDecimal($productDetails['rotation_stats']['freq_3m'] ?? 0, 1); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- 6 mois -->
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-purple-800 mb-3">6 Mois</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Entrées:</span>
                                    <span class="font-medium"><?php echo formatNumber($productDetails['rotation_stats']['entrees_6m'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Sorties:</span>
                                    <span class="font-medium"><?php echo formatNumber($productDetails['rotation_stats']['sorties_6m'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Fréquence/mois:</span>
                                    <span class="font-medium"><?php echo formatDecimal($productDetails['rotation_stats']['freq_6m'] ?? 0, 1); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- 12 mois -->
                        <div class="bg-orange-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-orange-800 mb-3">12 Mois</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Entrées:</span>
                                    <span class="font-medium"><?php echo formatNumber($productDetails['rotation_stats']['entrees_12m'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Sorties:</span>
                                    <span class="font-medium"><?php echo formatNumber($productDetails['rotation_stats']['sorties_12m'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Fréquence/mois:</span>
                                    <span class="font-medium"><?php echo formatDecimal($productDetails['rotation_stats']['freq_12m'] ?? 0, 1); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation par onglets -->
                <div class="card mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex flex-wrap space-x-8 px-6" aria-label="Tabs">
                            <button class="tab-button active py-4 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600" data-tab="movements">
                                <span class="material-icons mr-1">swap_vert</span>
                                Mouvements (<?php echo count($productDetails['movements']); ?>)
                            </button>
                            <button class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700" data-tab="achats">
                                <span class="material-icons mr-1">shopping_cart</span>
                                Achats (<?php echo count($productDetails['achats']); ?>)
                            </button>
                            <button class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700" data-tab="projets">
                                <span class="material-icons mr-1">work</span>
                                Projets (<?php echo count($productDetails['projets']); ?>)
                            </button>
                            <button class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700" data-tab="fournisseurs">
                                <span class="material-icons mr-1">business</span>
                                Fournisseurs (<?php echo count($productDetails['fournisseurs']); ?>)
                            </button>
                            <button class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700" data-tab="evolution">
                                <span class="material-icons mr-1">trending_up</span>
                                Évolution Stock
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <!-- Onglet Mouvements -->
                        <div id="tab-movements" class="tab-content active">
                            <h3 class="text-lg font-semibold mb-4">Historique des Mouvements de Stock</h3>
                            <?php if (!empty($productDetails['movements'])): ?>
                                <div class="overflow-x-auto">
                                    <table id="movementsTable" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Quantité</th>
                                                <th>Provenance/Destination</th>
                                                <th>Demandeur/Fournisseur</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($productDetails['movements'] as $movement): ?>
                                                <tr>
                                                    <td class="font-mono text-sm" data-order="<?php echo strtotime($movement['created_at']); ?>">
                                                        <?php echo htmlspecialchars($movement['date_formatted']); ?>
                                                    </td>
                                                    <td>
                                                        <span class="stat-badge <?php echo getMovementTypeClass($movement['movement_type']); ?>">
                                                            <?php echo htmlspecialchars($movement['type_formatted']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="font-semibold" data-order="<?php echo $movement['quantity']; ?>">
                                                        <?php echo formatNumber($movement['quantity']); ?> <?php echo htmlspecialchars($productDetails['unit']); ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($movement['movement_type'] === 'entry'): ?>
                                                            <span class="text-green-600">← <?php echo htmlspecialchars($movement['provenance'] ?? '-'); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-red-600">→ <?php echo htmlspecialchars($movement['destination'] ?? '-'); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($movement['movement_type'] === 'entry'): ?>
                                                            <?php echo htmlspecialchars($movement['fournisseur'] ?? '-'); ?>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($movement['demandeur'] ?? '-'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-sm text-gray-600" title="<?php echo htmlspecialchars($movement['notes'] ?? ''); ?>">
                                                        <?php echo htmlspecialchars($movement['notes'] ?? '-'); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <span class="material-icons text-4xl mb-2">inventory_2</span>
                                    <p>Aucun mouvement de stock enregistré</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Onglet Achats -->
                        <div id="tab-achats" class="tab-content">
                            <h3 class="text-lg font-semibold mb-4">Historique des Achats</h3>
                            <?php if (!empty($productDetails['achats'])): ?>
                                <div class="overflow-x-auto">
                                    <table id="achatsTable" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Projet</th>
                                                <th>Client</th>
                                                <th>Quantité</th>
                                                <th>Prix Unitaire</th>
                                                <th>Fournisseur</th>
                                                <th>Statut</th>
                                                <th>Responsable</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($productDetails['achats'] as $achat): ?>
                                                <tr>
                                                    <td class="font-mono text-sm" data-order="<?php echo strtotime($achat['created_at']); ?>">
                                                        <?php echo htmlspecialchars($achat['date_formatted']); ?>
                                                    </td>
                                                    <td class="font-medium">
                                                        <?php echo htmlspecialchars($achat['code_projet'] ?? '-'); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($achat['nom_client'] ?? '-'); ?>
                                                    </td>
                                                    <td class="font-semibold" data-order="<?php echo $achat['quantity']; ?>">
                                                        <?php echo formatNumber($achat['quantity']); ?> <?php echo htmlspecialchars($achat['unit'] ?? $productDetails['unit']); ?>
                                                    </td>
                                                    <td class="font-semibold text-green-600" data-order="<?php echo $achat['prix_unitaire'] ?? 0; ?>">
                                                        <?php echo formatMoney($achat['prix_unitaire'] ?? 0); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($achat['fournisseur'] ?? '-'); ?>
                                                    </td>
                                                    <td>
                                                        <span class="stat-badge <?php
                                                                                echo $achat['status'] === 'reçu' ? 'bg-green-100 text-green-800' : ($achat['status'] === 'commandé' ? 'bg-blue-100 text-blue-800' : ($achat['status'] === 'annulé' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'));
                                                                                ?>">
                                                            <?php echo htmlspecialchars($achat['status_formatted']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($achat['user_name'] ?? '-'); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <span class="material-icons text-4xl mb-2">shopping_cart</span>
                                    <p>Aucun achat enregistré pour ce produit</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Onglet Projets -->
                        <div id="tab-projets" class="tab-content">
                            <h3 class="text-lg font-semibold mb-4">Utilisation dans les Projets</h3>
                            <?php if (!empty($productDetails['projets'])): ?>
                                <div class="overflow-x-auto">
                                    <table id="projetsTable" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Code Projet</th>
                                                <th>Client</th>
                                                <th>Chef de Projet</th>
                                                <th>Quantité Demandée</th>
                                                <th>Prix Unitaire</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($productDetails['projets'] as $projet): ?>
                                                <tr>
                                                    <td class="font-mono text-sm" data-order="<?php echo strtotime($projet['created_at']); ?>">
                                                        <?php echo htmlspecialchars($projet['date_formatted']); ?>
                                                    </td>
                                                    <td class="font-medium">
                                                        <?php echo htmlspecialchars($projet['code_projet'] ?? '-'); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($projet['nom_client'] ?? '-'); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($projet['chefprojet'] ?? '-'); ?>
                                                    </td>
                                                    <td class="font-semibold" data-order="<?php echo $projet['quantity'] ?? 0; ?>">
                                                        <?php echo formatNumber($projet['quantity'] ?? 0); ?> <?php echo htmlspecialchars($projet['unit'] ?? $productDetails['unit']); ?>
                                                    </td>
                                                    <td data-order="<?php echo $projet['prix_unitaire'] ?? 0; ?>">
                                                        <?php echo formatMoney($projet['prix_unitaire'] ?? 0); ?>
                                                    </td>
                                                    <td>
                                                        <span class="stat-badge <?php
                                                                                echo $projet['valide_achat'] === 'reçu' ? 'bg-green-100 text-green-800' : ($projet['valide_achat'] === 'validé' ? 'bg-blue-100 text-blue-800' : ($projet['valide_achat'] === 'annulé' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'));
                                                                                ?>">
                                                            <?php echo htmlspecialchars($projet['status_formatted']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <span class="material-icons text-4xl mb-2">work</span>
                                    <p>Ce produit n'a pas encore été utilisé dans des projets</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- **ONGLET FOURNISSEURS AMÉLIORÉ AVEC PRIX MOYEN PAR FOURNISSEUR** -->
                        <div id="tab-fournisseurs" class="tab-content">
                            <h3 class="text-lg font-semibold mb-4">Analyse des Fournisseurs avec Prix Moyens</h3>
                            <?php if (!empty($productDetails['fournisseurs'])): ?>
                                <!-- Tableau DataTable des fournisseurs -->
                                <div class="overflow-x-auto mb-6">
                                    <table id="fournisseursTable" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Fournisseur</th>
                                                <th>Nb Commandes</th>
                                                <th>Quantité Totale</th>
                                                <th>Prix Moyen</th>
                                                <th>Prix Minimum</th>
                                                <th>Prix Maximum</th>
                                                <th>Dernier Prix</th>
                                                <th>Dernière Commande</th>
                                                <th>Commandes avec Prix</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($productDetails['fournisseurs'] as $fournisseur): ?>
                                                <tr>
                                                    <td class="font-semibold text-blue-800">
                                                        <?php echo htmlspecialchars($fournisseur['fournisseur']); ?>
                                                    </td>
                                                    <td data-order="<?php echo $fournisseur['nb_commandes']; ?>">
                                                        <span class="font-medium"><?php echo formatNumber($fournisseur['nb_commandes']); ?></span>
                                                    </td>
                                                    <td data-order="<?php echo $fournisseur['quantite_totale']; ?>">
                                                        <span class="font-medium"><?php echo formatNumber($fournisseur['quantite_totale']); ?> <?php echo htmlspecialchars($productDetails['unit']); ?></span>
                                                    </td>
                                                    <td class="bg-green-50" data-order="<?php echo $fournisseur['prix_moyen'] ?? 0; ?>">
                                                        <span class="font-bold text-green-800"><?php echo formatMoney($fournisseur['prix_moyen'] ?? 0); ?></span>
                                                    </td>
                                                    <td data-order="<?php echo $fournisseur['prix_min'] ?? 0; ?>">
                                                        <span class="font-medium text-green-600"><?php echo formatMoney($fournisseur['prix_min'] ?? 0); ?></span>
                                                    </td>
                                                    <td data-order="<?php echo $fournisseur['prix_max'] ?? 0; ?>">
                                                        <span class="font-medium text-red-600"><?php echo formatMoney($fournisseur['prix_max'] ?? 0); ?></span>
                                                    </td>
                                                    <td class="bg-blue-50" data-order="<?php echo $fournisseur['dernier_prix'] ?? 0; ?>">
                                                        <span class="font-bold text-blue-800"><?php echo formatMoney($fournisseur['dernier_prix'] ?? 0); ?></span>
                                                    </td>
                                                    <td data-order="<?php echo strtotime($fournisseur['derniere_commande'] ?? ''); ?>">
                                                        <span class="font-medium"><?php echo htmlspecialchars($fournisseur['derniere_commande_format']); ?></span>
                                                    </td>
                                                    <td data-order="<?php echo $fournisseur['nb_commandes_avec_prix']; ?>">
                                                        <span class="font-medium">
                                                            <span class="text-green-600"><?php echo formatNumber($fournisseur['nb_commandes_avec_prix']); ?></span>
                                                            <span class="text-gray-500">/<?php echo formatNumber($fournisseur['nb_commandes']); ?></span>
                                                        </span>
                                                        <div class="text-xs text-gray-500">
                                                            <?php
                                                            $pourcentage = $fournisseur['nb_commandes'] > 0 ?
                                                                round(($fournisseur['nb_commandes_avec_prix'] / $fournisseur['nb_commandes']) * 100) : 0;
                                                            echo $pourcentage . '%';
                                                            ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Comparatif des prix par fournisseur -->
                                <div class="chart-card mb-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <div class="flex items-center">
                                            <span class="material-icons text-green-500 mr-3 text-2xl">bar_chart</span>
                                            <h4 class="font-bold text-lg text-gray-800">Prix par Fournisseur</h4>
                                        </div>
                                        <div class="chart-badge success">
                                            <span class="material-icons text-sm mr-1">business</span>
                                            Comparatif
                                        </div>
                                    </div>
                                    <div class="chart-container">
                                        <canvas id="fournisseursPrixChart"></canvas>
                                    </div>
                                </div>

                                <!-- Répartition des achats par fournisseur -->
                                <?php if (!empty($productDetails['fournisseurs'])): ?>
                                    <div class="chart-card">
                                        <div class="flex justify-between items-center mb-4">
                                            <div class="flex items-center">
                                                <span class="material-icons text-purple-500 mr-3 text-2xl">pie_chart</span>
                                                <h4 class="font-bold text-lg text-gray-800">Répartition des Achats</h4>
                                            </div>
                                            <div class="chart-badge info">
                                                <span class="material-icons text-sm mr-1">donut_small</span>
                                                Par quantité
                                            </div>
                                        </div>
                                        <div class="chart-container">
                                            <canvas id="fournisseursRepartitionChart"></canvas>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <span class="material-icons text-4xl mb-2">business</span>
                                    <p>Aucun fournisseur identifié pour ce produit</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Onglet Évolution Stock -->
                        <div id="tab-evolution" class="tab-content">
                            <h3 class="text-lg font-semibold mb-4">Évolution Mensuelle du Stock</h3>
                            <?php if (!empty($productDetails['evolution_mensuelle'])): ?>
                                <div class="chart-card mb-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <div class="flex items-center">
                                            <span class="material-icons text-purple-500 mr-3 text-2xl">show_chart</span>
                                            <h4 class="font-bold text-lg text-gray-800">Évolution du Stock</h4>
                                        </div>
                                        <div class="chart-badge warning">
                                            <span class="material-icons text-sm mr-1">calendar_month</span>
                                            Mensuel
                                        </div>
                                    </div>
                                    <div class="chart-container">
                                        <canvas id="evolutionChart"></canvas>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table id="evolutionTable" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Mois</th>
                                                <th>Entrées</th>
                                                <th>Sorties</th>
                                                <th>Variation Nette</th>
                                                <th>Nb Mouvements</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_reverse($productDetails['evolution_mensuelle']) as $evolution): ?>
                                                <tr>
                                                    <td class="font-medium" data-order="<?php echo $evolution['mois']; ?>">
                                                        <?php echo htmlspecialchars($evolution['mois_format'] ?? $evolution['mois']); ?>
                                                    </td>
                                                    <td class="text-green-600 font-semibold" data-order="<?php echo $evolution['entrees']; ?>">
                                                        +<?php echo formatNumber($evolution['entrees']); ?>
                                                    </td>
                                                    <td class="text-red-600 font-semibold" data-order="<?php echo $evolution['sorties']; ?>">
                                                        -<?php echo formatNumber($evolution['sorties']); ?>
                                                    </td>
                                                    <td class="font-semibold <?php echo $evolution['variation_nette'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>" data-order="<?php echo $evolution['variation_nette']; ?>">
                                                        <?php echo ($evolution['variation_nette'] >= 0 ? '+' : '') . formatNumber($evolution['variation_nette']); ?>
                                                    </td>
                                                    <td data-order="<?php echo $evolution['nb_mouvements']; ?>">
                                                        <?php echo formatNumber($evolution['nb_mouvements']); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <span class="material-icons text-4xl mb-2">trending_up</span>
                                    <p>Pas suffisamment de données pour afficher l'évolution</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </main>

        <?php include_once '../../../components/footer.html'; ?>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <!-- DataTables Scripts -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <!-- SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="../assets/js/chart_functions.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Données pour les graphiques
            const chartData = {
                evolutionPrix: <?php echo json_encode($productDetails['evolution_prix'] ?? []); ?>,
                evolutionStock: <?php echo json_encode($productDetails['evolution_mensuelle'] ?? []); ?>,
                fournisseurs: <?php echo json_encode($productDetails['fournisseurs'] ?? []); ?>
            };

            const productName = "<?php echo addslashes($productDetails['product_name'] ?? ''); ?>";
            const productUnit = "<?php echo addslashes($productDetails['unit'] ?? 'unités'); ?>";

            // Initialiser la page avec les nouvelles fonctions optimisées
            initProductDetailsPage(chartData, productName, productUnit);
        });

        // Fonctions d'export PDF (conservées)
        function exportToPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();

            // Configuration du document
            doc.setFontSize(20);
            doc.text('Détails du Produit', 20, 30);

            // Informations de base
            doc.setFontSize(14);
            doc.text('<?php echo addslashes($productDetails['product_name'] ?? ''); ?>', 20, 50);

            doc.setFontSize(10);
            doc.text('Code-barres: <?php echo addslashes($productDetails['barcode'] ?? ''); ?>', 20, 65);
            doc.text('Catégorie: <?php echo addslashes($productDetails['category_name'] ?? ''); ?>', 20, 75);
            doc.text('Stock: <?php echo formatNumber($productDetails['quantity'] ?? 0); ?> <?php echo addslashes($productDetails['unit'] ?? ''); ?>', 20, 85);
            doc.text('Prix Moyen: <?php echo formatMoney($productDetails['prix_stats']['prix_moyen_achats'] ?? 0); ?>', 20, 95);
            doc.text('Dernier Prix: <?php echo formatMoney($productDetails['prix_stats']['dernier_prix_achat'] ?? 0); ?>', 20, 105);

            // Sauvegarder
            doc.save('details-produit-<?php echo preg_replace('/[^a-zA-Z0-9]/', '-', $productDetails['product_name'] ?? 'produit'); ?>.pdf');
        }

        function exportDetailedReport() {
            const productId = <?php echo $productId; ?>;
            const productName = "<?php echo addslashes($productDetails['product_name'] ?? ''); ?>";

            if (!productId || productId === 'null' || productId === '') {
                Swal.fire({
                    title: 'Erreur',
                    text: 'ID du produit non trouvé. Impossible de générer le rapport.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Configuration du dialogue d'export
            Swal.fire({
                title: 'Rapport Détaillé du Produit',
                html: `
            <div class="text-left">
                <p class="mb-4"><strong>Produit :</strong> ${productName}</p>
                <p class="mb-4">Sélectionnez les sections à inclure dans le rapport :</p>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" id="include-stats" checked class="mr-2">
                        Statistiques générales et informations de base
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" id="include-movements" checked class="mr-2">
                        Historique des mouvements de stock (50 derniers)
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" id="include-purchases" checked class="mr-2">
                        Historique des achats (30 derniers)
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" id="include-projects" checked class="mr-2">
                        Utilisation dans les projets (25 derniers)
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" id="include-suppliers" checked class="mr-2">
                        Analyse des fournisseurs (10 principaux)
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" id="include-evolution" checked class="mr-2">
                        Évolution du stock (12 derniers mois)
                    </label>
                </div>
            </div>
        `,
                showCancelButton: true,
                confirmButtonText: 'Générer le Rapport',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#6366f1',
                width: '550px',
                customClass: {
                    popup: 'text-sm'
                },
                preConfirm: () => {
                    const options = {
                        include_stats: document.getElementById('include-stats').checked,
                        include_movements: document.getElementById('include-movements').checked,
                        include_purchases: document.getElementById('include-purchases').checked,
                        include_projects: document.getElementById('include-projects').checked,
                        include_suppliers: document.getElementById('include-suppliers').checked,
                        include_evolution: document.getElementById('include-evolution').checked
                    };

                    const hasSelection = Object.values(options).some(option => option);
                    if (!hasSelection) {
                        Swal.showValidationMessage('Veuillez sélectionner au moins une section');
                        return false;
                    }

                    return options;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Génération du rapport PDF',
                        html: `
                    <div class="text-center">
                        <div class="mb-3">Génération en cours du rapport détaillé...</div>
                        <div class="text-sm text-gray-600">Produit: ${productName}</div>
                        <div class="text-sm text-gray-600">Sections sélectionnées: ${Object.values(result.value).filter(v => v).length}/6</div>
                    </div>
                `,
                        icon: 'info',
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    let reportUrl = '../generate_report.php?type=product_details&product_id=' + productId;
                    const options = result.value;
                    Object.keys(options).forEach(key => {
                        if (options[key]) {
                            reportUrl += '&' + key + '=1';
                        }
                    });

                    const newWindow = window.open(reportUrl, '_blank');
                    if (!newWindow) {
                        Swal.fire({
                            title: 'Popup bloqué',
                            text: 'Veuillez autoriser les popups pour ce site et réessayer.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }

                    setTimeout(() => {
                        Swal.fire({
                            title: 'Rapport généré',
                            text: 'Le rapport détaillé a été généré avec succès.',
                            icon: 'success',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    }, 2000);
                }
            });
        }
    </script>
</body>

</html>