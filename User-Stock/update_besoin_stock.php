<?php
// Démarrer la session
session_start();

// Vérifiez si l'utilisateur est connecté et l'ID de l'utilisateur est disponible dans la session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté.']);
    exit;
}

// Récupérer l'ID de l'utilisateur connecté depuis la session
$userId = $_SESSION['user_id'];

// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Recevoir les données JSON envoyées par la requête AJAX
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['expressions']) && is_array($data['expressions'])) {
        // Préparer la requête de mise à jour pour user_stock, qt_stock et qt_acheter
        $stmt = $pdo->prepare("
            UPDATE besoins
            SET user_stock = :user_stock, qt_stock = :qt_stock, qt_acheter = :qt_acheter
            WHERE id = :id
        ");

        // Commencer une transaction
        $pdo->beginTransaction();

        foreach ($data['expressions'] as $expression) {
            $qtStock = (int) $expression['qt_stock'];
            $qt_demande = (int) $expression['qt_demande'];

            // Calculer la quantité achetée
            $qtAcheter = $qtStock > $qt_demande ? 0 : $qt_demande - $qtStock;

            // Préparer les valeurs pour la requête
            $stmt->bindParam(':id', $expression['id'], PDO::PARAM_INT);
            $stmt->bindParam(':qt_stock', $qtStock, PDO::PARAM_INT);
            $stmt->bindParam(':qt_acheter', $qtAcheter, PDO::PARAM_INT);
            $stmt->bindParam(':user_stock', $userId, PDO::PARAM_INT); // Utiliser l'ID utilisateur de la session

            $stmt->execute();
        }

        // Commit de la transaction
        $pdo->commit();

        // Retourner une réponse JSON
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Données invalides.']);
    }
} catch (PDOException $e) {
    // En cas d'erreur, rollback de la transaction et affichage de l'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
