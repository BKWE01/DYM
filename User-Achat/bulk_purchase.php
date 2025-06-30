<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['bulk_purchase'])) {
    header("Location: achats_materiaux.php");
    exit();
}

// Récupérer les données du formulaire
$projectId = $_POST['project_id'] ?? '';
$materialIds = $_POST['materials'] ?? [];
$fournisseur = $_POST['fournisseur'] ?? '';
$prixType = $_POST['prix_type'] ?? 'individual';
$commonPrix = $_POST['common_prix'] ?? 0;

// Vérifier les données obligatoires
if (empty($materialIds) || empty($fournisseur)) {
    $_SESSION['error_message'] = "Données incomplètes. Veuillez remplir tous les champs obligatoires.";
    header("Location: achats_materiaux.php");
    exit();
}

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Si prix commun, traiter directement les commandes
    if ($prixType === 'common' && $commonPrix > 0) {
        $pdo->beginTransaction();

        // ID de l'utilisateur connecté
        $user_id = $_SESSION['user_id'];

        // Tableau pour stocker les IDs d'expression uniques
        $expressionIds = [];

        foreach ($materialIds as $materialId) {
            // Récupérer les informations du matériau
            $materialQuery = "SELECT idExpression, designation, qt_acheter, unit FROM expression_dym WHERE id = :id";
            $materialStmt = $pdo->prepare($materialQuery);
            $materialStmt->bindParam(':id', $materialId);
            $materialStmt->execute();
            $material = $materialStmt->fetch(PDO::FETCH_ASSOC);

            if ($material) {
                // Insérer dans la table achats_materiaux
                $insertAchatQuery = "INSERT INTO achats_materiaux 
                                   (expression_id, designation, quantity, unit, prix_unitaire, fournisseur, status, user_achat) 
                                   VALUES (:expression_id, :designation, :quantity, :unit, :prix, :fournisseur, 'commandé', :user_achat)";

                $insertStmt = $pdo->prepare($insertAchatQuery);
                $insertStmt->bindParam(':expression_id', $material['idExpression']);
                $insertStmt->bindParam(':designation', $material['designation']);
                $insertStmt->bindParam(':quantity', $material['qt_acheter']);
                $insertStmt->bindParam(':unit', $material['unit']);
                $insertStmt->bindParam(':prix', $commonPrix);
                $insertStmt->bindParam(':fournisseur', $fournisseur);
                $insertStmt->bindParam(':user_achat', $user_id);
                $insertStmt->execute();

                // Mettre à jour la table expression_dym
                $updateExpressionQuery = "UPDATE expression_dym 
                                        SET valide_achat = 'validé', 
                                        prix_unitaire = :prix, 
                                        fournisseur = :fournisseur,
                                        user_achat = :user_achat 
                                        WHERE id = :id";

                $updateStmt = $pdo->prepare($updateExpressionQuery);
                $updateStmt->bindParam(':prix', $commonPrix);
                $updateStmt->bindParam(':fournisseur', $fournisseur);
                $updateStmt->bindParam(':user_achat', $user_id);
                $updateStmt->bindParam(':id', $materialId);
                $updateStmt->execute();

                // Collecter les IDs d'expression pour les bons de commande
                if (!in_array($material['idExpression'], $expressionIds)) {
                    $expressionIds[] = $material['idExpression'];
                }
            }
        }

        $pdo->commit();

        // Stocker les IDs d'expression dans la session pour le bon de commande
        $_SESSION['bulk_purchase_expressions'] = $expressionIds;

        $_SESSION['success_message'] = "Commandes groupées enregistrées avec succès!";

        // Si nous avons des projets multiples, utiliser le premier pour générer le bon de commande
        if (!empty($expressionIds)) {
            header("Location: generate_bon_commande.php?id=" . $expressionIds[0]);
        } else {
            header("Location: achats_materiaux.php");
        }
        exit();
    } else {
        // Si prix individuels, rediriger vers un formulaire pour définir les prix individuellement
        $_SESSION['bulk_purchase'] = [
            'project_id' => $projectId,
            'material_ids' => $materialIds,
            'fournisseur' => $fournisseur
        ];

        header("Location: individual_prices.php");
        exit();
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Une erreur s'est produite : " . $e->getMessage();
}

// Rediriger vers la page principale
header("Location: achats_materiaux.php");
exit();
?>