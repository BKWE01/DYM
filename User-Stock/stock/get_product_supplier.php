<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php'; 

// Récupérer le paramètre de la requête
$productName = isset($_GET['product_name']) ? $_GET['product_name'] : '';

if (empty($productName)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nom de produit non spécifié'
    ]);
    exit;
}

try {
    // Rechercher dans les commandes récentes pour trouver le fournisseur associé à ce produit
    $query = "SELECT am.fournisseur
              FROM achats_materiaux am
              WHERE LOWER(am.designation) LIKE :search
              AND am.fournisseur IS NOT NULL 
              AND am.fournisseur != ''
              ORDER BY am.date_achat DESC
              LIMIT 1";

    // Préparer le terme de recherche
    $searchTerm = '%' . strtolower($productName) . '%';

    $stmt = $pdo->prepare($query);
    $stmt->execute([':search' => $searchTerm]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && !empty($result['fournisseur'])) {
        echo json_encode([
            'success' => true,
            'fournisseur' => $result['fournisseur']
        ]);
    } else {
        // Si aucun fournisseur trouvé dans les commandes, chercher dans les expressions de besoin validées
        $exprQuery = "SELECT ed.fournisseur
                     FROM expression_dym ed
                     WHERE LOWER(ed.designation) LIKE :search
                     AND ed.fournisseur IS NOT NULL 
                     AND ed.fournisseur != ''
                     AND (ed.valide_achat = 'validé' OR ed.valide_achat = 'reçu')
                     ORDER BY ed.created_at DESC
                     LIMIT 1";

        $exprStmt = $pdo->prepare($exprQuery);
        $exprStmt->execute([':search' => $searchTerm]);
        $exprResult = $exprStmt->fetch(PDO::FETCH_ASSOC);

        if ($exprResult && !empty($exprResult['fournisseur'])) {
            echo json_encode([
                'success' => true,
                'fournisseur' => $exprResult['fournisseur']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Aucun fournisseur trouvé pour ce produit'
            ]);
        }
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la recherche du fournisseur: ' . $e->getMessage()
    ]);
}