<?php
// 23/11 (avaliação do site, item 3): fichas repetidas na mesma cidade (a mesma loja importada 2 vezes com nomes um pouco
// diferentes: "Supermercado Forte Mix" e "Supermercados Forte Mix"). O robô só ACHA os grupos; quem decide é o admin em
// /admin/duplicadas.php: "Juntar" (fica uma, as outras são pausadas e o endereço delas redireciona) ou "Não são a mesma".
// Tabela fichas_iguais (mudança 044).
declare(strict_types=1);
require_once __DIR__ . '/guia.php';
require_once __DIR__ . '/limpeza.php';

/** Palavras que não identificam a loja (tipo de comércio, ligação, cidade): ficam fora da comparação dos nomes. */
const DUP_GENERICAS = ['supermercado', 'supermercados', 'mercado', 'mercados', 'mercearia', 'loja', 'lojas', 'clinica', 'clinicas', 'posto', 'postos',
    'auto', 'pecas', 'farmacia', 'farmacias', 'drogaria', 'drogarias', 'restaurante', 'lanchonete', 'pizzaria', 'padaria', 'panificadora', 'salao',
    'studio', 'estudio', 'centro', 'casa', 'comercio', 'servicos', 'servico', 'ltda', 'eireli', 'materiais', 'construcao', 'oficina', 'mecanica',
    'odontologia', 'odontologica', 'dentista', 'consultorio', 'escritorio', 'advocacia', 'advogado', 'advogada', 'contabilidade', 'academia',
    'distribuidora', 'comercial', 'industria', 'empresa', 'grupo', 'unidade', 'filial', 'matriz', 'the', 'and', 'dos', 'das', 'para', 'com',
    'sao', 'joao', 'boa', 'vista', 'pocos', 'caldas', 'vargem', 'grande', 'sul', 'aguai', 'santa', 'rita', 'sapucai', 'cidade', 'centro',
    'doutor', 'doutora', 'dra', 'dr', 'cirurgia', 'cirurgia', 'dentista', 'acessorios', 'automotivos', 'veiculos', 'bar', 'barbearia', 'beleza',
    'estetica', 'cabeleireiro', 'cabeleireira', 'moda', 'roupas', 'calcados', 'presentes', 'variedades', 'utilidades', 'ferramentas', 'tintas',
    'imobiliaria', 'imoveis', 'corretor', 'corretora', 'seguros', 'laboratorio', 'analises', 'clinicas', 'hospital', 'pet', 'shop', 'veterinaria',
    'igreja', 'escola', 'colegio', 'hotel', 'pousada', 'auto', 'center', 'express', 'delivery', 'store', 'loja'];

/** Palavras que identificam a loja: "Supermercados Forte Mix - São João" → ['forte', 'mix']. */
function dup_palavras(string $nome): array {
    $t = preg_replace('/[^a-z0-9 ]+/', ' ', chave_texto(nome_curto($nome, 120)));
    $r = [];
    foreach (preg_split('/\s+/', trim($t)) as $w) if (mb_strlen($w) >= 3 && !in_array($w, DUP_GENERICAS, true) && !ctype_digit($w)) $r[$w] = 1;
    return array_keys($r);
}
/** Nomes parecidos: as palavras próprias do nome menor estão (quase) todas no maior. */
function dup_nomes_parecidos(string $a, string $b): bool {
    $pa = dup_palavras($a); $pb = dup_palavras($b);
    if (!$pa || !$pb) return chave_texto(nome_curto($a)) === chave_texto(nome_curto($b));
    [$menor, $maior] = count($pa) <= count($pb) ? [$pa, $pb] : [$pb, $pa];
    $iguais = count(array_intersect($menor, $maior));
    return $iguais >= 1 && $iguais >= ceil(count($menor) * 0.6);
}

/** Pares já decididos: "a-b" => situação. */
function dup_decididos(): array {
    $r = [];
    foreach (linhas('SELECT a_id, b_id, situacao FROM fichas_iguais') as $l) $r[$l['a_id'] . '-' . $l['b_id']] = $l['situacao'];
    return $r;
}

