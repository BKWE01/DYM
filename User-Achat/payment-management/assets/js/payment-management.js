/**
 * GESTION DES MODES DE PAIEMENT - JAVASCRIPT
 * DYM MANUFACTURE - Service Achat
 * Version: 3.2 - Support upload d'icônes personnalisées
 * Date: 29/06/2025
 * Auteur: DYM Team
 */

'use strict';

/* ==========================================
   VARIABLES GLOBALES
========================================== */
let paymentsTable = null;
let isEditMode = false;
let currentEditId = null;
let selectedIconFile = null; // NOUVEAU : fichier d'icône sélectionné
let currentIconPath = null; // NOUVEAU : chemin de l'icône actuelle

/* ==========================================
   CONFIGURATION
========================================== */
const CONFIG = {
    // URLs des API
    API_URLS: {
        GET_PAYMENTS: 'api/get_payment_methods.php',
        ADD_PAYMENT: 'api/add_payment_method.php',
        UPDATE_PAYMENT: 'api/update_payment_method.php',
        TOGGLE_PAYMENT: 'api/toggle_payment_method.php',
        DELETE_PAYMENT: 'api/delete_payment_method.php',
        EXPORT_PAYMENTS: 'api/export_payment_methods.php'
    },
    
    // NOUVEAU : Configuration upload d'icônes
    ICON_UPLOAD: {
        max_size: 2 * 1024 * 1024, // 2MB
        allowed_types: ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp'],
        allowed_extensions: ['png', 'jpg', 'jpeg', 'svg', 'webp']
    },
    
    // Configuration DataTables
    DATATABLE_CONFIG: {
        responsive: true,
        processing: true,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
        },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tous"]],
        columnDefs: [
            { orderable: false, targets: [0, 7] }, // Icône et Actions non triables
            { searchable: false, targets: [0, 7] }, // Icône et Actions non recherchables
            { className: 'text-center', targets: [0, 3, 4, 5, 7] }, // Centrage pour certaines colonnes
            { width: '60px', targets: [0] }, // Largeur fixe pour l'icône
            { width: '120px', targets: [7] } // Largeur fixe pour les actions
        ],
        order: [[4, 'asc']], // Tri par ordre d'affichage par défaut
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    },
    
    // Configuration SweetAlert2
    SWAL_CONFIG: {
        confirmButtonColor: '#007bff',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Confirmer',
        cancelButtonText: 'Annuler'
    }
};

/* ==========================================
   INITIALISATION
========================================== */
$(document).ready(function() {
    console.log('🚀 Initialisation de la gestion des modes de paiement v3.2 (Upload d\'icônes)');
    
    // Initialiser la page
    initializePage();
    
    // Configurer les gestionnaires d'événements
    setupEventHandlers();
    
    // Charger les données
    loadPaymentMethods();
});

/**
 * Initialisation de la page
 */
function initializePage() {
    // Initialiser le tableau DataTables
    initializeDataTable();
    
    // NOUVEAU : Initialiser le système d'upload d'icônes
    initializeIconUpload();
    
    // Afficher la version dans la console
    console.log('📊 DYM MANUFACTURE - Gestion des Modes de Paiement v3.2 (Upload d\'icônes)');
}

/**
 * Initialisation du tableau DataTables
 */
function initializeDataTable() {
    if ($.fn.DataTable.isDataTable('#paymentsTable')) {
        $('#paymentsTable').DataTable().destroy();
    }
    
    paymentsTable = $('#paymentsTable').DataTable(CONFIG.DATATABLE_CONFIG);
    
    console.log('📋 Tableau DataTables initialisé avec support d\'icônes');
}

/**
 * NOUVEAU : Initialisation du système d'upload d'icônes
 */
function initializeIconUpload() {
    const uploadArea = document.getElementById('iconUploadArea');
    const fileInput = document.getElementById('icon_file');
    
    if (!uploadArea || !fileInput) return;
    
    // Gestion du drag & drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleIconFileSelection(files[0]);
        }
    });
    
    // Gestion du clic sur la zone d'upload
    uploadArea.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Gestion du changement de fichier
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            handleIconFileSelection(e.target.files[0]);
        }
    });
    
    console.log('📎 Système d\'upload d\'icônes initialisé');
}

