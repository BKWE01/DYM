/**
 * Module de gestion des filtres avancés pour les matériaux
 * Ce script gère l'interface et la logique des filtres avancés
 * pour le tableau des matériaux en attente de commande.
 * 
 * @author DYM Manufacture
 * @version 1.0
 * @date Mai 2025
 */

// Namespace pour le module de filtres avancés
const AdvancedFilters = {
    // Configuration
    config: {
        tableId: 'pendingMaterialsTable',
        filterFormId: 'advanced-filters-form',
        toggleButtonId: 'toggle-filters-btn',
        resetButtonId: 'reset-filters-btn',
        applyButtonId: 'apply-filters-btn',
        filterContainerId: 'advanced-filters-container',
        savedFiltersKey: 'dym_pending_materials_filters',
        animation: {
            duration: 300 // ms
        }
    },

    // Référence à l'instance DataTable
    dataTable: null,

    // État des filtres
    filters: {
        projet: '',
        client: '',
        produit: '',
        fournisseur: '',
        statut: '',
        dateDebut: '',
        dateFin: '',
        unite: '',
        source: ''
    },

    /**
     * Initialise le module de filtres avancés
     */
    init: function () {
        console.log('Initialisation des filtres avancés...');

        // Attendre que le DOM soit complètement chargé
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    },

    /**
     * Configure le module une fois le DOM chargé
     */
    setup: function () {
        // Obtenir la référence à DataTables (attendre qu'elle soit initialisée)
        this.waitForDataTable(() => {
            // Référencer DataTable
            this.dataTable = jQuery('#' + this.config.tableId).DataTable();

            // Configurer les événements UI
            this.setupUIEvents();

            // Charger les filtres sauvegardés
            this.loadSavedFilters();

            // Initialiser les sélecteurs dynamiques
            this.initDynamicSelectors();

            console.log('Filtres avancés initialisés avec succès');
        });
    },

    /**
     * Attend l'initialisation de DataTable
     * @param {Function} callback - Fonction à exécuter quand DataTable est prêt
     */
    waitForDataTable: function (callback) {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
            setTimeout(() => this.waitForDataTable(callback), 100);
            return;
        }

        if (jQuery.fn.DataTable.isDataTable('#' + this.config.tableId)) {
            callback();
        } else {
            setTimeout(() => this.waitForDataTable(callback), 100);
        }
    },

    /**
     * Configure les événements de l'interface utilisateur
     */
    setupUIEvents: function () {

        // Exportation des résultats filtrés
        const exportBtn = document.getElementById('export-filtered-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportFilteredResults());
        }

        // Toggle du panneau de filtres
        const toggleBtn = document.getElementById(this.config.toggleButtonId);
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleFilterPanel());
        }

        // Réinitialisation des filtres
        const resetBtn = document.getElementById(this.config.resetButtonId);
        if (resetBtn) {
            resetBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.resetFilters();
            });
        }

        // Application des filtres
        const applyBtn = document.getElementById(this.config.applyButtonId);
        if (applyBtn) {
            applyBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.applyFilters();
            });
        }

        // Soumission du formulaire
        const form = document.getElementById(this.config.filterFormId);
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.applyFilters();
            });
        }

        // Gestion de l'affichage initial du panneau de filtres
        const filterContainer = document.getElementById(this.config.filterContainerId);
        if (filterContainer) {
            // Afficher si des filtres sont actifs
            if (this.hasActiveFilters()) {
                filterContainer.classList.remove('hidden');
            }

            // Ajouter une classe pour les animations
            filterContainer.classList.add('transition-all', 'duration-300');
        }
    },

    /**
 * Exporte les résultats filtrés au format CSV
 */
    exportFilteredResults: function () {
        if (!this.dataTable) return;

        // Récupérer les données filtrées
        const data = this.dataTable.rows({ search: 'applied' }).data();

        if (data.length === 0) {
            // Afficher une alerte si aucune donnée
            Swal.fire({
                title: 'Aucune donnée',
                text: 'Aucune donnée à exporter avec les filtres actuels.',
                icon: 'warning'
            });
            return;
        }

        // Générer le contenu CSV
        let csv = 'Projet;Client;Produit;Quantité;Unité;Statut;Fournisseur;Date\n';

        data.each(function (row) {
            // Échapper les valeurs qui contiennent des points-virgules
            const escapedRow = Array.from(row).map(value => {
                if (typeof value === 'string' && value.includes(';')) {
                    return `"${value}"`;
                }
                return value;
            });

            // N'ajouter que les colonnes visibles (exclure les boutons d'action)
            csv += `${escapedRow[1]};${escapedRow[2]};${escapedRow[3]};${escapedRow[4]};${escapedRow[5]};${escapedRow[6]};${escapedRow[7]};${escapedRow[8]}\n`;
        });

        // Créer un blob avec le contenu CSV
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });

        // Créer un lien pour télécharger le fichier
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        // Configurer et déclencher le téléchargement
        link.setAttribute('href', url);
        link.setAttribute('download', `materiaux_en_attente_${new Date().toISOString().slice(0, 10)}.csv`);
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    },

    /**
     * Alterne l'affichage du panneau de filtres
     */
    toggleFilterPanel: function () {
        const filterContainer = document.getElementById(this.config.filterContainerId);
        const toggleBtn = document.getElementById(this.config.toggleButtonId);

        if (filterContainer) {
            filterContainer.classList.toggle('hidden');

            // Mise à jour de l'icône du bouton
            if (toggleBtn) {
                if (filterContainer.classList.contains('hidden')) {
                    toggleBtn.innerHTML = '<span class="material-icons mr-1">filter_list</span>Afficher les filtres';
                } else {
                    toggleBtn.innerHTML = '<span class="material-icons mr-1">filter_list_off</span>Masquer les filtres';
                }
            }
        }
    },

    /**
     * Charge les filtres sauvegardés dans le stockage local
     */
    loadSavedFilters: function () {
        try {
            const savedFilters = localStorage.getItem(this.config.savedFiltersKey);
            if (savedFilters) {
                const parsedFilters = JSON.parse(savedFilters);
                this.filters = { ...this.filters, ...parsedFilters };

                // Appliquer les filtres sauvegardés à l'interface
                this.updateFilterUI();

                // Appliquer les filtres au tableau
                this.applyFilters(false);
            }
        } catch (error) {
            console.error('Erreur lors du chargement des filtres sauvegardés:', error);
            // En cas d'erreur, supprimer les filtres corrompus
            localStorage.removeItem(this.config.savedFiltersKey);
        }
    },

    /**
     * Sauvegarde les filtres dans le stockage local
     */
    saveFilters: function () {
        try {
            localStorage.setItem(this.config.savedFiltersKey, JSON.stringify(this.filters));
        } catch (error) {
            console.error('Erreur lors de la sauvegarde des filtres:', error);
        }
    },

    /**
     * Met à jour l'interface utilisateur avec les valeurs des filtres
     */
    updateFilterUI: function () {
        const form = document.getElementById(this.config.filterFormId);
        if (!form) return;

        // Pour chaque filtre, mettre à jour l'élément correspondant
        Object.entries(this.filters).forEach(([key, value]) => {
            const element = form.elements[key];
            if (element) {
                element.value = value;
            }
        });
    },

    /**
     * Initialise les sélecteurs dynamiques basés sur les données actuelles
     */
    initDynamicSelectors: function () {
        if (!this.dataTable) return;

        const data = this.dataTable.data();

        // Extraire les valeurs uniques pour les sélecteurs
        const projets = new Set();
        const clients = new Set();
        const unites = new Set();
        const fournisseurs = new Set();

        data.each(function (item) {
            projets.add(item[1]); // Colonne Projet
            clients.add(item[2]); // Colonne Client

            if (item[5]) {
                unites.add(item[5]); // Colonne Unité
            }

            // Version améliorée pour extraire uniquement le nom du fournisseur
            if (item[7]) {
                // Créer un élément temporaire pour extraire le texte brut
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = item[7];

                // Si nous avons une structure avec un élément de classe 'text-gray-500'
                // (qui contient généralement le nom du fournisseur principal)
                const supplierElement = tempDiv.querySelector('.text-gray-500');
                if (supplierElement) {
                    fournisseurs.add(supplierElement.textContent.trim());
                } else {
                    // Sinon, prendre le premier nœud de texte non vide
                    const textNodes = Array.from(tempDiv.childNodes)
                        .filter(node => node.nodeType === 3 && node.textContent.trim());
                    if (textNodes.length > 0) {
                        fournisseurs.add(textNodes[0].textContent.trim());
                    } else {
                        // Fallback - prendre tout le texte, mais nettoyer les espaces multiples
                        const cleanText = tempDiv.textContent.replace(/\s+/g, ' ').trim();
                        fournisseurs.add(cleanText);
                    }
                }
            }
        });

        // Remplir les sélecteurs
        this.populateSelector('projet', Array.from(projets).sort());
        this.populateSelector('client', Array.from(clients).sort());
        this.populateSelector('unite', Array.from(unites).sort());
        this.populateSelector('fournisseur', Array.from(fournisseurs).filter(f => f !== '-').sort());
    },

    /**
     * Remplit un sélecteur avec les options données
     * @param {string} selectName - Nom du sélecteur
     * @param {Array} options - Options à ajouter
     */
    populateSelector: function (selectName, options) {
        const select = document.querySelector(`#${this.config.filterFormId} select[name="${selectName}"]`);
        if (!select) return;

        // Sauvegarder l'option vide actuelle
        const emptyOption = select.querySelector('option[value=""]');

        // Vider le sélecteur sauf l'option vide
        select.innerHTML = '';
        if (emptyOption) {
            select.appendChild(emptyOption);
        }

        // Ajouter les nouvelles options
        options.forEach(option => {
            if (option && typeof option === 'string' && option.trim() !== '') {
                const optElement = document.createElement('option');
                optElement.value = option;

                // Extraire uniquement le texte si l'option contient du HTML
                if (option.includes('<')) {
                    // Créer un élément temporaire pour extraire le texte
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = option;
                    optElement.textContent = tempDiv.textContent || option;
                } else {
                    optElement.textContent = option;
                }

                select.appendChild(optElement);
            }
        });
    },

    /**
     * Applique les filtres au tableau DataTable
     * @param {boolean} saveFilters - Si vrai, sauvegarde les filtres
     */
    applyFilters: function (saveFilters = true) {
        if (!this.dataTable) return;

        // Récupérer les valeurs des filtres depuis le formulaire
        const form = document.getElementById(this.config.filterFormId);
        if (form) {
            Object.keys(this.filters).forEach(key => {
                const element = form.elements[key];
                if (element) {
                    this.filters[key] = element.value;
                }
            });
        }

        // Sauvegarder les filtres si demandé
        if (saveFilters) {
            this.saveFilters();
        }

        // Effacer les filtres actuels
        this.dataTable.search('').columns().search('');

        // Appliquer les filtres de colonne
        if (this.filters.projet) {
            this.dataTable.column(1).search(this.filters.projet, false, false);
        }

        if (this.filters.client) {
            this.dataTable.column(2).search(this.filters.client, false, false);
        }

        if (this.filters.produit) {
            this.dataTable.column(3).search(this.filters.produit, true, false);
        }

        if (this.filters.fournisseur) {
            this.dataTable.column(7).search(this.filters.fournisseur, false, false);
        }

        if (this.filters.unite) {
            this.dataTable.column(5).search(this.filters.unite, false, false);
        }

        // Filtres complexes
        this.applyComplexFilters();

        // Redessiner le tableau
        this.dataTable.draw();

        // Mettre à jour l'affichage du compteur de résultats
        this.updateResultsCounter();

        // Mise à jour du bouton de filtres (indication visuelle)
        this.updateFilterButton();
    },

    /**
     * Applique les filtres complexes (date, statut, source)
     */
    applyComplexFilters: function () {
        // Supprimer les filtres existants
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(
            f => f.name !== 'advancedFilters'
        );

        // Ajouter notre filtre personnalisé
        const self = this;
        const filterFunction = function (settings, data, dataIndex) {
            // Vérifier que nous filtrons le bon tableau
            if (settings.nTable.id !== self.config.tableId) {
                return true;
            }

            // Filtre de date
            if (self.filters.dateDebut || self.filters.dateFin) {
                const dateStr = data[8]; // Colonne Date
                if (!dateStr) return false;

                try {
                    // Parser la date (format dd/mm/yyyy)
                    const parts = dateStr.split('/');
                    const rowDate = new Date(parts[2], parts[1] - 1, parts[0]);

                    if (self.filters.dateDebut) {
                        const startDate = new Date(self.filters.dateDebut);
                        if (rowDate < startDate) return false;
                    }

                    if (self.filters.dateFin) {
                        const endDate = new Date(self.filters.dateFin);
                        endDate.setHours(23, 59, 59, 999); // Fin de journée
                        if (rowDate > endDate) return false;
                    }
                } catch (e) {
                    console.warn('Erreur lors du filtrage par date:', e);
                }
            }

            // Filtre de statut
            if (self.filters.statut) {
                const statusCol = data[6]; // Colonne Statut
                if (!statusCol) return false;

                if (self.filters.statut === 'en_attente' && !statusCol.includes('En attente')) {
                    return false;
                }
            }

            // Filtre de source (expression_dym ou besoins)
            if (self.filters.source) {
                // Le champ source est caché dans une colonne data-*
                // Nous devrons l'extraire du DOM directement
                const row = self.dataTable.row(dataIndex).node();
                if (!row) return true;

                const sourceEl = row.querySelector('.source-indicator');
                if (!sourceEl) return true;

                const source = sourceEl.dataset.source || '';
                if (self.filters.source !== source) {
                    return false;
                }
            }

            return true;
        };

        // Nommer notre fonction de filtre pour pouvoir la retrouver plus tard
        filterFunction.name = 'advancedFilters';

        // Ajouter le filtre à DataTables
        $.fn.dataTable.ext.search.push(filterFunction);
    },

    /**
     * Réinitialise tous les filtres
     */
    resetFilters: function () {
        // Réinitialiser les valeurs de filtres
        Object.keys(this.filters).forEach(key => {
            this.filters[key] = '';
        });

        // Réinitialiser le formulaire
        const form = document.getElementById(this.config.filterFormId);
        if (form) {
            form.reset();
        }

        // Supprimer les filtres de DataTables
        this.dataTable.search('').columns().search('');
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(
            f => f.name !== 'advancedFilters'
        );

        // Redessiner le tableau
        this.dataTable.draw();

        // Mettre à jour l'affichage du compteur
        this.updateResultsCounter();

        // Supprimer les filtres sauvegardés
        localStorage.removeItem(this.config.savedFiltersKey);

        // Mise à jour du bouton de filtres
        this.updateFilterButton(false);
    },

    /**
     * Met à jour le compteur de résultats filtrés
     */
    updateResultsCounter: function () {
        const info = this.dataTable.page.info();
        const counterEl = document.getElementById('filtered-results-counter');

        if (counterEl) {
            const totalCount = info.recordsTotal;
            const filteredCount = info.recordsDisplay;

            if (filteredCount < totalCount) {
                counterEl.textContent = `${filteredCount} résultat(s) sur ${totalCount}`;
                counterEl.classList.remove('hidden');
            } else {
                counterEl.classList.add('hidden');
            }
        }
    },

    /**
     * Vérifie si des filtres sont actifs
     * @returns {boolean} - Vrai si au moins un filtre est actif
     */
    hasActiveFilters: function () {
        return Object.values(this.filters).some(value => value !== '');
    },

    /**
     * Met à jour l'apparence du bouton de filtres
     * @param {boolean} checkFilters - Si vrai, vérifie l'état des filtres
     */
    updateFilterButton: function (checkFilters = true) {
        const toggleBtn = document.getElementById(this.config.toggleButtonId);
        if (!toggleBtn) return;

        const hasFilters = checkFilters ? this.hasActiveFilters() : false;

        // Ajouter un indicateur visuel si des filtres sont actifs
        if (hasFilters) {
            toggleBtn.classList.add('bg-blue-100', 'text-blue-800');
            toggleBtn.classList.remove('bg-gray-100', 'text-gray-700');

            // Ajouter un badge avec le nombre de filtres actifs
            const count = Object.values(this.filters).filter(v => v !== '').length;
            toggleBtn.innerHTML = `<span class="material-icons mr-1">filter_list</span>Filtres (${count})`;
        } else {
            toggleBtn.classList.remove('bg-blue-100', 'text-blue-800');
            toggleBtn.classList.add('bg-gray-100', 'text-gray-700');
            toggleBtn.innerHTML = '<span class="material-icons mr-1">filter_list</span>Filtres';
        }
    }
};

// Initialiser les filtres avancés au chargement de la page
document.addEventListener('DOMContentLoaded', function () {
    AdvancedFilters.init();
});

