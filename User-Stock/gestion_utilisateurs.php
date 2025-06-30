<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Vérifier si l'utilisateur est un super admin
include_once '../database/connection.php';
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_type, role FROM users_exp WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Stocker le rôle dans la session pour que la navbar puisse y accéder
if ($user && isset($user['role'])) {
    $_SESSION['user_role'] = $user['role'];
}

if (!$user || $user['role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

// Traitement de l'ajout d'un nouvel utilisateur
$message = '';
$message_type = '';

// Traitement pour l'ajout d'un utilisateur
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $confirm_password = password_hash($_POST['confirm_password'], PASSWORD_DEFAULT);
    $user_type = $_POST['user_type'];
    $role = isset($_POST['role']) ? $_POST['role'] : null;

    // Vérifier si l'email existe déjà
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_exp WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $message = "Cet email est déjà utilisé.";
        $message_type = "error";
    } else {
        // Insérer le nouvel utilisateur
        $stmt = $pdo->prepare("INSERT INTO users_exp (name, email, password, confirm_password, user_type, role) VALUES (:name, :email, :password, :confirm_password, :user_type, :role)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':confirm_password', $confirm_password);
        $stmt->bindParam(':user_type', $user_type);
        $stmt->bindParam(':role', $role);

        if ($stmt->execute()) {
            $message = "Utilisateur ajouté avec succès !";
            $message_type = "success";
        } else {
            $message = "Erreur lors de l'ajout de l'utilisateur.";
            $message_type = "error";
        }
    }
}

// Traitement pour la modification d'un utilisateur
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $user_type = $_POST['user_type'];
    $role = isset($_POST['role']) ? $_POST['role'] : null;
    
    // Vérifier si l'email existe déjà pour un autre utilisateur
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_exp WHERE email = :email AND id != :user_id");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $message = "Cet email est déjà utilisé par un autre utilisateur.";
        $message_type = "error";
    } else {
        // Vérifier si un nouveau mot de passe a été fourni
        if (!empty($_POST['password']) && !empty($_POST['confirm_password'])) {
            if ($_POST['password'] === $_POST['confirm_password']) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $confirm_password = password_hash($_POST['confirm_password'], PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users_exp SET name = :name, email = :email, password = :password, confirm_password = :confirm_password, user_type = :user_type, role = :role WHERE id = :user_id");
                $stmt->bindParam(':password', $password);
                $stmt->bindParam(':confirm_password', $confirm_password);
            } else {
                $message = "Les mots de passe ne correspondent pas.";
                $message_type = "error";
                // Récupérer la liste des utilisateurs
                $stmt = $pdo->prepare("SELECT id, name, email, user_type, role, created_at FROM users_exp ORDER BY created_at DESC");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                include('view_template.php');
                exit();
            }
        } else {
            $stmt = $pdo->prepare("UPDATE users_exp SET name = :name, email = :email, user_type = :user_type, role = :role WHERE id = :user_id");
        }
        
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':user_type', $user_type);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $message = "Utilisateur modifié avec succès !";
            $message_type = "success";
        } else {
            $message = "Erreur lors de la modification de l'utilisateur.";
            $message_type = "error";
        }
    }
}

// Traitement pour la suppression d'un utilisateur
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    $user_id = $_POST['user_id'];
    
    // Ne pas permettre la suppression de soi-même
    if ($user_id == $_SESSION['user_id']) {
        $message = "Vous ne pouvez pas supprimer votre propre compte.";
        $message_type = "error";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users_exp WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $message = "Utilisateur supprimé avec succès !";
            $message_type = "success";
        } else {
            $message = "Erreur lors de la suppression de l'utilisateur.";
            $message_type = "error";
        }
    }
}

