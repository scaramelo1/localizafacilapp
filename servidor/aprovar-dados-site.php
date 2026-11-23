<?php
/**
 * 23/11 (passo 74, pedido do usuário): aprova EM LOTE os dados pendentes do robô "Dados pelo site" (/admin/dados-site.php),
 * com as mesmas regras que deixam a linha marcada na tela. "Pode aprovar, depois o dono muda."
 *
 *   APROVA   tudo que é "preencher" (a ficha estava sem): WhatsApp, telefone, Instagram, Facebook, e-mail de contato,
 *            o que faz ou vende e endereço.
 *   RECUSA   o que não serve: lixo do criador de site (facebook.com/people, wix, 1234567890, dpo@...), telefone/WhatsApp/e-mail
 *            da central da rede (mesmo valor em 3+ fichas, 0800), número com DDD diferente do telefone da ficha, e
 *            Instagram/Facebook repetido em outras fichas sem nada do nome do comércio (perfil de quem fez o site).
 *   DEIXA    os "diferentes" (a ficha já tem outro número/perfil) pendentes: não troca o que a ficha já tem.
 *
 *   php aprovar-dados-site.php             prévia (não muda nada)
 *   php aprovar-dados-site.php --aplicar   aprova e recusa (cópia das tabelas antes)
 */
declare(strict_types=1);
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';
require_once __DIR__ . '/inc/limpeza.php';
require_once __DIR__ . '/inc/moderacao.php';
require_once __DIR__ . '/inc/duplicadas.php';
require_once __DIR__ . '/inc/dados-site.php';
if (PHP_SAPI !== 'cli') exit("Só pela linha de comando.\n");
$aplicar = in_array('--aplicar', $argv, true);
const AD_COLUNA = ['whatsapp' => 'whatsapp', 'telefone' => 'telefone', 'instagram' => 'instagram', 'facebook' => 'facebook',
    'email' => 'email_contato', 'servicos' => 'servicos', 'endereco' => 'endereco'];

