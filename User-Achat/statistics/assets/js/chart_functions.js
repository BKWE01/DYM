/**
 * assets/js/chart_functions.js - Fonctions améliorées pour les graphiques du tableau de bord
 * Version: 2.0.0
 * Date: 23/04/2025
 */

// ==============================
// FONCTIONS DE FORMATAGE
// ==============================

/**
 * Formate les montants avec séparateurs de milliers
 * @param {number} amount - Montant à formater
 * @param {boolean} includeCurrency - Inclure le symbole de devise
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
 * Formate les pourcentages
 * @param {number} value - Valeur à formater en pourcentage
 * @param {number} decimals - Nombre de décimales
 * @return {string} Pourcentage formaté
 */
function formatPercent(value, decimals = 1) {
    return `${value.toFixed(decimals)}%`;
}

/**
 * Formate les dates au format français
 * @param {string|Date} date - Date à formater
 * @param {boolean} includeTime - Inclure l'heure
 * @return {string} Date formatée
 */
function formatDate(date, includeTime = false) {
    const options = {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    };

    if (includeTime) {
        options.hour = '2-digit';
        options.minute = '2-digit';
    }

    const dateObj = date instanceof Date ? date : new Date(date);
    return dateObj.toLocaleDateString('fr-FR', options);
}

// ==============================
// CONFIGURATION CHART.JS AVANCÉE
// ==============================

/**
 * Configuration par défaut pour tous les graphiques
 */
function setupAdvancedChartDefaults() {
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#64748b';
    Chart.defaults.elements.line.borderWidth = 3;
    Chart.defaults.elements.point.radius = 5;
    Chart.defaults.elements.point.hoverRadius = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(17, 24, 39, 0.95)';
    Chart.defaults.plugins.tooltip.titleColor = '#ffffff';
    Chart.defaults.plugins.tooltip.bodyColor = '#ffffff';
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.displayColors = true;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.legend.labels.padding = 15;
}

/**
 * Palette de couleurs premium pour les graphiques
 */
const PREMIUM_COLOR_PALETTE = {
    primary: ['#4361ee', '#7209b7', '#f72585', '#4cc9f0', '#10b981'],
    gradients: {
        blue: ['rgba(67, 97, 238, 0.8)', 'rgba(67, 97, 238, 0.2)'],
        purple: ['rgba(114, 9, 183, 0.8)', 'rgba(114, 9, 183, 0.2)'],
        pink: ['rgba(247, 37, 133, 0.8)', 'rgba(247, 37, 133, 0.2)'],
        cyan: ['rgba(76, 201, 240, 0.8)', 'rgba(76, 201, 240, 0.2)'],
        green: ['rgba(16, 185, 129, 0.8)', 'rgba(16, 185, 129, 0.2)']
    },
    success: '#10b981',
    warning: '#f59e0b',
    danger: '#ef4444',
    info: '#3b82f6'
};

/**
 * Crée un gradient pour les graphiques
 * @param {CanvasRenderingContext2D} ctx - Contexte du canvas
 * @param {Array} colors - Tableau de couleurs [couleur1, couleur2]
 * @param {number} height - Hauteur du gradient
 * @return {CanvasGradient} Gradient créé
 */
function createChartGradient(ctx, colors, height = 300) {
    const gradient = ctx.createLinearGradient(0, 0, 0, height);
    gradient.addColorStop(0, colors[0]);
    gradient.addColorStop(0.5, colors[1]);
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0.1)');
    return gradient;
}

// ==============================
// FONCTIONS DE GRAPHIQUES AMÉLIORÉES
// ==============================

/**
 * Génère un graphique en ligne des achats avec animations
 * @param {string} canvasId - ID du canvas
 * @param {object} data - Données du graphique
 */
function renderPurchasesChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');

    // Définir le gradient pour l'arrière-plan
    const gradient = ctx.createLinearGradient(0, 0, 0, 350);
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

    // Créer des datasets animés
    const animatedDatasets = [
        {
            label: 'Montant total (FCFA)',
            data: data.amounts,
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: gradient,
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            yAxisID: 'y',
            pointBackgroundColor: 'white',
            pointBorderColor: 'rgb(59, 130, 246)',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        },
        {
            label: 'Nombre de commandes',
            data: data.counts,
            borderColor: 'rgb(139, 92, 246)',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            borderWidth: 2,
            fill: false,
            tension: 0.4,
            borderDash: [5, 5],
            yAxisID: 'y1',
            pointBackgroundColor: 'white',
            pointBorderColor: 'rgb(139, 92, 246)',
            pointBorderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5
        }
    ];

    // Configuration avancée
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: animatedDatasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1500,
                easing: 'easeOutQuart',
                delay: (context) => context.dataIndex * 100
            },
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                tooltip: {
                    usePointStyle: true,
                    position: 'nearest',
                    backgroundColor: 'rgba(17, 24, 39, 0.9)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 12,
                    cornerRadius: 8,
                    caretSize: 6,
                    callbacks: {
                        label: function (context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.datasetIndex === 0) {
                                label += formatMoney(context.raw);
                            } else {
                                label += context.raw;
                            }
                            return label;
                        }
                    }
                },
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        padding: 15
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        }
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Montant (FCFA)',
                        color: '#64748b',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12,
                            weight: 'normal'
                        }
                    },
                    ticks: {
                        callback: function (value) {
                            return formatMoney(value, false);
                        },
                        color: '#64748b',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        }
                    },
                    grid: {
                        color: 'rgba(226, 232, 240, 0.7)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Nombre de commandes',
                        color: '#64748b',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12,
                            weight: 'normal'
                        }
                    },
                    ticks: {
                        color: '#64748b',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        }
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

/**
 * Génère un graphique en doughnut des catégories avec animations et légende personnalisée
 * @param {string} canvasId - ID du canvas
 * @param {object} data - Données du graphique
 */
function renderCategoriesChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');

    // Configuration améliorée
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: data.backgroundColor,
                borderColor: 'white',
                borderWidth: 2,
                hoverOffset: 15,
                hoverBorderWidth: 0,
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
                    bodyFont: {
                        family: "'Inter', sans-serif"
                    },
                    titleFont: {
                        family: "'Inter', sans-serif",
                        weight: 'bold'
                    },
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
}

/**
 * Génère un graphique en barres des achats mensuels avec animations
 * @param {string} canvasId - ID du canvas
 * @param {object} data - Données du graphique
 * @param {string} year - Année des données
 * @param {string} viewType - Type de vue ('amount' ou 'count')
 */
function renderMonthlyPurchasesChart(canvasId, data, year, viewType = 'amount') {
    const ctx = document.getElementById(canvasId).getContext('2d');

    // Préparer les données
    const labels = data.map(item => item.month_name);
    const counts = data.map(item => item.count);
    const amounts = data.map(item => item.total);

    // Définir le gradient pour l'arrière-plan
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.8)');
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0.2)');

    // Configuration avancée
    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Montant total (FCFA)',
                data: amounts,
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
                easing: 'easeOutQuart',
                delay: (context) => context.dataIndex * 50
            },
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: `Achats mensuels - ${year}`,
                    font: {
                        size: 16,
                        family: "'Inter', sans-serif",
                        weight: 'bold'
                    },
                    color: '#334155',
                    padding: {
                        top: 10,
                        bottom: 20
                    }
                },
                tooltip: {
                    usePointStyle: true,
                    backgroundColor: 'rgba(17, 24, 39, 0.9)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 12,
                    cornerRadius: 8,
                    bodyFont: {
                        family: "'Inter', sans-serif"
                    },
                    titleFont: {
                        family: "'Inter', sans-serif",
                        weight: 'bold'
                    },
                    callbacks: {
                        label: function (context) {
                            return `Montant: ${formatMoney(context.raw)} FCFA`;
                        },
                        afterLabel: function (context) {
                            const dataIndex = context.dataIndex;
                            return `Commandes: ${counts[dataIndex]}`;
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
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#64748b'
                    },
                    grid: {
                        color: 'rgba(226, 232, 240, 0.7)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#64748b'
                    }
                }
            }
        }
    });

    // Important : retourner l'instance du graphique
    return chart;
}

