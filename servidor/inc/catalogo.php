<?php
// Catálogo da ficha (22/11, passos 42–45, pedido do usuário). Só Destaque em dia. Um catálogo só, que muda de cara pelo
// segmento: Cardápio (comida), Produtos (roupas, farmácia, ótica), Ofertas (mercado), Bebidas, Peças.
// Guardado em profissionais.cardapio (JSON):
//   {"atualizado":"2026-11-22","validade_ate":"2026-11-29","secoes":[{"nome":"Lanches","itens":[
//     {"id":"a1b2c3","nome":"X-Tudo","desc":"...","preco":"34.90","preco_de":"39.90","a_partir":true,"unidade":"kg",
//      "etiquetas":["P","M"],"destaque":true,"esgotado":false,"ate":"2026-11-29","foto":"c103616-....jpg"}]}]}
// "ate" (do item) ou "validade_ate" (do catálogo todo) vencidos: o item some sozinho (ofertas de mercado).
// profissionais.catalogo_busca/catalogo_itens/catalogo_em: índice para a busca e o cartão (catalogo_indexar()).
declare(strict_types=1);
require_once __DIR__ . '/base.php';

const CATALOGO_MODELOS = [
    'comida' => ['carrinho' => true, 'aba' => '🍔 Cardápio', 'curto' => 'Cardápio', 'titulo' => 'Cardápio', 'botao' => 'Pedir pelo WhatsApp',
        'aviso' => 'Preços informados pelo comércio e podem mudar. Confirme na hora de pedir.',
        'secoes' => ['Lanches', 'Porções', 'Bebidas', 'Sobremesas'], 'etiquetas' => '', 'unidade' => false, 'validade' => false],
    'roupas' => ['aba' => '👕 Produtos', 'curto' => 'Produtos', 'titulo' => 'Produtos e coleção', 'botao' => 'Perguntar se tem pelo WhatsApp',
        'aviso' => 'Tamanhos e cores sujeitos à disponibilidade. Pergunte pelo WhatsApp antes de ir até a loja.',
        'secoes' => ['Feminino', 'Masculino', 'Infantil', 'Acessórios'], 'etiquetas' => 'Tamanhos e cores (separe por vírgula)', 'unidade' => false, 'validade' => false],
    'mercado' => ['carrinho' => true, 'aba' => '🛒 Ofertas', 'curto' => 'Ofertas', 'titulo' => 'Ofertas', 'botao' => 'Fazer pedido pelo WhatsApp',
        'aviso' => 'Ofertas enquanto durar o estoque. Preços informados pelo mercado.',
        'secoes' => ['Açougue', 'Hortifrúti', 'Mercearia', 'Bebidas', 'Limpeza'], 'etiquetas' => '', 'unidade' => true, 'validade' => true],
    'bebidas' => ['carrinho' => true, 'aba' => '🍺 Bebidas', 'curto' => 'Bebidas', 'titulo' => 'Bebidas e preços', 'botao' => 'Pedir pelo WhatsApp',
        'aviso' => 'Venda proibida para menores de 18 anos. Beba com moderação. Preços informados pelo comércio.',
        'secoes' => ['Cervejas', 'Destilados', 'Vinhos', 'Sem álcool', 'Gelo e carvão'], 'etiquetas' => 'Detalhes (ex.: Gelada, Puro malte)', 'unidade' => true, 'validade' => false],
    'pecas' => ['aba' => '🔧 Peças', 'curto' => 'Peças', 'titulo' => 'Peças e serviços', 'botao' => 'Consultar peça pelo WhatsApp',
        'aviso' => 'Confirme se a peça serve no modelo e no ano do seu veículo antes de comprar. Mande a placa ou o chassi pelo WhatsApp.',
        'secoes' => ['Freios', 'Motor e filtros', 'Suspensão', 'Elétrica', 'Serviços'], 'etiquetas' => 'Serve em (carros, separe por vírgula)', 'unidade' => false, 'validade' => false],
    'farmacia' => ['aba' => '💊 Produtos', 'curto' => 'Produtos', 'titulo' => 'Produtos e serviços', 'botao' => 'Falar com a farmácia no WhatsApp',
        'aviso' => 'Não anunciamos medicamentos. Para remédios, fale com o farmacêutico. Medicamentos com receita só na farmácia, com a receita.',
        'secoes' => ['Serviços', 'Higiene e beleza', 'Bebês', 'Dermocosméticos'], 'etiquetas' => 'Detalhes (ex.: tamanhos)', 'unidade' => false, 'validade' => false],
    'otica' => ['aba' => '👓 Produtos', 'curto' => 'Produtos', 'titulo' => 'Armações, lentes e serviços', 'botao' => 'Perguntar pelo WhatsApp',
        'aviso' => 'Lentes de grau feitas a partir da receita do seu oftalmologista. Preços de referência; o valor final depende da receita.',
        'secoes' => ['Armações', 'Lentes', 'Óculos de sol', 'Serviços'], 'etiquetas' => 'Detalhes (ex.: Feminina, Infantil)', 'unidade' => false, 'validade' => false],
    'geral' => ['aba' => '🛍️ Produtos', 'curto' => 'Produtos', 'titulo' => 'Produtos e serviços', 'botao' => 'Perguntar pelo WhatsApp',
        'aviso' => 'Preços e disponibilidade informados pelo comércio e podem mudar.',
        'secoes' => ['Produtos', 'Serviços'], 'etiquetas' => 'Detalhes (separe por vírgula)', 'unidade' => false, 'validade' => false],
];
// 'carrinho' => true (comida, mercado, bebidas): escolhe vários itens e manda o pedido inteiro no WhatsApp (passo 47)
const CATALOGO_MAX_ITENS = 150;
const CATALOGO_MAX_SECOES = 20;
const CATALOGO_MAX_FOTOS = 60;

