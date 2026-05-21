# Configuração do Redis para Laravel POPRUA

## Status
✅ Redis está instalado e funcionando (versão 7.0.15)
✅ Extensão PHP Redis está instalada

## Configuração no .env

Adicione ou atualize as seguintes variáveis no arquivo `.env`:

```env
# Redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Redis Databases
REDIS_DB=0          # Database padrão (para filas e sessões)
REDIS_CACHE_DB=1    # Database para cache

# Redis Prefix
REDIS_PREFIX=poprua-database-

# Queue - Usar Redis
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=90

# Cache - Usar Redis (opcional)
CACHE_DRIVER=redis
REDIS_CACHE_CONNECTION=cache

# Session - Usar Redis (opcional)
SESSION_DRIVER=redis
```

## Estrutura de Databases do Redis

O Laravel usa diferentes databases do Redis para diferentes propósitos:

- **Database 0** (`REDIS_DB=0`): Filas e sessões (padrão)
- **Database 1** (`REDIS_CACHE_DB=1`): Cache

## Testar a Conexão

### Via Script de Teste
```bash
php test-redis.php
# Deve retornar: ✅ Redis está funcionando corretamente!
```

### Via Artisan Tinker
```bash
php artisan tinker
>>> use Illuminate\Support\Facades\Redis;
>>> Redis::connection()->ping()
=> "PONG"
```

### Via Redis CLI
```bash
redis-cli ping
# Deve retornar: PONG
```

### Testar Queue
```bash
# Criar um job de teste
php artisan queue:work redis --once

# Ver jobs na fila
redis-cli
> SELECT 0
> KEYS *
```

## Atualizar Supervisor

O arquivo de configuração do Supervisor já está configurado para usar Redis:

```bash
# Se já instalado, reiniciar os workers
sudo supervisorctl restart poprua-worker:*
```

## Comandos Úteis

### Monitorar Redis
```bash
# Monitorar comandos em tempo real
redis-cli MONITOR

# Ver informações do servidor
redis-cli INFO

# Ver todas as chaves
redis-cli KEYS "*"

# Limpar database (cuidado!)
redis-cli FLUSHDB
```

### Gerenciar Filas
```bash
# Processar filas manualmente
php artisan queue:work redis

# Ver jobs falhados
php artisan queue:failed

# Retentar jobs falhados
php artisan queue:retry all

# Limpar jobs falhados
php artisan queue:flush
```

## Performance

Redis é mais rápido que database para filas porque:
- ✅ Operações em memória (muito mais rápido)
- ✅ Suporta blocking operations (mais eficiente)
- ✅ Melhor para alta concorrência
- ✅ Suporta pub/sub para notificações em tempo real

## Segurança

Se o Redis estiver exposto na rede, configure senha:

```env
REDIS_PASSWORD=sua_senha_segura
```

E no arquivo `/etc/redis/redis.conf`:
```
requirepass sua_senha_segura
```

Depois reinicie o Redis:
```bash
sudo systemctl restart redis
```

## Troubleshooting

### Erro: "Connection refused"
```bash
# Verificar se Redis está rodando
sudo systemctl status redis

# Iniciar Redis
sudo systemctl start redis
```

### Erro: "Class 'Redis' not found"
```bash
# Instalar extensão PHP Redis
sudo apt-get install php-redis
# ou
sudo pecl install redis
```

### Limpar cache do Laravel
```bash
php artisan config:clear
php artisan cache:clear
php artisan queue:restart
```
