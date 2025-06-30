<?php
session_start();

// Connexion à la base de données
include_once 'database/connection.php';

$message = ''; // Initialiser le message

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Vérifier que les mots de passe correspondent
    if ($password === $confirm_password) {
        // Hachage du mot de passe
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Mettre à jour le mot de passe de l'utilisateur
        try {
            $stmt = $pdo->prepare("UPDATE users_exp SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);

            if ($stmt->rowCount() > 0) {
                $message = "<p class='text-green-500'>Mot de passe mis à jour avec succès ! ( <a href='index.php' class='text-blue-500 hover:underline'><span class='material-icons align-middle'>arrow_forward</span> Connectez-vous</a> )</p>";
            } else {
                $message = "<p class='text-red-500'>Erreur : Aucun utilisateur trouvé avec cette adresse e-mail.</p>";
            }
        } catch (PDOException $e) {
            $message = "<p class='text-red-500'>Erreur lors de la mise à jour du mot de passe: " . $e->getMessage() . "</p>";
        }
    } else {
        $message = "<p class='text-red-500'>Les mots de passe ne correspondent pas.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"> <!-- Ajout de Material Icons -->
    <title>Réinitialisation de mot de passe</title>
    <style>
        body {
            font-family: "Noto Sans", Verdana, Geneva, Tahoma, sans-serif;
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
        .title {
            font-size: 28px;
            color: #2d2d2d;
            line-height: 1.2;
            font-weight: 500;
        }
    </style>
</head>
<body class="flex items-center justify-center h-screen bg-gray-100">
    <div id="renitialisation-form" class="bg-white p-6 rounded-lg">
        <div class="mb-6">
            <a href="index.php"><img src="logo.png" alt="Logo" class="w-24 h-22 mx-auto"></a>
        </div>
        <h3 class="title">Rénitialiser votre mot de passe</h3>
        <br>

        <!-- Affichage du message PHP -->
        <div class="mt-4">
            <?php if ($message): ?>
                <?php echo $message; ?>
            <?php endif; ?>
        </div>

        <form class="mt-8 space-y-6" action="" method="POST">
            <div class="space-y-4">
                <div>
                    <label for="renitialisation-email" class="block text-sm font-medium text-gray-700 mb-2">Adresse e-mail</label>
                    <input id="renitialisation-email" name="email" type="email" autocomplete="email" required class="appearance-none rounded-md w-full px-3 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" />
                </div>
                <div>
                    <label for="renitialisation-password" class="block text-sm font-medium text-gray-700 mb-2">Mot de passe</label>
                    <input id="renitialisation-password" name="password" type="password" autocomplete="current-password" required class="appearance-none rounded-md w-full px-3 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" />
                </div>
                <div>
                    <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-2">Confirmer le mot de passe</label>
                    <input id="confirm-password" name="confirm_password" type="password" required class="appearance-none rounded-md w-full px-3 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" />
                </div>
            </div>

            <div>
                <button id="renitialisation-button" type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-300 cursor-not-allowed focus:outline-none" disabled>
                    Réinitialiser
                </button>
            </div>
        </form>
    </div>

    <script>
        const passwordInput = document.getElementById('renitialisation-password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const signUpButton = document.getElementById('renitialisation-button');

        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (password === confirmPassword && password !== '') {
                // Les mots de passe correspondent, bordure verte
                confirmPasswordInput.classList.remove('border-red-500');
                confirmPasswordInput.classList.add('border-green-500');
                signUpButton.disabled = false;
                signUpButton.classList.remove('bg-blue-300', 'cursor-not-allowed');
                signUpButton.classList.add('bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer');
            } else {
                // Les mots de passe ne correspondent pas, bordure rouge
                confirmPasswordInput.classList.remove('border-green-500');
                confirmPasswordInput.classList.add('border-red-500');
                signUpButton.disabled = true;
                signUpButton.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'cursor-pointer');
                signUpButton.classList.add('bg-blue-300', 'cursor-not-allowed');
            }
        }

        // Vérifier la correspondance des mots de passe en temps réel
        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    </script>
</body>
</html>
