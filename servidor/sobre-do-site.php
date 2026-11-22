<?php
/**
 * 22/11 (passo 55, E): lê a página inicial do SITE PRÓPRIO de cada comércio e guarda a descrição que o próprio comércio
 * escreveu (meta description / og:description) em sobre_site, como PENDENTE. Nada aparece no site sozinho: um admin
 * aprova, corrige ou recusa em /admin/sobre-site.php. Assim a ficha ganha texto que não veio do Google Maps.
 *
 *   php sobre-do-site.php                 fichas que estão no Google, com site, ainda não lidas (até 300)
 *   php sobre-do-site.php --limite=50     no máximo 50
 *   php sobre-do-site.php --todas         também as fichas que ainda não estão no Google
 *   php sobre-do-site.php --id=5576       só uma ficha (lê de novo mesmo já lida)
 *   php sobre-do-site.php --teste         só mostra, não grava
 *
 * Educado com os sites: respeita o robots.txt, 1 site por vez, pausa de 1 s, no máximo 600 KB e 10 s por página.
 * Facebook, Instagram, WhatsApp, Linktree, iFood, gov.br e encurtadores não são site próprio: ficam de fora.
 */
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';

if (PHP_SAPI !== 'cli') exit("Só pela linha de comando.\n");
function arg(string $n): ?string { foreach ($GLOBALS['argv'] as $a) if (str_starts_with($a, "--$n=")) return substr($a, strlen($n) + 3); return null; }
$teste = in_array('--teste', $argv, true);
$todas = in_array('--todas', $argv, true);
$limite = max(1, (int)(arg('limite') ?? 300));
$so_id = (int)(arg('id') ?? 0);

const SOBRE_UA = 'LocalizaFacilApp/1.0 (+https://localizafacilapp.com.br/contato.php)';
const SOBRE_NAO_PROPRIOS = '/(^|\.)(facebook\.com|fb\.com|instagram\.com|wa\.me|whatsapp\.com|linktr\.ee|linkin\.bio|bio\.link|beacons\.ai|ifood\.com\.br|99app\.com|google\.com|goo\.gl|g\.page|bit\.ly|tinyurl\.com|youtube\.com|tiktok\.com|twitter\.com|x\.com|gov\.br|mercadolivre\.com\.br|olx\.com\.br|doctoralia\.com\.br|booking\.com|tripadvisor\.com(\.br)?|airbnb\.com(\.br)?|ueniweb\.com|negocio\.site|business\.site)$/i';
// texto padrão de criador de site / em construção / genérico demais: não serve
const SOBRE_GENERICOS = '/(just another wordpress|site em constru|em manuten|coming soon|under construction|lorem ipsum|create your website|crie seu site|this domain|domínio (está )?à venda|parked|hostgator|locaweb|wix\.com|página inicial$|^home$|^início$|bem-vindo ao site|welcome to)/iu';

function sobre_baixar(string $url, int $max = 600000): array {
    $ch = curl_init($url);
    $corpo = '';
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => SOBRE_UA, CURLOPT_ENCODING => '',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Language: pt-BR,pt;q=0.9'],
        CURLOPT_WRITEFUNCTION => function ($ch, $d) use (&$corpo, $max) { $corpo .= $d; return strlen($corpo) > $max ? 0 : strlen($d); }]);
    curl_exec($ch);
    $r = ['codigo' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'final' => (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
        'tipo' => (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE), 'corpo' => $corpo, 'erro' => curl_errno($ch) && strlen($corpo) <= $max ? curl_error($ch) : ''];
    curl_close($ch);
    return $r;
}
/** robots.txt: o grupo "User-agent: *" (ou o nosso) proíbe "/"? */
function sobre_robots_proibe(string $base): bool {
    $r = sobre_baixar(rtrim($base, '/') . '/robots.txt', 100000);
    if ($r['codigo'] !== 200 || stripos($r['tipo'], 'text/plain') === false) return false;
    $vale = false; $proibe = false;
    foreach (preg_split('/\R/', $r['corpo']) as $l) {
        $l = trim(preg_replace('/#.*/', '', $l));
        if (preg_match('/^user-agent:\s*(.+)$/i', $l, $m)) { $ua = trim($m[1]); $vale = $ua === '*' || stripos($ua, 'localizafacil') !== false; continue; }
        if ($vale && preg_match('/^disallow:\s*(\S*)/i', $l, $m) && $m[1] === '/') $proibe = true;
    }
    return $proibe;
}
function sobre_limpar(string $t): string {
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!mb_check_encoding($t, 'UTF-8')) $t = mb_convert_encoding($t, 'UTF-8', 'ISO-8859-1');
    return trim(preg_replace('/\s+/u', ' ', strip_tags($t)));
}
/** [titulo, texto, motivo-se-não-serve] da página. */
function sobre_extrair(string $html, string $nome): array {
    $meta = function (string $chave) use ($html): string {
        foreach (['/<meta[^>]+(?:name|property)\s*=\s*["\']' . $chave . '["\'][^>]*content\s*=\s*["\']([^"\']*)["\']/is',
                  '/<meta[^>]+content\s*=\s*["\']([^"\']*)["\'][^>]*(?:name|property)\s*=\s*["\']' . $chave . '["\']/is'] as $re)
            if (preg_match($re, $html, $m)) return sobre_limpar($m[1]);
        return '';
    };
    preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $mt);
    $titulo = sobre_limpar($mt[1] ?? '');
    $texto = $meta('description');
    $og = $meta('og:description');
    if (mb_strlen($og) > mb_strlen($texto)) $texto = $og;
    if ($texto === '') return [$titulo, '', 'o site não tem descrição'];
    if (mb_strlen($texto) < 50) return [$titulo, $texto, 'descrição curta demais'];
    if (preg_match(SOBRE_GENERICOS, $texto)) return [$titulo, $texto, 'texto padrão do criador de site'];
    if (mb_strtolower($texto) === mb_strtolower($titulo)) return [$titulo, $texto, 'igual ao título'];
    if (!preg_match('/\b(de|da|do|em|com|para|e|os|as|seu|sua|nossa|nosso)\b/iu', $texto)) return [$titulo, $texto, 'não parece português'];
    if (mb_strlen($texto) > 500) $texto = rtrim(mb_substr($texto, 0, 497)) . '…';
    return [$titulo, $texto, ''];
}

