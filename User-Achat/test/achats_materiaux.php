<?php

/**
 * Module de gestion des achats de matériaux
 * 
 * Ce fichier permet de gérer les achats de matériaux pour les projets,
 * incluant le suivi des commandes, les achats groupés, et les statistiques.
 * 
 * @package DYM_MANUFACTURE
 * @subpackage expressions_besoins
 */

// ===============================
// INITIALISATION ET CONFIGURATION
// ===============================
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Connexion à la base de données et helpers
include_once '../database/connection.php';
include_once '../include/date_helper.php';
include_once 'get_suggested_fournisseur.php';

// ===============================
// INITIALISATION DES VARIABLES
// ===============================
$message = '';
$projects = [];
$materialsCount = 0;
$allMaterials = [];
$recents = [];
$retourStats = [
    'total_retours' => 0,
    'valeur_retours' => 0,
    'retours_attente' => 0,
    'retours_completes' => 0
];

// ===============================
// TRAITEMENT DE LA LOGIQUE
// ===============================
// Initialisation des compteurs pour les notifications
try {
    // Récupérer les compteurs pour l'affichage initial
    $notificationsQuery = "SELECT 
        (
            SELECT COUNT(*) 
            FROM expression_dym 
            WHERE valide_achat = 'en_cours'
            AND qt_restante > 0
            AND " . getFilteredDateCondition() . "
        ) +
        (
            SELECT COUNT(*) 
            FROM besoins 
            WHERE achat_status = 'en_cours'
            AND qt_restante > 0
            AND " . getFilteredDateCondition() . "
        ) as partial_count,
        
        (
            SELECT COUNT(*) 
            FROM expression_dym 
            WHERE (valide_achat = 'validé' OR valide_achat = 'valide_en_cours')
            AND " . getFilteredDateCondition() . "
        ) +
        (
            SELECT COUNT(*) 
            FROM besoins 
            WHERE (achat_status = 'validé' OR achat_status = 'valide_en_cours')
            AND " . getFilteredDateCondition() . "
        ) as ordered_count";

    $notificationsStmt = $pdo->prepare($notificationsQuery);
    $notificationsStmt->execute();
    $notificationsData = $notificationsStmt->fetch(PDO::FETCH_ASSOC);

    // Initialiser les notifications avec les compteurs récupérés
    $notifications = [
        'counts' => [
            'partial' => $notificationsData['partial_count'] ?? 0,
            'ordered' => $notificationsData['ordered_count'] ?? 0,
            'remaining' => $notificationsData['partial_count'] ?? 0  // Pour compatibilité
        ]
    ];
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des compteurs de notification: " . $e->getMessage());
    // Valeurs par défaut en cas d'erreur
    $notifications = [
        'counts' => [
            'partial' => 0,
            'ordered' => 0,
            'remaining' => 0
        ]
    ];
}

try {
    // Récupérer les projets avec des matériaux à acheter
    $projectsQuery = "SELECT DISTINCT ip.id, ip.idExpression, ip.code_projet, ip.nom_client, ip.created_at
    FROM identification_projet ip
    INNER JOIN expression_dym ed ON ip.idExpression = ed.idExpression
    WHERE ed.qt_acheter IS NOT NULL 
    AND ed.qt_acheter > 0
    AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
    AND " . getFilteredDateCondition('ed.created_at') . "
    
    UNION
    
    SELECT 0 as id, b.idBesoin as idExpression, 'SYS' as code_projet, 
           CONCAT('Demande ', d.client) as nom_client, b.created_at
    FROM besoins b
    LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
    WHERE b.qt_acheter IS NOT NULL 
    AND b.qt_acheter > 0
    AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL)
    AND " . getFilteredDateCondition('b.created_at') . "
    
    ORDER BY created_at DESC";

    $projectsStmt = $pdo->prepare($projectsQuery);
    $projectsStmt->execute();
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);



    // Regrouper les projets par code_projet et nom_client
    $groupedProjects = [];
    foreach ($projects as $project) {
        $key = $project['code_projet'] . '_' . $project['nom_client'];

        if (!isset($groupedProjects[$key])) {
            $groupedProjects[$key] = [
                'code_projet' => $project['code_projet'],
                'nom_client' => $project['nom_client'],
                'expression_ids' => [$project['idExpression']],
                'materials_count' => 0
            ];
        } else {
            // Ajouter l'ID d'expression au groupe existant s'il n'existe pas déjà
            if (!in_array($project['idExpression'], $groupedProjects[$key]['expression_ids'])) {
                $groupedProjects[$key]['expression_ids'][] = $project['idExpression'];
            }
        }
    }

    // Compter le nombre de matériaux par projet
    foreach ($groupedProjects as $key => &$group) {
        $placeholders = implode(',', array_fill(0, count($group['expression_ids']), '?'));
        $countQuery = "SELECT COUNT(*) as count
                   FROM expression_dym
                   WHERE idExpression IN ($placeholders)
                   AND qt_acheter > 0
                   AND (valide_achat = 'pas validé' OR valide_achat IS NULL)";

        $countStmt = $pdo->prepare($countQuery);

        foreach ($group['expression_ids'] as $index => $id) {
            $countStmt->bindValue($index + 1, $id);
        }

        $countStmt->execute();
        $group['materials_count'] = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    unset($group); // Supprimer la référence



    // ===============================
    // CALCUL DES COMPTEURS SPÉCIFIQUES
    // ===============================

    // Compteur pour matériaux vraiment en attente (excluant les partiels)
    $pendingMaterialsQuery = "SELECT (
    (SELECT COUNT(*)
     FROM expression_dym ed
     WHERE ed.qt_acheter IS NOT NULL
     AND ed.qt_acheter > 0
     AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL OR ed.valide_achat = '')
     AND ed.valide_achat != 'en_cours'
     AND " . getFilteredDateCondition('ed.created_at') . ")
    +
    (SELECT COUNT(*)
     FROM besoins b
     WHERE b.qt_acheter IS NOT NULL
     AND b.qt_acheter > 0
     AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL OR b.achat_status = '')
     AND b.achat_status != 'en_cours'
     AND " . getFilteredDateCondition('b.created_at') . ")
) as pending_total";

    $pendingStmt = $pdo->prepare($pendingMaterialsQuery);
    $pendingStmt->execute();
    $pendingMaterialsCount = $pendingStmt->fetch(PDO::FETCH_ASSOC)['pending_total'];

    // Compteur total pour l'onglet principal (tous les matériaux à acheter)
    $countMateriauxQuery = "SELECT (
    (SELECT COUNT(*) 
     FROM expression_dym 
     WHERE qt_acheter IS NOT NULL 
     AND qt_acheter > 0 
     AND (valide_achat = 'pas validé' OR valide_achat IS NULL OR valide_achat = 'en_cours')
     AND " . getFilteredDateCondition() . ")
    +
    (SELECT COUNT(*) 
     FROM besoins 
     WHERE qt_acheter IS NOT NULL 
     AND qt_acheter > 0 
     AND (achat_status = 'pas validé' OR achat_status IS NULL OR achat_status = 'en_cours')
     AND " . getFilteredDateCondition() . ")
    ) as total";

    $countStmt = $pdo->prepare($countMateriauxQuery);
    $countStmt->execute();
    $materialsCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ===============================
    // TRAITEMENT DU FORMULAIRE D'ACHAT
    // ===============================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_purchase'])) {
        $pdo->beginTransaction();

        $expressionId = $_POST['expression_id'];
        $designation = $_POST['designation'];
        $quantite = $_POST['quantite'];
        $unite = $_POST['unite'];
        $prix = $_POST['prix'];
        $fournisseur = $_POST['fournisseur'];

        // Insérer dans la table achats_materiaux
        $insertAchatQuery = "INSERT INTO achats_materiaux 
        (expression_id, designation, quantity, unit, prix_unitaire, fournisseur, status, user_achat, original_quantity, is_partial) 
        VALUES (:expression_id, :designation, :quantity, :unit, :prix, :fournisseur, 'commandé', :user_achat, :original_qty, :is_partial)";

        $insertStmt = $pdo->prepare($insertAchatQuery);
        $insertStmt->bindParam(':expression_id', $expressionId);
        $insertStmt->bindParam(':designation', $designation);
        $insertStmt->bindParam(':quantity', $quantite);
        $insertStmt->bindParam(':unit', $unite);
        $insertStmt->bindParam(':prix', $prix);
        $insertStmt->bindParam(':fournisseur', $fournisseur);
        $insertStmt->bindParam(':user_achat', $user_id);
        $insertStmt->execute();

        // Mettre à jour la table expression_dym
        $updateExpressionQuery = "UPDATE expression_dym 
                                SET valide_achat = 'valide_en_cours', 
                                prix_unitaire = :prix, 
                                fournisseur = :fournisseur,
                                user_achat = :user_achat 
                                WHERE idExpression = :expression_id 
                                AND designation = :designation";

        $updateStmt = $pdo->prepare($updateExpressionQuery);
        $updateStmt->bindParam(':prix', $prix);
        $updateStmt->bindParam(':fournisseur', $fournisseur);
        $updateStmt->bindParam(':user_achat', $user_id);
        $updateStmt->bindParam(':expression_id', $expressionId);
        $updateStmt->bindParam(':designation', $designation);
        $updateStmt->execute();

        $pdo->commit();
        $message = "Commande enregistrée avec succès!";

        // Rafraîchir la page pour montrer les changements
        header("Location: achats_materiaux.php?success=1");
        exit();
    }

    // Vérifier si un message de succès existe dans l'URL
    if (isset($_GET['success']) && $_GET['success'] == 1) {
        $message = "Commande enregistrée avec succès!";
    }

    // ===============================
    // RÉCUPÉRATION DES DONNÉES POUR LES TABLEAUX
    // ===============================

    // Récupérer tous les matériaux à acheter pour le DataTable
    $allMaterialsQuery = "SELECT * FROM (
        -- Matériaux depuis expression_dym
        SELECT 
            ed.id, 
            ed.idExpression, 
            ed.designation, 
            ed.qt_acheter, 
            ed.unit, 
            ed.valide_achat, 
            ed.prix_unitaire, 
            ed.fournisseur, 
            ed.qt_restante, 
            ed.initial_qt_acheter,
            ip.code_projet, 
            ip.nom_client, 
            ip.created_at,
            'expression_dym' AS source_table,
            (SELECT am.fournisseur 
             FROM achats_materiaux am 
             WHERE am.designation = ed.designation
             ORDER BY am.date_achat DESC LIMIT 1) AS dernier_fournisseur,
            (SELECT fc.categorie 
             FROM fournisseur_categories fc 
             INNER JOIN fournisseurs f ON fc.fournisseur_id = f.id
             WHERE f.nom = (SELECT am2.fournisseur 
                           FROM achats_materiaux am2 
                           WHERE am2.designation = ed.designation
                           ORDER BY am2.date_achat DESC LIMIT 1)
             LIMIT 1) AS categorie_fournisseur
        FROM expression_dym ed
        INNER JOIN identification_projet ip ON ed.idExpression = ip.idExpression
        WHERE ed.qt_acheter IS NOT NULL 
        AND ed.qt_acheter > 0
        AND ed.valide_achat != 'annulé'
        AND " . getFilteredDateCondition('ed.created_at') . "
        
        UNION ALL
        
        -- Matériaux depuis besoins
        SELECT 
            b.id, 
            b.idBesoin as idExpression, 
            b.designation_article as designation, 
            b.qt_acheter, 
            b.caracteristique as unit, 
            b.achat_status as valide_achat, 
            NULL as prix_unitaire, 
            NULL as fournisseur, 
            NULL as qt_restante,
            NULL as initial_qt_acheter,
            CONCAT('SYSTÈME-', d.client) as code_projet, 
            d.client as nom_client, 
            b.created_at,
            'besoins' as source_table,
            (SELECT am.fournisseur 
             FROM achats_materiaux am 
             WHERE am.designation = b.designation_article
             ORDER BY am.date_achat DESC LIMIT 1) AS dernier_fournisseur,
            (SELECT fc.categorie 
             FROM fournisseur_categories fc 
             INNER JOIN fournisseurs f ON fc.fournisseur_id = f.id
             WHERE f.nom = (SELECT am2.fournisseur 
                           FROM achats_materiaux am2 
                           WHERE am2.designation = b.designation_article
                           ORDER BY am2.date_achat DESC LIMIT 1)
             LIMIT 1) AS categorie_fournisseur
        FROM besoins b
        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
        WHERE b.qt_acheter IS NOT NULL 
        AND b.qt_acheter != '' 
        AND b.qt_acheter > 0
        AND (b.achat_status IS NULL OR b.achat_status = '' OR b.achat_status = 'pas validé')
        AND " . getFilteredDateCondition('b.created_at') . "
    ) AS combined_materials
    ORDER BY valide_achat ASC, created_at DESC, designation ASC";

    $allMaterialsStmt = $pdo->prepare($allMaterialsQuery);
    $allMaterialsStmt->execute();
    $allMaterials = $allMaterialsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les matériaux reçus avec informations d'achat
    $recentsQuery = "
    -- Requête pour les matériaux de expression_dym
    SELECT 
        ed.id as ed_id,
        ed.idExpression,
        ed.designation,
        ed.quantity,
        ed.quantity_stock,
        ed.qt_acheter,
        ed.unit,
        ed.prix_unitaire,
        ed.fournisseur,
        ed.valide_achat,
        ed.created_at,
        ed.updated_at,
        ip.code_projet,
        ip.nom_client,
        u.name as acheteur_name,
        (
            SELECT MAX(id) 
            FROM achats_materiaux 
            WHERE expression_id = ed.idExpression 
            AND designation = ed.designation
        ) as last_order_id,
        (
            SELECT MAX(date_reception) 
            FROM achats_materiaux 
            WHERE expression_id = ed.idExpression 
            AND designation = ed.designation
            AND status = 'reçu'
        ) as date_reception,
        (
            SELECT COUNT(*) 
            FROM achats_materiaux 
            WHERE expression_id = ed.idExpression 
            AND designation = ed.designation
        ) as orders_count,
        'expression_dym' as source_table
    FROM expression_dym ed
    LEFT JOIN identification_projet ip ON ed.idExpression = ip.idExpression
    LEFT JOIN users_exp u ON ed.user_achat = u.id
    WHERE ed.valide_achat = 'reçu'";

    // Ajouter la condition de date si la fonction existe
    if (function_exists('getFilteredDateCondition')) {
        $recentsQuery .= " AND " . getFilteredDateCondition('ed.updated_at');
    }

    $recentsQuery .= "
    
    UNION ALL
    
    -- Requête pour les matériaux de besoins
    SELECT 
        b.id as ed_id,
        b.idBesoin as idExpression,
        b.designation_article as designation,
        b.qt_demande as quantity,
        b.qt_stock as quantity_stock,
        b.qt_acheter,
        b.caracteristique as unit,
        (
            SELECT MAX(am.prix_unitaire) 
            FROM achats_materiaux am 
            WHERE am.expression_id = b.idBesoin 
            AND am.designation = b.designation_article
        ) as prix_unitaire,
        (
            SELECT MAX(am.fournisseur) 
            FROM achats_materiaux am 
            WHERE am.expression_id = b.idBesoin 
            AND am.designation = b.designation_article
        ) as fournisseur,
        b.achat_status as valide_achat,
        b.created_at,
        b.updated_at,
        'SYS' as code_projet,
        CONCAT('Demande ', COALESCE(d.client, 'Système')) as nom_client,
        (
            SELECT u2.name 
            FROM users_exp u2 
            WHERE u2.id = b.user_achat
        ) as acheteur_name,
        (
            SELECT MAX(id) 
            FROM achats_materiaux 
            WHERE expression_id = b.idBesoin 
            AND designation = b.designation_article
        ) as last_order_id,
        (
            SELECT MAX(date_reception) 
            FROM achats_materiaux 
            WHERE expression_id = b.idBesoin 
            AND designation = b.designation_article
            AND status = 'reçu'
        ) as date_reception,
        (
            SELECT COUNT(*) 
            FROM achats_materiaux 
            WHERE expression_id = b.idBesoin 
            AND designation = b.designation_article
        ) as orders_count,
        'besoins' as source_table
    FROM besoins b
    LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
    WHERE b.achat_status = 'reçu'";

    // Ajouter la condition de date si la fonction existe
    if (function_exists('getFilteredDateCondition')) {
        $recentsQuery .= " AND " . getFilteredDateCondition('b.updated_at');
    }

    $recentsQuery .= " ORDER BY updated_at DESC";
    //LIMIT 10";

    $recentsStmt = $pdo->prepare($recentsQuery);
    $recentsStmt->execute();
    $recents = $recentsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = "Une erreur s'est produite : " . $e->getMessage();
}

// Récupérer les statistiques sur les retours fournisseurs
try {
    $retourQuery = "SELECT 
        COUNT(DISTINCT sr.id) as total_retours,
        COALESCE(SUM(DISTINCT sr.quantity * COALESCE((
            SELECT MAX(ed.prix_unitaire) 
            FROM expression_dym ed 
            WHERE ed.designation = p.product_name 
            LIMIT 1
        ), 0)), 0) as valeur_retours,
        COUNT(DISTINCT CASE WHEN sr.status = 'pending' THEN sr.id END) as retours_attente,
        COUNT(DISTINCT CASE WHEN sr.status = 'completed' THEN sr.id END) as retours_completes
    FROM supplier_returns sr
    JOIN products p ON sr.product_id = p.id
    WHERE " . getFilteredDateCondition('sr.created_at');

    $retourStmt = $pdo->prepare($retourQuery);
    $retourStmt->execute();
    $retourStats = $retourStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $retourStats = [
        'total_retours' => 0,
        'valeur_retours' => 0,
        'retours_attente' => 0,
        'retours_completes' => 0
    ];
}

// ===============================
// FONCTIONS UTILITAIRES
// ===============================

/**
 * Formate les nombres avec séparateurs de milliers
 * 
 * @param float $number Le nombre à formater
 * @return string Le nombre formaté
 */
function formatNumber($number)
{
    return number_format((float) $number, 0, ',', ' ');
}

// ===============================
// DÉBUT DU CODE HTML
// ===============================
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achats Matériaux</title>

    <!-- Styles CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

    <!-- Scripts JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* =============================== */
        /* STRUCTURE GLOBALE ET DISPOSITION */
        /* =============================== */
        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* =============================== */
        /* COMPOSANTS COMMUNS */
        /* =============================== */
        /* Boutons */
        .validate-btn {
            border: 2px solid #38a169;
            color: #38a169;
            padding: 8px 18px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            background-color: transparent;
            transition: color 0.3s, border-color 0.3s;
        }

        .validate-btn:hover {
            color: #2f855a;
            border-color: #2f855a;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.375rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }

        .btn-action:hover {
            transform: translateY(-1px);
            background-color: rgba(0, 0, 0, 0.05);
        }

        .btn-action .material-icons {
            font-size: 1.25rem;
        }

        /* Cartes */
        .project-card {
            border-left: 4px solid #3182ce;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .stats-card {
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .status-pending {
            background-color: #fff0e1;
            color: #ff8c00;
        }

        .status-ordered {
            background-color: #e6f6f0;
            color: #38a169;
        }

        .status-completed {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-received {
            background-color: #e3effd;
            color: #3182ce;
        }

        .status-partial {
            background-color: #fde68a;
            color: #92400e;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: 3px 6px;
            border-radius: 50%;
            background-color: #e53e3e;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        /* Styles pour les onglets de notifications */
        .tab-button {
            color: #6B7280;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .tab-button:hover:not(.tab-active) {
            color: #4B5563;
            border-bottom-color: #E5E7EB;
        }

        .tab-active {
            color: #3B82F6;
            border-bottom: 2px solid #3B82F6;
        }

        .tab-content {
            transition: all 0.3s ease;
        }

        /* =============================== */
        /* COMPOSANTS SPÉCIFIQUES */
        /* =============================== */
        /* Date et heure */
        .date-time {
            display: flex;
            align-items: center;
            font-size: 16px;
            color: #4a5568;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 8px 18px;
        }

        .date-time .material-icons {
            margin-right: 12px;
            font-size: 22px;
            color: #2d3748;
        }

        /* Onglets */
        .tab-btn {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            color: #3182ce;
        }

        .tab-content {
            transition: all 0.3s ease;
        }

        .materials-tab {
            position: relative;
            transition: all 0.2s ease;
        }

        .materials-tab.active {
            color: #3182ce;
            border-bottom-color: #3182ce;
        }

        .materials-tab:hover:not(.active) {
            color: #4a5568;
            border-bottom-color: #a0aec0;
        }

        /* Lignes de matériaux */
        .material-row {
            transition: background-color 0.2s;
        }

        .material-row:hover {
            background-color: #f7fafc;
        }

        .material-row.completed {
            background-color: #f0fff4;
        }

        .material-row.pending {
            background-color: #fffaf0;
        }

        .material-row.received {
            background-color: #ebf5ff;
        }

        .material-row.partial-order {
            position: relative;
            border-left: 4px solid #f59e0b;
        }

        .material-row.partial-order:hover {
            background-color: #fef3c7;
        }

        .project-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .project-row:hover {
            background-color: #f7fafc;
        }

        /* Checkboxes */
        .material-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .partial-material-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .select-all-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* Barres de progression */
        .progress-bar-container {
            width: 100%;
            height: 6px;
            background-color: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 4px;
        }

        .progress-bar {
            height: 100%;
            border-radius: 3px;
        }

        .progress-bar-yellow {
            background-color: #f59e0b;
        }

        .progress-bar-green {
            background-color: #10b981;
        }

        .progress-bar-red {
            background-color: #ef4444;
        }

        .progress-bar-partial {
            background-color: #f59e0b;
        }

        .progress-bar-complete {
            background-color: #10b981;
        }

        /* =============================== */
        /* MODALS ET FENÊTRES MODALES */
        /* =============================== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .close-modal-btn {
            cursor: pointer;
            transition: all 0.2s;
        }

        .close-modal-btn:hover {
            transform: rotate(90deg);
        }

        /* =============================== */
        /* TABLES ET TABLEAUX */
        /* =============================== */
        /* Style de base pour DataTables */
        table.dataTable {
            width: 100% !important;
            margin-bottom: 1rem;
            clear: both;
            border-collapse: separate;
            border-spacing: 0;
        }

        table.dataTable thead th,
        table.dataTable thead td {
            padding: 12px 18px;
            border-bottom: 2px solid #e2e8f0;
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }

        table.dataTable tbody td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }

        table.dataTable tbody tr.odd {
            background-color: #f9fafb;
        }

        table.dataTable tbody tr.even {
            background-color: #ffffff;
        }

        table.dataTable tbody tr:hover {
            background-color: #f1f5f9;
        }

        /* Contrôles DataTables */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate {
            color: #4a5568;
            margin-bottom: 1rem;
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.375rem 0.75rem;
            margin-left: 0.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            background-color: #ffffff;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4299e1;
            color: white !important;
            border: 1px solid #4299e1;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #2b6cb0;
            color: white !important;
            border: 1px solid #2b6cb0;
        }

        /* Tables spécifiques */
        .materials-table-wrapper {
            max-height: 300px;
            overflow-y: auto;
        }

        .materials-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .materials-table thead th {
            position: sticky;
            top: 0;
            background-color: #f7fafc;
            z-index: 10;
            padding: 10px 18px;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #4a5568;
            text-align: left;
        }

        /* =============================== */
        /* AUTRES COMPOSANTS SPÉCIFIQUES */
        /* =============================== */
        /* Suggestions d'autocomplétion */
        .fournisseur-suggestion {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.2s;
        }

        .fournisseur-suggestion:hover {
            background-color: #f3f4f6;
        }

        .fournisseur-suggestion:last-child {
            border-bottom: none;
        }

        .product-suggestion {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.2s;
        }

        .product-suggestion:hover {
            background-color: #f3f4f6;
        }

        .product-suggestion:last-child {
            border-bottom: none;
        }

        #fournisseurs-suggestions,
        #fournisseurs-suggestions-bulk,
        #product-suggestions {
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            background-color: white;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        #fournisseurs-suggestions.active,
        #fournisseurs-suggestions-bulk.active,
        #product-suggestions.active {
            display: block;
        }

        /* Tooltips */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            text-wrap: auto;
            transition: opacity 0.3s;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Messages flash */
        .flash-message {
            animation: fadeOut 5s forwards;
            animation-delay: 3s;
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }

            to {
                opacity: 0;
            }
        }

        /* Animation de pulse */
        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                background-color: #fef3c7;
            }

            50% {
                background-color: #fffbeb;
            }

            100% {
                background-color: #fef3c7;
            }
        }

        /* =============================== */
        /* STYLES POUR LES MODES DE PAIEMENT */
        /* =============================== */

        /* Sélecteur de mode de paiement */
        .payment-method-select {
            position: relative;
        }

        .payment-method-select select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 2.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 3.5rem;
        }

        .payment-method-select .material-icons {
            right: 2.5rem;
        }

        /* Description du mode de paiement */
        .payment-method-description {
            transition: all 0.3s ease;
            min-height: 1.25rem;
        }

        .payment-method-description.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Badges de mode de paiement */
        .payment-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .payment-badge-cash {
            background-color: #fef3c7;
            color: #92400e;
        }

        .payment-badge-bank {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .payment-badge-check {
            background-color: #e0e7ff;
            color: #5b21b6;
        }

        .payment-badge-mobile {
            background-color: #dcfce7;
            color: #166534;
        }

        .payment-badge-credit {
            background-color: #fef2f2;
            color: #dc2626;
        }

        /* Animation pour les sélecteurs */
        .payment-method-select select:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            border-color: #3b82f6;
        }

        /* Icône de mode de paiement dans les tableaux */
        .payment-method-icon {
            width: 1rem;
            height: 1rem;
            margin-right: 0.25rem;
        }

        /* Validation des champs */
        .payment-method-select.error select {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .payment-method-select.success select {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        /* =============================== */
        /* RÉACTIVITÉ ET MEDIA QUERIES */
        /* =============================== */
        @media (max-width: 768px) {

            /* Ajustements pour petits écrans */
            table.dataTable tbody td {
                padding: 8px;
            }

            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_length {
                text-align: left;
                margin-bottom: 0.5rem;
            }

            .dataTables_wrapper .dataTables_filter input {
                width: 100%;
                margin-left: 0;
            }

            /* Tables spécifiques sur mobile */
            #partialOrdersTable {
                font-size: 0.85rem;
            }

            #partialOrdersTable td,
            #partialOrdersTable th {
                padding: 0.5rem;
            }

            /* Responsive pour modes de paiement */
            .payment-badge {
                font-size: 0.625rem;
                padding: 0.125rem 0.5rem;
            }

            .payment-method-select select {
                font-size: 0.875rem;
            }
        }

        @media (min-width: 1024px) {

            /* Ajustements pour grands écrans */
            .md\:grid-cols-2.grid-span .bg-white:last-child {
                grid-column: 1/2 span;
            }
        }
    </style>

