<?php

/**
 * ========================================
 * TRAITEMENT DES ACHATS GROUPÉS DE MATÉRIAUX
 * Avec support des pro-formas et gestion correcte des modes de paiement
 * Version : 2.1 (Correction mode de paiement)
 * ========================================
 */

session_start();

// Connexion à la base de données
include_once '../database/connection.php';

// NOUVEAU : Inclure le gestionnaire d'upload de pro-forma
require_once 'commandes-traitement/upload_proforma.php';

// Inclure le fichier avec les fonctions de mise à jour des prix
require_once('price_update_functions.php');

// ========================================
// INITIALISATION DES VARIABLES
// ========================================

// NOUVEAU : Tableau pour stocker les erreurs d'upload de pro-forma
$uploadErrors = [];

// Variables pour les informations du pro-forma
$proformaInfo = ['uploaded' => false];

// ========================================
// VÉRIFICATIONS DE SÉCURITÉ
// ========================================

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Si c'est une requête AJAX, renvoyer un message JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter pour continuer.']);
        exit;
    }
    // Sinon, rediriger vers la page de connexion
    header("Location: ./../index.php");
    exit();
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['bulk_purchase'])) {
    // Si c'est une requête AJAX, renvoyer un message JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Requête non valide.']);
        exit;
    }
    // Sinon, rediriger vers la page principale
    header("Location: achats_materiaux.php");
    exit();
}

// ========================================
// RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
// ========================================

