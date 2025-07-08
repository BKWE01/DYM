<?php

/**
 * Classe Logger pour enregistrer les actions des utilisateurs
 * Cette classe fournit une interface centralisée pour la journalisation des actions
 */
class Logger
{
    private $pdo;
    private $user_id;
    private $username;

    /**
     * Constructeur
     * @param PDO $pdo Instance de connexion PDO à la base de données
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->initUser();
    }

    /**
     * Initialise les informations de l'utilisateur actuel à partir de la session
     */
    private function initUser()
    {
        // S'assurer que la session est démarrée
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Récupérer l'ID utilisateur et le nom d'utilisateur depuis la session
        $this->user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $this->username = isset($_SESSION['username']) ? $_SESSION['username'] : null;

        // Si username n'est pas défini mais user_id est disponible, essayer de récupérer le nom d'utilisateur
        if ($this->user_id && !$this->username) {
            try {
                $stmt = $this->pdo->prepare("SELECT name FROM users_exp WHERE id = :id");
                $stmt->execute([':id' => $this->user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $this->username = $user['name'];
                }
            } catch (PDOException $e) {
                // Si échec, laisser username comme null
            }
        }
    }

    /**
     * Enregistre une action dans la base de données
     * 
     * @param string $action Type d'action (ex: 'product_add', 'stock_entry')
     * @param string $type Type d'entité concernée (ex: 'product', 'stock', 'category')
     * @param string|int|null $entity_id Identifiant de l'entité concernée
     * @param string|null $entity_name Nom de l'entité concernée
     * @param array|null $details Détails supplémentaires au format tableau associatif
     * @return bool Succès ou échec de l'enregistrement
     */
    public function log($action, $type, $entity_id = null, $entity_name = null, $details = null)
    {
        try {
            // Convertir le tableau de détails en JSON si présent
            $jsonDetails = null;
            if ($details !== null) {
                $jsonDetails = json_encode($details, JSON_UNESCAPED_UNICODE);
            }

            // Récupérer l'adresse IP du client
            $ip_address = $this->getClientIP();

            // Préparer et exécuter la requête
            $stmt = $this->pdo->prepare("
                INSERT INTO system_logs (
                    user_id, username, action, type, entity_id, entity_name, details, ip_address
                ) VALUES (
                    :user_id, :username, :action, :type, :entity_id, :entity_name, :details, :ip_address
                )
            ");

            $stmt->execute([
                ':user_id' => $this->user_id,
                ':username' => $this->username,
                ':action' => $action,
                ':type' => $type,
                ':entity_id' => $entity_id,
                ':entity_name' => $entity_name,
                ':details' => $jsonDetails,
                ':ip_address' => $ip_address
            ]);

            return true;
        } catch (PDOException $e) {
            // Enregistrer l'erreur dans un fichier de log système
            $this->logError('Erreur lors de l\'enregistrement du log : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enregistre les actions d'entrée de stock
     * 
     * @param array $productData Données du produit
     * @param int $quantity Quantité
     * @param string $provenance Provenance du stock
     * @param string|null $fournisseur Fournisseur (optionnel)
     * @return bool Succès ou échec de l'enregistrement
     */
    public function logStockEntry($productData, $quantity, $provenance, $fournisseur = null)
    {
        $details = [
            'quantity' => $quantity,
            'provenance' => $provenance,
            'fournisseur' => $fournisseur
        ];

        return $this->log(
            'stock_entry',
            'stock',
            $productData['id'],
            $productData['product_name'],
            $details
        );
    }

    /**
     * Enregistre les actions de sortie de stock
     * 
     * @param array $productData Données du produit
     * @param int $quantity Quantité
     * @param string $destination Destination du stock
     * @param string $demandeur Demandeur
     * @param string|null $project Projet lié (optionnel)
     * @return bool Succès ou échec de l'enregistrement
     */
    public function logStockOutput($productData, $quantity, $destination, $demandeur, $project = null)
    {
        $details = [
            'quantity' => $quantity,
            'destination' => $destination,
            'demandeur' => $demandeur,
            'project' => $project
        ];

        return $this->log(
            'stock_output',
            'stock',
            $productData['id'],
            $productData['product_name'],
            $details
        );
    }

    /**
     * Enregistre les actions d'ajout de produit
     * 
     * @param array $productData Données du produit
     * @return bool Succès ou échec de l'enregistrement
     */
    public function logProductAdd($productData)
    {
        return $this->log(
            'product_add',
            'product',
            $productData['id'],
            $productData['product_name'],
            $productData
        );
    }

    /**
     * Enregistre les actions de modification de produit
     * 
     * @param array $productData Données du produit
     * @param array $oldData Anciennes données du produit
     * @return bool Succès ou échec de l'enregistrement
     */
    public function logProductEdit($productData, $oldData)
    {
        $details = [
            'old' => $oldData,
            'new' => $productData
        ];

        return $this->log(
            'product_edit',
            'product',
            $productData['id'],
            $productData['product_name'],
            $details
        );
    }

    /**
     * Enregistre les actions de suppression de produit
     * 
     * @param array $productData Données du produit
     * @return bool Succès ou échec de l'enregistrement
     */
    public function logProductDelete($productData)
    {
        return $this->log(
            'product_delete',
            'product',
            $productData['id'],
            $productData['product_name'],
            $productData
        );
    }

    /**
     * Enregistre les actions d'upload de facture
     * 
     * @param array $invoiceData Données de la facture
     * @return bool Succès ou échec de l'enregistrement
     */
    public function logInvoiceUpload($invoiceData)
    {
        return $this->log(
            'invoice_upload',
            'invoice',
            $invoiceData['id'] ?? null,
            $invoiceData['original_filename'] ?? null,
            $invoiceData
        );
    }

    /**
     * Enregistre l'association d'une facture à un mouvement
     *
     * @param int $invoiceId ID de la facture associée
     * @param int $movementId ID du mouvement concerné
     * @param string|null $fileName Nom du fichier de la facture
     * @return bool Succès ou échec de l'enregistrement
     */
    public function logInvoiceAssociate($invoiceId, $movementId, $fileName = null)
    {
        $details = [
            'movement_id' => $movementId,
            'invoice_id' => $invoiceId
        ];

        return $this->log(
            'invoice_associate',
            'invoice',
            $invoiceId,
            $fileName,
            $details
        );
    }

    /**
     * Enregistre le remplacement d'une facture pour un mouvement
     *
     * @param int $invoiceId        Nouvel ID de facture
     * @param int $movementId       ID du mouvement concerné
     * @param int $oldInvoiceId     Ancien ID de facture remplacé
     * @param string|null $fileName Nom du fichier de la nouvelle facture
     * @return bool Succès ou échec de l'enregistrement
     */
    public function logInvoiceReplace($invoiceId, $movementId, $oldInvoiceId, $fileName = null)
    {
        $details = [
            'movement_id' => $movementId,
            'invoice_id' => $invoiceId,
            'replaced_invoice_id' => $oldInvoiceId
        ];

        return $this->log(
            'invoice_replace',
            'invoice',
            $invoiceId,
            $fileName,
            $details
        );
    }

    /**
     * Enregistre l'action de connexion d'un utilisateur
     * 
     * @return bool Succès ou échec de l'enregistrement
     */
    public function logUserLogin()
    {
        return $this->log(
            'user_login',
            'user',
            $this->user_id,
            $this->username,
            ['ip_address' => $this->getClientIP()]
        );
    }

    /**
     * Enregistre l'action de déconnexion d'un utilisateur
     * 
     * @return bool Succès ou échec de l'enregistrement
     */
    public function logUserLogout()
    {
        return $this->log(
            'user_logout',
            'user',
            $this->user_id,
            $this->username,
            ['ip_address' => $this->getClientIP()]
        );
    }

    /**
     * Récupère l'adresse IP du client
     * 
     * @return string Adresse IP
     */
    private function getClientIP()
    {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        return $ip;
    }

    /**
     * Enregistre une erreur dans un fichier de log système
     * 
     * @param string $message Message d'erreur
     */
    private function logError($message)
    {
        $logDir = dirname(__FILE__) . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . '/system_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;

        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Enregistre les actions de retour fournisseur
     * 
     * @param array $productData Données du produit
     * @param int $quantity Quantité
     * @param string $supplier Nom du fournisseur
     * @param string $reason Motif du retour
     * @param string|null $comment Commentaire additionnel (optionnel)
     * @return bool Succès ou échec de l'enregistrement
     */
    public function logSupplierReturn($productData, $quantity, $supplier, $reason, $comment = null)
    {
        $details = [
            'product_id' => $productData['id'],
            'product_name' => $productData['product_name'],
            'barcode' => $productData['barcode'] ?? 'N/A',
            'quantity' => $quantity,
            'supplier' => $supplier,
            'reason' => $reason
        ];

        if (!empty($comment)) {
            $details['comment'] = $comment;
        }

        $entity_name = $productData['product_name'];
        $message = "Retour fournisseur: {$quantity} x {$entity_name} retourné à {$supplier}. Motif: {$reason}";

        if (!empty($comment)) {
            $message .= ". Commentaire: {$comment}";
        }

        return $this->log(
            'supplier_return',
            'stock',
            $productData['id'],
            $entity_name,
            $details
        );
    }
}
