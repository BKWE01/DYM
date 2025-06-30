/**
 * Plugin DataTables pour filtres personnalisés avancés - Version 4.0
 * 
 * Ce fichier gère les filtres personnalisés pour les tableaux DataTables
 * dans les pages d'achats et d'archives avec support des rejets.
 * 
 * MISE À JOUR : Support complet des bons de commande rejetés
 */

// Namespace pour éviter les conflits
var CustomFilters = {};

/**
 * Configuration des filtres personnalisés pour les matériaux
 */
CustomFilters.MaterialsFilters = {
    /**
     * Initialise tous les filtres pour les tableaux de matériaux
     * @param {string} tableId - ID du tableau DataTable
     */
    init: function (tableId) {
        // Référence au DataTable
        this.table = jQuery('#' + tableId).DataTable();

        // Ajouter les écouteurs d'événements
        this.setupEventListeners(tableId);

        // Initialiser les filtres de date
        this.initDateFilters(tableId);

        // Initialiser le filtre de prix
        this.initPriceFilter(tableId);

        console.log('Filtres personnalisés initialisés pour:', tableId);
    },

    /**
     * Configuration des écouteurs d'événements
     */
    setupEventListeners: function (tableId) {
        const filters = {
            projet: 'project-filter-' + tableId,
            fournisseur: 'supplier-filter-' + tableId,
            statut: 'status-filter-' + tableId,
            dateDebut: 'date-start-' + tableId,
            dateFin: 'date-end-' + tableId,
            prixMin: 'price-min-' + tableId,
            prixMax: 'price-max-' + tableId
        };

        // Appliquer les filtres lors des changements
        Object.values(filters).forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => this.applyFilters(tableId));
                element.addEventListener('input', () => this.applyFilters(tableId));
            }
        });

        // Bouton de réinitialisation
        const resetBtn = document.getElementById('reset-filters-' + tableId);
        if (resetBtn) {
            resetBtn.addEventListener('click', () => this.resetFilters(tableId));
        }

        // Bouton d'export avec filtres
        const exportBtn = document.getElementById('export-filtered-' + tableId);
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportFiltered(tableId));
        }
    },

    /**
     * Initialisation des filtres de date
     */
    initDateFilters: function (tableId) {
        // Ajouter la validation de date
        const dateStart = document.getElementById('date-start-' + tableId);
        const dateEnd = document.getElementById('date-end-' + tableId);

        if (dateStart && dateEnd) {
            dateStart.addEventListener('change', function () {
                dateEnd.min = this.value;
            });

            dateEnd.addEventListener('change', function () {
                dateStart.max = this.value;
            });
        }
    },

    /**
     * Initialisation du filtre de prix
     */
    initPriceFilter: function (tableId) {
        const priceMin = document.getElementById('price-min-' + tableId);
        const priceMax = document.getElementById('price-max-' + tableId);

        if (priceMin && priceMax) {
            priceMin.addEventListener('input', function () {
                if (priceMax.value && parseFloat(this.value) > parseFloat(priceMax.value)) {
                    this.value = priceMax.value;
                }
            });

            priceMax.addEventListener('input', function () {
                if (priceMin.value && parseFloat(this.value) < parseFloat(priceMin.value)) {
                    this.value = priceMin.value;
                }
            });
        }
    },

    /**
     * Applique tous les filtres actifs
     */
    applyFilters: function (tableId) {
        const table = this.table;

        // Réinitialiser la recherche
        table.search('');

        // Filtrer par projet
        const projectFilter = document.getElementById('project-filter-' + tableId);
        if (projectFilter && projectFilter.value) {
            table.column(1).search(projectFilter.value);
        } else {
            table.column(1).search('');
        }

        // Filtrer par fournisseur
        const supplierFilter = document.getElementById('supplier-filter-' + tableId);
        if (supplierFilter && supplierFilter.value) {
            table.column(7).search(supplierFilter.value);
        } else {
            table.column(7).search('');
        }

        // Filtrer par statut
        const statusFilter = document.getElementById('status-filter-' + tableId);
        if (statusFilter && statusFilter.value) {
            table.column(6).search(statusFilter.value);
        } else {
            table.column(6).search('');
        }

        // Appliquer le filtre de date personnalisé
        this.applyDateFilter(tableId);

        // Appliquer le filtre de prix personnalisé
        this.applyPriceFilter(tableId);

        // Redessiner le tableau
        table.draw();

        // Mettre à jour le compteur de résultats
        this.updateResultsCount(tableId);
    },

    /**
     * Filtre personnalisé pour les dates
     */
    applyDateFilter: function (tableId) {
        const dateStart = document.getElementById('date-start-' + tableId);
        const dateEnd = document.getElementById('date-end-' + tableId);

        jQuery.fn.dataTable.ext.search.push(
            function (settings, data, dataIndex) {
                // Vérifier que c'est le bon tableau
                if (settings.nTable.id !== tableId) {
                    return true;
                }

                // Récupérer la date de la ligne (colonne 8)
                const dateStr = data[8];

                if (!dateStr || dateStr === 'N/A') {
                    return false;
                }

                // Parser la date française
                const parts = dateStr.split('/');
                const rowDate = new Date(parts[2], parts[1] - 1, parts[0]);

                // Vérifier la date de début
                if (dateStart && dateStart.value) {
                    const startDate = new Date(dateStart.value);
                    if (rowDate < startDate) {
                        return false;
                    }
                }

                // Vérifier la date de fin
                if (dateEnd && dateEnd.value) {
                    const endDate = new Date(dateEnd.value);
                    if (rowDate > endDate) {
                        return false;
                    }
                }

                return true;
            }
        );
    },

    /**
     * Filtre personnalisé pour les prix
     */
    applyPriceFilter: function (tableId) {
        const priceMin = document.getElementById('price-min-' + tableId);
        const priceMax = document.getElementById('price-max-' + tableId);

        jQuery.fn.dataTable.ext.search.push(
            function (settings, data, dataIndex) {
                // Vérifier que c'est le bon tableau
                if (settings.nTable.id !== tableId) {
                    return true;
                }

                // Récupérer le prix de la ligne (colonne 6 ou 7 selon le tableau)
                const priceStr = data[6] || data[7];

                if (!priceStr) {
                    return true;
                }

                // Extraire la valeur numérique
                const price = parseFloat(priceStr.replace(/[^\d,-]/g, '').replace(',', '.'));

                // Vérifier le prix minimum
                if (priceMin && priceMin.value && price < parseFloat(priceMin.value)) {
                    return false;
                }

                // Vérifier le prix maximum
                if (priceMax && priceMax.value && price > parseFloat(priceMax.value)) {
                    return false;
                }

                return true;
            }
        );
    },

    /**
     * Réinitialise tous les filtres
     */
    resetFilters: function (tableId) {
        // Réinitialiser les champs de formulaire
        const form = document.getElementById('filters-form-' + tableId);
        if (form) {
            form.reset();
        }

        // Réinitialiser les filtres DataTables
        this.table.search('');
        this.table.columns().search('');

        // Supprimer les filtres personnalisés
        jQuery.fn.dataTable.ext.search = [];

        // Redessiner le tableau
        this.table.draw();

        // Mettre à jour le compteur
        this.updateResultsCount(tableId);
    },

    /**
     * Met à jour le compteur de résultats
     */
    updateResultsCount: function (tableId) {
        const info = this.table.page.info();
        const counter = document.getElementById('results-count-' + tableId);

        if (counter) {
            counter.textContent = `${info.recordsDisplay} résultat(s) sur ${info.recordsTotal}`;
        }
    },

    /**
     * Export des données filtrées
     */
    exportFiltered: function (tableId) {
        // Récupérer les données filtrées
        const data = this.table.rows({ search: 'applied' }).data();

        if (data.length === 0) {
            Swal.fire({
                title: 'Aucune donnée',
                text: 'Aucune donnée à exporter avec les filtres actuels.',
                icon: 'warning'
            });
            return;
        }

        // Créer le CSV
        let csv = this.generateCSV(data);

        // Télécharger le fichier
        this.downloadCSV(csv, 'export_' + tableId + '_' + new Date().toISOString().slice(0, 10) + '.csv');
    },

    /**
     * Génère le contenu CSV
     */
    generateCSV: function (data) {
        // En-têtes
        let csv = 'Projet,Client,Produit,Quantité,Unité,Statut,Prix Unitaire,Fournisseur,Date\n';

        // Données
        data.each(function (row) {
            csv += `"${row[1]}","${row[2]}","${row[3]}","${row[4]}","${row[5]}","${row[6]}","${row[7]}","${row[8]}","${row[9]}"\n`;
        });

        return csv;
    },

    /**
     * Télécharge le fichier CSV
     */
    downloadCSV: function (csv, filename) {
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
};

