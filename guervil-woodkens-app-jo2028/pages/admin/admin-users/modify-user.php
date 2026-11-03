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

// Récupération et validation de l'identifiant fourni dans l'URL
$id_admin = filter_input(INPUT_GET, 'id_admin', FILTER_VALIDATE_INT);
if ($id_admin === false || $id_admin === null || $id_admin < 1) {
    $_SESSION['error'] = "Identifiant de l'administrateur invalide.";
    header("Location: manage-users.php");
    exit();
}

// Lecture de l'administrateur à modifier (requête préparée)
try {
    $queryAdmin = "SELECT nom_admin, prenom_admin, login FROM ADMINISTRATEUR WHERE id_admin = :id_admin";
    $statementAdmin = $connexion->prepare($queryAdmin);
    $statementAdmin->execute([':id_admin' => $id_admin]);
    $admin = $statementAdmin->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO : " . $e->getMessage());
    $_SESSION['error'] = "Erreur de base de données.";
    header("Location: manage-users.php");
    exit();
}

if (!$admin) {
    $_SESSION['error'] = "Administrateur non trouvé.";
    header("Location: manage-users.php");
    exit();
}

// Valeurs affichées dans le formulaire : celles de la base, puis celles saisies en cas d'erreur
$nom = $admin['nom_admin'];
$prenom = $admin['prenom_admin'];
$login = $admin['login'];
$error = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        // 2. Récupération des données. Le mot de passe est facultatif :
        //    s'il est laissé vide, l'ancien mot de passe est conservé.
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        // 3. La logique métier (validation, doublon, hachage Bcrypt, mise à jour)
        //    est dans src/fonctions.php : elle est ainsi testée avec PHPUnit
        $resultat = modifierAdministrateur($connexion, $id_admin, $nom, $prenom, $login, $password);

        if ($resultat['succes']) {
            // Si l'administrateur modifie son propre compte, la session est mise à jour
            if ($id_admin === (int) $_SESSION['id_admin']) {
                $_SESSION['nom_admin'] = $nom;
                $_SESSION['prenom_admin'] = $prenom;
                $_SESSION['login'] = $login;
            }
            $_SESSION['success'] = $resultat['message'];
            header("Location: manage-users.php");
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
    <title>Modifier un Administrateur - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Modifier un Administrateur</h1>

        <?php
        if ($error !== "") {
            echo '<p style="color: red;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        ?>

        <form action="modify-user.php?id_admin=<?php echo $id_admin; ?>" method="post"
            onsubmit="return confirm('Êtes-vous sûr de vouloir modifier cet administrateur ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="nom">Nom :</label>
            <input type="text" name="nom" id="nom" maxlength="255" value="<?php echo htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="prenom">Prénom :</label>
            <input type="text" name="prenom" id="prenom" maxlength="255" value="<?php echo htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="login">Login :</label>
            <input type="text" name="login" id="login" maxlength="50" value="<?php echo htmlspecialchars($login, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="password">Nouveau mot de passe (laisser vide pour ne pas le changer) :</label>
            <input type="password" name="password" id="password" autocomplete="new-password">

            <input type="submit" value="Modifier l'Administrateur">
        </form>

        <p class="paragraph-link">
            <a class="link-home" href="manage-users.php">Retour à la gestion des administrateurs</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>
</body>

</html>
