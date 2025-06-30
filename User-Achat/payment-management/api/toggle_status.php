<?php
/**
 * API simplifiée pour activer/désactiver un mode de paiement
 * NOUVELLE VERSION : Support icônes uploadées (icon_path)
 * 
 * @package DYM_MANUFACTURE
 * @subpackage payment_management_api
 * @author DYM Team
 * @version 3.2 - Upload d'icônes personnalisées
 * @date 29/06/2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérification des permissions
$allowedUserTypes = ['admin', 'achat', 'super_admin'];
if (!in_array($_SESSION['user_type'] ?? '', $allowedUserTypes)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
    exit();
}

// Vérification de la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

/**
 * Fonction pour générer l'URL complète de l'icône
 */
function getIconUrl($iconPath) {
    if (empty($iconPath)) {
        return null;
    }
    
    return (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . 
           dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))) . '/' . ltrim($iconPath, '/');
}

/**
 * Fonction pour vérifier si un fichier d'icône existe
 */
function iconFileExists($iconPath) {
    if (empty($iconPath)) {
        return false;
    }
    
    $physicalPath = '../../../uploads/payment_icons/' . basename($iconPath);
    return file_exists($physicalPath);
}

try {
    // Récupération des données JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        exit();
    }
    
    $id = (int)($input['id'] ?? 0);
    $status = (int)($input['status'] ?? 0);
    
    // Validation
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        exit();
    }
    
    if (!in_array($status, [0, 1])) {
        echo json_encode(['success' => false, 'message' => 'Statut invalide']);
        exit();
    }
    
    $pdo->beginTransaction();
    
    // Vérifier que l'élément existe - MISE À JOUR : récupérer icon_path
    $checkQuery = "SELECT id, label, icon_path, is_active FROM payment_methods WHERE id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$id]);
    $method = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$method) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Mode de paiement non trouvé']);
        exit();
    }
    
    // Vérification - ne pas désactiver le dernier mode actif
    if ($status == 0 && $method['is_active'] == 1) {
        $activeCountQuery = "SELECT COUNT(*) as count FROM payment_methods WHERE is_active = 1 AND id != ?";
        $activeCountStmt = $pdo->prepare($activeCountQuery);
        $activeCountStmt->execute([$id]);
        $activeCount = $activeCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($activeCount == 0) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de désactiver le dernier mode de paiement actif'
            ]);
            exit();
        }
        
        // Vérification supplémentaire : commandes en cours
        $usageCheckQuery = "
            SELECT COUNT(*) as count 
            FROM achats_materiaux 
            WHERE mode_paiement_id = ? 
            AND status IN ('en_attente', 'commande', 'en_livraison')
        ";
        $usageCheckStmt = $pdo->prepare($usageCheckQuery);
        $usageCheckStmt->execute([$id]);
        $pendingUsage = $usageCheckStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($pendingUsage > 0) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => "Impossible de désactiver : {$pendingUsage} commande(s) en cours utilisent ce mode"
            ]);
            exit();
        }
    }
    
    // Mise à jour du statut
    $updateQuery = "UPDATE payment_methods SET is_active = ?, updated_at = NOW() WHERE id = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->execute([$status, $id]);
    
    // Vérifier que la mise à jour a fonctionné
    if ($updateStmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Aucune modification effectuée'
        ]);
        exit();
    }
    
    $pdo->commit();
    
    // Log de l'action pour audit - MISE À JOUR : inclure info icône
    $action = $status == 1 ? 'activé' : 'désactivé';
    $logMessage = "TOGGLE SIMPLE MODE PAIEMENT - ID: {$id}, Label: {$method['label']}, Action: {$action}";
    if (!empty($method['icon_path'])) {
        $logMessage .= ", Icône: " . basename($method['icon_path']);
    }
    $logMessage .= " - Par utilisateur: " . ($_SESSION['user_id'] ?? 'inconnu');
    
    error_log($logMessage);
    
    // Vérifier l'état de l'icône pour les avertissements
    $iconWarning = null;
    if ($status == 1 && !empty($method['icon_path']) && !iconFileExists($method['icon_path'])) {
        $iconWarning = "Mode activé mais fichier d'icône manquant";
    }
    
    // Construction de la réponse - MISE À JOUR : inclure informations icône
    $response = [
        'success' => true,
        'message' => $status == 1 ? 'Mode de paiement activé' : 'Mode de paiement désactivé',
        'data' => [
            'id' => $id,
            'label' => $method['label'],
            'icon_path' => $method['icon_path'], // NOUVEAU : icon_path au lieu de icon
            'new_status' => $status,
            'status_text' => $status == 1 ? 'Actif' : 'Inactif',
            'action_performed' => $action,
            // NOUVEAU : Informations sur l'icône
            'icon_info' => [
                'has_custom_icon' => !empty($method['icon_path']),
                'icon_url' => getIconUrl($method['icon_path']),
                'icon_exists' => !empty($method['icon_path']) ? iconFileExists($method['icon_path']) : false,
                'icon_filename' => !empty($method['icon_path']) ? basename($method['icon_path']) : null
            ]
        ]
    ];
    
    // Ajouter l'avertissement si nécessaire
    if ($iconWarning) {
        $response['warning'] = $iconWarning;
        $response['data']['icon_warning'] = $iconWarning;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erreur base de données dans toggle_status.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la modification du statut'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erreur générale dans toggle_status.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur inattendue s\'est produite'
    ]);
}
?>