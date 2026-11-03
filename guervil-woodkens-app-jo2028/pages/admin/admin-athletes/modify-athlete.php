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

// Listes pour les menus déroulants (pays et genres), lues dans la base
try {
    $pays = $connexion->query("SELECT id_pays, nom_pays FROM PAYS ORDER BY nom_pays")->fetchAll(PDO::FETCH_ASSOC);
    $genres = $connexion->query("SELECT id_genre, nom_genre FROM GENRE ORDER BY nom_genre")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO : " . $e->getMessage());
    $_SESSION['error'] = "Erreur de base de données.";
    header("Location: manage-athletes.php");
    exit();
}

// Récupération et validation de l'identifiant fourni dans l'URL
$id_athlete = filter_input(INPUT_GET, 'id_athlete', FILTER_VALIDATE_INT);
if ($id_athlete === false || $id_athlete === null || $id_athlete < 1) {
    $_SESSION['error'] = "Identifiant de l'athlète invalide.";
    header("Location: manage-athletes.php");
    exit();
}

// Lecture de l'athlète à modifier (requête préparée)
try {
    $querySelect = "SELECT nom_athlete, prenom_athlete, id_pays, id_genre FROM ATHLETE WHERE id_athlete = :id_athlete";
    $statementSelect = $connexion->prepare($querySelect);
    $statementSelect->execute([':id_athlete' => $id_athlete]);
    $row = $statementSelect->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO : " . $e->getMessage());
    $_SESSION['error'] = "Erreur de base de données.";
    header("Location: manage-athletes.php");
    exit();
}

if (!$row) {
    $_SESSION['error'] = "Athlète non trouvé.";
    header("Location: manage-athletes.php");
    exit();
}

// Valeurs affichées dans le formulaire : celles de la base, puis celles saisies en cas d'erreur
$nom_athlete = (string) $row['nom_athlete'];
$prenom_athlete = (string) $row['prenom_athlete'];
$id_pays = (string) $row['id_pays'];
$id_genre = (string) $row['id_genre'];
$error = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        // 2. Récupération des données
        $nom_athlete = trim($_POST['nom_athlete'] ?? '');
        $prenom_athlete = trim($_POST['prenom_athlete'] ?? '');
        $id_pays = trim($_POST['id_pays'] ?? '');
        $id_genre = trim($_POST['id_genre'] ?? '');

        // 3. Validation des données
        if ($nom_athlete === '' || $prenom_athlete === '' || $id_pays === '' || $id_genre === '') {
            $error = "Tous les champs sont obligatoires.";
        } elseif (mb_strlen($nom_athlete) > 255 || mb_strlen($prenom_athlete) > 255) {
            $error = "Le nom et le prénom ne doivent pas dépasser 255 caractères.";
        } elseif (filter_var($id_pays, FILTER_VALIDATE_INT) === false || filter_var($id_genre, FILTER_VALIDATE_INT) === false) {
            $error = "Le pays ou le genre sélectionné est invalide.";
        } else {
            try {
                // Le pays et le genre choisis doivent exister dans la base
                $checkPays = $connexion->prepare("SELECT COUNT(*) FROM PAYS WHERE id_pays = :id_pays");
                $checkPays->execute([':id_pays' => $id_pays]);
                $checkGenre = $connexion->prepare("SELECT COUNT(*) FROM GENRE WHERE id_genre = :id_genre");
                $checkGenre->execute([':id_genre' => $id_genre]);

                if ($checkPays->fetchColumn() == 0) {
                    $error = "Le pays sélectionné n'existe pas.";
                } elseif ($checkGenre->fetchColumn() == 0) {
                    $error = "Le genre sélectionné n'existe pas.";
                } else {

                    // 4. Contrôle du doublon : un AUTRE athlète ne doit pas avoir les mêmes nom, prénom et pays
                    $queryCheck = "SELECT id_athlete FROM ATHLETE
                                   WHERE nom_athlete = :nom_athlete AND prenom_athlete = :prenom_athlete
                                   AND id_pays = :id_pays AND id_athlete <> :id_athlete";
                    $statementCheck = $connexion->prepare($queryCheck);
                    $statementCheck->execute([
                        ':nom_athlete' => $nom_athlete,
                        ':prenom_athlete' => $prenom_athlete,
                        ':id_pays' => $id_pays,
                        ':id_athlete' => $id_athlete
                    ]);

                    if ($statementCheck->rowCount() > 0) {
                        $error = "Cet athlète existe déjà (même nom, prénom et pays).";
                    } else {
                        // 5. Mise à jour avec une requête préparée
                        $query = "UPDATE ATHLETE
                                  SET nom_athlete = :nom_athlete, prenom_athlete = :prenom_athlete,
                                      id_pays = :id_pays, id_genre = :id_genre
                                  WHERE id_athlete = :id_athlete";
                        $statement = $connexion->prepare($query);
                        $statement->execute([
                            ':nom_athlete' => $nom_athlete,
                            ':prenom_athlete' => $prenom_athlete,
                            ':id_pays' => $id_pays,
                            ':id_genre' => $id_genre,
                            ':id_athlete' => $id_athlete
                        ]);

                        $_SESSION['success'] = "L'athlète a été modifié avec succès.";
                        header("Location: manage-athletes.php");
                        exit();
                    }
                }
            } catch (PDOException $e) {
                error_log("Erreur PDO : " . $e->getMessage());
                $error = "Erreur lors de la modification de l'athlète.";
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
    <title>Modifier un Athlète - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Modifier un Athlète</h1>

        <?php
        if ($error !== "") {
            echo '<p style="color: red;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        ?>

        <form action="modify-athlete.php?id_athlete=<?php echo $id_athlete; ?>" method="post"
            onsubmit="return confirm('Êtes-vous sûr de vouloir modifier cet athlète ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="nom_athlete">Nom :</label>
            <input type="text" name="nom_athlete" id="nom_athlete" maxlength="255" value="<?php echo htmlspecialchars($nom_athlete, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="prenom_athlete">Prénom :</label>
            <input type="text" name="prenom_athlete" id="prenom_athlete" maxlength="255" value="<?php echo htmlspecialchars($prenom_athlete, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="id_pays">Pays :</label>
            <select name="id_pays" id="id_pays" style="width: 100%; padding: 12px; margin-bottom: 18px; box-sizing: border-box; border: 2px solid #e5e0d0; border-radius: 8px; font-size: 1em; background-color: #ffffff;" required>
                <option value="">-- Choisir un pays --</option>
                <?php foreach ($pays as $p): ?>
                    <option value="<?php echo (int) $p['id_pays']; ?>" <?php echo ((string) $id_pays === (string) $p['id_pays']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['nom_pays'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="id_genre">Genre :</label>
            <select name="id_genre" id="id_genre" style="width: 100%; padding: 12px; margin-bottom: 18px; box-sizing: border-box; border: 2px solid #e5e0d0; border-radius: 8px; font-size: 1em; background-color: #ffffff;" required>
                <option value="">-- Choisir un genre --</option>
                <?php foreach ($genres as $g): ?>
                    <option value="<?php echo (int) $g['id_genre']; ?>" <?php echo ((string) $id_genre === (string) $g['id_genre']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g['nom_genre'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="submit" value="Modifier l'Athlète">
        </form>

        <p class="paragraph-link">
            <a class="link-home" href="manage-athletes.php">Retour à la gestion des athlètes</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>
</body>

</html>
