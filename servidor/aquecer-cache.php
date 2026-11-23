<?php
/**
 * 23/11 (passo 76): deixa as páginas de cidade e de área sempre prontas no cache. Abre a página inicial e as páginas que
 * estão nos sitemaps de cidades e categorias (as que o Google visita). Página já guardada volta em ~0,07 s; a que tinha
 * saído do cache (aprovação no admin, virada do dia, 1 h vencida) é montada aqui, e não na visita do Google ou de alguém.
 * Roda de 10 em 10 minutos pelo cron (/etc/cron.d/guia-novo). Não conta nos números do site (eles só contam pelo navegador).
 *
 *   php aquecer-cache.php          abre todas e mostra só as lentas
 *   php aquecer-cache.php -v       mostra todas
 */
if (PHP_SAPI !== 'cli') exit("Só pela linha de comando.\n");
require __DIR__ . '/inc/base.php';
$base = rtrim(defined('URL_PUBLICA') ? URL_PUBLICA : 'https://localizafacilapp.com.br', '/');
$verboso = in_array('-v', $argv, true);

function aq_get(string $url, int $tempo = 30): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $tempo, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'LocalizaFacil-aquecer-cache/1.0 (bot)', CURLOPT_ENCODING => '']);
    $corpo = (string)curl_exec($ch);
    $r = [(int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME), $corpo];
    curl_close($ch);
    return $r;
}
$urls = ["$base/"];
[$c, , $indice] = aq_get("$base/sitemap.xml");
if ($c === 200 && preg_match_all('#<loc>([^<]*-(?:cidades|categorias)\.xml)</loc>#', $indice, $mm))
    foreach ($mm[1] as $sm) {
        [$c2, , $x] = aq_get(html_entity_decode($sm));
        if ($c2 === 200 && preg_match_all('#<loc>([^<]+)</loc>#', $x, $ll)) foreach ($ll[1] as $u) $urls[] = html_entity_decode($u);
    }
$urls = array_values(array_unique($urls));
$lentas = 0; $erros = 0; $t0 = microtime(true);
foreach ($urls as $u) {
    [$c, $t] = aq_get($u);
    if ($c !== 200) $erros++;
    if ($t > 1) $lentas++;
    if ($verboso || $t > 1 || $c !== 200) printf("%s %3d %5.2fs %s\n", date('H:i:s'), $c, $t, $u);
}
if ($verboso || $lentas || $erros) printf("%s %d páginas em %.0fs · %d montadas agora (lentas) · %d com erro\n", date('H:i'), count($urls), microtime(true) - $t0, $lentas, $erros);
