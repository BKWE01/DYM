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
    // Récupérer les informations de la facture associée au mouvement de stock
    $query = "
        SELECT i.id, i.invoice_number, i.file_path, i.original_filename, i.upload_date, i.supplier
        FROM stock_movement sm
        LEFT JOIN invoices i ON sm.invoice_id = i.id
        WHERE sm.id = :movement_id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':movement_id' => $movementId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invoice && $invoice['id']) {
        // Vérifier si le chemin du fichier est valide
        if (!empty($invoice['file_path'])) {
            // Utiliser le résolveur de chemin pour trouver le fichier
            $fileResolverUrl = "get_file_path.php?path=" . urlencode($invoice['file_path']) . "&invoice_id=" . $invoice['id'];
            $fileResolverResponse = @file_get_contents($fileResolverUrl);

            if ($fileResolverResponse !== false) {
                $fileInfo = json_decode($fileResolverResponse, true);

                if ($fileInfo && $fileInfo['success']) {
                    // Remplacer le chemin par l'URL résolue
                    $invoice['file_path'] = $fileInfo['url'];
                    $invoice['resolved_path'] = true;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'invoice' => $invoice
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Aucune facture associée à ce mouvement',
            'invoice' => null
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'invoice' => null
    ]);
}