<?php
// Incluir um negócio que não está no app, de graça: só os dados iniciais (os mesmos que os comércios da lista já têm).
// O comércio entra como 'pendente' (fora do app) e importado = 1 (aparece sem plano, como os da lista); um pedido
// tipo 'incluir' avisa os admins, que conferem e publicam em /admin/pedido.php. Fotos e descrição: pelos planos.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
iniciar_sessao();
// incluir negócio: só com a conta Google (para receber o aviso de "aceito" no celular)
require_once __DIR__ . '/../inc/conta-web.php';
$conta = exigir_login_web('Incluir meu negócio', 'Inclua o seu negócio no Localiza Fácil de graça.');

$areas = linhas("SELECT id, nome FROM categorias WHERE ativa = 1 ORDER BY ordem, nome");
// 17/11 (passos 94–96): tipo na caixa de seleção da área (inc/tipos.php), para não criar tipos repetidos
require_once __DIR__ . '/../inc/tipos.php';
$tipos_area = tipos_por_area();
$pronto = valor("SELECT 1 FROM migracoes WHERE nome = '012-pedido-incluir.sql'");
$erro = null;
$enviado = false;
$f = e_post() ? $_POST : ['cidade' => (string)($_GET['cidade'] ?? '')];   // 19/11: ?cidade= vem da busca sem resultado

if (e_post() && $pronto) {
    exigir_post();
    $d = [
        'nome' => txt('comercio', 120), 'tipo' => txt('tipo', 80), 'categoria_id' => (int)($_POST['area'] ?? 0),
        'cidade' => (string)($_POST['cidade'] ?? ''), 'endereco' => txt('endereco', 200), 'bairro' => txt('bairro', 80),
        'telefone' => telefone_normalizado(txt('telefone_comercio', 20)), 'whatsapp' => telefone_normalizado(txt('whatsapp', 20)),
        'instagram' => ltrim((string)txt('instagram', 60), '@') ?: null,
        'email' => email_valido(txt('email_comercio', 160)),   // 22/11 (passo 40): aparece na ficha (botão E-mail)
        'ifood' => link_delivery('ifood', txt('ifood', 300)), 'food99' => link_delivery('food99', txt('food99', 300)),   // 22/11 (passo 41)
    ];
    if (!e_area_comida($d['categoria_id'])) $d['ifood'] = $d['food99'] = null;   // só áreas de comida
    [$d['horarios'], $erro_hor] = horarios_do_post((array)($_POST['hor'] ?? []));
    $d['horario'] = horarios_texto(horarios_ler($d['horarios']));
    $nome = txt('nome', 120);
    $contato = telefone_normalizado(txt('contato', 20));
    // tipo: o da caixa da área; fora da lista só com "Não encontrei o tipo na lista" (o admin confere)
    [$d['tipo'], $tipo_novo, $erro_tipo] = tipo_do_post($tipos_area, $d['categoria_id']);
    if ($erro_hor) $erro = $erro_hor;
    elseif (!empty($erro_tipo)) $erro = $erro_tipo;
    elseif (!$d['nome'] || !$d['tipo'] || !$d['endereco']) $erro = 'Preencha o nome do negócio, o que ele é e o endereço.';
    elseif (!in_array($d['categoria_id'], array_map('intval', array_column($areas, 'id')), true)) $erro = 'Escolha a área do negócio.';
    elseif (!isset(CIDADES[$d['cidade']])) $erro = 'Escolha a cidade.';
    elseif (!$d['telefone'] && !$d['whatsapp']) $erro = 'Coloque o telefone ou o WhatsApp do negócio (com DDD).';
    elseif (txt('email_comercio', 160) && !$d['email']) $erro = 'E-mail do negócio: confira o endereço (ex.: contato@seunegocio.com.br).';
    elseif (e_area_comida($d['categoria_id']) && txt('ifood', 300) && !$d['ifood']) $erro = 'iFood: cole o link da página do seu negócio no iFood (ifood.com.br/delivery/...).';
    elseif (e_area_comida($d['categoria_id']) && txt('food99', 300) && !$d['food99']) $erro = '99Food: cole o link da página do seu negócio no 99Food (99app.com/...).';
    elseif (!$nome || !$contato) $erro = 'Preencha o seu nome e um telefone com DDD para a equipe confirmar.';
    elseif (!empty($_POST['site_web'])) $enviado = true;   // campo escondido preenchido = robô: finge que deu certo
    elseif ((int)valor('SELECT COUNT(*) FROM pedidos WHERE ip = ? AND criado_em > NOW() - INTERVAL 1 DAY', [ip()]) >= 10) $erro = 'Muitos pedidos hoje. Tente amanhã.';
    elseif (valor("SELECT 1 FROM profissionais WHERE cidade = ? AND LOWER(nome) = LOWER(?) AND status IN ('ativo','pendente')", [$d['cidade'], $d['nome']])) {
        $erro = 'Já existe um negócio com esse nome nessa cidade (no app ou esperando conferência). Procure por ele na busca.';
    } else {
        db()->beginTransaction();
        $pid = inserir('profissionais', $d + ['status' => 'pendente', 'importado' => 1]);
        require_once __DIR__ . '/../inc/avisos.php';
        $uid = pedido_usuario_atual();
        $pedido = inserir('pedidos', ['profissional_id' => $pid, 'tipo' => 'incluir', 'nome' => $nome, 'telefone' => $contato,
            'mensagem' => trim(($tipo_novo ? 'TIPO NOVO (fora da lista): ' . $d['tipo'] . "\n" : '') . (txt('mensagem', 900) ?? '')) ?: null, 'ip' => ip()] + ($uid ? ['usuario_id' => $uid] : []));
        db()->commit();
        require_once __DIR__ . '/../inc/avisos.php';
        avisar_admins('Negócio novo para incluir', "$nome quer incluir {$d['nome']} (" . nome_cidade($d['cidade'], true) . ').', ['pedido' => $pedido]);
        $enviado = true;
    }
}

