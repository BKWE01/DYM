<?php
session_start();

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire la session
if (session_id() != "" || isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
    session_destroy();
}

// Rediriger vers index.php
header("Location: index.php");
exit(); // Assurez-vous de sortir du script après la redirection
?>
