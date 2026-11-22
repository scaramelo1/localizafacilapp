<?php
// 22/11 (passo 45): o dono (Destaque em dia) monta o catálogo da ficha: Cardápio, Produtos, Ofertas, Bebidas, Peças...
// conforme o segmento (inc/catalogo.php). Publica na hora (sem conferência: é plano pago e só mexe no catálogo).
// O admin abre o de qualquer comércio por /painel/catalogo.php?id=<comércio> (link no admin, Comércios > editar).
require __DIR__ . '/../../inc/moderacao.php';
require __DIR__ . '/../../inc/pagina.php';
iniciar_sessao();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$admin = admin_atual();
$p = null;
if ($admin && isset($_GET['id'])) {
    $p = linha('SELECT * FROM profissionais WHERE id = ?', [(int)$_GET['id']]);
} else {
    require_once __DIR__ . '/../../inc/conta-web.php';
    if (empty($_SESSION['dono_acesso']) && ($conta = usuario_web()) && ($ja = acesso_da_conta((int)$conta['id']))) $_SESSION['dono_acesso'] = $ja;
    $p = dono_atual();
}
if (!$p) { flash('Entre no painel do seu comércio primeiro.', 'erro'); ir('painel/'); }
$pid = (int)$p['id'];
$p = linha('SELECT p.*, c.slug AS categoria_slug, ' . SQL_DESTAQUE . ' AS destaque_pago FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE p.id = ?', [$pid]);
$destaque = !empty($p['destaque_pago']);
$pode_editar = $destaque || $admin;
$mod = catalogo_modelo($p);
$volta = $admin && isset($_GET['id']) ? '?id=' . $pid : '';
$erro = null;

