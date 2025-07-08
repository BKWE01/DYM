/**
 * Gestionnaire des notifications lues - Service Achat
 * 
 * DYM MANUFACTURE
 * Fichier: /User-Achat/assets/js/notifications-read-manager.js
 */

class NotificationsReadManager {
    constructor() {
        const baseUrl = window.DYM_BASE_URL || '';
        this.apiUrl = `${baseUrl}/User-Achat/api/notifications/mark_notifications_read.php`;
        this.debounceTimeout = null;
        this.init();
    }

    /**
     * Initialisation du gestionnaire
     */
    init() {
        this.bindEvents();
        this.addNotificationMarkingControls();
        console.log('NotificationsReadManager initialisé');
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Intercepter les clics sur les notifications individuelles
        this.bindNotificationClicks();
        
        // Gérer la fermeture du dropdown
        this.bindDropdownClose();
        
        // Boutons de marquage global
        this.bindGlobalMarkingButtons();
        
        // Auto-refresh périodique des compteurs
        this.startPeriodicRefresh();
    }

    /**
     * Intercepter les clics sur les notifications
     */
    bindNotificationClicks() {
        const notificationsDropdown = document.getElementById('notifications-dropdown');
        
        if (notificationsDropdown) {
            // Délégation d'événements pour les éléments de notification
            notificationsDropdown.addEventListener('click', (event) => {
                const notificationItem = event.target.closest('[data-notification-item]');
                
                if (notificationItem) {
                    this.handleNotificationClick(notificationItem, event);
                }
            });
        }
    }

    /**
     * Gérer le clic sur une notification individuelle
     */
    handleNotificationClick(notificationElement, event) {
        // Empêcher la propagation pour éviter de fermer le dropdown
        event.stopPropagation();
        
        const notificationData = this.extractNotificationData(notificationElement);
        
        if (notificationData) {
            // Marquer visuellement comme lu immédiatement
            this.markNotificationAsReadVisually(notificationElement);
            
            // Envoyer la requête API
            this.markSingleNotificationRead(notificationData)
                .then(() => {
                    // Mettre à jour les compteurs
                    this.updateNotificationCounters();
                })
                .catch((error) => {
                    console.error('Erreur lors du marquage de la notification:', error);
                    // Restaurer l'état visuel en cas d'erreur
                    this.unmarkNotificationVisually(notificationElement);
                });
        }
    }

    /**
     * Extraire les données d'une notification depuis le DOM
     */
    extractNotificationData(element) {
        const materialId = element.getAttribute('data-material-id');
        const expressionId = element.getAttribute('data-expression-id');
        const sourceTable = element.getAttribute('data-source-table') || 'expression_dym';
        const notificationType = element.getAttribute('data-notification-type');
        const designation = element.getAttribute('data-designation') || 
                          element.querySelector('.notification-title')?.textContent.trim() || '';

        if (!materialId || !expressionId || !notificationType) {
            console.warn('Données de notification incomplètes:', {
                materialId, expressionId, sourceTable, notificationType
            });
            return null;
        }

        return {
            material_id: parseInt(materialId),
            expression_id: expressionId,
            source_table: sourceTable,
            notification_type: notificationType,
            designation: designation
        };
    }

    /**
     * Marquer visuellement une notification comme lue
     */
    markNotificationAsReadVisually(element) {
        element.classList.add('notification-read');
        element.style.opacity = '0.6';
        
        // Ajouter un indicateur visuel
        const readIndicator = document.createElement('span');
        readIndicator.className = 'notification-read-indicator';
        readIndicator.innerHTML = '<span class="material-icons text-xs text-green-600">check_circle</span>';
        
        const titleElement = element.querySelector('.notification-title');
        if (titleElement && !element.querySelector('.notification-read-indicator')) {
            titleElement.appendChild(readIndicator);
        }
    }

    /**
     * Restaurer l'état visuel non-lu (en cas d'erreur)
     */
    unmarkNotificationVisually(element) {
        element.classList.remove('notification-read');
        element.style.opacity = '1';
        
        const readIndicator = element.querySelector('.notification-read-indicator');
        if (readIndicator) {
            readIndicator.remove();
        }
    }

