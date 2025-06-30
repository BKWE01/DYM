<?php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

// Vérifier si des données ont été reçues
if (empty($input)) {
    echo json_encode(['success' => false, 'message' => 'Aucune donnée reçue']);
    exit;
}

// Connexion à la base de données
include_once '../../../../../database/connection.php';

// Fonction pour créer un nouvel enregistrement d'historique
function addHistoryRecord($pdo, $returnId, $action, $details = null)
{
    try {
        $userId = $_SESSION['user_id'];
        $userName = $_SESSION['user_name'] ?? 'Utilisateur';

        $stmt = $pdo->prepare("
            INSERT INTO stock_returns_history (
                return_id, 
                action, 
                details, 
                user_id, 
                user_name,
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([$returnId, $action, $details, $userId, $userName]);
    } catch (PDOException $e) {
        return false;
    }
}

// Traitement selon le type d'action
try {
    // Cas 1: Ajouter un nouveau retour
    if (!isset($input['action']) && isset($input['product_id'])) {
        // Validation des champs requis
        if (
            !isset($input['product_id']) ||
            !isset($input['quantity']) ||
            !isset($input['origin']) ||
            !isset($input['return_reason']) ||
            !isset($input['product_condition']) ||
            !isset($input['returned_by'])
        ) {
            echo json_encode(['success' => false, 'message' => 'Tous les champs requis doivent être remplis']);
            exit;
        }

        // Vérifier si le produit existe
        $stmt = $pdo->prepare("SELECT id, quantity, unit FROM products WHERE id = ?");
        $stmt->execute([$input['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
            exit;
        }

        // Valider la quantité
        $quantity = floatval($input['quantity']);
        if ($quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'La quantité doit être supérieure à zéro']);
            exit;
        }

        // Valider le motif "autre"
        if ($input['return_reason'] === 'other' && empty($input['other_reason'])) {
            echo json_encode(['success' => false, 'message' => 'Veuillez préciser le motif de retour']);
            exit;
        }

        // Insérer le retour dans la base de données
        $stmt = $pdo->prepare("
            INSERT INTO stock_returns (
                product_id,
                quantity,
                origin,
                return_reason,
                other_reason,
                product_condition,
                returned_by,
                comments,
                status,
                created_by,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");

        $result = $stmt->execute([
            $input['product_id'],
            $quantity,
            $input['origin'],
            $input['return_reason'],
            $input['return_reason'] === 'other' ? $input['other_reason'] : null,
            $input['product_condition'],
            $input['returned_by'],
            $input['comments'] ?? null,
            $_SESSION['user_id']
        ]);

        if ($result) {
            $returnId = $pdo->lastInsertId();

            // Ajouter un enregistrement dans l'historique
            addHistoryRecord($pdo, $returnId, 'created');

            echo json_encode([
                'success' => true,
                'message' => 'Retour créé avec succès',
                'return_id' => $returnId
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du retour']);
        }
    }
    // Cas 2: Traiter un retour existant (approuver, rejeter, compléter)
    else if (isset($input['action']) && isset($input['return_id'])) {
        $returnId = intval($input['return_id']);
        $action = $input['action'];

        // Vérifier si le retour existe
        $stmt = $pdo->prepare("
            SELECT r.*, p.product_name, p.barcode, p.quantity as current_stock, p.unit 
            FROM stock_returns r
            JOIN products p ON r.product_id = p.id
            WHERE r.id = ?
        ");
        $stmt->execute([$returnId]);
        $return = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$return) {
            echo json_encode(['success' => false, 'message' => 'Retour non trouvé']);
            exit;
        }

        // Vérifier si le retour est dans un état permettant l'action demandée
        switch ($action) {
            case 'approve':
                if ($return['status'] !== 'pending') {
                    echo json_encode(['success' => false, 'message' => 'Ce retour ne peut pas être approuvé dans son état actuel']);
                    exit;
                }

                // Mettre à jour le statut du retour
                $stmt = $pdo->prepare("
                    UPDATE stock_returns 
                    SET status = 'approved', approved_by = ?, approved_at = NOW() 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$_SESSION['user_id'], $returnId]);

                if ($result) {
                    // Ajouter un enregistrement dans l'historique
                    addHistoryRecord($pdo, $returnId, 'approved');

                    echo json_encode([
                        'success' => true,
                        'message' => 'Retour approuvé avec succès'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'approbation du retour']);
                }
                break;

            case 'reject':
                if ($return['status'] !== 'pending') {
                    echo json_encode(['success' => false, 'message' => 'Ce retour ne peut pas être rejeté dans son état actuel']);
                    exit;
                }

                if (!isset($input['reject_reason']) || empty($input['reject_reason'])) {
                    echo json_encode(['success' => false, 'message' => 'Veuillez fournir un motif de rejet']);
                    exit;
                }

                // Mettre à jour le statut du retour
                $stmt = $pdo->prepare("
                    UPDATE stock_returns 
                    SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), reject_reason = ? 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$_SESSION['user_id'], $input['reject_reason'], $returnId]);

                if ($result) {
                    // Ajouter un enregistrement dans l'historique
                    addHistoryRecord($pdo, $returnId, 'rejected', $input['reject_reason']);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Retour rejeté avec succès'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors du rejet du retour']);
                }
                break;

            case 'complete':
                if ($return['status'] !== 'approved') {
                    echo json_encode(['success' => false, 'message' => 'Ce retour doit être approuvé avant d\'être complété']);
                    exit;
                }

                // Vérifier si le produit existe toujours et est actif
                $stmt = $pdo->prepare("SELECT id, quantity FROM products WHERE id = ? AND quantity IS NOT NULL");
                $stmt->execute([$return['product_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    echo json_encode(['success' => false, 'message' => 'Le produit associé à ce retour n\'existe plus ou a été désactivé']);
                    exit;
                }

                // Commencer une transaction
                $pdo->beginTransaction();

                try {
                    // 1. Mettre à jour le statut du retour
                    $stmt = $pdo->prepare("
                            UPDATE stock_returns 
                            SET status = 'completed', completed_by = ?, completed_at = NOW() 
                            WHERE id = ? AND status = 'approved'
                        ");
                    $result = $stmt->execute([$_SESSION['user_id'], $returnId]);

                    if (!$result || $stmt->rowCount() === 0) {
                        throw new Exception('Impossible de mettre à jour le statut du retour. Il a peut-être déjà été traité.');
                    }

                    // 2. Mettre à jour le stock du produit
                    $newQuantity = $return['current_stock'] + $return['quantity'];
                    $stmt = $pdo->prepare("
                            UPDATE products 
                            SET quantity = ?, 
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                    $result = $stmt->execute([$newQuantity, $return['product_id']]);

                    if (!$result || $stmt->rowCount() === 0) {
                        throw new Exception('Impossible de mettre à jour la quantité du produit');
                    }

                    // 3. Ajouter un mouvement de stock avec plus de détails
                    $stmt = $pdo->prepare("
                            INSERT INTO stock_movement (
                                product_id, 
                                quantity, 
                                movement_type, 
                                provenance, 
                                destination, 
                                demandeur, 
                                notes, 
                                date,
                                created_at
                            ) VALUES (?, ?, 'entry', ?, 'Stock (Retour)', ?, ?, NOW(), NOW())
                        ");
                    $result = $stmt->execute([
                        $return['product_id'],
                        $return['quantity'],
                        $return['origin'],
                        $return['returned_by'],
                        'Retour en stock #' . $returnId . ' - Motif: ' . ($return['return_reason'] === 'other' ? $return['other_reason'] : $return['return_reason'])
                    ]);

                    if (!$result) {
                        throw new Exception('Impossible d\'enregistrer le mouvement de stock');
                    }

                    // 4. Ajouter un enregistrement dans l'historique
                    $historyDetails = 'Quantité de ' . $return['quantity'] . ' ' . ($return['unit'] ?: 'unité(s)') . ' ajoutée au stock';
                    $result = addHistoryRecord(
                        $pdo,
                        $returnId,
                        'completed',
                        $historyDetails
                    );

                    if (!$result) {
                        throw new Exception('Impossible d\'enregistrer l\'historique');
                    }

                    // 5. Ajouter un log système pour traçabilité
                    $stmt = $pdo->prepare("
                            INSERT INTO system_logs (
                                user_id, 
                                username, 
                                action, 
                                type, 
                                entity_id, 
                                entity_name, 
                                details, 
                                ip_address, 
                                created_at
                            ) VALUES (?, ?, 'complete_return', 'return', ?, ?, ?, ?, NOW())
                        ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $_SESSION['user_name'] ?? 'Utilisateur',
                        $returnId,
                        'Retour #' . $returnId,
                        $historyDetails,
                        $_SERVER['REMOTE_ADDR'] ?? null
                    ]);

                    // Valider la transaction
                    $pdo->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Retour complété avec succès'
                    ]);
                } catch (Exception $e) {
                    // Annuler la transaction en cas d'erreur
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de la complétion du retour: ' . $e->getMessage()]);
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>