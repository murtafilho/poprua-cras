# ADR-003: Healthcheck do container queue via pgrep

**Data:** 2026-05-19
**Status:** Aceito

**Contexto:** A imagem base utilizada pelo container `queue-poprua-cras` define um `HEALTHCHECK` padrão com `curl localhost:8080`. O container, porém, executa `php artisan queue:work` — um processo de linha de comando sem endpoint HTTP. O resultado era status `UNHEALTHY` persistente (streak 4143), gerando alarmes falsos e dificultando a distinção entre falhas reais e falhas de sondagem.

**Decisão:** Sobrescrever o `HEALTHCHECK` herdado da imagem base diretamente no `docker-compose.yml`, substituindo o `curl` por:

```
["CMD", "pgrep", "-f", "queue:work"]
```

O `pgrep` verifica apenas se o processo existe, sem depender de rede.

**Consequências:**

- Fica mais fácil: distinguir containers realmente doentes de containers saudáveis sem HTTP; o orquestrador pode reiniciar o container automaticamente se o worker travar.
- Fica mais difícil: `pgrep` não valida se o worker está processando jobs — apenas que o processo está vivo. Um worker em deadlock ainda seria reportado como saudável.
- O que muda: status do container passa para `healthy`, streak zerado; nenhuma alteração na imagem Docker foi necessária.
