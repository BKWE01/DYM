/**
 * ====================================================================
 * MODULE DE GESTION DES ACHATS DE MATÉRIAUX - VERSION CORRIGÉE
 * ====================================================================
 * 
 * Fichier: achats-materiaux.js
 * Emplacement: /DYM MANUFACTURE/expressions_besoins/User-Achat/assets/js/
 * 
 * Correction complète du JavaScript pour correspondre au fichier PHP
 * et reprendre la logique de l'ancien script
 * 
 * Auteur: DYM MANUFACTURE
 * Version: 2.1 - Correction des incohérences
 * Date: Juin 2025
 * ====================================================================
 */

'use strict';

// =====================================================
// CONFIGURATION GLOBALE ET CONSTANTES
// =====================================================

const CONFIG = {
    // URLs des API - Correspondant au fichier PHP
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
        PAYMENT_METHODS: 'api/payment/get_payment_methods.php'
    },

    // Intervalles de rafraîchissement
    REFRESH_INTERVALS: {
        DATETIME: 1000,                    // 1 seconde pour la date/heure
        CHECK_MATERIALS: 5 * 60 * 1000,    // 5 minutes pour vérifier nouveaux matériaux
        CHECK_VALIDATION: 5 * 60 * 1000    // 5 minutes pour validation
    },

    // Configuration DataTables
    DATATABLES: {
        LANGUAGE_URL: "//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json",
        DOM: 'Blfrtip',
        BUTTONS: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm',
                title: 'Achats_Materiaux_' + new Date().toISOString().slice(0, 10)
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm',
                title: 'Achats_Materiaux_' + new Date().toISOString().slice(0, 10)
            }
        ]
    },

    // Messages utilisateur
    MESSAGES: {
        SUCCESS: {
            PURCHASE_COMPLETE: 'Achat enregistré avec succès',
            ORDER_CANCELLED: 'Commande annulée avec succès',
            BULK_ACTION: 'Action groupée effectuée avec succès'
        },
        ERROR: {
            GENERIC: 'Une erreur est survenue',
            NETWORK: 'Erreur de connexion au serveur',
            VALIDATION: 'Données invalides',
            UNAUTHORIZED: 'Accès non autorisé'
        },
        CONFIRM: {
            DELETE: 'Êtes-vous sûr de vouloir supprimer cette commande ?',
            CANCEL: 'Êtes-vous sûr de vouloir annuler cette commande ?'
        }
    },

    // Classes CSS pour les statuts
    STATUS_CLASSES: {
        'pas validé': 'bg-yellow-100 text-yellow-800',
        'validé': 'bg-green-100 text-green-800',
        'en_cours': 'bg-blue-100 text-blue-800',
        'commandé': 'bg-purple-100 text-purple-800',
        'reçu': 'bg-gray-100 text-gray-800',
        'annulé': 'bg-red-100 text-red-800',
        'valide_en_cours': 'bg-indigo-100 text-indigo-800'
    }
};

// =====================================================
// VARIABLES GLOBALES
// =====================================================

// Tables DataTables
let materialsTable = null;
let orderedMaterialsTable = null;
let partialOrdersTable = null;
let receivedMaterialsTable = null;
let supplierReturnsTable = null;

// Variables pour la gestion des données
let allMaterials = [];
let selectedMaterials = [];
let currentFilters = {};
let fournisseurs = [];

// Variables pour les modals et formulaires
let currentModal = null;
let formData = {};

// Intervalles pour les tâches automatiques
let refreshIntervals = {
    datetime: null,
    materials: null,
    validation: null
};

// Variable globale pour marquer l'initialisation
let achatsModuleInitialized = false;

// =====================================================
// INITIALISATION PRINCIPALE
// =====================================================

/**
 * Point d'entrée principal - Initialisation complète au chargement du DOM
 */
$(document).ready(function () {
    console.log('🚀 Initialisation du module Achats de Matériaux...');

    try {
        initializeApplication();
        achatsModuleInitialized = true;
        console.log('✅ Module Achats de Matériaux initialisé avec succès');
    } catch (error) {
        console.error('❌ Erreur lors de l\'initialisation:', error);
        showNotification('Erreur lors de l\'initialisation de l\'application', 'error');
    }
});

/**
 * Fonction principale d'initialisation
 */
function initializeApplication() {
    // 1. Initialisation de base
    initializeBasicComponents();

    // 2. Configuration des gestionnaires d'événements
    setupEventHandlers();

    // 3. Initialisation des DataTables
    initializeDataTables();

    // 4. Chargement des données initiales
    loadInitialData();

    // 5. Configuration des tâches automatiques
    setupAutomaticTasks();

    // 6. Configuration des gestionnaires d'erreurs
    setupErrorHandlers();
}

/**
 * Initialisation des composants de base
 */
function initializeBasicComponents() {
    // Affichage de la date courante
    displayCurrentDate();

    // Initialisation des tooltips
    initializeTooltips();

    // Configuration des sélecteurs multiples
    initializeMultiSelect();

    // Configuration des dropdowns
    initializeDropdowns();
}

/**
 * Configuration des gestionnaires d'événements
 */
function setupEventHandlers() {
    // Gestionnaires pour les filtres
    setupFilterHandlers();

    // Gestionnaires pour les actions en lot
    setupBulkActionHandlers();

    // Gestionnaires pour les modals
    setupModalHandlers();

    // Gestionnaires pour les formulaires
    setupFormHandlers();

    // Gestionnaires pour les checkboxes
    setupCheckboxHandlers();

    // Gestionnaires pour les boutons d'action
    setupActionButtonHandlers();
}

// =====================================================
// INITIALISATION DES DATATABLES
// =====================================================

/**
 * Initialisation de tous les DataTables
 */
function initializeDataTables() {
    console.log('📊 Initialisation des DataTables...');

    // Table des matériaux en attente
    initializeMaterialsTable();

    // Table des matériaux commandés
    initializeOrderedMaterialsTable();

    // Table des commandes partielles
    initializePartialOrdersTable();

    // Table des matériaux reçus
    initializeReceivedMaterialsTable();

    // Table des retours fournisseurs
    initializeSupplierReturnsTable();
}

/**
 * Initialisation de la table des matériaux en attente
 */
function initializeMaterialsTable() {
    if ($.fn.DataTable.isDataTable('#materialsTable')) {
        $('#materialsTable').DataTable().destroy();
    }

    materialsTable = $('#materialsTable').DataTable({
        language: {
            url: CONFIG.DATATABLES.LANGUAGE_URL
        },
        dom: CONFIG.DATATABLES.DOM,
        buttons: CONFIG.DATATABLES.BUTTONS,
        responsive: true,
        processing: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tout"]],
        columnDefs: [
            {
                targets: 0,
                orderable: false,
                className: 'select-checkbox',
                render: function (data, type, row) {
                    return `<input type="checkbox" class="material-checkbox" 
                            value="${row.id}" data-expression="${row.idExpression}">`;
                }
            },
            {
                targets: [6], // Colonne statut
                render: function (data, type, row) {
                    const statusClass = CONFIG.STATUS_CLASSES[data] || 'bg-gray-100 text-gray-800';
                    return `<span class="px-2 py-1 text-xs rounded-full ${statusClass}">${data}</span>`;
                }
            },
            {
                targets: [7], // Colonne prix
                render: function (data, type, row) {
                    return data ? formatCurrency(data) : 'Non défini';
                }
            },
            {
                targets: [-1], // Dernière colonne (Actions)
                orderable: false,
                render: function (data, type, row) {
                    return generateActionButtons(row);
                }
            }
        ],
        order: [[9, 'desc']], // Trier par date de création
        initComplete: function () {
            console.log('✅ Table des matériaux en attente initialisée');
            setupTableFilters(this.api());
        }
    });
}

/**
 * Initialisation de la table des matériaux commandés
 */
function initializeOrderedMaterialsTable() {
    if ($.fn.DataTable.isDataTable('#orderedMaterialsTable')) {
        $('#orderedMaterialsTable').DataTable().destroy();
    }

    orderedMaterialsTable = $('#orderedMaterialsTable').DataTable({
        language: {
            url: CONFIG.DATATABLES.LANGUAGE_URL
        },
        dom: CONFIG.DATATABLES.DOM,
        buttons: CONFIG.DATATABLES.BUTTONS,
        responsive: true,
        processing: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tout"]],
        columnDefs: [
            {
                targets: 0,
                orderable: false,
                className: 'select-checkbox',
                render: function (data, type, row) {
                    return `<input type="checkbox" class="ordered-material-checkbox" 
                            value="${row.id}" data-expression="${row.idExpression}">`;
                }
            },
            {
                targets: [6], // Colonne statut
                render: function (data, type, row) {
                    const statusClass = CONFIG.STATUS_CLASSES[data] || 'bg-gray-100 text-gray-800';
                    return `<span class="px-2 py-1 text-xs rounded-full ${statusClass}">${data}</span>`;
                }
            },
            {
                targets: [-1], // Dernière colonne (Actions)
                orderable: false,
                render: function (data, type, row) {
                    return generateOrderedMaterialActions(row);
                }
            }
        ],
        order: [[9, 'desc']], // Trier par date
        initComplete: function () {
            console.log('✅ Table des matériaux commandés initialisée');
        }
    });
}

/**
 * Initialisation de la table des commandes partielles
 */
function initializePartialOrdersTable() {
    if ($.fn.DataTable.isDataTable('#partialOrdersTable')) {
        $('#partialOrdersTable').DataTable().destroy();
    }

    partialOrdersTable = $('#partialOrdersTable').DataTable({
        language: {
            url: CONFIG.DATATABLES.LANGUAGE_URL
        },
        dom: CONFIG.DATATABLES.DOM,
        buttons: CONFIG.DATATABLES.BUTTONS,
        responsive: true,
        processing: true,
        pageLength: 25,
        columnDefs: [
            {
                targets: 0,
                orderable: false,
                className: 'select-checkbox',
                render: function (data, type, row) {
                    return `<input type="checkbox" class="partial-order-checkbox" 
                            value="${row.id}" data-expression="${row.idExpression}">`;
                }
            },
            {
                targets: [-1], // Actions
                orderable: false,
                render: function (data, type, row) {
                    return generatePartialOrderActions(row);
                }
            }
        ],
        initComplete: function () {
            console.log('✅ Table des commandes partielles initialisée');
        }
    });
}

/**
 * Initialisation de la table des matériaux reçus
 */
function initializeReceivedMaterialsTable() {
    if ($.fn.DataTable.isDataTable('#receivedMaterialsTable')) {
        $('#receivedMaterialsTable').DataTable().destroy();
    }

    receivedMaterialsTable = $('#receivedMaterialsTable').DataTable({
        language: {
            url: CONFIG.DATATABLES.LANGUAGE_URL
        },
        dom: CONFIG.DATATABLES.DOM,
        buttons: CONFIG.DATATABLES.BUTTONS,
        responsive: true,
        processing: true,
        pageLength: 25,
        columnDefs: [
            {
                targets: [-1], // Actions
                orderable: false,
                render: function (data, type, row) {
                    return generateReceivedMaterialActions(row);
                }
            }
        ],
        order: [[5, 'desc']], // Trier par date de réception
        initComplete: function () {
            console.log('✅ Table des matériaux reçus initialisée');
        }
    });
}

/**
 * Initialisation de la table des retours fournisseurs
 */
function initializeSupplierReturnsTable() {
    if ($.fn.DataTable.isDataTable('#supplierReturnsTable')) {
        $('#supplierReturnsTable').DataTable().destroy();
    }

    supplierReturnsTable = $('#supplierReturnsTable').DataTable({
        language: {
            url: CONFIG.DATATABLES.LANGUAGE_URL
        },
        dom: CONFIG.DATATABLES.DOM,
        buttons: CONFIG.DATATABLES.BUTTONS,
        responsive: true,
        processing: true,
        pageLength: 25,
        columnDefs: [
            {
                targets: [-1], // Actions
                orderable: false,
                render: function (data, type, row) {
                    return generateSupplierReturnActions(row);
                }
            }
        ],
        initComplete: function () {
            console.log('✅ Table des retours fournisseurs initialisée');
        }
    });
}

// =====================================================
// GESTIONNAIRES D'ÉVÉNEMENTS
// =====================================================

/**
 * Configuration des gestionnaires de filtres
 */
function setupFilterHandlers() {
    // Filtre de recherche générale
    $('#search-materials').on('input', debounce(function () {
        const searchTerm = $(this).val();
        filterMaterialsBySearch(searchTerm);
    }, 300));

    // Filtres par date
    $('#dateDebut, #dateFin').on('change', function () {
        applyDateFilters();
    });

    // Filtre par client
    $('#clientFilter').on('change', function () {
        const client = $(this).val();
        filterMaterialsByClient(client);
    });

    // Filtre par fournisseur
    $('#fournisseurFilter').on('change', function () {
        const fournisseur = $(this).val();
        filterMaterialsByFournisseur(fournisseur);
    });

    // Filtre par statut
    $('#statusFilter').on('change', function () {
        const status = $(this).val();
        filterMaterialsByStatus(status);
    });

    // Bouton de réinitialisation des filtres
    $('#reset-filters-btn').on('click', function () {
        resetAllFilters();
    });

    // Bouton d'application des filtres
    $('#apply-filters-btn').on('click', function () {
        applyAllFilters();
    });
}

/**
 * Configuration des gestionnaires d'actions en lot
 */
