-- Ajout de la colonne quantity_stock à la table expression_dym si elle n'existe pas déjà
SET @exist_quantity_stock := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'expression_dym'
    AND COLUMN_NAME = 'quantity_stock'
);

SET @statement := IF(@exist_quantity_stock = 0,
    'ALTER TABLE `expression_dym` ADD COLUMN `quantity_stock` float DEFAULT 0 COMMENT "Quantité entrée en stock" AFTER `quantity`',
    'SELECT "Column quantity_stock already exists"'
);

PREPARE alter_statement FROM @statement;
EXECUTE alter_statement;
DEALLOCATE PREPARE alter_statement;

-- Mise à jour des valeurs existantes de quantity_stock en fonction des données dans achats_materiaux
-- Cette mise à jour est optionnelle et peut être exécutée pour initialiser les données
UPDATE expression_dym ed
LEFT JOIN (
    SELECT 
        am.expression_id,
        am.designation,
        SUM(CASE WHEN am.status = 'reçu' THEN am.quantity ELSE 0 END) as quantite_recue
    FROM achats_materiaux am
    GROUP BY am.expression_id, am.designation
) am_totals ON ed.idExpression = am_totals.expression_id AND ed.designation = am_totals.designation
SET ed.quantity_stock = COALESCE(am_totals.quantite_recue, 0)
WHERE ed.quantity_stock = 0 AND am_totals.quantite_recue > 0;

-- Mettre à jour le statut des commandes qui sont complètement satisfaites
UPDATE expression_dym ed
SET ed.valide_achat = 'reçu'
WHERE ed.quantity_stock >= (ed.qt_acheter + COALESCE(ed.qt_restante, 0) - 0.001)
AND ed.valide_achat != 'reçu';