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
    <title>Liste des Épreuves - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Liste des Épreuves</h1>

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
            <button onclick="openAddForm()">Ajouter une Épreuve</button>
        </div>

        <!-- Tableau du calendrier -->
        <?php
        try {
            // Requête préparée avec jointures : le sport et le lieu sont lus dans leurs tables
            $query = "SELECT e.id_epreuve, s.nom_sport, l.nom_lieu,
                             DATE_FORMAT(e.date_epreuve, '%d/%m/%Y') AS date_fr,
                             DATE_FORMAT(e.heure_epreuve, '%H:%i') AS heure_fr
                      FROM EPREUVE e
                      INNER JOIN SPORT s ON s.id_sport = e.id_sport
                      INNER JOIN LIEU l ON l.id_lieu = e.id_lieu
                      ORDER BY e.date_epreuve, e.heure_epreuve";
            $statement = $connexion->prepare($query);
            $statement->execute();

            if ($statement->rowCount() > 0) {
                echo "<table><tr><th>Sport</th><th>Date</th><th>Heure</th><th>Lieu</th><th>Modifier</th><th>Supprimer</th></tr>";

                while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                    $id = (int) $row['id_epreuve'];
                    echo "<tr>";
                    // htmlspecialchars protège contre les attaques XSS à l'affichage
                    echo "<td>" . htmlspecialchars($row['nom_sport'], ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['date_fr'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['heure_fr'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['nom_lieu'], ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td><button onclick='openModifyForm($id)'>Modifier</button></td>";
                    echo "<td><button onclick='deleteConfirmation($id)'>Supprimer</button></td>";
                    echo "</tr>";
                }

                echo "</table>";
            } else {
                echo "<p>Aucune épreuve trouvée.</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Erreur : impossible de récupérer le calendrier.</p>";
            error_log("Erreur PDO : " . $e->getMessage());
        }
        ?>

        <!-- Formulaire caché : la suppression se fait en POST avec le jeton CSRF -->
        <form id="delete-form" action="delete-event.php" method="post" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id_epreuve" id="delete-id" value="">
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
            window.location.href = 'add-event.php';
        }

        function openModifyForm(id_epreuve) {
            window.location.href = 'modify-event.php?id_epreuve=' + id_epreuve;
        }

        function deleteConfirmation(id_epreuve) {
            if (confirm("Êtes-vous sûr de vouloir supprimer cette épreuve ?")) {
                document.getElementById('delete-id').value = id_epreuve;
                document.getElementById('delete-form').submit();
            }
        }
    </script>
</body>

</html>