/**
 * Génère un graphique radar de performance fournisseur avec animations
 * @param {string} canvasId - ID du canvas
 * @param {object} data - Données du graphique
 */
function renderSupplierPerformanceChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');

    // Préparer les données
    const labels = data.map(item => item.nom || 'Non spécifié');
    const deliveryTimes = data.map(item => item.avg_delivery_time);
    const completionRates = data.map(item => item.completion_rate);

    // Configuration améliorée
    new Chart(ctx, {
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
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgb(255, 99, 132)',
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
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgb(54, 162, 235)',
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
            elements: {
                line: {
                    borderWidth: 2
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
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
                    bodyFont: {
                        family: "'Inter', sans-serif"
                    },
                    titleFont: {
                        family: "'Inter', sans-serif",
                        weight: 'bold'
                    },
                    callbacks: {
                        label: function (context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.raw.toFixed(1);
                            return label;
                        }
                    }
                }
            },
            scales: {
                r: {
                    angleLines: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    suggestedMin: 0,
                    ticks: {
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        backdropColor: 'transparent',
                        color: '#64748b'
                    },
                    pointLabels: {
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#334155'
                    }
                }
            }
        }
    });
}

/**
 * Génère un graphique horizontal des catégories de stock avec animations
 * @param {string} canvasId - ID du canvas
 * @param {object} data - Données du graphique
 */
function renderCategoriesStockChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');

    // Préparer les données
    const labels = data.map(item => item.category || 'Non catégorisé');
    const values = data.map(item => item.total_value);
    const quantities = data.map(item => item.total_quantity);

    // Générer des couleurs harmonieuses
    const backgroundColors = [];
    for (let i = 0; i < labels.length; i++) {
        const hue = (i * 137) % 360; // Répartir les couleurs de manière équilibrée
        backgroundColors.push(`hsla(${hue}, 70%, 60%, 0.7)`);
    }

    // Configuration améliorée
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                axis: 'y',
                label: 'Valeur du stock (FCFA)',
                data: values,
                backgroundColor: backgroundColors,
                borderColor: 'rgba(255, 255, 255, 0.7)',
                borderWidth: 1,
                borderRadius: 4,
                barPercentage: 0.8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            animation: {
                duration: 1500,
                easing: 'easeOutQuart',
                delay: (context) => context.dataIndex * 100
            },
            plugins: {
                legend: {
                    display: false
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
                            return `Valeur: ${formatMoney(context.raw)} FCFA`;
                        },
                        afterLabel: function (context) {
                            const dataIndex = context.dataIndex;
                            return `Quantité: ${quantities[dataIndex]}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return formatMoney(value, false);
                        },
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#64748b'
                    },
                    grid: {
                        color: 'rgba(226, 232, 240, 0.7)'
                    }
                },
                y: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#64748b'
                    }
                }
            }
        }
    });
}

/**
 * Génère un graphique d'évolution des prix moyens
 * @param {string} canvasId - ID du canvas
 * @param {object} data - Données du graphique
 */
function renderPriceEvolutionChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');

    // Définir le gradient pour l'arrière-plan
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(220, 38, 38, 0.2)');  // rouge pour les prix
    gradient.addColorStop(1, 'rgba(220, 38, 38, 0.0)');

    // Configuration améliorée
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Prix moyen (FCFA)',
                    data: data.prices,
                    borderColor: 'rgb(220, 38, 38)',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y',
                    pointBackgroundColor: 'white',
                    pointBorderColor: 'rgb(220, 38, 38)',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Nombre de produits',
                    data: data.counts,
                    borderColor: 'rgb(37, 99, 235)',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    borderDash: [5, 5],
                    yAxisID: 'y1',
                    pointBackgroundColor: 'white',
                    pointBorderColor: 'rgb(37, 99, 235)',
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1500,
                easing: 'easeOutQuart'
            },
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                tooltip: {
                    usePointStyle: true,
                    backgroundColor: 'rgba(17, 24, 39, 0.9)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function (context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.datasetIndex === 0) {
                                label += formatMoney(context.raw);
                            } else {
                                label += context.raw;
                            }
                            return label;
                        }
                    }
                },
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#64748b'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Prix moyen (FCFA)',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12,
                            weight: 'normal'
                        },
                        color: '#64748b'
                    },
                    ticks: {
                        callback: function (value) {
                            return formatMoney(value, false);
                        },
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#64748b'
                    },
                    grid: {
                        color: 'rgba(226, 232, 240, 0.7)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Nombre de produits',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12,
                            weight: 'normal'
                        },
                        color: '#64748b'
                    },
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#64748b'
                    }
                }
            }
        }
    });
}

/**
 * Génère un graphique des retours fournisseurs avec animations
 * @param {string} canvasId - ID du canvas
 * @param {object} data - Données du graphique
 */
function renderSupplierReturnsChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');

    // Configuration améliorée avec thème et animations
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.reasons.map(item => item.reason),
            datasets: [{
                data: data.reasons.map(item => item.count),
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)'
                ],
                borderColor: 'white',
                borderWidth: 2,
                hoverOffset: 15,
                borderRadius: 5
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
                        },
                        color: '#64748b'
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
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
}

/**
 * Génère un graphique des commandes annulées par mois avec animations
 * @param {string} canvasId - ID du canvas
 * @param {object} data - Données du graphique
 */
function renderCanceledOrdersChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');

    // Obtenir les 6 derniers mois et préparer les données
    const months = [];
    const counts = [];

    // Préparer les 6 derniers mois (même sans données)
    for (let i = 5; i >= 0; i--) {
        const date = new Date();
        date.setMonth(date.getMonth() - i);
        const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        const monthName = date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });

        months.push(monthName);

        // Trouver la valeur correspondante dans les données
        const monthData = data.find(item => item.month === monthKey);
        counts.push(monthData ? parseInt(monthData.count) : 0);
    }

    // Définir le gradient pour l'arrière-plan
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(239, 68, 68, 0.3)');  // rouge pour les annulations
    gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)');

    // Configuration améliorée
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Commandes annulées',
                data: counts,
                borderColor: 'rgb(239, 68, 68)',
                backgroundColor: gradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'white',
                pointBorderColor: 'rgb(239, 68, 68)',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
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
                legend: {
                    display: false
                },
                tooltip: {
                    usePointStyle: true,
                    backgroundColor: 'rgba(17, 24, 39, 0.9)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 12,
                    cornerRadius: 8,
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function (context) {
                            return `Commandes annulées: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#64748b'
                    },
                    grid: {
                        color: 'rgba(226, 232, 240, 0.7)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#64748b'
                    }
                }
            }
        }
    });
}