    /**
     * Marquer une notification comme lue via l'API
     */
    async markSingleNotificationRead(notificationData) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_single',
                    ...notificationData
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Erreur inconnue');
            }

            return result;
        } catch (error) {
            console.error('Erreur API markSingleNotificationRead:', error);
            throw error;
        }
    }

    /**
     * Ajouter les contrôles de marquage global
     */
    addNotificationMarkingControls() {
        const notificationsDropdown = document.getElementById('notifications-dropdown');
        
        if (notificationsDropdown) {
            // Chercher le footer existant ou le créer
            let footer = notificationsDropdown.querySelector('.notifications-footer');
            
            if (!footer) {
                footer = notificationsDropdown.querySelector('.bg-gray-50.px-4.py-3.border-t');
            }
            
            if (footer) {
                // Ajouter les boutons de contrôle avant le bouton "Voir tous les matériaux"
                const controlsHtml = `
                    <div class="flex justify-between items-center mb-3 gap-2">
                        <div class="flex gap-1">
                            <button id="mark-all-notifications-btn" 
                                    class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-700 px-2 py-1 rounded transition-colors"
                                    title="Marquer toutes les notifications comme lues">
                                <span class="material-icons text-xs mr-1">done_all</span>
                                Tout marquer
                            </button>
                            <button id="mark-urgent-notifications-btn" 
                                    class="text-xs bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded transition-colors"
                                    title="Marquer les urgents comme lus">
                                Urgents
                            </button>
                            <button id="mark-recent-notifications-btn" 
                                    class="text-xs bg-green-100 hover:bg-green-200 text-green-700 px-2 py-1 rounded transition-colors"
                                    title="Marquer les récents comme lus">
                                Récents
                            </button>
                        </div>
                        <button id="refresh-notifications-btn" 
                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 py-1 rounded transition-colors"
                                title="Actualiser les notifications">
                            <span class="material-icons text-xs">refresh</span>
                        </button>
                    </div>
                `;
                
                footer.insertAdjacentHTML('afterbegin', controlsHtml);
            }
        }
    }

    /**
     * Gérer les boutons de marquage global
     */
    bindGlobalMarkingButtons() {
        // Marquer toutes les notifications
        document.addEventListener('click', (event) => {
            if (event.target.id === 'mark-all-notifications-btn' || 
                event.target.closest('#mark-all-notifications-btn')) {
                this.markAllNotifications();
            }
        });

        // Marquer les notifications urgentes
        document.addEventListener('click', (event) => {
            if (event.target.id === 'mark-urgent-notifications-btn' || 
                event.target.closest('#mark-urgent-notifications-btn')) {
                this.markNotificationsByType('urgent');
            }
        });

        // Marquer les notifications récentes
        document.addEventListener('click', (event) => {
            if (event.target.id === 'mark-recent-notifications-btn' || 
                event.target.closest('#mark-recent-notifications-btn')) {
                this.markNotificationsByType('recent');
            }
        });

        // Actualiser les notifications
        document.addEventListener('click', (event) => {
            if (event.target.id === 'refresh-notifications-btn' || 
                event.target.closest('#refresh-notifications-btn')) {
                this.refreshNotifications();
            }
        });
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    async markAllNotifications() {
        try {
            const button = document.getElementById('mark-all-notifications-btn');
            this.setButtonLoading(button, true);

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_all'
                })
            });

            const result = await response.json();

            if (result.success) {
                // Marquer visuellement toutes les notifications
                this.markAllNotificationsVisually();
                
                // Mettre à jour les compteurs
                this.updateNotificationCounters();
                
                this.showNotificationFeedback('Toutes les notifications marquées comme lues', 'success');
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Erreur lors du marquage global:', error);
            this.showNotificationFeedback('Erreur lors du marquage des notifications', 'error');
        } finally {
            const button = document.getElementById('mark-all-notifications-btn');
            this.setButtonLoading(button, false);
        }
    }

    /**
     * Marquer les notifications d'un type spécifique
     */
    async markNotificationsByType(notificationType) {
        try {
            const button = document.getElementById(`mark-${notificationType}-notifications-btn`);
            this.setButtonLoading(button, true);

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_type',
                    notification_type: notificationType
                })
            });

            const result = await response.json();

            if (result.success) {
                // Marquer visuellement les notifications du type
                this.markNotificationsByTypeVisually(notificationType);
                
                // Mettre à jour les compteurs
                this.updateNotificationCounters();
                
                const typeLabel = notificationType === 'urgent' ? 'urgentes' : 'récentes';
                this.showNotificationFeedback(`Notifications ${typeLabel} marquées comme lues`, 'success');
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error(`Erreur lors du marquage ${notificationType}:`, error);
            this.showNotificationFeedback('Erreur lors du marquage des notifications', 'error');
        } finally {
            const button = document.getElementById(`mark-${notificationType}-notifications-btn`);
            this.setButtonLoading(button, false);
        }
    }

    /**
     * Marquer visuellement toutes les notifications
     */
    markAllNotificationsVisually() {
        const notifications = document.querySelectorAll('[data-notification-item]');
        notifications.forEach(notification => {
            this.markNotificationAsReadVisually(notification);
        });
    }

    /**
     * Marquer visuellement les notifications d'un type
     */
    markNotificationsByTypeVisually(type) {
        const notifications = document.querySelectorAll(`[data-notification-type="${type}"]`);
        notifications.forEach(notification => {
            this.markNotificationAsReadVisually(notification);
        });
    }

    /**
     * Mettre à jour les compteurs de notifications
     */
    async updateNotificationCounters() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_unread_count'
                })
            });

            const result = await response.json();

            if (result.success) {
                const unreadCount = result.data.unread_count;
                
                // Mettre à jour le badge principal
                const mainBadge = document.querySelector('#notifications-btn .notification-badge');
                if (mainBadge) {
                    if (unreadCount > 0) {
                        mainBadge.textContent = Math.min(unreadCount, 99);
                        mainBadge.style.display = 'flex';
                    } else {
                        mainBadge.style.display = 'none';
                    }
                }

                // Mettre à jour le compteur dans l'en-tête du dropdown
                const dropdownCounter = document.querySelector('#notifications-dropdown .bg-blue-100');
                if (dropdownCounter) {
                    dropdownCounter.textContent = unreadCount;
                }
            }
        } catch (error) {
            console.error('Erreur lors de la mise à jour des compteurs:', error);
        }
    }

    /**
     * Actualiser les notifications (recharger la page)
     */
    refreshNotifications() {
        const button = document.getElementById('refresh-notifications-btn');
        this.setButtonLoading(button, true);
        
        // Délay pour montrer le loading
        setTimeout(() => {
            window.location.reload();
        }, 500);
    }

    /**
     * Définir l'état de chargement d'un bouton
     */
    setButtonLoading(button, loading) {
        if (!button) return;

        if (loading) {
            button.disabled = true;
            button.classList.add('opacity-50', 'cursor-not-allowed');
            
            const icon = button.querySelector('.material-icons');
            if (icon) {
                icon.classList.add('animate-spin');
            }
        } else {
            button.disabled = false;
            button.classList.remove('opacity-50', 'cursor-not-allowed');
            
            const icon = button.querySelector('.material-icons');
            if (icon) {
                icon.classList.remove('animate-spin');
            }
        }
    }

    /**
     * Afficher un feedback à l'utilisateur
     */
    showNotificationFeedback(message, type = 'info') {
        // Créer un toast/notification temporaire
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white text-sm transform transition-all duration-300 translate-x-full`;
        
        if (type === 'success') {
            toast.classList.add('bg-green-500');
        } else if (type === 'error') {
            toast.classList.add('bg-red-500');
        } else {
            toast.classList.add('bg-blue-500');
        }
        
        toast.innerHTML = `
            <div class="flex items-center">
                <span class="material-icons text-sm mr-2">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>
                ${message}
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animation d'apparition
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
        }, 100);
        
        // Disparition automatique
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }

    /**
     * Gérer la fermeture du dropdown
     */
    bindDropdownClose() {
        document.addEventListener('click', (event) => {
            const dropdown = document.getElementById('notifications-dropdown');
            const button = document.getElementById('notifications-btn');
            
            if (dropdown && !dropdown.contains(event.target) && !button.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    }

    /**
     * Rafraîchissement périodique des compteurs
     */
    startPeriodicRefresh() {
        // Actualiser les compteurs toutes les 2 minutes
        setInterval(() => {
            this.updateNotificationCounters();
        }, 120000); // 2 minutes
    }
}

// Initialisation automatique quand le DOM est prêt
document.addEventListener('DOMContentLoaded', function() {
    // Attendre un peu pour s'assurer que la navbar est complètement chargée
    setTimeout(() => {
        if (typeof window.notificationsManager === 'undefined') {
            window.notificationsManager = new NotificationsReadManager();
        }
    }, 500);
});

// Export pour utilisation modulaire si nécessaire
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationsReadManager;
}