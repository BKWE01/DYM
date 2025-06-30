-- ========================================
-- SCRIPT DE CRÉATION/MISE À JOUR DE LA TABLE PAYMENT_METHODS
-- Version: 2.0
-- Auteur: DYM Team
-- Description: Table des modes de paiement avec contraintes anti-doublons
-- ========================================

-- Vérifier si la table existe et la sauvegarder si nécessaire
DROP TABLE IF EXISTS `payment_methods_backup`;

-- Créer une sauvegarde si la table existe
CREATE TABLE IF NOT EXISTS `payment_methods_backup` AS 
SELECT * FROM `payment_methods` WHERE 1=0;

-- Insérer les données existantes dans la sauvegarde
INSERT IGNORE INTO `payment_methods_backup` 
SELECT * FROM `payment_methods` WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'payment_methods');

-- ========================================
-- CRÉATION DE LA NOUVELLE TABLE
-- ========================================

DROP TABLE IF EXISTS `payment_methods`;

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL COMMENT 'Code unique du mode de paiement',
  `label` varchar(100) NOT NULL COMMENT 'Libellé affiché',
  `description` text DEFAULT NULL COMMENT 'Description détaillée',
  `icon` varchar(10) DEFAULT NULL COMMENT 'Icône emoji ou caractère',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Mode actif/inactif',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  -- Clé primaire
  PRIMARY KEY (`id`),
  
  -- Index uniques pour éviter les doublons
  UNIQUE KEY `idx_code_unique` (`code`),
  UNIQUE KEY `idx_label_unique` (`label`),
  
  -- Index pour les recherches fréquentes
  KEY `idx_active` (`is_active`),
  KEY `idx_display_order` (`display_order`),
  KEY `idx_code_active` (`code`, `is_active`),
  KEY `idx_label_active` (`label`, `is_active`),
  
  -- Index composites pour les performances
  KEY `idx_active_order` (`is_active`, `display_order`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_updated_at` (`updated_at`),
  
  -- Contraintes de validation
  CONSTRAINT `chk_code_format` CHECK (
    `code` REGEXP '^[a-zA-Z0-9_-]+$' AND 
    LENGTH(TRIM(`code`)) >= 2 AND 
    LENGTH(TRIM(`code`)) <= 50
  ),
  CONSTRAINT `chk_label_length` CHECK (
    LENGTH(TRIM(`label`)) >= 2 AND 
    LENGTH(TRIM(`label`)) <= 100
  ),
  CONSTRAINT `chk_display_order` CHECK (`display_order` >= 0),
  CONSTRAINT `chk_is_active` CHECK (`is_active` IN (0, 1))
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Modes de paiement disponibles';

-- ========================================
-- TRIGGERS POUR LA VALIDATION ET LA NORMALISATION
-- ========================================

-- Trigger pour normaliser les données avant insertion
DELIMITER $$

DROP TRIGGER IF EXISTS `payment_methods_before_insert`$$

CREATE TRIGGER `payment_methods_before_insert` 
BEFORE INSERT ON `payment_methods` 
FOR EACH ROW 
BEGIN
    -- Normalisation des chaînes (suppression des espaces)
    SET NEW.code = TRIM(NEW.code);
    SET NEW.label = TRIM(NEW.label);
    SET NEW.description = CASE 
        WHEN NEW.description IS NULL OR TRIM(NEW.description) = '' THEN NULL 
        ELSE TRIM(NEW.description) 
    END;
    
    -- Validation du code
    IF NEW.code IS NULL OR NEW.code = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Le code ne peut pas être vide';
    END IF;
    
    -- Validation du libellé
    IF NEW.label IS NULL OR NEW.label = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Le libellé ne peut pas être vide';
    END IF;
    
    -- Définir l'ordre d'affichage automatiquement si non spécifié
    IF NEW.display_order = 0 OR NEW.display_order IS NULL THEN
        SELECT COALESCE(MAX(display_order), 0) + 1 INTO NEW.display_order FROM payment_methods;
    END IF;
    
    -- Assurer qu'il y a au moins un mode actif
    IF NEW.is_active = 1 AND (SELECT COUNT(*) FROM payment_methods WHERE is_active = 1) = 0 THEN
        SET NEW.is_active = 1;
    END IF;
END$$

-- Trigger pour normaliser les données avant mise à jour
DROP TRIGGER IF EXISTS `payment_methods_before_update`$$

CREATE TRIGGER `payment_methods_before_update` 
BEFORE UPDATE ON `payment_methods` 
FOR EACH ROW 
BEGIN
    -- Normalisation des chaînes
    SET NEW.code = TRIM(NEW.code);
    SET NEW.label = TRIM(NEW.label);
    SET NEW.description = CASE 
        WHEN NEW.description IS NULL OR TRIM(NEW.description) = '' THEN NULL 
        ELSE TRIM(NEW.description) 
    END;
    
    -- Validation du code
    IF NEW.code IS NULL OR NEW.code = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Le code ne peut pas être vide';
    END IF;
    
    -- Validation du libellé
    IF NEW.label IS NULL OR NEW.label = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Le libellé ne peut pas être vide';
    END IF;
    
    -- Empêcher la désactivation du dernier mode actif
    IF OLD.is_active = 1 AND NEW.is_active = 0 THEN
        IF (SELECT COUNT(*) FROM payment_methods WHERE is_active = 1 AND id != NEW.id) = 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Impossible de désactiver le dernier mode de paiement actif';
        END IF;
    END IF;
END$$

-- Trigger pour la journalisation des modifications importantes
DROP TRIGGER IF EXISTS `payment_methods_after_update`$$

CREATE TRIGGER `payment_methods_after_update` 
AFTER UPDATE ON `payment_methods` 
FOR EACH ROW 
BEGIN
    -- Log des changements importants
    IF OLD.code != NEW.code OR OLD.is_active != NEW.is_active THEN
        INSERT INTO payment_methods_audit_log (
            payment_method_id,
            action_type,
            old_values,
            new_values,
            changed_at
        ) VALUES (
            NEW.id,
            'UPDATE',
            JSON_OBJECT(
                'code', OLD.code,
                'label', OLD.label,
                'is_active', OLD.is_active
            ),
            JSON_OBJECT(
                'code', NEW.code,
                'label', NEW.label,
                'is_active', NEW.is_active
            ),
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- ========================================
-- TABLE D'AUDIT (OPTIONNELLE)
-- ========================================

CREATE TABLE IF NOT EXISTS `payment_methods_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_method_id` int(11) NOT NULL,
  `action_type` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `changed_by` int(11) DEFAULT NULL COMMENT 'ID de l''utilisateur qui a fait la modification',
  
  PRIMARY KEY (`id`),
  KEY `idx_payment_method_id` (`payment_method_id`),
  KEY `idx_changed_at` (`changed_at`),
  KEY `idx_action_type` (`action_type`),
  
  FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Journal des modifications des modes de paiement';

-- ========================================
-- INSERTION DES DONNÉES PAR DÉFAUT
-- ========================================

-- Restaurer les données depuis la sauvegarde si elle existe
INSERT IGNORE INTO `payment_methods` (
    `id`, `code`, `label`, `description`, `icon`, `is_active`, `display_order`, `created_at`, `updated_at`
)
SELECT 
    `id`, `code`, `label`, `description`, `icon`, `is_active`, `display_order`, `created_at`, `updated_at`
FROM `payment_methods_backup`
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'payment_methods_backup');

-- Insérer les modes de paiement par défaut si la table est vide
INSERT IGNORE INTO `payment_methods` (`code`, `label`, `description`, `icon`, `is_active`, `display_order`) VALUES
('especes', 'Espèces', 'Paiement en liquide', '💰', 1, 1),
('cheque', 'Chèque', 'Paiement par chèque bancaire', '🏛️', 1, 2),
('virement', 'Virement bancaire', 'Virement de compte à compte', '🏦', 1, 3),
('carte_credit', 'Carte de crédit', 'Paiement par carte de crédit', '💳', 1, 4),
('carte_debit', 'Carte de débit', 'Paiement par carte de débit', '💳', 1, 5),
('credit_fournisseur', 'Crédit fournisseur', 'Paiement à crédit chez le fournisseur', '🏭', 1, 6),
('mobile_money', 'Mobile Money', 'Paiement par portefeuille mobile', '📱', 1, 7),
('traite', 'Traite', 'Paiement par traite commerciale', '📝', 1, 8),
('autre', 'Autre', 'Autre mode de paiement (à préciser)', '❓', 1, 9);

-- ========================================
-- PROCÉDURES STOCKÉES UTILITAIRES
-- ========================================

-- Procédure pour nettoyer les doublons
DELIMITER $$

DROP PROCEDURE IF EXISTS `CleanPaymentMethodDuplicates`$$

CREATE PROCEDURE `CleanPaymentMethodDuplicates`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE duplicate_id INT;
    DECLARE keep_id INT;
    DECLARE duplicate_code VARCHAR(50);
    
    -- Curseur pour les doublons par code
    DECLARE duplicate_cursor CURSOR FOR
        SELECT 
            MIN(id) as keep_id,
            MAX(id) as duplicate_id,
            code
        FROM payment_methods 
        GROUP BY LOWER(TRIM(code))
        HAVING COUNT(*) > 1;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    START TRANSACTION;
    
    OPEN duplicate_cursor;
    
    read_loop: LOOP
        FETCH duplicate_cursor INTO keep_id, duplicate_id, duplicate_code;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Mettre à jour les références dans achats_materiaux
        UPDATE achats_materiaux 
        SET mode_paiement = (SELECT code FROM payment_methods WHERE id = keep_id)
        WHERE mode_paiement = (SELECT code FROM payment_methods WHERE id = duplicate_id);
        
        -- Supprimer le doublon
        DELETE FROM payment_methods WHERE id = duplicate_id;
        
    END LOOP;
    
    CLOSE duplicate_cursor;
    
    COMMIT;
    
    SELECT 'Nettoyage des doublons terminé' as message;
END$$

-- Procédure pour réorganiser les ordres d'affichage
DROP PROCEDURE IF EXISTS `ReorganizeDisplayOrder`$$

CREATE PROCEDURE `ReorganizeDisplayOrder`()
BEGIN
    SET @row_number = 0;
    
    UPDATE payment_methods 
    SET display_order = (@row_number := @row_number + 1)
    ORDER BY display_order ASC, id ASC;
    
    SELECT 'Réorganisation des ordres terminée' as message;
END$$

-- Fonction pour vérifier les doublons
DROP FUNCTION IF EXISTS `HasDuplicates`$$

CREATE FUNCTION `HasDuplicates`() RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE duplicate_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO duplicate_count
    FROM (
        SELECT code 
        FROM payment_methods 
        GROUP BY LOWER(TRIM(code)) 
        HAVING COUNT(*) > 1
        UNION
        SELECT label 
        FROM payment_methods 
        GROUP BY LOWER(TRIM(label)) 
        HAVING COUNT(*) > 1
    ) as duplicates;
    
    RETURN duplicate_count;
END$$

DELIMITER ;

-- ========================================
-- VUES UTILES
-- ========================================

-- Vue pour les modes de paiement actifs avec statistiques
CREATE OR REPLACE VIEW `active_payment_methods_with_stats` AS
SELECT 
    pm.id,
    pm.code,
    pm.label,
    pm.description,
    pm.icon,
    pm.display_order,
    pm.created_at,
    pm.updated_at,
    COALESCE(usage.total_usage, 0) as total_usage,
    COALESCE(usage.recent_usage, 0) as recent_usage_30d,
    COALESCE(usage.last_used, NULL) as last_used_date
FROM payment_methods pm
LEFT JOIN (
    SELECT 
        mode_paiement,
        COUNT(*) as total_usage,
        SUM(CASE WHEN date_achat >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_usage,
        MAX(date_achat) as last_used
    FROM achats_materiaux 
    WHERE mode_paiement IS NOT NULL
    GROUP BY mode_paiement
) usage ON pm.code = usage.mode_paiement
WHERE pm.is_active = 1
ORDER BY pm.display_order ASC;

-- Vue pour détecter les problèmes de données
CREATE OR REPLACE VIEW `payment_methods_issues` AS
SELECT 
    id,
    code,
    label,
    CASE 
        WHEN code != TRIM(code) THEN 'Code avec espaces'
        WHEN label != TRIM(label) THEN 'Libellé avec espaces'
        WHEN NOT (code REGEXP '^[a-zA-Z0-9_-]+$') THEN 'Code avec caractères invalides'
        WHEN LENGTH(code) < 2 THEN 'Code trop court'
        WHEN LENGTH(label) < 2 THEN 'Libellé trop court'
        ELSE 'OK'
    END as issue_type
FROM payment_methods
WHERE 
    code != TRIM(code) 
    OR label != TRIM(label)
    OR NOT (code REGEXP '^[a-zA-Z0-9_-]+$')
    OR LENGTH(code) < 2
    OR LENGTH(label) < 2;

-- ========================================
-- NETTOYAGE FINAL
-- ========================================

-- Supprimer la table de sauvegarde si tout s'est bien passé
-- DROP TABLE IF EXISTS `payment_methods_backup`;

-- Vérification finale
SELECT 
    COUNT(*) as total_modes,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as modes_actifs,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as modes_inactifs,
    HasDuplicates() as doublons_detectes
FROM payment_methods;

-- Afficher les statistiques
SELECT 'Installation terminée avec succès!' as message;
SELECT 'Table payment_methods créée avec contraintes anti-doublons' as info;