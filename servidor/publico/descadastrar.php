<?php
// "Não quero mais receber" (21/11, inc/email.php): link do rodapé dos e-mails da equipe. Abrir o link só mostra o botão
// (antivírus e leitores de e-mail abrem links sozinhos); o botão, ou o "Cancelar inscrição" do próprio Gmail
// (List-Unsubscribe-Post, sem sessão), é que descadastra. Avisos automáticos (pagamento, alteração) continuam chegando.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
require_once __DIR__ . '/../inc/email.php';

$email = email_valido((string)($_GET['e'] ?? $_POST['e'] ?? ''));
$valido = $email && email_pronto() && hash_equals(email_token($email), (string)($_GET['t'] ?? $_POST['t'] ?? ''));
$feito = false;
if (e_post() && $valido) {
    q('INSERT IGNORE INTO emails_descadastro (email) VALUES (?)', [$email]);
    q("UPDATE emails SET status = 'pulado' WHERE para = ? AND tipo = 'equipe' AND status = 'fila'", [$email]);
    $feito = true;
    if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') { http_response_code(200); exit('ok'); }
}

pagina_inicio('Não receber mais e-mails | Localiza Fácil', 'Parar de receber os e-mails da equipe do Localiza Fácil.', '', ['noindex' => true]);
?>
<main class="documento">
  <h1>Não receber mais e-mails</h1>
  <?php if (!$valido): ?>
    <div class="aviso erro">Este link não é válido ou está incompleto. Abra de novo pelo botão do e-mail que você recebeu.</div>
  <?php elseif ($feito || email_descadastrado($email)): ?>
    <div class="aviso ok">Pronto: <b><?= h($email) ?></b> não recebe mais os e-mails da equipe do Localiza Fácil.</div>
    <p class="discreto">Avisos do seu próprio comércio (pagamento confirmado, alteração aprovada) continuam chegando.</p>
  <?php else: ?>
    <p>Parar de receber os e-mails da equipe do Localiza Fácil em <b><?= h($email) ?></b>?</p>
    <form method="post">
      <input type="hidden" name="e" value="<?= h($email) ?>"><input type="hidden" name="t" value="<?= h(email_token($email)) ?>">
      <p><button class="botao amarelo">Não quero mais receber</button></p>
    </form>
  <?php endif ?>
</main>
<?php pagina_fim();
