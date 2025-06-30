<?php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non connecté'
    ]);
    exit;
}

// Vérifier si l'ID du produit est fourni
if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID du produit non fourni'
    ]);
    exit;
}

$productId = intval($_GET['product_id']);

require_once '../../../../database/connection.php';

try {
    // Récupérer les détails du produit
    $sqlProduct = "SELECT * FROM products WHERE id = :id";
    $stmtProduct = $pdo->prepare($sqlProduct);
    $stmtProduct->execute(['id' => $productId]);
    $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé'
        ]);
        exit;
    }

    // Récupérer les réservations de la table expression_dym pour ce produit
    $sqlReservationsED = "SELECT 
                            ed.id AS reservation_id,
                            ed.quantity_reserved AS reserved_quantity,
                            ed.created_at,
                            ip.id AS project_id,
                            ip.code_projet AS project_code,
                            ip.nom_client AS project_name,
                            p.quantity AS total_quantity,
                            'expression_dym' AS source_table
                        FROM 
                            expression_dym ed
                        JOIN 
                            identification_projet ip ON ed.idExpression = ip.idExpression
                        JOIN 
                            products p ON LOWER(ed.designation) = LOWER(p.product_name)
                        WHERE 
                            p.id = :product_id
                            AND ed.quantity_reserved > 0
                        ORDER BY 
                            ed.created_at DESC";

    $stmtReservationsED = $pdo->prepare($sqlReservationsED);
    $stmtReservationsED->execute(['product_id' => $productId]);
    $reservationsED = $stmtReservationsED->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les réservations de la table besoins pour ce produit
    $sqlReservationsB = "SELECT 
                            b.id AS reservation_id,
                            b.quantity_reserved AS reserved_quantity,
                            b.created_at,
                            'SYS' AS project_code,
                            CASE 
                                WHEN d.client IS NOT NULL THEN CONCAT('Demande ', d.client)
                                ELSE 'Demande Système'
                            END AS project_name,
                            b.id AS project_id,
                            p.quantity AS total_quantity,
                            'besoins' AS source_table
                        FROM 
                            besoins b
                        LEFT JOIN 
                            demandeur d ON b.idBesoin = d.idBesoin
                        JOIN 
                            products p ON b.product_id = p.id
                        WHERE 
                            p.id = :product_id
                            AND b.quantity_reserved > 0
                        ORDER BY 
                            b.created_at DESC";

    $stmtReservationsB = $pdo->prepare($sqlReservationsB);
    $stmtReservationsB->execute(['product_id' => $productId]);
    $reservationsB = $stmtReservationsB->fetchAll(PDO::FETCH_ASSOC);

    // Fusionner les deux ensembles de réservations
    $reservations = array_merge($reservationsED, $reservationsB);

    // Calculer le stock disponible
    $totalReserved = 0;
    foreach ($reservations as $reservation) {
        $totalReserved += $reservation['reserved_quantity'];
    }
    
    $availableQuantity = $product['quantity'] - $totalReserved;
    $availableQuantity = max(0, $availableQuantity);

    // Ajouter le statut à chaque réservation
    foreach ($reservations as &$reservation) {
        if ($availableQuantity >= $reservation['reserved_quantity']) {
            $reservation['status'] = 'available';
        } elseif ($availableQuantity > 0) {
            $reservation['status'] = 'partial';
        } else {
            $reservation['status'] = 'unavailable';
        }
        
        // Formater la date
        if (isset($reservation['created_at'])) {
            $date = new DateTime($reservation['created_at']);
            $reservation['created_at'] = $date->format('d/m/Y H:i');
        }
    }

    echo json_encode([
        'success' => true,
        'product' => $product,
        'reservations' => $reservations,
        'total_reserved' => $totalReserved,
        'available_quantity' => $availableQuantity
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}