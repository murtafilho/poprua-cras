<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVistoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Garante que cada participante tenha a permission 'participar de equipes vistoria'.
     * Defesa em profundidade — o form já filtra os elegíveis, esta valida POST direto.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ids = collect($this->input('participantes', []))->filter()->unique()->values()->all();
            if (empty($ids)) {
                return;
            }
            $autorizados = User::query()
                ->where('ativo', true)
                ->permission('participar de equipes vistoria')
                ->whereIn('id', $ids)
                ->pluck('id')
                ->all();
            $invalidos = array_diff($ids, $autorizados);
            if (! empty($invalidos)) {
                $v->errors()->add('participantes', 'Há participantes selecionados que não estão autorizados a participar de equipes de vistoria.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ponto_id' => 'nullable|exists:pontos,id',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'data_abordagem' => 'required|date_format:Y-m-d\TH:i',
            'tipo_abordagem_id' => 'required|exists:tipo_abordagem,id',
            'quantidade_pessoas' => 'nullable|integer|min:0',
            'nomes_pessoas' => 'nullable|string',
            'resultado_acao_id' => 'required|exists:resultados_acoes,id',
            'tipo_abrigo_desmontado_id' => 'nullable|exists:tipo_abrigo_desmontado,id',
            'qtd_kg' => 'nullable|integer|min:0',
            'observacao' => 'nullable|string',
            'complemento_ponto' => 'nullable|string|max:255',
            'fotos.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'legendas_fotos' => 'nullable|array',
            'legendas_fotos.*' => 'nullable|string|max:500',
            // Campos boolean de complexidade
            'resistencia' => 'nullable|boolean',
            'num_reduzido' => 'nullable|boolean',
            'casal' => 'nullable|boolean',
            'qtd_casais' => 'nullable|integer|min:0',
            'catador_reciclados' => 'nullable|boolean',
            'fixacao_antiga' => 'nullable|boolean',
            'excesso_objetos' => 'nullable|boolean',
            'trafico_ilicitos' => 'nullable|boolean',
            'crianca_adolescente' => 'nullable|boolean',
            'idosos' => 'nullable|boolean',
            'gestante' => 'nullable|boolean',
            'lgbtqiapn' => 'nullable|boolean',
            'cena_uso_caracterizada' => 'nullable|boolean',
            'deficiente' => 'nullable|boolean',
            'agrupamento_quimico' => 'nullable|boolean',
            'saude_mental' => 'nullable|boolean',
            'animais' => 'nullable|boolean',
            'qtd_animais' => 'nullable|integer|min:0',
            // Abrigos
            'qtd_abrigos_provisorios' => 'nullable|integer|min:0',
            'abrigos_tipos' => 'nullable|array',
            'abrigos_tipos.*' => 'nullable|exists:tipo_abrigo_desmontado,id',
            // Fiscalizacao
            'conducao_forcas_seguranca' => 'nullable|in:0,1',
            'conducao_forcas_observacao' => 'nullable|string',
            'apreensao_fiscal' => 'nullable|boolean',
            'auto_fiscalizacao_aplicado' => 'nullable|in:0,1',
            'auto_fiscalizacao_numero' => 'nullable|string|max:100',
            // Lavratura e protocolo
            'houve_lavratura' => 'nullable|boolean',
            'tipo_protocolo' => 'nullable|in:chuva,frio,normal',
            // Data prevista zeladoria
            'data_prevista_zeladoria' => 'nullable|date',
            'periodo_zeladoria' => 'nullable|in:manha,tarde',
            // Encaminhamentos
            'e1_id' => 'nullable|exists:encaminhamentos,id',
            'e2_id' => 'nullable|exists:encaminhamentos,id',
            'e3_id' => 'nullable|exists:encaminhamentos,id',
            'e4_id' => 'nullable|exists:encaminhamentos,id',
            'e5_id' => 'nullable|exists:encaminhamentos,id',
            'e6_id' => 'nullable|exists:encaminhamentos,id',
            // Participantes da equipe
            'participantes' => 'nullable|array',
            'participantes.*' => 'exists:users,id',
            // Moradores
            'moradores_presentes' => 'nullable|array',
            'moradores_presentes.*' => 'exists:moradores,id',
            'novos_moradores' => 'nullable|array',
            'novos_moradores.*.nome_social' => 'required|string|max:255',
            'novos_moradores.*.apelido' => 'nullable|string|max:255',
            'novos_moradores.*.genero' => 'nullable|string|max:100',
            'novos_moradores.*.documento' => 'nullable|string|max:50',
            'novos_moradores.*.contato' => 'nullable|string|max:50',
            'novos_moradores.*.observacoes' => 'nullable|string',
        ];
    }
}
