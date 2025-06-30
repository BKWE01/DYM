
/**
 * Module de gestion des achats de matériaux
 * Architecture moderne avec organisation modulaire
 * VERSION CORRIGÉE : Intégration complète des modes de paiement par ID
 */
"use strict";

// -----------------------------------------------------------------------------
// Configuration globale et constantes
// -----------------------------------------------------------------------------
const CONFIG = {
    API_URLS: {
        FOURNISSEURS: 'get_fournisseurs.php',
        CHECK_MATERIALS: 'check_new_materials.php',
        MATERIAL_INFO: 'get_material_info.php',
        CANCEL_ORDERS: 'api/orders/cancel_multiple_orders.php',
        CANCEL_PENDING: 'api/orders/cancel_pending_materials.php',
        PARTIAL_ORDERS: 'commandes-traitement/api.php',
        SUBSTITUTION: 'api/substitution/process_substitution.php',
        PRODUCT_SUGGESTIONS: 'api/substitution/get_product_suggestions.php',
        BON_COMMANDE: 'generate_bon_commande.php',
        CHECK_FOURNISSEUR: 'api/fournisseurs/check_fournisseur.php',
        PROCESS_PURCHASE: 'api/fournisseurs/process_purchase.php',
        UPDATE_ORDER_STATUS: 'api/orders/update_order_status.php',
        PAYMENT_METHODS: 'api/payment/get_payment_methods.php', // NOUVEAU : API pour les modes de paiement
    },
    REFRESH_INTERVALS: {
        DATETIME: 1000,
        CHECK_MATERIALS: 5 * 60 * 1000, // 5 minutes
        CHECK_VALIDATION: 5 * 60 * 1000 // 5 minutes
    },
    DATATABLES: {
        LANGUAGE_URL: "//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json",
        DOM: 'Blfrtip',
        BUTTONS: ['excel', 'print'],
        PAGE_LENGTH: 15
    }
};
/**
 * MODULE DE GESTION DES MODES DE PAIEMENT - VERSION 2.0
 * 
 * Mise à jour : Utilisation du champ icon_path au lieu de icon
 * Améliorations : Gestion dynamique et optimisée des modes de paiement
 * Date : 30/06/2025
 */
const PaymentMethodsManager = {
    // Cache des données
    paymentMethods: [],
    isLoaded: false,
    cache: {
        lastUpdate: null,
        cacheDuration: 5 * 60 * 1000 // 5 minutes en millisecondes
    },
    /**
     * Initialisation du gestionnaire des modes de paiement
     */
    async init() {
        try {
            console.log('🔄 Initialisation du PaymentMethodsManager v2.0...');
            await this.loadPaymentMethods();
            this.populateAllSelectors();
            this.setupEventListeners();
            console.log('✅ PaymentMethodsManager initialisé avec succès');
            console.log(`📊 ${this.paymentMethods.length} modes de paiement disponibles`);
        } catch (error) {
            console.error('❌ Erreur lors de l\'initialisation des modes de paiement:', error);
            this.handleError('Erreur d\'initialisation des modes de paiement');
        }
    },
    /**
     * Chargement des modes de paiement depuis l'API - MISE À JOUR
     */
    async loadPaymentMethods() {
        try {
            // Vérification du cache
            if (this.isLoaded && this.isCacheValid()) {
                console.log('📋 Utilisation du cache des modes de paiement');
                return;
            }
            console.log('🔄 Chargement des modes de paiement depuis l\'API...');
            // Appel à la nouvelle API
            const response = await fetch(CONFIG.API_URLS.PAYMENT_METHODS, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!response.ok) {
                throw new Error(`Erreur HTTP ${response.status}: ${response.statusText}`);
            }
            const data = await response.json();
            if (data.success) {
                this.paymentMethods = data.methods || [];
                this.isLoaded = true;
                this.cache.lastUpdate = Date.now();
                console.log(`✅ ${data.count} modes de paiement chargés depuis l'API`);
                console.log('📋 Version API:', data.metadata?.version || 'inconnue');
            } else {
                throw new Error(data.message || 'Erreur lors du chargement des modes de paiement');
            }
        } catch (error) {
            console.warn('⚠️ Erreur API, utilisation des modes par défaut:', error.message);
            this.loadFallbackMethods();
        }
    },
    /**
     * Peupler un sélecteur avec les modes de paiement - CORRIGÉE
     */
    populatePaymentSelect(selectId) {
        const select = document.getElementById(selectId);
        if (!select) {
            console.warn('⚠️ Sélecteur non trouvé:', selectId);
            return;
        }
        // Garder l'option par défaut
        const defaultOption = select.querySelector('option[value=""]');
        select.innerHTML = '';
        if (defaultOption) {
            select.appendChild(defaultOption);
        }
        // Ajouter les modes de paiement actifs
        this.paymentMethods
            .filter(method => method.is_active !== false)
            .forEach(method => {
                const option = document.createElement('option');
                // CORRECTION PRINCIPALE : Utiliser l'ID comme valeur
                option.value = method.id;
                option.textContent = method.label;
                // Stocker les données supplémentaires
                if (method.description) option.dataset.description = method.description;
                if (method.icon) option.dataset.icon = method.icon;
                select.appendChild(option);
            });
        console.log(`✅ Sélecteur ${selectId} peuplé avec ${this.paymentMethods.length} modes de paiement`);
    },
    /**
     * Méthodes de paiement par défaut (fallback)
     */
    loadFallbackMethods() {
        this.paymentMethods = [{
            id: 1,
            label: 'Virement bancaire',
            description: 'Paiement par virement bancaire',
            icon_path: null, // Pas d'icône en mode fallback
            display_order: 1,
            is_active: true
        },
        {
            id: 2,
            label: 'Espèces',
            description: 'Paiement en espèces',
            icon_path: null,
            display_order: 2,
            is_active: true
        },
        {
            id: 3,
            label: 'Chèque',
            description: 'Paiement par chèque',
            icon_path: null,
            display_order: 3,
            is_active: true
        }
        ];
        this.isLoaded = true;
        this.cache.lastUpdate = Date.now();
        console.log('📋 Modes de paiement par défaut chargés');
    },
    /**
     * Vérification de la validité du cache
     */
    isCacheValid() {
        if (!this.cache.lastUpdate) return false;
        return (Date.now() - this.cache.lastUpdate) < this.cache.cacheDuration;
    },
    /**
     * Population de tous les sélecteurs de modes de paiement
     */
    populateAllSelectors() {
        const selectors = [
            'payment-method', // Sélecteur principal
            'edit-payment-method', // Modal d'édition
            'payment-method-partial' // Commandes partielles
        ];
        selectors.forEach(selectorId => {
            this.populateSelector(selectorId);
        });
    },
    /**
     * Remplissage d'un sélecteur spécifique - MISE À JOUR ICON_PATH
     */
    populateSelector(selectorId) {
        const selector = document.getElementById(selectorId);
        if (!selector) {
            console.warn(`⚠️ Sélecteur #${selectorId} non trouvé`);
            return;
        }
        // Sauvegarde de la valeur actuelle
        const currentValue = selector.value;
        // Nettoyage du sélecteur
        selector.innerHTML = '<option value="">Sélectionnez un mode de paiement</option>';
        // Ajout des options avec ICON_PATH
        this.paymentMethods.forEach(method => {
            if (!method.is_active) return;
            const option = document.createElement('option');
            option.value = method.id;
            option.textContent = method.label;
            // NOUVEAU : Ajout des données pour icon_path
            if (method.icon_path) {
                option.setAttribute('data-icon-path', method.icon_path);
            }
            if (method.description) {
                option.setAttribute('data-description', method.description);
            }
            selector.appendChild(option);
        });
        // Restauration de la valeur si elle existe encore
        if (currentValue && this.paymentMethods.find(m => m.id.toString() === currentValue)) {
            selector.value = currentValue;
            this.updatePaymentDescription(this.getDescriptionElementId(selectorId), currentValue);
        }
        console.log(`✅ Sélecteur #${selectorId} mis à jour avec ${this.paymentMethods.length} options`);
    },
    /**
     * Mise à jour de la description d'un mode de paiement - AVEC ICON_PATH
     */
    updatePaymentDescription(descriptionElementId, paymentMethodId) {
        const descriptionElement = document.getElementById(descriptionElementId);
        if (!descriptionElement) return;
        if (!paymentMethodId) {
            descriptionElement.innerHTML = '';
            return;
        }
        const method = this.paymentMethods.find(m => m.id.toString() === paymentMethodId.toString());
        if (!method) {
            descriptionElement.innerHTML = '<span class="text-red-500">Mode de paiement non trouvé</span>';
            return;
        }
        // Construction du HTML avec icon_path
        let html = '';
        // NOUVEAU : Affichage de l'icône via icon_path
        if (method.icon_path) {
            html += `<img src="${method.icon_path}" alt="${method.label}" class="inline-block w-4 h-4 mr-2 align-middle">`;
        } else {
            // Icône par défaut si pas d'icon_path
            html += '<span class="material-icons text-sm mr-2 align-middle">payment</span>';
        }
        // Description
        if (method.description) {
            html += `<span class="text-gray-600">${method.description}</span>`;
        } else {
            html += `<span class="text-gray-500 italic">Mode de paiement : ${method.label}</span>`;
        }
        descriptionElement.innerHTML = html;
    },
    /**
     * Configuration des événements
     */
    setupEventListeners() {
        // Événements pour tous les sélecteurs
        const selectorIds = ['payment-method', 'edit-payment-method', 'payment-method-partial'];
        selectorIds.forEach(selectorId => {
            const selector = document.getElementById(selectorId);
            if (selector) {
                // Suppression des anciens événements
                selector.removeEventListener('change', this.handlePaymentMethodChange);
                // Ajout du nouvel événement
                selector.addEventListener('change', (event) => {
                    this.handlePaymentMethodChange(event, selectorId);
                });
            }
        });
        // Événement pour le rechargement des modes de paiement
        document.addEventListener('refreshPaymentMethods', () => {
            this.refreshPaymentMethods();
        });
    },
    /**
     * Gestionnaire de changement de mode de paiement
     */
    handlePaymentMethodChange(event, selectorId) {
        const selectedValue = event.target.value;
        const descriptionElementId = this.getDescriptionElementId(selectorId);
        // Mise à jour de la description
        this.updatePaymentDescription(descriptionElementId, selectedValue);
        // Validation en temps réel
        this.validatePaymentMethod(event.target);
        console.log(`💳 Mode de paiement sélectionné: ${selectedValue} pour #${selectorId}`);
    },
    /**
     * Obtention de l'ID de l'élément de description
     */
    getDescriptionElementId(selectorId) {
        const descriptionMap = {
            'payment-method': 'payment-method-description',
            'edit-payment-method': 'edit-payment-method-description',
            'payment-method-partial': 'payment-method-description-partial'
        };
        return descriptionMap[selectorId] || `${selectorId}-description`;
    },
    /**
     * Validation d'un champ mode de paiement
     */
    validatePaymentMethod(selectorOrValue) {
        let element = selectorOrValue;
        if (typeof selectorOrValue === 'string' && !(selectorOrValue instanceof Element)) {
            element = document.getElementById(selectorOrValue);
        }
        const value = element && element.value !== undefined ? element.value : selectorOrValue;
        const isValid = value !== '' && value !== undefined && value !== null;
        if (element instanceof Element) {
            if (isValid) {
                element.classList.remove('border-red-500', 'bg-red-50');
                element.classList.add('border-green-500');
            } else {
                element.classList.remove('border-green-500');
                element.classList.add('border-red-500', 'bg-red-50');
            }
        }
        return { valid: isValid, element };
    },
    /**
     * Rechargement forcé des modes de paiement
     */
    async refreshPaymentMethods() {
        try {
            console.log('🔄 Rechargement forcé des modes de paiement...');
            // Réinitialisation du cache
            this.isLoaded = false;
            this.cache.lastUpdate = null;
            // Rechargement
            await this.loadPaymentMethods();
            this.populateAllSelectors();
            // Notification
            this.showNotification('Modes de paiement mis à jour', 'success');
        } catch (error) {
            console.error('❌ Erreur lors du rechargement:', error);
            this.handleError('Erreur lors du rechargement des modes de paiement');
        }
    },
    /**
     * Récupération d'un mode de paiement par ID
     */
    getPaymentMethod(id) {
        return this.paymentMethods.find(method => method.id.toString() === id.toString());
    },
    /**
     * Vérification de la disponibilité d'un mode de paiement
     */
    isPaymentMethodAvailable(id) {
        const method = this.getPaymentMethod(id);
        return method && method.is_active;
    },
    /**
     * Gestion des erreurs
     */
    handleError(message) {
        console.error('❌ PaymentMethodsManager:', message);
        this.showNotification(message, 'error');
    },
    /**
     * Affichage des notifications
     */
    showNotification(message, type = 'info') {
        // Intégration avec le système de notifications existant
        if (typeof showNotification === 'function') {
            showNotification(message, type);
        } else {
            console.log(`${type.toUpperCase()}: ${message}`);
        }
    },
    /**
     * Nettoyage et destruction
     */
    destroy() {
        this.paymentMethods = [];
        this.isLoaded = false;
        this.cache.lastUpdate = null;
        console.log('🧹 PaymentMethodsManager détruit');
    }
};
/**
 * Gestionnaire de modification des commandes
 */
const EditOrderManager = {
    /**
     * Ouvre la modal de modification d'une commande
     */
    async openModal(orderId, expressionId, designation, sourceTable, quantity, unit, price, supplier) {
        const modal = document.getElementById('edit-order-modal');
        if (!modal) {
            console.error('Modal de modification introuvable');
            return;
        }
        // Remplir les champs du formulaire
        document.getElementById('edit-order-id').value = orderId;
        document.getElementById('edit-expression-id').value = expressionId;
        document.getElementById('edit-source-table').value = sourceTable;
        document.getElementById('edit-designation').value = designation;
        document.getElementById('edit-quantity').value = quantity;
        document.getElementById('edit-unit').value = unit;
        document.getElementById('edit-price').value = price;
        document.getElementById('edit-supplier').value = supplier;
        // Réinitialiser les notes
        document.getElementById('edit-notes').value = '';
        // CORRECTION : Charger les modes de paiement avec le nouveau gestionnaire
        await this.loadPaymentMethods();
        // Configurer l'autocomplétion des fournisseurs
        this.setupSupplierAutocomplete();
        // Afficher la modal
        modal.style.display = 'flex';
    },
    /**
     * Ferme la modal de modification
     */
    closeModal() {
        const modal = document.getElementById('edit-order-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    },
    /**
     * Charge les modes de paiement - VERSION CORRIGÉE
     */
    async loadPaymentMethods() {
        try {
            // CORRECTION : Utiliser le PaymentMethodsManager
            if (!PaymentMethodsManager.isLoaded) {
                await PaymentMethodsManager.init();
            }
            PaymentMethodsManager.populatePaymentSelect('edit-payment-method');
        } catch (error) {
            console.error('Erreur lors du chargement des modes de paiement:', error);
        }
    },
    /**
     * Configure l'autocomplétion des fournisseurs
     */
    setupSupplierAutocomplete() {
        const input = document.getElementById('edit-supplier');
        const suggestions = document.getElementById('edit-fournisseurs-suggestions');
        if (!input || !suggestions) return;
        // Supprimer les anciens écouteurs
        input.replaceWith(input.cloneNode(true));
        const newInput = document.getElementById('edit-supplier');
        newInput.addEventListener('input', () => {
            this.handleSupplierInput(newInput, suggestions);
        });
        document.addEventListener('click', (e) => {
            if (e.target !== newInput && !suggestions.contains(e.target)) {
                suggestions.classList.add('hidden');
            }
        });
    },
    /**
     * Gère la saisie dans le champ fournisseur
     */
    handleSupplierInput(input, suggestions) {
        const value = input.value.toLowerCase().trim();
        suggestions.innerHTML = '';
        if (value.length < 2) {
            suggestions.classList.add('hidden');
            return;
        }
        const matches = AppState.suppliersList
            .filter(supplier => supplier.toLowerCase().includes(value))
            .slice(0, 8);
        if (matches.length > 0) {
            suggestions.classList.remove('hidden');
            matches.forEach(supplier => {
                const div = document.createElement('div');
                div.className = 'fournisseur-suggestion';
                const index = supplier.toLowerCase().indexOf(value);
                if (index !== -1) {
                    const before = supplier.substring(0, index);
                    const match = supplier.substring(index, index + value.length);
                    const after = supplier.substring(index + value.length);
                    div.innerHTML = `${Utils.escapeHtml(before)}<strong>${Utils.escapeHtml(match)}</strong>${Utils.escapeHtml(after)}`;
                } else {
                    div.textContent = supplier;
                }
                div.onclick = () => {
                    input.value = supplier;
                    suggestions.innerHTML = '';
                    suggestions.classList.add('hidden');
                };
                suggestions.appendChild(div);
            });
        } else {
            suggestions.classList.add('hidden');
        }
    },
    /**
     * Traite la soumission du formulaire de modification
     */
    async handleSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        // Validation
        if (!this.validateForm(form)) return;
        // Afficher un indicateur de chargement
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="material-icons animate-spin mr-1">refresh</span>Sauvegarde...';
        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                this.closeModal();
                Swal.fire({
                    title: 'Succès!',
                    text: data.message || 'Commande modifiée avec succès!',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Erreur',
                    text: data.message || 'Une erreur est survenue lors de la modification.',
                    icon: 'error'
                });
            }
        } catch (error) {
            console.error('Erreur:', error);
            Swal.fire({
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la communication avec le serveur.',
                icon: 'error'
            });
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    },
    /**
     * Valide le formulaire de modification - VERSION CORRIGÉE
     */
    validateForm(form) {
        const quantity = parseFloat(form.quantity.value);
        const price = parseFloat(form.prix_unitaire.value);
        const supplier = form.fournisseur.value.trim();
        const paymentMethod = form.payment_method;
        if (isNaN(quantity) || quantity <= 0) {
            Swal.fire({
                title: 'Quantité invalide',
                text: 'Veuillez saisir une quantité valide supérieure à 0.',
                icon: 'error'
            });
            return false;
        }
        if (isNaN(price) || price <= 0) {
            Swal.fire({
                title: 'Prix invalide',
                text: 'Veuillez saisir un prix unitaire valide supérieur à 0.',
                icon: 'error'
            });
            return false;
        }
        if (!supplier) {
            Swal.fire({
                title: 'Fournisseur manquant',
                text: 'Veuillez sélectionner un fournisseur.',
                icon: 'error'
            });
            return false;
        }
        // CORRECTION : Utiliser PaymentMethodsManager pour la validation
        if (!PaymentMethodsManager.validatePaymentMethod(paymentMethod).valid) {
            return false;
        }
        return true;
    }
};
// État global de l'application
const AppState = {
    suppliersList: [],
    selectedMaterials: new Set(),
    selectedPartialMaterials: new Set(),
    selectedOrderedMaterials: new Set(),
    selectedPendingMaterials: new Set()
};
/**
 * Module principal de l'application
 */
