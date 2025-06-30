/**
 * GESTIONNAIRE DE RETOURS DE PROJETS
 * Gestion complète des retours de matériel et historique des mouvements
 * Version: 2.1 - Correction des erreurs de recherche
 * Auteur: DYM MANUFACTURE
 */

// ==========================================
// VARIABLES GLOBALES
// ==========================================
let projects = [];
let currentProject = null;
let selectedReturnType = 'unused';
let returnsSelected = [];
let historyTable = null;

// ==========================================
// INITIALISATION
// ==========================================
$(document).ready(function () {
    initializePage();
    setupEventHandlers();
});

/**
 * Initialisation de la page
 */
function initializePage() {
    // Afficher la date courante
    displayCurrentDate();

    // Charger les projets
    loadProjects();
}

/**
 * Configuration des gestionnaires d'événements
 */
function setupEventHandlers() {
    // Recherche de projet - CORRECTION: Meilleure gestion du contexte et des valeurs
    $('#project-search').on('input', function () {
        const element = $(this);
        const searchValue = element.val();

        // Vérification de sécurité pour éviter l'erreur toLowerCase
        const searchTerm = (searchValue && typeof searchValue === 'string') ? searchValue.toLowerCase() : '';

        // Utiliser debounce avec une fonction anonyme pour préserver le contexte
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(function () {
            filterProjects(searchTerm);
        }, 300);
    });

    // Gestionnaires pour les options de type de retour
    $('.return-type-option').on('click', handleReturnTypeChange);

    // Gestionnaire pour le bouton "Tout sélectionner"
    $('#select-all-btn').on('click', handleSelectAll);

    // Gestionnaires pour les boutons de soumission
    $('#submit-returns, #submit-from-summary').on('click', submitReturns);

    // Gestionnaire pour le bouton de rafraîchissement de l'historique
    $('#refresh-history').on('click', function () {
        if (currentProject) {
            loadProjectHistory(currentProject.idExpression);
        }
    });

    // Gestionnaire pour la case à cocher "Marquer ce projet comme terminé"
    $(document).on('change', '#project-completed-checkbox', handleProjectCompletedChange);

    // Gestionnaires pour les filtres d'historique
    $('#movement-type-filter, #period-filter').on('change', handleHistoryFilter);

    // Gestionnaire pour l'export de l'historique
    $('#export-history').on('click', handleHistoryExport);

    // Gestionnaire pour fermer le modal
    $(document).on('click', '#movement-details-modal', function (e) {
        if (e.target === this) {
            closeMovementModal();
        }
    });
}

// ==========================================
// GESTION DES PROJETS
// ==========================================

/**
 * Chargement de la liste des projets
 */
function loadProjects() {
    $.ajax({
        url: 'api_return/api_getProjectsList.php',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                projects = response.projects || [];
                displayProjects(projects);
            } else {
                displayProjectsError(response.message || 'Une erreur est survenue');
            }
        },
        error: function (xhr, status, error) {
            console.error('Erreur lors du chargement des projets:', error);
            displayProjectsError('Erreur lors du chargement des projets');
        }
    });
}

/**
 * Affichage de la liste des projets
 */
function displayProjects(projectsList) {
    const projectsContainer = $('#projects-list');
    projectsContainer.empty();

    if (!projectsList || projectsList.length === 0) {
        projectsContainer.html(getEmptyProjectsHTML());
        $('#project-count').text('0 projet');
        return;
    }

    $('#project-count').text(`${projectsList.length} projet${projectsList.length > 1 ? 's' : ''}`);

    projectsList.forEach((project, index) => {
        const card = createProjectCard(project, index);
        projectsContainer.append(card);
    });
}

/**
 * Création d'une carte de projet
 */
function createProjectCard(project, index) {
    // Vérifications de sécurité pour éviter les erreurs
    const nomClient = project.nom_client || 'Nom non spécifié';
    const codeProjet = project.code_projet || 'N/A';
    const idExpression = project.idExpression || '';
    const createdAt = project.created_at || '';

    const card = $(`
        <div class="project-card bg-white rounded-lg shadow-sm p-3 cursor-pointer slide-in" 
             data-id="${idExpression}" 
             style="animation-delay: ${index * 0.05}s">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-medium text-gray-800">${nomClient}</h3>
                    <div class="text-xs text-gray-500 mt-1">
                        <span class="font-medium text-indigo-600">${codeProjet}</span> - 
                        <span>ID: ${idExpression}</span>
                    </div>
                </div>
                <div class="text-xs text-gray-500">
                    ${formatDate(createdAt)}
                </div>
            </div>
        </div>
    `);

    card.on('click', function () {
        selectProject($(this), project);
    });

    return card;
}

/**
 * Sélection d'un projet
 */
function selectProject(cardElement, project) {
    // Retirer la classe active de tous les projets
    $('.project-card').removeClass('active');

    // Ajouter la classe active à ce projet
    cardElement.addClass('active');

    // Charger les détails du projet
    getProjectDetails(project.idExpression);
}

/**
 * Filtrage des projets lors de la recherche - CORRECTION: Meilleure gestion des erreurs
 */
function filterProjects(searchTerm) {
    // Vérification de sécurité
    if (!projects || !Array.isArray(projects)) {
        console.warn('Liste des projets non disponible pour le filtrage');
        return;
    }

    if (!searchTerm || searchTerm.trim() === '') {
        displayProjects(projects);
        return;
    }

    const filtered = projects.filter(project => {
        // Vérifications de sécurité pour chaque propriété
        const nomClient = (project.nom_client || '').toLowerCase();
        const codeProjet = (project.code_projet || '').toLowerCase();
        const idExpression = (project.idExpression || '').toLowerCase();
        const description = (project.description_projet || '').toLowerCase();

        return nomClient.includes(searchTerm) ||
            codeProjet.includes(searchTerm) ||
            idExpression.includes(searchTerm) ||
            description.includes(searchTerm);
    });

    displayProjects(filtered);
}

/**
 * Récupération des détails d'un projet
 */
function getProjectDetails(projectId) {
    if (!projectId) {
        handleProjectDetailsError('ID de projet invalide');
        return;
    }

    showProjectDetailsLoading();
    hideEmptyState();
    hideProductsSection();
    hideHistorySection();

    $.ajax({
        url: 'api_return/api_getProjectDetails.php',
        type: 'GET',
        data: { query: projectId },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                handleProjectDetailsSuccess(response);
            } else {
                handleProjectDetailsError(response.message);
            }
        },
        error: function (xhr, status, error) {
            console.error('Erreur lors de la récupération des détails:', error);
            handleProjectDetailsError('Erreur lors de la connexion au serveur');
        }
    });
}

/**
 * Gestion du succès de récupération des détails du projet
 */
