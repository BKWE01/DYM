<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php';

try {
    // Récupérer le terme de recherche
    $query = isset($_GET['query']) ? $_GET['query'] : '';

    if (empty($query) || strlen($query) < 3) {
        echo json_encode([
            'success' => false,
            'message' => 'Le terme de recherche doit contenir au moins 3 caractères'
        ]);
        exit;
    }

    // Tableau pour stocker les résultats uniques
    $uniqueProjects = [];
    $seenKeys = [];

    // Rechercher dans les projets normaux (avec récupération d'expression_dym liées)
    $stmtProjects = $pdo->prepare("
    SELECT DISTINCT ip.code_projet, ip.nom_client, ip.description_projet, 
           MIN(ip.idExpression) as idExpression,
           'project' as source_type
    FROM identification_projet ip
    JOIN expression_dym ed ON ip.idExpression = ed.idExpression
    WHERE 
        ip.idExpression LIKE :query OR 
        ip.nom_client LIKE :query OR 
        ip.code_projet LIKE :query OR
        ip.description_projet LIKE :query
    GROUP BY ip.code_projet, ip.nom_client, ip.description_projet
    ORDER BY ip.code_projet
    ");

    $param = "%{$query}%";
    $stmtProjects->bindParam(':query', $param);
    $stmtProjects->execute();
    
    // Ajouter les projets uniques au tableau
    while ($project = $stmtProjects->fetch(PDO::FETCH_ASSOC)) {
        $key = $project['code_projet'];
        if (!isset($seenKeys[$key])) {
            $seenKeys[$key] = true;
            $uniqueProjects[] = $project;
        }
    }

    // Rechercher également dans les besoins système
    $stmtBesoins = $pdo->prepare("
    SELECT DISTINCT 
           b.idBesoin as idExpression,
           b.idBesoin as code_projet,
           CASE 
               WHEN d.client IS NOT NULL THEN CONCAT('Demande ', d.client)
               ELSE 'Demande Système'
           END as nom_client,
           b.designation_article as description_projet,
           'besoin' as source_type
    FROM besoins b
    LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
    WHERE 
        b.idBesoin LIKE :query OR 
        b.designation_article LIKE :query OR
        d.client LIKE :query
    GROUP BY b.idBesoin, d.client, b.designation_article
    ORDER BY b.idBesoin
    ");

    $stmtBesoins->bindParam(':query', $param);
    $stmtBesoins->execute();
    
    // Ajouter les besoins uniques au tableau
    while ($besoin = $stmtBesoins->fetch(PDO::FETCH_ASSOC)) {
        $key = 'SYS-' . $besoin['idExpression']; // Préfixer avec 'SYS-' pour éviter les collisions avec les codes projets
        if (!isset($seenKeys[$key])) {
            $seenKeys[$key] = true;
            $uniqueProjects[] = $besoin;
        }
    }

    // Limiter à 10 résultats
    $uniqueProjects = array_slice($uniqueProjects, 0, 10);

    echo json_encode([
        'success' => true,
        'projects' => $uniqueProjects
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