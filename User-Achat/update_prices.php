<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json'); // Ajout d'en-tête pour indiquer que la réponse est en JSON

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Rediriger vers index.php
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

// Inclure le fichier avec les fonctions de mise à jour des prix
require_once('price_update_functions.php');

// Récupérer l'ID de l'utilisateur connecté depuis la session
$userId = $_SESSION['user_id'];

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Recevoir les données JSON envoyées par la requête AJAX
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['expressions']) && is_array($data['expressions'])) {
        // Préparer la requête SQL
        $sql = "
            UPDATE expression_dym 
            SET user_achat = :user_achat, 
            prix_unitaire = :pu, 
            qt_acheter = :quantiteAcheter, 
            montant = :montant, 
            fournisseur = :fournisseur, 
            modePaiement = :modePaiement, 
            valide_achat = 'pas validé',
            pre_achat = 'valide'
            WHERE id = :id
        ";
        $stmt = $pdo->prepare($sql);

        // Exécuter la requête pour chaque expression
        foreach ($data['expressions'] as $expression) {
            // Vérifier si toutes les clés nécessaires sont présentes
            if (isset($expression['pu'], $expression['quantiteAcheter'], $expression['montant'], $expression['fournisseur'], $expression['modePaiement'], $expression['id'])) {
                $stmt->execute([
                    ':user_achat' => $userId, // Enregistrer l'ID de l'utilisateur dans la colonne user_achat
                    ':pu' => $expression['pu'],
                    ':quantiteAcheter' => $expression['quantiteAcheter'],
                    ':montant' => $expression['montant'],
                    ':fournisseur' => $expression['fournisseur'],
                    ':modePaiement' => $expression['modePaiement'],
                    ':id' => $expression['id']
                ]);

                // Obtenir la désignation du produit pour la mise à jour du prix
                $designationQuery = "SELECT designation FROM expression_dym WHERE id = :id";
                $designationStmt = $pdo->prepare($designationQuery);
                $designationStmt->bindParam(':id', $expression['id']);
                $designationStmt->execute();
                $designation = $designationStmt->fetchColumn();

                if ($designation) {
                    // NOUVELLE FONCTIONNALITÉ: Mettre à jour les prix des produits
                    // Cette fonction vérifiera si c'est un premier prix ou calculera un prix moyen
                    updateProductPrice($pdo, $designation, $expression['pu']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Missing parameters in expression.']);
                exit();
            }
        }

        // Retourner une réponse JSON indiquant le succès
        echo json_encode(['success' => true]);
    } else {
        // Retourner une réponse JSON indiquant une erreur
        echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
    }
} catch (PDOException $e) {
    // En cas d'erreur, retourner une réponse JSON avec le message d'erreur
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>