function handleProjectDetailsSuccess(response) {
    currentProject = response.project;
    currentProject.reservedProducts = response.reservedProducts || [];

    // Afficher les détails du projet
    displayProjectDetails(currentProject);

    // Afficher l'historique
    showHistorySection();
    loadProjectHistory(currentProject.idExpression);

    if (response.project_completed) {
        handleCompletedProject();
    } else if (response.reservedProducts && response.reservedProducts.length > 0) {
        showProductsSection();
        displayReservedProducts(response.reservedProducts);
    } else {
        showNoProductsMessage();
    }
}

// ==========================================
// GESTION DES PRODUITS RÉSERVÉS
// ==========================================

/**
 * Affichage des produits réservés
 */
function displayReservedProducts(products) {
    const container = $('#reserved-products');
    container.empty();
    hideNoProductsMessage();

    // Vérification de sécurité
    if (!products || !Array.isArray(products)) {
        showNoProductsMessage();
        return;
    }

    // Filtrer les produits selon le type de retour sélectionné
    const displayProducts = filterProductsByReturnType(products);

    if (displayProducts.length === 0) {
        showNoProductsMessage();
        return;
    }

    // Réinitialiser les produits sélectionnés
    returnsSelected = [];
    updateSelectAllButton();

    // Afficher chaque produit
    displayProducts.forEach((product, index) => {
        const productCard = createProductCard(product, index);
        container.append(productCard);
        setupProductEventHandlers(productCard, product);
    });
}

/**
 * Filtrage des produits selon le type de retour
 */
function filterProductsByReturnType(products) {
    if (!products || !Array.isArray(products)) {
        return [];
    }

    if (selectedReturnType === 'unused') {
        return products.filter(product => {
            const reserved = parseFloat(product.quantity_reserved) || 0;
            const used = parseFloat(product.quantity_used) || 0;
            return (reserved - used) > 0;
        });
    } else {
        return products.filter(product => parseFloat(product.quantity_output) > 0);
    }
}

/**
 * Création d'une carte de produit
 */
