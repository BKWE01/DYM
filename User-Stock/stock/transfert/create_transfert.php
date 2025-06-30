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
    <title>Créer un transfert - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/antd/4.16.13/antd.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
        }

        .ant-card {
            box-shadow: 0 1px 2px -2px rgba(0, 0, 0, 0.16), 0 3px 6px 0 rgba(0, 0, 0, 0.12), 0 5px 12px 4px rgba(0, 0, 0, 0.09);
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

        /* Styles pour l'autocomplétion */
        .autocomplete-container {
            position: relative;
        }

        .autocomplete-results {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 300px;
            overflow-y: auto;
            background-color: white;
            border: 1px solid #d1d5db;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .autocomplete-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
        }

        .autocomplete-item:hover,
        .autocomplete-item.selected {
            background-color: #f3f4f6;
        }

        .highlight {
            font-weight: bold;
            background-color: rgba(59, 130, 246, 0.1);
        }

        /* Style pour les tooltips */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Style pour les alert-infos */
        .alert-info {
            background-color: #e1f5fe;
            color: #0277bd;
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
        }

        .alert-info .icon {
            margin-right: 12px;
            font-size: 24px;
        }

        .alert-info .content {
            flex: 1;
        }

        .alert-info .title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        /* Style pour le résumé de transfert */
        .transfer-summary {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .transfer-summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .transfer-summary-item:last-child {
            border-bottom: none;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react/17.0.2/umd/react.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react-dom/17.0.2/umd/react-dom.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/antd/4.16.13/antd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include_once '../sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once '../header.php'; ?>

            <main class="p-6 flex-1">
                <div class="alert-info mb-6">
                    <span class="material-icons icon">info</span>
                    <div class="content">
                        <div class="title">À propos des transferts</div>
                        <p>Cette fonctionnalité permet de transférer des quantités réservées d'un
                            projet vers un autre pour les produits qui ont été reçus en stock.
                            Le transfert est soumis à validation et n'affecte pas le stock général.</p>
                        <p class="mt-2 text-xs text-blue-700">
                            <span class="material-icons text-xs align-middle mr-1">verified</span>
                            Seuls les produits ayant le statut "reçu" par le service achat sont disponibles pour
                            transfert.
                        </p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md ant-card">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold">Créer un nouveau transfert</h1>
                        <a href="transfert_manager.php"
                            class="flex items-center text-gray-600 hover:text-gray-900 py-2 px-4 rounded-md transition-colors duration-300">
                            <span class="material-icons mr-2">arrow_back</span>
                            Retour à la liste
                        </a>
                    </div>

                    <form id="transfertForm" class="space-y-6">
                        <!-- Sélection du produit -->
                        <div>
                            <label for="product" class="block text-sm font-medium text-gray-700 mb-1">
                                Produit <span class="text-red-500">*</span>
                            </label>
                            <div class="autocomplete-container">
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                        <span class="material-icons text-gray-400">inventory</span>
                                    </span>
                                    <input type="text" id="product" name="product"
                                        placeholder="Rechercher un produit par nom ou code barres..."
                                        class="pl-10 p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <input type="hidden" id="product_id" name="product_id">
                                </div>
                                <div id="productResults" class="autocomplete-results hidden"></div>
                            </div>
                            <div id="productDetails" class="mt-2 hidden bg-gray-50 p-3 rounded-md">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium product-name"></div>
                                        <div class="text-xs text-gray-500 product-barcode"></div>
                                    </div>
                                    <div class="text-sm font-medium text-blue-700">
                                        <span class="product-stock"></span> en stock
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Source du transfert -->
                        <div>
                            <label for="source_project" class="block text-sm font-medium text-gray-700 mb-1">
                                Projet source <span class="text-red-500">*</span>
                                <span class="tooltip">
                                    <span class="material-icons text-gray-400 text-sm align-middle">help_outline</span>
                                    <span class="tooltip-text">Projet d'où provient la quantité réservée</span>
                                </span>
                            </label>
                            <div class="autocomplete-container">
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                        <span class="material-icons text-gray-400">source</span>
                                    </span>
                                    <input type="text" id="source_project" name="source_project"
                                        placeholder="Rechercher un projet source..."
                                        class="pl-10 p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        disabled>
                                    <input type="hidden" id="source_project_id" name="source_project_id">
                                    <input type="hidden" id="source_project_code" name="source_project_code">
                                </div>
                                <div id="sourceProjectResults" class="autocomplete-results hidden"></div>
                            </div>
                            <div id="sourceProjectDetails" class="mt-2 hidden bg-gray-50 p-3 rounded-md">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium source-project-name"></div>
                                        <div class="text-xs text-gray-500 source-project-code"></div>
                                    </div>
                                    <div class="text-sm font-medium text-blue-700">
                                        <span class="source-reserved-quantity"></span> réservé(s)
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quantité à transférer -->
                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">
                                Quantité à transférer <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="material-icons text-gray-400">dialpad</span>
                                </span>
                                <input type="number" id="quantity" name="quantity" min="1" value="1"
                                    class="pl-10 p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    disabled>
                            </div>
                            <div class="mt-1 text-xs text-gray-500" id="quantityHelp">
                                La quantité à transférer ne peut pas dépasser la quantité réservée pour le projet
                                source.
                            </div>
                        </div>

                        <!-- Destination du transfert -->
                        <div>
                            <label for="destination_project" class="block text-sm font-medium text-gray-700 mb-1">
                                Projet destination <span class="text-red-500">*</span>
                                <span class="tooltip">
                                    <span class="material-icons text-gray-400 text-sm align-middle">help_outline</span>
                                    <span class="tooltip-text">Projet qui recevra la quantité transférée</span>
                                </span>
                            </label>
                            <div class="autocomplete-container">
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                        <span class="material-icons text-gray-400">flag</span>
                                    </span>
                                    <input type="text" id="destination_project" name="destination_project"
                                        placeholder="Rechercher un projet destination..."
                                        class="pl-10 p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        disabled>
                                    <input type="hidden" id="destination_project_id" name="destination_project_id">
                                    <input type="hidden" id="destination_project_code" name="destination_project_code">
                                </div>
                                <div id="destinationProjectResults" class="autocomplete-results hidden"></div>
                            </div>
                            <div id="destinationProjectDetails" class="mt-2 hidden bg-gray-50 p-3 rounded-md">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium destination-project-name"></div>
                                        <div class="text-xs text-gray-500 destination-project-code"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                                Notes / Raison du transfert
                            </label>
                            <div class="relative">
                                <span class="absolute top-3 left-3">
                                    <span class="material-icons text-gray-400">notes</span>
                                </span>
                                <textarea id="notes" name="notes" rows="3"
                                    class="pl-10 p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Ajoutez des notes ou la raison du transfert..."></textarea>
                            </div>
                        </div>

                        <!-- Résumé du transfert (apparaît après validation) -->
                        <div id="transfertSummary" class="transfer-summary hidden">
                            <h3 class="text-lg font-semibold mb-3">Résumé du transfert</h3>
                            <div class="transfer-summary-item">
                                <span class="text-sm text-gray-600">Produit:</span>
                                <span class="text-sm font-medium" id="summary-product"></span>
                            </div>
                            <div class="transfer-summary-item">
                                <span class="text-sm text-gray-600">Quantité:</span>
                                <span class="text-sm font-medium" id="summary-quantity"></span>
                            </div>
                            <div class="transfer-summary-item">
                                <span class="text-sm text-gray-600">Projet source:</span>
                                <span class="text-sm font-medium" id="summary-source"></span>
                            </div>
                            <div class="transfer-summary-item">
                                <span class="text-sm text-gray-600">Projet destination:</span>
                                <span class="text-sm font-medium" id="summary-destination"></span>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" id="cancelBtn"
                                class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-200">
                                Annuler
                            </button>
                            <button type="submit" id="submitBtn"
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200 flex items-center"
                                disabled>
                                <span class="material-icons mr-1">save</span>
                                Créer le transfert
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Références aux éléments DOM
            const productInput = document.getElementById('product');
            const productIdInput = document.getElementById('product_id');
            const productResults = document.getElementById('productResults');
            const productDetails = document.getElementById('productDetails');

            const sourceProjectInput = document.getElementById('source_project');
            const sourceProjectIdInput = document.getElementById('source_project_id');
            const sourceProjectCodeInput = document.getElementById('source_project_code');
            const sourceProjectResults = document.getElementById('sourceProjectResults');
            const sourceProjectDetails = document.getElementById('sourceProjectDetails');

            const quantityInput = document.getElementById('quantity');

            const destinationProjectInput = document.getElementById('destination_project');
            const destinationProjectIdInput = document.getElementById('destination_project_id');
            const destinationProjectCodeInput = document.getElementById('destination_project_code');
            const destinationProjectResults = document.getElementById('destinationProjectResults');
            const destinationProjectDetails = document.getElementById('destinationProjectDetails');

            const notesInput = document.getElementById('notes');
            const transfertSummary = document.getElementById('transfertSummary');

            const submitBtn = document.getElementById('submitBtn');
            const cancelBtn = document.getElementById('cancelBtn');

            // État global pour suivre les sélections
            let selectedProduct = null;
            let selectedSourceProject = null;
            let selectedDestinationProject = null;
            let maxQuantity = 0;

            // Fonction pour rechercher des produits
            function searchProducts(query) {
                if (query.length < 3) {
                    productResults.innerHTML = '';
                    productResults.classList.add('hidden');
                    return;
                }

                fetch(`api_search_products.php?query=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.products.length > 0) {
                            renderProductResults(data.products, query);
                        } else {
                            productResults.innerHTML = '<div class="autocomplete-item">Aucun produit trouvé</div>';
                            productResults.classList.remove('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        productResults.innerHTML = '<div class="autocomplete-item text-red-500">Erreur lors de la recherche</div>';
                        productResults.classList.remove('hidden');
                    });
            }

            // Fonction pour rechercher des projets qui ont des réservations pour un produit
            function searchSourceProjects(productId) {
                if (!productId) return;

                fetch(`api_search_projects_with_reservations.php?product_id=${productId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.projects.length > 0) {
                            renderSourceProjectResults(data.projects);
                            sourceProjectInput.disabled = false;
                        } else {
                            sourceProjectResults.innerHTML = '<div class="autocomplete-item">Aucun projet avec des réservations trouvé</div>';
                            sourceProjectResults.classList.remove('hidden');
                            Swal.fire({
                                icon: 'info',
                                title: 'Aucune réservation trouvée',
                                text: 'Il n\'y a aucune réservation pour ce produit dans aucun projet.',
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        sourceProjectResults.innerHTML = '<div class="autocomplete-item text-red-500">Erreur lors de la recherche</div>';
                        sourceProjectResults.classList.remove('hidden');
                    });
            }

            // Fonction pour rechercher tous les projets actifs (pour la destination)
            function searchDestinationProjects(query) {
                if (query.length < 2) {
                    destinationProjectResults.innerHTML = '';
                    destinationProjectResults.classList.add('hidden');
                    return;
                }

                fetch(`api_search_all_projects.php?query=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.projects.length > 0) {
                            renderDestinationProjectResults(data.projects, query);
                        } else {
                            destinationProjectResults.innerHTML = '<div class="autocomplete-item">Aucun projet trouvé</div>';
                            destinationProjectResults.classList.remove('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        destinationProjectResults.innerHTML = '<div class="autocomplete-item text-red-500">Erreur lors de la recherche</div>';
                        destinationProjectResults.classList.remove('hidden');
                    });
            }

            // Fonctions de rendu pour les résultats
            function renderProductResults(products, query) {
                productResults.innerHTML = '';

                products.forEach(product => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item';

                    // Mettre en évidence la partie qui correspond à la recherche
                    const regex = new RegExp(`(${query})`, 'gi');
                    const highlightedName = product.product_name.replace(regex, '<span class="highlight">$1</span>');
                    const highlightedBarcode = product.barcode ? product.barcode.replace(regex, '<span class="highlight">$1</span>') : '';

                    item.innerHTML = `
                        <div class="font-medium">${highlightedName}</div>
                        ${product.barcode ? `<div class="text-xs text-gray-500">${highlightedBarcode}</div>` : ''}
                        <div class="text-xs text-blue-600">Stock: ${product.quantity}</div>
                    `;

                    item.addEventListener('click', () => {
                        selectedProduct = product;
                        productInput.value = product.product_name;
                        productIdInput.value = product.id;

                        // Afficher les détails du produit
                        document.querySelector('.product-name').textContent = product.product_name;
                        document.querySelector('.product-barcode').textContent = `Code: ${product.barcode || 'N/A'}`;
                        document.querySelector('.product-stock').textContent = product.quantity;
                        productDetails.classList.remove('hidden');

                        // Ajouter un badge "Reçu" pour indiquer que le produit a été réceptionné
                        const statusBadge = document.createElement('span');
                        statusBadge.className = 'ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800';
                        statusBadge.innerHTML = '<span class="material-icons text-xs mr-1">verified</span>Reçu';
                        document.querySelector('.product-name').appendChild(statusBadge);

                        // Cacher les résultats
                        productResults.classList.add('hidden');

                        // Rechercher les projets qui ont des réservations pour ce produit
                        searchSourceProjects(product.id);

                        // Mise à jour de l'UI
                        updateUIState();
                    });

                    productResults.appendChild(item);
                });

                productResults.classList.remove('hidden');
            }

            function renderSourceProjectResults(projects) {
                sourceProjectResults.innerHTML = '';

                projects.forEach(project => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item';

                    item.innerHTML = `
                        <div class="font-medium">${project.nom_client}</div>
                        <div class="text-xs text-gray-500">Code: ${project.code_projet}</div>
                        <div class="text-xs text-blue-600">Quantité réservée: ${project.reserved_quantity}</div>
                    `;

                    item.addEventListener('click', () => {
                        selectedSourceProject = project;
                        sourceProjectInput.value = project.nom_client;
                        sourceProjectIdInput.value = project.id;
                        sourceProjectCodeInput.value = project.code_projet;

                        // Afficher les détails du projet source
                        document.querySelector('.source-project-name').textContent = project.nom_client;
                        document.querySelector('.source-project-code').textContent = `Code: ${project.code_projet}`;
                        document.querySelector('.source-reserved-quantity').textContent = project.reserved_quantity;
                        sourceProjectDetails.classList.remove('hidden');

                        // Cacher les résultats
                        sourceProjectResults.classList.add('hidden');

                        // Activer et définir les limites de la quantité
                        maxQuantity = parseInt(project.reserved_quantity);
                        quantityInput.max = maxQuantity;
                        quantityInput.value = 1;
                        quantityInput.disabled = false;
                        document.getElementById('quantityHelp').textContent = `La quantité maximum disponible pour transfert est de ${maxQuantity}.`;

                        // Activer la sélection du projet de destination
                        destinationProjectInput.disabled = false;

                        // Mise à jour de l'UI
                        updateUIState();
                    });

                    sourceProjectResults.appendChild(item);
                });

                sourceProjectResults.classList.remove('hidden');
            }

            function renderDestinationProjectResults(projects, query) {
                destinationProjectResults.innerHTML = '';

                // Filtrer pour ne pas afficher le projet source comme destination
                const filteredProjects = projects.filter(project =>
                    !selectedSourceProject || project.id !== selectedSourceProject.id
                );

                if (filteredProjects.length === 0) {
                    destinationProjectResults.innerHTML = '<div class="autocomplete-item">Aucun autre projet disponible</div>';
                    destinationProjectResults.classList.remove('hidden');
                    return;
                }

                filteredProjects.forEach(project => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item';

                    // Mettre en évidence la partie qui correspond à la recherche
                    const regex = new RegExp(`(${query})`, 'gi');
                    const highlightedName = project.nom_client.replace(regex, '<span class="highlight">$1</span>');
                    const highlightedCode = project.code_projet.replace(regex, '<span class="highlight">$1</span>');

                    item.innerHTML = `
                        <div class="font-medium">${highlightedName}</div>
                        <div class="text-xs text-gray-500">Code: ${highlightedCode}</div>
                    `;

                    item.addEventListener('click', () => {
                        selectedDestinationProject = project;
                        destinationProjectInput.value = project.nom_client;
                        destinationProjectIdInput.value = project.id;
                        destinationProjectCodeInput.value = project.code_projet;

                        // Afficher les détails du projet destination
                        document.querySelector('.destination-project-name').textContent = project.nom_client;
                        document.querySelector('.destination-project-code').textContent = `Code: ${project.code_projet}`;
                        destinationProjectDetails.classList.remove('hidden');

                        // Cacher les résultats
                        destinationProjectResults.classList.add('hidden');

                        // Mise à jour de l'UI
                        updateUIState();
                    });

                    destinationProjectResults.appendChild(item);
                });

                destinationProjectResults.classList.remove('hidden');
            }

            // Fonction pour mettre à jour l'état de l'interface utilisateur
            function updateUIState() {
                // Activer le bouton de soumission si toutes les conditions sont remplies
                if (selectedProduct && selectedSourceProject && selectedDestinationProject &&
                    parseInt(quantityInput.value) > 0 && parseInt(quantityInput.value) <= maxQuantity) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }

            // Gestionnaires d'événements pour les champs de recherche
            productInput.addEventListener('input', debounce(function () {
                searchProducts(this.value);
            }, 300));

            sourceProjectInput.addEventListener('input', function () {
                // Si le champ est vide, cacher les détails
                if (!this.value) {
                    sourceProjectDetails.classList.add('hidden');
                    selectedSourceProject = null;
                    updateUIState();
                }
            });

            sourceProjectInput.addEventListener('focus', function () {
                if (selectedProduct && this.value.length < 2) {
                    // Afficher tous les projets avec réservations pour ce produit
                    searchSourceProjects(selectedProduct.id);
                }
            });

            destinationProjectInput.addEventListener('input', debounce(function () {
                searchDestinationProjects(this.value);

                // Si le champ est vide, cacher les détails
                if (!this.value) {
                    destinationProjectDetails.classList.add('hidden');
                    selectedDestinationProject = null;
                    updateUIState();
                }
            }, 300));

            // Gestionnaire d'événement pour la quantité
            quantityInput.addEventListener('input', function () {
                const value = parseInt(this.value);

                if (isNaN(value) || value < 1) {
                    this.value = 1;
                } else if (value > maxQuantity) {
                    this.value = maxQuantity;

                    // Animation de shake et message d'erreur
                    this.classList.add('shake');
                    setTimeout(() => this.classList.remove('shake'), 600);

                    Swal.fire({
                        icon: 'warning',
                        title: 'Quantité limitée',
                        text: `La quantité maximum disponible est de ${maxQuantity}.`,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }

                updateUIState();
            });

            // Gestionnaire d'événement pour le formulaire
            document.getElementById('transfertForm').addEventListener('submit', function (e) {
                e.preventDefault();

                // Valider une dernière fois
                if (!selectedProduct || !selectedSourceProject || !selectedDestinationProject ||
                    parseInt(quantityInput.value) < 1 || parseInt(quantityInput.value) > maxQuantity) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Formulaire incomplet',
                        text: 'Veuillez remplir tous les champs obligatoires correctement.'
                    });
                    return;
                }

                // Afficher le résumé
                document.getElementById('summary-product').textContent = selectedProduct.product_name;
                document.getElementById('summary-quantity').textContent = quantityInput.value;
                document.getElementById('summary-source').textContent = `${selectedSourceProject.nom_client} (${selectedSourceProject.code_projet})`;
                document.getElementById('summary-destination').textContent = `${selectedDestinationProject.nom_client} (${selectedDestinationProject.code_projet})`;
                transfertSummary.classList.remove('hidden');

                // Demander confirmation
                Swal.fire({
                    title: 'Confirmer le transfert',
                    text: 'Êtes-vous sûr de vouloir créer ce transfert?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, créer',
                    cancelButtonText: 'Annuler',
                    confirmButtonColor: '#3085d6',
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Soumettre les données
                        const formData = {
                            product_id: productIdInput.value,
                            source_project_id: sourceProjectIdInput.value,
                            source_project_code: sourceProjectCodeInput.value,
                            destination_project_id: destinationProjectIdInput.value,
                            destination_project_code: destinationProjectCodeInput.value,
                            quantity: quantityInput.value,
                            notes: notesInput.value
                        };

                        fetch('api_create_transfert.php', {
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
                                        title: 'Transfert créé',
                                        text: data.message,
                                        icon: 'success',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        // Rediriger vers la liste des transferts
                                        window.location.href = 'transfert_manager.php';
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Erreur',
                                        text: data.message,
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Erreur:', error);
                                Swal.fire({
                                    title: 'Erreur',
                                    text: 'Une erreur est survenue lors de la création du transfert.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            });
                    }
                });
            });

            // Bouton d'annulation
            cancelBtn.addEventListener('click', function () {
                Swal.fire({
                    title: 'Annuler les modifications?',
                    text: 'Toutes les modifications seront perdues.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, annuler',
                    cancelButtonText: 'Non, continuer',
                    confirmButtonColor: '#d33',
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'transfert_manager.php';
                    }
                });
            });

            // Cliquer en dehors des résultats pour les fermer
            document.addEventListener('click', function (e) {
                if (!productInput.contains(e.target) && !productResults.contains(e.target)) {
                    productResults.classList.add('hidden');
                }

                if (!sourceProjectInput.contains(e.target) && !sourceProjectResults.contains(e.target)) {
                    sourceProjectResults.classList.add('hidden');
                }

                if (!destinationProjectInput.contains(e.target) && !destinationProjectResults.contains(e.target)) {
                    destinationProjectResults.classList.add('hidden');
                }
            });

            // Gestion des touches clavier pour la navigation
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    productResults.classList.add('hidden');
                    sourceProjectResults.classList.add('hidden');
                    destinationProjectResults.classList.add('hidden');
                }
            });

            // Fonction utilitaire de debounce
            function debounce(func, wait) {
                let timeout;
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }
        });
    </script>
</body>

</html>