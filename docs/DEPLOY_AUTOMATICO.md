# Deploy automatico (push → producao)

O **push sozinho nao atualiza o servidor**. Para automatizar, escolha uma das opcoes abaixo.

## Comparacao

| Opcao | Latencia | Complexidade | Requer |
|-------|----------|--------------|--------|
| **A. Cron (poll)** | ~3 min | Baixa | deploy key (ja configurada) |
| **B. GitHub Actions runner** | ~30 s | Media | token de registro do runner |

Ambas rodam **dentro do vlcp-sufis01** (rede RMI) e fazem `git pull` + `deploy.sh`.

---

## Opcao A — Cron (recomendada para comecar)

O servidor verifica o GitHub a cada 3 minutos. Se `origin/main` avancou, roda o deploy.

### Instalar (uma vez, na RMI)

```bash
ssh sufis
sudo bash /var/www/html/joomla_sufis/ginfi/poprua-cras/docker/install-auto-deploy-cron.sh
```

### Testar

```bash
# Na sua maquina: push qualquer commit em main
bash poprua push

# No servidor (ou aguardar ate 3 min):
ssh sufis "sudo tail -20 /var/log/poprua-cras-poll-deploy.log"
```

### Desinstalar

```bash
ssh sufis "sudo rm -f /etc/cron.d/poprua-cras-auto-deploy"
```

---

## Opcao B — GitHub Actions self-hosted runner (deploy imediato)

O host e **Debian 9** — o runner nativo nao inicia (glibc antiga). Usamos runner em **Docker** (`github-runner-poprua-cras`).

### Instalar / reinstalar

```bash
# Token novo (uso unico): GitHub > Settings > Actions > Runners > New self-hosted runner
ssh sufis
sudo RUNNER_TOKEN='SEU_TOKEN' bash /var/www/html/joomla_sufis/ginfi/poprua-cras/docker/install-github-runner-docker.sh
```

### Verificar

- GitHub > Settings > Actions > Runners → `vlcp-sufis01-poprua-cras` (Idle)
- `ssh sufis "sudo docker logs github-runner-poprua-cras --tail 10"`

Cada push em `main` dispara `.github/workflows/deploy-production.yml`.

---

## Fluxo completo (com auto-deploy ativo)

```
sua maquina  --push-->  GitHub  --(cron ou runner)-->  deploy.sh  -->  producao
```

Voce so precisa:

```bash
git commit ...
bash poprua push
```

O servidor atualiza sozinho em seguida (imediato com runner, ou em ate 3 min com cron).

---

## Comandos uteis

```bash
# Deploy manual (sempre disponivel)
bash poprua deploy

# Ver log do cron
ssh sufis "sudo tail -f /var/log/poprua-cras-poll-deploy.log"

# Ver se servidor esta em dia com GitHub
ssh sufis "sudo git -C /var/www/html/joomla_sufis/ginfi/poprua-cras -c safe.directory=* rev-parse HEAD origin/main"
```
