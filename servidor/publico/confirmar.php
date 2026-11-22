<?php
// Plano D, passo 10 (20/11): o dono abre o link que recebeu pelo WhatsApp, confere os dados do comércio e confirma.
// "Está tudo certo" confirma na hora; correção (telefone, WhatsApp, endereço, bairro, horário) vira alteração pendente
// que o admin aprova. Sem conta e sem senha: o link é único e secreto (inc/confirmacao.php). Fora do Google.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/confirmacao.php';
require_once __DIR__ . '/../inc/avisos.php';
require __DIR__ . '/../inc/pagina.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$token = (string)($_GET['c'] ?? $_POST['c'] ?? '');
$achado = confirmacao_do_token($token);
$feito = null;
$erro = null;

if ($achado && e_post()) {
    exigir_post();
    $p = $achado['p'];
    if (($_POST['acao'] ?? '') === 'ok') {
        confirmacao_registrar_uso($achado['link'], 'ok');
        avisar_admins('Ficha confirmada pelo dono', nome_curto((string)$p['nome']) . ' (' . nome_cidade((string)$p['cidade']) . ') disse que está tudo certo.');
        $feito = 'ok';
    } elseif (($_POST['acao'] ?? '') === 'corrigir') {
        $v = [];
        foreach (['telefone', 'whatsapp', 'endereco', 'bairro'] as $c) {
            if (!array_key_exists($c, $_POST)) { $v[$c] = $p[$c] === null ? null : (string)$p[$c]; continue; }   // não veio: fica como está
            $t = trim(preg_replace('/\s+/', ' ', (string)($_POST[$c] ?? '')));
            $v[$c] = $t === '' ? null : mb_substr($t, 0, CAMPOS_DONO[$c][1]);
        }
        foreach (['telefone', 'whatsapp'] as $c)
            if ($v[$c] !== null && !($v[$c] = telefone_normalizado($v[$c]))) $erro = CAMPOS_DONO[$c][0] . ': digite com DDD, por exemplo (19) 99999-9999.';
        if (!$erro && !$v['telefone'] && !$v['whatsapp']) $erro = 'Informe pelo menos um telefone ou WhatsApp.';
        if (!$erro) {
            [$hor, $erro_hor] = horarios_do_post((array)($_POST['hor'] ?? []));
            if ($erro_hor) $erro = $erro_hor;
            elseif ($hor !== null) { $v['horarios'] = $hor; $v['horario'] = horarios_texto(horarios_ler($hor)); }
        }
        if (!$erro) {
            $alt = confirmacao_propor($p, $v);
            confirmacao_registrar_uso($achado['link'], $alt ? 'corrigiu' : 'ok');
            if ($alt) avisar_admins('Correção pelo link de confirmação', nome_curto((string)$p['nome']) . ' mandou correção. Confira em Pendências.');
            $feito = $alt ? 'corrigiu' : 'ok';
        }
    }
    if ($achado['link']['aberto_em'] === null) q('UPDATE confirmacao_links SET aberto_em = NOW() WHERE id = ?', [(int)$achado['link']['id']]);
} elseif ($achado && $achado['link']['aberto_em'] === null) {
    q('UPDATE confirmacao_links SET aberto_em = NOW() WHERE id = ?', [(int)$achado['link']['id']]);
}

pagina_inicio('Confirme os dados do seu comércio | Localiza Fácil App', 'Confira os dados do seu comércio no Localiza Fácil.', '', ['noindex' => true]);
$base = config()['url'];
?>
<main class="documento">
<?php if (!$achado): ?>
  <h1>Link vencido ou inválido</h1>
  <p>Este link de confirmação não vale mais. Peça um novo para a equipe do Localiza Fácil pelo e-mail
    <a href="mailto:<?= h(config()['contato_email'] ?? '') ?>"><?= h(config()['contato_email'] ?? '') ?></a>, ou procure o seu comércio:</p>
  <p><a class="botao" href="<?= $base ?>/busca.php">Procurar meu comércio</a></p>