const AchatsMateriauxApp = {
    /**
     * Initialisation de l'application
     */
    init() {
        console.log('Initialisation de l\'application Achats Matériaux');
        // Attendre que le DOM soit chargé
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.onDOMReady());
        } else {
            this.onDOMReady();
        }
    },
    /**
     * Actions à effectuer une fois le DOM chargé
     */
    async onDOMReady() {
        // CORRECTION : Initialiser les modes de paiement en premier
        await PaymentMethodsManager.init();
        // Initialisation des autres modules
        DateTimeModule.init();
        TabsManager.init();
        EventHandlers.init();
        DataTablesManager.init();
        FournisseursModule.init();
        PartialOrdersManager.init();
        // Vérifications initiales
        this.performInitialChecks();
    },
    /**
     * Vérifications initiales au chargement
     */
    performInitialChecks() {
        // Vérifier les nouveaux matériaux
        this.checkNewMaterials();
        // Vérifier les validations finance après un court délai
        setTimeout(() => {
            OrderValidationChecker.check();
        }, 1000);
        // Configurer les intervalles de rafraîchissement
        setInterval(() => this.checkNewMaterials(), CONFIG.REFRESH_INTERVALS.CHECK_MATERIALS);
        setInterval(() => OrderValidationChecker.check(), CONFIG.REFRESH_INTERVALS.CHECK_VALIDATION);
    },
    /**
     * Vérification des nouveaux matériaux
     */
    async checkNewMaterials() {
        try {
            const response = await fetch(CONFIG.API_URLS.CHECK_MATERIALS);
            const data = await response.json();
            NotificationsManager.updateMaterialsNotification(data);
        } catch (error) {
            console.error('Erreur lors de la vérification des matériaux:', error);
        }
    }
};
/**
 * Module de gestion de la date et heure
 */
const DateTimeModule = {
    init() {
        this.updateDateTime();
        setInterval(() => this.updateDateTime(), CONFIG.REFRESH_INTERVALS.DATETIME);
    },
    updateDateTime() {
        const element = document.getElementById('date-time-display');
        if (element) {
            const now = new Date();
            element.textContent = `${now.toLocaleDateString('fr-FR')} ${now.toLocaleTimeString('fr-FR')}`;
        }
    }
};
/**
 * Gestionnaire des onglets
 */
const TabsManager = {
    tabs: [{
        tabId: 'tab-materials',
        contentId: 'content-materials'
    },
    {
        tabId: 'tab-grouped',
        contentId: 'content-grouped'
    },
    {
        tabId: 'tab-recents',
        contentId: 'content-recents'
    },
    {
        tabId: 'tab-returns',
        contentId: 'content-returns'
    },
    {
        tabId: 'tab-canceled',
        contentId: 'content-canceled'
    }
    ],
    init() {
        this.setupMainTabs();
        this.setupMaterialTabs();
        this.checkURLParams();
    },
    setupMainTabs() {
        this.tabs.forEach(({
            tabId,
            contentId
        }) => {
            const tab = document.getElementById(tabId);
            const content = document.getElementById(contentId);
            if (tab && content) {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.activateTab(tab, content);
                });
            }
        });
    },
    setupMaterialTabs() {
        document.querySelectorAll('.materials-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.activateMaterialTab(tab);
            });
        });
    },
    activateTab(activeTab, activeContent) {
        // Réinitialiser tous les onglets
        this.tabs.forEach(({
            tabId
        }) => {
            const tab = document.getElementById(tabId);
            if (tab) {
                tab.classList.remove('border-blue-500', 'text-blue-600');
                tab.classList.add('border-transparent', 'text-gray-500');
            }
        });
        // Cacher tous les contenus
        this.tabs.forEach(({
            contentId
        }) => {
            const content = document.getElementById(contentId);
            if (content) content.classList.add('hidden');
        });
        // Activer l'onglet sélectionné
        activeTab.classList.remove('border-transparent', 'text-gray-500');
        activeTab.classList.add('border-blue-500', 'text-blue-600');
        activeContent.classList.remove('hidden');
        // Réajuster les DataTables
        DataTablesManager.adjustTables();
    },
    activateMaterialTab(tab) {
        const tabs = document.querySelectorAll('.materials-tab');
        // Désactiver tous les onglets
        tabs.forEach(t => {
            t.classList.remove('active', 'text-blue-600', 'border-blue-600');
            t.classList.add('text-gray-500', 'border-transparent');
        });
        // Activer l'onglet cliqué
        tab.classList.add('active', 'text-blue-600', 'border-blue-600');
        tab.classList.remove('text-gray-500', 'border-transparent');
        // Gérer l'affichage des sections
        const targetId = tab.getAttribute('data-target');
        document.querySelectorAll('.materials-section').forEach(section => {
            section.classList.add('hidden');
        });
        const targetSection = document.getElementById(targetId);
        if (targetSection) {
            targetSection.classList.remove('hidden');
            // Charger les données si nécessaire
            if (targetId === 'materials-partial') {
                PartialOrdersManager.load(false);
            }
            DataTablesManager.adjustMaterialTable(targetId);
        }
    },
    checkURLParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam === 'recents') {
            const recentsTab = document.getElementById('tab-recents');
            const recentsContent = document.getElementById('content-recents');
            if (recentsTab && recentsContent) {
                this.activateTab(recentsTab, recentsContent);
            }
        }
    }
};
/**
 * Gestionnaire des événements
 */
const EventHandlers = {
    init() {
        this.setupGeneralEvents();
        this.setupModalEvents();
        this.setupFormEvents();
        this.setupCheckboxEvents();
        this.setupButtonEvents();
    },
    setupGeneralEvents() {
        // Case à cocher "Tout sélectionner" pour les matériaux en attente
        const selectAllPending = document.getElementById('select-all-pending-materials');
        if (selectAllPending) {
            selectAllPending.addEventListener('change', (e) => {
                const isChecked = e.target.checked;
                document.querySelectorAll('#materials-pending .material-checkbox').forEach(checkbox => {
                    checkbox.checked = isChecked;
                    SelectionManager.updateSelection('pending', checkbox);
                });
                ButtonStateManager.updateAllButtons();
            });
        }
        // Case à cocher "Tout sélectionner" pour les matériaux commandés
        const selectAllOrdered = document.getElementById('select-all-ordered-materials');
        if (selectAllOrdered) {
            selectAllOrdered.addEventListener('change', (e) => {
                const isChecked = e.target.checked;
                document.querySelectorAll('.ordered-material-checkbox').forEach(checkbox => {
                    checkbox.checked = isChecked;
                    SelectionManager.updateSelection('ordered', checkbox);
                });
                ButtonStateManager.updateCancelButton();
            });
        }
        // Case à cocher "Tout sélectionner" pour les commandes partielles
        const selectAllPartial = document.getElementById('select-all-partial-materials');
        if (selectAllPartial) {
            selectAllPartial.addEventListener('change', (e) => {
                const isChecked = e.target.checked;
                document.querySelectorAll('.partial-material-checkbox').forEach(checkbox => {
                    checkbox.checked = isChecked;
                    SelectionManager.updateSelection('partial', checkbox);
                });
                ButtonStateManager.updateAllButtons();
            });
        }
    },
    setupModalEvents() {
        // Fermeture des modals
        document.querySelectorAll('.close-modal-btn, .modal').forEach(element => {
            element.addEventListener('click', (e) => {
                if (e.target.classList.contains('close-modal-btn') ||
                    e.target.classList.contains('modal')) {
                    const modal = e.target.closest('.modal') || e.target;
                    ModalManager.close(modal);
                }
            });
        });
    },
    setupFormEvents() {
        // Formulaire d'achat individuel
        const purchaseForm = document.getElementById('purchase-form');
        if (purchaseForm) {
            purchaseForm.addEventListener('submit', (e) => {
                e.preventDefault();
                PurchaseManager.handleIndividualPurchase(e);
            });
        }
        // Formulaire d'achat groupé
        const bulkPurchaseForm = document.getElementById('bulk-purchase-form');
        if (bulkPurchaseForm) {
            bulkPurchaseForm.addEventListener('submit', (e) => {
                e.preventDefault();
                PurchaseManager.handleBulkPurchase(e);
            });
        }
        // Formulaire de substitution
        const substitutionForm = document.getElementById('substitution-form');
        if (substitutionForm) {
            substitutionForm.addEventListener('submit', (e) => {
                e.preventDefault();
                SubstitutionManager.handleSubmit(e);
            });
        }
        // Changement de type de prix
        const priceTypeSelect = document.getElementById('price-type');
        if (priceTypeSelect) {
            priceTypeSelect.addEventListener('change', () => {
                PurchaseManager.togglePriceInputs();
            });
        }
        // Changement de raison de substitution
        const substitutionReason = document.getElementById('substitution-reason');
        if (substitutionReason) {
            substitutionReason.addEventListener('change', function () {
                const otherReasonContainer = document.getElementById('other-reason-container');
                if (otherReasonContainer) {
                    otherReasonContainer.style.display = this.value === 'autre' ? 'block' : 'none';
                }
            });
        }
    },
    setupCheckboxEvents() {
        // Checkboxes des matériaux en attente
        document.querySelectorAll('#materials-pending .material-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                SelectionManager.updateSelection('pending', checkbox);
                ButtonStateManager.updateAllButtons();
            });
        });
        // Checkboxes des matériaux commandés
        document.querySelectorAll('.ordered-material-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                SelectionManager.updateSelection('ordered', checkbox);
                ButtonStateManager.updateCancelButton();
            });
        });
    },
    setupButtonEvents() {
        // Bouton d'achat groupé
        const bulkPurchaseBtn = document.getElementById('bulk-purchase-btn');
        if (bulkPurchaseBtn) {
            bulkPurchaseBtn.addEventListener('click', () => {
                ModalManager.openBulkPurchase();
            });
        }
        // Bouton de complétion des commandes partielles
        const bulkCompleteBtn = document.getElementById('bulk-complete-btn');
        if (bulkCompleteBtn) {
            bulkCompleteBtn.addEventListener('click', () => {
                ModalManager.openBulkComplete();
            });
        }
        // Bouton d'annulation multiple pour matériaux en attente
        const bulkCancelPendingBtn = document.getElementById('bulk-cancel-pending-btn');
        if (bulkCancelPendingBtn) {
            bulkCancelPendingBtn.addEventListener('click', () => {
                CancelManager.cancelMultiplePending();
            });
        }
        // Bouton d'annulation multiple pour matériaux commandés
        const bulkCancelBtn = document.getElementById('bulk-cancel-btn');
        if (bulkCancelBtn) {
            bulkCancelBtn.addEventListener('click', () => {
                CancelManager.cancelMultipleOrders();
            });
        }
        // Bouton d'actualisation des commandes partielles
        const refreshListBtn = document.getElementById('refresh-list');
        if (refreshListBtn) {
            refreshListBtn.addEventListener('click', () => {
                PartialOrdersManager.load(false);
            });
        }
        // Bouton d'export Excel
        const exportExcelBtn = document.getElementById('export-excel');
        if (exportExcelBtn) {
            exportExcelBtn.addEventListener('click', () => {
                ExportManager.exportPartialOrdersToExcel();
            });
        }
    }
};
/**
 * Gestionnaire de DataTables
 */
