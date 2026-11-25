<?php
// Números (21/11, passo 32, pedido do usuário: "mescle a página números e de onde as entradas vêm, mais intuitiva").
// Uma página só, de cima para baixo: filtros em botões -> 4 números grandes com comparação -> caminho da pessoa
// (apareceu -> abriu -> chamou) -> gráfico dia a dia (ou hora a hora em "Hoje") -> de onde vêm -> cidades, áreas,
// comércios, tipos de contato e horários -> páginas por onde entram -> tabela de todas as fichas (com CSV).
// O antigo /admin/entradas.php redireciona para cá com a origem escolhida. Detalhe de um dia: entradas-dia.php.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
exigir_numeros_prontos();
require_once __DIR__ . '/../../inc/google-pesquisa.php';   // 25/11 (passo 87): aparições na pesquisa do Google

// ---------- filtros ----------
const PERIODOS = [1 => 'Hoje', 7 => '7 dias', 30 => '30 dias', 90 => '90 dias'];
$dias = isset(PERIODOS[(int)($_GET['dias'] ?? 7)]) ? (int)($_GET['dias'] ?? 7) : 7;
$cidade = isset(TODAS_CIDADES[$_GET['cidade'] ?? '']) ? (string)$_GET['cidade'] : '';
$origens_app = array_values(array_diff(array_keys(ORIGENS), ORIGENS_SITE));
$origem = (string)($_GET['origem'] ?? '');
if (!in_array($origem, ['', 'app', 'site'], true) && !isset(ORIGENS[$origem])) $origem = '';
$e_site = $origem === 'site' || in_array($origem, ORIGENS_SITE, true);
$e_app = $origem === 'app' || in_array($origem, $origens_app, true);

$hoje_d = hoje();
$desde = desde_dias($dias);
$ant_fim = date('Y-m-d', strtotime("$desde -1 day"));
$ant_ini = date('Y-m-d', strtotime("$desde -$dias days"));
$inicio_dados = (string)(valor('SELECT LEAST(COALESCE((SELECT MIN(dia) FROM contatos), CURDATE()), COALESCE((SELECT MIN(dia) FROM aparicoes), CURDATE()))') ?: $hoje_d);
$compara = $ant_fim >= $inicio_dados;   // há dados do período anterior?

/** Link desta página mudando só alguns filtros (os vazios saem do endereço). */
$link = fn(array $mudar, string $ancora = '') => '?' . http_build_query(array_filter(array_merge(
    ['dias' => $dias, 'cidade' => $cidade, 'origem' => $origem], array_intersect_key($_GET, array_flip(['categoria', 'tipo', 'plano', 'vistos', 'por', 'ordem'])), $mudar),
    fn($v) => $v !== '' && $v !== null && $v !== 0)) . $ancora;

// condições de cada tabela para os filtros de cidade e origem: [sql, params]
$sql_in = fn(array $l) => implode(',', array_fill(0, count($l), '?'));
/** Visualizações (contatos tipo perfil): a origem é a lista do app ou a origem do site. */
$w_vis = function (string $a = 'k') use ($origem, $origens_app, $sql_in): array {
    if ($origem === '') return ['', []];
    if ($origem === 'app') return [" AND $a.origem NOT IN (" . $sql_in(ORIGENS_SITE) . ')', ORIGENS_SITE];
    if ($origem === 'site') return [" AND $a.origem IN (" . $sql_in(ORIGENS_SITE) . ')', ORIGENS_SITE];
    return [" AND $a.origem = ?", [$origem]];
};
/** Toques: no app todos ficam como 'app' (não separam por lista); no site, pela origem. */
$w_toq = function (string $a = 'k') use ($origem, $e_app, $sql_in): array {
    if ($origem === '') return ['', []];
    if ($e_app) return [" AND $a.origem NOT IN (" . $sql_in(ORIGENS_SITE) . ')', ORIGENS_SITE];
    if ($origem === 'site') return [" AND $a.origem IN (" . $sql_in(ORIGENS_SITE) . ')', ORIGENS_SITE];
    return [" AND $a.origem = ?", [$origem]];
};
$w_cid = fn(string $col) => $cidade !== '' ? [" AND $col = ?", [$cidade]] : ['', []];
$tem_entradas = entradas_prontas() && !$e_app;
// 25/11: o site também conta aparições (ONDE_APARICAO_SITE), mas sem saber de onde a pessoa veio: com uma origem do
// site escolhida (Google, redes...) as aparições somem; "Site" mostra só as do site e "App" só as do app.
$tem_aparicoes = $origem === '' || $origem === 'app' || $origem === 'site' || isset(ONDE_APARICAO[$origem]);
$w_ent = fn() => in_array($origem, ORIGENS_SITE, true) ? [' AND origem = ?', [$origem]] : ['', []];
$w_apa = fn() => match (true) {
    isset(ONDE_APARICAO[$origem]) => [' AND a.onde = ?', [$origem]],
    $origem === 'app' => [' AND a.onde NOT IN (' . $sql_in(ONDE_APARICAO_SITE) . ')', ONDE_APARICAO_SITE],
    $origem === 'site' => [' AND a.onde IN (' . $sql_in(ONDE_APARICAO_SITE) . ')', ONDE_APARICAO_SITE],
    default => ['', []],
};

