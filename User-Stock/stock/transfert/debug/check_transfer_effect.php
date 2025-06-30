<?php
// Script de débogage pour vérifier l'effet des transferts sur les quantités de produits reçus
session_start();

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header("Location: ./../index.php");
    exit();
}

// Connexion à la base de données
include_once '../../../../database/connection.php';

// Récupérer les paramètres
$transfertId = isset($_GET['transfert_id']) ? intval($_GET['transfert_id']) : 0;
$productName = isset($_GET['product_name']) ? $_GET['product_name'] : '';
$sourceProjectId = isset($_GET['source_project_id']) ? intval($_GET['source_project_id']) : 0;

// Définir le mode de sortie
$outputMode = isset($_GET['output']) ? $_GET['output'] : 'html';
if ($outputMode === 'json') {
    header('Content-Type: application/json');
}

// Initialiser le tableau de résultats
$result = [
    'success' => false,
    'transfert' => null,
    'source_project' => null,
    'product' => null,
    'quantities_before' => null,
    'quantities_after' => null,
    'history' => null,
    'error' => null
];

try {
    if ($transfertId > 0) {
        // Récupérer les informations sur le transfert
        $transfertQuery = "
            SELECT t.*, 
                   p.product_name, 
                   sp.code_projet as source_project_code, sp.nom_client as source_project_name,
                   dp.code_projet as dest_project_code, dp.nom_client as dest_project_name,
                   u1.name as requested_by_name,
                   u2.name as completed_by_name
            FROM transferts t
            LEFT JOIN products p ON t.product_id = p.id
            LEFT JOIN identification_projet sp ON t.source_project_id = sp.id
            LEFT JOIN identification_projet dp ON t.destination_project_id = dp.id
            LEFT JOIN users_exp u1 ON t.requested_by = u1.id
            LEFT JOIN users_exp u2 ON t.completed_by = u2.id
            WHERE t.id = :transfert_id
        ";

        $transfertStmt = $pdo->prepare($transfertQuery);
        $transfertStmt->execute(['transfert_id' => $transfertId]);
        $transfert = $transfertStmt->fetch(PDO::FETCH_ASSOC);

        if ($transfert) {
            $result['transfert'] = $transfert;
            $productName = $transfert['product_name'];
            $sourceProjectId = $transfert['source_project_id'];

            // Récupérer l'historique du transfert
            $historyQuery = "
                SELECT th.*, u.name as user_name, th.details
                FROM transfert_history th
                LEFT JOIN users_exp u ON th.user_id = u.id
                WHERE th.transfert_id = :transfert_id
                ORDER BY th.created_at
            ";

            $historyStmt = $pdo->prepare($historyQuery);
            $historyStmt->execute(['transfert_id' => $transfertId]);
            $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

            // Transformer les détails JSON en tableau pour chaque entrée d'historique
            foreach ($history as &$entry) {
                if (isset($entry['details']) && !empty($entry['details'])) {
                    $entry['details_array'] = json_decode($entry['details'], true);
                }
            }

            $result['history'] = $history;
        } else {
            $result['error'] = "Transfert non trouvé";
        }
    }

    if ($sourceProjectId > 0) {
        // Récupérer les informations sur le projet source
        $projectQuery = "
            SELECT *
            FROM identification_projet
            WHERE id = :project_id
        ";

        $projectStmt = $pdo->prepare($projectQuery);
        $projectStmt->execute(['project_id' => $sourceProjectId]);
        $project = $projectStmt->fetch(PDO::FETCH_ASSOC);

        if ($project) {
            $result['source_project'] = $project;
        } else {
            $result['error'] = "Projet source non trouvé";
        }
    }

    if (!empty($productName) && isset($project['idExpression'])) {
        // Récupérer les quantités dans achats_materiaux
        $achatsQuery = "
            SELECT id, designation, quantity, status, date_achat, date_reception
            FROM achats_materiaux
            WHERE expression_id = :expression_id
            AND (
                LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
                OR LOWER(TRIM(designation)) LIKE CONCAT('%', LOWER(TRIM(:product_name)), '%')
            )
            ORDER BY status, date_achat
        ";

        $achatsStmt = $pdo->prepare($achatsQuery);
        $achatsStmt->execute([
            'expression_id' => $project['idExpression'],
            'product_name' => $productName
        ]);
        $achats = $achatsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les quantités dans expression_dym
        $expressionQuery = "
            SELECT id, designation, quantity, quantity_reserved, created_at
            FROM expression_dym
            WHERE idExpression = :expression_id
            AND (
                LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
                OR LOWER(TRIM(designation)) LIKE CONCAT('%', LOWER(TRIM(:product_name)), '%')
            )
        ";

        $expressionStmt = $pdo->prepare($expressionQuery);
        $expressionStmt->execute([
            'expression_id' => $project['idExpression'],
            'product_name' => $productName
        ]);
        $expressions = $expressionStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculer les totaux par statut
        $totals = [
            'recu' => 0,
            'commande' => 0,
            'en_cours' => 0,
            'expression_quantity' => 0,
            'expression_reserved' => 0
        ];

        foreach ($achats as $achat) {
            if ($achat['status'] === 'reçu') {
                $totals['recu'] += floatval($achat['quantity']);
            } elseif ($achat['status'] === 'commandé') {
                $totals['commande'] += floatval($achat['quantity']);
            } elseif ($achat['status'] === 'en_cours') {
                $totals['en_cours'] += floatval($achat['quantity']);
            }
        }

        foreach ($expressions as $expr) {
            $totals['expression_quantity'] += floatval($expr['quantity'] ?? 0);
            $totals['expression_reserved'] += floatval($expr['quantity_reserved'] ?? 0);
        }

        $result['quantities_before'] = [
            'achats_materiaux' => $achats,
            'expression_dym' => $expressions,
            'totals' => $totals
        ];

        // Récupérer le produit dans la table products
        if (isset($transfert['product_id'])) {
            $productQuery = "SELECT * FROM products WHERE id = :product_id";
            $productStmt = $pdo->prepare($productQuery);
            $productStmt->execute(['product_id' => $transfert['product_id']]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $result['product'] = $product;
            }
        }

        // Calculer ce que serait l'état après l'application du transfert (simulation)
        if (isset($transfert['quantity']) && $transfert['status'] === 'pending') {
            $transferQuantity = floatval($transfert['quantity']);
            $simulatedTotals = $totals;

            // Simuler la déduction de la quantité
            $remainingToDeduct = $transferQuantity;

            // Priorité au statut 'reçu'
            if ($simulatedTotals['recu'] >= $remainingToDeduct) {
                $simulatedTotals['recu'] -= $remainingToDeduct;
                $remainingToDeduct = 0;
            } else {
                $remainingToDeduct -= $simulatedTotals['recu'];
                $simulatedTotals['recu'] = 0;

                // Ensuite, déduire du statut 'commandé'
                if ($simulatedTotals['commande'] >= $remainingToDeduct) {
                    $simulatedTotals['commande'] -= $remainingToDeduct;
                    $remainingToDeduct = 0;
                } else {
                    $remainingToDeduct -= $simulatedTotals['commande'];
                    $simulatedTotals['commande'] = 0;

                    // Enfin, déduire du statut 'en_cours'
                    if ($simulatedTotals['en_cours'] >= $remainingToDeduct) {
                        $simulatedTotals['en_cours'] -= $remainingToDeduct;
                        $remainingToDeduct = 0;
                    } else {
                        $remainingToDeduct -= $simulatedTotals['en_cours'];
                        $simulatedTotals['en_cours'] = 0;
                    }
                }
            }

            // Simuler la déduction dans expression_dym
            if ($simulatedTotals['expression_reserved'] >= $transferQuantity) {
                $simulatedTotals['expression_reserved'] -= $transferQuantity;
            } else {
                $simulatedTotals['expression_reserved'] = 0;
            }

            $result['quantities_after'] = [
                'totals' => $simulatedTotals,
                'transfer_quantity' => $transferQuantity,
                'remaining_to_deduct' => $remainingToDeduct
            ];
        }
    }

    // Si le transfert est complété ou annulé, récupérer les données actuelles
    if (isset($transfert['status']) && ($transfert['status'] === 'completed' || $transfert['status'] === 'canceled')) {
        // Les données actuelles sont déjà dans quantities_before
        $result['quantities_after'] = [
            'message' => "Le transfert est déjà {$transfert['status']}. Consultez l'historique pour plus de détails."
        ];
    }

    $result['success'] = true;

} catch (PDOException $e) {
    $result['error'] = "Erreur de base de données: " . $e->getMessage();
}

