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

// Récupération et validation de l'identifiant fourni dans l'URL
$id_genre = filter_input(INPUT_GET, 'id_genre', FILTER_VALIDATE_INT);
if ($id_genre === false || $id_genre === null || $id_genre < 1) {
    $_SESSION['error'] = "Identifiant invalide.";
    header("Location: manage-genres.php");
    exit();
}

// Lecture de l'élément à modifier (requête préparée)
try {
    $querySelect = "SELECT nom_genre FROM GENRE WHERE id_genre = :id_genre";
    $statementSelect = $connexion->prepare($querySelect);
    $statementSelect->execute([':id_genre' => $id_genre]);
    $row = $statementSelect->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO : " . $e->getMessage());
    $_SESSION['error'] = "Erreur de base de données.";
    header("Location: manage-genres.php");
    exit();
}

if (!$row) {
    $_SESSION['error'] = "Genre non trouvé.";
    header("Location: manage-genres.php");
    exit();
}

// Valeurs affichées dans le formulaire : celles de la base, puis celles saisies en cas d'erreur
$nom_genre = $row['nom_genre'];
$error = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        // 2. Récupération des données
        $nom_genre = trim($_POST['nom_genre'] ?? '');

        // 3. Validation des données
        if ($nom_genre === '') {
            $error = "Tous les champs sont obligatoires.";
        } elseif (mb_strlen($nom_genre) > 255) {
            $error = "Le champ « Nom du genre » ne doit pas dépasser 255 caractères.";
        } else {
            try {
                // 4. Contrôle du doublon : le nom ne doit pas appartenir à un AUTRE enregistrement
                $queryCheck = "SELECT id_genre FROM GENRE WHERE nom_genre = :nom_genre AND id_genre <> :id_genre";
                $statementCheck = $connexion->prepare($queryCheck);
                $statementCheck->execute([':nom_genre' => $nom_genre, ':id_genre' => $id_genre]);

                if ($statementCheck->rowCount() > 0) {
                    $error = "Ce genre existe déjà.";
                } else {
                    // 5. Mise à jour avec une requête préparée
                    $query = "UPDATE GENRE SET nom_genre = :nom_genre WHERE id_genre = :id_genre";
                    $statement = $connexion->prepare($query);
                    $statement->execute([
                        ':nom_genre' => $nom_genre,
                        ':id_genre' => $id_genre
                    ]);

                    $_SESSION['success'] = "Le genre a été modifié avec succès.";
                    header("Location: manage-genres.php");
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Erreur PDO : " . $e->getMessage());
                $error = "Erreur lors de la modification.";
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
    <title>Modifier un Genre - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Modifier un Genre</h1>

        <?php
        if ($error !== "") {
            echo '<p style="color: red;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        ?>

        <form action="modify-genre.php?id_genre=<?php echo $id_genre; ?>" method="post"
            onsubmit="return confirm('Êtes-vous sûr de vouloir modifier ce genre ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="nom_genre">Nom du genre :</label>
            <input type="text" name="nom_genre" id="nom_genre" maxlength="255" value="<?php echo htmlspecialchars($nom_genre, ENT_QUOTES, 'UTF-8'); ?>" required>

            <input type="submit" value="Modifier le Genre">
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
