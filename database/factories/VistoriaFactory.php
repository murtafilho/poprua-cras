<?php

namespace Database\Factories;

use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vistoria>
 */
class VistoriaFactory extends Factory
{
    protected $model = Vistoria::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ponto_id' => Ponto::factory(),
            'user_id' => User::factory(),
            'data_abordagem' => fake()->dateTimeBetween('-1 year', 'now'),
            'quantidade_pessoas' => fake()->numberBetween(1, 10),
            'nomes_pessoas' => fake()->optional(0.7)->sentence(3),
            'tipo_abordagem_id' => fake()->numberBetween(1, 3),
            'resultado_acao_id' => fake()->numberBetween(1, 5),
            'tipo_abrigo_desmontado_id' => fake()->optional(0.5)->numberBetween(1, 5),
            'qtd_kg' => fake()->optional(0.3)->numberBetween(0, 500),
            'observacao' => fake()->optional(0.5)->paragraph(),

            // Indicadores de complexidade
            'resistencia' => fake()->boolean(20),
            'num_reduzido' => fake()->boolean(30),
            'casal' => fake()->boolean(25),
            'qtd_casais' => fn (array $attributes) => $attributes['casal'] ? fake()->numberBetween(1, 3) : 0,
            'catador_reciclados' => fake()->boolean(40),
            'fixacao_antiga' => fake()->boolean(35),
            'excesso_objetos' => fake()->boolean(30),
            'trafico_ilicitos' => fake()->boolean(15),
            'crianca_adolescente' => fake()->boolean(10),
            'idosos' => fake()->boolean(25),
            'gestante' => fake()->boolean(5),
            'lgbtqiapn' => fake()->boolean(15),
            'cena_uso_caracterizada' => fake()->boolean(20),
            'deficiente' => fake()->boolean(10),
            'agrupamento_quimico' => fake()->boolean(15),
            'saude_mental' => fake()->boolean(30),
            'animais' => fake()->boolean(35),
            'qtd_animais' => fn (array $attributes) => $attributes['animais'] ? fake()->numberBetween(1, 5) : 0,

            // Abrigos
            'qtd_abrigos_provisorios' => fake()->numberBetween(0, 5),
            'abrigos_tipos' => null,

            // Fiscalização
            'conducao_forcas_seguranca' => fake()->boolean(10),
            'conducao_forcas_observacao' => null,
            'apreensao_fiscal' => fake()->boolean(5),
            'auto_fiscalizacao_aplicado' => fake()->boolean(5),
            'auto_fiscalizacao_numero' => null,
        ];
    }

    public function comComplexidadeAlta(): static
    {
        return $this->state(fn (array $attributes) => [
            'resistencia' => true,
            'num_reduzido' => false,
            'casal' => false,
            'catador_reciclados' => false,
            'fixacao_antiga' => true,
            'excesso_objetos' => true,
            'trafico_ilicitos' => true,
            'crianca_adolescente' => false,
            'idosos' => false,
            'gestante' => false,
            'lgbtqiapn' => false,
            'cena_uso_caracterizada' => false,
            'deficiente' => false,
            'agrupamento_quimico' => false,
            'saude_mental' => true,
            'animais' => false,
        ]);
    }

    public function comComplexidadeBaixa(): static
    {
        return $this->state(fn (array $attributes) => [
            'resistencia' => false,
            'num_reduzido' => false,
            'casal' => false,
            'catador_reciclados' => false,
            'fixacao_antiga' => false,
            'excesso_objetos' => false,
            'trafico_ilicitos' => false,
            'crianca_adolescente' => false,
            'idosos' => false,
            'gestante' => false,
            'lgbtqiapn' => false,
            'cena_uso_caracterizada' => false,
            'deficiente' => false,
            'agrupamento_quimico' => false,
            'saude_mental' => false,
            'animais' => false,
        ]);
    }

    public function finalizada(): static
    {
        return $this->state(fn (array $attributes) => [
            'finalizada' => true,
            'finalizada_em' => now(),
            'finalizada_por' => $attributes['user_id'],
        ]);
    }

    public function cancelada(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancelada' => true,
            'cancelada_em' => now(),
            'cancelada_por' => $attributes['user_id'],
        ]);
    }

    public function recente(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_abordagem' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function antiga(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_abordagem' => fake()->dateTimeBetween('-2 years', '-6 months'),
        ]);
    }
}
