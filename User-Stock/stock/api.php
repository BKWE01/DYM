<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure le logger
include_once __DIR__ . '/trace/include_logger.php';
$logger = getLogger();

// Fonction pour ajouter plusieurs produits
function addProducts($pdo, $products, $logger)
{
    try {
        $pdo->beginTransaction();
        
        // Préparation des requêtes
        $productStmt = $pdo->prepare("INSERT INTO products 
            (barcode, product_name, quantity, unit, unit_price, category, supplier_id, notes, created_at) 
            VALUES (:barcode, :product_name, :quantity, :unit, :unit_price, :category, :supplier_id, :notes, NOW())");
        
        $movementStmt = $pdo->prepare("INSERT INTO stock_movement 
            (product_id, quantity, movement_type, provenance, fournisseur, destination, notes, created_at) 
            VALUES (:product_id, :quantity, :movement_type, :provenance, :fournisseur, :destination, :notes, NOW())");
        
        $successful = 0;
        $errors = [];

        foreach ($products as $product) {
            try {
                // Valider les données minimales
                if (empty($product['barcode']) || empty($product['name'])) {
                    $errors[] = "Code-barres ou nom manquant pour un produit";
                    continue;
                }
                
                // Insérer le produit
                $productStmt->execute([
                    ':barcode' => $product['barcode'],
                    ':product_name' => $product['name'],
                    ':quantity' => $product['quantity'],
                    ':unit' => $product['unit'],
                    ':unit_price' => (!empty($product['price']) && is_numeric($product['price'])) ? $product['price'] : null,
                    ':category' => $product['category'],
                    ':supplier_id' => !empty($product['supplier_id']) ? $product['supplier_id'] : null,
                    ':notes' => !empty($product['notes']) ? $product['notes'] : null
                ]);

                // Récupérer l'ID du produit inséré
                $productId = $pdo->lastInsertId();
                
                // Enregistrer le mouvement de stock initial si la quantité > 0
                if (!empty($product['quantity']) && $product['quantity'] > 0) {
                    // Récupérer le nom du fournisseur si fourni
                    $fournisseurName = null;
                    if (!empty($product['supplier_id'])) {
                        $suppStmt = $pdo->prepare("SELECT nom FROM fournisseurs WHERE id = ?");
                        $suppStmt->execute([$product['supplier_id']]);
                        $suppResult = $suppStmt->fetch(PDO::FETCH_ASSOC);
                        if ($suppResult) {
                            $fournisseurName = $suppResult['nom'];
                        }
                    }
                    
                    $movementStmt->execute([
                        ':product_id' => $productId,
                        ':quantity' => $product['quantity'],
                        ':movement_type' => 'entry',
                        ':provenance' => 'Initial',
                        ':fournisseur' => $fournisseurName,
                        ':destination' => 'Stock',
                        ':notes' => 'Création initiale du produit'
                    ]);
                }

                // Logger l'ajout du produit
                if ($logger) {
                    $productData = [
                        'id' => $productId,
                        'barcode' => $product['barcode'],
                        'product_name' => $product['name'],
                        'quantity' => $product['quantity'],
                        'unit' => $product['unit'],
                        'unit_price' => $product['price'],
                        'category' => $product['category']
                    ];
                    $logger->logProductAdd($productData);
                }
                
                $successful++;
            } catch (PDOException $e) {
                $errors[] = "Erreur pour le produit {$product['name']}: " . $e->getMessage();
            }
        }

        if ($successful > 0) {
            $pdo->commit();
            return [
                'success' => true, 
                'message' => "Produits ajoutés avec succès: $successful" . 
                            (count($errors) > 0 ? ". Erreurs: " . count($errors) : ""),
                'errors' => $errors
            ];
        } else {
            $pdo->rollBack();
            return ['error' => 'Aucun produit n\'a été ajouté. Erreurs: ' . implode(', ', $errors)];
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['error' => 'Erreur lors de l\'ajout des produits : ' . $e->getMessage()];
    }
}

// Fonction pour vérifier les produits similaires
function checkSimilarProducts($pdo, $productName) {
    try {
        // Recherche de produits avec des noms similaires
        $stmt = $pdo->prepare("SELECT id, barcode, product_name, quantity, unit, unit_price, category 
                            FROM products 
                            WHERE product_name LIKE :like_name
                            LIMIT 5");
        
        $likeName = '%' . $productName . '%';
        $stmt->execute([':like_name' => $likeName]);
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'products' => $products
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Erreur lors de la recherche de produits similaires: ' . $e->getMessage()
        ];
    }
}

// Fonction pour mettre à jour la quantité d'un produit existant
function updateProductQuantity($pdo, $productId, $quantity, $logger) {
    try {
        // Récupérer les informations du produit
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return ['success' => false, 'message' => 'Produit non trouvé'];
        }
        
        $pdo->beginTransaction();
        
        // Mettre à jour la quantité du produit
        $updateStmt = $pdo->prepare("UPDATE products SET quantity = quantity + :quantity, updated_at = NOW() WHERE id = :id");
        $updateStmt->execute([
            ':quantity' => $quantity,
            ':id' => $productId
        ]);
        
        // Ajouter un mouvement de stock
        $movementStmt = $pdo->prepare("INSERT INTO stock_movement 
                                    (product_id, quantity, movement_type, provenance, destination, notes, created_at) 
                                    VALUES (:product_id, :quantity, :movement_type, :provenance, :destination, :notes, NOW())");
        $movementStmt->execute([
            ':product_id' => $productId,
            ':quantity' => $quantity,
            ':movement_type' => 'entry',
            ':provenance' => 'Ajout',
            ':destination' => 'Stock',
            ':notes' => 'Ajout de stock via la page Ajouter un produit'
        ]);
        
        // Logger l'entrée en stock
        if ($logger) {
            $logger->logStockEntry(
                $product,
                $quantity,
                'Ajout',
                null
            );
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Quantité mise à jour avec succès',
            'new_quantity' => $product['quantity'] + $quantity
        ];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()];
    }
}

// Fonction pour rechercher des produits
function searchProducts($pdo, $term) {
    try {
        // Recherche par nom ou code-barres
        $stmt = $pdo->prepare("SELECT id, barcode, product_name, quantity, unit, category 
                            FROM products 
                            WHERE product_name LIKE :like_term 
                            OR barcode LIKE :like_term
                            ORDER BY product_name
                            LIMIT 10");
        
        $likeTerm = '%' . $term . '%';
        $stmt->execute([':like_term' => $likeTerm]);
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'products' => $products
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Erreur lors de la recherche: ' . $e->getMessage()
        ];
    }
}

// Fonction pour obtenir le nom d'une catégorie
function getCategoryName($pdo, $categoryId) {
    try {
        $stmt = $pdo->prepare("SELECT libelle FROM categories WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'category_name' => $result ? $result['libelle'] : 'Catégorie inconnue'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Erreur lors de la récupération de la catégorie: ' . $e->getMessage()
        ];
    }
}

// Fonction pour enregistrer un retour fournisseur
function returnToSupplier($pdo, $data, $logger) {
    try {
        // Vérifier que le produit existe et que la quantité est suffisante
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $data['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return ['success' => false, 'message' => 'Produit non trouvé'];
        }
        
        if ($product['quantity'] < $data['quantity']) {
            return ['success' => false, 'message' => 'Quantité insuffisante en stock'];
        }
        
        // Récupérer le nom du fournisseur
        $supplierName = '';
        $supplierStmt = $pdo->prepare("SELECT nom FROM fournisseurs WHERE id = :id");
        $supplierStmt->execute([':id' => $data['supplier_id']]);
        $supplierResult = $supplierStmt->fetch(PDO::FETCH_ASSOC);
        if ($supplierResult) {
            $supplierName = $supplierResult['nom'];
        }
        
        // Préparer le motif du retour
        $reason = $data['reason'];
        if ($reason === 'autre' && !empty($data['other_reason'])) {
            $reason = $data['other_reason'];
        }
        
        $pdo->beginTransaction();
        
        // Mettre à jour la quantité du produit
        $updateStmt = $pdo->prepare("UPDATE products SET quantity = quantity - :quantity, updated_at = NOW() WHERE id = :id");
        $updateStmt->execute([
            ':quantity' => $data['quantity'],
            ':id' => $data['product_id']
        ]);
        
        // Ajouter un mouvement de stock (sortie)
        $movementStmt = $pdo->prepare("INSERT INTO stock_movement 
                                    (product_id, quantity, movement_type, provenance, fournisseur, destination, notes, created_at) 
                                    VALUES (:product_id, :quantity, :movement_type, :provenance, :fournisseur, :destination, :notes, NOW())");
        $movementStmt->execute([
            ':product_id' => $data['product_id'],
            ':quantity' => $data['quantity'],
            ':movement_type' => 'return',
            ':provenance' => 'Stock',
            ':fournisseur' => $supplierName,
            ':destination' => 'Fournisseur: ' . $supplierName,
            ':notes' => 'Retour fournisseur. Motif: ' . $reason . (!empty($data['comment']) ? '. ' . $data['comment'] : '')
        ]);
        
        $movementId = $pdo->lastInsertId();
        
        // Enregistrer les détails du retour
        $returnStmt = $pdo->prepare("INSERT INTO supplier_returns 
                                  (movement_id, product_id, supplier_id, supplier_name, quantity, reason, comment, status, created_at) 
                                  VALUES (:movement_id, :product_id, :supplier_id, :supplier_name, :quantity, :reason, :comment, :status, NOW())");
        $returnStmt->execute([
            ':movement_id' => $movementId,
            ':product_id' => $data['product_id'],
            ':supplier_id' => $data['supplier_id'],
            ':supplier_name' => $supplierName,
            ':quantity' => $data['quantity'],
            ':reason' => $reason,
            ':comment' => $data['comment'] ?? null,
            ':status' => 'pending'
        ]);
        
        // Logger le retour fournisseur
        if ($logger) {
            $logger->logSupplierReturn(
                $product,
                $data['quantity'],
                $supplierName,
                $reason,
                $data['comment'] ?? null
            );
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Retour fournisseur enregistré avec succès',
            'movement_id' => $movementId
        ];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du retour: ' . $e->getMessage()];
    }
}

// Traitement de la requête AJAX
$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Traitement des requêtes GET
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'checkSimilarProducts':
                if (isset($_GET['name'])) {
                    $response = checkSimilarProducts($pdo, $_GET['name']);
                } else {
                    $response = ['error' => 'Nom du produit non spécifié'];
                }
                break;
                
            case 'searchProducts':
                if (isset($_GET['term'])) {
                    $response = searchProducts($pdo, $_GET['term']);
                } else {
                    $response = ['error' => 'Terme de recherche non spécifié'];
                }
                break;
                
            case 'getCategoryName':
                if (isset($_GET['id'])) {
                    $response = getCategoryName($pdo, $_GET['id']);
                } else {
                    $response = ['error' => 'ID de catégorie non spécifié'];
                }
                break;
                
            default:
                $response = ['error' => 'Action non reconnue'];
        }
    } else {
        $response = ['error' => 'Action non spécifiée'];
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Traitement des requêtes POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'addProducts':
                if (isset($input['products']) && is_array($input['products'])) {
                    $response = addProducts($pdo, $input['products'], $logger);
                } else {
                    $response = ['error' => 'Données de produits invalides'];
                }
                break;
                
            case 'updateProductQuantity':
                if (isset($input['product_id']) && isset($input['quantity'])) {
                    $response = updateProductQuantity($pdo, $input['product_id'], $input['quantity'], $logger);
                } else {
                    $response = ['error' => 'ID de produit ou quantité non spécifiés'];
                }
                break;
                
            case 'returnToSupplier':
                if (isset($input['data']) && is_array($input['data'])) {
                    $response = returnToSupplier($pdo, $input['data'], $logger);
                } else {
                    $response = ['error' => 'Données de retour fournisseur invalides'];
                }
                break;
                
            default:
                $response = ['error' => 'Action non reconnue'];
        }
    } else {
        $response = ['error' => 'Action non spécifiée'];
    }
} else {
    $response = ['error' => 'Méthode non autorisée'];
}

// Retourner la réponse JSON
echo json_encode($response);
?>