/**
 * Configuration des gestionnaires d'événements
 */
function setupEventHandlers() {
    // Bouton d'ajout de nouveau mode de paiement
    $('#btnAddPayment').on('click', function() {
        openPaymentModal();
    });
    
    // Bouton d'export
    $('#btnExport').on('click', function() {
        exportPaymentMethods();
    });
    
    // Bouton de rafraîchissement
    $('#btnRefresh').on('click', function() {
        refreshData();
    });
    
    // Bouton de vérification des doublons
    $('#btnCheckDuplicates').on('click', function() {
        checkDuplicates();
    });
    
    // Bouton de sauvegarde dans le modal
    $('#btnSavePayment').on('click', function() {
        savePaymentMethod();
    });
    
    // NOUVEAU : Bouton de suppression d'icône
    $('#removeIconBtn').on('click', function() {
        removeCurrentIcon();
    });
    
    // Filtres
    $('#filterStatus').on('change', function() {
        filterByStatus($(this).val());
    });
    
    $('#searchPayments').on('keyup', debounce(function() {
        paymentsTable.search($(this).val()).draw();
    }, 300));
    
    // Gestion du modal
    $('#paymentModal').on('hidden.bs.modal', function() {
        resetModal();
    });
    
    // Validation en temps réel du formulaire
    $('#payment_label').on('input', function() {
        validateLabel($(this).val());
    });
    
    console.log('🎯 Gestionnaires d\'événements configurés (v3.2 avec upload)');
}

/* ==========================================
   GESTION DES ICÔNES
========================================== */

/**
 * NOUVEAU : Gestion des erreurs de chargement d'icônes
 */
function handleIconError(imgElement) {
    // Remplacer l'image par l'icône par défaut
    const parentDiv = imgElement.parentElement;
    parentDiv.innerHTML = '<span style="font-size: 1.2rem;">💳</span>';
    parentDiv.classList.add('icon-error');
    
    console.warn('❌ Erreur de chargement d\'icône:', imgElement.src);
}

/**
 * NOUVEAU : Gestion de la sélection de fichier d'icône
 */
function handleIconFileSelection(file) {
    // Valider le fichier
    const validation = validateIconFile(file);
    if (!validation.valid) {
        showError('Fichier invalide', validation.message);
        return;
    }
    
    selectedIconFile = file;
    
    // Afficher l'aperçu
    displayIconPreview(file);
    
    // Masquer l'aperçu de l'icône actuelle
    $('#currentIconPreview').hide();
    
    // Afficher le bouton de suppression
    $('#removeIconBtn').show().text('Supprimer la nouvelle icône');
}

/**
 * NOUVEAU : Validation d'un fichier d'icône
 */
function validateIconFile(file) {
    if (!file) {
        return { valid: false, message: 'Aucun fichier sélectionné' };
    }
    
    // Vérifier la taille
    if (file.size > CONFIG.ICON_UPLOAD.max_size) {
        return { 
            valid: false, 
            message: `Fichier trop volumineux (max ${formatFileSize(CONFIG.ICON_UPLOAD.max_size)})` 
        };
    }
    
    // Vérifier le type
    if (!CONFIG.ICON_UPLOAD.allowed_types.includes(file.type)) {
        return { 
            valid: false, 
            message: 'Type de fichier non autorisé. Utilisez PNG, JPG, SVG ou WebP' 
        };
    }
    
    // Vérifier l'extension
    const extension = file.name.split('.').pop().toLowerCase();
    if (!CONFIG.ICON_UPLOAD.allowed_extensions.includes(extension)) {
        return { 
            valid: false, 
            message: 'Extension de fichier non autorisée' 
        };
    }
    
    return { valid: true, message: 'Fichier valide' };
}

/**
 * NOUVEAU : Afficher l'aperçu de l'icône sélectionnée
 */
