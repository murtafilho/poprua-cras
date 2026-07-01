-- =============================================================================
-- etl/remap-model-has-roles.sql — Remap de atribuicoes de papel Geo -> CRAS
-- =============================================================================
-- Executado pelo cutover.sh fase 5c, APOS PermissoesSeeder (fase 5b).
-- Pre-requisito: schema etl_geo com roles + model_has_roles (FDW do etl:run).
--
-- RBAC canônico: roles/permissions vêm do PermissoesSeeder do CRAS.
-- Apenas as atribuicoes usuario<->papel sao herdadas do Geo, com remap
-- de role_id via JOIN em roles.name (IDs do Geo != IDs do CRAS).
-- =============================================================================

TRUNCATE public.model_has_roles;

INSERT INTO public.model_has_roles (role_id, model_type, model_id)
SELECT cr.id, g.model_type, g.model_id
FROM etl_geo.model_has_roles g
JOIN etl_geo.roles gr ON gr.id = g.role_id
JOIN public.roles cr ON cr.name = gr.name AND cr.guard_name = gr.guard_name;
