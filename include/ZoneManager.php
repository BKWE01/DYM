<?php
/**
 * Gestionnaire centralisé des zones
 * Gère la détection, les colonnes et les opérations spécifiques aux zones
 */
class ZoneManager {
    private $pdo;
    private $userId;
    private $currentZone;
    
    public function __construct($pdo, $userId = null) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->initializeCurrentZone();
    }
    
    /**
     * Initialise la zone courante de l'utilisateur
     */
    private function initializeCurrentZone() {
        // Vérifier d'abord si une zone est déjà en session
        if (isset($_SESSION['active_zone_id'])) {
            $this->currentZone = [
                'id' => $_SESSION['active_zone_id'],
                'code' => $_SESSION['active_zone_code'],
                'name' => $_SESSION['active_zone_name']
            ];
            return;
        }
        
        // Sinon, déterminer la zone de l'utilisateur
        if ($this->userId) {
            $this->detectUserZone();
        } else {
            $this->setDefaultZone();
        }
    }
    
    /**
     * Détermine la zone d'un utilisateur selon la priorité :
     * 1. Zone dont il est responsable
     * 2. Zone avec accès dans zone_access
     * 3. Zone par défaut si super_admin
     */
    private function detectUserZone() {
        try {
            // 1. Vérifier si l'utilisateur est responsable d'une zone
            $stmt = $this->pdo->prepare("
                SELECT z.id, z.code, z.nom, u.role
                FROM zones z, users_exp u
                WHERE z.responsable_id = :user_id 
                AND u.id = :user_id
                AND z.status = 'active'
            ");
            $stmt->execute([':user_id' => $this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->setCurrentZone($result);
                return;
            }
            
            // 2. Vérifier les accès dans zone_access
            $stmt = $this->pdo->prepare("
                SELECT z.id, z.code, z.nom, u.role
                FROM zones z
                JOIN zone_access za ON z.id = za.zone_id
                JOIN users_exp u ON u.id = :user_id
                WHERE za.user_id = :user_id 
                AND z.status = 'active'
                AND za.can_view = 1
                ORDER BY za.can_manage DESC, za.can_edit DESC
                LIMIT 1
            ");
            $stmt->execute([':user_id' => $this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->setCurrentZone($result);
                return;
            }
            
            // 3. Si super_admin, donner accès à la première zone active
            $stmt = $this->pdo->prepare("
                SELECT z.id, z.code, z.nom, u.role
                FROM zones z, users_exp u
                WHERE u.id = :user_id 
                AND u.role = 'super_admin'
                AND z.status = 'active'
                ORDER BY z.id ASC
                LIMIT 1
            ");
            $stmt->execute([':user_id' => $this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->setCurrentZone($result);
                return;
            }
            
            // Fallback : zone par défaut
            $this->setDefaultZone();
            
        } catch (PDOException $e) {
            error_log("Erreur détection zone utilisateur: " . $e->getMessage());
            $this->setDefaultZone();
        }
    }
    
    /**
     * Définit la zone par défaut (DYM-MAIN)
     */
    private function setDefaultZone() {
        $stmt = $this->pdo->prepare("SELECT id, code, nom FROM zones WHERE code = 'DYM-MAIN' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $this->setCurrentZone($result);
        } else {
            // Créer la zone par défaut si elle n'existe pas
            $this->createDefaultZone();
        }
    }
    
    /**
     * Crée la zone par défaut si elle n'existe pas
     */
    private function createDefaultZone() {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO zones (code, nom, description, status, created_at) 
                VALUES ('DYM-MAIN', 'DYM MANUFACTURE Principal', 'Zone principale par défaut', 'active', NOW())
            ");
            $stmt->execute();
            
            $zoneId = $this->pdo->lastInsertId();
            $this->setCurrentZone([
                'id' => $zoneId,
                'code' => 'DYM-MAIN',
                'nom' => 'DYM MANUFACTURE Principal'
            ]);
        } catch (PDOException $e) {
            error_log("Erreur création zone par défaut: " . $e->getMessage());
        }
    }
    
    /**
     * Définit la zone courante
     */
    private function setCurrentZone($zoneData) {
        $this->currentZone = [
            'id' => $zoneData['id'],
            'code' => $zoneData['code'],
            'name' => $zoneData['nom']
        ];
        
        // Stocker en session
        $_SESSION['active_zone_id'] = $zoneData['id'];
        $_SESSION['active_zone_code'] = $zoneData['code'];
        $_SESSION['active_zone_name'] = $zoneData['nom'];
        
        error_log("Zone active définie: " . $zoneData['code']);
    }
    
    /**
     * Retourne la zone courante
     */
    public function getCurrentZone() {
        return $this->currentZone;
    }
    
    /**
     * Retourne l'ID de la zone courante
     */
    public function getCurrentZoneId() {
        return $this->currentZone['id'] ?? null;
    }
    
    /**
     * Retourne le code de la zone courante
     */
    public function getCurrentZoneCode() {
        return $this->currentZone['code'] ?? null;
    }
    
    /**
     * Retourne les noms des colonnes pour la zone courante
     */
    public function getQuantityColumns() {
        $zoneCode = $this->getCurrentZoneCode();
        
        // Zone principale : utilise les colonnes par défaut
        if (empty($zoneCode) || $zoneCode === 'DYM-MAIN') {
            return [
                'quantity' => 'quantity',
                'quantity_reserved' => 'quantity_reserved'
            ];
        }
        
        // Autres zones : colonnes spécifiques
        $suffix = $this->formatZoneCodeForColumn($zoneCode);
        
        $quantityCol = 'quantity_' . $suffix;
        $reservedCol = 'quantity_reserved_' . $suffix;
        
        // Vérifier que les colonnes existent, sinon les créer
        $this->ensureColumnsExist($quantityCol, $reservedCol);
        
        return [
            'quantity' => $quantityCol,
            'quantity_reserved' => $reservedCol
        ];
    }
    
    /**
     * Formate le code de zone pour le nom de colonne
     */
    private function formatZoneCodeForColumn($zoneCode) {
        // DYM-BING → dymbing
        return strtolower(str_replace(['-', '_', ' '], '', $zoneCode));
    }
    
    /**
     * S'assure que les colonnes de zone existent dans la table products
     */
    private function ensureColumnsExist($quantityCol, $reservedCol) {
        try {
            // Vérifier si les colonnes existent
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM products LIKE ?");
            
            // Colonne quantity
            $stmt->execute([$quantityCol]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->exec("ALTER TABLE products ADD COLUMN `$quantityCol` int(11) DEFAULT 0 COMMENT 'Quantité pour zone {$this->currentZone['name']}'");
                error_log("Colonne $quantityCol créée");
            }
            
            // Colonne quantity_reserved
            $stmt->execute([$reservedCol]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->exec("ALTER TABLE products ADD COLUMN `$reservedCol` int(11) DEFAULT 0 COMMENT 'Quantité réservée pour zone {$this->currentZone['name']}'");
                error_log("Colonne $reservedCol créée");
            }
        } catch (PDOException $e) {
            error_log("Erreur création colonnes zone: " . $e->getMessage());
        }
    }
    
    /**
     * Met à jour la quantité d'un produit dans la zone courante
     */
    public function updateProductQuantity($productId, $quantityChange, $type = 'quantity') {
        $columns = $this->getQuantityColumns();
        $column = $columns[$type];
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE products 
                SET `$column` = `$column` + :change 
                WHERE id = :product_id
            ");
            $stmt->execute([
                ':change' => $quantityChange,
                ':product_id' => $productId
            ]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur mise à jour quantité: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtient la quantité d'un produit dans la zone courante
     */
    public function getProductQuantity($productId, $type = 'quantity') {
        $columns = $this->getQuantityColumns();
        $column = $columns[$type];
        
        try {
            $stmt = $this->pdo->prepare("SELECT `$column` as qty FROM products WHERE id = :product_id");
            $stmt->execute([':product_id' => $productId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? floatval($result['qty']) : 0;
        } catch (PDOException $e) {
            error_log("Erreur récupération quantité: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Vérifie si l'utilisateur a accès à une zone
     */
    public function hasAccessToZone($zoneId, $accessType = 'view') {
        if (!$this->userId) return false;
        
        try {
            // Super admin a accès à tout
            $stmt = $this->pdo->prepare("SELECT role FROM users_exp WHERE id = :user_id");
            $stmt->execute([':user_id' => $this->userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['role'] === 'super_admin') {
                return true;
            }
            
            // Vérifier si responsable de la zone
            $stmt = $this->pdo->prepare("SELECT id FROM zones WHERE id = :zone_id AND responsable_id = :user_id");
            $stmt->execute([':zone_id' => $zoneId, ':user_id' => $this->userId]);
            if ($stmt->rowCount() > 0) {
                return true;
            }
            
            // Vérifier les droits dans zone_access
            $accessColumn = 'can_view';
            if ($accessType === 'edit') $accessColumn = 'can_edit';
            if ($accessType === 'manage') $accessColumn = 'can_manage';
            
            $stmt = $this->pdo->prepare("
                SELECT id FROM zone_access 
                WHERE user_id = :user_id AND zone_id = :zone_id AND $accessColumn = 1
            ");
            $stmt->execute([':user_id' => $this->userId, ':zone_id' => $zoneId]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur vérification accès zone: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Génère un sélecteur de zones pour l'interface
     */
    public function generateZoneSelector() {
        if (!$this->userId) return '';
        
        try {
            // Récupérer les zones accessibles
            $zones = $this->getAccessibleZones();
            
            if (count($zones) <= 1) {
                // Une seule zone : affichage simple
                return '<div class="zone-display">
                    <i class="fas fa-building"></i> ' . 
                    htmlspecialchars($this->currentZone['name']) . 
                    '</div>';
            }
            
            // Sélecteur multiple
            $html = '<form id="zone-switch-form" method="post" action="">
                <select name="zone_id" onchange="this.form.submit()" class="form-control">';
            
            foreach ($zones as $zone) {
                $selected = ($zone['id'] == $this->currentZone['id']) ? 'selected' : '';
                $html .= '<option value="' . $zone['id'] . '" ' . $selected . '>' . 
                         htmlspecialchars($zone['nom']) . '</option>';
            }
            
            $html .= '</select>
                <input type="hidden" name="action" value="switch_zone">
                </form>';
            
            return $html;
        } catch (Exception $e) {
            error_log("Erreur génération sélecteur zone: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Récupère les zones accessibles par l'utilisateur
     */
    public function getAccessibleZones() {
        if (!$this->userId) return [];
        
        try {
            // Super admin : toutes les zones
            $stmt = $this->pdo->prepare("SELECT role FROM users_exp WHERE id = :user_id");
            $stmt->execute([':user_id' => $this->userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['role'] === 'super_admin') {
                $stmt = $this->pdo->prepare("SELECT id, code, nom FROM zones WHERE status = 'active' ORDER BY nom");
                $stmt->execute();
            } else {
                // Zones où l'utilisateur est responsable + zones avec accès
                $stmt = $this->pdo->prepare("
                    SELECT DISTINCT z.id, z.code, z.nom 
                    FROM zones z
                    LEFT JOIN zone_access za ON z.id = za.zone_id AND za.user_id = :user_id
                    WHERE z.status = 'active' 
                    AND (z.responsable_id = :user_id OR za.can_view = 1)
                    ORDER BY z.nom
                ");
                $stmt->execute([':user_id' => $this->userId]);
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur récupération zones accessibles: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Change la zone active
     */
    public function switchZone($zoneId) {
        if (!$this->hasAccessToZone($zoneId)) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT id, code, nom FROM zones WHERE id = :zone_id AND status = 'active'");
            $stmt->execute([':zone_id' => $zoneId]);
            $zone = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($zone) {
                $this->setCurrentZone($zone);
                return true;
            }
        } catch (PDOException $e) {
            error_log("Erreur changement zone: " . $e->getMessage());
        }
        
        return false;
    }
}