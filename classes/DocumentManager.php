<?php
/**
 * Classe DocumentManager pour la gestion des documents générés
 * 
 * Cette classe fournit toutes les fonctionnalités nécessaires pour gérer
 * l'enregistrement, la récupération et le suivi des documents PDF générés
 * par les différents services de DYM MANUFACTURE.
 */
class DocumentManager
{
    private $pdo;
    private $baseDir;
    private $userId;

    /**
     * Constructeur
     * 
     * @param PDO $pdo Instance de PDO pour la connexion à la base de données
     * @param int $userId ID de l'utilisateur actuel
     */
    public function __construct(PDO $pdo, $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;

        // Définir le répertoire de base pour les documents
        $this->baseDir = dirname(__DIR__) . '/documents';

        // Créer les répertoires s'ils n'existent pas
        $this->initDirectories();
    }

    /**
     * Initialise les répertoires nécessaires
     */
    private function initDirectories()
    {
        $directories = [
            $this->baseDir,
            $this->baseDir . '/bureau_etudes/expressions',
            $this->baseDir . '/bureau_etudes/retours',
            $this->baseDir . '/achats/bons_commande',
            $this->baseDir . '/achats/factures',
            $this->baseDir . '/stock/bons_reception',
            $this->baseDir . '/stock/fiches_produits',
            $this->baseDir . '/stock/mouvements',
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Enregistre un document dans le système
     * 
     * @param string $sourcePath Chemin source du fichier
     * @param string $documentType Type de document (enum dans la base de données)
     * @param string $referenceId ID de référence (numéro d'expression, bon de commande, etc.)
     * @param array $metadata Métadonnées supplémentaires pour le document
     * @return int|bool ID du document enregistré ou false en cas d'échec
     */
    public function saveDocument($sourcePath, $documentType, $referenceId, $metadata = [])
    {
        try {
            // Vérifier l'existence du fichier source
            if (!file_exists($sourcePath)) {
                throw new Exception("Le fichier source n'existe pas: " . $sourcePath);
            }

            // Déterminer le répertoire de destination en fonction du type de document
            $destDir = $this->getDirectoryForDocumentType($documentType);

            // Générer un nom de fichier unique
            $fileName = $this->generateFileName($documentType, $referenceId);

            // Chemin de destination complet
            $destPath = $destDir . '/' . $fileName;

            // Copier le fichier vers sa destination finale
            if (!copy($sourcePath, $destPath)) {
                throw new Exception("Impossible de copier le fichier vers: " . $destPath);
            }

            // Obtenir les informations sur le fichier
            $fileSize = filesize($destPath);
            $fileType = mime_content_type($destPath) ?: 'application/pdf';

            // Chemin relatif pour la base de données
            $relativePath = str_replace($this->baseDir, '', $destPath);
            $relativePath = '/documents' . $relativePath; // Préfixe pour l'accès web

            // Enregistrer dans la base de données
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO documents (file_name, file_path, file_type, file_size, document_type, reference_id, user_id)
                VALUES (:file_name, :file_path, :file_type, :file_size, :document_type, :reference_id, :user_id)
            ");

            $stmt->bindParam(':file_name', $fileName);
            $stmt->bindParam(':file_path', $relativePath);
            $stmt->bindParam(':file_type', $fileType);
            $stmt->bindParam(':file_size', $fileSize);
            $stmt->bindParam(':document_type', $documentType);
            $stmt->bindParam(':reference_id', $referenceId);
            $stmt->bindParam(':user_id', $this->userId);

            $stmt->execute();
            $documentId = $this->pdo->lastInsertId();

            // Enregistrer les métadonnées supplémentaires
            if (!empty($metadata)) {
                $metadataStmt = $this->pdo->prepare("
                    INSERT INTO document_metadata (document_id, `key`, `value`)
                    VALUES (:document_id, :key, :value)
                ");

                foreach ($metadata as $key => $value) {
                    $metadataStmt->bindParam(':document_id', $documentId);
                    $metadataStmt->bindParam(':key', $key);
                    $metadataStmt->bindParam(':value', $value);
                    $metadataStmt->execute();
                }
            }

            $this->pdo->commit();

            return $documentId;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log("DocumentManager::saveDocument - Erreur: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Génère un nom de fichier unique pour un document
     * 
     * @param string $documentType Type de document
     * @param string $referenceId ID de référence
     * @return string Nom de fichier généré
     */
    private function generateFileName($documentType, $referenceId)
    {
        $prefix = $this->getPrefixForDocumentType($documentType);
        $timestamp = date('YmdHis');
        $random = substr(md5(uniqid(mt_rand(), true)), 0, 8);

        return $prefix . '_' . $referenceId . '_' . $timestamp . '_' . $random . '.pdf';
    }

    /**
     * Récupère le préfixe pour un type de document
     * 
     * @param string $documentType Type de document
     * @return string Préfixe pour le nom de fichier
     */
    private function getPrefixForDocumentType($documentType)
    {
        $prefixes = [
            'expression_besoin' => 'EB',
            'bon_commande' => 'BC',
            'facture' => 'FAC',
            'bon_reception' => 'BR',
            'retour_produit' => 'RET'
        ];

        return isset($prefixes[$documentType]) ? $prefixes[$documentType] : 'DOC';
    }

    /**
     * Récupère le répertoire pour un type de document
     * 
     * @param string $documentType Type de document
     * @return string Chemin complet du répertoire
     */
    private function getDirectoryForDocumentType($documentType)
    {
        $directories = [
            'expression_besoin' => $this->baseDir . '/bureau_etudes/expressions',
            'bon_commande' => $this->baseDir . '/achats/bons_commande',
            'facture' => $this->baseDir . '/achats/factures',
            'bon_reception' => $this->baseDir . '/stock/bons_reception',
            'retour_produit' => $this->baseDir . '/bureau_etudes/retours'
        ];

        return isset($directories[$documentType]) ? $directories[$documentType] : $this->baseDir;
    }

    /**
     * Enregistre l'accès à un document
     * 
     * @param int $documentId ID du document
     * @param string $action Type d'action (view, download, print, share)
     * @return bool Succès ou échec
     */
    public function logAccess($documentId, $action)
    {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

            $stmt = $this->pdo->prepare("
                INSERT INTO document_access_log (document_id, user_id, action, ip_address)
                VALUES (:document_id, :user_id, :action, :ip_address)
            ");

            $stmt->bindParam(':document_id', $documentId);
            $stmt->bindParam(':user_id', $this->userId);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':ip_address', $ipAddress);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("DocumentManager::logAccess - Erreur: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère un document par son ID
     * 
     * @param int $documentId ID du document
     * @return array|bool Document info ou false si non trouvé
     */
    public function getDocument($documentId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM documents WHERE id = :document_id
            ");

            $stmt->bindParam(':document_id', $documentId);
            $stmt->execute();

            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($document) {
                // Récupérer également les métadonnées
                $metadataStmt = $this->pdo->prepare("
                    SELECT `key`, `value` FROM document_metadata WHERE document_id = :document_id
                ");

                $metadataStmt->bindParam(':document_id', $documentId);
                $metadataStmt->execute();

                $metadata = [];
                while ($row = $metadataStmt->fetch(PDO::FETCH_ASSOC)) {
                    $metadata[$row['key']] = $row['value'];
                }

                $document['metadata'] = $metadata;

                // Enregistrer l'accès
                $this->logAccess($documentId, 'view');
            }

            return $document;
        } catch (Exception $e) {
            error_log("DocumentManager::getDocument - Erreur: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les documents par type et référence
     * 
     * @param string $documentType Type de document
     * @param string $referenceId ID de référence (optionnel)
     * @return array Liste des documents
     */
    public function getDocumentsByType($documentType, $referenceId = null)
    {
        try {
            $sql = "SELECT * FROM documents WHERE document_type = :document_type";
            $params = [':document_type' => $documentType];

            if ($referenceId !== null) {
                $sql .= " AND reference_id = :reference_id";
                $params[':reference_id'] = $referenceId;
            }

            $sql .= " ORDER BY created_at DESC";

            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer les métadonnées pour chaque document
            foreach ($documents as &$document) {
                $metadataStmt = $this->pdo->prepare("
                    SELECT `key`, `value` FROM document_metadata WHERE document_id = :document_id
                ");

                $metadataStmt->bindParam(':document_id', $document['id']);
                $metadataStmt->execute();

                $metadata = [];
                while ($row = $metadataStmt->fetch(PDO::FETCH_ASSOC)) {
                    $metadata[$row['key']] = $row['value'];
                }

                $document['metadata'] = $metadata;
            }

            return $documents;
        } catch (Exception $e) {
            error_log("DocumentManager::getDocumentsByType - Erreur: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Télécharge un document
     * 
     * @param int $documentId ID du document
     * @return bool Succès ou échec
     */
    public function downloadDocument($documentId)
    {
        try {
            $document = $this->getDocument($documentId);

            if (!$document) {
                throw new Exception("Document non trouvé: " . $documentId);
            }

            // Construire le chemin complet du fichier
            $filePath = $_SERVER['DOCUMENT_ROOT'] . $document['file_path'];

            if (!file_exists($filePath)) {
                throw new Exception("Fichier non trouvé: " . $filePath);
            }

            // Enregistrer l'action de téléchargement
            $this->logAccess($documentId, 'download');

            // Envoyer le fichier
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $document['file_type']);
            header('Content-Disposition: attachment; filename="' . basename($document['file_name']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));

            readfile($filePath);
            return true;
        } catch (Exception $e) {
            error_log("DocumentManager::downloadDocument - Erreur: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recherche des documents selon différents critères
     * 
     * @param array $criteria Critères de recherche
     * @return array Documents trouvés
     */
    public function searchDocuments($criteria = [])
    {
        try {
            $sql = "SELECT d.* FROM documents d
                    LEFT JOIN document_metadata dm ON d.id = dm.document_id
                    WHERE 1=1";

            $params = [];

            // Filtre par type de document
            if (isset($criteria['document_type']) && !empty($criteria['document_type'])) {
                $sql .= " AND d.document_type = :document_type";
                $params[':document_type'] = $criteria['document_type'];
            }

            // Filtre par référence
            if (isset($criteria['reference_id']) && !empty($criteria['reference_id'])) {
                $sql .= " AND d.reference_id LIKE :reference_id";
                $params[':reference_id'] = '%' . $criteria['reference_id'] . '%';
            }

            // Filtre par date de création (début)
            if (isset($criteria['date_from']) && !empty($criteria['date_from'])) {
                $sql .= " AND d.created_at >= :date_from";
                $params[':date_from'] = $criteria['date_from'] . ' 00:00:00';
            }

            // Filtre par date de création (fin)
            if (isset($criteria['date_to']) && !empty($criteria['date_to'])) {
                $sql .= " AND d.created_at <= :date_to";
                $params[':date_to'] = $criteria['date_to'] . ' 23:59:59';
            }

            // Filtre par utilisateur
            if (isset($criteria['user_id']) && !empty($criteria['user_id'])) {
                $sql .= " AND d.user_id = :user_id";
                $params[':user_id'] = $criteria['user_id'];
            }

            // Filtre par métadonnées
            if (isset($criteria['metadata']) && is_array($criteria['metadata'])) {
                foreach ($criteria['metadata'] as $key => $value) {
                    $paramKey = ':meta_key_' . $key;
                    $paramValue = ':meta_value_' . $key;

                    $sql .= " AND EXISTS (SELECT 1 FROM document_metadata dm2 WHERE dm2.document_id = d.id AND dm2.key = {$paramKey} AND dm2.value LIKE {$paramValue})";

                    $params[$paramKey] = $key;
                    $params[$paramValue] = '%' . $value . '%';
                }
            }

            // Recherche textuelle dans le nom du fichier
            if (isset($criteria['search']) && !empty($criteria['search'])) {
                $sql .= " AND d.file_name LIKE :search";
                $params[':search'] = '%' . $criteria['search'] . '%';
            }

            // Groupe par ID de document pour éviter les doublons
            $sql .= " GROUP BY d.id";

            // Tri par date de création (du plus récent au plus ancien)
            $sql .= " ORDER BY d.created_at DESC";

            // Limite et pagination
            if (isset($criteria['limit']) && is_numeric($criteria['limit'])) {
                $sql .= " LIMIT :limit";
                $offset = isset($criteria['offset']) && is_numeric($criteria['offset']) ? $criteria['offset'] : 0;
                $sql .= " OFFSET :offset";

                $params[':limit'] = (int) $criteria['limit'];
                $params[':offset'] = (int) $offset;
            }

            $stmt = $this->pdo->prepare($sql);

            // Liaison des paramètres en respectant leur type
            foreach ($params as $key => $value) {
                if (strpos($key, 'limit') !== false || strpos($key, 'offset') !== false) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }

            $stmt->execute();

            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer les métadonnées pour chaque document
            foreach ($documents as &$document) {
                $metadataStmt = $this->pdo->prepare("
                    SELECT `key`, `value` FROM document_metadata WHERE document_id = :document_id
                ");

                $metadataStmt->bindParam(':document_id', $document['id']);
                $metadataStmt->execute();

                $metadata = [];
                while ($row = $metadataStmt->fetch(PDO::FETCH_ASSOC)) {
                    $metadata[$row['key']] = $row['value'];
                }

                $document['metadata'] = $metadata;
            }

            return $documents;
        } catch (Exception $e) {
            error_log("DocumentManager::searchDocuments - Erreur: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Supprime un document (soft delete)
     * 
     * @param int $documentId ID du document
     * @return bool Succès ou échec
     */
    public function deleteDocument($documentId)
    {
        try {
            // Au lieu de supprimer définitivement, on pourrait mettre à jour un champ "deleted"
            // Mais pour cet exemple, nous supprimons réellement
            $document = $this->getDocument($documentId);

            if (!$document) {
                throw new Exception("Document non trouvé: " . $documentId);
            }

            $this->pdo->beginTransaction();

            // Supprimer les métadonnées
            $stmtMeta = $this->pdo->prepare("DELETE FROM document_metadata WHERE document_id = :document_id");
            $stmtMeta->bindParam(':document_id', $documentId);
            $stmtMeta->execute();

            // Supprimer les logs d'accès
            $stmtLogs = $this->pdo->prepare("DELETE FROM document_access_log WHERE document_id = :document_id");
            $stmtLogs->bindParam(':document_id', $documentId);
            $stmtLogs->execute();

            // Supprimer les versions
            $stmtVersions = $this->pdo->prepare("DELETE FROM document_versions WHERE document_id = :document_id");
            $stmtVersions->bindParam(':document_id', $documentId);
            $stmtVersions->execute();

            // Supprimer les partages
            $stmtShares = $this->pdo->prepare("DELETE FROM document_shares WHERE document_id = :document_id");
            $stmtShares->bindParam(':document_id', $documentId);
            $stmtShares->execute();

            // Supprimer le document lui-même
            $stmtDoc = $this->pdo->prepare("DELETE FROM documents WHERE id = :document_id");
            $stmtDoc->bindParam(':document_id', $documentId);
            $stmtDoc->execute();

            $this->pdo->commit();

            // Supprimer le fichier physique
            $filePath = $_SERVER['DOCUMENT_ROOT'] . $document['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log("DocumentManager::deleteDocument - Erreur: " . $e->getMessage());
            return false;
        }
    }
}