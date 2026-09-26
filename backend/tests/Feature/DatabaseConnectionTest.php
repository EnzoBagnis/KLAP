<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

/**
 * Connexion à la base (issue #53).
 *
 * Les tests tournent sur SQLite en mémoire (cf. phpunit.xml), donc on vérifie ici
 * la configuration de la connexion MariaDB sans l'ouvrir. Le dernier test tente une
 * vraie connexion et se met en skip si la base n'est pas joignable : il est utile en
 * local et en CI quand un service MariaDB tourne, sans casser le reste du temps.
 */
class DatabaseConnectionTest extends TestCase
{
    public function test_la_connexion_mysql_est_declaree(): void
    {
        $this->assertArrayHasKey('mysql', config('database.connections'));
        $this->assertSame('mysql', config('database.connections.mysql.driver'));
    }

    public function test_la_connexion_mysql_pointe_sur_la_base_klap(): void
    {
        $mysql = config('database.connections.mysql');

        // Les valeurs viennent du .env et changent selon l'environnement (db ou 127.0.0.1,
        // port remappé...). On vérifie donc qu'elles sont renseignées, pas leur contenu.
        foreach (['host', 'port', 'database', 'username'] as $key) {
            $this->assertNotEmpty($mysql[$key], "La clé « {$key} » de la connexion mysql est vide.");
        }
    }

    /**
     * Les données de test contiennent « Lycée Saint-Charles », « Étincelles »,
     * « chorégraphique »... Un jeu de caractères non utf8mb4 les corromprait.
     */
    public function test_le_jeu_de_caracteres_supporte_les_accents(): void
    {
        $this->assertSame('utf8mb4', config('database.connections.mysql.charset'));
        $this->assertStringStartsWith('utf8mb4_', config('database.connections.mysql.collation'));
    }

    /**
     * Sans DB_CONNECTION, on doit atterrir sur mysql et non sur sqlite : une bascule
     * silencieuse sur SQLite ferait passer les migrations en ignorant les contraintes
     * CHECK, qui y sont explicitement désactivées.
     */
    public function test_le_repli_par_defaut_est_mysql(): void
    {
        // phpunit.xml impose DB_CONNECTION=sqlite : on la retire le temps de relire le
        // fichier de config, sinon on observerait l'environnement de test et non le repli.
        // Laravel lit $_ENV, $_SERVER et getenv(), il faut donc neutraliser les trois.
        $env = $_ENV['DB_CONNECTION'] ?? null;
        $server = $_SERVER['DB_CONNECTION'] ?? null;
        $putenv = getenv('DB_CONNECTION');

        unset($_ENV['DB_CONNECTION'], $_SERVER['DB_CONNECTION']);
        putenv('DB_CONNECTION');

        try {
            $config = require config_path('database.php');

            $this->assertSame('mysql', $config['default'], 'Le repli de DB_CONNECTION doit rester mysql.');
        } finally {
            if ($env !== null) {
                $_ENV['DB_CONNECTION'] = $env;
            }
            if ($server !== null) {
                $_SERVER['DB_CONNECTION'] = $server;
            }
            if ($putenv !== false) {
                putenv('DB_CONNECTION='.$putenv);
            }
        }
    }

    public function test_la_base_mariadb_repond_si_elle_est_joignable(): void
    {
        $database = config('database.connections.mysql.database');

        // phpunit.xml impose DB_DATABASE=:memory: pour les tests SQLite. Tant que cette
        // valeur est en place, la connexion mysql ne pointe sur aucune base réelle.
        if ($database === ':memory:') {
            $this->markTestSkipped(
                'Connexion mysql pointée sur :memory: (valeur imposée par phpunit.xml). '
                .'Pour tester la vraie base : DB_DATABASE=klap DB_HOST=127.0.0.1 php artisan test --filter=DatabaseConnectionTest'
            );
        }

        try {
            $version = DB::connection('mysql')->selectOne('SELECT VERSION() AS version')->version;
        } catch (PDOException $e) {
            $this->markTestSkipped('MariaDB non joignable : '.$e->getMessage());
        }

        $this->assertNotEmpty($version, 'La base a répondu sans numéro de version.');
        $this->assertStringContainsStringIgnoringCase('mariadb', $version, "Version inattendue : {$version}");
    }
}
