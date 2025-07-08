/**
 * DataTableManager.js
 * Gestionnaire des tableaux DataTables pour le service Finance - VERSION CORRIGÉE
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

class DataTableManager {
    constructor() {
        this.tables = {
            pending: null,
            signed: null
        };

        // Fonction personnalisée DataTables pour le filtrage des bons en attente
        this.pendingFilterFn = null;

        this.config = {
            language: {
                url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
            },
            responsive: true,
            pageLength: 15,
            lengthMenu: [[10, 15, 25, 50, -1], [10, 15, 25, 50, "Tout"]],
            order: [[1, 'desc']], // Tri par défaut (sera surchargé pour signed)
            dom: 'Blfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="material-icons">file_download</i> Excel',
                    className: 'btn-export'
                },
                {
                    extend: 'pdf',
                    text: '<i class="material-icons">picture_as_pdf</i> PDF',
                    className: 'btn-export'
                }
            ]
        };

        this.init();
    }

    /**
     * Initialisation du gestionnaire de tableaux
     */
    init() {
        this.bindEvents();
        console.log('✅ DataTableManager initialisé');
    }

    /**
     * Liaison des événements
     */
    bindEvents() {
        // Événements pour les boutons d'action - VERSION CORRIGÉE
        $(document).on('click', '.sign-btn', (e) => {
            e.preventDefault();
            const orderId = $(e.currentTarget).data('id');
            const orderNumber = $(e.currentTarget).data('order');

            if (window.financeManager) {
                window.financeManager.signOrder(orderId, orderNumber);
            }
        });

        /**
        * Liaison des événements - AJOUT ÉVÉNEMENT RÉVOQUER
        * À ajouter dans la méthode bindEvents() existante
        */

        // Événement pour le bouton révoquer un bon signé - NOUVEAU
        $(document).on('click', '.revoke-signed-btn', (e) => {
            e.preventDefault();
            const orderId = $(e.currentTarget).data('id');
            const orderNumber = $(e.currentTarget).data('order');

            if (window.financeManager) {
                // Utiliser la même méthode rejectOrder mais avec un message adapté
                window.financeManager.revokeSignedOrder(orderId, orderNumber);
            } else {
                console.error('❌ FinanceManager non disponible');
                alert('Erreur : Gestionnaire non disponible');
            }
        });

        // Événement pour le bouton rejeter - CORRIGÉ
        $(document).on('click', '.reject-btn', (e) => {
            e.preventDefault();
            const orderId = $(e.currentTarget).data('id');
            const orderNumber = $(e.currentTarget).data('order');

            // CORRECTION : Appeler directement la méthode rejectOrder du FinanceManager
            if (window.financeManager) {
                window.financeManager.rejectOrder(orderId, orderNumber);
            } else {
                console.error('❌ FinanceManager non disponible');
                alert('Erreur : Gestionnaire non disponible');
            }
        });

        // Événement pour le bouton supprimer définitivement - NOUVEAU
        $(document).on('click', '.delete-order-btn', (e) => {
            e.preventDefault();
            const orderId = $(e.currentTarget).data('id');
            const orderNumber = $(e.currentTarget).data('order');

            if (window.financeManager) {
                window.financeManager.deleteOrder(orderId, orderNumber);
            } else {
                console.error('❌ FinanceManager non disponible');
                alert('Erreur : Gestionnaire non disponible');
            }
        });

        $(document).on('click', '.view-signed-btn', (e) => {
            e.preventDefault();
            const bonId = $(e.currentTarget).data('id');

            if (window.financeManager && window.financeManager.components.modal) {
                window.financeManager.components.modal.showViewSignedModal(bonId);
            }
        });

        $(document).on('click', '.download-signed-btn', (e) => {
            e.preventDefault();
            const bonId = $(e.currentTarget).data('id');
            this.downloadSignedOrder(bonId);
        });

        // CORRECTION PRINCIPALE : Utiliser la bonne méthode
        $(document).on('click', '.view-details-btn', (e) => {
            e.preventDefault();
            const orderId = $(e.currentTarget).data('id');

            if (window.financeManager && window.financeManager.components.modal) {
                window.financeManager.components.modal.showOrderDetails(orderId);
            }
        });
    }

    /**
     * Initialisation du tableau des bons en attente
     */
    initializePendingTable(data) {
        console.log('📊 Initialisation tableau pending avec', data.length, 'éléments');

        if (!data || data.length === 0) {
            console.log('⚠️ Aucune donnée pour le tableau pending');
            this.hideTableContainer('pending');
            return false;
        }

        // Détruire le tableau existant s'il existe
        if (this.tables.pending) {
            this.tables.pending.destroy();
            this.tables.pending = null;
            $('#pending-table').empty();
        }

        // Nettoyer et valider les données
        const cleanedData = this.cleanTableData(data);
        console.log('✨ Données nettoyées pending:', cleanedData.length);

        if (cleanedData.length === 0) {
            this.hideTableContainer('pending');
            return false;
        }

        // Afficher le conteneur et masquer l'état vide
        this.showTableContainer('pending');

        try {
            // Initialiser le nouveau tableau
            this.tables.pending = $('#pending-table').DataTable({
                ...this.config,
                data: cleanedData,
                columns: this.getPendingColumns(),
                columnDefs: [
                    { orderable: false, targets: [6] }, // Actions non triables
                    { type: 'date-fr', targets: [1] }   // Type de date française
                ]
            });

            // Appliquer et configurer les filtres personnalisés
            this.setupPendingFilters(cleanedData);

            console.log(`✅ Tableau pending initialisé avec ${cleanedData.length} éléments`);
            return true;

        } catch (error) {
            console.error('❌ Erreur initialisation tableau pending:', error);
            this.hideTableContainer('pending');
            return false;
        }
    }

    /**
     * Initialisation du tableau des bons signés
     */
    initializeSignedTable(data) {
        console.log('📊 Initialisation tableau signed avec', data.length, 'éléments');

        if (!data || data.length === 0) {
            console.log('⚠️ Aucune donnée pour le tableau signed');
            this.hideTableContainer('signed');
            return false;
        }

        // Détruire le tableau existant s'il existe
        if (this.tables.signed) {
            this.tables.signed.destroy();
            this.tables.signed = null;
            $('#signed-table').empty();
        }

        // Nettoyer et valider les données
        const cleanedData = this.cleanTableData(data);
        console.log('✨ Données nettoyées signed:', cleanedData.length);

        if (cleanedData.length === 0) {
            this.hideTableContainer('signed');
            return false;
        }

        // Afficher le conteneur et masquer l'état vide
        this.showTableContainer('signed');

        try {
            // Initialiser le nouveau tableau avec tri par date de signature
            this.tables.signed = $('#signed-table').DataTable({
                ...this.config,
                data: cleanedData,
                columns: this.getSignedColumns(),
                columnDefs: [
                    { orderable: false, targets: [6] }, // Actions non triables
                    { type: 'date-fr', targets: [1, 5] }, // Dates françaises
                    { type: 'num', targets: [3] } // Montant numérique
                ],
                order: [[5, 'desc']] // TRI PAR DATE DE SIGNATURE DÉCROISSANTE (colonne 5)
            });

            console.log(`✅ Tableau signed initialisé avec ${cleanedData.length} éléments - Tri par date de signature`);
            return true;

        } catch (error) {
            console.error('❌ Erreur initialisation tableau signed:', error);
            this.hideTableContainer('signed');
            return false;
        }
    }

    /**
 * Initialisation du tableau des bons rejetés - NOUVELLE MÉTHODE
 */
    initializeRejectedTable(data) {
        console.log('📊 Initialisation tableau rejected avec', data.length, 'éléments');

        if (!data || data.length === 0) {
            console.log('⚠️ Aucune donnée pour le tableau rejected');
            this.hideTableContainer('rejected');
            return false;
        }

        // Détruire le tableau existant s'il existe
        if (this.tables.rejected) {
            this.tables.rejected.destroy();
            this.tables.rejected = null;
            $('#rejected-table').empty();
        }

        // Nettoyer et valider les données
        const cleanedData = this.cleanTableData(data);
        console.log('✨ Données nettoyées rejected:', cleanedData.length);

        if (cleanedData.length === 0) {
            this.hideTableContainer('rejected');
            return false;
        }

        // Afficher le conteneur et masquer l'état vide
        this.showTableContainer('rejected');

        try {
            // Initialiser le nouveau tableau avec tri par date de rejet
            this.tables.rejected = $('#rejected-table').DataTable({
                ...this.config,
                data: cleanedData,
                columns: this.getRejectedColumns(),
                columnDefs: [
                    { orderable: false, targets: [7] }, // Actions non triables
                    { type: 'date-fr', targets: [1, 5] }, // Dates françaises
                    { type: 'num', targets: [3] } // Montant numérique
                ],
                order: [[5, 'desc']] // TRI PAR DATE DE REJET DÉCROISSANTE
            });

            console.log(`✅ Tableau rejected initialisé avec ${cleanedData.length} éléments - Tri par date de rejet`);
            return true;

        } catch (error) {
            console.error('❌ Erreur initialisation tableau rejected:', error);
            this.hideTableContainer('rejected');
            return false;
        }
    }

    /**
     * Configuration des colonnes pour les bons rejetés - NOUVELLE MÉTHODE
     */
    getRejectedColumns() {
        return [
            {
                data: 'bon_number',
                title: 'N° Bon',
                render: (data) => `<span class="font-mono text-sm">${data || 'N/A'}</span>`
            },
            {
                data: 'formatted_created_at',
                title: 'Date de création',
                render: (data) => `<span class="text-gray-600">${data || 'N/A'}</span>`
            },
            {
                data: 'fournisseur',
                title: 'Fournisseur',
                render: (data) => `<span class="font-medium">${data || 'N/A'}</span>`
            },
            {
                data: 'montant',
                title: 'Montant',
                render: (data) => {
                    const amount = FinanceManager.formatAmount(data || 0);
                    return `<span class="font-semibold text-red-600">${amount}</span>`;
                }
            },
            {
                data: 'mode_paiement',
                title: 'Mode de paiement',
                render: (data) => `<span class="text-gray-700">${data || 'N/A'}</span>`
            },
            {
                data: 'formatted_rejected_at',
                title: 'Date de rejet',
                render: (data) => `<span class="text-red-600 font-medium">${data || 'N/A'}</span>`
            },
            {
                data: 'rejection_reason',
                title: 'Motif',
                render: (data) => {
                    const reason = data || 'Non spécifié';
                    const truncated = reason.length > 50 ? reason.substring(0, 50) + '...' : reason;
                    return `<span class="text-gray-700" title="${reason}">${truncated}</span>`;
                }
            },
            {
                data: null,
                title: 'Actions',
                render: (data, type, row) => this.renderRejectedActions(row)
            }
        ];
    }

    /**
         * Rendu des actions pour les bons rejetés - VERSION AVEC SUPPRESSION
         */
    renderRejectedActions(row) {
        const bonId = row.id || row.bon_number || 0;

        return `
        <div class="flex space-x-2">
            <button class="view-details-btn px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm font-medium transition-colors"
                    data-id="${bonId}"
                    title="Voir les détails">
                <i class="material-icons mr-1" style="font-size: 14px;">visibility</i>
                Détails
            </button>
            <button class="delete-order-btn px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-sm font-medium transition-colors"
                    data-id="${bonId}"
                    data-order="${row.bon_number || 'N/A'}"
                    title="Supprimer définitivement">
                <i class="material-icons mr-1" style="font-size: 14px;">delete_forever</i>
                Supprimer
            </button>
        </div>
    `;
    }

    /**
     * Affiche le conteneur de tableau et masque l'état vide
     */
    showTableContainer(type) {
        $(`#${type}-table-container`).removeClass('hidden').show();
        $(`#${type}-no-data`).addClass('hidden').hide();
        $(`#${type}-loading`).hide();

        console.log(`👁️ Conteneur ${type} affiché`);
    }

    /**
     * Masque le conteneur de tableau
     */
    hideTableContainer(type) {
        $(`#${type}-table-container`).addClass('hidden').hide();

        console.log(`🙈 Conteneur ${type} masqué`);
    }

    /**
     * Configuration des colonnes pour les bons en attente
     */
    getPendingColumns() {
        return [
            {
                data: 'order_number',
                title: 'N° Bon',
                render: (data) => `<span class="font-mono text-sm">${data || 'N/A'}</span>`
            },
            {
                data: 'formatted_date',
                title: 'Date de création',
                render: (data) => `<span class="text-gray-600">${data || 'N/A'}</span>`
            },
            {
                data: 'fournisseur',
                title: 'Fournisseur',
                render: (data) => `<span class="font-medium">${data || 'N/A'}</span>`
            },
            {
                data: 'montant_total',
                title: 'Montant',
                render: (data) => {
                    const amount = FinanceManager.formatAmount(data || 0);
                    return `<span class="font-semibold text-green-600">${amount}</span>`;
                }
            },
            {
                data: 'mode_paiement',
                title: 'Mode de paiement',
                render: (data) => `<span class="text-gray-700">${data || 'N/A'}</span>`
            },
            {
                data: 'username',
                title: 'Créé par',
                render: (data) => `<span class="text-gray-700">${data || 'N/A'}</span>`
            },
            {
                data: null,
                title: 'Actions',
                render: (data, type, row) => this.renderPendingActions(row)
            }
        ];
    }

    /**
     * Configuration des colonnes pour les bons signés
     */
    getSignedColumns() {
        return [
            {
                data: 'bon_number',
                title: 'N° Bon',
                render: (data) => `<span class="font-mono text-sm">${data || 'N/A'}</span>`
            },
            {
                data: 'formatted_created_at',
                title: 'Date de création',
                render: (data, type, row) => {
                    if (type === 'display') {
                        const formatted = data || FinanceManager.formatDate(row.created_at);
                        return `<span class="text-gray-600">${formatted}</span>`;
                    }
                    // Pour le tri, utiliser le timestamp
                    return row.created_at || '';
                }
            },
            {
                data: 'fournisseur',
                title: 'Fournisseur',
                render: (data) => `<span class="font-medium">${data || 'N/A'}</span>`
            },
            {
                data: 'montant',
                title: 'Montant',
                render: (data) => {
                    const amount = FinanceManager.formatAmount(data || 0);
                    return `<span class="font-semibold text-green-600">${amount}</span>`;
                }
            },
            {
                data: 'mode_paiement',
                title: 'Mode de paiement',
                render: (data) => `<span class="text-gray-700">${data || 'N/A'}</span>`
            },
            {
                data: 'formatted_signed_at',
                title: 'Date de signature',
                render: (data, type, row) => {
                    if (type === 'display') {
                        const formatted = data || FinanceManager.formatDate(row.signed_at);
                        return `<span class="text-blue-600 font-medium">${formatted}</span>`;
                    }
                    // Pour le tri, utiliser le timestamp de signature
                    return row.signed_at_timestamp || row.signed_at || '';
                }
            },
            {
                data: null,
                title: 'Actions',
                render: (data, type, row) => this.renderSignedActions(row)
            }
        ];
    }

    /**
     * Configuration spécifique pour les tableaux signés
     */
    getSignedTableConfig() {
        return {
            ...this.config,
            columnDefs: [
                { orderable: false, targets: [6] }, // Actions non triables
                { type: 'date-fr', targets: [1, 5] }, // Dates françaises
                { type: 'num', targets: [3] }, // Montant numérique
                {
                    targets: [5], // Colonne date de signature
                    type: 'date',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return row.formatted_signed_at || 'N/A';
                        }
                        // Pour le tri, retourner timestamp
                        return row.signed_at_timestamp || 0;
                    }
                }
            ],
            order: [[5, 'desc']], // Tri par date de signature décroissante
            language: {
                ...this.config.language,
                info: "Affichage de _START_ à _END_ sur _TOTAL_ bons signés (triés par date de signature)",
                infoFiltered: "(filtrés depuis un total de _MAX_ bons signés)"
            }
        };
    }

    /**
     * Rendu des actions pour les bons en attente - VERSION AVEC REJET
     */
    renderPendingActions(row) {
        const orderId = row.id || 0;
        const orderNumber = row.order_number || 'N/A';

        // CORRECTION : Corriger le chemin vers le fichier PDF
        let pdfPath = row.file_path || '#';
        if (pdfPath !== '#' && pdfPath.startsWith('purchase_orders/')) {
            pdfPath = '../User-Achat/gestion-bon-commande/' + pdfPath;
        } else if (pdfPath !== '#' && !pdfPath.startsWith('http') && !pdfPath.startsWith('/') && !pdfPath.startsWith('../')) {
            pdfPath = '../User-Achat/gestion-bon-commande/' + pdfPath;
        }

        return `
            <div class="flex space-x-2">
                <button class="sign-btn px-3 py-1 bg-green-500 hover:bg-green-600 text-white rounded text-sm font-medium transition-colors" 
                        data-id="${orderId}" 
                        data-order="${orderNumber}"
                        title="Signer ce bon">
                    <i class="material-icons mr-1" style="font-size: 14px;">edit</i>
                    Signer
                </button>
                <button class="reject-btn px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded text-sm font-medium transition-colors"
                        data-id="${orderId}"
                        data-order="${orderNumber}"
                        title="Rejeter ce bon">
                    <i class="material-icons mr-1" style="font-size: 14px;">cancel</i>
                    Rejeter
                </button>
                <button class="view-details-btn px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm font-medium transition-colors"
                        data-id="${orderId}"
                        title="Voir les détails">
                    <i class="material-icons mr-1" style="font-size: 14px;">visibility</i>
                    Détails
                </button>
                <a href="${pdfPath}" 
                   target="_blank" 
                   class="inline-block px-3 py-1 bg-purple-500 hover:bg-purple-600 text-white rounded text-sm font-medium transition-colors"
                   title="Voir le PDF">
                    <i class="material-icons mr-1" style="font-size: 14px;">description</i>
                    PDF
                </a>
            </div>
        `;
    }

    /**
     * Rendu des actions pour les bons signés - VERSION AVEC BOUTON REJETER
     */
    renderSignedActions(row) {
        const bonId = row.id || row.bon_number || 0;

        return `
        <div class="flex space-x-2">
            <button class="view-signed-btn px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm font-medium transition-colors"
                    data-id="${bonId}"
                    title="Voir le bon signé">
                <i class="material-icons mr-1" style="font-size: 14px;">visibility</i>
                Voir
            </button>
            <button class="download-signed-btn px-3 py-1 bg-green-500 hover:bg-green-600 text-white rounded text-sm font-medium transition-colors"
                    data-id="${bonId}"
                    title="Télécharger PDF validé">
                <i class="material-icons mr-1" style="font-size: 14px;">file_download</i>
                Télécharger
            </button>
            <button class="revoke-signed-btn px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded text-sm font-medium transition-colors"
                    data-id="${bonId}"
                    data-order="${row.bon_number || 'N/A'}"
                    title="Révoquer ce bon signé">
                <i class="material-icons mr-1" style="font-size: 14px;">cancel</i>
                Révoquer
            </button>
        </div>
    `;
    }

    /**
     * Nettoyage des données pour éviter les doublons
     */
    cleanTableData(data) {
        if (!Array.isArray(data)) {
            console.warn('⚠️ Données non valides pour le tableau:', typeof data);
            return [];
        }

        // Éliminer les doublons basés sur l'ID ou le numéro de bon
        const uniqueData = [];
        const seenIds = new Set();

        data.forEach(item => {
            if (!item || typeof item !== 'object') {
                console.warn('⚠️ Item non valide ignoré:', item);
                return;
            }

            const uniqueId = item.id || item.bon_number || item.order_number;
            if (uniqueId && !seenIds.has(uniqueId)) {
                seenIds.add(uniqueId);
                uniqueData.push(item);
            }
        });

        console.log(`🧹 Nettoyage: ${data.length} → ${uniqueData.length} éléments`);
        return uniqueData;
    }

    /**
     * Configure les filtres avancés pour le tableau des bons en attente
     */
    setupPendingFilters(data) {
        const table = this.tables.pending;
        if (!table) return;

        // Nettoyer tout filtre existant
        if (this.pendingFilterFn) {
            const idx = $.fn.dataTable.ext.search.indexOf(this.pendingFilterFn);
            if (idx !== -1) {
                $.fn.dataTable.ext.search.splice(idx, 1);
            }
            this.pendingFilterFn = null;
        }

        // Renseigner dynamiquement les modes de paiement
        const paymentSet = new Set();
        (data || []).forEach(item => {
            if (item.mode_paiement) {
                paymentSet.add(item.mode_paiement);
            }
        });
        const paymentSelect = $('#filter-payment');
        if (paymentSelect.length) {
            paymentSelect.empty().append('<option value="">Tous</option>');
            Array.from(paymentSet).forEach(label => {
                paymentSelect.append(`<option value="${label}">${label}</option>`);
            });
        }

        // Fonction de filtre personnalisée
        this.pendingFilterFn = (settings, searchData, index, rowData) => {
            if (settings.nTable.id !== 'pending-table') return true;

            const from = $('#filter-date-from').val();
            const to = $('#filter-date-to').val();
            const fournisseur = $('#filter-fournisseur').val().toLowerCase();
            const mode = $('#filter-payment').val();
            const minAmount = parseFloat($('#filter-min-amount').val()) || null;
            const maxAmount = parseFloat($('#filter-max-amount').val()) || null;

            const created = rowData.generated_at ? new Date(rowData.generated_at) : null;
            if (from && created && created < new Date(from)) return false;
            if (to && created && created > new Date(to + 'T23:59:59')) return false;

            if (fournisseur && rowData.fournisseur && !rowData.fournisseur.toLowerCase().includes(fournisseur)) {
                return false;
            }

            if (mode && rowData.mode_paiement !== mode) return false;

            const amount = parseFloat(rowData.montant_total || 0);
            if (minAmount !== null && amount < minAmount) return false;
            if (maxAmount !== null && amount > maxAmount) return false;

            return true;
        };
        $.fn.dataTable.ext.search.push(this.pendingFilterFn);

        // Bouton appliquer
        $('#apply-pending-filters').off('click').on('click', () => {
            table.draw();
        });

        // Bouton réinitialiser
        $('#reset-pending-filters').off('click').on('click', () => {
            $('#pending-filters input').val('');
            $('#pending-filters select').val('');
            table.draw();
        });
    }

    /**
         * Téléchargement d'un bon signé - VERSION CORRIGÉE ALTERNATIVE
         */
    downloadSignedOrder(bonId) {
        if (!bonId) {
            console.error('❌ ID de bon manquant pour le téléchargement');
            return;
        }

        console.log(`📥 Tentative de téléchargement du bon signé ID: ${bonId}`);

        // CORRECTION : Essayer plusieurs chemins possibles
        const possibleUrls = [
            `../User-Achat/gestion-bon-commande/api/download_validated_bon_commande.php?id=${bonId}`,
            `../User-Achat/gestion-bon-commande/api/download_bon_commande.php?id=${bonId}&validated=true`,
            `api/download_validated_bon_commande.php?id=${bonId}`,
            `../User-Achat/gestion-bon-commande/download_bon.php?id=${bonId}`
        ];

        // Tester le premier URL
        const testUrl = possibleUrls[0];

        // Vérifier d'abord si l'URL est accessible
        fetch(testUrl, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    // Si accessible, ouvrir pour téléchargement
                    this.triggerDownload(testUrl, bonId);
                } else {
                    // Essayer les autres URLs
                    this.tryAlternativeDownload(possibleUrls, bonId, 1);
                }
            })
            .catch(error => {
                console.error('❌ Erreur lors du test de l\'URL:', error);
                // En cas d'erreur, essayer quand même le téléchargement direct
                this.triggerDownload(testUrl, bonId);
            });
    }

    /**
     * Essaie les URLs alternatives pour le téléchargement
     */
    tryAlternativeDownload(urls, bonId, index) {
        if (index >= urls.length) {
            console.error('❌ Aucune URL de téléchargement fonctionnelle trouvée');
            alert('Erreur: Impossible de télécharger le fichier. Veuillez contacter l\'administrateur.');
            return;
        }

        const currentUrl = urls[index];
        console.log(`🔄 Test de l'URL alternative ${index}: ${currentUrl}`);

        fetch(currentUrl, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    this.triggerDownload(currentUrl, bonId);
                } else {
                    this.tryAlternativeDownload(urls, bonId, index + 1);
                }
            })
            .catch(error => {
                console.error(`❌ Erreur URL ${index}:`, error);
                this.tryAlternativeDownload(urls, bonId, index + 1);
            });
    }

    /**
     * Déclenche le téléchargement effectif
     */
    triggerDownload(url, bonId) {
        console.log(`📥 Téléchargement via: ${url}`);

        // Créer un lien temporaire pour forcer le téléchargement
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.download = `Bon_Commande_Signe_${bonId}.pdf`;

        // Déclencher le téléchargement
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        console.log(`✅ Téléchargement initié pour le bon ${bonId}`);
    }

    /**
     * Obtention du nombre total d'éléments
     */
    getTotalCount(tableType) {
        if (!this.tables[tableType]) {
            return 0;
        }

        return this.tables[tableType].data().length;
    }

    /**
     * Redimensionnement responsive des tableaux
     */
    recalculateResponsive() {
        Object.values(this.tables).forEach(table => {
            if (table && typeof table.responsive !== 'undefined') {
                table.responsive.recalc();
            }
        });
    }

    /**
     * Nettoyage et destruction
     */
    destroy() {
        // Détruire tous les tableaux
        Object.entries(this.tables).forEach(([key, table]) => {
            if (table) {
                table.destroy();
                this.tables[key] = null;
            }
        });

        if (this.pendingFilterFn) {
            const idx = $.fn.dataTable.ext.search.indexOf(this.pendingFilterFn);
            if (idx !== -1) {
                $.fn.dataTable.ext.search.splice(idx, 1);
            }
            this.pendingFilterFn = null;
        }

        // Nettoyer les événements
        $(document).off('.dataTableManager');

        console.log('🧹 DataTableManager nettoyé');
    }
}

// Export pour utilisation globale
window.DataTableManager = DataTableManager;