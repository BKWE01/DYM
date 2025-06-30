<?php
// Fichier /DYM MANUFACTURE/expressions_besoins/User-Achat/commandes-traitement/direct_download.php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Vérifier le jeton de téléchargement
$token = $_GET['token'] ?? '';
if (
    empty($token) ||
    !isset($_SESSION['download_token']) ||
    $token !== $_SESSION['download_token'] ||
    !isset($_SESSION['download_expression_id'])
) {
    // Afficher une erreur HTML
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Erreur de téléchargement</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .error { color: red; padding: 15px; border: 1px solid #f5c6cb; background-color: #f8d7da; border-radius: 5px; }
            .back-link { display: inline-block; margin-top: 20px; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <h1>Erreur de téléchargement</h1>
        <div class="error">
            <p>Le lien de téléchargement est invalide ou a expiré.</p>
        </div>
        <a href="achats_materiaux.php" class="back-link">Retour à la page des achats</a>
    </body>
    </html>';
    exit;
}

// Vérifier si le jeton n'a pas expiré (10 minutes max)
if (
    !isset($_SESSION['download_timestamp']) ||
    (time() - $_SESSION['download_timestamp']) > 600
) {
    // Nettoyer les variables de session
    unset($_SESSION['download_token']);
    unset($_SESSION['download_expression_id']);
    unset($_SESSION['download_timestamp']);

    // Afficher un message d'expiration
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Lien expiré</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .error { color: #856404; padding: 15px; border: 1px solid #ffeeba; background-color: #fff3cd; border-radius: 5px; }
            .back-link { display: inline-block; margin-top: 20px; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <h1>Lien expiré</h1>
        <div class="error">
            <p>Le lien de téléchargement a expiré (valide pendant 10 minutes).</p>
        </div>
        <a href="achats_materiaux.php" class="back-link">Retour à la page des achats</a>
    </body>
    </html>';
    exit;
}

// Récupérer l'ID d'expression et rediriger vers generate_bon_commande.php
$expressionId = $_SESSION['download_expression_id'];

// Nettoyer les variables de session après usage
unset($_SESSION['download_token']);
unset($_SESSION['download_expression_id']);
unset($_SESSION['download_timestamp']);

// Déterminer le chemin relatif correct pour generate_bon_commande.php
// Obtenir le chemin du script courant
$currentPath = $_SERVER['SCRIPT_NAME'];
$relativePathToBonCommande = 'generate_bon_commande.php';

// Si nous sommes dans un sous-dossier, ajuster le chemin en conséquence
if (strpos($currentPath, '/commandes-traitement/') !== false) {
    $relativePathToBonCommande = '../../generate_bon_commande.php';
} elseif (strpos($currentPath, '/besoins/') !== false) {
    $relativePathToBonCommande = '../../../generate_bon_commande.php';
}

// Rediriger vers la génération du PDF avec téléchargement forcé
header("Location: $relativePathToBonCommande?id=$expressionId&download=1");
exit;