function setupBulkActionHandlers() {
    // Sélection de tous les matériaux
    $('#select-all-materials').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('.material-checkbox').prop('checked', isChecked).trigger('change');
    });

    // Sélection de tous les matériaux commandés
    $('#select-all-ordered-materials').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('.ordered-material-checkbox').prop('checked', isChecked).trigger('change');
    });

    // Achat en lot
    $('#bulk-purchase-btn').on('click', function () {
        const selectedItems = getSelectedMaterials();
        if (selectedItems.length === 0) {
            showNotification('Aucun matériau sélectionné', 'warning');
            return;
        }
        openBulkPurchaseModal(selectedItems);
    });

    // Annulation en lot
    $('#bulk-cancel-btn').on('click', function () {
        const selectedItems = getSelectedOrderedMaterials();
        if (selectedItems.length === 0) {
            showNotification('Aucune commande sélectionnée', 'warning');
            return;
        }
        cancelMultipleOrders(selectedItems);
    });

    // Complétion en lot des commandes partielles
    $('#bulk-complete-btn').on('click', function () {
        const selectedItems = getSelectedPartialOrders();
        if (selectedItems.length === 0) {
            showNotification('Aucune commande partielle sélectionnée', 'warning');
            return;
        }
        completeMultiplePartialOrders(selectedItems);
    });
}

/**
 * Configuration des gestionnaires de modals
 */
function setupModalHandlers() {
    // Modal d'achat individuel
    $(document).on('click', '.purchase-btn', function () {
        const row = $(this).closest('tr');
        const materialData = getMaterialDataFromRow(row);
        openPurchaseModal(materialData);
    });

    // Modal de modification de commande
    $(document).on('click', '.edit-order-btn', function () {
        const orderId = $(this).data('order-id');
        const expressionId = $(this).data('expression-id');
        openEditOrderModal(orderId, expressionId);
    });

    // Modal de détails de commande
    $(document).on('click', '.view-details-btn', function () {
        const orderId = $(this).data('order-id');
        viewOrderDetails(orderId);
    });

    // Fermeture des modals
    $('.modal .close, .modal [data-dismiss="modal"]').on('click', function () {
        $(this).closest('.modal').hide();
    });

    // Fermeture des modals en cliquant à l'extérieur
    $(window).on('click', function (event) {
        if ($(event.target).hasClass('modal')) {
            $(event.target).hide();
        }
    });
}

/**
 * Configuration des gestionnaires de formulaires
 */
function setupFormHandlers() {
    // Formulaire d'achat individuel
    $('#purchase-form').on('submit', function (e) {
        e.preventDefault();
        handleIndividualPurchase(this);
    });

    // Formulaire d'achat en lot
    $('#bulk-purchase-form').on('submit', function (e) {
        e.preventDefault();
        handleBulkPurchase(this);
    });

    // Formulaire de modification de commande
    $('#edit-order-form').on('submit', function (e) {
        e.preventDefault();
        handleOrderEdit(this);
    });

    // Gestion des changements de fournisseur
    $(document).on('change', '#fournisseur', function () {
        const fournisseur = $(this).val();
        updateFournisseurInfo(fournisseur);
    });

    // Validation en temps réel
    $(document).on('input', '.required-field', function () {
        validateField(this);
    });
}

/**
 * Configuration des gestionnaires de checkboxes
 */
function setupCheckboxHandlers() {
    // Gestion des checkboxes individuelles
    $(document).on('change', '.material-checkbox', function () {
        updateMaterialSelection();
        updateBulkActionButtons();
    });

    $(document).on('change', '.ordered-material-checkbox', function () {
        updateOrderedMaterialSelection();
        updateBulkActionButtons();
    });

    $(document).on('change', '.partial-order-checkbox', function () {
        updatePartialOrderSelection();
        updateBulkActionButtons();
    });
}

/**
 * Configuration des gestionnaires de boutons d'action
 */
function setupActionButtonHandlers() {
    // Bouton de substitution
    $(document).on('click', '.substitute-btn', function () {
        const materialId = $(this).data('material-id');
        const designation = $(this).data('designation');
        const expressionId = $(this).data('expression-id');
        openSubstitutionModal(materialId, designation, expressionId);
    });

    // Bouton de génération de bon de commande
    $(document).on('click', '.generate-bon-commande-btn', function () {
        const expressionId = $(this).data('expression-id');
        generateBonCommande(expressionId);
    });

    // Bouton de téléchargement de bon de commande
    $(document).on('click', '.download-bon-commande-btn', function () {
        const bonCommandeId = $(this).data('bon-commande-id');
        downloadBonCommande(bonCommandeId);
    });

    // Bouton de visualisation du stock
    $(document).on('click', '.view-stock-btn', function () {
        const designation = $(this).data('designation');
        viewStockDetails(designation);
    });
}

// =====================================================
// CHARGEMENT DES DONNÉES INITIALES
// =====================================================

/**
 * Chargement de toutes les données initiales avec les nouveaux gestionnaires
 */
function loadInitialData() {
    console.log('📊 Chargement des données initiales...');

    // Chargement des fournisseurs avec le nouveau module
    FournisseursModule.loadFournisseurs();

    // Chargement des modes de paiement avec le nouveau gestionnaire
    PaymentMethodsManager.loadPaymentMethods();

    // Mise à jour des compteurs de notifications
    updateNotificationCounters();

    // Chargement des filtres sauvegardés
    loadSavedFilters();

    // Configuration de la validation en temps réel
    setupRealTimeValidation();
}

/**
 * Configuration des tâches automatiques et intervalles avec nouvelles vérifications
 */
function setupAutomaticTasks() {
    // Mise à jour de l'heure toutes les secondes
    refreshIntervals.datetime = setInterval(() => {
        displayCurrentDate();
    }, CONFIG.REFRESH_INTERVALS.DATETIME);

    // Vérification des nouveaux matériaux toutes les 5 minutes
    refreshIntervals.materials = setInterval(() => {
        updateNotificationCounters();
    }, CONFIG.REFRESH_INTERVALS.CHECK_MATERIALS);

    // Vérification des validations en attente toutes les 5 minutes
    refreshIntervals.validation = setInterval(() => {
        checkOrderValidationStatus();
    }, CONFIG.REFRESH_INTERVALS.CHECK_VALIDATION);

    // Nouveau : Vérification des changements de statut toutes les 2 minutes
    refreshIntervals.statusCheck = setInterval(() => {
        checkOrderStatusChanges();
    }, 2 * 60 * 1000);
}

/**
 * Vérification des changements de statut des commandes
 */
async function checkOrderStatusChanges() {
    try {
        const response = await fetch('api/orders/check_status_changes.php');
        const data = await response.json();

        if (data.success && data.changes.length > 0) {
            // Rafraîchir les tables
            refreshDataTables();

            // Notification discrète
            showNotification(`${data.changes.length} changement(s) de statut détecté(s)`, 'info');
        }

    } catch (error) {
        console.warn('⚠️ Impossible de vérifier les changements de statut:', error);
    }
}

/**
 * Configuration améliorée des gestionnaires d'événements avec nouveaux gestionnaires
 */
function setupEventHandlers() {
    // Gestionnaires pour les filtres
    setupFilterHandlers();

    // Gestionnaires pour les actions en lot avec nouveaux managers
    setupBulkActionHandlers();

    // Gestionnaires pour les modals
    setupModalHandlers();

    // Gestionnaires pour les formulaires avec validation
    setupFormHandlers();

    // Gestionnaires pour les checkboxes avec nouveaux managers
    setupCheckboxHandlers();

    // Gestionnaires pour les boutons d'action
    setupActionButtonHandlers();

    // Nouveau : Gestionnaires pour les fonctions avancées
    setupAdvancedEventHandlers();
}

/**
 * Configuration des gestionnaires d'événements avancés
 */
function setupAdvancedEventHandlers() {
    // Gestionnaire pour les formulaires d'achat en lot
    $('#bulk-purchase-form').on('submit', async function (e) {
        e.preventDefault();
        await BulkPurchaseManager.handleBulkPurchase(this);
    });

    // Gestionnaire pour les formulaires de complétion partielle
    $('#complete-partial-form').on('submit', async function (e) {
        e.preventDefault();
        await PartialOrdersManager.handleCompletionSubmit(this);
    });

    // Gestionnaire pour les formulaires de modification
    $('#edit-order-form').on('submit', function (e) {
        EditOrderManager.handleSubmit(e);
    });

    // Gestionnaire pour les formulaires de substitution
    $('#substitution-form').on('submit', async function (e) {
        e.preventDefault();
        await SubstitutionManager.handleSubstitution(this);
    });

    // Gestionnaires pour l'autocomplétion des fournisseurs
    $(document).on('focus', 'input[name*="fournisseur"]', function () {
        const inputId = $(this).attr('id');
        const suggestionsId = inputId + '-suggestions';
        FournisseursModule.setupAutocomplete(inputId, suggestionsId);
    });

    // Gestionnaire pour les changements de mode de paiement
    $(document).on('change', 'select[name*="payment"]', function () {
        const paymentMethodId = $(this).val();
        updatePaymentMethodInfo(paymentMethodId);
    });
}

/**
 * Mise à jour des informations de mode de paiement
 */
function updatePaymentMethodInfo(paymentMethodId) {
    if (!paymentMethodId) return;

    const method = PaymentMethodsManager.paymentMethods.find(m => m.id == paymentMethodId);
    if (method) {
        // Afficher les informations du mode de paiement
        const infoContainer = $('.payment-method-info');
        if (infoContainer.length) {
            infoContainer.html(`
                <div class="alert alert-info">
                    <strong>${method.nom}</strong><br>
                    ${method.description || 'Mode de paiement sélectionné'}
                </div>
            `);
        }
    }
}

/**
 * Configuration améliorée des gestionnaires d'actions en lot
 */
function setupBulkActionHandlers() {
    // Sélection de tous les matériaux
    $('#select-all-materials').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('.material-checkbox').prop('checked', isChecked).trigger('change');
    });

    // Sélection de tous les matériaux commandés
    $('#select-all-ordered-materials').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('.ordered-material-checkbox').prop('checked', isChecked).trigger('change');
    });

    // Achat en lot avec nouveau gestionnaire
    $('#bulk-purchase-btn').on('click', function () {
        BulkPurchaseManager.openModal();
    });

    // Annulation en lot avec nouveau gestionnaire
    $('#bulk-cancel-btn').on('click', function () {
        CancelManager.cancelMultipleOrders();
    });

    // Complétion en lot des commandes partielles
    $('#bulk-complete-btn').on('click', function () {
        const selectedItems = getSelectedPartialOrders();
        if (selectedItems.length === 0) {
            showNotification('Aucune commande partielle sélectionnée', 'warning');
            return;
        }
        completeMultiplePartialOrders(selectedItems);
    });

    // Nouveau : Annulation en lot des matériaux en attente
    $('#bulk-cancel-pending-btn').on('click', function () {
        CancelManager.cancelMultiplePending();
    });

    // Nouveau : Export en lot
    $('#bulk-export-btn').on('click', function () {
        exportSelectedMaterials();
    });
}

/**
 * Fonction de complétion multiple des commandes partielles
 */
async function completeMultiplePartialOrders(selectedItems) {
    const result = await Swal.fire({
        title: 'Complétion multiple',
        text: `Êtes-vous sûr de vouloir compléter ${selectedItems.length} commande(s) partielle(s) ?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, compléter',
        cancelButtonText: 'Annuler'
    });

    if (result.isConfirmed) {
        try {
            showProcessingLoader('Complétion des commandes en cours...');

            const completionPromises = selectedItems.map(item =>
                PartialOrdersManager.completeOrder(item.id, item.designation, item.qt_restante, item.unit, item.sourceTable)
            );

            await Promise.all(completionPromises);

            hideProcessingLoader();
            showNotification(`${selectedItems.length} commande(s) complétée(s) avec succès`, 'success');
            refreshDataTables();
            updateNotificationCounters();
            clearSelection();

        } catch (error) {
            hideProcessingLoader();
            console.error('Erreur lors de la complétion multiple:', error);
            showNotification('Erreur lors de la complétion des commandes', 'error');
        }
    }
}

/**
 * Export des matériaux sélectionnés
 */
function exportSelectedMaterials() {
    const selectedMaterials = getSelectedMaterials();

    if (selectedMaterials.length === 0) {
        showNotification('Aucun matériau sélectionné', 'warning');
        return;
    }

    Swal.fire({
        title: 'Format d\'export',
        text: 'Choisissez le format d\'export souhaité',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Excel',
        cancelButtonText: 'PDF',
        showDenyButton: true,
        denyButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            exportMaterialsToExcel(selectedMaterials);
        } else if (result.isDismissed && result.dismiss !== Swal.DismissReason.cancel) {
            exportMaterialsToPDF(selectedMaterials);
        }
    });
}

/**
 * Export Excel des matériaux
 */
function exportMaterialsToExcel(materials) {
    const formData = new FormData();
    formData.append('materials', JSON.stringify(materials));
    formData.append('format', 'excel');

    fetch('export/export_materials.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `materiaux_${new Date().toISOString().slice(0, 10)}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            showNotification('Export Excel terminé', 'success');
        })
        .catch(error => {
            console.error('Erreur lors de l\'export Excel:', error);
            showNotification('Erreur lors de l\'export Excel', 'error');
        });
}

/**
 * Export PDF des matériaux
 */
function exportMaterialsToPDF(materials) {
    const formData = new FormData();
    formData.append('materials', JSON.stringify(materials));
    formData.append('format', 'pdf');

    fetch('export/export_materials.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `materiaux_${new Date().toISOString().slice(0, 10)}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            showNotification('Export PDF terminé', 'success');
        })
        .catch(error => {
            console.error('Erreur lors de l\'export PDF:', error);
            showNotification('Erreur lors de l\'export PDF', 'error');
        });
}

/**
 * Chargement de la liste des fournisseurs
 */