function displayIconPreview(file) {
    const reader = new FileReader();
    
    reader.onload = function(e) {
        const uploadArea = $('#iconUploadArea');
        const uploadContent = uploadArea.find('.upload-content');
        
        // Créer l'aperçu
        const previewHtml = `
            <div class="icon-preview-container">
                <img src="${e.target.result}" alt="Aperçu" class="icon-preview-image" style="max-width: 64px; max-height: 64px; object-fit: contain; border-radius: 4px;">
                <div class="mt-2">
                    <small class="text-success">
                        <i class="fas fa-check-circle"></i>
                        ${file.name} (${formatFileSize(file.size)})
                    </small>
                </div>
            </div>
        `;
        
        uploadContent.html(previewHtml);
    };
    
    reader.readAsDataURL(file);
}

/**
 * NOUVEAU : Supprimer l'icône actuelle
 */
function removeCurrentIcon() {
    selectedIconFile = null;
    currentIconPath = null;
    
    // Réinitialiser la zone d'upload
    resetIconUploadArea();
    
    // Masquer l'aperçu actuel
    $('#currentIconPreview').hide();
    
    // Masquer le bouton de suppression
    $('#removeIconBtn').hide();
    
    showNotification('Icône supprimée', 'info');
}

/**
 * NOUVEAU : Réinitialiser la zone d'upload d'icônes
 */
function resetIconUploadArea() {
    const uploadContent = $('#iconUploadArea .upload-content');
    const defaultContent = `
        <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-muted"></i>
        <p class="mb-1"><strong>Cliquez pour sélectionner</strong> ou glissez-déposez votre icône</p>
        <p class="text-muted small mb-0">PNG, JPG, SVG - Maximum 2MB - Recommandé: 64x64px</p>
    `;
    
    uploadContent.html(defaultContent);
    
    // Réinitialiser l'input file
    $('#icon_file').val('');
}

/**
 * NOUVEAU : Formater la taille de fichier
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

/* ==========================================
   GESTION DES DONNÉES
========================================== */

/**
 * Charger les modes de paiement
 */
