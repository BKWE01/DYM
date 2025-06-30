<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php'; 

try {
    // Requête pour récupérer les projets sans doublons, en forçant une collation commune
    $query = "
        SELECT DISTINCT 
            CONVERT(ip.code_projet USING utf8mb4) AS code_projet, 
            CONVERT(ip.nom_client USING utf8mb4) AS nom_client
        FROM identification_projet ip
        WHERE 
            (
                EXISTS (
                    SELECT 1 
                    FROM stock_movement sm 
                    WHERE CONVERT(sm.nom_projet USING utf8mb4) = CONVERT(ip.code_projet USING utf8mb4)
                )
                OR 
                EXISTS (
                    SELECT 1 
                    FROM achats_materiaux am 
                    WHERE CONVERT(am.expression_id USING utf8mb4) = CONVERT(ip.code_projet USING utf8mb4) 
                    AND am.status = 'reçu'
                )
            )
            AND ip.code_projet != ''
            AND ip.nom_client IS NOT NULL
        ORDER BY code_projet ASC
        LIMIT 100
    ";

    error_log("Requête des projets : " . $query);

    $stmt = $pdo->query($query);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Nombre de projets trouvés : " . count($projects));

    if (empty($projects)) {
        error_log("Aucun projet trouvé. Vérifiez vos tables et jointures.");
        echo json_encode([
            'success' => false,
            'message' => 'Aucun projet trouvé',
            'query' => $query
        ]);
        exit;
    }

    // Journalisation des projets trouvés pour débogage
    foreach ($projects as $project) {
        error_log("Projet trouvé : " . json_encode($project));
    }

    echo json_encode([
        'success' => true,
        'projects' => $projects
    ]);

} catch (PDOException $e) {
    error_log("Erreur PDO : " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données : ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Erreur générale : " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur : ' . $e->getMessage()
    ]);
}
?>