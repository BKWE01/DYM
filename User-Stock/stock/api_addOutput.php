<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure le logger
include_once __DIR__ . '/trace/include_logger.php';
$logger = getLogger();

try {
    // Récupération des données envoyées
    $data = json_decode(file_get_contents('php://input'), true);
    $output = $data['output'];

    // Démarrer une transaction
    $pdo->beginTransaction();

    foreach ($output as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $output_type = isset($item['output_type']) ? $item['output_type'] : 'standard';

        // Déterminer le type de mouvement et les données associées
        if ($output_type === 'supplier-return') {
            // Pour les retours fournisseur
            $movement_type = 'output'; // Toujours une sortie du stock
            $destination = $item['destination']; // Déjà formaté comme "Retour fournisseur: NomFournisseur"
            $demandeur = $item['demandeur']; // Habituellement "Système"
            $provenance = 'Stock général';

            // Vérifier si un projet est associé au retour fournisseur
            $project_nom = isset($item['project_name']) && !empty($item['project_name']) ? trim($item['project_name']) : "";
            $project_code = isset($item['project_code']) && !empty($item['project_code']) ? trim($item['project_code']) : "";
            $id_project = isset($item['id_project']) ? $item['id_project'] : null;

            // Si nous n'avons pas les données du projet mais que id_project est renseigné
            if (empty($project_nom) && !empty($id_project)) {
                // Extraire le code du projet (au cas où l'utilisateur aurait sélectionné dans le format "CODE - NOM")
                if (strpos($id_project, ' - ') !== false) {
                    $parts = explode(' - ', $id_project);
                    $project_code = trim($parts[0]);
                    $project_nom = trim($parts[1]);
                } else {
                    $project_code = trim($id_project);

                    // Si nous n'avons que le code, rechercher le nom du client dans la base de données
                    if (empty($project_nom)) {
                        $stmt = $pdo->prepare("
                            SELECT nom_client 
                            FROM identification_projet
                            WHERE code_projet = :code_projet
                            LIMIT 1
                        ");
                        $stmt->execute([':code_projet' => $project_code]);
                        $project = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($project) {
                            $project_nom = $project['nom_client'];
                        }
                    }
                }
            }

            // Associer le projet au retour fournisseur si fourni
            $nom_projet = !empty($project_nom) ? $project_nom : null;

            // Enregistrer les informations supplémentaires dans les notes
            $notes = "Motif: " . $item['return_reason'];
            if (!empty($item['return_comment'])) {
                $notes .= " - " . $item['return_comment'];
            }
            // Ajouter le projet associé dans les notes si disponible
            if (!empty($project_code) && !empty($project_nom)) {
                $notes .= " - Projet: " . $project_code . " (" . $project_nom . ")";
            }

            // Récupérer les infos du fournisseur pour le log
            $supplier = $item['supplier'];
            $supplier_id = !empty($item['supplier_id']) ? $item['supplier_id'] : null;

            // Si un projet est défini, vérifier s'il a des réservations pour ce produit
            if (!empty($project_code)) {
                // Vérifier dans expression_dym
                $stmtExpression = $pdo->prepare("
                    SELECT p.product_name, e.designation, e.idExpression, i.nom_client, i.code_projet
                    FROM expression_dym e
                    JOIN products p ON p.product_name = e.designation
                    JOIN identification_projet i ON i.idExpression = e.idExpression
                    WHERE (i.code_projet = :code_projet OR i.idExpression = :id_project) AND p.id = :product_id
                ");
                $stmtExpression->execute([
                    ':code_projet' => $project_code,
                    ':id_project' => $project_code, // Au cas où l'ID d'expression est fourni directement
                    ':product_id' => $product_id
                ]);
                $reservationExpression = $stmtExpression->fetch(PDO::FETCH_ASSOC);

                if ($reservationExpression) {
                    // Mise à jour de la quantité réservée dans la table products
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity) 
                        WHERE id = :product_id
                    ");
                    $stmt->execute([
                        ':quantity' => $quantity,
                        ':product_id' => $product_id
                    ]);

                    // Mise à jour également de la quantité réservée dans expression_dym
                    $stmt = $pdo->prepare("
                        UPDATE expression_dym 
                        SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity) 
                        WHERE idExpression = :id_expression AND LOWER(designation) = LOWER(:designation)
                    ");
                    $stmt->execute([
                        ':quantity' => $quantity,
                        ':id_expression' => $reservationExpression['idExpression'],
                        ':designation' => $reservationExpression['designation']
                    ]);
                } else {
                    // Vérifier dans besoins si c'est un besoin système
                    $stmtBesoins = $pdo->prepare("
                        SELECT b.*, p.product_name
                        FROM besoins b
                        JOIN products p ON p.id = b.product_id OR LOWER(p.product_name) = LOWER(b.designation_article)
                        WHERE b.idBesoin = :id_besoin AND p.id = :product_id
                    ");
                    $stmtBesoins->execute([
                        ':id_besoin' => $project_code, // Le code projet pourrait être l'ID du besoin
                        ':product_id' => $product_id
                    ]);
                    $reservationBesoin = $stmtBesoins->fetch(PDO::FETCH_ASSOC);

                    if ($reservationBesoin) {
                        // Mise à jour de la quantité réservée dans la table products
                        $stmt = $pdo->prepare("
                            UPDATE products 
                            SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity) 
                            WHERE id = :product_id
                        ");
                        $stmt->execute([
                            ':quantity' => $quantity,
                            ':product_id' => $product_id
                        ]);

                        // Mise à jour également de la quantité réservée dans besoins
                        $stmt = $pdo->prepare("
                            UPDATE besoins 
                            SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity) 
                            WHERE id = :id_besoin AND product_id = :product_id
                        ");
                        $stmt->execute([
                            ':quantity' => $quantity,
                            ':id_besoin' => $reservationBesoin['id'],
                            ':product_id' => $product_id
                        ]);
                    }
                }
            }
        } else {
            // Pour les sorties standard
            $movement_type = 'output';
            $destination = $item['destination'];
            $demandeur = $item['demandeur'];
            $provenance = 'Stock général';

            // Utiliser directement project_name s'il est disponible
            $project_nom = isset($item['project_name']) && !empty($item['project_name']) ? trim($item['project_name']) : "";
            $project_code = isset($item['project_code']) && !empty($item['project_code']) ? trim($item['project_code']) : "";
            $id_project = isset($item['id_project']) ? $item['id_project'] : null;

            // Si nous n'avons pas les données du projet mais que id_project est renseigné
            if (empty($project_nom) && !empty($id_project)) {
                // Extraire le code du projet (au cas où l'utilisateur aurait sélectionné dans le format "CODE - NOM")
                if (strpos($id_project, ' - ') !== false) {
                    $parts = explode(' - ', $id_project);
                    $project_code = trim($parts[0]);
                    $project_nom = trim($parts[1]);
                } else {
                    $project_code = trim($id_project);

                    // Si nous n'avons que le code, rechercher le nom du client dans la base de données
                    if (empty($project_nom)) {
                        // D'abord, vérifier si c'est un projet normal
                        $stmt = $pdo->prepare("
                            SELECT nom_client 
                            FROM identification_projet
                            WHERE code_projet = :code_projet
                            LIMIT 1
                        ");
                        $stmt->execute([':code_projet' => $project_code]);
                        $project = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($project) {
                            $project_nom = $project['nom_client'];
                        } else {
                            // Si ce n'est pas un projet normal, vérifier si c'est un besoin système
                            // Extraire l'ID du client depuis la table demandeur
                            $stmt = $pdo->prepare("
                                SELECT d.client 
                                FROM besoins b
                                LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                                WHERE b.idBesoin = :id_besoin
                                LIMIT 1
                            ");
                            $stmt->execute([':id_besoin' => $project_code]);
                            $besoin = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($besoin) {
                                $project_nom = 'Demande ' . ($besoin['client'] ? $besoin['client'] : 'Système');
                            }
                        }
                    }
                }
            }

            // Vérifier les réservations dans les deux tables
            $reservation = null;
            $isBesoinsReservation = false;

            // 1. Vérifier d'abord dans expression_dym
            if (!empty($project_code)) {
                $stmt = $pdo->prepare("
                    SELECT p.product_name, e.designation, e.idExpression, i.nom_client, i.code_projet
                    FROM expression_dym e
                    JOIN products p ON p.product_name = e.designation
                    JOIN identification_projet i ON i.idExpression = e.idExpression
                    WHERE (i.code_projet = :code_projet OR i.idExpression = :id_project) AND p.id = :product_id
                ");
                $stmt->execute([
                    ':code_projet' => $project_code,
                    ':id_project' => $project_code, // Au cas où l'ID d'expression est fourni directement
                    ':product_id' => $product_id
                ]);
                $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($reservation) {
                    // Utiliser les informations de la réservation si elles sont trouvées
                    $project_nom = $reservation['nom_client'];
                    $project_code = $reservation['code_projet'];

                    // Mettre à jour la quantité réservée dans la table products
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity) 
                        WHERE id = :product_id
                    ");
                    $stmt->execute([
                        ':quantity' => $quantity,
                        ':product_id' => $product_id
                    ]);

                    // Mise à jour également de la quantité réservée dans expression_dym
                    $stmt = $pdo->prepare("
                        UPDATE expression_dym 
                        SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity) 
                        WHERE idExpression = :id_expression AND LOWER(designation) = LOWER(:designation)
                    ");
                    $stmt->execute([
                        ':quantity' => $quantity,
                        ':id_expression' => $reservation['idExpression'],
                        ':designation' => $reservation['designation']
                    ]);
                }
            }

            // 2. Si aucune réservation n'a été trouvée dans expression_dym, vérifier dans besoins
            if (!$reservation && !empty($project_code)) {
                $stmt = $pdo->prepare("
                    SELECT b.*, p.product_name
                    FROM besoins b
                    JOIN products p ON p.id = b.product_id OR LOWER(p.product_name) = LOWER(b.designation_article)
                    WHERE (b.idBesoin = :id_besoin OR b.id = :direct_id) AND p.id = :product_id
                    AND b.quantity_reserved > 0
                ");
                $stmt->execute([
                    ':id_besoin' => $project_code, // Le code projet pourrait être l'ID du besoin
                    ':direct_id' => is_numeric($project_code) ? intval($project_code) : 0,
                    ':product_id' => $product_id
                ]);
                $besoinReservation = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($besoinReservation) {
                    $isBesoinsReservation = true;
                    
                    // Mettre à jour le nom du projet si nécessaire
                    $stmt = $pdo->prepare("
                        SELECT d.client FROM demandeur d
                        WHERE d.idBesoin = :id_besoin
                        LIMIT 1
                    ");
                    $stmt->execute([':id_besoin' => $besoinReservation['idBesoin']]);
                    $demandeurInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($demandeurInfo) {
                        $project_nom = 'Demande ' . $demandeurInfo['client'];
                    } else {
                        $project_nom = 'Demande Système';
                    }
                    $project_code = 'SYS';

                    // Mettre à jour la quantité réservée dans la table products
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity) 
                        WHERE id = :product_id
                    ");
                    $stmt->execute([
                        ':quantity' => $quantity,
                        ':product_id' => $product_id
                    ]);

                    // Mise à jour également de la quantité réservée dans besoins
                    $stmt = $pdo->prepare("
                        UPDATE besoins 
                        SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity) 
                        WHERE id = :id_besoin
                    ");
                    $stmt->execute([
                        ':quantity' => $quantity,
                        ':id_besoin' => $besoinReservation['id']
                    ]);
                }
            }

            $nom_projet = !empty($project_nom) ? $project_nom : null;
            $notes = null; // Pas de notes pour les sorties standard
        }

        // Mise à jour de la quantité dans la table products
        $stmt = $pdo->prepare("UPDATE products SET quantity = quantity - :quantity WHERE id = :product_id");
        $stmt->execute([
            ':quantity' => $quantity,
            ':product_id' => $product_id
        ]);

        // Vérification que la quantité n'est pas devenue négative
        $stmt = $pdo->prepare("SELECT quantity FROM products WHERE id = :product_id");
        $stmt->execute([':product_id' => $product_id]);
        $current_quantity = $stmt->fetchColumn();

        if ($current_quantity < 0) {
            throw new Exception("La quantité du produit ID $product_id est devenue négative.");
        }

        // Après avoir vérifié que la quantité n'est pas devenue négative, ajoutez:
        if ($logger) {
            $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = :product_id");
            $productStmt->execute([':product_id' => $product_id]);
            $productData = $productStmt->fetch(PDO::FETCH_ASSOC);

            if ($output_type === 'supplier-return') {
                // Log spécifique pour les retours fournisseur
                $logger->logSupplierReturn($productData, $quantity, $supplier, $item['return_reason'], $item['return_comment']);
            } else {
                // Log standard pour les sorties
                $logger->logStockOutput($productData, $quantity, $destination, $demandeur, $nom_projet);
            }
        }

        // Ajout d'une entrée dans la table stock_movement
        $stmt = $pdo->prepare("
            INSERT INTO stock_movement (
                product_id, quantity, movement_type, destination, demandeur, nom_projet, provenance, notes, date
            ) VALUES (
                :product_id, :quantity, :movement_type, :destination, :demandeur, :nom_projet, :provenance, :notes, NOW()
            )
        ");

        $stmt->execute([
            ':product_id' => $product_id,
            ':quantity' => $quantity,
            ':movement_type' => $movement_type,
            ':destination' => $destination,
            ':demandeur' => $demandeur,
            ':nom_projet' => $nom_projet,
            ':provenance' => $provenance,
            ':notes' => $notes
        ]);

        // Si c'est un retour fournisseur, enregistrer dans une table dédiée (si elle existe)
        if ($output_type === 'supplier-return' && tableExists($pdo, 'supplier_returns')) {
            $movement_id = $pdo->lastInsertId();

            // Récupérer l'ID du projet si un code projet est fourni
            $project_id = null;
            if (!empty($project_code)) {
                $stmt = $pdo->prepare("
                    SELECT id FROM identification_projet 
                    WHERE code_projet = :code_projet 
                    LIMIT 1
                ");
                $stmt->execute([':code_projet' => $project_code]);
                $project_result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($project_result) {
                    $project_id = $project_result['id'];
                }
            }

            // Vérifier si les colonnes liées au projet existent dans la table
            $has_project_columns = false;
            try {
                $check_columns = $pdo->query("SHOW COLUMNS FROM supplier_returns LIKE 'project_id'");
                $has_project_columns = ($check_columns->rowCount() > 0);
            } catch (Exception $e) {
                // Si la vérification échoue, supposer que les colonnes n'existent pas
                $has_project_columns = false;
            }

            if ($has_project_columns) {
                // Utiliser la requête avec les colonnes de projet
                $stmt = $pdo->prepare("
                    INSERT INTO supplier_returns (
                        movement_id, product_id, supplier_id, supplier_name, quantity, 
                        reason, comment, project_id, project_code, project_name, created_at
                    ) VALUES (
                        :movement_id, :product_id, :supplier_id, :supplier_name, :quantity, 
                        :reason, :comment, :project_id, :project_code, :project_name, NOW()
                    )
                ");

                $stmt->execute([
                    ':movement_id' => $movement_id,
                    ':product_id' => $product_id,
                    ':supplier_id' => $supplier_id,
                    ':supplier_name' => $supplier,
                    ':quantity' => $quantity,
                    ':reason' => $item['return_reason'],
                    ':comment' => $item['return_comment'],
                    ':project_id' => $project_id,
                    ':project_code' => $project_code,
                    ':project_name' => $project_nom
                ]);
            } else {
                // Utiliser la requête originale sans les colonnes de projet
                $stmt = $pdo->prepare("
                    INSERT INTO supplier_returns (
                        movement_id, product_id, supplier_id, supplier_name, quantity, reason, comment, created_at
                    ) VALUES (
                        :movement_id, :product_id, :supplier_id, :supplier_name, :quantity, :reason, :comment, NOW()
                    )
                ");

                $stmt->execute([
                    ':movement_id' => $movement_id,
                    ':product_id' => $product_id,
                    ':supplier_id' => $supplier_id,
                    ':supplier_name' => $supplier,
                    ':quantity' => $quantity,
                    ':reason' => $item['return_reason'],
                    ':comment' => $item['return_comment']
                ]);
            }
        }
    }

    // Valider la transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Sorties de stock effectuées avec succès.']);

} catch (Exception $e) {
    // En cas d'erreur, annuler la transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Erreur lors des sorties de stock : ' . $e->getMessage()]);
}

// Fonction utilitaire pour vérifier si une table existe
function tableExists($pdo, $table)
{
    try {
        $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}
?>