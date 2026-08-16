<?php

$localNetwork = is_file(__DIR__.'/network-labels.local.php')
    ? require __DIR__.'/network-labels.local.php'
    : [];

return [

    /*
    |--------------------------------------------------------------------------
    | Espectadores das câmeras (integração com o Watchtower)
    |--------------------------------------------------------------------------
    |
    | O relay do watchtower (stream-relay.mjs) expõe /health com a contagem de
    | viewers por câmera. Em produção, costuma rodar em 127.0.0.1:9000.
    | A rota interna /internal/cameras traduz id -> nome das câmeras.
    |
    */
    'relay' => [
        'health_url' => env('WATCHTOWER_RELAY_HEALTH_URL', 'http://127.0.0.1:9000/health'),
        'viewers_url' => env('WATCHTOWER_RELAY_VIEWERS_URL', 'http://127.0.0.1:9000/viewers'),
        'cameras_url' => env('WATCHTOWER_CAMERAS_URL', 'http://127.0.0.1:8000/internal/cameras'),
        // O gravador do watchtower aparece como 1 viewer por câmera no relay.
        // Descontamos esse 1 para estimar "pessoas".
        'discount_recorder' => env('WATCHTOWER_DISCOUNT_RECORDER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Roteador OpenWrt (LAN) — quem está navegando
    |--------------------------------------------------------------------------
    |
    | Consultado por SSH (chave, sem senha). Localmente em 192.168.1.1.
    |
    */
    'openwrt' => [
        'host' => env('OPENWRT_HOST', '192.168.1.1'),
        'user' => env('OPENWRT_SSH_USER', 'root'),
        'port' => (int) env('OPENWRT_SSH_PORT', 22),
        'key' => env('OPENWRT_SSH_KEY'),

        /*
        | Captive portal de "acesso bloqueado". Ao bloquear um aparelho, além de
        | cortar a WAN, o HTTP dele (porta 80) é redirecionado (DNAT) pra uma
        | página local servida por uma instância uhttpd dedicada no roteador,
        | em <ip>:<port>. O HTTPS continua só caindo (não dá pra forjar
        | certificado), mas o teste de conectividade do sistema (HTTP) é
        | desviado e abre a página de aviso sozinho. 'ip' é o IP de LAN do
        | roteador (onde o uhttpd do portal escuta), não o de SSH.
        | Página versionada em deploy/openwrt/blocked-portal.html.
        */
        'portal' => [
            'enabled' => (bool) env('OPENWRT_PORTAL_ENABLED', true),
            'ip' => env('OPENWRT_PORTAL_IP', '192.168.1.1'),
            'port' => (int) env('OPENWRT_PORTAL_PORT', 8090),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Identificação dos dispositivos da rede
    |--------------------------------------------------------------------------
    |
    | Dá nome e tipo aos aparelhos que aparecem na tela "Rede". Duas camadas:
    |
    |  - labels: apelido manual por MAC completo (tem prioridade).
    |  - oui: fabricante + tipo pelo prefixo do MAC (3 primeiros octetos).
    |    Quando não há apelido nem hostname, mostramos o fabricante no lugar de
    |    "Desconhecido".
    |
    | Apelidos reais ficam em config/network-labels.local.php (fora do git).
    | Veja network-labels.local.php.example.
    |
    | Tipos (kind) usados pelos ícones do painel: solar, camera, celular,
    | computador, servidor, tv, tvbox, smarthome, printer, router, dispositivo,
    | desconhecido.
    |
    */
    'network' => [

        'labels' => array_replace([
            'aa:bb:cc:dd:ee:01' => ['name' => 'TV da sala', 'kind' => 'tv'],
            'aa:bb:cc:dd:ee:02' => ['name' => 'Notebook', 'kind' => 'computador'],
            'aa:bb:cc:dd:ee:03' => ['name' => 'Câmera da entrada', 'kind' => 'camera'],
        ], $localNetwork['labels'] ?? []),

        'oui' => array_replace([
            '38:26:56' => ['vendor' => 'TCL', 'kind' => 'tv'],
            'a0:4f:85' => ['vendor' => 'LG', 'kind' => 'celular'],
            '9c:ae:d3' => ['vendor' => 'Epson', 'kind' => 'printer'],
        ], $localNetwork['oui'] ?? []),

    ],

];