/**
 * Grupos de fichas ativas que parecem a mesma loja: mesma cidade E (mesmo telefone/WhatsApp OU ponto exato do mapa a até
 * 40 m) E nomes parecidos. Pares que o admin já disse "não são a mesma" ficam fora. Devolve listas de fichas (do banco).
 */
function dup_grupos(?string $cidade = null): array {
    $where = "p.status = 'ativo'" . ($cidade !== null && isset(CIDADES[$cidade]) ? " AND p.cidade = '$cidade'" : '');
    $fichas = [];
    foreach (linhas("SELECT p.id, p.nome, p.cidade, p.tipo, p.bairro, p.endereco, p.telefone, p.whatsapp, p.site, p.instagram, p.horarios,
        p.sobre_site, p.descricao, p.lat, p.lng, p.geo_origem, p.popularidade, p.total_avaliacoes, p.foto, " . SQL_TEM_DONO . ' AS tem_dono, '
        . SQL_CONFIRMADA . " AS confirmada FROM profissionais p WHERE $where") as $p) $fichas[(int)$p['id']] = $p;
    $decid = dup_decididos();
    $pai = [];
    $raiz = function (int $x) use (&$pai, &$raiz): int { return !isset($pai[$x]) || $pai[$x] === $x ? $x : ($pai[$x] = $raiz($pai[$x])); };
    $unir = function (int $a, int $b) use (&$pai, $raiz) { $ra = $raiz($a); $rb = $raiz($b); if ($ra !== $rb) $pai[max($ra, $rb)] = min($ra, $rb); };
    $testar = function (array $ids) use ($fichas, $decid, $unir) {
        $ids = array_values(array_unique($ids));
        if (count($ids) < 2 || count($ids) > 30) return;   // 30+: central de rede, não é repetida
        for ($i = 0; $i < count($ids); $i++) for ($j = $i + 1; $j < count($ids); $j++) {
            [$a, $b] = [min($ids[$i], $ids[$j]), max($ids[$i], $ids[$j])];
            if (isset($decid["$a-$b"])) continue;
            if (dup_nomes_parecidos($fichas[$a]['nome'], $fichas[$b]['nome'])) $unir($a, $b);
        }
    };
    // 1) mesmo telefone ou WhatsApp na cidade
    $por_tel = [];
    foreach ($fichas as $id => $p) foreach (array_unique(array_filter([$p['telefone'], $p['whatsapp']])) as $t) {
        $d = preg_replace('/\D/', '', (string)$t);
        if (strlen($d) >= 8) $por_tel[$p['cidade'] . '|' . substr($d, -8)][] = $id;
    }
    foreach ($por_tel as $ids) $testar($ids);
    // 2) ponto exato do mapa (Google) a até ~40 m
    $por_ponto = [];
    foreach ($fichas as $id => $p) if ($p['geo_origem'] === 'google' && $p['lat'] !== null)
        $por_ponto[$p['cidade'] . '|' . round((float)$p['lat'] * 2500) . '|' . round((float)$p['lng'] * 2500)][] = $id;
    foreach ($por_ponto as $ids) $testar($ids);
    $grupos = [];
    foreach ($pai as $x => $_) $grupos[$raiz($x)][] = $fichas[$x];
    foreach ($grupos as $r => $g) if (!in_array($r, array_column($g, 'id'), false)) $grupos[$r][] = $fichas[$r];
    $grupos = array_map(function ($g) { $u = []; foreach ($g as $p) $u[(int)$p['id']] = $p; return array_values($u); }, $grupos);
    $grupos = array_values(array_filter($grupos, fn($g) => count($g) >= 2));
    foreach ($grupos as &$g) usort($g, fn($x, $y) => dup_pontos($y) <=> dup_pontos($x));   // a sugerida para ficar vem primeiro
    unset($g);
    usort($grupos, fn($x, $y) => [$x[0]['cidade'], mb_strtolower($x[0]['nome'])] <=> [$y[0]['cidade'], mb_strtolower($y[0]['nome'])]);
    return $grupos;
}

