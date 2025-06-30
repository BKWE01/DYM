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
include_once '../../database/connection.php';

try {
    // Récupérer la liste des fournisseurs
    $fournisseursStmt = $pdo->query("SELECT * FROM fournisseurs ORDER BY nom ASC");
    $fournisseurs = $fournisseursStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Erreur de connexion à la base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrée de produits - DYM STOCK</title>
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

        /* Style pour les badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            color: #fff;
            white-space: nowrap;
            border-radius: 9999px;
        }

        .badge-blue {
            background-color: #3b82f6;
        }

        .badge-green {
            background-color: #10b981;
        }

        .badge-purple {
            background-color: #8b5cf6;
        }

        .badge-orange {
            background-color: #f59e0b;
        }

        .badge-red {
            background-color: #ef4444;
        }

        /* Style pour le toggle de source d'entrée */
        .toggle-container {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .toggle-btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
            cursor: pointer;
            transition: all 0.2s;
        }

        .toggle-btn:first-child {
            border-top-left-radius: 0.375rem;
            border-bottom-left-radius: 0.375rem;
        }

        .toggle-btn:last-child {
            border-top-right-radius: 0.375rem;
            border-bottom-right-radius: 0.375rem;
        }

        .toggle-btn.active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        /* Style pour le champ de provenance conditionnelle */
        .fournisseur-field-container,
        .provenance-field-container {
            transition: all 0.3s ease;
        }

        /* Style pour le fournisseur auto-sélectionné */
        .auto-selected {
            border-color: #3b82f6 !important;
            background-color: #f0f7ff !important;
        }

        /* Indicateur d'erreur */
        .error-field {
            border-color: #ef4444 !important;
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

        /* Style pour zone de drop */
        .file-upload-container {
            border: 2px dashed #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-container:hover {
            border-color: #3b82f6;
            background-color: #f0f7ff;
        }

        .file-upload-container.dragging {
            background-color: #f0f7ff;
            border-color: #3b82f6;
        }

        .upload-icon {
            font-size: 2rem;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        /* Style pour la liste des fichiers */
        .file-list {
            margin-top: 10px;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 4px;
            background-color: #f8fafc;
        }

        .file-item .material-icons {
            margin-right: 8px;
            color: #64748b;
        }

        .file-item .file-name {
            flex-grow: 1;
            font-size: 0.875rem;
        }

        .file-item .file-remove {
            color: #ef4444;
            cursor: pointer;
        }

        /* Style pour le projet prioritaire */
        .priority-project {
            border: 2px solid #3b82f6;
            background-color: #eff6ff;
        }

        .priority-project-info {
            display: none;
            margin-top: 4px;
            font-size: 0.75rem;
            color: #3b82f6;
        }

        .priority-project-select:not([value=""])+.priority-project-info {
            display: block;
        }

        /* Style pour les commandes partielles */
        .partial-order-info {
            border-left: 3px solid #f59e0b;
            padding-left: 12px;
            margin: 8px 0;
            background-color: #fffbeb;
            border-radius: 4px;
        }

        .partial-progress {
            height: 8px;
            background-color: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 6px;
        }

        .partial-progress-bar {
            height: 100%;
            background-color: #f59e0b;
            border-radius: 4px;
        }

        /* Style pour le modal de détails */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 40;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            border-radius: 8px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react/17.0.2/umd/react.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react-dom/17.0.2/umd/react-dom.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/antd/4.16.13/antd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include_once 'sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once 'header.php'; ?>

            <main class="p-6 flex-1">

                <!-- Alerte d'information sur le dispatching -->
                <div class="flex p-4 mb-6 text-sm text-blue-800 rounded-lg bg-blue-50 border border-blue-300"
                    role="alert">
                    <svg class="flex-shrink-0 inline w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20"
                        xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z"
                            clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <span class="font-medium">Système de dispatching actif!</span> Les produits entrants seront
                        automatiquement répartis pour combler les commandes en attente. Seule la quantité restante
                        sera ajoutée au stock général. <br>
                        <span class="mt-1 block"><strong>Nouveau :</strong> Gestion améliorée des commandes partielles -
                            Le système reconnaît et traite maintenant automatiquement les commandes partielles des
                            projets et des demandes système.</span>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md ant-card">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold">Entrée de produits</h1>
                        <a href="../fournisseurs/fournisseurs.php"
                            class="flex items-center bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition-colors duration-300">
                            <span class="material-icons mr-2">business</span>
                            Gérer les fournisseurs
                        </a>
                    </div>

                    <form id="barcodeForm" class="mb-6">
                        <div class="flex space-x-4">
                            <div class="ant-input-group-wrapper flex-grow">
                                <div class="ant-input-group">
                                    <span class="ant-input-group-addon">
                                        <i class="fas fa-barcode text-gray-400"></i>
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

                    <!-- Container pour afficher les informations sur les commandes partielles -->
                    <div id="partial-orders-container" class="mb-4 hidden">
                        <h2 class="text-lg font-medium mb-2 flex items-center text-amber-700">
                            <span class="material-icons mr-2">assignment_late</span>
                            Commandes partielles détectées
                        </h2>
                        <div id="partial-orders-content" class="border border-amber-200 rounded-lg p-4 bg-amber-50">
                            <!-- Le contenu sera rempli dynamiquement -->
                        </div>
                    </div>

                    <div id="productList" class="space-y-4">
                        <!-- Les produits scannés seront ajoutés ici -->
                    </div>

                    <!-- Zone d'upload de facture -->
                    <div class="mt-6 mb-6">
                        <h3 class="text-lg font-medium mb-2">Facture ou bon de livraison (optionnel)</h3>
                        <div id="dropzone" class="file-upload-container">
                            <input type="file" id="invoice-file" name="invoice_file" class="hidden"
                                accept=".pdf,.jpg,.jpeg,.png">
                            <span class="material-icons upload-icon">cloud_upload</span>
                            <p class="mb-1 font-medium">Glissez-déposez un fichier ici</p>
                            <p class="text-sm text-gray-500">ou cliquez pour sélectionner un fichier</p>
                            <p class="text-xs text-gray-400 mt-2">Formats acceptés: PDF, JPG, JPEG, PNG (Max 5 Mo)</p>
                        </div>
                        <div id="file-list" class="file-list"></div>
                    </div>

                    <button id="submitEntries" class="mt-6 ant-btn ant-btn-primary ant-btn-lg">
                        Valider les entrées
                    </button>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal pour afficher les détails des commandes partielles -->
    <div id="partial-details-modal" class="modal-backdrop">
        <div class="modal-content p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold" id="modal-title">Détails de la commande partielle</h2>
                <button id="close-modal" class="text-gray-500 hover:text-gray-700">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div id="modal-content">
                <!-- Le contenu sera rempli dynamiquement -->
            </div>
            <div class="mt-6 flex justify-end">
                <button id="close-modal-btn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Fermer
                </button>
            </div>
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

        // Nettoyage du code-barres
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
                            // Vérifier s'il y a des commandes en attente pour ce produit
                            checkPendingOrders(data.product.product_name, data.product.id)
                                .then(pendingOrdersData => {
                                    // Récupérer le fournisseur pour ce produit si disponible
                                    getSupplierForProduct(data.product.product_name)
                                        .then(supplier => {
                                            // Ajouter le produit à la liste avec le fournisseur pré-rempli
                                            addProductToList(data.product, supplier);

                                            // Vérifier les commandes partielles pour ce produit
                                            checkPartialOrders(data.product.product_name, data.product.id);

                                            message.success('Produit ajouté avec succès');
                                        })
                                        .catch(error => {
                                            console.error("Erreur lors de la récupération du fournisseur:", error);
                                            // En cas d'erreur, ajouter quand même le produit sans fournisseur pré-rempli
                                            addProductToList(data.product, null);

                                            // Quand même vérifier les commandes partielles
                                            checkPartialOrders(data.product.product_name, data.product.id);

                                            message.success('Produit ajouté avec succès');
                                        });
                                });
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

        // Fonction pour vérifier les commandes partielles
        function checkPartialOrders(productName, productId) {
            fetch(`commande_partielle_traitement/check_partial_orders.php?product_id=${productId}&product_name=${encodeURIComponent(productName)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.hasPartialOrders) {
                        // Afficher les informations sur les commandes partielles
                        displayPartialOrders(data.orders, productName);
                    }
                })
                .catch(error => {
                    console.error("Erreur lors de la vérification des commandes partielles:", error);
                });
        }

        // Fonction pour afficher les commandes partielles
        function displayPartialOrders(orders, productName) {
            const container = document.getElementById('partial-orders-container');
            const content = document.getElementById('partial-orders-content');

            // Construire le contenu HTML
            let html = `<p class="mb-2">Le produit <strong>${productName}</strong> a <strong>${orders.length}</strong> commande(s) partielle(s) en cours:</p>`;

            orders.forEach(order => {
                // Calculer le pourcentage de progression
                const initialQty = parseFloat(order.initial_qty || order.total_quantity || 0);
                const remainingQty = parseFloat(order.qt_restante || 0);
                const orderedQty = initialQty - remainingQty;
                const progress = initialQty > 0 ? Math.round((orderedQty / initialQty) * 100) : 0;

                // Déterminer la source et l'afficher avec un badge
                const sourceClass = order.source_table === 'besoins' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800';
                const sourceLabel = order.source_table === 'besoins' ? 'Système' : 'Projet';

                html += `
        <div class="partial-order-info p-3 mb-2">
            <div class="flex justify-between items-start">
                <div>
                    <p class="font-medium">${order.designation}</p>
                    <div class="flex items-center">
                        <p class="text-sm text-gray-600 mr-2">Projet: ${order.code_projet} - ${order.nom_client}</p>
                        <span class="px-2 py-0.5 text-xs rounded-full ${sourceClass}">${sourceLabel}</span>
                    </div>
                </div>
                <button class="text-blue-600 hover:text-blue-800 view-details" 
                        data-order-id="${order.id}" 
                        data-product-name="${productName}"
                        data-source="${order.source_table}">
                    <span class="material-icons text-sm">visibility</span>
                </button>
            </div>
            <div class="flex justify-between text-sm mt-1">
                <span>Commandé: ${orderedQty.toFixed(2)} / ${initialQty.toFixed(2)}</span>
                <span class="text-amber-600">Restant: ${remainingQty.toFixed(2)}</span>
            </div>
            <div class="partial-progress">
                <div class="partial-progress-bar" style="width: ${progress}%"></div>
            </div>
        </div>`;
            });

            html += `
    <div class="mt-3 text-sm text-gray-600">
        <p><span class="material-icons align-middle text-amber-500 text-sm">info</span> 
        Ces commandes partielles seront automatiquement complétées lors de l'entrée en stock.</p>
    </div>`;

            content.innerHTML = html;
            container.classList.remove('hidden');

            // Ajouter les événements pour voir les détails
            document.querySelectorAll('.view-details').forEach(btn => {
                btn.addEventListener('click', function () {
                    const orderId = this.getAttribute('data-order-id');
                    const productName = this.getAttribute('data-product-name');
                    const source = this.getAttribute('data-source') || 'expression_dym';
                    showPartialOrderDetails(orderId, productName, source);
                });
            });
        }

        // Fonction pour afficher les détails d'une commande partielle
        function showPartialOrderDetails(orderId, productName, source = 'expression_dym') {
            // Afficher un loader
            document.getElementById('modal-title').textContent = 'Chargement...';
            document.getElementById('modal-content').innerHTML = `
    <div class="flex justify-center items-center py-10">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
    </div>`;

            // Afficher le modal
            document.getElementById('partial-details-modal').style.display = 'flex';

            // Charger les détails avec la source
            fetch(`commande_partielle_traitement/get_partial_order_details.php?id=${orderId}&source=${source}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const material = data.material;
                        const linkedOrders = data.linkedOrders || [];
                        const stats = data.stats || {};
                        const source = data.source || 'expression_dym';

                        // Adapter les propriétés en fonction de la source
                        const designation = source === 'besoins' ? material.designation_article : material.designation;
                        const sourceLabel = source === 'besoins' ? 'Système' : 'Projet';
                        const sourceClass = source === 'besoins' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800';

                        // Mettre à jour le titre
                        document.getElementById('modal-title').textContent = `Détails: ${designation}`;

                        // Construire le contenu avec badge de source
                        let html = `
                <div class="bg-amber-50 p-4 rounded-lg mb-4 border border-amber-200">
                    <div class="flex justify-between items-start mb-3">
                        <h3 class="font-medium">${designation}</h3>
                        <span class="px-2 py-1 text-xs rounded-full ${sourceClass}">${sourceLabel}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-sm text-gray-600">Projet:</p>
                            <p class="font-medium">${material.code_projet} - ${material.nom_client}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Fournisseur:</p>
                            <p class="font-medium">${material.fournisseur || 'Non spécifié'}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <p class="text-sm text-gray-600">Quantité initiale:</p>
                            <p class="font-medium">${stats.initial_qty.toFixed(2)} ${material.unit || ''}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Déjà commandé:</p>
                            <p class="font-medium">${(stats.initial_qty - stats.remaining_qty).toFixed(2)} ${material.unit || ''}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Restant à commander:</p>
                            <p class="font-medium text-amber-600">${stats.remaining_qty.toFixed(2)} ${material.unit || ''}</p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-1">Progression:</p>
                        <div class="bg-gray-200 rounded-full h-2.5">
                            <div class="bg-amber-500 h-2.5 rounded-full" style="width: ${stats.progress}%"></div>
                        </div>
                        <p class="text-right text-xs mt-1">${stats.progress}% complété</p>
                    </div>
                </div>`;

                        // Ajouter l'historique des commandes si disponible
                        if (linkedOrders.length > 0) {
                            html += `
                    <div class="mb-4">
                        <h3 class="font-medium mb-2">Historique des commandes:</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix unitaire</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fournisseur</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">`;

                            linkedOrders.forEach(order => {
                                const orderDate = new Date(order.date_achat).toLocaleDateString('fr-FR');
                                html += `
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap">${orderDate}</td>
                            <td class="px-3 py-2 whitespace-nowrap">${parseFloat(order.quantity).toFixed(2)} ${material.unit || ''}</td>
                            <td class="px-3 py-2 whitespace-nowrap">${parseFloat(order.prix_unitaire || 0).toFixed(2)} FCFA</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                ${order.status === 'reçu' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}">
                                    ${order.status}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">${order.fournisseur || 'Non spécifié'}</td>
                        </tr>`;
                            });

                            html += `
                                </tbody>
                            </table>
                        </div>
                    </div>`;
                        }

                        // Informations sur le dispatching automatique
                        html += `
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <h3 class="font-medium flex items-center mb-2 text-blue-800">
                        <span class="material-icons mr-1 text-blue-600">info</span>
                        Information sur l'entrée en stock
                    </h3>
                    <p class="text-sm text-blue-800">
                        Lors de l'entrée en stock de "${designation}", le système de dispatching automatique 
                        allouera en priorité la quantité nécessaire pour compléter cette commande partielle.
                    </p>
                </div>`;

                        // Mettre à jour le contenu du modal
                        document.getElementById('modal-content').innerHTML = html;
                    } else {
                        document.getElementById('modal-title').textContent = 'Erreur';
                        document.getElementById('modal-content').innerHTML = `
                <div class="p-4 bg-red-50 rounded-lg border border-red-200 text-red-700">
                    <p>Impossible de charger les détails de la commande: ${data.message || 'Erreur inconnue'}</p>
                </div>`;
                    }
                })
                .catch(error => {
                    console.error("Erreur lors du chargement des détails:", error);
                    document.getElementById('modal-title').textContent = 'Erreur';
                    document.getElementById('modal-content').innerHTML = `
            <div class="p-4 bg-red-50 rounded-lg border border-red-200 text-red-700">
                <p>Une erreur s'est produite lors du chargement des détails.</p>
            </div>`;
                });
        }

        // Fonction pour récupérer le fournisseur associé à un produit
        async function getSupplierForProduct(productName) {
            try {
                const response = await fetch(`get_product_supplier.php?product_name=${encodeURIComponent(productName)}`);
                const data = await response.json();

                if (data.success && data.fournisseur) {
                    return data.fournisseur;
                }
                return null;
            } catch (error) {
                console.error("Erreur lors de la récupération du fournisseur:", error);
                return null;
            }
        }

        // Ajout du produit à la liste
        function addProductToList(product, recommendedSupplier) {
            const productList = document.getElementById('productList');
            const productDiv = document.createElement('div');
            productDiv.className = 'flex flex-wrap items-center space-x-4 p-4 bg-gray-50 rounded-md';
            productDiv.setAttribute('data-product-id', product.id);
            productDiv.setAttribute('data-product-name', product.product_name);

            // Génère le contenu HTML de base
            let html = `
                <input type="hidden" name="product_id" value="${product.id}">
                <input type="hidden" name="entry_type" value="commande" class="entry-type-input">
                <p class="flex-grow text-gray-700">${product.product_name}</p>
<input type="number" name="quantity" value="1" min="0.01" step="any" class="w-20 p-2 border rounded-md ant-input product-quantity" required onchange="validateQuantity(this)">
                <div class="toggle-container">
                    <button type="button" class="toggle-btn active toggle-commande" data-type="commande">Commande</button>
                    <button type="button" class="toggle-btn toggle-autre" data-type="autre">Autre provenance</button>
                </div>

                <div class="fournisseur-field-container w-full sm:w-64">
                    <select name="fournisseur" class="w-full p-2 border rounded-md ant-input supplier-select">
                        <option value="">Sélectionner un fournisseur</option>
                        <?php foreach ($fournisseurs as $fournisseur): ?>
                            <option value="<?php echo htmlspecialchars($fournisseur['nom']); ?>" 
                                data-category="<?php echo htmlspecialchars($fournisseur['categorie'] ?? ''); ?>">
                                <?php echo htmlspecialchars($fournisseur['nom']); ?>
                                <?php if (!empty($fournisseur['categorie'])): ?>
                                    (<?php echo htmlspecialchars($fournisseur['categorie']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="nouveau_fournisseur">+ Ajouter un nouveau fournisseur</option>
                    </select>
                </div>

                <div class="provenance-field-container w-full sm:w-64">
                    <input type="text" name="provenance" placeholder="Saisir la provenance" class="w-full p-2 border rounded-md ant-input">
                </div>

                <div class="w-64">
                    <select name="nom_projet" class="w-full p-2 border rounded-md ant-input priority-project-select">
                        <option value="">Aucun projet (stock général)</option>
                        <?php
                        // Récupérer les projets actifs qui ont des commandes en attente
                        try {
                            $projetStmt = $pdo->query("
                                -- Projets depuis identification_projet
                                SELECT DISTINCT ip.idExpression, ip.code_projet, ip.nom_client 
                                FROM identification_projet ip
                                JOIN achats_materiaux am ON ip.idExpression = am.expression_id
                                WHERE am.status = 'commandé'

                                UNION

                                -- Projets système depuis besoins
                                SELECT DISTINCT b.idBesoin as idExpression, 'SYS' as code_projet, 
                                      CONCAT('Demande ', COALESCE(d.service_demandeur, 'Système')) as nom_client
                                FROM besoins b
                                LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                                JOIN achats_materiaux am ON b.idBesoin = am.expression_id
                                WHERE am.status = 'commandé'

                                ORDER BY code_projet, nom_client
                            ");
                            $projets = $projetStmt->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($projets as $projet) {
                                echo '<option value="' . htmlspecialchars($projet['code_projet']) . '">'
                                    . htmlspecialchars($projet['code_projet'] . ' - ' . $projet['nom_client']) . '</option>';
                            }
                        } catch (PDOException $e) {
                            // Erreur silencieuse
                        }
                        ?>
                    </select>
                    <div class="priority-project-info">
                        <span class="material-icons align-middle text-xs mr-1">priority_high</span>
                        Ce projet sera prioritaire pour le dispatching
                    </div>
                </div>
                <button type="button" class="text-red-500 hover:text-red-700 ant-btn ant-btn-dangerous delete-product-btn">Supprimer</button>
            `;

            productDiv.innerHTML = html;
            productList.appendChild(productDiv);

            // Récupérer les éléments du produit ajouté
            const fournisseurSelect = productDiv.querySelector('select[name="fournisseur"]');
            const provenanceInput = productDiv.querySelector('input[name="provenance"]');
            const entryTypeInput = productDiv.querySelector('.entry-type-input');
            const toggleCommande = productDiv.querySelector('.toggle-commande');
            const toggleAutre = productDiv.querySelector('.toggle-autre');
            const fournisseurContainer = productDiv.querySelector('.fournisseur-field-container');
            const provenanceContainer = productDiv.querySelector('.provenance-field-container');
            const deleteButton = productDiv.querySelector('.delete-product-btn');
            const projetSelect = productDiv.querySelector('select[name="nom_projet"]');
            const quantityInput = productDiv.querySelector('.product-quantity');

            // Configurer le comportement initial (par défaut: commande)
            fournisseurContainer.style.display = 'block';
            provenanceContainer.style.display = 'none';

            // Sélectionner automatiquement le fournisseur si disponible
            if (recommendedSupplier) {
                // Parcourir les options pour trouver celle qui correspond
                Array.from(fournisseurSelect.options).forEach(option => {
                    if (option.value === recommendedSupplier) {
                        option.selected = true;
                        fournisseurSelect.classList.add('auto-selected');

                        // Afficher une notification pour informer l'utilisateur
                        notification.info({
                            message: 'Fournisseur auto-sélectionné',
                            description: `Le fournisseur "${recommendedSupplier}" a été automatiquement sélectionné pour ce produit.`,
                            placement: 'bottomRight',
                            duration: 4
                        });
                    }
                });
            }

            // Configurer les boutons toggle
            toggleCommande.addEventListener('click', function () {
                toggleEntryType(this, toggleAutre, fournisseurContainer, provenanceContainer, entryTypeInput, 'commande');
            });

            toggleAutre.addEventListener('click', function () {
                toggleEntryType(this, toggleCommande, provenanceContainer, fournisseurContainer, entryTypeInput, 'autre');
            });

            // Gérer l'ajout d'un nouveau fournisseur
            fournisseurSelect.addEventListener('change', function () {
                if (this.value === 'nouveau_fournisseur') {
                    openAddFournisseurModal(fournisseurSelect);
                }
            });

            // Configurer le bouton de suppression
            deleteButton.addEventListener('click', function () {
                productDiv.remove();

                // Si c'était le dernier produit, cacher le conteneur des commandes partielles
                if (document.querySelectorAll('#productList > div').length === 0) {
                    document.getElementById('partial-orders-container').classList.add('hidden');
                }
            });

            // Event pour la sélection de projet prioritaire
            projetSelect.addEventListener('change', function () {
                const projetInfo = this.nextElementSibling;
                if (this.value) {
                    this.classList.add('priority-project');
                } else {
                    this.classList.remove('priority-project');
                }
            });

            // Ajouter un événement pour mettre à jour la quantité en fonction des commandes partielles
            quantityInput.addEventListener('change', function () {
                checkPartialOrdersForQuantity(product.id, product.product_name, this.value);
            });
        }

        // Fonction pour vérifier les commandes partielles lorsqu'une quantité est modifiée
        function checkPartialOrdersForQuantity(productId, productName, quantity) {
            // À implémenter pour donner des avertissements si la quantité est insuffisante
            // pour couvrir les commandes partielles
            fetch(`commande_partielle_traitement/check_partial_orders.php?product_id=${productId}&product_name=${encodeURIComponent(productName)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.hasPartialOrders) {
                        const totalNeeded = data.totalQuantity || 0;
                        if (parseFloat(quantity) < totalNeeded) {
                            notification.warning({
                                message: 'Quantité insuffisante',
                                description: `La quantité saisie (${quantity}) est inférieure au total des commandes partielles (${totalNeeded}). Le dispatching sera partiel.`,
                                placement: 'bottomRight',
                                duration: 6
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error("Erreur lors de la vérification des quantités:", error);
                });
        }

        // Fonction pour changer le type d'entrée (commande ou autre provenance)
        function toggleEntryType(activeBtn, inactiveBtn, showContainer, hideContainer, entryTypeInput, entryType) {
            // Mettre à jour l'apparence des boutons
            activeBtn.classList.add('active');
            inactiveBtn.classList.remove('active');

            // Afficher/masquer les conteneurs appropriés
            showContainer.style.display = 'block';
            hideContainer.style.display = 'none';

            // Mettre à jour le type d'entrée
            entryTypeInput.value = entryType;
        }

        // Fonction pour ouvrir la modal d'ajout de fournisseur
        function openAddFournisseurModal(fournisseurSelect) {
            Swal.fire({
                title: 'Ajouter un nouveau fournisseur',
                html: `
                    <form id="quick-add-supplier">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1 text-left">Nom du fournisseur *</label>
                            <input type="text" id="quick-nom" class="w-full p-2 border rounded" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1 text-left">Catégorie</label>
                            <select id="quick-categorie" class="w-full p-2 border rounded">
                                <option value="">Sélectionner une catégorie</option>
                                <option value="Matériaux ferreux">Matériaux ferreux</option>
                                <option value="Matériaux non ferreux">Matériaux non ferreux</option>
                                <option value="Électrique">Électrique</option>
                                <option value="Plomberie">Plomberie</option>
                                <option value="Outillage">Outillage</option>
                                <option value="Quincaillerie">Quincaillerie</option>
                                <option value="Peinture">Peinture</option>
                                <option value="Divers">Divers</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1 text-left">Email</label>
                            <input type="email" id="quick-email" class="w-full p-2 border rounded">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1 text-left">Téléphone</label>
                            <input type="tel" id="quick-telephone" class="w-full p-2 border rounded">
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Ajouter',
                cancelButtonText: 'Annuler',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const nom = document.getElementById('quick-nom').value;
                    const categorie = document.getElementById('quick-categorie').value;
                    const email = document.getElementById('quick-email').value;
                    const telephone = document.getElementById('quick-telephone').value;

                    if (!nom) {
                        Swal.showValidationMessage('Le nom du fournisseur est requis');
                        return false;
                    }

                    return fetch('ajax_add_fournisseur.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'add',
                            nom: nom,
                            categorie: categorie,
                            email: email,
                            telephone: telephone
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                return data;
                            } else {
                                throw new Error(data.message || 'Erreur lors de l\'ajout du fournisseur');
                            }
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Erreur: ${error.message}`);
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value.success) {
                    // Ajouter le nouveau fournisseur à la liste déroulante
                    const newOption = document.createElement('option');
                    newOption.value = result.value.fournisseur.nom;
                    newOption.text = result.value.fournisseur.nom;

                    // Insérer le nouveau fournisseur avant l'option "Ajouter un nouveau fournisseur"
                    const addOption = fournisseurSelect.querySelector('option[value="nouveau_fournisseur"]');
                    fournisseurSelect.insertBefore(newOption, addOption);

                    // Sélectionner le nouveau fournisseur
                    fournisseurSelect.value = result.value.fournisseur.nom;

                    Swal.fire(
                        'Fournisseur ajouté',
                        'Le fournisseur a été ajouté avec succès',
                        'success'
                    );
                } else {
                    // Réinitialiser la valeur du select si l'ajout est annulé
                    fournisseurSelect.value = "";
                }
            });
        }

        // Fonction pour vérifier les commandes en attente pour un produit
        function checkPendingOrders(productName, productId) {
            return fetch(`check_pending_orders.php?product_id=${productId}&product_name=${encodeURIComponent(productName)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.hasPendingOrders) {
                        // Trier les commandes par type (projet et système)
                        const projectOrders = data.orders.filter(order => order.code_projet !== 'SYS');
                        const systemOrders = data.orders.filter(order => order.code_projet === 'SYS');

                        // Construire l'HTML du contenu
                        let ordersList = '';

                        // Ajouter les commandes de projet
                        if (projectOrders.length > 0) {
                            ordersList += `<h5 class="font-medium mt-3 mb-1 text-blue-700">Projets (${projectOrders.length}):</h5>`;
                            ordersList += '<ul class="list-disc pl-5 mt-1 mb-2">';
                            projectOrders.forEach(order =>
                                ordersList += `<li>${order.designation} - Projet: ${order.nom_client} - Quantité: ${parseFloat(order.quantity).toFixed(2)}</li>`
                            );
                            ordersList += '</ul>';
                        }

                        // Ajouter les commandes système
                        if (systemOrders.length > 0) {
                            ordersList += `<h5 class="font-medium mt-3 mb-1 text-purple-700">Demandes système (${systemOrders.length}):</h5>`;
                            ordersList += '<ul class="list-disc pl-5 mt-1">';
                            systemOrders.forEach(order =>
                                ordersList += `<li>${order.designation} - ${order.nom_client} - Quantité: ${parseFloat(order.quantity).toFixed(2)}</li>`
                            );
                            ordersList += '</ul>';
                        }

                        // Afficher un message d'alerte
                        Swal.fire({
                            title: 'Commandes en attente détectées',
                            html: `<div class="text-left">
                        <p>Ce produit a ${data.ordersCount} commande(s) en attente pour un total de ${parseFloat(data.totalQuantity).toFixed(2)} unités.</p>
                        ${ordersList}
                        <div class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                            <p class="text-blue-800 text-sm">
                                <span class="material-icons align-middle mr-1 text-blue-600 text-sm">info</span>
                                Le système de dispatching attribuera automatiquement ces unités aux commandes en attente.
                            </p>
                        </div>
                       </div>`,
                            icon: 'info',
                            confirmButtonText: 'Compris'
                        });
                    }
                    return data;
                })
                .catch(error => {
                    console.error('Erreur lors de la vérification des commandes en attente:', error);
                    return { hasPendingOrders: false };
                });
        }

        // Initialisation de la zone de dépôt de fichiers (dropzone)
        document.addEventListener('DOMContentLoaded', function () {
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('invoice-file');
            const fileList = document.getElementById('file-list');
            const closeModalBtns = document.querySelectorAll('#close-modal, #close-modal-btn');

            // Configurer les boutons de fermeture du modal
            closeModalBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    document.getElementById('partial-details-modal').style.display = 'none';
                });
            });

            // Événements pour le drag & drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            // Appliquer le style quand on survole la zone de drop
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, function () {
                    dropzone.classList.add('dragging');
                }, false);
            });

            // Retirer le style quand on quitte la zone de drop
            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, function () {
                    dropzone.classList.remove('dragging');
                }, false);
            });

            // Gérer le dépôt de fichier
            dropzone.addEventListener('drop', function (e) {
                const dt = e.dataTransfer;
                const files = dt.files;

                if (files.length > 0) {
                    fileInput.files = files;
                    updateFileList(files[0]);
                }
            }, false);

            // Gérer la sélection de fichier via le bouton
            dropzone.addEventListener('click', function () {
                fileInput.click();
            }, false);

            fileInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    updateFileList(this.files[0]);
                }
            }, false);

            // Mettre à jour l'affichage de la liste des fichiers
            function updateFileList(file) {
                // Vérifier la taille du fichier (max 5 Mo)
                if (file.size > 5 * 1024 * 1024) {
                    notification.error({
                        message: 'Fichier trop volumineux',
                        description: 'La taille du fichier ne doit pas dépasser 5 Mo.',
                    });
                    fileInput.value = ''; // Réinitialiser l'input
                    return;
                }

                // Vérifier le type de fichier
                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    notification.error({
                        message: 'Type de fichier non supporté',
                        description: 'Seuls les fichiers PDF, JPG, JPEG et PNG sont acceptés.',
                    });
                    fileInput.value = ''; // Réinitialiser l'input
                    return;
                }

                // Afficher le fichier sélectionné
                fileList.innerHTML = `
                    <div class="file-item">
                        <span class="material-icons">${file.type.includes('pdf') ? 'picture_as_pdf' : 'image'}</span>
                        <span class="file-name">${file.name} (${formatFileSize(file.size)})</span>
                        <span class="material-icons file-remove" onclick="removeFile()">close</span>
                    </div>
                `;
            }

            // Formater la taille du fichier
            function formatFileSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                else return (bytes / 1048576).toFixed(1) + ' MB';
            }

            // Fonction pour supprimer le fichier
            window.removeFile = function () {
                fileInput.value = '';
                fileList.innerHTML = '';
            }
        });

        // Modifier la fonction de soumission du formulaire pour inclure la facture et gérer les commandes partielles
        document.getElementById('submitEntries').addEventListener('click', function () {
            // Validation avant soumission
            let hasNegativeQuantity = false;
            document.querySelectorAll('.product-quantity').forEach(input => {
                const quantity = parseFloat(input.value);
                if (quantity <= 0) {
                    hasNegativeQuantity = true;
                    input.classList.add('border-red-500');
                    input.classList.add('shake');

                    setTimeout(() => {
                        input.classList.remove('border-red-500');
                        input.classList.remove('shake');
                    }, 600);
                }
            });

            if (hasNegativeQuantity) {
                notification.error({
                    message: 'Valeurs invalides',
                    description: 'Toutes les quantités doivent être des nombres positifs supérieurs à zéro.',
                    placement: 'bottomRight',
                    duration: 4
                });
                return; // Arrêter la soumission
            }

            const entries = [];
            let hasErrors = false;
            let priorityProject = '';

            document.querySelectorAll('#productList > div').forEach(div => {
                const entryType = div.querySelector('.entry-type-input').value;
                const productId = div.querySelector('input[name="product_id"]').value;
                const quantity = parseFloat(div.querySelector('input[name="quantity"]').value);
                const nomProjet = div.querySelector('select[name="nom_projet"]').value;

                // Si un projet est sélectionné, le mémoriser comme projet prioritaire
                if (nomProjet && !priorityProject) {
                    priorityProject = nomProjet;
                }

                let provenance = '';
                let fournisseur = '';

                // Récupérer la source en fonction du type d'entrée
                if (entryType === 'commande') {
                    fournisseur = div.querySelector('select[name="fournisseur"]').value;

                    if (!fournisseur) {
                        // Mettre en évidence le champ obligatoire
                        div.querySelector('select[name="fournisseur"]').classList.add('border-red-500');
                        div.querySelector('select[name="fournisseur"]').classList.add('shake');
                        hasErrors = true;
                    } else {
                        // Réinitialiser le style
                        div.querySelector('select[name="fournisseur"]').classList.remove('border-red-500');
                        div.querySelector('select[name="fournisseur"]').classList.remove('shake');
                    }

                    // Pour les commandes, le fournisseur est utilisé comme provenance
                    provenance = fournisseur;
                } else {
                    // Pour les autres sources, utiliser le champ provenance
                    provenance = div.querySelector('input[name="provenance"]').value;

                    if (!provenance) {
                        // Mettre en évidence le champ obligatoire
                        div.querySelector('input[name="provenance"]').classList.add('border-red-500');
                        div.querySelector('input[name="provenance"]').classList.add('shake');
                        hasErrors = true;
                    } else {
                        // Réinitialiser le style
                        div.querySelector('input[name="provenance"]').classList.remove('border-red-500');
                        div.querySelector('input[name="provenance"]').classList.remove('shake');
                    }
                }

                // Seulement ajouter à la liste des entrées si les champs obligatoires sont remplis
                if (entryType === 'commande' && fournisseur || entryType === 'autre' && provenance) {
                    entries.push({
                        product_id: productId,
                        quantity: quantity,
                        fournisseur: fournisseur,
                        provenance: provenance,
                        nom_projet: nomProjet,
                        entry_type: entryType,
                        product_name: div.getAttribute('data-product-name') // Ajout du nom du produit pour référence
                    });
                }
            });

            if (entries.length === 0) {
                notification.warning({
                    message: 'Aucun produit ajouté',
                    description: 'Veuillez ajouter au moins un produit avant de soumettre les entrées.',
                });
                return;
            }

            if (hasErrors) {
                notification.error({
                    message: 'Informations manquantes',
                    description: 'Veuillez remplir tous les champs obligatoires pour chaque produit.',
                });
                return;
            }

            // Vérifier si des entrées concernent des commandes partielles
            const checkPartialBeforeSubmit = async () => {
                let hasPartialOrders = false;
                let partialOrdersInfo = [];

                // Ajouter des propriétés aux entrées pour identifier les partielles
                for (const entry of entries) {
                    try {
                        const response = await fetch(`commande_partielle_traitement/check_partial_orders.php?product_id=${entry.product_id}&product_name=${encodeURIComponent(entry.product_name)}`);
                        const data = await response.json();

                        if (data.success && data.hasPartialOrders) {
                            hasPartialOrders = true;
                            entry.hasPartialOrders = true;
                            entry.partialOrdersCount = data.orders?.length || 0;
                            entry.partialOrdersTotal = data.totalQuantity || 0;

                            // Stocker les infos pour l'affichage
                            partialOrdersInfo.push({
                                product_name: entry.product_name,
                                count: entry.partialOrdersCount,
                                total: entry.partialOrdersTotal,
                                entry_quantity: entry.quantity
                            });
                        } else {
                            entry.hasPartialOrders = false;
                        }
                    } catch (error) {
                        console.error("Erreur lors de la vérification des commandes partielles:", error);
                        entry.hasPartialOrders = false;
                    }
                }

                if (hasPartialOrders) {
                    // Construire le message de confirmation
                    let confirmContent = `
                    <div class="text-left">
                        <p class="mb-3">Les produits suivants ont des commandes partielles qui seront automatiquement complétées :</p>
                        <ul class="list-disc pl-5 my-3 space-y-2">`;

                    partialOrdersInfo.forEach(info => {
                        const sufficient = info.entry_quantity >= info.total;
                        confirmContent += `
                            <li class="${sufficient ? 'text-green-700' : 'text-amber-700'}">
                                <strong>${info.product_name}</strong>
                                <span> - ${info.count} commande(s) partielle(s) pour un total de ${info.total} unités</span>
                                ${sufficient ?
                                `<span class="text-green-700"> (Quantité suffisante: ${info.entry_quantity})</span>` :
                                `<span class="text-amber-700 font-semibold"> (Quantité insuffisante: ${info.entry_quantity}/${info.total})</span>`
                            }
                            </li>`;
                    });

                    confirmContent += `
                        </ul>
                        <div class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                            <p class="text-blue-800 text-sm">
                                Le système va automatiquement compléter ces commandes partielles lors de l'entrée en stock.
                                ${partialOrdersInfo.some(info => info.entry_quantity < info.total) ?
                            `<br><span class="font-semibold text-amber-700">Attention: Certaines commandes partielles ne seront pas complètement satisfaites.</span>` :
                            ''
                        }
                            </p>
                        </div>
                    </div>`;

                    // Demander confirmation avant de poursuivre
                    return Swal.fire({
                        title: 'Commandes partielles détectées',
                        html: confirmContent,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Poursuivre l\'entrée en stock',
                        cancelButtonText: 'Annuler',
                        confirmButtonColor: '#3085d6'
                    }).then((result) => {
                        return result.isConfirmed;
                    });
                }

                return true; // Aucune commande partielle, continuer normalement
            };

            // Vérifier les commandes partielles puis soumettre si confirmé
            checkPartialBeforeSubmit().then(shouldContinue => {
                if (!shouldContinue) return;

                // Afficher un indicateur de chargement pendant le traitement du dispatching
                Swal.fire({
                    title: 'Traitement en cours...',
                    text: 'Veuillez patienter pendant le dispatching des produits',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Préparer les données à envoyer au serveur
                const requestData = {
                    entries: entries,
                    priority_project: priorityProject,
                    handle_partial_orders: true // Nouveau flag pour indiquer que le dispatching doit gérer les commandes partielles
                };

                // Ajouter les informations de la facture si un fichier a été sélectionné
                const fileInput = document.getElementById('invoice-file');
                if (fileInput && fileInput.files.length > 0) {
                    const file = fileInput.files[0];

                    // Créer un FormData pour l'upload de fichier
                    const formData = new FormData();
                    formData.append('invoice_file', file);

                    // Envoyer le fichier d'abord
                    fetch('upload_invoice.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Ajouter les informations de la facture à la requête principale
                                requestData.invoice = {
                                    file_path: data.file_path,
                                    original_filename: data.original_filename,
                                    file_type: data.file_type,
                                    file_size: data.file_size,
                                    upload_user_id: data.upload_user_id,
                                    supplier: entries.length > 0 ? entries[0].fournisseur : null
                                };

                                // Continuer avec la soumission des entrées
                                submitEntries(requestData);
                            } else {
                                Swal.close();
                                notification.error({
                                    message: 'Erreur',
                                    description: data.message || 'Une erreur est survenue lors de l\'upload de la facture.',
                                });
                            }
                        })
                        .catch(error => {
                            Swal.close();
                            console.error('Erreur:', error);
                            notification.error({
                                message: 'Erreur',
                                description: 'Une erreur est survenue lors de l\'upload de la facture.',
                            });
                        });
                } else {
                    // Pas de facture, soumettre directement les entrées
                    submitEntries(requestData);
                }
            });
        });

        // Fonction pour soumettre les entrées
        function submitEntries(requestData) {
            fetch('api_addEntries.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData),
            })
                .then(response => response.json())
                .then(data => {
                    Swal.close();

                    if (data.success) {
                        // Ajouter une section spécifique pour les commandes partielles si présente
                        let partialContent = '';
                        if (data.partial_orders && data.partial_orders.length > 0) {
                            partialContent = `
                            <div class="mt-4">
                                <h4 class="text-lg font-semibold text-amber-700 mb-2">Commandes partielles complétées</h4>
                                <div class="bg-amber-50 p-3 rounded-md mb-4 border border-amber-200">
                                    <ul class="list-disc pl-5 space-y-1">`;

                            data.partial_orders.forEach(order => {
                                const isComplete = order.remaining <= 0;
                                partialContent += `
                                <li>
                                    <span class="font-medium">${order.designation || order.product_name}</span>
                                    <span class="text-sm"> - ${order.allocated.toFixed(2)} unités attribuées au projet </span>
                                    <span class="text-sm font-medium">${order.project}</span>
                                    <span class="text-sm"> (${order.client})</span>
                                    ${isComplete ?
                                        `<span class="text-green-700"> - Commande complétée</span>` :
                                        `<span class="text-amber-700"> - Reste: ${order.remaining} unités</span>`
                                    }
                                </li>`;
                            });

                            partialContent += `
                                    </ul>
                                </div>
                            </div>`;
                        }

                        // Si des produits ont été dispatchés
                        // Pour les commandes normales
                        if (data.dispatching && data.dispatching.length > 0) {
                            // Créer un résumé des résultats de dispatching
                            const completedOrders = data.dispatching.filter(r => r.status === 'completed');
                            const partialOrders = data.dispatching.filter(r => r.status === 'partial');

                            // Initialisez detailedContent ici
                            let detailedContent = '';

                            if (completedOrders.length > 0) {
                                detailedContent += '<h4 class="text-lg font-semibold text-green-700 mb-2">Commandes complètement satisfaites</h4>';
                                detailedContent += '<div class="bg-green-50 p-3 rounded-md mb-4">';
                                detailedContent += '<ul class="list-disc pl-5 space-y-1">';

                                completedOrders.forEach(order => {
                                    detailedContent += `
            <li>
                <span class="font-medium">${order.product_name}</span>
                <span class="text-sm"> - ${order.allocated} unités attribuées au projet </span>
                <span class="text-sm font-medium">${order.project}</span>
                <span class="text-sm"> (${order.client})</span>
                <span class="text-sm text-green-700"> - Stock mis à jour</span>
            </li>`;
                                });

                                detailedContent += '</ul></div>';
                            }

                            if (partialOrders.length > 0) {
                                detailedContent += '<h4 class="text-lg font-semibold text-orange-600 mb-2">Commandes partiellement satisfaites</h4>';
                                detailedContent += '<div class="bg-orange-50 p-3 rounded-md">';
                                detailedContent += '<ul class="list-disc pl-5 space-y-1">';

                                partialOrders.forEach(order => {
                                    detailedContent += `
            <li>
                <span class="font-medium">${order.product_name}</span>
                <span class="text-sm"> - ${order.allocated} unités attribuées au projet </span>
                <span class="text-sm font-medium">${order.project}</span>
                <span class="text-sm"> (${order.client})</span>
                <span class="text-sm text-orange-700"> - Reste à recevoir: ${order.remaining} unités</span>
            </li>`;
                                });

                                detailedContent += '</ul></div>';
                            }

                            // Afficher le détail complet des résultats de dispatching
                            Swal.fire({
                                title: 'Dispatching effectué',
                                html: detailedContent,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {

                            // Notification standard si aucun dispatching normal n'a eu lieu
                            // mais peut-être des commandes partielles
                            let content = `
                            <div class="text-left">
                                <p class="mb-3">L'entrée de stock a été effectuée avec succès.</p>`;

                            // Ajouter le contenu des commandes partielles si présent
                            if (partialContent) {
                                content += partialContent;
                            }

                            // Ajouter des informations sur la facture si elle a été uploadée
                            if (data.invoice_id) {
                                content += `
                                <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                    <p class="text-blue-700">
                                        <span class="material-icons align-middle mr-1">receipt</span>
                                        Facture enregistrée avec succès (ID: ${data.invoice_id})
                                    </p>
                                </div>`;
                            }

                            content += '</div>';

                            // Si nous avons du contenu pour les commandes partielles, afficher une modal
                            // Sinon, afficher juste une notification
                            if (partialContent) {
                                Swal.fire({
                                    title: 'Entrée en stock réussie',
                                    html: content,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.reload();
                                    }
                                });
                            } else {
                                notification.success({
                                    message: 'Entrées ajoutées',
                                    description: data.message || 'Entrées de stock ajoutées avec succès.',
                                });

                                // Vider la liste des produits après l'ajout réussi
                                document.getElementById('productList').innerHTML = '';
                                document.getElementById('file-list').innerHTML = '';
                                document.getElementById('invoice-file').value = '';
                                document.getElementById('partial-orders-container').classList.add('hidden');
                            }
                        }
                    } else {
                        notification.error({
                            message: 'Erreur',
                            description: data.message,
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Erreur:', error);
                    notification.error({
                        message: 'Erreur',
                        description: 'Une erreur est survenue lors de l\'ajout des entrées.',
                    });
                });
        }

        // Fonction pour valider la quantité (rejeter les nombres négatifs ou nuls)
        function validateQuantity(input) {
            const value = parseFloat(input.value);
            if (value <= 0) {
                // Réinitialiser à 0.01 (valeur minimale autorisée)
                input.value = 0.01;

                // Afficher une notification d'erreur
                notification.error({
                    message: 'Valeur invalide',
                    description: 'La quantité doit être un nombre positif supérieur à zéro.',
                    placement: 'bottomRight',
                    duration: 4
                });

                // Mettre en évidence visuellement le champ
                input.classList.add('border-red-500');
                input.classList.add('shake');

                // Enlever la classe après l'animation
                setTimeout(() => {
                    input.classList.remove('border-red-500');
                    input.classList.remove('shake');
                }, 600);
            }
        }
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Script pour le système de dispatching -->
    <script src="assets/js/dispatching.js"></script>
</body>

</html>