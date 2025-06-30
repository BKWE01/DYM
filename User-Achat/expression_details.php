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
        SELECT id, idExpression, designation, unit, quantity, type, qt_stock, qt_acheter, prix_unitaire, montant, entity, created_at
        FROM expression_dym
        WHERE idExpression = :id
        ORDER BY type
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();

    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expressions)) {
        throw new Exception('Aucune expression trouvée pour cet ID.');
    }

} catch (PDOException $e) {
    $error = $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php
// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Récupération de l'ID de l'expression depuis l'URL
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';

    if (empty($id)) {
        throw new Exception('ID invalide.');
    }

    // Préparer la requête pour obtenir les détails du projet
    $stmt_projet = $pdo->prepare("
        SELECT code_projet, nom_client, description_projet, sitgeo, chefprojet
        FROM identification_projet
        WHERE idExpression = :id
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
            margin-left: 8px;
            /* Espacement entre le bouton PDF et la date-time */
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
            background-color: #f5f5f5;
            /* Couleur de fond gris clair */
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
            /* Ombre grise */
            border: none;
            /* Retirer la bordure */
            outline: none;
            /* Retirer le contour lors du focus */
        }

        .disabled-input:focus {
            border: none;
            /* Assurer qu'il n'y a pas de bordure lors du focus */
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
            /* Maintenir l'ombre grise lors du focus */
        }

        .disabled-input:hover {
            border: none;
            /* Assurer qu'il n'y a pas de bordure lors du survol */
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
            /* Maintenir l'ombre grise lors du survol */
        }

        .pdf-btn {
            border: 2px solid #e53e3e;
            /* Couleur rouge pour le bouton PDF */
            color: #e53e3e;
            /* Couleur de l'icône */
            padding: 5px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-size: 22px;
            /* Ajuste la taille de l'icône PDF */
            background-color: transparent;
            transition: color 0.3s, border-color 0.3s;
        }

        .pdf-btn:hover {
            color: #c53030;
            /* Couleur de l'icône au survol */
            border-color: #c53030;
            /* Couleur de la bordure au survol */
        }

        .pdf-btn .material-icons {
            vertical-align: middle;
            /* Alignement de l'icône au centre */
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
        }

        .form-table {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .form-row {
            display: table-row;
        }

        .form-row div {
            display: table-cell;
            padding: 8px;
            border: 1px solid #ccc;
            vertical-align: middle;
        }

        .form-row div.label {
            font-weight: bold;
            background-color: #f9f9f9;
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

        .dashboard-btn {
            background-color: #f3f4f6;
            color: #4b5563;
            border: none;
            padding: 0.625rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .dashboard-btn:hover {
            background-color: #e5e7eb;
            color: #1f2937;
            transform: translateY(-2px);
        }

        .dashboard-btn .material-icons {
            font-size: 1.25rem;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <!-- Navbar -->
        <?php include_once '../components/navbar_achat.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
                <button id="validate-prices" class="validate-btn">Valider les prix</button>
                <?php
                $creationDate = date('d/m/Y', strtotime($expressions[0]['created_at']));
                $idExpress = ($expressions[0]['idExpression']);
                ?>

                <a href="dashboard.php" class="dashboard-btn ml-4">
                    <span class="material-icons">arrow_back</span>
                    <span>Retour au tableau de bord</span>
                </a>

                <h2 class="header-title">Détails des Expressions n° <?php echo htmlspecialchars($idExpress); ?> - Créées
                    le <?php echo htmlspecialchars($creationDate); ?></h2>
                <div class="date-time-container flex items-center">
                    <div class="date-time">
                        <span class="material-icons">calendar_today</span>
                        <span id="date-time-display"></span>
                    </div>
                </div>
            </div>
            <div class="container">
                <h2 class="title">IDENTIFICATION DU PROJET</h2>

                <div class="form-table">
                    <div class="form-row">
                        <div class="label">Code Projet</div>
                        <div>
                            <?php echo htmlspecialchars($projetDetails['code_projet']); ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="label">Nom du client</div>
                        <div>
                            <?php echo htmlspecialchars($projetDetails['nom_client']); ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="label">Description du Projet</div>
                        <div>
                            <?php echo htmlspecialchars($projetDetails['description_projet']); ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="label">Situation géographique du chantier</div>
                        <div>
                            <?php echo htmlspecialchars($projetDetails['sitgeo']); ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="label">Chef de Projet</div>
                        <div>
                            <?php echo htmlspecialchars($projetDetails['chefprojet']); ?>
                        </div>
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
                        <h2 class="title">BESOIN IMMÉDIAT(S) DU PROJET</h2>
                        <table class="min-w-full bg-white border border-gray-300 mb-4 text-sm">
                            <thead>
                                <tr>
                                    <th class="py-2 px-3 border-b text-left">Désignation</th>
                                    <th class="py-2 px-3 border-b">Unité</th>
                                    <th class="py-2 px-3 border-b">Quantité demandée</th>
                                    <th class="py-2 px-3 border-b">Quantité en Stock</th>
                                    <th class="py-2 px-3 border-b">Quantité à acheter</th>
                                    <th class="py-2 px-3 border-b">PU</th>
                                    <th class="py-2 px-3 border-b">Montant</th>
                                    <th class="py-2 px-3 border-b">Fournisseur</th>
                                    <th class="py-2 px-3 border-b">Mode de paiement</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $currentType = '';
                                foreach ($expressions as $expression):
                                    if ($expression['type'] !== $currentType):
                                        if ($currentType !== ''):
                                            // Fermer le groupe précédent
                                            echo '</tbody>';
                                        endif;
                                        $currentType = $expression['type'];
                                        // Afficher le titre du type
                                        echo '<tr><td colspan="9" class="py-2 px-3 bg-gray-200 border-b-2 border-gray-500 text-lg font-semibold">' . htmlspecialchars($currentType) . '</td></tr>';
                                        // Ouvrir un nouveau groupe
                                        echo '<tbody>';
                                    endif;
                                    ?>
                                    <tr>
                                        <td class="py-2 px-3 border-b text-sm">
                                            <?php echo htmlspecialchars($expression['designation']); ?>
                                        </td>
                                        <td class="py-2 px-3 border-b text-center text-sm">
                                            <?php echo htmlspecialchars($expression['unit']); ?>
                                        </td>
                                        <td class="py-2 px-3 border-b text-center text-sm">
                                            <?php echo htmlspecialchars($expression['quantity']); ?>
                                        </td>
                                        <td class="py-2 px-3 border-b text-center text-sm">
                                            <?php echo htmlspecialchars($expression['qt_stock']); ?>
                                        </td>
                                        <td class="py-2 px-3 border-b text-center text-sm">
                                            <input type="number"
                                                id="quantite-acheter-<?php echo htmlspecialchars($expression['id']); ?>"
                                                name="quantite-acheter[<?php echo htmlspecialchars($expression['id']); ?>]"
                                                value="<?php echo htmlspecialchars($expression['qt_acheter']); ?>"
                                                class="w-full p-1 rounded text-center disabled-input" readonly>
                                        </td>
                                        <td class="py-2 px-3 border-b text-center text-sm">
                                            <input type="number" id="pu-<?php echo htmlspecialchars($expression['id']); ?>"
                                                name="pu[<?php echo htmlspecialchars($expression['id']); ?>]"
                                                value="<?php echo htmlspecialchars($expression['prix_unitaire']); ?>"
                                                class="w-full border border-gray-100 p-1 rounded text-center disabled-input">
                                        </td>
                                        <td class="py-2 px-3 border-b text-center text-sm">
                                            <input type="text" id="montant-<?php echo htmlspecialchars($expression['id']); ?>"
                                                name="montant[<?php echo htmlspecialchars($expression['id']); ?>]"
                                                value="<?php echo htmlspecialchars($expression['montant']); ?>"
                                                class="w-full p-1 rounded text-center disabled-input" readonly>
                                        </td>
                                        <td class="py-2 px-3 border-b text-center text-sm">
                                            <input type="text" id="fournisseur-<?php echo htmlspecialchars($expression['id']); ?>"
                                                name="fournisseur[<?php echo htmlspecialchars($expression['id']); ?>]"
                                                value="<?php echo htmlspecialchars($expression['fournisseur'] ?? ''); ?>"
                                                class="w-64 p-1 border border-gray-100 rounded text-center disabled-input">
                                        </td>
                                        <td class="py-2 px-3 border-b text-center text-sm">
                                            <select id="mode-paiement-<?php echo htmlspecialchars($expression['id']); ?>"
                                                name="mode-paiement[<?php echo htmlspecialchars($expression['id']); ?>]"
                                                class="w-64 p-1 border border-gray-100 rounded text-center disabled-input">
                                                <option value="Carte bancaire">Carte bancaire</option>
                                                <option value="Virement bancaire">Virement bancaire</option>
                                                <option value="Chèque">Chèque</option>
                                                <option value="Espèces">Espèces</option>
                                            </select>
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
        // Fonction pour mettre à jour le montant
        function updateMontant(id) {
            const pu = parseFloat(document.getElementById(`pu-${id}`).value) || 0;
            const quantiteAcheter = parseFloat(document.getElementById(`quantite-acheter-${id}`).value) || 0;
            const montant = pu * quantiteAcheter;
            document.getElementById(`montant-${id}`).value = montant.toFixed(2);
        }

        // Fonction pour valider et envoyer les données
        document.getElementById('validate-prices').addEventListener('click', function () {
            const data = [];
            const expressions = <?php echo json_encode($expressions); ?>;

            expressions.forEach(expression => {
                const id = expression.id;
                const pu = document.getElementById(`pu-${id}`)?.value;
                const quantiteAcheter = document.getElementById(`quantite-acheter-${id}`)?.value;
                const montant = document.getElementById(`montant-${id}`)?.value;
                const fournisseur = document.getElementById(`fournisseur-${id}`)?.value;
                const modePaiement = document.getElementById(`mode-paiement-${id}`)?.value;

                if (pu && quantiteAcheter && montant) {
                    data.push({
                        id: id,
                        pu: pu,
                        quantiteAcheter: quantiteAcheter,
                        montant: montant,
                        fournisseur: fournisseur || '',
                        modePaiement: modePaiement || 'Carte bancaire'
                    });
                }
            });

            if (data.length === 0) {
                alert('Erreur : Impossible de collecter les données du formulaire. Vérifiez que tous les champs requis sont remplis.');
                return;
            }

            console.log('Données à envoyer:', data); // Pour débogage

            fetch('update_prices.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ expressions: data })
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur réseau');
                    }
                    return response.json();
                })
                .then(result => {
                    if (result.success) {
                        alert('Prix validés avec succès !');
                        const id = new URLSearchParams(window.location.search).get('id');
                        if (id) {
                            window.open(`generate_pdf.php?id=${id}`, '_blank');
                            window.location.href = 'dashboard.php';
                        }
                    } else {
                        throw new Error(result.message || 'Erreur lors de la mise à jour');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de la validation: ' + error.message);
                });
        });

        // Initialiser les événements de mise à jour
        document.querySelectorAll('input[id^="pu-"]').forEach(input => {
            const id = input.id.split('-')[1];
            input.addEventListener('input', () => updateMontant(id));
        });

        // Affichage de l'heure (si l'élément existe)
        const timeDisplay = document.getElementById('date-time-display');
        if (timeDisplay) {
            function updateDateTime() {
                const now = new Date();
                timeDisplay.textContent = `${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
            }
            updateDateTime();
            setInterval(updateDateTime, 1000);
        }
    </script>

</body>

</html>