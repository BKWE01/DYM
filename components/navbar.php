<?php
$rootPath = dirname(__DIR__); // Remonte 3 niveaux depuis notifications/be/
include_once $rootPath . '/database/connection.php'; // Incluez votre fichier de connexion à la base de données

// Chargement des notifications pour BE
include_once 'notifications_be.php';
/*var_dump('ok');
exit;*/

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT name, profile_image FROM users_exp WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $user = null;
}

require_once 'config.php';
$base_url = PROJECT_ROOT;

// Déterminez la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);
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
                        <a href="<?= $base_url ?>/User-BE/dashboard.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'dashboard.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>"
                            aria-current="page">Dashboard</a>
                        <a href="<?= $base_url ?>/User-BE/expression.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'expression.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Expression
                            de besoin Projet</a>

                        <a href="<?= $base_url ?>/User-BE/views/besoins/index.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'index.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Expression
                            de besoin Système</a>

                        <a href="<?= $base_url ?>/User-BE/consulter_stock.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'consulter_stock.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Voir
                            stock</a>

                        <a href="<?= $base_url ?>/User-BE/liste_elements.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'liste_elements.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Liste
                            éléments</a>
                        <a href="<?= $base_url ?>/User-BE/project_return.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'project_return.php') ? 'bg-gray-900 text-white' : 'text-indigo-400 hover:bg-gray-700 hover:text-white'; ?>"
                            style="<?php echo ($current_page != 'project_return.php') ? 'background-color: #5959ec;' : ''; ?>">Retour
                            produits non utilisés</a>
                        <a href="<?= $base_url ?>/User-BE/profile.php"
                            class="rounded-md px-3 py-2 text-sm font-medium <?php echo ($current_page == 'profile.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Profile</a>
                    </div>
                </div>
            </div>
            <div class="hidden md:block">
                <div class="ml-4 flex items-center md:ml-6">
                    <div class="relative">
                        <button type="button" id="notifications-toggle"
                            class="relative rounded-full bg-gray-800 p-1 text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800">
                            <span class="absolute -inset-1.5"></span>
                            <span class="sr-only">View notifications</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            <?php if ($notifications['counts']['total'] > 0): ?>
                                <span class="absolute top-0 right-0 -mt-1 -mr-1 flex items-center justify-center">
                                    <?php if ($notifications['counts']['received'] > 0 || $notifications['counts']['canceled'] > 0): ?>
                                        <!-- Badge rouge pour les notifications importantes -->
                                        <span
                                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                        <span
                                            class="relative inline-flex rounded-full h-5 w-5 bg-red-500 text-white text-xs flex items-center justify-center">
                                            <?php echo $notifications['counts']['total']; ?>
                                        </span>
                                    <?php else: ?>
                                        <!-- Badge bleu pour les notifications normales -->
                                        <span
                                            class="relative inline-flex rounded-full h-5 w-5 bg-blue-500 text-white text-xs flex items-center justify-center">
                                            <?php echo $notifications['counts']['total']; ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <!-- Notifications Dropdown -->
                        <div id="notifications-dropdown"
                            class="hidden absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-lg border border-gray-200 z-50 overflow-hidden">
                            <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                                <h3 class="text-lg font-semibold text-gray-800">Notifications</h3>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?php echo $notifications['counts']['total']; ?>
                                    notification<?php echo $notifications['counts']['total'] > 1 ? 's' : ''; ?>
                                </span>
                            </div>

                            <!-- Section des matériaux reçus -->
                            <?php if (!empty($notifications['materials']['received'])): ?>
                                <div class="border-b border-gray-100">
                                    <div class="flex items-center justify-between p-3 bg-green-50">
                                        <h4 class="text-sm font-bold text-green-700 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
                                                fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Matériaux reçus
                                        </h4>
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <?php echo $notifications['counts']['received']; ?>
                                        </span>
                                    </div>
                                    <div class="max-h-40 overflow-y-auto">
                                        <?php foreach ($notifications['materials']['received'] as $material): ?>
                                            <div
                                                class="p-3 border-l-4 border-green-500 hover:bg-green-50 transition duration-200 flex justify-between items-start">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($material['designation']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600 mt-1">
                                                        <span
                                                            class="font-medium"><?php echo htmlspecialchars($material['code_projet'] ?? 'N/A'); ?></span>
                                                        - <?php echo htmlspecialchars($material['nom_client'] ?? 'N/A'); ?>
                                                    </p>
                                                    <div class="flex items-center mt-1">
                                                        <span class="text-xs text-gray-500 mr-2">
                                                            <?php echo htmlspecialchars($material['quantity']); ?>
                                                            <?php echo htmlspecialchars($material['unit'] ?? 'unité(s)'); ?>
                                                        </span>
                                                        <span class="text-xs text-green-600 font-medium">
                                                            Reçu <?php echo timeAgo($material['date_reception']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <a href="<?= $base_url ?>/User-BE/received_materials.php?projet=<?php echo htmlspecialchars($material['expression_id']); ?>"
                                                    class="text-green-600 hover:text-green-800 text-xs font-medium">
                                                    Voir détails
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Section des commandes annulées -->
                            <?php if (!empty($notifications['materials']['canceled'])): ?>
                                <div class="border-b border-gray-100">
                                    <div class="flex items-center justify-between p-3 bg-red-50">
                                        <h4 class="text-sm font-bold text-red-700 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
                                                fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Commandes annulées
                                        </h4>
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <?php echo $notifications['counts']['canceled']; ?>
                                        </span>
                                    </div>
                                    <div class="max-h-40 overflow-y-auto">
                                        <?php foreach ($notifications['materials']['canceled'] as $canceled): ?>
                                            <div
                                                class="p-3 border-l-4 border-red-500 hover:bg-red-50 transition duration-200 flex justify-between items-start">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($canceled['designation']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600 mt-1">
                                                        <span
                                                            class="font-medium"><?php echo htmlspecialchars($canceled['code_projet'] ?? 'N/A'); ?></span>
                                                        - <?php echo htmlspecialchars($canceled['nom_client'] ?? 'N/A'); ?>
                                                    </p>
                                                    <div class="flex items-center mt-1">
                                                        <span class="text-xs text-red-600 font-medium">
                                                            Annulé <?php echo timeAgo($canceled['canceled_at']); ?>:
                                                            <?php echo htmlspecialchars($canceled['cancel_reason']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <a href="<?= $base_url ?>/User-BE/liste_elements.php?projet=<?php echo htmlspecialchars($canceled['project_id']); ?>"
                                                    class="text-red-600 hover:text-red-800 text-xs font-medium">
                                                    Voir détails
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Section des modifications récentes -->
                            <?php if (!empty($notifications['materials']['recent'])): ?>
                                <div class="border-b border-gray-100">
                                    <div class="flex items-center justify-between p-3 bg-blue-50">
                                        <h4 class="text-sm font-bold text-blue-700 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
                                                fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Modifications récentes
                                        </h4>
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo $notifications['counts']['recent']; ?>
                                        </span>
                                    </div>
                                    <div class="max-h-40 overflow-y-auto">
                                        <?php foreach ($notifications['materials']['recent'] as $recent): ?>
                                            <div
                                                class="p-3 border-l-4 border-blue-500 hover:bg-blue-50 transition duration-200 flex justify-between items-start">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($recent['designation']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600 mt-1">
                                                        <span
                                                            class="font-medium"><?php echo htmlspecialchars($recent['code_projet'] ?? 'N/A'); ?></span>
                                                        - <?php echo htmlspecialchars($recent['nom_client'] ?? 'N/A'); ?>
                                                    </p>
                                                    <div class="flex items-center mt-1">
                                                        <span class="text-xs text-blue-600">
                                                            Modifié <?php echo timeAgo($recent['updated_at']); ?>
                                                            <?php if ($recent['valide_achat']): ?>
                                                                - Status: <?php echo htmlspecialchars($recent['valide_achat']); ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <a href="<?= $base_url ?>/User-BE/liste_elements.php?projet=<?php echo htmlspecialchars($recent['idExpression']); ?>"
                                                    class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                                    Voir détails
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Section des transferts/retours de matériaux -->
                            <?php if (!empty($notifications['materials']['pending'])): ?>
                                <div class="border-b border-gray-100">
                                    <div class="flex items-center justify-between p-3 bg-purple-50">
                                        <h4 class="text-sm font-bold text-purple-700 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
                                                fill="currentColor">
                                                <path
                                                    d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z" />
                                            </svg>
                                            Mouvements de stock
                                        </h4>
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                            <?php echo $notifications['counts']['pending']; ?>
                                        </span>
                                    </div>
                                    <div class="max-h-40 overflow-y-auto">
                                        <?php foreach ($notifications['materials']['pending'] as $pending): ?>
                                            <div
                                                class="p-3 border-l-4 border-purple-500 hover:bg-purple-50 transition duration-200 flex justify-between items-start">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($pending['product_name'] ?? 'Produit #' . $pending['product_id']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600 mt-1">
                                                        <span
                                                            class="font-medium"><?php echo htmlspecialchars($pending['nom_projet'] ?? 'N/A'); ?></span>
                                                        - <?php echo htmlspecialchars($pending['quantity']); ?> unité(s)
                                                    </p>
                                                    <div class="flex items-center mt-1">
                                                        <span class="text-xs text-purple-600 font-medium">
                                                            Transféré <?php echo timeAgo($pending['created_at']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <a href="<?= $base_url ?>/User-BE/project_return.php"
                                                    class="text-purple-600 hover:text-purple-800 text-xs font-medium">
                                                    Gérer
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Pied de page des notifications -->
                            <div class="p-3 bg-gray-50 text-center border-t border-gray-200">
                                <a href="<?= $base_url ?>/User-BE/dashboard.php"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow-sm transition duration-150">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                        <path fill-rule="evenodd"
                                            d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Voir toutes les notifications
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Profile dropdown -->
                    <div class="relative ml-3">
                        <div>
                            <button type="button"
                                class="relative flex max-w-xs items-center rounded-full bg-gray-800 text-sm focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800"
                                id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                <span class="absolute -inset-1.5"></span>
                                <span class="sr-only">Open user menu</span>
                                <?php if ($user && $user['profile_image']): ?>
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
                            <a href="<?= $base_url ?>/User-BE/profile.php"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem"
                                tabindex="-1">Profil</a>
                            <a href="<?= $base_url ?>/logout.php"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem"
                                tabindex="-1">Déconnexion</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="-mr-2 flex md:hidden">
                <!-- Bouton du menu mobile - maintenant fonctionnel -->
                <button type="button" id="mobile-menu-button"
                    class="relative inline-flex items-center justify-center rounded-md bg-gray-800 p-2 text-gray-400 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800"
                    aria-controls="mobile-menu" aria-expanded="false">
                    <span class="absolute -inset-0.5"></span>
                    <span class="sr-only">Open main menu</span>
                    <svg class="block h-6 w-6 menu-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg class="hidden h-6 w-6 close-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Menu mobile - amélioré pour être vraiment responsive -->
    <div class="md:hidden hidden" id="mobile-menu">
        <div class="space-y-1 px-2 pb-3 pt-2 sm:px-3">
            <a href="<?= $base_url ?>/User-BE/dashboard.php"
                class="block rounded-md px-3 py-2 text-base font-medium <?php echo ($current_page == 'dashboard.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>"
                aria-current="page">Dashboard</a>
            <a href="<?= $base_url ?>/User-BE/expression.php"
                class="block rounded-md px-3 py-2 text-base font-medium <?php echo ($current_page == 'expression.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Expression
                de besoin projet</a>
            <a href="<?= $base_url ?>/User-BE/views/besoins/index.php"
                class="block rounded-md px-3 py-2 text-base font-medium <?php echo ($current_page == 'index.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Expression
                de besoin système</a>
            <a href="<?= $base_url ?>/User-BE/consulter_stock.php"
                class="block rounded-md px-3 py-2 text-base font-medium <?php echo ($current_page == 'consulter_stock.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Voir
                stock</a>
            <a href="<?= $base_url ?>/User-BE/liste_elements.php"
                class="block rounded-md px-3 py-2 text-base font-medium <?php echo ($current_page == 'liste_elements.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Liste
                éléments</a>
            <a href="<?= $base_url ?>/User-BE/project_return.php"
                class="block rounded-md px-3 py-2 text-base font-medium <?php echo ($current_page == 'project_return.php') ? 'bg-gray-900 text-white' : 'text-indigo-300 hover:bg-gray-700 hover:text-white'; ?>"
                style="<?php echo ($current_page != 'project_return.php') ? 'background-color: #5959ec;' : ''; ?>">Retour
                produits non utilisés</a>
            <a href="<?= $base_url ?>/User-BE/profile.php"
                class="block rounded-md px-3 py-2 text-base font-medium <?php echo ($current_page == 'profile.php') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">Profil</a>
        </div>

        <!-- Ajout d'une section de notification pour mobile -->
        <div class="border-t border-gray-700 pt-4 pb-3">
            <div class="flex items-center px-5">
                <div class="flex-shrink-0">
                    <?php if ($user && $user['profile_image']): ?>
                        <img class="h-10 w-10 rounded-full"
                            src="../uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Image">
                    <?php else: ?>
                        <img class="h-10 w-10 rounded-full" src="../uploads/default-profile-image.png" alt="Profile Image">
                    <?php endif; ?>
                </div>
                <div class="ml-3">
                    <div class="text-base font-medium text-white">
                        <?php echo htmlspecialchars($user['name'] ?? 'Utilisateur'); ?></div>
                </div>

                <!-- Bouton de notification mobile -->
                <?php if ($notifications['counts']['total'] > 0): ?>
                    <div class="ml-auto">
                        <button id="mobile-notifications-toggle"
                            class="relative bg-gray-800 p-1 rounded-full text-gray-400 hover:text-white">
                            <span class="absolute -inset-1.5"></span>
                            <span class="sr-only">View notifications</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            <?php if ($notifications['counts']['total'] > 0): ?>
                                <span
                                    class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-600 ring-2 ring-white"></span>
                            <?php endif; ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Liste compact de notifications mobile -->
            <div id="mobile-notifications-panel" class="mt-3 px-2 hidden">
                <div class="bg-gray-700 rounded-lg overflow-hidden">
                    <?php if ($notifications['counts']['received'] > 0): ?>
                        <div class="p-3 border-b border-gray-600">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                                <span
                                    class="text-green-200 text-sm font-medium"><?php echo $notifications['counts']['received']; ?>
                                    matériaux reçus</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($notifications['counts']['canceled'] > 0): ?>
                        <div class="p-3 border-b border-gray-600">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-red-500 rounded-full mr-2"></div>
                                <span
                                    class="text-red-200 text-sm font-medium"><?php echo $notifications['counts']['canceled']; ?>
                                    commandes annulées</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($notifications['counts']['recent'] > 0): ?>
                        <div class="p-3 border-b border-gray-600">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                                <span
                                    class="text-blue-200 text-sm font-medium"><?php echo $notifications['counts']['recent']; ?>
                                    modifications récentes</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($notifications['counts']['pending'] > 0): ?>
                        <div class="p-3">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-purple-500 rounded-full mr-2"></div>
                                <span
                                    class="text-purple-200 text-sm font-medium"><?php echo $notifications['counts']['pending']; ?>
                                    mouvements de stock</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Lien vers le tableau de bord pour plus de détails -->
                    <div class="bg-gray-800 p-3 text-center">
                        <a href="<?= $base_url ?>/User-BE/dashboard.php"
                            class="text-gray-200 text-sm font-medium hover:text-white inline-flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fill-rule="evenodd"
                                    d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                    clip-rule="evenodd" />
                            </svg>
                            Voir toutes les notifications
                        </a>
                    </div>
                </div>
            </div>

            <!-- Actions utilisateur rapides pour mobile -->
            <div class="mt-3 space-y-1 px-2">
                <a href="<?= $base_url ?>/User-BE/profile.php"
                    class="block rounded-md py-2 px-3 text-base font-medium text-gray-300 hover:bg-gray-700 hover:text-white">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                clip-rule="evenodd" />
                        </svg>
                        Profil
                    </div>
                </a>
                <a href="<?= $base_url ?>/logout.php"
                    class="block rounded-md py-2 px-3 text-base font-medium text-gray-300 hover:bg-gray-700 hover:text-white">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M3 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1V7.414a1 1 0 00-.293-.707L11.414 2.414A1 1 0 0010.707 2H4a1 1 0 00-1 1zm9 8a1 1 0 11-2 0 1 1 0 012 0zm-6-1a1 1 0 100 2h4a1 1 0 100-2H6z"
                                clip-rule="evenodd" />
                        </svg>
                        Déconnexion
                    </div>
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Gestion du menu profil
        const profileMenuButton = document.getElementById('user-menu-button');
        const profileMenu = document.querySelector('.profile-menu');

        // Gestion des notifications
        const notificationsToggle = document.getElementById('notifications-toggle');
        const notificationsDropdown = document.getElementById('notifications-dropdown');

        // Gestion du menu mobile
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuIcon = document.querySelector('.menu-icon');
        const closeIcon = document.querySelector('.close-icon');

        // Gestion des notifications mobiles
        const mobileNotificationsToggle = document.getElementById('mobile-notifications-toggle');
        const mobileNotificationsPanel = document.getElementById('mobile-notifications-panel');

        // Fonction pour fermer tous les menus
        function closeAllMenus() {
            if (profileMenu) profileMenu.classList.add('hidden');
            if (notificationsDropdown) notificationsDropdown.classList.add('hidden');
            if (mobileNotificationsPanel) mobileNotificationsPanel.classList.add('hidden');
        }

        // Gestion du menu profil (Desktop)
        if (profileMenuButton && profileMenu) {
            profileMenuButton.addEventListener('click', function (e) {
                e.stopPropagation();
                closeAllMenus(); // Fermer tous les autres menus d'abord
                profileMenu.classList.toggle('hidden');
            });
        }

        // Gestion des notifications (Desktop)
        if (notificationsToggle && notificationsDropdown) {
            notificationsToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                closeAllMenus(); // Fermer tous les autres menus d'abord
                notificationsDropdown.classList.toggle('hidden');
            });

            // Empêcher la fermeture du dropdown lors d'un clic à l'intérieur
            notificationsDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        // Gestion du menu mobile
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function () {
                const isOpen = mobileMenu.classList.toggle('hidden');

                // Changer l'icône selon l'état du menu
                if (mobileMenu.classList.contains('hidden')) {
                    menuIcon.classList.remove('hidden');
                    closeIcon.classList.add('hidden');
                } else {
                    menuIcon.classList.add('hidden');
                    closeIcon.classList.remove('hidden');
                }

                // Si on ouvre le menu mobile, fermer tous les autres menus
                if (!mobileMenu.classList.contains('hidden')) {
                    closeAllMenus();
                }
            });
        }

        // Gestion des notifications mobiles
        if (mobileNotificationsToggle && mobileNotificationsPanel) {
            mobileNotificationsToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                mobileNotificationsPanel.classList.toggle('hidden');
            });

            // Empêcher la fermeture du panel lors d'un clic à l'intérieur
            mobileNotificationsPanel.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        // Fermer les menus en cliquant à l'extérieur
        document.addEventListener('click', function (event) {
            closeAllMenus();
        });

        // Amélioration de l'accessibilité avec le support clavier
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAllMenus();
                if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                    mobileMenu.classList.add('hidden');
                    menuIcon.classList.remove('hidden');
                    closeIcon.classList.add('hidden');
                }
            }
        });

        // Adaptation en cas de redimensionnement de la fenêtre
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 768) { // 768px est le breakpoint de Tailwind pour md:
                if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                    mobileMenu.classList.add('hidden');
                    menuIcon.classList.remove('hidden');
                    closeIcon.classList.add('hidden');
                }
            }
        });
    });
</script>