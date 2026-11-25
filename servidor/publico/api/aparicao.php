<?php
// 25/11 (pedido do usuário): fichas mostradas numa lista do site (cidade, área/tipo, busca, outras opções na ficha).
// O script do rodapé (pagina_fim) manda as marcas que a página trouxe (aparicao_marca); robôs e equipe não contam.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/numeros.php';
metodo('POST');
$c = corpo();
gravar_aparicoes_site(is_array($c['m'] ?? null) ? $c['m'] : []);
responder(['ok' => true]);
