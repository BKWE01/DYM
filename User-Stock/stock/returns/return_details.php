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

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Rediriger vers la page des retours
    header("Location: index.php");
    exit();
}

$return_id = intval($_GET['id']);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Retour - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transform: translateY(-3px);
        }

        .data-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .data-value {
            color: #111827;
            font-size: 1rem;
            font-weight: 500;
        }

        .empty-value {
            color: #9ca3af;
            font-style: italic;
        }

        .timeline-item {
            position: relative;
            padding-left: 1.5rem;
            padding-bottom: 1.5rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #e5e7eb;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            left: -4px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #3b82f6;
            border: 2px solid #ffffff;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-item:last-child::before {
            display: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            border-radius: 9999px;
        }

        .badge-green {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-blue {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-red {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .badge-yellow {
            background-color: #fef3c7;
            color: #92400e;
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

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

    <!-- Bibliothèques React et Ant Design nécessaires -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react/17.0.2/umd/react.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react-dom/17.0.2/umd/react-dom.production.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/antd/4.16.13/antd.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/antd/4.16.13/antd.min.js"></script>
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
                    <!-- Loader -->
                    <div id="loading" class="flex justify-center items-center py-12">
                        <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        <span class="text-gray-700">Chargement des détails du retour...</span>
                    </div>

                    <!-- Contenu principal - affiché une fois les données chargées -->
                    <div id="returnDetails" class="hidden">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-800">Détails du Retour <span
                                        id="returnIdDisplay" class="text-gray-500 font-normal"></span></h1>
                                <p class="text-gray-600 mt-1">Informations complètes sur le retour en stock</p>
                            </div>

                            <!-- Actions conditionnelles selon le statut -->
                            <div id="actionButtons" class="space-x-2">
                                <!-- Les boutons d'action seront ajoutés dynamiquement selon le statut -->
                            </div>
                        </div>

                        <!-- Carte d'informations principales -->
                        <div class="card p-6 mb-6">
                            <div class="flex justify-between items-start mb-4">
                                <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <span class="material-icons-round mr-2 text-blue-600">info</span>
                                    Informations générales
                                </h2>
                                <span id="statusBadge" class="badge"></span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <p class="data-label">Produit</p>
                                    <p id="productName" class="data-value"></p>
                                    <p id="productBarcode" class="text-sm text-gray-500 mt-1"></p>
                                </div>

                                <div>
                                    <p class="data-label">Quantité retournée</p>
                                    <p id="quantity" class="data-value"></p>
                                </div>

                                <div>
                                    <p class="data-label">Origine / Provenance</p>
                                    <p id="origin" class="data-value"></p>
                                </div>

                                <div>
                                    <p class="data-label">Motif du retour</p>
                                    <p id="returnReason" class="data-value"></p>
                                </div>

                                <div>
                                    <p class="data-label">État du produit</p>
                                    <p id="productCondition" class="data-value"></p>
                                </div>

                                <div>
                                    <p class="data-label">Retourné par</p>
                                    <p id="returnedBy" class="data-value"></p>
                                </div>

                                <div class="md:col-span-2">
                                    <p class="data-label">Commentaires</p>
                                    <p id="comments" class="data-value"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Historique du retour -->
                        <div class="card p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <span class="material-icons-round mr-2 text-blue-600">history</span>
                                Historique du retour
                            </h2>

                            <div id="timeline" class="pl-4">
                                <!-- Les événements de l'historique seront ajoutés ici -->
                            </div>
                        </div>
                    </div>

                    <!-- Message d'erreur en cas de problème -->
                    <div id="errorMessage" class="hidden text-center py-12">
                        <div class="mx-auto w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mb-4">
                            <span class="material-icons-round text-red-500 text-4xl">error</span>
                        </div>
                        <h3 class="text-lg font-medium text-gray-800 mb-2">Erreur de chargement</h3>
                        <p id="errorText" class="text-gray-500 mb-6">Impossible de charger les détails du retour.</p>
                        <a href="index.php"
                            class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg inline-flex items-center">
                            <span class="material-icons-round mr-2">arrow_back</span>
                            Retour à la liste
                        </a>
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
        const returnId = <?php echo $return_id; ?>;
        let returnData = null;

        // Fonction pour initialiser le conteneur de notification
        function initNotification() {
            try {
                const container = document.getElementById('notification-container');
                if (window.React && window.ReactDOM && window.antd) {
                    ReactDOM.render(React.createElement(antd.ConfigProvider, null), container);
                } else {
                    console.warn("Les bibliothèques React ou Ant Design ne sont pas chargées correctement");
                }
            } catch (error) {
                console.error("Erreur lors de l'initialisation des notifications:", error);
            }
        }

        // Appeler la fonction d'initialisation
        initNotification();

        // Fonction alternative pour afficher des notifications sans antd
        function showNotification(message, type) {
            if (window.Toastify) {
                const backgroundColor = type === 'success' ? '#10b981' : '#ef4444';

                Toastify({
                    text: message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: backgroundColor,
                    stopOnFocus: true,
                }).showToast();
            } else {
                console.log(`Notification (${type}): ${message}`);
            }
        }

        // Fonction pour charger les détails du retour
        function loadReturnDetails() {
            fetch(`api/get_return_details.php?id=${returnId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading').classList.add('hidden');

                    if (data.success) {
                        returnData = data.return_data;
                        displayReturnDetails(returnData);
                        document.getElementById('returnDetails').classList.remove('hidden');
                    } else {
                        document.getElementById('errorText').textContent = data.message || 'Impossible de charger les détails du retour.';
                        document.getElementById('errorMessage').classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('loading').classList.add('hidden');
                    document.getElementById('errorMessage').classList.remove('hidden');
                });
        }

        // Fonction pour afficher les détails du retour
        function displayReturnDetails(data) {
            // Afficher l'ID du retour
            document.getElementById('returnIdDisplay').textContent = `#${data.id}`;

            // Afficher les informations du produit
            document.getElementById('productName').textContent = data.product_name;
            document.getElementById('productBarcode').textContent = `Code-barres: ${data.barcode}`;
            document.getElementById('quantity').textContent = `${data.quantity} ${data.unit || 'unité(s)'}`;
            document.getElementById('origin').textContent = data.origin;

            // Afficher le motif du retour
            const reasonDisplay = document.getElementById('returnReason');
            switch (data.return_reason) {
                case 'unused':
                    reasonDisplay.textContent = 'Produit non utilisé';
                    break;
                case 'excess':
                    reasonDisplay.textContent = 'Excédent de matériel';
                    break;
                case 'wrong_product':
                    reasonDisplay.textContent = 'Produit erroné';
                    break;
                case 'defective':
                    reasonDisplay.textContent = 'Produit défectueux';
                    break;
                case 'project_completed':
                    reasonDisplay.textContent = 'Projet terminé';
                    break;
                case 'other':
                    reasonDisplay.textContent = data.other_reason || 'Autre';
                    break;
                default:
                    reasonDisplay.textContent = data.return_reason;
            }

            // Afficher l'état du produit
            const conditionDisplay = document.getElementById('productCondition');
            switch (data.product_condition) {
                case 'new':
                    conditionDisplay.textContent = 'Neuf (non utilisé)';
                    break;
                case 'good':
                    conditionDisplay.textContent = 'Bon état (utilisable)';
                    break;
                case 'damaged':
                    conditionDisplay.textContent = 'Endommagé (réparable)';
                    break;
                case 'defective':
                    conditionDisplay.textContent = 'Défectueux (non utilisable)';
                    break;
                default:
                    conditionDisplay.textContent = data.product_condition;
            }

            // Afficher la personne qui a retourné le produit
            document.getElementById('returnedBy').textContent = data.returned_by;

            // Afficher les commentaires (s'il y en a)
            const commentsDisplay = document.getElementById('comments');
            if (data.comments) {
                commentsDisplay.textContent = data.comments;
            } else {
                commentsDisplay.textContent = 'Aucun commentaire';
                commentsDisplay.classList.add('empty-value');
            }

            // Afficher le statut
            const statusBadge = document.getElementById('statusBadge');
            let statusText, badgeClass;
            switch (data.status) {
                case 'pending':
                    statusText = 'En attente';
                    badgeClass = 'badge-yellow';
                    break;
                case 'approved':
                    statusText = 'Approuvé';
                    badgeClass = 'badge-blue';
                    break;
                case 'completed':
                    statusText = 'Complété';
                    badgeClass = 'badge-green';
                    break;
                case 'rejected':
                    statusText = 'Rejeté';
                    badgeClass = 'badge-red';
                    break;
                case 'canceled':
                    statusText = 'Annulé';
                    badgeClass = 'badge-red';
                    break;
                default:
                    statusText = 'En attente';
                    badgeClass = 'badge-yellow';
            }
            statusBadge.textContent = statusText;
            statusBadge.className = `badge ${badgeClass}`;

            // Afficher les boutons d'action selon le statut
            displayActionButtons(data.status);

            // Afficher l'historique du retour
            displayTimeline(data.history || []);
        }

        // Fonction pour afficher les boutons d'action
        function displayActionButtons(status) {
            const actionButtons = document.getElementById('actionButtons');
            actionButtons.innerHTML = '';

            if (status === 'pending') {
                // Bouton Approuver
                const approveBtn = document.createElement('button');
                approveBtn.className = 'bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg shadow-md transition-all flex items-center';
                approveBtn.innerHTML = '<span class="material-icons-round mr-2">check_circle</span> Approuver';
                approveBtn.addEventListener('click', () => approveReturn(returnId));
                actionButtons.appendChild(approveBtn);

                // Bouton Rejeter
                const rejectBtn = document.createElement('button');
                rejectBtn.className = 'bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg shadow-md transition-all flex items-center';
                rejectBtn.innerHTML = '<span class="material-icons-round mr-2">cancel</span> Rejeter';
                rejectBtn.addEventListener('click', () => rejectReturn(returnId));
                actionButtons.appendChild(rejectBtn);

                // Bouton Annuler
                const cancelBtn = document.createElement('button');
                cancelBtn.className = 'bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg shadow-md transition-all flex items-center';
                cancelBtn.innerHTML = '<span class="material-icons-round mr-2">delete</span> Annuler';
                cancelBtn.addEventListener('click', () => cancelReturn(returnId));
                actionButtons.appendChild(cancelBtn);
            } else if (status === 'approved') {
                // Bouton Compléter
                const completeBtn = document.createElement('button');
                completeBtn.className = 'bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg shadow-md transition-all flex items-center';
                completeBtn.innerHTML = '<span class="material-icons-round mr-2">done_all</span> Marquer comme complété';
                completeBtn.addEventListener('click', () => completeReturn(returnId));
                actionButtons.appendChild(completeBtn);

                // Bouton Annuler
                const cancelBtn = document.createElement('button');
                cancelBtn.className = 'bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg shadow-md transition-all flex items-center';
                cancelBtn.innerHTML = '<span class="material-icons-round mr-2">delete</span> Annuler';
                cancelBtn.addEventListener('click', () => cancelReturn(returnId));
                actionButtons.appendChild(cancelBtn);
            }

            // Bouton d'impression (toujours disponible)
            const printBtn = document.createElement('button');
            printBtn.className = 'bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg shadow-md transition-all flex items-center';
            printBtn.innerHTML = '<span class="material-icons-round mr-2">print</span> Imprimer';
            printBtn.addEventListener('click', printReturnDetails);
            actionButtons.appendChild(printBtn);
        }

        // Fonction pour afficher la chronologie
        function displayTimeline(history) {
            const timeline = document.getElementById('timeline');
            timeline.innerHTML = '';

            if (history.length === 0) {
                const emptyTimeline = document.createElement('div');
                emptyTimeline.className = 'text-center text-gray-500 py-4';
                emptyTimeline.textContent = 'Aucun événement dans l\'historique';
                timeline.appendChild(emptyTimeline);
                return;
            }

            // Ajouter les événements à la chronologie
            history.forEach(event => {
                const timelineItem = document.createElement('div');
                timelineItem.className = 'timeline-item';

                let eventTitle, eventIcon;
                switch (event.action) {
                    case 'created':
                        eventTitle = 'Retour créé';
                        eventIcon = 'add_circle';
                        break;
                    case 'approved':
                        eventTitle = 'Retour approuvé';
                        eventIcon = 'check_circle';
                        break;
                    case 'completed':
                        eventTitle = 'Retour complété';
                        eventIcon = 'done_all';
                        break;
                    case 'rejected':
                        eventTitle = 'Retour rejeté';
                        eventIcon = 'cancel';
                        break;
                    case 'canceled':
                        eventTitle = 'Retour annulé';
                        eventIcon = 'delete';
                        break;
                    default:
                        eventTitle = 'Événement';
                        eventIcon = 'event';
                }

                // Formater la date
                const eventDate = new Date(event.created_at);
                const formattedDate = eventDate.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                timelineItem.innerHTML = `
                    <div class="mb-1 flex items-center">
                        <span class="material-icons-round text-blue-600 mr-2">${eventIcon}</span>
                        <h3 class="font-medium">${eventTitle}</h3>
                    </div>
                    <div class="text-sm text-gray-500 mb-1">${formattedDate}</div>
                    <div class="text-sm">
                        <span class="font-medium">Par:</span> ${event.user_name || 'Utilisateur'}
                    </div>
                    ${event.details ? `<div class="text-sm mt-1">${event.details}</div>` : ''}
                `;

                timeline.appendChild(timelineItem);
            });
        }

        // Fonction pour approuver un retour
        function approveReturn(returnId) {
            Swal.fire({
                title: 'Approuver le retour',
                text: 'Êtes-vous sûr de vouloir approuver ce retour?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Oui, approuver',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Envoyer la requête d'approbation
                    fetch('api/process_return.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            return_id: returnId,
                            action: 'approve'
                        }),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Approuvé!',
                                    text: 'Le retour a été approuvé avec succès.',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    // Recharger la page pour afficher les changements
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Erreur',
                                    text: data.message || 'Une erreur est survenue lors de l\'approbation du retour.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            Swal.fire({
                                title: 'Erreur de connexion',
                                text: 'Une erreur est survenue lors de la communication avec le serveur.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });
        }

        // Fonction pour rejeter un retour
        function rejectReturn(returnId) {
            Swal.fire({
                title: 'Rejeter le retour',
                text: 'Veuillez indiquer le motif du rejet',
                input: 'text',
                inputPlaceholder: 'Motif du rejet',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Rejeter',
                cancelButtonText: 'Annuler',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Veuillez saisir un motif de rejet';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Envoyer la requête de rejet
                    fetch('api/process_return.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            return_id: returnId,
                            action: 'reject',
                            reject_reason: result.value
                        }),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Rejeté!',
                                    text: 'Le retour a été rejeté avec succès.',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    // Recharger la page pour afficher les changements
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Erreur',
                                    text: data.message || 'Une erreur est survenue lors du rejet du retour.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            Swal.fire({
                                title: 'Erreur de connexion',
                                text: 'Une erreur est survenue lors de la communication avec le serveur.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });
        }

        // Fonction pour compléter un retour
        function completeReturn(returnId) {
            Swal.fire({
                title: 'Compléter le retour',
                text: 'Êtes-vous sûr de vouloir marquer ce retour comme complété? Cette action finalisera le retour en stock.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Oui, compléter',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Envoyer la requête de complétion
                    fetch('api/process_return.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            return_id: returnId,
                            action: 'complete'
                        }),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Complété!',
                                    text: 'Le retour a été marqué comme complété avec succès.',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    // Recharger la page pour afficher les changements
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Erreur',
                                    text: data.message || 'Une erreur est survenue lors de la complétion du retour.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            Swal.fire({
                                title: 'Erreur de connexion',
                                text: 'Une erreur est survenue lors de la communication avec le serveur.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });
        }

        // Fonction pour annuler un retour
        function cancelReturn(returnId) {
            Swal.fire({
                title: 'Annuler le retour',
                text: 'Êtes-vous sûr de vouloir annuler ce retour?',
                input: 'text',
                inputPlaceholder: 'Motif d\'annulation (optionnel)',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, annuler',
                cancelButtonText: 'Ne pas annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Envoyer la requête d'annulation
                    fetch('api/cancel_return.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            return_id: returnId,
                            cancel_reason: result.value || ''
                        }),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Annulé!',
                                    text: 'Le retour a été annulé avec succès.',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    // Recharger la page pour afficher les changements
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Erreur',
                                    text: data.message || 'Une erreur est survenue lors de l\'annulation du retour.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            Swal.fire({
                                title: 'Erreur de connexion',
                                text: 'Une erreur est survenue lors de la communication avec le serveur.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });
        }

        // Fonction pour imprimer les détails du retour
        function printReturnDetails() {
            if (!returnData) return;

            // Créer une fenêtre d'impression
            const printWindow = window.open('', '_blank');

            // Style CSS pour l'impression
            const printStyles = `
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 20px;
                border-bottom: 1px solid #ddd;
            }
            .company-name {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .document-title {
                font-size: 18px;
                color: #555;
            }
            .section {
                margin-bottom: 20px;
            }
            .section-title {
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px solid #eee;
            }
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            .info-item {
                margin-bottom: 10px;
            }
            .info-label {
                font-weight: bold;
                color: #555;
                margin-bottom: 3px;
            }
            .info-value {
                margin: 0;
            }
            .status {
                display: inline-block;
                padding: 5px 10px;
                border-radius: 15px;
                font-weight: bold;
                font-size: 14px;
            }
            .status-pending {
                background-color: #fef3c7;
                color: #92400e;
            }
            .status-approved {
                background-color: #dbeafe;
                color: #1e40af;
            }
            .status-completed {
                background-color: #d1fae5;
                color: #065f46;
            }
            .status-rejected, .status-canceled {
                background-color: #fee2e2;
                color: #b91c1c;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                font-size: 12px;
                color: #666;
                text-align: center;
            }
            @media print {
                @page {
                    size: A4;
                    margin: 2cm;
                }
            }
        </style>
    `;

            // Obtenir le statut formaté
            let statusClass, statusText;
            switch (returnData.status) {
                case 'pending':
                    statusText = 'En attente';
                    statusClass = 'status-pending';
                    break;
                case 'approved':
                    statusText = 'Approuvé';
                    statusClass = 'status-approved';
                    break;
                case 'completed':
                    statusText = 'Complété';
                    statusClass = 'status-completed';
                    break;
                case 'rejected':
                    statusText = 'Rejeté';
                    statusClass = 'status-rejected';
                    break;
                case 'canceled':
                    statusText = 'Annulé';
                    statusClass = 'status-canceled';
                    break;
                default:
                    statusText = 'En attente';
                    statusClass = 'status-pending';
            }

            // Formater la date
            const createdDate = new Date(returnData.created_at);
            const formattedDate = createdDate.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            // Formater la raison du retour
            let returnReason;
            switch (returnData.return_reason) {
                case 'unused':
                    returnReason = 'Produit non utilisé';
                    break;
                case 'excess':
                    returnReason = 'Excédent de matériel';
                    break;
                case 'wrong_product':
                    returnReason = 'Produit erroné';
                    break;
                case 'defective':
                    returnReason = 'Produit défectueux';
                    break;
                case 'project_completed':
                    returnReason = 'Projet terminé';
                    break;
                case 'other':
                    returnReason = returnData.other_reason || 'Autre';
                    break;
                default:
                    returnReason = returnData.return_reason;
            }

            // Formater l'état du produit
            let productCondition;
            switch (returnData.product_condition) {
                case 'new':
                    productCondition = 'Neuf (non utilisé)';
                    break;
                case 'good':
                    productCondition = 'Bon état (utilisable)';
                    break;
                case 'damaged':
                    productCondition = 'Endommagé (réparable)';
                    break;
                case 'defective':
                    productCondition = 'Défectueux (non utilisable)';
                    break;
                default:
                    productCondition = returnData.product_condition;
            }

            // Contenu HTML
            const printContent = `
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <title>Détails du Retour #${returnData.id}</title>
            ${printStyles}
        </head>
        <body>
            <div class="header">
                <div class="company-name">DYM STOCK</div>
                <div class="document-title">Fiche de Retour en Stock</div>
            </div>
            
            <div class="section">
                <div class="section-title">Informations générales</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Référence du retour:</div>
                        <p class="info-value">#${returnData.id}</p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date de création:</div>
                        <p class="info-value">${formattedDate}</p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Statut:</div>
                        <p class="info-value"><span class="status ${statusClass}">${statusText}</span></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Retourné par:</div>
                        <p class="info-value">${returnData.returned_by}</p>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Informations sur le produit</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Produit:</div>
                        <p class="info-value">${returnData.product_name}</p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Code-barres:</div>
                        <p class="info-value">${returnData.barcode}</p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Quantité retournée:</div>
                        <p class="info-value">${returnData.quantity} ${returnData.unit || 'unité(s)'}</p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">État du produit:</div>
                        <p class="info-value">${productCondition}</p>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Informations sur le retour</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Origine / Provenance:</div>
                        <p class="info-value">${returnData.origin}</p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Motif du retour:</div>
                        <p class="info-value">${returnReason}</p>
                    </div>
                </div>
                <div class="info-item" style="margin-top: 15px;">
                    <div class="info-label">Commentaires:</div>
                    <p class="info-value">${returnData.comments || 'Aucun commentaire'}</p>
                </div>
            </div>
            
            <div class="footer">
                <p>Document généré le ${new Date().toLocaleDateString('fr-FR')} à ${new Date().toLocaleTimeString('fr-FR')}</p>
                <p>DYM STOCK - Système de gestion de stock</p>
            </div>
        </body>
        </html>
    `;

            // Écrire le contenu dans la fenêtre d'impression
            printWindow.document.write(printContent);
            printWindow.document.close();

            // Attendre que le contenu soit chargé avant d'imprimer
            printWindow.onload = function () {
                printWindow.print();
                // La fenêtre sera fermée après l'impression
            };
        }

        // Charger les détails du retour au chargement de la page
        document.addEventListener('DOMContentLoaded', loadReturnDetails);

        // Appeler la fonction d'initialisation après le chargement de la page
        document.addEventListener('DOMContentLoaded', function () {
            initNotification();
        });

    </script>
</body>

</html>