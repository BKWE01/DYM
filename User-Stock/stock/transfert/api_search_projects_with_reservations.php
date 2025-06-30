<?php
header('Content-Type: application/json');
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette ressource.'
    ]);
    exit;
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    $productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

    if ($productId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de produit invalide.'
        ]);
        exit;
    }

    // Récupérer les informations du produit
    $productStmt = $pdo->prepare("SELECT product_name, barcode FROM products WHERE id = :product_id");
    $productStmt->execute(['product_id' => $productId]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé.'
        ]);
        exit;
    }

    // Rechercher les projets qui ont des réservations pour ce produit
    // Cette requête cherche dans la table expression_dym pour les réservations
    $sql = "
    SELECT 
        ip.id,
        ip.idExpression,
        ip.code_projet,
        ip.nom_client,
        CASE 
            -- Si la quantité réservée est définie dans expression_dym, l'utiliser
            WHEN ed.quantity_reserved IS NOT NULL AND ed.quantity_reserved > 0 THEN ed.quantity_reserved
            -- Sinon vérifier si qt_acheter existe
            WHEN ed.qt_acheter IS NOT NULL AND ed.qt_acheter > 0 THEN ed.qt_acheter
            -- Sinon utiliser qt_restante si elle existe
            WHEN ed.qt_restante IS NOT NULL AND ed.qt_restante > 0 THEN ed.qt_restante
            -- Sinon, utiliser 0 comme valeur par défaut
            ELSE 0
        END as reserved_quantity
    FROM 
        expression_dym ed
    JOIN 
        identification_projet ip ON ed.idExpression = ip.idExpression
    JOIN 
        products p ON p.id = :product_id
    WHERE 
        LOWER(ed.designation) LIKE LOWER(CONCAT('%', p.product_name, '%'))
        AND (
            ed.quantity_reserved > 0 
            OR ed.qt_acheter > 0 
            OR ed.qt_restante > 0
        )
    ORDER BY 
        ip.created_at DESC
";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['product_id' => $productId]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si aucun projet trouvé avec la méthode ci-dessus, essayer avec une autre approche
    // (pour les systèmes où les réservations pourraient être stockées différemment)
    if (empty($projects)) {
        $alternativeSql = "
            SELECT 
                ip.id,
                ip.idExpression,
                ip.code_projet,
                ip.nom_client,
                am.quantity as reserved_quantity
            FROM 
                achats_materiaux am
            JOIN 
                identification_projet ip ON am.expression_id = ip.idExpression
            WHERE 
                am.designation = (SELECT product_name FROM products WHERE id = :product_id)
                AND am.status IN ('reç')
            ORDER BY 
                ip.created_at DESC
        ";

        $altStmt = $pdo->prepare($alternativeSql);
        $altStmt->execute(['product_id' => $productId]);
        $projects = $altStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'product' => $product,
        'projects' => $projects
    ]);

} catch (PDOException $e) {
    error_log('Erreur dans api_search_projects_with_reservations.php: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la recherche des projets: ' . $e->getMessage()
    ]);
}