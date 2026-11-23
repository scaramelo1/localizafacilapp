<?php
// 23/11 (avaliação do site, item 3): fichas repetidas (a mesma loja importada 2 ou mais vezes). O robô acha os grupos
// (inc/duplicadas.php: mesmo telefone ou mesmo ponto do mapa, com nome parecido); o admin escolhe qual fica e junta.
// Juntar: a que fica recebe o que só a outra tinha (WhatsApp, site, horário, Sobre...), favoritos e avaliações passam,
// a outra sai do app e do Google, e o endereço dela redireciona para a que ficou. Tudo pode ser desfeito.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/duplicadas.php';
require_once __DIR__ . '/../../inc/web.php';
$admin = exigir_admin();
$aba = in_array($_GET['aba'] ?? '', ['mesmo', 'diferente', 'juntadas', 'diferentes'], true) ? $_GET['aba'] : 'mesmo';
$cidade = isset(CIDADES[$_GET['cidade'] ?? '']) ? $_GET['cidade'] : null;
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$voltar = 'admin/duplicadas.php?aba=' . $aba . ($cidade ? '&cidade=' . $cidade : '') . ($pagina > 1 ? '&pagina=' . $pagina : '');

function dup_resposta(string $msg, string $tipo, string $voltar, ?string $situacao = null): never {
    if (!empty($_POST['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $tipo === 'ok', 'msg' => $msg, 'situacao' => $situacao], JSON_UNESCAPED_UNICODE);
        exit;
    }
    flash($msg, $tipo);
    ir($voltar);
}
if (e_post()) {
    exigir_post();
    $acao = (string)($_POST['acao'] ?? '');
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))))));
    if ($acao === 'juntar') {
        $fica = (int)($_POST['fica'] ?? 0);
        $sai = array_values(array_diff(array_map('intval', (array)($_POST['sai'] ?? [])), [$fica]));
        if (!in_array($fica, $ids, true)) dup_resposta('Escolha qual ficha fica.', 'erro', $voltar);
        if (!$sai) dup_resposta('Marque pelo menos uma ficha para sair.', 'erro', $voltar);
        $erros = [];
        foreach ($sai as $s) if (in_array($s, $ids, true) && ($e = dup_juntar($fica, $s, (int)$admin['id']))) $erros[] = $e;
        // as que não foram marcadas para sair: "não são a mesma" que a que fica (o grupo sai da lista)
        foreach (array_diff($ids, $sai, [$fica]) as $o)
            q("INSERT IGNORE INTO fichas_iguais (a_id, b_id, situacao, decidido_em, decidido_por) VALUES (?, ?, 'diferentes', NOW(), ?)", [min($fica, $o), max($fica, $o), $admin['id']]);
        web_cache_limpar();
        if ($erros) dup_resposta(implode(' ', $erros), count($erros) === count($sai) ? 'erro' : 'ok', $voltar, 'aprovado');
        $nome = nome_curto((string)valor('SELECT nome FROM profissionais WHERE id = ?', [$fica]));
        dup_resposta('✅ Juntadas em ' . $nome . " (#$fica): " . count($sai) . ' ficha' . (count($sai) > 1 ? 's saíram' : ' saiu') . '.', 'ok', $voltar, 'aprovado');
    } elseif ($acao === 'diferentes') {
        for ($i = 0; $i < count($ids); $i++) for ($j = $i + 1; $j < count($ids); $j++)
            q("INSERT IGNORE INTO fichas_iguais (a_id, b_id, situacao, decidido_em, decidido_por) VALUES (?, ?, 'diferentes', NOW(), ?)",
                [min($ids[$i], $ids[$j]), max($ids[$i], $ids[$j]), $admin['id']]);
        registrar((int)$admin['id'], 'fichas_diferentes', $ids[0] ?? null, implode(', ', array_map(fn($x) => "#$x", $ids)));
        dup_resposta('Marcadas como lojas diferentes.', 'ok', $voltar, 'recusado');
    } elseif ($acao === 'desfazer') {
        $e = dup_desfazer((int)($_POST['a'] ?? 0), (int)($_POST['b'] ?? 0), (int)$admin['id']);
        web_cache_limpar();
        dup_resposta($e ?? 'Desfeito: a ficha voltou para o app.', $e ? 'erro' : 'ok', $voltar, 'recusado');
    } elseif ($acao === 'reabrir') {
        q("DELETE FROM fichas_iguais WHERE a_id = ? AND b_id = ? AND situacao = 'diferentes'", [(int)($_POST['a'] ?? 0), (int)($_POST['b'] ?? 0)]);
        dup_resposta('O par volta para a lista de repetidas.', 'ok', $voltar, 'recusado');
    }
    dup_resposta('Ação desconhecida.', 'erro', $voltar);
}

