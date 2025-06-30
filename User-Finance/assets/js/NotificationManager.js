/**
 * NotificationManager.js
 * Gestionnaire des notifications pour le service Finance
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

class NotificationManager {
    constructor(options = {}) {
        this.config = {
            position: options.position || 'top-right',
            autoClose: options.autoClose !== false,
            duration: options.duration || 5000,
            maxNotifications: options.maxNotifications || 5,
            useToast: options.useToast !== false,
            useSweetAlert: options.useSweetAlert !== false,
            sounds: options.sounds || false
        };
        
        this.notifications = [];
        this.container = null;
        
        this.init();
    }
    
    /**
     * Initialisation du gestionnaire de notifications
     */
    init() {
        this.createContainer();
        this.bindEvents();
        console.log('✅ NotificationManager initialisé');
    }
    
    /**
     * Création du conteneur de notifications
     */
    createContainer() {
        if (this.container) {
            return;
        }
        
        this.container = document.createElement('div');
        this.container.id = 'notification-container';
        this.container.className = `notification-container ${this.config.position}`;
        
        // Styles CSS pour le conteneur
        const styles = this.getContainerStyles();
        this.container.style.cssText = styles;
        
        document.body.appendChild(this.container);
    }
    
    /**
     * Styles CSS pour le conteneur
     */
    getContainerStyles() {
        const positions = {
            'top-right': 'top: 20px; right: 20px;',
            'top-left': 'top: 20px; left: 20px;',
            'bottom-right': 'bottom: 20px; right: 20px;',
            'bottom-left': 'bottom: 20px; left: 20px;',
            'top-center': 'top: 20px; left: 50%; transform: translateX(-50%);',
            'bottom-center': 'bottom: 20px; left: 50%; transform: translateX(-50%);'
        };
        
        return `
            position: fixed;
            ${positions[this.config.position] || positions['top-right']}
            z-index: 9999;
            max-width: 400px;
            pointer-events: none;
        `;
    }
    
    /**
     * Liaison des événements
     */
    bindEvents() {
        // Écouter les événements personnalisés
        $(document).on('notification:show', (e, message, type, options) => {
            this.show(message, type, options);
        });
        
        $(document).on('notification:clear', () => {
            this.clearAll();
        });
    }
    
    /**
     * Affichage d'une notification
     */
    show(message, type = 'info', options = {}) {
        // Valider les paramètres
        if (!message || typeof message !== 'string') {
            console.error('Message de notification invalide');
            return;
        }
        
        // Types de notification supportés
        const validTypes = ['success', 'error', 'warning', 'info'];
        if (!validTypes.includes(type)) {
            type = 'info';
        }
        
        // Fusionner les options
        const notificationOptions = {
            ...this.config,
            ...options,
            id: this.generateId(),
            message,
            type,
            timestamp: new Date()
        };
        
        // Choisir la méthode d'affichage
        if (this.config.useSweetAlert && typeof Swal !== 'undefined') {
            this.showSweetAlert(notificationOptions);
        } else if (this.config.useToast) {
            this.showToast(notificationOptions);
        } else {
            this.showCustomNotification(notificationOptions);
        }
        
        // Jouer un son si activé
        if (this.config.sounds) {
            this.playSound(type);
        }
        
        return notificationOptions.id;
    }
    
    /**
     * Affichage avec SweetAlert
     */
    showSweetAlert(options) {
        const swalOptions = {
            icon: this.getSweetAlertIcon(options.type),
            title: this.getNotificationTitle(options.type),
            text: options.message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: options.duration,
            timerProgressBar: true
        };
        
        Swal.fire(swalOptions);
    }
    
    /**
     * Affichage avec Toast personnalisé
     */
    showToast(options) {
        // Limiter le nombre de notifications
        if (this.notifications.length >= this.config.maxNotifications) {
            this.removeOldest();
        }
        
        // Créer l'élément de notification
        const notificationElement = this.createNotificationElement(options);
        
        // Ajouter au conteneur
        this.container.appendChild(notificationElement);
        this.notifications.push({
            id: options.id,
            element: notificationElement,
            options
        });
        
        // Animation d'entrée
        this.animateIn(notificationElement);
        
        // Suppression automatique
        if (options.autoClose) {
            setTimeout(() => {
                this.remove(options.id);
            }, options.duration);
        }
    }
    
    /**
     * Affichage avec notification personnalisée
     */
    showCustomNotification(options) {
        this.showToast(options); // Utiliser le système de toast par défaut
    }
    
    /**
     * Création de l'élément de notification
     */
    createNotificationElement(options) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${options.type}`;
        notification.id = `notification-${options.id}`;
        notification.style.cssText = this.getNotificationStyles(options.type);
        
        // Contenu de la notification
        const content = `
            <div class="notification-content">
                <div class="notification-header">
                    <span class="notification-icon">${this.getIcon(options.type)}</span>
                    <span class="notification-title">${this.getNotificationTitle(options.type)}</span>
                    <button class="notification-close" onclick="window.notificationManager?.remove('${options.id}')">
                        <span class="material-icons">close</span>
                    </button>
                </div>
                <div class="notification-message">${options.message}</div>
                ${options.duration ? `<div class="notification-progress"><div class="notification-progress-bar"></div></div>` : ''}
            </div>
        `;
        
        notification.innerHTML = content;
        
        // Activer les pointeurs d'événements pour cette notification
        notification.style.pointerEvents = 'auto';
        
        return notification;
    }
    
    /**
     * Styles CSS pour les notifications
     */
    getNotificationStyles(type) {
        const baseStyles = `
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            max-width: 100%;
            word-wrap: break-word;
            font-family: 'Inter', sans-serif;
        `;
        
        const typeStyles = {
            success: 'background: #d1fae5; border-left: 4px solid #10b981; color: #065f46;',
            error: 'background: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b;',
            warning: 'background: #fef3c7; border-left: 4px solid #f59e0b; color: #92400e;',
            info: 'background: #dbeafe; border-left: 4px solid #3b82f6; color: #1e40af;'
        };
        
        return baseStyles + (typeStyles[type] || typeStyles.info);
    }
    
    /**
     * Obtention de l'icône selon le type
     */
    getIcon(type) {
        const icons = {
            success: '<span class="material-icons" style="color: #10b981;">check_circle</span>',
            error: '<span class="material-icons" style="color: #ef4444;">error</span>',
            warning: '<span class="material-icons" style="color: #f59e0b;">warning</span>',
            info: '<span class="material-icons" style="color: #3b82f6;">info</span>'
        };
        
        return icons[type] || icons.info;
    }
    
    /**
     * Obtention du titre selon le type
     */
    getNotificationTitle(type) {
        const titles = {
            success: 'Succès',
            error: 'Erreur',
            warning: 'Attention',
            info: 'Information'
        };
        
        return titles[type] || titles.info;
    }
    
    /**
     * Obtention de l'icône SweetAlert
     */
    getSweetAlertIcon(type) {
        const icons = {
            success: 'success',
            error: 'error',
            warning: 'warning',
            info: 'info'
        };
        
        return icons[type] || icons.info;
    }
    
    /**
     * Animation d'entrée
     */
    animateIn(element) {
        element.style.opacity = '0';
        element.style.transform = 'translateX(100%)';
        
        setTimeout(() => {
            element.style.opacity = '1';
            element.style.transform = 'translateX(0)';
        }, 10);
    }
    
    /**
     * Animation de sortie
     */
    animateOut(element, callback) {
        element.style.opacity = '0';
        element.style.transform = 'translateX(100%)';
        
        setTimeout(() => {
            if (typeof callback === 'function') {
                callback();
            }
        }, 300);
    }
    
    /**
     * Suppression d'une notification
     */
    remove(notificationId) {
        const notification = this.notifications.find(n => n.id === notificationId);
        
        if (!notification) {
            return;
        }
        
        this.animateOut(notification.element, () => {
            // Supprimer de la DOM
            if (notification.element.parentNode) {
                notification.element.parentNode.removeChild(notification.element);
            }
            
            // Supprimer du tableau
            const index = this.notifications.findIndex(n => n.id === notificationId);
            if (index > -1) {
                this.notifications.splice(index, 1);
            }
        });
    }
    
    /**
     * Suppression de la plus ancienne notification
     */
    removeOldest() {
        if (this.notifications.length > 0) {
            const oldest = this.notifications[0];
            this.remove(oldest.id);
        }
    }
    
    /**
     * Suppression de toutes les notifications
     */
    clearAll() {
        this.notifications.forEach(notification => {
            this.remove(notification.id);
        });
    }
    
    /**
     * Génération d'un ID unique
     */
    generateId() {
        return 'notif_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    /**
     * Lecture d'un son de notification
     */
    playSound(type) {
        // Sons différents selon le type
        const frequencies = {
            success: [523, 659, 784], // Do, Mi, Sol
            error: [220, 196],         // La, Sol bémol
            warning: [392, 440],       // Sol, La
            info: [440]                // La
        };
        
        if (!frequencies[type]) return;
        
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            frequencies[type].forEach((freq, index) => {
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.setValueAtTime(freq, audioContext.currentTime);
                oscillator.type = 'sine';
                
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
                
                oscillator.start(audioContext.currentTime + index * 0.1);
                oscillator.stop(audioContext.currentTime + 0.2 + index * 0.1);
            });
        } catch (error) {
            console.warn('Impossible de jouer le son de notification:', error);
        }
    }
    
    /**
     * Méthodes de raccourci
     */
    success(message, options = {}) {
        return this.show(message, 'success', options);
    }
    
    error(message, options = {}) {
        return this.show(message, 'error', options);
    }
    
    warning(message, options = {}) {
        return this.show(message, 'warning', options);
    }
    
    info(message, options = {}) {
        return this.show(message, 'info', options);
    }
    
    /**
     * Obtention du nombre de notifications actives
     */
    getCount() {
        return this.notifications.length;
    }
    
    /**
     * Vérification si une notification existe
     */
    exists(notificationId) {
        return this.notifications.some(n => n.id === notificationId);
    }
    
    /**
     * Mise à jour d'une notification existante
     */
    update(notificationId, newMessage) {
        const notification = this.notifications.find(n => n.id === notificationId);
        
        if (notification) {
            const messageElement = notification.element.querySelector('.notification-message');
            if (messageElement) {
                messageElement.textContent = newMessage;
            }
        }
    }
    
    /**
     * Configuration en temps réel
     */
    configure(newConfig) {
        this.config = { ...this.config, ...newConfig };
    }
    
    /**
     * Nettoyage et destruction
     */
    destroy() {
        this.clearAll();
        
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
        
        $(document).off('.notificationManager');
        
        console.log('🧹 NotificationManager nettoyé');
    }
}

// Styles CSS globaux pour les notifications
const notificationCSS = `
    <style id="notification-manager-styles">
        .notification-content {
            position: relative;
        }
        
        .notification-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .notification-icon {
            margin-right: 8px;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 14px;
        }
        
        .notification-close {
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .notification-close:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .notification-close .material-icons {
            font-size: 18px;
        }
        
        .notification-message {
            font-size: 13px;
            line-height: 1.4;
            margin-bottom: 8px;
        }
        
        .notification-progress {
            height: 3px;
            background-color: rgba(0, 0, 0, 0.1);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .notification-progress-bar {
            height: 100%;
            background-color: currentColor;
            opacity: 0.7;
            animation: progress-countdown var(--duration, 5s) linear forwards;
        }
        
        @keyframes progress-countdown {
            from { width: 100%; }
            to { width: 0%; }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .notification-container {
                left: 10px !important;
                right: 10px !important;
                max-width: calc(100% - 20px) !important;
                transform: none !important;
            }
            
            .notification {
                margin-bottom: 5px;
                padding: 12px;
            }
        }
    </style>
`;

// Injecter le CSS
if (!document.getElementById('notification-manager-styles')) {
    document.head.insertAdjacentHTML('beforeend', notificationCSS);
}

// Export pour utilisation globale
window.NotificationManager = NotificationManager;