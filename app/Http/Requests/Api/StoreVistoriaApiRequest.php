<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\StoreVistoriaRequest;

/**
 * Criação de vistoria via JSON (fila offline). Reaproveita as regras e as
 * validações compostas de StoreVistoriaRequest e exige o client_uuid usado
 * para idempotência da sincronização.
 */
class StoreVistoriaApiRequest extends StoreVistoriaRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'client_uuid' => 'required|uuid',
        ]);
    }
}
