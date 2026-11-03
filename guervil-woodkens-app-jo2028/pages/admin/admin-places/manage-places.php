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

// Connexion à la base de données (variable $connexion)
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
    <title>Liste des Lieux - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Liste des Lieux</h1>

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
            <button onclick="openAddForm()">Ajouter un Lieu</button>
        </div>

        <!-- Tableau -->
        <?php
        try {
            // Requête préparée
            $query = "SELECT id_lieu, nom_lieu, adresse_lieu, cp_lieu, ville_lieu FROM LIEU ORDER BY nom_lieu";
            $statement = $connexion->prepare($query);
            $statement->execute();

            if ($statement->rowCount() > 0) {
                echo "<table><tr><th>Nom</th><th>Adresse</th><th>Code postal</th><th>Ville</th><th>Modifier</th><th>Supprimer</th></tr>";

                while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                    $id = (int) $row['id_lieu'];
                    echo "<tr>";
                    // htmlspecialchars protège contre les attaques XSS à l'affichage
                    echo "<td>" . htmlspecialchars($row['nom_lieu'], ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['adresse_lieu'], ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['cp_lieu'], ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['ville_lieu'], ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td><button onclick='openModifyForm($id)'>Modifier</button></td>";
                    echo "<td><button onclick='deleteConfirmation($id)'>Supprimer</button></td>";
                    echo "</tr>";
                }

                echo "</table>";
            } else {
                echo "<p>Aucun lieu trouvé.</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Erreur : impossible de récupérer la liste.</p>";
            error_log("Erreur PDO : " . $e->getMessage());
        }
        ?>

        <!-- Formulaire caché : la suppression se fait en POST avec le jeton CSRF -->
        <form id="delete-form" action="delete-place.php" method="post" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id_lieu" id="delete-id" value="">
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
            window.location.href = 'add-place.php';
        }

        function openModifyForm(id_lieu) {
            window.location.href = 'modify-place.php?id_lieu=' + id_lieu;
        }

        function deleteConfirmation(id_lieu) {
            if (confirm("Êtes-vous sûr de vouloir supprimer ce lieu ?")) {
                document.getElementById('delete-id').value = id_lieu;
                document.getElementById('delete-form').submit();
            }
        }
    </script>
</body>

</html>
