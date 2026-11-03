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

// Listes pour les menus déroulants (athlètes et épreuves), lues dans la base
try {
    $athletes = $connexion->query("SELECT a.id_athlete, a.nom_athlete, a.prenom_athlete, p.nom_pays
                                   FROM ATHLETE a
                                   INNER JOIN PAYS p ON p.id_pays = a.id_pays
                                   ORDER BY a.nom_athlete, a.prenom_athlete")->fetchAll(PDO::FETCH_ASSOC);
    $epreuves = $connexion->query("SELECT e.id_epreuve, s.nom_sport, l.nom_lieu,
                                          DATE_FORMAT(e.date_epreuve, '%d/%m/%Y') AS date_fr,
                                          DATE_FORMAT(e.heure_epreuve, '%H:%i') AS heure_fr
                                   FROM EPREUVE e
                                   INNER JOIN SPORT s ON s.id_sport = e.id_sport
                                   INNER JOIN LIEU l ON l.id_lieu = e.id_lieu
                                   ORDER BY e.date_epreuve, e.heure_epreuve")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO : " . $e->getMessage());
    $_SESSION['error'] = "Erreur de base de données.";
    header("Location: manage-results.php");
    exit();
}

// Un résultat est identifié par le couple (athlète, épreuve) fourni dans l'URL
$id_athlete = filter_input(INPUT_GET, 'id_athlete', FILTER_VALIDATE_INT);
$id_epreuve = filter_input(INPUT_GET, 'id_epreuve', FILTER_VALIDATE_INT);
if ($id_athlete === false || $id_athlete === null || $id_athlete < 1
    || $id_epreuve === false || $id_epreuve === null || $id_epreuve < 1) {
    $_SESSION['error'] = "Identifiants du résultat invalides.";
    header("Location: manage-results.php");
    exit();
}

// Lecture du résultat à modifier (requête préparée)
try {
    $querySelect = "SELECT resultat FROM PARTICIPER WHERE id_athlete = :id_athlete AND id_epreuve = :id_epreuve";
    $statementSelect = $connexion->prepare($querySelect);
    $statementSelect->execute([':id_athlete' => $id_athlete, ':id_epreuve' => $id_epreuve]);
    $row = $statementSelect->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO : " . $e->getMessage());
    $_SESSION['error'] = "Erreur de base de données.";
    header("Location: manage-results.php");
    exit();
}

if (!$row) {
    $_SESSION['error'] = "Résultat non trouvé.";
    header("Location: manage-results.php");
    exit();
}

// Valeurs affichées dans le formulaire : celles de la base, puis celles saisies en cas d'erreur
$id_athlete_form = (string) $id_athlete;
$id_epreuve_form = (string) $id_epreuve;
$resultat = (string) $row['resultat'];
$error = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        // 2. Récupération des données
        $id_athlete_form = trim($_POST['id_athlete_form'] ?? '');
        $id_epreuve_form = trim($_POST['id_epreuve_form'] ?? '');
        $resultat = trim($_POST['resultat'] ?? '');

        // 3. Validation des données
        if ($id_athlete_form === '' || $id_epreuve_form === '' || $resultat === '') {
            $error = "Tous les champs sont obligatoires.";
        } elseif (mb_strlen($resultat) > 100) {
            $error = "Le résultat ne doit pas dépasser 100 caractères.";
        } elseif (filter_var($id_athlete_form, FILTER_VALIDATE_INT) === false || filter_var($id_epreuve_form, FILTER_VALIDATE_INT) === false) {
            $error = "L'athlète ou l'épreuve sélectionné est invalide.";
        } else {
            try {
                // L'athlète et l'épreuve choisis doivent exister dans la base
                $checkAthlete = $connexion->prepare("SELECT COUNT(*) FROM ATHLETE WHERE id_athlete = :id_athlete");
                $checkAthlete->execute([':id_athlete' => $id_athlete_form]);
                $checkEpreuve = $connexion->prepare("SELECT COUNT(*) FROM EPREUVE WHERE id_epreuve = :id_epreuve");
                $checkEpreuve->execute([':id_epreuve' => $id_epreuve_form]);

                if ($checkAthlete->fetchColumn() == 0) {
                    $error = "L'athlète sélectionné n'existe pas.";
                } elseif ($checkEpreuve->fetchColumn() == 0) {
                    $error = "L'épreuve sélectionnée n'existe pas.";
                } else {

                    // 4. Contrôle du doublon : le nouveau couple (athlète, épreuve) ne doit pas déjà exister,
                    //    sauf s'il s'agit du résultat en cours de modification
                    $changeDeCouple = ((int) $id_athlete_form !== $id_athlete || (int) $id_epreuve_form !== $id_epreuve);
                    $queryCheck = "SELECT id_athlete FROM PARTICIPER WHERE id_athlete = :id_athlete AND id_epreuve = :id_epreuve";
                    $statementCheck = $connexion->prepare($queryCheck);
                    $statementCheck->execute([
                        ':id_athlete' => $id_athlete_form,
                        ':id_epreuve' => $id_epreuve_form
                    ]);

                    if ($changeDeCouple && $statementCheck->rowCount() > 0) {
                        $error = "Cet athlète a déjà un résultat pour cette épreuve.";
                    } else {
                        // 5. Mise à jour avec une requête préparée
                        $query = "UPDATE PARTICIPER
                                  SET id_athlete = :new_athlete, id_epreuve = :new_epreuve, resultat = :resultat
                                  WHERE id_athlete = :old_athlete AND id_epreuve = :old_epreuve";
                        $statement = $connexion->prepare($query);
                        $statement->execute([
                            ':new_athlete' => $id_athlete_form,
                            ':new_epreuve' => $id_epreuve_form,
                            ':resultat' => $resultat,
                            ':old_athlete' => $id_athlete,
                            ':old_epreuve' => $id_epreuve
                        ]);

                        $_SESSION['success'] = "Le résultat a été modifié avec succès.";
                        header("Location: manage-results.php");
                        exit();
                    }
                }
            } catch (PDOException $e) {
                error_log("Erreur PDO : " . $e->getMessage());
                $error = "Erreur lors de la modification du résultat.";
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
    <title>Modifier un Résultat - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Modifier un Résultat</h1>

        <?php
        if ($error !== "") {
            echo '<p style="color: red;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        ?>

        <form action="modify-result.php?id_athlete=<?php echo $id_athlete; ?>&amp;id_epreuve=<?php echo $id_epreuve; ?>" method="post"
            onsubmit="return confirm('Êtes-vous sûr de vouloir modifier ce résultat ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="id_athlete_form">Athlète :</label>
            <select name="id_athlete_form" id="id_athlete_form" style="width: 100%; padding: 12px; margin-bottom: 18px; box-sizing: border-box; border: 2px solid #e5e0d0; border-radius: 8px; font-size: 1em; background-color: #ffffff;" required>
                <option value="">-- Choisir un athlète --</option>
                <?php foreach ($athletes as $a): ?>
                    <option value="<?php echo (int) $a['id_athlete']; ?>" <?php echo ((string) $id_athlete_form === (string) $a['id_athlete']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($a['nom_athlete'] . ' ' . $a['prenom_athlete'] . ' (' . $a['nom_pays'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="id_epreuve_form">Épreuve :</label>
            <select name="id_epreuve_form" id="id_epreuve_form" style="width: 100%; padding: 12px; margin-bottom: 18px; box-sizing: border-box; border: 2px solid #e5e0d0; border-radius: 8px; font-size: 1em; background-color: #ffffff;" required>
                <option value="">-- Choisir une épreuve --</option>
                <?php foreach ($epreuves as $e): ?>
                    <option value="<?php echo (int) $e['id_epreuve']; ?>" <?php echo ((string) $id_epreuve_form === (string) $e['id_epreuve']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['nom_sport'] . ' - ' . $e['date_fr'] . ' ' . $e['heure_fr'] . ' - ' . $e['nom_lieu'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="resultat">Résultat :</label>
            <input type="text" name="resultat" id="resultat" maxlength="100" placeholder="Ex. : 10.5, 2:05.3, Vainqueur"
                value="<?php echo htmlspecialchars($resultat, ENT_QUOTES, 'UTF-8'); ?>" required>

            <input type="submit" value="Modifier le Résultat">
        </form>

        <p class="paragraph-link">
            <a class="link-home" href="manage-results.php">Retour à la gestion des résultats</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>
</body>

</html>
