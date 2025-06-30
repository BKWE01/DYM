<?php
// Connexion à la base de données
include_once '../database/connection.php';

// Recevoir les données JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Aucune donnée reçue.']);
    exit;
}

// Préparer les données pour l'insertion
$serviceDemandeur = $data['service_demandeur'];
$nomPrenoms = $data['nom_prenoms'];
$dateDemande = $data['date_demande'];
$motifDemande = $data['motif_demande'];
$idBesoin = $data['idBesoin']; // Assurez-vous que ce champ est bien transmis

$query = "INSERT INTO demandeur (idBesoin, service_demandeur, nom_prenoms, date_demande, motif_demande)
          VALUES (:idBesoin, :service_demandeur, :nom_prenoms, :date_demande, :motif_demande)";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':idBesoin' => $idBesoin,
        ':service_demandeur' => $serviceDemandeur,
        ':nom_prenoms' => $nomPrenoms,
        ':date_demande' => $dateDemande,
        ':motif_demande' => $motifDemande
    ]);

    echo json_encode(['success' => true, 'idDemandeur' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'enregistrement du demandeur.']);
}
?>
