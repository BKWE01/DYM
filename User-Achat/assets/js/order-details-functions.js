/**
 * FONCTIONS DE VISUALISATION DES DÉTAILS DES COMMANDES
 * Module pour afficher les détails des commandes, annulations et retours
 * Version: 1.0
 * Fichier: assets/js/order-details-functions.js
 * 
 * À placer dans: /DYM MANUFACTURE/expressions_besoins/User-Achat/assets/js/
 */

// ==========================================
// CONFIGURATION ET CONSTANTES
// ==========================================
const ORDER_DETAILS_CONFIG = {
    API_ENDPOINTS: {
        ORDER_DETAILS: 'api/orders/get_order_details.php',
        CANCELED_DETAILS: 'api_canceled/api_getCanceledOrderDetails.php', 
        RETURN_DETAILS: 'statistics/retour-fournisseur/get_return_details.php'
    },
    MODAL_IDS: {
        ORDER_DETAILS: 'order-details-modal',
        CANCELED_DETAILS: 'canceled-details-modal',
        RETURN_DETAILS: 'return-details-modal'
    }
};

// ==========================================
// FONCTION PRINCIPALE: DÉTAILS DE COMMANDE
// ==========================================

/**
 * Affiche les détails d'une commande reçue
 * @param {number} orderId - ID de la commande
 * @param {number} expressionId - ID de l'expression/projet
 * @param {string} designation - Désignation du matériel
 * @param {string} sourceTable - Table source (expression_dym ou besoins)
 */
window.viewOrderDetails = async (orderId, expressionId, designation, sourceTable = 'expression_dym') => {
    console.log('Affichage des détails - Order ID:', orderId, 'Expression ID:', expressionId, 'Designation:', designation);

    // Afficher un loader
    Swal.fire({
        title: 'Chargement...',
        text: 'Récupération des détails de la commande',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        // Construire l'URL avec les paramètres GET
        const params = new URLSearchParams();
        if (orderId && orderId !== '0' && orderId !== 0) {
            params.append('order_id', orderId);
        }
        if (expressionId) {
            params.append('expression_id', expressionId);
        }
        if (designation) {
            params.append('designation', designation);
        }
        if (sourceTable) {
            params.append('source_table', sourceTable);
        }

        const url = `${ORDER_DETAILS_CONFIG.API_ENDPOINTS.ORDER_DETAILS}?${params.toString()}`;
        console.log('URL API:', url);

        // Appel API avec méthode GET
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }

        const data = await response.json();
        
        // Fermer le loader
        Swal.close();

        if (data.success) {
            // Afficher la modal avec les détails
            showOrderDetailsModal(data.data);
        } else {
            throw new Error(data.message || 'Erreur lors de la récupération des données');
        }

    } catch (error) {
        console.error('Erreur lors de la récupération des détails:', error);
        Swal.fire({
            title: 'Erreur',
            text: 'Erreur lors de la récupération des détails: ' + error.message,
            icon: 'error'
        });
    }
};

// ==========================================
// FONCTION: DÉTAILS DES COMMANDES ANNULÉES
// ==========================================

/**
 * Affiche les détails d'une commande annulée
 * @param {number} orderId - ID de la commande annulée
 * @param {number} expressionId - ID de l'expression/projet
 * @param {string} designation - Désignation du matériel
 * @param {string} sourceTable - Table source
 */
