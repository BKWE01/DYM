<?php
/**
 * API mise à jour pour ajouter un nouveau mode de paiement
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

try {
    // Déterminer le type de contenu reçu
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'multipart/form-data') !== false) {
        // Données avec fichier (formulaire multipart)
        $label = trim($_POST['label'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = (int)($_POST['is_active'] ?? 1);
        $display_order = (int)($_POST['display_order'] ?? 1);
        
        // Traitement du fichier d'icône
        $iconFile = $_FILES['icon_file'] ?? null;
        
    } else {
        // Données JSON (sans fichier)
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['success' => false, 'message' => 'Données invalides']);
            exit();
        }
        
        $label = trim($input['label'] ?? '');
        $description = trim($input['description'] ?? '');
        $is_active = (int)($input['is_active'] ?? 1);
        $display_order = (int)($input['display_order'] ?? 1);
        $iconFile = null; // Pas de fichier en JSON
    }
    
    // Validations de base
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
    
    // Vérification des doublons sur le libellé
    $duplicateQuery = "
        SELECT id, label 
        FROM payment_methods 
        WHERE LOWER(TRIM(label)) = LOWER(?)
    ";
    $duplicateStmt = $pdo->prepare($duplicateQuery);
    $duplicateStmt->execute([$label]);
    $existingMethods = $duplicateStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($existingMethods)) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => "Doublon détecté: Le libellé '{$label}' est déjà utilisé"
        ]);
        exit();
    }
    
    // Gestion de l'upload d'icône
    $iconPath = null;
    $uploadResult = ['success' => true];
    
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
        
        $iconPath = $uploadResult['icon_path'] ?? null;
    }
    
    // Vérifier l'ordre d'affichage et ajuster si nécessaire
    $orderCheckQuery = "SELECT id FROM payment_methods WHERE display_order = ?";
    $orderCheckStmt = $pdo->prepare($orderCheckQuery);
    $orderCheckStmt->execute([$display_order]);
    
    if ($orderCheckStmt->fetch()) {
        // Si l'ordre est déjà pris, prendre le prochain disponible
        $nextOrderQuery = "SELECT MAX(display_order) + 1 as next_order FROM payment_methods";
        $nextOrderStmt = $pdo->prepare($nextOrderQuery);
        $nextOrderStmt->execute();
        $nextOrderResult = $nextOrderStmt->fetch(PDO::FETCH_ASSOC);
        $display_order = $nextOrderResult['next_order'] ?? 1;
    }
    
    // Insertion du nouveau mode de paiement - MISE À JOUR : utilisation de icon_path
    $insertQuery = "
        INSERT INTO payment_methods (
            label, 
            description, 
            icon_path, 
            is_active, 
            display_order,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ";
    
    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->execute([
        $label,
        $description ?: null,
        $iconPath,
        $is_active,
        $display_order
    ]);
    
    $newId = $pdo->lastInsertId();
    
    // Récupération du nouvel enregistrement pour confirmation
    $selectQuery = "SELECT * FROM payment_methods WHERE id = ?";
    $selectStmt = $pdo->prepare($selectQuery);
    $selectStmt->execute([$newId]);
    $newMethod = $selectStmt->fetch(PDO::FETCH_ASSOC);
    
    // Log de l'action pour audit
    error_log("CREATION MODE PAIEMENT - ID: {$newId}, Label: {$label}, Icône: " . ($iconPath ? 'uploadée' : 'aucune') . " - Par utilisateur: " . ($_SESSION['user_id'] ?? 'inconnu'));
    
    $pdo->commit();
    
    // Construction de la réponse
    $response = [
        'success' => true,
        'message' => 'Mode de paiement ajouté avec succès',
        'data' => [
            'id' => (int)$newMethod['id'],
            'label' => $newMethod['label'],
            'description' => $newMethod['description'],
            'icon_path' => $newMethod['icon_path'], // NOUVEAU : icon_path au lieu de icon
            'is_active' => (int)$newMethod['is_active'],
            'display_order' => (int)$newMethod['display_order'],
            'created_at' => $newMethod['created_at'],
            'updated_at' => $newMethod['updated_at'],
            // Informations supplémentaires pour l'interface
            'formatted_created_at' => date('d/m/Y H:i', strtotime($newMethod['created_at'])),
            'status_text' => (int)$newMethod['is_active'] === 1 ? 'Actif' : 'Inactif',
            'unique_identifier' => 'payment_' . $newMethod['id'],
            // NOUVEAU : Informations sur l'icône uploadée
            'icon_info' => [
                'has_custom_icon' => !empty($newMethod['icon_path']),
                'icon_url' => !empty($newMethod['icon_path']) ? 
                    ((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . 
                     dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))) . '/' . $newMethod['icon_path']) : null,
                'original_filename' => $uploadResult['original_name'] ?? null,
                'file_size' => $uploadResult['file_size'] ?? null,
                'mime_type' => $uploadResult['mime_type'] ?? null,
                'uploaded_filename' => $uploadResult['uploaded_filename'] ?? null
            ]
        ],
        'upload_info' => $iconPath ? [
            'icon_uploaded' => true,
            'icon_path' => $iconPath,
            'upload_details' => $uploadResult
        ] : [
            'icon_uploaded' => false,
            'default_icon_used' => true
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Nettoyer le fichier uploadé en cas d'erreur de base de données
    if (!empty($iconPath)) {
        deleteIconFile($iconPath);
    }
    
    error_log("Erreur base de données dans add_payment_method.php: " . $e->getMessage());
    
    // Gestion des erreurs spécifiques
    if ($e->getCode() == 23000) {
        echo json_encode([
            'success' => false,
            'message' => 'Ce libellé existe déjà'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'ajout du mode de paiement'
        ]);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Nettoyer le fichier uploadé en cas d'erreur générale
    if (!empty($iconPath)) {
        deleteIconFile($iconPath);
    }
    
    error_log("Erreur générale dans add_payment_method.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur inattendue s\'est produite'
    ]);
}
?>