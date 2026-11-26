<?php
// "Quero minha Cidade!": a pessoa pede o Localiza Fácil na cidade dela (não precisa de conta). {cidade, uf}
// O estado e a cidade precisam existir na lista do IBGE (dados/municipios.json); grava o nome oficial.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/guia.php';
metodo('POST');
$uf = strtoupper(trim(campo('uf', 10)));
if (!isset(municipios()['estados'][$uf])) erro('Escolha o estado.');
$cidade = municipio_oficial($uf, campo('cidade', 80)) ?? erro('Escolha a cidade na lista.');
// 25/11: robôs (o teste automático da Google Play pediu Itajubá ao revisar o app) não contam nem avisam
require_once __DIR__ . '/../../inc/numeros.php';
if (e_robo()) responder(['ok' => true]);
if ((int)valor('SELECT COUNT(*) FROM cidades_pedidas WHERE ip = ? AND criado_em > NOW() - INTERVAL 1 DAY', [ip()]) >= 5)
    responder(['ok' => true]);   // não conta de novo, mas não incomoda a pessoa
require_once __DIR__ . '/../../inc/cidades-pedidas.php';
$dados = ['cidade' => $cidade, 'uf' => $uf, 'ip' => ip()];
if (cidades_pedidas_completas()) {   // 25/11: quem pediu (se entrou na conta) e de onde, para a página do admin
    $u = usuario_app();
    $dados['usuario_id'] = $u ? (int)$u['id'] : null;
    $dados['origem'] = ($_SERVER['HTTP_X_AF_WEB'] ?? '') === '1' ? 'site' : 'app';
}
inserir('cidades_pedidas', $dados);
require_once __DIR__ . '/../../inc/avisos.php';
$total = (int)valor('SELECT COUNT(*) FROM cidades_pedidas WHERE cidade = ? AND uf = ?', [$cidade, $uf]);
avisar_admins('Quero minha Cidade!', "Pediram o Localiza Fácil em $cidade ($uf)" . ($total > 1 ? " ({$total}º pedido)" : '') . '. Veja em Admin > Comércios > Quero minha Cidade!');
responder(['ok' => true]);
