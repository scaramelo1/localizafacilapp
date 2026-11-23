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

require_once __DIR__ . '/inc/site-robo.php';   // 23/11: baixar, robots.txt e limpar texto ficaram em comum com horario-do-site.php

// sobre_extrair() fica em inc/site-robo.php (23/11: o robô dados-do-site.php usa o mesmo)

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
