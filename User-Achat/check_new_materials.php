<?php
// Désactiver la mise en cache
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non autorisé', 'newCount' => 0]);
    exit();
}

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Récupérer le nombre total de matériaux à acheter
    $query = "SELECT COUNT(*) as total 
              FROM expression_dym 
              WHERE qt_acheter IS NOT NULL 
              AND qt_acheter > 0 
              AND (valide_achat = 'pas validé' OR valide_achat IS NULL)";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Récupérer les matériaux les plus récents (ajoutés dans les dernières 24 heures)
    $recentQuery = "SELECT COUNT(*) as recent_count 
    FROM expression_dym 
    WHERE qt_acheter IS NOT NULL 
    AND qt_acheter > 0 
    AND (valide_achat = 'pas validé' OR valide_achat IS NULL)
    AND created_at >= NOW() - INTERVAL 24 HOUR";

    $recentStmt = $pdo->prepare($recentQuery);
    $recentStmt->execute();
    $recentResult = $recentStmt->fetch(PDO::FETCH_ASSOC);

    // Vérifier si une variable de session existe pour stocker le dernier nombre connu
    // Cela permet de détecter les nouveaux éléments depuis la dernière vérification
    if (!isset($_SESSION['last_materials_count'])) {
        $_SESSION['last_materials_count'] = $result['total'];
        $newItems = 0;
    } else {
        $newItems = $result['total'] - $_SESSION['last_materials_count'];
        if ($newItems < 0)
            $newItems = 0; // Pour éviter les nombres négatifs
        $_SESSION['last_materials_count'] = $result['total'];
    }

    // Retourner les résultats
    echo json_encode([
        'total' => (int) $result['total'],
        'newCount' => (int) $newItems,
        'recentCount' => (int) $recentResult['recent_count']
    ]);

} catch (PDOException $e) {
    // En cas d'erreur, renvoyer un message d'erreur
    echo json_encode([
        'error' => 'Erreur de base de données: ' . $e->getMessage(),
        'newCount' => 0
    ]);
}
?>