<?php
/**
 * API mise à jour pour modifier un mode de paiement existant
 * NOUVELLE VERSION : Support upload d'icônes (icon_path)
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
header('Access-Control-Allow-Headers: Content-Type');

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
 * Configuration pour l'upload d'icônes
 */
const UPLOAD_CONFIG = [
    'max_size' => 2 * 1024 * 1024, // 2MB max
    'allowed_types' => ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp'],
    'allowed_extensions' => ['png', 'jpg', 'jpeg', 'svg', 'webp'],
    'upload_dir' => '../../../uploads/payment_icons/', // Répertoire d'upload
    'url_path' => 'uploads/payment_icons/' // Chemin URL relatif
];

/**
 * Fonction pour valider et uploader un fichier d'icône
 */
function uploadIconFile($file) {
    // Vérifier qu'un fichier a été uploadé
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'icon_path' => null]; // Pas de fichier = OK (optionnel)
    }
    
    // Vérifier les erreurs d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux (limite serveur)',
            UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (limite formulaire)',
            UPLOAD_ERR_PARTIAL => 'Upload incomplet',
            UPLOAD_ERR_NO_TMP_DIR => 'Répertoire temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Erreur d\'écriture',
            UPLOAD_ERR_EXTENSION => 'Extension PHP bloquée'
        ];
        
        return [
            'success' => false, 
            'message' => $errorMessages[$file['error']] ?? 'Erreur d\'upload inconnue'
        ];
    }
    
    // Vérifier la taille
    if ($file['size'] > UPLOAD_CONFIG['max_size']) {
        return [
            'success' => false, 
            'message' => 'Fichier trop volumineux (max ' . round(UPLOAD_CONFIG['max_size'] / 1024 / 1024, 1) . 'MB)'
        ];
    }
    
    // Vérifier le type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, UPLOAD_CONFIG['allowed_types'])) {
        return [
            'success' => false, 
            'message' => 'Type de fichier non autorisé. Utilisez PNG, JPG, SVG ou WebP'
        ];
    }
    
    // Vérifier l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, UPLOAD_CONFIG['allowed_extensions'])) {
        return [
            'success' => false, 
            'message' => 'Extension de fichier non autorisée'
        ];
    }
    
    // Créer le répertoire de destination s'il n'existe pas
    $uploadDir = UPLOAD_CONFIG['upload_dir'];
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return [
                'success' => false, 
                'message' => 'Impossible de créer le répertoire d\'upload'
            ];
        }
    }
    
    // Générer un nom de fichier unique et sécurisé
    $timestamp = time();
    $randomString = bin2hex(random_bytes(8));
    $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
    $newFilename = "payment_icon_{$timestamp}_{$randomString}_{$safeFilename}.{$extension}";
    
    $uploadPath = $uploadDir . $newFilename;
    $urlPath = UPLOAD_CONFIG['url_path'] . $newFilename;
    
    // Déplacer le fichier uploadé
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return [
            'success' => false, 
            'message' => 'Erreur lors de la sauvegarde du fichier'
        ];
    }
    
    // Vérifier que le fichier a bien été créé et est lisible
    if (!file_exists($uploadPath) || !is_readable($uploadPath)) {
        return [
            'success' => false, 
            'message' => 'Fichier uploadé non accessible'
        ];
    }
    
    return [
        'success' => true,
        'icon_path' => $urlPath,
        'original_name' => $file['name'],
        'file_size' => $file['size'],
        'mime_type' => $mimeType,
        'uploaded_filename' => $newFilename
    ];
}

/**
 * Fonction pour supprimer un fichier d'icône
 */
function deleteIconFile($iconPath) {
    if (empty($iconPath)) {
        return true;
    }
    
    $physicalPath = UPLOAD_CONFIG['upload_dir'] . basename($iconPath);
    
    if (file_exists($physicalPath)) {
        return unlink($physicalPath);
    }
    
    return true; // Fichier n'existe déjà plus
}

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