/** Quantas fichas diferentes têm o mesmo valor (no robô ou já na ficha). */
function ad_repete(string $campo, string $valor): int {
    static $cache = [];
    $k = "$campo|$valor";
    if (isset($cache[$k])) return $cache[$k];
    $col = AD_COLUNA[$campo];
    return $cache[$k] = (int)valor("SELECT COUNT(DISTINCT x) FROM (SELECT profissional_id AS x FROM dados_site WHERE campo = ? AND valor = ?
        UNION SELECT id AS x FROM profissionais WHERE $col = ? AND status = 'ativo') t", [$campo, $valor, $valor]);
}
/** O perfil (Instagram/Facebook) tem alguma palavra do nome do comércio? */
function ad_perfil_do_nome(string $perfil, string $nome): bool {
    $perfil = preg_replace('/[^a-z0-9]/', '', chave_texto($perfil));
    $palavras = array_merge(dup_palavras($nome), preg_split('/\s+/', preg_replace('/[^a-z0-9 ]+/', ' ', chave_texto(nome_curto($nome, 120)))));
    foreach ($palavras as $w) if (mb_strlen($w) >= 4 && str_contains($perfil, $w)) return true;
    return false;
}
/** Perfil repetido: as outras fichas com ele são da mesma empresa (nome parecido)? Senão é de quem fez o site. */
function ad_perfil_da_empresa(string $campo, string $valor, int $id, string $nome): bool {
    if (ad_perfil_do_nome($campo === 'facebook' ? preg_replace('#^https://www\.facebook\.com/#', '', $valor) : $valor, $nome)) return true;
    $col = AD_COLUNA[$campo];
    $outros = array_column(linhas("SELECT DISTINCT p.nome FROM profissionais p WHERE p.id <> ? AND (p.$col = ? OR EXISTS (SELECT 1 FROM dados_site d
        WHERE d.profissional_id = p.id AND d.campo = ? AND d.valor = ?)) LIMIT 20", [$id, $valor, $campo, $valor]), 'nome');
    $perfil = $campo === 'facebook' ? preg_replace('#^https://www\.facebook\.com/#', '', $valor) : $valor;
    // nome parecido ("Farmácia Drogal" x "Farmácia Drogal") ou o perfil está no nome da outra ("Clínica Fisioterapia UNIFAE")
    foreach ($outros as $o) if (dup_nomes_parecidos($nome, (string)$o) || (mb_strlen($perfil) >= 4 && str_contains(preg_replace('/[^a-z0-9]/', '', chave_texto((string)$o)), preg_replace('/[^a-z0-9]/', '', chave_texto($perfil))))) return true;
    return false;
}
/** [valor para a ficha, null] para aprovar, ou [null, motivo] para recusar. */
function ad_decidir(array $d, array $p): array {
    $c = $d['campo']; $v = (string)$d['valor'];
    switch ($c) {
        case 'whatsapp': case 'telefone':
            $t = ds_fone($v);
            if (!$t) return [null, 'número inválido'];
            if (telefone_central($t)) return [null, 'número de central (0800)'];
            if (ad_repete($c, $t) >= 3) return [null, 'número da central da rede (em várias fichas)'];
            $ficha = so_digitos((string)($p['telefone'] ?: $p['whatsapp']));
            if ($ficha !== '' && !telefone_central($ficha) && substr($ficha, 0, 2) !== substr($t, 0, 2)) return [null, 'DDD diferente do da ficha'];
            return [$t, null];
        case 'instagram':
            $h = strtolower(ltrim($v, '@'));
            if (!preg_match('/^[a-z0-9._]{2,30}$/', $h) || in_array($h, DS_IG_NAO, true)) return [null, 'perfil inválido'];
            if (ad_repete($c, $h) >= 2 && !ad_perfil_da_empresa($c, $h, (int)$d['profissional_id'], (string)$p['nome'])) return [null, 'perfil repetido em outras fichas (de quem fez o site?)'];
            return [$h, null];
        case 'facebook':
            $f = ds_facebook($v);
            if (!$f) return [null, 'link inválido'];
            if (ad_repete($c, $f) >= 2 && !ad_perfil_da_empresa($c, $f, (int)$d['profissional_id'], (string)$p['nome'])) return [null, 'página repetida em outras fichas (de quem fez o site?)'];
            return [$f, null];
        case 'email':
            $e = ds_email($v);
            if (!$e) return [null, 'e-mail inválido'];
            if (ad_repete($c, $e) >= 3) return [null, 'e-mail da central da rede (em várias fichas)'];
            return [$e, null];
        case 'servicos':
            return mb_strlen(trim($v)) >= 3 ? [mb_substr(trim($v), 0, 500), null] : [null, 'vazio'];
        case 'endereco':
            return mb_strlen(trim($v)) >= 6 && !endereco_invalido($v) ? [mb_substr(trim($v), 0, 200), null] : [null, 'endereço inválido'];
    }
    return [null, 'campo desconhecido'];
}

