<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Vérifier si l'ID de la facture a été fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "ID de facture manquant";
    exit();
}

$invoice_id = intval($_GET['id']);

// Connexion à la base de données
include_once '../../database/connection.php'; 

// Récupérer les informations de la facture
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id");
$stmt->bindParam(':id', $invoice_id, PDO::PARAM_INT);
$stmt->execute();
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header('HTTP/1.0 404 Not Found');
    echo "Facture non trouvée";
    exit();
}

// Vérifier si le fichier existe
if (!file_exists($invoice['file_path'])) {
    header('HTTP/1.0 404 Not Found');
    echo "Le fichier de facture n'existe pas sur le serveur";
    exit();
}

// Déterminer le type MIME du fichier
$file_info = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $file_info->file($invoice['file_path']);

// Envoyer le fichier au navigateur
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . $invoice['original_filename'] . '"');
header('Content-Length: ' . filesize($invoice['file_path']));
header('Cache-Control: public, max-age=0');

readfile($invoice['file_path']);
exit();