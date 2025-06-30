<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '../../components/config.php';
$base_url = PROJECT_ROOT;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        /* Police personnalisée pour toute l'application */
        body,
        #sidebar,
        #main-content,
        header {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        /* Styles généraux */
        body {
            overflow: hidden;
            background-color: #f8f9fa;
        }

        /* Styles du logo */
        .logo-container {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
            margin: 16px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .logo-text {
            font-weight: 700;
            background: linear-gradient(90deg, #ffffff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        /* Styles de la barre latérale */
        #sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background: #1e293b;
            /* Couleur dark moderne */
            color: #94a3b8;
            /* Texte gris clair */
            z-index: 40;
        }

        #sidebar::-webkit-scrollbar {
            width: 4px;
        }

        #sidebar::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        /* Styles des éléments de navigation */
        .nav-item {
            margin: 4px 12px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .nav-item:hover:not(.active-nav-item) {
            background-color: rgba(255, 255, 255, 0.08);
            transform: translateX(4px);
        }

        .active-nav-item {
            background: linear-gradient(90deg, #3b82f6, #4f46e5);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.25);
            color: white;
            transform: translateX(4px);
        }

        .nav-item .material-icons-round {
            font-size: 20px;
            transition: transform 0.2s ease;
        }

        .nav-item:hover .material-icons-round {
            transform: scale(1.1);
        }

        /* Séparateur entre les groupes d'éléments */
        .nav-divider {
            margin: 16px 20px;
            height: 1px;
            background: linear-gradient(90deg, rgba(148, 163, 184, 0.05), rgba(148, 163, 184, 0.2), rgba(148, 163, 184, 0.05));
        }

        /* Badge de notification */
        .notification-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            height: 16px;
            width: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            background: linear-gradient(45deg, #ef4444, #f97316);
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
        }

        /* Conteneur principal */
        #main-content {
            margin-left: 14rem;
            height: 100vh;
            overflow-y: auto;
            transition: margin-left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        /* En-tête fixe */
        header {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(10px);
        }

        /* Bouton toggle de la sidebar */
        #toggleSidebar {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            height: 32px;
            width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        #toggleSidebar:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-50%) scale(1.1);
        }

        /* Menu mobile */
        @media (max-width: 768px) {
            #sidebar {
                transform: translateX(-100%);
                width: 260px !important;
            }

            #sidebar.open {
                transform: translateX(0);
            }

            #mobile-menu-button {
                display: flex;
                position: fixed;
                top: 16px;
                left: 16px;
                z-index: 60;
                background: #3b82f6;
                color: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
                transition: all 0.2s ease;
            }

            #mobile-menu-button:hover {
                background: #2563eb;
                transform: scale(1.05);
            }

            #main-content {
                margin-left: 0;
                padding-top: 64px;
            }
        }

        @media (min-width: 769px) {
            #mobile-menu-button {
                display: none;
            }
        }

        /* Animations */
        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }
    </style>
</head>

