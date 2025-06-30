<?php
/**
 * API pour récupérer les compteurs de matériaux
 */

session_start();

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Connexion à la base de données
require_once '../../../database/connection.php';
require_once '../../../include/date_helper.php';

try {
    $response = [
        'success' => true,
        'counts' => [
            'pending' => 0,
            'partial' => 0,
            'ordered' => 0
        ]
    ];

    // Compter les matériaux en attente
    $pendingQuery = "SELECT (
        (SELECT COUNT(*) 
         FROM expression_dym 
         WHERE qt_acheter > 0 
         AND (valide_achat = 'pas validé' OR valide_achat IS NULL)
         AND " . getFilteredDateCondition() . ")
        +
        (SELECT COUNT(*) 
         FROM besoins 
         WHERE qt_acheter > 0 
         AND (achat_status = 'pas validé' OR achat_status IS NULL)
         AND " . getFilteredDateCondition() . ")
    ) as total";

    $pendingStmt = $pdo->prepare($pendingQuery);
    $pendingStmt->execute();
    $response['counts']['pending'] = (int) $pendingStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Compter les commandes partielles
    $partialQuery = "SELECT (
        (SELECT COUNT(*) 
         FROM expression_dym 
         WHERE valide_achat = 'en_cours'
         AND qt_restante > 0
         AND " . getFilteredDateCondition() . ")
        +
        (SELECT COUNT(*) 
         FROM besoins 
         WHERE achat_status = 'en_cours'
         AND qt_restante > 0
         AND " . getFilteredDateCondition() . ")
    ) as total";

    $partialStmt = $pdo->prepare($partialQuery);
    $partialStmt->execute();
    $response['counts']['partial'] = (int) $partialStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Ajouter la valeur 'remaining' pour la compatibilité
    $response['counts']['remaining'] = $response['counts']['partial'];

    // Compter les matériaux commandés
    $orderedQuery = "SELECT (
        (SELECT COUNT(*) 
         FROM expression_dym 
         WHERE (valide_achat = 'validé' OR valide_achat = 'valide_en_cours')
         AND " . getFilteredDateCondition() . ")
        +
        (SELECT COUNT(*) 
         FROM besoins 
         WHERE (achat_status = 'validé' OR achat_status = 'valide_en_cours')
         AND " . getFilteredDateCondition() . ")
    ) as total";

    $orderedStmt = $pdo->prepare($orderedQuery);
    $orderedStmt->execute();
    $response['counts']['ordered'] = (int) $orderedStmt->fetch(PDO::FETCH_ASSOC)['total'];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}