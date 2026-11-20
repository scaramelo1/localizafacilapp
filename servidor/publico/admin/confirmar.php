<?php
// Plano D, passo 11 (20/11): "Confirmar fichas" por telefone. Um comércio por vez, na ordem da prioridade das áreas
// (Veículos, Saúde, Alimentação, Casa, Mercados, Lojas, outras) e, em cada área, os mais conhecidos. A pessoa liga,
// corrige o que precisar e marca: confirmado / não atende (volta amanhã) / mandei o link (volta em 3 dias se o dono
// não confirmar) / fechou (sai do site). Regras e fila em inc/confirmacao.php.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/confirmacao.php';
$admin = exigir_admin();

$cidades = array_keys(CIDADES);
$cidade = in_array($_GET['cidade'] ?? $_POST['cidade'] ?? '', $cidades, true) ? (string)($_GET['cidade'] ?? $_POST['cidade']) : $cidades[0];
$pular = max(0, (int)($_GET['pular'] ?? 0));
$voltar = 'admin/confirmar.php?cidade=' . rawurlencode($cidade);

if (e_post()) {
    exigir_post();
    $id = (int)($_POST['id'] ?? 0);
    $p = linha('SELECT * FROM profissionais WHERE id = ?', [$id]);
    $acao = (string)($_POST['acao'] ?? '');
    if (!$p) { flash('Comércio não encontrado.', 'erro'); ir($voltar); }
    $nome = nome_curto((string)$p['nome']);

    if ($acao === 'confirmar') {
        // correções rápidas feitas durante a ligação (vão direto, como em Comércios > editar)
        $dados = [];
        foreach (['telefone', 'whatsapp', 'endereco', 'bairro'] as $c) {
            if (!array_key_exists($c, $_POST)) continue;   // campo que não veio não muda
            $t = trim(preg_replace('/\s+/', ' ', (string)($_POST[$c] ?? '')));
            $v = $t === '' ? null : mb_substr($t, 0, CAMPOS_DONO[$c][1]);
            if (in_array($c, ['telefone', 'whatsapp'], true) && $v !== null && !($v = telefone_normalizado($v))) {
                flash(CAMPOS_DONO[$c][0] . ': digite com DDD. Nada foi salvo.', 'erro'); ir($voltar . '&pular=' . $pular);
            }
            if ($v !== ($p[$c] === null ? null : (string)$p[$c])) $dados[$c] = $v;
        }
        [$hor, $erro_hor] = horarios_do_post((array)($_POST['hor'] ?? []));
        if ($erro_hor) { flash($erro_hor . ' Nada foi salvo.', 'erro'); ir($voltar . '&pular=' . $pular); }
        if ($hor !== null && $hor !== $p['horarios']) { $dados['horarios'] = $hor; $dados['horario'] = horarios_texto(horarios_ler($hor)); }
        if (isset($dados['endereco']) || array_key_exists('bairro', $dados))
            if ($p['geo_origem'] !== 'manual') $dados += ['lat' => null, 'lng' => null, 'geo_origem' => null, 'geo_tentado' => null];
        if ($dados) {
            atualizar('profissionais', $id, $dados);
            registrar((int)$admin['id'], 'editou_comercio', $id, implode(', ', array_keys($dados)) . ' (confirmar por telefone)');
        }
        marcar_confirmada($id, 'equipe');
        q('DELETE FROM confirmar_fila WHERE profissional_id = ?', [$id]);
        registrar((int)$admin['id'], 'confirmou_ficha', $id, 'por telefone');
        web_cache_limpar();
        flash("✅ $nome confirmado" . ($dados ? ' (com correção)' : '') . '.');
    } elseif ($acao === 'nao_atende') {
        fila_marcar($id, 'nao_atende', (int)$admin['id'], FILA_DIAS_NAO_ATENDE);
        registrar((int)$admin['id'], 'fila_nao_atende', $id);
        flash("📵 $nome não atendeu. Volta para a fila amanhã.");
    } elseif ($acao === 'fechou') {
        q("UPDATE profissionais SET status = 'pausado' WHERE id = ?", [$id]);
        fila_marcar($id, 'fechou', (int)$admin['id'], 36500);
        registrar((int)$admin['id'], 'fila_fechou', $id, 'tirado do site (pausado)');
        web_cache_limpar();
        flash("❌ $nome saiu do site (pausado). Dá para voltar em Comércios > editar.");
    } elseif ($acao === 'link') {
        $url = confirmacao_criar_link($id, (int)$admin['id']);
        $wa = confirmacao_link_whats($p, $url);
        fila_marcar($id, 'link', (int)$admin['id'], FILA_DIAS_LINK);
        registrar((int)$admin['id'], 'mandou_link_confirmacao', $id);
        $_SESSION['confirmar_link'] = ['nome' => $nome, 'wa' => $wa, 'url' => $url, 'msg' => confirmacao_mensagem($p, $url)];
    } elseif ($acao === 'pular') {
        fila_marcar($id, 'pulou', (int)$admin['id'], 1 / 24);
    }
    ir($voltar);
}

