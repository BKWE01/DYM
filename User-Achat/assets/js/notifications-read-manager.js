/**
 * Gestionnaire avancé des notifications lues
 * Gère la lecture des notifications et la mise à jour en temps réel de l'interface
 * 
 * Service Achat - DYM MANUFACTURE
 * Fichier: /User-Achat/assets/js/notifications-read-manager.js
 */

class NotificationsReadManager {
    constructor() {
        this.apiUrl = 'api/notifications/mark_notifications_read.php';
        this.readNotifications = new Set();
        this.isInitialized = false;
        this.observers = [];
        
        // Configuration
        this.config = {
            fadeOutDuration: 300,
            updateInterval: 30000, // 30 secondes
            maxRetries: 3,
            retryDelay: 1000
        };

        // Initialiser dès que possible
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }

    /**
     * Initialise le gestionnaire
     */
    init() {
        if (this.isInitialized) return;
        
        console.log('Initialisation du gestionnaire de notifications lues');
        
        this.setupEventListeners();
        this.loadReadNotifications();
        this.setupPeriodicUpdate();
        this.attachToNotificationItems();
        
        this.isInitialized = true;
    }

    /**
     * Configure les écouteurs d'événements
     */
    setupEventListeners() {
        // Boutons de marquage en masse
        this.setupBulkMarkingButtons();
        
        // Observer les changements d'onglets pour réattacher les événements
        this.observeTabChanges();
        
        // Gérer la fermeture des notifications individuelles
        this.attachToCloseButtons();
    }

    /**
     * Configure les boutons de marquage en masse
     */
    setupBulkMarkingButtons() {
        // Créer et injecter les boutons de contrôle dans le footer des notifications
        const notificationsFooter = document.querySelector('#notifications-dropdown .bg-gray-50:last-child');
        
        if (notificationsFooter && !notificationsFooter.querySelector('.bulk-notification-controls')) {
            const controlsContainer = document.createElement('div');
            controlsContainer.className = 'bulk-notification-controls flex justify-between items-center mb-3';
            controlsContainer.innerHTML = `
                <div class="flex space-x-2">
                    <button id="mark-all-notifications-btn" 
                            class="text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 px-2 py-1 rounded transition-colors">
                        <span class="material-icons text-xs mr-1">done_all</span>Tout marquer
                    </button>
                    <button id="mark-urgent-notifications-btn" 
                            class="text-xs bg-red-100 text-red-700 hover:bg-red-200 px-2 py-1 rounded transition-colors">
                        <span class="material-icons text-xs mr-1">priority_high</span>Urgents
                    </button>
                    <button id="mark-recent-notifications-btn" 
                            class="text-xs bg-green-100 text-green-700 hover:bg-green-200 px-2 py-1 rounded transition-colors">
                        <span class="material-icons text-xs mr-1">schedule</span>Récents
                    </button>
                </div>
                <button id="refresh-notifications-btn" 
                        class="text-xs bg-gray-100 text-gray-700 hover:bg-gray-200 px-2 py-1 rounded transition-colors">
                    <span class="material-icons text-xs mr-1">refresh</span>Actualiser
                </button>
            `;
            
            // Insérer avant le lien "Voir tous les matériaux"
            const existingLink = notificationsFooter.querySelector('a');
            notificationsFooter.insertBefore(controlsContainer, existingLink);
            
            // Attacher les événements
            this.attachBulkButtonEvents();
        }
    }

