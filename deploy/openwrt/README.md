# Captive portal de "acesso bloqueado" (OpenWrt)

Quando o winchestack bloqueia um dispositivo, ele faz duas coisas no roteador
(via SSH/uci, por MAC — ver `app/Services/RouterControlService.php`):

1. **Corta a internet**: regra de firewall `wsk_<mac>` (lan→wan REJECT pelo MAC)
   e, em seguida, **derruba as conexões já abertas** do aparelho (limpa o
   conntrack dos IPs v4/v6 daquele MAC). Assim o bloqueio é **imediato** — sem
   isso ele "demora", porque o fw4 aceita as conexões `established` antes da
   nossa regra, então streaming/download em andamento seguem até expirar.
   Requer o pacote `conntrack` no roteador (veja infra abaixo).
2. **Mostra a página de bloqueio**: redirect `wsk_p_<mac>` (DNAT) que joga o
   **HTTP (porta 80)** do aparelho para uma página local servida por uma
   instância `uhttpd` dedicada no roteador (`<ip>:<port>`, padrão
   `192.168.1.1:8090`). Essa instância responde os testes de conectividade do
   sistema com um **302** (ver `portal-redirect.cgi`), sinal forte de "portal"
   que faz o Android/Windows abrirem o aviso sozinhos com mais confiabilidade.

> HTTPS **não** mostra a página (não dá pra forjar certificado de `instagram.com`
> etc.) — esses sites só dão erro de conexão. Mas o **teste de conectividade do
> sistema** (que é HTTP) é desviado para a página, então o aparelho abre sozinho
> a janela "Entrar na rede" com o aviso. Sites HTTP "pelados" também mostram.

## Infra fixa no roteador (uma vez)

Isto NÃO é criado pelo código — é configurado uma vez no roteador e persiste
(uci + overlay). Refaça se o roteador for resetado/reflashado.

```sh
# 0) Ferramenta conntrack: faz o bloqueio cortar NA HORA as conexões já abertas
ssh root@192.168.1.1 'apk add conntrack'

# 1) Página + CGI de redirect (deste repo) para o roteador
ssh root@192.168.1.1 'mkdir -p /www-portal/cgi-bin'
ssh root@192.168.1.1 'cat > /www-portal/index.html' < deploy/openwrt/blocked-portal.html
ssh root@192.168.1.1 'cat > /www-portal/cgi-bin/redirect && chmod 755 /www-portal/cgi-bin/redirect' < deploy/openwrt/portal-redirect.cgi

# 2) Instância uhttpd dedicada na porta 8090 (só no IP de LAN)
ssh root@192.168.1.1 '
  uci set uhttpd.portal=uhttpd
  uci set uhttpd.portal.listen_http="192.168.1.1:8090"
  uci set uhttpd.portal.home="/www-portal"
  uci set uhttpd.portal.rfc1918_filter="0"
  uci set uhttpd.portal.max_requests="5"
  uci set uhttpd.portal.cgi_prefix="/cgi-bin"
  uci set uhttpd.portal.error_page="/cgi-bin/redirect"
  uci commit uhttpd
  /etc/init.d/uhttpd restart
'

# 3) Conferir: raiz mostra a pagina; path qualquer redireciona (302 -> pagina)
ssh root@192.168.1.1 'uclient-fetch -q -O - http://192.168.1.1:8090/ | grep -o "Acesso bloqueado"'
ssh root@192.168.1.1 'uclient-fetch -O - http://192.168.1.1:8090/generate_204 2>&1 | grep -i redirect'
```

`rfc1918_filter=0` evita que o uhttpd rejeite requisições cujo `Host` é um
domínio público (o aparelho pede `instagram.com` e cai aqui via DNAT).
`error_page=/cgi-bin/redirect` + `cgi_prefix=/cgi-bin` fazem **qualquer caminho**
(inclusive os testes de conectividade do Android/Windows, ex. `/generate_204`)
responder com um **302** para a página — sinal forte de "portal". A raiz `/`
serve a `index.html` (200), então não há loop de redirect.

## Configuração no winchestack (.env, opcional)

```
OPENWRT_PORTAL_ENABLED=true        # desliga o redirect do portal se false
OPENWRT_PORTAL_IP=192.168.1.1      # IP de LAN do roteador (onde o uhttpd escuta)
OPENWRT_PORTAL_PORT=8090
```

Com `OPENWRT_PORTAL_ENABLED=false`, o bloqueio volta a ser só o corte de WAN
(sem página), sem precisar mexer no roteador.
