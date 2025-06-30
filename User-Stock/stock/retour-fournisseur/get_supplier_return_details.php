<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Récupérer l'ID du mouvement
    $movementId = isset($_GET['movement_id']) ? intval($_GET['movement_id']) : 0;

    if ($movementId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de mouvement invalide'
        ]);
        exit;
    }

    // Vérifier d'abord si la table supplier_returns existe
    $tableExists = false;
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'supplier_returns'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        // Récupérer les détails du retour fournisseur
        $stmt = $pdo->prepare("
            SELECT sr.*, p.product_name
            FROM supplier_returns sr
            JOIN products p ON sr.product_id = p.id
            WHERE sr.movement_id = :movement_id
        ");
        $stmt->execute([':movement_id' => $movementId]);
        $return = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($return) {
            echo json_encode([
                'success' => true,
                'return' => $return
            ]);
            exit;
        }
    }

    // Si la table n'existe pas ou si aucun enregistrement n'est trouvé,
    // récupérer les informations de base du mouvement de stock
    $stmt = $pdo->prepare("
        SELECT sm.*, p.product_name
        FROM stock_movement sm
        JOIN products p ON sm.product_id = p.id
        WHERE sm.id = :movement_id
    ");
    $stmt->execute([':movement_id' => $movementId]);
    $movement = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($movement) {
        // Extraire les informations du retour fournisseur à partir du champ destination et notes
        $supplierName = '';
        if (strpos($movement['destination'], 'Retour fournisseur:') === 0) {
            $supplierName = trim(substr($movement['destination'], strlen('Retour fournisseur:')));
        }

        $reason = '';
        $comment = '';
        if ($movement['notes'] && strpos($movement['notes'], 'Motif:') === 0) {
            $notesContent = substr($movement['notes'], strlen('Motif:'));
            $parts = explode(' - ', $notesContent);
            $reason = trim($parts[0]);
            if (count($parts) > 1) {
                $comment = trim($parts[1]);
            }
        }

        echo json_encode([
            'success' => true,
            'return' => [
                'id' => $movement['id'],
                'movement_id' => $movement['id'],
                'product_id' => $movement['product_id'],
                'product_name' => $movement['product_name'],
                'supplier_name' => $supplierName,
                'quantity' => $movement['quantity'],
                'reason' => $reason,
                'comment' => $comment,
                'status' => 'pending', // Valeur par défaut
                'created_at' => $movement['date']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Mouvement non trouvé'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>