/**
 * Configuration des filtres pour l'archive des bons de commande - MISE À JOUR AVEC REJETS
 */
CustomFilters.OrdersFilters = {
    /**
     * Paramètres de configuration
     */
    config: {
        tableId: 'purchaseOrdersTable',
        saveKey: 'dym_archive_filters',
        filterFormId: 'filters-form-orders',
        toggleBtnId: 'toggle-filters-orders',
        filterSectionId: 'filters-section-orders',
        dateFormat: 'dd/mm/yyyy'
    },

    /**
     * État du module
     */
    state: {
        activeFilters: {},
        favoritesFilters: [],
        lastSearches: [],
        dataCache: null
    },

    /**
     * Initialise les filtres pour l'archive
     */
    init: function () {
        console.log('Initialisation des filtres avancés pour l\'archive des bons de commande (v4.0 - avec rejets)');
        this.table = jQuery('#' + this.config.tableId).DataTable();

        // Charger l'état sauvegardé
        this.loadState();

        // Configuration des événements
        this.setupEventListeners();

        // Initialiser les filtres spéciaux
        this.initAmountFilter();
        this.initDateRangeFilter();
        this.initProjectsFilter();
        this.initCreatedByFilter();
        this.initAdvancedDateFilter();
        this.initMultiSelectFilters();

        // Créer le panneau des filtres actifs
        this.createActiveFiltersPanel();

        // Appliquer les filtres sauvegardés si présents
        if (Object.keys(this.state.activeFilters).length > 0) {
            this.restoreFilters(this.state.activeFilters);
        }

        // Initialiser les favoris et historique
        this.initFavoritesAndHistory();

        // Mettre à jour les statistiques initiales
        this.updateStatistics();
    },

    /**
     * Charge l'état sauvegardé depuis le localStorage
     */
    loadState: function () {
        try {
            const savedState = localStorage.getItem(this.config.saveKey);
            if (savedState) {
                const parsedState = JSON.parse(savedState);
                this.state = { ...this.state, ...parsedState };
                console.log('État des filtres chargé depuis localStorage');
            }
        } catch (error) {
            console.error('Erreur lors du chargement des filtres sauvegardés:', error);
            localStorage.removeItem(this.config.saveKey);
        }
    },

    /**
     * Sauvegarde l'état actuel dans le localStorage
     */
    saveState: function () {
        try {
            localStorage.setItem(this.config.saveKey, JSON.stringify(this.state));
        } catch (error) {
            console.error('Erreur lors de la sauvegarde de l\'état:', error);
        }
    },

    /**
     * Configuration des écouteurs d'événements
     */
    setupEventListeners: function () {
        // Filtres simples
        const filters = {
            orderNumber: 'order-number-filter',
            supplier: 'supplier-filter-orders',
            project: 'project-filter-orders',
            status: 'status-filter-orders',
            type: 'type-filter-orders',
            createdBy: 'created-by-filter',
            dateStart: 'date-start-filter',
            dateEnd: 'date-end-filter'
        };

        Object.entries(filters).forEach(([key, id]) => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', () => this.applyFilters());
                element.addEventListener('change', () => this.applyFilters());
            }
        });

        // Boutons
        document.getElementById('reset-filters-orders')?.addEventListener('click', () => this.resetFilters());
        document.getElementById('advanced-stats')?.addEventListener('click', () => this.showAdvancedStats());
        document.getElementById('save-filters-btn')?.addEventListener('click', () => this.saveCurrentFiltersAsPreset());
        document.getElementById('export-filtered-csv')?.addEventListener('click', () => this.exportFilteredResults('csv'));
        document.getElementById('export-filtered-excel')?.addEventListener('click', () => this.exportFilteredResults('excel'));
        document.getElementById('export-filtered-pdf')?.addEventListener('click', () => this.exportFilteredResults('pdf'));
    },

    /**
     * Initialise les filtres de projets avec recherche autocomplete
     */
    initProjectsFilter: function () {
        const projectInput = document.getElementById('project-filter-orders');

        if (projectInput) {
            // Créer le conteneur de suggestions s'il n'existe pas
            let suggestionsContainer = document.getElementById('project-suggestions');
            if (!suggestionsContainer) {
                suggestionsContainer = document.createElement('div');
                suggestionsContainer.id = 'project-suggestions';
                suggestionsContainer.className = 'absolute z-10 bg-white shadow-lg rounded-md max-h-60 overflow-y-auto w-full';
                projectInput.parentNode.style.position = 'relative';
                projectInput.parentNode.appendChild(suggestionsContainer);
            }

            // Fonction pour charger les projets uniques du tableau
            const loadProjects = () => {
                const projects = new Set();
                this.table.column(3).data().each(function (value) {
                    if (value) {
                        // Extraire les noms de projets (peut contenir du HTML)
                        const div = document.createElement('div');
                        div.innerHTML = value;
                        const text = div.textContent.trim();

                        // Gérer le cas des multi-projets
                        if (text.includes('Multi-projets')) {
                            // Ne pas ajouter l'entrée "Multi-projets (X)"
                        } else {
                            projects.add(text);
                        }
                    }
                });
                return Array.from(projects).sort();
            };

            // Afficher les suggestions lors de la saisie
            projectInput.addEventListener('input', function () {
                const value = this.value.toLowerCase();
                if (value.length < 2) {
                    suggestionsContainer.innerHTML = '';
                    suggestionsContainer.style.display = 'none';
                    return;
                }

                const projects = loadProjects();
                const matches = projects.filter(project =>
                    project.toLowerCase().includes(value)
                ).slice(0, 10);

                if (matches.length === 0) {
                    suggestionsContainer.innerHTML = '<div class="p-2 text-gray-500">Aucun résultat</div>';
                } else {
                    suggestionsContainer.innerHTML = matches.map(project =>
                        `<div class="p-2 hover:bg-gray-100 cursor-pointer">${project}</div>`
                    ).join('');

                    // Ajouter les événements de clic
                    suggestionsContainer.querySelectorAll('div').forEach(div => {
                        div.addEventListener('click', () => {
                            projectInput.value = div.textContent;
                            suggestionsContainer.style.display = 'none';
                            this.applyFilters();
                        });
                    });
                }

                suggestionsContainer.style.display = 'block';
            }.bind(this));

            // Masquer les suggestions au clic en dehors
            document.addEventListener('click', function (e) {
                if (e.target !== projectInput && e.target !== suggestionsContainer) {
                    suggestionsContainer.style.display = 'none';
                }
            });
        }
    },

    /**
     * Initialise le filtre de créateur avec recherche autocomplete
     */
    initCreatedByFilter: function () {
        const createdBySelect = document.getElementById('created-by-filter');

        if (createdBySelect) {
            // Charger les créateurs uniques depuis le tableau
            const users = new Set();
            this.table.column(7).data().each(function (value) {
                if (value) {
                    users.add(value.trim());
                }
            });

            // Trier et ajouter les options
            const sortedUsers = Array.from(users).sort();

            // Ajouter une option vide
            createdBySelect.innerHTML = '<option value="">Tous les utilisateurs</option>';

            // Ajouter les autres options
            sortedUsers.forEach(user => {
                const option = document.createElement('option');
                option.value = user;
                option.textContent = user;
                createdBySelect.appendChild(option);
            });
        }
    },

    /**
     * Initialise le filtre de date avancé
     */
    initAdvancedDateFilter: function () {
        const dateStartInput = document.getElementById('date-start-filter');
        const dateEndInput = document.getElementById('date-end-filter');

        if (dateStartInput && dateEndInput) {
            // S'assurer que le format est correct (aaaa-mm-jj pour l'input date)
            const formatDate = (date) => {
                if (!date) return '';
                const d = new Date(date);
                return d.toISOString().split('T')[0];
            };

            // Événement de changement pour limiter les dates
            dateStartInput.addEventListener('change', function () {
                dateEndInput.min = this.value;
                if (dateEndInput.value && new Date(dateEndInput.value) < new Date(this.value)) {
                    dateEndInput.value = this.value;
                }
            });

            dateEndInput.addEventListener('change', function () {
                dateStartInput.max = this.value;
                if (dateStartInput.value && new Date(dateStartInput.value) > new Date(this.value)) {
                    dateStartInput.value = this.value;
                }
            });

            // Ajouter des raccourcis de date
            const dateShortcuts = document.getElementById('date-shortcuts');
            if (dateShortcuts) {
                dateShortcuts.addEventListener('click', (e) => {
                    if (e.target.tagName === 'BUTTON') {
                        const period = e.target.dataset.period;

                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        let startDate = new Date(today);

                        switch (period) {
                            case 'today':
                                // Aujourd'hui (déjà configuré)
                                break;
                            case 'yesterday':
                                startDate.setDate(today.getDate() - 1);
                                break;
                            case 'week':
                                startDate.setDate(today.getDate() - 7);
                                break;
                            case 'month':
                                startDate.setMonth(today.getMonth() - 1);
                                break;
                            case 'quarter':
                                startDate.setMonth(today.getMonth() - 3);
                                break;
                            case 'year':
                                startDate.setFullYear(today.getFullYear() - 1);
                                break;
                        }

                        dateStartInput.value = formatDate(startDate);
                        dateEndInput.value = formatDate(today);

                        this.applyFilters();
                    }
                });
            }
        }
    },

    /**
     * Initialise le filtre de montant
     */
    initAmountFilter: function () {
        const minAmount = document.getElementById('amount-min');
        const maxAmount = document.getElementById('amount-max');
        const amountSlider = document.getElementById('amount-slider');

        if (minAmount && maxAmount) {
            // Limites de montant selon les données
            let minValue = Infinity;
            let maxValue = 0;

            this.table.column(5).data().each(function (value) {
                // Extraction du montant numérique
                const amount = parseFloat(value.replace(/[^\d,-]/g, '').replace(',', '.'));
                if (!isNaN(amount)) {
                    minValue = Math.min(minValue, amount);
                    maxValue = Math.max(maxValue, amount);
                }
            });

            // Arrondir les valeurs
            minValue = Math.floor(minValue / 1000) * 1000;
            maxValue = Math.ceil(maxValue / 1000) * 1000;

            // Mettre à jour les placeholders
            minAmount.placeholder = `Min (${minValue.toLocaleString('fr-FR')})`;
            maxAmount.placeholder = `Max (${maxValue.toLocaleString('fr-FR')})`;

            // Événements sur changement
            minAmount.addEventListener('input', () => {
                if (maxAmount.value && parseFloat(minAmount.value) > parseFloat(maxAmount.value)) {
                    minAmount.value = maxAmount.value;
                }
                this.applyFilters();
            });

            maxAmount.addEventListener('input', () => {
                if (minAmount.value && parseFloat(maxAmount.value) < parseFloat(minAmount.value)) {
                    maxAmount.value = minAmount.value;
                }
                this.applyFilters();
            });

            // Implémenter un slider si disponible
            if (amountSlider) {
                // Code pour initialiser le slider (utilisant noUiSlider ou un équivalent)
                // Cette partie dépend de la bibliothèque utilisée
            }
        }
    },

    /**
     * Initialise les filtres à sélection multiple
     */
    initMultiSelectFilters: function () {
        // Exemple pour un filtre multi-fournisseurs
        const multiSupplierSelect = document.getElementById('multi-supplier-filter');

        if (multiSupplierSelect) {
            // Créer une liste de contrôle pour les fournisseurs
            const suppliers = new Set();
            this.table.column(4).data().each(function (value) {
                if (value) suppliers.add(value.trim());
            });

            // Créer la liste
            let html = '';
            Array.from(suppliers).sort().forEach(supplier => {
                html += `
                <div class="flex items-center mb-1">
                    <input type="checkbox" class="supplier-checkbox" value="${supplier}" id="supplier-${supplier.replace(/\s+/g, '-')}">
                    <label class="ml-2 text-sm" for="supplier-${supplier.replace(/\s+/g, '-')}">${supplier}</label>
                </div>`;
            });

            multiSupplierSelect.innerHTML = html;

            // Ajouter les événements
            document.querySelectorAll('.supplier-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', () => this.applyFilters());
            });
        }
    },

    /**
     * Initialise le filtre de période
     */
    initDateRangeFilter: function () {
        const dateRange = document.getElementById('date-range-orders');

        if (dateRange) {
            dateRange.addEventListener('change', () => {
                console.log('Période sélectionnée:', dateRange.value);
                this.applyDateRangeFilter(dateRange.value);
            });
        }
    },

    /**
     * Crée le panneau des filtres actifs
     */
    createActiveFiltersPanel: function () {
        const filtersContainer = document.getElementById('active-filters-container');
        if (!filtersContainer) return;

        // Vider le conteneur
        filtersContainer.innerHTML = '';

        // Construire le HTML pour les filtres actifs
        const activeFilters = this.getActiveFilters();

        if (Object.keys(activeFilters).length === 0) {
            filtersContainer.innerHTML = '<p class="text-gray-500 italic">Aucun filtre actif</p>';
            filtersContainer.classList.add('hidden');
            return;
        }

        filtersContainer.classList.remove('hidden');

        let html = '<div class="flex flex-wrap gap-2">';

        Object.entries(activeFilters).forEach(([key, value]) => {
            let label = '';
            switch (key) {
                case 'orderNumber': label = 'N° Bon'; break;
                case 'supplier': label = 'Fournisseur'; break;
                case 'minAmount': label = 'Montant min'; break;
                case 'maxAmount': label = 'Montant max'; break;
                case 'dateStart': label = 'Du'; break;
                case 'dateEnd': label = 'Au'; break;
                case 'status': label = 'État'; break;
                case 'type': label = 'Type'; break;
                case 'project': label = 'Projet'; break;
                case 'createdBy': label = 'Créé par'; break;
                default: label = key;
            }

            html += `
            <div class="bg-blue-100 text-blue-800 rounded-full px-3 py-1 text-sm flex items-center">
                <span class="font-medium mr-1">${label}:</span>
                <span>${value}</span>
                <button class="ml-2 text-blue-600 hover:text-blue-800" data-filter="${key}">
                    <span class="material-icons text-sm">close</span>
                </button>
            </div>`;
        });

        html += '</div>';
        filtersContainer.innerHTML = html;

        // Ajouter les événements pour supprimer les filtres
        filtersContainer.querySelectorAll('button[data-filter]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const filterKey = e.currentTarget.dataset.filter;
                this.removeFilter(filterKey);
            });
        });
    },

    /**
     * Obtient les filtres actifs sous forme d'objet
     */
    getActiveFilters: function () {
        const filters = {};

        // Récupérer les valeurs des filtres simples
        const orderNumber = document.getElementById('order-number-filter')?.value;
        if (orderNumber) filters.orderNumber = orderNumber;

        const supplier = document.getElementById('supplier-filter-orders')?.value;
        if (supplier) filters.supplier = supplier;

        const minAmount = document.getElementById('amount-min')?.value;
        if (minAmount) filters.minAmount = minAmount;

        const maxAmount = document.getElementById('amount-max')?.value;
        if (maxAmount) filters.maxAmount = maxAmount;

        const dateStart = document.getElementById('date-start-filter')?.value;
        if (dateStart) filters.dateStart = dateStart;

        const dateEnd = document.getElementById('date-end-filter')?.value;
        if (dateEnd) filters.dateEnd = dateEnd;

        const status = document.getElementById('status-filter-orders')?.value;
        if (status && status !== 'all') filters.status = status;

        const type = document.getElementById('type-filter-orders')?.value;
        if (type && type !== 'all') filters.type = type;

        const project = document.getElementById('project-filter-orders')?.value;
        if (project) filters.project = project;

        const createdBy = document.getElementById('created-by-filter')?.value;
        if (createdBy) filters.createdBy = createdBy;

        // Filtres multi-select
        const selectedSuppliers = [];
        document.querySelectorAll('.supplier-checkbox:checked').forEach(cb => {
            selectedSuppliers.push(cb.value);
        });
        if (selectedSuppliers.length > 0) {
            filters.multiSuppliers = selectedSuppliers.join(', ');
        }

        return filters;
    },

    /**
     * Supprime un filtre actif et met à jour l'affichage
     */
    removeFilter: function (filterKey) {
        // Réinitialiser le champ correspondant
        switch (filterKey) {
            case 'orderNumber':
                document.getElementById('order-number-filter').value = '';
                break;
            case 'supplier':
                document.getElementById('supplier-filter-orders').value = '';
                break;
            case 'minAmount':
                document.getElementById('amount-min').value = '';
                break;
            case 'maxAmount':
                document.getElementById('amount-max').value = '';
                break;
            case 'dateStart':
                document.getElementById('date-start-filter').value = '';
                break;
            case 'dateEnd':
                document.getElementById('date-end-filter').value = '';
                break;
            case 'status':
                document.getElementById('status-filter-orders').value = 'all';
                break;
            case 'type':
                document.getElementById('type-filter-orders').value = 'all';
                break;
            case 'project':
                document.getElementById('project-filter-orders').value = '';
                break;
            case 'createdBy':
                document.getElementById('created-by-filter').value = '';
                break;
            case 'multiSuppliers':
                document.querySelectorAll('.supplier-checkbox').forEach(cb => {
                    cb.checked = false;
                });
                break;
        }

        // Appliquer les filtres mis à jour
        this.applyFilters();
    },

    /**
     * Initialise les fonctionnalités de favoris et historique
     */
    initFavoritesAndHistory: function () {
        const favoritesContainer = document.getElementById('favorites-filters');
        const historyContainer = document.getElementById('history-filters');

        if (favoritesContainer) {
            this.renderFavorites(favoritesContainer);
        }

        if (historyContainer) {
            this.renderHistory(historyContainer);
        }
    },

    /**
     * Affiche les filtres favoris
     */
    renderFavorites: function (container) {
        if (this.state.favoritesFilters.length === 0) {
            container.innerHTML = '<p class="text-gray-500 italic">Aucun filtre favori</p>';
            return;
        }

        let html = '<div class="space-y-2">';
        this.state.favoritesFilters.forEach((favorite, index) => {
            html += `
            <div class="bg-white border rounded-lg p-2 flex justify-between items-center">
                <div>
                    <p class="font-medium">${favorite.name}</p>
                    <p class="text-xs text-gray-500">${this.formatFilterDescription(favorite.filters)}</p>
                </div>
                <div class="flex space-x-1">
                    <button class="text-blue-600 hover:text-blue-800 p-1" data-apply="${index}">
                        <span class="material-icons text-sm">play_arrow</span>
                    </button>
                    <button class="text-red-600 hover:text-red-800 p-1" data-delete="${index}">
                        <span class="material-icons text-sm">delete</span>
                    </button>
                </div>
            </div>`;
        });
        html += '</div>';

        container.innerHTML = html;

        // Ajouter les événements
        container.querySelectorAll('button[data-apply]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.currentTarget.dataset.apply);
                this.applyFavoriteFilter(index);
            });
        });

        container.querySelectorAll('button[data-delete]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.currentTarget.dataset.delete);
                this.deleteFavoriteFilter(index);
            });
        });
    },

    /**
     * Formatte la description d'un ensemble de filtres - MISE À JOUR AVEC REJETS
     */
    formatFilterDescription: function (filters) {
        const parts = [];

        if (filters.supplier) parts.push(`Fournisseur: ${filters.supplier}`);
        if (filters.status) {
            // MISE À JOUR : Gestion des nouveaux statuts
            let statusLabel = filters.status;
            switch (filters.status) {
                case 'validated': statusLabel = 'Validé'; break;
                case 'pending': statusLabel = 'En attente'; break;
                case 'rejected': statusLabel = 'Rejeté'; break;
            }
            parts.push(`État: ${statusLabel}`);
        }
        if (filters.type) parts.push(`Type: ${filters.type}`);
        if (filters.minAmount) parts.push(`Min: ${filters.minAmount} FCFA`);
        if (filters.maxAmount) parts.push(`Max: ${filters.maxAmount} FCFA`);

        return parts.join(' | ') || 'Filtres personnalisés';
    },

    /**
     * Sauvegarde les filtres actuels en tant que préréglage
     */
    saveCurrentFiltersAsPreset: function () {
        const filters = this.getActiveFilters();

        if (Object.keys(filters).length === 0) {
            Swal.fire({
                title: 'Aucun filtre actif',
                text: 'Veuillez appliquer au moins un filtre pour pouvoir le sauvegarder.',
                icon: 'info'
            });
            return;
        }

        Swal.fire({
            title: 'Sauvegarder les filtres',
            input: 'text',
            inputLabel: 'Nom du préréglage',
            inputPlaceholder: 'Ex: Commandes récentes PETROCI',
            showCancelButton: true,
            confirmButtonText: 'Sauvegarder',
            cancelButtonText: 'Annuler',
            inputValidator: (value) => {
                if (!value) {
                    return 'Veuillez entrer un nom pour ce préréglage';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Ajouter aux favoris
                this.state.favoritesFilters.push({
                    name: result.value,
                    filters: filters,
                    createdAt: new Date().toISOString()
                });

                // Sauvegarder l'état
                this.saveState();

                // Mettre à jour l'affichage
                const favoritesContainer = document.getElementById('favorites-filters');
                if (favoritesContainer) {
                    this.renderFavorites(favoritesContainer);
                }

                Swal.fire({
                    title: 'Sauvegardé!',
                    text: 'Vos filtres ont été sauvegardés avec succès.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    },

    /**
     * Applique un filtre favori
     */
    applyFavoriteFilter: function (index) {
        if (!this.state.favoritesFilters[index]) return;

        const filters = this.state.favoritesFilters[index].filters;
        this.restoreFilters(filters);
    },

    /**
     * Supprime un filtre favori
     */
    deleteFavoriteFilter: function (index) {
        Swal.fire({
            title: 'Êtes-vous sûr?',
            text: "Ce préréglage de filtres sera définitivement supprimé.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                // Supprimer le favori
                this.state.favoritesFilters.splice(index, 1);

                // Sauvegarder l'état
                this.saveState();

                // Mettre à jour l'affichage
                const favoritesContainer = document.getElementById('favorites-filters');
                if (favoritesContainer) {
                    this.renderFavorites(favoritesContainer);
                }
            }
        });
    },

    /**
     * Affiche l'historique des recherches
     */
    renderHistory: function (container) {
        if (this.state.lastSearches.length === 0) {
            container.innerHTML = '<p class="text-gray-500 italic">Aucun historique de recherche</p>';
            return;
        }

        let html = '<div class="space-y-2">';
        this.state.lastSearches.slice(0, 5).forEach((search, index) => {
            const date = new Date(search.timestamp);
            const formattedDate = date.toLocaleDateString('fr-FR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });

            html += `
            <div class="bg-white border rounded-lg p-2 flex justify-between items-center">
                <div>
                    <p class="text-xs text-gray-500">${formattedDate}</p>
                    <p class="text-sm">${this.formatFilterDescription(search.filters)}</p>
                </div>
                <div class="flex space-x-1">
                    <button class="text-blue-600 hover:text-blue-800 p-1" data-apply-history="${index}">
                        <span class="material-icons text-sm">restore</span>
                    </button>
                </div>
            </div>`;
        });
        html += '</div>';

        container.innerHTML = html;

        // Ajouter les événements
        container.querySelectorAll('button[data-apply-history]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.currentTarget.dataset.applyHistory);
                this.applyHistorySearch(index);
            });
        });
    },

    /**
     * Applique une recherche de l'historique
     */
    applyHistorySearch: function (index) {
        if (!this.state.lastSearches[index]) return;

        const filters = this.state.lastSearches[index].filters;
        this.restoreFilters(filters);
    },

    /**
     * Ajoute une recherche à l'historique
     */
    addToHistory: function (filters) {
        // Limiter à 10 entrées dans l'historique
        this.state.lastSearches.unshift({
            filters: filters,
            timestamp: new Date().toISOString()
        });

        if (this.state.lastSearches.length > 10) {
            this.state.lastSearches.pop();
        }

        // Mettre à jour l'affichage si nécessaire
        const historyContainer = document.getElementById('history-filters');
        if (historyContainer) {
            this.renderHistory(historyContainer);
        }

        // Sauvegarder l'état
        this.saveState();
    },

    /**
     * Restaure les filtres à partir d'un objet de filtres
     */
    restoreFilters: function (filters) {
        // Réinitialiser d'abord
        this.resetFilters(false);

        // Restaurer chaque filtre
        if (filters.orderNumber) {
            document.getElementById('order-number-filter').value = filters.orderNumber;
        }

        if (filters.supplier) {
            document.getElementById('supplier-filter-orders').value = filters.supplier;
        }

        if (filters.minAmount) {
            document.getElementById('amount-min').value = filters.minAmount;
        }

        if (filters.maxAmount) {
            document.getElementById('amount-max').value = filters.maxAmount;
        }

        if (filters.dateStart) {
            document.getElementById('date-start-filter').value = filters.dateStart;
        }

        if (filters.dateEnd) {
            document.getElementById('date-end-filter').value = filters.dateEnd;
        }

        if (filters.status) {
            document.getElementById('status-filter-orders').value = filters.status;
        }

        if (filters.type) {
            document.getElementById('type-filter-orders').value = filters.type;
        }

        if (filters.project) {
            document.getElementById('project-filter-orders').value = filters.project;
        }

        if (filters.createdBy) {
            document.getElementById('created-by-filter').value = filters.createdBy;
        }

        // Filtres multi-select
        if (filters.multiSuppliers) {
            const suppliers = filters.multiSuppliers.split(', ');
            document.querySelectorAll('.supplier-checkbox').forEach(cb => {
                cb.checked = suppliers.includes(cb.value);
            });
        }

        // Appliquer les filtres
        this.applyFilters();
    },

    /**
     * Applique tous les filtres - MISE À JOUR AVEC GESTION DES REJETS
     */
    applyFilters: function () {
        // Réinitialiser les filtres
        jQuery.fn.dataTable.ext.search = [];

        // Récupérer les filtres actifs
        const filters = this.getActiveFilters();

        // Sauvegarder dans l'état
        this.state.activeFilters = filters;
        this.saveState();

        // Ajouter à l'historique si des filtres sont actifs
        if (Object.keys(filters).length > 0) {
            this.addToHistory(filters);
        }

        // Filtre par numéro de commande
        if (filters.orderNumber) {
            this.table.column(0).search(filters.orderNumber);
        } else {
            this.table.column(0).search('');
        }

        // Filtre par fournisseur
        if (filters.supplier) {
            this.table.column(4).search(filters.supplier);
        } else {
            this.table.column(4).search('');
        }

        // Filtre par créateur
        if (filters.createdBy) {
            this.table.column(7).search(filters.createdBy);
        } else {
            this.table.column(7).search('');
        }

        // Filtre par projet
        if (filters.project) {
            this.table.column(3).search(filters.project);
        } else {
            this.table.column(3).search('');
        }

        // Filtres personnalisés
        this.applyCustomFilters(filters);

        // Redessiner
        this.table.draw();

        // Mettre à jour les statistiques
        this.updateStatistics();

        // Mettre à jour le panneau des filtres actifs
        this.createActiveFiltersPanel();
    },

    /**
     * Applique les filtres personnalisés - MISE À JOUR AVEC REJETS
     */
    applyCustomFilters: function (filters) {
        // Filtre de montant
        const minAmount = filters.minAmount;
        const maxAmount = filters.maxAmount;

        // Filtre multi-fournisseurs
        const multiSuppliers = filters.multiSuppliers ? filters.multiSuppliers.split(', ') : [];

        jQuery.fn.dataTable.ext.search.push(
            function (settings, data, dataIndex) {
                if (settings.nTable.id !== 'purchaseOrdersTable') {
                    return true;
                }

                // Vérifier que la ligne a toutes les colonnes
                if (!data || data.length < 9) {
                    return false;
                }

                // Montant (colonne 5)
                const amountStr = data[5];
                if (minAmount || maxAmount) {
                    if (!amountStr) {
                        return false;
                    }
                    const amount = parseFloat(amountStr.replace(/[^\d,-]/g, '').replace(',', '.'));

                    if (minAmount && amount < parseFloat(minAmount)) {
                        return false;
                    }

                    if (maxAmount && amount > parseFloat(maxAmount)) {
                        return false;
                    }
                }

                // Fournisseurs multiples
                if (multiSuppliers.length > 0) {
                    const fournisseur = data[4];
                    if (!fournisseur || !multiSuppliers.includes(fournisseur)) {
                        return false;
                    }
                }

                // Statut (colonne 8) - MISE À JOUR AVEC REJETS
                const statusFilter = filters.status;
                if (statusFilter) {
                    const status = data[8];
                    if (!status) {
                        return false;
                    }
                    if (statusFilter === 'validated' && !status.includes('Validé')) {
                        return false;
                    }
                    if (statusFilter === 'pending' && !status.includes('attente')) {
                        return false;
                    }
                    // NOUVEAU : Filtre pour les rejets
                    if (statusFilter === 'rejected' && !status.includes('Rejeté')) {
                        return false;
                    }
                }

                // Type (colonne 6)
                const typeFilter = filters.type;
                if (typeFilter) {
                    const type = data[6];
                    if (!type) {
                        return false;
                    }
                    if (typeFilter === 'multi' && !type.includes('Multi')) {
                        return false;
                    }
                    if (typeFilter === 'single' && !type.includes('unique')) {
                        return false;
                    }
                }

                // Date avancée (colonne 2)
                if (filters.dateStart || filters.dateEnd) {
                    const dateStr = data[2];
                    if (!dateStr || dateStr === '') {
                        return false;
                    }

                    try {
                        // Format français: dd/mm/yyyy
                        const parts = dateStr.split('/');
                        const day = parseInt(parts[0], 10);
                        const month = parseInt(parts[1], 10) - 1;
                        const year = parseInt(parts[2], 10);
                        const rowDate = new Date(year, month, day);

                        if (filters.dateStart) {
                            const startDate = new Date(filters.dateStart);
                            if (rowDate < startDate) return false;
                        }

                        if (filters.dateEnd) {
                            const endDate = new Date(filters.dateEnd);
                            // Ajouter un jour pour inclure la date de fin
                            endDate.setDate(endDate.getDate() + 1);
                            if (rowDate > endDate) return false;
                        }
                    } catch (e) {
                        console.error('Erreur de parsing de date:', e);
                        return false;
                    }
                }

                return true;
            }
        );
    },

    /**
     * Applique le filtre de période prédéfinie
     */
    applyDateRangeFilter: function (range) {
        // D'abord, supprimer les filtres de date existants
        jQuery.fn.dataTable.ext.search = jQuery.fn.dataTable.ext.search.filter(function (fn) {
            return fn.name !== 'dateRangeFilter';
        });

        if (!range || range === '') {
            // Si aucune période sélectionnée, redessiner sans filtre
            this.table.draw();
            this.updateStatistics();
            return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0); // Réinitialiser l'heure à minuit
        let startDate, endDate;

        switch (range) {
            case 'today':
                startDate = new Date(today);
                endDate = new Date(today);
                endDate.setHours(23, 59, 59, 999);
                break;
            case 'week':
                startDate = new Date(today);
                startDate.setDate(today.getDate() - 7);
                endDate = new Date();
                endDate.setHours(23, 59, 59, 999);
                break;
            case 'month':
                startDate = new Date(today);
                startDate.setDate(today.getDate() - 30);
                endDate = new Date();
                endDate.setHours(23, 59, 59, 999);
                break;
            case 'quarter':
                startDate = new Date(today);
                startDate.setMonth(today.getMonth() - 3);
                endDate = new Date();
                endDate.setHours(23, 59, 59, 999);
                break;
            case 'year':
                startDate = new Date(today.getFullYear(), 0, 1); // 1er janvier de l'année en cours
                endDate = new Date();
                endDate.setHours(23, 59, 59, 999);
                break;
            default:
                startDate = null;
                endDate = null;
        }

        if (!startDate || !endDate) {
            this.table.draw();
            this.updateStatistics();
            return;
        }

        // Fonction de filtre avec un nom pour pouvoir la supprimer
        const dateRangeFilter = function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'purchaseOrdersTable') {
                return true;
            }

            // Vérifier que la ligne a toutes les colonnes
            if (!data || data.length < 9) {
                return false;
            }

            // Date (colonne 2) - format français dd/mm/yyyy
            const dateStr = data[2];
            if (!dateStr || dateStr === '' || dateStr === 'N/A') {
                return false;
            }

            // Parser la date française
            let rowDate;
            try {
                // Format: dd/mm/yyyy ou dd/mm/yyyy hh:mm
                const datePart = dateStr.split(' ')[0]; // Prendre seulement la partie date
                const parts = datePart.split('/');

                if (parts.length !== 3) {
                    console.warn('Format de date invalide:', dateStr);
                    return false;
                }

                const day = parseInt(parts[0], 10);
                const month = parseInt(parts[1], 10) - 1; // Les mois sont 0-indexés en JavaScript
                const year = parseInt(parts[2], 10);

                rowDate = new Date(year, month, day);

                // Vérifier que la date est valide
                if (isNaN(rowDate.getTime())) {
                    console.warn('Date invalide:', dateStr);
                    return false;
                }
            } catch (e) {
                console.error('Erreur lors du parsing de la date:', dateStr, e);
                return false;
            }

            // Comparer les dates
            const result = rowDate >= startDate && rowDate <= endDate;

            return result;
        };

        // Donner un nom à la fonction pour pouvoir la retrouver
        dateRangeFilter.name = 'dateRangeFilter';

        // Ajouter le filtre
        jQuery.fn.dataTable.ext.search.push(dateRangeFilter);

        // Redessiner le tableau
        this.table.draw();

        // Mettre à jour les statistiques
        this.updateStatistics();

        // Mettre à jour le panneau des filtres actifs
        this.createActiveFiltersPanel();
    },

    /**
     * Réinitialise tous les filtres
     */
    resetFilters: function (updateTable = true) {
        // Réinitialiser le formulaire
        const form = document.getElementById(this.config.filterFormId);
        if (form) {
            form.reset();
        }

        // Réinitialiser DataTables
        this.table.search('');
        this.table.columns().search('');

        // Réinitialiser les filtres multi-select
        document.querySelectorAll('.supplier-checkbox').forEach(cb => {
            cb.checked = false;
        });

        // Supprimer uniquement les filtres personnalisés, pas tous les filtres
        jQuery.fn.dataTable.ext.search = jQuery.fn.dataTable.ext.search.filter(function (fn) {
            return fn.name !== 'dateRangeFilter' && fn.name !== 'customFilters';
        });

        // Réinitialiser l'état
        this.state.activeFilters = {};
        this.saveState();

        if (updateTable) {
            // Redessiner
            this.table.draw();

            // Mettre à jour les statistiques
            this.updateStatistics();

            // Mettre à jour le panneau des filtres actifs
            this.createActiveFiltersPanel();
        }
    },

    /**
     * Met à jour les statistiques en temps réel - MISE À JOUR AVEC REJETS
     */
    updateStatistics: function () {
        const data = this.table.rows({ search: 'applied' }).data();

        let totalAmount = 0;
        let validatedCount = 0;
        let pendingCount = 0;
        let rejectedCount = 0; // NOUVEAU
        let multiProjectCount = 0;

        data.each(function (row) {
            // Vérifier que la ligne a bien toutes les colonnes nécessaires
            if (!row || row.length < 9) {
                console.warn('Ligne incomplète:', row);
                return;
            }

            // Montant (colonne 5)
            const amountStr = row[5];
            if (amountStr) {
                const amount = parseFloat(amountStr.replace(/[^\d,-]/g, '').replace(',', '.'));
                if (!isNaN(amount)) {
                    totalAmount += amount;
                }
            }

            // Statut (colonne 8) - MISE À JOUR AVEC REJETS
            const status = row[8];
            if (status) {
                if (status.includes('Rejeté')) {
                    rejectedCount++;
                } else if (status.includes('Validé')) {
                    validatedCount++;
                } else if (status.includes('attente')) {
                    pendingCount++;
                }
            }

            // Multi-projets (colonne 6)
            const type = row[6];
            if (type && type.includes('Multi')) {
                multiProjectCount++;
            }
        });

        // Mettre à jour l'affichage
        const totalElement = document.getElementById('filtered-total');
        if (totalElement) {
            totalElement.textContent = data.length;
        }

        const amountElement = document.getElementById('filtered-amount');
        if (amountElement) {
            amountElement.textContent = new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'XOF',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(totalAmount);
        }

        const validatedElement = document.getElementById('filtered-validated');
        if (validatedElement) {
            validatedElement.textContent = validatedCount;
        }

        const pendingElement = document.getElementById('filtered-pending');
        if (pendingElement) {
            pendingElement.textContent = pendingCount;
        }

        // NOUVEAU : Élément pour les rejets
        const rejectedElement = document.getElementById('filtered-rejected');
        if (rejectedElement) {
            rejectedElement.textContent = rejectedCount;
        }

        const multiElement = document.getElementById('filtered-multi');
        if (multiElement) {
            multiElement.textContent = multiProjectCount;
        }
    },

    /**
     * Exporte les résultats filtrés
     */
    exportFilteredResults: function (format) {
        const data = this.table.rows({ search: 'applied' }).data();

        if (data.length === 0) {
            Swal.fire({
                title: 'Aucune donnée',
                text: 'Aucune donnée à exporter avec les filtres actuels.',
                icon: 'warning'
            });
            return;
        }

        switch (format) {
            case 'csv':
                this.exportCSV(data);
                break;
            case 'excel':
                // Utiliser la fonctionnalité d'export Excel de DataTables si disponible
                if (jQuery.fn.dataTable.ext.buttons.excel) {
                    jQuery.fn.dataTable.ext.buttons.excel.action(null, this.table, null);
                } else {
                    // Fallback sur CSV si l'export Excel n'est pas disponible
                    this.exportCSV(data);
                }
                break;
            case 'pdf':
                // Utiliser la fonctionnalité d'export PDF de DataTables si disponible
                if (jQuery.fn.dataTable.ext.buttons.pdf) {
                    jQuery.fn.dataTable.ext.buttons.pdf.action(null, this.table, null);
                } else {
                    Swal.fire({
                        title: 'Fonctionnalité non disponible',
                        text: 'L\'export PDF n\'est pas pris en charge. Veuillez utiliser l\'export CSV.',
                        icon: 'info'
                    });
                }
                break;
        }
    },

    /**
     * Exporte les données filtrées en CSV - MISE À JOUR AVEC REJETS
     */
    exportCSV: function (data) {
        // En-têtes du CSV - MISE À JOUR
        let csv = 'N° Bon;Référence;Date;Projet(s);Fournisseur;Montant;Type;Créé par;État\n';

        // Données
        data.each(function (row) {
            // Nettoyer les données pour le CSV
            const cleanRow = Array.from(row).map(cell => {
                // Supprimer les balises HTML
                if (typeof cell === 'string') {
                    const div = document.createElement('div');
                    div.innerHTML = cell;
                    cell = div.textContent.trim();

                    // Échapper les points-virgules pour éviter de casser le CSV
                    if (cell.includes(';')) {
                        cell = '"' + cell + '"';
                    }
                }
                return cell || '';
            });

            // Ajouter la ligne au CSV
            csv += cleanRow.slice(0, 9).join(';') + '\n';
        });

        // Télécharger le fichier CSV
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        const date = new Date().toISOString().slice(0, 10);
        link.setAttribute('href', url);
        link.setAttribute('download', `bons_commande_export_${date}.csv`);
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    },

    /**
     * Affiche les statistiques avancées - MISE À JOUR AVEC REJETS
     */
    showAdvancedStats: function () {
        const data = this.table.rows({ search: 'applied' }).data();

        if (data.length === 0) {
            Swal.fire({
                title: 'Aucune donnée',
                text: 'Aucune donnée disponible pour générer les statistiques.',
                icon: 'info'
            });
            return;
        }

        // Calculer les statistiques par fournisseur
        const supplierStats = {};
        const monthlyStats = {};
        const projectStats = {
            single: 0,
            multi: 0
        };
        const statusStats = {
            validated: 0,
            pending: 0,
            rejected: 0 // NOUVEAU
        };
        const userStats = {};

        // Calculer des métriques avancées
        let totalAmount = 0;
        let maxAmount = 0;
        let minAmount = Infinity;
        let avgAmount = 0;
        let medianAmounts = [];

        data.each(function (row) {
            // Vérifier que la ligne a toutes les colonnes nécessaires
            if (!row || row.length < 9) {
                console.warn('Ligne incomplète:', row);
                return;
            }

            // Fournisseur (colonne 4)
            const supplier = row[4] || 'Non spécifié';

            // Montant (colonne 5)
            const amountStr = row[5];
            let amount = 0;
            if (amountStr) {
                try {
                    amount = parseFloat(amountStr.replace(/[^\d,-]/g, '').replace(',', '.'));
                    if (isNaN(amount)) {
                        amount = 0;
                    }
                } catch (e) {
                    console.warn('Erreur lors du parsing du montant:', amountStr);
                    amount = 0;
                }

                // Statistiques globales
                totalAmount += amount;
                maxAmount = Math.max(maxAmount, amount);
                if (amount > 0) minAmount = Math.min(minAmount, amount);
                medianAmounts.push(amount);
            }

            // Date (colonne 2)
            const dateStr = row[2];
            let monthKey = 'Sans date';
            if (dateStr) {
                try {
                    const parts = dateStr.split('/');
                    if (parts.length >= 3) {
                        const month = parts[1];
                        const year = parts[2].split(' ')[0]; // Prendre seulement l'année si il y a l'heure
                        monthKey = `${month}/${year}`;
                    }
                } catch (e) {
                    console.warn('Erreur lors du parsing de la date:', dateStr);
                }
            }

            // Type (colonne 6)
            const type = row[6];
            if (type) {
                if (type.includes('Multi')) {
                    projectStats.multi++;
                } else if (type.includes('unique')) {
                    projectStats.single++;
                }
            }

            // Statut (colonne 8) - MISE À JOUR AVEC REJETS
            const status = row[8];
            if (status) {
                if (status.includes('Rejeté')) {
                    statusStats.rejected++;
                } else if (status.includes('Validé')) {
                    statusStats.validated++;
                } else if (status.includes('attente')) {
                    statusStats.pending++;
                }
            }

            // Utilisateur (colonne 7)
            const user = row[7] || 'Non spécifié';
            if (!userStats[user]) {
                userStats[user] = {
                    count: 0,
                    total: 0
                };
            }
            userStats[user].count++;
            userStats[user].total += amount;

            // Statistiques par fournisseur
            if (!supplierStats[supplier]) {
                supplierStats[supplier] = {
                    count: 0,
                    total: 0
                };
            }
            supplierStats[supplier].count++;
            supplierStats[supplier].total += amount;

            // Statistiques mensuelles
            if (!monthlyStats[monthKey]) {
                monthlyStats[monthKey] = {
                    count: 0,
                    total: 0
                };
            }
            monthlyStats[monthKey].count++;
            monthlyStats[monthKey].total += amount;
        });

        // Calculer la moyenne
        avgAmount = data.length > 0 ? totalAmount / data.length : 0;

        // Calculer la médiane
        let medianAmount = 0;
        if (medianAmounts.length > 0) {
            medianAmounts.sort((a, b) => a - b);
            const mid = Math.floor(medianAmounts.length / 2);
            medianAmount = medianAmounts.length % 2 === 0
                ? (medianAmounts[mid - 1] + medianAmounts[mid]) / 2
                : medianAmounts[mid];
        }

        // Créer le contenu HTML pour les statistiques
        let statsHtml = '<div class="space-y-6">';

        // Statistiques globales
        statsHtml += `
        <div>
            <h3 class="font-bold text-lg mb-3">Statistiques globales</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-blue-50 p-3 rounded-lg text-center">
                    <p class="text-sm text-gray-600">Montant total</p>
                    <p class="font-bold text-lg text-blue-600">${totalAmount.toLocaleString('fr-FR')} FCFA</p>
                </div>
                <div class="bg-green-50 p-3 rounded-lg text-center">
                    <p class="text-sm text-gray-600">Montant moyen</p>
                    <p class="font-bold text-lg text-green-600">${avgAmount.toLocaleString('fr-FR')} FCFA</p>
                </div>
                <div class="bg-purple-50 p-3 rounded-lg text-center">
                    <p class="text-sm text-gray-600">Montant médian</p>
                    <p class="font-bold text-lg text-purple-600">${medianAmount.toLocaleString('fr-FR')} FCFA</p>
                </div>
                <div class="bg-amber-50 p-3 rounded-lg text-center">
                    <p class="text-sm text-gray-600">Montant max</p>
                    <p class="font-bold text-lg text-amber-600">${maxAmount.toLocaleString('fr-FR')} FCFA</p>
                </div>
            </div>
        </div>
        `;

        // Top 5 fournisseurs
        const sortedSuppliers = Object.entries(supplierStats)
            .sort((a, b) => b[1].total - a[1].total)
            .slice(0, 5);

        statsHtml += '<div>';
        statsHtml += '<h3 class="font-bold text-lg mb-3">Top 5 Fournisseurs par montant</h3>';
        statsHtml += '<div class="bg-gray-50 rounded-lg p-4">';

        if (sortedSuppliers.length > 0) {
            statsHtml += '<div class="space-y-2">';
            sortedSuppliers.forEach(([supplier, stats], index) => {
                const formattedAmount = new Intl.NumberFormat('fr-FR', {
                    style: 'currency',
                    currency: 'XOF',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(stats.total);

                // Calcul du pourcentage
                const percentage = totalAmount > 0 ? (stats.total / totalAmount * 100).toFixed(1) : 0;

                statsHtml += `
                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <div>
                        <span class="font-medium">${index + 1}. ${supplier}</span>
                        <span class="text-sm text-gray-600 ml-2">(${stats.count} commandes)</span>
                    </div>
                    <div class="text-right">
                        <span class="font-bold text-blue-600">${formattedAmount}</span>
                        <span class="text-sm text-gray-600 ml-2">${percentage}%</span>
                    </div>
                </div>
            `;
            });
            statsHtml += '</div>';
        } else {
            statsHtml += '<p class="text-gray-500">Aucune donnée disponible</p>';
        }

        statsHtml += '</div></div>';

        // Top utilisateurs
        const sortedUsers = Object.entries(userStats)
            .sort((a, b) => b[1].count - a[1].count)
            .slice(0, 5);

        statsHtml += '<div>';
        statsHtml += '<h3 class="font-bold text-lg mb-3">Top 5 Utilisateurs par nombre de commandes</h3>';
        statsHtml += '<div class="bg-gray-50 rounded-lg p-4">';

        if (sortedUsers.length > 0) {
            statsHtml += '<div class="space-y-2">';
            sortedUsers.forEach(([user, stats], index) => {
                const formattedAmount = new Intl.NumberFormat('fr-FR', {
                    style: 'currency',
                    currency: 'XOF',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(stats.total);

                const percentage = data.length > 0 ? (stats.count / data.length * 100).toFixed(1) : 0;

                statsHtml += `
                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <div>
                        <span class="font-medium">${index + 1}. ${user}</span>
                        <span class="text-sm text-gray-600 ml-2">(${stats.count} commandes - ${percentage}%)</span>
                    </div>
                    <span class="font-bold text-green-600">${formattedAmount}</span>
                </div>`;
            });
            statsHtml += '</div>';
        } else {
            statsHtml += '<p class="text-gray-500">Aucune donnée disponible</p>';
        }

        statsHtml += '</div></div>';

        // Statistiques par type de projet
        statsHtml += '<div>';
        statsHtml += '<h3 class="font-bold text-lg mb-3">Répartition par type de commande</h3>';

        const totalProjects = projectStats.single + projectStats.multi;
        const singlePercentage = totalProjects > 0 ? (projectStats.single / totalProjects * 100).toFixed(1) : 0;
        const multiPercentage = totalProjects > 0 ? (projectStats.multi / totalProjects * 100).toFixed(1) : 0;

        statsHtml += '<div class="grid grid-cols-2 gap-4">';
        statsHtml += `
        <div class="bg-blue-50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-blue-600">${projectStats.single}</div>
            <div class="text-lg text-blue-800">${singlePercentage}%</div>
            <div class="text-sm text-gray-600">Projets uniques</div>
        </div>
        <div class="bg-purple-50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-purple-600">${projectStats.multi}</div>
            <div class="text-lg text-purple-800">${multiPercentage}%</div>
            <div class="text-sm text-gray-600">Multi-projets</div>
        </div>
    `;
        statsHtml += '</div></div>';

        // Statistiques par statut - MISE À JOUR AVEC REJETS
        statsHtml += '<div>';
        statsHtml += '<h3 class="font-bold text-lg mb-3">Répartition par statut de validation</h3>';

        const totalStatus = statusStats.validated + statusStats.pending + statusStats.rejected;
        const validatedPercentage = totalStatus > 0 ? (statusStats.validated / totalStatus * 100).toFixed(1) : 0;
        const pendingPercentage = totalStatus > 0 ? (statusStats.pending / totalStatus * 100).toFixed(1) : 0;
        const rejectedPercentage = totalStatus > 0 ? (statusStats.rejected / totalStatus * 100).toFixed(1) : 0;

        statsHtml += '<div class="grid grid-cols-3 gap-4">';
        statsHtml += `
        <div class="bg-green-50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-green-600">${statusStats.validated}</div>
            <div class="text-lg text-green-800">${validatedPercentage}%</div>
            <div class="text-sm text-gray-600">Validés</div>
        </div>
        <div class="bg-yellow-50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-yellow-600">${statusStats.pending}</div>
            <div class="text-lg text-yellow-800">${pendingPercentage}%</div>
            <div class="text-sm text-gray-600">En attente</div>
        </div>
        <div class="bg-red-50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-red-600">${statusStats.rejected}</div>
            <div class="text-lg text-red-800">${rejectedPercentage}%</div>
            <div class="text-sm text-gray-600">Rejetés</div>
        </div>
    `;
        statsHtml += '</div></div>';

        // Top 5 mois
        const sortedMonths = Object.entries(monthlyStats)
            .sort((a, b) => b[1].total - a[1].total)
            .slice(0, 5);

        statsHtml += '<div>';
        statsHtml += '<h3 class="font-bold text-lg mb-3">Top 5 Mois par montant</h3>';
        statsHtml += '<div class="bg-gray-50 rounded-lg p-4">';

        if (sortedMonths.length > 0) {
            statsHtml += '<div class="space-y-2">';
            sortedMonths.forEach(([month, stats], index) => {
                const formattedAmount = new Intl.NumberFormat('fr-FR', {
                    style: 'currency',
                    currency: 'XOF',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(stats.total);

                const percentage = totalAmount > 0 ? (stats.total / totalAmount * 100).toFixed(1) : 0;

                statsHtml += `
                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <div>
                        <span class="font-medium">${month}</span>
                        <span class="text-sm text-gray-600 ml-2">(${stats.count} commandes)</span>
                    </div>
                    <div class="text-right">
                        <span class="font-bold text-green-600">${formattedAmount}</span>
                        <span class="text-sm text-gray-600 ml-2">${percentage}%</span>
                    </div>
                </div>
            `;
            });
            statsHtml += '</div>';
        } else {
            statsHtml += '<p class="text-gray-500">Aucune donnée disponible</p>';
        }

        statsHtml += '</div></div>';

        statsHtml += '</div>';

        // Afficher dans un modal
        Swal.fire({
            title: 'Statistiques Avancées',
            html: statsHtml,
            width: 800,
            confirmButtonText: 'Fermer',
            customClass: {
                popup: 'rounded-lg',
                title: 'text-2xl font-bold text-center',
                htmlContainer: 'text-left overflow-auto max-h-[70vh]'
            }
        });
    }
};

// Export des fonctions pour utilisation globale
window.CustomFilters = CustomFilters;