/**
 * Génère un graphique des produits les plus annulés avec animations
 * @param {string} canvasId - ID du canvas
 * @param {object} data - Données du graphique
 */
function renderCanceledProductsChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');

    // Préparer les données
    const labels = data.map(item => item.designation);
    const values = data.map(item => parseInt(item.count));

    // Générer des couleurs harmonieuses
    const backgroundColors = [];
    for (let i = 0; i < labels.length; i++) {
        const hue = (i * 137) % 360; // Répartir les couleurs de manière équilibrée
        backgroundColors.push(`hsla(${hue}, 70%, 60%, 0.7)`);
    }

    // Configuration améliorée
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Nombre d\'annulations',
                data: values,
                backgroundColor: backgroundColors,
                borderColor: 'white',
                borderWidth: 1,
                borderRadius: 6,
                barPercentage: 0.7,
                categoryPercentage: 0.8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            animation: {
                duration: 1500,
                easing: 'easeOutQuart',
                delay: (context) => context.dataIndex * 100
            },
            plugins: {
                legend: {
                    display: false
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
                            return `Annulations: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#64748b'
                    },
                    grid: {
                        color: 'rgba(226, 232, 240, 0.7)'
                    }
                },
                y: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12
                        },
                        color: '#64748b'
                    }
                }
            }
        }
    });
}

/**
 * Génère un graphique en doughnut des raisons d'annulation avec animations
 * @param {string} canvasId - ID du canvas
 * @param {object} data - Données du graphique
 */
function renderCancellationReasonsChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');

    // Préparer les données
    const labels = data.map(item => item.cancel_reason);
    const values = data.map(item => parseInt(item.count));

    // Définir les couleurs
    const colors = [
        'rgba(239, 68, 68, 0.8)',  // Rouge
        'rgba(245, 158, 11, 0.8)', // Orange
        'rgba(99, 102, 241, 0.8)', // Indigo
        'rgba(16, 185, 129, 0.8)', // Vert
        'rgba(107, 114, 128, 0.8)' // Gris
    ];

    // Configuration améliorée
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors.slice(0, values.length),
                borderColor: 'white',
                borderWidth: 2,
                hoverOffset: 15,
                borderRadius: 5
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
                        },
                        color: '#64748b'
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
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
}

/**
 * Crée un mini graphique d'évolution pour les KPI cards
 * @param {string} canvasId - ID du canvas
 * @param {Array} data - Données du graphique
 * @param {string} type - Type de graphique ('up', 'down', 'neutral')
 */
function createMiniChart(canvasId, data, type = 'neutral') {
    const ctx = document.getElementById(canvasId).getContext('2d');

    // Définir la couleur selon le type
    let color;
    switch (type) {
        case 'up':
            color = '#10b981'; // vert
            break;
        case 'down':
            color = '#ef4444'; // rouge
            break;
        default:
            color = '#3b82f6'; // bleu
    }

    // Configuration minimaliste
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: Array(data.length).fill(''),
            datasets: [{
                data: data,
                borderColor: color,
                backgroundColor: 'transparent',
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            },
            scales: {
                x: {
                    display: false
                },
                y: {
                    display: false
                }
            },
            elements: {
                line: {
                    tension: 0.4
                }
            }
        }
    });
}

// ============= Fonction pour détail produit ================
// ==============================
// FONCTIONS GRAPHIQUES DÉTAILS PRODUIT
// ==============================

/**
 * Graphique d'évolution des prix du produit avec design premium et courbes ultra-lisses
 * @param {string} canvasId - ID du canvas
 * @param {Array} evolutionData - Données d'évolution des prix
 * @param {string} productName - Nom du produit
 */
function renderProductPriceEvolutionChart(canvasId, evolutionData, productName = '') {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !evolutionData.length) return;

    const canvasContext = ctx.getContext('2d');

    // Création des gradients premium avec transparence douce
    const gradientPrixMoyen = canvasContext.createLinearGradient(0, 0, 0, 400);
    gradientPrixMoyen.addColorStop(0, 'rgba(67, 97, 238, 0.4)');
    gradientPrixMoyen.addColorStop(0.5, 'rgba(67, 97, 238, 0.2)');
    gradientPrixMoyen.addColorStop(1, 'rgba(67, 97, 238, 0.05)');

    // Utilisation directe des labels français depuis PHP
    const frenchLabels = evolutionData.map(item => item.mois_format || item.mois || '');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: frenchLabels,
            datasets: [
                {
                    label: 'Prix Moyen',
                    data: evolutionData.map(item => item.prix_moyen_mois || 0),
                    borderColor: '#4361ee',
                    backgroundColor: gradientPrixMoyen,
                    fill: true,
                    tension: 0.9, // COURBES ULTRA-LISSES (maximum)
                    pointRadius: 6,
                    pointHoverRadius: 10,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#4361ee',
                    pointBorderWidth: 3,
                    pointHoverBackgroundColor: '#4361ee',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 4,
                    borderWidth: 4,
                    cubicInterpolationMode: 'monotone' // Interpolation cubique parfaite
                },
                {
                    label: 'Prix Minimum',
                    data: evolutionData.map(item => item.prix_min_mois || 0),
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.9, // COURBES ULTRA-LISSES
                    borderDash: [15, 8],
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#10b981',
                    pointBorderWidth: 2,
                    borderWidth: 3,
                    cubicInterpolationMode: 'monotone'
                },
                {
                    label: 'Prix Maximum',
                    data: evolutionData.map(item => item.prix_max_mois || 0),
                    borderColor: '#ef4444',
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.9, // COURBES ULTRA-LISSES
                    borderDash: [15, 8],
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#ef4444',
                    pointBorderWidth: 2,
                    borderWidth: 3,
                    cubicInterpolationMode: 'monotone'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            animation: {
                duration: 2500,
                easing: 'easeInOutQuart',
                delay: (context) => context.dataIndex * 100
            },
            elements: {
                line: {
                    tension: 0.9, // Configuration globale pour des courbes parfaites
                    capStyle: 'round',
                    joinStyle: 'round'
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: {
                            size: 13,
                            weight: '600',
                            family: "'Inter', sans-serif"
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.95)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#4361ee',
                    borderWidth: 2,
                    cornerRadius: 12,
                    displayColors: true,
                    padding: 16,
                    titleFont: {
                        size: 15,
                        weight: 'bold',
                        family: "'Inter', sans-serif"
                    },
                    bodyFont: {
                        size: 14,
                        family: "'Inter', sans-serif"
                    },
                    callbacks: {
                        title: function (context) {
                            return `${productName} - ${context[0].label}`;
                        },
                        label: function (context) {
                            return `${context.dataset.label}: ${formatMoney(context.raw)}`;
                        },
                        afterBody: function (tooltipItems) {
                            const monthData = evolutionData[tooltipItems[0].dataIndex];
                            return [
                                '',
                                `Achats du mois: ${monthData.nb_achats_mois || 0}`,
                                `Écart min-max: ${formatMoney((monthData.prix_max_mois || 0) - (monthData.prix_min_mois || 0))}`
                            ];
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 12,
                            weight: '500',
                            family: "'Inter', sans-serif"
                        },
                        color: '#64748b',
                        maxRotation: 45
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    title: {
                        display: true,
                        text: 'Prix (FCFA)',
                        font: {
                            size: 14,
                            weight: '600',
                            family: "'Inter', sans-serif"
                        },
                        color: '#374151'
                    },
                    ticks: {
                        font: {
                            size: 12,
                            family: "'Inter', sans-serif"
                        },
                        color: '#64748b',
                        callback: function (value) {
                            return formatMoney(value, false);
                        }
                    }
                }
            }
        }
    });
}


/**
 * Graphique comparatif des prix par fournisseur avec design premium
 * @param {string} canvasId - ID du canvas
 * @param {Array} fournisseursData - Données des fournisseurs
 * @param {string} productName - Nom du produit
 */
function renderProductSupplierPricesChart(canvasId, fournisseursData, productName = '') {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !fournisseursData.length) return;

    const canvasContext = ctx.getContext('2d');

    // Couleurs dynamiques pour chaque fournisseur
    const backgroundColors = fournisseursData.map((_, index) => {
        const hue = (index * 137) % 360;
        return `hsla(${hue}, 70%, 60%, 0.8)`;
    });

    const borderColors = fournisseursData.map((_, index) => {
        const hue = (index * 137) % 360;
        return `hsla(${hue}, 70%, 45%, 1)`;
    });

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: fournisseursData.map(item => item.fournisseur),
            datasets: [
                {
                    label: 'Prix Moyen',
                    data: fournisseursData.map(item => item.prix_moyen || 0),
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 3,
                    borderRadius: 12,
                    borderSkipped: false,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8,
                    hoverOffset: 8
                },
                {
                    label: 'Dernier Prix',
                    data: fournisseursData.map(item => item.dernier_prix || 0),
                    backgroundColor: 'rgba(59, 130, 246, 0.3)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.5,
                    categoryPercentage: 0.8,
                    type: 'line',
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 3,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            animation: {
                duration: 2000,
                easing: 'easeInOutBounce',
                delay: (context) => context.dataIndex * 150
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 13,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.95)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#4361ee',
                    borderWidth: 2,
                    cornerRadius: 12,
                    displayColors: true,
                    padding: 16,
                    titleFont: {
                        size: 15,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 14
                    },
                    callbacks: {
                        title: function (context) {
                            return `${context[0].label} - ${productName}`;
                        },
                        label: function (context) {
                            const fournisseur = fournisseursData[context.dataIndex];
                            if (context.datasetIndex === 0) {
                                return [
                                    `Prix moyen: ${formatMoney(context.raw)}`,
                                    `Commandes: ${fournisseur.nb_commandes}`,
                                    `Quantité totale: ${new Intl.NumberFormat('fr-FR').format(fournisseur.quantite_totale)}`
                                ];
                            } else {
                                return `Dernier prix: ${formatMoney(context.raw)}`;
                            }
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 12,
                            weight: '500'
                        },
                        color: '#64748b',
                        maxRotation: 45
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    title: {
                        display: true,
                        text: 'Prix (FCFA)',
                        font: {
                            size: 14,
                            weight: '600'
                        },
                        color: '#374151'
                    },
                    ticks: {
                        font: {
                            size: 12
                        },
                        color: '#64748b',
                        callback: function (value) {
                            return formatMoney(value, false);
                        }
                    }
                }
            }
        }
    });
}

/**
 * Graphique de répartition des achats par fournisseur avec design premium
 * @param {string} canvasId - ID du canvas
 * @param {Array} fournisseursData - Données des fournisseurs
 * @param {string} productName - Nom du produit
 */
function renderProductSupplierDistributionChart(canvasId, fournisseursData, productName = '') {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !fournisseursData.length) return;

    // Couleurs sophistiquées inspirées du design moderne
    const colors = [
        '#4361ee', '#7209b7', '#f72585', '#4cc9f0', '#10b981',
        '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#22c55e'
    ];

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: fournisseursData.map(item => item.fournisseur),
            datasets: [{
                data: fournisseursData.map(item => item.quantite_totale || 0),
                backgroundColor: colors.slice(0, fournisseursData.length),
                borderColor: 'white',
                borderWidth: 4,
                hoverOffset: 20,
                hoverBorderWidth: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 2500,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 25,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 12,
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.95)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#4361ee',
                    borderWidth: 2,
                    cornerRadius: 12,
                    displayColors: true,
                    padding: 16,
                    titleFont: {
                        size: 15,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 14
                    },
                    callbacks: {
                        title: function (context) {
                            return `${context[0].label} - ${productName}`;
                        },
                        label: function (context) {
                            const value = context.raw;
                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                            const percentage = Math.round((value / total) * 100);
                            const fournisseur = fournisseursData[context.dataIndex];

                            return [
                                `Quantité: ${new Intl.NumberFormat('fr-FR').format(value)} unités`,
                                `Part: ${percentage}%`,
                                `Commandes: ${fournisseur.nb_commandes}`,
                                `Prix moyen: ${formatMoney(fournisseur.prix_moyen || 0)}`
                            ];
                        }
                    }
                }
            }
        }
    });
}

/**
 * Graphique d'évolution du stock avec design premium et courbes ultra-lisses
 * @param {string} canvasId - ID du canvas
 * @param {Array} evolutionData - Données d'évolution du stock
 * @param {string} productName - Nom du produit
 * @param {string} unit - Unité du produit
 */
function renderProductStockEvolutionChart(canvasId, evolutionData, productName = '', unit = 'unités') {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !evolutionData.length) return;

    // DÉTRUIRE LE GRAPHIQUE EXISTANT S'IL EXISTE
    destroyChartIfExists(canvasId);

    const canvasContext = ctx.getContext('2d');

    // Gradients premium ultra-smooth avec plus de nuances
    const gradientEntrees = canvasContext.createLinearGradient(0, 0, 0, 400);
    gradientEntrees.addColorStop(0, 'rgba(16, 185, 129, 0.5)');
    gradientEntrees.addColorStop(0.3, 'rgba(16, 185, 129, 0.3)');
    gradientEntrees.addColorStop(0.7, 'rgba(16, 185, 129, 0.15)');
    gradientEntrees.addColorStop(1, 'rgba(16, 185, 129, 0.02)');

    const gradientSorties = canvasContext.createLinearGradient(0, 0, 0, 400);
    gradientSorties.addColorStop(0, 'rgba(239, 68, 68, 0.5)');
    gradientSorties.addColorStop(0.3, 'rgba(239, 68, 68, 0.3)');
    gradientSorties.addColorStop(0.7, 'rgba(239, 68, 68, 0.15)');
    gradientSorties.addColorStop(1, 'rgba(239, 68, 68, 0.02)');

    const gradientVariation = canvasContext.createLinearGradient(0, 0, 0, 400);
    gradientVariation.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
    gradientVariation.addColorStop(0.5, 'rgba(59, 130, 246, 0.15)');
    gradientVariation.addColorStop(1, 'rgba(59, 130, 246, 0.02)');

    // Utilisation directe des labels français depuis PHP
    const frenchLabels = evolutionData.map(item => item.mois_format || item.mois || '');

    // CRÉER ET STOCKER LA NOUVELLE INSTANCE
    window.chartInstances[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: frenchLabels,
            datasets: [
                {
                    label: 'Entrées',
                    data: evolutionData.map(item => item.entrees || 0),
                    borderColor: '#10b981',
                    backgroundColor: gradientEntrees,
                    fill: true,
                    tension: 0.95, // TENSION MAXIMALE pour des courbes ultra-lisses
                    pointRadius: 8,
                    pointHoverRadius: 12,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#10b981',
                    pointBorderWidth: 4,
                    pointHoverBackgroundColor: '#10b981',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 5,
                    borderWidth: 5,
                    yAxisID: 'y',
                    cubicInterpolationMode: 'monotone', // Interpolation parfaite
                    lineTension: 0.95 // Double tension pour plus de fluidité
                },
                {
                    label: 'Sorties',
                    data: evolutionData.map(item => item.sorties || 0),
                    borderColor: '#ef4444',
                    backgroundColor: gradientSorties,
                    fill: true,
                    tension: 0.95, // TENSION MAXIMALE
                    pointRadius: 8,
                    pointHoverRadius: 12,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#ef4444',
                    pointBorderWidth: 4,
                    pointHoverBackgroundColor: '#ef4444',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 5,
                    borderWidth: 5,
                    yAxisID: 'y',
                    cubicInterpolationMode: 'monotone',
                    lineTension: 0.95
                },
                {
                    label: 'Variation Nette',
                    data: evolutionData.map(item => item.variation_nette || 0),
                    borderColor: '#3b82f6',
                    backgroundColor: gradientVariation,
                    fill: '+1', // Remplir jusqu'à la ligne précédente
                    tension: 0.95, // TENSION MAXIMALE
                    borderDash: [0], // Enlever les pointillés pour une ligne continue
                    pointRadius: 6,
                    pointHoverRadius: 10,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 3,
                    pointHoverBackgroundColor: '#3b82f6',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 4,
                    borderWidth: 4,
                    yAxisID: 'y1',
                    cubicInterpolationMode: 'monotone',
                    lineTension: 0.95
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            animation: {
                duration: 3000, // Animation plus longue pour un effet fluide
                easing: 'easeInOutCubic', // Easing plus fluide
                delay: (context) => context.dataIndex * 50
            },
            elements: {
                line: {
                    tension: 0.95, // Configuration globale maximale
                    capStyle: 'round',
                    joinStyle: 'round',
                    borderCapStyle: 'round',
                    borderJoinStyle: 'round'
                },
                point: {
                    hoverRadius: 12,
                    radius: 8,
                    borderWidth: 4,
                    hoverBorderWidth: 5
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 25,
                        boxWidth: 15,
                        boxHeight: 15,
                        font: {
                            size: 14,
                            weight: '600',
                            family: "'Inter', sans-serif"
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.96)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#10b981',
                    borderWidth: 3,
                    cornerRadius: 15,
                    displayColors: true,
                    padding: 20,
                    titleFont: {
                        size: 16,
                        weight: 'bold',
                        family: "'Inter', sans-serif"
                    },
                    bodyFont: {
                        size: 15,
                        family: "'Inter', sans-serif"
                    },
                    caretSize: 8,
                    callbacks: {
                        title: function (context) {
                            return `${productName} - ${context[0].label}`;
                        },
                        label: function (context) {
                            let label = context.dataset.label + ': ';
                            label += new Intl.NumberFormat('fr-FR').format(context.raw) + ` ${unit}`;
                            return label;
                        },
                        afterBody: function (tooltipItems) {
                            const monthData = evolutionData[tooltipItems[0].dataIndex];
                            const variation = monthData.variation_nette || 0;
                            const variationText = variation >= 0 ? `+${new Intl.NumberFormat('fr-FR').format(variation)}` : new Intl.NumberFormat('fr-FR').format(variation);

                            return [
                                '',
                                `📊 Mouvements total: ${monthData.nb_mouvements || 0}`,
                                `📈 Solde net: ${variationText} ${unit}`,
                                variation > 0 ? '🟢 Augmentation du stock' : variation < 0 ? '🔴 Diminution du stock' : '⚪ Stock stable'
                            ];
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 13,
                            weight: '500',
                            family: "'Inter', sans-serif"
                        },
                        color: '#64748b',
                        padding: 10
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: {
                        color: 'rgba(0, 0, 0, 0.03)',
                        drawBorder: false,
                        lineWidth: 1
                    },
                    title: {
                        display: true,
                        text: `📦 Quantité (${unit})`,
                        font: {
                            size: 15,
                            weight: '700',
                            family: "'Inter', sans-serif"
                        },
                        color: '#374151',
                        padding: { bottom: 10 }
                    },
                    ticks: {
                        font: {
                            size: 13,
                            family: "'Inter', sans-serif"
                        },
                        color: '#64748b',
                        padding: 10,
                        callback: function (value) {
                            return new Intl.NumberFormat('fr-FR').format(value);
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    },
                    title: {
                        display: true,
                        text: '📊 Variation Nette',
                        font: {
                            size: 15,
                            weight: '700',
                            family: "'Inter', sans-serif"
                        },
                        color: '#374151',
                        padding: { bottom: 10 }
                    },
                    ticks: {
                        font: {
                            size: 13,
                            family: "'Inter', sans-serif"
                        },
                        color: '#64748b',
                        padding: 10,
                        callback: function (value) {
                            return (value >= 0 ? '+' : '') + new Intl.NumberFormat('fr-FR').format(value);
                        }
                    }
                }
            }
        }
    });
}


/**
 * Mini graphique de tendance pour les cartes métriques
 * @param {string} canvasId - ID du canvas
 * @param {Array} data - Données pour le graphique
 * @param {string} type - Type de tendance ('up', 'down', 'neutral')
 * @param {string} color - Couleur personnalisée (optionnel)
 */
function createProductMiniTrendChart(canvasId, data, type = 'neutral', color = null) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !data.length) return;

    // Définir la couleur selon le type
    let chartColor = color;
    if (!chartColor) {
        switch (type) {
            case 'up':
                chartColor = PREMIUM_COLOR_PALETTE.success;
                break;
            case 'down':
                chartColor = PREMIUM_COLOR_PALETTE.danger;
                break;
            default:
                chartColor = PREMIUM_COLOR_PALETTE.info;
        }
    }

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: Array(data.length).fill(''),
            datasets: [{
                data: data,
                borderColor: chartColor,
                backgroundColor: `${chartColor}20`,
                borderWidth: 3,
                pointRadius: 0,
                pointHoverRadius: 0,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1500,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            },
            scales: {
                x: {
                    display: false
                },
                y: {
                    display: false
                }
            },
            elements: {
                line: {
                    tension: 0.4
                },
                point: {
                    radius: 0
                }
            }
        }
    });
}

// ==============================
// FONCTIONS D'INITIALISATION PRODUIT
// ==============================

/**
 * Configuration DataTables en français optimisée
 */
const PRODUCT_DATATABLES_CONFIG = {
    language: {
        processing: "Traitement en cours...",
        search: "Rechercher&nbsp;:",
        lengthMenu: "Afficher _MENU_ éléments",
        info: "Affichage de l'élément _START_ à _END_ sur _TOTAL_ éléments",
        infoEmpty: "Affichage de l'élément 0 à 0 sur 0 élément",
        infoFiltered: "(filtré de _MAX_ éléments au total)",
        infoPostfix: "",
        loadingRecords: "Chargement en cours...",
        zeroRecords: "Aucun élément à afficher",
        emptyTable: "Aucune donnée disponible dans le tableau",
        paginate: {
            first: "Premier",
            previous: "Précédent",
            next: "Suivant",
            last: "Dernier"
        },
        aria: {
            sortAscending: ": activer pour trier la colonne par ordre croissant",
            sortDescending: ": activer pour trier la colonne par ordre décroissant"
        }
    },
    responsive: true,
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tout"]],
    dom: 'Bfrtip',
    buttons: [
        { extend: 'copy', text: 'Copier', className: 'dt-button' },
        { extend: 'csv', text: 'CSV', className: 'dt-button' },
        { extend: 'excel', text: 'Excel', className: 'dt-button' },
        { extend: 'pdf', text: 'PDF', className: 'dt-button' },
        { extend: 'print', text: 'Imprimer', className: 'dt-button' }
    ],
    order: [[0, 'desc']],
    columnDefs: [{ targets: [0], type: 'date' }]
};

/**
 * Initialise un DataTable avec la configuration optimisée
 * @param {string} tableId - ID du tableau
 * @param {string} filename - Nom de base pour les exports
 * @param {Array} columnDefs - Définitions de colonnes spécifiques
 */
function initProductDataTable(tableId, filename, columnDefs = []) {
    if ($.fn.DataTable.isDataTable(`#${tableId}`)) {
        $(`#${tableId}`).DataTable().destroy();
    }

    if ($(`#${tableId}`).length) {
        const config = {
            ...PRODUCT_DATATABLES_CONFIG,
            buttons: PRODUCT_DATATABLES_CONFIG.buttons.map(btn => ({
                ...btn,
                filename: `${filename}-${new Date().toISOString().split('T')[0]}`
            })),
            columnDefs: [...PRODUCT_DATATABLES_CONFIG.columnDefs, ...columnDefs]
        };

        $(`#${tableId}`).DataTable(config);
    }
}

