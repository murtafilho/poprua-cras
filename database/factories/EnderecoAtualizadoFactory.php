<?php

namespace Database\Factories;

use App\Models\EnderecoAtualizado;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<EnderecoAtualizado>
 */
class EnderecoAtualizadoFactory extends Factory
{
    protected $model = EnderecoAtualizado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipos = ['R', 'AV', 'PCA', 'TV', 'AL', 'BC'];
        $regionais = ['BARREIRO', 'CENTRO-SUL', 'LESTE', 'NORDESTE', 'NOROESTE', 'NORTE', 'OESTE', 'PAMPULHA', 'VENDA NOVA'];

        // Coordenadas aproximadas de Belo Horizonte
        $lat = fake()->latitude(-20.05, -19.75);
        $lng = fake()->longitude(-44.10, -43.85);

        return [
            'SIGLA_TIPO_LOGRADOURO' => fake()->randomElement($tipos),
            'NOME_LOGRADOURO' => strtoupper(fake()->streetName()),
            'NUMERO_IMOVEL' => fake()->numberBetween(1, 5000),
            'LETRA_IMOVEL' => fake()->optional(0.1)->randomLetter(),
            'NOME_BAIRRO_OFICIAL' => strtoupper(fake()->citySuffix().' '.fake()->lastName()),
            'NOME_REGIONAL' => fake()->randomElement($regionais),
            'CEP' => fake()->numerify('########'),
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (EnderecoAtualizado $endereco) {
            if ($endereco->lat !== null && $endereco->lng !== null) {
                DB::statement(
                    'UPDATE endereco_atualizados SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$endereco->lng, $endereco->lat, $endereco->id]
                );
            }
        });
    }

    public function semCoordenadas(): static
    {
        return $this->state(fn (array $attributes) => [
            'lat' => null,
            'lng' => null,
        ]);
    }

    public function regional(string $regional): static
    {
        return $this->state(fn (array $attributes) => [
            'NOME_REGIONAL' => $regional,
        ]);
    }
}
