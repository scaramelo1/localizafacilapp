<?php
// 23/11 (passo 78): "Tipos genéricos". Fichas com tipo "Outros", "Escritório da empresa", "Loja", "Empresa" ou sem tipo
// não aparecem nos filtros nem nas páginas de tipo. O site sugere um tipo pelo nome (inc/tipos-genericos.php, só tipos que
// já existem); o admin desmarca o que estiver errado e aplica. Antes de aplicar, guarda o tipo e a área antigos num arquivo
// (dá para voltar). Embaixo, as "fichas" cujo nome é um endereço ou um bairro (não são comércio): pausar só se marcar.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/moderacao.php';
require_once __DIR__ . '/../../inc/web.php';
require_once __DIR__ . '/../../inc/tipos-genericos.php';
$admin = exigir_admin();

if (e_post()) {
    exigir_post();
    $r = tipos_propor();
    $acao = (string)($_POST['acao'] ?? '');
    if ($acao === 'aplicar') {
        $marcados = array_flip(array_map('intval', (array)($_POST['mudar'] ?? [])));
        $antes = [];
        $n = 0;
        foreach ($r['mudar'] as [$p, $tipo, $cat]) {
            if (!isset($marcados[(int)$p['id']])) continue;
            $antes[] = ['id' => (int)$p['id'], 'tipo' => $p['tipo'], 'categoria_id' => (int)$p['categoria_id']];
            q('UPDATE profissionais SET tipo = ?, categoria_id = ? WHERE id = ? AND status = \'ativo\'', [$tipo, $cat, (int)$p['id']]);
            $n++;
        }
        if ($n) {
            $arq = config()['dados_dir'] . '/backups/tipos-antes-' . date('Ymd-His') . '.json';
            @file_put_contents($arq, json_encode($antes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            registrar((int)$admin['id'], 'tipos_genericos', null, "$n fichas com tipo novo (antes em " . basename($arq) . ')');
            web_cache_limpar();   // muda listas e páginas de tipo de várias cidades
        }
        flash("✅ $n fichas com tipo novo." . ($n ? ' O tipo e a área antigos ficaram guardados (dá para voltar).' : ''));
    } elseif ($acao === 'pausar') {
        $marcados = array_flip(array_map('intval', (array)($_POST['lixo'] ?? [])));
        $n = 0;
        foreach ($r['lixo'] as $p) {
            if (!isset($marcados[(int)$p['id']])) continue;
            q("UPDATE profissionais SET status = 'pausado' WHERE id = ? AND status = 'ativo'", [(int)$p['id']]);
            registrar((int)$admin['id'], 'pausou_nao_comercio', (int)$p['id'], (string)$p['nome']);
            $n++;
        }
        if ($n) web_cache_limpar();
        flash("$n fichas pausadas (saíram do app e do site; voltam em Comércios > editar).");
    }
    ir('admin/tipos.php');
}

$r = tipos_propor();
admin_inicio('Tipos genéricos', $admin);
?>
<h1>🏷️ Tipos genéricos</h1>
<p class="discreto">Fichas com tipo “Outros”, “Escritório da empresa”, “Loja”, “Empresa” ou sem tipo não aparecem nos filtros nem nas
  páginas de tipo. Abaixo, o tipo sugerido pelo <b>nome</b> (só tipos que o site já usa). Desmarque o que estiver errado e aplique.
  O tipo e a área antigos ficam guardados.<br>
  Sugestões: <b><?= count($r['mudar']) ?></b> · sem sugestão (o nome não diz o que é): <?= (int)$r['sem'] ?> · não parecem comércio: <?= count($r['lixo']) ?></p>
<?php mostrar_flash(); ?>
<?php if ($r['mudar']): ?>
<form method="post"><?= campo_csrf() ?>
  <p><label class="marcar"><input type="checkbox" id="todos" checked> marcar / desmarcar todos</label></p>
  <table class="tabela">
    <thead><tr><th></th><th>Comércio</th><th>Cidade</th><th>Hoje</th><th>Tipo sugerido</th></tr></thead>
    <tbody>
    <?php foreach ($r['mudar'] as [$p, $tipo, $cat]): ?>
      <tr><td><input type="checkbox" name="mudar[]" value="<?= (int)$p['id'] ?>" checked class="m"></td>
        <td><a href="<?= h(url('admin/comercio.php?id=' . (int)$p['id'])) ?>" target="_blank"><?= h(nome_curto((string)$p['nome'])) ?></a></td>
        <td><?= h(nome_cidade((string)$p['cidade'], true)) ?></td>
        <td class="discreto"><?= h($p['tipo'] ?: '(sem tipo)') ?> · <?= h((string)$p['categoria']) ?></td>
        <td><b><?= h($tipo) ?></b></td></tr>
    <?php endforeach ?>
    </tbody>
  </table>
  <p><button class="botao" name="acao" value="aplicar">✅ Aplicar os marcados</button></p>
</form>
<?php endif ?>
<?php if ($r['lixo']): ?>
<h2>Não parecem comércio (o nome é um endereço ou um bairro)</h2>
<p class="discreto">Vieram da lista do Google Maps assim. Marque só os que quer tirar do app e do site (ficam pausados; dá para voltar).</p>
<form method="post"><?= campo_csrf() ?>
  <ul>
  <?php foreach ($r['lixo'] as $p): ?>
    <li><label><input type="checkbox" name="lixo[]" value="<?= (int)$p['id'] ?>"> <?= h((string)$p['nome']) ?></label>
      <span class="discreto">· <?= h(nome_cidade((string)$p['cidade'], true)) ?> · <a href="<?= h(url('admin/comercio.php?id=' . (int)$p['id'])) ?>" target="_blank">ver</a></span></li>
  <?php endforeach ?>
  </ul>
  <p><button class="botao contorno" name="acao" value="pausar">Pausar os marcados</button></p>
</form>
<?php endif ?>
<script>
document.getElementById('todos')?.addEventListener('change', function (e) {
  document.querySelectorAll('input.m').forEach(function (x) { x.checked = e.target.checked; });
});
</script>
<?php admin_fim();
