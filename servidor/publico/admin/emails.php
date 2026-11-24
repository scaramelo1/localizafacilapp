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
$f = ['publico' => 'donos', 'cidade' => '', 'um' => '', 'escolhidos' => [], 'assunto' => '', 'texto' => EMAIL_MODELO];
$contagem = null;
if (!e_post() && ($pre = email_valido((string)($_GET['para'] ?? '')))) $f = ['publico' => 'um', 'um' => $pre] + $f;
// 24/11 (passo 83): modelo pronto do convite para as lojas atualizarem a ficha (Admin > E-mails > "Usar o modelo")
const EMAIL_MODELO_LOJAS_ASSUNTO = '{comercio} está no Localiza Fácil App: assuma e atualize a sua ficha de graça';
const EMAIL_MODELO_LOJAS = "Olá, equipe {comercio}!

O seu comércio já aparece no Localiza Fácil App (localizafacilapp.com.br), o guia de comércios e serviços da região. Todo dia, pessoas da cidade usam o guia para encontrar onde comprar, comer e resolver as coisas, e chamam direto pelo WhatsApp ou pelo telefone.

Veja como está a sua ficha: {link}

O nosso aplicativo será lançado em breve. Até lá, aproveite para deixar a ficha do seu comércio atualizada no site, para já receber acessos.

Assumir a ficha é de graça, e corrigir os dados também: telefone, WhatsApp, endereço e horário de funcionamento. Leva poucos minutos e você não paga nada por isso.

Para assumir e atualizar a sua ficha de graça: {planos}

Este e-mail é automático e foi enviado porque o e-mail do seu comércio está no site da própria empresa. Se não quiser receber mais, é só clicar em \"Não quero mais receber\", no fim desta mensagem.

Qualquer dúvida, é só responder este e-mail.

Equipe Localiza Fácil App";
if (!e_post() && ($_GET['modelo'] ?? '') === 'lojas') {
    $f = ['publico' => 'lojas', 'assunto' => EMAIL_MODELO_LOJAS_ASSUNTO, 'texto' => EMAIL_MODELO_LOJAS] + $f;
}

