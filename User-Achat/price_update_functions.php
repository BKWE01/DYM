<?php
/**
 * Fonctions utilitaires pour la mise à jour des prix des produits
 * Utilisées par process_bulk_purchase.php et update_prices.php
 */

/**
 * Mise à jour du prix d'un produit en fonction de son historique
 * 
 * @param PDO $pdo Instance de connexion PDO
 * @param string $designation Désignation du produit
 * @param float $prix_commande Prix de la commande actuelle
 * @return bool Succès de l'opération
 */
function updateProductPrice($pdo, $designation, $prix_commande)
{
    try {
        // Trouver le produit dans la table products par sa désignation
        $checkProductQuery = "SELECT id, product_name, unit_price 
                             FROM products 
                             WHERE product_name = :designation 
                             LIMIT 1";

        $checkStmt = $pdo->prepare($checkProductQuery);
        $checkStmt->bindParam(':designation', $designation);
        $checkStmt->execute();

        $product = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            // Le produit existe dans la table products
            $productId = $product['id'];
            $currentUnitPrice = $product['unit_price'];

            // Vérifier si le champ prix_moyen existe
            $checkColumnQuery = "SHOW COLUMNS FROM products LIKE 'prix_moyen'";
            $checkColumnStmt = $pdo->query($checkColumnQuery);
            $prixMoyenColExists = $checkColumnStmt->rowCount() > 0;

            // Si le champ prix_moyen n'existe pas, le créer
            if (!$prixMoyenColExists) {
                try {
                    $alterTableQuery = "ALTER TABLE products ADD COLUMN prix_moyen DECIMAL(10,2) DEFAULT NULL AFTER unit_price";
                    $pdo->exec($alterTableQuery);
                    error_log("Colonne prix_moyen ajoutée à la table products");
                    $prixMoyenColExists = true;
                } catch (PDOException $e) {
                    error_log("Erreur lors de l'ajout de la colonne prix_moyen: " . $e->getMessage());
                    // Continuer quand même puisque la colonne pourrait exister dans certaines bases
                }
            }

            // Si le prix unitaire est nul ou égal à zéro, on le définit pour la première fois
            if ($currentUnitPrice === null || floatval($currentUnitPrice) == 0) {
                // Mise à jour du prix unitaire de base
                $updateQuery = "UPDATE products 
                               SET unit_price = :prix_commande 
                               WHERE id = :productId";

                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->bindParam(':prix_commande', $prix_commande);
                $updateStmt->bindParam(':productId', $productId);
                $updateStmt->execute();

                // Aussi mettre à jour le prix moyen pour la première fois
                if ($prixMoyenColExists) {
                    $updateMoyenQuery = "UPDATE products 
                                        SET prix_moyen = :prix_commande 
                                        WHERE id = :productId";

                    $updateMoyenStmt = $pdo->prepare($updateMoyenQuery);
                    $updateMoyenStmt->bindParam(':prix_commande', $prix_commande);
                    $updateMoyenStmt->bindParam(':productId', $productId);
                    $updateMoyenStmt->execute();
                }

                return true;
            } else if ($prixMoyenColExists) {
                // Le prix unitaire existe déjà, calculer le prix moyen

                // D'abord, récupérer toutes les commandes précédentes pour ce produit
                $commandesQuery = "SELECT prix_unitaire 
                                  FROM achats_materiaux 
                                  WHERE designation = :designation 
                                  AND prix_unitaire > 0";

                $commandesStmt = $pdo->prepare($commandesQuery);
                $commandesStmt->bindParam(':designation', $designation);
                $commandesStmt->execute();

                $commandes = $commandesStmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculer le prix moyen, y compris la commande actuelle
                $totalPrix = 0;
                $totalCommandes = count($commandes);

                foreach ($commandes as $commande) {
                    $totalPrix += floatval($commande['prix_unitaire']);
                }

                // Ajouter le prix de la commande actuelle
                $totalPrix += floatval($prix_commande);
                $totalCommandes++;

                // Calculer le nouveau prix moyen
                $nouveauPrixMoyen = $totalPrix / $totalCommandes;

                // Mise à jour du prix moyen du produit
                $updateMoyenQuery = "UPDATE products 
                                    SET prix_moyen = :prix_moyen 
                                    WHERE id = :productId";

                $updateMoyenStmt = $pdo->prepare($updateMoyenQuery);
                $updateMoyenStmt->bindParam(':prix_moyen', $nouveauPrixMoyen);
                $updateMoyenStmt->bindParam(':productId', $productId);
                $updateMoyenStmt->execute();

                return true;
            }
        }
        // Si le produit n'existe pas dans la table products ou si nous n'avons pas pu mettre à jour le prix moyen
        return false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour du prix du produit: " . $e->getMessage());
        // Ne pas bloquer le processus de commande en cas d'erreur
        return false;
    }
}

