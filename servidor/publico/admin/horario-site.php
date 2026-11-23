<?php
// 23/11 (passo 61): horário lido do site próprio do comércio (servidor/horario-do-site.php). O admin vê o trecho do site
// onde o robô achou o horário, confere e corrige nos campos e aprova: vai para profissionais.horarios (a ficha passa a
// mostrar o horário, o selo "Aberto agora" e entra no filtro). Só preenche ficha SEM horário (nunca troca o do dono).
// Aprova e recusa sem recarregar a página (igual a /admin/sobre-site.php).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/moderacao.php';
require_once __DIR__ . '/../../inc/horarios.php';
require_once __DIR__ . '/../../inc/web.php';
$admin = exigir_admin();
$aba = in_array($_GET['aba'] ?? '', ['pendente', 'aprovado', 'recusado', 'erro'], true) ? $_GET['aba'] : 'pendente';
$pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
$voltar = 'admin/horario-site.php?aba=' . $aba . ($pagina > 1 ? '&pagina=' . $pagina : '');

function hs_resposta(string $msg, string $tipo, string $voltar, ?string $situacao = null): never {
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
    $id = (int)($_POST['id'] ?? 0);
    $s = linha('SELECT hs.*, p.nome, p.horarios AS horarios_ficha FROM horario_site hs JOIN profissionais p ON p.id = hs.profissional_id WHERE hs.profissional_id = ?', [$id]);
    if (!$s) hs_resposta('Não encontrado.', 'erro', $voltar);
    $nome = nome_curto((string)$s['nome']);
    $acao = (string)($_POST['acao'] ?? '');
    if ($acao === 'aprovar') {
        [$hor, $erro] = horarios_do_post((array)($_POST['hor'] ?? []));
        if ($erro) hs_resposta($erro . ' Nada foi salvo.', 'erro', $voltar);
        if ($hor === null) hs_resposta('Nenhum dia preenchido. Nada foi salvo.', 'erro', $voltar);
        if (horarios_ler($s['horarios_ficha']) && empty($_POST['trocar']))
            hs_resposta("$nome já tem horário na ficha (do dono ou da equipe). Nada foi trocado.", 'erro', $voltar);
        q("UPDATE horario_site SET horarios = ?, situacao = 'aprovado', decidido_em = NOW(), decidido_por = ? WHERE profissional_id = ?", [$hor, $admin['id'], $id]);
        q('UPDATE profissionais SET horarios = ?, horario = ? WHERE id = ?', [$hor, horarios_texto(horarios_ler($hor)), $id]);
        registrar((int)$admin['id'], 'horario_site_aprovado', $id, horarios_texto(horarios_ler($hor)));
        web_cache_limpar();   // as listas mostram "Aberto agora" e o selo do horário
        hs_resposta("✅ $nome: horário na ficha (" . horarios_texto(horarios_ler($hor)) . ').', 'ok', $voltar, 'aprovado');
    } elseif ($acao === 'recusar') {
        q("UPDATE horario_site SET situacao = 'recusado', decidido_em = NOW(), decidido_por = ? WHERE profissional_id = ?", [$admin['id'], $id]);
        registrar((int)$admin['id'], 'horario_site_recusado', $id);
        hs_resposta("$nome: recusado.", 'ok', $voltar, 'recusado');
    } elseif ($acao === 'dispensar') {
        // 23/11 (pedido do usuário): sai dos Recusados e volta para a fila: a próxima leitura do robô lê o site de novo
        q('DELETE FROM horario_site WHERE profissional_id = ?', [$id]);
        registrar((int)$admin['id'], 'horario_site_dispensado', $id);
        hs_resposta("$nome: dispensado. Volta para a próxima leitura do robô.", 'ok', $voltar, 'dispensado');
    } elseif ($acao === 'desfazer') {
        // tira da ficha só se o horário dela ainda é o que veio do site
        if ((string)$s['horarios_ficha'] === (string)$s['horarios']) q('UPDATE profissionais SET horarios = NULL, horario = NULL WHERE id = ?', [$id]);
        q("UPDATE horario_site SET situacao = 'recusado', decidido_em = NOW(), decidido_por = ? WHERE profissional_id = ?", [$admin['id'], $id]);
        registrar((int)$admin['id'], 'horario_site_desfeito', $id);
        web_cache_limpar();
        hs_resposta("$nome: horário tirado da ficha.", 'ok', $voltar, 'recusado');
    }
    hs_resposta('Ação desconhecida.', 'erro', $voltar);
}

