# ADR-010 — Permissoes compartilhadas host/container via ACL POSIX + grupo www-data

**Status:** Aceito
**Data:** 2026-05-20
**Contexto:** poprua-cras (codebase canonico; Geo nao e tratado aqui — Geo so existe como fonte upstream do ETL)

## Contexto

O codigo-fonte fica em `/var/www/html/joomla_sufis/ginfi/poprua-cras/` no host `vlcp-sufis01` (Debian 9.13, ext4) e e bind-mounted no container PHP (`php84-poprua-cras`). Dois processos diferentes escrevem na mesma arvore:

- **Container (`www-data`)**: UID 33, GID 33 — PHP-FPM, queue worker, artisan, composer, npm.
- **Host (`cassio.martins`)**: UID 999330183 (Active Directory), GID primario `forum` (2000), membro suplementar do grupo `www-data` (GID 33). Edita via editor, executa `git`, scripts.

### Sintomas que se repetiram durante todo o projeto

1. Container escreve arquivo (`storage/`, `bootstrap/cache/`, vendor pos `composer install`, build pos `npm run build`, anexos Spatie). Fica `33:33` com umask 022 → modo `644`. Host nao pode editar (`Permission denied`); `git checkout` falha com `unable to unlink`.
2. Host edita arquivo. Fica `999330183:2000` (`forum`). Container PHP-FPM nao pode escrever — queue falha ao mover anexo, conversao Spatie falha.
3. `docker exec -u root` deixa arquivos `root:root` (modo 644). Ambos os lados ficam bloqueados.
4. `git rebase --autostash` quebra mid-flight com `Permission denied`, deixando working tree corrompida.

Solucoes ad-hoc anteriores (`sudo chown` quando da pau, `init-perms` cobrindo so storage/) sao reativas e fragmentadas. Esta ADR estabelece a solucao definitiva.

## Decisao

**Combinar tres mecanismos POSIX classicos para cross-write entre processos:**

1. **Grupo comum `www-data` (GID 33)** como dono compartilhado de toda a arvore.
2. **ACL POSIX default** (`setfacl -d -m g:www-data:rwx`) nos diretorios — arquivos novos herdam automaticamente write para o grupo, independente do umask de quem cria.
3. **Setgid bit** nos diretorios (`chmod g+s` / modo `2775`) — arquivos novos herdam o grupo do dir parent (nao o grupo primario do criador).
4. **umask 002 no container** — arquivos criados pelo PHP-FPM/queue/CLI nascem `664`/`775`.

Excluidos do tratamento: `.git/`, `vendor/`, `node_modules/`, `storage/framework/cache/`, `bootstrap/cache/` (gerados por ferramentas que ja gerenciam suas perms).

### Mudancas concretas

| Arquivo | Mudanca |
|---|---|
| `docker/bootstrap-host-perms.sh` (novo) | Script idempotente que aplica chgrp/setgid/ACL no host. Roda uma vez como root; pode ser re-executado a qualquer momento. |
| `docker-compose.yml` → `init-perms` | Adicionar instalacao do pacote `acl` no alpine e replay do setup acima a cada `up -d`. Self-healing. |
| `docker/Dockerfile` | Adicionar pacote `acl` + `umask 002` em `/etc/profile.d/umask.sh` + entrypoint dos workers. |
| Host: pacote `acl` | Necessario para `setfacl`/`getfacl`. Em Debian 9: `apt-get install acl`. Ja instalado. |
| `docs/ARQUITETURA_DOCKER.md` | Documentar contrato: usuarios humanos devem ser membros de `www-data` (gid 33). |

### Por que NAO alinhar UID 33 ao UID do host

A alternativa "recriar `www-data` com UID 999330183 no container" foi rejeitada porque:

- UID e da Active Directory, fora do range usual de container UIDs (0-65535) — alguns syscalls de fs ficam confusos.
- A imagem base `serversideup/php` tem dezenas de paths chown 33:33 hard-baked (cache composer, sessions, etc.) — `usermod -u` deixaria tudo orfa, exigindo re-chown do filesystem inteiro da imagem.
- Quebra portabilidade — outros devs com UIDs diferentes nao rodariam.

A solucao de grupo + ACL e portavel: cada dev so precisa estar no grupo `www-data` (gid 33) na maquina dele.

### Por que NAO somente setgid + chgrp (sem ACL)

Setgid garante que arquivos NEW herdam o GROUP correto, mas nao garantem que tenham `g+w`. Sem ACL default, mesmo com setgid, novos arquivos nascem `rw-r--r--` (umask 022 padrao do host) — grupo NAO pode escrever. ACL default `g:www-data:rwx` injeta o `g+w` na criacao, dispensando umask correto no host.

## Consequencias

**Positivas:**
- Editar/criar arquivos no host e dentro do container deixa de gerar conflito.
- `git rebase`, `git checkout`, `mv`, `rm` funcionam sem `sudo` para ambos os lados.
- Self-healing via `init-perms`: corrige drift a cada `docker compose up -d`.
- Solucao baseada em primitivas POSIX classicas, sem mexer em UID/GID — portavel.

**Negativas:**
- Arquivos do projeto ficam group-writable (`664`/`2775`). Aceitavel em servidor interno PBH controlado.
- `init-perms` fica mais pesado (~10s extra em arvore grande). Aceitavel — uma vez no `up`.
- ACLs nao sao preservados por `git` — toda vez que `git checkout` cria arquivos novos, eles herdam ACL do diretorio parent (ok), mas se o dir e novo ele precisa ser tratado por `init-perms` ou pelo proximo `setfacl -R`.

## Validacao

```bash
# Host: criar arquivo
touch /var/www/html/joomla_sufis/ginfi/poprua-cras/test-host.txt
ls -la test-host.txt  # deve mostrar group www-data + rw para grupo

# Container: criar arquivo
sudo docker exec php84-poprua-cras touch storage/logs/test-container.log
ls -la storage/logs/test-container.log  # deve mostrar www-data:www-data + rw para grupo

# Host: editar arquivo criado pelo container (sem sudo)
echo ok >> storage/logs/test-container.log

# Container: editar arquivo criado pelo host (sem -u root)
sudo docker exec php84-poprua-cras sh -c 'echo ok >> test-host.txt'

# Git operations sem sudo
git checkout HEAD~1 -- some-file && git checkout main -- some-file

# Cleanup
rm test-host.txt storage/logs/test-container.log
```

## Referencias

- [ADR-006](ADR-006-infraestrutura-docker-canonical.md) — docker-compose como fonte da verdade
- [POSIX ACL — setfacl(1)](https://man7.org/linux/man-pages/man1/setfacl.1.html)
- [Setgid bit explanation](https://www.gnu.org/software/coreutils/manual/html_node/Directory-Setuid-and-Setgid.html)
