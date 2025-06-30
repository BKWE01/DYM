<?php
// Désactiver le rapport d'erreurs
error_reporting(0);

// Inclure la connexion à la base de données
include_once '../database/connection.php';

/**
 * Fonction utilitaire pour formater les dates
 */
function formatDateForDisplay($date) {
    if (empty($date) || $date === 'N/A') {
        return 'N/A';
    }
    
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format('d/m/Y');
    } catch (Exception $e) {
        return 'N/A';
    }
}

// Fonction pour suggérer un fournisseur en fonction du produit/désignation
function suggestFournisseur($designation)
{
    global $pdo;

    // Étape 1: Chercher le dernier fournisseur utilisé pour ce produit spécifique
    try {
        $query = "SELECT fournisseur 
                  FROM achats_materiaux 
                  WHERE designation = :designation 
                  ORDER BY date_achat DESC 
                  LIMIT 1";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':designation', $designation);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return [
                'fournisseur' => $stmt->fetch(PDO::FETCH_ASSOC)['fournisseur'],
                'source' => 'historique',
                'confiance' => 'haute'
            ];
        }
    } catch (PDOException $e) {
        // Continuer avec d'autres méthodes si celle-ci échoue
    }

    // Étape 2: Déterminer la catégorie probable du produit à partir de mots-clés
    $categorie = determinerCategorie($designation);

    if ($categorie) {
        // Chercher le fournisseur le plus fréquent pour cette catégorie
        try {
            $query = "SELECT f.nom, f.categorie, COUNT(*) as commandes 
                      FROM fournisseurs f 
                      JOIN achats_materiaux a ON f.nom = a.fournisseur 
                      WHERE f.categorie = :categorie 
                      GROUP BY f.nom, f.categorie 
                      ORDER BY commandes DESC 
                      LIMIT 1";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':categorie', $categorie);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return [
                    'fournisseur' => $result['nom'],
                    'categorie' => $result['categorie'],
                    'source' => 'catégorie',
                    'confiance' => 'moyenne'
                ];
            }
        } catch (PDOException $e) {
            // Continuer avec d'autres méthodes si celle-ci échoue
        }
    }

    // Étape 3: Si aucune correspondance directe, rechercher des produits similaires
    try {
        // Extraire les mots-clés importants de la désignation
        $keywords = extractKeywords($designation);

        if (!empty($keywords)) {
            $conditions = [];
            $params = [];

            foreach ($keywords as $index => $keyword) {
                $key = ":keyword" . $index;
                $conditions[] = "designation LIKE " . $key;
                $params[$key] = "%$keyword%";
            }

            $conditionStr = implode(" OR ", $conditions);

            $query = "SELECT fournisseur, COUNT(*) as count 
                      FROM achats_materiaux 
                      WHERE $conditionStr 
                      GROUP BY fournisseur 
                      ORDER BY count DESC 
                      LIMIT 1";

            $stmt = $pdo->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return [
                    'fournisseur' => $stmt->fetch(PDO::FETCH_ASSOC)['fournisseur'],
                    'source' => 'produits similaires',
                    'confiance' => 'basse'
                ];
            }
        }
    } catch (PDOException $e) {
        // Échec silencieux
    }

    // Étape 4: En dernier recours, suggérer le fournisseur le plus utilisé globalement
    try {
        $query = "SELECT fournisseur, COUNT(*) as count 
                  FROM achats_materiaux 
                  WHERE fournisseur IS NOT NULL AND fournisseur != ''
                  GROUP BY fournisseur 
                  ORDER BY count DESC 
                  LIMIT 1";

        $stmt = $pdo->query($query);

        if ($stmt->rowCount() > 0) {
            return [
                'fournisseur' => $stmt->fetch(PDO::FETCH_ASSOC)['fournisseur'],
                'source' => 'général',
                'confiance' => 'très basse'
            ];
        }
    } catch (PDOException $e) {
        // Si tout échoue, ne rien suggérer
    }

    return null;
}

