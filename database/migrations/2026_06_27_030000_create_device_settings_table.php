<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes por dispositivo definidos no painel: apelido, tipo e bloqueio.
 * A chave é o MAC (identidade estável do aparelho). Tem prioridade sobre os
 * apelidos do config e sobre o hostname/fabricante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mac', 17)->unique();
            $table->string('name')->nullable();
            $table->string('kind')->nullable();
            $table->boolean('blocked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_settings');
    }
};
