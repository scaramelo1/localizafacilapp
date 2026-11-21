<?php
// E-mails (21/11, inc/email.php): escrever para os donos, para as contas do app ou para um e-mail só; mandar um teste
// para si mesmo antes; acompanhar a fila, os lotes e os últimos envios (inclusive os automáticos e o formulário de contato).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/email.php';
require_once __DIR__ . '/../../inc/moderacao.php';
$admin = exigir_admin();
if (!email_pronto()) {
    admin_inicio('E-mails', $admin);
    echo '<h1>E-mails</h1><p>Falta aplicar a mudança 037 (bash servidor/instalar.sh).</p>';
    admin_fim();
    exit;
}

const EMAIL_MODELO = "Olá {nome},\n\n\n\nQualquer dúvida, é só responder este e-mail.\n\nEquipe Localiza Fácil";
$f = ['publico' => 'donos', 'cidade' => '', 'um' => '', 'assunto' => '', 'texto' => EMAIL_MODELO];
$contagem = null;

if (e_post()) {
    exigir_post();
    $acao = $_POST['acao'] ?? '';
    $f = [
        'publico' => isset(EMAIL_PUBLICOS[$_POST['publico'] ?? '']) ? $_POST['publico'] : 'donos',
        'cidade' => isset(TODAS_CIDADES[$_POST['cidade'] ?? '']) ? $_POST['cidade'] : '',
        'um' => trim((string)($_POST['um'] ?? '')),
        'assunto' => (string)txt('assunto', 200),
        'texto' => trim(str_replace("\r", '', (string)($_POST['texto'] ?? ''))),
    ];
    $lista = email_destinatarios($f['publico'], $f['publico'] === 'donos' && $f['cidade'] ? $f['cidade'] : null, $f['um']);
    $pronto_texto = $f['assunto'] !== '' && mb_strlen($f['texto']) >= 10 && $f['texto'] !== EMAIL_MODELO;

    if ($acao === 'processar') {
        $r = email_processar();
        flash($r['parou'] ? 'Parou: ' . $r['parou'] : "Enviados agora: {$r['enviados']}" . ($r['falhas'] ? ", {$r['falhas']} com erro" : '') . ". Na fila: {$r['sobrou']}.", $r['parou'] ? 'erro' : 'ok');
        ir('admin/emails.php');
    } elseif ($acao === 'contar') {
        $contagem = count($lista);
    } elseif (!$pronto_texto) {
        flash('Escreva o assunto e a mensagem antes de mandar.', 'erro');
    } elseif ($acao === 'teste') {
        $nome_teste = $lista ? (reset($lista) ?: $admin['nome'] ?? null) : ($admin['nome'] ?? null);
        $id = email_enfileirar((string)$admin['email'], $nome_teste, '[Teste] ' . $f['assunto'], $f['texto'], 'teste', ['admin_id' => (int)$admin['id']]);
        if ($id) { email_processar(5); }
        $st = $id ? (string)valor('SELECT status FROM emails WHERE id = ?', [$id]) : '';
        flash($st === 'enviado' ? 'Teste enviado para ' . $admin['email'] . '. Confira a caixa de entrada (e o spam).'
            : 'O teste ficou na fila' . (email_problema() ? ': ' . email_problema() : '; ele sai no próximo minuto.'), $st === 'enviado' ? 'ok' : 'erro');
    } elseif ($acao === 'enviar') {
        if (!$lista) {
            flash('Ninguém para receber com essa escolha.', 'erro');
        } else {
            $publico = EMAIL_PUBLICOS[$f['publico']] . ($f['publico'] === 'donos' && $f['cidade'] ? ' · ' . nome_cidade($f['cidade']) : '')
                . ($f['publico'] === 'um' ? ' · ' . array_key_first($lista) : '');
            $lote = inserir('emails_lotes', ['assunto' => $f['assunto'], 'texto' => $f['texto'], 'publico' => mb_substr($publico, 0, 120),
                'total' => count($lista), 'admin_id' => (int)$admin['id']]);
            foreach ($lista as $para => $nome) {
                email_enfileirar($para, $nome, $f['assunto'], $f['texto'], 'equipe', ['lote_id' => $lote, 'admin_id' => (int)$admin['id']]);
            }
            registrar((int)$admin['id'], 'mandou_emails', null, count($lista) . ' · ' . $f['assunto']);
            $livres = max(0, email_limite_dia() - email_enviados_24h());
            flash(count($lista) . ' e-mail(s) na fila. Saem ' . EMAIL_POR_RODADA . ' por minuto'
                . (count($lista) > $livres ? '; passa do limite de hoje, então o resto sai nas próximas 24 h.' : '.'));
            ir('admin/emails.php');
        }
    }
}