$por_pag = 40;
$grupos = dup_grupos($cidade);
$mesmo = array_values(array_filter($grupos, 'dup_mesmo_lugar'));
$diferente = array_values(array_filter($grupos, fn($g) => !dup_mesmo_lugar($g)));
$num = ['mesmo' => count($mesmo), 'diferente' => count($diferente)]
    + array_column(linhas("SELECT IF(situacao = 'juntada', 'juntadas', 'diferentes') AS s, COUNT(*) AS n FROM fichas_iguais GROUP BY situacao"), 'n', 's');
$lista = $aba === 'mesmo' ? $mesmo : ($aba === 'diferente' ? $diferente : []);
$total = $aba === 'mesmo' || $aba === 'diferente' ? count($lista) : (int)($num[$aba] ?? 0);
$lista = array_slice($lista, ($pagina - 1) * $por_pag, $por_pag);
// no Google agora (para mostrar e para escolher a que fica)
$no_google = [];
$todos_ids = array_merge(...array_map(fn($g) => array_map(fn($p) => (int)$p['id'], $g), $lista ?: [[]]));
if ($todos_ids) $no_google = array_flip(array_map('intval', array_column(linhas('SELECT p.id FROM profissionais p WHERE p.id IN (' . implode(',', $todos_ids) . ') AND ' . sql_no_google()), 'id')));
$decididas = in_array($aba, ['juntadas', 'diferentes'], true) ? linhas("SELECT fi.*, a.nome AS nome_a, a.cidade, b.nome AS nome_b FROM fichas_iguais fi
    JOIN profissionais a ON a.id = fi.a_id JOIN profissionais b ON b.id = fi.b_id WHERE fi.situacao = ? ORDER BY fi.decidido_em DESC LIMIT $por_pag OFFSET "
    . (($pagina - 1) * $por_pag), [$aba === 'juntadas' ? 'juntada' : 'diferentes']) : [];
$paginas = max(1, (int)ceil($total / $por_pag));
$link = fn(array $q) => h(url('admin/duplicadas.php?' . http_build_query(array_filter($q + ['aba' => $aba, 'cidade' => $cidade]))));

admin_inicio('Fichas repetidas', $admin);
?>
<h1>👯 Fichas repetidas</h1>
<p class="discreto">A mesma loja cadastrada 2 ou mais vezes (mesmo telefone ou mesmo ponto no mapa, com nome parecido). Escolha qual
  <b>fica</b> (a sugerida vem marcada: com dono, conferida ou mais completa) e junte. A que fica recebe o que só a outra tinha
  (WhatsApp, site, horário, Sobre, ponto do mapa), favoritos e avaliações passam, a outra sai do app e do Google e o link dela leva
  para a que ficou. Tudo pode ser desfeito na aba Juntadas.<br>
  <b>Endereços diferentes</b> quase sempre são lojas da mesma rede (Loja 1, Loja 2): nesses casos, use “Não são a mesma”.</p>
<p class="filtros-linha">
  <?php foreach (['mesmo' => '📍 Mesmo endereço', 'diferente' => '🏬 Endereços diferentes', 'juntadas' => 'Juntadas', 'diferentes' => 'Não são a mesma'] as $k => $r): ?>
    <a class="botao <?= $k === $aba ? '' : 'contorno' ?> pequeno" href="<?= $link(['aba' => $k]) ?>"><?= h($r) ?> (<span<?= $k === $aba ? ' id="sobre-cont"' : '' ?>><?= (int)($num[$k] ?? 0) ?></span>)</a>
  <?php endforeach ?>