    /**
     * Attache les événements aux boutons de marquage en masse
     */
    attachBulkButtonEvents() {
        // Marquer toutes les notifications
        const markAllBtn = document.getElementById('mark-all-notifications-btn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.markAllNotifications();
            });
        }

        // Marquer les notifications urgentes
        const markUrgentBtn = document.getElementById('mark-urgent-notifications-btn');
        if (markUrgentBtn) {
            markUrgentBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.markNotificationsByType('urgent');
            });
        }

        // Marquer les notifications récentes
        const markRecentBtn = document.getElementById('mark-recent-notifications-btn');
        if (markRecentBtn) {
            markRecentBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.markNotificationsByType('recent');
            });
        }

        // Actualiser les notifications
        const refreshBtn = document.getElementById('refresh-notifications-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.refreshNotifications();
            });
        }
    }

    /**
     * Attache les événements aux éléments de notification
     */
    attachToNotificationItems() {
        const notificationItems = document.querySelectorAll('[data-notification-item]');
        
        notificationItems.forEach(item => {
            if (!item.dataset.listenerAttached) {
                item.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.markNotificationAsRead(item);
                });
                
                item.dataset.listenerAttached = 'true';
            }
        });
    }

    /**
     * Attache les événements aux boutons de fermeture
     */
    attachToCloseButtons() {
        const closeButtons = document.querySelectorAll('.notification-close-btn');
        
        closeButtons.forEach(button => {
            if (!button.dataset.listenerAttached) {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const notificationItem = button.closest('[data-notification-item]');
                    if (notificationItem) {
                        this.markNotificationAsRead(notificationItem, true);
                    }
                });
                
                button.dataset.listenerAttached = 'true';
            }
        });
    }

    /**
     * Marque une notification individuelle comme lue
     */
    async markNotificationAsRead(notificationElement, shouldHide = false) {
        const materialId = notificationElement.dataset.materialId;
        const expressionId = notificationElement.dataset.expressionId;
        const sourceTable = notificationElement.dataset.sourceTable || 'expression_dym';
        const notificationType = notificationElement.dataset.notificationType;
        const designation = notificationElement.dataset.designation;

        if (!materialId || !expressionId || !notificationType) {
            console.warn('Données de notification incomplètes', notificationElement.dataset);
            return;
        }

        try {
            // Marquer comme lu dans l'interface immédiatement
            this.markAsReadVisually(notificationElement, shouldHide);
            
            // Envoyer la requête à l'API
            const response = await this.sendMarkReadRequest({
                action: 'mark_single',
                material_id: materialId,
                expression_id: expressionId,
                source_table: sourceTable,
                notification_type: notificationType,
                designation: designation
            });

            if (response.success) {
                // Ajouter à notre set local
                const notificationKey = `${sourceTable}_${materialId}_${notificationType}`;
                this.readNotifications.add(notificationKey);
                
                // Mettre à jour les compteurs
                this.updateNotificationCounters();
                
                console.log('Notification marquée comme lue:', designation);
                
                // Afficher un toast de confirmation
                this.showToast('Notification marquée comme lue', 'success');
            }
        } catch (error) {
            console.error('Erreur lors du marquage de la notification:', error);
            
            // Restaurer l'état visuel en cas d'erreur
            this.unmarkAsReadVisually(notificationElement);
            
            this.showToast('Erreur lors du marquage de la notification', 'error');
        }
    }

    /**
     * Marque toutes les notifications comme lues
     */
    async markAllNotifications() {
        try {
            const button = document.getElementById('mark-all-notifications-btn');
            const originalText = button ? button.innerHTML : '';
            
            if (button) {
                button.innerHTML = '<span class="material-icons text-xs animate-spin mr-1">refresh</span>Traitement...';
                button.disabled = true;
            }

            const response = await this.sendMarkReadRequest({
                action: 'mark_all'
            });

            if (response.success) {
                // Marquer tous les éléments visuellement
                const allNotifications = document.querySelectorAll('[data-notification-item]');
                allNotifications.forEach(item => this.markAsReadVisually(item, false));
                
                // Mettre à jour les compteurs
                this.updateNotificationCounters();
                
                this.showToast(`${response.data.marked_count} notifications marquées comme lues`, 'success');
            }
        } catch (error) {
            console.error('Erreur lors du marquage de toutes les notifications:', error);
            this.showToast('Erreur lors du marquage des notifications', 'error');
        } finally {
            const button = document.getElementById('mark-all-notifications-btn');
            if (button) {
                button.innerHTML = '<span class="material-icons text-xs mr-1">done_all</span>Tout marquer';
                button.disabled = false;
            }
        }
    }

    /**
     * Marque toutes les notifications d'un type spécifique comme lues
     */
    async markNotificationsByType(notificationType) {
        try {
            const buttonId = `mark-${notificationType}-notifications-btn`;
            const button = document.getElementById(buttonId);
            const originalText = button ? button.innerHTML : '';
            
            if (button) {
                button.innerHTML = '<span class="material-icons text-xs animate-spin mr-1">refresh</span>Traitement...';
                button.disabled = true;
            }

            const response = await this.sendMarkReadRequest({
                action: 'mark_type',
                notification_type: notificationType
            });

            if (response.success) {
                // Marquer les éléments du type spécifique visuellement
                const typeNotifications = document.querySelectorAll(`[data-notification-type="${notificationType}"]`);
                typeNotifications.forEach(item => this.markAsReadVisually(item, false));
                
                // Mettre à jour les compteurs
                this.updateNotificationCounters();
                
                this.showToast(`${response.data.marked_count} notifications "${notificationType}" marquées comme lues`, 'success');
            }
        } catch (error) {
            console.error(`Erreur lors du marquage des notifications ${notificationType}:`, error);
            this.showToast(`Erreur lors du marquage des notifications ${notificationType}`, 'error');
        } finally {
            const buttonId = `mark-${notificationType}-notifications-btn`;
            const button = document.getElementById(buttonId);
            const originalText = notificationType === 'urgent' ? 'Urgents' : 'Récents';
            if (button) {
                const icon = notificationType === 'urgent' ? 'priority_high' : 'schedule';
                button.innerHTML = `<span class="material-icons text-xs mr-1">${icon}</span>${originalText}`;
                button.disabled = false;
            }
        }
    }

    /**
     * Marque visuellement une notification comme lue
     */
    markAsReadVisually(notificationElement, shouldHide = false) {
        if (!notificationElement) return;

        // Ajouter les classes de notification lue
        notificationElement.classList.add('notification-read');
        
        // Modifier le style pour indiquer la lecture
        notificationElement.style.opacity = '0.6';
        notificationElement.style.backgroundColor = '#f9fafb';
        notificationElement.style.borderLeftColor = '#d1d5db';
        
        // Ajouter un indicateur visuel
        const title = notificationElement.querySelector('.notification-title');
        if (title && !title.querySelector('.notification-read-indicator')) {
            const indicator = document.createElement('span');
            indicator.className = 'notification-read-indicator material-icons text-xs text-gray-400 ml-2';
            indicator.textContent = 'check_circle';
            indicator.title = 'Notification lue';
            title.appendChild(indicator);
        }

        // Cacher la notification si demandé
        if (shouldHide) {
            notificationElement.style.transition = `opacity ${this.config.fadeOutDuration}ms ease-out`;
            notificationElement.style.opacity = '0';
            
            setTimeout(() => {
                notificationElement.style.display = 'none';
            }, this.config.fadeOutDuration);
        }
    }

    /**
     * Restaure l'état visuel d'une notification (en cas d'erreur)
     */
    unmarkAsReadVisually(notificationElement) {
        if (!notificationElement) return;

        notificationElement.classList.remove('notification-read');
        notificationElement.style.opacity = '';
        notificationElement.style.backgroundColor = '';
        notificationElement.style.borderLeftColor = '';
        notificationElement.style.display = '';
        
        // Supprimer l'indicateur de lecture
        const indicator = notificationElement.querySelector('.notification-read-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    /**
     * Met à jour les compteurs de notifications
     */
    updateNotificationCounters() {
        // Compter les notifications visibles non lues par type
        const urgentCount = document.querySelectorAll('[data-notification-type="urgent"]:not(.notification-read)').length;
        const recentCount = document.querySelectorAll('[data-notification-type="recent"]:not(.notification-read)').length;
        const partialCount = document.querySelectorAll('[data-notification-type="remaining"]:not(.notification-read)').length;
        
        // Mettre à jour le compteur total
        const totalCount = urgentCount + recentCount + partialCount;
        
        // Mettre à jour l'affichage des compteurs
        this.updateCounterDisplay('.notification-badge', totalCount);
        
        // Mettre à jour les compteurs dans les onglets
        this.updateTabCounters(urgentCount, recentCount, partialCount);
        
        // Déclencher l'événement de mise à jour des compteurs
        if (window.NotificationCounters && typeof window.NotificationCounters.refresh === 'function') {
            window.NotificationCounters.refresh();
        }
    }

    /**
     * Met à jour l'affichage d'un compteur
     */
    updateCounterDisplay(selector, count) {
        const counter = document.querySelector(selector);
        if (counter) {
            if (count > 0) {
                counter.textContent = Math.min(count, 99);
                counter.style.display = '';
            } else {
                counter.style.display = 'none';
            }
        }
    }

    /**
     * Met à jour les compteurs dans les onglets
     */
    updateTabCounters(urgentCount, recentCount, partialCount) {
        // Mettre à jour l'onglet urgents
        const urgentTab = document.querySelector('[data-tab="urgent"] .bg-red-500');
        if (urgentTab) {
            if (urgentCount > 0) {
                urgentTab.textContent = urgentCount;
                urgentTab.style.display = '';
            } else {
                urgentTab.style.display = 'none';
            }
        }

        // Mettre à jour l'onglet récents
        const recentTab = document.querySelector('[data-tab="recent"] .bg-blue-500');
        if (recentTab) {
            if (recentCount > 0) {
                recentTab.textContent = recentCount;
                recentTab.style.display = '';
            } else {
                recentTab.style.display = 'none';
            }
        }

        // Mettre à jour l'onglet partiels
        const partialTab = document.querySelector('[data-tab="partials"] .bg-yellow-500');
        if (partialTab) {
            if (partialCount > 0) {
                partialTab.textContent = partialCount;
                partialTab.style.display = '';
            } else {
                partialTab.style.display = 'none';
            }
        }
    }

    /**
     * Actualise les notifications
     */
    async refreshNotifications() {
        try {
            const button = document.getElementById('refresh-notifications-btn');
            if (button) {
                button.innerHTML = '<span class="material-icons text-xs animate-spin mr-1">refresh</span>Actualisation...';
                button.disabled = true;
            }

            // Recharger la page pour obtenir les nouvelles notifications
            window.location.reload();
            
        } catch (error) {
            console.error('Erreur lors de l\'actualisation:', error);
            this.showToast('Erreur lors de l\'actualisation', 'error');
        } finally {
            const button = document.getElementById('refresh-notifications-btn');
            if (button) {
                button.innerHTML = '<span class="material-icons text-xs mr-1">refresh</span>Actualiser';
                button.disabled = false;
            }
        }
    }

    /**
     * Charge les notifications déjà lues
     */
    async loadReadNotifications() {
        try {
            const response = await this.sendMarkReadRequest({
                action: 'get_unread_count'
            });

            if (response.success) {
                // Marquer visuellement les notifications déjà lues
                this.applyReadStateToExistingNotifications();
            }
        } catch (error) {
            console.error('Erreur lors du chargement des notifications lues:', error);
        }
    }

    /**
     * Applique l'état lu aux notifications existantes
     */
    applyReadStateToExistingNotifications() {
        // Cette fonction sera appelée pour marquer visuellement 
        // les notifications qui sont déjà dans la base comme lues
        // Pour l'instant, on se base sur la présence des éléments dans le DOM
    }

    /**
     * Observe les changements d'onglets pour réattacher les événements
     */
    observeTabChanges() {
        // Observer les clics sur les onglets de notification
        const notificationTabs = document.querySelectorAll('.notification-tab');
        
        notificationTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Réattacher les événements après un délai pour laisser le temps au contenu de se charger
                setTimeout(() => {
                    this.attachToNotificationItems();
                    this.attachToCloseButtons();
                }, 100);
            });
        });
    }

    /**
     * Configure la mise à jour périodique
     */
    setupPeriodicUpdate() {
        setInterval(() => {
            this.updateNotificationCounters();
        }, this.config.updateInterval);
    }

    /**
     * Envoie une requête à l'API de marquage
     */
    async sendMarkReadRequest(data, retryCount = 0) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Erreur API');
            }

            return result;
            
        } catch (error) {
            if (retryCount < this.config.maxRetries) {
                console.warn(`Tentative ${retryCount + 1} échouée, retry...`, error.message);
                await new Promise(resolve => setTimeout(resolve, this.config.retryDelay * (retryCount + 1)));
                return this.sendMarkReadRequest(data, retryCount + 1);
            }
            throw error;
        }
    }

    /**
     * Affiche un toast de notification
     */
    showToast(message, type = 'info') {
        // Supprimer les anciens toasts
        const existingToasts = document.querySelectorAll('.toast-notification');
        existingToasts.forEach(toast => toast.remove());

        const toast = document.createElement('div');
        toast.className = `toast-notification fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50 text-white transition-all duration-300 ${this.getToastClass(type)}`;
        toast.textContent = message;

        document.body.appendChild(toast);

        // Animation d'entrée
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        }, 10);

        // Suppression automatique
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * Retourne la classe CSS pour le type de toast
     */
    getToastClass(type) {
        const classes = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            warning: 'bg-yellow-500',
            info: 'bg-blue-500'
        };
        return classes[type] || classes.info;
    }

    /**
     * Nettoie les ressources
     */
    destroy() {
        this.observers.forEach(observer => observer.disconnect());
        this.isInitialized = false;
    }
}

// Initialisation automatique
if (typeof window !== 'undefined') {
    window.NotificationsReadManager = NotificationsReadManager;
    
    // Créer une instance globale
    window.notificationsReadManager = new NotificationsReadManager();
}