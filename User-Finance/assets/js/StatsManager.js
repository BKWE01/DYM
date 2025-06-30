/**
 * StatsManager.js
 * Gestionnaire des statistiques pour le service Finance
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

class StatsManager {
    constructor() {
        this.config = {
            urls: {
                stats: 'api/dashboard/get_finance_stats.php'
            }
        };
    }

    /**
     * Charge et met à jour les statistiques
     */
    async load() {
        try {
            console.log('📊 Chargement des statistiques Finance...');

            const response = await this.apiCall(this.config.urls.stats);

            if (response.success) {
                this.updateStatsDisplay(response.data);
                console.log('✅ Statistiques mises à jour');
            } else {
                console.warn('⚠️ Erreur lors du chargement des statistiques:', response.message);
                this.showDefaults();
            }
        } catch (error) {
            console.error('❌ Erreur lors du chargement des statistiques:', error);
            this.showDefaults();
        }
    }

    /**
     * Met à jour l'affichage des statistiques
     */
    updateStatsDisplay(stats) {
        // Mettre à jour les cartes de statistiques
        this.setElement('stat-pending-count', stats.pending_count || 0);
        this.setElement('stat-signed-count', stats.signed_count || 0);
        this.setElement('stat-total-amount', this.formatAmount(stats.total_signed_amount || 0));

        // Mettre à jour les badges des onglets
        this.setElement('pending-badge', stats.pending_count || 0);
        this.setElement('signed-badge', stats.signed_count || 0);
        this.setElement('rejected-badge', stats.rejected_count || 0); // NOUVEAU

        console.log('📊 Statistiques affichées:', stats);
    }

    /**
     * Affiche les valeurs par défaut en cas d'erreur
     */
    showDefaults() {
        this.setElement('stat-pending-count', '0');
        this.setElement('stat-signed-count', '0');
        this.setElement('stat-total-amount', '0 FCFA');
        this.setElement('pending-badge', '0');
        this.setElement('signed-badge', '0');
    }

    /**
     * Met à jour un élément DOM
     */
    setElement(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }

    /**
     * Formate un montant
     */
    formatAmount(amount) {
        return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
    }

    /**
     * Appel API simplifié
     */
    async apiCall(url) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: url,
                method: 'GET',
                dataType: 'json',
                timeout: 10000,
                success: resolve,
                error: (xhr, status, error) => {
                    reject(new Error(`API Error: ${status} - ${error}`));
                }
            });
        });
    }
}

// Créer une instance globale
window.Stats = new StatsManager();

// Export pour utilisation
window.StatsManager = StatsManager;