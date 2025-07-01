-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 30 juin 2025 à 10:34
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

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

-- --------------------------------------------------------

--
-- Structure de la table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `label` varchar(100) NOT NULL COMMENT 'Libellé affiché',
  `description` text DEFAULT NULL COMMENT 'Description détaillée',
  `icon_path` varchar(500) DEFAULT NULL COMMENT 'Chemin vers le fichier d''icône uploadé',
  `sort_order` int(3) DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Mode actif/inactif',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Déchargement des données de la table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `label`, `description`, `icon_path`, `sort_order`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(13, 'Espèces', 'Paiement en liquide', 'uploads/payment_icons/payment_icon_1751227522_02f9a9cc5b374073_icons8-payment-48.png', 0, 1, 1, '2025-06-28 14:57:22', '2025-06-29 20:05:22'),
(14, 'Chèque', 'Paiement par chèque bancaire', 'uploads/payment_icons/payment_icon_1751226535_fcbfacdc446bb355_bill.png', 0, 1, 2, '2025-06-28 14:57:22', '2025-06-29 19:48:55'),
(15, 'Virement bancaire', 'Virement de compte à compte', 'uploads/payment_icons/payment_icon_1751226762_40d2407785119111_gateway.png', 0, 1, 3, '2025-06-28 14:57:22', '2025-06-29 19:52:42'),
(16, 'Carte de crédit', 'Paiement par carte bancaire', 'uploads/payment_icons/payment_icon_1751224980_61e84d983d4e5b89_atm-card.png', 0, 1, 4, '2025-06-28 14:57:22', '2025-06-29 19:23:00'),
(17, 'Mobile Money', 'Paiement par portefeuille mobile', 'uploads/payment_icons/payment_icon_1751226435_54128cbffb784178_smartphone.png', 0, 1, 5, '2025-06-28 14:57:22', '2025-06-29 19:47:15'),
(18, 'Crédit fournisseur', 'Paiement à crédit chez le fournisseur', NULL, 0, 1, 6, '2025-06-28 14:57:22', '2025-06-28 14:57:22'),
(19, 'Traite', 'Paiement par traite commerciale', NULL, 0, 1, 7, '2025-06-28 14:57:22', '2025-06-28 14:57:22'),
(20, 'Autre', 'Autre mode de paiement à préciser', NULL, 0, 1, 8, '2025-06-28 14:57:22', '2025-06-29 20:04:58');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_label_unique` (`label`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_display_order` (`display_order`),
  ADD KEY `idx_label_active` (`label`,`is_active`),
  ADD KEY `idx_active_order` (`is_active`,`display_order`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_updated_at` (`updated_at`),
  ADD KEY `idx_active_display` (`is_active`,`display_order`),
  ADD KEY `idx_icon_path` (`icon_path`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