/**
 * Initialise la gestion des onglets pour la page de détails produit
 * @param {Object} chartData - Données pour les graphiques
 * @param {string} productName - Nom du produit
 * @param {string} unit - Unité du produit
 */
function initProductDetailsPage(chartData, productName = '', unit = 'unités') {
    // Configuration Chart.js
    setupAdvancedChartDefaults();

    // Gestion des onglets
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    // Initialiser le premier DataTable
    setTimeout(() => {
        initProductDataTable('movementsTable', `mouvements-${productName.replace(/[^a-zA-Z0-9]/g, '-')}`);
    }, 100);

    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            // Désactiver tous les boutons
            tabButtons.forEach(btn => {
                btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            // Activer le bouton cliqué
            this.classList.add('active', 'border-blue-500', 'text-blue-600');
            this.classList.remove('border-transparent', 'text-gray-500');

            // Masquer tous les contenus
            tabContents.forEach(content => content.classList.remove('active'));

            // Afficher le contenu correspondant
            const tabId = this.getAttribute('data-tab');
            document.getElementById(`tab-${tabId}`).classList.add('active');

            // Initialiser le DataTable et graphiques correspondants
            setTimeout(() => {
                const productSlug = productName.replace(/[^a-zA-Z0-9]/g, '-');

                switch (tabId) {
                    case 'movements':
                        initProductDataTable('movementsTable', `mouvements-${productSlug}`);
                        break;
                    case 'achats':
                        initProductDataTable('achatsTable', `achats-${productSlug}`);
                        break;
                    case 'projets':
                        initProductDataTable('projetsTable', `projets-${productSlug}`);
                        break;
                    case 'fournisseurs':
                        initProductDataTable('fournisseursTable', `fournisseurs-${productSlug}`);
                        // Initialiser les graphiques spécifiques aux fournisseurs
                        setTimeout(() => {
                            if (chartData.fournisseurs && chartData.fournisseurs.length > 0) {
                                renderProductSupplierPricesChart('fournisseursPrixChart', chartData.fournisseurs, productName);
                                renderProductSupplierDistributionChart('fournisseursRepartitionChart', chartData.fournisseurs, productName);
                            }
                        }, 200);
                        break;
                    case 'evolution':
                        initProductDataTable('evolutionTable', `evolution-${productSlug}`);
                        break;
                }
            }, 150);
        });
    });

    // Initialiser les graphiques principaux avec un délai
    setTimeout(() => {
        if (chartData.evolutionPrix && chartData.evolutionPrix.length > 0) {
            renderProductPriceEvolutionChart('prixEvolutionChart', chartData.evolutionPrix, productName);
        }

        if (chartData.evolutionStock && chartData.evolutionStock.length > 0) {
            renderProductStockEvolutionChart('evolutionChart', chartData.evolutionStock, productName, unit);
        }

        if (chartData.fournisseurs && chartData.fournisseurs.length > 0) {
            renderProductSupplierPricesChart('fournisseursPrixChart', chartData.fournisseurs, productName);
            renderProductSupplierDistributionChart('fournisseursRepartitionChart', chartData.fournisseurs, productName);
        }

        // Mini graphique de tendance des prix si disponible
        if (chartData.evolutionPrix && chartData.evolutionPrix.length > 0) {
            const prixData = chartData.evolutionPrix.map(item => item.prix_moyen_mois || 0);
            createProductMiniTrendChart('prixMoyenTrendChart', prixData, 'neutral');
        }
    }, 500);
}

