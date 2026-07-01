{{-- Campos de entrega do comunicado e agendamento de retorno (aba Relatório). --}}
<div class="form-group">
    <label class="form-label">Data de Entrega do Comunicado</label>
    <input type="datetime-local"
           name="data_comunicado"
           value="{{ old('data_comunicado', isset($vistoria) && $vistoria->data_comunicado ? $vistoria->data_comunicado->format('Y-m-d\TH:i') : date('Y-m-d\TH:i')) }}"
           class="form-input">
</div>
<div class="grid grid-cols-2 gap-3">
    <div class="form-group">
        <label class="form-label">Data de Agendamento</label>
        <input type="date"
               name="data_prevista_zeladoria"
               value="{{ old('data_prevista_zeladoria', isset($vistoria) && $vistoria->data_prevista_zeladoria ? $vistoria->data_prevista_zeladoria->format('Y-m-d') : '') }}"
               class="form-input">
    </div>
    <div class="form-group">
        <label class="form-label">Período de Agendamento</label>
        <select name="periodo_zeladoria" class="form-input form-select">
            <option value="">Selecione...</option>
            <option value="manha" {{ old('periodo_zeladoria', optional($vistoria ?? null)->periodo_zeladoria) === 'manha' ? 'selected' : '' }}>Manhã</option>
            <option value="tarde" {{ old('periodo_zeladoria', optional($vistoria ?? null)->periodo_zeladoria) === 'tarde' ? 'selected' : '' }}>Tarde</option>
        </select>
    </div>
</div>
