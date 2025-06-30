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
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sortie de produits - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/antd/4.16.13/antd.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
        }

        .ant-input {
            border-radius: 2px;
        }

        .ant-btn {
            border-radius: 2px;
        }

        .ant-card {
            box-shadow: 0 1px 2px -2px rgba(0, 0, 0, 0.16), 0 3px 6px 0 rgba(0, 0, 0, 0.12), 0 5px 12px 4px rgba(0, 0, 0, 0.09);
        }

        .ant-input-group-wrapper {
            width: 100%;
        }

        .ant-input-group {
            display: flex;
        }

        .ant-input-group .ant-input {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .ant-input-group .ant-btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .ant-input-group-addon {
            background-color: #f0f0f0;
            padding: 0 12px;
            display: flex;
            align-items: center;
        }

        .ant-input-group-addon .fas {
            font-size: 18px;
            color: #555;
        }

        /* Style pour le champ de recherche de projet */
        .project-search-container {
            position: relative;
        }

        .project-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d9d9d9;
            border-radius: 2px;
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        }

        .project-search-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }

        .project-search-item:hover {
            background-color: #f5f5f5;
        }

        /* Style pour le champ de recherche de fournisseur */
        .supplier-search-container {
            position: relative;
        }

        .supplier-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d9d9d9;
            border-radius: 2px;
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        }

        .supplier-search-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }

        .supplier-search-item:hover {
            background-color: #f5f5f5;
        }

        /* Style pour les options de type de sortie */
        .output-type-options {
            display: flex;
            margin-bottom: 1rem;
            border-radius: 2px;
            overflow: hidden;
            border: 1px solid #d9d9d9;
        }

        .output-type-option {
            flex: 1;
            text-align: center;
            padding: 0.75rem 1rem;
            cursor: pointer;
            background-color: #f9f9f9;
            transition: all 0.3s;
            font-weight: 500;
        }

        .output-type-option.active {
            background-color: #1890ff;
            color: white;
        }

        .output-type-option:not(.active):hover {
            background-color: #f0f0f0;
        }

        /* Animation pour les sections conditionnelles */
        .conditional-section {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .conditional-section.visible {
            max-height: 500px;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react/17.0.2/umd/react.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react-dom/17.0.2/umd/react-dom.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/antd/4.16.13/antd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include_once 'sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once 'header.php'; ?>

            <main class="p-6 flex-1">
                <div class="bg-white p-6 rounded-lg shadow-md ant-card">
                    <h1 class="text-2xl font-semibold mb-6">Sortie de produits</h1>

                    <!-- Options de type de sortie -->
                    <div class="output-type-options mb-6">
                        <div class="output-type-option active" data-type="standard">
                            <i class="fas fa-sign-out-alt mr-2"></i>Sortie standard
                        </div>
                        <div class="output-type-option" data-type="supplier-return">
                            <i class="fas fa-truck mr-2"></i>Retour fournisseur
                        </div>
                    </div>

                    <form id="barcodeForm" class="mb-6">
                        <div class="flex space-x-4">
                            <div class="ant-input-group-wrapper flex-grow">
                                <div class="ant-input-group">
                                    <span class="ant-input-group-addon">
                                        <i class="fas fa-barcode"></i>
                                    </span>
                                    <input type="text" id="barcodeInput" name="barcode"
                                        placeholder="Scannez ou saisissez le code-barres" class="ant-input flex-1">
                                </div>
                            </div>
                            <button type="submit" class="ant-btn ant-btn-primary w-1/4">
                                <i class="fas fa-plus mr-2"></i>Ajouter
                            </button>
                        </div>
                    </form>

                    <div id="productList" class="space-y-4">
                        <!-- Les produits scannés seront ajoutés ici -->
                    </div>

                    <button id="submitOutput" class="mt-6 ant-btn ant-btn-primary ant-btn-lg">
                        <i class="fas fa-check mr-2"></i>Valider les sorties
                    </button>
                </div>
            </main>
        </div>
    </div>

    <div id="notification-container"></div>

    <script>
        const { message, notification } = antd;
        let currentOutputType = 'standard'; // Valeur par défaut pour le type de sortie

        // Fonction pour initialiser le conteneur de notification
        function initNotification() {
            const container = document.getElementById('notification-container');
            ReactDOM.render(React.createElement(antd.ConfigProvider, null), container);
        }

        // Appeler la fonction d'initialisation
        initNotification();

        // Gestionnaire pour les options de type de sortie
        document.querySelectorAll('.output-type-option').forEach(option => {
            option.addEventListener('click', function () {
                // Supprimer la classe active de toutes les options
                document.querySelectorAll('.output-type-option').forEach(opt => {
                    opt.classList.remove('active');
                });

                // Ajouter la classe active à l'option cliquée
                this.classList.add('active');

                // Mettre à jour le type de sortie actuel
                currentOutputType = this.getAttribute('data-type');

                // Mettre à jour l'interface en fonction du type de sortie
                updateInterface();

                // Vider la liste des produits pour éviter les conflits
                document.getElementById('productList').innerHTML = '';
            });
        });

        function updateInterface() {
            // Cette fonction est appelée lorsque le type de sortie change
            // Elle met à jour l'interface en fonction du type de sortie sélectionné

            // Mettre à jour le texte du bouton de validation
            const submitBtn = document.getElementById('submitOutput');
            if (currentOutputType === 'supplier-return') {
                submitBtn.innerHTML = '<i class="fas fa-truck mr-2"></i>Valider les retours fournisseur';
            } else {
                submitBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Valider les sorties';
            }
        }

        function cleanBarcode(input) {
            // Carte pour convertir les caractères spéciaux en chiffres ou en symboles
            const charMap = {
                'à': '0', '&': '1', 'é': '2', '"': '3', "'": '4',
                '(': '5', 'è': '7', '_': '8', 'ç': '9',
                ')': '-', 'Q': 'A', '°': '-'
            };

            // Remplacement des caractères spéciaux dans l'entrée
            let cleaned = input.split('').map(char => charMap[char] || char).join('');
            console.log('Code-barres après conversion de caractères spéciaux:', cleaned);

            // Liste des préfixes valides
            const validPrefixes = ['ACC', 'BOVE', 'DIV', 'ELEC', 'EDPI', 'PLOM', 'MATO', 'MAFE', 'OMDP', 'OACS', 'REPP', 'REPS'];

            // Expression régulière pour valider et reformater le code-barres
            const regex = new RegExp(`^(${validPrefixes.join('|')})[-]?(\\d{1,5})$`, 'i');
            const match = cleaned.match(regex);

            if (match) {
                const prefix = match[1].toUpperCase(); // Préfixe en majuscules
                const numbers = match[2].padStart(5, '0'); // Formatage avec 5 chiffres
                cleaned = `${prefix}-${numbers}`; // Reconstruire le code-barres avec un tiret
                console.log('Code-barres nettoyé:', cleaned);
                return cleaned;
            }

            console.log('Code-barres invalide:', input);
            return ''; // Retourner une chaîne vide si le format est invalide
        }

        document.getElementById('barcodeForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const barcodeInput = document.getElementById('barcodeInput');
            const rawBarcode = barcodeInput.value;

            console.log('Code-barres brut:', rawBarcode);

            const cleanedBarcode = cleanBarcode(rawBarcode);

            if (cleanedBarcode) {
                fetch(`api_entry.php?barcode=${encodeURIComponent(cleanedBarcode)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            addProductToList(data.product);
                            message.success('Produit ajouté avec succès');
                        } else {
                            message.error(data.message);
                        }
                        barcodeInput.value = '';
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        message.error('Une erreur est survenue lors de la récupération des données du produit.');
                    });
            } else {
                message.error('Code-barres invalide');
            }
        });

        // Fonction pour chercher des projets actifs avec l'ID correspondant
        function searchProjects(query, inputElement) {
            if (!query || query.length < 3) {
                // Supprimer les résultats précédents si la requête est trop courte
                const container = inputElement.parentNode;
                const oldResults = container.querySelector('.project-search-results');
                if (oldResults) {
                    container.removeChild(oldResults);
                }
                return;
            }

            fetch(`api_searchProjects.php?query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Afficher les résultats dans un menu déroulant
                        const resultsContainer = document.createElement('div');
                        resultsContainer.className = 'project-search-results';

                        if (data.projects && data.projects.length > 0) {
                            data.projects.forEach(project => {
                                const item = document.createElement('div');
                                item.className = 'project-search-item';
                                item.textContent = `${project.code_projet} - ${project.nom_client}`;
                                item.setAttribute('data-project-code', project.code_projet);
                                item.setAttribute('data-project-name', project.nom_client);
                                item.addEventListener('click', () => {
                                    // Quand l'utilisateur clique sur un résultat, mettre à jour l'input
                                    inputElement.value = `${project.code_projet} - ${project.nom_client}`;
                                    inputElement.setAttribute('data-project-code', project.code_projet);
                                    inputElement.setAttribute('data-project-name', project.nom_client);

                                    // Supprimer le conteneur de résultats
                                    if (resultsContainer.parentNode) {
                                        resultsContainer.parentNode.removeChild(resultsContainer);
                                    }
                                });
                                resultsContainer.appendChild(item);
                            });
                        } else {
                            const noResults = document.createElement('div');
                            noResults.className = 'project-search-item';
                            noResults.textContent = 'Aucun projet trouvé';
                            resultsContainer.appendChild(noResults);
                        }

                        // Ajouter le conteneur de résultats après l'input
                        const container = inputElement.parentNode;
                        // Supprimer les anciens résultats s'ils existent
                        const oldResults = container.querySelector('.project-search-results');
                        if (oldResults) {
                            container.removeChild(oldResults);
                        }
                        container.appendChild(resultsContainer);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                });
        }

        // Fonction pour chercher des fournisseurs
        function searchSuppliers(query, inputElement) {
            if (!query || query.length < 2) {
                // Supprimer les résultats précédents si la requête est trop courte
                const container = inputElement.parentNode;
                const oldResults = container.querySelector('.supplier-search-results');
                if (oldResults) {
                    container.removeChild(oldResults);
                }
                return;
            }

            fetch(`retour-fournisseur/api_searchSuppliers.php?query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Afficher les résultats dans un menu déroulant
                        const resultsContainer = document.createElement('div');
                        resultsContainer.className = 'supplier-search-results';

                        if (data.suppliers && data.suppliers.length > 0) {
                            data.suppliers.forEach(supplier => {
                                const item = document.createElement('div');
                                item.className = 'supplier-search-item';
                                item.textContent = supplier.nom;
                                item.setAttribute('data-supplier-id', supplier.id);
                                item.addEventListener('click', () => {
                                    // Quand l'utilisateur clique sur un résultat, mettre à jour l'input
                                    inputElement.value = supplier.nom;
                                    inputElement.setAttribute('data-supplier-id', supplier.id);

                                    // Supprimer le conteneur de résultats
                                    if (resultsContainer.parentNode) {
                                        resultsContainer.parentNode.removeChild(resultsContainer);
                                    }
                                });
                                resultsContainer.appendChild(item);
                            });
                        } else {
                            const noResults = document.createElement('div');
                            noResults.className = 'supplier-search-item';
                            noResults.textContent = 'Aucun fournisseur trouvé';
                            resultsContainer.appendChild(noResults);
                        }

                        // Ajouter le conteneur de résultats après l'input
                        const container = inputElement.parentNode;
                        // Supprimer les anciens résultats s'ils existent
                        const oldResults = container.querySelector('.supplier-search-results');
                        if (oldResults) {
                            container.removeChild(oldResults);
                        }
                        container.appendChild(resultsContainer);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                });
        }

        function addProductToList(product) {
            const productList = document.getElementById('productList');
            const productDiv = document.createElement('div');
            productDiv.className = 'flex items-center space-x-4 p-4 bg-gray-50 rounded-md flex-wrap';

            // Contenu HTML de base commun aux deux types de sortie
            let baseHTML = `
            <input type="hidden" name="product_id" value="${product.id}">
            <input type="hidden" name="output_type" value="${currentOutputType}">
            <div class="flex-grow text-gray-700">${product.product_name}</div>
            <div class="flex items-center space-x-4">
                <label class="text-sm text-gray-600">Quantité:</label>
                <input type="number" name="quantity" value="1" min="0.001" max="${product.quantity}" step="0.001" class="w-20 p-2 border rounded-md ant-input" onfocus="this.select()" onchange="checkQuantity(this, ${product.quantity})">
            </div>`;

            // Contenu HTML spécifique au type de sortie
            if (currentOutputType === 'supplier-return') {
                // Contenu pour les retours fournisseur
                baseHTML += `
                <div class="flex items-center space-x-4 supplier-search-container">
                    <label class="text-sm text-gray-600">Fournisseur:</label>
                    <input type="text" name="supplier" placeholder="Fournisseur" class="w-60 p-2 border rounded-md ant-input" required oninput="searchSuppliers(this.value, this)" autocomplete="off">
                </div>
                <div class="flex items-center space-x-4">
                    <label class="text-sm text-gray-600">Motif du retour:</label>
                    <select name="return_reason" class="w-40 p-2 border rounded-md ant-input">
                        <option value="defectueux">Défectueux</option>
                        <option value="erreur_commande">Erreur de commande</option>
                        <option value="surplus">Surplus</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                <div class="flex items-center space-x-4 w-full mt-2">
                    <label class="text-sm text-gray-600">Commentaire:</label>
                    <input type="text" name="return_comment" placeholder="Commentaire (facultatif)" class="flex-1 p-2 border rounded-md ant-input">
                </div>
                <div class="flex items-center space-x-4 w-full mt-2 project-search-container">
                    <label class="text-sm text-gray-600">Projet lié:</label>
                    <input type="text" name="id_project" placeholder="Projet (facultatif)" class="w-60 p-2 border rounded-md ant-input" oninput="searchProjects(this.value, this)" autocomplete="off">
                </div>`;
            } else {
                // Contenu pour les sorties standard
                baseHTML += `
                <div class="flex items-center space-x-4">
                    <label class="text-sm text-gray-600">Destination:</label>
                    <input type="text" name="destination" placeholder="Destination" class="w-40 p-2 border rounded-md ant-input">
                </div>
                <div class="flex items-center space-x-4">
                    <label class="text-sm text-gray-600">Demandeur:</label>
                    <input type="text" name="demandeur" placeholder="Demandeur" class="w-40 p-2 border rounded-md ant-input">
                </div>
                <div class="flex items-center space-x-4 project-search-container">
                    <label class="text-sm text-gray-600">Projet lié:</label>
                    <input type="text" name="id_project" placeholder="Projet (facultatif)" class="w-60 p-2 border rounded-md ant-input" oninput="searchProjects(this.value, this)" autocomplete="off">
                </div>`;
            }

            // Bouton de suppression commun aux deux types
            baseHTML += `
            <button type="button" class="text-red-500 hover:text-red-700 ant-btn ant-btn-dangerous" onclick="removeProduct(this)">
                <i class="fas fa-trash-alt"></i>
            </button>`;

            productDiv.innerHTML = baseHTML;
            productList.appendChild(productDiv);

            // Ajouter l'événement de recherche de projet et/ou de fournisseur selon le type
            if (currentOutputType === 'supplier-return') {
                const supplierInput = productDiv.querySelector('input[name="supplier"]');
                supplierInput.addEventListener('input', function () {
                    searchSuppliers(this.value, this);
                });

                // Ajouter également l'événement de recherche de projet pour les retours fournisseur
                const projectInput = productDiv.querySelector('input[name="id_project"]');
                if (projectInput) {
                    projectInput.addEventListener('input', function () {
                        searchProjects(this.value, this);
                    });
                }
            } else {
                const projectInput = productDiv.querySelector('input[name="id_project"]');
                projectInput.addEventListener('input', function () {
                    searchProjects(this.value, this);
                });
            }
        }

        // Fonction améliorée qui tolère les états intermédiaires pendant la saisie
        function checkQuantity(input, maxQuantity) {
            // Ne pas valider pendant la saisie de nombres décimaux ou états intermédiaires
            if (input.value === '0' || input.value === '0.' || input.value === '-' || input.value === ''
                || input.value.endsWith('.')) {
                return; // L'utilisateur est en train de saisir, ne pas interrompre
            }

            const quantity = parseFloat(input.value);

            // Vérifier si c'est un nombre valide
            if (isNaN(quantity)) {
                input.value = 1;
                return;
            }

            // Empêcher les valeurs négatives
            if (quantity <= 0) {
                input.value = 0.001;
                message.warning('La quantité ne peut pas être négative ou nulle.');
                return;
            }

            // Réintroduire la vérification de quantité maximale
            if (quantity > maxQuantity) {
                input.value = maxQuantity;
                message.warning(`La quantité maximale disponible est ${maxQuantity}.`);
            }
        }

        function removeProduct(button) {
            const productDiv = button.closest('div');
            productDiv.remove();
        }

        document.getElementById('submitOutput').addEventListener('click', function () {
            const output = [];
            document.querySelectorAll('#productList > div').forEach(div => {
                const productId = div.querySelector('input[name="product_id"]').value;
                const quantityInput = div.querySelector('input[name="quantity"]');
                const quantity = parseFloat(quantityInput.value);
                const outputType = div.querySelector('input[name="output_type"]').value;

                // Données communes
                const outputItem = {
                    product_id: productId,
                    quantity: quantity,
                    output_type: outputType
                };

                // Données spécifiques selon le type de sortie
                if (outputType === 'supplier-return') {
                    const supplierInput = div.querySelector('input[name="supplier"]');
                    const returnReason = div.querySelector('select[name="return_reason"]').value;
                    const returnComment = div.querySelector('input[name="return_comment"]').value;
                    const projectInput = div.querySelector('input[name="id_project"]');
                    const idProject = projectInput ? projectInput.value : '';
                    const projectCode = projectInput ? projectInput.getAttribute('data-project-code') || '' : '';
                    const projectName = projectInput ? projectInput.getAttribute('data-project-name') || '' : '';

                    outputItem.supplier = supplierInput.value;
                    outputItem.supplier_id = supplierInput.getAttribute('data-supplier-id') || '';
                    outputItem.return_reason = returnReason;
                    outputItem.return_comment = returnComment;
                    outputItem.id_project = idProject;
                    outputItem.project_code = projectCode;
                    outputItem.project_name = projectName;

                    // Pour compatibilité avec l'API existante
                    outputItem.destination = 'Retour fournisseur: ' + supplierInput.value;
                    outputItem.demandeur = 'Système';
                } else {
                    const destination = div.querySelector('input[name="destination"]').value;
                    const demandeur = div.querySelector('input[name="demandeur"]').value;
                    const projectInput = div.querySelector('input[name="id_project"]');
                    const idProject = projectInput ? projectInput.value : '';
                    const projectCode = projectInput ? projectInput.getAttribute('data-project-code') || '' : '';
                    const projectName = projectInput ? projectInput.getAttribute('data-project-name') || '' : '';

                    outputItem.destination = destination;
                    outputItem.demandeur = demandeur;
                    outputItem.id_project = idProject;
                    outputItem.project_code = projectCode;
                    outputItem.project_name = projectName;
                }

                output.push(outputItem);
            });

            if (output.length === 0) {
                notification.warning({
                    message: 'Aucun produit ajouté',
                    description: 'Veuillez ajouter au moins un produit avant de soumettre.',
                });
                return;
            }

            // Vérification des champs obligatoires selon le type
            let hasErrors = false;

            output.forEach(item => {
                if (item.output_type === 'supplier-return') {
                    if (!item.supplier.trim()) {
                        hasErrors = true;
                        notification.warning({
                            message: 'Fournisseur manquant',
                            description: 'Veuillez indiquer un fournisseur pour chaque retour.',
                        });
                    }
                } else {
                    if (!item.destination.trim()) {
                        hasErrors = true;
                        notification.warning({
                            message: 'Destinations manquantes',
                            description: 'Veuillez remplir la destination pour tous les produits.',
                        });
                    }

                    if (!item.demandeur.trim()) {
                        hasErrors = true;
                        notification.warning({
                            message: 'Demandeurs manquants',
                            description: 'Veuillez remplir le demandeur pour tous les produits.',
                        });
                    }
                }
            });

            if (hasErrors) return;

            fetch('api_addOutput.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ output: output }),
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        notification.success({
                            message: currentOutputType === 'supplier-return' ? 'Retours effectués' : 'Sorties effectuées',
                            description: data.message,
                        });
                        // Vider la liste des produits après l'ajout réussi
                        document.getElementById('productList').innerHTML = '';
                    } else {
                        notification.error({
                            message: 'Erreur',
                            description: data.message,
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    notification.error({
                        message: 'Erreur',
                        description: 'Une erreur est survenue lors du traitement.',
                    });
                });
        });
    </script>
</body>

</html>