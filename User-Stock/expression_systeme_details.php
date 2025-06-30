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

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Rediriger vers la page dashboard si aucun ID n'est fourni
    header("Location: dashboard.php");
    exit();
}

$idBesoin = $_GET['id'];
$userId = $_SESSION['user_id'];

// Inclure la connexion à la base de données
include_once '../database/connection.php';

try {
    // Récupérer les informations du demandeur
    $stmt_demandeur = $pdo->prepare("
        SELECT * FROM demandeur WHERE idBesoin = :idBesoin
    ");
    $stmt_demandeur->bindParam(':idBesoin', $idBesoin);
    $stmt_demandeur->execute();
    $demandeur = $stmt_demandeur->fetch(PDO::FETCH_ASSOC);

    if (!$demandeur) {
        throw new Exception("Expression de besoin non trouvée.");
    }

    // Récupérer les besoins associés
    $stmt_besoins = $pdo->prepare("
        SELECT * FROM besoins WHERE idBesoin = :idBesoin
    ");
    $stmt_besoins->bindParam(':idBesoin', $idBesoin);
    $stmt_besoins->execute();
    $besoins = $stmt_besoins->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

// En-tête HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Expression Système</title>
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
        .reject-btn {
            border: 2px solid #e53e3e;
            color: #e53e3e;
            padding: 8px 18px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            background-color: transparent;
            transition: color 0.3s, border-color 0.3s;
        }
        .reject-btn:hover {
            color: #c53030;
            border-color: #c53030;
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
        .info-section {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .info-title {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        .info-item {
            margin-bottom: 12px;
        }
        .info-label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 16px;
            color: #2d3748;
            font-weight: 500;
        }
        .table-container {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background-color: #f9fafb;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 1px solid #e2e8f0;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        tr:hover {
            background-color: #f9fafb;
        }
        input[type="number"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            background-color: #f9fafb;
        }
        input[type="number"]:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="wrapper">
        <?php include '../components/navbar_stock.php'; ?>

        <main class="flex-1 p-6">
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
                <div class="flex space-x-2">
                    <button class="validate-btn" onclick="validateExpression()">Valider l'expression</button>
                    <button class="reject-btn" onclick="rejectExpression()">Rejeter</button>
                </div>
                <h1 class="text-xl font-semibold">Détails Expression de Besoin Système</h1>
                <div class="date-time">
                    <span class="material-icons">calendar_today</span>
                    <span id="date-time-display"></span>
                </div>
            </div>

            <div class="container mx-auto">
                <div class="info-section">
                    <h2 class="info-title">Informations du demandeur</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">ID Besoin</div>
                            <div class="info-value"><?php echo htmlspecialchars($demandeur['idBesoin']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Service Demandeur</div>
                            <div class="info-value"><?php echo htmlspecialchars($demandeur['service_demandeur']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Nom & Prénoms</div>
                            <div class="info-value"><?php echo htmlspecialchars($demandeur['nom_prenoms']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date Demande</div>
                            <div class="info-value"><?php echo htmlspecialchars($demandeur['date_demande']); ?></div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="info-label">Motif de la demande</div>
                        <div class="info-value p-2 bg-white rounded border border-gray-200">
                            <?php echo nl2br(htmlspecialchars($demandeur['motif_demande'])); ?>
                        </div>
                    </div>
                </div>

                <div class="table-container bg-white mt-6">
                    <form id="validation-form">
                        <input type="hidden" name="idBesoin" value="<?php echo htmlspecialchars($idBesoin); ?>">
                        <input type="hidden" name="user_stock" value="<?php echo htmlspecialchars($userId); ?>">
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>Désignation</th>
                                    <th>Caractéristique</th>
                                    <th>Quantité Demandée</th>
                                    <th>Quantité en Stock</th>
                                    <th>Quantité à Acheter</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($besoins as $besoin): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($besoin['designation_article']); ?></td>
                                    <td><?php echo htmlspecialchars($besoin['caracteristique']); ?></td>
                                    <td><?php echo htmlspecialchars($besoin['qt_demande']); ?></td>
                                    <td>
                                        <input type="number" name="qt_stock[<?php echo $besoin['id']; ?>]" 
                                            value="<?php echo !empty($besoin['qt_stock']) ? htmlspecialchars($besoin['qt_stock']) : ''; ?>" 
                                            min="0" class="qt-stock-input" 
                                            data-demanded="<?php echo htmlspecialchars($besoin['qt_demande']); ?>"
                                            data-id="<?php echo $besoin['id']; ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="qt_acheter[<?php echo $besoin['id']; ?>]" 
                                            value="<?php echo !empty($besoin['qt_acheter']) ? htmlspecialchars($besoin['qt_acheter']) : ''; ?>" 
                                            min="0" class="qt-acheter-input" 
                                            data-id="<?php echo $besoin['id']; ?>" readonly>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                </div>
            </div>
        </main>

        <?php include '../components/footer.html'; ?>
    </div>

    <script>
        // Mise à jour de la date et de l'heure
        function updateDateTime() {
            const now = new Date();
            const formattedDate = `${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
            document.getElementById('date-time-display').textContent = formattedDate;
        }

        setInterval(updateDateTime, 1000); // Mise à jour toutes les secondes

        // Calcul automatique de la quantité à acheter
        document.querySelectorAll('.qt-stock-input').forEach(input => {
            input.addEventListener('input', function() {
                const id = this.getAttribute('data-id');
                const qtDemande = parseInt(this.getAttribute('data-demanded'));
                const qtStock = parseInt(this.value) || 0;
                
                const qtAcheter = Math.max(0, qtDemande - qtStock);
                
                document.querySelector(`.qt-acheter-input[data-id="${id}"]`).value = qtAcheter;
            });
        });

        // Validation de l'expression
        function validateExpression() {
            // Vérifier que toutes les quantités sont renseignées
            const stockInputs = document.querySelectorAll('.qt-stock-input');
            let allFilled = true;
            
            stockInputs.forEach(input => {
                if (input.value === '') {
                    allFilled = false;
                }
            });

            if (!allFilled) {
                alert('Veuillez renseigner toutes les quantités en stock avant de valider.');
                return;
            }

            // Récupérer les données du formulaire
            const form = document.getElementById('validation-form');
            const formData = new FormData(form);

            // Envoyer les données au serveur
            fetch('traitement_systeme/validate_expression_systeme.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Expression validée avec succès !');
                    // Rediriger vers le dashboard
                    window.location.href = 'dashboard.php';
                } else {
                    alert('Erreur lors de la validation: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la validation.');
            });
        }

        // Rejet de l'expression
        function rejectExpression() {
            if (confirm('Êtes-vous sûr de vouloir rejeter cette expression ?')) {
                const idBesoin = '<?php echo htmlspecialchars($idBesoin); ?>';
                
                fetch('traitement_systeme/reject_expression_systeme.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        idBesoin: idBesoin
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Expression rejetée.');
                        window.location.href = 'dashboard.php';
                    } else {
                        alert('Erreur lors du rejet: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors du rejet.');
                });
            }
        }

        // Initialiser la page
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            
            // Déclencher l'événement input pour calculer les quantités à acheter initiales
            document.querySelectorAll('.qt-stock-input').forEach(input => {
                const event = new Event('input', {
                    bubbles: true,
                    cancelable: true,
                });
                input.dispatchEvent(event);
            });
        });
    </script>
</body>
</html>