/** Qual fica: com dono > confirmada > mais completa > mais conhecida > mais antiga. */
function dup_pontos(array $p): array {
    $dados = (int)(bool)$p['whatsapp'] + (int)(bool)$p['telefone'] + (int)(bool)$p['site'] + (int)(bool)$p['instagram'] + (int)(bool)$p['horarios']
        + (int)(bool)$p['sobre_site'] + (int)(bool)$p['descricao'] + (int)(bool)$p['foto'] + (int)($p['geo_origem'] === 'google')
        + (int)(!empty($p['endereco']) && !endereco_invalido($p['endereco'])) + 2 * (int)$p['total_avaliacoes']
        + (int)(nome_curto((string)$p['nome']) === trim((string)$p['nome']) && mb_strlen((string)$p['nome']) <= 50 && !str_contains((string)$p['nome'], '·'));   // nome limpo
    return [(int)$p['tem_dono'], (int)$p['confirmada'], $dados, (int)$p['popularidade'], -(int)$p['id']];
}

/** Campos que a ficha que fica recebe da que sai quando ela não tem (só ficha sem dono; dado do dono não é mexido). */
const DUP_COMPLETAR = ['whatsapp', 'telefone', 'site', 'instagram', 'email', 'endereco', 'bairro', 'mapa', 'horarios', 'horario',
    'sobre_site', 'sobre_site_url', 'ifood', 'food99', 'cardapio'];

/** Junta $sai em $fica. Devolve null se deu certo, ou o motivo de não juntar. */
function dup_juntar(int $fica, int $sai, ?int $admin_id): ?string {
    if ($fica === $sai) return 'É a mesma ficha.';
    $tem_dono = 'SELECT p.*, ' . SQL_TEM_DONO . ' AS tem_dono FROM profissionais p WHERE p.id = ?';
    $f = linha($tem_dono, [$fica]); $s = linha($tem_dono, [$sai]);
    if (!$f || !$s) return 'Ficha não encontrada.';
    if ($s['tem_dono']) return nome_curto($s['nome']) . " (#$sai) tem dono: ela é que deve ficar.";
    if ($s['status'] !== 'ativo') return "#$sai já está fora do app.";
    $antes = ['fica' => [], 'sai_status' => $s['status'], 'favoritos' => [], 'avaliacoes' => []];
    $novo = [];
    if (!$f['tem_dono']) {
        foreach (DUP_COMPLETAR as $c) {
            if (!array_key_exists($c, $f)) continue;
            $vazio = fn($v) => $v === null || trim((string)$v) === '' || ($c === 'endereco' && endereco_invalido((string)$v));
            if ($vazio($f[$c]) && !$vazio($s[$c])) { $novo[$c] = $s[$c]; $antes['fica'][$c] = $f[$c]; }
        }
        if ($f['geo_origem'] !== 'google' && $s['geo_origem'] === 'google') {
            foreach (['lat', 'lng', 'geo_origem'] as $c) { $novo[$c] = $s[$c]; $antes['fica'][$c] = $f[$c]; }
        }
        if ((int)$s['popularidade'] > (int)$f['popularidade']) { $novo['popularidade'] = $s['popularidade']; $antes['fica']['popularidade'] = $f['popularidade']; }
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($novo) q('UPDATE profissionais SET ' . implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($novo))) . ' WHERE id = ?', [...array_values($novo), $fica]);
        foreach (['favoritos', 'avaliacoes'] as $tab) foreach (linhas("SELECT usuario_id FROM $tab WHERE profissional_id = ?", [$sai]) as $l) {
            $u = (int)$l['usuario_id'];
            if (valor("SELECT 1 FROM $tab WHERE profissional_id = ? AND usuario_id = ?", [$fica, $u])) continue;   // já tinha na que fica
            q("UPDATE $tab SET profissional_id = ? WHERE profissional_id = ? AND usuario_id = ?", [$fica, $sai, $u]);
            $antes[$tab][] = $u;
        }
        q("UPDATE profissionais SET status = 'pausado', atualizado_em = atualizado_em WHERE id = ?", [$sai]);
        [$a, $b] = [min($fica, $sai), max($fica, $sai)];
        q("REPLACE INTO fichas_iguais (a_id, b_id, situacao, fica_id, sai_id, antes, decidido_em, decidido_por) VALUES (?, ?, 'juntada', ?, ?, ?, NOW(), ?)",
            [$a, $b, $fica, $sai, json_encode($antes, JSON_UNESCAPED_UNICODE), $admin_id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return 'Erro ao juntar: ' . $e->getMessage();
    }
    if ($antes['avaliacoes']) recalcular_nota($fica);
    require_once __DIR__ . '/moderacao.php';
    registrar($admin_id, 'ficha_juntada', $fica, "#$sai juntada nesta" . ($novo ? ' (recebeu: ' . implode(', ', array_keys($novo)) . ')' : ''));
    return null;
}

