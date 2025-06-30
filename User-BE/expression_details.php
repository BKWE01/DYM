<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Rediriger vers index.php
    header("Location: ./../index.php");
    exit();
}

// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Récupération de l'ID de l'expression depuis l'URL
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';

    if (empty($id)) {
        throw new Exception('ID invalide.');
    }

    // Préparation et exécution de la requête pour obtenir les détails de l'expression
    $stmt = $pdo->prepare("
        SELECT id, idExpression, designation, unit, quantity, type, created_at
        FROM expression_dym
        WHERE idExpression = :id
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();

    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expressions)) {
        throw new Exception('Aucune expression trouvée pour cet ID.');
    }

    // Préparer la requête pour obtenir les détails du projet
    $stmt_projet = $pdo->prepare("
        SELECT code_projet, description_projet, sitgeo, chefprojet, production
        FROM identification_projet
        WHERE idExpression = :id
    ");
    $stmt_projet->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt_projet->execute();

    $projetDetails = $stmt_projet->fetch(PDO::FETCH_ASSOC);

    if (!$projetDetails) {
        throw new Exception('Détails du projet non trouvés pour cet ID.');
    }

    // Récupérer la date de création pour l'afficher une seule fois
    $creationDate = date('d/m/Y', strtotime($expressions[0]['created_at']));
    $idExpress = ($expressions[0]['idExpression']);

} catch (PDOException $e) {
    $error = $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails des Expressions</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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
        .date-time span {
            font-weight: 500;
            line-height: 1.4;
        }

        .table-width {
            width: 100%;
        }
        .container {
            width: 93%;
            margin: 30px auto;
            padding: 10px;
        }
        .title {
            text-align: left;
            margin-bottom: 20px;
            font-size: 20px;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .form-table th, .form-table td {
            padding: 8px;
            border: 1px solid #ccc;
            text-align: left;
            min-width: 200px; /* Largeur minimale pour les cellules */
            vertical-align: middle; /* Aligner verticalement au milieu */
        }
        .form-table th {
            background-color: #f9f9f9;
            font-weight: bold;
        }
        .input-text {
            width: 100%; /* Les inputs occuperont toute la largeur de la cellule */
            padding: 8px;
            border: none;
            box-sizing: border-box; /* Assure que le padding ne dépasse pas la largeur */
            background-color: #f3f4f6; /* Couleur de fond modifiée */
        }
        .input-text:focus {
            outline: none; /* Retirer le contour par défaut */
        }
        .checkbox-group {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
        }

        /* Styles pour centrer le titre entre les éléments */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header-title {
            text-align: center;
            margin: 0;
            font-size: 18px;
        }
    </style>
</head>
<body class="bg-gray-100">
   <div class="wrapper">
        <!-- Navbar -->
        <?php include_once '../components/navbar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4">
                <div class="header-container">
                    <h2 class="header-title">Expressions De Besoins n° <?php echo htmlspecialchars($idExpress); ?> - Créées Le <?php echo htmlspecialchars($creationDate); ?></h2>
                    <div class="date-time">
                        <span class="material-icons">calendar_today</span>
                        <span id="date-time-display"></span>
                    </div>
                </div>
            </div>
            <div class="container">
                <h2 class="title">IDENTIFICATION DU PROJET</h2>
                <table class="form-table">
                    <tr>
                        <th class="label-code">Code Projet</th>
                        <td><?php echo htmlspecialchars($projetDetails['code_projet']); ?></td>
                    </tr>
                    <tr>
                        <th class="label-description">Description du Projet</th>
                        <td><?php echo htmlspecialchars($projetDetails['description_projet']); ?></td>
                    </tr>
                    <tr>
                        <th class="label-location">Situation géographique du chantier</th>
                        <td><?php echo htmlspecialchars($projetDetails['sitgeo']); ?></td>
                    </tr>
                    <tr>
                        <th class="label-manager">Chef de Projet</th>
                        <td><?php echo htmlspecialchars($projetDetails['chefprojet']); ?></td>
                    </tr>
                    <tr>
                        <th class="label-production">Production</th>
                        <td><?php echo htmlspecialchars($projetDetails['production']); ?></td>
                    </tr>
                </table>
            </div>
            <?php
// Trier les expressions par 'type'
usort($expressions, function($a, $b) {
    return strcmp($a['type'], $b['type']);
});

// Regrouper les expressions par type
$groupedExpressions = [];
foreach ($expressions as $expression) {
    $groupedExpressions[$expression['type']][] = $expression;
}
?>

<div class="container">
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Erreur :</strong>
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php else: ?>
        <?php if (count($expressions) > 0): ?>
            <h2 class="title">BESOIN IMMÉDIAT(S) DU PROJET</h2>
            <table class="table-width bg-white border border-gray-300">
                <thead>
                    <tr>
                        <th class="py-2 px-3 border-b">Désignation</th>
                        <th class="py-2 px-3 border-b">Unité</th>
                        <th class="py-2 px-3 border-b">Quantité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedExpressions as $type => $items): ?>
                        <tr>
                            <td colspan="4" class="py-2 px-3 bg-gray-200 border-b-2 border-gray-500 text-lg font-semibold">
                                <?php echo htmlspecialchars($type); ?>
                            </td>
                        </tr>
                        <?php foreach ($items as $expression): ?>
                            <tr>
                                <td class="py-2 px-3 border-b text-center"><?php echo htmlspecialchars($expression['designation']); ?></td>
                                <td class="py-2 px-3 border-b text-center"><?php echo htmlspecialchars($expression['unit']); ?></td>
                                <td class="py-2 px-3 border-b text-center"><?php echo htmlspecialchars($expression['quantity']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
    .type-cell {
        border-left: 1px solid #ddd; /* Bordure à gauche pour la cellule de type */
        border-right: 1px solid #ddd; /* Bordure à droite pour la cellule de type */
        border-bottom: 3px solid #333; /* Bordure plus épaisse en bas pour séparer les groupes */
    }
    table {
        border-collapse: collapse; /* Assurez-vous que les bordures se chevauchent correctement */
    }
</style>



        </main>
    </div>

    <!-- Footer -->
    <?php include_once '../components/footer.html'; ?>

    <script>
        // JavaScript pour Mobile Menu Toggle, etc.
        document.addEventListener('DOMContentLoaded', function () {
            const mobileMenuButton = document.querySelector('[aria-controls="mobile-menu"]');
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenuButton.addEventListener('click', function () {
                mobileMenu.classList.toggle('hidden');
            });
        });

        function updateDateTime() {
            const now = new Date();
            const formattedDate = `${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
            document.getElementById('date-time-display').textContent = formattedDate;
        }

        setInterval(updateDateTime, 1000); // Mise à jour de l'heure toutes les secondes
    </script>
</body>
</html>
