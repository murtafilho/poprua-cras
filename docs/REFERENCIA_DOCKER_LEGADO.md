# Docker em Linux Legado — Referência para POPRUA

## Realidade do nosso host

| Item | Valor | Implicação |
|---|---|---|
| Host | Debian 9.13 (Stretch) | **EOL desde junho/2022** (LTS) e arquivado em **março/2024** |
| Kernel | 4.9.0-19-amd64 (2016) | Sem `cgroup v2`; sem várias syscalls modernas |
| Docker Engine | 26.1.4 | Recente, mas com features limitadas pelo kernel |
| Imagens runtime | postgis/postgis:17-3.5, redis:7-alpine, serversideup/php:8.4 | Modernas (2024-2026) — gap de ~8 anos com o kernel |

Cenário "rodar imagens modernas em kernel antigo" é tecnicamente possível
mas tem zonas escondidas: syscalls do glibc/libpq compiladas para kernels
modernos podem fazer fallback ineficiente ou comportamento estranho (vide
o bug do `libpq + sslmode=prefer` que descobrimos em 2026-05-20).

## 8 Boas práticas — o que JÁ aplicamos vs o que falta

### 1. Pin de imagens por digest (`@sha256:...`) em vez de tag mutável

**Por quê:** em agosto/2025 dezenas de imagens oficiais Debian no Docker
Hub ainda traziam o backdoor do XZ Utils meses depois do disclosure.
Quem usava `debian:bookworm` puxava silenciosamente o comprometido; quem
pinava digest tinha o estado conhecido.

**No POPRUA:**
- ✅ Geo: `postgis/postgis:17-3.5@sha256:ab50fc...` e `redis:7-alpine@sha256:8b81dd...`
- ❌ CRAS: tags mutáveis (`postgis/postgis:17-3.5`, `redis:7-alpine`)
- **Ação:** pinar digests também no CRAS

### 2. Rodar como `non-root` no container

**Por quê:** mesmo que o atacante saia do container, é UID não-root no
host. Reduz superfície drasticamente.

**No POPRUA:**
- ✅ Ambos: `USER www-data` no fim do Dockerfile
- ⚠️ Comandos administrativos usam `-u root` (apt/install/chown) — OK
- ✅ Memória reflete: CLAUDE.md sem `-u root` por default

### 3. `cap_drop: [ALL]` + `cap_add` mínimo

**Por quê:** PHP-FPM/nginx só precisam de poucas capabilities. O resto
abre caminho para escalation.

**No POPRUA:**
- ✅ Geo: `cap_drop: [ALL]` + add 7 caps mínimas (CHOWN, DAC_OVERRIDE,
  FOWNER, SETGID, SETUID, KILL, NET_BIND_SERVICE)
- ❌ CRAS: sem cap_drop
- **Ação:** aplicar mesma postura no CRAS

### 4. `security_opt: no-new-privileges:true`

**Por quê:** bloqueia processos dentro do container de ganhar
privilégios via setuid/setgid mesmo se conseguirem.

**No POPRUA:**
- ✅ Geo: aplicado em app, db, redis, ssh
- ❌ CRAS: sem essa flag
- **Ação:** adicionar no CRAS

### 5. Multi-stage builds + base mínima

**Por quê:** menos pacotes = menos CVEs = menos superfície. As "Docker
Hardened Images" (DHI) lançadas em out/2025 levam isso ao extremo.

**No POPRUA:**
- ⚠️ Usamos `serversideup/php:8.4-fpm-nginx` (base completa, ~600MB)
- Não fazemos multi-stage build
- **Realidade:** trocar a imagem base implicaria reescrever tudo. Aceitar
  por enquanto; vale revisitar quando estabilizar pós-cutover.

### 6. Não confiar em alias docker-compose service-level

**Por quê (descoberto hoje):** quando um container está em MÚLTIPLAS
redes via `external`, o alias do nome do serviço (ex: `db`) é
herdado em TODAS as redes. Se duas stacks têm um serviço chamado `db`
e compartilham network, o DNS resolve para múltiplos IPs em
round-robin → conexões aleatoriamente vão pro container errado.

**Aprendido hoje:**
- `pg17-poprua-cras` na rede do Geo herdou alias `db` → 50% das
  conexões do Geo iam pro DB do CRAS → auth fail intermitente
- **Fix:** usar nome de container específico (`pg17-poprua-geo`) no
  `DB_HOST` em vez de alias service-level (`db`)
