<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePontoRequest;
use App\Models\Ponto;
use App\Services\EnderecoService;
use App\Services\PontoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PontoController extends Controller
{
    public function __construct(private PontoService $pontoService) {}

    public function index(Request $request): View
    {
        $request->validate([
            'logradouro' => 'nullable|string|max:200',
            'numero' => 'nullable|string|max:20',
            'bairro' => 'nullable|string|max:200',
            'regional' => 'nullable|string|max:100',
            'resultado' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== 'info_precaria' && ! \DB::table('resultados_acoes')->where('id', $value)->exists()) {
                    $fail('Resultado inválido.');
                }
            }],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = $request->only(['logradouro', 'numero', 'bairro', 'regional', 'resultado']);
        $perPage = min((int) $request->input('per_page', 5), 100);

        $pontos = $this->pontoService->listarPontosComVistorias($filters, $perPage);

        return view('pontos.index', array_merge(
            ['pontos' => $pontos],
            $this->pontoService->getFilterData()
        ));
    }

    public function show(int $id): View
    {
        $ponto = $this->pontoService->buscarPontoPorId($id);

        if (! $ponto) {
            abort(404, 'Ponto não encontrado');
        }

        $vistorias = $this->pontoService->buscarVistoriasDoPonto($id);

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
}
