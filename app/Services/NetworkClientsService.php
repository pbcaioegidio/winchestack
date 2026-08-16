<?php

namespace App\Services;

use App\Models\DeviceSetting;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Lê do roteador OpenWrt (via SSH) quem está na rede local e quem está ativo.
 *
 * Fontes no roteador:
 *  - /tmp/dhcp.leases     → hostname por MAC/IP
 *  - ip neigh             → estado/IP (REACHABLE = online; STALE = ocioso)
 *  - iwinfo assoclist     → Wi-Fi: sinal (dBm) e banda (2.4/5GHz) por interface
 *  - brctl showstp/showmacs → porta da bridge por MAC (cabo lanX x Wi-Fi phyX)
 *  - nlbw (nlbwmon)       → tráfego rx/tx por MAC, se instalado
 */
class NetworkClientsService
{
    public function clients(): array
    {
        $cfg = config('winchestack.openwrt');

        $remote = implode('; ', [
            'cat /tmp/dhcp.leases 2>/dev/null',
            'echo ___NEIGH___',
            'ip neigh show 2>/dev/null',
            'echo ___WIFI___',
            'for d in $(iwinfo 2>/dev/null | grep ESSID | cut -d" " -f1); do f=$(iwinfo "$d" info 2>/dev/null | grep -oE "[0-9.]+ GHz" | head -1); echo "IFACE $d $f"; iwinfo "$d" assoclist 2>/dev/null; done',
            'echo ___PORTS___',
            'brctl showstp br-lan 2>/dev/null | grep -E "\\([0-9]+\\)"',
            'echo ___FDB___',
            'brctl showmacs br-lan 2>/dev/null',
            'echo ___NLBW___',
            'nlbw -c csv -g mac 2>/dev/null',
        ]);

        $args = ['ssh', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=5', '-o', 'StrictHostKeyChecking=accept-new'];
        if (! empty($cfg['key'])) {
            $args[] = '-i';
            $args[] = $cfg['key'];
        }
        $args[] = '-p';
        $args[] = (string) ($cfg['port'] ?? 22);
        $args[] = ($cfg['user'] ?? 'root').'@'.($cfg['host'] ?? '192.168.1.1');
        $args[] = $remote;

        try {
            $result = Process::timeout(12)->run($args);
        } catch (\Throwable $e) {
            return ['available' => false, 'reason' => 'ssh_error', 'clients' => [], 'count' => 0, 'online' => 0];
        }

        if (! $result->successful()) {
            return ['available' => false, 'reason' => 'ssh_failed', 'clients' => [], 'count' => 0, 'online' => 0];
        }

        return $this->parse($result->output());
    }

    private function parse(string $out): array
    {
        [$leasesRaw, $rest] = array_pad(explode('___NEIGH___', $out, 2), 2, '');
        [$neighRaw, $rest2] = array_pad(explode('___WIFI___', $rest, 2), 2, '');
        [$wifiRaw, $rest3] = array_pad(explode('___PORTS___', $rest2, 2), 2, '');
        [$portsRaw, $rest4] = array_pad(explode('___FDB___', $rest3, 2), 2, '');
        [$fdbRaw, $nlbwRaw] = array_pad(explode('___NLBW___', $rest4, 2), 2, '');

        $labels = config('winchestack.network.labels', []);
        $oui = config('winchestack.network.oui', []);
        $overrides = DeviceSetting::query()->get()->keyBy('mac');

        // MAC => nome do dispositivo (hostname do lease, '*' vira null)
        $leases = [];
        foreach (preg_split('/\r?\n/', trim($leasesRaw)) as $line) {
            $p = preg_split('/\s+/', trim($line));
            if (count($p) < 4) {
                continue;
            }
            $mac = strtolower($p[1]);
            $leases[$mac] = [
                'ip' => $p[2],
                'hostname' => ($p[3] !== '*' && $p[3] !== '') ? $p[3] : null,
            ];
        }

        // MAC => { ips: [todos os IPs vistos], state: melhor estado }.
        // Um mesmo MAC costuma ter linha IPv4 E IPv6; guardamos todas para
        // depois preferir o IPv4 (some o IPv6 "fantasma" na tela).
        $neigh = [];
        $statePriority = ['REACHABLE' => 5, 'PERMANENT' => 5, 'DELAY' => 4, 'PROBE' => 4, 'STALE' => 2, 'NOARP' => 1, 'FAILED' => 0];
        foreach (preg_split('/\r?\n/', trim($neighRaw)) as $line) {
            if (! preg_match('/^(\S+)\s+dev\s+\S+\s+lladdr\s+([0-9a-f:]{17}).*?(REACHABLE|STALE|DELAY|PROBE|PERMANENT|NOARP|FAILED)/i', $line, $m)) {
                continue;
            }
            $mac = strtolower($m[2]);
            $state = strtoupper($m[3]);
            if (! isset($neigh[$mac])) {
                $neigh[$mac] = ['ips' => [], 'state' => $state];
            }
            $neigh[$mac]['ips'][] = $m[1];
            if (($statePriority[$state] ?? 0) > ($statePriority[$neigh[$mac]['state']] ?? 0)) {
                $neigh[$mac]['state'] = $state;
            }
        }

        // Wi-Fi: assoclist por interface. Cada bloco começa com "IFACE <if> <freq> GHz"
        // e depois lista os MACs associados + sinal. Daí tiramos banda (2.4/5GHz) e dBm.
        $wifi = [];        // mac => ['signal' => int, 'band' => '2.4'|'5'|null]
        $ifaceBand = [];   // interface Wi-Fi => banda (para classificar via bridge também)
        $curIface = null;
        $curBand = null;
        foreach (preg_split('/\r?\n/', trim($wifiRaw)) as $line) {
            if (preg_match('/^IFACE\s+(\S+)\s*([0-9.]+)?/', trim($line), $m)) {
                $curIface = $m[1];
                $freq = $m[2] ?? '';
                $curBand = str_starts_with($freq, '5') ? '5' : (str_starts_with($freq, '2') ? '2.4' : null);
                if ($curBand !== null) {
                    $ifaceBand[$curIface] = $curBand;
                }

                continue;
            }
            if (preg_match('/^([0-9A-F:]{17})\s+(-?\d+)\s*dBm/i', trim($line), $m)) {
                $wifi[strtolower($m[1])] = ['signal' => (int) $m[2], 'band' => $curBand];
            }
        }

        // Portas da bridge: "nome (numero)" — ex.: "lan1 (1)", "phy2-ap0 (5)".
        $portName = [];
        foreach (preg_split('/\r?\n/', trim($portsRaw)) as $line) {
            if (preg_match('/(\S+)\s+\((\d+)\)/', trim($line), $m)) {
                $portName[(int) $m[2]] = $m[1];
            }
        }

        // FDB da bridge (brctl showmacs): "porta  mac  is_local?  ageing". Só os
        // não-locais (aparelhos reais). Diz em qual PORTA física cada MAC está →
        // permite saber se é cabo (lanX) ou Wi-Fi (phyX) de verdade.
        $fdb = [];
        foreach (preg_split('/\r?\n/', trim($fdbRaw)) as $line) {
            $cols = preg_split('/\s+/', trim($line));
            if (count($cols) < 3 || ! preg_match('/^\d+$/', $cols[0]) || ! preg_match('/^[0-9a-f:]{17}$/i', $cols[1])) {
                continue;
            }
            if (strtolower($cols[2]) === 'yes') {
                continue; // MAC local (do próprio roteador)
            }
            $fdb[strtolower($cols[1])] = (int) $cols[0];
        }

        // Tráfego por MAC (nlbwmon, se instalado): rx/tx em bytes. TSV com
        // cabeçalho "mac","conns","rx_bytes","rx_pkts","tx_bytes","tx_pkts".
        $traffic = [];
        foreach (preg_split('/\r?\n/', trim($nlbwRaw)) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '"mac"')) {
                continue;
            }
            $cols = array_map(fn ($c) => trim($c, '"'), explode("\t", $line));
            if (count($cols) < 5) {
                continue;
            }
            $traffic[strtolower($cols[0])] = ['rx' => (int) $cols[2], 'tx' => (int) $cols[4]];
        }
        $trafficAvailable = trim($nlbwRaw) !== '';

