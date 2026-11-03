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
$id_lieu = filter_input(INPUT_GET, 'id_lieu', FILTER_VALIDATE_INT);
if ($id_lieu === false || $id_lieu === null || $id_lieu < 1) {
    $_SESSION['error'] = "Identifiant invalide.";
    header("Location: manage-places.php");
    exit();
}

// Lecture de l'élément à modifier (requête préparée)
try {
    $querySelect = "SELECT nom_lieu, adresse_lieu, cp_lieu, ville_lieu FROM LIEU WHERE id_lieu = :id_lieu";
    $statementSelect = $connexion->prepare($querySelect);
    $statementSelect->execute([':id_lieu' => $id_lieu]);
    $row = $statementSelect->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO : " . $e->getMessage());
    $_SESSION['error'] = "Erreur de base de données.";
    header("Location: manage-places.php");
    exit();
}

if (!$row) {
    $_SESSION['error'] = "Lieu non trouvé.";
    header("Location: manage-places.php");
    exit();
}

// Valeurs affichées dans le formulaire : celles de la base, puis celles saisies en cas d'erreur
$nom_lieu = $row['nom_lieu'];
$adresse_lieu = $row['adresse_lieu'];
$cp_lieu = $row['cp_lieu'];
$ville_lieu = $row['ville_lieu'];
$error = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        // 2. Récupération des données
        $nom_lieu = trim($_POST['nom_lieu'] ?? '');
        $adresse_lieu = trim($_POST['adresse_lieu'] ?? '');
        $cp_lieu = trim($_POST['cp_lieu'] ?? '');
        $ville_lieu = trim($_POST['ville_lieu'] ?? '');

        // 3. Validation des données
        if ($nom_lieu === '' || $adresse_lieu === '' || $cp_lieu === '' || $ville_lieu === '') {
            $error = "Tous les champs sont obligatoires.";
        } elseif (mb_strlen($nom_lieu) > 255) {
            $error = "Le champ « Nom du lieu » ne doit pas dépasser 255 caractères.";
        } elseif (mb_strlen($adresse_lieu) > 255) {
            $error = "Le champ « Adresse » ne doit pas dépasser 255 caractères.";
        } elseif (mb_strlen($cp_lieu) > 5) {
            $error = "Le champ « Code postal » ne doit pas dépasser 5 caractères.";
        } elseif (!preg_match('/^[0-9]{5}$/', $cp_lieu)) {
            $error = "Le code postal doit contenir exactement 5 chiffres.";
        } elseif (mb_strlen($ville_lieu) > 255) {
            $error = "Le champ « Ville » ne doit pas dépasser 255 caractères.";
        } else {
            try {
                // 4. Contrôle du doublon : le nom ne doit pas appartenir à un AUTRE enregistrement
                $queryCheck = "SELECT id_lieu FROM LIEU WHERE nom_lieu = :nom_lieu AND id_lieu <> :id_lieu";
                $statementCheck = $connexion->prepare($queryCheck);
                $statementCheck->execute([':nom_lieu' => $nom_lieu, ':id_lieu' => $id_lieu]);

                if ($statementCheck->rowCount() > 0) {
                    $error = "Ce lieu existe déjà.";
                } else {
                    // 5. Mise à jour avec une requête préparée
                    $query = "UPDATE LIEU SET nom_lieu = :nom_lieu, adresse_lieu = :adresse_lieu, cp_lieu = :cp_lieu, ville_lieu = :ville_lieu WHERE id_lieu = :id_lieu";
                    $statement = $connexion->prepare($query);
                    $statement->execute([
                        ':nom_lieu' => $nom_lieu,
                        ':adresse_lieu' => $adresse_lieu,
                        ':cp_lieu' => $cp_lieu,
                        ':ville_lieu' => $ville_lieu,
                        ':id_lieu' => $id_lieu
                    ]);

                    $_SESSION['success'] = "Le lieu a été modifié avec succès.";
                    header("Location: manage-places.php");
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
    <title>Modifier un Lieu - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Modifier un Lieu</h1>

        <?php
        if ($error !== "") {
            echo '<p style="color: red;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        ?>

        <form action="modify-place.php?id_lieu=<?php echo $id_lieu; ?>" method="post"
            onsubmit="return confirm('Êtes-vous sûr de vouloir modifier ce lieu ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="nom_lieu">Nom du lieu :</label>
            <input type="text" name="nom_lieu" id="nom_lieu" maxlength="255" value="<?php echo htmlspecialchars($nom_lieu, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="adresse_lieu">Adresse :</label>
            <input type="text" name="adresse_lieu" id="adresse_lieu" maxlength="255" value="<?php echo htmlspecialchars($adresse_lieu, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="cp_lieu">Code postal :</label>
            <input type="text" name="cp_lieu" id="cp_lieu" maxlength="5" pattern="[0-9]{5}" inputmode="numeric" value="<?php echo htmlspecialchars($cp_lieu, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="ville_lieu">Ville :</label>
            <input type="text" name="ville_lieu" id="ville_lieu" maxlength="255" value="<?php echo htmlspecialchars($ville_lieu, ENT_QUOTES, 'UTF-8'); ?>" required>

            <input type="submit" value="Modifier le Lieu">
        </form>

        <p class="paragraph-link">
            <a class="link-home" href="manage-places.php">Retour à la gestion des lieux</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>
</body>

</html>
