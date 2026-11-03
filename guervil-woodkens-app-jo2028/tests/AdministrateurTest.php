<?php

require_once __DIR__ . '/BaseDeDonneesTestCase.php';

/**
 * Tests de la gestion des administrateurs : ajout, modification, suppression,
 * hachage Bcrypt du mot de passe et refus des doublons de login.
 */
class AdministrateurTest extends BaseDeDonneesTestCase
{
    /** Crée un administrateur de test et renvoie son identifiant. */
    private function creerAdministrateur(string $login = 'marie_d', string $motDePasse = 'motdepasse1'): int
    {
        $resultat = ajouterAdministrateur($this->pdo, 'Dupont', 'Marie', $login, $motDePasse);
        $this->assertTrue($resultat['succes'], 'Le jeu de test devrait pouvoir créer un administrateur');

        return (int) $this->valeur("SELECT id_admin FROM ADMINISTRATEUR WHERE login = :login", [':login' => $login]);
    }

    // ----- Ajout ---------------------------------------------------------

    public function testAjoutDunAdministrateur(): void
    {
        $avant = $this->compter('ADMINISTRATEUR');

        $resultat = ajouterAdministrateur($this->pdo, 'Dupont', 'Marie', 'marie_d', 'motdepasse1');

        $this->assertTrue($resultat['succes'], $resultat['message']);
        $this->assertSame("L'administrateur a été ajouté avec succès.", $resultat['message']);
        $this->assertSame($avant + 1, $this->compter('ADMINISTRATEUR'));
    }

    public function testLeMotDePasseEstHacheAvecBcrypt(): void
    {
        $this->creerAdministrateur('marie_d', 'motdepasse1');

        $hash = $this->valeur("SELECT password FROM ADMINISTRATEUR WHERE login = 'marie_d'");

        // Le mot de passe en clair n'est jamais enregistré
        $this->assertNotSame('motdepasse1', $hash);
        // Bcrypt : 60 caractères commençant par $2y$
        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertSame(60, strlen($hash));
        // La vérification fonctionne avec le bon mot de passe, pas avec un mauvais
        $this->assertTrue(password_verify('motdepasse1', $hash));
        $this->assertFalse(password_verify('mauvais-mot-de-passe', $hash));
    }

    public function testDeuxMotsDePasseIdentiquesDonnentDeuxHachagesDifferents(): void
    {
        $this->creerAdministrateur('premier', 'motdepasse1');
        $this->creerAdministrateur('second', 'motdepasse1');

        $hash1 = $this->valeur("SELECT password FROM ADMINISTRATEUR WHERE login = 'premier'");
        $hash2 = $this->valeur("SELECT password FROM ADMINISTRATEUR WHERE login = 'second'");

        // Bcrypt ajoute un sel aléatoire à chaque hachage
        $this->assertNotSame($hash1, $hash2);
    }

    public function testAjoutRefuseUnLoginDejaExistant(): void
    {
        $this->creerAdministrateur('marie_d');
        $avant = $this->compter('ADMINISTRATEUR');

        // Même login, avec des majuscules : le doublon est détecté
        $resultat = ajouterAdministrateur($this->pdo, 'Autre', 'Personne', 'MARIE_D', 'motdepasse1');

        $this->assertFalse($resultat['succes']);
        $this->assertSame('Ce login existe déjà.', $resultat['message']);
        $this->assertSame($avant, $this->compter('ADMINISTRATEUR'));
    }

    public function testAjoutRefuseUnMotDePasseTropCourt(): void
    {
        $avant = $this->compter('ADMINISTRATEUR');

        $resultat = ajouterAdministrateur($this->pdo, 'Dupont', 'Marie', 'marie_d', 'court');

        $this->assertFalse($resultat['succes']);
        $this->assertStringContainsString('au moins 8 caractères', $resultat['message']);
        $this->assertSame($avant, $this->compter('ADMINISTRATEUR'));
    }

    public function testAjoutRefuseUnLoginInvalide(): void
    {
        $resultat = ajouterAdministrateur($this->pdo, 'Dupont', 'Marie', 'login invalide !', 'motdepasse1');

        $this->assertFalse($resultat['succes']);
        $this->assertStringContainsString('Le login doit contenir', $resultat['message']);
    }

    public function testAjoutRefuseLesChampsVides(): void
    {
        $resultat = ajouterAdministrateur($this->pdo, '', 'Marie', 'marie_d', 'motdepasse1');

        $this->assertFalse($resultat['succes']);
        $this->assertSame('Tous les champs sont obligatoires.', $resultat['message']);
    }

