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

// Listes pour les menus déroulants (sports et lieux), lues dans la base
try {
    $sports = $connexion->query("SELECT id_sport, nom_sport FROM SPORT ORDER BY nom_sport")->fetchAll(PDO::FETCH_ASSOC);
    $lieux = $connexion->query("SELECT id_lieu, nom_lieu FROM LIEU ORDER BY nom_lieu")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO : " . $e->getMessage());
    $_SESSION['error'] = "Erreur de base de données.";
    header("Location: manage-events.php");
    exit();
}

// Récupération et validation de l'identifiant fourni dans l'URL
$id_epreuve = filter_input(INPUT_GET, 'id_epreuve', FILTER_VALIDATE_INT);
if ($id_epreuve === false || $id_epreuve === null || $id_epreuve < 1) {
    $_SESSION['error'] = "Identifiant de l'épreuve invalide.";
    header("Location: manage-events.php");
    exit();
}

// Lecture de l'épreuve à modifier (requête préparée)
try {
    $querySelect = "SELECT date_epreuve, heure_epreuve, id_lieu, id_sport FROM EPREUVE WHERE id_epreuve = :id_epreuve";
    $statementSelect = $connexion->prepare($querySelect);
    $statementSelect->execute([':id_epreuve' => $id_epreuve]);
    $row = $statementSelect->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO : " . $e->getMessage());
    $_SESSION['error'] = "Erreur de base de données.";
    header("Location: manage-events.php");
    exit();
}

if (!$row) {
    $_SESSION['error'] = "Épreuve non trouvée.";
    header("Location: manage-events.php");
    exit();
}