$p = fila_proxima($cidade, $pular);
$num = fila_numeros($cidade);
$pct = min(100, (int)round($num['confirmadas'] * 100 / META_CONFIRMADAS));
$link_feito = $_SESSION['confirmar_link'] ?? null;
unset($_SESSION['confirmar_link']);
admin_inicio('Confirmar fichas', $admin);
?>
<h1>✅ Confirmar fichas</h1>
<p class="discreto">Ligue, confira os dados e marque. A ficha confirmada ganha o selo “Dados conferidos” e entra no Google fora da fila das ondas.
  Ordem: Veículos, Saúde, Alimentação, Casa, Mercados, Lojas, outras; em cada área, os mais conhecidos.</p>
<form method="get" class="filtros-linha">
  <label for="cidade">Cidade</label>
  <select id="cidade" name="cidade" onchange="this.form.submit()"><?php foreach (CIDADES as $s => $c): ?><option value="<?= h($s) ?>"<?= $s === $cidade ? ' selected' : '' ?>><?= h($c[0]) ?></option><?php endforeach ?></select>
</form>
<div class="quadro">
  <strong><?= $num['confirmadas'] ?> de <?= META_CONFIRMADAS ?> confirmadas</strong> em <?= h(nome_cidade($cidade)) ?>
  <span style="display:inline-block;width:160px;height:10px;background:#e5e7eb;border-radius:6px;vertical-align:middle;margin:0 8px"><span style="display:block;height:10px;width:<?= $pct ?>%;background:#005C38;border-radius:6px"></span></span><?= $pct ?>%
  <br><span class="discreto"><?= number_format($num['na_fila'], 0, ',', '.') ?> na fila ·
    <?= (int)($num['esperando']['link'] ?? 0) ?> esperando o dono pelo link · <?= (int)($num['esperando']['nao_atende'] ?? 0) ?> não atenderam (voltam amanhã) ·
    <?= (int)($num['esperando']['fechou'] ?? 0) ?> fecharam</span>
</div>
<?php mostrar_flash(); ?>
<?php if ($link_feito): ?>
  <div class="aviso ok">
    <strong>Link de confirmação de <?= h($link_feito['nome']) ?> pronto.</strong>
    <?php if ($link_feito['wa']): ?>
      <a class="botao" href="<?= h($link_feito['wa']) ?>" target="_blank" rel="noopener">💬 Abrir o WhatsApp com a mensagem</a>
    <?php else: ?>
      O comércio não tem WhatsApp nem celular: copie a mensagem e mande por onde der.
    <?php endif ?>
    <p style="margin:8px 0 0"><textarea readonly rows="3" style="width:100%" onclick="this.select()"><?= h($link_feito['msg']) ?></textarea></p>
  </div>
