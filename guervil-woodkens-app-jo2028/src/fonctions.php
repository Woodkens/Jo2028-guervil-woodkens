<?php
/**
 * Fonctions métier de l'application JO 2028.
 *
 * La logique (validation des données, contrôle des doublons, hachage des mots de passe,
 * accès à la base de données) est isolée ici, séparée de l'affichage, afin de pouvoir
 * être testée automatiquement avec PHPUnit (dossier tests/).
 *
 * Chaque fonction qui modifie la base renvoie un tableau :
 *   ['succes' => true|false, 'message' => 'texte à afficher à l'administrateur']
 *
 * Toutes les requêtes SQL sont préparées (protection contre les injections SQL).
 * L'échappement des données à l'affichage (protection XSS) reste fait dans les pages.
 */

// ---------------------------------------------------------------------------
// Utilitaire
// ---------------------------------------------------------------------------

/**
 * Construit la réponse standard des fonctions métier.
 */
function reponse(bool $succes, string $message): array
{
    return ['succes' => $succes, 'message' => $message];
}

// ---------------------------------------------------------------------------
// Validation des données
// ---------------------------------------------------------------------------

/**
 * Un login contient de 3 à 50 caractères : lettres, chiffres, point, tiret ou underscore.
 */
function validerLogin(string $login): bool
{
    return preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $login) === 1;
}

/**
 * Un mot de passe doit contenir au moins 8 caractères.
 */
function validerMotDePasse(string $motDePasse): bool
{
    return mb_strlen($motDePasse) >= 8;
}

/**
 * Une date doit être au format AAAA-MM-JJ et exister réellement (pas de 31 février).
 */
function validerDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

/**
 * Une heure doit être au format HH:MM et exister réellement (pas de 25:99).
 */
function validerHeure(string $heure): bool
{
    $h = DateTime::createFromFormat('H:i', $heure);
    return $h !== false && $h->format('H:i') === $heure;
}

// ---------------------------------------------------------------------------
// Administrateurs (table ADMINISTRATEUR)
// ---------------------------------------------------------------------------

/**
 * Ajoute un administrateur.
 * Le login doit être unique et le mot de passe est haché avec Bcrypt avant l'enregistrement.
 */
function ajouterAdministrateur(PDO $pdo, string $nom, string $prenom, string $login, string $motDePasse): array
{
    $nom = trim($nom);
    $prenom = trim($prenom);
    $login = trim($login);

    if ($nom === '' || $prenom === '' || $login === '' || $motDePasse === '') {
        return reponse(false, "Tous les champs sont obligatoires.");
    }
    if (mb_strlen($nom) > 255 || mb_strlen($prenom) > 255) {
        return reponse(false, "Le nom et le prénom ne doivent pas dépasser 255 caractères.");
    }
    if (!validerLogin($login)) {
        return reponse(false, "Le login doit contenir de 3 à 50 caractères (lettres, chiffres, point, tiret ou underscore).");
    }
    if (!validerMotDePasse($motDePasse)) {
        return reponse(false, "Le mot de passe doit contenir au moins 8 caractères.");
    }

    try {
        // Contrôle du doublon : le login doit être unique
        $check = $pdo->prepare("SELECT id_admin FROM ADMINISTRATEUR WHERE login = :login");
        $check->execute([':login' => $login]);
        if ($check->fetch()) {
            return reponse(false, "Ce login existe déjà.");
        }

        // Hachage du mot de passe avec Bcrypt : le mot de passe en clair n'est jamais enregistré
        $hash = password_hash($motDePasse, PASSWORD_BCRYPT);

        $insert = $pdo->prepare(
            "INSERT INTO ADMINISTRATEUR (nom_admin, prenom_admin, login, password)
             VALUES (:nom, :prenom, :login, :password)"
        );
        $insert->execute([
            ':nom' => $nom,
            ':prenom' => $prenom,
            ':login' => $login,
            ':password' => $hash
        ]);

        return reponse(true, "L'administrateur a été ajouté avec succès.");
    } catch (PDOException $e) {
        error_log("Erreur PDO : " . $e->getMessage());
        return reponse(false, "Erreur lors de l'ajout de l'administrateur.");
    }
}