</p>
<?php if ($aba === 'mesmo' || $aba === 'diferente'): ?>
<p class="filtros-linha">Cidade:
  <a class="botao pequeno <?= $cidade ? 'contorno' : '' ?>" href="<?= h(url('admin/duplicadas.php?aba=' . $aba)) ?>">Todas</a>
  <?php foreach (CIDADES as $slug => $c): ?><a class="botao pequeno <?= $cidade === $slug ? '' : 'contorno' ?>" href="<?= $link(['cidade' => $slug, 'pagina' => null]) ?>"><?= h($c[0]) ?></a> <?php endforeach ?>
</p>
<?php endif ?>
<?php mostrar_flash(); ?>
<?php if (($aba === 'mesmo' || $aba === 'diferente') && !$lista): ?><div class="aviso ok">Nada aqui.</div><?php endif ?>

<?php foreach ($lista as $g): $ids = implode(',', array_map(fn($p) => (int)$p['id'], $g)); ?>
  <div class="quadro quadro-sobre">
    <form method="post"><?= campo_csrf() ?><input type="hidden" name="ids" value="<?= h($ids) ?>">
      <p class="discreto" style="margin:0 0 6px"><?= h(nome_cidade((string)$g[0]['cidade'])) ?> · <?= count($g) ?> fichas</p>
      <table class="tabela dup-tab"><thead><tr><th>Fica</th><th>Sai</th><th>Ficha</th><th>Endereço</th><th>Contato</th><th>Tem</th></tr></thead><tbody>
      <?php foreach ($g as $i => $p): $id = (int)$p['id']; $ficha = web_ficha($id); ?>
        <tr>
          <td><input type="radio" name="fica" value="<?= $id ?>"<?= $i === 0 ? ' checked' : '' ?> aria-label="Fica a #<?= $id ?>"></td>
          <td><input type="checkbox" name="sai[]" value="<?= $id ?>"<?= $i > 0 ? ' checked' : '' ?><?= $p['tem_dono'] ? ' disabled title="Tem dono: não pode sair"' : '' ?> aria-label="Sai a #<?= $id ?>"></td>
          <td><b><?= h((string)$p['nome']) ?></b><br><small class="discreto">#<?= $id ?><?= $p['tipo'] ? ' · ' . h((string)$p['tipo']) : '' ?>
            <?php if ($ficha): ?> · <a href="<?= h(url_ficha($ficha)) ?>" target="_blank">ver</a><?php endif ?></small></td>
          <td><?= h((string)($p['endereco'] ?: '—')) ?><?= $p['bairro'] ? '<br><small class="discreto">' . h((string)$p['bairro']) . '</small>' : '' ?></td>
          <td><small><?= $p['whatsapp'] ? 'Zap ' . h(telefone_formatado((string)$p['whatsapp'])) . '<br>' : '' ?><?= $p['telefone'] ? 'Tel ' . h(telefone_formatado((string)$p['telefone'])) : '' ?></small></td>
          <td><small><?= implode(' ', array_filter([
              $p['tem_dono'] ? '👤 dono' : null, $p['confirmada'] ? '✔ conferida' : null, isset($no_google[$id]) ? '🌐 Google' : null,
              $p['sobre_site'] || $p['descricao'] ? '📝 Sobre' : null, $p['horarios'] ? '🕒 horário' : null, $p['site'] ? '🔗 site' : null,
              $p['geo_origem'] === 'google' ? '📍 ponto' : null, (int)$p['total_avaliacoes'] ? '⭐ ' . (int)$p['total_avaliacoes'] : null,
              '👁 ' . (int)$p['popularidade']])) ?></small></td>
        </tr>
      <?php endforeach ?>
      </tbody></table>
      <p style="margin:8px 0 0"><button class="botao pequeno" name="acao" value="juntar">👯 Juntar</button>
        <button class="botao contorno pequeno" name="acao" value="diferentes">Não são a mesma</button></p>
    </form>
  </div>
<?php endforeach ?>

