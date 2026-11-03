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
    header("Location: manage-events.php");
    exit();
}

// Vérification du jeton CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = "Jeton de sécurité invalide.";
    header("Location: manage-events.php");
    exit();
}

// Validation de l'identifiant (entier positif obligatoire)
$id_epreuve = filter_var($_POST['id_epreuve'] ?? null, FILTER_VALIDATE_INT);
if ($id_epreuve === false || $id_epreuve === null || $id_epreuve < 1) {
    $_SESSION['error'] = "Identifiant de l'épreuve invalide.";
    header("Location: manage-events.php");
    exit();
}

try {
    // Suppression avec une requête préparée
    $query = "DELETE FROM EPREUVE WHERE id_epreuve = :id_epreuve";
    $statement = $connexion->prepare($query);
    $statement->execute([':id_epreuve' => $id_epreuve]);

    if ($statement->rowCount() > 0) {
        $_SESSION['success'] = "L'épreuve a été supprimée avec succès.";
    } else {
        $_SESSION['error'] = "Épreuve non trouvée.";
    }
} catch (PDOException $e) {
    // Code 23000 : violation d'une clé étrangère (des résultats sont enregistrés pour cette épreuve)
    if ($e->getCode() == '23000') {
        $_SESSION['error'] = "Cette épreuve a des résultats enregistrés : supprimez d'abord ses résultats pour pouvoir la supprimer.";
    } else {
        error_log("Erreur PDO : " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la suppression de l'épreuve.";
    }
}

header("Location: manage-events.php");
exit();
?>