// ==============================
// FONCTIONS UTILITAIRES AMÉLIORÉES
// ==============================

/**
 * Convertit les noms de mois anglais en français
 * @param {string} englishMonth - Nom du mois en anglais
 * @return {string} Nom du mois en français
 */
function convertMonthToFrench(englishMonth) {
    const monthMap = {
        'January': 'Janvier',
        'February': 'Février',
        'March': 'Mars',
        'April': 'Avril',
        'May': 'Mai',
        'June': 'Juin',
        'July': 'Juillet',
        'August': 'Août',
        'September': 'Septembre',
        'October': 'Octobre',
        'November': 'Novembre',
        'December': 'Décembre'
    };

    return monthMap[englishMonth] || englishMonth;
}

/**
 * Formate une date en français (mois année)
 * @param {string} dateString - Date au format YYYY-MM ou date string
 * @return {string} Date formatée en français
 */
function formatDateToFrench(dateString) {
    try {
        // Si c'est au format YYYY-MM
        if (dateString.match(/^\d{4}-\d{2}$/)) {
            const [year, month] = dateString.split('-');
            const monthNames = [
                'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
            ];
            return `${monthNames[parseInt(month) - 1]} ${year}`;
        }

        // Si c'est déjà formaté mais en anglais
        let formatted = dateString;
        const monthNames = {
            'January': 'Janvier', 'February': 'Février', 'March': 'Mars',
            'April': 'Avril', 'May': 'Mai', 'June': 'Juin',
            'July': 'Juillet', 'August': 'Août', 'September': 'Septembre',
            'October': 'Octobre', 'November': 'Novembre', 'December': 'Décembre'
        };

        Object.keys(monthNames).forEach(englishMonth => {
            formatted = formatted.replace(englishMonth, monthNames[englishMonth]);
        });

        return formatted;
    } catch (error) {
        return dateString;
    }
}

