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
    <title>Ajouter un Retour - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/antd/4.16.13/antd.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Ajouter SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

    <style>
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .ant-input {
            border-radius: 4px;
        }

        .ant-btn {
            border-radius: 4px;
        }

        .ant-card {
            box-shadow: 0 1px 2px -2px rgba(0, 0, 0, 0.16), 0 3px 6px 0 rgba(0, 0, 0, 0.12), 0 5px 12px 4px rgba(0, 0, 0, 0.09);
            border-radius: 8px;
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

        /* Animation shake pour les erreurs */
        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        .shake {
            animation: shake 0.6s;
        }

        .error-field {
            border-color: #ef4444 !important;
        }

        /* Bouton flottant pour revenir en arrière */
        .back-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            height: 48px;
            width: 48px;
            border-radius: 50%;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
            z-index: 10;
        }

        .back-button:hover {
            transform: scale(1.1);
            background-color: #e5e7eb;
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
        <?php include_once '../sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once '../header.php'; ?>

            <main class="p-6 flex-1 overflow-y-auto">
                <div class="max-w-4xl mx-auto">
                    <!-- Titre de la page -->
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">Ajouter un Retour en Stock</h1>
                        <p class="text-gray-600 mt-1">Formulaire de saisie pour les retours de matériel en stock</p>
                    </div>

                    <!-- Formulaire de retour -->
                    <div class="bg-white p-6 rounded-lg shadow-md ant-card">
                        <form id="returnForm" class="space-y-6">
                            <!-- Section 1: Sélection du produit -->
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                    <span class="material-icons-round mr-2 text-blue-600">inventory_2</span>
                                    Sélection du produit
                                </h2>

                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Méthode de
                                        sélection</label>
                                    <div class="flex space-x-4">
                                        <button type="button" id="scanMethodBtn"
                                            class="flex-1 py-2 px-4 bg-blue-500 text-white rounded-md flex items-center justify-center">
                                            <span class="material-icons-round mr-2">qr_code_scanner</span>
                                            Scanner le code-barres
                                        </button>
                                        <button type="button" id="searchMethodBtn"
                                            class="flex-1 py-2 px-4 bg-gray-200 text-gray-700 rounded-md flex items-center justify-center">
                                            <span class="material-icons-round mr-2">search</span>
                                            Rechercher un produit
                                        </button>
                                    </div>
                                </div>

                                <!-- Méthode 1: Scanner un code-barres -->
                                <div id="scanMethod">
                                    <div class="flex space-x-4 mb-4">
                                        <div class="ant-input-group-wrapper flex-grow">
                                            <div class="ant-input-group">
                                                <span class="ant-input-group-addon">
                                                    <span
                                                        class="material-icons-round text-gray-400">qr_code_scanner</span>
                                                </span>
                                                <input type="text" id="barcodeInput" name="barcode"
                                                    placeholder="Scannez ou saisissez le code-barres"
                                                    class="ant-input flex-1">
                                                <input type="hidden" id="productId" name="product_id">
                                            </div>
                                        </div>
                                        <button type="button" id="addBarcodeBtn" class="flex align-items-center ant-btn ant-btn-primary">
                                            <span class="material-icons-round mr-1">add</span>
                                            Ajouter
                                        </button>
                                    </div>
                                </div>

                                <!-- Méthode 2: Rechercher un produit -->
                                <div id="searchMethod" class="hidden">
                                    <div class="mb-4">
                                        <label for="productSearch"
                                            class="block text-sm font-medium text-gray-700 mb-1">Rechercher un
                                            produit</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                                <span class="material-icons-round text-gray-400">search</span>
                                            </span>
                                            <input type="text" id="productSearch"
                                                class="pl-10 pr-4 py-2 w-full border rounded-md"
                                                placeholder="Nom du produit, code barres...">
                                        </div>
                                    </div>
                                    <div id="searchResults"
                                        class="max-h-60 overflow-y-auto bg-white rounded-md shadow hidden">
                                        <!-- Les résultats de recherche seront ajoutés ici -->
                                    </div>
                                </div>

                                <!-- Information sur le produit sélectionné -->
                                <div id="selectedProductInfo"
                                    class="bg-blue-50 p-4 rounded-md border border-blue-100 hidden">
                                    <div class="flex items-start">
                                        <span class="material-icons-round text-blue-500 mr-3">inventory_2</span>
                                        <div class="flex-1">
                                            <h3 id="selectedProductName" class="font-medium text-blue-900"></h3>
                                            <div class="grid grid-cols-2 gap-4 mt-2 text-sm">
                                                <div>
                                                    <p class="text-gray-500">Code-barres:</p>
                                                    <p id="selectedProductBarcode" class="font-medium"></p>
                                                </div>
                                                <div>
                                                    <p class="text-gray-500">Quantité disponible:</p>
                                                    <p id="selectedProductQuantity" class="font-medium"></p>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" id="changeProductBtn"
                                            class="text-blue-600 hover:text-blue-800">
                                            <span class="material-icons-round">edit</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Informations sur le retour -->
                            <div id="returnInfoSection" class="hidden">
                                <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                    <span class="material-icons-round mr-2 text-blue-600">info</span>
                                    Informations sur le retour
                                </h2>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Quantité -->
                                    <div>
                                        <label for="quantity"
                                            class="block text-sm font-medium text-gray-700 mb-1">Quantité
                                            retournée*</label>
                                        <div class="flex rounded-md shadow-sm">
                                            <input type="number" id="quantity" name="quantity"
                                                class="flex-1 min-w-0 block w-full px-3 py-2 rounded-l-md border focus:ring-blue-500 focus:border-blue-500"
                                                step="0.01" min="0.01" required>
                                            <span id="unitDisplay"
                                                class="inline-flex items-center px-3 py-2 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                                unité(s)
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Provenance -->
                                    <div>
                                        <label for="origin"
                                            class="block text-sm font-medium text-gray-700 mb-1">Provenance/Origine*</label>
                                        <input type="text" id="origin" name="origin"
                                            class="block w-full px-3 py-2 border rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="D'où provient ce retour?" required>
                                    </div>

                                    <!-- Motif de retour -->
                                    <div>
                                        <label for="returnReason"
                                            class="block text-sm font-medium text-gray-700 mb-1">Motif du
                                            retour*</label>
                                        <select id="returnReason" name="return_reason"
                                            class="block w-full px-3 py-2 border rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            required>
                                            <option value="">Sélectionner un motif</option>
                                            <option value="unused">Produit non utilisé</option>
                                            <option value="excess">Excédent de matériel</option>
                                            <option value="wrong_product">Produit erroné</option>
                                            <option value="defective">Produit défectueux</option>
                                            <option value="project_completed">Projet terminé</option>
                                            <option value="other">Autre</option>
                                        </select>
                                    </div>

                                    <!-- Champ "Autre motif" (conditionnel) -->
                                    <div id="otherReasonContainer" class="hidden">
                                        <label for="otherReason"
                                            class="block text-sm font-medium text-gray-700 mb-1">Précisez le
                                            motif*</label>
                                        <input type="text" id="otherReason" name="other_reason"
                                            class="block w-full px-3 py-2 border rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Veuillez préciser le motif de retour">
                                    </div>

                                    <!-- État du produit -->
                                    <div>
                                        <label for="productCondition"
                                            class="block text-sm font-medium text-gray-700 mb-1">État du
                                            produit*</label>
                                        <select id="productCondition" name="product_condition"
                                            class="block w-full px-3 py-2 border rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            required>
                                            <option value="">Sélectionner l'état</option>
                                            <option value="new">Neuf (non utilisé)</option>
                                            <option value="good">Bon état (utilisable)</option>
                                            <option value="damaged">Endommagé (réparable)</option>
                                            <option value="defective">Défectueux (non utilisable)</option>
                                        </select>
                                    </div>

                                    <!-- Personne effectuant le retour -->
                                    <div>
                                        <label for="returnedBy"
                                            class="block text-sm font-medium text-gray-700 mb-1">Retourné par*</label>
                                        <input type="text" id="returnedBy" name="returned_by"
                                            class="block w-full px-3 py-2 border rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Nom de la personne" required>
                                    </div>

                                    <!-- Commentaires -->
                                    <div class="md:col-span-2">
                                        <label for="comments"
                                            class="block text-sm font-medium text-gray-700 mb-1">Commentaires</label>
                                        <textarea id="comments" name="comments" rows="3"
                                            class="block w-full px-3 py-2 border rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Informations complémentaires"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Bouton d'envoi -->
                            <div id="submitBtnContainer" class="pt-4 hidden">
                                <button type="submit"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-md shadow-md transition-all flex items-center justify-center">
                                    <span class="material-icons-round mr-2">save</span>
                                    Enregistrer le retour
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>

            <!-- Bouton retour -->
            <a href="index.php" class="back-button">
                <span class="material-icons-round text-gray-600">arrow_back</span>
            </a>
        </div>
    </div>

    <div id="notification-container"></div>

    <script>
        const { message, notification } = antd;

        // Fonction pour initialiser le conteneur de notification
        function initNotification() {
            const container = document.getElementById('notification-container');
            ReactDOM.render(React.createElement(antd.ConfigProvider, null), container);
        }

        // Appeler la fonction d'initialisation
        initNotification();

        document.addEventListener('DOMContentLoaded', function () {
            // Gestion des méthodes de sélection du produit
            const scanMethodBtn = document.getElementById('scanMethodBtn');
            const searchMethodBtn = document.getElementById('searchMethodBtn');
            const scanMethod = document.getElementById('scanMethod');
            const searchMethod = document.getElementById('searchMethod');

            scanMethodBtn.addEventListener('click', function () {
                scanMethodBtn.classList.remove('bg-gray-200', 'text-gray-700');
                scanMethodBtn.classList.add('bg-blue-500', 'text-white');
                searchMethodBtn.classList.remove('bg-blue-500', 'text-white');
                searchMethodBtn.classList.add('bg-gray-200', 'text-gray-700');
                scanMethod.classList.remove('hidden');
                searchMethod.classList.add('hidden');
                document.getElementById('searchResults').classList.add('hidden');
                document.getElementById('barcodeInput').focus();
            });

            searchMethodBtn.addEventListener('click', function () {
                searchMethodBtn.classList.remove('bg-gray-200', 'text-gray-700');
                searchMethodBtn.classList.add('bg-blue-500', 'text-white');
                scanMethodBtn.classList.remove('bg-blue-500', 'text-white');
                scanMethodBtn.classList.add('bg-gray-200', 'text-gray-700');
                searchMethod.classList.remove('hidden');
                scanMethod.classList.add('hidden');
                document.getElementById('productSearch').focus();
            });

            // Focus initial sur le champ code-barres
            document.getElementById('barcodeInput').focus();

            // Gestion de l'ajout par code-barres
            document.getElementById('addBarcodeBtn').addEventListener('click', function () {
                const barcode = document.getElementById('barcodeInput').value.trim();
                if (barcode) {
                    fetchProductByBarcode(barcode);
                } else {
                    notification.warning({
                        message: 'Champ vide',
                        description: 'Veuillez scanner ou saisir un code-barres.',
                        placement: 'bottomRight'
                    });
                }
            });

            // Aussi ajouter avec la touche Entrée
            document.getElementById('barcodeInput').addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('addBarcodeBtn').click();
                }
            });

            // Gestion de la recherche de produit
            const productSearch = document.getElementById('productSearch');
            const searchResults = document.getElementById('searchResults');

            productSearch.addEventListener('input', function () {
                const query = this.value.trim();
                if (query.length >= 2) {
                    searchProducts(query);
                } else {
                    searchResults.classList.add('hidden');
                }
            });

            // Gestion du motif de retour "Autre"
            document.getElementById('returnReason').addEventListener('change', function () {
                const otherReasonContainer = document.getElementById('otherReasonContainer');
                if (this.value === 'other') {
                    otherReasonContainer.classList.remove('hidden');
                    document.getElementById('otherReason').setAttribute('required', 'required');
                } else {
                    otherReasonContainer.classList.add('hidden');
                    document.getElementById('otherReason').removeAttribute('required');
                }
            });

            // Changer de produit
            document.getElementById('changeProductBtn').addEventListener('click', function () {
                document.getElementById('selectedProductInfo').classList.add('hidden');
                document.getElementById('returnInfoSection').classList.add('hidden');
                document.getElementById('submitBtnContainer').classList.add('hidden');
                document.getElementById('scanMethod').classList.remove('hidden');
                document.getElementById('barcodeInput').value = '';
                document.getElementById('barcodeInput').focus();
            });

            // Soumission du formulaire
            document.getElementById('returnForm').addEventListener('submit', function (e) {
                e.preventDefault();

                // Vérifier que toutes les informations requises sont présentes
                const productId = document.getElementById('productId').value;
                const quantity = document.getElementById('quantity').value;
                const origin = document.getElementById('origin').value;
                const returnReason = document.getElementById('returnReason').value;
                const productCondition = document.getElementById('productCondition').value;
                const returnedBy = document.getElementById('returnedBy').value;

                // Vérifier le champ "Autre motif" si nécessaire
                if (returnReason === 'other') {
                    const otherReason = document.getElementById('otherReason').value;
                    if (!otherReason) {
                        document.getElementById('otherReason').classList.add('error-field', 'shake');
                        setTimeout(() => {
                            document.getElementById('otherReason').classList.remove('error-field', 'shake');
                        }, 600);
                        return;
                    }
                }

                // Collecter toutes les données du formulaire
                const formData = {
                    product_id: productId,
                    quantity: quantity,
                    origin: origin,
                    return_reason: returnReason,
                    other_reason: returnReason === 'other' ? document.getElementById('otherReason').value : '',
                    product_condition: productCondition,
                    returned_by: returnedBy,
                    comments: document.getElementById('comments').value
                };

                // Envoyer les données via AJAX
                submitReturnForm(formData);
            });

            // Fonction pour rechercher un produit par code-barres
            function fetchProductByBarcode(barcode) {
                // Nettoyage du code-barres selon le format utilisé dans l'application
                function cleanBarcode(input) {
                    // Carte pour convertir les caractères spéciaux en chiffres ou en symboles
                    const charMap = {
                        'à': '0', '&': '1', 'é': '2', '"': '3', "'": '4',
                        '(': '5', 'è': '7', '_': '8', 'ç': '9',
                        ')': '-', 'Q': 'A', '°': '-'
                    };

                    // Remplacement des caractères spéciaux dans l'entrée
                    let cleaned = input.split('').map(char => charMap[char] || char).join('');

                    // Liste des préfixes valides
                    const validPrefixes = ['ACC', 'BOVE', 'DIV', 'ELEC', 'EDPI', 'PLOM', 'MATO', 'MAFE', 'OMDP', 'OACS', 'REPP', 'REPS'];

                    // Expression régulière pour valider et reformater le code-barres
                    const regex = new RegExp(`^(${validPrefixes.join('|')})[-]?(\\d{1,5})$`, 'i');
                    const match = cleaned.match(regex);

                    if (match) {
                        const prefix = match[1].toUpperCase(); // Préfixe en majuscules
                        const numbers = match[2].padStart(5, '0'); // Formatage avec 5 chiffres
                        cleaned = `${prefix}-${numbers}`; // Reconstruire le code-barres avec un tiret
                        return cleaned;
                    }

                    return ''; // Retourner une chaîne vide si le format est invalide
                }

                const cleanedBarcode = cleanBarcode(barcode);
                if (!cleanedBarcode) {
                    notification.error({
                        message: 'Format invalide',
                        description: 'Le code-barres saisi n\'est pas dans un format valide.',
                        placement: 'bottomRight'
                    });
                    return;
                }

                // Afficher un indicateur de chargement
                document.getElementById('barcodeInput').setAttribute('disabled', 'disabled');
                document.getElementById('addBarcodeBtn').setAttribute('disabled', 'disabled');
                document.getElementById('addBarcodeBtn').innerHTML = '<span class="material-icons-round animate-spin">refresh</span>';

                // Appeler l'API pour récupérer les informations du produit
                fetch(`../api_entry.php?barcode=${encodeURIComponent(cleanedBarcode)}`)
                    .then(response => response.json())
                    .then(data => {
                        // Réactiver les champs
                        document.getElementById('barcodeInput').removeAttribute('disabled');
                        document.getElementById('addBarcodeBtn').removeAttribute('disabled');
                        document.getElementById('addBarcodeBtn').innerHTML = '<span class="material-icons-round mr-1">add</span> Ajouter';

                        if (data.success) {
                            displaySelectedProduct(data.product);
                        } else {
                            notification.error({
                                message: 'Erreur',
                                description: data.message || 'Produit non trouvé',
                                placement: 'bottomRight'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        document.getElementById('barcodeInput').removeAttribute('disabled');
                        document.getElementById('addBarcodeBtn').removeAttribute('disabled');
                        document.getElementById('addBarcodeBtn').innerHTML = '<span class="material-icons-round mr-1">add</span> Ajouter';

                        notification.error({
                            message: 'Erreur de connexion',
                            description: 'Impossible de contacter le serveur',
                            placement: 'bottomRight'
                        });
                    });
            }

            // Fonction pour rechercher des produits
            function searchProducts(query) {
                fetch(`api/search_products.php?query=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        searchResults.innerHTML = '';

                        if (data.success && data.products.length > 0) {
                            searchResults.classList.remove('hidden');

                            data.products.forEach(product => {
                                const resultItem = document.createElement('div');
                                resultItem.className = 'py-2 px-4 hover:bg-gray-100 cursor-pointer border-b';
                                resultItem.innerHTML = `
                                    <div class="flex items-center">
                                        <span class="material-icons-round text-gray-400 mr-2">inventory_2</span>
                                        <div>
                                            <div class="font-medium">${product.product_name}</div>
                                            <div class="text-xs text-gray-500">
                                                Code: ${product.barcode} | Stock: ${product.quantity} ${product.unit || 'unité(s)'}
                                            </div>
                                        </div>
                                    </div>
                                `;

                                resultItem.addEventListener('click', function () {
                                    displaySelectedProduct(product);
                                    searchResults.classList.add('hidden');
                                    productSearch.value = '';
                                });

                                searchResults.appendChild(resultItem);
                            });
                        } else {
                            searchResults.classList.remove('hidden');
                            searchResults.innerHTML = `
                                <div class="py-4 px-4 text-center text-gray-500">
                                    Aucun produit trouvé
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        searchResults.innerHTML = `
                            <div class="py-4 px-4 text-center text-red-500">
                                Erreur lors de la recherche
                            </div>
                        `;
                        searchResults.classList.remove('hidden');
                    });
            }

            // Fonction pour afficher le produit sélectionné
            function displaySelectedProduct(product) {
                // Masquer les méthodes de sélection
                document.getElementById('scanMethod').classList.add('hidden');
                document.getElementById('searchMethod').classList.add('hidden');

                // Afficher les informations du produit
                document.getElementById('selectedProductInfo').classList.remove('hidden');
                document.getElementById('selectedProductName').textContent = product.product_name;
                document.getElementById('selectedProductBarcode').textContent = product.barcode;
                document.getElementById('selectedProductQuantity').textContent = `${product.quantity} ${product.unit || 'unité(s)'}`;

                // Stocker l'ID du produit
                document.getElementById('productId').value = product.id;

                // Mettre à jour l'unité dans le formulaire
                document.getElementById('unitDisplay').textContent = product.unit || 'unité(s)';

                // Afficher la section d'informations sur le retour
                document.getElementById('returnInfoSection').classList.remove('hidden');
                document.getElementById('submitBtnContainer').classList.remove('hidden');

                // Mettre le focus sur le champ quantité
                document.getElementById('quantity').focus();
            }

            // Fonction pour soumettre le formulaire de retour
            function submitReturnForm(formData) {
                // Afficher un indicateur de chargement
                const submitBtn = document.querySelector('#submitBtnContainer button');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.setAttribute('disabled', 'disabled');
                submitBtn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Traitement en cours...
                `;

                // Envoyer les données
                fetch('api/process_return.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData),
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Retour enregistré',
                                text: 'Le retour a été enregistré avec succès. Que souhaitez-vous faire maintenant?',
                                icon: 'success',
                                showCancelButton: true,
                                confirmButtonText: 'Ajouter un autre retour',
                                cancelButtonText: 'Voir la liste des retours'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // Réinitialiser le formulaire
                                    document.getElementById('returnForm').reset();

                                    // Masquer les sections et afficher la méthode de scan
                                    document.getElementById('selectedProductInfo').classList.add('hidden');
                                    document.getElementById('returnInfoSection').classList.add('hidden');
                                    document.getElementById('submitBtnContainer').classList.add('hidden');
                                    document.getElementById('scanMethod').classList.remove('hidden');

                                    // Focus sur le champ code-barres
                                    document.getElementById('barcodeInput').focus();
                                } else {
                                    // Rediriger vers la liste des retours
                                    window.location.href = 'index.php';
                                }
                            });
                        } else {
                            Swal.fire({
                                title: 'Erreur',
                                text: data.message || 'Une erreur est survenue lors de l\'enregistrement du retour.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }

                        // Restaurer le bouton
                        submitBtn.removeAttribute('disabled');
                        submitBtn.innerHTML = originalBtnText;
                    })
                    .catch(error => {
                        console.error('Erreur:', error);

                        Swal.fire({
                            title: 'Erreur de connexion',
                            text: 'Une erreur est survenue lors de la communication avec le serveur.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });

                        // Restaurer le bouton
                        submitBtn.removeAttribute('disabled');
                        submitBtn.innerHTML = originalBtnText;
                    });
            }
        });
    </script>
</body>

</html>