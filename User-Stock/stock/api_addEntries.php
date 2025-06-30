<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure le logger
include_once __DIR__ . '/trace/include_logger.php';
$logger = getLogger();

// Fonction pour enregistrer les événements dans un fichier de log
function logEvent($message)
{
    $logFile = 'dispatching_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

/**
 * Vérifie si un produit a un statut permettant le dispatching
 * ET s'il existe des commandes en attente pour ce produit
 * 
 * @param PDO $pdo Instance de connexion à la base de données
 * @param string $productName Nom du produit
 * @return bool True si le dispatching est nécessaire
 */
function shouldDispatchProduct($pdo, $productName)
{
    // Recherche de commandes en attente dans achats_materiaux
    $pendingOrdersStmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM achats_materiaux am
        WHERE LOWER(am.designation) LIKE :search 
        AND am.status = 'commandé'
    ");

    $searchTerm = '%' . strtolower($productName) . '%';
    $pendingOrdersStmt->execute([':search' => $searchTerm]);
    $pendingOrders = $pendingOrdersStmt->fetch(PDO::FETCH_ASSOC);

    // Si aucune commande en attente, pas besoin de dispatching
    if ($pendingOrders['count'] == 0) {
        logEvent("Aucune commande en attente trouvée pour '$productName' - pas de dispatching nécessaire");
        return false;
    }

    // Vérifier les statuts dans expression_dym qui correspondent aux commandes en attente
    $edStmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM achats_materiaux am
        JOIN expression_dym ed ON (ed.idExpression = am.expression_id AND LOWER(ed.designation) = LOWER(am.designation))
        WHERE LOWER(am.designation) LIKE :search 
        AND am.status = 'commandé'
        AND (ed.valide_achat = 'validé' OR ed.valide_achat = 'en_cours')
    ");
    $edStmt->execute([':search' => $searchTerm]);
    $edResult = $edStmt->fetch(PDO::FETCH_ASSOC);

    if ($edResult['count'] > 0) {
        logEvent("Dispatching nécessaire pour '$productName' - trouvé dans expression_dym avec statut validé/en_cours");
        return true;
    }

    // Vérifier les statuts dans besoins qui correspondent aux commandes en attente
    $bStmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM achats_materiaux am
        JOIN besoins b ON (b.idBesoin = am.expression_id AND LOWER(b.designation_article) = LOWER(am.designation))
        WHERE LOWER(am.designation) LIKE :search 
        AND am.status = 'commandé'
        AND (b.achat_status = 'validé' OR b.achat_status = 'en_cours')
    ");
    $bStmt->execute([':search' => $searchTerm]);
    $bResult = $bStmt->fetch(PDO::FETCH_ASSOC);

    if ($bResult['count'] > 0) {
        logEvent("Dispatching nécessaire pour '$productName' - trouvé dans besoins avec statut validé/en_cours");
        return true;
    }

    logEvent("Produit '$productName' a des commandes en attente mais aucune n'a un statut validé/en_cours - pas de dispatching");
    return false;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données JSON du corps de la requête
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['entries']) || !is_array($data['entries'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$pdo->beginTransaction();

try {
    // Récupérer le projet prioritaire s'il est spécifié
    $priorityProject = isset($data['priority_project']) ? $data['priority_project'] : '';
    logEvent("Projet prioritaire spécifié: " . ($priorityProject ? $priorityProject : "Aucun"));

    // Vérifier si le mode de gestion des commandes partielles est activé
    $handlePartialOrders = isset($data['handle_partial_orders']) && $data['handle_partial_orders'] === true;
    logEvent("Gestion des commandes partielles: " . ($handlePartialOrders ? "Activée" : "Désactivée"));

    // Vérifier si une facture a été fournie
    $invoice_id = null;
    if (isset($data['invoice']) && !empty($data['invoice'])) {
        $invoice = $data['invoice'];

        // Insérer les informations de la facture dans la table invoices
        $invoiceStmt = $pdo->prepare("
            INSERT INTO invoices 
            (invoice_number, file_path, original_filename, file_type, file_size, upload_date, upload_user_id, entry_date, supplier, notes) 
            VALUES (:invoice_number, :file_path, :original_filename, :file_type, :file_size, NOW(), :upload_user_id, CURDATE(), :supplier, :notes)
        ");

        $invoiceStmt->execute([
            ':invoice_number' => $invoice['invoice_number'] ?? null,
            ':file_path' => $invoice['file_path'],
            ':original_filename' => $invoice['original_filename'],
            ':file_type' => $invoice['file_type'],
            ':file_size' => $invoice['file_size'],
            ':upload_user_id' => $invoice['upload_user_id'] ?? null,
            ':supplier' => $invoice['supplier'] ?? null,
            ':notes' => $invoice['notes'] ?? null
        ]);

        $invoice_id = $pdo->lastInsertId();
        logEvent("Facture enregistrée avec ID: $invoice_id, Fichier: {$invoice['original_filename']}");
    }

    $dispatchingResults = []; // Pour stocker les résultats de dispatching
    $partialOrdersResults = []; // Pour stocker les résultats de dispatching des commandes partielles

    foreach ($data['entries'] as $entry) {
        $product_id = $entry['product_id'];
        $quantity = floatval($entry['quantity']);

        // Vérifier que la quantité est positive
        if ($quantity <= 0) {
            throw new Exception("La quantité doit être un nombre positif supérieur à zéro pour le produit");
        }
        $fournisseur = isset($entry['fournisseur']) ? $entry['fournisseur'] : '';
        $provenance = isset($entry['provenance']) ? $entry['provenance'] : '';
        $project_code = isset($entry['nom_projet']) ? $entry['nom_projet'] : ''; // Actuellement c'est le code projet
        $entry_type = isset($entry['entry_type']) ? $entry['entry_type'] : 'commande';

        // Variable pour suivre la quantité dispatchée
        $dispatchedQuantity = 0;

        // Si un projet est spécifié, récupérer le nom du client correspondant
        $project_name = '';
        if (!empty($project_code)) {
            $projectStmt = $pdo->prepare("SELECT nom_client FROM identification_projet WHERE code_projet = :code_projet LIMIT 1");
            $projectStmt->execute([':code_projet' => $project_code]);
            $projectData = $projectStmt->fetch(PDO::FETCH_ASSOC);

            if ($projectData) {
                $project_name = $projectData['nom_client'];
            }
        }

        // Si le type d'entrée est 'autre', utiliser la provenance fournie
        // Sinon (pour une commande), utiliser le fournisseur comme provenance
        $provenanceToUse = ($entry_type === 'autre') ? $provenance : $fournisseur;

        // 1. Récupérer les informations du produit
        $productStmt = $pdo->prepare("SELECT product_name, barcode FROM products WHERE id = :product_id");
        $productStmt->execute([':product_id' => $product_id]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception("Produit non trouvé avec l'ID: $product_id");
        }

        $productName = $product['product_name'];
        $barcode = $product['barcode'];

        logEvent("Entrée de produit: $productName (ID: $product_id, Code-barres: $barcode), Quantité: $quantity, Type d'entrée: $entry_type, Fournisseur/Provenance: $provenanceToUse");

        // Vérifier si le produit a un statut permettant le dispatching
        $shouldDispatch = shouldDispatchProduct($pdo, $productName);

        // MODIFICATION IMPORTANTE: Ajouter la totalité de la quantité au stock général
        // Mettre à jour la quantité dans la table products
        $updateStockStmt = $pdo->prepare("UPDATE products SET quantity = quantity + :quantity WHERE id = :product_id");
        $updateStockStmt->execute([':quantity' => $quantity, ':product_id' => $product_id]);

        // Ajoutez ce code:
        if ($logger) {
            $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = :product_id");
            $productStmt->execute([':product_id' => $product_id]);
            $productData = $productStmt->fetch(PDO::FETCH_ASSOC);

            $logger->logStockEntry($productData, $quantity, $provenanceToUse, $fournisseur);
        }

        if ($updateStockStmt->rowCount() === 0) {
            throw new Exception("Erreur lors de la mise à jour du produit ID: $product_id");
        }

        logEvent("$quantity unités de $productName ajoutées au stock général");

        // 4. Ajouter une entrée dans la table stock_movement
        $stockMvtStmt = $pdo->prepare("
    INSERT INTO stock_movement 
    (product_id, quantity, movement_type, provenance, nom_projet, destination, demandeur, date, fournisseur, invoice_id) 
    VALUES (:product_id, :quantity, 'entry', :provenance, :nom_projet, :destination, :demandeur, NOW(), :fournisseur, :invoice_id)
");

        // Si le produit ne doit pas être dispatché, le diriger explicitement vers le stock général
        if (!$shouldDispatch) {
            $stockMvtStmt->execute([
                ':product_id' => $product_id,
                ':quantity' => $quantity,
                ':provenance' => $provenanceToUse,
                ':nom_projet' => 'Stock général', // Pour les entrées directes en stock général
                ':destination' => 'Stock général', // Explicitement définir la destination
                ':demandeur' => 'Stock général',   // Explicitement définir le demandeur
                ':fournisseur' => $fournisseur,
                ':invoice_id' => $invoice_id
            ]);
        } else {
            // Pour les produits qui doivent être dispatchés, garder le comportement actuel
            $stockMvtStmt->execute([
                ':product_id' => $product_id,
                ':quantity' => $quantity,
                ':provenance' => $provenanceToUse,
                ':nom_projet' => $project_name, // Utiliser le nom du client si fourni
                ':destination' => '', // La destination sera remplie lors du dispatching
                ':demandeur' => '',   // Le demandeur sera rempli lors du dispatching
                ':fournisseur' => $fournisseur,
                ':invoice_id' => $invoice_id
            ]);
        }

        if ($stockMvtStmt->rowCount() === 0) {
            throw new Exception("Erreur lors de l'ajout du mouvement de stock pour le produit ID: $product_id");
        }

        $movement_id = $pdo->lastInsertId(); // Récupérer l'ID du mouvement de stock
        logEvent("Mouvement de stock #$movement_id enregistré pour $productName, quantité totale: $quantity, provenance: $provenanceToUse");

        // Si le produit n'a pas de statut validé ou en_cours, ne pas faire de dispatching
        if (!$shouldDispatch) {
            logEvent("Produit $productName ajouté directement au stock général sans dispatching car son statut n'est ni 'validé' ni 'en_cours'");
            continue; // Passer au produit suivant
        }

        // Variable pour suivre la quantité restante après traitement des commandes partielles
        $remainingAfterPartial = $quantity;

        // NOUVELLE SECTION: Traitement des commandes partielles si activé
        if ($handlePartialOrders) {
            logEvent("Vérification des commandes partielles pour $productName");

            // Recherche des commandes partielles pour ce produit
            // Modification: Rechercher les commandes basées sur qt_acheter plutôt que qt_restante
            $partialOrdersQuery = "
                (
                    -- Commandes partielles depuis expression_dym
                    SELECT ed.id, ed.idExpression, ed.designation, ed.qt_acheter, ed.qt_restante, 
                           ed.initial_qt_acheter, ed.unit, ed.prix_unitaire, ed.fournisseur, ed.quantity_stock,
                           ip.code_projet, ip.nom_client, 'expression_dym' AS source_table
                    FROM expression_dym ed
                    JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                    WHERE ed.qt_acheter > 0
                    AND ed.valide_achat = 'en_cours'
                    AND LOWER(ed.designation) LIKE :search
                )

                UNION ALL

                (
                    -- Commandes partielles depuis besoins
                    SELECT b.id, b.idBesoin AS idExpression, b.designation_article AS designation,
                           b.qt_acheter, (b.qt_demande - b.qt_acheter) AS qt_restante,
                           b.qt_demande AS initial_qt_acheter, b.caracteristique AS unit, 
                           NULL AS prix_unitaire, NULL AS fournisseur, b.quantity_dispatch_stock AS quantity_stock,
                           'SYS' AS code_projet, 
                           CONCAT('Demande ', COALESCE(d.client, 'Système')) AS nom_client,
                           'besoins' AS source_table
                    FROM besoins b
                    LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                    WHERE b.qt_acheter > 0
                    AND b.achat_status = 'en_cours'
                    AND LOWER(b.designation_article) LIKE :search
                )";

            // Utiliser une recherche flexible sur le nom du produit
            $searchTerm = '%' . strtolower($productName) . '%';
            $partialOrdersParams = [':search' => $searchTerm];

            // Vérifier s'il faut prioriser un projet spécifique
            if ($priorityProject) {
                $partialOrdersQuery = "
                    (
                        -- Commandes partielles de expression_dym pour le projet prioritaire
                        SELECT ed.id, ed.idExpression, ed.designation, ed.qt_acheter, ed.qt_restante, 
                               ed.initial_qt_acheter, ed.unit, ed.prix_unitaire, ed.fournisseur, ed.quantity_stock,
                               ip.code_projet, ip.nom_client, 'expression_dym' AS source_table
                        FROM expression_dym ed
                        JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                        WHERE ed.qt_acheter > 0
                        AND ed.valide_achat = 'en_cours'
                        AND LOWER(ed.designation) LIKE :search
                        AND ip.code_projet = :priority_project
                    )
                    
                    UNION ALL
                    
                    (
                        -- Commandes partielles de expression_dym pour les autres projets
                        SELECT ed.id, ed.idExpression, ed.designation, ed.qt_acheter, ed.qt_restante, 
                               ed.initial_qt_acheter, ed.unit, ed.prix_unitaire, ed.fournisseur, ed.quantity_stock,
                               ip.code_projet, ip.nom_client, 'expression_dym' AS source_table
                        FROM expression_dym ed
                        JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                        WHERE ed.qt_acheter > 0
                        AND ed.valide_achat = 'en_cours'
                        AND LOWER(ed.designation) LIKE :search
                        AND ip.code_projet != :priority_project
                    )
                    
                    UNION ALL
                    
                    (
                        -- Commandes partielles de besoins
                        SELECT b.id, b.idBesoin AS idExpression, b.designation_article AS designation,
                               b.qt_acheter, (b.qt_demande - b.qt_acheter) AS qt_restante,
                               b.qt_demande AS initial_qt_acheter, b.caracteristique AS unit, 
                               NULL AS prix_unitaire, NULL AS fournisseur, b.quantity_dispatch_stock AS quantity_stock,
                               'SYS' AS code_projet, 
                               CONCAT('Demande ', COALESCE(d.client, 'Système')) AS nom_client,
                               'besoins' AS source_table
                        FROM besoins b
                        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                        WHERE b.qt_acheter > 0
                        AND b.achat_status = 'en_cours'
                        AND LOWER(b.designation_article) LIKE :search
                    )";

                $partialOrdersParams[':priority_project'] = $priorityProject;

                // Ajouter ORDER BY après les UNION ALL
                $partialOrdersQuery .= " ORDER BY source_table, code_projet, designation";
            } else {
                // Ajouter ORDER BY après les UNION ALL
                $partialOrdersQuery .= " ORDER BY source_table, id";
            }

            $partialOrdersStmt = $pdo->prepare($partialOrdersQuery);
            foreach ($partialOrdersParams as $param => $value) {
                $partialOrdersStmt->bindValue($param, $value);
            }
            $partialOrdersStmt->execute();
            $partialOrders = $partialOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

            logEvent("Nombre de commandes partielles trouvées pour $productName: " . count($partialOrders));

            // Si des commandes partielles sont trouvées, les traiter
            if (count($partialOrders) > 0) {
                foreach ($partialOrders as $order) {
                    // Vérifier s'il reste de la quantité à allouer
                    if ($remainingAfterPartial <= 0) {
                        break;
                    }

                    $orderId = $order['id'];
                    $expressionId = $order['idExpression'];
                    $designation = $order['designation'];
                    $sourceTable = $order['source_table'];

                    // Déterminer comment traiter selon la source
                    $commandedQty = floatval($order['qt_acheter']);
                    $initialQty = floatval($order['initial_qt_acheter'] ?? ($order['qt_acheter'] + $order['qt_restante']));
                    $orderProject = $order['code_projet'];
                    $orderClient = $order['nom_client'];

                    // IMPORTANT: Utiliser la valeur actuelle de quantity_stock ou initialiser à 0
                    $currentStockQty = floatval($order['quantity_stock'] ?? 0);

                    logEvent("Traitement de la commande partielle #$orderId pour le projet $orderProject ($orderClient), Quantité commandée: $commandedQty");

                    // CORRECTION: Allouer toute la quantité disponible, sans limiter à qt_restante
                    $allocatedQuantity = $remainingAfterPartial;
                    $remainingAfterPartial -= $allocatedQuantity;

                    // MODIFICATION: Mettre à jour quantity_stock selon la source
                    $newStockQty = $currentStockQty + $allocatedQuantity;

                    // CORRECTION: Vérifier si la commande est complètement satisfaite
                    // Une commande est complète si quantity_stock >= (qt_acheter + qt_restante)
                    $qtRestante = floatval($order['qt_restante']);
                    $totalRequired = $commandedQty + $qtRestante;
                    $isFullyCompleted = $newStockQty >= $totalRequired;

                    if ($sourceTable === 'besoins') {
                        // Mettre à jour la table besoins
                        $updateQuery = "
                            UPDATE besoins 
                            SET quantity_dispatch_stock = :quantity_stock";

                        // Si la commande est complètement satisfaite, mettre à jour le statut
                        if ($isFullyCompleted) {
                            $updateQuery .= ", achat_status = 'reçu'";
                        }

                        $updateQuery .= " WHERE id = :id";

                        $updateOrderStmt = $pdo->prepare($updateQuery);
                        $updateOrderStmt->execute([
                            ':quantity_stock' => $newStockQty,
                            ':id' => $orderId
                        ]);
                    } else {
                        // Mettre à jour la table expression_dym - CORRECTION: ne pas mettre à jour qt_restante
                        $updateQuery = "
                            UPDATE expression_dym 
                            SET quantity_stock = :quantity_stock";

                        // Si la commande est complètement satisfaite, mettre à jour le statut
                        if ($isFullyCompleted) {
                            $updateQuery .= ", valide_achat = 'reçu'";
                        }

                        $updateQuery .= " WHERE id = :id";

                        $updateOrderStmt = $pdo->prepare($updateQuery);
                        $updateOrderStmt->execute([
                            ':quantity_stock' => $newStockQty,
                            ':id' => $orderId
                        ]);
                    }

                    logEvent("Commande partielle #$orderId " . ($isFullyCompleted ? "COMPLÉTÉE" : "PARTIELLEMENT satisfaite") .
                        ", quantity_stock mise à jour: $newStockQty ($currentStockQty → $newStockQty)" .
                        ", besoin total: $totalRequired");

                    // Enregistrer les détails du dispatching de la commande partielle
                    $insertDispatchPartialStmt = $pdo->prepare("
                        INSERT INTO dispatch_details 
                        (movement_id, order_id, product_id, allocated, remaining, status, project, client, dispatch_date, notes, fournisseur)
                        VALUES 
                        (:movement_id, :order_id, :product_id, :allocated, :remaining, :status, :project, :client, NOW(), :notes, :fournisseur)");

                    $insertDispatchPartialStmt->execute([
                        ':movement_id' => $movement_id,
                        ':order_id' => $orderId,
                        ':product_id' => $product_id,
                        ':allocated' => $allocatedQuantity,
                        ':remaining' => $isFullyCompleted ? 0 : ($totalRequired - $newStockQty),
                        ':status' => $isFullyCompleted ? 'completed' : 'partial',
                        ':project' => $orderProject,
                        ':client' => $orderClient,
                        ':notes' => "Dispatching automatique pour commande partielle: $designation (Source: $sourceTable)",
                        ':fournisseur' => $fournisseur
                    ]);

                    // Construction des résultats pour les commandes partielles
                    if ($sourceTable === 'besoins') {
                        $getUpdatedInfoStmt = $pdo->prepare("
                            SELECT b.qt_acheter, (b.qt_demande - b.qt_acheter) as qt_restante, 
                                   b.achat_status as valide_achat, b.quantity_dispatch_stock as quantity_stock
                            FROM besoins b
                            WHERE b.id = :id
                        ");
                    } else {
                        $getUpdatedInfoStmt = $pdo->prepare("
                            SELECT ed.qt_acheter, ed.qt_restante, ed.valide_achat, ed.quantity_stock
                            FROM expression_dym ed
                            WHERE ed.id = :id
                        ");
                    }

                    $getUpdatedInfoStmt->execute([':id' => $orderId]);
                    $updatedInfo = $getUpdatedInfoStmt->fetch(PDO::FETCH_ASSOC);

                    // Ajouter aux résultats
                    $partialOrdersResults[] = [
                        'order_id' => $orderId,
                        'designation' => $designation,
                        'product_name' => $productName,
                        'project' => $orderProject,
                        'client' => $orderClient,
                        'allocated' => $allocatedQuantity,
                        'remaining' => $isFullyCompleted ? 0 : ($totalRequired - $newStockQty),
                        'quantity_stock' => $newStockQty,
                        'status' => $isFullyCompleted ? 'completed' : 'partial',
                        'final_status' => $updatedInfo['valide_achat'],
                        'fournisseur' => $fournisseur,
                        'source_table' => $sourceTable
                    ];
                }
            }
        }

        // 2. Préparation de la requête pour les commandes en attente normales
        // Utiliser la quantité restante après le traitement des commandes partielles
        $remainingQuantity = $remainingAfterPartial;

        // Requête qui combine les commandes en attente des deux sources
        // SOLUTION: Utiliser des sous-requêtes nommées avec UNION ALL et ORDER BY à l'extérieur
        $pendingOrdersQuery = "
            SELECT * FROM (
                -- Commandes en attente depuis expression_dym
                SELECT am.id, am.expression_id, am.designation, am.quantity, am.date_achat,
                       ip.code_projet, ip.nom_client,
                       ed.id as ed_id, ed.quantity_stock, ed.qt_acheter, ed.qt_restante, 
                       'expression_dym' AS source_table
                FROM achats_materiaux am
                JOIN identification_projet ip ON am.expression_id = ip.idExpression
                JOIN expression_dym ed ON (ed.idExpression = am.expression_id AND ed.designation = am.designation)
                WHERE am.status = 'commandé' 
                AND ed.valide_achat = 'validé'
                AND LOWER(am.designation) LIKE :search
                
                UNION ALL
                
                -- Commandes en attente depuis besoins
                SELECT am.id, am.expression_id, am.designation, am.quantity, am.date_achat,
                       'SYS' AS code_projet, 
                       CONCAT('Demande ', COALESCE(d.client, 'Système')) AS nom_client,
                       b.id as ed_id, b.quantity_dispatch_stock AS quantity_stock, b.qt_acheter, 
                       (b.qt_demande - b.qt_acheter) AS qt_restante, 
                       'besoins' AS source_table
                FROM achats_materiaux am
                JOIN besoins b ON (b.idBesoin = am.expression_id AND b.designation_article = am.designation)
                LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                WHERE am.status = 'commandé' 
                AND b.achat_status = 'validé'
                AND LOWER(am.designation) LIKE :search
            ) AS combined_orders";

        // Utiliser une recherche flexible sur le nom du produit
        $searchTerm = '%' . strtolower($productName) . '%';
        $pendingOrdersParams = [':search' => $searchTerm];

        // Vérifier si un projet prioritaire a été spécifié et l'utiliser dans la requête
        if ($priorityProject) {
            // Construire la requête pour prioriser les commandes du projet spécifié
            $pendingOrdersQuery = "
                SELECT * FROM (
                    -- Commandes de expression_dym pour le projet prioritaire
                    SELECT am.id, am.expression_id, am.designation, am.quantity, am.date_achat,
                           ip.code_projet, ip.nom_client,
                           ed.id as ed_id, ed.quantity_stock, ed.qt_acheter, ed.qt_restante, 
                           'expression_dym' AS source_table,
                           1 AS priority_order
                    FROM achats_materiaux am
                    JOIN identification_projet ip ON am.expression_id = ip.idExpression
                    JOIN expression_dym ed ON (ed.idExpression = am.expression_id AND ed.designation = am.designation)
                    WHERE am.status = 'commandé' 
                    AND ed.valide_achat = 'validé'
                    AND LOWER(am.designation) LIKE :search
                    AND ip.code_projet = :priority_project
                    
                    UNION ALL
                    
                    -- Commandes de expression_dym pour les autres projets
                    SELECT am.id, am.expression_id, am.designation, am.quantity, am.date_achat,
                           ip.code_projet, ip.nom_client,
                           ed.id as ed_id, ed.quantity_stock, ed.qt_acheter, ed.qt_restante, 
                           'expression_dym' AS source_table,
                           2 AS priority_order
                    FROM achats_materiaux am
                    JOIN identification_projet ip ON am.expression_id = ip.idExpression
                    JOIN expression_dym ed ON (ed.idExpression = am.expression_id AND ed.designation = am.designation)
                    WHERE am.status = 'commandé' 
                    AND ed.valide_achat = 'validé'
                    AND LOWER(am.designation) LIKE :search
                    AND ip.code_projet != :priority_project
                    
                    UNION ALL
                    
                    -- Commandes de besoins (toujours après les commandes du projet prioritaire)
                    SELECT am.id, am.expression_id, am.designation, am.quantity, am.date_achat,
                           'SYS' AS code_projet, 
                           CONCAT('Demande ', COALESCE(d.client, 'Système')) AS nom_client,
                           b.id as ed_id, b.quantity_dispatch_stock AS quantity_stock, b.qt_acheter, 
                           (b.qt_demande - b.qt_acheter) AS qt_restante, 
                           'besoins' AS source_table,
                           3 AS priority_order
                    FROM achats_materiaux am
                    JOIN besoins b ON (b.idBesoin = am.expression_id AND b.designation_article = am.designation)
                    LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                    WHERE am.status = 'commandé' 
                    AND b.achat_status = 'validé'
                    AND LOWER(am.designation) LIKE :search
                ) AS combined_orders
                ORDER BY priority_order, date_achat ASC";

            $pendingOrdersParams[':priority_project'] = $priorityProject;

            logEvent("Requête modifiée pour prioriser le projet: $priorityProject");
        } else {
            // Ordre standard par date d'achat si aucun projet prioritaire
            $pendingOrdersQuery .= " ORDER BY date_achat ASC";
        }

        $pendingOrdersStmt = $pdo->prepare($pendingOrdersQuery);
        foreach ($pendingOrdersParams as $param => $value) {
            $pendingOrdersStmt->bindValue($param, $value);
        }
        $pendingOrdersStmt->execute();
        $pendingOrders = $pendingOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

        logEvent("Recherche de commandes en attente pour '$productName', " . count($pendingOrders) . " trouvées.");

        // 3. Dispatcher la quantité entrante restante vers les commandes en attente normales
        foreach ($pendingOrders as $order) {
            // Passer à la commande suivante s'il ne reste plus de quantité à distribuer
            if ($remainingQuantity <= 0) {
                break;
            }

            $orderId = $order['id'];
            $expressionId = $order['expression_id'];
            $orderQuantity = floatval($order['quantity']);
            $orderProject = $order['code_projet'];
            $orderClient = $order['nom_client'];
            $edId = $order['ed_id']; // ID de l'entrée dans expression_dym ou besoins
            $sourceTable = $order['source_table'];

            // IMPORTANT: Utiliser la valeur actuelle de quantity_stock ou initialiser à 0
            $currentStockQty = floatval($order['quantity_stock'] ?? 0);
            $qtAcheter = floatval($order['qt_acheter'] ?? 0);
            $qtRestante = floatval($order['qt_restante'] ?? 0);

            logEvent("Traitement de la commande #$orderId (source: $sourceTable) pour le projet $orderProject ($orderClient), Quantité commandée: $orderQuantity");

            // Calculer la quantité à attribuer à cette commande
            $allocatedQuantity = min($remainingQuantity, $orderQuantity);
            $remainingQuantity -= $allocatedQuantity;

            // Mettre à jour la quantité dispatchée
            $dispatchedQuantity += $allocatedQuantity;

            // Vérifier si original_quantity est déjà défini, sinon l'initialiser
            $checkOriginalQty = $pdo->prepare("SELECT original_quantity FROM achats_materiaux WHERE id = :order_id");
            $checkOriginalQty->execute([':order_id' => $orderId]);
            $originalQtyData = $checkOriginalQty->fetch(PDO::FETCH_ASSOC);

            if ($originalQtyData['original_quantity'] === null) {
                // Si c'est la première modification, définir original_quantity
                $updateOriginalQty = $pdo->prepare("
                    UPDATE achats_materiaux 
                    SET original_quantity = :original_qty 
                    WHERE id = :order_id
                ");
                $updateOriginalQty->execute([
                    ':original_qty' => $orderQuantity,
                    ':order_id' => $orderId
                ]);

                logEvent("Quantité originale sauvegardée: $orderQuantity pour la commande #$orderId");
            }

            // MODIFICATION: Uniquement mettre à jour quantity_stock
            $newStockQty = $currentStockQty + $allocatedQuantity;

            // CORRECTION: Vérifier si la commande est complètement satisfaite
            // Une commande est complète si quantity_stock >= (qt_acheter + qt_restante)
            $totalRequired = $qtAcheter + $qtRestante;
            $isFullyCompleted = $newStockQty >= $totalRequired;

            logEvent("Vérification de la commande: quantity_stock=$newStockQty, besoin total=$totalRequired, statut: " .
                ($isFullyCompleted ? "COMPLET" : "PARTIEL"));

            // Mise à jour différente selon la source
            if ($sourceTable === 'besoins') {
                $updateQuery = "
                    UPDATE besoins 
                    SET quantity_dispatch_stock = :quantity_stock";

                // Si la commande est complètement satisfaite, mettre à jour le statut également
                if ($isFullyCompleted) {
                    $updateQuery .= ", achat_status = 'reçu'";
                }

                $updateQuery .= " WHERE id = :id";

                $updateExpressionStmt = $pdo->prepare($updateQuery);
                $updateExpressionStmt->execute([
                    ':quantity_stock' => $newStockQty,
                    ':id' => $edId
                ]);

                logEvent("Entrée #$edId dans besoins mise à jour, quantity_stock: $newStockQty ($currentStockQty → $newStockQty)");
            } else {
                $updateQuery = "
                    UPDATE expression_dym 
                    SET quantity_stock = :quantity_stock";

                // Si la commande est complètement satisfaite, mettre à jour le statut également
                if ($isFullyCompleted) {
                    $updateQuery .= ", valide_achat = 'reçu'";
                }

                $updateQuery .= " WHERE id = :id";

                $updateExpressionStmt = $pdo->prepare($updateQuery);
                $updateExpressionStmt->execute([
                    ':quantity_stock' => $newStockQty,
                    ':id' => $edId
                ]);

                logEvent("Entrée #$edId dans expression_dym mise à jour, quantity_stock: $newStockQty ($currentStockQty → $newStockQty)");
            }

            // Si toute la quantité de la commande est satisfaite, mettre à jour le statut dans achats_materiaux
            if ($isFullyCompleted) {
                $updateOrderStmt = $pdo->prepare("
                    UPDATE achats_materiaux 
                    SET status = 'reçu', date_reception = NOW() 
                    WHERE id = :order_id
                ");
                $updateOrderStmt->execute([':order_id' => $orderId]);

                logEvent("Commande #$orderId COMPLÉTÉE et marquée comme 'reçu'");
            } else {
                logEvent("Commande #$orderId PARTIELLEMENT satisfaite");
            }

            // Enregistrer les détails du dispatching dans la table dispatch_details
            $insertDispatchStmt = $pdo->prepare("
                INSERT INTO dispatch_details 
                (movement_id, order_id, product_id, allocated, remaining, status, project, client, dispatch_date, notes, fournisseur)
                VALUES 
                (:movement_id, :order_id, :product_id, :allocated, :remaining, :status, :project, :client, NOW(), :notes, :fournisseur)
            ");

            $insertDispatchStmt->execute([
                ':movement_id' => $movement_id,
                ':order_id' => $orderId,
                ':product_id' => $product_id,
                ':allocated' => $allocatedQuantity,
                ':remaining' => $isFullyCompleted ? 0 : ($totalRequired - $newStockQty),
                ':status' => $isFullyCompleted ? 'completed' : 'partial',
                ':project' => $orderProject,
                ':client' => $orderClient,
                ':notes' => $isFullyCompleted ? "Dispatching automatique pour $productName (Source: $sourceTable)" : "Dispatching partiel automatique pour $productName (Source: $sourceTable)",
                ':fournisseur' => $fournisseur
            ]);

            $dispatchingResults[] = [
                'order_id' => $orderId,
                'product_name' => $productName,
                'project' => $orderProject,
                'client' => $orderClient,
                'allocated' => $allocatedQuantity,
                'status' => $isFullyCompleted ? 'completed' : 'partial',
                'remaining' => $isFullyCompleted ? 0 : ($totalRequired - $newStockQty),
                'quantity_stock' => $newStockQty,
                'fournisseur' => $fournisseur,
                'source_table' => $sourceTable
            ];
        }

        // Calculer la quantité restante après dispatching
        $remainingAfterDispatch = $remainingQuantity;

        // Si une quantité reste après le dispatching, créer un mouvement spécifique pour cette quantité
        // Fiabiliser la création de l'entrée de stock général
        if ($remainingAfterDispatch > 0.001) { // Utiliser une tolérance pour éviter les problèmes d'arrondi
            // Utiliser une transaction imbriquée spécifiquement pour cette opération critique
            try {
                $savepoint = "create_stock_general_" . mt_rand(10000, 99999);
                $pdo->exec("SAVEPOINT $savepoint");

                // Créer un mouvement distinct pour la quantité restante vers le stock général
                $stockMvtRemainingStmt = $pdo->prepare("
            INSERT INTO stock_movement 
            (product_id, quantity, movement_type, provenance, nom_projet, destination, date, fournisseur, invoice_id) 
            VALUES (:product_id, :quantity, 'entry', :provenance, 'Stock général', 'Stock général', NOW(), :fournisseur, :invoice_id)
        ");

                // S'assurer que tous les paramètres sont correctement définis
                $remainingParams = [
                    ':product_id' => $product_id,
                    ':quantity' => $remainingAfterDispatch,
                    ':provenance' => $provenanceToUse,
                    ':fournisseur' => $fournisseur,
                    ':invoice_id' => $invoice_id
                ];

                // Tentative d'insertion - en cas d'échec, réessayer jusqu'à 3 fois
                $success = false;
                $attempts = 0;
                $maxAttempts = 3;
                $lastError = null;

                while (!$success && $attempts < $maxAttempts) {
                    $attempts++;
                    try {
                        $stockMvtRemainingStmt->execute($remainingParams);
                        $remainingMovementId = $pdo->lastInsertId();

                        if ($remainingMovementId) {
                            // Vérifier que l'insertion a bien fonctionné
                            $checkInsertStmt = $pdo->prepare("SELECT id FROM stock_movement WHERE id = :id");
                            $checkInsertStmt->execute([':id' => $remainingMovementId]);
                            $result = $checkInsertStmt->fetch(PDO::FETCH_ASSOC);

                            if ($result) {
                                $success = true;
                                logEvent("Mouvement de stock #$remainingMovementId créé pour le reste non dispatché: $remainingAfterDispatch unités de $productName (réussi à la tentative $attempts)");
                            }
                        }
                    } catch (Exception $attemptError) {
                        $lastError = $attemptError;
                        logEvent("Échec de tentative $attempts pour créer le mouvement de stock général: " . $attemptError->getMessage());
                        // Bref délai avant nouvel essai
                        usleep(100000); // 100ms
                    }
                }

                // Si toutes les tentatives ont échoué, lancer une exception détaillée
                if (!$success) {
                    $errorMessage = "Échec après $maxAttempts tentatives de création du mouvement de stock général. ";
                    $errorMessage .= $lastError ? "Dernière erreur: " . $lastError->getMessage() : "Aucune erreur spécifique.";
                    throw new Exception($errorMessage);
                }

                $pdo->exec("RELEASE SAVEPOINT $savepoint");
            } catch (Exception $e) {
                $pdo->exec("ROLLBACK TO SAVEPOINT $savepoint");
                logEvent("ERREUR critique lors de la création du mouvement pour le reste: " . $e->getMessage());

                // Tenter une insertion directe comme dernier recours (hors transaction)
                try {
                    $directQuery = "
                INSERT INTO stock_movement 
                (product_id, quantity, movement_type, provenance, nom_projet, destination, date, fournisseur, invoice_id) 
                VALUES (:product_id, :quantity, 'entry', :provenance, 'Stock général', 'Stock général', NOW(), :fournisseur, :invoice_id)
            ";

                    $directStmt = $pdo->prepare($directQuery);
                    foreach ($remainingParams as $key => $value) {
                        $directStmt->bindValue($key, $value);
                    }

                    $directResult = $directStmt->execute();
                    $directId = $pdo->lastInsertId();

                    if ($directResult && $directId) {
                        logEvent("RÉCUPÉRATION D'URGENCE: Mouvement de stock #$directId créé pour le reste non dispatché après échec critique.");
                    } else {
                        logEvent("ÉCHEC TOTAL: Impossible de créer le mouvement de stock pour le reste malgré la récupération d'urgence.");
                    }
                } catch (Exception $directEx) {
                    logEvent("ÉCHEC CATASTROPHIQUE: " . $directEx->getMessage());
                }
            }
        } else {
            // Si le reste est nul ou négligeable (inférieur à 0.001), considérer qu'il n'y a pas de reste
            logEvent("Aucun reste après dispatching pour $productName (quantité: $quantity, dispatché: $dispatchedQuantity)");
        }

        // Mettre à jour les commandes achats_materiaux avec le fournisseur utilisé
        if (!empty($fournisseur) && !empty($dispatchingResults)) {
            foreach ($dispatchingResults as $result) {
                $updateFournisseurStmt = $pdo->prepare("
                    UPDATE achats_materiaux 
                    SET fournisseur = :fournisseur
                    WHERE id = :order_id AND (fournisseur IS NULL OR fournisseur = '')
                ");
                $updateFournisseurStmt->execute([
                    ':fournisseur' => $fournisseur,
                    ':order_id' => $result['order_id']
                ]);

                logEvent("Fournisseur mis à jour pour la commande #{$result['order_id']}: $fournisseur");
            }
        }

        logEvent("Fin du traitement du mouvement #$movement_id");
    }

    $pdo->commit();

    if ($invoice_id) {
        $logger = getLogger();
        if ($logger) {
            $invoiceData = [
                'id' => $invoice_id,
                'original_filename' => $invoice['original_filename'] ?? 'Facture sans nom',
                'supplier' => $invoice['supplier'] ?? null
            ];
            $logger->logInvoiceUpload($invoiceData);
        }
    }

    $response = [
        'success' => true,
        'message' => 'Entrées de stock ajoutées avec succès',
        'dispatching' => $dispatchingResults,
        'priority_project' => $priorityProject, // Inclure l'information sur le projet prioritaire dans la réponse
        'partial_orders' => $partialOrdersResults // Inclure les résultats des commandes partielles
    ];

    // Ajouter les informations de la facture à la réponse si disponible
    if ($invoice_id) {
        $response['invoice_id'] = $invoice_id;
    }

    echo json_encode($response);

    logEvent("Transaction VALIDÉE avec succès");

} catch (Exception $e) {
    $pdo->rollBack();
    logEvent("ERREUR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout des entrées: ' . $e->getMessage()]);
}

/**
 * Vérifie si une commande est complètement satisfaite
 * en comparant la quantité en stock avec la quantité requise
 * 
 * @param float $stockQty Quantité en stock
 * @param float $totalRequired Quantité totale requise
 * @return bool True si la commande est complètement satisfaite
 */
function isOrderFullyCompleted($stockQty, $totalRequired)
{
    // Utiliser une petite valeur de tolérance pour les erreurs d'arrondi
    return $stockQty >= ($totalRequired - 0.001);
}

/**
 * Vérifie et met à jour le statut des commandes non partielles
 * 
 * @param PDO $pdo Instance de connexion à la base de données
 * @param int $orderId ID de la commande
 * @param float $allocatedQuantity Quantité allouée à la commande
 * @param string $sourceTable Table source ('expression_dym' ou 'besoins')
 * @return bool True si la mise à jour a réussi
 */
function updateNonPartialOrderStatus($pdo, $orderId, $allocatedQuantity, $sourceTable = 'expression_dym')
{
    try {
        // Récupérer les informations de la commande selon la source
        if ($sourceTable === 'besoins') {
            $orderQuery = "SELECT am.*, b.quantity_dispatch_stock AS quantity_stock, b.id as ed_id,
                          b.qt_demande, b.qt_acheter
                          FROM achats_materiaux am
                          LEFT JOIN besoins b ON (b.idBesoin = am.expression_id AND b.designation_article = am.designation)
                          WHERE am.id = :order_id";
        } else {
            $orderQuery = "SELECT am.*, ed.quantity_stock, ed.id as ed_id,
                          ed.qt_acheter, ed.qt_restante 
                          FROM achats_materiaux am
                          LEFT JOIN expression_dym ed ON (ed.idExpression = am.expression_id AND ed.designation = am.designation)
                          WHERE am.id = :order_id";
        }

        $orderStmt = $pdo->prepare($orderQuery);
        $orderStmt->bindParam(':order_id', $orderId);
        $orderStmt->execute();
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return false;
        }

        // Vérifier si le stock est suffisant pour considérer la commande comme reçue
        $orderQuantity = floatval($order['quantity']);
        $stockQuantity = floatval($order['quantity_stock'] ?? 0) + $allocatedQuantity;
        $qtAcheter = floatval($order['qt_acheter'] ?? 0);
        $qtRestante = floatval($order['qt_restante'] ?? 0);
        $totalRequired = $qtAcheter + $qtRestante;

        // Si le stock est supérieur ou égal à la quantité totale requise, marquer comme reçu
        if ($stockQuantity >= $totalRequired) {
            // Mettre à jour le statut dans achats_materiaux
            $updateOrderStmt = $pdo->prepare("
                UPDATE achats_materiaux 
                SET status = 'reçu', 
                    date_reception = NOW() 
                WHERE id = :order_id
            ");
            $updateOrderStmt->execute([':order_id' => $orderId]);

            // Mettre à jour le statut dans la table source
            if ($sourceTable === 'besoins') {
                if ($order['ed_id']) {
                    $updateSourceStmt = $pdo->prepare("
                        UPDATE besoins 
                        SET achat_status = 'reçu',
                            valide_finance = 'validé',
                            quantity_dispatch_stock = :stock_quantity
                        WHERE id = :ed_id
                    ");
                    $updateSourceStmt->execute([
                        ':stock_quantity' => $stockQuantity,
                        ':ed_id' => $order['ed_id']
                    ]);
                }
            } else {
                if ($order['ed_id']) {
                    $updateSourceStmt = $pdo->prepare("
                        UPDATE expression_dym 
                        SET valide_achat = 'reçu',
                            valide_finance = 'validé',
                            quantity_stock = :stock_quantity
                        WHERE id = :ed_id
                    ");
                    $updateSourceStmt->execute([
                        ':stock_quantity' => $stockQuantity,
                        ':ed_id' => $order['ed_id']
                    ]);
                }
            }

            return true;
        }

        return false;
    } catch (Exception $e) {
        error_log("Erreur dans updateNonPartialOrderStatus: " . $e->getMessage());
        return false;
    }
}