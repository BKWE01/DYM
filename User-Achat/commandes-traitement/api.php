<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit();
}

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure les fonctions de combinaison de commandes
include_once 'utils/combine_orders.php';

// Inclure les fonctions de journalisation
include_once 'utils/system_logger.php';

// Traiter les requêtes en fonction de l'action demandée
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

try {
    switch ($action) {
        case 'get_material_info':
            // Récupérer les informations d'un matériau (prix unitaire, fournisseur)
            handleGetMaterialInfo($pdo);
            break;

        case 'complete_partial_order':
            // Compléter une commande partielle
            handleCompletePartialOrder($pdo, $user_id);
            break;

        case 'get_remaining':
            // Récupérer la liste des matériaux avec quantités restantes
            handleGetRemainingMaterials($pdo);
            break;

        case 'get_partial_details':
            // Récupérer les détails d'une commande partielle
            handleGetPartialDetails($pdo);
            break;

        case 'mark_as_received':
            // Marquer une commande partielle comme reçue
            handleMarkAsReceived($pdo, $user_id);
            break;

        case 'export_remaining':
            // Exporter les matériaux avec quantités restantes
            handleExportRemaining($pdo);
            break;

        case 'get_material_with_remaining':
            // Récupérer un matériau spécifique avec quantité restante
            handleGetMaterialWithRemaining($pdo);
            break;

        case 'complete_multiple_partial':
            // Compléter plusieurs commandes partielles
            completeMultiplePartial($pdo, $user_id);
            break;

        default:
            // Action non reconnue
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}

// Fonction pour récupérer les informations d'un matériau
function handleGetMaterialInfo($pdo)
{
    $id = isset($_GET['id']) ? $_GET['id'] : null;

    if (!$id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de matériau manquant']);
        return;
    }

    try {
        // Récupérer les informations du matériau
        $query = "SELECT ed.*, 
                    ip.code_projet, 
                    ip.nom_client
                  FROM expression_dym ed
                  JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                  WHERE ed.id = :id";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$material) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Matériau non trouvé']);
            return;
        }

        // Récupérer la dernière commande liée pour avoir le prix et le fournisseur
        $linkedQuery = "SELECT am.prix_unitaire, am.fournisseur, am.date_achat
                      FROM achats_materiaux am
                      WHERE am.expression_id = :expression_id
                      AND am.designation = :designation
                      AND am.is_partial = 1
                      ORDER BY am.date_achat DESC
                      LIMIT 1";

        $linkedStmt = $pdo->prepare($linkedQuery);
        $linkedStmt->bindParam(':expression_id', $material['idExpression']);
        $linkedStmt->bindParam(':designation', $material['designation']);
        $linkedStmt->execute();
        $linked = $linkedStmt->fetch(PDO::FETCH_ASSOC);

        // Combiner les informations
        $result = [
            'success' => true,
            'id' => $material['id'],
            'designation' => $material['designation'],
            'qt_restante' => $material['qt_restante'],
            'unit' => $material['unit'],
            'code_projet' => $material['code_projet'],
            'nom_client' => $material['nom_client'],
            'prix_unitaire' => $linked ? $linked['prix_unitaire'] : $material['prix_unitaire'],
            'fournisseur' => $linked ? $linked['fournisseur'] : $material['fournisseur']
        ];

        header('Content-Type: application/json');
        echo json_encode($result);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

// Fonction pour récupérer un matériau avec quantité restante par expression_id et designation
function handleGetMaterialWithRemaining($pdo)
{
    $expressionId = isset($_GET['expression_id']) ? $_GET['expression_id'] : null;
    $designation = isset($_GET['designation']) ? $_GET['designation'] : null;

    if (!$expressionId || !$designation) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
        return;
    }

    try {
        // Récupérer le matériau avec quantité restante
        $query = "SELECT ed.* 
                  FROM expression_dym ed
                  WHERE ed.idExpression = :expression_id
                  AND ed.designation = :designation
                  AND ed.qt_restante > 0
                  AND ed.valide_achat = 'en_cours'
                  LIMIT 1";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':expression_id', $expressionId);
        $stmt->bindParam(':designation', $designation);
        $stmt->execute();
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$material) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Matériau non trouvé']);
            return;
        }

        // Retourner le résultat
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'material' => $material
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

// Fonction pour récupérer la liste des matériaux avec quantités restantes
function handleGetRemainingMaterials($pdo)
{
    try {
        // Requête pour récupérer les matériaux avec quantités restantes de la table expression_dym
        $queryExpression = "SELECT 
                     ed.id, 
                     ed.idExpression, 
                     ed.designation, 
                     ed.qt_acheter,
                     ed.qt_restante,
                     ed.initial_qt_acheter,
                     ed.unit,
                     ed.prix_unitaire,
                     ed.fournisseur,
                     ip.code_projet,
                     ip.nom_client,
                     'expression_dym' as source_table,
                     (
                         SELECT COALESCE(SUM(am.quantity), 0) 
                         FROM achats_materiaux am
                         WHERE am.expression_id = ed.idExpression
                         AND am.designation = ed.designation
                         AND am.is_partial = 1
                     ) as quantite_deja_commandee
                  FROM expression_dym ed
                  JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                  WHERE ed.qt_restante > 0 
                  AND ed.valide_achat = 'en_cours'";

        // Requête pour récupérer les matériaux avec quantités restantes de la table besoins
        $queryBesoins = "SELECT 
                     b.id, 
                     b.idBesoin as idExpression, 
                     b.designation_article as designation, 
                     b.qt_acheter,
                     b.qt_demande - b.qt_acheter as qt_restante,
                     b.qt_demande as initial_qt_acheter,
                     b.caracteristique as unit,
                     NULL as prix_unitaire,
                     NULL as fournisseur,
                     CONCAT('SYS-', COALESCE(d.service_demandeur, 'Système')) as code_projet,
                     COALESCE(d.client, 'Demande interne') as nom_client,
                     'besoins' as source_table,
                     (
                         SELECT COALESCE(SUM(am.quantity), 0) 
                         FROM achats_materiaux am
                         WHERE am.expression_id = b.idBesoin
                         AND am.designation = b.designation_article
                         AND am.is_partial = 1
                     ) as quantite_deja_commandee
                  FROM besoins b
                  LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                  WHERE b.qt_demande > b.qt_acheter
                  AND b.achat_status = 'en_cours'";

        // Exécuter les requêtes et combiner les résultats
        $stmtExpression = $pdo->prepare($queryExpression);
        $stmtExpression->execute();
        $materiauxExpression = $stmtExpression->fetchAll(PDO::FETCH_ASSOC);

        $stmtBesoins = $pdo->prepare($queryBesoins);
        $stmtBesoins->execute();
        $materiauxBesoins = $stmtBesoins->fetchAll(PDO::FETCH_ASSOC);

        // Combiner les deux ensembles de résultats
        $materiaux = array_merge($materiauxExpression, $materiauxBesoins);

        // Trier par code_projet et designation
        usort($materiaux, function ($a, $b) {
            $compareProjet = strcmp($a['code_projet'], $b['code_projet']);
            if ($compareProjet !== 0) {
                return $compareProjet;
            }
            return strcmp($a['designation'], $b['designation']);
        });

        // Statistiques globales
        $totalMaterials = count($materiaux);
        $uniqueProjects = count(array_unique(array_column($materiaux, 'code_projet')));
        $totalRemaining = 0;
        $totalProgress = 0;
        $totalInitial = 0;

        foreach ($materiaux as &$materiau) {
            // Calculer la quantité initiale en traitant correctement les valeurs NULL
            $quantiteInitiale = $materiau['initial_qt_acheter'] !== null && $materiau['initial_qt_acheter'] > 0
                ? floatval($materiau['initial_qt_acheter'])
                : (floatval($materiau['qt_acheter']) + floatval($materiau['qt_restante']));

            $quantiteCommandee = $quantiteInitiale - floatval($materiau['qt_restante']);
            $pourcentage = $quantiteInitiale > 0 ? round(($quantiteCommandee / $quantiteInitiale) * 100) : 0;

            $materiau['progress_percentage'] = $pourcentage;
            $totalProgress += $pourcentage;
            $totalRemaining += floatval($materiau['qt_restante']);
            $totalInitial += $quantiteInitiale;

            // Ajouter des clés calculées pour l'affichage
            $materiau['quantite_initiale'] = $quantiteInitiale;
            $materiau['quantite_commandee'] = $quantiteCommandee;
        }

        $progress = $totalMaterials > 0 ? round($totalProgress / $totalMaterials) : 0;
        $globalProgress = $totalInitial > 0 ? round((($totalInitial - $totalRemaining) / $totalInitial) * 100) : 0;

        // Préparation de la réponse
        $response = [
            'success' => true,
            'materials' => $materiaux,
            'stats' => [
                'total_materials' => $totalMaterials,
                'total_projects' => $uniqueProjects,
                'total_remaining' => $totalRemaining,
                'total_initial' => $totalInitial,
                'progress' => $progress,
                'global_progress' => $globalProgress
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString() // Ajout pour le débogage
        ]);
    }
}

// Fonction pour récupérer les détails d'une commande partielle
function handleGetPartialDetails($pdo)
{
    $id = isset($_GET['id']) ? $_GET['id'] : null;

    if (!$id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID manquant']);
        return;
    }

    try {
        // Récupérer les informations du matériau depuis expression_dym
        $materialQuery = "SELECT ed.*, ip.code_projet, ip.nom_client 
                         FROM expression_dym ed
                         LEFT JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                         WHERE ed.id = :id";

        $materialStmt = $pdo->prepare($materialQuery);
        $materialStmt->bindParam(':id', $id);
        $materialStmt->execute();
        $material = $materialStmt->fetch(PDO::FETCH_ASSOC);

        if (!$material) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Matériau non trouvé']);
            return;
        }

        // Récupérer les commandes liées avec le mode de paiement inclus
        $linkedQuery = "SELECT am.id, am.date_achat, am.quantity, am.prix_unitaire, 
                              am.fournisseur, am.status, am.mode_paiement_id, am.is_partial,
                              am.created_at, am.updated_at
                      FROM achats_materiaux am
                      WHERE am.expression_id = :expression_id
                      AND am.designation = :designation
                      AND am.is_partial = 1
                      ORDER BY am.date_achat DESC";

        $linkedStmt = $pdo->prepare($linkedQuery);
        $linkedStmt->bindParam(':expression_id', $material['idExpression']);
        $linkedStmt->bindParam(':designation', $material['designation']);
        $linkedStmt->execute();
        $linked_orders = $linkedStmt->fetchAll(PDO::FETCH_ASSOC);

        // Retourner le résultat avec les modes de paiement
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'material' => $material,
            'linked_orders' => $linked_orders
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

/**
 * Fonction pour compléter une commande partielle avec auto-création du fournisseur
 */
function handleCompletePartialOrder($pdo, $user_id)
{
    // Récupérer les données du formulaire
    $materialId = isset($_POST['material_id']) ? $_POST['material_id'] : null;
    $quantiteCommande = isset($_POST['quantite_commande']) ? floatval($_POST['quantite_commande']) : 0;
    $fournisseur = isset($_POST['fournisseur']) ? $_POST['fournisseur'] : '';
    $prixUnitaire = isset($_POST['prix_unitaire']) ? floatval($_POST['prix_unitaire']) : 0;
    $createFournisseur = isset($_POST['create_fournisseur']) ? true : false;

    // NOUVEAU : Récupérer le mode de paiement
    $paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';

    // Gestion du pro-forma
    require_once 'upload_proforma.php';
    $proformaHandler = new ProformaUploadHandler($pdo);
    $hasProforma = isset($_FILES['proforma_file']) && $_FILES['proforma_file']['error'] === UPLOAD_ERR_OK;

    // Validation des données - MODIFIÉE
    if (!$materialId || $quantiteCommande <= 0 || !$fournisseur || $prixUnitaire <= 0 || !$paymentMethod) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Données invalides (mode de paiement requis)']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Vérifier et créer le fournisseur si nécessaire
        if ($createFournisseur) {
            $checkFournisseurQuery = "SELECT id FROM fournisseurs WHERE LOWER(nom) = LOWER(:nom)";
            $checkStmt = $pdo->prepare($checkFournisseurQuery);
            $checkStmt->bindParam(':nom', $fournisseur);
            $checkStmt->execute();

            if (!$checkStmt->fetch()) {
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
                            'source' => 'commande_partielle'
                        ])
                    );
                }
            }
        }

        // 1. Récupérer les informations du matériau
        $materialQuery = "SELECT ed.*, ip.nom_client
                           FROM expression_dym ed
                           LEFT JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                           WHERE ed.id = :id";
        $materialStmt = $pdo->prepare($materialQuery);
        $materialStmt->bindParam(':id', $materialId);
        $materialStmt->execute();
        $material = $materialStmt->fetch(PDO::FETCH_ASSOC);

        if (!$material) {
            $pdo->rollBack();
            throw new Exception('Matériau non trouvé');
        }

        // 2. Vérifier que la quantité demandée est valide
        if ($quantiteCommande > $material['qt_restante']) {
            $pdo->rollBack();
            throw new Exception('La quantité demandée est supérieure à la quantité restante');
        }

        // 3. Calculer la nouvelle quantité restante
        $nouvelleQuantiteRestante = $material['qt_restante'] - $quantiteCommande;

        // Déterminer si cette commande complète la quantité totale
        $isComplete = $nouvelleQuantiteRestante <= 0.001; // Tolérance pour les erreurs d'arrondi

        // Si c'est complet, arrondir à zéro pour éviter des valeurs négatives minuscules
        if ($isComplete) {
            $nouvelleQuantiteRestante = 0;
        }

        // 4. Insérer la nouvelle commande partielle - CORRIGÉ
        $insertQuery = "INSERT INTO achats_materiaux 
        (expression_id, designation, quantity, unit, prix_unitaire, fournisseur, 
         status, user_achat, original_quantity, is_partial, date_achat, mode_paiement_id)
        VALUES 
        (:expression_id, :designation, :quantity, :unit, :prix_unitaire, :fournisseur, 
         'commandé', :user_achat, :original_quantity, 1, NOW(), :mode_paiement_id)";

        $insertStmt = $pdo->prepare($insertQuery);

        // CORRECTION : Préparer toutes les variables avant de les lier
        $expressionId = $material['idExpression'];
        $designation = $material['designation'];
        $unit = $material['unit'];
        $originalQuantity = $material['initial_qt_acheter'] ?? ($material['qt_acheter'] + $material['qt_restante']);

        $insertStmt->bindParam(':expression_id', $expressionId);
        $insertStmt->bindParam(':designation', $designation);
        $insertStmt->bindParam(':quantity', $quantiteCommande);
        $insertStmt->bindParam(':unit', $unit);
        $insertStmt->bindParam(':prix_unitaire', $prixUnitaire);
        $insertStmt->bindParam(':fournisseur', $fournisseur);
        $insertStmt->bindParam(':user_achat', $user_id);
        $insertStmt->bindParam(':mode_paiement_id', $paymentMethod);
        $insertStmt->bindParam(':original_quantity', $originalQuantity);
        $insertStmt->execute();

        $newOrderId = $pdo->lastInsertId();

        // 5. Mettre à jour la quantité restante et le statut si nécessaire
        if ($isComplete) {
            // Si la commande est complète, marquer comme "valide_en_cours"
            $updateQuery = "UPDATE expression_dym 
                 SET qt_restante = 0,
                     valide_achat = 'valide_en_cours',
                     qt_acheter = qt_acheter + :quantite_commande
                 WHERE id = :id";

            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(':quantite_commande', $quantiteCommande);
            $updateStmt->bindParam(':id', $materialId);
            $updateStmt->execute();
        } else {
            // Sinon, maintenir le statut "en_cours" et mettre à jour la quantité restante
            $updateQuery = "UPDATE expression_dym 
                 SET qt_restante = :nouvelle_quantite,
                     valide_achat = 'en_cours',
                     qt_acheter = qt_acheter + :quantite_commande
                 WHERE id = :id";

            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(':nouvelle_quantite', $nouvelleQuantiteRestante);
            $updateStmt->bindParam(':quantite_commande', $quantiteCommande);
            $updateStmt->bindParam(':id', $materialId);
            $updateStmt->execute();
        }

        // Valider la transaction
        $pdo->commit();

        // Récupérer le libellé du mode de paiement pour la suite (bon de commande)
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

        // Après le commit, effectuer les opérations qui ne nécessitent pas d'être dans la transaction
        if ($isComplete && function_exists('combinePartialOrders')) {
            $combineResult = combinePartialOrders($pdo, $newOrderId);
        }

        // Journaliser l'événement
        if (function_exists('logSystemEvent')) {
            $eventType = $isComplete ? 'commande_partielle_complete' : 'commande_partielle';
            $logData = [
                'material_id' => $materialId,
                'designation' => $material['designation'],
                'quantity' => $quantiteCommande,
                'remaining' => $nouvelleQuantiteRestante,
                'is_complete' => $isComplete,
                'fournisseur' => $fournisseur,
                'payment_method' => $paymentMethod,
                'new_fournisseur' => $createFournisseur
            ];

            logSystemEvent($pdo, $user_id, $eventType, 'achats_materiaux', $newOrderId, json_encode($logData));
        }

        // Stocker les données pour le bon de commande
        if (!isset($_SESSION['commande_partielle_quantities'])) {
            $_SESSION['commande_partielle_quantities'] = [];
        }
        $_SESSION['commande_partielle_quantities'][$materialId] = $quantiteCommande;
        $_SESSION['bulk_purchase_expressions'] = [$material['idExpression']];
        $_SESSION['temp_fournisseur'] = $fournisseur;
        $_SESSION['temp_payment_method'] = $paymentMethod;

        if (!isset($_SESSION['temp_material_prices'])) {
            $_SESSION['temp_material_prices'] = [];
        }
        $_SESSION['temp_material_prices'][$materialId] = $prixUnitaire;
        $_SESSION['selected_material_ids'] = [$materialId];

        // Générer un jeton pour le téléchargement
        $downloadToken = md5(time() . $material['idExpression'] . rand(1000, 9999));
        $_SESSION['download_token'] = $downloadToken;
        $_SESSION['download_expression_id'] = $material['idExpression'];
        $_SESSION['download_timestamp'] = time();

        // Upload du pro-forma le cas échéant
        $proformaUploaded = false;
        $uploadedProformaId = null;
        if ($hasProforma) {
            try {
                $upload = $proformaHandler->uploadFile(
                    $_FILES['proforma_file'],
                    $newOrderId,
                    $fournisseur,
                    $material['nom_client'] ?? null
                );
                $proformaUploaded = $upload['success'];
                if ($proformaUploaded && isset($upload['proforma_id'])) {
                    $uploadedProformaId = $upload['proforma_id'];
                    // Lier le pro-forma à la commande
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
            'payment_method' => $paymentMethod,
            'payment_method_label' => $paymentMethodLabel,
            'pdf_url' => 'direct_download.php?token=' . $downloadToken,
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
}

// Fonction pour marquer une commande partielle comme reçue
function handleMarkAsReceived($pdo, $user_id)
{
    $orderId = isset($_POST['order_id']) ? $_POST['order_id'] : null;

    if (!$orderId) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de commande manquant']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Mettre à jour le statut de la commande
        $updateQuery = "UPDATE achats_materiaux 
                       SET status = 'valide_en_cours', 
                           date_reception = NOW() 
                       WHERE id = :id";

        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':id', $orderId);
        $updateStmt->execute();

        // Récupérer les informations de la commande
        $orderQuery = "SELECT * FROM achats_materiaux WHERE id = :id";
        $orderStmt = $pdo->prepare($orderQuery);
        $orderStmt->bindParam(':id', $orderId);
        $orderStmt->execute();
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        // Si c'est une commande partielle, vérifier si toutes les parties sont reçues
        if ($order['is_partial'] == 1) {
            // Appeler la fonction de regroupement
            $result = combinePartialOrders($pdo, $orderId);

            // Journaliser l'action
            if (function_exists('logSystemEvent')) {
                $details = json_encode([
                    'order_id' => $orderId,
                    'status' => 'reçu',
                    'combine_result' => $result
                ]);

                logSystemEvent($pdo, $user_id, 'reception_commande_partielle', 'achats_materiaux', $orderId, $details);
            }
        }

        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Commande marquée comme reçue avec succès'
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

// Fonction pour exporter les matériaux avec quantités restantes
function handleExportRemaining($pdo)
{
    $format = isset($_GET['format']) ? $_GET['format'] : 'excel';

    try {
        // Récupérer tous les matériaux avec des quantités restantes
        $query = "SELECT 
                         ed.id, 
                         ed.idExpression, 
                         ed.designation, 
                         ed.qt_acheter,
                         ed.qt_restante,
                         ed.initial_qt_acheter,
                         ed.unit,
                         ed.prix_unitaire,
                         ip.code_projet,
                         ip.nom_client,
                         (
                             SELECT COALESCE(SUM(am.quantity), 0) 
                             FROM achats_materiaux am
                             WHERE am.expression_id = ed.idExpression
                             AND am.designation = ed.designation
                             AND am.is_partial = 1
                         ) as quantite_deja_commandee
                      FROM expression_dym ed
                      JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                      WHERE ed.qt_restante > 0 
                      AND ed.valide_achat = 'en_cours'
                      ORDER BY ip.code_projet, ed.designation";

        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $materiaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Préparer les données pour l'export
        $exportData = [];

        // En-tête
        $headers = [
            'Projet',
            'Client',
            'Désignation',
            'Quantité initiale',
            'Quantité commandée',
            'Quantité restante',
            'Unité',
            'Prix unitaire',
            'Progression (%)'
        ];

        $exportData[] = $headers;

        // Données
        foreach ($materiaux as $materiau) {
            $quantiteInitiale = floatval($materiau['initial_qt_acheter'] ?? $materiau['qt_acheter'] + $materiau['qt_restante']);
            $quantiteCommandee = $quantiteInitiale - floatval($materiau['qt_restante']);
            $pourcentage = $quantiteInitiale > 0 ? round(($quantiteCommandee / $quantiteInitiale) * 100) : 0;

            $row = [
                $materiau['code_projet'],
                $materiau['nom_client'],
                $materiau['designation'],
                $quantiteInitiale,
                $quantiteCommandee,
                $materiau['qt_restante'],
                $materiau['unit'],
                $materiau['prix_unitaire'],
                $pourcentage . '%'
            ];

            $exportData[] = $row;
        }

        // Exporter selon le format demandé
        if ($format === 'excel') {
            exportToExcel($exportData, 'materiaux_restants_' . date('Y-m-d') . '.xls');
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $materiaux
            ]);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

// Fonction pour exporter vers Excel
function exportToExcel($data, $filename)
{
    // En-têtes pour forcer le téléchargement
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Ouvrir le flux de sortie
    $out = fopen('php://output', 'w');

    // UTF-8 BOM pour Excel
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Écrire les données
    foreach ($data as $row) {
        fputcsv($out, $row, "\t");
    }

    // Fermer le flux
    fclose($out);
    exit;
}

/**
 * Fonction pour traiter la complétion de plusieurs commandes partielles
 */
function completeMultiplePartial($pdo, $user_id)
{
    try {
        // Vérifier les données requises
        $materialIds = $_POST['material_ids'] ?? [];
        $fournisseur = $_POST['fournisseur'] ?? '';
        $paymentMethod = $_POST['payment_method'] ?? '';
        $quantities = $_POST['quantities'] ?? [];
        $prices = $_POST['prices'] ?? [];
        $sourceTable = $_POST['source_table'] ?? [];

        // Validation des données de base
        if (empty($materialIds) || empty($fournisseur) || empty($paymentMethod)) {
            throw new Exception('Données incomplètes : fournisseur et mode de paiement requis');
        }

        // NOUVEAU : Inclure le gestionnaire d'upload de pro-forma
        require_once 'upload_proforma.php';

        // NOUVEAU : Initialiser le gestionnaire de pro-forma
        $proformaHandler = new ProformaUploadHandler($pdo);
        $proformaResults = [];
        $hasProforma = isset($_FILES['proforma_file']) && $_FILES['proforma_file']['error'] === UPLOAD_ERR_OK;

        // Validation du mode de paiement
        $paymentData = validatePaymentMethod($pdo, $paymentMethod);

        // Démarrer la transaction
        $pdo->beginTransaction();

        $successfulOrders = [];
        $errors = [];
        $totalProcessed = 0;
        $expressionIds = [];
        $materialPrices = [];
        $selectedMaterials = [];
        $materialSourcesMap = [];
        if (!isset($_SESSION['commande_partielle_quantities'])) {
            $_SESSION['commande_partielle_quantities'] = [];
        }

        foreach ($materialIds as $materialId) {
            try {
                $quantiteCommande = floatval($quantities[$materialId] ?? 0);
                $prixUnitaire = floatval($prices[$materialId] ?? 0);
                $source = $sourceTable[$materialId] ?? 'expression_dym';

                if ($quantiteCommande <= 0 || $prixUnitaire <= 0) {
                    throw new Exception("Quantité ou prix invalide pour le matériau ID $materialId");
                }

                // Traitement selon la source
                if ($source === 'besoins') {
                    $result = processPartialFromBesoins($pdo, $user_id, $materialId, $quantiteCommande, $prixUnitaire, $fournisseur, $paymentMethod);
                } else {
                    $result = processPartialFromExpression($pdo, $user_id, $materialId, $quantiteCommande, $prixUnitaire, $fournisseur, $paymentMethod);
                }

                if ($result['success']) {
                    $successfulOrders[] = [
                        'material_id' => $materialId,
                        'order_id' => $result['order_id'],
                        'quantity' => $quantiteCommande,
                        'remaining' => $result['remaining'] ?? 0,
                        'is_complete' => $result['is_complete'] ?? false
                    ];

                    // Conserver les informations pour le bon de commande
                    $expressionIds[] = $result['expression_id'];
                    $selectedMaterials[] = $materialId;
                    $materialPrices[$materialId] = $prixUnitaire;
                    $materialSourcesMap[$materialId] = $source;
                    $_SESSION['commande_partielle_quantities'][$materialId] = $quantiteCommande;

                    // NOUVEAU : Traiter le pro-forma pour cette commande si présent
                    if ($hasProforma && !empty($result['order_id'])) {
                        try {
                            $proformaResult = $proformaHandler->uploadFile(
                                $_FILES['proforma_file'],
                                $result['order_id'],
                                $fournisseur,
                                $result['project_client'] ?? null
                            );

                            $proformaResults[] = [
                                'order_id' => $result['order_id'],
                                'proforma_id' => $proformaResult['proforma_id'] ?? null,
                                'upload_status' => $proformaResult['success'] ? 'success' : 'failed',
                                'error' => $proformaResult['error'] ?? null
                            ];
                        } catch (Exception $proformaError) {
                            // Log l'erreur du pro-forma mais ne pas faire échouer la commande
                            error_log("Erreur pro-forma pour commande {$result['order_id']}: " . $proformaError->getMessage());
                            $proformaResults[] = [
                                'order_id' => $result['order_id'],
                                'upload_status' => 'failed',
                                'error' => $proformaError->getMessage()
                            ];
                        }
                    }

                    $totalProcessed++;
                } else {
                    $errors[] = "Matériau ID $materialId: " . $result['message'];
                }
            } catch (Exception $e) {
                $errors[] = "Erreur matériau ID $materialId: " . $e->getMessage();
                continue;
            }
        }

        // Vérifier s'il y a eu au moins une commande réussie
        if ($totalProcessed === 0) {
            throw new Exception('Aucune commande n\'a pu être traitée. Erreurs: ' . implode('; ', $errors));
        }

        // Stocker les données pour le bon de commande
        $_SESSION['bulk_purchase_orders'] = $successfulOrders;
        $_SESSION['temp_fournisseur'] = $fournisseur;
        $_SESSION['temp_payment_method'] = $paymentMethod;
        $_SESSION['temp_payment_method_label'] = $paymentData['label'] ?? '';
        $_SESSION['temp_material_prices'] = $materialPrices;
        $_SESSION['selected_material_ids'] = $selectedMaterials;
        $_SESSION['selected_material_sources'] = $materialSourcesMap;
        $_SESSION['bulk_purchase_expressions'] = array_values(array_unique($expressionIds));
        if (!empty($expressionIds)) {
            $_SESSION['download_expression_id'] = $expressionIds[0];
        }

        // Générer un token pour le téléchargement
        $downloadToken = md5(time() . $user_id . rand(1000, 9999));
        $_SESSION['download_token'] = $downloadToken;
        $_SESSION['download_timestamp'] = time();

        // Construire l'URL de téléchargement
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $rootPath = rtrim(str_replace('commandes-traitement/api.php', '', $scriptPath), '/');
        $pdfUrl = $rootPath . '/direct_download.php?token=' . $downloadToken;

        // Journaliser l'opération groupée
        if (function_exists('logSystemEvent')) {
            $logData = [
                'total_orders' => $totalProcessed,
                'fournisseur' => $fournisseur,
                'payment_method' => $paymentMethod,
                'proforma_uploaded' => $hasProforma,
                'proforma_results' => $proformaResults,
                'orders' => $successfulOrders,
                'errors' => $errors
            ];

            logSystemEvent($pdo, $user_id, 'completion_multiple_partial', 'achats_materiaux', null, json_encode($logData));
        }

        // Valider la transaction
        $pdo->commit();

        // Préparer la réponse
        $response = [
            'success' => true,
            'message' => "Traitement terminé : $totalProcessed commande(s) complétée(s)" .
                (count($errors) > 0 ? " avec " . count($errors) . " erreur(s)" : ""),
            'total_processed' => $totalProcessed,
            'orders' => $successfulOrders,
            'pdf_url' => $pdfUrl,
            'payment_method' => $paymentMethod
        ];

        // NOUVEAU : Ajouter les informations de pro-forma si présent
        if ($hasProforma) {
            $successfulUploads = array_filter($proformaResults, fn($r) => $r['upload_status'] === 'success');
            $failedUploads = array_filter($proformaResults, fn($r) => $r['upload_status'] === 'failed');

            $response['proforma'] = [
                'uploaded' => true,
                'total_uploads' => count($proformaResults),
                'successful_uploads' => count($successfulUploads),
                'failed_uploads' => count($failedUploads),
                'results' => $proformaResults
            ];

            if (count($failedUploads) > 0) {
                $response['message'] .= ". Attention : " . count($failedUploads) . " pro-forma(s) n'ont pas pu être uploadés";
            }
        }

        if (count($errors) > 0) {
            $response['errors'] = $errors;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du traitement : ' . $e->getMessage()
        ]);
    }
}

/**
 * NOUVEAU : Traiter une commande partielle depuis la table besoins
 */
function processPartialFromBesoins($pdo, $user_id, $materialId, $quantiteCommande, $prixUnitaire, $fournisseur, $paymentMethod)
{
    // Récupérer les informations du besoin
    $besoinQuery = "SELECT b.*, b.caracteristique AS unit, ip.code_projet, ip.nom_client
                    FROM besoins b
                    LEFT JOIN identification_projet ip ON b.idBesoin = ip.idExpression
                    WHERE b.id = :id";

    $besoinStmt = $pdo->prepare($besoinQuery);
    $besoinStmt->bindParam(':id', $materialId);
    $besoinStmt->execute();
    $besoin = $besoinStmt->fetch(PDO::FETCH_ASSOC);

    if (!$besoin) {
        throw new Exception("Besoin non trouvé pour l'ID $materialId");
    }

    // Vérifier si la quantité demandée est disponible
    if ($quantiteCommande > $besoin['qt_restante']) {
        throw new Exception("Quantité demandée ($quantiteCommande) supérieure à la quantité restante ({$besoin['qt_restante']})");
    }

    // Insérer la nouvelle commande
    $insertQuery = "INSERT INTO achats_materiaux (
        designation, quantity, unit, prix_unitaire, fournisseur,
        mode_paiement_id, status, is_partial, expression_id, date_achat,
        original_quantity
    ) VALUES (
        :designation, :quantity, :unit, :prix_unitaire, :fournisseur,
        :mode_paiement_id, 'en_attente', 1, :expression_id, NOW(),
        :original_quantity
    )";

    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->execute([
        ':designation' => $besoin['designation_article'],
        ':quantity' => $quantiteCommande,
        ':unit' => $besoin['unit'],
        ':prix_unitaire' => $prixUnitaire,
        ':fournisseur' => $fournisseur,
        ':mode_paiement_id' => $paymentMethod,
        ':expression_id' => $besoin['idBesoin'],
        ':original_quantity' => $quantiteCommande
    ]);

    $newOrderId = $pdo->lastInsertId();

    // Mettre à jour la quantité restante
    $nouvelleQuantiteRestante = $besoin['qt_restante'] - $quantiteCommande;
    $isComplete = $nouvelleQuantiteRestante <= 0;

    $updateQuery = "UPDATE besoins SET qt_restante = :nouvelle_quantite WHERE id = :id";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->execute([
        ':nouvelle_quantite' => $nouvelleQuantiteRestante,
        ':id' => $materialId
    ]);

    return [
        'success' => true,
        'order_id' => $newOrderId,
        'remaining' => $nouvelleQuantiteRestante,
        'is_complete' => $isComplete,
        'project_client' => $besoin['nom_client'] ?? null,
        'expression_id' => $besoin['idBesoin']
    ];
}

/**
 * NOUVEAU : Traiter une commande partielle depuis la table expression_dym
 */
function processPartialFromExpression($pdo, $user_id, $materialId, $quantiteCommande, $prixUnitaire, $fournisseur, $paymentMethod)
{
    // Récupérer les informations du matériau
    $materialQuery = "SELECT ed.*, ip.code_projet, ip.nom_client 
                      FROM expression_dym ed
                      LEFT JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                      WHERE ed.id = :id";

    $materialStmt = $pdo->prepare($materialQuery);
    $materialStmt->bindParam(':id', $materialId);
    $materialStmt->execute();
    $material = $materialStmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        throw new Exception("Matériau non trouvé pour l'ID $materialId");
    }

    // Vérifier si la quantité demandée est disponible
    if ($quantiteCommande > $material['qt_restante']) {
        throw new Exception("Quantité demandée ($quantiteCommande) supérieure à la quantité restante ({$material['qt_restante']})");
    }

    // Insérer la nouvelle commande
    $insertQuery = "INSERT INTO achats_materiaux (
        designation, quantity, unit, prix_unitaire, fournisseur,
        mode_paiement_id, status, is_partial, expression_id, date_achat,
        original_quantity
    ) VALUES (
        :designation, :quantity, :unit, :prix_unitaire, :fournisseur,
        :mode_paiement_id, 'en_attente', 1, :expression_id, NOW(),
        :original_quantity
    )";

    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->execute([
        ':designation' => $material['designation'],
        ':quantity' => $quantiteCommande,
        ':unit' => $material['unit'],
        ':prix_unitaire' => $prixUnitaire,
        ':fournisseur' => $fournisseur,
        ':mode_paiement_id' => $paymentMethod,
        ':expression_id' => $material['idExpression'],
        ':original_quantity' => $quantiteCommande
    ]);

    $newOrderId = $pdo->lastInsertId();

    // Mettre à jour la quantité restante
    $nouvelleQuantiteRestante = $material['qt_restante'] - $quantiteCommande;
    $isComplete = $nouvelleQuantiteRestante <= 0;

    $updateQuery = "UPDATE expression_dym SET qt_restante = :nouvelle_quantite WHERE id = :id";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->execute([
        ':nouvelle_quantite' => $nouvelleQuantiteRestante,
        ':id' => $materialId
    ]);

    return [
        'success' => true,
        'order_id' => $newOrderId,
        'remaining' => $nouvelleQuantiteRestante,
        'is_complete' => $isComplete,
        'project_client' => $material['nom_client'] ?? null,
        'expression_id' => $material['idExpression']
    ];
}

/**
 * Validation du mode de paiement - version simplifiée
 * Copiée depuis process_bulk_purchase.php pour éviter l'erreur
 */
function validatePaymentMethod($pdo, $paymentMethodId)
{
    // Vérifier que l'ID est bien un entier positif
    if (!is_numeric($paymentMethodId) || intval($paymentMethodId) <= 0) {
        throw new Exception('ID du mode de paiement invalide : ' . $paymentMethodId);
    }

    $paymentMethodId = intval($paymentMethodId);

    $query = 'SELECT id, label, description, icon_path
              FROM payment_methods
              WHERE id = :id AND is_active = 1';

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $paymentMethodId, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        throw new Exception('Mode de paiement invalide ou inactif (ID: ' . $paymentMethodId . ')');
    }

    return $result;
}