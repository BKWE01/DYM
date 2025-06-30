<?php
session_start();
// Connexion à la base de données
include_once 'database/connection.php';

try {

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = $_POST['email'];
        $password = $_POST['password'];

        // Modifiez votre requête SQL pour inclure les colonnes 'role' et 'user_type'
        $stmt = $pdo->prepare("SELECT id, password, user_type, role FROM users_exp WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role']; // Rôle peut être null
            $_SESSION['user_type'] = $user['user_type']; // Stocker le type d'utilisateur dans la session

            // Redirection en fonction du type d'utilisateur
            switch ($user['user_type']) {
                case 'bureau_etude':
                    header("Location: User-BE/dashboard.php");
                    break;
                case 'achat':
                    header("Location: User-Achat/dashboard.php");
                    break;
                case 'stock':
                    header("Location: User-Stock/dashboard.php");
                    break;
                case 'finance':
                    header("Location: User-Finance/dashboard.php");
                    break;
                case 'admin':
                    header("Location: User-admin/dashboard.php");
                    break;
                case 'user':
                    header("Location: User/dashboard.php");
                    break;
                default:
                    header("Location: index.php?notification=error&message=Type d'utilisateur inconnu.");
                    break;
            }
            exit(); // Assurez-vous de sortir du script après la redirection
        } else {
            header("Location: index.php?notification=error&message=Mot de passe incorrect ou adresse e-mail inconnue.");
            exit(); // Assurez-vous de sortir du script après la redirection
        }
    }
} catch (PDOException $e) {
    header("Location: index.php?notification=error&message=Erreur de connexion: " . $e->getMessage());
    exit(); // Assurez-vous de sortir du script après la redirection
}

$pdo = null;
?>