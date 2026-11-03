<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/fonctions.php';

/**
 * Tests des fonctions de validation (login, mot de passe, date, heure).
 * Ces tests n'utilisent pas la base de données.
 */
class ValidationTest extends TestCase
{
    public function testLoginsValides(): void
    {
        foreach (['admin', 'john_doe', 'marie.d', 'a-b', 'abc', str_repeat('a', 50)] as $login) {
            $this->assertTrue(validerLogin($login), "« $login » devrait être un login valide");
        }
    }

    public function testLoginsInvalides(): void
    {
        $invalides = ['', 'ab', 'x y', 'zoé', "a'b", 'a<b>', str_repeat('a', 51)];
        foreach ($invalides as $login) {
            $this->assertFalse(validerLogin($login), "« $login » devrait être refusé");
        }
    }

    public function testMotDePasseDeHuitCaracteresMinimum(): void
    {
        $this->assertTrue(validerMotDePasse('12345678'));
        $this->assertTrue(validerMotDePasse('un mot de passe avec espaces'));
        $this->assertFalse(validerMotDePasse('1234567'));
        $this->assertFalse(validerMotDePasse(''));
    }

    public function testDatesValides(): void
    {
        // 2028 est une année bissextile : le 29 février existe
        foreach (['2028-07-14', '2028-07-30', '2028-02-29'] as $date) {
            $this->assertTrue(validerDate($date), "« $date » devrait être une date valide");
        }
    }

    public function testDatesInvalides(): void
    {
        $invalides = ['', '2028-02-30', '2027-02-29', '2028-13-01', '14/07/2028', '2028-7-4', 'pas une date'];
        foreach ($invalides as $date) {
            $this->assertFalse(validerDate($date), "« $date » devrait être refusée");
        }
    }

    public function testHeuresValides(): void
    {
        foreach (['00:00', '09:15', '14:30', '23:59'] as $heure) {
            $this->assertTrue(validerHeure($heure), "« $heure » devrait être une heure valide");
        }
    }

    public function testHeuresInvalides(): void
    {
        $invalides = ['', '24:00', '25:99', '14:60', '9:5', '14:30:00', 'midi'];
        foreach ($invalides as $heure) {
            $this->assertFalse(validerHeure($heure), "« $heure » devrait être refusée");
        }
    }
}
