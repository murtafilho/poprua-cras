-- =============================================================================
-- etl/migrate.sql — Migracao one-shot poprua-geo -> poprua-cras
-- =============================================================================
-- Pre-requisitos:
--   1. Migrations do CRAS aplicadas (php artisan migrate)
--   2. Container CRAS conectado a rede do Geo:
--        sudo docker network connect poprua-geo_poprua-geo php84-poprua-cras
--   3. ETL_SOURCE_PASSWORD configurado no .env (substituido por <<GEO_PWD>>)
--   4. extension postgres_fdw disponivel (vem no postgis/postgis:17-3.5)
--
-- Execucao:  php artisan etl:run --confirm
-- A engine PHP substitui <<GEO_PWD>> pela senha e executa via DB::unprepared
-- (transacao bracketed por BEGIN/COMMIT abaixo).
--
-- Idempotente: TRUNCATE...RESTART IDENTITY no inicio. Re-rodar e seguro.
-- =============================================================================

BEGIN;

-- -----------------------------------------------------------------------------
-- 1. Setup do Foreign Data Wrapper
-- -----------------------------------------------------------------------------
CREATE EXTENSION IF NOT EXISTS postgres_fdw;

DROP SERVER IF EXISTS geo_src CASCADE;
CREATE SERVER geo_src FOREIGN DATA WRAPPER postgres_fdw
    OPTIONS (host 'pg17-poprua-geo', port '5432', dbname 'poprua_geo');

CREATE USER MAPPING FOR CURRENT_USER SERVER geo_src
    OPTIONS (user 'poprua', password '<<GEO_PWD>>');

CREATE SCHEMA IF NOT EXISTS etl_geo;

IMPORT FOREIGN SCHEMA public LIMIT TO (
    roles, permissions, role_has_permissions,
    users, model_has_permissions, model_has_roles,
    geo_bairros, geo_regionais, geo_limite_municipio,
    caracteristica_abrigo, encaminhamentos, tipo_abordagem,
    tipo_abrigo_desmontado, resultados_acoes,
    endereco_atualizados, pontos, moradores, vistorias,
    vistoria_fotos, morador_historicos, media
) FROM SERVER geo_src INTO etl_geo;

-- -----------------------------------------------------------------------------
-- 2. Limpeza do destino (ordem reversa de FK para evitar CASCADE)
-- -----------------------------------------------------------------------------
TRUNCATE
    public.media,
    public.morador_historicos,
    public.vistoria_fotos,
    public.vistorias,
    public.moradores,
    public.pontos,
    public.endereco_atualizados,
    public.tipo_abordagem,
    public.tipo_abrigo_desmontado,
    public.resultados_acoes,
    public.encaminhamentos,
    public.caracteristica_abrigo,
    public.geo_limite_municipio,
    public.geo_regionais,
    public.geo_bairros,
    public.model_has_roles,
    public.model_has_permissions,
    public.role_has_permissions,
    public.users,
    public.permissions,
    public.roles
RESTART IDENTITY CASCADE;

-- -----------------------------------------------------------------------------
-- 3. Carga em ordem topologica (pais primeiro)
-- -----------------------------------------------------------------------------

-- Auth / autorizacao
INSERT INTO public.roles SELECT * FROM etl_geo.roles;
INSERT INTO public.permissions SELECT * FROM etl_geo.permissions;
INSERT INTO public.role_has_permissions SELECT * FROM etl_geo.role_has_permissions;

-- users: + ativo (nova em CRAS, default true para usuarios herdados do Geo)
INSERT INTO public.users (
    id, name, email, email_verified_at, password, remember_token,
    created_at, updated_at, ativo
)
SELECT
    id, name, email, email_verified_at, password, remember_token,
    created_at, updated_at, TRUE
FROM etl_geo.users;
INSERT INTO public.model_has_permissions SELECT * FROM etl_geo.model_has_permissions;
INSERT INTO public.model_has_roles SELECT * FROM etl_geo.model_has_roles;

-- Geo (poligonos urbanos)
INSERT INTO public.geo_bairros SELECT * FROM etl_geo.geo_bairros;
INSERT INTO public.geo_regionais SELECT * FROM etl_geo.geo_regionais;
INSERT INTO public.geo_limite_municipio SELECT * FROM etl_geo.geo_limite_municipio;

-- Corrige self-intersections em geo_bairros vindas da origem (geo-004 do quality-audit).
-- 2026-05-19: detectados em "Morro dos Macacos" (id=188) e "Distrito Industrial do Jatoba" (id=426).
-- ST_MakeValid eh idempotente; o WHERE garante UPDATE so nas geometrias quebradas.
UPDATE public.geo_bairros
SET    geom = ST_Multi(ST_MakeValid(geom))
WHERE  NOT ST_IsValid(geom::geometry);

-- Lookups
INSERT INTO public.caracteristica_abrigo SELECT * FROM etl_geo.caracteristica_abrigo;
INSERT INTO public.encaminhamentos SELECT * FROM etl_geo.encaminhamentos;
INSERT INTO public.tipo_abordagem SELECT * FROM etl_geo.tipo_abordagem;
INSERT INTO public.tipo_abrigo_desmontado SELECT * FROM etl_geo.tipo_abrigo_desmontado;
INSERT INTO public.resultados_acoes SELECT * FROM etl_geo.resultados_acoes;

-- Enderecos
INSERT INTO public.endereco_atualizados SELECT * FROM etl_geo.endereco_atualizados;

