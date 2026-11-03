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
    header("Location: manage-places.php");
    exit();
}

// Vérification du jeton CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = "Jeton de sécurité invalide.";
    header("Location: manage-places.php");
    exit();
}

// Validation de l'identifiant (entier positif obligatoire)
$id_lieu = filter_var($_POST['id_lieu'] ?? null, FILTER_VALIDATE_INT);
if ($id_lieu === false || $id_lieu === null || $id_lieu < 1) {
    $_SESSION['error'] = "Identifiant invalide.";
    header("Location: manage-places.php");
    exit();
}

try {
    // Suppression avec une requête préparée
    $query = "DELETE FROM LIEU WHERE id_lieu = :id_lieu";
    $statement = $connexion->prepare($query);
    $statement->execute([':id_lieu' => $id_lieu]);

    if ($statement->rowCount() > 0) {
        $_SESSION['success'] = "Le lieu a été supprimé avec succès.";
    } else {
        $_SESSION['error'] = "Lieu non trouvé.";
    }
} catch (PDOException $e) {
    // Code 23000 : violation d'une clé étrangère (élément encore utilisé par une autre table)
    if ($e->getCode() == '23000') {
        $_SESSION['error'] = "Ce lieu est utilisé par au moins une épreuve : il ne peut pas être supprimé.";
    } else {
        error_log("Erreur PDO : " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la suppression.";
    }
}

header("Location: manage-places.php");
exit();
?>
