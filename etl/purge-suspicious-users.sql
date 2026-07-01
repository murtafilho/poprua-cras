-- Remove contas de pentest/scanner (jun/2026) e claude.test@interno.local (id 25).
-- Preserva ids 26-28 (Iara, Raquel) e 81 (Wendy).
-- Rodar em poprua_cras E poprua_geo para nao reimportar no proximo ETL.

BEGIN;

CREATE TEMP TABLE _suspicious AS
SELECT id FROM users WHERE id = 25 OR id BETWEEN 29 AND 80;

DELETE FROM vistorias WHERE user_id IN (SELECT id FROM _suspicious);

DELETE FROM model_has_roles
WHERE model_type = 'App\Models\User' AND model_id IN (SELECT id FROM _suspicious);

DELETE FROM model_has_permissions
WHERE model_type = 'App\Models\User' AND model_id IN (SELECT id FROM _suspicious);

DELETE FROM sessions WHERE user_id IN (SELECT id FROM _suspicious);

DELETE FROM users WHERE id IN (SELECT id FROM _suspicious);

COMMIT;
