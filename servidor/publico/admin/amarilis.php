<?php
// Amarilis no WhatsApp (passo 85, inc/amarilis.php): situação das chaves, liga/desliga, conversa de teste com a IA
// (mesmas regras do WhatsApp, recado não manda e-mail), conversas recebidas (?c=) e recados aos donos.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/amarilis.php';
$admin = exigir_admin();

if (!amarilis_pronta()) {
    admin_inicio('Amarilis', $admin);
    echo '<h1>🤖 Amarilis</h1><p class="aviso erro">Falta aplicar a mudança 047 do banco (bash servidor/instalar.sh).</p>';
    admin_fim();
    exit;
}
$teste = amarilis_conversa('teste', 'admin-' . (int)$admin['id'], (string)($admin['nome'] ?? 'Admin'));

if (e_post()) {
    exigir_post();
    $acao = (string)($_POST['acao'] ?? '');
    if ($acao === 'perguntar') {
        $t = trim((string)($_POST['texto'] ?? ''));
        if ($t !== '' && amarilis_receber($teste, $t)) {
            @set_time_limit(90);
            amarilis_processar((int)$teste['id']);
        }
        ir('admin/amarilis.php#teste');
    } elseif ($acao === 'limpar') {
        q('DELETE FROM amarilis_mensagens WHERE conversa_id = ?', [$teste['id']]);
        q('UPDATE amarilis_conversas SET cidade = NULL WHERE id = ?', [$teste['id']]);
        q('DELETE FROM amarilis_recados WHERE conversa_id = ?', [$teste['id']]);
        ir('admin/amarilis.php#teste');
    } elseif ($acao === 'ligar' || $acao === 'desligar') {
        amarilis_ligar($acao === 'ligar');
        registrar((int)$admin['id'], $acao === 'ligar' ? 'ligou_amarilis' : 'desligou_amarilis', null, 'WhatsApp');
        flash($acao === 'ligar' ? 'Amarilis ligada no WhatsApp.' : 'Amarilis desligada: as mensagens do WhatsApp ficam sem resposta.');
        ir('admin/amarilis.php');
    } elseif (($acao === 'bloquear' || $acao === 'desbloquear') && ($cid = (int)($_POST['c'] ?? 0))) {
        q('UPDATE amarilis_conversas SET bloqueada = ? WHERE id = ? AND canal = ?', [$acao === 'bloquear' ? 1 : 0, $cid, 'whatsapp']);
        registrar((int)$admin['id'], $acao . '_amarilis', null, 'conversa ' . $cid);
        flash($acao === 'bloquear' ? 'Número bloqueado: a Amarilis não responde mais.' : 'Número desbloqueado.');
        ir('admin/amarilis.php?c=' . $cid);
    }
    ir('admin/amarilis.php');
}

/** Balões da conversa (pessoa à esquerda, Amarilis à direita), com o que a IA consultou. */
function amarilis_balao_html(array $msgs): string {
    if (!$msgs) return '<p class="discreto">Nenhuma mensagem ainda.</p>';
    $o = '<div class="amarilis-conversa">';
    foreach ($msgs as $m) {
        $eu = $m['de'] === 'amarilis';
        $texto = nl2br(preg_replace('~(https?://[^\s<]+)~', '<a href="$1" target="_blank" rel="noopener">$1</a>', h($m['texto'])));
        $texto = preg_replace('/\*([^*\n]+)\*/u', '<b>$1</b>', $texto);
        $info = [datahora_br($m['criado_em'])];
        if ($eu) {
            foreach (json_decode((string)$m['ferramentas'], true) ?: [] as $f)
                $info[] = '🔎 ' . h($f['f']) . ' ' . h(json_encode($f['args'], JSON_UNESCAPED_UNICODE)) . ($f['n'] !== null ? ' → ' . (int)$f['n'] : '') . ($f['erro'] ? ' (' . h($f['erro']) . ')' : '');
            if ($m['ms']) $info[] = round($m['ms'] / 1000, 1) . ' s · ' . (int)$m['chamadas_ia'] . ' chamada(s) · ' . h((string)$m['modelo']);
            if ($m['erro']) $info[] = '<span class="erro">⚠️ ' . h($m['erro']) . '</span>';
        } elseif ($m['status'] !== 'ok') $info[] = h($m['status']);
        $o .= '<div class="balao ' . ($eu ? 'eu' : 'pessoa') . '"><div>' . $texto . '</div><small>' . implode('<br>', $info) . '</small></div>';
    }
    return $o . '</div>';
}

