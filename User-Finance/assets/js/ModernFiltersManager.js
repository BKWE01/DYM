/**
 * Gestionnaire modernisé des filtres avancés
 * Amélioration de l'UX et de la logique des filtres
 */

class ModernFiltersManager {
    constructor() {
        this.filtersContainer = document.getElementById('pending-filters');
        this.toggleButton = document.getElementById('toggle-pending-filters');
        this.applyButton = document.getElementById('apply-pending-filters');
        this.resetButton = document.getElementById('reset-pending-filters');
        this.statusText = document.getElementById('filter-status-text');

        this.isVisible = false;
        this.activeFilters = new Map();

        this.init();
    }

    /**
     * Initialisation du gestionnaire
     */
    init() {
        this.bindEvents();
        this.updateToggleButton();
        this.updateFilterStatus();
        console.log('✅ ModernFiltersManager initialisé');
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Toggle des filtres avec animation modernisée
        this.toggleButton?.addEventListener('click', () => {
            this.toggleFilters();
        });

        // Application des filtres
        this.applyButton?.addEventListener('click', () => {
            this.applyFilters();
        });

        // Réinitialisation des filtres
        this.resetButton?.addEventListener('click', () => {
            this.resetFilters();
        });

        // Surveillance des changements en temps réel
        this.bindInputEvents();

        // Animation des champs actifs
        this.bindFocusEvents();
    }

    /**
     * Événements sur les inputs
     */
    bindInputEvents() {
        const inputs = this.filtersContainer?.querySelectorAll('input, select');

        inputs?.forEach(input => {
            input.addEventListener('input', () => {
                this.updateActiveFilters();
                this.updateFilterStatus();
                this.highlightActiveFields();
            });

            input.addEventListener('change', () => {
                this.updateActiveFilters();
                this.updateFilterStatus();
                this.highlightActiveFields();
            });
        });
    }

    /**
     * Événements de focus pour les animations
     */
    bindFocusEvents() {
        const inputs = this.filtersContainer?.querySelectorAll('input, select');

        inputs?.forEach(input => {
            input.addEventListener('focus', (e) => {
                this.animateFieldFocus(e.target, true);
            });

            input.addEventListener('blur', (e) => {
                this.animateFieldFocus(e.target, false);
            });
        });
    }

    /**
     * Toggle des filtres avec animation fluide
     */
    toggleFilters() {
        if (!this.filtersContainer) return;

        this.isVisible = !this.isVisible;

        if (this.isVisible) {
            // Afficher avec animation
            this.filtersContainer.classList.remove('hidden');

            // Force reflow pour l'animation
            this.filtersContainer.offsetHeight;

            // Animation d'entrée
            this.filtersContainer.style.opacity = '0';
            this.filtersContainer.style.transform = 'translateY(-10px)';

            requestAnimationFrame(() => {
                this.filtersContainer.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                this.filtersContainer.style.opacity = '1';
                this.filtersContainer.style.transform = 'translateY(0)';
            });

            // Animation séquentielle des sections
            this.animateFilterSections();

        } else {
            // Masquer avec animation
            this.filtersContainer.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            this.filtersContainer.style.opacity = '0';
            this.filtersContainer.style.transform = 'translateY(-10px)';

            setTimeout(() => {
                this.filtersContainer.classList.add('hidden');
                this.filtersContainer.style.opacity = '';
                this.filtersContainer.style.transform = '';
                this.filtersContainer.style.transition = '';
            }, 300);
        }

        this.updateToggleButton();
    }

