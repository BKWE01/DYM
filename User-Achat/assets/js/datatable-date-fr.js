/**
 * Plugin DataTables pour le tri des dates au format français - VERSION CORRIGÉE
 * 
 * Ce fichier ajoute le support complet des dates au format français (dd/mm/yyyy)
 * pour DataTables, permettant un tri correct des colonnes de dates.
 * 
 * Version améliorée avec gestion robuste des formats de date et recherche par produit
 */

// Ajout du type de tri pour les dates françaises
jQuery.extend(jQuery.fn.dataTableExt.oSort, {
    /**
     * Convertit une date française en timestamp pour comparaison
     * @param {string} dateStr - Date au format dd/mm/yyyy ou ISO
     * @returns {number} - Timestamp pour comparaison
     */
    "date-fr-pre": function (dateStr) {
        if (!dateStr || dateStr.trim() === '' || dateStr === 'N/A' || dateStr === null || dateStr === undefined || dateStr === 'Date invalide') {
            return 0;
        }

        // Si c'est déjà un timestamp
        if (typeof dateStr === 'number') {
            return dateStr;
        }

        // Conversion en string si nécessaire
        dateStr = String(dateStr).trim();

        // Vérifier si c'est un format ISO (yyyy-mm-dd ou yyyy-mm-dd hh:mm:ss)
        if (/^\d{4}-\d{2}-\d{2}/.test(dateStr)) {
            try {
                const date = new Date(dateStr);
                if (!isNaN(date.getTime())) {
                    return date.getTime();
                }
            } catch (e) {
                console.warn('Erreur parsing date ISO:', dateStr);
            }
        }

        // Extraction des parties de la date française (dd/mm/yyyy)
        const parts = dateStr.split('/');
        if (parts.length === 3) {
            const day = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10);
            const year = parseInt(parts[2], 10);
            
            if (!isNaN(day) && !isNaN(month) && !isNaN(year)) {
                const date = new Date(year, month - 1, day);
                if (!isNaN(date.getTime())) {
                    return date.getTime();
                }
            }
        }

        // Tentative de parsing direct
        try {
            const date = new Date(dateStr);
            if (!isNaN(date.getTime())) {
                return date.getTime();
            }
        } catch (e) {
            console.warn('Impossible de parser la date:', dateStr);
        }

        return 0;
    },

    /**
     * Tri croissant pour les dates françaises
     */
    "date-fr-asc": function (a, b) {
        return a - b;
    },

    /**
     * Tri décroissant pour les dates françaises
     */
    "date-fr-desc": function (b, a) {
        return a - b;
    }
});

/**
 * Fonction utilitaire pour formater une date en français - VERSION CORRIGÉE
 * @param {Date|string|number} date - Date à formater
 * @returns {string} - Date formatée au format dd/mm/yyyy
 */
function formatDateFr(date) {
    // Gestion des valeurs nulles ou undefined
    if (!date || date === null || date === undefined || date === '' || date === 'Date invalide') {
        return 'N/A';
    }

    let dateObj;

    try {
        // Si c'est déjà un objet Date
        if (date instanceof Date) {
            dateObj = date;
        }
        // Si c'est un nombre (timestamp)
        else if (typeof date === 'number') {
            dateObj = new Date(date);
        }
        // Si c'est une chaîne
        else if (typeof date === 'string') {
            const dateStr = date.trim();
            
            // Vérifier si c'est déjà au format français (dd/mm/yyyy)
            if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
                return dateStr; // Déjà au bon format
            }
            
            // Vérifier si c'est au format ISO (yyyy-mm-dd)
            if (/^\d{4}-\d{2}-\d{2}/.test(dateStr)) {
                dateObj = new Date(dateStr);
            }
            // Autres formats de date
            else {
                dateObj = new Date(dateStr);
            }
        }
        else {
            // Type non reconnu, tenter une conversion
            dateObj = new Date(date);
        }

        // Vérification de validité de la date
        if (isNaN(dateObj.getTime())) {
            console.warn('Date invalide détectée:', date);
            return 'Date invalide';
        }

        // Formatage au format français
        const day = String(dateObj.getDate()).padStart(2, '0');
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const year = dateObj.getFullYear();

        return `${day}/${month}/${year}`;

    } catch (error) {
        console.error('Erreur lors du formatage de la date:', date, error);
        return 'Erreur date';
    }
}

/**
 * Fonction utilitaire pour formater une date avec l'heure
 * @param {Date|string|number} date - Date à formater
 * @returns {string} - Date formatée au format dd/mm/yyyy HH:mm
 */
function formatDateTimeFr(date) {
    if (!date || date === null || date === undefined || date === '' || date === 'Date invalide') {
        return 'N/A';
    }

    try {
        let dateObj;
        
        if (date instanceof Date) {
            dateObj = date;
        } else if (typeof date === 'number') {
            dateObj = new Date(date);
        } else {
            dateObj = new Date(date);
        }

        if (isNaN(dateObj.getTime())) {
            return 'Date invalide';
        }

        const day = String(dateObj.getDate()).padStart(2, '0');
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const year = dateObj.getFullYear();
        const hours = String(dateObj.getHours()).padStart(2, '0');
        const minutes = String(dateObj.getMinutes()).padStart(2, '0');

        return `${day}/${month}/${year} ${hours}:${minutes}`;

    } catch (error) {
        console.error('Erreur lors du formatage de la date/heure:', date, error);
        return 'Erreur date';
    }
}

// Exposer les fonctions globalement pour utilisation dans d'autres scripts
window.formatDateFr = formatDateFr;
window.formatDateTimeFr = formatDateTimeFr;

// Amélioration du rendu des dates dans DataTables pour les résultats de recherche
jQuery.extend(true, jQuery.fn.dataTable.defaults, {
    language: {
        url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
    }
});