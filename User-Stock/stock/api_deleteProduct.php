<?php
// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure le logger
include_once __DIR__ . '/trace/include_logger.php';
$logger = getLogger();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $productId = $_POST['id'];

    error_log("Tentative de suppression du produit avec l'ID: " . $productId);
    error_log("Contenu de \$_POST: " . print_r($_POST, true));

    try {
        // Vérifier si le produit existe avant de le supprimer
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id = :id");
        $checkStmt->execute(['id' => $productId]);
        $productExists = $checkStmt->fetchColumn();

        error_log("Le produit existe-t-il ? " . ($productExists ? "Oui" : "Non"));

        if ($productExists) {
            // Récupérer les données du produit avant suppression (incluant l'image)
            $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
            $productStmt->execute(['id' => $productId]);
            $productData = $productStmt->fetch(PDO::FETCH_ASSOC);

            // ⭐ NOUVEAU : Supprimer le fichier image s'il existe
            if (!empty($productData['product_image'])) {
                $imageToDelete = null;
                
                // Déterminer le chemin physique du fichier à supprimer
                if (strpos($productData['product_image'], '/public/') === 0) {
                    // Nouveau format : chemin absolu depuis la racine
                    $imageToDelete = '../../public/uploads/products/' . basename($productData['product_image']);
                } else if (strpos($productData['product_image'], 'uploads/products/') === 0) {
                    // Ancien format : chemin relatif
                    $imageToDelete = '../../public/' . $productData['product_image'];
                } else if (strpos($productData['product_image'], '../../public/uploads/products/') === 0) {
                    // Format déjà correct
                    $imageToDelete = $productData['product_image'];
                }

                // Supprimer le fichier physique s'il existe
                if ($imageToDelete && file_exists($imageToDelete)) {
                    if (unlink($imageToDelete)) {
                        error_log("Image supprimée avec succès: " . $imageToDelete);
                    } else {
                        error_log("Erreur lors de la suppression de l'image: " . $imageToDelete);
                        // Ne pas arrêter le processus, continuer la suppression du produit
                    }
                } else {
                    error_log("Fichier image non trouvé ou chemin invalide: " . $productData['product_image']);
                }
            }

            // Commencer une transaction pour assurer la cohérence
            $pdo->beginTransaction();

            try {
                // Supprimer le produit de la base de données
                $sql = "DELETE FROM products WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['id' => $productId]);

                if ($stmt->rowCount() > 0) {
                    // Valider la transaction
                    $pdo->commit();

                    // Logger la suppression après succès
                    if ($logger) {
                        $logger->logProductDelete($productData);
                    }

                    error_log("Produit supprimé avec succès (ID: $productId)");
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Produit et image supprimés avec succès'
                    ]);
                } else {
                    // Annuler la transaction si aucune ligne affectée
                    $pdo->rollBack();
                    error_log("Aucune ligne supprimée pour l'ID: " . $productId);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Erreur lors de la suppression du produit'
                    ]);
                }
            } catch (Exception $e) {
                // Annuler la transaction en cas d'erreur
                $pdo->rollBack();
                throw $e;
            }
        } else {
            error_log("Aucun produit trouvé avec l'ID: " . $productId);
            echo json_encode([
                'success' => false, 
                'message' => 'Aucun produit trouvé avec cet ID'
            ]);
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur de base de données: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        error_log("Erreur générale: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Une erreur inattendue est survenue'
        ]);
    }
} else {
    error_log("Requête invalide ou ID manquant");
    error_log("Contenu de \$_POST: " . print_r($_POST, true));
    error_log("Méthode de la requête: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false, 
        'message' => 'Requête invalide ou ID manquant'
    ]);
}
?>