function createProductCard(product, index) {
    const { maxReturn, quantityLabel } = calculateProductQuantities(product);
    const returnedQuantity = parseFloat(product.quantity_returned || 0).toFixed(2);

    // Vérifications de sécurité
    const designation = product.designation || 'Produit sans nom';
    const unit = product.unit || 'unité';
    const productId = product.product_id || '';
    const idExpression = product.idExpression || '';

    return $(`
        <div class="product-card bg-white border border-gray-200 rounded-lg p-4 slide-in" 
             style="animation-delay: ${index * 0.05}s">
            <div class="flex justify-between items-start mb-3">
                <div class="flex items-start">
                    <input type="checkbox" class="product-checkbox h-5 w-5 mt-1 mr-3 text-indigo-600 rounded border-gray-300"
                        data-product-id="${productId}"
                        data-expression-id="${idExpression}"
                        data-designation="${designation}">
                    <div>
                        <h4 class="font-medium text-gray-800">${designation}</h4>
                        <div class="flex items-center mt-1">
                            <span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full text-xs">
                                ${unit}
                            </span>
                            ${product.type ? `
                                <span class="bg-purple-50 text-purple-700 px-2 py-0.5 rounded-full text-xs ml-2">
                                    ${product.type}
                                </span>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-4 gap-3 mb-4">
                <div class="bg-gray-50 rounded p-2 text-center">
                    <div class="text-lg font-semibold text-gray-800">${parseFloat(product.quantity_reserved || 0).toFixed(2)}</div>
                    <div class="text-xs text-gray-500">Réservé</div>
                </div>
                <div class="bg-gray-50 rounded p-2 text-center">
                    <div class="text-lg font-semibold text-green-600">${returnedQuantity}</div>
                    <div class="text-xs text-gray-500">Retourné</div>
                </div>
                <div class="bg-gray-50 rounded p-2 text-center">
                    <div class="text-lg font-semibold text-red-800">${parseFloat(product.quantity_used || 0).toFixed(2)}</div>
                    <div class="text-xs text-gray-500">Utilisé</div>
                </div>
                <div class="bg-gray-50 rounded p-2 text-center">
                    <div class="text-lg font-semibold text-indigo-600">${maxReturn}</div>
                    <div class="text-xs text-gray-500">${quantityLabel}</div>
                </div>
            </div>
            
            <div class="border-t border-gray-100 pt-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">Quantité à retourner :</label>
                <div class="flex items-center">
                    <div class="quantity-controls">
                        <button type="button" class="qty-btn decrease-btn">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="return-quantity" min="0" max="${maxReturn}" value="0" step="0.01"
                            data-product-id="${productId}"
                            data-expression-id="${idExpression}"
                            data-designation="${designation}">
                        <button type="button" class="qty-btn increase-btn">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <button class="ml-2 px-2 py-1 bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 text-sm" 
                            onclick="setMaxQuantity(this, ${maxReturn})">
                        Max
                    </button>
                </div>
            </div>
        </div>
    `);
}

/**
 * Calcul des quantités pour un produit
 */
function calculateProductQuantities(product) {
    let maxReturn, quantityLabel;

    if (selectedReturnType === 'unused') {
        const reserved = parseFloat(product.quantity_reserved) || 0;
        const used = parseFloat(product.quantity_used) || 0;
        maxReturn = Math.max(0, reserved - used).toFixed(2);
        quantityLabel = 'non utilisée';
    } else {
        maxReturn = (parseFloat(product.quantity_output) || 0).toFixed(2);
        quantityLabel = 'à retourner';
    }

    return { maxReturn, quantityLabel };
}

// ==========================================
// GESTION DE L'HISTORIQUE DES MOUVEMENTS
// ==========================================

/**
 * Chargement de l'historique des mouvements du projet
 */
function loadProjectHistory(projectId) {
    if (!projectId) {
        showHistoryEmpty();
        return;
    }

    console.log('🔍 Chargement historique pour projet:', projectId);

    showHistoryLoading();
    hideHistoryEmpty();
    hideHistoryStats();
    destroyHistoryTable();

    $.ajax({
        url: 'api_return/api_getProjectHistory.php',
        type: 'GET',
        data: {
            project_id: projectId,
            include_user_details: true,
            include_stats: true,
            debug: 1 // Activer le mode debug
        },
        dataType: 'json',
        success: function (response) {
            hideHistoryLoading();

            console.log('📊 Réponse API historique:', response);

            if (response.success && response.movements && response.movements.length > 0) {
                console.log(`✅ ${response.movements.length} mouvement(s) trouvé(s)`);

                if (response.debug) {
                    console.log('🐛 Informations debug:', response.debug);
                }

                if (response.stats) {
                    displayHistoryStats(response.stats);
                    showHistoryStats();
                }

                displayEnhancedHistoryTable(response.movements);

                // Afficher les informations de zone si disponibles
                if (response.active_zone) {
                    console.log(`🌍 Zone active: ID ${response.active_zone.id}, ${response.active_zone.total_movements} mouvement(s)`);
                }
            } else {
                console.log('❌ Aucun mouvement trouvé ou erreur:', response.message);
                showHistoryEmpty();

                // Afficher un message d'aide si aucun mouvement n'est trouvé
                if (response.success && response.movements && response.movements.length === 0) {
                    $('#history-empty').find('p').html(`
                        Aucun mouvement trouvé pour ce projet.<br>
                        <small class="text-gray-400">
                            Vérifiez que le projet a des mouvements de stock associés dans votre zone.
                        </small>
                    `);
                }
            }
        },
        error: function (xhr, status, error) {
            console.error('❌ Erreur lors du chargement de l\'historique:', {
                status: status,
                error: error,
                response: xhr.responseText
            });

            hideHistoryLoading();
            showHistoryEmpty();
            $('#history-empty').find('p').html(`
                Erreur lors du chargement de l'historique<br>
                <small class="text-red-500">${error}</small>
            `);
        }
    });
}

/**
 * Affichage des statistiques de l'historique
 */
function displayHistoryStats(stats) {
    $('#stats-inputs').text(stats.inputs || 0).addClass('stat-animate');
    $('#stats-outputs').text(stats.outputs || 0).addClass('stat-animate');
    $('#stats-adjustments').text(stats.adjustments || 0).addClass('stat-animate');
    $('#stats-returns').text(stats.returns || 0).addClass('stat-animate');

    // ✅ Afficher info dispatches si présents (optionnel)
    if (stats.dispatches > 0) {
        // Ajouter une petite indication sous le compteur d'entrées
        const inputsText = `${stats.inputs} ${stats.dispatches > 0 ? '(dont ' + stats.dispatches + ' dispatch)' : ''}`;
        $('#stats-inputs').parent().find('.text-xs').text('Entrées' + (stats.dispatches > 0 ? ' (avec dispatch)' : ''));
    }

    setTimeout(() => $('.stat-animate').removeClass('stat-animate'), 300);
}

/**
 * Affichage du tableau d'historique amélioré
 */
function displayEnhancedHistoryTable(movements) {
    const historyTableBody = $('#history-table-body');
    historyTableBody.empty();
    destroyHistoryTable();

    if (!movements || !Array.isArray(movements)) {
        showHistoryEmpty();
        return;
    }

    const dataSet = movements.map(movement => createHistoryTableRow(movement));

    historyTable = $('#history-table').DataTable({
        data: dataSet,
        columns: getHistoryTableColumns(),
        language: {
            url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json",
            emptyTable: "Aucun mouvement trouvé pour ce projet",
            zeroRecords: "Aucun mouvement correspond aux critères de recherche"
        },
        responsive: true,
        order: [[0, 'desc']],
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50, 100],
        dom: 'Bfrtip',
        buttons: getHistoryTableButtons(),
        initComplete: function () {
            $('.dt-buttons').addClass('mb-3');
            $('.dt-button').addClass('px-3 py-1 text-sm rounded-md mr-2');
        }
    });

    setupHistoryTableFilters();
}

/**
 * Création d'une ligne du tableau d'historique
 */
function createHistoryTableRow(movement) {
    const formattedDate = formatDateWithTime(movement.date);
    const { typeBadge, quantityFormatted, userWithTooltip, actionButtons } = formatMovementData(movement);

    // CORRECTION: Utiliser la date formatée française pour le filtrage
    const typeForFilter = movement.display_type || movement.movement_type || '';

    return [
        formattedDate,  // Colonne 0 - Date formatée en français
        movement.product_name || 'Produit inconnu',
        typeBadge,
        quantityFormatted,
        movement.provenance || '-',
        movement.destination || '-',
        userWithTooltip,
        actionButtons,
        typeForFilter,   // Colonne 8 - Type Filter (cachée)
        formattedDate    // Colonne 9 - Date Filter utilise aussi le format français
    ];
}

/**
 * Formatage des données de mouvement
 */
function formatMovementData(movement) {
    // ✅ Utiliser display_type si disponible, sinon movement_type
    const typeForDisplay = movement.display_type || movement.movement_type;
    const { badgeClass, badgeIcon, typeText } = getMovementTypeData(typeForDisplay);

    const typeBadge = `<span class="movement-badge ${badgeClass}">
        <i class="${badgeIcon}"></i>
        ${typeText}
        ${movement.movement_type === 'dispatch' ? ' <small>(Dispatch)</small>' : ''}
    </span>`;

    // ✅ Les dispatches et entrées sont positives, les sorties négatives
    const isNegative = movement.movement_type === 'output';
    const quantityClass = isNegative ? 'text-red-600' : 'text-green-600';
    const quantityPrefix = isNegative ? '-' : '+';
    const quantityFormatted = `<span class="font-medium ${quantityClass}">
        ${quantityPrefix}${parseFloat(movement.quantity || 0).toFixed(2)}
    </span>`;

    const userInfo = movement.user_name || 'Système';
    const userWithTooltip = `<div class="tooltip">
        <span class="text-sm text-gray-600">${userInfo}</span>
        <span class="tooltiptext">
            Utilisateur: ${userInfo}<br>
            Type: ${movement.user_type || 'N/A'}<br>
            Date: ${formatDateWithTime(movement.date)}
        </span>
    </div>`;

    const actionButtons = `
        <button onclick="showMovementDetails(${movement.id})" 
                class="text-indigo-600 hover:text-indigo-900 text-sm">
            <i class="fas fa-eye"></i>
        </button>
        ${movement.notes ? `
            <button onclick="showMovementNotes('${encodeURIComponent(movement.notes)}')" 
                    class="ml-2 text-gray-600 hover:text-gray-900 text-sm">
                <i class="fas fa-sticky-note"></i>
            </button>
        ` : ''}
    `;

    return { typeBadge, quantityFormatted, userWithTooltip, actionButtons };
}

// ==========================================
// GESTIONNAIRES D'ÉVÉNEMENTS
// ==========================================

/**
 * Gestion du changement de type de retour
 */
function handleReturnTypeChange() {
    $('.return-type-option').removeClass('active bg-indigo-600 text-white');
    $(this).addClass('active bg-indigo-600 text-white');
    selectedReturnType = $(this).data('type');

    if (currentProject && currentProject.reservedProducts) {
        displayReservedProducts(currentProject.reservedProducts);
    }
}

/**
 * Gestion du bouton "Tout sélectionner"
 */
function handleSelectAll() {
    const checkboxes = $('.product-checkbox');
    if (checkboxes.length === 0) return;

    const anyUnchecked = checkboxes.toArray().some(checkbox => !checkbox.checked);

    checkboxes.prop('checked', anyUnchecked);

    checkboxes.each(function () {
        const checkbox = $(this);
        const row = checkbox.closest('.product-card');
        const quantityInput = row.find('.return-quantity');
        const maxQuantity = parseFloat(quantityInput.attr('max')) || 0;

        if (anyUnchecked && maxQuantity > 0) {
            quantityInput.val(maxQuantity.toFixed(2));
            updateReturnSummary(
                checkbox.data('product-id'),
                checkbox.data('designation'),
                maxQuantity
            );
        } else {
            quantityInput.val('0');
            removeFromReturnSummary(checkbox.data('product-id'));
        }
    });

    updateSelectAllButtonText(anyUnchecked);
}

/**
 * Gestion du changement de statut "projet terminé"
 */
function handleProjectCompletedChange() {
    if (this.checked) {
        selectAllProductsForCompletion();
        showToast("Tous les produits ont été sélectionnés pour le retour", "info");
    }
}

/**
 * Gestion des filtres d'historique
 */
function handleHistoryFilter() {
    if (!historyTable) {
        console.warn('Tableau d\'historique non disponible');
        return;
    }

    try {
        const typeFilter = $('#movement-type-filter').val();
        const periodFilter = $('#period-filter').val();

        console.log('Application des filtres - Type:', typeFilter, 'Période:', periodFilter);

        // Réinitialiser tous les filtres d'abord
        historyTable.column(8).search('');
        historyTable.column(9).search('');

        // Appliquer le filtre de type si sélectionné
        if (typeFilter && typeFilter !== '') {
            historyTable.column(8).search('^' + typeFilter + '$', true, false);
        }

        // Appliquer le filtre de période si sélectionné
        if (periodFilter && periodFilter !== '') {
            applyPeriodFilter(periodFilter);
        } else {
            // Si aucun filtre de période, juste redessiner le tableau
            historyTable.draw();
        }

    } catch (error) {
        console.error('Erreur lors de l\'application des filtres:', error);
        showToast('Erreur lors de l\'application des filtres', 'error');
    }
}



/**
 * Gestion de l'export de l'historique
 */
function handleHistoryExport() {
    if (currentProject && currentProject.idExpression) {
        window.open(`api_return/api_exportProjectHistory.php?project_id=${currentProject.idExpression}`, '_blank');
    }
}

// ==========================================
// FONCTIONS UTILITAIRES
// ==========================================

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
 * Formatage d'une date
 */
function formatDate(dateString, includeTime = false) {
    if (!dateString) return 'N/A';

    try {
        const date = new Date(dateString);
        const options = {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        };

        if (includeTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }

        return date.toLocaleDateString('fr-FR', options);
    } catch (error) {
        console.error('Erreur de formatage de date:', error);
        return 'N/A';
    }
}

/**
 * Formatage d'une date avec heure
 */
function formatDateWithTime(dateString) {
    if (!dateString) return 'N/A';

    try {
        const date = new Date(dateString);
        return date.toLocaleString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        console.error('Erreur de formatage de date avec heure:', error);
        return 'N/A';
    }
}

/**
 * Affichage d'un toast de notification
 */
function showToast(message, type = 'info') {
    if (typeof Swal !== 'undefined') {
        Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        }).fire({
            icon: type,
            title: message
        });
    } else {
        console.log(`Toast ${type}: ${message}`);
    }
}

// ==========================================
// GESTION DES ÉTATS D'AFFICHAGE
// ==========================================

function showProjectDetailsLoading() {
    $('#project-details').removeClass('hidden').html(`
        <div class="text-center py-4">
            <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-indigo-500 border-r-transparent"></div>
            <p class="text-gray-500 mt-2">Chargement des détails du projet...</p>
        </div>
    `);
}

function hideEmptyState() { $('#empty-state').addClass('hidden'); }
function hideProductsSection() { $('#reserved-products-section').addClass('hidden'); }
function hideHistorySection() { $('#history-section').addClass('hidden'); }
function showHistorySection() { $('#history-section').removeClass('hidden'); }
function showProductsSection() { $('#reserved-products-section').removeClass('hidden'); }
function hideNoProductsMessage() { $('#no-products-message').addClass('hidden'); }
function showNoProductsMessage() { $('#no-products-message').removeClass('hidden'); }
function showHistoryLoading() { $('#history-loading').removeClass('hidden'); }
function hideHistoryLoading() { $('#history-loading').addClass('hidden'); }
function showHistoryEmpty() { $('#history-empty').removeClass('hidden'); }
function hideHistoryEmpty() { $('#history-empty').addClass('hidden'); }
function showHistoryStats() { $('#history-stats').removeClass('hidden'); }
function hideHistoryStats() { $('#history-stats').addClass('hidden'); }

function destroyHistoryTable() {
    if ($.fn.DataTable.isDataTable('#history-table')) {
        $('#history-table').DataTable().destroy();
    }
    $('#history-table-body').empty();
}

// ==========================================
// FONCTIONS D'EXPORT ET CONFIGURATION
// ==========================================

/**
 * Données de configuration pour les types de mouvement
 */
function getMovementTypeData(type) {
    const types = {
        'input': {
            badgeClass: 'badge-input',
            badgeIcon: 'fas fa-arrow-down',
            typeText: 'Entrée'
        },
        'entry': {
            badgeClass: 'badge-input',
            badgeIcon: 'fas fa-arrow-down',
            typeText: 'Entrée'
        },
        'dispatch': {
            // ✅ DISPATCH = ENTRÉE
            badgeClass: 'badge-input',
            badgeIcon: 'fas fa-arrow-down',
            typeText: 'Entrée'
        },
        'output': {
            badgeClass: 'badge-output',
            badgeIcon: 'fas fa-arrow-up',
            typeText: 'Sortie'
        },
        'adjustment': {
            badgeClass: 'badge-adjustment',
            badgeIcon: 'fas fa-cog',
            typeText: 'Ajustement'
        },
        'return': {
            badgeClass: 'badge-return',
            badgeIcon: 'fas fa-undo',
            typeText: 'Retour'
        }
    };

    return types[type] || {
        badgeClass: 'bg-gray-100 text-gray-800',
        badgeIcon: 'fas fa-question',
        typeText: type
    };
}

/**
 * Configuration des colonnes du tableau d'historique
 */
function getHistoryTableColumns() {
    return [
        { title: "Date & Heure", width: "15%" },
        { title: "Produit", width: "20%" },
        { title: "Type", width: "12%" },
        { title: "Quantité", width: "10%" },
        { title: "Source", width: "15%" },
        { title: "Destination", width: "15%" },
        { title: "Utilisateur", width: "10%" },
        { title: "Actions", width: "8%", orderable: false },
        { title: "Type Filter", visible: false, searchable: false },
        { title: "Date Filter", visible: false, searchable: false }
    ];
}

/**
 * Configuration des boutons du tableau d'historique
 */
function getHistoryTableButtons() {
    return [
        {
            extend: 'excel',
            text: '<i class="fas fa-file-excel mr-1"></i>Excel',
            className: 'btn btn-success btn-sm',
            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
        },
        {
            extend: 'pdf',
            text: '<i class="fas fa-file-pdf mr-1"></i>PDF',
            className: 'btn btn-danger btn-sm',
            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
        },
        {
            extend: 'print',
            text: '<i class="fas fa-print mr-1"></i>Imprimer',
            className: 'btn btn-info btn-sm',
            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
        }
    ];
}

// ==========================================
// GESTION DES QUANTITÉS ET VALIDATION
// ==========================================

/**
 * Configuration des gestionnaires d'événements pour un produit
 */
function setupProductEventHandlers(productCard, product) {
    const checkbox = productCard.find('.product-checkbox');
    const quantityInput = productCard.find('.return-quantity');
    const decreaseBtn = productCard.find('.decrease-btn');
    const increaseBtn = productCard.find('.increase-btn');
    const maxQuantity = parseFloat(quantityInput.attr('max')) || 0;

    // Gestionnaire pour le checkbox
    checkbox.on('change', function () {
        handleProductCheckboxChange(this, quantityInput, maxQuantity, product);
    });

    // Gestionnaire pour l'input de quantité
    quantityInput.on('input', function () {
        handleQuantityInputChange(this, checkbox, maxQuantity, product);
    });

    // Gestionnaires pour les boutons d'augmentation/diminution
    decreaseBtn.on('click', function () {
        decreaseQuantity(quantityInput[0]);
    });

    increaseBtn.on('click', function () {
        increaseQuantity(quantityInput[0]);
    });
}

/**
 * Gestion du changement de checkbox de produit
 */
function handleProductCheckboxChange(checkbox, quantityInput, maxQuantity, product) {
    if (checkbox.checked) {
        if (parseFloat(quantityInput.val()) === 0) {
            quantityInput.val(maxQuantity.toFixed(2));
            updateReturnSummary(product.product_id, product.designation, maxQuantity);
        } else {
            updateReturnSummary(product.product_id, product.designation, quantityInput.val());
        }
    } else {
        quantityInput.val(0);
        removeFromReturnSummary(product.product_id);
    }
    updateSelectAllButton();
}

/**
 * Gestion du changement de quantité
 */
function handleQuantityInputChange(input, checkbox, maxQuantity, product) {
    validateReturnQuantity(input, maxQuantity);

    const inputValue = parseFloat(input.value) || 0;
    if (inputValue > 0) {
        checkbox.prop('checked', true);
        updateReturnSummary(product.product_id, product.designation, inputValue);
    } else {
        checkbox.prop('checked', false);
        removeFromReturnSummary(product.product_id);
    }
    updateSelectAllButton();
}

/**
 * Diminution de la quantité
 */
function decreaseQuantity(input) {
    const currentValue = parseFloat(input.value) || 0;
    if (currentValue > 0) {
        input.value = Math.max(0, currentValue - 0.01).toFixed(2);
        $(input).trigger('input');
    }
}

/**
 * Augmentation de la quantité
 */
function increaseQuantity(input) {
    const currentValue = parseFloat(input.value) || 0;
    const maxValue = parseFloat(input.getAttribute('max')) || 0;
    if (currentValue < maxValue) {
        input.value = Math.min(maxValue, currentValue + 0.01).toFixed(2);
        $(input).trigger('input');
    }
}

/**
 * Définition de la quantité maximale
 */
function setMaxQuantity(button, max) {
    const input = $(button).closest('.flex').find('.return-quantity')[0];
    if (input) {
        input.value = max;
        $(input).trigger('input');
    }
}

/**
 * Validation de la quantité de retour
 */
function validateReturnQuantity(input, max) {
    let value = parseFloat(input.value);
    if (isNaN(value) || value < 0) {
        input.value = 0;
    } else if (value > max) {
        input.value = max;
        showToast(`La quantité maximale à retourner est ${max}`, 'warning');
    }
}

// ==========================================
// GESTION DU RÉSUMÉ DES RETOURS
// ==========================================

/**
 * Mise à jour du résumé des retours
 */
function updateReturnSummary(productId, designation, quantity) {
    const existingIndex = returnsSelected.findIndex(item => item.product_id === productId);

    if (existingIndex !== -1) {
        returnsSelected[existingIndex].quantity = parseFloat(quantity);
    } else {
        returnsSelected.push({
            product_id: productId,
            designation: designation,
            quantity: parseFloat(quantity)
        });
    }

    displayReturnSummary();
    toggleReturnSummary(true);
}

/**
 * Suppression d'un produit du résumé
 */
function removeFromReturnSummary(productId) {
    returnsSelected = returnsSelected.filter(item => item.product_id !== productId);
    displayReturnSummary();

    if (returnsSelected.length === 0) {
        toggleReturnSummary(false);
    }
}

/**
 * Affichage du résumé des retours
 */
function displayReturnSummary() {
    const summaryContent = $('#summary-content');
    const summaryTotal = $('#summary-total');

    summaryContent.empty();

    returnsSelected.forEach(item => {
        const summaryItem = $(`
            <div class="summary-item">
                <div class="summary-item-name" title="${item.designation}">
                    ${truncateText(item.designation, 20)}
                </div>
                <div class="summary-item-quantity">${item.quantity}</div>
            </div>
        `);
        summaryContent.append(summaryItem);
    });

    summaryTotal.text(returnsSelected.length);
}

/**
 * Affichage/Masquage du résumé des retours
 */
function toggleReturnSummary(show) {
    const summary = $('#return-summary');

    if (show && returnsSelected.length > 0) {
        summary.addClass('visible');
    } else {
        summary.removeClass('visible');
    }
}

// ==========================================
// SOUMISSION DES RETOURS
// ==========================================

/**
 * Soumission des retours
 */
function submitReturns() {
    if (!currentProject) {
        showToast('Aucun projet sélectionné', 'error');
        return;
    }

    const isProjectCompleted = $('#project-completed-checkbox').is(':checked');

    if (isProjectCompleted) {
        ensureAllProductsSelected();
    } else if (returnsSelected.length === 0) {
        showToast('Aucun produit à retourner sélectionné', 'warning');
        return;
    }

    showSubmissionConfirmation(isProjectCompleted);
}

/**
 * S'assurer que tous les produits sont sélectionnés pour un projet terminé
 */
function ensureAllProductsSelected() {
    const checkboxes = $('.product-checkbox');
    const anyUnchecked = checkboxes.toArray().some(checkbox => !checkbox.checked);

    if (anyUnchecked) {
        checkboxes.prop('checked', true);

        checkboxes.each(function () {
            const checkbox = $(this);
            if (!checkbox.prop('checked')) {
                const row = checkbox.closest('.product-card');
                const quantityInput = row.find('.return-quantity');
                const maxQuantity = parseFloat(quantityInput.attr('max')) || 0;

                quantityInput.val(maxQuantity.toFixed(2));
                updateReturnSummary(
                    checkbox.data('product-id'),
                    checkbox.data('designation'),
                    maxQuantity
                );
            }
        });

        updateSelectAllButton();
    }
}

/**
 * Affichage de la confirmation de soumission
 */
function showSubmissionConfirmation(isProjectCompleted) {
    let additionalMessage = '';
    if (isProjectCompleted) {
        additionalMessage = `
            <div class="p-3 bg-blue-50 text-blue-800 rounded-md mb-3">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Information:</strong> Marquer le projet comme terminé supprimera automatiquement toutes les réservations restantes et annulera les commandes en attente.
            </div>
        `;
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Confirmer le retour',
            html: createSubmissionConfirmationHTML(additionalMessage, isProjectCompleted),
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check mr-2"></i>Confirmer',
            cancelButtonText: '<i class="fas fa-times mr-2"></i>Annuler',
            confirmButtonColor: '#4F46E5',
            cancelButtonColor: '#6B7280',
            reverseButtons: true,
            focusConfirm: false,
            showLoaderOnConfirm: true,
            preConfirm: () => processReturnsSubmission(isProjectCompleted),
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                handleSubmissionSuccess(result.value);
            }
        });
    }
}

/**
 * Création du HTML de confirmation
 */
function createSubmissionConfirmationHTML(additionalMessage, isProjectCompleted) {
    return `
        ${additionalMessage}
        <div class="text-left">
            ${returnsSelected.length > 0 ?
            `<p class="mb-2"><i class="fas fa-box text-indigo-500 mr-2"></i> Vous allez retourner <strong>${returnsSelected.length}</strong> produit(s) au stock.</p>` :
            '<p class="mb-2"><i class="fas fa-info-circle text-yellow-500 mr-2"></i> Aucun produit à retourner.</p>'}
            ${isProjectCompleted ?
            '<p class="mb-2"><i class="fas fa-check-circle text-green-500 mr-2"></i> Le projet sera marqué comme <strong>terminé</strong>.</p>' : ''}
            <p class="mt-4">Êtes-vous sûr de vouloir continuer ?</p>
        </div>
    `;
}

/**
 * Traitement de la soumission des retours
 */
function processReturnsSubmission(isProjectCompleted) {
    const requestData = {
        returns: returnsSelected,
        project_completed: isProjectCompleted,
        project_id: currentProject.idExpression,
        return_type: selectedReturnType,
        remove_all_reservations: isProjectCompleted
    };

    return $.ajax({
        url: 'api_return/api_returnProducts.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(requestData),
        dataType: 'json'
    })
        .then(response => {
            if (!response.success) {
                throw new Error(response.message || 'Une erreur est survenue lors du traitement de la demande.');
            }
            return response;
        })
        .catch(error => {
            if (typeof Swal !== 'undefined') {
                Swal.showValidationMessage(`Erreur: ${error.message || 'Une erreur est survenue.'}`);
            }
        });
}

/**
 * Gestion du succès de soumission
 */
function handleSubmissionSuccess(result) {
    const message = result.message;
    const canceledCount = result.canceled_orders_count || 0;

    let displayMessage = message;
    if (canceledCount > 0) {
        displayMessage = `${message} ${canceledCount} commande(s) en attente ont été automatiquement annulée(s).`;
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Opération réussie',
            text: displayMessage,
            icon: 'success',
            confirmButtonColor: '#4F46E5'
        }).then(() => {
            resetAfterSubmission();
        });
    } else {
        resetAfterSubmission();
    }
}

/**
 * Réinitialisation après soumission
 */
function resetAfterSubmission() {
    toggleReturnSummary(false);
    returnsSelected = [];
    if (currentProject && currentProject.idExpression) {
        getProjectDetails(currentProject.idExpression);
    }
    loadProjects();
}

// ==========================================
// GESTION DES MODALES
// ==========================================

/**
 * Affichage des détails d'un mouvement
 */
function showMovementDetails(movementId) {
    $.ajax({
        url: 'api_return/api_getMovementDetails.php',
        type: 'GET',
        data: { movement_id: movementId },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                displayMovementModal(response.movement);
            } else {
                showToast('Erreur lors du chargement des détails', 'error');
            }
        },
        error: function (xhr, status, error) {
            console.error('Erreur lors du chargement des détails du mouvement:', error);
            showToast('Erreur de connexion', 'error');
        }
    });
}

/**
 * Affichage du modal des détails de mouvement
 */
function displayMovementModal(movement) {
    const modal = $('#movement-details-modal');
    const title = $('#modal-title');
    const content = $('#modal-content');

    title.text(`Mouvement #${movement.id} - ${movement.product_name}`);

    const modalHTML = createMovementModalHTML(movement);
    content.html(modalHTML);
    modal.removeClass('hidden');
}

/**
 * Création du HTML du modal de mouvement
 */
function createMovementModalHTML(movement) {
    // Informations de base
    let modalHTML = `
        <div class="space-y-4">
            <!-- Informations principales -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                    Informations principales
                </h4>
                <div class="grid grid-cols-2 gap-4">
                    <div class="movement-detail-item">
                        <span class="movement-detail-label">Produit:</span>
                        <span class="movement-detail-value">${movement.product_name || 'N/A'}</span>
                    </div>
                    <div class="movement-detail-item">
                        <span class="movement-detail-label">Type de mouvement:</span>
                        <span class="movement-detail-value">
                            ${movement.movement_type_label || getMovementTypeLabel(movement.movement_type)}
                        </span>
                    </div>
                    <div class="movement-detail-item">
                        <span class="movement-detail-label">Quantité:</span>
                        <span class="movement-detail-value font-bold text-lg">
                            ${movement.quantity_formatted || movement.quantity}
                            ${movement.unit ? ` ${movement.unit}` : ''}
                        </span>
                    </div>
                    <div class="movement-detail-item">
                        <span class="movement-detail-label">Date:</span>
                        <span class="movement-detail-value">
                            ${movement.formatted_date || formatDateWithTime(movement.date)}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Informations de provenance/destination -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                    <i class="fas fa-route text-green-500 mr-2"></i>
                    Traçabilité
                </h4>
                <div class="grid grid-cols-2 gap-4">
                    <div class="movement-detail-item">
                        <span class="movement-detail-label">Provenance:</span>
                        <span class="movement-detail-value">${movement.provenance || '-'}</span>
                    </div>
                    <div class="movement-detail-item">
                        <span class="movement-detail-label">Destination:</span>
                        <span class="movement-detail-value">${movement.destination || '-'}</span>
                    </div>
                    <div class="movement-detail-item">
                        <span class="movement-detail-label">Projet:</span>
                        <span class="movement-detail-value">
                            ${movement.project_client || movement.nom_projet || '-'}
                            ${movement.code_projet ? ` (${movement.code_projet})` : ''}
                        </span>
                    </div>
                    ${movement.demandeur ? `
                        <div class="movement-detail-item">
                            <span class="movement-detail-label">Demandeur:</span>
                            <span class="movement-detail-value">${movement.demandeur}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>
    `;

    // Notes si disponibles
    if (movement.notes) {
        modalHTML += `
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                    <i class="fas fa-sticky-note text-gray-500 mr-2"></i>
                    Notes
                </h4>
                <div class="bg-white p-3 rounded border text-sm">
                    ${movement.notes}
                </div>
            </div>
        `;
    }

    return modalHTML;
}

/**
 * Fermeture du modal de mouvement
 */
function closeMovementModal() {
    $('#movement-details-modal').addClass('hidden');
}

/**
 * Affichage des notes d'un mouvement
 */
function showMovementNotes(encodedNotes) {
    const notes = decodeURIComponent(encodedNotes);
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Notes du mouvement',
            text: notes,
            icon: 'info',
            confirmButtonColor: '#4F46E5'
        });
    } else {
        alert(notes);
    }
}

// ==========================================
// FONCTIONS DE MISE À JOUR D'INTERFACE
// ==========================================

/**
 * Mise à jour du bouton "Tout sélectionner"
 */
function updateSelectAllButton() {
    const checkboxes = $('.product-checkbox');
    const allChecked = checkboxes.length > 0 && checkboxes.toArray().every(checkbox => checkbox.checked);

    updateSelectAllButtonText(!allChecked);
}

/**
 * Mise à jour du texte du bouton "Tout sélectionner"
 */
function updateSelectAllButtonText(anyUnchecked) {
    const button = $('#select-all-btn');
    if (button.length > 0) {
        button.html(anyUnchecked ?
            '<i class="fas fa-check-square mr-1"></i>Tout sélectionner' :
            '<i class="fas fa-times-square mr-1"></i>Tout désélectionner');
    }
}

/**
 * Sélection de tous les produits pour completion
 */
function selectAllProductsForCompletion() {
    $('.product-checkbox').prop('checked', true);

    $('.product-checkbox').each(function () {
        const checkbox = $(this);
        const row = checkbox.closest('.product-card');
        const quantityInput = row.find('.return-quantity');
        const maxQuantity = parseFloat(quantityInput.attr('max')) || 0;

        quantityInput.val(maxQuantity.toFixed(2));
        updateReturnSummary(
            checkbox.data('product-id'),
            checkbox.data('designation'),
            maxQuantity
        );
    });

    updateSelectAllButton();
}

// ==========================================
// FONCTIONS D'AFFICHAGE DES DÉTAILS DE PROJET
// ==========================================

/**
 * Affichage des détails du projet
 */
function displayProjectDetails(project) {
    const dateCreated = formatDate(project.created_at);
    const isCompleted = project.project_status === 'completed';
    const statusClass = isCompleted ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800';
    const statusText = isCompleted ? 'Terminé' : 'Actif';

    const detailsHTML = createProjectDetailsHTML(project, dateCreated, statusClass, statusText, isCompleted);
    $('#project-details').html(detailsHTML).removeClass('hidden');

    if (project.system_start_date) {
        appendSystemStartDateInfo(project.system_start_date);
    }
}

/**
 * Création du HTML des détails de projet
 */
function createProjectDetailsHTML(project, dateCreated, statusClass, statusText, isCompleted) {
    return `
        <div class="fade-in">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">${project.nom_client || 'Nom non spécifié'}</h3>
                    <div class="flex items-center mt-1">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                            ${statusText}
                        </span>
                        <span class="ml-2 text-sm text-gray-500">ID: ${project.idExpression || 'N/A'}</span>
                        <span class="mx-2 text-gray-300">|</span>
                        <span class="text-sm text-gray-500">Code: ${project.code_projet || 'N/A'}</span>
                    </div>
                </div>
                <div class="text-sm text-gray-500">
                    Créé le: ${dateCreated}
                </div>
            </div>
            
            <div class="mt-4">
                <p class="text-sm text-gray-600">${project.description_projet || 'Aucune description disponible.'}</p>
            </div>
            
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="bg-gray-100 rounded p-3">
                    <div class="text-xs text-gray-500">Chef de projet</div>
                    <div class="font-medium">${project.chefprojet || 'Non spécifié'}</div>
                </div>
                <div class="bg-gray-100 rounded p-3">
                    <div class="text-xs text-gray-500">Localisation</div>
                    <div class="font-medium">${project.sitgeo || 'Non spécifiée'}</div>
                </div>
            </div>
            
            ${!isCompleted ? `
                <div class="mt-4 flex items-center">
                    <input type="checkbox" id="project-completed-checkbox" class="h-4 w-4 text-indigo-600 rounded border-gray-300">
                    <label for="project-completed-checkbox" class="ml-2 text-sm text-gray-700">
                        Marquer ce projet comme terminé
                    </label>
                </div>
            ` : ''}
        </div>
    `;
}

/**
 * Ajout des informations de date de début système
 */
function appendSystemStartDateInfo(systemStartDate) {
    const formattedDate = formatDate(systemStartDate);
    $('#project-details').append(`
        <div class="mt-3 bg-blue-50 text-blue-700 p-2 rounded-md text-xs flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            <span>Seuls les produits et mouvements depuis le ${formattedDate} sont affichés</span>
        </div>
    `);
}

// ==========================================
// GESTION DES ERREURS ET ÉTATS VIDES
// ==========================================

/**
 * Affichage d'erreur pour les projets
 */
function displayProjectsError(message) {
    $('#projects-list').html(`
        <div class="text-center py-4 text-red-500">
            <i class="fas fa-exclamation-circle mr-2"></i>
            ${message}
        </div>
    `);
}

/**
 * HTML pour les projets vides
 */
function getEmptyProjectsHTML() {
    return `
        <div class="text-center py-4 text-gray-500">
            <i class="fas fa-folder-open text-gray-300 text-3xl mb-2"></i>
            <p>Aucun projet trouvé</p>
        </div>
    `;
}

/**
 * Gestion des erreurs de détails de projet
 */
function handleProjectDetailsError(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: message || 'Une erreur est survenue lors du chargement des détails du projet',
            confirmButtonColor: '#4F46E5'
        });
    } else {
        console.error('Erreur projet:', message);
    }

    $('#project-details').addClass('hidden');
    $('#empty-state').removeClass('hidden');
}

/**
 * Gestion d'un projet terminé
 */
function handleCompletedProject() {
    $('#reserved-products-section').addClass('hidden');
    $('#project-details').append(`
        <div class="mt-4 p-3 bg-yellow-50 text-yellow-700 rounded-md">
            <i class="fas fa-exclamation-circle mr-2"></i>
            Ce projet est marqué comme terminé et n'a pas de produits disponibles pour le retour.
        </div>
    `);
}

// ==========================================
// FONCTIONS UTILITAIRES AVANCÉES
// ==========================================

/**
 * Troncature de texte
 */
function truncateText(text, maxLength) {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

/**
 * Application du filtre de période
 */
function applyPeriodFilter(filterValue) {
    if (!historyTable) {
        console.warn('Tableau d\'historique non disponible pour le filtrage');
        return;
    }

    try {
        let searchPattern = '';
        const now = new Date();

        switch (filterValue) {
            case 'today':
                // Format français: DD/MM/YYYY
                const day = String(now.getDate()).padStart(2, '0');
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const year = now.getFullYear();
                searchPattern = `${day}/${month}/${year}`;
                break;

            case 'week':
                // Pour la semaine, utiliser le mois actuel en format MM/YYYY
                const weekMonth = String(now.getMonth() + 1).padStart(2, '0');
                const weekYear = now.getFullYear();
                searchPattern = `${weekMonth}/${weekYear}`;
                break;

            case 'month':
                // Format français: MM/YYYY
                const currentMonth = String(now.getMonth() + 1).padStart(2, '0');
                const currentYear = now.getFullYear();
                searchPattern = `${currentMonth}/${currentYear}`;
                break;

            case 'quarter':
                // Format français pour le trimestre: MM/YYYY du mois de début
                searchPattern = getQuarterPatternFrench(now);
                break;

            default:
                // Aucun filtre
                searchPattern = '';
        }

        console.log('Pattern de recherche généré (format français):', searchPattern);

        if (searchPattern === '') {
            // Réinitialiser le filtre
            historyTable.column(9).search('').draw();
        } else if (filterValue === 'week') {
            // Pour la semaine, utiliser une fonction personnalisée
            applyCustomDateFilterFrench(filterValue);
        } else {
            // Appliquer le pattern de recherche sur la colonne Date Filter
            // Utiliser la recherche simple (pas regex) car on cherche dans le texte formaté français
            historyTable.column(9).search(searchPattern, false, false).draw();
        }

    } catch (error) {
        console.error('Erreur dans applyPeriodFilter:', error);
        // En cas d'erreur, réinitialiser le filtre
        historyTable.column(9).search('').draw();
    }
}

/**
 * Filtre personnalisé pour les plages de dates (semaine)
 */
function applyCustomDateFilterFrench(period) {
    if (!historyTable) return;

    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'history-table') {
            return true; // Ne pas filtrer les autres tableaux
        }

        const dateStr = data[0]; // Colonne 0 contient la date formatée française
        if (!dateStr) return false;

        try {
            // Parser la date française DD/MM/YYYY HH:MM
            const dateParts = dateStr.split(' ')[0]; // Prendre seulement la partie date
            const [day, month, year] = dateParts.split('/');
            const rowDate = new Date(year, month - 1, day); // Créer l'objet Date

            const now = new Date();

            switch (period) {
                case 'week':
                    const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                    return rowDate >= weekAgo && rowDate <= now;
                default:
                    return true;
            }
        } catch (error) {
            console.warn('Erreur de parsing de date française:', dateStr, error);
            return false;
        }
    });

    historyTable.draw();

    // Nettoyer le filtre personnalisé après utilisation
    setTimeout(() => {
        $.fn.dataTable.ext.search.pop();
    }, 100);
}

/**
 * Pattern pour une semaine - VERSION SIMPLIFIÉE
 */
function getWeekPattern(startDate, endDate) {
    // Retourner le pattern du mois actuel pour simplifier
    const year = endDate.getFullYear();
    const month = String(endDate.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}`;
}

/**
 * Pattern de plage de dates
 */
function getDateRangePattern(startDate, endDate) {
    return endDate.getFullYear() + '-' + String(endDate.getMonth() + 1).padStart(2, '0');
}

/**
 * Pattern de trimestre
 */
function getQuarterPatternFrench(date) {
    const year = date.getFullYear();
    const quarter = Math.floor(date.getMonth() / 3) + 1;

    // Mois de début de chaque trimestre en format français
    const quarterStartMonths = {
        1: '01', // Q1: Janvier-Mars
        2: '04', // Q2: Avril-Juin  
        3: '07', // Q3: Juillet-Septembre
        4: '10'  // Q4: Octobre-Décembre
    };

    // Retourner au format MM/YYYY
    return `${quarterStartMonths[quarter]}/${year}`;
}

/**
 * Configuration des filtres du tableau d'historique
 */
function setupHistoryTableFilters() {
    // Filtre par type de mouvement
    $('#movement-type-filter').off('change').on('change', function () {
        const filterValue = this.value;

        if (!historyTable) {
            console.warn('Tableau d\'historique non initialisé');
            return;
        }

        try {
            if (filterValue === '') {
                // Réinitialiser le filtre
                historyTable.column(8).search('').draw();
            } else {
                // Appliquer le filtre exact sur la colonne cachée Type Filter
                historyTable.column(8).search('^' + filterValue + '$', true, false).draw();
            }

            console.log('Filtre type appliqué:', filterValue);
        } catch (error) {
            console.error('Erreur lors de l\'application du filtre type:', error);
        }
    });

    // Filtre par période
    $('#period-filter').off('change').on('change', function () {
        const filterValue = this.value;

        if (!historyTable) {
            console.warn('Tableau d\'historique non initialisé');
            return;
        }

        try {
            applyPeriodFilter(filterValue);
            console.log('Filtre période appliqué:', filterValue);
        } catch (error) {
            console.error('Erreur lors de l\'application du filtre période:', error);
        }
    });
}

/**
 * Libellé du type de mouvement
 */
function getMovementTypeLabel(type) {
    const labels = {
        'input': 'Entrée',
        'output': 'Sortie',
        'adjustment': 'Ajustement',
        'return': 'Retour',
        'transfer': 'Transfert'
    };
    return labels[type] || type;
}

/**
 * Fonction de débogage pour les filtres - NOUVELLE FONCTION
 */
function debugFilters() {
    if (!historyTable) {
        console.log('❌ Tableau d\'historique non initialisé');
        return;
    }

    console.log('🔍 État des filtres:');
    console.log('- Filtre type (colonne 8):', historyTable.column(8).search());
    console.log('- Filtre date (colonne 9):', historyTable.column(9).search());
    console.log('- Nombre de lignes visibles:', historyTable.rows({ search: 'applied' }).count());
    console.log('- Nombre total de lignes:', historyTable.rows().count());

    // Vérifier les données de la première ligne
    const firstRowData = historyTable.row(0).data();
    if (firstRowData) {
        console.log('- Données première ligne:');
        console.log('  * Date formatée (colonne 0):', firstRowData[0]);
        console.log('  * Type filter (colonne 8):', firstRowData[8]);
        console.log('  * Date filter (colonne 9):', firstRowData[9]);
    }

    // Afficher quelques exemples de dates
    console.log('📅 Exemples de patterns de recherche:');
    const now = new Date();
    const day = String(now.getDate()).padStart(2, '0');
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();

    console.log('- Aujourd\'hui:', `${day}/${month}/${year}`);
    console.log('- Ce mois:', `${month}/${year}`);
    console.log('- Ce trimestre:', getQuarterPatternFrench(now));
}

// ==========================================
// FONCTIONS GLOBALES EXPOSÉES
// ==========================================

/**
 * Fonctions exposées globalement pour utilisation dans le HTML
 */
window.debugFilters = debugFilters;
window.setMaxQuantity = setMaxQuantity;
window.showMovementDetails = showMovementDetails;
window.showMovementNotes = showMovementNotes;
window.closeMovementModal = closeMovementModal;
window.toggleReturnSummary = toggleReturnSummary;

// ==========================================
// INITIALISATION FINALE
// ==========================================

/**
 * Log de démarrage pour le débogage
 */
console.log('🚀 Gestionnaire de retours de projets initialisé - Version 2.1 (Corrections appliquées)');
console.log('📊 Fonctionnalités disponibles:', {
    'Gestion des projets': '✅',
    'Retours de matériel': '✅',
    'Historique des mouvements': '✅',
    'Recherche sécurisée': '✅',
    'Gestion des erreurs': '✅'
});