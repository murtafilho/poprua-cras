{{-- Form partial reutilizado por create.blade.php e edit.blade.php --}}
@csrf

<div class="form-group">
    <label class="form-label required" for="nome">Nome</label>
    <input type="text" name="nome" id="nome" required maxlength="255"
           value="{{ old('nome', $membro->nome ?? '') }}"
           class="form-input @error('nome') is-invalid @enderror">
    @error('nome')<small class="form-error">{{ $message }}</small>@enderror
</div>

<div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
    <div class="form-group">
        <label class="form-label" for="matricula">Matricula</label>
        <input type="text" name="matricula" id="matricula" maxlength="30"
               value="{{ old('matricula', $membro->matricula ?? '') }}"
               class="form-input @error('matricula') is-invalid @enderror">
        @error('matricula')<small class="form-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="email">E-mail</label>
        <input type="email" name="email" id="email" maxlength="255"
               value="{{ old('email', $membro->email ?? '') }}"
               class="form-input @error('email') is-invalid @enderror">
        @error('email')<small class="form-error">{{ $message }}</small>@enderror
    </div>
</div>

<div class="form-group">
    <label class="form-label required" for="equipe">Equipe</label>
    <select name="equipe" id="equipe" required class="form-select @error('equipe') is-invalid @enderror">
        @foreach($equipes as $key => $label)
            <option value="{{ $key }}" {{ old('equipe', $membro->equipe ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    @error('equipe')<small class="form-error">{{ $message }}</small>@enderror
</div>

<div class="form-group">
    <label class="checkbox-option" style="display: flex; align-items: center; gap: var(--space-2);">
        <input type="hidden" name="ativo" value="0">
        <input type="checkbox" name="ativo" id="ativo" value="1" class="form-checkbox"
               {{ old('ativo', isset($membro) ? $membro->ativo : true) ? 'checked' : '' }}>
        <span>Ativo (membro disponivel para escala)</span>
    </label>
</div>
