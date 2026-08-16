<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajuste manual de um dispositivo da rede (apelido, tipo e bloqueio),
 * definido pelo usuário no painel. Identificado pelo MAC.
 */
class DeviceSetting extends Model
{
    protected $fillable = [
        'mac',
        'name',
        'kind',
        'blocked',
    ];

    protected $casts = [
        'blocked' => 'boolean',
    ];
}