function loadPaymentMethods() {
    showLoading(true);
    
    $.ajax({
        url: CONFIG.API_URLS.GET_PAYMENTS,
        type: 'GET',
        data: {
            include_usage: true,
            include_validation: true,
            include_icons: true // NOUVEAU : inclure les infos d'icônes
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                populateTable(response.data);
                showNotification('Données chargées avec succès', 'success');
            } else {
                showError('Erreur lors du chargement', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Erreur AJAX:', error);
            showError('Erreur de communication', 'Impossible de charger les données');
        },
        complete: function() {
            showLoading(false);
        }
    });
}

/**
 * Remplir le tableau avec les données - MISE À JOUR pour les icônes avec debug
 */
function populateTable(data) {
    paymentsTable.clear();
    
    if (data && data.length > 0) {
        data.forEach(function(payment) {
            // DEBUG : Afficher les informations d'icône dans la console
            if (payment.icon_path) {
                console.log('🖼️ Mode de paiement avec icône:', {
                    label: payment.label,
                    icon_path: payment.icon_path,
                    icon_info: payment.icon_info
                });
            }
            
            const row = [
                generateIconColumn(payment), // MISE À JOUR : gestion des icônes uploadées
                escapeHtml(payment.label),
                escapeHtml(payment.description || 'Aucune description'),
                generateStatusBadge(payment.is_active),
                payment.display_order || 1,
                generateUsageBadge(payment.usage_stats?.total_usage || 0),
                formatDate(payment.created_at),
                generateActionButtons(payment.id, payment.is_active)
            ];
            
            paymentsTable.row.add(row);
        });
    }
    
    paymentsTable.draw();
    console.log(`📊 ${data?.length || 0} modes de paiement chargés dans le tableau`);
}

/**
 * Rafraîchir les données
 */
function refreshData() {
    showNotification('Actualisation en cours...', 'info');
    loadPaymentMethods();
}

/* ==========================================
   GESTION DU MODAL
========================================== */

/**
 * Ouvrir le modal pour ajouter un nouveau mode
 */
function openPaymentModal(paymentData = null) {
    isEditMode = !!paymentData;
    currentEditId = paymentData?.id || null;
    
    // Titre du modal
    const title = isEditMode ? 
        '<i class="fas fa-edit me-2"></i>Modifier le Mode de Paiement' : 
        '<i class="fas fa-plus me-2"></i>Nouveau Mode de Paiement';
    
    $('#paymentModalTitle').html(title);
    
    // Bouton de sauvegarde
    $('#btnSavePayment').html(
        isEditMode ? 
        '<i class="fas fa-save me-1"></i>Mettre à jour' : 
        '<i class="fas fa-save me-1"></i>Enregistrer'
    );
    
    // Remplir le formulaire si en mode édition
    if (isEditMode && paymentData) {
        populateForm(paymentData);
    }
    
    // Afficher le modal
    $('#paymentModal').modal('show');
    
    // Focus sur le premier champ
    setTimeout(() => {
        $('#payment_label').focus();
    }, 500);
}

/**
 * Remplir le formulaire avec les données existantes - CORRECTION pour l'affichage d'icônes
 */
function populateForm(data) {
    $('#payment_id').val(data.id);
    $('#payment_label').val(data.label);
    $('#payment_description').val(data.description || '');
    $('#payment_order').val(data.display_order || 1);
    $('#payment_active').prop('checked', data.is_active == 1);
    
    // NOUVEAU : Gestion de l'icône actuelle - CORRECTION
    currentIconPath = data.icon_path;
    
    // Vérifier d'abord si icon_path existe directement
    if (data.icon_path && data.icon_path.trim() !== '') {
        // Construire l'URL de l'icône comme dans generateIconColumn
        const baseUrl = window.location.origin;
        const projectPath = window.location.pathname.split('/').slice(0, -1).join('/'); // Remonter d'1 niveau (on est déjà dans payment-management)
        const iconUrl = `${baseUrl}${projectPath}/../../${data.icon_path}`;
        
        console.log('🖼️ URL icône pour modal:', iconUrl);
        
        // Afficher l'icône actuelle
        $('#currentIconImage').attr('src', iconUrl);
        $('#currentIconImage').on('error', function() {
            console.warn('❌ Erreur de chargement de l\'icône dans le modal:', iconUrl);
            $('#currentIconPreview').hide();
        });
        $('#currentIconImage').on('load', function() {
            console.log('✅ Icône chargée avec succès dans le modal');
        });
        $('#currentIconPreview').show();
        $('#removeIconBtn').show().text('Supprimer l\'icône actuelle');
        
    } else if (data.icon_info && data.icon_info.has_custom_icon && data.icon_info.icon_exists) {
        // Fallback : utiliser l'URL de icon_info si disponible
        $('#currentIconImage').attr('src', data.icon_info.icon_url);
        $('#currentIconPreview').show();
        $('#removeIconBtn').show().text('Supprimer l\'icône actuelle');
        
    } else {
        // Pas d'icône personnalisée
        $('#currentIconPreview').hide();
        $('#removeIconBtn').hide();
    }
    
    // Réinitialiser la zone d'upload
    resetIconUploadArea();
    selectedIconFile = null;
}

/**
 * Réinitialiser le modal
 */
function resetModal() {
    $('#paymentForm')[0].reset();
    $('#payment_id').val('');
    isEditMode = false;
    currentEditId = null;
    selectedIconFile = null;
    currentIconPath = null;
    
    // Supprimer les classes de validation
    $('.form-control, .form-select').removeClass('is-valid is-invalid');
    $('.invalid-feedback, .valid-feedback').remove();
    
    // NOUVEAU : Réinitialiser la zone d'icônes
    resetIconUploadArea();
    $('#currentIconPreview').hide();
    $('#removeIconBtn').hide();
}

/* ==========================================
   SAUVEGARDE DES DONNÉES
========================================== */

/**
 * Sauvegarder un mode de paiement - MISE À JOUR pour l'upload
 */
function savePaymentMethod() {
    if (!validateForm()) {
        return;
    }
    
    const formData = collectFormData(); // NOUVEAU : FormData pour l'upload
    const url = isEditMode ? CONFIG.API_URLS.UPDATE_PAYMENT : CONFIG.API_URLS.ADD_PAYMENT;
    
    // Désactiver le bouton de sauvegarde
    $('#btnSavePayment').prop('disabled', true).html(
        '<i class="fas fa-spinner fa-spin me-1"></i>Enregistrement...'
    );
    
    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        processData: false, // NOUVEAU : ne pas traiter les données
        contentType: false, // NOUVEAU : laisser jQuery gérer le content-type
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#paymentModal').modal('hide');
                loadPaymentMethods();
                
                const message = isEditMode ? 
                    'Mode de paiement mis à jour avec succès' : 
                    'Mode de paiement ajouté avec succès';
                
                showNotification(message, 'success');
                
                // NOUVEAU : Afficher info sur l'icône uploadée
                if (response.upload_info && response.upload_info.icon_uploaded) {
                    showNotification('Icône uploadée avec succès', 'success');
                }
            } else {
                showError('Erreur de sauvegarde', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Erreur lors de la sauvegarde:', error);
            showError('Erreur de communication', 'Impossible de sauvegarder les données');
        },
        complete: function() {
            // Réactiver le bouton
            $('#btnSavePayment').prop('disabled', false).html(
                isEditMode ? 
                '<i class="fas fa-save me-1"></i>Mettre à jour' : 
                '<i class="fas fa-save me-1"></i>Enregistrer'
            );
        }
    });
}

