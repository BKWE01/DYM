-- Table principale pour les transferts
CREATE TABLE IF NOT EXISTS `transferts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `source_project_id` int(11) NOT NULL,
  `source_project_code` varchar(255) NOT NULL,
  `destination_project_id` int(11) NOT NULL,
  `destination_project_code` varchar(255) NOT NULL,
  `quantity` float NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','completed','canceled') NOT NULL DEFAULT 'pending',
  `requested_by` int(11) NOT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `canceled_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `canceled_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `source_project_id` (`source_project_id`),
  KEY `destination_project_id` (`destination_project_id`),
  KEY `status` (`status`),
  KEY `requested_by` (`requested_by`),
  KEY `completed_by` (`completed_by`),
  KEY `canceled_by` (`canceled_by`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour l'historique des transferts
CREATE TABLE IF NOT EXISTS `transfert_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfert_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transfert_id` (`transfert_id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ajout de la colonne quantity_reserved à expression_dym si elle n'existe pas déjà
ALTER TABLE `expression_dym` 
ADD COLUMN IF NOT EXISTS `quantity_reserved` float DEFAULT 0 AFTER `quantity`;

-- Définition des contraintes de clés étrangères
ALTER TABLE `transferts`
  ADD CONSTRAINT `fk_transferts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transferts_source_project` FOREIGN KEY (`source_project_id`) REFERENCES `identification_projet` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transferts_destination_project` FOREIGN KEY (`destination_project_id`) REFERENCES `identification_projet` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transferts_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users_exp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transferts_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transferts_canceled_by` FOREIGN KEY (`canceled_by`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `transfert_history`
  ADD CONSTRAINT `fk_transfert_history_transfert` FOREIGN KEY (`transfert_id`) REFERENCES `transferts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfert_history_user` FOREIGN KEY (`user_id`) REFERENCES `users_exp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Procédure pour initialiser les quantités réservées dans expression_dym
-- basées sur les achats en cours ou commandés
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `initialize_reserved_quantities`()
BEGIN
  -- Mise à jour des quantités réservées dans expression_dym
  UPDATE expression_dym ed
  JOIN identification_projet ip ON ed.idExpression = ip.idExpression
  JOIN achats_materiaux am ON am.expression_id = ip.idExpression AND LOWER(am.designation) = LOWER(ed.designation)
  SET ed.quantity_reserved = am.quantity
  WHERE am.status IN ('commandé', 'en_cours')
    AND (ed.quantity_reserved IS NULL OR ed.quantity_reserved = 0);
  
  -- Insérer des entrées dans expression_dym pour les achats qui n'ont pas de correspondance
  INSERT INTO expression_dym (idExpression, designation, quantity_reserved, created_at)
  SELECT 
    am.expression_id,
    am.designation,
    am.quantity,
    NOW()
  FROM achats_materiaux am
  LEFT JOIN expression_dym ed ON am.expression_id = ed.idExpression AND LOWER(am.designation) = LOWER(ed.designation)
  WHERE ed.id IS NULL
    AND am.status IN ('commandé', 'en_cours');
END //
DELIMITER ;

-- Exécution de la procédure pour initialiser les données
CALL initialize_reserved_quantities();