@php
    use App\Enums\TipoEquipe;
    $porEquipe = $usuarios->groupBy(fn ($u) => TipoEquipe::fromUser($u)->value);
@endphp

@if ($usuarios->isEmpty())
    <p class="text-muted">Nenhum outro usuário cadastrado no sistema.</p>
@else
    <div class="equipe-membros">
        @foreach (TipoEquipe::ordenados() as $tipo)
            @if ($porEquipe->has($tipo->value))
                <p class="info-label" style="margin: var(--space-3) 0 var(--space-1); padding-left: var(--space-1);">{{ $tipo->label() }}</p>
                @foreach ($porEquipe[$tipo->value] as $u)
                    <label class="equipe-membro">
                        <input type="checkbox" name="membros[]" value="{{ $u->id }}"
                               {{ in_array($u->id, $marcados) ? 'checked' : '' }}>
                        <span class="checkbox" aria-hidden="true"></span>
                        <span class="info">
                            <span class="nome">{{ $u->name }}</span>
                            @if ($u->email)
                                <span class="email">{{ $u->email }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            @endif
        @endforeach
    </div>
@endif
