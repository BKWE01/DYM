<?php
// S'assurer que la session est démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // Corriger le chemin d'inclusion
    include_once dirname(__DIR__) . '/database/connection.php';
    
    $stmt = $pdo->prepare("SELECT name, profile_image FROM users_exp WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $user = null;
}

// Déterminez la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);

require_once dirname(__DIR__) . '/components/config.php';
$base_url = PROJECT_ROOT;
?>

<nav class="bg-gray-800">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <img class="h-8 w-15" src="<?= $base_url ?>/public/logo.png" alt="Your Company">
                </div>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        
                        <a href="<?= $base_url ?>/User-Stock/dashboard.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'dashboard.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>"
                            aria-current="page">Dashboard</a>

                        <a href="<?= $base_url ?>/User-Stock/expression_systeme.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'expression_systeme.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Expression
                            de besoin Système</a>

                        <a href="<?= $base_url ?>/User-Stock/stock/index.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'index.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Gestion
                            de stock</a>
                            
                        <a href="<?= $base_url ?>/User-Stock/profile.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'profile.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Profile</a>
                    </div>
                </div>
            </div>
            <div class="hidden md:block">
                <div class="ml-4 flex items-center md:ml-6">
                    <button type="button"
                        class="relative rounded-full bg-gray-800 p-1 text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800">
                        <span class="absolute -inset-1.5"></span>
                        <span class="sr-only">View notifications</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                    </button>

                    <!-- Profile dropdown -->
                    <div class="relative ml-3">
                        <div>
                            <button type="button"
                                class="relative flex max-w-xs items-center rounded-full bg-gray-800 text-sm focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800"
                                id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                <span class="absolute -inset-1.5"></span>
                                <span class="sr-only">Open user menu</span>
                                <?php if ($user && isset($user['profile_image']) && $user['profile_image']): ?>
                                    <img class="h-8 w-8 rounded-full"
                                        src="./../uploads/<?php echo htmlspecialchars($user['profile_image']); ?>"
                                        alt="Profile Image">
                                <?php else: ?>
                                    <img class="h-8 w-8 rounded-full" src="../uploads/default-profile-image.png"
                                        alt="Profile Image">
                                <?php endif; ?>
                            </button>
                        </div>
                        <div class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none profile-menu hidden"
                            role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                            <a href="<?= $base_url ?>/User-Stock/profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                role="menuitem" tabindex="-1">Profil</a>
                            <a href="<?= $base_url ?>/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                role="menuitem" tabindex="-1">Déconnexion</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="-mr-2 flex md:hidden">
                <button type="button"
                    class="relative inline-flex items-center justify-center rounded-md bg-gray-800 p-2 text-gray-400 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800"
                    aria-controls="mobile-menu" aria-expanded="false">
                    <span class="absolute -inset-0.5"></span>
                    <span class="sr-only">Open main menu</span>
                    <svg class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div class="md:hidden" id="mobile-menu">
        <div class="space-y-1 px-2 pb-3 pt-2 sm:px-3">
            <a href="<?= $base_url ?>/User-Stock/dashboard.php"
                class="block rounded-md <?php echo ($current_page == 'dashboard.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>"
                aria-current="page">Dashboard</a>

            <a href="<?= $base_url ?>/User-Stock/expression_systeme.php"
                class="block rounded-md <?php echo ($current_page == 'expression_systeme.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Expression
                de besoin système</a>

            <a href="<?= $base_url ?>/User-Stock/stock/index.php" 
                class="block rounded-md <?php echo ($current_page == 'index.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Gestion
                de stock</a>
                
            <a href="<?= $base_url ?>/User-Stock/profile.php"
                class="block rounded-md <?php echo ($current_page == 'profile.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Profile</a>
        </div>
        <div class="border-t border-gray-700 pb-3 pt-4">
            <div class="flex items-center px-5">
                <div class="flex-shrink-0">
                    <?php if ($user && isset($user['profile_image']) && $user['profile_image']): ?>
                        <img class="h-10 w-10 rounded-full"
                            src="../uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Image">
                    <?php else: ?>
                        <img class="h-10 w-10 rounded-full" src="../uploads/default-profile-image.png" alt="Profile Image">
                    <?php endif; ?>
                </div>
                <div class="ml-3">
                    <?php if ($user && isset($user['name'])): ?>
                        <div class="text-base font-medium text-white"><?php echo htmlspecialchars($user['name']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-3 space-y-1 px-2">
                <a href="<?= $base_url ?>/User-Stock/profile.php"
                    class="block rounded-md py-2 px-3 text-base font-medium text-gray-300 hover:bg-gray-700 hover:text-white">Profil</a>
                <a href="<?= $base_url ?>/logout.php"
                    class="block rounded-md py-2 px-3 text-base font-medium text-gray-300 hover:bg-gray-700 hover:text-white">Déconnexion</a>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const profileMenuButton = document.getElementById('user-menu-button');
        const profileMenu = document.querySelector('.profile-menu');

        profileMenuButton.addEventListener('click', function () {
            profileMenu.classList.toggle('hidden');
        });

        document.addEventListener('click', function (event) {
            if (!profileMenuButton.contains(event.target) && !profileMenu.contains(event.target)) {
                profileMenu.classList.add('hidden');
            }
        });
    });
</script>