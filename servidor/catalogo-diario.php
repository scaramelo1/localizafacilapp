<?php
// 22/11 (passo 45): 1x por dia refaz o índice do catálogo (busca, número de itens): oferta com data vencida sai sozinha
// da busca e do selo do cartão (na ficha ela já some na hora). Cron de madrugada (/etc/cron.d/guia-novo).
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';
$n = 0;
foreach (linhas("SELECT id FROM profissionais WHERE cardapio IS NOT NULL") as $l) { catalogo_indexar((int)$l['id']); $n++; }
if ($n) web_cache_limpar();
echo date('Y-m-d H:i') . " catálogos reindexados: $n\n";
