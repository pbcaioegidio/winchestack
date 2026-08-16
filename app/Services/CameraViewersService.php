<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Lê do relay do Watchtower (stream-relay.mjs) quem está assistindo as câmeras.
 *
 *  - /health  → contagem de viewers por câmera (o gravador conta como 1, então
 *               descontamos 1 para estimar "pessoas").
 *  - /viewers → detalhe por conexão (IP + User-Agent), já sem o gravador.
 *               Agrupamos por IP+navegador para listar cada espectador.
 *
 * O nome do dispositivo (quando está na LAN) é resolvido no front, cruzando o
 * IP do espectador com os dispositivos do roteador (/api/network).
 */
class CameraViewersService
{
    public function viewers(): array
    {
        $cfg = config('winchestack.relay');

        try {
            $health = Http::timeout(5)->get($cfg['health_url']);
            if (! $health->successful()) {
                return $this->unavailable('relay_unreachable');
            }
            $data = $health->json();
        } catch (\Throwable $e) {
            return $this->unavailable('relay_error');
        }

        $names = $this->cameraNames($cfg['cameras_url']);
        $discount = (bool) ($cfg['discount_recorder'] ?? true);

        $cameras = [];
        $maxPeople = 0;
        foreach (($data['cameras'] ?? []) as $id => $info) {
            $v = (int) ($info['viewers'] ?? 0);
            $people = $discount ? max(0, $v - 1) : $v;
            $maxPeople = max($maxPeople, $people);
            $cameras[] = [
                'id' => (int) $id,
                'name' => $names[(string) $id] ?? "Câmera {$id}",
                'viewers' => $v,
                'people' => $people,
                'pushing' => (bool) ($info['pushing'] ?? false),
                'lastDataSeconds' => $info['lastDataSeconds'] ?? null,
            ];
        }
        usort($cameras, fn ($a, $b) => $b['people'] <=> $a['people'] ?: strcmp($a['name'], $b['name']));

        // Detalhe dos espectadores (relay novo). Se o endpoint não existir
        // (relay antigo), seguimos só com as contagens.
        $people = $this->people($cfg['viewers_url'] ?? null, $names);

        return [
            'available' => true,
            'total' => $people !== null ? count($people) : $maxPeople,
            'cameras' => $cameras,
            'people' => $people ?? [],
        ];
    }

    /**
     * Busca e agrupa os espectadores por IP + navegador.
     *
     * @return array<int, array<string, mixed>>|null  null se o endpoint não existir
     */
    private function people(?string $url, array $names): ?array
    {
        if (! $url) {
            return null;
        }

        try {
            $resp = Http::timeout(5)->get($url);
            if (! $resp->successful()) {
                return null;
            }
            $list = $resp->json('viewers') ?? [];
        } catch (\Throwable $e) {
            return null;
        }

        $groups = [];
        foreach ($list as $v) {
            $ip = (string) ($v['ip'] ?? '');
            $ua = (string) ($v['ua'] ?? '');
            if ($ip === '') {
                continue;
            }
            $key = $ip.'|'.$ua;
            if (! isset($groups[$key])) {
                [$browser, $os, $kind] = $this->parseUserAgent($ua);
                $groups[$key] = [
                    'ip' => $ip,
                    'browser' => $browser,
                    'os' => $os,
                    'kind' => $kind,
                    'cams' => [],
                    'since' => $v['since'] ?? null,
                ];
            }
            $cid = (string) ($v['cameraId'] ?? '');
            if ($cid !== '') {
                $groups[$key]['cams'][$cid] = true;
            }
            $since = $v['since'] ?? null;
            if ($since && (! $groups[$key]['since'] || $since < $groups[$key]['since'])) {
                $groups[$key]['since'] = $since;
            }
        }

        $people = [];
        foreach ($groups as $g) {
            $camIds = array_keys($g['cams']);
            $people[] = [
                'ip' => $g['ip'],
                'browser' => $g['browser'],
                'os' => $g['os'],
                'kind' => $g['kind'],
                'cameras' => count($camIds),
                'cameraNames' => array_values(array_map(fn ($id) => $names[$id] ?? "Câmera {$id}", $camIds)),
                'since' => $g['since'],
            ];
        }
        usort($people, fn ($a, $b) => $b['cameras'] <=> $a['cameras']);

        return $people;
    }

    private function cameraNames(string $url): array
    {
        $names = [];
        try {
            $resp = Http::timeout(5)->get($url);
            if ($resp->successful()) {
                foreach ($resp->json() ?? [] as $c) {
                    if (isset($c['id'])) {
                        $names[(string) $c['id']] = $c['name'] ?? null;
                    }
                }
            }
        } catch (\Throwable $e) {
            // segue sem nomes
        }

        return $names;
    }

    /**
     * Extrai navegador, sistema e tipo de dispositivo do User-Agent.
     *
     * @return array{0:string,1:string,2:string} [browser, os, kind]
     */
    private function parseUserAgent(string $ua): array
    {
        $browser = match (true) {
            Str::contains($ua, 'Edg/') => 'Edge',
            Str::contains($ua, ['OPR/', 'Opera']) => 'Opera',
            Str::contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            Str::contains($ua, 'Firefox/') => 'Firefox',
            Str::contains($ua, 'Chrome/') => 'Chrome',
            Str::contains($ua, 'Safari/') => 'Safari',
            default => 'Outro',
        };

        [$os, $kind] = match (true) {
            Str::contains($ua, 'Android') => ['Android', 'celular'],
            Str::contains($ua, 'iPhone') => ['iOS', 'celular'],
            Str::contains($ua, 'iPad') => ['iPadOS', 'tablet'],
            Str::contains($ua, 'CrKey') => ['Chromecast', 'tv'],
            Str::contains($ua, 'Windows') => ['Windows', 'computador'],
            Str::contains($ua, ['Macintosh', 'Mac OS X']) => ['macOS', 'computador'],
            Str::contains($ua, 'Linux') => ['Linux', 'computador'],
            default => ['—', 'dispositivo'],
        };

        // Pista extra de mobile em UAs que mencionam Linux mas são celulares
        if ($kind === 'computador' && Str::contains($ua, 'Mobile')) {
            $kind = 'celular';
        }

        return [$browser, $os, $kind];
    }

    private function unavailable(string $reason): array
    {
        return ['available' => false, 'reason' => $reason, 'total' => 0, 'cameras' => [], 'people' => []];
    }
}