        // Só dispositivos PRESENTES: vistos pelo ARP (neigh), associados ao
        // Wi-Fi, ou ativos na bridge (fdb). Leases antigos/expirados que não
        // estão em nenhum deles são descartados — some a sujeira offline.
        $macs = array_values(array_unique(array_merge(array_keys($neigh), array_keys($wifi), array_keys($fdb))));
        $clients = [];
        foreach ($macs as $mac) {
            $state = $neigh[$mac]['state'] ?? null;
            $onWifi = isset($wifi[$mac]);

            // Porta na bridge (se houver): lanX = cabo, phyX = Wi-Fi.
            $ifname = isset($fdb[$mac]) ? ($portName[$fdb[$mac]] ?? null) : null;
            $onWired = $ifname !== null && ! isset($ifaceBand[$ifname]);

            // Online = conectado AGORA de verdade: associado ao Wi-Fi do roteador
            // (assoclist, tempo real) ou numa porta de cabo (lanX). Só "alcançável"
            // pelo ARP sem isso = visto há pouco (ocioso) — cobre quem acabou de
            // sair ou está atrás de repetidor, e NÃO conta como online.
            $online = $onWifi || $onWired;
            $idle = ! $online && $state !== null && $state !== 'FAILED';

            // Conexão + banda (assoclist tem o sinal; cabo vem da porta da bridge).
            $connection = null;
            $band = null;
            $signal = null;
            if ($onWifi) {
                $connection = 'wifi';
                $band = $wifi[$mac]['band'];
                $signal = $wifi[$mac]['signal'];
            } elseif ($onWired) {
                $connection = 'cabo';
            }

            $hostname = $leases[$mac]['hostname'] ?? null;
            $ip = $this->preferIpv4($leases[$mac]['ip'] ?? null, $neigh[$mac]['ips'] ?? []);

            // Identidade: ajuste do painel (banco) > apelido do config > fabricante (OUI) > hostname.
            $label = $labels[$mac] ?? null;
            $ouiInfo = $oui[substr($mac, 0, 8)] ?? null;
            $ov = $overrides[$mac] ?? null;

            $vendor = $ouiInfo['vendor'] ?? null;
            if (($vendor === null || $vendor === '') && $this->isRandomMac($mac)) {
                $vendor = 'MAC privado';
            }

            $kind = $ov?->kind ?? $label['kind'] ?? $ouiInfo['kind'] ?? $this->guessKind($hostname);
            if ($kind === 'desconhecido' && $this->isRandomMac($mac)) {
                $kind = 'celular'; // MAC aleatório em rede doméstica ~ celular/laptop
            }

            $name = $ov?->name ?? $label['name'] ?? $hostname;
            $blocked = (bool) ($ov?->blocked ?? false);
            // "novo" = não identificado e ainda não mexido por você (sem ajuste no banco).
            $novo = $ov === null && in_array($kind, ['desconhecido', 'dispositivo'], true);

            $clients[] = [
                'mac' => $mac,
                'ip' => $ip,
                'hostname' => $hostname,
                'name' => $name,
                'vendor' => $vendor,
                'kind' => $kind,
                'online' => $online,
                'idle' => $idle,
                'connection' => $connection,
                'band' => $band,
                'signal' => $signal,
                'rx' => $traffic[$mac]['rx'] ?? null,
                'tx' => $traffic[$mac]['tx'] ?? null,
                'blocked' => $blocked,
                'novo' => $novo,
            ];
        }

