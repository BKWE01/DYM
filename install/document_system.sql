-- Table principale pour les documents
CREATE TABLE `exp_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `document_type` enum('expression_besoin', 'bon_commande', 'facture', 'bon_reception', 'retour_produit') NOT NULL,
  `reference_id` varchar(100) NOT NULL COMMENT 'ID de référence (ex: numéro d''expression, numéro de bon, etc.)',
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `document_type` (`document_type`),
  KEY `reference_id` (`reference_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les métadonnées spécifiques à chaque type de document
CREATE TABLE `exp_document_metadata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `key` varchar(255) NOT NULL COMMENT 'Nom de la métadonnée',
  `value` text NOT NULL COMMENT 'Valeur de la métadonnée',
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  CONSTRAINT `fk_document_metadata` FOREIGN KEY (`document_id`) REFERENCES `exp_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour l'historique des accès aux documents
CREATE TABLE `exp_document_access_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('view', 'download', 'print', 'share') NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `accessed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_document_access` FOREIGN KEY (`document_id`) REFERENCES `exp_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les versions des documents (si un document est modifié)
CREATE TABLE `exp_document_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  CONSTRAINT `fk_document_versions` FOREIGN KEY (`document_id`) REFERENCES `exp_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les partages de documents (si vous souhaitez implémenter cette fonctionnalité)
CREATE TABLE `exp_document_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `shared_by` int(11) NOT NULL,
  `shared_with` int(11) NOT NULL,
  `share_token` varchar(255) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  CONSTRAINT `fk_document_shares` FOREIGN KEY (`document_id`) REFERENCES `exp_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;