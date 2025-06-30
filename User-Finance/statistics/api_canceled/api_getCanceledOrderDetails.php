<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

// Définir le type de contenu
header('Content-Type: application/json');

// Récupérer les paramètres
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$expressionId = isset($_GET['expressionId']) ? $_GET['expressionId'] : '';

if ($id <= 0 || empty($expressionId)) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants ou invalides'
    ]);
    exit();
}

try {
    // Récupérer les détails de la commande annulée
    $query = "SELECT 
        ed.*,
        ip.code_projet,
        ip.nom_client,
        ip.description_projet,
        ip.sitgeo,
        ip.chefprojet
    FROM expression_dym ed
    JOIN identification_projet ip ON ed.idExpression = ip.idExpression
    WHERE ed.id = :id AND ed.idExpression = :expressionId AND ed.valide_achat = 'annulé'";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':expressionId', $expressionId);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode([
            'success' => false,
            'message' => 'Commande annulée non trouvée'
        ]);
        exit();
    }

    // Récupérer l'historique d'achat si disponible
    $historyQuery = "SELECT * FROM achats_materiaux 
                    WHERE expression_id = :expressionId 
                    AND designation = :designation
                    ORDER BY date_achat DESC";
    
    $historyStmt = $pdo->prepare($historyQuery);
    $historyStmt->bindParam(':expressionId', $expressionId);
    $historyStmt->bindParam(':designation', $order['designation']);
    $historyStmt->execute();
    
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'order' => $order,
        'history' => $history
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}
?>