if (e_post()) {
    exigir_post();
    $acao = $_POST['acao'] ?? '';
    $f = [
        'publico' => isset(EMAIL_PUBLICOS[$_POST['publico'] ?? '']) ? $_POST['publico'] : 'donos',
        'cidade' => isset(TODAS_CIDADES[$_POST['cidade'] ?? '']) ? $_POST['cidade'] : '',
        'um' => trim((string)($_POST['um'] ?? '')),
        'escolhidos' => array_values(array_filter(array_map(fn($e) => email_valido((string)$e), (array)($_POST['escolhidos'] ?? [])))),
        'assunto' => (string)txt('assunto', 200),
        'texto' => trim(str_replace("\r", '', (string)($_POST['texto'] ?? ''))),
    ];
    $lista = email_destinatarios($f['publico'], in_array($f['publico'], ['donos', 'lojas'], true) && $f['cidade'] ? $f['cidade'] : null, $f['um'], $f['escolhidos']);
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
        // 23/11 (passo 79): no teste para lojas, o {comercio}/{link} da 1ª loja da lista (para ver como chega)
        [$as_t, $tx_t] = $f['publico'] === 'lojas' && $lista ? [email_trocar_loja($f['assunto'], (string)array_key_first($lista)), email_trocar_loja($f['texto'], (string)array_key_first($lista))] : [$f['assunto'], $f['texto']];
        $id = email_enfileirar((string)$admin['email'], $nome_teste, '[Teste] ' . $as_t, $tx_t, 'teste', ['admin_id' => (int)$admin['id']]);
        if ($id) { email_processar(5); }
        $st = $id ? (string)valor('SELECT status FROM emails WHERE id = ?', [$id]) : '';
        flash($st === 'enviado' ? 'Teste enviado para ' . $admin['email'] . '. Confira a caixa de entrada (e o spam).'
            : 'O teste ficou na fila' . (email_problema() ? ': ' . email_problema() : '; ele sai no próximo minuto.'), $st === 'enviado' ? 'ok' : 'erro');
    } elseif ($acao === 'enviar') {
        if (!$lista) {
            flash('Ninguém para receber com essa escolha.', 'erro');
        } else {
            $publico = EMAIL_PUBLICOS[$f['publico']] . (in_array($f['publico'], ['donos', 'lojas'], true) && $f['cidade'] ? ' · ' . nome_cidade($f['cidade']) : '')
                . ($f['publico'] === 'um' ? ' · ' . array_key_first($lista) : '');
            if ($f['publico'] === 'escolhidos') $publico = count($lista) === 1 ? array_key_first($lista) : count($lista) . ' marcados na lista';
            $lote = inserir('emails_lotes', ['assunto' => $f['assunto'], 'texto' => $f['texto'], 'publico' => mb_substr($publico, 0, 120),
                'total' => count($lista), 'admin_id' => (int)$admin['id']]);
            foreach ($lista as $para => $nome) {
                $loja = $f['publico'] === 'lojas';   // 23/11 (passo 79): {comercio}, {link} e {planos} de cada loja
                email_enfileirar($para, $nome, $loja ? email_trocar_loja($f['assunto'], $para) : $f['assunto'], $loja ? email_trocar_loja($f['texto'], $para) : $f['texto'],
                    'equipe', ['lote_id' => $lote, 'admin_id' => (int)$admin['id']]);
            }
            registrar((int)$admin['id'], 'mandou_emails', null, count($lista) . ' · ' . $f['assunto']);
            $livres = max(0, email_limite_dia() - email_enviados_24h());
            flash(count($lista) . ' e-mail(s) na fila. Saem ' . EMAIL_POR_RODADA . ' por minuto'
                . (count($lista) > $livres ? '; passa do limite de hoje, então o resto sai nas próximas 24 h.' : '.'));
            ir('admin/emails.php');
        }
    }
}

$cadastrados = email_cadastrados();
if (($_GET['baixar'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="emails-localiza-facil-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM: o Excel abre os acentos certo
    fputcsv($out, ['E-mail', 'Nome', 'Tipo', 'Comércios', 'Desde', 'Recebe e-mails da equipe'], ';');
    foreach ($cadastrados as $e => $c) {
        fputcsv($out, [$e, $c['nome'], implode(' + ', array_filter([$c['comercios'] ? 'Dono' : null, $c['conta'] ? 'Conta do app' : null])),
            implode(', ', array_map(fn($x) => $x[1] . ' (' . nome_cidade($x[2], true) . ')', $c['comercios'])), data_br($c['desde']), $c['fora'] ? 'Não' : 'Sim'], ';');
    }
    exit;
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
<p class="discreto">Modelo pronto: <a href="?modelo=lojas">📨 Convite para as lojas atualizarem a ficha</a> (já escolhe "Lojas com e-mail no site").
  Mande um teste para você antes e, para não travar o Gmail, uma cidade por dia, começando pela menor.</p>
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
  <p id="campo-marcados" class="discreto"><b id="qtd-marcados">0</b> marcado(s) na lista de e-mails cadastrados, mais abaixo. <a href="#cadastrados">Ver a lista</a></p>
  <div id="campo-um">
    <label for="um">E-mail</label>
    <input id="um" name="um" type="email" maxlength="160" value="<?= h($f['um']) ?>" placeholder="nome@exemplo.com">
  </div>
  <label for="assunto">Assunto</label>
  <input id="assunto" name="assunto" maxlength="200" value="<?= h($f['assunto']) ?>" placeholder="Ex.: O Acha Fácil agora é Localiza Fácil">
  <label for="texto">Mensagem</label>
  <textarea id="texto" name="texto" rows="12"><?= h($f['texto']) ?></textarea>
  <p class="discreto">Texto simples: deixe uma linha em branco entre os parágrafos; links começando com https:// viram link.
    <code>{nome}</code> vira o primeiro nome da pessoa (some quando não há nome). O rodapé com "Não quero mais receber" entra sozinho.<br>
    Para <b>Lojas com e-mail no site</b>: <code>{comercio}</code> vira o nome da loja, <code>{link}</code> o endereço da ficha dela e
    <code>{planos}</code> a página para assumir a ficha / ver os planos.</p>
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
    document.getElementById('campo-cidade').hidden = p.value !== 'donos' && p.value !== 'lojas';
    document.getElementById('campo-um').hidden = p.value !== 'um';
    document.getElementById('campo-marcados').hidden = p.value !== 'escolhidos';
  }
  p.addEventListener('change', mostrar); mostrar();
  // caixinhas da lista "E-mails cadastrados" (ficam fora do formulário, ligadas a ele pelo atributo form)
  var caixas = Array.prototype.slice.call(document.querySelectorAll('input.escolher'));
  var todos = document.getElementById('marcar-todos');
  function contar(mudarPublico) {
    var n = caixas.filter(function (c) { return c.checked; }).length;
    document.getElementById('qtd-marcados').textContent = n;
    if (todos) { todos.checked = n > 0 && n === caixas.length; todos.indeterminate = n > 0 && n < caixas.length; }
    if (mudarPublico && n > 0) { p.value = 'escolhidos'; mostrar(); }
  }
  caixas.forEach(function (c) { c.addEventListener('change', function () { contar(true); }); });
  if (todos) todos.addEventListener('change', function () { caixas.forEach(function (c) { c.checked = todos.checked; }); contar(true); });
  contar(false);
})();
</script>

