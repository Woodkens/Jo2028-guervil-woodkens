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
    header("Location: manage-countries.php");
    exit();
}

// Vérification du jeton CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = "Jeton de sécurité invalide.";
    header("Location: manage-countries.php");
    exit();
}

// Validation de l'identifiant (entier positif obligatoire)
$id_pays = filter_var($_POST['id_pays'] ?? null, FILTER_VALIDATE_INT);
if ($id_pays === false || $id_pays === null || $id_pays < 1) {
    $_SESSION['error'] = "Identifiant invalide.";
    header("Location: manage-countries.php");
    exit();
}

// La logique métier (dont le refus de supprimer un pays encore utilisé par un athlète)
// est dans src/fonctions.php : elle est ainsi testée avec PHPUnit
$resultat = supprimerPays($connexion, $id_pays);

if ($resultat['succes']) {
    $_SESSION['success'] = $resultat['message'];
} else {
    $_SESSION['error'] = $resultat['message'];
}

header("Location: manage-countries.php");
exit();
?>
