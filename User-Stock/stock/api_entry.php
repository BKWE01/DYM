<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php'; 

try {

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['barcode'])) {
        $barcode = $_GET['barcode'];

        // Log du code-barres reçu
        error_log("Code-barres reçu côté backend: " . $barcode);

        // Validation stricte du code-barres
        if (!preg_match('/^[A-Z]+-[0-9]{5}$/', $barcode)) {
            echo json_encode(['success' => false, 'message' => 'Code-barres invalide']);
            exit;
        }

        // Requête pour récupérer le produit
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
} catch (PDOException $e) {
    error_log("Erreur de base de données : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
}


?>


