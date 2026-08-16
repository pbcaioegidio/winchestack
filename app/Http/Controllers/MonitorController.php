<?php

namespace App\Http\Controllers;

use App\Services\CameraViewersService;
use App\Services\NetworkClientsService;
use Illuminate\Http\JsonResponse;

class MonitorController extends Controller
{
    /**
     * Quem está assistindo as câmeras agora (via relay do watchtower).
     */
    public function viewers(CameraViewersService $service): JsonResponse
    {
        return response()->json($service->viewers());
    }

    /**
     * Quem está na rede local / online (via roteador OpenWrt).
     */
    public function network(NetworkClientsService $service): JsonResponse
    {
        return response()->json($service->clients());
    }
}