/** Segmento da ficha (pelo tipo; senão pela área). $p precisa de tipo, nome e categoria_slug (ou categoria_id). */
function catalogo_modelo(array $p): array {
    $slug = (string)($p['categoria_slug'] ?? '');
    if ($slug === '' && !empty($p['categoria_id'])) $slug = (string)valor('SELECT slug FROM categorias WHERE id = ?', [(int)$p['categoria_id']]);
    $t = mb_strtolower((string)($p['tipo'] ?? '') . ' ' . (string)($p['nome'] ?? ''));
    $chave = match (true) {
        (bool)preg_match('/farm[aá]c|drogar/u', $t) => 'farmacia',
        (bool)preg_match('/[óo]tica/u', $t) => 'otica',
        (bool)preg_match('/autope[çc]|pe[çc]as para|pe[çc]as automot|auto ?pe[çc]/u', $t) => 'pecas',
        (bool)preg_match('/bebida|adega|cervej|distribuidora/u', $t) => 'bebidas',
        (bool)preg_match('/supermerc|mercad|mercearia|hortifr|conveni[êe]ncia|a[çc]ougue/u', $t) => 'mercado',
        (bool)preg_match('/roupa|moda|boutique|cal[çc]ado|vestu/u', $t) => 'roupas',
        $slug === 'alimentacao' => 'comida',
        $slug === 'moda' => 'roupas',
        $slug === 'mercados' => 'mercado',
        default => 'geral',
    };
    return CATALOGO_MODELOS[$chave] + ['chave' => $chave];
}

/** JSON guardado -> dados completos (para o editor), sem tirar nada. */
function catalogo_dados(?string $json): array {
    $d = $json ? json_decode($json, true) : null;
    return is_array($d) ? $d + ['secoes' => []] : ['secoes' => []];
}

/** Item ainda vale hoje? (oferta com data vencida some) */
function catalogo_item_vale(array $it, string $validade_ate, ?string $hoje = null): bool {
    $hoje ??= date('Y-m-d');
    $ate = (string)($it['ate'] ?? '') ?: $validade_ate;
    return $ate === '' || $ate >= $hoje;
}

/**
 * Seções com os itens que aparecem hoje (sem nome vazio e sem oferta vencida). Esgotado continua, marcado.
 */
