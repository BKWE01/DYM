<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Vérifier si l'utilisateur est de type finance
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    header("Location: ./../index.php?notification=error&message=Accès non autorisé");
    exit();
}

// Inclure la connexion à la base de données
require_once '../../database/connection.php';

// Récupérer les statistiques des appels de fonds
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides,
        SUM(CASE WHEN statut = 'partiellement_valide' THEN 1 ELSE 0 END) as partiels,
        SUM(CASE WHEN statut = 'rejete' THEN 1 ELSE 0 END) as rejetes,
        SUM(CASE WHEN statut = 'en_attente' THEN montant_total ELSE 0 END) as montant_attente,
        SUM(CASE WHEN statut = 'valide' THEN montant_total ELSE 0 END) as montant_valide
    FROM appels_fonds 
    WHERE MONTH(date_creation) = MONTH(CURRENT_DATE()) 
    AND YEAR(date_creation) = YEAR(CURRENT_DATE())
";

$stats_result = $pdo->query($stats_query);
$stats = $stats_result->fetch(PDO::FETCH_ASSOC);

// Récupérer les notifications non lues
$notif_query = "
    SELECT COUNT(*) as non_lues 
    FROM notifications_fonds 
    WHERE user_id = ? AND lu = FALSE
";
$notif_stmt = $pdo->prepare($notif_query);
$notif_stmt->execute([$_SESSION['user_id']]);
$notifications_count = $notif_stmt->fetch(PDO::FETCH_ASSOC)['non_lues'];
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Finance | DYM Global</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #334155;
        }

        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .top-bar {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
        }

        .finance-btn {
            background-color: #ebf2ff;
            border: none;
            color: #3b82f6;
            padding: 10px 20px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .finance-btn:hover {
            background-color: #dbeafe;
            color: #2563eb;
            transform: translateY(-1px);
        }

        .finance-btn-secondary {
            background-color: #f1f5f9;
            color: #64748b;
        }

        .finance-btn-secondary:hover {
            background-color: #e2e8f0;
            color: #475569;
        }

        .date-time {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #64748b;
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 500;
        }

        .date-time .material-icons {
            margin-right: 10px;
            font-size: 20px;
            color: #475569;
        }

        .card-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            color: white;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .action-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }

        .action-card .material-icons {
            font-size: 3rem;
            color: #3b82f6;
            margin-bottom: 1rem;
        }

        .action-card h4 {
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .action-card p {
            color: #64748b;
            font-size: 0.875rem;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .recent-activity {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .activity-item {
            padding: 1rem;
            border-left: 3px solid #e2e8f0;
            margin-bottom: 1rem;
            background: #f8fafc;
            border-radius: 0 8px 8px 0;
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            border-left-color: #3b82f6;
            background: #f0f9ff;
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        .activity-item .activity-time {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .activity-item .activity-text {
            color: #334155;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #fff;
            min-width: 280px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-radius: 0.5rem;
            z-index: 1;
            max-height: 400px;
            overflow-y: auto;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .notification-item {
            display: flex;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s;
            cursor: pointer;
        }

        .notification-item:hover {
            background-color: #f3f4f6;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-dot {
            height: 8px;
            width: 8px;
            background-color: #3b82f6;
            border-radius: 50%;
            margin-right: 0.5rem;
            align-self: center;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../../components/navbar_finance.php'; ?>

        <main class="flex-1 p-6">
            <!-- Top Bar -->
            <div class="top-bar p-4 mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center space-x-4">
                    <h1 class="text-xl font-bold text-gray-800">Tableau de Bord Finance</h1>
                    <div class="dropdown">
                        <button class="relative bg-white p-2 rounded-full text-gray-500 hover:text-gray-700 focus:outline-none">
                            <span class="material-icons">notifications</span>
                            <?php if ($notifications_count > 0): ?>
                                <span class="notification-badge"><?php echo $notifications_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-content">
                            <div class="py-2 px-4 bg-gray-50 border-b">
                                <h3 class="text-sm font-semibold text-gray-800">Notifications</h3>
                            </div>
                            <div id="notifications-list">
                                <!-- Les notifications seront chargées dynamiquement -->
                            </div>
                            <div class="py-2 px-4 text-center border-t">
                                <a href="#" class="text-sm font-medium text-blue-600 hover:text-blue-800">Voir toutes</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="date-time">
                    <span class="material-icons">calendar_today</span>
                    <span id="date-time-display"></span>
                </div>
            </div>

            <!-- Statistiques principales -->
            <div class="dashboard-grid mb-8">
                <div class="stat-card" onclick="filterAppelsFonds('all')">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Appels de Fonds</p>
                    <div class="mt-2 text-sm opacity-75">
                        <?php echo number_format($stats['montant_attente'] + $stats['montant_valide']); ?> FCFA
                    </div>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);" onclick="filterAppelsFonds('en_attente')">
                    <h3><?php echo $stats['en_attente']; ?></h3>
                    <p>En Attente</p>
                    <div class="mt-2 text-sm opacity-75">
                        <?php echo number_format($stats['montant_attente']); ?> FCFA
                    </div>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);" onclick="filterAppelsFonds('valide')">
                    <h3><?php echo $stats['valides']; ?></h3>
                    <p>Validés</p>
                    <div class="mt-2 text-sm opacity-75">
                        <?php echo number_format($stats['montant_valide']); ?> FCFA
                    </div>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);" onclick="filterAppelsFonds('partiellement_valide')">
                    <h3><?php echo $stats['partiels']; ?></h3>
                    <p>Partiellement Validés</p>
                    <div class="mt-2 text-sm opacity-75">
                        Traitement partiel
                    </div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="quick-actions">
                <div class="action-card" onclick="window.location.href='dashboards.php'">
                    <span class="material-icons">dashboard</span>
                    <h4>Tableau de Bord</h4>
                    <p>Vue d'ensemble</p>
                </div>

                <div class="action-card" onclick="window.location.href='gestion_appels.php'">
                    <span class="material-icons">receipt_long</span>
                    <h4>Appels de Fonds</h4>
                    <p>Gérer les demandes</p>
                </div>

                <!-- <div class="action-card" onclick="window.location.href='gestion_bons.php'">
                    <span class="material-icons">description</span>
                    <h4>Bons de Commande</h4>
                    <p>Signatures et validation</p>
                </div> -->

                <div class="action-card" onclick="window.location.href='rapports.php'">
                    <span class="material-icons">assessment</span>
                    <h4>Rapports</h4>
                    <p>Analyses financières</p>
                </div>

                <div class="action-card" onclick="window.location.href='budgets.php'">
                    <span class="material-icons">account_balance</span>
                    <h4>Gestion Budgets</h4>
                    <p>Suivi budgétaire</p>
                </div>

                <div class="action-card" onclick="window.location.href='tresorerie.php'">
                    <span class="material-icons">trending_up</span>
                    <h4>Trésorerie</h4>
                    <p>Flux de trésorerie</p>
                </div>
            </div>

            <!-- Activité récente -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="recent-activity">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <span class="material-icons mr-2">history</span>
                        Activité Récente
                    </h3>
                    <div id="recent-activities">
                        <!-- Les activités seront chargées dynamiquement -->
                        <div class="text-center py-4">
                            <span class="material-icons text-gray-400 text-3xl">history</span>
                            <p class="text-gray-500 mt-2">Chargement des activités...</p>
                        </div>
                    </div>
                </div>

                <div class="recent-activity">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <span class="material-icons mr-2">payment</span>
                        Appels de Fonds Urgents
                    </h3>
                    <div id="urgent-appels">
                        <!-- Les appels urgents seront chargés dynamiquement -->
                        <div class="text-center py-4">
                            <span class="material-icons text-gray-400 text-3xl">priority_high</span>
                            <p class="text-gray-500 mt-2">Chargement...</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            updateDateTime();
            loadNotifications();
            loadRecentActivities();
            loadUrgentAppels();

            // Mise à jour de l'heure toutes les minutes
            setInterval(updateDateTime, 60000);
        });

        function updateDateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('date-time-display').textContent = 
                now.toLocaleDateString('fr-FR', options);
        }

        function loadNotifications() {
            $.ajax({
                url: 'fonds/api_notifications.php',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        response.notifications.forEach(notif => {
                            html += `
                                <div class="notification-item" onclick="markAsRead(${notif.id})">
                                    ${!notif.lu ? '<span class="notification-dot"></span>' : '<span style="width: 8px; margin-right: 0.5rem;"></span>'}
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">${notif.titre}</p>
                                        <p class="text-xs text-gray-500">${notif.time_ago}</p>
                                    </div>
                                </div>
                            `;
                        });
                        
                        if (html === '') {
                            html = '<div class="p-4 text-center text-gray-500">Aucune notification</div>';
                        }
                        
                        $('#notifications-list').html(html);
                    }
                },
                error: function() {
                    $('#notifications-list').html('<div class="p-4 text-center text-red-500">Erreur de chargement</div>');
                }
            });
        }

        function loadRecentActivities() {
            $.ajax({
                url: 'fonds/api_activities.php',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        response.activities.forEach(activity => {
                            html += `
                                <div class="activity-item">
                                    <div class="activity-time">${activity.time_ago}</div>
                                    <div class="activity-text">${activity.description}</div>
                                </div>
                            `;
                        });
                        
                        if (html === '') {
                            html = '<div class="text-center py-4 text-gray-500">Aucune activité récente</div>';
                        }
                        
                        $('#recent-activities').html(html);
                    }
                },
                error: function() {
                    $('#recent-activities').html('<div class="text-center py-4 text-red-500">Erreur de chargement</div>');
                }
            });
        }

        function loadUrgentAppels() {
            $.ajax({
                url: 'fonds/api_urgent_appels.php',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        response.appels.forEach(appel => {
                            html += `
                                <div class="activity-item border-l-yellow-500">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="font-medium text-gray-900">${appel.code_appel}</div>
                                            <div class="text-sm text-gray-600">${appel.designation}</div>
                                            <div class="text-xs text-gray-500">${appel.time_ago}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-medium text-blue-600">${new Intl.NumberFormat('fr-FR').format(appel.montant_total)} FCFA</div>
                                            <button onclick="viewAppel('${appel.code_appel}')" 
                                                    class="text-xs text-blue-600 hover:text-blue-800">Voir</button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        if (html === '') {
                            html = '<div class="text-center py-4 text-gray-500">Aucun appel urgent</div>';
                        }
                        
                        $('#urgent-appels').html(html);
                    }
                },
                error: function() {
                    $('#urgent-appels').html('<div class="text-center py-4 text-red-500">Erreur de chargement</div>');
                }
            });
        }

        function markAsRead(notificationId) {
            $.ajax({
                url: 'fonds/api_notifications.php',
                method: 'POST',
                data: { action: 'mark_read', id: notificationId },
                success: function() {
                    loadNotifications(); // Recharger les notifications
                }
            });
        }

        function filterAppelsFonds(statut) {
            window.location.href = `./gestion_appels.php?filter=${statut}`;
        }

        function viewAppel(codeAppel) {
            window.location.href = `fonds/gestion_appels.php?view=${codeAppel}`;
        }
    </script>
</body>

</html>