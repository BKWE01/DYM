/**
 * GESTION DES DÉTAILS DE PROJET GROUPÉ
 * Fichier JavaScript pour la page de détails des projets groupés
 * 
 * @package DYM_MANUFACTURE
 * @subpackage expressions_besoins/User-Achat/statistics
 * @version 2.1.0
 * @author Équipe DYM
 */

// ========================================
// VARIABLES GLOBALES
// ========================================
let projectDetailsData = {};
let chartsInstances = {};

// ========================================
// CONFIGURATION DATATABLES
// ========================================
const DATATABLES_CONFIG = {
    responsive: true,
    language: {
        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
    },
    pageLength: 25,
    lengthMenu: [
        [10, 25, 50, 100, -1],
        [10, 25, 50, 100, "Tout"]
    ],
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'excel',
            text: '<i class="material-icons">file_download</i> Excel',
            className: 'dt-button'
        },
        {
            extend: 'pdf',
            text: '<i class="material-icons">picture_as_pdf</i> PDF',
            className: 'dt-button',
            orientation: 'landscape'
        },
        {
            extend: 'print',
            text: '<i class="material-icons">print</i> Imprimer',
            className: 'dt-button'
        }
    ],
    order: [[0, 'asc']],
    columnDefs: [
        { responsivePriority: 1, targets: 0 },
        { responsivePriority: 2, targets: 1 },
        { responsivePriority: 3, targets: -1 }
    ]
};

// ========================================
// FONCTIONS UTILITAIRES
// ========================================

/**
 * Formate les montants avec séparateurs de milliers
 * @param {number} amount - Montant à formater
 * @param {boolean} includeCurrency - Inclure FCFA
 * @return {string} Montant formaté
 */
function formatMoney(amount, includeCurrency = true) {
    const formattedValue = new Intl.NumberFormat('fr-FR', {
        style: 'decimal',
        maximumFractionDigits: 0
    }).format(amount);

    return includeCurrency ? `${formattedValue} FCFA` : formattedValue;
}

/**
 * Détruit un graphique existant pour éviter les conflits
 * @param {string} chartId - ID du graphique
 */
function destroyChart(chartId) {
    if (chartsInstances[chartId]) {
        chartsInstances[chartId].destroy();
        delete chartsInstances[chartId];
    }
}

// ========================================
// GESTION DES ONGLETS
// ========================================

/**
 * Affiche l'onglet sélectionné et masque les autres
 * @param {string} tabId - ID de l'onglet à afficher
 */
function showTab(tabId) {
    console.log('Tentative d\'activation de l\'onglet:', tabId);

    // Masquer tous les contenus d'onglets
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.style.display = 'none';
    });

    // Réinitialiser tous les boutons d'onglets
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('border-blue-500', 'text-blue-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });

    // Afficher l'onglet sélectionné
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.style.display = 'block';
        console.log('Onglet affiché:', tabId);
    } else {
        console.error('Onglet non trouvé:', tabId);
    }

    // Activer le bouton correspondant
    const selectedButton = document.getElementById('btn-' + tabId);
    if (selectedButton) {
        selectedButton.classList.remove('border-transparent', 'text-gray-500');
        selectedButton.classList.add('border-blue-500', 'text-blue-600');
        console.log('Bouton activé:', 'btn-' + tabId);
    } else {
        console.error('Bouton non trouvé:', 'btn-' + tabId);
    }

    // Redimensionner les DataTables après changement d'onglet
    setTimeout(() => {
        if ($.fn.DataTable) {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }
    }, 100);
}

/**
 * Initialise la gestion des onglets
 */