const DataTablesManager = {
    tables: {
        pending: null,
        ordered: null,
        grouped: null,
        recent: null,
        returns: null,
        partial: null,
        canceled: null
    },
    init() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
            console.error('jQuery ou DataTables non chargé');
            return;
        }
        jQuery(document).ready(() => {
            this.initializeTables();
        });
    },
    initializeTables() {
        this.initPendingMaterialsTable();
        this.initOrderedMaterialsTable();
        this.initGroupedProjectsTable();
        this.initRecentPurchasesTable();
        this.initSupplierReturnsTable();
        this.initCanceledOrdersTable();
    },
    initPendingMaterialsTable() {
        if (!jQuery('#pendingMaterialsTable').length) return;
        this.tables.pending = jQuery('#pendingMaterialsTable').DataTable({
            responsive: true,
            language: {
                url: CONFIG.DATATABLES.LANGUAGE_URL
            },
            dom: CONFIG.DATATABLES.DOM,
            buttons: CONFIG.DATATABLES.BUTTONS,
            columnDefs: [{
                orderable: false,
                targets: [0, 9]
            },
            {
                type: 'date-fr',
                targets: 8
            }
            ],
            order: [
                [8, 'desc']
            ],
            pageLength: CONFIG.DATATABLES.PAGE_LENGTH,
            drawCallback: function () {
                // Réinitialiser l'état de la checkbox "Tout sélectionner"
                const selectAllCheckbox = document.getElementById('select-all-pending-materials');
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
                // Restaurer l'état des checkboxes basé sur notre Map de sélection
                document.querySelectorAll('#pendingMaterialsTable .material-checkbox').forEach(checkbox => {
                    if (checkbox.dataset.id) {
                        checkbox.checked = SelectionManager.isSelected('pending', checkbox.dataset.id);
                        checkbox.addEventListener('change', () => {
                            SelectionManager.updateSelection('pending', checkbox);
                        });
                    }
                });
                ButtonStateManager.updateBulkPurchaseButton();
                ButtonStateManager.updateCancelPendingButton();
            },
            stateSave: true
        });
    },
    initOrderedMaterialsTable() {
        if (!jQuery('#orderedMaterialsTable').length) return;
        this.tables.ordered = jQuery('#orderedMaterialsTable').DataTable({
            responsive: true,
            language: {
                url: CONFIG.DATATABLES.LANGUAGE_URL
            },
            dom: CONFIG.DATATABLES.DOM,
            buttons: CONFIG.DATATABLES.BUTTONS,
            columnDefs: [{
                orderable: false,
                targets: [0, 11]
            },
            {
                type: 'date-fr',
                targets: 9
            },
            {
                type: 'num',
                targets: 10
            }
            ],
            order: [
                [9, 'desc']
            ],
            pageLength: CONFIG.DATATABLES.PAGE_LENGTH,
            drawCallback: () => {
                this.resetSelectAll('select-all-ordered-materials');
                this.attachCheckboxEvents('.ordered-material-checkbox', 'ordered');
                ButtonStateManager.updateCancelButton();
            }
        });
    },
    initGroupedProjectsTable() {
        if (!jQuery('#groupedProjectsTable').length) return;
        this.tables.grouped = jQuery('#groupedProjectsTable').DataTable({
            responsive: true,
            language: {
                url: CONFIG.DATATABLES.LANGUAGE_URL
            },
            order: [
                [0, 'desc']
            ],
            pageLength: 10
        });
    },
    initRecentPurchasesTable() {
        if (!jQuery('#recentPurchasesTable').length) return;
        this.tables.recent = jQuery('#recentPurchasesTable').DataTable({
            responsive: true,
            language: {
                url: CONFIG.DATATABLES.LANGUAGE_URL
            },
            dom: CONFIG.DATATABLES.DOM,
            buttons: CONFIG.DATATABLES.BUTTONS,
            columnDefs: [{
                type: 'date-fr',
                targets: 8
            },
            {
                targets: 9,
                render: (data, type, row) => {
                    const expressionId = row[10] || '';
                    const orderId = row[11] || '';
                    let designation = row[2] || '';
                    designation = designation.replace(/<[^>]*>/g, '');
                    const cleanDesignation = Utils.escapeString(designation);
                    return `
                                <button onclick="generateBonCommande('${expressionId}')" 
                                    class="btn-action text-green-600 hover:text-green-800 mr-2" 
                                    title="Générer bon de commande">
                                    <span class="material-icons">receipt</span>
                                </button>
                                <button onclick="viewOrderDetails('${orderId}', '${expressionId}', '${cleanDesignation}')" 
                                    class="btn-action text-blue-600 hover:text-blue-800 mr-2" 
                                    title="Voir les détails">
                                    <span class="material-icons">visibility</span>
                                </button>
                                <button onclick="viewStockDetails('${cleanDesignation}')" 
                                    class="btn-action text-purple-600 hover:text-purple-800" 
                                    title="Voir dans le stock">
                                    <span class="material-icons">inventory_2</span>
                                </button>
                            `;
                }
            }
            ],
            order: [
                [8, 'desc']
            ],
            pageLength: CONFIG.DATATABLES.PAGE_LENGTH
        });
    },
    initSupplierReturnsTable() {
        if (!jQuery('#supplierReturnsTable').length) return;
        this.tables.returns = jQuery('#supplierReturnsTable').DataTable({
            responsive: true,
            language: {
                url: CONFIG.DATATABLES.LANGUAGE_URL
            },
            dom: CONFIG.DATATABLES.DOM,
            buttons: CONFIG.DATATABLES.BUTTONS,
            columnDefs: [{
                type: 'date-fr',
                targets: 4
            }],
            order: [
                [4, 'desc']
            ],
            pageLength: CONFIG.DATATABLES.PAGE_LENGTH,
            ajax: {
                url: 'statistics/retour-fournisseur/api_getSupplierReturns.php',
                type: 'GET',
                dataSrc: (json) => {
                    const uniqueData = [];
                    const seenIds = new Set();
                    if (json.data && Array.isArray(json.data)) {
                        json.data.forEach(item => {
                            if (!seenIds.has(item.id)) {
                                seenIds.add(item.id);
                                uniqueData.push(item);
                            }
                        });
                    }
                    return uniqueData;
                }
            },
            columns: [{
                data: 'product_name'
            },
            {
                data: 'supplier_name'
            },
            {
                data: 'quantity'
            },
            {
                data: 'reason'
            },
            {
                data: 'created_at'
            },
            {
                data: 'status',
                render: (data) => {
                    const statusMap = {
                        'completed': {
                            class: 'bg-green-100 text-green-800',
                            text: 'Complété'
                        },
                        'cancelled': {
                            class: 'bg-red-100 text-red-800',
                            text: 'Annulé'
                        },
                        'default': {
                            class: 'bg-yellow-100 text-yellow-800',
                            text: 'En attente'
                        }
                    };
                    const status = statusMap[data] || statusMap.default;
                    return `<span class="px-2 py-1 text-xs rounded-full ${status.class}">${status.text}</span>`;
                }
            },
            {
                data: 'id',
                render: (data) => `
                            <button onclick="viewReturnDetails(${data})" class="text-blue-600 hover:text-blue-800" title="Voir les détails">
                                <span class="material-icons text-sm">visibility</span>
                            </button>
                        `
            }
            ]
        });
    },
    initCanceledOrdersTable() {
        if (!jQuery('#canceledOrdersTable').length) return;
        this.tables.canceled = jQuery('#canceledOrdersTable').DataTable({
            responsive: true,
            language: {
                url: CONFIG.DATATABLES.LANGUAGE_URL
            },
            ajax: {
                url: 'api_canceled/api_getCanceledOrders.php',
                dataSrc: (json) => {
                    NotificationsManager.updateCanceledOrdersStats(json.stats);
                    return json.data || [];
                }
            },
            columns: [{
                data: 'code_projet'
            },
            {
                data: 'nom_client'
            },
            {
                data: 'designation'
            },
            {
                data: 'original_status',
                orderable: false
            },
            {
                data: 'quantity'
            },
            {
                data: 'fournisseur'
            },
            {
                data: 'canceled_at'
            },
            {
                data: 'cancel_reason'
            },
            {
                data: 'id',
                render: (data) => `
                            <button onclick="viewCanceledOrderDetails(${data})" class="text-blue-600 hover:text-blue-800">
                                <span class="material-icons text-sm">visibility</span>
                            </button>
                        `,
                orderable: false
            }
            ],
            columnDefs: [{
                type: 'date-fr',
                targets: 6
            }],
            order: [
                [6, 'desc']
            ],
            pageLength: CONFIG.DATATABLES.PAGE_LENGTH
        });
    },
    resetSelectAll(checkboxId) {
        const checkbox = document.getElementById(checkboxId);
        if (checkbox) checkbox.checked = false;
    },
    attachCheckboxEvents(selector, type) {
        document.querySelectorAll(selector).forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                SelectionManager.updateSelection(type, checkbox);
                switch (type) {
                    case 'pending':
                        ButtonStateManager.updateAllButtons();
                        break;
                    case 'ordered':
                        ButtonStateManager.updateCancelButton();
                        break;
                    case 'partial':
                        ButtonStateManager.updateAllButtons();
                        break;
                }
            });
        });
    },
    adjustTables() {
        Object.entries(this.tables).forEach(([key, table]) => {
            if (table) {
                table.columns.adjust().responsive.recalc();
            }
        });
    },
    adjustMaterialTable(targetId) {
        const tableMap = {
            'materials-pending': 'pending',
            'materials-ordered': 'ordered',
            'materials-partial': 'partial'
        };
        const tableKey = tableMap[targetId];
        if (tableKey && this.tables[tableKey]) {
            this.tables[tableKey].columns.adjust().responsive.recalc();
        }
    }
};
/**
 * Gestionnaire des sélections - VERSION AMÉLIORÉE
 */
const SelectionManager = {
    // Utiliser des Maps pour stocker les données complètes des matériaux sélectionnés par type
    selectionMaps: {
        'pending': new Map(),
        'ordered': new Map(),
        'partial': new Map()
    },
    /**
     * Met à jour la sélection d'un matériau
     */
    updateSelection(type, checkbox) {
        if (!this.selectionMaps[type]) return;
        const materialData = this.extractMaterialData(checkbox);
        const materialId = materialData.id;
        if (checkbox.checked) {
            this.selectionMaps[type].set(materialId, materialData);
        } else {
            this.selectionMaps[type].delete(materialId);
        }
        this.updateSelectionCounter(type);
    },
    /**
     * Extrait les données du matériau depuis les attributs data-* de la checkbox
     */
    extractMaterialData(checkbox) {
        return {
            id: checkbox.dataset.id || checkbox.getAttribute('data-id'),
            expressionId: checkbox.dataset.expression || checkbox.dataset.expressionId || checkbox.getAttribute('data-expression'),
            designation: checkbox.dataset.designation || checkbox.getAttribute('data-designation'),
            quantity: checkbox.dataset.quantity || checkbox.getAttribute('data-quantity'),
            unit: checkbox.dataset.unit || checkbox.getAttribute('data-unit'),
            sourceTable: checkbox.dataset.sourceTable || checkbox.getAttribute('data-source-table') || 'expression_dym',
            project: checkbox.dataset.project || checkbox.getAttribute('data-project') || ''
        };
    },
    /**
     * Récupère les matériaux sélectionnés d'un type donné
     */
    getSelectedMaterials(type) {
        if (!this.selectionMaps[type]) return [];
        return Array.from(this.selectionMaps[type].values());
    },
    /**
     * Récupère les matériaux directement depuis le DOM pour plus de fiabilité
     */
    getSelectedMaterialsFromDOM(type) {
        const materials = [];
        let selector = '';
        switch (type) {
            case 'pending':
                selector = '#pendingMaterialsTable .material-checkbox:checked';
                break;
            case 'ordered':
                selector = '.ordered-material-checkbox:checked';
                break;
            case 'partial':
                selector = '.partial-material-checkbox:checked';
                break;
        }
        if (selector) {
            document.querySelectorAll(selector).forEach(checkbox => {
                if (checkbox.dataset.id) {
                    materials.push(this.extractMaterialData(checkbox));
                }
            });
        }
        return materials;
    },
    /**
     * Met à jour le compteur dans le bouton correspondant au type
     */
    updateSelectionCounter(type) {
        switch (type) {
            case 'pending':
                ButtonStateManager.updateBulkPurchaseButton();
                ButtonStateManager.updateCancelPendingButton();
                break;
            case 'ordered':
                ButtonStateManager.updateCancelButton();
                break;
            case 'partial':
                ButtonStateManager.updateBulkCompleteButton();
                break;
        }
    },
    /**
     * Vérifie si un matériau est sélectionné
     */
    isSelected(type, id) {
        return this.selectionMaps[type] ? this.selectionMaps[type].has(id) : false;
    },
    /**
     * Réinitialise les sélections d'un type
     */
    clearSelections(type) {
        if (this.selectionMaps[type]) {
            this.selectionMaps[type].clear();
            this.updateSelectionCounter(type);
        }
    },
    /**
     * Synchronise les sélections entre la Map et le DOM
     */
    syncSelections(type) {
        const domMaterials = this.getSelectedMaterialsFromDOM(type);
        this.selectionMaps[type].clear();
        domMaterials.forEach(material => {
            this.selectionMaps[type].set(material.id, material);
        });
        this.updateSelectionCounter(type);
    }
};
/**
 * Gestionnaire des états des boutons
 */
const ButtonStateManager = {
    updateAllButtons() {
        this.updateBulkPurchaseButton();
        this.updateBulkCompleteButton();
        this.updateCancelPendingButton();
    },
    updateBulkPurchaseButton() {
        const button = document.getElementById('bulk-purchase-btn');
        if (!button) return;
        const selectedCount = SelectionManager.selectionMaps.pending.size;
        button.disabled = selectedCount === 0;
        button.innerHTML = `
                <span class="material-icons align-middle mr-1">shopping_basket</span>
                Commander les éléments sélectionnés${selectedCount > 0 ? ' (' + selectedCount + ')' : ''}
            `;
    },
    updateBulkCompleteButton() {
        const button = document.getElementById('bulk-complete-btn');
        if (!button) return;
        const selectedCount = document.querySelectorAll('.partial-material-checkbox:checked').length;
        button.disabled = selectedCount === 0;
        button.innerHTML = `
                <span class="material-icons text-sm mr-1">shopping_basket</span>
                Compléter les commandes sélectionnées${selectedCount > 0 ? ' (' + selectedCount + ')' : ''}
            `;
    },
    updateCancelPendingButton() {
        const button = document.getElementById('bulk-cancel-pending-btn');
        if (!button) return;
        const selectedCount = document.querySelectorAll('#pendingMaterialsTable .material-checkbox:checked').length;
        button.disabled = selectedCount === 0;
        button.innerHTML = `
                <span class="material-icons text-sm mr-1">cancel</span>
                Annuler les matériaux sélectionnés${selectedCount > 0 ? ' (' + selectedCount + ')' : ''}
            `;
    },
    updateCancelButton() {
        const button = document.getElementById('bulk-cancel-btn');
        if (!button) return;
        const selectedCount = document.querySelectorAll('.ordered-material-checkbox:checked').length;
        button.disabled = selectedCount === 0;
        button.innerHTML = `
                <span class="material-icons text-sm mr-1">cancel</span>
                Annuler les commandes sélectionnées${selectedCount > 0 ? ' (' + selectedCount + ')' : ''}
            `;
    }
};
/**
 * Gestionnaire des fournisseurs
 */
const FournisseursModule = {
    async init() {
        try {
            const response = await fetch(CONFIG.API_URLS.FOURNISSEURS);
            const data = await response.json();
            AppState.suppliersList = data;
            this.initializeAutocomplete();
            console.log(`${data.length} fournisseurs chargés avec succès`);
        } catch (error) {
            console.error('Erreur lors du chargement des fournisseurs:', error);
            this.showError();
        }
    },
    initializeAutocomplete() {
        this.setupAutocomplete('fournisseur', 'fournisseurs-suggestions');
        this.setupAutocomplete('fournisseur-bulk', 'fournisseurs-suggestions-bulk');
    },
    setupAutocomplete(inputId, suggestionsId) {
        const input = document.getElementById(inputId);
        const suggestions = document.getElementById(suggestionsId);
        if (!input || !suggestions) return;
        input.addEventListener('input', () => {
            this.handleAutocompleteInput(input, suggestions);
        });
        document.addEventListener('click', (e) => {
            if (e.target !== input && !suggestions.contains(e.target)) {
                suggestions.classList.remove('active');
            }
        });
    },
    handleAutocompleteInput(input, suggestions) {
        const value = input.value.toLowerCase().trim();
        suggestions.innerHTML = '';
        if (value.length < 2) {
            suggestions.classList.remove('active');
            return;
        }
        const matches = AppState.suppliersList
            .filter(supplier => supplier.toLowerCase().includes(value))
            .slice(0, 8);
        if (matches.length > 0) {
            suggestions.classList.add('active');
            matches.forEach(supplier => {
                const div = this.createSuggestionItem(supplier, value);
                div.onclick = () => {
                    input.value = supplier;
                    suggestions.innerHTML = '';
                    suggestions.classList.remove('active');
                };
                suggestions.appendChild(div);
            });
            // Ajouter l'option de gestion des fournisseurs
            const manageDiv = this.createManageOption();
            suggestions.appendChild(manageDiv);
        } else {
            suggestions.classList.remove('active');
        }
    },
    createSuggestionItem(supplier, searchValue) {
        const div = document.createElement('div');
        div.className = 'fournisseur-suggestion';
        const index = supplier.toLowerCase().indexOf(searchValue);
        if (index !== -1) {
            const before = supplier.substring(0, index);
            const match = supplier.substring(index, index + searchValue.length);
            const after = supplier.substring(index + searchValue.length);
            div.innerHTML = `${Utils.escapeHtml(before)}<strong>${Utils.escapeHtml(match)}</strong>${Utils.escapeHtml(after)}`;
        } else {
            div.textContent = supplier;
        }
        return div;
    },
    createManageOption() {
        const div = document.createElement('div');
        div.className = 'fournisseur-suggestion text-blue-600';
        div.innerHTML = `<span class="material-icons text-sm mr-1 align-middle">add</span> Gérer les fournisseurs`;
        div.onclick = () => window.open('../fournisseurs/fournisseurs.php', '_blank');
        return div;
    },
    async checkAndCreate(fournisseurName) {
        if (!fournisseurName || fournisseurName.trim() === '') {
            throw new Error('Veuillez saisir un nom de fournisseur');
        }
        const formData = new FormData();
        formData.append('fournisseur', fournisseurName);
        try {
            const response = await fetch(CONFIG.API_URLS.CHECK_FOURNISSEUR, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                return {
                    success: true,
                    newFournisseur: !data.exists,
                    name: fournisseurName,
                    id: data.id
                };
            } else {
                throw new Error(data.message || 'Erreur lors de la vérification du fournisseur');
            }
        } catch (error) {
            console.error('Erreur lors de la vérification du fournisseur:', error);
            throw error;
        }
    },
    showError() {
        Swal.fire({
            title: 'Information',
            text: 'Impossible de charger la liste des fournisseurs. Vous pouvez quand même saisir manuellement le nom du fournisseur.',
            icon: 'info',
            confirmButtonText: 'OK'
        });
    }
};
/**
 * Gestionnaire des modals
 */