// Valeurs affichées dans le formulaire : celles de la base, puis celles saisies en cas d'erreur
// (l'heure est coupée à HH:MM pour le champ de type "time")
$date_epreuve = (string) $row['date_epreuve'];
$heure_epreuve = substr((string) $row['heure_epreuve'], 0, 5);
$id_sport = (string) $row['id_sport'];
$id_lieu = (string) $row['id_lieu'];
$error = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        // 2. Récupération des données
        $date_epreuve = trim($_POST['date_epreuve'] ?? '');
        $heure_epreuve = trim($_POST['heure_epreuve'] ?? '');
        $id_sport = trim($_POST['id_sport'] ?? '');
        $id_lieu = trim($_POST['id_lieu'] ?? '');

        // 3. Validation des données
        // La date (AAAA-MM-JJ, sans 31 février) et l'heure (HH:MM) sont contrôlées
        // par des fonctions de src/fonctions.php, testées avec PHPUnit
        $dateValide = validerDate($date_epreuve);
        $heureValide = validerHeure($heure_epreuve);

        if ($date_epreuve === '' || $heure_epreuve === '' || $id_sport === '' || $id_lieu === '') {
            $error = "Tous les champs sont obligatoires.";
        } elseif (!$dateValide) {
            $error = "La date est invalide.";
        } elseif (!$heureValide) {
            $error = "L'heure est invalide.";
        } elseif (filter_var($id_sport, FILTER_VALIDATE_INT) === false || filter_var($id_lieu, FILTER_VALIDATE_INT) === false) {
            $error = "Le sport ou le lieu sélectionné est invalide.";
        } else {
            try {
                // Le sport et le lieu choisis doivent exister dans la base
                $checkSport = $connexion->prepare("SELECT COUNT(*) FROM SPORT WHERE id_sport = :id_sport");
                $checkSport->execute([':id_sport' => $id_sport]);
                $checkLieu = $connexion->prepare("SELECT COUNT(*) FROM LIEU WHERE id_lieu = :id_lieu");
                $checkLieu->execute([':id_lieu' => $id_lieu]);

                if ($checkSport->fetchColumn() == 0) {
                    $error = "Le sport sélectionné n'existe pas.";
                } elseif ($checkLieu->fetchColumn() == 0) {
                    $error = "Le lieu sélectionné n'existe pas.";
                } else {

                    // 4. Contrôle du doublon : une AUTRE épreuve ne doit pas avoir les mêmes sport, date, heure et lieu
                    $queryCheck = "SELECT id_epreuve FROM EPREUVE
                                   WHERE id_sport = :id_sport AND id_lieu = :id_lieu
                                   AND date_epreuve = :date_epreuve AND heure_epreuve = :heure_epreuve
                                   AND id_epreuve <> :id_epreuve";
                    $statementCheck = $connexion->prepare($queryCheck);
                    $statementCheck->execute([
                        ':id_sport' => $id_sport,
                        ':id_lieu' => $id_lieu,
                        ':date_epreuve' => $date_epreuve,
                        ':heure_epreuve' => $heure_epreuve,
                        ':id_epreuve' => $id_epreuve
                    ]);

                    if ($statementCheck->rowCount() > 0) {
                        $error = "Cette épreuve existe déjà (même sport, date, heure et lieu).";
                    } else {
                        // 5. Mise à jour avec une requête préparée
                        $query = "UPDATE EPREUVE
                                  SET date_epreuve = :date_epreuve, heure_epreuve = :heure_epreuve,
                                      id_lieu = :id_lieu, id_sport = :id_sport
                                  WHERE id_epreuve = :id_epreuve";
                        $statement = $connexion->prepare($query);
                        $statement->execute([
                            ':date_epreuve' => $date_epreuve,
                            ':heure_epreuve' => $heure_epreuve,
                            ':id_lieu' => $id_lieu,
                            ':id_sport' => $id_sport,
                            ':id_epreuve' => $id_epreuve
                        ]);

                        $_SESSION['success'] = "L'épreuve a été modifiée avec succès.";
                        header("Location: manage-events.php");
                        exit();
                    }
                }
            } catch (PDOException $e) {
                error_log("Erreur PDO : " . $e->getMessage());
                $error = "Erreur lors de la modification de l'épreuve.";
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
    <title>Modifier une Épreuve - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Modifier une Épreuve</h1>

        <?php
        if ($error !== "") {
            echo '<p style="color: red;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        ?>

        <form action="modify-event.php?id_epreuve=<?php echo $id_epreuve; ?>" method="post"
            onsubmit="return confirm('Êtes-vous sûr de vouloir modifier cette épreuve ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="id_sport">Sport :</label>
            <select name="id_sport" id="id_sport" style="width: 100%; padding: 12px; margin-bottom: 18px; box-sizing: border-box; border: 2px solid #e5e0d0; border-radius: 8px; font-size: 1em; background-color: #ffffff;" required>
                <option value="">-- Choisir un sport --</option>
                <?php foreach ($sports as $sport): ?>
                    <option value="<?php echo (int) $sport['id_sport']; ?>" <?php echo ((string) $id_sport === (string) $sport['id_sport']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sport['nom_sport'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="date_epreuve">Date :</label>
            <input type="date" name="date_epreuve" id="date_epreuve" value="<?php echo htmlspecialchars($date_epreuve, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="heure_epreuve">Heure :</label>
            <input type="time" name="heure_epreuve" id="heure_epreuve" value="<?php echo htmlspecialchars($heure_epreuve, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="id_lieu">Lieu :</label>
            <select name="id_lieu" id="id_lieu" style="width: 100%; padding: 12px; margin-bottom: 18px; box-sizing: border-box; border: 2px solid #e5e0d0; border-radius: 8px; font-size: 1em; background-color: #ffffff;" required>
                <option value="">-- Choisir un lieu --</option>
                <?php foreach ($lieux as $lieu): ?>
                    <option value="<?php echo (int) $lieu['id_lieu']; ?>" <?php echo ((string) $id_lieu === (string) $lieu['id_lieu']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lieu['nom_lieu'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="submit" value="Modifier l'Épreuve">
        </form>

        <p class="paragraph-link">
            <a class="link-home" href="manage-events.php">Retour au calendrier</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>
</body>

</html>