/**
 * Collecter les données du formulaire - NOUVEAU : FormData pour l'upload
 */
function collectFormData() {
    const formData = new FormData();
    
    // Données de base
    formData.append('label', $('#payment_label').val().trim());
    formData.append('description', $('#payment_description').val().trim());
    formData.append('display_order', parseInt($('#payment_order').val()) || 1);
    formData.append('is_active', $('#payment_active').is(':checked') ? 1 : 0);
    
    if (isEditMode) {
        formData.append('id', parseInt($('#payment_id').val()));
        formData.append('current_icon_path', currentIconPath || '');
    }
    
    // NOUVEAU : Gestion de l'icône
    if (selectedIconFile) {
        formData.append('icon_file', selectedIconFile);
    } else if (currentIconPath === null && isEditMode) {
        // Suppression d'icône demandée
        formData.append('remove_icon', 'true');
    }
    
    return formData;
}

/* ==========================================
   ACTIONS SUR LES MODES DE PAIEMENT
========================================== */

/**
 * Modifier un mode de paiement
 */
function editPaymentMethod(id) {
    showLoading(true);
    
    $.ajax({
        url: CONFIG.API_URLS.GET_PAYMENTS,
        type: 'GET',
        data: { 
            id: id,
            include_icons: true // NOUVEAU : inclure les infos d'icônes
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data.length > 0) {
                openPaymentModal(response.data[0]);
            } else {
                showError('Mode de paiement introuvable', 'Impossible de charger les données');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Erreur lors du chargement:', error);
            showError('Erreur de communication', 'Impossible de charger les données');
        },
        complete: function() {
            showLoading(false);
        }
    });
}

/**
 * Basculer l'état actif/inactif
 */
