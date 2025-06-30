<?php
// Démarrer la session (si ce n'est pas déjà fait)
session_start();

// Connexion à la base de données
include_once '../database/connection.php';


// Vérifier si l'utilisateur est connecté et récupérer son ID depuis la session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Utilisateur non connecté.']);
    exit;
}
$user_id = $_SESSION['user_id']; // ID de l'utilisateur connecté

// Traitement des données envoyées par la requête
$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    try {
        // Préparer la requête SQL pour insérer les besoins, y compris user_emet
        $stmt = $pdo->prepare("INSERT INTO besoins (idBesoin, user_emet, designation_article, caracteristique, qt_demande, stock_status, achat_status) 
                               VALUES (:idBesoin, :user_emet, :designation_article, :caracteristique, :qt_demande, 'pas validé', 'pas validé')");

        // Boucler à travers chaque ligne de besoins
        foreach ($data as $besoin) {
            $stmt->execute([
                ':user_emet' => $user_id,
                ':idBesoin' => $besoin['idBesoin'],
                ':designation_article' => $besoin['designation_article'],
                ':caracteristique' => $besoin['caracteristique'],
                ':qt_demande' => $besoin['qt_demande']
            ]);
        }

        // Si tout s'est bien passé, retourner un message de succès
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // Si une erreur survient lors de l'insertion, afficher le message d'erreur détaillé
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'insertion des besoins : ' . $e->getMessage()]);
    }
} else {
    // Si aucune donnée n'a été envoyée
    echo json_encode(['success' => false, 'error' => 'Aucune donnée envoyée.']);
}
?>
