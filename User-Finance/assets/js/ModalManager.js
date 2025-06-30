/**
 * ModalManager.js
 * Gestionnaire des modales pour le service Finance - VERSION ULTRA CORRIGÉE
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

class ModalManager {
    constructor() {
        this.activeModal = null;
        this.modals = new Map();
        this.isInitialized = false;

        // Configuration des URLs
        this.config = {
            urls: {
                orderDetails: 'api/dashboard/get_bon_details_finance.php',
                downloadOrder: 'api/download_bon_commande.php',
                downloadValidated: 'api/download_validated_bon_commande.php'
            }
        };

        this.init();
    }

    /**
     * Initialisation du gestionnaire de modales
     */
    init() {
        if (this.isInitialized) return;

        this.bindEvents();
        this.cleanupExistingModals();
        this.forceInjectStyles();
        this.isInitialized = true;

        console.log('✅ ModalManager initialisé et nettoyé');
    }

    /**
     * Force l'injection des styles CSS avec priorité maximale
     */
    forceInjectStyles() {
        // Supprimer les anciens styles s'ils existent
        const existingStyle = document.getElementById('modal-manager-styles');
        if (existingStyle) {
            existingStyle.remove();
        }

        const style = document.createElement('style');
        style.id = 'modal-manager-styles';
        style.innerHTML = `
            /* STYLES MODALES FORCÉS */
            .modal {
                display: none !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                background-color: rgba(0, 0, 0, 0.8) !important;
                z-index: 999999 !important;
                justify-content: center !important;
                align-items: center !important;
                padding: 1rem !important;
                overflow-y: auto !important;
                backdrop-filter: blur(2px) !important;
            }
            
            .modal.show {
                display: flex !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            
            .modal-content {
                background: white !important;
                border-radius: 12px !important;
                padding: 2rem !important;
                max-width: 900px !important;
                width: 95% !important;
                max-height: 90vh !important;
                overflow-y: auto !important;
                position: relative !important;
                z-index: 1000000 !important;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3) !important;
                transform: scale(1) !important;
                opacity: 1 !important;
                margin: auto !important;
            }
            
            .modal-close {
                position: absolute !important;
                top: 1rem !important;
                right: 1rem !important;
                background: #f3f4f6 !important;
                border: none !important;
                font-size: 1.5rem !important;
                cursor: pointer !important;
                color: #374151 !important;
                z-index: 1000001 !important;
                width: 2rem !important;
                height: 2rem !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 50% !important;
                transition: all 0.2s ease !important;
            }
            
            .modal-close:hover {
                background-color: #e5e7eb !important;
                color: #111827 !important;
            }
            
            body.modal-open {
                overflow: hidden !important;
                position: fixed !important;
                width: 100% !important;
            }
        `;

        // Injecter en tant que premier élément dans le head pour priorité maximale
        document.head.insertBefore(style, document.head.firstChild);

        console.log('💄 Styles forcés injectés avec priorité maximale');
    }

    /**
     * Nettoie toutes les modales existantes
     */
    cleanupExistingModals() {
        const existingModals = document.querySelectorAll('.modal');
        existingModals.forEach(modal => {
            modal.remove();
        });

        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';

        console.log('🧹 Modales existantes supprimées');
    }

    /**
     * Liaison des événements des modales
     */
    bindEvents() {
        // Fermeture par le bouton X
        document.addEventListener('click', (e) => {
            if (e.target.matches('.modal-close') || e.target.closest('.modal-close')) {
                e.preventDefault();
                e.stopPropagation();
                this.close();
            }
        });

        // Fermeture par clic en dehors
        document.addEventListener('click', (e) => {
            if (e.target.matches('.modal') && !e.target.closest('.modal-content')) {
                this.close();
            }
        });

        // Fermeture par Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeModal) {
                this.close();
            }
        });

        console.log('🎯 Événements des modales liés');
    }

    /**
     * Crée une modal avec l'ID donné - VERSION ULTRA FORCÉE
     */
    createModal(modalId, title, content = '') {
        // Supprimer toute modal existante avec le même ID
        let modal = document.getElementById(modalId);
        if (modal) {
            modal.remove();
        }

        modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'modal';

        // FORCE les styles inline pour garantir l'affichage
        modal.style.cssText = `
            display: none !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background-color: rgba(0, 0, 0, 0.8) !important;
            z-index: 999999 !important;
            justify-content: center !important;
            align-items: center !important;
            padding: 1rem !important;
            overflow-y: auto !important;
        `;

        modal.innerHTML = `
            <div class="modal-content" style="
                background: white !important;
                border-radius: 12px !important;
                padding: 2rem !important;
                max-width: 900px !important;
                width: 95% !important;
                max-height: 90vh !important;
                overflow-y: auto !important;
                position: relative !important;
                z-index: 1000000 !important;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3) !important;
                margin: auto !important;
            ">
                <button class="modal-close" type="button" aria-label="Fermer" style="
                    position: absolute !important;
                    top: 1rem !important;
                    right: 1rem !important;
                    background: #f3f4f6 !important;
                    border: none !important;
                    font-size: 1.5rem !important;
                    cursor: pointer !important;
                    color: #374151 !important;
                    z-index: 1000001 !important;
                    width: 2rem !important;
                    height: 2rem !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    border-radius: 50% !important;
                ">
                    <span class="material-icons">close</span>
                </button>
                <div class="modal-header mb-4">
                    <h3 class="text-xl font-bold text-gray-800">${title}</h3>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
                <div class="modal-footer mt-6 flex justify-end space-x-2">
                    <button type="button" class="modal-close px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded font-medium">
                        
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        this.modals.set(modalId, modal);

        console.log(`✨ Modal créée avec styles inline forcés: ${modalId}`);
        return modal;
    }

    /**
     * Affiche les détails d'un bon de commande - VERSION ULTRA CORRIGÉE
     */
    async showOrderDetails(orderId) {
        try {
            console.log(`🔍 === DÉBUT AFFICHAGE DÉTAILS BON ID: ${orderId} ===`);

            // Fermer toute modal existante
            this.close();

            const modalId = 'order-details-modal';
            const modal = this.createModal(modalId, 'Détails du Bon de Commande');

            console.log('📦 Modal créée, affichage en cours...');

            // Afficher la modal d'abord avec le chargement
            this.setLoading(modal, true);
            this.forceShow(modal);

            console.log('👁️ Modal affichée, début de la requête API...');

            // Faire l'appel API
            const url = `${this.config.urls.orderDetails}?id=${orderId}`;
            console.log(`📡 URL API: ${url}`);

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });

            console.log(`📊 Statut réponse: ${response.status} ${response.statusText}`);

            if (!response.ok) {
                throw new Error(`Erreur HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('📄 Données reçues:', data);

            if (data.success && data.data) {
                console.log('✅ Données valides, remplissage de la modal...');
                this.populateOrderDetailsModal(modal, data.data, orderId);
                console.log('✅ Modal remplie avec succès');
            } else {
                throw new Error(data.message || 'Données invalides reçues de l\'API');
            }

            console.log('🎉 === FIN AFFICHAGE DÉTAILS BON ===');

        } catch (error) {
            console.error('❌ === ERREUR AFFICHAGE DÉTAILS ===');
            console.error('❌ Message:', error.message);
            console.error('❌ Stack:', error.stack);

            // Afficher l'erreur dans une modal simplifiée
            this.showSimpleError(`Erreur lors du chargement des détails: ${error.message}`);
        }
    }

    /**
     * Affiche une erreur dans une modal simple
     */
    showSimpleError(message) {
        const errorModal = this.createModal('error-modal', 'Erreur', `
            <div class="text-center py-8">
                <div class="text-red-500 text-6xl mb-4">
                    <span class="material-icons" style="font-size: 3rem;">error</span>
                </div>
                <h4 class="text-lg font-semibold text-gray-800 mb-2">Erreur</h4>
                <p class="text-gray-600">${message}</p>
            </div>
        `);

        this.forceShow(errorModal);
    }

    /**
     * Affichage de la modale de signature - NOUVELLE MÉTHODE
     */
    async showSignatureModal(orderId, orderNumber) {
        try {
            console.log(`✍️ === DÉBUT AFFICHAGE MODAL SIGNATURE ===`);
            console.log(`✍️ Bon ID: ${orderId}, Numéro: ${orderNumber}`);

            const modalId = 'signature-modal';
            const modal = this.createModal(modalId, 'Signature du Bon de Commande');

            this.setLoading(modal, true);
            this.forceShow(modal);

            // Charger les détails du bon pour la signature
            const url = `${this.config.urls.orderDetails}?id=${orderId}`;
            console.log(`📡 Chargement des détails pour signature: ${url}`);

            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success && data.data) {
                this.populateSignatureModal(modal, data.data, orderId, orderNumber);
                console.log('✅ Modal de signature remplie avec succès');
            } else {
                throw new Error(data.message || 'Erreur lors du chargement des détails');
            }

            return new Promise((resolve) => {
                // Configurer les boutons
                const signBtn = modal.querySelector('#confirm-sign-order');
                const cancelBtns = modal.querySelectorAll('.modal-close, #cancel-sign');

                if (signBtn) {
                    signBtn.addEventListener('click', () => {
                        console.log('✅ Signature confirmée par l\'utilisateur');
                        resolve(true);
                        this.close();
                    });
                }

                cancelBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        console.log('❌ Signature annulée par l\'utilisateur');
                        resolve(false);
                        this.close();
                    });
                });
            });

        } catch (error) {
            console.error('❌ Erreur showSignatureModal:', error);
            this.showSimpleError('Erreur lors du chargement des détails du bon de commande');
            return false;
        }
    }

    /**
     * Affiche la modal de rejet d'un bon de commande - NOUVELLE MÉTHODE
     */

    async showRejectModal(orderId, orderNumber) {
        try {
            console.log(`❌ === DÉBUT AFFICHAGE MODAL REJET ===`);
            console.log(`❌ Bon ID: ${orderId}, Numéro: ${orderNumber}`);

            // Fermer toute modal existante
            this.close();

            const modalId = 'reject-modal';
            const modal = this.createModal(modalId, 'Rejeter le Bon de Commande');

            this.setLoading(modal, true);
            this.forceShow(modal);

            // Charger les détails du bon pour le rejet
            const url = `${this.config.urls.orderDetails}?id=${orderId}`;
            console.log(`📡 Chargement des détails pour rejet: ${url}`);

            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success && data.data) {
                this.populateRejectModal(modal, data.data, orderId, orderNumber);
                console.log('✅ Modal de rejet remplie avec succès');
            } else {
                throw new Error(data.message || 'Erreur lors du chargement des détails');
            }

            return new Promise((resolve) => {
                // Configurer les boutons
                const rejectBtn = modal.querySelector('#confirm-reject-order');
                const cancelBtns = modal.querySelectorAll('.modal-close, #cancel-reject');

                if (rejectBtn) {
                    rejectBtn.addEventListener('click', () => {
                        const reason = modal.querySelector('#rejection-reason')?.value?.trim();
                        if (!reason) {
                            alert('Veuillez saisir une raison de rejet');
                            return;
                        }
                        console.log('❌ Rejet confirmé par l\'utilisateur avec raison:', reason);

                        // CORRECTION : Fermer la modal et résoudre immédiatement
                        this.close();
                        resolve({ confirmed: true, reason: reason });
                    });
                }

                cancelBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        console.log('✅ Rejet annulé par l\'utilisateur');
                        this.close();
                        resolve({ confirmed: false, reason: null });
                    });
                });
            });

        } catch (error) {
            console.error('❌ Erreur showRejectModal:', error);
            this.showSimpleError('Erreur lors du chargement des détails du bon de commande');
            return { confirmed: false, reason: null };
        }
    }

    /**
     * Affiche la modal de suppression définitive d'un bon de commande - NOUVELLE MÉTHODE
     */
    async showDeleteModal(orderId, orderNumber) {
        try {
            console.log(`🗑️ === DÉBUT AFFICHAGE MODAL SUPPRESSION ===`);
            console.log(`🗑️ Bon ID: ${orderId}, Numéro: ${orderNumber}`);

            // Fermer toute modal existante
            this.close();

            const modalId = 'delete-modal';
            const modal = this.createModal(modalId, 'Supprimer Définitivement le Bon de Commande');

            this.setLoading(modal, true);
            this.forceShow(modal);

            // Charger les détails du bon pour la suppression
            const url = `${this.config.urls.orderDetails}?id=${orderId}`;
            console.log(`📡 Chargement des détails pour suppression: ${url}`);

            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success && data.data) {
                this.populateDeleteModal(modal, data.data, orderId, orderNumber);
                console.log('✅ Modal de suppression remplie avec succès');
            } else {
                throw new Error(data.message || 'Erreur lors du chargement des détails');
            }

            return new Promise((resolve) => {
                // Configurer les boutons
                const deleteBtn = modal.querySelector('#confirm-delete-order');
                const cancelBtns = modal.querySelectorAll('.modal-close, #cancel-delete');

                if (deleteBtn) {
                    deleteBtn.addEventListener('click', () => {
                        const reason = modal.querySelector('#delete-reason')?.value?.trim();
                        if (!reason) {
                            alert('Veuillez saisir une raison de suppression');
                            return;
                        }
                        console.log('🗑️ Suppression confirmée par l\'utilisateur avec raison:', reason);

                        this.close();
                        resolve({ confirmed: true, reason: reason });
                    });
                }

                cancelBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        console.log('✅ Suppression annulée par l\'utilisateur');
                        this.close();
                        resolve({ confirmed: false, reason: null });
                    });
                });
            });

        } catch (error) {
            console.error('❌ Erreur showDeleteModal:', error);
            this.showSimpleError('Erreur lors du chargement des détails du bon de commande');
            return { confirmed: false, reason: null };
        }
    }

    /**
     * Affiche la modal de révocation d'un bon signé - NOUVELLE MÉTHODE
     * À ajouter dans la classe ModalManager
     */
    async showRevokeSignedModal(orderId, orderNumber) {
        try {
            console.log(`🔄 === DÉBUT AFFICHAGE MODAL RÉVOCATION BON SIGNÉ ===`);
            console.log(`🔄 Bon ID: ${orderId}, Numéro: ${orderNumber}`);

            // Fermer toute modal existante
            this.close();

            const modalId = 'revoke-signed-modal';
            const modal = this.createModal(modalId, 'Révoquer le Bon de Commande Signé');

            this.setLoading(modal, true);
            this.forceShow(modal);

            // Charger les détails du bon pour la révocation
            const url = `${this.config.urls.orderDetails}?id=${orderId}`;
            console.log(`📡 Chargement des détails pour révocation: ${url}`);

            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success && data.data) {
                this.populateRevokeSignedModal(modal, data.data, orderId, orderNumber);
                console.log('✅ Modal de révocation remplie avec succès');
            } else {
                throw new Error(data.message || 'Erreur lors du chargement des détails');
            }

            return new Promise((resolve) => {
                // Configurer les boutons
                const revokeBtn = modal.querySelector('#confirm-revoke-signed-order');
                const cancelBtns = modal.querySelectorAll('.modal-close, #cancel-revoke-signed');

                if (revokeBtn) {
                    revokeBtn.addEventListener('click', () => {
                        const reason = modal.querySelector('#revoke-signed-reason')?.value?.trim();
                        if (!reason) {
                            alert('Veuillez saisir une raison de révocation');
                            return;
                        }
                        console.log('🔄 Révocation confirmée par l\'utilisateur avec raison:', reason);

                        this.close();
                        resolve({ confirmed: true, reason: reason });
                    });
                }

                cancelBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        console.log('✅ Révocation annulée par l\'utilisateur');
                        this.close();
                        resolve({ confirmed: false, reason: null });
                    });
                });
            });

        } catch (error) {
            console.error('❌ Erreur showRevokeSignedModal:', error);
            this.showSimpleError('Erreur lors du chargement des détails du bon de commande');
            return { confirmed: false, reason: null };
        }
    }

    /**
     * Remplissage de la modal de révocation d'un bon signé avec les données
     * À ajouter dans la classe ModalManager
     */
    populateRevokeSignedModal(modal, orderData, orderId, orderNumber) {
        console.log('🔄 === REMPLISSAGE MODAL RÉVOCATION BON SIGNÉ ===');

        const order = orderData.order || orderData;
        const projects = orderData.projects || [];

        const content = `
        <div class="space-y-6">
            <!-- Alerte de révocation -->
            <div class="bg-orange-100 border-l-4 border-orange-500 p-4 rounded-lg">
                <div class="flex">
                    <div class="ml-3">
                        <p class="text-sm text-orange-800">
                            <span class="material-icons mr-2 align-middle text-orange-600">warning</span>
                            <strong>ATTENTION :</strong> Vous êtes sur le point de révoquer un bon de commande DÉJÀ SIGNÉ. 
                            Cette action annulera la signature Finance et déplacera le bon vers les bons rejetés.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Détails du bon signé à révoquer -->
            <div class="bg-gray-50 p-6 rounded-lg border border-orange-200">
                <h4 class="text-lg font-semibold mb-4 text-orange-800 flex items-center">
                    <span class="material-icons mr-2">assignment_turned_in</span>
                    Bon de commande signé à révoquer
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <p><strong class="text-gray-700">N° Bon:</strong> <span class="font-mono text-blue-600">${order.order_number}</span></p>
                        <p><strong class="text-gray-700">Fournisseur:</strong> <span class="text-gray-900">${order.fournisseur}</span></p>
                        <p><strong class="text-gray-700">Date de création:</strong> <span class="text-gray-900">${this.formatDate(order.generated_at)}</span></p>
                        <p><strong class="text-gray-700">Signé par:</strong> <span class="text-green-600 font-semibold">${order.finance_username || 'Finance'}</span></p>
                    </div>
                    <div class="space-y-2">
                        <p><strong class="text-gray-700">Montant:</strong> <span class="text-orange-600 font-bold text-lg">${this.formatAmount(order.montant_total)} FCFA</span></p>
                        <p><strong class="text-gray-700">Date de signature:</strong> <span class="text-green-600">${this.formatDate(order.signature_finance)}</span></p>
                        <div class="mt-2">
                            <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                                <span class="material-icons mr-1" style="font-size: 14px;">verified</span>
                                BON ACTUELLEMENT SIGNÉ
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Résumé des projets -->
            ${projects.length > 0 ? `
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <h4 class="text-md font-semibold mb-2 text-blue-800 flex items-center">
                        <span class="material-icons mr-2">folder_open</span>
                        Projet(s) qui seront affectés par la révocation
                    </h4>
                    <div class="text-sm text-blue-700">
                        ${this.buildProjectSummary(projects)}
                    </div>
                </div>
            ` : ''}
            
            <!-- Raison de la révocation - OBLIGATOIRE -->
            <div class="bg-white border-2 border-orange-300 p-6 rounded-lg">
                <h4 class="text-lg font-semibold mb-4 text-orange-800 flex items-center">
                    <span class="material-icons mr-2">report_problem</span>
                    Motif de la révocation (obligatoire)
                </h4>
                <div class="mb-4">
                    <textarea id="revoke-signed-reason" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                              rows="4" 
                              placeholder="Veuillez indiquer clairement les raisons de la révocation de ce bon de commande signé..."
                              required></textarea>
                    <p class="text-sm text-gray-600 mt-2">
                        Exemples : Erreur découverte après signature, annulation projet, modification fournisseur, etc.
                    </p>
                </div>
                
                <div class="bg-yellow-50 p-3 rounded-lg mb-4">
                    <p class="text-sm text-yellow-800">
                        <span class="material-icons mr-2 align-middle text-yellow-600">info</span>
                        <strong>Conséquences :</strong> La signature Finance sera annulée et le bon sera déplacé 
                        vers l'onglet "Bons rejetés" avec le motif de révocation.
                    </p>
                </div>
                
                <div class="flex justify-center space-x-4">
                    <button id="cancel-revoke-signed" 
                            class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg font-medium transition-colors">
                        <span class="material-icons mr-2 align-middle">cancel</span>
                        Annuler
                    </button>
                    <button id="confirm-revoke-signed-order" 
                            data-id="${orderId}" 
                            class="px-6 py-3 text-white rounded-lg font-medium transition-colors revoke-confirm-btn">
                        <span class="material-icons mr-2 align-middle">cancel</span>
                        Confirmer la révocation
                    </button>
                </div>
            </div>
        </div>
    `;

        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            modalBody.innerHTML = content;
            console.log('✅ Contenu de la modal de révocation injecté');
        }

        // Mettre à jour le footer
        const modalFooter = modal.querySelector('.modal-footer');
        if (modalFooter) {
            modalFooter.innerHTML = ''; // Vider le footer car les boutons sont dans le contenu
        }

        console.log('🔄 === FIN REMPLISSAGE MODAL RÉVOCATION BON SIGNÉ ===');
    }

    /**
     * Remplissage de la modal de suppression avec les données
     */
    populateDeleteModal(modal, orderData, orderId, orderNumber) {
        console.log('🗑️ === REMPLISSAGE MODAL SUPPRESSION ===');

        const order = orderData.order || orderData;
        const projects = orderData.projects || [];

        const content = `
            <div class="space-y-6">
                <!-- Alerte de suppression définitive -->
                <div class="bg-red-100 border-l-4 border-red-500 p-4 rounded-lg">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-red-800">
                                <span class="material-icons mr-2 align-middle text-red-600">warning</span>
                                <strong>ATTENTION :</strong> Vous êtes sur le point de supprimer DÉFINITIVEMENT ce bon de commande. 
                                Cette action est IRRÉVERSIBLE et toutes les données seront perdues à jamais.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Détails du bon à supprimer -->
                <div class="bg-gray-50 p-6 rounded-lg border border-red-200">
                    <h4 class="text-lg font-semibold mb-4 text-red-800 flex items-center">
                        <span class="material-icons mr-2">delete_forever</span>
                        Bon de commande à supprimer définitivement
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <p><strong class="text-gray-700">N° Bon:</strong> <span class="font-mono text-red-600">${order.order_number}</span></p>
                            <p><strong class="text-gray-700">Fournisseur:</strong> <span class="text-gray-900">${order.fournisseur}</span></p>
                            <p><strong class="text-gray-700">Date de création:</strong> <span class="text-gray-900">${this.formatDate(order.generated_at)}</span></p>
                        </div>
                        <div class="space-y-2">
                            <p><strong class="text-gray-700">Montant:</strong> <span class="text-red-600 font-bold text-lg">${this.formatAmount(order.montant_total)} FCFA</span></p>
                            <p><strong class="text-gray-700">Date de rejet:</strong> <span class="text-red-600">${this.formatDate(order.rejected_at)}</span></p>
                            <p><strong class="text-gray-700">Motif de rejet:</strong> <span class="text-gray-900">${order.rejection_reason || 'Non spécifié'}</span></p>
                        </div>
                    </div>
                </div>
                
                <!-- Résumé des projets -->
                ${projects.length > 0 ? `
                    <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                        <h4 class="text-md font-semibold mb-2 text-orange-800 flex items-center">
                            <span class="material-icons mr-2">folder_open</span>
                            Projet(s) qui seront affectés
                        </h4>
                        <div class="text-sm text-orange-700">
                            ${this.buildProjectSummary(projects)}
                        </div>
                    </div>
                ` : ''}
                
                <!-- Raison de la suppression - OBLIGATOIRE -->
                <div class="bg-white border-2 border-red-300 p-6 rounded-lg">
                    <h4 class="text-lg font-semibold mb-4 text-red-800 flex items-center">
                        <span class="material-icons mr-2">report_problem</span>
                        Motif de la suppression définitive (obligatoire)
                    </h4>
                    <div class="mb-4">
                        <textarea id="delete-reason" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" 
                                  rows="4" 
                                  placeholder="Veuillez indiquer clairement les raisons de la suppression définitive de ce bon de commande..."
                                  required></textarea>
                        <p class="text-sm text-gray-600 mt-2">
                            Exemples : Doublon confirmé, erreur de saisie majeure, annulation projet, etc.
                        </p>
                    </div>
                    
                    <div class="flex justify-center space-x-4">
                        <button id="cancel-delete" 
                                class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg font-medium transition-colors">
                            <span class="material-icons mr-2 align-middle">cancel</span>
                            Annuler
                        </button>
                        <button id="confirm-delete-order" 
                                data-id="${orderId}" 
                                class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                            <span class="material-icons mr-2 align-middle">delete_forever</span>
                            Confirmer la suppression
                        </button>
                    </div>
                </div>
            </div>
        `;

        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            modalBody.innerHTML = content;
            console.log('✅ Contenu de la modal de suppression injecté');
        }

        // Mettre à jour le footer
        const modalFooter = modal.querySelector('.modal-footer');
        if (modalFooter) {
            modalFooter.innerHTML = ''; // Vider le footer car les boutons sont dans le contenu
        }

        console.log('🗑️ === FIN REMPLISSAGE MODAL SUPPRESSION ===');
    }

    /**
     * Remplissage de la modal de rejet avec les données
     */
    populateRejectModal(modal, orderData, orderId, orderNumber) {
        console.log('❌ === REMPLISSAGE MODAL REJET ===');

        const order = orderData.order || orderData;
        const projects = orderData.projects || [];
        const materials = orderData.consolidated_materials || orderData.materials || [];
        const validationDetails = orderData.validation_details || {};

        const content = `
            <div class="space-y-6">
                <!-- Alerte de rejet -->
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-lg">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-red-700">
                                <span class="material-icons mr-2 align-middle text-red-600">warning</span>
                                <strong>Attention :</strong> Vous êtes sur le point de rejeter ce bon de commande. 
                                Cette action est irréversible et le bon sera retiré de la liste des bons en attente.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Détails du bon -->
                <div class="bg-gray-50 p-6 rounded-lg border">
                    <h4 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                        <span class="material-icons mr-2">assignment</span>
                        Détails du bon de commande à rejeter
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <p><strong class="text-gray-700">N° Bon:</strong> <span class="font-mono text-blue-600">${order.order_number}</span></p>
                            <p><strong class="text-gray-700">Fournisseur:</strong> <span class="text-gray-900">${order.fournisseur}</span></p>
                            <p><strong class="text-gray-700">Date de création:</strong> <span class="text-gray-900">${this.formatDate(order.generated_at)}</span></p>
                        </div>
                        <div class="space-y-2">
                            <p><strong class="text-gray-700">Montant:</strong> <span class="text-red-600 font-bold text-lg">${this.formatAmount(order.montant_total)} FCFA</span></p>
                            <p><strong class="text-gray-700">Mode de paiement:</strong> <span class="text-gray-900">${validationDetails.modePaiement || 'Non spécifié'}</span></p>
                            <p><strong class="text-gray-700">Créé par:</strong> <span class="text-gray-900">${order.username_creation || validationDetails.validated_by_name || 'Non renseigné'}</span></p>
                        </div>
                    </div>
                </div>
                
                <!-- Résumé des projets -->
                ${projects.length > 0 ? `
                    <div class="bg-blue-50 p-4 rounded-lg border">
                        <h4 class="text-md font-semibold mb-2 text-blue-800 flex items-center">
                            <span class="material-icons mr-2">folder_open</span>
                            Projet(s) concerné(s)
                        </h4>
                        <div class="text-sm text-blue-700">
                            ${this.buildProjectSummary(projects)}
                        </div>
                    </div>
                ` : ''}
                
                <!-- Raison du rejet - OBLIGATOIRE -->
                <div class="bg-white border-2 border-red-200 p-6 rounded-lg">
                    <h4 class="text-lg font-semibold mb-4 text-red-800 flex items-center">
                        <span class="material-icons mr-2">report_problem</span>
                        Motif du rejet (obligatoire)
                    </h4>
                    <div class="mb-4">
                        <textarea id="rejection-reason" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" 
                                  rows="4" 
                                  placeholder="Veuillez indiquer clairement les raisons du rejet de ce bon de commande..."
                                  required></textarea>
                        <p class="text-sm text-gray-600 mt-2">
                            Exemples : Budget insuffisant, fournisseur non approuvé, prix trop élevé, documentation manquante, etc.
                        </p>
                    </div>
                    
                    <div class="flex justify-center space-x-4">
                        <button id="cancel-reject" 
                                class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg font-medium transition-colors">
                            <span class="material-icons mr-2 align-middle">cancel</span>
                            Annuler
                        </button>
                        <button id="confirm-reject-order" 
                                data-id="${orderId}" 
                                class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                            <span class="material-icons mr-2 align-middle">block</span>
                            Confirmer le rejet
                        </button>
                    </div>
                </div>
            </div>
        `;

        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            modalBody.innerHTML = content;
            console.log('✅ Contenu de la modal de rejet injecté');
        }

        // Mettre à jour le footer
        const modalFooter = modal.querySelector('.modal-footer');
        if (modalFooter) {
            modalFooter.innerHTML = ''; // Vider le footer car les boutons sont dans le contenu
        }

        console.log('❌ === FIN REMPLISSAGE MODAL REJET ===');
    }

    /**
     * Remplissage de la modale de signature avec les données
     */
    populateSignatureModal(modal, orderData, orderId, orderNumber) {
        console.log('✍️ === REMPLISSAGE MODAL SIGNATURE ===');

        const order = orderData.order || orderData;
        const projects = orderData.projects || [];
        const materials = orderData.consolidated_materials || orderData.materials || [];
        const validationDetails = orderData.validation_details || {};

        const content = `
            <div class="space-y-6">
                <!-- Alerte de confirmation -->
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <span class="material-icons mr-2 align-middle text-yellow-600">warning</span>
                                <strong>Attention :</strong> Vous êtes sur le point de signer ce bon de commande. 
                                Cette action est irréversible.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Détails du bon -->
                <div class="bg-gray-50 p-6 rounded-lg border">
                    <h4 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                        <span class="material-icons mr-2">assignment</span>
                        Détails du bon de commande
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <p><strong class="text-gray-700">N° Bon:</strong> <span class="font-mono text-blue-600">${order.order_number}</span></p>
                            <p><strong class="text-gray-700">Fournisseur:</strong> <span class="text-gray-900">${order.fournisseur}</span></p>
                            <p><strong class="text-gray-700">Date de création:</strong> <span class="text-gray-900">${this.formatDate(order.generated_at)}</span></p>
                        </div>
                        <div class="space-y-2">
                            <p><strong class="text-gray-700">Montant:</strong> <span class="text-green-600 font-bold text-lg">${this.formatAmount(order.montant_total)} FCFA</span></p>
                            <p><strong class="text-gray-700">Mode de paiement:</strong> <span class="text-gray-900">${validationDetails.modePaiement || 'Non spécifié'}</span></p>
                            <p><strong class="text-gray-700">Validé par:</strong> <span class="text-gray-900">${order.username_creation || validationDetails.validated_by_name || 'Non renseigné'}</span></p>
                        </div>
                    </div>
                </div>
                
                <!-- Résumé des projets -->
                ${projects.length > 0 ? `
                    <div class="bg-blue-50 p-4 rounded-lg border">
                        <h4 class="text-md font-semibold mb-2 text-blue-800 flex items-center">
                            <span class="material-icons mr-2">folder_open</span>
                            Projet(s) concerné(s)
                        </h4>
                        <div class="text-sm text-blue-700">
                            ${this.buildProjectSummary(projects)}
                        </div>
                    </div>
                ` : ''}
                
                <!-- Résumé des matériaux -->
                ${materials.length > 0 ? `
                    <div class="bg-green-50 p-4 rounded-lg border">
                        <h4 class="text-md font-semibold mb-2 text-green-800 flex items-center">
                            <span class="material-icons mr-2">inventory_2</span>
                            Matériaux (${materials.length} article${materials.length > 1 ? 's' : ''})
                        </h4>
                        <div class="text-sm text-green-700 max-h-32 overflow-y-auto">
                            ${materials.map(m => `• ${m.designation} (${m.quantity} ${m.unit})`).join('<br>')}
                        </div>
                    </div>
                ` : ''}
                
                <!-- Confirmation de signature -->
                <div class="bg-white border-2 border-green-200 p-6 rounded-lg">
                    <h4 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
                        <span class="material-icons mr-2">verified_user</span>
                        Confirmation de signature
                    </h4>
                    <p class="text-gray-700 mb-4">
                        En cliquant sur "Confirmer la signature", vous certifiez avoir vérifié 
                        tous les éléments de ce bon de commande et l'approuver au nom du service Finance.
                    </p>
                    
                    <div class="flex justify-center space-x-4">
                        <button id="cancel-sign" 
                                class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg font-medium transition-colors">
                            <span class="material-icons mr-2 align-middle">cancel</span>
                            Annuler
                        </button>
                        <button id="confirm-sign-order" 
                                data-id="${orderId}" 
                                class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                            <span class="material-icons mr-2 align-middle">check_circle</span>
                            Confirmer la signature
                        </button>
                    </div>
                </div>
            </div>
        `;

        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            modalBody.innerHTML = content;
            console.log('✅ Contenu de la modal de signature injecté');
        }

        // Mettre à jour le footer
        const modalFooter = modal.querySelector('.modal-footer');
        if (modalFooter) {
            modalFooter.innerHTML = ''; // Vider le footer car les boutons sont dans le contenu
        }

        console.log('✍️ === FIN REMPLISSAGE MODAL SIGNATURE ===');
    }

    /**
     * Construit un résumé des projets pour la modal de signature
     */
    buildProjectSummary(projects) {
        if (!projects || projects.length === 0) {
            return 'Aucun projet associé';
        }

        // Éliminer les doublons
        const uniqueProjects = new Map();
        projects.forEach(project => {
            const key = `${project.idExpression || 'N/A'}-${project.code_projet || 'N/A'}`;
            if (!uniqueProjects.has(key)) {
                uniqueProjects.set(key, project);
            }
        });

        const uniqueProjectsArray = Array.from(uniqueProjects.values());

        return uniqueProjectsArray.map(p =>
            `• <strong>${p.code_projet || 'N/A'}</strong> - ${p.nom_client || 'N/A'}`
        ).join('<br>');
    }

    /**
         * Affichage de la modale des bons signés - VERSION CORRIGÉE
         */
    showViewSignedModal(bonId) {
        console.log(`📋 Affichage du bon signé ID: ${bonId}`);

        const modalId = 'view-signed-modal';
        const modal = this.createModal(modalId, 'Bon de Commande Signé');

        // CORRECTION : Utiliser le bon chemin vers l'API
        const previewUrl = `../User-Achat/gestion-bon-commande/api/download_validated_bon_commande.php?id=${bonId}`;

        const content = `
            <div class="space-y-4">
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                    <p class="text-blue-700">
                        <span class="material-icons mr-2 align-middle">info</span>
                        Visualisation du bon de commande validé et signé
                    </p>
                </div>
                
                <div id="signed-bon-preview" class="border border-gray-200 rounded-lg overflow-hidden min-h-[500px] bg-white">
                    <iframe src="${previewUrl}" 
                            width="100%" 
                            height="500" 
                            style="border:none;" 
                            title="Aperçu du bon signé">
                    </iframe>
                </div>
                
                <div class="flex justify-center">
                    <button id="download-signed" 
                            data-id="${bonId}" 
                            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        <span class="material-icons mr-2 align-middle">file_download</span>
                        Télécharger le PDF signé
                    </button>
                </div>
            </div>
        `;

        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            modalBody.innerHTML = content;
        }

        // Lier l'événement de téléchargement
        const downloadBtn = modal.querySelector('#download-signed');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => {
                window.open(previewUrl, '_blank');
                console.log(`📥 Téléchargement initié pour le bon ${bonId}`);
            });
        }

        this.forceShow(modal);
    }

    /**
     * Remplit la modal avec les détails du bon de commande - VERSION AVEC BADGE REJET
     */
    populateOrderDetailsModal(modal, orderData, orderId) {
        console.log('🔧 === DÉBUT REMPLISSAGE MODAL AVEC DÉTECTION REJET ===');
        console.log('🔧 Données reçues:', orderData);

        const order = orderData.order || orderData;
        const projects = orderData.projects || [];
        const materials = orderData.consolidated_materials || orderData.materials || [];
        const validationDetails = orderData.validation_details || {};

        console.log('🔧 Order:', order);
        console.log('🔧 Status du bon:', order.status);
        console.log('🔧 Rejected_at:', order.rejected_at);
        console.log('🔧 Rejection_reason:', order.rejection_reason);

        // NOUVEAU : Détecter si le bon est rejeté
        const isRejected = order.status === 'rejected' || order.rejected_at !== null;
        const rejectionReason = order.rejection_reason || 'Motif non spécifié';
        const rejectedDate = order.rejected_at ? this.formatDate(order.rejected_at) : null;
        const rejectedBy = order.rejected_by_username || 'Finance';

        const content = `
        <div class="bg-white rounded-lg border border-gray-200 mb-6 overflow-hidden">
            <!-- NOUVEAU : Badge de rejet si applicable -->
            ${isRejected ? `
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <span class="material-icons text-red-500 text-2xl">cancel</span>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-bold text-red-800 flex items-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 mr-3">
                                    <span class="material-icons mr-1" style="font-size: 16px;">block</span>
                                    BON DE COMMANDE REJETÉ
                                </span>
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <p><strong>Date de rejet :</strong> ${rejectedDate || 'Non spécifiée'}</p>
                                <p><strong>Rejeté par :</strong> ${rejectedBy}</p>
                                <p><strong>Motif du rejet :</strong></p>
                                <div class="bg-red-100 p-3 rounded-md mt-2 border border-red-200">
                                    <p class="text-red-800 font-medium">${rejectionReason}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            ` : ''}
            
            <!-- En-tête du bon -->
            <div class="bg-gradient-to-r ${isRejected ? 'from-red-50 to-red-100' : 'from-blue-50 to-indigo-50'} p-6 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">N° Bon de commande:</p>
                        <p class="font-bold text-2xl ${isRejected ? 'text-red-800' : 'text-blue-800'}">${order.order_number || 'N/A'}</p>
                        ${isRejected ? `
                            <div class="mt-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <span class="material-icons mr-1" style="font-size: 12px;">error</span>
                                    REJETÉ
                                </span>
                            </div>
                        ` : ''}
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-600 mb-1">Date de génération:</p>
                        <p class="font-bold text-lg text-gray-800">${this.formatDate(order.generated_at)}</p>
                        ${isRejected && rejectedDate ? `
                            <p class="text-sm font-medium text-red-600 mt-2">Rejeté le: ${rejectedDate}</p>
                        ` : ''}
                    </div>
                </div>
            </div>
            
            <!-- Informations principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                <!-- Fournisseur -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-bold text-lg uppercase mb-2 text-gray-800">
                        <span class="material-icons mr-2 align-middle">business</span>
                        FOURNISSEUR
                    </h3>
                    <p class="font-medium text-xl text-gray-700">${order.fournisseur || 'N/A'}</p>
                </div>
                
                <!-- Montant -->
                <div class="bg-${isRejected ? 'red' : 'green'}-50 p-4 rounded-lg border-l-4 border-${isRejected ? 'red' : 'green'}-400">
                    <h3 class="font-bold text-lg uppercase mb-2 text-${isRejected ? 'red' : 'green'}-800">
                        <span class="material-icons mr-2 align-middle">payments</span>
                        MONTANT TOTAL
                    </h3>
                    <p class="font-bold text-3xl text-${isRejected ? 'red' : 'green'}-600">${this.formatAmount(order.montant_total)} FCFA</p>
                    ${isRejected ? `<p class="text-sm text-red-600 mt-1 font-medium">Montant non validé (bon rejeté)</p>` : ''}
                </div>
            </div>
            
            <!-- Type de commande -->
            <div class="px-6 pb-4">
                <div class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium ${order.is_multi_project == 1 ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}">
                    <span class="material-icons mr-2" style="font-size: 1rem;">
                        ${order.is_multi_project == 1 ? 'apps' : 'assignment'}
                    </span>
                    ${order.is_multi_project == 1 ? 'COMMANDE MULTI-PROJETS' : 'PROJET UNIQUE'}
                </div>
                ${isRejected ? `
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-red-100 text-red-800 ml-2">
                        <span class="material-icons mr-2" style="font-size: 1rem;">cancel</span>
                        COMMANDE REJETÉE
                    </span>
                ` : ''}
            </div>
            
            <!-- Projets -->
            <div class="p-6 border-t border-gray-200">
                <h3 class="font-bold text-lg uppercase mb-4 text-gray-800 flex items-center">
                    <span class="material-icons mr-2">folder_open</span>
                    PROJET(S) CONCERNÉ(S)
                    ${isRejected ? `<span class="ml-2 text-red-600 text-sm">(Projets affectés par le rejet)</span>` : ''}
                </h3>
                ${this.buildProjectInfo(projects)}
            </div>
            
            <!-- Matériaux -->
            <div class="p-6 border-t border-gray-200">
                <h3 class="font-bold text-lg uppercase mb-4 text-gray-800 flex items-center">
                    <span class="material-icons mr-2">inventory_2</span>
                    LISTE DES MATÉRIAUX
                    ${isRejected ? `<span class="ml-2 text-red-600 text-sm">(Matériaux non commandés)</span>` : ''}
                </h3>
                ${this.buildMaterialsTable(materials, isRejected)}
            </div>
            
            <!-- Mode de paiement -->
            <div class="p-6 border-t border-gray-200">
                <h3 class="font-bold text-lg uppercase mb-2 text-gray-800 flex items-center">
                    <span class="material-icons mr-2">credit_card</span>
                    MODE DE PAIEMENT
                </h3>
                <p class="font-medium text-lg ${isRejected ? 'text-red-600' : ''}">${validationDetails.modePaiement || 'Non spécifié'}</p>
                ${isRejected ? `<p class="text-sm text-red-600 mt-1">Mode de paiement annulé suite au rejet</p>` : ''}
            </div>
            
            <!-- Signatures -->
            <div class="p-6 border-t border-gray-200">
                <h3 class="font-bold text-lg uppercase mb-4 text-gray-800 flex items-center">
                    <span class="material-icons mr-2">how_to_reg</span>
                    SIGNATURES
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border-2 border-blue-200 p-4 rounded-lg bg-blue-50">
                        <p class="font-medium text-blue-800 mb-1">Service achat:</p>
                        <p class="text-gray-700 font-semibold">${order.username_creation || validationDetails.validated_by_name || 'N/A'}</p>
                    </div>
                    <div class="border-2 ${isRejected ? 'border-red-200 bg-red-50' : (order.signature_finance ? 'border-green-200 bg-green-50' : 'border-orange-200 bg-orange-50')} p-4 rounded-lg">
                        <p class="font-medium ${isRejected ? 'text-red-800' : (order.signature_finance ? 'text-green-800' : 'text-orange-800')} mb-1">Service finance:</p>
                        ${isRejected ? `
                            <p class="text-red-700 font-semibold">BON REJETÉ</p>
                            <p class="text-sm text-red-600 mt-1">Rejeté le: ${rejectedDate || 'Date inconnue'}</p>
                            <p class="text-sm text-red-600">Par: ${rejectedBy}</p>
                        ` : `
                            <p class="text-gray-700 font-semibold">${order.finance_username || 'Non signé'}</p>
                            ${order.signature_finance ? `<p class="text-sm text-gray-600 mt-1">Signé le: ${this.formatDate(order.signature_finance)}</p>` : ''}
                        `}
                    </div>
                </div>
            </div>

            <!-- NOUVEAU : Section détaillée du rejet (si rejeté) -->
            ${isRejected ? `
                <div class="p-6 border-t border-red-200 bg-red-50">
                    <h3 class="font-bold text-lg uppercase mb-4 text-red-800 flex items-center">
                        <span class="material-icons mr-2">report_problem</span>
                        DÉTAILS DU REJET
                    </h3>
                    <div class="bg-white border border-red-200 rounded-lg p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-sm font-medium text-red-700">Date du rejet:</p>
                                <p class="font-semibold text-red-800">${rejectedDate || 'Non spécifiée'}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-red-700">Rejeté par:</p>
                                <p class="font-semibold text-red-800">${rejectedBy}</p>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-red-700 mb-2">Motif détaillé du rejet:</p>
                            <div class="bg-red-100 border border-red-300 rounded-md p-3">
                                <p class="text-red-800 whitespace-pre-wrap">${rejectionReason}</p>
                            </div>
                        </div>
                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                            <p class="text-yellow-800 text-sm">
                                <span class="material-icons mr-2 align-middle text-yellow-600">info</span>
                                <strong>Note:</strong> Ce bon de commande a été rejeté et ne sera pas traité. 
                                Les matériaux listés ne seront pas commandés et les montants ne seront pas engagés.
                            </p>
                        </div>
                    </div>
                </div>
            ` : ''}
        </div>
    `;

        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            modalBody.innerHTML = content;
            console.log('✅ Contenu de la modal injecté avec détection du rejet');
        } else {
            console.error('❌ Impossible de trouver .modal-body');
        }

        // Ajouter boutons de téléchargement
        this.addDownloadButtons(modal, order);

        console.log('🔧 === FIN REMPLISSAGE MODAL AVEC DÉTECTION REJET ===');
    }

    /**
         * Construit les informations de projet - VERSION AVEC REGROUPEMENT
         */
    buildProjectInfo(projects) {
        console.log('🏗️ Construction des infos projets:', projects);

        if (!projects || projects.length === 0) {
            return '<p class="text-gray-500 italic">Aucune information de projet disponible</p>';
        }

        // REGROUPEMENT : Éliminer les doublons en utilisant code_projet comme clé unique
        const uniqueProjects = new Map();
        projects.forEach(project => {
            const key = `${project.code_projet || 'N/A'}`;

            if (!uniqueProjects.has(key)) {
                // Premier projet avec ce code
                uniqueProjects.set(key, {
                    ...project,
                    descriptions: [project.description_projet || 'Aucune description']
                });
            } else {
                // Projet existant - ajouter la description si différente
                const existing = uniqueProjects.get(key);
                const newDescription = project.description_projet || 'Aucune description';

                if (!existing.descriptions.includes(newDescription)) {
                    existing.descriptions.push(newDescription);
                }
            }
        });

        const uniqueProjectsArray = Array.from(uniqueProjects.values());
        console.log('🏗️ Projets regroupés:', uniqueProjectsArray);

        if (uniqueProjectsArray.length === 0) {
            return '<p class="text-gray-500 italic">Aucune information de projet disponible</p>';
        }

        return `
            <div class="space-y-3">
                ${uniqueProjectsArray.map(p => `
                    <div class="bg-gray-50 p-4 rounded-lg border-l-4 border-blue-400">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h4 class="font-semibold text-lg text-gray-800">${p.code_projet || 'N/A'}</h4>
                                <p class="text-gray-600 font-medium">${p.nom_client || 'N/A'}</p>
                                
                                <!-- DESCRIPTIONS REGROUPÉES -->
                                <div class="mt-2">
                                    ${p.descriptions.map(desc => `
                                        <p class="text-sm text-gray-500 mb-1">• ${desc}</p>
                                    `).join('')}
                                </div>
                            </div>
                            <div class="text-right ml-4">
                                <span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">
                                    ${p.chefprojet || 'N/A'}
                                </span>
                                <!-- Indicateur si plusieurs descriptions -->
                                ${p.descriptions.length > 1 ? `
                                    <div class="mt-1">
                                        <span class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">
                                            ${p.descriptions.length} éléments
                                        </span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    /**
     * Construit le tableau des matériaux - VERSION AVEC INDICATION REJET
     */
    buildMaterialsTable(materials, isRejected = false) {
        console.log('📋 Construction du tableau matériaux avec statut rejet:', isRejected);

        if (!materials || materials.length === 0) {
            return `<p class="text-gray-500 italic">Aucun matériau disponible</p>`;
        }

        const total = materials.reduce((sum, m) => {
            const qty = parseFloat(m.quantity || m.qt_acheter || 0);
            const price = parseFloat(m.prix_unitaire || 0);
            return sum + (qty * price);
        }, 0);

        const rows = materials.map((m, index) => {
            const qty = parseFloat(m.quantity || m.qt_acheter || 0);
            const price = parseFloat(m.prix_unitaire || 0);
            const itemTotal = qty * price;

            return `
            <tr class="hover:bg-gray-50 ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'} ${isRejected ? 'opacity-75' : ''}">
                <td class="px-4 py-3 text-sm font-medium ${isRejected ? 'text-red-700 line-through' : 'text-gray-900'}">${m.designation || 'N/A'}</td>
                <td class="px-4 py-3 text-sm text-center font-semibold ${isRejected ? 'text-red-600' : 'text-blue-600'}">${qty.toFixed(2)}</td>
                <td class="px-4 py-3 text-sm text-center text-gray-600">${m.unit || 'N/A'}</td>
                <td class="px-4 py-3 text-sm text-right font-medium ${isRejected ? 'text-red-700' : 'text-gray-800'}">${this.formatAmount(price)} FCFA</td>
                <td class="px-4 py-3 text-sm text-right font-bold ${isRejected ? 'text-red-600' : 'text-green-600'}">${this.formatAmount(itemTotal)} FCFA</td>
            </tr>
        `;
        }).join('');

        return `
        <div class="overflow-x-auto bg-white rounded-lg shadow ${isRejected ? 'border-2 border-red-200' : ''}">
            ${isRejected ? `
                <div class="bg-red-50 p-3 border-b border-red-200">
                    <p class="text-red-800 text-sm font-medium flex items-center">
                        <span class="material-icons mr-2 text-red-600">cancel</span>
                        Les matériaux suivants n'ont PAS été commandés suite au rejet du bon de commande
                    </p>
                </div>
            ` : ''}
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100 ${isRejected ? 'bg-red-50' : ''}">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium ${isRejected ? 'text-red-700' : 'text-gray-700'} uppercase tracking-wider">Désignation</th>
                        <th class="px-4 py-3 text-center text-xs font-medium ${isRejected ? 'text-red-700' : 'text-gray-700'} uppercase tracking-wider">Quantité</th>
                        <th class="px-4 py-3 text-center text-xs font-medium ${isRejected ? 'text-red-700' : 'text-gray-700'} uppercase tracking-wider">Unité</th>
                        <th class="px-4 py-3 text-right text-xs font-medium ${isRejected ? 'text-red-700' : 'text-gray-700'} uppercase tracking-wider">Prix Unitaire</th>
                        <th class="px-4 py-3 text-right text-xs font-medium ${isRejected ? 'text-red-700' : 'text-gray-700'} uppercase tracking-wider">Montant Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${rows}
                </tbody>
                <tfoot class="bg-gray-50 ${isRejected ? 'bg-red-50' : ''}">
                    <tr class="font-bold">
                        <td colspan="4" class="px-4 py-3 text-right text-sm uppercase ${isRejected ? 'text-red-800' : 'text-gray-800'}">
                            ${isRejected ? 'TOTAL NON ENGAGÉ' : 'TOTAL GÉNÉRAL'}
                        </td>
                        <td class="px-4 py-3 text-right text-lg font-bold ${isRejected ? 'text-red-600 line-through' : 'text-green-600'}">${this.formatAmount(total)} FCFA</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    `;
    }

    /**
         * Ajoute les boutons de téléchargement - VERSION CORRIGÉE
         */
    addDownloadButtons(modal, order) {
        const modalFooter = modal.querySelector('.modal-footer');
        if (!modalFooter) return;

        let downloadButtons = '';

        // CORRECTION : Corriger le chemin vers le bon répertoire
        if (order.file_path) {
            // Nettoyer et corriger le chemin
            let correctPath = order.file_path;

            // Si le chemin commence par "purchase_orders/", ajouter le bon préfixe
            if (correctPath.startsWith('purchase_orders/')) {
                correctPath = '../User-Achat/gestion-bon-commande/' + correctPath;
            }
            // Si le chemin est relatif sans préfixe, l'ajouter
            else if (!correctPath.startsWith('http') && !correctPath.startsWith('/') && !correctPath.startsWith('../')) {
                correctPath = '../User-Achat/gestion-bon-commande/' + correctPath;
            }

            downloadButtons += `
                <a href="${correctPath}" target="_blank" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-md font-medium hover:bg-blue-700 inline-flex items-center">
                    <span class="material-icons mr-2">file_download</span>
                    Télécharger PDF Original
                </a>
            `;
        }

        // Bouton de téléchargement du bon validé (si signé)
        if (order.signature_finance && order.user_finance_id) {
            downloadButtons += `
                <a href="../User-Achat/gestion-bon-commande/api/download_validated_bon_commande.php?id=${order.id}" target="_blank" 
                   class="px-4 py-2 bg-green-600 text-white rounded-md font-medium hover:bg-green-700 inline-flex items-center">
                    <span class="material-icons mr-2">verified</span>
                    Télécharger Version Signée
                </a>
            `;
        }

        if (downloadButtons) {
            modalFooter.insertAdjacentHTML('afterbegin', downloadButtons);
        }

        console.log('📥 Boutons de téléchargement ajoutés avec chemins corrigés');
    }


    /**
     * Force l'affichage d'une modal - VERSION ULTRA AGRESSIVE
     */
    forceShow(modal) {
        if (!modal) {
            console.error('❌ Tentative d\'affichage d\'une modal nulle');
            return;
        }

        console.log('👁️ === DÉBUT AFFICHAGE MODAL FORCÉ ===');

        // Fermer toute modal active d'abord
        this.close();

        // Préparer le body de manière agressive
        document.body.classList.add('modal-open');
        document.body.style.cssText = `
            overflow: hidden !important;
            position: fixed !important;
            width: 100% !important;
        `;

        // FORCE l'affichage avec tous les moyens possibles
        modal.style.cssText = `
            display: flex !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background-color: rgba(0, 0, 0, 0.8) !important;
            z-index: 999999 !important;
            justify-content: center !important;
            align-items: center !important;
            padding: 1rem !important;
            overflow-y: auto !important;
            visibility: visible !important;
            opacity: 1 !important;
        `;

        // Ajouter la classe show
        modal.classList.add('show');

        // Définir comme modal active
        this.activeModal = modal;

        // Logs de débogage
        console.log('👁️ Modal définie comme active:', modal.id);
        console.log('👁️ Classes de la modal:', modal.className);
        console.log('👁️ Style computed display:', window.getComputedStyle(modal).display);
        console.log('👁️ Style computed visibility:', window.getComputedStyle(modal).visibility);
        console.log('👁️ Style computed z-index:', window.getComputedStyle(modal).zIndex);
        console.log('👁️ === FIN AFFICHAGE MODAL FORCÉ ===');

        // Double vérification après un court délai
        setTimeout(() => {
            console.log('🔍 Vérification après 100ms:');
            console.log('🔍 Modal toujours active:', this.activeModal ? this.activeModal.id : 'null');
            console.log('🔍 Display style:', window.getComputedStyle(modal).display);
            console.log('🔍 Modal visible dans DOM:', document.contains(modal));
        }, 100);
    }

    /**
     * Ferme la modal active
     */
    close() {
        if (this.activeModal) {
            console.log('❌ Fermeture de la modal:', this.activeModal.id);

            // Masquer immédiatement
            this.activeModal.style.display = 'none !important';
            this.activeModal.classList.remove('show');

            // Supprimer après délai
            setTimeout(() => {
                if (this.activeModal && this.activeModal.parentNode) {
                    this.activeModal.parentNode.removeChild(this.activeModal);
                }
                this.activeModal = null;
            }, 100);
        }

        // Restaurer le body
        document.body.classList.remove('modal-open');
        document.body.style.cssText = '';
    }

    /**
     * Affiche l'état de chargement
     */
    setLoading(modal, isLoading) {
        const modalBody = modal.querySelector('.modal-body');
        if (!modalBody) return;

        if (isLoading) {
            modalBody.innerHTML = `
                <div class="flex justify-center items-center h-32">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                    <p class="ml-4 text-gray-600 font-medium">Chargement des détails...</p>
                </div>
            `;
        }
    }

    /**
     * Formate une date
     */
    formatDate(dateString) {
        if (!dateString) return 'N/A';

        try {
            return new Date(dateString).toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (error) {
            return 'Date invalide';
        }
    }

    /**
     * Formate un montant
     */
    formatAmount(amount) {
        const num = parseFloat(amount) || 0;
        return new Intl.NumberFormat('fr-FR').format(num);
    }

    /**
     * Nettoyage et destruction
     */
    destroy() {
        this.close();

        this.modals.forEach(modal => {
            if (modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
        });
        this.modals.clear();

        // Supprimer les styles injectés
        const styleElement = document.getElementById('modal-manager-styles');
        if (styleElement) {
            styleElement.remove();
        }

        document.body.classList.remove('modal-open');
        document.body.style.cssText = '';

        this.isInitialized = false;

        console.log('🧹 ModalManager nettoyé complètement');
    }
}

// Export pour utilisation globale
window.ModalManager = ModalManager;