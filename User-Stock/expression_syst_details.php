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
        SELECT service_demandeur, nom_prenoms, date_demande, motif_demande
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
            width: 93%;
        }
        
        .container {
            width: 100%;
            margin: 30px auto;
            display: flex;
            justify-content: center;
        }

        .demande-table {
            width: 90%;
            border: 2px solid #000;
        }

        .table-row {
            display: flex;
        }

        .header-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .table-cell {
            flex: 1;
            border: 1px solid #000;
            padding: 15px;
            text-align: center;
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

    .error-bubble {
        display: none;
        position: absolute;
        top: -40px;
        right: 10px; /* Légèrement décalé à droite */
        background-color: #f56565;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 0.875rem;
        white-space: nowrap;
        z-index: 1;
        width: auto; /* Laisse la largeur s'ajuster automatiquement au texte */
    }

    .error-bubble:after {
        content: "";
        position: absolute;
        bottom: -6px;
        right: 20px; /* Pointe alignée correctement avec la bulle */
        border-width: 6px;
        border-style: solid;
        border-color: #f56565 transparent transparent transparent;
    }

    .suggestion-item {
        padding: 8px 12px;
        cursor: pointer;
        text-align: left; /* Aligne le texte à gauche */
        transition: background-color 0.3s;
    }

    .suggestion-item:hover {
        background-color: #f1f5f9;
    }

    .create-option {
        font-weight: bold;
        color: #3b82f6;
        font-style: italic; /* Met le texte en italique */
    }

    .create-option:hover {
        background-color: #dbeafe;
    }

    .suggestions {
        border: 1px solid #e5e7eb;
        background-color: #fff;
        border-radius: 4px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        position: absolute;
    }

    /* CSS pour les inputs */
    .substitution-input, .qt-stock-input {
        border: none; /* Supprime complètement les bordures */
        height: 100%; /* Prend toute la hauteur de la cellule */
        padding: 8px; /* Ajuste le padding pour que ce soit plus agréable */
        outline: none; /* Supprime le contour par défaut sur le focus */
    }

    /* Supprimer l'effet de hover sur les inputs lorsqu'ils sont cliqués */
    .substitution-input:hover, .qt-stock-input:hover {
        background-color: inherit; /* Pas de changement de couleur au survol */
    }

    /* Garder l'effet de focus */
    .substitution-input:focus, .qt-stock-input:focus {
        box-shadow: none; /* Supprime tout effet de shadow sur focus */
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
                <button id="validate-prices" class="validate-btn">Valider les  quantités en stock</button>
                <?php 
                        // Récupérer la date de création pour l'afficher une seule fois
                        $creationDate = date('d/m/Y', strtotime($expressions[0]['created_at']));
                        $idExpress = ($expressions[0]['idBesoin']);
                        ?>
                        <h2 class="header-title">Expressions de besoin n° <?php echo htmlspecialchars($idExpress); ?> - Créées le <?php echo htmlspecialchars($creationDate); ?></h2>
                <div class="date-time">
                    <span class="material-icons">calendar_today</span>
                    <span id="date-time-display"></span>
                </div>
            </div>
            <div class="container">
                <div class="demande-table">
                    <div class="table-row header-row">
                        <div class="table-cell" id="service-label">SERVICE DEMANDEUR</div>
                        <div class="table-cell" id="nom-prenoms-label">NOM & PRÉNOMS DEMANDEUR</div>
                        <div class="table-cell" id="date-demande-label">DATE DEMANDE</div>
                    </div>
                    <div class="table-row">
                        <div class="table-cell">
                        <?php echo htmlspecialchars($projetDetails['service_demandeur']); ?>
                        </div>
                        <div class="table-cell">
                        <?php echo htmlspecialchars($projetDetails['nom_prenoms']); ?>
                        </div>
                        <div class="table-cell">
                        <?php echo htmlspecialchars($projetDetails['date_demande']); ?>
                        </div>
                    </div>
                    <div class="table-row">
                        <div class="table-cell" colspan="3" class="motif">
                            <label for="motif_demande" class="form-label" id="motif-label"><u><em>Motif de la demande :</em></u></label>
                            <?php echo htmlspecialchars($projetDetails['motif_demande']); ?>
                        </div>
                    </div>
                </div>
            </div>

            
<!-- Partie HTML pour le tableau et les inputs -->
<div class="table-width mx-auto p-6">
    <?php if (isset($error)): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>
        <table class="min-w-full table-auto bg-white shadow-md rounded">
            <thead>
                <tr class="bg-gray-800 text-white">
                    <th class="px-6 py-3 text-left">Désignation de l'article</th>
                    <th class="px-6 py-3 text-left">Caractéristique</th>
                    <th class="px-6 py-3 text-center">Quantité demandée</th>
                    <th class="px-6 py-3 text-center">Quantité en stock</th>
                    <th class="px-6 py-3 text-center">Substituer</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expressions as $expression): ?>
                    <tr class="border-t">
                        <td class="px-6 py-2 text-left border designation-article" id="designation-<?php echo htmlspecialchars($expression['id']); ?>">
                            <?php echo htmlspecialchars($expression['designation_article']); ?>
                        </td>
                        <td class="px-6 py-2 text-left border"><?php echo htmlspecialchars($expression['caracteristique']); ?></td>
                        <td class="px-6 py-2 text-center border"><?php echo htmlspecialchars($expression['qt_demande']); ?></td>
                        <td class="px-6 py-2 text-center border">
                            <input type="text" name="qt-stock-<?php echo htmlspecialchars($expression['id']); ?>" class="qt-stock-input w-full focus:outline-none focus:ring-2 focus:ring-blue-500" autocomplete="off"/>
                        </td>
                        <td class="px-6 py-2 text-center border">
                            <input type="text" name="substitution-<?php echo htmlspecialchars($expression['id']); ?>" 
                                   class="substitution-input w-96 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   data-id="<?php echo htmlspecialchars($expression['id']); ?>" autocomplete="off"/>
                            <div class="suggestions hidden absolute z-10 max-h-60 overflow-y-auto w-full" 
                                 id="suggestions-<?php echo htmlspecialchars($expression['id']); ?>"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal -->
<div id="createDesignationModal" class="hidden fixed z-10 inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg shadow-lg w-1/3">
        <h2 class="text-lg font-bold mb-4">Créer une nouvelle désignation</h2>
        <input type="text" id="newDesignation" class="border border-gray-300 p-2 w-full rounded focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4" placeholder="Nouvelle désignation">
        <input type="text" id="unit-input" class="border border-gray-300 p-2 w-full rounded focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4" placeholder="Unité">
        <select id="type-select" class="mb-4 p-2 border border-gray-300 rounded w-full">
            <option value="" disabled selected>Sélectionner le type</option>
            <option value="MATÉRIAUX FERREUX">MATÉRIAUX FERREUX</option>
            <option value="ACCESSOIRES">ACCESSOIRES</option>
            <option value="REVÊTEMENT DE PROTECTION OU DE FINITIONS">REVÊTEMENT DE PROTECTION OU DE FINITIONS</option>
            <!-- Ajoute d'autres options ici -->
        </select>
        <div class="flex justify-end space-x-4">
            <button id="closeModal" class="text-gray-500 px-4 py-2 hover:bg-gray-100 rounded">Annuler</button>
            <button id="saveDesignation" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Enregistrer</button>
        </div>
    </div>
</div>



    <!-- Footer -->
    <?php include_once '../components/footer.html'; ?>

<!-- Partie JavaScript -->
<script>
    document.querySelectorAll('.substitution-input').forEach(input => {
        const suggestionsBox = document.getElementById('suggestions-' + input.dataset.id);
        
        input.addEventListener('input', function() {
            const value = this.value;

            // Ajuste la largeur de la boîte de suggestions pour correspondre à l'input
            suggestionsBox.style.width = this.offsetWidth + 'px';

            // Gérer le barré de la désignation
            const designationElement = document.getElementById('designation-' + input.dataset.id);
            
            if (value.length > 0) {
                // Si l'input n'est pas vide, barrer la désignation
                designationElement.style.textDecoration = 'line-through';
            } else {
                // Si l'input est vide, enlever le barré
                designationElement.style.textDecoration = 'none';
            }

            if (value.length >= 1) {  // Afficher après la première lettre
                fetch('get_suggestions.php?query=' + value)
                    .then(response => response.json())
                    .then(data => {
                        suggestionsBox.innerHTML = '';

                        // Ajoute les suggestions
                        let suggestionExists = false; // Vérifie si une suggestion existe
                        data.forEach(suggestion => {
                            const div = document.createElement('div');
                            div.textContent = suggestion;
                            div.classList.add('suggestion-item', 'border-b', 'border-gray-300', 'last:border-b-0');
                            div.addEventListener('click', function() {
                                input.value = suggestion;
                                suggestionsBox.innerHTML = '';
                                designationElement.style.textDecoration = 'line-through'; // Barrer lors de la sélection d'une suggestion
                            });
                            suggestionsBox.appendChild(div);
                            suggestionExists = true; // Une suggestion a été ajoutée
                        });

                        // Affiche l'option "Créer" uniquement si aucune suggestion n'existe
                        if (!suggestionExists) {
                            const createOption = document.createElement('div');
                            createOption.textContent = 'Créer "' + value + '"';
                            createOption.classList.add('suggestion-item', 'create-option', 'border-b', 'border-gray-300', 'last:border-b-0', 'italic');
                            createOption.addEventListener('click', function() {
                                openCreateModal(value, input.dataset.id);
                            });
                            suggestionsBox.appendChild(createOption);
                        }

                        suggestionsBox.classList.remove('hidden');
                    });
            } else {
                suggestionsBox.classList.add('hidden');
            }
        });

        
        // Fermer la liste lorsque l'on clique en dehors
        document.addEventListener('click', function(event) {
            if (!input.contains(event.target) && !suggestionsBox.contains(event.target)) {
                suggestionsBox.classList.add('hidden');
            }
        });
    });

    function openCreateModal(designation, id) {
        document.getElementById('newDesignation').value = designation;
        document.getElementById('createDesignationModal').classList.remove('hidden');
        
        document.getElementById('saveDesignation').addEventListener('click', function() {
            const newDesignation = document.getElementById('newDesignation').value;
            const unit = document.getElementById('unit-input').value;
            const type = document.getElementById('type-select').value;

            fetch('create_designation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ designation: newDesignation, unit: unit, type: type })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`[name="substitution-${id}"]`).value = newDesignation;
                    document.getElementById('createDesignationModal').classList.add('hidden');
                    // Réinitialise les champs du modal après l'enregistrement
                    document.getElementById('newDesignation').value = '';
                    document.getElementById('unit-input').value = '';
                    document.getElementById('type-select').selectedIndex = 0; // Reset selection
                } else {
                    alert('Erreur lors de l\'enregistrement. Veuillez réessayer.'); // Message d'erreur
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur s\'est produite. Veuillez réessayer.'); // Gestion des erreurs
            });
        });
    }

    document.getElementById('closeModal').addEventListener('click', function() {
        document.getElementById('createDesignationModal').classList.add('hidden');
    });


</script>

</body>
</html>