// ==============================
// GESTION DES INSTANCES CHART.JS
// ==============================

/**
 * Stockage des instances de graphiques pour éviter les conflits
 */
window.chartInstances = window.chartInstances || {};

/**
 * Détruit un graphique existant s'il existe
 * @param {string} canvasId - ID du canvas
 */
function destroyChartIfExists(canvasId) {
    if (window.chartInstances[canvasId]) {
        window.chartInstances[canvasId].destroy();
        delete window.chartInstances[canvasId];
    }
}

/**
 * Graphique comparatif des prix par fournisseur avec design premium
 * @param {string} canvasId - ID du canvas
 * @param {Array} fournisseursData - Données des fournisseurs
 * @param {string} productName - Nom du produit
 */
function renderProductSupplierPricesChart(canvasId, fournisseursData, productName = '') {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !fournisseursData.length) return;

    // DÉTRUIRE LE GRAPHIQUE EXISTANT S'IL EXISTE
    destroyChartIfExists(canvasId);

    const canvasContext = ctx.getContext('2d');

    // Couleurs dynamiques pour chaque fournisseur
    const backgroundColors = fournisseursData.map((_, index) => {
        const hue = (index * 137) % 360;
        return `hsla(${hue}, 70%, 60%, 0.8)`;
    });

    const borderColors = fournisseursData.map((_, index) => {
        const hue = (index * 137) % 360;
        return `hsla(${hue}, 70%, 45%, 1)`;
    });

    // CRÉER ET STOCKER LA NOUVELLE INSTANCE
    window.chartInstances[canvasId] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: fournisseursData.map(item => item.fournisseur),
            datasets: [
                {
                    label: 'Prix Moyen',
                    data: fournisseursData.map(item => item.prix_moyen || 0),
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 3,
                    borderRadius: 12,
                    borderSkipped: false,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8,
                    hoverOffset: 8
                },
                {
                    label: 'Dernier Prix',
                    data: fournisseursData.map(item => item.dernier_prix || 0),
                    backgroundColor: 'rgba(59, 130, 246, 0.3)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.5,
                    categoryPercentage: 0.8,
                    type: 'line',
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 3,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            animation: {
                duration: 2000,
                easing: 'easeInOutBounce',
                delay: (context) => context.dataIndex * 150
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 13,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.95)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#4361ee',
                    borderWidth: 2,
                    cornerRadius: 12,
                    displayColors: true,
                    padding: 16,
                    titleFont: {
                        size: 15,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 14
                    },
                    callbacks: {
                        title: function (context) {
                            return `${context[0].label} - ${productName}`;
                        },
                        label: function (context) {
                            const fournisseur = fournisseursData[context.dataIndex];
                            if (context.datasetIndex === 0) {
                                return [
                                    `Prix moyen: ${formatMoney(context.raw)}`,
                                    `Commandes: ${fournisseur.nb_commandes}`,
                                    `Quantité totale: ${new Intl.NumberFormat('fr-FR').format(fournisseur.quantite_totale)}`
                                ];
                            } else {
                                return `Dernier prix: ${formatMoney(context.raw)}`;
                            }
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 12,
                            weight: '500'
                        },
                        color: '#64748b',
                        maxRotation: 45
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    title: {
                        display: true,
                        text: 'Prix (FCFA)',
                        font: {
                            size: 14,
                            weight: '600'
                        },
                        color: '#374151'
                    },
                    ticks: {
                        font: {
                            size: 12
                        },
                        color: '#64748b',
                        callback: function (value) {
                            return formatMoney(value, false);
                        }
                    }
                }
            }
        }
    });
}

