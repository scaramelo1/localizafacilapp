<?php
// Passo 119 (4), 20/11: conteúdo próprio nas páginas de cidade, área e tipo, feito com dados reais de cada página
// (números, bairros, mais conhecidos, cidades vizinhas e perguntas), para elas não serem só uma lista que muda todo dia.
// Só aparece na página 1, sem filtro de bairro e sem "Aberto agora" (as páginas que vão para o Google).
// Regra do projeto: nota do Google não é exibida; popularidade (nº de avaliações no Google) só ordena ("mais conhecidos").
declare(strict_types=1);

/** Cidades ativas mais perto (linha reta), sem a própria. */
function web_cidades_perto(string $cidade, int $n = 6): array {
    $c = CIDADES[$cidade] ?? null;
    if (!$c || $c[3] === null) return [];
    $dist = [];
    foreach (CIDADES as $s => $o) {
        if ($s === $cidade || $o[3] === null) continue;
        $dx = ($o[4] - $c[4]) * cos(deg2rad(($o[3] + $c[3]) / 2));
        $dy = $o[3] - $c[3];
        $dist[$s] = sqrt($dx * $dx + $dy * $dy) * 111.2;
    }
    asort($dist);
    return array_slice($dist, 0, $n, true);
}

/** Números de uma lista (cidade, ou cidade + área, ou + tipo). */
function web_numeros_lista(string $cidade, ?int $categoria_id = null, ?string $tipo = null): array {
    $sql = visivel($cidade);
    $par = [];
    if ($categoria_id !== null) { $sql .= ' AND p.categoria_id = ?'; $par[] = $categoria_id; }
    if ($tipo !== null) { $sql .= ' AND p.tipo = ?'; $par[] = $tipo; }
    $r = linha("SELECT COUNT(*) AS total,
        SUM(COALESCE(p.whatsapp, '') <> '') AS whats,
        SUM(COALESCE(p.telefone, '') <> '' OR COALESCE(p.whatsapp, '') <> '') AS contato,
        SUM(COALESCE(p.site, '') <> '' OR (COALESCE(p.instagram, '') <> '' AND " . SQL_BASICO_LIBERADO . ")) AS online,
        SUM(" . SQL_COM_HORARIO . ") AS horario,
        SUM(p.atende_em_casa = 1 AND " . SQL_BASICO_LIBERADO . ") AS em_casa,   -- 23/11 (passo 72): só quem pagou o Básico
        COUNT(DISTINCT NULLIF(p.bairro, '')) AS bairros
        FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE $sql", $par) ?? [];
    return array_map('intval', $r);
}

/** Bairros com mais lugares: [[bairro, total]]. */
function web_top_bairros(string $cidade, ?int $categoria_id = null, ?string $tipo = null, int $n = 6): array {
    $sql = visivel($cidade) . " AND p.bairro IS NOT NULL AND p.bairro <> ''";
    $par = [];
    if ($categoria_id !== null) { $sql .= ' AND p.categoria_id = ?'; $par[] = $categoria_id; }
    if ($tipo !== null) { $sql .= ' AND p.tipo = ?'; $par[] = $tipo; }
    return linhas("SELECT p.bairro, COUNT(*) AS total FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE $sql GROUP BY p.bairro HAVING total >= 2 ORDER BY total DESC, p.bairro LIMIT $n", $par);
}

/** Mais conhecidos (mais avaliações no Google + avaliações no Localiza Fácil), ordem fixa: nome, bairro, id. */
function web_mais_conhecidos(string $cidade, ?int $categoria_id = null, ?string $tipo = null, int $n = 5): array {
    $sql = visivel($cidade);
    $par = [];
    if ($categoria_id !== null) { $sql .= ' AND p.categoria_id = ?'; $par[] = $categoria_id; }
    if ($tipo !== null) { $sql .= ' AND p.tipo = ?'; $par[] = $tipo; }
    return linhas("SELECT p.id, p.nome, p.cidade, p.bairro, p.tipo FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE $sql AND (p.popularidade > 0 OR p.total_avaliacoes > 0)
        ORDER BY p.popularidade + p.total_avaliacoes * 5 DESC, p.id LIMIT $n", $par);
}

/** Tipos mais comuns da cidade (de todas as áreas), com o slug da área para o link. */
function web_top_tipos_cidade(string $cidade, int $n = 16): array {
    return linhas('SELECT p.tipo AS nome, c.slug AS area, COUNT(*) AS total FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . " AND p.tipo IS NOT NULL AND p.tipo <> '' AND p.tipo NOT IN ('Escritório da empresa', 'Empresa') GROUP BY p.tipo, c.slug
        HAVING total >= " . WEB_TIPO_MINIMO . " ORDER BY total DESC, p.tipo LIMIT $n");
}

/** O mesmo tipo (ou área) nas cidades vizinhas que têm página no Google: [[cidade, km, total]]. */
function web_vizinhas_com(string $cidade, int $categoria_id, ?string $tipo): array {
    $perto = web_cidades_perto($cidade, 8);
    if (!$perto) return [];
    $lista = "'" . implode("','", array_keys($perto)) . "'";   // slugs já validados em carregar_cidades()
    $sql = visivel() . " AND p.cidade IN ($lista) AND p.categoria_id = ?";
    $par = [$categoria_id];
    if ($tipo !== null) { $sql .= ' AND p.tipo = ?'; $par[] = $tipo; }
    $tot = array_column(linhas("SELECT p.cidade, COUNT(*) AS total FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE $sql GROUP BY p.cidade", $par), 'total', 'cidade');
    $r = [];
    foreach ($perto as $s => $km) {
        $t = (int)($tot[$s] ?? 0);
        if ($t >= ($tipo !== null ? WEB_TIPO_MINIMO : 1)) $r[] = [$s, $km, $t];
    }
    return array_slice($r, 0, 6);
}

function web_pct(int $parte, int $total): string { return $total ? (string)round($parte * 100 / $total) . '%' : '0%'; }

/** Junta "A, B e C". */
function web_e(array $itens): string {
    $itens = array_values($itens);
    if (count($itens) <= 1) return $itens[0] ?? '';
    return implode(', ', array_slice($itens, 0, -1)) . ' e ' . end($itens);
}

/** Bloco "Sobre" + mais conhecidos + bairros + vizinhas + perguntas, na página de área ou tipo. */
function web_conteudo_lista(string $cidade, array $area, ?string $tipo): string {
    [$nome_cidade, , $uf] = CIDADES[$cidade];
    $cat = (int)$area['id'];
    $o_que = mb_strtolower($tipo ?? $area['nome']);
    $num = web_numeros_lista($cidade, $cat, $tipo);
    $total = $num['total'] ?? 0;
    if ($total < 1) return '';
    $bairros = web_top_bairros($cidade, $cat, $tipo);
    $conhecidos = web_mais_conhecidos($cidade, $cat, $tipo);
    $vizinhas = web_vizinhas_com($cidade, $cat, $tipo);
    $irmaos = $tipo !== null ? array_values(array_filter(web_tipos($cidade, $cat), fn($t) => $t['nome'] !== $tipo)) : [];

    // texto com os números desta página
    $frases = ['O Localiza Fácil reúne <b>' . web_qtd($total, 'lugar', 'lugares') . '</b> de ' . h($o_que) . ' em ' . h("$nome_cidade ($uf)") . '.'];
    $partes = [];
    if ($num['whats']) $partes[] = web_num($num['whats']) . ' atendem pelo WhatsApp';
    if ($num['contato'] > $num['whats']) $partes[] = web_num($num['contato']) . ' têm telefone ou WhatsApp para chamar direto';
    if ($num['online']) $partes[] = web_num($num['online']) . ' têm site ou Instagram';
    if ($partes) $frases[] = 'Desses, ' . web_e($partes) . '.';
    if ($bairros) $frases[] = (count($bairros) > 1 ? 'Os bairros com mais opções são ' : 'O bairro com mais opções é ')
        . web_e(array_map(fn($b) => h($b['bairro']) . ' (' . web_num((int)$b['total']) . ')', array_slice($bairros, 0, 4))) . '.';
    if ($num['em_casa']) $frases[] = web_qtd($num['em_casa'], 'atende', 'atendem') . ' em casa ou a domicílio.';
    if ($num['horario']) $frases[] = web_qtd($num['horario'], 'informou', 'informaram') . ' o horário; use o botão <i>Aberto agora</i> para ver quem está atendendo neste momento.';

    // perguntas com resposta dos dados
    $perguntas = [];
    $perguntas[] = ['Quantos lugares de ' . $o_que . ' há em ' . $nome_cidade . '?',
        'No Localiza Fácil há ' . web_qtd($total, 'lugar', 'lugares') . ' de ' . h($o_que) . ' em ' . h($nome_cidade) . ', com endereço e contato.'];
    if ($conhecidos) $perguntas[] = ['Quais lugares de ' . $o_que . ' são mais conhecidos em ' . $nome_cidade . '?',
        'Entre os mais procurados estão ' . web_e(array_map(fn($p) => '<a href="' . h(url_ficha($p)) . '">' . h(nome_curto($p['nome'])) . '</a>', array_slice($conhecidos, 0, 3))) . '.'];
    if ($bairros) $perguntas[] = ['Em que bairro de ' . $nome_cidade . ' tem mais ' . $o_que . '?',
        'O bairro ' . h($bairros[0]['bairro']) . ' tem mais: ' . web_qtd((int)$bairros[0]['total'], 'lugar', 'lugares') . '. Use o filtro de bairro acima da lista para ver só os de um bairro.'];
    $perguntas[] = ['Como falar com um lugar de ' . $o_que . '?',
        'Abra a ficha e toque em WhatsApp, Ligar ou Mapa. ' . ($num['contato'] ? web_pct($num['contato'], $total) . ' dos lugares desta lista têm telefone ou WhatsApp.' : '')];
    // 23/11 (item 8): na lista de farmácias/drogarias (ou de Saúde), a pergunta da farmácia 24 horas
    if (in_array($tipo, WEB_TIPOS_FARMACIA, true) || ($tipo === null && $area['slug'] === 'saude'))
        $perguntas[] = ['Tem farmácia 24 horas em ' . $nome_cidade . '?', web_resposta_farmacia_24h($cidade)];
    if ($tipo === null && $area['slug'] === 'mercados' && ($r = web_resposta_mercados_domingo($cidade)))
        $perguntas[] = ['Quais mercados abrem no domingo em ' . $nome_cidade . '?', $r];
    if ($vizinhas) $perguntas[] = ['Tem ' . $o_que . ' em cidades perto de ' . $nome_cidade . '?',
        'Sim: ' . web_e(array_map(fn($v) => h(CIDADES[$v[0]][0]) . ' (' . web_num($v[2]) . ', a ' . (int)round($v[1]) . ' km)', array_slice($vizinhas, 0, 3))) . '.'];

    ob_start(); ?>
  <section class="conteudo-extra"><div class="conteudo">
    <h2>Sobre <?= h($o_que) ?> em <?= h($nome_cidade) ?></h2>
    <p class="sobre"><?= implode(' ', $frases) ?></p>
    <?php if ($conhecidos): ?>
      <h3>Mais conhecidos</h3>
      <ol class="conhecidos"><?php foreach ($conhecidos as $p): ?><li><a href="<?= h(url_ficha($p)) ?>"><?= h(nome_curto($p['nome'])) ?></a><?= $p['bairro'] ? ' <small>' . h($p['bairro']) . '</small>' : '' ?></li><?php endforeach ?></ol>
    <?php endif ?>
    <?php if ($irmaos): ?>
      <h3>Veja também em <?= h($nome_cidade) ?></h3>
      <?= web_chips(array_map(fn($t) => '<a href="' . h(url_tipo($cidade, $area['slug'], $t['nome'])) . '">' . h($t['nome']) . ' <small>' . (int)$t['total'] . '</small></a>', $irmaos)) ?>
    <?php endif ?>
    <?php if ($vizinhas): ?>
      <h3><?= h($tipo ?? $area['nome']) ?> perto de <?= h($nome_cidade) ?></h3>
      <p class="chips"><?php foreach ($vizinhas as [$s, $km, $t]): ?><a href="<?= h($tipo !== null ? url_tipo($s, $area['slug'], $tipo) : url_area($s, $area['slug'])) ?>"><?= h(CIDADES[$s][0]) ?> <small><?= web_num($t) ?> · <?= (int)round($km) ?> km</small></a><?php endforeach ?></p>
    <?php endif ?>
    <h3>Perguntas frequentes</h3>
    <div class="perguntas"><?php foreach ($perguntas as [$q, $r]): ?><h4><?= h($q) ?></h4><p><?= $r ?></p><?php endforeach ?></div>
  </div></section>
<?php
    return (string)ob_get_clean();
}

/** Bloco da página da cidade: sobre, o que mais tem, bairros, cidades perto e perguntas. */
function web_conteudo_cidade(string $cidade, array $areas): string {
    [$nome, , $uf] = CIDADES[$cidade];
    $num = web_numeros_lista($cidade);
    $total = $num['total'] ?? 0;
    if ($total < 1) return '';
    $top_tipos = web_top_tipos_cidade($cidade);
    $bairros = web_top_bairros($cidade, null, null, 8);
    $perto = web_cidades_perto($cidade, 8);
    $maiores = array_slice(array_values(array_filter($areas, fn($a) => $a['total'] > 0)), 0);
    usort($maiores, fn($a, $b) => $b['total'] <=> $a['total']);
    $maiores = array_slice($maiores, 0, 3);

    $frases = ['<b>' . h($nome) . '</b> (' . h(NOMES_ESTADOS[$uf] ?? $uf) . ') tem <b>' . web_qtd($total, 'comércio e serviço', 'comércios e serviços') . '</b> no Localiza Fácil'
        . ($num['bairros'] > 1 ? ', em ' . web_num($num['bairros']) . ' bairros' : '') . '.'];
    if ($maiores) $frases[] = 'As áreas com mais lugares são ' . web_e(array_map(fn($a) => h(mb_strtolower($a['nome'])) . ' (' . web_num((int)$a['total']) . ')', $maiores)) . '.';
    if ($top_tipos) $frases[] = 'O que mais tem na cidade: ' . web_e(array_map(fn($t) => h(mb_strtolower($t['nome'])), array_slice($top_tipos, 0, 4))) . '.';
    if ($num['contato']) $frases[] = web_pct($num['contato'], $total) . ' têm telefone ou WhatsApp para chamar direto' . ($num['online'] ? ' e ' . web_num($num['online']) . ' têm site ou Instagram' : '') . '.';

    $perguntas = [['Quantos comércios há em ' . $nome . ' no Localiza Fácil?', web_qtd($total, 'lugar', 'lugares') . ' com endereço e contato, separados por área e tipo de negócio.']];
    if ($bairros) $perguntas[] = ['Quais bairros de ' . $nome . ' têm mais comércios?', web_e(array_map(fn($b) => h($b['bairro']) . ' (' . web_num((int)$b['total']) . ')', array_slice($bairros, 0, 4))) . '.'];
    if ($perto) $perguntas[] = ['Quais cidades ficam perto de ' . $nome . '?', web_e(array_map(fn($s, $km) => h(CIDADES[$s][0]) . ' (' . (int)round($km) . ' km)', array_keys(array_slice($perto, 0, 4, true)), array_slice($perto, 0, 4, true))) . ', em linha reta. Todas têm guia no Localiza Fácil.'];
    // 23/11 (item 8): perguntas com resposta dos horários das fichas
    $perguntas[] = ['Tem farmácia 24 horas em ' . $nome . '?', web_resposta_farmacia_24h($cidade)];
    if ($r = web_resposta_mercados_domingo($cidade)) $perguntas[] = ['Quais mercados abrem no domingo em ' . $nome . '?', $r];
    $perguntas[] = ['Tenho um comércio em ' . $nome . '. Como apareço?', 'Procure o seu negócio na busca acima: se já estiver aqui, toque em "É o dono?". Se não estiver, <a href="' . h(url('incluir.php')) . '">inclua de graça</a>.'];

    ob_start(); ?>
  <section class="conteudo-extra"><div class="conteudo">
    <h2>Sobre o comércio de <?= h($nome) ?></h2>
    <p class="sobre"><?= implode(' ', $frases) ?></p>
    <?php if ($top_tipos): ?>
      <h3>Mais procurados em <?= h($nome) ?></h3>
      <?= web_chips(array_map(fn($t) => '<a href="' . h(url_tipo($cidade, $t['area'], $t['nome'])) . '">' . h($t['nome']) . ' <small>' . web_num((int)$t['total']) . '</small></a>', $top_tipos)) ?>
    <?php endif ?>
    <?php if ($perto): ?>
      <h3>Cidades perto de <?= h($nome) ?></h3>
      <p class="chips"><?php foreach ($perto as $s => $km): ?><a href="<?= h(url_cidade($s)) ?>"><?= h(CIDADES[$s][0] . ' - ' . CIDADES[$s][2]) ?> <small><?= (int)round($km) ?> km</small></a><?php endforeach ?></p>
    <?php endif ?>
    <h3>Perguntas frequentes</h3>
    <div class="perguntas"><?php foreach ($perguntas as [$q, $r]): ?><h4><?= h($q) ?></h4><p><?= $r ?></p><?php endforeach ?></div>
  </div></section>
<?php
    return (string)ob_get_clean();
}

// ---------- 23/11 (avaliação do site, item 8): farmácias 24 horas e o que abre no domingo, pelos horários das fichas ----------
const WEB_TIPOS_FARMACIA = ['Farmácia', 'Drogaria'];

/** Fichas visíveis da cidade com horário, deste tipo (lista) ou desta área (slug). Cada uma com 'h' = horarios_ler(). */
function web_com_horario(string $cidade, ?array $tipos = null, ?string $area_slug = null): array {
    static $cache = [];
    $k = $cidade . '|' . json_encode($tipos) . '|' . $area_slug;
    if (isset($cache[$k])) return $cache[$k];
    $where = visivel($cidade) . " AND p.horarios IS NOT NULL AND p.horarios NOT IN ('', '[]', '{}')";
    $args = [];
    if ($tipos) { $where .= ' AND p.tipo IN (' . implode(',', array_fill(0, count($tipos), '?')) . ')'; $args = $tipos; }
    if ($area_slug !== null) { $where .= ' AND c.slug = ?'; $args[] = $area_slug; }
    $r = [];
    foreach (linhas('SELECT ' . CAMPOS_RESUMO . ', p.popularidade FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . $where
        . ' ORDER BY ' . SQL_DESTAQUE . ' DESC, p.popularidade DESC, p.id', $args) as $p) {
        $p['h'] = horarios_ler($p['horarios']);
        if ($p['h']) $r[] = $p;
    }
    return $cache[$k] = $r;
}
/** Aberta 24 horas todos os dias (segunda a domingo). */
function web_e_24h(array $h): bool {
    foreach (['semana', 'sab', 'dom'] as $c) if (($h[$c]['sit'] ?? '') !== '24h') return false;
    return true;
}
function web_abre_domingo(array $h): bool { return in_array($h['dom']['sit'] ?? '', ['aberto', '24h'], true); }
/** Fecha às 22h ou depois (ou depois da meia-noite) de segunda a sexta. */
function web_abre_noite(array $h): bool {
    $s = $h['semana'] ?? null;
    if (!$s) return false;
    if ($s['sit'] === '24h') return true;
    return $s['sit'] === 'aberto' && ($s['fecha'] >= '22:00' || $s['fecha'] < $s['abre']);
}
/** Farmácias e drogarias da cidade com horário: ['24h' => [...], 'domingo' => [...] (sem as 24h), 'noite' => [...] (sem as de antes)]. */
function web_farmacias(string $cidade): array {
    $com = web_com_horario($cidade, WEB_TIPOS_FARMACIA);
    $r = ['24h' => [], 'domingo' => [], 'noite' => []];
    foreach ($com as $p) {
        if (web_e_24h($p['h'])) $r['24h'][] = $p;
        elseif (web_abre_domingo($p['h'])) $r['domingo'][] = $p;
        elseif (web_abre_noite($p['h'])) $r['noite'][] = $p;
    }
    return $r;
}
/** "as 37 farmácias e as 16 drogarias de X", com link para a lista de cada tipo. */
function web_links_farmacias(string $cidade): string {
    $partes = [];
    foreach (WEB_TIPOS_FARMACIA as $t) {
        $n = (int)valor('SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel($cidade) . ' AND p.tipo = ?', [$t]);
        if ($n) $partes[] = '<a href="' . h(url_tipo($cidade, 'saude', $t)) . '">' . ($n === 1 ? 'a ' . mb_strtolower($t) : 'as ' . web_num($n) . ' ' . mb_strtolower($t) . 's') . '</a>';
    }
    return web_e($partes) . ' de ' . h(CIDADES[$cidade][0]);
}
/** Nomes com link: "A, B e C". */
function web_nomes_links(array $lista, int $max = 4): string {
    return web_e(array_map(fn($p) => '<a href="' . h(url_ficha($p)) . '">' . h(nome_curto($p['nome'])) . '</a>' . ($p['bairro'] ? ' (' . h($p['bairro']) . ')' : ''), array_slice($lista, 0, $max)));
}
/** Resposta de "Tem farmácia 24 horas em X?" só com o que as fichas informam. */
function web_resposta_farmacia_24h(string $cidade): string {
    $nome = CIDADES[$cidade][0];
    $f = web_farmacias($cidade);
    $todas = web_links_farmacias($cidade);
    if ($f['24h']) return (count($f['24h']) > 1 ? 'Sim. Informaram atendimento 24 horas, todos os dias: ' : 'Sim. Informou atendimento 24 horas, todos os dias: ')
        . web_nomes_links($f['24h']) . '. Antes de ir, confirme pelo telefone. Veja também ' . $todas . '.';
    $r = 'Nenhuma farmácia de ' . h($nome) . ' informou atendimento 24 horas no Localiza Fácil até agora.';
    if ($f['domingo']) $r .= ' Abrem no domingo: ' . web_nomes_links($f['domingo']) . '.';
    elseif ($f['noite']) $r .= ' Ficam abertas até mais tarde: ' . web_nomes_links($f['noite']) . '.';
    return $r . ' Para a farmácia de plantão, ligue antes. Veja ' . $todas . '.';
}
/** Resposta de "Quais mercados abrem no domingo em X?", ou null se nenhum mercado informou o horário. */
function web_resposta_mercados_domingo(string $cidade): ?string {
    // só mercado de verdade (a área Mercados também tem loja de bebidas e distribuidora)
    $com = array_values(array_filter(web_com_horario($cidade, null, 'mercados'), fn($p) => preg_match('/mercado|mercearia|hortifr|sacol|emp[óo]rio|atacad/iu', (string)$p['tipo'])));
    if (!$com) return null;
    $dom = array_values(array_filter($com, fn($p) => web_abre_domingo($p['h'])));
    if (!$dom) return 'Dos ' . web_num(count($com)) . ' mercados que informaram o horário, nenhum abre no domingo.';
    return web_nomes_links($dom, 5) . (count($dom) > 5 ? ', entre outros (' . web_num(count($dom)) . ' no total)' : '') . (count($dom) === 1 ? ' abre' : ' abrem') . ' no domingo, pelo horário informado. '
        . 'Nas listas, o botão <i>Aberto agora</i> mostra quem está atendendo neste momento.';
}
/** Quadro "Farmácias 24 horas" da página da cidade (só aparece se alguma farmácia tem horário). */
function web_quadro_farmacias(string $cidade): string {
    $nome = CIDADES[$cidade][0];
    $f = web_farmacias($cidade);
    if (!$f['24h'] && !$f['domingo'] && !$f['noite']) return '';
    $lista = $f['24h'] ?: ($f['domingo'] ?: $f['noite']);
    $titulo = $f['24h'] ? 'Farmácias 24 horas em ' . $nome : ($f['domingo'] ? 'Farmácias abertas no domingo em ' . $nome : 'Farmácias abertas até mais tarde em ' . $nome);
    $intro = $f['24h'] ? (count($f['24h']) > 1 ? 'Informaram' : 'Informou') . ' atendimento 24 horas, todos os dias. Antes de ir, confirme pelo telefone.'
        : 'Nenhuma farmácia de ' . $nome . ' informou atendimento 24 horas até agora. ' . (count($lista) > 1 ? 'Estas ' : 'Esta ')
          . ($f['domingo'] ? (count($lista) > 1 ? 'abrem no domingo' : 'abre no domingo') : (count($lista) > 1 ? 'ficam abertas até mais tarde' : 'fica aberta até mais tarde')) . ', pelo horário informado.';
    $extra = $f['24h'] && $f['domingo'] ? '<p class="intro">Também abrem no domingo: ' . web_nomes_links($f['domingo'], 6) . '.</p>' : '';
    return '<section><div class="conteudo"><h2>💊 ' . h($titulo) . '</h2><p class="intro">' . h($intro) . '</p>'
        . web_cartoes(array_slice($lista, 0, 6), 'site_cidade') . $extra
        . '<p class="intro">Veja ' . web_links_farmacias($cidade) . '.</p></div></section>';
}
