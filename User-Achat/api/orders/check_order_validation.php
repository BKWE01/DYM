<?php

/*Le fichier check_order_validation.php serait utile SEULEMENT si vous voulez :

Ajouter un bouton "Vérifier validation" sur chaque ligne de matériau
Afficher en temps réel l'état de validation d'un bon de commande
Permettre aux utilisateurs de voir pourquoi une commande n'est pas encore validée
*/

/**
 * API pour vérifier l'état de validation d'une commande spécifique
 * 
 * @package DYM_MANUFACTURE 
 * @subpackage achats_materiaux
 */

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Récupérer les paramètres
    $expressionId = $_GET['expression_id'] ?? '';
    $fournisseur = $_GET['fournisseur'] ?? '';

    if (empty($expressionId)) {
        throw new Exception('ID d\'expression manquant');
    }

    // Vérifier si un bon de commande validé existe pour cette expression et ce fournisseur
    $validationQuery = "
        SELECT 
            po.id,
            po.order_number,
            po.signature_finance,
            po.user_finance_id,
            uf.name as finance_user_name,
            po.generated_at,
            po.signature_finance as validated_at
        FROM purchase_orders po
        LEFT JOIN users_exp uf ON po.user_finance_id = uf.id
        WHERE (
            po.expression_id = :expression_id 
            OR JSON_CONTAINS(po.related_expressions, JSON_QUOTE(:expression_id_json))
        )
        AND (:fournisseur = '' OR LOWER(TRIM(po.fournisseur)) = LOWER(TRIM(:fournisseur)))
        ORDER BY po.generated_at DESC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($validationQuery);
    $stmt->bindValue(':expression_id', $expressionId);
    $stmt->bindValue(':expression_id_json', $expressionId);
    $stmt->bindValue(':fournisseur', $fournisseur);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'has_order' => true,
            'is_validated' => !empty($order['signature_finance']) && !empty($order['user_finance_id']),
            'order_info' => [
                'order_number' => $order['order_number'],
                'generated_at' => $order['generated_at'],
                'validated_at' => $order['validated_at'],
                'validated_by' => $order['finance_user_name']
            ]
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'has_order' => false,
            'is_validated' => false,
            'message' => 'Aucun bon de commande trouvé pour cette expression'
        ]);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la vérification: ' . $e->getMessage()
    ]);
}