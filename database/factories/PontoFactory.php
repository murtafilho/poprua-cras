<?php

namespace Database\Factories;

use App\Models\EnderecoAtualizado;
use App\Models\Ponto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Ponto>
 */
class PontoFactory extends Factory
{
    protected $model = Ponto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Coordenadas aproximadas de Belo Horizonte
        $lat = fake()->latitude(-20.05, -19.75);
        $lng = fake()->longitude(-44.10, -43.85);

        return [
            'numero' => (string) fake()->numberBetween(1, 5000),
            'complemento' => fake()->optional(0.5)->sentence(3),
            'lat' => $lat,
            'lng' => $lng,
            'endereco_atualizado_id' => null,
            'caracteristica_abrigo_id' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Ponto $ponto) {
            if ($ponto->lat !== null && $ponto->lng !== null) {
                DB::statement(
                    'UPDATE pontos SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$ponto->lng, $ponto->lat, $ponto->id]
                );
            }
        });
    }

    public function comEndereco(): static
    {
        return $this->state(fn (array $attributes) => [
            'endereco_atualizado_id' => EnderecoAtualizado::factory(),
        ]);
    }

    public function semCoordenadas(): static
    {
        return $this->state(fn (array $attributes) => [
            'lat' => null,
            'lng' => null,
        ]);
    }

    public function georreferenciado(): static
    {
        return $this->state(fn (array $attributes) => [
            'lat' => fake()->latitude(-20.05, -19.75),
            'lng' => fake()->longitude(-44.10, -43.85),
        ]);
    }

    public function naoGeorreferenciado(): static
    {
        return $this->state(fn (array $attributes) => [
            'lat' => null,
            'lng' => null,
        ]);
    }
}
