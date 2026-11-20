#!/bin/bash
# LANÇA o Localiza Fácil para o Google (passo 17/18): tira o noindex e o 'sem_google'. A 1ª onda começa sozinha hoje.
# Rodar só DEPOIS de: ativar.sh ok + Acha Fácil fechado para o Google (passo 17, feito pelo Claude no acha-facil).
#   bash servidor/dominio/lancar.sh
set -e
cd "$(dirname "$0")"
H=../publico/.htaccess
grep -q '>>> SEM_GOOGLE' "$H" || { echo "ERRO: $H não é o do domínio (rode ativar.sh antes)."; exit 1; }
cp "$H" "/var/lib/guia-novo/backups/htaccess.antes-lancar.$(date +%Y%m%d%H%M%S)"
sed -i '/>>> SEM_GOOGLE/,/<<< SEM_GOOGLE/d' "$H"
php -r '$f="/etc/guia-novo/config.php"; $s=file_get_contents($f);
  $s=preg_replace("~\x27sem_google\x27 => true,~", "\x27sem_google\x27 => false,", $s); file_put_contents($f, $s);
  $c=require $f; echo "sem_google: ", var_export($c["sem_google"], true), "\n";'
find /var/lib/guia-novo/cache/web -type f -delete 2>/dev/null || true
echo "Robots da inicial: $(curl -s https://localizafacilapp.com.br/ | grep -o 'name="robots"[^>]*' || echo 'sem noindex (certo)')"
echo "Sitemap: $(curl -s https://localizafacilapp.com.br/sitemap.xml | grep -c '<sitemap>') arquivos. Envie https://localizafacilapp.com.br/sitemap.xml no Search Console."
