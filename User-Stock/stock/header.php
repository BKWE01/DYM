<header class="flex items-center justify-between p-4 bg-white shadow-sm backdrop-blur-lg sticky top-0">
    <!-- Titre de la page (déterminé dynamiquement) -->
    <div class="flex items-center">
        <?php
        // Récupérer le nom de la page actuelle et l'afficher comme titre
        $currentPage = basename($_SERVER['PHP_SELF']);
        $pageTitle = '';

        switch ($currentPage) {
            case 'index.php':
                $pageTitle = 'Tableau de bord';
                $pageIcon = 'dashboard';
                break;
            case 'liste_produits.php':
                $pageTitle = 'Liste des produits';
                $pageIcon = 'inventory';
                break;
            case 'product_entry.php':
                $pageTitle = 'Entrée de produits';
                $pageIcon = 'add_box';
                break;
            case 'product_output.php':
                $pageTitle = 'Sortie de produits';
                $pageIcon = 'indeterminate_check_box';
                break;
            case 'inventory.php':
                $pageTitle = 'Inventaire';
                $pageIcon = 'assignment';
                break;
            case 'entrées_sorties.php':
                $pageTitle = 'Entrées et Sorties';
                $pageIcon = 'sync_alt';
                break;
            case 'add_product.php':
                $pageTitle = 'Ajouter un produit';
                $pageIcon = 'add_circle';
                break;
            case 'categories.php':
                $pageTitle = 'Catégories';
                $pageIcon = 'category';
                break;
            case 'logs.php':
                $pageTitle = 'Logs d\'activité';
                $pageIcon = 'history';
                break;
            default:
                if (strpos($currentPage, 'transfert') !== false) {
                    $pageTitle = 'Gestion des transferts';
                    $pageIcon = 'swap_horiz';
                } else {
                    $pageTitle = ucfirst(str_replace('.php', '', str_replace('_', ' ', $currentPage)));
                    $pageIcon = 'article';
                }
        }
        ?>

        <div class="flex items-center bg-gray-50 py-2 px-4 rounded-lg">
            <span class="material-icons-round text-gray-500 mr-3"><?php echo $pageIcon; ?></span>
            <h1 class="text-xl font-semibold text-gray-800"><?php echo $pageTitle; ?></h1>
        </div>

        <!-- Fil d'Ariane (Breadcrumb) simplifié -->
        <div class="hidden md:flex items-center ml-4 text-sm text-gray-500">
            <a href="<?= $base_url ?>/User-Stock/dashboard.php"
                class="hover:text-blue-600 transition-colors">Accueil</a>
            <span class="material-icons-round text-gray-400 mx-1 text-sm">chevron_right</span>
            <span class="text-gray-600 font-medium"><?php echo $pageTitle; ?></span>
        </div>
    </div>

    <!-- Éléments de droite -->
    <div class="flex items-center space-x-3">
        <!-- Date et heure -->
        <div id="currentDateTime"
            class="hidden md:block relative overflow-hidden bg-gradient-to-r from-gray-50 to-white rounded-xl p-3 border border-gray-200 shadow-inner transition-all hover:shadow">
            <div class="flex items-center">
                <span class="material-icons-round text-gray-400 mr-2">event</span>
                <div>
                    <div id="currentDate" class="text-sm font-medium text-gray-700"></div>
                    <div id="currentTime" class="text-base font-bold text-blue-600"></div>
                </div>
            </div>
        </div>

        <!-- Notifications (exemple) -->
        <div class="relative">
            <button id="notificationsButton"
                class="relative p-2 rounded-full bg-gray-50 hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition-colors">
                <span class="material-icons-round">notifications</span>
                <!-- Badge de notification -->
                <span id="notificationBadge"
                    class="absolute top-1 right-1 h-4 w-4 rounded-full bg-red-500 text-white text-xs flex items-center justify-center">
                    3
                </span>
            </button>

            <!-- Dropdown des notifications (caché par défaut) -->
            <div id="notificationsDropdown"
                class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-10 overflow-hidden">
                <div class="p-3 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Notifications</h3>
                    <button id="markAllReadBtn" class="text-xs text-blue-600 hover:text-blue-800">Tout marquer comme
                        lu</button>
                </div>
                <div id="notificationsList" class="max-h-64 overflow-y-auto">
                    <div class="p-3 border-b border-gray-50 hover:bg-gray-50 transition-colors">
                        <div class="flex">
                            <span class="material-icons-round text-red-500 mr-3">error_outline</span>
                            <div class="flex-1">
                                <p class="text-sm font-medium">Stock faible pour "Câble RJ45 Cat6"</p>
                                <p class="text-xs text-gray-500">Il reste 3 unités en stock</p>
                                <p class="text-xs text-gray-400 mt-1">Il y a 25 minutes</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 border-b border-gray-50 hover:bg-gray-50 transition-colors">
                        <div class="flex">
                            <span class="material-icons-round text-green-500 mr-3">add_circle</span>
                            <div class="flex-1">
                                <p class="text-sm font-medium">Entrée de produits enregistrée</p>
                                <p class="text-xs text-gray-500">10 nouveaux articles ajoutés</p>
                                <p class="text-xs text-gray-400 mt-1">Il y a 1 heure</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 hover:bg-gray-50 transition-colors">
                        <div class="flex">
                            <span class="material-icons-round text-blue-500 mr-3">swap_horiz</span>
                            <div class="flex-1">
                                <p class="text-sm font-medium">Transfert approuvé</p>
                                <p class="text-xs text-gray-500">Le transfert #TR-2023-045 a été approuvé</p>
                                <p class="text-xs text-gray-400 mt-1">Il y a 3 heures</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-2 border-t border-gray-100 bg-gray-50 text-center">
                    <a href="#" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Voir toutes les
                        notifications</a>
                </div>
            </div>
        </div>

        <!-- Profil utilisateur -->
        <div class="relative">
            <button id="userProfileButton"
                class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                <div
                    class="w-9 h-9 rounded-full bg-gradient-to-r from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold shadow">
                    <?php
                    // Initiales de l'utilisateur (à adapter selon votre système d'authentification)
                    $userInitials = isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'U';
                    echo $userInitials;
                    ?>
                </div>
                <div class="hidden md:block text-left">
                    <p class="text-sm font-medium text-gray-700 leading-tight">
                        <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Utilisateur'; ?>
                    </p>
                    <p class="text-xs text-gray-500 leading-tight">
                        <?php
                        $roleDisplay = '';
                        if (isset($_SESSION['user_role'])) {
                            switch ($_SESSION['user_role']) {
                                case 'super_admin':
                                    $roleDisplay = 'Administrateur';
                                    break;
                                case 'admin':
                                    $roleDisplay = 'Gestionnaire';
                                    break;
                                default:
                                    $roleDisplay = 'Utilisateur';
                            }
                        }
                        echo $roleDisplay;
                        ?>
                    </p>
                </div>
                <span class="material-icons-round text-gray-400 hidden md:block">expand_more</span>
            </button>

            <!-- Dropdown du profil (caché par défaut) -->
            <div id="userProfileDropdown"
                class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10 overflow-hidden">
                <div class="p-3 border-b border-gray-100">
                    <p class="font-medium text-gray-800">
                        <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Utilisateur'; ?></p>
                    <p class="text-xs text-gray-500">
                        <?php echo isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'user@example.com'; ?></p>
                </div>
                <div class="py-1">
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                        <span class="material-icons-round text-gray-400 mr-2 align-middle text-sm">account_circle</span>
                        Mon profil
                    </a>
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                        <span class="material-icons-round text-gray-400 mr-2 align-middle text-sm">settings</span>
                        Paramètres
                    </a>
                </div>
                <div class="py-1 border-t border-gray-100">
                    <a href="<?= $base_url ?>/logout.php"
                        class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <span class="material-icons-round text-red-500 mr-2 align-middle text-sm">logout</span>
                        Déconnexion
                    </a>
                </div>
            </div>
        </div>

        <!-- Bouton d'actualisation -->
        <button id="refreshButton"
            class="p-2 rounded-full bg-gray-50 hover:bg-blue-50 text-gray-600 hover:text-blue-600 transition-colors">
            <span class="material-icons-round text-xl">refresh</span>
        </button>
    </div>