window.viewCanceledOrderDetails = async (orderId, expressionId, designation, sourceTable = 'expression_dym') => {
    console.log('Affichage des détails annulation - Order ID:', orderId);

    // Afficher un loader
    Swal.fire({
        title: 'Chargement...',
        text: 'Récupération des détails de l\'annulation',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        // Construire l'URL avec le paramètre id (comme attendu par l'API)
        const url = `${ORDER_DETAILS_CONFIG.API_ENDPOINTS.CANCELED_DETAILS}?id=${orderId}`;
        console.log('URL API Canceled:', url);

        // Appel API avec méthode GET
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }

        const data = await response.json();
        
        Swal.close();

        if (data.success) {
            // L'API retourne un format différent avec 'order' au lieu de 'data'
            showCanceledOrderDetailsModal({
                canceled_order: data.order,
                reason: data.order.reason || 'Raison non spécifiée',
                materials: data.related_orders || []
            });
        } else {
            throw new Error(data.message || 'Erreur lors de la récupération des données d\'annulation');
        }

    } catch (error) {
        console.error('Erreur lors de la récupération des détails d\'annulation:', error);
        Swal.fire({
            title: 'Erreur',
            text: 'Erreur lors de la récupération des détails d\'annulation: ' + error.message,
            icon: 'error'
        });
    }
};

// ==========================================
// FONCTION: DÉTAILS DES RETOURS
// ==========================================

/**
 * Affiche les détails d'un retour de matériel
 * @param {number} returnId - ID du retour
 * @param {number} expressionId - ID de l'expression/projet
 * @param {string} designation - Désignation du matériel
 * @param {string} sourceTable - Table source
 */
window.viewReturnDetails = async (returnId, expressionId, designation, sourceTable = 'expression_dym') => {
    console.log('Affichage des détails retour - Return ID:', returnId);

    // Afficher un loader
    Swal.fire({
        title: 'Chargement...',
        text: 'Récupération des détails du retour',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        // Construire l'URL avec le paramètre id (comme attendu par l'API)
        const url = `${ORDER_DETAILS_CONFIG.API_ENDPOINTS.RETURN_DETAILS}?id=${returnId}`;
        console.log('URL API Return:', url);

        // Appel API avec méthode GET
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }

        const data = await response.json();
        
        Swal.close();

        if (data.success) {
            // L'API retourne un format avec 'data'
            showReturnDetailsModal({
                return_order: data.data,
                reason: data.data.reason || 'Raison non spécifiée',
                materials: [] // Pas de matériaux dans cette API, ou adapter selon vos besoins
            });
        } else {
            throw new Error(data.message || 'Erreur lors de la récupération des données de retour');
        }

    } catch (error) {
        console.error('Erreur lors de la récupération des détails de retour:', error);
        Swal.fire({
            title: 'Erreur',
            text: 'Erreur lors de la récupération des détails de retour: ' + error.message,
            icon: 'error'
        });
    }
};

// ==========================================
// FONCTIONS D'AFFICHAGE DES MODALES
// ==========================================

/**
 * Affiche la modal avec les détails de la commande
 * @param {Object} orderData - Données de la commande
 */
function showOrderDetailsModal(orderData) {
    const modal = document.getElementById(ORDER_DETAILS_CONFIG.MODAL_IDS.ORDER_DETAILS);
    const content = document.getElementById('order-details-content');

    if (!modal || !content || !orderData) {
        console.error('Modal ou données manquantes');
        return;
    }

    // Construire le contenu HTML
    const htmlContent = buildOrderDetailsHTML(orderData);
    content.innerHTML = htmlContent;

    // Afficher la modal
    modal.style.display = 'flex';
}

/**
 * Affiche la modal avec les détails de l'annulation
 * @param {Object} canceledData - Données de l'annulation
 */
function showCanceledOrderDetailsModal(canceledData) {
    const modal = document.getElementById(ORDER_DETAILS_CONFIG.MODAL_IDS.CANCELED_DETAILS);
    
    if (!modal) {
        // Créer dynamiquement la modal si elle n'existe pas
        createCanceledDetailsModal();
    }
    
    const content = document.getElementById('canceled-details-content');
    
    if (!content || !canceledData) {
        console.error('Modal ou données d\'annulation manquantes');
        return;
    }

    const htmlContent = buildCanceledDetailsHTML(canceledData);
    content.innerHTML = htmlContent;

    modal.style.display = 'flex';
}

/**
 * Affiche la modal avec les détails du retour
 * @param {Object} returnData - Données du retour
 */
