<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests de vérification de l'environnement de test (Mission 7, question 20).
 * Ils confirment que PHPUnit fonctionne et que PHP dispose de ce dont l'application a besoin.
 */
class EnvironnementTest extends TestCase
{
    // PHPUnit est correctement installé et sait exécuter un test
    public function testPhpunitFonctionne(): void
    {
        $this->assertTrue(true);
    }

    // La version de PHP est suffisante pour l'application
    public function testVersionPhpSuffisante(): void
    {
        $this->assertTrue(version_compare(PHP_VERSION, '7.4.0', '>='));
    }

    // L'extension PDO pour MySQL est chargée (nécessaire à la connexion à la base de données)
    public function testExtensionPdoMysqlDisponible(): void
    {
        $this->assertTrue(extension_loaded('pdo_mysql'));
    }

    // Le hachage Bcrypt est disponible (nécessaire aux mots de passe des administrateurs)
    public function testHachageBcryptDisponible(): void
    {
        $hash = password_hash('mot-de-passe-de-test', PASSWORD_BCRYPT);

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(password_verify('mot-de-passe-de-test', $hash));
    }
}
