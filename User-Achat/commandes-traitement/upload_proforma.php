<?php

/**
 * ========================================
 * GESTIONNAIRE D'UPLOAD DE PRO-FORMA
 * Fichier : /User-Achat/commandes-traitement/upload_proforma.php
 * 
 * Fonctionnalités :
 * - Upload sécurisé des fichiers pro-forma
 * - Validation côté serveur
 * - Insertion en base de données
 * - Gestion des erreurs
 * ========================================
 */

class ProformaUploadHandler
{
    private $pdo;
    private $uploadDir;
    private $allowedTypes;
    private $maxFileSize;
    private $allowedMimeTypes;

    /**
     * Constructeur
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;

        // Configuration de l'upload
        $this->uploadDir = __DIR__ . '/../../uploads/proformas/';
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB

        $this->allowedTypes = [
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'jpg',
            'jpeg',
            'png',
            'gif'
        ];

        $this->allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif'
        ];

        // Créer le répertoire s'il n'existe pas
        $this->ensureUploadDirectory();
    }

    /**
     * Upload un fichier pro-forma et l'associe à une commande
     */
    public function uploadFile($fileData, $achatMateriauxId, $fournisseur, $projetClient = null)
    {
        try {
            // Validation de base
            if (!$this->validateFileUpload($fileData)) {
                throw new Exception('Fichier invalide ou non fourni');
            }

            // Validation détaillée du fichier
            $validation = $this->validateFile($fileData);
            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            // Générer un nom de fichier sécurisé
            $filename = $this->generateSecureFilename($fileData['name']);
            $filePath = $this->uploadDir . $filename;

            // Déplacer le fichier uploadé
            if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
                throw new Exception('Erreur lors de la sauvegarde du fichier');
            }

            // Enregistrer en base de données
            $proformaId = $this->saveToDatabase(
                $achatMateriauxId,
                $fournisseur,
                $projetClient,
                $filename,
                $fileData
            );

            // Log de succès
            $this->logUpload($proformaId, $achatMateriauxId, $filename, 'success');

            return [
                'success' => true,
                'proforma_id' => $proformaId,
                'filename' => $filename,
                'file_path' => 'uploads/proformas/' . $filename
            ];
        } catch (Exception $e) {
            // Log de l'erreur
            $this->logUpload(null, $achatMateriauxId, $fileData['name'] ?? 'unknown', 'error', $e->getMessage());

            // Nettoyer le fichier si il a été déplacé
            if (isset($filePath) && file_exists($filePath)) {
                unlink($filePath);
            }

            throw $e;
        }
    }

    /**
     * Validation de base de l'upload
     */
    private function validateFileUpload($fileData)
    {
        return isset($fileData) &&
            isset($fileData['error']) &&
            $fileData['error'] === UPLOAD_ERR_OK &&
            isset($fileData['tmp_name']) &&
            is_uploaded_file($fileData['tmp_name']);
    }

    /**
     * Validation complète du fichier
     */
    private function validateFile($fileData)
    {
        // Vérifier la taille
        if ($fileData['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => 'Fichier trop volumineux. Taille maximale : ' . $this->formatFileSize($this->maxFileSize)
            ];
        }

        // Vérifier l'extension
        $filename = strtolower($fileData['name']);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (!in_array($extension, $this->allowedTypes)) {
            return [
                'valid' => false,
                'message' => 'Type de fichier non autorisé. Extensions acceptées : ' . implode(', ', $this->allowedTypes)
            ];
        }

        // Vérifier le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileData['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return [
                'valid' => false,
                'message' => 'Type MIME non autorisé : ' . $mimeType
            ];
        }

        // Vérifications de sécurité supplémentaires
        if (!$this->isSecureFile($fileData['tmp_name'], $extension)) {
            return [
                'valid' => false,
                'message' => 'Fichier potentiellement dangereux détecté'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Génère un nom de fichier sécurisé et unique
     */
    private function generateSecureFilename($originalName)
    {
        // Nettoyer le nom original
        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $cleanName = preg_replace('/_{2,}/', '_', $cleanName);

        // Obtenir l'extension
        $extension = pathinfo($cleanName, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($cleanName, PATHINFO_FILENAME);

        // Limiter la longueur du nom
        $nameWithoutExt = substr($nameWithoutExt, 0, 50);

        // Ajouter timestamp et hash pour l'unicité
        $timestamp = date('YmdHis');
        $hash = substr(md5(uniqid(rand(), true)), 0, 8);

        return "proforma_{$timestamp}_{$hash}_{$nameWithoutExt}.{$extension}";
    }

    /**
     * Sauvegarde les informations en base de données
     */
    private function saveToDatabase($achatMateriauxId, $fournisseur, $projetClient, $filename, $fileData)
    {
        $query = "INSERT INTO proformas (
            achat_materiau_id, 
            fournisseur, 
            projet_client, 
            file_path, 
            original_filename, 
            file_type, 
            file_size, 
            upload_user_id, 
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')";

        $stmt = $this->pdo->prepare($query);

        $userId = $_SESSION['user_id'] ?? null;
        $filePath = 'uploads/proformas/' . $filename;

        $stmt->execute([
            $achatMateriauxId,
            $fournisseur,
            $projetClient,
            $filePath,
            $fileData['name'],
            $fileData['type'],
            $fileData['size'],
            $userId
        ]);

        return $this->pdo->lastInsertId();
    }

    /**
     * Vérifications de sécurité sur le fichier
     */
    private function isSecureFile($filePath, $extension)
    {
        // Vérifier que ce n'est pas un exécutable
        $dangerousExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar', 'php', 'asp'];
        if (in_array($extension, $dangerousExtensions)) {
            return false;
        }

        // Pour les images, vérifier que c'est vraiment une image
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $imageInfo = getimagesize($filePath);
            if ($imageInfo === false) {
                return false;
            }
        }

        // Vérifier qu'il n'y a pas de code PHP dans le fichier
        $fileContent = file_get_contents($filePath, false, null, 0, 1024); // Lire les premiers 1024 octets
        if (strpos($fileContent, '<?php') !== false || strpos($fileContent, '<?=') !== false) {
            return false;
        }

        return true;
    }

    /**
     * S'assure que le répertoire d'upload existe
     */
    private function ensureUploadDirectory()
    {
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                throw new Exception('Impossible de créer le répertoire d\'upload');
            }
        }

        // Créer un fichier .htaccess pour sécuriser le répertoire
        $htaccessPath = $this->uploadDir . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "# Sécurité upload proformas\n";
            $htaccessContent .= "Options -ExecCGI -Indexes\n";
            $htaccessContent .= "AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
            $htaccessContent .= "<Files ~ \"\\.(php|pl|py|jsp|asp|sh|cgi)$\">\n";
            $htaccessContent .= "    Deny from all\n";
            $htaccessContent .= "</Files>\n";

            file_put_contents($htaccessPath, $htaccessContent);
        }
    }

    /**
     * Log des opérations d'upload
     */
    private function logUpload($proformaId, $achatMateriauxId, $filename, $status, $error = null)
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'proforma_id' => $proformaId,
            'achat_materiau_id' => $achatMateriauxId,
            'filename' => $filename,
            'status' => $status,
            'user_id' => $_SESSION['user_id'] ?? null,
            'error' => $error
        ];

        // Log dans un fichier
        $logDir = __DIR__ . '/logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . 'proforma_uploads.log';
        $logLine = json_encode($logData) . "\n";

        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Formate la taille de fichier pour affichage
     */
    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Récupère les pro-formas associés à une commande
     */
    public function getProformasForOrder($achatMateriauxId)
    {
        $query = "SELECT * FROM proformas WHERE achat_materiau_id = ? ORDER BY upload_date DESC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$achatMateriauxId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour le statut d'un pro-forma
     */
    public function updateProformaStatus($proformaId, $status, $notes = null)
    {
        $query = "UPDATE proformas SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($query);

        return $stmt->execute([$status, $notes, $proformaId]);
    }
}
