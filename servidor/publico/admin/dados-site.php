<?php
// 23/11 (passo 70): "Dados pelo site". O robô servidor/dados-do-site.php lê o site próprio do comércio e guarda em
// dados_site o WhatsApp, telefone, Instagram, Facebook, e-mail de contato, serviços e endereço que achou, como pendentes.
// Aqui o admin vê uma ficha por quadro, com tudo o que o site tem: confere (pode corrigir o texto), desmarca o que não
// serve e aprova. O que ficou desmarcado vai para os Recusados. Nada vai para a ficha sem aprovação.
// "Preencher" = a ficha estava sem (vem marcado). "Diferente" = a ficha tem outro valor (vem desmarcado: só troca se marcar).
// Aviso de rede/agência: o mesmo valor aparece em outras fichas (matriz da rede ou quem fez o site) -> vem desmarcado.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/moderacao.php';
require_once __DIR__ . '/../../inc/web.php';
require_once __DIR__ . '/../../inc/limpeza.php';
require_once __DIR__ . '/../../inc/dados-site.php';
$admin = exigir_admin();
$aba = in_array($_GET['aba'] ?? '', ['pendente', 'aprovado', 'recusado'], true) ? $_GET['aba'] : 'pendente';
$campo_f = isset(DS_CAMPOS[$_GET['campo'] ?? '']) ? $_GET['campo'] : '';
$pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
$voltar = 'admin/dados-site.php?aba=' . $aba . ($campo_f ? '&campo=' . $campo_f : '') . ($pagina > 1 ? '&pagina=' . $pagina : '');
const DS_COLUNA = ['whatsapp' => 'whatsapp', 'telefone' => 'telefone', 'instagram' => 'instagram', 'facebook' => 'facebook',
    'email' => 'email_contato', 'servicos' => 'servicos', 'endereco' => 'endereco'];