    public function testAjoutConserveApostrophesEtAccents(): void
    {
        ajouterAdministrateur($this->pdo, "O'Brien", 'Zoé', 'zoe_ob', 'motdepasse1');

        // Les textes sont enregistrés tels quels (l'échappement se fait à l'affichage)
        $this->assertSame("O'Brien", $this->valeur("SELECT nom_admin FROM ADMINISTRATEUR WHERE login = 'zoe_ob'"));
        $this->assertSame('Zoé', $this->valeur("SELECT prenom_admin FROM ADMINISTRATEUR WHERE login = 'zoe_ob'"));
    }

    // ----- Modification --------------------------------------------------

    public function testModificationSansMotDePasseConserveLAncienHachage(): void
    {
        $id = $this->creerAdministrateur('marie_d', 'motdepasse1');
        $hashAvant = $this->valeur("SELECT password FROM ADMINISTRATEUR WHERE id_admin = $id");

        $resultat = modifierAdministrateur($this->pdo, $id, 'Martin', 'Marie', 'marie_d', '');

        $this->assertTrue($resultat['succes'], $resultat['message']);
        $this->assertSame('Martin', $this->valeur("SELECT nom_admin FROM ADMINISTRATEUR WHERE id_admin = $id"));
        $this->assertSame($hashAvant, $this->valeur("SELECT password FROM ADMINISTRATEUR WHERE id_admin = $id"));
    }

    public function testModificationAvecNouveauMotDePasseChangeLeHachage(): void
    {
        $id = $this->creerAdministrateur('marie_d', 'motdepasse1');
        $hashAvant = $this->valeur("SELECT password FROM ADMINISTRATEUR WHERE id_admin = $id");

        $resultat = modifierAdministrateur($this->pdo, $id, 'Dupont', 'Marie', 'marie_d', 'nouveau-mdp-99');

        $this->assertTrue($resultat['succes'], $resultat['message']);
        $hashApres = $this->valeur("SELECT password FROM ADMINISTRATEUR WHERE id_admin = $id");
        $this->assertNotSame($hashAvant, $hashApres);
        $this->assertTrue(password_verify('nouveau-mdp-99', $hashApres));
        $this->assertFalse(password_verify('motdepasse1', $hashApres));
    }

    public function testModificationRefuseLeLoginDunAutreAdministrateur(): void
    {
        $this->creerAdministrateur('premier');
        $id = $this->creerAdministrateur('second');

        $resultat = modifierAdministrateur($this->pdo, $id, 'Dupont', 'Marie', 'premier', '');

        $this->assertFalse($resultat['succes']);
        $this->assertSame('Ce login existe déjà.', $resultat['message']);
        $this->assertSame('second', $this->valeur("SELECT login FROM ADMINISTRATEUR WHERE id_admin = $id"));
    }

    public function testModificationPermetDeGarderSonPropreLogin(): void
    {
        $id = $this->creerAdministrateur('marie_d');

        $resultat = modifierAdministrateur($this->pdo, $id, 'Nouveau', 'Nom', 'marie_d', '');

        $this->assertTrue($resultat['succes'], $resultat['message']);
    }

    public function testModificationDunAdministrateurInexistant(): void
    {
        $resultat = modifierAdministrateur($this->pdo, 999999, 'Dupont', 'Marie', 'inconnu', '');

        $this->assertFalse($resultat['succes']);
        $this->assertSame('Administrateur non trouvé.', $resultat['message']);
    }

    // ----- Suppression ---------------------------------------------------

    public function testSuppressionDunAdministrateur(): void
    {
        $id = $this->creerAdministrateur('marie_d');
        $avant = $this->compter('ADMINISTRATEUR');

        // Le 3e paramètre est l'identifiant de l'administrateur connecté (un autre compte)
        $resultat = supprimerAdministrateur($this->pdo, $id, 1);

        $this->assertTrue($resultat['succes'], $resultat['message']);
        $this->assertSame($avant - 1, $this->compter('ADMINISTRATEUR'));
    }

    public function testImpossibleDeSupprimerSonPropreCompte(): void
    {
        $id = $this->creerAdministrateur('marie_d');
        $avant = $this->compter('ADMINISTRATEUR');

        // L'administrateur connecté (3e paramètre) est celui qu'il essaie de supprimer
        $resultat = supprimerAdministrateur($this->pdo, $id, $id);

        $this->assertFalse($resultat['succes']);
        $this->assertSame('Vous ne pouvez pas supprimer votre propre compte.', $resultat['message']);
        $this->assertSame($avant, $this->compter('ADMINISTRATEUR'));
    }

    public function testSuppressionDunAdministrateurInexistant(): void
    {
        $resultat = supprimerAdministrateur($this->pdo, 999999, 1);

        $this->assertFalse($resultat['succes']);
        $this->assertSame('Administrateur non trouvé.', $resultat['message']);
    }
}
