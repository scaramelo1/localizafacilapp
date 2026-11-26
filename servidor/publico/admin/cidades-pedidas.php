<?php
// "Quero minha Cidade!" (25/11, pedido do usuário: "só aparece a notificação no celular, não tem um lugar no site para
// ver quem pediu"). Ranking das cidades pedidas e cada pedido: quando, de onde (app ou site) e quem, se entrou na conta
// (sem conta o pedido é anônimo: guardamos só a cidade). Abrir a página marca os pedidos como vistos (zera o contador do menu).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/cidades-pedidas.php';
require_once __DIR__ . '/../../inc/numeros.php';   // num()
$admin = exigir_admin();
$completa = cidades_pedidas_completas();

if (e_post() && ($_POST['acao'] ?? '') === 'apagar_cidade') {
    exigir_post();
    $cid = (string)($_POST['cidade'] ?? '');
    $uf = (string)($_POST['uf'] ?? '');
    $n = q('DELETE FROM cidades_pedidas WHERE LOWER(cidade) = LOWER(?) AND uf <=> ?', [$cid, $uf === '' ? null : $uf])->rowCount();
    registrar((int)$admin['id'], 'apagou_cidade', null, "$cid" . ($uf ? " ($uf)" : '') . ": $n pedido(s)");
    flash("$cid saiu da lista ($n pedido" . ($n === 1 ? '' : 's') . ').');
    ir('admin/cidades-pedidas.php');
}

$filtro = trim((string)($_GET['cidade'] ?? ''));
[$fc, $fuf] = array_pad(explode('|', $filtro, 2), 2, '');
$resumo = linha('SELECT COUNT(*) AS total, COUNT(DISTINCT LOWER(cidade), uf) AS cidades,
    SUM(criado_em >= NOW() - INTERVAL 7 DAY) AS semana FROM cidades_pedidas') ?? ['total' => 0, 'cidades' => 0, 'semana' => 0];
$ranking = linhas('SELECT MIN(cidade) AS cidade, uf, COUNT(*) AS total, MIN(criado_em) AS primeiro, MAX(criado_em) AS ultimo'
    . ($completa ? ', COUNT(DISTINCT usuario_id) AS contas, SUM(visto = 0) AS novos' : ', 0 AS contas, 0 AS novos')
    . ' FROM cidades_pedidas GROUP BY LOWER(cidade), uf ORDER BY total DESC, ultimo DESC');
$onde = $fc !== '' ? ' WHERE LOWER(c.cidade) = LOWER(?) AND c.uf <=> ?' : '';
$par = $fc !== '' ? [$fc, $fuf === '' ? null : $fuf] : [];
$pedidos = $completa
    ? linhas("SELECT c.*, u.nome AS pessoa, u.email FROM cidades_pedidas c LEFT JOIN usuarios u ON u.id = c.usuario_id$onde ORDER BY c.id DESC LIMIT 300", $par)
    : linhas("SELECT c.*, NULL AS usuario_id, NULL AS origem, 0 AS visto, NULL AS pessoa, NULL AS email FROM cidades_pedidas c$onde ORDER BY c.id DESC LIMIT 300", $par);
// abriu a página = viu tudo (o destaque "novo" aparece só nesta abertura)
if ($completa) q('UPDATE cidades_pedidas SET visto = 1 WHERE visto = 0');

admin_inicio('Quero minha Cidade!', $admin);
?>
<h1>🙋 Quero minha Cidade!</h1>
<p class="discreto">Pedidos feitos na tela de escolher a cidade (app e site). Cada pessoa pode pedir até 5 vezes por dia.
  Quem estava com a conta aberta aparece com nome e e-mail; sem conta, o pedido é anônimo.</p>
<?php mostrar_flash() ?>
<?php if (!$completa): ?><p class="aviso erro">Falta aplicar a mudança 050 (bash servidor/instalar.sh): até lá não dá para ver quem pediu nem de onde.</p><?php endif ?>

<div class="numeros-resumo">
  <div><strong><?= num((int)$resumo['total']) ?></strong><span>pedidos</span></div>
  <div><strong><?= num((int)$resumo['cidades']) ?></strong><span>cidades diferentes</span></div>
  <div><strong><?= num((int)$resumo['semana']) ?></strong><span>nos últimos 7 dias</span></div>
</div>

<h2>Cidades mais pedidas</h2>
<?php if (!$ranking): ?><p class="discreto">Ninguém pediu ainda.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Cidade</th><th>Pedidos</th><th>Com conta</th><th>Primeiro</th><th>Último</th><th></th></tr>
  <?php foreach ($ranking as $c): $chave = $c['cidade'] . '|' . ($c['uf'] ?? ''); ?>
  <tr><td><a href="?cidade=<?= h(rawurlencode($chave)) ?>#pedidos"><strong><?= h($c['cidade']) ?></strong></a> <span class="discreto"><?= h($c['uf'] ?? '') ?></span>
      <?php if ((int)$c['novos']): ?> <span class="contador"><?= (int)$c['novos'] ?> novo<?= (int)$c['novos'] === 1 ? '' : 's' ?></span><?php endif ?></td>
    <td><strong><?= (int)$c['total'] ?></strong></td><td><?= (int)$c['contas'] ?></td>
    <td><?= h(datahora_br($c['primeiro'])) ?></td><td><?= h(datahora_br($c['ultimo'])) ?></td>
    <td><form method="post" onsubmit="return confirm('Tirar <?= h(addslashes($c['cidade'])) ?> da lista? Os pedidos dela são apagados.')"><?= campo_csrf() ?>
      <input type="hidden" name="acao" value="apagar_cidade"><input type="hidden" name="cidade" value="<?= h($c['cidade']) ?>">
      <input type="hidden" name="uf" value="<?= h($c['uf'] ?? '') ?>">
      <button class="botao contorno" style="padding:6px 12px;font-size:14px">Excluir</button></form></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<h2 id="pedidos">Cada pedido<?= $fc !== '' ? ': ' . h($fc) . ($fuf ? " ($fuf)" : '') : '' ?></h2>
<?php if ($fc !== ''): ?><p><a href="cidades-pedidas.php#pedidos">← Ver de todas as cidades</a></p><?php endif ?>
<?php if (!$pedidos): ?><p class="discreto">Nenhum pedido.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Quando</th><th>Cidade</th><th>Quem pediu</th><th>Onde</th></tr>
  <?php foreach ($pedidos as $l): ?>
  <tr><td><?= h(datahora_br($l['criado_em'])) ?><?php if ($completa && !(int)$l['visto']): ?> <span class="contador">novo</span><?php endif ?></td>
    <td><?= h($l['cidade']) ?> <span class="discreto"><?= h($l['uf'] ?? '') ?></span></td>
    <td><?php if ($l['usuario_id'] && $l['pessoa'] !== null): ?><a href="pessoa.php?id=<?= (int)$l['usuario_id'] ?>"><strong><?= h($l['pessoa']) ?></strong></a><br><span class="discreto"><?= h((string)$l['email']) ?></span>
      <?php else: ?><span class="discreto">Sem conta (anônimo)</span><?php endif ?></td>
    <td><?= $l['origem'] === 'site' ? '🌐 Site' : ($l['origem'] === 'app' ? '📱 App' : '<span class="discreto">—</span>') ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
