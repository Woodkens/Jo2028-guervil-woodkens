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
    <title>Liste des Résultats - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Liste des Résultats</h1>

        <?php
        // Affichage des messages (succès ou erreur) enregistrés avant la redirection
        if (isset($_SESSION['error'])) {
            echo '<p style="color: red;">' . htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') . '</p>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<p style="color: green;">' . htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') . '</p>';
            unset($_SESSION['success']);
        }
        ?>

        <div class="action-buttons">
            <button onclick="openAddForm()">Ajouter un Résultat</button>
        </div>

        <!-- Tableau des résultats -->
        <?php
        try {
            // Requête préparée avec jointures : épreuve (sport, date, heure), athlète et pays
            $query = "SELECT part.id_athlete, part.id_epreuve, part.resultat,
                             s.nom_sport,
                             DATE_FORMAT(e.date_epreuve, '%d/%m/%Y') AS date_fr,
                             DATE_FORMAT(e.heure_epreuve, '%H:%i') AS heure_fr,
                             a.nom_athlete, a.prenom_athlete, p.nom_pays
                      FROM PARTICIPER part
                      INNER JOIN EPREUVE e ON e.id_epreuve = part.id_epreuve
                      INNER JOIN SPORT s ON s.id_sport = e.id_sport
                      INNER JOIN ATHLETE a ON a.id_athlete = part.id_athlete
                      INNER JOIN PAYS p ON p.id_pays = a.id_pays
                      ORDER BY s.nom_sport, e.date_epreuve, a.nom_athlete, a.prenom_athlete";
            $statement = $connexion->prepare($query);
            $statement->execute();

            if ($statement->rowCount() > 0) {
                echo "<table><tr><th>Épreuve</th><th>Athlète</th><th>Pays</th><th>Résultat</th><th>Modifier</th><th>Supprimer</th></tr>";

                while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                    $id_athlete = (int) $row['id_athlete'];
                    $id_epreuve = (int) $row['id_epreuve'];
                    $epreuve = $row['nom_sport'] . " - " . $row['date_fr'] . " " . $row['heure_fr'];
                    $athlete = $row['nom_athlete'] . " " . $row['prenom_athlete'];

                    echo "<tr>";
                    // htmlspecialchars protège contre les attaques XSS à l'affichage
                    echo "<td>" . htmlspecialchars($epreuve, ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($athlete, ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['nom_pays'], ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['resultat'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td><button onclick='openModifyForm($id_athlete, $id_epreuve)'>Modifier</button></td>";
                    echo "<td><button onclick='deleteConfirmation($id_athlete, $id_epreuve)'>Supprimer</button></td>";
                    echo "</tr>";
                }

                echo "</table>";
            } else {
                echo "<p>Aucun résultat trouvé.</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Erreur : impossible de récupérer la liste des résultats.</p>";
            error_log("Erreur PDO : " . $e->getMessage());
        }
        ?>

        <!-- Formulaire caché : la suppression se fait en POST avec le jeton CSRF -->
        <form id="delete-form" action="delete-result.php" method="post" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id_athlete" id="delete-id-athlete" value="">
            <input type="hidden" name="id_epreuve" id="delete-id-epreuve" value="">
        </form>

        <p class="paragraph-link">
            <a class="link-home" href="../admin.php">Accueil administration</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>

    <script>
        function openAddForm() {
            window.location.href = 'add-result.php';
        }

        // Un résultat est identifié par le couple (athlète, épreuve)
        function openModifyForm(id_athlete, id_epreuve) {
            window.location.href = 'modify-result.php?id_athlete=' + id_athlete + '&id_epreuve=' + id_epreuve;
        }

        function deleteConfirmation(id_athlete, id_epreuve) {
            if (confirm("Êtes-vous sûr de vouloir supprimer ce résultat ?")) {
                document.getElementById('delete-id-athlete').value = id_athlete;
                document.getElementById('delete-id-epreuve').value = id_epreuve;
                document.getElementById('delete-form').submit();
            }
        }
    </script>
</body>

</html>
