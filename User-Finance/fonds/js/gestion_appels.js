// Mise à jour du fichier gestion_appels.js pour afficher le département

// Gestion des appels de fonds côté finance
$(document).ready(function() {
    // Initialisation du DataTable
    initializeDataTable();
    
    // Gestion des événements modaux
    setupModalEvents();
});

// Initialisation du DataTable
function initializeDataTable() {
    $('#appels-table').DataTable({
        responsive: true,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/French.json"
        },
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'desc']], // Tri par date de création
        columnDefs: [
            { orderable: false, targets: [6, 7] }, // Colonnes progression et actions non triables
            { className: 'text-center', targets: [5, 6] } // Centrer statut et progression
        ]
    });
}

// Configuration des événements des modales
function setupModalEvents() {
    // Fermeture des modales
    $('.close').on('click', function() {
        closeAllModals();
    });
    
    // Fermeture au clic en dehors
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('modal')) {
            closeAllModals();
        }
    });
    
    // Prévention de la fermeture au clic dans le contenu
    $('.modal-content').on('click', function(event) {
        event.stopPropagation();
    });
}

// Visualisation d'un appel de fonds
async function viewAppel(codeAppel) {
    try {
        showLoadingModal();
        
        const response = await fetch(`api_get_appel.php?code=${encodeURIComponent(codeAppel)}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message);
        }
        
        displayAppelDetails(data);
        $('#appel-modal').show();
        
    } catch (error) {
        console.error('Erreur:', error);
        Swal.fire({
            title: 'Erreur',
            text: 'Impossible de charger les détails de l\'appel de fonds',
            icon: 'error'
        });
    }
}

// Affichage des détails d'un appel
function displayAppelDetails(data) {
    const { appel, elements, justificatifs, historique, totaux } = data;
    
    // Header du modal
    $('.modal-header h3').text(`Appel de Fonds ${appel.code_appel}`);
    
    // Corps du modal
    $('#modal-body').html(`
        <div class="space-y-6">
            <!-- Informations générales -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="text-lg font-semibold mb-3 text-gray-700">Informations générales</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="mb-2">
                            <span class="font-medium text-gray-600">Demandeur:</span>
                            <span class="ml-2">${appel.demandeur_nom}</span>
                        </div>
                        <div class="mb-2">
                            <span class="font-medium text-gray-600">Email:</span>
                            <span class="ml-2">${appel.demandeur_email}</span>
                        </div>
                        <div class="mb-2">
                            <span class="font-medium text-gray-600">Entité:</span>
                            <span class="ml-2">${appel.entite || 'Non spécifié'}</span>
                        </div>
                        <div class="mb-2">
                            <span class="font-medium text-gray-600">Département:</span>
                            <span class="ml-2">${appel.departement || 'Non spécifié'}</span>
                        </div>
                    </div>
                    <div>
                        <div class="mb-2">
                            <span class="font-medium text-gray-600">Mode de paiement:</span>
                            <span class="ml-2">${appel.mode_paiement || 'Non spécifié'}</span>
                        </div>
                        <div class="mb-2">
                            <span class="font-medium text-gray-600">Date de création:</span>
                            <span class="ml-2">${appel.date_creation_formatted}</span>
                        </div>
                        <div class="mb-2">
                            <span class="font-medium text-gray-600">Statut:</span>
                            <span class="ml-2">
                                <span class="status-badge status-${appel.statut}">
                                    ${getStatusLabel(appel.statut)}
                                </span>
                            </span>
                        </div>
                        ${appel.date_validation ? `
                        <div class="mb-2">
                            <span class="font-medium text-gray-600">Date de validation:</span>
                            <span class="ml-2">${appel.date_validation_formatted}</span>
                        </div>
                        ` : ''}
                        ${appel.validateur_nom ? `
                        <div class="mb-2">
                            <span class="font-medium text-gray-600">Validé par:</span>
                            <span class="ml-2">${appel.validateur_nom}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div class="mt-4">
                    <span class="font-medium text-gray-600">Désignation:</span>
                    <p class="mt-1 text-gray-900">${appel.designation}</p>
                </div>
                
                ${appel.justification ? `
                <div class="mt-4">
                    <span class="font-medium text-gray-600">Justification:</span>
                    <p class="mt-1 text-gray-900">${appel.justification}</p>
                </div>
                ` : ''}
            </div>
            
            <!-- Résumé des montants -->
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <h4 class="text-lg font-semibold mb-3 text-blue-800">Résumé financier</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">${formatAmount(totaux.montant_total)}</div>
                        <div class="text-sm text-gray-600">Montant total</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">${formatAmount(totaux.montant_valide)}</div>
                        <div class="text-sm text-gray-600">Validé</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-600">${formatAmount(totaux.montant_rejete)}</div>
                        <div class="text-sm text-gray-600">Rejeté</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600">${formatAmount(totaux.montant_attente)}</div>
                        <div class="text-sm text-gray-600">En attente</div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span>Progression</span>
                        <span>${Math.round(totaux.progression)}%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${totaux.progression}%"></div>
                    </div>
                </div>
            </div>
            
            <!-- Éléments détaillés -->
            <div>
                <div class="flex justify-between items-center mb-3">
                    <h4 class="text-lg font-semibold text-gray-700">Éléments détaillés</h4>
                    ${appel.statut === 'en_attente' || appel.statut === 'partiellement_valide' ? `
                    <div class="space-x-2">
                        <button onclick="validateSelectedElements()" 
                                class="action-btn btn-validate" id="validate-selected-btn" disabled>
                            <span class="material-icons" style="font-size: 14px;">check</span>
                            Valider sélectionnés
                        </button>
                        <button onclick="rejectSelectedElements()" 
                                class="action-btn btn-reject" id="reject-selected-btn" disabled>
                            <span class="material-icons" style="font-size: 14px;">close</span>
                            Rejeter sélectionnés
                        </button>
                    </div>
                    ` : ''}
                </div>
                
                <div class="space-y-3" id="elements-container">
                    ${elements.map(element => `
                        <div class="element-card" data-element-id="${element.id}">
                            <div class="element-header">
                                <div class="flex items-center space-x-3">
                                    ${(element.statut === 'en_attente' && (appel.statut === 'en_attente' || appel.statut === 'partiellement_valide')) ? 
                                        `<input type="checkbox" class="element-checkbox" value="${element.id}">` : ''
                                    }
                                    <div>
                                        <h5 class="font-medium text-gray-900">${element.designation}</h5>
                                        <div class="text-sm text-gray-500">
                                            ${element.quantite} ${element.unite || ''} × ${formatAmount(element.prix_unitaire)} = ${formatAmount(element.montant_total)}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="status-badge status-${element.statut}">
                                        ${element.statut_libelle}
                                    </span>
                                    ${element.statut === 'en_attente' && (appel.statut === 'en_attente' || appel.statut === 'partiellement_valide') ? `
                                    <div class="element-actions">
                                        <button onclick="validateElement(${element.id})" 
                                                class="action-btn btn-validate text-xs">
                                            <span class="material-icons" style="font-size: 12px;">check</span>
                                        </button>
                                        <button onclick="rejectElement(${element.id})" 
                                                class="action-btn btn-reject text-xs">
                                            <span class="material-icons" style="font-size: 12px;">close</span>
                                        </button>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                            ${element.commentaire ? `
                            <div class="mt-2 p-2 bg-gray-100 rounded text-sm">
                                <span class="font-medium">Commentaire:</span> ${element.commentaire}
                            </div>
                            ` : ''}
                        </div>
                    `).join('')}
                </div>
            </div>
            
            <!-- Justificatifs -->
            ${justificatifs.length > 0 ? `
            <div>
                <h4 class="text-lg font-semibold mb-3 text-gray-700">Justificatifs</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    ${justificatifs.map(file => `
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg border">
                            <span class="material-icons text-gray-500 mr-3">insert_drive_file</span>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">${file.nom_fichier}</div>
                                <div class="text-sm text-gray-500">
                                    ${Math.round(file.taille_fichier / 1024)} KB
                                </div>
                            </div>
                            <a href="${file.chemin_fichier}" target="_blank" 
                               class="text-blue-600 hover:text-blue-800">
                                <span class="material-icons">download</span>
                            </a>
                        </div>
                    `).join('')}
                </div>
            </div>
            ` : ''}
            
            <!-- Historique -->
            <div>
                <h4 class="text-lg font-semibold mb-3 text-gray-700">Historique des actions</h4>
                <div class="space-y-2">
                    ${historique.map(hist => `
                        <div class="flex items-start p-3 bg-gray-50 rounded-lg">
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">${hist.description}</div>
                                <div class="text-sm text-gray-500">
                                    Par ${hist.user_nom} - ${hist.created_at_formatted}
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `);
    
    // Footer du modal avec boutons d'action
    const footerButtons = [];
    
    if (appel.statut === 'en_attente' || appel.statut === 'partiellement_valide') {
        footerButtons.push(`
            <button onclick="validateAllAppel('${appel.code_appel}')" 
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <span class="material-icons mr-1 align-middle" style="font-size: 18px;">check_circle</span>
                Valider tout
            </button>
        `);
        
        footerButtons.push(`
            <button onclick="showRejectModal('${appel.code_appel}')" 
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                <span class="material-icons mr-1 align-middle" style="font-size: 18px;">cancel</span>
                Rejeter tout
            </button>
        `);
    }
    
    // Bouton d'export Excel
    footerButtons.push(`
        <button onclick="exportAppelExcel('${appel.code_appel}')" 
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
            <span class="material-icons mr-1 align-middle" style="font-size: 18px;">grid_on</span>
            Export Excel
        </button>
    `);
    
    // Bouton d'export PDF
    footerButtons.push(`
        <button onclick="exportAppelPDF('${appel.code_appel}')" 
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            <span class="material-icons mr-1 align-middle" style="font-size: 18px;">picture_as_pdf</span>
            Export PDF
        </button>
    `);
    
    footerButtons.push(`
        <button onclick="closeAllModals()" 
                class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
            Fermer
        </button>
    `);
    
    $('#modal-footer').html(footerButtons.join(''));
    
    // Gestion des checkboxes
    $('.element-checkbox').on('change', function() {
        updateSelectedElementsButtons();
    });
    
    currentAppelData = data; // Stocker pour utilisation ultérieure
}

// Validation d'un élément individuel
async function validateElement(elementId) {
    const result = await Swal.fire({
        title: 'Confirmer la validation',
        text: 'Voulez-vous valider cet élément?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Oui, valider',
        cancelButtonText: 'Annuler',
        input: 'textarea',
        inputPlaceholder: 'Commentaire (optionnel)...',
        inputAttributes: {
            maxlength: 500
        }
    });
    
    if (result.isConfirmed) {
        await processValidation('validate_elements', [elementId], result.value || '');
    }
}

// Rejet d'un élément individuel
async function rejectElement(elementId) {
    const result = await Swal.fire({
        title: 'Rejeter l\'élément',
        text: 'Voulez-vous rejeter cet élément?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Oui, rejeter',
        cancelButtonText: 'Annuler',
        input: 'textarea',
        inputPlaceholder: 'Motif du rejet...',
        inputAttributes: {
            maxlength: 500
        },
        inputValidator: (value) => {
            if (!value) {
                return 'Veuillez indiquer un motif de rejet';
            }
        }
    });
    
    if (result.isConfirmed) {
        await processValidation('reject_elements', [elementId], result.value);
    }
}

// Validation des éléments sélectionnés
async function validateSelectedElements() {
    const selectedIds = $('.element-checkbox:checked').map(function() {
        return parseInt(this.value);
    }).get();
    
    if (selectedIds.length === 0) {
        Swal.fire('Attention', 'Veuillez sélectionner au moins un élément', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Valider les éléments sélectionnés',
        text: `Voulez-vous valider ${selectedIds.length} élément(s)?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Oui, valider',
        cancelButtonText: 'Annuler',
        input: 'textarea',
        inputPlaceholder: 'Commentaire (optionnel)...',
        inputAttributes: {
            maxlength: 500
        }
    });
    
    if (result.isConfirmed) {
        await processValidation('validate_elements', selectedIds, result.value || '');
    }
}

// Rejet des éléments sélectionnés
async function rejectSelectedElements() {
    const selectedIds = $('.element-checkbox:checked').map(function() {
        return parseInt(this.value);
    }).get();
    
    if (selectedIds.length === 0) {
        Swal.fire('Attention', 'Veuillez sélectionner au moins un élément', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Rejeter les éléments sélectionnés',
        text: `Voulez-vous rejeter ${selectedIds.length} élément(s)?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Oui, rejeter',
        cancelButtonText: 'Annuler',
        input: 'textarea',
        inputPlaceholder: 'Motif du rejet...',
        inputAttributes: {
            maxlength: 500
        },
        inputValidator: (value) => {
            if (!value) {
                return 'Veuillez indiquer un motif de rejet';
            }
        }
    });
    
    if (result.isConfirmed) {
        await processValidation('reject_elements', selectedIds, result.value);
    }
}

// Validation complète de l'appel
async function validateAllAppel(codeAppel) {
    const result = await Swal.fire({
        title: 'Valider tout l\'appel de fonds',
        text: 'Cette action validera tous les éléments en attente. Confirmer?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Oui, valider tout',
        cancelButtonText: 'Annuler',
        input: 'textarea',
        inputPlaceholder: 'Commentaire (optionnel)...',
        inputAttributes: {
            maxlength: 500
        }
    });
    
    if (result.isConfirmed) {
        await processValidation('validate_all', null, result.value || '', currentAppelData.appel.id);
    }
}

// Traitement de la validation/rejet
async function processValidation(action, elementIds, commentaire, appelId = null) {
    try {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('commentaire', commentaire);
        
        if (elementIds) {
            elementIds.forEach(id => formData.append('element_ids[]', id));
        }
        
        if (appelId) {
            formData.append('appel_id', appelId);
        } else if (currentAppelData) {
            formData.append('appel_id', currentAppelData.appel.id);
        }
        
        const response = await fetch('api_validate_reject.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                title: 'Succès',
                text: data.message,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Recharger les détails de l'appel
            if (currentAppelData) {
                setTimeout(() => {
                    viewAppel(currentAppelData.appel.code_appel);
                }, 1000);
            }
            
            // Recharger la page pour actualiser le tableau
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            throw new Error(data.message);
        }
        
    } catch (error) {
        console.error('Erreur:', error);
        Swal.fire({
            title: 'Erreur',
            text: error.message || 'Une erreur s\'est produite',
            icon: 'error'
        });
    }
}

// Affichage de la modal de rejet
function showRejectModal(codeAppel) {
    $('#reject-appel-id').val(codeAppel);
    $('#reject-modal').show();
}

// Fermeture de la modal de rejet
function closeRejectModal() {
    $('#reject-modal').hide();
    $('#reject-form')[0].reset();
}

// Confirmation du rejet complet
async function confirmReject() {
    const codeAppel = $('#reject-appel-id').val();
    const motif = $('#reject-reason').val();
    const commentaire = $('#reject-comment').val();
    
    if (!motif) {
        Swal.fire('Erreur', 'Veuillez sélectionner un motif de rejet', 'error');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'reject_all');
        formData.append('appel_id', currentAppelData.appel.id);
        formData.append('motif_rejet', motif);
        formData.append('commentaire', commentaire);
        
        const response = await fetch('api_validate_reject.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeRejectModal();
            closeAllModals();
            
            Swal.fire({
                title: 'Rejeté',
                text: 'L\'appel de fonds a été rejeté avec succès',
                icon: 'success'
            });
            
            // Recharger la page
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            throw new Error(data.message);
        }
        
    } catch (error) {
        console.error('Erreur:', error);
        Swal.fire({
            title: 'Erreur',
            text: error.message || 'Une erreur s\'est produite',
            icon: 'error'
        });
    }
}

// Mise à jour des boutons pour les éléments sélectionnés
function updateSelectedElementsButtons() {
    const selectedCount = $('.element-checkbox:checked').length;
    const validateBtn = $('#validate-selected-btn');
    const rejectBtn = $('#reject-selected-btn');
    
    if (selectedCount > 0) {
        validateBtn.prop('disabled', false).text(`Valider sélectionnés (${selectedCount})`);
        rejectBtn.prop('disabled', false).text(`Rejeter sélectionnés (${selectedCount})`);
    } else {
        validateBtn.prop('disabled', true).text('Valider sélectionnés');
        rejectBtn.prop('disabled', true).text('Rejeter sélectionnés');
    }
}

// Fermeture de toutes les modales
function closeAllModals() {
    $('.modal').hide();
    currentAppelData = null;
}

// Affichage de la modal de chargement
function showLoadingModal() {
    $('#modal-body').html(`
        <div class="text-center py-8">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">Chargement des détails...</p>
        </div>
    `);
    $('#modal-footer').html('');
    $('#appel-modal').show();
}

// Export PDF d'un appel de fonds
function exportAppelPDF(codeAppel) {
    window.open(`export_appel_pdf.php?code=${encodeURIComponent(codeAppel)}`, '_blank');
}

// Export Excel d'un appel de fonds (Nouveau)
function exportAppelExcel(codeAppel) {
    window.open(`export_appel_excel.php?code=${encodeURIComponent(codeAppel)}`, '_blank');
}

// Export des données
function exportData(format) {
    const filter = new URLSearchParams(window.location.search).get('filter') || 'all';
    window.open(`export_appels.php?format=${format}&filter=${filter}`, '_blank');
}

// Utilitaires
function formatAmount(amount) {
    return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
}

function getStatusLabel(status) {
    const labels = {
        'en_attente': 'En attente',
        'valide': 'Validé',
        'partiellement_valide': 'Partiellement validé',
        'rejete': 'Rejeté'
    };
    return labels[status] || status;
}

// Variable globale pour stocker les données de l'appel courant
let currentAppelData = null;