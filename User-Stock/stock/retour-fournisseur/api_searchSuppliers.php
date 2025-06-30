<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Récupérer le terme de recherche
    $query = isset($_GET['query']) ? $_GET['query'] : '';

    if (empty($query) || strlen($query) < 2) {
        echo json_encode([
            'success' => false,
            'message' => 'Le terme de recherche doit contenir au moins 2 caractères'
        ]);
        exit;
    }

    // Rechercher dans les fournisseurs
    $stmt = $pdo->prepare("
        SELECT id, nom, email, telephone
        FROM fournisseurs
        WHERE 
            nom LIKE :query OR 
            email LIKE :query OR
            telephone LIKE :query
        ORDER BY nom ASC
        LIMIT 10
    ");

    $param = "%{$query}%";
    $stmt->bindParam(':query', $param);
    $stmt->execute();

    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'suppliers' => $suppliers
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>