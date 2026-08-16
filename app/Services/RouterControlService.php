<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Controla o roteador OpenWrt: bloqueia/libera a INTERNET de um dispositivo
 * por MAC, com uma regra de firewall (fw4/nftables) persistente.
 *
 * Bloquear = regra lan→wan REJECT pro MAC (o aparelho continua no Wi-Fi/LAN,
 * mas não acessa a internet). Liberar = remove a regra. O MAC é validado de
 * forma estrita antes de qualquer comando (evita injeção).
 */
class RouterControlService
{
    public function block(string $mac): bool
    {
        $mac = $this->normalizeMac($mac);
        if ($mac === null) {
            return false;
        }

        $s = $this->section($mac);
        $cmds = [
            "uci set firewall.{$s}=rule",
            "uci set firewall.{$s}.name='wsk-block-{$mac}'",
            "uci set firewall.{$s}.src='lan'",
            "uci set firewall.{$s}.dest='wan'",
            "uci set firewall.{$s}.src_mac='{$mac}'",
            "uci set firewall.{$s}.target='REJECT'",
        ];

        // Captive portal: além de cortar a WAN, redireciona o HTTP (porta 80) do
        // aparelho pra uma página local de "acesso bloqueado" (uhttpd dedicado no
        // roteador). O HTTPS continua só caindo — não dá pra forjar certificado —
        // mas o teste de conectividade do sistema (HTTP) é desviado e abre a
        // página sozinho. DNAT (prerouting) intercepta a porta 80 antes do
        // forward; o resto (443 etc.) cai no REJECT acima.
        $portal = config('winchestack.openwrt.portal');
        if (! empty($portal['enabled'])) {
            $p = $this->portalSection($mac);
            $ip = $portal['ip'] ?? '192.168.1.1';
            $port = (int) ($portal['port'] ?? 8090);
            $cmds = array_merge($cmds, [
                "uci set firewall.{$p}=redirect",
                "uci set firewall.{$p}.name='wsk-portal-{$mac}'",
                "uci set firewall.{$p}.src='lan'",
                "uci set firewall.{$p}.dest='lan'",
                "uci set firewall.{$p}.proto='tcp'",
                "uci set firewall.{$p}.src_mac='{$mac}'",
                "uci set firewall.{$p}.src_dport='80'",
                "uci set firewall.{$p}.dest_ip='{$ip}'",
                "uci set firewall.{$p}.dest_port='{$port}'",
                "uci set firewall.{$p}.target='DNAT'",
            ]);
        }

        $cmds[] = 'uci commit firewall';

        // Aplica o firewall e, em seguida, DERRUBA as conexões já abertas deste
        // aparelho (conntrack). Sem isso o bloqueio "demora": a regra corta só
        // conexões NOVAS, mas o fw4 aceita as established ANTES da nossa regra,
        // então streaming/download em andamento seguem até expirar. Limpamos o
        // conntrack só dos IPs (v4+v6) DESTE MAC — não toca em outros aparelhos
        // nem nas câmeras. O sucesso é definido pelo reload; a limpeza é
        // best-effort (se o pacote 'conntrack' não estiver instalado, ignora).
        $kill = "ips=\"\$(grep -i '{$mac}' /tmp/dhcp.leases 2>/dev/null | cut -d' ' -f3) \$(ip neigh 2>/dev/null | grep -i '{$mac}' | cut -d' ' -f1)\"; "
            ."for ip in \$ips; do case \"\$ip\" in "
            ."*:*) conntrack -D -f ipv6 -s \"\$ip\" >/dev/null 2>&1; conntrack -D -f ipv6 -d \"\$ip\" >/dev/null 2>&1;; "
            ."*) conntrack -D -s \"\$ip\" >/dev/null 2>&1; conntrack -D -d \"\$ip\" >/dev/null 2>&1;; "
            ."esac; done";
        // Chuta o aparelho do Wi-Fi (deauth) em todas as APs: ele reconecta
        // sozinho em ~2s e, no reconnect, o popup do captive portal aparece NA
        // HORA — sem ninguém reconectar na mão. Só o MAC bloqueado é afetado
        // (câmeras e outros aparelhos não); em aparelho no cabo, não faz nada.
        $kick = "for ap in \$(ubus list 2>/dev/null | grep '^hostapd\\.'); do "
            ."ubus call \"\$ap\" del_client '{\"addr\":\"{$mac}\",\"reason\":1,\"deauth\":true,\"ban_time\":0}' >/dev/null 2>&1; done";
        $cmds[] = "/etc/init.d/firewall reload >/dev/null 2>&1; rc=\$?; {$kill}; {$kick}; exit \$rc";

        return $this->run(implode('; ', $cmds));
    }

    public function unblock(string $mac): bool
    {
        $mac = $this->normalizeMac($mac);
        if ($mac === null) {
            return false;
        }

        $s = $this->section($mac);
        $p = $this->portalSection($mac);
        $cmd = implode('; ', [
            "uci -q delete firewall.{$s}",
            "uci -q delete firewall.{$p}",
            'uci commit firewall',
            '/etc/init.d/firewall reload >/dev/null 2>&1',
        ]);

        return $this->run($cmd);
    }

    /**
     * Valida e normaliza o MAC (minúsculo). Null se inválido — barreira contra
     * qualquer valor estranho antes de chegar perto de um comando shell.
     */
    private function normalizeMac(string $mac): ?string
    {
        $mac = strtolower(trim($mac));

        return preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac) ? $mac : null;
    }

    private function section(string $mac): string
    {
        return 'wsk_'.preg_replace('/[^0-9a-f]/', '', $mac);
    }

    /**
     * Seção do redirect (captive portal) do MAC. Prefixo distinto do REJECT
     * pra poder criar/remover os dois de forma independente.
     */
    private function portalSection(string $mac): string
    {
        return 'wsk_p_'.preg_replace('/[^0-9a-f]/', '', $mac);
    }

    private function run(string $remote): bool
    {
        $cfg = config('winchestack.openwrt');

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
            return Process::timeout(15)->run($args)->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