// Récupérer les données d'un utilisateur spécifique (pour l'édition)
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT id, name, email, user_type, role FROM users_exp WHERE id = :edit_id");
    $stmt->bindParam(':edit_id', $edit_id);
    $stmt->execute();
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer la liste des utilisateurs
$stmt = $pdo->prepare("SELECT id, name, email, user_type, role, created_at FROM users_exp ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - DYM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
    /* Styles de base */
    .btn-primary {
        background-color: #1d4ed8;
        transition: all 0.3s;
    }
    
    .btn-primary:hover {
        background-color: #1e40af;
        transform: translateY(-2px);
    }
    
    .notification {
        position: fixed;
        top: 1rem;
        right: 1rem;
        padding: 1rem 1.5rem;
        border-radius: 0.375rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        transition: opacity 0.5s ease-in-out, transform 0.3s ease-in-out;
        z-index: 50;
        opacity: 1;
    }
    
    .notification.success {
        background-color: #38a169;
        color: white;
    }
    
    .notification.error {
        background-color: #e53e3e;
        color: white;
    }
    
    .notification.fade-out {
        opacity: 0;
    }
    
    .hidden {
        display: none !important;
    }

    /* Force navbar display */
    nav.bg-gray-800 {
        display: block !important;
    }
    
    /* Styles responsifs isolés pour la gestion des utilisateurs */
    @media (max-width: 640px) {
        /* Conteneur principal isolé */
        .user-management-container .flex.justify-between.items-center.mb-6 {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        
        .user-management-container .flex.justify-between.items-center.mb-6 h1 {
            text-align: center;
            margin-bottom: 0.5rem;
        }
        
        .user-management-container .flex.justify-between.items-center.mb-6 button {
            width: 100%;
            justify-content: center;
        }
        
        /* Ajustement du tableau */
        .user-management-container table {
            display: block;
            box-shadow: none;
            border-radius: 0;
        }
        
        .user-management-container thead {
            display: none;
        }
        
        .user-management-container tbody {
            display: block;
        }
        
        .user-management-container tr {
            display: block;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            background-color: white;
            padding: 1rem;
        }
        
        .user-management-container td {
            display: flex;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
            text-align: left;
            align-items: center;
        }
        
        .user-management-container td:last-child {
            border-bottom: none;
            justify-content: flex-end;
            padding-top: 1rem;
        }
        
        /* Ajouter des étiquettes pour remplacer les en-têtes de colonnes cachés */
        .user-management-container td::before {
            content: attr(data-label);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #6b7280;
            margin-right: 1rem;
            min-width: 100px;
        }
        
        .user-management-container td:last-child::before {
            content: "";
            min-width: 0;
        }
    }
    </style>
</head>
<body class="bg-gray-100">
    <?php 
    include('../components/navbar_stock.php');
    ?>
    
    <div class="container mx-auto px-4 py-8 user-management-container">
        <!-- Notifications -->
        <?php if ($message): ?>
            <div id="notification" class="notification <?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                <div class="flex items-center">
                    <span class="material-icons mr-2">
                        <?php if ($message_type === 'success'): ?>
                            check_circle
                        <?php else: ?>
                            error
                        <?php endif; ?>
                    </span>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
                <button onclick="closeNotification()" class="ml-4 text-white">
                    <span class="material-icons">close</span>
                </button>
            </div>
        <?php endif; ?>
        
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Gestion des utilisateurs</h1>
            <button id="openModalBtn" class="btn-primary text-white px-4 py-2 rounded-lg flex items-center">
                <span class="material-icons mr-1">person_add</span>
                Ajouter un utilisateur
            </button>
        </div>
        
        <!-- Tableau des utilisateurs avec attributs data-label pour mobile -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rôle</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date de création</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user_item): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap" data-label="Nom">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user_item['name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" data-label="Email">
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user_item['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" data-label="Type">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch($user_item['user_type']) {
                                        case 'admin':
                                            echo 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'user':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'bureau_etude':
                                            echo 'bg-purple-100 text-purple-800';
                                            break;
                                        case 'achat':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'finance':
                                            echo 'bg-pink-100 text-pink-800';
                                            break;
                                        case 'stock':
                                            echo 'bg-indigo-100 text-indigo-800';
                                            break;
                                        default:
                                            echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo htmlspecialchars($user_item['user_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" data-label="Rôle">
                                <?php if ($user_item['role']): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $user_item['role'] === 'super_admin' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo htmlspecialchars($user_item['role']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-label="Date de création">
                                <?php echo date('d/m/Y H:i', strtotime($user_item['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" data-label="Actions">
                                <button onclick="openEditModal(<?php echo $user_item['id']; ?>, '<?php echo htmlspecialchars($user_item['name']); ?>', '<?php echo htmlspecialchars($user_item['email']); ?>', '<?php echo htmlspecialchars($user_item['user_type']); ?>', '<?php echo htmlspecialchars($user_item['role'] ?? ''); ?>')" class="text-blue-600 hover:text-blue-900 mr-2" title="Modifier">
                                    <span class="material-icons">edit</span>
                                </button>
                                <button onclick="openDeleteModal(<?php echo $user_item['id']; ?>, '<?php echo htmlspecialchars($user_item['name']); ?>')" class="text-red-600 hover:text-red-900" title="Supprimer">
                                    <span class="material-icons">delete</span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal pour ajouter un utilisateur -->
    <div id="userModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">Ajouter un utilisateur</h3>
                <button id="closeModalBtn" class="text-gray-400 hover:text-gray-500">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form action="gestion_utilisateurs.php" method="POST" class="px-6 py-4">
                <input type="hidden" name="action" value="add_user">
                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nom utilisateur</label>
                        <input id="name" name="name" type="text" required 
                               class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="Nom de l'utilisateur" />
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Adresse e-mail</label>
                        <input id="email" name="email" type="email" required 
                               class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="exemple@email.com" />
                    </div>
                    <div>
                        <label for="user_type" class="block text-sm font-medium text-gray-700 mb-2">Type d'utilisateur</label>
                        <select id="user_type" name="user_type" required 
                                class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="" disabled selected>Sélectionnez un type</option>
                            <option value="user">Utilisateur</option>
                            <option value="admin">Admin</option>
                            <option value="achat">Achat</option>
                            <option value="finance">Finance</option>
                            <option value="bureau_etude">Bureau d'étude</option>
                            <option value="stock">Stock</option>
                        </select>
                    </div>
                    <div id="roleField" class="hidden">
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Rôle</label>
                        <select id="role" name="role" 
                                class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Standard</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Mot de passe</label>
                        <input id="password" name="password" type="password" required 
                               class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="••••••••" />
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirmer le mot de passe</label>
                        <input id="confirm_password" name="confirm_password" type="password" required 
                               class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="••••••••" />
                    </div>
                </div>
                <div class="mt-6">
                    <button id="submitBtn" type="submit" disabled
                            class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-300 cursor-not-allowed focus:outline-none">
                        Ajouter l'utilisateur
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal pour modifier un utilisateur -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">Modifier un utilisateur</h3>
                <button id="closeEditModalBtn" class="text-gray-400 hover:text-gray-500">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form action="gestion_utilisateurs.php" method="POST" class="px-6 py-4">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" id="edit_user_id" name="user_id" value="">
                <div class="space-y-4">
                    <div>
                        <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-2">Nom utilisateur</label>
                        <input id="edit_name" name="name" type="text" required 
                               class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="Nom de l'utilisateur" />
                    </div>
                    <div>
                        <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-2">Adresse e-mail</label>
                        <input id="edit_email" name="email" type="email" required 
                               class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="exemple@email.com" />
                    </div>
                    <div>
                        <label for="edit_user_type" class="block text-sm font-medium text-gray-700 mb-2">Type d'utilisateur</label>
                        <select id="edit_user_type" name="user_type" required 
                                class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="" disabled>Sélectionnez un type</option>
                            <option value="user">Utilisateur</option>
                            <option value="admin">Admin</option>
                            <option value="achat">Achat</option>
                            <option value="finance">Finance</option>
                            <option value="bureau_etude">Bureau d'étude</option>
                            <option value="stock">Stock</option>
                        </select>
                    </div>
                    <div id="edit_roleField">
                        <label for="edit_role" class="block text-sm font-medium text-gray-700 mb-2">Rôle</label>
                        <select id="edit_role" name="role" 
                                class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Standard</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit_password" class="block text-sm font-medium text-gray-700 mb-2">Nouveau mot de passe (laissez vide pour conserver l'actuel)</label>
                        <input id="edit_password" name="password" type="password"
                               class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="••••••••" />
                    </div>
                    <div>
                        <label for="edit_confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirmer le nouveau mot de passe</label>
                        <input id="edit_confirm_password" name="confirm_password" type="password"
                               class="appearance-none rounded-lg w-full px-4 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="••••••••" />
                    </div>
                </div>
                <div class="mt-6">
                    <button id="editSubmitBtn" type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal pour confirmer la suppression -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">Confirmer la suppression</h3>
                <button id="closeDeleteModalBtn" class="text-gray-400 hover:text-gray-500">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div class="px-6 py-4">
                <p class="text-gray-700">Êtes-vous sûr de vouloir supprimer l'utilisateur <span id="delete_user_name" class="font-semibold"></span> ?</p>
                <p class="text-sm text-gray-500 mt-2">Cette action est irréversible.</p>
                
                <form action="gestion_utilisateurs.php" method="POST" class="mt-6">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" id="delete_user_id" name="user_id" value="">
                    <div class="flex justify-end space-x-3">
                        <button type="button" id="cancelDeleteBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">
                            Annuler
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            Supprimer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Fonctions globales pour être accessibles depuis les boutons inline
    window.openEditModal = function(id, name, email, userType, role) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_user_type').value = userType;
        document.getElementById('edit_role').value = role || '';
        
        // Afficher le champ de rôle
        document.getElementById('edit_roleField').style.display = 'block';
        
        // Réinitialiser les champs de mot de passe
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_confirm_password').value = '';
        
        document.getElementById('editModal').classList.remove('hidden');
    };

    window.openDeleteModal = function(id, name) {
        document.getElementById('delete_user_id').value = id;
        document.getElementById('delete_user_name').textContent = name;
        document.getElementById('deleteModal').classList.remove('hidden');
    };

    window.closeNotification = function() {
        const notification = document.getElementById('notification');
        if (notification) {
            notification.classList.add('fade-out');
            setTimeout(() => {
                notification.classList.add('hidden');
            }, 500);
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Gestion du modal d'ajout
        const modal = document.getElementById('userModal');
        const openModalBtn = document.getElementById('openModalBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');
        
        if (openModalBtn) {
            openModalBtn.addEventListener('click', () => {
                modal.classList.remove('hidden');
            });
        }
        
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
            });
        }
        
        // Gestion du modal de modification
        const editModal = document.getElementById('editModal');
        const closeEditModalBtn = document.getElementById('closeEditModalBtn');
        
        if (closeEditModalBtn) {
            closeEditModalBtn.addEventListener('click', () => {
                editModal.classList.add('hidden');
            });
        }
        
        // Gestion du modal de suppression
        const deleteModal = document.getElementById('deleteModal');
        const closeDeleteModalBtn = document.getElementById('closeDeleteModalBtn');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        
        if (closeDeleteModalBtn) {
            closeDeleteModalBtn.addEventListener('click', () => {
                deleteModal.classList.add('hidden');
            });
        }
        
        if (cancelDeleteBtn) {
            cancelDeleteBtn.addEventListener('click', () => {
                deleteModal.classList.add('hidden');
            });
        }
        
        // Fermer les modals en cliquant à l'extérieur
        window.addEventListener('click', (e) => {
            if (modal && e.target === modal) {
                modal.classList.add('hidden');
            }
            if (editModal && e.target === editModal) {
                editModal.classList.add('hidden');
            }
            if (deleteModal && e.target === deleteModal) {
                deleteModal.classList.add('hidden');
            }
        });
        
        // Masquer la notification après 5 secondes
        const notification = document.getElementById('notification');
        if (notification) {
            setTimeout(() => {
                closeNotification();
            }, 5000);
        }
    
        // Afficher le champ de rôle pour tous les types d'utilisateurs
        const userTypeSelect = document.getElementById('user_type');
        const roleField = document.getElementById('roleField');
        
        if (userTypeSelect && roleField) {
            userTypeSelect.addEventListener('change', () => {
                roleField.classList.remove('hidden');
            });
        }
        
        // Vérification des mots de passe pour le formulaire d'ajout
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');
        
        function checkPasswordMatch() {
            if (!passwordInput || !confirmPasswordInput || !submitBtn) return;
            
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (password === confirmPassword && password !== '') {
                confirmPasswordInput.classList.remove('border-red-500');
                confirmPasswordInput.classList.add('border-green-500');
                submitBtn.disabled = false;
                submitBtn.classList.remove('bg-blue-300', 'cursor-not-allowed');
                submitBtn.classList.add('bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer');
            } else {
                if (confirmPassword !== '') {
                    confirmPasswordInput.classList.remove('border-green-500');
                    confirmPasswordInput.classList.add('border-red-500');
                }
                submitBtn.disabled = true;
                submitBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer');
                submitBtn.classList.add('bg-blue-300', 'cursor-not-allowed');
            }
        }
        
        if (passwordInput && confirmPasswordInput) {
            passwordInput.addEventListener('input', checkPasswordMatch);
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
        
        // Vérification des mots de passe pour le formulaire de modification
        const editPasswordInput = document.getElementById('edit_password');
        const editConfirmPasswordInput = document.getElementById('edit_confirm_password');
        const editSubmitBtn = document.getElementById('editSubmitBtn');
        
        function checkEditPasswordMatch() {
            if (!editPasswordInput || !editConfirmPasswordInput || !editSubmitBtn) return;
            
            const password = editPasswordInput.value;
            const confirmPassword = editConfirmPasswordInput.value;
            
            // Si les deux champs sont vides, on permet la soumission (pas de changement de mot de passe)
            if (password === '' && confirmPassword === '') {
                editConfirmPasswordInput.classList.remove('border-red-500', 'border-green-500');
                editConfirmPasswordInput.classList.add('border-gray-300');
                editSubmitBtn.disabled = false;
                editSubmitBtn.classList.remove('bg-blue-300', 'cursor-not-allowed');
                editSubmitBtn.classList.add('bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer');
                return;
            }
            
            // Si les mots de passe correspondent et ne sont pas vides
            if (password === confirmPassword && password !== '') {
                editConfirmPasswordInput.classList.remove('border-red-500', 'border-gray-300');
                editConfirmPasswordInput.classList.add('border-green-500');
                editSubmitBtn.disabled = false;
                editSubmitBtn.classList.remove('bg-blue-300', 'cursor-not-allowed');
                editSubmitBtn.classList.add('bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer');
            } else {
                if (confirmPassword !== '') {
                    editConfirmPasswordInput.classList.remove('border-green-500', 'border-gray-300');
                    editConfirmPasswordInput.classList.add('border-red-500');
                }
                
                // Ne pas désactiver le bouton, mais afficher une bordure rouge
                if (password !== '' || confirmPassword !== '') {
                    editSubmitBtn.disabled = true;
                    editSubmitBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer');
                    editSubmitBtn.classList.add('bg-blue-300', 'cursor-not-allowed');
                }
            }
        }
        
        if (editPasswordInput && editConfirmPasswordInput) {
            editPasswordInput.addEventListener('input', checkEditPasswordMatch);
            editConfirmPasswordInput.addEventListener('input', checkEditPasswordMatch);
        }
    });
    </script>
</body>
</html>