function togglePaymentMethod(id, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const action = newStatus == 1 ? 'activer' : 'désactiver';
    
    Swal.fire({
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} ce mode de paiement ?`,
        text: `Êtes-vous sûr de vouloir ${action} ce mode de paiement ?`,
        icon: 'question',
        showCancelButton: true,
        ...CONFIG.SWAL_CONFIG
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: CONFIG.API_URLS.TOGGLE_PAYMENT,
                type: 'POST',
                data: JSON.stringify({
                    id: id,
                    is_active: newStatus
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadPaymentMethods();
                        
                        let message = `Mode de paiement ${action} avec succès`;
                        if (response.warning) {
                            message += ` (${response.warning})`;
                        }
                        
                        showNotification(message, 'success');
                        
                        // NOUVEAU : Afficher avertissement d'icône si nécessaire
                        if (response.data.icon_warning) {
                            showNotification(response.data.icon_warning, 'warning');
                        }
                    } else {
                        showError('Erreur lors de la modification', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Erreur lors du basculement:', error);
                    showError('Erreur de communication', 'Impossible de modifier le statut');
                }
            });
        }
    });
}

/**
 * Supprimer un mode de paiement
 */
function deletePaymentMethod(id) {
    Swal.fire({
        title: 'Supprimer ce mode de paiement ?',
        text: 'Cette action est irréversible ! L\'icône associée sera également supprimée.',
        icon: 'warning',
        showCancelButton: true,
        ...CONFIG.SWAL_CONFIG,
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: CONFIG.API_URLS.DELETE_PAYMENT,
                type: 'POST',
                data: JSON.stringify({ 
                    id: id,
                    cleanup_orphaned: true // NOUVEAU : nettoyer les icônes orphelines
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadPaymentMethods();
                        showNotification('Mode de paiement supprimé avec succès', 'success');
                        
                        // NOUVEAU : Afficher info sur la suppression d'icône
                        if (response.data.icon_deletion && response.data.icon_deletion.had_icon) {
                            if (response.data.icon_deletion.icon_deleted) {
                                showNotification('Icône associée supprimée', 'info');
                            } else {
                                showNotification('Attention: icône non supprimée', 'warning');
                            }
                        }
                    } else {
                        showError('Erreur lors de la suppression', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Erreur lors de la suppression:', error);
                    showError('Erreur de communication', 'Impossible de supprimer le mode');
                }
            });
        }
    });
}

/* ==========================================
   FILTRES ET RECHERCHE
========================================== */

/**
 * Filtrer par statut
 */
function filterByStatus(status) {
    if (status === 'all') {
        paymentsTable.column(3).search('').draw();
    } else {
        const searchTerm = status == 1 ? 'Actif' : 'Inactif';
        paymentsTable.column(3).search(searchTerm).draw();
    }
}

/* ==========================================
   EXPORT DES DONNÉES
========================================== */

/**
 * Exporter les modes de paiement - MISE À JOUR pour les icônes
 */
function exportPaymentMethods() {
    Swal.fire({
        title: 'Format d\'export',
        html: `
            <div class="mb-3">
                <label class="form-label">Format de fichier :</label>
                <select id="exportFormat" class="form-select">
                    <option value="csv">CSV</option>
                    <option value="json">JSON</option>
                </select>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="includeIcons" checked>
                    <label class="form-check-label" for="includeIcons">
                        Inclure les informations d'icônes
                    </label>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Niveau de détail des icônes :</label>
                <select id="iconDetails" class="form-select">
                    <option value="basic">Basique</option>
                    <option value="full">Complet</option>
                    <option value="urls_only">URLs uniquement</option>
                </select>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Exporter',
        cancelButtonText: 'Annuler',
        preConfirm: () => {
            const format = document.getElementById('exportFormat').value;
            const includeIcons = document.getElementById('includeIcons').checked;
            const iconDetails = document.getElementById('iconDetails').value;
            
            return { format, includeIcons, iconDetails };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { format, includeIcons, iconDetails } = result.value;
            downloadFile(format, includeIcons, iconDetails);
        }
    });
}

/**
 * Télécharger le fichier d'export - MISE À JOUR pour les icônes
 */
function downloadFile(format, includeIcons = false, iconDetails = 'basic') {
    let url = `${CONFIG.API_URLS.EXPORT_PAYMENTS}?format=${format}&include_usage=true`;
    
    if (includeIcons) {
        url += `&include_icons=true&icon_details=${iconDetails}`;
    }
    
    // Créer un lien invisible pour télécharger
    const link = document.createElement('a');
    link.href = url;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showNotification(`Export ${format.toUpperCase()} lancé${includeIcons ? ' (avec icônes)' : ''}`, 'success');
}

/* ==========================================
   VÉRIFICATION DES DOUBLONS
========================================== */

/**
 * Vérifier les doublons
 */
function checkDuplicates() {
    showLoading(true);
    
    $.ajax({
        url: CONFIG.API_URLS.GET_PAYMENTS,
        type: 'GET',
        data: { check_duplicates: true },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (response.duplicates && response.duplicates.length > 0) {
                    showDuplicatesAlert(response.duplicates);
                } else {
                    showNotification('Aucun doublon détecté', 'success');
                }
            } else {
                showError('Erreur lors de la vérification', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Erreur lors de la vérification:', error);
            showError('Erreur de communication', 'Impossible de vérifier les doublons');
        },
        complete: function() {
            showLoading(false);
        }
    });
}