$materialIds = $_POST['material_ids'] ?? [];
$fournisseur = $_POST['fournisseur'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';
$priceType = $_POST['price_type'] ?? 'individual';
$commonPrice = $_POST['common_price'] ?? 0;
$individualPrices = $_POST['prices'] ?? [];
$newQuantities = $_POST['quantities'] ?? [];
$originalQuantities = $_POST['original_quantities'] ?? [];
$sourceTable = $_POST['source_table'] ?? []; // Source de chaque matériau

// ========================================
// VALIDATION DES PRO-FORMAS (SI PRÉSENTS)
// ========================================

// NOUVEAU : Validation spécifique pour les pro-formas si présents
if (isset($_FILES['proforma_file']) && $_FILES['proforma_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Vérifier la taille maximale du fichier (sécurité supplémentaire)
    $maxSize = 10 * 1024 * 1024; // 10 MB
    if ($_FILES['proforma_file']['size'] > $maxSize) {
        $errorResponse = [
            'success' => false,
            'message' => 'Le fichier pro-forma est trop volumineux (max 10 MB)'
        ];

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($errorResponse);
        } else {
            $_SESSION['error_message'] = $errorResponse['message'];
            header("Location: achats_materiaux.php");
        }
        exit();
    }

    // Vérifier l'extension du fichier
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'];
    $fileExtension = strtolower(pathinfo($_FILES['proforma_file']['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExtension, $allowedExtensions)) {
        $errorResponse = [
            'success' => false,
            'message' => 'Format de fichier pro-forma non autorisé. Formats acceptés : ' . implode(', ', $allowedExtensions)
        ];

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($errorResponse);
        } else {
            $_SESSION['error_message'] = $errorResponse['message'];
            header("Location: achats_materiaux.php");
        }
        exit();
    }

    // Marquer qu'un pro-forma est présent
    $proformaInfo = [
        'uploaded' => true,
        'filename' => $_FILES['proforma_file']['name'],
        'size' => $_FILES['proforma_file']['size'],
        'errors' => []
    ];
}

// ========================================
// VALIDATION DES DONNÉES OBLIGATOIRES
// ========================================

if (empty($materialIds) || empty($fournisseur) || empty($paymentMethod)) {
    $errorResponse = [
        'success' => false,
        'message' => 'Données incomplètes. Veuillez remplir tous les champs obligatoires (fournisseur et mode de paiement).'
    ];

    // Si c'est une requête AJAX, renvoyer un message JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($errorResponse);
        exit;
    }

    // Sinon, rediriger avec un message d'erreur
    $_SESSION['error_message'] = $errorResponse['message'];
    header("Location: achats_materiaux.php");
    exit();
}

// ========================================
// FONCTIONS UTILITAIRES
// ========================================

/**
 * Fonction pour traiter l'upload du pro-forma après création de commande
 */
function processProformaUpload($pdo, $achatMateriauxId, $fournisseur, $projetClient = null)
{
    // Vérifier qu'un fichier pro-forma a été uploadé
    if (!isset($_FILES['proforma_file']) || $_FILES['proforma_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // Pas de pro-forma, ce n'est pas une erreur
    }

    try {
        $uploadHandler = new ProformaUploadHandler($pdo);

        $result = $uploadHandler->uploadFile(
            $_FILES['proforma_file'],
            $achatMateriauxId,
            $fournisseur,
            $projetClient
        );

        return $result;
    } catch (Exception $e) {
        // Log l'erreur mais ne pas faire échouer toute la commande
        error_log("Erreur upload pro-forma pour commande {$achatMateriauxId}: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Validation du mode de paiement - VERSION CORRIGÉE
 * Valide l'ID du mode de paiement et retourne ses informations
 */
function validatePaymentMethod($pdo, $paymentMethodId)
{
    // Vérifier que l'ID est un entier valide
    if (!is_numeric($paymentMethodId) || intval($paymentMethodId) <= 0) {
        throw new Exception("ID du mode de paiement invalide : " . $paymentMethodId);
    }

    // Convertir en entier pour sécurité
    $paymentMethodId = intval($paymentMethodId);

    // CHANGEMENT : le champ 'icon' a été remplacé par 'icon_path'
    // On récupère donc icon_path pour l'utiliser dans les interfaces
    $query = "SELECT id, label, description, icon_path FROM payment_methods WHERE id = :id AND is_active = 1";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $paymentMethodId, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$result) {
        throw new Exception("Mode de paiement invalide ou inactif (ID: " . $paymentMethodId . ")");
    }

    return $result;
}

/**
 * Logger les erreurs de pro-forma de manière détaillée
 */
function logProformaError($error, $context = [])
{
    $logMessage = date('Y-m-d H:i:s') . " [PROFORMA ERROR] " . $error;

    if (!empty($context)) {
        $logMessage .= " Context: " . json_encode($context);
    }

    // Créer le dossier logs s'il n'existe pas
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    error_log($logMessage, 3, $logDir . '/proforma_errors.log');
}

// ========================================
// VALIDATION DU MODE DE PAIEMENT
// ========================================

try {
    $paymentMethodInfo = validatePaymentMethod($pdo, $paymentMethod);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $errorResponse = ['success' => false, 'message' => $e->getMessage()];

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($errorResponse);
        exit;
    }

    $_SESSION['error_message'] = $e->getMessage();
    header("Location: achats_materiaux.php");
    exit();
}

// ========================================
// TRAITEMENT PRINCIPAL
// ========================================

try {
    $pdo->beginTransaction();

    // ID de l'utilisateur connecté
    $user_id = $_SESSION['user_id'];

    // ========================================
    // GESTION DU FOURNISSEUR
    // ========================================

    // Vérifier si le fournisseur existe, sinon le créer
    $checkFournisseurQuery = "SELECT id FROM fournisseurs WHERE LOWER(nom) = LOWER(:nom)";
    $checkStmt = $pdo->prepare($checkFournisseurQuery);
    $checkStmt->bindParam(':nom', $fournisseur);
    $checkStmt->execute();
    $fournisseurExists = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$fournisseurExists) {
        // Le fournisseur n'existe pas, le créer
        $createFournisseurQuery = "INSERT INTO fournisseurs (nom, created_by, created_at) 
                                  VALUES (:nom, :created_by, NOW())";
        $createStmt = $pdo->prepare($createFournisseurQuery);
        $createStmt->bindParam(':nom', $fournisseur);
        $createStmt->bindParam(':created_by', $user_id);
        $createStmt->execute();

        // Journaliser la création du fournisseur
        $fournisseurId = $pdo->lastInsertId();
        if (function_exists('logSystemEvent')) {
            logSystemEvent(
                $pdo,
                $user_id,
                'create',
                'fournisseurs',
                $fournisseurId,
                "Création automatique du fournisseur lors d'une commande"
            );
        }
    }

    // ========================================
    // INITIALISATION DES VARIABLES DE TRAITEMENT
    // ========================================

    // Tableau pour stocker les IDs d'expression uniques (pour le bon de commande)
    $expressionIds = [];

    // Tableau temporaire pour stocker les prix des matériaux
    $materialPrices = [];

    // Compteur pour les pro-formas uploadés avec succès
    $proformasUploaded = 0;

    // ========================================
    // TRAITEMENT DES MATÉRIAUX
    // ========================================

    foreach ($materialIds as $index => $materialId) {
        // Déterminer la source du matériau (expression_dym ou besoins)
        $tableSource = isset($sourceTable[$materialId]) ? $sourceTable[$materialId] : 'expression_dym';

        if ($tableSource === 'expression_dym') {
            // ========================================
            // TRAITEMENT EXPRESSION_DYM
            // ========================================

            $materialQuery = "SELECT idExpression, designation, qt_acheter, unit FROM expression_dym WHERE id = :id";
            $materialStmt = $pdo->prepare($materialQuery);
            $materialStmt->bindParam(':id', $materialId);
            $materialStmt->execute();
            $material = $materialStmt->fetch(PDO::FETCH_ASSOC);

            if ($material) {
                // Déterminer le prix à utiliser
                $price = ($priceType === 'common') ? $commonPrice : ($individualPrices[$materialId] ?? 0);
                if ($price <= 0) {
                    throw new Exception("Prix invalide pour le matériau ID: " . $materialId);
                }

                // Stocker le prix pour ce matériau
                $materialPrices[$materialId] = $price;

                // Récupérer la nouvelle quantité si elle est fournie, sinon utiliser l'originale
                $quantity = isset($newQuantities[$materialId]) && $newQuantities[$materialId] > 0 ?
                    $newQuantities[$materialId] : $material['qt_acheter'];

                // Conserver la quantité originale pour référence
                $originalQuantity = isset($originalQuantities[$materialId]) ?
                    $originalQuantities[$materialId] : $material['qt_acheter'];

                // Vérifier si c'est une commande partielle
                $isPartialOrder = $quantity < $originalQuantity;
                $remainingQuantity = 0;

                if ($isPartialOrder) {
                    // Calculer la quantité restante
                    $remainingQuantity = $originalQuantity - $quantity;
                }

                // CORRECTION PRINCIPALE : Insérer dans la table achats_materiaux avec l'ID du mode de paiement
                $insertAchatQuery = "INSERT INTO achats_materiaux 
                    (expression_id, designation, quantity, unit, prix_unitaire, fournisseur, mode_paiement_id, status, user_achat, original_quantity, is_partial) 
                    VALUES (:expression_id, :designation, :quantity, :unit, :prix, :fournisseur, :mode_paiement_id, 'commandé', :user_achat, :original_qty, :is_partial)";

                $insertStmt = $pdo->prepare($insertAchatQuery);
                $insertStmt->bindParam(':expression_id', $material['idExpression']);
                $insertStmt->bindParam(':designation', $material['designation']);
                $insertStmt->bindParam(':quantity', $quantity);
                $insertStmt->bindParam(':unit', $material['unit']);
                $insertStmt->bindParam(':prix', $price);
                $insertStmt->bindParam(':fournisseur', $fournisseur);
                // CORRECTION : Utiliser l'ID du mode de paiement validé
                $insertStmt->bindParam(':mode_paiement_id', $paymentMethodInfo['id'], PDO::PARAM_INT);
                $insertStmt->bindParam(':user_achat', $user_id);
                $insertStmt->bindParam(':original_qty', $originalQuantity);
                $insertStmt->bindParam(':is_partial', $isPartialOrder, PDO::PARAM_BOOL);
                $insertStmt->execute();

                $newOrderId = $pdo->lastInsertId();

                // NOUVEAU : Traiter l'upload du pro-forma
                $proformaResult = processProformaUpload($pdo, $newOrderId, $fournisseur, $material['idExpression']);
                if ($proformaResult) {
                    if ($proformaResult['success']) {
                        $proformasUploaded++;
                    } else {
                        // Log l'erreur mais continuer le traitement
                        $uploadErrors[] = "Erreur pro-forma pour matériau {$material['designation']}: " . $proformaResult['error'];
                        logProformaError($proformaResult['error'], [
                            'material_id' => $materialId,
                            'order_id' => $newOrderId,
                            'designation' => $material['designation']
                        ]);
                    }
                }

                // Mise à jour de expression_dym selon le type de commande
                if ($isPartialOrder) {
                    // Mettre à jour expression_dym pour une commande partielle
                    $updateExpressionQuery = "UPDATE expression_dym 
                                           SET valide_achat = 'en_cours', 
                                           prix_unitaire = :prix, 
                                           fournisseur = :fournisseur,
                                           user_achat = :user_achat,
                                           qt_acheter = :quantity,
                                           initial_qt_acheter = :original_qty,
                                           qt_restante = :remaining_qty
                                           WHERE id = :id";
                    $updateStmt = $pdo->prepare($updateExpressionQuery);
                    $updateStmt->bindParam(':prix', $price);
                    $updateStmt->bindParam(':fournisseur', $fournisseur);
                    $updateStmt->bindParam(':user_achat', $user_id);
                    $updateStmt->bindParam(':quantity', $quantity);
                    $updateStmt->bindParam(':original_qty', $originalQuantity);
                    $updateStmt->bindParam(':remaining_qty', $remainingQuantity);
                    $updateStmt->bindParam(':id', $materialId);
                    $updateStmt->execute();

                    // Journaliser l'action de commande partielle
                    if (function_exists('logSystemEvent')) {
                        $details = json_encode([
                            'material_id' => $materialId,
                            'designation' => $material['designation'],
                            'quantity' => $quantity,
                            'remaining' => $remainingQuantity,
                            'original' => $originalQuantity,
                            'is_partial' => true
                        ]);
                        logSystemEvent($pdo, $user_id, 'commande_partielle', 'achats_materiaux', $newOrderId, $details);
                    }
                } else {
                    // Mettre à jour expression_dym pour une commande complète
                    $updateExpressionQuery = "UPDATE expression_dym 
                                           SET valide_achat = 'valide_en_cours', 
                                           prix_unitaire = :prix, 
                                           fournisseur = :fournisseur,
                                           user_achat = :user_achat,
                                           qt_acheter = :quantity,
                                           qt_restante = 0
                                           WHERE id = :id";
                    $updateStmt = $pdo->prepare($updateExpressionQuery);
                    $updateStmt->bindParam(':prix', $price);
                    $updateStmt->bindParam(':fournisseur', $fournisseur);
                    $updateStmt->bindParam(':user_achat', $user_id);
                    $updateStmt->bindParam(':quantity', $quantity);
                    $updateStmt->bindParam(':id', $materialId);
                    $updateStmt->execute();
                }

                // Stocker l'ID d'expression pour le bon de commande
                if (!in_array($material['idExpression'], $expressionIds)) {
                    $expressionIds[] = $material['idExpression'];
                }

                // Mise à jour des prix produits
                updateProductPrice($pdo, $material['designation'], $price);
                // Stocker les informations de commande pour le bon de commande
                if (!isset($_SESSION['bulk_purchase_orders'])) {
                    $_SESSION['bulk_purchase_orders'] = [];
                }
                $_SESSION['bulk_purchase_orders'][] = [
                    "material_id" => $materialId,
                    "order_id" => $newOrderId,
                    "quantity" => $quantity,
                    "remaining" => $remainingQuantity,
                    "is_complete" => !$isPartialOrder
                ];
            }
        } else if ($tableSource === 'besoins') {
            // ========================================
            // TRAITEMENT BESOINS
            // ========================================

            $materialQuery = "SELECT b.idBesoin, b.designation_article, b.qt_acheter, b.caracteristique, b.id 
                             FROM besoins b WHERE b.id = :id";
            $materialStmt = $pdo->prepare($materialQuery);
            $materialStmt->bindParam(':id', $materialId);
            $materialStmt->execute();
            $material = $materialStmt->fetch(PDO::FETCH_ASSOC);

            if ($material) {
                // Déterminer le prix à utiliser
                $price = ($priceType === 'common') ? $commonPrice : ($individualPrices[$materialId] ?? 0);
                if ($price <= 0) {
                    throw new Exception("Prix invalide pour le matériau ID: " . $materialId);
                }

                // Stocker le prix pour ce matériau
                $materialPrices[$materialId] = $price;

                // Récupérer la nouvelle quantité si elle est fournie, sinon utiliser l'originale
                $quantity = isset($newQuantities[$materialId]) && $newQuantities[$materialId] > 0 ?
                    $newQuantities[$materialId] : $material['qt_acheter'];

                // Conserver la quantité originale pour référence
                $originalQuantity = isset($originalQuantities[$materialId]) ?
                    $originalQuantities[$materialId] : $material['qt_acheter'];

                // Vérifier si c'est une commande partielle
                $isPartialOrder = $quantity < $originalQuantity;
                $remainingQuantity = 0;

                if ($isPartialOrder) {
                    // Calculer la quantité restante
                    $remainingQuantity = $originalQuantity - $quantity;
                }

                // CORRECTION PRINCIPALE : Insérer dans la table achats_materiaux avec l'ID du mode de paiement
                $insertAchatQuery = "INSERT INTO achats_materiaux 
                    (expression_id, designation, quantity, unit, prix_unitaire, fournisseur, mode_paiement_id, status, user_achat, original_quantity, is_partial) 
                    VALUES (:expression_id, :designation, :quantity, :unit, :prix, :fournisseur, :mode_paiement_id, 'commandé', :user_achat, :original_qty, :is_partial)";

                $insertStmt = $pdo->prepare($insertAchatQuery);
                $insertStmt->bindParam(':expression_id', $material['idBesoin']);
                $insertStmt->bindParam(':designation', $material['designation_article']);
                $insertStmt->bindParam(':quantity', $quantity);
                $insertStmt->bindParam(':unit', $material['caracteristique']);
                $insertStmt->bindParam(':prix', $price);
                $insertStmt->bindParam(':fournisseur', $fournisseur);
                // CORRECTION : Utiliser l'ID du mode de paiement validé
                $insertStmt->bindParam(':mode_paiement_id', $paymentMethodInfo['id'], PDO::PARAM_INT);
                $insertStmt->bindParam(':user_achat', $user_id);
                $insertStmt->bindParam(':original_qty', $originalQuantity);
                $insertStmt->bindParam(':is_partial', $isPartialOrder, PDO::PARAM_BOOL);
                $insertStmt->execute();

                $newOrderId = $pdo->lastInsertId();

                // NOUVEAU : Traiter l'upload du pro-forma
                $proformaResult = processProformaUpload($pdo, $newOrderId, $fournisseur, $material['idBesoin']);
                if ($proformaResult) {
                    if ($proformaResult['success']) {
                        $proformasUploaded++;
                    } else {
                        // Log l'erreur mais continuer le traitement
                        $uploadErrors[] = "Erreur pro-forma pour matériau {$material['designation_article']}: " . $proformaResult['error'];
                        logProformaError($proformaResult['error'], [
                            'material_id' => $materialId,
                            'order_id' => $newOrderId,
                            'designation' => $material['designation_article']
                        ]);
                    }
                }

                // Mise à jour de besoins selon le type de commande
                if ($isPartialOrder) {
                    // Mettre à jour besoins pour une commande partielle
                    $updateBesoinQuery = "UPDATE besoins 
                                       SET achat_status = 'en_cours', 
                                       user_achat = :user_achat,
                                       qt_acheter = :quantity,
                                       initial_qt_acheter = :original_qty,
                                       qt_restante = :remaining_qty
                                       WHERE id = :id";
                    $updateStmt = $pdo->prepare($updateBesoinQuery);
                    $updateStmt->bindParam(':user_achat', $user_id);
                    $updateStmt->bindParam(':quantity', $quantity);
                    $updateStmt->bindParam(':original_qty', $originalQuantity);
                    $updateStmt->bindParam(':remaining_qty', $remainingQuantity);
                    $updateStmt->bindParam(':id', $materialId);
                    $updateStmt->execute();

                    // Journaliser l'action de commande partielle
                    if (function_exists('logSystemEvent')) {
                        $details = json_encode([
                            'material_id' => $materialId,
                            'designation' => $material['designation_article'],
                            'quantity' => $quantity,
                            'remaining' => $remainingQuantity,
                            'original' => $originalQuantity,
                            'is_partial' => true,
                            'source' => 'besoins'
                        ]);
                        logSystemEvent($pdo, $user_id, 'commande_partielle', 'achats_materiaux', $newOrderId, $details);
                    }
                } else {
                    // Mettre à jour besoins pour une commande complète
                    $updateBesoinQuery = "UPDATE besoins 
                                       SET achat_status = 'valide_en_cours', 
                                       user_achat = :user_achat,
                                       qt_acheter = :quantity,
                                       initial_qt_acheter = :original_qty,
                                       qt_restante = 0
                                       WHERE id = :id";
                    $updateStmt = $pdo->prepare($updateBesoinQuery);
                    $updateStmt->bindParam(':user_achat', $user_id);
                    $updateStmt->bindParam(':quantity', $quantity);
                    $updateStmt->bindParam(':original_qty', $originalQuantity);
                    $updateStmt->bindParam(':id', $materialId);
                    $updateStmt->execute();
                }

                // Stocker l'ID d'expression pour le bon de commande
                if (!in_array($material['idBesoin'], $expressionIds)) {
                    $expressionIds[] = $material['idBesoin'];
                }

                // Mise à jour des prix produits
                updateProductPrice($pdo, $material['designation_article'], $price);
                // Stocker les informations de commande pour le bon de commande
                if (!isset($_SESSION['bulk_purchase_orders'])) {
                    $_SESSION['bulk_purchase_orders'] = [];
                }
                $_SESSION['bulk_purchase_orders'][] = [
                    "material_id" => $materialId,
                    "order_id" => $newOrderId,
                    "quantity" => $quantity,
                    "remaining" => $remainingQuantity,
                    "is_complete" => !$isPartialOrder
                ];
            }
        }
    }

    // ========================================
    // FINALISATION DE LA TRANSACTION
    // ========================================

    $pdo->commit();

    // ========================================
    // PRÉPARATION DES INFORMATIONS FINALES
    // ========================================

    // Stocker les IDs d'expression dans la session pour le bon de commande
    $_SESSION['bulk_purchase_expressions'] = $expressionIds;

    // Stocker temporairement le fournisseur et les prix des matériaux
    $_SESSION['temp_fournisseur'] = $fournisseur;
    $_SESSION['temp_material_prices'] = $materialPrices;

    // Stocker les IDs des matériaux sélectionnés
    $_SESSION['selected_material_ids'] = $materialIds;
    $_SESSION['selected_material_sources'] = $sourceTable;

    // CORRECTION : Stocker les informations complètes du mode de paiement
    $_SESSION['temp_payment_method'] = $paymentMethodInfo['id'];
    $_SESSION['temp_payment_method_label'] = $paymentMethodInfo['label'];
    $_SESSION['temp_payment_method_info'] = $paymentMethodInfo;

    // NOUVEAU : Mettre à jour les informations du pro-forma
    if ($proformaInfo['uploaded']) {
        $proformaInfo['uploaded_successfully'] = $proformasUploaded;
        $proformaInfo['total_orders'] = count($materialIds);

        if (!empty($uploadErrors)) {
            $proformaInfo['errors'] = $uploadErrors;
            $proformaInfo['has_errors'] = true;
        } else {
            $proformaInfo['has_errors'] = false;
        }
    }

    // Message de succès
    $successMessage = "✅ " . count($materialIds) . " matériaux commandés avec succès !";
    if ($proformaInfo['uploaded'] && $proformasUploaded > 0) {
        $successMessage .= " Pro-forma uploadé et associé aux commandes.";
    }

    $_SESSION['success_message'] = $successMessage;

    // ========================================
    // RÉPONSE SELON LE TYPE DE REQUÊTE
    // ========================================

    // Si c'est une requête AJAX, retourner une réponse JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');

        $response = [
            'success' => true,
            'message' => $successMessage,
            'orders_created' => count($materialIds),
            'proforma_info' => $proformaInfo, // NOUVEAU
            'payment_method' => $paymentMethodInfo
        ];

        if (!empty($expressionIds)) {
            $downloadToken = md5(time() . $expressionIds[0] . rand(1000, 9999));
            $_SESSION['download_token'] = $downloadToken;
            $_SESSION['download_expression_id'] = $expressionIds[0];
            $_SESSION['download_timestamp'] = time();

            $response['pdf_url'] = 'direct_download.php?token=' . $downloadToken;
            $response['auto_download'] = true;
            $response['multi_projects'] = count($expressionIds) > 1;
        }

        echo json_encode($response);
        exit;
    }

    // Sinon, pour les requêtes normales (non-AJAX)
    // Rediriger vers la page de génération de bon de commande
    if (!empty($expressionIds)) {
        header("Location: generate_bon_commande.php?id=" . $expressionIds[0] . "&download=1");
    } else {
        // Si aucun ID d'expression n'est disponible, rediriger vers la page principale
        header("Location: achats_materiaux.php?success=1");
    }
    exit();
} catch (Exception $e) {
    // ========================================
    // GESTION DES ERREURS
    // ========================================

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Logger l'erreur
    error_log("Erreur dans process_bulk_purchase.php: " . $e->getMessage());

    $errorResponse = ['success' => false, 'message' => $e->getMessage()];

    // Si c'est une requête AJAX, retourner une réponse JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($errorResponse);
        exit;
    }

    // Sinon, rediriger avec un message d'erreur
    $_SESSION['error_message'] = "❌ Une erreur s'est produite : " . $e->getMessage();
    header("Location: achats_materiaux.php");
    exit();
}