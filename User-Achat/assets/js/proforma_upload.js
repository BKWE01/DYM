/**
 * ========================================
 * GESTIONNAIRE D'UPLOAD DE PRO-FORMA POUR MODAL DYNAMIQUE
 * Fichier : /User-Achat/assets/js/proforma_upload.js
 * 
 * Cette version résout le problème d'upload dans la modal dynamique
 * en réinitialisant les événements à chaque ouverture de modal
 * ========================================
 */

const ProformaUploadManager = {
    // Configuration
    config: {
        maxFileSize: 10 * 1024 * 1024, // 10MB
        allowedTypes: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'],
        allowedMimeTypes: [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif'
        ]
    },

    // État actuel
    currentFile: null,
    isInitialized: false,
    context: document,

    /**
     * Initialise ou réinitialise le gestionnaire d'upload
     * Appelé à chaque ouverture de la modal d'achat groupé
     */
    init(context = document) {
        console.log('Initialisation ProformaUploadManager');
        this.context = context || document;
        
        // Nettoyer les anciens événements
        this.cleanup();
        
        // Réinitialiser l'état
        this.currentFile = null;
        this.resetUI();
        
        // Attacher les nouveaux événements
        this.attachEvents();
        
        this.isInitialized = true;
        console.log('ProformaUploadManager initialisé avec succès');
    },

    /**
     * Nettoie les anciens événements pour éviter les doublons
     */
    cleanup() {
        const fileInput = this.context.querySelector('#proforma-upload');
        const removeBtn = this.context.querySelector('#proforma-remove-file');
        
        if (fileInput) {
            // Cloner l'élément pour supprimer tous les événements
            const newFileInput = fileInput.cloneNode(true);
            fileInput.parentNode.replaceChild(newFileInput, fileInput);
        }
        
        if (removeBtn) {
            const newRemoveBtn = removeBtn.cloneNode(true);
            removeBtn.parentNode.replaceChild(newRemoveBtn, removeBtn);
        }
    },

    /**
     * Attache les événements aux éléments
     */
    attachEvents() {
        const fileInput = this.context.querySelector('#proforma-upload');
        const removeBtn = this.context.querySelector('#proforma-remove-file');
        
        if (fileInput) {
            fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
            console.log('Événement change attaché au file input');
        } else {
            console.warn('Élément proforma-upload non trouvé');
        }
        
        if (removeBtn) {
            removeBtn.addEventListener('click', (e) => this.removeFile(e));
            console.log('Événement click attaché au bouton remove');
        }
    },

    /**
     * Gère la sélection d'un fichier
     */
    handleFileSelect(event) {
        console.log('Fichier sélectionné', event);
        
        const file = event.target.files[0];
        
        if (!file) {
            this.resetUI();
            return;
        }

        // Validation du fichier
        const validation = this.validateFile(file);
        
        if (!validation.isValid) {
            this.showError(validation.message);
            event.target.value = ''; // Reset du file input
            this.resetUI();
            return;
        }

        // Stocker le fichier et mettre à jour l'UI
        this.currentFile = file;
        this.updateFileInfo(file);
        this.showSuccess('Fichier pro-forma sélectionné avec succès');
    },

    /**
     * Valide le fichier sélectionné
     */
    validateFile(file) {
        // Vérifier la taille
        if (file.size > this.config.maxFileSize) {
            return {
                isValid: false,
                message: `Le fichier est trop volumineux. Taille maximale autorisée : ${this.formatFileSize(this.config.maxFileSize)}`
            };
        }

        // Vérifier l'extension
        const fileName = file.name.toLowerCase();
        const fileExtension = fileName.split('.').pop();
        
        if (!this.config.allowedTypes.includes(fileExtension)) {
            return {
                isValid: false,
                message: `Type de fichier non autorisé. Types acceptés : ${this.config.allowedTypes.join(', ')}`
            };
        }

        // Vérifier le type MIME si disponible
        if (file.type && !this.config.allowedMimeTypes.includes(file.type)) {
            return {
                isValid: false,
                message: 'Type de fichier non reconnu par le navigateur'
            };
        }

        return { isValid: true };
    },

    /**
     * Met à jour l'affichage des informations du fichier
     */
    updateFileInfo(file) {
        const fileInfoDiv = this.context.querySelector('#proforma-file-info');
        const fileNameSpan = this.context.querySelector('#proforma-file-name');
        const fileSizeSpan = this.context.querySelector('#proforma-file-size');
        
        if (fileInfoDiv && fileNameSpan && fileSizeSpan) {
            fileNameSpan.textContent = file.name;
            fileSizeSpan.textContent = `(${this.formatFileSize(file.size)})`;
            fileInfoDiv.classList.remove('hidden');
        }
    },

    /**
     * Supprime le fichier sélectionné
     */
    removeFile(event) {
        event.preventDefault();
        console.log('Suppression du fichier');
        
        // Reset du file input
        const fileInput = this.context.querySelector('#proforma-upload');
        if (fileInput) {
            fileInput.value = '';
        }
        
        // Reset de l'état
        this.currentFile = null;
        this.resetUI();
        
        this.showInfo('Fichier pro-forma supprimé');
    },

    /**
     * Remet à zéro l'interface utilisateur
     */
    resetUI() {
        const fileInfoDiv = this.context.querySelector('#proforma-file-info');
        const progressDiv = this.context.querySelector('#proforma-upload-progress');
        
        if (fileInfoDiv) {
            fileInfoDiv.classList.add('hidden');
        }
        
        if (progressDiv) {
            progressDiv.classList.add('hidden');
        }
        
        this.hideProgress();
    },

    /**
     * Affiche la barre de progression
     */
    showProgress() {
        const progressDiv = this.context.querySelector('#proforma-upload-progress');
        if (progressDiv) {
            progressDiv.classList.remove('hidden');
        }
    },

    /**
     * Cache la barre de progression
     */
    hideProgress() {
        const progressDiv = this.context.querySelector('#proforma-upload-progress');
        if (progressDiv) {
            progressDiv.classList.add('hidden');
        }
    },

    /**
     * Met à jour la barre de progression
     */
    updateProgress(percent, text = null) {
        const progressBar = this.context.querySelector('#proforma-progress-bar');
        const progressText = this.context.querySelector('#proforma-progress-text');
        
        if (progressBar) {
            progressBar.style.width = `${percent}%`;
        }
        
        if (progressText && text) {
            progressText.textContent = text;
        }
    },

    /**
     * Vérifie si un fichier est sélectionné
     */
    hasFile() {
        return this.currentFile !== null;
    },

    /**
     * Retourne le fichier actuel
     */
    getFile() {
        return this.currentFile;
    },

    /**
     * Valide avant soumission du formulaire
     */
    validateForSubmission() {
        // Le pro-forma est optionnel, donc toujours valide
        return { isValid: true };
    },

    /**
     * Formate la taille du fichier pour l'affichage
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    /**
     * Affiche un message d'erreur
     */
    showError(message) {
        if (window.Swal) {
            Swal.fire({
                title: 'Erreur de fichier',
                text: message,
                icon: 'error',
                timer: 5000,
                showConfirmButton: false
            });
        } else {
            alert('Erreur: ' + message);
        }
    },

    /**
     * Affiche un message de succès
     */
    showSuccess(message) {
        if (window.Swal) {
            Swal.fire({
                title: 'Succès',
                text: message,
                icon: 'success',
                timer: 3000,
                showConfirmButton: false
            });
        }
    },

    /**
     * Affiche un message d'information
     */
    showInfo(message) {
        if (window.Swal) {
            Swal.fire({
                title: 'Information',
                text: message,
                icon: 'info',
                timer: 3000,
                showConfirmButton: false
            });
        }
    }
};

/**
 * Auto-initialisation quand le DOM est prêt
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM ready - ProformaUploadManager disponible');
});

/**
 * Exposition globale pour utilisation dans d'autres scripts
 */
window.ProformaUploadManager = ProformaUploadManager;