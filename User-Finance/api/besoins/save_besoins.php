<?php
/**
 * Sauvegarde des besoins système avec validation stricte des produits
 * 
 * @package DYM_MANUFACTURE
 * @subpackage api/besoins
 * @version 3.0
 */

// Connexion à la base de données
include_once '../../../database/connection.php';

// Configuration des en-têtes
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Fonction pour logger les erreurs
function logError($message, $data = null) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ];
    
    error_log("SAVE_BESOINS_ERROR: " . json_encode($logData));
}

// Fonction pour valider les données décimales
function validateDecimal($value, $fieldName = 'field') {
    if ($value === null || $value === '') {
        return 0.0;
    }
    
    // Conversion en float avec gestion des virgules françaises
    $cleanValue = str_replace(',', '.', (string)$value);
    $floatValue = floatval($cleanValue);
    
    // Validation des limites raisonnables
    if ($floatValue < 0) {
        throw new Exception("La valeur de '$fieldName' ne peut pas être négative");
    }
    
    if ($floatValue > 999999.99) {
        throw new Exception("La valeur de '$fieldName' est trop élevée (max: 999,999.99)");
    }
    
    return round($floatValue, 2);
}

/**
 * Validation stricte de l'existence du produit dans la base
 * MODIFICATION PRINCIPALE : Refuse l'enregistrement si le produit n'existe pas
 */