$sel = fn(string $campo, $valor) => (string)($f[$campo] ?? '') === (string)$valor ? ' selected' : '';
pagina_inicio('Incluir meu negócio | Localiza Fácil', 'Inclua o seu negócio no Localiza Fácil de graça.');
?>
<main class="documento">
  <h1>Incluir meu negócio</h1>
  <?php if ($enviado): ?>
    <div class="aviso ok">Recebemos! A equipe do Localiza Fácil vai conferir os dados (normalmente em até 2 dias úteis) e o seu negócio
      aparece no app assim que for aprovado.</div>
    <p>Quer colocar fotos, descrição e o que você vende? Depois que aparecer no app, <a href="<?= h(config()['url']) ?>/busca.php">procure o seu negócio</a> e confira os planos.</p>
  <?php elseif (!$pronto): ?>
    <div class="aviso erro">Esta página ainda está sendo preparada. Tente de novo mais tarde.</div>
  <?php else: ?>
    <p>Incluir é <strong>de graça</strong>. Coloque os dados básicos do seu negócio; a equipe confere antes de aparecer no app.
      Fotos, descrição e a lista do que você vende ou faz ficam nos <a href="<?= h(config()['url']) ?>/planos.php">planos</a>.</p>
    <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
    <form method="post">
      <?= campo_csrf() ?>
      <h2>O negócio</h2>
      <label for="comercio">Nome do negócio</label>
      <input id="comercio" name="comercio" required maxlength="120" value="<?= h($f['comercio'] ?? '') ?>">
      <label for="area">Área</label>
      <select id="area" name="area" required>
        <option value="">Escolha</option>
        <?php foreach ($areas as $a): ?><option value="<?= (int)$a['id'] ?>"<?= $sel('area', $a['id']) ?>><?= h($a['nome']) ?></option><?php endforeach ?>
      </select>
      <?= tipo_campos_html($tipos_area, 'area', empty($f['tipo_novo']) ? (string)($f['tipo'] ?? '') : '', !empty($f['tipo_novo']), (string)($f['tipo_outro'] ?? '')) ?>
      <label for="cidade">Cidade</label>
      <select id="cidade" name="cidade" required>
        <option value="">Escolha</option>
        <?php foreach (CIDADES as $slug => [$nomeCidade, , $uf]): ?><option value="<?= h($slug) ?>"<?= $sel('cidade', $slug) ?>><?= h("$nomeCidade ($uf)") ?></option><?php endforeach ?>
      </select>
      <label for="endereco">Endereço (rua e número)</label>
      <input id="endereco" name="endereco" required maxlength="200" value="<?= h($f['endereco'] ?? '') ?>">
      <label for="bairro">Bairro</label>
      <input id="bairro" name="bairro" maxlength="80" value="<?= h($f['bairro'] ?? '') ?>">
      <label for="telefone_comercio">Telefone do negócio (com DDD)</label>
      <input id="telefone_comercio" name="telefone_comercio" inputmode="tel" maxlength="20" value="<?= h($f['telefone_comercio'] ?? '') ?>">
      <label for="whatsapp">WhatsApp do negócio (com DDD)</label>
      <input id="whatsapp" name="whatsapp" inputmode="tel" maxlength="20" value="<?= h($f['whatsapp'] ?? '') ?>">
      <?= horarios_form(horarios_ler(horarios_do_post((array)($f['hor'] ?? []))[0])) ?>
      <label for="instagram">Instagram (opcional)</label>
      <input id="instagram" name="instagram" maxlength="60" placeholder="@seunegocio" value="<?= h($f['instagram'] ?? '') ?>">
      <?php $ids_comida = array_values(array_filter(array_map('intval', array_column($areas, 'id')), 'e_area_comida')); ?>
      <div id="campos-comida" data-areas="<?= h(implode(',', $ids_comida)) ?>"<?= in_array((int)($f['area'] ?? 0), $ids_comida, true) ? '' : ' hidden' ?>>
        <label for="ifood">Link da página no iFood (opcional)</label>
        <input id="ifood" name="ifood" maxlength="300" inputmode="url" placeholder="https://www.ifood.com.br/delivery/..." value="<?= h($f['ifood'] ?? '') ?>">
        <label for="food99">Link da página no 99Food (opcional)</label>
        <input id="food99" name="food99" maxlength="300" inputmode="url" placeholder="https://99app.com/..." value="<?= h($f['food99'] ?? '') ?>">
        <p class="dica">No app do iFood ou do 99Food, abra a sua loja, toque em Compartilhar e copie o link.</p>
      </div>
      <script>(function () {   // 22/11 (passo 41): iFood e 99Food só para áreas de comida
        var a = document.getElementById('area'), c = document.getElementById('campos-comida'), ids = c.dataset.areas.split(',');
        a.addEventListener('change', function () { c.hidden = ids.indexOf(a.value) < 0; });
      })();</script>
      <label for="email_comercio">E-mail do negócio (opcional, aparece na ficha)</label>
      <input id="email_comercio" name="email_comercio" type="email" inputmode="email" maxlength="160" placeholder="contato@seunegocio.com.br" value="<?= h($f['email_comercio'] ?? '') ?>">
      <div hidden><label for="site_web">Não preencha</label><input id="site_web" name="site_web" tabindex="-1" autocomplete="off"></div>

      <h2>Você</h2>
      <label for="nome">Seu nome</label>
      <input id="nome" name="nome" required maxlength="120" value="<?= h($f['nome'] ?? ($conta['nome'] ?? '')) ?>">
      <label for="contato">Seu telefone ou WhatsApp (com DDD), para a equipe confirmar</label>
      <input id="contato" name="contato" required inputmode="tel" maxlength="20" value="<?= h($f['contato'] ?? '') ?>">
      <label for="mensagem">Recado para a equipe (opcional)</label>
      <textarea id="mensagem" name="mensagem" maxlength="1000" rows="3"><?= h($f['mensagem'] ?? '') ?></textarea>
      <p><button class="botao amarelo">Enviar para conferência</button></p>
      <p class="discreto">Ao enviar, você confirma que é responsável pelo negócio e concorda com os <a href="<?= h(config()['url']) ?>/termos.php">termos</a>.</p>
    </form>
  <?php endif ?>
</main>
<?php pagina_fim();
