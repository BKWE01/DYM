-- Ajout d'un champ 'notes' à la table stock_movement (s'il n'existe pas déjà)
ALTER TABLE `stock_movement` 
ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `invoice_id`;

-- Création d'une table dédiée pour les retours fournisseurs
CREATE TABLE IF NOT EXISTS `supplier_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `movement_id` int(11) NOT NULL COMMENT 'Référence au mouvement de stock',
  `product_id` int(11) NOT NULL COMMENT 'Référence au produit',
  `supplier_id` int(11) NULL COMMENT 'ID du fournisseur (si disponible)',
  `supplier_name` varchar(255) NOT NULL COMMENT 'Nom du fournisseur',
  `quantity` int(11) NOT NULL COMMENT 'Quantité retournée',
  `reason` varchar(50) NOT NULL COMMENT 'Motif du retour',
  `comment` text NULL COMMENT 'Commentaire additionnel',
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Statut du retour',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'Date de confirmation du retour',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date de création',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Date de mise à jour',
  PRIMARY KEY (`id`),
  KEY `movement_id` (`movement_id`),
  KEY `product_id` (`product_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ajout des commentaires de champs à la table stock_movement (pour plus de clarté)
ALTER TABLE `stock_movement` 
MODIFY COLUMN `movement_type` varchar(10) NOT NULL COMMENT 'Type de mouvement: entry, output, transfer, return',
MODIFY COLUMN `destination` varchar(225) NOT NULL COMMENT 'Destination du mouvement (peut être un fournisseur pour les retours)',
MODIFY COLUMN `notes` TEXT NULL COMMENT 'Notes additionnelles (motif du retour, etc.)';