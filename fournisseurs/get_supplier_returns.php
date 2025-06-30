<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si le fournisseur est fourni
if (!isset($_GET['supplier']) && !isset($_GET['supplier_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Paramètre fournisseur manquant']);
    exit();
}

// Connexion à la base de données
include_once '../database/connection.php';

try {
    $supplier = isset($_GET['supplier']) ? $_GET['supplier'] : null;
    $supplier_id = isset($_GET['supplier_id']) ? $_GET['supplier_id'] : null;
    
    // Vérifier si la table supplier_returns existe
    $tableExists = false;
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'supplier_returns'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
    
    $returns = [];
    
    if ($tableExists) {
        // Si la table supplier_returns existe
        if ($supplier_id) {
            // Recherche par ID du fournisseur
            $query = "SELECT sr.*, p.product_name 
                      FROM supplier_returns sr
                      JOIN products p ON sr.product_id = p.id
                      WHERE sr.supplier_id = :supplier_id
                      ORDER BY sr.created_at DESC";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':supplier_id', $supplier_id);
        } else {
            // Recherche par nom du fournisseur
            $query = "SELECT sr.*, p.product_name 
                      FROM supplier_returns sr
                      JOIN products p ON sr.product_id = p.id
                      WHERE sr.supplier_name = :supplier
                      ORDER BY sr.created_at DESC";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':supplier', $supplier);
        }
        
        $stmt->execute();
        $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Si la table supplier_returns n'existe pas ou si aucun retour n'a été trouvé,
    // rechercher dans la table stock_movement
    if (!$tableExists || empty($returns)) {
        $condition = $supplier_id 
            ? "JOIN fournisseurs f ON sm.destination LIKE CONCAT('Retour fournisseur: ', f.nom) WHERE f.id = :condition_value" 
            : "WHERE sm.destination = :condition_value";
        $condition_value = $supplier_id ? $supplier_id : "Retour fournisseur: $supplier";
        
        $query = "SELECT 
                    sm.id, 
                    sm.product_id, 
                    p.product_name, 
                    sm.quantity, 
                    sm.notes,
                    sm.date as created_at,
                    SUBSTRING(sm.destination, 20) as supplier_name,
                    'pending' as status
                  FROM 
                    stock_movement sm
                  JOIN 
                    products p ON sm.product_id = p.id
                  $condition";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':condition_value', $condition_value);
        $stmt->execute();
        $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Analyser les notes pour extraire le motif et les commentaires
    foreach ($returns as &$return) {
        if (isset($return['notes']) && !empty($return['notes'])) {
            if (strpos($return['notes'], 'Motif:') === 0) {
                $notesContent = substr($return['notes'], strlen('Motif:'));
                $parts = explode(' - ', $notesContent);
                $return['reason'] = trim($parts[0]);
                if (count($parts) > 1) {
                    $return['comment'] = trim($parts[1]);
                }
            }
        }
        
        // Si le statut n'est pas défini, définir à "pending" par défaut
        if (!isset($return['status'])) {
            $return['status'] = 'pending';
        }
    }
    
    // Calculer des statistiques de base
    $stats = [
        'total_returns' => count($returns),
        'total_quantity' => 0,
        'reasons' => []
    ];
    
    foreach ($returns as $return) {
        $stats['total_quantity'] += $return['quantity'];
        
        // Compter les occurrences de chaque motif
        $reason = isset($return['reason']) ? $return['reason'] : 'Non spécifié';
        if (!isset($stats['reasons'][$reason])) {
            $stats['reasons'][$reason] = 0;
        }
        $stats['reasons'][$reason]++;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'returns' => $returns,
        'stats' => $stats
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}