/**
 * Graphique de répartition des achats par fournisseur avec design premium
 * @param {string} canvasId - ID du canvas
 * @param {Array} fournisseursData - Données des fournisseurs
 * @param {string} productName - Nom du produit
 */
function renderProductSupplierDistributionChart(canvasId, fournisseursData, productName = '') {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !fournisseursData.length) return;

    // DÉTRUIRE LE GRAPHIQUE EXISTANT S'IL EXISTE
    destroyChartIfExists(canvasId);

    // Couleurs sophistiquées inspirées du design moderne
    const colors = [
        '#4361ee', '#7209b7', '#f72585', '#4cc9f0', '#10b981',
        '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#22c55e'
    ];

    // CRÉER ET STOCKER LA NOUVELLE INSTANCE
    window.chartInstances[canvasId] = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: fournisseursData.map(item => item.fournisseur),
            datasets: [{
                data: fournisseursData.map(item => item.quantite_totale || 0),
                backgroundColor: colors.slice(0, fournisseursData.length),
                borderColor: 'white',
                borderWidth: 4,
                hoverOffset: 20,
                hoverBorderWidth: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 2500,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 25,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 12,
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.95)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#4361ee',
                    borderWidth: 2,
                    cornerRadius: 12,
                    displayColors: true,
                    padding: 16,
                    titleFont: {
                        size: 15,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 14
                    },
                    callbacks: {
                        title: function (context) {
                            return `${context[0].label} - ${productName}`;
                        },
                        label: function (context) {
                            const value = context.raw;
                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                            const percentage = Math.round((value / total) * 100);
                            const fournisseur = fournisseursData[context.dataIndex];

                            return [
                                `Quantité: ${new Intl.NumberFormat('fr-FR').format(value)} unités`,
                                `Part: ${percentage}%`,
                                `Commandes: ${fournisseur.nb_commandes}`,
                                `Prix moyen: ${formatMoney(fournisseur.prix_moyen || 0)}`
                            ];
                        }
                    }
                }
            }
        }
    });
}

/**
 * Graphique d'évolution des prix du produit avec design premium et courbes ultra-lisses
 * @param {string} canvasId - ID du canvas
 * @param {Array} evolutionData - Données d'évolution des prix
 * @param {string} productName - Nom du produit
 */