$num = array_column(linhas('SELECT situacao, COUNT(*) AS n FROM horario_site GROUP BY situacao'), 'n', 'situacao');
$onde = "hs.situacao = '$aba'";
$lista = linhas("SELECT hs.*, p.nome, p.cidade, p.tipo, p.horarios AS horarios_ficha FROM horario_site hs JOIN profissionais p ON p.id = hs.profissional_id
    WHERE $onde AND p.status = 'ativo' ORDER BY p.popularidade DESC, p.id LIMIT 50 OFFSET " . (($pagina - 1) * 50));
$total_aba = (int)valor("SELECT COUNT(*) FROM horario_site hs JOIN profissionais p ON p.id = hs.profissional_id WHERE $onde AND p.status = 'ativo'");
$paginas = max(1, (int)ceil($total_aba / 50));

admin_inicio('Horário pelo site do comércio', $admin);
?>
<h1>🕒 Horário pelo site do comércio</h1>
<p class="discreto">O robô procura o horário escrito no <b>site do próprio comércio</b>. Nada vai para a ficha sozinho: leia o trecho do
  site, confira os campos (corrija se precisar) e aprove. Cuidado com horário de missa, culto, aula, delivery ou de outra loja da rede:
  nesses casos, recuse ou corrija. Aprovado, a ficha mostra o horário e o “Aberto agora”.<br>
  Ler mais sites (na VPS): <code>sudo -u www-data php /var/www/guia-novo/servidor/horario-do-site.php --todas --limite=4000</code>
  · <a href="<?= h(url('admin/sobre-site.php')) ?>">🌐 Sobre pelo site</a></p>
<p class="filtros-linha">
  <?php foreach (['pendente' => 'Para aprovar', 'aprovado' => 'Aprovados', 'recusado' => 'Recusados', 'erro' => 'Sites que não abriram'] as $k => $r): ?>
    <a class="botao <?= $k === $aba ? '' : 'contorno' ?> pequeno" href="<?= h(url('admin/horario-site.php?aba=' . $k)) ?>"><?= h($r) ?> (<span<?= $k === $aba ? ' id="sobre-cont"' : '' ?>><?= (int)($num[$k] ?? 0) ?></span>)</a>
  <?php endforeach ?>
  <span class="discreto">· sites sem horário escrito: <?= (int)($num['nao_achou'] ?? 0) ?></span>
</p>
<?php mostrar_flash(); ?>
<?php if (!$lista): ?><div class="aviso ok">Nada aqui.</div><?php endif ?>
<?php foreach ($lista as $s): $ficha = web_ficha((int)$s['profissional_id']); $h = horarios_ler($s['horarios']); $tem_ficha = (bool)horarios_ler($s['horarios_ficha']); ?>
  <div class="quadro quadro-sobre">
    <h2 style="margin:0 0 4px"><?= h(nome_curto((string)$s['nome'])) ?> <small class="discreto">#<?= (int)$s['profissional_id'] ?> · <?= h(nome_cidade((string)$s['cidade'])) ?><?= $s['tipo'] ? ' · ' . h($s['tipo']) : '' ?></small></h2>
    <p class="discreto" style="margin:0 0 8px">Site: <a href="<?= h($s['url']) ?>" target="_blank" rel="noopener"><?= h($s['url']) ?></a>
      <?php if ($ficha): ?> · <a href="<?= h(url_ficha($ficha)) ?>" target="_blank">ver a ficha</a><?php endif ?></p>
    <?php if ($aba === 'erro'): ?>
      <p>⚠️ <?= h((string)$s['motivo']) ?></p>
    <?php elseif ($aba === 'aprovado'): ?>
      <p><b>Na ficha:</b> <?= h((string)horarios_texto($h)) ?></p>
      <form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$s['profissional_id'] ?>"><input type="hidden" name="pagina" value="<?= $pagina ?>">
        <button class="botao contorno pequeno" name="acao" value="desfazer">Tirar da ficha</button></form>
    <?php else: ?>
      <?php if ($aba === 'recusado' && $s['motivo']): ?><p class="discreto" style="margin:0 0 6px">Recusado porque: <?= h((string)$s['motivo']) ?></p><?php endif ?>
      <p style="margin:0 0 8px">No site: <q style="background:#fff8d6;padding:2px 6px;border-radius:6px"><?= h((string)$s['trecho']) ?></q></p>
      <?php if ($tem_ficha): ?><p class="aviso erro" style="margin:0 0 8px">A ficha já tem horário (<?= h((string)horarios_texto(horarios_ler($s['horarios_ficha']))) ?>). Aprovar não troca, a não ser que marque abaixo.</p><?php endif ?>
      <form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$s['profissional_id'] ?>"><input type="hidden" name="pagina" value="<?= $pagina ?>">
        <?= horarios_form($h) ?>
        <?php if ($tem_ficha): ?><label class="marcar"><input type="checkbox" name="trocar" value="1"> Trocar o horário da ficha por este</label><?php endif ?>
        <p style="margin:6px 0 0"><button class="botao pequeno" name="acao" value="aprovar">✅ Aprovar</button>
          <?php if ($aba === 'pendente'): ?><button class="botao contorno pequeno" name="acao" value="recusar">Recusar</button><?php endif ?>
          <?php if ($aba === 'recusado'): ?><button class="botao contorno pequeno" name="acao" value="dispensar" title="Tira dos recusados; o robô lê este site de novo na próxima vez">↩️ Dispensar</button>
            <span class="discreto">Dispensar: o robô lê o site de novo na próxima vez.</span><?php endif ?></p>
      </form>
    <?php endif ?>
  </div>
<?php endforeach ?>
<?php if ($paginas > 1): ?><p class="filtros-linha">Páginas:
  <?php for ($n = 1; $n <= $paginas; $n++): ?><a class="botao pequeno <?= $n === $pagina ? '' : 'contorno' ?>" href="<?= h(url('admin/horario-site.php?aba=' . $aba . '&pagina=' . $n)) ?>"><?= $n ?></a> <?php endfor ?>
  <span class="discreto"><?= number_format($total_aba, 0, ',', '.') ?> no total</span></p><?php endif ?>
<script>
// aprova/recusa sem recarregar: o quadro fica verde (ou cinza) com o resultado e some da lista
(function () {
  var cont = document.getElementById('sobre-cont');
  document.addEventListener('submit', function (ev) {
    var f = ev.target; if (!f.closest('.quadro-sobre')) return;
    ev.preventDefault();
    var b = ev.submitter, q = f.closest('.quadro-sobre');
    var dados = new FormData(f); dados.set('ajax', '1'); if (b && b.name) dados.set(b.name, b.value);
    f.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
    fetch(location.href, { method: 'POST', body: dados, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (t) {
        var j; try { j = JSON.parse(t); } catch (e) { j = { ok: false, msg: t || 'Erro no servidor.' }; }
        var m = document.createElement('p'); m.className = 'sobre-msg'; m.textContent = j.msg;
        if (!j.ok) { m.style.color = '#b42318'; f.appendChild(m); f.querySelectorAll('button').forEach(function (x) { x.disabled = false; }); return; }
        q.classList.add(j.situacao === 'aprovado' ? 'sobre-ok' : 'sobre-nao');
        q.innerHTML = ''; q.appendChild(m);
        if (cont) cont.textContent = Math.max(0, (parseInt(cont.textContent, 10) || 1) - 1);
        setTimeout(function () { q.style.display = 'none'; }, 1500);
      })
      .catch(function () { alert('Não deu certo (sem internet?). Tente de novo.'); f.querySelectorAll('button').forEach(function (x) { x.disabled = false; }); });
  });
})();
</script>
<style>.quadro-sobre.sobre-ok { background: #e3f2ea; } .quadro-sobre.sobre-nao { background: #f1f1f1; } .sobre-msg { margin: 0; font-weight: 700; }
.quadro-sobre .horarios { border: 0; padding: 0; margin: 0; } .quadro-sobre .horario-linha { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 4px 0; }
.quadro-sobre .horario-linha label { min-width: 120px; }</style>
<?php admin_fim();
