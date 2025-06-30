
// Ajouter ces fonctions à votre fichier JavaScript pour achats_materiaux.php

// 1. Configuration des onglets
function setupTabs() {
    const tabs = [
        { tabId: 'tab-materials', contentId: 'content-materials' },
        { tabId: 'tab-grouped', contentId: 'content-grouped' },
        { tabId: 'tab-partial', contentId: 'content-partial' },
        { tabId: 'tab-recents', contentId: 'content-recents' }
    ];

    tabs.forEach(({ tabId, contentId }) => {
        const tab = document.getElementById(tabId);
        const content = document.getElementById(contentId);

        if (tab && content) {
            tab.addEventListener('click', (e) => {
                e.preventDefault();

                // Réinitialiser tous les onglets
                tabs.forEach(t => {
                    const currentTab = document.getElementById(t.tabId);
                    const currentContent = document.getElementById(t.contentId);
                    if (currentTab) {
                        currentTab.classList.remove('border-blue-500', 'text-blue-600');
                        currentTab.classList.add('border-transparent', 'text-gray-500');
                    }
                    if (currentContent) {
                        currentContent.classList.add('hidden');
                    }
                });

                // Activer l'onglet cliqué
                tab.classList.remove('border-transparent', 'text-gray-500');
                tab.classList.add('border-blue-500', 'text-blue-600');
                content.classList.remove('hidden');

                // Si nous affichons l'onglet des commandes partielles, charger les données
                if (tabId === 'tab-partial') {
                    loadPartialOrders();
                }

                // Réajuster les tables DataTables si nécessaire
                if (window.jQuery && window.jQuery.fn.DataTable) {
                    if (tabId === 'tab-partial' && jQuery.fn.DataTable.isDataTable('#partialOrdersTable')) {
                        jQuery('#partialOrdersTable').DataTable().columns.adjust().responsive.recalc();
                    }
                }
            });
        }
    });
}