const ModalManager = {
    openPurchase(expressionId, designation, quantity, unit, fournisseur = '') {
        // Remplir les champs du formulaire
        document.getElementById('expression_id').value = expressionId;
        document.getElementById('designation').value = designation;
        document.getElementById('quantite').value = quantity;
        document.getElementById('unite').value = unit;
        if (fournisseur) {
            document.getElementById('fournisseur').value = fournisseur;
        }
        // Afficher le modal
        const modal = document.getElementById('purchase-modal');
        if (modal) modal.style.display = 'flex';
        // Chercher et charger le prix si possible
        this.loadMaterialPrice(expressionId, designation);
    },
    async loadMaterialPrice(expressionId, designation) {
        // Chercher l'ID du matériau correspondant
        const checkboxes = document.querySelectorAll('.material-checkbox');
        let materialId = null;
        for (const checkbox of checkboxes) {
            if (checkbox.dataset.expression === expressionId &&
                checkbox.dataset.designation === designation) {
                materialId = checkbox.dataset.id;
                break;
            }
        }
        if (!materialId) return;
        try {
            const response = await fetch(`${CONFIG.API_URLS.MATERIAL_INFO}?material_id=${materialId}`);
            const data = await response.json();
            if (data && data.prix_unitaire && parseFloat(data.prix_unitaire) > 0) {
                const prixField = document.getElementById('prix');
                if (prixField) {
                    prixField.value = data.prix_unitaire;
                }
            }
        } catch (error) {
            console.error("Erreur lors de la récupération des infos matériau:", error);
        }
    },
    async openBulkPurchase() {
        const selectedMaterials = SelectionManager.getSelectedMaterials('pending');
        if (selectedMaterials.length === 0) {
            Swal.fire({
                title: 'Aucun matériau sélectionné',
                text: 'Veuillez sélectionner au moins un matériau à acheter.',
                icon: 'warning'
            });
            return;
        }
        this.prepareBulkPurchaseModal(selectedMaterials);
    },
    async prepareBulkPurchaseModal(materials) {
        const container = document.getElementById('selected-materials-container');
        const tbody = document.getElementById('individual-prices-tbody');
        if (!container || !tbody) return;
        // Réinitialiser le contenu
        container.innerHTML = `<p class="mb-2">Vous avez sélectionné <strong>${materials.length}</strong> matériaux à acheter.</p>`;
        tbody.innerHTML = '';
        // Ajouter les champs cachés
        materials.forEach(material => {
            container.innerHTML += `
                    <input type="hidden" name="material_ids[]" value="${material.id}">
                    <input type="hidden" name="source_table[${material.id}]" value="${material.sourceTable || 'expression_dym'}">
                `;
        });
        // Initialiser l'autocomplétion
        FournisseursModule.setupAutocomplete('fournisseur-bulk', 'fournisseurs-suggestions-bulk');
        // CORRECTION : Peupler les modes de paiement
        PaymentMethodsManager.populatePaymentSelect('payment-method-bulk');
        // Afficher le modal
        const modal = document.getElementById('bulk-purchase-modal');
        if (modal) {
            const modalTitle = modal.querySelector('h2');
            if (modalTitle) modalTitle.textContent = 'Achat groupé de matériaux';
            const confirmButton = modal.querySelector('#confirm-bulk-purchase');
            if (confirmButton) confirmButton.textContent = 'Passer la commande';
            modal.style.display = 'flex';
        }
        // Charger les prix
        await this.loadBulkPrices(materials);
    },
    async loadBulkPrices(materials) {
        const tbody = document.getElementById('individual-prices-tbody');
        const commonPrice = document.getElementById('common-price');
        try {
            const pricePromises = materials.map(material => {
                const apiUrl = material.sourceTable === 'besoins' ?
                    `api/besoins/get_besoin_info.php?besoin_id=${material.id}` :
                    `${CONFIG.API_URLS.MATERIAL_INFO}?material_id=${material.id}`;
                return fetch(apiUrl)
                    .then(response => response.json())
                    .catch(() => null);
            });
            const results = await Promise.all(pricePromises);
            // Calculer le prix moyen
            const validPrices = results
                .filter(data => data && data.prix_unitaire && parseFloat(data.prix_unitaire) > 0)
                .map(data => parseFloat(data.prix_unitaire));
            if (validPrices.length > 0 && commonPrice) {
                const averagePrice = validPrices.reduce((sum, price) => sum + price, 0) / validPrices.length;
                commonPrice.value = averagePrice.toFixed(2);
            }
            // Créer les lignes du tableau
            tbody.innerHTML = '';
            materials.forEach((material, index) => {
                const prix = results[index]?.prix_unitaire || '';
                const row = this.createPriceRow(material, prix);
                tbody.appendChild(row);
            });
        } catch (error) {
            console.error("Erreur lors du chargement des prix:", error);
        }
    },
    createPriceRow(material, prix) {
        const row = document.createElement('tr');
        row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap text-sm">${Utils.escapeHtml(material.designation)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <input type="number" name="quantities[${material.id}]" 
                        class="shadow border rounded w-full py-1 px-2 text-gray-700" 
                        step="0.01" min="0.01" value="${material.quantity}" required>
                    <input type="hidden" name="original_quantities[${material.id}]" value="${material.quantity}">
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">${Utils.escapeHtml(material.unit || 'N/A')}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="number" name="prices[${material.id}]" 
                        class="shadow border rounded w-full py-1 px-2 text-gray-700" 
                        step="0.01" min="0" value="${prix}" required>
                </td>
            `;
        return row;
    },
    async openBulkComplete() {
        const selectedMaterials = SelectionManager.getSelectedMaterials('partial');
        if (selectedMaterials.length === 0) {
            Swal.fire({
                title: 'Aucun matériau sélectionné',
                text: 'Veuillez sélectionner au moins un matériau à compléter.',
                icon: 'warning'
            });
            return;
        }
        // Utiliser la même modal que pour l'achat groupé mais avec des adaptations
        const container = document.getElementById('selected-materials-container');
        const tbody = document.getElementById('individual-prices-tbody');
        const modal = document.getElementById('bulk-purchase-modal');
        if (!container || !tbody || !modal) return;
        // Adapter le contenu pour la complétion
        container.innerHTML = `<p class="mb-2">Vous avez sélectionné <strong>${selectedMaterials.length}</strong> matériaux à compléter.</p>`;
        tbody.innerHTML = '';
        // Ajouter les champs cachés avec indicateur de commande partielle
        selectedMaterials.forEach(material => {
            container.innerHTML += `
                    <input type="hidden" name="material_ids[]" value="${material.id}">
                    <input type="hidden" name="source_table[${material.id}]" value="${material.sourceTable || 'expression_dym'}">
                    <input type="hidden" name="is_partial[${material.id}]" value="1">
                `;
        });
        // Initialiser l'autocomplétion des fournisseurs
        FournisseursModule.setupAutocomplete('fournisseur-bulk', 'fournisseurs-suggestions-bulk');
        // CORRECTION : Peupler le sélecteur de modes de paiement
        PaymentMethodsManager.populatePaymentSelect('payment-method-bulk');
        // Réinitialiser le champ de mode de paiement
        const paymentSelect = document.getElementById('payment-method-bulk');
        const paymentDescription = document.getElementById('payment-method-description');
        if (paymentSelect) paymentSelect.value = '';
        if (paymentDescription) paymentDescription.innerHTML = '';
        // Modifier le titre et le bouton
        const modalTitle = modal.querySelector('h2');
        if (modalTitle) modalTitle.textContent = 'Compléter les commandes partielles';
        const confirmButton = modal.querySelector('#confirm-bulk-purchase');
        if (confirmButton) confirmButton.textContent = 'Compléter les commandes';
        // Afficher le modal
        modal.style.display = 'flex';
        // Charger les prix et informations
        await this.loadPartialOrderPrices(selectedMaterials);
    },
    async loadPartialOrderPrices(materials) {
        const tbody = document.getElementById('individual-prices-tbody');
        const commonPrice = document.getElementById('common-price');
        const fournisseurInput = document.getElementById('fournisseur-bulk');
        try {
            const pricePromises = materials.map(material => {
                const apiUrl = material.sourceTable === 'besoins' ?
                    `commandes-traitement/besoins/get_besoin_with_remaining.php?id=${material.id}` :
                    `commandes-traitement/api.php?action=get_material_info&id=${material.id}`;
                return fetch(apiUrl)
                    .then(response => response.json())
                    .catch(() => null);
            });
            const results = await Promise.all(pricePromises);
            // Calculer le prix moyen et récupérer le dernier fournisseur
            const validPrices = results
                .filter(data => data && data.prix_unitaire && parseFloat(data.prix_unitaire) > 0)
                .map(data => parseFloat(data.prix_unitaire));
            if (validPrices.length > 0 && commonPrice) {
                const averagePrice = validPrices.reduce((sum, price) => sum + price, 0) / validPrices.length;
                commonPrice.value = averagePrice.toFixed(2);
            }
            // Suggérer le dernier fournisseur utilisé
            const fournisseurSuggested = results.find(data => data?.fournisseur)?.fournisseur;
            if (fournisseurSuggested && fournisseurInput && !fournisseurInput.value) {
                fournisseurInput.value = fournisseurSuggested;
            }
            // Créer les lignes du tableau
            tbody.innerHTML = '';
            materials.forEach((material, index) => {
                const prix = results[index]?.prix_unitaire || '';
                const row = this.createPartialPriceRow(material, prix);
                tbody.appendChild(row);
            });
        } catch (error) {
            console.error("Erreur lors du chargement des prix pour commandes partielles:", error);
        }
    },
    createPartialPriceRow(material, prix) {
        const row = document.createElement('tr');
        row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap text-sm">${Utils.escapeHtml(material.designation)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <input type="number" name="quantities[${material.id}]" 
                        class="shadow border rounded w-full py-1 px-2 text-gray-700" 
                        step="0.01" min="0.01" max="${material.quantity}" value="${material.quantity}" required>
                    <input type="hidden" name="original_quantities[${material.id}]" value="${material.quantity}">
                    <input type="hidden" name="is_partial[${material.id}]" value="1">
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">${Utils.escapeHtml(material.unit || 'N/A')}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="number" name="prices[${material.id}]" 
                        class="shadow border rounded w-full py-1 px-2 text-gray-700" 
                        step="0.01" min="0" value="${prix}" required>
                </td>
            `;
        return row;
    },
    openSubstitution(materialId, designation, expressionId, sourceTable = 'expression_dym') {
        const modal = document.getElementById('substitution-modal');
        const originalProductInput = document.getElementById('original-product');
        const materialIdInput = document.getElementById('substitute-material-id');
        const expressionIdInput = document.getElementById('substitute-expression-id');
        const sourceTableInput = document.getElementById('substitute-source-table');
        if (!modal || !originalProductInput || !materialIdInput || !expressionIdInput || !sourceTableInput) return;
        // Remplir les champs
        originalProductInput.value = designation;
        materialIdInput.value = materialId;
        expressionIdInput.value = expressionId;
        sourceTableInput.value = sourceTable;
        // Afficher le modal
        modal.style.display = 'flex';
        // Configurer l'autocomplétion
        SubstitutionManager.setupProductAutocomplete();
    },
    close(modal) {
        if (modal) modal.style.display = 'none';
    }
};
/**
 * Gestionnaire des achats - VERSION CORRIGÉE
 */
