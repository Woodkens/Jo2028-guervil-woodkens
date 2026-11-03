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

// Valeurs du formulaire (réaffichées en cas d'erreur) et message d'erreur éventuel
$id_athlete_form = "";
$id_epreuve_form = "";
$resultat = "";
$error = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        // 2. Récupération des données (trim supprime les espaces inutiles).
        //    Les données sont enregistrées telles quelles et échappées à l'affichage (protection XSS).
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

                    // 4. Contrôle du doublon : un athlète n'a qu'un seul résultat par épreuve
                    $queryCheck = "SELECT id_athlete FROM PARTICIPER WHERE id_athlete = :id_athlete AND id_epreuve = :id_epreuve";
                    $statementCheck = $connexion->prepare($queryCheck);
                    $statementCheck->execute([
                        ':id_athlete' => $id_athlete_form,
                        ':id_epreuve' => $id_epreuve_form
                    ]);

                    if ($statementCheck->rowCount() > 0) {
                        $error = "Cet athlète a déjà un résultat pour cette épreuve.";
                    } else {
                        // 5. Insertion avec une requête préparée (protection contre les injections SQL)
                        $query = "INSERT INTO PARTICIPER (id_athlete, id_epreuve, resultat)
                                  VALUES (:id_athlete, :id_epreuve, :resultat)";
                        $statement = $connexion->prepare($query);
                        $statement->execute([
                            ':id_athlete' => $id_athlete_form,
                            ':id_epreuve' => $id_epreuve_form,
                            ':resultat' => $resultat
                        ]);

                        $_SESSION['success'] = "Le résultat a été ajouté avec succès.";
                        header("Location: manage-results.php");
                        exit();
                    }
                }
            } catch (PDOException $e) {
                error_log("Erreur PDO : " . $e->getMessage());
                $error = "Erreur lors de l'ajout du résultat.";
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
    <title>Ajouter un Résultat - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Ajouter un Résultat</h1>

        <?php
        if ($error !== "") {
            echo '<p style="color: red;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        ?>

        <form action="add-result.php" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir ajouter ce résultat ?')">
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

            <input type="submit" value="Ajouter le Résultat">
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