        // Ordena: online primeiro, depois identificados antes dos anônimos, depois A-Z
        usort($clients, function ($a, $b) {
            if ($a['online'] !== $b['online']) {
                return $b['online'] <=> $a['online'];
            }
            $an = ($a['name'] ?? $a['vendor']) ? 0 : 1;
            $bn = ($b['name'] ?? $b['vendor']) ? 0 : 1;
            if ($an !== $bn) {
                return $an <=> $bn;
            }
            return strcmp((string) ($a['name'] ?? $a['vendor'] ?? $a['ip']), (string) ($b['name'] ?? $b['vendor'] ?? $b['ip']));
        });

        return [
            'available' => true,
            'count' => count($clients),
            'online' => count(array_filter($clients, fn ($c) => $c['online'])),
            'traffic_available' => $trafficAvailable,
            'clients' => $clients,
        ];
    }

    /**
     * Escolhe o melhor IP para exibir: só IPv4 real (lease ou ARP), ignorando
     * link-local (169.254). Sem IPv4 retorna null (a tela mostra "—") — evita
     * mostrar IPv6 que o usuário não reconhece como "o IP do aparelho".
     */
    private function preferIpv4(?string $leaseIp, array $neighIps): ?string
    {
        $isV4 = fn ($ip) => $ip !== null && str_contains($ip, '.') && ! str_starts_with($ip, '169.254.');
        if ($isV4($leaseIp)) {
            return $leaseIp;
        }
        foreach ($neighIps as $ip) {
            if ($isV4($ip)) {
                return $ip;
            }
        }
        return null;
    }

    /**
     * MAC aleatório/privado (bit "locally administered" ligado no 1º octeto).
     * Celulares modernos usam isso por privacidade — não dá pra identificar o
     * fabricante, então marcamos como "MAC privado".
     */
    private function isRandomMac(string $mac): bool
    {
        $first = hexdec(substr($mac, 0, 2));

        return ($first & 0x02) === 0x02;
    }

    /**
     * Chuta o tipo do dispositivo pelo hostname (heurística leve).
     */
    private function guessKind(?string $hostname): string
    {
        if (! $hostname) {
            return 'desconhecido';
        }
        $h = Str::lower($hostname);
        if (Str::contains($h, ['cam', 'camera', 'ipc', 'nvr', 'onvif'])) {
            return 'camera';
        }
        if (Str::contains($h, ['redmi', 'poco', 'xiaomi', 'iphone', 'galaxy', 'moto', 'motorola', 'samsung', 'oneplus', 'pixel', 'phone', 'sm-'])) {
            return 'celular';
        }
        if (Str::contains($h, ['bl-ti', 'desktop', 'notebook', 'note', 'pc', 'macbook', 'laptop', 'win'])) {
            return 'computador';
        }
        if (Str::contains($h, ['tv', 'roku', 'firestick', 'chromecast', 'box'])) {
            return 'tv';
        }
        return 'dispositivo';
    }
}