function showReturnDetailsModal(returnData) {
    const modal = document.getElementById(ORDER_DETAILS_CONFIG.MODAL_IDS.RETURN_DETAILS);
    
    if (!modal) {
        // Créer dynamiquement la modal si elle n'existe pas
        createReturnDetailsModal();
    }
    
    const content = document.getElementById('return-details-content');
    
    if (!content || !returnData) {
        console.error('Modal ou données de retour manquantes');
        return;
    }

    const htmlContent = buildReturnDetailsHTML(returnData);
    content.innerHTML = htmlContent;

    modal.style.display = 'flex';
}

// ==========================================
// FONCTIONS DE CONSTRUCTION HTML
// ==========================================

/**
 * Construit le HTML pour les détails de la commande
 * @param {Object} data - Données de la commande
 * @returns {string} HTML formaté
 */
function buildOrderDetailsHTML(data) {
    const order = data.order;
    const materials = data.materials || [];
    const history = data.history || [];
    const sourceTable = data.source_table || 'expression_dym';

    // Calculer le total
    const total = materials.reduce((sum, item) => {
        return sum + (parseFloat(item.quantity || 0) * parseFloat(item.prix_unitaire || 0));
    }, 0);

    // Adapter l'affichage selon la source
    const isSystemRequest = sourceTable === 'besoins';
    const projectLabel = isSystemRequest ? 'Demande système' : 'Projet';
    const clientLabel = isSystemRequest ? 'Service demandeur' : 'Client';

    return `
        <div class="space-y-6">
            <!-- Badge de source -->
            <div class="flex justify-between items-center">
                <span class="px-3 py-1 text-xs font-medium rounded-full ${isSystemRequest ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                    ${isSystemRequest ? 'Demande Système' : 'Projet Client'}
                </span>
                <span class="text-sm text-gray-500">
                    Commande #${order.id || 'N/A'}
                </span>
            </div>

            <!-- Informations générales -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2">${projectLabel}</h3>
                    <p class="text-sm text-gray-600">Code: ${order.code_projet || 'N/A'}</p>
                    <p class="text-sm text-gray-600">${clientLabel}: ${order.nom_client || 'N/A'}</p>
                    <p class="text-sm text-gray-600">Date: ${formatDate(order.date_achat)}</p>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2">Fournisseur</h3>
                    <p class="text-sm text-gray-600">${order.fournisseur || 'Non spécifié'}</p>
                    <p class="text-sm text-gray-600">Statut: ${order.statut || 'En cours'}</p>
                    <p class="text-sm text-gray-600">Total: ${total.toFixed(2)} FCFA</p>
                </div>
            </div>

            <!-- Liste des matériaux -->
            <div>
                <h3 class="font-semibold text-gray-800 mb-3">Matériaux commandés</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unité</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            ${materials.map(item => `
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900">${item.designation || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900">${item.quantity || '0'}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900">${item.unit || 'U'}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900">${parseFloat(item.prix_unitaire || 0).toFixed(2)} FCFA</td>
                                    <td class="px-4 py-2 text-sm text-gray-900">${(parseFloat(item.quantity || 0) * parseFloat(item.prix_unitaire || 0)).toFixed(2)} FCFA</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Historique si disponible -->
            ${history.length > 0 ? `
            <div>
                <h3 class="font-semibold text-gray-800 mb-3">Historique</h3>
                <div class="space-y-2">
                    ${history.map(item => `
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <span class="text-sm text-gray-700">${item.action || 'Action'}</span>
                            <span class="text-xs text-gray-500">${formatDate(item.date)}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
            ` : ''}
        </div>
    `;
}

/**
 * Construit le HTML pour les détails d'annulation
 * @param {Object} data - Données de l'annulation
 * @returns {string} HTML formaté
 */
function buildCanceledDetailsHTML(data) {
    const canceledOrder = data.canceled_order;
    const reason = data.reason || canceledOrder.reason || 'Raison non spécifiée';
    const materials = data.materials || [];

    return `
        <div class="space-y-6">
            <!-- Header annulation -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h3 class="font-semibold text-red-800 mb-2">Commande Annulée</h3>
                <p class="text-sm text-red-600">Raison: ${reason}</p>
                <p class="text-sm text-red-600">Date d'annulation: ${formatDate(canceledOrder.canceled_at)}</p>
                <p class="text-sm text-red-600">Annulé par: ${canceledOrder.canceled_by_name || 'N/A'}</p>
            </div>

            <!-- Détails de la commande annulée -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2">Informations Projet</h3>
                    <p class="text-sm text-gray-600">Code: ${canceledOrder.code_projet || 'N/A'}</p>
                    <p class="text-sm text-gray-600">Client: ${canceledOrder.nom_client || 'N/A'}</p>
                    <p class="text-sm text-gray-600">Désignation: ${canceledOrder.designation || 'N/A'}</p>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2">Détails Commande</h3>
                    <p class="text-sm text-gray-600">Fournisseur: ${canceledOrder.fournisseur || 'N/A'}</p>
                    <p class="text-sm text-gray-600">Date commande: ${formatDate(canceledOrder.date_achat)}</p>
                    <p class="text-sm text-gray-600">Quantité: ${canceledOrder.quantity || '0'} ${canceledOrder.unit || ''}</p>
                    <p class="text-sm text-gray-600">Prix unitaire: ${parseFloat(canceledOrder.prix_unitaire || 0).toFixed(2)} FCFA</p>
                </div>
            </div>

            <!-- Matériaux liés si disponibles -->
            ${materials.length > 0 ? `
            <div>
                <h3 class="font-semibold text-gray-800 mb-3">Commandes Liées</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            ${materials.map(item => `
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900">${item.designation || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900">${item.quantity || '0'} ${item.unit || ''}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900">${parseFloat(item.prix_unitaire || 0).toFixed(2)} FCFA</td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        <span class="px-2 py-1 text-xs rounded-full ${item.status === 'reçu' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'}">
                                            ${item.status || 'En cours'}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900">${formatDate(item.date_achat)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
            ` : ''}

            <!-- Notes si disponibles -->
            ${canceledOrder.notes ? `
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 class="font-semibold text-yellow-800 mb-2">Notes</h3>
                <p class="text-sm text-yellow-700">${canceledOrder.notes}</p>
            </div>
            ` : ''}
        </div>
    `;
}

/**
 * Construit le HTML pour les détails de retour
 * @param {Object} data - Données du retour
 * @returns {string} HTML formaté
 */
function buildReturnDetailsHTML(data) {
    const returnOrder = data.return_order;
    const reason = data.reason || returnOrder.reason || 'Raison non spécifiée';
    const materials = data.materials || [];

    return `
        <div class="space-y-6">
            <!-- Header retour -->
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <h3 class="font-semibold text-orange-800 mb-2">Retour de Matériel</h3>
                <p class="text-sm text-orange-600">Raison: ${reason}</p>
                <p class="text-sm text-orange-600">Date de retour: ${formatDate(returnOrder.created_at)}</p>
                <p class="text-sm text-orange-600">Statut: ${returnOrder.status || 'En cours'}</p>
            </div>

            <!-- Détails du retour -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2">Informations Produit</h3>
                    <p class="text-sm text-gray-600">Produit: ${returnOrder.product_name || 'N/A'}</p>
                    <p class="text-sm text-gray-600">Quantité retournée: ${returnOrder.quantity_returned || '0'}</p>
                    <p class="text-sm text-gray-600">Prix unitaire: ${parseFloat(returnOrder.prix_unitaire || 0).toFixed(2)} FCFA</p>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2">Détails Retour</h3>
                    <p class="text-sm text-gray-600">Condition: ${returnOrder.condition || 'Non spécifiée'}</p>
                    <p class="text-sm text-gray-600">Remboursement: ${returnOrder.refund_amount ? parseFloat(returnOrder.refund_amount).toFixed(2) + ' FCFA' : 'N/A'}</p>
                    ${returnOrder.completed_at ? `<p class="text-sm text-gray-600">Complété le: ${formatDate(returnOrder.completed_at)}</p>` : ''}
                </div>
            </div>

            <!-- Valeur économisée -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="font-semibold text-blue-800 mb-2">Impact Financier</h3>
                <p class="text-sm text-blue-600">
                    Valeur retournée: ${(parseFloat(returnOrder.quantity_returned || 0) * parseFloat(returnOrder.prix_unitaire || 0)).toFixed(2)} FCFA
                </p>
            </div>

            <!-- Matériaux associés si disponibles -->
            ${materials.length > 0 ? `
            <div>
                <h3 class="font-semibold text-gray-800 mb-3">Matériaux Associés</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantité retournée</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">État</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            ${materials.map(item => `
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900">${item.designation || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900">${item.quantity_returned || '0'}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        <span class="px-2 py-1 text-xs rounded-full ${item.etat === 'bon' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                            ${item.etat || 'Non spécifié'}
                                        </span>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
            ` : ''}

            <!-- Notes si disponibles -->
            ${returnOrder.notes ? `
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 class="font-semibold text-yellow-800 mb-2">Notes</h3>
                <p class="text-sm text-yellow-700">${returnOrder.notes}</p>
            </div>
            ` : ''}
        </div>
    `;
}

// ==========================================
// FONCTIONS DE FERMETURE DES MODALES
// ==========================================

/**
 * Ferme la modal des détails de commande
 */
window.closeOrderDetailsModal = () => {
    const modal = document.getElementById(ORDER_DETAILS_CONFIG.MODAL_IDS.ORDER_DETAILS);
    if (modal) {
        modal.style.display = 'none';
    }
};

/**
 * Ferme la modal des détails d'annulation
 */
window.closeCanceledDetailsModal = () => {
    const modal = document.getElementById(ORDER_DETAILS_CONFIG.MODAL_IDS.CANCELED_DETAILS);
    if (modal) {
        modal.style.display = 'none';
    }
};

/**
 * Ferme la modal des détails de retour
 */
window.closeReturnDetailsModal = () => {
    const modal = document.getElementById(ORDER_DETAILS_CONFIG.MODAL_IDS.RETURN_DETAILS);
    if (modal) {
        modal.style.display = 'none';
    }
};

// ==========================================
// FONCTIONS UTILITAIRES
// ==========================================

/**
 * Formate une date pour l'affichage
 * @param {string} dateString - Date au format string
 * @returns {string} Date formatée
 */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return dateString;
    }
}

/**
 * Crée dynamiquement la modal pour les détails d'annulation
 */
function createCanceledDetailsModal() {
    const modalHTML = `
        <div id="${ORDER_DETAILS_CONFIG.MODAL_IDS.CANCELED_DETAILS}" class="modal">
            <div class="modal-content" style="max-width: 900px;">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Détails de l'annulation</h2>
                    <button onclick="closeCanceledDetailsModal()" class="text-gray-400 hover:text-gray-600">
                        <span class="material-icons">close</span>
                    </button>
                </div>
                <div id="canceled-details-content"></div>
                <div class="flex justify-end gap-3 mt-6">
                    <button onclick="closeCanceledDetailsModal()"
                        class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

/**
 * Crée dynamiquement la modal pour les détails de retour
 */
function createReturnDetailsModal() {
    const modalHTML = `
        <div id="${ORDER_DETAILS_CONFIG.MODAL_IDS.RETURN_DETAILS}" class="modal">
            <div class="modal-content" style="max-width: 900px;">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Détails du retour</h2>
                    <button onclick="closeReturnDetailsModal()" class="text-gray-400 hover:text-gray-600">
                        <span class="material-icons">close</span>
                    </button>
                </div>
                <div id="return-details-content"></div>
                <div class="flex justify-end gap-3 mt-6">
                    <button onclick="closeReturnDetailsModal()"
                        class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// ==========================================
// INITIALISATION
// ==========================================
console.log('Module order-details-functions.js chargé avec succès');