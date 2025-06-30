<?php
/**
 * Fonction utilitaire pour vérifier et corriger les incohérences dans les quantités restantes
 */

/**
 * Vérifie et corrige les incohérences dans les quantités restantes
 * 
 * @param PDO $pdo Instance de connexion à la base de données
 * @return array Rapport des corrections effectuées
 */
function checkAndFixRemainingQuantities($pdo)
{
    $report = [
        'checked' => 0,
        'fixed' => 0,
        'errors' => []
    ];

    try {
        // 1. Récupérer tous les matériaux avec valide_achat = 'en_cours'
        $query = "SELECT id, idExpression, designation, qt_acheter, qt_restante, initial_qt_acheter
                 FROM expression_dym
                 WHERE valide_achat = 'en_cours'";

        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $report['checked'] = count($materials);

        foreach ($materials as $material) {
            // 2. Pour chaque matériau, récupérer la somme des commandes partielles
            $orderedQuery = "SELECT SUM(quantity) as total_ordered
                            FROM achats_materiaux
                            WHERE expression_id = :expression_id
                            AND designation = :designation
                            AND is_partial = 1";

            $orderedStmt = $pdo->prepare($orderedQuery);
            $orderedStmt->bindParam(':expression_id', $material['idExpression']);
            $orderedStmt->bindParam(':designation', $material['designation']);
            $orderedStmt->execute();
            $ordered = $orderedStmt->fetch(PDO::FETCH_ASSOC);

            $totalOrdered = floatval($ordered['total_ordered'] ?? 0);
            $initialQuantity = floatval($material['initial_qt_acheter'] ?? $material['qt_acheter']);
            $expectedRemaining = $initialQuantity - $totalOrdered;

            // 3. Vérifier si la quantité restante correspond à l'attendu
            if (abs($expectedRemaining - floatval($material['qt_restante'])) > 0.001) {
                // Correction nécessaire
                $updateQuery = "UPDATE expression_dym
                               SET qt_restante = :remaining
                               WHERE id = :id";

                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->bindParam(':remaining', $expectedRemaining);
                $updateStmt->bindParam(':id', $material['id']);
                $updateStmt->execute();

                $report['fixed']++;
                $report['errors'][] = [
                    'id' => $material['id'],
                    'designation' => $material['designation'],
                    'old_remaining' => $material['qt_restante'],
                    'new_remaining' => $expectedRemaining
                ];
            }

            // 4. Si la quantité restante est nulle ou négative, mettre à jour le statut
            if ($expectedRemaining <= 0.001) {
                $updateStatusQuery = "UPDATE expression_dym
                                     SET valide_achat = 'valide_en_cours',
                                         qt_restante = 0
                                     WHERE id = :id";

                $updateStatusStmt = $pdo->prepare($updateStatusQuery);
                $updateStatusStmt->bindParam(':id', $material['id']);
                $updateStatusStmt->execute();

                // Journaliser cette modification si nécessaire
                if (function_exists('logSystemEvent')) {
                    $details = json_encode([
                        'id' => $material['id'],
                        'designation' => $material['designation'],
                        'action' => 'Mise à jour du statut de en_cours à validé',
                        'reason' => 'Quantité restante nulle ou négative'
                    ]);

                    // Récupérer l'ID utilisateur de la session
                    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

                    logSystemEvent($pdo, $userId, 'correction_automatique_statut', 'expression_dym', $material['id'], $details);
                }
            }
        }

        return $report;

    } catch (Exception $e) {
        $report['errors'][] = ['exception' => $e->getMessage()];
        error_log("Erreur lors de la vérification des quantités restantes: " . $e->getMessage());
        return $report;
    }
}