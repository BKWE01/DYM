<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Démarrer la session
session_start();

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté.']);
    exit;
}

$userId = $_SESSION['user_id'];

// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Recevoir les données JSON envoyées par la requête AJAX
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['expressions']) && is_array($data['expressions'])) {
        // Préparer les requêtes de mise à jour
        $stmtUpdateStock = $pdo->prepare("
            UPDATE expression_dym
            SET user_stock = :user_stock, qt_stock = :qt_stock, qt_acheter = :qt_acheter, valide_stock = 'validé'
            WHERE id = :id
        ");
        
        $stmtUpdateDesignation = $pdo->prepare("
            UPDATE expression_dym
            SET designation = :designation
            WHERE id = :id
        ");

        // Commencer une transaction
        $pdo->beginTransaction();

        foreach ($data['expressions'] as $expression) {
            $qtStock = (float) $expression['qt_stock'];
            $quantity = (float) $expression['quantity'];

            // Calculer la quantité achetée
            $qtAcheter = $qtStock > $quantity ? 0 : $quantity - $qtStock;

            // Mise à jour des champs stock
            $stmtUpdateStock->bindParam(':id', $expression['id'], PDO::PARAM_INT);
            $stmtUpdateStock->bindParam(':qt_stock', $qtStock, PDO::PARAM_STR); // Utiliser PDO::PARAM_STR pour varchar
            $stmtUpdateStock->bindParam(':qt_acheter', $qtAcheter, PDO::PARAM_STR); // Utiliser PDO::PARAM_STR pour varchar
            $stmtUpdateStock->bindParam(':user_stock', $userId, PDO::PARAM_INT);
            $stmtUpdateStock->execute();

            // Vérifier si une substitution a été fournie
            if (!empty($expression['substitution'])) {
                $substitution = $expression['substitution'];
                $stmtUpdateDesignation->bindParam(':id', $expression['id'], PDO::PARAM_INT);
                $stmtUpdateDesignation->bindParam(':designation', $substitution, PDO::PARAM_STR);
                $stmtUpdateDesignation->execute();
            }
        }

        // Valider la transaction
        $pdo->commit();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aucune donnée valide fournie.']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
}
?>