$where = "p.status = 'ativo' AND p.site IS NOT NULL AND p.site <> ''";
if ($so_id) $where .= ' AND p.id = ' . $so_id;
else {
    $where .= ' AND NOT EXISTS (SELECT 1 FROM sobre_site s WHERE s.profissional_id = p.id)';
    if (!$todas) $where .= ' AND ' . sql_no_google();
}
$fichas = linhas("SELECT p.id, p.nome, p.site, p.cidade FROM profissionais p WHERE $where ORDER BY p.popularidade DESC, p.id LIMIT $limite");
echo count($fichas) . " fichas para ler" . ($teste ? ' (teste: não grava)' : '') . "\n";
$conta = ['pendente' => 0, 'sem_texto' => 0, 'erro' => 0, 'fora' => 0];
$robots = [];
foreach ($fichas as $f) {
    $url = trim($f['site']);
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $host_sem_www = preg_replace('/^www\./', '', $host);
    if ($host === '' || preg_match(SOBRE_NAO_PROPRIOS, $host_sem_www)) { $conta['fora']++; continue; }   // não é site próprio: nem grava
    $base = parse_url($url, PHP_URL_SCHEME) . '://' . $host;
    $situacao = 'erro'; $titulo = null; $texto = null; $motivo = null;
    if ($robots[$host] ??= sobre_robots_proibe($base)) $motivo = 'robots.txt do site não deixa';
    else {
        $r = sobre_baixar($url);
        if ($r['erro'] !== '' || $r['codigo'] >= 400 || $r['codigo'] === 0) $motivo = 'site não abriu (' . ($r['erro'] ?: 'HTTP ' . $r['codigo']) . ')';
        elseif (stripos($r['tipo'], 'html') === false) $motivo = 'não é página (' . $r['tipo'] . ')';
        else {
            $final = strtolower((string)parse_url($r['final'], PHP_URL_HOST));
            if ($final !== '' && preg_match(SOBRE_NAO_PROPRIOS, preg_replace('/^www\./', '', $final))) { $conta['fora']++; continue; }   // redireciona para Facebook etc.
            [$titulo, $texto, $motivo] = sobre_extrair($r['corpo'], $f['nome']);
            $situacao = $motivo === '' ? 'pendente' : 'sem_texto';
            if ($motivo === '') $motivo = null;
            $url = $r['final'] ?: $url;
        }
    }
    $conta[$situacao]++;
    printf("%-7s #%d %s\n        %s\n        %s\n", $situacao, $f['id'], mb_substr($f['nome'], 0, 50), $url, $motivo ?? mb_substr((string)$texto, 0, 160));
    if (!$teste) q('REPLACE INTO sobre_site (profissional_id, url, titulo, texto, situacao, motivo, lido_em) VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$f['id'], mb_substr($url, 0, 300), $titulo !== null ? mb_substr($titulo, 0, 300) : null, $texto, $situacao, $motivo]);
    sleep(1);
}
printf("\nPara aprovar: %d · sem texto que sirva: %d · não abriram: %d · não são site próprio (Facebook, Instagram...): %d\n",
    $conta['pendente'], $conta['sem_texto'], $conta['erro'], $conta['fora']);
