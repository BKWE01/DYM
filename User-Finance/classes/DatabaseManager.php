<?php
/**
 * DatabaseManager.php
 * Gestionnaire centralisé des interactions avec la base de données
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

class DatabaseManager 
{
    private $pdo;
    private $isConnected = false;
    
    public function __construct() 
    {
        $this->connect();
    }
    
    /**
     * Établit la connexion à la base de données
     * @throws Exception
     */
    private function connect(): void
    {
        try {
            require_once __DIR__ . '/../../database/connection.php';
            
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                throw new Exception("Connexion PDO non disponible");
            }
            
            $this->pdo = $pdo;
            $this->isConnected = true;
            
        } catch (Exception $e) {
            error_log("Erreur connexion DatabaseManager: " . $e->getMessage());
            throw new Exception("Impossible de se connecter à la base de données");
        }
    }
    
    /**
     * Vérifie l'état de la connexion
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->isConnected && $this->pdo !== null;
    }
    
    /**
     * Exécute une requête SELECT et retourne les résultats
     * @param string $query
     * @param array $params
     * @return array
     * @throws Exception
     */
    public function executeQuery(string $query, array $params = []): array
    {
        if (!$this->isConnected()) {
            throw new Exception("Base de données non connectée");
        }
        
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Erreur executeQuery: " . $e->getMessage() . " - Query: " . $query);
            throw new Exception("Erreur lors de l'exécution de la requête");
        }
    }
    
    /**
     * Exécute une requête UPDATE/INSERT/DELETE
     * @param string $query
     * @param array $params
     * @return bool
     * @throws Exception
     */
    public function executeUpdate(string $query, array $params = []): bool
    {
        if (!$this->isConnected()) {
            throw new Exception("Base de données non connectée");
        }
        
        try {
            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute($params);
            
            return $result && $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Erreur executeUpdate: " . $e->getMessage() . " - Query: " . $query);
            throw new Exception("Erreur lors de la mise à jour");
        }
    }
    
    /**
     * Exécute une requête INSERT et retourne l'ID inséré
     * @param string $query
     * @param array $params
     * @return int
     * @throws Exception
     */
    public function executeInsert(string $query, array $params = []): int
    {
        if (!$this->isConnected()) {
            throw new Exception("Base de données non connectée");
        }
        
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return (int) $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Erreur executeInsert: " . $e->getMessage() . " - Query: " . $query);
            throw new Exception("Erreur lors de l'insertion");
        }
    }
    
    /**
     * Récupère un seul enregistrement
     * @param string $query
     * @param array $params
     * @return array|null
     * @throws Exception
     */
    public function fetchOne(string $query, array $params = []): ?array
    {
        $result = $this->executeQuery($query, $params);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Récupère une seule valeur
     * @param string $query
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    public function fetchValue(string $query, array $params = [])
    {
        if (!$this->isConnected()) {
            throw new Exception("Base de données non connectée");
        }
        
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchColumn();
            
        } catch (PDOException $e) {
            error_log("Erreur fetchValue: " . $e->getMessage() . " - Query: " . $query);
            throw new Exception("Erreur lors de la récupération de la valeur");
        }
    }
    
    /**
     * Démarre une transaction
     * @return bool
     */
    public function beginTransaction(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            return $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            error_log("Erreur beginTransaction: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Confirme une transaction
     * @return bool
     */
    public function commit(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            return $this->pdo->commit();
        } catch (PDOException $e) {
            error_log("Erreur commit: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Annule une transaction
     * @return bool
     */
    public function rollback(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            return $this->pdo->rollback();
        } catch (PDOException $e) {
            error_log("Erreur rollback: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Exécute une transaction avec callback
     * @param callable $callback
     * @return mixed
     * @throws Exception
     */
    public function executeTransaction(callable $callback)
    {
        if (!$this->beginTransaction()) {
            throw new Exception("Impossible de démarrer la transaction");
        }
        
        try {
            $result = $callback($this);
            
            if (!$this->commit()) {
                throw new Exception("Impossible de confirmer la transaction");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Journalise un événement système
     * @param int $userId
     * @param string $action
     * @param string $entityType
     * @param int $entityId
     * @param string $details
     * @return bool
     */
    public function logSystemEvent(int $userId, string $action, string $entityType, int $entityId, string $details = ''): bool
    {
        $query = "INSERT INTO system_logs 
                  (user_id, action, type, entity_id, details, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
        
        try {
            return $this->executeUpdate($query, [$userId, $action, $entityType, $entityId, $details]);
        } catch (Exception $e) {
            error_log("Erreur logSystemEvent: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Nettoie les anciennes données de log
     * @param int $daysToKeep
     * @return bool
     */
    public function cleanOldLogs(int $daysToKeep = 90): bool
    {
        $query = "DELETE FROM system_logs 
                  WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        try {
            return $this->executeUpdate($query, [$daysToKeep]);
        } catch (Exception $e) {
            error_log("Erreur cleanOldLogs: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère les informations d'un utilisateur
     * @param int $userId
     * @return array|null
     */
    public function getUserInfo(int $userId): ?array
    {
        $query = "SELECT id, name, email, user_type, signature 
                  FROM users_exp 
                  WHERE id = ?";
        
        try {
            return $this->fetchOne($query, [$userId]);
        } catch (Exception $e) {
            error_log("Erreur getUserInfo: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Vérifie l'existence d'une table
     * @param string $tableName
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        $query = "SHOW TABLES LIKE ?";
        
        try {
            $result = $this->fetchValue($query, [$tableName]);
            return $result !== false;
        } catch (Exception $e) {
            error_log("Erreur tableExists: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère la configuration système
     * @param string $key
     * @return string|null
     */
    public function getSystemConfig(string $key): ?string
    {
        $configFile = __DIR__ . '/../../config/system_config.php';
        
        if (file_exists($configFile)) {
            $config = require $configFile;
            return $config[$key] ?? null;
        }
        
        return null;
    }
    
    /**
     * Ferme la connexion à la base de données
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->isConnected = false;
    }
    
    /**
     * Destructeur - ferme automatiquement la connexion
     */
    public function __destruct() 
    {
        $this->disconnect();
    }
}