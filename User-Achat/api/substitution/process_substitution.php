<?php
/**
 * API pour gérer la substitution de produits - Version étendue avec gestion des tables multiples
 */

session_start();
include_once '../../../database/connection.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit(json_encode(['success' => false, 'message' => 'Non autorisé']));
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 405 Method Not Allowed");
    exit(json_encode(['success' => false, 'message' => 'Méthode non autorisée']));
}

// Récupérer les données du formulaire
$materialId = $_POST['material_id'] ?? null;
$expressionId = $_POST['expression_id'] ?? null;
$substituteProduct = trim($_POST['substitute_product'] ?? '');
$substitutionReason = $_POST['substitution_reason'] ?? '';
$otherReason = trim($_POST['other_reason'] ?? '');
$sourceTable = $_POST['source_table'] ?? 'expression_dym'; // Valeur par défaut : expression_dym

// Journal de débogage
error_log("Substitution - Source: $sourceTable, ID: $materialId, Expression: $expressionId, Nouveau produit: $substituteProduct");

// Validation des données adaptée pour gérer les différentes sources
$isValid = false;

// Validation spécifique à chaque source
if ($sourceTable === 'besoins') {
    $isValid = !empty($materialId) && !empty($substituteProduct);
} else {
    $isValid = !empty($materialId) && !empty($expressionId) && !empty($substituteProduct);
}

if (!$isValid) {
    error_log("Validation échouée - Source: $sourceTable, ID: $materialId, Expression: $expressionId, Produit: $substituteProduct");
    exit(json_encode(['success' => false, 'message' => 'Données manquantes']));
}

