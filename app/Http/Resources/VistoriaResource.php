<?php

namespace App\Http\Resources;

use App\Models\Vistoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vistoria */
class VistoriaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ponto_id' => $this->ponto_id,
            /** @phpstan-ignore instanceof.alwaysFalse (data_abordagem is cast to Carbon at runtime) */
            'data_abordagem' => ($date = $this->data_abordagem) instanceof \DateTimeInterface ? $date->format('Y-m-d H:i') : $date,
            'quantidade_pessoas' => $this->quantidade_pessoas,
            'nomes_pessoas' => $this->nomes_pessoas,
            'qtd_kg' => $this->qtd_kg,
            'observacao' => $this->observacao,

            // IDs de relacionamentos
            'tipo_abordagem_id' => $this->tipo_abordagem_id,
            'resultado_acao_id' => $this->resultado_acao_id,
            'tipo_abrigo_desmontado_id' => $this->tipo_abrigo_desmontado_id,

            // Nomes dos relacionamentos (quando carregados)
            'tipo_abordagem' => $this->whenLoaded('tipoAbordagem', fn () => $this->tipoAbordagem?->nome),
            'resultado_acao' => $this->whenLoaded('resultadoAcao', fn () => $this->resultadoAcao?->nome),
            'tipo_abrigo_desmontado' => $this->whenLoaded('tipoAbrigoDesmontado', fn () => $this->tipoAbrigoDesmontado?->nome),

            // Indicadores de complexidade
            'complexidade' => [
                'resistencia' => (bool) $this->resistencia,
                'num_reduzido' => (bool) $this->num_reduzido,
                'casal' => (bool) $this->casal,
                'catador_reciclados' => (bool) $this->catador_reciclados,
                'fixacao_antiga' => (bool) $this->fixacao_antiga,
                'excesso_objetos' => (bool) $this->excesso_objetos,
                'trafico_ilicitos' => (bool) $this->trafico_ilicitos,
                'crianca_adolescente' => (bool) $this->crianca_adolescente,
                'idosos' => (bool) $this->idosos,
                'gestante' => (bool) $this->gestante,
                'lgbtqiapn' => (bool) $this->lgbtqiapn,
                'cena_uso_caracterizada' => (bool) $this->cena_uso_caracterizada,
                'deficiente' => (bool) $this->deficiente,
                'agrupamento_quimico' => (bool) $this->agrupamento_quimico,
                'saude_mental' => (bool) $this->saude_mental,
                'animais' => (bool) $this->animais,
            ],

            // Dados de fiscalização
            'conducao_forcas_seguranca' => (bool) $this->conducao_forcas_seguranca,
            'apreensao_fiscal' => (bool) $this->apreensao_fiscal,
            'auto_fiscalizacao_aplicado' => (bool) $this->auto_fiscalizacao_aplicado,

            // Abrigos
            'qtd_abrigos_provisorios' => $this->qtd_abrigos_provisorios,
            'abrigos_tipos' => $this->abrigos_tipos,

            // Relationships
            'ponto' => new PontoResource($this->whenLoaded('ponto')),
            'usuario' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
