<?php
// Script de correction pour les transferts dont les quantités n'ont pas été correctement mises à jour
session_start();

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
// if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
//     header("Location: ./../index.php");
//     exit();
// }

// Connexion à la base de données
include_once '../../../../database/connection.php';

// Variables pour le rapport
$log = [];
$errors = 0;
$fixes = 0;
$checked = 0;

// Fonction pour ajouter un message au log
function addLog($message, $type = 'info')
{
    global $log;
    $log[] = [
        'message' => $message,
        'type' => $type,
        'time' => date('H:i:s')
    ];
}

// Mode d'exécution (dry-run par défaut, fix pour corriger)
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'dry-run';
$transfertId = isset($_GET['transfert_id']) ? intval($_GET['transfert_id']) : 0;

try {
    // 1. Récupérer les transferts à vérifier
    $whereClause = $transfertId > 0 ? "WHERE t.id = :transfert_id" : "WHERE t.status = 'completed'";

    $transfertsQuery = "
        SELECT t.*, 
               p.product_name,
               sp.idExpression AS source_expression_id,
               dp.idExpression AS destination_expression_id,
               sp.code_projet AS source_code,
               dp.code_projet AS destination_code
        FROM transferts t
        JOIN products p ON t.product_id = p.id
        JOIN identification_projet sp ON t.source_project_id = sp.id
        JOIN identification_projet dp ON t.destination_project_id = dp.id
        $whereClause
        ORDER BY t.completed_at DESC
    ";

    $transfertsStmt = $pdo->prepare($transfertsQuery);
    if ($transfertId > 0) {
        $transfertsStmt->bindParam(':transfert_id', $transfertId);
    }
    $transfertsStmt->execute();
    $transferts = $transfertsStmt->fetchAll(PDO::FETCH_ASSOC);

    addLog("Début de l'analyse de " . count($transferts) . " transfert(s)");

    foreach ($transferts as $transfert) {
        $checked++;
        addLog("Vérification du transfert #{$transfert['id']} - Produit: {$transfert['product_name']}, Quantité: {$transfert['quantity']}");

        // 2. Vérifier les quantités dans le projet source (achats_materiaux)
        $sourceAchatsQuery = "
            SELECT id, quantity, status
            FROM achats_materiaux
            WHERE expression_id = :expression_id
            AND (
                LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
                OR LOWER(TRIM(designation)) LIKE CONCAT('%', LOWER(TRIM(:product_name)), '%')
            )
            AND status = 'reçu'
            ORDER BY date_achat ASC
        ";

        $sourceAchatsStmt = $pdo->prepare($sourceAchatsQuery);
        $sourceAchatsStmt->execute([
            'expression_id' => $transfert['source_expression_id'],
            'product_name' => $transfert['product_name']
        ]);
        $sourceAchats = $sourceAchatsStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalSourceQuantity = 0;
        foreach ($sourceAchats as $achat) {
            $totalSourceQuantity += floatval($achat['quantity']);
        }

        addLog("Quantité totale reçue dans le projet source: $totalSourceQuantity");

        // 3. Vérifier l'entrée dans transfert_history pour voir si la quantité a été déduite correctement
        $historyQuery = "
            SELECT th.*, th.details
            FROM transfert_history th
            WHERE th.transfert_id = :transfert_id AND th.action = 'complete'
            ORDER BY th.created_at DESC
            LIMIT 1
        ";

        $historyStmt = $pdo->prepare($historyQuery);
        $historyStmt->execute(['transfert_id' => $transfert['id']]);
        $history = $historyStmt->fetch(PDO::FETCH_ASSOC);

        $detailsArray = [];
        $transfertSource = '';

        if ($history && isset($history['details'])) {
            $detailsArray = json_decode($history['details'], true);
            $transfertSource = isset($detailsArray['transfert_source']) ? $detailsArray['transfert_source'] : '';
            addLog("Source du transfert selon l'historique: $transfertSource");
        } else {
            addLog("Aucune entrée d'historique trouvée pour ce transfert", 'warning');
            $transfertSource = 'reçu'; // Supposer que la source est "reçu" par défaut
        }

        // 4. Vérifier si les quantités ont été correctement déduites
        $problem = false;

        // Vérifier si la quantité dans achats_materiaux est cohérente 
        // (devrait être inférieure à ce qu'elle était avant le transfert)
        if ($transfertSource === 'reçu') {
            // Vérifier si nous pouvons trouver des achats dont la quantité est anormalement élevée
            // On suppose que la quantité a été transférée mais pas déduite correctement

            $needsFix = false;
            $targetAchatId = null;
            $expectedQuantity = 0;

            foreach ($sourceAchats as $achat) {
                // Si un achat a une quantité supérieure à la quantité du transfert
                // et qu'il n'y a pas assez d'achats pour couvrir la quantité totale attendue
                // alors c'est probablement celui qui n'a pas été déduit correctement
                if (floatval($achat['quantity']) >= $transfert['quantity']) {
                    $targetAchatId = $achat['id'];
                    $expectedQuantity = floatval($achat['quantity']) - $transfert['quantity'];
                    $needsFix = true;
                    break;
                }
            }

            if ($needsFix) {
                addLog("Problème détecté: La quantité dans achat_materiaux id=$targetAchatId n'a pas été déduite correctement.", 'warning');

                if ($mode === 'fix') {
                    // Corriger la quantité dans l'achat
                    $updateAchatSql = "
                        UPDATE achats_materiaux
                        SET quantity = :new_quantity
                        WHERE id = :achat_id
                    ";

                    $updateAchatStmt = $pdo->prepare($updateAchatSql);
                    $result = $updateAchatStmt->execute([
                        'new_quantity' => $expectedQuantity,
                        'achat_id' => $targetAchatId
                    ]);

                    if ($result) {
                        addLog("CORRIGÉ: Quantité mise à jour pour l'achat ID $targetAchatId de {$achat['quantity']} à $expectedQuantity", 'success');
                        $fixes++;
                    } else {
                        addLog("ÉCHEC: Impossible de corriger la quantité pour l'achat ID $targetAchatId", 'error');
                        $errors++;
                    }
                } else {
                    // Mode dry-run, juste enregistrer ce qui serait fait
                    addLog("ACTION NÉCESSAIRE: La quantité de l'achat ID $targetAchatId devrait être mise à jour de {$achat['quantity']} à $expectedQuantity", 'warning');
                }

                $problem = true;
            } else {
                addLog("Aucun problème détecté pour ce transfert", 'success');
            }
        } else {
            addLog("Transfert depuis une source autre que 'reçu', vérification non applicable");
        }

        // 5. Vérifier si la quantité a été correctement ajoutée dans le projet de destination
        // Cette partie est moins critique car le principal problème est la déduction dans le projet source
        $destExpressionQuery = "
            SELECT id, quantity_reserved
            FROM expression_dym
            WHERE idExpression = :expression_id
            AND LOWER(TRIM(designation)) LIKE LOWER(CONCAT('%', :product_name, '%'))
        ";

        $destExpressionStmt = $pdo->prepare($destExpressionQuery);
        $destExpressionStmt->execute([
            'expression_id' => $transfert['destination_expression_id'],
            'product_name' => $transfert['product_name']
        ]);
        $destExpression = $destExpressionStmt->fetch(PDO::FETCH_ASSOC);

        if ($destExpression) {
            addLog("Quantité réservée dans le projet destination: {$destExpression['quantity_reserved']}");
        } else {
            addLog("Aucune entrée d'expression_dym trouvée dans le projet destination", 'warning');
        }

        addLog("Analyse du transfert #{$transfert['id']} terminée", 'info');
        addLog("-----------------------------", 'info');
    }

    addLog("Résumé: $checked transfert(s) vérifié(s), $fixes correction(s) appliquée(s), $errors erreur(s) rencontrée(s)");

} catch (PDOException $e) {
    addLog("Erreur critique: " . $e->getMessage(), 'error');
    $errors++;
}

