<?php

/**
 * FinanceController.php
 * Contrôleur principal pour la gestion Finance des bons de commande
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

require_once __DIR__ . '/PurchaseOrderService.php';
require_once __DIR__ . '/DatabaseManager.php';
require_once __DIR__ . '/ApiResponse.php';

class FinanceController
{
    private $purchaseOrderService;
    private $dbManager;

    public function __construct()
    {
        $this->dbManager = new DatabaseManager();
        $this->purchaseOrderService = new PurchaseOrderService($this->dbManager);
    }

    /**
     * Récupère les bons de commande en attente de signature Finance
     * @return ApiResponse
     */
    public function getPendingOrders(): ApiResponse
    {
        try {
            $orders = $this->purchaseOrderService->getPendingFinanceOrders();

            return ApiResponse::success($orders, 'Bons en attente récupérés avec succès');
        } catch (Exception $e) {
            error_log("Erreur getPendingOrders: " . $e->getMessage());
            return ApiResponse::error('Erreur lors de la récupération des bons', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Récupère les bons de commande déjà signés par Finance
     * @return ApiResponse
     */
    public function getSignedOrders(): ApiResponse
    {
        try {
            $orders = $this->purchaseOrderService->getSignedFinanceOrders();

            return ApiResponse::success($orders, 'Bons signés récupérés avec succès');
        } catch (Exception $e) {
            error_log("Erreur getSignedOrders: " . $e->getMessage());
            return ApiResponse::error('Erreur lors de la récupération des bons signés', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Récupère les détails complets d'un bon de commande
     * @param int $orderId
     * @return ApiResponse
     */
    public function getOrderDetails(int $orderId): ApiResponse
    {
        try {
            $details = $this->purchaseOrderService->getOrderFullDetails($orderId);

            if (!$details) {
                return ApiResponse::notFound('Bon de commande non trouvé');
            }

            return ApiResponse::success($details, 'Détails récupérés avec succès');
        } catch (Exception $e) {
            error_log("Erreur getOrderDetails: " . $e->getMessage());
            return ApiResponse::error('Erreur lors de la récupération des détails', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Signe un bon de commande
     * @param int $orderId
     * @param int $userId
     * @return ApiResponse
     */
    public function signOrder(int $orderId, int $userId): ApiResponse
    {
        try {
            $result = $this->purchaseOrderService->signOrderByFinance($orderId, $userId);

            if ($result) {
                return ApiResponse::success(['order_id' => $orderId], 'Bon de commande signé avec succès');
            } else {
                return ApiResponse::error('Échec de la signature du bon de commande');
            }
        } catch (Exception $e) {
            error_log("Erreur signOrder: " . $e->getMessage());
            return ApiResponse::error('Erreur lors de la signature: ' . $e->getMessage());
        }
    }

    /**
     * Récupère les statistiques Finance - VERSION AMÉLIORÉE
     * @return ApiResponse
     */
    public function getFinanceStats(): ApiResponse
    {
        try {
            $stats = $this->purchaseOrderService->getFinanceStatistics();

            // Ajouter les statistiques des bons rejetés
            $rejectedStats = $this->getRejectedOrdersStats();
            $stats = array_merge($stats, $rejectedStats);

            return ApiResponse::success($stats, 'Statistiques récupérées avec succès');
        } catch (Exception $e) {
            error_log("Erreur getFinanceStats: " . $e->getMessage());
            return ApiResponse::error('Erreur lors de la récupération des statistiques', []);
        }
    }

    /**
     * Récupère les statistiques des bons rejetés
     * @return array
     */
    private function getRejectedOrdersStats(): array
    {
        try {
            $query = "SELECT COUNT(*) as count 
                  FROM purchase_orders 
                  WHERE status = 'rejected'
                    AND rejected_at IS NOT NULL
                    AND generated_at >= '2025-04-15'";

            $result = $this->dbManager->executeQuery($query);

            return [
                'rejected_count' => $result[0]['count'] ?? 0
            ];
        } catch (Exception $e) {
            error_log("Erreur getRejectedOrdersStats: " . $e->getMessage());
            return ['rejected_count' => 0];
        }
    }

    /**
     * Exporte les données selon le format demandé
     * @param string $type (pending|signed)
     * @param string $format (excel|pdf)
     * @return bool
     */
    public function exportData(string $type, string $format): bool
    {
        try {
            return $this->purchaseOrderService->exportFinanceData($type, $format);
        } catch (Exception $e) {
            error_log("Erreur exportData: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Valide les permissions d'accès Finance
     * @param array $userSession
     * @return bool
     */
    public function validateFinanceAccess(array $userSession): bool
    {
        // Vérification plus souple pour le service Finance
        if (!isset($userSession['user_id']) || !isset($userSession['user_type'])) {
            return false;
        }

        // Accepter 'finance' ou 'Finance' (insensible à la casse)
        $userType = strtolower($userSession['user_type']);

        return in_array($userType, ['finance', 'admin', 'super_admin']);
    }

    /**
     * Journalise les actions Finance
     * @param int $userId
     * @param string $action
     * @param int $orderId
     * @param array $details
     * @return void
     */
    public function logFinanceAction(int $userId, string $action, int $orderId, array $details = []): void
    {
        try {
            $this->dbManager->logSystemEvent(
                $userId,
                $action,
                'finance_purchase_orders',
                $orderId,
                json_encode($details)
            );
        } catch (Exception $e) {
            error_log("Erreur logFinanceAction: " . $e->getMessage());
        }
    }

    /**
     * Test de la connexion à la base de données
     * @return ApiResponse
     */
    public function testConnection(): ApiResponse
    {
        try {
            $result = $this->dbManager->testConnection();

            if ($result['success']) {
                return ApiResponse::success($result, 'Test de connexion réussi');
            } else {
                return ApiResponse::error('Test de connexion échoué', ['details' => $result]);
            }
        } catch (Exception $e) {
            return ApiResponse::error('Erreur lors du test de connexion', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Révoque un bon de commande déjà signé - NOUVELLE MÉTHODE
     * @param int $orderId
     * @param int $userId
     * @param string $revocationReason
     * @return ApiResponse
     */
    public function revokeSignedOrder(int $orderId, int $userId, string $revocationReason): ApiResponse
    {
        try {
            // Vérifier que le bon existe et est bien signé
            $checkQuery = "SELECT id, order_number, signature_finance, rejected_at, status, user_finance_id 
                      FROM purchase_orders 
                      WHERE id = ?";

            $order = $this->dbManager->fetchOne($checkQuery, [$orderId]);

            if (!$order) {
                return ApiResponse::notFound('Bon de commande non trouvé');
            }

            // Vérifier que le bon est bien signé (condition pour révocation)
            if (empty($order['signature_finance']) || empty($order['user_finance_id'])) {
                return ApiResponse::error('Ce bon de commande n\'est pas signé, impossible de le révoquer');
            }

            // Vérifier que le bon n'est pas déjà rejeté
            if ($order['rejected_at'] !== null || $order['status'] === 'rejected') {
                return ApiResponse::error('Ce bon de commande a déjà été rejeté');
            }

            // Révoquer le bon de commande dans une transaction
            $success = $this->dbManager->executeTransaction(function ($db) use ($userId, $revocationReason, $orderId) {

                // Annuler la signature et marquer comme rejeté
                $revokeQuery = "UPDATE purchase_orders 
                           SET signature_finance = NULL,
                               user_finance_id = NULL,
                               rejected_at = NOW(),
                               rejected_by_user_id = ?,
                               rejection_reason = ?,
                               status = 'rejected'
                           WHERE id = ? 
                             AND signature_finance IS NOT NULL
                             AND user_finance_id IS NOT NULL";

                return $db->executeUpdate($revokeQuery, [$userId, $revocationReason, $orderId]);
            });

            if ($success) {
                return ApiResponse::success([
                    'order_id' => $orderId,
                    'order_number' => $order['order_number'],
                    'revoked_at' => date('Y-m-d H:i:s'),
                    'revocation_reason' => $revocationReason,
                    'action_type' => 'revocation'
                ], 'Bon de commande révoqué avec succès');
            } else {
                return ApiResponse::error('Échec de la révocation du bon de commande');
            }
        } catch (Exception $e) {
            error_log("Erreur revokeSignedOrder: " . $e->getMessage());
            return ApiResponse::error('Erreur lors de la révocation');
        }
    }

    /**
     * Rejette un bon de commande - VERSION OPTIMISÉE
     * @param int $orderId
     * @param int $userId
     * @param string $rejectionReason
     * @return ApiResponse
     */
    public function rejectOrder(int $orderId, int $userId, string $rejectionReason): ApiResponse
    {
        try {
            // Vérifier que le bon existe et n'est pas déjà traité
            $checkQuery = "SELECT id, order_number, signature_finance, rejected_at, status 
                      FROM purchase_orders 
                      WHERE id = ?";

            $order = $this->dbManager->fetchOne($checkQuery, [$orderId]);

            if (!$order) {
                return ApiResponse::notFound('Bon de commande non trouvé');
            }

            // Vérifier que le bon n'est pas déjà signé ou rejeté
            if ($order['signature_finance'] !== null) {
                return ApiResponse::error('Ce bon de commande a déjà été signé');
            }

            if ($order['rejected_at'] !== null || $order['status'] === 'rejected') {
                return ApiResponse::error('Ce bon de commande a déjà été rejeté');
            }

            // Rejeter le bon de commande dans une transaction
            $success = $this->dbManager->executeTransaction(function ($db) use ($userId, $rejectionReason, $orderId) {
                $rejectQuery = "UPDATE purchase_orders 
                           SET rejected_at = NOW(),
                               rejected_by_user_id = ?,
                               rejection_reason = ?,
                               status = 'rejected'
                           WHERE id = ? 
                             AND signature_finance IS NULL 
                             AND (rejected_at IS NULL OR status != 'rejected')";

                return $db->executeUpdate($rejectQuery, [$userId, $rejectionReason, $orderId]);
            });

            if ($success) {
                return ApiResponse::success([
                    'order_id' => $orderId,
                    'order_number' => $order['order_number'],
                    'rejected_at' => date('Y-m-d H:i:s'),
                    'rejection_reason' => $rejectionReason
                ], 'Bon de commande rejeté avec succès');
            } else {
                return ApiResponse::error('Échec du rejet du bon de commande');
            }
        } catch (Exception $e) {
            error_log("Erreur rejectOrder: " . $e->getMessage());
            return ApiResponse::error('Erreur lors du rejet');
        }
    }

    /**
     * Supprime définitivement un bon de commande - NOUVELLE MÉTHODE
     * @param int $orderId
     * @param int $userId
     * @param string $deleteReason
     * @return ApiResponse
     */
    public function deleteOrder(int $orderId, int $userId, string $deleteReason): ApiResponse
    {
        try {
            // Vérifier que le bon existe et est rejeté
            $checkQuery = "SELECT id, order_number, status, rejected_at, signature_finance 
                      FROM purchase_orders 
                      WHERE id = ?";

            $order = $this->dbManager->fetchOne($checkQuery, [$orderId]);

            if (!$order) {
                return ApiResponse::notFound('Bon de commande non trouvé');
            }

            // Vérifier que le bon est bien rejeté
            if ($order['status'] !== 'rejected' || empty($order['rejected_at'])) {
                return ApiResponse::error('Seuls les bons de commande rejetés peuvent être supprimés définitivement');
            }

            // Vérifier que le bon n'est pas signé
            if (!empty($order['signature_finance'])) {
                return ApiResponse::error('Impossible de supprimer un bon de commande déjà signé');
            }

            // Supprimer le bon de commande dans une transaction
            $success = $this->dbManager->executeTransaction(function ($db) use ($orderId, $userId, $deleteReason, $order) {

                // 1. Journaliser la suppression avant de supprimer
                $logQuery = "INSERT INTO system_logs 
                           (user_id, action, type, entity_id, entity_name, details, created_at) 
                           VALUES (?, 'delete_order_permanent', 'purchase_orders', ?, ?, ?, NOW())";

                $logDetails = json_encode([
                    'order_number' => $order['order_number'],
                    'delete_reason' => $deleteReason,
                    'original_status' => $order['status'],
                    'rejected_at' => $order['rejected_at']
                ]);

                $db->executeUpdate($logQuery, [$userId, $orderId, $order['order_number'], $logDetails]);

                // 2. Supprimer définitivement le bon de commande
                $deleteQuery = "DELETE FROM purchase_orders WHERE id = ? AND status = 'rejected'";

                return $db->executeUpdate($deleteQuery, [$orderId]);
            });

            if ($success) {
                return ApiResponse::success([
                    'order_id' => $orderId,
                    'order_number' => $order['order_number'],
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'delete_reason' => $deleteReason
                ], 'Bon de commande supprimé définitivement avec succès');
            } else {
                return ApiResponse::error('Échec de la suppression du bon de commande');
            }
        } catch (Exception $e) {
            error_log("Erreur deleteOrder: " . $e->getMessage());
            return ApiResponse::error('Erreur lors de la suppression définitive');
        }
    }

    /**
     * Récupère les bons de commande rejetés par Finance
     * @return ApiResponse
     */
    public function getRejectedOrders(): ApiResponse
    {
        try {
            $query = "SELECT 
                        po.id,
                        po.order_number as bon_number,
                        po.fournisseur,
                        po.montant_total as montant,
                        po.generated_at as created_at,
                        po.rejected_at,
                        po.rejection_reason,
                        uf.name as finance_username,
                        u.name as creator_username,
                        DATE_FORMAT(po.generated_at, '%d/%m/%Y %H:%i') as formatted_created_at,
                        DATE_FORMAT(po.rejected_at, '%d/%m/%Y %H:%i') as formatted_rejected_at,
                        UNIX_TIMESTAMP(po.rejected_at) as rejected_at_timestamp
                      FROM purchase_orders po
                      LEFT JOIN users_exp u ON po.user_id = u.id
                      LEFT JOIN users_exp uf ON po.rejected_by_user_id = uf.id
                      WHERE po.status = 'rejected'
                        AND po.rejected_at IS NOT NULL
                        AND po.generated_at >= '2025-04-15'
                      ORDER BY po.rejected_at DESC, po.id DESC";

            $result = $this->dbManager->executeQuery($query);
            return ApiResponse::success($result ?: [], 'Bons rejetés récupérés avec succès');
        } catch (Exception $e) {
            error_log("Erreur getRejectedOrders: " . $e->getMessage());
            return ApiResponse::error('Erreur lors de la récupération des bons rejetés', []);
        }
    }
}
