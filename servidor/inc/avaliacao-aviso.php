<?php
// 21/11 (passo 33, pedido do usuário): quando uma ficha recebe uma avaliação (app ou site, os dois usam api/avaliar.php),
// o dono é avisado no celular (conta do app ligada ao comércio) e por e-mail (o do pedido/cadastro), e os admins
// recebem um aviso no celular. Trocar só uma letra do comentário em seguida não avisa de novo (10 min).
// Nunca derruba a avaliação: qualquer falha só vai para o registro de erros.
declare(strict_types=1);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/guia.php';   // caminho_ficha()

const AVALIACAO_AVISO_INTERVALO = 600;   // segundos: edição da mesma avaliação logo depois não avisa de novo

/** Contas do app e e-mails dos donos do comércio (pedido pago, acesso ao painel liberado ou cadastro aprovado). */
function donos_da_ficha(int $profissional_id): array {
    $pedidos = linhas("SELECT DISTINCT pe.id, pe.usuario_id, pe.email, pe.nome, u.email AS email_conta FROM pedidos pe
        LEFT JOIN usuarios u ON u.id = pe.usuario_id
        LEFT JOIN acessos_dono a ON a.pedido_id = pe.id AND a.ativo = 1
        WHERE pe.profissional_id = ? AND (a.id IS NOT NULL OR pe.mp_status IN ('authorized','approved') OR (pe.tipo = 'incluir' AND pe.status = 'feito'))",
        [$profissional_id]);
    $contas = $emails = [];
    foreach ($pedidos as $p) {
        if ($p['usuario_id']) $contas[(int)$p['usuario_id']] = true;
        $e = email_valido($p['email']) ?? email_valido($p['email_conta']);
        if ($e) $emails[$e] ??= $p['nome'];
    }
    return [array_keys($contas), $emails];
}

/**
 * Chamada por api/avaliar.php depois de gravar. $antes = a avaliação dessa pessoa antes de gravar (null se é nova).
 */
function avisar_avaliacao(array $prof, array $autor, int $estrelas, ?string $comentario, ?array $antes): void {
    try {
        $mudou = !$antes || (int)$antes['estrelas'] !== $estrelas || (string)$antes['comentario'] !== (string)$comentario;
        if (!$mudou) return;
        $pid = (int)$prof['id'];
        $arq = config()['dados_dir'] . '/avaliacoes-avisadas.json';
        $vistos = is_file($arq) ? (json_decode((string)@file_get_contents($arq), true) ?: []) : [];
        $chave = $pid . '-' . (int)$autor['id'];
        if (($vistos[$chave] ?? 0) > time() - AVALIACAO_AVISO_INTERVALO) return;
        $vistos[$chave] = time();
        $vistos = array_filter($vistos, fn($t) => $t > time() - 86400);
        @file_put_contents($arq, json_encode($vistos), LOCK_EX);

        require_once __DIR__ . '/avisos.php';
        require_once __DIR__ . '/email.php';
        $nova = $antes === null;
        $est = str_repeat('★', $estrelas) . str_repeat('☆', 5 - $estrelas);
        $quem = trim((string)($autor['nome'] ?? '')) !== '' ? preg_split('/\s+/', trim((string)$autor['nome']))[0] : 'Alguém';
        $trecho = $comentario !== null && $comentario !== '' ? '"' . mb_substr($comentario, 0, 110) . (mb_strlen($comentario) > 110 ? '…' : '') . '"' : 'Sem comentário.';
        $link = url_publica(caminho_ficha($pid, (string)$prof['nome'], (string)$prof['cidade']));

        // dono: no celular e por e-mail (o próprio dono avaliando a ficha dele não recebe)
        [$contas, $emails] = donos_da_ficha($pid);
        $autor_email = mb_strtolower((string)($autor['email'] ?? ''));
        foreach ($contas as $u) {
            if ($u === (int)$autor['id']) continue;
            avisar_usuario($u, ($nova ? '⭐ Nova avaliação: ' : '⭐ Avaliação alterada: ') . $estrelas . ($estrelas === 1 ? ' estrela' : ' estrelas'),
                mb_substr($prof['nome'] . ': ' . $trecho, 0, 178), ['tela' => 'profissional', 'id' => $pid]);
        }
        foreach ($emails as $e => $nome) {
            if ($e === $autor_email) continue;
            email_enfileirar($e, $nome, ($nova ? 'Nova avaliação' : 'Avaliação alterada') . " de $estrelas " . ($estrelas === 1 ? 'estrela' : 'estrelas') . ": {$prof['nome']}",
                "Olá {nome},\n\n" . ($nova ? "$quem avaliou" : "$quem mudou a avaliação de") . " {$prof['nome']} no Localiza Fácil:\n\n"
                . "$est ($estrelas de 5)\n" . ($comentario ? "\"$comentario\"\n" : "Sem comentário.\n")
                . "\nVeja na sua ficha:\n$link\n\n"
                . "Se a avaliação for falsa, ofensiva ou de quem nunca foi cliente, responda este e-mail que a equipe confere.\n\nEquipe Localiza Fácil",
                'aviso');
        }

        // admins: no celular
        avisar_admins('⭐ ' . ($nova ? 'Avaliação nova' : 'Avaliação alterada') . " ($estrelas/5): " . mb_substr((string)$prof['nome'], 0, 50),
            mb_substr("$quem: $trecho" . ($contas || $emails ? '' : ' · comércio sem dono cadastrado'), 0, 178),
            ['tela' => 'profissional', 'id' => $pid]);   // abre a ficha no app, onde a avaliação aparece
    } catch (Throwable $e) {
        error_log('localiza-facil aviso avaliacao: ' . $e->getMessage());
    }
}