// Afficher les résultats selon le mode
if ($outputMode === 'json') {
    echo json_encode($result, JSON_PRETTY_PRINT);
} else {
    // Affichage HTML
    ?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Analyse de l'effet des transferts - DYM STOCK</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <style>
            .code-block {
                background-color: #f8f9fa;
                border: 1px solid #e5e7eb;
                border-radius: 0.375rem;
                padding: 1rem;
                font-family: monospace;
                white-space: pre-wrap;
                overflow-x: auto;
            }

            .status-badge {
                display: inline-flex;
                align-items: center;
                padding: 0.35rem 0.65rem;
                font-size: 0.75rem;
                font-weight: 600;
                line-height: 1;
                color: #fff;
                white-space: nowrap;
                border-radius: 9999px;
            }

            .status-pending {
                background-color: #f59e0b;
            }

            .status-completed {
                background-color: #10b981;
            }

            .status-canceled {
                background-color: #ef4444;
            }
        </style>
    </head>

    <body class="bg-gray-100">
        <div class="container mx-auto p-6">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-semibold">Analyse de l'effet des transferts</h1>
                    <div class="flex space-x-3">
                        <a href="transfert_manager.php" class="flex items-center text-gray-600 hover:text-gray-900">
                            <span class="material-icons mr-1">arrow_back</span>
                            Retour
                        </a>
                        <a href="<?php echo $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'output=json'; ?>"
                            class="flex items-center text-blue-600 hover:text-blue-900">
                            <span class="material-icons mr-1">code</span>
                            Voir JSON
                        </a>
                    </div>
                </div>

                <?php if (isset($result['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                        <p><?php echo $result['error']; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($result['transfert']): ?>
                    <div class="mb-6">
                        <h2 class="text-xl font-medium mb-3">Informations sur le transfert</h2>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-gray-500">ID du transfert</p>
                                    <p class="font-medium"><?php echo $result['transfert']['id']; ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Statut</p>
                                    <p>
                                        <?php
                                        $statusClass = '';
                                        $statusText = '';
                                        switch ($result['transfert']['status']) {
                                            case 'pending':
                                                $statusClass = 'status-pending';
                                                $statusText = 'En attente';
                                                break;
                                            case 'completed':
                                                $statusClass = 'status-completed';
                                                $statusText = 'Complété';
                                                break;
                                            case 'canceled':
                                                $statusClass = 'status-canceled';
                                                $statusText = 'Annulé';
                                                break;
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Produit</p>
                                    <p class="font-medium"><?php echo $result['transfert']['product_name']; ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Quantité</p>
                                    <p class="font-medium"><?php echo $result['transfert']['quantity']; ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Projet source</p>
                                    <p class="font-medium"><?php echo $result['transfert']['source_project_name']; ?>
                                        (<?php echo $result['transfert']['source_project_code']; ?>)</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Projet destination</p>
                                    <p class="font-medium"><?php echo $result['transfert']['dest_project_name']; ?>
                                        (<?php echo $result['transfert']['dest_project_code']; ?>)</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Demandé par</p>
                                    <p class="font-medium"><?php echo $result['transfert']['requested_by_name']; ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Date de demande</p>
                                    <p class="font-medium">
                                        <?php echo date('d/m/Y H:i', strtotime($result['transfert']['created_at'])); ?></p>
                                </div>
                                <?php if ($result['transfert']['status'] === 'completed'): ?>
                                    <div>
                                        <p class="text-gray-500">Complété par</p>
                                        <p class="font-medium"><?php echo $result['transfert']['completed_by_name']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Date de complétion</p>
                                        <p class="font-medium">
                                            <?php echo date('d/m/Y H:i', strtotime($result['transfert']['completed_at'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($result['transfert']['notes']): ?>
                                    <div class="md:col-span-2">
                                        <p class="text-gray-500">Notes</p>
                                        <p class="bg-gray-100 p-2 rounded"><?php echo $result['transfert']['notes']; ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($result['source_project']): ?>
                    <div class="mb-6">
                        <h2 class="text-xl font-medium mb-3">Projet source</h2>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-gray-500">ID du projet</p>
                                    <p class="font-medium"><?php echo $result['source_project']['id']; ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Code du projet</p>
                                    <p class="font-medium"><?php echo $result['source_project']['code_projet']; ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Nom du client</p>
                                    <p class="font-medium"><?php echo $result['source_project']['nom_client']; ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">ID Expression</p>
                                    <p class="font-medium"><?php echo $result['source_project']['idExpression']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($result['product']): ?>
                    <div class="mb-6">
                        <h2 class="text-xl font-medium mb-3">Informations sur le produit</h2>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-gray-500">ID du produit</p>
                                    <p class="font-medium"><?php echo $result['product']['id']; ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Nom du produit</p>
                                    <p class="font-medium"><?php echo $result['product']['product_name']; ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Code-barres</p>
                                    <p class="font-medium"><?php echo $result['product']['barcode'] ?: 'N/A'; ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Quantité en stock</p>
                                    <p class="font-medium"><?php echo $result['product']['quantity']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($result['quantities_before']): ?>
                    <div class="mb-6">
                        <h2 class="text-xl font-medium mb-3">Quantités actuelles dans le projet source</h2>

                        <div class="bg-blue-50 p-4 rounded-lg mb-4">
                            <h3 class="font-medium text-blue-700 mb-2">Résumé des quantités</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-white p-3 rounded shadow-sm">
                                    <p class="text-gray-500 text-sm">Quantité reçue</p>
                                    <p class="font-medium text-lg"><?php echo $result['quantities_before']['totals']['recu']; ?>
                                    </p>
                                </div>
                                <div class="bg-white p-3 rounded shadow-sm">
                                    <p class="text-gray-500 text-sm">Quantité commandée</p>
                                    <p class="font-medium text-lg">
                                        <?php echo $result['quantities_before']['totals']['commande']; ?></p>
                                </div>
                                <div class="bg-white p-3 rounded shadow-sm">
                                    <p class="text-gray-500 text-sm">Quantité en cours</p>
                                    <p class="font-medium text-lg">
                                        <?php echo $result['quantities_before']['totals']['en_cours']; ?></p>
                                </div>
                                <div class="bg-white p-3 rounded shadow-sm">
                                    <p class="text-gray-500 text-sm">Quantité expression_dym</p>
                                    <p class="font-medium text-lg">
                                        <?php echo $result['quantities_before']['totals']['expression_quantity']; ?></p>
                                </div>
                                <div class="bg-white p-3 rounded shadow-sm">
                                    <p class="text-gray-500 text-sm">Quantité réservée</p>
                                    <p class="font-medium text-lg">
                                        <?php echo $result['quantities_before']['totals']['expression_reserved']; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Détails des achats_materiaux -->
                        <?php if (count($result['quantities_before']['achats_materiaux']) > 0): ?>
                            <h3 class="font-medium text-gray-700 mb-2 mt-4">Détails des achats_materiaux</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white rounded-lg overflow-hidden">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="py-2 px-4 text-left">ID</th>
                                            <th class="py-2 px-4 text-left">Désignation</th>
                                            <th class="py-2 px-4 text-left">Quantité</th>
                                            <th class="py-2 px-4 text-left">Statut</th>
                                            <th class="py-2 px-4 text-left">Date achat</th>
                                            <th class="py-2 px-4 text-left">Date réception</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($result['quantities_before']['achats_materiaux'] as $achat): ?>
                                            <tr class="border-t">
                                                <td class="py-2 px-4"><?php echo $achat['id']; ?></td>
                                                <td class="py-2 px-4"><?php echo $achat['designation']; ?></td>
                                                <td class="py-2 px-4"><?php echo $achat['quantity']; ?></td>
                                                <td class="py-2 px-4">
                                                    <?php
                                                    $statusColor = '';
                                                    switch ($achat['status']) {
                                                        case 'reçu':
                                                            $statusColor = 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'commandé':
                                                            $statusColor = 'bg-yellow-100 text-yellow-800';
                                                            break;
                                                        case 'en_cours':
                                                            $statusColor = 'bg-blue-100 text-blue-800';
                                                            break;
                                                    }
                                                    ?>
                                                    <span
                                                        class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                                                        <?php echo $achat['status']; ?>
                                                    </span>
                                                </td>
                                                <td class="py-2 px-4">
                                                    <?php echo $achat['date_achat'] ? date('d/m/Y', strtotime($achat['date_achat'])) : 'N/A'; ?>
                                                </td>
                                                <td class="py-2 px-4">
                                                    <?php echo $achat['date_reception'] ? date('d/m/Y', strtotime($achat['date_reception'])) : 'N/A'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="bg-yellow-50 p-4 rounded-lg mt-4">
                                <p class="text-yellow-700">Aucun achat trouvé pour ce produit dans le projet source.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Détails de expression_dym -->
                        <?php if (count($result['quantities_before']['expression_dym']) > 0): ?>
                            <h3 class="font-medium text-gray-700 mb-2 mt-4">Détails de expression_dym</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white rounded-lg overflow-hidden">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="py-2 px-4 text-left">ID</th>
                                            <th class="py-2 px-4 text-left">Désignation</th>
                                            <th class="py-2 px-4 text-left">Quantité</th>
                                            <th class="py-2 px-4 text-left">Quantité réservée</th>
                                            <th class="py-2 px-4 text-left">Date création</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($result['quantities_before']['expression_dym'] as $expr): ?>
                                            <tr class="border-t">
                                                <td class="py-2 px-4"><?php echo $expr['id']; ?></td>
                                                <td class="py-2 px-4"><?php echo $expr['designation']; ?></td>
                                                <td class="py-2 px-4"><?php echo $expr['quantity']; ?></td>
                                                <td class="py-2 px-4"><?php echo $expr['quantity_reserved']; ?></td>
                                                <td class="py-2 px-4"><?php echo date('d/m/Y', strtotime($expr['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="bg-yellow-50 p-4 rounded-lg mt-4">
                                <p class="text-yellow-700">Aucune expression trouvée pour ce produit dans le projet source.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($result['quantities_after'] && isset($result['quantities_after']['totals'])): ?>
                    <div class="mb-6">
                        <h2 class="text-xl font-medium mb-3">Simulation après transfert</h2>

                        <div class="bg-green-50 p-4 rounded-lg mb-4">
                            <h3 class="font-medium text-green-700 mb-2">Résumé des quantités après transfert</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-white p-3 rounded shadow-sm">
                                    <p class="text-gray-500 text-sm">Quantité reçue</p>
                                    <p class="font-medium text-lg"><?php echo $result['quantities_after']['totals']['recu']; ?>
                                    </p>
                                    <?php if (isset($result['quantities_before']) && $result['quantities_before']['totals']['recu'] != $result['quantities_after']['totals']['recu']): ?>
                                        <p
                                            class="text-xs <?php echo $result['quantities_after']['totals']['recu'] < $result['quantities_before']['totals']['recu'] ? 'text-red-500' : 'text-green-500'; ?>">
                                            <?php echo $result['quantities_after']['totals']['recu'] < $result['quantities_before']['totals']['recu'] ? '-' : '+'; ?>
                                            <?php echo abs($result['quantities_after']['totals']['recu'] - $result['quantities_before']['totals']['recu']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-white p-3 rounded shadow-sm">
                                    <p class="text-gray-500 text-sm">Quantité commandée</p>
                                    <p class="font-medium text-lg">
                                        <?php echo $result['quantities_after']['totals']['commande']; ?></p>
                                    <?php if (isset($result['quantities_before']) && $result['quantities_before']['totals']['commande'] != $result['quantities_after']['totals']['commande']): ?>
                                        <p
                                            class="text-xs <?php echo $result['quantities_after']['totals']['commande'] < $result['quantities_before']['totals']['commande'] ? 'text-red-500' : 'text-green-500'; ?>">
                                            <?php echo $result['quantities_after']['totals']['commande'] < $result['quantities_before']['totals']['commande'] ? '-' : '+'; ?>
                                            <?php echo abs($result['quantities_after']['totals']['commande'] - $result['quantities_before']['totals']['commande']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-white p-3 rounded shadow-sm">
                                    <p class="text-gray-500 text-sm">Quantité en cours</p>
                                    <p class="font-medium text-lg">
                                        <?php echo $result['quantities_after']['totals']['en_cours']; ?></p>
                                    <?php if (isset($result['quantities_before']) && $result['quantities_before']['totals']['en_cours'] != $result['quantities_after']['totals']['en_cours']): ?>
                                        <p
                                            class="text-xs <?php echo $result['quantities_after']['totals']['en_cours'] < $result['quantities_before']['totals']['en_cours'] ? 'text-red-500' : 'text-green-500'; ?>">
                                            <?php echo $result['quantities_after']['totals']['en_cours'] < $result['quantities_before']['totals']['en_cours'] ? '-' : '+'; ?>
                                            <?php echo abs($result['quantities_after']['totals']['en_cours'] - $result['quantities_before']['totals']['en_cours']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-white p-3 rounded shadow-sm">
                                    <p class="text-gray-500 text-sm">Quantité expression_dym</p>
                                    <p class="font-medium text-lg">
                                        <?php echo $result['quantities_after']['totals']['expression_quantity']; ?></p>
                                    <?php if (isset($result['quantities_before']) && $result['quantities_before']['totals']['expression_quantity'] != $result['quantities_after']['totals']['expression_quantity']): ?>
                                        <p
                                            class="text-xs <?php echo $result['quantities_after']['totals']['expression_quantity'] < $result['quantities_before']['totals']['expression_quantity'] ? 'text-red-500' : 'text-green-500'; ?>">
                                            <?php echo $result['quantities_after']['totals']['expression_quantity'] < $result['quantities_before']['totals']['expression_quantity'] ? '-' : '+'; ?>
                                            <?php echo abs($result['quantities_after']['totals']['expression_quantity'] - $result['quantities_before']['totals']['expression_quantity']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-white p-3 rounded shadow-sm">
                                    <p class="text-gray-500 text-sm">Quantité réservée</p>
                                    <p class="font-medium text-lg">
                                        <?php echo $result['quantities_after']['totals']['expression_reserved']; ?></p>
                                    <?php if (isset($result['quantities_before']) && $result['quantities_before']['totals']['expression_reserved'] != $result['quantities_after']['totals']['expression_reserved']): ?>
                                        <p
                                            class="text-xs <?php echo $result['quantities_after']['totals']['expression_reserved'] < $result['quantities_before']['totals']['expression_reserved'] ? 'text-red-500' : 'text-green-500'; ?>">
                                            <?php echo $result['quantities_after']['totals']['expression_reserved'] < $result['quantities_before']['totals']['expression_reserved'] ? '-' : '+'; ?>
                                            <?php echo abs($result['quantities_after']['totals']['expression_reserved'] - $result['quantities_before']['totals']['expression_reserved']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (isset($result['quantities_after']['transfer_quantity'])): ?>
                                <div class="mt-4 bg-white p-3 rounded">
                                    <p class="text-sm">
                                        <span class="font-medium">Quantité à transférer:</span>
                                        <?php echo $result['quantities_after']['transfer_quantity']; ?>
                                    </p>
                                    <?php if ($result['quantities_after']['remaining_to_deduct'] > 0): ?>
                                        <p class="text-sm text-red-600">
                                            <span class="material-icons text-xs align-middle">warning</span>
                                            Attention: Il manque <?php echo $result['quantities_after']['remaining_to_deduct']; ?>
                                            unités pour compléter ce transfert
                                        </p>
                                    <?php else: ?>
                                        <p class="text-sm text-green-600">
                                            <span class="material-icons text-xs align-middle">check_circle</span>
                                            Le transfert peut être complété avec les quantités disponibles
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($result['transfert']['status'] === 'pending'): ?>
                            <div class="flex justify-end mt-4">
                                <a href="api_complete_transfert.php?id=<?php echo $result['transfert']['id']; ?>"
                                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded shadow focus:outline-none">
                                    <span class="material-icons align-middle text-sm mr-1">check_circle</span>
                                    Compléter le transfert
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($result['quantities_after'] && isset($result['quantities_after']['message'])): ?>
                    <div class="bg-yellow-50 p-4 rounded-lg mb-6">
                        <p class="text-yellow-700"><?php echo $result['quantities_after']['message']; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($result['history']): ?>
                    <div class="mb-6">
                        <h2 class="text-xl font-medium mb-3">Historique du transfert</h2>
                        <div class="space-y-4">
                            <?php foreach ($result['history'] as $entry): ?>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium">
                                                <?php
                                                $actionText = '';
                                                switch ($entry['action']) {
                                                    case 'create':
                                                        $actionText = 'Création du transfert';
                                                        break;
                                                    case 'complete':
                                                        $actionText = 'Transfert complété';
                                                        break;
                                                    case 'cancel':
                                                        $actionText = 'Transfert annulé';
                                                        break;
                                                    default:
                                                        $actionText = $entry['action'];
                                                }
                                                echo $actionText;
                                                ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo date('d/m/Y H:i:s', strtotime($entry['created_at'])); ?>
                                                par <?php echo $entry['user_name']; ?>
                                            </p>
                                        </div>
                                        <div>
                                            <?php
                                            $actionBadgeClass = '';
                                            switch ($entry['action']) {
                                                case 'create':
                                                    $actionBadgeClass = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'complete':
                                                    $actionBadgeClass = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'cancel':
                                                    $actionBadgeClass = 'bg-red-100 text-red-800';
                                                    break;
                                                default:
                                                    $actionBadgeClass = 'bg-gray-100 text-gray-800';
                                            }
                                            ?>
                                            <span
                                                class="px-2 py-1 rounded-full text-xs font-medium <?php echo $actionBadgeClass; ?>">
                                                <?php echo $entry['action']; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <?php if (isset($entry['details_array'])): ?>
                                        <div class="mt-3 bg-white p-3 rounded text-sm">
                                            <p class="font-medium mb-1">Détails:</p>
                                            <ul class="list-disc pl-5 space-y-1">
                                                <?php
                                                foreach ($entry['details_array'] as $key => $value):
                                                    if (is_string($value) || is_numeric($value)):
                                                        ?>
                                                        <li>
                                                            <span
                                                                class="font-medium"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                                                            <?php echo htmlspecialchars($value); ?>
                                                        </li>
                                                    <?php
                                                    endif;
                                                endforeach;
                                                ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </body>

    </html>
    <?php
}
?>