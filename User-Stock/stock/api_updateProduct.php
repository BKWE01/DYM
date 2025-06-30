<?php
// Activer l'affichage des erreurs pour le développement (à ne pas utiliser en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Assurez-vous que la réponse est toujours en JSON
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure le logger
include_once __DIR__ . '/trace/include_logger.php';
$logger = getLogger();

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données envoyées par AJAX
$productId = isset($_POST['id']) ? intval($_POST['id']) : 0;
$productName = isset($_POST['name']) ? trim($_POST['name']) : '';
$productCategory = isset($_POST['category']) ? intval($_POST['category']) : 0;
$productUnit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
$productQuantity = isset($_POST['quantity_prod']) ? trim($_POST['quantity_prod']) : '';

// Validation des données
if ($productId <= 0 || empty($productName) || $productCategory <= 0 || empty($productUnit) || !is_numeric($productQuantity) || floatval($productQuantity) < 0) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

// Convertir la quantité en nombre décimal
$productQuantity = floatval($productQuantity);

try {
    // Vérifier que la catégorie existe
    $categoryCheckStmt = $pdo->prepare("SELECT id FROM categories WHERE id = :categoryId");
    $categoryCheckStmt->execute([':categoryId' => $productCategory]);

    if (!$categoryCheckStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Catégorie non trouvée']);
        exit;
    }

    // Récupérer les données d'origine avant la modification
    $getOriginalStmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $getOriginalStmt->execute([':id' => $productId]);
    $oldProduct = $getOriginalStmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldProduct) {
        echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
        exit;
    }

    // Gérer l'upload d'image si un fichier est fourni
    $imagePath = $oldProduct['product_image']; // Conserver l'ancienne image par défaut
    
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        // Vérifier le type de fichier
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $fileType = $_FILES['product_image']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Utilisez JPG, PNG ou GIF.']);
            exit;
        }
        
        // Vérifier la taille du fichier (5MB max)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($_FILES['product_image']['size'] > $maxSize) {
            echo json_encode(['success' => false, 'message' => 'Le fichier est trop volumineux. Taille maximale : 5 MB.']);
            exit;
        }
        
        // Chemin vers le dossier public centralisé
        $uploadDir = '../../public/uploads/products/';
        $publicPath = '/public/uploads/products/'; // Chemin public pour l'affichage
        
        // Créer le dossier de destination s'il n'existe pas
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                echo json_encode(['success' => false, 'message' => 'Impossible de créer le dossier de destination']);
                exit;
            }
        }
        
        // Générer un nom de fichier unique
        $fileExtension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'product_' . $productId . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $fileName;
        $publicImagePath = $publicPath . $fileName; // Chemin pour la base de données
        
        // Déplacer le fichier uploadé
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadPath)) {
            // Supprimer l'ancienne image si elle existe
            if (!empty($oldProduct['product_image']) && file_exists('../../' . $oldProduct['product_image'])) {
                unlink('../../' . $oldProduct['product_image']);
            }
            
            $imagePath = $publicImagePath;
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload de l\'image']);
            exit;
        }
    }

    // Préparer la requête SQL pour mettre à jour le produit (avec image)
    $query = "UPDATE products SET 
              product_name = :productName, 
              category = :productCategory, 
              unit = :productUnit, 
              quantity = :productQuantity,
              product_image = :productImage,
              updated_at = CURRENT_TIMESTAMP
              WHERE id = :id";
              
    $stmt = $pdo->prepare($query);

    // Lier les paramètres à la requête SQL
    $stmt->bindParam(':productName', $productName);
    $stmt->bindParam(':productCategory', $productCategory, PDO::PARAM_INT);
    $stmt->bindParam(':productUnit', $productUnit);
    $stmt->bindParam(':productQuantity', $productQuantity);
    $stmt->bindParam(':productImage', $imagePath);
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);

    // Exécuter la requête
    $stmt->execute();

    // Vérifier si une ligne a été affectée
    if ($stmt->rowCount() > 0) {
        // Récupérer les nouvelles données après la modification
        $getNewStmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $getNewStmt->execute([':id' => $productId]);
        $newProduct = $getNewStmt->fetch(PDO::FETCH_ASSOC);

        // Journaliser la modification
        if ($logger) {
            $logger->logProductEdit($newProduct, $oldProduct);
        }

        // Réponse JSON pour succès
        echo json_encode([
            'success' => true,
            'message' => 'Produit mis à jour avec succès',
            'data' => [
                'id' => $productId,
                'name' => $productName,
                'category' => $productCategory,
                'unit' => $productUnit,
                'quantity' => $productQuantity,
                'image' => $imagePath
            ]
        ]);
    } else {
        // Si aucune ligne n'a été mise à jour (peut-être parce que les données sont identiques)
        echo json_encode([
            'success' => true,
            'message' => 'Produit mis à jour avec succès (aucune modification détectée)',
            'data' => [
                'id' => $productId,
                'name' => $productName,
                'category' => $productCategory,
                'unit' => $productUnit,
                'quantity' => $productQuantity,
                'image' => $imagePath
            ]
        ]);
    }
} catch (PDOException $e) {
    // En cas d'erreur lors de l'exécution de la requête SQL
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du produit: ' . $e->getMessage()]);
} catch (Exception $e) {
    // En cas d'autres erreurs
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue: ' . $e->getMessage()]);
}
?>