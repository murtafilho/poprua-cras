# Instalação do Supervisor para Workers do Laravel

## 1. Criar link simbólico para o arquivo de configuração

```bash
sudo ln -s /home/murta/projects/popruav2/supervisor/poprua-worker.conf /etc/supervisor/conf.d/poprua-worker.conf
```

**OU** copiar o arquivo (se preferir não usar link simbólico):

```bash
sudo cp /home/murta/projects/popruav2/supervisor/poprua-worker.conf /etc/supervisor/conf.d/poprua-worker.conf
```

## 2. Criar o diretório de logs (se não existir)

```bash
mkdir -p /home/murta/projects/popruav2/storage/logs
chmod 775 /home/murta/projects/popruav2/storage/logs
```

## 3. Recarregar e reiniciar o Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start poprua-worker:*
```

## 4. Verificar o status

```bash
sudo supervisorctl status
```

## 5. Comandos úteis

### Parar os workers
```bash
sudo supervisorctl stop poprua-worker:*
```

### Reiniciar os workers
```bash
sudo supervisorctl restart poprua-worker:*
```

### Ver logs
```bash
tail -f /home/murta/projects/popruav2/storage/logs/worker.log
```

### Ver logs do Supervisor
```bash
sudo tail -f /var/log/supervisor/supervisord.log
```

## Configuração

O arquivo de configuração está configurado para:
- **2 processos** (`numprocs=2`) - processa 2 jobs simultaneamente
- **3 tentativas** (`--tries=3`) - tenta processar cada job até 3 vezes
- **3 segundos de sleep** (`--sleep=3`) - espera 3 segundos quando não há jobs
- **1 hora de tempo máximo** (`--max-time=3600`) - reinicia o worker após 1 hora
- **Conexão database** - usa a fila do banco de dados

## Ajustar configuração

Para alterar o número de processos ou outras opções, edite o arquivo:

```bash
sudo nano /etc/supervisor/conf.d/poprua-worker.conf
```

Depois recarregue:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart poprua-worker:*
```
