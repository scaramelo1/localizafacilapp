<?php
// 22/11 (passo 55, E): "Sobre (do site do comércio)". O robô sobre-do-site.php lê a página inicial do site próprio de
// cada comércio e guarda a descrição como pendente. Aqui o admin aprova (pode corrigir o texto antes), recusa ou desfaz.
// Aprovado vai para profissionais.sobre_site e aparece na ficha com o link do site. Também lista os sites que não abrem
// (o botão "Site" da ficha leva a lugar nenhum): dá para tirar o site da ficha com um toque.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/moderacao.php';
require_once __DIR__ . '/../../inc/web.php';
$admin = exigir_admin();
$aba = in_array($_GET['aba'] ?? '', ['pendente', 'aprovado', 'recusado', 'erro'], true) ? $_GET['aba'] : 'pendente';
$pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
$voltar = 'admin/sobre-site.php?aba=' . $aba . ($pagina > 1 ? '&pagina=' . $pagina : '');

// 22/11 (pedido do usuário): aprovar sem recarregar a página. Com ajax=1 responde JSON; sem JavaScript continua igual.
function sobre_resposta(string $msg, string $tipo, string $voltar, ?string $situacao = null): never {
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
    $s = linha('SELECT s.*, p.nome FROM sobre_site s JOIN profissionais p ON p.id = s.profissional_id WHERE s.profissional_id = ?', [$id]);
    if (!$s) sobre_resposta('Não encontrado.', 'erro', $voltar);
    $nome = nome_curto((string)$s['nome']);
    $acao = (string)($_POST['acao'] ?? '');
    // a ficha não fica em cache e as listas não mostram o Sobre nem o site: não precisa limpar o cache (deixava cada clique lento)
    if ($acao === 'aprovar') {
        $texto = trim(preg_replace('/\s+/u', ' ', (string)($_POST['texto'] ?? '')));
        if (mb_strlen($texto) < 20) sobre_resposta('Texto curto demais. Nada foi salvo.', 'erro', $voltar);
        $texto = mb_substr($texto, 0, 600);
        q("UPDATE sobre_site SET texto = ?, situacao = 'aprovado', decidido_em = NOW(), decidido_por = ? WHERE profissional_id = ?", [$texto, $admin['id'], $id]);
        q('UPDATE profissionais SET sobre_site = ?, sobre_site_url = ? WHERE id = ?', [$texto, $s['url'], $id]);
        registrar((int)$admin['id'], 'sobre_site_aprovado', $id, mb_substr($texto, 0, 180));
        sobre_resposta("✅ $nome aprovado: já aparece na ficha.", 'ok', $voltar, 'aprovado');
    } elseif ($acao === 'recusar' || $acao === 'desfazer') {
        q("UPDATE sobre_site SET situacao = 'recusado', decidido_em = NOW(), decidido_por = ? WHERE profissional_id = ?", [$admin['id'], $id]);
        q('UPDATE profissionais SET sobre_site = NULL, sobre_site_url = NULL WHERE id = ?', [$id]);
        registrar((int)$admin['id'], 'sobre_site_recusado', $id);
        sobre_resposta($acao === 'desfazer' ? "$nome: tirado da ficha." : "$nome: recusado.", 'ok', $voltar, 'recusado');
    } elseif ($acao === 'dispensar') {
        // 23/11 (pedido do usuário): sai dos Recusados e volta para a fila: a próxima leitura do robô lê o site de novo
        q('DELETE FROM sobre_site WHERE profissional_id = ?', [$id]);
        registrar((int)$admin['id'], 'sobre_site_dispensado', $id);
        sobre_resposta("$nome: dispensado. Volta para a próxima leitura do robô.", 'ok', $voltar, 'dispensado');
    } elseif ($acao === 'tirar_site') {
        $p = linha('SELECT site FROM profissionais WHERE id = ?', [$id]);
        q('UPDATE profissionais SET site = NULL WHERE id = ?', [$id]);
        q("UPDATE sobre_site SET situacao = 'recusado', motivo = CONCAT('site tirado da ficha: ', IFNULL(motivo, '')), decidido_em = NOW(), decidido_por = ? WHERE profissional_id = ?", [$admin['id'], $id]);
        registrar((int)$admin['id'], 'site_tirado', $id, 'site fora do ar: ' . ($p['site'] ?? ''));
        sobre_resposta("$nome: botão Site tirado da ficha (era " . ($p['site'] ?? '') . ').', 'ok', $voltar, 'site_tirado');
    }
    sobre_resposta('Ação desconhecida.', 'erro', $voltar);
}

