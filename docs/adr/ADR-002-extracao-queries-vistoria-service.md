# ADR-002: Extração de queries para VistoriaService

**Data:** 2026-05-19
**Status:** Aceito

**Contexto:** `VistoriaController` havia crescido para 731 linhas com 28 chamadas `DB::` diretas espalhadas pelo código. As queries de listagem estavam duplicadas nos métodos `create` e `edit`, dificultando manutenção e testes unitários. A mistura de lógica de banco com lógica HTTP violava o princípio de responsabilidade única.

**Decisão:** Criar `app/Services/VistoriaService.php` extraindo toda a lógica de consulta ao banco. O ponto central da extração é o método `buildBaseListQuery()`, compartilhado entre `listarMinhas()` e `listarComFiltros()`, eliminando a duplicação. O controller passa a depender apenas do service, sem acesso direto ao `DB::`.

**Consequências:**

- Fica mais fácil: testar a lógica de queries isoladamente (sem HTTP); reutilizar consultas em futuros endpoints de API; identificar regressões de performance.
- Fica mais difícil: o fluxo de execução envolve uma camada a mais, exigindo que novos devs entendam a separação controller/service.
- O que muda: controller reduzido de 731 para 521 linhas; zero chamadas `DB::` diretas no controller; `VistoriaService` passa a ser o único responsável por queries de vistoria.
