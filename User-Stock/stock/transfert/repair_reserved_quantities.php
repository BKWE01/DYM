<?php
// Enregistrer dans le fichier repair_reserved_quantities.php
session_start();

// // Vérifier si l'utilisateur est connecté et est super admin
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
//     header("Location: ./../index.php");
//     exit();
// }

// Connexion à la base de données
include_once '../../../database/connection.php';

// Variables pour les messages
$logs = [];
$errorCount = 0;
$fixedCount = 0;
$updatedCount = 0;

// Fonction pour ajouter un message au log
function addLog($message, $type = 'info')
{
    global $logs;
    $logs[] = [
        'message' => $message,
        'type' => $type,
        'time' => date('H:i:s')
    ];
}

try {
    // Démarrer une transaction
    $pdo->beginTransaction();

    addLog("Début de la réparation des quantités réservées");

    // 1. Identifier les produits avec des quantités réservées non synchronisées
    $discrepanciesQuery = "
        SELECT 
            ed.id,
            ed.idExpression,
            ed.designation,
            ed.quantity_reserved AS current_quantity,
            COALESCE(SUM(am.quantity), 0) AS calculated_quantity,
            ip.code_projet,
            ip.nom_client
        FROM 
            expression_dym ed
        JOIN 
            identification_projet ip ON ed.idExpression = ip.idExpression
        LEFT JOIN 
            achats_materiaux am ON am.expression_id = ed.idExpression 
                AND LOWER(TRIM(am.designation)) = LOWER(TRIM(ed.designation))
                AND am.status IN ('commandé', 'en_cours')
        GROUP BY 
            ed.id, ed.idExpression, ed.designation, ed.quantity_reserved, ip.code_projet, ip.nom_client
        HAVING 
            ed.quantity_reserved <> COALESCE(SUM(am.quantity), 0)
        ORDER BY 
            ip.code_projet, ed.designation
    ";

    $discrepanciesStmt = $pdo->prepare($discrepanciesQuery);
    $discrepanciesStmt->execute();
    $discrepancies = $discrepanciesStmt->fetchAll(PDO::FETCH_ASSOC);

    addLog("Nombre de discordances trouvées: " . count($discrepancies));

    // 2. Corriger les discordances
    if (count($discrepancies) > 0) {
        foreach ($discrepancies as $discrepancy) {
            try {
                $updateQuery = "
                    UPDATE expression_dym
                    SET quantity_reserved = :calculated_quantity
                    WHERE id = :id
                ";

                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([
                    'calculated_quantity' => $discrepancy['calculated_quantity'],
                    'id' => $discrepancy['id']
                ]);

                $updatedCount++;
                addLog("Correction pour {$discrepancy['designation']} dans le projet {$discrepancy['code_projet']} ({$discrepancy['nom_client']}): {$discrepancy['current_quantity']} → {$discrepancy['calculated_quantity']}", 'success');
            } catch (PDOException $e) {
                $errorCount++;
                addLog("Erreur lors de la correction pour {$discrepancy['designation']} dans le projet {$discrepancy['code_projet']}: " . $e->getMessage(), 'error');
            }
        }
    } else {
        addLog("Aucune discordance à corriger", 'success');
    }

    // 3. Rechercher des entrées dans achats_materiaux sans correspondance dans expression_dym
    $missingSql = "
        SELECT 
            am.id,
            am.expression_id,
            am.designation,
            am.quantity,
            ip.code_projet,
            ip.nom_client
        FROM 
            achats_materiaux am
        JOIN 
            identification_projet ip ON am.expression_id = ip.idExpression
        LEFT JOIN 
            expression_dym ed ON am.expression_id = ed.idExpression 
                AND LOWER(TRIM(am.designation)) = LOWER(TRIM(ed.designation))
        WHERE 
            ed.id IS NULL
            AND am.status IN ('commandé', 'en_cours')
        ORDER BY 
            ip.code_projet, am.designation
    ";

    $missingStmt = $pdo->prepare($missingSql);
    $missingStmt->execute();
    $missing = $missingStmt->fetchAll(PDO::FETCH_ASSOC);

    addLog("Nombre d'entrées manquantes dans expression_dym: " . count($missing));

    // 4. Créer les entrées manquantes
    if (count($missing) > 0) {
        foreach ($missing as $item) {
            try {
                $insertQuery = "
                    INSERT INTO expression_dym (
                        idExpression, 
                        designation, 
                        quantity_reserved,
                        valide_achat,
                        created_at
                    ) VALUES (
                        :expression_id,
                        :designation,
                        :quantity,
                        'validé',
                        NOW()
                    )
                ";

                $insertStmt = $pdo->prepare($insertQuery);
                $insertStmt->execute([
                    'expression_id' => $item['expression_id'],
                    'designation' => $item['designation'],
                    'quantity' => $item['quantity']
                ]);

                $fixedCount++;
                addLog("Création d'une entrée pour {$item['designation']} dans le projet {$item['code_projet']} ({$item['nom_client']}): {$item['quantity']}", 'success');
            } catch (PDOException $e) {
                $errorCount++;
                addLog("Erreur lors de la création d'une entrée pour {$item['designation']} dans le projet {$item['code_projet']}: " . $e->getMessage(), 'error');
            }
        }
    }

    // 5. Exécuter la procédure stockée pour s'assurer que tout est synchronisé
    $pdo->exec("CALL initialize_reserved_quantities()");
    addLog("Procédure initialize_reserved_quantities exécutée avec succès", 'success');

    // Valider les changements
    $pdo->commit();

    addLog("Réparation terminée. Entrées mises à jour: $updatedCount, Entrées créées: $fixedCount, Erreurs: $errorCount", $errorCount > 0 ? 'warning' : 'success');

} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    addLog("Erreur critique: " . $e->getMessage(), 'error');
    $errorCount++;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réparation des Quantités Réservées - DYM STOCK</title>
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
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex flex-col min-h-screen">
        <!-- Sidebar -->
        <?php include_once '../sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 p-6">
            <?php include_once '../header.php'; ?>

            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-semibold">Réparation des Quantités Réservées</h1>

                    <div class="flex space-x-4">
                        <a href="manage_reservations.php"
                            class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition-colors duration-300 flex items-center">
                            <span class="material-icons mr-2">manage_search</span>
                            Gérer les quantités réservées
                        </a>

                        <a href="repair_reserved_quantities.php"
                            class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-md transition-colors duration-300 flex items-center">
                            <span class="material-icons mr-2">refresh</span>
                            Relancer la réparation
                        </a>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h2 class="text-lg font-medium text-gray-700 mb-2">Résumé</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white p-4 rounded-lg border shadow-sm">
                            <div class="flex items-center">
                                <span class="material-icons mr-2 text-blue-500">update</span>
                                <span class="text-gray-700 font-medium">Mises à jour</span>
                            </div>
                            <div class="text-2xl font-bold text-blue-600 mt-2"><?php echo $updatedCount; ?></div>
                        </div>

                        <div class="bg-white p-4 rounded-lg border shadow-sm">
                            <div class="flex items-center">
                                <span class="material-icons mr-2 text-green-500">add_circle</span>
                                <span class="text-gray-700 font-medium">Créations</span>
                            </div>
                            <div class="text-2xl font-bold text-green-600 mt-2"><?php echo $fixedCount; ?></div>
                        </div>

                        <div class="bg-white p-4 rounded-lg border shadow-sm">
                            <div class="flex items-center">
                                <span class="material-icons mr-2 text-red-500">error</span>
                                <span class="text-gray-700 font-medium">Erreurs</span>
                            </div>
                            <div class="text-2xl font-bold text-red-600 mt-2"><?php echo $errorCount; ?></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border rounded-lg shadow-sm">
                    <div class="p-4 border-b bg-gray-50">
                        <h2 class="text-lg font-medium text-gray-700">Journal de réparation</h2>
                    </div>

                    <div class="p-4 max-h-96 overflow-y-auto">
                        <?php if (empty($logs)): ?>
                            <p class="text-gray-500 italic">Aucune opération effectuée</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($logs as $log): ?>
                                    <div class="log-<?php echo $log['type']; ?> p-3 rounded text-sm">
                                        <div class="flex items-start">
                                            <span class="text-xs text-gray-500 mr-2">[<?php echo $log['time']; ?>]</span>
                                            <span><?php echo $log['message']; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>