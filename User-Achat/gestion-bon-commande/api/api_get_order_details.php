<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit();
}

// Vérifier si l'ID du bon de commande est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID du bon de commande manquant']);
    exit();
}

$orderId = intval($_GET['id']);
$fullDetails = isset($_GET['full_details']) && $_GET['full_details'] == 1;

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // 1. MISE À JOUR : Récupérer les informations du bon de commande avec données de rejet
    $orderQuery = "SELECT 
                    po.id, 
                    po.order_number, 
                    po.download_reference,
                    po.expression_id, 
                    po.related_expressions,
                    po.file_path, 
                    po.fournisseur, 
                    po.montant_total, 
                    po.user_id, 
                    po.is_multi_project, 
                    po.generated_at,
                    po.signature_finance,
                    po.user_finance_id,
                    po.status,
                    po.rejected_at,
                    po.rejected_by_user_id,
                    po.rejection_reason,
                    u.name as username,
                    uf.name as finance_username,
                    ur.name as rejected_by_username,
                    CASE 
                        WHEN po.expression_id LIKE '%EXP_B%' THEN 'besoins' 
                        ELSE 'expression_dym' 
                    END as source_table
                  FROM purchase_orders po
                  LEFT JOIN users_exp u ON po.user_id = u.id
                  LEFT JOIN users_exp uf ON po.user_finance_id = uf.id
                  LEFT JOIN users_exp ur ON po.rejected_by_user_id = ur.id
                  WHERE po.id = ?";

    $orderStmt = $pdo->prepare($orderQuery);
    $orderStmt->execute([$orderId]);

    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Bon de commande non trouvé']);
        exit();
    }

    // 2. Récupérer les informations des projets associés
    $projects = [];
    $relatedExpressions = json_decode($order['related_expressions'], true);

    if ($relatedExpressions && is_array($relatedExpressions)) {
        // Traiter chaque expression selon sa source (expression_dym ou besoins)
        foreach ($relatedExpressions as $expressionId) {
            if (strpos($expressionId, 'EXP_B') !== false) {
                // C'est une expression système (besoins)
                $besoinQuery = "SELECT 
                              b.idBesoin as idExpression,
                              CONCAT('SYS-', d.service_demandeur) as code_projet,
                              d.client as nom_client,
                              d.motif_demande as description_projet,
                              'Système' as chefprojet
                            FROM besoins b
                            LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                            WHERE b.idBesoin = ?";

                $besoinStmt = $pdo->prepare($besoinQuery);
                $besoinStmt->execute([$expressionId]);
                $besoin = $besoinStmt->fetch(PDO::FETCH_ASSOC);

                if ($besoin) {
                    $projects[] = $besoin;
                }
            } else {
                // C'est une expression de projet (identification_projet)
                $projectQuery = "SELECT 
                              idExpression, 
                              code_projet, 
                              nom_client, 
                              description_projet,
                              chefprojet
                            FROM identification_projet 
                            WHERE idExpression = ?";

                $projectStmt = $pdo->prepare($projectQuery);
                $projectStmt->execute([$expressionId]);
                $project = $projectStmt->fetch(PDO::FETCH_ASSOC);

                if ($project) {
                    $projects[] = $project;
                }
            }
        }
    } else {
        // Pas d'expressions liées, traiter l'expression principale
        if ($order['source_table'] == 'besoins') {
            // Expression système
            $besoinQuery = "SELECT 
                          b.idBesoin as idExpression,
                          CONCAT('SYS-', d.service_demandeur) as code_projet,
                          d.client as nom_client,
                          d.motif_demande as description_projet,
                          'Système' as chefprojet
                        FROM besoins b
                        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                        WHERE b.idBesoin = ?";

            $besoinStmt = $pdo->prepare($besoinQuery);
            $besoinStmt->execute([$order['expression_id']]);
            $besoin = $besoinStmt->fetch(PDO::FETCH_ASSOC);

            if ($besoin) {
                $projects[] = $besoin;
            }
        } else {
            // Expression de projet
            $projectQuery = "SELECT 
                          idExpression, 
                          code_projet, 
                          nom_client, 
                          description_projet,
                          chefprojet
                        FROM identification_projet 
                        WHERE idExpression = ?";

            $projectStmt = $pdo->prepare($projectQuery);
            $projectStmt->execute([$order['expression_id']]);
            $project = $projectStmt->fetch(PDO::FETCH_ASSOC);

            if ($project) {
                $projects[] = $project;
            }
        }
    }

    // 3. Essayer de récupérer les matériaux associés au bon de commande
    $materials = [];
    $consolidatedMaterials = [];

    // Récupérer d'abord la date exacte du bon de commande pour s'en servir de référence
    $bonCommandeDate = $order['generated_at'];

    // Vérifier s'il existe une table qui enregistre les matériaux spécifiques à chaque bon de commande
    $checkTableQuery = "SHOW TABLES LIKE 'purchase_order_materials'";
    $checkTableStmt = $pdo->prepare($checkTableQuery);
    $checkTableStmt->execute();
    $tableExists = $checkTableStmt->rowCount() > 0;

    if ($tableExists) {
        // Si la table existe, récupérer directement les matériaux liés à ce bon de commande
        $materialsQuery = "SELECT 
                         material_id,
                         designation, 
                         quantity,
                         unit,
                         prix_unitaire,
                         fournisseur,
                         expression_id
                       FROM purchase_order_materials
                       WHERE purchase_order_id = ?
                       ORDER BY designation";

        $materialsStmt = $pdo->prepare($materialsQuery);
        $materialsStmt->execute([$orderId]);
        $materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Sinon, utiliser une méthode alternative basée sur les expressions et la date
        if (!empty($relatedExpressions) || !empty($order['expression_id'])) {
            // Convertir à nouveau pour s'assurer d'avoir un tableau
            $expressionIds = $relatedExpressions ?: [$order['expression_id']];

            // Séparation des IDs par source (expression_dym ou besoins)
            $projetsIds = [];
            $besoinsIds = [];

            foreach ($expressionIds as $expressionId) {
                if (strpos($expressionId, 'EXP_B') !== false) {
                    $besoinsIds[] = $expressionId;
                } else {
                    $projetsIds[] = $expressionId;
                }
            }

            // Récupérer les matériaux pour les projets
            if (!empty($projetsIds)) {
                $placeholders = implode(',', array_fill(0, count($projetsIds), '?'));

                $materialsProjetQuery = "SELECT 
                                    am.id,
                                    am.designation, 
                                    am.quantity,
                                    am.unit,
                                    am.prix_unitaire,
                                    am.fournisseur,
                                    am.date_achat,
                                    am.expression_id,
                                    ip.code_projet,
                                    ip.nom_client,
                                    'expression_dym' as source_table
                                FROM achats_materiaux am
                                LEFT JOIN identification_projet ip ON am.expression_id = ip.idExpression
                                WHERE am.expression_id IN ($placeholders)
                                AND DATE(am.date_achat) = DATE(?)
                                AND am.fournisseur = ?
                                ORDER BY am.designation";

                $materialsProjetStmt = $pdo->prepare($materialsProjetQuery);

                // Créer un tableau pour tous les paramètres
                $params = $projetsIds;
                // Ajouter la date et le fournisseur
                $params[] = $bonCommandeDate;
                $params[] = $order['fournisseur'];

                $materialsProjetStmt->execute($params);
                $materialsProjet = $materialsProjetStmt->fetchAll(PDO::FETCH_ASSOC);

                $materials = array_merge($materials, $materialsProjet);
            }

            // Récupérer les matériaux pour les besoins système
            if (!empty($besoinsIds)) {
                $placeholders = implode(',', array_fill(0, count($besoinsIds), '?'));

                $materialsBesoinsQuery = "SELECT 
                                      am.id,
                                      am.designation, 
                                      am.quantity,
                                      am.unit,
                                      am.prix_unitaire,
                                      am.fournisseur,
                                      am.date_achat,
                                      am.expression_id,
                                      CONCAT('SYS-', d.service_demandeur) as code_projet,
                                      d.client as nom_client,
                                      'besoins' as source_table
                                    FROM achats_materiaux am
                                    LEFT JOIN besoins b ON am.expression_id = b.idBesoin
                                    LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                                    WHERE am.expression_id IN ($placeholders)
                                    AND DATE(am.date_achat) = DATE(?)
                                    AND am.fournisseur = ?
                                    ORDER BY am.designation";

                $materialsBesoinsStmt = $pdo->prepare($materialsBesoinsQuery);

                // Créer un tableau pour tous les paramètres
                $params = $besoinsIds;
                // Ajouter la date et le fournisseur
                $params[] = $bonCommandeDate;
                $params[] = $order['fournisseur'];

                $materialsBesoinsStmt->execute($params);
                $materialsBesoins = $materialsBesoinsStmt->fetchAll(PDO::FETCH_ASSOC);

                $materials = array_merge($materials, $materialsBesoins);
            }

            // Si aucun matériau n'est trouvé avec la date exacte, élargir légèrement la recherche
            if (empty($materials)) {
                // Fonction pour récupérer avec un intervalle de temps
                $getMaterialsWithInterval = function ($ids, $isBesoins) use ($pdo, $order, $bonCommandeDate) {
                    if (empty($ids))
                        return [];

                    $placeholders = implode(',', array_fill(0, count($ids), '?'));

                    $query = $isBesoins ?
                        "SELECT 
                            am.id,
                            am.designation, 
                            am.quantity,
                            am.unit,
                            am.prix_unitaire,
                            am.fournisseur,
                            am.date_achat,
                            am.expression_id,
                            CONCAT('SYS-', d.service_demandeur) as code_projet,
                            d.client as nom_client,
                            'besoins' as source_table
                        FROM achats_materiaux am
                        LEFT JOIN besoins b ON am.expression_id = b.idBesoin
                        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                        WHERE am.expression_id IN ($placeholders)
                        AND am.fournisseur = ?
                        AND am.date_achat BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND DATE_ADD(?, INTERVAL 1 HOUR)
                        ORDER BY ABS(TIMESTAMPDIFF(SECOND, am.date_achat, ?))
                        LIMIT 50" :
                        "SELECT 
                            am.id,
                            am.designation, 
                            am.quantity,
                            am.unit,
                            am.prix_unitaire,
                            am.fournisseur,
                            am.date_achat,
                            am.expression_id,
                            ip.code_projet,
                            ip.nom_client,
                            'expression_dym' as source_table
                        FROM achats_materiaux am
                        LEFT JOIN identification_projet ip ON am.expression_id = ip.idExpression
                        WHERE am.expression_id IN ($placeholders)
                        AND am.fournisseur = ?
                        AND am.date_achat BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND DATE_ADD(?, INTERVAL 1 HOUR)
                        ORDER BY ABS(TIMESTAMPDIFF(SECOND, am.date_achat, ?))
                        LIMIT 50";

                    $stmt = $pdo->prepare($query);

                    // Créer un tableau pour tous les paramètres
                    $params = $ids;
                    // Ajouter le fournisseur et les dates
                    $params[] = $order['fournisseur'];
                    $params[] = $bonCommandeDate;
                    $params[] = $bonCommandeDate;
                    $params[] = $bonCommandeDate;

                    $stmt->execute($params);
                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                };

                // Obtenir les matériaux avec un intervalle de temps
                $materialsProjet = $getMaterialsWithInterval($projetsIds, false);
                $materialsBesoins = $getMaterialsWithInterval($besoinsIds, true);

                $materials = array_merge($materialsProjet, $materialsBesoins);
            }
        }
    }

    // Si on demande les détails complets, consolider les matériaux
    if ($fullDetails && !empty($materials)) {
        $consolidatedMaterials = consolidateMaterials($materials);
    }

    // 4. NOUVEAU : Récupérer l'historique des modifications de statut si c'est un bon rejeté
    $rejectionHistory = [];
    if ($order['status'] === 'rejected' || $order['rejected_at']) {
        $historyQuery = "SELECT 
                        'rejection' as action_type,
                        po.rejected_at as action_date,
                        ur.name as action_by,
                        po.rejection_reason as details
                      FROM purchase_orders po
                      LEFT JOIN users_exp ur ON po.rejected_by_user_id = ur.id
                      WHERE po.id = ?
                      UNION ALL
                      SELECT 
                        'creation' as action_type,
                        po.generated_at as action_date,
                        u.name as action_by,
                        'Bon de commande créé' as details
                      FROM purchase_orders po
                      LEFT JOIN users_exp u ON po.user_id = u.id
                      WHERE po.id = ?
                      ORDER BY action_date DESC";
        
        $historyStmt = $pdo->prepare($historyQuery);
        $historyStmt->execute([$orderId, $orderId]);
        $rejectionHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. Formater et retourner les résultats - MISE À JOUR AVEC DONNÉES DE REJET
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'order' => $order,
        'projects' => $projects,
        'materials' => $materials,
        'consolidated_materials' => $consolidatedMaterials,
        'rejection_history' => $rejectionHistory, // NOUVEAU
        'is_rejected' => ($order['status'] === 'rejected' || $order['rejected_at'] !== null), // NOUVEAU
        'rejection_details' => [ // NOUVEAU
            'rejected_at' => $order['rejected_at'],
            'rejected_by' => $order['rejected_by_username'],
            'rejection_reason' => $order['rejection_reason']
        ]
    ]);

} catch (Exception $e) {
    // En cas d'erreur, retourner un message d'erreur
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}