<?php if ($decididas): ?>
  <table class="tabela"><thead><tr><th>Quando</th><th>Fichas</th><th></th></tr></thead><tbody>
  <?php foreach ($decididas as $d): ?>
    <tr class="quadro-sobre"><td><small><?= h(date('d/m H:i', strtotime((string)$d['decidido_em']))) ?></small></td>
      <td><?php if ($d['situacao'] === 'juntada'): $fica_a = (int)$d['fica_id'] === (int)$d['a_id']; ?>
          Ficou <b><?= h($fica_a ? $d['nome_a'] : $d['nome_b']) ?></b> #<?= (int)$d['fica_id'] ?> · saiu <?= h($fica_a ? $d['nome_b'] : $d['nome_a']) ?> #<?= (int)$d['sai_id'] ?>
        <?php else: ?><?= h($d['nome_a']) ?> #<?= (int)$d['a_id'] ?> ≠ <?= h($d['nome_b']) ?> #<?= (int)$d['b_id'] ?><?php endif ?>
        <small class="discreto"> · <?= h(nome_cidade((string)$d['cidade'])) ?></small></td>
      <td><form method="post"><?= campo_csrf() ?><input type="hidden" name="a" value="<?= (int)$d['a_id'] ?>"><input type="hidden" name="b" value="<?= (int)$d['b_id'] ?>">
        <button class="botao contorno pequeno" name="acao" value="<?= $d['situacao'] === 'juntada' ? 'desfazer' : 'reabrir' ?>"><?= $d['situacao'] === 'juntada' ? '↩️ Desfazer' : '↩️ Voltar para a lista' ?></button></form></td></tr>
  <?php endforeach ?>
  </tbody></table>
<?php elseif (in_array($aba, ['juntadas', 'diferentes'], true)): ?><div class="aviso ok">Nada aqui.</div><?php endif ?>

<?php if ($paginas > 1): ?><p class="filtros-linha">Páginas:
  <?php for ($n = 1; $n <= $paginas; $n++): ?><a class="botao pequeno <?= $n === $pagina ? '' : 'contorno' ?>" href="<?= $link(['pagina' => $n]) ?>"><?= $n ?></a> <?php endfor ?>
  <span class="discreto"><?= number_format($total, 0, ',', '.') ?> no total</span></p><?php endif ?>
<script>
// junta / desfaz sem recarregar (igual ao Sobre pelo site); "Fica" marcada não pode estar marcada para sair
(function () {
  var cont = document.getElementById('sobre-cont');
  document.addEventListener('change', function (ev) {
    var r = ev.target; if (r.name !== 'fica') return;
    r.form.querySelectorAll('input[name="sai[]"]').forEach(function (c) { if (c.value === r.value) c.checked = false; else if (!c.disabled) c.checked = true; });
  });
  document.addEventListener('submit', function (ev) {
    var f = ev.target, q = f.closest('.quadro-sobre'); if (!q) return;
    ev.preventDefault();
    var b = ev.submitter;
    var dados = new FormData(f); dados.set('ajax', '1'); if (b && b.name) dados.set(b.name, b.value);
    f.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
    fetch(location.href, { method: 'POST', body: dados, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (t) {
        var j; try { j = JSON.parse(t); } catch (e) { j = { ok: false, msg: t || 'Erro no servidor.' }; }
        var m = document.createElement('p'); m.className = 'sobre-msg'; m.textContent = j.msg;
        if (!j.ok) { m.style.color = '#b42318'; f.appendChild(m); f.querySelectorAll('button').forEach(function (x) { x.disabled = false; }); return; }
        q.classList.add(j.situacao === 'aprovado' ? 'sobre-ok' : 'sobre-nao');
        if (q.tagName === 'TR') { q.cells[1].textContent = j.msg; q.cells[2].innerHTML = ''; }
        else { q.innerHTML = ''; q.appendChild(m); setTimeout(function () { q.style.display = 'none'; }, 1500); }
        if (cont) cont.textContent = Math.max(0, (parseInt(cont.textContent, 10) || 1) - 1);
      })
      .catch(function () { alert('Não deu certo (sem internet?). Tente de novo.'); f.querySelectorAll('button').forEach(function (x) { x.disabled = false; }); });
  });
})();
</script>
<style>.quadro-sobre.sobre-ok { background: #e3f2ea; } .quadro-sobre.sobre-nao { background: #f1f1f1; } .sobre-msg { margin: 0; font-weight: 700; }
.dup-tab td { vertical-align: top; } .dup-tab td:nth-child(-n+2) { text-align: center; width: 40px; }</style>
<?php admin_fim();
