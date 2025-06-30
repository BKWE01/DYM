/**
 * Module de gestion des compteurs de notification
 * Ce fichier gère l'affichage et la mise à jour des compteurs de notification 
 * pour les onglets de matériaux partiels et commandés
 * 
 * @module NotificationCounters
 * @author [Votre nom]
 * @date Mai 2025
 */

// Namespace pour éviter les conflits
var NotificationCounters = (function() {
    // Configuration
    const CONFIG = {
        API_URL: 'api/counters/material_counters.php',
        REFRESH_INTERVAL: 120000, // 2 minutes
        SELECTORS: {
            PARTIAL_COUNTER: '#materials-partial-tab .rounded-full',
            ORDERED_COUNTER: '#materials-ordered-tab .rounded-full',
            PENDING_COUNTER: '#materials-pending-tab .rounded-full'
        }
    };

    // État interne
    let state = {
        lastUpdate: 0,
        counts: {
            partial: 0,
            ordered: 0,
            pending: 0
        },
        updateInterval: null
    };

    /**
     * Initialise le module de gestion des compteurs
     */
    function init() {
        console.log('Initialisation du module de compteurs de notification');
        
        // Charger les compteurs immédiatement
        fetchAndUpdateCounters();
        
        // Configurer la mise à jour périodique
        if (state.updateInterval) {
            clearInterval(state.updateInterval);
        }
        
        state.updateInterval = setInterval(fetchAndUpdateCounters, CONFIG.REFRESH_INTERVAL);
        
        // Attacher aux événements existants si nécessaire
        attachToExistingEvents();
    }

    /**
     * Récupère les compteurs depuis l'API et met à jour l'interface
     * @returns {Promise} Promesse résolue lorsque les compteurs sont mis à jour
     */
    function fetchAndUpdateCounters() {
        return fetch(CONFIG.API_URL)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur HTTP: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.counts) {
                    // Mettre à jour l'état interne
                    state.counts = data.counts;
                    state.lastUpdate = Date.now();
                    
                    // Mettre à jour l'interface
                    updateCountersUI();
                    
                    console.log('Compteurs de notification mis à jour:', data.counts);
                    return data.counts;
                } else {
                    console.error('Format de réponse incorrect:', data);
                    throw new Error('Format de réponse incorrect');
                }
            })
            .catch(error => {
                console.error('Erreur lors de la récupération des compteurs:', error);
                // Ne pas bloquer l'interface en cas d'erreur
                return null;
            });
    }

    /**
     * Met à jour l'interface utilisateur avec les compteurs actuels
     */
    function updateCountersUI() {
        // Mettre à jour le compteur d'achats partiels
        updateSingleCounter(
            CONFIG.SELECTORS.PARTIAL_COUNTER, 
            state.counts.partial,
            'status-partial'
        );
        
        // Mettre à jour le compteur d'achats commandés
        updateSingleCounter(
            CONFIG.SELECTORS.ORDERED_COUNTER, 
            state.counts.ordered,
            'status-ordered'
        );
        
        // Mettre à jour le compteur d'achats en attente
        updateSingleCounter(
            CONFIG.SELECTORS.PENDING_COUNTER, 
            state.counts.pending,
            'status-pending'
        );
    }

    /**
     * Met à jour un compteur spécifique
     * @param {string} selector - Sélecteur CSS pour trouver l'élément compteur
     * @param {number} count - Valeur du compteur
     * @param {string} className - Classe CSS à ajouter pour le style
     */
    function updateSingleCounter(selector, count, className) {
        const counterElement = document.querySelector(selector);
        
        if (!counterElement) {
            console.warn(`Élément compteur non trouvé: ${selector}`);
            return;
        }
        
        // Mettre à jour le texte
        counterElement.textContent = count || 0;
        
        // Gérer la visibilité et les classes
        if (count > 0) {
            counterElement.classList.remove('hidden');
            counterElement.classList.add(className);
        } else {
            counterElement.classList.add('hidden');
        }
    }

    /**
     * Attache les gestionnaires aux événements existants
     */
    function attachToExistingEvents() {
        // S'attacher à la fonction switchTab si elle existe
        if (typeof window.switchTab === 'function') {
            const originalSwitchTab = window.switchTab;
            
            window.switchTab = function(tabName) {
                // Appeler la fonction originale
                originalSwitchTab(tabName);
                
                // S'assurer que les compteurs restent visibles après le changement d'onglet
                setTimeout(updateCountersUI, 50);
            };
            
            console.log('Fonction switchTab interceptée pour maintenir les compteurs');
        }

        // Observer les mutations du DOM pour détecter les changements d'onglets
        // Cela est utile si les onglets sont modifiés par d'autres scripts
        setupMutationObserver();
    }

    /**
     * Configure un observateur de mutations pour surveiller les changements d'onglets
     */
    function setupMutationObserver() {
        if (!window.MutationObserver) return;
        
        const tabsContainer = document.querySelector('.materials-tab')?.parentElement;
        if (!tabsContainer) return;
        
        const observer = new MutationObserver(mutations => {
            let shouldUpdate = false;
            
            mutations.forEach(mutation => {
                if (mutation.type === 'attributes' && 
                    mutation.attributeName === 'class') {
                    shouldUpdate = true;
                }
            });
            
            if (shouldUpdate) {
                setTimeout(updateCountersUI, 50);
            }
        });
        
        observer.observe(tabsContainer, { 
            attributes: true, 
            subtree: true,
            attributeFilter: ['class']
        });
        
        console.log('Observateur de mutations configuré pour les onglets');
    }

    /**
     * Permet de mettre à jour manuellement les compteurs
     * Cette fonction peut être appelée après des actions qui modifieraient les compteurs
     */
    function refreshCounters() {
        return fetchAndUpdateCounters();
    }

    // API publique
    return {
        init: init,
        refresh: refreshCounters,
        getState: function() { return {...state}; }
    };
})();

// Initialiser le module au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    NotificationCounters.init();
    
    // Exposer le module globalement pour permettre des mises à jour manuelles
    window.NotificationCounters = NotificationCounters;
});