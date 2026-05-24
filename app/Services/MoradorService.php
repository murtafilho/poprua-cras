<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Morador;
use App\Models\MoradorHistorico;
use App\Models\Ponto;
use App\Models\Vistoria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MoradorService
{
    /**
     * Registra entrada de um morador em um ponto
     */
    public function registrarEntrada(
        Morador $morador,
        Ponto $ponto,
        ?Vistoria $vistoria = null,
        ?\DateTimeInterface $dataEntrada = null
    ): MoradorHistorico {
        $dataEntrada = $dataEntrada ?? now();

        return DB::transaction(function () use ($morador, $ponto, $vistoria, $dataEntrada) {
            // Fecha histórico anterior se existir (morador migrou)
            $this->fecharHistoricoAberto($morador, $vistoria, $dataEntrada);

            // Atualiza ponto atual do morador
            $morador->update(['ponto_atual_id' => $ponto->id]);

            // Cria novo registro de histórico
            return MoradorHistorico::create([
                'morador_id' => $morador->id,
                'ponto_id' => $ponto->id,
                'vistoria_entrada_id' => $vistoria?->id,
                'data_entrada' => $dataEntrada,
            ]);
        });
    }

    /**
     * Registra saída de um morador de um ponto
     */
    public function registrarSaida(
        Morador $morador,
        ?Vistoria $vistoria = null,
        ?\DateTimeInterface $dataSaida = null
    ): ?MoradorHistorico {
        $dataSaida = $dataSaida ?? now();

        return DB::transaction(function () use ($morador, $vistoria, $dataSaida) {
            $historico = $this->fecharHistoricoAberto($morador, $vistoria, $dataSaida);

            // Remove ponto atual
            $morador->update(['ponto_atual_id' => null]);

            return $historico;
        });
    }

    /**
     * Transfere morador de um ponto para outro (migração)
     */
    public function transferir(
        Morador $morador,
        Ponto $novoPonto,
        ?Vistoria $vistoriaSaida = null,
        ?Vistoria $vistoriaEntrada = null,
        ?\DateTimeInterface $data = null
    ): MoradorHistorico {
        $data = $data ?? now();

        return DB::transaction(function () use ($morador, $novoPonto, $vistoriaSaida, $vistoriaEntrada, $data) {
            // Fecha histórico do ponto anterior
            $this->fecharHistoricoAberto($morador, $vistoriaSaida, $data);

            // Atualiza ponto atual
            $morador->update(['ponto_atual_id' => $novoPonto->id]);

            // Cria histórico no novo ponto
            return MoradorHistorico::create([
                'morador_id' => $morador->id,
                'ponto_id' => $novoPonto->id,
                'vistoria_entrada_id' => $vistoriaEntrada?->id,
                'data_entrada' => $data,
            ]);
        });
    }

    /**
     * Cria novo morador e registra entrada no ponto
     *
     * @param  array<string, mixed>  $dados
     */
    public function criarComEntrada(
        array $dados,
        Ponto $ponto,
        ?Vistoria $vistoria = null
    ): Morador {
        return DB::transaction(function () use ($dados, $ponto, $vistoria) {
            $morador = Morador::create($dados);
            $this->registrarEntrada($morador, $ponto, $vistoria);

            return $morador->fresh(['pontoAtual']);
        });
    }

    /**
     * Atualiza lista de moradores presentes em uma vistoria
     *
     * @param  array<int>  $moradoresPresentes  IDs dos moradores presentes
     * @param  array<array<string, mixed>>  $novosMoradores  Dados de novos moradores a criar
     */
    public function atualizarPresencaVistoria(
        Vistoria $vistoria,
        array $moradoresPresentes,
        array $novosMoradores = []
    ): void {
        /** @var Ponto $ponto */
        $ponto = $vistoria->ponto;
        /** @var \DateTimeInterface|null $dataVistoria */
        $dataVistoria = $vistoria->data_abordagem;

        DB::transaction(function () use ($vistoria, $ponto, $moradoresPresentes, $novosMoradores, $dataVistoria) {
            // Moradores atuais do ponto
            $moradoresAtuais = $ponto->moradores()->pluck('id')->toArray();

            // Moradores que saíram (estavam no ponto mas não estão presentes)
            $moradoresSaida = array_diff($moradoresAtuais, $moradoresPresentes);
            $moradoresEntrada = array_diff($moradoresPresentes, $moradoresAtuais);

            $allIds = array_merge($moradoresSaida, $moradoresEntrada);
            $moradoresById = $allIds ? Morador::whereIn('id', $allIds)->get()->keyBy('id') : collect();

            foreach ($moradoresSaida as $moradorId) {
                if ($morador = $moradoresById->get($moradorId)) {
                    $this->registrarSaida($morador, $vistoria, $dataVistoria);
                }
            }

            foreach ($moradoresEntrada as $moradorId) {
                if ($morador = $moradoresById->get($moradorId)) {
                    $this->registrarEntrada($morador, $ponto, $vistoria, $dataVistoria);
                }
            }

            // Criar novos moradores
            foreach ($novosMoradores as $dados) {
                $this->criarComEntrada($dados, $ponto, $vistoria);
            }
        });
    }

    /**
     * Busca moradores por nome ou apelido (para detectar migração)
     *
     * @return Collection<int, Morador>
     */
    public function buscarPorNome(string $termo, ?int $excluirPontoId = null): Collection
    {
        $query = Morador::query()
            ->where(function ($q) use ($termo) {
                $q->where('nome_social', 'like', "%{$termo}%")
                    ->orWhere('apelido', 'like', "%{$termo}%")
                    ->orWhere('nome_registro', 'like', "%{$termo}%");
            })
            ->with(['pontoAtual.enderecoAtualizado']);

        if ($excluirPontoId) {
            $query->where(function ($q) use ($excluirPontoId) {
                $q->whereNull('ponto_atual_id')
                    ->orWhere('ponto_atual_id', '!=', $excluirPontoId);
            });
        }

        return $query->limit(20)->get();
    }

    /**
     * Retorna histórico completo de um morador
     *
     * @return Collection<int, MoradorHistorico>
     */
    public function getHistorico(Morador $morador): Collection
    {
        return $morador->historico()
            ->with(['ponto.enderecoAtualizado', 'vistoriaEntrada', 'vistoriaSaida'])
            ->get();
    }

    /**
     * Retorna moradores de um ponto com histórico
     *
     * @return Collection<int, Morador>
     */
    public function getMoradoresDoPonto(Ponto $ponto): Collection
    {
        return $ponto->moradores()
            ->with(['historico' => fn ($q) => $q->where('ponto_id', $ponto->id)])
            ->get();
    }

    /**
     * Fecha o histórico aberto de um morador (se existir)
     */
    private function fecharHistoricoAberto(
        Morador $morador,
        ?Vistoria $vistoria,
        \DateTimeInterface $dataSaida
    ): ?MoradorHistorico {
        $historicoAberto = MoradorHistorico::where('morador_id', $morador->id)
            ->whereNull('data_saida')
            ->first();

        if ($historicoAberto) {
            $historicoAberto->update([
                'vistoria_saida_id' => $vistoria?->id,
                'data_saida' => $dataSaida,
            ]);
        }

        return $historicoAberto;
    }
}