function cardapio_ler(?string $json): array {
    $d = catalogo_dados($json);
    $val = (string)($d['validade_ate'] ?? '');
    $secoes = [];
    foreach ((array)$d['secoes'] as $s) {
        $itens = array_values(array_filter((array)($s['itens'] ?? []), fn($i) => trim((string)($i['nome'] ?? '')) !== '' && catalogo_item_vale($i, $val)));
        if ($itens) $secoes[] = ['nome' => trim((string)($s['nome'] ?? '')) ?: 'Produtos', 'itens' => $itens];
    }
    return $secoes;
}
/** Data da última mudança e validade das ofertas (para mostrar no topo). */
function cardapio_info(?string $json): array {
    $d = catalogo_dados($json);
    $val = (string)($d['validade_ate'] ?? '');
    return ['atualizado' => (string)($d['atualizado'] ?? ''), 'validade_ate' => $val >= date('Y-m-d') ? $val : '',
        'validade' => trim((string)($d['validade'] ?? ''))];   // texto livre (fichas de teste antigas)
}
function cardapio_preco(mixed $v): string {
    if ($v === null || $v === '') return '';
    if ((float)str_replace(',', '.', (string)$v) == 0) return 'Grátis';
    return 'R$ ' . number_format((float)str_replace(',', '.', (string)$v), 2, ',', '.');
}
/** "dd/mm" de uma data Y-m-d. */
function catalogo_dia(string $d): string { return $d ? date('d/m', strtotime($d)) : ''; }

/** Texto do WhatsApp do "Pedir este item". */
function catalogo_msg_item(array $it, string $comercio): string {
    $preco = cardapio_preco($it['preco'] ?? null);
    return 'Olá! Vi no Localiza Fácil: ' . trim((string)$it['nome']) . ($preco !== '' && $preco !== 'Grátis' ? " ($preco)" : '') . '. Ainda tem?';
}

/**
 * Atualiza o índice da ficha (busca, número de itens e data) a partir do JSON. Chamar depois de salvar e 1x por dia
 * (catalogo-diario.php), porque oferta vencida sai sozinha.
 */
function catalogo_indexar(int $profissional_id): void {
    $json = valor('SELECT cardapio FROM profissionais WHERE id = ?', [$profissional_id]);
    $secoes = cardapio_ler(is_string($json) ? $json : null);
    $nomes = [];
    $n = 0;
    foreach ($secoes as $s) foreach ($s['itens'] as $it) {
        if (!empty($it['esgotado'])) continue;
        $n++;
        $nomes[] = trim((string)$it['nome']);
    }
    // " | " separa os itens: a busca procura começo de palavra ("% x-tudo")
    $busca = $nomes ? ' ' . mb_substr(implode(' | ', $nomes), 0, 8000) . ' ' : null;
    $em = catalogo_dados(is_string($json) ? $json : null)['atualizado'] ?? null;
    q('UPDATE profissionais SET catalogo_busca = ?, catalogo_itens = ?, catalogo_em = ? WHERE id = ?', [$busca, $n, $em ?: null, $profissional_id]);
}

/**
 * Normaliza o que veio do editor (painel do dono ou admin) e grava. $fotos = [item_id => nome do arquivo] já salvos.
 * Devolve [dados gravados, erro].
 */