if (e_post() && $pode_editar) {
    exigir_post();
    $fotos = [];
    $n_fotos = 0;
    try {
        foreach ((array)($_FILES['foto_item']['tmp_name'] ?? []) as $id => $tmp) {
            if (!preg_match('/^[a-z0-9]{6,16}$/', (string)$id) || ($_FILES['foto_item']['error'][$id] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if (++$n_fotos > CATALOGO_MAX_FOTOS) throw new RuntimeException('Até ' . CATALOGO_MAX_FOTOS . ' fotos por vez.');
            $f = ['tmp_name' => $tmp, 'error' => $_FILES['foto_item']['error'][$id], 'size' => $_FILES['foto_item']['size'][$id], 'name' => $_FILES['foto_item']['name'][$id]];
            $fotos[(string)$id] = salvar_foto_enviada($f, $pid, 600, true);
        }
        [, $erro] = catalogo_salvar($pid, (array)($_POST['secao'] ?? []), (array)($_POST['item'] ?? []), (string)($_POST['validade_ate'] ?? ''), $fotos);
    } catch (RuntimeException $e) {
        $erro = $e->getMessage();
    }
    if (!$erro) {
        registrar($admin ? (int)$admin['id'] : null, $admin ? 'editou_catalogo' : 'dono_catalogo', $pid);
        flash('✅ Salvo! O ' . mb_strtolower($mod['curto']) . ' já aparece na sua ficha.');
        ir('painel/catalogo.php' . $volta);
    }
}

$dados = catalogo_dados($p['cardapio'] ?? null);
// primeira vez: começa com as seções sugeridas do segmento (o dono apaga ou troca o que não usar)
$secoes = $dados['secoes'] ?: array_map(fn($n) => ['nome' => $n, 'itens' => []], $mod['secoes']);
$mais_pedidos = catalogo_mais_pedidos($pid, desde_dias(30));
$novo_id = fn() => substr(bin2hex(random_bytes(6)), 0, 10);
$val = fn($v) => h((string)$v);
$preco_campo = fn($v) => $v === null || $v === '' ? '' : number_format((float)$v, 2, ',', '');

$item_html = function (array $it, string $sec) use ($mod, $val, $preco_campo): string {
    $id = h((string)($it['id'] ?? ''));
    ob_start(); ?>
    <div class="cat-item" data-id="<?= $id ?>">
      <input type="hidden" name="item[<?= $id ?>][secao]" value="<?= h($sec) ?>" class="cat-secao-ref">
      <div class="cat-linha">
        <label class="cat-nome">Nome<input name="item[<?= $id ?>][nome]" maxlength="120" value="<?= $val($it['nome'] ?? '') ?>" placeholder="Ex.: <?= h($mod['chave'] === 'comida' ? 'X-Salada' : ($mod['chave'] === 'pecas' ? 'Pastilha de freio' : 'Nome do produto')) ?>"></label>
        <label class="cat-preco">Preço (R$)<input name="item[<?= $id ?>][preco]" inputmode="decimal" maxlength="12" value="<?= $preco_campo($it['preco'] ?? null) ?>" placeholder="0,00"></label>
      </div>
      <label>Descrição (opcional)<input name="item[<?= $id ?>][desc]" maxlength="300" value="<?= $val($it['desc'] ?? '') ?>"></label>
      <details class="cat-mais"<?= !empty($it['preco_de']) || !empty($it['etiquetas']) || !empty($it['unidade']) || !empty($it['ate']) || !empty($it['foto']) ? ' open' : '' ?>><summary>Mais opções: foto, promoção<?= $mod['etiquetas'] ? ', detalhes' : '' ?><?= $mod['unidade'] ? ', unidade' : '' ?></summary>
        <div class="cat-linha">
          <label>Preço antes (promoção)<input name="item[<?= $id ?>][preco_de]" inputmode="decimal" maxlength="12" value="<?= $preco_campo($it['preco_de'] ?? null) ?>" placeholder="riscado"></label>
          <?php if ($mod['unidade']): ?><label>Unidade<input name="item[<?= $id ?>][unidade]" maxlength="20" value="<?= $val($it['unidade'] ?? '') ?>" placeholder="kg, un, fardo"></label><?php endif ?>
          <label>Oferta até (opcional)<input type="date" name="item[<?= $id ?>][ate]" value="<?= $val($it['ate'] ?? '') ?>"></label>
        </div>
        <?php if ($mod['etiquetas']): ?><label><?= h($mod['etiquetas']) ?><input name="item[<?= $id ?>][etiquetas]" maxlength="300" value="<?= $val(implode(', ', (array)($it['etiquetas'] ?? []))) ?>"></label><?php endif ?>
        <div class="cat-foto">
          <?php if (!empty($it['foto'])): ?><img src="<?= h(foto_url((string)$it['foto'])) ?>" alt="" width="64" height="64"><label class="marcar"><input type="checkbox" name="item[<?= $id ?>][tirar_foto]" value="1"> Tirar a foto</label><?php endif ?>
          <label>Foto (opcional)<input type="file" name="foto_item[<?= $id ?>]" accept="image/*"></label>
        </div>
      </details>
      <div class="cat-opcoes">
        <label class="marcar"><input type="checkbox" name="item[<?= $id ?>][a_partir]" value="1"<?= !empty($it['a_partir']) ? ' checked' : '' ?>> "a partir de"</label>
        <label class="marcar"><input type="checkbox" name="item[<?= $id ?>][destaque]" value="1"<?= !empty($it['destaque']) ? ' checked' : '' ?>> ⭐ Mais pedido</label>
        <label class="marcar esgot"><input type="checkbox" name="item[<?= $id ?>][esgotado]" value="1"<?= !empty($it['esgotado']) ? ' checked' : '' ?>> Esgotado</label>
        <span class="cat-mover"><button type="button" data-acao="subir" title="Subir">↑</button><button type="button" data-acao="descer" title="Descer">↓</button>
          <button type="button" data-acao="tirar-item" class="cat-tirar" title="Apagar item">🗑️</button></span>
      </div>
    </div>
    <?php return (string)ob_get_clean();
};

pagina_inicio($mod['titulo'] . ' | Painel | Localiza Fácil App', 'Monte o catálogo do seu comércio no Localiza Fácil.');
?>
<main class="documento catalogo-editor">
  <p><a href="<?= h(url('painel/')) ?>">&larr; Painel do comércio</a><?php if ($admin && isset($_GET['id'])): ?> · <a href="<?= h(url('admin/comercio.php?id=' . $pid)) ?>">editar no admin</a><?php endif ?></p>
  <h1><?= h($mod['aba']) ?></h1>
  <p class="discreto"><?= h($p['nome']) ?> · aparece numa aba própria da sua ficha, no site e no app<?= !empty($dados['atualizado']) ? ' · atualizado em ' . h(catalogo_dia((string)$dados['atualizado'])) : '' ?>.</p>
  <?php mostrar_flash() ?>
  <?php if (!$pode_editar): ?>
    <div class="quadro"><strong>O <?= h(mb_strtolower($mod['curto'])) ?> é do plano Destaque.</strong> Mostre seus produtos e preços numa aba da sua ficha,
      e quem procurar por um deles (ex.: "<?= h($mod['chave'] === 'comida' ? 'x-tudo' : ($mod['chave'] === 'pecas' ? 'pastilha de freio' : 'vestido')) ?>") acha o seu comércio na busca.
      <br><a class="botao amarelo" href="<?= h(url('perfil.php?acao=assumir&plano=destaque&id=' . $pid)) ?>">Quero o Destaque</a></div>
  <?php else: ?>
    <?php if (!$destaque): ?><p class="aviso">Admin: este comércio não é Destaque. O catálogo fica guardado, mas só aparece na ficha com o Destaque em dia.</p><?php endif ?>
    <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
    <?php if ($mais_pedidos): ?>
      <div class="quadro"><strong>Mais pedidos pelo WhatsApp (30 dias)</strong>
        <ol class="cat-mais-pedidos"><?php foreach ($mais_pedidos as $m): ?><li><?= h($m['item_nome']) ?> <b><?= num((int)$m['total']) ?>×</b></li><?php endforeach ?></ol></div>
    <?php endif ?>
    <div class="quadro dica-cat">💡 Dicas: nome curto e claro (é o que as pessoas procuram na busca), preço sempre que puder e marque
      <b>Esgotado</b> em vez de apagar. Cada item ganha o botão <b>💬 Pedir</b>, que abre o seu WhatsApp já com o nome do item.</div>

    <form method="post" enctype="multipart/form-data" id="form-catalogo">
      <?= campo_csrf() ?>
      <?php if ($mod['validade']): ?>
        <label for="validade_ate"><b>Ofertas válidas até</b> (depois desse dia elas saem sozinhas da ficha)</label>
        <input type="date" id="validade_ate" name="validade_ate" value="<?= $val($dados['validade_ate'] ?? '') ?>">
      <?php endif ?>
      <div id="cat-secoes">
        <?php foreach ($secoes as $s): $sk = substr(bin2hex(random_bytes(4)), 0, 8); ?>
        <fieldset class="cat-secao" data-secao="<?= $sk ?>">
          <div class="cat-secao-topo"><input class="cat-secao-nome" name="secao[<?= $sk ?>]" maxlength="60" value="<?= $val($s['nome']) ?>" aria-label="Nome da seção" placeholder="Nome da seção">
            <span class="cat-mover"><button type="button" data-acao="subir-secao" title="Subir seção">↑</button><button type="button" data-acao="descer-secao" title="Descer seção">↓</button>
            <button type="button" data-acao="tirar-secao" class="cat-tirar" title="Apagar seção">🗑️</button></span></div>
          <div class="cat-itens"><?php foreach ((array)$s['itens'] as $it) echo $item_html($it + ['id' => $it['id'] ?? $novo_id()], $sk); ?></div>
          <button type="button" class="botao pequeno contorno" data-acao="novo-item">+ Item em "<span class="cat-rot"><?= $val($s['nome']) ?></span>"</button>
        </fieldset>
        <?php endforeach ?>
      </div>
      <p><button type="button" class="botao contorno" data-acao="nova-secao">+ Nova seção</button></p>
      <p class="cat-salvar"><button class="botao amarelo">Salvar <?= h(mb_strtolower($mod['curto'])) ?></button>
        <a class="botao contorno" href="<?= h(url_publica(caminho_ficha($pid, (string)$p['nome'], (string)$p['cidade']))) ?>#cardapio" target="_blank" rel="noopener">Ver na ficha</a></p>
    </form>
    <template id="modelo-item"><?= $item_html(['id' => '__ID__'], '__SEC__') ?></template>
    <template id="modelo-secao"><fieldset class="cat-secao" data-secao="__SEC__">
      <div class="cat-secao-topo"><input class="cat-secao-nome" name="secao[__SEC__]" maxlength="60" value="" aria-label="Nome da seção" placeholder="Nome da seção (ex.: Promoções)">
        <span class="cat-mover"><button type="button" data-acao="subir-secao">↑</button><button type="button" data-acao="descer-secao">↓</button>
        <button type="button" data-acao="tirar-secao" class="cat-tirar">🗑️</button></span></div>
      <div class="cat-itens"></div>
      <button type="button" class="botao pequeno contorno" data-acao="novo-item">+ Item em "<span class="cat-rot">nova seção</span>"</button>
    </fieldset></template>
    <script>
    (function () {
      var box = document.getElementById('cat-secoes');
      function novoId() { return Math.random().toString(36).slice(2, 12).replace(/[^a-z0-9]/g, 'x').padEnd(8, 'x'); }
      function de(modelo, troca) { var t = document.getElementById(modelo).innerHTML; for (var k in troca) t = t.split(k).join(troca[k]); var d = document.createElement('div'); d.innerHTML = t.trim(); return d.firstElementChild; }
      document.getElementById('form-catalogo').addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-acao]'); if (!b) return;
        var acao = b.dataset.acao, item = b.closest('.cat-item'), sec = b.closest('.cat-secao');
        if (acao === 'novo-item') { var n = de('modelo-item', { '__ID__': novoId(), '__SEC__': sec.dataset.secao }); sec.querySelector('.cat-itens').appendChild(n); n.querySelector('input:not([type=hidden])').focus(); }
        else if (acao === 'nova-secao') { var s = de('modelo-secao', { '__SEC__': novoId() }); box.appendChild(s); s.querySelector('.cat-secao-nome').focus(); }
        else if (acao === 'tirar-item') { if (confirm('Apagar este item?')) item.remove(); }
        else if (acao === 'tirar-secao') { if (confirm('Apagar a seção e todos os itens dela?')) sec.remove(); }
        else if (acao === 'subir' && item.previousElementSibling) item.parentNode.insertBefore(item, item.previousElementSibling);
        else if (acao === 'descer' && item.nextElementSibling) item.parentNode.insertBefore(item.nextElementSibling, item);
        else if (acao === 'subir-secao' && sec.previousElementSibling) box.insertBefore(sec, sec.previousElementSibling);
        else if (acao === 'descer-secao' && sec.nextElementSibling) box.insertBefore(sec.nextElementSibling, sec);
      });
      box.addEventListener('input', function (ev) {   // o botão "+ Item em ..." acompanha o nome da seção
        if (ev.target.classList.contains('cat-secao-nome')) ev.target.closest('.cat-secao').querySelector('.cat-rot').textContent = ev.target.value || 'nova seção';
      });
    })();
    </script>
  <?php endif ?>
