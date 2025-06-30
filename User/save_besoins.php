<?php
/**
 * Sauvegarde des besoins système avec vérification correcte des stocks
 * 
 * @package DYM_MANUFACTURE
 * @subpackage api/besoins
 * @version 2.1
 */

// Connexion à la base de données
include_once '../database/connection.php';

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

// Fonction améliorée pour calculer les quantités (inspirée de save_expressions.php)
function calculateQuantitiesImproved($pdo, $productId, $designationArticle, $qtDemande) {
    $result = [
        'qt_stock' => 0.0,
        'qt_acheter' => 0.0,
        'stock_disponible' => 0.0,
        'needs_purchase' => false
    ];
    
    try {
        // Rechercher le produit par ID ou par désignation
        if (!empty($productId)) {
            // Recherche par ID
            $stmt = $pdo->prepare("
                SELECT p.quantity, p.quantity_reserved, p.product_name 
                FROM products p 
                WHERE p.id = :product_id
            ");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        } else {
            // Recherche par désignation si pas d'ID
            $stmt = $pdo->prepare("
                SELECT p.quantity, p.quantity_reserved, p.product_name, p.id 
                FROM products p 
                WHERE p.product_name = :designation
            ");
            $stmt->bindParam(':designation', $designationArticle);
        }
        
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // Calcul du stock disponible
            $quantityInStock = floatval($product['quantity'] ?? 0);
            $quantityReserved = floatval($product['quantity_reserved'] ?? 0);
            $availableStock = $quantityInStock - $quantityReserved;
            
            // Affichage du stock disponible (minimum 0 pour l'affichage)
            $displayAvailableStock = max(0, $availableStock);
            $result['stock_disponible'] = $displayAvailableStock;
            
            // Logique de calcul des quantités (comme dans save_expressions.php)
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
            
            // Mettre à jour l'ID du produit si trouvé par désignation
            if (empty($productId) && isset($product['id'])) {
                $result['product_id'] = $product['id'];
            }
            
        } else {
            // Produit non trouvé - tout doit être acheté
            $result['qt_acheter'] = $qtDemande;
            $result['needs_purchase'] = true;
            
            logError("Produit non trouvé dans le stock", [
                'product_id' => $productId,
                'designation' => $designationArticle,
                'qt_demande' => $qtDemande
            ]);
        }
        
    } catch (PDOException $e) {
        logError("Erreur lors du calcul des quantités", [
            'product_id' => $productId,
            'designation' => $designationArticle,
            'qt_demande' => $qtDemande,
            'error' => $e->getMessage()
        ]);
        
        // En cas d'erreur, tout doit être acheté
        $result['qt_acheter'] = $qtDemande;
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
    $itemsNeedingPurchase = []; // Array pour suivre les articles nécessitant des achats
    
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
        
        $processedData[] = $cleanBesoin;
    }
    
    // Début de la transaction
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
    
    // Insertion de chaque besoin avec vérification améliorée
    foreach ($processedData as $besoin) {
        // Calcul automatique des quantités avec la logique améliorée
        $quantities = calculateQuantitiesImproved(
            $pdo, 
            $besoin['product_id'], 
            $besoin['designation_article'], 
            $besoin['qt_demande']
        );
        
        // Déterminer les statuts
        $stockStatus = ($quantities['qt_stock'] > 0) ? 'disponible' : 'indisponible';
        $achatStatus = ($quantities['qt_acheter'] > 0) ? 'nécessaire' : 'non nécessaire';
        
        // Utiliser l'ID du produit trouvé si disponible
        $finalProductId = $quantities['product_id'] ?? $besoin['product_id'];
        
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
        
        // Mise à jour de la quantité réservée dans la table products (comme dans save_expressions.php)
        if (!empty($finalProductId)) {
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