function catalogo_salvar(int $profissional_id, array $secoes_post, array $itens_post, string $validade_ate, array $fotos): array {
    $antes = catalogo_dados(valor('SELECT cardapio FROM profissionais WHERE id = ?', [$profissional_id]));
    $fotos_antes = [];
    foreach ($antes['secoes'] as $s) foreach ((array)($s['itens'] ?? []) as $it) if (!empty($it['foto'])) $fotos_antes[(string)$it['id']] = (string)$it['foto'];
    $preco = function ($v): ?string {
        $v = trim(str_replace(['R$', ' '], '', (string)$v));
        if ($v === '') return null;
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $v)) $v = str_replace('.', '', $v);   // 1.234,56
        $v = str_replace(',', '.', $v);
        return is_numeric($v) && (float)$v >= 0 && (float)$v < 1000000 ? number_format((float)$v, 2, '.', '') : null;
    };
    $data = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? (string)$v : '';
    $secoes = [];
    foreach ($secoes_post as $chave => $nome) {
        if (count($secoes) >= CATALOGO_MAX_SECOES) break;
        $secoes[(string)$chave] = ['nome' => mb_substr(trim((string)$nome), 0, 60), 'itens' => []];
    }
    $total = 0;
    foreach ($itens_post as $id => $it) {
        $id = (string)$id;
        if (!preg_match('/^[a-z0-9]{6,16}$/', $id) || !is_array($it)) continue;
        $nome = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($it['nome'] ?? ''))), 0, 120);
        if ($nome === '') continue;
        $sec = (string)($it['secao'] ?? '');
        if (!isset($secoes[$sec])) continue;
        if (++$total > CATALOGO_MAX_ITENS) return [null, 'O catálogo pode ter até ' . CATALOGO_MAX_ITENS . ' itens.'];
        $p = $preco($it['preco'] ?? '');
        if (trim((string)($it['preco'] ?? '')) !== '' && $p === null) return [null, "Preço de \"$nome\": use só números, como 24,90."];
        $item = ['id' => $id, 'nome' => $nome];
        if (($d = mb_substr(trim((string)($it['desc'] ?? '')), 0, 300)) !== '') $item['desc'] = $d;
        if ($p !== null) $item['preco'] = $p;
        if (($pd = $preco($it['preco_de'] ?? '')) !== null && $p !== null && (float)$pd > (float)$p) $item['preco_de'] = $pd;
        if (!empty($it['a_partir'])) $item['a_partir'] = true;
        if (($u = mb_substr(trim((string)($it['unidade'] ?? '')), 0, 20)) !== '') $item['unidade'] = ltrim($u, '/');
        $et = array_values(array_filter(array_map(fn($e) => mb_substr(trim($e), 0, 30), explode(',', (string)($it['etiquetas'] ?? ''))), fn($e) => $e !== ''));
        if ($et) $item['etiquetas'] = array_slice($et, 0, 15);
        if (!empty($it['destaque'])) $item['destaque'] = true;
        if (!empty($it['esgotado'])) $item['esgotado'] = true;
        if ($a = $data($it['ate'] ?? '')) $item['ate'] = $a;
        $foto = $fotos[$id] ?? (empty($it['tirar_foto']) ? ($fotos_antes[$id] ?? null) : null);
        if ($foto) $item['foto'] = $foto;
        $secoes[$sec]['itens'][] = $item;
    }
    $secoes = array_values(array_filter($secoes, fn($s) => $s['itens'] || $s['nome'] !== ''));
    $d = ['atualizado' => date('Y-m-d'), 'secoes' => $secoes];
    if ($v = $data($validade_ate)) $d['validade_ate'] = $v;
    q('UPDATE profissionais SET cardapio = ? WHERE id = ?', [json_encode($d, JSON_UNESCAPED_UNICODE), $profissional_id]);
    catalogo_indexar($profissional_id);
    // fotos que saíram do catálogo: apaga o arquivo
    $agora = [];
    foreach ($secoes as $s) foreach ($s['itens'] as $it) if (!empty($it['foto'])) $agora[$it['foto']] = true;
    foreach ($fotos_antes as $f) if (!isset($agora[$f]) && preg_match('/^[a-z0-9-]{8,80}\.(jpg|png|webp)$/', $f)) @unlink(config()['dados_dir'] . '/fotos/' . $f);
    if (function_exists('web_cache_limpar')) web_cache_limpar();
    return [$d, null];
}