</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- Header de la page -->
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex flex-wrap justify-between items-center">
                <div class="flex items-center m-2">
                    <button class="validate-btn mr-4">Tableau de Bord Achats Matériaux</button>
                </div>

                <div class="date-time m-2">
                    <span class="material-icons">event</span>
                    <span id="date-time-display"></span>
                </div>
            </div>

            <!-- Message flash -->
            <?php if (!empty($message)): ?>
                <div id="flash-message"
                    class="flash-message bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                    <span class="block sm:inline"><?php echo $message; ?></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3"
                        onclick="document.getElementById('flash-message').style.display='none';">
                        <span class="material-icons">close</span>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Onglets de navigation -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex flex-wrap space-x-8">
                        <!-- Onglet "Liste des Matériaux à Acheter" -->
                        <a id="tab-materials" href="#"
                            class="tab-btn border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center"
                            aria-current="page">
                            <span class="material-icons mr-2">inventory</span>
                            Liste des Matériaux à Acheter
                            <span class="ml-1 bg-blue-100 text-blue-800 text-xs font-medium px-2 py-0.5 rounded-full">
                                <?php echo $materialsCount; ?>
                            </span>
                        </a>

                        <!-- Onglet "Achats Récents" -->
                        <a id="tab-recents" href="#"
                            class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                            <span class="material-icons mr-2">history</span>
                            Achats Récents
                            <span class="ml-1 bg-green-100 text-green-800 text-xs font-medium px-2 py-0.5 rounded-full">
                                <?php echo count($recents); ?>
                            </span>
                        </a>

                        <!-- Bouton Archive des Bons de Commande -->
                        <a href="gestion-bon-commande/commandes_archive.php"
                            class="ml-2 bg-blue-500 hover:bg-blue-600 text-white py-1 px-2 rounded-md text-sm flex items-center">
                            <span class="material-icons mr-1" style="font-size: 16px;">inventory</span>
                            Archive BC
                        </a>
                        <!-- Onglet Commandes annulées -->
                        <a id="tab-canceled" href="#"
                            class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                            <span class="material-icons mr-2">cancel</span>Commandes Annulées
                            <span class="ml-1 bg-red-100 text-red-800 text-xs font-medium px-2 py-0.5 rounded-full">
                                <?php echo isset($canceledOrdersCount) ? $canceledOrdersCount : 0; ?>
                            </span>
                        </a>

                        <!-- Onglet "Retours Fournisseurs" -->
                        <a id="tab-returns" href="#"
                            class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                            <span class="material-icons">assignment_return</span>Retours Fournisseurs
                            <span class="ml-1 bg-gray-100 text-gray-800 text-xs font-medium px-2 py-0.5 rounded-full">
                                <?php echo $retourStats['total_retours']; ?>
                            </span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Contenu de l'onglet "Liste des Matériaux à Acheter" avec trois sections -->
            <div id="content-materials" class="tab-content bg-white rounded-lg shadow-sm p-6 mb-6">
                <!-- Lien vers l'historique des substitutions -->
                <div class="flex justify-end mb-2">
                    <a href="views/substitution_history.php"
                        class="bg-purple-100 text-purple-800 hover:bg-purple-200 rounded-md px-3 py-2 text-sm font-medium flex items-center">
                        <span class="material-icons text-sm mr-1">swap_horiz</span>
                        Historique des substitutions
                    </a>
                </div>

                <div class="flex flex-wrap justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Liste des Matériaux à Commander</h2>
                    <button id="bulk-purchase-btn"
                        class="bg-blue-500 my-2 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                        <span class="material-icons align-middle mr-1">shopping_basket</span>
                        Commander les éléments sélectionnés
                    </button>
                </div>

                <!-- Tabs pour les trois sections -->
                <div class="border-b border-gray-200 mb-6">
                    <ul class="flex flex-wrap -mb-px">
                        <li class="mr-2">
                            <a href="#" id="materials-pending-tab"
                                class="materials-tab inline-block py-2 px-4 text-blue-600 border-b-2 border-blue-600 rounded-t-lg active flex items-center"
                                data-target="materials-pending">
                                <span class="material-icons mr-2 text-xl">hourglass_empty</span>
                                Matériaux En Attente
                                <span class="ml-1 bg-blue-100 text-blue-800 text-xs font-medium px-2 py-0.5 rounded-full">
                                    <?php echo $pendingMaterialsCount; ?>
                                </span>
                            </a>
                        </li>
                        <li class="mr-2">
                            <!-- Onglet "Commandes Partielles" -->
                            <a href="#" id="materials-partial-tab"
                                class="materials-tab inline-block py-2 px-4 text-gray-500 hover:text-gray-600 hover:border-gray-300 border-b-2 border-transparent rounded-t-lg flex items-center"
                                data-target="materials-partial">
                                <span class="material-icons mr-2 text-xl">content_paste</span>
                                Commandes Partielles
                                <span
                                    class="ml-1 bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-0.5 rounded-full <?php echo ($notifications['counts']['partial'] <= 0) ? 'hidden' : ''; ?>">
                                    <?php echo $notifications['counts']['partial']; ?>
                                </span>
                            </a>
                        </li>
                        <li class="mr-2">
                            <!-- Onglet "Matériaux Commandés" -->
                            <a href="#" id="materials-ordered-tab"
                                class="materials-tab inline-block py-2 px-4 text-gray-500 hover:text-gray-600 hover:border-gray-300 border-b-2 border-transparent rounded-t-lg flex items-center"
                                data-target="materials-ordered">
                                <span class="material-icons mr-2 text-xl">check_circle_outline</span>
                                Matériaux Commandés
                                <span
                                    class="ml-1 bg-green-100 text-green-800 text-xs font-medium px-2 py-0.5 rounded-full <?php echo ($notifications['counts']['ordered'] <= 0) ? 'hidden' : ''; ?>">
                                    <?php echo $notifications['counts']['ordered']; ?>
                                </span>
                            </a>
                        </li>
                        <li class="mr-2">
                            <!-- Lien vers l'historique des modifications -->
                            <a href="views/order_modifications_history.php"
                                class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-200">
                                <span class="material-icons mr-3">history</span>
                                Historique des modifications
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Section: Matériaux En Attente -->
                <div id="materials-pending" class="materials-section">
                    <div class="flex flex-wrap justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-700 my-3">Matériaux en attente de commande</h3>
                        <button id="bulk-cancel-pending-btn"
                            class="bg-red-500 my-1 hover:bg-red-600 text-white px-3 py-1 rounded-md text-sm transition flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="material-icons text-sm mr-1">cancel</span>
                            Annuler les matériaux sélectionnés
                        </button>
                    </div>
                    <!-- Section des filtres avancés -->
                    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                        <div class="flex justify-between items-center mb-2">
                            <div class="flex items-center">
                                <button id="toggle-filters-btn"
                                    class="flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <span class="material-icons mr-1">filter_list</span>Filtres
                                </button>
                                <span id="filtered-results-counter"
                                    class="ml-3 text-sm text-blue-600 font-medium hidden"></span>
                            </div>
                            <div class="flex items-center">
                                <button id="export-filtered-btn"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium bg-green-100 text-green-700 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <span class="material-icons mr-1 text-sm">file_download</span>Exporter résultats
                                </button>
                            </div>
                        </div>

                        <!-- Conteneur des filtres avancés (masqué par défaut) -->
                        <div id="advanced-filters-container" class="mt-4 bg-blue-50 rounded-md p-4 shadow-inner hidden">
                            <form id="advanced-filters-form" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Filtre par projet -->
                                    <div>
                                        <label for="projet"
                                            class="block text-sm font-medium text-gray-700 mb-1">Projet</label>
                                        <select name="projet" id="projet"
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            <option value="">Tous les projets</option>
                                        </select>
                                    </div>

                                    <!-- Filtre par client -->
                                    <div>
                                        <label for="client"
                                            class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                                        <select name="client" id="client"
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            <option value="">Tous les clients</option>
                                        </select>
                                    </div>

                                    <!-- Filtre par produit (recherche textuelle) -->
                                    <div>
                                        <label for="produit"
                                            class="block text-sm font-medium text-gray-700 mb-1">Produit
                                            contient</label>
                                        <input type="text" name="produit" id="produit" placeholder="Nom ou référence..."
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                    </div>

                                    <!-- Filtre par fournisseur -->
                                    <div>
                                        <label for="fournisseur"
                                            class="block text-sm font-medium text-gray-700 mb-1">Fournisseur
                                            suggéré</label>
                                        <select name="fournisseur" id="fournisseur"
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            <option value="">Tous les fournisseurs</option>
                                        </select>
                                    </div>

                                    <!-- Filtre par unité -->
                                    <div>
                                        <label for="unite"
                                            class="block text-sm font-medium text-gray-700 mb-1">Unité</label>
                                        <select name="unite" id="unite"
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            <option value="">Toutes les unités</option>
                                        </select>
                                    </div>

                                    <!-- Filtre par source -->
                                    <div>
                                        <label for="source"
                                            class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                                        <select name="source" id="source"
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            <option value="">Toutes les sources</option>
                                            <option value="expression_dym">Expressions DYM</option>
                                            <option value="besoins">Besoins Système</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Filtre par date -->
                                    <div class="bg-white p-3 rounded-md">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Période</label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label for="dateDebut" class="block text-xs text-gray-500">Du</label>
                                                <input type="date" name="dateDebut" id="dateDebut"
                                                    class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label for="dateFin" class="block text-xs text-gray-500">Au</label>
                                                <input type="date" name="dateFin" id="dateFin"
                                                    class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Statut et Boutons d'action -->
                                    <div class="flex flex-col justify-between">
                                        <div>
                                            <label for="statut"
                                                class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                                            <select name="statut" id="statut"
                                                class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                                <option value="">Tous les statuts</option>
                                                <option value="en_attente">En attente</option>
                                                <option value="valide">Validé</option>
                                                <option value="recu">Reçu</option>
                                            </select>
                                        </div>

                                        <div class="flex justify-end space-x-3 mt-3">
                                            <button id="reset-filters-btn" type="button"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                                <span
                                                    class="material-icons mr-1 text-sm">restart_alt</span>Réinitialiser
                                            </button>
                                            <button id="apply-filters-btn" type="submit"
                                                class="inline-flex items-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <span class="material-icons mr-1 text-sm">filter_alt</span>Appliquer
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="pendingMaterialsTable" class="display responsive nowrap w-full">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all-pending-materials"
                                            class="select-all-checkbox"></th>
                                    <th>Projet</th>
                                    <th>Client</th>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Unité</th>
                                    <th>Statut</th>
                                    <th>Fournisseur</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    // boucle qui affiche les matériaux en attente
                                    foreach ($allMaterials as $material) {
                                        try {
                                            // N'afficher que les matériaux qui sont réellement en attente (pas validé)
                                            // et qui ne sont pas en cours de commande partielle
                                            $isPending = (
                                                $material['valide_achat'] === 'pas validé' ||
                                                $material['valide_achat'] === NULL ||
                                                $material['valide_achat'] === ''
                                            );
                                            $isPartial = $material['valide_achat'] === 'en_cours';

                                            // On n'affiche que les matériaux complètement en attente
                                            if (!$isPending || $isPartial) {
                                                continue;
                                            }

                                            // Déterminer la source du matériau 
                                            $sourceTable = $material['source_table'] ?? 'expression_dym';

                                            // Adapter les champs en fonction de la source
                                            if ($sourceTable === 'besoins') {
                                                $designation = htmlspecialchars($material['designation'] ?? $material['designation_article'] ?? 'N/A');
                                                $expressionId = htmlspecialchars($material['idBesoin'] ?? '');
                                                $projet = htmlspecialchars($material['code_projet'] ?? 'N/A');
                                                $client = htmlspecialchars($material['nom_client'] ?? 'N/A');
                                                $quantite = htmlspecialchars($material['qt_acheter'] ?? 0);
                                                $unite = htmlspecialchars($material['unit'] ?? $material['caracteristique'] ?? 'N/A');
                                            } else {
                                                $designation = htmlspecialchars($material['designation'] ?? 'N/A');
                                                $expressionId = htmlspecialchars($material['idExpression'] ?? '');
                                                $projet = htmlspecialchars($material['code_projet'] ?? 'N/A');
                                                $client = htmlspecialchars($material['nom_client'] ?? 'N/A');
                                                $quantite = htmlspecialchars($material['qt_acheter'] ?? 0);
                                                $unite = htmlspecialchars($material['unit'] ?? 'N/A');
                                            }

                                            $fournisseur = htmlspecialchars($material['fournisseur'] ?? 'N/A');
                                            $dateCreation = formatDateForDisplay($material['created_at']);
                                ?>
                                            <tr class="material-row pending">
                                                <td>
                                                    <input type="checkbox" class="material-checkbox"
                                                        data-id="<?= $material['id'] ?>"
                                                        data-expression="<?= $sourceTable === 'besoins' ? $material['idBesoin'] : $expressionId ?>"
                                                        data-designation="<?= htmlspecialchars($designation) ?>"
                                                        data-quantity="<?= $quantite ?>"
                                                        data-unit="<?= htmlspecialchars($unit ?? $unite) ?>"
                                                        data-source-table="<?= $sourceTable ?>">
                                                    <?php if ($sourceTable === 'besoins'): ?>
                                                        <input type="hidden" class="besoin-id-data"
                                                            value="<?= $material['idBesoin'] ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $projet ?></td>
                                                <td><?= $client ?></td>
                                                <td><?= htmlspecialchars($designation) ?>
                                                    <span class="source-indicator hidden" data-source="<?= $sourceTable ?>"></span>
                                                    <?php if ($sourceTable === 'besoins'): ?>
                                                        <span
                                                            class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Système</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $quantite ?></td>
                                                <td><?= $unite ?></td>
                                                <td>
                                                    <span class="status-badge status-pending">En attente</span>
                                                </td>
                                                <td>
                                                    <?php
                                                    if (!empty($material['fournisseur'])):
                                                        echo htmlspecialchars($material['fournisseur']);
                                                    elseif (!empty($material['dernier_fournisseur'])): ?>
                                                        <div class="flex items-center tooltip">
                                                            <span
                                                                class="text-gray-500"><?= htmlspecialchars($material['dernier_fournisseur']) ?></span>
                                                            <span
                                                                class="ml-1 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">Précédent</span>
                                                            <span class="tooltiptext">Ce fournisseur a été utilisé lors de la dernière
                                                                commande de ce produit</span>
                                                        </div>
                                                        <?php else:

                                                        $ch = curl_init();
                                                        curl_setopt($ch, CURLOPT_URL, 'get_suggested_fournisseur.php?designation=' . urlencode($material['designation']));
                                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                        $result = curl_exec($ch);
                                                        curl_close($ch);
                                                        $suggestion = json_decode($result, true);

                                                        if ($suggestion): ?>
                                                            <div class="flex items-center tooltip">
                                                                <span
                                                                    class="text-gray-500"><?= htmlspecialchars($suggestion['fournisseur']) ?></span>
                                                                <span
                                                                    class="ml-1 text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded-full">Suggéré</span>
                                                                <span class="tooltiptext">Ce fournisseur est recommandé pour les produits de
                                                                    catégorie "<?= $suggestion['categorie'] ?>"</span>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">-</span>
                                                    <?php endif;
                                                    endif; ?>
                                                </td>
                                                <td><?= $dateCreation ?></td>
                                                <td class="text-center">


                                                    <button
                                                        onclick="openSubstitutionModal('<?= $material['id'] ?>', '<?= addslashes($designation) ?>', '<?= $sourceTable === 'besoins' ? $material['idBesoin'] : $material['idExpression'] ?>', '<?= $sourceTable ?>')"
                                                        class="btn-action bg-purple-600 text-white hover:text-purple-800 ml-2"
                                                        title="Substituer ce produit">
                                                        <span class="material-icons">swap_horiz</span>
                                                    </button>

                                                    <button
                                                        onclick="cancelPendingMaterial('<?= $material['id'] ?>', '<?= $material['idExpression'] ?>', '<?= addslashes($material['designation']) ?>', '<?= $sourceTable ?>')"
                                                        class="btn-action text-red-600 hover:text-red-800"
                                                        title="Annuler ce matériau">
                                                        <span class="material-icons">cancel</span>
                                                    </button>

                                                </td>
                                            </tr>
                                        <?php
                                        } catch (Exception $materialException) {
                                            error_log("Erreur de traitement du matériau : " . $materialException->getMessage());
                                        ?>
                                            <tr class="bg-red-100">
                                                <td colspan="10">Erreur de chargement du matériau</td>
                                            </tr>
                                    <?php
                                        }
                                    }
                                } catch (Exception $globalException) {
                                    error_log("Erreur globale lors du chargement des matériaux : " . $globalException->getMessage());
                                    ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-red-600">
                                            Impossible de charger les matériaux. Une erreur s'est produite.
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section: Commandes Partielles (initialement cachée) -->
                <div id="materials-partial" class="materials-section hidden">
                    <div class="flex flex-wrap justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-700">Matériaux à compléter</h3>
                        <div class="flex flex-wrap space-x-2">
                            <button id="bulk-complete-btn"
                                class="bg-yellow-500 my-1 hover:bg-yellow-600 text-white px-3 py-1 rounded-md text-sm transition flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="material-icons text-sm mr-1">shopping_basket</span>
                                Compléter les commandes sélectionnées
                            </button>
                            <button id="export-excel"
                                class="bg-green-500 my-1 hover:bg-green-600 text-white px-3 py-1 rounded-md text-sm transition flex items-center">
                                <span class="material-icons text-sm mr-1">file_download</span>
                                Exporter Excel
                            </button>
                            <button id="refresh-list"
                                class="bg-blue-500 my-1 hover:bg-blue-600 text-white px-3 py-1 rounded-md text-sm transition flex items-center">
                                <span class="material-icons text-sm mr-1">refresh</span>
                                Actualiser
                            </button>
                        </div>
                    </div>

                    <!-- Tableau des commandes partielles avec structure adaptée pour DataTables -->
                    <div class="overflow-x-auto">
                        <table id="partialOrdersTable" class="display responsive nowrap w-full">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="select-all-partial-materials"
                                            class="select-all-checkbox">
                                    </th>
                                    <th>Projet</th>
                                    <th>Client</th>
                                    <th>Désignation</th>
                                    <th>Quantité initiale</th>
                                    <th>Quantité commandée</th>
                                    <th>Quantité restante</th>
                                    <th>Progression</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="partial-orders-body">
                                <!-- Les données seront chargées dynamiquement par JavaScript -->
                                <tr>
                                    <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                                        Chargement des données...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section: Matériaux Commandés (initialement cachée) -->
                <div id="materials-ordered" class="materials-section hidden">
                    <div class="flex flex-wrap justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-700">Matériaux en attente de réception</h3>
                        <div>
                            <button onclick="checkOrderValidationStatus()"
                                class="bg-blue-500 my-1 hover:bg-blue-600 text-white px-3 py-1 rounded-md text-sm transition flex items-center mr-2">
                                <span class="material-icons text-sm mr-1">refresh</span>
                                Vérifier les validations finance
                            </button>
                            <button id="bulk-cancel-btn"
                                class="bg-red-500 my-1 hover:bg-red-600 text-white px-3 py-1 rounded-md text-sm transition flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="material-icons text-sm mr-1">cancel</span>
                                Annuler les commandes sélectionnées
                            </button>
                        </div>
                    </div>
                    <!-- Section des filtres avancés pour matériaux commandés -->
                    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                        <div class="flex justify-between items-center mb-2">
                            <div class="flex items-center">
                                <button id="toggle-ordered-filters-btn"
                                    class="flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <span class="material-icons mr-1">filter_list</span>Filtres
                                </button>
                                <span id="filtered-ordered-counter"
                                    class="ml-3 text-sm text-blue-600 font-medium hidden"></span>
                            </div>
                            <div class="flex items-center">
                                <button id="export-ordered-filtered-btn"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium bg-green-100 text-green-700 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <span class="material-icons mr-1 text-sm">file_download</span>Exporter résultats
                                </button>
                            </div>
                        </div>

                        <!-- Conteneur des filtres avancés (masqué par défaut) -->
                        <div id="ordered-filters-container" class="mt-4 bg-blue-50 rounded-md p-4 shadow-inner hidden">
                            <form id="ordered-filters-form" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Filtre par projet -->
                                    <div>
                                        <label for="projet-ordered"
                                            class="block text-sm font-medium text-gray-700 mb-1">Projet</label>
                                        <select name="projet" id="projet-ordered"
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            <option value="">Tous les projets</option>
                                        </select>
                                    </div>

                                    <!-- Filtre par client -->
                                    <div>
                                        <label for="client-ordered"
                                            class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                                        <select name="client" id="client-ordered"
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            <option value="">Tous les clients</option>
                                        </select>
                                    </div>

                                    <!-- Filtre par produit (recherche textuelle) -->
                                    <div>
                                        <label for="produit-ordered"
                                            class="block text-sm font-medium text-gray-700 mb-1">Produit
                                            contient</label>
                                        <input type="text" name="produit" id="produit-ordered"
                                            placeholder="Nom ou référence..."
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                    </div>

                                    <!-- Filtre par fournisseur -->
                                    <div>
                                        <label for="fournisseur-ordered"
                                            class="block text-sm font-medium text-gray-700 mb-1">Fournisseur</label>
                                        <select name="fournisseur" id="fournisseur-ordered"
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            <option value="">Tous les fournisseurs</option>
                                        </select>
                                    </div>

                                    <!-- Filtre par unité -->
                                    <div>
                                        <label for="unite-ordered"
                                            class="block text-sm font-medium text-gray-700 mb-1">Unité</label>
                                        <select name="unite" id="unite-ordered"
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            <option value="">Toutes les unités</option>
                                        </select>
                                    </div>

                                    <!-- Filtre par statut de validation -->
                                    <div>
                                        <label for="validation-ordered"
                                            class="block text-sm font-medium text-gray-700 mb-1">État validation</label>
                                        <select name="validation" id="validation-ordered"
                                            class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            <option value="">Tous les états</option>
                                            <!-- Les options seront ajoutées dynamiquement par JavaScript -->
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Filtre par date -->
                                    <div class="bg-white p-3 rounded-md">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Période</label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label for="dateDebut-ordered"
                                                    class="block text-xs text-gray-500">Du</label>
                                                <input type="date" name="dateDebut" id="dateDebut-ordered"
                                                    class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label for="dateFin-ordered"
                                                    class="block text-xs text-gray-500">Au</label>
                                                <input type="date" name="dateFin" id="dateFin-ordered"
                                                    class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Filtre par prix -->
                                    <div class="bg-white p-3 rounded-md">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Prix unitaire
                                            (FCFA)</label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label for="prixMin-ordered"
                                                    class="block text-xs text-gray-500">Min</label>
                                                <input type="number" name="prixMin" id="prixMin-ordered" min="0"
                                                    step="100"
                                                    class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label for="prixMax-ordered"
                                                    class="block text-xs text-gray-500">Max</label>
                                                <input type="number" name="prixMax" id="prixMax-ordered" min="0"
                                                    step="100"
                                                    class="block w-full shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-3">
                                    <button id="reset-ordered-filters-btn" type="button"
                                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                        <span class="material-icons mr-1 text-sm">restart_alt</span>Réinitialiser
                                    </button>
                                    <button id="apply-ordered-filters-btn" type="submit"
                                        class="inline-flex items-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <span class="material-icons mr-1 text-sm">filter_alt</span>Appliquer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="orderedMaterialsTable" class="display responsive nowrap w-full">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all-ordered-materials"
                                            class="select-all-checkbox"></th>
                                    <th>Projet</th>
                                    <th>Client</th>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Unité</th>
                                    <th>Statut</th>
                                    <th>Prix Unit.</th>
                                    <th>Fournisseur</th>
                                    <th>Date</th>
                                    <th>Qté Restante</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    // ===============================================
                                    // REQUÊTE ULTRA-OPTIMISÉE POUR MATÉRIAUX COMMANDÉS
                                    // ===============================================
                                    // Correction complète pour les besoins système avec fallbacks robustes

                                    $orderedQuery = "
                                    SELECT DISTINCT
                                        main_data.*,
                                        /* Récupération des informations d'achat avec fallbacks */
                                        (SELECT am.id 
                                         FROM achats_materiaux am 
                                         WHERE BINARY am.expression_id = BINARY main_data.expression_id 
                                         AND BINARY am.designation = BINARY main_data.designation
                                         ORDER BY am.date_achat DESC LIMIT 1) as achat_id,
                                                                
                                        (SELECT am.date_achat 
                                         FROM achats_materiaux am 
                                         WHERE BINARY am.expression_id = BINARY main_data.expression_id 
                                         AND BINARY am.designation = BINARY main_data.designation
                                         ORDER BY am.date_achat DESC LIMIT 1) as date_achat,
                                                                
                                        (SELECT am.is_partial 
                                         FROM achats_materiaux am 
                                         WHERE BINARY am.expression_id = BINARY main_data.expression_id 
                                         AND BINARY am.designation = BINARY main_data.designation
                                         ORDER BY am.date_achat DESC LIMIT 1) as is_partial,
                                                                
                                        /* Informations bon de commande */
                                        (SELECT po.id 
                                         FROM purchase_orders po 
                                         WHERE (BINARY po.expression_id = BINARY main_data.expression_id 
                                                OR JSON_CONTAINS(po.related_expressions, CONCAT('\"', main_data.expression_id, '\"')))
                                         AND BINARY po.fournisseur = BINARY main_data.fournisseur
                                         ORDER BY po.generated_at DESC LIMIT 1) as bon_commande_id,
                                                                
                                        (SELECT po.order_number 
                                         FROM purchase_orders po 
                                         WHERE (BINARY po.expression_id = BINARY main_data.expression_id 
                                                OR JSON_CONTAINS(po.related_expressions, CONCAT('\"', main_data.expression_id, '\"')))
                                         AND BINARY po.fournisseur = BINARY main_data.fournisseur
                                         ORDER BY po.generated_at DESC LIMIT 1) as bon_commande_number,
                                                                
                                        (SELECT po.signature_finance 
                                         FROM purchase_orders po 
                                         WHERE (BINARY po.expression_id = BINARY main_data.expression_id 
                                                OR JSON_CONTAINS(po.related_expressions, CONCAT('\"', main_data.expression_id, '\"')))
                                         AND BINARY po.fournisseur = BINARY main_data.fournisseur
                                         ORDER BY po.generated_at DESC LIMIT 1) as signature_finance,
                                                                
                                        (SELECT po.user_finance_id 
                                         FROM purchase_orders po 
                                         WHERE (BINARY po.expression_id = BINARY main_data.expression_id 
                                                OR JSON_CONTAINS(po.related_expressions, CONCAT('\"', main_data.expression_id, '\"')))
                                         AND BINARY po.fournisseur = BINARY main_data.fournisseur
                                         ORDER BY po.generated_at DESC LIMIT 1) as user_finance_id,
                                                                
                                        (SELECT po.file_path 
                                         FROM purchase_orders po 
                                         WHERE (BINARY po.expression_id = BINARY main_data.expression_id 
                                                OR JSON_CONTAINS(po.related_expressions, CONCAT('\"', main_data.expression_id, '\"')))
                                         AND BINARY po.fournisseur = BINARY main_data.fournisseur
                                         ORDER BY po.generated_at DESC LIMIT 1) as bon_commande_path
                                                                
                                    FROM (
                                        /* SOUS-REQUÊTE 1: Matériaux depuis expression_dym */
                                        SELECT 
                                            ed.id as expression_row_id,
                                            ed.idExpression as expression_id, 
                                            ed.designation, 
                                            ed.qt_acheter as quantity, 
                                            ed.initial_qt_acheter as original_quantity,
                                            ed.unit, 
                                            ed.prix_unitaire,
                                            ed.fournisseur,
                                            ed.valide_achat as status,
                                            ed.qt_restante,
                                            ed.quantity_stock,
                                            ed.created_at,
                                            GREATEST(0, (COALESCE(ed.qt_acheter, 0) + COALESCE(ed.qt_restante, 0)) - COALESCE(ed.quantity_stock, 0)) as quantity_remaining,
                                            ip.code_projet,
                                            ip.nom_client,
                                            (SELECT COUNT(*) 
                                             FROM achats_materiaux am 
                                             WHERE BINARY am.expression_id = BINARY ed.idExpression 
                                             AND BINARY am.designation = BINARY ed.designation) as command_count,
                                            (SELECT SUM(am.quantity) 
                                             FROM achats_materiaux am 
                                             WHERE BINARY am.expression_id = BINARY ed.idExpression 
                                             AND BINARY am.designation = BINARY ed.designation 
                                             AND am.is_partial = 1) as total_partial_quantity,
                                            'expression_dym' as source_table
                                        FROM expression_dym ed
                                        INNER JOIN identification_projet ip ON BINARY ed.idExpression = BINARY ip.idExpression
                                        WHERE (ed.valide_achat IN ('validé', 'en_cours', 'valide_en_cours'))
                                        AND ed.qt_acheter > 0
                                        " . (function_exists('getFilteredDateCondition') ? "AND " . getFilteredDateCondition('ed.created_at') : "") . "
                                                                
                                        UNION ALL
                                                                
                                        /* SOUS-REQUÊTE 2: Matériaux depuis besoins (système) - ULTRA-OPTIMISÉE */
                                        SELECT 
                                            b.id as expression_row_id,
                                            b.idBesoin as expression_id, 
                                            b.designation_article as designation, 
                                            COALESCE(b.qt_acheter, 0) as quantity, 
                                            b.qt_demande as original_quantity,
                                            b.caracteristique as unit, 
                                                                
                                            /* CORRECTION PRIX UNITAIRE - Priorité : achats_materiaux > products > 0 */
                                            COALESCE(
                                                NULLIF((SELECT am.prix_unitaire 
                                                        FROM achats_materiaux am 
                                                        WHERE BINARY am.expression_id = BINARY b.idBesoin 
                                                        AND BINARY am.designation = BINARY b.designation_article
                                                        AND am.prix_unitaire IS NOT NULL 
                                                        AND am.prix_unitaire > 0
                                                        ORDER BY am.date_achat DESC LIMIT 1), 0),
                                                NULLIF((SELECT p.unit_price 
                                                        FROM products p 
                                                        WHERE BINARY p.product_name = BINARY b.designation_article
                                                        AND p.unit_price IS NOT NULL 
                                                        AND p.unit_price > 0
                                                        LIMIT 1), 0),
                                                NULLIF((SELECT p.prix_moyen 
                                                        FROM products p 
                                                        WHERE BINARY p.product_name = BINARY b.designation_article
                                                        AND p.prix_moyen IS NOT NULL 
                                                        AND p.prix_moyen > 0
                                                        LIMIT 1), 0),
                                                0
                                            ) as prix_unitaire,
                                                                
                                            /* CORRECTION FOURNISSEUR - Priorité : achats_materiaux > besoins > fallback */
                                            COALESCE(
                                                NULLIF((SELECT am.fournisseur 
                                                        FROM achats_materiaux am 
                                                        WHERE BINARY am.expression_id = BINARY b.idBesoin 
                                                        AND BINARY am.designation = BINARY b.designation_article
                                                        AND am.fournisseur IS NOT NULL 
                                                        AND am.fournisseur != ''
                                                        ORDER BY am.date_achat DESC LIMIT 1), ''),
                                                NULLIF(b.fournisseur, ''),
                                                NULLIF((SELECT am2.fournisseur 
                                                        FROM achats_materiaux am2 
                                                        WHERE BINARY am2.designation = BINARY b.designation_article
                                                        AND am2.fournisseur IS NOT NULL 
                                                        AND am2.fournisseur != ''
                                                        ORDER BY am2.date_achat DESC LIMIT 1), ''),
                                                'Non spécifié'
                                            ) as fournisseur,
                                                                
                                            b.achat_status as status,
                                            COALESCE(b.qt_restante, GREATEST(0, b.qt_demande - COALESCE(b.qt_acheter, 0))) as qt_restante,
                                            b.quantity_dispatch_stock as quantity_stock,
                                            b.created_at,
                                                                
                                            /* CALCUL QUANTITÉ RESTANTE POUR BESOINS */
                                            GREATEST(0, COALESCE(b.qt_acheter, 0) - COALESCE(b.quantity_dispatch_stock, 0)) as quantity_remaining,
                                                                
                                            CONCAT('SYS-', COALESCE(d.client, 'SYSTÈME')) as code_projet,
                                            COALESCE(d.client, 'Demande interne') as nom_client,
                                                                
                                            (SELECT COUNT(*) 
                                             FROM achats_materiaux am 
                                             WHERE BINARY am.expression_id = BINARY b.idBesoin 
                                             AND BINARY am.designation = BINARY b.designation_article) as command_count,
                                            (SELECT SUM(am.quantity) 
                                             FROM achats_materiaux am 
                                             WHERE BINARY am.expression_id = BINARY b.idBesoin 
                                             AND BINARY am.designation = BINARY b.designation_article 
                                             AND am.is_partial = 1) as total_partial_quantity,
                                            'besoins' as source_table
                                                                
                                        FROM besoins b
                                        LEFT JOIN demandeur d ON BINARY b.idBesoin = BINARY d.idBesoin
                                        WHERE (b.achat_status IN ('validé', 'en_cours', 'valide_en_cours'))
                                        AND b.qt_acheter > 0
                                        " . (function_exists('getFilteredDateCondition') ? "AND " . getFilteredDateCondition('b.created_at') : "") . "
                                    ) as main_data
                                                                
                                    ORDER BY 
                                        COALESCE(
                                            (SELECT MAX(am.date_achat) 
                                             FROM achats_materiaux am 
                                             WHERE BINARY am.expression_id = BINARY main_data.expression_id 
                                             AND BINARY am.designation = BINARY main_data.designation), 
                                            main_data.created_at
                                        ) DESC";

                                    // ===============================================
                                    // EXÉCUTION DE LA REQUÊTE ET TRAITEMENT
                                    // ===============================================

                                    $orderedStmt = $pdo->prepare($orderedQuery);
                                    $orderedStmt->execute();
                                    $orderedMaterials = $orderedStmt->fetchAll(PDO::FETCH_ASSOC);

                                    // ===============================================
                                    // AFFICHAGE OPTIMISÉ DES RÉSULTATS
                                    // ===============================================

                                    if (empty($orderedMaterials)) {
                                ?>
                                        <tr>
                                            <td colspan="12" class="text-center text-gray-500 py-8">
                                                <div class="flex flex-col items-center">
                                                    <span class="material-icons text-4xl text-gray-300 mb-2">inventory_2</span>
                                                    <p class="text-lg font-medium">Aucun matériau en attente de réception</p>
                                                    <p class="text-sm text-gray-400 mt-1">Les matériaux commandés apparaîtront ici</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    } else {
                                        // Boucle d'affichage optimisée
                                        foreach ($orderedMaterials as $material) {
                                            try {
                                                // ===============================================
                                                // RÉCUPÉRATION ET VALIDATION DES DONNÉES
                                                // ===============================================

                                                $sourceTable = $material['source_table'] ?? 'expression_dym';
                                                $isSystemRequest = ($sourceTable === 'besoins');

                                                // ID de commande avec fallback
                                                $achatId = $material['achat_id'];
                                                if (!$achatId) {
                                                    $findOrderQuery = "SELECT id FROM achats_materiaux 
                                                    WHERE BINARY expression_id = BINARY ? 
                                                    AND BINARY designation = BINARY ? 
                                                    ORDER BY date_achat DESC LIMIT 1";
                                                    $findStmt = $pdo->prepare($findOrderQuery);
                                                    $findStmt->execute([$material['expression_id'], $material['designation']]);
                                                    $orderResult = $findStmt->fetch(PDO::FETCH_ASSOC);
                                                    $achatId = $orderResult['id'] ?? 0;
                                                }

                                                // ===============================================
                                                // TRAITEMENT DES PRIX AVEC FALLBACKS ROBUSTES
                                                // ===============================================

                                                $prixUnitaire = floatval($material['prix_unitaire'] ?? 0);
                                                $prixDisplay = '0';
                                                $prixClass = 'text-gray-500';

                                                if ($prixUnitaire > 0) {
                                                    $prixDisplay = number_format($prixUnitaire, 0, ',', ' ');
                                                    $prixClass = 'text-gray-900 font-medium';
                                                } elseif ($isSystemRequest) {
                                                    // Fallback supplémentaire pour les besoins système
                                                    try {
                                                        $fallbackPrixQuery = "
                                                    SELECT COALESCE(
                                                        (SELECT am.prix_unitaire FROM achats_materiaux am 
                                                         WHERE BINARY am.designation = BINARY ? 
                                                         AND am.prix_unitaire > 0 
                                                         ORDER BY am.date_achat DESC LIMIT 1),
                                                        (SELECT p.unit_price FROM products p 
                                                         WHERE BINARY p.product_name = BINARY ? 
                                                         AND p.unit_price > 0 LIMIT 1),
                                                        0
                                                    ) as fallback_prix";
                                                        $fallbackStmt = $pdo->prepare($fallbackPrixQuery);
                                                        $fallbackStmt->execute([$material['designation'], $material['designation']]);
                                                        $fallbackResult = $fallbackStmt->fetch(PDO::FETCH_ASSOC);

                                                        if ($fallbackResult && $fallbackResult['fallback_prix'] > 0) {
                                                            $prixUnitaire = floatval($fallbackResult['fallback_prix']);
                                                            $prixDisplay = number_format($prixUnitaire, 0, ',', ' ') . ' <span class="text-xs text-blue-600">(Récupéré)</span>';
                                                            $prixClass = 'text-blue-700';
                                                        }
                                                    } catch (Exception $e) {
                                                        error_log("Erreur fallback prix: " . $e->getMessage());
                                                    }
                                                }

                                                // ===============================================
                                                // TRAITEMENT DES FOURNISSEURS AVEC FALLBACKS
                                                // ===============================================

                                                $fournisseur = trim($material['fournisseur'] ?? '');
                                                $fournisseurDisplay = 'Non spécifié';
                                                $fournisseurClass = 'text-gray-500 italic';

                                                if (!empty($fournisseur) && $fournisseur !== 'Non spécifié') {
                                                    $fournisseurDisplay = htmlspecialchars($fournisseur);
                                                    $fournisseurClass = 'text-gray-900';
                                                } elseif ($isSystemRequest) {
                                                    // Fallback pour les besoins système
                                                    try {
                                                        $fallbackFournQuery = "
                                                    SELECT COALESCE(
                                                        (SELECT am.fournisseur FROM achats_materiaux am 
                                                         WHERE BINARY am.designation = BINARY ? 
                                                         AND am.fournisseur IS NOT NULL 
                                                         AND am.fournisseur != '' 
                                                         ORDER BY am.date_achat DESC LIMIT 1),
                                                        'Non spécifié'
                                                    ) as fallback_fournisseur";
                                                        $fallbackStmt = $pdo->prepare($fallbackFournQuery);
                                                        $fallbackStmt->execute([$material['designation']]);
                                                        $fallbackResult = $fallbackStmt->fetch(PDO::FETCH_ASSOC);

                                                        if (
                                                            $fallbackResult && !empty($fallbackResult['fallback_fournisseur']) &&
                                                            $fallbackResult['fallback_fournisseur'] !== 'Non spécifié'
                                                        ) {
                                                            $fournisseurDisplay = htmlspecialchars($fallbackResult['fallback_fournisseur']) .
                                                                ' <span class="text-xs text-green-600">(Récent)</span>';
                                                            $fournisseurClass = 'text-green-700';
                                                        }
                                                    } catch (Exception $e) {
                                                        error_log("Erreur fallback fournisseur: " . $e->getMessage());
                                                    }
                                                }

                                                // ===============================================
                                                // DÉTERMINATION DU STATUT ET STYLE
                                                // ===============================================

                                                $isPartial = (int) ($material['is_partial'] ?? 0) === 1 || $material['status'] === 'en_cours';
                                                $quantityRemaining = floatval($material['quantity_remaining'] ?? 0);

                                                // Classes CSS de base
                                                $rowClass = 'material-row hover:bg-gray-50 transition-colors duration-150';
                                                if ($isSystemRequest) {
                                                    $rowClass .= ' border-l-4 border-blue-400';
                                                }

                                                // Badge de statut
                                                $statusBadge = '';
                                                $quantityInfo = '';

                                                if ($isPartial && $quantityRemaining > 0) {
                                                    $rowClass .= ' bg-yellow-50';
                                                    $statusBadge = '<span class="status-badge status-partial">Partielle</span>';

                                                    $initialQty = floatval($material['original_quantity'] ?? $material['quantity']);
                                                    $orderedQty = $initialQty - floatval($material['qt_restante'] ?? 0);
                                                    $progress = $initialQty > 0 ? round(($orderedQty / $initialQty) * 100) : 0;

                                                    $quantityInfo = sprintf(
                                                        '
                                                        <div class="space-y-1">
                                                            <span class="text-sm text-gray-900 font-medium">%s / %s %s</span>
                                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                                <div class="bg-yellow-500 h-2 rounded-full transition-all duration-300" style="width: %d%%"></div>
                                                            </div>
                                                            <span class="text-xs text-yellow-600 font-medium">Reste: %s</span>
                                                        </div>',
                                                        number_format($orderedQty, 2, ',', ' '),
                                                        number_format($initialQty, 2, ',', ' '),
                                                        htmlspecialchars($material['unit'] ?? ''),
                                                        $progress,
                                                        number_format(floatval($material['qt_restante'] ?? 0), 2, ',', ' ')
                                                    );
                                                } else {
                                                    $rowClass .= ' bg-green-50';

                                                    if ($material['status'] === 'valide_en_cours') {
                                                        $statusBadge = '<span class="status-badge status-completed">En cours de validation</span>';
                                                    } else {
                                                        $statusBadge = '<span class="status-badge status-ordered">Commandé</span>';
                                                    }

                                                    $displayQuantity = floatval($material['quantity'] ?? 0);
                                                    $quantityInfo = sprintf(
                                                        '<span class="text-sm text-gray-900 font-medium">%s %s</span>',
                                                        number_format($displayQuantity, 2, ',', ' '),
                                                        htmlspecialchars($material['unit'] ?? '')
                                                    );
                                                }

                                                // Indicateur de quantité restante
                                                $remainingClass = $quantityRemaining > 0 ? 'text-amber-600 font-medium' : 'text-green-600 font-medium';
                                                $remainingIcon = $quantityRemaining > 0 ?
                                                    '<span class="material-icons text-sm mr-1">schedule</span>' :
                                                    '<span class="material-icons text-sm mr-1">check_circle</span>';

                                                // Indicateur de source
                                                $sourceIndicator = $isSystemRequest ?
                                                    '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                       <span class="material-icons text-xs mr-1">computer</span>Système
                                                     </span>' : '';

                                                // ===============================================
                                                // AFFICHAGE DE LA LIGNE DU TABLEAU
                                                // ===============================================
                                        ?>
                                                <tr class="<?= $rowClass ?>">
                                                    <td class="px-4 py-3">
                                                        <input type="checkbox" class="material-checkbox ordered-material-checkbox w-4 h-4 text-blue-600 rounded"
                                                            data-id="<?= htmlspecialchars($material['expression_row_id'] ?? '0') ?>"
                                                            data-expression="<?= htmlspecialchars($material['expression_id'] ?? '') ?>"
                                                            data-designation="<?= htmlspecialchars($material['designation'] ?? '') ?>"
                                                            data-project="<?= htmlspecialchars($material['code_projet'] ?? '') ?> - <?= htmlspecialchars($material['nom_client'] ?? '') ?>"
                                                            data-source="<?= htmlspecialchars($sourceTable) ?>">
                                                    </td>

                                                    <td class="px-4 py-3">
                                                        <span class="font-medium text-gray-900">
                                                            <?= htmlspecialchars($material['code_projet'] ?? 'N/A') ?>
                                                        </span>
                                                    </td>

                                                    <td class="px-4 py-3">
                                                        <span class="text-gray-700">
                                                            <?= htmlspecialchars($material['nom_client'] ?? 'N/A') ?>
                                                        </span>
                                                    </td>

                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center">
                                                            <span class="font-medium text-gray-900">
                                                                <?= htmlspecialchars($material['designation'] ?? 'N/A') ?>
                                                            </span>
                                                            <?= $sourceIndicator ?>
                                                        </div>
                                                    </td>

                                                    <td class="px-4 py-3"><?= $quantityInfo ?></td>

                                                    <td class="px-4 py-3 text-sm text-gray-700">
                                                        <?= htmlspecialchars($material['unit'] ?? 'N/A') ?>
                                                    </td>

                                                    <td class="px-4 py-3"><?= $statusBadge ?></td>

                                                    <td class="px-4 py-3">
                                                        <span class="<?= $prixClass ?>">
                                                            <?= $prixDisplay ?> FCFA
                                                        </span>
                                                    </td>

                                                    <td class="px-4 py-3">
                                                        <span class="<?= $fournisseurClass ?>">
                                                            <?= $fournisseurDisplay ?>
                                                        </span>
                                                    </td>

                                                    <td class="px-4 py-3 text-sm text-gray-600">
                                                        <?= isset($material['date_achat']) ? date('d/m/Y', strtotime($material['date_achat'])) : 'N/A' ?>
                                                    </td>

                                                    <td class="px-4 py-3 <?= $remainingClass ?>">
                                                        <div class="flex items-center">
                                                            <?= $remainingIcon ?>
                                                            <span><?= number_format($quantityRemaining, 2, ',', ' ') ?></span>
                                                            <?php if ($quantityRemaining > 0): ?>
                                                                <span class="text-xs text-gray-500 ml-1"><?= htmlspecialchars($material['unit'] ?? '') ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>

                                                    <td class="px-4 py-3 text-center">
                                                        <div class="flex items-center justify-center space-x-1">
                                                            <?php
                                                            // Bouton de téléchargement du bon de commande
                                                            if (isset($material['bon_commande_id']) && $material['bon_commande_id'] && isset($material['bon_commande_path'])) {
                                                                $pdfPath = $material['bon_commande_path'];
                                                                if (strpos($pdfPath, 'purchase_orders/') !== 0 && strpos($pdfPath, 'gestion-bon-commande/') !== 0) {
                                                                    $pdfPath = 'purchase_orders/' . $pdfPath;
                                                                }
                                                                $pdfUrl = 'gestion-bon-commande/' . $pdfPath;
                                                                if ($material['signature_finance'] && $material['user_finance_id']) {
                                                            ?>
                                                                    <a href="gestion-bon-commande/api/download_validated_bon_commande.php?id=<?= htmlspecialchars($material['bon_commande_id']) ?>"
                                                                        class="btn-action text-blue-600 hover:text-blue-800 mr-2"
                                                                        title="Télécharger bon de commande validé" target="_blank">
                                                                        <span class="material-icons">verified</span>
                                                                    </a>
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <a href="<?= htmlspecialchars($pdfUrl) ?>"
                                                                        class="btn-action text-blue-600 hover:text-blue-800 mr-2"
                                                                        title="Télécharger bon de commande" target="_blank">
                                                                        <span class="material-icons">cloud_download</span>
                                                                    </a>
                                                                <?php
                                                                }
                                                            }

                                                            // Bouton Modifier (Super Admin uniquement)
                                                            if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'):
                                                                ?>
                                                                <button onclick="openEditOrderModal('<?= htmlspecialchars($achatId) ?>', '<?= htmlspecialchars($material['expression_id'] ?? '') ?>', '<?= addslashes($material['designation'] ?? 'Produit') ?>', '<?= htmlspecialchars($sourceTable) ?>', '<?= htmlspecialchars($material['quantity'] ?? '0') ?>', '<?= htmlspecialchars($material['unit'] ?? '') ?>', '<?= htmlspecialchars($prixUnitaire) ?>', '<?= htmlspecialchars($fournisseur) ?>')"
                                                                    class="btn-action text-orange-600 hover:text-orange-800 p-1 rounded hover:bg-orange-100"
                                                                    title="Modifier la commande">
                                                                    <span class="material-icons text-sm">edit</span>
                                                                </button>
                                                            <?php
                                                            endif;
                                                            ?>

                                                            <button onclick="cancelSingleOrder('<?= htmlspecialchars($material['expression_row_id'] ?? '0') ?>', '<?= htmlspecialchars($material['expression_id'] ?? '') ?>', '<?= addslashes($material['designation'] ?? 'Produit') ?>', '<?= htmlspecialchars($sourceTable) ?>')"
                                                                class="btn-action text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-100"
                                                                title="Annuler la commande">
                                                                <span class="material-icons text-sm">cancel</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php

                                            } catch (Exception $materialException) {
                                                error_log("Erreur traitement matériau ID " . ($material['expression_row_id'] ?? 'inconnu') . ": " . $materialException->getMessage());
                                            ?>
                                                <tr class="bg-red-50 border-l-4 border-red-400">
                                                    <td colspan="12" class="px-4 py-3 text-center text-red-700">
                                                        <div class="flex items-center justify-center">
                                                            <span class="material-icons text-sm mr-2">error</span>
                                                            Erreur d'affichage pour : <?= htmlspecialchars($material['designation'] ?? 'Produit inconnu') ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                    <?php
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    error_log("Erreur critique dans l'affichage des matériaux commandés: " . $e->getMessage());
                                    ?>
                                    <tr>
                                        <td colspan="12" class="text-center text-red-600 py-8">
                                            <div class="flex flex-col items-center">
                                                <span class="material-icons text-4xl text-red-300 mb-2">error_outline</span>
                                                <p class="text-lg font-medium">Erreur lors du chargement des matériaux commandés</p>
                                                <p class="text-sm text-gray-500 mt-1">Veuillez actualiser la page ou contacter l'administrateur</p>
                                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
                                                    <details class="mt-2 text-xs text-gray-400">
                                                        <summary class="cursor-pointer">Détails de l'erreur (Admin)</summary>
                                                        <code class="block mt-1 p-2 bg-gray-100 rounded">
                                                            <?= htmlspecialchars($e->getMessage()) ?>
                                                        </code>
                                                    </details>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Contenu de l'onglet "Achats Récents" -->
            <div id="content-recents" class="tab-content bg-white rounded-lg shadow-sm p-6 mb-6 hidden">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Matériaux Reçus en Stock</h2>

                <?php if (!empty($recents)): ?>
                    <div class="overflow-x-auto">
                        <table id="recentPurchasesTable" class="display responsive nowrap w-full">
                            <thead>
                                <tr>
                                    <th>Projet</th>
                                    <th>Client</th>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Unité</th>
                                    <th>Statut</th>
                                    <th>Prix Unit.</th>
                                    <th>Fournisseur</th>
                                    <th>Date Réception</th>
                                    <th>Actions</th>
                                    <!-- Colonnes cachées pour stocker des données additionnelles -->
                                    <th class="hidden">Expression ID</th>
                                    <th class="hidden">Last Order ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    foreach ($recents as $material) {
                                        try {
                                            $designation = htmlspecialchars($material['designation'] ?? 'N/A');
                                            $projet = htmlspecialchars($material['code_projet'] ?? 'N/A');
                                            $client = htmlspecialchars($material['nom_client'] ?? 'N/A');
                                            $lastOrderId = htmlspecialchars($material['last_order_id'] ?? '0');
                                            $expressionId = htmlspecialchars($material['idExpression'] ?? '');
                                            $sourceTable = $material['source_table'] ?? 'expression_dym';

                                            // Déterminer la quantité à afficher
                                            $quantite = htmlspecialchars($material['qt_acheter'] ?? $material['quantity'] ?? 0);

                                            $unite = htmlspecialchars($material['unit'] ?? 'N/A');
                                            $fournisseur = htmlspecialchars(string: $material['fournisseur'] ?? 'N/A');
                                            $dateReception = isset($material['date_reception'])
                                                ? date('d/m/Y H:i', strtotime($material['date_reception']))
                                                : (isset($material['updated_at']) ? date('d/m/Y H:i', strtotime($material['updated_at'])) : 'N/A');
                                            $prixUnitaire = $material['prix_unitaire'] ?? 0;

                                            // Déterminer si c'est une commande avec historique
                                            $hasHistory = ($material['orders_count'] ?? 0) > 1;
                                            $statusClass = $hasHistory ? "status-badge status-partial" : "status-badge status-received";
                                            $statusText = $hasHistory ? "Multiple" : "Reçu";

                                            // Indicateur pour les matériaux provenant de la table besoins
                                            $sourceIndicator = '';
                                            if ($sourceTable === 'besoins') {
                                                $sourceIndicator = '<span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Système</span>';
                                            }
                                ?>
                                            <tr class="material-row received">
                                                <td><?= $projet ?></td>
                                                <td><?= $client ?></td>
                                                <td><?= $designation ?><?= $sourceIndicator ?></td>
                                                <td><?= number_format(floatval($quantite), 2, ',', ' ') ?></td>
                                                <td><?= $unite ?></td>
                                                <td>
                                                    <span class="<?= $statusClass ?>"><?= $statusText ?></span>
                                                </td>
                                                <td><?= number_format((float) $prixUnitaire, 0, ',', ' ') ?> FCFA</td>
                                                <td><?= $fournisseur ?></td>
                                                <td><?= $dateReception ?></td>
                                                <td>
                                                    <!-- Les boutons seront gérés par le rendu DataTables -->
                                                </td>
                                                <!-- Colonnes cachées avec données pour le JavaScript -->
                                                <td class="hidden"><?= $expressionId ?></td>
                                                <td class="hidden"><?= $lastOrderId ?></td>
                                            </tr>
                                        <?php
                                        } catch (Exception $materialException) {
                                            error_log("Erreur de traitement du matériau : " . $materialException->getMessage());
                                        ?>
                                            <tr class="bg-red-100">
                                                <td colspan="10">Erreur de chargement du matériau</td>
                                            </tr>
                                    <?php
                                        }
                                    }
                                } catch (Exception $globalException) {
                                    error_log("Erreur globale lors du chargement des matériaux : " . $globalException->getMessage());
                                    ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-red-600">
                                            Impossible de charger les matériaux. Une erreur s'est produite.
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-gray-500">Aucun matériau réceptionné en stock pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contenu de l'onglet Commandes Annulées -->
            <div id="content-canceled" class="tab-content bg-white rounded-lg shadow-sm p-6 mb-6 hidden">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Commandes Annulées</h2>

                <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <span class="material-icons text-yellow-500">info</span>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Information importante</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>Cette section affiche les commandes qui ont été automatiquement annulées lorsqu'un
                                    projet a été marqué comme terminé par le service Bureau d'Études.</p>
                                <p class="mt-2">Lorsqu'un projet est marqué comme terminé, toutes les commandes en
                                    attente, partielles ou normales associées à ce projet sont automatiquement annulées
                                    pour éviter des dépenses inutiles.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiques des commandes annulées -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Total des annulations</p>
                                <h3 class="text-2xl font-bold mt-1" id="total-canceled-count">0</h3>
                            </div>
                            <div class="rounded-full bg-red-100 p-3">
                                <span class="material-icons text-red-600">cancel</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Commandes annulées automatiquement</p>
                    </div>

                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Projets concernés</p>
                                <h3 class="text-2xl font-bold mt-1" id="projects-canceled-count">0</h3>
                            </div>
                            <div class="rounded-full bg-orange-100 p-3">
                                <span class="material-icons text-orange-600">folder_off</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Projets avec commandes annulées</p>
                    </div>

                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Dernière annulation</p>
                                <h3 class="text-lg font-bold mt-1" id="last-canceled-date">-</h3>
                            </div>
                            <div class="rounded-full bg-purple-100 p-3">
                                <span class="material-icons text-purple-600">event_busy</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Date de la dernière annulation</p>
                    </div>
                </div>

                <!-- Tableau des commandes annulées -->
                <div class="overflow-x-auto">
                    <table id="canceledOrdersTable" class="display responsive nowrap w-full">
                        <thead>
                            <tr>
                                <th>Projet</th>
                                <th>Client</th>
                                <th>Produit</th>
                                <th>Statut original</th>
                                <th>Quantité</th>
                                <th>Fournisseur</th>
                                <th>Date d'annulation</th>
                                <th>Raison</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Les données seront chargées dynamiquement -->
                            <tr>
                                <td colspan="9" class="text-center py-4">Chargement des données...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Contenu de l'onglet "Retours Fournisseurs" -->
            <div id="content-returns" class="tab-content bg-white rounded-lg shadow-sm p-6 mb-6 hidden">
                <h2 class="text-lg font-semibold mb-4">Retours Fournisseurs</h2>

                <!-- Statistiques sur les retours -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Total des retours</p>
                                <h3 class="text-2xl font-bold mt-1">
                                    <?php echo formatNumber($retourStats['total_retours']); ?>
                                </h3>
                            </div>
                            <div class="rounded-full bg-pink-100 p-3">
                                <span class="material-icons text-pink-600">assignment_return</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Nombre de retours fournisseurs</p>
                    </div>

                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Valeur des retours</p>
                                <h3 class="text-2xl font-bold mt-1">
                                    <?php echo formatNumber($retourStats['valeur_retours']); ?> FCFA
                                </h3>
                            </div>
                            <div class="rounded-full bg-red-100 p-3">
                                <span class="material-icons text-red-600">payments</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Montant total retourné</p>
                    </div>

                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Retours en attente</p>
                                <h3 class="text-2xl font-bold mt-1">
                                    <?php echo formatNumber($retourStats['retours_attente']); ?>
                                </h3>
                            </div>
                            <div class="rounded-full bg-yellow-100 p-3">
                                <span class="material-icons text-yellow-600">hourglass_empty</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">À confirmer par les fournisseurs</p>
                    </div>
                </div>

                <!-- Tableau des retours -->
                <div class="overflow-x-auto">
                    <table id="supplierReturnsTable" class="display responsive nowrap w-full">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Fournisseur</th>
                                <th>Quantité</th>
                                <th>Motif</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Les données seront chargées dynamiquement -->
                            <tr>
                                <td colspan="7" class="text-center py-4">Chargement des données...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <?php include_once '../components/footer.html'; ?>
    </div>

    <!-- ============== start modal ================ -->
    <!-- Modal pour l'achat -->
    <div id="purchase-modal" class="modal">
        <div class="modal-content">
            <h2 class="text-xl font-semibold mb-4">Enregistrer un achat</h2>
            <form id="purchase-form" method="POST" action="api/fournisseurs/process_purchase.php">
                <input type="hidden" id="expression_id" name="expression_id">
                <input type="hidden" id="designation" name="designation">

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="quantite">
                        Quantité à acheter
                    </label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="quantite" name="quantite" type="number" step="0.01" readonly>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="unite">
                        Unité
                    </label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="unite" name="unite" type="text" readonly>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="prix">
                        Prix unitaire (FCFA)
                    </label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="prix" name="prix" type="number" step="0.01" value="1000" required>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="fournisseur">
                        Fournisseur
                    </label>
                    <div class="relative">
                        <input
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            id="fournisseur" name="fournisseur" type="text" required
                            placeholder="Saisissez ou sélectionnez un fournisseur">
                        <div id="fournisseurs-suggestions"
                            class="absolute w-full bg-white mt-1 shadow-lg rounded-md z-10 max-h-60 overflow-y-auto">
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-blue-600">
                        <a href="../fournisseurs/fournisseurs.php" target="_blank" class="flex items-center">
                            <span class="material-icons text-sm mr-1">add_circle</span>
                            Gérer les fournisseurs
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <button
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                        name="submit_purchase" type="submit">
                        Commander
                    </button>
                    <button
                        class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                        type="button" onclick="closePurchaseModal()">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour les achats en gros -->
    <div id="bulk-purchase-modal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <h2 class="text-xl font-semibold mb-4">Achat groupé de matériaux</h2>
            <form id="bulk-purchase-form" method="POST" action="">
                <input type="hidden" name="bulk_purchase" value="1">

                <!-- Container for selected materials info -->
                <div id="selected-materials-container" class="mb-6">
                    <!-- Will be populated dynamically -->
                </div>

                <!-- Supplier information -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="fournisseur-bulk">
                        Fournisseur <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            id="fournisseur-bulk" name="fournisseur" type="text" required
                            placeholder="Saisissez ou sélectionnez un fournisseur">
                        <div id="fournisseurs-suggestions-bulk"
                            class="absolute w-full bg-white mt-1 shadow-lg rounded-md z-10 max-h-60 overflow-y-auto">
                            <!-- Suggestions will be populated here -->
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-blue-600">
                        <a href="../fournisseurs/fournisseurs.php" target="_blank" class="flex items-center">
                            <span class="material-icons text-sm mr-1">add_circle</span>
                            Gérer les fournisseurs
                        </a>
                    </div>
                </div>

                <!-- Mode de paiement -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="payment-method-bulk">
                        Mode de paiement <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <select id="payment-method-bulk" name="payment_method" required
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">Sélectionnez un mode de paiement</option>
                            <!-- Les options seront chargées dynamiquement -->
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                            <span class="material-icons text-gray-400">payment</span>
                        </div>
                    </div>
                    <div class="mt-1 text-xs text-gray-600" id="payment-method-description">
                        <!-- Description du mode de paiement sélectionné -->
                    </div>
                </div>

                <!-- Upload Pro-forma (Optionnel) -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="proforma-upload">
                        Pro-forma (Optionnel)
                        <span class="text-xs text-gray-500 font-normal ml-2">PDF, DOC, XLS, JPG, PNG (Max 10MB)</span>
                    </label>
                    <div class="relative">
                        <input type="file"
                            id="proforma-upload"
                            name="proforma_file"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                            <span class="material-icons text-gray-400">attach_file</span>
                        </div>
                    </div>

                    <!-- Informations sur le fichier sélectionné -->
                    <div id="proforma-file-info" class="mt-2 text-xs text-gray-600 hidden">
                        <div class="flex items-center">
                            <span class="material-icons text-sm mr-1 text-green-600">check_circle</span>
                            <span id="proforma-file-name"></span>
                            <span id="proforma-file-size" class="ml-2 text-gray-500"></span>
                            <button type="button" id="proforma-remove-file" class="ml-2 text-red-600 hover:text-red-800">
                                <span class="material-icons text-sm">close</span>
                            </button>
                        </div>
                    </div>

                    <!-- Message d'aide -->
                    <div class="mt-2 text-xs text-blue-600">
                        <div class="flex items-center">
                            <span class="material-icons text-sm mr-1">info</span>
                            <span>Le pro-forma sera automatiquement associé à toutes les commandes de ce groupe</span>
                        </div>
                    </div>

                    <!-- Zone de progression d'upload (cachée par défaut) -->
                    <div id="proforma-upload-progress" class="mt-2 hidden">
                        <div class="bg-gray-200 rounded-full h-2">
                            <div id="proforma-progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <div class="text-xs text-gray-600 mt-1">
                            <span id="proforma-progress-text">Upload en cours...</span>
                        </div>
                    </div>
                </div>

                <!-- Price type selection -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="price-type">
                        Type de prix
                    </label>
                    <select id="price-type" name="price_type"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="individual">Prix individuels (définir pour chaque matériau)</option>
                        <option value="common">Prix commun (même prix pour tous)</option>
                    </select>
                </div>

                <!-- Common price container (hidden by default) -->
                <div id="common-price-container" class="mb-4 hidden">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="common-price">
                        Prix unitaire commun (FCFA) <span class="text-red-500">*</span>
                    </label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="common-price" name="common_price" type="number" step="0.01" min="0">
                </div>

                <!-- Individual prices container -->
                <div id="individual-prices-container" class="mb-4">
                    <h3 class="text-lg font-medium mb-2">Prix individuels</h3>
                    <div class="overflow-x-auto max-h-60" style="max-height: 19rem; height: 100%w;">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Produit
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Quantité <span class="text-blue-500 text-sm">(modifiable)</span>
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Unité
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Prix unitaire (FCFA) <span class="text-red-500">*</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="individual-prices-tbody" class="bg-white divide-y divide-gray-200">
                                <!-- Will be populated dynamically with JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="flex items-center justify-between mt-6">
                    <button type="button"
                        class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                        onclick="closeBulkPurchaseModal()">
                        Annuler
                    </button>
                    <button type="submit" id="confirm-bulk-purchase"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Passer la commande
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour la substitution de produit -->
    <div id="substitution-modal" class="modal">
        <div class="modal-content">
            <h2 class="text-xl font-semibold mb-4">Substituer un produit</h2>
            <form id="substitution-form" method="POST" action="api/substitution/process_substitution.php">
                <input type="hidden" id="substitute-material-id" name="material_id">
                <input type="hidden" id="substitute-expression-id" name="expression_id">
                <input type="hidden" id="substitute-source-table" name="source_table" value="expression_dym">

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="original-product">
                        Produit original
                    </label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="original-product" type="text" readonly>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="substitute-product">
                        Nouveau produit <span class="text-red-500">*</span>
                    </label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="substitute-product" name="substitute_product" type="text" required
                        placeholder="Saisissez le produit de substitution">
                    <div id="product-suggestions"
                        class="absolute w-full bg-white mt-1 shadow-lg rounded-md z-10 max-h-60 overflow-y-auto hidden">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="substitution-reason">
                        Raison de la substitution <span class="text-red-500">*</span>
                    </label>
                    <select
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="substitution-reason" name="substitution_reason" required>
                        <option value="">Sélectionnez une raison</option>
                        <option value="indisponibilite">Produit indisponible</option>
                        <option value="meilleur_prix">Meilleur prix</option>
                        <option value="qualite_superieure">Qualité supérieure</option>
                        <option value="autre">Autre raison</option>
                    </select>
                </div>

                <div class="mb-4" id="other-reason-container" style="display: none;">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="other-reason">
                        Précisez la raison
                    </label>
                    <textarea
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="other-reason" name="other_reason" rows="3"></textarea>
                </div>

                <div class="flex items-center justify-between">
                    <button
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                        type="submit">
                        Confirmer la substitution
                    </button>
                    <button
                        class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                        type="button" onclick="closeSubstitutionModal()">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour les détails d'une commande reçue -->
    <div id="order-details-modal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Détails de la commande</h2>
                <button onclick="closeOrderDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <span class="material-icons">close</span>
                </button>
            </div>

            <!-- Contenu dynamique -->
            <div id="order-details-content">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>

            <!-- Pied de modal -->
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeOrderDetailsModal()"
                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <!-- Modal pour modifier une commande -->
    <div id="edit-order-modal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Modifier la commande</h2>
                <button onclick="closeEditOrderModal()" class="text-gray-400 hover:text-gray-600">
                    <span class="material-icons">close</span>
                </button>
            </div>

            <form id="edit-order-form" method="POST" action="api/orders/edit_order.php">
                <input type="hidden" id="edit-order-id" name="order_id">
                <input type="hidden" id="edit-expression-id" name="expression_id">
                <input type="hidden" id="edit-source-table" name="source_table">

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit-designation">
                        Désignation
                    </label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100"
                        id="edit-designation" name="designation" type="text" readonly>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit-quantity">
                            Quantité <span class="text-red-500">*</span>
                        </label>
                        <input
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            id="edit-quantity" name="quantity" type="number" step="0.01" min="0.01" required>
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit-unit">
                            Unité
                        </label>
                        <input
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100"
                            id="edit-unit" name="unit" type="text" readonly>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit-price">
                        Prix unitaire (FCFA) <span class="text-red-500">*</span>
                    </label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="edit-price" name="prix_unitaire" type="number" step="0.01" min="0" required>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit-supplier">
                        Fournisseur <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            id="edit-supplier" name="fournisseur" type="text" required
                            placeholder="Saisissez ou sélectionnez un fournisseur">
                        <div id="edit-fournisseurs-suggestions"
                            class="absolute w-full bg-white mt-1 shadow-lg rounded-md z-10 max-h-60 overflow-y-auto hidden">
                        </div>
                    </div>
                </div>

                <!-- Mode de paiement -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit-payment-method">
                        Mode de paiement <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <select id="edit-payment-method" name="payment_method" required
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">Sélectionnez un mode de paiement</option>
                            <!-- Les options seront chargées dynamiquement -->
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                            <span class="material-icons text-gray-400">payment</span>
                        </div>
                    </div>
                    <div class="mt-1 text-xs text-gray-600" id="edit-payment-method-description">
                        <!-- Description du mode de paiement sélectionné -->
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit-notes">
                        Notes de modification
                    </label>
                    <textarea
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="edit-notes" name="notes" rows="3" placeholder="Motif de la modification..."></textarea>
                </div>

                <div class="flex items-center justify-between">
                    <button
                        class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                        type="submit">
                        <span class="material-icons align-middle mr-1">save</span>
                        Sauvegarder les modifications
                    </button>
                    <button
                        class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                        type="button" onclick="closeEditOrderModal()">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- ============== end modal ================ -->

    <!-- Scripts jQuery et DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js">
    </script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js">
    </script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <!-- Ajouter après les autres scripts DataTables -->
    <script src="assets/js/datatable-date-fr.js"></script>
    <script src="assets/js/notification-counters.js"></script>
    <!-- Scripts pour les filtres avancés -->
    <script src="assets/js/advanced-filters.js"></script>
    <script src="assets/js/ordered-materials-filters.js"></script>
    <script src="assets/js/proforma_upload.js"></script>

    <script>
        /**
         * Module de gestion des achats de matériaux
         * Architecture moderne avec organisation modulaire
         * VERSION CORRIGÉE : Intégration complète des modes de paiement par ID
         */

        // Configuration globale et constantes
        const CONFIG = {
            API_URLS: {
                FOURNISSEURS: 'get_fournisseurs.php',
                CHECK_MATERIALS: 'check_new_materials.php',
                MATERIAL_INFO: 'get_material_info.php',
                CANCEL_ORDERS: 'api/orders/cancel_multiple_orders.php',
                CANCEL_PENDING: 'api/orders/cancel_pending_materials.php',
                PARTIAL_ORDERS: 'commandes-traitement/api.php',
                SUBSTITUTION: 'api/substitution/process_substitution.php',
                PRODUCT_SUGGESTIONS: 'api/substitution/get_product_suggestions.php',
                BON_COMMANDE: 'generate_bon_commande.php',
                CHECK_FOURNISSEUR: 'api/fournisseurs/check_fournisseur.php',
                PROCESS_PURCHASE: 'api/fournisseurs/process_purchase.php',
                UPDATE_ORDER_STATUS: 'api/orders/update_order_status.php',
                PAYMENT_METHODS: 'api_getPaymentMethods.php', // NOUVEAU : API pour les modes de paiement
            },
            REFRESH_INTERVALS: {
                DATETIME: 1000,
                CHECK_MATERIALS: 5 * 60 * 1000, // 5 minutes
                CHECK_VALIDATION: 5 * 60 * 1000 // 5 minutes
            },
            DATATABLES: {
                LANGUAGE_URL: "//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json",
                DOM: 'Blfrtip',
                BUTTONS: ['excel', 'print'],
                PAGE_LENGTH: 15
            }
        };

        /**
         * MODULE DE GESTION DES MODES DE PAIEMENT - VERSION CORRIGÉE
         * Assure que les IDs des modes de paiement sont correctement traités
         */
        const PaymentMethodsManager = {
            paymentMethods: [],
            isLoaded: false,

            /**
             * Initialise le gestionnaire des modes de paiement
             */
            async init() {
                try {
                    await this.loadPaymentMethods();
                    this.setupEventListeners();
                    console.log('✅ PaymentMethodsManager initialisé avec succès');
                } catch (error) {
                    console.error('❌ Erreur lors de l\'initialisation des modes de paiement:', error);
                }
            },

            /**
             * Charge les modes de paiement depuis l'API - CORRIGÉE
             */
            async loadPaymentMethods() {
                try {
                    // CORRECTION : Utiliser la nouvelle API
                    const response = await fetch(CONFIG.API_URLS.PAYMENT_METHODS);
                    const data = await response.json();

                    if (data.success) {
                        this.paymentMethods = data.methods || [];
                        this.isLoaded = true;
                        console.log(`✅ ${data.count} modes de paiement chargés`);
                    } else {
                        throw new Error(data.message || 'Erreur lors du chargement des modes de paiement');
                    }
                } catch (error) {
                    console.error('❌ Erreur chargement modes de paiement:', error);
                    // Fallback avec modes par défaut
                    this.paymentMethods = [{
                            id: 1,
                            label: 'Espèces',
                            description: 'Paiement en liquide',
                            icon: '💰'
                        },
                        {
                            id: 2,
                            label: 'Chèque',
                            description: 'Paiement par chèque bancaire',
                            icon: '🏛️'
                        },
                        {
                            id: 3,
                            label: 'Virement bancaire',
                            description: 'Virement de compte à compte',
                            icon: '🏦'
                        }
                    ];
                    this.isLoaded = true;
                }
            },

            /**
             * Peupler un sélecteur avec les modes de paiement - CORRIGÉE
             */
            populatePaymentSelect(selectId) {
                const select = document.getElementById(selectId);
                if (!select) {
                    console.warn('⚠️ Sélecteur non trouvé:', selectId);
                    return;
                }

                // Garder l'option par défaut
                const defaultOption = select.querySelector('option[value=""]');
                select.innerHTML = '';
                if (defaultOption) {
                    select.appendChild(defaultOption);
                }

                // Ajouter les modes de paiement actifs
                this.paymentMethods
                    .filter(method => method.is_active !== false)
                    .forEach(method => {
                        const option = document.createElement('option');

                        // CORRECTION PRINCIPALE : Utiliser l'ID comme valeur
                        option.value = method.id;
                        option.textContent = method.label;

                        // Stocker les données supplémentaires
                        if (method.description) option.dataset.description = method.description;
                        if (method.icon) option.dataset.icon = method.icon;

                        select.appendChild(option);
                    });

                console.log(`✅ Sélecteur ${selectId} peuplé avec ${this.paymentMethods.length} modes de paiement`);
            },
            
            /**
             * Validation d'un mode de paiement - CORRIGÉE
             */
            validatePaymentMethod(paymentMethodId) {
                if (!paymentMethodId || paymentMethodId === '') {
                    Swal.fire({
                        title: 'Mode de paiement requis',
                        text: 'Veuillez sélectionner un mode de paiement.',
                        icon: 'warning',
                        confirmButtonColor: '#4F46E5'
                    });
                    return false;
                }

                // Vérifier que l'ID est un nombre valide
                const methodId = parseInt(paymentMethodId);
                if (isNaN(methodId) || methodId <= 0) {
                    Swal.fire({
                        title: 'Mode de paiement invalide',
                        text: 'L\'ID du mode de paiement sélectionné est invalide.',
                        icon: 'error',
                        confirmButtonColor: '#4F46E5'
                    });
                    return false;
                }

                // Vérifier que le mode existe dans la liste chargée
                const method = this.paymentMethods.find(m => parseInt(m.id) === methodId);
                if (!method) {
                    Swal.fire({
                        title: 'Mode de paiement non trouvé',
                        text: 'Le mode de paiement sélectionné n\'existe pas ou n\'est plus disponible.',
                        icon: 'error',
                        confirmButtonColor: '#4F46E5'
                    });
                    return false;
                }

                return true;
            },

            /**
             * Mise à jour de la description d'un mode de paiement
             */
            updatePaymentDescription(descriptionElementId, paymentMethodId) {
                const descriptionElement = document.getElementById(descriptionElementId);
                if (!descriptionElement) return;

                const methodId = parseInt(paymentMethodId);
                const method = this.paymentMethods.find(m => parseInt(m.id) === methodId);

                if (method && method.description) {
                    const icon = method.icon ? method.icon + ' ' : '';
                    descriptionElement.innerHTML = `
                        <div class="text-sm text-gray-600 mt-2 p-2 bg-blue-50 rounded border-l-4 border-blue-400">
                            ${icon}${method.description}
                        </div>
                    `;
                } else {
                    descriptionElement.innerHTML = '';
                }
            },

            /**
             * Configuration des gestionnaires d'événements - CORRIGÉE
             */
            setupEventListeners() {
                // Gestionnaire pour la modal d'achat groupé
                const bulkPaymentSelect = document.getElementById('payment-method-bulk');
                if (bulkPaymentSelect) {
                    bulkPaymentSelect.addEventListener('change', (e) => {
                        this.updatePaymentDescription('payment-method-description', e.target.value);

                        // Validation en temps réel
                        if (e.target.value) {
                            this.validatePaymentMethod(e.target.value);
                        }
                    });
                }

                // Gestionnaire pour la modal d'achat individuel
                const individualPaymentSelect = document.getElementById('payment-method-individual');
                if (individualPaymentSelect) {
                    individualPaymentSelect.addEventListener('change', (e) => {
                        this.updatePaymentDescription('payment-method-description-individual', e.target.value);

                        if (e.target.value) {
                            this.validatePaymentMethod(e.target.value);
                        }
                    });
                }

                // Gestionnaire pour la modal d'édition
                const editPaymentSelect = document.getElementById('edit-payment-method');
                if (editPaymentSelect) {
                    editPaymentSelect.addEventListener('change', (e) => {
                        this.updatePaymentDescription('edit-payment-method-description', e.target.value);
                    });
                }

                console.log('✅ Gestionnaires d\'événements des modes de paiement configurés');
            },

            /**
             * Récupération des informations d'un mode de paiement
             */
            getPaymentMethodInfo(paymentMethodId) {
                const methodId = parseInt(paymentMethodId);
                return this.paymentMethods.find(m => parseInt(m.id) === methodId) || null;
            },

            /**
             * Réinitialisation d'un sélecteur de mode de paiement
             */
            resetPaymentSelect(selectId) {
                const select = document.getElementById(selectId);
                if (select) {
                    select.value = '';

                    // Effacer la description si elle existe
                    const descriptionId = selectId.replace('payment-method', 'payment-method-description');
                    const descriptionElement = document.getElementById(descriptionId);
                    if (descriptionElement) {
                        descriptionElement.innerHTML = '';
                    }
                }
            }
        };

        /**
         * Gestionnaire de modification des commandes
         */
        const EditOrderManager = {
            /**
             * Ouvre la modal de modification d'une commande
             */
            async openModal(orderId, expressionId, designation, sourceTable, quantity, unit, price, supplier) {
                const modal = document.getElementById('edit-order-modal');
                if (!modal) {
                    console.error('Modal de modification introuvable');
                    return;
                }

                // Remplir les champs du formulaire
                document.getElementById('edit-order-id').value = orderId;
                document.getElementById('edit-expression-id').value = expressionId;
                document.getElementById('edit-source-table').value = sourceTable;
                document.getElementById('edit-designation').value = designation;
                document.getElementById('edit-quantity').value = quantity;
                document.getElementById('edit-unit').value = unit;
                document.getElementById('edit-price').value = price;
                document.getElementById('edit-supplier').value = supplier;

                // Réinitialiser les notes
                document.getElementById('edit-notes').value = '';

                // CORRECTION : Charger les modes de paiement avec le nouveau gestionnaire
                await this.loadPaymentMethods();

                // Configurer l'autocomplétion des fournisseurs
                this.setupSupplierAutocomplete();

                // Afficher la modal
                modal.style.display = 'flex';
            },

            /**
             * Ferme la modal de modification
             */
            closeModal() {
                const modal = document.getElementById('edit-order-modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            },

            /**
             * Charge les modes de paiement - VERSION CORRIGÉE
             */
            async loadPaymentMethods() {
                try {
                    // CORRECTION : Utiliser le PaymentMethodsManager
                    if (!PaymentMethodsManager.isLoaded) {
                        await PaymentMethodsManager.init();
                    }

                    PaymentMethodsManager.populatePaymentSelect('edit-payment-method');
                } catch (error) {
                    console.error('Erreur lors du chargement des modes de paiement:', error);
                }
            },

            /**
             * Configure l'autocomplétion des fournisseurs
             */
            setupSupplierAutocomplete() {
                const input = document.getElementById('edit-supplier');
                const suggestions = document.getElementById('edit-fournisseurs-suggestions');

                if (!input || !suggestions) return;

                // Supprimer les anciens écouteurs
                input.replaceWith(input.cloneNode(true));
                const newInput = document.getElementById('edit-supplier');

                newInput.addEventListener('input', () => {
                    this.handleSupplierInput(newInput, suggestions);
                });

                document.addEventListener('click', (e) => {
                    if (e.target !== newInput && !suggestions.contains(e.target)) {
                        suggestions.classList.add('hidden');
                    }
                });
            },

            /**
             * Gère la saisie dans le champ fournisseur
             */
            handleSupplierInput(input, suggestions) {
                const value = input.value.toLowerCase().trim();

                suggestions.innerHTML = '';

                if (value.length < 2) {
                    suggestions.classList.add('hidden');
                    return;
                }

                const matches = AppState.suppliersList
                    .filter(supplier => supplier.toLowerCase().includes(value))
                    .slice(0, 8);

                if (matches.length > 0) {
                    suggestions.classList.remove('hidden');
                    matches.forEach(supplier => {
                        const div = document.createElement('div');
                        div.className = 'fournisseur-suggestion';

                        const index = supplier.toLowerCase().indexOf(value);
                        if (index !== -1) {
                            const before = supplier.substring(0, index);
                            const match = supplier.substring(index, index + value.length);
                            const after = supplier.substring(index + value.length);
                            div.innerHTML = `${Utils.escapeHtml(before)}<strong>${Utils.escapeHtml(match)}</strong>${Utils.escapeHtml(after)}`;
                        } else {
                            div.textContent = supplier;
                        }

                        div.onclick = () => {
                            input.value = supplier;
                            suggestions.innerHTML = '';
                            suggestions.classList.add('hidden');
                        };

                        suggestions.appendChild(div);
                    });
                } else {
                    suggestions.classList.add('hidden');
                }
            },

            /**
             * Traite la soumission du formulaire de modification
             */
            async handleSubmit(e) {
                e.preventDefault();
                const form = e.target;
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;

                // Validation
                if (!this.validateForm(form)) return;

                // Afficher un indicateur de chargement
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="material-icons animate-spin mr-1">refresh</span>Sauvegarde...';

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.closeModal();
                        Swal.fire({
                            title: 'Succès!',
                            text: data.message || 'Commande modifiée avec succès!',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message || 'Une erreur est survenue lors de la modification.',
                            icon: 'error'
                        });
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    Swal.fire({
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la communication avec le serveur.',
                        icon: 'error'
                    });
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            },

            /**
             * Valide le formulaire de modification - VERSION CORRIGÉE
             */
            validateForm(form) {
                const quantity = parseFloat(form.quantity.value);
                const price = parseFloat(form.prix_unitaire.value);
                const supplier = form.fournisseur.value.trim();
                const paymentMethod = form.payment_method.value;

                if (isNaN(quantity) || quantity <= 0) {
                    Swal.fire({
                        title: 'Quantité invalide',
                        text: 'Veuillez saisir une quantité valide supérieure à 0.',
                        icon: 'error'
                    });
                    return false;
                }

                if (isNaN(price) || price <= 0) {
                    Swal.fire({
                        title: 'Prix invalide',
                        text: 'Veuillez saisir un prix unitaire valide supérieur à 0.',
                        icon: 'error'
                    });
                    return false;
                }

                if (!supplier) {
                    Swal.fire({
                        title: 'Fournisseur manquant',
                        text: 'Veuillez sélectionner un fournisseur.',
                        icon: 'error'
                    });
                    return false;
                }

                // CORRECTION : Utiliser PaymentMethodsManager pour la validation
                if (!PaymentMethodsManager.validatePaymentMethod(paymentMethod)) {
                    return false;
                }

                return true;
            }
        };

        // État global de l'application
        const AppState = {
            suppliersList: [],
            selectedMaterials: new Set(),
            selectedPartialMaterials: new Set(),
            selectedOrderedMaterials: new Set(),
            selectedPendingMaterials: new Set()
        };

        /**
         * Module principal de l'application
         */
        const AchatsMateriauxApp = {
            /**
             * Initialisation de l'application
             */
            init() {
                console.log('Initialisation de l\'application Achats Matériaux');

                // Attendre que le DOM soit chargé
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => this.onDOMReady());
                } else {
                    this.onDOMReady();
                }
            },

            /**
             * Actions à effectuer une fois le DOM chargé
             */
            async onDOMReady() {
                // CORRECTION : Initialiser les modes de paiement en premier
                await PaymentMethodsManager.init();

                // Initialisation des autres modules
                DateTimeModule.init();
                TabsManager.init();
                EventHandlers.init();
                DataTablesManager.init();
                FournisseursModule.init();
                PartialOrdersManager.init();

                // Vérifications initiales
                this.performInitialChecks();
            },

            /**
             * Vérifications initiales au chargement
             */
            performInitialChecks() {
                // Vérifier les nouveaux matériaux
                this.checkNewMaterials();

                // Vérifier les validations finance après un court délai
                setTimeout(() => {
                    OrderValidationChecker.check();
                }, 1000);

                // Configurer les intervalles de rafraîchissement
                setInterval(() => this.checkNewMaterials(), CONFIG.REFRESH_INTERVALS.CHECK_MATERIALS);
                setInterval(() => OrderValidationChecker.check(), CONFIG.REFRESH_INTERVALS.CHECK_VALIDATION);
            },

            /**
             * Vérification des nouveaux matériaux
             */
            async checkNewMaterials() {
                try {
                    const response = await fetch(CONFIG.API_URLS.CHECK_MATERIALS);
                    const data = await response.json();

                    NotificationsManager.updateMaterialsNotification(data);
                } catch (error) {
                    console.error('Erreur lors de la vérification des matériaux:', error);
                }
            }
        };

        /**
         * Module de gestion de la date et heure
         */
        const DateTimeModule = {
            init() {
                this.updateDateTime();
                setInterval(() => this.updateDateTime(), CONFIG.REFRESH_INTERVALS.DATETIME);
            },

            updateDateTime() {
                const element = document.getElementById('date-time-display');
                if (element) {
                    const now = new Date();
                    element.textContent = `${now.toLocaleDateString('fr-FR')} ${now.toLocaleTimeString('fr-FR')}`;
                }
            }
        };

        /**
         * Gestionnaire des onglets
         */
        const TabsManager = {
            tabs: [{
                    tabId: 'tab-materials',
                    contentId: 'content-materials'
                },
                {
                    tabId: 'tab-grouped',
                    contentId: 'content-grouped'
                },
                {
                    tabId: 'tab-recents',
                    contentId: 'content-recents'
                },
                {
                    tabId: 'tab-returns',
                    contentId: 'content-returns'
                },
                {
                    tabId: 'tab-canceled',
                    contentId: 'content-canceled'
                }
            ],

            init() {
                this.setupMainTabs();
                this.setupMaterialTabs();
                this.checkURLParams();
            },

            setupMainTabs() {
                this.tabs.forEach(({
                    tabId,
                    contentId
                }) => {
                    const tab = document.getElementById(tabId);
                    const content = document.getElementById(contentId);

                    if (tab && content) {
                        tab.addEventListener('click', (e) => {
                            e.preventDefault();
                            this.activateTab(tab, content);
                        });
                    }
                });
            },

            setupMaterialTabs() {
                document.querySelectorAll('.materials-tab').forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.activateMaterialTab(tab);
                    });
                });
            },

            activateTab(activeTab, activeContent) {
                // Réinitialiser tous les onglets
                this.tabs.forEach(({
                    tabId
                }) => {
                    const tab = document.getElementById(tabId);
                    if (tab) {
                        tab.classList.remove('border-blue-500', 'text-blue-600');
                        tab.classList.add('border-transparent', 'text-gray-500');
                    }
                });

                // Cacher tous les contenus
                this.tabs.forEach(({
                    contentId
                }) => {
                    const content = document.getElementById(contentId);
                    if (content) content.classList.add('hidden');
                });

                // Activer l'onglet sélectionné
                activeTab.classList.remove('border-transparent', 'text-gray-500');
                activeTab.classList.add('border-blue-500', 'text-blue-600');
                activeContent.classList.remove('hidden');

                // Réajuster les DataTables
                DataTablesManager.adjustTables();
            },

            activateMaterialTab(tab) {
                const tabs = document.querySelectorAll('.materials-tab');

                // Désactiver tous les onglets
                tabs.forEach(t => {
                    t.classList.remove('active', 'text-blue-600', 'border-blue-600');
                    t.classList.add('text-gray-500', 'border-transparent');
                });

                // Activer l'onglet cliqué
                tab.classList.add('active', 'text-blue-600', 'border-blue-600');
                tab.classList.remove('text-gray-500', 'border-transparent');

                // Gérer l'affichage des sections
                const targetId = tab.getAttribute('data-target');
                document.querySelectorAll('.materials-section').forEach(section => {
                    section.classList.add('hidden');
                });

                const targetSection = document.getElementById(targetId);
                if (targetSection) {
                    targetSection.classList.remove('hidden');

                    // Charger les données si nécessaire
                    if (targetId === 'materials-partial') {
                        PartialOrdersManager.load(false);
                    }

                    DataTablesManager.adjustMaterialTable(targetId);
                }
            },

            checkURLParams() {
                const urlParams = new URLSearchParams(window.location.search);
                const tabParam = urlParams.get('tab');

                if (tabParam === 'recents') {
                    const recentsTab = document.getElementById('tab-recents');
                    const recentsContent = document.getElementById('content-recents');
                    if (recentsTab && recentsContent) {
                        this.activateTab(recentsTab, recentsContent);
                    }
                }
            }
        };

        /**
         * Gestionnaire des événements
         */
        const EventHandlers = {
            init() {
                this.setupGeneralEvents();
                this.setupModalEvents();
                this.setupFormEvents();
                this.setupCheckboxEvents();
                this.setupButtonEvents();
            },

            setupGeneralEvents() {
                // Case à cocher "Tout sélectionner" pour les matériaux en attente
                const selectAllPending = document.getElementById('select-all-pending-materials');
                if (selectAllPending) {
                    selectAllPending.addEventListener('change', (e) => {
                        const isChecked = e.target.checked;
                        document.querySelectorAll('#materials-pending .material-checkbox').forEach(checkbox => {
                            checkbox.checked = isChecked;
                            SelectionManager.updateSelection('pending', checkbox);
                        });
                        ButtonStateManager.updateAllButtons();
                    });
                }

                // Case à cocher "Tout sélectionner" pour les matériaux commandés
                const selectAllOrdered = document.getElementById('select-all-ordered-materials');
                if (selectAllOrdered) {
                    selectAllOrdered.addEventListener('change', (e) => {
                        const isChecked = e.target.checked;
                        document.querySelectorAll('.ordered-material-checkbox').forEach(checkbox => {
                            checkbox.checked = isChecked;
                            SelectionManager.updateSelection('ordered', checkbox);
                        });
                        ButtonStateManager.updateCancelButton();
                    });
                }

                // Case à cocher "Tout sélectionner" pour les commandes partielles
                const selectAllPartial = document.getElementById('select-all-partial-materials');
                if (selectAllPartial) {
                    selectAllPartial.addEventListener('change', (e) => {
                        const isChecked = e.target.checked;
                        document.querySelectorAll('.partial-material-checkbox').forEach(checkbox => {
                            checkbox.checked = isChecked;
                            SelectionManager.updateSelection('partial', checkbox);
                        });
                        ButtonStateManager.updateAllButtons();
                    });
                }
            },

            setupModalEvents() {
                // Fermeture des modals
                document.querySelectorAll('.close-modal-btn, .modal').forEach(element => {
                    element.addEventListener('click', (e) => {
                        if (e.target.classList.contains('close-modal-btn') ||
                            e.target.classList.contains('modal')) {
                            const modal = e.target.closest('.modal') || e.target;
                            ModalManager.close(modal);
                        }
                    });
                });
            },

            setupFormEvents() {
                // Formulaire d'achat individuel
                const purchaseForm = document.getElementById('purchase-form');
                if (purchaseForm) {
                    purchaseForm.addEventListener('submit', (e) => {
                        e.preventDefault();
                        PurchaseManager.handleIndividualPurchase(e);
                    });
                }

                // Formulaire d'achat groupé
                const bulkPurchaseForm = document.getElementById('bulk-purchase-form');
                if (bulkPurchaseForm) {
                    bulkPurchaseForm.addEventListener('submit', (e) => {
                        e.preventDefault();
                        PurchaseManager.handleBulkPurchase(e);
                    });
                }

                // Formulaire de substitution
                const substitutionForm = document.getElementById('substitution-form');
                if (substitutionForm) {
                    substitutionForm.addEventListener('submit', (e) => {
                        e.preventDefault();
                        SubstitutionManager.handleSubmit(e);
                    });
                }

                // Changement de type de prix
                const priceTypeSelect = document.getElementById('price-type');
                if (priceTypeSelect) {
                    priceTypeSelect.addEventListener('change', () => {
                        PurchaseManager.togglePriceInputs();
                    });
                }

                // Changement de raison de substitution
                const substitutionReason = document.getElementById('substitution-reason');
                if (substitutionReason) {
                    substitutionReason.addEventListener('change', function() {
                        const otherReasonContainer = document.getElementById('other-reason-container');
                        if (otherReasonContainer) {
                            otherReasonContainer.style.display = this.value === 'autre' ? 'block' : 'none';
                        }
                    });
                }
            },

            setupCheckboxEvents() {
                // Checkboxes des matériaux en attente
                document.querySelectorAll('#materials-pending .material-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', () => {
                        SelectionManager.updateSelection('pending', checkbox);
                        ButtonStateManager.updateAllButtons();
                    });
                });

                // Checkboxes des matériaux commandés
                document.querySelectorAll('.ordered-material-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', () => {
                        SelectionManager.updateSelection('ordered', checkbox);
                        ButtonStateManager.updateCancelButton();
                    });
                });
            },

            setupButtonEvents() {
                // Bouton d'achat groupé
                const bulkPurchaseBtn = document.getElementById('bulk-purchase-btn');
                if (bulkPurchaseBtn) {
                    bulkPurchaseBtn.addEventListener('click', () => {
                        ModalManager.openBulkPurchase();
                    });
                }

                // Bouton de complétion des commandes partielles
                const bulkCompleteBtn = document.getElementById('bulk-complete-btn');
                if (bulkCompleteBtn) {
                    bulkCompleteBtn.addEventListener('click', () => {
                        ModalManager.openBulkComplete();
                    });
                }

                // Bouton d'annulation multiple pour matériaux en attente
                const bulkCancelPendingBtn = document.getElementById('bulk-cancel-pending-btn');
                if (bulkCancelPendingBtn) {
                    bulkCancelPendingBtn.addEventListener('click', () => {
                        CancelManager.cancelMultiplePending();
                    });
                }

                // Bouton d'annulation multiple pour matériaux commandés
                const bulkCancelBtn = document.getElementById('bulk-cancel-btn');
                if (bulkCancelBtn) {
                    bulkCancelBtn.addEventListener('click', () => {
                        CancelManager.cancelMultipleOrders();
                    });
                }

                // Bouton d'actualisation des commandes partielles
                const refreshListBtn = document.getElementById('refresh-list');
                if (refreshListBtn) {
                    refreshListBtn.addEventListener('click', () => {
                        PartialOrdersManager.load(false);
                    });
                }

                // Bouton d'export Excel
                const exportExcelBtn = document.getElementById('export-excel');
                if (exportExcelBtn) {
                    exportExcelBtn.addEventListener('click', () => {
                        ExportManager.exportPartialOrdersToExcel();
                    });
                }
            }
        };

        /**
         * Gestionnaire de DataTables
         */
        const DataTablesManager = {
            tables: {
                pending: null,
                ordered: null,
                grouped: null,
                recent: null,
                returns: null,
                partial: null,
                canceled: null
            },

            init() {
                if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
                    console.error('jQuery ou DataTables non chargé');
                    return;
                }

                jQuery(document).ready(() => {
                    this.initializeTables();
                });
            },

            initializeTables() {
                this.initPendingMaterialsTable();
                this.initOrderedMaterialsTable();
                this.initGroupedProjectsTable();
                this.initRecentPurchasesTable();
                this.initSupplierReturnsTable();
                this.initCanceledOrdersTable();
            },

            initPendingMaterialsTable() {
                if (!jQuery('#pendingMaterialsTable').length) return;

                this.tables.pending = jQuery('#pendingMaterialsTable').DataTable({
                    responsive: true,
                    language: {
                        url: CONFIG.DATATABLES.LANGUAGE_URL
                    },
                    dom: CONFIG.DATATABLES.DOM,
                    buttons: CONFIG.DATATABLES.BUTTONS,
                    columnDefs: [{
                            orderable: false,
                            targets: [0, 9]
                        },
                        {
                            type: 'date-fr',
                            targets: 8
                        }
                    ],
                    order: [
                        [8, 'desc']
                    ],
                    pageLength: CONFIG.DATATABLES.PAGE_LENGTH,
                    drawCallback: function() {
                        // Réinitialiser l'état de la checkbox "Tout sélectionner"
                        const selectAllCheckbox = document.getElementById('select-all-pending-materials');
                        if (selectAllCheckbox) selectAllCheckbox.checked = false;

                        // Restaurer l'état des checkboxes basé sur notre Map de sélection
                        document.querySelectorAll('#pendingMaterialsTable .material-checkbox').forEach(checkbox => {
                            if (checkbox.dataset.id) {
                                checkbox.checked = SelectionManager.isSelected('pending', checkbox.dataset.id);
                                checkbox.addEventListener('change', () => {
                                    SelectionManager.updateSelection('pending', checkbox);
                                });
                            }
                        });

                        ButtonStateManager.updateBulkPurchaseButton();
                        ButtonStateManager.updateCancelPendingButton();
                    },
                    stateSave: true
                });
            },

            initOrderedMaterialsTable() {
                if (!jQuery('#orderedMaterialsTable').length) return;

                this.tables.ordered = jQuery('#orderedMaterialsTable').DataTable({
                    responsive: true,
                    language: {
                        url: CONFIG.DATATABLES.LANGUAGE_URL
                    },
                    dom: CONFIG.DATATABLES.DOM,
                    buttons: CONFIG.DATATABLES.BUTTONS,
                    columnDefs: [{
                            orderable: false,
                            targets: [0, 11]
                        },
                        {
                            type: 'date-fr',
                            targets: 9
                        },
                        {
                            type: 'num',
                            targets: 10
                        }
                    ],
                    order: [
                        [9, 'desc']
                    ],
                    pageLength: CONFIG.DATATABLES.PAGE_LENGTH,
                    drawCallback: () => {
                        this.resetSelectAll('select-all-ordered-materials');
                        this.attachCheckboxEvents('.ordered-material-checkbox', 'ordered');
                        ButtonStateManager.updateCancelButton();
                    }
                });
            },

            initGroupedProjectsTable() {
                if (!jQuery('#groupedProjectsTable').length) return;

                this.tables.grouped = jQuery('#groupedProjectsTable').DataTable({
                    responsive: true,
                    language: {
                        url: CONFIG.DATATABLES.LANGUAGE_URL
                    },
                    order: [
                        [0, 'desc']
                    ],
                    pageLength: 10
                });
            },

            initRecentPurchasesTable() {
                if (!jQuery('#recentPurchasesTable').length) return;

                this.tables.recent = jQuery('#recentPurchasesTable').DataTable({
                    responsive: true,
                    language: {
                        url: CONFIG.DATATABLES.LANGUAGE_URL
                    },
                    dom: CONFIG.DATATABLES.DOM,
                    buttons: CONFIG.DATATABLES.BUTTONS,
                    columnDefs: [{
                            type: 'date-fr',
                            targets: 8
                        },
                        {
                            targets: 9,
                            render: (data, type, row) => {
                                const expressionId = row[10] || '';
                                const orderId = row[11] || '';
                                let designation = row[2] || '';
                                designation = designation.replace(/<[^>]*>/g, '');
                                const cleanDesignation = Utils.escapeString(designation);

                                return `
                                    <button onclick="generateBonCommande('${expressionId}')" 
                                        class="btn-action text-green-600 hover:text-green-800 mr-2" 
                                        title="Générer bon de commande">
                                        <span class="material-icons">receipt</span>
                                    </button>
                                    <button onclick="viewOrderDetails('${orderId}', '${expressionId}', '${cleanDesignation}')" 
                                        class="btn-action text-blue-600 hover:text-blue-800 mr-2" 
                                        title="Voir les détails">
                                        <span class="material-icons">visibility</span>
                                    </button>
                                    <button onclick="viewStockDetails('${cleanDesignation}')" 
                                        class="btn-action text-purple-600 hover:text-purple-800" 
                                        title="Voir dans le stock">
                                        <span class="material-icons">inventory_2</span>
                                    </button>
                                `;
                            }
                        }
                    ],
                    order: [
                        [8, 'desc']
                    ],
                    pageLength: CONFIG.DATATABLES.PAGE_LENGTH
                });
            },

            initSupplierReturnsTable() {
                if (!jQuery('#supplierReturnsTable').length) return;

                this.tables.returns = jQuery('#supplierReturnsTable').DataTable({
                    responsive: true,
                    language: {
                        url: CONFIG.DATATABLES.LANGUAGE_URL
                    },
                    dom: CONFIG.DATATABLES.DOM,
                    buttons: CONFIG.DATATABLES.BUTTONS,
                    columnDefs: [{
                        type: 'date-fr',
                        targets: 4
                    }],
                    order: [
                        [4, 'desc']
                    ],
                    pageLength: CONFIG.DATATABLES.PAGE_LENGTH,
                    ajax: {
                        url: 'statistics/retour-fournisseur/api_getSupplierReturns.php',
                        type: 'GET',
                        dataSrc: (json) => {
                            const uniqueData = [];
                            const seenIds = new Set();
                            if (json.data && Array.isArray(json.data)) {
                                json.data.forEach(item => {
                                    if (!seenIds.has(item.id)) {
                                        seenIds.add(item.id);
                                        uniqueData.push(item);
                                    }
                                });
                            }
                            return uniqueData;
                        }
                    },
                    columns: [{
                            data: 'product_name'
                        },
                        {
                            data: 'supplier_name'
                        },
                        {
                            data: 'quantity'
                        },
                        {
                            data: 'reason'
                        },
                        {
                            data: 'created_at'
                        },
                        {
                            data: 'status',
                            render: (data) => {
                                const statusMap = {
                                    'completed': {
                                        class: 'bg-green-100 text-green-800',
                                        text: 'Complété'
                                    },
                                    'cancelled': {
                                        class: 'bg-red-100 text-red-800',
                                        text: 'Annulé'
                                    },
                                    'default': {
                                        class: 'bg-yellow-100 text-yellow-800',
                                        text: 'En attente'
                                    }
                                };
                                const status = statusMap[data] || statusMap.default;
                                return `<span class="px-2 py-1 text-xs rounded-full ${status.class}">${status.text}</span>`;
                            }
                        },
                        {
                            data: 'id',
                            render: (data) => `
                                <button onclick="viewReturnDetails(${data})" class="text-blue-600 hover:text-blue-800" title="Voir les détails">
                                    <span class="material-icons text-sm">visibility</span>
                                </button>
                            `
                        }
                    ]
                });
            },

            initCanceledOrdersTable() {
                if (!jQuery('#canceledOrdersTable').length) return;

                this.tables.canceled = jQuery('#canceledOrdersTable').DataTable({
                    responsive: true,
                    language: {
                        url: CONFIG.DATATABLES.LANGUAGE_URL
                    },
                    ajax: {
                        url: 'api_canceled/api_getCanceledOrders.php',
                        dataSrc: (json) => {
                            NotificationsManager.updateCanceledOrdersStats(json.stats);
                            return json.data || [];
                        }
                    },
                    columns: [{
                            data: 'code_projet'
                        },
                        {
                            data: 'nom_client'
                        },
                        {
                            data: 'designation'
                        },
                        {
                            data: 'original_status',
                            orderable: false
                        },
                        {
                            data: 'quantity'
                        },
                        {
                            data: 'fournisseur'
                        },
                        {
                            data: 'canceled_at'
                        },
                        {
                            data: 'cancel_reason'
                        },
                        {
                            data: 'id',
                            render: (data) => `
                                <button onclick="viewCanceledOrderDetails(${data})" class="text-blue-600 hover:text-blue-800">
                                    <span class="material-icons text-sm">visibility</span>
                                </button>
                            `,
                            orderable: false
                        }
                    ],
                    columnDefs: [{
                        type: 'date-fr',
                        targets: 6
                    }],
                    order: [
                        [6, 'desc']
                    ],
                    pageLength: CONFIG.DATATABLES.PAGE_LENGTH
                });
            },

            resetSelectAll(checkboxId) {
                const checkbox = document.getElementById(checkboxId);
                if (checkbox) checkbox.checked = false;
            },

            attachCheckboxEvents(selector, type) {
                document.querySelectorAll(selector).forEach(checkbox => {
                    checkbox.addEventListener('change', () => {
                        SelectionManager.updateSelection(type, checkbox);
                        switch (type) {
                            case 'pending':
                                ButtonStateManager.updateAllButtons();
                                break;
                            case 'ordered':
                                ButtonStateManager.updateCancelButton();
                                break;
                            case 'partial':
                                ButtonStateManager.updateAllButtons();
                                break;
                        }
                    });
                });
            },

            adjustTables() {
                Object.entries(this.tables).forEach(([key, table]) => {
                    if (table) {
                        table.columns.adjust().responsive.recalc();
                    }
                });
            },

            adjustMaterialTable(targetId) {
                const tableMap = {
                    'materials-pending': 'pending',
                    'materials-ordered': 'ordered',
                    'materials-partial': 'partial'
                };
                const tableKey = tableMap[targetId];
                if (tableKey && this.tables[tableKey]) {
                    this.tables[tableKey].columns.adjust().responsive.recalc();
                }
            }
        };

        /**
         * Gestionnaire des sélections - VERSION AMÉLIORÉE
         */
        const SelectionManager = {
            // Utiliser des Maps pour stocker les données complètes des matériaux sélectionnés par type
            selectionMaps: {
                'pending': new Map(),
                'ordered': new Map(),
                'partial': new Map()
            },

            /**
             * Met à jour la sélection d'un matériau
             */
            updateSelection(type, checkbox) {
                if (!this.selectionMaps[type]) return;

                const materialData = this.extractMaterialData(checkbox);
                const materialId = materialData.id;

                if (checkbox.checked) {
                    this.selectionMaps[type].set(materialId, materialData);
                } else {
                    this.selectionMaps[type].delete(materialId);
                }

                this.updateSelectionCounter(type);
            },

            /**
             * Extrait les données du matériau depuis les attributs data-* de la checkbox
             */
            extractMaterialData(checkbox) {
                return {
                    id: checkbox.dataset.id || checkbox.getAttribute('data-id'),
                    expressionId: checkbox.dataset.expression || checkbox.dataset.expressionId || checkbox.getAttribute('data-expression'),
                    designation: checkbox.dataset.designation || checkbox.getAttribute('data-designation'),
                    quantity: checkbox.dataset.quantity || checkbox.getAttribute('data-quantity'),
                    unit: checkbox.dataset.unit || checkbox.getAttribute('data-unit'),
                    sourceTable: checkbox.dataset.sourceTable || checkbox.getAttribute('data-source-table') || 'expression_dym',
                    project: checkbox.dataset.project || checkbox.getAttribute('data-project') || ''
                };
            },

            /**
             * Récupère les matériaux sélectionnés d'un type donné
             */
            getSelectedMaterials(type) {
                if (!this.selectionMaps[type]) return [];
                return Array.from(this.selectionMaps[type].values());
            },

            /**
             * Récupère les matériaux directement depuis le DOM pour plus de fiabilité
             */
            getSelectedMaterialsFromDOM(type) {
                const materials = [];
                let selector = '';

                switch (type) {
                    case 'pending':
                        selector = '#pendingMaterialsTable .material-checkbox:checked';
                        break;
                    case 'ordered':
                        selector = '.ordered-material-checkbox:checked';
                        break;
                    case 'partial':
                        selector = '.partial-material-checkbox:checked';
                        break;
                }

                if (selector) {
                    document.querySelectorAll(selector).forEach(checkbox => {
                        if (checkbox.dataset.id) {
                            materials.push(this.extractMaterialData(checkbox));
                        }
                    });
                }

                return materials;
            },

            /**
             * Met à jour le compteur dans le bouton correspondant au type
             */
            updateSelectionCounter(type) {
                switch (type) {
                    case 'pending':
                        ButtonStateManager.updateBulkPurchaseButton();
                        ButtonStateManager.updateCancelPendingButton();
                        break;
                    case 'ordered':
                        ButtonStateManager.updateCancelButton();
                        break;
                    case 'partial':
                        ButtonStateManager.updateBulkCompleteButton();
                        break;
                }
            },

            /**
             * Vérifie si un matériau est sélectionné
             */
            isSelected(type, id) {
                return this.selectionMaps[type] ? this.selectionMaps[type].has(id) : false;
            },

            /**
             * Réinitialise les sélections d'un type
             */
            clearSelections(type) {
                if (this.selectionMaps[type]) {
                    this.selectionMaps[type].clear();
                    this.updateSelectionCounter(type);
                }
            },

            /**
             * Synchronise les sélections entre la Map et le DOM
             */
            syncSelections(type) {
                const domMaterials = this.getSelectedMaterialsFromDOM(type);
                this.selectionMaps[type].clear();
                domMaterials.forEach(material => {
                    this.selectionMaps[type].set(material.id, material);
                });
                this.updateSelectionCounter(type);
            }
        };

        /**
         * Gestionnaire des états des boutons
         */
        const ButtonStateManager = {
            updateAllButtons() {
                this.updateBulkPurchaseButton();
                this.updateBulkCompleteButton();
                this.updateCancelPendingButton();
            },

            updateBulkPurchaseButton() {
                const button = document.getElementById('bulk-purchase-btn');
                if (!button) return;

                const selectedCount = SelectionManager.selectionMaps.pending.size;
                button.disabled = selectedCount === 0;
                button.innerHTML = `
                    <span class="material-icons align-middle mr-1">shopping_basket</span>
                    Commander les éléments sélectionnés${selectedCount > 0 ? ' (' + selectedCount + ')' : ''}
                `;
            },

            updateBulkCompleteButton() {
                const button = document.getElementById('bulk-complete-btn');
                if (!button) return;

                const selectedCount = document.querySelectorAll('.partial-material-checkbox:checked').length;
                button.disabled = selectedCount === 0;
                button.innerHTML = `
                    <span class="material-icons text-sm mr-1">shopping_basket</span>
                    Compléter les commandes sélectionnées${selectedCount > 0 ? ' (' + selectedCount + ')' : ''}
                `;
            },

            updateCancelPendingButton() {
                const button = document.getElementById('bulk-cancel-pending-btn');
                if (!button) return;

                const selectedCount = document.querySelectorAll('#pendingMaterialsTable .material-checkbox:checked').length;
                button.disabled = selectedCount === 0;
                button.innerHTML = `
                    <span class="material-icons text-sm mr-1">cancel</span>
                    Annuler les matériaux sélectionnés${selectedCount > 0 ? ' (' + selectedCount + ')' : ''}
                `;
            },

            updateCancelButton() {
                const button = document.getElementById('bulk-cancel-btn');
                if (!button) return;

                const selectedCount = document.querySelectorAll('.ordered-material-checkbox:checked').length;
                button.disabled = selectedCount === 0;
                button.innerHTML = `
                    <span class="material-icons text-sm mr-1">cancel</span>
                    Annuler les commandes sélectionnées${selectedCount > 0 ? ' (' + selectedCount + ')' : ''}
                `;
            }
        };

        /**
         * Gestionnaire des fournisseurs
         */
        const FournisseursModule = {
            async init() {
                try {
                    const response = await fetch(CONFIG.API_URLS.FOURNISSEURS);
                    const data = await response.json();
                    AppState.suppliersList = data;
                    this.initializeAutocomplete();
                    console.log(`${data.length} fournisseurs chargés avec succès`);
                } catch (error) {
                    console.error('Erreur lors du chargement des fournisseurs:', error);
                    this.showError();
                }
            },

            initializeAutocomplete() {
                this.setupAutocomplete('fournisseur', 'fournisseurs-suggestions');
                this.setupAutocomplete('fournisseur-bulk', 'fournisseurs-suggestions-bulk');
            },

            setupAutocomplete(inputId, suggestionsId) {
                const input = document.getElementById(inputId);
                const suggestions = document.getElementById(suggestionsId);

                if (!input || !suggestions) return;

                input.addEventListener('input', () => {
                    this.handleAutocompleteInput(input, suggestions);
                });

                document.addEventListener('click', (e) => {
                    if (e.target !== input && !suggestions.contains(e.target)) {
                        suggestions.classList.remove('active');
                    }
                });
            },

            handleAutocompleteInput(input, suggestions) {
                const value = input.value.toLowerCase().trim();

                suggestions.innerHTML = '';

                if (value.length < 2) {
                    suggestions.classList.remove('active');
                    return;
                }

                const matches = AppState.suppliersList
                    .filter(supplier => supplier.toLowerCase().includes(value))
                    .slice(0, 8);

                if (matches.length > 0) {
                    suggestions.classList.add('active');
                    matches.forEach(supplier => {
                        const div = this.createSuggestionItem(supplier, value);
                        div.onclick = () => {
                            input.value = supplier;
                            suggestions.innerHTML = '';
                            suggestions.classList.remove('active');
                        };
                        suggestions.appendChild(div);
                    });

                    // Ajouter l'option de gestion des fournisseurs
                    const manageDiv = this.createManageOption();
                    suggestions.appendChild(manageDiv);
                } else {
                    suggestions.classList.remove('active');
                }
            },

            createSuggestionItem(supplier, searchValue) {
                const div = document.createElement('div');
                div.className = 'fournisseur-suggestion';

                const index = supplier.toLowerCase().indexOf(searchValue);
                if (index !== -1) {
                    const before = supplier.substring(0, index);
                    const match = supplier.substring(index, index + searchValue.length);
                    const after = supplier.substring(index + searchValue.length);
                    div.innerHTML = `${Utils.escapeHtml(before)}<strong>${Utils.escapeHtml(match)}</strong>${Utils.escapeHtml(after)}`;
                } else {
                    div.textContent = supplier;
                }

                return div;
            },

            createManageOption() {
                const div = document.createElement('div');
                div.className = 'fournisseur-suggestion text-blue-600';
                div.innerHTML = `<span class="material-icons text-sm mr-1 align-middle">add</span> Gérer les fournisseurs`;
                div.onclick = () => window.open('../fournisseurs/fournisseurs.php', '_blank');
                return div;
            },

            async checkAndCreate(fournisseurName) {
                if (!fournisseurName || fournisseurName.trim() === '') {
                    throw new Error('Veuillez saisir un nom de fournisseur');
                }

                const formData = new FormData();
                formData.append('fournisseur', fournisseurName);

                try {
                    const response = await fetch(CONFIG.API_URLS.CHECK_FOURNISSEUR, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        return {
                            success: true,
                            newFournisseur: !data.exists,
                            name: fournisseurName,
                            id: data.id
                        };
                    } else {
                        throw new Error(data.message || 'Erreur lors de la vérification du fournisseur');
                    }
                } catch (error) {
                    console.error('Erreur lors de la vérification du fournisseur:', error);
                    throw error;
                }
            },

            showError() {
                Swal.fire({
                    title: 'Information',
                    text: 'Impossible de charger la liste des fournisseurs. Vous pouvez quand même saisir manuellement le nom du fournisseur.',
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
            }
        };

        /**
         * Gestionnaire des modals
         */
        const ModalManager = {
            openPurchase(expressionId, designation, quantity, unit, fournisseur = '') {
                // Remplir les champs du formulaire
                document.getElementById('expression_id').value = expressionId;
                document.getElementById('designation').value = designation;
                document.getElementById('quantite').value = quantity;
                document.getElementById('unite').value = unit;
                if (fournisseur) {
                    document.getElementById('fournisseur').value = fournisseur;
                }

                // Afficher le modal
                const modal = document.getElementById('purchase-modal');
                if (modal) modal.style.display = 'flex';

                // Chercher et charger le prix si possible
                this.loadMaterialPrice(expressionId, designation);
            },

            async loadMaterialPrice(expressionId, designation) {
                // Chercher l'ID du matériau correspondant
                const checkboxes = document.querySelectorAll('.material-checkbox');
                let materialId = null;

                for (const checkbox of checkboxes) {
                    if (checkbox.dataset.expression === expressionId &&
                        checkbox.dataset.designation === designation) {
                        materialId = checkbox.dataset.id;
                        break;
                    }
                }

                if (!materialId) return;

                try {
                    const response = await fetch(`${CONFIG.API_URLS.MATERIAL_INFO}?material_id=${materialId}`);
                    const data = await response.json();

                    if (data && data.prix_unitaire && parseFloat(data.prix_unitaire) > 0) {
                        const prixField = document.getElementById('prix');
                        if (prixField) {
                            prixField.value = data.prix_unitaire;
                        }
                    }
                } catch (error) {
                    console.error("Erreur lors de la récupération des infos matériau:", error);
                }
            },

            async openBulkPurchase() {
                const selectedMaterials = SelectionManager.getSelectedMaterials('pending');

                if (selectedMaterials.length === 0) {
                    Swal.fire({
                        title: 'Aucun matériau sélectionné',
                        text: 'Veuillez sélectionner au moins un matériau à acheter.',
                        icon: 'warning'
                    });
                    return;
                }

                this.prepareBulkPurchaseModal(selectedMaterials);
            },

            async prepareBulkPurchaseModal(materials) {
                const container = document.getElementById('selected-materials-container');
                const tbody = document.getElementById('individual-prices-tbody');

                if (!container || !tbody) return;

                // Réinitialiser le contenu
                container.innerHTML = `<p class="mb-2">Vous avez sélectionné <strong>${materials.length}</strong> matériaux à acheter.</p>`;
                tbody.innerHTML = '';

                // Ajouter les champs cachés
                materials.forEach(material => {
                    container.innerHTML += `
                        <input type="hidden" name="material_ids[]" value="${material.id}">
                        <input type="hidden" name="source_table[${material.id}]" value="${material.sourceTable || 'expression_dym'}">
                    `;
                });

                // Initialiser l'autocomplétion
                FournisseursModule.setupAutocomplete('fournisseur-bulk', 'fournisseurs-suggestions-bulk');

                // CORRECTION : Peupler les modes de paiement
                PaymentMethodsManager.populatePaymentSelect('payment-method-bulk');

                // Afficher le modal
                const modal = document.getElementById('bulk-purchase-modal');
                if (modal) {
                    const modalTitle = modal.querySelector('h2');
                    if (modalTitle) modalTitle.textContent = 'Achat groupé de matériaux';

                    const confirmButton = modal.querySelector('#confirm-bulk-purchase');
                    if (confirmButton) confirmButton.textContent = 'Passer la commande';

                    modal.style.display = 'flex';
                }

                // Charger les prix
                await this.loadBulkPrices(materials);
            },

            async loadBulkPrices(materials) {
                const tbody = document.getElementById('individual-prices-tbody');
                const commonPrice = document.getElementById('common-price');

                try {
                    const pricePromises = materials.map(material => {
                        const apiUrl = material.sourceTable === 'besoins' ?
                            `api/besoins/get_besoin_info.php?besoin_id=${material.id}` :
                            `${CONFIG.API_URLS.MATERIAL_INFO}?material_id=${material.id}`;

                        return fetch(apiUrl)
                            .then(response => response.json())
                            .catch(() => null);
                    });

                    const results = await Promise.all(pricePromises);

                    // Calculer le prix moyen
                    const validPrices = results
                        .filter(data => data && data.prix_unitaire && parseFloat(data.prix_unitaire) > 0)
                        .map(data => parseFloat(data.prix_unitaire));

                    if (validPrices.length > 0 && commonPrice) {
                        const averagePrice = validPrices.reduce((sum, price) => sum + price, 0) / validPrices.length;
                        commonPrice.value = averagePrice.toFixed(2);
                    }

                    // Créer les lignes du tableau
                    tbody.innerHTML = '';
                    materials.forEach((material, index) => {
                        const prix = results[index]?.prix_unitaire || '';
                        const row = this.createPriceRow(material, prix);
                        tbody.appendChild(row);
                    });
                } catch (error) {
                    console.error("Erreur lors du chargement des prix:", error);
                }
            },

            createPriceRow(material, prix) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm">${Utils.escapeHtml(material.designation)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <input type="number" name="quantities[${material.id}]" 
                            class="shadow border rounded w-full py-1 px-2 text-gray-700" 
                            step="0.01" min="0.01" value="${material.quantity}" required>
                        <input type="hidden" name="original_quantities[${material.id}]" value="${material.quantity}">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">${Utils.escapeHtml(material.unit || 'N/A')}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <input type="number" name="prices[${material.id}]" 
                            class="shadow border rounded w-full py-1 px-2 text-gray-700" 
                            step="0.01" min="0" value="${prix}" required>
                    </td>
                `;
                return row;
            },

            async openBulkComplete() {
                const selectedMaterials = SelectionManager.getSelectedMaterials('partial');

                if (selectedMaterials.length === 0) {
                    Swal.fire({
                        title: 'Aucun matériau sélectionné',
                        text: 'Veuillez sélectionner au moins un matériau à compléter.',
                        icon: 'warning'
                    });
                    return;
                }

                // Utiliser la même modal que pour l'achat groupé mais avec des adaptations
                const container = document.getElementById('selected-materials-container');
                const tbody = document.getElementById('individual-prices-tbody');
                const modal = document.getElementById('bulk-purchase-modal');

                if (!container || !tbody || !modal) return;

                // Adapter le contenu pour la complétion
                container.innerHTML = `<p class="mb-2">Vous avez sélectionné <strong>${selectedMaterials.length}</strong> matériaux à compléter.</p>`;
                tbody.innerHTML = '';

                // Ajouter les champs cachés avec indicateur de commande partielle
                selectedMaterials.forEach(material => {
                    container.innerHTML += `
                        <input type="hidden" name="material_ids[]" value="${material.id}">
                        <input type="hidden" name="source_table[${material.id}]" value="${material.sourceTable || 'expression_dym'}">
                        <input type="hidden" name="is_partial[${material.id}]" value="1">
                    `;
                });

                // Initialiser l'autocomplétion des fournisseurs
                FournisseursModule.setupAutocomplete('fournisseur-bulk', 'fournisseurs-suggestions-bulk');

                // CORRECTION : Peupler le sélecteur de modes de paiement
                PaymentMethodsManager.populatePaymentSelect('payment-method-bulk');

                // Réinitialiser le champ de mode de paiement
                const paymentSelect = document.getElementById('payment-method-bulk');
                const paymentDescription = document.getElementById('payment-method-description');
                if (paymentSelect) paymentSelect.value = '';
                if (paymentDescription) paymentDescription.innerHTML = '';

                // Modifier le titre et le bouton
                const modalTitle = modal.querySelector('h2');
                if (modalTitle) modalTitle.textContent = 'Compléter les commandes partielles';

                const confirmButton = modal.querySelector('#confirm-bulk-purchase');
                if (confirmButton) confirmButton.textContent = 'Compléter les commandes';

                // Afficher le modal
                modal.style.display = 'flex';

                // Charger les prix et informations
                await this.loadPartialOrderPrices(selectedMaterials);
            },

            async loadPartialOrderPrices(materials) {
                const tbody = document.getElementById('individual-prices-tbody');
                const commonPrice = document.getElementById('common-price');
                const fournisseurInput = document.getElementById('fournisseur-bulk');

                try {
                    const pricePromises = materials.map(material => {
                        const apiUrl = material.sourceTable === 'besoins' ?
                            `commandes-traitement/besoins/get_besoin_with_remaining.php?id=${material.id}` :
                            `commandes-traitement/api.php?action=get_material_info&id=${material.id}`;

                        return fetch(apiUrl)
                            .then(response => response.json())
                            .catch(() => null);
                    });

                    const results = await Promise.all(pricePromises);

                    // Calculer le prix moyen et récupérer le dernier fournisseur
                    const validPrices = results
                        .filter(data => data && data.prix_unitaire && parseFloat(data.prix_unitaire) > 0)
                        .map(data => parseFloat(data.prix_unitaire));

                    if (validPrices.length > 0 && commonPrice) {
                        const averagePrice = validPrices.reduce((sum, price) => sum + price, 0) / validPrices.length;
                        commonPrice.value = averagePrice.toFixed(2);
                    }

                    // Suggérer le dernier fournisseur utilisé
                    const fournisseurSuggested = results.find(data => data?.fournisseur)?.fournisseur;
                    if (fournisseurSuggested && fournisseurInput && !fournisseurInput.value) {
                        fournisseurInput.value = fournisseurSuggested;
                    }

                    // Créer les lignes du tableau
                    tbody.innerHTML = '';
                    materials.forEach((material, index) => {
                        const prix = results[index]?.prix_unitaire || '';
                        const row = this.createPartialPriceRow(material, prix);
                        tbody.appendChild(row);
                    });
                } catch (error) {
                    console.error("Erreur lors du chargement des prix pour commandes partielles:", error);
                }
            },

            createPartialPriceRow(material, prix) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm">${Utils.escapeHtml(material.designation)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <input type="number" name="quantities[${material.id}]" 
                            class="shadow border rounded w-full py-1 px-2 text-gray-700" 
                            step="0.01" min="0.01" max="${material.quantity}" value="${material.quantity}" required>
                        <input type="hidden" name="original_quantities[${material.id}]" value="${material.quantity}">
                        <input type="hidden" name="is_partial[${material.id}]" value="1">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">${Utils.escapeHtml(material.unit || 'N/A')}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <input type="number" name="prices[${material.id}]" 
                            class="shadow border rounded w-full py-1 px-2 text-gray-700" 
                            step="0.01" min="0" value="${prix}" required>
                    </td>
                `;
                return row;
            },

            openSubstitution(materialId, designation, expressionId, sourceTable = 'expression_dym') {
                const modal = document.getElementById('substitution-modal');
                const originalProductInput = document.getElementById('original-product');
                const materialIdInput = document.getElementById('substitute-material-id');
                const expressionIdInput = document.getElementById('substitute-expression-id');
                const sourceTableInput = document.getElementById('substitute-source-table');

                if (!modal || !originalProductInput || !materialIdInput || !expressionIdInput || !sourceTableInput) return;

                // Remplir les champs
                originalProductInput.value = designation;
                materialIdInput.value = materialId;
                expressionIdInput.value = expressionId;
                sourceTableInput.value = sourceTable;

                // Afficher le modal
                modal.style.display = 'flex';

                // Configurer l'autocomplétion
                SubstitutionManager.setupProductAutocomplete();
            },

            close(modal) {
                if (modal) modal.style.display = 'none';
            }
        };

        /**
         * Gestionnaire des achats - VERSION CORRIGÉE
         */
        const PurchaseManager = {
            async handleIndividualPurchase(e) {
                e.preventDefault();
                const form = e.target;
                const fournisseur = document.getElementById('fournisseur').value;
                const prix = document.getElementById('prix').value;
                const paymentMethod = document.getElementById('payment-method').value; // NOUVEAU

                // CORRECTION : Validation incluant le mode de paiement
                if (!this.validateIndividualPurchase(fournisseur, prix, paymentMethod)) return;

                try {
                    // Vérifier et créer le fournisseur si nécessaire
                    const fournisseurResult = await FournisseursModule.checkAndCreate(fournisseur);

                    // Afficher un indicateur de chargement
                    Swal.fire({
                        title: 'Traitement en cours...',
                        text: 'Enregistrement de la commande',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Préparer et envoyer les données
                    const formData = new FormData(form);
                    if (fournisseurResult.newFournisseur) {
                        formData.append('create_fournisseur', '1');
                    }

                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        ModalManager.close(document.getElementById('purchase-modal'));
                        Swal.fire({
                            title: 'Succès!',
                            text: data.message || 'Commande enregistrée avec succès!',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message || 'Une erreur est survenue lors du traitement de la commande.',
                            icon: 'error'
                        });
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    Swal.fire({
                        title: 'Erreur',
                        text: error.message || 'Une erreur est survenue lors de la communication avec le serveur.',
                        icon: 'error'
                    });
                }
            },

            async handleBulkPurchase(e) {
                e.preventDefault();
                const form = e.target;
                const priceType = document.getElementById('price-type').value;
                const fournisseur = document.getElementById('fournisseur-bulk').value;

                // CORRECTION : Validation incluant le mode de paiement
                if (!this.validateBulkPurchase(priceType, fournisseur)) return;

                // Déterminer l'URL de soumission
                const isPartialCompletion = form.querySelector('input[name^="is_partial["]') !== null;
                const submitUrl = isPartialCompletion ?
                    'commandes-traitement/api.php?action=complete_multiple_partial' :
                    'process_bulk_purchase.php';

                try {
                    // Vérifier et créer le fournisseur si nécessaire
                    const fournisseurResult = await FournisseursModule.checkAndCreate(fournisseur);

                    Swal.fire({
                        title: 'Traitement en cours...',
                        text: isPartialCompletion ? 'Complétion des commandes en cours' : 'Enregistrement de la commande',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Préparer les données
                    const formData = new FormData(form);
                    if (!formData.has('bulk_purchase')) {
                        formData.append('bulk_purchase', '1');
                    }

                    if (fournisseurResult.newFournisseur) {
                        formData.append('create_fournisseur', '1');
                    }

                    const response = await fetch(submitUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        ModalManager.close(document.getElementById('bulk-purchase-modal'));

                        // Gérer le téléchargement du PDF si disponible
                        if (data.pdf_url) {
                            window.open(data.pdf_url, '_blank');
                            Swal.fire({
                                title: 'Succès!',
                                text: 'Commande enregistrée avec succès et le bon de commande est en cours de téléchargement.',
                                icon: 'success',
                                timer: 3000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Succès!',
                                text: data.message || (isPartialCompletion ?
                                    'Commandes complétées avec succès!' :
                                    'Matériaux commandés avec succès!'),
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        }
                    } else {
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message || 'Une erreur est survenue lors du traitement de la commande.',
                            icon: 'error'
                        });
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    Swal.fire({
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la communication avec le serveur.',
                        icon: 'error'
                    });
                }
            },

            validateIndividualPurchase(fournisseur, prix, paymentMethod) {
                if (!fournisseur.trim()) {
                    Swal.fire({
                        title: 'Fournisseur manquant',
                        text: 'Veuillez sélectionner un fournisseur.',
                        icon: 'error'
                    });
                    return false;
                }

                if (!prix || parseFloat(prix) <= 0) {
                    Swal.fire({
                        title: 'Prix invalide',
                        text: 'Veuillez saisir un prix unitaire valide.',
                        icon: 'error'
                    });
                    return false;
                }

                // CORRECTION : Validation du mode de paiement
                if (!PaymentMethodsManager.validatePaymentMethod(paymentMethod)) {
                    return false;
                }

                return true;
            },

            validateBulkPurchase(priceType, fournisseur) {
                if (!fournisseur.trim()) {
                    Swal.fire({
                        title: 'Fournisseur manquant',
                        text: 'Veuillez sélectionner un fournisseur.',
                        icon: 'error'
                    });
                    return false;
                }

                // CORRECTION PRINCIPALE : Validation du mode de paiement
                const paymentMethodSelect = document.getElementById('payment-method-bulk');
                if (!paymentMethodSelect) {
                    console.error('❌ Sélecteur de mode de paiement non trouvé');
                    return false;
                }

                const paymentMethodId = paymentMethodSelect.value;
                if (!PaymentMethodsManager.validatePaymentMethod(paymentMethodId)) {
                    return false;
                }

                // Validation du pro-forma (si applicable)
                if (window.ProformaUploadManager) {
                    const proformaValidation = ProformaUploadManager.validateForSubmission();
                    if (!proformaValidation.isValid) {
                        Swal.fire({
                            title: 'Erreur Pro-forma',
                            text: proformaValidation.message,
                            icon: 'error',
                            confirmButtonColor: '#4F46E5'
                        });
                        return false;
                    }
                }

                // Validation des quantités
                const quantityInputs = document.querySelectorAll('input[name^="quantities["]');
                for (const input of quantityInputs) {
                    if (!input.value || parseFloat(input.value) <= 0) {
                        Swal.fire({
                            title: 'Quantités invalides',
                            text: 'Veuillez saisir une quantité valide supérieure à 0 pour chaque matériau.',
                            icon: 'warning',
                            confirmButtonColor: '#4F46E5'
                        });
                        return false;
                    }
                }

                // Validation des prix
                if (priceType === 'common') {
                    const commonPriceInput = document.getElementById('common-price');
                    if (!commonPriceInput || !commonPriceInput.value || parseFloat(commonPriceInput.value) <= 0) {
                        Swal.fire({
                            title: 'Prix invalide',
                            text: 'Veuillez saisir un prix unitaire commun valide.',
                            icon: 'error',
                            confirmButtonColor: '#4F46E5'
                        });
                        return false;
                    }
                } else {
                    const priceInputs = document.querySelectorAll('input[name^="prices["]');
                    for (const input of priceInputs) {
                        if (!input.value || parseFloat(input.value) <= 0) {
                            Swal.fire({
                                title: 'Prix manquants',
                                text: 'Veuillez saisir un prix valide pour chaque matériau.',
                                icon: 'error',
                                confirmButtonColor: '#4F46E5'
                            });
                            return false;
                        }
                    }
                }

                return true;
            },

            togglePriceInputs() {
                const priceType = document.getElementById('price-type');
                const commonPriceContainer = document.getElementById('common-price-container');
                const individualPricesContainer = document.getElementById('individual-prices-container');

                if (priceType && commonPriceContainer && individualPricesContainer) {
                    const isCommon = priceType.value === 'common';
                    commonPriceContainer.classList.toggle('hidden', !isCommon);
                    individualPricesContainer.classList.toggle('hidden', isCommon);
                }
            }
        };

        /**
         * Gestionnaire des annulations - VERSION CORRIGÉE
         */
        const CancelManager = {
            cancelSingleOrder(id, expressionId, designation, sourceTable = 'expression_dym') {
                this.openCancelConfirmationModal([{
                    id: id,
                    expressionId: expressionId,
                    designation: designation,
                    project: '',
                    sourceTable: sourceTable
                }]);
            },

            cancelMultipleOrders() {
                const selectedMaterials = SelectionManager.getSelectedMaterials('ordered');

                if (selectedMaterials.length === 0) {
                    Swal.fire({
                        title: 'Aucune commande sélectionnée',
                        text: 'Veuillez sélectionner au moins une commande à annuler.',
                        icon: 'warning'
                    });
                    return;
                }

                this.openCancelConfirmationModal(selectedMaterials);
            },

            cancelPendingMaterial(id, expressionId, designation, sourceTable = 'expression_dym') {
                this.openCancelPendingMaterialModal([{
                    id: id,
                    expressionId: expressionId,
                    designation: designation,
                    project: '',
                    sourceTable: sourceTable
                }]);
            },

            cancelMultiplePending() {
                const selectedMaterials = SelectionManager.getSelectedMaterialsFromDOM('pending');

                if (selectedMaterials.length === 0) {
                    Swal.fire({
                        title: 'Aucun matériau sélectionné',
                        text: 'Veuillez sélectionner au moins un matériau à annuler.',
                        icon: 'warning'
                    });
                    return;
                }

                SelectionManager.syncSelections('pending');
                this.openCancelPendingMaterialModal(selectedMaterials);
            },

            openCancelConfirmationModal(materials) {
                const materialsList = this.createMaterialsList(materials);

                Swal.fire({
                    title: materials.length === 1 ? 'Annuler la commande?' : `Annuler ${materials.length} commandes?`,
                    html: `Êtes-vous sûr de vouloir annuler ${materials.length === 1 ? 'cette commande' : 'ces commandes'}?<br>${materialsList}`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, annuler',
                    cancelButtonText: 'Non, garder',
                    confirmButtonColor: '#d33',
                    reverseButtons: true,
                    input: 'text',
                    inputLabel: 'Raison de l\'annulation',
                    inputPlaceholder: 'Veuillez indiquer la raison de l\'annulation',
                    inputValidator: (value) => {
                        if (!value || value.trim() === '') {
                            return 'Vous devez indiquer une raison d\'annulation';
                        }
                    },
                    showLoaderOnConfirm: true,
                    preConfirm: (reasonText) => this.performCancellation(reasonText, materials, CONFIG.API_URLS.CANCEL_ORDERS),
                    allowOutsideClick: () => !Swal.isLoading()
                }).then(result => {
                    if (result.isConfirmed) {
                        this.showSuccessMessage(materials.length);
                    }
                });
            },

            openCancelPendingMaterialModal(materials) {
                const materialsList = this.createMaterialsList(materials);

                Swal.fire({
                    title: materials.length === 1 ? 'Annuler ce matériau?' : `Annuler ${materials.length} matériaux?`,
                    html: `Êtes-vous sûr de vouloir annuler ${materials.length === 1 ? 'ce matériau' : 'ces matériaux'} en attente?<br>${materialsList}`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, annuler',
                    cancelButtonText: 'Non, garder',
                    confirmButtonColor: '#d33',
                    reverseButtons: true,
                    input: 'text',
                    inputLabel: 'Raison de l\'annulation',
                    inputPlaceholder: 'Veuillez indiquer la raison de l\'annulation',
                    inputValidator: (value) => {
                        if (!value || value.trim() === '') {
                            return 'Vous devez indiquer une raison d\'annulation';
                        }
                    },
                    showLoaderOnConfirm: true,
                    preConfirm: (reasonText) => this.performPendingCancellation(reasonText, materials),
                    allowOutsideClick: () => !Swal.isLoading()
                }).then(result => {
                    if (result.isConfirmed) {
                        this.showSuccessMessage(materials.length, 'Matériau(x) annulé(s)!',
                            materials.length === 1 ? 'Le matériau a été annulé avec succès' :
                            'Les matériaux ont été annulés avec succès');
                    }
                });
            },

            createMaterialsList(materials) {
                if (materials.length === 1) {
                    const sourceLabel = materials[0].sourceTable === 'besoins' ? 'Système' : 'Projet';
                    return `<p><strong>${materials[0].designation}</strong></p>
                            <p class="text-sm text-gray-600">(Source: ${sourceLabel})</p>`;
                } else {
                    let list = '<ul class="text-left mt-2 mb-4 max-h-40 overflow-y-auto">';
                    materials.forEach(material => {
                        const sourceLabel = material.sourceTable === 'besoins' ? 'Système' : 'Projet';
                        list += `<li class="py-1 border-b border-gray-200 flex justify-between">
                                    <span class="font-medium">${material.designation}</span>
                                    <span class="text-sm text-gray-600">${material.project || ''} (${sourceLabel})</span>
                                </li>`;
                    });
                    list += '</ul>';
                    return list;
                }
            },

            async performPendingCancellation(reasonText, materials) {
                const formData = new FormData();
                formData.append('reason', reasonText);
                formData.append('materials', JSON.stringify(materials));

                try {
                    const response = await fetch('api/orders/cancel_pending_materials.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error(`Erreur HTTP: ${response.status}`);
                    }

                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.message || 'Erreur lors de l\'annulation');
                    }

                    return data;
                } catch (error) {
                    console.error("Erreur lors de l'annulation:", error);
                    Swal.showValidationMessage(`Erreur: ${error.message}`);
                }
            },

            async performCancellation(reasonText, materials, apiUrl, type = null) {
                const formData = new FormData();
                formData.append('reason', reasonText);
                formData.append('materials', JSON.stringify(materials));
                if (type) formData.append('type', type);

                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error('Erreur réseau');
                    }

                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.message || 'Erreur lors de l\'annulation');
                    }

                    return data;
                } catch (error) {
                    console.error("Erreur lors de l'annulation:", error);
                    Swal.showValidationMessage(`Erreur: ${error.message}`);
                }
            },

            showSuccessMessage(count, title = 'Commande(s) annulée(s)!', message = null) {
                const defaultMessage = count === 1 ?
                    'La commande a été annulée avec succès' :
                    'Les commandes ont été annulées avec succès';

                Swal.fire({
                    title: title,
                    text: message || defaultMessage,
                    icon: 'success',
                    timer: 3000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            }
        };

        /**
         * Gestionnaire des commandes partielles
         */
        const PartialOrdersManager = {
            init() {
                this.load(false);
            },

            async load(switchTab = true) {
                try {
                    this.showLoading();
                    const response = await fetch(`${CONFIG.API_URLS.PARTIAL_ORDERS}?action=get_remaining`);
                    if (!response.ok) {
                        throw new Error(`Erreur HTTP: ${response.status}`);
                    }
                    const data = await response.json();
                    if (data.success) {
                        // Mettre à jour les statistiques
                        NotificationsManager.updatePartialOrdersStats(data.stats);
                        // Mettre à jour le compteur dans l'onglet
                        this.updateTabCounter(data.materials ? data.materials.length : 0);
                        // Afficher l'onglet si demandé
                        if (switchTab || this.isTabActive()) {
                            if (switchTab) this.showTab();
                            this.renderTable(data.materials || []);
                        }
                    } else {
                        this.showError(data.message);
                    }
                } catch (error) {
                    console.error("Erreur réseau:", error);
                    this.showError(error.message);
                }
            },

            showLoading() {
                const tbody = document.getElementById('partial-orders-body');
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                                <svg class="animate-spin h-5 w-5 mr-3 inline" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Chargement des données...
                            </td>
                        </tr>
                    `;
                }
            },

            showError(message) {
                const tbody = document.getElementById('partial-orders-body');
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-sm text-red-500">
                                Erreur lors du chargement des données: ${message || 'Veuillez réessayer'}
                            </td>
                        </tr>
                    `;
                }
            },

            isTabActive() {
                const section = document.getElementById('materials-partial');
                return section && !section.classList.contains('hidden');
            },

            showTab() {
                TabsManager.activateMaterialTab(document.getElementById('materials-partial-tab'));
            },

            updateTabCounter(count) {
                const counter = document.querySelector('#materials-partial-tab .rounded-full');
                if (counter) {
                    counter.textContent = count;
                    counter.classList.toggle('bg-yellow-100', count > 0);
                    counter.classList.toggle('text-yellow-800', count > 0);
                    counter.classList.toggle('bg-gray-100', count === 0);
                    counter.classList.toggle('text-gray-800', count === 0);
                }
            },

            renderTable(materials) {
                const tbody = document.getElementById('partial-orders-body');
                if (!tbody) return;

                // Sauvegarder les sélections actuelles
                const selectedIds = this.getSelectedIds();

                // Détruire le DataTable existant
                if (jQuery.fn.DataTable.isDataTable('#partialOrdersTable')) {
                    jQuery('#partialOrdersTable').DataTable().destroy();
                }

                if (!materials || materials.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                                Aucune commande partielle trouvée.
                            </td>
                        </tr>
                    `;
                    return;
                }

                // Construire le HTML
                const html = materials.map(material => this.createTableRow(material, selectedIds)).join('');
                tbody.innerHTML = html;

                // Réattacher les événements
                this.attachEvents();

                // Initialiser DataTable
                this.initDataTable();
            },

            getSelectedIds() {
                const selectedIds = [];
                document.querySelectorAll('.partial-material-checkbox:checked').forEach(checkbox => {
                    if (checkbox.dataset && checkbox.dataset.id) {
                        selectedIds.push(checkbox.dataset.id);
                    }
                });
                return selectedIds;
            },

            createTableRow(material, selectedIds) {
                const sourceTable = material.source_table || 'expression_dym';
                // Adapter les variables selon la source
                const designation = sourceTable === 'besoins' ?
                    material.designation || material.designation_article || 'Sans désignation' :
                    material.designation || 'Sans désignation';
                const unit = sourceTable === 'besoins' ?
                    material.unit || material.caracteristique || '' :
                    material.unit || '';
                const restante = parseFloat(material.qt_restante || 0);
                const expressionId = sourceTable === 'besoins' ?
                    material.idExpression || material.idBesoin || '' :
                    material.idExpression || '';

                // Calculer les valeurs
                const initialQty = parseFloat(material.quantite_initiale || material.initial_qt_acheter || 0);
                const orderedQty = parseFloat(material.quantite_commandee || material.quantite_deja_commandee || 0);
                const progress = initialQty > 0 ? Math.round((orderedQty / initialQty) * 100) : 0;

                // Déterminer la couleur de progression
                let progressColor = 'bg-yellow-500';
                if (progress >= 75) progressColor = 'bg-green-500';
                if (progress < 25) progressColor = 'bg-red-500';

                // Vérifier la sélection
                const isChecked = selectedIds.includes(material.id?.toString()) ? 'checked' : '';

                return `
                    <tr class="${progress < 50 ? 'bg-yellow-50 pulse-animation' : ''}" data-id="${material.id}">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="checkbox" class="material-checkbox partial-material-checkbox"
                                data-id="${material.id}"
                                data-expression="${expressionId}"
                                data-designation="${Utils.escapeHtml(designation)}"
                                data-quantity="${restante}"
                                data-unit="${Utils.escapeHtml(unit)}"
                                data-source-table="${sourceTable}"
                                ${isChecked}>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">${material.code_projet || '-'}</td>
                        <td class="px-6 py-4 whitespace-nowrap">${material.nom_client || '-'}</td>
                        <td class="px-6 py-4 whitespace-nowrap font-medium">${Utils.escapeHtml(designation)}</td>
                        <td class="px-6 py-4 whitespace-nowrap">${Utils.formatQuantity(initialQty)} ${Utils.escapeHtml(unit)}</td>
                        <td class="px-6 py-4 whitespace-nowrap">${Utils.formatQuantity(orderedQty)} ${Utils.escapeHtml(unit)}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-yellow-600 font-medium">${Utils.formatQuantity(restante)} ${Utils.escapeHtml(unit)}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="${progressColor} h-2 rounded-full" style="width: ${progress}%"></div>
                                </div>
                                <span class="ml-2 text-xs font-medium">${progress}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button onclick="completePartialOrder('${material.id}', '${Utils.escapeString(designation)}', ${restante}, '${Utils.escapeHtml(unit)}', '${sourceTable}')" 
                                class="text-blue-600 hover:text-blue-900 mr-2">
                                <span class="material-icons text-sm">add_shopping_cart</span>
                            </button>
                            <button onclick="viewPartialOrderDetails('${material.id}', '${sourceTable}')" 
                                class="text-gray-600 hover:text-gray-900">
                                <span class="material-icons text-sm">visibility</span>
                            </button>
                        </td>
                    </tr>
                `;
            },

            attachEvents() {
                const selectAllCheckbox = document.getElementById('select-all-partial-materials');
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.removeEventListener('change', this.handleSelectAll);
                    selectAllCheckbox.addEventListener('change', this.handleSelectAll);
                }

                document.querySelectorAll('.partial-material-checkbox').forEach(checkbox => {
                    checkbox.removeEventListener('change', ButtonStateManager.updateAllButtons);
                    checkbox.addEventListener('change', () => {
                        SelectionManager.updateSelection('partial', checkbox);
                        ButtonStateManager.updateAllButtons();
                    });
                });
            },

            handleSelectAll(e, type) {
                const isChecked = e.target.checked;
                // Mettre à jour toutes les checkboxes visibles
                document.querySelectorAll(`.${type}-material-checkbox`).forEach(checkbox => {
                    checkbox.checked = isChecked;
                    SelectionManager.updateSelection(type, checkbox);
                });
                // Si on décoche "tout sélectionner", on vide complètement la sélection
                if (!isChecked) {
                    SelectionManager.clearSelections(type);
                }
            },

            initDataTable() {
                DataTablesManager.tables.partial = jQuery('#partialOrdersTable').DataTable({
                    responsive: true,
                    language: {
                        url: CONFIG.DATATABLES.LANGUAGE_URL
                    },
                    dom: CONFIG.DATATABLES.DOM,
                    buttons: CONFIG.DATATABLES.BUTTONS,
                    columnDefs: [{
                            orderable: false,
                            targets: [0, 8]
                        },
                        {
                            responsivePriority: 1,
                            targets: [3, 6]
                        }
                    ],
                    order: [
                        [4, 'desc']
                    ],
                    pageLength: 10,
                    drawCallback: () => {
                        // Réattacher les événements après le redessinage
                        document.querySelectorAll('.partial-material-checkbox').forEach(checkbox => {
                            checkbox.removeEventListener('change', ButtonStateManager.updateAllButtons);
                            checkbox.addEventListener('change', () => {
                                SelectionManager.updateSelection('partial', checkbox);
                                ButtonStateManager.updateAllButtons();
                            });
                        });
                        ButtonStateManager.updateAllButtons();
                    }
                });
            },

            async completeOrder(id, designation, remaining, unit, sourceTable = 'expression_dym') {
                let apiUrl = `${CONFIG.API_URLS.PARTIAL_ORDERS}?action=get_material_info&id=${id}`;
                if (sourceTable === 'besoins') {
                    apiUrl = `commandes-traitement/besoins/get_besoin_with_remaining.php?id=${id}`;
                }

                try {
                    const response = await fetch(apiUrl);
                    const materialInfo = await response.json();

                    if (materialInfo.success === false) {
                        throw new Error(materialInfo.message || 'Impossible de récupérer les informations du matériau');
                    }

                    this.showCompleteOrderModal(id, designation, remaining, unit, materialInfo, sourceTable);
                } catch (error) {
                    console.error("Erreur lors de la récupération des infos du matériau:", error);
                    Swal.fire({
                        title: 'Erreur de chargement',
                        text: 'Impossible de charger les données complètes du matériau. Voulez-vous continuer quand même?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Continuer',
                        cancelButtonText: 'Annuler'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.showCompleteOrderModal(id, designation, remaining, unit, {}, sourceTable);
                        }
                    });
                }
            },

            showCompleteOrderModal(id, designation, remaining, unit, materialInfo, sourceTable = 'expression_dym') {
                Swal.fire({
                    title: 'Compléter la commande',
                    html: `
                        <div class="text-left">
                            <p class="mb-4"><strong>Désignation :</strong> ${designation}</p>
                            <p class="mb-4"><strong>Quantité restante :</strong> ${remaining} ${unit}</p>
                            
                            <div class="mb-4">
                                <label for="quantity" class="block text-sm font-medium text-gray-700">Quantité à commander :</label>
                                <input type="number" id="quantity" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" 
                                    value="${remaining}" min="0.01" max="${remaining}" step="0.01">
                            </div>
                            
                            <div class="mb-4">
                                <label for="supplier" class="block text-sm font-medium text-gray-700">Fournisseur :</label>
                                <input type="text" id="supplier" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2"
                                    value="${materialInfo.fournisseur || ''}">
                                <div id="supplier-suggestions-partial" class="absolute w-full bg-white mt-1 shadow-lg rounded-md z-10 max-h-60 overflow-y-auto hidden">
                                </div>
                                <div class="mt-2 text-xs text-blue-600">
                                    <a href="../fournisseurs/fournisseurs.php" target="_blank" class="flex items-center">
                                        <span class="material-icons text-sm mr-1">add_circle</span>
                                        Gérer les fournisseurs
                                    </a>
                                </div>
                            </div>
                            
                            <!-- CORRECTION : Champ Mode de paiement -->
                            <div class="mb-4">
                                <label for="payment-method" class="block text-sm font-medium text-gray-700">Mode de paiement <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <select id="payment-method" required
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Sélectionnez un mode de paiement</option>
                                        <!-- Les options seront chargées dynamiquement -->
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                        <span class="material-icons text-gray-400">payment</span>
                                    </div>
                                </div>
                                <div class="mt-1 text-xs text-gray-600" id="payment-method-description-partial">
                                    <!-- Description du mode de paiement sélectionné -->
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="price" class="block text-sm font-medium text-gray-700">Prix unitaire (FCFA) :</label>
                                <input type="number" id="price" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" 
                                    min="0.01" step="0.01" value="${materialInfo.prix_unitaire || ''}">
                            </div>
                            
                            <input type="hidden" id="source_table" value="${sourceTable}">
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Commander',
                    cancelButtonText: 'Annuler',
                    showLoaderOnConfirm: true,
                    didOpen: () => {
                        // Initialiser l'autocomplétion des fournisseurs
                        PartialOrdersManager.initPartialSupplierAutocomplete();
                        // CORRECTION : Charger et initialiser les modes de paiement
                        PartialOrdersManager.initPartialPaymentMethods();
                        // Suggérer un fournisseur si absent
                        if (!materialInfo.fournisseur) {
                            PartialOrdersManager.suggestSupplier(designation);
                        }
                    },
                    preConfirm: () => {
                        return PartialOrdersManager.handleOrderCompletion(id, remaining, sourceTable);
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then(result => {
                    if (result.isConfirmed) {
                        // Gérer le téléchargement du PDF si disponible
                        if (result.value.pdf_url) {
                            window.open(result.value.pdf_url, '_blank');
                        }
                        Swal.fire({
                            title: 'Succès !',
                            text: 'Commande enregistrée avec succès' + (result.value.pdf_url ?
                                ' et le bon de commande est en cours de téléchargement.' : '.'),
                            icon: 'success',
                            timer: 3000,
                            showConfirmButton: false
                        }).then(() => {
                            PartialOrdersManager.load(false);
                        });
                    }
                });
            },

            /**
             * Initialise les modes de paiement pour la modal de complétion - CORRIGÉE
             */
            async initPartialPaymentMethods() {
                try {
                    // CORRECTION : Utiliser PaymentMethodsManager
                    if (!PaymentMethodsManager.isLoaded) {
                        await PaymentMethodsManager.init();
                    }

                    PaymentMethodsManager.populatePaymentSelect('payment-method');

                    // Configurer les événements de changement
                    const paymentSelect = document.getElementById('payment-method');
                    const paymentDescription = document.getElementById('payment-method-description-partial');

                    if (paymentSelect && paymentDescription) {
                        paymentSelect.addEventListener('change', function() {
                            PaymentMethodsManager.updatePaymentDescription('payment-method-description-partial', this.value);
                        });
                    }
                } catch (error) {
                    console.error('Erreur lors de l\'initialisation des modes de paiement:', error);
                }
            },

            async handleOrderCompletion(id, remaining, sourceTable) {
                const quantity = document.getElementById('quantity').value;
                const supplier = document.getElementById('supplier').value;
                const price = document.getElementById('price').value;
                // CORRECTION : récupérer le mode de paiement
                const paymentMethod = document.getElementById('payment-method').value;

                // CORRECTION : Validation incluant le mode de paiement
                if (!this.validateOrder(quantity, remaining, supplier, price, paymentMethod)) {
                    return false;
                }

                try {
                    // Vérifier le fournisseur
                    const fournisseurResult = await FournisseursModule.checkAndCreate(supplier);

                    // Préparer les données
                    const formData = new FormData();
                    formData.append('action', 'complete_partial_order');
                    formData.append('material_id', id);
                    formData.append('quantite_commande', quantity);
                    formData.append('fournisseur', supplier);
                    formData.append('prix_unitaire', price);
                    formData.append('payment_method', paymentMethod); // CORRECTION
                    formData.append('source_table', sourceTable);

                    if (fournisseurResult.newFournisseur) {
                        formData.append('create_fournisseur', '1');
                    }

                    // Déterminer l'URL de l'API
                    const apiUrl = sourceTable === 'besoins' ?
                        'commandes-traitement/besoins/complete_besoin_partial.php' :
                        CONFIG.API_URLS.PARTIAL_ORDERS;

                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error('Erreur réseau');
                    }

                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.message || 'Erreur lors de l\'enregistrement de la commande');
                    }

                    return data;
                } catch (error) {
                    Swal.showValidationMessage(`Erreur: ${error.message}`);
                }
            },

            validateOrder(quantity, maxQuantity, supplier, price, paymentMethod) {
                if (!quantity || parseFloat(quantity) <= 0 || parseFloat(quantity) > parseFloat(maxQuantity)) {
                    Swal.showValidationMessage(`Veuillez saisir une quantité valide (entre 0 et ${maxQuantity})`);
                    return false;
                }

                if (!supplier.trim()) {
                    Swal.showValidationMessage('Veuillez indiquer un fournisseur');
                    return false;
                }

                if (!price || parseFloat(price) <= 0) {
                    Swal.showValidationMessage('Veuillez saisir un prix unitaire valide');
                    return false;
                }

                // CORRECTION : validation du mode de paiement
                if (!PaymentMethodsManager.validatePaymentMethod(paymentMethod)) {
                    return false;
                }

                return true;
            },

            initPartialSupplierAutocomplete() {
                const input = document.getElementById('supplier');
                const suggestions = document.getElementById('supplier-suggestions-partial');

                if (!input || !suggestions) return;

                input.addEventListener('input', function() {
                    const value = this.value.toLowerCase().trim();

                    suggestions.innerHTML = '';

                    if (value.length < 2) {
                        suggestions.classList.add('hidden');
                        return;
                    }

                    const matches = AppState.suppliersList
                        .filter(f => f.toLowerCase().includes(value))
                        .slice(0, 8);

                    if (matches.length > 0) {
                        suggestions.classList.remove('hidden');
                        matches.forEach(supplier => {
                            const div = document.createElement('div');
                            div.className = 'p-2 hover:bg-gray-100 cursor-pointer border-b';

                            // Mettre en évidence la partie correspondante
                            const index = supplier.toLowerCase().indexOf(value);
                            if (index !== -1) {
                                const before = supplier.substring(0, index);
                                const match = supplier.substring(index, index + value.length);
                                const after = supplier.substring(index + value.length);
                                div.innerHTML = `${Utils.escapeHtml(before)}<strong>${Utils.escapeHtml(match)}</strong>${Utils.escapeHtml(after)}`;
                            } else {
                                div.textContent = supplier;
                            }

                            div.onclick = () => {
                                input.value = supplier;
                                suggestions.innerHTML = '';
                                suggestions.classList.add('hidden');
                            };

                            suggestions.appendChild(div);
                        });

                        // Option pour créer un nouveau fournisseur
                        const createDiv = document.createElement('div');
                        createDiv.className = 'p-2 hover:bg-gray-100 cursor-pointer text-blue-600 font-medium';
                        createDiv.innerHTML = `<span class="material-icons text-sm mr-1 align-middle">add</span> Créer "${value}"`;
                        createDiv.onclick = () => {
                            input.value = value;
                            suggestions.innerHTML = '';
                            suggestions.classList.add('hidden');
                        };
                        suggestions.appendChild(createDiv);
                    } else {
                        suggestions.classList.add('hidden');
                    }
                });

                // Masquer les suggestions lors d'un clic en dehors
                document.addEventListener('click', (e) => {
                    if (e.target !== input && !suggestions.contains(e.target)) {
                        suggestions.classList.add('hidden');
                    }
                });
            },

            async suggestSupplier(designation) {
                try {
                    const response = await fetch(`get_suggested_fournisseur.php?designation=${encodeURIComponent(designation)}`);
                    const data = await response.json();
                    const supplierInput = document.getElementById('supplier');
                    if (supplierInput && data && data.fournisseur) {
                        supplierInput.value = data.fournisseur;
                    }
                } catch (error) {
                    console.error('Erreur lors de la suggestion de fournisseur:', error);
                }
            },

            async viewDetails(id, sourceTable = 'expression_dym') {
                // Afficher un loader
                Swal.fire({
                    title: 'Chargement...',
                    text: 'Récupération des détails de la commande',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Choisir l'URL de l'API
                const apiUrl = sourceTable === 'besoins' ?
                    `commandes-traitement/besoins/get_besoin_partial_details.php?id=${id}` :
                    `${CONFIG.API_URLS.PARTIAL_ORDERS}?action=get_partial_details&id=${id}`;

                try {
                    const response = await fetch(apiUrl);
                    const data = await response.json();

                    if (data.success) {
                        this.showDetailsModal(data, sourceTable);
                    } else {
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message || 'Impossible de récupérer les détails de la commande',
                            icon: 'error'
                        });
                    }
                } catch (error) {
                    console.error('Erreur lors de la récupération des détails:', error);
                    Swal.fire({
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la récupération des détails',
                        icon: 'error'
                    });
                }
            },

            showDetailsModal(data, sourceTable) {
                const material = data.material;
                const linkedOrders = data.linked_orders || [];

                // Adapter les propriétés selon la source
                let designation, unit, restante, initialQty;
                if (sourceTable === 'besoins') {
                    designation = material.designation_article || 'Sans désignation';
                    unit = material.caracteristique || '';
                    restante = parseFloat(material.qt_restante || 0);
                    initialQty = parseFloat(material.qt_demande || 0);
                } else {
                    designation = material.designation || 'Sans désignation';
                    unit = material.unit || '';
                    restante = parseFloat(material.qt_restante || 0);
                    initialQty = parseFloat(material.initial_qt_acheter ||
                        parseFloat(material.qt_acheter) + parseFloat(material.qt_restante) || 0);
                }

                const orderedQty = initialQty - restante;
                const progress = initialQty > 0 ? Math.round((orderedQty / initialQty) * 100) : 0;

                // Fonction pour obtenir le label et l'icône du mode de paiement
                const getPaymentMethodInfo = (paymentId) => {
                    if (!paymentId) return {
                        label: 'Non spécifié',
                        icon: 'help_outline',
                        class: 'text-gray-500'
                    };

                    const id = parseInt(paymentId);
                    const paymentMethods = {
                        1: {
                            label: 'Espèces',
                            icon: 'payments',
                            class: 'text-green-600'
                        },
                        2: {
                            label: 'Chèque',
                            icon: 'receipt_long',
                            class: 'text-purple-600'
                        },
                        3: {
                            label: 'Virement bancaire',
                            icon: 'account_balance',
                            class: 'text-blue-600'
                        },
                        4: {
                            label: 'Carte de crédit',
                            icon: 'credit_card',
                            class: 'text-red-600'
                        },
                        6: {
                            label: 'Crédit fournisseur',
                            icon: 'factory',
                            class: 'text-orange-600'
                        },
                        7: {
                            label: 'Mobile Money',
                            icon: 'phone_android',
                            class: 'text-orange-600'
                        },
                        8: {
                            label: 'Traite',
                            icon: 'description',
                            class: 'text-indigo-600'
                        },
                        9: {
                            label: 'Autre',
                            icon: 'more_horiz',
                            class: 'text-gray-600'
                        }
                    };

                    return paymentMethods[id] || {
                        label: `Mode ${id}`,
                        icon: 'payment',
                        class: 'text-gray-600'
                    };
                };

                // Préparer le tableau des commandes liées avec les modes de paiement
                const ordersHtml = linkedOrders.length > 0 ?
                    linkedOrders.map(order => {
                        const paymentInfo = getPaymentMethodInfo(order.mode_paiement);
                        return `
                            <tr>
                                <td class="border px-4 py-2">${new Date(order.date_achat).toLocaleDateString('fr-FR')}</td>
                                <td class="border px-4 py-2">${Utils.formatQuantity(order.quantity)} ${unit}</td>
                                <td class="border px-4 py-2">${Utils.formatQuantity(order.prix_unitaire)} FCFA</td>
                                <td class="border px-4 py-2">${order.fournisseur || '-'}</td>
                                <td class="border px-4 py-2">
                                    <div class="flex items-center ${paymentInfo.class}">
                                        <span class="material-icons text-sm mr-1">${paymentInfo.icon}</span>
                                        <span class="text-xs">${paymentInfo.label}</span>
                                    </div>
                                </td>
                                <td class="border px-4 py-2">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium ${order.status === 'reçu' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}">
                                        ${order.status}
                                    </span>
                                </td>
                            </tr>
                        `;
                    }).join('') :
                    `
                        <tr>
                            <td colspan="6" class="border px-4 py-2 text-center text-gray-500">
                                Aucune commande liée trouvée.
                            </td>
                        </tr>
                    `;

                // Afficher la modal avec SweetAlert2
                Swal.fire({
                    title: 'Détails de la commande partielle',
                    html: `
                        <div class="text-left">
                            <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                                <h3 class="font-bold text-lg mb-2">${designation}</h3>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-gray-600">Projet:</span>
                                        <span class="font-medium ml-2">${sourceTable === 'besoins' ? 'PETROCI' : (material.code_projet || 'N/A')}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Quantité initiale:</span>
                                        <span class="font-medium ml-2">${Utils.formatQuantity(initialQty)} ${unit}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Quantité commandée:</span>
                                        <span class="font-medium ml-2">${Utils.formatQuantity(orderedQty)} ${unit}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Quantité restante:</span>
                                        <span class="font-medium ml-2 ${restante > 0 ? 'text-orange-600' : 'text-green-600'}">${Utils.formatQuantity(restante)} ${unit}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium">Progression:</span>
                                    <span class="text-sm font-medium">${progress}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full transition-all duration-300" style="width: ${progress}%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <p class="font-medium mb-2">Historique des commandes liées :</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white border text-sm">
                                        <thead>
                                            <tr>
                                                <th class="border px-4 py-2 bg-gray-50 text-xs">Date</th>
                                                <th class="border px-4 py-2 bg-gray-50 text-xs">Quantité</th>
                                                <th class="border px-4 py-2 bg-gray-50 text-xs">Prix unitaire</th>
                                                <th class="border px-4 py-2 bg-gray-50 text-xs">Fournisseur</th>
                                                <th class="border px-4 py-2 bg-gray-50 text-xs">Mode de paiement</th>
                                                <th class="border px-4 py-2 bg-gray-50 text-xs">Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${ordersHtml}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="flex justify-center">
                                <button onclick="completePartialOrder('${material.id}', '${Utils.escapeString(designation)}', ${restante}, '${unit}', '${sourceTable}')" 
                                    class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded mr-2 flex items-center">
                                    <span class="material-icons mr-1">add_shopping_cart</span>
                                    Commander le restant
                                </button>
                            </div>
                        </div>
                    `,
                    width: 900,
                    confirmButtonText: 'Fermer',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                });
            }
        };

        /**
         * Gestionnaire des substitutions
         */
        const SubstitutionManager = {
            setupProductAutocomplete() {
                const productInput = document.getElementById('substitute-product');
                const originalProductInput = document.getElementById('original-product');
                const suggestionsDiv = document.getElementById('product-suggestions');

                if (!productInput || !suggestionsDiv) return;

                productInput.addEventListener('input', async function() {
                    const searchTerm = this.value.trim();
                    const originalProduct = originalProductInput.value.trim();

                    if (searchTerm.length < 2) {
                        suggestionsDiv.innerHTML = '';
                        suggestionsDiv.classList.remove('active');
                        return;
                    }

                    try {
                        const response = await fetch(`${CONFIG.API_URLS.PRODUCT_SUGGESTIONS}?term=${encodeURIComponent(searchTerm)}&original=${encodeURIComponent(originalProduct)}`);
                        const products = await response.json();

                        suggestionsDiv.innerHTML = '';

                        if (products.length > 0) {
                            suggestionsDiv.classList.add('active');
                            suggestionsDiv.style.display = 'block';

                            products.forEach(product => {
                                const div = document.createElement('div');
                                div.className = 'product-suggestion';

                                if (product.category_name) {
                                    div.innerHTML = `${product.product_name} <span class="text-xs text-gray-500">(${product.category_name})</span>`;
                                } else {
                                    div.textContent = product.product_name;
                                }

                                div.onclick = () => {
                                    productInput.value = product.product_name;
                                    suggestionsDiv.innerHTML = '';
                                    suggestionsDiv.classList.remove('active');
                                    suggestionsDiv.style.display = 'none';
                                };

                                suggestionsDiv.appendChild(div);
                            });
                        } else {
                            suggestionsDiv.classList.remove('active');
                            suggestionsDiv.style.display = 'none';
                        }
                    } catch (error) {
                        console.error('Erreur lors de la récupération des suggestions:', error);
                        suggestionsDiv.classList.remove('active');
                        suggestionsDiv.style.display = 'none';
                    }
                });

                // Masquer les suggestions lors d'un clic en dehors
                document.addEventListener('click', (e) => {
                    if (e.target !== productInput && !suggestionsDiv.contains(e.target)) {
                        suggestionsDiv.classList.remove('active');
                        suggestionsDiv.style.display = 'none';
                    }
                });
            },

            validateForm() {
                const originalProduct = document.getElementById('original-product').value;
                const substituteProduct = document.getElementById('substitute-product').value;
                const reason = document.getElementById('substitution-reason').value;
                const otherReason = document.getElementById('other-reason').value;

                // Vérifier que le produit de substitution est différent
                if (substituteProduct.trim() === originalProduct.trim()) {
                    Swal.fire({
                        title: 'Erreur de validation',
                        text: 'Le produit de substitution doit être différent du produit original.',
                        icon: 'error'
                    });
                    return false;
                }

                // Vérifier que le produit n'est pas vide
                if (!substituteProduct.trim()) {
                    Swal.fire({
                        title: 'Erreur de validation',
                        text: 'Veuillez saisir un produit de substitution.',
                        icon: 'error'
                    });
                    return false;
                }

                // Vérifier la raison
                if (!reason) {
                    Swal.fire({
                        title: 'Erreur de validation',
                        text: 'Veuillez sélectionner une raison pour la substitution.',
                        icon: 'error'
                    });
                    return false;
                }

                // Si "Autre raison" est sélectionnée
                if (reason === 'autre' && !otherReason.trim()) {
                    Swal.fire({
                        title: 'Erreur de validation',
                        text: 'Veuillez préciser la raison de la substitution.',
                        icon: 'error'
                    });
                    return false;
                }

                return true;
            },

            async handleSubmit(e) {
                e.preventDefault();

                if (!this.validateForm()) {
                    return;
                }

                const form = e.target;
                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;

                // Afficher un indicateur de chargement
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="material-icons spin">autorenew</span> Traitement...';

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Message de confirmation
                        Swal.fire({
                            title: 'Produit substitué avec succès',
                            html: `
                                <div class="text-left">
                                    <p><strong>Produit original:</strong> ${data.data.original_product} (${data.data.original_unit})</p>
                                    <p><strong>Remplacé par:</strong> ${data.data.new_product} (${data.data.new_unit})</p>
                                    <p><strong>Quantité transférée:</strong> ${data.data.quantity_transferred}</p>
                                </div>
                            `,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la substitution',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }
        };

        /**
         * Gestionnaire des exportations
         */
        const ExportManager = {
            exportPartialOrdersToExcel() {
                window.location.href = `${CONFIG.API_URLS.PARTIAL_ORDERS}?action=export_remaining&format=excel`;
            }
        };

        /**
         * Gestionnaire des notifications
         */
        const NotificationsManager = {
            updateMaterialsNotification(data) {
                const notificationBadge = document.querySelector('.notification-badge');
                const tooltipText = document.querySelector('.tooltiptext');

                if (notificationBadge && tooltipText) {
                    notificationBadge.textContent = data.total;
                    tooltipText.textContent = `Il y a ${data.total} matériaux à commander`;

                    if (data.newCount > 0) {
                        notificationBadge.classList.add('bg-red-600');
                        tooltipText.textContent += ` (${data.newCount} nouveaux)`;
                    }
                }
            },

            updatePartialOrdersStats(stats) {
                if (!stats) return;

                const updates = {
                    'stat-total-partial': stats.total_materials || 0,
                    'stat-remaining-qty': Utils.formatQuantity(stats.total_remaining || 0),
                    'stat-projects-count': stats.total_projects || 0,
                    'stat-progress': `${stats.global_progress || 0}%`
                };

                Object.entries(updates).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.textContent = value;
                    }
                });

                // Mise à jour de la barre de progression
                const progressBar = document.getElementById('progress-bar');
                if (progressBar) {
                    progressBar.style.width = `${stats.global_progress || 0}%`;
                }
            },

            updateCanceledOrdersStats(stats) {
                if (!stats) return;

                const updates = {
                    'total-canceled-count': stats.total_canceled || 0,
                    'projects-canceled-count': stats.projects_count || 0
                };

                Object.entries(updates).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.textContent = value;
                    }
                });

                // Mise à jour de la date
                if (document.getElementById('last-canceled-date') && stats.last_canceled_date) {
                    const date = new Date(stats.last_canceled_date);
                    document.getElementById('last-canceled-date').textContent = date.toLocaleDateString('fr-FR');
                }

                // Mise à jour du compteur dans l'onglet
                const canceledTabCounter = document.querySelector('#tab-canceled .rounded-full');
                if (canceledTabCounter) {
                    canceledTabCounter.textContent = stats.total_canceled || 0;
                }
            }
        };

        /**
         * Vérificateur de validation des commandes
         */
        const OrderValidationChecker = {
            async check() {
                try {
                    const response = await fetch(CONFIG.API_URLS.UPDATE_ORDER_STATUS + '?debug=1');
                    const data = await response.json();

                    if (data.success) {
                        if (data.updated_count > 0) {
                            // Afficher les détails des mises à jour
                            let message = `${data.updated_count} commande(s) validée(s) par la finance :\n\n`;
                            if (data.processed_items) {
                                data.processed_items.forEach(item => {
                                    message += `• ${item.designation} (BC: ${item.bon_commande})\n`;
                                });
                            }

                            Swal.fire({
                                title: 'Validations Finance',
                                text: message,
                                icon: 'success',
                                confirmButtonText: 'Actualiser la page'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.reload();
                                }
                            });
                        }
                    } else {
                        console.error("Erreur lors de la vérification des validations:", data.message);
                        if (data.debug_log) {
                            console.log("Debug log:", data.debug_log);
                        }
                    }
                } catch (error) {
                    console.error('Erreur lors de la vérification des validations finance:', error);
                }
            }
        };

        /**
         * Utilitaires
         */
        const Utils = {
            escapeHtml(str) {
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            },

            escapeString(str) {
                return str.replace(/[\\'\"]/g, function(match) {
                    return '\\' + match;
                });
            },

            formatQuantity(qty) {
                if (qty === null || qty === undefined) return '0.00';
                return parseFloat(qty).toLocaleString('fr-FR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            },

            formatPrice(price) {
                return parseFloat(price).toLocaleString('fr-FR', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                });
            }
        };

        /**
         * Fonctions globales exposées
         */
        window.openPurchaseModal = (expressionId, designation, quantity, unit, fournisseur = '') => {
            ModalManager.openPurchase(expressionId, designation, quantity, unit, fournisseur);
        };

        window.closePurchaseModal = () => {
            ModalManager.close(document.getElementById('purchase-modal'));
        };

        window.closeBulkPurchaseModal = () => {
            ModalManager.close(document.getElementById('bulk-purchase-modal'));
        };

        window.openSubstitutionModal = (materialId, designation, expressionId, sourceTable = 'expression_dym') => {
            ModalManager.openSubstitution(materialId, designation, expressionId, sourceTable);
        };

        window.closeSubstitutionModal = () => {
            ModalManager.close(document.getElementById('substitution-modal'));
        };

        window.generateBonCommande = (expressionId) => {
            const downloadUrl = `${CONFIG.API_URLS.BON_COMMANDE}?id=${expressionId}`;
            window.open(downloadUrl, '_blank');
            Swal.fire({
                title: 'Bon de commande généré!',
                text: 'Le bon de commande a été téléchargé et sauvegardé dans les archives.',
                icon: 'success',
                timer: 3000,
                showConfirmButton: false
            });
        };

        window.completePartialOrder = (id, designation, remaining, unit, sourceTable = 'expression_dym') => {
            PartialOrdersManager.completeOrder(id, designation, remaining, unit, sourceTable);
        };

        window.viewPartialOrderDetails = (id, sourceTable = 'expression_dym') => {
            PartialOrdersManager.viewDetails(id, sourceTable);
        };

        window.cancelSingleOrder = (id, expressionId, designation, sourceTable = 'expression_dym') => {
            CancelManager.cancelSingleOrder(id, expressionId, designation, sourceTable);
        };

        window.cancelPendingMaterial = (id, expressionId, designation, sourceTable = 'expression_dym') => {
            CancelManager.cancelPendingMaterial(id, expressionId, designation, sourceTable);
        };

        window.viewStockDetails = (designation) => {
            window.open(`../stock/inventory.php?search=${encodeURIComponent(designation)}`, '_blank');
        };

        window.openEditOrderModal = (orderId, expressionId, designation, sourceTable, quantity, unit, price, supplier) => {
            EditOrderManager.openModal(orderId, expressionId, designation, sourceTable, quantity, unit, price, supplier);
        };

        window.closeEditOrderModal = () => {
            EditOrderManager.closeModal();
        };

        window.closeOrderDetailsModal = () => {
            const modal = document.getElementById('order-details-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        };

        // Configurer l'intervalle de vérification (toutes les 5 minutes)
        window.checkOrderStatusInterval = setInterval(() => {
            OrderValidationChecker.check();
        }, CONFIG.REFRESH_INTERVALS.CHECK_VALIDATION || 5 * 60 * 1000);

        /**
         * Point d'entrée principal
         */
        document.addEventListener('DOMContentLoaded', () => {
            AchatsMateriauxApp.init();

            const editOrderForm = document.getElementById('edit-order-form');
            if (editOrderForm) {
                editOrderForm.addEventListener('submit', (e) => {
                    EditOrderManager.handleSubmit(e);
                });
            }
        });

        console.log('✅ Script achats_materiaux.js chargé avec support complet des modes de paiement par ID');
    </script>
</body>

</html>