/**
 * Vérifie si un produit existe déjà dans la table products
 * Si non, tente de le créer à partir des informations disponibles
 * 
 * @param PDO $pdo Instance de connexion PDO
 * @param string $designation Désignation du produit
 * @param string $unit Unité du produit (optionnel)
 * @param int $categoryId ID de la catégorie (optionnel)
 * @return int|null ID du produit créé ou null en cas d'échec
 */
function createProductIfNotExists($pdo, $designation, $unit = 'unité', $categoryId = null)
{
    try {
        // Vérifier si le produit existe déjà
        $checkQuery = "SELECT id FROM products WHERE product_name = :designation LIMIT 1";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->bindParam(':designation', $designation);
        $checkStmt->execute();

        $product = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            // Le produit existe déjà, retourner son ID
            return $product['id'];
        }

        // Le produit n'existe pas, déterminer la catégorie si non fournie
        if ($categoryId === null) {
            // Définir une catégorie par défaut (20 = DIVERS selon la structure de la BDD)
            $categoryId = 20;

            // Essayer de déterminer la catégorie en fonction des mots-clés dans la désignation
            $categoryMappings = [
                'REPP' => ['peinture', 'enduit', 'laque', 'vernis', 'primer', 'antirouille', 'protection'],
                'ELEC' => ['câble', 'cable', 'électrique', 'electrique', 'disjoncteur', 'interrupteur', 'LED', 'ampoule'],
                'REPS' => ['sol', 'dalle', 'revêtement', 'revetement', 'carrelage', 'parquet', 'lino', 'moquette'],
                'ACC' => ['accessoire', 'fixation', 'attache', 'clip', 'support', 'système', 'systeme'],
                'MAFE' => ['acier', 'fer', 'métal', 'metal', 'ferreux', 'tôle', 'tole', 'tube', 'cornière'],
                'DIV' => ['divers', 'autre', 'spécial', 'special', 'ponctuel'],
                'EDPI' => ['casque', 'gant', 'protection', 'lunette', 'masque', 'combinaison', 'gilet', 'sécurité', 'securite'],
                'OACS' => ['soudure', 'électrode', 'electrode', 'masque', 'poste à souder', 'fil', 'chalumeau'],
                'PLOM' => ['plomberie', 'raccord', 'tuyau', 'tube', 'robinet', 'vanne', 'joint', 'coude', 'té'],
                'BOVE' => ['boulon', 'vis', 'écrou', 'ecrou', 'rondelle', 'clou', 'cheville', 'fixation'],
                'OMDP' => ['meule', 'disque', 'ponçage', 'poncage', 'abrasif', 'découpe', 'decoupe', 'polissage'],
                'MATO' => ['outil', 'outillage', 'marteau', 'pince', 'tournevis', 'clé', 'cle', 'scie', 'perceuse']
            ];

            $designationLower = strtolower($designation);

            foreach ($categoryMappings as $code => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($designationLower, $keyword) !== false) {
                        // Trouver l'ID de la catégorie correspondant au code
                        $categoryQuery = "SELECT id FROM categories WHERE code = :code LIMIT 1";
                        $categoryStmt = $pdo->prepare($categoryQuery);
                        $categoryStmt->bindParam(':code', $code);
                        $categoryStmt->execute();

                        $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);
                        if ($category) {
                            $categoryId = $category['id'];
                            break 2; // Sortir des deux boucles
                        }
                    }
                }
            }
        }

        // Générer un code-barres unique pour le produit
        $barcode = generateUniqueBarcode($pdo, $categoryId);

        // Insérer le nouveau produit
        $insertQuery = "INSERT INTO products 
                       (barcode, product_name, quantity, unit, unit_price, category) 
                       VALUES (:barcode, :product_name, 0, :unit, 0, :category)";

        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->bindParam(':barcode', $barcode);
        $insertStmt->bindParam(':product_name', $designation);
        $insertStmt->bindParam(':unit', $unit);
        $insertStmt->bindParam(':category', $categoryId);
        $insertStmt->execute();

        // Retourner l'ID du produit créé
        return $pdo->lastInsertId();

    } catch (PDOException $e) {
        error_log("Erreur lors de la création du produit: " . $e->getMessage());
        return null;
    }
}

