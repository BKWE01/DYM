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
        SELECT id, idExpression, designation, unit, quantity, qt_stock, type, created_at
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

    .input-container {
    position: relative;
    display: inline-block;
    width: 100%; /* Ajuste la largeur de l'input à 100% */
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

/* Pour augmenter la largeur de l'input */
input[type="number"] {
    width: 70%; /* L'input prend toute la largeur de son conteneur */
    padding: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    text-align: center;
    background-color: #f5f5f5; /* Couleur de fond gris clair */
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.2); /* Ombre grise */
    border: none; /* Retirer la bordure */
    outline: none; /* Retirer le contour lors du focus */
}

.substituer-column {
    width: 35%; /* Ajuste le pourcentage en fonction de la largeur souhaitée */
}

input[type="text"] {
    width: 100%; /* L'input prendra toute la largeur de sa cellule */
    padding: 7px;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    text-align: left;
    background-color: #f5f5f5; 
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.2); 
    border: none;
    outline: none;
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
                        $idExpress = ($expressions[0]['idExpression']);
                        ?>
                        <h2 class="header-title">Expressions de besoin un projet n° <?php echo htmlspecialchars($idExpress); ?> - Créées le <?php echo htmlspecialchars($creationDate); ?></h2>
                <div class="date-time">
                    <span class="material-icons">calendar_today</span>
                    <span id="date-time-display"></span>
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
                        <table class="min-w-full bg-white border border-gray-300">
                            <thead>
                                <tr>
                                    <th class="py-2 px-3 border-b text-left">Désignation</th>
                                    <th class="py-2 px-6 border-b substituer-column">Substituer</th>
                                    <th class="py-2 px-3 border-b">Unité</th>
                                    <th class="py-2 px-1 border-b">Quantité demandée</th>
                                    <th class="py-2 px-1 border-b">Quantité en stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $currentType = '';
                                foreach ($expressions as $expression):
                                    if ($expression['type'] !== $currentType):
                                        $currentType = $expression['type'];
                                ?>
                                    <tr>
                                        <td colspan="5" class="py-2 px-3 bg-gray-200 font-semibold border-b-2 border-gray-500">
                                            <?php echo htmlspecialchars($currentType); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="px-3 py-2 border-b designation-article" id="designation-<?php echo htmlspecialchars($expression['id']); ?>">
                                        <?php echo htmlspecialchars($expression['designation']); ?>
                                    </td>
                                    <td class="px-6 py-2 text-center border">
                                        <input type="text" name="substitution-<?php echo htmlspecialchars($expression['id']); ?>" 
                                            class="substitution-input w-96 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                            data-id="<?php echo htmlspecialchars($expression['id']); ?>" autocomplete="off"/>
                                        <div class="suggestions hidden absolute z-10 max-h-60 overflow-y-auto w-full" 
                                            id="suggestions-<?php echo htmlspecialchars($expression['id']); ?>"></div>
                                    </td>
                                    <td class="py-2 px-3 border text-center"><?php echo htmlspecialchars($expression['unit']); ?></td>
                                    <td class="py-2 px-1 border text-center">
                                        <input type="number" id="quantite-<?php echo htmlspecialchars($expression['id']); ?>" name="quantite[<?php echo htmlspecialchars($expression['id']); ?>]" value="<?php echo htmlspecialchars($expression['quantity']); ?>" class="w-full p-1 rounded text-center disabled-input" readonly>
                                    </td>
                                    <td class="py-2 px-1 border text-center">
                                        <div class="input-container">
                                            <input type="number" id="qt-stock-<?php echo htmlspecialchars($expression['id']); ?>" name="qt-stock[<?php echo htmlspecialchars($expression['id']); ?>]" value="<?php echo htmlspecialchars($expression['qt_stock']); ?>" class="w-full border border-gray-100 p-1 rounded text-center" oninput="validateStock(<?php echo htmlspecialchars($expression['id']); ?>)">
                                            <span id="error-<?php echo htmlspecialchars($expression['id']); ?>" class="error-bubble" style="display:none;">La quantité en stock ne peut pas dépasser la quantité demandée.</span>
                                        </div>
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

    <script>
    document.getElementById('validate-prices').addEventListener('click', function() {
        const data = [];
        const substitutions = {};
        document.querySelectorAll('tbody tr').forEach(row => {
            const quantityInput = row.querySelector('input[name^="quantite"]');
            const stockInput = row.querySelector('input[name^="qt-stock"]');
            const substitutionInput = row.querySelector('input[name^="substitution-"]');

            if (quantityInput && stockInput) {
                const id = quantityInput.name.match(/\d+/)[0];
                const quantity = parseFloat(quantityInput.value) || 0;
                const qtStock = parseFloat(stockInput.value) || 0;

                // Récupérer la valeur de substitution
                if (substitutionInput) {
                    const substitutionValue = substitutionInput.value.trim();
                    if (substitutionValue !== '') {
                        substitutions[id] = substitutionValue; // Enregistrer la substitution si elle n'est pas vide
                    }
                }

                data.push({
                    id: id,
                    qt_stock: qtStock,
                    quantity: quantity,
                    substitution: substitutions[id] || null // Inclure la substitution (ou null si vide)
                });
            }
        });

        fetch('update_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ expressions: data })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(result => {
            console.log(result); // Ajoutez cette ligne
            if (result.success) {
                alert('Quantités mises à jour avec succès.');
                const id = new URLSearchParams(window.location.search).get('id');
                if (id) {
                    window.open(`generate_pdf.php?id=${id}`, '_blank');
                    window.location.href = 'dashboard.php';
                } else {
                    alert('ID d\'expression manquant pour la redirection.');
                }
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

    function validateStock(id) {
        const quantityInput = document.getElementById(`quantite-${id}`);
        const qtStockInput = document.getElementById(`qt-stock-${id}`);
        const errorBubble = document.getElementById(`error-${id}`);

        if (quantityInput && qtStockInput && errorBubble) {
            const quantity = parseFloat(quantityInput.value);
            const qtStock = parseFloat(qtStockInput.value);

            if (qtStock > quantity) {
                errorBubble.style.display = 'block'; // Afficher la bulle d'erreur
            } else {
                errorBubble.style.display = 'none'; // Masquer la bulle d'erreur
            }
        }
    }
</script>


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
