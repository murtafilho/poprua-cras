<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePontoRequest;
use App\Models\Ponto;
use App\Services\EnderecoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PontoController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'logradouro' => 'nullable|string|max:200',
            'numero' => 'nullable|string|max:20',
            'bairro' => 'nullable|string|max:200',
            'regional' => 'nullable|string|max:100',
            'resultado' => 'nullable|integer|exists:resultados_acoes,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DB::table('pontos as p')
            ->leftJoin('endereco_atualizados as ea', 'ea.id', '=', 'p.endereco_atualizado_id')
            ->leftJoin(DB::raw('(SELECT ponto_id, MAX(id) as ultima_vistoria_id, COUNT(*) as total_vistorias FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) as uv'), 'uv.ponto_id', '=', 'p.id')
            ->leftJoin('vistorias as v', 'v.id', '=', 'uv.ultima_vistoria_id')
            ->leftJoin('resultados_acoes as ra', 'ra.id', '=', 'v.resultado_acao_id')
            ->select([
                'p.id',
                DB::raw('COALESCE(ea."NUMERO_IMOVEL", p.numero) as numero'),
                'p.complemento',
                'p.lat',
                'p.lng',
                DB::raw('ea."NOME_LOGRADOURO" as logradouro'),
                DB::raw('ea."NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('ea."NOME_REGIONAL" as regional'),
                DB::raw('ea."SIGLA_TIPO_LOGRADOURO" as tipo'),
                'v.resultado_acao_id',
                'ra.resultado as resultado_acao',
                DB::raw('COALESCE(uv.total_vistorias, 0) as total_vistorias'),
                DB::raw(Ponto::COMPLEXIDADE_SQL.' as complexidade'),
                'v.quantidade_pessoas',
            ])
            ->whereNotNull('p.lat')
            ->whereNotNull('p.lng')
            ->whereNotNull('p.endereco_atualizado_id');

        $filters = $request->only(['logradouro', 'numero', 'bairro', 'regional', 'resultado']);

        if (! empty($filters['logradouro'])) {
            $query->where('ea.NOME_LOGRADOURO', 'ilike', '%'.$filters['logradouro'].'%');
        }
        if (! empty($filters['regional'])) {
            $query->where('ea.NOME_REGIONAL', $filters['regional']);
        }
        if (! empty($filters['numero'])) {
            $query->where('ea.NUMERO_IMOVEL', $filters['numero']);
        }
        if (! empty($filters['bairro'])) {
            $query->where('ea.NOME_BAIRRO_POPULAR', 'ilike', '%'.$filters['bairro'].'%');
        }
        if (! empty($filters['resultado'])) {
            $query->where('v.resultado_acao_id', $filters['resultado']);
        }

        $pontos = $query->orderBy('logradouro')
            ->orderByRaw('NULLIF(regexp_replace(numero, \'[^0-9]\', \'\', \'g\'), \'\')::int NULLS LAST')
            ->paginate(min((int) $request->input('per_page', 5), 100));

        return view('pontos.index', array_merge(
            ['pontos' => $pontos],
            $this->getFilterData()
        ));
    }

    public function show(int $id): View
    {
        // Buscar dados do ponto
        $ponto = DB::table('pontos as p')
            ->leftJoin('endereco_atualizados as ea', 'ea.id', '=', 'p.endereco_atualizado_id')
            ->leftJoin(DB::raw('(SELECT ponto_id, MAX(id) as ultima_vistoria_id, COUNT(*) as total_vistorias FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) as uv'), 'uv.ponto_id', '=', 'p.id')
            ->leftJoin('vistorias as v', 'v.id', '=', 'uv.ultima_vistoria_id')
            ->leftJoin('resultados_acoes as ra', 'ra.id', '=', 'v.resultado_acao_id')
            ->select([
                'p.id',
                DB::raw('COALESCE(ea."NUMERO_IMOVEL", p.numero) as numero'),
                'p.complemento',
                'p.lat',
                'p.lng',
                DB::raw('ea."NOME_LOGRADOURO" as logradouro'),
                DB::raw('ea."NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('ea."NOME_REGIONAL" as regional'),
                DB::raw('ea."SIGLA_TIPO_LOGRADOURO" as tipo'),
                'v.resultado_acao_id',
                'ra.resultado as resultado_acao',
                DB::raw('COALESCE(uv.total_vistorias, 0) as total_vistorias'),
            ])
            ->where('p.id', $id)
            ->first();

        if (! $ponto) {
            abort(404, 'Ponto não encontrado');
        }

        // Buscar vistorias do ponto ordenadas por data decrescente
        $vistorias = DB::table('vistorias as v')
            ->leftJoin('tipo_abordagem as ta', 'ta.id', '=', 'v.tipo_abordagem_id')
            ->leftJoin('resultados_acoes as ra', 'ra.id', '=', 'v.resultado_acao_id')
            ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->select([
                'v.id',
                'v.data_abordagem',
                'v.quantidade_pessoas',
                'v.qtd_kg',
                'v.observacao',
                'v.nomes_pessoas',
                'ta.tipo as tipo_abordagem',
                'ra.resultado as resultado_acao',
                'u.name as usuario',
            ])
            ->where('v.ponto_id', $id)
            ->whereNull('v.deleted_at')
            ->orderBy('v.data_abordagem', 'desc')
            ->paginate(50);

        return view('pontos.show', [
            'ponto' => $ponto,
            'vistorias' => $vistorias,
        ]);
    }

    public function edit(Ponto $ponto): View
    {
        $ponto->load('enderecoAtualizado');

        return view('pontos.edit', [
            'ponto' => $ponto,
        ]);
    }

    public function update(UpdatePontoRequest $request, Ponto $ponto, EnderecoService $enderecoService): RedirectResponse
    {
        $this->authorize('update', $ponto);

        $data = $request->validated();

        // Se coordenadas mudaram, atualizar geometria e revincular endereço
        $coordenadasMudaram = false;
        if (isset($data['lat']) && isset($data['lng'])) {
            $coordenadasMudaram = (float) $data['lat'] !== (float) $ponto->lat
                || (float) $data['lng'] !== (float) $ponto->lng;
        }

        $ponto->update([
            'numero' => $data['numero'],
            'complemento' => $data['complemento'],
            'observacao' => $data['observacao'] ?? $ponto->observacao,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'endereco_atualizado_id' => $data['endereco_atualizado_id'] ?? $ponto->endereco_atualizado_id,
        ]);

        // Se coordenadas mudaram e não foi selecionado endereço manualmente, revincular
        if ($coordenadasMudaram && ! $request->filled('endereco_atualizado_id') && $ponto->lat && $ponto->lng) {
            $enderecoService->vincularEnderecoAoPonto($ponto->id, (float) $ponto->lat, (float) $ponto->lng);
        }

        return redirect()->route('pontos.show', $ponto->id)
            ->with('success', 'Ponto atualizado com sucesso.');
    }

    private function getFilterData(): array
    {
        return [
            'bairros' => Cache::remember('filtro:bairros', 3600, fn () => DB::table('endereco_atualizados')
                ->select('NOME_BAIRRO_POPULAR as bairro')->distinct()
                ->whereNotNull('NOME_BAIRRO_POPULAR')->orderBy('NOME_BAIRRO_POPULAR')->pluck('bairro')),
            'regionais' => Cache::remember('filtro:regionais', 3600, fn () => DB::table('endereco_atualizados')
                ->select('NOME_REGIONAL as regional')->distinct()
                ->whereNotNull('NOME_REGIONAL')->orderBy('NOME_REGIONAL')->pluck('regional')),
            'resultados' => Cache::remember('filtro:resultados', 3600, fn () => DB::table('resultados_acoes')
                ->orderBy('id')->get()),
        ];
    }
}
