<?php
header('Content-Type: application/json');
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé. Vous devez être connecté.']);
    exit();
}

// Vérifier si l'ID du log est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID du log non fourni.']);
    exit();
}

// Connexion à la base de données
include_once dirname(__DIR__) . '/../../database/connection.php';

try {
    $logId = (int) $_GET['id'];

    // Récupérer les détails du log
    $stmt = $pdo->prepare("SELECT * FROM system_logs WHERE id = :id");
    $stmt->execute([':id' => $logId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Log non trouvé.']);
        exit();
    }

    // Renvoyer les détails du log
    echo json_encode(['success' => true, 'log' => $log]);

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