</header>

<script>
    // Affichage de la date et de l'heure actuelles
    function updateDateTime() {
        const now = new Date();
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };

        document.getElementById('currentDate').textContent = now.toLocaleDateString('fr-FR', dateOptions);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('fr-FR', timeOptions);
    }
    updateDateTime();
    setInterval(updateDateTime, 1000); // Mise à jour toutes les secondes

    // Code pour le bouton de rafraîchissement
    document.getElementById('refreshButton').addEventListener('click', () => {
        // Animation de rotation pendant le rechargement
        const refreshIcon = document.querySelector('#refreshButton .material-icons-round');
        refreshIcon.style.transition = 'transform 0.5s ease-in-out';
        refreshIcon.style.transform = 'rotate(360deg)';

        setTimeout(() => {
            location.reload();
        }, 300);
    });

    // Gestion du dropdown des notifications
    const notificationsButton = document.getElementById('notificationsButton');
    const notificationsDropdown = document.getElementById('notificationsDropdown');

    notificationsButton.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationsDropdown.classList.toggle('hidden');
        userProfileDropdown.classList.add('hidden'); // Fermer l'autre dropdown s'il est ouvert
    });

    // Gestion du dropdown de profil
    const userProfileButton = document.getElementById('userProfileButton');
    const userProfileDropdown = document.getElementById('userProfileDropdown');

    userProfileButton.addEventListener('click', (e) => {
        e.stopPropagation();
        userProfileDropdown.classList.toggle('hidden');
        notificationsDropdown.classList.add('hidden'); // Fermer l'autre dropdown s'il est ouvert
    });

    // Fermer les dropdowns quand on clique ailleurs
    document.addEventListener('click', (e) => {
        if (!notificationsButton.contains(e.target) && !notificationsDropdown.contains(e.target)) {
            notificationsDropdown.classList.add('hidden');
        }

        if (!userProfileButton.contains(e.target) && !userProfileDropdown.contains(e.target)) {
            userProfileDropdown.classList.add('hidden');
        }
    });

    // Marquer toutes les notifications comme lues
    document.getElementById('markAllReadBtn').addEventListener('click', (e) => {
        e.stopPropagation();
        document.getElementById('notificationBadge').textContent = '0';
        // Vous pourriez ajouter une requête AJAX ici pour mettre à jour le statut des notifications
    });
</script>