/**
 * Modifie un administrateur.
 * Si le mot de passe est vide, l'ancien est conservé ; sinon le nouveau est haché avec Bcrypt.
 */
function modifierAdministrateur(PDO $pdo, int $id, string $nom, string $prenom, string $login, string $motDePasse = ''): array
{
    $nom = trim($nom);
    $prenom = trim($prenom);
    $login = trim($login);

    if ($nom === '' || $prenom === '' || $login === '') {
        return reponse(false, "Le nom, le prénom et le login sont obligatoires.");
    }
    if (mb_strlen($nom) > 255 || mb_strlen($prenom) > 255) {
        return reponse(false, "Le nom et le prénom ne doivent pas dépasser 255 caractères.");
    }
    if (!validerLogin($login)) {
        return reponse(false, "Le login doit contenir de 3 à 50 caractères (lettres, chiffres, point, tiret ou underscore).");
    }
    if ($motDePasse !== '' && !validerMotDePasse($motDePasse)) {
        return reponse(false, "Le nouveau mot de passe doit contenir au moins 8 caractères.");
    }

    try {
        // L'administrateur à modifier doit exister
        $existe = $pdo->prepare("SELECT id_admin FROM ADMINISTRATEUR WHERE id_admin = :id");
        $existe->execute([':id' => $id]);
        if (!$existe->fetch()) {
            return reponse(false, "Administrateur non trouvé.");
        }

        // Contrôle du doublon : le login ne doit pas appartenir à un AUTRE administrateur
        $check = $pdo->prepare("SELECT id_admin FROM ADMINISTRATEUR WHERE login = :login AND id_admin <> :id");
        $check->execute([':login' => $login, ':id' => $id]);
        if ($check->fetch()) {
            return reponse(false, "Ce login existe déjà.");
        }

        if ($motDePasse !== '') {
            $update = $pdo->prepare(
                "UPDATE ADMINISTRATEUR
                 SET nom_admin = :nom, prenom_admin = :prenom, login = :login, password = :password
                 WHERE id_admin = :id"
            );
            $update->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':login' => $login,
                ':password' => password_hash($motDePasse, PASSWORD_BCRYPT),
                ':id' => $id
            ]);
        } else {
            $update = $pdo->prepare(
                "UPDATE ADMINISTRATEUR
                 SET nom_admin = :nom, prenom_admin = :prenom, login = :login
                 WHERE id_admin = :id"
            );
            $update->execute([':nom' => $nom, ':prenom' => $prenom, ':login' => $login, ':id' => $id]);
        }

        return reponse(true, "L'administrateur a été modifié avec succès.");
    } catch (PDOException $e) {
        error_log("Erreur PDO : " . $e->getMessage());
        return reponse(false, "Erreur lors de la modification de l'administrateur.");
    }
}

/**
 * Supprime un administrateur.
 * Un administrateur ne peut pas supprimer son propre compte ($idConnecte).
 */
function supprimerAdministrateur(PDO $pdo, int $id, int $idConnecte): array
{
    if ($id === $idConnecte) {
        return reponse(false, "Vous ne pouvez pas supprimer votre propre compte.");
    }

    try {
        $delete = $pdo->prepare("DELETE FROM ADMINISTRATEUR WHERE id_admin = :id");
        $delete->execute([':id' => $id]);

        if ($delete->rowCount() > 0) {
            return reponse(true, "L'administrateur a été supprimé avec succès.");
        }
        return reponse(false, "Administrateur non trouvé.");
    } catch (PDOException $e) {
        error_log("Erreur PDO : " . $e->getMessage());
        return reponse(false, "Erreur lors de la suppression de l'administrateur.");
    }
}

