<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Connexion à la base de données
include_once '../database/connection.php';

try {

    $stmt = $pdo->prepare("
        SELECT e.idExpression, MIN(e.created_at) as created_at, i.code_projet, i.nom_client
        FROM expression_dym e
        JOIN identification_projet i ON e.idExpression = i.idExpression
        WHERE e.valide_achat = 'validé'
        GROUP BY e.idExpression
        ORDER BY MIN(e.created_at) DESC
    ");
    $stmt->execute();

    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($expressions) {
        echo json_encode($expressions);
    } else {
        echo json_encode([]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