// Afficher les résultats
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction des Quantités de Transfert - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .log-info {
            background-color: #f3f4f6;
            border-left: 4px solid #6b7280;
        }

        .log-success {
            background-color: #ecfdf5;
            border-left: 4px solid #10b981;
        }

        .log-warning {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
        }

        .log-error {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
        }

        .code-block {
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-semibold">Correction des Quantités de Transfert</h1>
                <div class="flex space-x-3">
                    <a href="../transfert_manager.php" class="flex items-center text-gray-600 hover:text-gray-900">
                        <span class="material-icons mr-1">arrow_back</span>
                        Retour
                    </a>
                    <?php if ($mode === 'dry-run' && (count($transferts) > 0)): ?>
                        <a href="?mode=fix<?php echo $transfertId ? '&transfert_id=' . $transfertId : ''; ?>"
                            class="flex items-center bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-md">
                            <span class="material-icons mr-1">build</span>
                            Appliquer les corrections
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">
                <p>
                    <span class="font-bold">Mode:</span>
                    <?php echo $mode === 'fix' ? 'Correction (les problèmes détectés seront corrigés)' : 'Analyse (aucune modification ne sera appliquée)'; ?>
                </p>
                <p class="mt-2">
                    <span class="font-bold">Transfert ID:</span>
                    <?php echo $transfertId > 0 ? $transfertId : 'Tous les transferts complétés'; ?>
                </p>
            </div>

            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-4">Résumé</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg border shadow-sm">
                        <div class="text-lg font-medium">Transferts vérifiés</div>
                        <div class="text-3xl font-bold text-gray-800 mt-2"><?php echo $checked; ?></div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg border shadow-sm border-green-200">
                        <div class="text-lg font-medium text-green-800">Corrections appliquées</div>
                        <div class="text-3xl font-bold text-green-800 mt-2"><?php echo $fixes; ?></div>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg border shadow-sm border-red-200">
                        <div class="text-lg font-medium text-red-800">Erreurs rencontrées</div>
                        <div class="text-3xl font-bold text-red-800 mt-2"><?php echo $errors; ?></div>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-xl font-semibold mb-4">Journal d'exécution</h2>
                <div class="space-y-2 max-h-96 overflow-y-auto border rounded-lg p-4">
                    <?php foreach ($log as $entry): ?>
                        <div class="log-<?php echo $entry['type']; ?> p-3 rounded">
                            <div class="flex">
                                <span class="text-xs text-gray-500 mr-2">[<?php echo $entry['time']; ?>]</span>
                                <span><?php echo $entry['message']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($mode === 'fix' && $fixes > 0): ?>
                <div class="mt-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4">
                    <p class="font-bold">Corrections appliquées avec succès!</p>
                    <p>Les quantités des produits ont été mises à jour pour refléter correctement les transferts effectués.
                    </p>
                </div>
            <?php elseif ($mode === 'dry-run' && $checked > 0 && $errors === 0 && $fixes === 0): ?>
                <div class="mt-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4">
                    <p class="font-bold">Aucune correction nécessaire!</p>
                    <p>Tous les transferts vérifiés sont corrects et les quantités sont à jour.</p>
                </div>
            <?php endif; ?>

            <div class="mt-6 flex justify-end">
                <?php if ($mode === 'fix'): ?>
                    <a href="../transfert_manager.php" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md">
                        Retour au gestionnaire de transferts
                    </a>
                <?php else: ?>
                    <?php if ($errors > 0 || $fixes > 0): ?>
                        <a href="?mode=fix<?php echo $transfertId ? '&transfert_id=' . $transfertId : ''; ?>"
                            class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-md">
                            <span class="material-icons mr-1">build</span>
                            Appliquer les corrections
                        </a>
                    <?php else: ?>
                        <a href="../transfert_manager.php" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md">
                            Retour au gestionnaire de transferts
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>