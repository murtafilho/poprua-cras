<?php

declare(strict_types=1);

return [

    'ordem_grupos' => [
        'geral',
        'workflow',
        'mapa',
        'listagem',
        'limites',
        'complexidade',
    ],

    'grupos' => [
        'geral' => [
            'label' => 'Geral',
            'desc' => 'Nome e identificação institucional do sistema.',
        ],
        'workflow' => [
            'label' => 'Workflow',
            'desc' => 'Regras do fluxo de abordagem: dias para Informação Precária, obrigatoriedade de comunicado antes da zeladoria.',
        ],
        'mapa' => [
            'label' => 'Mapa',
            'desc' => 'Coordenadas centrais e zoom padrão da visualização geoespacial. O mapa é restrito aos limites de Belo Horizonte.',
        ],
        'listagem' => [
            'label' => 'Listagem',
            'desc' => 'Paginação e quantidade de registros exibidos por página nas telas de zeladorias e pontos.',
        ],
        'limites' => [
            'label' => 'Limites',
            'desc' => 'Restrições de tamanho de arquivo para upload de fotos de vistorias e pessoas.',
        ],
        'complexidade' => [
            'label' => 'Complexidade',
            'desc' => 'Pesos individuais dos 16 fatores de vulnerabilidade do ponto e thresholds de classificação (Crítico ≥ 8, Alto ≥ 5, Médio ≥ 3). Fundamentado no VI-SPDAT e na Portaria Conjunta nº 009/2026. Fatores com peso maior indicam maior gravidade da situação.',
        ],
    ],

    'contextos' => [
        'app_nome' => 'Exibido no header do sistema, título das páginas e rodapé',
        'app_orgao' => 'Exibido no rodapé e nos relatórios impressos',
        'info_precaria_dias' => 'Listagem de pontos e zeladorias: pontos sem vistoria há mais de N dias recebem status "Informação Precária" e prioridade de visita',
        'exigir_comunicado' => 'Formulário de vistoria: se Sim, o sistema impede agendar zeladoria sem comunicado prévio entregue',
        'rascunho_debounce_ms' => 'Formulário de nova zeladoria: intervalo de autosave do rascunho (milissegundos)',
        'rascunho_dias_expiracao' => 'Job rascunhos:limpar — dias sem atualização para expirar rascunhos salvos',
        'mapa_centro_lat' => 'Mapa: latitude inicial ao abrir a tela de mapa',
        'mapa_centro_lng' => 'Mapa: longitude inicial ao abrir a tela de mapa',
        'mapa_zoom_padrao' => 'Mapa: nível de zoom ao abrir (12 = cidade inteira, 18 = rua)',
        'vistorias_por_pagina' => 'Listagem de zeladorias e pontos: quantidade de registros exibidos por página',
        'paginacao_max' => 'Todas as listagens: limite máximo de itens por página (segurança)',
        'foto_max_tamanho_kb' => 'Upload de fotos em vistorias e pessoas: tamanho máximo por arquivo em KB (10240 = 10MB)',
        'complexidade_critico' => 'Cards de pontos: pontuação a partir da qual o ponto é classificado como vermelho (crítico)',
        'complexidade_alto' => 'Cards de pontos: pontuação a partir da qual o ponto é classificado como amarelo (alto)',
        'complexidade_medio' => 'Cards de pontos: pontuação a partir da qual o ponto é classificado como azul (médio)',
        'peso_resistencia' => 'Cálculo de complexidade: multiplicador quando há resistência à abordagem no ponto',
        'peso_num_reduzido' => 'Cálculo de complexidade: multiplicador quando há número reduzido de pessoas',
        'peso_casal' => 'Cálculo de complexidade: multiplicador quando há casais no ponto',
        'peso_catador_reciclados' => 'Cálculo de complexidade: multiplicador quando há catadores de recicláveis (Art. 3º, VII Portaria 009/2026)',
        'peso_fixacao_antiga' => 'Cálculo de complexidade: multiplicador quando há fixação antiga — cronificação (VI-SPDAT domínio 1)',
        'peso_excesso_objetos' => 'Cálculo de complexidade: multiplicador quando há excesso de objetos/pertences',
        'peso_trafico_ilicitos' => 'Cálculo de complexidade: multiplicador quando há tráfico — fator de violência',
        'peso_crianca_adolescente' => 'Cálculo de complexidade: multiplicador quando há crianças/adolescentes — prioridade absoluta (ECA, CONANDA)',
        'peso_idosos' => 'Cálculo de complexidade: multiplicador quando há idosos (60+)',
        'peso_gestante' => 'Cálculo de complexidade: multiplicador quando há gestantes — alto risco materno-fetal',
        'peso_lgbtqiapn' => 'Cálculo de complexidade: multiplicador quando há população LGBTQIAPN+',
        'peso_deficiente' => 'Cálculo de complexidade: multiplicador quando há pessoa com deficiência',
        'peso_agrupamento_quimico' => 'Cálculo de complexidade: multiplicador quando há agrupamento com dependência química',
        'peso_saude_mental' => 'Cálculo de complexidade: multiplicador quando há questões de saúde mental (VI-SPDAT wellness)',
        'peso_cena_uso_caracterizada' => 'Cálculo de complexidade: multiplicador quando há cena de uso caracterizada',
        'peso_animais' => 'Cálculo de complexidade: multiplicador quando há animais — barreira para acolhimento',
    ],

    'listagem' => [
        'default_per_page' => 5,
        'max_per_page' => 100,
    ],

];
