<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

class EnderecoService
{
    /**
     * Raio máximo de busca em metros
     */
    private const RAIO_BUSCA_METROS = 300;

    /**
     * Busca o endereço de porta mais próximo às coordenadas informadas.
     * Usa a tabela endereco_atualizado como fonte primária.
     *
     * @param  float  $lat  Latitude
     * @param  float  $lng  Longitude
     * @return object|null Endereço mais próximo ou null se não encontrado
     */
    public function buscarEnderecoMaisProximo(float $lat, float $lng): ?object
    {
        $expandDeg = self::RAIO_BUSCA_METROS / 111000 * 1.5;

        return DB::table('endereco_atualizados')
            ->select([
                'id',
                DB::raw('"SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('"NOME_LOGRADOURO" as logradouro'),
                DB::raw('"NUMERO_IMOVEL" as numero'),
                DB::raw('"LETRA_IMOVEL" as complemento'),
                DB::raw('"NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('"NOME_REGIONAL" as regional'),
                DB::raw('"CEP" as cep'),
                'lat',
                'lng',
            ])
            ->selectRaw(GeoService::sqlDistanceSelect(), [$lng, $lat])
            ->whereRaw(GeoService::sqlExpandBounds(), [$lng, $lat, $expandDeg])
            ->whereRaw(GeoService::sqlWithinDistance('geom', '<='), [$lng, $lat, self::RAIO_BUSCA_METROS])
            ->orderBy('distancia', 'asc')
            ->first();
    }

    /**
     * Geocodifica um endereço buscando na tabela endereco_atualizado.
     * Primeiro tenta correspondência exata, depois busca número mais próximo.
     *
     * @param  string  $logradouro  Nome do logradouro (pode incluir tipo: "RUA EXEMPLO")
     * @param  string|null  $numero  Número do imóvel
     * @param  string|null  $bairro  Nome do bairro
     * @return object|null Endereço encontrado com lat/lng ou null
     */
    public function geocodificarEndereco(string $logradouro, ?string $numero = null, ?string $bairro = null): ?object
    {
        // Normaliza o logradouro removendo acentos e convertendo para maiúsculas
        $logradouroNormalizado = $this->normalizarTexto($logradouro);

        // Remove o tipo do logradouro se presente (RUA, AV, PCA, etc.)
        $logradouroSemTipo = $this->removerTipoLogradouro($logradouroNormalizado);

        // Converte número para inteiro se possível
        $numeroInt = $numero ? (int) preg_replace('/[^0-9]/', '', $numero) : null;

        // 1. Tenta correspondência exata (logradouro + número + bairro)
        if ($numeroInt && $bairro) {
            $resultado = $this->buscarEnderecoExato($logradouroSemTipo, $numeroInt, $bairro);
            if ($resultado) {
                return $resultado;
            }
        }

        // 2. Tenta correspondência por logradouro + número (sem bairro)
        if ($numeroInt) {
            $resultado = $this->buscarEnderecoExato($logradouroSemTipo, $numeroInt, null);
            if ($resultado) {
                return $resultado;
            }
        }

        // 3. Busca número mais próximo no mesmo logradouro/bairro
        if ($numeroInt && $bairro) {
            $resultado = $this->buscarNumeroMaisProximo($logradouroSemTipo, $numeroInt, $bairro);
            if ($resultado) {
                return $resultado;
            }
        }

        // 4. Busca número mais próximo no mesmo logradouro (qualquer bairro)
        if ($numeroInt) {
            $resultado = $this->buscarNumeroMaisProximo($logradouroSemTipo, $numeroInt, null);
            if ($resultado) {
                return $resultado;
            }
        }

        // 5. Busca qualquer endereço no logradouro/bairro
        if ($bairro) {
            $resultado = $this->buscarPrimeiroEnderecoLogradouro($logradouroSemTipo, $bairro);
            if ($resultado) {
                return $resultado;
            }
        }

        // 6. Busca qualquer endereço no logradouro
        return $this->buscarPrimeiroEnderecoLogradouro($logradouroSemTipo, null);
    }

    /**
     * Normaliza texto removendo acentos e convertendo para maiúsculas.
     */
    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtoupper($texto, 'UTF-8');
        $texto = preg_replace(
            ['/[ÁÀÃÂÄ]/u', '/[ÉÈÊË]/u', '/[ÍÌÎÏ]/u', '/[ÓÒÕÔÖ]/u', '/[ÚÙÛÜ]/u', '/[Ç]/u'],
            ['A', 'E', 'I', 'O', 'U', 'C'],
            $texto
        );

        return trim($texto);
    }

    /**
     * Remove o tipo do logradouro (RUA, AV, PCA, etc.).
     */
    private function removerTipoLogradouro(string $logradouro): string
    {
        $tipos = ['RUA', 'AVENIDA', 'AVE', 'AV', 'PRACA', 'PCA', 'TRAVESSA', 'TV', 'ALAMEDA', 'AL', 'BECO', 'BC', 'LARGO', 'LG', 'RODOVIA', 'ROD'];

        foreach ($tipos as $tipo) {
            if (str_starts_with($logradouro, $tipo.' ')) {
                return trim(substr($logradouro, strlen($tipo)));
            }
        }

        return $logradouro;
    }

    /**
     * Busca endereço com correspondência exata na tabela endereco_atualizado.
     */
    private function buscarEnderecoExato(string $logradouro, int $numero, ?string $bairro): ?object
    {
        $query = DB::table('endereco_atualizados')
            ->select([
                'id',
                DB::raw('"SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('"NOME_LOGRADOURO" as logradouro'),
                DB::raw('"NUMERO_IMOVEL" as numero'),
                DB::raw('"LETRA_IMOVEL" as complemento'),
                DB::raw('"NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('"NOME_REGIONAL" as regional'),
                DB::raw('"CEP" as cep'),
                'lat',
                'lng',
            ])
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('NOME_LOGRADOURO', 'ILIKE', '%'.$logradouro.'%')
            ->where('NUMERO_IMOVEL', (string) $numero);

        if ($bairro) {
            $bairroNormalizado = $this->normalizarTexto($bairro);
            $query->where('NOME_BAIRRO_POPULAR', 'ILIKE', '%'.$bairroNormalizado.'%');
        }

        return $query->first();
    }

    /**
     * Busca o número mais próximo no mesmo logradouro.
     */
    private function buscarNumeroMaisProximo(string $logradouro, int $numero, ?string $bairro): ?object
    {
        $query = DB::table('endereco_atualizados')
            ->select([
                'id',
                DB::raw('"SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('"NOME_LOGRADOURO" as logradouro'),
                DB::raw('"NUMERO_IMOVEL" as numero'),
                DB::raw('"LETRA_IMOVEL" as complemento'),
                DB::raw('"NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('"NOME_REGIONAL" as regional'),
                DB::raw('"CEP" as cep'),
                'lat',
                'lng',
            ])
            ->selectRaw('ABS(CAST("NUMERO_IMOVEL" AS INTEGER) - ?) as diferenca_numero', [$numero])
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereNotNull('NUMERO_IMOVEL')
            ->whereRaw("\"NUMERO_IMOVEL\" ~ '^[0-9]+$'")
            ->where('NOME_LOGRADOURO', 'ILIKE', '%'.$logradouro.'%');

        if ($bairro) {
            $bairroNormalizado = $this->normalizarTexto($bairro);
            $query->where('NOME_BAIRRO_POPULAR', 'ILIKE', '%'.$bairroNormalizado.'%');
        }

        return $query->orderBy('diferenca_numero', 'asc')
            ->first();
    }

    /**
     * Busca o primeiro endereço de um logradouro.
     */
    private function buscarPrimeiroEnderecoLogradouro(string $logradouro, ?string $bairro): ?object
    {
        $query = DB::table('endereco_atualizados')
            ->select([
                'id',
                DB::raw('"SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('"NOME_LOGRADOURO" as logradouro'),
                DB::raw('"NUMERO_IMOVEL" as numero'),
                DB::raw('"LETRA_IMOVEL" as complemento'),
                DB::raw('"NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('"NOME_REGIONAL" as regional'),
                DB::raw('"CEP" as cep'),
                'lat',
                'lng',
            ])
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('NOME_LOGRADOURO', 'ILIKE', '%'.$logradouro.'%');

        if ($bairro) {
            $bairroNormalizado = $this->normalizarTexto($bairro);
            $query->where('NOME_BAIRRO_POPULAR', 'ILIKE', '%'.$bairroNormalizado.'%');
        }

        return $query->orderByRaw('NULLIF(regexp_replace("NUMERO_IMOVEL", \'[^0-9]\', \'\', \'g\'), \'\')::int NULLS LAST')
            ->first();
    }

    /**
     * Processa um ponto recém-criado, vinculando ao endereço mais próximo.
     *
     * @param  int  $pontoId  ID do ponto
     * @param  float  $lat  Latitude
     * @param  float  $lng  Longitude
     * @param  string|null  $complementoUsuario  Complemento informado pelo usuário
     * @return bool True se encontrou e vinculou endereço, false caso contrário
     */
    public function vincularEnderecoAoPonto(int $pontoId, float $lat, float $lng, ?string $complementoUsuario = null): bool
    {
        $endereco = $this->buscarEnderecoMaisProximo($lat, $lng);

        if (! $endereco) {
            return false;
        }

        // Se usuário não informou complemento, gera referência automática
        $complemento = $complementoUsuario;
        if (empty($complemento) && isset($endereco->distancia)) {
            $complemento = sprintf(
                '%dm de %s %s, %s',
                round($endereco->distancia),
                $endereco->tipo,
                $endereco->logradouro,
                intval($endereco->numero)
            );
        }

        DB::table('pontos')
            ->where('id', $pontoId)
            ->update([
                'endereco_atualizado_id' => $endereco->id,
                'complemento' => $complemento ?: null,
                'updated_at' => now(),
            ]);

        return true;
    }
}