try {
    // Étape 1 : Vérifier si le produit de substitution existe dans la table products
    $checkProductStmt = $pdo->prepare("SELECT * FROM products WHERE product_name = ?");
    $checkProductStmt->execute([$substituteProduct]);
    $substituteProductData = $checkProductStmt->fetch(PDO::FETCH_ASSOC);

    // Si le produit n'existe pas, bloquer la substitution
    if (!$substituteProductData) {
        exit(json_encode([
            'success' => false,
            'message' => 'Le produit de substitution n\'existe pas dans la base de données. Veuillez l\'ajouter d\'abord dans la liste des produits.'
        ]));
    }

    $pdo->beginTransaction();

    $originalDesignation = '';
    $originalQuantityReserved = 0;
    $originalUnit = 'unité';

    // En fonction de la source, récupérer les informations du matériau original
    if ($sourceTable === 'besoins') {
        // Récupérer les informations du besoin
        $stmt = $pdo->prepare("SELECT * FROM besoins WHERE id = ?");
        $stmt->execute([$materialId]);
        $originalMaterial = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$originalMaterial) {
            throw new Exception("Matériau introuvable dans la table besoins");
        }

        // Mise à jour pour obtenir l'expression ID s'il n'est pas fourni
        if (empty($expressionId)) {
            $expressionId = $originalMaterial['idBesoin'];
        }

        $originalDesignation = $originalMaterial['designation_article'];
        $originalQuantityReserved = (float) $originalMaterial['quantity_reserved'];
        // Pour besoins, la quantité réservée est qt_acheter
        $originalQuantityReserved = (float) ($originalMaterial['qt_acheter'] ?: 0);
        $originalUnit = $originalMaterial['caracteristique'] ?? 'unité';

    } else {
        // Récupérer les informations de l'expression_dym
        $stmt = $pdo->prepare("SELECT * FROM expression_dym WHERE id = ? AND idExpression = ?");
        $stmt->execute([$materialId, $expressionId]);
        $originalMaterial = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$originalMaterial) {
            throw new Exception("Matériau introuvable dans la table expression_dym");
        }

        $originalDesignation = $originalMaterial['designation'];
        $originalQuantityReserved = (float) $originalMaterial['quantity_reserved'];
        if ($originalQuantityReserved <= 0) {
            // Utiliser qt_acheter si quantity_reserved n'est pas défini
            $originalQuantityReserved = (float) ($originalMaterial['qt_acheter'] ?: 0);
        }
        $originalUnit = $originalMaterial['unit'] ?? 'unité';
    }

    // Vérifier qu'on a récupéré un expressionId valide
    if (empty($expressionId)) {
        throw new Exception("ID d'expression non trouvé");
    }

    // Déterminer l'unité à utiliser (celle du nouveau produit si existe, sinon garder l'ancienne)
    $newUnit = $substituteProductData['unit'] ?? $originalUnit;

    // 3. Gestion de l'ancien produit dans products (si quantity_reserved > 0)
    if ($originalQuantityReserved > 0) {
        $stmt = $pdo->prepare("SELECT id, quantity_reserved FROM products WHERE product_name = ?");
        $stmt->execute([$originalDesignation]);
        $originalProductData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($originalProductData) {
            $newReserved = max(0, (float) ($originalProductData['quantity_reserved'] ?: 0) - $originalQuantityReserved);

            $stmt = $pdo->prepare("UPDATE products 
                                 SET quantity_reserved = ?
                                 WHERE product_name = ?");
            $stmt->execute([$newReserved, $originalDesignation]);
        }
    }

    // 4. Gestion du nouveau produit dans products
    // Mise à jour quantité réservée + unité
    $stmt = $pdo->prepare("UPDATE products 
                         SET quantity_reserved = COALESCE(quantity_reserved, 0) + ?,
                             unit = ?,
                             updated_at = NOW()
                         WHERE product_name = ?");
    $stmt->execute([$originalQuantityReserved, $newUnit, $substituteProduct]);

    // 5. Mettre à jour le matériau dans la table source
    if ($sourceTable === 'besoins') {
        $stmt = $pdo->prepare("UPDATE besoins 
                              SET designation_article = ?, 
                                  caracteristique = ?,
                                  updated_at = NOW()
                              WHERE id = ?");
        $stmt->execute([$substituteProduct, $newUnit, $materialId]);
    } else {
        $stmt = $pdo->prepare("UPDATE expression_dym 
                              SET designation = ?, 
                                  unit = ?,
                                  updated_at = NOW()
                              WHERE id = ?");
        $stmt->execute([$substituteProduct, $newUnit, $materialId]);
    }

    // 6. Enregistrer l'historique de substitution (avec les unités)
    $stmt = $pdo->prepare("INSERT INTO product_substitutions 
                          (original_product, original_unit, substitute_product, substitute_unit,
                           expression_id, material_id, reason, other_reason, user_id, 
                           quantity_transferred, source_table)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $originalDesignation,
        $originalUnit,
        $substituteProduct,
        $newUnit,
        $expressionId,
        $materialId,
        $substitutionReason,
        $otherReason,
        $_SESSION['user_id'],
        $originalQuantityReserved,
        $sourceTable
    ]);

    $pdo->commit();

    // Ajouter une entrée dans le journal système
    $logQuery = "INSERT INTO system_logs (user_id, action, type, entity_id, entity_name, details, ip_address) 
                VALUES (?, 'substitution', ?, ?, ?, ?, ?)";
    $logStmt = $pdo->prepare($logQuery);
    $logStmt->execute([
        $_SESSION['user_id'],
        $sourceTable,
        $materialId,
        "De: $originalDesignation à: $substituteProduct",
        "Raison: " . ($substitutionReason === 'autre' ? $otherReason : $substitutionReason),
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ]);

    // Réponse avec toutes les infos
    echo json_encode([
        'success' => true,
        'message' => 'Substitution effectuée avec succès',
        'data' => [
            'original_product' => $originalDesignation,
            'original_unit' => $originalUnit,
            'new_product' => $substituteProduct,
            'new_unit' => $newUnit,
            'quantity_transferred' => $originalQuantityReserved,
            'source_table' => $sourceTable
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur de substitution: " . $e->getMessage() . " - " . $e->getTraceAsString());
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la substitution: ' . $e->getMessage(),
        'error_details' => [
            'original_product' => $originalDesignation ?? 'N/A',
            'new_product' => $substituteProduct,
            'original_unit' => $originalUnit ?? 'N/A',
            'source_table' => $sourceTable,
            'expression_id' => $expressionId ?? 'Non trouvé'
        ]
    ]);
}