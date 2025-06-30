<?php
// Connexion à la base de données
include_once '../../database/connection.php'; 

try {

    // Récupération des catégories uniques
    $sql = "SELECT id, libelle FROM categories ORDER BY libelle";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $categories = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = [
            'id' => $row['id'],
            'libelle' => $row['libelle']
        ];
    }

    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => "Erreur de connexion à la base de données: " . $e->getMessage()
    ]);
}
