<?php
session_start();
header('Content-Type: application/json');

// Activer les journaux d'erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 0); // Ne pas afficher les erreurs dans la réponse JSON
ini_set('log_errors', 1);

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Récupérer l'ID du besoin
$besoinId = $_GET['id'] ?? '';

if (empty($besoinId)) {
    error_log("get_besoin_with_remaining: ID de besoin non spécifié");
    echo json_encode(['success' => false, 'message' => 'ID de besoin non spécifié']);
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Récupérer les informations du besoin
    $query = "SELECT b.*, 
              CONCAT('SYS-', COALESCE(d.service_demandeur, 'Système')) as code_projet,
              COALESCE(d.client, 'Demande interne') as nom_client,
              (b.qt_demande - b.qt_acheter) as qt_restante,
              b.qt_demande as initial_qt_acheter
              FROM besoins b
              LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
              WHERE b.id = :id
              AND b.qt_demande > b.qt_acheter
              AND b.achat_status = 'en_cours'";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $besoinId);
    $stmt->execute();

    $besoin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($besoin) {
        error_log("get_besoin_with_remaining: Besoin trouvé: " . json_encode($besoin));

        // Récupérer la dernière commande liée pour avoir le prix et le fournisseur
        $linkedQuery = "SELECT am.prix_unitaire, am.fournisseur, am.date_achat
                      FROM achats_materiaux am
                      WHERE am.expression_id = :expression_id
                      AND am.designation = :designation
                      AND am.is_partial = 1
                      ORDER BY am.date_achat DESC
                      LIMIT 1";

        $linkedStmt = $pdo->prepare($linkedQuery);
        $linkedStmt->bindParam(':expression_id', $besoin['idBesoin']);
        $linkedStmt->bindParam(':designation', $besoin['designation_article']);
        $linkedStmt->execute();
        $linked = $linkedStmt->fetch(PDO::FETCH_ASSOC);

        // Si on n'a pas trouvé de prix dans les commandes liées, chercher un prix similaire
        if (!$linked || empty($linked['prix_unitaire'])) {
            $prixQuery = "SELECT prix_unitaire 
                         FROM achats_materiaux 
                         WHERE designation = :designation 
                         AND prix_unitaire > 0
                         ORDER BY date_achat DESC 
                         LIMIT 1";

            $prixStmt = $pdo->prepare($prixQuery);
            $prixStmt->bindParam(':designation', $besoin['designation_article']);
            $prixStmt->execute();
            $prix = $prixStmt->fetch(PDO::FETCH_ASSOC);

            if ($prix && !empty($prix['prix_unitaire'])) {
                $linked['prix_unitaire'] = $prix['prix_unitaire'];
                error_log("get_besoin_with_remaining: Prix trouvé: " . $linked['prix_unitaire']);
            }
        }

        // Combiner les informations
        $result = [
            'success' => true,
            'id' => $besoin['id'],
            'designation' => $besoin['designation_article'],
            'qt_restante' => $besoin['qt_restante'],
            'unit' => $besoin['caracteristique'],
            'code_projet' => $besoin['code_projet'],
            'nom_client' => $besoin['nom_client'],
            'idExpression' => $besoin['idBesoin'],
            'prix_unitaire' => $linked['prix_unitaire'] ?? '',
            'fournisseur' => $linked['fournisseur'] ?? '',
            'source_table' => 'besoins'
        ];

        echo json_encode($result);
    } else {
        error_log("get_besoin_with_remaining: Besoin non trouvé pour ID: $besoinId");
        echo json_encode(['success' => false, 'message' => 'Besoin non trouvé', 'id' => $besoinId]);
    }

} catch (PDOException $e) {
    error_log("get_besoin_with_remaining: Erreur de base de données: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>