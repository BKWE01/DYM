<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Rediriger vers index.php
    header("Location: ./../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Apex Charts pour des visualisations plus avancées -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        body, #main-content {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
        }
        
        /* Styles généraux */
        .card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transform: translateY(-3px);
        }
        
        /* Stats Card */
        .stats-card {
            transition: all 0.3s ease;
            background-image: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            backdrop-filter: blur(10px);
        }
        
        .stats-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Animations */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse-animation {
            animation: pulse 3s infinite;
        }
        
        /* Boutons */
        .action-button {
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .action-button::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(0);
            transition: transform 0.5s ease;
        }
        
        .action-button:hover::after {
            transform: translate(-50%, -50%) scale(2);
        }
        
        .action-button:hover {
            transform: translateY(-2px);
        }
        
        /* Tableau d'activité récente */
        .activity-item {
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }
        
        .activity-item:hover {
            border-left-width: 6px;
            background-color: rgba(243, 244, 246, 0.5);
        }
        
        .entry-activity {
            border-left-color: #10b981;
        }
        
        .output-activity {
            border-left-color: #ef4444;
        }
        
        /* Graphique */
        .chart-container {
            border-radius: 16px;
            background: linear-gradient(145deg, #ffffff, #f5f7fa);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }
        
        /* Scrollbar personnalisée */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Produits populaires */
        .product-card {
            transition: all 0.3s ease;
            border-radius: 14px;
            overflow: hidden;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .product-indicator {
            height: 6px;
            border-radius: 3px;
            margin-top: 6px;
        }
        
        /* Skeleton loader pour le chargement */
        .skeleton {
            background: linear-gradient(90deg, rgba(229, 232, 235, 0.8) 25%, rgba(215, 219, 223, 0.6) 37%, rgba(229, 232, 235, 0.8) 63%);
            background-size: 400% 100%;
            animation: skeleton-loading 1.4s ease infinite;
        }
        
        @keyframes skeleton-loading {
            0% { background-position: 100% 50%; }
            100% { background-position: 0 50%; }
        }
    </style>
</head>
<body class="antialiased">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include_once 'sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col overflow-hidden">
            <?php include_once 'header.php'; ?>

            <main class="p-6 flex-1 overflow-y-auto">
                <div class="max-w-7xl mx-auto">
                    <!-- En-tête du dashboard -->
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-800">Tableau de bord</h1>
                        <p class="text-gray-600 mt-1">Bienvenue sur DYM STOCK - Aperçu de votre inventaire</p>
                    </div>

                    <!-- Cartes de statistiques -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Total des produits -->
                        <div class="card stats-card p-6 relative overflow-hidden" style="border-top: 4px solid #3b82f6;">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 mb-1">Total des produits</p>
                                    <h3 id="total-materials" class="text-2xl font-bold text-gray-800 skeleton h-8 w-20 rounded">&nbsp;</h3>
                                    <p class="text-xs text-green-600 font-medium mt-2 flex items-center">
                                        <span class="material-icons-round text-base mr-1">trending_up</span>
                                        <span id="products-growth">+2.5% ce mois</span>
                                    </p>
                                </div>
                                <div class="stats-card-icon bg-blue-100 text-blue-600">
                                    <span class="material-icons-round">inventory_2</span>
                                </div>
                            </div>
                            <div class="absolute -bottom-4 -right-4 w-24 h-24 bg-blue-500 opacity-10 rounded-full"></div>
                        </div>

                        <!-- Catégories -->
                        <div class="card stats-card p-6 relative overflow-hidden" style="border-top: 4px solid #8b5cf6;">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 mb-1">Catégories</p>
                                    <h3 id="categories" class="text-2xl font-bold text-gray-800 skeleton h-8 w-16 rounded">&nbsp;</h3>
                                    <p class="text-xs text-purple-600 font-medium mt-2 flex items-center">
                                        <span class="material-icons-round text-base mr-1">category</span>
                                        <span>Groupes de produits</span>
                                    </p>
                                </div>
                                <div class="stats-card-icon bg-purple-100 text-purple-600">
                                    <span class="material-icons-round">category</span>
                                </div>
                            </div>
                            <div class="absolute -bottom-4 -right-4 w-24 h-24 bg-purple-500 opacity-10 rounded-full"></div>
                        </div>

                        <!-- Produits en alerte -->
                        <div class="card stats-card p-6 relative overflow-hidden" style="border-top: 4px solid #ef4444;">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 mb-1">Produits en alerte</p>
                                    <h3 id="materials-in-alert" class="text-2xl font-bold text-gray-800 skeleton h-8 w-16 rounded">&nbsp;</h3>
                                    <p class="text-xs text-red-600 font-medium mt-2 flex items-center">
                                        <span class="material-icons-round text-base mr-1">warning</span>
                                        <span>Stock faible ou épuisé</span>
                                    </p>
                                </div>
                                <div class="stats-card-icon bg-red-100 text-red-600">
                                    <span class="material-icons-round">error_outline</span>
                                </div>
                            </div>
                            <div class="absolute -bottom-4 -right-4 w-24 h-24 bg-red-500 opacity-10 rounded-full"></div>
                        </div>

                        <!-- Valeur totale du stock -->
                        <div class="card stats-card p-6 relative overflow-hidden" style="border-top: 4px solid #10b981;">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 mb-1">Valeur du stock</p>
                                    <h3 id="stock-value" class="text-2xl font-bold text-gray-800 skeleton h-8 w-28 rounded">&nbsp;</h3>
                                    <p class="text-xs text-green-600 font-medium mt-2 flex items-center">
                                        <span class="material-icons-round text-base mr-1">paid</span>
                                        <span>Estimation actuelle</span>
                                    </p>
                                </div>
                                <div class="stats-card-icon bg-green-100 text-green-600">
                                    <span class="material-icons-round">account_balance_wallet</span>
                                </div>
                            </div>
                            <div class="absolute -bottom-4 -right-4 w-24 h-24 bg-green-500 opacity-10 rounded-full"></div>
                        </div>
                    </div>

                    <!-- Contenu principal - 2 colonnes -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                        <!-- Colonne de gauche - Activité récente -->
                        <div class="lg:col-span-1">
                            <div class="card p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-bold text-gray-800">Activité récente</h3>
                                    <a href="entrées_sorties.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                        Tout voir
                                        <span class="material-icons-round text-lg ml-1">chevron_right</span>
                                    </a>
                                </div>
                                
                                <div class="space-y-3" id="recent-activity-container">
                                    <!-- Placeholder de chargement -->
                                    <div class="skeleton h-14 rounded mb-2"></div>
                                    <div class="skeleton h-14 rounded mb-2"></div>
                                    <div class="skeleton h-14 rounded mb-2"></div>
                                    <div class="skeleton h-14 rounded mb-2"></div>
                                </div>
                                
                                <div id="recent-activity-list" class="space-y-3 hidden">
                                    <!-- Activités récentes seront injectées ici -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Colonne de droite - Actions rapides et graphique -->
                        <div class="lg:col-span-2">
                            <!-- Actions rapides -->
                            <div class="card p-6 mb-8">
                                <h3 class="text-lg font-bold text-gray-800 mb-4">Actions rapides</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                                    <button onclick="window.location.href='product_entry.php'" class="action-button flex flex-col items-center justify-center p-4 rounded-xl bg-green-500 text-white hover:bg-green-600 transition-all shadow-md">
                                        <span class="material-icons-round text-3xl mb-2">add_box</span>
                                        <span class="font-medium">Entrée</span>
                                    </button>
                                    
                                    <button onclick="window.location.href='product_output.php'" class="action-button flex flex-col items-center justify-center p-4 rounded-xl bg-red-500 text-white hover:bg-red-600 transition-all shadow-md">
                                        <span class="material-icons-round text-3xl mb-2">indeterminate_check_box</span>
                                        <span class="font-medium">Sortie</span>
                                    </button>
                                    
                                    <button onclick="window.location.href='inventory.php'" class="action-button flex flex-col items-center justify-center p-4 rounded-xl bg-amber-500 text-white hover:bg-amber-600 transition-all shadow-md"
                                    style="background-color: black;">
                                        <span class="material-icons-round text-3xl mb-2">assignment</span>
                                        <span class="font-medium">Inventaire</span>
                                    </button>
                                    
                                    <button onclick="window.location.href='transfert/transfert_manager.php'" class="action-button flex flex-col items-center justify-center p-4 rounded-xl bg-purple-500 text-white hover:bg-purple-600 transition-all shadow-md">
                                        <span class="material-icons-round text-3xl mb-2">swap_horiz</span>
                                        <span class="font-medium">Transfert</span>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Évolution du stock (graphique) -->
                            <div class="card p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-bold text-gray-800">Évolution du stock</h3>
                                    <div class="flex space-x-2">
                                        <button id="viewWeekBtn" class="px-3 py-1 rounded text-sm font-medium bg-gray-200 hover:bg-gray-300 transition">Semaine</button>
                                        <button id="viewMonthBtn" class="px-3 py-1 rounded text-sm font-medium bg-blue-500 text-white hover:bg-blue-600 transition">Mois</button>
                                        <button id="viewYearBtn" class="px-3 py-1 rounded text-sm font-medium bg-gray-200 hover:bg-gray-300 transition">Année</button>
                                    </div>
                                </div>
                                <div class="chart-container p-2 h-80">
                                    <canvas id="stockChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Produits les plus utilisés -->
                    <div class="mb-8">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-gray-800">Produits les plus utilisés</h3>
                            <a href="liste_produits.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                Voir tous les produits
                                <span class="material-icons-round text-lg ml-1">chevron_right</span>
                            </a>
                        </div>
                        <div id="most-used-products-loader" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Placeholders de chargement -->
                            <div class="skeleton h-32 w-full rounded"></div>
                            <div class="skeleton h-32 w-full rounded"></div>
                            <div class="skeleton h-32 w-full rounded"></div>
                            <div class="skeleton h-32 w-full rounded"></div>
                        </div>
                        <div id="most-used-products" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 hidden">
                            <!-- Les produits les plus utilisés seront injectés ici -->
                        </div>
                    </div>
                    
                    <!-- Stock par catégorie (donut chart) -->
                    <div class="card p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Répartition du stock par catégorie</h3>
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div class="lg:col-span-1 chart-container flex items-center justify-center" style="height: 300px;">
                                <div id="categoryChart" style="width: 100%; height: 100%;"></div>
                            </div>
                            <div class="lg:col-span-2">
                                <div id="category-stats-loader" class="skeleton h-64 w-full rounded"></div>
                                <div id="category-stats" class="overflow-auto max-h-64 hidden">
                                    <table class="min-w-full">
                                        <thead>
                                            <tr>
                                                <th class="py-2 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catégorie</th>
                                                <th class="py-2 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre produits</th>
                                                <th class="py-2 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valeur</th>
                                                <th class="py-2 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">% du total</th>
                                            </tr>
                                        </thead>
                                        <tbody id="category-stats-body">
                                            <!-- Les données des catégories seront injectées ici -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Simuler un délai de chargement pour la démonstration
        setTimeout(() => {
            // Charger les données réelles
            fetch('api_dashboard.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateDashboard(data.data);
                    } else {
                        console.error('Erreur lors de la récupération des données:', data.message);
                        showErrorMessage();
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showErrorMessage();
                });
        }, 800); // Délai simulé pour montrer les skeletons
        
        // Gestion des boutons de périodes pour le graphique
        document.getElementById('viewWeekBtn').addEventListener('click', function() {
            updateChartPeriod('week', this);
        });
        
        document.getElementById('viewMonthBtn').addEventListener('click', function() {
            updateChartPeriod('month', this);
        });
        
        document.getElementById('viewYearBtn').addEventListener('click', function() {
            updateChartPeriod('year', this);
        });
    });
    
    function updateChartPeriod(period, button) {
        // Réinitialiser tous les boutons
        document.querySelectorAll('#viewWeekBtn, #viewMonthBtn, #viewYearBtn').forEach(btn => {
            btn.classList.remove('bg-blue-500', 'text-white');
            btn.classList.add('bg-gray-200');
        });
        
        // Activer le bouton sélectionné
        button.classList.remove('bg-gray-200');
        button.classList.add('bg-blue-500', 'text-white');
        
        // Ici, vous pourriez appeler une API pour obtenir des données pour la période spécifiée
        // et mettre à jour le graphique en conséquence. Pour cette démo, nous utilisons des données fictives.
        
        // Simuler un chargement
        const ctx = document.getElementById('stockChart').getContext('2d');
        ctx.canvas.style.opacity = '0.5';
        
        setTimeout(() => {
            // Pour la démonstration, générons des données fictives selon la période
            let labels, entries, outputs;
            
            if (period === 'week') {
                labels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
                entries = [12, 19, 13, 15, 22, 8, 5];
                outputs = [8, 14, 10, 12, 19, 6, 3];
            } else if (period === 'month') {
                labels = ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'];
                entries = [45, 59, 72, 48];
                outputs = [38, 49, 62, 41];
            } else {
                labels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
                entries = [120, 180, 220, 190, 250, 280, 300, 330, 290, 310, 350, 390];
                outputs = [100, 150, 190, 170, 220, 250, 270, 300, 260, 280, 320, 360];
            }
            
            // Mettre à jour le graphique
            if (window.stockChart) {
                window.stockChart.data.labels = labels;
                window.stockChart.data.datasets[0].data = entries;
                window.stockChart.data.datasets[1].data = outputs;
                window.stockChart.update();
                ctx.canvas.style.opacity = '1';
            }
        }, 500);
    }
    
    function updateDashboard(data) {
        // Mise à jour des statistiques générales
        document.getElementById('total-materials').textContent = data.general_stats.total_materials;
        document.getElementById('total-materials').classList.remove('skeleton');
        
        document.getElementById('categories').textContent = data.general_stats.categories;
        document.getElementById('categories').classList.remove('skeleton');
        
        document.getElementById('materials-in-alert').textContent = data.general_stats.materials_in_alert;
        document.getElementById('materials-in-alert').classList.remove('skeleton');
        
        // Nouvelle statistique : valeur du stock
        document.getElementById('stock-value').textContent = '---';
        // document.getElementById('stock-value').textContent = formatMoney(data.general_stats.stock_value || 0);
        document.getElementById('stock-value').classList.remove('skeleton');

        // Mise à jour de l'activité récente
        const recentActivityContainer = document.getElementById('recent-activity-container');
        const recentActivityList = document.getElementById('recent-activity-list');
        
        recentActivityList.innerHTML = '';
        
        if (data.recent_activity && data.recent_activity.length > 0) {
            data.recent_activity.forEach(activity => {
                const isEntry = activity.movement_type === 'entry';
                const activityClass = isEntry ? 'entry-activity' : 'output-activity';
                const iconName = isEntry ? 'add_circle' : 'remove_circle';
                const iconColor = isEntry ? 'text-green-500' : 'text-red-500';
                const actionText = isEntry ? 'Entrée' : 'Sortie';
                
                const li = document.createElement('div');
                li.className = `activity-item ${activityClass} p-3 rounded-md`;
                li.innerHTML = `
                    <div class="flex items-center">
                        <span class="material-icons-round ${iconColor} mr-3">${iconName}</span>
                        <div class="flex-1">
                            <p class="text-sm font-medium">${actionText} de ${activity.quantity} ${activity.product_name}</p>
                            <p class="text-xs text-gray-500">${formatDate(activity.date || new Date())}</p>
                        </div>
                    </div>
                `;
                recentActivityList.appendChild(li);
            });
            
            // Afficher la liste et masquer le placeholder
            recentActivityContainer.classList.add('hidden');
            recentActivityList.classList.remove('hidden');
        } else {
            const noActivity = document.createElement('div');
            noActivity.className = "text-center p-4 text-gray-500";
            noActivity.textContent = "Aucune activité récente";
            recentActivityList.appendChild(noActivity);
            
            recentActivityContainer.classList.add('hidden');
            recentActivityList.classList.remove('hidden');
        }

        // Mise à jour des produits les plus utilisés
        const mostUsedProductsLoader = document.getElementById('most-used-products-loader');
        const mostUsedProductsContainer = document.getElementById('most-used-products');
        
        mostUsedProductsContainer.innerHTML = '';
        
        if (data.most_used_products && data.most_used_products.length > 0) {
            data.most_used_products.forEach(product => {
                // Calculer le pourcentage utilisé pour la barre de progression
                const totalQuantity = product.used_quantity + product.remaining_stock;
                const usedPercentage = (product.used_quantity / totalQuantity) * 100;
                
                // Déterminer la couleur en fonction du pourcentage utilisé
                let progressColor;
                if (usedPercentage > 75) {
                    progressColor = 'bg-red-500';
                } else if (usedPercentage > 50) {
                    progressColor = 'bg-amber-500';
                } else {
                    progressColor = 'bg-green-500';
                }
                
                const div = document.createElement('div');
                div.className = 'product-card bg-white p-5 shadow hover:shadow-lg transition-all';
                div.innerHTML = `
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-semibold text-gray-800 truncate" title="${product.product_name}">${product.product_name}</h4>
                        <span class="material-icons-round text-gray-400 ml-2">inventory_2</span>
                    </div>
                    <div class="flex flex-col space-y-1 mb-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Utilisé</span>
                            <span class="font-medium text-gray-800">${product.used_quantity}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">En stock</span>
                            <span class="font-medium text-gray-800">${product.remaining_stock}</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="${progressColor} h-2 rounded-full" style="width: ${usedPercentage}%"></div>
                    </div>
                    <div class="text-xs text-right mt-1 text-gray-500">${Math.round(usedPercentage)}% utilisé</div>
                `;
                mostUsedProductsContainer.appendChild(div);
            });
            
            // Afficher les produits et masquer le loader
            mostUsedProductsLoader.classList.add('hidden');
            mostUsedProductsContainer.classList.remove('hidden');
        } else {
            const noProducts = document.createElement('div');
            noProducts.className = "col-span-full text-center p-4 text-gray-500";
            noProducts.textContent = "Aucune donnée disponible";
            mostUsedProductsContainer.appendChild(noProducts);
            
            // Afficher les produits et masquer le loader
            mostUsedProductsLoader.classList.add('hidden');
            mostUsedProductsContainer.classList.remove('hidden');
        }

        // Création du graphique d'évolution du stock
        createStockChart(data.stock_evolution);
        
        // Création du graphique de répartition par catégorie
        createCategoryChart(data.category_distribution);
        
        // Mise à jour des statistiques par catégorie
        updateCategoryStats(data.category_distribution);
    }

    function createStockChart(data) {
        const ctx = document.getElementById('stockChart').getContext('2d');
        
        // Créer des dégradés pour les zones sous les courbes
        const gradient1 = ctx.createLinearGradient(0, 0, 0, 400);
        gradient1.addColorStop(0, 'rgba(59, 130, 246, 0.5)');
        gradient1.addColorStop(1, 'rgba(59, 130, 246, 0.05)');

        const gradient2 = ctx.createLinearGradient(0, 0, 0, 400);
        gradient2.addColorStop(0, 'rgba(239, 68, 68, 0.5)');
        gradient2.addColorStop(1, 'rgba(239, 68, 68, 0.05)');
        
        // Créer ou mettre à jour le graphique
        window.stockChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                datasets: [{
                    label: 'Entrées',
                    data: data ? data.entries : [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    borderColor: '#3b82f6',
                    backgroundColor: gradient1,
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }, {
                    label: 'Sorties',
                    data: data ? data.outputs : [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    borderColor: '#ef4444',
                    backgroundColor: gradient2,
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.8)',
                        titleFont: {
                            family: "'Poppins', sans-serif",
                            size: 13,
                        },
                        bodyFont: {
                            family: "'Poppins', sans-serif",
                            size: 12
                        },
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(156, 163, 175, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 11
                            },
                            color: '#6B7280'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 11
                            },
                            color: '#6B7280'
                        }
                    }
                }
            }
        });
    }
    
    function createCategoryChart(data) {
        if (!data || !data.length) {
            // Si pas de données, afficher un message
            document.getElementById('categoryChart').innerHTML = '<div class="flex items-center justify-center h-full text-gray-500">Aucune donnée disponible</div>';
            return;
        }
        
        // Préparer les données pour le graphique
        const labels = data.map(item => item.name);
        const values = data.map(item => item.count);
        const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#6366f1', '#14b8a6', '#f97316', '#6b7280'];
        
        // Créer le graphique avec ApexCharts
        const options = {
            series: values,
            chart: {
                type: 'donut',
                height: 300,
                fontFamily: "'Poppins', sans-serif",
            },
            labels: labels,
            colors: colors,
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                fontSize: '14px',
                                fontWeight: 600,
                                offsetY: -10,
                            },
                            value: {
                                show: true,
                                fontSize: '20px',
                                fontWeight: 700,
                                formatter: function (val) {
                                    return val;
                                }
                            },
                            total: {
                                show: true,
                                fontSize: '14px',
                                fontWeight: 600,
                                label: 'Total',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            legend: {
                show: false
            },
            tooltip: {
                enabled: true,
                fillSeriesColor: false,
                style: {
                    fontSize: '12px',
                    fontFamily: "'Poppins', sans-serif",
                }
            },
            stroke: {
                width: 2,
                colors: ['transparent']
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        height: 280
                    }
                }
            }]
        };

        const chart = new ApexCharts(document.getElementById('categoryChart'), options);
        chart.render();
    }
    
    function updateCategoryStats(data) {
        const categoryStatsLoader = document.getElementById('category-stats-loader');
        const categoryStats = document.getElementById('category-stats');
        const categoryStatsBody = document.getElementById('category-stats-body');
        
        if (!data || !data.length) {
            categoryStatsBody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-gray-500">Aucune donnée disponible</td></tr>';
            categoryStatsLoader.classList.add('hidden');
            categoryStats.classList.remove('hidden');
            return;
        }
        
        // Calculer la somme totale
        const totalCount = data.reduce((sum, item) => sum + item.count, 0);
        
        // Trier les données par nombre de produits (décroissant)
        const sortedData = [...data].sort((a, b) => b.count - a.count);
        
        // Vider le tableau et ajouter les nouvelles données
        categoryStatsBody.innerHTML = '';
        
        sortedData.forEach((category, index) => {
            const percentage = ((category.count / totalCount) * 100).toFixed(1);
            const row = document.createElement('tr');
            
            // Alterner les couleurs de fond
            if (index % 2 === 0) {
                row.className = 'bg-gray-50';
            }
            
            row.innerHTML = `
                <td class="py-2 px-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-2" style="background-color: ${getCategoryColor(index)}"></div>
                        <span class="font-medium">${category.name}</span>
                    </div>
                </td>
                <td class="py-2 px-4 whitespace-nowrap text-sm">${category.count}</td>
                <td class="py-2 px-4 whitespace-nowrap text-sm">---</td>
                <td class="py-2 px-4 whitespace-nowrap text-sm font-medium">${percentage}%</td>
                `;
                // <td class="py-2 px-4 whitespace-nowrap text-sm">${formatMoney(category.value || 0)}</td>
            
            categoryStatsBody.appendChild(row);
        });
        
        // Afficher les statistiques et masquer le loader
        categoryStatsLoader.classList.add('hidden');
        categoryStats.classList.remove('hidden');
    }
    
    // Fonction pour obtenir une couleur pour une catégorie
    function getCategoryColor(index) {
        const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#6366f1', '#14b8a6', '#f97316', '#6b7280'];
        return colors[index % colors.length];
    }
    
    // Fonction pour formater une date
    function formatDate(dateString) {
        const date = new Date(dateString);
        // Options de formatage - ajuster selon vos préférences
        const options = { 
            day: '2-digit', 
            month: 'short', 
            hour: '2-digit', 
            minute: '2-digit'
        };
        return date.toLocaleDateString('fr-FR', options);
    }
    
    // Fonction pour formater un montant en devise
    function formatMoney(amount) {
        return new Intl.NumberFormat('fr-FR', { 
            style: 'currency', 
            currency: 'CFA',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    }
    
    // Fonction pour afficher un message d'erreur
    function showErrorMessage() {
        // Enlever les squelettes de chargement
        document.querySelectorAll('.skeleton').forEach(el => {
            if (el.id) {
                document.getElementById(el.id).textContent = "Erreur";
                document.getElementById(el.id).classList.remove('skeleton');
            } else {
                el.innerHTML = '<div class="text-red-500 p-4 text-center">Erreur de chargement des données</div>';
                el.classList.remove('skeleton');
            }
        });
        
        // Afficher des messages d'erreur dans les conteneurs
        document.getElementById('recent-activity-container').innerHTML = '<div class="text-red-500 p-4 text-center">Impossible de charger les activités récentes</div>';
        document.getElementById('most-used-products-loader').innerHTML = '<div class="text-red-500 p-4 text-center">Impossible de charger les produits</div>';
        document.getElementById('category-stats-loader').innerHTML = '<div class="text-red-500 p-4 text-center">Impossible de charger les statistiques</div>';
    }
    </script>
</body>
</html>