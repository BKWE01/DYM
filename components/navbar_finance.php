<?php
// S'assurer que la session est démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupérer le nom d'utilisateur depuis la session pour l'afficher dans le menu
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Utilisateur';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';

// Récupérer la photo de profil si elle existe
$profile_image = 'default-profile.png'; // Image par défaut

if (!empty($user_id)) {
    try {
        // Inclusion sécurisée du fichier de connexion
        $connection_file = __DIR__ . '/../database/connection.php';
        if (file_exists($connection_file)) {
            include_once $connection_file;
            
            // Vérifier que $pdo est bien défini
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->prepare("SELECT profile_image FROM users_exp WHERE id = :id");
                $stmt->bindParam(':id', $user_id);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result && !empty($result['profile_image'])) {
                    $profile_image = $result['profile_image'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erreur navbar_finance: " . $e->getMessage());
        // Garder l'image par défaut en cas d'erreur
    }
}

// Obtenir le chemin complet relatif pour identifier la page active
$current_path = $_SERVER['PHP_SELF'];
$current_page = basename($_SERVER['PHP_SELF']);

// Fonction pour vérifier si un lien est actif
function isActiveLink($link_path, $current_path)
{
    // Normaliser les chemins pour la comparaison
    $link_path = str_replace('\\', '/', $link_path);
    $current_path = str_replace('\\', '/', $current_path);

    // Vérifier si le chemin correspond
    return strpos($current_path, $link_path) !== false;
}

// Configuration du base_url - VERSION CORRIGÉE
$base_url = ''; // Valeur par défaut

// Essayer de charger la configuration personnalisée
$config_file = __DIR__ . '/config.php';
if (file_exists($config_file)) {
    require_once $config_file;
    if (defined('PROJECT_ROOT') && !empty(PROJECT_ROOT)) {
        $base_url = PROJECT_ROOT;
    }
} else {
    // Le fallback est déjà défini ci-dessus
    error_log("Fichier config.php non trouvé, utilisation du fallback: " . $base_url);
}
?>

<nav class="bg-black shadow-md position-relative z-2">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="<?= $base_url ?>/User-Finance/dashboard.php">
                        <img class="h-10 w-auto" src="<?= $base_url ?>/public/logo.png" alt="DYM Logo">
                    </a>
                </div>

                <!-- Navigation principale desktop -->
                <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                    <a href="<?= $base_url ?>/User-Finance/dashboard.php"
                        class="<?php echo ($current_page == 'dashboard.php' && strpos($current_path, 'User-Finance') !== false) ? 'border-blue-400 text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-gray-500'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Dashboard
                    </a>

                    <a href="<?= $base_url ?>/User-Finance/views/expressions/index.php"
                        class="<?php echo isActiveLink('/User-Finance/views/expressions/', $current_path) ? 'border-blue-400 text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-gray-500'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Expression de besoin
                    </a>

                    <a href="<?= $base_url ?>/User-Finance/views/besoins/index.php"
                        class="<?php echo isActiveLink('/User-Finance/views/besoins/', $current_path) ? 'border-blue-400 text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-gray-500'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Tous les besoins système
                    </a>

                    <a href="<?= $base_url ?>/User-Finance/consulter_stock.php"
                        class="<?php echo ($current_page == 'consulter_stock.php' && strpos($current_path, 'User-Finance') !== false) ? 'border-blue-400 text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-gray-500'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Niveau des stocks
                    </a>

                    <a href="<?= $base_url ?>/User-Finance/statistics/index.php"
                        class="<?php echo ($current_page == 'index.php' && strpos($current_path, 'User-Finance') !== false) ? 'border-blue-400 text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-gray-500'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Statistiques des achats
                    </a>

                    <a href="<?= $base_url ?>/User-Finance/fonds/dashboards.php"
                        class="<?php echo ($current_page == '/User-Finance/fonds/dashboards.php' && strpos($current_path, 'User-Finance') !== false) ? 'border-blue-400 text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-gray-500'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Appel de fonds
                    </a>
                </div>
            </div>

            <!-- Menu droit (profil) desktop -->
            <div class="hidden sm:ml-6 sm:flex sm:items-center">
                <div class="ml-3 relative">
                    <div>
                        <button type="button"
                            class="flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                            <span class="sr-only">Ouvrir le menu utilisateur</span>
                            <img class="h-8 w-8 rounded-full object-cover"
                                src="<?= $base_url ?>/uploads/<?php echo htmlspecialchars($profile_image); ?>"
                                alt="Photo de profil">
                        </button>
                    </div>

                    <!-- Menu déroulant du profil - caché par défaut -->
                    <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none"
                        role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1"
                        id="user-menu">
                        <span
                            class="block px-4 py-2 text-sm text-gray-700 font-medium border-b"><?php echo htmlspecialchars($user_name); ?></span>
                        <a href="<?= $base_url ?>/User-Finance/profile.php"
                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Profil</a>
                        <a href="<?= $base_url ?>/logout.php"
                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            role="menuitem">Déconnexion</a>
                    </div>
                </div>
            </div>

            <!-- Bouton menu mobile -->
            <div class="-mr-2 flex items-center sm:hidden">
                <button id="mobile-menu-toggle" type="button"
                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                    <span class="sr-only">Ouvrir le menu principal</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Menu mobile full-screen -->
        <div id="mobile-menu" class="fixed inset-0 bg-black z-50 hidden overflow-y-auto">
            <div class="pt-2 pb-3 space-y-1">
                <div class="flex justify-between items-center px-4 py-2 border-b border-gray-700">
                    <img class="h-8 w-auto" src="<?= $base_url ?>/public/logo.png" alt="Logo">
                    <button id="mobile-menu-close" class="text-gray-400 hover:text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Profil mobile -->
                <div class="px-4 py-4 border-b border-gray-700 flex items-center">
                    <img class="h-10 w-10 rounded-full object-cover mr-3"
                        src="<?= $base_url ?>/uploads/<?php echo htmlspecialchars($profile_image); ?>"
                        alt="Photo de profil">
                    <div>
                        <p class="text-white font-medium"><?php echo htmlspecialchars($user_name); ?></p>
                    </div>
                </div>

                <!-- Menu items -->
                <a href="<?= $base_url ?>/User-Finance/dashboard.php"
                    class="block px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white <?php echo ($current_page == 'dashboard.php' && strpos($current_path, 'User-Finance') !== false) ? 'bg-gray-800 text-white' : ''; ?>">
                    Dashboard
                </a>

                <a href="<?= $base_url ?>/User-Finance/views/expressions/index.php"
                    class="block px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white <?php echo isActiveLink('/User-Finance/views/expressions/', $current_path) ? 'bg-gray-800 text-white' : ''; ?>">
                    Expressions de besoins
                </a>

                <a href="<?= $base_url ?>/User-Finance/views/besoins/index.php"
                    class="block px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white <?php echo isActiveLink('/User-Finance/views/besoins/', $current_path) ? 'bg-gray-800 text-white' : ''; ?>">
                    Tous les besoins système
                </a>

                <a href="<?= $base_url ?>/User-Finance/consulter_stock.php"
                    class="block px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white <?php echo ($current_page == 'consulter_stock.php' && strpos($current_path, 'User-Finance') !== false) ? 'bg-gray-800 text-white' : ''; ?>">
                    Niveau des stocks
                </a>

                <a href="<?= $base_url ?>/User-Finance/statistics/index.php"
                    class="block px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white <?php echo ($current_page == 'index.php' && strpos($current_path, 'User-Finance') !== false) ? 'bg-gray-800 text-white' : ''; ?>">
                    Statistiques des achats
                </a>

                <a href="<?= $base_url ?>/User-Finance/fonds/dashboards.php"
                    class="block px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white <?php echo ($current_page == 'dashboards.php' && strpos($current_path, 'User-Finance') !== false) ? 'bg-gray-800 text-white' : ''; ?>">
                    Appel de fonds
                </a>

                <!-- Section actions -->
                <div class="border-t border-gray-700 pt-4">
                    <a href="<?= $base_url ?>/User-Finance/profile.php"
                        class="block px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white">
                        Profil
                    </a>
                    <a href="<?= $base_url ?>/logout.php"
                        class="block px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white">
                        Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuClose = document.getElementById('mobile-menu-close');
        const userMenuButton = document.getElementById('user-menu-button');
        const userMenu = document.getElementById('user-menu');

        // Menu mobile
        if (mobileMenuToggle && mobileMenu && mobileMenuClose) {
            mobileMenuToggle.addEventListener('click', function () {
                mobileMenu.classList.remove('hidden');
            });

            mobileMenuClose.addEventListener('click', function () {
                mobileMenu.classList.add('hidden');
            });
        }

        // Menu profil desktop
        if (userMenuButton && userMenu) {
            userMenuButton.addEventListener('click', function () {
                userMenu.classList.toggle('hidden');
            });

            document.addEventListener('click', function (event) {
                // Fermeture menu profil
                if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                    userMenu.classList.add('hidden');
                }
            });
        }
    });
</script>