function initTabManagement() {
    console.log('Initialisation de la gestion des onglets');

    // Ajouter les gestionnaires d'événements pour les boutons d'onglets
    const tabButtons = document.querySelectorAll('.tab-button');
    console.log('Nombre de boutons d\'onglets trouvés:', tabButtons.length);

    tabButtons.forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault();

            // Déterminer l'ID de l'onglet à partir de l'onclick
            const onclickAttr = this.getAttribute('onclick');
            if (onclickAttr) {
                const match = onclickAttr.match(/showTab\('(.+?)'\)/);
                if (match) {
                    const tabId = match[1];
                    showTab(tabId);
                }
            }
        });
    });

    // Afficher l'onglet par défaut
    setTimeout(() => {
        showTab('tab-materials');
    }, 100);
}

// ========================================
// GESTION DES DATATABLES
// ========================================

/**
 * Détruit un DataTable s'il existe
 * @param {string} tableId - ID du tableau
 */
function destroyDataTable(tableId) {
    if ($.fn.DataTable && $.fn.DataTable.isDataTable(`#${tableId}`)) {
        $(`#${tableId}`).DataTable().destroy();
        console.log('DataTable détruit:', tableId);
    }
}

/**
 * Initialise un DataTable spécifique
 * @param {string} tableId - ID du tableau
 * @param {Object} config - Configuration du DataTable
 */
function initSingleDataTable(tableId, config) {
    const table = document.getElementById(tableId);
    if (!table) {
        console.warn('Tableau non trouvé:', tableId);
        return;
    }

    // Vérifier que le tableau a des lignes de données
    const rows = table.querySelectorAll('tbody tr');
    if (rows.length === 0) {
        console.warn('Aucune ligne dans le tableau:', tableId);
        return;
    }

    // Vérifier s'il y a des données réelles ou juste un message "pas de données"
    const firstRow = rows[0];
    const firstRowCells = firstRow.querySelectorAll('td');

    // Si la première ligne n'a qu'une cellule avec colspan, c'est un message "pas de données"
    if (firstRowCells.length === 1) {
        const firstCell = firstRowCells[0];
        const colspan = firstCell.getAttribute('colspan');

        if (colspan && parseInt(colspan) > 1) {
            console.log(`Tableau ${tableId} vide (message "pas de données" détecté)`);
            return; // Ne pas initialiser DataTable pour un tableau vide
        }
    }

    // Vérifier la cohérence des colonnes pour les vraies données
    const headerCells = table.querySelectorAll('thead th');

    // Trouver une ligne avec le bon nombre de cellules (pas de colspan)
    let validRow = null;
    for (let row of rows) {
        const cells = row.querySelectorAll('td');
        if (cells.length === headerCells.length) {
            validRow = row;
            break;
        }
    }

    if (!validRow) {
        console.warn(`Aucune ligne valide trouvée dans ${tableId} (${headerCells.length} en-têtes attendus)`);
        return;
    }

    try {
        destroyDataTable(tableId);
        $(`#${tableId}`).DataTable(config);
        console.log('DataTable initialisé:', tableId);
    } catch (error) {
        console.error('Erreur lors de l\'initialisation du DataTable:', tableId, error);
    }
}

/**
 * Initialise tous les DataTables
 * @param {string} codeProjet - Code du projet pour les noms de fichiers
 */
