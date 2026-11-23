<?php
/**
 * 23/11 (passo 70): robô "Dados pelo site". Lê o SITE PRÓPRIO de cada comércio UMA vez (página inicial + até 2 páginas
 * de contato) e acha tudo o que serve para a ficha, menos fotos:
 *   WhatsApp, telefone, Instagram, Facebook, e-mail de contato, o que faz ou vende (serviços) e endereço   -> dados_site
 *   Sobre (descrição do próprio comércio)                                                                -> sobre_site
 *   Horário                                                                                              -> horario_site
 * Tudo fica PENDENTE. Nada aparece no site sozinho: um admin aprova em Admin > Comércios > "🧩 Dados pelo site"
 * (/admin/dados-site.php), "Sobre pelo site" e "Horário pelo site". Só propõe preencher o que falta; quando o site mostra
 * um WhatsApp/telefone/Instagram/Facebook/e-mail DIFERENTE do da ficha, avisa ("diferente") e o admin decide.
 * Serviços e endereço só quando a ficha não tem (endereço também quando o da ficha é inválido).
 *
 *   php dados-do-site.php                 fichas que estão no Google, com site, ainda não lidas (até 300)
 *   php dados-do-site.php --todas         todas as fichas com site (use --limite=6000 para ler todas de uma vez)
 *   php dados-do-site.php --limite=50     no máximo 50
 *   php dados-do-site.php --cidade=pocos-de-caldas   só uma cidade (cidade nova)
 *   php dados-do-site.php --id=5576       só uma ficha (lê de novo mesmo já lida)
 *   php dados-do-site.php --teste         só mostra, não grava
 *
 * Educado com os sites, igual aos outros robôs: respeita o robots.txt, 1 site por vez, pausa de 1 s entre páginas,
 * no máximo 600 KB e 10 s por página. Facebook, Instagram, WhatsApp, Linktree, iFood, gov.br e encurtadores não são
 * site próprio: ficam de fora.
 */
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';
require_once __DIR__ . '/inc/limpeza.php';
require_once __DIR__ . '/inc/horarios.php';
require_once __DIR__ . '/inc/dados-site.php';

if (PHP_SAPI !== 'cli') exit("Só pela linha de comando.\n");
function arg(string $n): ?string { foreach ($GLOBALS['argv'] as $a) if (str_starts_with($a, "--$n=")) return substr($a, strlen($n) + 3); return null; }
$teste = in_array('--teste', $argv, true);
$todas = in_array('--todas', $argv, true);
$limite = max(1, (int)(arg('limite') ?? 300));
$so_id = (int)(arg('id') ?? 0);
$cidade = arg('cidade');

$where = "p.status = 'ativo' AND p.site IS NOT NULL AND p.site <> ''";
$params = [];
if ($so_id) $where .= ' AND p.id = ' . $so_id;
else {
    $where .= ' AND NOT EXISTS (SELECT 1 FROM dados_site_lidos d WHERE d.profissional_id = p.id)';
    if ($cidade !== null) { $where .= ' AND p.cidade = ?'; $params[] = $cidade; }
    elseif (!$todas) $where .= ' AND ' . sql_no_google();
}
$fichas = linhas("SELECT p.* FROM profissionais p WHERE $where ORDER BY p.popularidade DESC, p.id LIMIT $limite", $params);
echo count($fichas) . ' fichas para ler' . ($teste ? ' (teste: não grava)' : '') . "\n";
$conta = ['lido' => 0, 'nada' => 0, 'erro' => 0, 'fora' => 0, 'dados' => 0, 'sobre' => 0, 'horario' => 0];
$por_campo = array_fill_keys(array_keys(DS_CAMPOS), 0);
$robots = [];

