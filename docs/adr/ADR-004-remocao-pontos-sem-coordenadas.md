# ADR-004: Remoção de pontos sem coordenadas

**Data:** 2026-05-19
**Status:** Aceito

**Contexto:** 19 pontos no banco de dados não possuíam latitude/longitude (dados legados importados sem geocodificação). Esses pontos eram impossíveis de exibir no mapa Leaflet e não podiam ser geocodificados retroativamente por falta de referência de endereço confiável. Sua presença causava erros silenciosos em queries PostGIS e poluía relatórios de cobertura territorial.

**Decisão:** Executar hard-delete dos 19 pontos sem coordenadas. As 31 vistorias vinculadas a esses pontos recebem soft-delete (preservadas para fins de auditoria histórica, mas excluídas dos fluxos operacionais).

Invariante futura: pontos sem `lat`/`lng` não podem ser criados. A validação é aplicada em `StoreVistoriaRequest` (campos `lat` e `lng` marcados como `required|numeric`), impedindo que o problema se repita via interface ou API.

**Consequências:**

- Fica mais fácil: queries PostGIS não precisam mais filtrar geometrias nulas; o mapa exibe apenas pontos válidos; relatórios de cobertura são precisos.
- Fica mais difícil: os 19 pontos são irrecuperáveis (hard-delete sem backup individual); as 31 vistorias soft-deletadas não aparecem nos fluxos normais, mas podem ser restauradas por admin via banco se necessário.
- O que muda: `StoreVistoriaRequest` passa a ser a fronteira de integridade para coordenadas; qualquer integração que envie pontos sem coords receberá erro 422.
