<?php
// 23/11 (avaliação do site, item 1): tira do banco o endereço que não é endereço ("Zap ( 18", "5369+FV", "R.", "38"...),
// regra em endereco_invalido() (inc/limpeza.php). Só ficha SEM dono e não editada pelo admin. A ficha passa a mostrar
// "Endereço não informado" com o bairro; o "Como chegar" continua pelo ponto do mapa.
//   php servidor/limpar-enderecos.php            → só mostra (nada é gravado)
//   php servidor/limpar-enderecos.php --aplicar  → grava e guarda um arquivo para desfazer em <dados>/backups/
// O importador (importar-leads.php) já recusa esses endereços nas listas novas.
declare(strict_types=1);
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';
require_once __DIR__ . '/inc/limpeza.php';
require_once __DIR__ . '/inc/moderacao.php';   // editados_pelo_admin()
if (PHP_SAPI !== 'cli') exit("Rode pelo terminal.\n");
$aplicar = in_array('--aplicar', $argv, true);

$do_admin = editados_pelo_admin();
$tirar = [];
$conta = [];
foreach (linhas('SELECT p.id, p.nome, p.endereco, ' . SQL_TEM_DONO . " AS tem_dono FROM profissionais p WHERE p.endereco IS NOT NULL AND p.endereco <> ''") as $p) {
    if (!endereco_invalido($p['endereco']) || $p['tem_dono'] || isset($do_admin[(int)$p['id']])) continue;
    $tirar[] = $p;
    $conta[$p['endereco']] = ($conta[$p['endereco']] ?? 0) + 1;
}
arsort($conta);
echo 'Endereços inválidos a tirar: ' . count($tirar) . " fichas\n";
foreach ($conta as $e => $n) echo "  {$n}x $e\n";
if (!$tirar) exit;
if (!$aplicar) exit("\nNada foi gravado. Para gravar: php servidor/limpar-enderecos.php --aplicar\n");

$dir = config()['dados_dir'] . '/backups';
@mkdir($dir, 0750, true);
$arq = "$dir/limpar-enderecos-" . date('Ymd-His') . '.sql';
$pdo = db();
$sql = '-- Desfaz a limpeza de endereços de ' . date('d/m/Y H:i') . " (mysql guia_novo < este arquivo)\n";
foreach ($tirar as $p) $sql .= 'UPDATE profissionais SET endereco = ' . $pdo->quote($p['endereco']) . ', atualizado_em = atualizado_em WHERE id = ' . (int)$p['id'] . ";\n";
file_put_contents($arq, $sql) !== false || exit("✘ Não consegui gravar o arquivo para desfazer ($arq). Nada foi mudado.\n");
$st = $pdo->prepare('UPDATE profissionais SET endereco = NULL, atualizado_em = atualizado_em WHERE id = ?');
foreach ($tirar as $p) $st->execute([(int)$p['id']]);
require_once __DIR__ . '/inc/web.php';
web_cache_limpar();
echo "\n✔ Gravado: " . count($tirar) . " endereços tirados. Para desfazer: mysql guia_novo < $arq\n";
