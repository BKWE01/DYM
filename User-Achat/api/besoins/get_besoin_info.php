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

// Récupérer l'ID du besoin
$besoinId = $_GET['besoin_id'] ?? '';

if (empty($besoinId)) {
    error_log("get_besoin_info: ID de besoin non spécifié");
    echo json_encode(['error' => 'ID de besoin non spécifié']);
    exit();
}

// Journal pour le débogage
error_log("get_besoin_info: Recherche d'informations pour le besoin ID: $besoinId");

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Récupérer les informations du besoin
    $query = "SELECT id, designation_article as designation, qt_acheter, caracteristique as unit, idBesoin 
              FROM besoins 
              WHERE id = :besoin_id";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':besoin_id', $besoinId);
    $stmt->execute();

    $besoin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($besoin) {
        error_log("get_besoin_info: Besoin trouvé: " . json_encode($besoin));

        // Chercher un prix similaire pour ce matériau
        $prixQuery = "SELECT prix_unitaire 
                     FROM achats_materiaux 
                     WHERE designation = :designation 
                     AND prix_unitaire > 0
                     ORDER BY date_achat DESC 
                     LIMIT 1";

        $prixStmt = $pdo->prepare($prixQuery);
        $prixStmt->bindParam(':designation', $besoin['designation']);
        $prixStmt->execute();

        $prix = $prixStmt->fetch(PDO::FETCH_ASSOC);

        if ($prix && !empty($prix['prix_unitaire'])) {
            $besoin['prix_unitaire'] = $prix['prix_unitaire'];
            error_log("get_besoin_info: Prix trouvé: " . $besoin['prix_unitaire']);
        } else {
            // Chercher dans products si aucun prix n'est trouvé
            $productQuery = "SELECT unit_price, prix_moyen 
                           FROM products 
                           WHERE product_name = :designation 
                           LIMIT 1";

            $productStmt = $pdo->prepare($productQuery);
            $productStmt->bindParam(':designation', $besoin['designation']);
            $productStmt->execute();

            $product = $productStmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                // Préférer le prix moyen s'il existe et n'est pas nul, sinon utiliser le prix unitaire
                if (!empty($product['prix_moyen']) && floatval($product['prix_moyen']) > 0) {
                    $besoin['prix_unitaire'] = $product['prix_moyen'];
                    error_log("get_besoin_info: Prix moyen trouvé: " . $besoin['prix_unitaire']);
                } elseif (!empty($product['unit_price']) && floatval($product['unit_price']) > 0) {
                    $besoin['prix_unitaire'] = $product['unit_price'];
                    error_log("get_besoin_info: Prix unitaire du produit trouvé: " . $besoin['prix_unitaire']);
                }
            }
        }

        // S'assurer que le prix unitaire est formaté correctement
        if (isset($besoin['prix_unitaire']) && $besoin['prix_unitaire'] !== null && floatval($besoin['prix_unitaire']) > 0) {
            // Convertir en nombre pour s'assurer qu'il n'y a pas de problème de format
            $besoin['prix_unitaire'] = number_format((float) $besoin['prix_unitaire'], 2, '.', '');
        } else {
            // Si toujours pas de prix, mettre une valeur vide (mais pas null)
            $besoin['prix_unitaire'] = '';
        }

        echo json_encode($besoin);
    } else {
        error_log("get_besoin_info: Besoin non trouvé pour ID: $besoinId");
        echo json_encode(['error' => 'Besoin non trouvé', 'id' => $besoinId]);
    }

} catch (PDOException $e) {
    error_log("get_besoin_info: Erreur de base de données: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>