<body>
    <!-- Bouton du menu mobile -->
    <button id="mobile-menu-button" class="lg:hidden p-2 rounded-md flex items-center justify-center">
        <span class="material-icons-round">menu</span>
    </button>

    <!-- Sidebar moderne -->
    <div id="sidebar" class="w-56 transition-all duration-300 flex flex-col">
        <!-- Logo et nom de l'application -->
        <div class="logo-container p-4 flex items-center justify-center">
            <div class="flex flex-col items-center">
                <div class="flex items-center justify-center">
                    <span class="material-icons-round text-white text-2xl mr-2">inventory_2</span>
                    <span class="logo-text text-xl">DYM STOCK</span>
                </div>
                <div class="text-xs text-gray-300 mt-1 font-light">Gestion de stock</div>
            </div>
        </div>

        <!-- Navigation principale -->
        <nav class="flex-grow mt-4">
            <div class="px-4 py-2 text-xs uppercase tracking-wider text-gray-400 font-semibold">Navigation</div>
            <ul>
                <?php
                // Récupérer le chemin complet de la page actuelle
                $current_page = $_SERVER['PHP_SELF'];
                $current_directory = dirname($current_page);

                $nav_items = [
                    $base_url . '/User-Stock/stock/index.php' => ['icon' => 'dashboard', 'text' => 'Tableau de bord'],
                    $base_url . '/User-Stock/stock/liste_produits.php' => ['icon' => 'inventory', 'text' => 'Liste des produits'],
                    $base_url . '/User-Stock/stock/product_entry.php' => ['icon' => 'add_box', 'text' => 'Entrée de produit'],
                    $base_url . '/User-Stock/stock/product_output.php' => ['icon' => 'indeterminate_check_box', 'text' => 'Sortie de produit'],
                    $base_url . '/User-Stock/stock/reservations/reservations_details.php' => ['icon' => 'bookmark', 'text' => 'Réservations', 'is_section' => true],
                ];

                // Afficher les éléments de navigation principal
                foreach ($nav_items as $page => $item) {
                    $page_path = parse_url($page, PHP_URL_PATH);
                    $is_active = '';

                    // Vérification plus précise qui tient compte du chemin complet
                    if ($current_page === $page_path) {
                        $is_active = 'active-nav-item';
                    }
                    // Vérifier si on est dans un sous-dossier de cette section (avec chemin exact)
                    elseif (
                        isset($item['is_section']) && $item['is_section'] &&
                        (strpos($current_directory . '/', dirname($page_path) . '/') === 0 ||
                            $current_directory === dirname($page_path))
                    ) {
                        $is_active = 'active-nav-item';
                    }

                    echo "<li class='nav-item {$is_active} py-2 px-3'>";
                    echo "<a href='{$page}' class='flex items-center w-full text-sm'>";
                    echo "<span class='material-icons-round w-6 text-center'>{$item['icon']}</span>";
                    echo "<span class='ml-3 font-medium'>{$item['text']}</span>";
                    echo "</a>";
                    echo "</li>";
                }
                ?>
            </ul>

            <!-- Séparateur -->
            <div class="nav-divider"></div>

            <!-- Gestion -->
            <div class="px-4 py-2 text-xs uppercase tracking-wider text-gray-400 font-semibold">Gestion</div>
            <ul>
                <?php
                $management_items = [
                    $base_url . '/User-Stock/stock/transfert/transfert_manager.php' => ['icon' => 'swap_horiz', 'text' => 'Transferts', 'notif' => false, 'is_section' => true],
                    //$base_url . '/User-Stock/stock/views/returns/index.php' => ['icon' => 'assignment_return', 'text' => 'Retours en stock', 'notif' => false, 'is_section' => true],
                    $base_url . '/User-Stock/stock/inventory.php' => ['icon' => 'assignment', 'text' => 'Inventaire', 'notif' => false],
                    $base_url . '/User-Stock/stock/entrées_sorties.php' => ['icon' => 'sync_alt', 'text' => 'Entrées/Sorties', 'notif' => false],
                    $base_url . '/User-Stock/stock/views/commandes/en_cours/index.php' => ['icon' => 'shopping_cart', 'text' => 'Commandes en cours', 'notif' => false, 'is_section' => true],
                    $base_url . '/User-Stock/stock/views/commandes/annulees/index.php' => ['icon' => 'cancel', 'text' => 'Commandes annulées', 'notif' => false, 'is_section' => true],
                ];

                // Afficher les éléments de gestion
                foreach ($management_items as $page => $item) {
                    $page_path = parse_url($page, PHP_URL_PATH);
                    $is_active = '';

                    // Vérification plus précise qui tient compte du chemin complet
                    if ($current_page === $page_path) {
                        $is_active = 'active-nav-item';
                    }
                    // Vérifier si on est dans un sous-dossier de cette section (avec chemin exact)
                    elseif (
                        isset($item['is_section']) && $item['is_section'] &&
                        (strpos($current_directory . '/', dirname($page_path) . '/') === 0 ||
                            $current_directory === dirname($page_path))
                    ) {
                        $is_active = 'active-nav-item';
                    }
                    // Cas spécial pour les retours en stock
                    elseif (strpos($current_directory, '/returns') !== false && strpos($page_path, '/returns/') !== false) {
                        $is_active = 'active-nav-item';
                    }

                    echo "<li class='nav-item {$is_active} py-2 px-3'>";
                    echo "<a href='{$page}' class='flex items-center w-full text-sm'>";
                    echo "<div class='relative'>";
                    echo "<span class='material-icons-round w-6 text-center'>{$item['icon']}</span>";
                    if ($item['notif']) {
                        echo "<span class='notification-badge'>!</span>";
                    }
                    echo "</div>";
                    echo "<span class='ml-3 font-medium'>{$item['text']}</span>";
                    echo "</a>";
                    echo "</li>";
                }
                ?>
            </ul>

            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin'): ?>
                <!-- Séparateur pour l'administration -->
                <div class="nav-divider"></div>

                <!-- Administration (uniquement pour les administrateurs) -->
                <div class="px-4 py-2 text-xs uppercase tracking-wider text-gray-400 font-semibold">Administration</div>
                <ul>
                    <?php
                    $admin_items = [
                        $base_url . '/User-Stock/stock/add_product.php' => ['icon' => 'add_circle', 'text' => 'Ajouter un produit'],
                        $base_url . '/User-Stock/stock/categories.php' => ['icon' => 'category', 'text' => 'Catégories'],
                        $base_url . '/User-Stock/stock/admin/logs.php' => ['icon' => 'history', 'text' => 'Logs d\'activité', 'is_section' => true],
                    ];

                    // Afficher les éléments d'administration
                    foreach ($admin_items as $page => $item) {
                        $page_path = parse_url($page, PHP_URL_PATH);
                        $is_active = '';

                        // Vérification plus précise qui tient compte du chemin complet
                        if ($current_page === $page_path) {
                            $is_active = 'active-nav-item';
                        }
                        // Vérifier si on est dans un sous-dossier de cette section (avec chemin exact)
                        elseif (
                            isset($item['is_section']) && $item['is_section'] &&
                            (strpos($current_directory . '/', dirname($page_path) . '/') === 0 ||
                                $current_directory === dirname($page_path))
                        ) {
                            $is_active = 'active-nav-item';
                        }

                        echo "<li class='nav-item {$is_active} py-2 px-3'>";
                        echo "<a href='{$page}' class='flex items-center w-full text-sm'>";
                        echo "<span class='material-icons-round w-6 text-center'>{$item['icon']}</span>";
                        echo "<span class='ml-3 font-medium'>{$item['text']}</span>";
                        echo "</a>";
                        echo "</li>";
                    }
                    ?>
                </ul>
            <?php endif; ?>
        </nav>

        <!-- Bouton de basculement de la sidebar -->
        <div class="pb-8 flex items-center justify-center relative">
            <button id="toggleSidebar" class="hover:bg-blue-500/20 transition-colors duration-200">
                <span id="toggleIcon" class="material-icons-round text-white">chevron_left</span>
            </button>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const toggleSidebar = document.getElementById('toggleSidebar');
        const toggleIcon = document.getElementById('toggleIcon');
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mainContent = document.getElementById('main-content');

        // Fonction pour réduire la sidebar
        function collapseSidebar() {
            sidebar.classList.replace('w-56', 'w-16');
            toggleIcon.textContent = 'chevron_right';
            // Masquer tous les textes sauf les icônes
            document.querySelectorAll('#sidebar .logo-text, #sidebar nav span:not(.material-icons-round), #sidebar .text-xs').forEach(el => {
                el.classList.add('hidden');
            });
            // Réduire le logo
            document.querySelector('.logo-container').classList.add('p-2');
            document.querySelector('.logo-container').classList.remove('p-4');

            // Centrer les icônes
            document.querySelectorAll('#sidebar nav .material-icons-round').forEach(icon => {
                icon.parentElement.classList.remove('w-6');
                icon.parentElement.classList.add('w-full', 'flex', 'justify-center');
            });

            // Masquer les titres de section
            document.querySelectorAll('#sidebar .uppercase').forEach(el => {
                el.classList.add('hidden');
            });

            // Ajuster les marges des éléments de navigation
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('px-3');
                item.classList.add('px-2', 'flex', 'justify-center');
            });

            // Cacher les séparateurs
            document.querySelectorAll('.nav-divider').forEach(div => {
                div.classList.add('hidden');
            });

            if (window.innerWidth > 768) {
                mainContent.style.marginLeft = '4rem';
            }
        }

        // Fonction pour étendre la sidebar
        function expandSidebar() {
            sidebar.classList.replace('w-16', 'w-56');
            toggleIcon.textContent = 'chevron_left';
            // Afficher tous les textes
            document.querySelectorAll('#sidebar .logo-text, #sidebar nav span:not(.material-icons-round), #sidebar .text-xs').forEach(el => {
                el.classList.remove('hidden');
            });
            // Restaurer le logo
            document.querySelector('.logo-container').classList.remove('p-2');
            document.querySelector('.logo-container').classList.add('p-4');

            // Restaurer les icônes
            document.querySelectorAll('#sidebar nav .material-icons-round').forEach(icon => {
                icon.parentElement.classList.add('w-6');
                icon.parentElement.classList.remove('w-full', 'flex', 'justify-center');
            });

            // Afficher les titres de section
            document.querySelectorAll('#sidebar .uppercase').forEach(el => {
                el.classList.remove('hidden');
            });

            // Restaurer les marges des éléments de navigation
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.add('px-3');
                item.classList.remove('px-2', 'flex', 'justify-center');
            });

            // Afficher les séparateurs
            document.querySelectorAll('.nav-divider').forEach(div => {
                div.classList.remove('hidden');
            });

            if (window.innerWidth > 768) {
                mainContent.style.marginLeft = '14rem';
            }
        }

        // Gestionnaire d'événement pour le bouton de basculement
        toggleSidebar.addEventListener('click', () => {
            if (sidebar.classList.contains('w-56')) {
                collapseSidebar();
                localStorage.setItem('sidebarState', 'collapsed');
            } else {
                expandSidebar();
                localStorage.setItem('sidebarState', 'expanded');
            }
        });

        // Gestionnaire d'événement pour le bouton de menu mobile
        mobileMenuButton.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        // Fermer le menu mobile lorsqu'un lien est cliqué
        document.querySelectorAll('#sidebar nav ul li a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('open');
                }
            });
        });

        // Ajuster le comportement en fonction de la taille de l'écran
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
                mainContent.style.marginLeft = sidebar.classList.contains('w-16') ? '4rem' : '14rem';
            } else {
                mainContent.style.marginLeft = '0';
            }
        });

        // Restaurer l'état de la sidebar depuis le localStorage
        document.addEventListener('DOMContentLoaded', () => {
            const sidebarState = localStorage.getItem('sidebarState');
            if (sidebarState === 'collapsed') {
                collapseSidebar();
            }

            // Permettre le clic sur les éléments du menu pour les activer
            document.querySelectorAll('#sidebar nav ul li').forEach(item => {
                item.addEventListener('click', (e) => {
                    if (!e.target.closest('a')) { // S'assurer qu'on a pas cliqué sur le lien lui-même
                        const link = item.querySelector('a');
                        if (link) link.click();
                    }
                });
            });
        });
    </script>
</body>

</html>