<?php
// Connexion à la base de données
include_once '../database/connection.php';

// Récupérer le dernier idBesoin
$query = "SELECT idBesoin FROM demandeur ORDER BY idBesoin DESC LIMIT 1";

try {
    $stmt = $pdo->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $lastId = (int) substr($row['idBesoin'], 0, 5); // Extraire le numéro du dernier idBesoin
    } else {
        $lastId = 0; // Aucun idBesoin trouvé
    }

    // Retourner le dernier idBesoin trouvé
    echo json_encode(['success' => true, 'lastId' => $lastId]);

} catch (PDOException $e) {
    // Gestion des erreurs
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la récupération du dernier idBesoin.']);
}
?>
