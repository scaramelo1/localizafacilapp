<?php
// 22/11 (passo 45): "Pedir" de um item do catálogo (site e app). Conta o item (catalogo_pedidos) e um toque no WhatsApp.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/numeros.php';
metodo('POST');
$c = corpo();
$p = profissional_visivel((int)($c['id'] ?? 0)) ?? erro('Comércio não encontrado.', 404);
if (nao_contar()) responder(['ok' => true]);
$origem = ($c['origem'] ?? '') === 'web' ? 'web' : 'app';
catalogo_contar_pedido((int)$p['id'], (string)($c['item'] ?? ''), $origem);
// toque no site: a origem de quando a pessoa chegou (cookie de 30 min), como em api/contato.php
$o = $origem === 'web' && in_array($_COOKIE['af_origem'] ?? '', ORIGENS_SITE, true) ? (string)$_COOKIE['af_origem'] : ($origem === 'web' ? 'web' : 'app');
contar_contato((int)$p['id'], 'whatsapp', $o);
responder(['ok' => true]);
