<?php
/**
 * Sauvegarde des informations du demandeur
 * 
 * @package DYM_MANUFACTURE
 * @subpackage api/besoins
 * @version 2.0
 */

// Connexion à la base de données
include_once '../../../database/connection.php';

// Configuration des en-têtes
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Fonction pour logger les erreurs
function logError($message, $data = null) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ];
    
    error_log("SAVE_DEMANDEUR_ERROR: " . json_encode($logData));
}

// Fonction pour valider les services autorisés
function validateService($service) {
    $allowedServices = [
        'BUREAU D\'ETUDE',
        'SERVICE ACHAT',
        'SERVICE FINANCE',
        'SERVICE STOCK',
        'SERVICE INFORMATIQUE'
    ];
    
    return in_array(trim($service), $allowedServices);
}

try {
    // Vérification de la méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée. Utilisez POST.');
    }
    
    // Récupération et validation des données JSON
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('Aucune donnée reçue dans la requête');
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Données JSON invalides: ' . json_last_error_msg());
    }
    
    // Validation des champs requis
    $requiredFields = ['idBesoin', 'service_demandeur', 'nom_prenoms', 'date_demande', 'motif_demande'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            throw new Exception("Le champ '$field' est requis");
        }
    }
    
    // Nettoyage et validation des données
    $idBesoin = trim($data['idBesoin']);
    $serviceDemandeur = trim($data['service_demandeur']);
    $nomPrenoms = trim($data['nom_prenoms']);
    $dateDemande = trim($data['date_demande']);
    $motifDemande = trim($data['motif_demande']);
    
    // Validations spécifiques
    if (strlen($idBesoin) < 5) {
        throw new Exception('L\'ID de besoin doit contenir au moins 5 caractères');
    }
    
    if (!validateService($serviceDemandeur)) {
        throw new Exception('Service demandeur non valide. Services autorisés: BUREAU D\'ETUDE, SERVICE ACHAT, SERVICE FINANCE, SERVICE STOCK, SERVICE INFORMATIQUE');
    }
    
    if (strlen($nomPrenoms) < 2) {
        throw new Exception('Le nom et prénoms doivent contenir au moins 2 caractères');
    }
    
    if (!DateTime::createFromFormat('Y-m-d', $dateDemande)) {
        throw new Exception('Format de date invalide (attendu: YYYY-MM-DD)');
    }
    
    if (strlen($motifDemande) < 10) {
        throw new Exception('Le motif de la demande doit contenir au moins 10 caractères');
    }
    
    // Vérification de l'unicité de l'idBesoin
    $checkQuery = "SELECT id FROM demandeur WHERE idBesoin = :idBesoin";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([':idBesoin' => $idBesoin]);
    
    if ($checkStmt->fetch()) {
        throw new Exception('Cet ID de besoin existe déjà');
    }
    
    // Préparation de la requête d'insertion
    $insertQuery = "
        INSERT INTO demandeur (
            idBesoin, 
            service_demandeur, 
            nom_prenoms, 
            client, 
            date_demande, 
            motif_demande,
            created_at
        ) VALUES (
            :idBesoin, 
            :service_demandeur, 
            :nom_prenoms, 
            'DYM FONCTIONNEMENT', 
            :date_demande, 
            :motif_demande,
            NOW()
        )
    ";
    
    // Début de la transaction
    $pdo->beginTransaction();
    
    // Exécution de l'insertion
    $stmt = $pdo->prepare($insertQuery);
    $success = $stmt->execute([
        ':idBesoin' => $idBesoin,
        ':service_demandeur' => $serviceDemandeur,
        ':nom_prenoms' => $nomPrenoms,
        ':date_demande' => $dateDemande,
        ':motif_demande' => $motifDemande
    ]);
    
    if (!$success) {
        throw new Exception('Erreur lors de l\'insertion: ' . implode(', ', $stmt->errorInfo()));
    }
    
    $insertedId = $pdo->lastInsertId();
    
    // Commit de la transaction
    $pdo->commit();
    
    // Log de succès
    error_log("SAVE_DEMANDEUR_SUCCESS: Demandeur enregistré avec succès - ID: $insertedId");
    
    // Réponse de succès
    echo json_encode([
        'success' => true,
        'message' => 'Demandeur enregistré avec succès',
        'data' => [
            'idDemandeur' => $insertedId,
            'idBesoin' => $idBesoin,
            'service' => $serviceDemandeur,
            'nom_prenoms' => $nomPrenoms
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Rollback en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log de l'erreur
    logError("Erreur lors de la sauvegarde du demandeur", [
        'error' => $e->getMessage(),
        'data' => $data ?? null
    ]);
    
    // Réponse d'erreur
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'SAVE_DEMANDEUR_ERROR'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Rollback en cas d'erreur PDO
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log de l'erreur PDO
    logError("Erreur PDO lors de la sauvegarde du demandeur", [
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    // Réponse d'erreur
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données lors de l\'enregistrement',
        'error_code' => 'DATABASE_ERROR'
    ], JSON_UNESCAPED_UNICODE);
}
?>