$cid = (int)($_GET['c'] ?? 0);
$conv = $cid ? linha("SELECT * FROM amarilis_conversas WHERE id = ? AND canal = 'whatsapp'", [$cid]) : null;
admin_inicio('Amarilis', $admin);
?>
<style>
.amarilis-conversa{display:flex;flex-direction:column;gap:10px;margin:12px 0;max-height:70vh;overflow-y:auto;padding:12px;background:#ECE5DD;border-radius:12px}
.amarilis-conversa .balao{max-width:85%;padding:8px 12px;border-radius:10px;background:#fff;word-wrap:break-word;color:#111}
.amarilis-conversa .balao.eu{align-self:flex-end;background:#DCF8C6}
.amarilis-conversa .balao small{display:block;margin-top:6px;color:#667;font-size:.75rem;line-height:1.3}
.amarilis-conversa .erro{color:#b00020}
.amarilis-sit li{margin:4px 0}
</style>
<?php if ($conv):
    $msgs = linhas('SELECT * FROM amarilis_mensagens WHERE conversa_id = ? ORDER BY id DESC LIMIT 200', [$conv['id']]);
    $recs = linhas('SELECT r.*, p.nome AS comercio, p.cidade FROM amarilis_recados r JOIN profissionais p ON p.id = r.profissional_id WHERE r.conversa_id = ? ORDER BY r.id DESC', [$conv['id']]); ?>
  <p><a href="amarilis.php">&larr; Amarilis</a></p>
  <h1>💬 <?= h($conv['nome'] ?: 'Sem nome') ?></h1>
  <p>WhatsApp <?= h(telefone_formatado(preg_replace('/^55/', '', (string)$conv['contato']))) ?> · desde <?= datahora_br($conv['criado_em']) ?>
    · cidade: <?= $conv['cidade'] ? adm_cidade($conv['cidade'], null, true) : '—' ?><?= $conv['bloqueada'] ? ' · <strong>🚫 bloqueado</strong>' : '' ?></p>
  <?= amarilis_balao_html(array_reverse($msgs)) ?>
  <?php if ($recs): ?><h2>Recados</h2><ul><?php foreach ($recs as $r): ?>
    <li><?= datahora_br($r['criado_em']) ?> · <?= adm_comercio((int)$r['profissional_id'], $r['comercio'], $r['cidade']) ?> · <?= h($r['nome']) ?>: <?= h($r['texto']) ?></li>
  <?php endforeach ?></ul><?php endif ?>
  <form method="post"><?= campo_csrf() ?><input type="hidden" name="c" value="<?= (int)$conv['id'] ?>">
    <?php if ($conv['bloqueada']): ?><button class="botao pequeno contorno" name="acao" value="desbloquear">Desbloquear este número</button>
    <?php else: ?><button class="botao pequeno contorno" name="acao" value="bloquear" onclick="return confirm('A Amarilis não vai mais responder este número. Bloquear?')">🚫 Bloquear este número</button><?php endif ?>
  </form>
<?php else:
    $n = amarilis_numeros();
    $tem_ia = amarilis_tem_ia();
    $tem_wa = amarilis_tem_whatsapp();
    $tem_seg = trim((string)amarilis_cfg('wa_segredo_app', '')) !== '';
    $tem_ver = trim((string)amarilis_cfg('wa_verificar', '')) !== '';
    $ligada = amarilis_ligada();
    $ok = fn(bool $b) => $b ? '✅' : '❌'; ?>
  <h1>🤖 Amarilis no WhatsApp</h1>
  <p>Assistente que responde os moradores pelo WhatsApp com os dados do guia: procura comércios, diz quem está aberto agora,
    mostra o cardápio/catálogo dos Destaque e manda recado ao dono. A IA é o Gemini (cota grátis do Google).</p>

  <div class="quadro">
    <h2>Situação</h2>
    <ul class="amarilis-sit">
      <li><?= $ok($tem_ia) ?> Chave do Gemini (<code>gemini_chave</code>)<?= $tem_ia ? ' · modelos: ' . h(implode(', ', amarilis_modelos())) : '' ?></li>
      <li><?= $ok($tem_wa) ?> Token e número do WhatsApp (<code>wa_token</code>, <code>wa_numero_id</code>)</li>
      <li><?= $ok($tem_seg) ?> Segredo do app da Meta (<code>wa_segredo_app</code>), confere que a mensagem veio mesmo do WhatsApp</li>
      <li><?= $ok($tem_ver) ?> Token de verificação (<code>wa_verificar</code>)</li>
      <li>Webhook para a Meta: <code><?= h(url_publica('whatsapp.php')) ?></code> · campo assinado: <code>messages</code></li>
      <li><strong><?= $ligada ? '🟢 Ligada no WhatsApp' : '⚪ Desligada no WhatsApp' ?></strong> (a conversa de teste abaixo funciona sempre)</li>
    </ul>
    <form method="post"><?= campo_csrf() ?>
      <?php if ($ligada): ?><button class="botao contorno" name="acao" value="desligar">Desligar no WhatsApp</button>
      <?php else: ?><button class="botao" name="acao" value="ligar"<?= $tem_ia && $tem_wa && $tem_seg ? '' : ' disabled title="Faltam chaves no config.php"' ?>>Ligar no WhatsApp</button><?php endif ?>
    </form>
    <p class="discreto">Hoje: <?= $n['recebidas_hoje'] ?? 0 ?> mensagens de <?= $n['pessoas_hoje'] ?? 0 ?> pessoa(s) · <?= $n['ia_hoje'] ?? 0 ?> chamadas ao Gemini (com o teste)
      · <?= $n['erros_hoje'] ?? 0 ?> com erro · tempo médio <?= isset($n['ms_media']) && $n['ms_media'] ? round($n['ms_media'] / 1000, 1) . ' s' : '—' ?>.
      Total: <?= $n['pessoas_total'] ?? 0 ?> pessoas, <?= $n['recados_total'] ?? 0 ?> recados.</p>
  </div>

  <h2 id="teste">Testar a conversa</h2>
  <p class="discreto">Você conversa com a Amarilis como se fosse pelo WhatsApp. Recados ficam registrados aqui, mas o e-mail não vai ao dono.</p>
  <?= amarilis_balao_html(linhas('SELECT * FROM amarilis_mensagens WHERE conversa_id = ? ORDER BY id', [$teste['id']])) ?>
  <form method="post" class="quadro" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Pensando...'">
    <?= campo_csrf() ?><input type="hidden" name="acao" value="perguntar">
    <label for="texto">Sua mensagem</label>
    <textarea id="texto" name="texto" rows="2" required placeholder="Ex.: tem pizzaria aberta agora em Poços?" autofocus></textarea>
    <p><button class="botao"<?= $tem_ia ? '' : ' disabled title="Falta a chave do Gemini"' ?>>Enviar</button></p>
  </form>
  <form method="post"><?= campo_csrf() ?><button class="botao pequeno contorno" name="acao" value="limpar">Começar a conversa de novo</button></form>

  <h2>Conversas no WhatsApp</h2>
  <?php $lista = linhas("SELECT c.*, (SELECT COUNT(*) FROM amarilis_mensagens m WHERE m.conversa_id = c.id AND m.de = 'pessoa') AS n,
      (SELECT texto FROM amarilis_mensagens m WHERE m.conversa_id = c.id AND m.de = 'pessoa' ORDER BY m.id DESC LIMIT 1) AS ultima
      FROM amarilis_conversas c WHERE c.canal = 'whatsapp' ORDER BY c.atualizado_em DESC LIMIT 100");
  if (!$lista): ?><p class="discreto">Nenhuma conversa ainda.</p>
  <?php else: ?>
  <table class="tabela"><thead><tr><th>Quando</th><th>Pessoa</th><th>Cidade</th><th>Msgs</th><th>Última mensagem</th></tr></thead><tbody>
    <?php foreach ($lista as $c): ?><tr>
      <td><?= datahora_br($c['atualizado_em']) ?></td>
      <td><a href="?c=<?= (int)$c['id'] ?>"><?= h($c['nome'] ?: telefone_formatado(preg_replace('/^55/', '', (string)$c['contato']))) ?></a><?= $c['bloqueada'] ? ' 🚫' : '' ?></td>
      <td><?= $c['cidade'] ? adm_cidade($c['cidade'], null, true) : '—' ?></td>
      <td><?= (int)$c['n'] ?></td>
      <td><?= h(mb_strimwidth((string)$c['ultima'], 0, 80, '…')) ?></td>
    </tr><?php endforeach ?>
  </tbody></table>
  <?php endif ?>
<?php endif;
admin_fim();