// ---------- números por dia (período atual e anterior) ----------
const ZERO = ['entradas' => 0, 'aparicoes' => 0, 'vis' => 0, 'pessoas' => 0, 'toques' => 0];
$por_dia = function (string $ini, string $fim) use ($w_vis, $w_toq, $w_cid, $w_ent, $w_apa, $tem_entradas, $tem_aparicoes): array {
    $r = [];
    for ($d = $ini; $d <= $fim; $d = date('Y-m-d', strtotime("$d +1 day"))) $r[$d] = ZERO;
    [$cs, $cp] = $w_cid('p.cidade');
    [$vs, $vp] = $w_vis();
    foreach (linhas("SELECT k.dia, SUM(k.total) AS v, SUM(k.pessoas) AS pe FROM contatos k JOIN profissionais p ON p.id = k.profissional_id
            WHERE k.tipo = 'perfil' AND k.dia BETWEEN ? AND ?$cs$vs GROUP BY k.dia", [$ini, $fim, ...$cp, ...$vp]) as $l) {
        $r[$l['dia']]['vis'] = (int)$l['v'];
        $r[$l['dia']]['pessoas'] = (int)$l['pe'];
    }
    [$ts, $tp] = $w_toq();
    foreach (linhas("SELECT k.dia, SUM(k.total) AS t FROM contatos k JOIN profissionais p ON p.id = k.profissional_id
            WHERE k.tipo NOT IN ('perfil','compartilhar') AND k.dia BETWEEN ? AND ?$cs$ts GROUP BY k.dia", [$ini, $fim, ...$cp, ...$tp]) as $l)
        $r[$l['dia']]['toques'] = (int)$l['t'];
    if ($tem_aparicoes) {
        [$as, $ap] = $w_apa();
        foreach (linhas("SELECT a.dia, SUM(a.total) AS t FROM aparicoes a JOIN profissionais p ON p.id = a.profissional_id
                WHERE a.dia BETWEEN ? AND ?$cs$as GROUP BY a.dia", [$ini, $fim, ...$cp, ...$ap]) as $l) $r[$l['dia']]['aparicoes'] = (int)$l['t'];
    }
    if ($tem_entradas) {
        [$es, $ep] = $w_ent();
        [$ecs, $ecp] = $w_cid('cidade');
        foreach (linhas("SELECT dia, SUM(total) AS t FROM entradas WHERE dia BETWEEN ? AND ?$es$ecs GROUP BY dia", [$ini, $fim, ...$ep, ...$ecp]) as $l)
            $r[$l['dia']]['entradas'] = (int)$l['t'];
        // o Chrome baixando a página antes do clique (pre = 1) não é gente (desde 21/11 isso nem é mais gravado)
        if (pre_carregamento_pronto()) foreach (linhas("SELECT DATE(quando) AS dia, COUNT(*) AS t FROM entradas_hora WHERE pre = 1 AND quando BETWEEN ? AND ?$es$ecs
                GROUP BY DATE(quando)", ["$ini 00:00:00", "$fim 23:59:59", ...$ep, ...$ecp]) as $l)
            if (isset($r[$l['dia']])) $r[$l['dia']]['entradas'] = max(0, $r[$l['dia']]['entradas'] - (int)$l['t']);
    }
    return $r;
};
$somar = function (array $dias_l): array { $s = ZERO; foreach ($dias_l as $d) foreach ($s as $k => $_) $s[$k] += $d[$k]; return $s; };
$serie = $por_dia($desde, $hoje_d);
$agora = $somar($serie);
$antes = $compara ? $somar($por_dia($ant_ini, $ant_fim)) : null;

// ---------- hora a hora (gráfico de "Hoje" e "Horários") ----------
$por_hora = function (string $ini, string $fim) use ($w_vis, $w_cid, $w_ent, $tem_entradas): array {
    $r = ['vis' => array_fill(0, 24, 0), 'entradas' => array_fill(0, 24, 0)];
    if (contatos_hora_prontos()) {
        [$cs, $cp] = $w_cid('p.cidade');
        [$vs, $vp] = $w_vis('h');
        foreach (linhas("SELECT HOUR(h.quando) AS hr, COUNT(*) AS t FROM contatos_hora h JOIN profissionais p ON p.id = h.profissional_id
                WHERE h.tipo = 'perfil' AND h.quando BETWEEN ? AND ?$cs$vs GROUP BY hr", ["$ini 00:00:00", "$fim 23:59:59", ...$cp, ...$vp]) as $l)
            $r['vis'][(int)$l['hr']] = (int)$l['t'];
    }
    if ($tem_entradas) {
        [$es, $ep] = $w_ent();
        [$ecs, $ecp] = $w_cid('cidade');
        try {
            foreach (linhas("SELECT HOUR(quando) AS hr, COUNT(*) AS t FROM entradas_hora WHERE quando BETWEEN ? AND ?$es$ecs"
                    . (pre_carregamento_pronto() ? ' AND pre <> 1' : '') . ' GROUP BY hr', ["$ini 00:00:00", "$fim 23:59:59", ...$ep, ...$ecp]) as $l)
                $r['entradas'][(int)$l['hr']] = (int)$l['t'];
        } catch (PDOException) {}
    }
    return $r;
};
$horas = $por_hora($desde, $hoje_d);
$horas_antes = $compara ? $por_hora($ant_ini, $ant_fim) : null;

// ---------- de onde vêm ----------
[$cs, $cp] = $w_cid('p.cidade');
[$vs, $vp] = $w_vis();
$vis_origem = array_map('intval', array_column(linhas("SELECT k.origem, SUM(k.total) AS t FROM contatos k JOIN profissionais p ON p.id = k.profissional_id
    WHERE k.tipo = 'perfil' AND k.dia >= ?$cs$vs GROUP BY k.origem HAVING t > 0 ORDER BY t DESC", [$desde, ...$cp, ...$vp]), 't', 'origem'));
$ent_origem = [];
if ($tem_entradas) {
    [$es, $ep] = $w_ent();
    [$ecs, $ecp] = $w_cid('cidade');
    $ent_origem = array_map('intval', array_column(linhas("SELECT origem, SUM(total) AS t FROM entradas WHERE dia >= ?$es$ecs GROUP BY origem HAVING t > 0 ORDER BY t DESC",
        [$desde, ...$ep, ...$ecp]), 't', 'origem'));
}

// ---------- caminho por canal (app e site separados; desde 25/11 o site também tem aparição) ----------
$vis_canal = ['app' => 0, 'site' => 0];
foreach ($vis_origem as $o => $n) $vis_canal[in_array($o, ORIGENS_SITE, true) ? 'site' : 'app'] += $n;
[$ts0, $tp0] = $w_toq();
$toq_canal = ['app' => 0, 'site' => 0];
foreach (linhas("SELECT k.origem, SUM(k.total) AS t FROM contatos k JOIN profissionais p ON p.id = k.profissional_id
        WHERE k.tipo NOT IN ('perfil','compartilhar') AND k.dia >= ?$cs$ts0 GROUP BY k.origem", [$desde, ...$cp, ...$tp0]) as $l)
    $toq_canal[in_array($l['origem'], ORIGENS_SITE, true) ? 'site' : 'app'] += (int)$l['t'];
// aparições separadas por canal (25/11: o site também conta)
$apa_canal = ['app' => 0, 'site' => 0];
if ($tem_aparicoes) {
    [$as0, $ap0] = $w_apa();
    foreach (linhas("SELECT a.onde, SUM(a.total) AS t FROM aparicoes a JOIN profissionais p ON p.id = a.profissional_id
            WHERE a.dia >= ?$cs$as0 GROUP BY a.onde", [$desde, ...$cp, ...$ap0]) as $l)
        $apa_canal[in_array($l['onde'], ONDE_APARICAO_SITE, true) ? 'site' : 'app'] += (int)$l['t'];
}
$caminhos = [];
if ($tem_aparicoes && !$e_site) $caminhos['app'] = ['📱 No app', $apa_canal['app'], 'apareceram em listas', $vis_canal['app'], $toq_canal['app']];
if ($tem_aparicoes && !$e_app && $apa_canal['site'] > 0)
    $caminhos['site'] = ['🌐 No site', $apa_canal['site'], 'apareceram em listas do site', $vis_canal['site'], $toq_canal['site']];
elseif ($tem_entradas) $caminhos['site'] = ['🌐 No site', $agora['entradas'], 'chegaram ao site', $vis_canal['site'], $toq_canal['site']];

// ---------- pesquisa do Google (Search Console, passo 87) ----------
// Não sabe se a pessoa usa app ou site: some só com o filtro do app. Os dados chegam com 2 a 3 dias de atraso.
$tem_google = gp_pronto() && !$e_app;
$g = ['imp' => 0, 'cli' => 0, 'ate' => null, 'antes' => null, 'consultas' => [], 'fichas' => []];
if ($tem_google) {
    [$gcs, $gcp] = $w_cid('cidade');
    $gl = linha("SELECT SUM(impressoes) AS imp, SUM(cliques) AS cli, MAX(dia) AS ate FROM google_paginas WHERE dia >= ?$gcs", [$desde, ...$gcp]);
    $g['imp'] = (int)$gl['imp']; $g['cli'] = (int)$gl['cli']; $g['ate'] = $gl['ate'];
    if ($compara) $g['antes'] = (int)valor("SELECT SUM(impressoes) FROM google_paginas WHERE dia BETWEEN ? AND ?$gcs", [$ant_ini, $ant_fim, ...$gcp]);
    if ($cidade === '') $g['consultas'] = linhas('SELECT consulta, SUM(impressoes) AS imp, SUM(cliques) AS cli,
        ROUND(SUM(posicao * impressoes) / NULLIF(SUM(impressoes), 0), 1) AS pos FROM google_consultas WHERE dia >= ?
        GROUP BY consulta ORDER BY imp DESC, cli DESC LIMIT 20', [$desde]);
    [$gcs2, $gcp2] = $w_cid('g.cidade');
    $g['fichas'] = linhas("SELECT g.profissional_id AS id, p.nome, p.cidade, SUM(g.impressoes) AS imp, SUM(g.cliques) AS cli,
        ROUND(SUM(g.posicao * g.impressoes) / NULLIF(SUM(g.impressoes), 0), 1) AS pos
        FROM google_paginas g JOIN profissionais p ON p.id = g.profissional_id WHERE g.dia >= ?$gcs2
        GROUP BY g.profissional_id ORDER BY imp DESC, cli DESC LIMIT 20", [$desde, ...$gcp2]);
    $g['outras'] = linhas("SELECT g.pagina, SUM(g.impressoes) AS imp, SUM(g.cliques) AS cli FROM google_paginas g
        WHERE g.profissional_id IS NULL AND g.dia >= ?$gcs2 GROUP BY g.pagina ORDER BY imp DESC LIMIT 10", [$desde, ...$gcp2]);
}

// ---------- cidades, áreas, comércios e tipos de toque ----------
[$ts, $tp] = $w_toq();
$base_k = "FROM contatos k JOIN profissionais p ON p.id = k.profissional_id JOIN categorias c ON c.id = p.categoria_id WHERE k.dia >= ?$cs";
$vis_sql = "SUM(IF(k.tipo = 'perfil'$vs, k.total, 0))";
$toq_sql = "SUM(IF(k.tipo NOT IN ('perfil','compartilhar')$ts, k.total, 0))";
$par = [...$vp, ...$tp, $desde, ...$cp];
$por_cidade = linhas("SELECT p.cidade AS chave, $vis_sql AS vis, $toq_sql AS toq $base_k GROUP BY p.cidade HAVING vis + toq > 0 ORDER BY vis DESC, toq DESC", $par);
$por_area = linhas("SELECT c.nome AS chave, $vis_sql AS vis, $toq_sql AS toq $base_k GROUP BY c.id HAVING vis + toq > 0 ORDER BY vis DESC, toq DESC LIMIT 12", $par);
$top_comercios = linhas("SELECT p.id, p.nome, p.tipo, p.cidade, c.nome AS categoria, $vis_sql AS vis, $toq_sql AS toq $base_k
    GROUP BY p.id HAVING vis + toq > 0 ORDER BY vis DESC, toq DESC, p.nome LIMIT 10", $par);
$ent_cidade = [];
if ($tem_entradas) {
    [$es, $ep] = $w_ent();
    [$ecs, $ecp] = $w_cid('cidade');
    $ent_cidade = array_map('intval', array_column(linhas("SELECT COALESCE(cidade, '') AS c, SUM(total) AS t FROM entradas WHERE dia >= ?$es$ecs GROUP BY c",
        [$desde, ...$ep, ...$ecp]), 't', 'c'));
}
$cid_lista = [];
foreach ($por_cidade as $l) $cid_lista[$l['chave']] = ['vis' => (int)$l['vis'], 'toq' => (int)$l['toq'], 'ent' => $ent_cidade[$l['chave']] ?? 0];
foreach ($ent_cidade as $c => $t) if ($c !== '') $cid_lista[$c] ??= ['vis' => 0, 'toq' => 0, 'ent' => $t];
uasort($cid_lista, fn($a, $b) => ($b['vis'] + $b['ent']) <=> ($a['vis'] + $a['ent']));
$tipos_toque = array_map('intval', array_column(linhas("SELECT k.tipo, SUM(k.total) AS t FROM contatos k JOIN profissionais p ON p.id = k.profissional_id
    WHERE k.tipo <> 'perfil' AND k.dia >= ?$cs$ts GROUP BY k.tipo", [$desde, ...$cp, ...$tp]), 't', 'tipo'));

// ---------- páginas por onde entram ----------
$paginas = [];
if ($tem_entradas) {
    [$es, $ep] = $w_ent();
    [$ecs, $ecp] = $w_cid('cidade');
    $paginas = linhas("SELECT caminho, pagina, cidade, SUM(total) AS total, SUM(IF(origem = 'google', total, 0)) AS google, MAX(dia) AS ultimo
        FROM entradas WHERE dia >= ?$es$ecs GROUP BY caminho, pagina, cidade ORDER BY total DESC, ultimo DESC LIMIT 30", [$desde, ...$ep, ...$ecp]);
}

// ---------- tabela de fichas (com área, tipo, plano, CSV e páginas) ----------
$cat = (int)($_GET['categoria'] ?? 0);
$tipo = trim(mb_substr((string)($_GET['tipo'] ?? ''), 0, 80));
$plano = in_array($_GET['plano'] ?? '', ['sem', 'basico', 'destaque'], true) ? (string)$_GET['plano'] : '';
$so_vistos = !empty($_GET['vistos']);
const COLUNAS = [
    'aparicoes' => 'Aparições', 'visualizacoes' => 'Aberturas', 'pessoas' => 'Pessoas', 'whatsapp' => 'WhatsApp',
    'ligar' => 'Ligar', 'mapa' => 'Mapa', 'site_t' => 'Site', 'instagram' => 'Instagram', 'contatos' => 'Contatos',
];
$ordem = isset(COLUNAS[$_GET['ordem'] ?? '']) ? (string)$_GET['ordem'] : 'visualizacoes';
$plano_sql = "CASE WHEN " . SQL_DESTAQUE . " THEN 'destaque' WHEN " . SQL_EM_DIA . " THEN 'basico' ELSE 'sem' END";
$vis_k = str_replace(' AND k.', ' AND ', $vs);   // dentro da subconsulta a tabela não tem apelido
$toq_k = str_replace(' AND k.', ' AND ', $ts);
$where = ["p.status = 'ativo'"];
$params = [...$vp, ...$vp, ...$tp, ...$tp, ...$tp, ...$tp, ...$tp, ...$tp, $desde];
$apa_sql = '';
if ($tem_aparicoes) {
    [$as, $ap] = $w_apa();
    $apa_sql = "LEFT JOIN (SELECT profissional_id, SUM(total) AS aparicoes FROM aparicoes a WHERE dia >= ?$as GROUP BY profissional_id) a ON a.profissional_id = p.id";
    array_push($params, $desde, ...$ap);
}
if ($cidade !== '') { $where[] = 'p.cidade = ?'; $params[] = $cidade; }
if ($cat) { $where[] = 'p.categoria_id = ?'; $params[] = $cat; }
if ($tipo !== '') { $where[] = 'p.tipo = ?'; $params[] = $tipo; }
if ($plano !== '') { $where[] = "$plano_sql = ?"; $params[] = $plano; }
if ($so_vistos) $where[] = 'COALESCE(k.visualizacoes, 0) > 0';
// só fichas com algum número no período (16/11): as zeradas não aparecem; entram quando tiverem movimento
$where[] = '(' . ($tem_aparicoes ? 'COALESCE(a.aparicoes, 0) + ' : '') . 'COALESCE(k.visualizacoes, 0) + COALESCE(k.contatos, 0)) > 0';
$t = fn(string $tp_) => "SUM(IF(tipo = '$tp_'$toq_k, total, 0))";
$lista = linhas("SELECT p.id, p.nome, p.tipo, p.cidade, p.bairro, p.whatsapp AS fone_whatsapp, p.telefone, p.site AS tem_site,
        c.nome AS categoria, $plano_sql AS plano_atual, " . ($tem_aparicoes ? 'COALESCE(a.aparicoes, 0)' : '0') . " AS aparicoes,
        COALESCE(k.visualizacoes, 0) AS visualizacoes, COALESCE(k.pessoas, 0) AS pessoas, COALESCE(k.whatsapp, 0) AS whatsapp,
        COALESCE(k.ligar, 0) AS ligar, COALESCE(k.mapa, 0) AS mapa, COALESCE(k.site_t, 0) AS site_t, COALESCE(k.instagram, 0) AS instagram,
        COALESCE(k.contatos, 0) AS contatos
    FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN (SELECT profissional_id, SUM(IF(tipo = 'perfil'$vis_k, total, 0)) AS visualizacoes, SUM(IF(tipo = 'perfil'$vis_k, pessoas, 0)) AS pessoas,
            {$t('whatsapp')} AS whatsapp, {$t('ligar')} AS ligar, {$t('mapa')} AS mapa, {$t('site')} AS site_t, {$t('instagram')} AS instagram,
            SUM(IF(tipo NOT IN ('perfil','compartilhar')$toq_k, total, 0)) AS contatos
        FROM contatos WHERE dia >= ? GROUP BY profissional_id) k ON k.profissional_id = p.id
    $apa_sql
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $ordem DESC, visualizacoes DESC, aparicoes DESC, p.nome", $params);
$nome_plano = ['sem' => 'Sem plano', 'basico' => 'Básico', 'destaque' => 'Destaque'];

if (!empty($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="localiza-facil-numeros-' . ($cidade ?: 'todas') . '-' . $dias . 'd-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM: o Excel abre com acentos
    fputcsv($out, ['ID', 'Comércio', 'Tipo', 'Área', 'Cidade', 'Bairro', 'Plano', 'WhatsApp do comércio', 'Telefone', 'Tem site', ...array_values(COLUNAS), 'Taxa de contato', 'Ficha no admin'], ';');
    foreach ($lista as $l) {
        fputcsv($out, [$l['id'], $l['nome'], $l['tipo'], $l['categoria'], nome_cidade($l['cidade']), $l['bairro'], $nome_plano[$l['plano_atual']],
            telefone_formatado($l['fone_whatsapp']), telefone_formatado($l['telefone']), $l['tem_site'] ? 'sim' : 'não',
            ...array_map(fn($k) => (int)$l[$k], array_keys(COLUNAS)), pct((int)$l['contatos'], (int)$l['visualizacoes']),
            url_publica('admin/numeros-ficha.php?id=' . $l['id'])], ';');
    }
    exit;
}
$categorias = linhas('SELECT id, nome FROM categorias WHERE ativa = 1 ORDER BY nome');
$tpw = ["p.status = 'ativo'"]; $tpp = [];
if ($cidade !== '') { $tpw[] = 'p.cidade = ?'; $tpp[] = $cidade; }
if ($cat) { $tpw[] = 'p.categoria_id = ?'; $tpp[] = $cat; }
$tipos = linhas('SELECT p.tipo, COUNT(*) AS n FROM profissionais p WHERE p.tipo IS NOT NULL AND ' . implode(' AND ', $tpw) . ' GROUP BY p.tipo ORDER BY n DESC, p.tipo LIMIT 150', $tpp);
const POR_PAGINA = [15, 50, 100, 500];
$por = in_array((int)($_GET['por'] ?? 15), POR_PAGINA, true) ? (int)($_GET['por'] ?? 15) : 15;
$total_fichas = count($lista);
$n_paginas = max(1, (int)ceil($total_fichas / $por));
$pag = min($n_paginas, max(1, (int)($_GET['pag'] ?? 1)));
$paginacao = function () use ($pag, $n_paginas, $total_fichas, $por, $link): string {
    if ($n_paginas < 2) return '';
    $ir = fn(int $n) => h($link(['pag' => $n > 1 ? $n : null], '#fichas'));
    $html = '<nav class="paginacao" aria-label="Páginas">' . ($pag > 1 ? '<a class="botao pequeno contorno" href="' . $ir($pag - 1) . '">‹ Anterior</a>' : '');
    $ultima = 0;
    for ($n = 1; $n <= $n_paginas; $n++) {
        if ($n !== 1 && $n !== $n_paginas && abs($n - $pag) > 2) continue;
        if ($ultima && $n - $ultima > 1) $html .= '<span>…</span>';
        $html .= $n === $pag ? '<strong>' . $n . '</strong>' : '<a href="' . $ir($n) . '">' . $n . '</a>';
        $ultima = $n;
    }
    return $html . ($pag < $n_paginas ? '<a class="botao pequeno contorno" href="' . $ir($pag + 1) . '">Próxima ›</a>' : '')
        . '<span class="discreto">' . num(($pag - 1) * $por + 1) . '–' . num(min($total_fichas, $pag * $por)) . ' de ' . num($total_fichas) . '</span></nav>';
};

// ---------- pedaços de tela ----------
$nome_antes = $dias === 1 ? 'ontem' : "nos $dias dias antes";
/** Diferença para o período anterior: seta, número e o valor de antes. */
$dif = function (string $k) use ($agora, $antes, $nome_antes, $dias): string {
    if ($antes === null) return '<span class="np-dif sem">sem dados de antes para comparar</span>';
    $d = $agora[$k] - $antes[$k];
    $base = $antes[$k];
    $pc = $base > 0 ? ' (' . ($d >= 0 ? '+' : '−') . number_format(abs(100 * $d / $base), 0, ',', '.') . '%)' : '';
    if ($d === 0) return '<span class="np-dif igual">= igual a ' . h($nome_antes) . '</span>';
    return '<span class="np-dif ' . ($d > 0 ? 'sobe' : 'desce') . '">' . ($d > 0 ? '▲ +' : '▼ −') . num(abs($d)) . $pc
        . '</span><span class="np-antes">' . ($dias === 1 ? 'ontem' : 'antes') . ': ' . num($base) . ($dias === 1 ? ' (dia inteiro)' : '') . '</span>';
};
/** Linha de tendência pequena (SVG) com os valores do período, um por dia. */
$tendencia = function (string $k) use ($serie, $dias): string {
    if ($dias === 1) return '';
    $v = array_column($serie, $k);
    $max = max(1, ...array_values($v));
    $n = count($v);
    $pts = [];
    foreach ($v as $i => $x) $pts[] = round($n > 1 ? 100 * $i / ($n - 1) : 50, 1) . ',' . round(30 - 26 * $x / $max, 1);
    return '<svg class="np-tend" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true"><polyline points="' . implode(' ', $pts) . '"/></svg>';
};
/** Barras deitadas: [rótulo html, valor, link opcional]. */
$barras = function (array $linhas, string $classe = '', ?int $max = null): string {
    if (!$linhas) return '<p class="discreto">Nada no período.</p>';
    $max ??= max(1, ...array_values(array_map(fn($l) => (int)$l[1], $linhas)));
    $total = max(1, array_sum(array_map(fn($l) => (int)$l[1], $linhas)));
    $h = '<div class="np-barras ' . $classe . '">';
    foreach ($linhas as $l) {
        $rot = isset($l[2]) && $l[2] !== null ? '<a href="' . h($l[2]) . '">' . $l[0] . '</a>' : $l[0];
        $h .= '<div class="np-linha"><span class="np-rot">' . $rot . '</span><span class="np-trilho"><span style="width:' . max(1, round(100 * $l[1] / $max)) . '%"></span></span>'
            . '<span class="np-val">' . num((int)$l[1]) . ' <small>' . number_format(100 * $l[1] / $total, 0, ',', '.') . '%</small></span></div>';
    }
    return $h . '</div>';
};
$rotulo_origem = function (string $o): string { $t = preg_replace('/^Site: /', '', ORIGENS[$o] ?? $o); return h(mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1)); };
$nome_cidade_l = fn(string $c) => $c === '' ? 'Página inicial / sem cidade' : h(nome_cidade($c));
$dia_semana = fn(string $d) => ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'][(int)date('w', strtotime($d))];
$filtro_desc = PERIODOS[$dias] . ($cidade !== '' ? ' · ' . nome_cidade($cidade) : '') . ($origem !== '' ? ' · ' . ($origem === 'app' ? 'só o app' : ($origem === 'site' ? 'só o site' : (ORIGENS[$origem] ?? $origem))) : '');

admin_inicio('Números', $admin);
?>
<style>
  .np-topo { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 8px 16px; margin-bottom: 4px; }
  .np-topo h1 { margin: 0; }
  .np-filtros { background: var(--branco); border-radius: 18px; padding: 14px 16px; margin: 14px 0 18px; display: grid; gap: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
  .np-grupo { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
  .np-grupo > b { font-size: .85rem; color: var(--texto2); min-width: 64px; }
  .np-chip { display: inline-block; padding: 6px 14px; border-radius: 999px; border: 2px solid var(--borda); background: var(--creme); font-weight: 800; font-size: .88rem; text-decoration: none; color: var(--texto); line-height: 1.3; }
  .np-chip:hover { border-color: var(--verde2); }
  .np-chip.ativo { background: var(--verde); border-color: var(--verde); color: #fff; }
  .np-filtros select { font: inherit; font-size: .9rem; font-weight: 700; padding: 6px 10px; border-radius: 12px; border: 2px solid var(--borda); background: var(--creme); max-width: 100%; }
  .np-cartoes { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin: 0 0 22px; }
  .np-cartao { background: var(--branco); border-radius: 18px; padding: 16px 18px 12px; box-shadow: 0 2px 10px rgba(0,0,0,.05); display: flex; flex-direction: column; gap: 2px; min-width: 0; }
  .np-cartao .np-tit { font-weight: 800; color: var(--texto2); font-size: .9rem; }
  .np-cartao .np-num { font-size: 2.3rem; font-weight: 900; color: var(--verde); line-height: 1.1; }
  .np-cartao .np-num small { font-size: 1rem; color: var(--texto2); font-weight: 800; }
  .np-cartao .np-exp { font-size: .8rem; color: var(--texto2); line-height: 1.35; }
  .np-dif { font-size: .85rem; font-weight: 800; } .np-dif.sobe { color: #12803c; } .np-dif.desce { color: #c0392b; }
  .np-dif.igual, .np-dif.sem { color: var(--texto2); font-weight: 600; }
  .np-antes { font-size: .78rem; color: var(--texto2); }
  .np-tend { width: 100%; height: 32px; margin-top: 6px; } .np-tend polyline { fill: none; stroke: var(--verde2); stroke-width: 2; vector-effect: non-scaling-stroke; stroke-linejoin: round; }
  .np-caixa { background: var(--branco); border-radius: 18px; padding: 16px 18px; box-shadow: 0 2px 10px rgba(0,0,0,.05); margin: 0 0 18px; min-width: 0; }
  .np-caixa h2, .np-caixa h3 { margin: 0 0 4px; font-size: 1.15rem; }
  .np-caixa > .discreto:first-of-type { margin-top: 0; }
  .np-duas { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 0 18px; }
  .np-funil { display: grid; grid-template-columns: 1fr auto 1fr auto 1fr; align-items: center; gap: 8px; text-align: center; margin-top: 10px; }
  .np-funil .etapa { background: var(--verde-claro); border-radius: 16px; padding: 12px 8px; }
  .np-funil .etapa strong { display: block; font-size: 1.7rem; font-weight: 900; color: var(--verde); line-height: 1.1; }
  .np-funil .etapa span { font-size: .85rem; color: var(--texto2); font-weight: 700; }
  .np-funil .seta { font-weight: 900; color: var(--verde2); font-size: .85rem; line-height: 1.2; }
  .np-funil .seta b { display: block; font-size: 1.15rem; }
  .np-grafico { display: flex; align-items: flex-end; gap: clamp(2px, .6vw, 8px); height: 190px; padding: 22px 0 0; border-bottom: 2px solid var(--borda); }
  .np-col { flex: 1; height: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 2px; position: relative; min-width: 0; text-decoration: none; }
  .np-col span { flex: 1; max-width: 26px; border-radius: 5px 5px 0 0; min-height: 2px; }
  .np-col .a { background: var(--verde); } .np-col .b { background: var(--amarelo); } .np-col .c { background: #c9d6ce; }
  .np-col em { position: absolute; top: -20px; left: 0; right: 0; font-style: normal; font-size: .72rem; font-weight: 800; color: var(--texto2); text-align: center; }
  .np-col.hoje { background: var(--amarelo-claro); border-radius: 6px 6px 0 0; }
  .np-col:hover { background: var(--verde-claro); border-radius: 6px 6px 0 0; }
  .np-eixo { display: flex; gap: clamp(2px, .6vw, 8px); font-size: .7rem; color: var(--texto2); margin-top: 4px; }
  .np-eixo span { flex: 1; text-align: center; min-width: 0; overflow: hidden; white-space: nowrap; }
  .np-eixo.horas i { font-style: normal; }
  .np-eixo.horas span { overflow: visible; }
  @media (max-width: 640px) { .np-eixo.horas { font-size: .55rem; letter-spacing: -.04em; } .np-eixo.horas i { display: none; } }
  .np-legenda { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: .85rem; color: var(--texto2); margin: 4px 0 0; }
  .np-legenda i { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 5px; vertical-align: -1px; }
  .np-barras { display: grid; gap: 8px; margin-top: 10px; }
  .np-linha { display: grid; grid-template-columns: minmax(90px, 38%) 1fr auto; align-items: center; gap: 10px; font-size: .9rem; }
  .np-rot { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 700; }
  .np-rot a { text-decoration: none; border-bottom: 1px dashed var(--verde2); }
  .np-trilho { height: 12px; background: var(--creme); border-radius: 999px; overflow: hidden; }
  .np-trilho span { display: block; height: 100%; background: var(--verde2); border-radius: 999px; }
  .np-barras.amarela .np-trilho span { background: var(--amarelo); }
  .np-val { font-weight: 800; white-space: nowrap; font-size: .88rem; } .np-val small { color: var(--texto2); font-weight: 600; }
  .np-sub { font-size: .8rem; font-weight: 900; color: var(--texto2); text-transform: uppercase; letter-spacing: .04em; margin: 14px 0 0; }
  .np-ranking td, .np-ranking th { font-size: .9rem; }
  .np-ranking .barra-mini { display: inline-block; height: 8px; background: var(--verde2); border-radius: 4px; vertical-align: middle; margin-right: 6px; }
  details.np-mais > summary { cursor: pointer; font-weight: 800; color: var(--verde); margin: 10px 0 0; }
  .np-ajuda { font-size: .88rem; color: var(--texto2); }
  .np-ajuda dt { font-weight: 800; color: var(--texto); margin-top: 6px; } .np-ajuda dd { margin: 0; }
  @media (max-width: 640px) {
    .np-funil { grid-template-columns: 1fr; } .np-funil .seta b { display: inline; }
    .np-grupo > b { min-width: 100%; }
    .np-linha { grid-template-columns: minmax(80px, 34%) 1fr auto; gap: 6px; }
    .np-duas { grid-template-columns: 1fr; }
  }
</style>

<div class="np-topo">
  <h1>Números</h1>
  <span class="discreto">Mostrando: <strong><?= h($filtro_desc) ?></strong> · robôs e equipe não contam</span>
</div>

<form method="get" class="np-filtros" id="filtros">
  <div class="np-grupo"><b>Período</b>
    <?php foreach (PERIODOS as $d => $n): ?><a class="np-chip<?= $d === $dias ? ' ativo' : '' ?>" href="<?= h($link(['dias' => $d, 'pag' => null])) ?>"><?= $n ?></a><?php endforeach ?>
  </div>
  <div class="np-grupo"><b>Cidade</b>
    <a class="np-chip<?= $cidade === '' ? ' ativo' : '' ?>" href="<?= h($link(['cidade' => null, 'pag' => null])) ?>">Todas</a>
    <?php foreach (CIDADES as $slug => $c): ?><a class="np-chip<?= $slug === $cidade ? ' ativo' : '' ?>" href="<?= h($link(['cidade' => $slug, 'pag' => null])) ?>"><?= h($c[1] ?? $c[0]) ?></a><?php endforeach ?>
  </div>
  <div class="np-grupo"><b>Origem</b>
    <a class="np-chip<?= $origem === '' ? ' ativo' : '' ?>" href="<?= h($link(['origem' => null, 'pag' => null])) ?>">Tudo</a>
    <a class="np-chip<?= $origem === 'app' ? ' ativo' : '' ?>" href="<?= h($link(['origem' => 'app', 'pag' => null])) ?>">📱 App</a>
    <a class="np-chip<?= $origem === 'site' ? ' ativo' : '' ?>" href="<?= h($link(['origem' => 'site', 'pag' => null])) ?>">🌐 Site</a>
    <a class="np-chip<?= $origem === 'google' ? ' ativo' : '' ?>" href="<?= h($link(['origem' => 'google', 'pag' => null])) ?>">Google</a>
    <select name="origem" aria-label="Outra origem" onchange="this.form.submit()">
      <option value="">Outra origem…</option>
      <optgroup label="Site"><?php foreach (ORIGENS_SITE as $o): if ($o === 'web') continue; ?><option value="<?= $o ?>"<?= $o === $origem ? ' selected' : '' ?>><?= $rotulo_origem($o) ?></option><?php endforeach ?></optgroup>
      <optgroup label="App (onde a pessoa tocou)"><?php foreach ($origens_app as $o): if ($o === 'app') continue; ?><option value="<?= $o ?>"<?= $o === $origem ? ' selected' : '' ?>><?= $rotulo_origem($o) ?></option><?php endforeach ?></optgroup>
    </select>
    <input type="hidden" name="dias" value="<?= $dias ?>"><?php if ($cidade !== ''): ?><input type="hidden" name="cidade" value="<?= h($cidade) ?>"><?php endif ?>
  </div>
</form>

<!-- 1. os números principais -->
<div class="np-cartoes">
  <?php if ($tem_entradas): ?>
  <div class="np-cartao"><span class="np-tit">🌐 Chegaram ao site</span><span class="np-num"><?= num($agora['entradas']) ?></span>
    <span class="np-exp">pessoas que entraram no site vindas de fora (Google, redes, link, digitando o endereço)</span><?= $dif('entradas') ?><?= $tendencia('entradas') ?></div>
  <?php endif ?>
  <?php if ($tem_google): ?>
  <div class="np-cartao"><span class="np-tit">🔎 Apareceram no Google</span>
    <?php if (!gp_ligado() && !$g['ate']): ?><span class="np-num">—</span>
      <span class="np-exp">ainda não ligado: falta a chave do Search Console (docs/search-console-api.md)</span>
    <?php else: ?><span class="np-num"><?= num($g['imp']) ?> <small><?= num($g['cli']) ?> clique<?= $g['cli'] === 1 ? '' : 's' ?></small></span>
      <span class="np-exp">vezes que uma página do site apareceu numa pesquisa do Google (Search Console<?= $g['ate'] ? ', dados até ' . h(data_br($g['ate'])) : '' ?>; o Google atrasa 2 a 3 dias)</span>
      <?php if ($g['antes'] !== null && $g['antes'] > 0): $dg = $g['imp'] - $g['antes']; ?><span class="np-exp"><?= $dg >= 0 ? '▲' : '▼' ?> <?= num(abs($dg)) ?> em relação ao período anterior (<?= num($g['antes']) ?>)</span><?php endif ?>
    <?php endif ?></div>
  <?php endif ?>
  <?php if ($tem_aparicoes): ?>
  <div class="np-cartao"><span class="np-tit">👀 Apareceram em listas</span><span class="np-num"><?= num($agora['aparicoes']) ?></span>
    <span class="np-exp">vezes que uma ficha foi mostrada numa lista ou busca<?php if (!$e_site && !$e_app): ?> (📱 app <?= num($apa_canal['app']) ?> · 🌐 site <?= num($apa_canal['site']) ?>)<?php endif ?></span><?= $dif('aparicoes') ?><?= $tendencia('aparicoes') ?></div>
  <?php endif ?>
  <div class="np-cartao"><span class="np-tit">📄 Fichas abertas</span><span class="np-num"><?= num($agora['vis']) ?></span>
    <span class="np-exp">vezes que alguém abriu a ficha de um comércio, por <?= num($agora['pessoas']) ?> pessoa<?= $agora['pessoas'] === 1 ? '' : 's' ?> (somadas por dia)</span><?= $dif('vis') ?><?= $tendencia('vis') ?></div>
  <div class="np-cartao"><span class="np-tit">📞 Contatos</span><span class="np-num"><?= num($agora['toques']) ?> <small><?= pct($agora['toques'], $agora['vis']) ?> das aberturas</small></span>
    <span class="np-exp">toques em WhatsApp, ligar, mapa, site ou Instagram: o cliente indo até o comércio</span><?= $dif('toques') ?><?= $tendencia('toques') ?></div>
</div>

<!-- 2. caminho da pessoa, separado por canal -->
<?php if ($caminhos): ?>
<section class="np-caixa">
  <h2>O caminho até o comércio</h2>
  <p class="discreto">Quantos viram, quantos abriram a ficha e quantos chamaram o comércio. A porcentagem na seta é quantos passaram para a etapa seguinte.</p>
  <?php foreach ($caminhos as [$nome, $topo, $topo_rot, $vis_c, $toq_c]): ?>
  <?php if (count($caminhos) > 1): ?><p class="np-sub"><?= $nome ?></p><?php endif ?>
  <div class="np-funil">
    <div class="etapa"><strong><?= num($topo) ?></strong><span><?= $topo_rot ?></span></div>
    <div class="seta"><b><?= $topo >= $vis_c ? pct($vis_c, $topo) : '—' ?></b>abriram →</div>
    <div class="etapa"><strong><?= num($vis_c) ?></strong><span>fichas abertas</span></div>
    <div class="seta"><b><?= pct($toq_c, $vis_c) ?></b>chamaram →</div>
    <div class="etapa"><strong><?= num($toq_c) ?></strong><span>contatos</span></div>
  </div>
  <?php endforeach ?>
  <?php if (isset($caminhos['site']) && $caminhos['site'][2] === 'chegaram ao site' && $caminhos['site'][3] > $caminhos['site'][1]): ?><p class="discreto">No site há mais fichas abertas do que chegadas: a mesma pessoa abre várias fichas numa visita (e até 20/11 robôs também abriam).</p><?php endif ?>
</section>
<?php endif ?>

<!-- 2b. pesquisa do Google (passo 87) -->
<?php if ($tem_google && ($g['consultas'] || $g['fichas'])): ?>
<div class="np-duas">
  <?php if ($g['consultas']): ?>
  <section class="np-caixa">
    <h2>🔎 O que pesquisaram no Google</h2>
    <p class="discreto">Pesquisas em que o site apareceu. O Google esconde as pesquisas raras, então a soma fica menor que o total.</p>
    <div class="tabela" style="margin-bottom:0"><table class="np-ranking">
      <tr><th>Pesquisa</th><th>Apareceu</th><th>Cliques</th><th>Posição</th></tr>
      <?php foreach ($g['consultas'] as $l): ?><tr><td><?= h($l['consulta']) ?></td><td><?= num((int)$l['imp']) ?></td><td><?= num((int)$l['cli']) ?></td><td><?= h((string)$l['pos']) ?>º</td></tr><?php endforeach ?>
    </table></div>
  </section>
  <?php endif ?>
  <section class="np-caixa">
    <h2>🔎 Fichas que mais apareceram no Google</h2>
    <p class="discreto">Posição = lugar médio na página de resultados (1º é o primeiro).</p>
    <?php if (!$g['fichas']): ?><p class="discreto">Nenhuma ficha apareceu no período.</p><?php else: ?>
    <div class="tabela" style="margin-bottom:0"><table class="np-ranking">
      <tr><th>Comércio</th><th>Apareceu</th><th>Cliques</th><th>Posição</th></tr>
      <?php foreach ($g['fichas'] as $l): ?><tr><td><a href="numeros-ficha.php?id=<?= (int)$l['id'] ?>&amp;dias=<?= max(7, $dias) ?>"><?= h($l['nome']) ?></a> <small class="discreto"><?= h(CIDADES[$l['cidade']][0] ?? $l['cidade']) ?></small></td>
        <td><?= num((int)$l['imp']) ?></td><td><?= num((int)$l['cli']) ?></td><td><?= h((string)$l['pos']) ?>º</td></tr><?php endforeach ?>
    </table></div>
    <?php endif ?>
    <?php if (!empty($g['outras'])): ?>
    <p class="np-sub">Outras páginas (cidade, área, início)</p>
    <div class="tabela" style="margin-bottom:0"><table class="np-ranking">
      <?php foreach ($g['outras'] as $l): ?><tr><td><?= h($l['pagina']) ?></td><td><?= num((int)$l['imp']) ?></td><td><?= num((int)$l['cli']) ?> clique<?= (int)$l['cli'] === 1 ? '' : 's' ?></td></tr><?php endforeach ?>
    </table></div>
    <?php endif ?>
  </section>
</div>
<?php endif ?>

<!-- 3. gráfico no tempo -->
<section class="np-caixa">
<?php if ($dias === 1):
  $serie_h = [];
  foreach (range(0, 23) as $h) $serie_h[$h] = [$horas['vis'][$h], $horas['entradas'][$h], $horas_antes ? $horas_antes['vis'][$h] + $horas_antes['entradas'][$h] : 0];
  $max_h = max(1, ...array_merge(...array_values($serie_h)));
  $hora_agora = (int)date('G'); ?>
  <h2>Hoje, hora a hora</h2>
  <p class="np-legenda"><span><i style="background:var(--verde)"></i>Fichas abertas</span><?php if ($tem_entradas): ?><span><i style="background:var(--amarelo)"></i>Chegaram ao site</span><?php endif ?>
    <?php if ($horas_antes): ?><span><i style="background:#c9d6ce"></i>Ontem (aberturas + chegadas)</span><?php endif ?></p>
  <div class="np-grafico" role="img" aria-label="Fichas abertas e chegadas ao site por hora, hoje">
    <?php foreach ($serie_h as $h => [$v, $e, $o]): ?><div class="np-col<?= $h === $hora_agora ? ' hoje' : '' ?>" title="<?= $h ?>h: <?= $v ?> fichas abertas<?= $tem_entradas ? ", $e chegadas ao site" : '' ?><?= $horas_antes ? " · ontem: $o" : '' ?>">
      <?php if ($v + $e > 0): ?><em><?= $v + $e ?></em><?php endif ?>
      <?php if ($horas_antes): ?><span class="c" style="height:<?= round(100 * $o / $max_h) ?>%"></span><?php endif ?>
      <span class="a" style="height:<?= round(100 * $v / $max_h) ?>%"></span><?php if ($tem_entradas): ?><span class="b" style="height:<?= round(100 * $e / $max_h) ?>%"></span><?php endif ?></div><?php endforeach ?>
  </div>
  <div class="np-eixo horas" aria-hidden="true"><?php foreach (range(0, 23) as $h): ?><span><?= $h ?><i>h</i></span><?php endforeach ?></div>
  <p class="discreto">Horário de Brasília. A coluna clara é a hora de agora; hoje ainda não acabou.
    <a href="entradas-dia.php?<?= h(http_build_query(array_filter(['dia' => $hoje_d, 'origem' => in_array($origem, ORIGENS_SITE, true) ? $origem : '', 'cidade' => $cidade]))) ?>">Relatório completo de hoje ›</a></p>
<?php else:
  $max_d = max(1, ...array_values(array_map(fn($d) => max($d['vis'], $d['entradas']), $serie)));
  $n_d = count($serie); $mostrar_num = $n_d <= 31; $passo = max(1, (int)ceil($n_d / 15)); $i = 0; ?>
  <h2>Dia a dia</h2>
  <p class="np-legenda"><span><i style="background:var(--verde)"></i>Fichas abertas</span><?php if ($tem_entradas): ?><span><i style="background:var(--amarelo)"></i>Chegaram ao site</span><?php endif ?>
    <span>Toque num dia para ver o relatório completo dele</span></p>
  <div class="np-grafico" role="img" aria-label="Fichas abertas e chegadas ao site por dia">
    <?php foreach ($serie as $d => $v): ?><a class="np-col<?= $d === $hoje_d ? ' hoje' : '' ?>" href="entradas-dia.php?<?= h(http_build_query(array_filter(['dia' => $d, 'origem' => in_array($origem, ORIGENS_SITE, true) ? $origem : '', 'cidade' => $cidade]))) ?>"
      title="<?= h(data_br($d)) ?> (<?= $dia_semana($d) ?>): <?= $v['vis'] ?> fichas abertas<?= $tem_entradas ? ', ' . $v['entradas'] . ' chegadas ao site' : '' ?>, <?= $v['toques'] ?> contatos">
      <?php if ($mostrar_num && $v['vis'] > 0): ?><em><?= num($v['vis']) ?></em><?php endif ?>
      <span class="a" style="height:<?= round(100 * $v['vis'] / $max_d) ?>%"></span><?php if ($tem_entradas): ?><span class="b" style="height:<?= round(100 * $v['entradas'] / $max_d) ?>%"></span><?php endif ?></a><?php endforeach ?>
  </div>
  <div class="np-eixo" aria-hidden="true"><?php foreach (array_keys($serie) as $d): ?><span><?= $i++ % $passo === 0 ? date('d/m', strtotime($d)) : '' ?></span><?php endforeach ?></div>
  <?php if ($inicio_dados > $desde): ?><p class="discreto">Os números começam em <?= h(data_br($inicio_dados)) ?>; os dias antes disso ficam vazios.</p><?php endif ?>
  <details class="np-mais"><summary>Ver em tabela</summary>
    <div class="tabela"><table>
      <tr><th>Dia</th><?php if ($tem_entradas): ?><th>Chegaram ao site</th><?php endif ?><?php if ($tem_aparicoes): ?><th>Aparições</th><?php endif ?><th>Fichas abertas</th><th>Pessoas</th><th>Contatos</th></tr>
      <?php foreach (array_reverse($serie, true) as $d => $v): if ($d < $inicio_dados) continue; ?><tr>
        <td><a href="entradas-dia.php?<?= h(http_build_query(array_filter(['dia' => $d, 'origem' => in_array($origem, ORIGENS_SITE, true) ? $origem : '', 'cidade' => $cidade]))) ?>"><strong><?= date('d/m', strtotime($d)) ?></strong> <?= $dia_semana($d) ?></a></td>
        <?php if ($tem_entradas): ?><td><?= num($v['entradas']) ?></td><?php endif ?><?php if ($tem_aparicoes): ?><td><?= num($v['aparicoes']) ?></td><?php endif ?>
        <td><?= num($v['vis']) ?></td><td><?= num($v['pessoas']) ?></td><td><?= num($v['toques']) ?></td></tr><?php endforeach ?>
    </table></div>
  </details>
<?php endif ?>
</section>

<!-- 4. de onde vêm -->
<div class="np-duas">
  <section class="np-caixa">
    <h2>De onde vêm as fichas abertas</h2>
    <p class="discreto">Toque numa origem para ver só ela na página toda.</p>
    <?php $app_l = $site_l = [];
    foreach ($vis_origem as $o => $n) {
        $item = [$rotulo_origem((string)$o), $n, $link(['origem' => $o, 'pag' => null])];
        if (in_array($o, ORIGENS_SITE, true)) $site_l[] = $item; else $app_l[] = $item;
    }
    $max_o = max(1, ...array_values($vis_origem ?: [0])); ?>
    <?php if ($app_l): ?><p class="np-sub">📱 No app · <?= num(array_sum(array_column($app_l, 1))) ?></p><?= $barras($app_l, '', $max_o) ?><?php endif ?>
    <?php if ($site_l): ?><p class="np-sub">🌐 No site · <?= num(array_sum(array_column($site_l, 1))) ?></p><?= $barras($site_l, '', $max_o) ?><?php endif ?>
    <?php if (!$vis_origem): ?><p class="discreto">Nenhuma ficha aberta no período.</p><?php endif ?>
  </section>
  <?php if ($tem_entradas): ?>
  <section class="np-caixa">
    <h2>Como chegaram ao site</h2>
    <p class="discreto">Cada chegada é uma pessoa entrando no site vinda de fora; quem continua navegando não conta de novo.</p>
    <?= $barras(array_map(fn($o, $n) => [$rotulo_origem((string)$o), $n, $link(['origem' => $o, 'pag' => null])], array_keys($ent_origem), $ent_origem), 'amarela') ?>
    <p class="discreto">As palavras pesquisadas no Google não chegam ao site: veja no
      <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Search Console</a> ou em <a href="google.php">Google por cidade</a>.</p>
  </section>
  <?php endif ?>
</div>

<!-- 5. onde há interesse -->
<div class="np-duas">
  <section class="np-caixa">
    <h2>Cidades</h2>
    <?php if (!$cid_lista): ?><p class="discreto">Nada no período.</p><?php else: $max_c = max(1, ...array_values(array_map(fn($r) => max($r['vis'], $r['ent']), $cid_lista))); ?>
    <div class="tabela" style="margin-bottom:0"><table class="np-ranking">
      <tr><th>Cidade</th><?php if ($tem_entradas): ?><th>Chegadas</th><?php endif ?><th>Aberturas</th><th>Contatos</th></tr>
      <?php foreach ($cid_lista as $c => $r): ?><tr>
        <td><a href="<?= h($link(['cidade' => $c, 'pag' => null])) ?>" title="Ver só esta cidade"><strong><?= $nome_cidade_l((string)$c) ?></strong></a></td>
        <?php if ($tem_entradas): ?><td><span class="barra-mini" style="width:<?= round(60 * $r['ent'] / $max_c) ?>px;background:var(--amarelo)"></span><?= num($r['ent']) ?></td><?php endif ?>
        <td><span class="barra-mini" style="width:<?= round(60 * $r['vis'] / $max_c) ?>px"></span><?= num($r['vis']) ?></td>
        <td><strong><?= num($r['toq']) ?></strong></td></tr><?php endforeach ?>
    </table></div>
    <?php endif ?>
  </section>
  <section class="np-caixa">
    <h2>Áreas mais abertas</h2>
    <?= $barras(array_map(fn($l) => [h($l['chave']) . ((int)$l['toq'] ? ' <small class="discreto">· ' . num((int)$l['toq']) . ' contato' . ((int)$l['toq'] === 1 ? '' : 's') . '</small>' : ''), (int)$l['vis']],
        array_filter($por_area, fn($l) => (int)$l['vis'] > 0))) ?>
  </section>
</div>

<div class="np-duas">
  <section class="np-caixa">
    <h2>Comércios mais abertos</h2>
    <?php if (!$top_comercios): ?><p class="discreto">Nada no período.</p><?php else: ?>
    <div class="tabela" style="margin-bottom:0"><table class="np-ranking">
      <tr><th>#</th><th>Comércio</th><th>Aberturas</th><th>Contatos</th></tr>
      <?php foreach ($top_comercios as $i => $l): ?><tr>
        <td><?= $i + 1 ?></td>
        <td><a href="numeros-ficha.php?id=<?= (int)$l['id'] ?>&amp;dias=<?= $dias ?>" title="Números desta ficha"><strong><?= h($l['nome']) ?></strong></a><br>
          <span class="discreto"><?= h($l['tipo'] ?: $l['categoria']) ?> · <?= adm_cidade($l['cidade'], null, true) ?></span></td>
        <td><?= num((int)$l['vis']) ?></td><td><strong><?= num((int)$l['toq']) ?></strong></td></tr><?php endforeach ?>
    </table></div>
    <p class="discreto"><a href="#fichas">Ver todas as fichas ›</a></p>
    <?php endif ?>
  </section>
  <section class="np-caixa">
    <h2>Como entraram em contato</h2>
    <?php $tt = array_filter(array_map(fn($k) => [h(TIPOS_CONTATO[$k]), $tipos_toque[$k] ?? 0], array_diff(array_keys(TIPOS_CONTATO), ['compartilhar'])), fn($l) => $l[1] > 0);
    usort($tt, fn($a, $b) => $b[1] <=> $a[1]); ?>
    <?= $barras($tt) ?>
    <?php if (!empty($tipos_toque['compartilhar'])): ?><p class="discreto">E <?= num($tipos_toque['compartilhar']) ?> compartilhamento<?= $tipos_toque['compartilhar'] > 1 ? 's' : '' ?> de ficha no WhatsApp (não entra nos contatos).</p><?php endif ?>
    <?php if ($e_app && $origem !== 'app'): ?><p class="discreto">No app os contatos não se separam por lista: aqui aparecem todos os contatos feitos no app.</p><?php endif ?>
  </section>
</div>

<?php if ($dias > 1 && array_sum($horas['vis']) + array_sum($horas['entradas']) > 0):
  $hs = []; foreach (range(0, 23) as $h) $hs[$h] = $horas['vis'][$h] + $horas['entradas'][$h];
  $max_hs = max(1, ...$hs); $pico = array_search(max($hs), $hs, true); ?>
<section class="np-caixa">
  <h2>Horários de mais movimento</h2>
  <p class="discreto">Fichas abertas e chegadas ao site somadas por hora do dia, no período. Pico: <strong><?= $pico ?>h às <?= $pico + 1 ?>h</strong>.</p>
  <div class="np-grafico" style="height:130px" role="img" aria-label="Movimento por hora do dia">
    <?php foreach ($hs as $h => $n): ?><div class="np-col" title="<?= $h ?>h: <?= $n ?>"><span class="a" style="height:<?= round(100 * $n / $max_hs) ?>%"></span></div><?php endforeach ?>
  </div>
  <div class="np-eixo horas" aria-hidden="true"><?php foreach (range(0, 23) as $h): ?><span><?= $h ?><i>h</i></span><?php endforeach ?></div>
</section>
<?php endif ?>

<?php if ($paginas): ?>
<section class="np-caixa">
  <h2>Páginas por onde entram no site</h2>
  <p class="discreto">A primeira página que a pessoa viu ao chegar. Toque para abrir no site.</p>
  <div class="tabela" style="margin-bottom:0"><table class="np-ranking">
    <tr><th>Página</th><th>Tipo</th><th>Chegadas</th><th>Pelo Google</th><th>Último dia</th></tr>
    <?php foreach (array_slice($paginas, 0, 10) as $l): ?><tr>
      <td><a href="<?= h(url_publica(ltrim($l['caminho'], '/'))) ?>" target="_blank" rel="noopener"><?= h($l['caminho']) ?></a></td>
      <td><?= h(PAGINAS_ENTRADA[$l['pagina']] ?? $l['pagina']) ?></td><td><strong><?= num((int)$l['total']) ?></strong></td><td><?= num((int)$l['google']) ?></td>
      <td><?= date('d/m', strtotime($l['ultimo'])) ?></td></tr><?php endforeach ?>
  </table></div>
  <?php if (count($paginas) > 10): ?><details class="np-mais"><summary>Ver mais <?= count($paginas) - 10 ?> páginas</summary>
    <div class="tabela"><table class="np-ranking">
      <?php foreach (array_slice($paginas, 10) as $l): ?><tr>
        <td><a href="<?= h(url_publica(ltrim($l['caminho'], '/'))) ?>" target="_blank" rel="noopener"><?= h($l['caminho']) ?></a></td>
        <td><?= h(PAGINAS_ENTRADA[$l['pagina']] ?? $l['pagina']) ?></td><td><strong><?= num((int)$l['total']) ?></strong></td><td><?= num((int)$l['google']) ?></td>
        <td><?= date('d/m', strtotime($l['ultimo'])) ?></td></tr><?php endforeach ?>
    </table></div></details><?php endif ?>
</section>
<?php endif ?>

<!-- 6. todas as fichas -->
<section class="np-caixa" id="fichas">
  <h2>Todas as fichas com movimento</h2>
  <p class="discreto"><?= num($total_fichas) ?> fichas apareceram, foram abertas ou tiveram contato no período (as zeradas não aparecem).
    Toque no título de uma coluna para ordenar. O CSV serve de lista de prospecção.</p>
  <form method="get" class="filtros-numeros" action="#fichas">
    <input type="hidden" name="dias" value="<?= $dias ?>"><?php if ($cidade !== ''): ?><input type="hidden" name="cidade" value="<?= h($cidade) ?>"><?php endif ?>
    <?php if ($origem !== ''): ?><input type="hidden" name="origem" value="<?= h($origem) ?>"><?php endif ?>
    <input type="hidden" name="ordem" value="<?= h($ordem) ?>">
    <label>Área <select name="categoria" onchange="this.form.tipo.value=''"><option value="">Todas</option>
      <?php foreach ($categorias as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === $cat ? ' selected' : '' ?>><?= h($c['nome']) ?></option><?php endforeach ?>
    </select></label>
    <label>Tipo <select name="tipo"><option value="">Todos</option>
      <?php foreach ($tipos as $tt_): ?><option value="<?= h($tt_['tipo']) ?>"<?= $tt_['tipo'] === $tipo ? ' selected' : '' ?>><?= h($tt_['tipo']) ?> (<?= (int)$tt_['n'] ?>)</option><?php endforeach ?>
    </select></label>
    <label>Plano <select name="plano"><option value="">Todos</option>
      <?php foreach ($nome_plano as $k => $n): ?><option value="<?= $k ?>"<?= $k === $plano ? ' selected' : '' ?>><?= $n ?></option><?php endforeach ?>
    </select></label>
    <label>Por página <select name="por">
      <?php foreach (POR_PAGINA as $n): ?><option value="<?= $n ?>"<?= $n === $por ? ' selected' : '' ?>><?= $n ?></option><?php endforeach ?>
    </select></label>
    <label class="caixa"><input type="checkbox" name="vistos" value="1"<?= $so_vistos ? ' checked' : '' ?>> Só as que foram abertas</label>
    <button class="botao pequeno">Filtrar</button>
    <a class="botao pequeno contorno" href="<?= h($link(['csv' => 1])) ?>">Baixar CSV</a>
  </form>
  <?= $paginacao() ?>
  <div class="tabela"><table>
    <tr><th>Comércio</th><th>Plano</th>
      <?php foreach (COLUNAS as $k => $n): if ($k === 'aparicoes' && !$tem_aparicoes) continue; ?><th><a href="<?= h($link(['ordem' => $k, 'pag' => null], '#fichas')) ?>"><?= $n ?><?= $k === $ordem ? ' ▼' : '' ?></a></th><?php endforeach ?>
      <th>Taxa</th><th></th></tr>
    <?php foreach (array_slice($lista, ($pag - 1) * $por, $por) as $l): ?>
    <tr>
      <td><strong><?= adm_comercio((int)$l['id'], $l['nome'], $l['cidade']) ?></strong><br><span class="discreto"><?= h($l['tipo'] ?: $l['categoria']) ?> · <?= adm_cidade($l['cidade'], null, true) ?><?= $l['bairro'] ? ' · ' . h($l['bairro']) : '' ?></span></td>
      <td><?= $nome_plano[$l['plano_atual']] ?></td>
      <?php foreach (COLUNAS as $k => $n): if ($k === 'aparicoes' && !$tem_aparicoes) continue; ?><td><?= (int)$l[$k] ? num((int)$l[$k]) : '<span class="discreto">0</span>' ?></td><?php endforeach ?>
      <td><?= pct((int)$l['contatos'], (int)$l['visualizacoes']) ?></td>
      <td><a class="botao pequeno contorno" href="numeros-ficha.php?id=<?= (int)$l['id'] ?>&amp;dias=<?= $dias ?>">Ver</a></td>
    </tr>
    <?php endforeach ?>
  </table></div>
  <?= $paginacao() ?>
</section>

<section class="np-caixa">
  <details class="np-mais"><summary>Como ler estes números</summary>
    <dl class="np-ajuda">
      <dt>Chegaram ao site</dt><dd>Pessoa que entrou no site vinda de fora. Desde 21/11 só conta quando a página abre num navegador de verdade (robôs que só baixam a página ficam de fora). Quem continua navegando por 30 min não conta de novo.</dd>
      <dt>Apareceram em listas</dt><dd>Cada vez que uma ficha foi mostrada no app: tela inicial, área, busca ou "perto de você" (numa lista longa, só as 40 primeiras).</dd>
      <dt>Fichas abertas e pessoas</dt><dd>Quem reabre a mesma ficha em menos de 30 min não conta de novo. Pessoas = pessoas diferentes por dia, somadas no período.</dd>
      <dt>Contatos</dt><dd>Toques em WhatsApp, ligar, mapa, site e Instagram. Compartilhar a ficha aparece à parte.</dd>
      <dt>Origem</dt><dd>No app, é a lista onde a pessoa tocou (busca, área, início…). No site, de onde ela chegou (Google, redes, Acha Fácil, digitou o endereço…). Os contatos feitos no app não se separam por lista.</dd>
      <dt>Quem não conta</dt><dd>Robôs (buscadores, teste da Google Play, varreduras) e a equipe: quem está no admin, conta de admin no app ou no site, o navegador que já abriu o admin e o IP que abriu o admin nas últimas 3 horas.</dd>
      <dt>Comparação</dt><dd>A seta compara com o período de mesmo tamanho logo antes (em "Hoje", com o dia inteiro de ontem). Os números começam em <?= h(data_br($inicio_dados)) ?>.</dd>
    </dl>
  </details>
</section>
<?php admin_fim();
