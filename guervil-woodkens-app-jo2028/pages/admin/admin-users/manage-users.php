<?php
session_start();

// Vérifie que l'utilisateur est connecté, sinon retour à l'accueil du site
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// Génère un jeton CSRF s'il n'existe pas encore (utilisé pour la suppression)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Connexion à la base de données (variable $connexion)
require_once("../../../database/database.php");
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
    <title>Liste des Administrateurs - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Liste des Administrateurs</h1>

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
            <button onclick="openAddUserForm()">Ajouter un Administrateur</button>
        </div>

        <!-- Tableau des administrateurs -->
        <?php
        try {
            // Requête préparée : le mot de passe haché n'est volontairement jamais lu ni affiché
            $query = "SELECT id_admin, nom_admin, prenom_admin, login FROM ADMINISTRATEUR ORDER BY nom_admin, prenom_admin";
            $statement = $connexion->prepare($query);
            $statement->execute();

            if ($statement->rowCount() > 0) {
                echo "<table><tr><th>Nom</th><th>Prénom</th><th>Login</th><th>Modifier</th><th>Supprimer</th></tr>";

                while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                    $id = (int) $row['id_admin'];
                    echo "<tr>";
                    // htmlspecialchars protège contre les attaques XSS à l'affichage
                    echo "<td>" . htmlspecialchars($row['nom_admin'], ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['prenom_admin'], ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td>" . htmlspecialchars($row['login'], ENT_QUOTES, 'UTF-8') . "</td>";
                    echo "<td><button onclick='openModifyUserForm($id)'>Modifier</button></td>";

                    // Un administrateur ne peut pas supprimer son propre compte
                    if ($id === (int) $_SESSION['id_admin']) {
                        echo "<td><button disabled title='Vous ne pouvez pas supprimer votre propre compte'>Supprimer</button></td>";
                    } else {
                        echo "<td><button onclick='deleteUserConfirmation($id)'>Supprimer</button></td>";
                    }
                    echo "</tr>";
                }

                echo "</table>";
            } else {
                echo "<p>Aucun administrateur trouvé.</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Erreur : impossible de récupérer les administrateurs.</p>";
            error_log("Erreur PDO : " . $e->getMessage());
        }
        ?>

        <!-- Formulaire caché : la suppression se fait en POST avec le jeton CSRF -->
        <form id="delete-form" action="delete-user.php" method="post" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id_admin" id="delete-id" value="">
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
        function openAddUserForm() {
            window.location.href = 'add-user.php';
        }

        function openModifyUserForm(id_admin) {
            window.location.href = 'modify-user.php?id_admin=' + id_admin;
        }

        function deleteUserConfirmation(id_admin) {
            if (confirm("Êtes-vous sûr de vouloir supprimer cet administrateur ?")) {
                document.getElementById('delete-id').value = id_admin;
                document.getElementById('delete-form').submit();
            }
        }
    </script>
</body>

</html>
