<?php
session_start();
require_once("../../../database/database.php");
require_once("../../../src/fonctions.php");

// Vérifie que l'utilisateur est connecté, sinon retour à l'accueil du site
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// Génère un jeton CSRF s'il n'existe pas encore
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Valeurs du formulaire (réaffichées en cas d'erreur) et message d'erreur éventuel
$nom_pays = "";
$error = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        // 2. Récupération des données (trim supprime les espaces inutiles).
        //    Les données sont enregistrées telles quelles et échappées à l'affichage (protection XSS).
        $nom_pays = trim($_POST['nom_pays'] ?? '');

        // 3. La logique métier (validation, doublon, insertion) est dans src/fonctions.php :
        //    elle est ainsi testée avec PHPUnit
        $resultat = ajouterPays($connexion, $nom_pays);

        if ($resultat['succes']) {
            $_SESSION['success'] = $resultat['message'];
            header("Location: manage-countries.php");
            exit();
        }
        $error = $resultat['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../../css/normalize.css">
    <link rel="stylesheet" href="../../../css/styles-computer.css">
    <link rel="stylesheet" href="../../../css/styles-responsive.css">
    <link rel="shortcut icon" href="../../../img/favicon.ico" type="image/x-icon">
    <title>Ajouter un Pays - Jeux Olympiques - Los Angeles 2028</title>
</head>

<body>
    <header>
        <nav>
            <ul class="menu">
                <li><a href="../admin.php">Accueil Administration</a></li>
                <li><a href="../admin-sports/manage-sports.php">Gestion Sports</a></li>
                <li><a href="../admin-places/manage-places.php">Gestion Lieux</a></li>
                <li><a href="../admin-countries/manage-countries.php">Gestion Pays</a></li>
                <li><a href="../admin-events/manage-events.php">Gestion Calendrier</a></li>
                <li><a href="../admin-athletes/manage-athletes.php">Gestion Athlètes</a></li>
                <li><a href="../admin-results/manage-results.php">Gestion Résultats</a></li>
                <li><a href="../../logout.php">Déconnexion</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h1>Ajouter un Pays</h1>

        <?php
        if ($error !== "") {
            echo '<p style="color: red;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        ?>

        <form action="add-country.php" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir ajouter ce pays ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="nom_pays">Nom du pays :</label>
            <input type="text" name="nom_pays" id="nom_pays" maxlength="255" value="<?php echo htmlspecialchars($nom_pays, ENT_QUOTES, 'UTF-8'); ?>" required>

            <input type="submit" value="Ajouter le Pays">
        </form>

        <p class="paragraph-link">
            <a class="link-home" href="manage-countries.php">Retour à la gestion des pays</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>
</body>

</html>
