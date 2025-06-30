<?php
/**
 * API pour récupérer les détails d'une commande partielle de besoin système
 * Fichier : /commandes-traitement/besoins/get_besoin_partial_details.php
 * Version corrigée basée sur l'ancien fichier fonctionnel
 */

session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Récupérer l'ID du besoin
$id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID du besoin manquant']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // ========================================
    // 1. RÉCUPÉRER LES INFORMATIONS DU BESOIN
    // ========================================
    // Utilisation de la requête de l'ancien fichier qui fonctionnait
    $query = "SELECT b.*,
                     CONCAT('SYS-', COALESCE(d.service_demandeur, 'Système')) as code_projet,
                     COALESCE(d.client, 'Demande interne') as nom_client,
                     (b.qt_demande - b.qt_acheter) as qt_restante
              FROM besoins b
              LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
              WHERE b.id = :id";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $besoin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$besoin) {
        echo json_encode(['success' => false, 'message' => 'Besoin non trouvé']);
        exit();
    }

    // ========================================
    // 2. RÉCUPÉRER LES COMMANDES LIÉES AVEC MODES DE PAIEMENT
    // ========================================
    // MODIFICATION PRINCIPALE : Ajout du champ mode_paiement_id et autres infos utiles
    $linkedQuery = "SELECT am.id,
                          am.expression_id,
                          am.designation,
                          am.quantity,
                          am.unit,
                          am.prix_unitaire,
                          am.fournisseur,
                          am.status,
                          am.mode_paiement_id,
                          am.is_partial,
                          am.original_quantity,
                          am.parent_id,
                          am.user_achat,
                          am.date_achat,
                          am.created_at,
                          am.updated_at
                  FROM achats_materiaux am
                  WHERE am.expression_id = :expression_id
                  AND am.designation = :designation
                  AND am.is_partial = 1
                  ORDER BY am.date_achat DESC";

    $linkedStmt = $pdo->prepare($linkedQuery);
    $linkedStmt->bindParam(':expression_id', $besoin['idBesoin']);
    $linkedStmt->bindParam(':designation', $besoin['designation_article']);
    $linkedStmt->execute();
    $linked_orders = $linkedStmt->fetchAll(PDO::FETCH_ASSOC);

    // ========================================
    // 3. ENRICHIR LES DONNÉES (OPTIONNEL)
    // ========================================
    // Ajouter des informations formatées pour un meilleur affichage
    foreach ($linked_orders as &$order) {
        // Formater la date
        if (!empty($order['date_achat'])) {
            $order['date_achat_formatted'] = date('d/m/Y H:i', strtotime($order['date_achat']));
        }
        
        // Calculer le montant total de cette commande
        $order['total_amount'] = floatval($order['quantity']) * floatval($order['prix_unitaire']);
        
        // S'assurer que mode_paiement_id n'est pas null
        $order['mode_paiement_id'] = $order['mode_paiement_id'] ?? '';
        
        // S'assurer que fournisseur n'est pas null
        $order['fournisseur'] = $order['fournisseur'] ?? '';
    }

    // ========================================
    // 4. RETOURNER LE RÉSULTAT
    // ========================================
    // Format identique à l'ancien fichier pour compatibilité
    echo json_encode([
        'success' => true,
        'material' => $besoin,
        'linked_orders' => $linked_orders
    ]);

} catch (PDOException $e) {
    // Gestion d'erreur identique à l'ancien fichier
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>