function initAllDataTables(codeProjet) {
    console.log('Initialisation des DataTables pour le projet:', codeProjet);

    // Configuration spécifique pour chaque table
    const tableConfigs = {
        'materialsTable': {
            ...DATATABLES_CONFIG,
            order: [[8, 'asc'], [1, 'asc']], // Tri par statut puis désignation
            buttons: DATATABLES_CONFIG.buttons.map(btn => ({
                ...btn,
                title: `Matériaux_${codeProjet}`
            }))
        },
        'movementsTable': {
            ...DATATABLES_CONFIG,
            order: [[7, 'desc']], // Tri par date décroissante
            columnDefs: [
                ...DATATABLES_CONFIG.columnDefs,
                { targets: 7, type: 'date' }
            ],
            buttons: DATATABLES_CONFIG.buttons.map(btn => ({
                ...btn,
                title: `Mouvements_${codeProjet}`
            }))
        },
        'purchasesTable': {
            ...DATATABLES_CONFIG,
            order: [[6, 'desc']], // Tri par date d'achat décroissante
            columnDefs: [
                ...DATATABLES_CONFIG.columnDefs,
                { targets: 6, type: 'date' }
            ],
            buttons: DATATABLES_CONFIG.buttons.map(btn => ({
                ...btn,
                title: `Achats_${codeProjet}`
            }))
        },
        'suppliersTable': {
            ...DATATABLES_CONFIG,
            order: [[4, 'desc']], // Tri par montant total décroissant
            buttons: DATATABLES_CONFIG.buttons.map(btn => ({
                ...btn,
                title: `Fournisseurs_${codeProjet}`
            }))
        }
    };

    // Initialiser seulement les tables qui existent et ont des données
    Object.keys(tableConfigs).forEach(tableId => {
        setTimeout(() => {
            initSingleDataTable(tableId, tableConfigs[tableId]);
        }, 200 * Object.keys(tableConfigs).indexOf(tableId)); // Délai échelonné
    });

    // Redimensionner lors du redimensionnement de la fenêtre
    $(window).off('resize.datatables').on('resize.datatables', () => {
        if ($.fn.DataTable) {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }
    });
}

// ========================================
// GESTION DES GRAPHIQUES
// ========================================

/**
 * Crée un graphique en doughnut/pie
 * @param {string} canvasId - ID du canvas
 * @param {Object} data - Données du graphique
 * @param {string} title - Titre du graphique
 */
function createDoughnutChart(canvasId, data, title = '') {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !data.labels || !data.labels.length) {
        console.warn('Canvas non trouvé ou données manquantes pour:', canvasId);
        return;
    }

    destroyChart(canvasId);

    try {
        chartsInstances[canvasId] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.data,
                    backgroundColor: data.backgroundColor,
                    borderColor: 'white',
                    borderWidth: 2,
                    hoverOffset: 15,
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1500,
                    easing: 'easeOutCubic'
                },
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                family: "'Inter', sans-serif",
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        usePointStyle: true,
                        backgroundColor: 'rgba(17, 24, 39, 0.9)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${formatMoney(value)} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
        console.log('Graphique doughnut créé:', canvasId);
    } catch (error) {
        console.error('Erreur lors de la création du graphique:', canvasId, error);
    }
}

/**
 * Crée un graphique en barres mensuelles
 * @param {string} canvasId - ID du canvas
 * @param {Array} monthlyData - Données mensuelles
 * @param {string} codeProjet - Code du projet
 */
