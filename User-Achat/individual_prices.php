<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Vérifier si les informations d'achat groupé existent en session
if (!isset($_SESSION['bulk_purchase'])) {
    header("Location: achats_materiaux.php");
    exit();
}

// Récupérer les données de la session
$bulkPurchase = $_SESSION['bulk_purchase'];
$projectId = $bulkPurchase['project_id'];
$materialIds = $bulkPurchase['material_ids'];
$fournisseurId = $bulkPurchase['fournisseur'];

// Connexion à la base de données
include_once '../database/connection.php';

// Variables pour stocker les données
$project = null;
$materials = [];
$message = '';

try {
    // Récupérer les informations du projet
    $projectQuery = "SELECT id, idExpression, code_projet, nom_client, description_projet, sitgeo, chefprojet 
                    FROM identification_projet 
                    WHERE idExpression = :idExpression";
    $projectStmt = $pdo->prepare($projectQuery);
    $projectStmt->bindParam(':idExpression', $projectId);
    $projectStmt->execute();
    $project = $projectStmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception("Projet non trouvé.");
    }

    // Récupérer les informations de chaque matériau
    $materialsQuery = "SELECT id, idExpression, designation, qt_acheter, unit 
                      FROM expression_dym 
                      WHERE id IN (" . implode(',', array_map('intval', $materialIds)) . ")";
    $materialsStmt = $pdo->prepare($materialsQuery);
    $materialsStmt->execute();
    $materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($materials)) {
        throw new Exception("Aucun matériau trouvé pour cet achat groupé.");
    }

    // Tableau temporaire pour stocker les prix des matériaux
    $materialPrices = [];

    // Traitement du formulaire de soumission des prix individuels
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_individual_prices'])) {
        $pdo->beginTransaction();

        $user_id = $_SESSION['user_id'];
        $materialPrices = $_POST['material_prices'] ?? [];
        // Stocker temporairement dans la session
        $_SESSION['temp_fournisseur'] = $fournisseurId;
        $_SESSION['temp_material_prices'] = $materialPrices;
        $_SESSION['selected_material_ids'] = $materialIds;

        $quantities = $_POST['quantities'] ?? [];
        $originalQuantities = $_POST['original_quantities'] ?? [];

        // Vérifier si tous les prix sont fournis
        if (count($materialPrices) != count($materials)) {
            $message = "Veuillez définir un prix pour tous les matériaux.";
        }
        // Vérifier la validité des quantités
        else if (
            array_filter($quantities, function ($q) {
                return $q <= 0;
            })
        ) {
            $message = "Veuillez saisir des quantités valides supérieures à 0.";
        } else {
            // Tableau pour stocker tous les IDs d'expression uniques
            $allExpressionIds = [];

            // Traiter chaque matériau
            foreach ($materials as $material) {
                $materialId = $material['id'];
                if (!isset($materialPrices[$materialId]) || $materialPrices[$materialId] <= 0) {
                    throw new Exception("Prix invalide pour le matériau " . $material['designation']);
                }

                $prix = $materialPrices[$materialId];
                $quantity = isset($quantities[$materialId]) ? $quantities[$materialId] : $material['qt_acheter'];
                $originalQuantity = isset($originalQuantities[$materialId]) ? $originalQuantities[$materialId] : $material['qt_acheter'];

                // Insérer dans la table achats_materiaux
                $insertAchatQuery = "INSERT INTO achats_materiaux
                               (expression_id, designation, quantity, unit, prix_unitaire, fournisseur_id, status, user_achat, original_quantity)
                               VALUES (:expression_id, :designation, :quantity, :unit, :prix, :fournisseur_id, 'commandé', :user_achat, :original_qty)";

                $insertStmt = $pdo->prepare($insertAchatQuery);
                $insertStmt->bindParam(':expression_id', $material['idExpression']);
                $insertStmt->bindParam(':designation', $material['designation']);
                $insertStmt->bindParam(':quantity', $quantity);
                $insertStmt->bindParam(':unit', $material['unit']);
                $insertStmt->bindParam(':prix', $prix);
                $insertStmt->bindParam(':fournisseur_id', $fournisseurId);
                $insertStmt->bindParam(':user_achat', $user_id);
                $insertStmt->bindParam(':original_qty', $originalQuantity);
                $insertStmt->execute();

                // Mettre à jour la table expression_dym
                $updateExpressionQuery = "UPDATE expression_dym 
                                    SET valide_achat = 'validé', 
                                    prix_unitaire = :prix, 
                                    fournisseur = :fournisseur,
                                    user_achat = :user_achat,
                                    qt_acheter = :quantity,
                                    initial_qt_acheter = :original_qty 
                                    WHERE id = :id";

                $updateStmt = $pdo->prepare($updateExpressionQuery);
                $updateStmt->bindParam(':prix', $prix);
                $updateStmt->bindParam(':fournisseur', $fournisseurId);
                $updateStmt->bindParam(':user_achat', $user_id);
                $updateStmt->bindParam(':quantity', $quantity);
                $updateStmt->bindParam(':original_qty', $originalQuantity);
                $updateStmt->bindParam(':id', $materialId);
                $updateStmt->execute();

                // Collecter les IDs d'expression pour le bon de commande
                if (!in_array($material['idExpression'], $allExpressionIds)) {
                    $allExpressionIds[] = $material['idExpression'];
                }
            }

            $pdo->commit();

            // Stocker les informations pour le bon de commande
            $_SESSION['bulk_purchase_expressions'] = $allExpressionIds;

            // Supprimer les informations d'achat groupé de la session
            unset($_SESSION['bulk_purchase']);

            // Si nous avons des projets multiples, utiliser le premier pour générer le bon de commande
            if (!empty($allExpressionIds)) {
                $_SESSION['success_message'] = "Commandes groupées enregistrées avec succès!";
                header("Location: generate_bon_commande.php?id=" . $allExpressionIds[0]);
            } else {
                // Définir un message de succès et rediriger
                $_SESSION['success_message'] = "Commandes groupées enregistrées avec succès!";
                header("Location: achats_materiaux.php");
            }
            exit();
        }
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = "Une erreur s'est produite : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Définir les prix individuels - Achats Matériaux</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .validate-btn {
            border: 2px solid #38a169;
            color: #38a169;
            padding: 8px 18px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            background-color: transparent;
            transition: color 0.3s, border-color 0.3s;
        }

        .validate-btn:hover {
            color: #2f855a;
            border-color: #2f855a;
        }

        .date-time {
            display: flex;
            align-items: center;
            font-size: 16px;
            color: #4a5568;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 8px 18px;
        }

        .date-time .material-icons {
            margin-right: 12px;
            font-size: 22px;
            color: #2d3748;
        }

        /* Styles pour les formulaires */
        .form-input {
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            width: 100%;
            font-size: 0.875rem;
            transition: border-color 0.15s ease-in-out;
        }

        .form-input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .error-message {
            color: #e53e3e;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
                <div class="flex items-center">
                    <a href="achats_materiaux.php" class="mr-4 flex items-center text-blue-600 hover:text-blue-800">
                        <span class="material-icons">arrow_back</span>
                        <span class="ml-1">Retour</span>
                    </a>
                    <h1 class="text-xl font-semibold">Définir les prix individuels pour l'achat groupé</h1>
                </div>

                <div class="date-time">
                    <span class="material-icons">calendar_today</span>
                    <span id="date-time-display"></span>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <span class="block sm:inline"><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Informations du projet -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Informations du projet</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Code Projet:</p>
                        <p class="font-medium"><?php echo htmlspecialchars($project['code_projet']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Client:</p>
                        <p class="font-medium"><?php echo htmlspecialchars($project['nom_client']); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-600">Description:</p>
                        <p class="font-medium"><?php echo htmlspecialchars($project['description_projet']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Fournisseur commun -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Fournisseur commun</h2>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-600">Fournisseur sélectionné:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($fournisseurId); ?></p>
                </div>
            </div>

            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <span class="material-icons text-blue-500">info</span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Nouveau :</strong> Vous pouvez désormais modifier les quantités à commander. Les
                            quantités initiales seront conservées pour référence.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Formulaire de prix individuels -->
            <form method="POST" action="" class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Définir les prix unitaires (FCFA)</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Désignation
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Quantité <span class="text-blue-500 text-xs font-normal">(modifiable)</span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Unité
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Prix Unitaire (FCFA)
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($materials as $material): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($material['designation']); ?>
                                        <input type="hidden" name="original_quantities[<?php echo $material['id']; ?>]"
                                            value="<?php echo htmlspecialchars($material['qt_acheter']); ?>">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="number" step="0.01" min="0.01" required
                                            name="quantities[<?php echo $material['id']; ?>]" class="form-input"
                                            value="<?php echo htmlspecialchars($material['qt_acheter']); ?>">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($material['unit'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="number" step="0.01" min="0" required
                                            name="material_prices[<?php echo $material['id']; ?>]" class="form-input"
                                            placeholder="Entrez le prix unitaire">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-between">
                    <a href="achats_materiaux.php"
                        class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Annuler
                    </a>
                    <button type="submit" name="submit_individual_prices"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Valider la commande groupée
                    </button>
                </div>
            </form>
        </main>

        <?php include_once '../components/footer.html'; ?>
    </div>

    <script>
        // Fonction pour mettre à jour l'affichage de la date et heure
        function updateDateTime() {
            const now = new Date();
            const formattedDate = `${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
            document.getElementById('date-time-display').textContent = formattedDate;
        }

        // Mettre à jour l'heure toutes les secondes
        setInterval(updateDateTime, 1000);
        updateDateTime(); // Initialiser l'affichage
    </script>
</body>

</html>