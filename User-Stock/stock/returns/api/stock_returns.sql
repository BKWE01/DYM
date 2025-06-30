-- Table pour stocker les retours
CREATE TABLE IF NOT EXISTS `stock_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL COMMENT 'Référence au produit',
  `quantity` float NOT NULL COMMENT 'Quantité retournée',
  `origin` varchar(255) NOT NULL COMMENT 'Provenance du retour',
  `return_reason` varchar(50) NOT NULL COMMENT 'Motif du retour',
  `other_reason` text DEFAULT NULL COMMENT 'Autre motif de retour',
  `product_condition` varchar(50) NOT NULL COMMENT 'État du produit',
  `returned_by` varchar(255) NOT NULL COMMENT 'Nom de la personne effectuant le retour',
  `comments` text DEFAULT NULL COMMENT 'Commentaires additionnels',
  `status` enum('pending','approved','rejected','completed','canceled') NOT NULL DEFAULT 'pending' COMMENT 'Statut du retour',
  `created_by` int(11) NOT NULL COMMENT 'Utilisateur qui a créé le retour',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date de création',
  `approved_by` int(11) DEFAULT NULL COMMENT 'Utilisateur qui a approuvé le retour',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'Date d''approbation',
  `rejected_by` int(11) DEFAULT NULL COMMENT 'Utilisateur qui a rejeté le retour',
  `rejected_at` timestamp NULL DEFAULT NULL COMMENT 'Date de rejet',
  `reject_reason` text DEFAULT NULL COMMENT 'Motif du rejet',
  `completed_by` int(11) DEFAULT NULL COMMENT 'Utilisateur qui a complété le retour',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'Date de complétion',
  `canceled_by` int(11) DEFAULT NULL COMMENT 'Utilisateur qui a annulé le retour',
  `canceled_at` timestamp NULL DEFAULT NULL COMMENT 'Date d''annulation',
  `cancel_reason` text DEFAULT NULL COMMENT 'Motif de l''annulation',
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour l'historique des retours
CREATE TABLE IF NOT EXISTS `stock_returns_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL COMMENT 'Référence au retour',
  `action` varchar(50) NOT NULL COMMENT 'Action effectuée',
  `details` text DEFAULT NULL COMMENT 'Détails supplémentaires',
  `user_id` int(11) NOT NULL COMMENT 'Utilisateur ayant effectué l''action',
  `user_name` varchar(255) DEFAULT NULL COMMENT 'Nom de l''utilisateur',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date de l''action',
  PRIMARY KEY (`id`),
  KEY `return_id` (`return_id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;