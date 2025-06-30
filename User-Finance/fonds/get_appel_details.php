<?php
// API pour récupérer les détails d'un appel de fonds - Version mise à jour
header('Content-Type: application/json');
session_start();

require_once '../../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

$code_appel = $_GET['code'] ?? null;

if (!$code_appel) {
    echo json_encode(['success' => false, 'message' => 'Code appel manquant']);
    exit;
}

try {
    // Récupérer les informations de l'appel
    $appel_query = "
        SELECT 
            af.*,
            u.name as demandeur_nom,
            u.email as demandeur_email,
            vf.name as validateur_nom
        FROM appels_fonds af
        LEFT JOIN users_exp u ON af.user_id = u.id
        LEFT JOIN users_exp vf ON af.validé_par = vf.id
        WHERE af.code_appel = ?
    ";
    $appel_stmt = $pdo->prepare($appel_query);
    $appel_stmt->execute([$code_appel]);
    $appel = $appel_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appel) {
        echo json_encode(['success' => false, 'message' => 'Appel de fonds non trouvé']);
        exit;
    }
    
    // Récupérer les éléments
    $elements_query = "
        SELECT *, 
            CASE 
                WHEN statut = 'valide' THEN 'Validé'
                WHEN statut = 'rejete' THEN 'Rejeté'
                ELSE 'En attente'
            END as statut_libelle
        FROM appels_fonds_elements 
        WHERE appel_fonds_id = ?
        ORDER BY id
    ";
    $elements_stmt = $pdo->prepare($elements_query);
    $elements_stmt->execute([$appel['id']]);
    $elements = $elements_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les justificatifs
    $justificatifs_query = "
        SELECT * FROM appels_fonds_justificatifs 
        WHERE appel_fonds_id = ?
        ORDER BY uploaded_at
    ";
    $justificatifs_stmt = $pdo->prepare($justificatifs_query);
    $justificatifs_stmt->execute([$appel['id']]);
    $justificatifs = $justificatifs_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer l'historique
    $historique_query = "
        SELECT 
            afh.*,
            u.name as user_nom
        FROM appels_fonds_historique afh
        LEFT JOIN users_exp u ON afh.user_id = u.id
        WHERE afh.appel_fonds_id = ?
        ORDER BY afh.created_at DESC
    ";
    $historique_stmt = $pdo->prepare($historique_query);
    $historique_stmt->execute([$appel['id']]);
    $historique = $historique_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les totaux
    $montant_valide = array_sum(array_filter(array_map(function($el) {
        return $el['statut'] === 'valide' ? $el['montant_total'] : 0;
    }, $elements)));
    
    $montant_rejete = array_sum(array_filter(array_map(function($el) {
        return $el['statut'] === 'rejete' ? $el['montant_total'] : 0;
    }, $elements)));
    
    $montant_attente = array_sum(array_filter(array_map(function($el) {
        return $el['statut'] === 'en_attente' ? $el['montant_total'] : 0;
    }, $elements)));
    
    // Formater les dates
    $appel['date_creation_formatted'] = date('d/m/Y à H:i', strtotime($appel['date_creation']));
    if ($appel['date_validation']) {
        $appel['date_validation_formatted'] = date('d/m/Y à H:i', strtotime($appel['date_validation']));
    }
    
    foreach ($historique as &$hist) {
        $hist['created_at_formatted'] = date('d/m/Y à H:i', strtotime($hist['created_at']));
    }
    
    echo json_encode([
        'success' => true,
        'appel' => $appel,
        'elements' => $elements,
        'justificatifs' => $justificatifs,
        'historique' => $historique,
        'totaux' => [
            'montant_total' => $appel['montant_total'],
            'montant_valide' => $montant_valide,
            'montant_rejete' => $montant_rejete,
            'montant_attente' => $montant_attente,
            'progression' => $appel['montant_total'] > 0 ? ($montant_valide / $appel['montant_total']) * 100 : 0
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erreur récupération appel: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des données'
    ]);
}
?>