- ✅ Geo agora: `DB_HOST=pg17-poprua-geo`
- ⚠️ CRAS ainda: `DB_HOST=db` — funciona porque CRAS tem só 1 `db`
  na sua rede principal, mas se um dia compartilhar rede com Geo, o
  mesmo bug volta. Considerar mudar pra `pg17-poprua-cras`.

### 7. libpq vinculada à imagem base, NÃO ao `postgresql-client`

**Por quê (descoberto hoje):** o `pdo_pgsql` do PHP é compilado/linkado
contra a libpq que está na imagem base. Instalar `postgresql-client-17`
adiciona o binário `psql` mas **NÃO substitui** a libpq que o PHP usa.
Por isso o bug do `sslmode=prefer` persistia mesmo após o passo D.

**No POPRUA:**
- **Lição:** quando o PHP precisa de libpq nova, a única forma é trocar
  a imagem base do PHP (ex: ir para serversideup/php:8.4-fpm-nginx-bookworm
  se houver, ou esperar release que use libpq nova)
- **Workaround atual:** `DB_SSLMODE=disable` no config/database.php
- Documentado em ADR-007

### 8. Smoke-test pós-deploy obrigatório

**Por quê:** declarações como "containers healthy + tests passing" NÃO
provam que o pipe end-to-end do usuário funciona. Ver ADR-006 sinal #6.

**No POPRUA:**
- ✅ CRAS: `docker/smoke-test.sh` (4 checks)
- ✅ Geo: `docker/smoke-test.sh` (5 checks, inclui regressão do bug
  libpq via `/api/enderecos/logradouros`)

## Riscos residuais (não-evitáveis sem trocar o host)

| Risco | Probabilidade | Mitigação parcial |
|---|---|---|
| Syscall nova usada por imagem moderna não existe no kernel 4.9 | Baixa-Média | Pin imagens em versões conhecidamente OK; smoke-test detecta |
| Kernel CVEs sem patch (Debian 9 sem updates) | Alta | Acesso ao host restrito; rede atrás de firewall PBH |
| Docker Engine 26 release-cycle vs kernel antigo | Média | Pinar Docker Engine na versão atual; não atualizar sem teste |
| EOL Debian 9 — perda de repos APT | Alta | APT do Debian 9 redirecionando para archive.debian.org; pacotes congelados |
| Glibc/libpq mismatch | Demonstrada hoje | Workarounds documentados em ADRs |

## Quando vale migrar o host

Critérios sugeridos (qualquer um dispara discussão de upgrade):

- Necessidade de feature que exige kernel >= 5.x (cgroup v2,
  io_uring eficiente, SCRAM-SHA-256 channel binding correto)
- Mais de 1 incidente/mês com root cause identificada como "kernel
  4.9 não suporta"
- Política de segurança institucional/auditoria exigir Debian em LTS
- Migração de outro sistema crítico ao mesmo servidor

Até lá: **disciplina de hardening + observabilidade compensa.**

## Cheatsheet rápido (o que NÃO esquecer)

| Operação | Cuidado |
|---|---|
| `docker compose up -d` | Smoke-test logo depois |
| Atualizar imagem base no Dockerfile | Build + smoke em ambiente isolado primeiro |
| Conectar container em rede compartilhada | Cuidado com aliases conflitantes (lição 6) |
| Adicionar serviço novo | Definir `cap_drop`, `security_opt` desde o início |
| Mudar `.env` | Sempre `php artisan config:clear` + reload FPM (ou init-perms) |

## Fontes

- [Docker — Install on Debian](https://docs.docker.com/engine/install/debian/)
- [Debian Wiki — Docker](https://wiki.debian.org/Docker)
- [debian/eol Docker Image (archive.debian.org)](https://hub.docker.com/r/debian/eol/)
- [Docker Security — OWASP Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html)
- [Docker Security Best Practices 2026 — TheLinuxCode](https://thelinuxcode.com/docker-security-best-practices-2026-hardening-the-host-images-and-runtime-without-slowing-teams-down/)
- [Docker container compatibility with old kernels — Forum](https://forums.docker.com/t/libc-incompatibilities-when-will-they-emerge/9895)
- [Cgroup issues with old kernel — docker/cli #3853](https://github.com/docker/cli/issues/3853)
- [Docker Image Security Hardening Best Practices](https://medium.com/@vasanthancomrads/docker-image-security-hardening-best-practices-0cca3a4ec9bb)
- ADR-005 (config:cache só em prod)
- ADR-006 (infraestrutura Docker canonical no CRAS)
- ADR-007 (mesmo no Geo)
