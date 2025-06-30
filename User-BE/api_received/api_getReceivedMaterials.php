<?php
/**
 * API pour récupérer les matériaux reçus
 * 
 * Ce fichier gère la récupération des données des matériaux qui ont été
 * reçus par le service achat, avec options de filtrage par période.
 */

// Initialiser la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé. Veuillez vous connecter.'
    ]);
    exit();
}

// Récupérer l'ID de l'utilisateur
$user_id = $_SESSION['user_id'];

// Connexion à la base de données
require_once '../../database/connection.php';
// Inclure le helper de date pour utiliser les mêmes conditions de filtrage que le service achat
include_once '../../include/date_helper.php';

// Récupérer le paramètre de période de filtrage
$period = isset($_GET['period']) ? $_GET['period'] : 'all';

// Initialiser la réponse
$response = [
    'success' => true,
    'materials' => [],
    'stats' => [
        'total_received' => 0,
        'month_received' => 0,
        'total_value' => 0,
        'total_projects' => 0
    ]
];

try {
    // Construire la condition de date selon la période
    $dateCondition = "";
    switch ($period) {
        case 'month':
            $dateCondition = "AND am.date_reception >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
        case 'quarter':
            $dateCondition = "AND am.date_reception >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            break;
        case 'year':
            $dateCondition = "AND am.date_reception >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            break;
        default:
            // Utiliser la même condition de date que le service achat pour garantir la cohérence
            $dateCondition = "AND " . getFilteredDateCondition('am.date_reception');
            break;
    }

    // Requête principale pour récupérer les matériaux
    // Modification pour utiliser la quantité de expression_dym
    $query = "
        SELECT 
            am.id,
            am.expression_id as idExpression,
            am.designation,
            am.quantity as am_quantity,
            am.original_quantity,
            am.unit,
            am.prix_unitaire,
            am.fournisseur,
            am.date_achat,
            am.date_reception,
            am.is_partial,
            am.notes,
            am.parent_id,
            ip.code_projet,
            ip.nom_client,
            ip.description_projet,
            ip.chefprojet,
            ed.quantity as ed_quantity,  -- Ajout de la quantité depuis expression_dym
            ed.prix_unitaire as ed_prix_unitaire,
            ub.name as buyer_name,
            ur.name as receiver_name
        FROM 
            achats_materiaux am
        LEFT JOIN 
            identification_projet ip ON am.expression_id = ip.idExpression
        LEFT JOIN
            expression_dym ed ON (am.expression_id = ed.idExpression AND am.designation = ed.designation)
        LEFT JOIN
            users_exp ub ON am.user_achat = ub.id
        LEFT JOIN
            users_exp ur ON am.user_achat = ur.id
        WHERE 
            am.status = 'reçu'
            AND am.parent_id IS NULL
            $dateCondition
        ORDER BY 
            am.date_reception DESC
    ";

    // Préparer et exécuter la requête
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer les statistiques
    $statsQuery = "
        SELECT 
            COUNT(*) as total_received,
            SUM(CASE WHEN date_reception >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) THEN 1 ELSE 0 END) as month_received,
            SUM(
                CASE 
                    WHEN am.prix_unitaire IS NOT NULL THEN
                        am.prix_unitaire * (
                            SELECT ed.quantity 
                            FROM expression_dym ed 
                            WHERE ed.idExpression = am.expression_id 
                            AND ed.designation = am.designation
                            LIMIT 1
                        )
                    ELSE 0
                END
            ) as total_value,
            COUNT(DISTINCT expression_id) as total_projects
        FROM 
            achats_materiaux am
        WHERE 
            status = 'reçu'
            AND parent_id IS NULL
            AND " . getFilteredDateCondition('date_reception') . "
    ";

    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Mettre à jour la réponse
    $response['materials'] = $materials;
    $response['stats'] = $stats;

} catch (PDOException $e) {
    // En cas d'erreur, renvoyer un message d'échec
    $response = [
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ];
    
    // Log l'erreur pour le débogage
    error_log('API getReceivedMaterials - Erreur: ' . $e->getMessage());
}

// Envoyer la réponse au format JSON
header('Content-Type: application/json');
echo json_encode($response);