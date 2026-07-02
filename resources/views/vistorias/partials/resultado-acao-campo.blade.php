{{-- Campo principal da aba Relatório — classificação obrigatória da visita. --}}
<div class="card mb-4 resultado-acao-highlight" id="resultado-acao-campo">
    <div class="card-body">
        <div class="resultado-acao-highlight__header">
            <span class="resultado-acao-highlight__icon" aria-hidden="true">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </span>
            <div>
                <label class="form-label required resultado-acao-highlight__title" for="resultado_acao_id">Resultado da Ação</label>
                <p class="resultado-acao-highlight__hint">Classificação principal da visita. Campo obrigatório para finalizar o registro.</p>
            </div>
        </div>
        <div class="form-group resultado-acao-highlight__field">
            <select name="resultado_acao_id"
                    id="resultado_acao_id"
                    required
                    class="form-input form-select resultado-acao-highlight__select">
                <option value="">Selecione o resultado...</option>
                @foreach($resultadosAcao as $resultado)
                    @php
                        $ocultarPersiste = isset($pontoProximo) && ! $pontoProximo && str_contains(strtolower($resultado->resultado), 'persiste');
                    @endphp
                    @unless($ocultarPersiste)
                        <option value="{{ $resultado->id }}" @selected((string) old('resultado_acao_id', $vistoria->resultado_acao_id ?? '') === (string) $resultado->id)>
                            {{ $resultado->resultado }}
                        </option>
                    @endunless
                @endforeach
            </select>
        </div>
    </div>
</div>