/**
 * Afficher l'alerte des doublons
 */
function showDuplicatesAlert(duplicates) {
    let html = '<div class="alert alert-warning"><strong>Doublons détectés :</strong><ul>';
    
    duplicates.forEach(function(duplicate) {
        html += `<li>Libellé: "${duplicate.label}" (${duplicate.count} occurrences)</li>`;
    });
    
    html += '</ul></div>';
    
    Swal.fire({
        title: 'Doublons détectés',
        html: html,
        icon: 'warning',
        ...CONFIG.SWAL_CONFIG
    });
}

/* ==========================================
   VALIDATION DU FORMULAIRE
========================================== */

/**
 * Valider le formulaire complet
 */
function validateForm() {
    let isValid = true;
    
    // Réinitialiser les classes de validation
    $('.form-control, .form-select').removeClass('is-valid is-invalid');
    $('.invalid-feedback, .valid-feedback').remove();
    
    // Valider le libellé
    if (!validateLabel($('#payment_label').val())) {
        isValid = false;
    }
    
    // Valider l'ordre d'affichage
    if (!validateOrder($('#payment_order').val())) {
        isValid = false;
    }
    
    // NOUVEAU : Valider l'icône si un fichier est sélectionné
    if (selectedIconFile && !validateIconFile(selectedIconFile).valid) {
        isValid = false;
        showError('Icône invalide', validateIconFile(selectedIconFile).message);
    }
    
    return isValid;
}

/**
 * Valider le libellé
 */
function validateLabel(label) {
    const $field = $('#payment_label');
    const trimmedLabel = label.trim();
    
    if (!trimmedLabel) {
        setFieldError($field, 'Le libellé est obligatoire');
        return false;
    }
    
    if (trimmedLabel.length < 2 || trimmedLabel.length > 100) {
        setFieldError($field, 'Le libellé doit contenir entre 2 et 100 caractères');
        return false;
    }
    
    setFieldSuccess($field, 'Libellé valide');
    return true;
}

/**
 * Valider l'ordre d'affichage
 */
function validateOrder(order) {
    const $field = $('#payment_order');
    const numOrder = parseInt(order);
    
    if (isNaN(numOrder) || numOrder < 1 || numOrder > 999) {
        setFieldError($field, 'L\'ordre doit être un nombre entre 1 et 999');
        return false;
    }
    
    setFieldSuccess($field, 'Ordre valide');
    return true;
}

/**
 * Marquer un champ comme invalide
 */
function setFieldError($field, message) {
    $field.removeClass('is-valid').addClass('is-invalid');
    $field.next('.invalid-feedback').remove();
    $field.after(`<div class="invalid-feedback">${message}</div>`);
}

/**
 * Marquer un champ comme valide
 */
function setFieldSuccess($field, message = '') {
    $field.removeClass('is-invalid').addClass('is-valid');
    $field.next('.valid-feedback, .invalid-feedback').remove();
    if (message) {
        $field.after(`<div class="valid-feedback">${message}</div>`);
    }
}

/* ==========================================
   GÉNÉRATEURS HTML
========================================== */

/**
 * Générer la colonne icône - CORRECTION pour échappement des guillemets
 */
function generateIconColumn(payment) {
    // CORRECTION : Construire l'URL correctement si icon_path existe
    if (payment.icon_path && payment.icon_path.trim() !== '') {
        // Construire l'URL complète pour l'icône uploadée
        const baseUrl = window.location.origin;
        const projectPath = window.location.pathname.split('/').slice(0, -3).join('/'); // Remonter de 3 niveaux
        const iconUrl = `${baseUrl}${projectPath}/${payment.icon_path}`;
        
        return `<div class="payment-icon">
                    <img src="${iconUrl}" alt="Icône ${escapeHtml(payment.label)}" 
                         style="width: 32px; height: 32px; object-fit: contain; border-radius: 4px;"
                         onerror="handleIconError(this)">
                </div>`;
    } else if (payment.icon_info && payment.icon_info.has_custom_icon && payment.icon_info.icon_exists) {
        // Fallback : utiliser l'URL de icon_info si disponible
        return `<div class="payment-icon">
                    <img src="${payment.icon_info.icon_url}" alt="Icône ${escapeHtml(payment.label)}" 
                         style="width: 32px; height: 32px; object-fit: contain; border-radius: 4px;"
                         onerror="handleIconError(this)">
                </div>`;
    } else if (payment.icon_info && payment.icon_info.has_custom_icon && !payment.icon_info.icon_exists) {
        // Icône configurée mais manquante
        return `<div class="payment-icon icon-error" title="Icône manquante">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                </div>`;
    } else {
        // Icône par défaut
        return `<div class="payment-icon icon-default">
                    <span style="font-size: 1.2rem;">${payment.icon_info?.default_icon || '💳'}</span>
                </div>`;
    }
}

