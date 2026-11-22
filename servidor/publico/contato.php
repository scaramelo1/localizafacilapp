<?php
// Fale conosco (21/11, pedido do usuário): formulário sem login; a mensagem chega no contato_email do config (Gmail do
// Localiza Fácil) e o "Responder" vai direto para quem escreveu (inc/email.php). Quem tem conta Google continua podendo
// abrir um chamado em /suporte.php (resposta no app).
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
require_once __DIR__ . '/../inc/email.php';
iniciar_sessao();

const CONTATO_ASSUNTOS = [
    'duvida' => 'Dúvida', 'meu_comercio' => 'Meu comércio', 'info_errada' => 'Informação errada num comércio',
    'pagamento' => 'Pagamento ou plano', 'parceria' => 'Parceria ou anúncio', 'sugestao' => 'Sugestão', 'outro' => 'Outro assunto',
];
$destino = email_valido(site('contato_email'));
$pronto = email_pronto() && $destino;
$erro = null;
$enviado = false;
$f = e_post() ? $_POST : ['assunto' => (string)($_GET['assunto'] ?? '')];

if (e_post() && $pronto) {
    exigir_post();
    $nome = txt('nome', 120);
    $email = email_valido(txt('email', 160));
    $assunto = isset(CONTATO_ASSUNTOS[$_POST['assunto'] ?? '']) ? (string)$_POST['assunto'] : 'outro';
    $cidade = txt('cidade', 80);
    $msg = trim(str_replace("\r", '', (string)($_POST['mensagem'] ?? '')));
    $por_ip = (int)valor("SELECT COUNT(*) FROM emails WHERE tipo = 'contato' AND ip = ? AND criado_em > NOW() - INTERVAL 1 HOUR", [ip()]);
    $geral = (int)valor("SELECT COUNT(*) FROM emails WHERE tipo = 'contato' AND criado_em > NOW() - INTERVAL 1 HOUR");
    if (!empty($_POST['site_web'])) $enviado = true;   // campo escondido preenchido = robô: finge que deu certo
    elseif (!$nome) $erro = 'Escreva o seu nome.';
    elseif (!$email) $erro = 'Confira o seu e-mail: é por ele que vamos responder.';
    elseif (mb_strlen($msg) < 10) $erro = 'Escreva a sua mensagem (pelo menos algumas palavras).';
    elseif ($por_ip >= 3 || $geral >= 30) $erro = 'Recebemos várias mensagens agora há pouco. Tente de novo daqui a uma hora.';
    else {
        $texto = "Mensagem pelo formulário de contato do site.\n\n"
            . "Nome: $nome\nE-mail: $email\n" . ($cidade ? "Cidade: $cidade\n" : '') . 'Assunto: ' . CONTATO_ASSUNTOS[$assunto] . "\n\n"
            . mb_substr($msg, 0, 5000) . "\n\nPara responder, é só clicar em Responder.";
        $id = email_enfileirar($destino, 'Localiza Fácil', 'Contato pelo site: ' . CONTATO_ASSUNTOS[$assunto] . ' · ' . $nome, $texto, 'contato',
            ['responder_para' => $email, 'ip' => ip()]);
        if ($id) $enviado = true;
        else $erro = 'Não conseguimos enviar agora. Tente de novo em alguns minutos ou fale pelo Instagram.';
    }
}

$sel = fn(string $campo, $valor) => (string)($f[$campo] ?? '') === (string)$valor ? ' selected' : '';
pagina_inicio('Fale conosco | Localiza Fácil App', 'Mande uma mensagem para a equipe do Localiza Fácil: dúvidas, informação errada, planos ou sugestões.');
?>
<main class="documento">
  <h1>Fale conosco</h1>
  <?php if ($enviado): ?>
    <div class="aviso ok">Mensagem enviada! A equipe do Localiza Fácil responde no seu e-mail, normalmente em até 2 dias úteis.</div>
    <p><a href="<?= h(config()['url']) ?>/">Voltar para o início</a></p>
  <?php elseif (!$pronto): ?>
    <div class="aviso erro">O formulário ainda está sendo preparado. Enquanto isso, fale com a gente: <?= contatos_html() ?></div>
  <?php else: ?>
    <p>Dúvida, informação errada num comércio, planos ou sugestão? Escreva abaixo e respondemos no seu e-mail.</p>
    <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
    <form method="post">
      <?= campo_csrf() ?>
      <label for="nome">Seu nome</label>
      <input id="nome" name="nome" required maxlength="120" autocomplete="name" value="<?= h($f['nome'] ?? '') ?>">
      <label for="email">Seu e-mail</label>
      <input id="email" name="email" type="email" required maxlength="160" autocomplete="email" value="<?= h($f['email'] ?? '') ?>">
      <label for="assunto">Assunto</label>
      <select id="assunto" name="assunto">
        <?php foreach (CONTATO_ASSUNTOS as $k => $rot): ?><option value="<?= $k ?>"<?= $sel('assunto', $k) ?>><?= h($rot) ?></option><?php endforeach ?>
      </select>
      <label for="cidade">Sua cidade (opcional)</label>
      <input id="cidade" name="cidade" maxlength="80" value="<?= h($f['cidade'] ?? '') ?>">
      <label for="mensagem">Mensagem</label>
      <textarea id="mensagem" name="mensagem" rows="7" required maxlength="5000"><?= h($f['mensagem'] ?? '') ?></textarea>
      <div style="position:absolute;left:-5000px" aria-hidden="true"><label for="site_web">Não preencha</label><input id="site_web" name="site_web" tabindex="-1" autocomplete="off"></div>
      <p><button class="botao amarelo">Enviar mensagem</button></p>
      <p class="discreto">Também estamos em: <?= contatos_html() ?></p>
    </form>
  <?php endif ?>
</main>
<?php pagina_fim();
