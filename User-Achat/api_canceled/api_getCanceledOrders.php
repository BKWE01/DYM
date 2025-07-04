<?php
// Service achat,"/DYM MANUFACTURE/expressions_besoins/User-Achat/api_canceled/api_getCanceledOrders.php" 

header('Content-Type: application/json');

// Désactiver l'affichage des erreurs pour éviter de corrompre la sortie JSON
ini_set('display_errors', 0);
error_reporting(0);

// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure le helper de date
try {
    if (file_exists('../../include/date_helper.php')) {
        include_once '../../include/date_helper.php';
    } else if (file_exists('../../includes/date_helper.php')) {
        include_once '../../includes/date_helper.php';
    } else {
        // Définir une fonction de secours si le fichier n'existe pas
        function getSystemStartDate()
        {
            return '2025-03-24'; // Valeur par défaut
        }
    }
} catch (Exception $e) {
    // Définir une fonction de secours en cas d'erreur
    function getSystemStartDate()
    {
        return '2025-03-24'; // Valeur par défaut
    }
}

try {
    // Récupérer la date de début du système
    $systemStartDate = getSystemStartDate();

    // Requête modifiée pour prendre en compte les deux sources (expression_dym et besoins)
    $query = "
    SELECT 
        co.id,
        co.order_id,
        co.project_id,
        co.designation,
        co.canceled_by,
        co.cancel_reason,
        co.original_status,
        co.is_partial,
        co.canceled_at,
        u.name AS canceled_by_name,
        ip.code_projet,
        ip.nom_client,
        /* Priorité aux sources de données: expression_dym > besoins > achats_materiaux */
        COALESCE(ed.quantity, b.qt_demande, CASE WHEN co.order_id = 0 THEN NULL ELSE am.quantity END) as quantity,
        COALESCE(ed.unit, b.caracteristique, CASE WHEN co.order_id = 0 THEN NULL ELSE am.unit END) as unit,
        COALESCE(ed.prix_unitaire, CASE WHEN co.order_id = 0 THEN NULL ELSE am.prix_unitaire END) as prix_unitaire,
        COALESCE(ed.fournisseur, CASE WHEN co.order_id = 0 THEN NULL ELSE am.fournisseur END) as fournisseur,
        p.product_image,
        /* Récupérer les infos pour les besoins système */
        d.client as demandeur_nom,
        d.service_demandeur,
        /* Identifier la source des données */
        CASE 
            WHEN ed.id IS NOT NULL THEN 'expression_dym' 
            WHEN b.id IS NOT NULL THEN 'besoins' 
            ELSE NULL 
        END as source_table
    FROM 
        (
            SELECT 
                co.id,
                co.order_id,
                co.project_id,
                co.designation,
                co.canceled_by,
                co.cancel_reason,
                co.original_status,
                co.is_partial,
                co.canceled_at,
                MAX(co.id) as max_id
            FROM 
                canceled_orders_log co
            WHERE 
                co.canceled_at >= :system_start_date
            GROUP BY 
                co.project_id, co.designation
        ) as co
    LEFT JOIN users_exp u ON co.canceled_by = u.id
    LEFT JOIN identification_projet ip ON co.project_id = ip.idExpression
    LEFT JOIN achats_materiaux am ON co.order_id = am.id
    /* Jointure avec expression_dym */
    LEFT JOIN expression_dym ed ON (co.project_id = ed.idExpression AND LOWER(co.designation) = LOWER(ed.designation))
    /* Jointure avec besoins (expressions système) */
    LEFT JOIN besoins b ON (co.project_id = b.idBesoin AND LOWER(co.designation) = LOWER(b.designation_article))
    /* Jointure avec demandeur pour les infos sur les besoins système */
    LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
    /* Récupérer l'image du produit */
    LEFT JOIN products p ON LOWER(p.product_name) = LOWER(co.designation)
    ORDER BY co.canceled_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':system_start_date', $systemStartDate);
    $stmt->execute();

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Requête pour les statistiques avec prise en compte des deux sources
    $statsQuery = "
    SELECT 
        COUNT(DISTINCT project_id, designation) as total_canceled,
        COUNT(DISTINCT project_id) as projects_count,
        MAX(canceled_at) as last_canceled_date,
        (
            SELECT COALESCE(SUM(
                CASE 
                    WHEN ed.prix_unitaire IS NOT NULL AND ed.quantity IS NOT NULL THEN 
                        CAST(ed.prix_unitaire AS DECIMAL(10,2)) * ed.quantity 
                    WHEN am.prix_unitaire IS NOT NULL AND am.quantity IS NOT NULL THEN 
                        CAST(am.prix_unitaire AS DECIMAL(10,2)) * am.quantity
                    ELSE 0 
                END), 0)
            FROM (
                SELECT DISTINCT project_id, designation, order_id
                FROM canceled_orders_log
                WHERE canceled_at >= :system_start_date
            ) co2
            LEFT JOIN achats_materiaux am ON co2.order_id = am.id
            LEFT JOIN expression_dym ed ON (co2.project_id = ed.idExpression AND LOWER(co2.designation) = LOWER(ed.designation))
            LEFT JOIN besoins b ON (co2.project_id = b.idBesoin AND LOWER(co2.designation) = LOWER(b.designation_article))
        ) as saved_value
    FROM canceled_orders_log
    WHERE canceled_at >= :system_start_date";

    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->bindParam(':system_start_date', $systemStartDate);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Formater les données pour le tableau DataTables
    $data = [];
    foreach ($orders as $order) {
        // Convertir les formats de date pour l'affichage
        $canceledAt = date('d/m/Y H:i', strtotime($order['canceled_at']));

        // Formater l'état original
        $originalStatus = $order['original_status'];
        switch ($originalStatus) {
            case 'en_attente':
                $statusText = 'En attente';
                $statusClass = 'bg-yellow-100 text-yellow-800';
                break;
            case 'commandé':
                $statusText = 'Commandé';
                $statusClass = 'bg-blue-100 text-blue-800';
                break;
            case 'en_cours':
                $statusText = 'En cours';
                $statusClass = 'bg-orange-100 text-orange-800';
                break;
            case 'pas validé':
                $statusText = 'Pas validé';
                $statusClass = 'bg-gray-100 text-gray-800';
                break;
            case 'validé':
                $statusText = 'Validé';
                $statusClass = 'bg-green-100 text-green-800';
                break;
            default:
                $statusText = $originalStatus;
                $statusClass = 'bg-gray-100 text-gray-800';
        }

        // Déterminer si c'était une commande partielle
        $isPartial = $order['is_partial'] ? true : false;
        if ($isPartial) {
            $statusText .= ' (partielle)';
        }

        // Formater le statut pour l'affichage
        $formattedStatus = '<span class="px-2 py-1 rounded-full text-xs font-medium ' . $statusClass . '">' . $statusText . '</span>';

        // Formater la quantité avec l'unité
        $quantity = isset($order['quantity']) ? $order['quantity'] : '-';
        $unit = isset($order['unit']) ? $order['unit'] : '';
        $formattedQuantity = $quantity . ' ' . $unit;

        // Indiquer la source (système ou projet)
        $sourceLabel = '';
        if (!empty($order['source_table'])) {
            $sourceLabel = $order['source_table'] == 'besoins' ? 
                '<span class="ml-1 text-xs bg-blue-100 text-blue-800 px-1 py-0.5 rounded">Système</span>' : 
                '';
        }

        // Déterminer le code_projet et nom_client pour les besoins système
        $codeProjet = $order['code_projet'];
        $nomClient = $order['nom_client'];
        
        if ($order['source_table'] == 'besoins') {
            // Pour les besoins système, utiliser le service_demandeur comme code_projet
            // et le client comme nom_client
            $codeProjet = $order['service_demandeur'] ? 'SYS-' . $order['service_demandeur'] : 'SYSTÈME';
            $nomClient = $order['demandeur_nom'] ?: 'Demande interne';
        }

        // Ajouter à la liste des données
        $data[] = [
            'id' => $order['id'],
            'project_id' => $order['project_id'],
            'code_projet' => $codeProjet ?: 'N/A',
            'nom_client' => $nomClient ?: 'N/A',
            'designation' => $order['designation'] . $sourceLabel,
            'original_status' => $formattedStatus,
            'quantity' => $formattedQuantity,
            'fournisseur' => $order['fournisseur'] ?: 'Non spécifié',
            'product_image' => $order['product_image'],
            'canceled_at' => $canceledAt,
            'cancel_reason' => $order['cancel_reason'],
            'order_id' => $order['order_id'],
            'canceled_by' => $order['canceled_by_name'] ?: 'Système',
            'prix_unitaire' => $order['prix_unitaire'] ?: '-',
            'source_table' => $order['source_table'] ?: 'expression_dym'
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'stats' => $stats,
        'recordsTotal' => count($data),
        'recordsFiltered' => count($data)
    ]);

} catch (PDOException $e) {
    error_log("Erreur de base de données dans api_getCanceledOrders.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Exception dans api_getCanceledOrders.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>