/**
 * Génère un code-barres unique pour un nouveau produit
 * en fonction de la catégorie et du dernier numéro utilisé
 * 
 * @param PDO $pdo Instance de connexion PDO
 * @param int $categoryId ID de la catégorie du produit
 * @return string Code-barres généré
 */
function generateUniqueBarcode($pdo, $categoryId)
{
    try {
        // Récupérer le code de la catégorie
        $categoryQuery = "SELECT code FROM categories WHERE id = :categoryId LIMIT 1";
        $categoryStmt = $pdo->prepare($categoryQuery);
        $categoryStmt->bindParam(':categoryId', $categoryId);
        $categoryStmt->execute();

        $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);
        $categoryCode = $category ? $category['code'] : 'DIV'; // Par défaut DIVERS si catégorie non trouvée

        // Trouver le dernier numéro utilisé pour cette catégorie
        $lastNumberQuery = "SELECT MAX(CAST(SUBSTRING(barcode, LENGTH(:prefix) + 2) AS UNSIGNED)) as last_number
                          FROM products 
                          WHERE barcode LIKE CONCAT(:prefix, '-%')";

        $lastNumberStmt = $pdo->prepare($lastNumberQuery);
        $lastNumberStmt->bindParam(':prefix', $categoryCode);
        $lastNumberStmt->execute();

        $result = $lastNumberStmt->fetch(PDO::FETCH_ASSOC);
        $lastNumber = $result['last_number'] ? intval($result['last_number']) : 0;

        // Générer le nouveau numéro et le code-barres
        $newNumber = $lastNumber + 1;
        $barcode = $categoryCode . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);

        return $barcode;

    } catch (PDOException $e) {
        error_log("Erreur lors de la génération du code-barres: " . $e->getMessage());
        // En cas d'erreur, générer un code-barres basé sur le timestamp
        $timestamp = time();
        return 'DIV-' . str_pad(substr($timestamp, -5), 5, '0', STR_PAD_LEFT);
    }
}

/**
 * Enregistre un changement de prix dans la table d'historique
 * Cette fonction est optionnelle et peut être utilisée si une table d'historique existe
 * 
 * @param PDO $pdo Instance de connexion PDO
 * @param int $productId ID du produit
 * @param float $price Prix enregistré
 * @param string $type Type de prix (initial/moyen)
 * @param int $userId ID de l'utilisateur qui a effectué la commande
 * @return bool Succès de l'opération
 */
function logPriceChange($pdo, $productId, $price, $type = 'commande', $userId = null)
{
    try {
        // Vérifier si la table d'historique des prix existe
        $checkTableQuery = "SHOW TABLES LIKE 'prix_historique'";
        $checkTableStmt = $pdo->query($checkTableQuery);

        if ($checkTableStmt->rowCount() == 0) {
            // La table n'existe pas encore, la créer
            $createTableQuery = "CREATE TABLE IF NOT EXISTS prix_historique (
                               id INT(11) NOT NULL AUTO_INCREMENT,
                               product_id INT(11) NOT NULL,
                               prix DECIMAL(10,2) NOT NULL,
                               type_prix VARCHAR(50) NOT NULL,
                               user_id INT(11) DEFAULT NULL,
                               date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                               PRIMARY KEY (id),
                               KEY product_id (product_id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $pdo->exec($createTableQuery);
        }

        // Insérer l'enregistrement d'historique
        $insertQuery = "INSERT INTO prix_historique 
                       (product_id, prix, type_prix, user_id) 
                       VALUES (:product_id, :prix, :type_prix, :user_id)";

        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->bindParam(':product_id', $productId);
        $insertStmt->bindParam(':prix', $price);
        $insertStmt->bindParam(':type_prix', $type);
        $insertStmt->bindParam(':user_id', $userId);
        $insertStmt->execute();

        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'enregistrement du changement de prix: " . $e->getMessage());
        return false;
    }
}
?>