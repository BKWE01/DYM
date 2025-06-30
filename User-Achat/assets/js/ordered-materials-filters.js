/**
 * Module de gestion des filtres avancés pour les matériaux commandés
 * Ce script gère l'interface et la logique des filtres avancés
 * pour le tableau des matériaux en attente de réception.
 * 
 * @author DYM Manufacture
 * @version 1.0
 * @date Mai 2025
 */

// Namespace pour le module de filtres avancés des matériaux commandés
const OrderedMaterialsFilters = {
    // Configuration
    config: {
        tableId: 'orderedMaterialsTable',
        filterFormId: 'ordered-filters-form',
        toggleButtonId: 'toggle-ordered-filters-btn',
        resetButtonId: 'reset-ordered-filters-btn',
        applyButtonId: 'apply-ordered-filters-btn',
        filterContainerId: 'ordered-filters-container',
        exportButtonId: 'export-ordered-filtered-btn',
        counterElementId: 'filtered-ordered-counter',
        savedFiltersKey: 'dym_ordered_materials_filters',
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
        validation: '',
        dateDebut: '',
        dateFin: '',
        unite: '',
        prixMin: '',
        prixMax: ''
    },

    /**
     * Initialise le module de filtres avancés
     */
    init: function () {
        console.log('Initialisation des filtres avancés pour matériaux commandés...');

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

            console.log('Filtres avancés pour matériaux commandés initialisés avec succès');
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
        const exportBtn = document.getElementById(this.config.exportButtonId);
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

        // Mettre à jour les filtres de prix pour validation
        const prixMin = document.getElementById('prixMin-ordered');
        const prixMax = document.getElementById('prixMax-ordered');

        if (prixMin && prixMax) {
            prixMin.addEventListener('input', () => {
                if (prixMax.value && parseInt(prixMin.value) > parseInt(prixMax.value)) {
                    prixMin.value = prixMax.value;
                }
            });

            prixMax.addEventListener('input', () => {
                if (prixMin.value && parseInt(prixMax.value) < parseInt(prixMin.value)) {
                    prixMax.value = prixMin.value;
                }
            });
        }

        // Validation des dates
        const dateDebut = document.getElementById('dateDebut-ordered');
        const dateFin = document.getElementById('dateFin-ordered');

        if (dateDebut && dateFin) {
            dateDebut.addEventListener('change', () => {
                if (dateFin.value && dateDebut.value > dateFin.value) {
                    dateDebut.value = dateFin.value;
                }
            });

            dateFin.addEventListener('change', () => {
                if (dateDebut.value && dateFin.value < dateDebut.value) {
                    dateFin.value = dateDebut.value;
                }
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

        // Initialiser les options du sélecteur de statut de validation
        this.setupValidationStatusOptions();
    },

    /**
 * Configure les options du sélecteur de statut de validation
 */
    setupValidationStatusOptions: function () {
        const validationSelect = document.getElementById('validation-ordered');
        if (!validationSelect) return;

        // Vider les options existantes sauf la première
        const firstOption = validationSelect.querySelector('option[value=""]');
        validationSelect.innerHTML = '';

        if (firstOption) {
            validationSelect.appendChild(firstOption);
        }

        // Ajouter les options spécifiques aux statuts
        const statusOptions = [
            { value: 'valide', text: 'Commandé (validé)' },
            { value: 'en_cours', text: 'Commande partielle (en_cours)' },
            { value: 'valide_en_cours', text: 'En cours de validation (valide_en_cours)' }
        ];

        statusOptions.forEach(option => {
            const optElement = document.createElement('option');
            optElement.value = option.value;
            optElement.textContent = option.text;
            validationSelect.appendChild(optElement);
        });
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
        let csv = 'Projet;Client;Produit;Quantité;Unité;Statut;Prix Unit.;Fournisseur;Date\n';

        data.each(function (row) {
            // Échapper les valeurs qui contiennent des points-virgules
            const escapedRow = Array.from(row).map(value => {
                // S'assurer que la valeur est une chaîne et nettoyer les balises HTML
                let cleanValue = '';
                if (typeof value === 'string') {
                    // Créer un élément temporaire pour extraire le texte sans HTML
                    const temp = document.createElement('div');
                    temp.innerHTML = value;
                    cleanValue = temp.textContent || temp.innerText || value;

                    // Échapper les points-virgules
                    if (cleanValue.includes(';')) {
                        return `"${cleanValue}"`;
                    }
                    return cleanValue;
                }
                return value || '';
            });

            // N'ajouter que les colonnes visibles (exclure les boutons d'action)
            csv += `${escapedRow[1]};${escapedRow[2]};${escapedRow[3]};${escapedRow[4]};${escapedRow[5]};${escapedRow[6]};${escapedRow[7]};${escapedRow[8]};${escapedRow[9]}\n`;
        });

        // Créer un blob avec le contenu CSV
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });

        // Créer un lien pour télécharger le fichier
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        // Configurer et déclencher le téléchargement
        const now = new Date();
        const formattedDate = now.toISOString().slice(0, 10);
        link.setAttribute('href', url);
        link.setAttribute('download', `materiaux_commandes_${formattedDate}.csv`);
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
            // Adapter les noms des champs aux IDs du DOM
            let elementId;
            switch (key) {
                case 'projet': elementId = 'projet-ordered'; break;
                case 'client': elementId = 'client-ordered'; break;
                case 'produit': elementId = 'produit-ordered'; break;
                case 'fournisseur': elementId = 'fournisseur-ordered'; break;
                case 'unite': elementId = 'unite-ordered'; break;
                case 'validation': elementId = 'validation-ordered'; break;
                case 'dateDebut': elementId = 'dateDebut-ordered'; break;
                case 'dateFin': elementId = 'dateFin-ordered'; break;
                case 'prixMin': elementId = 'prixMin-ordered'; break;
                case 'prixMax': elementId = 'prixMax-ordered'; break;
                default: elementId = null;
            }

            if (elementId) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.value = value;
                }
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
            // Extraire les valeurs de chaque colonne

            // Projet (colonne 1)
            if (item[1]) {
                // Extraire le texte sans HTML
                const div = document.createElement('div');
                div.innerHTML = item[1];
                projets.add(div.textContent.trim());
            }

            // Client (colonne 2)
            if (item[2]) {
                const div = document.createElement('div');
                div.innerHTML = item[2];
                clients.add(div.textContent.trim());
            }

            // Unité (colonne 5)
            if (item[5]) {
                const div = document.createElement('div');
                div.innerHTML = item[5];
                unites.add(div.textContent.trim());
            }

            // Fournisseur (colonne 8)
            if (item[8]) {
                // Extraire le texte sans HTML
                const div = document.createElement('div');
                div.innerHTML = item[8];
                fournisseurs.add(div.textContent.trim());
            }
        });

        // Remplir les sélecteurs
        this.populateSelector('projet-ordered', Array.from(projets).sort());
        this.populateSelector('client-ordered', Array.from(clients).sort());
        this.populateSelector('unite-ordered', Array.from(unites).sort());
        this.populateSelector('fournisseur-ordered', Array.from(fournisseurs).sort());
    },

    /**
     * Remplit un sélecteur avec les options données
     * @param {string} selectId - ID du sélecteur
     * @param {Array} options - Options à ajouter
     */
    populateSelector: function (selectId, options) {
        const select = document.getElementById(selectId);
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
            if (option && option.trim() !== '') {
                const optElement = document.createElement('option');
                optElement.value = option;
                optElement.textContent = option;
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
            this.filters = {
                projet: document.getElementById('projet-ordered').value,
                client: document.getElementById('client-ordered').value,
                produit: document.getElementById('produit-ordered').value,
                fournisseur: document.getElementById('fournisseur-ordered').value,
                unite: document.getElementById('unite-ordered').value,
                validation: document.getElementById('validation-ordered').value,
                dateDebut: document.getElementById('dateDebut-ordered').value,
                dateFin: document.getElementById('dateFin-ordered').value,
                prixMin: document.getElementById('prixMin-ordered').value,
                prixMax: document.getElementById('prixMax-ordered').value
            };
        }

        // Sauvegarder les filtres si demandé
        if (saveFilters) {
            this.saveFilters();
        }

        // Effacer les filtres actuels de DataTables
        this.dataTable.search('').columns().search('');

        // Supprimer les filtres personnalisés existants
        this.clearCustomFilters();

        // Appliquer les filtres de colonne
        if (this.filters.projet) {
            this.dataTable.column(1).search(this.escapeRegex(this.filters.projet), true, false);
        }

        if (this.filters.client) {
            this.dataTable.column(2).search(this.escapeRegex(this.filters.client), true, false);
        }

        if (this.filters.produit) {
            this.dataTable.column(3).search(this.filters.produit, true, false);
        }

        if (this.filters.unite) {
            this.dataTable.column(5).search(this.escapeRegex(this.filters.unite), true, false);
        }

        if (this.filters.fournisseur) {
            this.dataTable.column(8).search(this.escapeRegex(this.filters.fournisseur), true, false);
        }

        // Appliquer les filtres personnalisés (date, prix, validation)
        this.applyCustomFilters();

        // Redessiner le tableau
        this.dataTable.draw();

        // Mettre à jour l'affichage du compteur de résultats
        this.updateResultsCounter();

        // Mise à jour du bouton de filtres (indication visuelle)
        this.updateFilterButton();
    },

    /**
     * Supprime les filtres personnalisés de DataTables
     */
    clearCustomFilters: function () {
        // Supprimer les filtres personnalisés existants
        jQuery.fn.dataTable.ext.search = jQuery.fn.dataTable.ext.search.filter(
            f => f.name !== 'orderedMaterialsCustomFilters'
        );
    },

    /**
     * Applique les filtres personnalisés (date, prix, validation)
     */
    applyCustomFilters: function () {
        // Créer une fonction de filtre personnalisée pour les filtres complexes
        const filterFunction = (settings, data, dataIndex) => {
            // Vérifier que nous filtrons le bon tableau
            if (settings.nTable.id !== this.config.tableId) {
                return true;
            }

            // Filtre de date
            if (this.filters.dateDebut || this.filters.dateFin) {
                const dateStr = data[9]; // Colonne Date
                if (!dateStr) return false;

                try {
                    // Parser la date (format dd/mm/yyyy)
                    const dateParts = dateStr.split('/');
                    if (dateParts.length < 3) return false;

                    const rowDate = new Date(
                        parseInt(dateParts[2], 10),
                        parseInt(dateParts[1], 10) - 1,
                        parseInt(dateParts[0], 10)
                    );

                    if (this.filters.dateDebut) {
                        const startDate = new Date(this.filters.dateDebut);
                        if (rowDate < startDate) return false;
                    }

                    if (this.filters.dateFin) {
                        const endDate = new Date(this.filters.dateFin);
                        endDate.setHours(23, 59, 59, 999); // Fin de journée
                        if (rowDate > endDate) return false;
                    }
                } catch (e) {
                    console.warn('Erreur lors du filtrage par date:', e);
                    return false;
                }
            }

            // Filtre de prix
            if (this.filters.prixMin || this.filters.prixMax) {
                const priceStr = data[7]; // Colonne Prix unitaire
                if (!priceStr) return false;

                try {
                    // Extraire le prix numérique (format: "12 345 FCFA")
                    const priceMatch = priceStr.replace(/\s+/g, '').match(/(\d+)/);
                    if (!priceMatch) return false;

                    const price = parseInt(priceMatch[1], 10);

                    if (this.filters.prixMin && price < parseInt(this.filters.prixMin, 10)) {
                        return false;
                    }

                    if (this.filters.prixMax && price > parseInt(this.filters.prixMax, 10)) {
                        return false;
                    }
                } catch (e) {
                    console.warn('Erreur lors du filtrage par prix:', e);
                    return false;
                }
            }

            // Filtre statut de validation basé sur les statuts spécifiques
            if (this.filters.validation) {
                const statusColumn = data[6]; // Colonne Statut

                // Créer un élément temporaire pour extraire le texte du statut sans HTML
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = statusColumn;
                const statusText = tempDiv.textContent || tempDiv.innerText || '';

                // Appliquer le filtre selon le statut sélectionné
                switch (this.filters.validation) {
                    case 'valide':
                        // Matériaux commandés (statut "validé")
                        if (!statusText.toLowerCase().includes('commandé') &&
                            !statusText.toLowerCase().includes('validé')) {
                            return false;
                        }
                        break;

                    case 'en_cours':
                        // Commandes partielles (statut "en_cours")
                        if (!statusText.toLowerCase().includes('partielle') &&
                            !statusText.toLowerCase().includes('en_cours')) {
                            return false;
                        }
                        break;

                    case 'valide_en_cours':
                        // En cours de validation (statut "valide_en_cours")
                        if (!statusText.toLowerCase().includes('validation') &&
                            !statusText.toLowerCase().includes('valide_en_cours')) {
                            return false;
                        }
                        break;

                    default:
                        // Aucun filtre spécifique
                        break;
                }
            }

            return true;
        };

        // Ajouter un nom à la fonction pour pouvoir la retrouver plus tard
        filterFunction.name = 'orderedMaterialsCustomFilters';

        // Ajouter la fonction de filtre à DataTables
        jQuery.fn.dataTable.ext.search.push(filterFunction);
    },

    /**
     * Échappe les caractères spéciaux dans les expressions régulières
     * @param {string} value - Valeur à échapper
     * @returns {string} - Valeur échappée
     */
    escapeRegex: function (value) {
        return value.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&');
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
        this.clearCustomFilters();

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
        const counterEl = document.getElementById(this.config.counterElementId);

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
    OrderedMaterialsFilters.init();
});