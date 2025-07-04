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

-- ========================================
-- AJOUT DE COLONNES DANS ACHATS_MATERIAUX
-- Pour référencer facilement le pro-forma
-- ========================================

-- Ajouter une colonne pour référencer le pro-forma (optionnel, pour faciliter les requêtes)
ALTER TABLE `achats_materiaux` 
ADD COLUMN `proforma_id` int(11) DEFAULT NULL COMMENT 'ID du pro-forma associé' AFTER `mode_paiement`,
ADD KEY `idx_proforma_id` (`proforma_id`);

-- ========================================
-- CRÉATION DU DOSSIER UPLOADS (à faire manuellement)
-- ========================================

-- IMPORTANT : Créer manuellement le dossier suivant sur le serveur :
-- /DYM MANUFACTURE/expressions_besoins/User-Achat/uploads/proformas/
-- Permissions recommandées : 755 ou 775
-- Ajouter un fichier .htaccess pour la sécurité :

/*
Contenu du fichier .htaccess à placer dans /uploads/proformas/ :

# Sécurité - Interdire l'exécution de scripts
Options -ExecCGI
AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi
Options -Indexes

# Autoriser seulement certains types de fichiers
<FilesMatch "\.(pdf|doc|docx|xls|xlsx|jpg|jpeg|png|gif|txt)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Bloquer tout le reste
<FilesMatch "\.">
    Order Deny,Allow
    Deny from all
</FilesMatch>
*/

-- ========================================
-- INDEX SUPPLÉMENTAIRES POUR PERFORMANCES
-- ========================================

-- Index composite pour recherches fréquentes
CREATE INDEX `idx_proforma_fournisseur_date` ON `proformas` (`fournisseur_id`, `upload_date`);
CREATE INDEX `idx_proforma_status_date` ON `proformas` (`status`, `upload_date`);

-- ========================================
-- COMMENTAIRES ET DOCUMENTATION
-- ========================================

-- Cette table permet de :
-- 1. Stocker les fichiers pro-forma envoyés par les fournisseurs
-- 2. Les associer aux commandes dans achats_materiaux
-- 3. Tracer qui a uploadé quoi et quand
-- 4. Gérer le statut de validation des pro-formas
-- 5. Faciliter les recherches par fournisseur, projet, etc.