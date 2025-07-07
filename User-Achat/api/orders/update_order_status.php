<?php
/**
 * API pour mettre à jour les statuts des commandes après validation finance
 * 
 * Ce fichier vérifie les bons de commande validés par la finance et met à jour
 * les statuts des matériaux correspondants de 'valide_en_cours' vers 'validé'
 * SEULEMENT si leur commande spécifique est dans un bon de commande validé
 * 
 * @package DYM_MANUFACTURE
 * @subpackage achats_materiaux
 * @version 2.0 - Correction de la logique de validation
 */

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Journal de débogage
    $debug_log = [];
    $updatedCount = 0;
    $processedItems = [];

    $debug_log[] = "=== DÉBUT DE LA VÉRIFICATION DES VALIDATIONS FINANCE (VERSION CORRIGÉE) ===";

    // ÉTAPE 1: Récupérer tous les matériaux avec statut 'valide_en_cours' 
    // ET leurs commandes correspondantes dans achats_materiaux
    $pendingMaterialsQuery = "
        SELECT DISTINCT 
            ed.id as material_id,
            ed.idExpression,
            ed.designation,
            ed.valide_achat,
            ed.fournisseur as material_fournisseur,
            am.id as achat_id,
            f.nom as achat_fournisseur,
            am.quantity as achat_quantity,
            am.date_achat,
            am.status as achat_status,
            'expression_dym' as source_table
        FROM expression_dym ed
        INNER JOIN achats_materiaux am ON (
            BINARY am.expression_id = BINARY ed.idExpression
            AND LOWER(TRIM(am.designation)) = LOWER(TRIM(ed.designation))
            AND am.status = 'commandé'
        )
        LEFT JOIN fournisseurs f ON am.fournisseur_id = f.id
        WHERE ed.valide_achat = 'valide_en_cours'
        
        UNION ALL
        
        SELECT DISTINCT 
            b.id as material_id,
            b.idBesoin as idExpression,
            b.designation_article as designation,
            b.achat_status as valide_achat,
            NULL as material_fournisseur,
            am.id as achat_id,
            f2.nom as achat_fournisseur,
            am.quantity as achat_quantity,
            am.date_achat,
            am.status as achat_status,
            'besoins' as source_table
        FROM besoins b
        INNER JOIN achats_materiaux am ON (
            BINARY am.expression_id = BINARY b.idBesoin
            AND LOWER(TRIM(am.designation)) = LOWER(TRIM(b.designation_article))
            AND am.status = 'commandé'
        )
        LEFT JOIN fournisseurs f2 ON am.fournisseur_id = f2.id
        WHERE b.achat_status = 'valide_en_cours'
        
        ORDER BY material_id, achat_id
    ";

    $pendingStmt = $pdo->prepare($pendingMaterialsQuery);
    $pendingStmt->execute();
    $pendingMaterials = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

    $debug_log[] = "Matériaux avec commandes en attente de validation trouvés: " . count($pendingMaterials);

    // ÉTAPE 2: Pour chaque matériau avec sa commande spécifique, 
    // vérifier s'il existe un bon de commande validé qui contient cette commande
    foreach ($pendingMaterials as $material) {
        $debug_log[] = "--- Traitement matériau: {$material['designation']} (Material ID: {$material['material_id']}, Achat ID: {$material['achat_id']}) ---";
        
        // ÉTAPE 2A: Rechercher le bon de commande qui contient cette commande spécifique
        $validatedOrderQuery = "
            SELECT DISTINCT 
                po.id, 
                po.order_number, 
                po.signature_finance, 
                po.user_finance_id, 
                po.fournisseur,
                po.expression_id as po_expression_id,
                po.related_expressions,
                po.is_multi_project,
                po.generated_at
            FROM purchase_orders po
            WHERE po.signature_finance IS NOT NULL 
            AND po.user_finance_id IS NOT NULL
            AND LOWER(TRIM(po.fournisseur)) = LOWER(TRIM(:fournisseur))
            AND (
                -- Cas 1: Bon de commande principal pour cette expression
                (po.expression_id = :expression_id AND po.is_multi_project = 0)
                OR
                -- Cas 2: Bon de commande multi-projets contenant cette expression
                (po.is_multi_project = 1 AND JSON_CONTAINS(po.related_expressions, JSON_QUOTE(:expression_id_json)))
            )
            -- Vérifier que le bon de commande a été généré après ou en même temps que la commande
            AND po.generated_at >= :achat_date
            ORDER BY po.generated_at DESC
            LIMIT 1
        ";

        $fournisseur = $material['achat_fournisseur'] ?: $material['material_fournisseur'];
        
        $validatedOrderStmt = $pdo->prepare($validatedOrderQuery);
        $validatedOrderStmt->bindValue(':expression_id', $material['idExpression']);
        $validatedOrderStmt->bindValue(':expression_id_json', $material['idExpression']);
        $validatedOrderStmt->bindValue(':fournisseur', $fournisseur);
        $validatedOrderStmt->bindValue(':achat_date', $material['date_achat']);
        $validatedOrderStmt->execute();
        $validatedOrder = $validatedOrderStmt->fetch(PDO::FETCH_ASSOC);

        if ($validatedOrder) {
            $debug_log[] = "✓ Bon de commande validé trouvé: {$validatedOrder['order_number']} (généré: {$validatedOrder['generated_at']})";
            
            // ÉTAPE 2B: Vérification supplémentaire - s'assurer que cette commande spécifique 
            // correspond bien au matériel et au fournisseur du bon de commande
            $commandeCorrespondQuery = "
                SELECT COUNT(*) as count_match
                FROM achats_materiaux am
                LEFT JOIN fournisseurs f ON am.fournisseur_id = f.id
                WHERE am.id = :achat_id
                AND BINARY am.expression_id = :expression_id
                AND LOWER(TRIM(am.designation)) = LOWER(TRIM(:designation))
                AND LOWER(TRIM(f.nom)) = LOWER(TRIM(:po_fournisseur))
                AND am.status = 'commandé'
            ";
            
            $correspondStmt = $pdo->prepare($commandeCorrespondQuery);
            $correspondStmt->bindValue(':achat_id', $material['achat_id']);
            $correspondStmt->bindValue(':expression_id', $material['idExpression']);
            $correspondStmt->bindValue(':designation', $material['designation']);
            $correspondStmt->bindValue(':po_fournisseur', $validatedOrder['fournisseur']);
            $correspondStmt->execute();
            $correspondance = $correspondStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($correspondance['count_match'] > 0) {
                $debug_log[] = "✓ Correspondance exacte confirmée entre commande et bon validé";
                
                // ÉTAPE 3: Mettre à jour le statut vers 'validé'
                $updateSuccess = false;
                
                if ($material['source_table'] === 'besoins') {
                    // Mise à jour pour les besoins système
                    $updateQuery = "UPDATE besoins 
                                   SET achat_status = 'validé' 
                                   WHERE id = :id 
                                   AND achat_status = 'valide_en_cours'";
                    $updateStmt = $pdo->prepare($updateQuery);
                    $updateStmt->bindValue(':id', $material['material_id']);
                    $updateStmt->execute();
                    
                    if ($updateStmt->rowCount() > 0) {
                        $updateSuccess = true;
                        $debug_log[] = "✓ Besoin mis à jour: {$material['designation']}";
                    }
                } else {
                    // Mise à jour pour les expressions standard
                    $updateQuery = "UPDATE expression_dym 
                                   SET valide_achat = 'validé' 
                                   WHERE id = :id 
                                   AND valide_achat = 'valide_en_cours'";
                    $updateStmt = $pdo->prepare($updateQuery);
                    $updateStmt->bindValue(':id', $material['material_id']);
                    $updateStmt->execute();
                    
                    if ($updateStmt->rowCount() > 0) {
                        $updateSuccess = true;
                        $debug_log[] = "✓ Expression mise à jour: {$material['designation']}";
                    }
                }
                
                if ($updateSuccess) {
                    $updatedCount++;
                    $processedItems[] = [
                        'type' => $material['source_table'] === 'besoins' ? 'besoin' : 'expression',
                        'id' => $material['material_id'],
                        'achat_id' => $material['achat_id'],
                        'designation' => $material['designation'],
                        'bon_commande' => $validatedOrder['order_number'],
                        'fournisseur' => $fournisseur
                    ];
                }
            } else {
                $debug_log[] = "✗ Pas de correspondance exacte entre la commande et le bon validé";
                $debug_log[] = "  -> Commande: ID={$material['achat_id']}, Fournisseur={$fournisseur}";
                $debug_log[] = "  -> Bon validé: {$validatedOrder['order_number']}, Fournisseur={$validatedOrder['fournisseur']}";
            }
        } else {
            $debug_log[] = "✗ Aucun bon de commande validé trouvé pour: {$material['designation']} (Fournisseur: {$fournisseur})";
        }
    }

    $debug_log[] = "=== FIN DE LA VÉRIFICATION ===";
    $debug_log[] = "Total mis à jour: $updatedCount";

    // Enregistrer le log de débogage (optionnel)
    if (isset($_GET['debug'])) {
        error_log("Update Order Status Log (CORRIGÉ): " . print_r($debug_log, true));
    }

    // Réponse en cas de succès
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $updatedCount > 0 ? "Statuts mis à jour avec succès" : "Aucun statut à mettre à jour",
        'updated_count' => $updatedCount,
        'processed_items' => $processedItems,
        'debug_enabled' => isset($_GET['debug']) ? $debug_log : null
    ]);

} catch (Exception $e) {
    // Log de l'erreur
    error_log("Erreur dans update_order_status.php (CORRIGÉ): " . $e->getMessage());
    
    // Réponse en cas d'erreur
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour des statuts: ' . $e->getMessage(),
        'debug_log' => $debug_log ?? []
    ]);
}
?>