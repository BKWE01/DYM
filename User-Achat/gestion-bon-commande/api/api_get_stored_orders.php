<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

/**
 * Formate une date de manière sécurisée
 * @param string|null $dateString
 * @return string|null
 */
function formatDateSafely($dateString) {
    if (empty($dateString) || $dateString === null || $dateString === '0000-00-00 00:00:00') {
        return null; // Retourner null pour les dates vides
    }
    
    try {
        $date = new DateTime($dateString);
        return $date->format('Y-m-d H:i:s'); // Format ISO standard
    } catch (Exception $e) {
        error_log("Erreur formatage date: " . $e->getMessage() . " pour la date: " . $dateString);
        return null;
    }
}

/**
 * Formate un montant de manière sécurisée
 * @param mixed $amount
 * @return array
 */
function formatAmountSafely($amount) {
    $numericAmount = is_numeric($amount) ? (float)$amount : 0;
    return [
        'formatted' => number_format($numericAmount, 0, ',', ' ') . ' FCFA',
        'raw' => $numericAmount
    ];
}

try {
    // MISE À JOUR : Récupérer tous les bons de commande avec gestion des rejets
    $query = "SELECT
                po.id,
                po.order_number,
                po.download_reference,
                po.expression_id,
                po.related_expressions,
                po.file_path,
                -- Chemin du dernier pro-forma lié à ce bon de commande
                (SELECT pr.file_path
                 FROM proformas pr
                 WHERE pr.bon_commande_id = po.id
                 ORDER BY pr.upload_date DESC
                 LIMIT 1) AS proforma_path,
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
              ORDER BY po.generated_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Traitement des données pour les rendre plus exploitables par DataTables
    $data = [];
    $totalValidated = 0;
    $totalRejected = 0;
    $totalPending = 0;
    
    foreach ($orders as $order) {
        try {
            // Récupérer le nombre de projets concernés
            $projectCount = 1; // Par défaut, au moins 1 projet
            $relatedExpressions = null;
            
            if (!empty($order['related_expressions'])) {
                $relatedExpressions = json_decode($order['related_expressions'], true);
                if (is_array($relatedExpressions)) {
                    $projectCount = count($relatedExpressions);
                }
            }

            // Préparer les informations du projet
            $projectInfo = "";
            $source_table = $order['source_table'];

            // Si c'est un multi-projet, récupérer les informations détaillées des projets
            if ($order['is_multi_project'] == 1 && $relatedExpressions) {
                // On affiche juste le nombre
                $projectInfo = 'Multi-projets (' . $projectCount . ')';
            } else {
                // Récupérer les informations détaillées selon la source
                if ($source_table == 'besoins') {
                    // Récupérer les informations de la table besoins et demandeur
                    $besoinQuery = "SELECT b.id, d.client, d.service_demandeur 
                                   FROM besoins b 
                                   LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin 
                                   WHERE b.idBesoin = ?";
                    $besoinStmt = $pdo->prepare($besoinQuery);
                    $besoinStmt->execute([$order['expression_id']]);
                    $besoin = $besoinStmt->fetch(PDO::FETCH_ASSOC);

                    if ($besoin) {
                        $projectInfo = 'SYS-' . ($besoin['service_demandeur'] ?? 'N/A') . ' - ' . ($besoin['client'] ?? 'N/A');
                    } else {
                        $projectInfo = 'Demande système';
                    }
                } else {
                    // Récupérer les informations de la table identification_projet
                    $projetQuery = "SELECT ip.code_projet, ip.nom_client 
                                  FROM identification_projet ip 
                                  WHERE ip.idExpression = ?";
                    $projetStmt = $pdo->prepare($projetQuery);
                    $projetStmt->execute([$order['expression_id']]);
                    $projet = $projetStmt->fetch(PDO::FETCH_ASSOC);

                    if ($projet) {
                        $projectInfo = ($projet['code_projet'] ?? 'N/A') . ' - ' . ($projet['nom_client'] ?? 'N/A');
                    } else {
                        $projectInfo = 'Projet inconnu';
                    }
                }
            }

            // Formatage sécurisé de la date
            $formattedDate = formatDateSafely($order['generated_at']);
            
            // Formatage sécurisé du montant
            $amountData = formatAmountSafely($order['montant_total']);

            // NOUVEAU : Déterminer le statut pour les statistiques
            if ($order['status'] === 'rejected' || $order['rejected_at']) {
                $totalRejected++;
            } elseif ($order['signature_finance'] && $order['user_finance_id']) {
                $totalValidated++;
            } else {
                $totalPending++;
            }

            // Ajouter l'entrée formatée
            $data[] = [
                'id' => (int)$order['id'],
                'order_number' => $order['order_number'] ?? 'N/A',
                'download_reference' => $order['download_reference'] ?? 'N/A',
                'expression_id' => $order['expression_id'] ?? '',
                'related_expressions' => $order['related_expressions'],
                'file_path' => $order['file_path'] ?? '',
                'proforma_path' => $order['proforma_path'] ?? null,
                'fournisseur' => $order['fournisseur'] ?? 'N/A',
                'montant_total' => $amountData['formatted'],
                'montant_total_raw' => $amountData['raw'],
                'user_id' => (int)$order['user_id'],
                'username' => $order['username'] ?? 'Utilisateur inconnu',
                'signature_finance' => $order['signature_finance'],
                'user_finance_id' => $order['user_finance_id'],
                'finance_username' => $order['finance_username'] ?? null,
                'is_multi_project' => (int)$order['is_multi_project'],
                'project_count' => $projectCount,
                'project_info' => $projectInfo,
                'source_table' => $source_table,
                'generated_at' => $formattedDate,
                'generated_at_display' => $formattedDate ? date('d/m/Y H:i', strtotime($formattedDate)) : 'Date inconnue',
                'generated_at_raw' => $order['generated_at'], // Garder la valeur brute pour debug
                // NOUVEAU : Données de rejet
                'status' => $order['status'] ?? 'pending',
                'rejected_at' => $order['rejected_at'],
                'rejected_by_user_id' => $order['rejected_by_user_id'],
                'rejected_by_username' => $order['rejected_by_username'],
                'rejection_reason' => $order['rejection_reason']
            ];

        } catch (Exception $itemError) {
            // Log l'erreur mais continue avec les autres éléments
            error_log("Erreur lors du traitement de l'ordre ID " . $order['id'] . ": " . $itemError->getMessage());
            
            // Ajouter une entrée minimale pour éviter les erreurs d'affichage
            $data[] = [
                'id' => (int)$order['id'],
                'order_number' => $order['order_number'] ?? 'Erreur',
                'download_reference' => 'Erreur',
                'expression_id' => '',
                'related_expressions' => null,
                'file_path' => '',
                'proforma_path' => null,
                'fournisseur' => 'Erreur',
                'montant_total' => '0 FCFA',
                'montant_total_raw' => 0,
                'user_id' => 0,
                'username' => 'Erreur',
                'signature_finance' => null,
                'user_finance_id' => null,
                'finance_username' => null,
                'is_multi_project' => 0,
                'project_count' => 0,
                'project_info' => 'Erreur de chargement',
                'source_table' => 'unknown',
                'generated_at' => null,
                'generated_at_display' => 'Erreur date',
                'generated_at_raw' => null,
                // NOUVEAU : Données de rejet par défaut
                'status' => 'unknown',
                'rejected_at' => null,
                'rejected_by_user_id' => null,
                'rejected_by_username' => null,
                'rejection_reason' => null
            ];
        }
    }

    // Statistiques rapides - MISE À JOUR AVEC REJETS
    $totalOrders = count($data);
    $totalAmount = array_sum(array_column($data, 'montant_total_raw'));
    $multiProjectOrders = count(array_filter($data, function($order) {
        return $order['is_multi_project'] == 1;
    }));

    // Retourner les données au format JSON avec statistiques mises à jour
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $data,
        'recordsTotal' => $totalOrders,
        'recordsFiltered' => $totalOrders,
        'statistics' => [
            'total_orders' => $totalOrders,
            'total_amount' => $totalAmount,
            'total_amount_formatted' => number_format($totalAmount, 0, ',', ' ') . ' FCFA',
            'validated_orders' => $totalValidated,
            'pending_orders' => $totalPending,
            'rejected_orders' => $totalRejected, // NOUVEAU
            'multi_project_orders' => $multiProjectOrders
        ],
        'debug_info' => [
            'query_executed' => true,
            'raw_orders_count' => count($orders),
            'processed_orders_count' => count($data),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    // Log l'erreur complète
    error_log("Erreur critique dans api_get_stored_orders.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // En cas d'erreur, retourner un message d'erreur détaillé
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des bons de commande',
        'error_details' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => [],
        'recordsTotal' => 0,
        'recordsFiltered' => 0
    ]);
}
?>