function ds_resposta(string $msg, string $tipo, string $voltar, ?string $situacao = null): never {
    if (!empty($_POST['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $tipo === 'ok', 'msg' => $msg, 'situacao' => $situacao], JSON_UNESCAPED_UNICODE);
        exit;
    }
    flash($msg, $tipo);
    ir($voltar);
}
/** Valor que o admin digitou -> valor para a coluna da ficha, ou [null, erro]. */
function ds_valor_ficha(string $campo, string $v): array {
    $v = trim($v);
    return match ($campo) {
        'whatsapp', 'telefone' => ($t = ds_fone($v)) ? [$t, null] : [null, 'número inválido'],
        'instagram' => preg_match('/^@?([a-z0-9._]{2,30})$/i', ($x = ds_instagram($v) ?? $v), $m) ? [strtolower($m[1]), null] : [null, 'perfil inválido'],
        'facebook' => ($x = ds_facebook($v)) ? [$x, null] : [null, 'link do Facebook inválido'],
        'email' => ($x = ds_email($v)) ? [$x, null] : [null, 'e-mail inválido'],
        'servicos' => mb_strlen($v) >= 3 ? [mb_substr($v, 0, 500), null] : [null, 'vazio'],
        'endereco' => mb_strlen($v) >= 6 && !endereco_invalido($v) ? [mb_substr($v, 0, 200), null] : [null, 'endereço inválido'],
    };
}

if (e_post()) {
    exigir_post();
    $id = (int)($_POST['id'] ?? 0);
    $p = linha('SELECT * FROM profissionais WHERE id = ?', [$id]);
    if (!$p) ds_resposta('Não encontrado.', 'erro', $voltar);
    $nome = nome_curto((string)$p['nome']);
    $acao = (string)($_POST['acao'] ?? '');
    if ($acao === 'aprovar' || $acao === 'recusar') {
        $itens = linhas("SELECT * FROM dados_site WHERE profissional_id = ? AND situacao = 'pendente'" . ($campo_f ? ' AND campo = ?' : ''), $campo_f ? [$id, $campo_f] : [$id]);
        if (!$itens) ds_resposta("$nome: nada pendente.", 'erro', $voltar);
        $marcados = $acao === 'aprovar' ? array_map('strval', (array)($_POST['usar'] ?? [])) : [];
        $novos = (array)($_POST['valor'] ?? []);
        $mudar = []; $feitos = []; $erros = [];
        foreach ($itens as $it) {
            $c = $it['campo'];
            if (!in_array($c, $marcados, true)) continue;
            [$v, $erro] = ds_valor_ficha($c, (string)($novos[$c] ?? $it['valor']));
            if ($erro) { $erros[] = DS_CAMPOS[$c] . ": $erro"; continue; }
            $mudar[$c] = $v;
        }
        if ($erros) ds_resposta(implode(' · ', $erros) . '. Nada foi salvo.', 'erro', $voltar);
        $sets = []; $vals = [];
        foreach ($mudar as $c => $v) {
            $col = DS_COLUNA[$c];
            q("UPDATE dados_site SET valor = ?, valor_antes = ?, situacao = 'aprovado', decidido_em = NOW(), decidido_por = ? WHERE profissional_id = ? AND campo = ?",
                [$v, $p[$col], $admin['id'], $id, $c]);
            $sets[] = "$col = ?"; $vals[] = $v;
            $feitos[] = DS_CAMPOS[$c];
            // endereço novo: o ponto do mapa tirado da rua antiga é refeito (o exato do Google fica)
            if ($c === 'endereco' && $p['geo_origem'] !== 'google') $sets[] = 'lat = NULL, lng = NULL, geo_origem = NULL, geo_tentado = NULL';
        }
        if ($sets) { $vals[] = $id; q('UPDATE profissionais SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals); }
        $recusados = array_values(array_diff(array_column($itens, 'campo'), array_keys($mudar)));
        foreach ($recusados as $c)
            q("UPDATE dados_site SET situacao = 'recusado', decidido_em = NOW(), decidido_por = ? WHERE profissional_id = ? AND campo = ?", [$admin['id'], $id, $c]);
        registrar((int)$admin['id'], 'dados_site_aprovado', $id, 'aprovado: ' . (implode(', ', $feitos) ?: 'nada') . ' · recusado: ' . (implode(', ', $recusados) ?: 'nada'));
        if ($feitos) web_cache_limpar_ficha($id);   // passo 76: só a cidade
        ds_resposta($feitos ? "✅ $nome: " . implode(', ', $feitos) . ' na ficha' . ($recusados ? ' (' . count($recusados) . ' recusado' . (count($recusados) > 1 ? 's' : '') . ')' : '') . '.' : "$nome: recusado.",
            'ok', $voltar, $feitos ? 'aprovado' : 'recusado');
    } elseif ($acao === 'desfazer') {
        $c = (string)($_POST['campo'] ?? '');
        $it = isset(DS_COLUNA[$c]) ? linha("SELECT * FROM dados_site WHERE profissional_id = ? AND campo = ? AND situacao = 'aprovado'", [$id, $c]) : null;
        if (!$it) ds_resposta('Não encontrado.', 'erro', $voltar);
        $col = DS_COLUNA[$c];
        // só volta se a ficha ainda está com o valor que veio do site (o dono pode ter mudado depois)
        if ((string)$p[$col] === (string)$it['valor']) q("UPDATE profissionais SET $col = ? WHERE id = ?", [$it['valor_antes'] !== '' ? $it['valor_antes'] : null, $id]);
        q("UPDATE dados_site SET situacao = 'recusado', decidido_em = NOW(), decidido_por = ? WHERE id = ?", [$admin['id'], $it['id']]);
        registrar((int)$admin['id'], 'dados_site_desfeito', $id, DS_CAMPOS[$c]);
        web_cache_limpar_ficha($id);
        ds_resposta("$nome: " . DS_CAMPOS[$c] . ' voltou ao que era.', 'ok', $voltar, 'recusado');
    } elseif ($acao === 'dispensar') {
        // sai dos Recusados e a ficha volta para a próxima leitura do robô
        q("DELETE FROM dados_site WHERE profissional_id = ? AND situacao = 'recusado'", [$id]);
        q('DELETE FROM dados_site_lidos WHERE profissional_id = ?', [$id]);
        registrar((int)$admin['id'], 'dados_site_dispensado', $id);
        ds_resposta("$nome: dispensado. Volta para a próxima leitura do robô.", 'ok', $voltar, 'dispensado');
    }
    ds_resposta('Ação desconhecida.', 'erro', $voltar);
}

// contagens
$num = array_column(linhas("SELECT d.situacao, COUNT(DISTINCT d.profissional_id) AS n FROM dados_site d JOIN profissionais p ON p.id = d.profissional_id
    WHERE p.status = 'ativo'" . ($campo_f ? ' AND d.campo = ?' : '') . ' GROUP BY d.situacao', $campo_f ? [$campo_f] : []), 'n', 'situacao');
$por_campo = array_column(linhas("SELECT d.campo, COUNT(*) AS n FROM dados_site d JOIN profissionais p ON p.id = d.profissional_id
    WHERE p.status = 'ativo' AND d.situacao = ? GROUP BY d.campo", [$aba]), 'n', 'campo');
$lidos = array_column(linhas('SELECT situacao, COUNT(*) AS n FROM dados_site_lidos GROUP BY situacao'), 'n', 'situacao');

// fichas desta página (as mais procuradas primeiro) e os dados de cada uma
$cond = 'd.situacao = ?' . ($campo_f ? ' AND d.campo = ?' : '');
$pc = $campo_f ? [$aba, $campo_f] : [$aba];
$total_aba = (int)valor("SELECT COUNT(DISTINCT d.profissional_id) FROM dados_site d JOIN profissionais p ON p.id = d.profissional_id WHERE p.status = 'ativo' AND $cond", $pc);
$paginas = max(1, (int)ceil($total_aba / 30));
$ids = array_column(linhas("SELECT p.id FROM profissionais p WHERE p.status = 'ativo' AND EXISTS (SELECT 1 FROM dados_site d WHERE d.profissional_id = p.id AND $cond)
    ORDER BY p.popularidade DESC, p.id LIMIT 30 OFFSET " . (($pagina - 1) * 30), $pc), 'id');
$por_ficha = [];
if ($ids) {
    $in = implode(',', array_map('intval', $ids));
    foreach (linhas("SELECT d.* FROM dados_site d WHERE d.profissional_id IN ($in) AND $cond ORDER BY FIELD(d.campo, 'whatsapp', 'telefone', 'instagram', 'facebook', 'email', 'servicos', 'endereco')", $pc) as $d)
        $por_ficha[(int)$d['profissional_id']][] = $d;
}
/** Quantas OUTRAS fichas têm esse mesmo valor (no site lido ou na própria ficha): rede ou quem fez o site. */
function ds_repete(array $d): int {
    if (in_array($d['campo'], ['servicos', 'endereco'], true)) return 0;
    $col = DS_COLUNA[$d['campo']];
    $v = $d['campo'] === 'instagram' ? ltrim((string)$d['valor'], '@') : (string)$d['valor'];
    return (int)valor("SELECT COUNT(DISTINCT x) FROM (SELECT profissional_id AS x FROM dados_site WHERE campo = ? AND valor = ? AND profissional_id <> ?
        UNION SELECT id AS x FROM profissionais WHERE $col = ? AND id <> ? AND status = 'ativo') t", [$d['campo'], $v, $d['profissional_id'], $v, $d['profissional_id']]);
}
function ds_mostrar(string $campo, ?string $v): string {
    if ($v === null || $v === '') return '—';
    return match ($campo) { 'whatsapp', 'telefone' => telefone_formatado($v), 'instagram' => '@' . ltrim($v, '@'), default => $v };
}

admin_inicio('Dados pelo site do comércio', $admin);
?>
<h1>🧩 Dados pelo site do comércio</h1>
<p class="discreto">O robô lê o <b>site do próprio comércio</b> e acha WhatsApp, telefone, Instagram, Facebook, e-mail, o que faz ou vende
  e endereço. Nada vai para a ficha sozinho: confira cada linha (dá para corrigir o texto), desmarque o que não serve e aprove. O que ficar
  desmarcado vai para os Recusados.<br>
  <b>Preencher</b> = a ficha está sem (vem marcado). <b>Diferente</b> = a ficha já tem outro (vem desmarcado; só troca se você marcar).
  ⚠️ <b>Aparece em outras fichas</b> = costuma ser da matriz da rede ou de quem fez o site (vem desmarcado).<br>
  Ler mais sites (na VPS): <code>sudo -u www-data php /var/www/guia-novo/servidor/dados-do-site.php --todas --limite=6000</code>
  · Sites lidos: <?= (int)($lidos['lido'] ?? 0) ?> com dados, <?= (int)($lidos['nada'] ?? 0) ?> sem nada novo, <?= (int)($lidos['erro'] ?? 0) ?> não abriram
  · <a href="<?= h(url('admin/sobre-site.php')) ?>">🌐 Sobre pelo site</a> · <a href="<?= h(url('admin/horario-site.php')) ?>">🕒 Horário pelo site</a></p>
<p class="filtros-linha">
  <?php foreach (['pendente' => 'Para aprovar', 'aprovado' => 'Aprovados', 'recusado' => 'Recusados'] as $k => $r): ?>
    <a class="botao <?= $k === $aba ? '' : 'contorno' ?> pequeno" href="<?= h(url('admin/dados-site.php?aba=' . $k . ($campo_f ? '&campo=' . $campo_f : ''))) ?>"><?= h($r) ?> (<span<?= $k === $aba ? ' id="sobre-cont"' : '' ?>><?= (int)($num[$k] ?? 0) ?></span> fichas)</a>
  <?php endforeach ?>
</p>
<p class="filtros-linha">Só: <a class="botao pequeno <?= $campo_f === '' ? '' : 'contorno' ?>" href="<?= h(url('admin/dados-site.php?aba=' . $aba)) ?>">Tudo</a>
  <?php foreach (DS_CAMPOS as $k => $r): ?>
    <a class="botao pequeno <?= $campo_f === $k ? '' : 'contorno' ?>" href="<?= h(url('admin/dados-site.php?aba=' . $aba . '&campo=' . $k)) ?>"><?= h($r) ?> (<?= (int)($por_campo[$k] ?? 0) ?>)</a>
  <?php endforeach ?></p>
<?php mostrar_flash(); ?>
<?php if (!$ids): ?><div class="aviso ok">Nada aqui.</div><?php endif ?>
<?php foreach ($ids as $pid): $p = linha('SELECT id, nome, cidade, tipo, telefone FROM profissionais WHERE id = ?', [$pid]); $ficha = web_ficha((int)$pid); $itens = $por_ficha[(int)$pid] ?? []; if (!$itens) continue; ?>
  <div class="quadro quadro-sobre">
    <h2 style="margin:0 0 4px"><?= h(nome_curto((string)$p['nome'])) ?> <small class="discreto">#<?= (int)$pid ?> · <?= h(nome_cidade((string)$p['cidade'])) ?><?= $p['tipo'] ? ' · ' . h($p['tipo']) : '' ?></small></h2>
    <p class="discreto" style="margin:0 0 8px">Site: <a href="<?= h($itens[0]['url']) ?>" target="_blank" rel="noopener"><?= h($itens[0]['url']) ?></a>
      <?php if ($ficha): ?> · <a href="<?= h(url_ficha($ficha)) ?>" target="_blank">ver a ficha</a><?php endif ?>
      · <a href="<?= h(url('admin/comercio.php?id=' . $pid)) ?>" target="_blank">editar</a></p>
    <?php if ($aba === 'aprovado'): ?>
      <?php foreach ($itens as $d): ?>
        <form method="post" class="ds-linha"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$pid ?>"><input type="hidden" name="campo" value="<?= h($d['campo']) ?>"><input type="hidden" name="pagina" value="<?= $pagina ?>">
          <b><?= h(DS_CAMPOS[$d['campo']]) ?>:</b> <?= h(ds_mostrar($d['campo'], $d['valor'])) ?>
          <?php if ($d['valor_antes'] !== null && $d['valor_antes'] !== ''): ?><span class="discreto">(antes: <?= h(ds_mostrar($d['campo'], $d['valor_antes'])) ?>)</span><?php endif ?>
          <button class="botao contorno pequeno" name="acao" value="desfazer">Desfazer</button></form>
      <?php endforeach ?>
    <?php else: ?>
      <form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$pid ?>"><input type="hidden" name="pagina" value="<?= $pagina ?>">
        <?php foreach ($itens as $d): $rep = ds_repete($d);
          $ddd_outro = in_array($d['campo'], ['whatsapp', 'telefone'], true) && $p['telefone'] && !telefone_central($d['valor']) && substr(so_digitos($p['telefone']), 0, 2) !== substr($d['valor'], 0, 2);
          $marcado = $aba === 'pendente' && $d['tipo'] === 'preencher' && !$rep && !$ddd_outro; ?>
          <div class="ds-linha">
            <label class="marcar"><input type="checkbox" name="usar[]" value="<?= h($d['campo']) ?>"<?= $marcado ? ' checked' : '' ?>> <b><?= h(DS_CAMPOS[$d['campo']]) ?></b></label>
            <span class="ds-tipo ds-<?= h($d['tipo']) ?>"><?= $d['tipo'] === 'preencher' ? 'preencher' : 'diferente' ?></span>
            <?php if (in_array($d['campo'], ['servicos', 'endereco'], true)): ?>
              <textarea name="valor[<?= h($d['campo']) ?>]" rows="<?= $d['campo'] === 'servicos' ? 2 : 1 ?>" maxlength="<?= $d['campo'] === 'servicos' ? 500 : 200 ?>"><?= h($d['valor']) ?></textarea>
            <?php else: ?>
              <input type="text" name="valor[<?= h($d['campo']) ?>]" value="<?= h($d['campo'] === 'instagram' ? '@' . $d['valor'] : $d['valor']) ?>" maxlength="300">
              <?php if ($d['campo'] === 'whatsapp' || $d['campo'] === 'telefone'): ?><span class="discreto"><?= h(telefone_formatado($d['valor'])) ?></span><?php endif ?>
              <?php if ($d['campo'] === 'instagram'): ?><a href="https://instagram.com/<?= h($d['valor']) ?>" target="_blank" rel="noopener">abrir</a><?php endif ?>
              <?php if ($d['campo'] === 'facebook'): ?><a href="<?= h($d['valor']) ?>" target="_blank" rel="noopener">abrir</a><?php endif ?>
            <?php endif ?>
            <?php if ($d['valor_ficha']): ?><div class="discreto">Na ficha hoje: <?= h(ds_mostrar($d['campo'], $d['valor_ficha'])) ?><?= $d['campo'] === 'endereco' ? ' (inválido)' : '' ?></div><?php endif ?>
            <?php if ($d['trecho']): ?><div class="discreto"><?= h($d['trecho']) ?></div><?php endif ?>
            <?php if ($rep): ?><div class="ds-alerta">⚠️ Aparece em <?= $rep ?> outra<?= $rep > 1 ? 's' : '' ?> ficha<?= $rep > 1 ? 's' : '' ?>: pode ser da matriz da rede ou de quem fez o site.</div><?php endif ?>
            <?php if ($ddd_outro): ?><div class="ds-alerta">⚠️ DDD diferente do telefone da ficha: pode ser de outra cidade.</div><?php endif ?>
          </div>
        <?php endforeach ?>
        <p style="margin:8px 0 0"><button class="botao pequeno" name="acao" value="aprovar">✅ Aprovar os marcados</button>
          <?php if ($aba === 'pendente'): ?><button class="botao contorno pequeno" name="acao" value="recusar">Recusar tudo</button><?php endif ?>
          <?php if ($aba === 'recusado'): ?><button class="botao contorno pequeno" name="acao" value="dispensar" title="Tira dos recusados; o robô lê este site de novo na próxima vez">↩️ Dispensar</button><?php endif ?></p>
      </form>
    <?php endif ?>
  </div>
<?php endforeach ?>
<?php if ($paginas > 1): ?><p class="filtros-linha">Páginas:
  <?php for ($n = 1; $n <= $paginas; $n++): ?><a class="botao pequeno <?= $n === $pagina ? '' : 'contorno' ?>" href="<?= h(url('admin/dados-site.php?aba=' . $aba . ($campo_f ? '&campo=' . $campo_f : '') . '&pagina=' . $n)) ?>"><?= $n ?></a> <?php endfor ?>
  <span class="discreto"><?= number_format($total_aba, 0, ',', '.') ?> fichas no total</span></p><?php endif ?>
<script>
// aprova/recusa sem recarregar: o quadro fica verde (ou cinza) com o resultado e some da lista (igual ao Sobre pelo site)
(function () {
  var cont = document.getElementById('sobre-cont');
  document.addEventListener('submit', function (ev) {
    var f = ev.target; if (!f.closest('.quadro-sobre')) return;
    ev.preventDefault();
    var b = ev.submitter, q = f.closest('.quadro-sobre'), so_linha = f.classList.contains('ds-linha');
    var dados = new FormData(f); dados.set('ajax', '1'); if (b && b.name) dados.set(b.name, b.value);
    f.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
    fetch(location.href, { method: 'POST', body: dados, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (t) {
        var j; try { j = JSON.parse(t); } catch (e) { j = { ok: false, msg: t || 'Erro no servidor.' }; }
        var m = document.createElement('p'); m.className = 'sobre-msg'; m.textContent = j.msg;
        if (!j.ok) { m.style.color = '#b42318'; f.appendChild(m); f.querySelectorAll('button').forEach(function (x) { x.disabled = false; }); return; }
        if (so_linha) { f.innerHTML = ''; f.appendChild(m); return; }   // Desfazer de uma linha dos Aprovados
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
.ds-linha { border-top: 1px solid #eee; padding: 6px 0; } .ds-linha:first-of-type { border-top: 0; }
.ds-linha input[type=text], .ds-linha textarea { width: 100%; max-width: 520px; margin: 4px 0 0; display: block; }
.ds-linha .marcar { display: inline-flex; gap: 6px; align-items: center; min-width: 170px; }
.ds-tipo { font-size: .8em; padding: 1px 8px; border-radius: 10px; } .ds-preencher { background: #e3f2ea; color: #1b6b3a; } .ds-diferente { background: #fff1d6; color: #8a5a00; }
.ds-alerta { color: #8a5a00; font-size: .9em; margin-top: 2px; }</style>
<?php admin_fim();
