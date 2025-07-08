<?php

/**
 * PurchaseOrderService.php
 * Service de gestion des bons de commande pour le service Finance
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

class PurchaseOrderService
{
    private $dbManager;

    public function __construct(DatabaseManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    /**
     * Récupère les bons de commande en attente de signature Finance - VERSION CORRIGÉE
     * @return array
     */
    public function getPendingFinanceOrders(): array
    {
        $query = "SELECT DISTINCT
                po.id,
                po.order_number,
                po.expression_id,
                po.fournisseur,
                po.montant_total,
                po.generated_at,
                po.file_path,
                po.is_multi_project,
                po.status,
                u.name as username,
                pm.label AS mode_paiement,
                DATE_FORMAT(po.generated_at, '%d/%m/%Y %H:%i') as formatted_date
              FROM purchase_orders po
              LEFT JOIN users_exp u ON po.user_id = u.id
              LEFT JOIN payment_methods pm ON po.mode_paiement_id = pm.id
              WHERE po.signature_finance IS NULL
                AND po.user_finance_id IS NULL
                AND po.rejected_at IS NULL
                AND po.rejected_by_user_id IS NULL
                AND (po.status = 'pending' OR po.status IS NULL)
                AND po.generated_at >= '2025-04-15'
              ORDER BY po.generated_at DESC";

        try {
            $result = $this->dbManager->executeQuery($query);
            return $result ?: [];
        } catch (Exception $e) {
            error_log("Erreur getPendingFinanceOrders: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les bons de commande déjà signés par Finance
     * @return array
     */
    public function getSignedFinanceOrders(): array
    {
        $query = "SELECT
                po.id,
                po.order_number as bon_number,
                po.fournisseur,
                po.montant_total as montant,
                po.generated_at as created_at,
                po.signature_finance as signed_at,
                uf.name as finance_username,
                u.name as creator_username,
                pm.label AS mode_paiement,
                DATE_FORMAT(po.generated_at, '%d/%m/%Y %H:%i') as formatted_created_at,
                DATE_FORMAT(po.signature_finance, '%d/%m/%Y %H:%i') as formatted_signed_at,
                UNIX_TIMESTAMP(po.signature_finance) as signed_at_timestamp
              FROM purchase_orders po
              LEFT JOIN users_exp u ON po.user_id = u.id
              LEFT JOIN users_exp uf ON po.user_finance_id = uf.id
              LEFT JOIN payment_methods pm ON po.mode_paiement_id = pm.id
              WHERE po.signature_finance IS NOT NULL
                AND po.user_finance_id IS NOT NULL
                AND po.status = 'signed'
                AND po.generated_at >= '2025-04-15'
              ORDER BY po.signature_finance DESC, po.id DESC";
        /* SUPPRESSION DE TOUTE LIMITATION */

        try {
            $result = $this->dbManager->executeQuery($query);

            // LOG pour vérifier le nombre de résultats
            error_log("🔍 getSignedFinanceOrders: " . count($result) . " bons récupérés");

            return $result ?: [];
        } catch (Exception $e) {
            error_log("Erreur getSignedFinanceOrders: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les détails complets d'un bon de commande - VERSION AVEC DONNÉES REJET
     * @param int $orderId
     * @return array|null
     */
    public function getOrderFullDetails(int $orderId): ?array
    {
        // Récupérer les informations principales du bon avec les données de rejet
        $orderQuery = "SELECT
                    po.*,
                    u.name as username_creation,
                    uf.name as finance_username,
                    ur.name as rejected_by_username,
                    pm.label AS mode_paiement
                  FROM purchase_orders po
                  LEFT JOIN users_exp u ON po.user_id = u.id
                  LEFT JOIN users_exp uf ON po.user_finance_id = uf.id
                  LEFT JOIN users_exp ur ON po.rejected_by_user_id = ur.id
                  LEFT JOIN payment_methods pm ON po.mode_paiement_id = pm.id
                  WHERE po.id = ?";

        try {
            $order = $this->dbManager->executeQuery($orderQuery, [$orderId]);

            if (empty($order)) {
                return null;
            }

            $order = $order[0];

            // Log pour debug
            error_log("🔍 Détails bon récupérés - Status: " . ($order['status'] ?? 'null') .
                ", Rejected_at: " . ($order['rejected_at'] ?? 'null') .
                ", Rejection_reason: " . ($order['rejection_reason'] ?? 'null'));

            // Récupérer les informations des projets associés
            $projects = $this->getOrderProjects($order);

            // Récupérer les matériaux consolidés
            $materials = $this->getOrderMaterials($order);

            // Récupérer les détails de validation
            $validationDetails = $this->getValidationDetails($order['expression_id']);

            return [
                'order' => $order,
                'projects' => $projects,
                'consolidated_materials' => $materials,
                'validation_details' => $validationDetails
            ];
        } catch (Exception $e) {
            error_log("Erreur getOrderFullDetails: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère les projets associés à un bon de commande
     * @param array $order
     * @return array
     */
    private function getOrderProjects(array $order): array
    {
        $projects = [];
        $relatedExpressions = json_decode($order['related_expressions'], true);

        if ($relatedExpressions && is_array($relatedExpressions)) {
            $expressionIds = $relatedExpressions;
        } else {
            $expressionIds = [$order['expression_id']];
        }

        foreach ($expressionIds as $expressionId) {
            try {
                if (strpos($expressionId, 'EXP_B') !== false) {
                    // Expression système (besoins)
                    $projectQuery = "SELECT 
                                  b.idBesoin as idExpression,
                                  CONCAT('SYS-', COALESCE(d.service_demandeur, 'Système')) as code_projet,
                                  COALESCE(d.client, 'Système') as nom_client,
                                  COALESCE(d.motif_demande, 'Besoin système') as description_projet,
                                  'Système' as chefprojet
                                FROM besoins b
                                LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                                WHERE b.idBesoin = ?";
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
                }

                $projectResult = $this->dbManager->executeQuery($projectQuery, [$expressionId]);
                if (!empty($projectResult)) {
                    $projects = array_merge($projects, $projectResult);
                }
            } catch (Exception $e) {
                error_log("Erreur getOrderProjects pour {$expressionId}: " . $e->getMessage());
            }
        }

        return $projects;
    }

    /**
     * Récupère et consolide les matériaux d'un bon de commande
     * @param array $order
     * @return array
     */
    private function getOrderMaterials(array $order): array
    {
        $relatedExpressions = json_decode($order['related_expressions'], true);
        $expressionIds = $relatedExpressions ?: [$order['expression_id']];

        if (empty($expressionIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($expressionIds), '?'));

        // Récupérer les matériaux de la date exacte
        $materialsQuery = "SELECT 
                         am.designation, 
                         am.quantity as qt_acheter,
                         am.unit,
                         am.prix_unitaire,
                         am.fournisseur
                       FROM achats_materiaux am
                       WHERE am.expression_id IN ($placeholders)
                       AND DATE(am.date_achat) = DATE(?)
                       AND am.fournisseur = ?
                       ORDER BY am.designation";

        $params = $expressionIds;
        $params[] = $order['generated_at'];
        $params[] = $order['fournisseur'];

        try {
            $rawMaterials = $this->dbManager->executeQuery($materialsQuery, $params);

            // Consolider les matériaux
            return $this->consolidateMaterials($rawMaterials);
        } catch (Exception $e) {
            error_log("Erreur getOrderMaterials: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Consolide les matériaux similaires
     * @param array $materials
     * @return array
     */
    private function consolidateMaterials(array $materials): array
    {
        $consolidated = [];

        foreach ($materials as $material) {
            $designation = trim($material['designation']);
            $unit = trim($material['unit']);
            $materialKey = md5($designation . '|' . $unit);

            $quantity = floatval($material['qt_acheter']);
            $prix = floatval($material['prix_unitaire']);

            if (!isset($consolidated[$materialKey])) {
                $consolidated[$materialKey] = [
                    'designation' => $designation,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'prix_unitaire' => $prix,
                    'montant_total' => $quantity * $prix
                ];
            } else {
                $consolidated[$materialKey]['quantity'] += $quantity;
                $consolidated[$materialKey]['montant_total'] =
                    $consolidated[$materialKey]['quantity'] * $consolidated[$materialKey]['prix_unitaire'];
            }
        }

        return array_values($consolidated);
    }

    /**
     * Récupère les détails de validation d'une expression
     * @param string $expressionId
     * @return array
     */
    private function getValidationDetails(string $expressionId): array
    {
        try {
            if (strpos($expressionId, 'EXP_B') !== false) {
                // Expression système
                $query = "SELECT 
                            b.user_achat as validated_by_name,
                            'Non spécifié' as modePaiement
                          FROM besoins b
                          WHERE b.idBesoin = ?";
            } else {
                // Expression de projet
                $query = "SELECT 
                            ed.user_achat as validated_by_name,
                            COALESCE(ed.modePaiement, 'Non spécifié') as modePaiement
                          FROM expression_dym ed
                          WHERE ed.idExpression = ?
                          LIMIT 1";
            }

            $result = $this->dbManager->executeQuery($query, [$expressionId]);
            return !empty($result) ? $result[0] : [];
        } catch (Exception $e) {
            error_log("Erreur getValidationDetails: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Signe un bon de commande par le service Finance
     * @param int $orderId
     * @param int $userId
     * @return bool
     */
    public function signOrderByFinance(int $orderId, int $userId): bool
    {
        $query = "UPDATE purchase_orders 
                  SET signature_finance = NOW(),
                      user_finance_id = ?,
                      status = 'signed'
                  WHERE id = ? 
                    AND signature_finance IS NULL 
                    AND user_finance_id IS NULL";

        try {
            return $this->dbManager->executeUpdate($query, [$userId, $orderId]);
        } catch (Exception $e) {
            error_log("Erreur signOrderByFinance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les statistiques pour le service Finance - VERSION CORRIGÉE
     * @return array
     */
    public function getFinanceStatistics(): array
    {
        $stats = [];

        try {
            // CORRECTION : Total des bons en attente avec critères plus stricts
            $pendingQuery = "SELECT COUNT(*) as count 
                        FROM purchase_orders 
                        WHERE signature_finance IS NULL 
                          AND user_finance_id IS NULL
                          AND rejected_at IS NULL
                          AND (status = 'pending' OR status IS NULL)
                          AND generated_at >= '2025-04-15'";
            $pending = $this->dbManager->executeQuery($pendingQuery);
            $stats['pending_count'] = $pending[0]['count'] ?? 0;

            // CORRECTION : Total des bons signés avec critères plus stricts
            $signedQuery = "SELECT COUNT(*) as count 
                       FROM purchase_orders 
                       WHERE signature_finance IS NOT NULL 
                         AND user_finance_id IS NOT NULL
                         AND status = 'signed'
                         AND generated_at >= '2025-04-15'";
            $signed = $this->dbManager->executeQuery($signedQuery);
            $stats['signed_count'] = $signed[0]['count'] ?? 0;

            // CORRECTION : Total des bons rejetés
            $rejectedQuery = "SELECT COUNT(*) as count 
                         FROM purchase_orders 
                         WHERE status = 'rejected'
                           AND rejected_at IS NOT NULL
                           AND rejected_by_user_id IS NOT NULL
                           AND generated_at >= '2025-04-15'";
            $rejected = $this->dbManager->executeQuery($rejectedQuery);
            $stats['rejected_count'] = $rejected[0]['count'] ?? 0;

            // Montant total des bons signés
            $amountQuery = "SELECT SUM(montant_total) as total 
                       FROM purchase_orders 
                       WHERE signature_finance IS NOT NULL 
                         AND user_finance_id IS NOT NULL
                         AND status = 'signed'
                         AND generated_at >= '2025-04-15'";
            $amount = $this->dbManager->executeQuery($amountQuery);
            $stats['total_signed_amount'] = $amount[0]['total'] ?? 0;

            // Statistiques par mois
            $monthlyQuery = "SELECT 
                        DATE_FORMAT(signature_finance, '%Y-%m') as month,
                        COUNT(*) as count,
                        SUM(montant_total) as amount
                      FROM purchase_orders 
                      WHERE signature_finance IS NOT NULL 
                        AND status = 'signed'
                        AND signature_finance >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        AND generated_at >= '2025-04-15'
                      GROUP BY DATE_FORMAT(signature_finance, '%Y-%m')
                      ORDER BY month DESC";
            $stats['monthly_stats'] = $this->dbManager->executeQuery($monthlyQuery);
        } catch (Exception $e) {
            error_log("Erreur getFinanceStatistics: " . $e->getMessage());
            // Valeurs par défaut en cas d'erreur
            $stats = [
                'pending_count' => 0,
                'signed_count' => 0,
                'rejected_count' => 0,
                'total_signed_amount' => 0,
                'monthly_stats' => []
            ];
        }

        return $stats;
    }

    /**
     * Exporte les données Finance selon le format demandé
     * @param string $type
     * @param string $format
     * @return bool
     */
    public function exportFinanceData(string $type, string $format): bool
    {
        try {
            $data = [];

            if ($type === 'pending') {
                $data = $this->getPendingFinanceOrders();
            } elseif ($type === 'signed') {
                $data = $this->getSignedFinanceOrders();
            }

            if ($format === 'excel') {
                return $this->exportToExcel($data, $type);
            } elseif ($format === 'pdf') {
                return $this->exportToPdf($data, $type);
            }

            return false;
        } catch (Exception $e) {
            error_log("Erreur exportFinanceData: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Export vers Excel
     * @param array $data
     * @param string $type
     * @return bool
     */
    private function exportToExcel(array $data, string $type): bool
    {
        try {
            // Créer le dossier exports s'il n'existe pas
            $exportDir = __DIR__ . '/../../exports/';
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            $filename = "finance_bons_{$type}_" . date('Y-m-d_H-i-s') . '.csv';
            $filepath = $exportDir . $filename;

            $file = fopen($filepath, 'w');

            if ($type === 'pending') {
                fputcsv($file, ['Numéro', 'Date', 'Fournisseur', 'Montant', 'Mode de paiement', 'Créé par']);
                foreach ($data as $row) {
                    fputcsv($file, [
                        $row['order_number'],
                        $row['formatted_date'],
                        $row['fournisseur'],
                        $row['montant_total'],
                        $row['mode_paiement'],
                        $row['username']
                    ]);
                }
            } else {
                fputcsv($file, ['Numéro', 'Date création', 'Fournisseur', 'Montant', 'Mode de paiement', 'Date signature']);
                foreach ($data as $row) {
                    fputcsv($file, [
                        $row['bon_number'],
                        $row['formatted_created_at'],
                        $row['fournisseur'],
                        $row['montant'],
                        $row['mode_paiement'],
                        $row['formatted_signed_at']
                    ]);
                }
            }

            fclose($file);

            // Téléchargement
            if (file_exists($filepath)) {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                readfile($filepath);
                unlink($filepath);
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Erreur exportToExcel: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Export vers PDF
     * @param array $data
     * @param string $type
     * @return bool
     */
    private function exportToPdf(array $data, string $type): bool
    {
        // Pour l'instant, rediriger vers CSV
        return $this->exportToExcel($data, $type);
    }
}
