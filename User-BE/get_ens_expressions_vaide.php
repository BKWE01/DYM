<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include_once '../database/connection.php';

try {
    // Requête modifiée pour exclure les projets terminés
    $stmt = $pdo->prepare("
        SELECT e.idExpression, MIN(e.created_at) as created_at, i.code_projet, i.nom_client, i.description_projet
        FROM expression_dym e
        JOIN identification_projet i ON e.idExpression = i.idExpression
        WHERE (e.valide_achat = 'pas validé')
        AND NOT EXISTS (
            SELECT 1 
            FROM project_status ps 
            WHERE ps.idExpression = e.idExpression 
            AND ps.status = 'completed'
        )
        GROUP BY e.idExpression, i.code_projet, i.nom_client, i.description_projet
        ORDER BY MIN(e.created_at) DESC
    ");
    $stmt->execute();

    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($expressions);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>