</main>
<style>
  .catalogo-editor .cat-secao { border: 2px solid var(--borda); border-radius: 18px; padding: 12px 14px 14px; margin: 16px 0; background: var(--branco); }
  .cat-secao-topo { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
  .cat-secao-nome { flex: 1; font-weight: 900; font-size: 1.1rem; }
  .cat-item { border-top: 1px dashed var(--borda); padding: 12px 0; }
  .cat-item label { display: block; font-size: .85rem; font-weight: 700; color: var(--texto2); margin: 6px 0 0; }
  .cat-item input:not([type=checkbox]) { width: 100%; box-sizing: border-box; }
  .cat-linha { display: flex; gap: 10px; flex-wrap: wrap; } .cat-linha > label { flex: 1 1 140px; }
  .cat-linha .cat-nome { flex: 3 1 200px; } .cat-linha .cat-preco { flex: 1 1 100px; }
  .cat-opcoes { display: flex; flex-wrap: wrap; gap: 4px 14px; align-items: center; margin-top: 8px; }
  .cat-opcoes .marcar { display: inline-flex; gap: 6px; align-items: center; margin: 0; color: var(--texto); }
  .cat-mover { margin-left: auto; display: inline-flex; gap: 4px; }
  .cat-mover button { border: 2px solid var(--borda); background: var(--creme); border-radius: 10px; min-width: 38px; min-height: 36px; cursor: pointer; font-size: 1rem; }
  .cat-mais summary { cursor: pointer; font-weight: 800; color: var(--verde); font-size: .9rem; margin-top: 8px; }
  .cat-foto { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; } .cat-foto img { border-radius: 10px; object-fit: cover; }
  .cat-salvar { position: sticky; bottom: 10px; background: var(--creme); padding: 10px; border-radius: 18px; box-shadow: 0 -4px 16px rgba(0,0,0,.08); display: flex; gap: 10px; flex-wrap: wrap; }
  .cat-mais-pedidos { margin: 6px 0 0; padding-left: 20px; }
</style>
<?php pagina_fim();
