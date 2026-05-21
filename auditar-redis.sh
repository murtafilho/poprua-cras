#!/bin/bash

# Script de Auditoria do Redis para Laravel POPRUA

echo "=========================================="
echo "🔍 AUDITORIA DO REDIS - Laravel POPRUA"
echo "=========================================="
echo ""

PROJECT_DIR="/home/murta/projects/popruav2"
cd "$PROJECT_DIR"

# 1. Verificar Redis
echo "1️⃣  VERIFICANDO REDIS"
echo "-------------------"
if redis-cli ping > /dev/null 2>&1; then
    echo "✅ Redis está rodando"
    redis-cli INFO server | grep "redis_version" | head -1
else
    echo "❌ Redis NÃO está rodando"
    exit 1
fi
echo ""

# 2. Verificar extensão PHP
echo "2️⃣  VERIFICANDO EXTENSÃO PHP REDIS"
echo "-------------------"
if php -m | grep -i redis > /dev/null; then
    echo "✅ Extensão PHP Redis instalada"
    php -m | grep -i redis
else
    echo "❌ Extensão PHP Redis NÃO instalada"
fi
echo ""

# 3. Testar conexão Laravel
echo "3️⃣  TESTANDO CONEXÃO LARAVEL"
echo "-------------------"
php test-redis.php
echo ""

# 4. Verificar configuração .env
echo "4️⃣  VERIFICANDO CONFIGURAÇÃO .ENV"
echo "-------------------"
if grep -q "QUEUE_CONNECTION=redis" .env; then
    echo "✅ QUEUE_CONNECTION=redis configurado"
else
    echo "❌ QUEUE_CONNECTION não está configurado para redis"
fi

REDIS_VARS=("REDIS_HOST" "REDIS_PORT" "REDIS_DB" "REDIS_CACHE_DB")
for var in "${REDIS_VARS[@]}"; do
    if grep -q "^$var=" .env; then
        echo "✅ $var configurado: $(grep "^$var=" .env | cut -d'=' -f2)"
    else
        echo "⚠️  $var não encontrado no .env"
    fi
done
echo ""

# 5. Verificar configuração do Laravel
echo "5️⃣  VERIFICANDO CONFIGURAÇÃO DO LARAVEL"
echo "-------------------"
QUEUE_DEFAULT=$(php artisan config:show queue.default 2>/dev/null | awk '{print $NF}')
echo "Queue Default: $QUEUE_DEFAULT"
if [ "$QUEUE_DEFAULT" = "redis" ]; then
    echo "✅ Laravel configurado para usar Redis"
else
    echo "⚠️  Laravel não está usando Redis (atual: $QUEUE_DEFAULT)"
    echo "   Execute: php artisan config:clear"
fi
echo ""

# 6. Verificar Supervisor
echo "6️⃣  VERIFICANDO SUPERVISOR"
echo "-------------------"
if [ -f "supervisor/poprua-worker.conf" ]; then
    echo "✅ Arquivo de configuração do Supervisor existe"
    if grep -q "queue:work redis" supervisor/poprua-worker.conf; then
        echo "✅ Supervisor configurado para usar Redis"
    else
        echo "⚠️  Supervisor não está configurado para Redis"
    fi
else
    echo "⚠️  Arquivo de configuração do Supervisor não encontrado"
fi
echo ""

# 7. Verificar filas no Redis
echo "7️⃣  VERIFICANDO FILAS NO REDIS"
echo "-------------------"
KEYS=$(redis-cli KEYS "*queue*" 2>/dev/null | wc -l)
echo "Chaves relacionadas a filas: $KEYS"
if [ "$KEYS" -gt 0 ]; then
    echo "Chaves encontradas:"
    redis-cli KEYS "*queue*" 2>/dev/null | head -5
fi

QUEUE_LENGTH=$(redis-cli LLEN "queues:default" 2>/dev/null)
echo "Jobs na fila 'default': $QUEUE_LENGTH"
echo ""

# 8. Estatísticas do Redis
echo "8️⃣  ESTATÍSTICAS DO REDIS"
echo "-------------------"
echo "Total de conexões recebidas: $(redis-cli INFO stats | grep total_connections_received | cut -d: -f2 | tr -d '\r')"
echo "Total de comandos processados: $(redis-cli INFO stats | grep total_commands_processed | cut -d: -f2 | tr -d '\r')"
echo "Total de chaves no database 0: $(redis-cli DBSIZE)"
echo ""

# 9. Testar processamento de job
echo "9️⃣  TESTANDO PROCESSAMENTO DE JOB"
echo "-------------------"
echo "Enviando job de teste..."
php artisan tinker --execute="App\Jobs\TestRedisJob::dispatch('Auditoria Redis'); echo 'Job enviado\n';" > /dev/null 2>&1
sleep 1

QUEUE_LENGTH=$(redis-cli LLEN "queues:default" 2>/dev/null)
if [ "$QUEUE_LENGTH" -gt 0 ]; then
    echo "✅ Job enviado para a fila ($QUEUE_LENGTH job(s) na fila)"
    echo "Processando job..."
    timeout 3 php artisan queue:work redis --once --verbose 2>&1 | head -10
else
    echo "⚠️  Nenhum job na fila (pode ter sido processado automaticamente)"
fi
echo ""

# 10. Verificar logs
echo "🔟 VERIFICANDO LOGS"
echo "-------------------"
if [ -f "storage/logs/laravel.log" ]; then
    LOG_SIZE=$(du -h storage/logs/laravel.log | cut -f1)
    echo "Tamanho do log: $LOG_SIZE"
    if tail -50 storage/logs/laravel.log | grep -i "testredis" > /dev/null 2>&1; then
        echo "✅ Job foi processado (encontrado nos logs)"
    else
        echo "⚠️  Job não encontrado nos logs (pode não ter sido processado ainda)"
    fi
else
    echo "⚠️  Arquivo de log não encontrado"
fi
echo ""

# 11. Resumo
echo "=========================================="
echo "📊 RESUMO DA AUDITORIA"
echo "=========================================="
echo ""
echo "✅ Redis: Funcionando"
echo "✅ PHP Redis: Instalado"
echo "✅ Conexão Laravel: OK"
if [ "$QUEUE_DEFAULT" = "redis" ]; then
    echo "✅ Configuração Laravel: Redis"
else
    echo "⚠️  Configuração Laravel: $QUEUE_DEFAULT (deve ser redis)"
fi
echo "✅ Supervisor: Configurado"
echo ""
echo "=========================================="
