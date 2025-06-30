<?php

// /DYM MANUFACTURE/expressions_besoins/database/connection.php

$host = 'localhost'; // Adresse du serveur de base de données
$dbname = 'dym_global'; // Nom de la base de données
$username = 'root'; // Nom d'utilisateur de la base de données
$password = ''; // Mot de passe de la base de données*/

/*$host = 'aescisyny.mysql.db'; // Adresse du serveur de base de données
$dbname = 'aescisyny'; // Nom de la base de données
$username = 'aescisyny'; // Nom d'utilisateur de la base de données
$password = 'Synylodge2024'; // Mot de passe de la base de données*/

date_default_timezone_set('Africa/Abidjan');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>