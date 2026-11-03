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

// Valeurs du formulaire (réaffichées en cas d'erreur) et message d'erreur éventuel
$nom_athlete = "";
$prenom_athlete = "";
$id_pays = "";
$id_genre = "";
$error = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        // 2. Récupération des données (trim supprime les espaces inutiles).
        //    Les données sont enregistrées telles quelles et échappées à l'affichage (protection XSS).
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

                    // 4. Contrôle du doublon : même nom, même prénom et même pays
                    $queryCheck = "SELECT id_athlete FROM ATHLETE
                                   WHERE nom_athlete = :nom_athlete AND prenom_athlete = :prenom_athlete
                                   AND id_pays = :id_pays";
                    $statementCheck = $connexion->prepare($queryCheck);
                    $statementCheck->execute([
                        ':nom_athlete' => $nom_athlete,
                        ':prenom_athlete' => $prenom_athlete,
                        ':id_pays' => $id_pays
                    ]);

                    if ($statementCheck->rowCount() > 0) {
                        $error = "Cet athlète existe déjà (même nom, prénom et pays).";
                    } else {
                        // 5. Insertion avec une requête préparée (protection contre les injections SQL)
                        $query = "INSERT INTO ATHLETE (nom_athlete, prenom_athlete, id_pays, id_genre)
                                  VALUES (:nom_athlete, :prenom_athlete, :id_pays, :id_genre)";
                        $statement = $connexion->prepare($query);
                        $statement->execute([
                            ':nom_athlete' => $nom_athlete,
                            ':prenom_athlete' => $prenom_athlete,
                            ':id_pays' => $id_pays,
                            ':id_genre' => $id_genre
                        ]);

                        $_SESSION['success'] = "L'athlète a été ajouté avec succès.";
                        header("Location: manage-athletes.php");
                        exit();
                    }
                }
            } catch (PDOException $e) {
                error_log("Erreur PDO : " . $e->getMessage());
                $error = "Erreur lors de l'ajout de l'athlète.";
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
    <title>Ajouter un Athlète - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Ajouter un Athlète</h1>

        <?php
        if ($error !== "") {
            echo '<p style="color: red;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        ?>

        <form action="add-athlete.php" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir ajouter cet athlète ?')">
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

            <input type="submit" value="Ajouter l'Athlète">
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