// ---------------------------------------------------------------------------
// Pays (table PAYS)
// ---------------------------------------------------------------------------

/**
 * Ajoute un pays. Le nom doit être unique (sans tenir compte des majuscules).
 */
function ajouterPays(PDO $pdo, string $nom): array
{
    $nom = trim($nom);

    if ($nom === '') {
        return reponse(false, "Tous les champs sont obligatoires.");
    }
    if (mb_strlen($nom) > 255) {
        return reponse(false, "Le champ « Nom du pays » ne doit pas dépasser 255 caractères.");
    }

    try {
        // Contrôle du doublon
        $check = $pdo->prepare("SELECT id_pays FROM PAYS WHERE nom_pays = :nom_pays");
        $check->execute([':nom_pays' => $nom]);
        if ($check->fetch()) {
            return reponse(false, "Ce pays existe déjà.");
        }

        $insert = $pdo->prepare("INSERT INTO PAYS (nom_pays) VALUES (:nom_pays)");
        $insert->execute([':nom_pays' => $nom]);

        return reponse(true, "Le pays a été ajouté avec succès.");
    } catch (PDOException $e) {
        error_log("Erreur PDO : " . $e->getMessage());
        return reponse(false, "Erreur lors de l'ajout.");
    }
}

/**
 * Modifie le nom d'un pays. Le nouveau nom ne doit pas appartenir à un autre pays.
 */
function modifierPays(PDO $pdo, int $id, string $nom): array
{
    $nom = trim($nom);

    if ($nom === '') {
        return reponse(false, "Tous les champs sont obligatoires.");
    }
    if (mb_strlen($nom) > 255) {
        return reponse(false, "Le champ « Nom du pays » ne doit pas dépasser 255 caractères.");
    }

    try {
        // Le pays à modifier doit exister
        $existe = $pdo->prepare("SELECT id_pays FROM PAYS WHERE id_pays = :id");
        $existe->execute([':id' => $id]);
        if (!$existe->fetch()) {
            return reponse(false, "Pays non trouvé.");
        }

        // Contrôle du doublon : le nom ne doit pas appartenir à un AUTRE pays
        $check = $pdo->prepare("SELECT id_pays FROM PAYS WHERE nom_pays = :nom_pays AND id_pays <> :id");
        $check->execute([':nom_pays' => $nom, ':id' => $id]);
        if ($check->fetch()) {
            return reponse(false, "Ce pays existe déjà.");
        }

        $update = $pdo->prepare("UPDATE PAYS SET nom_pays = :nom_pays WHERE id_pays = :id");
        $update->execute([':nom_pays' => $nom, ':id' => $id]);

        return reponse(true, "Le pays a été modifié avec succès.");
    } catch (PDOException $e) {
        error_log("Erreur PDO : " . $e->getMessage());
        return reponse(false, "Erreur lors de la modification.");
    }
}

/**
 * Supprime un pays.
 * Refusé si le pays est encore utilisé par au moins un athlète (clé étrangère).
 */
function supprimerPays(PDO $pdo, int $id): array
{
    try {
        $delete = $pdo->prepare("DELETE FROM PAYS WHERE id_pays = :id");
        $delete->execute([':id' => $id]);

        if ($delete->rowCount() > 0) {
            return reponse(true, "Le pays a été supprimé avec succès.");
        }
        return reponse(false, "Pays non trouvé.");
    } catch (PDOException $e) {
        // Code 23000 : violation d'une clé étrangère (pays encore utilisé par un athlète)
        if ($e->getCode() == '23000') {
            return reponse(false, "Ce pays est utilisé par au moins un athlète : il ne peut pas être supprimé.");
        }
        error_log("Erreur PDO : " . $e->getMessage());
        return reponse(false, "Erreur lors de la suppression.");
    }
}
