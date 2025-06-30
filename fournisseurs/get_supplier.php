<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID du fournisseur non spécifié']);
    exit();
}

$id = $_GET['id'];

// Connexion à la base de données
include_once '../database/connection.php';

// Vérifier si le fichier colors.php existe
$colorsFile = __DIR__ . '/config/colors.php';
if (!file_exists($colorsFile)) {
    echo json_encode(['success' => false, 'message' => 'Fichier de configuration des couleurs manquant']);
    exit();
}

require_once $colorsFile;

try {
    // Récupérer les informations du fournisseur
    $query = "SELECT * FROM fournisseurs WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        echo json_encode(['success' => false, 'message' => 'Fournisseur non trouvé']);
        exit();
    }

    // Récupérer les catégories du fournisseur
    $categoryQuery = "SELECT fc.categorie 
                      FROM fournisseur_categories fc 
                      WHERE fc.fournisseur_id = :id 
                      ORDER BY fc.categorie";
    $categoryStmt = $pdo->prepare($categoryQuery);
    $categoryStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

    // Synchroniser les couleurs des catégories
    foreach ($categories as $categoryName) {
        $checkColorQuery = "SELECT id, couleur FROM categories_fournisseurs WHERE nom = :nom";
        $checkColorStmt = $pdo->prepare($checkColorQuery);
        $checkColorStmt->bindParam(':nom', $categoryName);
        $checkColorStmt->execute();
        $categoryData = $checkColorStmt->fetch(PDO::FETCH_ASSOC);

        if (!$categoryData) {
            // Créer la catégorie manquante
            $color = ColorManager::getSmartFallbackColor($categoryName);
            $insertColorQuery = "INSERT INTO categories_fournisseurs (nom, couleur, description, active, created_by) 
                               VALUES (:nom, :couleur, :description, 1, :created_by)";
            $insertColorStmt = $pdo->prepare($insertColorQuery);
            $insertColorStmt->bindParam(':nom', $categoryName);
            $insertColorStmt->bindParam(':couleur', $color);
            $insertColorStmt->bindParam(':description', "Catégorie créée automatiquement");
            $insertColorStmt->bindParam(':created_by', $_SESSION['user_id']);
            $insertColorStmt->execute();
        } elseif (empty($categoryData['couleur']) || !ColorManager::colorExists($categoryData['couleur'])) {
            // Corriger la couleur invalide
            $color = ColorManager::getSmartFallbackColor($categoryName);
            $updateColorQuery = "UPDATE categories_fournisseurs SET couleur = :couleur WHERE id = :id";
            $updateColorStmt = $pdo->prepare($updateColorQuery);
            $updateColorStmt->bindParam(':couleur', $color);
            $updateColorStmt->bindParam(':id', $categoryData['id']);
            $updateColorStmt->execute();
        }
    }

    echo json_encode([
        'success' => true,
        'supplier' => $supplier,
        'categories' => $categories,
        'debug' => [
            'supplier_id' => $id,
            'categories_count' => count($categories),
            'categories_list' => $categories
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur base de données: ' . $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur générale: ' . $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
}