/** Desfaz uma junção: a ficha que saiu volta, e a que ficou perde o que tinha recebido (se ainda estiver igual). */
function dup_desfazer(int $a, int $b, ?int $admin_id): ?string {
    $l = linha("SELECT * FROM fichas_iguais WHERE a_id = ? AND b_id = ? AND situacao = 'juntada'", [$a, $b]);
    if (!$l) return 'Junção não encontrada.';
    $antes = json_decode((string)$l['antes'], true) ?: [];
    $fica = (int)$l['fica_id']; $sai = (int)$l['sai_id'];
    $pdo = db();
    $pdo->beginTransaction();
    foreach (($antes['fica'] ?? []) as $c => $v) if (in_array($c, [...DUP_COMPLETAR, 'lat', 'lng', 'geo_origem', 'popularidade'], true))
        q("UPDATE profissionais SET `$c` = ? WHERE id = ?", [$v, $fica]);
    foreach (['favoritos', 'avaliacoes'] as $tab) foreach (($antes[$tab] ?? []) as $u)
        q("UPDATE IGNORE $tab SET profissional_id = ? WHERE profissional_id = ? AND usuario_id = ?", [$sai, $fica, (int)$u]);
    q('UPDATE profissionais SET status = ?, atualizado_em = atualizado_em WHERE id = ?', [$antes['sai_status'] ?? 'ativo', $sai]);
    q('DELETE FROM fichas_iguais WHERE a_id = ? AND b_id = ?', [$a, $b]);
    $pdo->commit();
    if (!empty($antes['avaliacoes'])) { recalcular_nota($fica); recalcular_nota($sai); }
    require_once __DIR__ . '/moderacao.php';
    registrar($admin_id, 'ficha_juntada_desfeita', $fica, "#$sai voltou");
    return null;
}

/** Ficha que saiu numa junção: id da que ficou (seguindo junções em cadeia), ou null. Para o redirecionamento 301. */
function dup_destino(int $id): ?int {
    for ($i = 0; $i < 5; $i++) {
        $para = valor("SELECT fica_id FROM fichas_iguais WHERE sai_id = ? AND situacao = 'juntada' LIMIT 1", [$id]);
        if (!$para) return $i ? $id : null;
        $id = (int)$para;
    }
    return $id;
}

/** "R. Santa Maria, 500 - sala 2" → "santa maria|500" (rua e número), para ver se é o mesmo endereço. */
function dup_chave_endereco(?string $e): ?string {
    if ($e === null || endereco_invalido($e)) return null;
    $t = preg_replace('/[^a-z0-9 ,]+/', ' ', chave_texto($e));
    $t = preg_replace('/^(r|rua|av|avenida|al|alameda|praca|pca|rod|rodovia|estr|estrada|tv|travessa|largo)\b\s*/', '', trim($t));
    if (!preg_match('/^([a-z0-9 ]+?)[\s,]+(?:n\s*)?(\d{1,5})\b/', $t, $m)) return null;
    return trim(preg_replace('/\s+/', ' ', $m[1])) . '|' . $m[2];
}
/** Todas as fichas do grupo no mesmo endereço (rua e número) ou no mesmo ponto exato do mapa: quase certo que é repetida. */
function dup_mesmo_lugar(array $g): bool {
    $ends = array_unique(array_map(fn($p) => dup_chave_endereco($p['endereco']), $g));
    if (count($ends) === 1 && $ends[0] !== null) return true;
    $pts = array_unique(array_map(fn($p) => $p['geo_origem'] === 'google' && $p['lat'] !== null ? round((float)$p['lat'], 4) . ',' . round((float)$p['lng'], 4) : 'x' . $p['id'], $g));
    return count($pts) === 1;
}
