<?php
// Service Bureau d'Etude "/DYM MANUFACTURE/expressions_besoins/User-BE/api_canceled/api_getCanceledOrders.php" 
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
    
    // Récupérer le paramètre de période de filtrage
    $period = isset($_GET['period']) ? $_GET['period'] : 'all';
    
    // Construire la condition de date selon la période
    $dateCondition = "";
    switch ($period) {
        case 'month':
            $dateCondition = "AND co.canceled_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
        case 'quarter':
            $dateCondition = "AND co.canceled_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            break;
        case 'year':
            $dateCondition = "AND co.canceled_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            break;
        default:
            $dateCondition = "AND co.canceled_at >= :system_start_date"; // Tous depuis la date système
            break;
    }

    // Requête modifiée pour utiliser expression_dym comme source principale des données
    // et éviter les doublons en regroupant par projet et désignation
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
        ps.completed_by,
        u2.name AS completed_by_name,
        /* Priorité à expression_dym pour les informations de base */
        COALESCE(ed.quantity, CASE WHEN co.order_id = 0 THEN NULL ELSE am.quantity END) as quantity,
        COALESCE(ed.unit, CASE WHEN co.order_id = 0 THEN NULL ELSE am.unit END) as unit,
        COALESCE(ed.prix_unitaire, CASE WHEN co.order_id = 0 THEN NULL ELSE am.prix_unitaire END) as prix_unitaire,
        COALESCE(ed.fournisseur, CASE WHEN co.order_id = 0 THEN NULL ELSE am.fournisseur END) as fournisseur
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
                1=1
                $dateCondition
            GROUP BY 
                co.project_id, co.designation
        ) as co
    LEFT JOIN users_exp u ON co.canceled_by = u.id
    LEFT JOIN identification_projet ip ON co.project_id = ip.idExpression
    LEFT JOIN achats_materiaux am ON co.order_id = am.id
    LEFT JOIN project_status ps ON co.project_id = ps.idExpression
    LEFT JOIN users_exp u2 ON ps.completed_by = u2.id
    /* Ajout de la jointure avec expression_dym */
    LEFT JOIN expression_dym ed ON (co.project_id = ed.idExpression AND LOWER(co.designation) = LOWER(ed.designation))
    ORDER BY co.canceled_at DESC";

    $stmt = $pdo->prepare($query);
    
    // Lier le paramètre de date système si nécessaire
    if ($period === 'all' || empty($period)) {
        $stmt->bindParam(':system_start_date', $systemStartDate);
    }
    
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Requête pour les statistiques
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
                WHERE canceled_at >= :system_start_date2
            ) co2
            LEFT JOIN achats_materiaux am ON co2.order_id = am.id
            LEFT JOIN expression_dym ed ON (co2.project_id = ed.idExpression AND LOWER(co2.designation) = LOWER(ed.designation))
        ) as saved_value
    FROM canceled_orders_log
    WHERE canceled_at >= :system_start_date";

    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->bindParam(':system_start_date', $systemStartDate);
    $statsStmt->bindParam(':system_start_date2', $systemStartDate);
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

        // Calculer la valeur économisée pour cet ordre spécifique
        $itemValue = 0;
        if (isset($order['prix_unitaire']) && isset($order['quantity']) && 
            is_numeric($order['prix_unitaire']) && is_numeric($order['quantity'])) {
            $itemValue = floatval($order['prix_unitaire']) * floatval($order['quantity']);
        }

        // Ajouter à la liste des données
        $data[] = [
            'project_id' => $order['project_id'],
            'code_projet' => $order['code_projet'] ?: 'N/A',
            'nom_client' => $order['nom_client'] ?: 'N/A',
            'designation' => $order['designation'],
            'original_status' => $formattedStatus,
            'quantity' => $formattedQuantity,
            'fournisseur' => $order['fournisseur'] ?: 'Non spécifié',
            'canceled_at' => $canceledAt,
            'cancel_reason' => $order['cancel_reason'],
            'id' => $order['id'],  // Changé de order_id à id pour utiliser l'ID du log plutôt que de la commande
            'order_id' => $order['order_id'],
            'canceled_by' => $order['canceled_by_name'] ?: 'Système',
            'completed_by' => $order['completed_by_name'] ?: '',
            'prix_unitaire' => $order['prix_unitaire'] ?: '-',
            'item_value' => number_format($itemValue, 0, ',', ' ') . ' FCFA'
        ];
    }

    // Statistiques pour l'affichage dans le mois en cours
    $currentMonthStats = [
        'month_canceled' => 0
    ];
    
    // Compter les annulations du mois en cours
    foreach ($orders as $order) {
        $orderDate = new DateTime($order['canceled_at']);
        $now = new DateTime();
        if ($orderDate->format('Y-m') === $now->format('Y-m')) {
            $currentMonthStats['month_canceled']++;
        }
    }
    
    // Fusionner les statistiques
    $stats = array_merge($stats, $currentMonthStats);

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