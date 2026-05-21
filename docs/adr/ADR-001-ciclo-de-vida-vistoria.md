# ADR-001: Ciclo de vida e propriedade da Vistoria

**Data:** 2026-05-19
**Status:** Aceito

**Contexto:** Sem regras explícitas de propriedade e transição de estado, qualquer usuário autenticado podia editar ou cancelar qualquer vistoria, inclusive vistorias de outros usuários e vistorias já finalizadas. Isso gerava risco de perda de dados e auditoria inconsistente.

**Decisão:** Definir um modelo formal de ciclo de vida com as seguintes regras de transição:

- Dono edita e finaliza vistoria no estado *aberto*.
- Admin reativa vistoria no estado *finalizado* (retorna para *aberto*).
- Dono cancela vistoria no estado *aberto*.
- Admin cancela vistoria no estado *finalizado*.

Nenhum outro ator ou estado é permitido sem concessão explícita de permissão via policy.

**Implementado em:** `app/Policies/VistoriaPolicy.php` + rotas `finalizar`, `reativar` e `cancelar`.

**Consequências:**

- Fica mais fácil: auditar quem fez cada transição; garantir que vistorias finalizadas não sejam alteradas silenciosamente.
- Fica mais difícil: usuários comuns não podem mais corrigir erros em vistorias já finalizadas — precisam acionar um admin para reativar.
- O que muda: toda lógica de autorização de vistoria passa pela `VistoriaPolicy`; controllers não devem mais verificar permissões inline.
