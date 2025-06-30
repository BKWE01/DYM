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
        SELECT id, idBesoin, designation_article, caracteristique, qt_demande, qt_stock, created_at
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
        .disabled-input {
            background-color: #f5f5f5; /* Couleur de fond gris clair */
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2); /* Ombre grise */
            border: none; /* Retirer la bordure */
            outline: none; /* Retirer le contour lors du focus */
        }

        .disabled-input:focus {
            border: none; /* Assurer qu'il n'y a pas de bordure lors du focus */
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2); /* Maintenir l'ombre grise lors du focus */
        }

        .disabled-input:hover {
            border: none; /* Assurer qu'il n'y a pas de bordure lors du survol */
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2); /* Maintenir l'ombre grise lors du survol */
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
        margin-bottom: 18px;
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
        }
        .title {
      text-align: left;
      margin-bottom: 20px;
    }

    .container-tab {
    width: 100%;
    margin: 30px auto;
    display: flex;
    justify-content: center;
}

.demande-section {
    width: 90%;
    border: 2px solid #000;
    display: flex;
    flex-direction: column;
    border-collapse: collapse;
}

.demande-header, .demande-content {
    display: flex;
}

.demande-header {
    background-color: #f0f0f0;
    font-weight: bold;
}

.header-item, .content-item {
    border: 1px solid #000;
    padding: 15px;
    text-align: center;
    flex: 1;
}

.demande-content {
    border-top: none; /* Enlève la bordure supérieure pour éviter la double bordure */
}

.demande-motif {
    border-top: 1px solid #000;
    padding: 15px;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    text-align: left;
    padding-left: 10px;
    font-weight: bold;
}

#motif_demande {
    height: 60px; /* Ajuster selon besoin */
    resize: none;
}

    </style>
</head>
<body class="bg-gray-100">
   <div class="wrapper">
        <!-- Navbar -->
        <?php include_once '../components/navbar_stock.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
                <button id="validate-prices" class="validate-btn">Valider les  quantités en stock</button>
                <?php 
                        // Récupérer la date de création pour l'afficher une seule fois
                        $creationDate = date('d/m/Y', strtotime($expressions[0]['created_at']));
                        $idExpress = ($expressions[0]['idBesoin']);
                        ?>
                        <h2 class="header-title">Expressions de besoin un projet n° <?php echo htmlspecialchars($idExpress); ?> - Créées le <?php echo htmlspecialchars($creationDate); ?></h2>
                <div class="date-time">
                    <span class="material-icons">calendar_today</span>
                    <span id="date-time-display"></span>
                </div>
            </div>
            
            <div class="container-tab">
                <div class="demande-section">
                    <div class="demande-header">
                        <div class="header-item" id="service-label">SERVICE DEMANDEUR</div>
                        <div class="header-item" id="nom-prenoms-label">NOM & PRÉNOMS DEMANDEUR</div>
                        <div class="header-item" id="date-demande-label">DATE DEMANDE</div>
                    </div>
                    <div class="demande-content">
                        <div class="content-item" id="service_demandeur"><?php echo htmlspecialchars($projetDetails['service_demandeur']); ?></div>
                        <div class="content-item" id="nom_prenoms"><?php echo htmlspecialchars($projetDetails['nom_prenoms']); ?></div>
                        <div class="content-item" id="date_demande"><?php echo htmlspecialchars($projetDetails['date_demande']); ?></div>
                    </div>
                    <div class="demande-motif">
                        <label for="motif_demande" class="form-label" id="motif-label">
                            <u><em>Motif de la demande :</em></u>
                        </label>
                        <div id="motif_demande"><?php echo htmlspecialchars($projetDetails['motif_demande']); ?></div>
                    </div>
                </div>
            </div>

            <div class="container mx-auto p-6">
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <strong class="font-bold">Erreur :</strong>
                        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php else: ?>
                    <?php if (count($expressions) > 0): ?>
                        <h2 class="title">BESOIN EMMEDIAT(S) DU PROJET</h2>
                        <table class="min-w-full bg-white border border-gray-300">
                            <thead>
                                <tr>
                                    <th class="py-2 px-3 border-b text-left">Désignation</th>
                                    <th class="py-2 px-3 border-b">Unité</th>
                                    <th class="py-2 px-3 border-b">Quantité demandé</th>
                                    <th class="py-2 px-3 border-b">Quantité en stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expressions as $expression): ?>
                                    <tr>
                                        <td class="py-2 px-3 border-b"><?php echo htmlspecialchars($expression['designation_article']); ?></td>
                                        <td class="py-2 px-3 border-b text-center"><?php echo htmlspecialchars($expression['caracteristique']); ?></td>
                                        <td class="py-2 px-3 border-b text-center">
                                            <input type="number" id="quantite-<?php echo htmlspecialchars($expression['id']); ?>" name="quantite[<?php echo htmlspecialchars($expression['id']); ?>]" value="<?php echo htmlspecialchars($expression['qt_demande']); ?>" class="w-full p-1 rounded text-center disabled-input" onchange="updateMontant(<?php echo htmlspecialchars($expression['id']); ?>)" oninput="updateMontant(<?php echo htmlspecialchars($expression['id']); ?>)" readonly>
                                        </td>
                                        <td class="py-2 px-3 border-b text-center">
                                            <input type="text" id="qt-stock-<?php echo htmlspecialchars($expression['id']); ?>" name="qt-stock[<?php echo htmlspecialchars($expression['id']); ?>]" value="<?php echo htmlspecialchars($expression['qt_stock']); ?>" class="w-full border border-gray-100 p-1 rounded text-center disabled-input" onchange="updateMontant(<?php echo htmlspecialchars($expression['id']); ?>)" oninput="updateMontant(<?php echo htmlspecialchars($expression['id']); ?>)">
                                        </td>
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
    document.getElementById('validate-prices').addEventListener('click', function() {
        const data = [];
        document.querySelectorAll('tbody tr').forEach(row => {
            const id = row.querySelector('input[name^="quantite"]').name.match(/\d+/)[0];
            const qt_demande = parseFloat(row.querySelector(`#quantite-${id}`).value) || 0;
            const qtStock = parseFloat(row.querySelector(`#qt-stock-${id}`).value) || 0;

            // Pousser les données sans qt_acheter, car le calcul se fera dans PHP
            data.push({
                id: id,
                qt_stock: qtStock,
                qt_demande: qt_demande
            });
        });

        fetch('update_besoin_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ expressions: data })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Quantités mises à jour avec succès.');
            } else {
                alert('Erreur lors de la mise à jour des quantités.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de la mise à jour des quantités.');
        });
    });


   function updateDateTime() {
        const now = new Date();
        const formattedDate = `${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
        document.getElementById('date-time-display').textContent = formattedDate;
      }

      setInterval(updateDateTime, 1000); // Update time every second
</script>
</body>
</html>
