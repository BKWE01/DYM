<?php
header('Content-Type: application/json');

// Désactiver l'affichage des erreurs pour éviter de corrompre la sortie JSON
ini_set('display_errors', 0);
error_reporting(0);

// Connexion à la base de données
include_once '../../database/connection.php';

// Inclure le helper de date si disponible
try {
    if (file_exists('../../include/date_helper.php')) {
        include_once '../../include/date_helper.php';
    } else if (file_exists('../../includes/date_helper.php')) {
        include_once '../../includes/date_helper.php';
    }
} catch (Exception $e) {
    // Ignorer si le helper n'est pas disponible
}

try {
    // Récupérer l'ID du mouvement
    $movementId = isset($_GET['movement_id']) ? intval($_GET['movement_id']) : 0;

    if ($movementId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de mouvement invalide'
        ]);
        exit;
    }

    // Récupérer les détails complets du mouvement avec informations enrichies
    $stmt = $pdo->prepare("
        SELECT 
            sm.id,
            sm.product_id,
            sm.quantity,
            sm.movement_type,
            sm.provenance,
            sm.nom_projet,
            sm.destination,
            sm.demandeur,
            sm.fournisseur,
            sm.notes,
            sm.date,
            sm.created_at,
            sm.updated_at,
            sm.invoice_id,
            -- Informations produit
            p.product_name,
            p.barcode,
            p.unit,
            p.unit_price,
            p.category as product_category_id,
            c.libelle as product_category,
            -- Informations facture si disponible
            i.invoice_number,
            i.supplier as invoice_supplier,
            i.original_filename as invoice_filename,
            -- Informations projet si disponible
            proj.nom_client as project_client,
            proj.code_projet,
            proj.description_projet,
            proj.chefprojet,
            proj.sitgeo as project_location
        FROM stock_movement sm
        LEFT JOIN products p ON sm.product_id = p.id
        LEFT JOIN categories c ON p.category = c.id
        LEFT JOIN invoices i ON sm.invoice_id = i.id
        LEFT JOIN identification_projet proj ON sm.nom_projet = proj.nom_client OR sm.nom_projet = proj.idExpression
        WHERE sm.id = :movement_id
        ORDER BY sm.created_at DESC
        LIMIT 1
    ");

    $stmt->bindParam(':movement_id', $movementId, PDO::PARAM_INT);
    $stmt->execute();

    $movement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$movement) {
        echo json_encode([
            'success' => false,
            'message' => 'Mouvement non trouvé'
        ]);
        exit;
    }

    // Enrichir les données avec des informations calculées
    $movement['formatted_date'] = formatMovementDate($movement['date']);
    $movement['formatted_created_at'] = formatMovementDate($movement['created_at']);
    $movement['movement_type_label'] = getMovementTypeLabel($movement['movement_type']);
    $movement['quantity_formatted'] = number_format($movement['quantity'], 2, ',', ' ');

    // Calculer la valeur du mouvement si le prix unitaire est disponible
    if (!empty($movement['unit_price']) && $movement['unit_price'] > 0) {
        $movement['movement_value'] = $movement['quantity'] * $movement['unit_price'];
        $movement['movement_value_formatted'] = number_format($movement['movement_value'], 2, ',', ' ') . ' FCFA';
    } else {
        $movement['movement_value'] = null;
        $movement['movement_value_formatted'] = 'N/A';
    }

    // Récupérer l'historique des mouvements similaires pour ce produit et projet (optionnel)
    if (!empty($movement['product_id']) && !empty($movement['nom_projet'])) {
        $historyStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_movements,
                SUM(CASE WHEN movement_type = 'input' THEN quantity ELSE 0 END) as total_inputs,
                SUM(CASE WHEN movement_type = 'output' THEN quantity ELSE 0 END) as total_outputs,
                MIN(date) as first_movement,
                MAX(date) as last_movement
            FROM stock_movement 
            WHERE product_id = :product_id 
            AND nom_projet = :nom_projet
            AND id != :current_movement_id
        ");

        $historyStmt->bindParam(':product_id', $movement['product_id']);
        $historyStmt->bindParam(':nom_projet', $movement['nom_projet']);
        $historyStmt->bindParam(':current_movement_id', $movementId);
        $historyStmt->execute();

        $history = $historyStmt->fetch(PDO::FETCH_ASSOC);
        $movement['related_movements'] = $history;
    }

    // Récupérer les détails de la facture si disponible
    if (!empty($movement['invoice_id'])) {
        $invoiceStmt = $pdo->prepare("
            SELECT 
                id,
                invoice_number,
                file_path,
                original_filename,
                supplier,
                upload_date,
                entry_date,
                notes as invoice_notes
            FROM invoices 
            WHERE id = :invoice_id
        ");

        $invoiceStmt->bindParam(':invoice_id', $movement['invoice_id']);
        $invoiceStmt->execute();

        $invoiceDetails = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
        $movement['invoice_details'] = $invoiceDetails ?: null;
    }

    // Essayer de récupérer des informations utilisateur basées sur le demandeur
    if (!empty($movement['demandeur'])) {
        $userStmt = $pdo->prepare("
            SELECT 
                id as user_id,
                name as user_name,
                email as user_email,
                user_type
            FROM users_exp 
            WHERE name = :demandeur
            LIMIT 1
        ");
        
        $userStmt->bindParam(':demandeur', $movement['demandeur']);
        $userStmt->execute();
        
        $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($userInfo) {
            $movement['user_id'] = $userInfo['user_id'];
            $movement['user_name'] = $userInfo['user_name'];
            $movement['user_email'] = $userInfo['user_email'];
            $movement['user_type'] = $userInfo['user_type'];
        } else {
            // Valeurs par défaut si l'utilisateur n'est pas trouvé
            $movement['user_id'] = null;
            $movement['user_name'] = $movement['demandeur'] ?: 'Système';
            $movement['user_email'] = null;
            $movement['user_type'] = 'N/A';
        }
    } else {
        // Valeurs par défaut si pas de demandeur
        $movement['user_id'] = null;
        $movement['user_name'] = 'Système';
        $movement['user_email'] = null;
        $movement['user_type'] = 'Système';
    }

    // Nettoyer les données NULL
    $movement = array_map(function ($value) {
        return $value === null ? '' : $value;
    }, $movement);

    echo json_encode([
        'success' => true,
        'movement' => $movement,
        'generated_at' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log("Erreur PDO dans api_getMovementDetails.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Erreur dans api_getMovementDetails.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}

// ==========================================
// FONCTIONS UTILITAIRES
// ==========================================

/**
 * Formatage des dates pour l'affichage
 */
function formatMovementDate($dateString)
{
    if (empty($dateString)) return 'N/A';

    try {
        $date = new DateTime($dateString);
        return $date->format('d/m/Y à H:i');
    } catch (Exception $e) {
        return $dateString;
    }
}

/**
 * Libellés des types de mouvements
 */
function getMovementTypeLabel($type)
{
    $labels = [
        'input' => 'Entrée en stock',
        'entry' => 'Entrée en stock', // Alias pour input
        'output' => 'Sortie de stock',
        'adjustment' => 'Ajustement de stock',
        'return' => 'Retour de matériel',
        'transfer' => 'Transfert entre projets',
        'correction' => 'Correction de stock',
        'inventory' => 'Inventaire'
    ];

    return isset($labels[$type]) ? $labels[$type] : ucfirst($type);
}
?>