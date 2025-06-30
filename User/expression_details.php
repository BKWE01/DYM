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
        SELECT id, idBesoin, designation_article, caracteristique, qt_demande, created_at
        FROM besoins
        WHERE idBesoin = :id
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();

    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expressions)) {
        throw new Exception('Aucune expression trouvée pour cet ID.');
    }

    // Préparer la requête pour obtenir les détails du projet
    $stmt_projet = $pdo->prepare("
        SELECT idBesoin, nom_prenoms, service_demandeur, motif_demande, date_demande
        FROM demandeur
        WHERE idBesoin = :id
    ");
    $stmt_projet->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt_projet->execute();

    $projetDetails = $stmt_projet->fetch(PDO::FETCH_ASSOC);

    if (!$projetDetails) {
        throw new Exception('Détails du projet non trouvés pour cet ID.');
    }

    // Récupérer la date de création pour l'afficher une seule fois
    $creationDate = date('d/m/Y', strtotime($expressions[0]['created_at']));
    $idExpress = ($expressions[0]['idBesoin']);

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


        /* Styles pour centrer le titre entre les éléments */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header-title {
            flex-grow: 1;
            text-align: center;
            margin: 0;
            font-size: 18px;
            font-weight: 200;
        }

        .container-tab {
        width: 100%;
        margin: 30px auto;
        display: flex;
        justify-content: center;
    }

    .demande-table {
        width: 90%;
        border-collapse: collapse;
        border: 2px solid #000;
    }

    .demande-table th, .demande-table td {
        border: 1px solid #000;
        padding: 15px;
        text-align: center;
    }

    .demande-table th {
        background-color: #f0f0f0;
        font-weight: bold;
    }

    .demande-table td {
        padding: 0;
    }
    .form-textarea {
        height: 100px; /* Ajuster selon besoin */
        resize: none;
    }

    .form-label {
        display: block;
        margin-bottom: 5px;
        text-align: left;
        padding-left: 10px;
        font-weight: bold;
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
                    <button class="validate-btn">Détails Expression</button>
                    <h2 class="header-title">Détails des Expressions n° <?php echo htmlspecialchars($idExpress); ?> - Créées le <?php echo htmlspecialchars($creationDate); ?></h2>
                    <div class="date-time">
                        <span class="material-icons">calendar_today</span>
                        <span id="date-time-display"></span>
                    </div>
                </div>
            </div>
            
            <div class="container-tab">
                <table class="demande-table">
                    <tr>
                        <th id="service-label">SERVICE DEMANDEUR</th>
                        <th id="nom-prenoms-label">NOM & PRÉNOMS DEMANDEUR</th>
                        <th id="date-demande-label">DATE DEMANDE</th>
                    </tr>
                    <tr>
                        <td id="service_demandeur"><?php echo htmlspecialchars($projetDetails['service_demandeur']); ?></td>
                        <td id="nom_prenoms"><?php echo htmlspecialchars($projetDetails['nom_prenoms']); ?></td>
                        <td id="date_demande"><?php echo htmlspecialchars($projetDetails['date_demande']); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="motif">
                            <label for="motif_demande" class="form-label" id="motif-label">
                                <u><em>Motif de la demande :</em></u>
                            </label>
                            <div id="motif_demande"><?php echo htmlspecialchars($projetDetails['motif_demande']); ?></div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="container ">
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <strong class="font-bold">Erreur :</strong>
                        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php else: ?>
                    <?php if (count($expressions) > 0): ?>
                        <h2 class="title" style="font-size: 18px;">BESOIN EMMEDIAT(S)</h2>
                        <table class="table-width bg-white border border-gray-300">
                            <thead>
                                <tr>
                                    <th class="py-2 px-3 border-b text-left">Désignation article</th>
                                    <th class="py-2 px-3 border-b">Caractéristique</th>
                                    <th class="py-2 px-3 border-b text-right">Quantité</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expressions as $expression): ?>
                                    <tr>
                                        <td class="py-2 px-3 border-b"><?php echo htmlspecialchars($expression['designation_article']); ?></td>
                                        <td class="py-2 px-3 border-b text-center"><?php echo htmlspecialchars($expression['caracteristique']); ?></td>
                                        <td class="py-2 px-3 border-b text-right"><?php echo htmlspecialchars($expression['qt_demande']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
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
