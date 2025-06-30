<?php
// Connexion à la base de données
include_once 'database/connection.php';

try {

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = $_POST['email'];
        $name = $_POST['name'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $confirm_password = password_hash($_POST['confirm_password'], PASSWORD_DEFAULT);
        $user_type = $_POST['user_type'];

        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_exp WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            // Email existe déjà
            header("Location: index.php?notification=error&message=Cet email est déjà utilisé.");
        } else {
            // Insérer le nouvel utilisateur
            $stmt = $pdo->prepare("INSERT INTO users_exp (name, email, password, confirm_password,  user_type) VALUES (:name, :email, :password, :confirm_password, :user_type)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':confirm_password', $confirm_password);
            $stmt->bindParam(':user_type', $user_type);

            if ($stmt->execute()) {
                header("Location: index.php?notification=success&message=Inscription réussie!");
            } else {
                header("Location: index.php?notification=error&message=Erreur lors de l'inscription.");
            }
        }
    }
} catch (PDOException $e) {
    header("Location: index.php?notification=error&message=Erreur de connexion: " . $e->getMessage());
}

$pdo = null;
?>
