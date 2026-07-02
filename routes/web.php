<?php

use App\Http\Controllers\Admin\InfraestruturaController;
use App\Http\Controllers\Admin\MatrizPermissoesController;
use App\Http\Controllers\Admin\ParametroController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\Sprint11Controller;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MapaController;
use App\Http\Controllers\MinhaEquipeController;
use App\Http\Controllers\MoradorController;
use App\Http\Controllers\PontoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SobreController;
use App\Http\Controllers\StackProjecaoController;
use App\Http\Controllers\VistoriaController;
use Illuminate\Support\Facades\Route;

// Todas as rotas protegidas por autenticação
Route::middleware('auth')->group(function () {
    // Home redireciona para dashboard
    Route::get('/', function () {
        return redirect()->route('mapa.index');
    });

    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/sobre', SobreController::class)->name('sobre.index');
    Route::get('/stack-projecao', StackProjecaoController::class)->name('stack-projecao.index');

    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Mapa e Vistorias
    Route::get('/mapa', [MapaController::class, 'index'])->name('mapa.index');
    Route::get('/pontos', [PontoController::class, 'index'])->name('pontos.index');
    Route::get('/pontos/{id}', [PontoController::class, 'show'])->whereNumber('id')->name('pontos.show');
    Route::get('/pontos/{ponto}/edit', [PontoController::class, 'edit'])->name('pontos.edit');
    Route::put('/pontos/{ponto}', [PontoController::class, 'update'])->name('pontos.update');
    Route::get('/pontos/{ponto}/vistorias/create', [VistoriaController::class, 'createForPonto'])->name('pontos.vistorias.create');
    Route::get('/minhas-vistorias', [VistoriaController::class, 'minhas'])->name('vistorias.minhas');
    Route::get('/minha-equipe', [MinhaEquipeController::class, 'index'])->name('minha-equipe.index');
    Route::put('/minha-equipe', [MinhaEquipeController::class, 'update'])->name('minha-equipe.update');
    Route::get('/vistorias', [VistoriaController::class, 'index'])->name('vistorias.index');
    Route::get('/vistorias/create', [VistoriaController::class, 'create'])->name('vistorias.create');
    Route::get('/vistorias/roteiro', [VistoriaController::class, 'exportarRoteiro'])->name('vistorias.roteiro');
    Route::get('/vistorias/{vistoria}', [VistoriaController::class, 'show'])->name('vistorias.show');
    Route::get('/vistorias/{vistoria}/edit', [VistoriaController::class, 'edit'])->name('vistorias.edit');
    Route::post('/vistorias', [VistoriaController::class, 'store'])->name('vistorias.store');
    Route::put('/vistorias/{vistoria}', [VistoriaController::class, 'update'])->name('vistorias.update');
    Route::post('/vistorias/{vistoria}/finalizar', [VistoriaController::class, 'finalizar'])->name('vistorias.finalizar');
    Route::post('/vistorias/{vistoria}/reativar', [VistoriaController::class, 'reativar'])->name('vistorias.reativar');
    Route::post('/vistorias/{vistoria}/cancelar', [VistoriaController::class, 'cancelar'])->name('vistorias.cancelar');
    Route::post('/vistorias/{vistoria}/complementar', [VistoriaController::class, 'complementar'])->name('vistorias.complementar');
    Route::delete('/vistorias/{vistoria}', [VistoriaController::class, 'destroy'])->name('vistorias.destroy');

    // Moradores
    Route::resource('moradores', MoradorController::class)->parameters(['moradores' => 'morador']);

    // Admin routes (somente usuarios com role admin)
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::resource('roles', RoleController::class)->except(['show']);
        Route::resource('permissions', PermissionController::class)->only(['index', 'create', 'store', 'destroy']);
        Route::get('users', [UserRoleController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserRoleController::class, 'create'])->name('users.create');
        Route::post('users', [UserRoleController::class, 'store'])->name('users.store');
        Route::put('users/{user}/roles', [UserRoleController::class, 'updateRoles'])->name('users.roles.update');
        Route::get('infraestrutura', InfraestruturaController::class)->name('infraestrutura');
        Route::get('sprint-11', Sprint11Controller::class)->name('sprint-11');
        Route::get('matriz-permissoes', MatrizPermissoesController::class)->name('matriz-permissoes');
        Route::get('parametros', [ParametroController::class, 'index'])->name('parametros.index');
        Route::put('parametros', [ParametroController::class, 'update'])->name('parametros.update');
        Route::post('parametros', [ParametroController::class, 'create'])->name('parametros.create');
        Route::delete('parametros/{chave}', [ParametroController::class, 'destroy'])->name('parametros.destroy');
    });
});

// Power BI - rota publica (sem autenticacao)
Route::get('/powerbi', function () {
    return view('powerbi.index');
})->name('powerbi.index');

// Discussao - rota publica (sem autenticacao)
Route::get('/discussao', function () {
    return view('discussao.index');
})->name('discussao.index');

// Fallback: qualquer rota nao encontrada redireciona para login
Route::fallback(function () {
    return redirect()->route('login');
});

require __DIR__.'/auth.php';
