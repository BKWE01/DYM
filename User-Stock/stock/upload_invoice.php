<?php
header('Content-Type: application/json');
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé. Vous devez être connecté.']);
    exit();
}

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Vérifier si un fichier a été envoyé
if (!isset($_FILES['invoice_file']) || $_FILES['invoice_file']['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'Aucun fichier reçu ou erreur lors de l\'upload';

    // Obtenir un message d'erreur plus précis
    if (isset($_FILES['invoice_file'])) {
        switch ($_FILES['invoice_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $errorMessage = 'Le fichier dépasse la taille maximale autorisée par PHP.';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = 'Le fichier dépasse la taille maximale autorisée par le formulaire.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = 'Le fichier n\'a été que partiellement téléchargé.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = 'Aucun fichier n\'a été téléchargé.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = 'Le dossier temporaire est manquant.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = 'Échec de l\'écriture du fichier sur le disque.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage = 'Une extension PHP a arrêté l\'envoi de fichier.';
                break;
        }
    }

    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit();
}

// Récupérer le fichier
$file = $_FILES['invoice_file'];

// Vérifier la taille du fichier (max 5 Mo)
$maxFileSize = 5 * 1024 * 1024; // 5 Mo en octets
if ($file['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'Le fichier est trop volumineux. La taille maximale est de 5 Mo.']);
    exit();
}

// Vérifier le type de fichier
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Seuls les fichiers PDF, JPG, JPEG et PNG sont acceptés.']);
    exit();
}

// Créer le répertoire d'upload s'il n'existe pas
$uploadDir = '../../uploads/invoices/' . date('Y/m');
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Générer un nom de fichier unique
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$uniqueFileName = uniqid('invoice_') . '_' . time() . '.' . $extension;
$uploadPath = $uploadDir . '/' . $uniqueFileName;

// Déplacer le fichier téléchargé
if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // Calculer le chemin relatif à la racine du site
    $relativeUploadPath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $uploadPath);

    // Inclure le logger
    include_once __DIR__ . '/trace/include_logger.php';
    $logger = getLogger();

    $invoiceData = [
        'id' => null, // Sera défini plus tard
        'file_path' => $relativeUploadPath,
        'original_filename' => $file['name'],
        'file_type' => $file['type'],
        'file_size' => $file['size'],
        'upload_user_id' => $user_id
    ];

    // Journaliser l'upload
    if ($logger) {
        $logger->logInvoiceUpload($invoiceData);
    }


    // Si le chemin n'est pas déjà relatif à la racine du site, le rendre ainsi
    if (strpos($relativeUploadPath, '/') !== 0) {
        $relativeUploadPath = '/' . $relativeUploadPath;
    }

    // Succès
    echo json_encode([
        'success' => true,
        'message' => 'Fichier téléchargé avec succès',
        'file_path' => $relativeUploadPath,  // Stocke le chemin relatif à la racine
        'original_filename' => $file['name'],
        'file_type' => $file['type'],
        'file_size' => $file['size'],
        'upload_user_id' => $user_id
    ]);
} else {
    // Erreur
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du déplacement du fichier'
    ]);
}