/** grava a leitura da ficha */
$gravar_lido = function (array $f, string $url, int $paginas, int $achados, string $situacao, ?string $motivo) use ($teste) {
    if ($teste) return;
    q('REPLACE INTO dados_site_lidos (profissional_id, url, paginas, achados, situacao, motivo, lido_em) VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$f['id'], mb_substr($url, 0, 300), $paginas, $achados, $situacao, $motivo !== null ? mb_substr($motivo, 0, 200) : null]);
};

foreach ($fichas as $f) {
    $url = trim($f['site']);
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if ($host === '' || preg_match(SOBRE_NAO_PROPRIOS, preg_replace('/^www\./', '', $host))) { $conta['fora']++; continue; }
    $base = parse_url($url, PHP_URL_SCHEME) . '://' . $host;
    if ($robots[$host] ??= sobre_robots_proibe($base)) {
        $conta['erro']++; printf("erro    #%d %s\n        robots.txt do site não deixa\n", $f['id'], mb_substr($f['nome'], 0, 50));
        $gravar_lido($f, $url, 0, 0, 'erro', 'robots.txt do site não deixa'); continue;
    }
    $r = sobre_baixar($url);
    if ($r['erro'] !== '' || $r['codigo'] >= 400 || $r['codigo'] === 0 || stripos($r['tipo'], 'html') === false) {
        $motivo = 'site não abriu (' . ($r['erro'] ?: 'HTTP ' . $r['codigo']) . ')';
        $conta['erro']++; printf("erro    #%d %s\n        %s\n", $f['id'], mb_substr($f['nome'], 0, 50), $motivo);
        $gravar_lido($f, $url, 0, 0, 'erro', $motivo); sleep(1); continue;
    }
    $final_url = $r['final'] ?: $url;
    $final = strtolower((string)parse_url($final_url, PHP_URL_HOST));
    if ($final !== '' && preg_match(SOBRE_NAO_PROPRIOS, preg_replace('/^www\./', '', $final))) { $conta['fora']++; continue; }   // redireciona para Facebook etc.

    // páginas de contato do próprio site (os links que a inicial mostra)
    $paginas = [$final_url => $r['corpo']];
    if (preg_match_all('#href=["\']([^"\'\#]*(?:contato|contact|fale-conosco|onde-estamos|localizacao|sobre|quem-somos)[^"\'\#]*)["\']#i', $r['corpo'], $lk)) {
        foreach (array_unique($lk[1]) as $l) {
            if (count($paginas) >= 3) break;
            $l = html_entity_decode($l, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $u2 = preg_match('#^https?://#i', $l) ? $l : (str_starts_with($l, '//') ? 'https:' . $l : rtrim(parse_url($final_url, PHP_URL_SCHEME) . '://' . $final, '/') . '/' . ltrim($l, '/'));
            $h2 = strtolower((string)parse_url($u2, PHP_URL_HOST));
            if (($h2 !== $final && $h2 !== $host) || isset($paginas[$u2])) continue;
            sleep(1);
            $r2 = sobre_baixar($u2);
            if ($r2['codigo'] === 200 && stripos($r2['tipo'], 'html') !== false) $paginas[$u2] = $r2['corpo'];
        }
    }

    // 1) dados de contato, serviços e endereço
    $achado = dados_extrair(array_values($paginas), $final);
    $propostas = dados_comparar($achado, $f);
    // 2) Sobre (só a página inicial) e horário (inicial, depois contato), se esses robôs ainda não leram esta ficha
    $sobre = null; $horario = null;
    if (!valor('SELECT 1 FROM sobre_site WHERE profissional_id = ?', [$f['id']]) && empty($f['sobre_site'])) {
        [$titulo, $texto, $motivo_s] = sobre_extrair($r['corpo'], $f['nome']);
        if ($motivo_s === '') $sobre = [$titulo, $texto];
    }
    if (!horarios_ler($f['horarios']) && !valor('SELECT 1 FROM horario_site WHERE profissional_id = ?', [$f['id']]))
        foreach ($paginas as $u => $html) { [$h, $trecho] = horario_extrair($html); if ($h) { $horario = [$u, $h, $trecho]; break; } }

    $n = count($propostas) + ($sobre ? 1 : 0) + ($horario ? 1 : 0);
    $situacao = $n ? 'lido' : 'nada';
    $conta[$situacao]++;
    printf("%-7s #%d %s  (%d página%s)\n        %s\n", $situacao, $f['id'], mb_substr($f['nome'], 0, 50), count($paginas), count($paginas) > 1 ? 's' : '', $final_url);
    foreach ($propostas as [$campo, $v, $vf, $tipo, $trecho]) {
        $por_campo[$campo]++; $conta['dados']++;
        printf("        %-9s %-9s %s%s\n", $tipo, $campo, mb_substr($v, 0, 110), $vf ? "   (ficha: $vf)" : '');
        if ($teste) continue;
        $ja = linha('SELECT situacao FROM dados_site WHERE profissional_id = ? AND campo = ?', [$f['id'], $campo]);
        if ($ja && $ja['situacao'] !== 'pendente') continue;   // já decidido pelo admin: não volta
        q('REPLACE INTO dados_site (profissional_id, campo, valor, valor_ficha, tipo, trecho, url, situacao, lido_em) VALUES (?, ?, ?, ?, ?, ?, ?, \'pendente\', NOW())',
            [$f['id'], $campo, mb_substr($v, 0, 500), $vf !== null ? mb_substr($vf, 0, 500) : null, $tipo, $trecho !== null ? mb_substr($trecho, 0, 400) : null, mb_substr($final_url, 0, 300)]);
    }
    if ($sobre) {
        $conta['sobre']++; printf("        sobre     %s\n", mb_substr($sobre[1], 0, 110));
        if (!$teste) q("INSERT IGNORE INTO sobre_site (profissional_id, url, titulo, texto, situacao, lido_em) VALUES (?, ?, ?, ?, 'pendente', NOW())",
            [$f['id'], mb_substr($final_url, 0, 300), $sobre[0] !== '' ? mb_substr($sobre[0], 0, 300) : null, $sobre[1]]);
    }
    if ($horario) {
        $conta['horario']++; printf("        horário   %s   <- \"%s\"\n", horarios_texto($horario[1]), mb_substr((string)$horario[2], 0, 80));
        if (!$teste) q("INSERT IGNORE INTO horario_site (profissional_id, url, trecho, horarios, situacao, lido_em) VALUES (?, ?, ?, ?, 'pendente', NOW())",
            [$f['id'], mb_substr($horario[0], 0, 300), $horario[2] !== null ? mb_substr($horario[2], 0, 400) : null, json_encode($horario[1])]);
    }
    $gravar_lido($f, $final_url, count($paginas), $n, $situacao, null);
    sleep(1);
}
printf("\nSites lidos: %d com algo novo · %d sem nada novo · %d não abriram · %d não são site próprio\n",
    $conta['lido'], $conta['nada'], $conta['erro'], $conta['fora']);
printf("Para aprovar: %d dados (%s) · %d Sobre · %d horários\n", $conta['dados'],
    implode(', ', array_map(fn($k, $v) => DS_CAMPOS[$k] . " $v", array_keys(array_filter($por_campo)), array_filter($por_campo))) ?: 'nenhum', $conta['sobre'], $conta['horario']);
echo "Aprovar em: https://localizafacilapp.com.br/admin/dados-site.php\n";