const PurchaseManager = {
    async handleIndividualPurchase(e) {
        e.preventDefault();
        const form = e.target;
        const fournisseur = document.getElementById('fournisseur').value;
        const prix = document.getElementById('prix').value;
        const paymentMethod = document.getElementById('payment-method'); // NOUVEAU
        // CORRECTION : Validation incluant le mode de paiement
        if (!this.validateIndividualPurchase(fournisseur, prix, paymentMethod)) return;
        try {
            // Vérifier et créer le fournisseur si nécessaire
            const fournisseurResult = await FournisseursModule.checkAndCreate(fournisseur);
            // Afficher un indicateur de chargement
            Swal.fire({
                title: 'Traitement en cours...',
                text: 'Enregistrement de la commande',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            // Préparer et envoyer les données
            const formData = new FormData(form);
            if (fournisseurResult.newFournisseur) {
                formData.append('create_fournisseur', '1');
            }
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                ModalManager.close(document.getElementById('purchase-modal'));
                Swal.fire({
                    title: 'Succès!',
                    text: data.message || 'Commande enregistrée avec succès!',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Erreur',
                    text: data.message || 'Une erreur est survenue lors du traitement de la commande.',
                    icon: 'error'
                });
            }
        } catch (error) {
            console.error('Erreur:', error);
            Swal.fire({
                title: 'Erreur',
                text: error.message || 'Une erreur est survenue lors de la communication avec le serveur.',
                icon: 'error'
            });
        }
    },
    async handleBulkPurchase(e) {
        e.preventDefault();
        const form = e.target;
        const priceType = document.getElementById('price-type').value;
        const fournisseur = document.getElementById('fournisseur-bulk').value;
        // CORRECTION : Validation incluant le mode de paiement
        if (!this.validateBulkPurchase(priceType, fournisseur)) return;
        // Déterminer l'URL de soumission
        const isPartialCompletion = form.querySelector('input[name^="is_partial["]') !== null;
        const submitUrl = isPartialCompletion ?
            'commandes-traitement/api.php?action=complete_multiple_partial' :
            'process_bulk_purchase.php';
        try {
            // Vérifier et créer le fournisseur si nécessaire
            const fournisseurResult = await FournisseursModule.checkAndCreate(fournisseur);
            Swal.fire({
                title: 'Traitement en cours...',
                text: isPartialCompletion ? 'Complétion des commandes en cours' : 'Enregistrement de la commande',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            // Préparer les données
            const formData = new FormData(form);
            if (!formData.has('bulk_purchase')) {
                formData.append('bulk_purchase', '1');
            }
            if (fournisseurResult.newFournisseur) {
                formData.append('create_fournisseur', '1');
            }
            const response = await fetch(submitUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            if (data.success) {
                ModalManager.close(document.getElementById('bulk-purchase-modal'));
                // Gérer le téléchargement du PDF si disponible
                if (data.pdf_url) {
                    window.open(data.pdf_url, '_blank');
                    Swal.fire({
                        title: 'Succès!',
                        text: 'Commande enregistrée avec succès et le bon de commande est en cours de téléchargement.',
                        icon: 'success',
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Succès!',
                        text: data.message || (isPartialCompletion ?
                            'Commandes complétées avec succès!' :
                            'Matériaux commandés avec succès!'),
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                }
            } else {
                Swal.fire({
                    title: 'Erreur',
                    text: data.message || 'Une erreur est survenue lors du traitement de la commande.',
                    icon: 'error'
                });
            }
        } catch (error) {
            console.error('Erreur:', error);
            Swal.fire({
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la communication avec le serveur.',
                icon: 'error'
            });
        }
    },
    validateIndividualPurchase(fournisseur, prix, paymentMethodField) {
        if (!fournisseur.trim()) {
            Swal.fire({
                title: 'Fournisseur manquant',
                text: 'Veuillez sélectionner un fournisseur.',
                icon: 'error'
            });
            return false;
        }
        if (!prix || parseFloat(prix) <= 0) {
            Swal.fire({
                title: 'Prix invalide',
                text: 'Veuillez saisir un prix unitaire valide.',
                icon: 'error'
            });
            return false;
        }
        // CORRECTION : Validation du mode de paiement
        if (!PaymentMethodsManager.validatePaymentMethod(paymentMethodField).valid) {
            return false;
        }
        return true;
    },
    validateBulkPurchase(priceType, fournisseur) {
        if (!fournisseur.trim()) {
            Swal.fire({
                title: 'Fournisseur manquant',
                text: 'Veuillez sélectionner un fournisseur.',
                icon: 'error'
            });
            return false;
        }
        // CORRECTION PRINCIPALE : Validation du mode de paiement
        const paymentMethodSelect = document.getElementById('payment-method-bulk');
        if (!paymentMethodSelect) {
            console.error('❌ Sélecteur de mode de paiement non trouvé');
            return false;
        }
        if (!PaymentMethodsManager.validatePaymentMethod(paymentMethodSelect).valid) {
            return false;
        }
        // Validation du pro-forma (si applicable)
        if (window.ProformaUploadManager) {
            const proformaValidation = ProformaUploadManager.validateForSubmission();
            if (!proformaValidation.isValid) {
                Swal.fire({
                    title: 'Erreur Pro-forma',
                    text: proformaValidation.message,
                    icon: 'error',
                    confirmButtonColor: '#4F46E5'
                });
                return false;
            }
        }
        // Validation des quantités
        const quantityInputs = document.querySelectorAll('input[name^="quantities["]');
        for (const input of quantityInputs) {
            if (!input.value || parseFloat(input.value) <= 0) {
                Swal.fire({
                    title: 'Quantités invalides',
                    text: 'Veuillez saisir une quantité valide supérieure à 0 pour chaque matériau.',
                    icon: 'warning',
                    confirmButtonColor: '#4F46E5'
                });
                return false;
            }
        }
        // Validation des prix
        if (priceType === 'common') {
            const commonPriceInput = document.getElementById('common-price');
            if (!commonPriceInput || !commonPriceInput.value || parseFloat(commonPriceInput.value) <= 0) {
                Swal.fire({
                    title: 'Prix invalide',
                    text: 'Veuillez saisir un prix unitaire commun valide.',
                    icon: 'error',
                    confirmButtonColor: '#4F46E5'
                });
                return false;
            }
        } else {
            const priceInputs = document.querySelectorAll('input[name^="prices["]');
            for (const input of priceInputs) {
                if (!input.value || parseFloat(input.value) <= 0) {
                    Swal.fire({
                        title: 'Prix manquants',
                        text: 'Veuillez saisir un prix valide pour chaque matériau.',
                        icon: 'error',
                        confirmButtonColor: '#4F46E5'
                    });
                    return false;
                }
            }
        }
        return true;
    },
    togglePriceInputs() {
        const priceType = document.getElementById('price-type');
        const commonPriceContainer = document.getElementById('common-price-container');
        const individualPricesContainer = document.getElementById('individual-prices-container');
        if (priceType && commonPriceContainer && individualPricesContainer) {
            const isCommon = priceType.value === 'common';
            commonPriceContainer.classList.toggle('hidden', !isCommon);
            individualPricesContainer.classList.toggle('hidden', isCommon);
        }
    }
};
/**
 * Gestionnaire des annulations - VERSION CORRIGÉE
 */
const CancelManager = {
    cancelSingleOrder(id, expressionId, designation, sourceTable = 'expression_dym') {
        this.openCancelConfirmationModal([{
            id: id,
            expressionId: expressionId,
            designation: designation,
            project: '',
            sourceTable: sourceTable
        }]);
    },
    cancelMultipleOrders() {
        const selectedMaterials = SelectionManager.getSelectedMaterials('ordered');
        if (selectedMaterials.length === 0) {
            Swal.fire({
                title: 'Aucune commande sélectionnée',
                text: 'Veuillez sélectionner au moins une commande à annuler.',
                icon: 'warning'
            });
            return;
        }
        this.openCancelConfirmationModal(selectedMaterials);
    },
    cancelPendingMaterial(id, expressionId, designation, sourceTable = 'expression_dym') {
        this.openCancelPendingMaterialModal([{
            id: id,
            expressionId: expressionId,
            designation: designation,
            project: '',
            sourceTable: sourceTable
        }]);
    },
    cancelMultiplePending() {
        const selectedMaterials = SelectionManager.getSelectedMaterialsFromDOM('pending');
        if (selectedMaterials.length === 0) {
            Swal.fire({
                title: 'Aucun matériau sélectionné',
                text: 'Veuillez sélectionner au moins un matériau à annuler.',
                icon: 'warning'
            });
            return;
        }
        SelectionManager.syncSelections('pending');
        this.openCancelPendingMaterialModal(selectedMaterials);
    },
    openCancelConfirmationModal(materials) {
        const materialsList = this.createMaterialsList(materials);
        Swal.fire({
            title: materials.length === 1 ? 'Annuler la commande?' : `Annuler ${materials.length} commandes?`,
            html: `Êtes-vous sûr de vouloir annuler ${materials.length === 1 ? 'cette commande' : 'ces commandes'}?<br>${materialsList}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non, garder',
            confirmButtonColor: '#d33',
            reverseButtons: true,
            input: 'text',
            inputLabel: 'Raison de l\'annulation',
            inputPlaceholder: 'Veuillez indiquer la raison de l\'annulation',
            inputValidator: (value) => {
                if (!value || value.trim() === '') {
                    return 'Vous devez indiquer une raison d\'annulation';
                }
            },
            showLoaderOnConfirm: true,
            preConfirm: (reasonText) => this.performCancellation(reasonText, materials, CONFIG.API_URLS.CANCEL_ORDERS),
            allowOutsideClick: () => !Swal.isLoading()
        }).then(result => {
            if (result.isConfirmed) {
                this.showSuccessMessage(materials.length);
            }
        });
    },
    openCancelPendingMaterialModal(materials) {
        const materialsList = this.createMaterialsList(materials);
        Swal.fire({
            title: materials.length === 1 ? 'Annuler ce matériau?' : `Annuler ${materials.length} matériaux?`,
            html: `Êtes-vous sûr de vouloir annuler ${materials.length === 1 ? 'ce matériau' : 'ces matériaux'} en attente?<br>${materialsList}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non, garder',
            confirmButtonColor: '#d33',
            reverseButtons: true,
            input: 'text',
            inputLabel: 'Raison de l\'annulation',
            inputPlaceholder: 'Veuillez indiquer la raison de l\'annulation',
            inputValidator: (value) => {
                if (!value || value.trim() === '') {
                    return 'Vous devez indiquer une raison d\'annulation';
                }
            },
            showLoaderOnConfirm: true,
            preConfirm: (reasonText) => this.performPendingCancellation(reasonText, materials),
            allowOutsideClick: () => !Swal.isLoading()
        }).then(result => {
            if (result.isConfirmed) {
                this.showSuccessMessage(materials.length, 'Matériau(x) annulé(s)!',
                    materials.length === 1 ? 'Le matériau a été annulé avec succès' :
                        'Les matériaux ont été annulés avec succès');
            }
        });
    },
    createMaterialsList(materials) {
        if (materials.length === 1) {
            const sourceLabel = materials[0].sourceTable === 'besoins' ? 'Système' : 'Projet';
            return `<p><strong>${materials[0].designation}</strong></p>
                        <p class="text-sm text-gray-600">(Source: ${sourceLabel})</p>`;
        } else {
            let list = '<ul class="text-left mt-2 mb-4 max-h-40 overflow-y-auto">';
            materials.forEach(material => {
                const sourceLabel = material.sourceTable === 'besoins' ? 'Système' : 'Projet';
                list += `<li class="py-1 border-b border-gray-200 flex justify-between">
                                <span class="font-medium">${material.designation}</span>
                                <span class="text-sm text-gray-600">${material.project || ''} (${sourceLabel})</span>
                            </li>`;
            });
            list += '</ul>';
            return list;
        }
    },
    async performPendingCancellation(reasonText, materials) {
        const formData = new FormData();
        formData.append('reason', reasonText);
        formData.append('materials', JSON.stringify(materials));
        try {
            const response = await fetch('api/orders/cancel_pending_materials.php', {
                method: 'POST',
                body: formData
            });
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Erreur lors de l\'annulation');
            }
            return data;
        } catch (error) {
            console.error("Erreur lors de l'annulation:", error);
            Swal.showValidationMessage(`Erreur: ${error.message}`);
        }
    },
    async performCancellation(reasonText, materials, apiUrl, type = null) {
        const formData = new FormData();
        formData.append('reason', reasonText);
        formData.append('materials', JSON.stringify(materials));
        if (type) formData.append('type', type);
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Erreur lors de l\'annulation');
            }
            return data;
        } catch (error) {
            console.error("Erreur lors de l'annulation:", error);
            Swal.showValidationMessage(`Erreur: ${error.message}`);
        }
    },
    showSuccessMessage(count, title = 'Commande(s) annulée(s)!', message = null) {
        const defaultMessage = count === 1 ?
            'La commande a été annulée avec succès' :
            'Les commandes ont été annulées avec succès';
        Swal.fire({
            title: title,
            text: message || defaultMessage,
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        }).then(() => {
            window.location.reload();
        });
    }
};
/**
 * GESTIONNAIRE DES COMMANDES PARTIELLES - VERSION 2.0 COMPLÈTE
 * 
 * MISE À JOUR PRINCIPALE :
 * - Intégration complète du système de modes de paiement avec icon_path
 * - Validation robuste et gestion d'erreurs améliorée
 * - Interface utilisateur optimisée
 * - Performance et cache améliorés
 * 
 * À remplacer dans : /DYM MANUFACTURE/expressions_besoins/User-Achat/achats_materiaux.php
 * Section : PartialOrdersManager (Remplacer complètement)
 * 
 * Date : 30/06/2025
 */
const PartialOrdersManager = {
    // Cache et configuration
    cache: {
        lastUpdate: null,
        cacheDuration: 3 * 60 * 1000 // 3 minutes
    },
    /**
     * Initialisation du gestionnaire
     */
    init() {
        this.load(false);
    },
    /**
     * Chargement des données avec gestion du cache
     */
    async load(switchTab = true) {
        try {
            this.showLoading();
            const response = await fetch(`${CONFIG.API_URLS.PARTIAL_ORDERS}?action=get_remaining&_t=${Date.now()}`);
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status} - ${response.statusText}`);
            }
            const data = await response.json();
            if (data.success) {
                // Mettre à jour le cache
                this.cache.lastUpdate = Date.now();
                // Mettre à jour les statistiques
                NotificationsManager.updatePartialOrdersStats(data.stats);
                // Mettre à jour le compteur dans l'onglet
                this.updateTabCounter(data.materials ? data.materials.length : 0);
                // Afficher l'onglet si demandé
                if (switchTab || this.isTabActive()) {
                    if (switchTab) this.showTab();
                    this.renderTable(data.materials || []);
                }
                console.log(`✅ ${data.materials?.length || 0} commandes partielles chargées`);
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            console.error("❌ Erreur lors du chargement des commandes partielles:", error);
            this.showError(error.message);
        }
    },
    /**
     * Affichage du loader
     */
    showLoading() {
        const tbody = document.getElementById('partial-orders-body');
        if (tbody) {
            tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                    <div class="flex items-center justify-center">
                        <svg class="animate-spin h-5 w-5 mr-3" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Chargement des données...
                    </div>
                </td>
            </tr>
        `;
        }
    },
    /**
     * Affichage des erreurs
     */
    showError(message) {
        const tbody = document.getElementById('partial-orders-body');
        if (tbody) {
            tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-4 text-center text-sm text-red-500">
                    <div class="flex items-center justify-center">
                        <span class="material-icons mr-2">error_outline</span>
                        Erreur: ${message || 'Veuillez réessayer'}
                    </div>
                    <button onclick="PartialOrdersManager.load(false)" 
                        class="mt-2 text-blue-600 hover:text-blue-800 underline">
                        Réessayer
                    </button>
                </td>
            </tr>
        `;
        }
    },
    /**
     * Vérification si l'onglet est actif
     */
    isTabActive() {
        const section = document.getElementById('materials-partial');
        return section && !section.classList.contains('hidden');
    },
    /**
     * Affichage de l'onglet
     */
    showTab() {
        TabsManager.activateMaterialTab(document.getElementById('materials-partial-tab'));
    },
    /**
     * Mise à jour du compteur dans l'onglet
     */
    updateTabCounter(count) {
        const counter = document.querySelector('#materials-partial-tab .rounded-full');
        if (counter) {
            counter.textContent = count;
            counter.classList.toggle('bg-yellow-100', count > 0);
            counter.classList.toggle('text-yellow-800', count > 0);
            counter.classList.toggle('bg-gray-100', count === 0);
            counter.classList.toggle('text-gray-800', count === 0);
        }
    },
    /**
     * Rendu du tableau avec gestion optimisée
     */
    renderTable(materials) {
        const tbody = document.getElementById('partial-orders-body');
        if (!tbody) return;
        // Sauvegarder les sélections actuelles
        const selectedIds = this.getSelectedIds();
        // Détruire le DataTable existant
        if (jQuery.fn.DataTable.isDataTable('#partialOrdersTable')) {
            jQuery('#partialOrdersTable').DataTable().destroy();
        }
        if (!materials || materials.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                    <div class="flex flex-col items-center">
                        <span class="material-icons text-4xl mb-2 text-gray-300">inventory_2</span>
                        <span>Aucune commande partielle trouvée.</span>
                    </div>
                </td>
            </tr>
        `;
            return;
        }
        // Construire le HTML
        const html = materials.map(material => this.createTableRow(material, selectedIds)).join('');
        tbody.innerHTML = html;
        // Réattacher les événements
        this.attachEvents();
        // Initialiser DataTable
        this.initDataTable();
    },
    /**
     * Récupération des IDs sélectionnés
     */
    getSelectedIds() {
        const selectedIds = [];
        document.querySelectorAll('.partial-material-checkbox:checked').forEach(checkbox => {
            if (checkbox.dataset && checkbox.dataset.id) {
                selectedIds.push(checkbox.dataset.id);
            }
        });
        return selectedIds;
    },
    /**
     * Création d'une ligne de tableau
     */
    createTableRow(material, selectedIds) {
        const sourceTable = material.source_table || 'expression_dym';
        // Adapter les variables selon la source
        const designation = sourceTable === 'besoins' ?
            material.designation || material.designation_article || 'Sans désignation' :
            material.designation || 'Sans désignation';
        const unit = sourceTable === 'besoins' ?
            material.unit || material.caracteristique || '' :
            material.unit || '';
        const restante = parseFloat(material.qt_restante || 0);
        const expressionId = sourceTable === 'besoins' ?
            material.idExpression || material.idBesoin || '' :
            material.idExpression || '';
        // Calculer les valeurs
        const initialQty = parseFloat(material.quantite_initiale || material.initial_qt_acheter || 0);
        const orderedQty = parseFloat(material.quantite_commandee || material.quantite_deja_commandee || 0);
        const progress = initialQty > 0 ? Math.round((orderedQty / initialQty) * 100) : 0;
        // Déterminer la couleur de progression
        let progressColor = 'bg-yellow-500';
        if (progress >= 75) progressColor = 'bg-green-500';
        if (progress < 25) progressColor = 'bg-red-500';
        // Vérifier la sélection
        const isChecked = selectedIds.includes(material.id?.toString()) ? 'checked' : '';
        return `
        <tr class="${progress < 50 ? 'bg-yellow-50 pulse-animation' : ''}" data-id="${material.id}">
            <td class="px-6 py-4 whitespace-nowrap">
                <input type="checkbox" class="material-checkbox partial-material-checkbox"
                    data-id="${material.id}"
                    data-expression="${expressionId}"
                    data-designation="${Utils.escapeHtml(designation)}"
                    data-quantity="${restante}"
                    data-unit="${Utils.escapeHtml(unit)}"
                    data-source-table="${sourceTable}"
                    ${isChecked}>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">${material.code_projet || '-'}</td>
            <td class="px-6 py-4 whitespace-nowrap">${material.nom_client || '-'}</td>
            <td class="px-6 py-4 whitespace-nowrap font-medium">${Utils.escapeHtml(designation)}</td>
            <td class="px-6 py-4 whitespace-nowrap">${Utils.formatQuantity(initialQty)} ${Utils.escapeHtml(unit)}</td>
            <td class="px-6 py-4 whitespace-nowrap">${Utils.formatQuantity(orderedQty)} ${Utils.escapeHtml(unit)}</td>
            <td class="px-6 py-4 whitespace-nowrap text-yellow-600 font-medium">${Utils.formatQuantity(restante)} ${Utils.escapeHtml(unit)}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="${progressColor} h-2 rounded-full transition-all duration-300" style="width: ${progress}%"></div>
                    </div>
                    <span class="ml-2 text-xs font-medium">${progress}%</span>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex space-x-2">
                    <button onclick="PartialOrdersManager.completeOrder('${material.id}', '${Utils.escapeString(designation)}', ${restante}, '${Utils.escapeHtml(unit)}', '${sourceTable}')" 
                        class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50 transition-colors"
                        title="Commander le restant">
                        <span class="material-icons text-sm">add_shopping_cart</span>
                    </button>
                    <button onclick="PartialOrdersManager.viewDetails('${material.id}', '${sourceTable}')" 
                        class="text-gray-600 hover:text-gray-900 p-1 rounded hover:bg-gray-50 transition-colors"
                        title="Voir les détails">
                        <span class="material-icons text-sm">visibility</span>
                    </button>
                </div>
            </td>
        </tr>
    `;
    },
    /**
     * Attachement des événements
     */
    attachEvents() {
        const selectAllCheckbox = document.getElementById('select-all-partial-materials');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.removeEventListener('change', this.handleSelectAll);
            selectAllCheckbox.addEventListener('change', (e) => this.handleSelectAll(e, 'partial'));
        }
        document.querySelectorAll('.partial-material-checkbox').forEach(checkbox => {
            checkbox.removeEventListener('change', ButtonStateManager.updateAllButtons);
            checkbox.addEventListener('change', () => {
                SelectionManager.updateSelection('partial', checkbox);
                ButtonStateManager.updateAllButtons();
            });
        });
    },
    /**
     * Gestion de la sélection globale
     */
    handleSelectAll(e, type) {
        const isChecked = e.target.checked;
        // Mettre à jour toutes les checkboxes visibles
        document.querySelectorAll(`.${type}-material-checkbox`).forEach(checkbox => {
            checkbox.checked = isChecked;
            SelectionManager.updateSelection(type, checkbox);
        });
        // Si on décoche "tout sélectionner", on vide complètement la sélection
        if (!isChecked) {
            SelectionManager.clearSelections(type);
        }
        ButtonStateManager.updateAllButtons();
    },
    /**
     * Initialisation du DataTable
     */
    initDataTable() {
        DataTablesManager.tables.partial = jQuery('#partialOrdersTable').DataTable({
            responsive: true,
            language: {
                url: CONFIG.DATATABLES.LANGUAGE_URL
            },
            dom: CONFIG.DATATABLES.DOM,
            buttons: CONFIG.DATATABLES.BUTTONS,
            columnDefs: [{
                orderable: false,
                targets: [0, 8]
            }, {
                responsivePriority: 1,
                targets: [3, 6]
            }],
            order: [
                [4, 'desc']
            ],
            pageLength: 10,
            drawCallback: () => {
                // Réattacher les événements après le redessinage
                document.querySelectorAll('.partial-material-checkbox').forEach(checkbox => {
                    checkbox.removeEventListener('change', ButtonStateManager.updateAllButtons);
                    checkbox.addEventListener('change', () => {
                        SelectionManager.updateSelection('partial', checkbox);
                        ButtonStateManager.updateAllButtons();
                    });
                });
                ButtonStateManager.updateAllButtons();
            }
        });
    },
    /**
     * FONCTION PRINCIPALE : Compléter une commande - MISE À JOUR COMPLÈTE
     */
    async completeOrder(id, designation, remaining, unit, sourceTable = 'expression_dym') {
        try {
            console.log(`🔄 Complétion de la commande ${id} (${sourceTable})`);
            // Déterminer l'URL de l'API pour récupérer les infos
            let apiUrl = `${CONFIG.API_URLS.PARTIAL_ORDERS}?action=get_material_info&id=${id}`;
            if (sourceTable === 'besoins') {
                apiUrl = `commandes-traitement/besoins/get_besoin_with_remaining.php?id=${id}`;
            }
            // Récupération des informations du matériau
            const response = await fetch(apiUrl);
            const materialInfo = await response.json();
            if (materialInfo.success === false) {
                throw new Error(materialInfo.message || 'Impossible de récupérer les informations du matériau');
            }
            this.showCompleteOrderModal(id, designation, remaining, unit, materialInfo, sourceTable);
        } catch (error) {
            console.error("❌ Erreur lors de la récupération des infos du matériau:", error);
            Swal.fire({
                title: 'Erreur de chargement',
                text: 'Impossible de charger les données complètes du matériau. Voulez-vous continuer quand même?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Continuer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.showCompleteOrderModal(id, designation, remaining, unit, {}, sourceTable);
                }
            });
        }
    },
    /**
     * MODAL DE COMPLÉTION - MISE À JOUR COMPLÈTE AVEC NOUVEAUX MODES DE PAIEMENT
     */
    showCompleteOrderModal(id, designation, remaining, unit, materialInfo, sourceTable = 'expression_dym') {
        Swal.fire({
            title: 'Compléter la commande',
            html: `
            <div class="text-left space-y-4">
                <!-- Informations du matériau -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-2">Informations du matériau</h4>
                    <p class="text-sm"><strong>Désignation :</strong> ${designation}</p>
                    <p class="text-sm"><strong>Quantité restante :</strong> 
                        <span class="text-orange-600 font-medium">${remaining} ${unit}</span>
                    </p>
                </div>
                
                <!-- Quantité à commander -->
                <div>
                    <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">
                        Quantité à commander <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="quantity" 
                        class="w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                        value="${remaining}" min="0.01" max="${remaining}" step="0.01"
                        placeholder="Entrez la quantité">
                    <div class="text-xs text-gray-500 mt-1">Maximum: ${remaining} ${unit}</div>
                </div>
                
                <!-- Fournisseur avec autocomplétion -->
                <div class="relative">
                    <label for="supplier" class="block text-sm font-medium text-gray-700 mb-1">
                        Fournisseur <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="supplier" 
                        class="w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        value="${materialInfo.fournisseur || ''}"
                        placeholder="Nom du fournisseur">
                    <div id="supplier-suggestions-partial" 
                        class="absolute w-full bg-white mt-1 shadow-lg rounded-md z-50 max-h-60 overflow-y-auto hidden border">
                    </div>
                    <div class="mt-2">
                        <a href="../fournisseurs/fournisseurs.php" target="_blank" 
                            class="text-xs text-blue-600 hover:text-blue-800 flex items-center">
                            <span class="material-icons text-sm mr-1">add_circle</span>
                            Gérer les fournisseurs
                        </a>
                    </div>
                </div>
                
                <!-- MODE DE PAIEMENT - SECTION MISE À JOUR -->
                <div>
                    <label for="payment-method" class="block text-sm font-medium text-gray-700 mb-1">
                        Mode de paiement <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <select id="payment-method" required
                            class="w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pr-10">
                            <option value="">Sélectionnez un mode de paiement</option>
                            <!-- Les options seront chargées dynamiquement -->
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                            <span class="material-icons text-gray-400">payment</span>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-gray-600" id="payment-method-description-partial">
                        <!-- Description du mode de paiement sélectionné -->
                    </div>
                </div>
                
                <!-- Prix unitaire -->
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-1">
                        Prix unitaire (FCFA) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="price" 
                        class="w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                        min="0.01" step="0.01" value="${materialInfo.prix_unitaire || ''}"
                        placeholder="Prix par unité">
                </div>
                
                <!-- Champ caché pour la table source -->
                <input type="hidden" id="source_table" value="${sourceTable}">
                
                <!-- Résumé de la commande -->
                <div class="bg-blue-50 p-3 rounded-lg" id="order-summary" style="display: none;">
                    <h5 class="font-medium text-blue-800 mb-2">Résumé de la commande</h5>
                    <div class="text-sm text-blue-700" id="summary-content">
                        <!-- Contenu généré dynamiquement -->
                    </div>
                </div>
            </div>
        `,
            showCancelButton: true,
            confirmButtonText: 'Commander',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#6b7280',
            showLoaderOnConfirm: true,
            width: '600px',
            customClass: {
                popup: 'swal2-popup-custom'
            },
            didOpen: () => {
                // Initialiser les fonctionnalités
                this.initPartialSupplierAutocomplete();
                this.initPartialPaymentMethods();
                this.setupOrderSummary();
                // Suggérer un fournisseur si absent
                if (!materialInfo.fournisseur) {
                    this.suggestSupplier(designation);
                }
            },
            preConfirm: () => {
                return this.handleOrderCompletion(id, remaining, sourceTable);
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then(result => {
            if (result.isConfirmed && result.value) {
                // Gestion du succès avec détails
                const paymentInfo = PaymentMethodsManager.getMethodById(
                    document.getElementById('payment-method')?.value
                );
                Swal.fire({
                    title: 'Succès !',
                    html: `
                    <div class="text-center">
                        <div class="text-green-600 mb-4">
                            <span class="material-icons text-4xl">check_circle</span>
                        </div>
                        <p class="mb-2">Commande enregistrée avec succès</p>
                        <div class="text-sm text-gray-600">
                            <p>💳 Mode de paiement: ${paymentInfo?.label || 'Non défini'}</p>
                            <p>📦 Quantité: ${document.getElementById('quantity')?.value || 0} ${unit}</p>
                        </div>
                        ${result.value.pdf_url ? '<p class="mt-2 text-blue-600">📄 Le bon de commande est en cours de téléchargement</p>' : ''}
                    </div>
                `,
                    icon: 'success',
                    timer: 4000,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'swal2-success-popup'
                    }
                }).then(() => {
                    // Gérer le téléchargement du PDF si disponible
                    if (result.value.pdf_url) {
                        window.open(result.value.pdf_url, '_blank');
                    }
                    // Recharger les données
                    this.load(false);
                });
            }
        });
    },
    /**
     * INITIALISATION DES MODES DE PAIEMENT - MISE À JOUR COMPLÈTE
     */
    async initPartialPaymentMethods() {
        try {
            console.log('🔄 Initialisation des modes de paiement pour la modal...');
            // S'assurer que PaymentMethodsManager est initialisé
            if (!PaymentMethodsManager.isLoaded) {
                await PaymentMethodsManager.init();
            }
            // Peupler le sélecteur
            const paymentSelect = document.getElementById('payment-method');
            if (paymentSelect) {
                PaymentMethodsManager.populateSelector(paymentSelect);
                console.log('✅ Sélecteur de modes de paiement peuplé');
            }
            // Configurer les événements de changement
            const paymentDescription = document.getElementById('payment-method-description-partial');
            if (paymentSelect && paymentDescription) {
                paymentSelect.addEventListener('change', function () {
                    PaymentMethodsManager.updatePaymentDescription(
                        'payment-method-description-partial',
                        this.value
                    );
                    PartialOrdersManager.updateOrderSummary();
                });
            }
        } catch (error) {
            console.error('❌ Erreur lors de l\'initialisation des modes de paiement:', error);
            showNotification('Erreur lors du chargement des modes de paiement', 'error');
        }
    },
    /**
     * TRAITEMENT DE LA COMMANDE - MISE À JOUR COMPLÈTE
     */
    async handleOrderCompletion(id, remaining, sourceTable) {
        try {
            // Récupération des valeurs
            const quantity = document.getElementById('quantity').value;
            const supplier = document.getElementById('supplier').value;
            const price = document.getElementById('price').value;
            const paymentMethod = document.getElementById('payment-method').value;
            console.log('🔍 Validation des données de commande...');
            console.log({
                quantity,
                supplier,
                price,
                paymentMethod,
                sourceTable
            });
            // VALIDATION COMPLÈTE avec modes de paiement
            if (!this.validateOrder(quantity, remaining, supplier, price, paymentMethod)) {
                return false;
            }
            // Vérification et création du fournisseur
            const fournisseurResult = await FournisseursModule.checkAndCreate(supplier);
            // Préparation des données
            const formData = new FormData();
            formData.append('action', 'complete_partial_order');
            formData.append('material_id', id);
            formData.append('quantite_commande', quantity);
            formData.append('fournisseur', supplier);
            formData.append('prix_unitaire', price);
            formData.append('payment_method', paymentMethod); // NOUVEAU : obligatoire
            formData.append('source_table', sourceTable);
            if (fournisseurResult.newFournisseur) {
                formData.append('create_fournisseur', '1');
            }
            // Déterminer l'URL de l'API
            const apiUrl = sourceTable === 'besoins' ?
                'commandes-traitement/besoins/complete_besoin_partial.php' :
                CONFIG.API_URLS.PARTIAL_ORDERS;
            console.log(`📡 Envoi vers: ${apiUrl}`);
            // Envoi de la requête
            const response = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status} - ${response.statusText}`);
            }
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Erreur lors de l\'enregistrement de la commande');
            }
            console.log('✅ Commande enregistrée avec succès');
            return data;
        } catch (error) {
            console.error('❌ Erreur lors du traitement de la commande:', error);
            Swal.showValidationMessage(`Erreur: ${error.message}`);
            return false;
        }
    },
    /**
     * VALIDATION COMPLÈTE - MISE À JOUR
     */
    validateOrder(quantity, maxQuantity, supplier, price, paymentMethod) {
        try {
            console.log('🔍 Validation de la commande partielle...');
            // 1. Validation de la quantité
            if (!quantity || parseFloat(quantity) <= 0 || parseFloat(quantity) > parseFloat(maxQuantity)) {
                Swal.showValidationMessage(`Veuillez saisir une quantité valide (entre 0 et ${maxQuantity})`);
                return false;
            }
            // 2. Validation du fournisseur
            if (!supplier.trim() || supplier.trim().length < 2) {
                Swal.showValidationMessage('Veuillez indiquer un fournisseur (minimum 2 caractères)');
                return false;
            }
            // 3. Validation du prix
            if (!price || parseFloat(price) <= 0) {
                Swal.showValidationMessage('Veuillez saisir un prix unitaire valide');
                return false;
            }
            // 4. NOUVELLE VALIDATION : Mode de paiement obligatoire
            if (!paymentMethod) {
                Swal.showValidationMessage('Veuillez sélectionner un mode de paiement');
                return false;
            }
            // 5. Validation avancée du mode de paiement
            if (!PaymentMethodsManager.validatePaymentMethod(paymentMethod).valid) {
                Swal.showValidationMessage('Veuillez sélectionner un mode de paiement');
                return false;
            }
            console.log('✅ Validation réussie');
            return true;
        } catch (error) {
            console.error('❌ Erreur lors de la validation:', error);
            Swal.showValidationMessage('Erreur lors de la validation des données');
            return false;
        }
    },
    /**
     * CONFIGURATION DU RÉSUMÉ DE COMMANDE - NOUVEAU
     */
    setupOrderSummary() {
        const fields = ['quantity', 'supplier', 'price', 'payment-method'];
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                const eventType = field.tagName === 'SELECT' ? 'change' : 'input';
                field.addEventListener(eventType, () => this.updateOrderSummary());
            }
        });
    },
    /**
     * MISE À JOUR DU RÉSUMÉ - NOUVEAU
     */
    updateOrderSummary() {
        try {
            const quantity = document.getElementById('quantity')?.value;
            const price = document.getElementById('price')?.value;
            const paymentMethod = document.getElementById('payment-method')?.value;
            const supplier = document.getElementById('supplier')?.value;
            const summaryDiv = document.getElementById('order-summary');
            const summaryContent = document.getElementById('summary-content');
            if (!summaryDiv || !summaryContent) return;
            if (quantity && price && parseFloat(quantity) > 0 && parseFloat(price) > 0) {
                const total = (parseFloat(quantity) * parseFloat(price)).toFixed(2);
                const paymentInfo = PaymentMethodsManager.getMethodById(paymentMethod);
                summaryContent.innerHTML = `
                <div class="grid grid-cols-2 gap-2">
                    <div>Quantité:</div><div class="font-medium">${quantity}</div>
                    <div>Prix unitaire:</div><div class="font-medium">${Utils.formatQuantity(price)} FCFA</div>
                    <div>Total estimé:</div><div class="font-bold text-blue-800">${Utils.formatQuantity(total)} FCFA</div>
                    ${paymentInfo ? `<div>Mode de paiement:</div><div class="font-medium">${paymentInfo.label}</div>` : ''}
                    ${supplier ? `<div>Fournisseur:</div><div class="font-medium">${supplier}</div>` : ''}
                </div>
            `;
                summaryDiv.style.display = 'block';
            } else {
                summaryDiv.style.display = 'none';
            }
        } catch (error) {
            console.error('❌ Erreur lors de la mise à jour du résumé:', error);
        }
    },
    /**
     * AUTOCOMPLÉTION DES FOURNISSEURS - AMÉLIORÉE
     */
    initPartialSupplierAutocomplete() {
        const input = document.getElementById('supplier');
        const suggestions = document.getElementById('supplier-suggestions-partial');
        if (!input || !suggestions) return;
        input.addEventListener('input', function () {
            const value = this.value.toLowerCase().trim();
            suggestions.innerHTML = '';
            if (value.length < 2) {
                suggestions.classList.add('hidden');
                return;
            }
            const matches = AppState.suppliersList
                .filter(f => f.toLowerCase().includes(value))
                .slice(0, 8);
            if (matches.length > 0) {
                suggestions.classList.remove('hidden');
                matches.forEach(supplier => {
                    const div = document.createElement('div');
                    div.className = 'p-3 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 transition-colors';
                    // Mettre en évidence la partie correspondante
                    const index = supplier.toLowerCase().indexOf(value);
                    if (index !== -1) {
                        const before = supplier.substring(0, index);
                        const match = supplier.substring(index, index + value.length);
                        const after = supplier.substring(index + value.length);
                        div.innerHTML = `${Utils.escapeHtml(before)}<strong class="bg-yellow-200">${Utils.escapeHtml(match)}</strong>${Utils.escapeHtml(after)}`;
                    } else {
                        div.textContent = supplier;
                    }
                    div.onclick = () => {
                        input.value = supplier;
                        suggestions.innerHTML = '';
                        suggestions.classList.add('hidden');
                        PartialOrdersManager.updateOrderSummary();
                    };
                    suggestions.appendChild(div);
                });
                // Option pour créer un nouveau fournisseur
                const createDiv = document.createElement('div');
                createDiv.className = 'p-3 hover:bg-blue-50 cursor-pointer text-blue-600 font-medium border-t bg-gray-50';
                createDiv.innerHTML = `
                <div class="flex items-center">
                    <span class="material-icons text-sm mr-2">add_circle</span>
                    Créer le fournisseur "${Utils.escapeHtml(value)}"
                </div>
            `;
                createDiv.onclick = () => {
                    input.value = value;
                    suggestions.innerHTML = '';
                    suggestions.classList.add('hidden');
                    PartialOrdersManager.updateOrderSummary();
                };
                suggestions.appendChild(createDiv);
            } else {
                suggestions.classList.add('hidden');
            }
        });
        // Masquer les suggestions lors d'un clic en dehors
        document.addEventListener('click', (e) => {
            if (e.target !== input && !suggestions.contains(e.target)) {
                suggestions.classList.add('hidden');
            }
        });
        // Événement pour mettre à jour le résumé
        input.addEventListener('blur', () => {
            setTimeout(() => this.updateOrderSummary(), 100);
        });
    },
    /**
     * SUGGESTION AUTOMATIQUE DE FOURNISSEUR
     */
    async suggestSupplier(designation) {
        try {
            const response = await fetch(`get_suggested_fournisseur.php?designation=${encodeURIComponent(designation)}`);
            const data = await response.json();
            const supplierInput = document.getElementById('supplier');
            if (supplierInput && data && data.fournisseur) {
                supplierInput.value = data.fournisseur;
                this.updateOrderSummary();
            }
        } catch (error) {
            console.error('❌ Erreur lors de la suggestion de fournisseur:', error);
        }
    },
    /**
     * VISUALISATION DES DÉTAILS - AMÉLIORÉE
     */
    async viewDetails(id, sourceTable = 'expression_dym') {
        // Afficher un loader
        Swal.fire({
            title: 'Chargement...',
            text: 'Récupération des détails de la commande',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        // Choisir l'URL de l'API
        const apiUrl = sourceTable === 'besoins' ?
            `commandes-traitement/besoins/get_besoin_partial_details.php?id=${id}` :
            `${CONFIG.API_URLS.PARTIAL_ORDERS}?action=get_partial_details&id=${id}`;
        try {
            const response = await fetch(apiUrl);
            const data = await response.json();
            if (data.success) {
                this.showDetailsModal(data, sourceTable);
            } else {
                Swal.fire({
                    title: 'Erreur',
                    text: data.message || 'Impossible de récupérer les détails de la commande',
                    icon: 'error'
                });
            }
        } catch (error) {
            console.error('❌ Erreur lors de la récupération des détails:', error);
            Swal.fire({
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la récupération des détails',
                icon: 'error'
            });
        }
    },
    /**
     * MODAL DE DÉTAILS AVEC MODES DE PAIEMENT - MISE À JOUR
     */
    showDetailsModal(data, sourceTable) {
        const material = data.material;
        const linkedOrders = data.linked_orders || [];
        // Adapter les propriétés selon la source
        let designation, unit, restante, initialQty;
        if (sourceTable === 'besoins') {
            designation = material.designation_article || 'Sans désignation';
            unit = material.caracteristique || '';
            restante = parseFloat(material.qt_restante || 0);
            initialQty = parseFloat(material.qt_demande || 0);
        } else {
            designation = material.designation || 'Sans désignation';
            unit = material.unit || '';
            restante = parseFloat(material.qt_restante || 0);
            initialQty = parseFloat(material.initial_qt_acheter ||
                parseFloat(material.qt_acheter) + parseFloat(material.qt_restante) || 0);
        }
        const orderedQty = initialQty - restante;
        const progress = initialQty > 0 ? Math.round((orderedQty / initialQty) * 100) : 0;
        /**
         * FONCTION MISE À JOUR : Récupération des infos de mode de paiement
         */
        const getPaymentMethodInfo = (paymentId) => {
            if (!paymentId) {
                return {
                    label: 'Non spécifié',
                    icon: 'help_outline',
                    class: 'text-gray-500',
                    iconPath: null
                };
            }
            // Essayer d'abord avec PaymentMethodsManager
            const paymentInfo = PaymentMethodsManager.getMethodById(paymentId);
            if (paymentInfo) {
                return {
                    label: paymentInfo.label,
                    icon: 'payment',
                    class: 'text-blue-600',
                    iconPath: paymentInfo.icon_path // NOUVEAU : utilisation d'icon_path
                };
            }
            // Fallback avec les modes de paiement par défaut
            const defaultMethods = {
                1: {
                    label: 'Espèces',
                    icon: 'payments',
                    class: 'text-green-600'
                },
                2: {
                    label: 'Chèque',
                    icon: 'receipt_long',
                    class: 'text-purple-600'
                },
                3: {
                    label: 'Virement bancaire',
                    icon: 'account_balance',
                    class: 'text-blue-600'
                },
                4: {
                    label: 'Carte de crédit',
                    icon: 'credit_card',
                    class: 'text-red-600'
                },
                6: {
                    label: 'Crédit fournisseur',
                    icon: 'factory',
                    class: 'text-orange-600'
                },
                7: {
                    label: 'Mobile Money',
                    icon: 'phone_android',
                    class: 'text-orange-600'
                },
                8: {
                    label: 'Traite',
                    icon: 'description',
                    class: 'text-indigo-600'
                },
                9: {
                    label: 'Autre',
                    icon: 'more_horiz',
                    class: 'text-gray-600'
                }
            };
            const id = parseInt(paymentId);
            return defaultMethods[id] || {
                label: `Mode ${id}`,
                icon: 'payment',
                class: 'text-gray-600',
                iconPath: null
            };
        };
        // Préparer le tableau des commandes liées avec les modes de paiement MISE À JOUR
        const ordersHtml = linkedOrders.length > 0 ?
            linkedOrders.map(order => {
                const paymentInfo = getPaymentMethodInfo(order.mode_paiement_id);
                // Construction de l'icône avec support icon_path
                let iconHtml = '';
                if (paymentInfo.iconPath) {
                    iconHtml = `<img src="${paymentInfo.iconPath}" alt="${paymentInfo.label}" class="w-4 h-4 object-contain mr-1">`;
                } else {
                    iconHtml = `<span class="material-icons text-sm mr-1">${paymentInfo.icon}</span>`;
                }
                return `
                <tr class="hover:bg-gray-50">
                    <td class="border px-4 py-2 text-sm">${new Date(order.date_achat).toLocaleDateString('fr-FR')}</td>
                    <td class="border px-4 py-2 text-sm font-medium">${Utils.formatQuantity(order.quantity)} ${unit}</td>
                    <td class="border px-4 py-2 text-sm">${Utils.formatQuantity(order.prix_unitaire)} FCFA</td>
                    <td class="border px-4 py-2 text-sm">${order.fournisseur || '-'}</td>
                    <td class="border px-4 py-2">
                        <div class="flex items-center ${paymentInfo.class}">
                            ${iconHtml}
                            <span class="text-xs font-medium">${paymentInfo.label}</span>
                        </div>
                    </td>
                    <td class="border px-4 py-2">
                        <span class="px-2 py-1 rounded-full text-xs font-medium ${order.status === 'reçu' ? 'bg-green-100 text-green-800' :
                        order.status === 'en_attente' ? 'bg-yellow-100 text-yellow-800' :
                            'bg-blue-100 text-blue-800'
                    }">
                            ${order.status}
                        </span>
                    </td>
                </tr>
            `;
            }).join('') :
            `
            <tr>
                <td colspan="6" class="border px-4 py-8 text-center text-gray-500">
                    <div class="flex flex-col items-center">
                        <span class="material-icons text-3xl mb-2 text-gray-300">inbox</span>
                        <span>Aucune commande liée trouvée.</span>
                    </div>
                </td>
            </tr>
        `;
        // Couleur de progression
        let progressColor = 'bg-yellow-500';
        if (progress >= 75) progressColor = 'bg-green-500';
        if (progress < 25) progressColor = 'bg-red-500';
        // Afficher la modal avec SweetAlert2
        Swal.fire({
            title: 'Détails de la commande partielle',
            html: `
            <div class="text-left max-h-96 overflow-y-auto">
                <!-- En-tête avec informations principales -->
                <div class="mb-6 p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border">
                    <h3 class="font-bold text-lg mb-3 text-gray-800">${designation}</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Projet:</span>
                            <span class="font-medium">${sourceTable === 'besoins' ? 'PETROCI' : (material.code_projet || 'N/A')}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Quantité initiale:</span>
                            <span class="font-medium">${Utils.formatQuantity(initialQty)} ${unit}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Quantité commandée:</span>
                            <span class="font-medium">${Utils.formatQuantity(orderedQty)} ${unit}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Quantité restante:</span>
                            <span class="font-medium ${restante > 0 ? 'text-orange-600' : 'text-green-600'}">${Utils.formatQuantity(restante)} ${unit}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Barre de progression -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">Progression de la commande:</span>
                        <span class="text-sm font-bold ${progress >= 75 ? 'text-green-600' : progress >= 50 ? 'text-yellow-600' : 'text-red-600'}">${progress}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 shadow-inner">
                        <div class="${progressColor} h-3 rounded-full transition-all duration-500 shadow-sm" style="width: ${progress}%"></div>
                    </div>
                </div>
                
                <!-- Historique des commandes -->
                <div class="mb-4">
                    <h4 class="font-semibold mb-3 text-gray-800 flex items-center">
                        <span class="material-icons text-sm mr-2">history</span>
                        Historique des commandes liées
                    </h4>
                    <div class="overflow-x-auto border rounded-lg">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                                    <th class="border px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">Quantité</th>
                                    <th class="border px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">Prix unitaire</th>
                                    <th class="border px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">Fournisseur</th>
                                    <th class="border px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">Mode de paiement</th>
                                    <th class="border px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${ordersHtml}
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Actions -->
                ${restante > 0 ? `
                    <div class="flex justify-center pt-4 border-t">
                        <button onclick="Swal.close(); PartialOrdersManager.completeOrder('${material.id}', '${Utils.escapeString(designation)}', ${restante}, '${unit}', '${sourceTable}')" 
                            class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-lg font-medium flex items-center transition-colors shadow-md">
                            <span class="material-icons mr-2">add_shopping_cart</span>
                            Commander le restant (${Utils.formatQuantity(restante)} ${unit})
                        </button>
                    </div>
                ` : `
                    <div class="text-center pt-4 border-t">
                        <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 rounded-lg">
                            <span class="material-icons mr-2">check_circle</span>
                            <span class="font-medium">Commande complètement traitée</span>
                        </div>
                    </div>
                `}
            </div>
        `,
            width: '1000px',
            confirmButtonText: 'Fermer',
            confirmButtonColor: '#6b7280',
            customClass: {
                popup: 'swal2-popup-large',
                content: 'swal2-content-large'
            },
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
            }
        });
    }
};
/**
 * Gestionnaire des substitutions
 */
const SubstitutionManager = {
    setupProductAutocomplete() {
        const productInput = document.getElementById('substitute-product');
        const originalProductInput = document.getElementById('original-product');
        const suggestionsDiv = document.getElementById('product-suggestions');
        if (!productInput || !suggestionsDiv) return;
        productInput.addEventListener('input', async function () {
            const searchTerm = this.value.trim();
            const originalProduct = originalProductInput.value.trim();
            if (searchTerm.length < 2) {
                suggestionsDiv.innerHTML = '';
                suggestionsDiv.classList.remove('active');
                return;
            }
            try {
                const response = await fetch(`${CONFIG.API_URLS.PRODUCT_SUGGESTIONS}?term=${encodeURIComponent(searchTerm)}&original=${encodeURIComponent(originalProduct)}`);
                const products = await response.json();
                suggestionsDiv.innerHTML = '';
                if (products.length > 0) {
                    suggestionsDiv.classList.add('active');
                    suggestionsDiv.style.display = 'block';
                    products.forEach(product => {
                        const div = document.createElement('div');
                        div.className = 'product-suggestion';
                        if (product.category_name) {
                            div.innerHTML = `${product.product_name} <span class="text-xs text-gray-500">(${product.category_name})</span>`;
                        } else {
                            div.textContent = product.product_name;
                        }
                        div.onclick = () => {
                            productInput.value = product.product_name;
                            suggestionsDiv.innerHTML = '';
                            suggestionsDiv.classList.remove('active');
                            suggestionsDiv.style.display = 'none';
                        };
                        suggestionsDiv.appendChild(div);
                    });
                } else {
                    suggestionsDiv.classList.remove('active');
                    suggestionsDiv.style.display = 'none';
                }
            } catch (error) {
                console.error('Erreur lors de la récupération des suggestions:', error);
                suggestionsDiv.classList.remove('active');
                suggestionsDiv.style.display = 'none';
            }
        });
        // Masquer les suggestions lors d'un clic en dehors
        document.addEventListener('click', (e) => {
            if (e.target !== productInput && !suggestionsDiv.contains(e.target)) {
                suggestionsDiv.classList.remove('active');
                suggestionsDiv.style.display = 'none';
            }
        });
    },
    validateForm() {
        const originalProduct = document.getElementById('original-product').value;
        const substituteProduct = document.getElementById('substitute-product').value;
        const reason = document.getElementById('substitution-reason').value;
        const otherReason = document.getElementById('other-reason').value;
        // Vérifier que le produit de substitution est différent
        if (substituteProduct.trim() === originalProduct.trim()) {
            Swal.fire({
                title: 'Erreur de validation',
                text: 'Le produit de substitution doit être différent du produit original.',
                icon: 'error'
            });
            return false;
        }
        // Vérifier que le produit n'est pas vide
        if (!substituteProduct.trim()) {
            Swal.fire({
                title: 'Erreur de validation',
                text: 'Veuillez saisir un produit de substitution.',
                icon: 'error'
            });
            return false;
        }
        // Vérifier la raison
        if (!reason) {
            Swal.fire({
                title: 'Erreur de validation',
                text: 'Veuillez sélectionner une raison pour la substitution.',
                icon: 'error'
            });
            return false;
        }
        // Si "Autre raison" est sélectionnée
        if (reason === 'autre' && !otherReason.trim()) {
            Swal.fire({
                title: 'Erreur de validation',
                text: 'Veuillez préciser la raison de la substitution.',
                icon: 'error'
            });
            return false;
        }
        return true;
    },
    async handleSubmit(e) {
        e.preventDefault();
        if (!this.validateForm()) {
            return;
        }
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        // Afficher un indicateur de chargement
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="material-icons spin">autorenew</span> Traitement...';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                // Message de confirmation
                Swal.fire({
                    title: 'Produit substitué avec succès',
                    html: `
                            <div class="text-left">
                                <p><strong>Produit original:</strong> ${data.data.original_product} (${data.data.original_unit})</p>
                                <p><strong>Remplacé par:</strong> ${data.data.new_product} (${data.data.new_unit})</p>
                                <p><strong>Quantité transférée:</strong> ${data.data.quantity_transferred}</p>
                            </div>
                        `,
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Erreur',
                    text: data.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        } catch (error) {
            Swal.fire({
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la substitution',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }
};
/**
 * Gestionnaire des exportations
 */
const ExportManager = {
    exportPartialOrdersToExcel() {
        window.location.href = `${CONFIG.API_URLS.PARTIAL_ORDERS}?action=export_remaining&format=excel`;
    }
};
/**
 * Gestionnaire des notifications
 */
const NotificationsManager = {
    updateMaterialsNotification(data) {
        const notificationBadge = document.querySelector('.notification-badge');
        const tooltipText = document.querySelector('.tooltiptext');
        if (notificationBadge && tooltipText) {
            notificationBadge.textContent = data.total;
            tooltipText.textContent = `Il y a ${data.total} matériaux à commander`;
            if (data.newCount > 0) {
                notificationBadge.classList.add('bg-red-600');
                tooltipText.textContent += ` (${data.newCount} nouveaux)`;
            }
        }
    },
    updatePartialOrdersStats(stats) {
        if (!stats) return;
        const updates = {
            'stat-total-partial': stats.total_materials || 0,
            'stat-remaining-qty': Utils.formatQuantity(stats.total_remaining || 0),
            'stat-projects-count': stats.total_projects || 0,
            'stat-progress': `${stats.global_progress || 0}%`
        };
        Object.entries(updates).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
        // Mise à jour de la barre de progression
        const progressBar = document.getElementById('progress-bar');
        if (progressBar) {
            progressBar.style.width = `${stats.global_progress || 0}%`;
        }
    },
    updateCanceledOrdersStats(stats) {
        if (!stats) return;
        const updates = {
            'total-canceled-count': stats.total_canceled || 0,
            'projects-canceled-count': stats.projects_count || 0
        };
        Object.entries(updates).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
        // Mise à jour de la date
        if (document.getElementById('last-canceled-date') && stats.last_canceled_date) {
            const date = new Date(stats.last_canceled_date);
            document.getElementById('last-canceled-date').textContent = date.toLocaleDateString('fr-FR');
        }
        // Mise à jour du compteur dans l'onglet
        const canceledTabCounter = document.querySelector('#tab-canceled .rounded-full');
        if (canceledTabCounter) {
            canceledTabCounter.textContent = stats.total_canceled || 0;
        }
    }
};
/**
 * Vérificateur de validation des commandes
 */
const OrderValidationChecker = {
    async check() {
        try {
            const response = await fetch(CONFIG.API_URLS.UPDATE_ORDER_STATUS + '?debug=1');
            const data = await response.json();
            if (data.success) {
                if (data.updated_count > 0) {
                    // Afficher les détails des mises à jour
                    let message = `${data.updated_count} commande(s) validée(s) par la finance :\n\n`;
                    if (data.processed_items) {
                        data.processed_items.forEach(item => {
                            message += `• ${item.designation} (BC: ${item.bon_commande})\n`;
                        });
                    }
                    Swal.fire({
                        title: 'Validations Finance',
                        text: message,
                        icon: 'success',
                        confirmButtonText: 'Actualiser la page'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.reload();
                        }
                    });
                }
            } else {
                console.error("Erreur lors de la vérification des validations:", data.message);
                if (data.debug_log) {
                    console.log("Debug log:", data.debug_log);
                }
            }
        } catch (error) {
            console.error('Erreur lors de la vérification des validations finance:', error);
        }
    }
};
/**
 * Utilitaires
 */
const Utils = {
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },
    escapeString(str) {
        return str.replace(/[\\'\"]/g, function (match) {
            return '\\' + match;
        });
    },
    formatQuantity(qty) {
        if (qty === null || qty === undefined) return '0.00';
        return parseFloat(qty).toLocaleString('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },
    formatPrice(price) {
        return parseFloat(price).toLocaleString('fr-FR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }
};
/**
 * Fonctions globales exposées
 */
window.openPurchaseModal = (expressionId, designation, quantity, unit, fournisseur = '') => {
    ModalManager.openPurchase(expressionId, designation, quantity, unit, fournisseur);
};
window.closePurchaseModal = () => {
    ModalManager.close(document.getElementById('purchase-modal'));
};
window.closeBulkPurchaseModal = () => {
    ModalManager.close(document.getElementById('bulk-purchase-modal'));
};
window.openSubstitutionModal = (materialId, designation, expressionId, sourceTable = 'expression_dym') => {
    ModalManager.openSubstitution(materialId, designation, expressionId, sourceTable);
};
window.closeSubstitutionModal = () => {
    ModalManager.close(document.getElementById('substitution-modal'));
};
window.generateBonCommande = (expressionId) => {
    const downloadUrl = `${CONFIG.API_URLS.BON_COMMANDE}?id=${expressionId}`;
    window.open(downloadUrl, '_blank');
    Swal.fire({
        title: 'Bon de commande généré!',
        text: 'Le bon de commande a été téléchargé et sauvegardé dans les archives.',
        icon: 'success',
        timer: 3000,
        showConfirmButton: false
    });
};
window.completePartialOrder = (id, designation, remaining, unit, sourceTable = 'expression_dym') => {
    PartialOrdersManager.completeOrder(id, designation, remaining, unit, sourceTable);
};
window.viewPartialOrderDetails = (id, sourceTable = 'expression_dym') => {
    PartialOrdersManager.viewDetails(id, sourceTable);
};
window.cancelSingleOrder = (id, expressionId, designation, sourceTable = 'expression_dym') => {
    CancelManager.cancelSingleOrder(id, expressionId, designation, sourceTable);
};
window.cancelPendingMaterial = (id, expressionId, designation, sourceTable = 'expression_dym') => {
    CancelManager.cancelPendingMaterial(id, expressionId, designation, sourceTable);
};
window.viewStockDetails = (designation) => {
    window.open(`../stock/inventory.php?search=${encodeURIComponent(designation)}`, '_blank');
};
window.openEditOrderModal = (orderId, expressionId, designation, sourceTable, quantity, unit, price, supplier) => {
    EditOrderManager.openModal(orderId, expressionId, designation, sourceTable, quantity, unit, price, supplier);
};
window.closeEditOrderModal = () => {
    EditOrderManager.closeModal();
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
window.viewOrderDetails = async (orderId, expressionId, designation) => {
    console.log('Affichage des détails - Order ID:', orderId, 'Expression ID:', expressionId,
        'Designation:', designation);
    // Afficher un loader
    Swal.fire({
        title: 'Chargement...',
        text: 'Récupération des détails de la commande',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    try {
        // Construire l'URL avec les paramètres
        const params = new URLSearchParams({
            order_id: orderId || '',
            expression_id: expressionId || '',
            designation: designation || ''
        });
        const response = await fetch(`api/orders/get_order_details.php?${params}`);
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
            Swal.fire({
                title: 'Erreur',
                text: data.message || 'Impossible de récupérer les détails de la commande',
                icon: 'error'
            });
        }
    } catch (error) {
        console.error('Erreur lors de la récupération des détails:', error);
        Swal.close();
        Swal.fire({
            title: 'Erreur',
            text: 'Une erreur est survenue lors de la récupération des détails',
            icon: 'error'
        });
    }
};
/**
 * Affiche la modal avec les détails de la commande
 */
function showOrderDetailsModal(orderData) {
    const modal = document.getElementById('order-details-modal');
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
 * Construit le HTML pour les détails de la commande
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
                ${isSystemRequest ? 'Demande Système' : 'Projet Standard'}
            </span>
            <span class="text-sm text-gray-500">ID: ${order.expression_id || 'N/A'}</span>
        </div>
        
        <!-- Informations générales -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold mb-3">Informations générales</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">${projectLabel}</p>
                    <p class="font-medium">${order.code_projet || 'N/A'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">${clientLabel}</p>
                    <p class="font-medium">${order.nom_client || 'N/A'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Expression ID</p>
                    <p class="font-medium">${order.expression_id || 'N/A'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Date de commande</p>
                    <p class="font-medium">${order.date_achat ? new Date(order.date_achat).toLocaleDateString('fr-FR') : 'N/A'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Date de réception</p>
                    <p class="font-medium">${order.date_reception ? new Date(order.date_reception).toLocaleDateString('fr-FR') : 'N/A'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Fournisseur</p>
                    <p class="font-medium">${order.fournisseur || 'N/A'}</p>
                </div>
                ${isSystemRequest && order.service_demandeur ? `
                <div>
                    <p class="text-sm text-gray-600">Service demandeur</p>
                    <p class="font-medium">${order.service_demandeur}</p>
                </div>
                ` : ''}
                ${isSystemRequest && order.motif_demande ? `
                <div class="md:col-span-2">
                    <p class="text-sm text-gray-600">Motif de la demande</p>
                    <p class="font-medium">${order.motif_demande}</p>
                </div>
                ` : ''}
            </div>
        </div>
        
        <!-- Matériaux commandés -->
        <div>
            <h3 class="text-lg font-semibold mb-3">Matériaux commandés</h3>
            <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unité</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        ${materials.map(material => `
                            <tr>
                                <td class="px-4 py-2 text-sm">${material.designation || 'N/A'}</td>
                                <td class="px-4 py-2 text-sm">${Utils.formatQuantity(material.quantity || 0)}</td>
                                <td class="px-4 py-2 text-sm">${material.unit || (isSystemRequest ? order.unit_besoin : 'N/A') || 'N/A'}</td>
                                <td class="px-4 py-2 text-sm">${Utils.formatPrice(material.prix_unitaire || 0)} FCFA</td>
                                <td class="px-4 py-2 text-sm font-medium">${Utils.formatPrice((material.quantity || 0) * (material.prix_unitaire || 0))} FCFA</td>
                                <td class="px-4 py-2 text-sm">
                                    <span class="px-2 py-1 text-xs rounded-full ${getStatusBadgeClass(material.status)}">
                                        ${getStatusText(material.status)}
                                    </span>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th colspan="4" class="px-4 py-2 text-right text-sm font-medium">Total général:</th>
                            <th class="px-4 py-2 text-sm font-bold">${Utils.formatPrice(total)} FCFA</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- Historique des mouvements -->
        ${history.length > 0 ? `
        <div>
            <h3 class="text-lg font-semibold mb-3">Historique des mouvements</h3>
            <div class="space-y-2">
                ${history.map(item => `
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <div>
                            <p class="text-sm font-medium">${item.action || 'Action'}</p>
                            <p class="text-xs text-gray-600">${item.details || ''}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-600">${item.created_at ? new Date(item.created_at).toLocaleString('fr-FR') : ''}</p>
                            <p class="text-xs text-gray-600">${item.user_name || ''}</p>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
        ` : ''}
        
        <!-- Actions disponibles -->
        <div class="bg-blue-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold mb-3">Actions disponibles</h3>
            <div class="flex gap-3">
                ${!isSystemRequest ? `
                <button onclick="generateBonCommande('${order.expression_id}')" 
                        class="flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    <span class="material-icons mr-2">receipt</span>
                    Générer bon de commande
                </button>
                ` : ''}
                <button onclick="viewStockDetails('${order.designation}')" 
                        class="flex items-center px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                    <span class="material-icons mr-2">inventory_2</span>
                    Voir dans le stock
                </button>
                ${isSystemRequest ? `
                <button onclick="closeOrderDetailsModal()" 
                        class="flex items-center px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                    <span class="material-icons mr-2">info</span>
                    Demande système
                </button>
                ` : ''}
            </div>
        </div>
    </div>
    `;
}
/**
 * Retourne la classe CSS pour le badge de statut
 */
function getStatusBadgeClass(status) {
    switch (status) {
        case 'reçu':
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'commandé':
        case 'ordered':
            return 'bg-blue-100 text-blue-800';
        case 'en_cours':
        case 'partial':
            return 'bg-yellow-100 text-yellow-800';
        case 'annulé':
        case 'canceled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
/**
 * Retourne le texte à afficher pour le statut
 */
function getStatusText(status) {
    switch (status) {
        case 'reçu':
        case 'completed':
            return 'Reçu';
        case 'commandé':
        case 'ordered':
            return 'Commandé';
        case 'en_cours':
        case 'partial':
            return 'En cours';
        case 'annulé':
        case 'canceled':
            return 'Annulé';
        default:
            return status || 'Inconnu';
    }
}
/**
 * Ferme la modal des détails de commande
 */
window.closeOrderDetailsModal = () => {
    const modal = document.getElementById('order-details-modal');
    if (modal) {
        modal.style.display = 'none';
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
window.viewCanceledOrderDetails = async (orderId) => {
    // Validation de l'ID
    if (!orderId) {
        Swal.fire({
            title: 'Erreur',
            text: 'ID de commande non trouvé. Veuillez réessayer.',
            icon: 'error'
        });
        return;
    }
    // Afficher le loader
    Swal.fire({
        title: 'Chargement...',
        text: 'Récupération des détails de la commande annulée',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    try {
        const response = await fetch(`api_canceled/api_getCanceledOrderDetails.php?id=${orderId}`);
        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }
        const data = await response.json();
        if (data.success) {
            // Afficher la modal avec les détails
            showCanceledOrderDetailsModal(data.order, data.related_orders || [], data.saved_value || 0);
        } else {
            Swal.fire({
                title: 'Erreur',
                text: data.message || 'Impossible de récupérer les détails de la commande',
                icon: 'error'
            });
        }
    } catch (error) {
        console.error('Erreur lors de la récupération des détails:', error);
        Swal.fire({
            title: 'Erreur',
            text: 'Une erreur est survenue lors de la récupération des détails',
            icon: 'error'
        });
    }
};
/**
 * Affiche la modal avec les détails de la commande annulée (version compacte)
 */
function showCanceledOrderDetailsModal(order, relatedOrders, savedValue) {
    if (!order) {
        console.error('Données de commande manquantes');
        return;
    }
    // Construire le contenu HTML pour les détails
    const htmlContent = buildCanceledOrderDetailsHTML(order, relatedOrders, savedValue);
    // Afficher la modal avec SweetAlert2 (version compacte)
    Swal.fire({
        title: 'Commande annulée - ' + (order.designation || 'N/A'),
        html: htmlContent,
        width: '600px', // Réduction de la largeur
        confirmButtonText: 'Fermer',
        showClass: {
            popup: 'animate__animated animate__fadeIn'
        },
        customClass: {
            popup: 'text-left',
            htmlContainer: 'swal-compact' // Classe CSS personnalisée
        },
        // Ajout de CSS personnalisé pour une meilleure compacité
        didOpen: () => {
            // Injecter du CSS pour rendre la modal plus compacte
            const style = document.createElement('style');
            style.textContent = `
            .swal-compact {
                font-size: 0.9rem !important;
                line-height: 1.4 !important;
            }
            .swal-compact .grid {
                gap: 0.75rem !important;
            }
            .swal-compact .space-y-4 > * + * {
                margin-top: 1rem !important;
            }
            .swal-compact .p-4 {
                padding: 0.75rem !important;
            }
            .swal-compact .mb-3 {
                margin-bottom: 0.5rem !important;
            }
            .swal-compact table {
                font-size: 0.8rem !important;
            }
            .swal-compact .px-3 {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            .swal-compact .py-2 {
                padding-top: 0.25rem !important;
                padding-bottom: 0.25rem !important;
            }
        `;
            document.head.appendChild(style);
        }
    });
}
/**
 * Construit le HTML pour les détails de la commande annulée (version compacte)
 */
function buildCanceledOrderDetailsHTML(order, relatedOrders, savedValue) {
    // Déterminer la source de la commande
    const isSystemRequest = order.source_table === 'besoins';
    const sourceLabel = isSystemRequest ? 'Système' : 'Projet';
    // Préparer le badge de statut original
    const statusBadgeClass = getOriginalStatusBadgeClass(order.original_status);
    const statusText = getOriginalStatusText(order.original_status);

    // Version compacte des commandes liées
    const relatedOrdersHTML = relatedOrders.length > 0 ? `
        <div class="mt-4">
            <h4 class="text-sm font-semibold mb-2">Autres commandes (${relatedOrders.length})</h4>
            <div class="bg-gray-50 p-2 rounded text-xs max-h-32 overflow-y-auto">
                ${relatedOrders.slice(0, 3).map(relatedOrder => `
                <div class="flex justify-between items-center py-1 border-b border-gray-200 last:border-0">
                    <span>${relatedOrder.date_achat_formatted || 'N/A'}</span>
                    <span>${Utils.formatQuantity(relatedOrder.quantity || 0)} ${relatedOrder.unit || ''}</span>
                    <span class="px-1 py-0.5 rounded text-xs ${getStatusBadgeClass(relatedOrder.status)}">
                        ${getStatusText(relatedOrder.status)}
                    </span>
                </div>
            `).join('')}
            ${relatedOrders.length > 3 ? `<div class="text-center text-gray-500 pt-1">... et ${relatedOrders.length - 3} autres</div>` : ''}
        </div>
    </div>
    ` : '';
    return `
    <div class="space-y-4">
        <!-- En-tête avec badges -->
        <div class="flex justify-between items-center">
            <span class="px-2 py-1 text-xs font-medium rounded ${isSystemRequest ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                ${sourceLabel}
            </span>
            <span class="px-2 py-1 text-xs font-medium rounded bg-red-100 text-red-800">
                Annulée
            </span>
        </div>
        
        <!-- Informations principales en 2 colonnes compactes -->
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <p class="text-xs text-gray-600">Projet/Client</p>
                <p class="font-medium">${order.code_projet || 'N/A'}</p>
                <p class="text-xs text-gray-500">${order.nom_client || 'N/A'}</p>
            </div>
            <div>
                <p class="text-xs text-gray-600">Quantité</p>
                <p class="font-medium">${Utils.formatQuantity(order.quantity || 0)} ${order.unit || ''}</p>
                ${order.prix_unitaire ? `<p class="text-xs text-gray-500">${Utils.formatPrice(order.prix_unitaire)} FCFA/unité</p>` : ''}
            </div>
            <div>
                <p class="text-xs text-gray-600">Statut original</p>
                <span class="px-2 py-1 text-xs rounded ${statusBadgeClass}">${statusText}</span>
            </div>
            <div>
                <p class="text-xs text-gray-600">Fournisseur</p>
                <p class="font-medium">${order.fournisseur || 'Non spécifié'}</p>
            </div>
        </div>
        
        <!-- Détails de l'annulation compacts -->
        <div class="bg-red-50 p-3 rounded border border-red-200">
            <h4 class="text-sm font-semibold mb-2 text-red-800">Annulation</h4>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                    <p class="text-xs text-gray-600">Par</p>
                    <p class="font-medium">${order.canceled_by_name || 'Système'}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600">Le</p>
                    <p class="font-medium">${order.canceled_at_formatted || 'N/A'}</p>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-xs text-gray-600">Raison</p>
                <p class="text-sm bg-white p-2 rounded border">${order.cancel_reason || 'Aucune raison spécifiée'}</p>
            </div>
        </div>
        
        <!-- Économies réalisées (si applicable) -->
        ${savedValue > 0 ? `
        <div class="bg-green-50 p-3 rounded border border-green-200">
            <div class="flex justify-between items-center">
                <span class="text-sm font-semibold text-green-800">Économies</span>
                <span class="text-lg font-bold text-green-700">${Utils.formatPrice(savedValue)} FCFA</span>
            </div>
        </div>
        ` : ''}
        
        <!-- Informations additionnelles si disponibles -->
        ${order.description_projet ? `
        <div class="bg-blue-50 p-3 rounded">
            <h4 class="text-sm font-semibold mb-1">Description</h4>
            <p class="text-sm text-gray-700">${order.description_projet}</p>
            ${order.completed_by_name ? `<p class="text-xs text-gray-600 mt-1">Terminé par ${order.completed_by_name}</p>` : ''}
        </div>
        ` : ''}
        
        ${relatedOrdersHTML}
        
        <!-- Actions compactes -->
            <div class="flex gap-2 pt-2 border-t">
            ${!isSystemRequest ? `
            <button onclick="generateBonCommande('${order.project_id}'); Swal.close();" 
                    class="flex items-center px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs">
                <span class="material-icons mr-1 text-sm">receipt</span>
                Projet
            </button>
            ` : ''}
            <button onclick="viewStockDetails('${order.designation}'); Swal.close();" 
                    class="flex items-center px-3 py-1 bg-purple-600 text-white rounded hover:bg-purple-700 text-xs">
                <span class="material-icons mr-1 text-sm">inventory_2</span>
                Stock
            </button>
        </div>
    </div>
    `;
}
/**
 * Retourne la classe CSS pour le badge de statut original
 */
function getOriginalStatusBadgeClass(status) {
    switch (status) {
        case 'en_attente':
            return 'bg-yellow-100 text-yellow-800';
        case 'commandé':
            return 'bg-blue-100 text-blue-800';
        case 'en_cours':
            return 'bg-orange-100 text-orange-800';
        case 'pas validé':
            return 'bg-gray-100 text-gray-800';
        case 'validé':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
/**
 * Retourne le texte à afficher pour le statut original
 */
function getOriginalStatusText(status) {
    switch (status) {
        case 'en_attente':
            return 'En attente';
        case 'commandé':
            return 'Commandé';
        case 'en_cours':
            return 'En cours';
        case 'pas validé':
            return 'Pas validé';
        case 'validé':
            return 'Validé';
        default:
            return status || 'Inconnu';
    }
}
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
window.viewReturnDetails = async (returnId) => {
    Swal.fire({
        title: 'Chargement...',
        text: 'Récupération des détails du retour',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    try {
        const response = await fetch(`statistics/retour-fournisseur/get_return_details.php?id=${returnId}`);
        const data = await response.json();
        if (data.success) {
            const returnData = data.data;
            // Préparer le badge de statut
            let statusBadge;
            if (returnData.status === 'completed') {
                statusBadge =
                    '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Complété</span>';
            } else if (returnData.status === 'cancelled') {
                statusBadge =
                    '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Annulé</span>';
            } else {
                statusBadge =
                    '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">En attente</span>';
            }
            // Afficher les détails
            Swal.fire({
                title: 'Détails du retour',
                html: `
                <div class="text-left">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold mb-2">Détails du produit</h3>
                        <p><span class="font-medium">Produit:</span> ${returnData.product_name}</p>
                        <p><span class="font-medium">Quantité:</span> ${returnData.quantity}</p>
                        <p><span class="font-medium">Fournisseur:</span> ${returnData.supplier_name}</p>
                    </div>
                    
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold mb-2">Informations de retour</h3>
                        <p><span class="font-medium">Statut:</span> ${statusBadge}</p>
                        <p><span class="font-medium">Motif:</span> ${returnData.reason}</p>
                        <p><span class="font-medium">Date de retour:</span> ${returnData.created_at}</p>
                        ${returnData.completed_at ? `<p><span class="font-medium">Date de complétion:</span> ${returnData.completed_at}</p>` : ''}
                    </div>
                    
                    ${returnData.comment ? `
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold mb-2">Commentaire</h3>
                        <p class="text-gray-700 bg-gray-50 p-3 rounded">${returnData.comment}</p>
                    </div>` : ''}
                </div>
            `,
                width: 600,
                confirmButtonText: 'Fermer',
                showClass: {
                    popup: 'animate__animated animate__fadeIn'
                }
            });
        } else {
            Swal.fire({
                title: 'Erreur',
                text: data.message || 'Impossible de récupérer les détails du retour',
                icon: 'error'
            });
        }
    } catch (error) {
        console.error('Erreur lors de la récupération des détails:', error);
        Swal.fire({
            title: 'Erreur',
            text: 'Erreur lors de la récupération des détails',
            icon: 'error'
        });
    }
};
// Fonction globale pour vérifier manuellement
window.checkOrderValidationStatus = () => {
    OrderValidationChecker.check();
};
window.exportPartialOrdersExcel = () => {
    ExportManager.exportPartialOrdersToExcel();
};
// Configurer l'intervalle de vérification (toutes les 5 minutes)
window.checkOrderStatusInterval = setInterval(() => {
    OrderValidationChecker.check();
}, CONFIG.REFRESH_INTERVALS.CHECK_VALIDATION || 5 * 60 * 1000);
/**
 * Point d'entrée principal
 */
document.addEventListener('DOMContentLoaded', () => {
    AchatsMateriauxApp.init();
    const editOrderForm = document.getElementById('edit-order-form');
    if (editOrderForm) {
        editOrderForm.addEventListener('submit', (e) => {
            EditOrderManager.handleSubmit(e);
        });
    }
});
console.log('✅ Script achats_materiaux.js chargé avec support complet des modes de paiement par ID');

