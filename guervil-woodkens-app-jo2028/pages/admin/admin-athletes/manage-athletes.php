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
    <title>Liste des Athlètes - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Liste des Athlètes</h1>

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
            <button onclick="openAddForm()">Ajouter un Athlète</button>
        </div>

        <!-- Tableau des athlètes -->
        <?php
        try {
            // Requête préparée avec jointures : le pays et le genre sont lus dans leurs tables
            $query = "SELECT a.id_athlete, a.nom_athlete, a.prenom_athlete, p.nom_pays, g.nom_genre
                      FROM ATHLETE a
                      INNER JOIN PAYS p ON p.id_pays = a.id_pays
                      INNER JOIN GENRE g ON g.id_genre = a.id_genre
                      ORDER BY a.nom_athlete, a.prenom_athlete";
            $statement = $connexion->prepare($query);
            $statement->execute();

            if ($statement->rowCount() > 0) {
                echo "<table><tr><th>Nom</th><th>Prénom</th><th>Pays</th><th>Genre</th><th>Modifier</th><th>Supprimer</th></tr>";

                while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                    $id = (int) $row['id_athlete'];
                    echo "<tr>";
                    // htmlspecialchars protège contre les attaques XSS à l'affichage
                    echo "<td>" . htmlspecialchars($row['nom_athlete'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['prenom_athlete'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['nom_pays'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['nom_genre'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td><button onclick='openModifyForm($id)'>Modifier</button></td>";
                    echo "<td><button onclick='deleteConfirmation($id)'>Supprimer</button></td>";
                    echo "</tr>";
                }

                echo "</table>";
            } else {
                echo "<p>Aucun athlète trouvé.</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Erreur : impossible de récupérer la liste des athlètes.</p>";
            error_log("Erreur PDO : " . $e->getMessage());
        }
        ?>

        <!-- Formulaire caché : la suppression se fait en POST avec le jeton CSRF -->
        <form id="delete-form" action="delete-athlete.php" method="post" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id_athlete" id="delete-id" value="">
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
            window.location.href = 'add-athlete.php';
        }

        function openModifyForm(id_athlete) {
            window.location.href = 'modify-athlete.php?id_athlete=' + id_athlete;
        }

        function deleteConfirmation(id_athlete) {
            if (confirm("Êtes-vous sûr de vouloir supprimer cet athlète ?")) {
                document.getElementById('delete-id').value = id_athlete;
                document.getElementById('delete-form').submit();
            }
        }
    </script>
</body>

</html>
