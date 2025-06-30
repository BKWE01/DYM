<?php
// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Initialiser les notifications
$notification_type = isset($_GET['notification']) ? $_GET['notification'] : '';
$notification_message = isset($_GET['message']) ? $_GET['message'] : '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DYM - Connexion / Inscription</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .form-container {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-radius: 1rem;
            overflow: hidden;
        }
        
        .brand-side {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
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
            transition: opacity 1s ease-in-out, transform 0.3s ease-in-out;
            z-index: 50;
        }
        
        .notification.hidden {
            opacity: 0;
            transform: translateX(100%);
        }
        
        .notification.success {
            background-color: #38a169; /* bg-green-500 */
            color: white;
        }
        
        .notification.error {
            background-color: #e53e3e; /* bg-red-500 */
            color: white;
        }
        
        /* Bordure par défaut */
        #confirm-password {
            border: 2px solid #d1d5db; /* gris clair */
        }

        /* Bordure rouge si les mots de passe ne correspondent pas */
        #confirm-password.border-red-500 {
            border-color: #ef4444; /* rouge */
        }

        /* Bordure verte si les mots de passe correspondent */
        #confirm-password.border-green-500 {
            border-color: #10b981; /* vert */
        }
        
        .btn-primary {
            background-color: #1d4ed8;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #1e40af;
            transform: translateY(-2px);
        }
        
        .input-field {
            transition: all 0.3s;
        }
        
        .input-field:focus {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4 md:p-6">
    <div class="form-container bg-white w-full max-w-5xl mx-auto flex flex-col md:flex-row">
        <!-- Section Gauche: Branding -->
        <div class="brand-side w-full md:w-2/5 p-8 flex flex-col justify-center items-center text-center text-white">
            <div class="mb-6">
                <h1 class="text-4xl font-bold mb-2">DYM</h1>
                <p class="text-lg opacity-80">Plateforme de Gestion et de Suivi</p>
            </div>
            <div class="space-y-4 mt-8">
                <div class="p-4 bg-white bg-opacity-10 rounded-lg">
                    <p class="font-medium">Gérez vos projets efficacement</p>
                </div>
                <div class="p-4 bg-white bg-opacity-10 rounded-lg">
                    <p class="font-medium">Suivez votre progression</p>
                </div>
                <div class="p-4 bg-white bg-opacity-10 rounded-lg">
                    <p class="font-medium">Collaborez en temps réel</p>
                </div>
            </div>
        </div>

        <!-- Section Droite: Formulaire -->
        <div class="w-full md:w-3/5 p-8">
            <!-- Affichage des Notifications -->
            <?php if ($notification_message): ?>
                <div id="notification" class="notification <?php echo $notification_type === 'success' ? 'success' : 'error'; ?>">
                    <div class="flex items-center">
                        <span class="material-icons mr-2">
                            <?php if ($notification_type === 'success'): ?>
                                check_circle
                            <?php else: ?>
                                error
                            <?php endif; ?>
                        </span>
                        <span><?php echo htmlspecialchars($notification_message); ?></span>
                    </div>
                    <button onclick="closeNotification()" class="ml-4 text-white">
                        <span class="material-icons">close</span>
                    </button>
                </div>
            <?php endif; ?>

            <div id="sign-in-form">
                <h4 class="text-3xl font-bold text-gray-900 mb-2">Bienvenue</h4>
                <p class="text-gray-600 mb-8">
                    Connectez-vous à votre compte pour continuer
                </p>

                <form class="space-y-6" action="login.php" method="POST">
                    <div class="space-y-4">
                        <div>
                            <label for="email-address" class="block text-sm font-medium text-gray-700 mb-2">Adresse e-mail</label>
                            <input id="email-address" name="email" type="email" autocomplete="email" required 
                                   class="input-field appearance-none rounded-lg w-full px-4 py-3 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                   placeholder="exemple@email.com" />
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Mot de passe</label>
                            <input id="password" name="password" type="password" autocomplete="current-password" required 
                                   class="input-field appearance-none rounded-lg w-full px-4 py-3 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                   placeholder="••••••••" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-900"> Se souvenir de moi </label>
                        </div>

                        <div class="text-sm">
                            <a href="renitialisation_mot_de_passe.php" class="font-medium text-blue-600 hover:text-blue-500">Mot de passe oublié ?</a>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="btn-primary w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white">
                            Se connecter
                        </button>
                    </div>
                    
                    <!-- <div class="text-center mt-4">
                        <p class="text-sm text-gray-600">
                            Pas encore membre ? 
                            <a href="#" onclick="showSignUpForm(event)" class="font-medium text-blue-600 hover:text-blue-500">Inscrivez-vous</a>
                        </p>
                    </div> -->
                </form>
            </div>

            <div id="sign-up-form" class="hidden">
                <h3 class="text-3xl font-bold text-gray-900 mb-2">Créer un compte</h3>
                <p class="text-gray-600 mb-8">
                    Rejoignez la plateforme DYM dès maintenant
                </p>
                
                <form class="space-y-6" action="register.php" method="POST">
                    <div class="space-y-4">
                        <div>
                            <label for="sign-up-name" class="block text-sm font-medium text-gray-700 mb-2">Nom utilisateur</label>
                            <input id="sign-up-name" name="name" type="text" autocomplete="name" required 
                                   class="input-field appearance-none rounded-lg w-full px-4 py-3 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                   placeholder="Votre nom" />
                        </div>
                        <div>
                            <label for="sign-up-email" class="block text-sm font-medium text-gray-700 mb-2">Adresse e-mail</label>
                            <input id="sign-up-email" name="email" type="email" autocomplete="email" required 
                                   class="input-field appearance-none rounded-lg w-full px-4 py-3 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                   placeholder="exemple@email.com" />
                        </div>
                        <div>
                            <label for="user-type" class="block text-sm font-medium text-gray-700 mb-2">Type d'utilisateur</label>
                            <select id="user-type" name="user_type" required 
                                    class="input-field appearance-none rounded-lg w-full px-4 py-3 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="" disabled selected>Sélectionnez un type</option>
                                <option value="user">Utilisateur</option>
                                <option value="achat">Achat</option>
                                <option value="finance">Finance</option>
                                <option value="bureau_etude">Bureau d'étude</option>
                                <option value="stock">Stock</option>
                            </select>
                        </div>
                        <div>
                            <label for="sign-up-password" class="block text-sm font-medium text-gray-700 mb-2">Mot de passe</label>
                            <input id="sign-up-password" name="password" type="password" autocomplete="current-password" required 
                                   class="input-field appearance-none rounded-lg w-full px-4 py-3 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                   placeholder="••••••••" />
                        </div>
                        <div>
                            <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-2">Confirmer le mot de passe</label>
                            <input id="confirm-password" name="confirm_password" type="password" required 
                                   class="input-field appearance-none rounded-lg w-full px-4 py-3 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                   placeholder="••••••••" />
                        </div>
                    </div>

                    <div>
                        <button id="sign-up-button" type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-300 cursor-not-allowed focus:outline-none" disabled>
                            S'inscrire
                        </button>
                    </div>
                    
                    <div class="text-center mt-4">
                        <p class="text-sm text-gray-600">
                            Déjà membre ? 
                            <a href="#" onclick="showSignInForm(event)" class="font-medium text-blue-600 hover:text-blue-500">Connectez-vous</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showSignUpForm(event) {
            event.preventDefault();
            document.getElementById('sign-in-form').classList.add('hidden');
            document.getElementById('sign-up-form').classList.remove('hidden');
        }

        function showSignInForm(event) {
            event.preventDefault();
            document.getElementById('sign-up-form').classList.add('hidden');
            document.getElementById('sign-in-form').classList.remove('hidden');
        }

        function closeNotification() {
            const notification = document.getElementById('notification');
            notification.classList.add('hidden');
        }

        // Automatically hide the notification after 5 seconds
        setTimeout(() => {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.classList.add('hidden');
            }
        }, 5000);
        
        // Vérification des mots de passe
        const passwordInput = document.getElementById('sign-up-password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const signUpButton = document.getElementById('sign-up-button');

        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (password === confirmPassword && password !== '') {
                // Les mots de passe correspondent, bordure verte
                confirmPasswordInput.classList.remove('border-red-500');
                confirmPasswordInput.classList.add('border-green-500');
                signUpButton.disabled = false;
                signUpButton.classList.remove('bg-blue-300', 'cursor-not-allowed');
                signUpButton.classList.add('bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer', 'btn-primary');
            } else {
                // Les mots de passe ne correspondent pas, bordure rouge
                if (confirmPassword !== '') {
                    confirmPasswordInput.classList.remove('border-green-500');
                    confirmPasswordInput.classList.add('border-red-500');
                }
                signUpButton.disabled = true;
                signUpButton.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer', 'btn-primary');
                signUpButton.classList.add('bg-blue-300', 'cursor-not-allowed');
            }
        }

        // Vérifier la correspondance des mots de passe en temps réel
        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    </script>
</body>
</html>