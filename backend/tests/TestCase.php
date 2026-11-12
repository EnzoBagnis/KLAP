<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Classe de base de nos tests. Par défaut, ils tournent sur SQLite en mémoire
 * (phpunit.xml) ; le groupe « mariadb » rejoue sur MariaDB ce que SQLite ignore.
 */
abstract class TestCase extends BaseTestCase {}
