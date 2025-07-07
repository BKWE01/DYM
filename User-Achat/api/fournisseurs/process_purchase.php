<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit_purchase'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Requête non valide']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';
include_once '../../price_update_functions.php';

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Récupérer les données du formulaire
$expressionId = $_POST['expression_id'] ?? '';
$designation = $_POST['designation'] ?? '';
$quantite = $_POST['quantite'] ?? 0;
$unite = $_POST['unite'] ?? '';
$prix = $_POST['prix'] ?? 0;
$fournisseurId = null;
$fournisseur = $_POST['fournisseur'] ?? '';
$createFournisseur = isset($_POST['create_fournisseur']) && $_POST['create_fournisseur'] == '1';

// Valider les données
if (empty($expressionId) || empty($designation) || empty($fournisseur) || $quantite <= 0 || $prix <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données de formulaire incomplètes ou invalides']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Vérifier si le fournisseur existe, sinon le créer
    $checkFournisseurQuery = "SELECT id FROM fournisseurs WHERE LOWER(nom) = LOWER(:nom)";
    $checkStmt = $pdo->prepare($checkFournisseurQuery);
    $checkStmt->bindParam(':nom', $fournisseur);
    $checkStmt->execute();

    $fournisseurExists = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$fournisseurExists && $createFournisseur) {
        // Le fournisseur n'existe pas, le créer
        $createFournisseurQuery = "INSERT INTO fournisseurs (nom, created_by, created_at)
                                  VALUES (:nom, :created_by, NOW())";
        $createStmt = $pdo->prepare($createFournisseurQuery);
        $createStmt->bindParam(':nom', $fournisseur);
        $createStmt->bindParam(':created_by', $user_id);
        $createStmt->execute();

        $fournisseurId = $pdo->lastInsertId();

        if (function_exists('logSystemEvent')) {
            logSystemEvent(
                $pdo,
                $user_id,
                'create',
                'fournisseurs',
                $fournisseurId,
                "Création automatique du fournisseur lors d'une commande individuelle"
            );
        }
    } else {
        $fournisseurId = $fournisseurExists['id'];
    }

    // Récupérer l'ID du matériau à partir de l'expression et de la désignation
    $materialQuery = "SELECT id FROM expression_dym 
                     WHERE idExpression = :expression_id 
                     AND designation = :designation";
    $materialStmt = $pdo->prepare($materialQuery);
    $materialStmt->bindParam(':expression_id', $expressionId);
    $materialStmt->bindParam(':designation', $designation);
    $materialStmt->execute();
    $material = $materialStmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        throw new Exception("Matériau non trouvé");
    }

    $materialId = $material['id'];

    // Insérer dans la table achats_materiaux
    $insertAchatQuery = "INSERT INTO achats_materiaux
                       (expression_id, designation, quantity, unit, prix_unitaire, fournisseur_id, status, user_achat)
                       VALUES (:expression_id, :designation, :quantity, :unit, :prix, :fournisseur_id, 'commandé', :user_achat)";

    $insertStmt = $pdo->prepare($insertAchatQuery);
    $insertStmt->bindParam(':expression_id', $expressionId);
    $insertStmt->bindParam(':designation', $designation);
    $insertStmt->bindParam(':quantity', $quantite);
    $insertStmt->bindParam(':unit', $unite);
    $insertStmt->bindParam(':prix', $prix);
    $insertStmt->bindParam(':fournisseur_id', $fournisseurId);
    $insertStmt->bindParam(':user_achat', $user_id);
    $insertStmt->execute();

    // Mettre à jour la table expression_dym
    $updateExpressionQuery = "UPDATE expression_dym 
                            SET valide_achat = 'valide_en_cour', 
                            prix_unitaire = :prix, 
                            fournisseur = :fournisseur,
                            user_achat = :user_achat 
                            WHERE id = :material_id";

    $updateStmt = $pdo->prepare($updateExpressionQuery);
    $updateStmt->bindParam(':prix', $prix);
    $updateStmt->bindParam(':fournisseur', $fournisseur);
    $updateStmt->bindParam(':user_achat', $user_id);
    $updateStmt->bindParam(':material_id', $materialId);
    $updateStmt->execute();

    // Mise à jour des prix produits
    updateProductPrice($pdo, $designation, $prix);

    $pdo->commit();

    // Retourner une réponse de succès
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Commande enregistrée avec succès!'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Retourner une réponse d'erreur
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur s\'est produite: ' . $e->getMessage()
    ]);
}
?>