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
     AND (ed.valide_achat = 'pas validé' OR ed.valide_achat IS NULL)
     AND ed.valide_achat != 'en_cours'
     AND " . getFilteredDateCondition('ed.created_at') . ")
    +
    (SELECT COUNT(*) 
     FROM besoins b
     WHERE b.qt_acheter IS NOT NULL 
     AND b.qt_acheter > 0 
     AND (b.achat_status = 'pas validé' OR b.achat_status IS NULL)
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
            p.product_image,
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
        LEFT JOIN products p ON LOWER(p.product_name) = LOWER(ed.designation)
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
            p.product_image,
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
        LEFT JOIN products p ON p.id = b.product_id
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

        /* Style pour les images de produit */
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }

        .product-image-placeholder {
            width: 50px;
            height: 50px;
            background-color: #f3f4f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e5e7eb;
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
                                    <th>Image</th>
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
                                            $isPending = ($material['valide_achat'] === 'pas validé' || $material['valide_achat'] === NULL);
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
                                                <td>
                                                    <?php if (!empty($material['product_image']) && file_exists('../' . ltrim($material['product_image'], '/'))): ?>
                                                        <img src="../<?= htmlspecialchars($material['product_image']) ?>" alt="<?= htmlspecialchars($designation) ?>" class="product-image">
                                                    <?php else: ?>
                                                        <div class="product-image-placeholder">
                                                            <span class="material-icons text-gray-400">inventory_2</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
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
                                                <td colspan="11">Erreur de chargement du matériau</td>
                                            </tr>
                                    <?php
                                        }
                                    }
                                } catch (Exception $globalException) {
                                    error_log("Erreur globale lors du chargement des matériaux : " . $globalException->getMessage());
                                    ?>
                                    <tr>
                                        <td colspan="11" class="text-center text-red-600">
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
                                         ORDER BY po.generated_at DESC LIMIT 1) as bon_commande_id,
                                                                
                                        (SELECT po.order_number 
                                         FROM purchase_orders po 
                                         WHERE (BINARY po.expression_id = BINARY main_data.expression_id
                                                OR JSON_CONTAINS(po.related_expressions, CONCAT('\"', main_data.expression_id, '\"')))
                                         ORDER BY po.generated_at DESC LIMIT 1) as bon_commande_number,
                                                                
                                        (SELECT po.signature_finance 
                                         FROM purchase_orders po 
                                         WHERE (BINARY po.expression_id = BINARY main_data.expression_id 
                                                OR JSON_CONTAINS(po.related_expressions, CONCAT('\"', main_data.expression_id, '\"')))
                                         ORDER BY po.generated_at DESC LIMIT 1) as signature_finance,
                                                                
                                        (SELECT po.user_finance_id 
                                         FROM purchase_orders po 
                                         WHERE (BINARY po.expression_id = BINARY main_data.expression_id
                                                OR JSON_CONTAINS(po.related_expressions, CONCAT('\"', main_data.expression_id, '\"')))
                                         ORDER BY po.generated_at DESC LIMIT 1) as user_finance_id,
                                                                
                                        (SELECT po.file_path 
                                         FROM purchase_orders po 
                                         WHERE (BINARY po.expression_id = BINARY main_data.expression_id
                                                OR JSON_CONTAINS(po.related_expressions, CONCAT('\"', main_data.expression_id, '\"')))
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
                                            COALESCE(
                                                NULLIF(ed.fournisseur, ''),
                                                NULLIF((SELECT am.fournisseur
                                                        FROM achats_materiaux am
                                                        WHERE BINARY am.expression_id = BINARY ed.idExpression
                                                        AND BINARY am.designation = BINARY ed.designation
                                                        AND am.fournisseur IS NOT NULL
                                                        AND am.fournisseur != ''
                                                        ORDER BY am.date_achat DESC LIMIT 1), ''),
                                                'Non spécifié'
                                            ) as fournisseur,
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
                                                <td colspan="11">Erreur de chargement du matériau</td>
                                            </tr>
                                    <?php
                                        }
                                    }
                                } catch (Exception $globalException) {
                                    error_log("Erreur globale lors du chargement des matériaux : " . $globalException->getMessage());
                                    ?>
                                    <tr>
                                        <td colspan="11" class="text-center text-red-600">
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

    <!-- Modal pour les achats en gros - VERSION 2.0 -->
    <div id="bulk-purchase-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="modal-content relative top-10 mx-auto p-6 border shadow-2xl rounded-xl bg-white" style="max-width: 900px; margin-top: 2rem;">

            <!-- En-tête moderne du modal -->
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-lg mr-4">
                        <span class="material-icons text-blue-600 text-xl">shopping_cart</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Achat groupé de matériaux</h2>
                        <p class="text-sm text-gray-600 mt-1">Commande multiple avec un seul fournisseur et mode de paiement</p>
                    </div>
                </div>
                <button onclick="closeBulkPurchaseModal()"
                    class="text-gray-400 hover:text-gray-600 transition-colors p-2 hover:bg-gray-100 rounded-lg">
                    <span class="material-icons">close</span>
                </button>
            </div>

            <form id="bulk-purchase-form" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="bulk_purchase" value="1">

                <div class="space-y-6">
                    <!-- Container for selected materials info - Amélioré -->
                    <div id="selected-materials-container" class="bg-gray-50 p-4 rounded-lg border">
                        <h3 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <span class="material-icons text-sm mr-2 text-gray-600">inventory_2</span>
                            Matériaux sélectionnés
                        </h3>
                        <div id="selected-materials-list" class="space-y-2">
                            <!-- Will be populated dynamically -->
                        </div>
                        <div id="selected-materials-summary" class="mt-3 pt-3 border-t border-gray-200 text-sm text-gray-600">
                            <!-- Résumé dynamique -->
                        </div>
                    </div>

                    <!-- Informations fournisseur - Améliorées -->
                    <div class="space-y-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="fournisseur-bulk">
                            Fournisseur <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                                id="fournisseur-bulk" name="fournisseur" type="text" required
                                placeholder="Saisissez ou sélectionnez un fournisseur" autocomplete="off">
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <span class="material-icons text-gray-400">business</span>
                            </div>

                            <!-- Suggestions améliorées -->
                            <div id="fournisseurs-suggestions-bulk"
                                class="absolute w-full bg-white mt-1 shadow-xl rounded-lg z-50 max-h-60 overflow-y-auto hidden border border-gray-200">
                                <!-- Suggestions will be populated here -->
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-xs mt-2">
                            <span class="text-gray-500">Tapez au moins 2 caractères pour voir les suggestions</span>
                            <a href="../fournisseurs/fournisseurs.php" target="_blank"
                                class="text-blue-600 hover:text-blue-800 flex items-center transition-colors">
                                <span class="material-icons text-sm mr-1">add_circle</span>
                                Gérer les fournisseurs
                            </a>
                        </div>
                    </div>

                    <!-- MODE DE PAIEMENT - SECTION COMPLÈTEMENT MISE À JOUR -->
                    <div class="space-y-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="payment-method-bulk">
                            Mode de paiement <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <select id="payment-method-bulk" name="payment_method" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 appearance-none">
                                <option value="">Sélectionnez un mode de paiement</option>
                                <!-- Les options seront chargées dynamiquement par PaymentMethodsManager v2.0 -->
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <span class="material-icons text-gray-400">payment</span>
                            </div>

                            <!-- Indicateur de chargement -->
                            <div id="payment-method-bulk-loading" class="absolute inset-y-0 right-10 flex items-center pr-3 hidden">
                                <svg class="animate-spin h-4 w-4 text-blue-500" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Description du mode de paiement avec support icon_path - NOUVEAU -->
                        <div class="mt-2 p-3 bg-gray-50 rounded-lg border transition-all duration-300"
                            id="payment-method-description" style="display: none;">
                            <!-- Contenu généré dynamiquement avec icône (icon_path) et description -->
                            <div class="flex items-start space-x-3">
                                <div id="payment-method-icon-container" class="flex-shrink-0">
                                    <!-- Icône du mode de paiement -->
                                </div>
                                <div class="flex-1">
                                    <div id="payment-method-label" class="font-medium text-gray-800"></div>
                                    <div id="payment-method-desc" class="text-sm text-gray-600 mt-1"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Pro-forma - Section améliorée -->
                    <div class="space-y-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="proforma-upload">
                            Pro-forma (Optionnel)
                            <span class="text-xs text-gray-500 font-normal ml-2">PDF, DOC, XLS, JPG, PNG (Max 10MB)</span>
                        </label>
                        <div class="relative">
                            <input type="file"
                                id="proforma-upload"
                                name="proforma_file"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 file:mr-3 file:py-2 file:px-4 file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <span class="material-icons text-gray-400">attach_file</span>
                            </div>
                        </div>

                        <!-- Informations sur le fichier sélectionné - Améliorées -->
                        <div id="proforma-file-info" class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg hidden">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <span class="material-icons text-sm mr-2 text-green-600">check_circle</span>
                                    <div>
                                        <div id="proforma-file-name" class="font-medium text-green-800"></div>
                                        <div id="proforma-file-size" class="text-xs text-green-600"></div>
                                    </div>
                                </div>
                                <button type="button" id="proforma-remove-file"
                                    class="text-red-600 hover:text-red-800 hover:bg-red-50 p-1 rounded transition-colors">
                                    <span class="material-icons text-sm">close</span>
                                </button>
                            </div>
                        </div>

                        <!-- Message d'aide - Amélioré -->
                        <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-start">
                                <span class="material-icons text-sm mr-2 text-blue-600 mt-0.5">info</span>
                                <div class="text-xs text-blue-700">
                                    <div class="font-medium">Information importante :</div>
                                    <div>Le pro-forma sera automatiquement associé à toutes les commandes de ce groupe</div>
                                </div>
                            </div>
                        </div>

                        <!-- Zone de progression d'upload - Améliorée -->
                        <div id="proforma-upload-progress" class="mt-3 hidden">
                            <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                <span id="proforma-progress-text">Upload en cours...</span>
                                <span id="proforma-progress-percent">0%</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2 overflow-hidden">
                                <div id="proforma-progress-bar"
                                    class="bg-blue-600 h-2 rounded-full transition-all duration-300 ease-out"
                                    style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Type de prix - Section améliorée -->
                    <div class="space-y-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="price-type">
                            Type de prix
                        </label>
                        <select id="price-type" name="price_type"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                            <option value="individual">Prix individuels (définir pour chaque matériau)</option>
                            <option value="common">Prix commun (même prix pour tous)</option>
                        </select>
                        <div class="text-xs text-gray-500 mt-1">
                            Choisissez si tous les matériaux ont le même prix ou des prix différents
                        </div>
                    </div>

                    <!-- Container prix commun - Amélioré -->
                    <div id="common-price-container" class="space-y-2 hidden">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="common-price">
                            Prix unitaire commun (FCFA) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                                id="common-price" name="common_price" type="number" step="0.01" min="0"
                                placeholder="Prix par unité pour tous les matériaux">
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <span class="text-gray-400 text-sm font-medium">FCFA</span>
                            </div>
                        </div>
                        <div id="common-price-total" class="text-sm text-blue-600 font-medium hidden">
                            <!-- Total calculé dynamiquement -->
                        </div>
                    </div>

                    <!-- Container prix individuels - Complètement repensé -->
                    <div id="individual-prices-container" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <span class="material-icons text-sm mr-2 text-gray-600">price_check</span>
                                Prix individuels
                            </h3>
                            <div class="text-sm text-gray-600">
                                <span id="individual-total-display" class="font-medium">Total: 0 FCFA</span>
                            </div>
                        </div>

                        <!-- Tableau responsive amélioré -->
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="overflow-x-auto max-h-80" style="max-height: 20rem;">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50 sticky top-0 z-10">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Produit
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Quantité
                                                <span class="text-blue-500 text-xs normal-case font-normal">(modifiable)</span>
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Unité
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Prix unitaire (FCFA)
                                                <span class="text-red-500">*</span>
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="individual-prices-tbody" class="bg-white divide-y divide-gray-200">
                                        <!-- Will be populated dynamically with JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Résumé global de la commande - NOUVEAU -->
                    <div id="bulk-order-summary" class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 p-4 rounded-lg hidden">
                        <h4 class="font-semibold text-blue-800 mb-3 flex items-center">
                            <span class="material-icons text-sm mr-2">calculate</span>
                            Résumé de la commande groupée
                        </h4>
                        <div id="bulk-summary-content" class="space-y-2 text-sm">
                            <!-- Contenu généré dynamiquement -->
                        </div>
                    </div>
                </div>

                <!-- Boutons d'action - Améliorés -->
                <div class="flex items-center justify-between pt-6 mt-6 border-t border-gray-200">
                    <button type="button"
                        class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all duration-200"
                        onclick="closeBulkPurchaseModal()">
                        <span class="flex items-center">
                            <span class="material-icons text-sm mr-2">close</span>
                            Annuler
                        </span>
                    </button>

                    <button type="submit" id="confirm-bulk-purchase"
                        class="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        disabled>
                        <span class="flex items-center">
                            <span class="material-icons text-sm mr-2">shopping_cart</span>
                            <span id="bulk-purchase-btn-text">Passer la commande</span>
                        </span>
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
    <script src="assets/js/achats-materiaux.js"></script>


</body>

</html>
