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
include_once '../../../include/date_helper.php';

// Définir le type de contenu
header('Content-Type: application/json');

try {
    // Récupérer les commandes annulées récentes
    $query = "SELECT 
        ed.id,
        ed.idExpression,
        ed.designation,
        ed.quantity,
        ed.unit,
        ed.prix_unitaire,
        ed.valide_achat,
        ed.updated_at,
        ip.code_projet,
        ip.nom_client
    FROM expression_dym ed
    JOIN identification_projet ip ON ed.idExpression = ip.idExpression
    WHERE ed.valide_achat = 'annulé'
    ORDER BY ed.updated_at DESC
    LIMIT 20";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer le statut original (avant annulation) si disponible
    foreach ($orders as &$order) {
        // On suppose que le statut original a été sauvegardé quelque part ou peut être déterminé
        // Si ce n'est pas le cas, on peut le laisser comme "Inconnu"
        $order['original_status'] = 'pas validé'; // Par défaut, supposons que c'était "pas validé"

        // On pourrait vérifier dans les achats_materiaux si cette commande avait été traitée avant
        $historyQuery = "SELECT status FROM achats_materiaux 
                        WHERE expression_id = :expressionId 
                        AND designation = :designation
                        ORDER BY date_achat DESC LIMIT 1";

        $historyStmt = $pdo->prepare($historyQuery);
        $historyStmt->bindParam(':expressionId', $order['idExpression']);
        $historyStmt->bindParam(':designation', $order['designation']);
        $historyStmt->execute();

        $history = $historyStmt->fetch(PDO::FETCH_ASSOC);
        if ($history) {
            $order['original_status'] = $history['status'];
        }
    }

    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}
?>