    /**
     * Animation séquentielle des sections de filtres
     */
    animateFilterSections() {
        const sections = this.filtersContainer?.querySelectorAll('.filter-section');

        sections?.forEach((section, index) => {
            section.style.opacity = '0';
            section.style.transform = 'translateY(20px)';

            setTimeout(() => {
                section.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                section.style.opacity = '1';
                section.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }

    /**
     * Animation des champs lors du focus
     */
    animateFieldFocus(field, isFocused) {
        const filterSection = field.closest('.filter-section');

        if (isFocused) {
            filterSection?.classList.add('filter-focused');
            field.style.transform = 'scale(1.02)';
        } else {
            filterSection?.classList.remove('filter-focused');
            field.style.transform = 'scale(1)';
        }
    }

    /**
     * Mise à jour du bouton toggle
     */
    updateToggleButton() {
        if (!this.toggleButton) return;

        const icon = this.toggleButton.querySelector('.material-icons');
        const text = this.toggleButton.querySelector('span:last-child') || this.toggleButton;

        if (this.isVisible) {
            icon.textContent = 'filter_alt_off';
            if (text !== this.toggleButton) {
                text.textContent = 'Masquer les filtres';
            }
            this.toggleButton.classList.add('active');
        } else {
            icon.textContent = 'filter_alt';
            if (text !== this.toggleButton) {
                text.textContent = 'Afficher les filtres';
            }
            this.toggleButton.classList.remove('active');
        }
    }

    /**
     * Mise à jour des filtres actifs
     */
    updateActiveFilters() {
        this.activeFilters.clear();

        // Vérifier chaque champ de filtre
        const filters = {
            'filter-date-from': 'Date début',
            'filter-date-to': 'Date fin',
            'filter-fournisseur': 'Fournisseur',
            'filter-payment': 'Mode de paiement',
            'filter-min-amount': 'Montant minimum',
            'filter-max-amount': 'Montant maximum'
        };

        Object.entries(filters).forEach(([id, label]) => {
            const element = document.getElementById(id);
            if (element && element.value && element.value.trim() !== '') {
                this.activeFilters.set(id, {
                    label: label,
                    value: element.value
                });
            }
        });
    }

    /**
     * Mise à jour du statut des filtres
     */
    updateFilterStatus() {
        if (!this.statusText) return;

        const count = this.activeFilters.size;
        const statusIcon = this.statusText.previousElementSibling;

        if (count === 0) {
            this.statusText.textContent = 'Aucun filtre appliqué';
            statusIcon.textContent = 'info';
            statusIcon.style.color = '#06b6d4';
        } else {
            this.statusText.textContent = `${count} filtre${count > 1 ? 's' : ''} actif${count > 1 ? 's' : ''}`;
            statusIcon.textContent = 'filter_alt';
            statusIcon.style.color = '#10b981';
        }

        // Animation du statut
        this.statusText.style.transform = 'scale(1.05)';
        setTimeout(() => {
            this.statusText.style.transform = 'scale(1)';
        }, 150);
    }

    /**
     * Surlignage des champs actifs
     */
    highlightActiveFields() {
        // Retirer tous les highlights existants
        this.filtersContainer?.querySelectorAll('.filter-section').forEach(section => {
            section.classList.remove('filter-active');
        });

        // Ajouter highlight aux sections avec filtres actifs
        this.activeFilters.forEach((filter, fieldId) => {
            const field = document.getElementById(fieldId);
            const section = field?.closest('.filter-section');

            if (section) {
                section.classList.add('filter-active');
            }
        });
    }

    /**
     * Application des filtres avec feedback visuel
     */
    applyFilters() {
        // Animation du bouton
        this.applyButton.style.transform = 'scale(0.95)';
        this.applyButton.innerHTML = '<span class="material-icons">hourglass_empty</span><span>Application...</span>';

        // Simuler un délai de traitement
        setTimeout(() => {
            // Restaurer le bouton
            this.applyButton.style.transform = 'scale(1)';
            this.applyButton.innerHTML = '<span class="material-icons">filter_alt</span><span>Appliquer les filtres</span>';

            // Appeler la logique existante de DataTableManager
            if (window.financeManager?.components?.dataTable?.tables?.pending) {
                window.financeManager.components.dataTable.tables.pending.draw();
            }

            // Notification de succès
            this.showFilterNotification('Filtres appliqués avec succès', 'success');

            // Effet visuel sur le container
            this.highlightFilterContainer();

        }, 500);
    }

    /**
     * Réinitialisation des filtres avec animation
     */
    resetFilters() {
        // Animation du bouton
        this.resetButton.style.transform = 'scale(0.95)';

        // Vider tous les champs avec animation
        const inputs = this.filtersContainer?.querySelectorAll('input, select');

        inputs?.forEach((input, index) => {
            setTimeout(() => {
                input.style.transition = 'all 0.2s ease';
                input.style.transform = 'scale(1.05)';

                setTimeout(() => {
                    input.value = '';
                    input.style.transform = 'scale(1)';

                    // Déclencher l'événement change
                    input.dispatchEvent(new Event('change'));
                }, 100);
            }, index * 50);
        });

        // Restaurer le bouton
        setTimeout(() => {
            this.resetButton.style.transform = 'scale(1)';
        }, 200);

        // Mettre à jour l'état
        this.activeFilters.clear();
        this.updateFilterStatus();
        this.highlightActiveFields();

        // Notification
        this.showFilterNotification('Filtres réinitialisés', 'info');
    }

    /**
     * Effet visuel sur le container des filtres
     */
    highlightFilterContainer() {
        if (!this.filtersContainer) return;

        this.filtersContainer.style.boxShadow = '0 0 20px rgba(59, 130, 246, 0.3)';
        this.filtersContainer.style.transform = 'scale(1.01)';

        setTimeout(() => {
            this.filtersContainer.style.boxShadow = '';
            this.filtersContainer.style.transform = 'scale(1)';
        }, 600);
    }

    /**
     * Notification pour les actions de filtres
     */
    showFilterNotification(message, type = 'info') {
        if (window.notificationManager) {
            window.notificationManager.show(message, type, { duration: 2000 });
        }
    }

    /**
     * Obtenir l'état des filtres pour debugging
     */
    getFiltersState() {
        return {
            isVisible: this.isVisible,
            activeFilters: Object.fromEntries(this.activeFilters),
            filterCount: this.activeFilters.size
        };
    }

    /**
     * Appliquer des filtres programmatiquement
     */
    setFilters(filters) {
        Object.entries(filters).forEach(([fieldId, value]) => {
            const element = document.getElementById(fieldId);
            if (element) {
                element.value = value;
                element.dispatchEvent(new Event('change'));
            }
        });
    }

    /**
     * Destruction propre
     */
    destroy() {
        // Nettoyer les event listeners
        this.toggleButton?.removeEventListener('click', this.toggleFilters);
        this.applyButton?.removeEventListener('click', this.applyFilters);
        this.resetButton?.removeEventListener('click', this.resetFilters);

        console.log('🧹 ModernFiltersManager nettoyé');
    }
}

/**
 * Intégration avec l'existant - Amélioration de DataTableManager
 */
if (window.DataTableManager) {
    // Extension de la méthode setupPendingFilters existante
    const originalSetupPendingFilters = window.DataTableManager.prototype.setupPendingFilters;

    window.DataTableManager.prototype.setupPendingFilters = function (data) {
        // Appeler la méthode originale
        originalSetupPendingFilters.call(this, data);

        // Initialiser le gestionnaire moderne si pas encore fait
        if (!this.modernFiltersManager) {
            this.modernFiltersManager = new ModernFiltersManager();
        }
    };

    // Extension de la méthode destroy
    const originalDestroy = window.DataTableManager.prototype.destroy;

    window.DataTableManager.prototype.destroy = function () {
        // Nettoyer le gestionnaire moderne
        if (this.modernFiltersManager) {
            this.modernFiltersManager.destroy();
            this.modernFiltersManager = null;
        }

        // Appeler la méthode originale
        originalDestroy.call(this);
    };
}

// CSS additionnels pour les nouveaux états
const additionalCSS = `
<style>
.filter-focused {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
}

.btn-filter.active {
    background: linear-gradient(135deg, #1d4ed8, #1e40af) !important;
    color: white !important;
}

.filter-section {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

.modern-input, .modern-select {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.filter-processing {
    animation: pulse 1s infinite;
}
</style>
`;

// Injecter le CSS additionnel
if (!document.getElementById('modern-filters-additional-css')) {
    const style = document.createElement('style');
    style.id = 'modern-filters-additional-css';
    style.innerHTML = additionalCSS;
    document.head.appendChild(style);
}

// Export global
window.ModernFiltersManager = ModernFiltersManager;