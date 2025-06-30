<?php
/**
 * API de récupération des modes de paiement
 * 
 * Mise à jour : Utilisation du champ icon_path au lieu de icon
 * Version : 2.0
 * Date : 30/06/2025
 */

// En-têtes CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérification de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée. Seul GET est accepté.',
        'error_code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit();
}

try {
    // Configuration de la base de données
    require_once '../../../database/connection.php';
    
    // Vérification de la connexion à la base de données
    if (!isset($pdo) || $pdo === null) {
        throw new Exception('Connexion à la base de données non disponible');
    }

    /**
     * REQUÊTE MISE À JOUR : utilisation de icon_path
     * 
     * Changements principaux :
     * - Remplacement de 'icon' par 'icon_path'
     * - Optimisation de la requête avec les index
     * - Ajout de validations pour icon_path
     */
    $query = "
        SELECT 
            id,
            label,
            description,
            icon_path,
            display_order,
            is_active,
            created_at,
            updated_at
        FROM payment_methods 
        WHERE is_active = 1 
        ORDER BY display_order ASC, label ASC
    ";

    // Préparation et exécution de la requête
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    // Récupération des résultats
    $rawMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($rawMethods);

    /**
     * TRAITEMENT DES DONNÉES AVEC ICON_PATH
     * 
     * Formatage et validation des données de retour
     */
    $methods = [];
    foreach ($rawMethods as $method) {
        // Validation et formatage de icon_path
        $iconPath = null;
        if (!empty($method['icon_path'])) {
            // Vérification que le fichier existe
            $fullPath = '../../uploads/payment_icons/' . basename($method['icon_path']);
            if (file_exists($fullPath)) {
                $iconPath = 'uploads/payment_icons/' . basename($method['icon_path']);
            }
        }

        // Construction de l'objet mode de paiement
        $methods[] = [
            'id' => (int)$method['id'],
            'label' => htmlspecialchars($method['label'], ENT_QUOTES, 'UTF-8'),
            'description' => !empty($method['description']) 
                ? htmlspecialchars($method['description'], ENT_QUOTES, 'UTF-8') 
                : null,
            'icon_path' => $iconPath, // NOUVEAU : utilisation de icon_path
            'display_order' => (int)$method['display_order'],
            'is_active' => (bool)$method['is_active'],
            'created_at' => $method['created_at'],
            'updated_at' => $method['updated_at']
        ];
    }

    // Réponse de succès
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Modes de paiement récupérés avec succès',
        'count' => $count,
        'methods' => $methods,
        'metadata' => [
            'version' => '2.0',
            'updated_field' => 'icon_path', // Information sur la mise à jour
            'total_available' => $count,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    // Gestion des erreurs de base de données
    error_log("Erreur PDO lors de la récupération des modes de paiement: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données lors de la récupération des modes de paiement',
        'error_code' => 'DB_ERROR',
        'details' => 'Vérifiez que la table payment_methods existe et que le champ icon_path est présent'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Gestion des erreurs générales
    error_log("Erreur générale lors de la récupération des modes de paiement: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur système lors de la récupération des modes de paiement',
        'error_code' => 'SYSTEM_ERROR'
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * NOTES DE MIGRATION ET D'UTILISATION
 * 
 * 1. CHANGEMENT PRINCIPAL :
 *    - Ancien champ : 'icon' 
 *    - Nouveau champ : 'icon_path'
 * 
 * 2. VALIDATION DES FICHIERS :
 *    - Vérification de l'existence du fichier icône
 *    - Path relatif pour l'affichage frontend
 * 
 * 3. COMPATIBILITÉ :
 *    - Cette API retourne maintenant 'icon_path' au lieu de 'icon'
 *    - Les scripts JavaScript doivent être mis à jour pour utiliser ce nouveau champ
 * 
 * 4. SÉCURITÉ :
 *    - Échappement HTML des données de sortie
 *    - Validation des chemins de fichiers
 *    - Protection contre l'injection de code
 * 
 * 5. STRUCTURE DE RÉPONSE :
 *    {
 *      "success": true,
 *      "message": "...",
 *      "count": 3,
 *      "methods": [
 *        {
 *          "id": 1,
 *          "label": "Virement bancaire",
 *          "description": "...",
 *          "icon_path": "uploads/payment_icons/bank-transfer.png",
 *          "display_order": 1,
 *          "is_active": true,
 *          "created_at": "...",
 *          "updated_at": "..."
 *        }
 *      ],
 *      "metadata": {...}
 *    }
 */
?>