function loadFournisseurs() {
    $.ajax({
        url: CONFIG.API_URLS.FOURNISSEURS,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                fournisseurs = response.fournisseurs;
                populateFournisseurSelects();
                console.log('✅ Fournisseurs chargés:', fournisseurs.length);
            } else {
                console.error('❌ Erreur lors du chargement des fournisseurs:', response.message);
            }
        },
        error: function (xhr, status, error) {
            console.error('❌ Erreur AJAX lors du chargement des fournisseurs:', error);
        }
    });
}

/**
 * Chargement des modes de paiement
 */
function loadPaymentMethods() {
    $.ajax({
        url: CONFIG.API_URLS.PAYMENT_METHODS,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                populatePaymentMethodSelects(response.payment_methods);
                console.log('✅ Modes de paiement chargés');
            }
        },
        error: function (xhr, status, error) {
            console.error('❌ Erreur lors du chargement des modes de paiement:', error);
        }
    });
}

// =====================================================
// GESTIONNAIRES SPÉCIALISÉS MANQUANTS
// =====================================================

/**
 * Gestionnaire des achats en lot
 */
const BulkPurchaseManager = {
    selectedMaterials: [],

    async openModal() {
        const selectedMaterials = getSelectedMaterials();
        if (selectedMaterials.length === 0) {
            showNotification('Aucun matériau sélectionné', 'warning');
            return;
        }

        this.selectedMaterials = selectedMaterials;
        await this.prepareBulkPurchaseModal(selectedMaterials);
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

        // Initialiser l'autocomplétion des fournisseurs
        FournisseursModule.setupAutocomplete('fournisseur-bulk', 'fournisseurs-suggestions-bulk');

        // Peupler les modes de paiement
        PaymentMethodsManager.populatePaymentSelect('payment-method-bulk');

        // Afficher le modal
        const modal = document.getElementById('bulk-purchase-modal');
        if (modal) {
            modal.style.display = 'flex';
        }

        // Charger les prix
        await this.loadBulkPrices(materials);
    },

    async loadBulkPrices(materials) {
        const tbody = document.getElementById('individual-prices-tbody');

        try {
            const pricePromises = materials.map(async (material) => {
                const apiUrl = material.sourceTable === 'besoins' ?
                    'commandes-traitement/besoins/get_besoin_info.php' :
                    CONFIG.API_URLS.MATERIAL_INFO;

                const response = await fetch(`${apiUrl}?id=${material.id}`);
                const data = await response.json();

                return {
                    ...material,
                    prix_unitaire: data.prix_unitaire || 0,
                    fournisseur: data.fournisseur || ''
                };
            });

            const materialsWithPrices = await Promise.all(pricePromises);
            this.populatePricesTable(materialsWithPrices);

        } catch (error) {
            console.error('Erreur lors du chargement des prix:', error);
            showNotification('Erreur lors du chargement des prix', 'error');
        }
    },

    populatePricesTable(materials) {
        const tbody = document.getElementById('individual-prices-tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        materials.forEach(material => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="px-4 py-2">${material.designation}</td>
                <td class="px-4 py-2">${material.qt_acheter}</td>
                <td class="px-4 py-2">
                    <input type="number" 
                           name="prix_individuel[${material.id}]" 
                           value="${material.prix_unitaire || ''}"
                           step="0.01" 
                           min="0"
                           class="w-full px-2 py-1 border rounded individual-price-input"
                           placeholder="Prix unitaire">
                </td>
                <td class="px-4 py-2 total-price">0 €</td>
            `;
            tbody.appendChild(row);
        });

        // Calculer les totaux
        this.calculateTotals();

        // Écouter les changements de prix
        $('.individual-price-input').on('input', () => this.calculateTotals());
    },

    calculateTotals() {
        let grandTotal = 0;

        $('#individual-prices-tbody tr').each(function () {
            const quantity = parseFloat($(this).find('td:nth-child(2)').text()) || 0;
            const price = parseFloat($(this).find('input').val()) || 0;
            const total = quantity * price;

            $(this).find('.total-price').text(formatCurrency(total));
            grandTotal += total;
        });

        $('#grand-total').text(formatCurrency(grandTotal));
    }
};

/**
 * Gestionnaire des commandes partielles
 */
const PartialOrdersManager = {
    async completeOrder(id, designation, remaining, unit, sourceTable = 'expression_dym') {
        try {
            console.log(`🔄 Complétion de la commande ${id} (${sourceTable})`);

            let apiUrl = `${CONFIG.API_URLS.PARTIAL_ORDERS}?action=get_material_info&id=${id}`;
            if (sourceTable === 'besoins') {
                apiUrl = `commandes-traitement/besoins/get_besoin_with_remaining.php?id=${id}`;
            }

            const response = await fetch(apiUrl);
            const materialInfo = await response.json();

            if (materialInfo.success === false) {
                throw new Error(materialInfo.message || 'Impossible de récupérer les informations du matériau');
            }

            this.showCompleteOrderModal(id, designation, remaining, unit, materialInfo, sourceTable);

        } catch (error) {
            console.error("❌ Erreur lors de la récupération des infos du matériau:", error);
            showNotification('Erreur lors du chargement des données', 'error');
        }
    },

    showCompleteOrderModal(id, designation, remaining, unit, materialInfo, sourceTable) {
        const modal = document.getElementById('complete-partial-modal');
        if (!modal) {
            console.error('Modal de complétion non trouvé');
            return;
        }

        // Remplir les données
        document.getElementById('complete-order-id').value = id;
        document.getElementById('complete-source-table').value = sourceTable;
        document.getElementById('complete-designation').value = designation;
        document.getElementById('complete-remaining').value = remaining;
        document.getElementById('complete-unit').value = unit;

        // Afficher les informations
        modal.querySelector('.modal-title').textContent = `Compléter: ${designation}`;
        modal.querySelector('#remaining-info').textContent = `Quantité restante: ${remaining} ${unit}`;

        // Préremplir avec les données existantes si disponibles
        if (materialInfo.data) {
            const data = materialInfo.data;
            if (data.fournisseur) document.getElementById('complete-fournisseur').value = data.fournisseur;
            if (data.prix_unitaire) document.getElementById('complete-prix').value = data.prix_unitaire;
        }

        modal.style.display = 'flex';
    },

    async viewDetails(id, sourceTable = 'expression_dym') {
        try {
            let apiUrl = `commandes-traitement/api.php?action=get_order_details&id=${id}`;
            if (sourceTable === 'besoins') {
                apiUrl = `commandes-traitement/besoins/get_besoin_details.php?id=${id}`;
            }

            const response = await fetch(apiUrl);
            const data = await response.json();

            if (data.success) {
                this.showDetailsModal(data);
            } else {
                showNotification('Erreur lors du chargement des détails', 'error');
            }

        } catch (error) {
            console.error('Erreur lors de la récupération des détails:', error);
            showNotification('Erreur de connexion', 'error');
        }
    },

    showDetailsModal(data) {
        // Utiliser SweetAlert2 pour afficher les détails
        Swal.fire({
            title: 'Détails de la commande partielle',
            html: this.buildDetailsHTML(data),
            width: 600,
            showCloseButton: true,
            confirmButtonText: 'Fermer'
        });
    },

    buildDetailsHTML(data) {
        const order = data.order;
        return `
            <div class="text-left space-y-4">
                <div>
                    <h4 class="font-semibold">Informations du matériau</h4>
                    <p><strong>Désignation:</strong> ${order.designation}</p>
                    <p><strong>Quantité commandée:</strong> ${order.qt_acheter} ${order.unit}</p>
                    <p><strong>Quantité restante:</strong> ${order.qt_restante} ${order.unit}</p>
                    <p><strong>Fournisseur:</strong> ${order.fournisseur || 'Non défini'}</p>
                    <p><strong>Prix unitaire:</strong> ${order.prix_unitaire ? formatCurrency(order.prix_unitaire) : 'Non défini'}</p>
                </div>
                
                <div>
                    <h4 class="font-semibold">Informations du projet</h4>
                    <p><strong>Projet:</strong> ${order.code_projet || 'N/A'}</p>
                    <p><strong>Client:</strong> ${order.nom_client || 'N/A'}</p>
                    <p><strong>Date de commande:</strong> ${new Date(order.created_at).toLocaleDateString('fr-FR')}</p>
                </div>
            </div>
        `;
    }
};

/**
 * Gestionnaire de modification des commandes
 */
const EditOrderManager = {
    openModal(orderId, expressionId, designation, sourceTable, quantity, unit, price, supplier) {
        const modal = document.getElementById('edit-order-modal');
        if (!modal) {
            console.error('Modal de modification non trouvé');
            return;
        }

        // Remplir les champs
        document.getElementById('edit-order-id').value = orderId;
        document.getElementById('edit-expression-id').value = expressionId;
        document.getElementById('edit-designation').value = designation;
        document.getElementById('edit-source-table').value = sourceTable;
        document.getElementById('edit-quantite').value = quantity;
        document.getElementById('edit-unite').value = unit;
        document.getElementById('edit-prix-unitaire').value = price;
        document.getElementById('edit-fournisseur').value = supplier;

        // Charger les modes de paiement
        PaymentMethodsManager.populatePaymentSelect('edit-mode-paiement');

        modal.style.display = 'flex';
    },

    closeModal() {
        const modal = document.getElementById('edit-order-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    },

    async handleSubmit(event) {
        event.preventDefault();

        const formData = new FormData(event.target);

        try {
            const response = await fetch('api/orders/update_order.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showNotification('Commande modifiée avec succès', 'success');
                this.closeModal();
                refreshDataTables();
            } else {
                showNotification(result.message || 'Erreur lors de la modification', 'error');
            }

        } catch (error) {
            console.error('Erreur lors de la modification:', error);
            showNotification('Erreur de connexion', 'error');
        }
    }
};

/**
 * Gestionnaire d'annulation des commandes
 */
const CancelManager = {
    async cancelSingleOrder(id, expressionId, designation, sourceTable = 'expression_dym') {
        const result = await Swal.fire({
            title: 'Confirmer l\'annulation',
            text: `Êtes-vous sûr de vouloir annuler la commande pour "${designation}" ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non, garder'
        });

        if (result.isConfirmed) {
            try {
                const formData = new FormData();
                formData.append('order_id', id);
                formData.append('expression_id', expressionId);
                formData.append('source_table', sourceTable);
                formData.append('action', 'cancel_single');

                const response = await fetch(CONFIG.API_URLS.CANCEL_ORDERS, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Commande annulée avec succès', 'success');
                    refreshDataTables();
                    updateNotificationCounters();
                } else {
                    showNotification(data.message || 'Erreur lors de l\'annulation', 'error');
                }

            } catch (error) {
                console.error('Erreur lors de l\'annulation:', error);
                showNotification('Erreur de connexion', 'error');
            }
        }
    },

    async cancelPendingMaterial(id, expressionId, designation, sourceTable = 'expression_dym') {
        const result = await Swal.fire({
            title: 'Annuler le matériau en attente',
            text: `Êtes-vous sûr de vouloir annuler "${designation}" ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non, garder'
        });

        if (result.isConfirmed) {
            try {
                const formData = new FormData();
                formData.append('material_id', id);
                formData.append('expression_id', expressionId);
                formData.append('source_table', sourceTable);

                const response = await fetch(CONFIG.API_URLS.CANCEL_PENDING, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Matériau annulé avec succès', 'success');
                    refreshDataTables();
                    updateNotificationCounters();
                } else {
                    showNotification(data.message || 'Erreur lors de l\'annulation', 'error');
                }

            } catch (error) {
                console.error('Erreur lors de l\'annulation:', error);
                showNotification('Erreur de connexion', 'error');
            }
        }
    },

    async cancelMultipleOrders() {
        const selectedOrders = getSelectedOrderedMaterials();

        if (selectedOrders.length === 0) {
            showNotification('Aucune commande sélectionnée', 'warning');
            return;
        }

        const result = await Swal.fire({
            title: 'Annulation multiple',
            text: `Êtes-vous sûr de vouloir annuler ${selectedOrders.length} commande(s) ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, annuler tout',
            cancelButtonText: 'Non, garder'
        });

        if (result.isConfirmed) {
            try {
                const formData = new FormData();
                formData.append('orders', JSON.stringify(selectedOrders));
                formData.append('action', 'cancel_multiple');

                const response = await fetch(CONFIG.API_URLS.CANCEL_ORDERS, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(`${selectedOrders.length} commande(s) annulée(s) avec succès`, 'success');
                    refreshDataTables();
                    updateNotificationCounters();
                    clearSelection();
                } else {
                    showNotification(data.message || 'Erreur lors de l\'annulation', 'error');
                }

            } catch (error) {
                console.error('Erreur lors de l\'annulation multiple:', error);
                showNotification('Erreur de connexion', 'error');
            }
        }
    }
};

/**
 * Module de gestion des fournisseurs avec autocomplétion
 */
