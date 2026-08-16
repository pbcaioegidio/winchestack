#!/bin/sh
# Captive portal: responde QUALQUER teste de conectividade (generate_204,
# hotspot-detect.html, connecttest.txt, etc.) com um 302 para a pagina de
# bloqueio. O 302 e um sinal mais forte de "portal" do que servir a pagina em
# 200, entao Android/Windows abrem/reabrem o portal com mais vontade.
#
# Servido pela instancia uhttpd 'portal' (192.168.1.1:8090) como error_page:
# qualquer caminho inexistente cai aqui. A raiz "/" continua servindo a
# index.html (200), entao nao ha loop de redirect.
printf 'Status: 302 Found\r\n'
printf 'Location: http://192.168.1.1:8090/\r\n'
printf 'Cache-Control: no-store\r\n'
printf 'Content-Type: text/html; charset=utf-8\r\n'
printf '\r\n'
printf '<!doctype html><meta charset="utf-8"><title>Acesso bloqueado</title>\n'
printf '<p>Redirecionando para o aviso de bloqueio...</p>\n'
