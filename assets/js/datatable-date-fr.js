/**
 * Plugin DataTables pour le tri des dates au format français
 * 
 * Ce fichier ajoute le support complet des dates au format français (dd/mm/yyyy)
 * pour DataTables, permettant un tri correct des colonnes de dates.
 */

// Ajout du type de tri pour les dates françaises
jQuery.extend(jQuery.fn.dataTableExt.oSort, {
    /**
     * Convertit une date française en timestamp pour comparaison
     * @param {string} dateStr - Date au format dd/mm/yyyy
     * @returns {number} - Timestamp pour comparaison
     */
    "date-fr-pre": function (dateStr) {
        if (!dateStr || dateStr.trim() === '' || dateStr === 'N/A') {
            return 0;
        }

        // Extraction des parties de la date
        var parts = dateStr.split('/');
        if (parts.length !== 3) {
            return 0;
        }

        var day = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);
        var year = parseInt(parts[2], 10);

        // Création de la date
        var date = new Date(year, month - 1, day);

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
    "date-fr-desc": function (b, a) {
        return a - b;
    }
});

/**
 * Fonction utilitaire pour formater une date en français
 * @param {Date|string} date - Date à formater
 * @returns {string} - Date formatée au format dd/mm/yyyy
 */
function formatDateFr(date) {
    if (!date) return 'N/A';

    // Si c'est une chaîne, on tente de la parser
    if (typeof date === 'string') {
        date = new Date(date);
    }

    // Vérification de validité
    if (isNaN(date.getTime())) {
        return 'N/A';
    }

    // Formatage
    var day = String(date.getDate()).padStart(2, '0');
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var year = date.getFullYear();

    return `${day}/${month}/${year}`;
}

/**
 * Configuration globale pour DataTables avec support des dates françaises
 */
jQuery.extend(true, jQuery.fn.dataTable.defaults, {
    language: {
        url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
    },
    // Détection automatique des colonnes de dates
    columnDefs: [
        {
            targets: '_all',
            render: function (data, type, row, meta) {
                // Si c'est une colonne de date et qu'on veut l'afficher
                if (type === 'display' && meta.settings.aoColumns[meta.col].sType === 'date-fr') {
                    return formatDateFr(data);
                }
                return data;
            }
        }
    ]
});