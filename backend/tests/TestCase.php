<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * Garde-fou : les tests tournent toujours sur SQLite en memoire, et sur un
     * cache jetable.
     *
     * Les <env> de phpunit.xml ne sont pas fiables ici : quand une variable existe
     * deja dans l'environnement (ex. DB_CONNECTION=pgsql injecte par Docker) ou dans
     * le .env charge par Laravel, elle l'emporte, et RefreshDatabase vide alors la
     * base de developpement. On force donc la connexion apres le boot de l'app,
     * avant que les traits de test (RefreshDatabase) ne s'executent.
     *
     * Meme probleme pour le cache depuis qu'il pointe sur Redis : RefreshDatabase
     * ne nettoie que la base. Le limiteur de debit (throttle) stocke ses compteurs
     * dans le cache, qui survivait alors d'un test a l'autre — un test recevait un
     * 429 a cause des requetes du test precedent. Le driver "array" repart de zero
     * a chaque test.
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,

            // Aucun test ne doit toucher au Redis de developpement.
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',

            /*
             * FedaPay coupe par defaut, cles effacees.
             *
             * Deux raisons, la seconde serieuse. D'abord un test ne doit pas
             * dire quelque chose de different selon que le developpeur a
             * configure des cles ou non. Ensuite : le .env local peut porter
             * des cles LIVE. Sans cette coupure, un test qui appelle FedaPay
             * creerait de vraies transactions et ferait sonner de vrais
             * telephones.
             *
             * Les tests qui ont besoin de FedaPay le reactivent eux-memes,
             * avec des cles factices (voir FedapayPaymentTest).
             */
            'payments.fedapay.enabled' => false,
            'payments.fedapay.public_key' => null,
            'payments.fedapay.secret_key' => null,
            'payments.fedapay.webhook_secret' => null,
            'payments.fedapay.sandbox' => true,
        ]);

        DB::purge('sqlite');

        Cache::purge('array');
        RateLimiter::clear('');

        // Photos de preparation et de livraison : obligatoires, donc presentes
        // dans presque chaque parcours teste. Sans ce faux disque elles
        // s'ecriraient pour de bon dans backend/storage/app/public, qui se
        // remplirait de fichiers de test.
        Storage::fake('public');
    }
}
