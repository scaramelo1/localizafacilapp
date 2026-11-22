<?php
// 22/11 (passo 45, pedido do usuário: "semanal, notificação e e-mail"): toda semana cada comércio Destaque em dia recebe
// no celular (contas do app dos donos) e por e-mail um lembrete do catálogo:
//   - com itens: "Seu cardápio está atualizado?" (última mudança, link para editar);
//   - sem itens: convite para montar (aparece numa aba da ficha e na busca).
// Cron: segunda-feira de manhã (/etc/cron.d/guia-novo). --teste só lista, não manda nada.
//   php servidor/catalogo-semanal.php [--teste]
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';
require_once __DIR__ . '/inc/avaliacao-aviso.php';   // donos_da_ficha()
require_once __DIR__ . '/inc/avisos.php';
require_once __DIR__ . '/inc/email.php';
$teste = in_array('--teste', $argv, true);
$link = url_publica('painel/catalogo.php');

$fichas = linhas("SELECT p.id, p.nome, p.tipo, p.cidade, p.catalogo_itens, p.catalogo_em, c.slug AS categoria_slug
    FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE p.status = 'ativo' AND " . SQL_DESTAQUE);
$push = $mails = 0;
foreach ($fichas as $p) {
    [$contas, $emails] = donos_da_ficha((int)$p['id']);
    if (!$contas && !$emails) { echo "{$p['id']} {$p['nome']}: sem dono cadastrado\n"; continue; }
    $m = catalogo_modelo($p);
    // como falar de cada catálogo: [o seu X, Seu X está / Suas X estão]
    [$seu, $pergunta] = match ($m['chave']) {
        'comida' => ['o seu cardápio', 'Seu cardápio está em dia?'],
        'mercado' => ['as suas ofertas', 'Suas ofertas estão em dia?'],
        'bebidas' => ['as suas bebidas', 'Suas bebidas e preços estão em dia?'],
        'pecas' => ['as suas peças', 'Suas peças e preços estão em dia?'],
        default => ['os seus produtos', 'Seus produtos e preços estão em dia?'],
    };
    $itens = (int)$p['catalogo_itens'];
    if ($itens > 0) {
        $quando = $p['catalogo_em'] ? ' Última mudança em ' . date('d/m', strtotime((string)$p['catalogo_em'])) . '.' : '';
        $titulo = "📋 $pergunta";
        $texto = "{$p['nome']}: confira os preços e marque o que acabou como Esgotado.$quando Leva 1 minuto.";
        $assunto = "Lembrete da semana: confira $seu no Localiza Fácil";
        $corpo = "Olá {nome},\n\nPassando para lembrar: $seu de {$p['nome']} " . ($m['chave'] === 'comida' ? 'está' : 'estão') . " no ar com $itens "
            . ($itens === 1 ? 'item' : 'itens') . ".$quando\n\nPreço velho ou item que acabou desanima o cliente. Confira e atualize em 1 minuto:\n$link\n\n"
            . "Dica: marque \"Esgotado\" em vez de apagar, e use \"Mais pedido\" nos itens que mais saem.\n\nEquipe Localiza Fácil";
    } else {
        $titulo = "🛒 Mostre $seu no Localiza Fácil";
        $texto = "{$p['nome']}: o seu Destaque inclui uma aba com produtos e preços na ficha, e você aparece na busca por eles.";
        $assunto = "O seu Destaque já inclui " . mb_strtolower($m['curto']) . " na ficha";
        $corpo = "Olá {nome},\n\nO plano Destaque de {$p['nome']} inclui uma aba com $seu na sua ficha, no site e no app.\n\n"
            . "Quem procurar por um item (um lanche, uma peça, um produto) acha o seu comércio na busca, "
            . "e cada item tem o botão \"Pedir\", que abre o seu WhatsApp já com o nome do item.\n\nMonte agora, já começa com as seções prontas:\n$link\n\nEquipe Localiza Fácil";
    }
    echo "{$p['id']} {$p['nome']} ($itens itens): " . count($contas) . ' celular(es), ' . count($emails) . " e-mail(s)\n";
    if ($teste) continue;
    foreach ($contas as $u) $push += avisar_usuario($u, $titulo, mb_substr($texto, 0, 178), ['tela' => 'profissional', 'id' => (int)$p['id'], 'url' => $link]);
    foreach ($emails as $e => $nome) if (email_enfileirar($e, $nome, $assunto, $corpo, 'aviso')) $mails++;
}
echo ($teste ? '[teste] ' : '') . count($fichas) . " Destaque; avisos entregues: $push; e-mails na fila: $mails\n";