$itens = linhas("SELECT d.*, p.nome, p.telefone AS p_telefone, p.whatsapp AS p_whatsapp, p.geo_origem, p.instagram AS p_instagram, p.facebook AS p_facebook,
    p.email_contato AS p_email_contato, p.servicos AS p_servicos, p.endereco AS p_endereco
    FROM dados_site d JOIN profissionais p ON p.id = d.profissional_id
    WHERE d.situacao = 'pendente' AND d.tipo = 'preencher' AND p.status = 'ativo' ORDER BY d.profissional_id, d.campo");
$aprovar = []; $recusar = []; $motivos = []; $por_campo = [];
foreach ($itens as $d) {
    $p = ['nome' => $d['nome'], 'telefone' => $d['p_telefone'], 'whatsapp' => $d['p_whatsapp']];
    [$v, $motivo] = ad_decidir($d, $p);
    // a ficha ganhou esse dado depois que o robô leu (dono ou admin): não mexe
    $atual = (string)$d['p_' . AD_COLUNA[$d['campo']]];
    if ($v !== null && trim($atual) !== '' && !($d['campo'] === 'endereco' && endereco_invalido($atual))) { $v = null; $motivo = 'a ficha já foi preenchida'; }
    if ($v !== null) { $aprovar[] = [$d, $v, $atual]; $por_campo[$d['campo']] = ($por_campo[$d['campo']] ?? 0) + 1; }
    else { $recusar[] = [$d, $motivo]; $motivos[$motivo] = ($motivos[$motivo] ?? 0) + 1; }
}
$fichas = count(array_unique(array_map(fn($x) => $x[0]['profissional_id'], $aprovar)));
$diferentes = (int)valor("SELECT COUNT(*) FROM dados_site d JOIN profissionais p ON p.id = d.profissional_id WHERE d.situacao = 'pendente' AND d.tipo = 'diferente' AND p.status = 'ativo'");
printf("APROVAR: %d dados em %d fichas (%s)\n", count($aprovar), $fichas,
    implode(', ', array_map(fn($k, $n) => DS_CAMPOS[$k] . " $n", array_keys($por_campo), $por_campo)));
printf("RECUSAR: %d\n", count($recusar));
arsort($motivos);
foreach ($motivos as $m => $n) printf("  %5d  %s\n", $n, $m);
printf("FICAM PENDENTES (a ficha já tem outro valor, \"diferente\"): %d\n", $diferentes);
if (!$aplicar) {
    echo "\nAmostra do que seria recusado:\n";
    foreach (array_slice(array_values(array_filter($recusar, fn($x) => str_contains((string)$x[1], getenv("AD_FILTRO") ?: ""))), 0, (int)(getenv("AD_N") ?: 15)) as [$d, $m]) printf("  #%d %s · %s %s -> %s\n", $d['profissional_id'], mb_substr($d['nome'], 0, 35), $d['campo'], mb_substr($d['valor'], 0, 50), $m);
    exit("\n(prévia: nada foi mudado; para aplicar: --aplicar)\n");
}

// cópia antes
$c = config();
$arq = '/var/lib/guia-novo/backups/antes-aprovar-dados-site-' . date('Ymd-His') . '.sql.gz';
@mkdir(dirname($arq), 0750, true);
$cnf = tempnam(sys_get_temp_dir(), 'ad');
file_put_contents($cnf, "[client]\nuser=\"{$c['db_usuario']}\"\npassword=\"{$c['db_senha']}\"\nhost=\"{$c['db_host']}\"\n");
chmod($cnf, 0600);
exec('mysqldump --defaults-extra-file=' . escapeshellarg($cnf) . ' --single-transaction --no-tablespaces ' . escapeshellarg($c['db_nome'])
    . ' profissionais dados_site | gzip > ' . escapeshellarg($arq), $o, $rc);
unlink($cnf);
if ($rc !== 0 || filesize($arq) < 1000) exit("Não consegui fazer a cópia antes ($arq). Nada foi mudado.\n");
echo "\nCópia antes: $arq\n";

$pdo = db();
$pdo->beginTransaction();
foreach ($aprovar as [$d, $v, $atual]) {
    $col = AD_COLUNA[$d['campo']];
    $extra = $d['campo'] === 'endereco' && $d['geo_origem'] !== 'google' ? ', lat = NULL, lng = NULL, geo_origem = NULL, geo_tentado = NULL' : '';
    q("UPDATE profissionais SET $col = ?$extra WHERE id = ?", [$v, $d['profissional_id']]);
    q("UPDATE dados_site SET valor = ?, valor_antes = ?, situacao = 'aprovado', decidido_em = NOW() WHERE id = ?", [$v, $atual !== '' ? $atual : null, $d['id']]);
}
foreach ($recusar as [$d, $m]) q("UPDATE dados_site SET situacao = 'recusado', decidido_em = NOW() WHERE id = ?", [$d['id']]);
$pdo->commit();
registrar(null, 'dados_site_lote', null, count($aprovar) . ' dados aprovados em ' . $fichas . ' fichas, ' . count($recusar) . ' recusados (aprovar-dados-site.php)');
web_cache_limpar();
printf("✔ %d dados aprovados em %d fichas · %d recusados · %d \"diferentes\" continuam pendentes\n", count($aprovar), $fichas, count($recusar), $diferentes);
