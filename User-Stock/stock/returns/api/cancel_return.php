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
if (empty($input) || !isset($input['return_id'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$returnId = intval($input['return_id']);
$cancelReason = isset($input['cancel_reason']) ? trim($input['cancel_reason']) : '';

// Connexion à la base de données
include_once '../../../../database/connection.php';

// Fonction pour ajouter un enregistrement dans l'historique
function addHistoryRecord($pdo, $returnId, $action, $details = null) {
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

try {
    // Récupérer les informations complètes sur le retour, y compris le produit associé
    $stmt = $pdo->prepare("
        SELECT r.*, p.quantity as current_stock
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
    
    // On ne peut annuler qu'un retour en attente ou approuvé
    if (!in_array($return['status'], ['pending', 'approved'])) {
        echo json_encode(['success' => false, 'message' => 'Ce retour ne peut pas être annulé dans son état actuel']);
        exit;
    }
    
    // Démarrer une transaction pour garantir l'intégrité des données
    $pdo->beginTransaction();
    
    try {
        // Si le retour était approuvé, vérifier si des actions ont déjà été effectuées sur le stock
        if ($return['status'] === 'approved') {
            // Vérifier si des mouvements de stock ont déjà été créés pour ce retour
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as movement_count 
                FROM stock_movement 
                WHERE notes LIKE ? AND movement_type = 'entry'
            ");
            $stmt->execute(['%Retour en stock #' . $returnId . '%']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Si des mouvements existent déjà, ne pas permettre l'annulation
            if ($result && $result['movement_count'] > 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Ce retour a déjà été partiellement ou totalement traité et ne peut pas être annulé']);
                exit;
            }
        }
        
        // Mettre à jour le statut du retour
        $stmt = $pdo->prepare("
            UPDATE stock_returns 
            SET status = 'canceled', 
                canceled_by = ?, 
                canceled_at = NOW(), 
                cancel_reason = ? 
            WHERE id = ?
        ");
        $result = $stmt->execute([$_SESSION['user_id'], $cancelReason, $returnId]);
        
        if (!$result) {
            throw new Exception('Erreur lors de la mise à jour du statut du retour');
        }
        
        // Ajouter un enregistrement dans l'historique
        if (!addHistoryRecord($pdo, $returnId, 'canceled', $cancelReason ? 'Motif: ' . $cancelReason : null)) {
            throw new Exception('Erreur lors de l\'ajout de l\'historique');
        }
        
        // Ajouter un log système pour traçabilité
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
            ) VALUES (?, ?, 'cancel_return', 'return', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['user_name'] ?? 'Utilisateur',
            $returnId,
            'Retour #' . $returnId,
            'Annulation - ' . ($cancelReason ? 'Motif: ' . $cancelReason : 'Sans motif spécifié'),
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        // Valider la transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Retour annulé avec succès'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'annulation: ' . $e->getMessage()]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>