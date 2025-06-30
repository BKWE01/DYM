<?php
session_start();
header('Content-Type: application/json');

// Activer les journaux d'erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 0); // Ne pas afficher les erreurs dans la réponse JSON
ini_set('log_errors', 1);

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Récupérer l'ID du matériau
$materialId = $_GET['material_id'] ?? '';

if (empty($materialId)) {
    error_log("get_material_info: ID de matériau non spécifié");
    echo json_encode(['error' => 'ID de matériau non spécifié']);
    exit();
}

// Journal pour le débogage
error_log("get_material_info: Recherche d'informations pour le matériau ID: $materialId");

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Récupérer les informations du matériau
    $query = "SELECT id, designation, fournisseur, prix_unitaire, qt_acheter, unit, idExpression 
              FROM expression_dym 
              WHERE id = :material_id";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':material_id', $materialId);
    $stmt->execute();

    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($material) {
        error_log("get_material_info: Matériau trouvé: " . json_encode($material));

        // Si prix_unitaire est '0.00', est vide ou n'est pas défini, chercher alternatives
        if (empty($material['prix_unitaire']) || $material['prix_unitaire'] == '0.00' || floatval($material['prix_unitaire']) == 0) {
            error_log("get_material_info: Prix unitaire vide ou nul, recherche d'alternatives");
            
            // 1. Chercher dans achats_materiaux pour un prix unitaire historique
            $historicQuery = "SELECT prix_unitaire 
                            FROM achats_materiaux 
                            WHERE designation = :designation 
                            AND prix_unitaire > 0
                            ORDER BY date_achat DESC 
                            LIMIT 1";

            $historicStmt = $pdo->prepare($historicQuery);
            $historicStmt->bindParam(':designation', $material['designation']);
            $historicStmt->execute();

            $historicPrice = $historicStmt->fetch(PDO::FETCH_ASSOC);

            if ($historicPrice && !empty($historicPrice['prix_unitaire'])) {
                $material['prix_unitaire'] = $historicPrice['prix_unitaire'];
                error_log("get_material_info: Prix historique trouvé: " . $material['prix_unitaire']);
            } else {
                // 2. Chercher dans products si aucun prix historique n'est trouvé
                $productQuery = "SELECT unit_price, prix_moyen 
                                FROM products 
                                WHERE product_name = :designation 
                                LIMIT 1";

                $productStmt = $pdo->prepare($productQuery);
                $productStmt->bindParam(':designation', $material['designation']);
                $productStmt->execute();

                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    // Préférer le prix moyen s'il existe et n'est pas nul, sinon utiliser le prix unitaire
                    if (!empty($product['prix_moyen']) && floatval($product['prix_moyen']) > 0) {
                        $material['prix_unitaire'] = $product['prix_moyen'];
                        error_log("get_material_info: Prix moyen trouvé: " . $material['prix_unitaire']);
                    } elseif (!empty($product['unit_price']) && floatval($product['unit_price']) > 0) {
                        $material['prix_unitaire'] = $product['unit_price'];
                        error_log("get_material_info: Prix unitaire du produit trouvé: " . $material['prix_unitaire']);
                    }
                }
            }
        }

        // S'assurer que le prix unitaire est formaté correctement
        if (isset($material['prix_unitaire']) && $material['prix_unitaire'] !== null && floatval($material['prix_unitaire']) > 0) {
            // Convertir en nombre pour s'assurer qu'il n'y a pas de problème de format
            $material['prix_unitaire'] = number_format((float) $material['prix_unitaire'], 2, '.', '');
        } else {
            // Si toujours pas de prix, mettre une valeur vide (mais pas null)
            $material['prix_unitaire'] = '';
        }

        echo json_encode($material);
    } else {
        error_log("get_material_info: Matériau non trouvé pour ID: $materialId");
        echo json_encode(['error' => 'Matériau non trouvé', 'id' => $materialId]);
    }

} catch (PDOException $e) {
    error_log("get_material_info: Erreur de base de données: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>