try {
    // Déterminer le type de contenu reçu
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'multipart/form-data') !== false) {
        // Données avec fichier (formulaire multipart)
        $id = (int)($_POST['id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = (int)($_POST['is_active'] ?? 1);
        $display_order = (int)($_POST['display_order'] ?? 1);
        $current_icon_path = trim($_POST['current_icon_path'] ?? '');
        $remove_icon = isset($_POST['remove_icon']) && $_POST['remove_icon'] === 'true';
        
        // Traitement du fichier d'icône
        $iconFile = $_FILES['icon_file'] ?? null;
        
    } else {
        // Données JSON (sans fichier)
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
            exit();
        }
        
        $id = (int)($input['id'] ?? 0);
        $label = trim($input['label'] ?? '');
        $description = trim($input['description'] ?? '');
        $is_active = (int)($input['is_active'] ?? 1);
        $display_order = (int)($input['display_order'] ?? 1);
        $current_icon_path = trim($input['current_icon_path'] ?? '');
        $remove_icon = isset($input['remove_icon']) && $input['remove_icon'] === true;
        $iconFile = null; // Pas de fichier en JSON
    }
    
    // Validations
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        exit();
    }
    
    if (empty($label)) {
        echo json_encode(['success' => false, 'message' => 'Le libellé est obligatoire']);
        exit();
    }
    
    if (strlen($label) < 2 || strlen($label) > 100) {
        echo json_encode(['success' => false, 'message' => 'Le libellé doit contenir entre 2 et 100 caractères']);
        exit();
    }
    
    if ($display_order < 1 || $display_order > 999) {
        echo json_encode(['success' => false, 'message' => 'L\'ordre d\'affichage doit être entre 1 et 999']);
        exit();
    }
    
    $pdo->beginTransaction();
    
    // Vérifier que l'élément existe et récupérer les données actuelles
    $checkQuery = "SELECT * FROM payment_methods WHERE id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$id]);
    $existingMethod = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingMethod) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Mode de paiement non trouvé']);
        exit();
    }
    
    // Vérification des doublons (en excluant l'enregistrement actuel)
    $duplicateQuery = "
        SELECT id, label 
        FROM payment_methods 
        WHERE id != ? 
        AND LOWER(TRIM(label)) = LOWER(?)
    ";
    $duplicateStmt = $pdo->prepare($duplicateQuery);
    $duplicateStmt->execute([$id, $label]);
    $conflictingMethods = $duplicateStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($conflictingMethods)) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => "Doublon détecté: Le libellé '{$label}' est déjà utilisé par un autre mode de paiement"
        ]);
        exit();
    }
    
    // Vérifications de sécurité pour la désactivation
    if ($is_active == 0 && $existingMethod['is_active'] == 1) {
        // Vérifier qu'il reste au moins un mode actif
        $activeCountQuery = "SELECT COUNT(*) as count FROM payment_methods WHERE is_active = 1 AND id != ?";
        $activeCountStmt = $pdo->prepare($activeCountQuery);
        $activeCountStmt->execute([$id]);
        $activeCount = $activeCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($activeCount < 1) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'Impossible de désactiver : au moins un mode de paiement doit rester actif'
            ]);
            exit();
        }
        
        // Vérifier s'il y a des commandes en cours avec ce mode
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
                'message' => "Impossible de désactiver : {$pendingUsage} commande(s) en cours utilisent ce mode de paiement"
            ]);
            exit();
        }
    }
    
    // Gestion de l'icône
    $newIconPath = $existingMethod['icon_path']; // Garder l'ancienne par défaut
    $iconUploadInfo = null;
    $oldIconPath = $existingMethod['icon_path'];
    
    // Si demande de suppression de l'icône
    if ($remove_icon) {
        $newIconPath = null;
    }
    
    // Si nouveau fichier uploadé
    if ($iconFile !== null) {
        $uploadResult = uploadIconFile($iconFile);
        
        if (!$uploadResult['success']) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Erreur d\'upload d\'icône: ' . $uploadResult['message']
            ]);
            exit();
        }
        
        if ($uploadResult['icon_path'] !== null) {
            $newIconPath = $uploadResult['icon_path'];
            $iconUploadInfo = $uploadResult;
        }
    }
    
    // Vérifier si l'ordre d'affichage change et gérer les conflits
    if ($display_order != $existingMethod['display_order']) {
        $orderConflictQuery = "SELECT id FROM payment_methods WHERE display_order = ? AND id != ?";
        $orderConflictStmt = $pdo->prepare($orderConflictQuery);
        $orderConflictStmt->execute([$display_order, $id]);
        
        if ($orderConflictStmt->fetch()) {
            // Décaler les autres éléments pour faire de la place
            $shiftOrderQuery = "UPDATE payment_methods SET display_order = display_order + 1 WHERE display_order >= ? AND id != ?";
            $shiftOrderStmt = $pdo->prepare($shiftOrderQuery);
            $shiftOrderStmt->execute([$display_order, $id]);
        }
    }
    
    // Mise à jour - MISE À JOUR : utilisation de icon_path
    $updateQuery = "
        UPDATE payment_methods 
        SET 
            label = ?, 
            description = ?, 
            icon_path = ?, 
            is_active = ?, 
            display_order = ?,
            updated_at = NOW()
        WHERE id = ?
    ";
    
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->execute([
        $label,
        $description ?: null,
        $newIconPath,
        $is_active,
        $display_order,
        $id
    ]);
    
    // Supprimer l'ancienne icône si une nouvelle a été uploadée ou si suppression demandée
    if (($newIconPath !== $oldIconPath) && !empty($oldIconPath)) {
        deleteIconFile($oldIconPath);
    }
    
    // Récupération de l'enregistrement mis à jour
    $selectQuery = "SELECT * FROM payment_methods WHERE id = ?";
    $selectStmt = $pdo->prepare($selectQuery);
    $selectStmt->execute([$id]);
    $updatedMethod = $selectStmt->fetch(PDO::FETCH_ASSOC);
    
    // Déterminer quels changements ont été effectués
    $changes = [
        'label_changed' => $existingMethod['label'] !== $label,
        'description_changed' => $existingMethod['description'] !== $description,
        'icon_changed' => $existingMethod['icon_path'] !== $newIconPath,
        'status_changed' => $existingMethod['is_active'] != $is_active,
        'order_changed' => $existingMethod['display_order'] != $display_order
    ];
    
    // Log de l'action pour audit
    $changedFields = array_keys(array_filter($changes));
    $iconChange = $changes['icon_changed'] ? 
        ($newIconPath ? ($oldIconPath ? 'remplacée' : 'ajoutée') : 'supprimée') : 'inchangée';
    
    error_log("MODIFICATION MODE PAIEMENT - ID: {$id}, Label: {$label}, Champs modifiés: " . implode(', ', $changedFields) . ", Icône: {$iconChange} - Par utilisateur: " . ($_SESSION['user_id'] ?? 'inconnu'));
    
    $pdo->commit();
    
    // Construction de la réponse
    $response = [
        'success' => true,
        'message' => 'Mode de paiement mis à jour avec succès',
        'data' => [
            'id' => (int)$updatedMethod['id'],
            'label' => $updatedMethod['label'],
            'description' => $updatedMethod['description'],
            'icon_path' => $updatedMethod['icon_path'], // NOUVEAU : icon_path au lieu de icon
            'is_active' => (int)$updatedMethod['is_active'],
            'display_order' => (int)$updatedMethod['display_order'],
            'created_at' => $updatedMethod['created_at'],
            'updated_at' => $updatedMethod['updated_at'],
            // Informations supplémentaires pour l'interface
            'formatted_updated_at' => date('d/m/Y H:i', strtotime($updatedMethod['updated_at'])),
            'status_text' => (int)$updatedMethod['is_active'] === 1 ? 'Actif' : 'Inactif',
            'unique_identifier' => 'payment_' . $updatedMethod['id'],
            // NOUVEAU : Informations sur l'icône
            'icon_info' => [
                'has_custom_icon' => !empty($updatedMethod['icon_path']),
                'icon_url' => getIconUrl($updatedMethod['icon_path']),
                'icon_changed' => $changes['icon_changed'],
                'old_icon_path' => $oldIconPath,
                'new_icon_path' => $updatedMethod['icon_path']
            ]
        ],
        'changes' => $changes,
        'icon_update' => [
            'icon_changed' => $changes['icon_changed'],
            'action' => $newIconPath ? ($oldIconPath ? 'replaced' : 'added') : 'removed',
            'old_icon_removed' => $changes['icon_changed'] && !empty($oldIconPath),
            'new_icon_uploaded' => !empty($iconUploadInfo)
        ]
    ];
    
    // Ajouter les détails d'upload si nouvelle icône
    if ($iconUploadInfo) {
        $response['upload_info'] = $iconUploadInfo;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Nettoyer le nouveau fichier uploadé en cas d'erreur de base de données
    if (!empty($newIconPath) && $newIconPath !== $oldIconPath) {
        deleteIconFile($newIconPath);
    }
    
    error_log("Erreur base de données dans update_payment_method.php: " . $e->getMessage());
    
    // Gestion des erreurs spécifiques
    if ($e->getCode() == 23000) {
        echo json_encode([
            'success' => false,
            'message' => 'Ce libellé existe déjà'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour du mode de paiement'
        ]);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Nettoyer le nouveau fichier uploadé en cas d'erreur générale
    if (!empty($newIconPath) && $newIconPath !== $oldIconPath) {
        deleteIconFile($newIconPath);
    }
    
    error_log("Erreur générale dans update_payment_method.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur inattendue s\'est produite'
    ]);
}
?>