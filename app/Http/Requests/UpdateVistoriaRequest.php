<?php

namespace App\Http\Requests;

use App\Models\Parametro;
use App\Models\TipoAbordagem;
use App\Models\User;
use App\Services\ParametroService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateVistoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->permiteAgendamentoZeladoria()) {
            $this->merge([
                'data_prevista_zeladoria' => null,
                'periodo_zeladoria' => null,
            ]);
        }
    }

    private function permiteAgendamentoZeladoria(): bool
    {
        if ($this->boolean('houve_comunicado')) {
            return true;
        }

        $tipoId = $this->input('tipo_abordagem_id');
        if (! $tipoId) {
            return false;
        }

        $tipo = TipoAbordagem::query()->find($tipoId);

        return $tipo?->isComunicacaoZeladoria() ?? false;
    }

    /**
     * Validacoes after() compostas — espelha StoreVistoriaRequest:
     *  1. Participantes — todos tem que ter a permission 'participar de equipes vistoria'.
     *  2. Comunicado obrigatorio — se parametro `exigir_comunicado` ligado,
     *     nao agendar zeladoria sem houve_comunicado=Sim na mesma vistoria.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->validateParticipantes($v);
            $this->validateComunicadoObrigatorio($v);
        });
    }

    private function validateParticipantes(Validator $v): void
    {
        /** @var array<int, mixed> $participantes */
        $participantes = $this->input('participantes', []);
        $ids = collect($participantes)->filter()->unique()->values()->all();
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
    }

    private function validateComunicadoObrigatorio(Validator $v): void
    {
        if (! Parametro::get('exigir_comunicado', false)) {
            return;
        }

        $agendouZeladoria = ! empty($this->input('data_prevista_zeladoria'));
        $temComunicado = $this->boolean('houve_comunicado');

        if ($agendouZeladoria && ! $temComunicado) {
            $v->errors()->add(
                'houve_comunicado',
                'Comunicado prévio é obrigatório para agendar zeladoria (parâmetro exigir_comunicado está ligado).'
            );
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $fotoRegras = app(ParametroService::class)->regrasValidacaoFoto();

        return [
            'ponto_id' => 'nullable|exists:pontos,id',
            'data_abordagem' => 'required|date_format:Y-m-d\TH:i',
            'tipo_abordagem_id' => 'required|exists:tipo_abordagem,id',
            'quantidade_pessoas' => 'nullable|integer|min:0',
            'nomes_pessoas' => 'nullable|string',
            'resultado_acao_id' => 'required|exists:resultados_acoes,id',
            'tipo_abrigo_desmontado_id' => 'nullable|exists:tipo_abrigo_desmontado,id',
            'qtd_kg' => 'nullable|integer|min:0',
            'observacao' => 'nullable|string',
            'fotos.*' => $fotoRegras,
            'legendas_fotos' => 'nullable|array',
            'legendas_fotos.*' => 'nullable|string|max:500',
            'publicas_fotos' => 'nullable|array',
            'publicas_fotos.*' => 'nullable|in:0,1',
            'remover_fotos' => 'nullable|array',
            'remover_fotos.*' => 'integer',
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
            // Lavacao e comunicado
            'houve_lavacao' => 'nullable|boolean',
            'houve_comunicado' => 'nullable|boolean',
            'data_comunicado' => 'nullable|date',
            // Data prevista zeladoria
            'data_prevista_zeladoria' => 'nullable|date',
            'periodo_zeladoria' => 'nullable|in:manha,tarde',
            // Participantes da equipe
            'participantes' => 'nullable|array',
            'participantes.*' => 'exists:users,id',
            // Encaminhamentos
            'e1_id' => 'nullable|exists:encaminhamentos,id',
            'e2_id' => 'nullable|exists:encaminhamentos,id',
            'e3_id' => 'nullable|exists:encaminhamentos,id',
            'e4_id' => 'nullable|exists:encaminhamentos,id',
            'e5_id' => 'nullable|exists:encaminhamentos,id',
            'e6_id' => 'nullable|exists:encaminhamentos,id',
            // Moradores
            'moradores_presentes' => 'nullable|array',
            'moradores_presentes.*' => 'exists:moradores,id',
            'novos_moradores' => 'nullable|array',
            'novos_moradores.*.nome_social' => 'required_with:novos_moradores|string|max:255',
            'novos_moradores.*.apelido' => 'nullable|string|max:255',
            'novos_moradores.*.genero' => 'nullable|string|max:50',
            'novos_moradores.*.documento' => 'nullable|string|max:50',
            'novos_moradores.*.contato' => 'nullable|string|max:100',
            'novos_moradores.*.observacoes' => 'nullable|string|max:1000',
        ];
    }
}