const FournisseursModule = {
    fournisseurs: [],

    async loadFournisseurs() {
        try {
            const response = await fetch(CONFIG.API_URLS.FOURNISSEURS);
            const data = await response.json();

            if (data.success) {
                this.fournisseurs = data.fournisseurs;
                console.log('✅ Fournisseurs chargés:', this.fournisseurs.length);
            }

        } catch (error) {
            console.error('❌ Erreur lors du chargement des fournisseurs:', error);
        }
    },

    setupAutocomplete(inputId, suggestionsId) {
        const input = document.getElementById(inputId);
        const suggestions = document.getElementById(suggestionsId);

        if (!input || !suggestions) return;

        input.addEventListener('input', (e) => {
            const value = e.target.value.toLowerCase();

            if (value.length < 2) {
                suggestions.innerHTML = '';
                suggestions.style.display = 'none';
                return;
            }

            const matches = this.fournisseurs.filter(f =>
                f.nom.toLowerCase().includes(value)
            ).slice(0, 5);

            if (matches.length > 0) {
                suggestions.innerHTML = matches.map(f => `
                    <div class="suggestion-item p-2 cursor-pointer hover:bg-gray-100" 
                         onclick="FournisseursModule.selectFournisseur('${inputId}', '${suggestionsId}', '${f.nom}')">
                        ${f.nom}
                    </div>
                `).join('');
                suggestions.style.display = 'block';
            } else {
                suggestions.style.display = 'none';
            }
        });

        // Fermer les suggestions en cliquant ailleurs
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                suggestions.style.display = 'none';
            }
        });
    },

    selectFournisseur(inputId, suggestionsId, nom) {
        document.getElementById(inputId).value = nom;
        document.getElementById(suggestionsId).style.display = 'none';
    }
};

/**
 * Gestionnaire des modes de paiement
 */
const PaymentMethodsManager = {
    paymentMethods: [],

    async loadPaymentMethods() {
        try {
            const response = await fetch(CONFIG.API_URLS.PAYMENT_METHODS);
            const data = await response.json();

            if (data.success) {
                this.paymentMethods = data.payment_methods;
                console.log('✅ Modes de paiement chargés:', this.paymentMethods.length);
            }

        } catch (error) {
            console.error('❌ Erreur lors du chargement des modes de paiement:', error);
        }
    },

    populatePaymentSelect(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;

        select.innerHTML = '<option value="">Sélectionner un mode de paiement</option>';

        this.paymentMethods.forEach(method => {
            const option = document.createElement('option');
            option.value = method.id;
            option.textContent = method.nom;
            select.appendChild(option);
        });
    }
};

/**
 * Gestionnaire de substitution de matériaux
 */
const SubstitutionManager = {
    openSubstitution(materialId, designation, expressionId, sourceTable = 'expression_dym') {
        const modal = document.getElementById('substitution-modal');
        const originalProductInput = document.getElementById('original-product');
        const materialIdInput = document.getElementById('substitute-material-id');
        const expressionIdInput = document.getElementById('substitute-expression-id');
        const sourceTableInput = document.getElementById('substitute-source-table');

        if (!modal || !originalProductInput || !materialIdInput || !expressionIdInput || !sourceTableInput) {
            console.error('Éléments de substitution manquants');
            return;
        }

        // Remplir les champs
        originalProductInput.value = designation;
        materialIdInput.value = materialId;
        expressionIdInput.value = expressionId;
        sourceTableInput.value = sourceTable;

        // Afficher le modal
        modal.style.display = 'flex';

        // Configurer l'autocomplétion des produits
        this.setupProductAutocomplete();
    },

    setupProductAutocomplete() {
        const input = document.getElementById('substitute-product');
        const suggestions = document.getElementById('product-suggestions');

        if (!input || !suggestions) return;

        input.addEventListener('input', debounce(async (e) => {
            const value = e.target.value;

            if (value.length < 3) {
                suggestions.innerHTML = '';
                suggestions.style.display = 'none';
                return;
            }

            try {
                const response = await fetch(`${CONFIG.API_URLS.PRODUCT_SUGGESTIONS}?q=${encodeURIComponent(value)}`);
                const data = await response.json();

                if (data.success && data.products.length > 0) {
                    suggestions.innerHTML = data.products.map(product => `
                        <div class="suggestion-item p-2 cursor-pointer hover:bg-gray-100" 
                             onclick="SubstitutionManager.selectProduct('${product.designation}', '${product.id}')">
                            ${product.designation}
                        </div>
                    `).join('');
                    suggestions.style.display = 'block';
                } else {
                    suggestions.style.display = 'none';
                }

            } catch (error) {
                console.error('Erreur lors de la recherche de produits:', error);
            }
        }, 300));
    },

    selectProduct(designation, productId) {
        document.getElementById('substitute-product').value = designation;
        document.getElementById('substitute-product-id').value = productId;
        document.getElementById('product-suggestions').style.display = 'none';
    },

    close(modal) {
        if (modal) modal.style.display = 'none';
    }
};

// =====================================================
// FONCTIONS DE GÉNÉRATION DE CONTENU
// =====================================================

/**
 * Génération d'un bon de commande
 */
function generateBonCommande(expressionId) {
    if (!expressionId) {
        showNotification('ID d\'expression manquant', 'error');
        return;
    }

    Swal.fire({
        title: 'Génération du bon de commande',
        text: 'Voulez-vous générer un bon de commande pour cette expression ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, générer',
        cancelButtonText: 'Annuler'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch(CONFIG.API_URLS.BON_COMMANDE, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ expression_id: expressionId })
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        title: 'Bon de commande généré',
                        text: 'Le bon de commande a été généré avec succès.',
                        icon: 'success',
                        confirmButtonText: 'Télécharger',
                        showCancelButton: true,
                        cancelButtonText: 'Fermer'
                    }).then((downloadResult) => {
                        if (downloadResult.isConfirmed && data.file_path) {
                            window.open(data.file_path, '_blank');
                        }
                    });

                    // Rafraîchir les tables
                    refreshDataTables();
                } else {
                    showNotification(data.message || 'Erreur lors de la génération', 'error');
                }

            } catch (error) {
                console.error('Erreur lors de la génération du bon de commande:', error);
                showNotification('Erreur de connexion', 'error');
            }
        }
    });
}

/**
 * Visualisation des détails de commande
 */
