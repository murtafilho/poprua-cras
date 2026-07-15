# Spec — Home institucional SIZEM

**Data:** 2026-07-15  
**Status:** aprovada (abordagem A + lançador de funções)

## Objetivo

Substituir o redirect `/` → mapa por uma **home institucional** exibida ao abrir o app (Capacitor e web): logomarca, nome, versão, conformidade ADPF 976 / PNPSR, adaptação BH, e atalhos com ícones semânticos para as funções principais.

## Comportamento

| Estado | Conteúdo |
|--------|----------|
| Visitante | Marca + versão + bloco ADPF + botão **Entrar** |
| Autenticado | Marca + versão + bloco ADPF + **grade 3×2** de atalhos |

- Rota `/` pública (`name: home`).
- Pós-login e `RedirectIfAuthenticated` → `home`.
- Capacitor (`server.url` …/public/) passa a cair nesta página.

## Conteúdo obrigatório

1. Emblema SIZEM (`<x-application-logo />`)
2. Nome: `config('app.brand')` + subtítulo “Sistema Integrado de Zeladoria Municipal”
3. Versão: `v` + `config('app.version')` (JetBrains Mono)
4. Conformidade (sem chamar de “sistema oficial”):
   - Alinhado à **ADPF 976** / **PNPSR** (Decreto 7.053/2009)
   - Adaptado ao município de **Belo Horizonte** (Portaria Conjunta 009/2026)
5. Rodapé: PBH · GINFI/SUFIS

## Atalhos autenticados (ícones = sidebar)

| Label | Rota |
|-------|------|
| Mapa | `mapa.index` |
| Zeladorias | `vistorias.index` |
| Minhas | `vistorias.minhas` |
| Pontos | `pontos.index` |
| Pessoas | `moradores.index` |
| Dashboard | `dashboard` |

Fora da grade: admin, Power BI, Sobre, etc. (menu lateral).

## Visual (tokens PBH existentes)

- Cores: `#184186`, `#FFE500`, `#EDF1F8`, `#16223A`, `#46566F`
- Tipo: Outfit + JetBrains Mono
- Assinatura: faixa amarela sob a marca + selo “ADPF 976 · PNPSR”
- Layout: coluna única; grade toque ≥ 44px; sem cards no hero de marca
- Respeitar `prefers-reduced-motion`

## Fora de escopo

- Alterar Capacitator `server.url`
- Expôr texto jurídico completo da ADPF na home
- Redesign do menu lateral / bottom-nav
