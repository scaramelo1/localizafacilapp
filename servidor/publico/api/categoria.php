<?php
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$id = (int)($_GET['id'] ?? 0);
$c = linha('SELECT id, nome, icone, cor FROM categorias WHERE id = ? AND ativa = 1', [$id]) ?? erro('Área não encontrada.', 404);
$cat = ['id' => (int)$c['id'], 'nome' => $c['nome'], 'icone' => $c['icone'], 'cor' => $c['cor']];
// 23/11 (passo 77): app 1.17+ pede em páginas (?pagina=1&por=50, com tipo, q e aberto). Sem "pagina" = como antes
// (lista inteira), para os apps antigos continuarem funcionando. Saúde em Poços eram 1.102 fichas (540 KB) de uma vez.
if (isset($_GET['pagina'])) {
    $pagina = max(1, (int)$_GET['pagina']);
    $por = min(100, max(10, (int)($_GET['por'] ?? 50)));
    $tipo = trim(mb_substr((string)($_GET['tipo'] ?? ''), 0, 80));
    $termo = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 60));
    [$lista, $total] = da_categoria_pagina($id, cidade_pedida(), $pagina, $por, $tipo !== '' ? $tipo : null, $termo, !empty($_GET['aberto']));
    if ($pagina === 1) contar_aparicoes($lista, 'categoria');
    $resp = ['categoria' => $cat, 'profissionais' => $lista, 'total' => $total, 'pagina' => $pagina, 'temMais' => $pagina * $por < $total];
    if ($pagina === 1) {   // cabeçalho e filtros só na 1ª página
        $resp['tipos'] = tipos_da_categoria($id, cidade_pedida());
        $resp['totalArea'] = (int)valor('SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel(cidade_pedida()) . ' AND p.categoria_id = ?', [$id]);
    }
    responder($resp);
}
$lista = da_categoria($id, cidade_pedida());
contar_aparicoes($lista, 'categoria');
responder([
    'categoria' => $cat,
    'tipos' => tipos_da_categoria($id, cidade_pedida()),
    'profissionais' => $lista,
]);
