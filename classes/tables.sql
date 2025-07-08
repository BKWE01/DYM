-- Fichier combiné de toutes les définitions de tables
-- DYM MANUFACTURE - Système de gestion intégré
-- Date: 19 mai 2025 (Mise à jour complète)
-- Version: 3.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `dym_global`
--

-- ========================================
-- TABLES PRINCIPALES DE GESTION DE PROJET
-- ========================================

--
-- Table `identification_projet`
-- Stockage des informations des projets
--
CREATE TABLE IF NOT EXISTS `identification_projet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idExpression` varchar(255) NOT NULL,
  `code_projet` varchar(255) NOT NULL,
  `nom_client` varchar(225) DEFAULT NULL,
  `description_projet` text NOT NULL,
  `sitgeo` text NOT NULL,
  `chefprojet` varchar(255) NOT NULL,
  `zone_id` int(11) DEFAULT NULL COMMENT 'Zone à laquelle appartient le projet',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `zone_id` (`zone_id`),
  KEY `idx_expression` (`idExpression`),
  KEY `idx_identification_projet_code` (`idExpression`,`code_projet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `expression_dym`
-- Expressions de besoins pour les projets
--
CREATE TABLE IF NOT EXISTS `expression_dym` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idExpression` varchar(255) NOT NULL,
  `user_emet` varchar(225) DEFAULT NULL,
  `user_stock` varchar(225) DEFAULT NULL,
  `user_achat` varchar(225) DEFAULT NULL,
  `user_finance` varchar(225) DEFAULT NULL,
  `designation` varchar(255) NOT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `quantity` float DEFAULT NULL,
  `quantity_stock` float DEFAULT NULL COMMENT 'Quantité entrée en stock',
  `quantity_reserved` float DEFAULT NULL,
  `qt_stock` varchar(225) DEFAULT NULL,
  `qt_acheter` varchar(225) DEFAULT NULL,
  `qt_restante` float DEFAULT NULL,
  `initial_qt_acheter` varchar(225) DEFAULT NULL,
  `prix_unitaire` varchar(225) DEFAULT NULL,
  `montant` varchar(225) DEFAULT NULL,
  `fournisseur` varchar(225) DEFAULT NULL,
  `modePaiement` varchar(225) DEFAULT NULL,
  `entity` varchar(255) DEFAULT NULL,
  `type` varchar(225) DEFAULT NULL,
  `valide_stock` varchar(225) DEFAULT NULL,
  `valide_achat` enum('pas validé','validé','en_cours','reçu','annulé','commandé','valide_en_cours') DEFAULT 'pas validé',
  `valide_finance` enum('validé','pas validé') NOT NULL DEFAULT 'pas validé',
  `pre_achat` enum('invalide','valide') NOT NULL DEFAULT 'invalide',
  `quantity_dymmain` float DEFAULT NULL,
  `quantity_dymbing` float DEFAULT NULL,
  `quantity_dymaes` float DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  ADD PRIMARY KEY (`id`),
  KEY `idx_expression_dym_designation_date` (`designation`,`created_at`),
  KEY `idx_expression_product_match` (`designation`,`created_at`),
  KEY `idx_expression_dym_reservations` (`idExpression`,`quantity_reserved`,`designation`);
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `besoins`
-- Besoins système (administratifs et autres)
--
CREATE TABLE IF NOT EXISTS `besoins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idBesoin` varchar(50) NOT NULL,
  `user_emet` varchar(225) DEFAULT NULL,
  `user_stock` varchar(225) DEFAULT NULL,
  `user_achat` varchar(225) DEFAULT NULL,
  `designation_article` varchar(255) NOT NULL,
  `caracteristique` text NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `fournisseur` varchar(255) DEFAULT NULL,
  `qt_demande` float NOT NULL,
  `qt_stock` varchar(225) DEFAULT NULL,
  `quantity_dispatch_stock` float DEFAULT NULL,
  `qt_acheter` varchar(225) DEFAULT NULL,
  `qt_restante` float DEFAULT NULL,
  `initial_qt_acheter` float DEFAULT NULL,
  `quantity_reserved` float DEFAULT 0,
  `stock_status` varchar(225) DEFAULT NULL,
  `achat_status` varchar(225) DEFAULT NULL,
  `valide_finance` enum('pas validé','validé') DEFAULT 'pas validé',
  `product_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_besoin` (`idBesoin`),
  KEY `idx_besoins_reservations` (`product_id`,`quantity_reserved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `demandeur`
-- Informations sur les demandeurs de besoins
--
CREATE TABLE IF NOT EXISTS `demandeur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idBesoin` varchar(225) NOT NULL,
  `nom_prenoms` varchar(255) NOT NULL,
  `service_demandeur` varchar(255) NOT NULL,
  `motif_demande` text NOT NULL,
  `client` varchar(255) DEFAULT NULL COMMENT 'Client associé pour les besoins système',
  `date_demande` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_besoin` (`idBesoin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `project_status`
-- Statut des projets
--
CREATE TABLE IF NOT EXISTS `project_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idExpression` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idExpression` (`idExpression`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES DE GESTION DES ACHATS
-- ========================================

--
-- Table `achats_materiaux`
-- Commandes de matériaux
--
CREATE TABLE IF NOT EXISTS `achats_materiaux` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int (11) DEFAULT NULL,
  `expression_id` varchar(255) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `quantity` float NOT NULL,
  `original_quantity` float DEFAULT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `prix_unitaire` decimal(10, 2) DEFAULT NULL,
  `fournisseur` varchar(255) DEFAULT NULL,
  `mode_paiement_id` int(11) NOT NULL COMMENT 'ID du mode de paiement utilisé (référence payment_methods.id)' ,
  `proforma_id` int(11) DEFAULT NULL COMMENT 'ID du pro-forma associé',
  `date_achat` timestamp NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'en_attente',
  `is_partial` tinyint (1) DEFAULT 0,
  `notes` varchar(225) DEFAULT NULL,
  `user_achat` int (11) NOT NULL,
  `date_reception` timestamp NULL DEFAULT NULL,
  `canceled_at` timestamp NULL DEFAULT NULL COMMENT 'Date d''annulation',
  `canceled_by` int (11) DEFAULT NULL COMMENT 'ID utilisateur ayant annulé',
  `cancel_reason` varchar(255) DEFAULT NULL COMMENT 'Raison de l''annulation',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `expression_id` (`expression_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_designation_expression` (`designation`, `expression_id`),
  KEY `idx_expression_id` (`expression_id`),
  KEY `idx_achats_materiaux_designation_date` (`designation`, `created_at`, `status`),
  KEY `idx_mode_paiement` (`mode_paiement_id`),
  KEY `idx_proforma_id` (`proforma_id`),
  KEY `idx_achats_product_match` (`designation`, `created_at`, `status`);
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ========================================
-- TABLE DES PRO-FORMAS
-- Stockage des fichiers pro-forma associés aux commandes
-- ========================================

CREATE TABLE IF NOT EXISTS `proformas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `achat_materiau_id` int(11) NOT NULL COMMENT 'ID de la commande dans achats_materiaux',
  `bon_commande_id` int(11) DEFAULT NULL COMMENT 'ID du bon de commande (si table séparée)',
  `fournisseur_id` int(11) NOT NULL COMMENT 'ID du fournisseur',
  `id_product` int(11) DEFAULT NULL COMMENT 'ID du produit concerné',
  `projet_client` varchar(255) DEFAULT NULL COMMENT 'Projet ou client associé',
  `file_path` varchar(500) NOT NULL COMMENT 'Chemin vers le fichier pro-forma',
  `original_filename` varchar(255) NOT NULL COMMENT 'Nom original du fichier',
  `file_type` varchar(100) NOT NULL COMMENT 'Type MIME du fichier',
  `file_size` int(11) NOT NULL COMMENT 'Taille du fichier en octets',
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date d''upload',
  `upload_user_id` int(11) DEFAULT NULL COMMENT 'ID utilisateur qui a uploadé',
  `status` enum('en_attente','validé','rejeté') DEFAULT 'en_attente' COMMENT 'Statut du pro-forma',
  `notes` text DEFAULT NULL COMMENT 'Notes sur le pro-forma',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_achat_materiau` (`achat_materiau_id`),
  KEY `idx_fournisseur` (`fournisseur_id`),
  KEY `idx_upload_date` (`upload_date`),
  KEY `idx_status` (`status`),
  KEY `idx_upload_user` (`upload_user_id`),
  KEY `idx_product` (`id_product`),
  -- Contrainte pour lier aux achats de matériaux
  CONSTRAINT `fk_proforma_achat` FOREIGN KEY (`achat_materiau_id`) 
    REFERENCES `achats_materiaux` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 
COMMENT='Stockage des pro-formas associés aux commandes de matériaux';


-- 
-- Créer la table des modes de paiement
-- 
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL COMMENT 'Libellé affiché',
  `description` text DEFAULT NULL COMMENT 'Description détaillée',
  `icon_path` varchar(500) DEFAULT NULL COMMENT 'Chemin vers le fichier d''icône uploadé',
  `sort_order` int(3) DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Mode actif/inactif',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  -- Clé primaire
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_label_unique` (`label`),
  KEY `idx_active` (`is_active`),
  KEY `idx_display_order` (`display_order`),
  KEY `idx_label_active` (`label`,`is_active`),
  KEY `idx_active_order` (`is_active`,`display_order`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_updated_at` (`updated_at`),
  KEY `idx_active_display` (`is_active`,`display_order`),
  KEY `idx_icon_path` (`icon_path`),
  
  -- Contraintes de validation
  CONSTRAINT `chk_label_length` CHECK (
    LENGTH(TRIM(`label`)) >= 2 AND 
    LENGTH(TRIM(`label`)) <= 100
  ),
  CONSTRAINT `chk_display_order` CHECK (`display_order` >= 0),
  CONSTRAINT `chk_is_active` CHECK (`is_active` IN (0, 1))
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Modes de paiement disponibles';

-- Recréer les index optimisés sans le champ code
ALTER TABLE payment_methods 
ADD INDEX idx_active_display (is_active, display_order),
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

--
-- Table `purchase_orders`
-- Bons de commande générés
--
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL COMMENT 'Numéro du bon de commande',
  `expression_id` varchar(255) NOT NULL COMMENT 'ID de l''expression de besoin principale',
  `related_expressions` text DEFAULT NULL COMMENT 'IDs des expressions liées (JSON)',
  `file_path` varchar(255) NOT NULL COMMENT 'Chemin du fichier PDF',
  `fournisseur` varchar(255) NOT NULL COMMENT 'Fournisseur concerné',
  `mode_paiement_id` int(11) DEFAULT NULL COMMENT 'ID du mode de paiement utilisé (référence payment_methods.id)',
  `montant_total` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Montant total du bon de commande',
  `user_id` int(11) NOT NULL COMMENT 'Utilisateur ayant généré le bon',
  `is_multi_project` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indique si c''est un achat multi-projets',
  `download_reference` varchar(50) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date de génération',
  `signature_finance` timestamp NULL DEFAULT NULL COMMENT 'Date de signature par la finance',
  `user_finance_id` int(11) DEFAULT NULL COMMENT 'ID de l''utilisateur finance ayant signé',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rejected_at` timestamp NULL DEFAULT NULL COMMENT 'Date de rejet par Finance',
  `rejected_by_user_id` int(11) DEFAULT NULL COMMENT 'ID utilisateur Finance ayant rejeté',
  `rejection_reason` text DEFAULT NULL COMMENT 'Motif du rejet',
  `status` enum('pending','signed','rejected') DEFAULT 'pending' COMMENT 'Statut du bon de commande',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_download_reference` (`download_reference`),
  KEY `expression_id` (`expression_id`),
  KEY `fournisseur` (`fournisseur`),
  KEY `idx_mode_paiement` (`mode_paiement_id`),
  KEY `user_id` (`user_id`),
  KEY `generated_at` (`generated_at`),
  KEY `idx_order_number` (`order_number`),
  KEY `fk_purchase_orders_finance_user` (`user_finance_id`),
  KEY `idx_rejected_at` (`rejected_at`),
  KEY `idx_status` (`status`),
  KEY `fk_purchase_orders_rejected_by` (`rejected_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `user_notifications_read`
--

CREATE TABLE `user_notifications_read` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'ID de l''utilisateur',
  `notification_type` varchar(50) NOT NULL COMMENT 'Type de notification (urgent, recent, partial, other)',
  `material_id` int(11) NOT NULL COMMENT 'ID du matériau (expression_dym.id ou besoins.id)',
  `expression_id` varchar(255) NOT NULL COMMENT 'ID de l''expression (idExpression ou idBesoin)',
  `source_table` varchar(50) NOT NULL DEFAULT 'expression_dym' COMMENT 'Table source (expression_dym ou besoins)',
  `designation` varchar(255) NOT NULL COMMENT 'Désignation du matériau pour identification',
  `read_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date de lecture',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_material_unique` (`user_id`,`material_id`,`source_table`,`notification_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_material_id` (`material_id`),
  KEY `idx_expression_id` (`expression_id`),
  KEY `idx_source_table` (`source_table`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_read_at` (`read_at`),
  KEY `idx_user_type_read` (`user_id`,`notification_type`,`read_at`),
  KEY `idx_cleanup_old_notifications` (`read_at`,`notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Table pour tracker les notifications lues par les utilisateurs du service achat';

--
-- Table `canceled_orders_log`
-- Log des commandes annulées
--
CREATE TABLE IF NOT EXISTS `canceled_orders_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL DEFAULT 0 COMMENT 'ID de la commande annulée (0 si pas de commande)',
  `project_id` varchar(255) NOT NULL COMMENT 'ID du projet associé',
  `designation` varchar(255) NOT NULL COMMENT 'Nom du produit commandé',
  `canceled_by` int(11) NOT NULL COMMENT 'ID utilisateur ayant annulé la commande',
  `cancel_reason` varchar(255) NOT NULL COMMENT 'Raison de l''annulation',
  `original_status` varchar(50) NOT NULL COMMENT 'Statut de la commande avant annulation',
  `is_partial` tinyint(1) DEFAULT 0 COMMENT 'Indique si c''était une commande partielle',
  `canceled_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date d''annulation',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `project_id` (`project_id`),
  KEY `canceled_by` (`canceled_by`),
  KEY `canceled_at` (`canceled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `related_expressions`
-- Relations entre expressions de besoins
--
CREATE TABLE IF NOT EXISTS `related_expressions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_id` int(11) NOT NULL,
  `partial_id` int(11) NOT NULL,
  `achat_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_original` (`original_id`),
  KEY `idx_partial` (`partial_id`),
  KEY `idx_achat` (`achat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `product_substitutions`
-- Substitutions de produits
--
CREATE TABLE IF NOT EXISTS `product_substitutions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_product` varchar(255) NOT NULL,
  `original_unit` varchar(50) DEFAULT NULL,
  `substitute_product` varchar(255) NOT NULL,
  `substitute_unit` varchar(50) DEFAULT NULL,
  `expression_id` varchar(255) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_transferred` float NOT NULL,
  `source_table` varchar(50) DEFAULT 'expression_dym',
  `reason` varchar(255) NOT NULL,
  `other_reason` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_material_id` (`material_id`),
  KEY `idx_expression_id` (`expression_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `quote_requests`
-- Demandes de devis aux fournisseurs
--
CREATE TABLE IF NOT EXISTS `quote_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `materials` text NOT NULL COMMENT 'JSON des matériaux demandés',
  `filepath` varchar(500) NOT NULL,
  `deadline` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('sent','received','processed','cancelled') DEFAULT 'sent',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supplier_id` (`supplier_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_deadline` (`deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES DE GESTION DES STOCKS
-- ========================================

--
-- Table `products`
-- Catalogue des produits
--
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barcode` varchar(255) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_image` varchar(255) DEFAULT NULL COMMENT 'Chemin vers l''image du produit',
  `quantity` float DEFAULT NULL,
  `quantity_reserved` float DEFAULT NULL,
  `unit` varchar(255) DEFAULT 'unité',
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `prix_moyen` decimal(10,2) DEFAULT NULL,
  `category` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `quantity_dymmain` float NOT NULL COMMENT 'Quantité pour zone DYM ANGRE',
  `quantity_reserved_dymmain` float NOT NULL COMMENT '	Quantité réservée pour zone DYM ANGRE',
  `quantity_dymbing` float NOT NULL COMMENT '	Quantité pour zone DYM BINGERVILLE',
  `quantity_reserved_dymbing` float NOT NULL COMMENT '	Quantité réservée pour zone DYM BINGERVILLE',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_products_category_name` (`category`,`product_name`),
  KEY `idx_products_search` (`product_name`,`barcode`,`category`),
  KEY `idx_quantity_dymmain` (`quantity_dymmain`) USING BTREE,
  KEY `idx_quantity_reserved_dymmain` (`quantity_reserved_dymmain`) USING BTREE,
  KEY `idx_products_name_search` (`product_name`,`quantity`,`quantity_reserved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `categories`
-- Catégories de produits
--
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `libelle` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `stock_movement`
-- Mouvements de stock
--
CREATE TABLE IF NOT EXISTS `stock_movement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL COMMENT 'Zone concernée par le mouvement',
  `quantity` float NOT NULL,
  `movement_type` varchar(10) NOT NULL COMMENT 'Type de mouvement: entry, output, transfer, return',
  `provenance` varchar(225) NOT NULL,
  `fournisseur` varchar(255) DEFAULT NULL,
  `nom_projet` varchar(225) DEFAULT NULL,
  `destination` varchar(225) NOT NULL COMMENT 'Destination du mouvement (peut être un fournisseur pour les retours)',
  `destination_zone_id` int(11) DEFAULT NULL COMMENT 'Zone de destination (pour les transferts)',
  `demandeur` varchar(225) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL COMMENT 'Référence à la facture',
  `notes` text DEFAULT NULL COMMENT 'Notes additionnelles (motif du retour, etc.)',
  `date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `zone_id` (`zone_id`),
  KEY `destination_zone_id` (`destination_zone_id`),
  KEY `idx_stock_movement_product_date` (`product_id`,`created_at`,`movement_type`),
  KEY `idx_stock_movement_analytics` (`product_id`,`movement_type`,`created_at`);

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `stock_returns`
-- Retours de stock
--
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

--
-- Table `stock_returns_history`
-- Historique des retours de stock
--
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

--
-- Table `supplier_returns`
-- Retours vers fournisseurs
--
CREATE TABLE IF NOT EXISTS `supplier_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `movement_id` int(11) NOT NULL COMMENT 'Référence au mouvement de stock',
  `product_id` int(11) NOT NULL COMMENT 'Référence au produit',
  `supplier_id` int(11) DEFAULT NULL COMMENT 'ID du fournisseur (si disponible)',
  `supplier_name` varchar(255) NOT NULL COMMENT 'Nom du fournisseur',
  `quantity` float NOT NULL COMMENT 'Quantité retournée',
  `reason` varchar(50) NOT NULL COMMENT 'Motif du retour',
  `comment` text DEFAULT NULL COMMENT 'Commentaire additionnel',
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Statut du retour',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'Date de confirmation du retour',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date de création',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Date de mise à jour',
  `project_id` int(11) DEFAULT NULL COMMENT 'ID du projet associé (si disponible)',
  `project_code` varchar(255) DEFAULT NULL COMMENT 'Code du projet associé',
  `project_name` varchar(255) DEFAULT NULL COMMENT 'Nom du projet associé',
  PRIMARY KEY (`id`),
  KEY `movement_id` (`movement_id`),
  KEY `product_id` (`product_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `project_code` (`project_code`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `prix_historique`
-- Historique des prix
--
CREATE TABLE IF NOT EXISTS `prix_historique` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `type_prix` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `type_prix` (`type_prix`),
  KEY `date_creation` (`date_creation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `dispatch_details`
-- Détails des dispatches
--
CREATE TABLE IF NOT EXISTS `dispatch_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `movement_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `allocated` float NOT NULL,
  `remaining` float DEFAULT NULL,
  `fournisseur` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `project` varchar(100) DEFAULT NULL,
  `client` varchar(100) DEFAULT NULL,
  `dispatch_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `movement_id` (`movement_id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `invoices`
-- Factures
--
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(100) DEFAULT NULL COMMENT 'Numéro de facture (optionnel)',
  `file_path` varchar(255) NOT NULL COMMENT 'Chemin vers le fichier de facture',
  `original_filename` varchar(255) NOT NULL COMMENT 'Nom original du fichier',
  `file_type` varchar(50) NOT NULL COMMENT 'Type MIME du fichier',
  `file_size` int(11) NOT NULL COMMENT 'Taille du fichier en octets',
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date d''upload',
  `upload_user_id` int(11) DEFAULT NULL COMMENT 'ID de l''utilisateur qui a uploadé la facture',
  `entry_date` date DEFAULT NULL COMMENT 'Date d''entrée des produits',
  `supplier` varchar(255) DEFAULT NULL COMMENT 'Fournisseur lié à cette facture',
  `notes` text DEFAULT NULL COMMENT 'Notes supplémentaires',
  PRIMARY KEY (`id`),
  KEY `upload_date` (`upload_date`),
  KEY `supplier` (`supplier`),
  KEY `upload_user_id` (`upload_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES DE GESTION DES FOURNISSEURS
-- ========================================

--
-- Table `fournisseurs`
-- Liste des fournisseurs
--
CREATE TABLE IF NOT EXISTS `fournisseurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nom` (`nom`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `categories_fournisseurs`
-- Catégories de fournisseurs
--
CREATE TABLE IF NOT EXISTS `categories_fournisseurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `couleur` varchar(50) DEFAULT 'badge-blue',
  `icon_path` varchar(255) DEFAULT NULL COMMENT 'Chemin vers le fichier icône uploadé',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `fournisseur_categories`
-- Relation fournisseurs-catégories
--
CREATE TABLE IF NOT EXISTS `fournisseur_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fournisseur_id` int(11) NOT NULL,
  `categorie` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fournisseur_id` (`fournisseur_id`),
  KEY `idx_categorie` (`categorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLES DE GESTION DES TRANSFERTS
-- ========================================

--
-- Table `transferts`
-- Transferts entre projets
--
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

--
-- Table `transfert_history`
-- Historique des transferts
--
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

-- ========================================
-- TABLES DE GESTION DES UTILISATEURS
-- ========================================

--
-- Table `users_exp`
-- Utilisateurs du système
--
CREATE TABLE IF NOT EXISTS `users_exp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(225) NOT NULL,
  `email` varchar(225) NOT NULL,
  `password` varchar(225) NOT NULL,
  `confirm_password` varchar(225) DEFAULT NULL,
  `user_type` varchar(225) NOT NULL,
  `profile_image` varchar(225) DEFAULT NULL,
  `signature` varchar(225) DEFAULT NULL,
  `role` enum('super_admin','admin','user') DEFAULT NULL,
  `zone_id` int(11) DEFAULT NULL COMMENT 'Zone principale de l''utilisateur',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`),
  KEY `idx_zone_id` (`zone_id`),
  KEY `idx_role` (`role`),
  KEY `idx_user_type` (`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `zones`
-- Zones géographiques (référencée par les utilisateurs et projets)
--
CREATE TABLE IF NOT EXISTS `zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL COMMENT 'Code unique de la zone',
  `nom` varchar(255) NOT NULL COMMENT 'Nom complet de la zone/entité',
  `adresse` varchar(255) DEFAULT NULL COMMENT 'Adresse physique',
  `telephone` varchar(50) DEFAULT NULL COMMENT 'Téléphone de contact',
  `email` varchar(100) DEFAULT NULL COMMENT 'Email de contact',
  `responsable_id` int(11) DEFAULT NULL COMMENT 'ID du responsable de la zone',
  `description` text DEFAULT NULL COMMENT 'Description de la zone',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'Statut de la zone',
  `created_by` int(11) DEFAULT NULL COMMENT 'Utilisateur ayant créé la zone',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `responsable_id` (`responsable_id`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `zone_column_mapping`
--

CREATE TABLE `zone_column_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zone_id` int(11) NOT NULL,
  `zone_code` varchar(50) NOT NULL,
  `quantity_column` varchar(150) NOT NULL,
  `quantity_reserved_column` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `zone_id` (`zone_id`),
  KEY `zone_code` (`zone_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `zone_access`
--

CREATE TABLE `zone_access` (
  `id` int NOT NULL,
  `user_id` int NOT NULL COMMENT 'Utilisateur',
  `zone_id` int NOT NULL COMMENT 'Zone accessible',
  `can_view` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Peut consulter',
  `can_edit` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Peut modifier',
  `can_manage` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Accès administratif',
  `granted_by` int DEFAULT NULL COMMENT 'Accordé par',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_zone` (`user_id`,`zone_id`),
  KEY `zone_id` (`zone_id`),
  KEY `granted_by` (`granted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `zone_audit_log`
--
CREATE TABLE
  `zone_audit_log` (
    `id` int NOT NULL,
    `zone_id` int NOT NULL,
    `user_id` int DEFAULT NULL,
    `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `old_value` text COLLATE utf8mb4_general_ci,
    `new_value` text COLLATE utf8mb4_general_ci,
    `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `user_agent` text COLLATE utf8mb4_general_ci,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `zone_id` (`zone_id`),
    KEY `user_id` (`user_id`),
    KEY `action` (`action`),
    KEY `created_at` (`created_at`)
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- ========================================
-- TABLES DE LOGGING ET HISTORIQUE
-- ========================================

--
-- Table `system_logs`
-- Logs système
--
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(225) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `entity_id` varchar(50) DEFAULT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `type` (`type`),
  KEY `created_at` (`created_at`),
  KEY `idx_entity_id` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table `upgrade_logs`
-- Logs des mises à jour système
--
CREATE TABLE IF NOT EXISTS `upgrade_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `status` enum('success','error','warning') NOT NULL DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLE D'HISTORIQUE DES MODIFICATIONS
-- ========================================

--
-- Table `order_modifications_history`
-- Historique des modifications apportées aux commandes
--
CREATE TABLE IF NOT EXISTS `order_modifications_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT 'ID de la commande modifiée',
  `expression_id` varchar(255) NOT NULL COMMENT 'ID de l''expression liée',
  `old_quantity` decimal(10,2) DEFAULT NULL COMMENT 'Ancienne quantité',
  `new_quantity` decimal(10,2) DEFAULT NULL COMMENT 'Nouvelle quantité',
  `old_price` decimal(10,2) DEFAULT NULL COMMENT 'Ancien prix unitaire',
  `new_price` decimal(10,2) DEFAULT NULL COMMENT 'Nouveau prix unitaire',
  `old_supplier` varchar(255) DEFAULT NULL COMMENT 'Ancien fournisseur',
  `new_supplier` varchar(255) DEFAULT NULL COMMENT 'Nouveau fournisseur',
  `old_payment_method` varchar(50) DEFAULT NULL COMMENT 'Ancien ID du mode de paiement',
  `new_payment_method` varchar(50) DEFAULT NULL COMMENT 'Nouveau ID du mode de paiement',
  `modification_reason` text DEFAULT NULL COMMENT 'Raison de la modification',
  `modified_by` int(11) NOT NULL COMMENT 'ID de l''utilisateur ayant effectué la modification',
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date de modification',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_expression_id` (`expression_id`),
  KEY `idx_modified_by` (`modified_by`),
  KEY `idx_modified_at` (`modified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historique des modifications des commandes';

-- Contraintes de clés étrangères pour order_modifications_history
ALTER TABLE `order_modifications_history`
  ADD CONSTRAINT `fk_order_modifications_order` FOREIGN KEY (`order_id`) REFERENCES `achats_materiaux` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_modifications_user` FOREIGN KEY (`modified_by`) REFERENCES `users_exp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Index pour améliorer les performances
CREATE INDEX IF NOT EXISTS `idx_order_modifications_search` ON `order_modifications_history` (`order_id`, `modified_at`);
-- ========================================
-- DÉCLENCHEURS (TRIGGERS)
-- ========================================

--
-- Déclencheur pour éviter les doublons dans purchase_orders
--
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `prevent_duplicate_orders` 
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
END$$
DELIMITER ;

--
-- Déclencheur pour mettre à jour automatiquement les quantités dans expression_dym
--
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `update_expression_quantities` 
AFTER UPDATE ON `achats_materiaux` 
FOR EACH ROW 
BEGIN
    IF NEW.status = 'reçu' AND OLD.status != 'reçu' THEN
        UPDATE expression_dym 
        SET valide_achat = 'reçu',
            quantity_stock = COALESCE(quantity_stock, 0) + NEW.quantity
        WHERE idExpression = NEW.expression_id 
        AND designation = NEW.designation;
    END IF;
END$$
DELIMITER ;

-- ========================================
-- CONTRAINTES DE CLÉS ÉTRANGÈRES
-- ========================================
--
-- Contraintes pour la table `user_notifications_read`
--
ALTER TABLE `user_notifications_read`
  ADD CONSTRAINT `fk_user_notifications_read_user` FOREIGN KEY (`user_id`) REFERENCES `users_exp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Contraintes pour identification_projet
ALTER TABLE `identification_projet`
  ADD CONSTRAINT `fk_projet_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour besoins
ALTER TABLE `besoins`
  ADD CONSTRAINT `fk_besoins_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour project_status
ALTER TABLE `project_status`
  ADD CONSTRAINT `fk_project_status_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour achats_materiaux
ALTER TABLE `achats_materiaux`
  ADD CONSTRAINT `fk_achats_parent` FOREIGN KEY (`parent_id`) REFERENCES `achats_materiaux` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_achats_user` FOREIGN KEY (`user_achat`) REFERENCES `users_exp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Contraintes pour purchase_orders
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_purchase_orders_rejected_by` FOREIGN KEY (`rejected_by_user_id`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_purchase_orders_payment_method` FOREIGN KEY (`mode_paiement_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_purchase_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users_exp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Contraintes pour canceled_orders_log
ALTER TABLE `canceled_orders_log`
  ADD CONSTRAINT `fk_canceled_orders_user` FOREIGN KEY (`canceled_by`) REFERENCES `users_exp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Contraintes pour product_substitutions
ALTER TABLE `product_substitutions`
  ADD CONSTRAINT `fk_product_substitutions_user` FOREIGN KEY (`user_id`) REFERENCES `users_exp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_product_substitutions_material` FOREIGN KEY (`material_id`) REFERENCES `achats_materiaux` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Contraintes pour quote_requests
ALTER TABLE `quote_requests`
  ADD CONSTRAINT `fk_quote_requests_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `fournisseurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_quote_requests_user` FOREIGN KEY (`created_by`) REFERENCES `users_exp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Contraintes pour products
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Contraintes pour stock_movement
ALTER TABLE `stock_movement`
  ADD CONSTRAINT `fk_stock_movement_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_movement_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour stock_returns
ALTER TABLE `stock_returns`
  ADD CONSTRAINT `fk_stock_returns_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_returns_created_by` FOREIGN KEY (`created_by`) REFERENCES `users_exp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_returns_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_returns_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_returns_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_returns_canceled_by` FOREIGN KEY (`canceled_by`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour stock_returns_history
ALTER TABLE `stock_returns_history`
  ADD CONSTRAINT `fk_stock_returns_history_return` FOREIGN KEY (`return_id`) REFERENCES `stock_returns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_returns_history_user` FOREIGN KEY (`user_id`) REFERENCES `users_exp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Contraintes pour supplier_returns
ALTER TABLE `supplier_returns`
  ADD CONSTRAINT `fk_supplier_returns_movement` FOREIGN KEY (`movement_id`) REFERENCES `stock_movement` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supplier_returns_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supplier_returns_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `fournisseurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supplier_returns_project` FOREIGN KEY (`project_id`) REFERENCES `identification_projet` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour prix_historique
ALTER TABLE `prix_historique`
  ADD CONSTRAINT `fk_prix_historique_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_prix_historique_user` FOREIGN KEY (`user_id`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour dispatch_details
ALTER TABLE `dispatch_details`
  ADD CONSTRAINT `fk_dispatch_details_movement` FOREIGN KEY (`movement_id`) REFERENCES `stock_movement` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dispatch_details_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Contraintes pour invoices
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_upload_user` FOREIGN KEY (`upload_user_id`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour fournisseurs
ALTER TABLE `fournisseurs`
  ADD CONSTRAINT `fk_fournisseurs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour categories_fournisseurs
ALTER TABLE `categories_fournisseurs`
  ADD CONSTRAINT `fk_categories_fournisseurs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour fournisseur_categories
ALTER TABLE `fournisseur_categories`
  ADD CONSTRAINT `fk_fournisseur_categories_fournisseur` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Contraintes pour transferts
ALTER TABLE `transferts`
  ADD CONSTRAINT `fk_transferts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transferts_source_project` FOREIGN KEY (`source_project_id`) REFERENCES `identification_projet` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transferts_destination_project` FOREIGN KEY (`destination_project_id`) REFERENCES `identification_projet` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transferts_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users_exp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transferts_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transferts_canceled_by` FOREIGN KEY (`canceled_by`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour transfert_history
ALTER TABLE `transfert_history`
  ADD CONSTRAINT `fk_transfert_history_transfert` FOREIGN KEY (`transfert_id`) REFERENCES `transferts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfert_history_user` FOREIGN KEY (`user_id`) REFERENCES `users_exp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Contraintes pour users_exp
ALTER TABLE `users_exp`
  ADD CONSTRAINT `fk_users_exp_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Contraintes pour system_logs
ALTER TABLE `system_logs`
  ADD CONSTRAINT `fk_system_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users_exp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ========================================
-- PROCÉDURES STOCKÉES
-- ========================================

--
-- Procédure pour initialiser les quantités réservées
--
DELIMITER $$
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
END$$
DELIMITER ;

--
-- Procédure pour calculer les statistiques des achats
--
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS `get_achat_statistics`()
BEGIN
  SELECT 
    COUNT(CASE WHEN valide_achat = 'pas validé' OR valide_achat IS NULL THEN 1 END) as pending,
    COUNT(CASE WHEN valide_achat = 'validé' OR valide_achat = 'commandé' THEN 1 END) as ordered,
    COUNT(CASE WHEN valide_achat = 'en_cours' THEN 1 END) as partial,
    COUNT(CASE WHEN valide_achat = 'reçu' THEN 1 END) as received,
    COUNT(CASE WHEN valide_achat = 'annulé' THEN 1 END) as canceled,
    SUM(CASE WHEN prix_unitaire IS NOT NULL AND qt_acheter IS NOT NULL 
             THEN CAST(prix_unitaire AS DECIMAL(10,2)) * CAST(qt_acheter AS DECIMAL(10,2)) 
             ELSE 0 END) as total_value
  FROM expression_dym;
END$$
DELIMITER ;

--
-- Procédure pour nettoyer les logs anciens
--
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS `cleanup_old_logs`(IN days_to_keep INT)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;
  
  -- Nettoyer les logs système
  DELETE FROM system_logs 
  WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
  
  -- Nettoyer les logs de mise à jour
  DELETE FROM upgrade_logs 
  WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
  
  -- Nettoyer l'historique des retours
  DELETE FROM stock_returns_history 
  WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
  
  -- Nettoyer l'historique des transferts
  DELETE FROM transfert_history 
  WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
  
  COMMIT;
END$$
DELIMITER ;

-- ========================================
-- VUES POUR LES RAPPORTS
-- ========================================

--
-- Vue pour les statistiques de stock par projet
--
CREATE OR REPLACE VIEW `v_stock_by_project` AS
SELECT 
  ip.code_projet,
  ip.nom_client,
  p.product_name,
  p.quantity as stock_total,
  p.quantity_reserved as stock_reserved,
  (p.quantity - p.quantity_reserved) as stock_available,
  c.libelle as category,
  p.unit,
  p.prix_moyen
FROM products p
JOIN categories c ON p.category = c.id
CROSS JOIN identification_projet ip
WHERE p.quantity > 0
ORDER BY ip.code_projet, p.product_name;

--
-- Vue pour le suivi des commandes
--
CREATE OR REPLACE VIEW `v_commandes_suivi` AS
SELECT 
  am.id,
  am.expression_id,
  ip.code_projet,
  ip.nom_client,
  am.designation,
  am.quantity,
  am.prix_unitaire,
  am.fournisseur,
  am.status,
  am.date_achat,
  am.date_reception,
  u.name as user_achat_name,
  ed.valide_achat as status_expression
FROM achats_materiaux am
LEFT JOIN identification_projet ip ON am.expression_id = ip.idExpression
LEFT JOIN users_exp u ON am.user_achat = u.id
LEFT JOIN expression_dym ed ON am.expression_id = ed.idExpression AND am.designation = ed.designation
ORDER BY am.date_achat DESC;

--
-- Vue pour les mouvements de stock détaillés
--
CREATE OR REPLACE VIEW `v_mouvements_stock_details` AS
SELECT 
  sm.id,
  sm.movement_type,
  p.product_name,
  p.barcode,
  sm.quantity,
  sm.fournisseur,
  sm.nom_projet,
  sm.destination,
  sm.demandeur,
  sm.notes,
  sm.date,
  c.libelle as category
FROM stock_movement sm
JOIN products p ON sm.product_id = p.id
JOIN categories c ON p.category = c.id
ORDER BY sm.date DESC;

-- ========================================
-- INDEX OPTIMISATIONS SUPPLÉMENTAIRES
-- ========================================

-- Index composés pour les requêtes fréquentes
CREATE INDEX IF NOT EXISTS `idx_expression_dym_composite` ON `expression_dym` (`idExpression`, `valide_achat`, `designation`);
CREATE INDEX IF NOT EXISTS `idx_achats_materiaux_composite` ON `achats_materiaux` (`expression_id`, `status`, `fournisseur`);
CREATE INDEX IF NOT EXISTS `idx_stock_movement_composite` ON `stock_movement` (`product_id`, `movement_type`, `date`);
CREATE INDEX IF NOT EXISTS `idx_products_composite` ON `products` (`category`, `product_name`);

-- Index pour les recherches textuelles
CREATE FULLTEXT INDEX IF NOT EXISTS `idx_fulltext_products` ON `products` (`product_name`);
CREATE FULLTEXT INDEX IF NOT EXISTS `idx_fulltext_fournisseurs` ON `fournisseurs` (`nom`);

-- ========================================
-- INSERTION DES DONNÉES DE BASE
-- ========================================

-- Insertion des zones par défaut
INSERT IGNORE INTO `zones` (`nom`, `code`, `description`) VALUES
('Abidjan', 'ABJ', 'Zone d''Abidjan et environs'),
('Bouaké', 'BKE', 'Zone de Bouaké et environs'),
('San Pedro', 'SPD', 'Zone de San Pedro et environs'),
('Korhogo', 'KGO', 'Zone de Korhogo et environs'),
('National', 'NAT', 'Zone nationale - projets multi-zones');

-- Insertion des catégories de base
INSERT IGNORE INTO `categories` (`libelle`, `code`) VALUES
('Matériaux de construction', 'MAT_CONST'),
('Outillage', 'OUTILLAGE'),
('Électricité', 'ELECTRIC'),
('Plomberie', 'PLOMBERIE'),
('Fournitures de bureau', 'BUREAU'),
('Véhicules et transport', 'TRANSPORT'),
('Informatique', 'INFORMATIQUE'),
('Sécurité', 'SECURITE'),
('Consommables', 'CONSOMMABLES'),
('Divers', 'DIVERS');

-- Insertion des catégories de fournisseurs
INSERT IGNORE INTO `categories_fournisseurs` (`nom`, `description`, `couleur`, `icone`) VALUES
('Matériaux BTP', 'Fournisseurs de matériaux de construction', 'badge-blue', 'build'),
('Équipements', 'Fournisseurs d''équipements et outillage', 'badge-green', 'build_circle'),
('Services', 'Prestataires de services', 'badge-purple', 'engineering'),
('Fournitures', 'Fournisseurs de fournitures diverses', 'badge-orange', 'inventory'),
('Électronique', 'Fournisseurs de matériel électronique', 'badge-red', 'electrical_services'),
('Transport', 'Services de transport et logistique', 'badge-yellow', 'local_shipping');

-- ========================================
-- FINALISATION
-- ========================================

-- Activation des contraintes de clé étrangère
SET FOREIGN_KEY_CHECKS = 1;

-- Commit de toutes les transactions
COMMIT;

-- Message de fin
SELECT 'Installation de la base de données DYM terminée avec succès!' AS status;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;