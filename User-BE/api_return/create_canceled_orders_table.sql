-- Création de la table pour stocker les commandes annulées
-- Cette table permet de conserver l'historique des commandes annulées
-- notamment lorsqu'un projet est marqué comme terminé

CREATE TABLE IF NOT EXISTS `canceled_orders_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT 'ID de la commande annulée',
  `project_id` varchar(255) NOT NULL COMMENT 'ID du projet associé',
  `designation` varchar(255) NOT NULL COMMENT 'Nom du produit commandé',
  `canceled_by` int(11) NOT NULL COMMENT 'ID utilisateur ayant annulé la commande',
  `cancel_reason` varchar(255) NOT NULL COMMENT 'Raison de l\'annulation',
  `original_status` varchar(50) NOT NULL COMMENT 'Statut de la commande avant annulation',
  `is_partial` tinyint(1) DEFAULT 0 COMMENT 'Indique si c\'était une commande partielle',
  `canceled_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date d\'annulation',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `project_id` (`project_id`),
  KEY `canceled_by` (`canceled_by`),
  KEY `canceled_at` (`canceled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ajout d'une colonne pour la raison d'annulation dans la table achats_materiaux
ALTER TABLE `achats_materiaux` 
ADD COLUMN IF NOT EXISTS `canceled_at` timestamp NULL DEFAULT NULL COMMENT 'Date d\'annulation',
ADD COLUMN IF NOT EXISTS `canceled_by` int(11) DEFAULT NULL COMMENT 'ID utilisateur ayant annulé',
ADD COLUMN IF NOT EXISTS `cancel_reason` varchar(255) DEFAULT NULL COMMENT 'Raison de l\'annulation';