<h2 id="cadastrados">E-mails cadastrados (<?= count($cadastrados) ?>)</h2>
<p class="discreto">Donos (quem pediu, assumiu ou pagou um comércio) e contas do app (entraram com o Google).
  <a href="?baixar=csv">Baixar a lista (planilha)</a></p>
<p class="discreto">Marque as caixinhas para mandar só para essas pessoas: o "Para quem" muda sozinho para "Os marcados" e o e-mail é escrito no formulário acima.</p>
<?php if (!$cadastrados): ?><p class="discreto">Nenhum e-mail cadastrado ainda.</p><?php else: ?>
<div class="tabela"><table>
  <tr><th><input type="checkbox" id="marcar-todos" title="Marcar todos" aria-label="Marcar todos"></th><th>E-mail</th><th>Nome</th><th>Tipo</th><th>Comércio</th><th>Desde</th><th></th></tr>
  <?php foreach ($cadastrados as $e => $c): ?>
  <tr><td><?php if (!$c['fora']): ?><input type="checkbox" class="escolher" name="escolhidos[]" value="<?= h($e) ?>" form="form-email" aria-label="Marcar <?= h($e) ?>"<?= in_array($e, $f['escolhidos'], true) ? ' checked' : '' ?>><?php endif ?></td>
    <td><?= h($e) ?><?= $c['fora'] ? '<br><small class="discreto">pediu para não receber</small>' : '' ?></td><td><?= h($c['nome'] ?? '') ?></td>
    <td><?= h(implode(' + ', array_filter([$c['comercios'] ? 'Dono' : null, $c['conta'] ? 'Conta do app' : null]))) ?></td>
    <td><?= implode('<br>', array_map(fn($x) => adm_comercio($x[0], $x[1], $x[2]), $c['comercios'])) ?></td>
    <td><?= data_br($c['desde']) ?></td>
    <td><?php if (!$c['fora']): ?><a class="botao pequeno" href="?para=<?= rawurlencode($e) ?>#form-email">Escrever</a><?php endif ?></td></tr>
  <?php endforeach ?>
</table></div>
<?php endif ?>

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