<?php elseif ($feito): $p = $achado['p']; ?>
  <h1>✅ Obrigado!</h1>
  <?php if ($feito === 'ok'): ?>
    <p>Os dados da <strong><?= h(nome_curto((string)$p['nome'])) ?></strong> estão confirmados. A sua ficha agora mostra o selo
      <strong>“Dados confirmados pelo dono”</strong>.</p>
  <?php else: ?>
    <p>Recebemos a sua correção da <strong><?= h(nome_curto((string)$p['nome'])) ?></strong>. A equipe confere e coloca no ar em breve.
      A ficha já mostra o selo <strong>“Dados confirmados pelo dono”</strong>.</p>
  <?php endif ?>
  <div class="quadro">
    <h2 style="margin-top:0">Quer aparecer mais?</h2>
    <p>Com um plano, você coloca <strong>fotos</strong>, a <strong>descrição</strong> do seu negócio e pode aparecer em <strong>Destaque</strong> na sua cidade.</p>
    <p><a class="botao amarelo" href="<?= $base ?>/planos.php?id=<?= (int)$p['id'] ?>">Ver os planos</a>
      <a class="botao contorno" href="<?= h(url_ficha($p)) ?>">Ver minha ficha</a></p>
  </div>
<?php else: $p = $achado['p']; $f = $erro ? $_POST : $p; $hor = horarios_ler($erro ? null : $p['horarios']); ?>
  <h1>Confirme os dados do seu comércio</h1>
  <p>Este é o seu comércio no <strong>Localiza Fácil</strong>, o guia de comércios de <?= h(nome_cidade((string)$p['cidade'])) ?>. Confira e confirme. É grátis.</p>
  <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
  <div class="quadro">
    <h2 style="margin-top:0"><?= h($p['nome']) ?></h2>
    <p class="discreto"><?= h(implode(' · ', array_filter([$p['tipo'] ?: $p['categoria'], $p['bairro'], nome_cidade((string)$p['cidade'])]))) ?></p>
    <p>📍 <?= h($p['endereco'] ?: 'Endereço não informado') ?><br>
      📞 <?= h($p['telefone'] ? telefone_formatado($p['telefone']) : 'Telefone não informado') ?><br>
      💬 <?= h($p['whatsapp'] ? telefone_formatado($p['whatsapp']) : 'WhatsApp não informado') ?><br>
      🕒 <?= h($p['horario'] ?: 'Horário não informado') ?></p>
    <form method="post"><?= campo_csrf() ?><input type="hidden" name="c" value="<?= h($token) ?>">
      <button class="botao" name="acao" value="ok">✅ Está tudo certo</button></form>
  </div>
  <details<?= $erro ? ' open' : '' ?> class="quadro">
    <summary><strong>Algo errado ou quer informar o horário? Corrija aqui</strong></summary>
    <form method="post" class="form-admin-comercio"><?= campo_csrf() ?><input type="hidden" name="c" value="<?= h($token) ?>">
      <label for="telefone">Telefone</label><input id="telefone" name="telefone" inputmode="tel" value="<?= h(($erro ? $f['telefone'] : telefone_formatado($p['telefone'])) ?? '') ?>">
      <label for="whatsapp">WhatsApp</label><input id="whatsapp" name="whatsapp" inputmode="tel" value="<?= h(($erro ? $f['whatsapp'] : telefone_formatado($p['whatsapp'])) ?? '') ?>">
      <label for="endereco">Endereço</label><input id="endereco" name="endereco" maxlength="200" value="<?= h($f['endereco'] ?? '') ?>">
      <label for="bairro">Bairro</label><input id="bairro" name="bairro" maxlength="80" value="<?= h($f['bairro'] ?? '') ?>">
      <h3>Horário de funcionamento</h3>
      <?= horarios_form($hor) ?>
      <p><button class="botao" name="acao" value="corrigir">Enviar correção</button></p>
      <p class="discreto">A equipe confere a correção antes de colocar no ar. Fotos e descrição ficam nos <a href="<?= $base ?>/planos.php?id=<?= (int)$p['id'] ?>">planos</a>.</p>
    </form>
  </details>
<?php endif ?>
</main>
<?php pagina_fim();
