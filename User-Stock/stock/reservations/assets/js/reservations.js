/**
 * Gestionnaire des réservations - DYM STOCK
 * Optimisé pour les performances et la maintenabilité
 */

class ReservationsManager {
    constructor() {
        this.reservationsTable = null;
        this.allReservations = [];
        this.loadingInProgress = false;
        this.init();
    }

    // ==================== INITIALISATION ====================
    
    init() {
        this.bindEvents();
        this.updateDateTime();
        this.startDateTimeUpdater();
        this.loadReservations();
    }

    // ==================== GESTION DES DONNÉES ====================

    async loadReservations() {
        if (this.loadingInProgress) return;
        
        this.loadingInProgress = true;
        this.showLoadingState();

        try {
            const response = await this.apiCall('api/get_reserved_products.php');
            
            if (response.success) {
                this.allReservations = response.reservations;
                this.updateStatistics(this.allReservations);
                this.populateTable(this.allReservations);
                this.hideLoadingState();
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Erreur lors du chargement des réservations:', error);
            this.showNotification('Erreur lors du chargement des réservations', 'error');
            this.hideLoadingState();
        } finally {
            this.loadingInProgress = false;
        }
    }

    async loadProjectDetails(projectId) {
        try {
            const response = await this.apiCall('api/get_project_details.php', { project_id: projectId });
            
            if (response.success) {
                this.displayProjectDetails(response);
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Erreur lors du chargement des détails du projet:', error);
            this.showNotification('Erreur lors du chargement des détails du projet', 'error');
        }
    }

    async releaseReservation(reservationId, projectId, productId) {
        try {
            const response = await this.apiCall('api/release_reservation.php', {
                project_id: projectId,
                product_id: productId
            }, 'POST');

            this.closeReleaseModal();
            
            if (response.success) {
                this.showNotification('La réservation a été libérée avec succès', 'success');
                this.loadReservations();
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Erreur lors de la libération:', error);
            this.showNotification('Erreur: ' + error.message, 'error');
        }
    }

    // ==================== GESTION DU TABLEAU ====================

    initializeDataTable() {
        this.reservationsTable = $('#reservations-table').DataTable({
            responsive: true,
            processing: true,
            deferRender: true,
            language: {
                url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json"
            },
            dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'asc']],
            columnDefs: [
                { targets: [5], className: 'text-center font-semibold text-blue-600' },
                { targets: [6], className: 'text-center' },
                { targets: [7], className: 'text-center' },
                { targets: -1, orderable: false, searchable: false, className: 'text-center' }
            ],
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Excel',
                    title: 'Détails des Réservations',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7],
                        format: {
                            body: (data) => $(data).text() || data
                        }
                    },
                    className: 'hidden'
                },
                {
                    extend: 'pdfHtml5',
                    text: 'PDF',
                    title: 'Détails des Réservations',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7],
                        format: {
                            body: (data) => $(data).text() || data
                        }
                    },
                    className: 'hidden',
                    orientation: 'landscape',
                    pageSize: 'A4'
                }
            ]
        });

        this.reservationsTable.buttons().container().appendTo($('.dataTables_length').parent());
    }

    populateTable(reservations) {
        if (!this.reservationsTable) {
            this.initializeDataTable();
        }

        const tableData = reservations.map(reservation => this.formatTableRow(reservation));
        this.reservationsTable.clear().rows.add(tableData).draw(false);
    }

    formatTableRow(reservation) {
        const statusConfig = this.getStatusConfig(reservation.status);
        
        return [
            this.formatProjectColumn(reservation),
            reservation.barcode || '',
            reservation.product_name || '',
            reservation.unit || '',
            reservation.category_name || '',
            parseFloat(reservation.reserved_quantity).toFixed(2),
            this.formatStockColumn(reservation),
            this.formatStatusBadge(statusConfig),
            this.formatActionButton(reservation)
        ];
    }

    formatProjectColumn(reservation) {
        return `
            <div class="flex items-center">
                <div class="ml-1">
                    <div class="project-link project-details-link cursor-pointer text-blue-600 hover:text-blue-800" 
                         data-project-id="${reservation.project_id}">
                        ${reservation.project_code}
                    </div>
                    <div class="text-xs text-gray-500">${reservation.project_name}</div>
                </div>
            </div>
        `;
    }

    formatStockColumn(reservation) {
        const colorClass = {
            'available': 'text-green-600',
            'partial': 'text-yellow-600',
            'unavailable': 'text-red-600'
        }[reservation.status] || 'text-red-600';

        return `
            <span class="font-semibold ${colorClass}">
                ${parseFloat(reservation.available_quantity).toFixed(2)} / ${parseFloat(reservation.total_quantity).toFixed(2)}
            </span>
        `;
    }

    formatStatusBadge(statusConfig) {
        return `
            <span class="status-badge ${statusConfig.class}">
                <span class="material-icons">${statusConfig.icon}</span>
                ${statusConfig.text}
            </span>
        `;
    }

    formatActionButton(reservation) {
        return `
            <button class="action-btn btn-release release-reservation-btn"
                    data-reservation-id="${reservation.id}"
                    data-project-id="${reservation.project_id}"
                    data-project-name="${reservation.project_code} - ${reservation.project_name}"
                    data-product-id="${reservation.product_id}"
                    data-product-name="${reservation.product_name}"
                    data-quantity="${reservation.reserved_quantity}"
                    ${reservation.can_release ? '' : 'disabled'}>
                <span class="material-icons" style="font-size: 14px;">delete</span>
            </button>
        `;
    }

    // ==================== GESTION DES MODALS ====================

    openReleaseModal(reservationData) {
        $('#releaseProject').text(reservationData.projectName);
        $('#releaseProduct').text(reservationData.productName);
        $('#releaseQuantity').text(parseFloat(reservationData.quantity).toFixed(2));

        $('#confirmReleaseBtn')
            .data('reservation-id', reservationData.reservationId)
            .data('project-id', reservationData.projectId)
            .data('product-id', reservationData.productId);

        this.showModal('#releaseModal');
    }

    closeReleaseModal() {
        this.hideModal('#releaseModal');
    }

    openProjectDetailsModal() {
        this.showModal('#projectDetailsModal');
    }

    closeProjectDetailsModal() {
        this.hideModal('#projectDetailsModal');
    }

    showModal(modalSelector) {
        $(modalSelector).removeClass('hidden');
        setTimeout(() => {
            $(`${modalSelector} .modal-overlay`).addClass('opacity-100');
            $(`${modalSelector} .modal-content`).removeClass('opacity-0 scale-95').addClass('opacity-100 scale-100');
        }, 50);
    }

    hideModal(modalSelector) {
        $(`${modalSelector} .modal-overlay`).removeClass('opacity-100');
        $(`${modalSelector} .modal-content`).removeClass('opacity-100 scale-100').addClass('opacity-0 scale-95');
        setTimeout(() => {
            $(modalSelector).addClass('hidden');
        }, 300);
    }

    displayProjectDetails(response) {
        const project = response.project;
        const products = response.products;

        $('#projectDetailsTitle').text(`Détails du projet: ${project.code_projet}`);
        $('#projectCode').text(project.code_projet);
        $('#projectClient').text(project.nom_client);
        $('#projectDescription').text(project.description_projet);
        $('#projectLocation').text(project.sitgeo);
        $('#projectManager').text(project.chefprojet);

        const productsList = $('#projectProductsTable');
        productsList.empty();

        if (products.length === 0) {
            productsList.html(`
                <tr>
                    <td colspan="3" class="px-4 py-4 text-center text-gray-500">
                        Aucun produit réservé pour ce projet
                    </td>
                </tr>
            `);
        } else {
            const rows = products.map(product => {
                const statusConfig = this.getStatusConfig(product.status);
                return `
                    <tr>
                        <td class="px-4 py-2">
                            <div class="text-sm font-medium text-gray-900">${product.product_name}</div>
                            <div class="text-xs text-gray-500">${product.barcode}</div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-blue-600">
                            ${parseFloat(product.reserved_quantity).toFixed(2)} ${product.unit}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            ${this.formatStatusBadge(statusConfig)}
                        </td>
                    </tr>
                `;
            }).join('');

            productsList.html(rows);
        }

        this.openProjectDetailsModal();
    }

    // ==================== FONCTIONS UTILITAIRES ====================

    getStatusConfig(status) {
        const configs = {
            'available': { class: 'badge-success', icon: 'check_circle', text: 'Disponible' },
            'partial': { class: 'badge-warning', icon: 'warning', text: 'Partiel' },
            'unavailable': { class: 'badge-danger', icon: 'error', text: 'Non disponible' }
        };
        return configs[status] || configs['unavailable'];
    }

    async apiCall(url, data = {}, method = 'GET') {
        const config = {
            url: url,
            type: method,
            dataType: 'json',
            timeout: 30000,
            cache: method === 'GET'
        };

        if (method === 'POST') {
            config.data = data;
        } else if (Object.keys(data).length > 0) {
            config.data = data;
        }

        return new Promise((resolve, reject) => {
            $.ajax(config)
                .done(resolve)
                .fail((xhr, status, error) => {
                    reject(new Error(error || 'Erreur de connexion'));
                });
        });
    }

    showNotification(message, type = 'success') {
        const backgroundColor = type === 'success' ? '#4CAF50' : '#F44336';
        const icon = type === 'success' ? 'check_circle' : 'error_outline';

        Toastify({
            text: `
                <div class="flex items-center">
                    <span class="material-icons mr-2" style="font-size: 18px;">${icon}</span>
                    <span>${message}</span>
                </div>
            `,
            duration: 3000,
            close: false,
            gravity: "top",
            position: "right",
            style: { background: backgroundColor },
            stopOnFocus: true,
            escapeMarkup: false,
        }).showToast();
    }

    updateDateTime() {
        const now = new Date();
        const options = {
            weekday: 'long', year: 'numeric', month: 'long',
            day: 'numeric', hour: '2-digit', minute: '2-digit'
        };
        const element = document.getElementById('date-time-display');
        if (element) {
            element.textContent = now.toLocaleDateString('fr-FR', options);
        }
    }

    startDateTimeUpdater() {
        setInterval(() => this.updateDateTime(), 60000);
    }

    updateStatistics(reservations) {
        const total = reservations.length;
        const available = reservations.filter(r => r.status === 'available').length;
        const partial = reservations.filter(r => r.status === 'partial').length;
        const unavailable = reservations.filter(r => r.status === 'unavailable').length;

        $('#totalReservations').text(total);
        $('#availableReservations').text(available);
        $('#unavailableReservations').text(unavailable + (partial ? ` (+ ${partial} partiels)` : ''));
    }

    showLoadingState() {
        $('#statsContainer .stat-number').text('...');
        if (this.reservationsTable) {
            this.reservationsTable.clear().draw();
        }
    }

    hideLoadingState() {
        // La mise à jour des stats se fait automatiquement via updateStatistics
    }

    // ==================== GESTIONNAIRES D'ÉVÉNEMENTS ====================

    bindEvents() {
        // Boutons d'export
        $('#export-excel').on('click', () => {
            if (this.reservationsTable) {
                this.reservationsTable.button('.buttons-excelHtml5').trigger();
            }
        });

        $('#export-pdf').on('click', () => {
            if (this.reservationsTable) {
                this.reservationsTable.button('.buttons-pdfHtml5').trigger();
            }
        });

        // Actualiser les données
        let refreshTimeout;
        $('#refresh-data').on('click', () => {
            clearTimeout(refreshTimeout);
            refreshTimeout = setTimeout(() => {
                this.loadReservations();
                this.showNotification('Données actualisées', 'success');
            }, 300);
        });

        // Événements délégués pour les éléments dynamiques
        $(document).on('click', '.project-details-link', (e) => {
            const projectId = $(e.currentTarget).data('project-id');
            this.loadProjectDetails(projectId);
        });

        $(document).on('click', '.release-reservation-btn', (e) => {
            const btn = $(e.currentTarget);
            if (!btn.prop('disabled')) {
                const reservationData = {
                    reservationId: btn.data('reservation-id'),
                    projectId: btn.data('project-id'),
                    projectName: btn.data('project-name'),
                    productId: btn.data('product-id'),
                    productName: btn.data('product-name'),
                    quantity: btn.data('quantity')
                };
                this.openReleaseModal(reservationData);
            }
        });

        // Événements des modals
        $('#closeModalBtn, #cancelReleaseBtn, #modalOverlay').on('click', (e) => {
            if (e.target === e.currentTarget) {
                this.closeReleaseModal();
            }
        });

        $('#confirmReleaseBtn').on('click', (e) => {
            const btn = $(e.currentTarget);
            const reservationId = btn.data('reservation-id');
            const projectId = btn.data('project-id');
            const productId = btn.data('product-id');
            this.releaseReservation(reservationId, projectId, productId);
        });

        $('#closeProjectModalBtn, #projectModalOverlay').on('click', (e) => {
            if (e.target === e.currentTarget) {
                this.closeProjectDetailsModal();
            }
        });

        // Fermer les modals avec Escape
        $(document).on('keydown', (e) => {
            if (e.key === 'Escape') {
                if (!$('#releaseModal').hasClass('hidden')) {
                    this.closeReleaseModal();
                }
                if (!$('#projectDetailsModal').hasClass('hidden')) {
                    this.closeProjectDetailsModal();
                }
            }
        });
    }
}

// ==================== INITIALISATION GLOBALE ====================

$(document).ready(function() {
    // Initialiser le gestionnaire de réservations
    window.reservationsManager = new ReservationsManager();
});