<?php endif ?>

<?php if (!$p): ?>
  <div class="aviso ok"><strong>Fila vazia em <?= h(nome_cidade($cidade)) ?>.</strong> Os que não atenderam ou receberam o link voltam sozinhos depois.</div>
<?php else: $tel = (string)($p['telefone'] ?? ''); $wa_num = confirmacao_numero_whats($p); ?>
  <div class="quadro">
    <h2 style="margin-top:0"><?= h($p['nome']) ?></h2>
    <p class="discreto"><?= h(implode(' · ', array_filter([$p['categoria'], $p['tipo'], $p['bairro']]))) ?>
      · <?= (int)$p['popularidade'] ?> avaliações no Google
      <?= $p['fila_situacao'] ? ' · já tentado ' . (int)$p['fila_tentativas'] . 'x (' . h(str_replace('_', ' ', $p['fila_situacao'])) . ', ' . h(datahora_br($p['fila_quando'])) . ')' : '' ?></p>
    <p>
      <?php if ($tel): ?><a class="botao" href="tel:<?= h(telefone_discar($tel)) ?>">📞 Ligar <?= h(telefone_formatado($tel)) ?></a><?php endif ?>
      <?php if ($wa_num): ?><a class="botao contorno" href="<?= h(link_whatsapp($wa_num)) ?>" target="_blank" rel="noopener">💬 WhatsApp <?= h(telefone_formatado($wa_num)) ?></a><?php endif ?>
      <?php if ($p['mapa']): ?><a class="botao contorno" href="<?= h($p['mapa']) ?>" target="_blank" rel="noopener">🗺️ Mapa</a><?php endif ?>
      <a class="botao contorno" href="<?= h(url_ficha($p)) ?>" target="_blank" rel="noopener">Ver ficha</a>
      <a class="botao contorno" href="comercio.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">Editar tudo</a>
    </p>
    <p class="discreto">Roteiro: “Olá, aqui é do Localiza Fácil, o guia de comércios de <?= h(nome_cidade($cidade)) ?>. Estou conferindo os dados da <?= h(nome_curto((string)$p['nome'])) ?>:
      o endereço é <?= h($p['endereco'] ?: '...') ?>? Esse número é WhatsApp? Que horas vocês abrem e fecham?”</p>

    <form method="post" class="form-admin-comercio"><?= campo_csrf() ?>
      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="cidade" value="<?= h($cidade) ?>">
      <label for="telefone">Telefone</label><input id="telefone" name="telefone" inputmode="tel" value="<?= h(telefone_formatado($p['telefone'])) ?>">
      <label for="whatsapp">WhatsApp</label><input id="whatsapp" name="whatsapp" inputmode="tel" value="<?= h(telefone_formatado($p['whatsapp'])) ?>">
      <label for="endereco">Endereço</label><input id="endereco" name="endereco" maxlength="200" value="<?= h($p['endereco']) ?>">
      <label for="bairro">Bairro</label><input id="bairro" name="bairro" maxlength="80" value="<?= h($p['bairro']) ?>">
      <details><summary><strong>Horário</strong> (se o comércio falou)</summary><?= horarios_form(horarios_ler($p['horarios'])) ?></details>
      <p style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">
        <button class="botao" name="acao" value="confirmar">✅ Confirmado</button>
        <button class="botao contorno" name="acao" value="link">💬 Mandar link de confirmação</button>
        <button class="botao contorno" name="acao" value="nao_atende">📵 Não atende</button>
        <button class="botao contorno" name="acao" value="pular">⏭️ Pular</button>
        <button class="botao contorno" name="acao" value="fechou" onclick="return confirm('O comércio fechou ou não existe? Ele sai do site (fica pausado).')">❌ Fechou / não existe</button>
      </p>
    </form>
  </div>
<?php endif ?>
<?php admin_fim();
