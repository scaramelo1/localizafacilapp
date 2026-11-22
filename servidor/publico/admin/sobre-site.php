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
$voltar = 'admin/sobre-site.php?aba=' . $aba;

if (e_post()) {
    exigir_post();
    $id = (int)($_POST['id'] ?? 0);
    $s = linha('SELECT s.*, p.nome FROM sobre_site s JOIN profissionais p ON p.id = s.profissional_id WHERE s.profissional_id = ?', [$id]);
    if (!$s) { flash('Não encontrado.', 'erro'); ir($voltar); }
    $nome = nome_curto((string)$s['nome']);
    $acao = (string)($_POST['acao'] ?? '');
    if ($acao === 'aprovar') {
        $texto = trim(preg_replace('/\s+/u', ' ', (string)($_POST['texto'] ?? '')));
        if (mb_strlen($texto) < 20) { flash('Texto curto demais. Nada foi salvo.', 'erro'); ir($voltar); }
        $texto = mb_substr($texto, 0, 600);
        q("UPDATE sobre_site SET texto = ?, situacao = 'aprovado', decidido_em = NOW(), decidido_por = ? WHERE profissional_id = ?", [$texto, $admin['id'], $id]);
        q('UPDATE profissionais SET sobre_site = ?, sobre_site_url = ? WHERE id = ?', [$texto, $s['url'], $id]);
        registrar((int)$admin['id'], 'sobre_site_aprovado', $id, mb_substr($texto, 0, 180));
        flash("✅ Sobre de $nome aprovado: já aparece na ficha.");
    } elseif ($acao === 'recusar' || $acao === 'desfazer') {
        q("UPDATE sobre_site SET situacao = 'recusado', decidido_em = NOW(), decidido_por = ? WHERE profissional_id = ?", [$admin['id'], $id]);
        q('UPDATE profissionais SET sobre_site = NULL, sobre_site_url = NULL WHERE id = ?', [$id]);
        registrar((int)$admin['id'], 'sobre_site_recusado', $id);
        flash($acao === 'desfazer' ? "Sobre de $nome tirado da ficha." : "Recusado: $nome fica sem o texto do site.");
    } elseif ($acao === 'tirar_site') {
        $p = linha('SELECT site FROM profissionais WHERE id = ?', [$id]);
        q('UPDATE profissionais SET site = NULL WHERE id = ?', [$id]);
        q("UPDATE sobre_site SET situacao = 'recusado', motivo = CONCAT('site tirado da ficha: ', IFNULL(motivo, '')), decidido_em = NOW(), decidido_por = ? WHERE profissional_id = ?", [$admin['id'], $id]);
        registrar((int)$admin['id'], 'site_tirado', $id, 'site fora do ar: ' . ($p['site'] ?? ''));
        flash("Botão Site tirado da ficha de $nome (era " . ($p['site'] ?? '') . ').');
    }
    web_cache_limpar();
    ir($voltar);
}

$num = array_column(linhas('SELECT situacao, COUNT(*) AS n FROM sobre_site GROUP BY situacao'), 'n', 'situacao');
$onde = $aba === 'erro' ? "s.situacao = 'erro' AND p.site IS NOT NULL AND p.site <> ''" : 's.situacao = ' . "'" . $aba . "'";
$lista = linhas("SELECT s.*, p.nome, p.cidade, p.tipo, p.descricao, p.site FROM sobre_site s JOIN profissionais p ON p.id = s.profissional_id
    WHERE $onde AND p.status = 'ativo' ORDER BY p.popularidade DESC, p.id LIMIT 100");

admin_inicio('Sobre pelo site do comércio', $admin);
?>
<h1>🌐 Sobre pelo site do comércio</h1>
<p class="discreto">O robô lê a descrição que o <b>próprio comércio</b> escreveu no site dele. Nada aparece sozinho: confira, corrija se
  precisar e aprove. Aprovado aparece na ficha como “Sobre (do site do comércio)”, com o link. Texto de propaganda exagerada,
  de outra empresa (rede, plataforma) ou que não fala do comércio: recuse.<br>
  Ler mais sites (na VPS): <code>sudo -u www-data php /var/www/guia-novo/servidor/sobre-do-site.php --limite=100</code></p>
<p class="filtros-linha">
  <?php foreach (['pendente' => 'Para aprovar', 'aprovado' => 'Aprovados', 'recusado' => 'Recusados', 'erro' => 'Sites que não abrem'] as $k => $r): ?>
    <a class="botao <?= $k === $aba ? '' : 'contorno' ?> pequeno" href="<?= h(url('admin/sobre-site.php?aba=' . $k)) ?>"><?= h($r) ?> (<?= (int)($num[$k] ?? 0) ?>)</a>
  <?php endforeach ?>
  <span class="discreto">· sem texto que sirva: <?= (int)($num['sem_texto'] ?? 0) ?></span>
</p>
<?php mostrar_flash(); ?>
<?php if (!$lista): ?><div class="aviso ok">Nada aqui.</div><?php endif ?>
<?php foreach ($lista as $s): $ficha = web_ficha((int)$s['profissional_id']); ?>
  <div class="quadro">
    <h2 style="margin:0 0 4px"><?= h(nome_curto((string)$s['nome'])) ?> <small class="discreto">#<?= (int)$s['profissional_id'] ?> · <?= h(nome_cidade((string)$s['cidade'])) ?><?= $s['tipo'] ? ' · ' . h($s['tipo']) : '' ?></small></h2>
    <p class="discreto" style="margin:0 0 8px">Site: <a href="<?= h($s['url']) ?>" target="_blank" rel="noopener"><?= h($s['url']) ?></a>
      <?php if ($s['titulo']): ?> · título: “<?= h(mb_substr((string)$s['titulo'], 0, 90)) ?>”<?php endif ?>
      <?php if ($ficha): ?> · <a href="<?= h(url_ficha($ficha)) ?>" target="_blank">ver a ficha</a><?php endif ?></p>
    <?php if ($aba === 'erro'): ?>
      <p>⚠️ <?= h((string)$s['motivo']) ?> (lido em <?= h(date('d/m H:i', strtotime($s['lido_em']))) ?>). Se o site não existe mais, o botão “Site” da ficha leva a lugar nenhum.</p>
      <form method="post" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$s['profissional_id'] ?>">
        <button class="botao contorno pequeno" name="acao" value="tirar_site" onclick="return confirm('Tirar o site da ficha?')">Tirar o site da ficha</button></form>
    <?php elseif ($aba === 'pendente'): ?>
      <form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$s['profissional_id'] ?>">
        <textarea name="texto" rows="3" maxlength="600" style="width:100%"><?= h((string)$s['texto']) ?></textarea>
        <p style="margin:6px 0 0"><button class="botao pequeno" name="acao" value="aprovar">✅ Aprovar</button>
          <button class="botao contorno pequeno" name="acao" value="recusar">Recusar</button></p>
      </form>
    <?php else: ?>
      <p><?= h((string)$s['texto']) ?></p>
      <?php if ($aba === 'aprovado'): ?><form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$s['profissional_id'] ?>">
        <button class="botao contorno pequeno" name="acao" value="desfazer">Tirar da ficha</button></form><?php endif ?>
    <?php endif ?>
  </div>
<?php endforeach ?>
<?php admin_fim();
