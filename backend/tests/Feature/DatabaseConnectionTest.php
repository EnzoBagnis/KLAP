<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

/**
 * Connexion à la base (issue #53).
 *
 * La suite tourne sur SQLite en mémoire (phpunit.xml), on vérifie donc la
 * configuration sans ouvrir la connexion. Le dernier test interroge le serveur
 * quand il est joignable.
 */
class DatabaseConnectionTest extends TestCase
{
    public function test_la_connexion_mysql_est_declaree(): void
    {
        $this->assertArrayHasKey('mysql', config('database.connections'));
        $this->assertSame('mysql', config('database.connections.mysql.driver'));
    }

    public function test_les_parametres_de_connexion_sont_renseignes(): void
    {
        $mysql = config('database.connections.mysql');

        foreach (['host', 'port', 'database', 'username'] as $cle) {
            $this->assertNotEmpty($mysql[$cle], "La clé « {$cle} » de la connexion mysql est vide.");
        }
    }

    /**
     * Les données contiennent « Lycée Saint-Charles », « Étincelles »,
     * « chorégraphique ». Un jeu de caractères latin1 les corromprait.
     */
    public function test_le_jeu_de_caracteres_supporte_les_accents(): void
    {
        $this->assertSame('utf8mb4', config('database.connections.mysql.charset'));
        $this->assertStringStartsWith('utf8mb4_', config('database.connections.mysql.collation'));
    }

    /**
     * Sans DB_CONNECTION, il faut échouer sur mysql plutôt que basculer sur SQLite,
     * où les migrations ignorent les contraintes CHECK.
     */
    public function test_le_repli_par_defaut_reste_mysql(): void
    {
        $this->assertMatchesRegularExpression(
            "/'default'\s*=>\s*env\('DB_CONNECTION',\s*'mysql'\)/",
            file_get_contents(config_path('database.php')),
            'Le repli de DB_CONNECTION doit rester mysql, jamais sqlite.'
        );
    }

    public function test_le_serveur_repond_sil_est_joignable(): void
    {
        if (config('database.connections.mysql.database') === ':memory:') {
            $this->markTestSkipped(
                'Connexion mysql pointée sur :memory: (imposé par phpunit.xml). '
                .'Pour interroger la base : DB_DATABASE=klap DB_HOST=127.0.0.1 php artisan test --filter=DatabaseConnectionTest'
            );
        }

        try {
            $version = DB::connection('mysql')->selectOne('SELECT VERSION() AS version')->version;
        } catch (PDOException $e) {
            // Serveur hors ligne : on passe. Identifiants ou base erronés : on échoue,
            // c'est précisément ce que ce test doit détecter.
            if (! preg_match('/\[200[23]\]/', $e->getMessage())) {
                throw $e;
            }

            $this->markTestSkipped('Serveur de base injoignable : '.$e->getMessage());
        }

        // MariaDB en local, MySQL sur Alwaysdata : on ne fige aucun des deux.
        $this->assertMatchesRegularExpression('/^\d+\.\d+/', $version, "Version inattendue : {$version}");
    }
}
