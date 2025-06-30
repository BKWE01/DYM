<?php
// Script pour télécharger les images de produits de manière sécurisée
header('Content-Type: application/json');

// Vérifier que c'est une requête GET avec les paramètres requis
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres invalides']);
    exit;
}

$fileName = $_GET['file'];
$productName = isset($_GET['name']) ? $_GET['name'] : 'produit';

// Sécurité : Vérifier que le nom de fichier est valide
if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png|gif)$/i', $fileName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nom de fichier invalide']);
    exit;
}

// Chemin vers le fichier
$filePath = '../../../public/uploads/products/' . $fileName;

// Vérifier que le fichier existe
if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Fichier non trouvé']);
    exit;
}

// Obtenir les informations du fichier
$fileInfo = pathinfo($filePath);
$fileExtension = strtolower($fileInfo['extension']);

// Définir le type MIME
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif'
];

$mimeType = isset($mimeTypes[$fileExtension]) ? $mimeTypes[$fileExtension] : 'application/octet-stream';

// Nettoyer le nom du produit pour le nom de fichier
$cleanProductName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $productName);
$downloadName = $cleanProductName . '_' . $fileName;

// Envoyer les headers pour le téléchargement
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Envoyer le fichier
readfile($filePath);
exit;
?>