function renderProductPriceEvolutionChart(canvasId, evolutionData, productName = '') {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !evolutionData.length) return;

    // DÉTRUIRE LE GRAPHIQUE EXISTANT S'IL EXISTE
    destroyChartIfExists(canvasId);

    const canvasContext = ctx.getContext('2d');

    // Création des gradients premium avec transparence douce
    const gradientPrixMoyen = canvasContext.createLinearGradient(0, 0, 0, 400);
    gradientPrixMoyen.addColorStop(0, 'rgba(67, 97, 238, 0.4)');
    gradientPrixMoyen.addColorStop(0.5, 'rgba(67, 97, 238, 0.2)');
    gradientPrixMoyen.addColorStop(1, 'rgba(67, 97, 238, 0.05)');

    // Utilisation directe des labels français depuis PHP
    const frenchLabels = evolutionData.map(item => item.mois_format || item.mois || '');

    // CRÉER ET STOCKER LA NOUVELLE INSTANCE
    window.chartInstances[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: frenchLabels,
            datasets: [
                {
                    label: 'Prix Moyen',
                    data: evolutionData.map(item => item.prix_moyen_mois || 0),
                    borderColor: '#4361ee',
                    backgroundColor: gradientPrixMoyen,
                    fill: true,
                    tension: 0.9,
                    pointRadius: 6,
                    pointHoverRadius: 10,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#4361ee',
                    pointBorderWidth: 3,
                    pointHoverBackgroundColor: '#4361ee',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 4,
                    borderWidth: 4,
                    cubicInterpolationMode: 'monotone'
                },
                {
                    label: 'Prix Minimum',
                    data: evolutionData.map(item => item.prix_min_mois || 0),
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.9,
                    borderDash: [15, 8],
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#10b981',
                    pointBorderWidth: 2,
                    borderWidth: 3,
                    cubicInterpolationMode: 'monotone'
                },
                {
                    label: 'Prix Maximum',
                    data: evolutionData.map(item => item.prix_max_mois || 0),
                    borderColor: '#ef4444',
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.9,
                    borderDash: [15, 8],
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#ef4444',
                    pointBorderWidth: 2,
                    borderWidth: 3,
                    cubicInterpolationMode: 'monotone'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            animation: {
                duration: 2500,
                easing: 'easeInOutQuart',
                delay: (context) => context.dataIndex * 100
            },
            elements: {
                line: {
                    tension: 0.9,
                    capStyle: 'round',
                    joinStyle: 'round'
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: {
                            size: 13,
                            weight: '600',
                            family: "'Inter', sans-serif"
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.95)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#4361ee',
                    borderWidth: 2,
                    cornerRadius: 12,
                    displayColors: true,
                    padding: 16,
                    titleFont: {
                        size: 15,
                        weight: 'bold',
                        family: "'Inter', sans-serif"
                    },
                    bodyFont: {
                        size: 14,
                        family: "'Inter', sans-serif"
                    },
                    callbacks: {
                        title: function (context) {
                            return `${productName} - ${context[0].label}`;
                        },
                        label: function (context) {
                            return `${context.dataset.label}: ${formatMoney(context.raw)}`;
                        },
                        afterBody: function (tooltipItems) {
                            const monthData = evolutionData[tooltipItems[0].dataIndex];
                            return [
                                '',
                                `Achats du mois: ${monthData.nb_achats_mois || 0}`,
                                `Écart min-max: ${formatMoney((monthData.prix_max_mois || 0) - (monthData.prix_min_mois || 0))}`
                            ];
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 12,
                            weight: '500',
                            family: "'Inter', sans-serif"
                        },
                        color: '#64748b',
                        maxRotation: 45
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    title: {
                        display: true,
                        text: 'Prix (FCFA)',
                        font: {
                            size: 14,
                            weight: '600',
                            family: "'Inter', sans-serif"
                        },
                        color: '#374151'
                    },
                    ticks: {
                        font: {
                            size: 12,
                            family: "'Inter', sans-serif"
                        },
                        color: '#64748b',
                        callback: function (value) {
                            return formatMoney(value, false);
                        }
                    }
                }
            }
        }
    });
}

/**
 * Initialise la gestion des onglets pour la page de détails produit
 * @param {Object} chartData - Données pour les graphiques
 * @param {string} productName - Nom du produit
 * @param {string} unit - Unité du produit
 */
function initProductDetailsPage(chartData, productName = '', unit = 'unités') {
    // Configuration Chart.js
    setupAdvancedChartDefaults();

    // Gestion des onglets
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    // Variable pour tracker les graphiques déjà créés
    let suppliersChartsCreated = false;

    // Initialiser le premier DataTable
    setTimeout(() => {
        initProductDataTable('movementsTable', `mouvements-${productName.replace(/[^a-zA-Z0-9]/g, '-')}`);
    }, 100);

    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            // Désactiver tous les boutons
            tabButtons.forEach(btn => {
                btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            // Activer le bouton cliqué
            this.classList.add('active', 'border-blue-500', 'text-blue-600');
            this.classList.remove('border-transparent', 'text-gray-500');

            // Masquer tous les contenus
            tabContents.forEach(content => content.classList.remove('active'));

            // Afficher le contenu correspondant
            const tabId = this.getAttribute('data-tab');
            document.getElementById(`tab-${tabId}`).classList.add('active');

            // Initialiser le DataTable et graphiques correspondants
            setTimeout(() => {
                const productSlug = productName.replace(/[^a-zA-Z0-9]/g, '-');

                switch (tabId) {
                    case 'movements':
                        initProductDataTable('movementsTable', `mouvements-${productSlug}`);
                        break;
                    case 'achats':
                        initProductDataTable('achatsTable', `achats-${productSlug}`);
                        break;
                    case 'projets':
                        initProductDataTable('projetsTable', `projets-${productSlug}`);
                        break;
                    case 'fournisseurs':
                        initProductDataTable('fournisseursTable', `fournisseurs-${productSlug}`);
                        // Initialiser les graphiques spécifiques aux fournisseurs SEULEMENT s'ils n'existent pas
                        if (!suppliersChartsCreated && chartData.fournisseurs && chartData.fournisseurs.length > 0) {
                            setTimeout(() => {
                                renderProductSupplierPricesChart('fournisseursPrixChart', chartData.fournisseurs, productName);
                                renderProductSupplierDistributionChart('fournisseursRepartitionChart', chartData.fournisseurs, productName);
                                suppliersChartsCreated = true;
                            }, 200);
                        }
                        break;
                    case 'evolution':
                        initProductDataTable('evolutionTable', `evolution-${productSlug}`);
                        break;
                }
            }, 150);
        });
    });

    // Initialiser les graphiques principaux avec un délai
    setTimeout(() => {
        if (chartData.evolutionPrix && chartData.evolutionPrix.length > 0) {
            renderProductPriceEvolutionChart('prixEvolutionChart', chartData.evolutionPrix, productName);
        }

        if (chartData.evolutionStock && chartData.evolutionStock.length > 0) {
            renderProductStockEvolutionChart('evolutionChart', chartData.evolutionStock, productName, unit);
        }

        // Mini graphique de tendance des prix si disponible
        if (chartData.evolutionPrix && chartData.evolutionPrix.length > 0) {
            const prixData = chartData.evolutionPrix.map(item => item.prix_moyen_mois || 0);
            createProductMiniTrendChart('prixMoyenTrendChart', prixData, 'neutral');
        }
    }, 500);
}

// Exporter les fonctions
window.formatMoney = formatMoney;
window.formatPercent = formatPercent;
window.formatDate = formatDate;
window.renderPurchasesChart = renderPurchasesChart;
window.renderCategoriesChart = renderCategoriesChart;
window.renderMonthlyPurchasesChart = renderMonthlyPurchasesChart;
window.renderSupplierPerformanceChart = renderSupplierPerformanceChart;
window.renderCategoriesStockChart = renderCategoriesStockChart;
window.renderPriceEvolutionChart = renderPriceEvolutionChart;
window.renderSupplierReturnsChart = renderSupplierReturnsChart;
window.renderCanceledOrdersChart = renderCanceledOrdersChart;
window.renderCanceledProductsChart = renderCanceledProductsChart;
window.renderCancellationReasonsChart = renderCancellationReasonsChart;
window.createMiniChart = createMiniChart;

window.setupAdvancedChartDefaults = setupAdvancedChartDefaults;
window.renderProductPriceEvolutionChart = renderProductPriceEvolutionChart;
window.renderProductSupplierPricesChart = renderProductSupplierPricesChart;
window.renderProductSupplierDistributionChart = renderProductSupplierDistributionChart;
window.renderProductStockEvolutionChart = renderProductStockEvolutionChart;
window.createProductMiniTrendChart = createProductMiniTrendChart;
window.initProductDetailsPage = initProductDetailsPage;
window.initProductDataTable = initProductDataTable;

window.convertMonthToFrench = convertMonthToFrench;
window.formatDateToFrench = formatDateToFrench;

window.destroyChartIfExists = destroyChartIfExists;