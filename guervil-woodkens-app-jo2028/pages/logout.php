<?php
session_start();

// Vérification du token CSRF avant de détruire la session
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Vérifie si le token CSRF est présent et valide
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Token CSRF invalide.";
        header('Location: ../index.php');
        exit();
    }

    // Détruire la session utilisateur en toute sécurité
    session_unset(); // Détruit toutes les variables de session.
    session_destroy(); // Détruit la session elle-même.
    unset($_SESSION); // Supprime complètement la session.

    // Redirige vers la page d'accueil après déconnexion
    header('Location: ../index.php');
    exit();
}

// Générez un token CSRF si nécessaire
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un token sécurisé
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/styles-computer.css">
    <link rel="stylesheet" href="../css/styles-responsive.css">
    <link rel="shortcut icon" href="../img/favicon.ico" type="image/x-icon">
    <title>Déconnexion - Jeux Olympiques - Los Angeles 2028</title>
</head>

<body>
    <header>
        <nav>
            <!-- Menu vers les pages sports, events, et results -->
            <ul class="menu">
                <li><a href="../index.php">Accueil</a></li>
                <li><a href="sports.php">Sports</a></li>
                <li><a href="events.php">Calendrier des évènements</a></li>
                <li><a href="results.php">Résultats</a></li>
                <li><a href="login.php">Accès administrateur</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h1>Déconnexion</h1>
        <p>Vous êtes sur le point de vous déconnecter.</p>
        <!-- Formulaire de déconnexion avec token CSRF -->
        <form action="logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="submit" value="Se déconnecter">
        </form>
    </main>
    <footer>
        <figure>
            <img src="../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>
</body>

</html>
