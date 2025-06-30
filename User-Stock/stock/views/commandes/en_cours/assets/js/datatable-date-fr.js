/**
 * Plugin DataTables pour le tri des dates au format français
 * Version optimisée avec meilleure gestion des dates récentes
 * 
 * Ce fichier ajoute le support complet des dates au format français (dd/mm/yyyy)
 * pour DataTables, permettant un tri correct des colonnes de dates.
 * /User-Stock/stock/views/commandes/en_cours/assets/js/datatable-date-fr.js
 */

// Détection automatique du type de données pour les dates françaises
$.fn.dataTable.ext.type.detect.unshift(function (data) {
    // Vérifier si les données correspondent au format français dd/mm/yyyy
    if (data && typeof data === 'string') {
        var frenchDatePattern = /^([0-3]?\d)\/([01]?\d)\/(\d{4})$/;
        if (frenchDatePattern.test(data.trim())) {
            return 'date-fr';
        }
    }
    return null;
});

// Ajout du type de tri pour les dates françaises
jQuery.extend(jQuery.fn.dataTableExt.oSort, {
    /**
     * Convertit une date française en timestamp pour comparaison
     * @param {string} dateStr - Date au format dd/mm/yyyy
     * @returns {number} - Timestamp pour comparaison
     */
    "date-fr-pre": function (dateStr) {
        // Gestion des valeurs vides ou nulles
        if (!dateStr || dateStr.trim() === '' || dateStr === 'N/A' || dateStr === '-') {
            return 0;
        }

        // Nettoyer la chaîne de date
        var cleanDateStr = dateStr.trim();
        
        // Extraction des parties de la date avec validation améliorée
        var parts = cleanDateStr.split('/');
        if (parts.length !== 3) {
            return 0;
        }

        var day = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);
        var year = parseInt(parts[2], 10);

        // Validation des composants de date
        if (isNaN(day) || isNaN(month) || isNaN(year)) {
            return 0;
        }

        // Validation des plages (jour 1-31, mois 1-12, année > 1900)
        if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1900) {
            return 0;
        }

        // Création de la date
        var date = new Date(year, month - 1, day);

        // Vérification que la date est valide
        if (isNaN(date.getTime())) {
            return 0;
        }

        return date.getTime();
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
    "date-fr-desc": function (a, b) {
        return b - a;
    }
});

/**
 * Fonction utilitaire pour formater une date en français
 * @param {Date|string|number} date - Date à formater
 * @returns {string} - Date formatée au format dd/mm/yyyy
 */
function formatDateFr(date) {
    if (!date) return 'N/A';

    var dateObj;

    // Si c'est un timestamp
    if (typeof date === 'number') {
        dateObj = new Date(date);
    }
    // Si c'est une chaîne, on tente de la parser
    else if (typeof date === 'string') {
        // Si déjà au format français, on la retourne telle quelle après validation
        var frenchDatePattern = /^([0-3]?\d)\/([01]?\d)\/(\d{4})$/;
        if (frenchDatePattern.test(date.trim())) {
            return date.trim();
        }
        
        // Sinon, essayer de parser comme une date standard
        dateObj = new Date(date);
    }
    // Si c'est déjà un objet Date
    else if (date instanceof Date) {
        dateObj = date;
    }
    else {
        return 'N/A';
    }

    // Vérification de validité
    if (isNaN(dateObj.getTime())) {
        return 'N/A';
    }

    // Formatage avec padding automatique
    var day = String(dateObj.getDate()).padStart(2, '0');
    var month = String(dateObj.getMonth() + 1).padStart(2, '0');
    var year = dateObj.getFullYear();

    return `${day}/${month}/${year}`;
}

/**
 * Fonction utilitaire pour calculer les jours entre deux dates
 * @param {string} dateStr - Date au format dd/mm/yyyy
 * @returns {number} - Nombre de jours depuis la date
 */
function calculateDaysSince(dateStr) {
    var timestamp = jQuery.fn.dataTableExt.oSort["date-fr-pre"](dateStr);
    if (timestamp === 0) return 0;
    
    var date = new Date(timestamp);
    var today = new Date();
    var diffTime = Math.abs(today - date);
    var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    return diffDays;
}

/**
 * Configuration globale pour DataTables avec support amélioré des dates françaises
 */
jQuery.extend(true, jQuery.fn.dataTable.defaults, {
    language: {
        url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
    },
    
    // Détection automatique et rendu des colonnes de dates
    columnDefs: [
        {
            // Application automatique du rendu pour les colonnes détectées comme dates françaises
            targets: '_all',
            render: function (data, type, row, meta) {
                // Pour l'affichage, formater les dates détectées
                if (type === 'display' && meta.settings.aoColumns[meta.col].sType === 'date-fr') {
                    return formatDateFr(data);
                }
                return data;
            }
        }
    ]
});