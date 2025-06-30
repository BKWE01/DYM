-- Script SQL pour ajouter les champs liés au projet dans la table supplier_returns
ALTER TABLE `supplier_returns` 
ADD COLUMN `project_id` int(11) DEFAULT NULL COMMENT 'ID du projet associé (si disponible)',
ADD COLUMN `project_code` varchar(255) DEFAULT NULL COMMENT 'Code du projet associé',
ADD COLUMN `project_name` varchar(255) DEFAULT NULL COMMENT 'Nom du projet associé';

-- Ajout d'index pour améliorer les performances des requêtes
ALTER TABLE `supplier_returns`
ADD INDEX `idx_project_code` (`project_code`),
ADD INDEX `idx_project_id` (`project_id`);

-- Ajout d'une contrainte de clé étrangère si le champ identification_projet.id existe
-- Décommentez cette partie si vous souhaitez ajouter la contrainte
/*
ALTER TABLE `supplier_returns`
ADD CONSTRAINT `fk_supplier_returns_project` FOREIGN KEY (`project_id`) 
REFERENCES `identification_projet` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
*/