<?php

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\MonitorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Rotas web (Inertia)
|--------------------------------------------------------------------------
|
| As rotas de autenticação (GET/POST /login, /logout, etc.) são registradas
| automaticamente pelo Fortify. A página inicial decide internamente se mostra
| a UI de desktop ou a de mobile.
|
*/

Route::get('/', function () {
    return Inertia::render('Panel');
})->middleware('auth')->name('home');

/*
|--------------------------------------------------------------------------
| API do painel (autenticada por sessão)
|--------------------------------------------------------------------------
|
| Consumida pela SPA (Vue/Inertia) via fetch/axios. Mesma origem, cookie+CSRF.
|
*/
Route::middleware(['auth'])->prefix('api')->group(function () {
    Route::get('viewers', [MonitorController::class, 'viewers'])->name('api.viewers');
    Route::get('network', [MonitorController::class, 'network'])->name('api.network');

    // Ações por dispositivo (apelido/tipo + bloquear/liberar internet no roteador)
    Route::post('devices/{mac}', [DeviceController::class, 'update'])->where('mac', '[0-9a-fA-F:]{17}');
    Route::post('devices/{mac}/block', [DeviceController::class, 'block'])->where('mac', '[0-9a-fA-F:]{17}');
    Route::post('devices/{mac}/unblock', [DeviceController::class, 'unblock'])->where('mac', '[0-9a-fA-F:]{17}');
});