-- pontos: + deleted_at (nova em CRAS, soft delete)
INSERT INTO public.pontos (
    id, numero, caracteristica_abrigo_id, complemento, endereco_atualizado_id,
    updated_at, created_at, lat, lng, geom, observacao, deleted_at
)
SELECT
    id, numero, caracteristica_abrigo_id, complemento, endereco_atualizado_id,
    updated_at, created_at, lat, lng, geom, observacao, NULL
FROM etl_geo.pontos;

-- moradores: omitir fotografia (dropada em CRAS na migration 2026_05_18_000001)
INSERT INTO public.moradores (
    id, ponto_atual_id, nome_social, nome_registro, apelido, genero,
    observacoes, documento, contato, created_at, updated_at, deleted_at
)
SELECT
    id, ponto_atual_id, nome_social, nome_registro, apelido, genero,
    observacoes, documento, contato, created_at, updated_at, deleted_at
FROM etl_geo.moradores;

-- vistorias: + 7 colunas (finalizacao/zeladoria/lavratura/protocolo)
INSERT INTO public.vistorias (
    id, data_abordagem, nomes_pessoas, quantidade_pessoas, tipo_abordagem_id,
    casal, qtd_casais, classificacao, num_reduzido, catador_reciclados,
    resistencia, fixacao_antiga, excesso_objetos, trafico_ilicitos,
    crianca_adolescente, idosos, gestante, lgbtqiapn, cena_uso_caracterizada,
    qtd_abrigos_provisorios, abrigos_tipos, deficiente, agrupamento_quimico,
    saude_mental, animais, qtd_animais, conducao_forcas_seguranca,
    conducao_forcas_observacao, apreensao_fiscal, auto_fiscalizacao_aplicado,
    auto_fiscalizacao_numero, e1_id, e2_id, e3_id, e4_id, material_apreendido,
    material_descartado, tipo_abrigo_desmontado_id, qtd_kg, resultado_acao_id,
    movimento_migratorio, observacao, ponto_id, user_id, created_at, updated_at,
    e5_id, e6_id, deleted_at,
    finalizada, finalizada_em, finalizada_por,
    data_prevista_zeladoria, periodo_zeladoria,
    houve_lavratura, tipo_protocolo,
    cancelada, cancelada_em, cancelada_por,
    houve_comunicado, data_comunicado
)
SELECT
    id, data_abordagem, nomes_pessoas, quantidade_pessoas, tipo_abordagem_id,
    casal, qtd_casais, classificacao, num_reduzido, catador_reciclados,
    resistencia, fixacao_antiga, excesso_objetos, trafico_ilicitos,
    crianca_adolescente, idosos, gestante, lgbtqiapn, cena_uso_caracterizada,
    qtd_abrigos_provisorios, abrigos_tipos, deficiente, agrupamento_quimico,
    saude_mental, animais, qtd_animais, conducao_forcas_seguranca,
    conducao_forcas_observacao, apreensao_fiscal, auto_fiscalizacao_aplicado,
    auto_fiscalizacao_numero, e1_id, e2_id, e3_id, e4_id, material_apreendido,
    material_descartado, tipo_abrigo_desmontado_id, qtd_kg, resultado_acao_id,
    movimento_migratorio, observacao, ponto_id, user_id, created_at, updated_at,
    e5_id, e6_id, deleted_at,
    -- finalizada (bool NOT NULL default false), finalizada_em (ts null),
    -- finalizada_por (FK null), data_prevista_zeladoria (date null),
    -- periodo_zeladoria (str null), houve_lavratura (bool NOT NULL default false),
    -- tipo_protocolo (str null)
    FALSE, NULL, NULL, NULL, NULL, FALSE, NULL,
    -- cancelada (bool NOT NULL default false), cancelada_em (ts null),
    -- cancelada_por (FK null)
    FALSE, NULL, NULL,
    -- houve_comunicado (bool NOT NULL default false), data_comunicado (date null)
    -- (workflow zeladoria — etapa "comunicado de obstrução")
    FALSE, NULL
FROM etl_geo.vistorias;

INSERT INTO public.vistoria_fotos SELECT * FROM etl_geo.vistoria_fotos;
-- morador_historicos: data_entrada/data_saida sao DATE no Geo e TIMESTAMP no CRAS.
-- Cast explicito evita ambiguidade no FDW e documenta a divergencia.
INSERT INTO public.morador_historicos (
    id, morador_id, ponto_id, vistoria_entrada_id, vistoria_saida_id,
    data_entrada, data_saida, created_at, updated_at
)
SELECT
    id, morador_id, ponto_id, vistoria_entrada_id, vistoria_saida_id,
    data_entrada::timestamp, data_saida::timestamp, created_at, updated_at
FROM etl_geo.morador_historicos;
INSERT INTO public.media SELECT * FROM etl_geo.media;

-- -----------------------------------------------------------------------------
-- 4. Reset de sequencias (sincroniza id_seq com MAX(id))
-- -----------------------------------------------------------------------------
DO $reset$
DECLARE t text;
BEGIN
    FOR t IN
        SELECT unnest(ARRAY[
            'roles', 'permissions', 'users',
            'geo_bairros', 'geo_regionais', 'geo_limite_municipio',
            'caracteristica_abrigo', 'encaminhamentos', 'tipo_abordagem',
            'tipo_abrigo_desmontado', 'resultados_acoes',
            'endereco_atualizados', 'pontos', 'moradores', 'vistorias',
            'vistoria_fotos', 'morador_historicos', 'media'
        ])
    LOOP
        EXECUTE format(
            'SELECT setval(pg_get_serial_sequence(''public.%I'', ''id''),
                           COALESCE((SELECT MAX(id) FROM public.%I), 1))',
            t, t
        );
    END LOOP;
END
$reset$;

COMMIT;

-- Contagens e validacao PostGIS rodam separadamente via etl:run (PHP imprime).