// Fonction pour déterminer la catégorie d'un produit basée sur sa désignation
function determinerCategorie($designation)
{
    global $pdo;

    // Convertir la désignation en minuscules pour une comparaison insensible à la casse
    $designation = mb_strtolower($designation, 'UTF-8');

    // Tableau de correspondance entre mots-clés et catégories
    $categorieKeywords = [
        'REPP' => ['peinture', 'enduit', 'laque', 'vernis', 'primer', 'antirouille', 'protection'],
        'ELEC' => ['câble', 'cable', 'électrique', 'electrique', 'disjoncteur', 'interrupteur', 'LED', 'ampoule'],
        'REPS' => ['sol', 'dalle', 'revêtement', 'revetement', 'carrelage', 'parquet', 'lino', 'moquette'],
        'ACC' => ['accessoire', 'fixation', 'attache', 'clip', 'support', 'système', 'systeme'],
        'MAFE' => ['acier', 'fer', 'métal', 'metal', 'ferreux', 'tôle', 'tole', 'tube', 'cornière'],
        'DIV' => ['divers', 'autre', 'spécial', 'special', 'ponctuel'],
        'EDPI' => ['casque', 'gant', 'protection', 'lunette', 'masque', 'combinaison', 'gilet', 'sécurité', 'securite'],
        'OACS' => ['soudure', 'électrode', 'electrode', 'masque', 'poste à souder', 'fil', 'chalumeau'],
        'PLOM' => ['plomberie', 'raccord', 'tuyau', 'tube', 'robinet', 'vanne', 'joint', 'coude', 'té', 'te'],
        'BOVE' => ['boulon', 'vis', 'écrou', 'ecrou', 'rondelle', 'clou', 'cheville', 'fixation'],
        'OMDP' => ['meule', 'disque', 'ponçage', 'poncage', 'abrasif', 'découpe', 'decoupe', 'polissage'],
        'MATO' => ['outil', 'outillage', 'marteau', 'pince', 'tournevis', 'clé', 'cle', 'scie', 'perceuse']
    ];

    // Vérifier la correspondance avec les mots-clés
    foreach ($categorieKeywords as $code => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_strpos($designation, $keyword) !== false) {
                // Récupérer le libellé complet de la catégorie depuis la base de données
                try {
                    $query = "SELECT libelle FROM categories WHERE code = :code LIMIT 1";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindParam(':code', $code);
                    $stmt->execute();

                    if ($stmt->rowCount() > 0) {
                        return $stmt->fetch(PDO::FETCH_ASSOC)['libelle'];
                    } else {
                        // Si le code n'est pas trouvé dans la base de données, retourner une valeur par défaut
                        return getFallbackCategoryName($code);
                    }
                } catch (PDOException $e) {
                    // En cas d'erreur, retourner une valeur par défaut
                    return getFallbackCategoryName($code);
                }
            }
        }
    }

    // Si aucune correspondance, essayer de faire correspondre avec une catégorie existante
    try {
        $query = "SELECT c.libelle 
                  FROM categories c 
                  JOIN products p ON p.category = c.id 
                  WHERE LOWER(p.product_name) LIKE :designation 
                  LIMIT 1";

        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':designation', '%' . $designation . '%');
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC)['libelle'];
        }
    } catch (PDOException $e) {
        // Échec silencieux
    }

    return "Divers"; // Catégorie par défaut
}

// Fonction pour obtenir le nom de la catégorie si la requête échoue
function getFallbackCategoryName($code)
{
    $categories = [
        'REPP' => 'REVETEMENT DE PEINTURE ET DE PROTECTION',
        'ELEC' => 'ELECTRICITE',
        'REPS' => 'REVETEMENT DE PROTECTION DE SOL',
        'ACC' => 'ACCESOIRE',
        'MAFE' => 'MATERIELS FERREUX',
        'DIV' => 'DIVERS',
        'EDPI' => 'EQUIPEMENT DE PROTECTION INDIVIDUEL',
        'OACS' => 'OUTILS ET ACCESSOIRES DE SOUDURE',
        'PLOM' => 'MATERIELS DE PLOMBERIE',
        'BOVE' => 'BOULONS, VIS ET ECROUS',
        'OMDP' => 'OUTILS DE MEULAGE, DE DECOUPE ET PRAISSAGE',
        'MATO' => 'MATERIELS ET OUTILLAGES'
    ];

    return isset($categories[$code]) ? $categories[$code] : 'Divers';
}

// Fonction pour extraire les mots-clés importants d'une désignation
function extractKeywords($designation)
{
    // Convertir en minuscules et supprimer les caractères spéciaux
    $designation = mb_strtolower($designation, 'UTF-8');
    $designation = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $designation);

    // Diviser en mots
    $words = preg_split('/\s+/', $designation, -1, PREG_SPLIT_NO_EMPTY);

    // Filtrer les mots courts et les articles/prépositions
    $stopWords = ['de', 'du', 'des', 'le', 'la', 'les', 'un', 'une', 'et', 'a', 'à', 'au', 'aux', 'en', 'par', 'pour'];
    $keywords = [];

    foreach ($words as $word) {
        if (mb_strlen($word) > 3 && !in_array($word, $stopWords)) {
            $keywords[] = $word;
        }
    }

    return $keywords;
}

// Si ce script est appelé directement, retourner une suggestion de fournisseur
if (isset($_GET['designation'])) {
    $designation = $_GET['designation'];
    $suggestion = suggestFournisseur($designation);

    header('Content-Type: application/json');
    echo json_encode($suggestion);
    exit;
}