/** Catálogo para o app (prof_completo): null se não é Destaque ou não tem item. */
function catalogo_api(array $p): ?array {
    if (empty($p['destaque_pago'])) return null;
    $secoes = cardapio_ler($p['cardapio'] ?? null);
    if (!$secoes) return null;
    $mod = catalogo_modelo($p);
    $info = cardapio_info($p['cardapio'] ?? null);
    return [
        'aba' => $mod['aba'], 'titulo' => $mod['titulo'], 'aviso' => $mod['aviso'], 'botao' => $mod['botao'],
        // 22/11 (passo 49): pedido de vários itens no app (comida, mercado, bebidas com WhatsApp)
        'carrinho' => !empty($mod['carrinho']) && !empty($p['whatsapp']),
        'whatsapp' => !empty($p['whatsapp']) ? link_whatsapp((string)$p['whatsapp']) : null,
        'atualizado' => $info['atualizado'] ? catalogo_dia($info['atualizado']) : null,
        'validade' => $info['validade_ate'] ? 'Ofertas válidas até ' . catalogo_dia($info['validade_ate']) : ($info['validade'] ?: null),
        'secoes' => array_map(fn($s) => ['nome' => $s['nome'], 'itens' => array_map(fn($it) => [
            'id' => (string)($it['id'] ?? md5((string)$it['nome'])), 'nome' => (string)$it['nome'], 'desc' => $it['desc'] ?? null,
            'preco' => isset($it['preco']) ? cardapio_preco($it['preco']) : null,
            'precoDe' => !empty($it['preco_de']) ? cardapio_preco($it['preco_de']) : null,
            'aPartir' => !empty($it['a_partir']), 'unidade' => $it['unidade'] ?? null, 'etiquetas' => $it['etiquetas'] ?? [],
            // preço em número para somar o pedido (null: sem preço fixo, vai como "a confirmar")
            'precoNum' => isset($it['preco']) && empty($it['a_partir']) ? round((float)$it['preco'], 2) : null,
            'destaque' => !empty($it['destaque']), 'esgotado' => !empty($it['esgotado']),
            'foto' => !empty($it['foto']) ? foto_url((string)$it['foto']) : null,
            'mensagem' => catalogo_msg_item($it, (string)$p['nome']),
            // link do WhatsApp já com o item escrito (null: comércio sem WhatsApp ou item esgotado)
            'pedir' => !empty($p['whatsapp']) && empty($it['esgotado']) ? link_whatsapp((string)$p['whatsapp'], catalogo_msg_item($it, (string)$p['nome'])) : null,
        ], $s['itens'])], $secoes),
    ];
}

/** Para a busca: o 1º item do catálogo que tem as palavras procuradas (grupos de busca_grupos). */
function catalogo_achou(?string $indice, array $grupos): ?string {
    if (!$indice || !$grupos) return null;
    foreach (explode(' | ', trim($indice)) as $nome) {
        $n = ' ' . busca_normalizar($nome) . ' ';
        $todos = true;
        foreach ($grupos as $alts) {
            $um = false;
            foreach ($alts as $a) if (str_contains($n, ' ' . busca_normalizar((string)$a))) { $um = true; break; }
            if (!$um) { $todos = false; break; }
        }
        if ($todos) return $nome;
    }
    return null;
}

/** "Pedir este item": conta por item e por dia (o toque no WhatsApp conta também em contatos). */
function catalogo_contar_pedido(int $profissional_id, string $item_id, string $origem): void {
    if (!preg_match('/^[a-z0-9]{6,16}$/', $item_id) && !preg_match('/^[a-f0-9]{32}$/', $item_id)) return;
    $item = null;
    foreach (cardapio_ler(valor('SELECT cardapio FROM profissionais WHERE id = ?', [$profissional_id])) as $s)
        foreach ($s['itens'] as $it) if ((string)($it['id'] ?? md5((string)$it['nome'])) === $item_id) $item = $it;
    if (!$item) return;
    q('INSERT INTO catalogo_pedidos (profissional_id, item_id, item_nome, dia, origem, total) VALUES (?, ?, ?, CURDATE(), ?, 1)
        ON DUPLICATE KEY UPDATE total = total + 1, item_nome = VALUES(item_nome)', [$profissional_id, $item_id, mb_substr((string)$item['nome'], 0, 120), $origem === 'web' ? 'web' : 'app']);
}

/** Itens mais pedidos pelo WhatsApp desde $desde (painel do dono e admin). */
function catalogo_mais_pedidos(int $profissional_id, string $desde, int $max = 10): array {
    try {
        return linhas('SELECT item_nome, SUM(total) AS total FROM catalogo_pedidos WHERE profissional_id = ? AND dia >= ?
            GROUP BY item_id, item_nome ORDER BY total DESC LIMIT ' . (int)$max, [$profissional_id, $desde]);
    } catch (PDOException) { return []; }
}