async function viewOrderDetails(orderId, expressionId, designation, sourceTable = 'expression_dym') {
    try {
        Swal.fire({
            title: 'Chargement des détails...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        let apiUrl = `commandes-traitement/api.php?action=get_order_details&id=${orderId}`;
        if (sourceTable === 'besoins') {
            apiUrl = `commandes-traitement/besoins/get_besoin_details.php?id=${orderId}`;
        }

        const response = await fetch(apiUrl);
        const data = await response.json();

        if (data.success) {
            showOrderDetailsModal(data);
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
}

/**
 * Affichage de la modal avec les détails de la commande
 */
function showOrderDetailsModal(orderData) {
    const order = orderData.order;
    const materials = orderData.materials || [];
    const history = orderData.history || [];
    const sourceTable = orderData.source_table || 'expression_dym';

    // Calculer le total
    const total = materials.reduce((sum, item) => {
        return sum + (parseFloat(item.quantity || 0) * parseFloat(item.prix_unitaire || 0));
    }, 0);

    // Adapter l'affichage selon la source
    const isSystemRequest = sourceTable === 'besoins';
    const projectLabel = isSystemRequest ? 'Demande système' : 'Projet';
    const clientLabel = isSystemRequest ? 'Service demandeur' : 'Client';

    const htmlContent = `
        <div class="space-y-6">
            <!-- Badge de source -->
            <div class="flex justify-between items-center">
                <span class="px-3 py-1 text-xs font-medium rounded-full ${isSystemRequest ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                    ${isSystemRequest ? 'Demande Système' : 'Projet Client'}
                </span>
                <span class="text-sm text-gray-500">ID: ${order.id}</span>
            </div>
            
            <!-- Informations principales -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3">Informations générales</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">${projectLabel}</p>
                        <p class="font-medium">${order.code_projet || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">${clientLabel}</p>
                        <p class="font-medium">${order.nom_client || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Date de création</p>
                        <p class="font-medium">${new Date(order.created_at).toLocaleDateString('fr-FR')}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Statut</p>
                        <span class="px-2 py-1 text-xs rounded-full ${CONFIG.STATUS_CLASSES[order.valide_achat] || 'bg-gray-100 text-gray-800'}">
                            ${order.valide_achat}
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Détails du matériau -->
            <div class="bg-blue-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3">Détails du matériau</h3>
                <div class="space-y-2">
                    <p><span class="font-medium">Désignation:</span> ${order.designation}</p>
                    <p><span class="font-medium">Quantité:</span> ${order.qt_acheter} ${order.unit}</p>
                    <p><span class="font-medium">Prix unitaire:</span> ${order.prix_unitaire ? formatCurrency(order.prix_unitaire) : 'Non défini'}</p>
                    <p><span class="font-medium">Fournisseur:</span> ${order.fournisseur || 'Non défini'}</p>
                    <p><span class="font-medium">Total:</span> ${formatCurrency(total)}</p>
                </div>
            </div>
            
            ${history.length > 0 ? `
            <!-- Historique -->
            <div class="bg-yellow-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3">Historique</h3>
                <div class="space-y-2">
                    ${history.map(item => `
                        <div class="border-l-2 border-yellow-400 pl-3">
                            <p class="text-sm font-medium">${item.action}</p>
                            <p class="text-xs text-gray-600">${new Date(item.created_at).toLocaleString('fr-FR')}</p>
                            <p class="text-xs text-gray-600">${item.user_name || ''}</p>
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
                    <button onclick="generateBonCommande('${order.idExpression}')" 
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
                </div>
            </div>
        </div>
    `;

    Swal.fire({
        title: `Détails de la commande`,
        html: htmlContent,
        width: 800,
        showCloseButton: true,
        confirmButtonText: 'Fermer',
        customClass: {
            popup: 'text-left'
        }
    });
}

/**
 * Visualisation des détails du stock
 */
function viewStockDetails(designation) {
    if (!designation) {
        showNotification('Désignation du matériau manquante', 'error');
        return;
    }

    // Ouvrir la page de stock dans un nouvel onglet avec la recherche
    const stockUrl = `../stock/inventory.php?search=${encodeURIComponent(designation)}`;
    window.open(stockUrl, '_blank');
}

/**
 * Export Excel des commandes partielles
 */
function exportPartialOrdersExcel() {
    try {
        // Utiliser l'API d'export des DataTables
        if (partialOrdersTable) {
            partialOrdersTable.button('.buttons-excel').trigger();
        } else {
            // Fallback : export manuel
            window.open('export/partial_orders_excel.php', '_blank');
        }

        showNotification('Export Excel en cours...', 'info');

    } catch (error) {
        console.error('Erreur lors de l\'export Excel:', error);
        showNotification('Erreur lors de l\'export', 'error');
    }
}

/**
 * Téléchargement d'un bon de commande
 */
function downloadBonCommande(bonCommandeId, bonCommandePath) {
    if (!bonCommandeId && !bonCommandePath) {
        showNotification('Informations de bon de commande manquantes', 'error');
        return;
    }

    let downloadUrl = '';

    if (bonCommandePath) {
        // Construire l'URL à partir du chemin
        if (bonCommandePath.startsWith('purchase_orders/') || bonCommandePath.startsWith('gestion-bon-commande/')) {
            downloadUrl = `gestion-bon-commande/${bonCommandePath}`;
        } else {
            downloadUrl = `gestion-bon-commande/purchase_orders/${bonCommandePath}`;
        }
    } else if (bonCommandeId) {
        downloadUrl = `gestion-bon-commande/download.php?id=${bonCommandeId}`;
    }

    if (downloadUrl) {
        window.open(downloadUrl, '_blank');

        // Notification de succès
        Swal.fire({
            title: 'Téléchargement',
            text: 'Le bon de commande a été téléchargé et sauvegardé dans les archives.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        });
    } else {
        showNotification('Impossible de construire l\'URL de téléchargement', 'error');
    }
}

// =====================================================
// FONCTIONS GLOBALES EXPOSÉES
// =====================================================

// Exposition globale des fonctions pour utilisation dans le HTML
window.generateBonCommande = generateBonCommande;
window.viewOrderDetails = viewOrderDetails;
window.viewStockDetails = viewStockDetails;
window.downloadBonCommande = downloadBonCommande;
window.exportPartialOrdersExcel = exportPartialOrdersExcel;

// Gestionnaires
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

window.openEditOrderModal = (orderId, expressionId, designation, sourceTable, quantity, unit, price, supplier) => {
    EditOrderManager.openModal(orderId, expressionId, designation, sourceTable, quantity, unit, price, supplier);
};

window.closeEditOrderModal = () => {
    EditOrderManager.closeModal();
};

window.openSubstitution = (materialId, designation, expressionId, sourceTable = 'expression_dym') => {
    SubstitutionManager.openSubstitution(materialId, designation, expressionId, sourceTable);
};

// Gestionnaires de sélection et boutons
const SelectionManager = {
    updateSelection(type, checkbox) {
        // Mise à jour en fonction du type de sélection
        switch (type) {
            case 'pending':
                updateMaterialSelection();
                break;
            case 'ordered':
                updateOrderedMaterialSelection();
                break;
            case 'partial':
                updatePartialOrderSelection();
                break;
        }
    }
};

const ButtonStateManager = {
    updateAllButtons() {
        updateBulkActionButtons();
    },

    updateCancelButton() {
        const selectedOrdered = $('.ordered-material-checkbox:checked').length;
        $('#bulk-cancel-btn').prop('disabled', selectedOrdered === 0);
    }
};

// =====================================================
// GESTION AVANCÉE DES VALIDATIONS
// =====================================================

/**
 * Vérification du statut de validation des commandes
 */
async function checkOrderValidationStatus() {
    try {
        const response = await fetch('check_validation_status.php');
        const data = await response.json();

        if (data.success && data.hasChanges) {
            // Rafraîchir les tables si des changements sont détectés
            refreshDataTables();
            updateNotificationCounters();

            // Notification discrète de mise à jour
            if (data.updatedCount > 0) {
                showNotification(`${data.updatedCount} commande(s) mise(s) à jour`, 'info');
            }
        }

    } catch (error) {
        // Erreur silencieuse pour ne pas gêner l'utilisateur
        console.warn('⚠️ Impossible de vérifier le statut de validation:', error);
    }
}

/**
 * Validation en temps réel des champs de formulaire
 */
function setupRealTimeValidation() {
    // Validation des prix
    $(document).on('input', 'input[type="number"][name*="prix"]', function () {
        const value = parseFloat($(this).val());
        const $field = $(this);

        if (isNaN(value) || value < 0) {
            $field.addClass('is-invalid');
            $field.siblings('.invalid-feedback').text('Le prix doit être un nombre positif').show();
        } else {
            $field.removeClass('is-invalid').addClass('is-valid');
            $field.siblings('.invalid-feedback').hide();
        }
    });

    // Validation des quantités
    $(document).on('input', 'input[type="number"][name*="quantite"]', function () {
        const value = parseFloat($(this).val());
        const $field = $(this);

        if (isNaN(value) || value <= 0) {
            $field.addClass('is-invalid');
            $field.siblings('.invalid-feedback').text('La quantité doit être supérieure à 0').show();
        } else {
            $field.removeClass('is-invalid').addClass('is-valid');
            $field.siblings('.invalid-feedback').hide();
        }
    });

    // Validation des fournisseurs
    $(document).on('blur', 'input[name*="fournisseur"]', function () {
        const value = $(this).val().trim();
        const $field = $(this);

        if (value === '') {
            $field.addClass('is-invalid');
            $field.siblings('.invalid-feedback').text('Le fournisseur est obligatoire').show();
        } else {
            $field.removeClass('is-invalid').addClass('is-valid');
            $field.siblings('.invalid-feedback').hide();
        }
    });
}

// =====================================================
// AMÉLIORATION DU CHARGEMENT DES DONNÉES
// =====================================================

/**
 * Chargement intelligent des données avec mise en cache
 */
const DataManager = {
    cache: new Map(),
    cacheTimeout: 5 * 60 * 1000, // 5 minutes

    async getCachedData(key, fetchFunction) {
        const cached = this.cache.get(key);
        const now = Date.now();

        if (cached && (now - cached.timestamp) < this.cacheTimeout) {
            return cached.data;
        }

        try {
            const data = await fetchFunction();
            this.cache.set(key, {
                data: data,
                timestamp: now
            });
            return data;
        } catch (error) {
            console.error(`Erreur lors du chargement de ${key}:`, error);
            // Retourner les données en cache si disponibles, même expirées
            return cached ? cached.data : null;
        }
    },

    clearCache(key = null) {
        if (key) {
            this.cache.delete(key);
        } else {
            this.cache.clear();
        }
    }
};

function generateActionButtons(row) {
    const actions = [];

    // Bouton d'achat
    actions.push(`
        <button class="purchase-btn btn btn-sm btn-primary" 
                data-material-id="${row.id}" 
                data-expression-id="${row.idExpression}"
                title="Acheter ce matériau">
            <i class="fas fa-shopping-cart"></i>
        </button>
    `);

    // Bouton de substitution
    actions.push(`
        <button class="substitute-btn btn btn-sm btn-warning" 
                data-material-id="${row.id}" 
                data-designation="${row.designation}"
                data-expression-id="${row.idExpression}"
                title="Substituer ce matériau">
            <i class="fas fa-exchange-alt"></i>
        </button>
    `);

    // Bouton de visualisation du stock
    actions.push(`
        <button class="view-stock-btn btn btn-sm btn-info" 
                data-designation="${row.designation}"
                title="Voir dans le stock">
            <i class="fas fa-warehouse"></i>
        </button>
    `);

    return `<div class="btn-group" role="group">${actions.join('')}</div>`;
}

/**
 * Génération des boutons d'action pour les matériaux commandés
 */
function generateOrderedMaterialActions(row) {
    const actions = [];

    // Bouton de modification
    actions.push(`
        <button class="edit-order-btn btn btn-sm btn-primary" 
                data-order-id="${row.id}" 
                data-expression-id="${row.idExpression}"
                title="Modifier cette commande">
            <i class="fas fa-edit"></i>
        </button>
    `);

    // Bouton de détails
    actions.push(`
        <button class="view-details-btn btn btn-sm btn-info" 
                data-order-id="${row.id}"
                title="Voir les détails">
            <i class="fas fa-eye"></i>
        </button>
    `);

    // Bouton d'annulation
    actions.push(`
        <button class="cancel-order-btn btn btn-sm btn-danger" 
                data-order-id="${row.id}" 
                data-expression-id="${row.idExpression}"
                title="Annuler cette commande">
            <i class="fas fa-times"></i>
        </button>
    `);

    // Bouton de téléchargement de bon de commande (si disponible)
    if (row.bon_commande_id) {
        actions.push(`
            <button class="download-bon-commande-btn btn btn-sm btn-success" 
                    data-bon-commande-id="${row.bon_commande_id}"
                    title="Télécharger le bon de commande">
                <i class="fas fa-download"></i>
            </button>
        `);
    }

    return `<div class="btn-group" role="group">${actions.join('')}</div>`;
}

/**
 * Génération des boutons d'action pour les commandes partielles
 */
function generatePartialOrderActions(row) {
    const actions = [];

    // Bouton de complétion
    actions.push(`
        <button class="complete-partial-btn btn btn-sm btn-success" 
                data-order-id="${row.id}" 
                data-designation="${row.designation}"
                data-remaining="${row.qt_restante}"
                data-unit="${row.unit}"
                title="Compléter cette commande">
            <i class="fas fa-check"></i>
        </button>
    `);

    // Bouton de détails
    actions.push(`
        <button class="view-partial-details-btn btn btn-sm btn-info" 
                data-order-id="${row.id}"
                title="Voir les détails">
            <i class="fas fa-eye"></i>
        </button>
    `);

    return `<div class="btn-group" role="group">${actions.join('')}</div>`;
}

/**
 * Génération des boutons d'action pour les matériaux reçus
 */
function generateReceivedMaterialActions(row) {
    const actions = [];

    // Bouton de détails
    actions.push(`
        <button class="view-received-details-btn btn btn-sm btn-info" 
                data-order-id="${row.id}"
                title="Voir les détails">
            <i class="fas fa-eye"></i>
        </button>
    `);

    // Bouton de retour fournisseur (si applicable)
    actions.push(`
        <button class="return-to-supplier-btn btn btn-sm btn-warning" 
                data-order-id="${row.id}" 
                data-designation="${row.designation}"
                title="Retourner au fournisseur">
            <i class="fas fa-undo"></i>
        </button>
    `);

    return `<div class="btn-group" role="group">${actions.join('')}</div>`;
}

/**
 * Génération des boutons d'action pour les retours fournisseurs
 */
function generateSupplierReturnActions(row) {
    const actions = [];

    // Bouton de détails
    actions.push(`
        <button class="view-return-details-btn btn btn-sm btn-info" 
                data-return-id="${row.id}"
                title="Voir les détails du retour">
            <i class="fas fa-eye"></i>
        </button>
    `);

    // Si le retour est en attente, permettre de le compléter
    if (row.status === 'en_attente') {
        actions.push(`
            <button class="complete-return-btn btn btn-sm btn-success" 
                    data-return-id="${row.id}"
                    title="Marquer comme complété">
                <i class="fas fa-check"></i>
            </button>
        `);
    }

    return `<div class="btn-group" role="group">${actions.join('')}</div>`;
}

// =====================================================
// FONCTIONS DE GESTION DES MODALS
// =====================================================

/**
 * Ouverture du modal d'achat individuel
 */
function openPurchaseModal(materialData) {
    // Remplir les données du modal
    $('#expression_id').val(materialData.idExpression);
    $('#designation').val(materialData.designation);
    $('#quantite').val(materialData.qt_acheter);
    $('#unite').val(materialData.unit);

    // Afficher le modal
    $('#purchase-modal').show();

    // Focus sur le premier champ éditable
    $('#fournisseur').focus();
}

/**
 * Ouverture du modal d'achat en lot
 */
function openBulkPurchaseModal(selectedItems) {
    // Générer le contenu du modal avec les items sélectionnés
    const modalContent = generateBulkPurchaseContent(selectedItems);
    $('#bulk-purchase-content').html(modalContent);

    // Afficher le modal
    $('#bulk-purchase-modal').show();
}

/**
 * Ouverture du modal de modification de commande
 */
function openEditOrderModal(orderId, expressionId) {
    // Charger les données de la commande
    $.ajax({
        url: CONFIG.API_URLS.MATERIAL_INFO,
        type: 'GET',
        data: { order_id: orderId, expression_id: expressionId },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                populateEditOrderModal(response.order);
                $('#edit-order-modal').show();
            } else {
                showNotification('Erreur lors du chargement des données de la commande', 'error');
            }
        },
        error: function () {
            showNotification('Erreur de connexion lors du chargement', 'error');
        }
    });
}

/**
 * Peuplement du modal de modification de commande
 */
function populateEditOrderModal(orderData) {
    $('#edit-order-id').val(orderData.id);
    $('#edit-expression-id').val(orderData.idExpression);
    $('#edit-designation').val(orderData.designation);
    $('#edit-quantite').val(orderData.qt_acheter);
    $('#edit-unite').val(orderData.unit);
    $('#edit-prix-unitaire').val(orderData.prix_unitaire);
    $('#edit-fournisseur').val(orderData.fournisseur);
    $('#edit-mode-paiement').val(orderData.mode_paiement_id);
}

// =====================================================
// FONCTIONS DE TRAITEMENT DES FORMULAIRES
// =====================================================

/**
 * Traitement de l'achat individuel
 */
function handleIndividualPurchase(form) {
    const formData = new FormData(form);

    // Validation des données
    if (!validatePurchaseForm(formData)) {
        return;
    }

    // Affichage du loader
    showProcessingLoader('Traitement de l\'achat en cours...');

    $.ajax({
        url: CONFIG.API_URLS.PROCESS_PURCHASE,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            hideProcessingLoader();

            if (response.success) {
                showNotification(CONFIG.MESSAGES.SUCCESS.PURCHASE_COMPLETE, 'success');
                $('#purchase-modal').hide();
                refreshDataTables();
                updateNotificationCounters();
            } else {
                showNotification(response.message || CONFIG.MESSAGES.ERROR.GENERIC, 'error');
            }
        },
        error: function (xhr, status, error) {
            hideProcessingLoader();
            console.error('❌ Erreur lors de l\'achat:', error);
            showNotification(CONFIG.MESSAGES.ERROR.NETWORK, 'error');
        }
    });
}

/**
 * Traitement de l'achat en lot
 */
function handleBulkPurchase(form) {
    const formData = new FormData(form);
    const selectedItems = getSelectedMaterials();

    if (selectedItems.length === 0) {
        showNotification('Aucun matériau sélectionné', 'warning');
        return;
    }

    // Ajouter les items sélectionnés au FormData
    formData.append('selected_materials', JSON.stringify(selectedItems));

    showProcessingLoader('Traitement des achats en cours...');

    $.ajax({
        url: CONFIG.API_URLS.PROCESS_PURCHASE,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            hideProcessingLoader();

            if (response.success) {
                showNotification(CONFIG.MESSAGES.SUCCESS.BULK_ACTION, 'success');
                $('#bulk-purchase-modal').hide();
                refreshDataTables();
                updateNotificationCounters();
                clearSelection();
            } else {
                showNotification(response.message || CONFIG.MESSAGES.ERROR.GENERIC, 'error');
            }
        },
        error: function (xhr, status, error) {
            hideProcessingLoader();
            console.error('❌ Erreur lors de l\'achat en lot:', error);
            showNotification(CONFIG.MESSAGES.ERROR.NETWORK, 'error');
        }
    });
}

/**
 * Traitement de la modification de commande
 */
function handleOrderEdit(form) {
    const formData = new FormData(form);

    showProcessingLoader('Mise à jour de la commande...');

    $.ajax({
        url: CONFIG.API_URLS.UPDATE_ORDER_STATUS,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            hideProcessingLoader();

            if (response.success) {
                showNotification('Commande mise à jour avec succès', 'success');
                $('#edit-order-modal').hide();
                refreshDataTables();
            } else {
                showNotification(response.message || CONFIG.MESSAGES.ERROR.GENERIC, 'error');
            }
        },
        error: function (xhr, status, error) {
            hideProcessingLoader();
            console.error('❌ Erreur lors de la modification:', error);
            showNotification(CONFIG.MESSAGES.ERROR.NETWORK, 'error');
        }
    });
}

// =====================================================
// FONCTIONS DE VALIDATION
// =====================================================

/**
 * Validation du formulaire d'achat
 */
function validatePurchaseForm(formData) {
    const errors = [];

    // Validation du fournisseur
    const fournisseur = formData.get('fournisseur');
    if (!fournisseur || fournisseur.trim() === '') {
        errors.push('Le fournisseur est obligatoire');
    }

    // Validation de la quantité
    const quantite = parseFloat(formData.get('quantite'));
    if (!quantite || quantite <= 0) {
        errors.push('La quantité doit être supérieure à 0');
    }

    // Validation du prix unitaire
    const prixUnitaire = parseFloat(formData.get('prix_unitaire'));
    if (!prixUnitaire || prixUnitaire <= 0) {
        errors.push('Le prix unitaire doit être supérieur à 0');
    }

    // Affichage des erreurs
    if (errors.length > 0) {
        showNotification(errors.join('\n'), 'error');
        return false;
    }

    return true;
}

/**
 * Validation d'un champ individuel
 */
function validateField(field) {
    const $field = $(field);
    const value = $field.val().trim();
    const fieldType = $field.attr('type') || 'text';

    let isValid = true;
    let errorMessage = '';

    // Validation en fonction du type
    switch (fieldType) {
        case 'email':
            isValid = value === '' || isValidEmail(value);
            errorMessage = 'Adresse email invalide';
            break;
        case 'number':
            const numValue = parseFloat(value);
            isValid = value === '' || (!isNaN(numValue) && numValue >= 0);
            errorMessage = 'Valeur numérique invalide';
            break;
        case 'tel':
            isValid = value === '' || isValidPhoneNumber(value);
            errorMessage = 'Numéro de téléphone invalide';
            break;
        default:
            if ($field.hasClass('required-field')) {
                isValid = value !== '';
                errorMessage = 'Ce champ est obligatoire';
            }
    }

    // Mise à jour de l'interface
    if (isValid) {
        $field.removeClass('is-invalid').addClass('is-valid');
        $field.siblings('.invalid-feedback').hide();
    } else {
        $field.removeClass('is-valid').addClass('is-invalid');
        $field.siblings('.invalid-feedback').text(errorMessage).show();
    }

    return isValid;
}

// =====================================================
// FONCTIONS DE FILTRAGE
// =====================================================

/**
 * Filtrage par terme de recherche
 */
function filterMaterialsBySearch(searchTerm) {
    if (materialsTable) {
        materialsTable.search(searchTerm).draw();
    }

    currentFilters.search = searchTerm;
    updateActiveFiltersDisplay();
}

/**
 * Application des filtres de date
 */
function applyDateFilters() {
    const dateDebut = $('#dateDebut').val();
    const dateFin = $('#dateFin').val();

    if (dateDebut || dateFin) {
        // Appliquer le filtre de date aux tableaux
        if (materialsTable) {
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (settings.nTable.id !== 'materialsTable') return true;

                const date = new Date(data[9]); // Colonne de date
                const debut = dateDebut ? new Date(dateDebut) : null;
                const fin = dateFin ? new Date(dateFin) : null;

                return (!debut || date >= debut) && (!fin || date <= fin);
            });

            materialsTable.draw();
        }
    }

    currentFilters.date = { debut: dateDebut, fin: dateFin };
    updateActiveFiltersDisplay();
}

/**
 * Filtrage par client
 */
function filterMaterialsByClient(client) {
    if (materialsTable && client) {
        materialsTable.column(2).search(client).draw(); // Colonne client
    }

    currentFilters.client = client;
    updateActiveFiltersDisplay();
}

/**
 * Filtrage par fournisseur
 */
function filterMaterialsByFournisseur(fournisseur) {
    if (materialsTable && fournisseur) {
        materialsTable.column(8).search(fournisseur).draw(); // Colonne fournisseur
    }

    currentFilters.fournisseur = fournisseur;
    updateActiveFiltersDisplay();
}

/**
 * Filtrage par statut
 */
function filterMaterialsByStatus(status) {
    if (materialsTable && status) {
        materialsTable.column(6).search(status).draw(); // Colonne statut
    }

    currentFilters.status = status;
    updateActiveFiltersDisplay();
}

/**
 * Réinitialisation de tous les filtres
 */
function resetAllFilters() {
    // Réinitialiser les champs du formulaire
    $('#search-materials').val('');
    $('#dateDebut').val('');
    $('#dateFin').val('');
    $('#clientFilter').val('');
    $('#fournisseurFilter').val('');
    $('#statusFilter').val('');

    // Réinitialiser les filtres des tableaux
    if (materialsTable) {
        materialsTable.search('').columns().search('').draw();
    }

    if (orderedMaterialsTable) {
        orderedMaterialsTable.search('').columns().search('').draw();
    }

    // Supprimer les filtres personnalisés
    $.fn.dataTable.ext.search = [];

    // Réinitialiser les filtres en cours
    currentFilters = {};
    updateActiveFiltersDisplay();

    showNotification('Filtres réinitialisés', 'info');
}

/**
 * Application de tous les filtres
 */
function applyAllFilters() {
    const searchTerm = $('#search-materials').val();
    const client = $('#clientFilter').val();
    const fournisseur = $('#fournisseurFilter').val();
    const status = $('#statusFilter').val();

    // Appliquer tous les filtres
    if (searchTerm) filterMaterialsBySearch(searchTerm);
    if (client) filterMaterialsByClient(client);
    if (fournisseur) filterMaterialsByFournisseur(fournisseur);
    if (status) filterMaterialsByStatus(status);

    applyDateFilters();

    showNotification('Filtres appliqués', 'success');
}

/**
 * Mise à jour de l'affichage des filtres actifs
 */
function updateActiveFiltersDisplay() {
    const activeFilters = [];

    if (currentFilters.search) {
        activeFilters.push(`Recherche: "${currentFilters.search}"`);
    }

    if (currentFilters.client) {
        activeFilters.push(`Client: ${currentFilters.client}`);
    }

    if (currentFilters.fournisseur) {
        activeFilters.push(`Fournisseur: ${currentFilters.fournisseur}`);
    }

    if (currentFilters.status) {
        activeFilters.push(`Statut: ${currentFilters.status}`);
    }

    if (currentFilters.date && (currentFilters.date.debut || currentFilters.date.fin)) {
        let dateFilter = 'Période: ';
        if (currentFilters.date.debut) dateFilter += `du ${currentFilters.date.debut}`;
        if (currentFilters.date.fin) dateFilter += ` au ${currentFilters.date.fin}`;
        activeFilters.push(dateFilter);
    }

    const $activeFiltersContainer = $('#active-filters');
    if (activeFilters.length > 0) {
        $activeFiltersContainer.html(`
            <div class="alert alert-info">
                <strong>Filtres actifs:</strong> ${activeFilters.join(', ')}
                <button type="button" class="btn btn-sm btn-outline-secondary ml-2" onclick="resetAllFilters()">
                    Supprimer tous les filtres
                </button>
            </div>
        `).show();
    } else {
        $activeFiltersContainer.hide();
    }
}

// =====================================================
// FONCTIONS DE SÉLECTION
// =====================================================

/**
 * Récupération des matériaux sélectionnés
 */
function getSelectedMaterials() {
    const selected = [];
    $('.material-checkbox:checked').each(function () {
        const row = $(this).closest('tr');
        const materialData = getMaterialDataFromRow(row);
        materialData.id = $(this).val();
        materialData.idExpression = $(this).data('expression');
        selected.push(materialData);
    });
    return selected;
}

/**
 * Récupération des matériaux commandés sélectionnés
 */
function getSelectedOrderedMaterials() {
    const selected = [];
    $('.ordered-material-checkbox:checked').each(function () {
        const row = $(this).closest('tr');
        const materialData = getMaterialDataFromRow(row);
        materialData.id = $(this).val();
        materialData.idExpression = $(this).data('expression');
        selected.push(materialData);
    });
    return selected;
}

/**
 * Récupération des commandes partielles sélectionnées
 */
function getSelectedPartialOrders() {
    const selected = [];
    $('.partial-order-checkbox:checked').each(function () {
        const row = $(this).closest('tr');
        const orderData = getMaterialDataFromRow(row);
        orderData.id = $(this).val();
        orderData.idExpression = $(this).data('expression');
        selected.push(orderData);
    });
    return selected;
}

/**
 * Extraction des données d'un matériau depuis une ligne de tableau
 */
function getMaterialDataFromRow(row) {
    const cells = row.find('td');
    return {
        code_projet: $(cells[1]).text().trim(),
        nom_client: $(cells[2]).text().trim(),
        designation: $(cells[3]).text().trim(),
        qt_acheter: $(cells[4]).text().trim(),
        unit: $(cells[5]).text().trim(),
        valide_achat: $(cells[6]).find('span').text().trim(),
        prix_unitaire: $(cells[7]).text().replace(/[^0-9.,]/g, ''),
        fournisseur: $(cells[8]).text().trim(),
        created_at: $(cells[9]).text().trim()
    };
}

/**
 * Mise à jour de la sélection des matériaux
 */
function updateMaterialSelection() {
    const totalCheckboxes = $('.material-checkbox').length;
    const checkedCheckboxes = $('.material-checkbox:checked').length;

    // Mise à jour de la checkbox "Tout sélectionner"
    const $selectAll = $('#select-all-materials');
    if (checkedCheckboxes === 0) {
        $selectAll.prop('indeterminate', false).prop('checked', false);
    } else if (checkedCheckboxes === totalCheckboxes) {
        $selectAll.prop('indeterminate', false).prop('checked', true);
    } else {
        $selectAll.prop('indeterminate', true);
    }

    // Mise à jour du compteur de sélection
    $('#selected-count').text(checkedCheckboxes);
}

/**
 * Mise à jour de la sélection des matériaux commandés
 */
function updateOrderedMaterialSelection() {
    const totalCheckboxes = $('.ordered-material-checkbox').length;
    const checkedCheckboxes = $('.ordered-material-checkbox:checked').length;

    const $selectAll = $('#select-all-ordered-materials');
    if (checkedCheckboxes === 0) {
        $selectAll.prop('indeterminate', false).prop('checked', false);
    } else if (checkedCheckboxes === totalCheckboxes) {
        $selectAll.prop('indeterminate', false).prop('checked', true);
    } else {
        $selectAll.prop('indeterminate', true);
    }

    $('#selected-ordered-count').text(checkedCheckboxes);
}

/**
 * Mise à jour de la sélection des commandes partielles
 */
function updatePartialOrderSelection() {
    const checkedCheckboxes = $('.partial-order-checkbox:checked').length;
    $('#selected-partial-count').text(checkedCheckboxes);
}

/**
 * Effacement de toutes les sélections
 */
function clearSelection() {
    $('.material-checkbox, .ordered-material-checkbox, .partial-order-checkbox').prop('checked', false);
    $('#select-all-materials, #select-all-ordered-materials').prop('checked', false).prop('indeterminate', false);
    updateMaterialSelection();
    updateOrderedMaterialSelection();
    updatePartialOrderSelection();
    updateBulkActionButtons();
}

// =====================================================
// FONCTIONS DE MISE À JOUR DE L'INTERFACE
// =====================================================

/**
 * Mise à jour de l'état des boutons d'action en lot
 */
function updateBulkActionButtons() {
    const selectedMaterials = $('.material-checkbox:checked').length;
    const selectedOrdered = $('.ordered-material-checkbox:checked').length;
    const selectedPartial = $('.partial-order-checkbox:checked').length;

    // Boutons pour matériaux en attente
    $('#bulk-purchase-btn').prop('disabled', selectedMaterials === 0);

    // Boutons pour matériaux commandés
    $('#bulk-cancel-btn').prop('disabled', selectedOrdered === 0);

    // Boutons pour commandes partielles
    $('#bulk-complete-btn').prop('disabled', selectedPartial === 0);

    // Mise à jour des compteurs dans les boutons
    $('#bulk-purchase-btn .badge').text(selectedMaterials);
    $('#bulk-cancel-btn .badge').text(selectedOrdered);
    $('#bulk-complete-btn .badge').text(selectedPartial);
}

/**
 * Peuplement des sélecteurs de fournisseurs
 */
function populateFournisseurSelects() {
    const selects = ['#fournisseur', '#fournisseurFilter', '#edit-fournisseur', '#bulk-fournisseur'];

    selects.forEach(selector => {
        const $select = $(selector);
        if ($select.length) {
            $select.empty().append('<option value="">Sélectionner un fournisseur</option>');

            fournisseurs.forEach(fournisseur => {
                $select.append(`<option value="${fournisseur.nom}">${fournisseur.nom}</option>`);
            });
        }
    });
}

/**
 * Peuplement des sélecteurs de modes de paiement
 */
function populatePaymentMethodSelects(paymentMethods) {
    const selects = ['#mode-paiement', '#edit-mode-paiement', '#bulk-mode-paiement'];

    selects.forEach(selector => {
        const $select = $(selector);
        if ($select.length) {
            $select.empty().append('<option value="">Sélectionner un mode de paiement</option>');

            paymentMethods.forEach(method => {
                $select.append(`<option value="${method.id}">${method.nom}</option>`);
            });
        }
    });
}

/**
 * Mise à jour des compteurs de notifications
 */
function updateNotificationCounters() {
    $.ajax({
        url: 'notification_counts.php',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                // Mise à jour des badges de notification
                $('#materials-count').text(response.counts.materials || 0);
                $('#orders-count').text(response.counts.orders || 0);
                $('#partial-count').text(response.counts.partial || 0);
                $('#received-count').text(response.counts.received || 0);

                // Affichage ou masquage des badges selon les valeurs
                $('.notification-badge').each(function () {
                    const count = parseInt($(this).text());
                    $(this).toggle(count > 0);
                });
            }
        },
        error: function () {
            console.warn('⚠️ Impossible de mettre à jour les compteurs de notifications');
        }
    });
}