/**
 * Fonction pour consolider les matériaux similaires comme dans le bon de commande
 */
function consolidateMaterials($materials)
{
    $consolidated = [];

    foreach ($materials as $material) {
        $designation = trim($material['designation']);
        $unit = trim($material['unit']);

        // Clé unique pour le matériau (basée uniquement sur désignation et unité)
        $materialKey = md5($designation . '|' . $unit);

        $quantity = floatval($material['quantity']);
        $prixUnitaire = floatval($material['prix_unitaire']);

        // Si ce matériau n'a pas encore été traité, initialiser son entrée
        if (!isset($consolidated[$materialKey])) {
            $consolidated[$materialKey] = [
                'designation' => $designation,
                'unit' => $unit,
                'prix_unitaire' => $prixUnitaire,
                'quantity' => $quantity,
                'montant_total' => $quantity * $prixUnitaire
            ];
        } else {
            // Sinon, incrémenter la quantité
            $consolidated[$materialKey]['quantity'] += $quantity;

            // Recalculer le montant total basé sur le prix unitaire et la nouvelle quantité
            $consolidated[$materialKey]['montant_total'] =
                $consolidated[$materialKey]['quantity'] * $consolidated[$materialKey]['prix_unitaire'];
        }
    }

    // Convertir le tableau associatif en tableau indexé pour la sortie JSON
    return array_values($consolidated);
}
?>