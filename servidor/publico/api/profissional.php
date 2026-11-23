<?php
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$p = profissional_visivel((int)($_GET['id'] ?? 0));
// 22/11 (passo 49): prévia da equipe no app (conta de admin): ficha fora do app (de teste) abre, com o catálogo
// como se fosse Destaque, para conferir. Ninguém de fora vê.
if (!$p && ($id = (int)($_GET['id'] ?? 0)) && da_equipe()) {
    $p = linha('SELECT p.*, 1 AS destaque_pago, ' . SQL_TEM_DONO . ' AS tem_dono, c.nome AS categoria, c.slug AS categoria_slug, c.icone, c.cor
        FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE p.id = ?', [$id]);
}
$p ?? erro('Profissional não encontrado.', 404);
$p = ficha_publica($p);   // 23/11 (passo 72): sem os campos do Básico se não pagou
// origem = de onde a pessoa abriu (app 1.5.0+); 'nenhuma' = recarga da mesma tela ou tela de avaliar (não conta).
// Sem origem = app antigo: conta como 'app'.
$origem = isset($_GET['origem']) ? (string)$_GET['origem'] : null;
if ($origem !== 'nenhuma') contar_visita((int)$p['id'], origem_valida($origem));
$r = prof_completo($p, ($u = usuario_app()) ? (int)$u['id'] : null);
// 23/11 (passo 60): o mesmo conteúdo próprio da ficha do site (passo 55). Apps antigos ignoram estes campos.
require_once __DIR__ . '/../../inc/web.php';
$r['sobreSite'] = !$p['descricao'] && !empty($p['sobre_site']) ? (string)$p['sobre_site'] : null;
$r['sobreSiteDe'] = $r['sobreSite'] && !empty($p['sobre_site_url'])
    ? preg_replace('#^www\.#', '', (string)parse_url((string)$p['sobre_site_url'], PHP_URL_HOST)) : null;
$r['localizacao'] = web_local_texto($p, nome_cidade((string)$p['cidade'])) ?: null;
$perto = web_perto($p);
$r['perto'] = $perto ? [
    'titulo' => $perto['modo'] === 'perto' ? 'Perto de ' . nome_curto((string)$p['nome']) : 'Também na ' . $perto['rua'],
    'itens' => array_map(fn($x) => [
        'id' => (int)$x['id'], 'nome' => nome_curto((string)$x['nome']), 'tipo' => (string)($x['tipo'] ?: $x['categoria']),
        'icone' => icone_comercio($x), 'cor' => cor_comercio($x),
        'onde' => $perto['modo'] === 'perto' ? web_distancia_txt((float)$x['distancia']) : 'nº ' . (int)$x['numero'],
    ], $perto['itens']),
] : null;
responder(['profissional' => $r]);
