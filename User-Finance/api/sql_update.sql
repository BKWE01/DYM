-- Configuration SQL pour supporter les expressions système et classiques sans doublons
-- À exécuter pour optimiser les performances et éviter les doublons

-- 1. Ajouter un index unique sur purchase_orders pour éviter les doublons
ALTER TABLE `purchase_orders` 
ADD UNIQUE INDEX `idx_unique_order` (`order_number`, `expression_id`);

-- 2. Créer une vue pour unifier les expressions des deux sources
CREATE OR REPLACE VIEW `unified_expressions` AS
SELECT 
    ed.idExpression as expression_id,
    ed.designation,
    ed.quantity,
    ed.unit,
    ed.prix_unitaire,
    ed.montant,
    ed.fournisseur,
    ed.modePaiement,
    ed.user_achat,
    ed.created_at,
    ed.updated_at,
    'expression_dym' as source_table,
    ip.code_projet,
    ip.nom_client,
    ip.description_projet
FROM expression_dym ed
LEFT JOIN identification_projet ip ON ed.idExpression = ip.idExpression

UNION

SELECT 
    b.idBesoin as expression_id,
    b.designation_article as designation,
    b.qt_acheter as quantity,
    'unité' as unit,
    0 as prix_unitaire,
    0 as montant,
    '' as fournisseur,
    'Non spécifié' as modePaiement,
    b.user_achat,
    b.created_at,
    b.updated_at,
    'besoins' as source_table,
    b.idBesoin as code_projet,
    'Expression Système' as nom_client,
    b.caracteristique as description_projet
FROM besoins b;

-- 3. Créer un index pour améliorer les performances des jointures
ALTER TABLE `achats_materiaux` 
ADD INDEX `idx_expression_id` (`expression_id`);

ALTER TABLE `identification_projet` 
ADD INDEX `idx_expression` (`idExpression`);

ALTER TABLE `besoins` 
ADD INDEX `idx_besoin` (`idBesoin`);

-- 4. Fonction stockée pour récupérer les informations de projet sans doublons
DELIMITER //
CREATE FUNCTION `get_project_info`(p_expression_id VARCHAR(255))
RETURNS VARCHAR(500)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE project_info VARCHAR(500);
    
    -- Chercher d'abord dans identification_projet
    SELECT CONCAT_WS('|', code_projet, nom_client) INTO project_info
    FROM identification_projet 
    WHERE idExpression = p_expression_id
    LIMIT 1;
    
    -- Si non trouvé, chercher dans besoins
    IF project_info IS NULL THEN
        SELECT CONCAT_WS('|', idBesoin, 'Expression Système') INTO project_info
        FROM besoins 
        WHERE idBesoin = p_expression_id
        LIMIT 1;
    END IF;
    
    RETURN COALESCE(project_info, 'N/A|N/A');
END//
DELIMITER ;

-- 5. Procédure pour nettoyer les doublons existants
DELIMITER //
CREATE PROCEDURE `clean_duplicate_orders`()
BEGIN
    -- Créer une table temporaire avec les ordres uniques
    CREATE TEMPORARY TABLE temp_unique_orders AS
    SELECT MIN(id) as keep_id, order_number, expression_id
    FROM purchase_orders
    GROUP BY order_number, expression_id;
    
    -- Supprimer les doublons
    DELETE po FROM purchase_orders po
    LEFT JOIN temp_unique_orders tuo ON po.id = tuo.keep_id
    WHERE tuo.keep_id IS NULL;
    
    DROP TEMPORARY TABLE temp_unique_orders;
END//
DELIMITER ;

-- Exécuter la procédure de nettoyage
CALL clean_duplicate_orders();

-- 6. Trigger pour éviter l'insertion de doublons à l'avenir
DELIMITER //
CREATE TRIGGER `prevent_duplicate_orders` 
BEFORE INSERT ON `purchase_orders`
FOR EACH ROW
BEGIN
    DECLARE order_exists INT;
    
    SELECT COUNT(*) INTO order_exists
    FROM purchase_orders
    WHERE order_number = NEW.order_number 
    AND expression_id = NEW.expression_id;
    
    IF order_exists > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Doublon détecté: cet ordre existe déjà';
    END IF;
END//
DELIMITER ;