@php
    use App\Enums\TipoEquipe;
    $porEquipe = $usuariosEquipe->groupBy(fn ($u) => TipoEquipe::fromUser($u)->value);
@endphp

<div class="flex flex-col gap-3">
    @foreach (TipoEquipe::ordenados() as $tipo)
        @if ($porEquipe->has($tipo->value))
            <div>
                <span class="info-label" style="display: block; margin-bottom: var(--space-1);">{{ $tipo->label() }}</span>
                <div class="flex flex-col gap-1">
                    @foreach ($porEquipe[$tipo->value] as $u)
                        @php($checked = in_array($u->id, $participantesSelecionados))
                        <label class="checkbox-option" style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--text-sm);">
                            <input type="checkbox" name="participantes[]" value="{{ $u->id }}" class="form-checkbox" {{ $checked ? 'checked' : '' }}>
                            <span>{{ $u->name }}@if($u->email) <span class="text-muted" style="font-size: var(--text-xs);">— {{ $u->email }}</span>@endif</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
