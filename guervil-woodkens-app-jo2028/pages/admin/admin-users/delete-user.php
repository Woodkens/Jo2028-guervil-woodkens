<?php
session_start();
require_once("../../../database/database.php");
require_once("../../../src/fonctions.php");

// Vérifie que l'utilisateur est connecté, sinon retour à l'accueil du site
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// La suppression n'est acceptée qu'en POST (jamais par un simple lien)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage-users.php");
    exit();
}

// Vérification du jeton CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = "Jeton de sécurité invalide.";
    header("Location: manage-users.php");
    exit();
}

// Validation de l'identifiant (entier positif obligatoire)
$id_admin = filter_var($_POST['id_admin'] ?? null, FILTER_VALIDATE_INT);
if ($id_admin === false || $id_admin === null || $id_admin < 1) {
    $_SESSION['error'] = "Identifiant de l'administrateur invalide.";
    header("Location: manage-users.php");
    exit();
}

// La logique métier (dont l'interdiction de supprimer son propre compte)
// est dans src/fonctions.php : elle est ainsi testée avec PHPUnit
$resultat = supprimerAdministrateur($connexion, $id_admin, (int) $_SESSION['id_admin']);

if ($resultat['succes']) {
    $_SESSION['success'] = $resultat['message'];
} else {
    $_SESSION['error'] = $resultat['message'];
}

header("Location: manage-users.php");
exit();
?>