function createMonthlyChart(canvasId, monthlyData, codeProjet) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !monthlyData || !monthlyData.length) {
        console.warn('Canvas non trouvé ou données manquantes pour:', canvasId);
        return;
    }

    destroyChart(canvasId);

    try {
        const canvasContext = ctx.getContext('2d');
        const gradient = canvasContext.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.8)');
        gradient.addColorStop(1, 'rgba(16, 185, 129, 0.2)');

        chartsInstances[canvasId] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthlyData.map(item => item.month_name),
                datasets: [{
                    label: 'Montant total (FCFA)',
                    data: monthlyData.map(item => item.total),
                    backgroundColor: gradient,
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1500,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: `Achats mensuels - Groupe ${codeProjet}`,
                        font: {
                            size: 16,
                            family: "'Inter', sans-serif",
                            weight: 'bold'
                        },
                        color: '#334155',
                        padding: { top: 10, bottom: 20 }
                    },
                    tooltip: {
                        usePointStyle: true,
                        backgroundColor: 'rgba(17, 24, 39, 0.9)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (context) {
                                return `Montant: ${formatMoney(context.raw)}`;
                            },
                            afterLabel: function (context) {
                                const dataIndex = context.dataIndex;
                                return `Commandes: ${monthlyData[dataIndex].count}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return formatMoney(value, false);
                            },
                            font: { family: "'Inter', sans-serif", size: 12 },
                            color: '#64748b'
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.7)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: "'Inter', sans-serif", size: 12 },
                            color: '#64748b'
                        }
                    }
                }
            }
        });
        console.log('Graphique mensuel créé:', canvasId);
    } catch (error) {
        console.error('Erreur lors de la création du graphique mensuel:', canvasId, error);
    }
}

/**
 * Crée un graphique d'évolution des prix
 * @param {string} canvasId - ID du canvas
 * @param {Object} priceData - Données d'évolution des prix
 */
function createPriceEvolutionChart(canvasId, priceData) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !priceData || !priceData.datasets || !priceData.datasets.length) {
        console.warn('Canvas non trouvé ou données manquantes pour:', canvasId);
        return;
    }

    destroyChart(canvasId);

    try {
        const colors = ['#4299E1', '#48BB78', '#ECC94B', '#9F7AEA', '#ED64A6'];

        const datasets = priceData.datasets.map((dataset, index) => ({
            label: dataset.label,
            data: dataset.data,
            borderColor: colors[index % colors.length],
            backgroundColor: 'transparent',
            tension: 0.4,
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: 'white',
            pointBorderColor: colors[index % colors.length],
            pointBorderWidth: 2
        }));

        chartsInstances[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: priceData.labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                animation: {
                    duration: 1500,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { family: "'Inter', sans-serif", size: 12 }
                        }
                    },
                    tooltip: {
                        usePointStyle: true,
                        backgroundColor: 'rgba(17, 24, 39, 0.9)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + formatMoney(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Prix unitaire (FCFA)',
                            font: { family: "'Inter', sans-serif", size: 12 }
                        },
                        ticks: {
                            callback: function (value) {
                                return formatMoney(value, false);
                            },
                            font: { family: "'Inter', sans-serif", size: 12 },
                            color: '#64748b'
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.7)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: "'Inter', sans-serif", size: 12 },
                            color: '#64748b'
                        }
                    }
                }
            }
        });
        console.log('Graphique d\'évolution des prix créé:', canvasId);
    } catch (error) {
        console.error('Erreur lors de la création du graphique d\'évolution:', canvasId, error);
    }
}

/**
 * Crée un graphique radar de performance fournisseurs
 * @param {string} canvasId - ID du canvas
 * @param {Array} supplierData - Données des fournisseurs
 */
function createSupplierPerformanceChart(canvasId, supplierData) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !supplierData || supplierData.length < 2) {
        console.warn('Canvas non trouvé ou données insuffisantes pour:', canvasId);
        return;
    }

    destroyChart(canvasId);

    try {
        const labels = supplierData.map(item => item.nom || 'Non spécifié');
        const deliveryTimes = supplierData.map(item => item.avg_delivery_time);
        const completionRates = supplierData.map(item => item.completion_rate);

        chartsInstances[canvasId] = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Délai de livraison (jours)',
                        data: deliveryTimes,
                        fill: true,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgb(255, 99, 132)',
                        pointBackgroundColor: 'rgb(255, 99, 132)',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Taux de complétion (%)',
                        data: completionRates,
                        fill: true,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgb(54, 162, 235)',
                        pointBackgroundColor: 'rgb(54, 162, 235)',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1500,
                    easing: 'easeOutCubic'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { family: "'Inter', sans-serif", size: 12 }
                        }
                    },
                    tooltip: {
                        usePointStyle: true,
                        backgroundColor: 'rgba(17, 24, 39, 0.9)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    r: {
                        angleLines: { color: 'rgba(0, 0, 0, 0.05)' },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        suggestedMin: 0,
                        ticks: {
                            font: { family: "'Inter', sans-serif", size: 12 },
                            backdropColor: 'transparent',
                            color: '#64748b'
                        },
                        pointLabels: {
                            font: { family: "'Inter', sans-serif", size: 12 },
                            color: '#334155'
                        }
                    }
                }
            }
        });
        console.log('Graphique radar créé:', canvasId);
    } catch (error) {
        console.error('Erreur lors de la création du graphique radar:', canvasId, error);
    }
}

// ========================================
// INITIALISATION DES GRAPHIQUES
// ========================================

/**
 * Initialise tous les graphiques de la page
 * @param {Object} chartData - Données pour tous les graphiques
 * @param {string} codeProjet - Code du projet
 */
function initAllCharts(chartData, codeProjet) {
    console.log('Initialisation des graphiques pour:', codeProjet);

    // Délai pour s'assurer que les canvas sont rendus
    setTimeout(() => {
        // Graphique de statut des achats
        if (chartData.statusChart && chartData.statusChart.labels && chartData.statusChart.labels.length > 0) {
            createDoughnutChart('statusChart', chartData.statusChart, 'Répartition par statut');
        }

        // Graphique des catégories
        if (chartData.categoriesChart && chartData.categoriesChart.labels && chartData.categoriesChart.labels.length > 0) {
            createDoughnutChart('categoriesChart', chartData.categoriesChart, 'Répartition par catégorie');
        }

        // Graphique des achats mensuels
        if (chartData.monthlyData && chartData.monthlyData.length > 0) {
            createMonthlyChart('monthlyPurchasesChart', chartData.monthlyData, codeProjet);
        }

        // Graphique des fournisseurs
        if (chartData.suppliersChart && chartData.suppliersChart.labels && chartData.suppliersChart.labels.length > 0) {
            createDoughnutChart('suppliersChart', chartData.suppliersChart, 'Répartition par fournisseur');
        }

        // Graphique d'évolution des prix
        if (chartData.priceEvolutionChart && chartData.priceEvolutionChart.datasets && chartData.priceEvolutionChart.datasets.length > 0) {
            createPriceEvolutionChart('priceEvolutionChart', chartData.priceEvolutionChart);
        }

        // Graphique de performance fournisseurs
        if (chartData.supplierPerformanceData && chartData.supplierPerformanceData.length >= 2) {
            createSupplierPerformanceChart('supplierPerformanceChart', chartData.supplierPerformanceData);
        }
    }, 300);
}

// ========================================
// GESTION DES ÉVÉNEMENTS
// ========================================

/**
 * Initialise les gestionnaires d'événements
 * @param {string} codeProjet - Code du projet
 */
function initEventHandlers(codeProjet) {
    // Export PDF
    const exportPdfBtn = document.getElementById('export-pdf');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Génération du rapport',
                text: 'Le rapport PDF est en cours de génération...',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            setTimeout(() => {
                window.location.href = `generate_report.php?type=projet_group_details&code_projet=${encodeURIComponent(codeProjet)}`;
            }, 1500);
        });
    }

    // Impression
    const printBtn = document.getElementById('print-details');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }
}

// ========================================
// INITIALISATION PRINCIPALE
// ========================================

/**
 * Initialise toute la page des détails de projet
 * @param {Object} data - Données complètes de la page
 */
function initProjectDetails(data) {
    console.log('Initialisation des détails de projet avec les données:', data);

    // Stocker les données globalement
    projectDetailsData = data;

    // Initialiser les onglets
    initTabManagement();

    // Initialiser les graphiques
    initAllCharts(data.charts, data.codeProjet);

    // Initialiser les DataTables avec un délai
    setTimeout(() => {
        initAllDataTables(data.codeProjet);
    }, 500);

    // Initialiser les gestionnaires d'événements
    initEventHandlers(data.codeProjet);

    console.log('Page des détails de projet initialisée avec succès');
}

// ========================================
// EXPORTS GLOBAUX
// ========================================
window.showTab = showTab;
window.initProjectDetails = initProjectDetails;
window.formatMoney = formatMoney;

// ========================================
// AUTO-INITIALISATION
// ========================================
document.addEventListener('DOMContentLoaded', function () {
    console.log('Script de détails de projet chargé et prêt');
});