<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $params = [
            [
                'chave' => 'paginacao_max',
                'valor' => '100',
                'tipo' => 'integer',
                'grupo' => 'listagem',
                'descricao' => 'Limite máximo de itens por página em listagens',
            ],
            [
                'chave' => 'foto_max_tamanho_kb',
                'valor' => '10240',
                'tipo' => 'integer',
                'grupo' => 'limites',
                'descricao' => 'Tamanho máximo de upload de foto (KB)',
            ],
            [
                'chave' => 'complexidade_critico',
                'valor' => '8',
                'tipo' => 'integer',
                'grupo' => 'complexidade',
                'descricao' => 'Threshold de complexidade crítica (badge vermelho)',
            ],
            [
                'chave' => 'complexidade_alto',
                'valor' => '5',
                'tipo' => 'integer',
                'grupo' => 'complexidade',
                'descricao' => 'Threshold de complexidade alta (badge amarelo)',
            ],
            [
                'chave' => 'complexidade_medio',
                'valor' => '3',
                'tipo' => 'integer',
                'grupo' => 'complexidade',
                'descricao' => 'Threshold de complexidade média (badge azul)',
            ],
        ];

        $pesoFatores = config('parametros.peso_fatores', []);
        foreach ($pesoFatores as $fator) {
            $params[] = [
                'chave' => "peso_{$fator}",
                'valor' => '1',
                'tipo' => 'integer',
                'grupo' => 'complexidade',
                'descricao' => "Peso do fator {$fator} no cálculo de complexidade",
            ];
        }

        foreach ($params as $param) {
            if (DB::table('parametros')->where('chave', $param['chave'])->exists()) {
                continue;
            }

            DB::table('parametros')->insert(array_merge($param, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        $chaves = array_merge(
            ['paginacao_max', 'foto_max_tamanho_kb', 'complexidade_critico', 'complexidade_alto', 'complexidade_medio'],
            array_map(fn (string $fator): string => "peso_{$fator}", config('parametros.peso_fatores', []))
        );

        DB::table('parametros')->whereIn('chave', $chaves)->delete();
    }
};
