# ADR-005: config:cache apenas em produção via SSH

**Data:** 2026-05-19
**Status:** Aceito

**Contexto:** Rodar `php artisan config:cache` dentro do container local serializa o `.env` de desenvolvimento e grava o cache em `bootstrap/cache/config.php`. Quando a suíte de testes é executada em seguida, o Laravel carrega o cache em vez de ler o `.env`, fazendo os testes apontarem para o banco de produção (`poprua_cras`) em vez do banco isolado de testes (`poprua_cras_test`). Isso expõe dados reais durante os testes e pode causar mutações acidentais no banco de produção.

**Decisão:** `config:cache` é uma operação exclusiva de produção e deve ser executada sempre remotamente via SSH no servidor `vlcp-sufis01`:

```bash
ssh sufis-poprua-cras php artisan config:cache
```

Localmente (dentro do container de desenvolvimento), o cache de configuração nunca deve estar ativo. Antes de rodar a suíte de testes, garantir que o cache esteja limpo:

```bash
sudo docker exec -w /var/www/html/joomla_sufis/ginfi/poprua-cras php84-poprua-cras php artisan config:clear
```

**Consequências:**

- Fica mais fácil: testes sempre usam o banco correto (`poprua_cras_test`); o risco de mutação acidental em produção durante testes é eliminado.
- Fica mais difícil: desenvolvedores precisam lembrar de não rodar `config:cache` localmente; pipelines de CI que rodam no mesmo ambiente que produção precisam garantir `config:clear` antes dos testes.
- O que muda: `config:cache` sai do fluxo de desenvolvimento local e passa a ser documentado apenas como etapa de deploy; `config:clear` entra como pré-condição obrigatória para execução dos testes.
