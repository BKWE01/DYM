<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit();
}

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Connexion à la base de données
include_once '../../../database/connection.php';

// Inclure les fonctions de journalisation si elles existent
if (file_exists('../utils/system_logger.php')) {
    include_once '../utils/system_logger.php';
}
// Gestion du pro-forma
require_once '../upload_proforma.php';
$proformaHandler = new ProformaUploadHandler($pdo);
$hasProforma = isset($_FILES['proforma_file']) && $_FILES['proforma_file']['error'] === UPLOAD_ERR_OK;

// Récupérer les données du formulaire
$materialId = isset($_POST['material_id']) ? $_POST['material_id'] : null;
$quantiteCommande = isset($_POST['quantite_commande']) ? floatval($_POST['quantite_commande']) : 0;
$fournisseur = isset($_POST['fournisseur']) ? $_POST['fournisseur'] : '';
$prixUnitaire = isset($_POST['prix_unitaire']) ? floatval($_POST['prix_unitaire']) : 0;
// NOUVEAU : Récupérer le mode de paiement
$paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';

// Vérifier si on doit créer le fournisseur
$createFournisseur = isset($_POST['create_fournisseur']) ? true : false;

// Validation des données - MODIFIÉE
if (!$materialId || $quantiteCommande <= 0 || !$fournisseur || $prixUnitaire <= 0 || !$paymentMethod) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides (mode de paiement requis)']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Vérifier et créer le fournisseur si nécessaire
    if ($createFournisseur) {
        $checkFournisseurQuery = "SELECT id FROM fournisseurs WHERE LOWER(nom) = LOWER(:nom)";
        $checkStmt = $pdo->prepare($checkFournisseurQuery);
        $checkStmt->bindParam(':nom', $fournisseur);
        $checkStmt->execute();
        $fournisseurData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$fournisseurData) {
            // Créer le fournisseur
            $createFournisseurQuery = "INSERT INTO fournisseurs (nom, created_by, created_at)
                                      VALUES (:nom, :created_by, NOW())";
            $createStmt = $pdo->prepare($createFournisseurQuery);
            $createStmt->bindParam(':nom', $fournisseur);
            $createStmt->bindParam(':created_by', $user_id);
            $createStmt->execute();

            // Journaliser la création
            if (function_exists('logSystemEvent')) {
                $fournisseurId = $pdo->lastInsertId();
                logSystemEvent(
                    $pdo,
                    $user_id,
                    'create',
                    'fournisseurs',
                    $fournisseurId,
                    json_encode([
                        'nom' => $fournisseur,
                        'source' => 'commande_partielle_besoin'
                    ])
                );
            }
        } else {
            $fournisseurId = $fournisseurData['id'];
        }
    }

    if (!isset($fournisseurId)) {
        $fetchIdStmt = $pdo->prepare("SELECT id FROM fournisseurs WHERE LOWER(nom) = LOWER(:nom)");
        $fetchIdStmt->bindParam(':nom', $fournisseur);
        $fetchIdStmt->execute();
        $existingSupplier = $fetchIdStmt->fetch(PDO::FETCH_ASSOC);
        $fournisseurId = $existingSupplier['id'] ?? null;
    }

    // 1. Récupérer les informations du besoin
    $besoinQuery = "SELECT b.*,
                   (b.qt_demande - b.qt_acheter) as qt_restante,
                   d.client
                   FROM besoins b
                   LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                   WHERE b.id = :id";
    $besoinStmt = $pdo->prepare($besoinQuery);
    $besoinStmt->bindParam(':id', $materialId);
    $besoinStmt->execute();
    $besoin = $besoinStmt->fetch(PDO::FETCH_ASSOC);

    if (!$besoin) {
        $pdo->rollBack();
        throw new Exception('Besoin non trouvé');
    }

    // 2. Vérifier que la quantité demandée est valide
    $quantiteRestante = $besoin['qt_demande'] - $besoin['qt_acheter'];
    if ($quantiteCommande > $quantiteRestante) {
        $pdo->rollBack();
        throw new Exception('La quantité demandée est supérieure à la quantité restante');
    }

    // 3. Calculer la nouvelle quantité restante et mise à jour
    $nouvelleQuantiteAchetee = $besoin['qt_acheter'] + $quantiteCommande;
    $nouvelleQuantiteRestante = $besoin['qt_demande'] - $nouvelleQuantiteAchetee;

    // Déterminer si cette commande complète la quantité totale
    $isComplete = $nouvelleQuantiteRestante <= 0.001; // Tolérance pour les erreurs d'arrondi

    // Si c'est complet, arrondir à zéro pour éviter des valeurs négatives minuscules
    if ($isComplete) {
        $nouvelleQuantiteRestante = 0;
        $nouvelleQuantiteAchetee = $besoin['qt_demande']; // Ajuster pour éviter les dépassements
    }

    // 4. Insérer la nouvelle commande partielle dans achats_materiaux
    $insertQuery = "INSERT INTO achats_materiaux 
    (expression_id, designation, quantity, unit, prix_unitaire, fournisseur, 
     status, user_achat, original_quantity, is_partial, date_achat, mode_paiement_id)
    VALUES 
    (:expression_id, :designation, :quantity, :unit, :prix_unitaire, :fournisseur, 
     'commandé', :user_achat, :original_quantity, 1, NOW(), :mode_paiement_id)";

    $insertStmt = $pdo->prepare($insertQuery);

    // Préparer les variables avant de les lier
    $expressionId = $besoin['idBesoin'];
    $designation = $besoin['designation_article'];
    $unit = $besoin['caracteristique'];
    $originalQuantity = $besoin['qt_demande'];

    $insertStmt->bindParam(':expression_id', $expressionId);
    $insertStmt->bindParam(':designation', $designation);
    $insertStmt->bindParam(':quantity', $quantiteCommande);
    $insertStmt->bindParam(':unit', $unit);
    $insertStmt->bindParam(':prix_unitaire', $prixUnitaire);
    $insertStmt->bindParam(':fournisseur', $fournisseur);
    $insertStmt->bindParam(':user_achat', $user_id);
    $insertStmt->bindParam(':original_quantity', $originalQuantity);
    $insertStmt->bindParam(':mode_paiement_id', $paymentMethod); // NOUVEAU
    $insertStmt->execute();

    $newOrderId = $pdo->lastInsertId();

    // 5. Mettre à jour la quantité achetée et le statut si nécessaire
    if ($isComplete) {
        // Si la commande est complète, marquer comme "valide_en_cours"
        $updateQuery = "UPDATE besoins 
                SET qt_acheter = :nouvelle_quantite,
                    achat_status = 'valide_en_cours',
                    user_achat = :user_achat
                WHERE id = :id";

        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':nouvelle_quantite', $nouvelleQuantiteAchetee);
        $updateStmt->bindParam(':user_achat', $user_id);
        $updateStmt->bindParam(':id', $materialId);
        $updateStmt->execute();
    } else {
        // Sinon, maintenir le statut "en_cours" et mettre à jour la quantité achetée
        $updateQuery = "UPDATE besoins 
                SET qt_acheter = :nouvelle_quantite,
                    achat_status = 'en_cours',
                    user_achat = :user_achat
                WHERE id = :id";

        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':nouvelle_quantite', $nouvelleQuantiteAchetee);
        $updateStmt->bindParam(':user_achat', $user_id);
        $updateStmt->bindParam(':id', $materialId);
        $updateStmt->execute();
    }

    // Valider la transaction
    $pdo->commit();

    // Récupérer le libellé du mode de paiement pour affichage
    $paymentMethodLabel = '';
    try {
        $labelStmt = $pdo->prepare("SELECT label FROM payment_methods WHERE id = :id");
        $labelStmt->bindParam(':id', $paymentMethod);
        $labelStmt->execute();
        $paymentMethodLabel = $labelStmt->fetchColumn() ?: '';
        $_SESSION['temp_payment_method_label'] = $paymentMethodLabel;
    } catch (Exception $e) {
        error_log('Erreur récupération label mode paiement: ' . $e->getMessage());
    }

    // Journaliser l'événement après le commit
    if (function_exists('logSystemEvent')) {
        $eventType = $isComplete ? 'commande_besoin_partielle_complete' : 'commande_besoin_partielle';

        $logData = [
            'material_id' => $materialId,
            'designation' => $besoin['designation_article'],
            'quantity' => $quantiteCommande,
            'remaining' => $nouvelleQuantiteRestante,
            'is_complete' => $isComplete,
            'source_table' => 'besoins'
        ];

        logSystemEvent($pdo, $user_id, $eventType, 'achats_materiaux', $newOrderId, json_encode($logData));
    }

    // Stocker temporairement le mode de paiement et autres données pour le bon de commande
    if (!isset($_SESSION['commande_partielle_quantities'])) {
        $_SESSION['commande_partielle_quantities'] = [];
    }
    $_SESSION['commande_partielle_quantities'][$materialId] = $quantiteCommande;

    // Stocker l'ID d'expression dans la session pour le bon de commande
    $_SESSION['bulk_purchase_expressions'] = [$besoin['idBesoin']];

    // Stocker temporairement le fournisseur, le mode de paiement et les prix des matériaux
    $_SESSION['temp_fournisseur'] = $fournisseur;
    $_SESSION['temp_payment_method'] = $paymentMethod; // NOUVEAU

    if (!isset($_SESSION['temp_material_prices'])) {
        $_SESSION['temp_material_prices'] = [];
    }
    $_SESSION['temp_material_prices'][$materialId] = $prixUnitaire;

    // Stocker les IDs des matériaux sélectionnés
    $_SESSION['selected_material_ids'] = [$materialId];
    $_SESSION['selected_material_sources'] = [$materialId => 'besoins'];

    // Enregistrer l'ID de la commande pour mettre 
    // à jour le pro-forma lors de la génération du bon de commande
    if (!isset($_SESSION['bulk_purchase_orders'])) {
        $_SESSION['bulk_purchase_orders'] = [];
    }
    $_SESSION['bulk_purchase_orders'][] = [
        'material_id' => $materialId,
        'order_id' => $newOrderId,
        'quantity' => $quantiteCommande,
        'remaining' => $nouvelleQuantiteRestante,
        'is_complete' => $isComplete
    ];

    // Générer un jeton de sécurité temporaire pour le téléchargement
    $downloadToken = md5(time() . $besoin['idBesoin'] . rand(1000, 9999));
    $_SESSION['download_token'] = $downloadToken;
    $_SESSION['download_expression_id'] = $besoin['idBesoin'];
    $_SESSION['download_timestamp'] = time();

    // Inclure le lien de téléchargement dans la réponse avec chemin absolu
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $rootPath = rtrim(str_replace('commandes-traitement/besoins/complete_besoin_partial.php', '', $scriptPath), '/');
    $pdfUrl = $rootPath . '/direct_download.php?token=' . $downloadToken;

    // Upload du pro-forma le cas échéant
    $proformaUploaded = false;
    $uploadedProformaId = null;
    if ($hasProforma) {
        try {
            $upload = $proformaHandler->uploadFile(
                $_FILES['proforma_file'],
                $newOrderId,
                $fournisseurId,
                $besoin['client'] ?? null
            );
            $proformaUploaded = $upload['success'];
            if ($proformaUploaded && isset($upload['proforma_id'])) {
                $uploadedProformaId = $upload['proforma_id'];
                $updateProforma = $pdo->prepare(
                    'UPDATE achats_materiaux SET proforma_id = :pid WHERE id = :id'
                );
                $updateProforma->bindParam(':pid', $uploadedProformaId);
                $updateProforma->bindParam(':id', $newOrderId);
                $updateProforma->execute();
            }
        } catch (Exception $proformaError) {
            error_log('Erreur upload pro-forma: ' . $proformaError->getMessage());
        }
    }

    // Retourner le résultat
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $isComplete ? 'Commande complétée avec succès' : 'Commande partielle enregistrée avec succès',
        'order_id' => $newOrderId,
        'remaining' => $nouvelleQuantiteRestante,
        'is_complete' => $isComplete,
        'payment_method' => $paymentMethod, // NOUVEAU
        'payment_method_label' => $paymentMethodLabel,
        'pdf_url' => $pdfUrl,
        'proforma_uploaded' => $proformaUploaded,
        'proforma_id' => $uploadedProformaId
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
