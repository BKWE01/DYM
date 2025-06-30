<?php
/**
 * API pour vérifier la disponibilité d'un produit en stock
 * 
 * Vérifie si un produit existe dans le stock et renvoie sa quantité disponible.
 * 
 * @package DYM_MANUFACTURE
 * @subpackage stock/api
 */

// Initialisation
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non connecté'
    ]);
    exit();
}

// Vérifier le paramètre requis
if (!isset($_GET['designation'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètre manquant: designation'
    ]);
    exit();
}

$designation = $_GET['designation'];

// Connexion à la base de données
try {
    include_once '../../../../../../database/connection.php';

    // Rechercher le produit dans la table products
    $query = "SELECT 
                p.id,
                p.barcode,
                p.product_name,
                p.quantity,
                p.quantity_reserved,
                p.unit,
                p.unit_price,
                p.prix_moyen,
                p.category,
                c.libelle as category_name
              FROM products p
              LEFT JOIN categories c ON p.category = c.id
              WHERE LOWER(p.product_name) = LOWER(:designation)
              LIMIT 1";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':designation', $designation);
    $stmt->execute();
    
    $productInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($productInfo) {
        // Calculer la disponibilité réelle (quantité - réservations)
        $available = $productInfo['quantity'] - ($productInfo['quantity_reserved'] ?: 0);
        
        // Récupérer les derniers mouvements de stock pour ce produit
        $movementsQuery = "SELECT 
                            sm.id,
                            sm.product_id,
                            sm.quantity,
                            sm.movement_type,
                            sm.provenance,
                            sm.fournisseur,
                            sm.destination,
                            sm.date
                          FROM stock_movement sm
                          WHERE sm.product_id = :product_id
                          ORDER BY sm.date DESC
                          LIMIT 5";
        
        $movementsStmt = $pdo->prepare($movementsQuery);
        $movementsStmt->bindParam(':product_id', $productInfo['id']);
        $movementsStmt->execute();
        
        $recentMovements = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'stock_info' => $productInfo,
            'available' => $available,
            'recent_movements' => $recentMovements
        ]);
    } else {
        // Vérifier si le produit existe dans les commandes mais pas encore en stock
        $orderedQuery = "SELECT 
                           designation, 
                           COUNT(*) as order_count,
                           MAX(date_achat) as last_order_date
                         FROM achats_materiaux
                         WHERE LOWER(designation) = LOWER(:designation)
                         GROUP BY designation";
        
        $orderedStmt = $pdo->prepare($orderedQuery);
        $orderedStmt->bindParam(':designation', $designation);
        $orderedStmt->execute();
        
        $orderedInfo = $orderedStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'stock_info' => null,
            'order_info' => $orderedInfo
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
    exit();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
    exit();
}
?>