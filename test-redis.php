<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $redis = \Illuminate\Support\Facades\Redis::connection();
    $result = $redis->ping();

    if ($result === 'PONG' || $result === true) {
        echo "✅ Redis está funcionando corretamente!\n";
        echo '   Conexão: '.config('database.redis.default.host').':'.config('database.redis.default.port')."\n";
        echo '   Database: '.config('database.redis.default.database')."\n";
    } else {
        echo '❌ Redis retornou resposta inesperada: '.var_export($result, true)."\n";
    }
} catch (\Exception $e) {
    echo '❌ Erro ao conectar ao Redis: '.$e->getMessage()."\n";
    exit(1);
}
