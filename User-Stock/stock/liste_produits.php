<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des produits - DYM STOCK</title>

    <!-- Feuilles de style CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>

    <style>
        /* === STYLES DES MODALES === */
        /* Animation de la modal d'édition */
        #editModal>div:nth-child(2) {
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.2s ease-in-out;
        }

        #editModal>div:nth-child(2).scale-100 {
            transform: scale(1);
        }

        #editModal>div:nth-child(2).opacity-100 {
            opacity: 1;
        }

        #modalOverlay {
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
        }

        #modalOverlay.opacity-100 {
            opacity: 1;
        }

        /* Animations pour champ invalide */
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

        .input-error {
            border-color: #ef4444 !important;
            animation: shake 0.6s;
        }

        /* Style d'input focus amélioré */
        #editProductForm input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }

        /* Style pour les étiquettes lors du focus des inputs */
        #editProductForm input:focus+label {
            color: #3b82f6;
        }

        /* Style pour l'indicateur de chargement */
        #editModalLoader {
            transition: opacity 0.2s ease-in-out;
        }

        /* === STYLES POUR L'IMAGE === */
        .image-preview-container {
            width: 120px;
            height: 120px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            background-color: #f9fafb;
            transition: all 0.3s ease;
        }

        .image-preview-container:hover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        .image-preview-container.has-image {
            border-style: solid;
            border-color: #e5e7eb;
        }

        .image-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
        }

        .image-placeholder {
            color: #9ca3af;
            text-align: center;
            font-size: 12px;
        }

        .image-upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 6px;
        }

        .image-preview-container:hover .image-upload-overlay {
            opacity: 1;
        }

        .remove-image-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .remove-image-btn:hover {
            background: #dc2626;
        }

        /* === STYLES DATATABLES === */
        table.dataTable {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        table.dataTable thead th,
        table.dataTable thead td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        table.dataTable tbody td {
            padding: 8px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.75rem;
        }

        table.dataTable tbody tr:nth-child(odd) {
            background-color: #f9fafb;
        }

        table.dataTable tbody tr:nth-child(even) {
            background-color: #ffffff;
        }

        table.dataTable tbody tr:hover {
            background-color: #f1f5f9;
            cursor: pointer;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate {
            color: #4a5568;
            margin-bottom: 1rem;
            font-size: 0.75rem;
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            padding: 0.35rem 0.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.25rem 0.5rem;
            margin-left: 0.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.25rem;
            background-color: #ffffff;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #3b82f6;
            color: white !important;
            border: 1px solid #3b82f6;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #2563eb;
            color: white !important;
            border: 1px solid #2563eb;
        }

        /* Styles des boutons d'exportation */
        div.dt-buttons {
            margin-bottom: 1rem;
        }

        button.dt-button,
        div.dt-button,
        a.dt-button,
        input.dt-button {
            border-radius: 0.375rem;
            background-image: none;
            background-color: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
        }

        button.dt-button:hover,
        div.dt-button:hover,
        a.dt-button:hover,
        input.dt-button:hover {
            background-image: none !important;
            background-color: #e5e7eb !important;
            color: #111827 !important;
        }

        button.dt-button:active,
        button.dt-button.active,
        div.dt-button:active,
        div.dt-button.active,
        a.dt-button:active,
        a.dt-button.active,
        input.dt-button:active,
        input.dt-button.active {
            background-image: none !important;
            background-color: #d1d5db !important;
            box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06) !important;
        }

        /* Styles personnalisés pour les boutons */
        .print-list-btn {
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .print-list-btn:hover {
            background-color: #4338ca;
        }

        .max-width-produit-nom {
            max-width: 332px !important;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Style pour la colonne image */
        .product-image-cell {
            width: 50px;
            height: 50px;
        }

        .product-image-thumbnail {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
        }

        .no-image-placeholder {
            width: 40px;
            height: 40px;
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 12px;
        }

        /* Ajustements responsive */
        @media (max-width: 768px) {
            header.flex.items-center.justify-between.p-4.bg-white.shadow-sm.backdrop-blur-lg.sticky.top-0 {
                position: relative;
            }
        }

        /* === STYLES POUR LA MODAL DE VISUALISATION D'IMAGE === */
        #imageViewerModal {
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        #imageViewerModal .modal-content {
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .image-viewer-container {
            max-height: 80vh;
            max-width: 90vw;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .image-viewer-img {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 8px;
        }

        .image-viewer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px 12px 0 0;
        }

        .image-viewer-actions {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 1.5rem;
            border-radius: 0 0 12px 12px;
            border-top: 1px solid #e5e7eb;
        }

        .image-viewer-download-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
        }

        .image-viewer-download-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Style pour les images cliquables dans le tableau */
        .product-image-thumbnail {
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 6px;
        }

        .product-image-thumbnail:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10;
            position: relative;
        }

        /* Indicateur de clic sur l'image */
        .image-clickable-indicator {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(59, 130, 246, 0.9);
            color: white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .product-image-cell:hover .image-clickable-indicator {
            opacity: 1;
        }
    </style>
</head>

<body class="bg-gray-100 text-sm">
    <!-- Conteneur de notifications -->
    <div id="notification-container" class="fixed top-4 right-4 z-50"></div>

    <div class="flex h-screen">
        <?php include_once 'sidebar.php'; ?>

        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once 'header.php'; ?>

            <main class="p-4 flex-1">
                <div class="bg-white p-4 rounded-lg shadow">
                    <!-- Barre d'outils et filtres -->
                    <div class="flex flex-wrap gap-2 items-center mb-4">
                        <!-- Barre de recherche avec aide -->
                        <div class="relative w-64">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                <span class="material-icons text-gray-400 text-base">search</span>
                            </span>
                            <input type="text" id="customSearchInput" placeholder="Rechercher un produit"
                                class="w-full pl-10 p-1.5 border rounded text-xs"
                                title="Astuce: Utilisez # suivi d'un code-barres pour une recherche exacte">
                            <button id="searchHelpBtn"
                                class="absolute top-1/2 right-2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <span class="material-icons text-sm">help_outline</span>
                            </button>
                        </div>

                        <!-- Filtre par catégorie -->
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                <span class="material-icons text-gray-400 text-base">filter_list</span>
                            </span>
                            <select id="categoryFilter" class="pl-10 p-1.5 border rounded appearance-none pr-8 text-xs"
                                style="width: 180px;">
                                <option value="">Toutes les catégories</option>
                                <!-- Les catégories seront chargées dynamiquement -->
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <span class="material-icons text-gray-400 text-base">expand_more</span>
                            </span>
                        </div>

                        <!-- Filtre par statut de stock -->
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                <span class="material-icons text-gray-400 text-base">inventory_2</span>
                            </span>
                            <select id="stockStatusFilter"
                                class="pl-10 p-1.5 border rounded appearance-none pr-8 text-xs" style="width: 160px;">
                                <option value="">Tous les statuts</option>
                                <option value="inStock">En stock</option>
                                <option value="lowStock">Stock faible</option>
                                <option value="outOfStock">Rupture</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <span class="material-icons text-gray-400 text-base">expand_more</span>
                            </span>
                        </div>

                        <!-- Boutons d'action -->
                        <button id="resetFiltersBtn"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-3 py-1.5 rounded-md border border-gray-300 shadow-sm transition duration-300 ease-in-out flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-opacity-50 text-xs">
                            <span class="material-icons mr-1 text-base text-gray-600">refresh</span>
                            Réinitialiser
                        </button>

                        <button id="printSelectedBtn"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-3 py-1.5 rounded-md border border-gray-300 shadow-sm transition duration-300 ease-in-out flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-opacity-50 text-xs">
                            <span class="material-icons mr-1 text-base text-gray-600">print</span>
                            Imprimer codes-barres
                        </button>

                        <button id="printListBtn" class="print-list-btn">
                            <span class="material-icons text-base">description</span>
                            Imprimer liste
                        </button>
                    </div>

                    <!-- Tableau des produits avec DataTables -->
                    <div class="overflow-x-auto">
                        <table id="productsTable" class="w-full">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAllCheckbox"
                                            class="form-checkbox h-4 w-4 text-blue-600">
                                    </th>
                                    <th>Image</th>
                                    <th>Code-barres</th>
                                    <th>Nom du produit</th>
                                    <th>Catégorie</th>
                                    <th>Unité</th>
                                    <th>Quantité</th>
                                    <th>Quantité reservée</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="productTableBody">
                                <!-- Les produits seront insérés ici dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- MODALES -->

    <!-- Modal: Aide à la recherche -->
    <div id="searchHelpModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" id="searchHelpOverlay"></div>
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 md:mx-0 transform transition-all relative p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Astuces de recherche</h3>
                <button type="button" id="closeSearchHelpBtn"
                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                    <span class="material-icons">close</span>
                </button>
            </div>

            <div class="space-y-3 text-sm">
                <p class="text-gray-600">Utilisez ces astuces pour améliorer vos recherches :</p>

                <div class="py-2">
                    <h4 class="font-medium text-gray-800">Recherche standard</h4>
                    <p class="text-gray-600">Tapez simplement un mot ou une phrase pour rechercher dans
                        le nom, le code-barres et d'autres champs.</p>
                    <div class="mt-1 bg-gray-50 p-2 rounded border border-gray-200">
                        <code class="text-blue-600">stylo rouge</code> → Trouve les produits contenant à
                        la fois "stylo" ET "rouge".
                    </div>
                </div>

                <div class="py-2">
                    <h4 class="font-medium text-gray-800">Recherche par code-barres exact</h4>
                    <p class="text-gray-600">Utilisez le symbole # suivi du code-barres.</p>
                    <div class="mt-1 bg-gray-50 p-2 rounded border border-gray-200">
                        <code class="text-blue-600">#3760052141874</code> → Trouve le produit avec
                        exactement ce code-barres.
                    </div>
                </div>

                <div class="py-2">
                    <h4 class="font-medium text-gray-800">Recherche par ID</h4>
                    <p class="text-gray-600">Saisissez simplement le numéro d'ID du produit.</p>
                    <div class="mt-1 bg-gray-50 p-2 rounded border border-gray-200">
                        <code class="text-blue-600">42</code> → Trouve le produit avec l'ID 42.
                    </div>
                </div>
            </div>

            <div class="mt-6 text-right">
                <button id="closeSearchHelpConfirmBtn"
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                    Compris
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Édition du produit avec gestion d'image -->
    <div id="editModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" id="modalOverlay"></div>
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 md:mx-0 transform transition-all relative">
            <!-- En-tête de la modal -->
            <div class="flex items-center justify-between p-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800">Modifier le produit</h2>
                <button type="button" id="closeModalBtn" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                    <span class="material-icons">close</span>
                </button>
            </div>

            <!-- Contenu de la modal -->
            <div class="p-6">
                <form id="editProductForm" enctype="multipart/form-data">
                    <input type="hidden" id="editProductId">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Section Image -->
                        <div class="md:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Image du produit</label>
                            <div class="space-y-3">
                                <!-- Conteneur de prévisualisation de l'image -->
                                <div class="image-preview-container" id="imagePreviewContainer">
                                    <div class="image-placeholder" id="imagePlaceholder">
                                        <span class="material-icons text-2xl mb-1">add_photo_alternate</span>
                                        <div>Cliquer pour ajouter une image</div>
                                    </div>
                                    <img id="imagePreview" class="image-preview hidden" alt="Aperçu">
                                    <div class="image-upload-overlay">
                                        <span class="material-icons text-white text-2xl">camera_alt</span>
                                    </div>
                                    <button type="button" class="remove-image-btn hidden" id="removeImageBtn">
                                        <span class="material-icons text-sm">close</span>
                                    </button>
                                </div>

                                <!-- Input file caché -->
                                <input type="file" id="productImageInput" name="product_image" accept="image/*" class="hidden">

                                <!-- Boutons d'action pour l'image -->
                                <div class="flex space-x-2">
                                    <button type="button" id="selectImageBtn"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <span class="material-icons text-sm mr-1">folder_open</span>
                                        Parcourir
                                    </button>
                                    <button type="button" id="clearImageBtn"
                                        class="px-3 py-2 border border-red-300 rounded-md text-sm text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">
                                        <span class="material-icons text-sm">delete</span>
                                    </button>
                                </div>

                                <!-- Informations sur l'image -->
                                <div class="text-xs text-gray-500">
                                    <p>Formats acceptés: JPG, PNG, GIF</p>
                                    <p>Taille max: 5 MB</p>
                                </div>
                            </div>
                        </div>

                        <!-- Section Informations du produit -->
                        <div class="md:col-span-2 space-y-4">
                            <!-- Nom du produit -->
                            <div>
                                <label for="editProductName" class="block text-sm font-medium text-gray-700 mb-1">Nom du produit</label>
                                <input type="text" id="editProductName"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
                                    required>
                            </div>

                            <!-- Catégorie -->
                            <div>
                                <label for="editProductCategory" class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
                                <div class="relative">
                                    <select id="editProductCategory"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 appearance-none bg-white"
                                        required>
                                        <option value="">Sélectionner une catégorie</option>
                                        <!-- Les catégories seront chargées dynamiquement -->
                                    </select>
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <span class="material-icons text-gray-400 text-base">expand_more</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Unité -->
                            <div>
                                <label for="editProductUnit" class="block text-sm font-medium text-gray-700 mb-1">Unité</label>
                                <input type="text" id="editProductUnit"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
                                    required>
                            </div>

                            <!-- Quantité -->
                            <div>
                                <label for="editProductQuantity" class="block text-sm font-medium text-gray-700 mb-1">Quantité en stock</label>
                                <input type="number" id="editProductQuantity" min="0" step="0.01"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
                                    placeholder="Ex: 10.5"
                                    required>
                                <small class="text-gray-500 text-xs mt-1">Les nombres décimaux sont autorisés (ex: 10.5)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="mt-6 flex items-center justify-end space-x-3">
                        <button type="button" id="cancelEditBtn"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <span class="material-icons mr-2 text-sm">close</span>
                            Annuler
                        </button>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <span class="material-icons mr-2 text-sm">save</span>
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>

            <!-- Indicateur de chargement -->
            <div id="editModalLoader"
                class="hidden absolute inset-0 bg-white bg-opacity-80 flex items-center justify-center rounded-lg">
                <div class="flex flex-col items-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                    <p class="mt-2 text-sm text-gray-600">Mise à jour en cours...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Détails des réservations -->
    <div id="reservationsModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" id="reservationsModalOverlay"></div>
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4 md:mx-0 transform transition-all opacity-0 scale-95"
            id="reservationsModalContent">
            <div class="flex items-center justify-between p-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800" id="reservationsModalTitle">Réservations</h2>
                <button type="button" id="closeReservationsModal"
                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div class="p-4">
                <div id="reservationsContent" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Projet</th>
                                <th scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Code Projet</th>
                                <th scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Qté Réservée</th>
                                <th scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date Réservation</th>
                                <th scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Statut</th>
                            </tr>
                        </thead>
                        <tbody id="reservationsTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Les réservations seront insérées ici dynamiquement -->
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-center">
                                    <div class="flex justify-center items-center">
                                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"></div>
                                        <span class="ml-2">Chargement des réservations...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex justify-end">
                    <a id="viewAllReservationsLink" href="#"
                        class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                        <span class="material-icons text-base mr-1">visibility</span>
                        Voir toutes les réservations
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Visualisation d'image en grand -->
    <div id="imageViewerModal" class="fixed inset-0 flex items-center justify-center z-50 hidden bg-black bg-opacity-75">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-6xl mx-4 overflow-hidden">
            <!-- En-tête de la modal -->
            <div class="image-viewer-header flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold" id="imageViewerTitle">Aperçu de l'image</h3>
                    <p class="text-sm opacity-90" id="imageViewerSubtitle">Cliquez à l'extérieur pour fermer</p>
                </div>
                <button type="button" id="closeImageViewerBtn" class="text-white hover:text-gray-200 focus:outline-none transition-colors">
                    <span class="material-icons text-2xl">close</span>
                </button>
            </div>

            <!-- Contenu de l'image -->
            <div class="image-viewer-container bg-gray-50 flex items-center justify-center p-4">
                <img id="imageViewerImg" class="image-viewer-img" alt="Aperçu du produit" />
            </div>

            <!-- Actions -->
            <div class="image-viewer-actions flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <span class="material-icons text-sm mr-1 align-middle">info</span>
                    Utilisez la molette pour zoomer ou les gestes tactiles
                </div>
                <button id="downloadImageBtn" class="image-viewer-download-btn">
                    <span class="material-icons text-sm">download</span>
                    Télécharger
                </button>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <!-- DataTables JS et plugins d'exportation -->
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

    <script>
        $(document).ready(function() {
            // Variables globales
            let dataTable;
            let products = [];
            let currentImageFile = null; // Pour stocker le fichier image sélectionné

            /**
             * FONCTIONS DE GESTION DES DONNÉES
             */

            // Fonction pour charger les catégories depuis l'API
            function loadCategories() {
                $.ajax({
                    url: 'api_getCategories.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let categorySelect = $('#categoryFilter');
                            categorySelect.empty().append($('<option>', {
                                value: '',
                                text: 'Toutes les catégories'
                            }));
                            response.categories.forEach(function(category) {
                                categorySelect.append($('<option>', {
                                    value: category.id,
                                    text: category.libelle
                                }));
                            });
                        } else {
                            console.error('Erreur lors du chargement des catégories:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur AJAX:', error);
                    }
                });
            }

            // Fonction pour charger les catégories dans la modal d'édition
            function loadCategoriesForEdit() {
                return $.ajax({
                    url: 'api_getCategories.php',
                    type: 'GET',
                    dataType: 'json'
                });
            }

            // Fonction pour charger les produits depuis l'API
            function loadProducts() {
                // Afficher l'indicateur de chargement
                $('#productTableBody').html('<tr><td colspan="10" class="px-4 py-4 text-center"><div class="flex justify-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div><span class="ml-2">Chargement des produits...</span></div></td></tr>');

                const search = $('#customSearchInput').val();
                const category = $('#categoryFilter').val();
                const stockStatus = $('#stockStatusFilter').val();

                $.ajax({
                    url: 'api_getProducts.php',
                    type: 'GET',
                    data: {
                        limit: 5000, // Limite élevée pour obtenir tous les produits
                        page: 1,
                        search: search,
                        category: category,
                        stockStatus: stockStatus,
                        sortBy: 'id',
                        sortOrder: 'DESC'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            products = response.products;
                            renderDataTable();
                        } else {
                            console.error('Erreur lors de la récupération des produits:', response.message);
                            showNotification('Erreur lors de la récupération des produits: ' + response.message, 'error');
                            $('#productTableBody').html('<tr><td colspan="10" class="px-4 py-4 text-center text-red-500">Une erreur est survenue lors du chargement des produits</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur AJAX:', error);
                        $('#productTableBody').html('<tr><td colspan="10" class="px-4 py-4 text-center text-red-500">Une erreur est survenue lors du chargement des produits</td></tr>');
                        showNotification('Une erreur est survenue lors de la récupération des produits', 'error');
                    }
                });
            }

            // Fonction pour initialiser la DataTable avec les données des produits
            function renderDataTable() {
                // Détruire la DataTable existante si elle existe
                if (dataTable) {
                    dataTable.destroy();
                }

                // Préparer les données au format attendu par DataTables
                const tableData = products.map(product => {
                    // Déterminer le statut du produit
                    let status, statusColor, statusIcon;
                    if (product.quantity <= 0) {
                        status = "Rupture";
                        statusColor = "bg-red-100 text-red-800";
                        statusIcon = "error_outline";
                    } else if (product.quantity <= 10) {
                        status = "Faible";
                        statusColor = "bg-yellow-100 text-yellow-800";
                        statusIcon = "warning";
                    } else {
                        status = "En stock";
                        statusColor = "bg-green-100 text-green-800";
                        statusIcon = "check_circle";
                    }

                    // Créer l'affichage de l'image
                    let imageHTML;
                    console.log('image produit : '+product.product_image);
                    if (product.product_image && product.product_image.trim() !== '') {
                        imageHTML = `<div class="relative product-image-cell">
                                    <img src="${product.product_image}" 
                                         class="product-image-thumbnail view-image-btn" 
                                         alt="${product.product_name}" 
                                         data-product-name="${product.product_name}"
                                         data-product-code="${product.barcode}"
                                         data-image-src="${product.product_image}"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="image-clickable-indicator">
                                        <span class="material-icons" style="font-size: 10px;">zoom_in</span>
                                    </div>
                                    <div class="no-image-placeholder" style="display: none;">
                                        <span class="material-icons text-xs">image_not_supported</span>
                                    </div>
                                    </div>`;
                    } else {
                        imageHTML = `<div class="no-image-placeholder">
                                      <span class="material-icons text-xs">image</span>
                                    </div>`;
                    }

                    // Créer les boutons d'actions
                    const actionsHTML = `
                        <button class="text-blue-600 hover:text-blue-900 mr-2 edit-product" data-id="${product.id}" data-category-id="${product.category}">
                            <span class="material-icons text-base">edit</span>
                        </button>
                        <button class="text-red-600 hover:text-red-900 mr-2 delete-product" data-id="${product.id}">
                            <span class="material-icons text-base">delete</span>
                        </button>
                        <button class="text-green-600 hover:text-green-900 mr-2 print-barcode" data-barcode="${product.barcode}">
                            <span class="material-icons text-base">print</span>
                        </button>
                        ${product.quantity_reserved > 0 ? `
                        <button class="text-purple-600 hover:text-purple-900 view-reservations" title="Voir les réservations" data-id="${product.id}" data-name="${product.product_name}">
                            <span class="material-icons text-base">bookmark</span>
                        </button>` : ''}
                    `;

                    // Créer le badge de statut
                    const statusHTML = `
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusColor}">
                            <span class="material-icons text-xs mr-1">${statusIcon}</span>
                            ${status}
                        </span>
                    `;

                    // Retourner l'objet formaté pour DataTables
                    return [
                        `<input type="checkbox" class="product-checkbox form-checkbox h-4 w-4 text-blue-600" data-barcode="${product.barcode}">`,
                        imageHTML,
                        product.barcode,
                        product.product_name,
                        product.category_libelle,
                        product.unit,
                        product.quantity,
                        product.quantity_reserved !== null ? product.quantity_reserved : '0',
                        statusHTML,
                        actionsHTML
                    ];
                });

                // Initialiser DataTables avec les boutons d'exportation
                dataTable = $('#productsTable').DataTable({
                    data: tableData,
                    responsive: true,
                    columns: [{
                            title: '',
                            orderable: false
                        },
                        {
                            title: 'Image',
                            orderable: false,
                            className: 'product-image-cell'
                        },
                        {
                            title: 'Code-barres'
                        },
                        {
                            title: 'Nom du produit',
                            width: "auto",
                            className: 'max-width-produit-nom'
                        },
                        {
                            title: 'Catégorie'
                        },
                        {
                            title: 'Unité'
                        },
                        {
                            title: 'Quantité'
                        },
                        {
                            title: 'Quantité reservée'
                        },
                        {
                            title: 'Statut',
                            orderable: false
                        },
                        {
                            title: 'Actions',
                            orderable: false
                        }
                    ],
                    order: [
                        [6, 'desc']
                    ], // Trier par quantité par défaut
                    pageLength: 25,
                    lengthMenu: [
                        [10, 25, 50, 100, 500, -1],
                        [10, 25, 50, 100, 500, 'Tous']
                    ],
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
                    },
                    dom: 'Bfrtip', // Ajouter les boutons au DOM (B: boutons, f: filtre, r: processing, t: table, i: info, p: pagination)
                    buttons: [{
                            extend: 'excel',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 5px;">file_download</span>Excel',
                            title: 'Liste des produits DYM ' + new Date().toLocaleDateString('fr-FR'),
                            exportOptions: {
                                columns: [2, 3, 4, 5, 6, 7, 8] // Exporter toutes les colonnes sauf checkbox, image et actions
                            },
                            customize: function(xlsx) {
                                // Personnalisation supplémentaire du fichier Excel si nécessaire
                            }
                        },
                        {
                            extend: 'csv',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 5px;">description</span>CSV',
                            title: 'Liste des produits DYM ' + new Date().toLocaleDateString('fr-FR'),
                            exportOptions: {
                                columns: [2, 3, 4, 5, 6, 7] // Exclure les colonnes d'image, statut et actions
                            }
                        },
                        {
                            extend: 'pdf',
                            text: '<span class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 5px;">picture_as_pdf</span>PDF',
                            title: 'Liste des produits DYM',
                            exportOptions: {
                                columns: [2, 3, 4, 5, 6, 7] // Exclure les colonnes d'image, statut et actions
                            },
                            customize: function(doc) {
                                // Ajouter la date au document
                                doc.content.splice(1, 0, {
                                    text: 'Date: ' + new Date().toLocaleDateString('fr-FR'),
                                    style: 'subheader',
                                    margin: [0, 10, 0, 10]
                                });

                                // Personnaliser les styles
                                doc.styles.tableHeader.fontSize = 10;
                                doc.styles.tableHeader.fillColor = '#f3f4f6';
                                doc.styles.tableHeader.color = '#1f2937';

                                // Définir l'orientation du document
                                doc.pageOrientation = 'landscape';

                                // Ajouter un pied de page avec numéro de page
                                doc.footer = function(currentPage, pageCount) {
                                    return {
                                        text: 'Page ' + currentPage.toString() + ' sur ' + pageCount.toString(),
                                        alignment: 'center'
                                    };
                                };
                            }
                        }
                    ],
                    drawCallback: function() {
                        // Réinitialiser le sélecteur "Tout sélectionner" après le redraw
                        $('#selectAllCheckbox').prop('checked', false);

                        // Réappliquer les handlers d'événements pour les éléments dans le tableau
                        attachEventHandlers();
                    }
                });

                // Cacher le champ de recherche natif de DataTables
                $('.dataTables_filter').hide();
            }

            /**
             * FONCTIONS DE GESTION DES IMAGES
             */

            // Fonction pour initialiser les événements liés à l'image
            function initializeImageHandlers() {
                // Clic sur le conteneur de prévisualisation ou bouton parcourir
                $('#imagePreviewContainer, #selectImageBtn').on('click', function() {
                    $('#productImageInput').click();
                });

                // Changement de fichier
                $('#productImageInput').on('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // Validation du fichier
                        if (!validateImageFile(file)) {
                            return;
                        }

                        currentImageFile = file;
                        previewImage(file);
                    }
                });

                // Bouton supprimer image
                $('#clearImageBtn, #removeImageBtn').on('click', function(e) {
                    e.stopPropagation();
                    clearImage();
                });
            }

            // Fonction pour valider le fichier image
            function validateImageFile(file) {
                // Vérifier le type de fichier
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showNotification('Format de fichier non supporté. Utilisez JPG, PNG ou GIF.', 'error');
                    return false;
                }

                // Vérifier la taille du fichier (5MB max)
                const maxSize = 5 * 1024 * 1024; // 5MB en bytes
                if (file.size > maxSize) {
                    showNotification('Le fichier est trop volumineux. Taille maximale : 5 MB.', 'error');
                    return false;
                }

                return true;
            }

            // Fonction pour prévisualiser l'image
            function previewImage(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imagePreview = $('#imagePreview');
                    const placeholder = $('#imagePlaceholder');
                    const container = $('#imagePreviewContainer');
                    const removeBtn = $('#removeImageBtn');

                    // Afficher l'image
                    imagePreview.attr('src', e.target.result).removeClass('hidden');
                    placeholder.addClass('hidden');
                    container.addClass('has-image');
                    removeBtn.removeClass('hidden');
                };
                reader.readAsDataURL(file);
            }

            // Fonction pour effacer l'image
            function clearImage() {
                const imagePreview = $('#imagePreview');
                const placeholder = $('#imagePlaceholder');
                const container = $('#imagePreviewContainer');
                const removeBtn = $('#removeImageBtn');
                const fileInput = $('#productImageInput');

                // Réinitialiser l'affichage
                imagePreview.attr('src', '').addClass('hidden');
                placeholder.removeClass('hidden');
                container.removeClass('has-image');
                removeBtn.addClass('hidden');
                fileInput.val('');
                currentImageFile = null;
            }

            // Fonction pour charger l'image existante du produit
            function loadExistingImage(imagePath) {
                if (imagePath && imagePath.trim() !== '') {
                    const imagePreview = $('#imagePreview');
                    const placeholder = $('#imagePlaceholder');
                    const container = $('#imagePreviewContainer');
                    const removeBtn = $('#removeImageBtn');

                    // Afficher l'image existante
                    imagePreview.attr('src', imagePath).removeClass('hidden');
                    placeholder.addClass('hidden');
                    container.addClass('has-image');
                    removeBtn.removeClass('hidden');
                } else {
                    clearImage();
                }
            }

            /**
             * FONCTIONS D'IMPRESSION
             */

            // Fonction pour imprimer la liste complète des produits
            function printProductsList() {
                // Créer une version imprimable des données
                let printContent = `
                    <div style="padding: 20px; font-family: Arial, sans-serif;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h1 style="font-size: 22px; margin-bottom: 5px;">Liste des produits en stock</h1>
                            <p style="font-size: 14px; color: #666;">Date: ${new Date().toLocaleDateString('fr-FR')}</p>
                        </div>
                        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                            <thead>
                                <tr style="background-color: #f3f4f6;">
                                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">Code-barres</th>
                                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">Nom du produit</th>
                                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">Catégorie</th>
                                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">Unité</th>
                                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">Quantité</th>
                                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">Réservé</th>
                                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                // Obtenir les données visibles (filtrées) du tableau
                const visibleData = dataTable.rows({
                    search: 'applied'
                }).data();
                let totalQuantity = 0;
                let totalReserved = 0;

                // Pour chaque ligne visible, ajouter au contenu d'impression
                for (let i = 0; i < visibleData.length; i++) {
                    const rowData = visibleData[i];
                    const quantity = parseInt(rowData[6]);
                    const reserved = parseInt(rowData[7]);

                    // Accumuler les totaux
                    totalQuantity += isNaN(quantity) ? 0 : quantity;
                    totalReserved += isNaN(reserved) ? 0 : reserved;

                    // Déterminer la classe de statut
                    let statusClass = '';
                    let statusText = '';

                    if (rowData[8].includes('bg-red-100')) {
                        statusClass = 'background-color: #fee2e2; color: #b91c1c;';
                        statusText = 'Rupture';
                    } else if (rowData[8].includes('bg-yellow-100')) {
                        statusClass = 'background-color: #fef3c7; color: #92400e;';
                        statusText = 'Faible';
                    } else {
                        statusClass = 'background-color: #d1fae5; color: #065f46;';
                        statusText = 'En stock';
                    }

                    printContent += `
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px; border: 1px solid #e5e7eb;">${rowData[2]}</td>
                            <td style="padding: 8px; border: 1px solid #e5e7eb;">${rowData[3]}</td>
                            <td style="padding: 8px; border: 1px solid #e5e7eb;">${rowData[4]}</td>
                            <td style="padding: 8px; border: 1px solid #e5e7eb;">${rowData[5]}</td>
                            <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">${rowData[6]}</td>
                            <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">${rowData[7]}</td>
                            <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center; ${statusClass}">${statusText}</td>
                        </tr>
                    `;
                }

                // Ajouter une ligne de totaux
                printContent += `
                            <tr style="font-weight: bold; background-color: #f3f4f6;">
                                <td colspan="4" style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">TOTAL</td>
                                <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">${totalQuantity}</td>
                                <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">${totalReserved}</td>
                                <td style="padding: 8px; border: 1px solid #e5e7eb;"></td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px; font-size: 11px; color: #6b7280; text-align: center;">
                        <p>Document généré le ${new Date().toLocaleString('fr-FR')}</p>
                    </div>
                </div>
                `;

                // Créer un iframe pour l'impression
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                document.body.appendChild(iframe);

                // Écrire le contenu HTML dans l'iframe
                iframe.contentDocument.write(`
                    <html>
                    <head>
                        <title>Liste des produits - DYM STOCK</title>
                        <style>
                            @media print {
                                body {
                                    font-family: Arial, sans-serif;
                                    margin: 0;
                                    padding: 0;
                                }
                                @page {
                                    size: landscape;
                                    margin: 1cm;
                                }
                                table {
                                    width: 100%;
                                    border-collapse: collapse;
                                }
                                th, td {
                                    padding: 8px;
                                    border: 1px solid #e5e7eb;
                                }
                                thead {
                                    display: table-header-group;
                                }
                                tr {
                                    page-break-inside: avoid;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        ${printContent}
                    </body>
                    </html>
                `);

                // Imprimer puis supprimer l'iframe
                setTimeout(() => {
                    iframe.contentWindow.print();
                    document.body.removeChild(iframe);
                }, 500);
            }

            // Fonction pour générer et imprimer un seul code-barres
            function printBarcode(barcode) {
                // Créer un élément temporaire pour le code-barres
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = `<svg id="temp-barcode"></svg>`;
                document.body.appendChild(tempDiv);

                // Générer le code-barres
                JsBarcode("#temp-barcode", barcode, {
                    format: "CODE128",
                    width: 2,
                    height: 100,
                    displayValue: true
                });

                // Imprimer le code-barres
                const printContent = tempDiv.innerHTML;
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                document.body.appendChild(iframe);

                iframe.contentDocument.write(`
                    <html>
                    <head>
                        <title>Imprimer le code-barres</title>
                        <style>
                            .barcode-item {
                                transform: rotate(0deg); /* Rotation neutre */
                            }
                        </style>
                    </head>
                    <body>
                        ${printContent}
                    </body>
                    </html>
                `);

                iframe.contentWindow.print();

                // Supprimer les éléments temporaires après l'impression
                setTimeout(() => {
                    document.body.removeChild(tempDiv);
                    document.body.removeChild(iframe);
                }, 100);
            }

            // Fonction pour imprimer plusieurs codes-barres sélectionnés
            function printSelectedBarcodes(barcodes) {
                // Création d'un conteneur temporaire pour afficher les codes-barres
                const tempDiv = document.createElement('div');
                tempDiv.id = 'barcodesPrintArea';
                tempDiv.className = 'barcode-container';

                // Génération des éléments HTML pour chaque code-barres
                barcodes.forEach((barcode, index) => {
                    const barcodeContainer = document.createElement('div');
                    barcodeContainer.className = 'barcode-item';

                    // Récupération du nom du produit associé au code-barres
                    const product = products.find(p => p.barcode === barcode);
                    const productName = product ? product.product_name : '';

                    // Ajout des informations du produit et du code-barres au conteneur
                    barcodeContainer.innerHTML = `
                        <div class="product-info">
                            <span>${productName}</span>
                        </div>
                        <svg id="barcode-${index}"></svg>
                        <div class="product-info">
                            <span>${barcode}</span>
                        </div>
                    `;
                    tempDiv.appendChild(barcodeContainer);
                });

                // Ajout du conteneur temporaire au DOM
                document.body.appendChild(tempDiv);

                // Génération des codes-barres à l'aide de JsBarcode
                barcodes.forEach((barcode, index) => {
                    JsBarcode(`#barcode-${index}`, barcode, {
                        format: "CODE128",
                        width: 2,
                        height: 100,
                        displayValue: false,
                    });
                });

                // Préparation du contenu pour l'impression
                const printContent = tempDiv.outerHTML;

                // Création d'un iframe pour gérer l'impression
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                document.body.appendChild(iframe);

                // Ajout du contenu HTML dans l'iframe
                iframe.contentDocument.write(`
                    <html>
                    <head>
                        <title>Imprimer les codes-barres</title>
                        <style>
                            body { 
                                padding: 5mm;
                            }
                            .barcode-container {
                                display: flex;
                                flex-wrap: wrap;
                                gap: 20px;
                                justify-content: space-between;
                            }
                            .barcode-item {
                                text-align: center;
                                width: 45%;
                                margin-bottom: 30px;
                            }
                            .product-info {
                                margin-bottom: 5px;
                                font-size: 10px;
                                font-weight: bold;
                            }
                            .product-info span {
                                white-space: normal; /* Permettre le retour à la ligne */
                                overflow: visible; /* Autoriser tout le texte à s'afficher */
                                text-overflow: clip; /* Désactiver les points de suspension (...) */
                                word-wrap: break-word; /* Permettre de couper les mots si trop longs */
                                display: block;
                            }
                            svg {
                                width: 160%;
                                height: auto;
                            }
                            @media print {
                                body * {
                                    visibility: hidden;
                                }
                                #barcodesPrintArea, #barcodesPrintArea * {
                                    visibility: visible;
                                }
                                #barcodesPrintArea {
                                    position: absolute;
                                    left: 0;
                                    top: 0;
                                    width: 100%;
                                }
                                .barcode-item {
                                    page-break-inside: avoid;
                                    width: 50%;
                                    margin-bottom: 30px;
                                }
                                .product-info {
                                    font-size: 12px;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        ${printContent}
                    </body>
                    </html>
                `);

                // Lancement de l'impression
                iframe.contentWindow.print();

                // Nettoyage après impression
                setTimeout(() => {
                    document.body.removeChild(tempDiv);
                    document.body.removeChild(iframe);
                }, 100);
            }

            /**
             * FONCTIONS DE GESTION DES ÉVÉNEMENTS
             */

            // Attacher les gestionnaires d'événements aux boutons d'action
            function attachEventHandlers() {
                // Gestionnaire d'événements pour le bouton de suppression
                $('.delete-product').off('click').on('click', function(e) {
                    e.stopPropagation();
                    const productId = $(this).data('id');
                    deleteProduct(productId);
                });

                // Gestionnaire d'événements pour le bouton d'édition
                $('.edit-product').off('click').on('click', function(e) {
                    e.stopPropagation();
                    const productId = $(this).data('id');
                    openEditModal(productId);
                });

                // Gestionnaire d'événements pour le bouton d'impression de code-barres
                $('.print-barcode').off('click').on('click', function(e) {
                    e.stopPropagation();
                    const barcode = $(this).data('barcode');
                    printBarcode(barcode);
                });

                // Gestionnaire d'événements pour voir les réservations
                $('.view-reservations').off('click').on('click', function(e) {
                    e.stopPropagation();
                    const productId = $(this).data('id');
                    const productName = $(this).data('name');
                    openReservationsModal(productId, productName);
                });

                // Gestionnaire d'événements pour la visualisation d'image
                $('.view-image-btn').off('click').on('click', function(e) {
                    e.stopPropagation();
                    const imageSrc = $(this).data('image-src');
                    const productName = $(this).data('product-name');
                    const productCode = $(this).data('product-code');
                    openImageViewer(imageSrc, productName, productCode);
                });
            }

            /**
             * FONCTIONS DE MANIPULATION DES PRODUITS
             */

            // Fonction pour ouvrir la modal d'édition d'un produit
            function openEditModal(productId) {
                // Trouver le produit dans le tableau de données
                const product = products.find(p => p.id == productId);

                if (!product) {
                    showNotification('Produit non trouvé', 'error');
                    return;
                }

                // Charger les catégories pour la modal d'édition
                loadCategoriesForEdit().then(function(response) {
                    if (response.success) {
                        // Vider et remplir le select des catégories
                        const categorySelect = $('#editProductCategory');
                        categorySelect.empty().append($('<option>', {
                            value: '',
                            text: 'Sélectionner une catégorie'
                        }));

                        response.categories.forEach(function(category) {
                            categorySelect.append($('<option>', {
                                value: category.id,
                                text: category.libelle
                            }));
                        });

                        // Remplir le formulaire avec les données du produit
                        $('#editProductId').val(product.id);
                        $('#editProductName').val(product.product_name);
                        $('#editProductCategory').val(product.category);
                        $('#editProductUnit').val(product.unit);
                        $('#editProductQuantity').val(product.quantity);

                        // Charger l'image existante
                        loadExistingImage(product.product_image);

                        // Afficher la modal avec animation
                        const editModal = $('#editModal');
                        const modalOverlay = $('#modalOverlay');

                        editModal.removeClass('hidden');
                        setTimeout(() => {
                            modalOverlay.addClass('opacity-100');
                            $('#editModal > div:nth-child(2)').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
                        }, 50);

                        // Focus sur le champ nom
                        setTimeout(() => {
                            $('#editProductName').focus();
                        }, 200);
                    } else {
                        showNotification('Erreur lors du chargement des catégories', 'error');
                    }
                }).catch(function() {
                    showNotification('Erreur lors du chargement des catégories', 'error');
                });
            }

            // Fonction pour fermer la modal d'édition
            function closeEditModal() {
                const modalOverlay = $('#modalOverlay');
                modalOverlay.removeClass('opacity-100');
                $('#editModal > div:nth-child(2)').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
                setTimeout(() => {
                    $('#editModal').addClass('hidden');
                    // Réinitialiser le formulaire et l'image
                    $('#editProductForm')[0].reset();
                    clearImage();
                    currentImageFile = null;
                }, 200);
            }

            // Fonction pour supprimer un produit
            function deleteProduct(productId) {
                Swal.fire({
                    title: 'Êtes-vous sûr ?',
                    text: "Voulez-vous vraiment supprimer ce produit ?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Oui, supprimer',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'api_deleteProduct.php',
                            type: 'POST',
                            data: {
                                id: productId
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showNotification('Produit supprimé avec succès', 'success');
                                    loadProducts(); // Recharger les produits
                                } else {
                                    showNotification('Erreur lors de la suppression du produit: ' + response.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Erreur AJAX:', error);
                                showNotification('Une erreur est survenue lors de la suppression du produit', 'error');
                            }
                        });
                    }
                });
            }

            /**
             * FONCTIONS DE GESTION DES RÉSERVATIONS
             */

            // Fonction pour ouvrir la modal des réservations
            function openReservationsModal(productId, productName) {
                // Définir le titre de la modal
                $('#reservationsModalTitle').text(`Réservations pour "${productName}"`);

                // Mettre à jour le lien pour voir toutes les réservations
                $('#viewAllReservationsLink').attr('href', 'reservations/reservations_details.php?product_id=' + productId);

                // Charger les réservations
                loadProductReservations(productId);

                // Afficher la modal avec animation
                $('#reservationsModal').removeClass('hidden');
                setTimeout(() => {
                    $('#reservationsModalOverlay').addClass('opacity-100');
                    $('#reservationsModalContent').removeClass('opacity-0 scale-95').addClass('opacity-100 scale-100');
                }, 50);
            }

            // Fonction pour charger les réservations d'un produit
            function loadProductReservations(productId) {
                $('#reservationsTableBody').html(`
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-center">
                            <div class="flex justify-center items-center">
                                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"></div>
                                <span class="ml-2">Chargement des réservations...</span>
                            </div>
                        </td>
                    </tr>
                `);

                $.ajax({
                    url: 'reservations/api/get_product_reservations.php',
                    type: 'GET',
                    data: {
                        product_id: productId
                    },
                    dataType: 'json',
                    success: function(response) {
                        const tableBody = $('#reservationsTableBody');
                        tableBody.empty();

                        if (response.success) {
                            if (response.reservations.length === 0) {
                                tableBody.html('<tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">Aucune réservation trouvée</td></tr>');
                                return;
                            }

                            response.reservations.forEach(function(reservation) {
                                // Déterminer le statut
                                let statusBadge = '';
                                if (reservation.status === 'available') {
                                    statusBadge = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    <span class="material-icons text-xs mr-1">check_circle</span>
                                                    Disponible
                                                </span>`;
                                } else if (reservation.status === 'partial') {
                                    statusBadge = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    <span class="material-icons text-xs mr-1">warning</span>
                                                    Partiel
                                                </span>`;
                                } else {
                                    statusBadge = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    <span class="material-icons text-xs mr-1">error_outline</span>
                                                    Non disponible
                                                </span>`;
                                }

                                tableBody.append(`
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <div class="text-xs font-medium text-gray-900">${reservation.project_name}</div>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <div class="text-xs text-blue-600">${reservation.project_code}</div>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <div class="text-xs font-medium">${reservation.reserved_quantity}</div>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <div class="text-xs text-gray-500">${reservation.created_at}</div>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            ${statusBadge}
                                        </td>
                                    </tr>
                                `);
                            });
                        } else {
                            tableBody.html(`<tr><td colspan="5" class="px-4 py-4 text-center text-red-500">${response.message || 'Une erreur est survenue'}</td></tr>`);
                        }
                    },
                    error: function() {
                        $('#reservationsTableBody').html('<tr><td colspan="5" class="px-4 py-4 text-center text-red-500">Erreur lors du chargement des réservations</td></tr>');
                    }
                });
            }

            /**
             * GESTION DES NOTIFICATIONS
             */

            // Fonction pour afficher les notifications
            function showNotification(message, type = 'success') {
                const backgroundColor = type === 'success' ? '#4CAF50' : '#F44336';
                const icon = type === 'success' ? 'check_circle' : 'error_outline';

                Toastify({
                    text: `
                        <div class="flex items-center">
                            <span class="material-icons mr-2" style="font-size: 24px;">${icon}</span>
                            <span>${message}</span>
                        </div>
                    `,
                    duration: 3000,
                    close: false,
                    gravity: "top",
                    position: "right",
                    style: {
                        background: backgroundColor,
                    },
                    stopOnFocus: true,
                    escapeMarkup: false,
                }).showToast();
            }

            /**
             * INITIALISATION DES ÉCOUTEURS D'ÉVÉNEMENTS
             */

            // GESTION DES MODALES

            // Événements pour la modal d'édition
            $('#closeModalBtn, #cancelEditBtn, #modalOverlay').on('click', function(e) {
                if (e.target === this) {
                    closeEditModal();
                }
            });

            // Événements pour la modal des réservations
            $('#closeReservationsModal, #reservationsModalOverlay').on('click', function(e) {
                if (e.target === this) {
                    $('#reservationsModalOverlay').removeClass('opacity-100');
                    $('#reservationsModalContent').removeClass('opacity-100 scale-100').addClass('opacity-0 scale-95');
                    setTimeout(() => {
                        $('#reservationsModal').addClass('hidden');
                    }, 300);
                }
            });

            // Événements pour la modal d'aide à la recherche
            $('#searchHelpBtn').on('click', function() {
                $('#searchHelpModal').removeClass('hidden');
                setTimeout(() => {
                    $('#searchHelpOverlay').addClass('opacity-100');
                    $('#searchHelpModal > div:nth-child(2)').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
                }, 50);
            });

            $('#closeSearchHelpBtn, #closeSearchHelpConfirmBtn, #searchHelpOverlay').on('click', function(e) {
                if (e.target === this) {
                    $('#searchHelpOverlay').removeClass('opacity-100');
                    $('#searchHelpModal > div:nth-child(2)').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
                    setTimeout(() => {
                        $('#searchHelpModal').addClass('hidden');
                    }, 200);
                }
            });

            // Gestion des touches clavier (Escape) 
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    // Fermer la modal de visualisation d'image si elle est ouverte
                    if (!$('#imageViewerModal').hasClass('hidden')) {
                        closeImageViewer();
                        return;
                    }

                    // Fermer la modal d'édition si elle est ouverte
                    if (!$('#editModal').hasClass('hidden')) {
                        closeEditModal();
                    }

                    // Fermer la modal des réservations si elle est ouverte
                    if (!$('#reservationsModal').hasClass('hidden')) {
                        $('#reservationsModalOverlay').removeClass('opacity-100');
                        $('#reservationsModalContent').removeClass('opacity-100 scale-100').addClass('opacity-0 scale-95');
                        setTimeout(() => {
                            $('#reservationsModal').addClass('hidden');
                        }, 300);
                    }

                    // Fermer la modal d'aide à la recherche si elle est ouverte
                    if (!$('#searchHelpModal').hasClass('hidden')) {
                        $('#searchHelpOverlay').removeClass('opacity-100');
                        $('#searchHelpModal > div:nth-child(2)').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
                        setTimeout(() => {
                            $('#searchHelpModal').addClass('hidden');
                        }, 200);
                    }
                }
            });

            // GESTION DES FORMULAIRES

            // Gestion du formulaire d'édition de produit
            $('#editProductForm').on('submit', function(e) {
                e.preventDefault();

                // Récupérer les données du formulaire
                const productId = $('#editProductId').val();
                const productName = $('#editProductName').val();
                const productCategory = $('#editProductCategory').val();
                const productUnit = $('#editProductUnit').val();
                const productQuantity = $('#editProductQuantity').val();

                // Validation simple côté client
                if (!productName.trim()) {
                    showNotification('Le nom du produit est requis', 'error');
                    $('#editProductName').addClass('input-error').focus();
                    return;
                }

                if (!productCategory) {
                    showNotification('La catégorie du produit est requise', 'error');
                    $('#editProductCategory').addClass('input-error').focus();
                    return;
                }

                if (!productUnit.trim()) {
                    showNotification('L\'unité du produit est requise', 'error');
                    $('#editProductUnit').addClass('input-error').focus();
                    return;
                }

                // Validation pour les nombres décimaux
                if (!productQuantity.trim() || isNaN(productQuantity) || parseFloat(productQuantity) < 0) {
                    showNotification('La quantité doit être un nombre positif ou zéro (décimaux autorisés)', 'error');
                    $('#editProductQuantity').addClass('input-error').focus();
                    return;
                }

                // Formater la quantité en nombre décimal avec 2 décimales maximum
                const formattedQuantity = parseFloat(productQuantity).toFixed(2);

                // Préparer les données à envoyer
                const formData = new FormData();
                formData.append('id', productId);
                formData.append('name', productName);
                formData.append('category', productCategory);
                formData.append('unit', productUnit);
                formData.append('quantity_prod', formattedQuantity);

                // Ajouter l'image si une nouvelle image a été sélectionnée
                if (currentImageFile) {
                    formData.append('product_image', currentImageFile);
                }

                // Afficher l'indicateur de chargement
                $('#editModalLoader').removeClass('hidden');

                // Appel AJAX pour mettre à jour le produit
                $.ajax({
                    url: 'api_updateProduct.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        // Masquer l'indicateur de chargement
                        $('#editModalLoader').addClass('hidden');

                        if (response.success) {
                            // Fermer la modal avec animation
                            closeEditModal();

                            // Afficher une notification de succès
                            showNotification('Produit mis à jour avec succès', 'success');

                            // Recharger la liste des produits pour afficher les modifications
                            loadProducts();
                        } else {
                            showNotification('Erreur: ' + response.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        // Masquer l'indicateur de chargement
                        $('#editModalLoader').addClass('hidden');

                        console.error('Erreur AJAX:', error);
                        showNotification('Une erreur est survenue lors de la mise à jour du produit', 'error');
                    }
                });
            });

            // GESTION DES BOUTONS ET RECHERCHE

            // Recherche personnalisée en temps réel
            $('#customSearchInput').on('keyup', function() {
                if (dataTable) {
                    dataTable.search($(this).val()).draw();
                } else {
                    loadProducts();
                }
            });

            // Gestionnaires pour les filtres
            $('#categoryFilter, #stockStatusFilter').on('change', function() {
                loadProducts();
            });

            // Bouton de réinitialisation des filtres
            $('#resetFiltersBtn').on('click', function() {
                $('#customSearchInput').val('');
                $('#categoryFilter').val('');
                $('#stockStatusFilter').val('');

                if (dataTable) {
                    dataTable.search('').columns().search('').draw();
                }

                loadProducts();
            });

            // Checkbox "Tout sélectionner"
            $('#selectAllCheckbox').on('change', function() {
                if (dataTable) {
                    // Sélectionner/désélectionner toutes les cases à cocher visibles (page courante)
                    dataTable.rows({
                        page: 'current'
                    }).nodes().each(function(node) {
                        $(node).find('.product-checkbox').prop('checked', $('#selectAllCheckbox').is(':checked'));
                    });
                }
            });

            // Bouton d'impression des codes-barres sélectionnés
            $('#printSelectedBtn').on('click', function() {
                const selectedBarcodes = $('.product-checkbox:checked').map(function() {
                    return $(this).data('barcode');
                }).get();

                if (selectedBarcodes.length > 0) {
                    printSelectedBarcodes(selectedBarcodes);
                } else {
                    showNotification('Veuillez sélectionner au moins un produit à imprimer', 'error');
                }
            });

            // Bouton d'impression de la liste complète
            $('#printListBtn').on('click', function() {
                printProductsList();
            });

            /**
             * INITIALISATION DE LA PAGE
             */

            // Initialiser les gestionnaires d'événements pour les images
            initializeImageHandlers();

            // Chargement initial des catégories
            loadCategories();

            // Chargement initial des produits
            loadProducts();

            /**
             * FONCTIONS GLOBALES DE VISUALISATION D'IMAGE
             * (Définies en dehors du document.ready pour être accessibles globalement)
             */

            // Fonction globale pour ouvrir la modal de visualisation d'image
            function openImageViewer(imageSrc, productName, productCode) {
                // Mettre à jour le contenu de la modal
                $('#imageViewerImg').attr('src', imageSrc);
                $('#imageViewerTitle').text(productName);
                $('#imageViewerSubtitle').text(`Code: ${productCode} • Cliquez à l'extérieur pour fermer`);

                // Préparer le lien de téléchargement
                $('#downloadImageBtn').off('click').on('click', function() {
                    downloadImage(imageSrc, `${productName}_${productCode}`);
                });

                // Afficher la modal avec animation
                $('#imageViewerModal').removeClass('hidden');

                // Ajouter une classe pour les transitions CSS
                setTimeout(() => {
                    $('#imageViewerModal').addClass('opacity-100');
                }, 50);
            }

            // Fonction globale pour fermer la modal de visualisation d'image
            function closeImageViewer() {
                $('#imageViewerModal').removeClass('opacity-100');
                setTimeout(() => {
                    $('#imageViewerModal').addClass('hidden');
                }, 300);
            }

            // Fonction globale pour télécharger l'image - VERSION AVEC ENDPOINT
            function downloadImage(imageSrc, filename) {
                // Extraire le nom du fichier depuis le chemin
                let imageFileName;
                if (imageSrc.includes('/')) {
                    imageFileName = imageSrc.split('/').pop();
                } else {
                    imageFileName = imageSrc;
                }

                // Créer l'URL de téléchargement via l'endpoint
                const downloadUrl = `api/download_image.php?file=${encodeURIComponent(imageFileName)}&name=${encodeURIComponent(filename)}`;

                // Créer le lien de téléchargement
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = `${filename}.jpg`;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Notification de succès
                if (typeof showNotification === 'function') {
                    showNotification('Téléchargement démarré', 'success');
                }
            }

            // Initialisation des événements (à exécuter quand le DOM est prêt)
            $(document).ready(function() {
                // Gestionnaires d'événements pour la modal de visualisation d'image
                $('#closeImageViewerBtn, #imageViewerModal').on('click', function(e) {
                    if (e.target === this) {
                        closeImageViewer();
                    }
                });

                // Empêcher la fermeture quand on clique sur l'image
                $('#imageViewerImg').on('click', function(e) {
                    e.stopPropagation();
                });
            });
        });
    </script>
</body>

</html>