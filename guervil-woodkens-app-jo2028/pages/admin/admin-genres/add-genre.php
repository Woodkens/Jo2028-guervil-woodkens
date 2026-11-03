<?php
session_start();
require_once("../../../database/database.php");

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
$nom_genre = "";
$error = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        // 2. Récupération des données (trim supprime les espaces inutiles).
        //    Les données sont enregistrées telles quelles et échappées à l'affichage (protection XSS).
        $nom_genre = trim($_POST['nom_genre'] ?? '');

        // 3. Validation des données
        if ($nom_genre === '') {
            $error = "Tous les champs sont obligatoires.";
        } elseif (mb_strlen($nom_genre) > 255) {
            $error = "Le champ « Nom du genre » ne doit pas dépasser 255 caractères.";
        } else {
            try {
                // 4. Contrôle du doublon (requête préparée)
                $queryCheck = "SELECT id_genre FROM GENRE WHERE nom_genre = :nom_genre";
                $statementCheck = $connexion->prepare($queryCheck);
                $statementCheck->execute([':nom_genre' => $nom_genre]);

                if ($statementCheck->rowCount() > 0) {
                    $error = "Ce genre existe déjà.";
                } else {
                    // 5. Insertion avec une requête préparée (protection contre les injections SQL)
                    $query = "INSERT INTO GENRE (nom_genre) VALUES (:nom_genre)";
                    $statement = $connexion->prepare($query);
                    $statement->execute([
                        ':nom_genre' => $nom_genre
                    ]);

                    $_SESSION['success'] = "Le genre a été ajouté avec succès.";
                    header("Location: manage-genres.php");
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Erreur PDO : " . $e->getMessage());
                $error = "Erreur lors de l'ajout.";
            }
        }
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
    <title>Ajouter un Genre - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Ajouter un Genre</h1>

        <?php
        if ($error !== "") {
            echo '<p style="color: red;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        ?>

        <form action="add-genre.php" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir ajouter ce genre ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="nom_genre">Nom du genre :</label>
            <input type="text" name="nom_genre" id="nom_genre" maxlength="255" value="<?php echo htmlspecialchars($nom_genre, ENT_QUOTES, 'UTF-8'); ?>" required>

            <input type="submit" value="Ajouter le Genre">
        </form>

        <p class="paragraph-link">
            <a class="link-home" href="manage-genres.php">Retour à la gestion des genres</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>
</body>

</html>
