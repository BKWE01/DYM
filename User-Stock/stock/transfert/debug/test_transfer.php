<?php
// Script pour tester la fonctionnalité de transfert avec différents scénarios
session_start();

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header("Location: ./../index.php");
    exit();
}

// Connexion à la base de données
include_once '../../../../database/connection.php';

// Fonction pour exécuter un test et obtenir les résultats
function runTest($name, $callback)
{
    echo "<div class='test-case bg-white p-6 rounded-lg shadow-md mb-6'>";
    echo "<h3 class='text-xl font-semibold mb-4'>Test: $name</h3>";

    try {
        echo "<div class='bg-gray-100 p-4 rounded-lg mb-4'>";
        echo "<pre class='text-sm overflow-x-auto'>";
        $result = $callback();
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "</pre>";
        echo "</div>";

        if ($result['success']) {
            echo "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4'>";
            echo "<p><span class='font-bold'>✓ Succès:</span> Le test a réussi.</p>";
            echo "</div>";
        } else {
            echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4'>";
            echo "<p><span class='font-bold'>✗ Échec:</span> " . ($result['error'] ?? 'Une erreur est survenue') . "</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4'>";
        echo "<p><span class='font-bold'>✗ Exception:</span> " . $e->getMessage() . "</p>";
        echo "</div>";
    }

    echo "</div>";
}

// Tests à exécuter
$tests = [
    'Simuler un transfert entre deux projets' => function () use ($pdo) {
        global $pdo;

        // 1. Trouver un produit avec des quantités disponibles
        $productQuery = "
            SELECT p.id, p.product_name, am.expression_id, ip.id as project_id
            FROM products p 
            JOIN achats_materiaux am ON LOWER(TRIM(am.designation)) = LOWER(TRIM(p.product_name))
            JOIN identification_projet ip ON am.expression_id = ip.idExpression
            WHERE am.status = 'reçu' AND am.quantity > 0
            LIMIT 1
        ";

        $productStmt = $pdo->query($productQuery);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [
                'success' => false,
                'error' => 'Aucun produit avec des quantités disponibles trouvé'
            ];
        }

        // 2. Trouver un projet de destination différent
        $destProjectQuery = "
            SELECT id FROM identification_projet 
            WHERE id != :source_project_id
            LIMIT 1
        ";

        $destStmt = $pdo->prepare($destProjectQuery);
        $destStmt->execute(['source_project_id' => $product['project_id']]);
        $destProject = $destStmt->fetch(PDO::FETCH_ASSOC);

        if (!$destProject) {
            return [
                'success' => false,
                'error' => 'Aucun projet de destination trouvé'
            ];
        }

        // 3. Créer un transfert fictif
        $transfert = [
            'product_id' => $product['id'],
            'product_name' => $product['product_name'],
            'source_project_id' => $product['project_id'],
            'destination_project_id' => $destProject['id'],
            'quantity' => 1,  // Quantité minimale pour le test
            'status' => 'pending'
        ];

        // 4. Utiliser l'API de vérification des transferts pour simuler
        $apiUrl = "check_transfer_effect.php?product_name=" . urlencode($product['product_name']) .
            "&source_project_id=" . $product['project_id'] .
            "&output=json";

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'transfert' => $transfert,
                'simulation' => $result
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Erreur lors de la simulation du transfert',
                'transfert' => $transfert
            ];
        }
    },

    'Vérifier un transfert existant' => function () use ($pdo) {
        global $pdo;

        // Trouver un transfert existant
        $transfertQuery = "SELECT id FROM transferts LIMIT 1";
        $transfertStmt = $pdo->query($transfertQuery);
        $transfert = $transfertStmt->fetch(PDO::FETCH_ASSOC);

        if (!$transfert) {
            return [
                'success' => false,
                'error' => 'Aucun transfert trouvé dans la base de données'
            ];
        }

        // Utiliser l'API pour vérifier ce transfert
        $apiUrl = "check_transfer_effect.php?transfert_id=" . $transfert['id'] . "&output=json";

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'transfert_id' => $transfert['id'],
                'details' => $result
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Erreur lors de la vérification du transfert',
                'transfert_id' => $transfert['id']
            ];
        }
    },

    'Vérifier les quantités disponibles pour un produit' => function () use ($pdo) {
        global $pdo;

        // Trouver un produit existant
        $productQuery = "SELECT id, product_name FROM products WHERE quantity > 0 LIMIT 1";
        $productStmt = $pdo->query($productQuery);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [
                'success' => false,
                'error' => 'Aucun produit avec des quantités trouvé'
            ];
        }

        // Rechercher les projets qui ont ce produit
        $projectsQuery = "
            SELECT ip.id, ip.code_projet, ip.nom_client, 
                  (SELECT SUM(quantity) FROM achats_materiaux 
                   WHERE expression_id = ip.idExpression 
                   AND LOWER(TRIM(designation)) = LOWER(TRIM(:product_name))
                   AND status = 'reçu') as received_quantity
            FROM identification_projet ip
            JOIN achats_materiaux am ON am.expression_id = ip.idExpression
            WHERE LOWER(TRIM(am.designation)) = LOWER(TRIM(:product_name))
            GROUP BY ip.id, ip.code_projet, ip.nom_client
            HAVING received_quantity > 0
            LIMIT 5
        ";

        $projectsStmt = $pdo->prepare($projectsQuery);
        $projectsStmt->execute(['product_name' => $product['product_name']]);
        $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'product' => $product,
            'projects_with_quantities' => $projects,
            'message' => count($projects) > 0
                ? 'Des projets avec des quantités disponibles ont été trouvés'
                : 'Aucun projet avec des quantités disponibles pour ce produit'
        ];
    }
];

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tests des transferts - DYM STOCK</title>
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
    </style>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-semibold">Tests des fonctionnalités de transfert</h1>
                <div class="flex space-x-3">
                    <a href="../transfert_manager.php" class="flex items-center text-gray-600 hover:text-gray-900">
                        <span class="material-icons mr-1">arrow_back</span>
                        Retour
                    </a>
                </div>
            </div>

            <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">
                <p><span class="font-bold">Note:</span> Cette page exécute des tests automatisés pour vérifier les
                    fonctionnalités de transfert. N'utilisez cette page qu'à des fins de débogage.</p>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <?php
                // Exécuter chaque test
                foreach ($tests as $name => $callback) {
                    runTest($name, $callback);
                }
                ?>
            </div>
        </div>
    </div>
</body>

</html>