// 2. Chargement des commandes partielles
function loadPartialOrders() {
    try {
        document.getElementById('partial-orders-body').innerHTML = `
    <tr>
        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
            Chargement des données...
        </td>
    </tr>
`;

        fetch('commandes-traitement/api.php?action=get_remaining')
            .then(response => response.json())
            .then(data => {
                console.log("Données reçues:", data); // Débogage

                if (data.success) {
                    // Mise à jour des stats avec vérification de l'existence des éléments
                    const updateElement = (id, value) => {
                        const element = document.getElementById(id);
                        if (element) {
                            element.textContent = value;
                        } else {
                            console.warn(`Élément #${id} non trouvé dans le DOM`);
                        }
                    };

                    // Mettre à jour les statistiques de manière sécurisée
                    updateElement('stat-total-partial', data.stats.total_materials || 0);

                    const formattedRemaining = data.stats.total_remaining ?
                        parseFloat(data.stats.total_remaining).toLocaleString('fr-FR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }) : '0';
                    updateElement('stat-remaining-qty', formattedRemaining);

                    updateElement('stat-projects-count', data.stats.total_projects || 0);
                    updateElement('stat-progress', `${data.stats.progress || 0}%`);

                    // Mise à jour de la barre de progression
                    const progressBar = document.getElementById('progress-bar');
                    if (progressBar) {
                        progressBar.style.width = `${data.stats.progress || 0}%`;
                    } else {
                        console.warn("Élément #progress-bar non trouvé dans le DOM");
                    }

                    // Remplir le tableau avec les matériaux
                    renderPartialOrdersTable(data.materials || []);

                } else {
                    console.error("Erreur lors du chargement des commandes partielles:", data.message);
                    document.getElementById('partial-orders-body').innerHTML = `
                <tr>
                    <td colspan="8" class="px-6 py-4 text-center text-sm text-red-500">
                        Erreur lors du chargement des données: ${data.message || 'Veuillez réessayer'}
                    </td>
                </tr>
            `;
                }
            })
            .catch(error => {
                console.error("Erreur réseau:", error);
                document.getElementById('partial-orders-body').innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-4 text-center text-sm text-red-500">
                    Erreur de communication avec le serveur: ${error.message}. Veuillez réessayer.
                </td>
            </tr>
        `;
            });
    } catch (e) {
        console.error("Erreur lors du chargement des commandes partielles:", e);
        alert("Une erreur s'est produite lors du chargement des commandes partielles: " + e.message);
    }
}

// 3. Affichage des commandes partielles dans le tableau
function renderPartialOrdersTable(materials) {
    console.log("Rendu du tableau avec", materials.length, "matériaux"); // Débogage

    const tableBody = document.getElementById('partial-orders-body');
    if (!tableBody) {
        console.error("Élément 'partial-orders-body' non trouvé dans le DOM");
        return;
    }

    if (!materials || materials.length === 0) {
        tableBody.innerHTML = `
    <tr>
        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
            Aucune commande partielle trouvée.
        </td>
    </tr>
`;
        return;
    }

    let html = '';

    materials.forEach(material => {
        console.log("Traitement du matériau:", material); // Débogage

        // Calculer les valeurs nécessaires avec gestion des valeurs nulles/undefined
        const initialQty = parseFloat(material.initial_qt_acheter || parseFloat(material.qt_acheter) + parseFloat(material.qt_restante) || 0);
        const orderedQty = initialQty - parseFloat(material.qt_restante || 0);
        const progress = initialQty > 0 ? Math.round((orderedQty / initialQty) * 100) : 0;

        // Déterminer la couleur de la barre de progression
        let progressColor = 'bg-yellow-500';
        if (progress >= 75) progressColor = 'bg-green-500';
        if (progress < 25) progressColor = 'bg-red-500';

        // Formater les quantités pour l'affichage
        const formatQty = (qty) => {
            if (qty === null || qty === undefined) return '0.00';
            return parseFloat(qty).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        const materialId = material.id || '';
        const designation = material.designation || 'Sans désignation';
        const unit = material.unit || '';
        const restante = parseFloat(material.qt_restante || 0);

        html += `
    <tr class="${progress < 50 ? 'bg-yellow-50' : ''}" data-id="${materialId}">
        <td class="px-6 py-4 whitespace-nowrap">${material.code_projet || '-'}</td>
        <td class="px-6 py-4 whitespace-nowrap">${material.nom_client || '-'}</td>
        <td class="px-6 py-4 whitespace-nowrap font-medium">${designation}</td>
        <td class="px-6 py-4 whitespace-nowrap">${formatQty(initialQty)} ${unit}</td>
        <td class="px-6 py-4 whitespace-nowrap">${formatQty(orderedQty)} ${unit}</td>
        <td class="px-6 py-4 whitespace-nowrap text-yellow-600 font-medium">${formatQty(restante)} ${unit}</td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="${progressColor} h-2 rounded-full" style="width: ${progress}%"></div>
                </div>
                <span class="ml-2 text-xs font-medium">${progress}%</span>
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <button onclick="completePartialOrder('${materialId}', '${designation.replace(/'/g, "\\'")}', ${restante}, '${unit}')" 
                class="text-blue-600 hover:text-blue-900 mr-2">
                <span class="material-icons text-sm">add_shopping_cart</span>
            </button>
            <button onclick="viewPartialOrderDetails('${materialId}')" 
                class="text-gray-600 hover:text-gray-900">
                <span class="material-icons text-sm">visibility</span>
            </button>
        </td>
    </tr>
`;
    });

    if (html) {
        tableBody.innerHTML = html;
        console.log("Tableau mis à jour avec", materials.length, "lignes");
    } else {
        tableBody.innerHTML = `
    <tr>
        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
            Erreur lors de la génération du tableau.
        </td>
    </tr>
`;
        console.error("Aucun HTML généré pour le tableau");
    }
}
// 4. Fonction pour compléter une commande partielle
function completePartialOrder(id, designation, remaining, unit) {
    // Afficher un modal pour saisir les détails de la commande complémentaire
    Swal.fire({
        title: 'Compléter la commande',
        html: `
    <div class="text-left">
        <p class="mb-4"><strong>Désignation :</strong> ${designation}</p>
        <p class="mb-4"><strong>Quantité restante :</strong> ${remaining} ${unit}</p>
        
        <div class="mb-4">
            <label for="quantity" class="block text-sm font-medium text-gray-700">Quantité à commander :</label>
            <input type="number" id="quantity" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" 
                value="${remaining}" min="0.01" max="${remaining}" step="0.01">
        </div>
        
        <div class="mb-4">
            <label for="supplier" class="block text-sm font-medium text-gray-700">Fournisseur :</label>
            <input type="text" id="supplier" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
        </div>
        
        <div class="mb-4">
            <label for="price" class="block text-sm font-medium text-gray-700">Prix unitaire (FCFA) :</label>
            <input type="number" id="price" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" 
                min="0.01" step="0.01">
        </div>
    </div>
`,
        showCancelButton: true,
        confirmButtonText: 'Commander',
        cancelButtonText: 'Annuler',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const quantity = document.getElementById('quantity').value;
            const supplier = document.getElementById('supplier').value;
            const price = document.getElementById('price').value;

            // Validation
            if (!quantity || parseFloat(quantity) <= 0 || parseFloat(quantity) > parseFloat(remaining)) {
                Swal.showValidationMessage('Veuillez saisir une quantité valide (entre 0 et ' + remaining + ')');
                return false;
            }

            if (!supplier.trim()) {
                Swal.showValidationMessage('Veuillez indiquer un fournisseur');
                return false;
            }

            if (!price || parseFloat(price) <= 0) {
                Swal.showValidationMessage('Veuillez saisir un prix unitaire valide');
                return false;
            }

            // Préparation des données
            const formData = new FormData();
            formData.append('action', 'complete_partial_order');
            formData.append('material_id', id);
            formData.append('quantite_commande', quantity);
            formData.append('fournisseur', supplier);
            formData.append('prix_unitaire', price);

            // Envoi de la requête
            return fetch('commandes-traitement/api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Erreur lors de l\'enregistrement de la commande');
                    }
                    return data;
                })
                .catch(error => {
                    Swal.showValidationMessage(`Erreur: ${error.message}`);
                });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Succès !',
                text: result.value.message || 'Commande enregistrée avec succès',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Recharger les données
                loadPartialOrders();
            });
        }
    });
}

// 5. Fonction pour voir les détails d'une commande partielle
function viewPartialOrderDetails(id) {
    // Afficher un loader
    Swal.fire({
        title: 'Chargement...',
        text: 'Récupération des détails de la commande',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Charger les détails de la commande
    fetch(`commandes-traitement/api.php?action=get_partial_details&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const material = data.material;
                const linkedOrders = data.linked_orders || [];

                // Calculer les valeurs nécessaires
                const initialQty = parseFloat(material.initial_qt_acheter || material.qt_acheter + material.qt_restante);
                const orderedQty = initialQty - parseFloat(material.qt_restante);
                const progress = initialQty > 0 ? Math.round((orderedQty / initialQty) * 100) : 0;

                // Préparer le HTML pour les commandes liées
                let ordersHtml = '';
                if (linkedOrders.length > 0) {
                    linkedOrders.forEach(order => {
                        const orderDate = new Date(order.date_achat).toLocaleDateString('fr-FR');
                        ordersHtml += `
                    <tr>
                        <td class="border px-4 py-2">${orderDate}</td>
                        <td class="border px-4 py-2">${parseFloat(order.quantity).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${material.unit || ''}</td>
                        <td class="border px-4 py-2">${parseFloat(order.prix_unitaire).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} FCFA</td>
                        <td class="border px-4 py-2">${order.fournisseur || '-'}</td>
                        <td class="border px-4 py-2">
                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                ${order.status === 'reçu' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}">
                                ${order.status}
                            </span>
                        </td>
                    </tr>
                `;
                    });
                } else {
                    ordersHtml = `
                <tr>
                    <td colspan="5" class="border px-4 py-2 text-center text-gray-500">
                        Aucune commande liée trouvée.
                    </td>
                </tr>
            `;
                }

                // Afficher les détails dans un modal
                Swal.fire({
                    title: 'Détails de la commande partielle',
                    html: `
                <div class="text-left">
                    <div class="mb-4 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Désignation :</p>
                            <p class="font-medium">${material.designation}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Projet :</p>
                            <p class="font-medium">${material.code_projet || '-'} - ${material.nom_client || '-'}</p>
                        </div>
                    </div>
                    
                    <div class="mb-4 grid grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Quantité initiale :</p>
                            <p class="font-medium">${initialQty.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${material.unit || ''}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Quantité commandée :</p>
                            <p class="font-medium">${orderedQty.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${material.unit || ''}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Quantité restante :</p>
                            <p class="font-medium text-yellow-600">${parseFloat(material.qt_restante).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${material.unit || ''}</p>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <p class="text-sm text-gray-600 mb-1">Progression :</p>
                        <div class="flex items-center">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-yellow-500 h-2 rounded-full" style="width: ${progress}%"></div>
                            </div>
                            <span class="ml-2 text-xs font-medium">${progress}%</span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <p class="font-medium mb-2">Historique des commandes liées :</p>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border">
                                <thead>
                                    <tr>
                                        <th class="border px-4 py-2 bg-gray-50">Date</th>
                                        <th class="border px-4 py-2 bg-gray-50">Quantité</th>
                                        <th class="border px-4 py-2 bg-gray-50">Prix unitaire</th>
                                        <th class="border px-4 py-2 bg-gray-50">Fournisseur</th>
                                        <th class="border px-4 py-2 bg-gray-50">Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${ordersHtml}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="flex justify-center">
                        <button onclick="completePartialOrder(${material.id}, '${material.designation}', ${material.qt_restante}, '${material.unit || ''}')" 
                            class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded mr-2 flex items-center">
                            <span class="material-icons mr-1">add_shopping_cart</span>
                            Commander le restant
                        </button>
                    </div>
                    </div>
            `,
                    width: 800,
                    confirmButtonText: 'Fermer',
                    showClass: {
                        popup: 'animate__animated animate__fadeIn'
                    }
                });
            } else {
                Swal.fire({
                    title: 'Erreur',
                    text: data.message || 'Impossible de récupérer les détails de la commande',
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des détails:', error);
            Swal.fire({
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la récupération des détails',
                icon: 'error'
            });
        });
}

// 6. Fonction pour exporter les commandes partielles au format Excel
function exportPartialOrdersExcel() {
    window.location.href = 'commandes-traitement/api.php?action=export_remaining&format=excel';
}

// 7. Initialisation
document.addEventListener('DOMContentLoaded', function () {
    setupTabs();

    // Ajouter les écouteurs d'événements pour les boutons d'export et d'actualisation
    const exportBtn = document.getElementById('export-excel');
    if (exportBtn) {
        exportBtn.addEventListener('click', exportPartialOrdersExcel);
    }

    const refreshBtn = document.getElementById('refresh-list');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', loadPartialOrders);
    }

    // Si l'URL contient un paramètre tab=partial, ouvrir cet onglet automatiquement
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'partial') {
        const tabPartial = document.getElementById('tab-partial');
        if (tabPartial) {
            tabPartial.click();
        }
    }
});