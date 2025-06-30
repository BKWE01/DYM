<?php
// API pour valider ou rejeter des éléments d'appels de fonds
header('Content-Type: application/json');
session_start();

// Désactiver l'affichage des erreurs pour éviter la pollution JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$action = $_POST['action'] ?? null;
$element_ids = $_POST['element_ids'] ?? [];
$appel_id = $_POST['appel_id'] ?? null;
$commentaire = $_POST['commentaire'] ?? '';
$motif_rejet = $_POST['motif_rejet'] ?? '';

if (!$action || (!$element_ids && !$appel_id)) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

try {
    // Récupérer le nom de l'utilisateur actuel dès le début
    $user_query = "SELECT name FROM users_exp WHERE id = ?";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$_SESSION['user_id']]);
    $current_user_name = $user_stmt->fetchColumn();
    
    if (!$current_user_name) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    $pdo->beginTransaction();
    
    switch ($action) {
        case 'validate_elements':
            // Validation d'éléments spécifiques
            if (empty($element_ids)) {
                throw new Exception('Aucun élément sélectionné');
            }
            
            $placeholders = str_repeat('?,', count($element_ids) - 1) . '?';
            $update_query = "
                UPDATE appels_fonds_elements 
                SET statut = 'valide', validé_par = ?, date_validation = NOW(), commentaire = ?
                WHERE id IN ($placeholders)
            ";
            $params = array_merge([$_SESSION['user_id'], $commentaire], $element_ids);
            $stmt = $pdo->prepare($update_query);
            $stmt->execute($params);
            
            // Récupérer l'appel de fonds
            $appel_query = "
                SELECT af.*, afe.appel_fonds_id
                FROM appels_fonds_elements afe
                JOIN appels_fonds af ON afe.appel_fonds_id = af.id
                WHERE afe.id = ?
                LIMIT 1
            ";
            $appel_stmt = $pdo->prepare($appel_query);
            $appel_stmt->execute([$element_ids[0]]);
            $appel = $appel_stmt->fetch(PDO::FETCH_ASSOC);
            
            break;
            
        case 'reject_elements':
            // Rejet d'éléments spécifiques
            if (empty($element_ids)) {
                throw new Exception('Aucun élément sélectionné');
            }
            
            $placeholders = str_repeat('?,', count($element_ids) - 1) . '?';
            $update_query = "
                UPDATE appels_fonds_elements 
                SET statut = 'rejete', validé_par = ?, date_validation = NOW(), commentaire = ?
                WHERE id IN ($placeholders)
            ";
            $params = array_merge([$_SESSION['user_id'], $commentaire], $element_ids);
            $stmt = $pdo->prepare($update_query);
            $stmt->execute($params);
            
            // Récupérer l'appel de fonds
            $appel_query = "
                SELECT af.*, afe.appel_fonds_id
                FROM appels_fonds_elements afe
                JOIN appels_fonds af ON afe.appel_fonds_id = af.id
                WHERE afe.id = ?
                LIMIT 1
            ";
            $appel_stmt = $pdo->prepare($appel_query);
            $appel_stmt->execute([$element_ids[0]]);
            $appel = $appel_stmt->fetch(PDO::FETCH_ASSOC);
            
            break;
            
        case 'validate_all':
            // Validation complète de l'appel
            if (!$appel_id) {
                throw new Exception('ID appel manquant');
            }
            
            $update_elements_query = "
                UPDATE appels_fonds_elements 
                SET statut = 'valide', validé_par = ?, date_validation = NOW(), commentaire = ?
                WHERE appel_fonds_id = ? AND statut = 'en_attente'
            ";
            $stmt = $pdo->prepare($update_elements_query);
            $stmt->execute([$_SESSION['user_id'], $commentaire, $appel_id]);
            
            $update_appel_query = "
                UPDATE appels_fonds 
                SET statut = 'valide', validé_par = ?, date_validation = NOW(), commentaire_finance = ?
                WHERE id = ?
            ";
            $stmt = $pdo->prepare($update_appel_query);
            $stmt->execute([$_SESSION['user_id'], $commentaire, $appel_id]);
            
            // Récupérer l'appel de fonds
            $appel_query = "SELECT * FROM appels_fonds WHERE id = ?";
            $appel_stmt = $pdo->prepare($appel_query);
            $appel_stmt->execute([$appel_id]);
            $appel = $appel_stmt->fetch(PDO::FETCH_ASSOC);
            
            break;
            
        case 'reject_all':
            // Rejet complet de l'appel
            if (!$appel_id) {
                throw new Exception('ID appel manquant');
            }
            
            $update_elements_query = "
                UPDATE appels_fonds_elements 
                SET statut = 'rejete', validé_par = ?, date_validation = NOW(), commentaire = ?
                WHERE appel_fonds_id = ? AND statut = 'en_attente'
            ";
            $stmt = $pdo->prepare($update_elements_query);
            $stmt->execute([$_SESSION['user_id'], $commentaire, $appel_id]);
            
            $update_appel_query = "
                UPDATE appels_fonds 
                SET statut = 'rejete', validé_par = ?, date_validation = NOW(), motif_rejet = ?, commentaire_finance = ?
                WHERE id = ?
            ";
            $stmt = $pdo->prepare($update_appel_query);
            $stmt->execute([$_SESSION['user_id'], $motif_rejet, $commentaire, $appel_id]);
            
            // Récupérer l'appel de fonds
            $appel_query = "SELECT * FROM appels_fonds WHERE id = ?";
            $appel_stmt = $pdo->prepare($appel_query);
            $appel_stmt->execute([$appel_id]);
            $appel = $appel_stmt->fetch(PDO::FETCH_ASSOC);
            
            break;
            
        default:
            throw new Exception('Action non reconnue: ' . $action);
    }
    
    if (!$appel) {
        throw new Exception('Appel de fonds non trouvé');
    }
    
    // Mettre à jour le statut de l'appel selon les éléments (seulement pour les actions partielles)
    if (in_array($action, ['validate_elements', 'reject_elements'])) {
        $appel_fonds_id = isset($appel['appel_fonds_id']) ? $appel['appel_fonds_id'] : $appel['id'];
        
        $status_query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides,
                SUM(CASE WHEN statut = 'rejete' THEN 1 ELSE 0 END) as rejetes,
                SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as attente
            FROM appels_fonds_elements 
            WHERE appel_fonds_id = ?
        ";
        $status_stmt = $pdo->prepare($status_query);
        $status_stmt->execute([$appel_fonds_id]);
        $status = $status_stmt->fetch(PDO::FETCH_ASSOC);
        
        $nouveau_statut = 'en_attente';
        if ($status['attente'] == 0) {
            if ($status['valides'] > 0 && $status['rejetes'] > 0) {
                $nouveau_statut = 'partiellement_valide';
            } elseif ($status['valides'] > 0) {
                $nouveau_statut = 'valide';
            } else {
                $nouveau_statut = 'rejete';
            }
        } elseif ($status['valides'] > 0 || $status['rejetes'] > 0) {
            $nouveau_statut = 'partiellement_valide';
        }
        
        $update_appel_status = "
            UPDATE appels_fonds 
            SET statut = ?, validé_par = ?, date_validation = NOW()
            WHERE id = ?
        ";
        $stmt = $pdo->prepare($update_appel_status);
        $stmt->execute([$nouveau_statut, $_SESSION['user_id'], $appel_fonds_id]);
        
        $appel['statut'] = $nouveau_statut;
    }
    
    // Créer une notification pour le demandeur
    $notif_message = '';
    $notif_titre = '';
    
    switch ($action) {
        case 'validate_elements':
            $notif_titre = 'Éléments validés';
            $notif_message = "Des éléments de votre appel de fonds {$appel['code_appel']} ont été validés par {$current_user_name}";
            break;
        case 'validate_all':
            $notif_titre = 'Appel validé';
            $notif_message = "Votre appel de fonds {$appel['code_appel']} a été entièrement validé par {$current_user_name}";
            break;
        case 'reject_elements':
            $notif_titre = 'Éléments rejetés';
            $notif_message = "Des éléments de votre appel de fonds {$appel['code_appel']} ont été rejetés par {$current_user_name}";
            break;
        case 'reject_all':
            $notif_titre = 'Appel rejeté';
            $notif_message = "Votre appel de fonds {$appel['code_appel']} a été rejeté par {$current_user_name}";
            break;
    }
    
    if ($notif_message) {
        $notif_query = "
            INSERT INTO notifications_fonds 
            (user_id, appel_fonds_id, type_notification, titre, message) 
            VALUES (?, ?, 'validation_update', ?, ?)
        ";
        $notif_stmt = $pdo->prepare($notif_query);
        $notif_stmt->execute([$appel['user_id'], $appel['id'], $notif_titre, $notif_message]);
    }
    
    // Ajouter à l'historique
    $hist_query = "
        INSERT INTO appels_fonds_historique 
        (appel_fonds_id, user_id, action, description) 
        VALUES (?, ?, ?, ?)
    ";
    $hist_stmt = $pdo->prepare($hist_query);
    
    $hist_description = "{$current_user_name} a " . 
        (strpos($action, 'validate') !== false ? 'validé' : 'rejeté') . 
        " des éléments de l'appel {$appel['code_appel']}";
        
    if ($action === 'validate_all') {
        $hist_description = "{$current_user_name} a validé entièrement l'appel {$appel['code_appel']}";
    } elseif ($action === 'reject_all') {
        $hist_description = "{$current_user_name} a rejeté entièrement l'appel {$appel['code_appel']}";
    }
    
    $hist_stmt->execute([$appel['id'], $_SESSION['user_id'], $action, $hist_description]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Action effectuée avec succès',
        'nouveau_statut' => $appel['statut'] ?? 'inconnu',
        'action' => $action
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erreur validation appel: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'action: ' . $e->getMessage()
    ]);
}
?>