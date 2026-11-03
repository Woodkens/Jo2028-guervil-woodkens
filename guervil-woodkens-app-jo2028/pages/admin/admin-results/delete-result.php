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
    header("Location: manage-results.php");
    exit();
}

// Vérification du jeton CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = "Jeton de sécurité invalide.";
    header("Location: manage-results.php");
    exit();
}

// Validation des identifiants (entiers positifs obligatoires)
$id_athlete = filter_var($_POST['id_athlete'] ?? null, FILTER_VALIDATE_INT);
$id_epreuve = filter_var($_POST['id_epreuve'] ?? null, FILTER_VALIDATE_INT);
if ($id_athlete === false || $id_athlete === null || $id_athlete < 1
    || $id_epreuve === false || $id_epreuve === null || $id_epreuve < 1) {
    $_SESSION['error'] = "Identifiants du résultat invalides.";
    header("Location: manage-results.php");
    exit();
}

try {
    // Suppression avec une requête préparée
    $query = "DELETE FROM PARTICIPER WHERE id_athlete = :id_athlete AND id_epreuve = :id_epreuve";
    $statement = $connexion->prepare($query);
    $statement->execute([':id_athlete' => $id_athlete, ':id_epreuve' => $id_epreuve]);

    if ($statement->rowCount() > 0) {
        $_SESSION['success'] = "Le résultat a été supprimé avec succès.";
    } else {
        $_SESSION['error'] = "Résultat non trouvé.";
    }
} catch (PDOException $e) {
    error_log("Erreur PDO : " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de la suppression du résultat.";
}

header("Location: manage-results.php");
exit();
?>
