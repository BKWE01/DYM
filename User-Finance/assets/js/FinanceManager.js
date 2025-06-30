/**
 * FinanceManager.js
 * Gestionnaire principal pour le service Finance - VERSION CORRIGÉE
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

class FinanceManager {
    constructor() {
        this.config = {
            urls: {
                pendingOrders: 'api/dashboard/api_get_archived_orders.php',
                signedOrders: 'api/dashboard/get_signed_bons.php',
                rejectedOrders: 'api/dashboard/get_rejected_bons.php',
                orderDetails: 'api/dashboard/get_bon_details_finance.php',
                saveSignature: 'api/dashboard/update_save_signature.php',
                deleteOrder: 'api/dashboard/delete_order.php', // NOUVEAU
                exportData: 'api/dashboard/export_bons.php'
            },
            pagination: {
                defaultLimit: 25,
                maxLimit: 100
            },
            refresh: {
                interval: 300000, // 5 minutes
                auto: false
            }
        };

        this.state = {
            currentTab: 'pending',
            loading: false,
            data: {
                pending: [],
                signed: [],
                rejected: [] // NOUVEAU
            },
            filters: {},
            pagination: {
                pending: { page: 1, total: 0 },
                signed: { page: 1, total: 0 },
                rejected: { page: 1, total: 0 } // NOUVEAU
            }
        };

        this.components = {
            modal: null,
            dataTable: null,
            notification: null
        };

        this.init();
    }

    /**
     * Initialisation du gestionnaire
     */
    init() {
        console.log('🚀 Initialisation FinanceManager v2.0');

        this.bindEvents();
        this.initializeComponents();
        this.loadInitialData();
        this.startDateTime();

        console.log('✅ FinanceManager initialisé avec succès');
    }

    /**
     * Liaison des événements globaux
     */
    bindEvents() {
        // Navigation entre onglets
        $(document).on('click', '.tab', (e) => {
            const tabId = $(e.currentTarget).data('tab');
            this.switchTab(tabId);
        });

        // Événements pour les rejets de bons - NOUVEAU
        $(document).on('financeManager:rejectOrder', function (e, orderId, orderNumber) {
            window.financeManager.rejectOrder(orderId, orderNumber);
        });

        // Boutons d'export
        $('#export-excel').on('click', () => this.exportData('excel'));
        $('#export-pdf').on('click', () => this.exportData('pdf'));

        // Rafraîchissement automatique (optionnel)
        if (this.config.refresh.auto) {
            setInterval(() => {
                this.refreshCurrentTab();
            }, this.config.refresh.interval);
        }

        // Gestion des erreurs globales AJAX
        $(document).ajaxError((event, xhr, settings, thrownError) => {
            console.error('🚨 Erreur AJAX:', settings.url, xhr.status, thrownError);

            // Ne pas afficher d'erreur si c'est une erreur 0 (annulation)
            if (xhr.status !== 0) {
                this.showNotification('Erreur de connexion au serveur', 'error');
            }
        });

        // Nettoyage avant déchargement
        window.addEventListener('beforeunload', () => {
            this.destroy();
        });
    }

    /**
     * Initialisation des composants
     */
    initializeComponents() {
        // Initialiser le gestionnaire de modales
        this.components.modal = new ModalManager();

        // Initialiser le gestionnaire de tableaux
        this.components.dataTable = new DataTableManager();

        // Initialiser le système de notifications
        this.components.notification = new NotificationManager({
            position: 'top-right',
            autoClose: true,
            duration: 5000,
            useSweetAlert: true
        });
    }

    /**
     * Chargement des données initiales
     */
    loadInitialData() {
        // Charger les données de l'onglet actuel
        if (this.state.currentTab === 'pending') {
            this.loadPendingOrders();
        } else {
            this.loadSignedOrders();
        }
    }

    /**
     * Démarrage de l'horloge
     */
    startDateTime() {
        this.updateDateTime();
        this.dateTimeInterval = setInterval(() => this.updateDateTime(), 60000);
    }

    /**
     * Mise à jour de la date et heure
     */
    updateDateTime() {
        const now = new Date();
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };

        const formattedDate = now.toLocaleDateString('fr-FR', options);
        $('#date-time-display').text(formattedDate);
    }

    /**
     * Changement d'onglet
     */
    switchTab(tabId) {
        if (this.state.currentTab === tabId) return;

        console.log(`📂 Changement d'onglet: ${this.state.currentTab} → ${tabId}`);

        // Fermer toute modal ouverte avant de changer d'onglet
        if (this.components.modal) {
            this.components.modal.close();
        }

        // Mettre à jour l'interface
        $('.tab').removeClass('active');
        $(`.tab[data-tab="${tabId}"]`).addClass('active');

        $('.tab-content').removeClass('active');
        $(`#${tabId}-tab`).addClass('active');

        // Mettre à jour l'état
        this.state.currentTab = tabId;

        // Charger les données correspondantes
        if (tabId === 'pending') {
            this.loadPendingOrders();
        } else if (tabId === 'signed') {
            this.loadSignedOrders();
        } else if (tabId === 'rejected') { // NOUVEAU
            this.loadRejectedOrders();
        }

        console.log(`✅ Onglet actif: ${tabId}`);
    }

    /**
    * Chargement des bons rejetés - NOUVELLE MÉTHODE
    */
    async loadRejectedOrders() {
        try {
            console.log('📥 Chargement des bons rejetés...');
            this.setLoading('rejected', true);

            const response = await this.apiCall(this.config.urls.rejectedOrders);
            console.log('📄 Réponse API rejected:', response);

            if (response.success) {
                this.state.data.rejected = response.data || [];

                if (this.state.data.rejected.length > 0) {
                    // Initialiser le tableau et vérifier le succès
                    const tableInitialized = this.components.dataTable.initializeRejectedTable(this.state.data.rejected);

                    if (tableInitialized) {
                        // Masquer explicitement l'état vide
                        this.hideEmptyState('rejected');
                        console.log(`✅ ${this.state.data.rejected.length} bon(s) rejeté(s) chargé(s) et affiché(s)`);
                    } else {
                        this.showEmptyState('rejected', 'Erreur d\'affichage du tableau');
                    }
                } else {
                    this.showEmptyState('rejected', 'Aucun bon rejeté trouvé');
                }

            } else {
                console.warn('⚠️ Erreur API rejected:', response.message);
                this.showEmptyState('rejected', response.message || 'Erreur lors du chargement');
            }

        } catch (error) {
            console.error('❌ Erreur loadRejectedOrders:', error);
            this.showEmptyState('rejected', 'Erreur technique');
            this.showNotification('Erreur lors du chargement des bons rejetés', 'error');
        } finally {
            this.setLoading('rejected', false);
        }
    }



    /**
     * Chargement des bons en attente - VERSION CORRIGÉE
     */
    async loadPendingOrders() {
        try {
            console.log('📥 Chargement des bons en attente...');
            this.setLoading('pending', true);

            const response = await this.apiCall(this.config.urls.pendingOrders);
            console.log('📄 Réponse API pending:', response);

            if (response.success) {
                this.state.data.pending = response.data || [];

                if (this.state.data.pending.length > 0) {
                    // Initialiser le tableau et vérifier le succès
                    const tableInitialized = this.components.dataTable.initializePendingTable(this.state.data.pending);

                    if (tableInitialized) {
                        // Masquer explicitement l'état vide
                        this.hideEmptyState('pending');
                        console.log(`✅ ${this.state.data.pending.length} bon(s) en attente chargé(s) et affiché(s)`);
                    } else {
                        this.showEmptyState('pending', 'Erreur d\'affichage du tableau');
                    }
                } else {
                    this.showEmptyState('pending', 'Aucun bon en attente de signature');
                }

            } else {
                console.warn('⚠️ Erreur API pending:', response.message);
                this.showEmptyState('pending', response.message || 'Erreur lors du chargement');
            }

        } catch (error) {
            console.error('❌ Erreur loadPendingOrders:', error);
            this.showEmptyState('pending', 'Erreur technique');
            this.showNotification('Erreur lors du chargement des bons en attente', 'error');
        } finally {
            this.setLoading('pending', false);
        }
    }

    /**
     * Chargement des bons signés - VERSION CORRIGÉE
     */
    async loadSignedOrders() {
        try {
            console.log('📥 Chargement des bons signés...');
            this.setLoading('signed', true);

            const params = this.buildFilterParams('signed');
            const url = `${this.config.urls.signedOrders}?${params}`;

            const response = await this.apiCall(url);
            console.log('📄 Réponse API signed:', response);

            if (response.success) {
                this.state.data.signed = response.data || [];
                this.state.pagination.signed = response.meta?.pagination || { page: 1, total: 0 };

                if (this.state.data.signed.length > 0) {
                    // Initialiser le tableau et vérifier le succès
                    const tableInitialized = this.components.dataTable.initializeSignedTable(this.state.data.signed);

                    if (tableInitialized) {
                        // Masquer explicitement l'état vide
                        this.hideEmptyState('signed');
                        console.log(`✅ ${this.state.data.signed.length} bon(s) signé(s) chargé(s) et affiché(s)`);
                    } else {
                        this.showEmptyState('signed', 'Erreur d\'affichage du tableau');
                    }
                } else {
                    this.showEmptyState('signed', 'Aucun bon signé trouvé');
                }

            } else {
                console.warn('⚠️ Erreur API signed:', response.message);
                this.showEmptyState('signed', response.message || 'Erreur lors du chargement');
            }

        } catch (error) {
            console.error('❌ Erreur loadSignedOrders:', error);
            this.showEmptyState('signed', 'Erreur technique');
            this.showNotification('Erreur lors du chargement des bons signés', 'error');
        } finally {
            this.setLoading('signed', false);
        }
    }

    /**
     * Affichage de l'état de chargement - VERSION CORRIGÉE
     */
    setLoading(type, isLoading) {
        const loadingEl = $(`#${type}-loading`);
        const containerEl = $(`#${type}-table-container`);
        const noDataEl = $(`#${type}-no-data`);

        if (isLoading) {
            loadingEl.show();
            containerEl.addClass('hidden').hide();
            noDataEl.addClass('hidden').hide();
            console.log(`⏳ État de chargement activé pour ${type}`);
        } else {
            loadingEl.hide();
            console.log(`✅ État de chargement désactivé pour ${type}`);
        }

        this.state.loading = isLoading;
    }

    /**
     * Affichage de l'état vide - VERSION CORRIGÉE
     */
    showEmptyState(type, message) {
        // Masquer le tableau et le chargement
        $(`#${type}-table-container`).addClass('hidden').hide();
        $(`#${type}-loading`).hide();

        // Afficher l'état vide avec le message
        const noDataEl = $(`#${type}-no-data`);
        noDataEl.removeClass('hidden').show();
        noDataEl.find('.empty-message').text(message);

        console.log(`📭 État vide affiché pour ${type}: ${message}`);
    }

    /**
     * Masquer l'état vide - VERSION CORRIGÉE
     */
    hideEmptyState(type) {
        const noDataEl = $(`#${type}-no-data`);
        noDataEl.addClass('hidden').hide();

        console.log(`🙈 État vide masqué pour ${type}`);
    }

    /**
     * Construction des paramètres de filtre
     */
    buildFilterParams(type) {
        const params = new URLSearchParams();

        // Pagination
        const pagination = this.state.pagination[type];
        params.append('page', pagination.page);
        params.append('limit', this.config.pagination.defaultLimit);

        // Filtres spécifiques
        const filters = this.state.filters[type] || {};
        Object.entries(filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        return params.toString();
    }

    /**
     * Rafraîchissement de l'onglet actuel - VERSION CORRIGÉE
     */
    refreshCurrentTab() {
        console.log(`🔄 Rafraîchissement de l'onglet: ${this.state.currentTab}`);

        // Fermer toute modal ouverte
        if (this.components.modal) {
            this.components.modal.close();
        }

        // Rafraîchir selon l'onglet actuel
        if (this.state.currentTab === 'pending') {
            this.loadPendingOrders();
        } else if (this.state.currentTab === 'signed') {
            this.loadSignedOrders();
        } else if (this.state.currentTab === 'rejected') { // NOUVEAU
            this.loadRejectedOrders();
        }

        // Recharger les statistiques
        if (window.Stats) {
            window.Stats.load();
        }

        this.showNotification('Données actualisées', 'info');
    }

    /**
     * Signature d'un bon de commande - VERSION CORRIGÉE
     */
    async signOrder(orderId, orderNumber) {
        try {
            console.log(`✍️ Signature du bon ${orderNumber} (ID: ${orderId})`);

            // Afficher la modale de confirmation
            const confirmed = await this.components.modal.showSignatureModal(orderId, orderNumber);

            if (!confirmed) {
                console.log('❌ Signature annulée par l\'utilisateur');
                return;
            }

            // Effectuer la signature
            const response = await this.apiCall(this.config.urls.saveSignature, {
                method: 'POST',
                data: { order_id: orderId }
            });

            if (response.success) {
                this.showNotification('Bon de commande signé avec succès!', 'success');

                console.log('🔄 Rafraîchissement complet après signature...');

                // 1. Recharger les statistiques - CORRECTION
                if (window.Stats) {
                    await window.Stats.load();
                }

                // 2. Rafraîchir les données des deux onglets
                await this.refreshAllTabs();

                // 3. Passer à l'onglet "Bons signés" pour montrer le résultat
                setTimeout(() => {
                    this.switchTab('signed');
                    this.showNotification(`Le bon ${orderNumber} apparaît maintenant dans l'onglet "Bons signés"`, 'info');
                }, 1000);

                console.log(`✅ Bon ${orderNumber} signé avec succès et données rafraîchies`);
            } else {
                this.showNotification(response.message || 'Erreur lors de la signature', 'error');
                console.error('❌ Erreur signature:', response);
            }

        } catch (error) {
            console.error('❌ Erreur signOrder:', error);
            this.showNotification('Erreur technique lors de la signature', 'error');
        }
    }

    /**
 * Révocation d'un bon de commande déjà signé - NOUVELLE MÉTHODE
 * À ajouter dans la classe FinanceManager
 */
    async revokeSignedOrder(orderId, orderNumber) {
        try {
            console.log(`🔄 === DÉBUT RÉVOCATION BON SIGNÉ ${orderNumber} ===`);
            console.log(`🔄 Bon ID: ${orderId}, Numéro: ${orderNumber}`);

            // Afficher la modale de révocation spécifique
            const revokeResult = await this.components.modal.showRevokeSignedModal(orderId, orderNumber);
            console.log('🔍 Résultat modal révocation:', revokeResult);

            if (!revokeResult.confirmed) {
                console.log('🔄 Révocation annulée par l\'utilisateur');
                return;
            }

            console.log('📡 Préparation de l\'appel API de révocation...');

            // Préparer les données pour l'API avec un flag spécial pour révocation
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('rejection_reason', revokeResult.reason);
            formData.append('revoke_signed', 'true'); // Flag pour indiquer qu'on révoque un bon signé

            console.log('📡 Données à envoyer:', {
                order_id: orderId,
                rejection_reason: revokeResult.reason,
                revoke_signed: true
            });

            // Effectuer la révocation avec fetch
            const response = await fetch('api/dashboard/reject_order.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            console.log('📡 Statut réponse:', response.status, response.statusText);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            // Récupérer la réponse
            const responseText = await response.text();
            console.log('📡 Réponse brute:', responseText);

            // Vérifier si la réponse contient du HTML (erreurs PHP)
            if (responseText.includes('<br') || responseText.includes('<!DOCTYPE') || responseText.includes('<html')) {
                console.error('❌ Réponse contient du HTML au lieu de JSON:', responseText.substring(0, 200));
                throw new Error('Le serveur a retourné une réponse HTML au lieu de JSON. Vérifiez les logs du serveur.');
            }

            // Parser le JSON
            let responseData;
            try {
                responseData = JSON.parse(responseText);
            } catch (parseError) {
                console.error('❌ Erreur de parsing JSON:', parseError);
                console.error('❌ Texte de réponse:', responseText);
                throw new Error('Réponse du serveur invalide (JSON malformé)');
            }

            console.log('📡 Réponse API parsée:', responseData);

            if (responseData.success) {
                this.showNotification('Bon de commande révoqué avec succès!', 'success');

                console.log('🔄 Rafraîchissement complet après révocation...');

                // 1. Recharger les statistiques
                if (window.Stats) {
                    await window.Stats.load();
                }

                // 2. Rafraîchir les données des onglets
                await this.refreshAllTabs();

                // 3. Passer à l'onglet "Bons rejetés" pour voir le résultat
                setTimeout(() => {
                    this.switchTab('rejected');
                    this.showNotification(`Le bon ${orderNumber} a été révoqué et déplacé vers les bons rejetés`, 'info');
                }, 1000);

                console.log(`✅ Bon ${orderNumber} révoqué avec succès et données rafraîchies`);
            } else {
                console.error('❌ Erreur API:', responseData);
                this.showNotification(responseData.message || 'Erreur lors de la révocation', 'error');

                if (responseData.debug) {
                    console.error('🔍 Debug info:', responseData.debug);
                }
            }

        } catch (error) {
            console.error('❌ === ERREUR RÉVOCATION ===');
            console.error('❌ Erreur:', error.message);
            console.error('❌ Stack:', error.stack);
            this.showNotification('Erreur technique lors de la révocation: ' + error.message, 'error');
        }
    }

    /**
     * Rejet d'un bon de commande - NOUVELLE MÉTHODE
     */

    async rejectOrder(orderId, orderNumber) {
        try {
            console.log(`❌ === DÉBUT REJET BON ${orderNumber} ===`);
            console.log(`❌ Bon ID: ${orderId}, Numéro: ${orderNumber}`);

            // Afficher la modale de rejet
            const rejectResult = await this.components.modal.showRejectModal(orderId, orderNumber);
            console.log('🔍 Résultat modal rejet:', rejectResult);

            if (!rejectResult.confirmed) {
                console.log('❌ Rejet annulé par l\'utilisateur');
                return;
            }

            console.log('📡 Préparation de l\'appel API de rejet...');

            // Préparer les données pour l'API
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('rejection_reason', rejectResult.reason);

            console.log('📡 Données à envoyer:', {
                order_id: orderId,
                rejection_reason: rejectResult.reason
            });

            // Effectuer le rejet avec fetch pour plus de contrôle
            const response = await fetch('api/dashboard/reject_order.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            console.log('📡 Statut réponse:', response.status, response.statusText);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            // AMÉLIORATION : Récupérer d'abord le texte brut pour diagnostic
            const responseText = await response.text();
            console.log('📡 Réponse brute:', responseText);

            // Vérifier si la réponse contient du HTML (erreurs PHP)
            if (responseText.includes('<br') || responseText.includes('<!DOCTYPE') || responseText.includes('<html')) {
                console.error('❌ Réponse contient du HTML au lieu de JSON:', responseText.substring(0, 200));
                throw new Error('Le serveur a retourné une réponse HTML au lieu de JSON. Vérifiez les logs du serveur.');
            }

            // Parser le JSON
            let responseData;
            try {
                responseData = JSON.parse(responseText);
            } catch (parseError) {
                console.error('❌ Erreur de parsing JSON:', parseError);
                console.error('❌ Texte de réponse:', responseText);
                throw new Error('Réponse du serveur invalide (JSON malformé)');
            }

            console.log('📡 Réponse API parsée:', responseData);

            if (responseData.success) {
                this.showNotification('Bon de commande rejeté avec succès!', 'success');

                console.log('🔄 Rafraîchissement complet après rejet...');

                // 1. Recharger les statistiques
                if (window.Stats) {
                    await window.Stats.load();
                }

                // 2. Rafraîchir les données des onglets
                await this.refreshAllTabs();

                // 3. Rester sur l'onglet actuel pour voir la mise à jour
                this.showNotification(`Le bon ${orderNumber} a été rejeté et retiré de la liste`, 'info');

                console.log(`✅ Bon ${orderNumber} rejeté avec succès et données rafraîchies`);
            } else {
                console.error('❌ Erreur API:', responseData);
                this.showNotification(responseData.message || 'Erreur lors du rejet', 'error');

                // Afficher les détails de debug si disponibles
                if (responseData.debug) {
                    console.error('🔍 Debug info:', responseData.debug);
                }
            }

        } catch (error) {
            console.error('❌ === ERREUR REJET ===');
            console.error('❌ Erreur:', error.message);
            console.error('❌ Stack:', error.stack);
            this.showNotification('Erreur technique lors du rejet: ' + error.message, 'error');
        }
    }

    /**
         * Suppression définitive d'un bon de commande - NOUVELLE MÉTHODE
         */
    async deleteOrder(orderId, orderNumber) {
        try {
            console.log(`🗑️ === DÉBUT SUPPRESSION BON ${orderNumber} ===`);
            console.log(`🗑️ Bon ID: ${orderId}, Numéro: ${orderNumber}`);

            // Afficher la modale de suppression
            const deleteResult = await this.components.modal.showDeleteModal(orderId, orderNumber);
            console.log('🔍 Résultat modal suppression:', deleteResult);

            if (!deleteResult.confirmed) {
                console.log('🗑️ Suppression annulée par l\'utilisateur');
                return;
            }

            console.log('📡 Préparation de l\'appel API de suppression...');

            // Préparer les données pour l'API
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('delete_reason', deleteResult.reason);

            console.log('📡 Données à envoyer:', {
                order_id: orderId,
                delete_reason: deleteResult.reason
            });

            // Effectuer la suppression avec fetch
            const response = await fetch(this.config.urls.deleteOrder, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            console.log('📡 Statut réponse:', response.status, response.statusText);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            // Récupérer la réponse
            const responseText = await response.text();
            console.log('📡 Réponse brute:', responseText);

            // Vérifier si la réponse contient du HTML (erreurs PHP)
            if (responseText.includes('<br') || responseText.includes('<!DOCTYPE') || responseText.includes('<html')) {
                console.error('❌ Réponse contient du HTML au lieu de JSON:', responseText.substring(0, 200));
                throw new Error('Le serveur a retourné une réponse HTML au lieu de JSON. Vérifiez les logs du serveur.');
            }

            // Parser le JSON
            let responseData;
            try {
                responseData = JSON.parse(responseText);
            } catch (parseError) {
                console.error('❌ Erreur de parsing JSON:', parseError);
                console.error('❌ Texte de réponse:', responseText);
                throw new Error('Réponse du serveur invalide (JSON malformé)');
            }

            console.log('📡 Réponse API parsée:', responseData);

            if (responseData.success) {
                this.showNotification('Bon de commande supprimé définitivement avec succès!', 'success');

                console.log('🔄 Rafraîchissement complet après suppression...');

                // 1. Recharger les statistiques
                if (window.Stats) {
                    await window.Stats.load();
                }

                // 2. Rafraîchir les données des onglets
                await this.refreshAllTabs();

                // 3. Rester sur l'onglet rejetés pour voir la mise à jour
                this.showNotification(`Le bon ${orderNumber} a été supprimé définitivement`, 'info');

                console.log(`✅ Bon ${orderNumber} supprimé avec succès et données rafraîchies`);
            } else {
                console.error('❌ Erreur API:', responseData);
                this.showNotification(responseData.message || 'Erreur lors de la suppression', 'error');

                if (responseData.debug) {
                    console.error('🔍 Debug info:', responseData.debug);
                }
            }

        } catch (error) {
            console.error('❌ === ERREUR SUPPRESSION ===');
            console.error('❌ Erreur:', error.message);
            console.error('❌ Stack:', error.stack);
            this.showNotification('Erreur technique lors de la suppression: ' + error.message, 'error');
        }
    }

    /**
     * Rafraîchit tous les onglets - NOUVELLE MÉTHODE
     */
    async refreshAllTabs() {
        try {
            console.log('🔄 === DÉBUT RAFRAÎCHISSEMENT COMPLET ===');

            // Sauvegarder l'onglet actuel
            const currentTab = this.state.currentTab;

            // Vider le cache des données
            this.state.data.pending = [];
            this.state.data.signed = [];
            this.state.data.rejected = []; // NOUVEAU

            // Recharger les données en parallèle
            const [pendingPromise, signedPromise, rejectedPromise] = await Promise.allSettled([
                this.loadPendingOrdersData(),
                this.loadSignedOrdersData(),
                this.loadRejectedOrdersData() // NOUVEAU
            ]);

            // Traiter les résultats
            if (pendingPromise.status === 'fulfilled') {
                this.state.data.pending = pendingPromise.value;
                console.log('✅ Données pending rechargées:', this.state.data.pending.length);
            }

            if (signedPromise.status === 'fulfilled') {
                this.state.data.signed = signedPromise.value;
                console.log('✅ Données signed rechargées:', this.state.data.signed.length);
            }

            if (rejectedPromise.status === 'fulfilled') { // NOUVEAU
                this.state.data.rejected = rejectedPromise.value;
                console.log('✅ Données rejected rechargées:', this.state.data.rejected.length);
            }

            // Mettre à jour les tableaux
            this.updateDataTables();

            // Mettre à jour les badges des onglets
            this.updateTabBadges();

            // Revenir à l'onglet original
            this.state.currentTab = currentTab;

            console.log('🔄 === FIN RAFRAÎCHISSEMENT COMPLET ===');

        } catch (error) {
            console.error('❌ Erreur refreshAllTabs:', error);
        }
    }

    /**
 * Charge les données des bons rejetés (sans affichage) - NOUVELLE MÉTHODE
 */
    async loadRejectedOrdersData() {
        try {
            const response = await this.apiCall(this.config.urls.rejectedOrders);
            if (response.success) {
                return response.data || [];
            }
            return [];
        } catch (error) {
            console.error('❌ Erreur loadRejectedOrdersData:', error);
            return [];
        }
    }

    /**
     * Charge les données des bons en attente (sans affichage)
     */
    async loadPendingOrdersData() {
        try {
            const response = await this.apiCall(this.config.urls.pendingOrders);
            if (response.success) {
                return response.data || [];
            }
            return [];
        } catch (error) {
            console.error('❌ Erreur loadPendingOrdersData:', error);
            return [];
        }
    }

    /**
     * Charge les données des bons signés (sans affichage)
     */
    async loadSignedOrdersData() {
        try {
            const response = await this.apiCall(this.config.urls.signedOrders);
            if (response.success) {
                return response.data || [];
            }
            return [];
        } catch (error) {
            console.error('❌ Erreur loadSignedOrdersData:', error);
            return [];
        }
    }

    /**
     * Met à jour les DataTables avec les nouvelles données
     */
    updateDataTables() {
        try {
            // Mettre à jour le tableau pending
            if (this.state.data.pending.length > 0) {
                this.components.dataTable.initializePendingTable(this.state.data.pending);
                this.hideEmptyState('pending');
            } else {
                this.showEmptyState('pending', 'Aucun bon en attente de signature');
            }

            // Mettre à jour le tableau signed
            if (this.state.data.signed.length > 0) {
                this.components.dataTable.initializeSignedTable(this.state.data.signed);
                this.hideEmptyState('signed');
            } else {
                this.showEmptyState('signed', 'Aucun bon signé trouvé');
            }

            // Mettre à jour le tableau rejected - NOUVEAU
            if (this.state.data.rejected.length > 0) {
                this.components.dataTable.initializeRejectedTable(this.state.data.rejected);
                this.hideEmptyState('rejected');
            } else {
                this.showEmptyState('rejected', 'Aucun bon rejeté trouvé');
            }

            console.log('✅ DataTables mis à jour');
        } catch (error) {
            console.error('❌ Erreur updateDataTables:', error);
        }
    }

    /**
     * Met à jour les badges des onglets
     */
    updateTabBadges() {
        try {
            const pendingCount = this.state.data.pending.length;
            const signedCount = this.state.data.signed.length;
            const rejectedCount = this.state.data.rejected.length; // NOUVEAU

            $('#pending-badge').text(pendingCount);
            $('#signed-badge').text(signedCount);
            $('#rejected-badge').text(rejectedCount); // NOUVEAU

            // Mettre à jour les statistiques dans l'en-tête
            $('#stat-pending-count').text(pendingCount);
            $('#stat-signed-count').text(signedCount);

            console.log(`📊 Badges mis à jour: ${pendingCount} pending, ${signedCount} signed, ${rejectedCount} rejected`);
        } catch (error) {
            console.error('❌ Erreur updateTabBadges:', error);
        }
    }



    /**
     * Visualisation des détails d'un bon
     */
    async viewOrderDetails(orderId) {
        try {
            console.log(`👁️ Affichage des détails du bon ID: ${orderId}`);

            const response = await this.apiCall(`${this.config.urls.orderDetails}?id=${orderId}`);

            if (response.success) {
                this.components.modal.showOrderDetails(response.data);
            } else {
                this.showNotification(response.message || 'Erreur lors du chargement des détails', 'error');
            }

        } catch (error) {
            console.error('❌ Erreur viewOrderDetails:', error);
            this.showNotification('Erreur technique lors du chargement des détails', 'error');
        }
    }

    /**
     * Export des données
     */
    exportData(format) {
        const type = this.state.currentTab;
        const url = `${this.config.urls.exportData}?type=${type}&format=${format}`;

        console.log(`📥 Export ${format.toUpperCase()} pour ${type}`);

        // Ouvrir dans une nouvelle fenêtre pour téléchargement
        window.open(url, '_blank');

        this.showNotification(`Export ${format.toUpperCase()} en cours...`, 'info');
    }

    /**
     * Appel API générique amélioré
     */
    async apiCall(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            dataType: 'json',
            timeout: 30000
        };

        const finalOptions = { ...defaultOptions, ...options };

        console.log(`🌐 API Call: ${finalOptions.method} ${url}`);

        return new Promise((resolve, reject) => {
            $.ajax({
                url: url,
                method: finalOptions.method,
                data: finalOptions.data || null,
                dataType: finalOptions.dataType,
                timeout: finalOptions.timeout,
                success: (response) => {
                    console.log(`✅ API Success: ${url}`, response);
                    resolve(response);
                },
                error: (xhr, status, error) => {
                    console.error(`❌ API Error: ${url}`, {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText?.substring(0, 200),
                        error
                    });

                    // Traitement spécifique des erreurs
                    let errorMessage = 'Erreur de connexion';

                    if (xhr.status === 400) {
                        errorMessage = 'Requête invalide';
                    } else if (xhr.status === 401) {
                        errorMessage = 'Non autorisé';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Accès interdit';
                    } else if (xhr.status === 404) {
                        errorMessage = 'Ressource non trouvée';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Erreur serveur';
                    }

                    reject(new Error(`API Error: ${status} - ${errorMessage}`));
                }
            });
        });
    }

    /**
     * Affichage des notifications
     */
    showNotification(message, type = 'info') {
        if (this.components.notification) {
            this.components.notification.show(message, type);
        } else {
            // Fallback avec SweetAlert
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: type === 'success' ? 'success' : type === 'error' ? 'error' : 'info',
                    title: message,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                console.log(`[${type.toUpperCase()}] ${message}`);
            }
        }
    }

    /**
     * Nettoyage et destruction
     */
    destroy() {
        // Nettoyer les intervalles
        if (this.dateTimeInterval) {
            clearInterval(this.dateTimeInterval);
        }

        // Nettoyer les composants
        Object.values(this.components).forEach(component => {
            if (component && typeof component.destroy === 'function') {
                component.destroy();
            }
        });

        // Nettoyer les événements
        $(document).off('.financeManager');

        console.log('🧹 FinanceManager nettoyé');
    }

    /**
     * Méthodes utilitaires
     */

    /**
     * Formatage des montants
     */
    static formatAmount(amount) {
        return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
    }

    /**
     * Formatage des dates
     */
    static formatDate(dateString) {
        if (!dateString) return 'N/A';

        try {
            return new Date(dateString).toLocaleDateString('fr-FR');
        } catch (error) {
            return 'Date invalide';
        }
    }

    /**
     * Validation des données
     */
    static validateOrderId(orderId) {
        return orderId && Number.isInteger(Number(orderId)) && Number(orderId) > 0;
    }
}

// Export pour utilisation globale
window.FinanceManager = FinanceManager;