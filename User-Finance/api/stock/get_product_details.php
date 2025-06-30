<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentification requise'
    ]);
    exit();
}

// Vérifier si l'ID du produit est spécifié et valide
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || intval($_GET['id']) <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID de produit manquant ou invalide'
    ]);
    exit();
}

$productId = intval($_GET['id']);

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Récupérer les détails du produit
    $sql = "SELECT 
                p.id,
                p.barcode, 
                p.product_name,
                p.quantity,
                COALESCE(p.quantity_reserved, 0) as quantity_reserved,
                COALESCE(p.unit, 'unité') as unit,
                COALESCE(p.unit_price, 0.00) as unit_price,
                COALESCE(p.prix_moyen, 0.00) as prix_moyen,
                p.category,
                c.libelle as category_name,
                c.code as category_code,
                p.created_at,
                p.updated_at
            FROM products p
            LEFT JOIN categories c ON p.category = c.id
            WHERE p.id = :product_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé'
        ]);
        exit();
    }
    
    // Ajouter des informations additionnelles au produit
    
    // 1. Dernier prix connu
    $prixQuery = "SELECT prix FROM prix_historique 
                 WHERE product_id = :product_id 
                 ORDER BY date_creation DESC LIMIT 1";
    $prixStmt = $pdo->prepare($prixQuery);
    $prixStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $prixStmt->execute();
    $dernierPrix = $prixStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dernierPrix) {
        $product['dernier_prix'] = $dernierPrix['prix'];
    } else {
        $product['dernier_prix'] = $product['unit_price'];
    }
    
    // 2. Nombre de mouvements récents (30 derniers jours)
    $mouvementsQuery = "SELECT COUNT(*) as count, 
                        SUM(CASE WHEN movement_type = 'entry' THEN 1 ELSE 0 END) as entries,
                        SUM(CASE WHEN movement_type = 'output' THEN 1 ELSE 0 END) as outputs,
                        MAX(date) as last_movement
                      FROM stock_movement 
                      WHERE product_id = :product_id
                      AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $mouvementsStmt = $pdo->prepare($mouvementsQuery);
    $mouvementsStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $mouvementsStmt->execute();
    $mouvements = $mouvementsStmt->fetch(PDO::FETCH_ASSOC);
    
    $product['mouvements_30j'] = [
        'total' => intval($mouvements['count'] ?? 0),
        'entrees' => intval($mouvements['entries'] ?? 0),
        'sorties' => intval($mouvements['outputs'] ?? 0),
        'dernier_mouvement' => $mouvements['last_movement'] ?? null
    ];
    
    // 3. Tendance de consommation (calculée sur 3 mois)
    $tendanceQuery = "SELECT 
                        MONTH(date) as mois, 
                        YEAR(date) as annee,
                        SUM(CASE WHEN movement_type = 'entry' THEN quantity ELSE 0 END) as total_entrees,
                        SUM(CASE WHEN movement_type = 'output' THEN quantity ELSE 0 END) as total_sorties
                      FROM stock_movement 
                      WHERE product_id = :product_id
                      AND date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                      GROUP BY YEAR(date), MONTH(date)
                      ORDER BY YEAR(date) DESC, MONTH(date) DESC";
    $tendanceStmt = $pdo->prepare($tendanceQuery);
    $tendanceStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $tendanceStmt->execute();
    $tendanceData = $tendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer la tendance
    $tendance = 'stable';
    $tendanceValeur = 0;
    
    if (count($tendanceData) >= 2) {
        // Calculer la différence entre les deux derniers mois
        $moisActuel = $tendanceData[0];
        $moisPrecedent = $tendanceData[1];
        
        $sortiesActuelles = floatval($moisActuel['total_sorties'] ?? 0);
        $sortiesPrecedentes = floatval($moisPrecedent['total_sorties'] ?? 0);
        
        if ($sortiesPrecedentes > 0) {
            $tendanceValeur = round((($sortiesActuelles - $sortiesPrecedentes) / $sortiesPrecedentes) * 100);
            
            if ($tendanceValeur > 10) {
                $tendance = 'hausse';
            } else if ($tendanceValeur < -10) {
                $tendance = 'baisse';
            }
        }
    }
    
    $product['tendance'] = [
        'direction' => $tendance,
        'valeur' => $tendanceValeur,
        'donnees' => $tendanceData
    ];
    
    // 4. Projets associés récemment (30 derniers jours)
    $projetsQuery = "SELECT DISTINCT nom_projet
                    FROM stock_movement
                    WHERE product_id = :product_id
                    AND nom_projet IS NOT NULL
                    AND nom_projet != ''
                    AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ORDER BY date DESC
                    LIMIT 5";
    $projetsStmt = $pdo->prepare($projetsQuery);
    $projetsStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $projetsStmt->execute();
    $projets = $projetsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $product['projets_recents'] = $projets;
    
    // Renvoyer les données du produit avec les informations supplémentaires
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur dans get_product_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
    exit();
}
?>