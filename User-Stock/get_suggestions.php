<?php
// Connexion à la base de données
include_once '../database/connection.php';

$query = $_GET['query'];

$stmt = $pdo->prepare("SELECT designation FROM designations WHERE designation LIKE :query");
$stmt->execute(['query' => "%$query%"]);
$suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($suggestions);
?>
