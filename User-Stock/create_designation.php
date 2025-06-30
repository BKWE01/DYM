<?php
// Connexion à la base de données
include_once '../database/connection.php';

$data = json_decode(file_get_contents('php://input'), true);
$designation = $data['designation'];
$unit = $data['unit'];
$type = $data['type'];

$stmt = $pdo->prepare("INSERT INTO designations (designation, unit, type) VALUES (:designation, :unit, :type)");
$stmt->bindParam(':designation', $designation);
$stmt->bindParam(':unit', $unit);
$stmt->bindParam(':type', $type);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
