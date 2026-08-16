<?php

namespace App\Http\Controllers;

use App\Models\DeviceSetting;
use App\Services\RouterControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ações por dispositivo da rede, feitas pelo painel (autenticado):
 *  - update: apelido + tipo (salvos no banco, prevalecem sobre config/hostname)
 *  - block / unblock: corta/libera a internet do aparelho no roteador
 */
class DeviceController extends Controller
{
    private const KINDS = [
        'camera', 'celular', 'computador', 'servidor', 'tv', 'tvbox',
        'solar', 'smarthome', 'printer', 'router', 'dispositivo', 'desconhecido',
    ];

    public function update(Request $request, string $mac): JsonResponse
    {
        $mac = $this->mac($mac);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'kind' => ['nullable', 'string', Rule::in(self::KINDS)],
        ]);

        $setting = DeviceSetting::firstOrNew(['mac' => $mac]);
        $setting->name = ($data['name'] ?? null) ?: null;
        $setting->kind = $data['kind'] ?? null;
        $setting->save();

        return response()->json(['ok' => true]);
    }

    public function block(string $mac, RouterControlService $router): JsonResponse
    {
        $mac = $this->mac($mac);
        $ok = $router->block($mac);

        if ($ok) {
            DeviceSetting::firstOrNew(['mac' => $mac])->fill(['blocked' => true])->save();
        }

        return response()->json(['ok' => $ok], $ok ? 200 : 502);
    }

    public function unblock(string $mac, RouterControlService $router): JsonResponse
    {
        $mac = $this->mac($mac);
        $ok = $router->unblock($mac);

        if ($ok) {
            DeviceSetting::firstOrNew(['mac' => $mac])->fill(['blocked' => false])->save();
        }

        return response()->json(['ok' => $ok], $ok ? 200 : 502);
    }

    /** Valida o MAC do parâmetro de rota; 404 se o formato for inválido. */
    private function mac(string $mac): string
    {
        $mac = strtolower($mac);
        abort_unless(preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac), 404);

        return $mac;
    }
}