/**
 * Générer le badge de statut
 */
function generateStatusBadge(isActive) {
    if (isActive == 1) {
        return '<span class="status-badge status-active">Actif</span>';
    } else {
        return '<span class="status-badge status-inactive">Inactif</span>';
    }
}

/**
 * Générer le badge d'utilisation
 */
function generateUsageBadge(usage) {
    return `<span class="usage-badge">${usage} utilisation${usage > 1 ? 's' : ''}</span>`;
}

/**
 * Générer les boutons d'action
 */
function generateActionButtons(id, isActive) {
    const toggleIcon = isActive == 1 ? 'fas fa-pause' : 'fas fa-play';
    const toggleTitle = isActive == 1 ? 'Désactiver' : 'Activer';
    
    return `
        <div class="action-buttons">
            <button type="button" class="btn-action btn-edit" 
                    onclick="editPaymentMethod(${id})" 
                    title="Modifier">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="btn-action btn-toggle" 
                    onclick="togglePaymentMethod(${id}, ${isActive})" 
                    title="${toggleTitle}">
                <i class="${toggleIcon}"></i>
            </button>
            <button type="button" class="btn-action btn-delete" 
                    onclick="deletePaymentMethod(${id})" 
                    title="Supprimer">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
}

/* ==========================================
   UTILITAIRES
========================================== */

/**
 * Afficher/masquer l'indicateur de chargement
 */
function showLoading(show) {
    if (show) {
        $('body').addClass('loading');
    } else {
        $('body').removeClass('loading');
    }
}

/**
 * Afficher une notification
 */
function showNotification(message, type = 'info') {
    const icon = {
        'success': 'success',
        'error': 'error',
        'warning': 'warning',
        'info': 'info'
    }[type] || 'info';
    
    Swal.fire({
        title: message,
        icon: icon,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

/**
 * Afficher une erreur
 */
function showError(title, message) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'error',
        ...CONFIG.SWAL_CONFIG
    });
}

/**
 * Échapper les caractères HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Formater une date
 */
function formatDate(dateString) {
    if (!dateString) return 'Aucune date';
    
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Fonction de debounce pour limiter les appels fréquents
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

/* ==========================================
   GESTION DES ERREURS GLOBALES
========================================== */

// Gestion des erreurs AJAX globales
$(document).ajaxError(function(event, xhr, settings, thrownError) {
    console.error('❌ Erreur AJAX globale:', {
        url: settings.url,
        status: xhr.status,
        error: thrownError
    });
    
    // Ne pas afficher d'erreur si c'est un abort (annulation de requête)
    if (xhr.statusText !== 'abort') {
        showError('Erreur de communication', 'Une erreur est survenue lors de la communication avec le serveur');
    }
});

// Gestion des erreurs JavaScript globales
window.onerror = function(msg, url, lineNo, columnNo, error) {
    console.error('❌ Erreur JavaScript:', {
        message: msg,
        source: url,
        line: lineNo,
        column: columnNo,
        error: error
    });
    
    return false; // Ne pas empêcher la gestion d'erreur par défaut
};

/* ==========================================
   LOGS DE DÉBOGAGE
========================================== */
console.log('✅ JavaScript de gestion des modes de paiement chargé avec succès');
console.log('📱 Version: 3.2 - DYM MANUFACTURE (Upload d\'icônes)');
console.log('🔧 Configuration:', CONFIG);