$problema = email_problema();
$na_fila = (int)valor("SELECT COUNT(*) FROM emails WHERE status = 'fila'");
$hoje = email_enviados_24h();
$lotes = linhas("SELECT l.*, a.nome AS admin, SUM(e.status = 'enviado') AS enviados, SUM(e.status = 'fila') AS fila,
    SUM(e.status = 'erro') AS erros, SUM(e.status = 'pulado') AS pulados
    FROM emails_lotes l LEFT JOIN emails e ON e.lote_id = l.id LEFT JOIN admins a ON a.id = l.admin_id
    GROUP BY l.id ORDER BY l.id DESC LIMIT 20");
$ultimos = linhas('SELECT id, para, assunto, tipo, status, erro, criado_em, enviado_em FROM emails ORDER BY id DESC LIMIT 60');
$descad = (int)valor('SELECT COUNT(*) FROM emails_descadastro');

admin_inicio('E-mails', $admin);
?>
<h1>E-mails</h1>
<?php mostrar_flash(); ?>

<?php if (!email_configurado()): ?>
  <div class="aviso erro"><b>O envio ainda não está ligado.</b> Falta a senha de app do Gmail em <code>/etc/guia-novo/config.php</code>
    (<code>smtp_usuario</code> e <code>smtp_senha</code>). Os e-mails ficam guardados na fila e saem assim que ela for colocada.</div>
<?php elseif ($problema): ?>
  <div class="aviso erro"><b>Último problema no envio:</b> <?= h($problema) ?></div>
<?php endif ?>

<p class="discreto">Saem pelo <?= h(email_usuario() ?: 'Gmail') ?>. Enviados nas últimas 24 h: <b><?= $hoje ?></b> de <?= email_limite_dia() ?>
  · na fila: <b><?= $na_fila ?></b> · pediram para não receber: <?= $descad ?>.
  <?php if ($na_fila): ?>
  <form method="post" style="display:inline"><?= campo_csrf() ?><button class="botao pequeno" name="acao" value="processar">Mandar a fila agora</button></form>
  <?php endif ?></p>

<h2>Escrever</h2>
<form method="post" id="form-email">
  <?= campo_csrf() ?>
  <label for="publico">Para quem</label>
  <select id="publico" name="publico">
    <?php foreach (EMAIL_PUBLICOS as $k => $rot): ?><option value="<?= $k ?>"<?= $f['publico'] === $k ? ' selected' : '' ?>><?= h($rot) ?></option><?php endforeach ?>
  </select>
  <div id="campo-cidade">
    <label for="cidade">Cidade do comércio</label>
    <select id="cidade" name="cidade">
      <option value="">Todas as cidades</option>
      <?php foreach (TODAS_CIDADES as $slug => $c): ?><option value="<?= h($slug) ?>"<?= $f['cidade'] === $slug ? ' selected' : '' ?>><?= h($c[0]) ?></option><?php endforeach ?>
    </select>
  </div>
  <div id="campo-um">
    <label for="um">E-mail</label>
    <input id="um" name="um" type="email" maxlength="160" value="<?= h($f['um']) ?>" placeholder="nome@exemplo.com">
  </div>
  <label for="assunto">Assunto</label>
  <input id="assunto" name="assunto" maxlength="200" value="<?= h($f['assunto']) ?>" placeholder="Ex.: O Acha Fácil agora é Localiza Fácil">
  <label for="texto">Mensagem</label>
  <textarea id="texto" name="texto" rows="12"><?= h($f['texto']) ?></textarea>
  <p class="discreto">Texto simples: deixe uma linha em branco entre os parágrafos; links começando com https:// viram link.
    <code>{nome}</code> vira o primeiro nome da pessoa (some quando não há nome). O rodapé com "Não quero mais receber" entra sozinho.</p>
  <?php if ($contagem !== null): ?><div class="aviso ok"><b><?= $contagem ?></b> pessoa(s) vão receber com essa escolha.</div><?php endif ?>
  <p>
    <button class="botao" name="acao" value="contar" formnovalidate>Ver quantos vão receber</button>
    <button class="botao" name="acao" value="teste">Mandar um teste para mim</button>
    <button class="botao amarelo" name="acao" value="enviar" onclick="return confirm('Mandar este e-mail agora? Não dá para desfazer.')">Enviar</button>
  </p>
</form>
<script>
(function () {
  var p = document.getElementById('publico');
  function mostrar() {
    document.getElementById('campo-cidade').hidden = p.value !== 'donos';
    document.getElementById('campo-um').hidden = p.value !== 'um';
  }
  p.addEventListener('change', mostrar); mostrar();
})();
</script>

<?php if ($lotes): ?>
<h2>Enviados pela equipe</h2>
<div class="tabela"><table>
  <tr><th>Quando</th><th>Assunto</th><th>Para</th><th>Total</th><th>Enviados</th><th>Na fila</th><th>Erro</th><th>Por</th></tr>
  <?php foreach ($lotes as $l): ?>
  <tr><td><?= datahora_br($l['criado_em']) ?></td><td><?= h($l['assunto']) ?></td><td><?= h($l['publico']) ?></td>
    <td><?= (int)$l['total'] ?></td><td><?= (int)$l['enviados'] ?></td><td><?= (int)$l['fila'] ?></td>
    <td><?= (int)$l['erros'] + (int)$l['pulados'] ?: '' ?></td><td><?= h($l['admin'] ?? '') ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

<h2>Últimos e-mails</h2>
<?php if (!$ultimos): ?><p class="discreto">Nenhum e-mail ainda.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th>Quando</th><th>Para</th><th>Assunto</th><th>Tipo</th><th>Situação</th></tr>
  <?php foreach ($ultimos as $e): ?>
  <tr><td><?= datahora_br($e['enviado_em'] ?? $e['criado_em']) ?></td><td><?= h($e['para']) ?></td><td><?= h($e['assunto']) ?></td>
    <td><?= h(EMAIL_TIPOS[$e['tipo']] ?? $e['tipo']) ?></td>
    <td><?= h(EMAIL_STATUS[$e['status']] ?? $e['status']) ?><?= $e['erro'] ? '<br><small class="discreto">' . h($e['erro']) . '</small>' : '' ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>
<?php admin_fim();
