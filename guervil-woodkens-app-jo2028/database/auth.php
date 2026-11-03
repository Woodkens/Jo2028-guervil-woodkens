<?php
session_start(); // Démarre la session PHP pour stocker des variables de session.

require_once("database.php"); // Inclut le fichier de connexion à la base de données.

// Générer un token CSRF si ce n'est pas déjà fait
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérifie si le formulaire est soumis en POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Vérifie le token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Token CSRF invalide.";
        header("location: ../pages/login.php");
        exit();
    }

    // Assainir et valider les données d'entrée
    $login = filter_input(INPUT_POST, "login", FILTER_SANITIZE_EMAIL); // Assurez-vous que c'est un email valide
    $password = filter_input(INPUT_POST, "password", FILTER_SANITIZE_STRING); // Assainir la chaîne du mot de passe

    // Si le login ou mot de passe est vide
    if (empty($login) || empty($password)) {
        $_SESSION['error'] = "Login ou mot de passe manquant.";
        header("location: ../pages/login.php");
        exit();
    }

    // Préparer la requête SQL pour récupérer les informations de l'administrateur avec le login spécifié
    $query = "SELECT id_admin, nom_admin, prenom_admin, login, password FROM ADMINISTRATEUR WHERE login = :login";
    $stmt = $connexion->prepare($query); // Prépare la requête avec PDO.
    $stmt->bindParam(":login", $login, PDO::PARAM_STR); // Lie la variable :login à la valeur du login, évitant les injections SQL.

    if ($stmt->execute()) { // Exécute la requête préparée
        $row = $stmt->fetch(PDO::FETCH_ASSOC); // Récupère la première ligne de résultat de la requête

        if ($row && password_verify($password, $row["password"])) {
            // Si l'administrateur existe et que le mot de passe correspond
            $_SESSION["id_admin"] = $row["id_admin"];
            $_SESSION["nom_admin"] = $row["nom_admin"];
            $_SESSION["prenom_admin"] = $row["prenom_admin"];
            $_SESSION["login"] = $row["login"];
            
            // Redirige vers la page d'administration
            header("location: ../pages/admin/admin.php");
            exit(); // Termine le script
        } else {
            $_SESSION['error'] = "Login ou mot de passe incorrect.";
            header("location: ../pages/login.php"); // Redirige avec une erreur
        }
    } else {
        $_SESSION['error'] = "Erreur lors de l'exécution de la requête.";
        header("location: ../pages/login.php"); // Redirige avec une erreur
    }

    unset($stmt); // Libère la ressource associée à la requête préparée
} else {
    // Si le formulaire n'est pas soumis en POST, on redirige vers la page de login
    header("location: ../pages/login.php");
    exit(); // Termine le script
}

// Libération de la connexion à la base de données
unset($connexion);

// Affichage des erreurs en PHP (fonctionne à condition d'avoir activé l'option en local)
error_reporting(E_ALL);
ini_set("display_errors", 1);
exit(); // Termine le script.
?>