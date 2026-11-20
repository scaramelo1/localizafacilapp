#!/bin/bash
# Liga o domínio localizafacilapp.com.br: site no Apache, HTTPS (certbot) e fsadesigners.com.br/guia-novo redirecionando.
# O site continua FORA do Google (sem_google + noindex) até rodar lancar.sh.
# Rodar como root na VPS, depois que os registros A do domínio apontarem para esta VPS:  bash servidor/dominio/ativar.sh
set -e
cd "$(dirname "$0")"
IP=$(curl -s -4 ifconfig.me)
for h in localizafacilapp.com.br www.localizafacilapp.com.br; do
    d=$(dig +short "$h" A | tail -1)
    if [ "$d" != "$IP" ]; then echo "ERRO: $h aponta para '${d:-nada}', e a VPS é $IP. Acerte o DNS no Registro.br e espere propagar."; exit 1; fi
done
cp localizafacilapp.conf /etc/apache2/sites-available/localizafacilapp.conf
a2ensite -q localizafacilapp
apache2ctl configtest && systemctl reload apache2
certbot --apache --non-interactive --redirect -d localizafacilapp.com.br -d www.localizafacilapp.com.br
code=$(curl -s -o /dev/null -w '%{http_code}' https://localizafacilapp.com.br/)
if [ "$code" != "200" ]; then echo "ERRO: https://localizafacilapp.com.br/ respondeu $code; redirecionamento NÃO ligado."; exit 1; fi
# endereço público e retorno do login com o Google passam a ser os do domínio (sem_google continua true)
php -r '$f="/etc/guia-novo/config.php"; $s=file_get_contents($f);
  $s=preg_replace("~\x27endereco_publico\x27 => \x27[^\x27]*\x27,~", "\x27endereco_publico\x27 => \x27https://localizafacilapp.com.br/\x27,", $s);
  $s=preg_replace("~\x27google_retorno\x27 => \x27[^\x27]*\x27,~", "\x27google_retorno\x27 => \x27https://localizafacilapp.com.br/google/retorno.php\x27,", $s);
  file_put_contents($f, $s);'
cp htaccess-dominio ../publico/.htaccess
find /var/lib/guia-novo/cache/web /var/lib/guia-novo/og -type f -delete 2>/dev/null || true
echo "Pronto: https://localizafacilapp.com.br no ar (ainda fora do Google) e fsadesigners.com.br/guia-novo redirecionando."
