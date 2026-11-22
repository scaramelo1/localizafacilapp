<?php
// 22/11 (passo 45): "Pedir" de um item do catálogo (site e app). Conta o item (catalogo_pedidos) e um toque no WhatsApp.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/numeros.php';
metodo('POST');
$c = corpo();
$p = profissional_visivel((int)($c['id'] ?? 0)) ?? erro('Comércio não encontrado.', 404);
if (nao_contar()) responder(['ok' => true]);
$origem = ($c['origem'] ?? '') === 'web' ? 'web' : 'app';
// 22/11 (passo 47): pedido do carrinho manda vários itens de uma vez (cada um conta 1; o WhatsApp conta 1 toque só)
$lista = isset($c['itens']) && is_array($c['itens']) ? array_slice(array_unique(array_map('strval', $c['itens'])), 0, 60) : [(string)($c['item'] ?? '')];
foreach ($lista as $item) catalogo_contar_pedido((int)$p['id'], $item, $origem);
// toque no site: a origem de quando a pessoa chegou (cookie de 30 min), como em api/contato.php
$o = $origem === 'web' && in_array($_COOKIE['af_origem'] ?? '', ORIGENS_SITE, true) ? (string)$_COOKIE['af_origem'] : ($origem === 'web' ? 'web' : 'app');
contar_contato((int)$p['id'], 'whatsapp', $o);
responder(['ok' => true]);
