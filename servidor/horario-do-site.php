<?php
/**
 * 23/11 (passo 61): lê o HORÁRIO escrito no site próprio de cada comércio (dados schema.org ou texto como
 * "Segunda a sexta, das 8h às 18h") e guarda em horario_site como PENDENTE. Nada vai para a ficha sozinho: um admin
 * confere o trecho do site, corrige se precisar e aprova em Admin > Comércios > Sobre pelo site > Horários.
 * Só fichas SEM horário (nunca troca o que o dono ou a equipe informou).
 *
 *   php horario-do-site.php                 fichas que estão no Google, com site, ainda não lidas (até 300)
 *   php horario-do-site.php --todas         todas as fichas com site (use --limite=4000 para ler todas de uma vez)
 *   php horario-do-site.php --limite=50     no máximo 50
 *   php horario-do-site.php --id=5576       só uma ficha (lê de novo mesmo já lida)
 *   php horario-do-site.php --teste         só mostra, não grava
 *
 * Educado com os sites, igual ao sobre-do-site.php: robots.txt, 1 site por vez, pausa de 1 s, até 600 KB e 10 s.
 * Lê a página inicial e, se não achar, a página de contato (/contato, /contact, /fale-conosco), onde o horário costuma ficar.
 */
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';
require_once __DIR__ . '/inc/horarios.php';
require_once __DIR__ . '/inc/site-robo.php';

if (PHP_SAPI !== 'cli') exit("Só pela linha de comando.\n");
function arg(string $n): ?string { foreach ($GLOBALS['argv'] as $a) if (str_starts_with($a, "--$n=")) return substr($a, strlen($n) + 3); return null; }
$teste = in_array('--teste', $argv, true);
$todas = in_array('--todas', $argv, true);
$limite = max(1, (int)(arg('limite') ?? 300));
$so_id = (int)(arg('id') ?? 0);

$sem_horario = "(p.horarios IS NULL OR p.horarios IN ('', '[]', '{}'))";
$where = "p.status = 'ativo' AND p.site IS NOT NULL AND p.site <> '' AND $sem_horario";
if ($so_id) $where = "p.id = $so_id AND p.site IS NOT NULL AND p.site <> ''";
else {
    $where .= ' AND NOT EXISTS (SELECT 1 FROM horario_site hs WHERE hs.profissional_id = p.id)';
    // site que o robô do Sobre já viu fora do ar: nem tenta de novo
    $where .= " AND NOT EXISTS (SELECT 1 FROM sobre_site s WHERE s.profissional_id = p.id AND s.situacao = 'erro')";
    if (!$todas) $where .= ' AND ' . sql_no_google();
}
$fichas = linhas("SELECT p.id, p.nome, p.site FROM profissionais p WHERE $where ORDER BY p.popularidade DESC, p.id LIMIT $limite");
echo count($fichas) . ' fichas para ler' . ($teste ? ' (teste: não grava)' : '') . "\n";
$conta = ['pendente' => 0, 'nao_achou' => 0, 'erro' => 0, 'fora' => 0];
$robots = [];
foreach ($fichas as $f) {
    $url = trim($f['site']);
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if ($host === '' || preg_match(SOBRE_NAO_PROPRIOS, preg_replace('/^www\./', '', $host))) { $conta['fora']++; continue; }
    $base = parse_url($url, PHP_URL_SCHEME) . '://' . $host;
    $situacao = 'erro'; $h = []; $trecho = null; $motivo = null; $achou_em = $url;
    if ($robots[$host] ??= sobre_robots_proibe($base)) $motivo = 'robots.txt do site não deixa';
    else {
        $r = sobre_baixar($url);
        if ($r['erro'] !== '' || $r['codigo'] >= 400 || $r['codigo'] === 0 || stripos($r['tipo'], 'html') === false)
            $motivo = 'site não abriu (' . ($r['erro'] ?: 'HTTP ' . $r['codigo']) . ')';
        else {
            $final = strtolower((string)parse_url($r['final'], PHP_URL_HOST));
            if ($final !== '' && preg_match(SOBRE_NAO_PROPRIOS, preg_replace('/^www\./', '', $final))) { $conta['fora']++; continue; }
            [$h, $trecho] = horario_extrair($r['corpo']);
            // não achou na inicial: tenta a página de contato (só os links do próprio site que a inicial mostra)
            if (!$h && preg_match_all('#href=["\']([^"\'\#]*(?:contato|contact|fale-conosco|onde-estamos|localizacao|sobre)[^"\'\#]*)["\']#i', $r['corpo'], $lk)) {
                foreach (array_slice(array_unique($lk[1]), 0, 2) as $l) {
                    $u2 = preg_match('#^https?://#i', $l) ? $l : rtrim($base, '/') . '/' . ltrim($l, '/');
                    if (strtolower((string)parse_url($u2, PHP_URL_HOST)) !== $final && strtolower((string)parse_url($u2, PHP_URL_HOST)) !== $host) continue;
                    sleep(1);
                    $r2 = sobre_baixar($u2);
                    if ($r2['codigo'] === 200 && stripos($r2['tipo'], 'html') !== false) { [$h, $trecho] = horario_extrair($r2['corpo']); if ($h) { $achou_em = $u2; break; } }
                }
            }
            $situacao = $h ? 'pendente' : 'nao_achou';
            if ($situacao === 'pendente' && $achou_em === $url) $achou_em = $r['final'] ?: $url;
        }
    }
    $conta[$situacao]++;
    printf("%-9s #%d %s\n          %s\n          %s\n", $situacao, $f['id'], mb_substr($f['nome'], 0, 50), $achou_em,
        $h ? horarios_texto($h) . '   <- "' . mb_substr((string)$trecho, 0, 120) . '"' : (string)$motivo);
    if (!$teste) q('REPLACE INTO horario_site (profissional_id, url, trecho, horarios, situacao, motivo, lido_em) VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$f['id'], mb_substr($achou_em, 0, 300), $trecho !== null ? mb_substr($trecho, 0, 400) : null, $h ? json_encode($h) : null, $situacao, $motivo]);
    sleep(1);
}
printf("\nPara aprovar: %d · sem horário no site: %d · não abriram: %d · não são site próprio: %d\n",
    $conta['pendente'], $conta['nao_achou'], $conta['erro'], $conta['fora']);
