<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Morador>
 */
class MoradorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $generos = [
            'Homem cisgênero',
            'Mulher cisgênero',
            'Homem trans',
            'Mulher trans',
            'Travesti',
            'Não-binário',
            'Prefiro não informar',
        ];

        return [
            'nome_social' => fake()->firstName(),
            'nome_registro' => fake()->optional(0.5)->name(),
            'apelido' => fake()->optional(0.7)->firstName(),
            'genero' => fake()->optional(0.8)->randomElement($generos),
            'observacoes' => fake()->optional(0.5)->sentence(),
            'documento' => fake()->optional(0.3)->numerify('###.###.###-##'),
            'contato' => fake()->optional(0.3)->phoneNumber(),
        ];
    }
}
