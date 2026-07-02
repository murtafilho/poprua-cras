<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StackProjecaoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_stack_projecao_requires_authentication(): void
    {
        $this->get(route('stack-projecao.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_stack_projecao_document(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('stack-projecao.index'))
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8');
    }
}
