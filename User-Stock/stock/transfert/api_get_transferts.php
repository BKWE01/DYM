<?php
header('Content-Type: application/json');
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette ressource.'
    ]);
    exit;
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Paramètres de pagination
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $itemsPerPage;

    // Filtres
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $productFilter = isset($_GET['product']) ? $_GET['product'] : '';
    $dateFilter = isset($_GET['date']) ? $_GET['date'] : '';

    // Construction de la requête de base
    $query = "
        SELECT t.*, 
               p.product_name, p.barcode,
               sp.nom_client AS source_project, sp.code_projet AS source_project_code,
               dp.nom_client AS destination_project, dp.code_projet AS destination_project_code,
               u.name AS requested_by_name
        FROM transferts t
        LEFT JOIN products p ON t.product_id = p.id
        LEFT JOIN identification_projet sp ON t.source_project_id = sp.id
        LEFT JOIN identification_projet dp ON t.destination_project_id = dp.id
        LEFT JOIN users_exp u ON t.requested_by = u.id
        WHERE 1=1";
    
    $countQuery = "SELECT COUNT(*) FROM transferts t 
                  LEFT JOIN products p ON t.product_id = p.id
                  LEFT JOIN identification_projet sp ON t.source_project_id = sp.id
                  LEFT JOIN identification_projet dp ON t.destination_project_id = dp.id 
                  WHERE 1=1";
    
    $params = [];

    // Ajouter les filtres
    if ($status !== 'all') {
        $query .= " AND t.status = :status";
        $countQuery .= " AND t.status = :status";
        $params[':status'] = $status;
    }
    
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $query .= " AND (p.product_name LIKE :search 
                   OR p.barcode LIKE :search 
                   OR sp.nom_client LIKE :search 
                   OR sp.code_projet LIKE :search 
                   OR dp.nom_client LIKE :search 
                   OR dp.code_projet LIKE :search)";
        $countQuery .= " AND (p.product_name LIKE :search 
                     OR p.barcode LIKE :search 
                     OR sp.nom_client LIKE :search 
                     OR sp.code_projet LIKE :search 
                     OR dp.nom_client LIKE :search 
                     OR dp.code_projet LIKE :search)";
        $params[':search'] = $searchTerm;
    }
    
    if (!empty($productFilter)) {
        $query .= " AND t.product_id = :product_id";
        $countQuery .= " AND t.product_id = :product_id";
        $params[':product_id'] = $productFilter;
    }
    
    if (!empty($dateFilter)) {
        switch ($dateFilter) {
            case 'today':
                $query .= " AND DATE(t.created_at) = CURDATE()";
                $countQuery .= " AND DATE(t.created_at) = CURDATE()";
                break;
            case 'week':
                $query .= " AND YEARWEEK(t.created_at, 1) = YEARWEEK(CURDATE(), 1)";
                $countQuery .= " AND YEARWEEK(t.created_at, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'month':
                $query .= " AND YEAR(t.created_at) = YEAR(CURDATE()) AND MONTH(t.created_at) = MONTH(CURDATE())";
                $countQuery .= " AND YEAR(t.created_at) = YEAR(CURDATE()) AND MONTH(t.created_at) = MONTH(CURDATE())";
                break;
            case 'custom':
                if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                    $query .= " AND DATE(t.created_at) BETWEEN :start_date AND :end_date";
                    $countQuery .= " AND DATE(t.created_at) BETWEEN :start_date AND :end_date";
                    $params[':start_date'] = $_GET['start_date'];
                    $params[':end_date'] = $_GET['end_date'];
                }
                break;
        }
    }
    
    // Ajouter l'ordre et la pagination
    $query .= " ORDER BY t.created_at DESC LIMIT :offset, :limit";
    
    // Préparer et exécuter les requêtes
    $stmt = $pdo->prepare($query);
    $countStmt = $pdo->prepare($countQuery);
    
    // Lier les paramètres
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
        $countStmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    
    $stmt->execute();
    $countStmt->execute();
    
    $transferts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $countStmt->fetchColumn();
    
    // Obtenir la liste des produits pour le filtre
    $productsQuery = "
        SELECT DISTINCT p.id, p.product_name
        FROM transferts t
        JOIN products p ON t.product_id = p.id
        ORDER BY p.product_name ASC
    ";
    
    $productsStmt = $pdo->query($productsQuery);
    $products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données pour la pagination
    $totalPages = ceil($total / $itemsPerPage);
    $pagination = [
        'total' => $total,
        'per_page' => $itemsPerPage,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'start' => $offset + 1,
        'end' => min($offset + $itemsPerPage, $total)
    ];
    
    // Préparer la réponse
    foreach ($transferts as &$transfert) {
        $transfert['requested_by'] = $transfert['requested_by_name'] ?? 'Utilisateur inconnu';
        unset($transfert['requested_by_name']);
    }
    
    echo json_encode([
        'success' => true,
        'transferts' => $transferts,
        'pagination' => $pagination,
        'products' => $products
    ]);
    
} catch (PDOException $e) {
    // Journaliser l'erreur
    error_log('Erreur dans api_get_transferts.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la récupération des transferts: ' . $e->getMessage()
    ]);
}