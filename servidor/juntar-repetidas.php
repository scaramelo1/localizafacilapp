<?php
/**
 * 23/11 (passo 71, pedido do usuário): junta SOZINHO as fichas repetidas em que TRÊS coisas batem ao mesmo tempo:
 *   1) mesmo endereço (rua + número, dup_chave_endereco)
 *   2) mesmo telefone ou WhatsApp (os 8 últimos dígitos)
 *   3) nome quase idêntico: as mesmas palavras próprias ("Supermercado Forte Mix" = "Supermercados Forte Mix") ou 85%+ de letras iguais
 * Fica a melhor ficha do grupo (dup_pontos: com dono > confirmada > mais completa > mais conhecida > mais antiga); as outras
 * passam o que só elas tinham para a que fica, são pausadas e o link delas leva (301) para a que ficou (dup_juntar).
 * Ficha com dono nunca sai. Tudo aparece em /admin/duplicadas.php > Juntadas, com "Desfazer".
 *
 *   php juntar-repetidas.php             só mostra (prévia)
 *   php juntar-repetidas.php --aplicar   junta (faz cópia das tabelas antes)
 */
declare(strict_types=1);
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/duplicadas.php';
if (PHP_SAPI !== 'cli') exit("Só pela linha de comando.\n");
$aplicar = in_array('--aplicar', $argv, true);

function jr_tels(array $p): array {
    $r = [];
    foreach ([$p['telefone'], $p['whatsapp']] as $t) { $d = preg_replace('/\D/', '', (string)$t); if (strlen($d) >= 8) $r[] = substr($d, -8); }
    return array_unique($r);
}
function jr_nome(string $n): string {
    $t = preg_replace('/[^a-z0-9 ]+/', ' ', chave_texto(nome_curto($n, 120)));
    return trim(preg_replace('/\s+/', ' ', $t));
}
/** Palavras do TIPO de comércio no nome ("advocacia", "corretora", "pousada"), sem cidade nem ligação. */
function jr_tipos(string $nome): array {
    static $nao_tipo = ['the', 'and', 'dos', 'das', 'para', 'com', 'sao', 'joao', 'boa', 'vista', 'pocos', 'caldas', 'vargem', 'grande', 'sul',
        'aguai', 'santa', 'rita', 'sapucai', 'cidade', 'centro', 'ltda', 'eireli', 'unidade', 'filial', 'matriz', 'grupo', 'empresa',
        'doutor', 'doutora', 'dra', 'dr', 'express', 'delivery', 'store', 'center', 'comercio', 'comercial'];
    $t = preg_replace('/[^a-z0-9 ]+/', ' ', chave_texto(nome_curto($nome, 120)));
    return array_values(array_unique(array_filter(preg_split('/\s+/', trim($t)), fn($w) => in_array($w, DUP_GENERICAS, true) && !in_array($w, $nao_tipo, true))));
}
function jr_nomes_quase_iguais(string $a, string $b): bool {
    // os dois dizem o tipo e não têm nenhum em comum: negócios diferentes da mesma pessoa ("Seixas Contabilidade" x "Seixas Advocacia")
    $ta = jr_tipos($a); $tb = jr_tipos($b);
    if ($ta && $tb && !array_intersect($ta, $tb)) return false;
    $pa = dup_palavras($a); $pb = dup_palavras($b);
    sort($pa); sort($pb);
    if ($pa && $pa === $pb) return true;
    $na = jr_nome($a); $nb = jr_nome($b);
    if ($na === $nb) return true;
    similar_text($na, $nb, $pct);
    return $pct >= 85;
}
/** Os três critérios entre duas fichas. */
function jr_mesma(array $x, array $y): bool {
    $ea = dup_chave_endereco($x['endereco']); $eb = dup_chave_endereco($y['endereco']);
    if ($ea === null || $ea !== $eb) return false;
    if (!array_intersect(jr_tels($x), jr_tels($y))) return false;
    return jr_nomes_quase_iguais((string)$x['nome'], (string)$y['nome']);
}

$plano = [];   // [fica, [sai, ...]]
foreach (dup_grupos() as $g) {
    $fica = $g[0];   // dup_grupos já põe a melhor primeiro
    $sai = array_values(array_filter(array_slice($g, 1), fn($p) => !$p['tem_dono'] && jr_mesma($fica, $p)));
    if ($sai) $plano[] = [$fica, $sai];
}
$n_sai = array_sum(array_map(fn($x) => count($x[1]), $plano));
printf("%d grupos · %d fichas repetidas para juntar%s\n\n", count($plano), $n_sai, $aplicar ? '' : ' (prévia: nada foi mudado)');
foreach ($plano as [$f, $ss]) {
    printf("FICA #%d %s · %s · %s · %s\n", $f['id'], nome_curto((string)$f['nome']), nome_cidade((string)$f['cidade'], true), $f['endereco'], telefone_formatado($f['telefone'] ?: $f['whatsapp']));
    foreach ($ss as $s) printf("  sai #%d %s · %s\n", $s['id'], nome_curto((string)$s['nome']), $s['endereco']);
}
if (!$aplicar || !$plano) exit;

// cópia antes (as tabelas que dup_juntar mexe)
$c = config();
$arq = '/var/lib/guia-novo/backups/antes-juntar-repetidas-' . date('Ymd-His') . '.sql.gz';
@mkdir(dirname($arq), 0750, true);
$cnf = tempnam(sys_get_temp_dir(), 'jr');   // senha num arquivo só nosso (não aparece na lista de processos)
file_put_contents($cnf, "[client]\nuser=\"{$c['db_usuario']}\"\npassword=\"{$c['db_senha']}\"\nhost=\"{$c['db_host']}\"\n");
chmod($cnf, 0600);
$cmd = 'mysqldump --defaults-extra-file=' . escapeshellarg($cnf) . ' --single-transaction --no-tablespaces ' . escapeshellarg($c['db_nome'])
    . ' profissionais favoritos avaliacoes fichas_iguais | gzip > ' . escapeshellarg($arq);
exec($cmd, $o, $rc);
unlink($cnf);
if ($rc !== 0 || filesize($arq) < 1000) exit("Não consegui fazer a cópia antes ($arq). Nada foi mudado.\n");
echo "\nCópia antes: $arq\n";
$ok = 0; $erros = [];
foreach ($plano as [$f, $ss]) foreach ($ss as $s) {
    $e = dup_juntar((int)$f['id'], (int)$s['id'], null);
    if ($e === null) $ok++; else $erros[] = "#{$s['id']} -> #{$f['id']}: $e";
}
web_cache_limpar();
echo "✔ $ok fichas juntadas" . ($erros ? ' · não juntadas: ' . count($erros) . "\n  " . implode("\n  ", $erros) : '') . "\n";
