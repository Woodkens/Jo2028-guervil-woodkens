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
    header("Location: manage-athletes.php");
    exit();
}

// Vérification du jeton CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = "Jeton de sécurité invalide.";
    header("Location: manage-athletes.php");
    exit();
}

// Validation de l'identifiant (entier positif obligatoire)
$id_athlete = filter_var($_POST['id_athlete'] ?? null, FILTER_VALIDATE_INT);
if ($id_athlete === false || $id_athlete === null || $id_athlete < 1) {
    $_SESSION['error'] = "Identifiant de l'athlète invalide.";
    header("Location: manage-athletes.php");
    exit();
}

try {
    // Suppression avec une requête préparée
    $query = "DELETE FROM ATHLETE WHERE id_athlete = :id_athlete";
    $statement = $connexion->prepare($query);
    $statement->execute([':id_athlete' => $id_athlete]);

    if ($statement->rowCount() > 0) {
        $_SESSION['success'] = "L'athlète a été supprimé avec succès.";
    } else {
        $_SESSION['error'] = "Athlète non trouvé.";
    }
} catch (PDOException $e) {
    // Code 23000 : violation d'une clé étrangère (des résultats sont enregistrés pour cet athlète)
    if ($e->getCode() == '23000') {
        $_SESSION['error'] = "Cet athlète a des résultats enregistrés : supprimez d'abord ses résultats pour pouvoir le supprimer.";
    } else {
        error_log("Erreur PDO : " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la suppression de l'athlète.";
    }
}

header("Location: manage-athletes.php");
exit();
?>
