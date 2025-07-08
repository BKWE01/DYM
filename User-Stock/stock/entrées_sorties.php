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
    <title>Entrées/Sorties - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <style>
        /* Badge pour le type de mouvement */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-entry {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .badge-output {
            background-color: #fce4ec;
            color: #e91e63;
        }

        .badge-dispatch {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .badge-return {
            background-color: #fff0f6;
            color: #eb2f96;
        }

        /* Style pour le modal de détails */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 80%;
            max-width: 700px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        /* Style pour le tableau de dispatching */
        .dispatch-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
        }

        .dispatch-table th {
            background-color: #f3f4f6;
            padding: 0.75rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #4b5563;
            text-transform: uppercase;
        }

        .dispatch-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .dispatch-table tr:last-child td {
            border-bottom: none;
        }

        .dispatch-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .dispatch-complete {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .dispatch-partial {
            background-color: #fff3e0;
            color: #f57c00;
        }

        /* Style pour prévisualisation de facture */
        .invoice-modal {
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
        }

        .invoice-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 5px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }

        #invoiceModalContent {
            display: flex;
            justify-content: center;
        }

        /* card pour l'image de la facture  */
        img.max-w-full.h-auto {
            padding: .25rem;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: .375rem;
            max-width: 100%;
            height: auto;
        }

        /* Style pour afficher les infos du retour fournisseur */
        .return-info {
            font-size: 0.75rem;
            color: #eb2f96;
            margin-top: 0.25rem;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div id="notification-container" class="fixed top-4 right-4 z-50"></div>
    <div class="flex h-screen">
        <?php include_once 'sidebar.php'; ?>

        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once 'header.php'; ?>

            <main class="p-4 flex-1">
                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="flex flex-wrap justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold">Entrées/Sorties</h2>

                        <!-- Barre de recherche et filtrage -->
                        <div class="flex flex-wrap space-x-4">
                            <div class="relative w-64 mt-2">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons text-gray-400">search</span>
                                </span>
                                <input type="text" id="searchInput" placeholder="Rechercher un mouvement"
                                    class="w-full pl-10 p-2 border rounded">
                            </div>
                            <div class="relative mt-2">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons text-gray-400">filter_list</span>
                                </span>
                                <select id="movementTypeFilter" class="pl-10 p-2 border rounded appearance-none pr-8">
                                    <option value="">Tous les types</option>
                                    <option value="entry">Entrée</option>
                                    <option value="output">Sortie</option>
                                    <option value="supplier-return">Retour fournisseur</option>
                                    <!-- <option value="dispatch">Dispatching</option> -->
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <span class="material-icons text-gray-400">expand_more</span>
                                </span>
                            </div>
                            <div class="relative mt-2">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                    <span class="material-icons text-gray-400">business</span>
                                </span>
                                <select id="projectFilter" class="pl-10 p-2 border rounded appearance-none pr-8">
                                    <option value="">Tous les projets</option>
                                    <!-- Les projets seront chargés dynamiquement -->
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <span class="material-icons text-gray-400">expand_more</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tableau des mouvements de stock -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ID</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Produit</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Quantité</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Type</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Provenance</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Projet</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Destination</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Demandeur</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Facture</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody id="movementTableBody" class="bg-white divide-y divide-gray-200">
                                <!-- Les mouvements seront insérés ici dynamiquement -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="pagination" class="mt-4 flex justify-center space-x-2">
                        <!-- Les boutons de pagination seront insérés ici -->
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal pour les détails de dispatching -->
    <div id="dispatchDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="dispatchModalTitle" class="text-xl font-semibold mb-4">Détails du dispatching</h2>
            <div id="dispatchModalContent">
                <!-- Le contenu du modal sera inséré ici dynamiquement -->
            </div>
        </div>
    </div>

    <!-- Modal pour la prévisualisation de facture -->
    <div id="invoicePreviewModal" class="invoice-modal">
        <div class="invoice-modal-content">
            <span class="close">&times;</span>
            <h2 id="invoiceModalTitle" class="text-xl font-semibold mb-4">Prévisualisation de la facture</h2>
            <div id="invoiceModalContent" class="flex justify-content-center mt-4">
                <!-- Le contenu du modal sera inséré ici dynamiquement -->
            </div>
            <div class="mt-4 flex justify-end">
                <button id="invoiceDownloadBtn" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
                    Télécharger
                </button>
            </div>
        </div>
    </div>

    <!-- Modal pour les détails du retour fournisseur -->
    <div id="supplierReturnDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="returnModalTitle" class="text-xl font-semibold mb-4">Détails du retour fournisseur</h2>
            <div id="returnModalContent">
                <!-- Le contenu du modal sera inséré ici dynamiquement -->
            </div>
        </div>
    </div>

    <script>
        // Fonction pour récupérer les informations de facture
        function getInvoiceForMovement(movementId) {
            return fetch(`get_invoice_for_movement.php?movement_id=${movementId}`)
                .then(response => response.json())
                .catch(error => {
                    console.error('Erreur lors de la récupération des informations de facture:', error);
                    return {
                        success: false,
                        invoice: null
                    };
                });
        }

        // Fonction pour générer le HTML de la cellule de facture
        function createInvoiceCell(movement) {
            // Par défaut, la cellule est vide
            let invoiceCell = '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">-</td>';

            // Si c'est une entrée, récupérer les informations de facture
            if (movement.movement_type === 'entry') {
                // Créer la cellule avec un loader
                invoiceCell = `
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" id="invoice-cell-${movement.id}" data-movement-id="${movement.id}">
                <div class="animate-pulse flex space-x-2">
                    <div class="h-2 w-10 bg-gray-200 rounded"></div>
                </div>
            </td>
        `;

                // Récupérer les informations de facture de manière asynchrone
                setTimeout(() => {
                    getInvoiceForMovement(movement.id).then(data => {
                        const cell = document.getElementById(`invoice-cell-${movement.id}`);
                        if (!cell) return;

                        if (data.success && data.invoice) {
                            const invoice = data.invoice;

                            // Vérifier si un chemin de fichier est disponible
                            if (invoice.file_path) {
                                cell.innerHTML = `
                            <a href="javascript:void(0);" onclick="previewInvoice(${invoice.id}, '${invoice.file_path}')" class="text-blue-600 hover:text-blue-800 flex items-center">
                                <span class="material-icons text-sm mr-1">description</span>
                                ${invoice.invoice_number || invoice.original_filename || `Facture #${invoice.id}`}
                            </a>
                        `;
                            } else {
                                cell.innerHTML = `
                            <span class="text-gray-600 flex items-center">
                                <span class="material-icons text-sm mr-1">receipt</span>
                                ${invoice.invoice_number || `Facture #${invoice.id}`}
                            </span>
                        `;
                            }
                        } else {
                            cell.innerHTML = `
                        <button class="text-blue-600 hover:text-blue-800" onclick="openInvoiceUpload(${movement.id})">
                            Associer
                        </button>`;
                        }
                    });
                }, 100);
            }

            return invoiceCell;
        }

        // Fonction pour extraire et afficher les informations du retour fournisseur
        function viewReturnDetails(movementId) {
            // Récupérer les informations du mouvement depuis le DOM
            const row = document.querySelector(`tr[data-movement-id="${movementId}"]`);
            const destination = row.getAttribute('data-destination');
            const notes = row.getAttribute('data-notes');

            let supplierName = '';
            let returnReason = '';
            let returnComment = '';

            // Extraire le nom du fournisseur de la destination
            if (destination && destination.startsWith('Retour fournisseur: ')) {
                supplierName = destination.substring('Retour fournisseur: '.length);
            }

            // Extraire le motif et le commentaire des notes
            if (notes) {
                if (notes.startsWith('Motif: ')) {
                    const parts = notes.substring('Motif: '.length).split(' - ');
                    returnReason = parts[0];
                    if (parts.length > 1) {
                        returnComment = parts[1];
                    }
                }
            }

            // Récupérer d'autres informations spécifiques depuis la base de données si la table supplier_returns existe
            fetch(`retour-fournisseur/get_supplier_return_details.php?movement_id=${movementId}`)
                .then(response => response.json())
                .then(data => {
                    let modalContent = '';

                    if (data.success && data.return) {
                        const returnData = data.return;

                        // Utiliser les données plus détaillées de la table supplier_returns
                        modalContent = `
                            <div class="bg-pink-50 p-4 rounded mb-4">
                                <div class="flex items-center mb-2">
                                    <span class="material-icons text-pink-500 mr-2">local_shipping</span>
                                    <h3 class="text-lg font-medium text-pink-700">Retour fournisseur #${returnData.id}</h3>
                                </div>
                                <p class="mb-2"><strong>Fournisseur:</strong> ${returnData.supplier_name}</p>
                                <p class="mb-2"><strong>Produit:</strong> ${row.querySelector('td:nth-child(2)').textContent}</p>
                                <p class="mb-2"><strong>Quantité:</strong> ${returnData.quantity}</p>
                                <p class="mb-2"><strong>Motif du retour:</strong> ${returnData.reason}</p>
                                ${returnData.comment ? `<p class="mb-2"><strong>Commentaire:</strong> ${returnData.comment}</p>` : ''}
                                <p class="mb-2"><strong>Date du retour:</strong> ${new Date(returnData.created_at).toLocaleString()}</p>
                                <p class="text-xs text-pink-600 mt-2">Status: ${getStatusText(returnData.status)}</p>
                            </div>
                        `;
                    } else {
                        // Utiliser les informations extraites du DOM si la table n'existe pas ou si le retour n'est pas trouvé
                        modalContent = `
                            <div class="bg-pink-50 p-4 rounded mb-4">
                                <div class="flex items-center mb-2">
                                    <span class="material-icons text-pink-500 mr-2">local_shipping</span>
                                    <h3 class="text-lg font-medium text-pink-700">Retour fournisseur</h3>
                                </div>
                                <p class="mb-2"><strong>Fournisseur:</strong> ${supplierName}</p>
                                <p class="mb-2"><strong>Produit:</strong> ${row.querySelector('td:nth-child(2)').textContent}</p>
                                <p class="mb-2"><strong>Quantité:</strong> ${row.querySelector('td:nth-child(3)').textContent}</p>
                                ${returnReason ? `<p class="mb-2"><strong>Motif du retour:</strong> ${returnReason}</p>` : ''}
                                ${returnComment ? `<p class="mb-2"><strong>Commentaire:</strong> ${returnComment}</p>` : ''}
                                <p class="mb-2"><strong>Date:</strong> ${row.querySelector('td:nth-child(9)').textContent}</p>
                            </div>
                        `;
                    }

                    // Afficher le modal avec les détails
                    document.getElementById('returnModalTitle').textContent = 'Détails du retour fournisseur';
                    document.getElementById('returnModalContent').innerHTML = modalContent;
                    document.getElementById('supplierReturnDetailsModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Erreur:', error);

                    // En cas d'erreur, afficher quand même les infos basiques
                    const modalContent = `
                        <div class="bg-pink-50 p-4 rounded mb-4">
                            <div class="flex items-center mb-2">
                                <span class="material-icons text-pink-500 mr-2">local_shipping</span>
                                <h3 class="text-lg font-medium text-pink-700">Retour fournisseur</h3>
                            </div>
                            <p class="mb-2"><strong>Fournisseur:</strong> ${supplierName}</p>
                            <p class="mb-2"><strong>Produit:</strong> ${row.querySelector('td:nth-child(2)').textContent}</p>
                            <p class="mb-2"><strong>Quantité:</strong> ${row.querySelector('td:nth-child(3)').textContent}</p>
                            ${returnReason ? `<p class="mb-2"><strong>Motif:</strong> ${returnReason}</p>` : ''}
                            ${returnComment ? `<p class="mb-2"><strong>Commentaire:</strong> ${returnComment}</p>` : ''}
                            <p class="mb-2"><strong>Date:</strong> ${row.querySelector('td:nth-child(9)').textContent}</p>
                        </div>
                    `;

                    // Afficher le modal avec les détails
                    document.getElementById('returnModalTitle').textContent = 'Détails du retour fournisseur';
                    document.getElementById('returnModalContent').innerHTML = modalContent;
                    document.getElementById('supplierReturnDetailsModal').style.display = 'block';
                });
        }

        // Fonction auxiliaire pour afficher le statut du retour
        function getStatusText(status) {
            switch (status) {
                case 'pending':
                    return 'En attente';
                case 'completed':
                    return 'Complété';
                case 'cancelled':
                    return 'Annulé';
                default:
                    return 'Inconnu';
            }
        }

        // Fonction pour prévisualiser une facture
        function previewInvoice(invoiceId, filePath) {
            const modalTitle = document.getElementById('invoiceModalTitle');
            const modalContent = document.getElementById('invoiceModalContent');
            const downloadBtn = document.getElementById('invoiceDownloadBtn');

            // Mettre à jour le titre
            modalTitle.textContent = `Facture #${invoiceId}`;

            // Afficher l'indicateur de chargement
            modalContent.innerHTML = '<div class="flex justify-center items-center p-4"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>';

            // Utiliser directement get_invoice_direct.php qui contient maintenant notre logique intelligente
            fetch(`get_invoice_direct.php?id=${invoiceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayContent(data.file_url);
                    } else {
                        // Si aucun fichier n'est trouvé, essayer une recherche forcée
                        return fetch(`get_invoice_direct.php?id=${invoiceId}&force=1`)
                            .then(response => response.json())
                            .then(forceData => {
                                if (forceData.success) {
                                    displayContent(forceData.file_url);
                                } else {
                                    showErrorMessage(filePath, forceData);
                                }
                            });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showErrorMessage(filePath);
                });

            // Fonction pour afficher le contenu de la facture
            function displayContent(path) {
                const fileExtension = path.split('.').pop().toLowerCase();

                if (['pdf'].includes(fileExtension)) {
                    // Pour les PDF, utiliser un iframe
                    modalContent.innerHTML = `
                <iframe src="${path}" width="100%" height="500px" frameborder="0"></iframe>
            `;
                } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                    // Pour les images
                    modalContent.innerHTML = `
                <img src="${path}" alt="Facture #${invoiceId}" class="max-w-full h-auto">
            `;
                } else {
                    // Pour les autres types de fichiers, afficher un message
                    modalContent.innerHTML = `
                <div class="p-4 bg-gray-100 rounded-md text-center">
                    <span class="material-icons text-4xl text-gray-600 mb-2">description</span>
                    <p>Le fichier ne peut pas être prévisualisé directement.</p>
                    <p class="text-sm text-gray-500">Type de fichier: ${fileExtension.toUpperCase()}</p>
                </div>
            `;
                }

                // Configurer le bouton de téléchargement
                downloadBtn.onclick = function() {
                    // Créer un élément anchor temporaire
                    const downloadLink = document.createElement('a');
                    downloadLink.href = path;

                    // Extraire le nom du fichier depuis le chemin
                    const fileName = path.split('/').pop();

                    // Définir l'attribut download pour forcer le téléchargement
                    downloadLink.setAttribute('download', fileName);

                    // Masquer le lien
                    downloadLink.style.display = 'none';

                    // Ajouter au DOM
                    document.body.appendChild(downloadLink);

                    // Simuler un clic
                    downloadLink.click();

                    // Nettoyer après le téléchargement
                    setTimeout(() => {
                        document.body.removeChild(downloadLink);
                    }, 100);
                };
            }

            // Fonction pour afficher un message d'erreur
            function showErrorMessage(path, errorData) {
                modalContent.innerHTML = `
            <div class="p-4 bg-red-50 rounded-md text-center">
                <span class="material-icons text-4xl text-red-500 mb-2">error_outline</span>
                <p>Impossible de charger le fichier.</p>
                <p class="text-sm text-gray-600">Chemin: ${path}</p>
                <div class="mt-4">
                    <button id="tryAlternativesBtn" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        Réessayer avec une méthode alternative
                    </button>
                </div>
            </div>
        `;

                // Ajouter le gestionnaire d'événement après le rendu du contenu
                setTimeout(() => {
                    const tryAlternativesBtn = document.getElementById('tryAlternativesBtn');
                    if (tryAlternativesBtn) {
                        tryAlternativesBtn.addEventListener('click', function() {
                            // Afficher un message de diagnostic
                            Swal.fire({
                                title: 'Diagnostic du fichier',
                                html: `
                            <div class="text-left">
                                <p><strong>Facture ID:</strong> ${invoiceId}</p>
                                <p><strong>Chemin original:</strong> ${path}</p>
                                <p class="mt-3">Recherche approfondie en cours...</p>
                            </div>
                        `,
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });

                            // Utiliser get_invoice_direct avec force=1 et debug=1
                            fetch(`get_invoice_direct.php?id=${invoiceId}&force=1&debug=1`)
                                .then(response => response.json())
                                .then(data => {
                                    Swal.close();

                                    if (data.success && data.file_url) {
                                        displayContent(data.file_url);

                                        // Afficher une notification de succès
                                        Toastify({
                                            text: "Fichier trouvé avec succès!",
                                            duration: 3000,
                                            close: true,
                                            gravity: "top",
                                            position: "right",
                                            backgroundColor: "#51cf66",
                                        }).showToast();
                                    } else {
                                        // Afficher les chemins recherchés pour le débogage
                                        Swal.fire({
                                            title: 'Fichier introuvable',
                                            html: `
                                        <div class="text-left">
                                            <p>Le fichier n'a pas pu être localisé après une recherche approfondie.</p>
                                            <p class="mt-3"><strong>Détails techniques:</strong></p>
                                            <pre class="bg-gray-100 p-2 mt-2 text-xs overflow-auto max-h-36">${JSON.stringify(data, null, 2)}</pre>
                                        </div>
                                    `,
                                            icon: 'error'
                                        });
                                    }
                                })
                                .catch(error => {
                                    Swal.close();
                                    console.error('Erreur:', error);
                                    Swal.fire('Erreur', 'Une erreur est survenue lors de la tentative alternative.', 'error');
                                });
                        });
                    }
                }, 100);
            }

            // Afficher le modal
            document.getElementById('invoicePreviewModal').style.display = 'block';
        }

        // Fonction pour vérifier si une chaîne ressemble à un code de projet
        function looksLikeProjectCode(str) {
            // Les codes projet ont généralement un format comme "ABC-12345" ou "ABC12345"
            return typeof str === 'string' && (
                /^[A-Z]+-\d+$/i.test(str) || // Format avec tiret ABC-12345
                /^[A-Z]{2,}\d+$/i.test(str) // Format sans tiret ABC12345
            );
        }

        $(document).ready(function() {
            let currentPage = 1;
            const movementsPerPage = 10;

            // Charger les projets pour le filtre
            loadProjects();

            function loadProjects() {
                $.ajax({
                    url: 'api_getProjects.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        console.log('Réponse du serveur pour les projets :', response);

                        if (response.success) {
                            if (response.projects && response.projects.length > 0) {
                                let projectSelect = $('#projectFilter');
                                projectSelect.empty(); // Vider d'abord le sélecteur
                                projectSelect.append('<option value="">Tous les projets</option>');

                                // Trier les projets par code_projet
                                response.projects.sort((a, b) => a.code_projet.localeCompare(b.code_projet));

                                response.projects.forEach(function(project) {
                                    // Vérifier et formater les données
                                    const projectCode = project.code_projet || 'N/A';
                                    const clientName = project.nom_client || 'Client non spécifié';

                                    projectSelect.append(`
                            <option value="${projectCode}">
                                ${projectCode} - ${clientName}
                            </option>
                        `);
                                });
                            } else {
                                console.warn('Aucun projet trouvé');
                                $('#projectFilter').append('<option value="">Aucun projet disponible</option>');
                            }
                        } else {
                            console.error('Erreur lors du chargement des projets:', response.message);
                            $('#projectFilter').append(`<option value="">Erreur: ${response.message}</option>`);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur AJAX complète :', {
                            status: status,
                            error: error,
                            responseText: xhr.responseText
                        });

                        $('#projectFilter').append(`
                <option value="">Erreur de connexion</option>
            `);
                    }
                });
            }

            function loadMovements(page) {
                const search = $('#searchInput').val();
                const movementType = $('#movementTypeFilter').val();
                const projectCode = $('#projectFilter').val();

                $.ajax({
                    url: 'api_getStockMovements.php',
                    type: 'GET',
                    data: {
                        page: page,
                        limit: movementsPerPage,
                        search: search,
                        movement_type: movementType,
                        project_code: projectCode
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let tableBody = $('#movementTableBody');
                            tableBody.empty();

                            // Vérifier si la table dispatch_details existe
                            $.ajax({
                                url: 'check_dispatch_table.php',
                                type: 'GET',
                                dataType: 'json',
                                async: false,
                                success: function(tableResponse) {
                                    const dispatchTableExists = tableResponse.exists;

                                    // Traiter les mouvements
                                    if (response.movements.length === 0) {
                                        tableBody.append(`
                                <tr>
                                    <td colspan="11" class="px-6 py-4 text-center text-gray-500">Aucun mouvement trouvé</td>
                                </tr>
                            `);
                                    } else {
                                        // Si la table dispatch_details existe, récupérer les détails de dispatching pour les entrées
                                        let entriesWithDispatching = [];

                                        if (dispatchTableExists && (movementType === '' || movementType === 'entry')) {
                                            response.movements.forEach(function(movement) {
                                                if (movement.movement_type === 'entry') {
                                                    // Récupérer les détails de dispatching pour cette entrée
                                                    $.ajax({
                                                        url: 'get_dispatch_for_entry.php',
                                                        type: 'GET',
                                                        data: {
                                                            movement_id: movement.id
                                                        },
                                                        dataType: 'json',
                                                        async: false,
                                                        success: function(dispatchResponse) {
                                                            if (dispatchResponse.success && dispatchResponse.dispatches.length > 0) {
                                                                // Cette entrée a des dispatching, les ajouter individuellement
                                                                dispatchResponse.dispatches.forEach(function(dispatch) {
                                                                    entriesWithDispatching.push({
                                                                        id: movement.id,
                                                                        product_id: movement.product_id,
                                                                        product_name: movement.product_name,
                                                                        quantity: dispatch.allocated,
                                                                        movement_type: 'entry',
                                                                        provenance: movement.provenance,
                                                                        nom_projet: dispatch.project,
                                                                        nom_client: dispatch.client || '', // Stockage du nom du client
                                                                        project_display_name: dispatch.client || '', // Utiliser directement le champ client comme nom à afficher
                                                                        destination: dispatch.client || movement.destination,
                                                                        demandeur: movement.demandeur,
                                                                        date: movement.date,
                                                                        invoice_id: movement.invoice_id
                                                                    });
                                                                });
                                                            } else {
                                                                // Pas de dispatching, afficher l'entrée normalement
                                                                entriesWithDispatching.push(movement);
                                                            }
                                                        },
                                                        error: function() {
                                                            // En cas d'erreur, afficher l'entrée normalement
                                                            entriesWithDispatching.push(movement);
                                                        }
                                                    });
                                                } else if (movement.movement_type === 'output') {
                                                    // C'est une sortie, l'ajouter normalement
                                                    entriesWithDispatching.push(movement);
                                                }
                                                // Ignorer les mouvements de type 'dispatch' car nous les affichons via la table dispatch_details
                                            });

                                            // Pour chaque mouvement, récupérer le nom du projet si seulement le code est disponible
                                            // Pour les entrées standards (non-dispatching) qui n'ont pas de project_display_name
                                            entriesWithDispatching.forEach(function(movement, index) {
                                                // Si c'est une entrée standard (pas de dispatching) et que nous n'avons pas encore de project_display_name
                                                if (!movement.project_display_name && movement.nom_projet && looksLikeProjectCode(movement.nom_projet)) {
                                                    // Récupérer le nom du projet depuis la table identification_projet
                                                    $.ajax({
                                                        url: 'get_project_name.php',
                                                        type: 'GET',
                                                        data: {
                                                            project_code: movement.nom_projet
                                                        },
                                                        dataType: 'json',
                                                        async: false,
                                                        success: function(projectResponse) {
                                                            if (projectResponse.success) {
                                                                entriesWithDispatching[index].project_display_name = projectResponse.nom_client;
                                                            }
                                                        }
                                                    });
                                                } else if (!movement.project_display_name && movement.nom_projet) {
                                                    // Nom de projet existant qui n'est pas un code
                                                    entriesWithDispatching[index].project_display_name = movement.nom_projet;
                                                }
                                            });

                                            // Utiliser les entrées avec dispatching pour l'affichage
                                            entriesWithDispatching.forEach(function(movement) {
                                                let badgeClass, movementTypeDisplay;

                                                if (movement.movement_type === 'entry') {
                                                    badgeClass = 'badge-entry';
                                                    movementTypeDisplay = 'Entrée';
                                                } else if (movement.movement_type === 'output') {
                                                    // Vérifier si c'est un retour fournisseur
                                                    if (movement.destination && movement.destination.startsWith('Retour fournisseur:')) {
                                                        badgeClass = 'badge-return';
                                                        movementTypeDisplay = 'Retour';
                                                    } else {
                                                        badgeClass = 'badge-output';
                                                        movementTypeDisplay = 'Sortie';
                                                    }
                                                }

                                                // Déterminer le type d'entrée ou de sortie
                                                const isOutput = movement.movement_type === 'output';
                                                const isReturn = isOutput && movement.destination && movement.destination.startsWith('Retour fournisseur:');

                                                // IMPORTANT: Identifier les entrées vers le stock général (quantité restante après dispatching)
                                                const isStockGeneral = movement.movement_type === 'entry' &&
                                                    (movement.destination === 'Stock général' ||
                                                        movement.nom_projet === 'Stock général');

                                                // Pour l'affichage des noms de projet ou de client
                                                let projectDisplay = movement.project_display_name || movement.nom_client || movement.nom_projet || '-';

                                                // Pour les entrées de stock général, nous voulons montrer clairement que c'est le stock général
                                                if (isStockGeneral) {
                                                    projectDisplay = 'Stock général';
                                                }

                                                // Destination: afficher pour les sorties et entrées de stock général
                                                let destinationDisplay = '-';
                                                if (isOutput) {
                                                    destinationDisplay = movement.destination || '-';
                                                } else if (isStockGeneral) {
                                                    destinationDisplay = 'Stock général';
                                                }

                                                // Classe CSS conditionnelle pour les cellules
                                                let destClass = !isOutput && !isStockGeneral ? 'text-gray-300' : '';

                                                // Détails supplémentaires pour les retours fournisseurs
                                                let additionalActions = '';
                                                if (isReturn) {
                                                    additionalActions = `
                                                <button class="text-pink-600 hover:text-pink-800 ml-2 view-return-details" data-id="${movement.id}">
                                                    <span class="material-icons text-sm">info</span>
                                                </button>
                                            `;
                                                }

                                                tableBody.append(`
                                        <tr data-movement-id="${movement.id}" data-destination="${movement.destination || ''}" data-notes="${movement.notes || ''}">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.id}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${movement.product_name}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.quantity}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="badge ${badgeClass}">${movementTypeDisplay}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.provenance || '-'}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-medium">${projectDisplay}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 ${destClass}">${destinationDisplay}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 ${!isOutput ? 'text-gray-300' : ''}">${isOutput ? (movement.demandeur || '-') : '-'}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.date}</td>
                                            ${createInvoiceCell(movement)}
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                ${additionalActions}
                                            </td>
                                        </tr>
                                    `);
                                            });
                                        } else {
                                            // Table dispatch_details n'existe pas ou filtre sur les sorties, afficher normalement
                                            // Pour chaque mouvement, vérifier si nom_projet est un code ou un nom de client
                                            response.movements.forEach(function(movement, index) {
                                                // Pour la rétrocompatibilité - vérifier si nom_projet contient un code de projet ou déjà un nom de client
                                                if (movement.nom_projet && looksLikeProjectCode(movement.nom_projet)) {
                                                    // Ancien format: nom_projet contient le code du projet, faire la requête pour obtenir le nom du client
                                                    $.ajax({
                                                        url: 'get_project_name.php',
                                                        type: 'GET',
                                                        data: {
                                                            project_code: movement.nom_projet
                                                        },
                                                        dataType: 'json',
                                                        async: false,
                                                        success: function(projectResponse) {
                                                            if (projectResponse.success) {
                                                                // Stocker le résultat dans une nouvelle propriété pour l'affichage
                                                                response.movements[index].project_display_name = projectResponse.nom_client;
                                                            }
                                                        }
                                                    });
                                                } else if (movement.nom_projet) {
                                                    // Nouveau format: nom_projet contient déjà le nom du client
                                                    response.movements[index].project_display_name = movement.nom_projet;
                                                }
                                            });

                                            response.movements.forEach(function(movement) {
                                                // Ignorer les mouvements de type 'dispatch'
                                                if (movement.movement_type === 'dispatch') {
                                                    return;
                                                }

                                                let badgeClass, movementTypeDisplay;

                                                if (movement.movement_type === 'entry') {
                                                    badgeClass = 'badge-entry';
                                                    movementTypeDisplay = 'Entrée';
                                                } else if (movement.movement_type === 'output') {
                                                    // Vérifier si c'est un retour fournisseur
                                                    if (movement.destination && movement.destination.startsWith('Retour fournisseur:')) {
                                                        badgeClass = 'badge-return';
                                                        movementTypeDisplay = 'Retour';
                                                    } else {
                                                        badgeClass = 'badge-output';
                                                        movementTypeDisplay = 'Sortie';
                                                    }
                                                }

                                                // Déterminer le type d'entrée ou de sortie
                                                const isOutput = movement.movement_type === 'output';
                                                const isReturn = isOutput && movement.destination && movement.destination.startsWith('Retour fournisseur:');

                                                // IMPORTANT: Identifier les entrées vers le stock général (quantité restante après dispatching)
                                                const isStockGeneral = movement.movement_type === 'entry' &&
                                                    (movement.destination === 'Stock général' ||
                                                        movement.nom_projet === 'Stock général');

                                                // Pour l'affichage des noms de projet ou de client
                                                let projectDisplay = movement.project_display_name || movement.nom_projet || '-';

                                                // Pour les entrées de stock général, nous voulons montrer clairement que c'est le stock général
                                                if (isStockGeneral) {
                                                    projectDisplay = 'Stock général';
                                                }

                                                // Destination: afficher pour les sorties et entrées de stock général
                                                let destinationDisplay = '-';
                                                if (isOutput) {
                                                    destinationDisplay = movement.destination || '-';
                                                } else if (isStockGeneral) {
                                                    destinationDisplay = 'Stock général';
                                                }

                                                // Classe CSS conditionnelle pour les cellules
                                                let destClass = !isOutput && !isStockGeneral ? 'text-gray-300' : '';

                                                // Détails supplémentaires pour les retours fournisseurs
                                                let additionalActions = '';
                                                if (isReturn) {
                                                    additionalActions = `
                                                <button class="text-pink-600 hover:text-pink-800 ml-2 view-return-details" data-id="${movement.id}">
                                                    <span class="material-icons text-sm">info</span>
                                                </button>
                                            `;
                                                }

                                                tableBody.append(`
                                <tr data-movement-id="${movement.id}" data-destination="${movement.destination || ''}" data-notes="${movement.notes || ''}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.id}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${movement.product_name}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.quantity}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="badge ${badgeClass}">${movementTypeDisplay}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.provenance || '-'}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-medium">${projectDisplay}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 ${destClass}">${destinationDisplay}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 ${!isOutput ? 'text-gray-300' : ''}">${isOutput ? (movement.demandeur || '-') : '-'}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.date}</td>
                                    ${createInvoiceCell(movement)}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        ${additionalActions}
                                    </td>
                                </tr>
                            `);
                                            });
                                        }
                                    }
                                },
                                error: function() {
                                    // Si on ne peut pas vérifier la table, afficher normalement
                                    // Pour chaque mouvement, vérifier si nom_projet est un code ou un nom de client
                                    response.movements.forEach(function(movement, index) {
                                        // Pour la rétrocompatibilité - vérifier si nom_projet contient un code de projet ou déjà un nom de client
                                        if (movement.nom_projet && looksLikeProjectCode(movement.nom_projet)) {
                                            // Ancien format: nom_projet contient le code du projet, faire la requête pour obtenir le nom du client
                                            $.ajax({
                                                url: 'get_project_name.php',
                                                type: 'GET',
                                                data: {
                                                    project_code: movement.nom_projet
                                                },
                                                dataType: 'json',
                                                async: false,
                                                success: function(projectResponse) {
                                                    if (projectResponse.success) {
                                                        // Stocker le résultat dans une nouvelle propriété pour l'affichage
                                                        response.movements[index].project_display_name = projectResponse.nom_client;
                                                    }
                                                }
                                            });
                                        } else if (movement.nom_projet) {
                                            // Nouveau format: nom_projet contient déjà le nom du client
                                            response.movements[index].project_display_name = movement.nom_projet;
                                        }
                                    });

                                    response.movements.forEach(function(movement) {
                                        // Ignorer les mouvements de type 'dispatch'
                                        if (movement.movement_type === 'dispatch') {
                                            return;
                                        }

                                        let badgeClass, movementTypeDisplay;

                                        if (movement.movement_type === 'entry') {
                                            badgeClass = 'badge-entry';
                                            movementTypeDisplay = 'Entrée';
                                        } else if (movement.movement_type === 'output') {
                                            // Vérifier si c'est un retour fournisseur
                                            if (movement.destination && movement.destination.startsWith('Retour fournisseur:')) {
                                                badgeClass = 'badge-return';
                                                movementTypeDisplay = 'Retour';
                                            } else {
                                                badgeClass = 'badge-output';
                                                movementTypeDisplay = 'Sortie';
                                            }
                                        }

                                        // Déterminer le type d'entrée ou de sortie
                                        const isOutput = movement.movement_type === 'output';
                                        const isReturn = isOutput && movement.destination && movement.destination.startsWith('Retour fournisseur:');

                                        // IMPORTANT: Identifier les entrées vers le stock général (quantité restante après dispatching)
                                        const isStockGeneral = movement.movement_type === 'entry' &&
                                            (movement.destination === 'Stock général' ||
                                                movement.nom_projet === 'Stock général');

                                        // Pour l'affichage des noms de projet ou de client
                                        let projectDisplay = movement.project_display_name || movement.nom_projet || '-';

                                        // Pour les entrées de stock général, nous voulons montrer clairement que c'est le stock général
                                        if (isStockGeneral) {
                                            projectDisplay = 'Stock général';
                                        }

                                        // Destination: afficher pour les sorties et entrées de stock général
                                        let destinationDisplay = '-';
                                        if (isOutput) {
                                            destinationDisplay = movement.destination || '-';
                                        } else if (isStockGeneral) {
                                            destinationDisplay = 'Stock général';
                                        }

                                        // Classe CSS conditionnelle pour les cellules
                                        let destClass = !isOutput && !isStockGeneral ? 'text-gray-300' : '';

                                        // Détails supplémentaires pour les retours fournisseurs
                                        let additionalActions = '';
                                        if (isReturn) {
                                            additionalActions = `
                                        <button class="text-pink-600 hover:text-pink-800 ml-2 view-return-details" data-id="${movement.id}">
                                            <span class="material-icons text-sm">info</span>
                                        </button>
                                    `;
                                        }

                                        tableBody.append(`
                                <tr data-movement-id="${movement.id}" data-destination="${movement.destination || ''}" data-notes="${movement.notes || ''}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.id}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${movement.product_name}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.quantity}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="badge ${badgeClass}">${movementTypeDisplay}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.provenance || '-'}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-medium">${projectDisplay}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 ${destClass}">${destinationDisplay}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 ${!isOutput ? 'text-gray-300' : ''}">${isOutput ? (movement.demandeur || '-') : '-'}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${movement.date}</td>
                                    ${createInvoiceCell(movement)}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        ${additionalActions}
                                    </td>
                                </tr>
                            `);
                                    });
                                }
                            });

                            updatePagination(response.totalPages, currentPage);
                        } else {
                            console.error('Erreur lors de la récupération des mouvements:', response.message);
                            showNotification('Erreur: ' + response.message, 'error');
                            $('#movementTableBody').html('<tr><td colspan="11" class="text-center py-4">Erreur lors de la récupération des mouvements. Veuillez réessayer.</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur AJAX:', error);
                        showNotification('Erreur de connexion au serveur', 'error');
                        $('#movementTableBody').html('<tr><td colspan="11" class="text-center py-4">Erreur de connexion au serveur. Veuillez réessayer plus tard.</td></tr>');
                    }
                });
            }
            // Fonction pour charger les détails d'un dispatching
            function loadDispatchDetails(movementId) {
                $.ajax({
                    url: 'api_getDispatchDetails.php',
                    type: 'GET',
                    data: {
                        movement_id: movementId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const details = response.details;
                            const completedOrders = details.filter(d => d.status === 'completed');
                            const partialOrders = details.filter(d => d.status === 'partial');

                            let modalContent = `
                                <div class="bg-gray-100 p-4 rounded mb-4">
                                    <p class="mb-2"><strong>Produit:</strong> ${details[0].product_name}</p>
                                    <p class="mb-2"><strong>Quantité totale:</strong> ${details[0].total_quantity}</p>
                                    <p><strong>Date:</strong> ${details[0].dispatch_date}</p>
                                </div>
                                
                                <div class="flex items-center justify-between text-sm font-medium bg-gray-100 p-2 rounded-md mb-3">
                                    <span>${completedOrders.length} commande(s) complètement satisfaite(s)</span>
                                    <span>${partialOrders.length} commande(s) partiellement satisfaite(s)</span>
                                </div>
                            `;

                            if (completedOrders.length > 0) {
                                modalContent += `
                                    <h4 class="text-lg font-semibold text-green-700 mb-2">Commandes complètement satisfaites</h4>
                                    <table class="dispatch-table">
                                        <thead>
                                            <tr>
                                                <th>Produit</th>
                                                <th>Projet</th>
                                                <th>Client</th>
                                                <th>Quantité</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                `;

                                completedOrders.forEach(order => {
                                    modalContent += `
                                        <tr>
                                            <td>${order.product_name}</td>
                                            <td>${order.project}</td>
                                            <td>${order.client}</td>
                                            <td>${order.allocated}</td>
                                            <td><span class="dispatch-status dispatch-complete">Complété</span></td>
                                        </tr>
                                    `;
                                });

                                modalContent += `
                                        </tbody>
                                    </table>
                                `;
                            }

                            if (partialOrders.length > 0) {
                                modalContent += `
                                    <h4 class="text-lg font-semibold text-orange-600 mb-2 mt-4">Commandes partiellement satisfaites</h4>
                                    <table class="dispatch-table">
                                        <thead>
                                            <tr>
                                                <th>Produit</th>
                                                <th>Projet</th>
                                                <th>Client</th>
                                                <th>Quantité allouée</th>
                                                <th>Reste à livrer</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                `;

                                partialOrders.forEach(order => {
                                    modalContent += `
                                        <tr>
                                            <td>${order.product_name}</td>
                                            <td>${order.project}</td>
                                            <td>${order.client}</td>
                                            <td>${order.allocated}</td>
                                            <td>${order.remaining}</td>
                                            <td><span class="dispatch-status dispatch-partial">Partiel</span></td>
                                        </tr>
                                    `;
                                });

                                modalContent += `
                                        </tbody>
                                    </table>
                                `;
                            }

                            $('#dispatchModalTitle').text(`Détails du dispatching #${movementId}`);
                            $('#dispatchModalContent').html(modalContent);
                            $('#dispatchDetailsModal').css('display', 'block');
                        } else {
                            showNotification('Erreur: ' + response.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur AJAX:', error);
                        showNotification('Erreur de connexion au serveur', 'error');
                    }
                });
            }

            function updatePagination(totalPages, currentPage) {
                let paginationHtml = '';

                // Bouton précédent avec chevron simple
                paginationHtml += `
                    <button class="px-3 py-1 rounded ${currentPage === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}" 
                            ${currentPage === 1 ? 'disabled' : `onclick="changePage(${currentPage - 1})"`}>
                        &lsaquo;
                    </button>
                `;

                // Logique existante pour les numéros de pages
                const delta = 2;
                let range = [];
                range.push(1);

                for (let i = Math.max(2, currentPage - delta); i <= Math.min(totalPages - 1, currentPage + delta); i++) {
                    if (i === 2 && currentPage - delta > 2) {
                        range.push('...');
                    }
                    range.push(i);
                    if (i === totalPages - 1 && currentPage + delta < totalPages - 1) {
                        range.push('...');
                    }
                }

                if (totalPages > 1) {
                    range.push(totalPages);
                }

                // Génération des boutons de pages
                range.forEach(page => {
                    if (page === '...') {
                        paginationHtml += `
                            <span class="px-3 py-1">...</span>
                        `;
                    } else {
                        paginationHtml += `
                            <button class="px-3 py-1 rounded ${page === currentPage ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700'}"
                                    onclick="changePage(${page})">${page}</button>
                        `;
                    }
                });

                // Bouton suivant avec chevron simple
                paginationHtml += `
                    <button class="px-3 py-1 rounded ${currentPage === totalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}"
                            ${currentPage === totalPages ? 'disabled' : `onclick="changePage(${currentPage + 1})"`}>
                        &rsaquo;
                    </button>
                `;

                $('#pagination').html(paginationHtml);
            }

            window.changePage = function(page) {
                currentPage = page;
                loadMovements(page);
            };

            function openInvoiceUpload(movementId) {
                Swal.fire({
                    title: 'Associer une facture',
                    html: '<input type="file" id="swal-invoice-file" class="swal2-file">',
                    showCancelButton: true,
                    confirmButtonText: 'Envoyer',
                    preConfirm: () => {
                        const fileInput = document.getElementById('swal-invoice-file');
                        if (!fileInput || fileInput.files.length === 0) {
                            Swal.showValidationMessage('Veuillez sélectionner un fichier');
                            return false;
                        }

                        const formData = new FormData();
                        formData.append('invoice_file', fileInput.files[0]);

                        return fetch('upload_invoice.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (!data.success) {
                                    throw new Error(data.message || 'Erreur lors de l\'upload');
                                }

                                const req = {
                                    movement_id: movementId,
                                    invoice: {
                                        file_path: data.file_path,
                                        original_filename: data.original_filename,
                                        file_type: data.file_type,
                                        file_size: data.file_size,
                                        upload_user_id: data.upload_user_id
                                    }
                                };

                                return fetch('api/associate_invoice.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify(req)
                                }).then(r => r.json());
                            })
                            .catch(err => {
                                Swal.showValidationMessage(err.message);
                            });
                    }
                }).then(result => {
                    if (result.isConfirmed && result.value && result.value.success) {
                        Swal.fire('Succès', 'Facture associée avec succès', 'success').then(() => {
                            loadMovements(currentPage);
                        });
                    } else if (result.isConfirmed && result.value && !result.value.success) {
                        Swal.fire('Erreur', result.value.message || 'Une erreur est survenue', 'error');
                    }
                });
            }

            window.openInvoiceUpload = openInvoiceUpload;

            // Gestionnaire d'événements pour la recherche
            $('#searchInput').on('input', debounce(function() {
                currentPage = 1;
                loadMovements(currentPage);
            }, 300));

            // Gestionnaire d'événements pour les filtres
            $('#movementTypeFilter, #projectFilter').on('change', function() {
                currentPage = 1;
                loadMovements(currentPage);
            });

            // Gestionnaire d'événement pour voir les détails des retours fournisseurs
            $(document).on('click', '.view-return-details', function() {
                const movementId = $(this).data('id');
                viewReturnDetails(movementId);
            });

            // Fonction de debounce pour éviter trop d'appels API lors de la saisie
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            // Fonction pour afficher les notifications
            function showNotification(message, type) {
                Toastify({
                    text: message,
                    duration: 5000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    backgroundColor: type === 'error' ? "#ff6b6b" : "#51cf66",
                }).showToast();
            }

            // Gestionnaire pour fermer les modals
            $('.close').on('click', function() {
                $('#dispatchDetailsModal').css('display', 'none');
                $('#invoicePreviewModal').css('display', 'none');
                $('#supplierReturnDetailsModal').css('display', 'none');
            });

            // Fermer les modals si on clique en dehors
            $(window).on('click', function(event) {
                if (event.target == document.getElementById('dispatchDetailsModal')) {
                    $('#dispatchDetailsModal').css('display', 'none');
                }
                if (event.target == document.getElementById('invoicePreviewModal')) {
                    $('#invoicePreviewModal').css('display', 'none');
                }
                if (event.target == document.getElementById('supplierReturnDetailsModal')) {
                    $('#supplierReturnDetailsModal').css('display', 'none');
                }
            });

            // Chargement initial des mouvements
            loadMovements(currentPage);
        });
    </script>
</body>

</html>