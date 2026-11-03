<?php

require_once __DIR__ . '/BaseDeDonneesTestCase.php';

/**
 * Tests de la gestion des pays : ajout, modification, suppression,
 * refus des doublons et refus de supprimer un pays utilisé par un athlète.
 */
class PaysTest extends BaseDeDonneesTestCase
{
    /** Identifiant d'un pays utilisé par au moins un athlète (données de test). */
    private function idPaysUtilise(): int
    {
        $id = $this->valeur("SELECT id_pays FROM ATHLETE LIMIT 1");
        if ($id === false) {
            $this->markTestSkipped("Aucun athlète en base : importez test-data-jo.sql dans la base de test.");
        }
        return (int) $id;
    }

    /**
     * Nom de pays unique : les tests ne dépendent ainsi pas des pays déjà présents en base
     * (le Brésil, par exemple, existe déjà dans les données de test).
     */
    private function nomUnique(string $base = 'Pays de test'): string
    {
        return $base . ' ' . uniqid();
    }

    /** Crée un pays de test (donc non utilisé par un athlète) et renvoie son identifiant. */
    private function creerPays(?string $nom = null): int
    {
        $nom = $nom ?? $this->nomUnique();
        $resultat = ajouterPays($this->pdo, $nom);
        $this->assertTrue($resultat['succes'], 'Le jeu de test devrait pouvoir créer un pays : ' . $resultat['message']);

        return (int) $this->valeur("SELECT id_pays FROM PAYS WHERE nom_pays = :nom", [':nom' => $nom]);
    }

    // ----- Ajout ---------------------------------------------------------

    public function testAjoutDunPays(): void
    {
        $avant = $this->compter('PAYS');

        $resultat = ajouterPays($this->pdo, $this->nomUnique());

        $this->assertTrue($resultat['succes'], $resultat['message']);
        $this->assertSame('Le pays a été ajouté avec succès.', $resultat['message']);
        $this->assertSame($avant + 1, $this->compter('PAYS'));
    }

    public function testAjoutRefuseUnPaysDejaPresentDansLesDonneesDeTest(): void
    {
        // Le Brésil fait partie des données de test fournies : on ne peut pas l'ajouter une seconde fois
        $avant = $this->compter('PAYS');

        $resultat = ajouterPays($this->pdo, 'Brésil');

        $this->assertFalse($resultat['succes']);
        $this->assertSame('Ce pays existe déjà.', $resultat['message']);
        $this->assertSame($avant, $this->compter('PAYS'));
    }

    public function testAjoutRefuseUnPaysDejaExistant(): void
    {
        $nom = $this->nomUnique('Pays Doublon');
        $this->creerPays($nom);
        $avant = $this->compter('PAYS');

        // Même nom avec une casse différente : le doublon est détecté
        $resultat = ajouterPays($this->pdo, strtoupper($nom));

        $this->assertFalse($resultat['succes']);
        $this->assertSame('Ce pays existe déjà.', $resultat['message']);
        $this->assertSame($avant, $this->compter('PAYS'));
    }

    public function testAjoutRefuseUnNomVideOuTropLong(): void
    {
        $vide = ajouterPays($this->pdo, '   ');
        $this->assertFalse($vide['succes']);
        $this->assertSame('Tous les champs sont obligatoires.', $vide['message']);

        $trop_long = ajouterPays($this->pdo, str_repeat('x', 256));
        $this->assertFalse($trop_long['succes']);
        $this->assertStringContainsString('ne doit pas dépasser 255 caractères', $trop_long['message']);
    }

    public function testAjoutConserveApostrophesEtAccents(): void
    {
        $nom = $this->nomUnique("Côte d'Ivoire");
        $id = $this->creerPays($nom);

        $this->assertSame($nom, $this->valeur("SELECT nom_pays FROM PAYS WHERE id_pays = $id"));
    }

    public function testUneInjectionSqlEstTraiteeCommeDuTexte(): void
    {
        $avant = $this->compter('PAYS');
        $attaque = "x'); DROP TABLE PAYS; --";

        $resultat = ajouterPays($this->pdo, $attaque);

        // Les requêtes préparées enregistrent le texte tel quel et n'exécutent rien
        $this->assertTrue($resultat['succes'], $resultat['message']);
        $this->assertSame($avant + 1, $this->compter('PAYS'));
        $this->assertSame($attaque, $this->valeur("SELECT nom_pays FROM PAYS WHERE nom_pays = :nom", [':nom' => $attaque]));
    }

    // ----- Modification --------------------------------------------------

    public function testModificationDunPays(): void
    {
        $id = $this->creerPays();
        $nouveauNom = $this->nomUnique('Pays modifié');

        $resultat = modifierPays($this->pdo, $id, $nouveauNom);

        $this->assertTrue($resultat['succes'], $resultat['message']);
        $this->assertSame('Le pays a été modifié avec succès.', $resultat['message']);
        $this->assertSame($nouveauNom, $this->valeur("SELECT nom_pays FROM PAYS WHERE id_pays = $id"));
    }

    public function testModificationRefuseLeNomDunAutrePays(): void
    {
        $nom = $this->nomUnique();
        $id = $this->creerPays($nom);
        $nomExistant = $this->valeur("SELECT nom_pays FROM PAYS WHERE id_pays <> $id ORDER BY id_pays LIMIT 1");

        $resultat = modifierPays($this->pdo, $id, $nomExistant);

        $this->assertFalse($resultat['succes']);
        $this->assertSame('Ce pays existe déjà.', $resultat['message']);
        $this->assertSame($nom, $this->valeur("SELECT nom_pays FROM PAYS WHERE id_pays = $id"));
    }

    public function testModificationPermetDeGarderSonPropreNom(): void
    {
        $nom = $this->nomUnique();
        $id = $this->creerPays($nom);

        $resultat = modifierPays($this->pdo, $id, $nom);

        $this->assertTrue($resultat['succes'], $resultat['message']);
    }

    public function testModificationDunPaysInexistant(): void
    {
        $resultat = modifierPays($this->pdo, 999999, 'Inconnu');

        $this->assertFalse($resultat['succes']);
        $this->assertSame('Pays non trouvé.', $resultat['message']);
    }

    // ----- Suppression ---------------------------------------------------

    public function testSuppressionDunPaysLibre(): void
    {
        $id = $this->creerPays();
        $avant = $this->compter('PAYS');

        $resultat = supprimerPays($this->pdo, $id);

        $this->assertTrue($resultat['succes'], $resultat['message']);
        $this->assertSame('Le pays a été supprimé avec succès.', $resultat['message']);
        $this->assertSame($avant - 1, $this->compter('PAYS'));
    }

    public function testSuppressionRefuseeSiLePaysEstUtiliseParUnAthlete(): void
    {
        $id = $this->idPaysUtilise();
        $avant = $this->compter('PAYS');

        $resultat = supprimerPays($this->pdo, $id);

        // La clé étrangère protège les données : le pays est conservé
        $this->assertFalse($resultat['succes']);
        $this->assertStringContainsString('utilisé par au moins un athlète', $resultat['message']);
        $this->assertSame($avant, $this->compter('PAYS'));
    }

    public function testSuppressionDunPaysInexistant(): void
    {
        $resultat = supprimerPays($this->pdo, 999999);

        $this->assertFalse($resultat['succes']);
        $this->assertSame('Pays non trouvé.', $resultat['message']);
    }
}