// =====================================================
// FONCTIONS UTILITAIRES
// =====================================================

/**
 * Affichage de la date courante
 */
function displayCurrentDate() {
    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };
    const today = new Date();
    const currentDateElement = document.getElementById('current-date');
    if (currentDateElement) {
        currentDateElement.textContent = today.toLocaleDateString('fr-FR', options);
    }
}

/**
 * Formatage de devise
 */
function formatCurrency(amount) {
    const number = parseFloat(amount);
    if (isNaN(number)) return 'N/A';

    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2
    }).format(number);
}

/**
 * Formatage de quantité
 */
function formatQuantity(quantity, unit = '') {
    const number = parseFloat(quantity);
    if (isNaN(number)) return 'N/A';

    const formatted = new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(number);

    return unit ? `${formatted} ${unit}` : formatted;
}

/**
 * Validation d'email
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Validation de numéro de téléphone français
 */
function isValidPhoneNumber(phone) {
    const phoneRegex = /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/;
    return phoneRegex.test(phone);
}

/**
 * Fonction de debounce pour optimiser les performances
 */
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

/**
 * Affichage de notifications
 */
function showNotification(message, type = 'info') {
    const notificationClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    }[type] || 'alert-info';

    const notification = $(`
        <div class="alert ${notificationClass} alert-dismissible fade show notification-toast" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `);

    $('#notifications-container').append(notification);

    // Auto-suppression après 5 secondes
    setTimeout(() => {
        notification.fadeOut(() => notification.remove());
    }, 5000);
}

