<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

include_once '../../../database/connection.php';
include_once __DIR__ . '/../trace/include_logger.php';
$logger = getLogger();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['movement_id']) || !isset($input['invoice'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

$movementId = intval($input['movement_id']);
$invoice = $input['invoice'];
$replaceExisting = isset($input['replace_existing']) ? (bool)$input['replace_existing'] : false;

if ($movementId <= 0 || empty($invoice['file_path']) || empty($invoice['original_filename'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Vérifier si une facture était déjà associée
    $existingStmt = $pdo->prepare("SELECT invoice_id FROM stock_movement WHERE id = :id");
    $existingStmt->execute([':id' => $movementId]);
    $existingInvoiceId = $existingStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "INSERT INTO invoices (invoice_number, file_path, original_filename, file_type, file_size, upload_date, upload_user_id, entry_date, supplier, notes) VALUES (:invoice_number, :file_path, :original_filename, :file_type, :file_size, NOW(), :upload_user_id, CURDATE(), :supplier, :notes)"
    );
    $stmt->execute([
        ':invoice_number' => $invoice['invoice_number'] ?? null,
        ':file_path' => $invoice['file_path'],
        ':original_filename' => $invoice['original_filename'],
        ':file_type' => $invoice['file_type'],
        ':file_size' => $invoice['file_size'],
        ':upload_user_id' => $invoice['upload_user_id'] ?? null,
        ':supplier' => $invoice['supplier'] ?? null,
        ':notes' => $invoice['notes'] ?? null
    ]);

    $invoiceId = $pdo->lastInsertId();

    $update = $pdo->prepare("UPDATE stock_movement SET invoice_id = :invoice_id WHERE id = :movement_id");
    $update->execute([':invoice_id' => $invoiceId, ':movement_id' => $movementId]);

    $pdo->commit();

    // Journalisation de l'action
    if ($logger) {
        if ($replaceExisting || $existingInvoiceId) {
            $logger->logInvoiceReplace($invoiceId, $movementId, $existingInvoiceId, $invoice['original_filename'] ?? null);
        } else {
            $logger->logInvoiceAssociate($invoiceId, $movementId, $invoice['original_filename'] ?? null);
        }
    }

    echo json_encode(['success' => true, 'invoice_id' => $invoiceId]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
