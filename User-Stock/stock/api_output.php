<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php'; 

try {

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['barcode'])) {
        $barcode = $_GET['barcode'];
        
        // Préparer et exécuter la requête
        $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = ?");
        $stmt->execute([$barcode]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
}
?>