$num = array_column(linhas('SELECT situacao, COUNT(*) AS n FROM sobre_site GROUP BY situacao'), 'n', 'situacao');
$onde = $aba === 'erro' ? "s.situacao = 'erro' AND p.site IS NOT NULL AND p.site <> ''" : 's.situacao = ' . "'" . $aba . "'";
$lista = linhas("SELECT s.*, p.nome, p.cidade, p.tipo, p.descricao, p.site FROM sobre_site s JOIN profissionais p ON p.id = s.profissional_id
    WHERE $onde AND p.status = 'ativo' ORDER BY p.popularidade DESC, p.id LIMIT 100 OFFSET " . (($pagina - 1) * 100));
$total_aba = (int)valor("SELECT COUNT(*) FROM sobre_site s JOIN profissionais p ON p.id = s.profissional_id WHERE $onde AND p.status = 'ativo'");
$paginas = max(1, (int)ceil($total_aba / 100));

admin_inicio('Sobre pelo site do comércio', $admin);
?>
<h1>🌐 Sobre pelo site do comércio</h1>
<p class="discreto">O robô lê a descrição que o <b>próprio comércio</b> escreveu no site dele. Nada aparece sozinho: confira, corrija se
  precisar e aprove. Aprovado aparece na ficha como “Sobre (do site do comércio)”, com o link. Texto de propaganda exagerada,
  de outra empresa (rede, plataforma) ou que não fala do comércio: recuse.<br>
  Ler mais sites (na VPS): <code>sudo -u www-data php /var/www/guia-novo/servidor/sobre-do-site.php --limite=100</code>
  · <a href="<?= h(url('admin/horario-site.php')) ?>">🕒 Horário pelo site</a></p>
<p class="filtros-linha">
  <?php foreach (['pendente' => 'Para aprovar', 'aprovado' => 'Aprovados', 'recusado' => 'Recusados', 'erro' => 'Sites que não abrem'] as $k => $r): ?>
    <a class="botao <?= $k === $aba ? '' : 'contorno' ?> pequeno" href="<?= h(url('admin/sobre-site.php?aba=' . $k)) ?>"><?= h($r) ?> (<span<?= $k === $aba ? ' id="sobre-cont"' : '' ?>><?= (int)($num[$k] ?? 0) ?></span>)</a>
  <?php endforeach ?>
  <span class="discreto">· sem texto que sirva: <?= (int)($num['sem_texto'] ?? 0) ?></span>
</p>
<?php mostrar_flash(); ?>
<?php if (!$lista): ?><div class="aviso ok">Nada aqui.</div><?php endif ?>
<?php foreach ($lista as $s): $ficha = web_ficha((int)$s['profissional_id']); ?>
  <div class="quadro quadro-sobre">
    <h2 style="margin:0 0 4px"><?= h(nome_curto((string)$s['nome'])) ?> <small class="discreto">#<?= (int)$s['profissional_id'] ?> · <?= h(nome_cidade((string)$s['cidade'])) ?><?= $s['tipo'] ? ' · ' . h($s['tipo']) : '' ?></small></h2>
    <p class="discreto" style="margin:0 0 8px">Site: <a href="<?= h($s['url']) ?>" target="_blank" rel="noopener"><?= h($s['url']) ?></a>
      <?php if ($s['titulo']): ?> · título: “<?= h(mb_substr((string)$s['titulo'], 0, 90)) ?>”<?php endif ?>
      <?php if ($ficha): ?> · <a href="<?= h(url_ficha($ficha)) ?>" target="_blank">ver a ficha</a><?php endif ?></p>
    <?php if ($aba === 'erro'): ?>
      <p>⚠️ <?= h((string)$s['motivo']) ?> (lido em <?= h(date('d/m H:i', strtotime($s['lido_em']))) ?>). Se o site não existe mais, o botão “Site” da ficha leva a lugar nenhum.</p>
      <form method="post" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$s['profissional_id'] ?>"><input type="hidden" name="pagina" value="<?= $pagina ?>">
        <button class="botao contorno pequeno" name="acao" value="tirar_site" onclick="return confirm('Tirar o site da ficha?')">Tirar o site da ficha</button></form>
    <?php elseif ($aba === 'pendente'): ?>
      <form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$s['profissional_id'] ?>"><input type="hidden" name="pagina" value="<?= $pagina ?>">
        <textarea name="texto" rows="3" maxlength="600" style="width:100%"><?= h((string)$s['texto']) ?></textarea>
        <p style="margin:6px 0 0"><button class="botao pequeno" name="acao" value="aprovar">✅ Aprovar</button>
          <button class="botao contorno pequeno" name="acao" value="recusar">Recusar</button></p>
      </form>
    <?php elseif ($aba === 'recusado'): // 22/11 (pedido do usuário): recusado também pode ser aprovado depois de conferir ?>
      <?php if ($s['motivo']): ?><p class="discreto" style="margin:0 0 6px">Recusado porque: <?= h((string)$s['motivo']) ?></p><?php endif ?>
      <form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$s['profissional_id'] ?>"><input type="hidden" name="pagina" value="<?= $pagina ?>">
        <textarea name="texto" rows="3" maxlength="600" style="width:100%"><?= h((string)$s['texto']) ?></textarea>
        <p style="margin:6px 0 0"><button class="botao pequeno" name="acao" value="aprovar">✅ Aprovar</button>
          <button class="botao contorno pequeno" name="acao" value="dispensar" title="Tira dos recusados; o robô lê este site de novo na próxima vez">↩️ Dispensar</button>
          <span class="discreto">Dá para corrigir o texto antes de aprovar. Dispensar: o robô lê o site de novo na próxima vez.</span></p>
      </form>
    <?php else: ?>
      <p><?= h((string)$s['texto']) ?></p>
      <?php if ($aba === 'aprovado'): ?><form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$s['profissional_id'] ?>"><input type="hidden" name="pagina" value="<?= $pagina ?>">
        <button class="botao contorno pequeno" name="acao" value="desfazer">Tirar da ficha</button></form><?php endif ?>
    <?php endif ?>
  </div>
<?php endforeach ?>
<?php if ($paginas > 1): ?><p class="filtros-linha">Páginas:
  <?php for ($n = 1; $n <= $paginas; $n++): ?><a class="botao pequeno <?= $n === $pagina ? '' : 'contorno' ?>" href="<?= h(url('admin/sobre-site.php?aba=' . $aba . '&pagina=' . $n)) ?>"><?= $n ?></a> <?php endfor ?>
  <span class="discreto"><?= number_format($total_aba, 0, ',', '.') ?> no total</span></p><?php endif ?>
<script>
// 22/11 (pedido do usuário): aprova/recusa sem recarregar. O quadro fica verde (ou cinza) com o resultado e some da lista.
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
  // Ctrl+Enter na caixa de texto = Aprovar
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey) && ev.target.name === 'texto') {
      var b = ev.target.form.querySelector('button[value="aprovar"]'); if (b) { ev.preventDefault(); ev.target.form.requestSubmit(b); }
    }
  });
})();
</script>
<style>.quadro-sobre.sobre-ok { background: #e3f2ea; } .quadro-sobre.sobre-nao { background: #f1f1f1; } .sobre-msg { margin: 0; font-weight: 700; }</style>
<?php admin_fim();