function validateProductExists($pdo, $productId, $designationArticle) {
    $result = [
        'exists' => false,
        'product_data' => null,
        'error_message' => null
    ];
    
    try {
        // Rechercher le produit par ID ou par désignation
        if (!empty($productId)) {
            // Recherche par ID
            $stmt = $pdo->prepare("
                SELECT p.id, p.quantity, p.quantity_reserved, p.product_name, p.unit, c.libelle as type 
                FROM products p 
                LEFT JOIN categories c ON p.category = c.id
                WHERE p.id = :product_id
            ");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        } else {
            // Recherche par désignation si pas d'ID
            $stmt = $pdo->prepare("
                SELECT p.id, p.quantity, p.quantity_reserved, p.product_name, p.unit, c.libelle as type 
                FROM products p 
                LEFT JOIN categories c ON p.category = c.id
                WHERE p.product_name = :designation
            ");
            $stmt->bindParam(':designation', $designationArticle);
        }
        
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $result['exists'] = true;
            $result['product_data'] = $product;
        } else {
            $result['exists'] = false;
            $result['error_message'] = "Le produit '{$designationArticle}' n'existe pas dans notre catalogue. Veuillez contacter l'administrateur pour l'ajouter.";
            
            logError("Tentative d'enregistrement d'un produit inexistant", [
                'product_id' => $productId,
                'designation' => $designationArticle
            ]);
        }
        
    } catch (PDOException $e) {
        $result['exists'] = false;
        $result['error_message'] = "Erreur lors de la vérification du produit: " . $e->getMessage();
        
        logError("Erreur PDO lors de la validation du produit", [
            'product_id' => $productId,
            'designation' => $designationArticle,
            'error' => $e->getMessage()
        ]);
    }
    
    return $result;
}

/**
 * Fonction pour calculer les quantités uniquement pour les produits existants
 */
function calculateQuantitiesForExistingProduct($productData, $qtDemande) {
    $result = [
        'qt_stock' => 0.0,
        'qt_acheter' => 0.0,
        'stock_disponible' => 0.0,
        'needs_purchase' => false,
        'product_id' => $productData['id']
    ];
    
    // Calcul du stock disponible
    $quantityInStock = floatval($productData['quantity'] ?? 0);
    $quantityReserved = floatval($productData['quantity_reserved'] ?? 0);
    $availableStock = $quantityInStock - $quantityReserved;
    
    // Affichage du stock disponible (minimum 0 pour l'affichage)
    $displayAvailableStock = max(0, $availableStock);
    $result['stock_disponible'] = $displayAvailableStock;
    
    // Logique de calcul des quantités
    if ($qtDemande <= $availableStock && $availableStock > 0) {
        // Stock suffisant - tout vient du stock
        $result['qt_stock'] = $qtDemande;
        $result['qt_acheter'] = 0.0;
        $result['needs_purchase'] = false;
    } else {
        // Stock insuffisant - calcul de ce qui manque
        if ($availableStock > 0) {
            // Une partie du stock peut être utilisée
            $result['qt_stock'] = $availableStock;
            $result['qt_acheter'] = $qtDemande - $availableStock;
        } else {
            // Aucun stock disponible - tout doit être acheté
            $result['qt_stock'] = 0.0;
            $result['qt_acheter'] = $qtDemande;
        }
        $result['needs_purchase'] = true;
    }
    
    return $result;
}

try {
    // Vérification de la méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée. Utilisez POST.');
    }
    
    // Récupération et validation des données JSON
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('Aucune donnée reçue dans la requête');
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Données JSON invalides: ' . json_last_error_msg());
    }
    
    if (!is_array($data) || empty($data)) {
        throw new Exception('Les données doivent être un tableau non vide');
    }
    
    // Validation des champs requis pour chaque besoin
    $requiredFields = ['idBesoin', 'designation_article', 'qt_demande', 'user_emet'];
    $processedData = [];
    $itemsNeedingPurchase = [];
    $productValidationErrors = []; // Pour collecter les erreurs de validation des produits
    
    // ÉTAPE 1: Validation de tous les produits AVANT l'enregistrement
    foreach ($data as $index => $besoin) {
        // Vérification des champs obligatoires
        foreach ($requiredFields as $field) {
            if (!isset($besoin[$field]) || (is_string($besoin[$field]) && trim($besoin[$field]) === '')) {
                throw new Exception("Le champ '$field' est requis pour l'élément $index");
            }
        }
        
        // Validation et nettoyage des données
        $cleanBesoin = [
            'idBesoin' => trim($besoin['idBesoin']),
            'designation_article' => trim($besoin['designation_article']),
            'caracteristique' => trim($besoin['caracteristique'] ?? ''),
            'type' => trim($besoin['type'] ?? ''),
            'qt_demande' => validateDecimal($besoin['qt_demande'], 'qt_demande'),
            'quantity_reserved' => validateDecimal($besoin['quantity_reserved'] ?? $besoin['qt_demande'], 'quantity_reserved'),
            'user_emet' => intval($besoin['user_emet']),
            'product_id' => !empty($besoin['product_id']) ? intval($besoin['product_id']) : null
        ];
        
        // Validation spécifique
        if ($cleanBesoin['qt_demande'] <= 0) {
            throw new Exception("La quantité demandée doit être positive pour l'élément $index");
        }
        
        if ($cleanBesoin['user_emet'] <= 0) {
            throw new Exception("L'ID utilisateur doit être valide pour l'élément $index");
        }
        
        if (strlen($cleanBesoin['designation_article']) < 3) {
            throw new Exception("La désignation de l'article doit contenir au moins 3 caractères pour l'élément $index");
        }
        
        // VALIDATION CRITIQUE : Vérifier que le produit existe
        $productValidation = validateProductExists(
            $pdo, 
            $cleanBesoin['product_id'], 
            $cleanBesoin['designation_article']
        );
        
        if (!$productValidation['exists']) {
            $productValidationErrors[] = [
                'index' => $index,
                'designation' => $cleanBesoin['designation_article'],
                'error' => $productValidation['error_message']
            ];
        } else {
            // Ajouter les données du produit validé
            $cleanBesoin['product_data'] = $productValidation['product_data'];
        }
        
        $processedData[] = $cleanBesoin;
    }
    
    // Si des produits n'existent pas, arrêter le processus
    if (!empty($productValidationErrors)) {
        $errorMessages = [];
        foreach ($productValidationErrors as $error) {
            $errorMessages[] = "Article #{$error['index']}: {$error['error']}";
        }
        
        throw new Exception(
            "Impossible d'enregistrer les besoins. Produits non trouvés dans le catalogue:\n\n" . 
            implode("\n", $errorMessages) . 
            "\n\nVeuillez contacter l'administrateur pour ajouter ces produits au catalogue avant de pouvoir les commander."
        );
    }
    
    // ÉTAPE 2: Tous les produits existent, procéder à l'enregistrement
    $pdo->beginTransaction();
    
    $insertedIds = [];
    $totalInserted = 0;
    
    // Préparation de la requête d'insertion
    $insertQuery = "
        INSERT INTO besoins (
            idBesoin, 
            designation_article, 
            caracteristique,
            type,
            qt_demande, 
            qt_stock,
            qt_acheter,
            quantity_reserved,
            user_emet,
            product_id,
            stock_status,
            achat_status,
            created_at
        ) VALUES (
            :idBesoin, 
            :designation_article,
            :caracteristique,
            :type,
            :qt_demande, 
            :qt_stock,
            :qt_acheter,
            :quantity_reserved,
            :user_emet,
            :product_id,
            :stock_status,
            :achat_status,
            NOW()
        )
    ";
    
    $stmt = $pdo->prepare($insertQuery);
    
    // Insertion de chaque besoin (tous les produits existent)
    foreach ($processedData as $besoin) {
        // Calcul des quantités pour le produit existant
        $quantities = calculateQuantitiesForExistingProduct(
            $besoin['product_data'], 
            $besoin['qt_demande']
        );
        
        // Déterminer les statuts
        $stockStatus = ($quantities['qt_stock'] > 0) ? null : 'pas validé';
        $achatStatus = ($quantities['qt_acheter'] > 0) ? 'pas validé' : null;
        
        // Utiliser l'ID du produit validé
        $finalProductId = $quantities['product_id'];
        
        // Paramètres pour l'insertion
        $params = [
            ':idBesoin' => $besoin['idBesoin'],
            ':designation_article' => $besoin['designation_article'],
            ':caracteristique' => $besoin['caracteristique'],
            ':type' => $besoin['type'],
            ':qt_demande' => $besoin['qt_demande'],
            ':qt_stock' => $quantities['qt_stock'],
            ':qt_acheter' => $quantities['qt_acheter'],
            ':quantity_reserved' => $besoin['quantity_reserved'],
            ':user_emet' => $besoin['user_emet'],
            ':product_id' => $finalProductId,
            ':stock_status' => $stockStatus,
            ':achat_status' => $achatStatus
        ];
        
        // Exécution de l'insertion
        if (!$stmt->execute($params)) {
            throw new Exception("Erreur lors de l'insertion du besoin: " . implode(', ', $stmt->errorInfo()));
        }
        
        $insertedId = $pdo->lastInsertId();
        $insertedIds[] = $insertedId;
        $totalInserted++;
        
        // Ajouter à la liste des articles nécessitant des achats si besoin
        if ($quantities['needs_purchase']) {
            $itemsNeedingPurchase[] = [
                'designation' => $besoin['designation_article'],
                'requested' => $besoin['qt_demande'],
                'available' => $quantities['stock_disponible'],
                'to_purchase' => $quantities['qt_acheter']
            ];
        }
        
        // Mise à jour de la quantité réservée dans la table products
        $updateProductQuery = "
            UPDATE products 
            SET quantity_reserved = COALESCE(quantity_reserved, 0) + :quantity 
            WHERE id = :product_id
        ";
        
        $updateStmt = $pdo->prepare($updateProductQuery);
        $updateSuccess = $updateStmt->execute([
            ':quantity' => $besoin['quantity_reserved'],
            ':product_id' => $finalProductId
        ]);
        
        if (!$updateSuccess) {
            logError("Erreur lors de la mise à jour de la quantité réservée", [
                'product_id' => $finalProductId,
                'quantity_reserved' => $besoin['quantity_reserved']
            ]);
            // Ne pas faire échouer la transaction pour cela
        }
    }
    
    // Validation finale de la transaction
    if ($totalInserted === 0) {
        throw new Exception("Aucun besoin n'a été inséré");
    }
    
    // Commit de la transaction
    $pdo->commit();
    
    // Log de succès
    error_log("SAVE_BESOINS_SUCCESS: $totalInserted besoins enregistrés avec succès");
    
    // Réponse de succès avec informations sur les achats nécessaires
    echo json_encode([
        'success' => true,
        'message' => "Besoins enregistrés avec succès",
        'needsPurchase' => !empty($itemsNeedingPurchase),
        'itemsNeedingPurchase' => $itemsNeedingPurchase,
        'data' => [
            'total_inserted' => $totalInserted,
            'inserted_ids' => $insertedIds,
            'idBesoin' => $processedData[0]['idBesoin'] ?? null
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Rollback en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log de l'erreur
    logError("Erreur lors de la sauvegarde des besoins", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Réponse d'erreur
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'SAVE_BESOINS_ERROR'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Rollback en cas d'erreur PDO
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log de l'erreur PDO
    logError("Erreur PDO lors de la sauvegarde des besoins", [
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    // Réponse d'erreur
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données lors de l\'enregistrement',
        'error_code' => 'DATABASE_ERROR'
    ], JSON_UNESCAPED_UNICODE);
}
?>