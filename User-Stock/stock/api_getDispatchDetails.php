<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../../database/connection.php'; 

// Récupérer l'ID du mouvement
$movementId = isset($_GET['movement_id']) ? intval($_GET['movement_id']) : 0;

if ($movementId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de mouvement invalide'
    ]);
    exit;
}

try {
    // Récupérer les détails de base du mouvement
    $movementStmt = $pdo->prepare("
        SELECT sm.*, p.product_name 
        FROM stock_movement sm
        JOIN products p ON sm.product_id = p.id
        WHERE sm.id = :movement_id
    ");
    $movementStmt->execute([':movement_id' => $movementId]);
    $movement = $movementStmt->fetch(PDO::FETCH_ASSOC);

    if (!$movement) {
        echo json_encode([
            'success' => false,
            'message' => 'Mouvement non trouvé'
        ]);
        exit;
    }

    // Récupérer les détails du dispatching depuis la table dispatch_details
    $detailsStmt = $pdo->prepare("
        SELECT dd.*, p.product_name
        FROM dispatch_details dd
        JOIN products p ON dd.product_id = p.id
        WHERE dd.movement_id = :movement_id
        ORDER BY dd.status ASC, dd.id ASC
    ");
    $detailsStmt->execute([':movement_id' => $movementId]);
    $dispatchDetails = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Si aucun détail n'est trouvé dans la table dédiée, essayer de récupérer depuis les logs
    if (empty($dispatchDetails)) {
        // Lire le fichier de log de dispatching
        $logFile = 'dispatching_log.txt';
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $lines = explode(PHP_EOL, $logContent);

            $dispatchDetailsFromLog = [];
            $movementFound = false;
            $dispatchingData = [];

            foreach ($lines as $line) {
                // Chercher une mention du mouvement par son ID
                if (strpos($line, "Mouvement de stock #$movementId") !== false) {
                    $movementFound = true;
                }

                // Si le mouvement a été trouvé, collecter les informations de dispatching
                if ($movementFound) {
                    // Si on trouve une commande complétée
                    if (preg_match('/Commande #(\d+) COMPLETÉE et marquée comme \'reçu\'/', $line, $matches)) {
                        $orderId = $matches[1];

                        // Rechercher des informations supplémentaires sur cette commande
                        $orderStmt = $pdo->prepare("
                            SELECT am.*, ip.code_projet, ip.nom_client
                            FROM achats_materiaux am
                            JOIN identification_projet ip ON am.expression_id = ip.idExpression
                            WHERE am.id = :order_id
                        ");
                        $orderStmt->execute([':order_id' => $orderId]);
                        $orderInfo = $orderStmt->fetch(PDO::FETCH_ASSOC);

                        if ($orderInfo) {
                            $dispatchDetailsFromLog[] = [
                                'id' => count($dispatchDetailsFromLog) + 1,
                                'movement_id' => $movementId,
                                'order_id' => $orderId,
                                'product_id' => $movement['product_id'],
                                'product_name' => $movement['product_name'],
                                'project' => $orderInfo['code_projet'],
                                'client' => $orderInfo['nom_client'],
                                'allocated' => $orderInfo['quantity'], // Quantité attribuée estimée
                                'status' => 'completed',
                                'total_quantity' => $movement['quantity'],
                                'dispatch_date' => $movement['date']
                            ];
                        }
                    }
                    // Si on trouve une commande partiellement satisfaite
                    else if (preg_match('/Commande #(\d+) PARTIELLEMENT satisfaite, nouvelle quantité restante: (\d+(\.\d+)?)/', $line, $matches)) {
                        $orderId = $matches[1];
                        $remaining = $matches[2];

                        // Rechercher des informations supplémentaires sur cette commande
                        $orderStmt = $pdo->prepare("
                            SELECT am.*, ip.code_projet, ip.nom_client
                            FROM achats_materiaux am
                            JOIN identification_projet ip ON am.expression_id = ip.idExpression
                            WHERE am.id = :order_id
                        ");
                        $orderStmt->execute([':order_id' => $orderId]);
                        $orderInfo = $orderStmt->fetch(PDO::FETCH_ASSOC);

                        if ($orderInfo) {
                            $dispatchDetailsFromLog[] = [
                                'id' => count($dispatchDetailsFromLog) + 1,
                                'movement_id' => $movementId,
                                'order_id' => $orderId,
                                'product_id' => $movement['product_id'],
                                'product_name' => $movement['product_name'],
                                'project' => $orderInfo['code_projet'],
                                'client' => $orderInfo['nom_client'],
                                'allocated' => $orderInfo['quantity'] - $remaining, // Quantité allouée estimée
                                'remaining' => $remaining,
                                'status' => 'partial',
                                'total_quantity' => $movement['quantity'],
                                'dispatch_date' => $movement['date']
                            ];
                        }
                    }

                    // Si on trouve une ligne marquant la fin du traitement pour ce mouvement
                    if (strpos($line, "Fin du traitement du mouvement #$movementId") !== false) {
                        break;
                    }
                }
            }

            if (!empty($dispatchDetailsFromLog)) {
                $dispatchDetails = $dispatchDetailsFromLog;
            }
        }
    }

    // Si nous n'avons toujours pas de détails, créer un enregistrement générique
    if (empty($dispatchDetails)) {
        $dispatchDetails = [
            [
                'id' => 1,
                'movement_id' => $movementId,
                'order_id' => null,
                'product_id' => $movement['product_id'],
                'product_name' => $movement['product_name'],
                'project' => $movement['nom_projet'] ?: 'Non spécifié',
                'client' => 'Non spécifié',
                'allocated' => $movement['quantity'],
                'status' => 'completed',
                'total_quantity' => $movement['quantity'],
                'dispatch_date' => $movement['date'],
                'note' => 'Détails de dispatching non disponibles'
            ]
        ];
    }

    echo json_encode([
        'success' => true,
        'details' => $dispatchDetails
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}