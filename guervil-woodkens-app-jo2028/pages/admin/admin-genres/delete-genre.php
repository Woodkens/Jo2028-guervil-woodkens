<?php
session_start();
require_once("../../../database/database.php");

// Vérifie que l'utilisateur est connecté, sinon retour à l'accueil du site
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// La suppression n'est acceptée qu'en POST (jamais par un simple lien)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage-genres.php");
    exit();
}

// Vérification du jeton CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = "Jeton de sécurité invalide.";
    header("Location: manage-genres.php");
    exit();
}

// Validation de l'identifiant (entier positif obligatoire)
$id_genre = filter_var($_POST['id_genre'] ?? null, FILTER_VALIDATE_INT);
if ($id_genre === false || $id_genre === null || $id_genre < 1) {
    $_SESSION['error'] = "Identifiant invalide.";
    header("Location: manage-genres.php");
    exit();
}

try {
    // Suppression avec une requête préparée
    $query = "DELETE FROM GENRE WHERE id_genre = :id_genre";
    $statement = $connexion->prepare($query);
    $statement->execute([':id_genre' => $id_genre]);

    if ($statement->rowCount() > 0) {
        $_SESSION['success'] = "Le genre a été supprimé avec succès.";
    } else {
        $_SESSION['error'] = "Genre non trouvé.";
    }
} catch (PDOException $e) {
    // Code 23000 : violation d'une clé étrangère (élément encore utilisé par une autre table)
    if ($e->getCode() == '23000') {
        $_SESSION['error'] = "Ce genre est utilisé par au moins un athlète : il ne peut pas être supprimé.";
    } else {
        error_log("Erreur PDO : " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la suppression.";
    }
}

header("Location: manage-genres.php");
exit();
?>