/**
 * Affichage du loader de traitement
 */
function showProcessingLoader(message = 'Traitement en cours...') {
    const loader = $(`
        <div id="processing-loader" class="modal" style="display: block;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Chargement...</span>
                        </div>
                        <p class="mt-3">${message}</p>
                    </div>
                </div>
            </div>
        </div>
    `);

    $('body').append(loader);
}

/**
 * Masquage du loader de traitement
 */
function hideProcessingLoader() {
    $('#processing-loader').remove();
}

/**
 * Rafraîchissement de tous les DataTables
 */
function refreshDataTables() {
    if (materialsTable) materialsTable.ajax.reload(null, false);
    if (orderedMaterialsTable) orderedMaterialsTable.ajax.reload(null, false);
    if (partialOrdersTable) partialOrdersTable.ajax.reload(null, false);
    if (receivedMaterialsTable) receivedMaterialsTable.ajax.reload(null, false);
    if (supplierReturnsTable) supplierReturnsTable.ajax.reload(null, false);
}

// =====================================================
// GESTIONNAIRES MANQUANTS POUR MODALS ET FORMULAIRES
// =====================================================

/**
 * Gestionnaire d'extension pour BulkPurchaseManager avec soumission
 */
BulkPurchaseManager.handleBulkPurchase = async function (form) {
    const formData = new FormData(form);

    try {
        showProcessingLoader('Traitement des achats en cours...');

        const response = await fetch(CONFIG.API_URLS.PROCESS_PURCHASE, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        hideProcessingLoader();

        if (result.success) {
            showNotification('Achats en lot effectués avec succès', 'success');
            $('#bulk-purchase-modal').hide();
            refreshDataTables();
            updateNotificationCounters();
            clearSelection();
        } else {
            showNotification(result.message || 'Erreur lors de l\'achat en lot', 'error');
        }

    } catch (error) {
        hideProcessingLoader();
        console.error('Erreur lors de l\'achat en lot:', error);
        showNotification('Erreur de connexion', 'error');
    }
};

/**
 * Gestionnaire d'extension pour PartialOrdersManager avec soumission de complétion
 */
PartialOrdersManager.handleCompletionSubmit = async function (form) {
    const formData = new FormData(form);

    try {
        showProcessingLoader('Complétion de la commande...');

        const response = await fetch('api/orders/complete_partial_order.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        hideProcessingLoader();

        if (result.success) {
            showNotification('Commande complétée avec succès', 'success');
            $('#complete-partial-modal').hide();
            refreshDataTables();
            updateNotificationCounters();
        } else {
            showNotification(result.message || 'Erreur lors de la complétion', 'error');
        }

    } catch (error) {
        hideProcessingLoader();
        console.error('Erreur lors de la complétion:', error);
        showNotification('Erreur de connexion', 'error');
    }
};

/**
 * Gestionnaire d'extension pour SubstitutionManager avec soumission
 */
SubstitutionManager.handleSubstitution = async function (form) {
    const formData = new FormData(form);

    try {
        showProcessingLoader('Traitement de la substitution...');

        const response = await fetch(CONFIG.API_URLS.SUBSTITUTION, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        hideProcessingLoader();

        if (result.success) {
            showNotification('Substitution effectuée avec succès', 'success');
            $('#substitution-modal').hide();
            refreshDataTables();
            updateNotificationCounters();
        } else {
            showNotification(result.message || 'Erreur lors de la substitution', 'error');
        }

    } catch (error) {
        hideProcessingLoader();
        console.error('Erreur lors de la substitution:', error);
        showNotification('Erreur de connexion', 'error');
    }
};

/**
 * Extension du CancelManager avec annulation multiple des matériaux en attente
 */
CancelManager.cancelMultiplePending = async function () {
    const selectedMaterials = getSelectedMaterials();

    if (selectedMaterials.length === 0) {
        showNotification('Aucun matériau sélectionné', 'warning');
        return;
    }

    const result = await Swal.fire({
        title: 'Annulation multiple',
        text: `Êtes-vous sûr de vouloir annuler ${selectedMaterials.length} matériau(x) en attente ?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Oui, annuler tout',
        cancelButtonText: 'Non, garder'
    });

    if (result.isConfirmed) {
        try {
            showProcessingLoader('Annulation des matériaux...');

            const formData = new FormData();
            formData.append('materials', JSON.stringify(selectedMaterials));
            formData.append('action', 'cancel_multiple_pending');

            const response = await fetch(CONFIG.API_URLS.CANCEL_PENDING, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            hideProcessingLoader();

            if (data.success) {
                showNotification(`${selectedMaterials.length} matériau(x) annulé(s) avec succès`, 'success');
                refreshDataTables();
                updateNotificationCounters();
                clearSelection();
            } else {
                showNotification(data.message || 'Erreur lors de l\'annulation', 'error');
            }

        } catch (error) {
            hideProcessingLoader();
            console.error('Erreur lors de l\'annulation multiple:', error);
            showNotification('Erreur de connexion', 'error');
        }
    }
};

// =====================================================
// FONCTIONS UTILITAIRES MANQUANTES
// =====================================================

/**
 * Fonction pour vérifier les modifications non sauvegardées
 */
function hasUnsavedChanges() {
    // Vérifier si des formulaires ont été modifiés
    let hasChanges = false;

    $('form input, form select, form textarea').each(function () {
        const $element = $(this);
        const currentValue = $element.val();
        const initialValue = $element.data('initial-value');

        if (initialValue !== undefined && currentValue !== initialValue) {
            hasChanges = true;
            return false; // Sortir de la boucle
        }
    });

    return hasChanges;
}

/**
 * Configuration des gestionnaires de déchargement de page
 */
function setupPageUnloadHandlers() {
    // Nettoyage avant déchargement
    window.addEventListener('beforeunload', function (event) {
        // Arrêter les tâches automatiques
        stopAutomaticTasks();

        // Vérifier s'il y a des modifications non sauvegardées
        if (hasUnsavedChanges()) {
            const message = 'Vous avez des modifications non sauvegardées. Voulez-vous vraiment quitter ?';
            event.returnValue = message;
            return message;
        }
    });

    // Sauvegarde des données critiques avant fermeture
    window.addEventListener('pagehide', function () {
        // Sauvegarder les filtres en cours si nécessaire
        if (Object.keys(currentFilters).length > 0) {
            localStorage.setItem('achats_temp_filters', JSON.stringify(currentFilters));
        }
    });
}

/**
 * Fonction pour définir la quantité maximale
 */
function setMaxQuantity(inputId, maxValue) {
    const input = document.getElementById(inputId);
    if (input) {
        input.setAttribute('max', maxValue);
        input.setAttribute('title', `Quantité maximale: ${maxValue}`);

        // Validation en temps réel
        input.addEventListener('input', function () {
            const value = parseFloat(this.value);
            if (value > maxValue) {
                this.value = maxValue;
                showNotification(`Quantité limitée à ${maxValue}`, 'warning');
            }
        });
    }
}

/**
 * Fonction de formatage des dates avec gestion des fuseaux horaires
 */
function formatDateWithTime(dateString, includeSeconds = false) {
    if (!dateString) return 'N/A';

    try {
        const date = new Date(dateString);
        const options = {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Europe/Paris'
        };

        if (includeSeconds) {
            options.second = '2-digit';
        }

        return date.toLocaleString('fr-FR', options);
    } catch (error) {
        console.error('Erreur lors du formatage de la date:', error);
        return 'Date invalide';
    }
}

/**
 * Fonction de recherche intelligente dans les tables
 */
function setupIntelligentSearch() {
    // Recherche globale améliorée
    $('#search-materials').on('input', debounce(function () {
        const searchTerm = $(this).val().toLowerCase();

        if (searchTerm.length >= 2) {
            // Recherche dans toutes les colonnes pertinentes
            if (materialsTable) {
                materialsTable.search(searchTerm).draw();
            }

            // Mise en évidence des termes trouvés
            highlightSearchTerms(searchTerm);
        } else {
            // Effacer la recherche
            if (materialsTable) {
                materialsTable.search('').draw();
            }
            clearHighlights();
        }
    }, 300));
}

/**
 * Mise en évidence des termes de recherche
 */
function highlightSearchTerms(term) {
    $('.dataTables_wrapper tbody td').each(function () {
        const $td = $(this);
        const text = $td.text();

        if (text.toLowerCase().includes(term)) {
            const regex = new RegExp(`(${term})`, 'gi');
            const highlightedText = text.replace(regex, '<mark>$1</mark>');
            $td.html(highlightedText);
        }
    });
}

/**
 * Effacement des mises en évidence
 */
function clearHighlights() {
    $('.dataTables_wrapper tbody mark').each(function () {
        const $mark = $(this);
        $mark.replaceWith($mark.text());
    });
}

/**
 * Fonction de notification avec persistance
 */
function showPersistentNotification(message, type = 'info', persistent = false) {
    const notificationId = 'notification-' + Date.now();
    const notificationClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    }[type] || 'alert-info';

    const closeButton = persistent ?
        '<button type="button" class="close" data-dismiss="alert" aria-label="Fermer"><span aria-hidden="true">&times;</span></button>' :
        '';

    const notification = $(`
        <div id="${notificationId}" class="alert ${notificationClass} alert-dismissible fade show notification-toast" role="alert">
            ${message}
            ${closeButton}
        </div>
    `);

    $('#notifications-container').append(notification);

    // Auto-suppression si non persistant
    if (!persistent) {
        setTimeout(() => {
            notification.fadeOut(() => notification.remove());
        }, 5000);
    }

    return notificationId;
}

/**
 * Fonction de gestion des erreurs globales améliorée
 */
function setupGlobalErrorHandling() {
    // Gestionnaire d'erreurs AJAX amélioré
    $(document).ajaxError(function (event, xhr, settings, error) {
        console.error('❌ Erreur AJAX:', {
            url: settings.url,
            status: xhr.status,
            error: error,
            response: xhr.responseText
        });

        // Ne pas afficher d'erreur pour les requêtes de vérification automatique
        if (settings.url.includes('check_') || settings.url.includes('notification_counts')) {
            return;
        }

        let errorMessage = CONFIG.MESSAGES.ERROR.GENERIC;

        switch (xhr.status) {
            case 401:
                errorMessage = CONFIG.MESSAGES.ERROR.UNAUTHORIZED + ' Veuillez vous reconnecter';
                break;
            case 404:
                errorMessage = 'Ressource non trouvée';
                break;
            case 500:
                errorMessage = 'Erreur serveur interne';
                break;
            case 503:
                errorMessage = 'Service temporairement indisponible';
                break;
        }

        showNotification(errorMessage, 'error');
    });

    // Gestionnaire pour les erreurs JavaScript non capturées
    window.addEventListener('error', function (event) {
        console.error('❌ Erreur JavaScript:', event.error);

        // Logger l'erreur pour le débogage
        if (typeof event.error === 'object' && event.error.stack) {
            console.error('Stack trace:', event.error.stack);
        }
    });

    // Gestionnaire pour les promesses rejetées
    window.addEventListener('unhandledrejection', function (event) {
        console.error('❌ Promesse rejetée non gérée:', event.reason);
    });
}

// =====================================================
// FONCTIONS GLOBALES ADDITIONNELLES EXPOSÉES
// =====================================================

// Fonctions utilitaires exposées globalement
window.setMaxQuantity = setMaxQuantity;
window.formatDateWithTime = formatDateWithTime;
window.hasUnsavedChanges = hasUnsavedChanges;
window.showPersistentNotification = showPersistentNotification;

// Gestionnaires exposés globalement
window.SelectionManager = SelectionManager;
window.ButtonStateManager = ButtonStateManager;

// Fonction pour debug (utile en développement)
window.debugAchatsModule = function () {
    console.log('🔍 Debug du module Achats de Matériaux:');
    console.log('- Module initialisé:', achatsModuleInitialized);
    console.log('- Tables initialisées:', {
        materials: !!materialsTable,
        ordered: !!orderedMaterialsTable,
        partial: !!partialOrdersTable,
        received: !!receivedMaterialsTable,
        returns: !!supplierReturnsTable
    });
    console.log('- Fournisseurs chargés:', FournisseursModule.fournisseurs.length);
    console.log('- Modes de paiement chargés:', PaymentMethodsManager.paymentMethods.length);
    console.log('- Filtres actifs:', currentFilters);
    console.log('- Cache DataManager:', DataManager.getCacheStats());

    return {
        initialized: achatsModuleInitialized,
        tables: {
            materials: !!materialsTable,
            ordered: !!orderedMaterialsTable,
            partial: !!partialOrdersTable,
            received: !!receivedMaterialsTable,
            returns: !!supplierReturnsTable
        },
        data: {
            fournisseurs: FournisseursModule.fournisseurs.length,
            paymentMethods: PaymentMethodsManager.paymentMethods.length
        },
        filters: currentFilters,
        cache: DataManager.getCacheStats()
    };
};

/**
 * Configuration des tâches automatiques et intervalles
 */
function setupAutomaticTasks() {
    // Mise à jour de l'heure toutes les secondes
    refreshIntervals.datetime = setInterval(() => {
        displayCurrentDate();
    }, CONFIG.REFRESH_INTERVALS.DATETIME);

    // Vérification des nouveaux matériaux toutes les 5 minutes
    refreshIntervals.materials = setInterval(() => {
        updateNotificationCounters();
    }, CONFIG.REFRESH_INTERVALS.CHECK_MATERIALS);

    // Vérification des validations en attente toutes les 5 minutes
    refreshIntervals.validation = setInterval(() => {
        checkOrderValidationStatus();
    }, CONFIG.REFRESH_INTERVALS.CHECK_VALIDATION);
}

/**
 * Arrêt des tâches automatiques (mis à jour avec nouveau intervalle)
 */
function stopAutomaticTasks() {
    Object.values(refreshIntervals).forEach(interval => {
        if (interval) clearInterval(interval);
    });
    refreshIntervals = {
        datetime: null,
        materials: null,
        validation: null,
        statusCheck: null
    };
}

/**
 * API publique du module pour utilisation externe (mise à jour complète)
 */
window.AchatsMateriaux = {
    // Fonctions d'initialisation
    init: initializeApplication,
    reinit: function () {
        stopAutomaticTasks();
        DataManager.clearCache();
        initializeApplication();
    },

    // Gestion des tables
    refreshTables: refreshDataTables,
    getTables: function () {
        return {
            materials: materialsTable,
            ordered: orderedMaterialsTable,
            partial: partialOrdersTable,
            received: receivedMaterialsTable,
            returns: supplierReturnsTable
        };
    },

    // Gestion des sélections
    getSelectedMaterials: getSelectedMaterials,
    getSelectedOrdered: getSelectedOrderedMaterials,
    getSelectedPartial: getSelectedPartialOrders,
    clearSelection: clearSelection,

    // Gestionnaires spécialisés
    BulkPurchase: BulkPurchaseManager,
    PartialOrders: PartialOrdersManager,
    EditOrder: EditOrderManager,
    Cancel: CancelManager,
    Fournisseurs: FournisseursModule,
    PaymentMethods: PaymentMethodsManager,
    Substitution: SubstitutionManager,

    // Gestion des données
    DataManager: DataManager,

    // Gestion des filtres
    applyFilters: applyAllFilters,
    resetFilters: resetAllFilters,
    saveFilters: saveCurrentFilters,
    loadFilters: loadSavedFilters,

    // Gestion des notifications
    showNotification: showNotification,
    updateCounters: updateNotificationCounters,
    showLoader: showProcessingLoader,
    hideLoader: hideProcessingLoader,

    // Fonctions de génération et export
    generateBonCommande: generateBonCommande,
    viewOrderDetails: viewOrderDetails,
    viewStockDetails: viewStockDetails,
    exportToExcel: exportMaterialsToExcel,
    exportToPDF: exportMaterialsToPDF,
    exportPartialOrders: exportPartialOrdersExcel,

    // Utilitaires
    formatCurrency: formatCurrency,
    formatQuantity: formatQuantity,
    validateField: validateField,

    // Fonctions de vérification
    checkValidationStatus: checkOrderValidationStatus,
    checkStatusChanges: checkOrderStatusChanges,

    // État du module
    isInitialized: () => achatsModuleInitialized,
    getVersion: () => '2.1.0',

    // Configuration
    config: CONFIG,

    // Cache et performance
    clearCache: () => DataManager.clearCache(),
    getCacheStats: () => ({
        size: DataManager.cache.size,
        keys: Array.from(DataManager.cache.keys())
    })
};

/**
 * Vérification du statut de validation des commandes
 */
function checkOrderValidationStatus() {
    $.ajax({
        url: 'check_validation_status.php',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success && response.hasChanges) {
                // Rafraîchir les tables si des changements sont détectés
                refreshDataTables();
                updateNotificationCounters();
            }
        },
        error: function () {
            // Erreur silencieuse pour ne pas gêner l'utilisateur
            console.warn('⚠️ Impossible de vérifier le statut de validation');
        }
    });
}

// =====================================================
// GESTION DES ERREURS
// =====================================================

/**
 * Configuration des gestionnaires d'erreurs globaux
 */
function setupErrorHandlers() {
    // Gestionnaire d'erreurs AJAX global
    $(document).ajaxError(function (event, xhr, settings, error) {
        console.error('❌ Erreur AJAX:', {
            url: settings.url,
            status: xhr.status,
            error: error,
            response: xhr.responseText
        });

        // Ne pas afficher d'erreur pour les requêtes de vérification automatique
        if (settings.url.includes('check_') || settings.url.includes('notification_counts')) {
            return;
        }

        let errorMessage = CONFIG.MESSAGES.ERROR.GENERIC;

        switch (xhr.status) {
            case 401:
                errorMessage = CONFIG.MESSAGES.ERROR.UNAUTHORIZED;
                break;
            case 404:
                errorMessage = 'Ressource non trouvée';
                break;
            case 500:
                errorMessage = 'Erreur serveur interne';
                break;
            case 503:
                errorMessage = 'Service temporairement indisponible';
                break;
        }

        showNotification(errorMessage, 'error');
    });

    // Gestionnaire pour les erreurs JavaScript non capturées
    window.addEventListener('error', function (event) {
        console.error('❌ Erreur JavaScript:', event.error);

        // Logger l'erreur pour le débogage
        if (typeof event.error === 'object' && event.error.stack) {
            console.error('Stack trace:', event.error.stack);
        }
    });

    // Gestionnaire pour les promesses rejetées
    window.addEventListener('unhandledrejection', function (event) {
        console.error('❌ Promesse rejetée non gérée:', event.reason);
    });
}

// =====================================================
// INITIALISATION DES COMPOSANTS
// =====================================================

/**
 * Initialisation des tooltips
 */
function initializeTooltips() {
    if (typeof $().tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }
}

/**
 * Initialisation des sélecteurs multiples
 */
function initializeMultiSelect() {
    if (typeof $().select2 === 'function') {
        $('.multi-select').select2({
            placeholder: 'Sélectionner une ou plusieurs options',
            allowClear: true,
            language: 'fr'
        });
    }
}

/**
 * Initialisation des dropdowns
 */
function initializeDropdowns() {
    // Gestion des dropdowns personnalisés
    $('.dropdown-toggle').on('click', function (e) {
        e.preventDefault();
        $(this).siblings('.dropdown-menu').toggle();
    });

    // Fermeture des dropdowns en cliquant à l'extérieur
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.dropdown').length) {
            $('.dropdown-menu').hide();
        }
    });
}

/**
 * Configuration des filtres de table
 */
function setupTableFilters(api) {
    // Ajout de filtres dans l'en-tête si nécessaire
    api.columns().every(function () {
        const column = this;
        const header = $(column.header());

        if (header.hasClass('filterable')) {
            const select = $('<select class="form-control form-control-sm"><option value="">Tous</option></select>')
                .appendTo(header)
                .on('change', function () {
                    const val = $.fn.dataTable.util.escapeRegex($(this).val());
                    column.search(val ? '^' + val + '$' : '', true, false).draw();
                });

            column.data().unique().sort().each(function (d, j) {
                if (d) select.append('<option value="' + d + '">' + d + '</option>');
            });
        }
    });
}

// =====================================================
// FONCTIONS DE SAUVEGARDE DES FILTRES
// =====================================================

/**
 * Sauvegarde des filtres actuels
 */
function saveCurrentFilters(name) {
    if (!name || name.trim() === '') {
        showNotification('Veuillez spécifier un nom pour la configuration', 'warning');
        return;
    }

    const savedFilters = JSON.parse(localStorage.getItem('achats_saved_filters') || '{}');
    savedFilters[name] = currentFilters;

    localStorage.setItem('achats_saved_filters', JSON.stringify(savedFilters));
    updateSavedFiltersList();

    showNotification(`Configuration "${name}" sauvegardée`, 'success');
}

/**
 * Chargement des filtres sauvegardés
 */
function loadSavedFilters(name) {
    const savedFilters = JSON.parse(localStorage.getItem('achats_saved_filters') || '{}');

    if (savedFilters[name]) {
        currentFilters = savedFilters[name];
        applySavedFiltersToForm();
        refreshDataTables();
        updateActiveFiltersDisplay();

        showNotification(`Configuration "${name}" chargée`, 'success');
    } else {
        showNotification('Configuration non trouvée', 'error');
    }
}

/**
 * Application des filtres sauvegardés au formulaire
 */
function applySavedFiltersToForm() {
    // Réinitialiser d'abord
    resetAllFilters();

    // Appliquer les filtres sauvegardés
    if (currentFilters.search) {
        $('#search-materials').val(currentFilters.search);
    }

    if (currentFilters.date) {
        $('#dateDebut').val(currentFilters.date.debut);
        $('#dateFin').val(currentFilters.date.fin);
    }

    if (currentFilters.client) {
        $('#clientFilter').val(currentFilters.client);
    }

    if (currentFilters.fournisseur) {
        $('#fournisseurFilter').val(currentFilters.fournisseur);
    }

    if (currentFilters.status) {
        $('#statusFilter').val(currentFilters.status);
    }
}

/**
 * Mise à jour de la liste des filtres sauvegardés
 */
function updateSavedFiltersList() {
    const savedFilters = JSON.parse(localStorage.getItem('achats_saved_filters') || '{}');
    const filtersList = $('#savedFiltersList');

    if (Object.keys(savedFilters).length > 0) {
        let listHtml = '';
        Object.keys(savedFilters).forEach(name => {
            listHtml += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadSavedFilters('${name}')">
                        ${name}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSavedFilters('${name}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
        });
        filtersList.html(listHtml);
    } else {
        filtersList.html('<p class="text-muted">Aucune configuration sauvegardée</p>');
    }
}

/**
 * Suppression d'une configuration de filtres sauvegardée
 */
function deleteSavedFilters(name) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer la configuration "${name}" ?`)) {
        const savedFilters = JSON.parse(localStorage.getItem('achats_saved_filters') || '{}');
        delete savedFilters[name];

        localStorage.setItem('achats_saved_filters', JSON.stringify(savedFilters));
        updateSavedFiltersList();

        showNotification(`Configuration "${name}" supprimée`, 'success');
    }
}

// =====================================================
// API PUBLIQUE DU MODULE
// =====================================================

/**
 * API publique du module Achats de Matériaux
 * Exposée globalement pour permettre l'extension et l'intégration
 */
window.AchatsMateriaux = {
    // Fonctions d'initialisation
    init: initializeApplication,
    reinit: function () {
        stopAutomaticTasks();
        initializeApplication();
    },

    // Gestion des tables
    refreshTables: refreshDataTables,
    getTables: function () {
        return {
            materials: materialsTable,
            ordered: orderedMaterialsTable,
            partial: partialOrdersTable,
            received: receivedMaterialsTable,
            returns: supplierReturnsTable
        };
    },

    // Gestion des sélections
    getSelectedMaterials: getSelectedMaterials,
    getSelectedOrdered: getSelectedOrderedMaterials,
    getSelectedPartial: getSelectedPartialOrders,
    clearSelection: clearSelection,

    // Gestion des filtres
    applyFilters: applyAllFilters,
    resetFilters: resetAllFilters,
    saveFilters: saveCurrentFilters,
    loadFilters: loadSavedFilters,

    // Gestion des notifications
    showNotification: showNotification,
    updateCounters: updateNotificationCounters,

    // Utilitaires
    formatCurrency: formatCurrency,
    formatQuantity: formatQuantity,

    // État du module
    isInitialized: () => achatsModuleInitialized,
    getVersion: () => '2.1.0',

    // Configuration
    config: CONFIG
};

// =====================================================
// FINALISATION DE L'INITIALISATION
// =====================================================

/**
 * Finalisation de l'initialisation une fois que tout est chargé
 */
function finalizeInitialization() {
    // Marquer le module comme initialisé
    achatsModuleInitialized = true;

    // Exposer l'API globalement
    window.achatsModuleInitialized = true;

    // Log de confirmation
    console.log(`
╔══════════════════════════════════════════════════════════════╗
║                    ACHATS DE MATÉRIAUX                       ║
║                      VERSION 2.1.0                          ║
║                                                              ║
║  Module JavaScript corrigé et optimisé                      ║
║  DYM MANUFACTURE - Cohérence avec achats_materiaux.php      ║
║                                                              ║
║  ✅ DataTables avec configuration française                  ║
║  ✅ Système de filtrage et recherche avancé                 ║
║  ✅ Actions en lot et traitement des commandes              ║
║  ✅ Gestion des fournisseurs et modes de paiement           ║
║  ✅ Notifications et tâches automatiques                    ║
║  ✅ Gestion d'erreurs robuste                               ║
║  ✅ API publique pour extensions                            ║
║                                                              ║
║  Module prêt et opérationnel ! 🚀                          ║
╚══════════════════════════════════════════════════════════════╝
    `);
}

/**
 * ====================================================================
 * FIN DU MODULE ACHATS-MATERIAUX.JS - VERSION CORRIGÉE
 * ====================================================================
 * 
 * Ce fichier contient la logique complète du module de gestion des 
 * achats de matériaux pour DYM MANUFACTURE, corrigée pour être 
 * cohérente avec le fichier achats_materiaux.php.
 * 
 * Corrections apportées :
 * - Correspondance avec la structure HTML du fichier PHP
 * - Reprise de la logique de l'ancien script (script_complet.bak.txt)
 * - Optimisation de l'architecture et de la performance
 * - Gestion d'erreurs robuste
 * - API publique pour l'extensibilité
 * 
 * Utilisation :
 * 1. Inclure ce fichier après jQuery et DataTables
 * 2. L'initialisation se fait automatiquement au chargement
 * 3. Utiliser window.AchatsMateriaux pour l'API publique
 * 
 * Support : DYM MANUFACTURE - Équipe Développement
 * ====================================================================
 */