<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guarda contra "cache hell" (Licao #23):
     * `bootstrap/cache/config.php` cacheia valores do .env atual. Em
     * ambiente de teste, phpunit.xml deveria sobrescrever DB_DATABASE para
     * `poprua_cras_test` — mas se houver config cacheado, o override e
     * ignorado e os testes batem em poprua_cras (DB de dev/prod), gerando
     * falhas confusas tipo "actual size 900 matches expected size 1".
     *
     * Defesa: abortar imediatamente com mensagem clara se detectarmos
     * config cacheado. Container PHP ja limpa o cache via init-perms
     * sidecar no start; essa checagem cobre o caso em que alguem rode
     * `php artisan config:cache` durante uma sessao.
     */
    protected function setUp(): void
    {
        // __DIR__ funciona ANTES do parent::setUp() — base_path() depende do app booted
        $cachedConfig = __DIR__.'/../bootstrap/cache/config.php';
        if (file_exists($cachedConfig)) {
            throw new RuntimeException(
                "Config esta cacheado em {$cachedConfig}.\n".
                "Isso faz os testes baterem no DB errado (Licao #23).\n".
                "Rode: php artisan config:clear  (no host: \$EXEC php artisan config:clear)\n".
                'E NAO rode `config:cache` em dev — esse comando so deve rodar em producao real.'
            );
        }

        parent::setUp();

        if (app()->environment('testing')) {
            // bootstrap/app.php usa throttleWithRedis() — alias 'throttle' resolve para
            // ThrottleRequestsWithRedis, não ThrottleRequests (TBL-NEW-THROTTLE-REDIS).
            $this->withoutMiddleware([
                ThrottleRequests::class,
                ThrottleRequestsWithRedis::class,
            ]);
        }
    }
}
