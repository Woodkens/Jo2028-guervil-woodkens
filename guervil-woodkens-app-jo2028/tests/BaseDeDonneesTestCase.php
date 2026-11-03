<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/fonctions.php';

/**
 * Classe de base des tests qui utilisent la base de données.
 *
 * - Les tests s'exécutent sur une base DÉDIÉE (nom_prenom_jo2028_test), jamais sur la vraie base.
 * - Chaque test est enveloppé dans une transaction annulée à la fin (rollBack) :
 *   la base de test retrouve donc son état d'origine après chaque test.
 */
abstract class BaseDeDonneesTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        // Paramètres de connexion (modifiables par des variables d'environnement)
        $hote = getenv('DB_TEST_HOST') ?: 'localhost';
        $nomBase = getenv('DB_TEST_NAME') ?: 'guervil_woodkens_jo2028_test';
        $utilisateur = getenv('DB_TEST_USER') ?: 'root';
        $motDePasse = getenv('DB_TEST_PASSWORD') !== false ? getenv('DB_TEST_PASSWORD') : 'root';

        // Sécurité : on refuse de tester sur une base dont le nom ne se termine pas par « _test »
        if (substr($nomBase, -5) !== '_test') {
            $this->fail("Par sécurité, la base de test doit se terminer par « _test » (base demandée : $nomBase).");
        }

        try {
            $this->pdo = new PDO(
                "mysql:host=$hote;dbname=$nomBase;charset=utf8mb4",
                $utilisateur,
                $motDePasse,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            $this->markTestSkipped(
                "Base de test « $nomBase » introuvable. Créez-la dans phpMyAdmin puis importez "
                . "db-jo.sql et test-data-jo.sql. Détail : " . $e->getMessage()
            );
        }

        // Début de la transaction : tout ce que fait le test sera annulé
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    /** Nombre de lignes d'une table. */
    protected function compter(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }

    /** Valeur d'une requête préparée à une seule colonne (ou false si aucune ligne). */
    protected function valeur(string $sql, array $parametres = [])
    {
        $requete = $this->pdo->prepare($sql);
        $requete->execute($parametres);
        return $requete->fetchColumn();
    }
}
