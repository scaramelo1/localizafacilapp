<?php
// 21/11 (passo 31): a página do site abriu num navegador de verdade (o <script> que registrar_entrada_site() põe na
// página chama aqui). Só então a entrada conta: robôs que só baixam o HTML ficam de fora, e a equipe também (nao_contar).
// O sinal (assinado pelo servidor, vale 2 horas) impede contar origem ou página inventadas.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/numeros.php';
metodo('POST');
$c = corpo();
$d = ['origem' => (string)($c['origem'] ?? ''), 'caminho' => (string)($c['caminho'] ?? ''), 'pagina' => (string)($c['pagina'] ?? ''),
    'cidade' => isset($c['cidade']) && $c['cidade'] !== '' ? (string)$c['cidade'] : null, 'pre' => (int)($c['pre'] ?? 0), 't' => (int)($c['t'] ?? 0)];
if (!hash_equals(entrada_sinal($d), (string)($c['sinal'] ?? ''))) erro('Sinal inválido.', 400);
if ($d['t'] < time() - 7200 || $d['t'] > time() + 60) responder(['ok' => true]);   // página velha (aba aberta há horas): não conta
gravar_entrada($d);
responder(['ok' => true]);
